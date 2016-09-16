<?php
include_once(CHALLENGEBOX_PLUGIN_DIR . '/includes/class-cb-commands.php');

use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class WP_CLI_Command {};
class WP_CLI {
	public static function add_command() {}
}

class Test_CBCmd extends TestCase {

	function setUp() {
		parent::setUp();
	}

	function tearDown() {
		parent::tearDown();
	}
	
	/**
	 * test: injest_daily_tracking successfully
	 */
	public function testInjestDailyTrackingSuccessfully()
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
				->willReturn(array($activity => array('2016-01-01' => $i)));
			$i++;
		}

		$userId = 343;
		
		$user = new \stdClass();
		$user->ID = $userId;
		
		$users = array(
			$user
		);
		
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
		
		$aRawTrackingData = $this->getMockBuilder('CBRawTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('save'))
			->getMock();
		$aRawTrackingData->expects($this->any())
			->method('save');
		
		$classCbCommands = $this->getMockBuilder('CBCmd')
			->disableOriginalConstructor()
			->setMethods(array('get_carbon', 'get_wp_users',  'get_customers_fitbit',  'generate_cb_raw_tracking_data'))
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
		$classCbCommands->expects($this->at(3))
			->method('generate_cb_raw_tracking_data')
			->with(
				$this->equalTo($userId),
				$this->equalTo('2016-01-01'),
				$this->equalTo('fitbit-1'),
				$this->equalTo($dateParsedData['2016-01-01'])
			)
			->willReturn($aRawTrackingData);
		
		// run
		$classCbCommands->injest_daily_tracking($args, $assocArgs);
	}
}