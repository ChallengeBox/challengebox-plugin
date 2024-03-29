<?php
namespace ChallengeBox\Includes\Utilities;

class BaseFactory extends BaseSingleton
{
	protected $wpdb;
	
	/**
	 * get the global wpdb
	 */
	public function getWpdb() {
		
		if (is_null($this->wpdb)) {
			global $wpdb;
			$this->wpdb = $wpdb; 
		}
		
		return $this->wpdb;
	}
	
	/**
	 * generate
	 */
	public function generate($className, $params = array())
	{
		// php 7
		//return new $className(... $params);
		
		// php 5
		$reflect = new \ReflectionClass($className);
		return $reflect->newInstanceArgs($params);
	}
}