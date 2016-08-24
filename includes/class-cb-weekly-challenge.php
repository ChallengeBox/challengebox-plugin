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
	
	private $_leaderboard;

	public function __construct($customer, $date_in_week) {
		$this->customer = $customer;

		$date = Carbon::instance($date_in_week);

		$this->start = $date->copy()->startOfWeek();
		$this->end   = $date->copy()->endOfWeek();

		$this->entry_open   = $date->copy()->startOfWeek()->subWeek()->addDays(2);
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
		if (!$this->user_has_joined) {
			if (!isset($this->metric_level)) {
				$this->metric_level = 1;
			}
			$this->save_user_progress();

			// Track event
			$segment = new CBSegment();
			$segment->track($this->customer, 'Joined Weekly Challenge', array(
				'start_date' => $this->start->format('Y-m-d'),
			));
		}
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

	public function relative_weeks_human($date) {
		$weeks_different = $date->diffInWeeks($this->start, false);
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
		if (!isset($this->_leaderboard)) {
			global $wpdb;
			$key = $this->_cache_key();
			$this->_leaderboard = $wpdb->get_results($wpdb->prepare("
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
		}
		return $this->_leaderboard;
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

	public function format_date($date) {
		return $date->format('l, F jS h:i a T');
	}

	public function format_relative_week() {
		return ucwords($this->relative_weeks_human(Carbon::now($this->start->timezone)->startOfWeek()));
	}

	public function render_header() {
		$start  = $this->format_date($this->start);
		$end    = $this->format_date($this->end);
		$relative_week = $this->format_relative_week();

		if ($this->is_upcoming())    $title = "$relative_week: $this->title";
		if ($this->is_in_progress()) $title = "This week: $this->title</h2>";
		if ($this->is_over())        $title = "$relative_week: $this->title";

		return <<<HTML
		<h2>$title</h2>
		<p> 
				$this->description
				<br/>
				<span style="font-weight: lighter;" class="text-muted small">$start &rarr; $end</span>
		</p>
HTML;
	}

	public function render_join_status() {

		$participants = sizeof($this->get_leaderboard()) - 1;

		if ($this->is_upcoming()) {
			if ($this->user_has_joined) {
				$participating     = "You are participating";
				$others            = "along with $participants others";
				$not_participating = "You are not participating";
				$not_others        = "but $participants people are";
			} else {
				$query = http_build_query(array_merge($_GET, array(
						'date' => $this->start->format('Y-m-d'),
				)));
				$others = "along with $participants others";
				if ($participants > 0) {
					$extra = "&nbsp; $others.";
				} else {
					$extra = "";
				}
				return <<<HTML
				<p>
					<div class="alert alert-warning">
						&nbsp; You have not joined this challenge yet!
						<form method="post" style="display: inline;" action="?$query" class="form-horizontal">
							<input type="hidden" name="join" value="1"></input>
							<button type="submit">Join Challenge</button>
						</form>
						$extra
					</div>
				</p>
HTML;
			}
		}

		if ($this->is_in_progress()) {
			$participating     = "You are participating";
			$others            = "along with $participants others";
			$not_participating = "You are not participating";
			$not_others        = "but $participants people are";
		}

		if ($this->is_over()) {
			$participating     = "You participated";
			$others            = "along with $participants others";
			$not_participating = "You did not participate";
			$not_others        = "but $participants people did";
		}

		if ($this->user_has_joined) {
			return <<<HTML
			<p>
				<div class="label label-success">$participating</div>
				&nbsp; <span style="font-size: small;">$others</span>
			</p>
HTML;
		} else {
			return <<<HTML
			<p>
				<div class="label label-danger">$not_participating</div>
				&nbsp; <span style="font-size: small;">$not_others</span>
			</p>
HTML;
		}

	}

	public function render_leaderboard() {

		$result = "";
		$leaderboard = $this->get_leaderboard();

		if ($leaderboard) {

			$result .= "<p>";
			$result .= '<table class="table table-striped">';

			// Determine our rank index in the leaderboard
			$my_index = null;
			foreach ($leaderboard as $index => $row) {
				if ($row->user_id == $this->customer->get_user_id()) {
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
				$is_me = $cust->get_user_id() == $this->customer->get_user_id();
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

					if ($this->is_upcoming()) {
						$result .= '<td> &mdash; </td>';
					} else {
						$result .= '<td>';
						$result .= number_format($row->meta_value);
						$result .= ' ';
						$result .= explode('_', $this->metric)[0];
						$result .= '</td>';
					}

				$result .= '</tr>';
			}
			$result .= '</table>';
			$result .= "</p>";
		}

		return $result;
	}

	public function render_countdown_clock() {
		$deadline_date = $this->start->format('D M d Y H:i:s O');
		$clockid = uniqid("countdown_clock_");
		return <<<HTML
			<style>
			#$clockid{
				font-family: sans-serif;
				color: #fff;
				display: inline-block;
				font-weight: 100;
				text-align: center;
				font-size: 30px;
				margin-bottom: 20px;
			}

			#$clockid > div{
				padding: 10px;
				border-radius: 3px;
				background: #00BF96;
				display: inline-block;
			}

			#$clockid div > span{
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
			<div class="" id="$clockid">
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
			initializeClock('$clockid', deadline);
			</script>
HTML;
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

		// Join challenge if user wanted to
		if (isset($_POST['join'])) {
			$challenge->join_challenge();
			wp_redirect('?'.http_build_query($_GET));
			exit;
		}

		// If user is checking current challenge, update save progress here
		$challenge->fetch_user_progress();
		if ($challenge->is_in_progress()) {
			$challenge->save_user_progress();
		}

		$next = $challenge->get_next();
		$prev = $challenge->get_previous();

		$next_link = http_build_query(['date' => $next->start->format('Y-m-d')]);
		$prev_link = http_build_query(['date' => $prev->start->format('Y-m-d')]);

		$next_week_open = $next->entry_open->lte(Carbon::now());

		// Use this upcoming variable to create the joing button and clock, regardless
		// of what page we are on.
		$upcoming = null;
		if ($next->is_upcoming()) {
			$upcoming = $next;
		}
		if ($challenge->is_upcoming()) {
			$upcoming = $challenge;
		}

		//
		// Navigation
		//
		$result .= <<<HTML
		<nav class="challenge_nav" style="float: right; clear: both; margin-bottom: 10px;" aria-label="Challenge navigation">
HTML;

		// Previous (limit how far back it can go)
		if ($prev->start->gt(new Carbon("2016-08-01"))) {
			$result .= <<<HTML
				<a href="?$prev_link" aria-label="Previous Week">
					<span aria-hidden="true">&larr;</span>
					Previous
				</a>
HTML;
		}

		// Middle (current week)
		$result .= '&nbsp; <b> ' . $challenge->format_relative_week() . '</b> &nbsp;';

		// Next
		if (
			$next_week_open && (
				$next === $upcoming || $next->is_over() || $next->is_in_progress()
			)
		) {
			$result .= <<<HTML
				<a href="?$next_link" aria-label="Next Week">
					Next
					<span aria-hidden="true">&rarr;</span>
				</a>
HTML;
		}
		$result .= "</nav>";


		// Tease next week's challenge if appropriate
		if ($next_week_open && !$next->user_has_joined) {
			$query = http_build_query(array_merge($_GET, array(
					'date' => $next->start->format('Y-m-d'),
			)));
			$result .= <<<HTML
				<div style="float:left;" class="alert alert-warning">
					&nbsp; Next week's $next->title challenge is open!
					<form method="post" style="display: inline;" action="?$query" class="form-horizontal">
						<input type="hidden" name="join" value="1"></input>
						<button type="submit">Join Now</button>
					</form>
				</div>
HTML;
		}

		// Display current week's challenge
		$result .= $challenge->render_header();
		$result .= $challenge->render_join_status();
		$result .= $challenge->render_leaderboard();
		if ($challenge->is_upcoming()) {
			$result .= $challenge->render_countdown_clock();
		}

		// Display upcoming Challenge, only if we're on the current week page
		if (false && $next === $upcoming && $next_week_open) {
			$result .= $upcoming->render_header();
			$result .= $upcoming->render_join_status();
			// no leaderboarfd
			$result .= $upcoming->render_countdown_clock();
		}

		return $result;
	}

}

add_shortcode( 'cb_weekly_challenge', array( 'CBWeeklyChallenge', 'shortcode' ) );

