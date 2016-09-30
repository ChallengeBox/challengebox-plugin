<?php
namespace Tests\Unit\Includes;

use \BaseTest;
use Carbon\Carbon;

use ChallengeBox\Includes\Utilities\BaseFactory;

use \CBRawTrackingData;

class Test_CBCmd extends \BaseTest {

	/**
	 * test: ingest_daily_tracking successfully
	 */
	public function testIngestDailyTrackingSuccessfully()
	{
		// param
		$startDate = '2016-01-01';
		$endDate = '2016-01-05';
		$args = array(
			$startDate, $endDate	
		);
		$debug = 0;
		$assocArgs = array(
			'debug' => $debug	
		);
		
		// mock
		$userId = 343;

		$userIds = array($userId);
		
		$homePath = '\some\path';
		
		$execStatement = 'wp cb ingest_daily_tracking_for_user_block 2016-01-01 2016-01-05 343 --allow-root  --path="\some\path" ';
		
		$classCbCommands = $this->getMockBuilder('\CBCmd')
			->disableOriginalConstructor()
			->setMethods(array('get_carbon', 'getUserIds', 'getHomePath',  'exec'))
			->getMock();
		$classCbCommands->expects($this->at(0))
			->method('getHomePath')
			->willReturn($homePath);
		$classCbCommands->expects($this->at(1)) 
			->method('getUserIds')
			->willReturn($userIds);
		$classCbCommands->expects($this->at(2))
			->method('exec')
			->with($this->equalTo($execStatement));
		
		// run
		$classCbCommands->ingest_daily_tracking($args, $assocArgs);
	}

	/**
	 * test: ingest_daily_tracking_for_user_block
	 */
	public function testIngestDailyTracking_for_user_block()
	{
		// param
		$startDate = '2016-01-01';
		$endDate = '2016-01-05';
		$userId = 343;
		$args = array(
			$startDate, $endDate, $userId	
		);
		$debug = 0;
		$assocArgs = array(
			'debug' => $debug	
		);
		
		// mock
		$userIds = array($userId);
		
		$startDateInCarbon = '2016-01-01';
		$endDateInCarbon = '2016-01-01';
	
		$carbon = $this->getMockBuilder('Carbon')
			->disableOriginalConstructor()
			->setMethods(array('createFromFormat'))
			->getMock();
		$carbon->expects($this->at(0))
			->method('createFromFormat')
			->with($this->equalTo('Y-m-d'), $this->equalTo($startDate))
			->willReturn($startDateInCarbon);
		$carbon->expects($this->at(1))
			->method('createFromFormat')
			->with($this->equalTo('Y-m-d'), $this->equalTo($endDate))
			->willReturn($endDateInCarbon);
	
		$dateParsedData = array(
			'2016-01-01' => array(
				'caloriesIn' => 0, 
				'water' => 1, 
				'caloriesOut' => 2, 
				'steps' => 3, 
				'distance' => 4, 
				'floors' => 5, 
				'elevation' => 6,
				'minutesSedentary' => 7, 
				'minutesLightlyActive' => 8, 
				'minutesFairlyActive' => 9, 
				'minutesVeryActive' => 10,
				'activityCalories' => 11, 
				'tracker_caloriesOut' => 12, 
				'tracker_steps' => 13,
				'tracker_distance' => 14, 
				'tracker_floors' => 15,
				'tracker_elevation' => 16, 
				'startTime' => 17, 
				'timeInBed' => 18,
				'minutesAsleep' => 19, 
				'awakeningsCount' => 20, 
				'minutesAwake' => 21,
				'minutesToFallAsleep' => 22,
				'minutesAfterWakeup' => 23, 
				'efficiency' => 24,
				'weight' => 25, 
				'bmi' => 26, 
				'fat' => 27,
				'activities_steps' => 28
			)
		);
		
		$rawData = array(
			'caloriesIn' => array('2016-01-01' => 0), 
			'water' => array('2016-01-01' => 1), 
			'caloriesOut' => array('2016-01-01' => 2),
			'steps' =>  array('2016-01-01' => 3),
			'distance' =>  array('2016-01-01' => 4),
			'floors' =>  array('2016-01-01' => 5),
			'elevation' =>  array('2016-01-01' => 6),
			'minutesSedentary' =>  array('2016-01-01' => 7),
			'minutesLightlyActive' =>  array('2016-01-01' => 8),
			'minutesFairlyActive' => array('2016-01-01' => 9),
			'minutesVeryActive' => array('2016-01-01' => 10),
			'activityCalories' => array('2016-01-01' => 11),
			'tracker_caloriesOut' => array('2016-01-01' => 12),
			'tracker_steps' => array('2016-01-01' => 13),
			'tracker_distance' => array('2016-01-01' => 14),
			'tracker_floors' => array('2016-01-01' => 15),
			'tracker_elevation' => array('2016-01-01' => 16),
			'startTime' => array('2016-01-01' => 17),
			'timeInBed' => array('2016-01-01' => 18),
			'minutesAsleep' => array('2016-01-01' => 19),
			'awakeningsCount' => array('2016-01-01' => 20),
			'minutesAwake' => array('2016-01-01' => 21),
			'minutesToFallAsleep' => array('2016-01-01' => 22),
			'minutesAfterWakeup' => array('2016-01-01' => 23),
			'efficiency' => array('2016-01-01' => 24),
			'weight' => array('2016-01-01' => 25),
			'bmi' => array('2016-01-01' => 26),
			'fat' => array('2016-01-01' => 27),
			'activities_steps' => array('2016-01-01' => 28),
		);
		
		$cbRawTrackingData = $this->getMockBuilder('CBRawTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('multiSave'))
			->getMock();
		$cbRawTrackingData->expects($this->once())
			->method('multiSave')
			->with( 
				$this->equalTo($userId),
				$this->equalTo(CBRawTrackingData::FITBIT_V1_SOURCE),
				$this->equalTo($rawData)
			);
	
		$baseFactory = $this->getMockBuilder('ChallengeBox\Includes\Utilities\BaseFactory')
			->disableOriginalConstructor()
			->setMethods(array('generate'))
			->getMock();
		$baseFactory->expects($this->once())
			->method('generate')
			->with($this->equalTo('CBRawTrackingData'))
			->willReturn($cbRawTrackingData);
		BaseFactory::setInstance($baseFactory);
		
		$fitbit = $this->getMockBuilder('\stdClass')
			->disableOriginalConstructor()
			->setMethods(array('get_cached_time_series'))
			->getMock();
		$i=0;
		$activities = array(
			'caloriesIn', 'water', 'caloriesOut', 'steps', 'distance', 'floors', 'elevation',
			'minutesSedentary', 'minutesLightlyActive', 'minutesFairlyActive', 'minutesVeryActive',
			'activityCalories', 'tracker_caloriesOut', 'tracker_steps', 'tracker_distance', 'tracker_floors',
			'tracker_elevation', 'startTime', 'timeInBed', 'minutesAsleep', 'awakeningsCount', 'minutesAwake',
			'minutesToFallAsleep', 'minutesAfterWakeup', 'efficiency', 'weight', 'bmi', 'fat', 'activities_steps'
		);
		
		foreach ($activities as $activity) {
			$fitbit->expects($this->at($i))
				->method('get_cached_time_series')
				->with(
					$this->equalTo($activity),
					$this->equalTo($startDateInCarbon),
					$this->equalTo($endDateInCarbon)
				)
				->willReturn(array('2016-01-01' => $i));
			$i++;
		}
		
		$classCbCommands = $this->getMockBuilder('\CBCmd')
			->disableOriginalConstructor()
			->setMethods(array('get_carbon', 'get_customers_fitbit'))
			->getMock();
		$classCbCommands->expects($this->once())
			->method('get_carbon')
			->willReturn($carbon);
		$classCbCommands->expects($this->any())
			->method('get_customers_fitbit')
			->with($this->equalTo($userId))
			->willReturn($fitbit);
		
		// run
		$classCbCommands->ingest_daily_tracking_for_user_block($args, $assocArgs);
	}
	
	/**
	 * test: aggregate_raw_data
	 */
	public function testAggregateRawData()
	{
		// param
		$startDate = '2016-01-01';
		$endDate = '2016-01-04';
		$assocArgs = array();
		
		$args = array(
			$startDate,
			$endDate
		);
		
		// mock
		$userId = 343;
		
		$user = new \stdClass();
		$user->ID = $userId;
		
		$userIds = array(
			$userId
		);
		
		$rawData = 'some_raw_data';
		
		$cbRawTrackingData = $this->getMockBuilder('CBRawTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('findByUserIdAndDates'))
			->getMock();
		$cbRawTrackingData->expects($this->once())
			->method('findByUserIdAndDates')
			->with( 
				$this->equalTo($userId),
				$this->equalTo($startDate),
				$this->equalTo($endDate)
			)
			->willReturn($rawData);
		

		$cbAggregateTrackingData = $this->getMockBuilder('CBAggregateTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('aggregateAndSave'))
			->getMock();
		$cbAggregateTrackingData->expects($this->once())
			->method('aggregateAndSave')
			->with($this->equalTo($rawData));
		
		$baseFactory = $this->getMockBuilder('ChallengeBox\Includes\Utilities\BaseFactory')
			->disableOriginalConstructor()
			->setMethods(array('generate'))
			->getMock();
		$baseFactory->expects($this->at(0))
			->method('generate')
			->with($this->equalTo('CBRawTrackingData'))
			->willReturn($cbRawTrackingData);
		$baseFactory->expects($this->at(1))
			->method('generate')
			->with($this->equalTo('CBAggregateTrackingData'))
			->willReturn($cbAggregateTrackingData);
		BaseFactory::setInstance($baseFactory);
		
		$classCbCommands = $this->getMockBuilder('\CBCmd')
			->disableOriginalConstructor()
			->setMethods(array('getUserIds',  'get_customers_fitbit'))
			->getMock();
		$classCbCommands->expects($this->once()) 
			->method('getUserIds')
			->willReturn($userIds);
		
		// run
		$classCbCommands->aggregate_raw_data($args, $assocArgs);
		
	}
}