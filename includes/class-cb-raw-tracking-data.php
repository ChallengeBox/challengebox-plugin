<?php
use \ChallengeBox\Includes\Utilities\BaseFactory;
use \ChallengeBox\Includes\Utilities\Cache;
use \ChallengeBox\Includes\Utilities\Time;

/**
 * CBRawTrackingData
 * 
 * @author sheeleyb
 */
class CBRawTrackingData extends BaseFactory
{
	const DATE_CACHE_KEY = 'date-cache_key-';
	
	const FITBIT_V1_SOURCE = 'fitbit-1';
	const FITBIT_V2_SOURCE = 'fitbit-2';	// example for versioning, not currently in use (I don't think)
	const GARMIN_V1_SOURCE = 'garmin-1';
	
	private $table_name = 'raw_tracking_data';
			
	private $user_id;
	private $date;
	private $source;
	
	private $data;
	private $data_decoded;
	
	private $create_date;
	private $last_modified;
	
	/**
	 * build date cache key
	 */
	private function buildDateCacheKey($userId, $date) {
		return self::DATE_CACHE_KEY . $userId . '-' . $date;
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
	private function convertFitbitToDateParsedFormat($rawFitbit) {
		
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
	 * instantiate
	 * 
	 * note: "public" for mocking purposes 
	 */
	public function instantiate($userId, $date, $source, $data, $createDate = null, $lastModified = null)
	{
		return new self($userId, $date, $source, $data, $createDate, $lastModified);
	}
	
	/**
	 * create
	 * 
	 * Builds a CBRawTrackingData object, populates it, saves it to the db, and will update 
	 * the related cache (if available)
	 */
	protected function create($userId, $date, $source, $data, $createDate = null, $lastModified = null) 
	{
		$object = $this->instantiate($userId, $date, $source, $data, $createDate, $lastModified);
		$object->save();
		
		$this->updateCache($object);
		
		return $object;
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
	public function __construct($userId = null, $date = null, $source = null, $data = null, $createDate = null, $lastModified = null) 
	{
		$this->user_id = $userId;
		$this->date = $date;
		$this->source = $source;
		
		if (is_array($data) || is_object($data)) {
			$this->data_decoded = $data;
			$data = json_encode($data);
		}
		
		$this->data = $data;
		
		$time = Time::getInstance();
		$this->create_date = (is_null($createDate)) ? $time->now() : $createDate;
		$this->last_modified = (is_null($lastModified)) ? $time->now() : $lastModified;
	}
	
	/**
	 * getUserId
	 */
	public function getUserId() {
		return $this->user_id;
	}
	
	/**
	 * getDate
	 */
	public function getDate() {
		return $this->date;
	}
	
	/**
	 * getSource
	 */
	public function getSource() {
		return $this->source;
	}
	
	/**
	 * getData
	 */
	public function getData() {
		return $this->data;
	}
	
	/**
	 * get data decoded
	 */
	public function getDataDecoded() {
		
		if (is_null($this->data_decoded)) {
			$this->data_decoded = json_decode($this->data, true);
		}
		
		return $this->data_decoded;
	}
	
	/**
	 * find by user id and dates
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

			$wpdb = $this->getWpdb();
			
			$sql = 'select * from ' . $wpdb->prefix . $this->table_name . ' where user_id = %d and date in ' .
					'(' . implode(',', array_fill(0, $numberOfUncachedDates, '%s')) . ')';
			
			$injectedParams = $uncachedDates;
			array_unshift($injectedParams, $userId);

			$preparedStatement = $wpdb->prepare($sql, $injectedParams);
			
			$queryResults = $wpdb->get_results($preparedStatement);
			
			// separate by date
			foreach ($queryResults as $record) {
				
				if (!array_key_exists($record->date, $dateParsedResults)) {
					$dateParsedResults[$record->date] = array();
				}
				
				$dateParsedResults[$record->date][] = $record;
			}
			
			// set the cache for each date
			foreach ($dateParsedResults as $date => $records) {
				$cacheKey = $this->buildDateCacheKey($userId, $date);
				$cache->set($cacheKey, $records, Time::ONE_DAY);
			}
		}
		
		// merge (formerly) uncached data with cached data
		$mergedResults = array_merge($cachedData, $dateParsedResults);
	
		$objectResults = array();
		foreach ($mergedResults as $date => $dateRecords) {
			foreach ($dateRecords as $record) {
				$objectResults[$date][] = $this->instantiate($record->user_id, $record->date, $record->source, $record->data, $record->create_date, $record->last_modified);
			}
		}
		
		return $objectResults;
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
	 * multi-save
	 */
	public function multiSave($userId, $source, $rawData = array()) 
	{
		// reformat data, separated by date
		$dateParsed = $this->convertFitbitToDateParsedFormat($rawData);
			
		// record raw tracking "fitbit" data into table
		$dates = array_keys($rawData);
		foreach ($dateParsed as $date => $data) {
			$this->create($userId, $date, $source, $data);
		}
	}
	
	/**
	 * to cache format
	 */
	public function toCacheFormat()
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
	 * updateCache
	 */
	public function updateCache($newRawTrackingData = null)
	{
		$cache = Cache::getInstance();
		
		$newRawTrackingData = (is_null($newRawTrackingData)) ? $this : $newRawTrackingData;
		
		$cacheKey = $this->buildDateCacheKey($newRawTrackingData->getUserId(), $newRawTrackingData->getDate());

		// if the cache for this user/date exists, add the given record to it.
		$cachedRecords = $cache->get($cacheKey);
		
		if ($cachedRecords !== false) {
			
			$newRecord = $newRawTrackingData->toCacheFormat();
			$replaced = false;
			foreach ($cachedRecords as &$cachedRecord) {
				
				if ($newRecord->source == $cachedRecord->source) {
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