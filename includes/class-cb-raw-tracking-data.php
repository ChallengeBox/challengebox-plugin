<?php
use ChallengeBox\Includes\Utilities\{
	Cache,
	Time
};

/**
 * CBRawTrackingData
 * 
 * @author sheeleyb
 */
class CBRawTrackingData 
{
	const DATE_CACHE_KEY = 'date-cache_key-';
	
	const FITBIT_V1_SOURCE = 'fitbit-1';
	const FITBIT_V2_SOURCE = 'fitbit-2';
	const GARMIN_v1_SOURCE = 'garmin-1';
	
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
	 * build date cache key
	 */
	private function buildDateCacheKey($userId, $date) {
		return self::DATE_CACHE_KEY . $userId . '-' . $date;
	}
	
	/**
	 * get data decoded
	 */
	private function getDataDecoded() {
		
		if (is_null($this->dataDecoded)) {
			$this->dataDecoded = json_decode($this->data, true);
		}
		
		return $this->dataDecoded;
	}
	
	/**
	 * create
	 * 
	 * Builds a CBRawTrackingData object, populates it, saves it to the db, and will update 
	 * the related cache (if available)
	 */
	protected function create($userId, $date, $source, $data, $createDate = null, $lastModified = null) {

		$object = new self($userId, $date, $source, $data);
		$object->save();
		
		$this->updateCache($rawTrackingData);
		
		return $object;
	}
	
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
	 * to cache format
	 */
	protected function toCacheFormat()
	{
		return (object) [
			'user_id' => $this->user_id,
			'date' => $this->date,
			'source' => $this->source,
			'data' => $this->data,
			'create_date' => $this->create_date,
			'last_modified' => $this->last_modified
		];
	}
	
	/**
	 * construct
	 * 
	 * @param unknown $userId
	 * @param unknown $date
	 * @param unknown $source
	 * @param unknown $data
	 * @param unknown $createDate
	 * @param unknown $lastModified
	 */
	public function __construct($userId = null, $date = null, $source = null, $data = null, $createDate = null, $lastModified = null) {
		
		$this->user_id = $userId;
		$this->date = $date;
		$this->source = $source;
		
		if (is_array($data) || is_object($data)) {
			$data = json_encode($data);
		}
		
		$this->data = $data;
		
		$time = Time::getInstance();
		$this->create_date = (is_null($createDate)) ? $time->now() : $createDate;
		$this->last_modified = (is_null($lastModified)) ? $time->now() : $lastModified;
	}
	
	/**
	 * find by user id and dates
	 * 
	 * early version: WiP
	 */
	public function findByUserIdAndDates($userId, $startDate, $endDate)
	{
		// initialize dependencies
		$cache = Cache::getInstance();
		$time = Time::getInstance();
		
		// get all dates
		$dates = $time->getDateRange($startDate, $endDate);
		
		// get cached dates
		$cachedData = array();
		$uncachedDates = array();
		foreach ($dates as $date) {
			$cacheKey = $this->buildDateCacheKey($userId, $date);
			$dateData = $cache->get($cacheKey);
			if (false !== $dateData) {
				$cachedData[$date] = $dateData;
			} else {
				$uncachedDates[] = $date;
			}
		}
		
		// get data from remaining dates
		$numberOfUncachedDates = count($uncachedDates);
		$dateParsedResults = array();
		
		if ($numberOfUncachedDates > 0) {

			// get all records at once
			$wpdb = $this->getWpdb();
			
			$sql = 'select * from ' . $this->table_name . ' where user_id = ? and date in ' .
					'(' . implode(',', array(0, $numberOfUncachedDates, '?')) . ')';
		
			$injectedParams = array_unshift($uncachedDates, $userId);
			
			$wpdb->prepare($sql, $injectedParams);
			
			$queryResults = $wpdb->get_results($sql);
			
			// separate by date
			foreach ($queryResults as $record) {
				
				if (!array_key_exists($dateParsedResults)) {
					$dateParsedResults[$record->date] = array();
				}
				
				$dateParsedResults[$record->date][] = $record;
			}
			
			// set the cache for each date
			foreach ($dateParsedResults as $date => $records) {
				$cacheKey = $this->buildDateCacheKey($userId, $date);
				$cache->set($key, $records, Time::ONE_DAY);
			}
		}
		
		// merge (formerly) uncached data with cached data
		return array_merge($cachedData, $dateParsedResults);
	}

	
	/**
	 * save the cb raw-tracking-data record
	 * 
	 * effectively an upsert of a raw-tracking-data.
	 */
	public function save() {

		$wpdb = $this->getWpdb();
		
		// does the record already exist?
		$preparedStatement = $wpdb->prepare(
			'select count(user_id) as num from ' . $wpdb->prefix . $this->table_name . ' where user_id = %d and date = %s and source = %s',
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
				$wpdb->prefix . $this->table_name,
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
		
		// clear cache for the day
		$cacheKey = $this->buildDateCacheKey($this->user_id, $this->date);
		Cache::getInstance()->clear($cacheKey);
	}
	
	/**
	 * convert_fitbit_to_date_parsed_format
	 * 
	 * from:
	 * {
	 * 	"activity" : {
	 * 		"2016-01-01" => 34
	 *	},
	 *  "other_activity" : {
	 *  	"2016-01-01" => 6,
	 *  	"2016-01-02" => 7 
	 *  }
	 * }
	 * 
	 * to:
	 * {
	 *   "2016-01-01" : {
	 *      "activity" : 3,
	 *      "other_activity" : 6
	 *   },
	 *   "2016-01-02" : {
	 *   	"other_activity" : 7
	 *   }
	 * }
	 * 
	 */
	private function convert_fitbit_to_date_parsed_format($rawFitbit) {
		
		$dateFormat = array();
		
		foreach ($rawFitbit as $activity => $activityRecords) {
			
			foreach ($activityRecords as $date => $value) {
				
				if (!array_key_exists($date, $dateFormat)) {
					$dateFormat[$date] = array();
				}
				if (!array_key_exists($activity, $dateFormat[$date])) {
					$dateFormat[$date][$activity] = array();
				}
				
				$dateFormat[$date][$activity] = $value;
			}
		}
		
		return $dateFormat;
	}
	
	/**
	 * multi-save
	 */
	public function multiSave($userId, $source, $rawData = array()) 
	{
		// reformat data, separated by date
		$dateParsed = $this->convert_fitbit_to_date_parsed_format($rawData);
			
		// record raw tracking "fitbit" data into table
		$dates = array_keys($rawData);
		foreach ($dateParsed as $date => $data) {
			$rawTrackingData = $this->create($userId, $date, $source, $data);
		}
	}
	
	/**
	 * updateCache
	 */
	public function updateCache($newRawTrackingData = null)
	{
		$cache = Cache::getInstance();
		
		$newRawTrackingData = (is_null($newRawTrackingData)) ? $this : $newRawTrackingData;
		
		$cacheKey = $this->buildDateCacheKey($newRawTrackingData->user_id, $newRawTrackingData->date);

		// if the cache for this user/date exists, add the given record to it.
		$cachedRecords = $cache->get($cacheKey);
		if ($cachedResults !== false) {
			
			$newRecord = $newRawTrackingData->toCacheFormat();
			$replaced = false;
			foreach ($cachedRecords as &$cachedRecord) {
				if ($newRecord->source == $cachedRecord) {
					$cachedRecord = $newRecord;
					$replaced = true;
					break;
				}
			}
			
			if (!$replaced) {
				$cachedRecords[] = $newRecord;
			}
			
			$cache->set($cacheKey, $cachedRecords, Time::ONE_DAY);
		}
	}
}