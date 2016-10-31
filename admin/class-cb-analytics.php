<?php

class CBAnalytics_Admin {

	public function __construct($capability) {
		$this->capability = $capability;
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
	}

	public function add_admin_pages() {
		add_submenu_page( 'cb-home', 'Analytics', 'Analytics', $this->capability, 'cb-analytics', array( $this, 'analytics_page' ) );
	}

	public function analytics_page() {
		$table = new CBMonthly_Table();
		$table->prepare_items();
		$ss_table = new CBSubStatus_Table();
		$ss_table->prepare_items();
		?>
			<div class="wrap">
				<div id="icon-users" class="icon32">test</div>
				<h2>Monthly Overview</h2>
				<?php $table->display(); ?>

				<h2>Subscription Statuses</h2>
				<p>Where are they now? This table shows the WooCommerce status of each subscription based on it's start date.</p>
				<?php $ss_table->display(); ?>
		<?php
		$cohort_table = new CBCohort_Table();
		$cohort_table->table_data();
		$cohort_table->prepare_items();
		?> 
				<h2>Active Subscriptions</h2>
				<?php $cohort_table->display(); ?>
		<?php
		$cohort_table->variable = 'churned';
		$cohort_table->prepare_items();
		?> 
				<h2>Churned Subscriptions</h2>
				<?php $cohort_table->display(); ?>
		<?php
		$cohort_table->variable = 'activated';
		$cohort_table->prepare_items();
		?> 
				<h2>Activated Subscriptions</h2>
				<p>Includes both activated and re-activated.</p>
				<?php $cohort_table->display(); ?>
		<?php
		$cohort_table->variable = 'reactivated';
		$cohort_table->prepare_items();
		?> 
				<h2>Re-Activated Subscriptions</h2>
				<?php $cohort_table->display(); ?>
		<?php
		$cohort_table->variable = 'churn_danger';
		$cohort_table->prepare_items();
		?> 
				<h2>Churn-Danger for Subscriptions</h2>
				<p>A subscription is in churn danger if it enters a non-active state during the given month. This is an experiment to help us predict the number of churned subscriptions at the end of the month.</p>
				<?php $cohort_table->display(); ?>
		<?php
		$cohort_table->variable = 'churn_prediction';
		$cohort_table->prepare_items();
		?> 
				<h2>Churn Prediction for Subscriptions</h2>
				<p>We predict a subscription will churn if it's current state is not active, but it has been active for at least a day this month.</p>
				<?php $cohort_table->display(); ?>
		<?php
		?>
			</div>
		<?php
	}
}

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class CBMonthly_Table extends WP_List_Table  {

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$data = $this->table_data();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $data;
	}

	public function get_columns() {
		$columns = array(
			'calendar_month' => 'Month',
			'boxes_created' => '<span title="Source: WooCommerce orders. Method: Count unique order ids if order contains box sku for each month in order created_date.">Boxes Created</span>',
			'boxes_shipped' => '<span title="Source: WooCommerce orders. Method: Count unique order ids if order contains box sku for each month in order completed_date.">Boxes Shipped</span>',
			'shop_orders_created' => '<span title="Source: WooCommerce orders. Method: Count unique order id if order does not contain box sku for each month in order create_date.">Shop Orders Created</span>',
			'shop_orders_shipped' => '<span title="Source: WooCommerce orders. Method: Count unique order id if order does not contain box sku for each month in order completed_date.">Shop Orders Shipped</span>',
			'charges_succeeded' => '<span title="Source: Stripe. Method: Count charge ids for successfull charges where the charge date is in the given month.">Charges Succeeded</span>',
			'charges_failed' => '<span title="Source: Stripe. Method: Count charge ids for failed charges where the charge date is in the given month.">Charges Failed</span>',
			'refunds_succeeded' => '<span title="Source: Stripe. Method: Count refund ids where refund succeeded and the refund date is in the given month.">Refunds Succeeded</span>',
			'refunds_failed' => '<span title="Source: Stripe. Method: Count charge ids where the refund failed and the refund date is in the given month.">Refunds Failed</span>',
			'total_amount_charged' => '<span title="Source: Stripe. Method: Sum charge amounts where charge succeeded and the charge date is in the given month.">Total Amount Charged</span>',
			'total_amount_refunded' => '<span title="Source: Stripe. Method: Sum refund amounts where the refund date is in the given month.">Total Amount Refunded</span>',
			'net_revenue' => '<span title="Source: Stripe. Method: Total amount charged less total amount refunded.">Net Revenue</span>',
			'new_subscriptions' => '<span title="Source: WooCommerce. Method: Count unique subscription ids where subscription start date is in the given month.">New Subscriptions</span>',
			'user_cancelled' => '<span title="Source: WooCommerce. Method: Count subscription comments where the comment indicates the user cancelled and the comment is in the given month.">User Cancellation</span>',
			'user_hold' => '<span title="Source: WooCommerce. Method: Count subscription comments where the comment indicates the user placed subscription on hold and the comment is in the given month.">Subscription Placed on Hold</span>',
			'user_reactivated' => '<span title="Source: WooCommerce. Method: Count subscription comments where the comment indicates the user reactivated and the comment is in the given month.">User Reactivated</span>',
			'booked_revenue' => '<span title="Source: WooCommerce. Method: Complicated.">Booked Revenue</span>',
			'box_reactivated' => '<span title="Source: WooCommerce. Method: Complicated.">Box Reactivated</span>',
			'box_activated' => '<span title="Source: WooCommerce. Method: Complicated.">Box Activated</span>',
			'box_active' => '<span title="Source: WooCommerce. Method: Complicated.">Box Active</span>',
			'box_churned' => '<span title="Source: WooCommerce. Method: Complicated.">Box Churn</span>',
			'box_reactivated2' => '<span title="Source: WooCommerce. Method: Complicated.">Box Reactivated (2 month)</span>',
			'box_activated2' => '<span title="Source: WooCommerce. Method: Complicated.">Box Activated (2 month)</span>',
			'box_active2' => '<span title="Source: WooCommerce. Method: Complicated.">Box Active (2 month)</span>',
			'box_churned2' => '<span title="Source: WooCommerce. Method: Complicated.">Box Churn (2 month)</span>',
			'subs_reactivated' => '<span title="Source: WooCommerce. Method: Complicated.">Sub Reactivated</span>',
			'subs_activated' => '<span title="Source: WooCommerce. Method: Complicated.">Sub Activated</span>',
			'subs_active' => '<span title="Source: WooCommerce. Method: Complicated.">Sub Active</span>',
			'subs_churned' => '<span title="Source: WooCommerce. Method: Complicated.">Sub Churn</span>',
		);
		return $columns;
	}
	public function get_hidden_columns() {
		return array(
			'box_credits_alt',
			'box_debits_alt',
			'charges_failed',
			'refunds_failed',
			'total_credits_alt',
			'total_debits_alt',
			'box_balance_alt',
			'boxes_behind_alt',
			'rev_per_box_alt',
			'new_subscriptions',
			'shop_orders_created',
			'shop_orders_shipped',
			'user_cancelled',
			'user_hold',
			'user_reactivated',
		);
	}
	protected function format_number($val) {
		if ($val == 0) $val = '';
		if ($val > 1000.0) {
			if (is_float($val)) $val = number_format($val, 2);
			else $val = number_format($val);
		}
		return $val;
	}
	protected function format_percent($val, $total) {
		if ($total > 0) $pct = 100.0 * $val / $total;	
		else return 0;
		if ($pct > 10.0) return number_format($pct);
		if ($pct > 1.0) return number_format($pct, 1);
		return number_format($pct, 2);
	}
	protected function format_percent_column($value, $total) {
		$num = $this->format_number($value);
		$pct = $this->format_percent($value, $total);
		if ($pct) return "<b>$num</b> ($pct%)";
		return '';
	}
	public function get_sortable_columns() {
		//return array('title' => array('title', false));
		return array();
	}
	public function column_default( $item, $column_name ) {
		$val = $item[$column_name];
		switch ($column_name) {
			case 'charges_succeeded':
			case 'charges_failed':
				return $this->format_percent_column($val, $item['charges_succeeded'] + $item['charges_failed']);
			case 'total_amount_refunded':
				return $this->format_percent_column($val, $item['total_amount_charged']);
			case 'box_churned':
				return $this->format_percent_column($val, $item['box_active']);
			case 'box_churned2':
				return $this->format_percent_column($val, $item['box_active2']);
			case 'subs_churned':
				return $this->format_percent_column($val, $item['subs_active']);
			default:
				if (is_numeric($val)) {
					return '<b>' . $this->format_number($val) . '</b>';
				}
				return $val;
		}
	}

	private function table_data() {
		$schema = isset($_GET['schema']) ? $_GET['schema'] : null;
		$rs = new CBRedshift($schema);
		return $rs->execute_query('SELECT * FROM monthly_analytics ORDER BY calendar_month;');
	}
}

class CBSubStatus_Table extends CBMonthly_Table  {
	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$data = $this->table_data();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $data;
	}
	public function get_columns() {
		$columns = array(
			'start_month' => 'Start Month',
			'total' => 'Total',
			'active' => 'Active',
			'pending' => 'Pending',
			'pending_cancel' => 'Pending Cancellation',
			'cancelled' => 'Cancelled',
			'switched' => 'Switched',
			'expired' => 'Expired',
			'on_hold' => 'On hold',
		);
		return $columns;
	}
	public function column_default( $item, $column_name ) {
		$val = $item[$column_name];
		if (is_numeric($val) && $val == 0) return '';
		if ($column_name !== 'start_month' && $column_name !== 'total') {
			return $this->format_percent_column($val, $item['total']);
		}
		if (is_numeric($val)) {
			return '<b>' . $this->format_number($val) . '</b>';
		}
		return $val;
	}
	private function table_data() {
		$schema = isset($_GET['schema']) ? $_GET['schema'] : null;
		$rs = new CBRedshift($schema);
		return $rs->execute_query('SELECT * FROM subscription_status_by_start_month;');
	}
}

class CBCohort_Table extends CBMonthly_Table  {
	private $months_activated;
	private $calendar_months;
	private $data;
	public $variable = 'active';
	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $this->data[$this->variable];
	}
	public function get_columns() {
		$columns = array('month_activated' => '<b>Month Activated</b>');
		foreach ($this->calendar_months as $month) {
			if ($month == '2016-01') {
				$columns[$month] = "<b>Calendar Month</b> <br/> $month";
			} else {
				$columns[$month] = "<br/> $month";
			}
		}
		return $columns;
	}
	public function table_data() {
		$schema = isset($_GET['schema']) ? $_GET['schema'] : null;
		$rs = new CBRedshift($schema);
		$calendar_months = array();
		$months_activated = array();
		$variables = array();
		$data = array();
		// Catlogue rows, columns and variable names
		foreach ($rs->execute_query('SELECT * FROM subscription_churn_cohort_analysis') as $row) {
			$calendar_month = null;
			$month_activated = null;
			foreach ($row as $key => $value) {
				if ($key == 'calendar_month') { 
					$calendar_months[$value] = true;
					$calendar_month = $value;
				}
				if ($key == 'month_activated') {
					$months_activated[$value] = true;
					$month_activated = $value;
				}
				else {
					$variables[$key] = $true;
					if (!isset($data[$key])) { $data[$key] = array(); }
					if (!isset($data[$key][$month_activated])) { $data[$key][$month_activated] = array('month_activated'=>$month_activated); }
					if (!isset($data[$key][$month_activated][$calendar_month])) { $data[$key][$month_activated][$calendar_month] = $value; }
					if (!isset($data[$key]['total'][$calendar_month])) { $data[$key]['total'][$calendar_month] = 0; }
					$data[$key]['total'][$calendar_month] += $value;
				}
			}
		}
		ksort($calendar_months);
		ksort($months_activated);
		$this->calendar_months = array_keys($calendar_months);
		$this->months_activated = array_keys($months_activated);
		foreach ($data as $variable => $drow) {
			ksort($data[$variable]);
			$data[$variable]['total']['month_activated'] = 'Total';
		}
		$this->data = $data;
		return $data;
	}
}
