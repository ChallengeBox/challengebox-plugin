<?php

class ChallengeBox_i18n {
	public function load_plugin_textdomain() {
		load_plugin_textdomain('challengebox', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/');
	}
}
