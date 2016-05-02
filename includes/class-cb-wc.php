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

	//
	// Static functions
	//

	/**
	 * Returns a sku formatted from its components.
	 *
	 * # OPTIONS
	 *
	 * <month>
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
	public static function format_sku($month, $clothing_gender, $tshirt_size, $version = 'v1') {
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
					'm' . $month,
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
				return implode('_', array(
					'm' . $month,
					strtolower($clothing_gender),
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
			);
		}

		// New version sku
		switch (sizeof($exploded)) {
			case 3: 
				list($month_raw, $gender, $size) = $exploded; break;
			default: 
				throw new InvalidSku($sku . ': wrong number of components');
		}
		if ('m' !== $month_raw[0])
				throw new InvalidSku($sku . ': month field incorrect');
		return (object) array(
			'sku_version' => 'v2',
			'month' => (int) substr($month_raw, 1),
			'gender' => strtolower($gender),
			'size' => strtolower($size),
			'plan' => NULL,
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

	public static function extract_order_sku($order) {
		foreach ($order->line_items as $line_item) {
			if (!empty($line_item->sku)) {
				return $line_item->sku;
			}
		}
	}
}

class InvalidSku extends Exception {};

