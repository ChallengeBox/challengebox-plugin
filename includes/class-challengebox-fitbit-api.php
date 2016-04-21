<?php

use djchen\OAuth2\Client\Provider\Fitbit;

/**
 * Internal ChallengeBox fitbit API that handles OAuth version transition.
 *
 * Instantiate an api object with a given user id. By using this, you may force
 * the migration or re-authentication of the user, unless you specify interactive
 * as false.
 *
 * Example: $fitbit = new CBFitbitAPI(get_current_user());
 * 
 * @since 1.0.0
 * @package challengebox
 */

class CBFitbitAPI {

	private $user_id;
	private $interactive;

	private $api1; // OAuth 1.0a
	private $api2; // OAuth 2.0

	private $v1_token_key  = 'fitpress_fitbit_token';
	private $v1_secret_key = 'fitpress_fitbit_secret';
	private $v1_token;
	private $v1_secret;

	private $v2_token_key  = 'cb-fitbit-oauth2-v1_access-token';
	private $v2_owner_key  = 'cb-fitbit-oauth2-v1_resource-owner';
	private $v2_token;
	private $v2_owner;

	public function __construct($user_id, $interactive = true) {

		// Initial state
		$this->user_id = $user_id;
		$this->interactive = $interactive;

		// OAuth 1.0a API
		global $fitbit_php;
		$v1_token = get_user_meta($this->user_id, $this->v1_token_key, true);
		$v1_secret = get_user_meta($this->user_id, $this->v1_secret_key, true);
		if (!empty($v1_token) && !empty($v1_secret)) {
			$_SESSION['fitbit_Session'] = 2;
			$_SESSION['fitbit_Token'] = $v1_token;
			$_SESSION['fitbit_Secret'] = $v1_secret;
			$fitbit_php->setOAuthDetails($v1_token, $v1_secret);
			$this->api1 = $fitbit_php;
			$this->v1_token = $v1_token;
			$this->v1_secret = $v1_secret;
		} else {
			$this->api1 = false;
		}

		// OAuth 2.0 API
		$v2_token = get_user_meta($this->user_id, $this->v2_token_key, true);
		$v2_owner = get_user_meta($this->user_id, $this->v2_owner_key, true);
		if (!empty($v2_token) && !empty($v2_owner)) {
			$this->v2_token = $v2_token;
			$this->v2_owner = $v2_owner;
		}
		$this->api2 = new Fitbit([
			'clientId'          => '227HLB',
			'clientSecret'      => 'b1c277cc25aea167c683624d2cba0df6',
			'redirectUri'       => 'https://www.getchallengebox.com/fitbit-oauth-2-0-api-test/',
		]);
	}

	private function has_v2() {
		return !empty($this->v2_token) && !empty($this->v2_owner);
	}

	/*
   * Internal logic for the shortcode.
   */
	public function do_oauth_v2() {
		if ($this->has_v2()) return;
		if (!isset($_GET['code'])) {
			$authorizationUrl = $this->api2->getAuthorizationUrl();
			$_SESSION['oauth2state'] = $this->api2->getState();
			$_SESSION['oauth2referrer'] = $_SERVER['HTTP_REFERER'];
			header('Location: ' . $authorizationUrl);
			exit;
		} 
		elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
			unset($_SESSION['oauth2state']);
			exit('Invalid state');
		}
		else {
			try {
				$v2_token = $this->api2->getAccessToken('authorization_code', ['code' => $_GET['code']]);
				$v2_owner = $this->api2->getResourceOwner($v2_token);
				update_user_meta($this->user_id, $this->v2_token_key, $v2_token);
				update_user_meta($this->user_id, $this->v2_owner_key, $v2_owner);
				if (isset($_SESSION['oauth2referrer'])) {
					header('Location: ' . $_SESSION['oauth2referrer']);
					unset($_SESSION['oauth2referrer']);
				}
			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				exit($e->getMessage());
			}
		}
		
	}

	/**
	 * Use this shortcode on the page you wish to use as the redirect landing place
	 * for fitbit permissions page.
	 */
	public static function oauth_v2_shortcode( $atts, $content = "" ) {
		error_reporting(E_ALL);
		ini_set('display_errors', true);
		$user_id = get_current_user();
		$fitbit = new CBFitbitAPI($user_id, true);
		?> <pre style="font-size:10px;"> <?php echo $fitbit->do_oauth_v2(); ?> </pre> <?php
	}
	public static function remove_oauth_v2_shortcode( $atts, $content = "" ) {
		error_reporting(E_ALL);
		ini_set('display_errors', true);
		$user_id = get_current_user();
		$fitbit = new CBFitbitAPI($user_id, true);
		?> <pre style="font-size:10px;"> <?php echo $fitbit->do_oauth_v2(); ?> </pre> <?php
	}

	public static function apitest_shortcode( $atts, $content = "" ) {
		error_reporting(E_ALL);
		ini_set('display_errors', true);
		$user_id = get_current_user();
		$fitbit = new CBFitbitAPI($user_id, true);
		?> <pre style="font-size:10px;"> <?php echo $fitbit->do_oauth_v2(); ?> </pre> <?php
	}
}

add_shortcode( 'apitest', array('CBFitbitAPI', 'oauth_v2_shortcode') );
