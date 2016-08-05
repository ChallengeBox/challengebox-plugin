<?php

class Test_CB extends WP_UnitTestCase {
	function setUp() {
		parent::setUp();
	}
	function tearDown() {
		parent::tearDown();
	}
	function test_any() {
		$this->assertTrue(CB::any(array(1)));
		$this->assertTrue(CB::any(array(0,NULL,'','hi')));
		$this->assertFalse(CB::any(array()));
		$this->assertFalse(CB::any(array(0,NULL,'',array())));
	}
	function test_all() {
		$this->assertTrue(CB::all(array()));
		$this->assertFalse(CB::all(array(0,NULL,'','hi')));
		$this->assertFalse(CB::all(array(1,2,'',3,4)));
		$this->assertTrue(CB::all(array(1,2,3,4)));
		$this->assertTrue(CB::all(array('hi',array('t','h','e','r','e'))));
	}
	function test_number_of_months_apart() {
		$this->assertEquals(1, CB::number_of_months_apart(new DateTime("2016-06-28"), new DateTime("2016-07-01")) );
		$this->assertEquals(1, CB::number_of_months_apart(new DateTime("2016-07-28"), new DateTime("2016-06-01")) );
		$this->assertEquals(11, CB::number_of_months_apart(new DateTime("2016-07-28"), new DateTime("2017-06-01")) );
		$this->assertEquals(13, CB::number_of_months_apart(new DateTime("2016-06-28"), new DateTime("2017-07-01")) );
	}
	function test_date_plus_days() {
		$this->assertEquals(
			new DateTime("2016-06-10"),
			CB::date_plus_days(new DateTime("2016-06-01"), 9)
		);
		$this->assertEquals(
			new DateTime("2016-06-09"),
			CB::date_plus_days(new DateTime("2016-05-31"), 9)
		);
	}
}

