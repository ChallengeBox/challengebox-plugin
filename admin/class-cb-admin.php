<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 */
class ChallengeBox_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->capability = 'read';
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action('admin_menu', array($this, 'add_admin_pages'));
		new CBLedger_Admin($this->capability);
	}
	
	public function add_admin_pages() {
		add_menu_page('ChallengeBox', 'ChallengeBox', $this->capability, 'cb-home', array($this, 'main_page'), plugin_dir_url( __FILE__ ) . 'img/cb-icon.png', 1);
	}

	public function main_page() {
		?>
			<b>Welcome!</b>
		<?php
	}

	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/challengebox-admin.css', array(), $this->version, 'all' );

	}

	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/challengebox-admin.js', array( 'jquery' ), $this->version, false );

	}

}

