<?php

/**
 * Challenge shortcode.
 *
 * Create the content of the challenge page.
 *
 * @since 1.0.0
 * @package challengebox
 */


class CBChallengeShortcode {

	public static function shortcode( $atts, $content = "" ) {

		$a = shortcode_atts( array(
				'debug' => false,
		), $atts );

		if ($a['debug']) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
		}

		$return = "";

		if (!is_user_logged_in()) {
			$return = 'You must be logged in to authorize Fitbit';
			$return .= ' <a href="/my-account/">'.__( 'Log in' ).'</a>';
			return $return;
		} 

		$user_id = $real_user_id = get_current_user_id();
		if (!empty($_GET['sudo']))
			$user_id = $_GET['sudo'] + 0;

		//
		// Use stored credentials
		//

		$fitbit_user = new CBFitbitAPI($user_id);

		$customer = new CBCustomer($user_id);
		if (empty($customer->get_meta('tshirt_size')) || empty($customer->get_meta('clothing_gender'))) {
		 ?>	
				<div class="alert alert-warning" role="alert">
					<b>Head's up!</b> We can't ship your box until you fill out at least a T-Shirt size and prefered clothing gender. <a href="/fitness-profile/">Click here</a> to fix.
				</div>
			<?php
		}

		//
		// Utility functions
		//

		function number_of_months_apart($date1, $date2) {
			$date_one = min($date1, $date2);
			$date_two = max($date1, $date2);
			$year_one = $date_one->format('Y') + 0;
			$year_two = $date_two->format('Y') + 0;
			$month_one = $date_one->format('n') + 0;
			$month_two = $date_two->format('n') + 0;
			return (($year_two - $year_one) * 12) + ($month_two - $month_one);
		}

		function modQuery($add_to, $rem_from = array(), $clear_all = false){
			if ($clear_all){
				$query_string = array();
			}else{
				parse_str($_SERVER['QUERY_STRING'], $query_string);
			}
			if (!is_array($add_to)){ $add_to = array(); }
			$query_string = array_merge($query_string, $add_to);
			if (!is_array($rem_from)){ $rem_from = array($rem_from); }
			foreach($rem_from as $key){
				unset($query_string[$key]);
			}
			return http_build_query($query_string);
		}

		function sum($carry, $value) {
			return $carry + $value;
		}

		function dpw($current, $total) {
			return (1.0*$current/$total)*7.0;
		}

		function days_per_week($current, $total) {
			$dpw = ($current/$total)*7;
			if ($dpw - floor($dpw) > 0.25) {
				return floor($dpw).'-'.ceil($dpw);
			} else {
				return round($dpw)+'';
			}
		}

		function format_dpw($dpw) {
			if ($dpw - floor($dpw) > 0.25) {
				return floor($dpw).'-'.ceil($dpw);
			} else {
				return round($dpw)+'';
			}
		}

		function date_plus_days($date, $days) {
			// Returns a new DateTime object with the given number of days added.
			$new_date = clone $date;
			$new_date->add(DateInterval::createFromDateString(sprintf('%d days', $days)));
			return $new_date;
		}

		function fitbit_cache_key($string, $user_id, $month_start, $month_end) {
			return implode('_', array('fitbit-cache-v1', $string, $user_id, $month_start->format('Y-m-d'), $month_end->format('Y-m-d')));
		}

		function get_cached_fitbit_time_series($series_name, $user_id, $month_start, $month_end, $fitbit_user) {
			$cache_key = fitbit_cache_key($series_name, $user_id, $month_start, $month_end);
			if (false === ($raw = get_transient($cache_key))) {
				$raw = $fitbit_user->oldGetTimeSeries($series_name, $month_start, $month_end);
				set_transient($cache_key, $raw, 60*60*24);
			}
			return array_map(function ($v) { return 0 + $v->value; }, $raw);
		}

		//
		// Data gathering
		//

		$goal = get_user_meta($user_id, 'fitness_goal1', true);
		$user_info = get_userdata($user_id);
		$registration_date = new DateTime($user_info->user_registered);
		$first_name = get_user_meta($user_id, 'first_name', true);
		$last_name = get_user_meta($user_id, 'last_name', true);
		$name = $first_name;
		if ($name == "")
			$name = get_user_meta($user_id, 'nickname', true);
		$advanced_mode = isset($_GET['advanced']) && $_GET['advanced'] == 'true';
		
		$now = new DateTime();
		$last_day_of_next_month = clone $now; $last_day_of_next_month->modify('last day of next month');
		$year_string = isset($_GET['year']) ? $_GET['year'] : $now->format('Y');
		$month_string = isset($_GET['month']) ? $_GET['month'] : $now->format('n');
		$yearmonth_string = $year_string . '-' . $month_string . '-' . '1';
		$month_start = new DateTime($yearmonth_string);
		$month_end = clone $month_start; $month_end->modify('last day of');
		$last_month_start = clone $month_start; $last_month_start->modify('first day of last month');
		$last_month_end = clone $month_start; $last_month_end->modify('last day of last month');
		$next_month_start = clone $month_start; $next_month_start->modify('first day of next month');
		$next_month_end = clone $month_start; $next_month_end->modify('last day of next month');
		$cur_month_name = $month_start->format('F');
		$days_in_month = 0 + $month_start->format('t');
		$days_so_far = date_diff(min($month_end,$now), $month_start)->days+1;
		$so_far_date = date_plus_days($month_start, $days_so_far-1);

		$start_date = max(clone $registration_date, new DateTime("january 31, 2016"));
		$start_date->modify('first day of next month');
		$month = number_of_months_apart($start_date, $month_start);

		$is_users_first_month = $registration_date > $last_month_end;
		$showing_current_month = $month_start->format('Y-n') == $now->format('Y-n');

		// Don't allow the date to go before the first box or after next month
		if ($month_start < new DateTime('february 1, 2016') || $next_month_end > $last_day_of_next_month) {
			return wp_redirect(strtok($_SERVER["REQUEST_URI"],'?'), 302);
		}

		//
		// General Activity
		//

		function get_fitbit_data($user_id, $month_start, $month_end, $fitbit_user) {

			//
			// Actual fitbit data
			//
		
			$very_active = get_cached_fitbit_time_series('minutesVeryActive', $user_id, $month_start, $month_end, $fitbit_user);
			$fairly_active = get_cached_fitbit_time_series('minutesFairlyActive', $user_id, $month_start, $month_end, $fitbit_user);
			$lightly_active = get_cached_fitbit_time_series('minutesLightlyActive', $user_id, $month_start, $month_end, $fitbit_user);
			$water = get_cached_fitbit_time_series('water', $user_id, $month_start, $month_end, $fitbit_user);
			$food = get_cached_fitbit_time_series('caloriesIn', $user_id, $month_start, $month_end, $fitbit_user);
			$distance = get_cached_fitbit_time_series('distance', $user_id, $month_start, $month_end, $fitbit_user);
			$steps = get_cached_fitbit_time_series('steps', $user_id, $month_start, $month_end, $fitbit_user);

			// Activity digests
			$any_activity = $very_active;
			$medium_activity = $very_active;
			$heavy_activity = $very_active;
			foreach ($very_active as $key=>$item) {
				$any_activity[$key] = 0 + $very_active[$key] + $fairly_active[$key] + $lightly_active[$key];
				$medium_activity[$key] = 0 + $very_active[$key] + $fairly_active[$key];
				$heavy_activity[$key] = 0 + $very_active[$key];
			}

			// Use this to measure the time fitbit has actually been measuring data
			$wearing_fitbit = array_reduce(array_map(function ($v) { return $v >= 1; }, $any_activity), "sum", 0);

			return array(
					'any_activity' => $any_activity,
					'medium_activity' => $medium_activity,
					'heavy_activity' => $heavy_activity,
					'wearing_fitbit' => $wearing_fitbit,
					'activity_max' => max($medium_activity),
					'activity_max_index' => array_keys($medium_activity, max($medium_activity))[0],
					'light_30' => array_reduce(array_map(function ($v) { return $v >= 30; }, $any_activity), "sum", 0),
					'light_60' => array_reduce(array_map(function ($v) { return $v >= 60; }, $any_activity), "sum", 0),
					'light_90' => array_reduce(array_map(function ($v) { return $v >= 90; }, $any_activity), "sum", 0),
					'moderate_10' => array_reduce(array_map(function ($v) { return $v >= 10; }, $medium_activity), "sum", 0),
					'moderate_30' => array_reduce(array_map(function ($v) { return $v >= 30; }, $medium_activity), "sum", 0),
					'moderate_45' => array_reduce(array_map(function ($v) { return $v >= 45; }, $medium_activity), "sum", 0),
					'moderate_60' => array_reduce(array_map(function ($v) { return $v >= 60; }, $medium_activity), "sum", 0),
					'moderate_90' => array_reduce(array_map(function ($v) { return $v >= 90; }, $medium_activity), "sum", 0),
					'heavy_10' => array_reduce(array_map(function ($v) { return $v >= 10; }, $heavy_activity), "sum", 0),
					'heavy_30' => array_reduce(array_map(function ($v) { return $v >= 30; }, $heavy_activity), "sum", 0),
					'heavy_45' => array_reduce(array_map(function ($v) { return $v >= 45; }, $heavy_activity), "sum", 0),
					'heavy_60' => array_reduce(array_map(function ($v) { return $v >= 60; }, $heavy_activity), "sum", 0),
					'heavy_90' => array_reduce(array_map(function ($v) { return $v >= 90; }, $heavy_activity), "sum", 0),
					'water' => $water,
					'water_days' => array_reduce(array_map(function ($v) { return $v > 0; }, $water), "sum", 0),
					'food' => $food,
					'food_days' => array_reduce(array_map(function ($v) { return $v > 0; }, $food), "sum", 0),
					'food_or_water_days' => array_reduce(array_map(function ($f, $w) { return $f > 0 || $w > 0; }, $food, $water), "sum", 0),
					'distance' => $distance,
					'distance_1' => array_reduce(array_map(function ($v) { return $v >= 1; }, $distance), "sum", 0),
					'distance_2' => array_reduce(array_map(function ($v) { return $v >= 2; }, $distance), "sum", 0),
					'distance_3' => array_reduce(array_map(function ($v) { return $v >= 3; }, $distance), "sum", 0),
					'distance_4' => array_reduce(array_map(function ($v) { return $v >= 4; }, $distance), "sum", 0),
					'distance_5' => array_reduce(array_map(function ($v) { return $v >= 5; }, $distance), "sum", 0),
					'distance_6' => array_reduce(array_map(function ($v) { return $v >= 6; }, $distance), "sum", 0),
					'distance_8' => array_reduce(array_map(function ($v) { return $v >= 8; }, $distance), "sum", 0),
					'distance_10' => array_reduce(array_map(function ($v) { return $v >= 10; }, $distance), "sum", 0),
					'distance_15' => array_reduce(array_map(function ($v) { return $v >= 15; }, $distance), "sum", 0),
					'distance_15' => array_reduce(array_map(function ($v) { return $v >= 15; }, $distance), "sum", 0),
					'distance_max' => max($distance),
					'distance_max_index' => array_keys($distance, max($distance))[0],
					'steps' => $steps,
					'steps_8k' => array_reduce(array_map(function ($v) { return $v >= 8000; }, $steps), "sum", 0),
					'steps_10k' => array_reduce(array_map(function ($v) { return $v >= 10000; }, $steps), "sum", 0),
					'steps_12k' => array_reduce(array_map(function ($v) { return $v >= 12000; }, $steps), "sum", 0),
					'steps_15k' => array_reduce(array_map(function ($v) { return $v >= 15000; }, $steps), "sum", 0),
					'steps_max' => max($steps),
					'steps_max_index' => array_keys($steps, max($steps))[0],
			);

		}

		// Get data and cache results to avoid fitbit API problems
		$this_month_key = implode('_', array('fitbit-data-v3', $user_id, $month_start->format('Y-m-d'), $month_end->format('Y-m-d')));
		if (false === ($data = get_transient($this_month_key))) {
			$data = get_fitbit_data($user_id, $month_start, $month_end, $fitbit_user);
			set_transient($this_month_key, $data, 60*60);
		}
		$last_month_key = implode('_', array('fitbit-data-v3', $user_id, $last_month_start->format('Y-m-d'), $last_month_end->format('Y-m-d')));
		if (false === ($data_last = get_transient($last_month_key))) {
			$data_last = get_fitbit_data($user_id, $last_month_start, $last_month_end, $fitbit_user);
			set_transient($last_month_key, $data_last, 60*60);
		}

		//
		// Point system functions
		//

		function get_points($user_id, $point_key, $month_start) {
			$month_string = $month_start->format('Y-m');
			$key = implode('_', array('cb-points-detail-v1', $month_string, $point_key));
			return 0 + get_user_meta($user_id, $key, true);
		}
		function get_month_points($user_id, $month_start) {
			$month_string = $month_start->format('Y-m');
			$month_key = 'cb-points-month-v1_' . $month_string;
			return 0 + get_user_meta($user_id, $month_key, true);
		}
		function get_total_points($user_id) {
			$total_key = 'cb-points-v1';
			return 0 + get_user_meta($user_id, $total_key, true);
		}

		function record_points($user_id, $point_key, $point_amount, $month_start) {
			$month_string = $month_start->format('Y-m');
			$key = implode('_', array('cb-points-detail-v1', $month_string, $point_key));
			//$recorded_amount_key = implode('_', array('cb-points', $point_key, $month_string, 'recorded'));
			$current_points = 0 + get_user_meta($user_id, $key, true);
			//$recorded = get_user_meta($user_id, $recorded_amount_key, true);
			$difference = $point_amount - $current_points;
			$new_points = $current_points + $difference;
			update_user_meta($user_id, $key, $new_points);

			$month_key = 'cb-points-month-v1_' . $month_string;
			$current_month_points = 0 + get_user_meta($user_id, $month_key, true);
			$new_month_points = $current_month_points + $difference;
			update_user_meta($user_id, $month_key, $new_month_points);

			$total_key = 'cb-points-v1';
			$current_total_points = 0 + get_user_meta($user_id, $total_key, true);
			$new_total_points = $current_total_points + $difference;
			update_user_meta($user_id, $total_key, $new_total_points);

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

		//
		// Personal best functions
		//

		function get_personal_best_args($user_id, $metric, $month_start) {
			$value_key = 'cb-personal-best-val-v1_' . $metric;
			$date_key = 'cb-personal-best-date-v1_' . $metric;
			// These records are set when a personal best is set in a given month. They 
			// record the fact that at the time, this was the personal best, and that
			// fact should be remembered when looking at this month in the future (especially
			// for points purposes).
			$month_value_key = 'cb-personal-best-mval-v1_' . $month_start->format('Y-m') . '_' . $metric;
			$month_date_key = 'cb-personal-best-mdate-v1_' . $month_start->format('Y-m') . '_' . $metric;
			return (object) array(
				'user_id' => $user_id,
				'metric' => $metric,
				'value_key' => $value_key,
				'date_key' => $date_key,
				'month_value_key' => $month_value_key,
				'month_date_key' => $month_date_key,
			);
		}

		function get_personal_best($user_id, $metric, $month_start) {
			// Returns false if no personal best exists for this metric.
			// Otherwise returns an object containing the value and date
			// of the best.
			$args = get_personal_best_args($user_id, $metric, $month_start);
			$value = get_user_meta($user_id, $args->value_key, true);
			$mvalue = get_user_meta($user_id, $args->month_value_key, true);
			if ('' === $value) return false;
			if ('' === $mvalue) $mvalue = false;
			$date = get_user_meta($user_id, $args->date_key, true);
			$mdate = get_user_meta($user_id, $args->month_date_key, true);
			return (object) array(
				'value' => $value,
				'date' => $date,
				'mvalue' => $mvalue,  // If this was set, it was the best at the time
				'mdate' => $mdate,
			);
		}

		function set_personal_best($user_id, $metric, $value, $month_start) {
			// Just writes data, doesn't check that it makes sense!
			// Returns new personal best object
			$args = get_personal_best_args($user_id, $metric, $month_start);
			update_user_meta($user_id, $args->value_key, $value);
			update_user_meta($user_id, $args->date_key, $month_start);
			update_user_meta($user_id, $args->month_value_key, $value);
			update_user_meta($user_id, $args->month_date_key, $month_start);
			return (object) array(
				'value' => $value,
				'date' => $month_start,
				'mvalue' => $value,  // If this was set, it was the best at the time
				'mdate' => $month_start,
			);
		}

		function update_personal_best($user_id, $metric, $value, $month_start) {
			// Returns false if no personal best was achieved, or an object containing
			// the personal best and previous best if a personal best was achieved.
			$previous_best = get_personal_best($user_id, $metric, $month_start);
			if ($previous_best) {
				if ($value > $previous_best->value)
					$new_best = set_personal_best($user_id, $metric, $value, $month_start);
				else
					$new_best = false;
			}
			else {
				$new_best = set_personal_best($user_id, $metric, $value, $month_start);
			}
			return (object) array(
				'previous_best' => $previous_best,
				'new_best' => $new_best,
			);
		}

		//
		// Goal / challenge functions
		//

		function set_challenge_level($challenge_name, $nominal_level, $max_level, $data_last) {
			$previous_level = isset($data_last[$challenge_name]) ? $data_last[$challenge_name] : 0;
			$one_dpw_more = $previous_level * (1 + 1/7);
			if ($previous_level < $nominal_level) return round($nominal_level);
			if ($previous_level) return round(min($max_level, max($nominal_level, $one_dpw_more)));
			return round($nominal_level);
		}

		function goal_analysis($current, $goal, $days_so_far, $days_in_month) {
			$dpw_goal = dpw($goal, $days_in_month);
			$dpw_so_far = dpw($current, $days_so_far);
			$dpw_catchup = dpw($goal-$current, 1+$days_in_month-$days_so_far);
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
				if ($days_so_far == $days_in_month)
					$msg = "❤️ <b>Next time! Step up the pace next month to meet your goal.</b>";
				else
					$msg = "❤️ <b>Keep trying! You need to step up the pace to meet your goal.</b>";
			}
			$uid = uniqid();
			//$progress_message = '<ol>Progress: '.$current.'/'.$goal.' <span id="'.$uid.'"></span></ol><script>$("#'.$uid.'").sparkline(['.$goal.','.$current.','.$days_in_month.'], {type: "bullet"});</script>';
			$progress_message = '<ol>Progress: '.$current.'/'.$goal.' <span class="spark-bullet">'.implode(',', [$goal, $current, $days_in_month]).'</span></ol>';

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

		function create_goal($description, $metric, $nominal_level, $max_level, $data, $data_last, $days_so_far, $days_in_month) {
			$current = $data[$metric];
			$previous = $data_last[$metric];
			$goal = set_challenge_level($metric, $nominal_level, $max_level, $data_last);
			$analysis = goal_analysis($current, $goal, $days_so_far, $days_in_month);
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
		// Generate goal analysis for any goal
		//
		$challenges_by_month = array(
			"2016-02" => array(
				"strength" => array(
					"goals" => array(
						array("description" => "4 days of at least 10 minutes jogging this month.",
							"analysis" => goal_analysis($data['heavy_10'], 4, $days_so_far, $days_in_month),),
						array("description" => "15 days of logging water this month.",
							"analysis" => goal_analysis($data['water_days'], 15, $days_so_far, $days_in_month),),
					),
					"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
					"recommendation_links_by_month" => array("/month1-exercises-for-getting-stronger/"),
				),
				"weight_loss" => array(
					"goals" => array(
						array("description" => "30 minutes of activity on at least 15 days per month.",
							"analysis" => goal_analysis($data['moderate_30'], 15, $days_so_far, $days_in_month),),
						array("description" => "Add at least one food log on 15 separate days per month.",
							"analysis" => goal_analysis($data['food_days'], 15, $days_so_far, $days_in_month),),
					),
					"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array("/month1-exercises-for-losing-weight/"),
				),
				"tone" => array(
					"goals" => array(
						array("description" => "8 days of at least 45 active minutes this month.",
							"analysis" => goal_analysis($data['moderate_45'], 8, $days_so_far, $days_in_month),),
						array("description" => "15 days of logging water this month.",
							"analysis" => goal_analysis($data['water_days'], 15, $days_so_far, $days_in_month),),
					),
					"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array("/month1-exercises-for-getting-toned/"),
				),
				"healthy" => array(
					"goals" => array(
						array("description" => "30 minutes of activity on at least 15 days per month.",
							"analysis" => goal_analysis($data['moderate_30'], 15, $days_so_far, $days_in_month),),
						array("description" => "4 days of daily distance of at least 6 miles during the month.",
							"analysis" => goal_analysis($data['distance_6'], 4, $days_so_far, $days_in_month),),
					),
					"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array("/month1-exercises-for-being-healthy/"),
				),
				"race" => array(
					"goals" => array(
						array("description" => "15 days of at least 45 very active minutes this month.",
							"analysis" => goal_analysis($data['heavy_45'], 15, $days_so_far, $days_in_month),),
						array("description" => "15 days of logging water this month.",
							"analysis" => goal_analysis($data['water_days'], 15, $days_so_far, $days_in_month),),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array("/month1-exercises-for-being-healthy/"),
				),
			),
			"2016-03" => array(
				"strength" => array(
					"goals" => array(
						array("description" => "4 days of at least 10 minutes jogging this month.",
							"analysis" => goal_analysis($data['heavy_10'], 4, $days_so_far, $days_in_month),),
						array("description" => "15 days of logging water this month.",
							"analysis" => goal_analysis($data['water_days'], 15, $days_so_far, $days_in_month),),
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
							"analysis" => goal_analysis($data['moderate_30'], 15, $days_so_far, $days_in_month),),
						array("description" => "Add at least one food log on 15 separate days per month.",
							"analysis" => goal_analysis($data['food_days'], 15, $days_so_far, $days_in_month),),
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
							"analysis" => goal_analysis($data['moderate_45'], 8, $days_so_far, $days_in_month),),
						array("description" => "15 days of logging water this month.",
							"analysis" => goal_analysis($data['water_days'], 15, $days_so_far, $days_in_month),),
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
							"analysis" => goal_analysis($data['moderate_30'], 15, $days_so_far, $days_in_month),),
						array("description" => "4 days of daily distance of at least 6 miles during the month.",
							"analysis" => goal_analysis($data['distance_6'], 4, $days_so_far, $days_in_month),),
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
							"analysis" => goal_analysis($data['heavy_45'], 15, $days_so_far, $days_in_month),),
						array("description" => "15 days of logging water this month.",
							"analysis" => goal_analysis($data['water_days'], 15, $days_so_far, $days_in_month),),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/coreball-exercises-race/",
					),
				),
			),
			"2016-04" => array(
				"strength" => array(
					"goals" => array(
						create_goal("45 minutes of activity on at least %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
							'heavy_10', 4, 15, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
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
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
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
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
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
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 6 miles at least %d days this month.",
							'distance_6', 4, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
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
						create_goal("Go 10 miles at least %d days this month.",
							'distance_10', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 15 miles at least %d days this month.",
							'distance_15', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/coreball-exercises-race/",
						"/resistance-cuff-exercises/",
					),
				),
			),
			"2016-05" => array(
				"strength" => array(
					"goals" => array(
						create_goal("45 minutes of activity on at least %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
							'moderate_10', 4, 15, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-stronger/",
						"/core-ball-exercises-for-getting-stronger/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"weight_loss" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-losing-weight/",
						"/month2-exercises-for-losing-weight/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"tone" => array(
					"goals" => array(
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-toned/",
						"/core-ball-exercises-for-getting-toned/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"healthy" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 6 miles at least %d days this month.",
							'distance_6', 4, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/core-ball-exercises-for-being-healthy/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"race" => array(
					"goals" => array(
						create_goal("Go 10 miles at least %d days this month.",
							'distance_10', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 15 miles at least %d days this month.",
							'distance_15', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/coreball-exercises-race/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
			),
			"2016-06" => array(
				"strength" => array(
					"goals" => array(
						create_goal("45 minutes of activity on at least %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
							'moderate_10', 4, 15, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-stronger/",
						"/core-ball-exercises-for-getting-stronger/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"weight_loss" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-losing-weight/",
						"/month2-exercises-for-losing-weight/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"tone" => array(
					"goals" => array(
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-toned/",
						"/core-ball-exercises-for-getting-toned/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"healthy" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 6 miles at least %d days this month.",
							'distance_6', 4, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/core-ball-exercises-for-being-healthy/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"race" => array(
					"goals" => array(
						create_goal("Go 10 miles at least %d days this month.",
							'distance_10', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 15 miles at least %d days this month.",
							'distance_15', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/coreball-exercises-race/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
			),
			"2016-07" => array(
				"strength" => array(
					"goals" => array(
						create_goal("45 minutes of activity on at least %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
							'moderate_10', 4, 15, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
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
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
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
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-toned/",
						"/core-ball-exercises-for-getting-toned/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"healthy" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 6 miles at least %d days this month.",
							'distance_6', 4, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
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
						create_goal("Go 10 miles at least %d days this month.",
							'distance_10', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 15 miles at least %d days this month.",
							'distance_15', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
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
			),
			"2016-08" => array(
				"strength" => array(
					"goals" => array(
						create_goal("45 minutes of activity on at least %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
							'moderate_10', 4, 15, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-stronger/",
						"/core-ball-exercises-for-getting-stronger/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"weight_loss" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-losing-weight/",
						"/month2-exercises-for-losing-weight/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"tone" => array(
					"goals" => array(
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
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
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 6 miles at least %d days this month.",
							'distance_6', 4, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/core-ball-exercises-for-being-healthy/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"race" => array(
					"goals" => array(
						create_goal("Go 10 miles at least %d days this month.",
							'distance_10', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 15 miles at least %d days this month.",
							'distance_15', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/coreball-exercises-race/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
			),
			"2016-09" => array(
				"strength" => array(
					"goals" => array(
						create_goal("45 minutes of activity on at least %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
							'moderate_10', 4, 15, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-stronger/",
						"/core-ball-exercises-for-getting-stronger/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"weight_loss" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-losing-weight/",
						"/month2-exercises-for-losing-weight/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"tone" => array(
					"goals" => array(
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-toned/",
						"/core-ball-exercises-for-getting-toned/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"healthy" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 6 miles at least %d days this month.",
							'distance_6', 4, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/core-ball-exercises-for-being-healthy/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"race" => array(
					"goals" => array(
						create_goal("Go 10 miles at least %d days this month.",
							'distance_10', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 15 miles at least %d days this month.",
							'distance_15', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/coreball-exercises-race/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
			),
			"2016-10" => array(
				"strength" => array(
					"goals" => array(
						create_goal("45 minutes of activity on at least %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
							'moderate_10', 4, 15, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-stronger/",
						"/core-ball-exercises-for-getting-stronger/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"weight_loss" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-losing-weight/",
						"/month2-exercises-for-losing-weight/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"tone" => array(
					"goals" => array(
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-toned/",
						"/core-ball-exercises-for-getting-toned/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"healthy" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 6 miles at least %d days this month.",
							'distance_6', 4, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/core-ball-exercises-for-being-healthy/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"race" => array(
					"goals" => array(
						create_goal("Go 10 miles at least %d days this month.",
							'distance_10', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 15 miles at least %d days this month.",
							'distance_15', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/coreball-exercises-race/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
			),
			"2016-11" => array(
				"strength" => array(
					"goals" => array(
						create_goal("45 minutes of activity on at least %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
							'moderate_10', 4, 15, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-stronger/",
						"/core-ball-exercises-for-getting-stronger/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"weight_loss" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-losing-weight/",
						"/month2-exercises-for-losing-weight/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"tone" => array(
					"goals" => array(
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-toned/",
						"/core-ball-exercises-for-getting-toned/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"healthy" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 6 miles at least %d days this month.",
							'distance_6', 4, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/core-ball-exercises-for-being-healthy/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"race" => array(
					"goals" => array(
						create_goal("Go 10 miles at least %d days this month.",
							'distance_10', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 15 miles at least %d days this month.",
							'distance_15', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/coreball-exercises-race/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
			),
			"2016-12" => array(
				"strength" => array(
					"goals" => array(
						create_goal("45 minutes of activity on at least %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("%d days of at least 10 minutes cardio (running, biking, hiking, eliptical) this month.",
							'moderate_10', 4, 15, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting stronger we recommend you exercise 3-4 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-stronger/",
						"/core-ball-exercises-for-getting-stronger/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"weight_loss" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of losing weight we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-losing-weight/",
						"/month2-exercises-for-losing-weight/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					)
				),
				"tone" => array(
					"goals" => array(
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_45', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Complete at least 12,000 steps on %d days this month.",
							'steps_12k', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of getting toned we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-getting-toned/",
						"/core-ball-exercises-for-getting-toned/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"healthy" => array(
					"goals" => array(
						create_goal("Be active for at least 30 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 6 miles at least %d days this month.",
							'distance_6', 4, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 60 minutes on %d days this month.",
							'moderate_60', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of being healthy we recommend you exercise 2-3 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/core-ball-exercises-for-being-healthy/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
				"race" => array(
					"goals" => array(
						create_goal("Go 10 miles at least %d days this month.",
							'distance_10', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Be active for at least 45 minutes on %d days this month.",
							'moderate_30', 8, 20, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log water intake on %d days this month.",
							'water_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Log foods for %d days this month.",
							'food_days', 15, 28, $data, $data_last, $days_so_far, $days_in_month),
						create_goal("Go 15 miles at least %d days this month.",
							'distance_15', 5, 16, $data, $data_last, $days_so_far, $days_in_month),
					),
					"recommendation_text" => "Based on your goal of preparing for a race we recommend you exercise 4-5 days per week.",
					"recommendation_links_by_month" => array(
						"/month1-exercises-for-being-healthy/",
						"/coreball-exercises-race/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
						"/resistance-cuff-exercises/",
					),
				),
			),
		);
		/*
		echo '<pre style="font-size: 8px;">';
		var_dump($challenges_by_month[$month_start->format('Y-m')]);
		echo '</pre>';
		*/

		//
		// Select challenges and goals
		//

		$challenges = $challenges_by_month[$month_start->format('Y-m')];

		// Order goals so that more relevant goals are in front
		$ordered_goals = array();
		foreach ($challenges as $challenge_goal=>$challenge) {
			$selected_goals[$challenge_goal] = array();

			// Loop through potential goals and reorder them	
			$one = array();
			$two = array();
			foreach ($challenge["goals"] as $goal_index=>$goal_entry) {
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

		//
		// Calculate personal bests
		//

		function find_last_matching_date($metric, $criteria, $data) {
		}

		$best_metrics = array(
			'moderate_30' => (object) array(
				'display_string' => 'Did at least 30 minutes of excercise on %d separate days.',
				'completion_date' => $so_far_date,
			),
			'steps_max' => (object) array(
				'display_string' => 'Completed %s steps in a single day.',
				'completion_date' => date_plus_days($month_start, $data['steps_max_index']),
			),
			'distance_max' => (object) array(
				'display_string' => 'Traveled %0.1f miles in a single day.',
				'completion_date' => date_plus_days($month_start, $data['distance_max_index']),
			),
			'activity_max' => (object) array(
				'display_string' => 'Active for %d minutes in a single day.',
				'completion_date' => date_plus_days($month_start, $data['activity_max_index']),
			),
		);
		$new_bests = array();
		$bests_this_month = array();
		$previous_bests = array();
		foreach ($best_metrics as $metric => $entry) {
			$bests[$metric] = $result = update_personal_best($user_id, $metric, $data[$metric], $entry->completion_date);
			if ($result->previous_best) {
				$previous_bests[$metric] = $result->previous_best;
				// If this month had a best (even if it was since beaten) still consider it for this month
				if ($result->previous_best->mdate)
					$bests_this_month[$metric] = $result->previous_best;
			}
			if ($result->new_best) {
				$new_bests[$metric] = $result->new_best;
				// If we have a new best, clobber whatever previous value we had
				$bests_this_month[$metric] = $result->new_best;
			}
		}
		$bests_to_beat = array_filter($previous_bests, function($b) use (&$month_start) { return $b->date < $month_start; });

		//
		// Calculate point goals
		//

		$personal_goals = array_slice($ordered_goals[$goal], 0, 2);
		$personal_goals_completed = $personal_goals[0]['analysis']->completed && $personal_goals[1]['analysis']->completed;
		$consistency_goal = goal_analysis($data['moderate_30'], 10, $days_so_far, $days_in_month);
		$personal_best_completed = count($bests_this_month) > 0;
		$steps_goal = goal_analysis($data['steps_10k'], 15, $days_so_far, $days_in_month);
		$logging_goal = goal_analysis($data['food_or_water_days'], 8, $days_so_far, $days_in_month);

		if ($personal_goals_completed) record_points($user_id, 'personal-goals', 40, $month_start);
		if ($consistency_goal->completed) record_points($user_id, 'consistency-goal', 30, $month_start);
		if ($personal_best_completed) record_points($user_id, 'personal-best', 20, $month_start);
		if ($steps_goal->completed) record_points($user_id, 'steps-goal', 5, $month_start);
		if ($logging_goal->completed) record_points($user_id, 'logging-goal', 5, $month_start);

		$month_points = get_month_points($user_id, $month_start);
		$total_points = get_total_points($user_id);

		//
		// Rendering
		//

		function bar_graph($thing_name, $data) {
			/*
			?>
			<span id="<?php echo $thing_name ?>"></span>
			<script>$("#<?php echo $thing_name ?>").sparkline(<?php echo json_encode($data[$thing_name]); ?>, {type: 'bar'});</script>
			<?php
			*/
			echo '<span class="spark-line">'.implode(',', $data[$thing_name]).'</span>';
		}

		function bullet_chart($thing_one, $thing_two, $data, $days_in_month) {
			/*
			?>
				<span id="<?php echo $thing_one ?>"></span>
				<script>$("#<?php echo $thing_one ?>").sparkline(<?php echo json_encode([$data[$thing_two], $data[$thing_one], $days_in_month]) ?>, {type: 'bullet'});</script>
				(<?php echo days_per_week($data[$thing_one], $data[$thing_two]) ?> days per week)
			<?php
			*/
			?>
				<span id="<?php echo $thing_one ?>" class="spark-bullet"><?php echo implode(',', [$data[$thing_two], $data[$thing_one], $days_in_month]) ?></span>
				(<?php echo days_per_week($data[$thing_one], $data[$thing_two]) ?> days per week)
			<?php
		}

		function bullet_raw($one, $two, $three) {
			/*
			$uid = uniqid();
			?> <span id="<?php echo $uid ?>"></span>
				<script>$("#<?php echo $uid ?>").sparkline(<?php echo json_encode([$one, $two, $three]) ?>, {type: 'bullet'});</script>
			<?php
			*/
			echo '<span class="spark-bullet">'.implode(',', [$one,$two,$three]).'</span>';
		}

		function render_goal($goal, $advanced_mode) {
			if ($advanced_mode && isset($goal['order_note'])) {
				?><li <?php if (isset($goal['reserved']) && $goal['reserved']) echo 'style="margin: 2px; border: 1px dashed red; background-color: eee;"'?>> <b style="color:purple;"><?php echo $goal['order_note'] ?></b><br/> <?php 
			} else {
				?><li><?php 
			}
			echo sprintf($goal["description"], $goal["analysis"]->goal);
			echo '<br/>'.$goal["analysis"]->html;
			if ($advanced_mode) {
				echo '<p style="color:#ccc">DPW Goal/So Far/Catchup: ';
				echo sprintf('%0.1f/%0.1f/%0.1f', $goal["analysis"]->dpw_goal, $goal["analysis"]->dpw_so_far, $goal["analysis"]->dpw_catchup);
				if (isset($goal['nominal_level']) && $goal['analysis']->goal > $goal['nominal_level']) {
					echo '&nbsp; <span style="color:#888">Goal adjustment: ';
					echo sprintf('%d &rarr; %d', $goal["nominal_level"], $goal["analysis"]->goal);
					echo '</span>';
				}
				echo '</p>';
			}
			?></li><?php
		}

		//
		// Navigation links
		//
		?>
		<div class="challenge_nav" style="float: right; margin-bottom: 10px;">
			<?php if ($month > 0): ?>
				<a href="?<?php echo modQuery(array('year'=>$last_month_start->format('Y'), 'month'=>$last_month_start->format('n'))) ?>">&larr; <?php echo $last_month_start->format('F') ?></a>
			<?php endif ?>
			&nbsp; <b><?php echo $month_start->format('F') ?></b> &nbsp;
			<?php if (!$showing_current_month): ?>
				<a href="?<?php echo modQuery(array('year'=>$next_month_start->format('Y'), 'month'=>$next_month_start->format('n'))) ?>"><?php echo $next_month_start->format('F') ?> &rarr;</a>
			<?php endif ?>
			<?php if ($real_user_id == 1 || $real_user_id == 167): ?>
				<?php if ($advanced_mode): ?>
				<br/><a style="color: purple;" href="?<?php echo modQuery(array(), array('advanced')) ?>">Normal Mode</a>
				<?php else: ?>
				<br/><a style="color: purple;" href="?<?php echo modQuery(array('advanced'=>'true')) ?>">Advanced Mode</a>
				<?php endif ?>
			<?php endif ?>
		</div>

		<br/>
		<div>
			Challenge Points earned this month: <?php echo $month_points ?>/100</b> <?php bullet_raw($month_points, $month_points, 100) ?> 
			<br/>You have <?php echo $total_points ?> total challenge points.
		</div>
		<br/>

		<style>
		.cb-circle {
			position: relative;
			/*width: 200px;
			padding-bottom: 200px;*/
			width: 90%;
			padding-bottom: 90%;
			margin: auto;
			margin-bottom: 20px;
		}
		.cb-circle .outer-ring {
			position: absolute;
			width: 100%;
			height: 100%;
			background-color: rgb(20, 151, 24);
			border-radius: 50%;
		}
		.cb-circle .white-ring {
			margin-top: 4%;
			margin-left: 4%;
			position: absolute;
			width: 92%;
			height: 92%;
			background-color: #fff;
			border-radius: 50%;
		}
		.cb-circle .center-green {
			margin-top: 3%;
			margin-left: 3%;
			position: absolute;
			width: 94%;
			height: 94%;
			background-color: rgb(20, 151, 24);
			border-radius: 50%;
			text-align: center;
			vertical-align: middle;
			color: #fff;
		}
		.cb-circle .center-green h1 {
			font-size: 300%;
			margin-top: 30%;
			margin-bottom: 5%;
		}
		.cb-circle .center-green span {
			font-size: 16px;
		}
		.points .row {
			margin-bottom: 30px;
		}
		.points .col-md-9 {
			padding-top: 15px;
			padding-bottom: 20px;
			/*border-bottom: 1px solid #ccc;
			*/
		}
		</style>

	<div class="points">

		<div class="row">
			<div class="col-md-2">
				<div class="cb-circle">
					<div class="outer-ring">
						<div class="white-ring">
							<div class="center-green">
								<h1><?php echo $personal_goals_completed ? '+40' : '+0' ?></h1>
								<span>out of 40</span>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-9">
				<h1>Your Personal Challenge</h1>
				<p>Complete your personal monthly challenge to earn these points. <b>40 points possible.</b></p>

		<?php

		if ($advanced_mode) {

			/*
			echo '<br/>';
			var_dump($registration_date);
			echo '<br/>';
			var_dump($is_users_first_month);
			echo '<br/>';
			var_dump($yearmonth_string);
			echo '<br/>';
			var_dump(["month_start"=>$month_start]);
			echo '<br/>';
			var_dump(["month_end"=>$month_end]);

			var_dump((new DateTime("2016-4-1"))->format('t') + 0);
			echo '<br/>';
			var_dump((new DateTime("2016-4-1"))->modify('first day of'));
			echo '<br/>';
			var_dump((new DateTime("2016-4-1"))->modify('last day of'));
			echo '<br/>';
			var_dump((new DateTime("2016-4-1"))->modify('first day of last month'));
			echo '<br/>';
			var_dump((new DateTime("2016-4-1"))->modify('last day of last month'));
			*/

			?>
				
			<strong>Background Info:</strong>
			<br/>Light activity: <?php echo bar_graph('any_activity', $data) ?>
			<br/>Days with at least 30 minutes of light activity: <?php echo bullet_chart('light_30','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 60 minutes of light activity: <?php echo bullet_chart('light_60','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 90 minutes of light activity: <?php echo bullet_chart('light_90','wearing_fitbit',$data,$days_in_month) ?>
			<br/>

			<br/>Moderate activity (Fitbit standard): <?php echo bar_graph('medium_activity', $data) ?>
			<br/>Days with at least 10 minutes of moderate activity: <?php echo bullet_chart('moderate_10','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 30 minutes of moderate activity: <?php echo bullet_chart('moderate_30','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 45 minutes of moderate activity: <?php echo bullet_chart('moderate_45','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 60 minutes of moderate activity: <?php echo bullet_chart('moderate_60','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 90 minutes of moderate activity: <?php echo bullet_chart('moderate_90','wearing_fitbit',$data,$days_in_month) ?>
			<br/>

			<br/>Heavy activity (Fitbit Very Active): <?php echo bar_graph('heavy_activity', $data) ?>
			<br/>Days with at least 10 minutes of heavy activity: <?php echo bullet_chart('heavy_10','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 30 minutes of heavy activity: <?php echo bullet_chart('heavy_30','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 45 minutes of heavy activity: <?php echo bullet_chart('heavy_45','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 60 minutes of heavy activity: <?php echo bullet_chart('heavy_60','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days with at least 90 minutes of heavy activity: <?php echo bullet_chart('heavy_90','wearing_fitbit',$data,$days_in_month) ?>
			<br/>

			<br/>Water consumption: <?php echo bar_graph('water', $data) ?>
			<br/>Days when water was logged: <?php echo bullet_chart('water_days','wearing_fitbit',$data,$days_in_month) ?>
			<br/>

			<br/>Food consumption: <?php echo bar_graph('food', $data) ?>
			<br/>Days when food was logged: <?php echo bullet_chart('food_days','wearing_fitbit',$data,$days_in_month) ?>
			<br/>

			<br/>Distance per day: <?php echo bar_graph('distance', $data) ?>
			<br/>Days you went at least 1 mile: <?php echo bullet_chart('distance_1','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you went at least 2 miles: <?php echo bullet_chart('distance_2','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you went at least 3 miles: <?php echo bullet_chart('distance_3','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you went at least 4 miles: <?php echo bullet_chart('distance_4','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you went at least 5 miles: <?php echo bullet_chart('distance_5','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you went at least 6 miles: <?php echo bullet_chart('distance_6','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you went at least 8 miles: <?php echo bullet_chart('distance_8','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you went at least 10 miles: <?php echo bullet_chart('distance_10','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you went at least 15 miles: <?php echo bullet_chart('distance_15','wearing_fitbit',$data,$days_in_month) ?>
			<br/>

			<br/>Steps per day: <?php echo bar_graph('steps', $data) ?>
			<br/>Days you had at least 8k steps: <?php echo bullet_chart('steps_8k','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you had at least 10k steps: <?php echo bullet_chart('steps_10k','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you had at least 12k steps: <?php echo bullet_chart('steps_12k','wearing_fitbit',$data,$days_in_month) ?>
			<br/>Days you had at least 15k steps: <?php echo bullet_chart('steps_15k','wearing_fitbit',$data,$days_in_month) ?>
			<br/>

			<hr/>

			<?php

		}

		//
		// Render recommendation
		//
		
		if (!isset($challenges_by_month[$month_start->format('Y-m')])) {
			?> <p><b> This month's challenge is not posted yet. Stay tuned! </b></p> <?php
		} 
		else {

			if ($goal) {

				?>
					<strong>
						<?php echo $name ?>'s Personal Challenge for <?php echo $cur_month_name ?>:
					</strong>
					<br />
					<p>Your <?php if ($is_users_first_month) echo "first month's" ?> workout is based on your fitness goal. Each month your challenge will update based on your activity.</p>
				<?php

				//
				// Render challenges
				//
				if ($advanced_mode) {

					// Render all possible personal goals in advanced mode
					foreach ($challenges as $challenge_goal=>$challenge) {
						?>
						<div <?php if ($challenge_goal == $goal): ?>style="background-color: #eee; padding: 40px;"<?php endif ?>>
						<b style="color: purple;"><?php echo $challenge_goal ?></b>
						<ol>
						<?php
						foreach($ordered_goals[$challenge_goal] as $goal_entry)
							render_goal($goal_entry, true);
						?> </ol>
						<?php if ($challenge_goal == 'weight_loss'): ?>
							<p> <strong>Weight Loss Tip:</strong> Weight loss for most people can be reduced to simple math. For every 3500 calories you burn you’ll lose 1 pound. This means to drop a pound per week, each day if you eat 2500 calories, try to burn at least 3,000 calories daily.</p>
						<?php endif ?>
						<p><?php echo $challenge["recommendation_text"]; ?></p> 
						<a class="btn btn-primary custom-button red-btn"
							href="<?php echo $challenge["recommendation_links_by_month"][$month] ?>">View Your Workouts</a>
						</div>
						<?php
					}
				} else {

					// Render normal personal goals
					?> <ol> <?php
					foreach($personal_goals as $goal_entry)
						render_goal($goal_entry, false);
					?> </ol> <?php if ($goal == 'weight_loss'): ?>
						<p> <strong>Weight Loss Tip:</strong> Weight loss for most people can be reduced to simple math. For every 3500 calories you burn you’ll lose 1 pound. This means to drop a pound per week, each day if you eat 2500 calories, try to burn at least 3,000 calories daily.</p>
					<?php endif ?>
					<p><?php echo $challenges[$goal]["recommendation_text"]; ?></p> 
					<a class="btn btn-primary custom-button red-btn"
						href="<?php echo $challenges[$goal]["recommendation_links_by_month"][$month] ?>">View Your Workouts</a>
					</div>
					<?php
				}

			} else {
				?>
					<p>
					<strong>Please <a href="/fitness-profile/">complete your fitness profile</a> to get your personal challenge.</strong>
					</p>
					<a class="btn btn-primary custom-button red-btn" href="/fitness-profile/">Complete your Fitnes Profile</a>
				<?php
			}

			?>
			</div>

			<div class="row">
				<div class="col-md-2">
					<div class="cb-circle">
						<div class="outer-ring">
							<div class="white-ring">
								<div class="center-green">
									<h1><?php echo ($consistency_goal->completed) ? '+30' : '+0' ?></h1>
									<span>out of 30</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-9">
					<h1>Consistency</h1>
					<p>
						At least 10 days with more than 30 active minutes per day to earn these points.
						<br/>
						<?php echo $consistency_goal->html ?>
					</p>
				</div>
			</div>

			<div class="row">
				<div class="col-md-2">
					<div class="cb-circle">
						<div class="outer-ring">
							<div class="white-ring">
								<div class="center-green">
									<h1><?php echo ($personal_best_completed) ? '+20' : '+0' ?></h1>
									<span>out of 20</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-9">
					<h1>Personal Best</h1>
					<p>
						Achieve a personal best this month in daily steps, distance,
						number of active minutes in a single day, or number of days with
						more than 30 active minutes to earn these points.
					</p>

					<?php if ($bests_this_month) { ?>
					<p>
						<b>Bests This Month</b>
						<ul>
							<?php foreach($bests_this_month as $metric => $best): ?>
								<p class="alert alert-success">
									🏆 &nbsp; <?php echo sprintf($best_metrics[$metric]->display_string, $best->mvalue > 1000 ? number_format($best->mvalue) : $best->mvalue) ?>
									<span class="text-muted">(<?php echo $best->mdate->format('F j') ?>)</span> 
								</p>
							<?php endforeach ?>
						</ul>
					</p>
					<?php } ?>
					<?php if ($bests_to_beat) { ?>
					<p>
						<b>Bests to Beat</b>
						<ul>
							<?php foreach($bests_to_beat as $metric => $best): ?>
								<p class="alert alert-info">
									<?php echo sprintf($best_metrics[$metric]->display_string, $best->value > 1000 ? number_format($best->value) : $best->value) ?>
									<span class="text-muted">(<?php echo $best->date->format('F j') ?>)</span> 
								</p>
							<?php endforeach ?>
						</ul>
					</p>
					<?php } ?>
				</div>
			</div>


			<div class="row">
				<div class="col-md-2">
					<div class="cb-circle">
						<div class="outer-ring">
							<div class="white-ring">
								<div class="center-green">
									<h1><?php echo ($steps_goal->completed) ? '+5' : '+0' ?></h1>
									<span>out of 5</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-9">
					<h1>10,000 Steps</h1>
					<p>
						Achieve 10,000 steps on at least 15 days this month to earn these points.
						<br/>
						<?php echo $steps_goal->html ?>
					</p>
				</div>
			</div>

			<div class="row">
				<div class="col-md-2">
					<div class="cb-circle">
						<div class="outer-ring">
							<div class="white-ring">
								<div class="center-green">
									<h1><?php echo ($logging_goal->completed) ? '+5' : '+0' ?></h1>
									<span>out of 5</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-9">
					<h1>Log Points</h1>
					<p>
						Add at least one food or water log on 8 separate days this month to earn these points.
						<br/>
						<?php echo $logging_goal->html ?>
					</p>
				</div>
			</div>

		</div> <!--points-->
			<?php

		}

		return $return;

	} // end fitgoal_auth_func_dev
}

add_shortcode( 'cb_fitbit_challenge_page', array( 'CBChallengeShortcode', 'shortcode' ) );

