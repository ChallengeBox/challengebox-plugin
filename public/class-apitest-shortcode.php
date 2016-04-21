<?php

/**
 * @since 1.0.0
 * @package challengebox
 */

use djchen\OAuth2\Client\Provider\Fitbit;

function cb_fitbit_init_api() {
	return $api = new Fitbit([
			'clientId'          => '227HLB',
			'clientSecret'      => 'b1c277cc25aea167c683624d2cba0df6',
			'redirectUri'       => 'https://www.getchallengebox.com/fitbit-oauth-2-0-api-test/',
			]);
}

/**
 * Use this shortcode on the page you wish to use as the redirect landing place
 * for fitbit permissions page.
 */
function cb_fitbit_api_redirect_page( $atts, $content = "" ) {

	error_reporting(E_ALL);
	ini_set('display_errors', true);

	$api = cb_fitbit_init_api();
	$user_id = get_current_user();

	?> <pre style="font-size:10px;"> <?php

	if (!isset($_GET['code'])) {
		$authorizationUrl = $api->getAuthorizationUrl();
		$_SESSION['oauth2state'] = $api->getState();
		?>
		<a href="<?php echo $authorizationUrl ?>"><?php echo $authorizationUrl ?></a>
		<?php
		//header('Location: ' . $authorizationUrl);
		//exit;
	} 
	elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
		unset($_SESSION['oauth2state']);
		exit('Invalid state');
	}
	else {
		try {
			$accessToken = $api->getAccessToken('authorization_code', ['code' => $_GET['code']]);
			$resourceOwner = $api->getResourceOwner($accessToken);

			// The provider provides a way to get an authenticated API request for
			// the service, using the access token; it returns an object conforming
			// to Psr\Http\Message\RequestInterface.
			$request = $api->getAuthenticatedRequest(
					'GET',
					Fitbit::BASE_FITBIT_API_URL . '/1/user/-/profile.json',
					$accessToken,
					['headers' => ['Accept-Language' => 'en_US'], ['Accept-Locale' => 'en_US']]
					// Fitbit uses the Accept-Language for setting the unit system used
					// and setting Accept-Locale will return a translated response if available.
					// https://dev.fitbit.com/docs/basics/#localization
					);
			// Make the authenticated API request and get the response.
			$response = $api->getResponse($request);
			var_export($response);

		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

			// Failed to get the access token or user details.
			exit($e->getMessage());

		}
	}

	?> </pre> <?php
}

add_shortcode( 'apitest', 'cb_fitbit_api_redirect_page' );
