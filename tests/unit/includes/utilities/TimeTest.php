<?php
include_once(CHALLENGEBOX_PLUGIN_DIR . '/includes/utilities/BaseSingleton.php');
include_once(CHALLENGEBOX_PLUGIN_DIR . '/includes/utilities/Time.php');
include_once(CHALLENGEBOX_PLUGIN_DIR . '/vendor/nesbot/carbon/src/Carbon/Carbon.php');
include_once(CHALLENGEBOX_PLUGIN_DIR . '/vendor/nesbot/carbon/src/Carbon/CarbonInterval.php');

use PHPUnit\Framework\TestCase;
use \Carbon\Carbon;


class TimeTest extends TestCase {

	function setUp() {
		parent::setUp();
	}

	function tearDown() {
		parent::tearDown();
	}
	
	/**
	 *  getDateRange($startDate, $endDate) 
	 */
	public function testGetDateRangeWithStrings()
	{
		// param
		$startDate = '2016-02-23';
		$endDate = '2016-03-07';
		
		// run
		$dateRange = \ChallengeBox\Includes\Utilities\Time::getInstance()->getDateRange($startDate, $endDate);
		
		// post-run assertions
		$expectedResults = array(
			'2016-02-23',
			'2016-02-24',
			'2016-02-25',
			'2016-02-26',
			'2016-02-27',
			'2016-02-28',
			'2016-02-29',
			'2016-03-01',
			'2016-03-02',
			'2016-03-03',
			'2016-03-04',
			'2016-03-05',
			'2016-03-06',
			'2016-03-07'	
		);
		$this->assertEquals($expectedResults, $dateRange);
	}
	
	/**
	 *  getDateRange($startDate, $endDate) 
	 */
	public function testGetDateRangeWithCarbonDates()
	{
		// param
		$startDate = Carbon::createFromFormat('Y-m-d','2016-02-23');
		$endDate = Carbon::createFromFormat('Y-m-d','2016-03-07');
		
		// run
		$dateRange = \ChallengeBox\Includes\Utilities\Time::getInstance()->getDateRange($startDate, $endDate);
		
		// post-run assertions
		$expectedResults = array(
			'2016-02-23',
			'2016-02-24',
			'2016-02-25',
			'2016-02-26',
			'2016-02-27',
			'2016-02-28',
			'2016-02-29',
			'2016-03-01',
			'2016-03-02',
			'2016-03-03',
			'2016-03-04',
			'2016-03-05',
			'2016-03-06',
			'2016-03-07'	
		);
		$this->assertEquals($expectedResults, $dateRange);
	}
}