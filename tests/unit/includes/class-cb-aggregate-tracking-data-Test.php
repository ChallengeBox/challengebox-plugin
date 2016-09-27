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
				new CBRawTrackingData($userId, $date, CBRawTrackingData::FITBIT_V1_SOURCE, '{"water":30,"caloriesIn":40,"distance":3,"steps":100,"minutesVeryActive":50,"minutesFairlyActive":30,"minutesLightlyActive":100}'),
				new CBRawTrackingData($userId, $date, 'garmin-1', '{"water":50,"caloriesIn":20,"steps":200,"minutesVeryActive":150,"minutesFairlyActive":130,"minutesLightlyActive":200}'),
			),
			$secondDate => array(
				new CBRawTrackingData($userId, $secondDate, CBRawTrackingData::FITBIT_V1_SOURCE, '{"water":50,"caloriesIn":20,"steps":200,"minutesVeryActive":150,"minutesFairlyActive":130,"minutesLightlyActive":200}'),
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
					'heavy_activity' => 200,
					'light_30' => true,
					'light_60' => true,
					'light_90' => true,
					'moderate_10' => true,
					'moderate_30' => true,
					'moderate_45' => true,
					'moderate_60' => true,
					'moderate_90' => true,
					'heavy_10' => true,
					'heavy_30' => true,
					'heavy_45' => true,
					'heavy_60' => true,
					'heavy_90' => true,
					'water_days' => true,
					'food_days' => true,
					'food_or_water_days' => true,
					'distance_1' => true,
					'distance_2' => true,
					'distance_3' => true,
					'distance_4' => false,
					'distance_5' => false,
					'distance_6' => false,
					'distance_8' => false,
					'distance_10' => false,
					'distance_15' => false,
					'steps_8k' => false,
					'steps_10k' => false,
					'steps_12k' => false,
					'steps_15k' => false,
					'wearing_fitbit' => true
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
					'heavy_activity' => 150,
					'light_30' => true,
					'light_60' => true,
					'light_90' => true,
					'moderate_10' => true,
					'moderate_30' => true,
					'moderate_45' => true,
					'moderate_60' => true,
					'moderate_90' => true,
					'heavy_10' => true,
					'heavy_30' => true,
					'heavy_45' => true,
					'heavy_60' => true,
					'heavy_90' => true,
					'water_days' => true,
					'food_days' => true,
					'food_or_water_days' => true,
					'distance_1' => false,
					'distance_2' => false,
					'distance_3' => false,
					'distance_4' => false,
					'distance_5' => false,
					'distance_6' => false,
					'distance_8' => false,
					'distance_10' => false,
					'distance_15' => false,
					'steps_8k' => false,
					'steps_10k' => false,
					'steps_12k' => false,
					'steps_15k' => false,
					'wearing_fitbit' => true
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
		
		$light30 = true;
		$light60 = true;
		$light90 = true;
		$moderate10 = true;
		$moderate30 = true;
		$moderate45 = true;
		$moderate60 = true;
		$moderate90 = true;
		$heavy10 = true;
		$heavy30 = true;
		$heavy45 = true;
		$heavy60 = false;
		$heavy90 = false;
		$waterDays = true;
		$foodDays = true;
		$foodOrWaterDays = true;
		$distance1 = true;
		$distance2 = true;
		$distance3 = true;
		$distance4 = true;
		$distance5 = true;
		$distance6 = true;
		$distance8 = true;
		$distance10 = true;
		$distance15 = false;
		$steps8k = false;
		$steps10k = false;
		$steps12k = false;
		$steps15k = false;
		$wearingFitbit = true;
		
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
					'heavy_activity' => $heavyActivity,
					'light_30' => $light30,
					'light_60' => $light60,
					'light_90' => $light90,
					'moderate_10' => $moderate10,
					'moderate_30' => $moderate30,
					'moderate_45' => $moderate45,
					'moderate_60' => $moderate60,
					'moderate_90' => $moderate90,
					'heavy_10' => $heavy10,
					'heavy_30' => $heavy30,
					'heavy_45' => $heavy45,
					'heavy_60' => $heavy60,
					'heavy_90' => $heavy90,
					'water_days' => $waterDays,
					'food_days' => $foodDays,
					'food_or_water_days' => $foodOrWaterDays,
					'distance_1' => $distance1,
					'distance_2' => $distance2,
					'distance_3' => $distance3,
					'distance_4' => $distance4,
					'distance_5' => $distance5,
					'distance_6' => $distance6,
					'distance_8' => $distance8,
					'distance_10' => $distance10,
					'distance_15' => $distance15,
					'steps_8k' => $steps8k,
					'steps_10k' => $steps10k,
					'steps_12k' => $steps12k,
					'steps_15k' => $steps15k,
					'wearing_fitbit' => $wearingFitbit
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
				'lightly_active' => $lightlyActive,
				'light_30' => $light30,
				'light_60' => $light60,
				'light_90' => $light90,
				'moderate_10' => $moderate10,
				'moderate_30' => $moderate30,
				'moderate_45' => $moderate45,
				'moderate_60' => $moderate60,
				'moderate_90' => $moderate90,
				'heavy_10' => $heavy10,
				'heavy_30' => $heavy30,
				'heavy_45' => $heavy45,
				'heavy_60' => $heavy60,
				'heavy_90' => $heavy90,
				'water_days' => $waterDays,
				'food_days' => $foodDays,
				'food_or_water_days' => $foodOrWaterDays,
				'distance_1' => $distance1,
				'distance_2' => $distance2,
				'distance_3' => $distance3,
				'distance_4' => $distance4,
				'distance_5' => $distance5,
				'distance_6' => $distance6,
				'distance_8' => $distance8,
				'distance_10' => $distance10,
				'distance_15' => $distance15,
				'steps_8k' => $steps8k,
				'steps_10k' => $steps10k,
				'steps_12k' => $steps12k,
				'steps_15k' => $steps15k,
				'wearing_fitbit' => $wearingFitbit
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
		
		$light30 = true;
		$light60 = true;
		$light90 = true;
		$moderate10 = true;
		$moderate30 = true;
		$moderate45 = true;
		$moderate60 = true;
		$moderate90 = true;
		$heavy10 = true;
		$heavy30 = true;
		$heavy45 = true;
		$heavy60 = false;
		$heavy90 = false;
		$waterDays = true;
		$foodDays = true;
		$foodOrWaterDays = true;
		$distance1 = true;
		$distance2 = true;
		$distance3 = true;
		$distance4 = true;
		$distance5 = true;
		$distance6 = true;
		$distance8 = true;
		$distance10 = true;
		$distance15 = false;
		$steps8k = false;
		$steps10k = false;
		$steps12k = false;
		$steps15k = false;
		$wearingFitbit = true;
		
		
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
					'lightly_active' => $lightlyActive,
				'light_30' => $light30,
				'light_60' => $light60,
				'light_90' => $light90,
				'moderate_10' => $moderate10,
				'moderate_30' => $moderate30,
				'moderate_45' => $moderate45,
				'moderate_60' => $moderate60,
				'moderate_90' => $moderate90,
				'heavy_10' => $heavy10,
				'heavy_30' => $heavy30,
				'heavy_45' => $heavy45,
				'heavy_60' => $heavy60,
				'heavy_90' => $heavy90,
				'water_days' => $waterDays,
				'food_days' => $foodDays,
				'food_or_water_days' => $foodOrWaterDays,
				'distance_1' => $distance1,
				'distance_2' => $distance2,
				'distance_3' => $distance3,
				'distance_4' => $distance4,
				'distance_5' => $distance5,
				'distance_6' => $distance6,
				'distance_8' => $distance8,
				'distance_10' => $distance10,
				'distance_15' => $distance15,
				'steps_8k' => $steps8k,
				'steps_10k' => $steps10k,
				'steps_12k' => $steps12k,
				'steps_15k' => $steps15k,
				'wearing_fitbit' => $wearingFitbit
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
				'lightly_active' => $lightlyActive,
					'light_30' => $light30,
					'light_60' => $light60,
					'light_90' => $light90,
					'moderate_10' => $moderate10,
					'moderate_30' => $moderate30,
					'moderate_45' => $moderate45,
					'moderate_60' => $moderate60,
					'moderate_90' => $moderate90,
					'heavy_10' => $heavy10,
					'heavy_30' => $heavy30,
					'heavy_45' => $heavy45,
					'heavy_60' => $heavy60,
					'heavy_90' => $heavy90,
					'water_days' => $waterDays,
					'food_days' => $foodDays,
					'food_or_water_days' => $foodOrWaterDays,
					'distance_1' => $distance1,
					'distance_2' => $distance2,
					'distance_3' => $distance3,
					'distance_4' => $distance4,
					'distance_5' => $distance5,
					'distance_6' => $distance6,
					'distance_8' => $distance8,
					'distance_10' => $distance10,
					'distance_15' => $distance15,
					'steps_8k' => $steps8k,
					'steps_10k' => $steps10k,
					'steps_12k' => $steps12k,
					'steps_15k' => $steps15k,
					'wearing_fitbit' => $wearingFitbit
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