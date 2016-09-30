<?php

/**
 * ChallengeBox wrapper for Stripe API
 *
 * @package challengebox
 * @throws challengebox
 */

//class StripeConfigError extends Exception {}

class CBStripe {

	public static function get_customer_charges($customer) {
		//$sk = get_option('woocommerce_stripe_settings')['secret_key'];
		//if (!$secret_key) throw new CBStripeConfigError('Stripe secret key not set in WooCommerce');
		\Stripe\Stripe::setApiKey(get_option('woocommerce_stripe_settings')['secret_key']);
		return \Stripe\Charge::all(array("customer" => $customer->get_meta('_stripe_customer_id')));
	}

	public static function get_order_charge($order_id_or_instance) {
		$order = wc_get_order($order_id_or_instance);
		$charge_id = get_post_meta($order->id, '_stripe_charge_id', true);
		\Stripe\Stripe::setApiKey(get_option('woocommerce_stripe_settings')['secret_key']);
		return \Stripe\Charge::retrieve($charge_id);
	}

	public static function get_refunds($limit=10, $starting_after=false) {
		\Stripe\Stripe::setApiKey(get_option('woocommerce_stripe_settings')['secret_key']);
		if ($starting_after) {
			return \Stripe\Refund::all(array('limit' => $limit, 'starting_after' => $starting_after));
		} else {
			return \Stripe\Refund::all(array('limit' => $limit));
		}
	}

	public static function get_charges($limit=10, $starting_after=false) {
		\Stripe\Stripe::setApiKey(get_option('woocommerce_stripe_settings')['secret_key']);
		if ($starting_after) {
			return \Stripe\Charge::all(array('limit' => $limit, 'starting_after' => $starting_after));
		} else {
			return \Stripe\Charge::all(array('limit' => $limit));
		}
	}
}


