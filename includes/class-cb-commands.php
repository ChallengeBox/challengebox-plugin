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
			'date' => !empty($assoc_args['date']) ? new DateTime($assoc_args['date']) : new DateTime(),
			'sku_version' => !empty($assoc_args['sku-version']) ? $assoc_args['sku-version'] : 'v1',
			'limit' => !empty($assoc_args['limit']) ? intval($assoc_args['limit']) : false,
			'points' => !empty($assoc_args['points']) ? intval($assoc_args['points']) : false,
			'note' => !empty($assoc_args['note']) ? $assoc_args['note'] : false,
			'segment' => !empty($assoc_args['segment']) ? $assoc_args['segment'] : false,
		);
		if ($this->options->all) {
			unset($assoc_args['all']);
			WP_CLI::debug("Grabbing user ids...");
			$args = array_map(function ($user) { return $user->ID; }, get_users());
		}
		sort($args);
		if ($this->options->limit) {
			$args = array_slice($args, 0, $this->options->limit);
		}
		//var_dump(array('args'=>$args, 'assoc_args'=>$assoc_args));
		return array($args, $assoc_args);
	}

	/**
	 * Identifies a user to Segment without having them visit the website.
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
	 * : Don't actually do any api calls.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
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
	 *     wp cb identify 167
	 */
	function identify($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array();
		$segment = new CBSegment();

		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id");
			$customer = new CBCustomer($user_id);
			$data = $segment->identify($customer, $this->options->pretend);
			$result = array_merge(array('id' => $user_id), $data['traits']);
			array_push($results, $result);
			$columns = array_unique(array_merge($columns, array_keys($result)));
		}

		$segment->flush();

		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Rebuilds user metadata (in wp_usermeta) from source tables.
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
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
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
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

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
				'renewal_before' => $customer->get_meta('renewal_date') ? $customer->get_meta('renewal_date')->format('Y-m-d') : null,
				'status_before' => $customer->get_meta('subscription_status'),
				'type_before' => $customer->get_meta('subscription_type'),
			);

			try {

				$customer->update_metadata($this->options->overwrite, $this->options->pretend);

				$result = array_merge($result, array(
					'clothing_after' => $customer->get_meta('clothing_gender'),
					'tshirt_after' => $customer->get_meta('tshirt_size'),
					'box_after' => $customer->get_meta('box_month_of_latest_order') + 0,
					'active_after' => $customer->get_meta('active_subscriber'),
					'renewal_after' => $customer->get_meta('renewal_date') ? $customer->get_meta('renewal_date')->format('Y-m-d') : null,
					'status_after' => $customer->get_meta('subscription_status'),
					'type_after' => $customer->get_meta('subscription_type'),
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
					'renewal_after' => NULL,
					'status_after' => NULL,
					'type_after' => NULL,
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
			'renewal_before',
			'renewal_after',
			'status_before',
			'status_after',
			'type_before',
			'type_after',
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
	 * Synchronizes renewal dates. Ok, actually just pushes them out to the 26th of
	 * the month if they are going to renew before.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to adjust renewal date.
	 *
	 * [--all]
	 * : Adjust dates for all users. (Ignores <id>... if found).
	 *
	 * [--pretend]
	 * : Don't actually adjust dates, just print out what we'd do.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
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
	 *     wp cb synchronize_renewal 167
	 */
	function synchronize_renewal($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		/*
		$1m = !empty($assoc_args['1m']);
		$3m = !empty($assoc_args['3m']);
		$12m = !empty($assoc_args['3m']);
		*/

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("Synchronizing $user_id");

			if (sizeof($customer->get_active_subscriptions()) == 0) {
				WP_CLI::debug("\tNo active subscriptions. Skipping.");
				continue;
			}

			foreach ($customer->get_active_subscriptions() as $sub) {

				$sub_id = $sub->id;
				$renewal_date = CBWoo::parse_date_from_api($sub->billing_schedule->next_payment_at);

				WP_CLI::debug("\tSubscription $sub_id...");

				if (!$renewal_date) {
					WP_CLI::debug("\t\tSubscription doesn't have renewal date. Skipping.");
					continue;
				}

				// Get a date on the 26th that has the same H:M:S as renewal date
				// to ensure the renewals are spaced out
				$the_26th = new DateTime("first day of this month");
				$the_26th->add(new DateInterval("P25D"));
				$new_date = clone $renewal_date;
				$new_date->setDate($the_26th->format('Y'), $the_26th->format('m'), $the_26th->format('d'));

				if ($this->options->verbose)
					WP_CLI::debug(var_export(array('new'=>$new_date,'old'=>$renewal_date), true));

				if ($renewal_date >= $new_date) {
					WP_CLI::debug("\t\tRenewal too far out, doesn't need to be adjusted.");
					continue;
				} else {
					WP_CLI::debug("\t\tExisting renewal is before the 26th, adjusting.");
					$new_sub = array(
						'subscription' => array(
							'next_payment_date' => CBWoo::format_DateTime_for_api($new_date)
						)
					);
					if ($this->options->verbose)
						WP_CLI::debug("\t\tModifying date: " . var_export($new_sub, true));
					if (!$this->options->pretend) {
						$result = $this->api->update_subscription($sub->id, $new_sub);
						if ($this->options->verbose)
							WP_CLI::debug("\t\tResult: " . var_export($result, true));
					}
				}
			}

		}
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
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

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
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);
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
	 * [<user_id>...]
	 * : The user id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--pretend]
	 * : Don't do anything, just print out what we would have done.
	 *
	 * [--force]
	 * : Create the next order for the customer, even if they already have an order this month.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 * [--date=<date>]
	 * : Date on which to generate the order. Should be in iso 8601 format.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb generate_order 167
	 */
	function generate_order( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("User $user_id");

			// Reasons to not generate an order
			if (!$this->options->force && (
				$customer->has_box_order_this_month($this->options->date)
					||
				$customer->has_box_order_this_month(new DateTime())
			)) {
				WP_CLI::debug("\tCustomer already has a valid order this month.");
				continue;
			} 
			if (!$customer->has_active_subscription()) {
				WP_CLI::debug("\tCustomer doesn't have an active subscription.");
				continue;
			}

			$next_order = $customer->next_order_data($this->options->date);
			WP_CLI::debug("\tGenerating order");
			if ($this->options->verbose) {
				WP_CLI::debug("\tNext order: " . var_export($next_order, true));
			}

			if (!$this->options->pretend) {
				$response = $this->api->create_order($next_order);
				WP_CLI::debug("\tCreated new order");
				if ($this->options->verbose) {
					WP_CLI::debug("\tResponse: " . var_export($response, true));
				}
				/*
				$response = $this->api->update_order($response->order->id, array(
					'created_at' => $this->options->date->format("Y-m-d H:i:s")
				));
				WP_CLI::debug("\tTried to change date");
				if ($this->options->verbose) {
					WP_CLI::debug("\tResponse: " . var_export($response, true));
				}
				*/
			}
		}
	}

	/**
	 * Finds users with cancelled orders that don't have cancelled subscriptions.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--pretend]
	 * : Don't do anything, just print out what we would have done.
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
	 *     wp cb find_forgot_to_cancel 167
	 */
	function find_forgot_to_cancel( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("User $user_id");
			$any_order_cancelled = CB::any(array_filter(
				$customer->get_orders(),
				function ($order) { return 'cancelled' == $order->status; }
			));
			if ($any_order_cancelled && $customer->has_active_subscription()) {
				WP_CLI::debug("\tFound a candidate.");
				array_push($results, array('id'=>$user_id, 'email'=>get_userdata($user_id)->user_email));
			}
		}

		if (sizeof($results)) {
			WP_CLI\Utils\format_items($this->options->format, $results, array('id', 'email'));
		}
	}

	/**
	 * Corrects the sku of a processing order if that sku is the wrong month.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
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
	 *     wp cb correct_sku 167
	 */
	function correct_sku( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("User $user_id");

			$processing_box_orders = array_filter(
				$customer->get_orders(),
				function ($order) {
					return (
						CBWoo::is_valid_box_order($order) 
							||
						CBWoo::is_valid_single_box_order($order)
					) && 'processing' == $order->status;
				}
			);

			if (sizeof($processing_box_orders) < 1) {
				WP_CLI::debug("\tCustomer has no processing orders.");
				continue;
			}
			if (sizeof($processing_box_orders) > 1) {
				WP_CLI::debug("\tCustomer has more than one box order processing.");
				continue;
			}

			$order = array_pop($processing_box_orders);
			$current_sku = CBWoo::extract_order_sku($order);
			if (CBWoo::is_valid_single_box_order($order)) {
				//$next_sku = $customer->get_single_box_sku('single-' . $this->options->sku_version);
				$next_sku = $customer->get_single_box_sku();
				if ($next_sku == 'sbox__') {
					WP_CLI::debug("\tCustomer missing gender and size.");
					array_push($results, array(
						'id' => $user_id,
						'next_sku' => $next_sku,
						'current_sku' => $current_sku,
						'error' => 'Customer missing gender and size',
					));
					continue;
				}
			} else {
				$next_sku = $customer->get_next_box_sku($this->options->sku_version);
			}

			if ($next_sku !== $current_sku) {
				WP_CLI::debug("\tSkus don't match: next $next_sku current $current_sku.");
				$new_product = $this->api->get_product_by_sku($next_sku);
				$old_line_item = NULL;
				$new_order = array(
					'line_items' => array_map(
						function ($line_item) use ($current_sku, $next_sku, $new_product, &$old_line_item) {
							$new_line_item = (array) clone $line_item;
							if ($new_line_item['sku'] == $current_sku) {
								//$new_line_item['sku'] = $next_sku;
								//$new_line_item['product_id'] = $new_product->id;
								$new_line_item['product_id'] = NULL;
								//unset($new_line_item['id']);
								//unset($new_line_item['name']);
								//$new_line_item['quantity'] = 0;
								$old_line_item = $new_line_item;
							}
							unset($new_line_item['meta']);
							return (array) $new_line_item;
						},
						$order->line_items
					)
				);
				array_push($new_order['line_items'], array(
					'id' => NULL,
					'product_id' => $new_product->id,
					'quantity' => $old_line_item['quantity'],
					'price' => $old_line_item['price'],
					'subtotal' => $old_line_item['subtotal'],
					'subtotal_tax' => $old_line_item['subtotal_tax'],
					'total' => $old_line_item['total'],
					'total_tax' => $old_line_item['total_tax'],
				));
				WP_CLI::debug(var_export($new_order, true));

				if (!$this->options->pretend) {
					try {
						$response = $this->api->update_order($order->id, $new_order);
						WP_CLI::debug("\tUpdate order response: " . var_export($response, true));
						array_push($results, array(
							'id' => $user_id,
							'next_sku' => $next_sku,
							'current_sku' => $current_sku,
							'error' => NULL,
						));
					} catch (Exception $e) {
						array_push($results, array(
							'id' => $user_id,
							'next_sku' => $next_sku,
							'current_sku' => $current_sku,
							'error' => $e->getMessage(),
						));
					}
				} else {
					array_push($results, array(
						'id' => $user_id,
						'next_sku' => $next_sku,
						'current_sku' => $current_sku,
						'error' => NULL,
					));
				}

			}
			else {
				WP_CLI::debug("\tSkus match, no worries.");
			}
		}

		if (sizeof($results)) {
			WP_CLI\Utils\format_items($this->options->format, $results, array('id', 'next_sku', 'current_sku', 'error'));
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

	/**
	 * Clears a user's fitbit cache for a given month.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * <year_month>
	 * : The year and month to check. (i.e. 2016-04)
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb clear_fitbit_month_cache 167 2016-04
	 */
	function clear_fitbit_month_cache( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		list( $user_id, $year_month ) = $args;
		$customer = new CBCustomer($user_id);
		$month_start = new DateTime($year_month);
		$month_start->setTime(0,0);
		$month_end = clone $month_start; $month_end->modify('last day of');
		WP_CLI::debug('BEFORE: ' . var_export(
				$customer->inspect_fitbit_cache($month_start, $month_end), true)
		);
		if (! $this->options->pretend) {
			$customer->clear_fitbit_cache($month_start, $month_end);
		}
		WP_CLI::debug('AFTER: ' . var_export(
				$customer->inspect_fitbit_cache($month_start, $month_end), true)
		);
	}

	/**
	 * Calculates churn variables for a user for a given month.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--date]
	 * : The year and month to check. (i.e. 2016-04). Defaults to current month.
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
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
	 *     wp cb calculate_churn 167 --date=2016-04
	 */
	function calculate_churn( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);

		$results = array();

		$month_start = clone $this->options->date;
		$month_start->setTime(0,0);
		$month_end = clone $month_start; $month_end->modify('last day of');
		$month = $month_start->format('Y-m');
		//$last_month_start = clone $month_start; $last_month_start->modify('first day of last month');
		//$last_month_end = clone $month_start; $last_month_end->modify('last day of last month');
		$mrr_key = 'mrr_' . $month;
		$columns = array('id', 'cohort', 'first_sub', $mrr_key, 'error');

		foreach ($args as $user_id) {

			try {
				WP_CLI::debug("User $user_id.");
				$customer = new CBCustomer($user_id);
				$registered = new DateTime(get_userdata($user_id)->user_registered);
				if ($registered > $month_end) {
					WP_CLI::debug("\tSkipping, user registered after month end.");
					continue;
				}

				//$is_active = $customer->is_active_during_period($month_start, $month_end);
				//$is_active_last = $customer->is_active_during_period($last_month_start, $last_month_end);
				$cohort = $customer->earliest_subscription_date()->format('Y-m');
				$mrr = $customer->mrr_during_period($month_start, $month_end);

				if (! $this->options->pretend) {
					$customer->set_meta('cohort', $registered->format('Y-m'));
					$customer->set_meta($mrr_key, $mrr);
				}

				array_push($results, array(
					'id' => $user_id,
					'cohort' => $registered->format('Y-m'),
					'first_sub' => $cohort,
					$mrr_key => $mrr,
					'error' => NULL,
				));

			} catch (Exception $e) {
				WP_CLI::debug("\tError: " . $e->getMessage());	
				array_push($results, array(
					'id' => $user_id,
					'cohort' => isset($registered) ? $registered->format('Y-m') : NULL,
					'first_sub' => isset($cohort) ? $cohort : NULL,
					$mrr_key => isset($mrr) ? $mrr : NULL,
					'error' => $e->getMessage(),
				));
				if ($this->options->verbose)
					var_dump($e->getTrace());
			}

		}
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Applies points from month to account.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--date]
	 * : The year and month to check. (i.e. 2016-04). Defaults to current month.
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
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
	 *     wp cb apply_month_points 167 --date=2016-04
	 */
	function apply_month_points( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);

		$results = array();

		$month_start = clone $this->options->date;
		$month_start->setTime(0,0);
		$month_end = clone $month_start; $month_end->modify('last day of');
		$month = $month_start->format('Y-m');
		$points_key = 'cb-points-month-v1_' . $month;
		$points_applied_key = 'cb-points-applied-month-v1_' . $month;
		$columns = array(
			'id', 'before total', 'month points',
			'month points previously applied',
			'difference', 'after total', 'error'
		);

		foreach ($args as $user_id) {

			try {
				WP_CLI::debug("User $user_id.");
				$customer = new CBCustomer($user_id);
				$before_total = WC_Points_Rewards_Manager::get_users_points($user_id);
				$points = $customer->get_meta($points_key, 0);
				$points_applied = $customer->get_meta($points_applied_key, 0);
				$difference = $points - $points_applied;

				WP_CLI::debug("\tpoints $points applied $points_applied difference $difference");

				if ($points === 0) {
					WP_CLI::debug("\tSkipping, user has no points recorded this month.");
					continue;
				}

				if ($difference > 0) {
					WP_CLI::debug("\tAdding $difference points.");
					if (! $this->options->pretend) {
						WC_Points_Rewards_Manager::increase_points($user_id, $difference, 'monthly-challenge', $month_start);
						$customer->set_meta($points_applied_key, $points);
					}
				}
				elseif ($difference < 0) {
					$difference = -$difference;
					WP_CLI::debug("\tSubtracting $difference points.");
					if (! $this->options->pretend) {
						WC_Points_Rewards_Manager::decrease_points($user_id, $difference, 'monthly-challenge', $month_start);
						$customer->set_meta($points_applied_key, $points);
					}
				}

				$after_total = WC_Points_Rewards_Manager::get_users_points($user_id);

				$results[] = array(
					'id' => $user_id,
					'before total' => $before_total,
					'month points' => $points,
					'month points previously applied' => $points_applied,
					'difference' => $difference,
					'after total' => $after_total,
					'error' => NULL,
				);

			} catch (Exception $e) {
				WP_CLI::debug("\tError: " . $e->getMessage());

				$results[] = array(
					'id' => $user_id,
					'before total' => isset($before_total) ? $before_total : NULL,
					'month points' => isset($points) ? $points : NULL,
					'month points previously applied' => isset($points_applied) ? : NULL,
					'difference' => isset($difference) ? $difference : NULL,
					'after total' => isset($after_total) ? $after_total : NULL,
					'error' => NULL,
				);

				if ($this->options->verbose) {
					var_dump($e->getTrace());
				}
			}

		}
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Applies points to account.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * --points=<points>
	 * : Number of points to add or subtract.
	 *
	 * --note=<note>
	 * : A note to include with this points adjustment.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
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
	 *     wp cb apply_points 167 --points=100 --note="Thanks for bearing with us!"
	 */
	function apply_points( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array(
			'id', 'before total', 'points applied',
			'note', 'after total', 'error'
		);
		$points = $this->options->points;
		$note = $this->options->note;

		foreach ($args as $user_id) {

			try {
				WP_CLI::debug("User $user_id.");
				$customer = new CBCustomer($user_id);
				$before_total = WC_Points_Rewards_Manager::get_users_points($user_id);

				if ($points >= 0) {
					WP_CLI::debug("\tAdding $points points.");
					if (! $this->options->pretend) {
						WC_Points_Rewards_Manager::increase_points($user_id, $points, 'point-adjustment', $note);
					}
				}
				elseif ($points < 0) {
					$points = -$points;
					WP_CLI::debug("\tSubtracting $points points.");
					if (! $this->options->pretend) {
						WC_Points_Rewards_Manager::decrease_points($user_id, $points, 'point-adjustment', $note);
					}
				}

				$after_total = WC_Points_Rewards_Manager::get_users_points($user_id);

				$results[] = array(
					'id' => $user_id,
					'before total' => $before_total,
					'points applied' => $points,
					'note' => $note,
					'after total' => $after_total,
					'error' => NULL,
				);

			} catch (Exception $e) {
				WP_CLI::debug("\tError: " . $e->getMessage());

				$results[] = array(
					'id' => $user_id,
					'before total' => NULL,
					'points applied' => isset($points) ? $points : NULL,
					'note' => isset($note) ? : NULL,
					'after total' => NULL,
					'error' => NULL,
				);

				if ($this->options->verbose) {
					var_dump($e->getTrace());
				}
			}

		}
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Set special segment in customer.io. Overwrites existing special segment.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * --segment=<segment>
	 * : Name of the special segment.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
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
	 *     wp cb special_segment 167 --segment="Missing Resistance Bands"
	 */
	function special_segment( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		$segment_name = $this->options->segment;
		$segment = new CBSegment();

		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id.");
			$customer = new CBCustomer($user_id);
			if (! $this->options->pretend) {
				WP_CLI::debug("\tSetting special_segment to $segment_name.");
				$customer->set_meta('special_segment', $segment_name);
			}
			WP_CLI::debug("\tcalling identify().");
			$data = $segment->identify($customer, $this->options->pretend);
		}
	}

}

WP_CLI::add_command( 'cb', 'CBCmd' );

