<?php

/**
 * Routines for generating queries from redshift.
 */

class CBRedshift {

	protected $db;
	protected $schema;

	public function __construct($string=false, $schema=false) {
		$string_file = '/home/www-data/.aws/redshift.string';
		$string = $string ? $string : file_get_contents($string_file);
		$this->db = pg_connect($string);
		$default_schema = WP_DEBUG ? 'dev' : 'production';
		$this->schema = $schema ? $schema : $default_schema;
		if (!$this->db) throw new Exception(pg_last_error());
	}

	public function execute_query($query) {
		if (WP_DEBUG) var_dump($query);
		$result = pg_query($query);
		if (!$result) throw new Exception(pg_last_error());
		return pg_fetch_all($result);
	}		
}
