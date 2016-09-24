<?php
namespace Tests\Unit\Includes;

use \BaseTest;
use Carbon\Carbon;

use ChallengeBox\Includes\Utilities\BaseFactory;

use \CBRawTrackingData;
use \CBAggregateTrackingData;

class Test_AggregatedTrackingData extends \BaseTest 
{
	/**
	 * test: aggregate
	 */
	public function testAggregate() 
	{	
		// param
		$userId = 123;
		$date = '2016-01-01';
		$secondDate = '2016-01-02';
		
		$rawTrackingDataSet = array(
			$date => array(
				new CBRawTrackingData($userId, $date, 'fitbit-1', '{"water":30,"caloriesIn":40,"distance":3,"steps":100,"minutesVeryActive":50,"minutesFairlyActive":30,"minutesLightlyActive":100}'),
				new CBRawTrackingData($userId, $date, 'garmin-1', '{"water":50,"caloriesIn":20,"steps":200,"minutesVeryActive":150,"minutesFairlyActive":130,"minutesLightlyActive":200}'),
			),
			$secondDate => array(
				new CBRawTrackingData($userId, $secondDate, 'fitbit-1', '{"water":50,"caloriesIn":20,"steps":200,"minutesVeryActive":150,"minutesFairlyActive":130,"minutesLightlyActive":200}'),
			)
		);
		
		$aggregateTrackingData = new CBAggregateTrackingData();
		
		// run
		$results = $aggregateTrackingData->aggregate($rawTrackingDataSet);
		
		// post-run assertion
		$expectedResults = array(
			$userId => array(
				$date => new CBAggregateTrackingData(array(
					'user_id' => $userId,
					'date' => $date,
					'water' => 80,
					'food' => 60,
					'distance' => 3,
					'steps' => 300,
					'very_active' => 200,
					'fairly_active' => 160,
					'lightly_active' => 300,
					'any_activity' => 660,
					'medium_activity' => 360,
					'heavy_activity' => 200
				)),
				$secondDate => new CBAggregateTrackingData(array(
					'user_id' => $userId,
					'date' => $secondDate,
					'water' => 50,
					'food' => 20,
					'distance' => null,
					'steps' => 200,
					'very_active' => 150,
					'fairly_active' => 130,
					'lightly_active' => 200,
					'any_activity' => 480,
					'medium_activity' => 280,
					'heavy_activity' => 150
				))
			)
		);
		
		$this->assertEquals($expectedResults, $results);
	}
	
	/**
	 * test: aggregate and save
	 */
	public function testAggregateAndSave()
	{
		// param
		$rawTrackingDataSet = 'raw_data';
		
		// mock
		$aggregateData = 'aggregate_data';
		
		$aggregateTrackingData = $this->getMockBuilder('CBAggregateTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('aggregate', 'multiSave'))
			->getMock();
		$aggregateTrackingData->expects($this->once())
			->method('aggregate')
			->with($this->equalTo($rawTrackingDataSet))
			->willReturn($aggregateData);
		$aggregateTrackingData->expects($this->once())
			->method('multiSave')
			->with($this->equalTo($aggregateData));
		
		// run
		$aggregateTrackingData->aggregateAndSave($rawTrackingDataSet);
	}
	
	/**
	 * test: multiSave
	 */
	public function testMultiSave()
	{
		// params and mocks
		$aggregateInstance = $this->getMockBuilder('CBAggregateTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('save'))
			->getMock();
		
		$aggregate = array(
			clone $aggregateInstance,
			clone $aggregateInstance,
			clone $aggregateInstance
		);
		
		foreach ($aggregate as &$inst) {
			$inst->expects($this->once())
				->method('save');
		}
		
		$aggregates = array(
			123 => array(
				'2016-01-01' => $aggregate[0],
				'2016-01-02' => $aggregate[1]
			),
			234 => array(
				'2016-01-01' => $aggregate[2]
			)
		);
		
		$cbAggregateTrackingData = new \CBAggregateTrackingData();
		
		// run
		$cbAggregateTrackingData->multiSave($aggregates);
	}

	/**
	 * test: save - inserts if no record previously exists
	 */
	public function testSaveInsertsIfNoRecordPreviouslyExists()
	{
		// mock
		$userId = 123;
		$date = '2014-05-03 00:00:00';
		$water = 10;
		$food = 11;
		$distance = 12;
		$steps = 13;
		$veryActive = 50;
		$fairlyActive = 40;
		$lightlyActive = 20;
		$anyActivity = 110;
		$mediumActivity = 90;
		$heavyActivity = 50;
		
		$preparedStatement = 'a_prepared_statement_object';
		$wordpressPrefix = 'wp_';
		
		$result = new \stdClass();
		$result->num = 0;
		$results = array($result);
		$wpdb = $this->getMockBuilder('\stdClass')
			->disableOriginalConstructor()
			->setMethods(array('prepare', 'get_results', 'insert', 'update'))
			->getMock();
		$wpdb->expects($this->once())
			->method('prepare')
			->with(
				$this->equalTo('select count(user_id) as num from wp_aggregate_tracking_data where user_id = %d and date = %s'),
				$this->equalTo(array($userId, $date))
			)
			->willReturn($preparedStatement);
		$wpdb->expects($this->once())
			->method('get_results')
			->with($this->equalTo($preparedStatement))
			->willReturn($results);
		$wpdb->expects($this->once())
			->method('insert')
			->with(
				$this->equalTo('wp_aggregate_tracking_data'),
				$this->equalTo(array(
					'user_id' => $userId,
					'date' => $date,
					'water' => $water,
					'food' => $food,
					'distance' => $distance,
					'steps' => $steps,
					'very_active' => $veryActive,
					'fairly_active' => $fairlyActive,
					'lightly_active' => $lightlyActive,
					'any_activity' => $anyActivity,
					'medium_activity' => $mediumActivity,
					'heavy_activity' => $heavyActivity
				))	
			);
		$wpdb->expects($this->never())
			->method('update');
		$wpdb->prefix = $wordpressPrefix;
		
		$aggregateTrackingData = $this->getMockBuilder('CBAggregateTrackingData')
			->setConstructorArgs(array(array(
				'user_id' => $userId,
				'date' => $date,
				'any_activity' => $anyActivity,
				'medium_activity' => $mediumActivity,
				'heavy_activity' => $heavyActivity,
				'water' => $water,
				'food' => $food,
				'distance' => $distance,
				'steps' => $steps,
				'very_active' => $veryActive,
				'fairly_active' => $fairlyActive,
				'lightly_active' => $lightlyActive
			)))
			->setMethods(array('getWpdb'))
			->getMock();
		$aggregateTrackingData->expects($this->any())
			->method('getWpdb')
			->willReturn($wpdb);
		
		
		// run
		$aggregateTrackingData->save();
	}
	
	/**
	 * test: save - updates if a record previously exists
	 */
	public function testSaveUpdatesIfARecordPreviouslyExists()
	{
		// mock
		$userId = 123;
		$date = '2014-05-03 00:00:00';
		$water = 10;
		$food = 11;
		$distance = 12;
		$steps = 13;
		$veryActive = 50;
		$fairlyActive = 40;
		$lightlyActive = 20;
		$anyActivity = 110;
		$mediumActivity = 90;
		$heavyActivity = 50;
		
		
		$preparedStatement = 'a_prepared_statement_object';
		$wordpressPrefix = 'wp_';
		
		$result = new \stdClass();
		$result->num = 1;
		$results = array($result);
		$wpdb = $this->getMockBuilder('\stdClass')
			->disableOriginalConstructor()
			->setMethods(array('prepare', 'get_results', 'insert', 'update'))
			->getMock();
		$wpdb->expects($this->once())
			->method('prepare')
			->with(
				$this->equalTo('select count(user_id) as num from wp_aggregate_tracking_data where user_id = %d and date = %s'),
				$this->equalTo(array($userId, $date))
			)
			->willReturn($preparedStatement);
		$wpdb->expects($this->once())
			->method('get_results')
			->with($this->equalTo($preparedStatement))
			->willReturn($results);
		$wpdb->expects($this->never())
			->method('insert');
		$wpdb->expects($this->once())
			->method('update')
			->with(
				$this->equalTo('wp_aggregate_tracking_data'),
				$this->equalTo(array(
					'any_activity' => $anyActivity,
					'medium_activity' => $mediumActivity,
					'heavy_activity' => $heavyActivity,
					'water' => $water,
					'food' => $food,
					'distance' => $distance,
					'steps' => $steps,
					'very_active' => $veryActive,
					'fairly_active' => $fairlyActive,
					'lightly_active' => $lightlyActive
				)),
				$this->equalTo(array(
					'user_id' => $userId,
					'date' => $date
				)),
				$this->equalTo(array("%f","%f","%f","%f","%f","%f","%f","%f","%f")),
				$this->equalTo(array("%d","%s"))
			);
		$wpdb->prefix = $wordpressPrefix;
		
		
		$rawTrackingData = $this->getMockBuilder('CBAggregateTrackingData')
			->setConstructorArgs(array(array(
				'user_id' => $userId,
				'date' => $date,
				'any_activity' => $anyActivity,
				'medium_activity' => $mediumActivity,
				'heavy_activity' => $heavyActivity,
				'water' => $water,
				'food' => $food,
				'distance' => $distance,
				'steps' => $steps,
				'very_active' => $veryActive,
				'fairly_active' => $fairlyActive,
				'lightly_active' => $lightlyActive
			)))
			->setMethods(array('getWpdb'))
			->getMock();
		$rawTrackingData->expects($this->any())
			->method('getWpdb')
			->willReturn($wpdb);

		// run
		$rawTrackingData->save();
	}
}