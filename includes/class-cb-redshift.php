<?php

use Aws\Credentials\CredentialProvider;
use Aws\S3\S3Client;

/**
 * Routines for managing redshift queries.
 *
 * Queries will have the text $schema and $bucket replaced by the appropriate values.
 *
 * Usage:
 *
 *   $rs = new CBRedshift("host=localhost username=foo password=bar dbname=cb", "dev");
 *   $rs->upload_to_s3('example.csv.gz', ['col1'=>'val1', 'col2'=>1], ['col1', 'col2']);
 *   $rs->execute_file('load_example.sql');
 *   $rs->execute_query('SELECT * FROM example;');
 *
 */

class CBRedshift {

	protected static $default_schema_production = 'production';
	protected static $default_schema_dev = 'dev';
	protected static $default_bucket_production = 'challengebox-redshift';
	protected static $default_bucket_dev = 'challengebox-redshift-dev';

	protected static $default_local_s3_dir = '/tmp/s3';

	protected $debug_enabled;
	protected $debug_func;
	protected $db;
	protected $schema;
	protected $bucket;
	protected $s3Client;

	public static function get_defaults() {
		$debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
		$mode = $debug_enabled ? 'dev' : 'production';
		return (object) array(
			'debug_enabled' => $debug_enabled,
			'schema' => CBRedshift::${'default_schema_'.$mode},
			'bucket' => CBRedshift::${'default_bucket_'.$mode},
		);
	}

	public function debug($thing) {
		if ($this->debug_enabled && is_callable($this->debug_func)) {
			return call_user_func($this->debug_func, $thing);
		}
	}

	public function __construct($schema=null, $bucket=null, $cxn_string=null, $debug=false, $local_s3_dir=null) {
		$default = CBRedshift::get_defaults();
		$this->debug_enabled = is_callable($debug) ? true : (bool) $debug;
		$this->debug_func = is_callable($debug) ? $debug : 'var_dump';
		$this->debug($default);

		$this->local_s3_dir = isset($local_s3_dir) ? $local_s3_dir : CBRedshift::$default_local_s3_dir;

		$this->schema = isset($schema) ? $schema : $default->schema;
		$this->bucket = isset($bucket) ? $bucket : $default->bucket;
		if (isset($cxn_string)) {
			$cxn_string = trim(file_exists($cxn_string) ? file_get_contents($cxn_string) : $cxn_string);
		} else {
			$cxn_string = trim(file_get_contents('/home/www-data/.aws/redshift.string'));
		}

		// Connect to s3
		$provider = CredentialProvider::ini('redshift', '/home/www-data/.aws/credentials');
		$provider = CredentialProvider::memoize($provider);
		$this->s3Client = new S3Client(array(
			//'profile' => 'redshift',
			'region' => 'us-east-1',
			'version' => 'latest',
			'credentials' => $provider
		));

		// Connect to Database
		$this->debug("Connecting to redshift using: $cxn_string");
		$this->db = pg_connect($cxn_string);
		if (!$this->db) throw new Exception(pg_last_error());
		$this->execute_query("SET search_path TO $this->schema;");
	}

	public function execute_query($query, $replace=true) {
		if ($replace) {
			$query = str_replace('$schema', $this->schema, $query);
			$query = str_replace('$bucket', $this->bucket, $query);
		}
		$this->debug("Executing Redshift query:\n$query");
		$result = pg_query($query);
		if (!$result) throw new Exception(pg_last_error());
		return pg_fetch_all($result);
	}

	public function load_query($filename) {
		$redshift_dir = realpath(__DIR__.'/../redshift');
		$this->debug("Loading query from $redshift_dir/$filename");
		return file_get_contents("$redshift_dir/$filename");
	}

	public function execute_file($filename) {
		$query = $this->load_query($filename);
		return $this->execute_query($query);
	}

	public function upload_to_s3($file_path, $results, $columns, $gzip = true) {
		$fp = fopen('php://temp', 'rw');
		WP_CLI\Utils\write_csv($fp, $results, $columns);
		rewind($fp);
		if ($gzip) $content = gzencode(stream_get_contents($fp));
		else $content = stream_get_contents($fp);
		$bytes = strlen($content);
		$this->debug("Uploading $bytes bytes to s3://$this->bucket/$file_path");
		$result = $this->s3Client->putObject([
				'Bucket' => $this->bucket,
				'Key'    => "$file_path",
				'Body'   => $content
		]);
		fclose($fp);
	}

	//
	// MySQL interface (for loading redshift views back to local db)
	//

	public function download_from_s3($file_path) {
		$dir = implode('/', array(
			$this->local_s3_dir,
			$this->bucket,
			dirname($file_path)
		));
		mkdir($dir, 0755, true);
		$result = $this->s3Client->getObject([
				'Bucket' => $this->bucket,
				'Key'    => "$file_path",
				'SaveAs' => "/tmp/s3/$this->bucket/$file_path",
		]);
	}

	public function load_mysql_query($filename) {
		$mysql_dir = realpath(__DIR__.'/../mysql');
		$this->debug("Loading query from $mysql_dir/$filename");
		return file_get_contents("$mysql_dir/$filename");
	}

	public function execute_mysql_file($filename) {
		$query = $this->load_mysql_query($filename);
		return $this->execute_mysql_query($query);
	}

	public function execute_mysql_query($query, $replace=true) {
		global $wpdb;
		if ($replace) {
			$query = str_replace('$schema', $this->schema, $query);
			$query = str_replace('$bucket', $this->bucket, $query);
		}
		$queries = explode(';', $query);
		$results = array();
		foreach ($queries as $q) {
			if (!strlen(trim($q))) continue;
			$this->debug("Executing MySQL query:\n$q");
			$result = $wpdb->query($q);
			if (false === $result) throw new Exception($wpdb->last_error);
			array_push($results, $result);
		}
		if (sizeof($results) > 1) {
			return $results;
		} else {
			return $results[0];
		}
	}

	/**
	 * Loads all tables from s3. Old tables renamed to *_old.
	 */
	public function get_load_query() {
		return implode("\n", array(
			$this->load_query('load_box_orders.sql'),
			$this->load_query('load_charges.sql'),
			$this->load_query('load_refunds.sql'),
			$this->load_query('load_renewal_orders.sql'),
			$this->load_query('load_shop_orders.sql'),
			$this->load_query('load_subscription_events.sql'),
			$this->load_query('load_subscriptions.sql'),
			$this->load_query('load_users.sql'),
			$this->load_query('load_fitness_data.sql'),
		));
	}

	/**
	 * Query to drop *_old tables and reload views after data has been loaded.
	 */
	public function get_cleanup_query() {
		$query = implode("\n", array(
			$this->load_query('drop_old_views.sql'),
			$this->load_query('views.box_credit_ledger.sql'),
			$this->load_query('views.subscription_churn.sql'),
			$this->load_query('views.subscription_status.sql'),
			$this->load_query('views.box_churn.sql'),
			$this->load_query('views.monthly_analytics.sql'),
			$this->load_query('views.fitness_improvement.sql'),
		));
		// Filter out any internal transactions
		$query = str_replace('BEGIN;', '', $query);
		$query = str_replace('COMMIT;', '', $query);
		return $query;
	}

	/**
	 * Loads all data from s3 and refreshes views.
	 */
	public function reload_all() {
		$query = implode("\n", array(
			$this->get_load_query(),
			$this->get_cleanup_query(),
		));
		$this->execute_query($query);
	}

	/**
	 * Cleans up *_old tabes and refreshes views after data has been loaded.
	 */
	public function cleanup_after_load() {
		$this->execute_query($this->get_cleanup_query());
	}
}
