<?php

/**
 * ChallengeBox wrapper for Stripe API
 *
 * @package challengebox
 * @throws challengebox
 */

//class StripeConfigError extends Exception {}

class CBStripe {

	public static function setup_api() {
		$key = WP_DEBUG ? 'test_secret_key' : 'secret_key';
		\Stripe\Stripe::setApiKey(get_option('woocommerce_stripe_settings')[$key]);
	}

	public static function get_customer_charges($customer) {
		CBStripe::setup_api();
		return \Stripe\Charge::all(array("customer" => $customer->get_meta('_stripe_customer_id')));
	}

	public static function get_order_charge($order_id_or_instance) {
		$order = wc_get_order($order_id_or_instance);
		$charge_id = get_post_meta($order->id, '_stripe_charge_id', true);
		CBStripe::setup_api();
		return \Stripe\Charge::retrieve($charge_id);
	}

	public static function get_refunds($limit=10, $starting_after=false, $expand=false) {
		CBStripe::setup_api();
		$args = array('limit' => $limit);
		if ($starting_after) $args['starting_after'] = $starting_after;
		if ($expand) $args['expand'] = $expand;
		return \Stripe\Refund::all($args);
	}

	public static function get_charges($limit=10, $starting_after=false, $expand=false) {
		CBStripe::setup_api();
		$args = array('limit' => $limit);
		if ($starting_after) $args['starting_after'] = $starting_after;
		if ($expand) $args['expand'] = $expand;
		return \Stripe\Charge::all($args);
	}
}


