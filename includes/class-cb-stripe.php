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

}


