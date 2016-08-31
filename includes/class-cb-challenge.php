<?php

/**
 * Internal ChallengeBox API that handles all the challenges for a given user.
 *
 * Instantiate a challenges object with a given CBCustomer object.
 * 
 * @since 1.0.0
 * @package challengebox
 */

class CBChallenges {

	private $user_id;
	private $user_info;
	private $customer;
	private $month_data;
	private $parsed_dates;

	public function __construct($customer) {
		$this->customer = $customer;
		$this->user_id = $customer->get_user_id();
		$this->user_info = get_userdata($this->user_id);
		$this->month_data = array();
	}

	//
	//  Month activity data
	//

	public function get_month_activity($date_in_month) {
		$d = $this->parse_month($date_in_month);
		// Use local cache
		if (!isset($this->month_data[$d->month_string])) {
			$this->month_data[$d->month_string] = $this->customer->fitbit()->get_cached_activity_data($d->start, $d->end);
		}
		return $this->month_data[$d->month_string];
	}

	//
	// 
	//

	/**
	 * Given a DateTime object, returns a bunch of useful related data.
	 *
	 * (object) array(
	 *	'start'         => ... // DateTime for the start of the month
	 *	'end'           => ... // DateTime for the end of the month
	 *	'last_start'    => ... // DateTime for the start of previous month
	 *	'last_end'      => ... // DateTime for the end of previous month
	 *	'next_start'    => ... // DateTime for the start of next month
	 *	'next_end'      => ... // DateTime for the end of next month
	 *	'days_in_month' => ... // Number of days in the current month
	 *	'days_so_far'   => ... // Number of days elapsed in the current month
	 *	'so_far_date'   => ... // Date of the current day of the month, time truncated
	 *	'month_string'  => ... // String representation of the month, for convenience
	 * )
	 */
	public function parse_month($date_in_month) {

		$key = $date_in_month->format("Y-m-d");
		if (!isset($this->parsed_dates[$key])) {
			$month_start = clone $date_in_month; $month_start->modify('first day of'); $month_start->setTime(0,0);
			$month_end = clone $month_start; $month_end->modify('last day of');
			$last_month_start = clone $month_start; $last_month_start->modify('first day of last month');
			$last_month_end = clone $month_start; $last_month_end->modify('last day of last month');
			$next_month_start = clone $month_start; $next_month_start->modify('first day of next month');
			$next_month_end = clone $month_start; $next_month_end->modify('last day of next month');
			$days_so_far = date_diff(min($month_end, $date_in_month), $month_start)->days + 1;
			$this->parsed_dates[$key] = (object) array(
				'start' => $month_start,
				'end' => $month_end,
				'last_start' => $last_month_start,
				'last_end' => $last_month_end,
				'next_start' => $next_month_start,
				'next_end' => $next_month_end,
				'days_in_month' => 0 + $month_start->format('t'),
				'days_so_far' => $days_so_far,
				'so_far_date' => CB::date_plus_days($month_start, $days_so_far - 1),
				'month_string' => $month_start->format('Y-m'),
			);
		}
		return $this->parsed_dates[$key];
	}

	//
	// Point system for Challenges
	//
	// This system tracks points earned by doing challenges, and is separate
	// from the main points system that users spend. These points get applied to 
	// the spendable points system using the apply_points and apply_month_points
	// cli commands.
	//
	// Usage notes: each chalenge is uniquely determined by it's key and the month, so if
	//              you had a "run_10_miles" challenge, it can be awarded points for January 2016
	//              and also for February 2016, etc. Now, if you change the point total, this will
	//              overwrite the old point total. If the new points are lower, the month total will
	//              be lowered, and the overall total will be lowered as well.
	//

	/**
	 * Calculates points in a month so far and records them. This uses most of the other systems in this class.
	 */
	public function calculate_month_points($date_in_month, $pretend = false) {

		$d = $this->parse_month($date_in_month);
		$fitness_goal = $this->customer->get_meta('fitness_goal1', 'weight_loss');
		$ordered_goals = $this->get_ordered_challenges_for_month($date_in_month);
		$data = $this->get_month_activity($date_in_month);
		$bests = $this->calculate_personal_bests($date_in_month, $pretend);

		$personal_goals = array_slice($ordered_goals[$fitness_goal], 0, 2);
		$personal_goals_completed = $personal_goals[0]['analysis']->completed && $personal_goals[1]['analysis']->completed;

		$consistency_goal = $this->goal_analysis('moderate_30', 10, $date_in_month);
		$steps_goal = $this->goal_analysis('steps_10k', 15, $date_in_month);
		$logging_goal = $this->goal_analysis('food_or_water_days', 8, $date_in_month);

		$personal_best_completed = count($bests->bests_this_month) > 0;

		if ($personal_goals_completed) $this->record_points('personal-goals', 40, $d->start, $pretend);
		if ($consistency_goal->completed) $this->record_points('consistency-goal', 30, $d->start, $pretend);
		if ($personal_best_completed) $this->record_points('personal-best', 20, $d->start, $pretend);
		if ($steps_goal->completed) $this->record_points('steps-goal', 5, $d->start, $pretend);
		if ($logging_goal->completed) $this->record_points('logging-goal', 5, $d->start, $pretend);

		return (object) array(
			'fitness_goal' => $fitness_goal,
			'ordered_goals' => $ordered_goals,
			'data' => $data,
			'bests' => $bests,
			'personal_goals' => $personal_goals,
			'personal_goals_completed' => $personal_goals_completed,
			'consistency_goal' => $consistency_goal,
			'steps_goal' => $steps_goal,
			'logging_goal' => $logging_goal,
			'personal_best_completed' => $personal_best_completed,
		);
	}

	public function get_points($point_key, $date_in_month) {
		return 0 + $this->customer->get_meta($this->_point_detail_key($point_key, $date_in_month), 0);
	}
	public function get_month_points($date_in_month) {
		return 0 + $this->customer->get_meta($this->_point_month_key($date_in_month), 0);
	}
	public function get_total_points() {
		return 0 + $this->customer->get_meta($this->_point_total_key(), 0);
	}
	public function record_points($point_key, $point_amount, $date_in_month, $pretend = false) {

		// Write new points
		$current_points = $this->get_points($point_key, $date_in_month);
		$difference = $point_amount - $current_points;
		$new_points = $current_points + $difference;
		if (!$pretend) $this->customer->set_meta($this->_point_detail_key($point_key, $date_in_month), $new_points);

		// Write new month points
		$current_month_points = $this->get_month_points($date_in_month);
		$new_month_points = $current_month_points + $difference;
		if (!$pretend) $this->customer->set_meta($this->_point_month_key($date_in_month), $new_month_points);

		// Write new total points
		$current_total_points = $this->get_total_points();
		$new_total_points = $current_total_points + $difference;
		if (!$pretend) $this->customer->set_meta($this->_point_total_key(), $new_total_points);

		return (object) array(
			'previous_points' => $current_points,
			'new_points' => $new_points,
			'previous_month_points' => $current_month_points,
			'new_month_points' => $new_month_points,
			'previous_total_points' => $current_total_points,
			'new_total_points' => $new_total_points,
		);
		/*
			 var_dump(array(
			 'month_string'=>$month_string,
			 'key'=>$key,
			 'current_points'=>$current_points,
			 'difference'=>$difference,
			 'month_key'=>$month_key,
			 'current_month_points'=>$current_month_points,
			 'new_month_points'=>$new_month_points,
			 'total_key'=>$total_key,
			 'current_total_points'=>$current_total_points,
			 'new_total_points'=>$new_total_points,
			 ));
		 */
	}

	private static function _point_detail_key($point_key, $date_in_month) {
		$month_string = $date_in_month->format('Y-m');
		return implode('_', array('cb-points-detail-v1', $month_string, $point_key));
	}
	private static function _point_month_key($date_in_month) {
		$month_string = $date_in_month->format('Y-m');
		return 'cb-points-month-v1_' . $month_string;
	}
	private static function _point_total_key() {
		return 'cb-points-v1';
	}


	//
	// Personal best system
	//
	// These manage personal best records for a given month. Records are identified
	// my a unique key. These value of a record is always a number, and updating the
	// record with a larger number sets a new best.
	//

	/**
	 * Returns false if no personal best exists for this key. Otherwise
	 * returns a personal best object that looks like this:
	 * (object) array( 
	 *  'value'  => ... // value of the best
	 *  'date'   => ... // date the best was set
	 *  'pvalue' => ... // previous record value if there was one for this month, otherwise false
	 *  'pdate'  => ... // date previous record was set if there was one for this month, otherwise false
	 * );
	 * Note that if the $date parameter is used, then pvalue and pdate will be the previous
	 * record that stood during that month. This is to facilitate looking at what your
	 * previous record was in a past month.
	 */
	public function get_personal_best($key, $date = null) {
		if (null === $date) $date = new DateTime();
		$args = $this->_personal_best_args($key, $date);
		$value = $this->customer->get_meta($args->value_key);
		$pvalue = $this->customer->get_meta($args->previous_value_key);
		if (!$value) return false;
		if (!$pvalue) $pvalue = false;
		$date = $this->customer->get_meta($args->date_key);
		$pdate = $this->customer->get_meta($args->previous_date_key);
		return (object) array(
			'value' => $value,
			'date' => $date,
			'pvalue' => $pvalue,  // If this was set, it was the best at the time
			'pdate' => $pdate,
		);
	}

	/**
	 * Updates the record if value is greater than the previous record.
	 * Returns an object that looks like this:
	 * (object) array( 
	 *  'new_best' => ...  // personal best object (same as returned by get_personal_best) if the
	 *                     // value is high enough to set a new record, otherwise false
	 *  'previous_best' => ... // personal best object for the previous record if one exists,
	 *                         // otherwise false
	 * );
	 */
	public function update_personal_best($key, $value, $date = null, $pretend = false) {
		if (null === $date) $date = new DateTime();
		// Returns false if no personal best was achieved, or an object containing
		// the personal best and previous best if a personal best was achieved.
		$previous_best = $this->get_personal_best($key, $date);
		if ($previous_best) {
			if ($value > $previous_best->value)
				$new_best = $this->set_personal_best($key, $value, $date, $previous_best->value, $previous_best->date, $pretend);
			else
				$new_best = false;
		}
		else {
			$new_best = $this->set_personal_best($key, $value, $date, false, false, $pretend);
		}
		return (object) array(
			'previous_best' => $previous_best,
			'new_best' => $new_best,
		);
	}

	/**
	 * Calculate personal bests.
	 *
	 */
	public function calculate_personal_bests($date_in_month, $pretend = false) {
		$d = $this->parse_month($date_in_month);
		$data = $this->get_month_activity($date_in_month);

		$best_metrics = array(
			'moderate_30' => (object) array(
				'display_string' => 'Did at least 30 minutes of excercise on %d separate days.',
				'completion_date' => $d->so_far_date,
			),
			'steps_max' => (object) array(
				'display_string' => 'Completed %s steps in a single day.',
				'completion_date' => CB::date_plus_days($d->start, $data['metrics']['steps_max_index']),
			),
			'distance_max' => (object) array(
				'display_string' => 'Traveled %0.1f miles in a single day.',
				'completion_date' => CB::date_plus_days($d->start, $data['metrics']['distance_max_index']),
			),
			'activity_max' => (object) array(
				'display_string' => 'Active for %d minutes in a single day.',
				'completion_date' => CB::date_plus_days($d->start, $data['metrics']['activity_max_index']),
			),
		);
		$new_bests = array();
		$bests_this_month = array();
		$previous_bests = array();
		foreach ($best_metrics as $metric => $entry) {
			$bests[$metric] = $result = $this->update_personal_best($metric, $data['metrics'][$metric], $entry->completion_date, $pretend);
			if ($result->previous_best) {
				$previous_bests[$metric] = $result->previous_best;
				// If this month had a best (even if it was since beaten) still consider it for this month
				if ($result->previous_best->pdate)
					$bests_this_month[$metric] = $result->previous_best;
			}
			if ($result->new_best) {
				$new_bests[$metric] = $result->new_best;
				// If we have a new best, clobber whatever previous value we had
				$bests_this_month[$metric] = $result->new_best;
			}
		}
		$bests_to_beat = array_filter($previous_bests, function($b) use (&$d) { return $b->date < $d->start; });

		return (object) array(
			'bests_this_month' => $bests_this_month,
			'bests_to_beat' => $bests_to_beat,
		);
	}

	private function _personal_best_args($key, $date) {
		// Overall best
		$value_key = 'cb-personal-best-val-v1_' . $key;
		$date_key = 'cb-personal-best-date-v1_' . $key;
		// These records are set when a personal best is set in a given month. They 
		// record the fact that at the time, this was the personal best, and that
		// fact should be remembered when looking at this month in the future (especially
		// for points purposes).
		$previous_value_key = 'cb-personal-best-mval-v1_' . $date->format('Y-m') . '_' . $key;
		$previous_date_key = 'cb-personal-best-mdate-v1_' . $date->format('Y-m') . '_' . $key;
		return (object) array(
			'user_id' => $this->user_id,
			'key' => $key,
			'value_key' => $value_key,
			'date_key' => $date_key,
			'previous_value_key' => $previous_value_key,
			'previous_date_key' => $previous_date_key,
		);
	}

	private function set_personal_best($key, $value, $date, $prev_value, $prev_date, $pretend = false) {
		// Just writes data, doesn't check that it makes sense!
		$args = $this->_personal_best_args($key, $date);
		if (!$pretend) {
			$this->customer->set_meta($args->value_key, $value);
			$this->customer->set_meta($args->date_key, $date);
			$this->customer->set_meta($args->previous_value_key, $prev_value);
			$this->customer->set_meta($args->previous_date_key, $prev_date);
		}
		return (object) array(
			'value' => $value,
			'date' => $date,
			'pvalue' => $prev_value,  // If this was set, it was the best at the time
			'pdate' => $prev_date,
		);
	}

	//
	//  Challenge system
	//

	/**
	 * Orders raw challenges to select the most interesting for the user in the given month.
	 * Order is based on previous month, so challenge selected is always the same.
	 */
	public function get_ordered_challenges_for_month($date_in_month) {

		// Order goals so that more relevant goals are in front
		$ordered_goals = array();
		foreach ($this->get_raw_challenges_for_month($date_in_month) as $challenge_goal => $challenge) {
			// Loop through potential goals and reorder them	
			$one = array();
			$two = array();
			foreach ($challenge["goals"] as $goal_index => $goal_entry) {
				// Kick goal to the end of the list if they blew past the max last month
				if (isset($goal_entry['previous_ratio']) && $goal_entry['previous_ratio'] >= 1) {
					$goal_entry['order_note'] = 'Moved to bottom because goal hit max last month.';
					array_push($two, $goal_entry);
				} else {
					array_push($one, $goal_entry);
				}
			}
			$ordered_goals[$challenge_goal] = array_merge($one, $two);

			// Note which goals are held in reserve
			foreach (array_slice($ordered_goals[$challenge_goal], 2) as $goal_index=>$goal_entry) {
				if (!isset($goal_entry['order_note']))
					$goal_entry['order_note'] = '';
				$goal_entry['order_note'] .= ' Goal held in reserve.';
				$goal_entry['reserved'] = true;
			}
		}
		return $ordered_goals;
	}

	/**
	 * Returns raw challenges for every type of goal, with progress filled in for the current user.
	 * All possible challenges are returned, no ranking is performed.
	 * Challenge levels are set based on previous month, so they should not change once the previous month is complete.
	 */
	public function get_raw_challenges_for_month($date_in_month) {
		$d = $this->parse_month($date_in_month);

		$data = $this->get_month_activity($d->start);
		$data_last = $this->get_month_activity($d->last_start);

		$days_so_far = $d->days_so_far;
		$days_in_month = $d->days_in_month;

		switch ($d->month_string) {

			case "2016-02":
			case "2016-03":
				return array(
					"strength" => array(
						"goals" => array(
							array("description" => "4 days of at least 10 minutes jogging this month.",
								"analysis" => $this->goal_analysis('heavy_10', 4, $date_in_month),),
							array("description" => "15 days of logging water this month.",
								"analysis" => $this->goal_analysis('water_days', 15, $date_in_month),),
						),
						"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-getting-stronger/",
							"/core-ball-exercises-for-getting-stronger/",
						)
					),
					"weight_loss" => array(
						"goals" => array(
							array("description" => "30 minutes of activity on at least 15 days per month.",
								"analysis" => $this->goal_analysis('moderate_30', 15, $date_in_month),),
							array("description" => "Add at least one food log on 15 separate days per month.",
								"analysis" => $this->goal_analysis('food_days', 15, $date_in_month),),
						),
						"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-losing-weight/",
							"/month2-exercises-for-losing-weight/",
						)
					),
					"tone" => array(
						"goals" => array(
							array("description" => "8 days of at least 45 active minutes this month.",
								"analysis" => $this->goal_analysis('moderate_45', 8, $date_in_month),),
							array("description" => "15 days of logging water this month.",
								"analysis" => $this->goal_analysis('water_days', 15, $date_in_month),),
						),
						"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-getting-toned/",
							"/core-ball-exercises-for-getting-toned/",
						),
					),
					"healthy" => array(
						"goals" => array(
							array("description" => "30 minutes of activity on at least 15 days per month.",
								"analysis" => $this->goal_analysis('moderate_30', 15, $date_in_month),),
							array("description" => "4 days of daily distance of at least 6 miles during the month.",
								"analysis" => $this->goal_analysis('distance_6', 4, $date_in_month),),
						),
						"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-being-healthy/",
							"/core-ball-exercises-for-being-healthy/",
						),
					),
					"race" => array(
						"goals" => array(
							array("description" => "15 days of at least 45 very active minutes this month.",
								"analysis" => $this->goal_analysis('heavy_45', 15, $date_in_month),),
							array("description" => "15 days of logging water this month.",
								"analysis" => $this->goal_analysis('water_days', 15, $date_in_month),),
						),
						"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-being-healthy/",
							"/coreball-exercises-race/",
						),
					),
				);
				break;

			case "2016-04":
				return array(
					"strength" => array(
						"goals" => array(
							$this->create_goal("45 minutes of activity on at least %d days this month.",
								'moderate_45', 8, 20, $date_in_month),
							$this->create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
								'heavy_10', 4, 15, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-getting-stronger/",
							"/core-ball-exercises-for-getting-stronger/",
							"/resistance-cuff-exercises/",
						)
					),
					"weight_loss" => array(
						"goals" => array(
							$this->create_goal("Be active for at least 30 minutes on %d days this month.",
								'moderate_30', 8, 20, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
							$this->create_goal("Be active for at least 60 minutes on %d days this month.",
								'moderate_60', 8, 20, $date_in_month),
							$this->create_goal("Complete at least 12,000 steps on %d days this month.",
								'steps_12k', 8, 20, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-losing-weight/",
							"/month2-exercises-for-losing-weight/",
							"/resistance-cuff-exercises/",
						)
					),
					"tone" => array(
						"goals" => array(
							$this->create_goal("Be active for at least 45 minutes on %d days this month.",
								'moderate_45', 8, 20, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
							$this->create_goal("Complete at least 12,000 steps on %d days this month.",
								'steps_12k', 8, 20, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-getting-toned/",
							"/core-ball-exercises-for-getting-toned/",
							"/resistance-cuff-exercises/",
						),
					),
					"healthy" => array(
						"goals" => array(
							$this->create_goal("Be active for at least 30 minutes on %d days this month.",
								'moderate_30', 8, 20, $date_in_month),
							$this->create_goal("Go 6 miles at least %d days this month.",
								'distance_6', 4, 16, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
							$this->create_goal("Be active for at least 60 minutes on %d days this month.",
								'moderate_60', 8, 20, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-being-healthy/",
							"/core-ball-exercises-for-being-healthy/",
							"/resistance-cuff-exercises/",
						),
					),
					"race" => array(
						"goals" => array(
							$this->create_goal("Go 10 miles at least %d days this month.",
								'distance_10', 5, 16, $date_in_month),
							$this->create_goal("Be active for at least 45 minutes on %d days this month.",
								'moderate_30', 8, 20, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
							$this->create_goal("Go 15 miles at least %d days this month.",
								'distance_15', 5, 16, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-being-healthy/",
							"/coreball-exercises-race/",
							"/resistance-cuff-exercises/",
						),
					),
				);
				break;

			case "2016-05":
			case "2016-06":
			case "2016-07":
			case "2016-08":
				return array(
					"strength" => array(
						"goals" => array(
							$this->create_goal("45 minutes of activity on at least %d days this month.",
								'moderate_45', 8, 20, $date_in_month),
							$this->create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
								'moderate_10', 4, 15, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-getting-stronger/",
							"/core-ball-exercises-for-getting-stronger/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
						)
					),
					"weight_loss" => array(
						"goals" => array(
							$this->create_goal("Be active for at least 30 minutes on %d days this month.",
								'moderate_30', 8, 20, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
							$this->create_goal("Be active for at least 60 minutes on %d days this month.",
								'moderate_60', 8, 20, $date_in_month),
							$this->create_goal("Complete at least 12,000 steps on %d days this month.",
								'steps_12k', 8, 20, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-losing-weight/",
							"/month2-exercises-for-losing-weight/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
						)
					),
					"tone" => array(
						"goals" => array(
							$this->create_goal("Be active for at least 45 minutes on %d days this month.",
								'moderate_45', 8, 20, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
							$this->create_goal("Complete at least 12,000 steps on %d days this month.",
								'steps_12k', 8, 20, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-getting-toned/",
							"/core-ball-exercises-for-getting-toned/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
						),
					),
					"healthy" => array(
						"goals" => array(
							$this->create_goal("Be active for at least 30 minutes on %d days this month.",
								'moderate_30', 8, 20, $date_in_month),
							$this->create_goal("Go 6 miles at least %d days this month.",
								'distance_6', 4, 16, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
							$this->create_goal("Be active for at least 60 minutes on %d days this month.",
								'moderate_60', 8, 20, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-being-healthy/",
							"/core-ball-exercises-for-being-healthy/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
						),
					),
					"race" => array(
						"goals" => array(
							$this->create_goal("Go 10 miles at least %d days this month.",
								'distance_10', 5, 16, $date_in_month),
							$this->create_goal("Be active for at least 45 minutes on %d days this month.",
								'moderate_30', 8, 20, $date_in_month),
							$this->create_goal("Log water intake on %d days this month.",
								'water_days', 15, 28, $date_in_month),
							$this->create_goal("Log foods for %d days this month.",
								'food_days', 15, 28, $date_in_month),
							$this->create_goal("Go 15 miles at least %d days this month.",
								'distance_15', 5, 16, $date_in_month),
						),
						"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
						"recommendation_links_by_month" => array(
							"/month1-exercises-for-being-healthy/",
							"/coreball-exercises-race/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
							"/resistance-cuff-exercises/",
						),
					),
				);
				break;

			default:
				return false;
				break;

		} // end switch

	}

	/**
	 * Returns an appropriate challenge level between a nominal and max level.
	 */
	public function set_challenge_level($challenge_name, $nominal_level, $max_level, $date_in_month) {
		$d = $this->parse_month($date_in_month);
		$data_last = $this->get_month_activity($d->last_start);

		$previous_level = isset($data_last[$challenge_name]) ? $data_last[$challenge_name] : 0;
		$one_dpw_more = $previous_level * (1 + 1/7);
		if ($previous_level < $nominal_level) return round($nominal_level);
		if ($previous_level) return round(min($max_level, max($nominal_level, $one_dpw_more)));
		return round($nominal_level);
	}


	/**
	 * Analyzes the given metric against the given goal at the given point in time.
	 */
	public function goal_analysis($metric, $goal, $date_in_month) {

		$d = $this->parse_month($date_in_month);
		$data = $this->get_month_activity($date_in_month);
		$current = $data['metrics'][$metric];

		$dpw_goal = CBChallenges::dpw($goal, $d->days_in_month);
		$dpw_so_far = CBChallenges::dpw($current, $d->days_so_far);
		$dpw_catchup = CBChallenges::dpw($goal - $current, 1 + $d->days_in_month - $d->days_so_far);
		/*
		echo '<pre>';
		var_dump(array('days_so_far'=>$days_so_far, 'days_in_month'=>$days_in_month, 'goal'=>$dpw_goal,'so_far'=>$dpw_so_far,'catchup'=>$dpw_catchup));
		echo '</pre>';
		*/
		if ($current >= $goal) {
			$msg = "✅ <b>Goal complete! Good job!</b>";
		} elseif ($dpw_so_far >= $dpw_catchup) {
			$msg = "😀 <b>You're on track to meet your goal. Keep it up!</b>";
		} else {
			if ($d->days_so_far == $d->days_in_month)
				$msg = "❤️ <b>Next time! Step up the pace next month to meet your goal.</b>";
			else
				$msg = "❤️ <b>Keep trying! You need to step up the pace to meet your goal.</b>";
		}
		$uid = uniqid();
		$progress_message = '<ol>Progress: '.$current.'/'.$goal.' <span id="'.$uid.'"></span></ol><script>$("#'.$uid.'").sparkline(['.$goal.','.$current.','.$d->days_in_month.'], {type: "bullet"});</script>';

		return (object) array(
			"current" => $current,
			"goal" => $goal,
			"completed" => $current >= $goal, 
			"message" => $msg, 
			"dpw_goal" => $dpw_goal,
			"dpw_so_far" => $dpw_so_far,
			"dpw_catchup" => $dpw_catchup,
			"progress_message" => $progress_message,
			"html" => $msg.$progress_message
		);
	}

	/**
	 * Shortcut function for creating goals.
	 */
	private function create_goal($description, $metric, $nominal_level, $max_level, $date_in_month) {

		$d = $this->parse_month($date_in_month);
		$data = $this->get_month_activity($d->start);
		$data_last = $this->get_month_activity($d->last_start);

		$current = $data['metrics'][$metric];
		$previous = $data_last['metrics'][$metric];

		$goal = $this->set_challenge_level($metric, $nominal_level, $max_level, $date_in_month);
		$analysis = $this->goal_analysis($metric, $goal, $date_in_month);
		
		return array(
			"description" => sprintf($description, $goal),
			"metric" => $metric,
			"nominal_level" => $nominal_level,
			"previous_level" => $previous,
			"previous_ratio" => $previous / max(1, $goal),
			"max_level" => $max_level,
			"analysis" => $analysis,
		);
	}



	//
	// Utility functions
	//

	public static function dpw($current, $total) {
		return (1.0*$current/$total)*7.0;
	}

	public static function days_per_week($current, $total) {
		$dpw = ($current/$total)*7;
		if ($dpw - floor($dpw) > 0.25) {
			return floor($dpw).'-'.ceil($dpw);
		} else {
			return round($dpw)+'';
		}
	}

	public static function format_dpw($dpw) {
		if ($dpw - floor($dpw) > 0.25) {
			return floor($dpw).'-'.ceil($dpw);
		} else {
			return round($dpw)+'';
		}
	}

}
