<?php

class Test_CBChallenges extends WP_UnitTestCase {

	private $user_id;
	private $customer;
	private $challenges;

	function setUp() {
		parent::setUp();
		$this->customer = new CBCustomer(167);
	}
	function tearDown() {
		parent::tearDown();
		//unset($connection);
	}

	function test_construction() {
		$this->assertNotNull($this->customer);
		$this->assertNotNull($this->customer->challenges);
	}

	function test_parse_month() {
		$this->assertEquals(
			(object) array(
				'start' => new DateTime("2016-06-01"),
				'end' => new DateTime("2016-06-30"),
				'last_start' => new DateTime("2016-05-01"),
				'last_end' => new DateTime("2016-05-31"),
				'next_start' => new DateTime("2016-07-01"),
				'next_end' => new DateTime("2016-07-31"),
				'days_in_month' => 30,
				'days_so_far' => 2,
				'so_far_date' => new DateTime("2016-06-02"),
				'month_string' => "2016-06",
			),
			$this->customer->challenges->parse_month(new DateTime("2016-06-02T01:23:00Z"))
		);
	}

	//
	// Point system
	//
	function test_points() {
		$key = 'some_challenge';
		$month = new DateTime('2016-06');

		// Make sure we start blank
		$this->assertEquals(0, $this->customer->challenges->get_points($key, $month));
		$this->assertEquals(0, $this->customer->challenges->get_month_points($month));
		$this->assertEquals(0, $this->customer->challenges->get_total_points());

		// Add some points
		$this->customer->challenges->record_points($key, 10, $month);

		// Make sure they were recorded correctly
		$this->assertEquals(10, $this->customer->challenges->get_points($key, $month));
		$this->assertEquals(10, $this->customer->challenges->get_month_points($month));
		$this->assertEquals(10, $this->customer->challenges->get_total_points());

		// Recording a different value should reflect in the totals
		$this->customer->challenges->record_points($key, 20, $month);
		$this->assertEquals(20, $this->customer->challenges->get_points($key, $month));
		$this->assertEquals(20, $this->customer->challenges->get_month_points($month));
		$this->assertEquals(20, $this->customer->challenges->get_total_points());

		// Recording a different value should reflect properly in the totals
		$key2 = 'some_new_challenge';
		$this->customer->challenges->record_points($key2, 10, $month);
		$this->assertEquals(20, $this->customer->challenges->get_points($key, $month));
		$this->assertEquals(10, $this->customer->challenges->get_points($key2, $month));
		$this->assertEquals(30, $this->customer->challenges->get_month_points($month));
		$this->assertEquals(30, $this->customer->challenges->get_total_points());

		// Changing month should leave old month total alone, update overall total
		$month2 = new DateTime('2016-07');
		$this->customer->challenges->record_points($key, 10, $month2);
		$this->assertEquals(20, $this->customer->challenges->get_points($key, $month));
		$this->assertEquals(10, $this->customer->challenges->get_points($key, $month2));
		$this->assertEquals(30, $this->customer->challenges->get_month_points($month));
		$this->assertEquals(10, $this->customer->challenges->get_month_points($month2));
		$this->assertEquals(40, $this->customer->challenges->get_total_points());

		// Lowering a previous value should lower all totals appropriately
		$this->customer->challenges->record_points($key, 10, $month);
		$this->assertEquals(10, $this->customer->challenges->get_points($key, $month));
		$this->assertEquals(10, $this->customer->challenges->get_points($key, $month2));
		$this->assertEquals(20, $this->customer->challenges->get_month_points($month));
		$this->assertEquals(10, $this->customer->challenges->get_month_points($month2));
		$this->assertEquals(30, $this->customer->challenges->get_total_points());
	}

	//
	// Personal bests
	//
	function test_personal_bests() {
		$key = 'some_metric';
		$date = new DateTime('2016-06-02');

		// Make sure we start blank
		$this->assertFalse($this->customer->challenges->get_personal_best($key));

		// Set a best, and check it's as we expect
		$this->customer->challenges->update_personal_best($key, 10, $date);
		$this->assertEquals(
			$this->customer->challenges->get_personal_best($key, $date),
			(object) array(
				'value' => 10,
				'date' => $date,
				'pvalue' => false,
				'pdate' => false,
			)
		);

		// Set a new best, make sure it overrode and saved the old one
		$date2 = new DateTime('2016-06-03');
		$update = $this->customer->challenges->update_personal_best($key, 11, $date2);
		$new_best = $this->customer->challenges->get_personal_best($key, $date2);
		$this->assertEquals(
			(object) array(
				'previous_best' => (object) array(
					'value' => 10,
					'date' => $date,
					'pvalue' => false,
					'pdate' => false,
				),
				'new_best' => (object) array(
					'value' => 11,
					'date' => $date2,
					'pvalue' => 10,
					'pdate' => $date,
				),
			),
			$update
		);
		$this->assertEquals(
			(object) array(
				'value' => 11,
				'date' => $date2,
				'pvalue' => 10,
				'pdate' => $date,
			),
			$new_best
		);

		// A lower best should not change anything
		$date3 = new DateTime('2016-06-04');
		$update = $this->customer->challenges->update_personal_best($key, 10.5, $date3);
		$new_best = $this->customer->challenges->get_personal_best($key, $date3);
		$this->assertEquals(
			(object) array(
				'previous_best' => (object) array(
					'value' => 11,
					'date' => $date2,
					'pvalue' => 10,
					'pdate' => $date,
				),
				'new_best' => false,
			),
			$update
		);
		$this->assertEquals(
			(object) array(
				'value' => 11,
				'date' => $date2,
				'pvalue' => 10,
				'pdate' => $date,
			),
			$new_best
		);

		// Set a new best in a new month, should work as expected
		$date4 = new DateTime('2016-07-03');
		$update = $this->customer->challenges->update_personal_best($key, 15, $date4);
		$new_best = $this->customer->challenges->get_personal_best($key, $date4);
		$this->assertEquals(
			(object) array(
				'previous_best' => (object) array(
					'value' => 11,
					'date' => $date2,
					'pvalue' => false,  // note: this is a new month!
					'pdate' => false,   //       so these are blank!
				),
				'new_best' => (object) array(
					'value' => 15,
					'date' => $date4,
					'pvalue' => 11,
					'pdate' => $date2,
				),
			),
			$update
		);
		$this->assertEquals(
			(object) array(
				'value' => 15,
				'date' => $date4,
				'pvalue' => 11,       // note: however, these correctly reflect
				'pdate' => $date2,    //       the record as of last call
			),
			$new_best
		);
	}

}

