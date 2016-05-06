<?php

class Test_CBWoo extends WP_UnitTestCase {

	protected $api;
	function setUp() {
		$this->api = new CBWoo();
	}
	function tearDown() {
		unset($connection);
	}

	function test_get_order_statuses() {
		$this->assertEquals(
			$this->api->get_order_statuses(),
			array(
				'pending' => 'Pending Payment',
				'processing' => 'Processing',
				'on-hold' => 'On Hold',
				'completed' => 'Completed',
				'cancelled' => 'Cancelled',
				'refunded' => 'Refunded',
				'failed' => 'Failed',
			)
		);
	}

	function test_get_subscription_statuses() {
		$this->assertEquals(
			$this->api->get_subscription_statuses(),
			array(
				'pending' => 'Pending',
				'active' => 'Active',
				'on-hold' => 'On hold',
				'cancelled' => 'Cancelled',
				'switched' => 'Switched',
				'expired' => 'Expired',
				'pending-cancel' => 'Pending Cancellation',
			)
		);
	}

	function test_format_sku() {
		$this->assertEquals('cb_m1_female_m', CBWoo::format_sku(1, 'Female', 'M'));
		$this->assertEquals('cb_m10_male_xs', CBWoo::format_sku(10, 'MAlE', 'xs'));
		$this->assertEquals('m1_female_m', CBWoo::format_sku(1, 'Female', 'M', 'v2'));
		$this->assertEquals('m10_male_xs', CBWoo::format_sku(10, 'MAlE', 'xs', 'v2'));
	}

	function test_parse_box_sku() {
		$this->assertEquals(
			CBWoo::parse_box_sku('cb_m3_FemAle_xXl'),
			(object) array('sku_version'=>'v1', 'month'=>3, 'gender'=>'female', 'size'=>'xxl', 'plan'=>'1m')
		);
		$this->assertEquals(
			CBWoo::parse_box_sku('m2_male_m'),
			(object) array('sku_version'=>'v2', 'month'=>2, 'gender'=>'male', 'size'=>'m', 'plan'=>NULL)
		);
	}

	/**
	 * @expectedException InvalidSku
	 */
	function test_parse_box_sku_cb_single() {
		CBWoo::parse_box_sku('cb_single');
	}
	/**
	 * @expectedException InvalidSku
	 */
	function test_parse_box_sku_cb_sub_monthly() {
		CBWoo::parse_box_sku('cb_sub_monthly');
	}
	/**
	 * @expectedException InvalidSku
	 */
	function test_parse_box_sku_subsription_monthly() {
		CBWoo::parse_box_sku('subscription_monthly');
	}
	/**
	 * @expectedException InvalidSku
	 */
	function test_parse_box_sku_one_two_three_four_five() {
		CBWoo::parse_box_sku('one_two_three_four_five');
	}

	function test_arrange_orders_by_status() {
		$orders = array(
			0 => (object) array('id'=>1, 'status'=>'completed'),
			1 => (object) array('id'=>2, 'status'=>'processing'),
			2 => (object) array('id'=>3, 'status'=>'processing'),
		);
		$result = $this->api->arrange_orders_by_status($orders);
		$this->assertEquals(
			$result,
			array(
				'pending' => array(),
				'processing' => array(
					1 => (object) array('id'=>2, 'status'=>'processing'),
					2 => (object) array('id'=>3, 'status'=>'processing')
				),
				'on-hold' => array(),
				'completed' => array(
					0 => (object) array('id'=>1, 'status'=>'completed')
				),
				'cancelled' => array(),
				'refunded' => array(),
				'failed' => array()
			)
		);
	}

	function test_parse_order_options() {

		$this->assertEquals(
			$this->api->parse_order_options((object) array(
				'line_items' => array(
					(object) array(
						'sku' => 'cb_m1_male_m',
						'meta' => array(
							(object) array(
								'key' => 'pa_gender',
								'label' => 'Gender',
								'value' => 'Male'
							),
							(object) array(
								'key' => 'pa_size',
								'label' => 'T-Shirt Size',
								'value' => 'm'
							)
						)
					)
				)
			)),
			(object) array(
				'month' => 1,
				'gender' => 'male',
				'size' => 'm',
				'plan' => '1m',
				'sku_version' => 'v1'
			)
		);

		$this->assertEquals(
			$this->api->parse_order_options((object) array(
				'line_items' => array(
					(object) array(
						'sku' => 'cb_m3_female_xxxl_3m',
						'meta' => array(
							(object) array(
								'key' => 'pa_gender',
								'label' => 'Gender',
								'value' => 'Female'
							),
							(object) array(
								'key' => 'pa_size',
								'label' => 'T-Shirt Size',
								'value' => 'xxxl'
							)
						)
					)
				)
			)),
			(object) array(
				'month' => 3,
				'gender' => 'female',
				'size' => 'xxxl',
				'plan' => '3m',
				'sku_version' => 'v1',
			)
		);

		$this->assertEquals(
			$this->api->parse_order_options((object) array(
				'line_items' => array(
					(object) array(
						'sku' => 'm3_female_xxxl',
						'meta' => array(
							(object) array(
								'key' => 'pa_gender',
								'label' => 'Gender',
								'value' => 'Female'
							),
							(object) array(
								'key' => 'pa_size',
								'label' => 'T-Shirt Size',
								'value' => 'xxxl'
							)
						)
					)
				)
			)),
			(object) array(
				'month' => 3,
				'gender' => 'female',
				'size' => 'xxxl',
				'plan' => NULL,
				'sku_version' => 'v2',
			)
		);

		$this->assertEquals(
			$this->api->parse_order_options((object) array(
				'line_items' => array(
					(object) array(
						'sku' => 'cb_single',
						'meta' => array(
							(object) array(
								'key' => 'pa_gender',
								'label' => 'Gender',
								'value' => 'Female'
							),
							(object) array(
								'key' => 'pa_size',
								'label' => 'T-Shirt Size',
								'value' => 'xxxl'
							)
						)
					)
				)
			)),
			(object) array(
				'month' => NULL,
				'gender' => 'female',
				'size' => 'xxxl',
				'plan' => NULL,
				'sku_version' => NULL,
			)
		);


		// Test skus that shouldn't produce any data
		// Keep in mind that they still would produce data if they
		// had an appropriate meta array
		foreach (
			array(
				'cb_single',
				'cb_sub_monthly',
				'missing_item',
				'cb_sub_3m',
				'complimentary_item',
			) 
		as $bad_sku) {
			$this->assertEquals(
				$this->api->parse_order_options((object) array(
					'line_items' => array((object) array('sku' => $bad_sku))
				)),
				false
			);
		}

	}

	function test_is_valid_box_order() {

		$this->assertEquals(
			true,
			$this->api->is_valid_box_order((object) array(
				'line_items' => array(
					(object) array(
						'sku' => 'cb_m1_male_m',
						'meta' => array(
							(object) array(
								'key' => 'pa_gender',
								'label' => 'Gender',
								'value' => 'Male'
							),
							(object) array(
								'key' => 'pa_size',
								'label' => 'T-Shirt Size',
								'value' => 'm'
							)
						)
					)
				)
			))
		);

		$this->assertEquals(
			true,
			$this->api->is_valid_box_order((object) array(
				'line_items' => array(
					(object) array(
						'sku' => 'cb_m3_female_xxxl_3m',
						'meta' => array(
							(object) array(
								'key' => 'pa_gender',
								'label' => 'Gender',
								'value' => 'Female'
							),
							(object) array(
								'key' => 'pa_size',
								'label' => 'T-Shirt Size',
								'value' => 'xxxl'
							)
						)
					)
				)
			))
		);

		$this->assertEquals(
			true,
			$this->api->is_valid_box_order((object) array(
				'line_items' => array(
					(object) array(
						'sku' => 'cb_single',
						'meta' => array(
							(object) array(
								'key' => 'pa_gender',
								'label' => 'Gender',
								'value' => 'Female'
							),
							(object) array(
								'key' => 'pa_size',
								'label' => 'T-Shirt Size',
								'value' => 'xxxl'
							)
						)
					)
				)
			))
		);

		foreach (
			array(
				'cb_single',
				'cb_sub_monthly',
				'missing_item',
				'cb_sub_3m',
				'complimentary_item',
			) 
		as $bad_sku) {
			$this->assertEquals(
				false,
				$this->api->is_valid_box_order((object) array(
					'line_items' => array((object) array('sku' => $bad_sku))
				))
			);
		}
	}

	function test_extract_subscription_name() {
		$sub = $this->api->get_subscription(6109);
		$this->assertEquals(
			CBWoo::extract_subscription_name($sub),
			"Month to Month ChallengeBox Subscription"
		);
	}
}

/*
 array (
      0 => 
      stdClass::__set_state(array(
         'id' => 6618,
         'subtotal' => '24.99',
         'subtotal_tax' => '0.00',
         'total' => '0.00',
         'total_tax' => '0.00',
         'price' => '0.00',
         'quantity' => 1,
         'tax_class' => NULL,
         'name' => 'Month to Month ChallengeBox Subscription',
         'product_id' => 2378,
         'sku' => 'cb_m1_male_m',
         'meta' => 
        array (
          0 => 
          stdClass::__set_state(array(
             'key' => 'pa_gender',
             'label' => 'Gender',
             'value' => 'Male',
          )),
          1 => 
          stdClass::__set_state(array(
             'key' => 'pa_size',
             'label' => 'T-Shirt Size',
             'value' => 'm',
          )),
        ),
      )),
    ),

*/
