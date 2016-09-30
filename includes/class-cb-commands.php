<?php

use \Carbon\Carbon;
use ChallengeBox\Includes\Utilities\BaseFactory;
use Aws\Credentials\CredentialProvider;
use Aws\S3\S3Client;

/**
 * Commands for managing ChallengeBox.
 */
class CBCmd extends WP_CLI_Command {

	private $api;
	private $options;
	private $s3Client;
	private $carbon;

	public function __construct() {
		$this->api = new CBWoo();
	}

	private function parse_args($args, $assoc_args) {
		$tz = !empty($assoc_args['timezone']) ? $assoc_args['timezone'] : 'America/New_York';
		$now = Carbon::now($tz);
		$this->options = (object) array(
			'all' => !empty($assoc_args['all']),
			'timezone' => $tz,
			'orientation' => !empty($assoc_args['orientation']) ? $assoc_args['orientation'] : 'user',
			'reverse' => !empty($assoc_args['reverse']),
			'pretend' => !empty($assoc_args['pretend']),
			'verbose' => !empty($assoc_args['verbose']),
			'continue' => !empty($assoc_args['continue']),
			'overwrite' => !empty($assoc_args['overwrite']),
			'force' => !empty($assoc_args['force']),
			'skip' => !empty($assoc_args['skip']),
			'format' => !empty($assoc_args['format']) ? $assoc_args['format'] : 'table',
			'date' => !empty($assoc_args['date']) ? new Carbon($assoc_args['date'], $tz) : $now->copy(),
			'day' => intval(!empty($assoc_args['day']) ? $assoc_args['day'] : $now->format('d')),
			'renewal_cutoff' => !empty($assoc_args['renewal-cutoff']) ? new Carbon($assoc_args['renewal-cutoff'], $tz) : $now->copy(),
			'month' => !empty($assoc_args['month']) ? new Carbon($assoc_args['month'], $tz) : $now->copy(),
			'sku_version' => !empty($assoc_args['sku_version']) ? $assoc_args['sku_version'] : 'v3',
			'sku' => !empty($assoc_args['sku']) ? $assoc_args['sku'] : null,
			'limit' => !empty($assoc_args['limit']) ? intval($assoc_args['limit']) : false,
			'skip' => !empty($assoc_args['skip']) ? intval($assoc_args['skip']) : false,
			'points' => !empty($assoc_args['points']) ? intval($assoc_args['points']) : false,
			'no_points' => !empty($assoc_args['no_points']),
			'credit' => !empty($assoc_args['credit']) ? intval($assoc_args['credit']) : 1,
			'adjustment' => !empty($assoc_args['adjustment']) ? intval($assoc_args['adjustment']) : false,
			'note' => !empty($assoc_args['note']) ? $assoc_args['note'] : false,
			'segment' => !empty($assoc_args['segment']) ? $assoc_args['segment'] : false,
			'flatten' => !empty($assoc_args['flatten']),
			'settle' => !empty($assoc_args['settle']),
			'rush' => !empty($assoc_args['rush']),
			'auto' => !empty($assoc_args['auto']),
			'revenue' => !empty($assoc_args['revenue']),
			'bonus' => !empty($assoc_args['bonus']),
			'save' => !empty($assoc_args['save']),
			'series' => !empty($assoc_args['series']) ? $assoc_args['series'] : 'water',
			'channel' => !empty($assoc_args['channel']) ? $assoc_args['channel'] : '@ryan',
			'save_output' => !empty($assoc_args['save_output']),
			's3_bucket' => !empty($assoc_args['s3_bucket']) ? $assoc_args['s3_bucket'] : 'challengebox-redshift',
		);

		// Month option should be pegged to the first day
		if (empty($assoc_args['month'])) {
			$this->options->month->modify('first day of next month');
		}
		$this->options->month->setDate(
			$this->options->month->format('Y'),
			$this->options->month->format('m'),
			1
		);
		$this->options->month->setTime(0, 0);

		if (empty($assoc_args['renewal-cutoff'])) {
			$this->options->renewal_cutoff = Carbon::instance($this->options->month);
			$this->options->renewal_cutoff->subMonth()->endOfMonth();
		}

		// All option triggers gathering user ids
		if ($this->options->all) {
			unset($assoc_args['all']);

			if ('user' == $this->options->orientation) {
				WP_CLI::debug("Grabbing user ids...");
				$args = array_map(function ($user) { return $user->ID; }, get_users());
			} elseif ('order' == $this->options->orientation) {
				WP_CLI::debug("Grabbing order ids...");
				global $wpdb;
				$rows = $wpdb->get_results("select ID from wp_posts where post_type = 'shop_order';");
				$args = array_map(function ($p) { return $p->ID; }, $rows);
			} elseif ('subscription' == $this->options->orientation) {
				WP_CLI::debug("Grabbing subscription ids...");
				global $wpdb;
				$rows = $wpdb->get_results("select ID from wp_posts where post_type = 'shop_subscription';");
				$args = array_map(function ($p) { return $p->ID; }, $rows);
			}
		}
		sort($args);
		if ($this->options->reverse) {
			$args = array_reverse($args);
		}

		// Skip option affects incoming args
		if ($this->options->skip) {
			$args = array_slice($args, $this->options->skip);
		}

		// Limit option affects incoming args
		if ($this->options->limit) {
			$args = array_slice($args, 0, $this->options->limit);
		}

		if ($this->options->verbose) {
			WP_CLI::debug(var_export($this->options, true));
		}

		if ($this->options->save_output) {
			$provider = CredentialProvider::ini('redshift', '/home/www-data/.aws/credentials');
			$provider = CredentialProvider::memoize($provider);
			$this->s3Client = new S3Client(array(
				//'profile' => 'redshift',
				'region' => 'us-east-1',
				'version' => 'latest',
				'credentials' => $provider
			));
		}

		//var_dump(array('args'=>$args, 'assoc_args'=>$assoc_args));
		return array($args, $assoc_args);
	}

	private function upload_results_to_s3($file_path, $results, $columns, $gzip = true) {
		$fp = fopen('php://temp', 'rw');
		WP_CLI\Utils\write_csv($fp, $results, $columns);
		rewind($fp);
		if ($gzip) $content = gzencode(stream_get_contents($fp));
		else $content = stream_get_contents($fp);
		$result = $this->s3Client->putObject([
				'Bucket' => $this->options->s3_bucket,
				'Key'    => "$file_path",
				'Body'   => $content
		]);
		fclose($fp);
	}

	private function execute_redshift_queries($queries) {
		WP_CLI::debug("Connecting to redshift...");
		$db = pg_connect(file_get_contents('/home/www-data/.aws/redshift.string'))
				or WP_CLI::error(pg_last_error());
		foreach ($queries as $query) {
			//if ($this->options->verbose) 
			WP_CLI::debug("Executing $query");
			pg_query($query) or WP_CLI::error(pg_last_error());
		}
	}

	/**
	 * Identifies a user to Segment without having them visit the website.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) to migrate.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <id>... if found).
	 *
	 * [--revenue]
	 * : Include revenue data.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb identify 167
	 */
	function identify($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array();
		$segment = new CBSegment();

		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id");
			$customer = new CBCustomer($user_id, $interactive = false);
			$data = $segment->identify($customer, $this->options->pretend, $this->options->revenue);
			$result = array_merge(array('id' => $user_id), $data['traits']);
			array_push($results, $result);
			$columns = array_unique(array_merge($columns, array_keys($result)));
		}

		$segment->flush();

		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Rebuilds user metadata (in wp_usermeta) from source tables.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) to migrate.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <id>... if found).
	 *
	 * [--pretend]
	 * : Don't actually do any migration.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--continue]
	 * : Continue, even if errors are encountered on a given user.
	 *
	 * [--overwrite]
	 * : Overwrite values in database, even if there is data there.
	 *
	 * [--skip]
	 * : Skip users that have a value for clothing_gender in their metadata.
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb update_user_metadata 167
	 */
	function update_user_metadata($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();

		foreach ($args as $user_id) {

			WP_CLI::debug("User $user_id");
			$customer = new CBCustomer($user_id, $interactive = false);

			if ($this->options->skip && $customer->get_meta('active_subscriber')) {
				WP_CLI::debug("\tSkipping, already an active subscriber.");
			}

			$result = array(
				'id' => $user_id,
				'clothing_before' => $customer->get_meta('clothing_gender'),
				'tshirt_before' => $customer->get_meta('tshirt_size'),
				'box_before' => $customer->get_meta('box_month_of_latest_order') + 0,
				'active_before' => $customer->get_meta('active_subscriber'),
				'renewal_before' => $customer->get_meta('renewal_date') ? $customer->get_meta('renewal_date')->format('Y-m-d') : null,
				'status_before' => $customer->get_meta('subscription_status'),
				'type_before' => $customer->get_meta('subscription_type'),
				'points_before' => $customer->get_meta('wc_points_balance'),
				'oauth_before' => $customer->get_meta('fitbit_oauth_status'),
				'rush_before' => $customer->get_meta('has_rush_order'),
				'cohort_before' => $customer->get_meta('mrr_cohort'),
			);

			try {

				$errors = $customer->update_metadata($this->options->overwrite, $this->options->pretend);

				$result = array_merge($result, array(
					'clothing_after' => $customer->get_meta('clothing_gender'),
					'tshirt_after' => $customer->get_meta('tshirt_size'),
					'box_after' => $customer->get_meta('box_month_of_latest_order') + 0,
					'active_after' => $customer->get_meta('active_subscriber'),
					'renewal_after' => $customer->get_meta('renewal_date') ? $customer->get_meta('renewal_date')->format('Y-m-d') : null,
					'status_after' => $customer->get_meta('subscription_status'),
					'type_after' => $customer->get_meta('subscription_type'),
					'points_after' => $customer->get_meta('wc_points_balance'),
					'oauth_after' => $customer->get_meta('fitbit_oauth_status'),
					'rush_after' => $customer->get_meta('has_rush_order'),
					'cohort_after' => $customer->get_meta('mrr_cohort'),
					'has_order' => $customer->has_box_order_this_month(),
					'#orders' => sizeof($customer->get_orders()),
					'#box_orders' => sizeof($customer->get_box_orders()),
					'#shipped' => sizeof($customer->get_orders_shipped()),
					'#box_shipped' => sizeof($customer->get_box_orders_shipped()),
					'#subs' => sizeof($customer->get_subscriptions()),
					'error' => implode(', ', array_map(function ($e) { return $e->getMessage(); }, $errors)),
				));

			} catch (Exception $e) {
				WP_CLI::debug("\tError: " . $e->getMessage());	

				$result = array_merge($result, array(
					'clothing_after' => NULL,
					'tshirt_after' => NULL,
					'box_after' => NULL,
					'active_after' => NULL,
					'renewal_after' => NULL,
					'status_after' => NULL,
					'type_after' => NULL,
					'points_after' => NULL,
					'oauth_after' => NULL,
					'rush_after' => NULL,
					'cohort_after' => NULL,
					'has_order' => NULL,
					'#orders' => NULL,
					'#box_orders' => NULL,
					'#shipped' => NULL,
					'#box_shipped' => NULL,
					'#subs' => NULL,
					'error' => $e->getMessage(),
				));

			}

			array_push($results, $result);
			if ($this->options->verbose) {
				WP_CLI::debug(var_export($result, true));
			}
		}

		$columns = array(
			'id',
			'clothing_before', 'clothing_after',
			'tshirt_before', 'tshirt_after',
			'box_before', 'box_after',
			'active_before', 'active_after',
			'renewal_before', 'renewal_after',
			'status_before', 'status_after',
			'type_before', 'type_after',
			'points_before', 'points_after',
			'oauth_before', 'oauth_after',
			'rush_before', 'rush_after',
			'cohort_before', 'cohort_after',
			'has_order',
			'#orders',
			'#box_orders',
			'#shipped',
			'#box_shipped',
			'#subs',
			'error'
		);
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Synchronizes renewal dates for active subscriptions.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to adjust renewal date.
	 *
	 * [--day=<day>]
	 * : Day of the month on which to synchronize renewal. Defaults to the 21st
	 *   for users who joined on orafter July 1st 2016 and the 25th for users
	 *   who joined before that.
	 *
	 * [--all]
	 * : Adjust dates for all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--reverse]
	 * : Iterate users in reverse order.
	 *
	 * [--pretend]
	 * : Don't actually adjust dates, just print out what we'd do.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb synchronize_renewal 167
	 */
	function synchronize_renewal($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);
		$user_day_override = isset($assoc_args['day']);

		$results = array();
		$columns = array('id', 'sub_id', 'type', 'action', 'reason', 'old_renewal', 'new_renewal', 'box_credit_renewal', 'selected_renewal', 'renewal_day', 'credits', 'debits', 'box_this_month', 'next_box', 'errors');

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			$next_box = $customer->get_meta('next_box');

			WP_CLI::debug("Synchronizing $user_id");

			//var_dump($this->api->get_customer_subscriptions_internal($user_id));

			//
			// Setup renewal day (depends on user join date unless overridden)
			//
			if ($user_day_override) {
				$renewal_day = $this->options->day;
			} else {
				// Spec: If the customer joined before July 1, the renewal day should
				// be the 25th of the month.
				if ($customer->earliest_subscription_date() < new Carbon("2016-07-01")) {
					$renewal_day = 25;
				} else {
					// Spec: If the customer joined on or after July 1, the renewal day
					// should be the 21st of the month.
					$renewal_day = 21;
				}
			}

			//
			// Setup box credits / debits
			//
			$r = $customer->calculate_box_credits($this->options->verbose);
			$credits = $r['credits'];
			$revenue = $r['revenue'];
			$debits = $customer->calculate_box_debits($this->options->verbose);
			$box_this_month = $customer->has_box_order_this_month();


			if (sizeof($customer->get_active_subscriptions()) == 0) {
				WP_CLI::debug("\tNo active subscriptions. Skipping.");
				$results[] = array(
					'id' => $user_id,
					'sub_id' => $sub->id,
					'type' => $customer->get_subscription_type(),
					'action' => 'skip',
					'reason' => 'no active subs',
					'old_renewal' => NULL,
					'new_renewal' => NULL,
					'box_credit_renewal' => NULL,
					'selected_renewal' => NULL,
					'renewal_day' => $renewal_day,
					'credits' => $credits,
					'debits' => $debits,
					'box_this_month' => $box_this_month,
					'next_box' => $next_box,
					'errors' => NULL,
				);
				continue;
			}

			//
			// Modify renewal date for each sub
			//
			foreach ($customer->get_active_subscriptions() as $sub) {

				// Skip if we don't have a renewal date to work from
				if (empty($sub->billing_schedule->next_payment_at)) {
					WP_CLI::debug("\t\tSubscription doesn't have renewal date. Skipping.");
					$results[] = array(
						'id' => $user_id,
						'sub_id' => $sub->id,
						'type' => $customer->get_subscription_type(),
						'action' => 'skip',
						'reason' => 'no renewal date',
						'old_renewal' => NULL,
						'new_renewal' => NULL,
						'box_credit_renewal' => NULL,
						'selected_renewal' => NULL,
						'renewal_day' => $renewal_day,
						'credits' => $credits,
						'debits' => $debits,
						'box_this_month' => $box_this_month,
						'next_box' => $next_box,
						'errors' => NULL,
					);
					continue;
				}

				$old_renewal = Carbon::instance(CBWoo::parse_date_from_api($sub->billing_schedule->next_payment_at));

				WP_CLI::debug("\tSubscription $sub->id...");

				//
				// Calculate correct renewal date from the renewal day
				//

				// Potential renewal date in the same month as original renewal
				$d_this = $old_renewal->copy()->day($renewal_day);

				// Potential renewal date in the month after original renewal
				$d_next = $old_renewal->copy()->day(1)->addMonths(1)->day($renewal_day);
				
				if ($this->options->verbose)
					WP_CLI::debug(var_export(array('d_this' => $d_this, 'd_next' => $d_next, 'renewal_day' => $renewal_day), true));

				// Spec: If the customer's renewal date falls before the renewal day,
				// the renewal should be pushed forward to the renewal day.
				if ($old_renewal < $d_this) {
					$new_renewal = $d_this;
					$reason = 'old renewal before renewal day';
				} else {
					// Spec: If the customer's renewal date falls after the renewal day,
					// the renewal should be pushed forward to the renewal day next
					// month.
					$new_renewal = $d_this;
					$reason = 'old renewal after renewal day';
				}

				//
				// Modify renewal date if user has chosen to postpone box, and if
				// the postponement month is after the renewal we already chose
				//
				if ($next_box) {
					$next_box_date = new Carbon($next_box);
					if ($next_box_date >= $new_renewal) {
						$new_renewal->year = $next_box_date->year;
						$new_renewal->month = $next_box_date->month;
						WP_CLI::debug("\tOrder will be postponed until " . $new_renewal->format('Y-m-d'));
						$reason = 'user postponed box';
					}
				}

				//
				// See if the box credit model thinks we should bill on a different date
				//
				$months_before_renewal = $credits - $debits + ($box_this_month ? 0 : -1);
				$now = Carbon::now();
				$box_credit_renewal = $new_renewal->copy()->year($now->year)->month($now->month)->addMonths($months_before_renewal);


				// Adopt the box-credit model if it's sooner, but also still in the future
				if ($box_credit_renewal->lte($new_renewal) && $box_credit_renewal->gt(Carbon::now())) {
					$selected_renewal = $box_credit_renewal;
				} else {
					$selected_renewal = $new_renewal;
				}

				// Skip if old renewal happened already
				if ($old_renewal->lte(Carbon::now())) {
					WP_CLI::debug("\t\tOld renewal in the past. Skipping.");
					$results[] = array(
						'id' => $user_id,
						'sub_id' => $sub->id,
						'type' => $customer->get_subscription_type(),
						'action' => 'skip',
						'reason' => 'old renewal in past',
						'old_renewal' => $old_renewal->format('Y-m-d H:i:s'),
						'new_renewal' => $new_renewal->format('Y-m-d H:i:s'),
						'box_credit_renewal' => $box_credit_renewal->format('Y-m-d H:i:s'),
						'selected_renewal' => $selected_renewal->format('Y-m-d H:i:s'),
						'renewal_day' => $renewal_day,
						'credits' => $credits,
						'debits' => $debits,
						'box_this_month' => $box_this_month,
						'next_box' => $next_box,
						'errors' => NULL,
					);
					continue;
				}

				// Skip if selected renewal is in the past
				if ($selected_renewal->lte(Carbon::now())) {
					WP_CLI::debug("\t\tSelected renewal in the past. Skipping.");
					$results[] = array(
						'id' => $user_id,
						'sub_id' => $sub->id,
						'type' => $customer->get_subscription_type(),
						'action' => 'skip',
						'reason' => 'new renewal in past',
						'old_renewal' => $old_renewal->format('Y-m-d H:i:s'),
						'new_renewal' => $new_renewal->format('Y-m-d H:i:s'),
						'box_credit_renewal' => $box_credit_renewal->format('Y-m-d H:i:s'),
						'selected_renewal' => $selected_renewal->format('Y-m-d H:i:s'),
						'renewal_day' => $renewal_day,
						'credits' => $credits,
						'debits' => $debits,
						'box_this_month' => $box_this_month,
						'next_box' => $next_box,
						'errors' => NULL,
					);
					continue;
				}

				// Skip if renewal date is correct already
				if ($selected_renewal->isSameDay($old_renewal)) {
					WP_CLI::debug("\t\tRenewal already correct.");
					$results[] = array(
						'id' => $user_id,
						'sub_id' => $sub->id,
						'type' => $customer->get_subscription_type(),
						'action' => 'skip',
						'reason' => 'date already correct',
						'old_renewal' => $old_renewal->format('Y-m-d H:i:s'),
						'new_renewal' => $new_renewal->format('Y-m-d H:i:s'),
						'box_credit_renewal' => $box_credit_renewal->format('Y-m-d H:i:s'),
						'selected_renewal' => $selected_renewal->format('Y-m-d H:i:s'),
						'renewal_day' => $renewal_day,
						'credits' => $credits,
						'debits' => $debits,
						'box_this_month' => $box_this_month,
						'next_box' => $next_box,
						'errors' => NULL,
					);
					continue;
				}

				WP_CLI::debug("\t\tExisting renewal is incorrect, adjusting.");
				$new_sub = array(
					'subscription' => array(
						'next_payment_date' => CBWoo::format_DateTime_for_api($selected_renewal)
					)
				);
				if ($this->options->verbose)
					WP_CLI::debug("\t\tModifying date: " . var_export($new_sub, true));
				if (!$this->options->pretend) {
					$result = $this->api->update_subscription($sub->id, $new_sub);
					if ($this->options->verbose) WP_CLI::debug("\t\tResult: " . var_export($result, true));
				}
				$results[] = array(
					'id' => $user_id,
					'sub_id' => $sub->id,
					'type' => $customer->get_subscription_type(),
					'action' => 'changed renewal',
					'reason' => $reason,
					'old_renewal' => $old_renewal->format('Y-m-d H:i:s'),
					'new_renewal' => $new_renewal->format('Y-m-d H:i:s'),
					'selected_renewal' => $selected_renewal->format('Y-m-d H:i:s'),
					'box_credit_renewal' => $box_credit_renewal->format('Y-m-d H:i:s'),
					'renewal_day' => $renewal_day,
					'credits' => $credits,
					'debits' => $debits,
					'box_this_month' => $box_this_month,
					'next_box' => $next_box,
					'errors' => NULL,
				);

			}

		}

		if (sizeof($results))
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Prints out customer data.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb customer 167
	 */
	function customer( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export($this->api->get_customer($id)));
	}

	/**
	 * Prints out order statuses available.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb order_statuses
	 */
	function order_statuses( $args, $assoc_args ) {
		list( $id ) = $args; WP_CLI::line(var_export($this->api->get_order_statuses(), true));
	}

	/**
	 * Prints out product data.
	 *
	 * [<id_or_sku>...]
	 * : Product IDs or skus for the products you want to examine..
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb product b1609_m1
	 */
	function product( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);
		$results = array();
		foreach ($args as $id_or_sku) {
		//	try {
				$product = $this->api->get_product_by_sku(strval($id_or_sku));
			//} catch (Exception $e) {
		//		$product = $this->api->get_product($id_or_sku);
		//	}
			var_dump($product);
		}
	}

	/**
	 * Prints out order data for the given order.
	 *
	 * ## OPTIONS
	 *
	 * [<order_id>...]
	 * : The order id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all orders. (Ignores <order_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> orders out of the list given.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb order 1039
	 *     wp cb order --all
	 */
	function order( $args, $assoc_args ) {
		$assoc_args['orientation'] = 'order';
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);
		foreach ($args as $order_id) {
			WP_CLI::line(var_export($this->api->get_order($order_id), true));
		}
	}

	/**
	 * Generates the next order for a given subscriber. Requires you to list
	 * the month the order is intended for.
	 *
	 * Only generates an order if the subscription is active (or pending cancel), and
	 * if they have no other box order that month.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--skip=<skip>]
	 * : Skip this first <skip> users out of the list given.
	 *
	 * [--reverse]
	 * : Iterate users in reverse order.
	 *
	 * [--rush]
	 * : Only generate rush orders. Skip regular orders. Renewal cutoff does not apply for rush orders.
	 *
	 * [--month=<month>]
	 * : Month for which to generate the order. Format like '2016-05'. Defaults to current month.
	 *
	 * [--date=<date>]
	 * : Date on which to generate the order. This will actually check to see if the
	 *   user had an active subscription as of this date, which allows for back-generation
	 *   of orders, even if sub has expired as of the current moment.
	 *   Should be in iso 8601 format. Defaults to right now.
	 *
	 * [--renewal-cutoff=<renewal-cutoff>]
	 * : Last date on which a renewal can be and still have an order generated. Defaults to
	 *   the 20th of the previous month to the month option.  
	 *
	 * [--sku_version=<sku_version>]
	 * : Which sku version to use. Defaults to v2.
	 *
	 * [--pretend]
	 * : Don't do anything, just print out what we would have done.
	 *
	 * [--force]
	 * : Create the next order for the customer, even if they already have an order this month.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb generate_order 167
	 */
	function generate_order( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array(
			'user_id', 'orders', 'skus', 'credits', 'debits', 'action', 'reason', 'new_sku', 'error'
		);
		$date_string = CBWoo::format_DateTime_for_api($this->options->date);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			WP_CLI::debug("User $user_id");

			$skus = array();
			$orders = array();

			foreach ($customer->get_orders() as $order) {
				$orders[] = $order->id;
				foreach ($order->line_items as $line) {
					if ($line->sku) {
						$skus[] = $line->sku;
					}
				}
			}

			$r = $customer->calculate_box_credits($this->options->verbose);
			$credits = $r['credits'];
			$revenue = $r['revenue'];
			$debits = $customer->calculate_box_debits($this->options->verbose);

			$latest_renewal = end($customer->get_subscription_orders());
			$latest_renewal_date = CBWoo::parse_date_from_api($latest_renewal->created_at);

			// XXX: Pretend: add one credit if sub is active, assuming it will renew
			if ($this->options->pretend && $customer->has_renewing_subscription()) {
				$credits += 1;
				$latest_renewal_date = $this->options->renewal_cutoff->copy()->subHour();
			}

			$sub_id = $latest_renewal->id;
			$udata = get_userdata($customer->get_user_id())->data;
			$name = $udata->display_name;
			$email = $udata->user_email;

			// Reasons to not generate an order

			if ($this->options->rush && !$customer->has_outstanding_rush_order()) {
				WP_CLI::debug("\tNo outstanding rush order.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'not rush order',
					'new_sku' => NULL, 
					'error' => NULL
				);
				continue;
			}

			if ($this->options->verbose) {
				WP_CLI::debug(var_export(array('latest_renewal_date'=>$latest_renewal_date,'renewal_cutoff'=>$this->options->renewal_cutoff), true));
			}

			if (!$this->options->rush && $latest_renewal_date > $this->options->renewal_cutoff) {
				WP_CLI::debug("\tRenewal was too late.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'renewal too late',
					'new_sku' => NULL, 
					'error' => NULL
				);
				$customer->set_meta('next_box_sku', 'renewal_too_late');
				$customer->set_meta('next_box_m', 'renewal_too_late_m'.($customer->estimate_box_month()+1));
				continue;
			}

			if ($debits >= $credits) {
				WP_CLI::debug("\tCustomer does not have any box credits.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'no box credit',
					'new_sku' => NULL, 
					'error' => NULL
				);
				$customer->set_meta('next_box_sku', 'no_credit');
				$customer->set_meta('next_box_m', 'no_credit_m'.($customer->estimate_box_month()+1));
				continue;
			}

			if (!$this->options->force && (
				$customer->has_box_order_this_month($this->options->month)
			)) {
				WP_CLI::debug("\tCustomer already has a valid order this month.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'existing box order',
					'new_sku' => NULL, 
					'error' => NULL
				);
				$customer->set_meta('next_box_sku', 'pending');
				$customer->set_meta('next_box_m', 'already_generated_m'.($customer->estimate_box_month()+1));
				continue;
			} 
			// XXX: Special case for next_box = '2016-07'
			if (!$this->options->rush && $this->options->month->format('Y-m') === '2016-06' && $customer->get_meta('next_box') === '2016-07') {
				WP_CLI::debug("\tOrder should be postponed until July.");
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'user postponed',
					'new_sku' => NULL, 
					'error' => NULL
				);
				$customer->set_meta('next_box_sku', 'postponed');
				$customer->set_meta('next_box_m', 'postponed_m'.($customer->estimate_box_month()+1));
				continue;
			}
			// XXX: Special case for next_box = '2016-08'
			if (!$this->options->rush && $this->options->month->format('Y-m') === '2016-07' && $customer->get_meta('next_box') === '2016-08') {
				WP_CLI::debug("\tOrder should be postponed until August.");
				$results[] = array(
					'user_id' => $user_id,
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'user postponed',
					'new_sku' => NULL,
					'error' => NULL
				);
				$customer->set_meta('next_box_sku', 'postponed');
				$customer->set_meta('next_box_m', 'postponed_m'.($customer->estimate_box_month()+1));
				continue;
			}
			// XXX: Special case for next_box = '2016-09'
			if (!$this->options->rush && $this->options->month->format('Y-m') === '2016-08' && $customer->get_meta('next_box') === '2016-09') {
				WP_CLI::debug("\tOrder should be postponed until September.");
				$results[] = array(
					'user_id' => $user_id,
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'user postponed',
					'new_sku' => NULL,
					'error' => NULL
				);
				$customer->set_meta('next_box_sku', 'postponed');
				$customer->set_meta('next_box_m', 'postponed_m'.($customer->estimate_box_month()+1));
				continue;
			}

			$new_sku = $customer->get_next_box_sku($this->options->month, $this->options->sku_version);
			try {
				$next_order = $customer->next_order_data(
					$this->options->month, $this->options->date, false, $this->options->sku_version
				);
				WP_CLI::debug("\tWould use sku: " . $new_sku);
			} catch (Exception $e) {
				$results[] = array(
					'user_id' => $user_id, 
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits, 
					'debits' => $debits, 
					'action' => 'skip',
					'reason' => 'error',
					'new_sku' => $new_sku, 
					'error' => $e->getMessage()
				);
				$customer->set_meta('next_box_sku', $new_sku);
				$customer->set_meta('next_box_m', 'missing_data_m'.($customer->estimate_box_month()+1));

				if ($this->options->rush) {
					CB::post_to_slack("- Missing data: <https://www.getchallengebox.com/wp-admin/post.php?post=$sub_id&action=edit|#$sub_id> from *$name* &lt;$email&gt;. Partial sku: $new_sku", 'rush-orders');
				}

				continue;
			}
			WP_CLI::debug("\tGenerating order");
			if ($this->options->verbose) {
				WP_CLI::debug("\tNext order: " . var_export($next_order, true));
			}

			if ($this->options->pretend) {
				// Pretend: record which box we would generate
				$customer->set_meta('next_box_sku', $new_sku);
				$customer->set_meta('next_box_m', 'm'.($customer->estimate_box_month()+1));
			} else {
				// Real: actually generate the order
				try {
					$response = $this->api->create_order($next_order);
					WP_CLI::debug("\tCreated new order");
					if ($this->options->rush) {
						$oid = $response->order->id;
						CB::post_to_slack("Rush order <https://www.getchallengebox.com/wp-admin/post.php?post=$oid&action=edit|#$oid> from *$name* &lt;$email&gt; (renewal <https://www.getchallengebox.com/wp-admin/post.php?post=$sub_id&action=edit|#$sub_id> sku $new_sku)", 'rush-orders');
					}
					$customer->set_meta('next_box_sku', 'pending');
					$customer->set_meta('next_box_m', 'already_generated_m'.($customer->estimate_box_month()+1));

				} catch (Exception $e) {
					$results[] = array(
						'user_id' => $user_id, 
						'orders' => implode(" ", $orders),
						'skus' => implode(" ", $skus),
						'credits' => $credits, 
						'debits' => $debits, 
						'action' => 'error',
						'reason' => 'error',
						'new_sku' => $new_sku, 
						'error' => $e->getMessage()
					);
					$customer->set_meta('next_box_sku', $new_sku);
					$customer->set_meta('next_box_m', 'error_m'.($customer->estimate_box_month()+1));
				}
				if ($this->options->verbose) {
					WP_CLI::debug("\tResponse: " . var_export($response, true));
				}
			}
			$results[] = array(
				'user_id' => $user_id, 
				'orders' => implode(" ", $orders),
				'skus' => implode(" ", $skus),
				'credits' => $credits, 
				'debits' => $debits, 
				'action' => 'created order',
				'reason' => NULL,
				'new_sku' => $new_sku, 
				'error' => NULL
			);
		}
		if (sizeof($results)) {
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
		}
	}

	/**
	 * Prints out a prediction of the orders this month. Relies on generate_orders --all --pretend
	 * having been run prior to calling this.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--reverse]
	 * : Iterate users in reverse order.
	 *
	 * [--pretend]
	 * : Don't print output ot slack.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--channel=<channel>]
	 * : Post to the given slack <channel> or username.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb predict_orders 167
	 */
	function predict_orders( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);
		if (!$this->options->pretend) {
			$bot = new ChallengeBot();
			$bot->post_predictions($this->options->channel);
		}
	}

	/**
	 * Finds users with cancelled orders that don't have cancelled subscriptions.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--pretend]
	 * : Don't do anything, just print out what we would have done.
	 * 
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb find_forgot_to_cancel 167
	 */
	function find_forgot_to_cancel( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			WP_CLI::debug("User $user_id");
			$any_order_cancelled = CB::any(array_filter(
				$customer->get_orders(),
				function ($order) { return 'cancelled' == $order->status; }
			));
			if ($any_order_cancelled && $customer->has_active_subscription()) {
				WP_CLI::debug("\tFound a candidate.");
				array_push($results, array('id'=>$user_id, 'email'=>get_userdata($user_id)->user_email));
			}
		}

		if (sizeof($results)) {
			WP_CLI\Utils\format_items($this->options->format, $results, array('id', 'email'));
		}
	}

	/**
	 * Sets a user's box credit.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to check.
	 *
	 * [--credit=<credit>]
	 * : Amount of box credit to set. Defaults to 1.
	 *
	 * [--adjustment=<adjustment>]
	 * : Amount of box credit initial adjustment to set. Defaults to 0.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb box_credit 167 --credit=1 --adjustment=-11
	 */
	function box_credit( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			WP_CLI::debug("User $user_id");
			if (false !== $this->options->credit) {
				$customer->set_meta('extra_box_credits', $this->options->credit);
			}
			if (false !== $this->options->adjustment) {
				$customer->set_meta('box_credit_adjustment', $this->options->adjustment);
			}
		}
	}

	/**
	 * Corrects the sku of a processing order if that sku is the wrong month.
	 *
	 * ## OPTIONS
	 *
	 * <order_id>...
	 * : The order id(s) to change.
	 *
	 * [--sku=<sku>]
	 * : Use a specific sku.
	 *
	 * [--pretend]
	 * : Don't do anything, just print out what we would have done.
	 * 
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb correct_sku 1039
	 */
	function correct_sku( $args, $assoc_args ) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		foreach ($args as $order_id) {
			$order = $this->api->get_order($order_id);
			WP_CLI::debug("Order $order_id");

			$current_sku = CBWoo::extract_order_sku($order);
			if ($this->options->sku) {
				$new_sku = $this->options->sku;
			} else {
				if ($order->total > 50) {
					$new_sku = $current_sku . '_3m';
				}
				if ($order->total > 120) {
					$new_sku = $current_sku . '_12m';
				}
			}
			$new_product = $this->api->get_product_by_sku($new_sku);
			$old_line_item = NULL;

			// Create new order object, marking the old line item's product id as NULL,
			// which tells the woo api to delete it.
			$new_order = array(
				'line_items' => array_map(
					function ($line_item) use ($current_sku, $new_sku, $new_product, &$old_line_item) {
						$new_line_item = (array) clone $line_item;
						if ($new_line_item['sku'] == $current_sku) {
							$new_line_item['product_id'] = NULL;
							$old_line_item = $new_line_item;
						}
						unset($new_line_item['meta']);
						return (array) $new_line_item;
					},
					$order->line_items
				)
			);

			// Add the new line item, copying the old, but using the new product id
			array_push($new_order['line_items'], array(
				'id' => NULL,
				'product_id' => $new_product->id,
				'quantity' => $old_line_item['quantity'],
				'price' => $old_line_item['price'],
				'subtotal' => $old_line_item['subtotal'],
				'subtotal_tax' => $old_line_item['subtotal_tax'],
				'total' => $old_line_item['total'],
				'total_tax' => $old_line_item['total_tax'],
			));

			WP_CLI::debug(var_export($new_order, true));

			if (!$this->options->pretend) {
				$response = $this->api->update_order($order->id, $new_order);
				WP_CLI::debug("\tUpdate order response: " . var_export($response, true));
			} 
		}
	}

	/**
	 * Prints out order data for the given customer.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb orders 167
	 */
	function orders( $args, $assoc_args ) {
		list( $user_id ) = $args;
		$customer = new CBCustomer($user_id, $interactive = false);
		WP_CLI::line(var_export($customer->get_orders(), true));
	}

	/**
	 * Prints data for box orders for the given customer.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb box_orders 167
	 */
	function box_orders( $args, $assoc_args ) {
		list( $user_id ) = $args;
		$customer = new CBCustomer($user_id, $interactive = false);
		WP_CLI::line(var_export($customer->get_box_orders(), true));
	}

	/**
	 * Prints out the given subscription.
	 *
	 * ## OPTIONS
	 *
	 * <subscription_id>
	 * : The subscription id to check.
	 *
	 * [--flatten]
	 * : Display result as array rather than object.
	 *
	 * ## EXAMPLES
	 *     wp cb subscription 6691
	 */
	function subscription( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		list( $id ) = $args;
		$sub = $this->api->get_subscription($id);
		if ($this->options->flatten) {
			$result = json_decode(json_encode($sub), true);
		} else {
			$result = $sub;
		}
		WP_CLI::line(var_export($result, true));
	}

	/**
	 * Prints out subscription data for the given customer.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb subscriptions 167
	 */
	function subscriptions( $args, $assoc_args ) {
		list( $user_id ) = $args;
		$customer = new CBCustomer($user_id, $interactive = false);
		WP_CLI::line(var_export($customer->get_subscriptions(), true));
	}

	/**
	 * Clears a user's fitbit cache for a given month.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * <year_month>
	 * : The year and month to check. (i.e. 2016-04)
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb clear_fitbit_month_cache 167 2016-04
	 */
	function clear_fitbit_month_cache( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		list( $user_id, $year_month ) = $args;
		$customer = new CBCustomer($user_id, $interactive = false);
		$month_start = new DateTime($year_month);
		$month_start->setTime(0,0);
		$month_end = clone $month_start; $month_end->modify('last day of');
		WP_CLI::debug('BEFORE: ' . var_export(
				$customer->fitbit()->inspect_fitbit_cache($month_start, $month_end), true)
		);
		if (! $this->options->pretend) {
			$customer->fitbit()->clear_fitbit_cache($month_start, $month_end);
		}
		WP_CLI::debug('AFTER: ' . var_export(
				$customer->fitbit()->inspect_fitbit_cache($month_start, $month_end), true)
		);
	}

	/**
	 * Returns stripe charges for customer.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user id to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb stripe_charges 167
	 */
	function stripe_charges( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			WP_CLI::debug("\tCustomer $user_id.");
			WP_CLI::debug("\t\tCharges:");
			foreach (CBStripe::get_customer_charges($customer)->data as $charge) {
				WP_CLI::debug("\t\t\t$charge->id, $charge->created, $charge->amount, $charge->amount_refunded");
			}
			if (false) {
				WP_CLI::debug("\t\tNo stripe information.");
			}
		}
	}

	/**
	 * Calculates churn variables for a user for a given month.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--date]
	 * : The year and month to check. (i.e. 2016-04). Defaults to current month.
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb calculate_churn 167 --date=2016-04
	 */
	function calculate_churn( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);

		$results = array();

		$month_start = clone $this->options->date;
		$month_start->setTime(0,0);
		$month_end = clone $month_start; $month_end->modify('last day of');
		$month = $month_start->format('Y-m');
		//$last_month_start = clone $month_start; $last_month_start->modify('first day of last month');
		//$last_month_end = clone $month_start; $last_month_end->modify('last day of last month');

		/// TODO $wooactive_key = 'wooactive_' . $month;

		$mrr_key = 'mrr_' . $month;
		$revenue_key = 'revenue_' . $month;
		$revenue_sub_key = 'revenue_sub_' . $month;
		$revenue_single_key = 'revenue_single_' . $month;
		$revenue_shop_key = 'revenue_shop_' . $month;
		// TODO $columns = array('id', 'cohort', 'first_sub', $wooactive_key, $mrr_key, $revenue_key, $revenue_sub_key, $revenue_single_key, $revenue_shop_key, 'error');
		$columns = array('id', 'cohort', 'first_sub', $mrr_key, $revenue_key, $revenue_sub_key, $revenue_single_key, $revenue_shop_key, 'error');

		foreach ($args as $user_id) {

			try {
				WP_CLI::debug("User $user_id.");
				$customer = new CBCustomer($user_id, $interactive = false);
				$registered = new DateTime(get_userdata($user_id)->user_registered);
				if ($registered > $month_end) {
					WP_CLI::debug("\tSkipping, user registered after month end.");
					continue;
				}

				//$is_active = $customer->is_active_during_period($month_start, $month_end);
				//$is_active_last = $customer->is_active_during_period($last_month_start, $last_month_end);
				$cohort = $customer->earliest_subscription_date()->format('Y-m');
				$mrr = $customer->mrr_during_period($month_start, $month_end);
				$revenue = $customer->revenue_during_period($month_start, $month_end);

				if (! $this->options->pretend) {
					$customer->set_meta('cohort', $registered->format('Y-m'));
					$customer->set_meta($mrr_key, $mrr);
					$customer->set_meta($revenue_key, $revenue['total']);
					$customer->set_meta($revenue_sub_key, $revenue['sub']);
					$customer->set_meta($revenue_single_key, $revenue['single']);
					$customer->set_meta($revenue_shop_key, $revenue['shop']);
				}

				array_push($results, array(
					'id' => $user_id,
					'cohort' => $registered->format('Y-m'),
					'first_sub' => $cohort,
					$mrr_key => $mrr,
					$revenue_key => $revenue['total'],
					$revenue_sub_key => $revenue['sub'],
					$revenue_single_key => $revenue['single'],
					$revenue_shop_key => $revenue['shop'],
					'error' => NULL,
				));

			} catch (Exception $e) {
				WP_CLI::debug("\tError: " . $e->getMessage());	
				array_push($results, array(
					'id' => $user_id,
					'cohort' => isset($registered) ? $registered->format('Y-m') : NULL,
					'first_sub' => isset($cohort) ? $cohort : NULL,
					$mrr_key => isset($mrr) ? $mrr : NULL,
					$revenue_key => isset($revenue) ? $revenue : NULL,
					$revenue_sub_key => isset($revenue) ? $revenue['sub'] : NULL,
					$revenue_single_key => isset($revenue) ? $revenue['single'] : NULL,
					$revenue_shop_key => isset($revenue) ? $revenue['shop'] : NULL,
					'error' => $e->getMessage(),
				));
				if ($this->options->verbose)
					var_dump($e->getTrace());
			}

		}
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Applies points from month to account.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--date]
	 * : The year and month to check. (i.e. 2016-04). Defaults to current month.
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--bonus]
	 * : Apply 2x bonus points for this month.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb apply_month_points 167 --date=2016-04
	 */
	function apply_month_points( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);

		$results = array();

		$month_start = clone $this->options->date;
		$month_start->setTime(0,0);
		$month_end = clone $month_start; $month_end->modify('last day of');
		$month = $month_start->format('Y-m');
		$points_key = 'cb-points-month-v1_' . $month;
		$points_applied_key = 'cb-points-applied-month-v1_' . $month;
		$columns = array(
			'id', 'before total', 'month points',
			'month points previously applied',
			'difference', 'after total', 'error'
		);

		foreach ($args as $user_id) {

			try {
				WP_CLI::debug("User $user_id.");
				$customer = new CBCustomer($user_id, $interactive = false);
				$before_total = WC_Points_Rewards_Manager::get_users_points($user_id);
				$points = $customer->get_meta($points_key, 0);
				$points_applied = $customer->get_meta($points_applied_key, 0);
				$difference = $points - $points_applied;

				WP_CLI::debug("\tpoints $points applied $points_applied difference $difference");

				if ($points === 0) {
					WP_CLI::debug("\tSkipping, user has no points recorded this month.");
					continue;
				}

				if ($difference > 0) {
					WP_CLI::debug("\tAdding $difference points.");
					if (! $this->options->pretend) {
						WC_Points_Rewards_Manager::increase_points($user_id, $difference, 'monthly-challenge', $month_start);
						if ($this->options->bonus) {
							WC_Points_Rewards_Manager::increase_points($user_id, $difference, 'double-points', $month_start);
						}
						$customer->set_meta($points_applied_key, $points);
					}
				}
				elseif ($difference < 0) {
					$difference = -$difference;
					WP_CLI::debug("\tSubtracting $difference points.");
					if (! $this->options->pretend) {
						WC_Points_Rewards_Manager::decrease_points($user_id, $difference, 'monthly-challenge', $month_start);
						$customer->set_meta($points_applied_key, $points);
					}
				}

				$after_total = WC_Points_Rewards_Manager::get_users_points($user_id);

				$results[] = array(
					'id' => $user_id,
					'before total' => $before_total,
					'month points' => $points,
					'month points previously applied' => $points_applied,
					'difference' => $difference,
					'after total' => $after_total,
					'error' => NULL,
				);

			} catch (Exception $e) {
				WP_CLI::debug("\tError: " . $e->getMessage());

				$results[] = array(
					'id' => $user_id,
					'before total' => isset($before_total) ? $before_total : NULL,
					'month points' => isset($points) ? $points : NULL,
					'month points previously applied' => isset($points_applied) ? : NULL,
					'difference' => isset($difference) ? $difference : NULL,
					'after total' => isset($after_total) ? $after_total : NULL,
					'error' => NULL,
				);

				if ($this->options->verbose) {
					var_dump($e->getTrace());
				}
			}

		}
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Applies points to account.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * --points=<points>
	 * : Number of points to add or subtract.
	 *
	 * --note=<note>
	 * : A note to include with this points adjustment.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb apply_points 167 --points=100 --note="Thanks for bearing with us!"
	 */
	function apply_points( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array(
			'id', 'before total', 'points applied',
			'note', 'after total', 'error'
		);
		$points = $this->options->points;
		$note = $this->options->note;

		foreach ($args as $user_id) {

			try {
				WP_CLI::debug("User $user_id.");
				$customer = new CBCustomer($user_id, $interactive = false);
				$before_total = WC_Points_Rewards_Manager::get_users_points($user_id);

				if ($points >= 0) {
					WP_CLI::debug("\tAdding $points points.");
					if (! $this->options->pretend) {
						WC_Points_Rewards_Manager::increase_points($user_id, $points, 'point-adjustment', $note);
					}
				}
				elseif ($points < 0) {
					$points = -$points;
					WP_CLI::debug("\tSubtracting $points points.");
					if (! $this->options->pretend) {
						WC_Points_Rewards_Manager::decrease_points($user_id, $points, 'point-adjustment', $note);
					}
				}

				$after_total = WC_Points_Rewards_Manager::get_users_points($user_id);

				$results[] = array(
					'id' => $user_id,
					'before total' => $before_total,
					'points applied' => $points,
					'note' => $note,
					'after total' => $after_total,
					'error' => NULL,
				);

			} catch (Exception $e) {
				WP_CLI::debug("\tError: " . $e->getMessage());

				$results[] = array(
					'id' => $user_id,
					'before total' => NULL,
					'points applied' => isset($points) ? $points : NULL,
					'note' => isset($note) ? : NULL,
					'after total' => NULL,
					'error' => NULL,
				);

				if ($this->options->verbose) {
					var_dump($e->getTrace());
				}
			}

		}
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Set special segment in customer.io. Overwrites existing special segment.
	 *
	 * Writes a list of what it did to stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * --segment=<segment>
	 * : Name of the special segment.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb special_segment 167 --segment="Missing Resistance Bands"
	 */
	function special_segment( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		$segment_name = $this->options->segment;
		$segment = new CBSegment();

		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id.");
			$customer = new CBCustomer($user_id, $interactive = false);
			if (! $this->options->pretend) {
				WP_CLI::debug("\tSetting special_segment to $segment_name.");
				$customer->set_meta('special_segment', $segment_name);
			}
			WP_CLI::debug("\tcalling identify().");
			$data = $segment->identify($customer, $this->options->pretend);
		}
	}

	/**
	 * Fetch fitbit time series data.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * --series=<series>
	 * : Name of the series to fetch.
	 *  ---
	 *  options:
	 *    - caloriesIn
	 *    - water
	 *    - caloriesOut
	 *    - steps
	 *    - distance
	 *    - floors
	 *    - elevation
	 *    - minutesSedentary
	 *    - minutesLightlyActive
	 *    - minutesFairlyActive
	 *    - minutesVeryActive
	 *    - activityCalories
	 *  ---
	 *
	 * --date=<date>
	 * : The year and month to check. (i.e. 2016-04). 
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb fitbit_data 167 --series=water --date=2016-04
	 */
	function fitbit_data( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		$series = $this->options->series;
		$results = array();
		$columns = array('id');

		$month_start = clone $this->options->date;
		$month_start->setTime(0,0);
		$month_end = clone $month_start; $month_end->modify('last day of');
		$month = $month_start->format('Y-m');

		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id.");
			$customer = new CBCustomer($user_id, $interactive = false);
			$data = $customer->fitbit()->getTimeSeries($series, $month_start, $month_end);
			// Turn keys into strings
			$data = array_combine(array_map(function ($s) { return 'd' . $s; }, array_keys($data)), $data);
			$data = array_merge(array('id'=>$user_id), $data);
			$results[] = $data;
			$columns = array_unique(array_merge($columns, array_keys($data)));
		}
		WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Calculate fitbit activity data.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * [--date=<date>]
	 * : The month to check (can be any day in month). Defaults to current month.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb activity_data 167 --date=2016-04
	 */
	function activity_data( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id.");
			$customer = new CBCustomer($user_id, $interactive = false);
			$data = $customer->challenges->get_month_activity($this->options->date);

			$results = array();
			$columns = array();

			foreach ($data['time_series'] as $name => $series) {
				$series = array_merge(
					array('series' => $name, 'd0' => null),
					array_combine(
						array_map(function ($s) { return 'd' . $s; }, array_keys($series)), 
						array_map(function ($d) { return round($d, 2); }, $series)
					)
				);
				$results[] = $series;
				$columns = array_keys($series);
			}

			$blank_row = array(); foreach ($columns as $column) $blank_row[$column] = null;
			foreach ($data['metrics'] as $key => $value) {
				$results[] = array_merge($blank_row, array('series' => $key, 'd0' => round($value,2)));
			}

			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
		}
	}

	/**
	 * Calculate fitness challenges.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * [--date=<date>]
	 * : The month to check (can be any day in month). Defaults to current month.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb calculate_challenges 167 --date=2016-04
	 */
	function calculate_challenges( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id.");
			$customer = new CBCustomer($user_id, $interactive = false);
			//var_dump($customer->challenges->get_raw_challenges_for_month($this->options->date));
			var_dump($customer->challenges->get_ordered_challenges_for_month($this->options->date));
		}
	}

	/**
	 * Calculate personal bests.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * [--date=<date>]
	 * : The month to check (can be any day in month). Defaults to current month.
	 *
	 * [--pretend]
	 * : Don't actually save any data.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb calculate_personal_bests 167 --date=2016-04
	 */
	function calculate_personal_bests( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id.");
			$customer = new CBCustomer($user_id, $interactive = false);
			var_dump($customer->challenges->calculate_personal_bests($this->options->date, $this->options->pretend));
		}
	}

	/**
	 * Calculate challenge points for a given day.
	 *
	 * ## OPTIONS
	 *
	 * [<user_id>...]
	 * : The user id(s) to calculate.
	 *
	 * [--date=<date>]
	 * : The date to check (can be any day in month). Defaults to current day.
	 *
	 * [--pretend]
	 * : Don't actually save any data.
	 *
	 * [--all]
	 * : Iterate through all users. (Ignores <user_id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb calculate_month_points 167 --date=2016-04-01
	 */
	function calculate_month_points( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		foreach ($args as $user_id) {
			WP_CLI::debug("User $user_id.");
			$customer = new CBCustomer($user_id, $interactive = false);
			var_dump($customer->challenges->calculate_month_points($this->options->date, $this->options->pretend));
		}
	}

	/**
	 * Generate skus for product variations.
	 *
	 * Uses the parent sku, plus attributes marked for variation, in the order they appear
	 * in the admin interface.
	 *
	 * ## OPTIONS
	 *
	 * [<product_id>...]
	 * : The product id(s) to calculate.
	 *
	 * [--pretend]
	 * : Don't actually do any api calls.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb generate_skus 8514
	 */
	function generate_skus( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		foreach ($args as $product_id) {
			WP_CLI::debug("Product $product_id");
			$product = $this->api->get_product($product_id);
			foreach ($product->variations as $variation) {

				$atts = CBWoo::extract_attributes($variation->attributes);
				$sku = '' . $product->sku;

				// Add in a comonent for each attribute marked to use in variations
				foreach ($product->attributes as $pat) {

					// Only use attributes in sku that are marked to use in variations
					if (! $pat->variation) continue;

					$value = $atts[$pat->slug];
					if (isset($value)) {
						// Special case for diet
						if ('diet' === $pat->slug && 'no-restrictions' === $value) {
							// Don't add anything on the sku
						} else {
							$sku .= '_' . $atts[$pat->slug];
						}
					}
				}

				// Prep update
				$variation_id = $variation->id;
				$update = array('product' => array('sku' => $sku));
				WP_CLI::debug("\tVariation $variation_id -> sku $sku");
				if ($this->options->verbose) WP_CLI::debug(var_export($update, true));

				// Apply update
				if (! $this->options->pretend) {
					$result = $this->api->update_product($variation_id, $update);
					if ($this->options->verbose) WP_CLI::debug(var_export($result, true));
				}
			}
		}
	}

	/**
	 * Prints out product data available.
	 *
	 * ## OPTIONS
	 *
	 * [<product_id>...]
	 * : The product id(s) to show.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb product 8514
	function product( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		foreach ($args as $product_id) {
			$product = $this->api->get_product($product_id);
			var_dump($product);
			foreach ($product->attributes as $att) {
				var_dump($att);
			}
		}
	}
	 */

	/**
	 * Prints out product attributes.
	 */
	function product_attributes( $args, $assoc_args ) {
		var_dump($this->api->get_attributes());
	}

	/**
	 * Migrates old v2 style subscriptions to ones that don't generate box orders.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to adjust renewal date.
	 *
	 * [--all]
	 * : Adjust dates for all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually migrate, just print out what we'd do.
	 *
	 * [--force]
	 * : Even if skus already match, re-migrate sub. (One use case is correcting sub line
	 *   item description.)
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb migrate_subscriptions 167
	 */
	function migrate_subscriptions($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array('user_id', 'sub_id', 'current_sku', 'new_sku', 'error');

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			WP_CLI::debug("Synchronizing $user_id");

			if (sizeof($customer->get_subscriptions()) == 0) {
				WP_CLI::debug("\tNo subscriptions. Skipping.");
				continue;
			}

			// Convert subscriptions
			foreach ($customer->get_subscriptions() as $sub) {

				$sub_id = $sub->id;
				$current_sku = $new_sku = $e = NULL;

				try {
					WP_CLI::debug("\tSubscription $sub_id...");

					$new_sub_type = CBWoo::extract_subscription_type($sub);
					if (!$new_sub_type) {
						WP_CLI::debug("\t\tCan't identify subscription type. Skipping.");
						continue;
					}

					// Figure out what we're migrating to
					$current_sku = CBWoo::extract_order_sku($sub);
					$new_sku = array(
						'3 Month' => 'subscription_3month',
						'12 Month' => 'subscription_12month',
						'Month to Month' => 'subscription_monthly',
					)[$new_sub_type];

					if (!$this->options->force && $new_sku === $current_sku) {
						WP_CLI::debug("\t\tSkus already match, skipping.");
						continue;
					}

					$new_product = $this->api->get_product_by_sku($new_sku);

					// Create the new subscription by:
					// - Go through each line item in the old subscription
					// - Remove the 'meta' seciton
					// - For the one contaning the old sku, set the product id to null to cause
					//   the API to remove it.
					// - Create a new line item that copies the quantity, price, total, etc, from
					//   the old line item, but has the product id for the new item. This line is
					//   the replacement for the line with the old sku.
					// Yeah, this is weird, but it's how WooCommerce API works.
					$old_line_item = NULL;
					$new_sub = array(
						'line_items' => array_map(
							function ($line_item) use ($current_sku, $new_sku, $new_product, &$old_line_item) {
								$new_line_item = (array) clone $line_item;
								if ($new_line_item['sku'] == $current_sku) {
									$new_line_item['product_id'] = NULL;
									$old_line_item = $new_line_item;
								}
								unset($new_line_item['meta']);
								return (array) $new_line_item;
							},
							$sub->line_items
						)
					);
					array_push($new_sub['line_items'], array(
						'id' => NULL,
						'product_id' => $new_product->id,
						'quantity' => $old_line_item['quantity'],
						'price' => $old_line_item['price'],
						'subtotal' => $old_line_item['subtotal'],
						'subtotal_tax' => $old_line_item['subtotal_tax'],
						'total' => $old_line_item['total'],
						'total_tax' => $old_line_item['total_tax'],
					));
					WP_CLI::debug(var_export($new_sub, true));

					// Update the subscription
					if (!$this->options->pretend) {
						$result = $this->api->update_subscription($sub->id, array('subscription' => $new_sub));
						if ($this->options->verbose)
							WP_CLI::debug("\t\tResult: " . var_export($result, true));
					}
				} catch (Exception $e) {
				}

				$results[] = array(
					'user_id' => $user_id,
					'sub_id' => $sub_id,
					'current_sku' => $current_sku,
					'new_sku' => $new_sku,
					'error' => $e
				);
			}
		}

		if (sizeof($results))
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Marks every 'processing' subscription order as 'completed'.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to mark orders.
	 *
	 * [--all]
	 * : Mark orders for all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--pretend]
	 * : Don't actually mark, just print out what we'd do.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb mark_subscription_orders_as_completed 167
	 */
	function mark_subscription_orders_as_completed($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		/*
		$results = array();
		$columns = array(
			'user_id', 'sub_id', 'order_id', 'status_before', 'status_after'
		);
		*/

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			WP_CLI::debug("User $user_id");

			foreach ($customer->get_subscription_orders() as $order) {
				$oid = $order->id;
				WP_CLI::debug("\tOrder $oid");
				if ('completed' === $order->status) {
					WP_CLI::debug("\t\t-> completed");
					continue;
				}
				$new_order = array('order' => array('status' => 'completed'));
				WP_CLI::debug("\t\t-> marking...");
				if ($this->options->verbose) {
					WP_CLI::debug("\tnew" . var_export($new_order, true));
				}
				if (! $this->options->pretend) {
					$response = $this->api->update_order($order->id, $new_order);
					if ($this->options->verbose) {
						WP_CLI::debug("\tUpdate order response: " . var_export($response, true));
					}
				}
			}
		}
	}

	/**
	 * Command reconciles box credits vs debits.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to reconcile credits.
	 *
	 * [--all]
	 * : Work on all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--reverse]
	 * : Iterate users in reverse order.
	 *
	 * [--pretend]
	 * : Don't actually work, just print out what we'd do.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb reconcile_box_credits 167
	 */
	function reconcile_box_credits($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array(
			'user_id', 'orders', 'skus', 'credits', 'debits', 'owed', 'revenue', 'rpc', 'rpd', 'rpc_low', 'rpc_high', 'error', 'next_box', 'active_sub'
		);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			WP_CLI::debug("User $user_id");

			$skus = array();
			$orders = array();

			foreach ($customer->get_orders() as $order) {
				$orders[] = $order->id;
				foreach ($order->line_items as $line) {
					if ($line->sku) {
						$skus[] = $line->sku;
					}
				}
			}

			try { 

				$r = $customer->calculate_box_credits($this->options->verbose);
				$credits = $r['credits'];
				$revenue = $r['revenue'];
				$debits = $customer->calculate_box_debits($this->options->verbose);

				$results[] = array(
					'user_id' => $user_id,
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => $credits,
					'debits' => $debits,
					'revenue' => $revenue,
					'rpc' => $credits ? $revenue/$credits : 0,
					'rpd' => $debits ? $revenue/$debits : 0,
					'rpc_low' => (bool) ($credits && ($credits ? $revenue/$credits : 0) < 15),
					'rpc_high' => (bool) ($credits && ($credits ? $revenue/$credits : 0) > 50),
					'owed' => $credits - $debits,
					'next_box' => $customer->get_meta('next_box'),
					'active_sub' => $customer->has_active_subscription(),
					'error' => null,
				);

			} catch (Exception $e) {

				$results[] = array(
					'user_id' => $user_id,
					'orders' => implode(" ", $orders),
					'skus' => implode(" ", $skus),
					'credits' => null,
					'debits' => null,
					'revenue' => null,
					'rpc' => null,
					'rpd' => null,
					'rpc_low' => null,
					'rpc_high' => null,
					'owed' => null,
					'next_box' => $customer->get_meta('next_box'),
					'active_sub' => $customer->has_active_subscription(),
					'error' => $e->getMessage(),
				);
			}
		}
		if (sizeof($results))
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Cancels bad orders
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to cancel orders.
	 *
	 * --date=<date>
	 * : Cutoff date after which an order should be canceled.
	 *
	 * [--all]
	 * : Work on all users. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--reverse]
	 * : Iterate users in reverse order.
	 *
	 * [--pretend]
	 * : Don't actually work, just print out what we'd do.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb cancel_bad_orders 167
	 */
	function cancel_bad_orders($args, $assoc_args) {
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array(
			'user_id', 'order_id', 'skus', 'renewal_date', 'order_date', 'action', 'reason', 'error'
		);

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			WP_CLI::debug("User $user_id");

			$latest_renewal = end($customer->get_subscription_orders());
			$latest_renewal_date = CBWoo::parse_date_from_api($latest_renewal->created_at);

			foreach ($customer->get_orders() as $order) {

				$order_id = $order->id;
				$reason = null;
				$action = null;
				$skus = array();
				foreach ($order->line_items as $line) {
					if ($line->sku) {
						$skus[] = $line->sku;
					}
				}
				$order_date = CBWoo::parse_date_from_api($order->created_at);

				WP_CLI::debug("\tOrder $order_id");

				if (!CBWoo::order_counts_as_box_debit($order)) {
					WP_CLI::debug("\t\tskipping, not a box.");
					$action = 'skip';
					continue;
				}
				if ('cancelled' === $order->status) {
					WP_CLI::debug("\t\talready canceled");
					$action = 'skip';
					continue;
				}
				if ('processing' !== $order->status) {
					WP_CLI::debug("\t\tnot processing");
					$action = 'skip';
					continue;
				}

				if (!$order->shipping_address || !$order->shipping_address->address_1) {
					$action = 'cancel';
					$reason = 'no shipping address';
				}
				else {
					if ($order_date > $this->options->date) {
						if ($latest_renewal_date <= $this->options->date) {
							$action = 'skip';
							$reason = 'renewal before cutoff';
						} else {
							$action = 'cancel';
							$reason = 'renewal after cutoff';
						}
					} else {
						$action = 'skip';
						$reason = 'order not after renewal';
						continue;
					}
				}

				if ('cancel' == $action) {
					WP_CLI::debug("\t\t$reason");
					try { 
						$new_order = array('order' => array('status' => 'cancelled'));
						WP_CLI::debug("\t\tcancelling...");
						if (! $this->options->pretend) {
							$response = $this->api->update_order($order->id, $new_order);
							if ($this->options->verbose) {
								WP_CLI::debug("\tUpdate order response: " . var_export($response, true));
							}
						}
						$results[] = array(
							'user_id' => $user_id,
							'order_id' => $order_id,
							'skus' => implode(" ", $skus),
							'renewal_date' => $latest_renewal_date->format('Y-m-d H:i:s'),
							'order_date' => $order_date->format('Y-m-d H:i:s'),
							'action' => $action,
							'reason' => $reason,
							'error' => null,
						);
					} catch (Exception $e) {
						$results[] = array(
							'user_id' => $user_id,
							'order_id' => $order_id,
							'skus' => implode(" ", $skus),
							'renewal_date' => $latest_renewal_date->format('Y-m-d H:i:s'),
							'order_date' => $order_date->format('Y-m-d H:i:s'),
							'action' => $action,
							'reason' => $reason,
							'error' => $e->getMessage(),
						);
					}

				} else {
					$results[] = array(
						'user_id' => $user_id,
						'order_id' => $order_id,
						'skus' => implode(" ", $skus),
						'renewal_date' => $latest_renewal_date->format('Y-m-d H:i:s'),
						'order_date' => $order_date->format('Y-m-d H:i:s'),
						'action' => $action,
						'reason' => $reason,
						'error' => null,
					);
				}

			}
		}
		if (sizeof($results))
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

	/**
	 * Exports churn data.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb export_churn_data
	 */
	function export_churn_data( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);

		WP_CLI::debug("Getting churn data...");
		$churn_data = CBWoo::get_churn_data();
		WP_CLI::debug("Generating rollups...");
		$rollups = CBWoo::get_churn_rollups($churn_data);
		WP_CLI::debug("...done");

		/*
		var_dump($churn_data->columns);
		var_dump($churn_data->cohorts);
		var_dump($churn_data->mrr_cohorts);
		var_dump($churn_data->months);
		var_dump($churn_data->rollups);
		*/

		$columns = array_merge(array('cohort'), $churn_data->months);
		foreach ($rollups as $name => $rollup) {
			WP_CLI::debug($name);
			WP_CLI\Utils\format_items($this->options->format, $rollup, $columns);
		}
		WP_CLI::error("done");
		/**/

		// Render data as sorted rows
		$csv_rows = array();
		foreach ($churn_data->data as $user_id => $user_row) {
			$user_row["id"] = $user_id;
			$row = array();
			foreach ($churn_data->columns as $column) {
				if (isset($user_row[$column])) {
					$row[] = $user_row[$column];
				} else {
					$row[] = NULL;
				}
			}
			$csv_rows[] = $row;
		}

		// Print it out
		fputcsv(STDOUT, $churn_data->columns);
		foreach ($csv_rows as $row) {
			fputcsv(STDOUT, $row);
		}

	}

	/**
	 * Starts challenge bot.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb challenge_bot
	 */
	function challenge_bot( $args, $assoc_args ) {
		list( $args, $assoc_args ) = $this->parse_args($args, $assoc_args);
		$bot = new ChallengeBot();
		$bot->run();
	}

	/**
	 * Runs churn analytics on the database.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb run_churn_analytics
	 */
	function run_churn_analytics( $args, $assoc_args ) {
		$new_churn_base = <<<SQL
			drop table if exists cb_sequence_temp_from_box_orders;
			create table cb_sequence_temp_from_box_orders (
				user_id bigint(20) unsigned not NULL,
				ship_month varchar(5) character set utf8mb4 not null,
				primary key (user_id, ship_month)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			select
					user_id
				, date_format(month, 'b%y%m') as ship_month
				, date_format(month, '%Y-%m') as calendar_month
				, month
			from
				cb_box_orders, cb_months
			where
				month between '2016-02-01' and now()
			group by
				user_id, month
			order by
				user_id, month;

			SET @lag_user_id=NULL; SET @lag_box_active=NULL; SET @lag2_boxes_shipped=NULL; SET @lag2_user_id=NULL;

			drop table if exists cb_calendar_month_boxes;
			create table cb_calendar_month_boxes
				select
						user_id
					, (@lag2_user_id) as lag2_user_id
					, (@lag2_user_id:=lag_user_id) as lag_user_id
					, calendar_month
					, sum(`boxes shipped` > 0) as active
					, sum(`boxes shipped` + if(lag_boxes_shipped is NULL or lag_user_id is NULL or user_id <> lag_user_id, 0, lag_boxes_shipped) > 0) as active_grace_1m
					, sum(`boxes shipped` > 0 and (lag_boxes_shipped = 0 or user_id <> lag_user_id)) as activated
					, sum(`boxes shipped` > 0 and ((lag_boxes_shipped = 0 and @lag2_boxes_shipped = 0) or user_id <> lag_user_id)) as activated_grace_1m
					, sum(user_id = lag_user_id and `boxes shipped` = 0 and lag_boxes_shipped > 0) as box_churned
					, sum(`box user count`) as `box user count`
					, sum(`box count`) as `box count`
					, sum(`boxes shipped`) as `boxes shipped`
					, round(@lag2_boxes_shipped) as lag2_boxes_shipped
					, (@lag2_boxes_shipped:=lag_boxes_shipped) as lag_boxes_shipped
					, sum(`boxes cancelled`) as `boxes cancelled`
					, sum(`boxes processing`) as `boxes processing`
					, sum(`boxes other`) as `boxes other`
					, sum(`box revenue`) as `box revenue`
					, sum(`box item rev`) as `box item rev`
					, sum(`box ship rev`) as `box ship rev`
					, sum(`box rush rev`) as `box rush rev`
					, sum(`boxes from users with rush`) as `boxes from users with rush`
					, sum(`box full refunds`) as `box full refunds`
					, sum(`box partial refunds`) as `box partial refunds`
					, sum(`box refund amt`) as `box refund amt`
					, 100 * sum(`box refund amt`)/sum(`box revenue`) as `box refund amt % of rev`
					, 100 * sum(`box refunds`)/sum(`boxes shipped`) as `box refunds % of shipped`
					, 100 * sum(`box full refunds`)/sum(`boxes shipped`) as `box full refunds % of shipped`
					, 100 * sum(`box partial refunds`)/sum(`boxes shipped`) as `box partial refunds % of shipped`
					, sum(`box revenue`)-sum(`box refund amt`) as `box net rev`
				from (
					select
								(@lag_user_id) as lag_user_id
							, (@lag_user_id:=user_id) as user_id
							, calendar_month
							, `box user count`
							, `box count`
							, round(@lag_boxes_shipped) as lag_boxes_shipped
							, (@lag_boxes_shipped:=`boxes shipped`) as `boxes shipped`
							, `boxes cancelled`
							, `boxes processing`
							, `boxes other`
							, `box revenue`
							, `box item rev`
							, `box ship rev`
							, `box rush rev`
							, `boxes from users with rush`
							, `box refunds`
							, `box full refunds`
							, `box partial refunds`
							, `box refund amt`
							, `box net rev`

					from (
						select
								source.user_id
							, sequence.calendar_month
							, count(distinct if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, source.user_id, 0)) as `box user count`
							, count(distinct if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, id, 0)) as `box count`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status, 0) in ('completed', 'refunded')) as `boxes shipped`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status, 0) in ('cancelled')) as `boxes cancelled`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status, 0) in ('processing')) as `boxes processing`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status, 0) not in ('completed', 'refunded', 'cancelled', 'processing')) as `boxes other`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, total, 0)) as `box revenue`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_items, 0)) as `box item rev`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_ship, 0)) as `box ship rev`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_rush, 0)) as `box rush rev`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_rush>0, 0)) as `boxes from users with rush`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund > 0 and total > 0, 0)) as `box refunds`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund >= total and refund > 0 and total > 0, 0)) as `box full refunds`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund < total and refund > 0 and total > 0, 0)) as `box partial refunds`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund, 0)) as `box refund amt`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, total, 0)) - sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund, 0)) as `box net rev`
						from
							cb_box_orders as source
						join
							cb_sequence_temp_from_box_orders as sequence
						on
								source.user_id = sequence.user_id
						group by
							source.user_id, sequence.calendar_month
						order by
							source.user_id, sequence.calendar_month

					) as base

					group by
						user_id, calendar_month
					order by
						user_id, calendar_month

				) as lag_table
				
			group by
				calendar_month
			order by
				calendar_month
			;

			drop table if exists cb_calendar_month_renewals;
			create table cb_calendar_month_renewals
				select
						user_id
					, (@lag2_user_id) as lag2_user_id
					, (@lag2_user_id:=lag_user_id) as lag_user_id
					, calendar_month
					, sum(`renewals shipped` > 0) as renewal_active
					, sum(`renewals shipped` + if(lag_renewals_shipped is NULL or lag_user_id is NULL or user_id <> lag_user_id, 0, lag_renewals_shipped) > 0) as renewal_active_grace_1m
					, sum(`renewals shipped` > 0 and (lag_renewals_shipped = 0 or user_id <> lag_user_id)) as renewal_activated
					, sum(`renewals shipped` > 0 and ((lag_renewals_shipped = 0 and @lag2_renewals_shipped = 0) or user_id <> lag_user_id)) as renewal_activated_grace_1m
					, sum(user_id = lag_user_id and `renewals shipped` = 0 and lag_renewals_shipped > 0) as renewal_churned
					, sum(`renewal user count`) as `renewal user count`
					, sum(`renewal count`) as `renewal count`
					, sum(`renewals shipped`) as `renewals shipped`
					, round(@lag2_renewals_shipped) as lag2_renewals_shipped
					, (@lag2_renewals_shipped:=lag_renewals_shipped) as lag_renewals_shipped
					, sum(`renewals cancelled`) as `renewals cancelled`
					, sum(`renewals processing`) as `renewals processing`
					, sum(`renewals other`) as `renewals other`
					, sum(`renewal revenue`) as `renewal revenue`
					, sum(`renewal item rev`) as `renewal item rev`
					, sum(`renewal ship rev`) as `renewal ship rev`
					, sum(`renewal rush rev`) as `renewal rush rev`
					, sum(`renewals from users with rush`) as `renewals from users with rush`
					, sum(`renewal full refunds`) as `renewal full refunds`
					, sum(`renewal partial refunds`) as `renewal partial refunds`
					, sum(`renewal refund amt`) as `renewal refund amt`
					, 100 * sum(`renewal refund amt`)/sum(`renewal revenue`) as `renewal refund amt % of rev`
					, 100 * sum(`renewal refunds`)/sum(`renewals shipped`) as `renewal refunds % of shipped`
					, 100 * sum(`renewal full refunds`)/sum(`renewals shipped`) as `renewal full refunds % of shipped`
					, 100 * sum(`renewal partial refunds`)/sum(`renewals shipped`) as `renewal partial refunds % of shipped`
					, sum(`renewal revenue`)-sum(`renewal refund amt`) as `renewal net rev`
				from (
					select
								(@lag_user_id) as lag_user_id
							, (@lag_user_id:=user_id) as user_id
							, calendar_month
							, `renewal user count`
							, `renewal count`
							, round(@lag_renewals_shipped) as lag_renewals_shipped
							, (@lag_renewals_shipped:=`renewals shipped`) as `renewals shipped`
							, `renewals cancelled`
							, `renewals processing`
							, `renewals other`
							, `renewal revenue`
							, `renewal item rev`
							, `renewal ship rev`
							, `renewal rush rev`
							, `renewals from users with rush`
							, `renewal refunds`
							, `renewal full refunds`
							, `renewal partial refunds`
							, `renewal refund amt`
							, `renewal net rev`

					from (
						select
								source.user_id
							, sequence.calendar_month
							, count(distinct if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, source.user_id, 0)) as `renewal user count`
							, count(distinct if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, id, 0)) as `renewal count`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status, 0) in ('completed', 'refunded')) as `renewals shipped`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status, 0) in ('cancelled')) as `renewals cancelled`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status, 0) in ('processing')) as `renewals processing`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status, 0) not in ('completed', 'refunded', 'cancelled', 'processing')) as `renewals other`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, total, 0)) as `renewal revenue`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_items, 0)) as `renewal item rev`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_ship, 0)) as `renewal ship rev`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_rush, 0)) as `renewal rush rev`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_rush>0, 0)) as `renewals from users with rush`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund > 0 and total > 0, 0)) as `renewal refunds`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund >= total and refund > 0 and total > 0, 0)) as `renewal full refunds`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund < total and refund > 0 and total > 0, 0)) as `renewal partial refunds`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund, 0)) as `renewal refund amt`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, total, 0)) - sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund, 0)) as `renewal net rev`
						from
							cb_renewals as source
						join
							cb_sequence_temp_from_box_orders as sequence
						on
								source.user_id = sequence.user_id
						group by
							source.user_id, sequence.calendar_month
						order by
							source.user_id, sequence.calendar_month

					) as base

					group by
						user_id, calendar_month
					order by
						user_id, calendar_month

				) as lag_table
				
			group by
				calendar_month
			order by
				calendar_month
			;

			drop table if exists cb_calendar_month_shop;
			create table cb_calendar_month_shop
				select
						user_id
					, (@lag2_user_id) as lag2_user_id
					, (@lag2_user_id:=lag_user_id) as lag_user_id
					, calendar_month
					, sum(`shop shipped` > 0) as shop_active
					, sum(`shop shipped` + if(lag_shop_shipped is NULL or lag_user_id is NULL or user_id <> lag_user_id, 0, lag_shop_shipped) > 0) as shop_active_grace_1m
					, sum(`shop shipped` > 0 and (lag_shop_shipped = 0 or user_id <> lag_user_id)) as shop_activated
					, sum(`shop shipped` > 0 and ((lag_shop_shipped = 0 and @lag2_shop_shipped = 0) or user_id <> lag_user_id)) as shop_activated_grace_1m
					, sum(user_id = lag_user_id and `shop shipped` = 0 and lag_shop_shipped > 0) as shop_churned
					, sum(`shop user count`) as `shop user count`
					, sum(`shop count`) as `shop count`
					, sum(`shop shipped`) as `shop shipped`
					, round(@lag2_shop_shipped) as lag2_shop_shipped
					, (@lag2_shop_shipped:=lag_shop_shipped) as lag_shop_shipped
					, sum(`shop cancelled`) as `shop cancelled`
					, sum(`shop processing`) as `shop processing`
					, sum(`shop other`) as `shop other`
					, sum(`shop revenue`) as `shop revenue`
					, sum(`shop item rev`) as `shop item rev`
					, sum(`shop ship rev`) as `shop ship rev`
					, sum(`shop rush rev`) as `shop rush rev`
					, sum(`shop from users with rush`) as `shop from users with rush`
					, sum(`shop full refunds`) as `shop full refunds`
					, sum(`shop partial refunds`) as `shop partial refunds`
					, sum(`shop refund amt`) as `shop refund amt`
					, 100 * sum(`shop refund amt`)/sum(`shop revenue`) as `shop refund amt % of rev`
					, 100 * sum(`shop refunds`)/sum(`shop shipped`) as `shop refunds % of shipped`
					, 100 * sum(`shop full refunds`)/sum(`shop shipped`) as `shop full refunds % of shipped`
					, 100 * sum(`shop partial refunds`)/sum(`shop shipped`) as `shop partial refunds % of shipped`
					, sum(`shop revenue`)-sum(`shop refund amt`) as `shop net rev`
				from (
					select
								(@lag_user_id) as lag_user_id
							, (@lag_user_id:=user_id) as user_id
							, calendar_month
							, `shop user count`
							, `shop count`
							, round(@lag_shop_shipped) as lag_shop_shipped
							, (@lag_shop_shipped:=`shop shipped`) as `shop shipped`
							, `shop cancelled`
							, `shop processing`
							, `shop other`
							, `shop revenue`
							, `shop item rev`
							, `shop ship rev`
							, `shop rush rev`
							, `shop from users with rush`
							, `shop refunds`
							, `shop full refunds`
							, `shop partial refunds`
							, `shop refund amt`
							, `shop net rev`

					from (
						select
								source.user_id
							, sequence.calendar_month
							, count(distinct if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, source.user_id, 0)) as `shop user count`
							, count(distinct if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, id, 0)) as `shop count`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status in ('completed', 'refunded'), 0)) as `shop shipped`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status = 'cancelled', 0)) as `shop cancelled`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status = 'processing', 0)) as `shop processing`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, status not in ('completed', 'refunded', 'cancelled', 'processing'), 0) ) as `shop other`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, total, 0)) as `shop revenue`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_items, 0)) as `shop item rev`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_ship, 0)) as `shop ship rev`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_rush, 0)) as `shop rush rev`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, revenue_rush>0, 0)) as `shop from users with rush`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund > 0 and total > 0, 0)) as `shop refunds`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund >= total and refund > 0 and total > 0, 0)) as `shop full refunds`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund < total and refund > 0 and total > 0, 0)) as `shop partial refunds`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund, 0)) as `shop refund amt`
							, sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, total, 0)) - sum(if(date_format(source.completed_date, '%Y-%m') = sequence.calendar_month, refund, 0)) as `shop net rev`
						from
							cb_shop_orders as source
						join
							cb_sequence_temp_from_box_orders as sequence
						on
							source.user_id = sequence.user_id
						group by
							source.user_id, sequence.calendar_month
						order by
							source.user_id, sequence.calendar_month

					) as base

					group by
						user_id, calendar_month
					order by
						user_id, calendar_month

				) as lag_table
				
			group by
				calendar_month
			order by
				calendar_month
			;
SQL;

		$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
		if ($mysqli->connect_errno) {
			WP_CLI::error("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
		}
		$mysqli->set_charset(DB_CHARSET);
/*
		$mysqli->autocommit(FALSE);
		$mysqli->begin_transaction();
*/

		foreach (explode(';', $new_churn_base) as $statement) {
			if (!$statement) continue;
			WP_CLI::debug("Statement: $statement");
			if (!($result = $mysqli->query($statement))) {
				$mysqli->close();
				WP_CLI::error("Could not execute statement: (" . $mysqli->errno . ") " . $mysqli->error);
			}
			if (is_object($result)) {
				$result->free();
			}
		}

/*
		if (!$mysqli->commit()) {
			$mysqli->close();
			WP_CLI::error("Could not commit: (" . $mysqli->errno . ") " . $mysqli->error);
		}
*/

		$mysqli->multi_query($new_churn_base);

/*
		if ($mysqli->multi_query($new_churn_base)) {
			do {
				if ($result = $mysqli->store_result()) {
					while ($row = $result->fetch_row()) {
						WP_CLI::debug(var_export($row,true));
					}
					$result->free();
				}
				if ($mysqli->more_results()) {
					printf("-----------------\n");
				}
			} while ($mysqli->next_result());
		}
*/

		WP_CLI::debug("Done.");

		$mysqli->close();
	}

	/**
	 * Exports order data.
	 *
	 * ## OPTIONS
	 *
	 * [<order_id>...]
	 * : The order id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all orders. (Ignores <order_id>... if found).
	 *
	 * [--reverse]
	 * : Iterate orders in reverse order.
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> orders out of the list given.
	 *
	 * [--pretend]
	 * : Don't write anything, just show what we would do.
	 *
	 * [--verbose]
	 * : Print out debugging data.
	 *
	 * [--save_output]
	 * : Write results to s3 -> redshift.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb maintain_order --all
	 */
	function export_orders($args, $assoc_args) {
		global $wpdb;
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		// Schema
		$columns = array(
			'id',
			'order_types',
			'user_id',
			'parent_id',
			'status',
			'created_date',
			'completed_date',
			'sku',
			'box_credits',
			'box_month',
			'ship_month',
			'total',
			'revenue_items',
			'revenue_ship',
			'revenue_rush',
//			'stripe_fee',
			'refund',
		);

		/**
		 * Walks a list of orders sorted by date (earliest first) and returns
		 * the first order preceeding this one that counts as a box credit.
		 */
		function find_credit_parent($order, $orders) {
			$reached_us = false;
			$found_parent = false;
			// Try for a parent with a positive total
			foreach (array_reverse($orders) as $parent) {
				if ($parent === $order) $reached_us = true;
				if ($reached_us && CBWoo::order_counts_as_box_credit($parent) && doubleval($parent->total) > 0) {
					 $found_parent = $parent;
					 break;
				}
			}
			// If not, settle for whatever parent we find
			if (!$found_parent) {
				foreach (array_reverse($orders) as $parent) {
					if ($parent === $order) $reached_us = true;
					if ($reached_us && CBWoo::order_counts_as_box_credit($parent)) {
						 $found_parent = $parent;
						 break;
					}
				}
			}
			if ($found_parent) return $found_parent;
			else return $order;
		}

		$results = array();
		$boxes = array();
		$renewals = array();
		$shops = array();

		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			WP_CLI::debug("User $user_id...");

			// $orders = $customer->get_orders();
			$orders = $this->api->get_customer_orders_internal($user_id);

			foreach ($orders as $order) {

				WP_CLI::debug("\t-> order $order->id");

				if ($this->options->verbose) WP_CLI::debug(var_export($order, true));

				//if ('refunded' !== $order->status && 'processing' !== $order->status && 'completed' !== $order->status) continue;
				$is_credit = CBWoo::order_counts_as_box_credit($order);
				$is_debit = CBWoo::order_counts_as_box_debit($order);
				$order_types = array();
				if ($is_credit) $order_types[] = 'renewal';
				if ($is_debit) $order_types[] = 'box';
				if (!($is_credit || $is_debit)) $order_types[] = 'shop';
				$order_type = implode(',', $order_types);
				//if (!$is_debit) continue;
				$order_options = CBWoo::parse_order_options($order);
				$month = isset($order_options->month) ? "m$order_options->month" : null;
				$sku = implode(',', CBWoo::extract_order_skus($order));
				$parsed_sku = CBWoo::parse_box_sku($sku);
				$ship_month = $parsed_sku->ship_raw;

				$parent = find_credit_parent($order, $orders);
				$parent_credits = CBWoo::calculate_box_credit($parent)['credits'];

				if (false !== array_search('box', $order_types)) {
					$find_charge_on = $parent;
					$parent_id = $parent->id;
				} else {
					$find_charge_on = $order;
					$parent_id = null;
					$parent_credits = 0;
				}
				try {
					$stripe_charge = CBStripe::get_order_charge($find_charge_on->id);
					$stripe_total = $stripe_charge->amount / 100.0;
					$stripe_refunded = $stripe_charge->amount_refunded / 100.0;
					//$stripe_fee = $stripe_charge->fee / 100.0;
				} catch (InvalidArgumentException $e) {
					$stripe_total = 0.0;
					$stripe_refunded = 0.0;
					$stripe_fee = 0.0;
				}
				$line_item_total = array_sum(array_map(function($i) { return $i->total; }, $find_charge_on->line_items));
				$shipping_total = array_sum(array_map(function($i) { return $i->total; }, $find_charge_on->shipping_lines));
				$fee_total = array_sum(array_map(function($i) { return $i->total; }, $find_charge_on->fee_lines));
				$total = $stripe_total;
				$refunded = $stripe_refunded;
				if (false !== array_search('box', $order_types) && $parent_credits > 0) {
					$total /= $parent_credits;
					$line_item_total /= $parent_credits;
					$shipping_total /= $parent_credits;
					$fee_total /= $parent_credits;
					$refunded /= $parent_credits;
				}

				$order_row = array(
					'id' => $order->id,
					'order_types' => $order_type,
					'user_id' => $user_id,
					'parent_id' => $parent_id,
					'status' => $order->status,
					'created_date' => $order->created_at,
					'completed_date' => $order->completed_at,
					'skus' => $sku,
					'box_credits' => $parent_credits,
					'box_month' => $month,
					'ship_month' => $ship_month,
					'total' => $total,
					'revenue_items' => $line_item_total,
					'revenue_ship' => $shipping_total,
					'revenue_rush' => $fee_total,
					//'stripe_fee' => $stripe_fee,
					'refund' => $refunded,
				);
				$order_format = array(
					'%d',  // id
					'%s',  // order_type
					'%d',  // user_id
					'%d',  // parent_id
					'%s',  // status
					'%s',  // created_date
					'%s',  // completed_date
					'%s',  // skus
					'%s',  // box_month
					'%s',  // ship_month
					'%f',  // total
					'%f',  // revenue_items
					'%f',  // revenue_ship
					'%f',  // revenue_rush
					'%f',  // refund
				);

				$box_row = array(
					'id' => $order_row['id'],
					'user_id' => $order_row['user_id'],
					'parent_id' => $order_row['parent_id'],
					'status' => $order_row['status'],
					'created_date' => $order_row['created_date'],
					'completed_date' => $order_row['completed_date'],
					'sku' => $order_row['skus'],
					'box_month' => $order_row['box_month'],
					'ship_month' => $order_row['ship_month'],
					'total' => $order_row['total'],
					'revenue_items' => $order_row['revenue_items'],
					'revenue_ship' => $order_row['revenue_ship'],
					'revenue_rush' => $order_row['revenue_rush'],
					//'stripe_fee' => $stripe_fee'],
					'refund' => $order_row['refund'],
				);
				$box_format = array(
					'%d',  // id
					'%d',  // user_id
					'%d',  // parent_id
					'%s',  // status
					'%s',  // created_date
					'%s',  // completed_date
					'%s',  // sku
					'%s',  // box_month
					'%s',  // ship_month
					'%f',  // total
					'%f',  // revenue_items
					'%f',  // revenue_ship
					'%f',  // revenue_rush
					'%f',  // refund
				);
				$box_columns = array(
					'id',
					'user_id',
					'parent_id',
					'status',
					'created_date',
					'completed_date',
					'sku',
					'box_month',
					'ship_month',
					'total',
					'revenue_items',
					'revenue_ship',
					'revenue_rush',
					'refund',
				);

				$renewal_row = array(
					'id' => $order_row['id'],
					'user_id' => $order_row['user_id'],
					'status' => $order_row['status'],
					'created_date' => $order_row['created_date'],
					'completed_date' => $order_row['completed_date'],
					'sku' => $order_row['skus'],
					'box_credits' => $order_row['box_credits'],
					'total' => $order_row['total'],
					'revenue_items' => $order_row['revenue_items'],
					'revenue_ship' => $order_row['revenue_ship'],
					'revenue_rush' => $order_row['revenue_rush'],
					//'stripe_fee' => $stripe_fee'],
					'refund' => $order_row['refund'],
				);
				$renewal_format = array(
					'%d',  // id
					'%d',  // user_id
					'%s',  // status
					'%s',  // created_date
					'%s',  // completed_date
					'%s',  // sku
					'%f',  // total
					'%f',  // revenue_items
					'%f',  // revenue_ship
					'%f',  // revenue_rush
					'%f',  // refund
				);
				$renewal_columns = array(
					'id',
					'user_id',
					'status',
					'created_date',
					'completed_date',
					'sku',
					'box_credits',
					'total',
					'revenue_items',
					'revenue_ship',
					'revenue_rush',
					'refund',
				);

				$shop_row = array(
					'id' => $order_row['id'],
					'user_id' => $order_row['user_id'],
					'status' => $order_row['status'],
					'created_date' => $order_row['created_date'],
					'completed_date' => $order_row['completed_date'],
					'skus' => $order_row['skus'],
					'total' => $order_row['total'],
					'revenue_items' => $order_row['revenue_items'],
					'revenue_ship' => $order_row['revenue_ship'],
					'revenue_rush' => $order_row['revenue_rush'],
					//'stripe_fee' => $stripe_fee'],
					'refund' => $order_row['refund'],
				);
				$shop_format = array(
					'%d',  // id
					'%d',  // user_id
					'%s',  // status
					'%s',  // created_date
					'%s',  // completed_date
					'%s',  // skus
					'%f',  // total
					'%f',  // revenue_items
					'%f',  // revenue_ship
					'%f',  // revenue_rush
					'%f',  // refund
				);
				$shop_columns = array(
					'id',
					'user_id',
					'status',
					'created_date',
					'completed_date',
					'skus',
					'total',
					'revenue_items',
					'revenue_ship',
					'revenue_rush',
					'refund',
				);


				// Write to database
				$write_to_mysql = false;
				$wpdb->show_errors = true;
				// define( 'WP_DEBUG_LOG', true );
				// define( 'SAVEQUERIES', true );
				// var_dump($order_types);
				if (!$this->options->pretend) {
					if ($write_to_mysql) {
						WP_CLI::debug("\t\twriting row to database");
						if ($this->options->verbose) WP_CLI::debug(var_export($order_row, true));
						if (false === ($returned = $wpdb->replace('cb_orders', $order_row, $order_format))) {
							WP_CLI::error($wpdb->last_error);
						} 
					}
					$results[] = $order_row;
					if (false !== array_search('box', $order_types)) {
						$boxes[] = $box_row;
						if ($write_to_mysql) {
							WP_CLI::debug("\t\twriting box to database");
							if ($this->options->verbose) WP_CLI::debug(var_export($box_row, true));
							if (false === ($returned = $wpdb->replace('cb_box_orders', $box_row, $box_format))) {
								WP_CLI::error($wpdb->last_error);
							} 
						}
					}
					if (false !== array_search('renewal', $order_types)) {
						$renewals[] = $renewal_row;
						if ($write_to_mysql) {
							WP_CLI::debug("\t\twriting renewal to database");
							if ($this->options->verbose) WP_CLI::debug(var_export($renewal_row, true));
							if (false === ($returned = $wpdb->replace('cb_renewals', $renewal_row, $renewal_format))) {
								WP_CLI::error($wpdb->last_error);
							} 
						}
					}
					if (false !== array_search('shop', $order_types)) {
						$shops[] = $shop_row;
						if ($write_to_mysql) {
							WP_CLI::debug("\t\twriting shop order to database");
							if ($this->options->verbose) WP_CLI::debug(var_export($shop_row, true));
							if (false === ($returned = $wpdb->replace('cb_shop_orders', $shop_row, $shop_format))) {
								WP_CLI::error($wpdb->last_error);
							} 
						}
					}
				}
			}

		}

		//WP_CLI::debug(var_export($wpdb->queries, true));;

		if (sizeof($results)) {
			if ($this->options->save_output) {
				$this->upload_results_to_s3('command_results/orders.csv.gz', $results, $columns);
				$this->execute_redshift_queries(array(
					"DROP TABLE IF EXISTS orders;",
					"CREATE TABLE orders (
						  id INT8 NOT NULL
						, order_types VARCHAR(16) NOT NULL
						, user_id INT8 NOT NULL
						, parent_id INT8 DEFAULT NULL
						, status VARCHAR(16) DEFAULT NULL
						, created_date TIMESTAMP NOT NULL
						, completed_date TIMESTAMP NOT NULL
						, skus VARCHAR(1024) DEFAULT NULL
						, box_credits INT8 DEFAULT NULL
						, box_month VARCHAR(16) DEFAULT NULL
						, sku_month VARCHAR(16) DEFAULT NULL
						, total DECIMAL(10,2) DEFAULT '0.0'
						, revenue_items DECIMAL(10,2) DEFAULT '0.0'
						, revenue_ship DECIMAL(10,2) DEFAULT '0.0'
						, revenue_rush DECIMAL(10,2) DEFAULT '0.0'
						, refund DECIMAL(10,2) DEFAULT '0.0'
						)
						DISTKEY(user_id)
						SORTKEY(user_id,id);",
					"COPY orders FROM 's3://challengebox-redshift/command_results/orders.csv.gz' 
						CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
						CSV
						IGNOREHEADER AS 1
						NULL AS ''
						TIMEFORMAT 'auto' -- AS 'YYYY-MM-DD HH:MI:SS'
						GZIP;",
				));
				$this->upload_results_to_s3('command_results/box_orders.csv.gz', $boxes, $box_columns);
				$this->execute_redshift_queries(array(
					"DROP TABLE IF EXISTS box_orders;",
					"CREATE TABLE box_orders (
						  id INT8 NOT NULL
						, user_id INT8 NOT NULL
						, parent_id INT8 DEFAULT NULL
						, status VARCHAR(16) DEFAULT NULL
						, created_date TIMESTAMP NOT NULL
						, completed_date TIMESTAMP NOT NULL
						, sku VARCHAR(1024) DEFAULT NULL
						, box_month VARCHAR(16) DEFAULT NULL
						, sku_month VARCHAR(16) DEFAULT NULL
						, total DECIMAL(10,2) DEFAULT '0.0'
						, revenue_items DECIMAL(10,2) DEFAULT '0.0'
						, revenue_ship DECIMAL(10,2) DEFAULT '0.0'
						, revenue_rush DECIMAL(10,2) DEFAULT '0.0'
						, refund DECIMAL(10,2) DEFAULT '0.0'
						)
						DISTKEY(user_id)
						SORTKEY(user_id,id);",
					"COPY box_orders FROM 's3://challengebox-redshift/command_results/box_orders.csv.gz' 
						CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
						CSV
						IGNOREHEADER AS 1
						NULL AS ''
						TIMEFORMAT 'auto' -- AS 'YYYY-MM-DD HH:MI:SS'
						GZIP;",
				));
				$this->upload_results_to_s3('command_results/renewal_orders.csv.gz', $renewals, $renewal_columns);
				$this->execute_redshift_queries(array(
					"DROP TABLE IF EXISTS renewal_orders;",
					"CREATE TABLE renewal_orders (
						  id INT8 NOT NULL
						, user_id INT8 NOT NULL
						, status VARCHAR(16) DEFAULT NULL
						, created_date TIMESTAMP NOT NULL
						, completed_date TIMESTAMP NOT NULL
						, sku VARCHAR(1024) DEFAULT NULL
						, box_credits INT8 NOT NULL
						, total DECIMAL(10,2) DEFAULT '0.0'
						, revenue_items DECIMAL(10,2) DEFAULT '0.0'
						, revenue_ship DECIMAL(10,2) DEFAULT '0.0'
						, revenue_rush DECIMAL(10,2) DEFAULT '0.0'
						, refund DECIMAL(10,2) DEFAULT '0.0'
						)
						DISTKEY(user_id)
						SORTKEY(user_id,id);",
					"COPY renewal_orders FROM 's3://challengebox-redshift/command_results/renewal_orders.csv.gz' 
						CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
						CSV
						IGNOREHEADER AS 1
						NULL AS ''
						TIMEFORMAT 'auto' -- AS 'YYYY-MM-DD HH:MI:SS'
						GZIP;",
				));
				$this->upload_results_to_s3('command_results/shop_orders.csv.gz', $shops, $shop_columns);
				$this->execute_redshift_queries(array(
					"DROP TABLE IF EXISTS shop_orders;",
					"CREATE TABLE shop_orders (
						  id INT8 NOT NULL
						, user_id INT8 NOT NULL
						, status VARCHAR(16) DEFAULT NULL
						, created_date TIMESTAMP NOT NULL
						, completed_date TIMESTAMP NOT NULL
						, skus VARCHAR(1024) DEFAULT NULL
						, total DECIMAL(10,2) DEFAULT '0.0'
						, revenue_items DECIMAL(10,2) DEFAULT '0.0'
						, revenue_ship DECIMAL(10,2) DEFAULT '0.0'
						, revenue_rush DECIMAL(10,2) DEFAULT '0.0'
						, refund DECIMAL(10,2) DEFAULT '0.0'
						)
						DISTKEY(user_id)
						SORTKEY(user_id,id);",
					"COPY shop_orders FROM 's3://challengebox-redshift/command_results/shop_orders.csv.gz' 
						CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
						CSV
						IGNOREHEADER AS 1
						NULL AS ''
						TIMEFORMAT 'auto' -- AS 'YYYY-MM-DD HH:MI:SS'
						GZIP;",
				));
			} else {
				WP_CLI\Utils\format_items($this->options->format, $results, $columns);
			}
		}
	}

	/**
	 * Exports order data.
	 *
	 * ## OPTIONS
	 *
	 * [<order_id>...]
	 * : The order id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all orders. (Ignores <order_id>... if found).
	 *
	 * [--reverse]
	 * : Iterate orders in reverse order.
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> orders out of the list given.
	 *
	 * [--save]
	 * : Save the data to cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb export_order_data --all
	 */
	function export_order_data( $args, $assoc_args ) {
		$assoc_args['orientation'] = 'order';
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$subs = array();
		$boxes = array();
		$shops = array();

		foreach ($args as $order_id) {
			//$order = $this->api->get_order($order_id);
			$order = $this->api->get_order_internal($order_id);
			WP_CLI::debug("Order $order_id...");
			if ($this->options->verbose) WP_CLI::debug(var_export($order, true));

			$is_credit = CBWoo::order_counts_as_box_credit($order);
			$is_debit = CBWoo::order_counts_as_box_debit($order);
			$sku = CBWoo::extract_order_sku($order);
			$opts = CBWoo::parse_order_options($order);
			$created_at = CBWoo::parse_date_from_api($order->created_at);
			$completed_at = CBWoo::parse_date_from_api($order->completed_at);
			$parsed_sku = CBWoo::parse_box_sku($sku);

			//var_dump(wc_get_order($order_id));
			try {
				$stripe_charge = CBStripe::get_order_charge($order_id);
				$stripe_total = $stripe_charge->amount / 100.0;
				$stripe_refunded = $stripe_charge->amount_refunded / 100.0;
			} catch (InvalidArgumentException $e) {
				$stripe_total = 0.0;
				$stripe_refunded = 0.0;
			}

			// Try to guess the ship month
			$ship_month = $parsed_sku->ship_raw;
			try {
				$guess_date = Carbon::createFromFormat('\bym', $ship_month);
			} catch (InvalidArgumentException $e) {
				$guess_date = Carbon::instance($completed_at)->copy();
				if (empty($guess_date)) $guess_date = Carbon::instance($created_at)->copy();
			}
			if ($guess_date->day > 20) $guess_date = $guess_date->startOfMonth()->addMonth();
			else $guess_date = $guess_date->startOfMonth();
			$ship_month_guess = $guess_date->format('\bym');

			if ($is_credit) {
				$subs[] = array(
					'id' => $order->id,
					'created_cohort' => $created_at->format('Y-m'),
					'status' => $order->status,
					'customer' => $order->customer_id,
					'total' => $order->total,
					'stripe_total' => $stripe_total,
					'stripe_refunded' => $stripe_refunded,
					'sku' => $sku,
					'type' => CBWoo::extract_subscription_type($order),
					'rush' => CBWoo::order_is_rush($order),
					'ship_month' => $ship_month,
					'ship_month_guess' => $ship_month_guess,
				);
			}
			if ($is_debit) {
				$boxes[] = array(
					'id' => $order->id,
					'created_cohort' => $created_at->format('Y-m'),
					'shipped_cohort' => $completed_at->format('Y-m'),
					'stripe_total' => $stripe_total,
					'stripe_refunded' => $stripe_refunded,
					'status' => $order->status,
					'customer' => $order->customer_id,
					'sku' => $sku,
					'month' => $opts->month ? $opts->month : 'm1',
					'gender' => $opts->gender,
					't-shirt' => $opts->size,
					'sku_version' => $opts->sku_version,
					'ship_month' => $ship_month,
					'ship_month_guess' => $ship_month_guess,
				);
			}
			if (!$is_credit && !$is_debit) {
				// shop
			}
		}

		if ($this->options->save) {
			set_transient('cb_export_subscriptions_raw', $subs);
			set_transient('cb_export_boxes_raw', $boxes);
		} else {
			WP_CLI::debug("Renewals");
			$columns = array('id', 'created_cohort', 'status', 'customer', 'total', 'stripe_total', 'stripe_refunded', 'sku', 'type', 'rush', 'ship_month', 'ship_month_guess');
			WP_CLI\Utils\format_items($this->options->format, $subs, $columns);
			WP_CLI::debug("Boxes");
			$columns = array('id', 'created_cohort', 'shipped_cohort', 'status', 'customer', 'sku', 'month', 'gender', 't-shirt', 'sku_version', 'ship_month', 'ship_month_guess');
			WP_CLI\Utils\format_items($this->options->format, $boxes, $columns);
		}
	}

	/**
	 * Updates user data for the weekly challenge.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : The user id (or ids) of which to update.
	 *
	 * [--date=<date>]
	 * : Some date in the week of the challenge. Will still update using all of the data available
	 *   for that week, even if this date is partially through the week. Defaults to now.
	 *
	 * [--settle]
	 * : Settles last week's challenge and triggers emails. Should only be run once unless you're
	 *   using the --pretend option.
	 *
	 * [--all]
	 * : Work on all users *that have registered for this challenge*. (Ignores <id>... if found).
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> users out of the list given.
	 *
	 * [--reverse]
	 * : Iterate users in reverse order.
	 *
	 * [--pretend]
	 * : Don't actually work, just print out what we'd do.
   *
	 * [--no_points]
	 * : Don't update users' points. Kinda like pretend, but for testing emails.t 
	 *
	 * [--force]
	 * : Force given user to join challenge. Only useful with <id> parameters.
	 *
	 * [--verbose]
	 * : Print out extra information. (Use with --debug or you won't see anything)
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb update_weekly_challenge 167
	 */
	function update_weekly_challenge($args, $assoc_args) {
		$tz = $this->options->timezone;

		// For --settle, make sure default date is last week's challenge
		if (!empty($assoc_args['settle']) && empty($assoc_args['date'])) {
			$assoc_args['date'] = Carbon::now($tz)->subWeek()->format('Y-m-d');
		}

		// Handle --all in a different way, meaning all relevant users
		// (that have signed up for this challenge)
		if (!empty($assoc_args['all'])) {
			unset($assoc_args['all']);
			$date = empty($assoc_args['date']) ? Carbon::now($tz) : new Carbon($assoc_args['date'], $tz);
			$leaderboard = (new CBWeeklyChallenge(null, $date))->get_leaderboard();
			foreach ($leaderboard as $index => $row) { $args[] = strval($row->user_id); }
		}

		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();
		$columns = array('user_id', 'joined', 'previous_metric_level', 'metric_level', 'error');
		$joined_challenges = array();

		$global_challenge = new CBWeeklyChallenge(null, $this->options->date);
		WP_CLI::debug("Challenge that starts $global_challenge->start and ends $global_challenge->end");

		// Update each customer's progress
		foreach ($args as $user_id) {
			$customer = new CBCustomer($user_id, $interactive = false);
			$challenge = new CBWeeklyChallenge($customer, $this->options->date);
			WP_CLI::debug("\tCustomer $user_id...");
			
			try {
				if ($challenge->user_has_joined) {
					$joined_challenges[] = $challenge;
					WP_CLI::debug("\t\t-> fetching progress");
					$challenge->fetch_user_progress();
					if (!$this->options->pretend) {
						WP_CLI::debug("\t\t-> saving progress");
						$challenge->save_user_progress();
					}
				} else {
					if (!$this->options->pretend && $this->options->force) {
						WP_CLI::debug("\t\t-> fetching progress");
						$challenge->fetch_user_progress();
						WP_CLI::debug("\t\t-> saving progress");
						$challenge->save_user_progress();
					}
				}
				$results[] = array(
					'user_id' =>  $user_id,
					'joined' =>  $challenge->user_has_joined ? 'true' : 'false',
					'previous_metric_level' => $challenge->previous_metric_level,
					'metric_level' => $challenge->metric_level,
					'error' => null,
				);
			} catch (FitbitNeedsAuth $e) {
				$results[] = array(
					'user_id' =>  $user_id,
					'joined' =>  $challenge->user_has_joined ? 'true' : 'false',
					'previous_metric_level' => $challenge->previous_metric_level,
					'metric_level' => $challenge->metric_level,
					'error' => $e->getMessage(),
				);
			} catch (League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$results[] = array(
					'user_id' =>  $user_id,
					'joined' =>  $challenge->user_has_joined ? 'true' : 'false',
					'previous_metric_level' => $challenge->previous_metric_level,
					'metric_level' => $challenge->metric_level,
					'error' => $e->getMessage(),
				);
			}
		}

		// For the --settle option, we calculate their leaderboard as well
		// and generate the winners list.
		// NOTE: This relies on fetch_user_progress() and save_user_progress()
		//       being called for each user above to generate a consistent
		//       leaderboard.
		if ($this->options->settle) {

			$segment = new CBSegment();

			foreach ($joined_challenges as $challenge) {
				WP_CLI::debug("\tSettling customer " . $challenge->customer->get_user_id() . "...");

				// Use the already cached copy of the leaderboard to avoid db calls
				$challenge->get_leaderboard($global_challenge->get_leaderboard());

				// Prepare analytics/email trigger data
				$rank = $challenge->get_rank();
				$params = array(
					'participants' => sizeof($challenge->get_leaderboard()),
					'rank' => $rank,
					'rank_ordinal' => CB::ordinal($rank),
					'rank_emoji' => $challenge->emoji_for_rank($rank),
					'rank_points' => $challenge->points_for_rank($rank),
					'leaderboard_html' => $challenge->render_leaderboard(true),
				);

				// Apply points 
				if (!$this->options->pretend && !$this->options->no_points) {
					WP_CLI::debug("\t\t-> applying points");
					$challenge->apply_points();
				}

				// Send analytics event
				WP_CLI::debug("\t\t-> sending analytics event");
				$segment->track($challenge->customer, 'Completed Weekly Challenge', $params, $this->options->pretend);
				$segment->flush();
			}

			if (!$this->options->pretend) {
				$global_challenge->settled = true;
				$global_challenge->save_global();
			}

		}

		if (sizeof($results))
			WP_CLI\Utils\format_items($this->options->format, $results, $columns);
	}

    /**
	 * Exports subscription event data.
	 *
	 * ## OPTIONS
	 *
	 * [<sub_id>...]
	 * : The subscription id(s) to check.
	 *
	 * [--all]
	 * : Iterate through all subscriptions. (Ignores <sub_id>... if found).
	 *
	 * [--reverse]
	 * : Iterate subscriptions in reverse order.
	 *
	 * [--limit=<limit>]
	 * : Only process <limit> subscriptions out of the list given.
	 *
	 * [--save_output]
	 * : Write results to s3 -> redshift.
	 *
	 * [--format=<format>]
	 * : Output format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - yaml
	 *    - csv
	 *    - json
	 *  ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb export_subscription_events
	 */
	function export_subscription_events( $args, $assoc_args ) {
		global $wpdb;
		$assoc_args['orientation'] = 'subscription';
		list($args, $assoc_args) = $this->parse_args($args, $assoc_args);

		$results = array();

		foreach ($args as $sub_id) {
			$sub_id = intval($sub_id);
			$sub = wcs_get_subscription($sub_id);
			WP_CLI::debug("Subscription $sub_id...");
			$user_id = $sub->get_user_id();

			$rows = $wpdb->get_results("select * from wp_comments where comment_post_ID = $sub_id");
			//var_dump($rows);

			$found_initial_state = false;
			foreach ($rows as $comment) {

				$date = $comment->comment_date_gmt; //new Carbon($comment->comment_date_gmt);	
				$text = $comment->comment_content;	
				$event = 'other';
				$old_state = '';
				$new_state = '';

				//var_dump(['date'=>$date, 'text'=>$text]);
				$matches = array();

				// Categorize comment into state changes or other events
				if (preg_match('/Status changed from (.*) to (.*)\.$/', $text, $matches)) {
					$event = 'state-change';
					list($_, $old_state, $new_state) = $matches;
					$old_state = str_replace(' ', '-', strtolower($old_state));
					$new_state = str_replace(' ', '-', strtolower($new_state));

					// Attach initial state if we didn't have it already
					if ($found_initial_state === false) {
						$found_initial_state = true;
						$results[] = array(
							'sub_id' => $sub_id,
							'user_id' => $user_id,
							'event' => $event,
							'date' => $sub->post->post_date_gmt,
							'old_state' => '',
							'new_state' => $old_state,
							'comment' => $text,
						);
					}

				} elseif (preg_match('/Order (.*) created to record renewal\./', $text, $matches)) {
					$event = 'order-created';
				} elseif (preg_match('/Payment received\./', $text, $matches)) {
					$event = 'payment-received';
				} elseif (preg_match('/Payment failed\./', $text, $matches)) {
					$event = 'payment-failed';
				} elseif (preg_match('/Subscription cancelled by the subscriber from their account page\./', $text, $matches)) {
					$event = 'user-cancelled';
				} elseif (preg_match('/Subscription put on hold by the subscriber from their account page\./', $text, $matches)) {
					$event = 'user-hold';
				} elseif (preg_match('/Payment method changed from (.*) to (.*) by the subscriber from their account page\./', $text, $matches)) {
					$event = 'user-card-update';
				} elseif (preg_match('/Subscription reactivated by the subscriber from their account page\./', $text, $matches)) {
					$event = 'user-reactivated';
				}

				$results[] = array(
					'sub_id' => $sub_id,
					'user_id' => $user_id,
					'event' => $event,
					'date' => $date,
					'old_state' => $old_state,
					'new_state' => $new_state,
					'comment' => $text,
				);

			}
		}

		$columns = array(
			'sub_id',
			'user_id',
			'event',
			'date',
			'old_state',
			'new_state',
			'comment',
		);

		if (sizeof($results)) {
			if ($this->options->save_output) {
				$this->upload_results_to_s3('command_results/subscription_events.csv.gz', $results, $columns);
				$this->execute_redshift_queries(array(
					"DROP TABLE IF EXISTS sub_events;",
					"CREATE TABLE sub_events (
						sub_id INT8 NOT NULL
						, user_id INT8 NOT NULL
						, event VARCHAR(32) DEFAULT NULL
						, date TIMESTAMP NOT NULL
						, old_state VARCHAR(64) DEFAULT NULL
						, new_state VARCHAR(64) DEFAULT NULL
						, comment VARCHAR(1024) DEFAULT NULL
						)
						DISTKEY(user_id)
						SORTKEY(user_id,sub_id);",
					"COPY sub_events FROM 's3://challengebox-redshift/command_results/subscription_events.csv.gz' 
						CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
						CSV
						IGNOREHEADER AS 1
						NULL AS ''
						TIMEFORMAT 'auto' -- AS 'YYYY-MM-DD HH:MI:SS'
						GZIP;",
				));
			} else {
				WP_CLI\Utils\format_items($this->options->format, $results, $columns);
			}
		}

	}
	
	/**
	 * getUserIds
	 */
	public function getUserIds()
	{
		// for each user, get their ID
		$userParams = array(
			'fields' => array('ID')
		);
		
		$users = get_users($userParams);
		
		$userIds = array();
		foreach ($users as $user) {
			$userIds[] = $user->ID;
		}
		
		return $userIds;
	}
	
	/**
	 * Get customers fitbit
	 */
	public function get_customers_fitbit($userId, $interactive = false)
	{
		$customer = new CBCustomer($userId, $interactive);
		return $customer->fitbit();
	}
	
	/**
	 * Get Carbon
	 */
	public function get_carbon()
	{
		if (is_null($this->carbon)) {
			$this->carbon = new Carbon();
		}
		return $this->carbon;
	}
	
	/**
	 * exec
	 */
	protected function exec($command, &$output, &$returnValue) {
		exec($command, $output, $returnValue);
	}
	
	/**
	 * getHomePath
	 */
	public function getHomePath()
	{
		return get_home_path();
	}
	
	/**
	 * 
	 */
	
	/**
	 * ingest_daily_tracking
	 */
	public function ingest_daily_tracking($args, $assocArgs) {

		// make sure required dates are provided
		if (count($args) < 2) {
			echo 'wp cb ingest_daily_tracking <start date> <end date> [<block_size>]';
			return;
		}

		$debug = (isset($assocArgs['debug']) && $assocArgs['debug'] == 1);
		$homePath = $this->getHomePath();
		
		$blockSize = (isset($args[2])) ? $args[2] : 100;
		
		list($startDate, $endDate) = $args;

		$cbRawTrackingData = BaseFactory::getInstance()->generate('CBRawTrackingData');			
		
		$users = $this->getUserIds();
		
		$userBlocks = array_chunk($users, $blockSize);
		
		$numberOfUsers = count($users);
		
		$successfulUsers = 0;
		foreach ($userBlocks as $userBlock) {
			
			if ($debug) {
				echo '------------------' . PHP_EOL;
				echo 'Memory (before block processing): ' . memory_get_usage() . PHP_EOL;
			}
			
			$output = array();
			
			$userList = implode(' ', $userBlock);
			$this->exec('wp cb ingest_daily_tracking_for_user_block ' . $startDate . ' ' . $endDate . ' ' . $userList . ' --allow-root --debug --path="' . $homePath . '" ', $output, $numberOfSuccessfulUsers);
		
			if ($debug) {
				foreach ($output as $line) {
					echo $line . PHP_EOL;
				}
			}
			
			$successfulUsers += $numberOfSuccessfulUsers;

			if ($debug) {
				echo 'Memory (after block processing): ' . memory_get_usage() . PHP_EOL;
			}
		}

		if ($debug) {
			echo '------------------' . PHP_EOL;
			echo 'Total Users: ' . $numberOfUsers . PHP_EOL;
			echo 'Successful Users: ' . $successfulUsers . PHP_EOL;
			echo 'Unsuccessful Users: ' . ($numberOfUsers - $successfulUsers) . PHP_EOL;
		}
	}
	
	/**
	 * ingest_daily_tracking_for_user_block
	 */
	public function ingest_daily_tracking_for_user_block($args, $assocArgs) 
	{
		// make sure required dates are provided
		if (count($args) < 3) {
			echo 'wp cb ingest_daily_tracking <start date> <end date> <user_id1> [<user_id2> ... <user_id3>]';
			return;
		}

		$debug = (isset($assocArgs['debug']) && $assocArgs['debug'] == 1);
		
		$cbRawTrackingData = BaseFactory::getInstance()->generate('CBRawTrackingData');
		
		$startDate = $args[0];
		$endDate = $args[1];
		$userIds = array_slice($args, 2);
		
		list($startDate, $endDate) = $args;

		// build list of all fitbit activities to be included
		$activities = array(
				'caloriesIn',
				'water',
				'caloriesOut',
				'steps',
				'distance',
				'floors',
				'elevation',
				'minutesSedentary',
				'minutesLightlyActive',
				'minutesFairlyActive',
				'minutesVeryActive',
				'activityCalories',
				'tracker_caloriesOut',
				'tracker_steps',
				'tracker_distance',
				'tracker_floors',
				'tracker_elevation',
				'startTime',
				'timeInBed',
				'minutesAsleep',
				'awakeningsCount',
				'minutesAwake',
				'minutesToFallAsleep',
				'minutesAfterWakeup',
				'efficiency',
				'weight',
				'bmi',
				'fat',
				'activities_steps' 
		);

		// get starts into Carbon format
		$carbon = $this->get_carbon();
		$start = $carbon->createFromFormat('Y-m-d', $startDate);
		$end = $carbon->createFromFormat('Y-m-d', $endDate);
				
		
		$successfullyProcessedUsers = 0;
		foreach ($userIds as $userId) {
			try {
				
				// if the user has a fitbit connection
				$fitbit = $this->get_customers_fitbit($userId);

				if (!is_null($fitbit)) {
						
					// get fitbit data
					$rawData = array();
					foreach ($activities as $activity) {
						$results = $fitbit->get_cached_time_series($activity, $start, $end);
						$rawData = array_merge($rawData, array($activity => $results));
					}

					$cbRawTrackingData->multiSave($userId, CBRawTrackingData::FITBIT_V1_SOURCE, $rawData);

					if ($debug) {
						echo 'Success: User ID - ' . $userId . PHP_EOL;
					}
					$successfullyProcessedUsers++;
				
				} else if ($debug) {
					echo 'Error: User ID - ' . $userId . ', Not fitbit connection available.' . PHP_EOL;
				}
	
			} catch (Exception $e) {
				
		var_dump($e->getMessage());
				if ($debug) {
					echo 'Error: User ID - ' . $userId . ', Message - ' . $e->getMessage() . PHP_EOL;
				}
			}
		}
		
		return $successfullyProcessedUsers;
	}
	
	
	/**
	 * aggregate raw data
	 */
	public function aggregate_raw_data($args, $assocArgs)
	{
		// make sure required dates are provided
		if (count($args) < 2) {
			echo 'wp cb aggregate_raw_data <start date> <end date>';
			return;
		}
		
		list($startDate, $endDate) = $args;
		
		// dependencies
		$baseFactory = BaseFactory::getInstance();
		$rawDataObject = $baseFactory->generate('CBRawTrackingData');
		$aggregateDataObject = $baseFactory->generate('CBAggregateTrackingData');
		
		$userIds = $this->getUserIds();
		foreach ($userIds as $userId) {	
			// get raw data
			$rawData = $rawDataObject->findByUserIdAndDates($userId, $startDate, $endDate);
			
			// aggregate and save the data
			$aggregateDataObject->aggregateAndSave($rawData);
		}
		
	}

}


WP_CLI::add_command( 'cb', 'CBCmd' );

