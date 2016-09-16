<?php
include_once(CHALLENGEBOX_PLUGIN_DIR . '/includes/class-cb-raw-tracking-data.php');

use PHPUnit\Framework\TestCase;

class Test_CBRawTrackingData extends TestCase {

	function setUp() {
		parent::setUp();
	}

	function tearDown() {
		parent::tearDown();
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
		$data = '{"steps":5000,"calories":34,"water":40,"whiskey":940}';
		
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
					'data' => $data
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
}