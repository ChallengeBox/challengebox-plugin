<?php

/**
 * CBRawTrackingData
 * 
 * @author sheeleyb
 *
 * TODO: recommend adding a caching layer (memcached or redis)
 * to help speed processing here by reducing DB hits.
 */
class CBRawTrackingData 
{
	private $table_name = 'raw_tracking_data';
			
	private $user_id;
	private $date;
	private $source;
	
	private $data;
	private $data_decoded;
	
	private $create_date;
	private $last_modified;
	
	private $wpdb;
	
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
	
	private function getDataDecoded() {
		
		if (is_null($this->dataDecoded)) {
			$this->dataDecoded = json_decode($this->data, true);
		}
		
		return $this->dataDecoded;
	}
	
	public function __construct($userId, $date, $source, $data, $createDate = null, $lastModified = null) {
		
		$this->user_id = $userId;
		$this->date = $date;
		$this->source = $source;
		
		if (is_array($data) || is_object($data)) {
			$data = json_encode($data);
		}
		
		$this->data = $data;
		
		if (!is_null($createDate)) {
			$this->create_date = $createDate;
		}
		
		if (!is_null($lastModified)) {
			$this->last_modified = $lastModified;
		}
	}
	
	/**
	 * find by user id
	 * 
	 * early version: WiP
	 */
	/*
	public function findByUserId($userId) {

		$wpdb = $this->getWpdb();
		
		$wpdb->prepare('select * from ' . $this->table_name . ' where user_id = ?', array($userId));
		
		$queryResults = $wpdb->get_results($sql);
		
		$objectResults = array();
		foreach ($queryResults as $result) {
			$objectResults[] = new CBRawTrackingData(
				$result->user_id, 
				$result->date, 
				$result->source, 
				$result->data, 
				$result->create_date, 
				$result->last_modified);
		}
		
		return $objectResults;
	}
	*/
	
	/**
	 * save the cb raw-tracking-data record
	 * 
	 * effectively an upsert of a raw-tracking-data.
	 */
	public function save() {

		$wpdb = $this->getWpdb();
		
		// does the record already exist?
		$preparedStatement = $wpdb->prepare(
			'select count(user_id) as num from ' . $this->table_name . ' where user_id = %d and date = %s and source = %s',
			array($this->user_id, $this->date, $this->source)
		);

		$queryResults = $wpdb->get_results($preparedStatement);
		$count = 0;
		foreach ($queryResults as $result) {
			$count = $result->num;
		}
		
		// no? insert a new record
		if ($count == 0) {
			$wpdb->insert(
				$wpdb->prefix . $this->table_name,
				array(
					'user_id' => $this->user_id, 
					'date' => $this->date, 
					'source' => $this->source, 
					'data' => $this->data
				)
			);
	
		// yes? update the record
		} else {
			$wpdb->update(
				$this->table_name,
				array(
					'data' => $this->data
				),
				array(
					'user_id' => $this->user_id, 
					'date' => $this->date, 
					'source' => $this->source
				),
				array("%s"),
				array("%d", "%s", "%s")
			);
		}
		
	}
}