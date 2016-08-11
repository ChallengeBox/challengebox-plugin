<?php

/**
 * API for accessing customer data.
 *
 * Uses WooCommerce REST API and Wordpress internal API as data sources.
 * Data is generated and cached on demand.
 */
class CBCustomer {

	private $user_id;
	private $api;
	private $fitbit;
	private $orders;
	private $subscriptions;
	private $metadata;
	private $order_notes;

	/**
	 * Simply pass in the user id to construct.
	 *
	 * No database/API calls are made unless you call a function that
	 * begins with "get_", in which case calls are made and the results
	 * cached in this object for future calls.
	 *
	 * # OPTIONS
	 *
	 * <user_id>
	 * : The wordpress user id of the customer.
	 *
	 * # EXAMPLE
	 *
	 * $customer = new CBCustomer(167);
	 * $customer->get_orders();                // triggers a call to the woo api
	 * $customer->get_subscriptions();         // triggers another separate call to the woo api
	 * $customer->get_box_orders();            // no api calls, filters the data from get_orders()
	 * $customer->get_meta('clothing_gender'); // triggers a call to wordpress api
	 * $customer->get_meta('tshirt_size');     // no api calls, uses cached metadata from last get_meta()
	 *
	 */
	public function __construct($user_id) {
		$this->user_id = 0 + $user_id;
		$this->api = new CBWoo();
	}

	public function fitbit() {
		if (!isset($this->fitbit)) {
			$this->fitbit = new CBFitbitAPI($this->user_id);
		}
		return $this->fitbit;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	/**
	 * Returns the cache key for fitbit API calls.
	 */
	private function fitbit_cache_key($activity, $month_start, $month_end) {
		return implode(
			'_', array(
				'fitbit-cache-v1',
				$activity,
				$this->user_id,
				$month_start->format('Y-m-d'),
				$month_end->format('Y-m-d')
			)
		);
	}

	/**
	 * Clears cache for a specific month.
	 */
	public function clear_fitbit_cache($month_start, $month_end) {
		// Cached Fitbit API calls
		foreach (
			array(
				'minutesVeryActive',
				'minutesFairlyActive',
				'minutesLightlyActive',
				'water',
				'caloriesIn',
				'distance',
				'steps',
			) as $activity
		) {
			delete_transient($this->fitbit_cache_key($activity, $month_start, $month_end));
		}

		// Cached analysis result
		$this_month_key = implode(
			'_', array(
				'fitbit-data-v3',
				$this->user_id,
				$month_start->format('Y-m-d'),
				$month_end->format('Y-m-d')
			)
		);
		delete_transient($this_month_key);
	}

	public function inspect_fitbit_cache($month_start, $month_end) {
		// Cached Fitbit API calls
		$data = array();
		foreach (
			array(
				'minutesVeryActive',
				'minutesFairlyActive',
				'minutesLightlyActive',
				'water',
				'caloriesIn',
				'distance',
				'steps',
			) as $activity
		) {
			$key = $this->fitbit_cache_key($activity, $month_start, $month_end);
			$data[$key] = get_transient($key);
		}

		// Cached analysis result
		$this_month_key = implode(
			'_', array(
				'fitbit-data-v3',
				$this->user_id,
				$month_start->format('Y-m-d'),
				$month_end->format('Y-m-d')
			)
		);
		$data[$this_month_key] = get_transient($this_month_key);
		return $data;
	}

	/**
	 * Updates metadata variables in the user's account so other operations
	 * relying on these values can be done quickly.
	 *
	 * Values Written:
	 * - box_month_of_latest_order
	 * - active_subscriber
	 * - clothing_gender
	 * - tshirt_size 
	 * 
	 * This method will not overwrite the following keys unless $overwrite is
	 * set to true:
	 * - clothing_gender
	 * - tshirt_size
	 */
	public function update_metadata($overwrite = false, $local_only = false) {
		$clothing_gender = $this->get_meta('clothing_gender');
		$exceptions = array();
		if (!$clothing_gender || $overwrite) {
			try {
				$this->set_meta('clothing_gender', $this->estimate_clothing_gender(), $local_only);
			} catch (Exception $e) {
				$exceptions[] = $e;
			}
		}
		$tshirt_size = $this->get_meta('tshirt_size');
		if (!$tshirt_size || $overwrite) {
			try {
				$this->set_meta('tshirt_size', $this->estimate_tshirt_size(), $local_only);
			} catch (Exception $e) {
				$exceptions[] = $e;
			}
		}
		$this->set_meta('box_month_of_latest_order', $this->estimate_box_month(), $local_only);

		if ($this->has_active_subscription()) {
			$sub = array_values($this->get_active_subscriptions())[0];
			$renewal_date = CBWoo::parse_date_from_api($sub->billing_schedule->next_payment_at);
			$this->set_meta('active_subscriber', 1, $local_only);
			$this->set_meta('subscription_status', $sub->status, $local_only);
			$this->set_meta('subscription_type', $this->get_subscription_type(), $local_only);
			$this->set_meta('renewal_date', $renewal_date, $local_only);
		} elseif ($this->has_subscription_on_hold()) {
			$sub = array_values($this->get_subscriptions_on_hold())[0];
			$this->set_meta('active_subscriber', 0, $local_only);
			$this->set_meta('subscription_status', $sub->status, $local_only);
			$this->set_meta('subscription_type', $this->get_subscription_type(), $local_only);
			$this->set_meta('renewal_date', null, $local_only);
		} elseif ($this->had_mrr_subscription()) {
			$this->set_meta('active_subscriber', 0, $local_only);
			$this->set_meta('subscription_status', 'canceled', $local_only);
			$this->set_meta('subscription_type', $this->get_subscription_type(), $local_only);
			$this->set_meta('renewal_date', null, $local_only);
		} else {
			$this->set_meta('active_subscriber', 0, $local_only);
			$this->set_meta('subscription_status', null, $local_only);
			$this->set_meta('subscription_type', null, $local_only);
			$this->set_meta('renewal_date', null, $local_only);
		}

		$failed_orders = $this->get_failed_orders();
		if (sizeof($failed_orders)) {
			$order = array_values($failed_orders)[0];
			$this->set_meta('has_failed_order', true, $local_only);
			$this->set_meta('failed_order_payment_url', wc_get_order($order->id)->get_checkout_payment_url(), $local_only);
		} else {
			$this->set_meta('has_failed_order', null, $local_only);
			$this->set_meta('failed_order_payment_url', null, $local_only);
		}

		$this->set_meta('wc_points_balance', WC_Points_Rewards_Manager::get_users_points($this->user_id), $local_only);

		$this->set_meta('fitbit_oauth_status', $this->get_fitbit_oauth_status(), $local_only);
		$this->set_meta('has_rush_order', sizeof($this->get_rush_orders()) > 0, $local_only);

		$this->set_meta('mrr_cohort', $this->estimate_mrr_cohort(), $local_only);

		return $exceptions;
	}

	/**
	 * Returns subscriptions for which we should recognize revenue during the given period.
	 */
	public function get_mrr_subscriptions_during_period($start, $end) {
		return array_filter($this->get_subscriptions(), function ($sub) use ($start, $end) {

			if (!CBWoo::is_mrr_subscription($sub)) {
				return false;
			}

			$sub_start = CBWoo::parse_date_from_api($sub->billing_schedule->start_at);

			// Active subscriptions have an empty end_at parameter
			if ($sub->billing_schedule->end_at) {
				$sub_end = CBWoo::parse_date_from_api($sub->billing_schedule->end_at);
			} else {
				$sub_end = false;
			}

			// For ending subscriptions, don't count the period in which the end occurs.
			if ($sub_end) {
				return $sub_end > $end && $sub_start < $end;
			} else {
				return $sub_start < $end;
			}
		});
	}

	/**
	 * Returns orders placed during a given period.
	 */
	public function get_orders_during_period($start, $end) {
		return array_filter($this->get_orders(), function ($order) use ($start, $end) {
			$created = CBWoo::parse_date_from_api($order->created_at);
			return $created >= $start && $created < $end;
		});
	}


	/**
	 * Returns the start date of the earliest subscription. Can be used to determine cohort.
	 */
	public function earliest_subscription_date() {
		$earliest = new DateTime();
		foreach ($this->get_subscriptions() as $sub) {
			if ($sub->created_at) {
				$created = new DateTime($sub->created_at);
				if ($created < $earliest) {
					$earliest = $created;
				}
			}
		}
		return $earliest;
	}

	/**
	 * Returns the amount of monthly recurring revenue (in dollars) during
	 * the given period.
	 * Subscriptions that last n months count 1/n revenue per month.
	 */
	public function mrr_during_period($start, $end) {
		$revenue = 0;
		foreach ($this->get_mrr_subscriptions_during_period($start, $end) as $sub) {
			if ($sub->total) {
				switch (CBWoo::extract_subscription_type($sub)) {
					case '3 Month':
						$revenue += doubleval($sub->total) / 3.0;
						break;
					case '12 Month':
						$revenue += doubleval($sub->total) / 12.0;
						break;
					case 'Month to Month':
						$revenue += doubleval($sub->total);
						break;
					default:
						//throw new Exception('Unknown subscription type');
						break;
				}
			}
		}
		return $revenue;
	}

	/**
	 * Returns the amount of total revenue (in dollars, including non renewing) during
	 * the given period.
	 */
	public function revenue_during_period($start, $end) {
		$revenue = array(
			'total' => 0,
			'sub' => 0,
			'single' => 0,
			'shop' => 0,
		);
		foreach ($this->get_orders_during_period($start, $end) as $order) {
			$revenue['total'] += doubleval($order->total);
			if (
				(
					CBWoo::is_valid_box_order($order)
					&& !CBWoo::is_valid_single_box_order($order)
				)
					||
				CBWoo::is_subscription_order($order)
			) {
				$revenue['sub'] += doubleval($order->total);
			}
			elseif (CBWoo::is_valid_single_box_order($order)) {
				$revenue['single'] += doubleval($order->total);
			}
			else {
				$revenue['shop'] += doubleval($order->total);
			}
		}
		return $revenue;
	}

	/**
	 * Returns a cached assossiative array of the customer's wordpress metadata.
	 * All data is dereferenced, to save you a call to get_user_meta();
	 * If used with the $key option, returns just the value of that key.
	 * Keys with no data are returned as $default.
	 */
	public function get_meta($key = false, $default = NULL) {
		if (empty($this->metadata)) {
			// All non-empty data, dereferenced
			$got = get_user_meta($this->user_id); 
			$this->metadata = array_filter(array_map(
				function( $a ) { return maybe_unserialize($a[0]); },
				$got ? $got : array()
			));
		}
		if ($key) {
			return isset($this->metadata[$key]) ? $this->metadata[$key] : $default;
		}
		return $this->metadata;
	}

	/**
	 * Sets the specified metadata for the user. Only sets the value if it's different
	 * then current (cached) value. Updates the local cache.
	 * Returns true if the database was updated, false if not.
	 */
	public function set_meta($key, $value, $local_only = false) {
		if ($this->get_meta($key) !== $value) {
			if (!$local_only) {
				update_user_meta($this->user_id, $key, $value);
			}
			$this->metadata[$key] = $value;
			return true;
		}
		return false;
	}

	/**
	 * Returns customer's WooCommerce orders (not including subscriptions).
	 */
	public function get_orders() {
		if (empty($this->orders)) {
			$this->orders = $this->api->get_customer_orders($this->user_id);
		}
		return $this->orders;
	}
	public function get_orders_by_status() {
		return $this->api->arrange_orders_by_status($this->get_orders());
	}
	public function get_orders_shipped() {
		return array_filter(
			$this->get_orders(),
			function ($order) { return $this->order_was_shipped($order); }
		);
	}
	public function get_rush_orders() {
		return array_filter(
			$this->get_orders(),
			function ($order) { return CBWoo::order_is_rush($order); }
		);
	}
	public function get_failed_orders() {
		return array_filter(
			$this->get_orders(),
			function ($order) { return 'failed' === $order->status; }
		);
	}
	public function get_subscription_orders() {
		return array_filter(
			$this->get_orders(),
			function ($order) { return CBWoo::order_counts_as_box_credit($order); }
		);
	}

	/**
	 * Returns customer's orders that are valid boxes.
	 */
	public function get_box_orders($since = false) {
		return array_filter(
			$this->get_orders(),
			function ($o) use ($since) { 
				//$valid =  CBWoo::is_valid_box_order($o); 
				$valid =  CBWoo::order_counts_as_box_debit($o); 
				if ($since) {
					return $valid && CBWoo::parse_date_from_api($o->created_at) >= $since;
				} else {
					return $valid;
				}
			}
		);
	}
	public function get_box_orders_by_status($since = false) {
		return $this->api->arrange_orders_by_status($this->get_box_orders($since));
	}
	public function get_box_orders_shipped($since = false) {
		return array_filter(
			$this->get_box_orders($since),
			function ($order) { return $this->order_was_shipped($order); }
		);
	}
	/**
	 * Returns true if one of the orders that counts as a box credit is marked as a rush order,
	 * and no subsequent order marked as a box debit is found.
	 */
	public function has_outstanding_rush_order() {
		$found_rush = false;
		$has_outstanding = true;
		foreach ($this->get_orders() as $order) {
			if (!$found_rush && CBWoo::order_counts_as_box_credit($order) && CBWoo::order_is_rush($order)) {
				$found_rush = true;
			}
			if ($found_rush && CBWoo::order_counts_as_box_debit($order)) {
				$has_outstanding = false;
			}
		}
		return $found_rush && $has_outstanding;
	}

	/**
	 * Returns customer's WooCommerce subscriptions.
	 */
	public function get_subscriptions() {
		if (empty($this->subscriptions)) {
			$this->subscriptions = $this->api->get_customer_subscriptions($this->user_id);
		}
		return $this->subscriptions;
	}
	public function get_subscriptions_by_status() {
		return $this->api->arrange_subscriptions_by_status($this->get_subscriptions());
	}

	/**
	 * Returns customer's WooCommerce subscriptions that are active
	 * or active pending cancellation.
	 * If $as_of is set, it returns the subscriptions active as of that date.
	 */
	public function get_active_subscriptions($as_of = false) {
		if ($as_of) {
			return array_filter(
				$this->get_subscriptions(),
				function ($s) use ($as_of) {
					$start = CBWoo::parse_date_from_api($s->billing_schedule->start_at);
					$end = CBWoo::parse_date_from_api($s->billing_schedule->end_at);
					if ($start && $end) {
						return $as_of >= $start && $as_of < $end;
					}
					// Default to subscription status if we don't have an end date
					if ($as_of >= $start) {
						return 'active' == $s->status || 'pending-cancel' == $s->status;
					}
				}
			);
		}
		else {
			return array_filter(
				$this->get_subscriptions(),
				function ($s) {
					return 'active' == $s->status || 'pending-cancel' == $s->status;
				}
			);
		}
	}

	/**
	 * Returns customer's WooCommerce subscriptions that are on hold.
	 */
	public function get_subscriptions_on_hold($as_of = false) {
		return array_filter(
			$this->get_subscriptions(),
			function ($s) { return 'on-hold' == $s->status; }
		);
	}
	/**
	 * Returns customer's WooCommerce subscriptions that generate monthly
	 * recuring revenue.
	 */
	public function get_mrr_subscriptions() {
		return array_filter(
			$this->get_subscriptions(),
			function ($s) { return CBWoo::is_mrr_subscription($s); }
		);
	}


	/**
	 * Returns true if customer has a valid box order in the given
	 * month.
	 *
	 * # OPTIONS
	 *
	 * [<date>]
	 * : Any DateTime object in the month you want to check.
	 *   Defaults to current month.
	 * 
	 */
	public function has_box_order_this_month($date = false) {
		if (empty($date)) {
			$date = new DateTime();
		}
		$this_month = $date->format('Y-m');
		return CB::any(array_map(
			function($order) use ($this_month) {
				return (new DateTime($order->created_at))->format('Y-m') == $this_month && 'cancelled' !== $order->status;
			},
			$this->get_box_orders()
		));
	}

	/**
	 * Return order notes for the given order id.
	 * NOTE: This probably would be in the CBOrder class if we make one.
	 */
	public function get_order_notes($order_id) {
		if (empty($this->order_notes[$order_id])) {
			$this->order_notes[$order_id] = $this->api->get_order_notes($order_id);
		}
		return $this->order_notes[$order_id];
	}

	/**
	 * Returns true if the order has a note that contains the word 'shipped'
	 * or if the order status is 'completed'.
	 * NOTE: This probably would be in the CBOrder class if we make one.
	 */
	public function order_was_shipped($order) {
		return (bool) (
			CB::any(array_filter(
				$this->get_order_notes($order->id), 
				function ($note) {
					return strpos(strtolower($note->note), 'shipped') !== false;
				}
			))
			|| 'completed' == $order->status
		);
	}

	private function get_sorted_gender_and_size_candidates() {
		// Guess gender from a list of candidates, including orders and subscriptions
		$candidates = array_filter(
			// Step 1: merge orders and subscriptions
			array_merge(
				$this->get_orders(),
				$this->get_subscriptions()
			),
			// Step 2: drop everything that doesn't look like a box
			function ($candidate) {
				return CBWoo::is_valid_box_order($candidate);
			}
		);
		// Get the most recent one
		usort($candidates, function ($a, $b) { 
			return (
				(
					new DateTime($a->created_at)
				)->diff(
					new DateTime($b->created_at)
				)->format('%d') + 0
			);
		});
		return $candidates;
	}

	/**
	 * Estimates from previous orders what the prefered clothing gender would be.
	 */
	public function estimate_clothing_gender() {
		$candidates = $this->get_sorted_gender_and_size_candidates();
		if (0 == sizeof($candidates)) {
			throw new Exception('Cannot estimate clothing gender for customer #' . $this->user_id);
		}
		switch(strtolower(CBWoo::parse_order_options($candidates[0])->gender)[0]) {
			case 'm':
				return 'male';
			case 'f':
				return 'female';
			default:
				throw new Exception('Cannot estimate clothing gender for customer #' . $this->user_id);
		}
	}

	/**
	 * Estimates from previous orders what the prefered t-shirt size would be.
	 */
	public function estimate_tshirt_size() {
		$candidates = $this->get_sorted_gender_and_size_candidates();
		if (0 == sizeof($candidates)) {
			throw new Exception('Cannot estimate tshirt size for customer #' . $this->user_id);
		}
		return CBWoo::parse_order_options($candidates[0])->size;
	}

	/**
	 * Estimates from previous orders what was the month of the latest box shipped to customer.
	 */
	public function estimate_box_month() {
		return sizeof(
			array_filter(
				$this->get_box_orders(),
				function ($order) { return $this->order_was_shipped($order); }
			)
		);
	}
	/**
	 * Estimates when the user started becoming a recuring subscriber.
	 */
	public function estimate_mrr_cohort() {
		$subs = $this->get_mrr_subscriptions();
		if (sizeof($subs)) {
			$earliest = new DateTime();
			$found = false;
			foreach ($subs as $sub) {
				$sub_start = CBWoo::parse_date_from_api($sub->billing_schedule->start_at);
				if ($sub_start < $earliest) {
					$found = true;
					$earliest = $sub_start;
				}
			}
			if ($found) {
				return $earliest->format('Y-m');
			}
		}
	}

	/**
	 * Returns true if the customer has an active subscription or one that is pending
	 * cancellation and not cancelled yet.
	 * Include $as_of parameter to determine if there was an active subscription as
	 * of the given DateTime object.
	 */
	public function has_active_subscription($as_of = false) {
		return (bool) sizeof($this->get_active_subscriptions($as_of));
	}
	/**
	 * Returns true if the customer has a subscription on-hold.
	 */
	public function has_subscription_on_hold() {
		return (bool) sizeof($this->get_subscriptions_on_hold());
	}
	/**
	 * Returns true if the customer had any mrr subscription at any time.
	 */
	public function had_mrr_subscription() {
		return (bool) sizeof(array_filter(
			$this->get_subscriptions(), 
			function($sub) { return CBWoo::is_mrr_subscription($sub); }
		));
	}

	/**
	 * Returns 'Month to Month', '3 Month', or '12 Month', based on the line item
	 * name from the first currently active subscription. Returns false if no
	 * subscription is found.
	 */
	public function get_subscription_type($as_of = false) {
		$sub = array_values($this->get_subscriptions($as_of))[0];
		return CBWoo::extract_subscription_type($sub);
	}

	//
	// Functions that rely on up-to-date metadata
	//

	/**
	 * Returns the sku of customer's last shipped box.
	 * NOTE: Relies on up-to-date metadata.
	 */
	public function get_last_box_sku($ship_month, $version = 'v1') {
		return CBWoo::format_sku(
			$ship_month,
			$this->get_meta('box_month_of_latest_order'),
			$this->get_meta('clothing_gender'),
			$this->get_meta('tshirt_size'),
			$version,
			!empty($this->get_meta('fitness_diet'))
		);
	}

	/**
	 * Returns customer's next box in the series.
	 * NOTE: Relies on up-to-date metadata.
	 */
	public function get_next_box_sku($ship_month, $version = 'v2') {
		return CBWoo::format_sku(
			$ship_month,
			$this->get_meta('box_month_of_latest_order') + 1,
			$this->get_meta('clothing_gender'),
			$this->get_meta('tshirt_size'),
			$version,
			!empty($this->get_meta('fitness_diet'))
		);
	}

	/**
	 * Returns customer's single box sku.
	 * NOTE: Relies on up-to-date metadata.
	 */
	public function get_single_box_sku($ship_month, $version = 'single-v2') {
		return CBWoo::format_sku(
			$ship_month,
			$this->get_meta('box_month_of_latest_order'),
			$this->get_meta('clothing_gender'),
			$this->get_meta('tshirt_size'),
			$version,
			!empty($this->get_meta('fitness_diet'))
		);
	}

	/**
	 * Generates the request data for the next order in the customer's sequence.
	 */
	public function next_order_data($ship_month, $date = false, $rush = false) {
		if (!$date) {
			$date = new DateTime();
		}
		$subscription = current($this->get_active_subscriptions($date));
		if (!$subscription) {
			$subscription = current($this->get_subscriptions($date));
		}
		$sku = $this->get_next_box_sku($ship_month, $version='v2');
		$product = $this->api->get_product_by_sku($sku);
		$order = array(
			'status' => 'processing',
			'created_at' => CBWoo::format_DateTime_for_api($date),
			'customer_id' => $this->user_id,
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
		// Add Rush if specified
		if ($rush) {
			$order['fee_lines'] = array(array('id' => 15409));
		}
		// Default to credit card if subscription doesn't have payment info
		if (empty($order['payment_details']['method_id'])) {
			$order['payment_details']['method_id'] = 'stripe';
		}
		if (empty($order['payment_details']['method_title'])) {
			$order['payment_details']['method_title'] = 'Credit Card';
		}
		$order['payment_details']['paid'] = true;

		// Handle diet notes
		if (!empty($this->get_meta('fitness_diet'))) {
			$order['note'] = 'Dietary Restrictions: ' . $this->get_meta('fitness_diet');
		}
		return array('order' => $order);
	}

	/**
	 * Returns true if customer has connected their fitbit.
	 */
	public function has_connected_fitbit() {
		return ((
				$this->get_meta('fitpress_fitbit_token')
					&& $this->get_meta('fitpress_fitbit_secret')
			) || (
				$this->get_meta('cb-fitbit-oauth2-v1_access-token')
		));
	}

	/**
	 * Returns which oauth version customer is using ('oauth1' or 'oauth2'),
	 * 'none' if they are not connected or 'error' if there is a problem.
	 */
	public function get_fitbit_oauth_status() {
		try {
			if ($this->fitbit()->has_v2()) {
				return 'oauth2';
			} elseif ($this->fitbit()->has_v1()) {
				return 'oauth1';
			} else {
				return 'none';
			}
		} catch (Exception $e) {
			return 'error';
		}
	}


	/**
	 * Returns data to be added to segment identify() calls.
	 * Let's keep this short for now and only use cached metadata.
	 */
	public function get_segment_data($revenue_fields = false) {
		$user = get_user_by('ID', $this->get_user_id());
		$data = array(
			'last_shipped_box' => $this->get_meta('box_month_of_latest_order'),
			'clothing_gender' => $this->get_meta('clothing_gender'),
			'tshirt_size' => $this->get_meta('tshirt_size'),
			'pant_size' => $this->get_meta('pant_size'),
			'glove_size' => $this->get_meta('glove_size'),
			'sock_size' => $this->get_meta('sock_size'),
			'active_subscriber' => $this->get_meta('active_subscriber'),
			'subscription_status' => $this->get_meta('subscription_status'),
			'subscription_type' => $this->get_meta('subscription_type'),
			'renewal_date' => $this->get_meta('renewal_date') ? $this->get_meta('renewal_date')->format('U') : null,
			//'challenge_points' => $this->get_meta('cb-points-v1') ? intval($this->get_meta('cb-points-v1')) : 0,
			'challenge_points' => $this->get_meta('wc_points_balance') ? intval($this->get_meta('wc_points_balance')) : 0,
			'cp_2016_02' => intval($this->get_meta('cb-points-month-v1_2016-02', 0)),
			'cp_2016_03' => intval($this->get_meta('cb-points-month-v1_2016-03', 0)),
			'cp_2016_04' => intval($this->get_meta('cb-points-month-v1_2016-04', 0)),
			'cp_2016_05' => intval($this->get_meta('cb-points-month-v1_2016-05', 0)),
			'met_goal_2016_02' => $this->get_meta('cb-points-detail-v1_2016-02_personal-goals', 0) == 40,
			'met_goal_2016_03' => $this->get_meta('cb-points-detail-v1_2016-03_personal-goals', 0) == 40,
			'met_goal_2016_04' => $this->get_meta('cb-points-detail-v1_2016-04_personal-goals', 0) == 40,
			'met_goal_2016_05' => $this->get_meta('cb-points-detail-v1_2016-05_personal-goals', 0) == 40,
			'special_segment' => $this->get_meta('special_segment'),
			'connected_fitbit' => $this->has_connected_fitbit(),
			'fitbit_oauth_status' => $this->get_meta('fitbit_oauth_status'),
			'fitness_goal' => $this->get_meta('fitness_goal1'),
			'next_box' => $this->get_meta('next_box'),
			'has_rush_order' => $this->get_meta('has_rush_order'),
			'has_failed_order' => $this->get_meta('has_failed_order'),
			'failed_order_payment_url' => $this->get_meta('failed_order_payment_url'),
			'registered_after_june' => new DateTime($user->user_registered) > new DateTime('2016-06-01'),
		);

		if ($revenue_fields) {
			$data = array_merge($data, array(
				'mrr_cohort' => $this->get_meta('mrr_cohort'),
				'mrr_2016-01' => doubleval($this->get_meta('mrr_2016-01', 0)),
				'mrr_2016-02' => doubleval($this->get_meta('mrr_2016-02', 0)),
				'mrr_2016-03' => doubleval($this->get_meta('mrr_2016-03', 0)),
				'mrr_2016-04' => doubleval($this->get_meta('mrr_2016-04', 0)),
				'mrr_2016-05' => doubleval($this->get_meta('mrr_2016-05', 0)),
				'mrr_2016-06' => doubleval($this->get_meta('mrr_2016-06', 0)),
				'revenue_2016-01' => doubleval($this->get_meta('revenue_2016-01', 0)),
				'revenue_2016-02' => doubleval($this->get_meta('revenue_2016-02', 0)),
				'revenue_2016-03' => doubleval($this->get_meta('revenue_2016-03', 0)),
				'revenue_2016-04' => doubleval($this->get_meta('revenue_2016-04', 0)),
				'revenue_2016-05' => doubleval($this->get_meta('revenue_2016-05', 0)),
				'revenue_2016-06' => doubleval($this->get_meta('revenue_2016-06', 0)),
				'revenue_sub_2016-01' => doubleval($this->get_meta('revenue_sub_2016-01', 0)),
				'revenue_sub_2016-02' => doubleval($this->get_meta('revenue_sub_2016-02', 0)),
				'revenue_sub_2016-03' => doubleval($this->get_meta('revenue_sub_2016-03', 0)),
				'revenue_sub_2016-04' => doubleval($this->get_meta('revenue_sub_2016-04', 0)),
				'revenue_sub_2016-05' => doubleval($this->get_meta('revenue_sub_2016-05', 0)),
				'revenue_sub_2016-06' => doubleval($this->get_meta('revenue_sub_2016-06', 0)),
				'revenue_single_2016-01' => doubleval($this->get_meta('revenue_single_2016-01', 0)),
				'revenue_single_2016-02' => doubleval($this->get_meta('revenue_single_2016-02', 0)),
				'revenue_single_2016-03' => doubleval($this->get_meta('revenue_single_2016-03', 0)),
				'revenue_single_2016-04' => doubleval($this->get_meta('revenue_single_2016-04', 0)),
				'revenue_single_2016-05' => doubleval($this->get_meta('revenue_single_2016-05', 0)),
				'revenue_single_2016-06' => doubleval($this->get_meta('revenue_single_2016-06', 0)),
				'revenue_shop_2016-01' => doubleval($this->get_meta('revenue_shop_2016-01', 0)),
				'revenue_shop_2016-02' => doubleval($this->get_meta('revenue_shop_2016-02', 0)),
				'revenue_shop_2016-03' => doubleval($this->get_meta('revenue_shop_2016-03', 0)),
				'revenue_shop_2016-04' => doubleval($this->get_meta('revenue_shop_2016-04', 0)),
				'revenue_shop_2016-05' => doubleval($this->get_meta('revenue_shop_2016-05', 0)),
				'revenue_shop_2016-06' => doubleval($this->get_meta('revenue_shop_2016-06', 0)),
			));
		}

		return $data;
	}

	/**
	 * Counts up box credits in all orders, taking into account adjustments.
	 */
	public function calculate_box_credits($verbose = false, $rush = false) {
		$totals = array(
			'credits' => intval($this->get_meta('extra_box_credits', 0)) + intval($this->get_meta('box_credit_adjustment', 0)),
			'revenue' => 0,
		);
		foreach ($this->get_orders() as $order) {
			if ('refunded' !== $order->status && 'processing' !== $order->status && 'completed' !== $order->status) continue;
			// Skip non-rush orders in rush mode
			if ($rush && !CBWoo::order_is_rush($order)) continue;
			foreach ($order->line_items as $line) {
				if ($line->sku) {
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
			}
		}
		return $totals;
	}
	public function calculate_box_debits($verbose = false, $rush = false) {
		$total = 0;
		foreach ($this->get_orders() as $order) {
			// Only count order if it is in one of these allowed states
			if ('refunded' !== $order->status && 'processing' !== $order->status && 'completed' !== $order->status) continue;
			// Skip non-rush orders in rush mode
			if ($rush && !CBWoo::order_is_rush($order)) continue;
			foreach ($order->line_items as $line) {
				if ($line->sku) {
					$parsed = CBWoo::parse_box_sku($line->sku);
					if ($parsed->debits > 0) {
						$total += $parsed->debits;
						if ($verbose) WP_CLI::debug("\t".'sku ' . $line->sku . ' debit ' . $parsed->debits);
					}
				}
			}
		}
		return $total;
	}

}
