<?php

/**
 * Implements example command.
 */
class CBCmd extends WP_CLI_Command {


	/**
	 * Prints a greeting.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The name of the person to greet.
	 *
	 * [--type=<type>]
	 * : Whether or not to greet the person with success or error.
	 * ---
	 * default: success
	 * options:
	 *   - success
	 *   - error
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp example hello Newman
	 *
	 * @when before_wp_load
	 */
	function hello( $args, $assoc_args ) {
		list( $name ) = $args;

		// Print the message with type
		$type = $assoc_args['type'];
		WP_CLI::$type( "Hello, $name!" );
	}

	/**
	 * Migrates a user to the data storage scheme we introduced in April 2016.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) to migrate.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <id>... if found).
	 *
	 * [--pretend]
	 * : Don't actually do any migration.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--continue]
	 * : Continue, even if errors are encountered on a given user.
	 *
	 * [--overwrite]
	 * : Overwrite values in database, even if there is data there.
	 *
	 * [--skip]
	 * : Skip users that have a value for clothing_gender in their metadata.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb migrate_user_april 167
	 */
	function migrate_user_april($args, $assoc_args) {
		$all = !empty($assoc_args['all']);
		if ($all) {
			WP_CLI::debug("Grabbing user ids...");
			$args = array_map(function ($user) { return $user->id; }, get_users());
		}
		$not_pretend = empty($assoc_args['pretend']);
		$verbose = !empty($assoc_args['verbose']);
		$continue = !empty($assoc_args['continue']);
		$overwrite = !empty($assoc_args['overwrite']);
		$skip = !empty($assoc_args['skip']);
		$format = !empty($assoc_args['format']) ? $assoc_args['format'] : 'table';
		$results = array();
		sort($args);
		foreach ($args as $id) {
			if ($skip && !empty(get_user_meta($id, 'clothing_gender', true))) {
				WP_CLI::debug("Skipping $id");
				continue;
			}
			try {
				$result = CBCmd::_migrate_user_april($id, $not_pretend, $verbose, $overwrite);
				$result['error'] = NULL;
				array_push($results, $result);
			} catch (Exception $ex) {
				WP_CLI::debug("Error for user $id: " . $ex->getMessage());	
				array_push($results, array(
					'id' => $id, 
					'error' => $ex->getMessage(),
					'clothing_gender' => NULL,
					'tshirt_size' => NULL,
					'box_month' => NULL,
					'ordered_this_month' => NULL,
					'warnings' => NULL,
				));
			}
		}
		WP_CLI\Utils\format_items($format, $results, array('id', 'clothing_gender', 'tshirt_size', 'box_month', 'ordered_this_month', 'warnings', 'error'));
	}

	private function _migrate_user_april($id, $not_pretend, $verbose, $overwrite) {

		WP_CLI::debug("User $id...");

		$warnings = array();
		$customer = $this->api()->customers->get($id)->customer;
		$orders = array_filter( // Only non-cancelled orders
			$this->api()->customers->get_orders($id)->orders,
			function($o) { return $o->status == 'processing' || $o->status == 'completed'; }
		);
		$parsed = CBCmd::get_parsed_customer_skus_with_date($orders);

		if ($verbose)
			WP_CLI::debug(var_export($parsed, true));

		if (0 == sizeof($parsed)) {
			throw new Exception("User had no orders.");
		}

		// Sort by month to get latest order first
		//uasort($parsed, function ($a, $b) { return $a->date->diff($b->date)->format('%d')+0; });

		// Assume last order is the latest
		$basis = $parsed[sizeof($parsed)-1];

		$tshirt_size = $basis->size;
		$clothing_gender = $basis->gender;
		$box_month = $basis->month;

		// If we accidentally had more than one identical sku, adjust
		// so that the box month reflects the actual box number
		foreach ($parsed as $order) {
			if ($order != $basis && $order->month == $box_month) {
				$box_month++;
			}
		}
		// Also make sure that box_month is at least the number of valid skus we've seen
		$box_month = max($box_month, sizeof($parsed));

		$ordered = CBCmd::customer_has_box_order_this_month($orders);

		// Warn if acount differs from last order
		$account_clothing_gender = get_user_meta($id, 'clothing_gender', true);
		if ($account_clothing_gender && $account_clothing_gender != $clothing_gender) {
			array_push($warnings, "Last order gender $clothing_gender, account gender $account_clothing_gender");
			if ($not_pretend && $overwrite) {
				update_user_meta($id, 'clothing_gender', $clothing_gender);
				WP_CLI::debug($clothing_gender . ' -> $id.clothing_gender');
			}
		}
		else {
			if ($not_pretend) {
				update_user_meta($id, 'clothing_gender', $clothing_gender);
				WP_CLI::debug($clothing_gender . ' -> $id.clothing_gender');
			}
		}
		$account_tshirt_size = get_user_meta($id, 'tshirt_size', true);
		if ($account_tshirt_size && $account_tshirt_size != $tshirt_size) {
			array_push($warnings, "Last order size $tshirt_size, account size $account_tshirt_size");
			if ($not_pretend && $overwrite) {
				update_user_meta($id, 'tshirt_size', $tshirt_size);
				WP_CLI::debug($tshirt_size . ' -> $id.tshirt_size');
			}
		} else {
			if ($not_pretend) {
				update_user_meta($id, 'tshirt_size', $tshirt_size);
				WP_CLI::debug($tshirt_size . ' -> $id.tshirt_size');
			}
		}

		if ($not_pretend) {
			update_user_meta($id, 'box_month_of_latest_order', $box_month);
			WP_CLI::debug($box_month . ' -> $id.box_month_of_latest_order');
		}

		return array(
				'id' => $id,
				'tshirt_size' => $tshirt_size,
				'clothing_gender' => $clothing_gender,
				'box_month' => $box_month,
				'ordered_this_month' => $ordered,
				'warnings' => implode(". ", $warnings),
		);
		
	}

	/**
	 * (DEBUG COMMAND) Prints out and parses skus from a user.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb skus 167
	 */
	function skus( $args, $assoc_args ) {
		list( $id ) = $args;
		$customer = $this->api()->customers->get($id)->customer;
		$orders = $this->api()->customers->get_orders($id)->orders;
		$skus = CBCmd::get_customer_skus($customer, $orders);
		$parsed = CBCmd::parse_skus($skus);
		uasort($parsed, function ($a, $b) { return $b->month - $a->month; });
		WP_CLI::line(var_export(array(
				'skus' => $skus,
				'parsed' => $parsed,
		), false));
	}

	/**
	 * Prints out customer data.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb customer 167
	 */
	function customer( $args, $assoc_args ) {
		list( $id ) = $args;
		$customer = $this->api()->customers->get($id)->customer;
		WP_CLI::line(var_export($customer));
	}

	/**
	 * Prints out order data for the given customer.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb orders 167
	 */
	function orders( $args, $assoc_args ) {
		list( $id ) = $args;
		$orders = $this->api()->customers->get_orders($id)->orders;
		WP_CLI::line(var_export($orders));
	}

	//
	// Utility functions
	//

	// Returns a (cached) api object
	private $_api;
	private function api() {
		if (empty($this->_api)) {
			$options = array('ssl_verify' => false,);
			$this->_api = new WC_API_Client(
				'https://www.getchallengebox.com',
				'ck_b6f54bfa509972150c050e1a36c2199ddd5f6017',
				'cs_0d370d150e37bd90f1950a51b1231d4343c2bfb8',
				$options
			);
		}
		return $this->_api;
	}

	// Returns a (cached, writable) api object
	private $_write_api;
	private function write_api() {
		if (empty($this->_write_api)) {
			$options = array('ssl_verify' => false,);
			$this->_write_api = new WC_API_Client(
				'https://www.getchallengebox.com',
				'ck_869ca223f6b38ff9a17971147490ef8c488476f9',
				'cs_f88941f6bba9bb60154f87f12cddacc1a2c8d195',
				$options
			);
		}
		return $this->_write_api;
	}

	// Parses month, gender, size and plan from sku
	private static function parse_sku($sku) {
		switch (substr_count($sku, '_')) {
			case 3: 
        list($cb, $month_raw, $gender, $size) = explode('_', $sku);
				$plan = '1m';
				break;
			case 4: 
        list($cb, $month_raw, $gender, $size, $plan) = explode('_', $sku); break;
			default: 
				throw new Exception('Invalid sku');
		}
		return (object) array(
			'month' => (int) substr($month_raw, 1),
			'gender' => strtolower($gender),
			'size' => strtolower($size),
			'plan' => strtolower($plan),
		);
	}

	// Parses an array of skus, discarding the bad ones
	private static function parse_skus($skus) {
		$parsed = array();
		foreach ($skus as $sku) {
			try {
				array_push($parsed, CBCmd::parse_sku($sku));
			} catch (Exception $ex) {
			}
		}
		return $parsed;
	}

	// Returns all skus found in customer's orders
	private static function get_customer_skus($customer, $orders) {
		$skus = array();
		foreach ($orders as $order) {
			foreach ($order->line_items as $line_item) {
				if (!empty($line_item->sku)) {
					array_push($skus, $line_item->sku);
				}
			}
		}
		return $skus;
	}

	// Returns all skus found in customer's orders, parsed
	// and with the date included.
	private static function get_parsed_customer_skus_with_date($orders) {
		$result = array();
		foreach ($orders as $order) {
			foreach ($order->line_items as $line_item) {
				if (!empty($line_item->sku)) {
					try {
						$parsed_sku = (array) CBCmd::parse_sku($line_item->sku);
						$parsed_sku['date'] = new DateTime($order->created_at);
						array_push($result, (object) $parsed_sku);
					} catch (Exception $ex) {
						continue;
					}
				}
			}
		}
		return $result;
	}

	// Returns true if customer has a valid order this month
	private static function customer_has_box_order_this_month($orders) {
		$parsed = CBCmd::get_parsed_customer_skus_with_date($orders);
		$this_month = (new DateTime())->format('Y-m');
		foreach ($parsed as $candidate) {
			if ($candidate->date->format('Y-m') == $this_month) {
				return true;
			}
		}
		return false;
	}

}

WP_CLI::add_command( 'cb', 'CBCmd' );

