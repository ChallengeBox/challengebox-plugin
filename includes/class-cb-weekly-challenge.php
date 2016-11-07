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
	public $settled;

	public $user_has_joined;
	public $previous_metric_level;
	public $metric_level;
	
	private $_leaderboard;

	public function __construct($customer, $date_in_week) {
		$this->customer = $customer;

		$date = Carbon::instance($date_in_week);

		$this->start = $date->copy()->startOfWeek();
		$this->end   = $date->copy()->endOfWeek()->subDay();

		/*
		if ($customer && $customer->get_user_id() === 167) {
			echo '<pre>';
			var_dump(array(
				'$this->date_in_week' => $date_in_week,
				'$this->start' => $this->start,
			));
			echo '</pre>';
		}
		*/

		$this->entry_open   = $date->copy()->startOfWeek()->subWeek(); // ->addDays(4);
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
			$this->settled = empty($data['settled']) ? false : true;
		} else {
			$this->metric = 'steps_total';
			$this->title = 'Total Steps';
			$this->description = 'Compete for getting in the most steps in a week!';
			$this->settled = false;
		}
	}
	public function save_global() {
		$data = update_option(
			$this->_cache_key(),
			array(
				'metric' => $this->metric,
				'title' => $this->title,
				'description' => $this->description,
				'settled' => $this->settled,
			),
			false
		);
	}

	public function load_user_progress() {
		$level = $this->customer->get_meta($this->_cache_key());
		$this->previous_metric_level = isset($level) ? intval($level) : null;
		$this->user_has_joined = isset($level) || $level === 0;
	}
	public function fetch_user_progress() {
		$activity = $this->customer->fitbit()->get_cached_activity_data($this->start, $this->end);
		$this->metric_level = max(1, $activity['metrics'][$this->metric]);
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

	public function get_leaderboard($already_fetched_leaderboard = null) {
		if ($already_fetched_leaderboard) {
			$this->_leaderboard = $already_fetched_leaderboard;
		}
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
					CAST(meta_value AS DECIMAL(10,2)) DESC, umeta_id DESC
				;",
				$this->_cache_key()
			));
		}
		return $this->_leaderboard;
	}

	public function get_rank() {
		foreach ($this->get_leaderboard() as $index => $row) {
			$rank = $index + 1;
			$is_me = $row->user_id == $this->customer->get_user_id();
			if ($is_me) return $rank;
		}
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
				<div class="row">
					<div class="col-md-12 alert alert-warning">
						&nbsp; You have not joined this challenge yet!
						<form method="post" style="display: inline;" action="?$query" class="form-horizontal">
							<input type="hidden" name="join" value="1"></input>
							<button type="submit">Join Challenge</button>
						</form>
						$extra
					</div>
				</div>
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

	public function apply_points() {
		$rank = $this->get_rank();
		$points = $this->points_for_rank($rank);
		$user_id = $this->customer->get_user_id();

		$key = $this->_cache_key() . '_points';
		$previous_points = intval($this->customer->get_meta($key));
		$difference = $points - $previous_points;

		$data = (object) array(
			'week' => $this->start,
			'rank' => $rank,
		);

		if ($difference > 0) {
			WC_Points_Rewards_Manager::increase_points($user_id, $difference, 'weekly-challenge', $data);
		} elseif ($difference < 0) {
			WC_Points_Rewards_Manager::decrease_points($user_id, -$difference, 'weekly-challenge', $data);
		}

		$this->customer->set_meta($key, $points);

		return $points;
	}

	public static function format_points_description($data) {
		$emoji = CBWeeklyChallenge::emoji_for_rank($data->rank);
		$ordinal = CB::ordinal($data->rank);
		return "Weekly challenge: $emoji $ordinal place";
	}

	public static function points_for_rank($rank) {
		if (1 === $rank) return 510;
		if (2 <= $rank && $rank <= 5) return 210;
		if (6 <= $rank && $rank <= 25) return 110;
		return 10;
	}

	public static function emoji_for_rank($rank) {
		//return '🏅';
		if (1 === $rank) return '🏆';
		if (2 <= $rank && $rank <= 5) return '🏅';
		if (6 <= $rank && $rank <= 25) return '🎖';
		return '';
	}

	public function render_congrats() {
		$result = "";
		if ($this->user_has_joined && $this->settled) {
			$rank = $this->get_rank();
			$points = $this->points_for_rank($rank);
			if ($points) {
				$emoji = $this->emoji_for_rank($rank);
				$ordinal = CB::ordinal($rank);
				$nf_points = number_format($points);
				$result .= <<<HTML
				<div class="alert alert-success">
					$emoji You placed $ordinal in this challenge and earned $nf_points challenge points.
				</div>
HTML;
			}
		} elseif ($this->is_over() && !$this->settled) {
				$result .= <<<HTML
					<div class="alert alert-info">
						🕓 Sync your fitness tracker! We're compiling the data and will announce official winners on Monday morning.
					</div>
HTML;
		}
		return $result;
	}

	public function render_leaderboard($email = false) {

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
				$near_top = $index < 30;
				$near_bottom = (sizeof($leaderboard) - 1 - $index) < 3;
				$near_me = abs($index - $my_index) < 2;

				// Skip parts of the table we don't need to show
				if (!($near_top /*|| $near_bottom*/ || $near_me)) {
					if (!$elips) {
						$elips = true;
						$result .= '<tr><td style="text-align: center;" colspan=3> &hellip; </td></tr>';
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

					if (!$email) {
						// Desktop: #Rank -- First L. Sate
						$result .= $is_me ? '<td style="background-color: #d9edf7;" class="hidden-xs">' : '<td class="hidden-xs">';
						$result .= "#$rank &nbsp;&mdash;&nbsp; ";
						$result .= "<b>$name.</b> &nbsp; $state";
						$result .= $is_me ? '&nbsp; <span class="label label-info">you</span>' : '';

						$result .= '</td>';
					}

					// Mobile: #Rank -- First L. ST
					$result .= $is_me ? '<td style="background-color: #d9edf7;" class="visible-xs">' : '<td class="visible-xs">';
					$result .= "#$rank &nbsp;&mdash;&nbsp; ";
					$result .= "<b>$name.</b> &nbsp; $state_ab";
					$result .= $is_me ? '&nbsp; <span class="label label-info">you</span>' : '';
					$result .= '</td>';

					// Steps
					if ($this->is_upcoming()) {
						$result .= $is_me ? '<td style="background-color: #d9edf7;">' : '<td>';
						$result .= ' &mdash; </td>';
					} else {
						if ($row->meta_value <= 1) {
							$result .= $is_me ? '<td style="background-color: #d9edf7;">' : '<td>';
							$result .= ' &mdash; </td>';
						} else {
							$result .= $is_me ? '<td style="background-color: #d9edf7;">' : '<td>';
							$result .= number_format($row->meta_value);
							$result .= ' ';
							$result .= explode('_', $this->metric)[0];
							$result .= '</td>';
						}
					}

					// Rank / Reward
					if ($this->is_over() && $this->settled) {
						$emoji = $this->emoji_for_rank($rank);
						$points = $this->points_for_rank($rank);
						if ($points) {

							if (!$email) {
								// Rank (Desktop)
								$result .= $is_me ? '<td style="background-color: #d9edf7;" class="hidden-xs">' : '<td class="hidden-xs">';
								$result .= $emoji;
								$result .= ' ' . CB::ordinal($rank) . ' place ';
								$result .= ' &nbsp;&mdash;&nbsp; ';
								$result .= number_format($this->points_for_rank($rank));
								$result .= ' challenge points';
								$result .= '</td>';
							}

							// Rank (Mobile)
							$result .= $is_me ? '<td style="background-color: #d9edf7;" class="visible-xs">' : '<td class="visible-xs">';
							$result .= $emoji;
							$result .= number_format($this->points_for_rank($rank));
							$result .= ' points';
							$result .= '</td>';
						} else {
							$result .= $is_me ? '<td style="background-color: #d9edf7;">' : '<td>';
							$result .= '</td>';
						}
					} else {
						$result .= $is_me ? '<td style="background-color: #d9edf7;">' : '<td>';
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
			#$clockid div {
				color: #fff;
			}

			#$clockid {
				font-family: sans-serif;
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
			<div class="row">
				<div class="xcol-md-12" id="$clockid">
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
			</div>
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
		if (isset($_GET['debug'])) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
		}

		CB::login_redirect();

		$result = "";

		$is_admin = current_user_can('shop_manager') || current_user_can('administrator');
		$is_admin = $is_admin && isset($_GET['admin']);

		$timezone = 'America/New_York';

		// Figure out what date we have, otherwise use today
		if (!empty($_GET['date'])) {
			$date_in_week = Carbon::createFromFormat('Y-m-d', $_GET['date'], $timezone);
		} else {
			$date_in_week = Carbon::now($timezone);
		}

		$generic_challenge = new CBWeeklyChallenge(null, Carbon::now());
		$earliest_date = new Carbon('2016-08-29T00:00:00', $timezone);
		$latest_date = $generic_challenge->end->copy()->endOfWeek()->addWeek();

		// Bail if date falls outside our range
		$date_too_early = $date_in_week->lt($earliest_date);
		if ($date_too_early && !$is_admin) {
			wp_redirect('?' . http_build_query(array_merge($_GET, array(
				'date' => $earliest_date->format('Y-m-d')
			)))); 
			exit;
		}
		$date_too_late = $date_in_week->gt($latest_date);
		if ($date_too_late && !$is_admin) {
			wp_redirect('?' . http_build_query(array_merge($_GET, array(
				'date' => $generic_challenge->start->format('Y-m-d')
			)))); 
			exit;
		}

		// Lookup customer and challenges
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
		if ($challenge->is_in_progress() && $challenge->user_has_joined) {
			$challenge->save_user_progress();
		}

		// Fetch other challenges
		$next = $challenge->get_next();
		$prev = $challenge->get_previous();
		$next_link = http_build_query(array_merge($_GET, ['date' => $next->start->format('Y-m-d')]));
		$prev_link = http_build_query(array_merge($_GET, ['date' => $prev->start->format('Y-m-d')]));
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
		$prev_too_early = $prev->start->lt($earliest_date) || $prev->start->gt($latest_date);
		$next_too_late = $next->start->gt($latest_date) || $next->start->lt($earliest_date);

		/*
		if ($customer->get_user_id() === 167) {
			var_dump($date_in_week);
			var_dump($challenge->start);
			var_dump($prev->start);
			var_dump($earliest_date);
		}
		*/

		$result .= <<<HTML
		<nav class="challenge_nav" style="float: right; clear: both; margin-bottom: 10px;" aria-label="Challenge navigation">
HTML;

		// Previous (limit how far back it can go)
		if (!$prev_too_early || $is_admin) {
			$admin_only_text = $is_admin && $prev_too_early ? '<i style="color: purple;">(admin only)</i>' : '';
			$result .= <<<HTML
				<a href="?$prev_link" aria-label="Previous Week">
					<span aria-hidden="true">&larr;</span>
					Previous $admin_only_text
				</a>
HTML;
		}

		// Middle (current week)
		$admin_only_text = ($date_too_early || $date_too_late) && $is_admin ? '<i style="color: purple;">(admin only)</i>' : '';
		$result .= '&nbsp; <b> ' . $challenge->format_relative_week() . "</b> $admin_only_text &nbsp;";

		// Next
		if (
			($next_week_open && (
				$next === $upcoming || $next->is_over() || $next->is_in_progress()
			)) || $is_admin
		) {
			$admin_only_text = $is_admin && $next_too_late ? '<i style="color: purple;">(admin only)</i>' : '';
			$result .= <<<HTML
				<a href="?$next_link" aria-label="Next Week">
					Next $admin_only_text
					<span aria-hidden="true">&rarr;</span>
				</a>
HTML;
		}
		$result .= "</nav>";


		// Tease next week's challenge if appropriate
		if ($challenge->is_in_progress() && $next_week_open && !$next->user_has_joined) {
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
		if ($challenge->is_upcoming()) $result .= $challenge->render_countdown_clock();
		$result .= $challenge->render_join_status();
		$result .= $challenge->render_congrats();
		$result .= $challenge->render_leaderboard();

		// Display upcoming Challenge, only if we're on the current week page
		/*
		if ($next === $upcoming && $next_week_open) {
			$result .= $upcoming->render_header();
			$result .= $upcoming->render_join_status();
			// no leaderboarfd
			$result .= $upcoming->render_countdown_clock();
		}
		*/

		return $result;
	}

}

add_shortcode( 'cb_weekly_challenge', array( 'CBWeeklyChallenge', 'shortcode' ) );

