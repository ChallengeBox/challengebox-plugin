<?php

use Carbon\Carbon;

class Test_CBWeeklyChallenge extends WP_UnitTestCase {

	private $user_id;
	private $customer;

	function setUp() {
		parent::setUp();
		$this->customer = new CBCustomer(1);
	}
	function tearDown() {
		parent::tearDown();
	}

	function test_dates() {

		$today = Carbon::createFromDate(2016, 8, 16, 'America/New_York');
		$challenge = new CBWeeklyChallenge($this->customer, $today);

		// Just a normal week
		$this->assertEquals(Carbon::createFromDate(2016, 8, 15, 'America/New_York')->startOfDay(),
			$challenge->start);
		$this->assertEquals(Carbon::createFromDate(2016, 8, 21, 'America/New_York')->endOfDay(),
			$challenge->end);
		$this->assertEquals(Carbon::createFromDate(2016, 8, 12, 'America/New_York')->startOfDay(),
			$challenge->entry_open);
		$this->assertEquals(Carbon::createFromDate(2016, 8, 14, 'America/New_York')->endOfDay(),
			$challenge->entry_closed);

		// Feb 29 week
		$today = Carbon::createFromDate(2016, 2, 29, 'America/New_York');
		$challenge = new CBWeeklyChallenge($this->customer, $today);
		$this->assertEquals(Carbon::createFromDate(2016, 2, 29, 'America/New_York')->startOfDay(),
			$challenge->start);
		$this->assertEquals(Carbon::createFromDate(2016, 3, 6, 'America/New_York')->endOfDay(),
			$challenge->end);
		$this->assertEquals(Carbon::createFromDate(2016, 2, 26, 'America/New_York')->startOfDay(),
			$challenge->entry_open);
		$this->assertEquals(Carbon::createFromDate(2016, 2, 28, 'America/New_York')->endOfDay(),
			$challenge->entry_closed);

	}
}

