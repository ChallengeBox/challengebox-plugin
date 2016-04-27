<?php

/**
 * WC API Client resource for customer subscriptions
 */
class WC_API_Client_Resource_Customer_Subscriptions extends WC_API_Client_Resource {

	public function __construct( $client ) {
		parent::__construct( 'customers', 'customer', $client );
	}

	/**
	 * Get customer subscriptions
	 *
	 * GET /customers/#{customer_id}/subscriptions
	 *
	 * @since 2.0
	 * @param int $id customer ID
	 * @param array $args acceptable customer orders endpoint args, currently only `fields`
	 * @return array|object customer subscriptions!
	 */
	public function get_subscriptions( $id, $args = array() ) {

		$this->set_request_args( array(
			'method' => 'GET',
			'path'   => array( $id, 'subscriptions' ),
			'params' => $args,
		) );

		return $this->do_request();
	}
}

/**
 * WC API Client resource for subscriptions
 */
