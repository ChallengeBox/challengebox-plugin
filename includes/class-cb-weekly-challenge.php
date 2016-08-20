<?php

use Carbon\Carbon;

/**
 * Weekly challenge data and shortcode.
 *
 * @since 1.0.0
 * @package challengebox
 */

class CBWeeklyChallenge {

	public $customer;

	public $start;
	public $end;

	public $entry_open;
	public $entry_closed;

	public $metric;
	public $title;
	public $description;

	public $user_has_joined;
	public $previous_metric_level;
	public $metric_level;
	
	public function __construct($customer, $date_in_week) {
		$this->customer = $customer;

		$date = Carbon::instance($date_in_week);

		$this->start = $date->copy()->startOfWeek();
		$this->end   = $date->copy()->endOfWeek();

		$this->entry_open   = $date->copy()->startOfWeek()->subWeek()->addDays(4);
		$this->entry_closed = $date->copy()->endOfWeek()->subWeek();

		$this->load_global();
		if ($this->customer) $this->load_user_progress();
	}

	private function _cache_key() {
		return 'cb_weekly_challenge_v1_' . $this->start->format('Y-m-d');
	}

	private function load_global() {
		$data = get_option($this->_cache_key());
		if ($data) {
			$this->metric = empty($data['metric']) ? null : $data['metric'];
			$this->title = empty($data['title']) ? null : $data['title'];
			$this->description = empty($data['description']) ? null : $data['description'];
		} else {
			$this->metric = 'steps_total';
			$this->title = 'Total Steps';
			$this->description = 'Compete for getting in the most steps in a week!';
		}
	}
	private function save_global() {
		$data = update_option(
			$this->_cache_key(),
			array(
				'metric' => $this->metric,
				'title' => $this->title,
				'description' => $this->description,
			),
			false
		);
	}

	public function load_user_progress() {
		$level = $this->customer->get_meta($this->_cache_key());
		$this->previous_metric_level = isset($level) ? intval($level) : null;
		$this->user_has_joined = isset($level);
	}
	public function fetch_user_progress() {
		$activity = $this->customer->fitbit()->get_cached_activity_data($this->start, $this->end);
		$this->metric_level = $activity['metrics'][$this->metric];
	}
	public function save_user_progress() {
		$this->customer->set_meta($this->_cache_key(), $this->metric_level);
		$this->user_has_joined = true;
	}

	public function join_challenge() {
		if (!isset($this->metric_level)) {
			$this->metric_level = 1;
		}
		$this->save_user_progress();
	}

	public function get_previous() {
		return new CBWeeklyChallenge($this->customer, $this->start->copy()->subWeek());
	}
	public function get_next() {
		return new CBWeeklyChallenge($this->customer, $this->start->copy()->addWeek());
	}

	public function is_in_progress($as_of = null) {
		$as_of = empty($as_of) ? Carbon::now() : Carbon::instance($as_of);
		return $as_of->between($this->start, $this->end);
	}
	public function is_upcoming($as_of = null) {
		$as_of = empty($as_of) ? Carbon::now() : Carbon::instance($as_of);
		return $as_of->lt($this->start);
	}
	public function is_over($as_of = null) {
		$as_of = empty($as_of) ? Carbon::now() : Carbon::instance($as_of);
		return $as_of->gt($this->end);
	}

	public function relative_weeks_human($other_challenge) {
		$weeks_different = $this->start->diffInWeeks($other_challenge->start, false);
		if ($weeks_different == -1) {
			return 'last week';
		} if ($weeks_different == 1) {
			return 'next week';
		} if ($weeks_different > 0) {
			return "$weeks_different weeks from now";
		} if ($weeks_different < 0) {
			$weeks_different = abs($weeks_different);
			return "$weeks_different weeks ago";
		} else {
			return 'this week';
		}
	}

	public function get_leaderboard() {
		global $wpdb;
		$key = $this->_cache_key();
		$results = $wpdb->get_results($wpdb->prepare("
			SELECT 
				user_id, meta_value 
			FROM
				$wpdb->usermeta
			WHERE
				meta_key = %s
			ORDER BY
				CAST(meta_value AS DECIMAL(10,2)) DESC
			;",
			$this->_cache_key()
		));
		return $results;
	}

	public static function get_state_name_from_abbr($abbr) {
		$ABBR = strtoupper($abbr);
		$states =  array(
				'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
				'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut',
				'DE' => 'Delaware', 'DC' => 'Washington DC',
				'FL' => 'Florida',
				'GA' => 'Georgia',
				'HI' => 'Hawaii',
				'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
				'KS' => 'Kansas', 'KY' => 'Kentucky',
				'LA' => 'Louisiana',
				'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan',
				'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana',
				'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
				'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
				'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon',
				'PA' => 'Pennsylvania', 'PR' => 'Puerto Rico',
				'RI' => 'Rhode Island',
				'SC' => 'South Carolina', 'SD' => 'South Dakota',
				'TN' => 'Tennessee', 'TX' => 'Texas',
				'UT' => 'Utah',
				'VT' => 'Vermont', 'VI' => 'Virgin Islands', 'VA' => 'Virginia', 'WA' => 'Washington',
				'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
		);
		return $states[$ABBR];
	}

	public static function handle_post() {
	}

	public static function shortcode($atts, $content = "") {
		$atts = shortcode_atts(array('debug' => false), $atts);
		if ($a['debug']) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
		}
		error_reporting(E_ALL);
		ini_set('display_errors', true);

		$result = "";

		if (is_user_logged_in()) {}
		else return $result;

		$is_admin = current_user_can('shop_manager') || current_user_can('administrator');

		// Figure out what date we have, otherwise use today
		$timezone = 'America/New_York';
		if (!empty($_GET['date'])) {
			$date_in_week = Carbon::createFromFormat('Y-m-d', $_GET['date'], $timezone);
		} else {
			$date_in_week = Carbon::now($timezone);
		}

		// Lookup customer and challenge
		$customer = new CBCustomer(get_current_user_id());
		$challenge = new CBWeeklyChallenge($customer, $date_in_week);
		$challenge->fetch_user_progress();
		if ($challenge->is_in_progress()) {
			$challenge->save_user_progress(); // update here so the results show up in leaderboard
		}																		// but don't bother for old challenges

		// Use this to compare challenges, regardless of where we are in navigation
		$present_week = new CBWeeklyChallenge($customer, Carbon::now($timezone));

		$next = $challenge->get_next();
		$prev = $challenge->get_previous();

		// Support for joining next challenge
		if (isset($_GET['join'])) {
			$next->join_challenge();
		}

		$next_link = http_build_query(['date' => $next->start->format('Y-m-d')]);
		$prev_link = http_build_query(['date' => $prev->start->format('Y-m-d')]);
		$relative_week_human = ucwords($present_week->relative_weeks_human($challenge));

		$leaderboard = $challenge->get_leaderboard();
		$participants = sizeof($leaderboard);

		$next_leaderboard = $next->get_leaderboard();
		$next_participants = sizeof($next_leaderboard);

		//$result .= "<p>Currently viewing challenge for " . $relative_week_human . ".</p>";

		// Admin only
		if ($is_admin && empty($challenge->metric)) {
		$result .= <<<HTML
			<div class="alert alert-warning" role="alert">
				This challenge has not been created. It is invisible to users.
			</div>
HTML;
		}

		//
		// Navigation
		//
		$result .= <<<HTML
		<nav class="challenge_nav" style="float: right; margin-bottom: 10px;" aria-label="Challenge navigation">
HTML;

		// Limit backward nav
		if ($prev->start->gt(new Carbon("2016-08-01"))) {
			$result .= <<<HTML
				<a href="?$prev_link" aria-label="Previous Week">
					<span aria-hidden="true">&larr;</span>
					Previous
				</a>
HTML;
		}

		$result .= <<<HTML
			&nbsp;
			<b>$relative_week_human</b>
			&nbsp;
HTML;

		if (!$next->is_upcoming()) {
			$result .= <<<HTML
				<a href="?$next_link" aria-label="Next Week">
					Next
					<span aria-hidden="true">&rarr;</span>
				</a>
HTML;
		}
		$result .= "</nav>";



		$start_end_format = 'l, F jS h:i a T';

		//
		// Description
		//
		if ($challenge->is_upcoming()) {
			$result .= "<h2>$relative_week_human: $challenge->title</h2>";
		}
		if ($challenge->is_in_progress()) {
			$result .= "<h2>This week: $challenge->title</h2>";
		}
		if ($challenge->is_over()) {
			$result .= "<h2>$relative_week_human: $challenge->title</h2>";
		}

		$result .= "<p>";
		$result .= "$challenge->description";
		$result .= '<br/><span style="font-weight: lighter;" class="text-muted small">' . $challenge->start->format($start_end_format) . " &rarr; " . $challenge->end->format($start_end_format) . '</span>';
		$result .= "</p>";

		$result .= "<p>";
		if ($challenge->is_upcoming()) {
			$result .= $challenge->user_has_joined ? '<div class="label label-success">You are participating</div>' : '<div class="label label-danger">You are not participating</div>';
			$result .= "&nbsp; <span style=\"font-size: small;\">";
			$result .= $challenge->user_has_joined ? "along with $participants others" : "but $participants people are";
			$result .= ".</span>";
		}
		if ($challenge->is_in_progress()) {
			$result .= $challenge->user_has_joined ? '<div class="label label-success">You are participating</div>' : '<div class="label label-danger">You are not participating</div>';
			$result .= "&nbsp; <span style=\"font-size: small;\">";
			$result .= $challenge->user_has_joined ? "along with $participants others" : "but $participants people are";
			$result .= ".</span>";
		}
		if ($challenge->is_over()) {
			$result .= $challenge->user_has_joined ? '<div class="label label-success">You participated</div>' : '<div class="label label-danger">You did not participate</div>';
			$result .= "&nbsp; <span style=\"font-size: small;\">";
			$result .= $challenge->user_has_joined ? "along with $participants others" : "but $participants people did";
			$result .= ".</span>";
		}
		$result .= "</p>";


		//
		// Leaderboard
		//
		if ($leaderboard) {

			$result .= "<p>";
			$result .= '<table class="table table-striped">';

			// Determine our rank index in the leaderboard
			$my_index = null;
			foreach ($leaderboard as $index => $row) {
				if ($row->user_id == $customer->get_user_id()) {
					$my_index = $index;
				}
			}
	
			// Render table
			foreach ($leaderboard as $index => $row) {
				$near_top = $index < 3;
				$near_bottom = (sizeof($leaderboard) - 1 - $index) < 3;
				$near_me = abs($index - $my_index) < 2;

				// Skip parts of the table we don't need to show
				if (!($near_top /*|| $near_bottom*/ || $near_me)) {
					if (!$elips) {
						$elips = true;
						$result .= '<tr><td colspan=2> &hellip; </td></tr>';
					}
					continue;
				} else {
					$elips = false;
				}

				$rank = $index + 1;
				$cust = new CBCustomer($row->user_id);
				$is_me = $cust->get_user_id() == $customer->get_user_id();
				$name = ucfirst($cust->get_meta('first_name')).' '.ucfirst($cust->get_meta('last_name')[0]);
				$state_ab = $cust->get_meta('shipping_state', $cust->get_meta('billing_state'));
				$state = CBWeeklyChallenge::get_state_name_from_abbr($state_ab);

				$result .= $is_me ? '<tr style="background-color: #d9edf7; border-color: #bce8f1; color: #31708f;">' : '<tr>';

					$result .= '<td class="hidden-xs">';
					$result .= "#$rank &nbsp;&mdash;&nbsp; ";
					$result .= "<b>$name.</b> &nbsp; $state";
					$result .= $is_me ? '&nbsp; <span class="label label-info">you</span>' : '';
					$result .= '</td>';

					$result .= '<td class="visible-xs">';
					$result .= "#$rank &nbsp;&mdash;&nbsp; ";
					$result .= "<b>$name.</b> &nbsp; $state_ab";
					$result .= $is_me ? '&nbsp; <span class="label label-info">you</span>' : '';
					$result .= '</td>';

					$result .= '<td>';
					$result .= number_format($row->meta_value);
					$result .= ' ';
					$result .= explode('_', $challenge->metric)[0];
					$result .= '</td>';

				$result .= '</tr>';
			}
			$result .= '</table>';
			$result .= "</p>";
		}

		//
		// Upcoming Challenge
		//

		if ($next->is_upcoming()) {
			$result .= "<br/>";
			$result .= "<br/>";
			$result .= "<h2>Next week: $next->title</h2>";

			$result .= "<p>";
			$result .= "$next->description";
			$result .= '<br/><span style="font-weight: lighter;" class="text-muted small">' . $next->start->format($start_end_format) . " &rarr; " . $next->end->format($start_end_format) . '</span>';
			$result .= "</p>";

			// Join button
			if ($next->user_has_joined) {
				$result .= "<p>";
				$result .= $next->user_has_joined ? '<div class="label label-success">You are participating</div>' : '<div class="label label-danger">You are not participating</div>';
				$result .= "&nbsp; <span style=\"font-size: small;\">";
				$result .= $next->user_has_joined ? "along with $next_participants others" : "but $next_participants people are";
				$result .= ".</span>";
				$result .= "</p>";
			} else {
				$result .= '<p>';
				$result .= '<div class="alert alert-warning">';
				$result .= 'You have not joined this challenge yet!';
				$result .= '&nbsp; <a class="btn btn-primary btn-large" href="?'.http_build_query(array_merge($_GET, ['join'=>true])).'">Join Challenge</a>';
				$result .= $next_participants > 1 ? "&nbsp; along with $next_participants others." : '';
				$result .= '</div>';
				$result .= '</p>';
			}

			// Countdown clock
			$deadline_date = $next->start->format('D M d Y H:i:s O');
			$result .= <<<HTML
				<style>
				#clockdiv{
					font-family: sans-serif;
					color: #fff;
					display: inline-block;
					font-weight: 100;
					text-align: center;
					font-size: 30px;
					margin-bottom: 20px;
				}

				#clockdiv > div{
					padding: 10px;
					border-radius: 3px;
					background: #00BF96;
					display: inline-block;
				}

				#clockdiv div > span{
					padding: 15px;
					border-radius: 3px;
					background: #00816A;
					display: inline-block;
					font-size: 24px;
				}

				.smalltext{
					padding-top: 5px;
					font-size: 16px;
				}
				</style>
				<p>
				<div class="" id="clockdiv">
					<div>
						<span class="days"></span>
						<div class="smalltext">Days</div>
					</div>
					<div>
						<span class="hours"></span>
						<div class="smalltext">Hours</div>
					</div>
					<div>
						<span class="minutes"></span>
						<div class="smalltext">Minutes</div>
					</div>
					<div>
						<span class="seconds"></span>
						<div class="smalltext">Seconds</div>
					</div>
				</div>
				</p>
				<script>
				function getTimeRemaining(endtime) {
					var t = Date.parse(endtime) - Date.parse(new Date());
					var seconds = Math.floor((t / 1000) % 60);
					var minutes = Math.floor((t / 1000 / 60) % 60);
					var hours = Math.floor((t / (1000 * 60 * 60)) % 24);
					var days = Math.floor(t / (1000 * 60 * 60 * 24));
					return {
						'total': t,
						'days': days,
						'hours': hours,
						'minutes': minutes,
						'seconds': seconds
					};
				}

				function initializeClock(id, endtime) {
					var clock = document.getElementById(id);
					var daysSpan = clock.querySelector('.days');
					var hoursSpan = clock.querySelector('.hours');
					var minutesSpan = clock.querySelector('.minutes');
					var secondsSpan = clock.querySelector('.seconds');

					function updateClock() {
						var t = getTimeRemaining(endtime);

						daysSpan.innerHTML = ('0' + t.days).slice(-2);
						hoursSpan.innerHTML = ('0' + t.hours).slice(-2);
						minutesSpan.innerHTML = ('0' + t.minutes).slice(-2);
						secondsSpan.innerHTML = ('0' + t.seconds).slice(-2);

						if (t.total <= 0) {
							clearInterval(timeinterval);
						}
					}

					updateClock();
					var timeinterval = setInterval(updateClock, 1000);
				}

				var deadline = new Date(Date.parse('$deadline_date'));
				initializeClock('clockdiv', deadline);
				</script>
HTML;

		}

		// Leaderboard

		return $result;
	}

}

add_shortcode( 'cb_weekly_challenge', array( 'CBWeeklyChallenge', 'shortcode' ) );

