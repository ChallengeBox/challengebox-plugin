<?php

class Test_UserGuesser extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
	}

	function tearDown() {
		parent::tearDown();
	}

	function test_parser() {
		$this->assertEquals(
			(object) array(
				'tokens' => array('ryan', 'witt'),
				'email_tokens' => array(),
				'non_email_tokens' => array('ryan', 'witt'),
				'first_name_guess' => 'ryan',
				'last_name_guess' => 'witt',
				'email_guess' => false,
			), 
			(object) get_object_vars(new UserGuesser('Ryan Witt'))
		);
		$this->assertEquals(
			(object) array(
				'tokens' => array('ryan', 'witt', 'ryan@getchallengebox.com'),
				'email_tokens' => array('ryan@getchallengebox.com'),
				'non_email_tokens' => array('ryan', 'witt'),
				'first_name_guess' => 'ryan',
				'last_name_guess' => 'witt',
				'email_guess' => 'ryan@getchallengebox.com',
			), 
			(object) get_object_vars(new UserGuesser('Ryan Witt ryan@getchallengebox.com'))
		);
		$this->assertEquals(
			(object) array(
				'tokens' => array('ryan', 'witt', '<ryan@getchallengebox.com>'),
				'email_tokens' => array('<ryan@getchallengebox.com>'),
				'non_email_tokens' => array('ryan', 'witt'),
				'first_name_guess' => 'ryan',
				'last_name_guess' => 'witt',
				'email_guess' => 'ryan@getchallengebox.com',
			), 
			(object) get_object_vars(new UserGuesser('RYAN WITT <RYAN@GETCHALLENGEBOX.COM>'))
		);
		$this->assertEquals(
			(object) array(
				'tokens' => array('ryan', 'ryan@getchallengebox.com'),
				'email_tokens' => array('ryan@getchallengebox.com'),
				'non_email_tokens' => array('ryan'),
				'first_name_guess' => 'ryan',
				'last_name_guess' => 'ryan',
				'email_guess' => 'ryan@getchallengebox.com',
			), 
			(object) get_object_vars(new UserGuesser('ryAN ryan@getchallengebox.com'))
		);
		$this->assertEquals(
			(object) array(
				'tokens' => array('alisa'),
				'email_tokens' => array(),
				'non_email_tokens' => array('alisa'),
				'first_name_guess' => 'alisa',
				'last_name_guess' => 'alisa',
				'email_guess' => false,
			), 
			(object) get_object_vars(new UserGuesser('Alisa'))
		);
	}

	function test_format() {
		$this->assertEquals(
			"ryan witt <ryan@getchallengebox.com>",
			(new UserGuesser('Ryan Witt ryan@getchallengebox.com'))->format()
		);
		$this->assertEquals(
			"ryan witt <ryan@getchallengebox.com>",
			(new UserGuesser('Ryan WITT <RYAN@GEtchallengebox.com>'))->format()
		);
		$this->assertEquals(
			"ryan witt <ryan@getchallengebox.com>",
			(new UserGuesser('Ryan ryan@getchallengebox.com Witt'))->format()
		);
		$this->assertEquals(
			"ryan witt <ryan@getchallengebox.com>",
			(new UserGuesser('ryan@getchallengebox.coM Ryan Witt'))->format()
		);
		$this->assertEquals(
			"ryan",
			(new UserGuesser('Ryan'))->format()
		);
		$this->assertEquals(
			"ryan witt",
			(new UserGuesser('Ryan Witt'))->format()
		);
	}

	function test_rank() {
		$term = new UserGuesser("Ryan Witt <ryan@getchallengebox.com>");
		$this->assertEquals(11, $term->rank("Ryan Witt <Ryan@getchallengebox.com>"));
		$this->assertEquals(6, $term->rank("RYAN WITT"));
		$this->assertEquals(6, $term->rank("Ryan"));
		$this->assertEquals(5, $term->rank("Steve Jobs <ryan@getchallengebox.com>"));
		$this->assertEquals(5, $term->rank("ryan@getchallengebox.com"));
		$this->assertEquals(3, $term->rank("RyAN Gosling"));
		$this->assertEquals(3, $term->rank("Witt"));
		$this->assertEquals(2, $term->rank("RyanTest"));
		$this->assertEquals(1, $term->rank("Witty"));
	}

	function test_guess() {
		function create_customer($user_name, $user_email, $first_name, $last_name) {
			$user_id = username_exists( $user_name );
			if ( !$user_id ) {
				$test_email = $user_email;
				while (email_exists($test_email) != false) {
					$test_email = '1' . $test_email;
				}
				$random_password = wp_generate_password($length=12, $include_standard_special_chars=false);
				$user_id = wp_create_user($user_name, $random_password, $test_email);
			}
			$customer = new CBCustomer($user_id);
			$customer->set_meta('first_name', $first_name);
			$customer->set_meta('last_name', $last_name);
			$customer->set_meta('billing_email', $user_email);
			return $user_id;
		}

		$id1 = create_customer("test1", "ryan@getchallengebox.com", "ryan", "witt");
		$id2 = create_customer("test2", "Ryan+sneaky@getchallengebox.com", "RYAN", "WITT");
		$id3 = create_customer("test3", "ryan@getchallengebox.com", "Steve", "Jobs");
		$id4 = create_customer("test4", "admin@getchallengebox.com", "Ryan", "Witt");
		$id5 = create_customer("test5", "heygirl@getchallengebox.com", "Ryan", "Gosling");
		$id6 = create_customer("test6", "random@getchallengebox.com", "Ambrose", "Smith");

		global $wpdb;
		$wpdb->flush();

		$this->assertEquals(
			array(
				(object) array(
					'rank' => 11,
					'id' => $id1,
					'first_name' => 'ryan',
					'last_name' => 'witt',
					'email' => 'ryan@getchallengebox.com',
				),
				(object) array(
					'rank' => 6,
					'id' => $id2,
					'first_name' => 'RYAN',
					'last_name' => 'WITT',
					'email' => 'Ryan+sneaky@getchallengebox.com',
				),
				(object) array(
					'rank' => 6,
					'id' => $id4,
					'first_name' => 'Ryan',
					'last_name' => 'Witt',
					'email' => 'admin@getchallengebox.com',
				),
				(object) array(
					'rank' => 5,
					'id' => $id3,
					'first_name' => 'Steve',
					'last_name' => 'Jobs',
					'email' => 'ryan@getchallengebox.com',
				),
				(object) array(
					'rank' => 3,
					'id' => $id5,
					'first_name' => 'Ryan',
					'last_name' => 'Gosling',
					'email' => 'heygirl@getchallengebox.com',
				),
			),
			(new UserGuesser("Ryan Witt <ryan@getchallengebox.com>"))->guess()
		);
	}
}

