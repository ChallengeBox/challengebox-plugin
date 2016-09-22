<?php
namespace Tests\Unit\Includes;

use \BaseTest;
use \CBRawTrackingData;

use \ChallengeBox\Includes\Utilities\Cache;
use \ChallengeBox\Includes\Utilities\Time;


class Test_CBRawTrackingData extends BaseTest 
{
	/**
	 * test: accessors
	 */	
	public function testAccessors()
	{
		$userId = 'a_user_id';
		$date = '2016-01-01';
		$source = 'something-3';
		$data = array('some' => 'stuff');
		
		$rawTrackingData = new CBRawTrackingData(
			$userId, 
			$date, 
			$source, 
			$data
		);

		$this->assertEquals($userId, $rawTrackingData->getUserId());
		$this->assertEquals($date, $rawTrackingData->getDate());
		$this->assertEquals($source, $rawTrackingData->getSource());
		$this->assertEquals(json_encode($data), $rawTrackingData->getData());
	}
	
	/**
	 * test: get data decoded
	 */
	public function testGetDataDecoded()
	{
		$userId = 'a_user_id';
		$date = '2016-01-01';
		$source = 'something-3';
		$dataEncoded = '{"some":"data"}';
		
		$rawTrackingData = new CBRawTrackingData(
			$userId, 
			$date, 
			$source, 
			$dataEncoded
		);

		$this->assertEquals(array('some' => 'data'), $rawTrackingData->getDataDecoded());
	}

	/**
	 * test: save - inserts if no record previously exists
	 */
	public function testSaveInsertsIfNoRecordPreviouslyExists()
	{
		// mock
		$userId = 123;
		$date = '2014-05-03 00:00:00';
		$source = 'fitbit-v1';
		$data = '{"steps":5000,"calories":34,"water":40,"whiskey":940}';
		
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
				$this->equalTo('select count(user_id) as num from wp_raw_tracking_data where user_id = %d and date = %s and source = %s'),
				$this->equalTo(array($userId, $date, $source))
			)
			->willReturn($preparedStatement);
		$wpdb->expects($this->once())
			->method('get_results')
			->with($this->equalTo($preparedStatement))
			->willReturn($results);
		$wpdb->expects($this->once())
			->method('insert')
			->with(
				$this->equalTo('wp_raw_tracking_data'),
				$this->equalTo(array(
					'user_id' => $userId,
					'date' => $date,
					'source' => $source,
					'data' => $data
				))	
			);
		$wpdb->expects($this->never())
			->method('update');
		$wpdb->prefix = $wordpressPrefix;
		
		
		$rawTrackingData = $this->getMockBuilder('CBRawTrackingData')
			->setConstructorArgs(array($userId, $date, $source, $data))
			->setMethods(array('getWpdb'))
			->getMock();
		$rawTrackingData->expects($this->any())
			->method('getWpdb')
			->willReturn($wpdb);
		
		
		// run
		$rawTrackingData->save();
	}
	
	/**
	 * test: save - updates if a record previously exists
	 */
	public function testSaveUpdatesIfARecordPreviouslyExists()
	{
		// mock
		$userId = 123;
		$date = '2014-05-03 00:00:00';
		$source = 'fitbit-v1';
		$data = array('steps' => 5000, 'calories' => 34, 'water' => 40, 'whiskey' => 940);
		$dataJson = '{"steps":5000,"calories":34,"water":40,"whiskey":940}';
		
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
				$this->equalTo('select count(user_id) as num from wp_raw_tracking_data where user_id = %d and date = %s and source = %s'),
				$this->equalTo(array($userId, $date, $source))
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
				$this->equalTo('wp_raw_tracking_data'),
				$this->equalTo(array(
					'data' => $dataJson
				)),
				$this->equalTo(array(
					'user_id' => $userId,
					'date' => $date,
					'source' => $source
				)),
				$this->equalTo(array("%s")),
				$this->equalTo(array("%d","%s","%s"))
			);
		$wpdb->prefix = $wordpressPrefix;
		
		
		$rawTrackingData = $this->getMockBuilder('CBRawTrackingData')
			->setConstructorArgs(array($userId, $date, $source, $data))
			->setMethods(array('getWpdb'))
			->getMock();
		$rawTrackingData->expects($this->any())
			->method('getWpdb')
			->willReturn($wpdb);

		// run
		$rawTrackingData->save();
	}
	
	/**
	 * test: to cache format
	 */
	public function testToCacheFormat()
	{
		// params
		$rawTrackingData = new \CBRawTrackingData('a_user_id', '2016-01-01', 'unfit-bit-4', array('something' => 'else'), '2016-01-01 00:00:01', '2016-01-02 00:00:02');
		
		// run
		$results = $rawTrackingData->toCacheFormat();
		
		// post-run assertions
		$expectedResults = (object) [
			'user_id' => 'a_user_id',
			'date' => '2016-01-01',
			'source' => 'unfit-bit-4', 
			'data' => '{"something":"else"}',
			'create_date' => '2016-01-01 00:00:01',
			'last_modified' => '2016-01-02 00:00:02'
		];
		
		$this->assertEquals($expectedResults, $results);
	}
	
	/**
	 * test: multiSave
	 */
	public function testMultiSave()
	{
		// param
		
		$userId = 'a_user_id';
		$source = 'a_source';
		$rawData = array(
			'an_activity' => array(
				'2016-01-01' => 3,
				'2016-01-02' => 4
			)	
		);
		
		// mock
		$recordObject = $this->getMockBuilder('CBRawTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('save'))
			->getMock();
		$recordObject->expects($this->any())
			->method('save');
		
		$rawTrackingData = $this->getMockBuilder('CBRawTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('instantiate'))
			->getMock();
		$rawTrackingData->expects($this->at(0))
			->method('instantiate')
			->with(
				$this->equalTo($userId),
				$this->equalTo('2016-01-01'),
				$this->equalTo($source),
				$this->equalTo(array(
					'an_activity' => 3
				))
			)
			->willReturn($recordObject);
		$rawTrackingData->expects($this->at(1))
			->method('instantiate')
			->with(
				$this->equalTo($userId),
				$this->equalTo('2016-01-02'),
				$this->equalTo($source),
				$this->equalTo(array(
					'an_activity' => 4
				))
			)
			->willReturn($recordObject);
		
		// run
		$rawTrackingData->multiSave($userId, $source, $rawData);
	}
	
	/**
	 * test: updateCache with pre-existing record
	 */
	public function testUpdateCacheWithPreExistingRecord()
	{
		// param & mocks
		$userId = 123;
		$date = '2016-01-03';
		$source = 'fitbit-3';

		$cachedList = array(
			(object) [
				'user_id' => $userId,
				'date' => $date,
				'source' => 'something-2',
				'data' => '{"data":"eh"}',
				'create_date' => '2015-01-01 00:00:00',
				'last_modified' => '2015-01-01 00:00:03'
			],
			(object) [
				'user_id' => $userId,
				'date' => $date,
				'source' => $source,
				'data' => '{"data":"eh"}',
				'create_date' => '2015-01-01 00:00:00',
				'last_modified' => '2015-01-01 00:00:03'
			]
		);
		
		$updatedCachedList = array(
			(object) [
				'user_id' => $userId,
				'date' => $date,
				'source' => 'something-2',
				'data' => '{"data":"eh"}',
				'create_date' => '2015-01-01 00:00:00',
				'last_modified' => '2015-01-01 00:00:03'
			],
			(object) [
				'user_id' => $userId,
				'date' => $date,
				'source' => $source,
				'data' => '{"some":"stuff"}',
				'create_date' => '2016-01-02 00:00:00',
				'last_modified' => '2016-01-02 00:00:01'
			]
		);
		
		$cache = $this->getMockBuilder('ChallengeBox\Includes\Utilities\Cache')
			->disableOriginalConstructor()
			->setMethods(array('get', 'set'))
			->getMock();
		$cache->expects($this->once())
			->method('get')
			->with(
				$this->equalTo(CBRawTrackingData::DATE_CACHE_KEY . $userId . '-' . $date)	
			)
			->willReturn($cachedList);
		$cache->expects($this->once())
			->method('set')
			->with(
				$this->equalTo(CBRawTrackingData::DATE_CACHE_KEY . $userId . '-' . $date),
				$this->equalTo($updatedCachedList),
				$this->equalTo(Time::ONE_DAY)
			);
		Cache::setInstance($cache);
		
		$rawTrackingData = new CBRawTrackingData(
			$userId, 
			$date, 
			$source, 
			array('some' => 'stuff'), 
			'2016-01-02 00:00:00', 
			'2016-01-02 00:00:01'
		);
		
		// run
		$rawTrackingData->updateCache($rawTrackingData);
	}
	
	/**
	 * test: updateCache without pre-existing record
	 */
	public function testUpdateCacheWithoutPreExistingRecord()
	{
		// param & mocks
		$userId = 123;
		$date = '2016-01-03';
		$source = 'fitbit-3';

		$cachedList = array(
			(object) [
				'user_id' => $userId,
				'date' => $date,
				'source' => 'something-2',
				'data' => '{"data":"eh"}',
				'create_date' => '2015-01-01 00:00:00',
				'last_modified' => '2015-01-01 00:00:03'
			]
		);
		
		$updatedCachedList = array(
			(object) [
				'user_id' => $userId,
				'date' => $date,
				'source' => 'something-2',
				'data' => '{"data":"eh"}',
				'create_date' => '2015-01-01 00:00:00',
				'last_modified' => '2015-01-01 00:00:03'
			],
			(object) [
				'user_id' => $userId,
				'date' => $date,
				'source' => $source,
				'data' => '{"some":"stuff"}',
				'create_date' => '2016-01-02 00:00:00',
				'last_modified' => '2016-01-02 00:00:01'
			]
		);
		
		$cache = $this->getMockBuilder('ChallengeBox\Includes\Utilities\Cache')
			->disableOriginalConstructor()
			->setMethods(array('get', 'set'))
			->getMock();
		$cache->expects($this->once())
			->method('get')
			->with(
				$this->equalTo(CBRawTrackingData::DATE_CACHE_KEY . $userId . '-' . $date)	
			)
			->willReturn($cachedList);
		$cache->expects($this->once())
			->method('set')
			->with(
				$this->equalTo(CBRawTrackingData::DATE_CACHE_KEY . $userId . '-' . $date),
				$this->equalTo($updatedCachedList),
				$this->equalTo(Time::ONE_DAY)
			);
		Cache::setInstance($cache);
		
		$rawTrackingData = new CBRawTrackingData(
			$userId, 
			$date, 
			$source, 
			array('some' => 'stuff'), 
			'2016-01-02 00:00:00', 
			'2016-01-02 00:00:01'
		);
		
		// run
		$rawTrackingData->updateCache($rawTrackingData);
	}
	
	/**
	 * test: findByUserIdAndDates
	 */
	public function testFindByUserIdAndDates()
	{
		// param
		$userId = 123;
		$startDate = '2016-01-01';
		$endDate = '2016-01-03';
		
		// mock
		$cacheKey['2016-01-01'] = CBRawTrackingData::DATE_CACHE_KEY . $userId . '-2016-01-01';
		$cacheKey['2016-01-02'] = CBRawTrackingData::DATE_CACHE_KEY . $userId . '-2016-01-02';
		$cacheKey['2016-01-03'] = CBRawTrackingData::DATE_CACHE_KEY . $userId . '-2016-01-03';
		
		$cachedObjects = array(
			(object) [
				'user_id' => $userId,
				'date' => '2016-01-03',
				'source' => 'garmin-3',
				'data' => '{}'
			],
			(object) [
				'user_id' => $userId,
				'date' => '2016-01-03',
				'source' => 'fitbit-2',
				'data' => '{}'
			]
		);
		
		$cache = $this->getMockBuilder('Cache')
			->disableOriginalConstructor()
			->setMethods(array('get', 'set'))
			->getMock();
		$cache->expects($this->at(0))
			->method('get')
			->with($this->equalTo($cacheKey['2016-01-01']))
			->willReturn(false);
		$cache->expects($this->at(1))
			->method('get')
			->with($this->equalTo($cacheKey['2016-01-02']))
			->willReturn(false);
		$cache->expects($this->at(2))
			->method('get')
			->with($this->equalTo($cacheKey['2016-01-03']))
			->willReturn($cachedObjects);
		$cache->expects($this->at(3))
			->method('set')
			->with(
				$this->equalTo($cacheKey['2016-01-01']),
				$this->equalTo(array(
					(object) [
						'user_id' => $userId,
						'date' => '2016-01-01',
						'source' => 'garmin-3',
						'data' => '{}'
					],
					(object) [
						'user_id' => $userId,
						'date' => '2016-01-01',
						'source' => 'fitbit-2',
						'data' => '{}'
					]
				)),
				$this->equalTo(Time::ONE_DAY)
			);
		$cache->expects($this->at(4))
			->method('set')
			->with(
				$this->equalTo($cacheKey['2016-01-02']),
				$this->equalTo(array(
					(object) [
							'user_id' => $userId,
							'date' => '2016-01-02',
							'source' => 'garmin-3',
							'data' => '{}'
					]
				)),
				$this->equalTo(Time::ONE_DAY)
			);
		Cache::setInstance($cache);

		$expectedSql = 'select * from wp_raw_tracking_data where user_id = ? and date in (?,?)';
		$expectedParameters = array(
			$userId, '2016-01-01', '2016-01-02'	
		);
		
		$queryResults = array(
			(object) [
					'user_id' => $userId,
					'date' => '2016-01-01',
					'source' => 'garmin-3',
					'data' => '{}'
			],
			(object) [
					'user_id' => $userId,
					'date' => '2016-01-02',
					'source' => 'garmin-3',
					'data' => '{}'
			],
			(object) [
					'user_id' => $userId,
					'date' => '2016-01-01',
					'source' => 'fitbit-2',
					'data' => '{}'
			],
		);
		
		$preparedStatement = 'prepared_statement';
		
		$wpdb = $this->getMockBuilder('\stdClass')
			->disableOriginalConstructor()
			->setMethods(array('prepare', 'get_results'))
			->getMock();
		$wpdb->expects($this->once())
			->method('prepare')
			->with($this->equalTo($expectedSql), $this->equalTo($expectedParameters))
			->willReturn($preparedStatement);
		$wpdb->expects($this->once())
			->method('get_results')
			->with($this->equalTo($preparedStatement))
			->willReturn($queryResults);
		$wpdb->prefix = 'wp_';
		
		$rawTrackingData = $this->getMockBuilder('CBRawTrackingData')
			->disableOriginalConstructor()
			->setMethods(array('getWpdb'))
			->getMock();
		$rawTrackingData->expects($this->once())
			->method('getWpdb')
			->willReturn($wpdb);
		
		// run
		$results = $rawTrackingData->findByUserIdAndDates($userId, $startDate, $endDate);
		
		// post-run assertions
		$expectedResults = array(
			'2016-01-01' => array(
				(object) [
						'user_id' => $userId,
						'date' => '2016-01-01',
						'source' => 'garmin-3',
						'data' => '{}'
				],
				(object) [
						'user_id' => $userId,
						'date' => '2016-01-01',
						'source' => 'fitbit-2',
						'data' => '{}'
				]
			),
			'2016-01-02' => array(
				(object) [
						'user_id' => $userId,
						'date' => '2016-01-02',
						'source' => 'garmin-3',
						'data' => '{}'
				]
			),
			'2016-01-03' => array(
				(object) [
						'user_id' => $userId,
						'date' => '2016-01-03',
						'source' => 'garmin-3',
						'data' => '{}'
				],
				(object) [
						'user_id' => $userId,
						'date' => '2016-01-03',
						'source' => 'fitbit-2',
						'data' => '{}'
				]
			),
		);
		$this->assertEquals($expectedResults, $results);
			
	}
}