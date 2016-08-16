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

	public $challenge_start;
	public $challenge_end;

	public $entry_open;
	public $entry_closed;
	
	public function __construct($customer, $date_in_week) {
		$this->customer = $customer;

		$date = Carbon::instance($date_in_week);

		$week_start = $date->copy()->startOfWeek();
		$week_end   = $date->copy()->endOfWeek();

		$last_week_start = $week_start->copy()->subWeek();
		$last_week_end   = $week_end->copy()->subWeek();

		$this->challenge_start = $week_start->copy();
		$this->challenge_end   = $week_end->copy();

		$this->entry_open   = $last_week_start->copy()->addDays(4);
		$this->entry_closed = $last_week_end->copy();
	}

	public function get_previous() {
		return new CBWeeklyChallenge($this->challenge_start->copy()->subWeek());
	}
	public function get_next() {
		return new CBWeeklyChallenge($this->challenge_start->copy()->addWeek());
	}

	public static function shortcode($atts, $content = "") {
		$result = "";
		if (!is_user_logged_in()) return $result;

		// Figure out what date we have, otherwise use today
		$timezone = 'America/New_York';
		if (isset($_GET['date'])) {
			$date_in_week = Carbon::createFromFormat('Y-m-d', $_GET['date'], $timezone);
		} else {
			$date_in_week = new Carbon($timezone);
		}

		$customer = new CBCustomer(get_current_user_id());
		$challenge = new CBWeeklyChallenge($customer, $date_in_week);

		$next_challenge = $challenge->get_next();
		$prev_challenge = $challenge->get_previous();


		$result .= <<<HTML
		<nav aria-label="Challenge navigation">
			<ul class="pagination">
				<li>
					<a href="#" aria-label="Previous Week">
						<span aria-hidden="true">&larr;</span>
					</a>
				</li>
				<li><a href="#">$challenge->challenge_start</a></li>
				<li>
					<a href="#" aria-label="Next Week">
						<span aria-hidden="true">&rarr;</span>
					</a>
				</li>
			</ul>
		</nav>
HTML;

		return $result;
	}

}

add_shortcode( 'cb_weekly_challenge', array( 'CBWeeklyChallenge', 'shortcode' ) );
