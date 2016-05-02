<?php

/**
 * Commands for managing ChallengeBox.
 */
class CBCmd extends WP_CLI_Command {

	private $api;
	private $options;

	public function __construct() {
		$this->api = new CBWoo();
	}

	private function parse_args($args, $assoc_args) {
		$this->options = (object) array(
			'all' => !empty($assoc_args['all']),
			'pretend' => !empty($assoc_args['pretend']),
			'verbose' => !empty($assoc_args['verbose']),
			'continue' => !empty($assoc_args['continue']),
			'overwrite' => !empty($assoc_args['overwrite']),
			'force' => !empty($assoc_args['force']),
			'skip' => !empty($assoc_args['skip']),
			'format' => !empty($assoc_args['format']) ? $assoc_args['format'] : 'table',
			'sku_version' => !empty($assoc_args['sku-version']) ? $assoc_args['sku-version'] : 'v1',
		);
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
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb update_user_metadata 167
	 */
	function update_user_metadata($args, $assoc_args) {
		$this->parse_args($args, $assoc_args);

		if ($this->options->all) {
			WP_CLI::debug("Grabbing user ids...");
			$args = array_map(function ($user) { return $user->id; }, get_users());
		}
		sort($args);

		$results = array();

		foreach ($args as $user_id) {

			WP_CLI::debug("User $user_id");
			$customer = new CBCustomer($user_id);

			if ($this->options->skip && $customer->get_meta('active_subscriber')) {
				WP_CLI::debug("\tSkipping, already an active subscriber.");
			}

			$result = array(
				'id' => $user_id,
				'clothing_before' => $customer->get_meta('clothing_gender'),
				'tshirt_before' => $customer->get_meta('tshirt_size'),
				'box_before' => $customer->get_meta('box_month_of_latest_order') + 0,
				'active_before' => $customer->get_meta('active_subscriber'),
			);

			try {

				$customer->update_metadata($this->options->overwrite, $this->options->pretend);

				$result = array_merge($result, array(
					'clothing_after' => $customer->get_meta('clothing_gender'),
					'tshirt_after' => $customer->get_meta('tshirt_size'),
					'box_after' => $customer->get_meta('box_month_of_latest_order') + 0,
					'active_after' => $customer->get_meta('active_subscriber'),
					'has_order' => $customer->has_box_order_this_month(),
					'#orders' => sizeof($customer->get_orders()),
					'#box_orders' => sizeof($customer->get_box_orders()),
					'#shipped' => sizeof($customer->get_orders_shipped()),
					'#box_shipped' => sizeof($customer->get_box_orders_shipped()),
					'#subs' => sizeof($customer->get_subscriptions()),
					'error' => NULL,
				));

			} catch (Exception $e) {
				WP_CLI::debug("\tError: " . $e->getMessage());	

				$result = array_merge($result, array(
					'clothing_after' => NULL,
					'tshirt_after' => NULL,
					'box_after' => NULL,
					'active_after' => NULL,
					'has_order' => NULL,
					'#orders' => NULL,
					'#box_orders' => NULL,
					'#shipped' => NULL,
					'#box_shipped' => NULL,
					'#subs' => NULL,
					'error' => $e->getMessage(),
				));

			}

			array_push($results, $result);
			if ($this->options->verbose) {
				WP_CLI::debug(var_export($result, true));
			}
		}

		$columns = array(
			'id',
			'clothing_before',
			'clothing_after',
			'tshirt_before',
			'tshirt_after',
			'box_before',
			'box_after',
			'active_before',
			'active_after',
			'has_order',
			'#orders',
			'#box_orders',
			'#shipped',
			'#box_shipped',
			'#subs',
			'error'
		);
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
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

		$this->parse_args($args, $assoc_args);

		if ($this->options->all) {
			WP_CLI::debug("Grabbing user ids...");
			$args = array_map(function ($user) { return $user->id; }, get_users());
		}
		sort($args);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);

			WP_CLI::debug("Checking $user_id for rush orders...");

			if ($this->options->skip && $customer->get_meta('_rush_order_checked')) {
				WP_CLI::debug("\tSkipping (was marked as already checked)");	
				continue;
			}

			$customer_has_rush_order = CBCmd::any(
				// Return true if any order has a rush order set
				array_filter($customer->get_orders(), function ($order) {
					// Return true if any fee line indicates a rush order
					return CBCmd::any(
						array_filter($order->fee_lines, function ($line) {
							return 'Rush My Box' == $line->title;
						})
					);
				})
			);
			$customer_has_valid_box_order = (bool) sizeof($customer->get_box_orders());

			// Did they select a rush order at all?
			if ($customer_has_rush_order) {
				if (!$this->options->pretend) {
					$customer->set_meta('_rush_order', 1);
				}
				WP_CLI::debug("\t1 -> $id._rush_order");

				// If they selected rush order, did an order get generated?
				if (!$customer_has_valid_box_order) {
					// Need to generate an order
					WP_CLI::debug("\tGenerating rush order");
					if (!$this->options->pretend) {
						//$customer->set_meta('_rush_order_generated', 1);
					}
					WP_CLI::debug("\t1 -> $id._rush_order_generated");
				} else {
					WP_CLI::debug("\tValid box order already found, no need to generate.");
				}
			}

			// Speed up checks next time by noticing if we checked it
			if ($not_pretend) {
				$customer->set_meta('_rush_order_checked', 1);
			}
			WP_CLI::debug("\t1 -> $id._rush_order_checked");
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
		list( $id ) = $args; WP_CLI::line(var_export($this->api->get_customer($id)));
	}

	/**
	 * Prints out order statuses available.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb order_statuses
	 */
	function order_statuses( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export($this->api->get_order_statuses(), true));
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
		list( $id ) = $args; WP_CLI::line(var_export($this->api->get_order($id), true));
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
		$this->parse_args($args, $assoc_args);
		$customer = new CBCustomer($user_id);

		// Reasons to not generate an order
		if (!$this->options->force && $customer->has_box_order_this_month()) {
			WP_CLI::debug("\tCustomer already has a valid order this month.");
			return;
		} 
		if (!$customer->has_active_subscription()) {
			WP_CLI::debug("\tCustomer doesn't have an active subscription.");
			return;
		}

		$next_order = $customer->next_order_data();
		WP_CLI::debug("\tNext order: " . var_export($next_order, true));

		if (!$this->options->pretend) {
			$response = $this->api->create_order($next_order);
			WP_CLI::debug("\tCreated new order: " . var_export($response, true));
		}
	}

	/**
	 * Corrects the sku of a processing order if that sku is the wrong month.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * [--pretend]
	 * : Don't do anything, just print out what we would have done.
	 * 
	 * [--sku-version]
	 * : Use a specific sku version.
	 *  ---
	 *  default: v1
	 *  options:
	 *    - v1
	 *    - v2
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb correct_sku 167
	 */
	function correct_sku( $args, $assoc_args ) {
		list( $user_id ) = $args;
		$this->parse_args($args, $assoc_args);
		$customer = new CBCustomer($user_id);
		WP_CLI::debug("User $user_id.");

		$processing_box_orders = array_filteR(
			$customer->get_box_orders(),
			function ($order) { return 'processing' == $order->status; }
		);

		if (sizeof($processing_box_orders) < 1) {
			WP_CLI::debug("\tCustomer has no processing orders.");
			return;
		}
		if (sizeof($processing_box_orders) > 1) {
			WP_CLI::debug("\tCustomer has more than one box order processing.");
			return;
		}

		$order = $processing_box_orders[0];
		$current_sku = CBWoo::extract_order_sku($order);
		$next_sku = $customer->get_next_box_sku($this->options->sku_version);

		if ($next_sku !== $current_sku) {
			WP_CLI::debug("\t" . var_export(array(
				'next_sku' => $next_sku,
				'current_sku' => $current_sku,
			)));
			return;
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
		list( $user_id ) = $args;
		$customer = new CBCustomer($user_id);
		WP_CLI::line(var_export($customer->get_orders(), true));
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
		list( $user_id ) = $args;
		$customer = new CBCustomer($user_id);
		WP_CLI::line(var_export($customer->get_box_orders(), true));
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
		list( $id ) = $args; WP_CLI::line(var_export($this->api->get_subscription($id), true));
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
		list( $user_id ) = $args;
		$customer = new CBCustomer($user_id);
		WP_CLI::line(var_export($customer->get_subscriptions(), true));
	}

}

WP_CLI::add_command( 'cb', 'CBCmd' );

