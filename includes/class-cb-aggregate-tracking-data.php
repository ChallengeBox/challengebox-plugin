<?php
use ChallengeBox\Includes\Utilities\BaseFactory;
use ChallengeBox\Includes\Utilities\Cache;
use ChallengeBox\Includes\Utilities\Time;

/**
 * CBAggregateTrackingData
 * 
 * @author sheeleyb
 */
class CBAggregateTrackingData extends BaseFactory
{
	private $table_name = 'aggregate_tracking_data';
	
	private $user_id;
	private $date;
	private $any_activity;
	private $medium_activity;
	private $heavy_activity;
	private $water;
	private $food;
	private $distance;
	private $steps;
	private $very_active;
	private $fairly_active;
	private $lightly_active;
	private $create_date;
	private $last_modified;
	
	/**
	 * construct
	 */
	public function __construct($dataset = array())
	{
		foreach ($dataset as $key => $value) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}
	}
	
	/**
	 * instantiate
	 * 
	 * note: "public" for mocking purposes 
	 */
	public function instantiate($dataset = array())
	{
		return new self($dataset);
	}
	
	/**
	 * aggregate
	 */
	public function aggregate($rawTrackingDataSet = array()) 
	{
		// map out raw data from each tracker to aggregate table columns
		$rawToColumnMapper = array(
				
			// raw to aggregate mapping for fitbit v1 api
			CBRawTrackingData::FITBIT_V1_SOURCE => array(
				'water' => 'water',
				'caloriesIn' => 'food',
				'distance' => 'distance',
				'steps' => 'steps',
				'minutesVeryActive' => 'very_active',
				'minutesFairlyActive' => 'fairly_active',
				'minutesLightlyActive' => 'lightly_active'
			),

			// raw to aggregate mapping for garmin v1 api
			//
			// note: this is placeholder mapping until garmin
			// can get set up.
			CBRawTrackingData::GARMIN_V1_SOURCE => array(
				'water' => 'water',
				'caloriesIn' => 'food',
				'distance' => 'distance',
				'steps' => 'steps',
				'minutesVeryActive' => 'very_active',
				'minutesFairlyActive' => 'fairly_active',
				'minutesLightlyActive' => 'lightly_active'
			)
		);
		
		// for each day
		$results = array();
		foreach ($rawTrackingDataSet as $date => $rawDateData) {
		
			// for each raw data record
			foreach ($rawDateData as $rawTrackingData) {
				
				$userId = $rawTrackingData->getUserId();
				$source = $rawTrackingData->getSource();
				
				if (!array_key_exists($userId, $results)) {
					$results[$userId] = array();
				}
				if (!array_key_exists($date, $results[$userId])) {
					$results[$userId][$date] = array();
				}
					
				$dataDecoded = $rawTrackingData->getDataDecoded();
					
				// group data from different sources (fitbit, garmin, etc) together
				if (isset($rawToColumnMapper[$source])) {
					foreach ($rawToColumnMapper[$source] as $rawDataKey => $column) {

						if (!isset($results[$userId][$date][$column])) {
							$results[$userId][$date][$column] = 0;
						}
						
						$results[$userId][$date][$column] += (isset($dataDecoded[$rawDataKey]))
							? $dataDecoded[$rawDataKey] : 0;
					}
				}
			}
		}
		
		foreach ($results as $userId => $userGroup) {
			foreach ($userGroup as $date => $dateGroup) {
		
				$results[$userId][$date]['user_id'] = $userId;
				$results[$userId][$date]['date'] = $date;
				
				$veryActive = (isset($results[$userId][$date]['very_active']))
					? $results[$userId][$date]['very_active']
					: 0;
				$fairlyActive = (isset($results[$userId][$date]['fairly_active']))
					? $results[$userId][$date]['fairly_active']
					: 0;
				$lightlyActive = (isset($results[$userId][$date]['lightly_active']))
					? $results[$userId][$date]['lightly_active']
					: 0;
		
				$results[$userId][$date]['any_activity'] = 0 +
					$veryActive + $fairlyActive + $lightlyActive;
				$results[$userId][$date]['medium_activity'] = 0 +
					$veryActive + $fairlyActive;
				$results[$userId][$date]['heavy_activity'] = 0 +
					$veryActive;
				
				$results[$userId][$date] = $this->instantiate($results[$userId][$date]);
			}
		}
		
		return $results;
	}
	
	/**
	 * aggregate and Save
	 */
	public function aggregateAndSave($rawTrackingDataSet = array())
	{
		$aggregates = $this->aggregate($rawTrackingDataSet);
	
		$this->multiSave($aggregates);
	}
	
	/**
	 * multiSave
	 */
	public function multiSave($aggregates = array()) 
	{
		foreach ($aggregates as $userId => $userAggregates) {
			foreach ($userAggregates as $date => $aggregate) {
				$aggregate->save();
			}
		}
	}
	
	/**
	 * save
	 */
	public function save()
	{
		$wpdb = $this->getWpdb();

		// does the record already exist?
		$preparedStatement = $wpdb->prepare(
			'select count(user_id) as num from ' . $wpdb->prefix . $this->table_name . ' where user_id = %d and date = %s',
			array($this->user_id, $this->date)
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
					'any_activity' => $this->any_activity,
					'medium_activity' => $this->medium_activity,
					'heavy_activity' => $this->heavy_activity,
					'water' => $this->water,
					'food' => $this->food,
					'distance' => $this->distance,
					'steps' => $this->steps,
					'very_active' => $this->very_active,
					'fairly_active' => $this->fairly_active,
					'lightly_active' => $this->lightly_active
				)
			);
		
		// yes? update the record
		} else {
			$wpdb->update(
				$wpdb->prefix . $this->table_name,
				array(
					'any_activity' => $this->any_activity,
					'medium_activity' => $this->medium_activity,
					'heavy_activity' => $this->heavy_activity,
					'water' => $this->water,
					'food' => $this->food,
					'distance' => $this->distance,
					'steps' => $this->steps,
					'very_active' => $this->very_active,
					'fairly_active' => $this->fairly_active,
					'lightly_active' => $this->lightly_active
				),
				array(
					'user_id' => $this->user_id,
					'date' => $this->date
				),
				array("%f","%f","%f","%f","%f","%f","%f","%f","%f"),
				array("%d", "%s")
			);
		}
	}
}