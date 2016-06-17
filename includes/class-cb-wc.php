<?php

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
		return $this->api->products->get($id)->product;
	}
	public function get_product_by_sku($id) {
		return $this->api->products->get_by_sku($id)->product;
	}
	public function get_customer($id) {
		return $this->api->customers->get($id)->customer;
	}
	public function get_order($id) {
		return $this->api->orders->get($id)->order;
	}
	public function get_order_notes($id) {
		return $this->api->order_notes->get($id)->order_notes;
	}
	public function get_subscription($id) {
		return $this->api->subscriptions->get($id)->subscription;
	}
	public function get_customer_orders($id) {
		return $this->api->customers->get_orders($id)->orders;
	}
	public function get_customer_subscriptions($id) { 
		return array_map(
			function ($s) {return $s->subscription;},
			$this->api->customers->get_subscriptions($id)->customer_subscriptions
		);
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
		if (empty($this->order_statuses)) {
			$this->order_statuses = (array) $this->api->orders->get_statuses()->order_statuses;
		}
		return $this->order_statuses;
	}

	public function get_attributes() { 
		return $this->api->product_attributes->get()->product_attributes;
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
			$s = (array) $this->api->subscriptions->get_statuses()->subscription_statuses;
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

		if ('#livefit' == $exploded[0]) {
			return (object) array(
				'sku_version' => 'v0',
				'month' => NULL,
				'gender' => NULL,
				'size' => NULL,
				'plan' => NULL,
				'diet' => NULL,
				'is_box' => true,
			);
		}

		// Old version sku
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
			return (object) array(
				'sku_version' => 'v1',
				'month' => (int) substr($month_raw, 1),
				'gender' => strtolower($gender),
				'size' => strtolower($size),
				'plan' => strtolower($plan),
				'is_box' => true,
			);
		}

		// New version single box sku
		if ('sbox' == $exploded[0]) {
			switch (sizeof($exploded)) {
				case 1: 
					return (object) array(
						'sku_version' => 'single-v2',
						'month' => NULL,
						'gender' => NULL,
						'size' => NULL,
						'plan' => NULL,
						'diet' => NULL,
						'is_box' => true,
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
				'month' => NULL,
				'gender' => strtolower($gender),
				'size' => strtolower($size),
				'plan' => NULL,
				'diet' => strtolower($diet),
				'is_box' => true,
			);
		}

		// New version sku
		if ('b' === $exploded[0][0]) {
			switch (sizeof($exploded)) {
				case 4: 
					list($ship_month, $box_raw, $gender, $size) = $exploded;
					$diet = false;
					break;
				case 5: 
					list($ship_month, $box_raw, $gender, $size, $diet) = $exploded;
					$diet = true;
					break;
				default: 
					throw new InvalidSku($sku . ': wrong number of components');
			}
			if ('m' !== $box_raw[0])
					throw new InvalidSku($sku . ': month field incorrect');
			return (object) array(
				'sku_version' => 'v2',
				'ship_raw' => $ship_month,
				'box_number' => (int) substr($box_raw, 1),
				'month' => (int) substr($box_raw, 1),
				'gender' => strtolower($gender),
				'size' => strtolower($size),
				'plan' => NULL,
				'diet' => $diet,
				'is_box' => true,
			);
		}

		return (object) array(
			'sku_version' => NULL,
			'month' => NULL,
			'gender' => NULL,
			'size' => NULL,
			'plan' => NULL,
			'is_box' => false,
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
		return $parsed && $parsed->sku_version && $parsed->sku_version === 'single-v2';
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
}

class InvalidSku extends Exception {};

