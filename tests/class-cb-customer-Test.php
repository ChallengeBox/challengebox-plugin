<?php

class Test_CBCustomer extends WP_UnitTestCase {

	protected $api;
	function setUp() {
		$this->api = new CBWoo();
	}
	function tearDown() {
		unset($connection);
	}

	function test_construction() {
		$a = new CBCustomer(167);
	}

	function test_get_meta() {
		/*
		var_export((new CBCustomer(167))->get_meta());
		$this->assertEquals((new CBCustomer(167))->get_meta('first_name'), 'Ryan');
		*/
	}

	function test_has_box_order_this_month() {
		/*
		$this->assertFalse((new CBCustomer(167))->has_box_order_this_month());
		$this->assertFalse((new CBCustomer(8))->has_box_order_this_month());
		*/
	}

	function test_get_order_notes() {
		/*
		$a = new CBCustomer(167);
		$o = $a->get_orders()[0];
		var_dump($a->get_order_notes($o->id));
		*/
	}

	function test_order_was_shipped() {
		$a = new CBCustomer(167);
		$this->assertTrue($a->order_was_shipped($this->api->get_order(2659)));
		$this->assertFalse($a->order_was_shipped($this->api->get_order(922)));
	}

	function test_estimate_clothing_gender() {
		$this->assertEquals((new CBCustomer(167))->estimate_clothing_gender(), 'male');
		$this->assertEquals((new CBCustomer(388))->estimate_clothing_gender(), 'female');
		$this->assertEquals((new CBCustomer(1))->estimate_clothing_gender(), 'male');
	}

	function test_estimate_tshirt_size() {
		$this->assertEquals((new CBCustomer(167))->estimate_tshirt_size(), 'm');
		$this->assertEquals((new CBCustomer(388))->estimate_tshirt_size(), 'm');
		$this->assertEquals((new CBCustomer(1))->estimate_tshirt_size(), 'l');
	}

	function test_estimate_box_month() {
		$this->assertEquals((new CBCustomer(167))->estimate_box_month(), 3);
		$this->assertEquals((new CBCustomer(1))->estimate_box_month(), 1);
		$this->assertEquals((new CBCustomer(95))->estimate_box_month(), 0);
	}

	function test_has_active_subscription() {
		$this->assertTrue((new CBCustomer(167))->has_active_subscription());
		$this->assertTrue((new CBCustomer(1))->has_active_subscription());
		$this->assertFalse((new CBCustomer(8))->has_active_subscription());
	}

}

