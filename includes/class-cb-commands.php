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
			'reverse' => !empty($assoc_args['reverse']),
			'pretend' => !empty($assoc_args['pretend']),
			'verbose' => !empty($assoc_args['verbose']),
			'continue' => !empty($assoc_args['continue']),
			'overwrite' => !empty($assoc_args['overwrite']),
			'force' => !empty($assoc_args['force']),
			'skip' => !empty($assoc_args['skip']),
			'format' => !empty($assoc_args['format']) ? $assoc_args['format'] : 'table',
			'date' => !empty($assoc_args['date']) ? new DateTime($assoc_args['date']) : new DateTime(),
			'day' => !empty($assoc_args['day']) ? new DateTime($assoc_args['day']) : new DateTime(),
			'renewal_cutoff' => !empty($assoc_args['renewal-cutoff']) ? new DateTime($assoc_args['renewal-cutoff']) : new DateTime(),
			'month' => !empty($assoc_args['month']) ? new DateTime($assoc_args['month']) : new DateTime(),
			'sku_version' => !empty($assoc_args['sku-version']) ? $assoc_args['sku-version'] : 'v1',
			'sku' => !empty($assoc_args['sku']) ? $assoc_args['sku'] : null,
			'limit' => !empty($assoc_args['limit']) ? intval($assoc_args['limit']) : false,
			'points' => !empty($assoc_args['points']) ? intval($assoc_args['points']) : false,
			'credit' => !empty($assoc_args['credit']) ? intval($assoc_args['credit']) : 1,
			'adjustment' => !empty($assoc_args['adjustment']) ? intval($assoc_args['adjustment']) : false,
			'note' => !empty($assoc_args['note']) ? $assoc_args['note'] : false,
			'segment' => !empty($assoc_args['segment']) ? $assoc_args['segment'] : false,
			'flatten' => !empty($assoc_args['flatten']),
			'rush' => !empty($assoc_args['rush']),
			'auto' => !empty($assoc_args['auto']),
			'revenue' => !empty($assoc_args['revenue']),
			'bonus' => !empty($assoc_args['bonus']),
			'series' => !empty($assoc_args['series']) ? $assoc_args['series'] : 'water',
		);

		// Month option should be pegged to the first day
		if (empty($assoc_args['month'])) {
			$this->options->month->modify('first day of next month');
		}
		$this->options->month->setDate(
			$this->options->month->format('Y'),
			$this->options->month->format('m'),
			1
		);
		$this->options->month->setTime(0, 0);

		if (empty($assoc_args['renewal-cutoff'])) {
			$this->options->renewal_cutoff = clone $this->options->month;
			$this->options->renewal_cutoff->sub(new DateInterval("P1M"));
			$this->options->renewal_cutoff->add(new DateInterval("P19D"));
		}

		// All option triggers gathering user ids
		if ($this->options->all) {
			unset($assoc_args['all']);
			WP_CLI::debug("Grabbing user ids...");
			$args = array_map(function ($user) { return $user->ID; }, get_users());
		}
		sort($args);
		if ($this->options->reverse) {
			$args = array_reverse($args);
		}

		// Limit option affects incoming args
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
	 * [--revenue]
	 * : Include revenue data.
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
			$data = $segment->identify($customer, $this->options->pretend, $this->options->revenue);
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
				'points_before' => $customer->get_meta('wc_points_balance'),
				'oauth_before' => $customer->get_meta('fitbit_oauth_status'),
				'rush_before' => $customer->get_meta('has_rush_order'),
				'cohort_before' => $customer->get_meta('mrr_cohort'),
			);

			try {

				$errors = $customer->update_metadata($this->options->overwrite, $this->options->pretend);

				$result = array_merge($result, array(
					'clothing_after' => $customer->get_meta('clothing_gender'),
					'tshirt_after' => $customer->get_meta('tshirt_size'),
					'box_after' => $customer->get_meta('box_month_of_latest_order') + 0,
					'active_after' => $customer->get_meta('active_subscriber'),
					'renewal_after' => $customer->get_meta('renewal_date') ? $customer->get_meta('renewal_date')->format('Y-m-d') : null,
					'status_after' => $customer->get_meta('subscription_status'),
					'type_after' => $customer->get_meta('subscription_type'),
					'points_after' => $customer->get_meta('wc_points_balance'),
					'oauth_after' => $customer->get_meta('fitbit_oauth_status'),
					'rush_after' => $customer->get_meta('has_rush_order'),
					'cohort_after' => $customer->get_meta('mrr_cohort'),
					'has_order' => $customer->has_box_order_this_month(),
					'#orders' => sizeof($customer->get_orders()),
					'#box_orders' => sizeof($customer->get_box_orders()),
					'#shipped' => sizeof($customer->get_orders_shipped()),
					'#box_shipped' => sizeof($customer->get_box_orders_shipped()),
					'#subs' => sizeof($customer->get_subscriptions()),
					'error' => implode(', ', array_map(function ($e) { return $e->getMessage(); }, $errors)),
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
					'points_after' => NULL,
					'oauth_after' => NULL,
					'rush_after' => NULL,
					'cohort_after' => NULL,
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
			'clothing_before', 'clothing_after',
			'tshirt_before', 'tshirt_after',
			'box_before', 'box_after',
			'active_before', 'active_after',
			'renewal_before', 'renewal_after',
			'status_before', 'status_after',
			'type_before', 'type_after',
			'points_before', 'points_after',
			'oauth_before', 'oauth_after',
			'rush_before', 'rush_after',
			'cohort_before', 'cohort_after',
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
	 * [--day=<day>]
	 * : Day on which to synchronize renewal (ignores time and uses the time
	 *   of day of the original renewal date). Format like '2016-05-26'.
	 *   Defaults to the 20th of this month.
	 *
	 * [--all]
	 * : Adjust dates for all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--auto]
	 * : Try and guess dates (EXPERIMENTAL)
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

		if (empty($assoc_args['day'])) {
			// Get a date on the 20th that has the same H:M:S as renewal date
			// to ensure the renewals are spaced out
			$renewal_day = new DateTime("first day of this month");
			$renewal_day->add(new DateInterval("P19D"));
		} else {
			$renewal_day = $this->options->day;
		}

		$results = array();
		$columns = array('id', 'sub_id', 'type', 'earned', 'boxes', 'old_renewal', 'new_renewal', 'errors');

		foreach ($args as $user_id) {
			try {
				$customer = new CBCustomer($user_id);
			} catch (Exception $e) {
				$customer = new CBCustomer($user_id);
			}
			WP_CLI::debug("Synchronizing $user_id");

			try { $customer->get_active_subscriptions(); } catch (Exception $e) {}

			if (sizeof($customer->get_active_subscriptions()) == 0) {
				WP_CLI::debug("\tNo active subscriptions. Skipping.");
				continue;
			}

			foreach ($customer->get_active_subscriptions() as $sub) {

				$sub_id = $sub->id;
				$renewal_date = CBWoo::parse_date_from_api($sub->billing_schedule->next_payment_at);
				$start_date = CBWoo::parse_date_from_api($sub->billing_schedule->start_at);

				WP_CLI::debug("\tSubscription $sub_id...");

				if (!$renewal_date) {
					WP_CLI::debug("\t\tSubscription doesn't have renewal date. Skipping.");
					continue;
				}

				$original_renewal = clone $renewal_date;

				try { $customer->get_box_orders(); } catch (Exception $e) {}

				$last_box_order = end($customer->get_box_orders());
				//$last_order = CBWoo::parse_date_from_api($last_box_order->created_at);

				// Exploratory stuff
				$earned = NULL;
				$boxes = NULL;
				if ($this->options->auto) {

					$the_12th = new DateTime('2016-06-12');
					if ($renewal_date > $the_12th) {
						WP_CLI::debug("\t\tToo far out. Skipping.");
						continue;
					}

					// Find number of days sub was active
					$mod = clone $start_date;

					if ($customer->get_subscription_type() === 'Month to Month') {
						if (($mod->format('d')+0) > 20) {
							$mod->add('P12D');
						}
						$mod->setDate(
							$mod->format('Y'),
							$mod->format('m'),
							20
						);
					}
					$days = $mod->diff($this->options->date)->format('%r%a') + 0;

					// Compare number of boxes "earned" vs actual
					$earned = round($days/30);
					$boxes = sizeof($customer->get_box_orders_shipped($start_date));
					if (0 === $boxes) {
						WP_CLI::debug("\t\tNo boxes shipped yet, skipping.");
						continue;
					}

					if ($this->options->verbose) { 
						WP_CLI::debug(var_export(array(
							'start_date' => $start_date,
							'renewal_date' => $renewal_date,
							'days' => $days,
							'earned' => $earned,
							'orders' => $boxes,
						), true));
					}

					if ($earned <= $boxes) {
						// We need to renew now
						$renewal_day = new DateTime();
						$renewal_day->add(new DateInterval('PT1H'));
						// Instead of keeping original hour/minute slot, use 1 hours from now,
						// plus the original minutes (spacing), so we trigger the renewal today
						$renewal_date->setTime($renewal_day->format('H'), $renewal_date->format('i'));
					} else {
						// We need to postpone
						$renewal_day = new DateTime("first day of next month");
						$renewal_day->add(new DateInterval("P19D"));
					}

					if ($this->options->verbose) { 
						WP_CLI::debug(var_export(array(
							'renewal_date' => $renewal_date,
							'renewal_day' => $renewal_day,
						), true));
					}
				}

				$renewal_y = $renewal_day->format('Y');
				$renewal_m = $renewal_day->format('m');
				$renewal_d = $renewal_day->format('d');

				// XXX: Special case for next_box = '2016-07'
				if ($renewal_day->format('Y-m') === '2016-05' && $customer->get_meta('next_box') === '2016-07') {
					WP_CLI::debug("\tOrder will be postponed until Late June.");
					$renewal_y = '2016';
					$renewal_m = '06';
					$renewal_d = '20';
				}

				// XXX: Special case for next_box = '2016-08'
				if ($renewal_day->format('Y-m') === '2016-06' && $customer->get_meta('next_box') === '2016-08') {
					WP_CLI::debug("\tOrder will be postponed until Late July.");
					$renewal_y = '2016';
					$renewal_m = '07';
					$renewal_d = '20';
				}

				// XXX: Special case for next_box = '2016-09'
				if ($renewal_day->format('Y-m') === '2016-07' && $customer->get_meta('next_box') === '2016-09') {
					WP_CLI::debug("\tOrder will be postponed until Late August.");
					$renewal_y = '2016';
					$renewal_m = '08';
					$renewal_d = '20';
				}

				// Take the H:M:S from old $renewal_date, but the day from $renewal_day.
				$new_date = clone $renewal_date;
				$new_date->setDate($renewal_y, $renewal_m, $renewal_d);

				if ($this->options->verbose)
					WP_CLI::debug(var_export(array('new'=>$new_date,'old'=>$renewal_date), true));

				if (!$this->options->auto && $renewal_date >= $new_date) {
					WP_CLI::debug("\t\tRenewal too far out, doesn't need to be adjusted.");
					continue;
				} else {
					WP_CLI::debug("\t\tExisting renewal is incorrect, adjusting.");
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
					$results[] = array(
						'id' => $user_id,
						'sub_id' => $sub_id,
						'type' => $customer->get_subscription_type(),
						'earned' => $earned,
						'boxes' => $boxes,
						'old_renewal' => $original_renewal->format('Y-m-d H:i:s'),
						'new_renewal' => $new_date->format('Y-m-d H:i:s'),
						'errors' => NULL,
					);
				}
			}

		}

		if (sizeof($results))
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
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
	 * Generates the next order for a given subscriber. Requires you to list
	 * the month the order is intended for.
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
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--reverse]
	 * : Iterate users in reverse order.
	 *
	 * [--rush]
	 * : Only generate rush orders. Skip regular orders. Renewal cutoff does not apply for rush orders.
	 *
	 * [--month=<month>]
	 * : Month for which to generate the order. Format like '2016-05'.
	 *   Defaults to next month (because we usually generate orders ahead of the month).
	 *
	 * [--date=<date>]
	 * : Date on which to generate the order. This will actually check to see if the
	 *   user had an active subscription as of this date, which allows for back-generation
	 *   of orders, even if sub has expired as of the current moment.
	 *   Should be in iso 8601 format. Defaults to right now.
	 *
	 * [--renewal-cutoff=<renewal-cutoff>]
	 * : Last date on which a renewal can be and still have an order generated. Defaults to
	 *   the 20th of the previous month to the month option.  
	 *
	 * [--pretend]
	 * : Don't do anything, just print out what we would have done.
	 *
	 * [--force]
	 * : Create the next order for the customer, even if they already have an order this month.
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
	 *     wp cb generate_order 167
	 */
	function generate_order( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array(
			'user_id', 'orders', 'skus', 'credits', 'debits', 'action', 'reason', 'new_sku', 'error'
		);
		$date_string = CBWoo::format_DateTime_for_api($this->options->date);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("User $user_id");

			$skus = array();
			$orders = array();

			foreach ($customer->get_orders() as $order) {
				$orders[] = $order->id;
				foreach ($order->line_items as $line) {
					if ($line->sku) {
						$skus[] = $line->sku;
					}
				}
			}

			$r = $customer->calculate_box_credits($this->options->verbose);
			$credits = $r['credits'];
			$revenue = $r['revenue'];
			$debits = $customer->calculate_box_debits($this->options->verbose);

			$latest_renewal = end($customer->get_subscription_orders());
			$latest_renewal_date = CBWoo::parse_date_from_api($latest_renewal->created_at);

			$sub_id = $latest_renewal->id;
			$udata = get_userdata($customer->get_user_id())->data;
			$name = $udata->display_name;
			$email = $udata->user_email;

			// Reasons to not generate an order

			if ($this->options->rush && !$customer->has_outstanding_rush_order()) {
				WP_CLI::debug("\tNo outstanding rush order.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'not rush order',
					'new_sku' => NULL, 
					'error' => NULL
				);
				continue;
			}

			if ($this->options->verbose) {
				WP_CLI::debug(var_export(array('latest_renewal_date'=>$latest_renewal_date,'renewal_cutoff'=>$this->options->renewal_cutoff), true));
			}

			if (!$this->options->rush && $latest_renewal_date > $this->options->renewal_cutoff) {
				WP_CLI::debug("\tRenewal was too late.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'renewal too late',
					'new_sku' => NULL, 
					'error' => NULL
				);
				continue;
			}

			if ($debits >= $credits) {
				WP_CLI::debug("\tCustomer does not have any box credits.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'no box credit',
					'new_sku' => NULL, 
					'error' => NULL
				);
				continue;
			}

			if (!$this->options->force && (
				$customer->has_box_order_this_month($this->options->month)
			)) {
				WP_CLI::debug("\tCustomer already has a valid order this month.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'existing box order',
					'new_sku' => NULL, 
					'error' => NULL
				);
				continue;
			} 
			// XXX: Special case for next_box = '2016-07'
			if (!$this->options->rush && $this->options->month->format('Y-m') === '2016-06' && $customer->get_meta('next_box') === '2016-07') {
				WP_CLI::debug("\tOrder should be postponed until July.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'user postponed',
					'new_sku' => NULL, 
					'error' => NULL
				);
				continue;
			}
			// XXX: Special case for next_box = '2016-08'
			if (!$this->options->rush && $this->options->month->format('Y-m') === '2016-07' && $customer->get_meta('next_box') === '2016-08') {
				WP_CLI::debug("\tOrder should be postponed until August.");
				$results[] = array(
					'user_id' => $user_id,
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'user postponed',
					'new_sku' => NULL,
					'error' => NULL
				);
				continue;
			}
			// XXX: Special case for next_box = '2016-09'
			if (!$this->options->rush && $this->options->month->format('Y-m') === '2016-08' && $customer->get_meta('next_box') === '2016-09') {
				WP_CLI::debug("\tOrder should be postponed until September.");
				$results[] = array(
					'user_id' => $user_id,
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'user postponed',
					'new_sku' => NULL,
					'error' => NULL
				);
				continue;
			}

			$new_sku = $customer->get_next_box_sku($this->options->month, $version='v2');
			try {
				$next_order = $customer->next_order_data(
					$this->options->month, $this->options->date
				);
				WP_CLI::debug("\tWould use sku: " . $new_sku);
			} catch (Exception $e) {
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'error',
					'new_sku' => $new_sku, 
					'error' => $e->getMessage()
				);

				if ($this->options->rush) {
					CB::post_to_slack("- Missing data: <https://www.getchallengebox.com/wp-admin/post.php?post=$sub_id&action=edit|#$sub_id> from *$name* &lt;$email&gt;. Partial sku: $new_sku", 'rush-orders');
				}

				continue;
			}
			WP_CLI::debug("\tGenerating order");
			if ($this->options->verbose) {
				WP_CLI::debug("\tNext order: " . var_export($next_order, true));
			}

			if (!$this->options->pretend) {
				try {
					$response = $this->api->create_order($next_order);
					WP_CLI::debug("\tCreated new order");
					if ($this->options->rush) {
						$oid = $response->order->id;
						CB::post_to_slack("Rush order <https://www.getchallengebox.com/wp-admin/post.php?post=$oid&action=edit|#$oid> from *$name* &lt;$email&gt; (renewal <https://www.getchallengebox.com/wp-admin/post.php?post=$sub_id&action=edit|#$sub_id> sku $new_sku)", 'rush-orders');
					}

				} catch (Exception $e) {
					$results[] = array(
						'user_id' => $user_id, 
						'orders' => implode(" ", $orders),
						'skus' => implode(" ", $skus),
						'credits' => $credits, 
						'debits' => $debits, 
						'action' => 'error',
						'reason' => 'error',
						'new_sku' => $new_sku, 
						'error' => $e->getMessage()
					);
				}
				if ($this->options->verbose) {
					WP_CLI::debug("\tResponse: " . var_export($response, true));
				}
			}
			$results[] = array(
				'user_id' => $user_id, 
				'orders' => implode(" ", $orders),
				'skus' => implode(" ", $skus),
				'credits' => $credits, 
				'debits' => $debits, 
				'action' => 'created order',
				'reason' => NULL,
				'new_sku' => $new_sku, 
				'error' => NULL
			);
		}
		if (sizeof($results)) {
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
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
	 * Sets a user's box credit.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to check.
	 *
	 * [--credit=<credit>]
	 * : Amount of box credit to set. Defaults to 1.
	 *
	 * [--adjustment=<adjustment>]
	 * : Amount of box credit initial adjustment to set. Defaults to 0.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb box_credit 167 --credit=1 --adjustment=-11
	 */
	function box_credit( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("User $user_id");
			if (false !== $this->options->credit) {
				$customer->set_meta('extra_box_credits', $this->options->credit);
			}
			if (false !== $this->options->adjustment) {
				$customer->set_meta('box_credit_adjustment', $this->options->adjustment);
			}
		}
	}

	/**
	 * Corrects the sku of a processing order if that sku is the wrong month.
	 *
	 * ## OPTIONS
	 *
	 * <order_id>...
	 * : The order id(s) to change.
	 *
	 * [--sku=<sku>]
	 * : Use a specific sku.
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
	 *     wp cb correct_sku 1039
	 */
	function correct_sku( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		foreach ($args as $order_id) {
			$order = $this->api->get_order($order_id);
			WP_CLI::debug("Order $order_id");

			$current_sku = CBWoo::extract_order_sku($order);
			if ($this->options->sku) {
				$new_sku = $this->options->sku;
			} else {
				if ($order->total > 50) {
					$new_sku = $current_sku . '_3m';
				}
				if ($order->total > 120) {
					$new_sku = $current_sku . '_12m';
				}
			}
			$new_product = $this->api->get_product_by_sku($new_sku);
			$old_line_item = NULL;

			// Create new order object, marking the old line item's product id as NULL,
			// which tells the woo api to delete it.
			$new_order = array(
				'line_items' => array_map(
					function ($line_item) use ($current_sku, $new_sku, $new_product, &$old_line_item) {
						$new_line_item = (array) clone $line_item;
						if ($new_line_item['sku'] == $current_sku) {
							$new_line_item['product_id'] = NULL;
							$old_line_item = $new_line_item;
						}
						unset($new_line_item['meta']);
						return (array) $new_line_item;
					},
					$order->line_items
				)
			);

			// Add the new line item, copying the old, but using the new product id
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
				$response = $this->api->update_order($order->id, $new_order);
				WP_CLI::debug("\tUpdate order response: " . var_export($response, true));
			} 
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
	 * [--flatten]
	 * : Display result as array rather than object.
	 *
	 * ## EXAMPLES
	 *     wp cb subscription 6691
	 */
	function subscription( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		list( $id ) = $args;
		$sub = $this->api->get_subscription($id);
		if ($this->options->flatten) {
			$result = json_decode(json_encode($sub), true);
		} else {
			$result = $sub;
		}
		WP_CLI::line(var_export($result, true));
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
	 * [--month]
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

		$month_start = clone $this->options->month;
		$month_start->setTime(0,0);
		$month_end = clone $month_start; $month_end->modify('last day of');
		$month = $month_start->format('Y-m');
		//$last_month_start = clone $month_start; $last_month_start->modify('first day of last month');
		//$last_month_end = clone $month_start; $last_month_end->modify('last day of last month');
		$mrr_key = 'mrr_' . $month;
		$revenue_key = 'revenue_' . $month;
		$revenue_sub_key = 'revenue_sub_' . $month;
		$revenue_single_key = 'revenue_single_' . $month;
		$revenue_shop_key = 'revenue_shop_' . $month;
		$columns = array('id', 'cohort', 'first_sub', $mrr_key, $revenue_key, $revenue_sub_key, $revenue_single_key, $revenue_shop_key, 'error');

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
				$revenue = $customer->revenue_during_period($month_start, $month_end);

				if (! $this->options->pretend) {
					$customer->set_meta('cohort', $registered->format('Y-m'));
					$customer->set_meta($mrr_key, $mrr);
					$customer->set_meta($revenue_key, $revenue['total']);
					$customer->set_meta($revenue_sub_key, $revenue['sub']);
					$customer->set_meta($revenue_single_key, $revenue['single']);
					$customer->set_meta($revenue_shop_key, $revenue['shop']);
				}

				array_push($results, array(
					'id' => $user_id,
					'cohort' => $registered->format('Y-m'),
					'first_sub' => $cohort,
					$mrr_key => $mrr,
					$revenue_key => $revenue['total'],
					$revenue_sub_key => $revenue['sub'],
					$revenue_single_key => $revenue['single'],
					$revenue_shop_key => $revenue['shop'],
					'error' => NULL,
				));

			} catch (Exception $e) {
				WP_CLI::debug("\tError: " . $e->getMessage());	
				array_push($results, array(
					'id' => $user_id,
					'cohort' => isset($registered) ? $registered->format('Y-m') : NULL,
					'first_sub' => isset($cohort) ? $cohort : NULL,
					$mrr_key => isset($mrr) ? $mrr : NULL,
					$revenue_key => isset($revenue) ? $revenue : NULL,
					$revenue_sub_key => isset($revenue) ? $revenue['sub'] : NULL,
					$revenue_single_key => isset($revenue) ? $revenue['single'] : NULL,
					$revenue_shop_key => isset($revenue) ? $revenue['shop'] : NULL,
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
	 * [--bonus]
	 * : Apply 2x bonus points for this month.
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
						if ($this->options->bonus) {
							WC_Points_Rewards_Manager::increase_points($user_id, $difference, 'double-points', $month_start);
						}
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

	/**
	 * Fetch fitbit time series data.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * --series=<series>
	 * : Name of the series to fetch.
	 *  ---
	 *  options:
	 *    - caloriesIn
	 *    - water
	 *    - caloriesOut
	 *    - steps
	 *    - distance
	 *    - floors
	 *    - elevation
	 *    - minutesSedentary
	 *    - minutesLightlyActive
	 *    - minutesFairlyActive
	 *    - minutesVeryActive
	 *    - activityCalories
	 *  ---
	 *
	 * --date=<date>
	 * : The year and month to check. (i.e. 2016-04). 
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
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
	 *     wp cb fitbit_data 167 --series=water --date=2016-04
	 */
	function fitbit_data( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		$series = $this->options->series;
		$results = array();
		$columns = array('id');

		$month_start = clone $this->options->date;
		$month_start->setTime(0,0);
		$month_end = clone $month_start; $month_end->modify('last day of');
		$month = $month_start->format('Y-m');

		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id.");
			$customer = new CBCustomer($user_id);
			$data = $customer->fitbit()->getTimeSeries($series, $month_start, $month_end);
			// Turn keys into strings
			$data = array_combine(array_map(function ($s) { return 'd' . $s; }, array_keys($data)), $data);
			$data = array_merge(array('id'=>$user_id), $data);
			$results[] = $data;
			$columns = array_unique(array_merge($columns, array_keys($data)));
		}
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Generate skus for product variations.
	 *
	 * Uses the parent sku, plus attributes marked for variation, in the order they appear
	 * in the admin interface.
	 *
	 * ## OPTIONS
	 *
	 * [<product_id>...]
	 * : The product id(s) to calculate.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb generate_skus 8514
	 */
	function generate_skus( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		foreach ($args as $product_id) {
			WP_CLI::debug("Product $product_id");
			$product = $this->api->get_product($product_id);
			foreach ($product->variations as $variation) {

				$atts = CBWoo::extract_attributes($variation->attributes);
				$sku = '' . $product->sku;

				// Add in a comonent for each attribute marked to use in variations
				foreach ($product->attributes as $pat) {

					// Only use attributes in sku that are marked to use in variations
					if (! $pat->variation) continue;

					$value = $atts[$pat->slug];
					if (isset($value)) {
						// Special case for diet
						if ('diet' === $pat->slug && 'no-restrictions' === $value) {
							// Don't add anything on the sku
						} else {
							$sku .= '_' . $atts[$pat->slug];
						}
					}
				}

				// Prep update
				$variation_id = $variation->id;
				$update = array('product' => array('sku' => $sku));
				WP_CLI::debug("\tVariation $variation_id -> sku $sku");
				if ($this->options->verbose) WP_CLI::debug(var_export($update, true));

				// Apply update
				if (! $this->options->pretend) {
					$result = $this->api->update_product($variation_id, $update);
					if ($this->options->verbose) WP_CLI::debug(var_export($result, true));
				}
			}
		}
	}

	/**
	 * Prints out product data available.
	 *
	 * ## OPTIONS
	 *
	 * [<product_id>...]
	 * : The product id(s) to show.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb product 8514
	 */
	function product( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		foreach ($args as $product_id) {
			$product = $this->api->get_product($product_id);
			var_dump($product);
			foreach ($product->attributes as $att) {
				var_dump($att);
			}
		}
	}

	/**
	 * Prints out product attributes.
	 */
	function product_attributes( $args, $assoc_args ) {
		var_dump($this->api->get_attributes());
	}

	/**
	 * Migrates old v2 style subscriptions to ones that don't generate box orders.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to adjust renewal date.
	 *
	 * [--all]
	 * : Adjust dates for all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually migrate, just print out what we'd do.
	 *
	 * [--force]
	 * : Even if skus already match, re-migrate sub. (One use case is correcting sub line
	 *   item description.)
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
	 *     wp cb migrate_subscriptions 167
	 */
	function migrate_subscriptions($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array('user_id', 'sub_id', 'current_sku', 'new_sku', 'error');

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("Synchronizing $user_id");

			if (sizeof($customer->get_subscriptions()) == 0) {
				WP_CLI::debug("\tNo subscriptions. Skipping.");
				continue;
			}

			// Convert subscriptions
			foreach ($customer->get_subscriptions() as $sub) {

				$sub_id = $sub->id;
				$current_sku = $new_sku = $e = NULL;

				try {
					WP_CLI::debug("\tSubscription $sub_id...");

					$new_sub_type = CBWoo::extract_subscription_type($sub);
					if (!$new_sub_type) {
						WP_CLI::debug("\t\tCan't identify subscription type. Skipping.");
						continue;
					}

					// Figure out what we're migrating to
					$current_sku = CBWoo::extract_order_sku($sub);
					$new_sku = array(
						'3 Month' => 'subscription_3month',
						'12 Month' => 'subscription_12month',
						'Month to Month' => 'subscription_monthly',
					)[$new_sub_type];

					if (!$this->options->force && $new_sku === $current_sku) {
						WP_CLI::debug("\t\tSkus already match, skipping.");
						continue;
					}

					$new_product = $this->api->get_product_by_sku($new_sku);

					// Create the new subscription by:
					// - Go through each line item in the old subscription
					// - Remove the 'meta' seciton
					// - For the one contaning the old sku, set the product id to null to cause
					//   the API to remove it.
					// - Create a new line item that copies the quantity, price, total, etc, from
					//   the old line item, but has the product id for the new item. This line is
					//   the replacement for the line with the old sku.
					// Yeah, this is weird, but it's how WooCommerce API works.
					$old_line_item = NULL;
					$new_sub = array(
						'line_items' => array_map(
							function ($line_item) use ($current_sku, $new_sku, $new_product, &$old_line_item) {
								$new_line_item = (array) clone $line_item;
								if ($new_line_item['sku'] == $current_sku) {
									$new_line_item['product_id'] = NULL;
									$old_line_item = $new_line_item;
								}
								unset($new_line_item['meta']);
								return (array) $new_line_item;
							},
							$sub->line_items
						)
					);
					array_push($new_sub['line_items'], array(
						'id' => NULL,
						'product_id' => $new_product->id,
						'quantity' => $old_line_item['quantity'],
						'price' => $old_line_item['price'],
						'subtotal' => $old_line_item['subtotal'],
						'subtotal_tax' => $old_line_item['subtotal_tax'],
						'total' => $old_line_item['total'],
						'total_tax' => $old_line_item['total_tax'],
					));
					WP_CLI::debug(var_export($new_sub, true));

					// Update the subscription
					if (!$this->options->pretend) {
						$result = $this->api->update_subscription($sub->id, array('subscription' => $new_sub));
						if ($this->options->verbose)
							WP_CLI::debug("\t\tResult: " . var_export($result, true));
					}
				} catch (Exception $e) {
				}

				$results[] = array(
					'user_id' => $user_id,
					'sub_id' => $sub_id,
					'current_sku' => $current_sku,
					'new_sku' => $new_sku,
					'error' => $e
				);
			}
		}

		if (sizeof($results))
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Marks every 'processing' subscription order as 'completed'.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to mark orders.
	 *
	 * [--all]
	 * : Mark orders for all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually mark, just print out what we'd do.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb mark_subscription_orders_as_completed 167
	 */
	function mark_subscription_orders_as_completed($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		/*
		$results = array();
		$columns = array(
			'user_id', 'sub_id', 'order_id', 'status_before', 'status_after'
		);
		*/

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("User $user_id");

			foreach ($customer->get_subscription_orders() as $order) {
				$oid = $order->id;
				WP_CLI::debug("\tOrder $oid");
				if ('completed' === $order->status) {
					WP_CLI::debug("\t\t-> completed");
					continue;
				}
				$new_order = array('order' => array('status' => 'completed'));
				WP_CLI::debug("\t\t-> marking...");
				if ($this->options->verbose) {
					WP_CLI::debug("\tnew" . var_export($new_order, true));
				}
				if (! $this->options->pretend) {
					$response = $this->api->update_order($order->id, $new_order);
					if ($this->options->verbose) {
						WP_CLI::debug("\tUpdate order response: " . var_export($response, true));
					}
				}
			}
		}
	}

	/**
	 * Command reconciles box credits vs debits.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to reconcile credits.
	 *
	 * [--all]
	 * : Work on all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--reverse]
	 * : Iterate users in reverse order.
	 *
	 * [--pretend]
	 * : Don't actually work, just print out what we'd do.
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
	 *     wp cb reconcile_box_credits 167
	 */
	function reconcile_box_credits($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array(
			'user_id', 'orders', 'skus', 'credits', 'debits', 'owed', 'revenue', 'rpc', 'rpd', 'rpc_low', 'rpc_high', 'error', 'next_box', 'active_sub'
		);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("User $user_id");

			$skus = array();
			$orders = array();

			foreach ($customer->get_orders() as $order) {
				$orders[] = $order->id;
				foreach ($order->line_items as $line) {
					if ($line->sku) {
						$skus[] = $line->sku;
					}
				}
			}

			try { 

				$r = $customer->calculate_box_credits($this->options->verbose);
				$credits = $r['credits'];
				$revenue = $r['revenue'];
				$debits = $customer->calculate_box_debits($this->options->verbose);

				$results[] = array(
					'user_id' => $user_id,
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits,
					'debits' => $debits,
					'revenue' => $revenue,
					'rpc' => $credits ? $revenue/$credits : 0,
					'rpd' => $debits ? $revenue/$debits : 0,
					'rpc_low' => (bool) ($credits && ($credits ? $revenue/$credits : 0) < 15),
					'rpc_high' => (bool) ($credits && ($credits ? $revenue/$credits : 0) > 50),
					'owed' => $credits - $debits,
					'next_box' => $customer->get_meta('next_box'),
					'active_sub' => $customer->has_active_subscription(),
					'error' => null,
				);

			} catch (Exception $e) {

				$results[] = array(
					'user_id' => $user_id,
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => null,
					'debits' => null,
					'revenue' => null,
					'rpc' => null,
					'rpd' => null,
					'rpc_low' => null,
					'rpc_high' => null,
					'owed' => null,
					'next_box' => $customer->get_meta('next_box'),
					'active_sub' => $customer->has_active_subscription(),
					'error' => $e->getMessage(),
				);
			}
		}
		if (sizeof($results))
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Cancels bad orders
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to cancel orders.
	 *
	 * --date=<date>
	 * : Cutoff date after which an order should be canceled.
	 *
	 * [--all]
	 * : Work on all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--reverse]
	 * : Iterate users in reverse order.
	 *
	 * [--pretend]
	 * : Don't actually work, just print out what we'd do.
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
	 *     wp cb cancel_bad_orders 167
	 */
	function cancel_bad_orders($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array(
			'user_id', 'order_id', 'skus', 'renewal_date', 'order_date', 'action', 'reason', 'error'
		);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id);
			WP_CLI::debug("User $user_id");

			$latest_renewal = end($customer->get_subscription_orders());
			$latest_renewal_date = CBWoo::parse_date_from_api($latest_renewal->created_at);

			foreach ($customer->get_orders() as $order) {

				$order_id = $order->id;
				$reason = null;
				$action = null;
				$skus = array();
				foreach ($order->line_items as $line) {
					if ($line->sku) {
						$skus[] = $line->sku;
					}
				}
				$order_date = CBWoo::parse_date_from_api($order->created_at);

				WP_CLI::debug("\tOrder $order_id");

				if (!CBWoo::order_counts_as_box_debit($order)) {
					WP_CLI::debug("\t\tskipping, not a box.");
					$action = 'skip';
					continue;
				}
				if ('cancelled' === $order->status) {
					WP_CLI::debug("\t\talready canceled");
					$action = 'skip';
					continue;
				}
				if ('processing' !== $order->status) {
					WP_CLI::debug("\t\tnot processing");
					$action = 'skip';
					continue;
				}

				if (!$order->shipping_address || !$order->shipping_address->address_1) {
					$action = 'cancel';
					$reason = 'no shipping address';
				}
				else {
					if ($order_date > $this->options->date) {
						if ($latest_renewal_date <= $this->options->date) {
							$action = 'skip';
							$reason = 'renewal before cutoff';
						} else {
							$action = 'cancel';
							$reason = 'renewal after cutoff';
						}
					} else {
						$action = 'skip';
						$reason = 'order not after renewal';
						continue;
					}
				}

				if ('cancel' == $action) {
					WP_CLI::debug("\t\t$reason");
					try { 
						$new_order = array('order' => array('status' => 'cancelled'));
						WP_CLI::debug("\t\tcancelling...");
						if (! $this->options->pretend) {
							$response = $this->api->update_order($order->id, $new_order);
							if ($this->options->verbose) {
								WP_CLI::debug("\tUpdate order response: " . var_export($response, true));
							}
						}
						$results[] = array(
							'user_id' => $user_id,
							'order_id' => $order_id,
							'skus' => implode(" ", $skus),
							'renewal_date' => $latest_renewal_date->format('Y-m-d H:i:s'),
							'order_date' => $order_date->format('Y-m-d H:i:s'),
							'action' => $action,
							'reason' => $reason,
							'error' => null,
						);
					} catch (Exception $e) {
						$results[] = array(
							'user_id' => $user_id,
							'order_id' => $order_id,
							'skus' => implode(" ", $skus),
							'renewal_date' => $latest_renewal_date->format('Y-m-d H:i:s'),
							'order_date' => $order_date->format('Y-m-d H:i:s'),
							'action' => $action,
							'reason' => $reason,
							'error' => $e->getMessage(),
						);
					}

				} else {
					$results[] = array(
						'user_id' => $user_id,
						'order_id' => $order_id,
						'skus' => implode(" ", $skus),
						'renewal_date' => $latest_renewal_date->format('Y-m-d H:i:s'),
						'order_date' => $order_date->format('Y-m-d H:i:s'),
						'action' => $action,
						'reason' => $reason,
						'error' => null,
					);
				}

			}
		}
		if (sizeof($results))
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Exports churn data.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb export_churn_data
	 */
	function export_churn_data( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		global $wpdb;
		$data = $wpdb->get_results("
			select 
				id, meta_key, meta_value
			from wp_users U 
			join wp_usermeta A 
				on A.user_id = U.id
			where
				   meta_key LIKE 'mrr_%' 
				or meta_key LIKE 'revenue_%'
				or meta_key = 'cohort'
			order by
				user_registered
			;
		");

		// Pivot the data so it is organized in rows keyed by the user id
		$user_data = array();
		$columns = array("id" => true);
		foreach ($data as $row) {
			if (!isset($columns[$row->meta_key])) $columns[$row->meta_key] = true;
			if (!isset($user_data[$row->id])) $user_data[$row->id] = array();
			$user_data[$row->id][$row->meta_key] = $row->meta_value;
		}

		// Render data as sorted rows
		$csv_rows = array();
		foreach ($user_data as $user_id => $user_row) {
			$user_row["id"] = $user_id;
			$row = array();
			foreach ($columns as $column => $true) {
				if (isset($user_row[$column])) {
					$row[] = $user_row[$column];
				} else {
					$row[] = NULL;
				}
			}
			$csv_rows[] = $row;
		}

		// Print it out
		fputcsv(STDOUT, array_keys($columns));
		foreach ($csv_rows as $row) {
			fputcsv(STDOUT, $row);
		}
	}

}

WP_CLI::add_command( 'cb', 'CBCmd' );

