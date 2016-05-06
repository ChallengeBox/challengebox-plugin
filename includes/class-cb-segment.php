<?php

/**
 * Class to control server-side Segment.com integration for ChallengeBox
 * The main client side integration is handled by the plugin:
 *	https://github.com/segmentio/analytics-wordpress
 *
 * This class gets its settings from the above plugin, so make sure to install it.
 *
 * # Usage
 * $segment = new CBSegment();
 * $segment->identify(167);
 */
class CBSegment {

	private $active;
	
	public function __construct() {
		if (defined('Segment_Analytics_WordPress')) {
			$key = Segment_Analytics_WordPress::get_instance()->get_settings()['api_key'];
			if ($key) {
				Segment::init($key);
				$this->active = true;
			}
		}
	}

	/**
	 * Class to control server-side Segment.com integration for ChallengeBox
	 * Pass it a CBCustomer instance.
	 */
	public function identify($customer, $pretend = false) {

		$user = get_user_by('id', $customer->get_user_id());

		$traits = array_merge($customer->get_segment_data(), array(
			'username'  => $user->user_login,
			'email'     => $user->user_email,
			'firstName' => $user->user_firstname,
			'lastName'  => $user->user_lastname,
			'url'       => $user->user_url,
		));

		$data = array(
			'userId' => $user->user_email,
			'traits' => $traits
		);

		if ($this->active && !$pretend) {
			Segment::identify($data);
		}

		return $data;
	}

	//
	// Static functions
	//

	/**
	 * Adds ChallengeBox data to the client side integration.
	 */
	public static function add_challengebox_data_to_segment( $identify, $settings ) {
		if ( is_user_logged_in() && $identify && !empty($identify['user_id']) ) {
			$customer = new CBCustomer(get_current_user_id());
			return array_merge($identify, $customer->get_segment_data());
		}
		return $identify;
	}

}

add_filter( 'segment_get_current_user_identify', array('CBCustomer', 'add_challengebox_data_to_segment'), 10, 2 );

