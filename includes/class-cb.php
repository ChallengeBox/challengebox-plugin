<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    ChallengeBox
 * @subpackage ChallengeBox/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    ChallengeBox
 * @subpackage ChallengeBox/includes
 * @author     Your Name <email@example.com>
 */
class CB {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      ChallengeBox_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		$this->plugin_name = 'challengebox';
		$this->version = '1.0.0';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - ChallengeBox_Loader. Orchestrates the hooks of the plugin.
	 * - ChallengeBox_i18n. Defines internationalization functionality.
	 * - ChallengeBox_Admin. Defines all hooks for the admin area.
	 * - ChallengeBox_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		require_once plugin_dir_path(dirname(__FILE__)).'vendor/autoload.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-loader.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-i18n.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-wc.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-customer.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-segment.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-challenge.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-user-guesser.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-bot.php';
		require_once plugin_dir_path(dirname(__FILE__)).'admin/class-cb-admin.php';
		require_once plugin_dir_path(dirname(__FILE__)).'admin/class-cb-customers-stats.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-fitbit-api.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-challenge-shortcode.php';
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-churn-shortcode.php';
		require_once plugin_dir_path(dirname(__FILE__)).'public/class-cb-public.php';
 		if (defined( 'WP_CLI' ) && WP_CLI) {
			require_once plugin_dir_path(dirname(__FILE__)).'includes/class-cb-commands.php';
		}
		// Get rid of this once we move class-cb-fitibt-api.php off of it
		require_once plugin_dir_path(dirname(__FILE__)).'includes/class-fitbit-api.php';
		$this->loader = new ChallengeBox_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the ChallengeBox_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new ChallengeBox_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new ChallengeBox_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new ChallengeBox_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// add the WooCommerce core actions event descriptions
		add_filter('wc_points_rewards_event_description', array($this, 'add_points_event_descriptions'), 10, 3);

		// Skip order processing on subs
		add_filter('woocommerce_order_item_needs_processing', array($this, 'subscriptions_need_no_processing'), 10, 3);

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    ChallengeBox_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Similar to python's all(), returns true if all elements
	 * are true (true for empty array).
	 */
	public static function all($a) {
		return (bool) !array_filter($a, function ($x) {return !$x;});
	}
	/**
	 * Similar to python's any(), returns true if any element
	 * is true (false for empty array).
	 */
	public static function any($a) {
		return (bool) sizeof(array_filter($a));
	}

	/**
	 * Given two months, returns the number of months apart they are.
	 * For example:
	 *  number_of_months_apart(new Date("2016-06-30"), new Date("2016-07-01"))
	 *  evaluates to 1.
	 */
	public static function number_of_months_apart($date1, $date2) {
		$date_one = min($date1, $date2);
		$date_two = max($date1, $date2);
		$year_one = $date_one->format('Y') + 0;
		$year_two = $date_two->format('Y') + 0;
		$month_one = $date_one->format('n') + 0;
		$month_two = $date_two->format('n') + 0;
		return (($year_two - $year_one) * 12) + ($month_two - $month_one);
	}

	/**
	 * Returns a new DateTime object with the specified number of days added.
	 */
	public static function date_plus_days($date, $days) {
		$new_date = clone $date;
		$new_date->add(DateInterval::createFromDateString(sprintf('%d days', $days)));
		return $new_date;
	}

	/**
	 * Provides WooCommerce Points event description if the event type is one of 'product-review' or
	 * 'account-signup'
	 */
	public function add_points_event_descriptions( $event_description, $event_type, $event ) {
		switch ( $event_type ) {
			case 'monthly-challenge':
				$event_description = sprintf('%s challenge.', $event->data->format('F'));
				break;
			case 'point-adjustment':
				$event_description = sprintf('%s', $event->data);
				break;
			case 'double-points':
				$event_description = sprintf('2x point bonus for %s challenge.', $event->data->format('F'));
				break;
		}
		return $event_description;
	}

	/**
	 * Filter to tell WooCommerce not to put subscription orders into the 'processing' state but
	 * skip ahead to 'completed'.
	 */
	public function subscriptions_need_no_processing($need_processing, $_product, $order_id) {
		// Return false if product exists and sku startswith 'subscription_'
		if ($_product && $_product->exists()) {
			$sku = $_product->get_sku();
			if ($sku && 0 === stripos($sku, 'subscription_')) {
				return false;
			}
		}
		return $need_processing;
	}

	/**
	 * Post a message to Slack from WordPress
	 *
	 * @param string $message the message to be sent to Slack
	 * @param string $channel the #channel to send the message to (or @user for a DM)
	 * @param string $username the username for this bot eg : WordPress bot
	 * @param string $icon_emoji the icon emoji name for this bot eg :monkey: 
	 * 
	 * @link slack incoming webhook docs https://api.slack.com/incoming-webhooks
	 * @example ar_post_to_slack('message','#channel','bot-name',':monkey:');
	 */
	public function post_to_slack($message, $channel) {

		// Slack webhook endpoint from Slack settings
		$slack_endpoint = 'https://hooks.slack.com/services/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';

		// Prepare the data / payload to be posted to Slack
		$data = array(
			'payload'   => json_encode( array(
					'channel'   =>  $channel,
					'text'      =>  $message,
				)
			)
		);
		// Post our data via the slack webhook endpoint using wp_remote_post
		$posting_to_slack = wp_remote_post( 
			$slack_endpoint,
			array(
				'method' => 'POST',
				'timeout' => 30,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'headers' => array(),
				'body' => $data,
				'cookies' => array()
			)
		);
	}

	/**
	 * A warning that can be put at the top of pages if user's preferences have not been fully filled out.
	 */
	public function pref_warning_shortcode($atts, $content = "") {
		$a = shortcode_atts(array('debug' => false) , $atts);
		if ($a['debug']) { error_reporting(E_ALL); ini_set('display_errors', true); }
		if (!is_user_logged_in()) { return ""; } 

		$user_id = $real_user_id = get_current_user_id();
		if (!empty($_GET['sudo']))
			$user_id = $_GET['sudo'] + 0;

		$customer = new CBCustomer($user_id);
		if (empty($customer->get_meta('tshirt_size')) || empty($customer->get_meta('clothing_gender'))) {
			$return = <<<HTML
				<div class="alert alert-warning" role="alert">
					<b>Head's up!</b> We can't ship your box until you fill out at least a T-Shirt size and prefered clothing gender. <a href="/fitness-profile/">Click here</a> to fix.
				</div>
HTML;
		}
		return $return;
	}
}

add_shortcode( 'cb_pref_warning', array( 'CB', 'pref_warning_shortcode' ) );
