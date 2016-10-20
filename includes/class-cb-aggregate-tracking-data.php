<?php
use ChallengeBox\Includes\Utilities\BaseFactory;
use ChallengeBox\Includes\Utilities\Cache;
use ChallengeBox\Includes\Utilities\Time;
use Carbon\Carbon;

/**
 * CBAggregateTrackingData
 * 
 * @author sheeleyb
 */
class CBAggregateTrackingData extends BaseFactory
{
	private $table_name = 'cb_fitness_data';
	
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
	
	private $light_30;
	private $light_60;
	private $light_90;
	private $moderate_10;
	private $moderate_30;
	private $moderate_45;
	private $moderate_60;
	private $moderate_90;
	private $heavy_10;
	private $heavy_30;
	private $heavy_45;
	private $heavy_60;
	private $heavy_90;
	private $water_days;
	private $food_days;
	private $food_or_water_days;
	private $distance_1;
	private $distance_2;
	private $distance_3;
	private $distance_4;
	private $distance_5;
	private $distance_6;
	private $distance_8;
	private $distance_10;
	private $distance_15;
	private $steps_8k;
	private $steps_10k;
	private $steps_12k;
	private $steps_15k;
	private $wearing_fitbit;
	
	private $create_date;
	private $last_modified;
	
	/**
	 * construct
	 */
	public function __construct($dataset = array(), $createDate = null, $lastModified = null)
	{
		foreach ($dataset as $key => $value) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}

		$this->create_date = (is_null($createDate)) ? Carbon::now() : $createDate;
		$this->last_modified = (is_null($lastModified)) ? Carbon::now() : $lastModified;
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
				
				// initialize
				if (!array_key_exists($userId, $results)) {
					$results[$userId] = array();
				}
				if (!array_key_exists($date, $results[$userId])) {
					$results[$userId][$date] = array();
					$results[$userId][$date]['wearing_fitbit'] = false;
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
					
					// fitbit-specific
					if (in_array($source, array(CBRawTrackingData::FITBIT_V1_SOURCE, CBRawTrackingData::FITBIT_V2_SOURCE))) {
						$results[$userId][$date]['wearing_fitbit'] = (
							$results[$userId][$date]['wearing_fitbit'] ||
							(isset($dataDecoded['minutesVeryActive']) && $dataDecoded['minutesVeryActive'] > 0) ||
							(isset($dataDecoded['minutesFairlyActive']) && $dataDecoded['minutesFairlyActive'] > 0) ||
							(isset($dataDecoded['minutesLightlyActive']) && $dataDecoded['minutesLightlyActive'] > 0)
						);
					}
				}
			}
		}
		
		// calculate non-source-specific yet user/date-dependent analytics
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
				
				$lightBenchmarks = array(30, 60, 90);
				foreach ($lightBenchmarks as $benchmark) {
					$results[$userId][$date]['light_' . $benchmark] = ($results[$userId][$date]['any_activity'] >= $benchmark);
				}
				
				$moderateBenchmarks = array(10, 30, 45, 60, 90);
				foreach ($moderateBenchmarks as $benchmark) {
					$results[$userId][$date]['moderate_' . $benchmark] = ($results[$userId][$date]['medium_activity'] >= $benchmark);
				}
				
				$heavyBenchmarks = array(10, 30, 45, 60, 90);
				foreach ($heavyBenchmarks as $benchmark) {
					$results[$userId][$date]['heavy_' . $benchmark] = ($results[$userId][$date]['heavy_activity'] >= $benchmark);
				}

				$results[$userId][$date]['water_days'] = ($results[$userId][$date]['water'] > 0);
				$results[$userId][$date]['food_days'] = ($results[$userId][$date]['food'] > 0);
				$results[$userId][$date]['food_or_water_days'] = ($results[$userId][$date]['water_days'] || $results[$userId][$date]['food_days']);

				$distanceBenchmarks = array(1, 2, 3, 4, 5, 6, 8, 10, 15);
				foreach ($distanceBenchmarks as $benchmark) {
					$results[$userId][$date]['distance_' . $benchmark] = ($results[$userId][$date]['distance'] >= $benchmark);
				}
				
				$stepsBenchmarks = array(
					'8k' => 8000,
					'10k' => 10000,
					'12k' => 12000,
					'15k' => 15000
				);
				foreach ($stepsBenchmarks as $benchmarkLabel => $benchmark) {
					$results[$userId][$date]['steps_' . $benchmarkLabel] = ($results[$userId][$date]['steps'] >= $benchmark);
				}

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
			'select count(user_id) as num from ' . $this->table_name . ' where user_id = %d and date = %s',
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
				$this->table_name,
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
					'lightly_active' => $this->lightly_active,

					'light_30' => $this->light_30,
					'light_60' => $this->light_60,
					'light_90' => $this->light_90,

					'moderate_10' => $this->moderate_10,
					'moderate_30' => $this->moderate_30,
					'moderate_45' => $this->moderate_45,
					'moderate_60' => $this->moderate_60,
					'moderate_90' => $this->moderate_90,

					'heavy_10' => $this->heavy_10,
					'heavy_30' => $this->heavy_30,
					'heavy_45' => $this->heavy_45,
					'heavy_60' => $this->heavy_60,
					'heavy_90' => $this->heavy_90,

					'water_days' => $this->water_days,
					'food_days' => $this->food_days,
					'food_or_water_days' => $this->food_or_water_days,

					'distance_1' => $this->distance_1,
					'distance_2' => $this->distance_2,
					'distance_3' => $this->distance_3,
					'distance_4' => $this->distance_4,
					'distance_5' => $this->distance_5,
					'distance_6' => $this->distance_6,
					'distance_8' => $this->distance_8,
					'distance_10' => $this->distance_10,
					'distance_15' => $this->distance_15,
						
					'steps_8k' => $this->steps_8k,
					'steps_10k' => $this->steps_10k,
					'steps_12k' => $this->steps_12k,
					'steps_15k' => $this->steps_15k,
						
					'wearing_fitbit' => $this->wearing_fitbit,

					'last_modified' => $this->last_modified
				)
			);
		
		// yes? update the record
		} else {
			$wpdb->update(
				$this->table_name,
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
					'lightly_active' => $this->lightly_active,

					'light_30' => $this->light_30,
					'light_60' => $this->light_60,
					'light_90' => $this->light_90,

					'moderate_10' => $this->moderate_10,
					'moderate_30' => $this->moderate_30,
					'moderate_45' => $this->moderate_45,
					'moderate_60' => $this->moderate_60,
					'moderate_90' => $this->moderate_90,

					'heavy_10' => $this->heavy_10,
					'heavy_30' => $this->heavy_30,
					'heavy_45' => $this->heavy_45,
					'heavy_60' => $this->heavy_60,
					'heavy_90' => $this->heavy_90,

					'water_days' => $this->water_days,
					'food_days' => $this->food_days,
					'food_or_water_days' => $this->food_or_water_days,

					'distance_1' => $this->distance_1,
					'distance_2' => $this->distance_2,
					'distance_3' => $this->distance_3,
					'distance_4' => $this->distance_4,
					'distance_5' => $this->distance_5,
					'distance_6' => $this->distance_6,
					'distance_8' => $this->distance_8,
					'distance_10' => $this->distance_10,
					'distance_15' => $this->distance_15,
						
					'steps_8k' => $this->steps_8k,
					'steps_10k' => $this->steps_10k,
					'steps_12k' => $this->steps_12k,
					'steps_15k' => $this->steps_15k,
						
					'wearing_fitbit' => $this->wearing_fitbit,

					'last_modified' => $this->last_modified
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
