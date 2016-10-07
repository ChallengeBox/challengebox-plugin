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

	public function __construct($schema=null, $bucket=null, $cxn_string=null, $debug_func='var_dump') {
		$default = CBRedshift::get_defaults();
		$this->debug_enabled = $default->debug_enabled;
		$this->debug_func = $debug_func;
		$this->debug($default);

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
		$this->debug("Executing query:\n$query");
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

	public function cleanup_load() {
		$query = implode("\n", array(
			$this->load_query('drop_old_views.sql'),
			$this->load_query('views.box_credit_ledger.sql'),
			$this->load_query('views.monthly_analytics.sql'),
			$this->load_query('views.subscription_churn.sql'),
			$this->load_query('views.subscription_status.sql'),
		));
		// Filter out any transactions
		$query = str_replace('BEGIN;', '', $query);
		$query = str_replace('COMMIT;', '', $query);
		// And execute it inside our own transaction
		$this->execute_query($query);
	}
}
