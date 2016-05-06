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
		if (class_exists('Segment_Analytics_WordPress')) {
			$key = Segment_Analytics_WordPress::get_instance()->get_settings()['api_key'];
			$params = array(
				'debug' => false,
				'ssl' => true,
				'consumer' => 'socket',
				'error_handler' => function ($code, $msg) {
					throw new Exception("Error in segment. Code: $code Message: $msg");
				}
			);
			if ($key) {
				Segment::init($key, $params);
				$this->active = true;
			}
		} else {
				$this->active = false;
		}
	}

	/**
	 * Class to control server-side Segment.com integration for ChallengeBox
	 * Pass it a CBCustomer instance.
	 */
	public function identify($customer, $pretend = false) {

		$user = get_user_by('ID', $customer->get_user_id());

		$traits = array_merge($customer->get_segment_data(), array(
			'username'   => $user->user_login,
			'email'      => $user->user_email,
			'firstName'  => $user->user_firstname,
			'lastName'   => $user->user_lastname,
			'created_at' => $user->user_registered,
		));

		$data = array(
			'userId' => $user->ID,
			'traits' => $traits
		);

		if ($this->active && !$pretend) {
			Segment::identify($data);
		}

		return $data;
	}

	public function flush() {
		if ($this->active) {
			Segment::flush();
		}
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
			$identify['user_id'] = $customer->get_user_id();
			$identify['traits'] = array_merge($identify['traits'], $customer->get_segment_data());
			return $identify;
		}
		return $identify;
	}

}

add_filter( 'segment_get_current_user_identify', array('CBSegment', 'add_challengebox_data_to_segment'), 10, 2 );

