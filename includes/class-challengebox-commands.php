<?php

/**
 * Commands for managing ChallengeBox.
 */
class CBCmd extends WP_CLI_Command {

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
	 *    - json
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
				$result = array(
					'id' => $id, 
					'error' => $ex->getMessage(),
					'clothing_gender' => NULL,
					'tshirt_size' => NULL,
					'_old_month' => NULL,
					'box_month' => NULL,
					'ordered_this_month' => NULL,
					'_orders' => NULL,
					'_orders_shipped' => NULL,
					'_subs' => NULL,
					'_subs_valid' => NULL,
					'active_subscriber' => NULL,
					'warnings' => NULL,
				);
				foreach ($this->get_order_statuses() as $short_name=>$long_name) {
					$result['_orders_' . $short_name] = NULL;
				}
				array_push($results, $result);
			}
		}
		$columns = array(
			'id',
			'clothing_gender',
			'tshirt_size',
			'_old_month',
			'box_month',
			'ordered_this_month',
			'_orders',
			'_orders_shipped',
			'_subs',
			'_subs_valid',
			'active_subscriber',
			'warnings',
			'error'
		);
		foreach ($this->get_order_statuses() as $short_name=>$long_name) {
			array_push($columns, '_orders_' . $short_name);
		}
		WP_CLI\Utils\format_items($format, $results, $columns);
	}

	private function _migrate_user_april($id, $not_pretend, $verbose, $overwrite) {

		WP_CLI::debug("User $id...");

		$warnings = array();
		$customer = $this->get_customer($id);
		$orders = array_filter($this->get_customer_orders($id), function ($o) { return CBCmd::is_valid_box_order($o); });
		$subscriptions = $this->get_customer_subscriptions($id);

		// Arrange orders by status
		$orders_by_status = array();
		foreach ($this->get_order_statuses() as $short_name=>$long_name) {
			$orders_by_status[$short_name] = array_filter(
				$orders, 
				function ($o) use ($short_name) { 
					return $o->status == $short_name;
				}
			);
		}
		//var_export($orders_by_status);

		// Figure out how many orders shipped
		$orders_that_shipped = array();
		foreach ($orders as $order) {
			$notes = $this->get_order_notes($order->id);
			//var_export($notes);
			if (
				CBCmd::any(array_filter($notes, function ($note) {
					return strpos(strtolower($note->note), 'shipped') !== false;
				}))
					||
				'completed' == $order->status
			) {
				array_push($orders_that_shipped, $order);
			}
		}

		// Figure out how many subscriptions are active
		$active_or_pending_subscriptions = array();
		foreach ($subscriptions as $subscription) {
			if ('active' == $subscription->status || 'pending-cancel' == $subscription->status) {
				array_push($active_or_pending_subscriptions, $subscription);
			}
		}

		// Figure out gender, size, etc.
		$parsed = CBCmd::valid_box_orders($orders);
		if (0 == sizeof($parsed)) {
			$parsed = CBCmd::valid_box_orders($subscriptions);
		}
		if (0 == sizeof($parsed)) {
			throw new Exception("User had no orders or subscriptions.");
		}
		if ($verbose)
			WP_CLI::debug(var_export($parsed, true));

		// Sort by month to get latest order first
		//uasort($parsed, function ($a, $b) { return $a->date->diff($b->date)->format('%d')+0; });

		// Assume last order is the latest
		$basis = $parsed[sizeof($parsed)-1];

		//
		// Calculate properties
		//

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
		$_old_month = max($box_month, sizeof($parsed));
		$box_month = sizeof($orders_that_shipped);
		$ordered = CBCmd::customer_has_box_order_this_month($orders);
		$active_subscriber = sizeof($active_or_pending_subscriptions) > 0;

		//
		// Update metadata
		//

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

		if ($not_pretend) {
			update_user_meta($id, 'active_subscriber', $active_subscriber);
			WP_CLI::debug($box_month . ' -> $id.active_subscriber');
		}

		$result = array(
				'id' => $id,
				'tshirt_size' => $tshirt_size,
				'clothing_gender' => $clothing_gender,
				'_old_month' => $_old_month,
				'box_month' => $box_month,
				'ordered_this_month' => $ordered,
				'warnings' => implode(". ", $warnings),
				'_orders' => sizeof($orders),
				'_orders_shipped' => sizeof($orders_that_shipped),
				'_subs' => sizeof($subscriptions),
				'_subs_valid' => sizeof($active_or_pending_subscriptions),
				'active_subscriber' => $active_subscriber,
		);

		foreach ($this->get_order_statuses() as $short_name=>$long_name) {
			$result['_orders_'.$short_name] = sizeof($orders_by_status[$short_name]);
		}

		return $result;
		
	}

	/**
	 * Generates rush orders for users who have chosen this option with their subscription
	 * but do not yet have a rush order.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) to check or order generation.
	 *
	 * [--all]
	 * : Generate orders for all users. (Ignores <id>... if found).
	 *
	 * [--pretend]
	 * : Don't actually generate any orders, just print out what we'd do.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--continue]
	 * : Continue, even if errors are encountered on a given user.
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
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb generate_rush_orders 167
	 */
	function generate_rush_orders($args, $assoc_args) {
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

			WP_CLI::debug("Checking $id for rush orders...");

			$user_data = get_user_meta($id);
			if ($verbose) WP_CLI::debug(var_export(array('user_data'=>$user_data), true));

			if ($skip && !empty($user_data['_rush_order_checked'])) {
				WP_CLI::debug("\tSkipping $id. (was marked as already checked)");	
				continue;
			}

			$orders = $this->get_customer_orders($id);
			$customer_has_rush_order = CBCmd::any(
				// Return true if any order has a rush order set
				array_filter($orders, function ($order) {
					// Return true if any fee line indicates a rush order
					return CBCmd::any(
						array_filter($order->fee_lines, function ($line) {
							return 'Rush My Box' == $line->title;
						})
					);
				})
			);
			$customer_has_valid_box_order = CBCmd::any(CBCmd::valid_box_orders($orders));

			// Did they select a rush order at all?
			if ($customer_has_rush_order) {
				if ($not_pretend) update_user_meta($id, '_rush_order', 1); 
				WP_CLI::debug("\t1 -> $id._rush_order");

				// If they selected rush order, did an order get generated?
				if (!$customer_has_valid_box_order) {
					// Need to generate an order
					WP_CLI::debug("\tGenerating rush order");
					//if ($not_pretend) update_user_meta($id, '_rush_order_generated', 1);
					WP_CLI::debug("\t1 -> $id._rush_order_generated");
				} else {
					WP_CLI::debug("\tValid box order already found, no need to generate.");
				}
			}

			// Speed up checks next time by noticing if we checked it
			if ($not_pretend) update_user_meta($id, '_rush_order_checked', 1);
			WP_CLI::debug("\t1 -> $id._rush_order_checked");

			/*
			try {
				// Determine if 
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
			*/
		}
	}


	/**
	 * Prints out an estimate of what orders will need to be shipped.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb shipping_estimate
	 */
	function shipping_estimate( $args, $assoc_args ) {
	}

	/**
	 * Prints out and parses skus from a user.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb skus 167
	 */
	function skus( $args, $assoc_args ) {
		list( $id ) = $args;
		$customer = $this->get_customer($id);
		$orders = $this->get_customer_orders($id);
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
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb customer 167
	 */
	function customer( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export($this->get_customer($id)));
	}

	/**
	 * Prints out order statuses available.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb order_statuses
	 */
	function order_statuses( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export($this->get_order_statuses(), true));
	}

	/**
	 * Prints out order data for the given order.
	 *
	 * ## OPTIONS
	 *
	 * <order_id>
	 * : The order id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb order 1039
	 */
	function order( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export($this->get_order($id), true));
	}

	/**
	 * Generates the next order for all subscribers.
	 *
	 * Outputs a log of what it did.
	 *
	 * ## OPTIONS
	 *
	 * [--pretend]
	 * : Don't do anything, just print out what we would have done.
	 *
	 * [--force]
	 * : Create the next order for the customer, even if they already have an order this month.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb generate_orders
	 */
	function generate_orders( $args, $assoc_args ) {
		WP_CLI::debug("Grabbing user ids...");
		$args = array_map(function ($user) { return $user->id; }, get_users());
		foreach ($args as $user_id) {
			$this->generate_order(array($user_id), $assoc_args);
		}
	}

	/**
	 * Generates the next order for a given subscriber.
	 *
	 * Only generates an order if the subscription is active (or pending cancel), and
	 * if they have no other box order that month.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * [--pretend]
	 * : Don't do anything, just print out what we would have done.
	 *
	 * [--force]
	 * : Create the next order for the customer, even if they already have an order this month.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb generate_order 167
	 */
	function generate_order( $args, $assoc_args ) {
		list( $user_id ) = $args;
		$force = !empty($assoc_args['force']);
		$not_pretend = empty($assoc_args['pretend']);

		$customer = $this->get_customer($user_id);
		$orders = $this->get_customer_orders($user_id);
		$subscriptions = CBCmd::filter_subscriptions(
			$this->get_customer_subscriptions($user_id),
			array('active', 'pending-cancel')
		);

		// Choose a subscription
		$subscription = reset($subscriptions);

		if (! $force && $this->customer_has_box_order_this_month($orders)) {
			WP_CLI::debug("\tCustomer already has a valid order this month.");
			return;
		} 

		$next_order = $this->next_order_data($customer, $subscription);
		WP_CLI::debug("\tNext order: " . var_export($next_order, true));

		if ($not_pretend) {
			$response = $this->write_api()->orders->create($next_order);
			WP_CLI::debug("\tCreated new order: " . var_export($response, true));
		}
	}

	/**
	 * Prints out order data for the given customer.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb orders 167
	 */
	function orders( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export($this->get_customer_orders($id), true));
	}

	/**
	 * Prints data for box orders for the given customer.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb box_orders 167
	 */
	function box_orders( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export(CBCmd::valid_box_orders($this->get_customer_orders($id)), true));
	}

	/**
	 * Prints out the given subscription.
	 *
	 * ## OPTIONS
	 *
	 * <subscription_id>
	 * : The subscription id to check.
	 *
	 * ## EXAMPLES
	 *     wp cb subscription 6691
	 */
	function subscription( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export($this->get_subscription($id), true));
	}

	/**
	 * Prints out subscription data for the given customer.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb subscriptions 167
	 */
	function subscriptions( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export($this->get_customer_subscriptions($id), true));
	}

	//
	// Utility functions
	//

	private function get_product_by_sku($id) { return $this->api()->products->get_by_sku($id)->product; }
	private function get_customer($id) { return $this->api()->customers->get($id)->customer; }
	private function get_order($id) { return $this->api()->orders->get($id)->order; }
	private function get_order_notes($id) { return $this->api()->order_notes->get($id)->order_notes; }
	private function get_subscription($id) { return $this->api()->subscriptions->get($id)->subscription; }
	private function get_customer_orders($id) { return $this->api()->customers->get_orders($id)->orders; }
	private function get_customer_subscriptions($id) { 
		return array_map(
			function ($s) {return $s->subscription;},
			$this->api()->customers->get_subscriptions($id)->customer_subscriptions
		);
	}

	private $_order_statuses;
	private function get_order_statuses() { 
		if (empty($this->_order_statuses)) {
			$this->_order_statuses = (array) $this->api()->orders->get_statuses()->order_statuses;
		}
		return $this->_order_statuses;
	}

	// XXX: experimental internal wp api thing. Returns a (cached) woocommerce api object
	private $_woo_api;
	private function wapi() {
		if (empty($this->_woo_api)) {
			wp_set_current_user(167);
			WC()->api->includes();
			WC()->api->register_resources( new WC_API_Server( '/' ) );
			$this->_woo_api = WC()->api;
		}
		return $this->_woo_api;
	}

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

	// Returns true if the sku looks like a valid box order
	private static function is_valid_box_order($order) {
		foreach ($order->line_items as $line_item) {
			$item = array(
				'month' => 1,
				'gender' => NULL,
				'size' => NULL,
				'plan' => '1m',
				'date' => new DateTime($order->created_at),
			);

			// Gather data from the sku
			if (!empty($line_item->sku)) {
				try {
					$sku = CBCmd::parse_sku($line_item->sku);
					$item['gender'] = $sku->gender;
					$item['size'] = $sku->size;
					$item['month'] = $sku->month;
					$item['plan'] = $sku->plan;
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

			// Accept this sku if we have all the properties
			if (CBCmd::all($item)) return true;
		}
		return false;
	}

	// Returns all valid box skus found in customer's orders, parsed
	// and with the date included.
	// Does not return a sku if it is not a box.
	private static function valid_box_orders($orders) {
		$result = array();
		foreach ($orders as $order) {
			foreach ($order->line_items as $line_item) {

				$item = array(
					'month' => 1,
					'gender' => NULL,
					'size' => NULL,
					'plan' => '1m',
					'date' => new DateTime($order->created_at),
				);

				// Gather data from the sku
				if (!empty($line_item->sku)) {
					try {
						$sku = CBCmd::parse_sku($line_item->sku);
						$item['gender'] = $sku->gender;
						$item['size'] = $sku->size;
						$item['month'] = $sku->month;
						$item['plan'] = $sku->plan;
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

				// Accept this sku if we have all the properties
				if (CBCmd::all($item)) array_push($result, (object) $item);
			}
		}
		return $result;
	}

	// Filters out subscriptions that don't have any of the allowed statuses
	private static function filter_subscriptions($subscriptions, $allowed_statuses = array('active', 'pending-cancel')) {
		return array_filter(
			$subscriptions,
			function ($s) use ($allowed_statuses) {
				return CBCmd::any(
					array_filter(
						$allowed_statuses,
						function ($allowed_status) use ($s) {
							return $allowed_status == $s->status;
						}
					)
				);
			}
		);
	}

	// Returns true if customer has a valid order this month
	private static function customer_has_box_order_this_month($orders) {
		$parsed = CBCmd::valid_box_orders($orders);
		$this_month = (new DateTime())->format('Y-m');
		foreach ($parsed as $candidate) {
			if ($candidate->date->format('Y-m') == $this_month) {
				return true;
			}
		}
		return false;
	}

	// Returns a sku formatted from its components
	private static function format_sku($month, $clothing_gender, $tshirt_size) {
		return implode('_', array(
			'cb', 
			'm' . $month,
			strtolower($clothing_gender),
			strtolower($tshirt_size),
		));
	}

	// Returns the sku for the next box to be shipped
	private static function get_users_next_box_sku($user_id) {
		$user_data = get_user_meta($user_id);
		return CBCmd::format_sku(
			$user_data['box_month_of_latest_order'][0] + 1,
			$user_data['clothing_gender'][0],
			$user_data['tshirt_size'][0]
		);
	}

	// Returns the sku for the latest box to have been ordered
	// NOTE: supposedly! This doesn't mean it matches!
	private static function get_users_current_box_sku($user_id) {
		$user_data = get_user_meta($user_id);
		return CBCmd::format_sku(
			$user_data['box_month_of_latest_order'][0],
			$user_data['clothing_gender'][0],
			$user_data['tshirt_size'][0]
		);
	}

	// Generates the request data for the next order in the customer's sequence
	private function next_order_data($customer, $subscription, $date=false) {
		$user_id = $customer->id;
		$sku = $this->get_users_next_box_sku($user_id);
		$product = $this->get_product_by_sku($sku);
		$order = array(
			'status' => 'processing',
			'customer_id' => $customer->id,
			'billing_address' => (array) $subscription->billing_address,
			'shipping_address' => (array) $subscription->shipping_address,
			'line_items' => array(
				array(
					'product_id' => $product->id,
					'sku' => $sku,
					'quantity' => 1,
					'subtotal' => 0,
					'subtotal_tax' => 0,
					'total' => 0,
					'total_tax' => 0,
				)
			),
			'payment_details' => (array) $subscription->payment_details,
		);
		// Default to credit card if subscription doesn't have payment info
		if (empty($order['payment_details']['method_id'])) {
			$order['payment_details']['method_id'] = 'stripe';
		}
		if (empty($order['payment_details']['method_title'])) {
			$order['payment_details']['method_title'] = 'Credit Card';
		}
		$order['payment_details']['paid'] = true;
		return array('order' => $order);
	}

	// Similar to python's all(), returns true if all elements are true (true for empty array).
	private static function all($a) {
		return (bool) !array_filter($a, function ($x) {return !$x;});
	}
	// Similar to python's any(), returns true if any element is true (false for empty array).
	private static function any($a) {
		return (bool) sizeof(array_filter($a));
	}

}

WP_CLI::add_command( 'cb', 'CBCmd' );

