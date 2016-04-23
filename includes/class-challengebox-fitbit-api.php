<?php

use djchen\OAuth2\Client\Provider\Fitbit;

/**
 * Internal ChallengeBox fitbit API that handles OAuth version transition.
 *
 * Instantiate an api object with a given user id. By using this, you may force
 * the migration or re-authentication of the user, unless you specify interactive
 * as false.
 *
 * Example:
 * 	$fitbit = new CBFitbitAPI(get_current_user_id());
 * 	$fitbit->getProfile();
 * 	$fitbit->getTimeSeries('steps', '2016-04-01', '2016-04-30');
 * 
 * @since 1.0.0
 * @package challengebox
 */

class CBFitbitAPI {

	private $user_id;
	private $interactive;


	private $api1; // OAuth 1.0a
	private $api2; // OAuth 2.0
	private $force_v1;
	private $force_v2;

	private $v1_token_key  = 'fitpress_fitbit_token';
	private $v1_secret_key = 'fitpress_fitbit_secret';
	private $v1_token;
	private $v1_secret;

	private $v2_scopes = ['activity', 'heartrate', 'location', 'profile', 'settings', 'sleep', 'social', 'weight', 'nutrition'];
	private $v2_token_key  = 'cb-fitbit-oauth2-v1_access-token';
	private $v2_owner_key  = 'cb-fitbit-oauth2-v1_resource-owner';
	private $v2_token;
	private $v2_owner;

	//
	// URLs (TODO: make these settings eventually)
	//
	private $authentication_url = 'https://www.getchallengebox.com/link-fitbit/';
	private $unlink_url = 'https://www.getchallengebox.com/unlink-fitbit/';

	public function __construct($user_id, $interactive = true, $force_v1 = false, $force_v2 = false) {

		// Initial state
		$this->user_id = $user_id;
		$this->interactive = $interactive;
		$this->force_v1 = $force_v1;
		$this->force_v2 = $force_v2;

		//
		// OAuth 1.0a API
		//

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

		//
		// OAuth 2.0 API
		//

		$this->api2 = new Fitbit([
			'clientId'          => '227FVP',
			'clientSecret'      => '0efeaaef00ab4b529781cf79862a412f',
			'redirectUri'       => $this->authentication_url,
		]);

		$this->v2_token = get_user_meta($this->user_id, $this->v2_token_key, true);
		$this->v2_owner = get_user_meta($this->user_id, $this->v2_owner_key, true);

		if (!empty($this->v2_token) && !empty($this->v2_owner)) {
			// Token maintenance: update it if it's expired
			if ($this->v2_token->hasExpired()) {
				$this->v2_token = $this->api2->getAccessToken(
					'refresh_token', array('refresh_token' => $this->v2_token->getRefreshToken())
				);
				update_user_meta($this->user_id, $this->v2_token_key, $this->v2_token);
			}
		}

	}

	public function has_v1() {
		return (bool) (!empty($this->api1));
	}
	public function has_v2() {
		return (bool) ($this->v2_token && $this->v2_owner);
	}
	public function is_authenticated() {
		return (bool) ($this->has_v1() || $this->has_v2());
	}

	private function active_api() {
		// Respect caller overries
		if ($this->force_v1) return $this->api1;
		if ($this->force_v2) return $this->api2;

		// Use v2 if available
		if ($this->has_v2()) return $this->api2;

		// Otherwise, default to v1
		else return $this->api1;
	}

	/**
	 * Forces the user to authenticate only if they are not already
	 * and if the CBFitbitAPI object is flagged as interactive.
	 */
	private function maybe_authenticate_user() {
		if ($this->is_authenticated()) return;
		if ($this->interactive) $this->authenticate_user();
	}
	/**
	 * Forces the user to authenticate.
	 */
	private function authenticate_user() {
		// Preserve this page's URL so we can get back to it
		$_SESSION['oauth2referrer'] = get_permalink() . (empty($_SERVER['QUERY_STRING']) ? '' : '?'.$_SERVER['QUERY_STRING']);
		// Redirect to auth page
		header('Location: ' . $this->authentication_url);
		exit;
	}
	/**
	 * Handles deleting and re-asking for credentials in the case of a 401, etc.
	 */
	private function maybe_reauthorize_user() {
		if ($this->interactive) {
			$this->unlink_user();
			$this->authenticate_user();
		}
	}
	/**
	 * Removes all of our records of the fitbit account link.
	 */
	public function unlink_user() {
		$this->unlink_v1();
		$this->unlink_v2();
	}
	private function unlink_v1() {
		delete_user_meta($this->user_id, $this->v1_token_key);
		delete_user_meta($this->user_id, $this->v1_secret_key);
		unset($_SESSION['fitbit_Token']);
		unset($_SESSION['fitbit_Secret']);
		unset($_SESSION['fitbit_Session']);
		unset($this->v1_token);
		unset($this->v1_secret);
		$this->api1 = false;
	}
	private function unlink_v2() {
		delete_user_meta($this->user_id, $this->v2_token_key);
		delete_user_meta($this->user_id, $this->v2_owner_key);
		unset($this->v2_token);
		unset($this->v2_owner);
	}

	/*
   * Internal logic for sending a user out to fitbit's authorization page
   * and saving their tokens on return.
   */
	public function do_oauth_v2() {
		//if ($this->has_v2()) return;
		if (!isset($_GET['code'])) {
			$authorizationUrl = $this->api2->getAuthorizationUrl(array(
				'scope' => $this->v2_scopes
			));
			$_SESSION['oauth2state'] = $this->api2->getState();

			// Allow for callers to set the session variable, otherwise respect
			// the incoming referrer.
			if (!empty($_SERVER['HTTP_REFERER']) && empty($_SESSION['oauth2referrer']))
				$_SESSION['oauth2referrer'] = $_SERVER['HTTP_REFERER'];

			if (false) {
				?> <pre style="font-size:10px;"> <?php var_export(array(
					'$_SESSION' => $_SESSION,
				)); ?> </pre> <?php
				?>
					<a href="<?php echo $authorizationUrl ?>"><?php echo $authorizationUrl ?></a>
				<?php
			} else {
				header('Location: ' . $authorizationUrl);
				exit;
			}
		} 
		elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
			unset($_SESSION['oauth2state']);
			exit('Invalid state');
		}
		else {
			$v2_token = $this->api2->getAccessToken('authorization_code', ['code'=>$_GET['code']]);
			$v2_owner = $this->api2->getResourceOwner($v2_token);
			update_user_meta($this->user_id, $this->v2_token_key, $v2_token);
			update_user_meta($this->user_id, $this->v2_owner_key, $v2_owner);
			if (isset($_SESSION['oauth2referrer'])) {
				$ref = $_SESSION['oauth2referrer'];
				unset($_SESSION['oauth2referrer']);
				wp_safe_redirect($ref);
			}
		}
	}

	//
  // Brokered API (calls the appropriate version)
  //

	/**
   * Returns user profile (same results with both APIs)
   */
	public function getProfile() {
		try {

			$this->maybe_authenticate_user();

			if ($this->active_api() == $this->api1)
					return $this->api1->getProfile();

			return $this->api2->getResponse($this->api2->getAuthenticatedRequest(
				'GET', Fitbit::BASE_FITBIT_API_URL . '/1/user/-/profile.json', $this->v2_token,
				['headers' => ['Accept-Language' => 'en_US'], ['Accept-Locale' => 'en_US']]
			));

		} catch (OAuthException $ex) {
			if ($ex->getCode() == 401) return $this->maybe_reauthorize_user();
			else throw $ex;
		} catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $ex) {
			$this->maybe_reauthorize_user();
		}
	}

	/**
   * Returns time series as a simple array of values only, not labeled with dates.
   */
	public function getTimeSeries($activity, $start_date, $end_date) {
		try {

			$this->maybe_authenticate_user();

			if ($this->active_api() == $this->api1)
				return array_map(function ($v) { return $v->value; }, $this->api1->getTimeSeries($activity, $start_date, $end_date));

			switch ($activity) {
				case 'caloriesIn': $path = '/foods/log/caloriesIn'; break;
				case 'water': $path = '/foods/log/water'; break;
				case 'caloriesOut': $path = '/activities/log/calories'; break;
				case 'steps': $path = '/activities/log/steps'; break;
				case 'distance': $path = '/activities/log/distance'; break;
				case 'floors': $path = '/activities/log/floors'; break;
				case 'elevation': $path = '/activities/log/elevation'; break;
				case 'minutesSedentary': $path = '/activities/log/minutesSedentary'; break;
				case 'minutesLightlyActive': $path = '/activities/log/minutesLightlyActive'; break;
				case 'minutesFairlyActive': $path = '/activities/log/minutesFairlyActive'; break;
				case 'minutesVeryActive': $path = '/activities/log/minutesVeryActive'; break;
				case 'activityCalories': $path = '/activities/log/activityCalories'; break;
				case 'tracker_caloriesOut': $path = '/activities/log/tracker/calories'; break;
				case 'tracker_steps': $path = '/activities/log/tracker/steps'; break;
				case 'tracker_distance': $path = '/activities/log/tracker/distance'; break;
				case 'tracker_floors': $path = '/activities/log/tracker/floors'; break;
				case 'tracker_elevation': $path = '/activities/log/tracker/elevation'; break;
				case 'startTime': $path = '/sleep/startTime'; break;
				case 'timeInBed': $path = '/sleep/timeInBed'; break;
				case 'minutesAsleep': $path = '/sleep/minutesAsleep'; break;
				case 'awakeningsCount': $path = '/sleep/awakeningsCount'; break;
				case 'minutesAwake': $path = '/sleep/minutesAwake'; break;
				case 'minutesToFallAsleep': $path = '/sleep/minutesToFallAsleep'; break;
				case 'minutesAfterWakeup': $path = '/sleep/minutesAfterWakeup'; break;
				case 'efficiency': $path = '/sleep/efficiency'; break;
				case 'weight': $path = '/body/weight'; break;
				case 'bmi': $path = '/body/bmi'; break;
				case 'fat': $path = '/body/fat'; break;
				case 'activities_steps': $path = '/activities/steps'; break;
				default: return false;
			}

			$start_date_string = is_string($start_date) ? $start_date : $start_date->format('Y-m-d');
			$end_date_string = is_string($end_date) ? $end_date : $end_date->format('Y-m-d');

			$response = $this->api2->getResponse($this->api2->getAuthenticatedRequest(
				'GET', Fitbit::BASE_FITBIT_API_URL.'/1/user/-'.$path.'/date/'.$start_date_string.'/'.$end_date_string.'.json',
				$this->v2_token, ['headers' => ['Accept-Language' => 'en_US'], ['Accept-Locale' => 'en_US']]
			));

			$key = str_replace('/', '-', substr($path, 1));
			return array_map(function ($v) { return $v['value']; }, $response[$key]);

		} catch (OAuthException $ex) {
			if ($ex->getCode() == 401) return $this->maybe_reauthorize_user();
			else throw $ex;
		} catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $ex) {
			$this->maybe_reauthorize_user();
		}
	}

	/**
   * Returns time series (same format as old api).
   */
	public function oldGetTimeSeries($activity, $start_date, $end_date) {
		try {

			$this->maybe_authenticate_user();

			if ($this->active_api() == $this->api1)
				return $this->api1->getTimeSeries($activity, $start_date, $end_date);

			switch ($activity) {
				case 'caloriesIn': $path = '/foods/log/caloriesIn'; break;
				case 'water': $path = '/foods/log/water'; break;
				case 'caloriesOut': $path = '/activities/log/calories'; break;
				case 'steps': $path = '/activities/log/steps'; break;
				case 'distance': $path = '/activities/log/distance'; break;
				case 'floors': $path = '/activities/log/floors'; break;
				case 'elevation': $path = '/activities/log/elevation'; break;
				case 'minutesSedentary': $path = '/activities/log/minutesSedentary'; break;
				case 'minutesLightlyActive': $path = '/activities/log/minutesLightlyActive'; break;
				case 'minutesFairlyActive': $path = '/activities/log/minutesFairlyActive'; break;
				case 'minutesVeryActive': $path = '/activities/log/minutesVeryActive'; break;
				case 'activityCalories': $path = '/activities/log/activityCalories'; break;
				case 'tracker_caloriesOut': $path = '/activities/log/tracker/calories'; break;
				case 'tracker_steps': $path = '/activities/log/tracker/steps'; break;
				case 'tracker_distance': $path = '/activities/log/tracker/distance'; break;
				case 'tracker_floors': $path = '/activities/log/tracker/floors'; break;
				case 'tracker_elevation': $path = '/activities/log/tracker/elevation'; break;
				case 'startTime': $path = '/sleep/startTime'; break;
				case 'timeInBed': $path = '/sleep/timeInBed'; break;
				case 'minutesAsleep': $path = '/sleep/minutesAsleep'; break;
				case 'awakeningsCount': $path = '/sleep/awakeningsCount'; break;
				case 'minutesAwake': $path = '/sleep/minutesAwake'; break;
				case 'minutesToFallAsleep': $path = '/sleep/minutesToFallAsleep'; break;
				case 'minutesAfterWakeup': $path = '/sleep/minutesAfterWakeup'; break;
				case 'efficiency': $path = '/sleep/efficiency'; break;
				case 'weight': $path = '/body/weight'; break;
				case 'bmi': $path = '/body/bmi'; break;
				case 'fat': $path = '/body/fat'; break;
				case 'activities_steps': $path = '/activities/steps'; break;
				default: return false;
			}

			$start_date_string = is_string($start_date) ? $start_date : $start_date->format('Y-m-d');
			$end_date_string = is_string($end_date) ? $end_date : $end_date->format('Y-m-d');

			$response = $this->api2->getResponse($this->api2->getAuthenticatedRequest(
				'GET', Fitbit::BASE_FITBIT_API_URL.'/1/user/-'.$path.'/date/'.$start_date_string.'/'.$end_date_string.'.json',
				$this->v2_token, ['headers' => ['Accept-Language' => 'en_US'], ['Accept-Locale' => 'en_US']]
			));

			$key = str_replace('/', '-', substr($path, 1));
			return array_map(function ($v) { return (object) $v; }, $response[$key]);

		} catch (OAuthException $ex) {
			if ($ex->getCode() == 401) return $this->maybe_reauthorize_user();
			else throw $ex;
		} catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $ex) {
			$this->maybe_reauthorize_user();
		}
	}


	//
	// Shortcodes
	//

	/**
   * OAuth 2.0 endpoint shortcode.
   *
   * Instructions:
   *  1) Place this shortcode on its own page. 
	 *  2) Make sure to set the variable $authentication_url to the permalink for this page.
	 *  3) Make sure to set permalink as the callback url in the fitbit app settings.
   */
	public static function cb_fitbit_ouath2_endpoint( $atts, $content = "" ) {
		if (is_user_logged_in()) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
			$user_id = get_current_user_id();
			$fitbit_user = new CBFitbitAPI($user_id, true);
			$fitbit_user->do_oauth_v2();
		}
	}

	/**
   * Account unlinking shortcode.
   *
   * Instructions:
   *  1) Place this shortcode on its own page. 
	 *  2) Make sure to set the variable $unlink_url to the relative link (or permalink) for this page.
   */
	public static function cb_fitbit_unlink_account( $atts, $content = "" ) {
		if (is_user_logged_in()) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
			$user_id = get_current_user_id();
			$fitbit_user = new CBFitbitAPI($user_id, true);
			$fitbit_user->unlink_user();
			if (!empty($_SERVER['HTTP_REFERER'])) {
				wp_safe_redirect($_SERVER['HTTP_REFERER']);
			}
		}
	}

	/**
   * Account link/unlink shortcode. This shortcode displays the fitbit logo and a link
   * for the user to either link or unlink their account. You may place this anywhere
	 * inside a page.
   */
	public static function cb_fitbit_account_link( $atts, $content = "" ) {
		if (is_user_logged_in()) {
			$user_id = get_current_user_id();
			$fitbit_user = new CBFitbitAPI($user_id, true);
			if ($fitbit_user->is_authenticated()) {
				?>
					<img src='https://www.getchallengebox.com/wp-content/uploads/2016/02/unnamed-1.png' width=100>
					<a class="btn_unlink_fitbit" href="<?php echo $fitbit_user->unlink_url ?>">Unlink Fitbit Account</a>
				<?php
			} else {
				?>
					<img src='https://www.getchallengebox.com/wp-content/uploads/2016/02/fitbit-bw.png' width=100>
					<a class='btn_link_fitbit' href="<?php echo $fitbit_user->authentication_url ?>">Link Fitbit Account</a>
				<?php
			}
		}
	}

	/**
   * Testing shortcode.
   */
	public static function cb_fitbit_apitest( $atts, $content = "" ) {
		if (is_user_logged_in()) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
			$user_id = get_current_user_id();
			$fitbit = new CBFitbitAPI($user_id, true);
			?> <pre style="font-size:10px;"> <?php var_export(array(
				'$fitbit->has_v1()' => $fitbit->has_v1(),
				'$fitbit->has_v2()' => $fitbit->has_v2(),
				'$fitbit->is_authenticated()' => $fitbit->is_authenticated(),
				'$_SESSION' => $_SESSION,
			)); ?> </pre> <?php
		
			$fitbit_old = new CBFitbitAPI($user_id, true, true);
			$fitbit_new = new CBFitbitAPI($user_id, true, false, true);

			?> <pre style="font-size:10px;"> <?php var_export(array(
				'$fitbit_old->has_v1()' => $fitbit_old->has_v1(),
				'$fitbit_old->has_v2()' => $fitbit_old->has_v2(),
				'$fitbit_old->is_authenticated()' => $fitbit_old->is_authenticated(),
			)); ?> </pre> <?php


			?> <br/><hr/> <?php

			?> <pre style="font-size:10px;"> <?php var_export(array(
				'$fitbit->getTimeSeries()' => $fitbit_new->getTimeSeries('distance', '2016-03-01', '2016-03-31'),
			)); ?> </pre> <?php

			?> <pre style="font-size:10px;"> <?php var_export(array(
				'$fitbit_old->getTimeSeries()' => $fitbit_old->getTimeSeries('distance', '2016-03-01', '2016-03-31'),
			)); ?> </pre> <?php

			?> <br/><hr/> <?php

			try {
			?> <pre style="font-size:10px;"> <?php var_export(array(
				'$fitbit_new->getProfile()' => $fitbit_new->getProfile(),
			)); ?> </pre> <?php
			} catch (Exception $ex) {}

			try {
			?> <pre style="font-size:10px;"> <?php var_export(array(
				'$fitbit_old->getProfile()' => $fitbit_old->getProfile(),
			)); ?> </pre> <?php
			} catch (Exception $ex) {}
		}
	}
}

add_shortcode( 'cb_fitbit_oauth2_endpoint', array('CBFitbitAPI', 'cb_fitbit_ouath2_endpoint') );
add_shortcode( 'cb_fitbit_account_link', array('CBFitbitAPI', 'cb_fitbit_account_link') );
add_shortcode( 'cb_fitbit_unlink_account', array('CBFitbitAPI', 'cb_fitbit_unlink_account') );
add_shortcode( 'cb_fitbit_apitest', array('CBFitbitAPI', 'cb_fitbit_apitest') );

