<?php

use Carbon\Carbon;

/**
 * Wrapper for WooCommerce API client and commands for dealing with woo data.
 *
 * Right now this uses Woo's rest API, but we could potentially
 * speed things up in the future by bypassing REST and keeping the
 * format here the same.
 *
 * @package challengebox
 */

class CBWoo {

	private $api;
	private $writeapi;
	private $order_statuses;
	private $subscription_statuses;

	// XXX: Experimental internal api thing.
	// private $_wooapi;

	public function __construct() {
		$options = array('ssl_verify' => false,);
		$this->api = new WC_API_Client(
			'https://www.getchallengebox.com',
			'ck_b6f54bfa509972150c050e1a36c2199ddd5f6017',
			'cs_0d370d150e37bd90f1950a51b1231d4343c2bfb8',
			$options
		);
		$this->writeapi = new WC_API_Client(
			'https://www.getchallengebox.com',
			'ck_869ca223f6b38ff9a17971147490ef8c488476f9',
			'cs_f88941f6bba9bb60154f87f12cddacc1a2c8d195',
			$options
		);

		/*
		// XXX: Experimental internal api.
		wp_set_current_user(167);
		WC()->api->includes();
		WC()->api->register_resources( new WC_API_Server( '/' ) );
		$this->_wooapi = WC()->api;
		*/
	}

	//
	// Read
	//

	public function get_product($id) {
		try {
			return $this->api->products->get($id)->product;
		} catch (WC_API_Client_HTTP_Exception $e) {
			return $this->api->products->get($id)->product;
		}
	}
	public function get_product_by_sku($id) {
		try {
			return $this->api->products->get_by_sku($id)->product;
		} catch (WC_API_Client_HTTP_Exception $e) {
			return $this->api->products->get_by_sku($id)->product;
		}
	}
	public function get_customer($id) {
		try {
			return $this->api->customers->get($id)->customer;
		} catch (WC_API_Client_HTTP_Exception $e) {
			return $this->api->customers->get($id)->customer;
		}
	}
	public function get_order($id) {
		try {
			return $this->api->orders->get($id)->order;
		} catch (WC_API_Client_HTTP_Exception $e) {
			return $this->api->orders->get($id)->order;
		}
	}
	public function get_order_internal($id) {
		// Get the decimal precession
		$dp         = ( isset( $filter['dp'] ) ? intval( $filter['dp'] ) : 2 );
		$order      = wc_get_order( $id );
		$order_post = get_post( $id );

		$order_data = array(
			'id'                        => $order->id,
			'order_number'              => $order->get_order_number(),
			'created_at'                => (new Carbon($order_post->post_date_gmt))->format("Y-m-d\TH:i:s\Z"),
			'updated_at'                => (new Carbon($order_post->post_modified_gmt))->format("Y-m-d\TH:i:s\Z"),
			'completed_at'              => (new Carbon($order->completed_date))->format("Y-m-d\TH:i:s\Z"),
			'status'                    => $order->get_status(),
			'currency'                  => $order->get_order_currency(),
			'total'                     => wc_format_decimal( $order->get_total(), $dp ),
			'subtotal'                  => wc_format_decimal( $order->get_subtotal(), $dp ),
			'total_line_items_quantity' => $order->get_item_count(),
			'total_tax'                 => wc_format_decimal( $order->get_total_tax(), $dp ),
			'total_shipping'            => wc_format_decimal( $order->get_total_shipping(), $dp ),
			'cart_tax'                  => wc_format_decimal( $order->get_cart_tax(), $dp ),
			'shipping_tax'              => wc_format_decimal( $order->get_shipping_tax(), $dp ),
			'total_discount'            => wc_format_decimal( $order->get_total_discount(), $dp ),
			'shipping_methods'          => $order->get_shipping_method(),
			'payment_details' => (object) array(
				'method_id'    => $order->payment_method,
				'method_title' => $order->payment_method_title,
				'paid'         => isset( $order->paid_date ),
			),
			'billing_address' => (object) array(
				'first_name' => $order->billing_first_name,
				'last_name'  => $order->billing_last_name,
				'company'    => $order->billing_company,
				'address_1'  => $order->billing_address_1,
				'address_2'  => $order->billing_address_2,
				'city'       => $order->billing_city,
				'state'      => $order->billing_state,
				'postcode'   => $order->billing_postcode,
				'country'    => $order->billing_country,
				'email'      => $order->billing_email,
				'phone'      => $order->billing_phone,
			),
			'shipping_address' => (object) array(
				'first_name' => $order->shipping_first_name,
				'last_name'  => $order->shipping_last_name,
				'company'    => $order->shipping_company,
				'address_1'  => $order->shipping_address_1,
				'address_2'  => $order->shipping_address_2,
				'city'       => $order->shipping_city,
				'state'      => $order->shipping_state,
				'postcode'   => $order->shipping_postcode,
				'country'    => $order->shipping_country,
			),
			'note'                      => $order->customer_note,
			'customer_ip'               => $order->customer_ip_address,
			'customer_user_agent'       => $order->customer_user_agent,
			'customer_id'               => $order->get_user_id(),
			'view_order_url'            => $order->get_view_order_url(),
			'line_items'                => array(),
			'shipping_lines'            => array(),
			'tax_lines'                 => array(),
			'fee_lines'                 => array(),
			'coupon_lines'              => array(),
		);

		// add line items
		foreach ( $order->get_items() as $item_id => $item ) {

			$product     = $order->get_product_from_item( $item );
			$product_id  = null;
			$product_sku = null;

			// Check if the product exists.
			if ( is_object( $product ) ) {
				$product_id  = ( isset( $product->variation_id ) ) ? $product->variation_id : $product->id;
				$product_sku = $product->get_sku();
			}

			$meta = new WC_Order_Item_Meta( $item, $product );

			$item_meta = array();

			$hideprefix = ( isset( $filter['all_item_meta'] ) && $filter['all_item_meta'] === 'true' ) ? null : '_';

			foreach ( $meta->get_formatted( $hideprefix ) as $meta_key => $formatted_meta ) {
				$item_meta[] = (object) array(
					'key'   => $formatted_meta['key'],
					'label' => $formatted_meta['label'],
					'value' => $formatted_meta['value'],
				);
			}

			$order_data['line_items'][] = (object) array(
				'id'           => $item_id,
				'subtotal'     => wc_format_decimal( $order->get_line_subtotal( $item, false, false ), $dp ),
				'subtotal_tax' => wc_format_decimal( $item['line_subtotal_tax'], $dp ),
				'total'        => wc_format_decimal( $order->get_line_total( $item, false, false ), $dp ),
				'total_tax'    => wc_format_decimal( $item['line_tax'], $dp ),
				'price'        => wc_format_decimal( $order->get_item_total( $item, false, false ), $dp ),
				'quantity'     => wc_stock_amount( $item['qty'] ),
				'tax_class'    => ( ! empty( $item['tax_class'] ) ) ? $item['tax_class'] : null,
				'name'         => $item['name'],
				'product_id'   => $product_id,
				'sku'          => $product_sku,
				'meta'         => $item_meta,
			);
		}

		// add shipping
		foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {

			$order_data['shipping_lines'][] = (object) array(
				'id'           => $shipping_item_id,
				'method_id'    => $shipping_item['method_id'],
				'method_title' => $shipping_item['name'],
				'total'        => wc_format_decimal( $shipping_item['cost'], $dp ),
			);
		}

		// add taxes
		foreach ( $order->get_tax_totals() as $tax_code => $tax ) {

			$order_data['tax_lines'][] = (object) array(
				'id'       => $tax->id,
				'rate_id'  => $tax->rate_id,
				'code'     => $tax_code,
				'title'    => $tax->label,
				'total'    => wc_format_decimal( $tax->amount, $dp ),
				'compound' => (bool) $tax->is_compound,
			);
		}

		// add fees
		foreach ( $order->get_fees() as $fee_item_id => $fee_item ) {

			$order_data['fee_lines'][] = (object) array(
				'id'        => $fee_item_id,
				'title'     => $fee_item['name'],
				'tax_class' => ( ! empty( $fee_item['tax_class'] ) ) ? $fee_item['tax_class'] : null,
				'total'     => wc_format_decimal( $order->get_line_total( $fee_item ), $dp ),
				'total_tax' => wc_format_decimal( $order->get_line_tax( $fee_item ), $dp ),
			);
		}

		// add coupons
		foreach ( $order->get_items( 'coupon' ) as $coupon_item_id => $coupon_item ) {

			$order_data['coupon_lines'][] = (object) array(
				'id'     => $coupon_item_id,
				'code'   => $coupon_item['name'],
				'amount' => wc_format_decimal( $coupon_item['discount_amount'], $dp ),
			);
		}

		/*foreach (['line_items', 'shipping_lines', 'tax_lines', 'fee_lines', 'coupon_lines'] as $prop) {
			$order_data->$prop = (object) $order_data->$prop;
		}
		*/
		return (object) $order_data;
	}
	public function get_order_notes($id) {
		try {
			return $this->api->order_notes->get($id)->order_notes;
		} catch (WC_API_Client_HTTP_Exception $e) {
			return $this->api->order_notes->get($id)->order_notes;
		}
	}
	public function get_subscription($id) {
		try {
			return $this->api->subscriptions->get($id)->subscription;
		} catch (WC_API_Client_HTTP_Exception $e) {
			return $this->api->subscriptions->get($id)->subscription;
		}
	}
	public function get_subscription_internal($subscription_id) {

		$subscription      = wcs_get_subscription( $subscription_id );
		$order_data        = (array) $this->get_order_internal( $subscription_id );
		$subscription_data = $order_data; //(array) $order_data['order'];

		// Not all order meta relates to a subscription (a subscription doesn't "complete")
		if ( isset( $subscription_data['completed_at'] ) ) {
			unset( $subscription_data['completed_at'] );
		}

		$subscription_data['billing_schedule'] = (object) array(
			'period'          => $subscription->billing_period,
			'interval'        => $subscription->billing_interval,
			'start_at'        => (new Carbon($subscription->start))->format("Y-m-d\TH:i:s\Z"),
			'trial_end_at'    => (new Carbon($subscription->trial_end))->format("Y-m-d\TH:i:s\Z"),
			'next_payment_at' => (new Carbon($subscription->next_payment))->format("Y-m-d\TH:i:s\Z"),
			'end_at'          => (new Carbon($subscription->end))->format("Y-m-d\TH:i:s\Z"),
		);

		if ( ! empty( $subscription->order ) ) {
			$subscription_data['parent_order_id'] = $subscription->order->id;
		} else {
			$subscription_data['parent_order_id'] = array();
		}
		return (object) $subscription_data;
	}
	public function get_customer_orders($id) {
		try {
			return $this->api->customers->get_orders($id)->orders;
		} catch (WC_API_Client_HTTP_Exception $e) {
			return $this->api->customers->get_orders($id)->orders;
		}
	}
	public function get_customer_orders_internal($id) {
		global $wpdb;
		$order_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id
						FROM $wpdb->posts AS posts
						LEFT JOIN {$wpdb->postmeta} AS meta on posts.ID = meta.post_id
						WHERE meta.meta_key = '_customer_user'
						AND   meta.meta_value = '%s'
						AND   posts.post_type = 'shop_order'
						AND   posts.post_status IN ( '" . implode( "','", array_keys( wc_get_order_statuses() ) ) . "' )
					", $id ) );
		$orders = array();
		foreach ( $order_ids as $order_id ) {
			$orders[] = $this->get_order_internal($order_id);
		}
		return $orders;
	}
	public function get_customer_subscriptions($id) { 
		try {
			return array_map(
				function ($s) {return $s->subscription;},
				$this->api->customers->get_subscriptions($id)->customer_subscriptions
			);
		} catch (WC_API_Client_HTTP_Exception $e) {
			return array_map(
				function ($s) {return $s->subscription;},
				$this->api->customers->get_subscriptions($id)->customer_subscriptions
			);
		}
	}
	public function get_customer_subscriptions_internal($id) { 
		global $wpdb;
		$sub_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id
						FROM $wpdb->posts AS posts
						LEFT JOIN {$wpdb->postmeta} AS meta on posts.ID = meta.post_id
						WHERE meta.meta_key = '_customer_user'
						AND   meta.meta_value = '%s'
						AND   posts.post_type = 'shop_subscription'
						AND   posts.post_status IN ( '" . implode( "','", array_keys( wcs_get_subscription_statuses() ) ) . "' )
					", $id ) );
		$subs = array();
		foreach ( $sub_ids as $sub_id ) {
			$subs[] = $this->get_subscription_internal($sub_id);
		}
		return $subs;
	}

	//
	// Write
	//

	public function create_order($order) {
		return $this->writeapi->orders->create($order);
	}
	public function update_order($order_id, $order) {
		return $this->writeapi->orders->update($order_id, $order);
	}
	public function update_subscription($subscription_id, $subscription) {
		return $this->writeapi->subscriptions->update($subscription_id, $subscription);
	}
	public function update_product($product_id, $product) {
		return $this->writeapi->products->update($product_id, $product);
	}

	//
	// Stateful functions (these rely on caching or other state data)
	//

	/**
	 * Returns order statuses (including any custom statuses define in the admin)
	 */
	public function get_order_statuses() { 
		try {
			if (empty($this->order_statuses)) {
				$this->order_statuses = (array) $this->api->orders->get_statuses()->order_statuses;
			}
		} catch (WC_API_Client_HTTP_Exception $e) {
			if (empty($this->order_statuses)) {
				$this->order_statuses = (array) $this->api->orders->get_statuses()->order_statuses;
			}
		}
		return $this->order_statuses;
	}

	public function get_attributes() { 
		try {
			return $this->api->product_attributes->get()->product_attributes;
		} catch (WC_API_Client_HTTP_Exception $e) {
			return $this->api->product_attributes->get()->product_attributes;
		}
		/*
		if (empty($this->attribute_slug_map)) {
			$attributes = $thi->api->product_attributes->get()->product_attributes;
		}
		return $this->attribute_slug_map;
		*/
	}

	/**
	 * Returns subscription statuses (including any custom statuses define in the admin)
	 */
	public function get_subscription_statuses() { 
		if (empty($this->subscription_statuses)) {
			try {
				$s = (array) $this->api->subscriptions->get_statuses()->subscription_statuses;
			} catch (WC_API_Client_HTTP_Exception $e) {
				$s = (array) $this->api->subscriptions->get_statuses()->subscription_statuses;
			}
			$this->subscription_statuses = array();
			foreach ($s as $key => $value) {
				$this->subscription_statuses[substr($key,3)] = $value;
			}
		}
		return $this->subscription_statuses;
	}

	/**
	 * Returns the given order objects into an array where the keys
	 * are statuses and the values are arrays of the orders which
	 * match that status.
	 *
	 * If a status did not have any matching orders, the array is
	 * empty.
	 *
	 * # EXAMPLE
	 *
	 * $orders = array(
	 * 	(object) array('id'=>1, 'status'=>'completed'),
	 * 	(object) array('id'=>2, 'status'=>'pending'),
	 * 	(object) array('id'=>3, 'status'=>'pending'),
	 * );
	 * orders_by_status($orders) -->
	 * array(
	 * 	'pending' => array(),
	 * 	'processing' => array(
	 *		(object) array('id'=>2, 'status'=>'processing'),
	 *		(object) array('id'=>3, 'status'=>'processing')
	 *	),
	 * 	'on-hold' => array(),
	 * 	'completed' => array(
	 *		(object) array('id'=>1, 'status'=>'completed')
	 * 	),
	 *	'cancelled' => array(),
	 *	'refunded' => array(),
	 *	'failed' => array()
	 * )
	 */
	public function arrange_orders_by_status($orders) {
		$orders_by_status = array();
		foreach ($this->get_order_statuses() as $short_name => $long_name) {
			$filtered = array_filter(
				$orders, 
				function ($o) use ($short_name) { 
					return $o->status == $short_name;
				}
			);
			reset($filtered);
			$orders_by_status[$short_name] = $filtered;
		}
		return $orders_by_status;
	}

	/**
	 * Returns the given subscription objects into an array where the keys
	 * are statuses and the values are arrays of the subscriptions which
	 * match that status.
	 *
	 * If a status did not have any matching subscriptions, the array is
	 * empty.
	 *
	 * # EXAMPLE
	 *
	 * $subscriptions = array(
	 * 	(object) array('id'=>1, 'status'=>'completed'),
	 * 	(object) array('id'=>2, 'status'=>'pending'),
	 * 	(object) array('id'=>3, 'status'=>'pending'),
	 * );
	 * subscriptions_by_status($subscriptions) -->
	 * array(
	 * 	'pending' => array(),
	 * 	'processing' => array(
	 *		(object) array('id'=>2, 'status'=>'processing'),
	 *		(object) array('id'=>3, 'status'=>'processing')
	 *	),
	 * 	'on-hold' => array(),
	 * 	'completed' => array(
	 *		(object) array('id'=>1, 'status'=>'completed')
	 * 	),
	 *	'cancelled' => array(),
	 *	'refunded' => array(),
	 *	'failed' => array()
	 * )
	 */
	public function arrange_subscriptions_by_status($subscriptions) {
		$subscriptions_by_status = array();
		foreach ($this->get_subscription_statuses() as $short_name => $long_name) {
			$filtered = array_filter(
				$subscriptions, 
				function ($s) use ($short_name) { 
					return $s->status == $short_name;
				}
			);
			reset($filtered);
			$subscriptions_by_status[$short_name] = $filtered;
		}
		return $subscriptions_by_status;
	}

	//
	// Static functions
	//

	/**
	 * Returns a sku formatted from its components.
	 *
	 * # OPTIONS
	 *
	 * <ship_month>
	 * : Month in which the box will ship. 
	 *
	 * <box_num>
	 * : The month sequence number of the challenge box user. First month
	 *   is 1, second month is 2, etc.
	 *
	 * <clothing_gender>
	 * : The customer's preferred clothing gender.
	 *
	 * <tshirt_size>
	 * : The customer's preferred t-shirt size.
	 * 
	 */
	public static function format_sku($ship_month, $box_num, $clothing_gender, $tshirt_size, $version = 'v1', $diet = false) {
		// Generate sku based on version
		switch ($version) {
			case 'v1':
				// Translate tshirt sizes
				switch ($tshirt_size) {
					case '2xl': $tshirt_size = 'xxl'; break;
					case '3xl': $tshirt_size = 'xxxl'; break;
					default: break;
				}	
				return implode('_', array(
					'cb', 
					'm' . $box_num,
					strtolower($clothing_gender),
					strtolower($tshirt_size),
				));
			case 'v2':
				// Translate tshirt sizes
				switch ($tshirt_size) {
					case 'xxl': $tshirt_size = '2xl'; break;
					case 'xxxl': $tshirt_size = '3xl'; break;
					default: break;
				}	
				$sku = implode('_', array(
					'b' . $ship_month->format('ym'),
					'm' . $box_num,
					strtolower($clothing_gender),
					strtolower($tshirt_size),
				));
				if ($diet) {
					$sku .= '_diet';
				}
				return $sku;
			case 'single-v2':
				// Translate tshirt sizes
				switch ($tshirt_size) {
					case 'xxl': $tshirt_size = '2xl'; break;
					case 'xxxl': $tshirt_size = '3xl'; break;
					default: break;
				}	
				return implode('_', array(
					'sbox',
					strtolower($clothing_gender)[0],
					strtolower($tshirt_size),
				));
			case 'v3':
				$sku = implode('_', array(
					'b' . $ship_month->format('ym'),
					'm' . $box_num,
				));
				return $sku;
			default:
				throw new Exception('Invalid sku version');
		}
	}

	/**
	 * Returns data parsed from a sku.
	 *
	 * @return object containing parsed data.
	 * @throws InvalidSku if sku does not belong to a box.
	 */
	public static function parse_box_sku($sku) {
		$exploded = explode('_', $sku);

		// #livefit
		if ('#livefit' == $exploded[0]) {
			return (object) array(
				'sku_version' => 'v0',
				'ship_raw' => NULL,
				'month' => NULL,
				'gender' => NULL,
				'size' => NULL,
				'plan' => NULL,
				'diet' => NULL,
				'is_box' => true,
				'is_sub' => true,
				'credits' => NULL,
				'debits' => 1,
				'credit_only_with_total' => true,
			);
		}

		// cb_m1_female_m
		if ('cb' == $exploded[0]) {
			switch (sizeof($exploded)) {
				case 4: 
					list($cb, $month_raw, $gender, $size) = $exploded;
					$plan = '1m';
					break;
				case 5: 
					list($cb, $month_raw, $gender, $size, $plan) = $exploded; break;
				default: 
					throw new InvalidSku($sku . ': wrong number of components');
			}
			if ('m' !== $month_raw[0])
					throw new InvalidSku($sku . ': month field incorrect');
			switch ($plan) {
				case '1m': $plan = 'Month to Month'; $credits = 1; break;
				case '3m': $plan = '3 Month'; $credits = 3;  break;
				case '12m': $plan = '12 Month'; $credits = 12;  break;
				default: throw new InvalidSku($sku . ': unknown plan');
			};
			return (object) array(
				'sku_version' => 'v1',
				'ship_raw' => NULL,
				'month' => (int) substr($month_raw, 1),
				'gender' => strtolower($gender),
				'size' => strtolower($size),
				'plan' => strtolower($plan),
				'is_box' => true,
				'is_sub' => true,
				'credits' => $credits,
				'debits' => 1,
				'credit_only_with_total' => true,
			);
		}

		// sbox, sbox_f_m, etc. 
		if ('sbox' == $exploded[0]) {
			switch (sizeof($exploded)) {
				case 1: 
					return (object) array(
						'sku_version' => 'single-v1',
						'ship_raw' => NULL,
						'gender' => NULL,
						'size' => NULL,
						'plan' => NULL,
						'diet' => NULL,
						'credits' => 1,
						'debits' => 1,
						'is_box' => true,
						'is_sub' => true,
						'credit_only_with_total' => true,
					);
				case 3: 
					list($sbox, $gender, $size) = $exploded;
					$diet = 'no_restrictions';
					break;
				case 4: 
					list($cb, $gender, $size, $diet) = $exploded; break;
				default: 
					throw new InvalidSku($sku . ': wrong number of components');
			}
			return (object) array(
				'sku_version' => 'single-v2',
				'ship_raw' => NULL,
				'month' => NULL,
				'gender' => strtolower($gender),
				'size' => strtolower($size),
				'plan' => NULL,
				'diet' => strtolower($diet),
				'is_box' => true,
				'is_sub' => true,
				'credits' => 1,
				'debits' => 1,
				'credit_only_with_total' => true,
			);
		}

		// b1608_m1_f_m, b1608_m1, etc.
		if ('b' === $exploded[0][0]) {
			switch (sizeof($exploded)) {
				case 2: 
					list($ship_month, $box_raw) = $exploded;
					$version = 'v3';
					$gender = false; $size = false; $diet = false;
					break;
				case 4: 
					list($ship_month, $box_raw, $gender, $size) = $exploded;
					$version = 'v2';
					$diet = false;
					break;
				case 5: 
					list($ship_month, $box_raw, $gender, $size, $diet) = $exploded;
					$version = 'v2';
					$diet = true;
					break;
				default: 
					throw new InvalidSku($sku . ': wrong number of components');
			}
			if ('m' !== $box_raw[0])
					throw new InvalidSku($sku . ': month field incorrect');
			return (object) array(
				'sku_version' => $version,
				'ship_raw' => $ship_month,
				'box_number' => (int) substr($box_raw, 1),
				'month' => (int) substr($box_raw, 1),
				'gender' => $gender ? strtolower($gender) : NULL,
				'size' => $size ? strtolower($size) : NULL,
				'plan' => NULL,
				'diet' => $diet,
				'is_box' => true,
				'is_sub' => false,
				'credits' => 0,
				'debits' => 1,
				'credit_only_with_total' => false,
			);
		}

		// Subscription skus
		if ('subscription' == $exploded[0]) {
			switch (sizeof($exploded)) {
				case 2: list($subscription, $plan) = $exploded; break;
				case 3: list($subscription, $plan, $singlebox) = $exploded; break;
				default: throw new InvalidSku($sku . ': wrong number of components');
			}
			switch ($plan) {
				case 'single': 
				case 'single-v2': 
					$plan = 'Single Box'; $credits = 1; break;
				case 'monthly':
				case 'monthly-v2':
					$plan = 'Month to Month'; $credits = 1; break;
				case '3month':
				case '3month-v2':
					$plan = '3 Month'; $credits = 3; break;
				case '12month':
				case '12month-v2':
					$plan = '12 Month'; $credits = 12; break;
				default: throw new InvalidSku($sku . ': unexpected plan type');
			}
			return (object) array(
				'sku_version' => 'subscription-v2',
				'ship_raw' => NULL,
				'month' => NULL,
				'gender' => NULL,
				'size' => NULL,
				'plan' => $plan,
				'diet' => NULL,
				'is_box' => false,
				'is_sub' => true,
				'credits' => $credits,
				'debits' => 0,
				'credit_only_with_total' => true,
			);
		}

		return (object) array(
			'sku_version' => NULL,
			'ship_raw' => NULL,
			'month' => NULL,
			'gender' => NULL,
			'size' => NULL,
			'plan' => NULL,
			'is_box' => false,
			'is_sub' => false,
			'credits' => 0,
			'debits' => 0,
			'credit_only_with_total' => false,
		);
	}

	/**
	 * Given any object with a "line_items" property, returns an associative array
	 * containing only month, gender, size, plan and date.
	 * Returns false if there are none found.
	 */
	public static function parse_order_options($thing_with_line_items) {
		foreach ($thing_with_line_items->line_items as $line_item) {
			$item = array(
				'month' => NULL,
				'gender' => NULL,
				'size' => NULL,
				'plan' => NULL,
				'sku_version' => NULL,
			);

			// Gather data from the sku
			if (!empty($line_item->sku)) {
				try {
					$sku = CBWoo::parse_box_sku($line_item->sku);
					$item['gender'] = $sku->gender;
					$item['size'] = $sku->size;
					$item['month'] = $sku->month;
					$item['plan'] = $sku->plan;
					$item['sku_version'] = $sku->sku_version;
				} catch (Exception $ex) {
				}
			}

			// Gather data from the meta
			if (!empty($line_item->meta)) {
				foreach ($line_item->meta as $m) {
					if ($m->key == 'pa_gender') $item['gender'] = strtolower($m->value);
					if ($m->key == 'pa_size') $item['size'] = strtolower($m->value);
				}
			}

			// If we get any data out, return it
			if (CB::any($item)) return (object) $item;
		}
		return false;
	}

	/**
	 * Returns true if the order is for a box (as determined by the sku or metadata).
	 */
	public static function is_valid_box_order($order) {
		// Order is a box if it has available gender and size options.
		$parsed = CBWoo::parse_order_options($order);
		return !empty($parsed->gender) && !empty($parsed->size);
	}
	public static function is_valid_single_box_order($order) {
		$parsed = CBWoo::parse_order_options($order);
		return $parsed && $parsed->sku_version && ($parsed->sku_version === 'single-v2' || $parsed->sku_version === 'single-v1');
	}
	public static function is_subscription_order($order) {
		foreach ($order->line_items as $line_item) {
			if ($line_item->sku && 0 === strpos($line_item->sku, 'subscription_')) {
				return true;
			}
		}
	}
	public static function is_mrr_subscription($sub) {
		return (bool) in_array(
			CBWoo::extract_subscription_type($sub),
			array('3 Month', '12 Month', 'Month to Month')
		);
	}

	/**
	 * Returns true if the order is marked as rush.
	 */
	public static function order_is_rush($order) {
		return (bool) CB::any(
			array_filter($order->fee_lines, function ($line) {
				return 'Rush My Box' == $line->title;
			})
		);
	}

	public static function order_counts_as_box_credit($order) {
		foreach ($order->line_items as $line_item) {
			if (!empty($line_item->sku)) {
				if (CBWoo::parse_box_sku($line_item->sku)->is_sub) {
					return true;
				}
			}
		}
	}
	public static function order_counts_as_box_debit($order) {
		foreach ($order->line_items as $line_item) {
			if (!empty($line_item->sku)) {
				if (CBWoo::parse_box_sku($line_item->sku)->is_box) {
					return true;
				}
			}
		}
	}

	/**
	 * Returns the first order sku found in a given order object line item.
	 */
	public static function extract_order_sku($order) {
		foreach ($order->line_items as $line_item) {
			if (!empty($line_item->sku)) {
				return $line_item->sku;
			}
		}
	}

	/**
	 * Returns all skus found in an order as an array.
	 */
	public static function extract_order_skus($order) {
		$skus = array();
		foreach ($order->line_items as $line_item) {
			if (!empty($line_item->sku)) {
				$skus[] = $line_item->sku;
			}
		}
		return $skus;
	}

	/**
	 * Returns the first subscription name found in a subscription object line item.
	 */
	public static function extract_subscription_name($sub) {
		foreach ($sub->line_items as $line_item) {
			if (!empty($line_item->name)) {
				return $line_item->name;
			}
		}
	}

	/**
	 * Reforats an attribute array as array(attribute_slug => term_slug, ...)
	 */
	public static function extract_attributes($attributes) {
		$result = array();
		foreach ($attributes as $att) {
			$result[$att->slug] = $att->option;
		}
		return $result;
	}

	/**
	 * Returns '3 Month', '12 Month', 'Month to Month', or false.
	 */
	public static function extract_subscription_type($sub) {
		$name = strtolower(CBWoo::extract_subscription_name($sub));
		if ( strpos($name, '3 month') !== false ) {
			return '3 Month';
		} elseif ( strpos($name, '12 month') !== false ) {
			return '12 Month';
		} elseif ( strpos($name, 'month to month') !== false ) {
			return 'Month to Month';
		}
	}

	/**
	 * Parses a WooCommerce REST API date into a php DateTime object.
	 */
	public static function parse_date_from_api($date_string) {
		return DateTime::createFromFormat('Y-m-d?H:i:s?', $date_string);
	}
	/**
	 * Formats a DateTime object so the WooCommerce REST API will accept it.
	 */
	public static function format_DateTime_for_api($dateTime) {
		return $dateTime->format('Y-m-d H:i:s');
	}

	/**
	 * Try and guess which month a thing shipped. If we know, just return that.
	 */
	public static function guess_ship_month($order) {
			$created_at = CBWoo::parse_date_from_api($order->created_at);
			$completed_at = CBWoo::parse_date_from_api($order->completed_at);
			$sku = CBWoo::extract_order_sku($order);
			$parsed_sku = CBWoo::parse_box_sku($sku);
			$ship_month = $parsed_sku->ship_raw;
			if ($ship_month) {
				return $ship_month;
			}
			try {
				$guess_date = Carbon::createFromFormat('\bym', $ship_month);
			} catch (InvalidArgumentException $e) {
				$guess_date = Carbon::instance($completed_at)->copy();
				if (empty($guess_date)) {
					$guess_date = Carbon::instance($created_at)->copy();
				}
			}
			if ($guess_date->day > 20) {
				$guess_date = $guess_date->startOfMonth()->addMonth();
			} else {
				$guess_date = $guess_date->startOfMonth();
			}
			$ship_month_guess = $guess_date->format('\bym');
			return $ship_month_guess;
	}


	/**
	 * Given an order object, calculate how many box credits that order gives.
	 */
	public static function calculate_box_credit($order, $verbose = false) {

		$totals = array('credits' => 0, 'revenue' => 0);
		
		foreach ($order->line_items as $line) {
			if (!isset($line->sku)) continue; 
			$parsed = CBWoo::parse_box_sku($line->sku);
			if ($parsed->credits > 0) {
				if ($parsed->credit_only_with_total) {
					if ($order->subtotal > 0) {
						$totals['credits'] += $parsed->credits;
						$totals['revenue'] += $order->total;
						if ($verbose) WP_CLI::debug("\t".'sku ' . $line->sku . ' credit ' . $parsed->credits . ' amount ' . $order->total);
					} else {
						if ($verbose) WP_CLI::debug("\t".'sku ' . $line->sku . ' credit ' . 0 . ' amount ' . $order->total);
					}
				} else {
					$totals['credits'] += $parsed->credits;
					$totals['revenue'] += $order->total;
					if ($verbose) WP_CLI::debug("\t".'sku ' . $line->sku . ' credit ' . $parsed->credits . ' amount ' . $order->total);
				}
			} elseif (0 === $parsed->credits) {
				if ($verbose) WP_CLI::debug("\t".'sku ' . $line->sku . ' credit ' . $parsed->credits . ' amount ' . $order->total);
			} else {
				// We need to calculate credits in another way
				if ($order->total > 100) {
					$totals['credits'] += 12;
					$totals['revenue'] += $order->total;
					if ($verbose) WP_CLI::debug("\t".'sku ' . $line->sku . ' credit 12 amount ' . $order->total);
				} elseif ($order->total > 50) {
					$totals['credits'] += 3;
					$totals['revenue'] += $order->total;
					if ($verbose) WP_CLI::debug("\t".'sku ' . $line->sku . ' credit 3 amount ' . $order->total);
				} else {
					$totals['credits'] += 1;
					$totals['revenue'] += $order->total;
					if ($verbose) WP_CLI::debug("\t".'sku ' . $line->sku . ' credit 1 amount ' . $order->total);
				}
			}
		}

		return $totals;
	}

	public static function get_order_predictions() {
		global $wpdb;
		$data = $wpdb->get_results("
			select 
				count(meta_value) as count, meta_value as name
			from $wpdb->usermeta
			where
				meta_key = 'next_box_m'
			group by
				name
			order by
				name
			;
		");
		$box_total = 0;
		$other_total = 0;
		foreach ($data as $row) {
			if (preg_match('/^m\d/', $row->name)) {
				$box_total += intval($row->count);
			} else {
				$other_total += intval($row->count);
			}
		}
		$data[] = (object) array('count' => $box_total, 'name' => 'total');
		$data[] = (object) array('count' => $other_total, 'name' => 'other_total');
		return $data;
	}

	public static function get_revenue_data() {
		global $wpdb;

		$data = array();

		$standard_sums = "
				, sum(total) as `revenue`
				, sum(revenue_items) as `box rev`
				, sum(revenue_ship) as `ship rev`
				, sum(revenue_rush) as `rush rev`
				, sum(refund > 0) as `refunds`
				, 100 * sum(refund) / sum(total) as `refund %`
				, sum(refund) as `refund amt`
				, sum(total) - sum(refund) as `net rev`
		";

		$per_box_sums = "
				, (sum(total) - sum(refund)) / sum(1) as `rev/box`
		";

		$lag_lead_setup = "SET @lag_user_id=NULL; SET @lag_box_count=NULL;";

		$ship_month_churn_base = "
			select
				user_id,
				lag_user_id,
				ship_month,
				box_count,
				round(lag_box_count) as lag_box_count,
				case
					when lag_user_id <> user_id then NULL
					else case
						when lag_box_count is not NULL and box_count is NULL then \"churned\"
						when lag_box_count is NULL and box_count is not NULL then \"activated\"
						when lag_box_count is NULL and box_count is NULL then NULL
						else \"active\"
					end
				end as state,
				case
					when lag_user_id <> user_id then 0
					else case
						when lag_box_count is NULL and box_count is not NULL then 1
						else 0
					end
				end as activated,
				case
					when lag_user_id <> user_id then 0
					else case
						when lag_box_count is not NULL and box_count is NULL then 0
						when lag_box_count is NULL and box_count is not NULL then 1
						when lag_box_count is NULL and box_count is NULL then 0
						else 1
					end
				end as active,
				case
					when lag_user_id <> user_id then 0
					else case
						when lag_box_count is not NULL and box_count is NULL then 1
						else 0
					end
				end as churned,
				total,
				revenue_items,
				revenue_ship,
				revenue_rush,
				refund
			from
			(
				select
					@lag_user_id lag_user_id,
					@lag_user_id:=user_id user_id,
					ship_month,
					total,
					revenue_items,
					revenue_ship,
					revenue_rush,
					refund,
					@lag_box_count lag_box_count,
					@lag_box_count:=box_count box_count
				from
				(
					select
						id_date_combinations.user_id,
						id_date_combinations.ship_month,
						sum(events.total) as total,
						sum(events.revenue_items) as revenue_items,
						sum(events.revenue_ship) as revenue_ship,
						sum(events.revenue_rush) as revenue_rush,
						sum(events.refund) as refund,
						sum(events.status in (\"completed\", \"refunded\")) as box_count
					from
						cb_box_orders as events
					right join
						(
							select id, user_id, date_format(month, \"b%y%m\") as ship_month from cb_box_orders, cb_months where month between \"2016-02-01\" and now() group by id, user_id, month order by user_id, month
						) as id_date_combinations
					on
							events.id = id_date_combinations.id
						and events.user_id = id_date_combinations.user_id 
						and events.ship_month = id_date_combinations.ship_month
					group by
						user_id, ship_month
					order by
						user_id, ship_month
				) as group_table
				order by
					user_id, ship_month
			) as lag_lead_table
		";

		$calendar_month_churn_base = "
			select
				user_id,
				lag_user_id,
				month,
				box_count,
				round(lag_box_count) as lag_box_count,
				case
					when lag_user_id <> user_id then NULL
					else case
						when lag_box_count is not NULL and box_count is NULL then \"churned\"
						when lag_box_count is NULL and box_count is not NULL then \"activated\"
						when lag_box_count is NULL and box_count is NULL then NULL
						else \"active\"
					end
				end as state,
				case
					when lag_user_id <> user_id then 0
					else case
						when lag_box_count is NULL and box_count is not NULL then 1
						else 0
					end
				end as activated,
				case
					when lag_user_id <> user_id then 0
					else case
						when lag_box_count is not NULL and box_count is NULL then 0
						when lag_box_count is NULL and box_count is not NULL then 1
						when lag_box_count is NULL and box_count is NULL then 0
						else 1
					end
				end as active,
				case
					when lag_user_id <> user_id then 0
					else case
						when lag_box_count is not NULL and box_count is NULL then 1
						else 0
					end
				end as churned,
				total,
				revenue_items,
				revenue_ship,
				revenue_rush,
				refund
			from
			(
				select
					@lag_user_id lag_user_id,
					@lag_user_id:=user_id user_id,
					month,
					total,
					revenue_items,
					revenue_ship,
					revenue_rush,
					refund,
					@lag_box_count lag_box_count,
					@lag_box_count:=box_count box_count
				from
				(
					select
						id_date_combinations.user_id,
						id_date_combinations.month,
						sum(events.total) as total,
						sum(events.revenue_items) as revenue_items,
						sum(events.revenue_ship) as revenue_ship,
						sum(events.revenue_rush) as revenue_rush,
						sum(events.refund) as refund,
						sum(events.status in (\"completed\", \"refunded\")) as box_count
					from
						cb_box_orders as events
					right join
						(
							select id, user_id, month from cb_box_orders, cb_months where month between \"2016-02-01\" and now() group by id, user_id, month order by user_id, month
						) as id_date_combinations
					on
							events.id = id_date_combinations.id
						and events.user_id = id_date_combinations.user_id 
						and date_format(events.completed_date, \"%Y-%m\") = date_format(id_date_combinations.month, \"%Y-%m\")
					group by
						user_id, month
					order by
						user_id, month
				) as group_table
				order by
					user_id, month
			) as lag_lead_table
		";

			$data[] = (object) array(
				'title' => "by calendar month",
				'data' => $wpdb->get_results($q = <<<SQL
          select
              a.calendar_month as `calendar month`
            , `boxes shipped` as `boxes ship`
            , `renewals shipped` as `renewals`
            , `shop shipped` as `shop orders`
            , `box revenue` as `box rev`
            , `renewal revenue` as `sub rev`
            , `shop revenue` as `shop rev`
            , activated as `users +`
            , if(date_format(now(), '%Y-%m') = a.calendar_month, '*', `box_churned`) as `users -`
            , if(date_format(now(), '%Y-%m') = a.calendar_month, '*', 100 * `box_churned` / `boxes shipped`) as `churn %`

          from
            cb_calendar_month_boxes as a
          join
            cb_calendar_month_renewals as b
          on
            a.calendar_month = b.calendar_month
		  join
          	cb_calendar_month_shop as c
          on
          	a.calendar_month = c.calendar_month
          ;
SQL
			),
			'query' => $q,
		);

		//$wpdb->query($lag_lead_setup);
/*
		$data[] = (object) array(
			'title' => 'churn by sku month',
			'data' => $results = $wpdb->get_results($q = "
				select
					  ship_month as `ship month`
					, sum(box_count) as `boxes shipped`
					, sum(activated) as `added`
					, sum(active) as `active`
					, sum(churned) as `churned`
				from
					($ship_month_churn_base) as churn_base
				group by
					ship_month
				order by
					ship_month
				; 
			"),
			'query' => $q
		);
		//var_dump($q);
		//var_dump($wpdb->last_error);
		//var_dump($results);

		$data[] = (object) array(
			'title' => 'churn by calendar month',
			'data' => $results = $wpdb->get_results($q = "
				select
					  month as `calendar month`
					, sum(box_count) as `boxes shipped`
					, sum(activated) as `added`
					, sum(active) as `active`
					, sum(churned) as `churned`
					, 100 * sum(churned) / sum(active) as `percent churn`
				from
					($calendar_month_churn_base) as churn_base
				group by
					month
				order by
					month
				; 
			"),
		);
*/
		
		$data[] = (object) array(
			'title' => 'box detail by sku month',
			'data' => $wpdb->get_results("
				select
					  ship_month as `sku month`
					, sum(1) as `boxes shipped`
					$standard_sums
					$per_box_sums
				from
					cb_box_orders
				where
					status in (\"completed\", \"refunded\")
				group by
					ship_month
				order by
					ship_month
				; 
			"),
		);

		$data[] = (object) array(
			'title' => 'box detail by calendar month',
			'data' => $wpdb->get_results("
				select
					  date_format(completed_date, '%Y-%m') as `calendar month`
					, count(1) as `boxes shipped`
					$standard_sums
					$per_box_sums
				from
					cb_box_orders
				where
					status in (\"completed\", \"refunded\")
				group by
					`calendar month`
				order by
					`calendar month`
				; 
			"),
		);

		$data[] = (object) array(
			'title' => 'box detail by box month',
			'data' => $wpdb->get_results("
				select
					  box_month as `box month`
					, sum(1) as `boxes shipped`
					$standard_sums
					$per_box_sums
				from
					cb_box_orders
				where
					status in (\"completed\", \"refunded\")
				group by
					box_month
				order by
					box_month
				; 
			"),
		);

		$data[] = (object) array(
			'title' => 'box detail by price point',
			'data' => $wpdb->get_results("
				select
					  5*round(total/5) as `price point`
					, sum(1) as `boxes shipped`
					$standard_sums
					$per_box_sums
				from
					cb_box_orders
				where
					status in (\"completed\", \"refunded\")
				group by
					`price point`
				order by
					`price point`
				; 
			"),
		);


		$data[] = (object) array(
			'title' => 'subscription detail by month',
			'data' => $wpdb->get_results("
				select
					  date_format(completed_date, '%Y-%m') as `calendar month`
					, count(1) as `renewals`
					$standard_sums
					, (sum(total) - sum(refund)) / sum(1) as `rev/renewal`
				from
					(select * from cb_renewals order by user_id, created_date) as t
				where
					status in (\"completed\", \"refunded\")
				group by
					`calendar month`
				order by
					`calendar month`
				; 
			"),
		);

		$data[] = (object) array(
			'title' => 'shop detail by month',
			'data' => $wpdb->get_results("
				select
					  date_format(completed_date, '%Y-%m') as `calendar month`
					, sum(1) as `orders`
					$standard_sums
					, (sum(total) - sum(refund)) / sum(1) as `rev/order`
				from
					cb_shop_orders
				where
					status in (\"completed\", \"refunded\")
				group by
					`calendar month`
				order by
					`calendar month`
				; 
			"),
		);

		// Cohorts

		$cohort_data = $wpdb->get_results("
				select
					  ship_month as `ship month`
					, box_month as `box month`
					, count(1) as `boxes shipped`
					$standard_sums
					$per_box_sums
				from
					cb_box_orders
				where
					status in (\"completed\", \"refunded\")
				group by
					ship_month, box_month
				order by
					ship_month, box_month
				; 
		", ARRAY_A);

		$cohort_column  = 'ship month';
		$cohort_row     = 'box month';
		$cohort_stats   = array('boxes shipped', 'revenue');
		$cohort_columns = array_values(array_unique(array_column($cohort_data, $cohort_column), SORT_REGULAR));
		$cohort_rows    = array_values(array_unique(array_column($cohort_data, $cohort_row), SORT_REGULAR));

		// Make a separate cohort table for each stat
		foreach ($cohort_stats as $stat) {
			$rows = array();
			foreach ($cohort_rows as $row_name) {
				$row = array($cohort_row => $row_name);
				foreach ($cohort_columns as $column_name) {
					$row[$column_name] = array_sum(array_map(function ($data_row) use ($stat, $cohort_column, $column_name, $cohort_row, $row_name) {
						if ($data_row[$cohort_column] === $column_name && $data_row[$cohort_row] === $row_name) {
							return $data_row[$stat];
						} else {
							return 0;
						}
					}, $cohort_data));
				}
				$rows[] = (object) $row;
			}
			$data[] = (object) array('title' => "$stat cohorts", 'data' => $rows);
		}

		//$data[] = (object) array('title' => 'cohort data', 'data' => $cohort_data);

		return $data;
	}

	/**
	 * Grab churn data from the database and returns an object with two properties:
	 *
	 *	'colums'             => an array of column names, in order that they first appeared. 
	 *	'data'               => an array of rows of churn data keyed by user id.
	 *	'monthly_data_types' => an array of column prefixes for columns that contain data
	 *                          pertaining to a given month
	 *	'months'             => an array of sorted month strings ('2016-12') for each month encountered
	 *	'cohorts'            => an array of cohort strings ('2016-12') for all cohorts encountered
	 *	'mrr_cohorts'        => an array of cohort strings ('2016-12') for all cohorts encountered where
	 *                          cohort is counted from first appearence of monthly recurring revenue
	 *                          rather than signup
	 *
	 * These data need to be regularly  updated by the console command `calculate_churn`.
	 */
	public static function get_churn_data() {
		global $wpdb;
		$data = $wpdb->get_results("
			select 
				id, meta_key, meta_value
			from $wpdb->users U 
			join $wpdb->usermeta A 
				on A.user_id = U.id
			where
				   meta_key LIKE 'mrr_%' 
				or meta_key LIKE 'revenue_%'
				or meta_key = 'cohort'
			order by
				user_registered
			;
		");

		// Column prefixes that can be followed by a month
		$monthly_data_types = array(
			'mrr',
			'revenue',
			'revenue_sub',
			'revenue_shop',
			'revenue_single',
		);

		// Pivot the data so it is organized in rows keyed by the user id
		$user_data = array();
		$columns = array('id' => true);
		$cohorts = array();
		$mrr_cohorts = array();
		foreach ($data as $row) {
			// Add datum to user row, creating row if needed
			if (!isset($user_data[$row->id])) $user_data[$row->id] = array();
			$user_data[$row->id][$row->meta_key] = $row->meta_value;

			// Count unique columns
			if (!isset($columns[$row->meta_key])) $columns[$row->meta_key] = true;

			// Count unique cohorts
			if ($row->meta_key == 'cohort') {
				if (!isset($cohorts[$row->meta_value])) $cohorts[$row->meta_value] = true;
			}
			if ($row->meta_key == 'mrr_cohort') {
				if (!isset($mrr_cohorts[$row->meta_value])) $mrr_cohorts[$row->meta_value] = true;
			}
		}

		// Count unique months
		$months = array();
		foreach (array_keys($columns) as $column) {
			$exploded = explode('_', $column);
			$maybe_month = end($exploded);
			if (preg_match('/^\d{4}-\d{2}$/', $maybe_month)) {
				if (!isset($months[$maybe_month])) $months[$maybe_month] = true;
			}
		}

		// Make sure dates are nice and sorted
		ksort($months);
		ksort($cohorts);
		ksort($mrr_cohorts);

		//
		// Add in churn data
		//
		$monthly_data_types = array_merge(
			$monthly_data_types, array(
				'activated',
				'active',
				'churned',
		));
		foreach ($user_data as $user_id => $row) {
			// Walk through months and create entry with a 1 for the month where user activated
			// i.e. went from 0 mrr to some mrr.

			$previous_month = null;
			$old_cohort = $row['cohort'];
			if (isset($user_data[$user_id]['cohort'])) $user_data[$user_id]['cohort'] = null;

			foreach ($months as $month => $true) {

				$activated_key = 'activated_' . $month;
				$active_key = 'active_' . $month;
				$churned_key = 'churned_' . $month;

				$mrr_this = 'mrr_' . $month;
				$mrr_last = 'mrr_' . $previous_month;

				// Active if any mrr this month
				if (isset($row[$mrr_this]) && $row[$mrr_this]) {
					$user_data[$user_id][$active_key] = 1;
					if (!isset($columns[$active_key])) $columns[$active_key] = true; // add column if dne

					// Activated if previous month had no mrr
					if (
							!$previous_month                                 // is very first month
							|| (isset($row[$mrr_last]) && !$row[$mrr_last])  // last month was zero
							|| !isset($row[$mrr_last])                       // last month not set
					) {
						$user_data[$user_id][$activated_key] = 1;
						if (!isset($columns[$activated_key])) $columns[$activated_key] = true; // add column if dne

						// Also reset cohort to month they activated
						if (!isset($user_data[$user_id]['cohort'])) $user_data[$user_id]['cohort'] = $month;
					}
				}

				// Churned if no mrr this month but yes mrr last month
				if (
					$previous_month 
					&&
					(
						(isset($row[$mrr_this]) && !$row[$mrr_this])      // this month entry zero
						|| !isset($row[$mrr_this])                        // no entry this month
					)                                                   // no mrr this month
					&&
					(isset($row[$mrr_last]) && $row[$mrr_last])         // mrr last month
				) {
					$user_data[$user_id][$churned_key] = 1;
					if (!isset($columns[$churned_key])) $columns[$churned_key] = true;
				}

				$previous_month = $month;
			}
			// Also reset cohort if we didn't set it
			if (!isset($user_data[$user_id]['cohort'])) {
				$user_data[$user_id]['cohort'] = $old_cohort;
			}
		}

		// Make sure columns are sorted too
		ksort($columns);

		return (object) array(
			'columns' => array_keys($columns),
			'data' => $user_data,
			'monthly_data_types' => $monthly_data_types,
			'months' => array_keys($months),
			'cohorts' => array_keys($cohorts),
			'mrr_cohorts' => array_keys($mrr_cohorts),
		);		

	}

	//
	// Rollups of churn
	//
	public static function get_churn_rollups($churn_data) {
		$rollups = array();
		foreach ($churn_data->monthly_data_types as $dt) {
			$rollups[$dt] = array();
			foreach (array_merge($churn_data->cohorts, array('total')) as $cohort) {
				$rollups[$dt][$cohort] = array();
				foreach ($churn_data->months as $month) {
					$column = $dt . '_' . $month;
					$rollups[$dt][$cohort]['cohort'] = $cohort;
					foreach ($churn_data->data as $user_id => $row) {
						// Only count data if user is in the cohort we're looking at
						if ('total' == $cohort || (isset($row['cohort']) && $cohort == $row['cohort'])) {
							// Create value if missing
							if (!isset($rollups[$dt][$cohort][$month])) $rollups[$dt][$cohort][$month] = 0;
							// Sum if not
							$rollups[$dt][$cohort][$month] += isset($row[$column]) ? $row[$column] : 0;
						}
					}
				}
			}
		}
		return $rollups;
	}
}

class InvalidSku extends Exception {};

