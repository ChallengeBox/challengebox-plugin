<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    ChallengeBox
 * @subpackage ChallengeBox/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    ChallengeBox
 * @subpackage ChallengeBox/public
 * @author     Your Name <email@example.com>
 */
class ChallengeBox_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in ChallengeBox_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The ChallengeBox_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/challengebox-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in ChallengeBox_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The ChallengeBox_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		//wp_enqueue_script( 'jqcb', plugin_dir_url( __FILE__ ) . 'js/jquery-1.12.2.min.js', array() , '1.12.2', true );
		//wp_enqueue_script( 'jqcb', plugin_dir_url( __FILE__ ) . 'js/jquery-3.1.0.min.js', array() , '3.1.0', true );
		//wp_enqueue_script( 'jquery-sparklines', plugin_dir_url( __FILE__ ) . 'js/jquery-sparkline-2.1.2.min.js', array('jqcb') , '2.1.2', true );
		//wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/challengebox-public.js', array( 'jquery', 'jqcb', 'jquery-sparklines' ), $this->version, true );
		wp_enqueue_script( 'jquery-sparklines', plugin_dir_url( __FILE__ ) . 'js/jquery-sparkline-2.1.2.min.js', array('jquery') , '2.1.2', true );
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/challengebox-public.js', array( 'jquery', 'jquery-sparklines' ), $this->version, true );
	}

}
