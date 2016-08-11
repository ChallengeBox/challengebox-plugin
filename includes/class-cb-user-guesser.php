<?php

/**
 * Class for parsing strings into potential user info (first name, last name, email)
 */
class UserGuesser {

	public $tokens;
	public $email_tokens;
	public $non_email_tokens;

	public $first_name_guess;
	public $last_name_guess;
	public $email_guess;

	public function __construct($string) {

		$string = strtolower($string);

		$this->first_name_guess = $this->last_name_guess = $this->email_guess = false;
		$this->tokens = preg_split('#\s+#', $string, null, PREG_SPLIT_NO_EMPTY);

		// Name guess
		$this->non_email_tokens = array_values(
			array_filter($this->tokens, function ($t) { return false === strpos($t, '@'); })
		);
		if (sizeof($this->non_email_tokens) >= 2) {
			$this->first_name_guess = $this->non_email_tokens[0];
			$this->last_name_guess = $this->non_email_tokens[1];
		}
		if (1 == sizeof($this->non_email_tokens)) {
			$this->first_name_guess = $this->non_email_tokens[0];
			$this->last_name_guess = $this->non_email_tokens[0];
		}

		// Email guess
		$this->email_tokens = array_values(
			array_filter($this->tokens, function ($t) { return strpos($t, '@'); })
		);
		$this->email_guess = current($this->email_tokens);
		if ($this->email_guess) $this->email_guess = trim($this->email_guess, '<>');
	}

	/**
	 * Rank another string in similarity to this one. 
	 * Returns one point for each matching field.
	 */ 
	public function rank($string) {
		$other = new UserGuesser($string);
		$points = 0;
		if ($other->email_guess && $other->email_guess == $this->email_guess) {
			$points += 5;
		}
		if ($other->first_name_guess && $this->first_name_guess) {
			if ($other->first_name_guess == $this->first_name_guess) $points += 3;
			elseif (stristr($other->first_name_guess, $this->first_name_guess)
				|| 	stristr($this->first_name_guess, $other->first_name_guess)) $points += 1;
		}
		if ($other->last_name_guess && $this->first_name_guess) {
			if ($other->last_name_guess == $this->first_name_guess) $points += 3;
			elseif (stristr($other->last_name_guess, $this->first_name_guess)
				|| 	stristr($this->first_name_guess, $other->last_name_guess)) $points += 1;
		}
		if ($other->first_name_guess && $this->last_name_guess) {
			if ($this->last_name_guess == $other->first_name_guess) $points += 3;
			elseif (stristr($this->last_name_guess, $other->first_name_guess)
				|| 	stristr($other->first_name_guess, $this->last_name_guess)) $points += 1;
		}
		if ($other->last_name_guess && $this->last_name_guess) {
			if (($this->first_name_guess != $this->last_name_guess) 
				&& ($other->first_name_guess != $other->last_name_guess)) {
			 	if ($other->last_name_guess == $this->last_name_guess) $points += 3;
				elseif (stristr($other->last_name_guess, $this->last_name_guess)
					|| 	stristr($this->last_name_guess, $other->last_name_guess)) $points += 1;
			}
		}
		return $points;
	}

	/**
	 * Returns a formatted string using the best guess for how the user should be formatted.
	 * Example: Ryan Witt <ryan@getchallengebox.com>
	 */
	public function format() {
		return implode(' ', array_filter(
			array(
				$this->first_name_guess,
				$this->first_name_guess != $this->last_name_guess ? $this->last_name_guess : false,
				$this->email_guess ? "<$this->email_guess>" : false, 
			)
		));
	}

	/**
	 * Returns the best guesses of users found in the database that might match this user.
	 */
	public function guess() {

		global $wpdb;

		// Get user ids of matches
		$where_clauses = array();
		$where_variables = array();
		if ($this->email_guess) {
			$where_clauses[] = "(meta_key = 'billing_email' AND meta_value COLLATE UTF8MB4_GENERAL_CI LIKE %s)";
			$where_variables[] = "%" . $wpdb->esc_like($this->email_guess) . "%";
		}
		if ($this->first_name_guess) {
			$where_clauses[] = "(meta_key = 'first_name' AND meta_value COLLATE UTF8MB4_GENERAL_CI LIKE %s)";
			$where_variables[] = "%" . $wpdb->esc_like($this->first_name_guess) . "%";
		}
		if ($this->last_name_guess) {
			$where_clauses[] = "(meta_key = 'last_name' AND meta_value COLLATE UTF8MB4_GENERAL_CI LIKE %s)";
			$where_variables[] = "%" . $wpdb->esc_like($this->last_name_guess) . "%";
		}
		if (sizeof($where_clauses)) {
			$sql = $wpdb->prepare(
				"SELECT DISTINCT\n\tuser_id\nFROM\n\t$wpdb->usermeta\nWHERE\n\t" . implode("\n\tOR ", $where_clauses) . ';',
				$where_variables
			);
			//var_dump($sql);
			$ids = array_map(function ($row) { return intval($row[0]); }, $wpdb->get_results($sql, ARRAY_N));
			$id_map = array(); foreach ($ids as $id) { $id_map[$id] = true; }
		} else {
			$id_map = array();
		}

		//var_dump($ids);

		// Get matches from user table, augmenting any ids we find
		$where_clauses = array();
		$where_variables = array();
		if (sizeof($id_map)) {
			$where_clauses[] = "ID IN (" . implode(', ', array_keys($id_map)) . ")";
		}
		if ($this->email_guess) {
			$where_clauses[] = "user_email COLLATE UTF8MB4_GENERAL_CI LIKE %s";
			$where_variables[] = "%" . $wpdb->esc_like($this->email_guess) . "%";
		}
		if ($this->first_name_guess) {
			$where_clauses[] = "display_name COLLATE UTF8MB4_GENERAL_CI LIKE %s";
			$where_variables[] = "%" . $wpdb->esc_like($this->first_name_guess) . "%";
		}
		if ($this->last_name_guess) {
			$where_clauses[] = "display_name COLLATE UTF8MB4_GENERAL_CI LIKE %s";
			$where_variables[] = "%" . $wpdb->esc_like($this->last_name_guess) . "%";
		}
		if (sizeof($where_clauses)) {
			$sql = $wpdb->prepare(
				"SELECT\n\tID, user_email, display_name \nFROM\n\t$wpdb->users\nWHERE\n\t" . implode("\n\tOR ", $where_clauses) . ';',
				$where_variables
			);
			//var_dump($sql);
			$matches = $wpdb->get_results($sql);
			foreach ($matches as $m) { $id_map[$m->ID] = true; }
		} else {
			$matches = array();
		}
		//var_dump($matches);

		// Grab metadata for all these ids
		$where_clauses = array();
		if (sizeof($id_map)) {
			$where_clauses[] = "user_id IN (" . implode(', ', array_keys($id_map)) . ")";
			$where_clauses[] = "(meta_key = 'billing_email' OR meta_key = 'first_name' OR meta_key = 'last_name')";
			$sql = "SELECT\n\tuser_id, meta_key, meta_value\nFROM\n\t$wpdb->usermeta\nWHERE\n\t" . implode("\n\tAND ", $where_clauses) . ';';
			//var_dump($sql);
			$meta_matches = $wpdb->get_results($sql);
		} else {
			$meta_matches = array();
		}
		//var_dump($meta_matches);

		// Build a list of possible matches to rank
		$candidates = array();

		// Use the user table results to build up a template
		foreach ($matches as $m) {
			$candidates[$m->ID] = (object) array(
				'id' => intval($m->ID),
				'email' => $m->user_email,
				'first_name' => $m->display_name,
				'last_name' => null,
				'rank' => null,
			//	'rank_string' => null,
			);
		}

		// Then fill-in all the metadata
		foreach ($meta_matches as $m) {
			if (!isset($candidates[$m->user_id])) {
				$candidates[$m->user_id] = (object) array(
					'id' => intval($m->user_id),
					'email' => null,
					'first_name' => null,
					'last_name' => null,
					'rank' => null,
			//		'rank_string' => null,
				);
			}
			if ('billing_email' == $m->meta_key) $candidates[$m->user_id]->email = $m->meta_value;
			if ('first_name' == $m->meta_key) $candidates[$m->user_id]->first_name = $m->meta_value;
			if ('last_name' == $m->meta_key) $candidates[$m->user_id]->last_name = $m->meta_value;
		}
		//var_dump((object) get_object_vars($this));

		// Rank the list
		foreach ($candidates as $c) {
			$rank_string = implode(' ', array($c->first_name, $c->last_name, $c->email)); 
			$c->rank = $this->rank($rank_string);
		}
		//var_dump($candidates);
		usort($candidates, function ($a, $b) { return $b->rank - $a->rank; });

		return $candidates;

	}

}
