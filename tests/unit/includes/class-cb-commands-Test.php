<?php
namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

use ChallengeBox\Includes\Utilities\BaseFactory;

use \CBRawTrackingData;

class Test_CBCmd extends TestCase {

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
		$debug = 1;
		$assocArgs = array(
			'debug' => $debug	
		);
		
		// mock
		$userId = 343;
		
		$user = new \stdClass();
		$user->ID = $userId;
		
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
		
		$rawData = $dateParsedData;
		
		$cbRawTrackingData = $this->getMockBuilder('CBRawTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('multiSave'))
			->getMock();
		$cbRawTrackingData->expects($this->once())
			->method('multiSave')
			->with( 
				$this->equalTo($user->ID),
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
		
		$users = array(
			$user
		);
		
		echo 'is loaded: ' . class_exists('CBCmd') . PHP_EOL;
		
		$classCbCommands = $this->getMockBuilder('\CBCmd')
			->disableOriginalConstructor()
			->setMethods(array('get_carbon', 'get_wp_users',  'get_customers_fitbit'))
			->getMock();
		$classCbCommands->expects($this->at(0))
			->method('get_carbon')
			->willReturn($carbon);
		$classCbCommands->expects($this->at(1)) 
			->method('get_wp_users')
			->with($this->equalTo(array('fields' => array('ID'))))
			->willReturn($users);
		$classCbCommands->expects($this->at(2))
			->method('get_customers_fitbit')
			->with($this->equalTo($userId))
			->willReturn($fitbit);
		
		print_r(get_class_methods($classCbCommands));
			
		// run
		$classCbCommands->ingest_daily_tracking($args, $assocArgs);
	}
}