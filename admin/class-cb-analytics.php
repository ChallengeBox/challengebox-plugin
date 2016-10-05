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
			//'user_cancelled' => '<span title="Source: WooCommerce. Method: Count subscription comments where the comment indicates the user cancelled and the comment is in the given month.">User Cancellation</span>',
			//'user_hold' => '<span title="Source: WooCommerce. Method: Count subscription comments where the comment indicates the user placed subscription on hold and the comment is in the given month.">Subscription Placed on Hold</span>',
			//'user_reactivated' => '<span title="Source: WooCommerce. Method: Count subscription comments where the comment indicates the user reactivated and the comment is in the given month.">User Reactivated</span>',
		);
		return $columns;
	}
	public function get_hidden_columns() {
		return array(
			'box_credits_alt',
			'box_debits_alt',
			'total_credits_alt',
			'total_debits_alt',
			'box_balance_alt',
			'boxes_behind_alt',
			'rev_per_box_alt',
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
			default:
				if (is_numeric($val)) {
					return '<b>' . $this->format_number($val) . '</b>';
				}
				return $val;
		}
	}

	private function table_data() {
		$analytics = new CBAnalytics($string=false, $schema='production');
		return $analytics->get_monthly_analytics();
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
		$analytics = new CBAnalytics($string=false, $schema='production');
		return $analytics->get_monthly_ss();
	}
}
