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

class FitbitNeedsAuth extends Exception {}

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

		
		
		if (is_object($fitbit_php) && !empty($v1_token) && !empty($v1_secret)) {
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
			'verify'            => false,
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

	//
	//  High-level API
	// 

	public function get_cached_activity_data($start, $end) {
		$key = $this->_activity_cache_key($start, $end);
		if (false === ($data = get_transient($key))) {
			$data = $this->get_activity_data($start, $end);
			set_transient($key, $data, 60*60);
		}
		return $data;
	}

	public function get_activity_data($start, $end) {

		$very_active = $this->get_cached_time_series('minutesVeryActive', $start, $end);
		$fairly_active = $this->get_cached_time_series('minutesFairlyActive', $start, $end);
		$lightly_active = $this->get_cached_time_series('minutesLightlyActive', $start, $end);
		$water = $this->get_cached_time_series('water', $start, $end);
		$food = $this->get_cached_time_series('caloriesIn', $start, $end);
		$distance = $this->get_cached_time_series('distance', $start, $end);
		$steps = $this->get_cached_time_series('steps', $start, $end);

		// Activity digests
		$any_activity = $very_active;
		$medium_activity = $very_active;
		$heavy_activity = $very_active;
		foreach ($very_active as $key => $item) {
			$any_activity[$key] = 0 + $very_active[$key] + $fairly_active[$key] + $lightly_active[$key];
			$medium_activity[$key] = 0 + $very_active[$key] + $fairly_active[$key];
			$heavy_activity[$key] = 0 + $very_active[$key];
		}

		// Use this to measure the time fitbit has actually been measuring data
		$wearing_fitbit = array_sum(array_map(function ($v) { return $v >= 1; }, $any_activity));

		
		return array(
			'time_series' => array(
				'any_activity' => $any_activity,
				'medium_activity' => $medium_activity,
				'heavy_activity' => $heavy_activity,
				'water' => $water,
				'food' => $food,
				'distance' => $distance,
				'steps' => $steps,

				// raw data
				'very_active' => $very_active,
				'fairly_active' => $fairly_active,
				'lightly_active' => $lightly_active,
			),
			'metrics' => array(
				'activity_max' => max($medium_activity),
				'activity_max_index' => array_keys($medium_activity, max($medium_activity))[0],
				'light_30' => array_sum(array_map(function ($v) { return $v >= 30; }, $any_activity)),
				'light_60' => array_sum(array_map(function ($v) { return $v >= 60; }, $any_activity)),
				'light_90' => array_sum(array_map(function ($v) { return $v >= 90; }, $any_activity)),
				'moderate_10' => array_sum(array_map(function ($v) { return $v >= 10; }, $medium_activity)),
				'moderate_30' => array_sum(array_map(function ($v) { return $v >= 30; }, $medium_activity)),
				'moderate_45' => array_sum(array_map(function ($v) { return $v >= 45; }, $medium_activity)),
				'moderate_60' => array_sum(array_map(function ($v) { return $v >= 60; }, $medium_activity)),
				'moderate_90' => array_sum(array_map(function ($v) { return $v >= 90; }, $medium_activity)),
				'heavy_10' => array_sum(array_map(function ($v) { return $v >= 10; }, $heavy_activity)),
				'heavy_30' => array_sum(array_map(function ($v) { return $v >= 30; }, $heavy_activity)),
				'heavy_45' => array_sum(array_map(function ($v) { return $v >= 45; }, $heavy_activity)),
				'heavy_60' => array_sum(array_map(function ($v) { return $v >= 60; }, $heavy_activity)),
				'heavy_90' => array_sum(array_map(function ($v) { return $v >= 90; }, $heavy_activity)),
				'water_days' => array_sum(array_map(function ($v) { return $v > 0; }, $water)),
				'food_days' => array_sum(array_map(function ($v) { return $v > 0; }, $food)),
				'food_or_water_days' => array_sum(array_map(function ($f, $w) { return $f > 0 || $w > 0; }, $food, $water)),
				'distance_total' => array_sum($distance),
				'distance_1' => array_sum(array_map(function ($v) { return $v >= 1; }, $distance)),
				'distance_2' => array_sum(array_map(function ($v) { return $v >= 2; }, $distance)),
				'distance_3' => array_sum(array_map(function ($v) { return $v >= 3; }, $distance)),
				'distance_4' => array_sum(array_map(function ($v) { return $v >= 4; }, $distance)),
				'distance_5' => array_sum(array_map(function ($v) { return $v >= 5; }, $distance)),
				'distance_6' => array_sum(array_map(function ($v) { return $v >= 6; }, $distance)),
				'distance_8' => array_sum(array_map(function ($v) { return $v >= 8; }, $distance)),
				'distance_10' => array_sum(array_map(function ($v) { return $v >= 10; }, $distance)),
				'distance_15' => array_sum(array_map(function ($v) { return $v >= 15; }, $distance)),
				'distance_15' => array_sum(array_map(function ($v) { return $v >= 15; }, $distance)),
				'distance_max' => max($distance),
				'distance_max_index' => array_keys($distance, max($distance))[0],
				'steps_total' => array_sum($steps),
				'steps_8k' => array_sum(array_map(function ($v) { return $v >= 8000; }, $steps)),
				'steps_10k' => array_sum(array_map(function ($v) { return $v >= 10000; }, $steps)),
				'steps_12k' => array_sum(array_map(function ($v) { return $v >= 12000; }, $steps)),
				'steps_15k' => array_sum(array_map(function ($v) { return $v >= 15000; }, $steps)),
				'steps_max' => max($steps),
				'steps_max_index' => array_keys($steps, max($steps))[0],
				'wearing_fitbit' => $wearing_fitbit,
			),
		);
	}

	//
	//  Caching API
	// 

	public function get_cached_time_series($activity, $start, $end) {
		$key = $this->_time_series_cache_key($activity, $start, $end);

		if (false === ($raw = get_transient($key))) {
			$raw = $this->oldGetTimeSeries($activity, $start, $end);
			set_transient($key, $raw, 60*60);
		}
		return array_map(function ($v) { return 0 + $v->value; }, $raw);
	}

	public function clear_fitbit_cache($start, $end) {
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
			delete_transient($this->_time_series_cache_key($activity, $start, $end));
		}
		delete_transient($this->_activity_cache_key($start, $end));
	}

	public function inspect_fitbit_cache($start, $end) {
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
			$key = $this->_time_series_cache_key($activity, $start, $end);
			$data[$key] = get_transient($key);
		}
		$activity_key = $this->_activity_cache_key($start, $end);
		$data[$activity_key] = get_transient($activity_key);
		return $data;
	}

	// Cache keys

	private function _time_series_cache_key($activity, $start, $end) {
		return implode(
			'_', array(
				'fitbit-cache-v1',
				$activity,
				$this->user_id,
				$start->format('Y-m-d'),
				$end->format('Y-m-d')
			)
		);
	}
	private function _activity_cache_key($start, $end) {
		return implode(
			'_', array(
				'fitbit-activity-v1',
				$this->user_id,
				$start->format('Y-m-d'),
				$end->format('Y-m-d')
			)
		);
	}

	//
	// Selection machinery for new / old api
	//

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
		if ($this->interactive) {
			$this->authenticate_user();
		} else {
			throw new FitbitNeedsAuth("Fitbit for user $this->user_id not authenticated");
		}
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

