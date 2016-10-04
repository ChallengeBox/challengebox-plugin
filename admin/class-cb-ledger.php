<?php

class CBLedger_Admin {

	public function __construct($capability) {
		$this->capability = $capability;
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		//add_action( 'init', array( $this, 'some_post_function' ) );
	}

	public function add_admin_pages() {
		add_submenu_page( 'cb-home', 'Users', 'Users', $this->capability, 'cb-credits', array( $this, 'summary_page' ) );
		add_submenu_page( 'cb-home', 'Box Credit Ledger', 'Box Credit Ledger', $this->capability, 'cb-ledger', array( $this, 'ledger_page' ) );
		//add_menu_page( 'Box Credits', 'Box Credits', $this->capability, 'cb-ledger', array( $this, 'ledger_page' ) );
	}

	private function get_user_id() {
		//if (isset($_POST['user_id']) && is_numeric($_POST['s']))
		//	return intval($_POST['s']);
		if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
			//$_POST['s'] = $_GET['user_id'];
			return intval($_GET['user_id']);
		}
	}

	public function ledger_page() {
		$user_id = $this->get_user_id();
		$table = new CBLedger_Table($user_id);
		$table->prepare_items();
		?>
			<div class="wrap">
				<!--form method="post">
				<input type="hidden" name="page" value="user_id" />
				<?php $table->search_box('filter by user id', 'user_id'); ?>
				</form>
				-->
				<div id="icon-users" class="icon32"></div>
				<?php if ($user_id) : ?>
				<h2>Box Credit Ledger for User <?php echo $table->column_user_id(array('user_id'=>$user_id)) ?></h2>
				<?php else : ?>
				<h2>Box Credit Ledger</h2>
				<?php endif ?>
				<?php $table->display(); ?>
			</div>
		<?php
	}

	public function summary_page() {
		$user_id = $this->get_user_id();
		$table = new CBLedgerSummary_Table($user_id);
		$table->prepare_items();
		?>
			<div class="wrap">
				<!--form method="post">
				<input type="hidden" name="page" value="user_id" />
				<?php $table->search_box('filter by user id', 'user_id'); ?>
				</form>
				-->
				<div id="icon-users" class="icon32"></div>
				<?php if ($user_id) : ?>
				<h2>Box Credit Summary for User <?php echo $table->column_user_id(array('user_id'=>$user_id)) ?></h2>
				<?php else : ?>
				<h2>Box Credit Summary by User</h2>
				<?php endif ?>
				<?php $table->display(); ?>
			</div>
		<?php
	}
}

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class CBLedger_Table extends WP_List_Table  {

	private $user_id;

	public function __construct($user_id = false) {
		$this->user_id = $user_id;
		parent::__construct();
	}

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
			'user_id' => 'User',
			'event_date' => 'Date',
			'event' => 'Event',
			'status' => 'Status',
			'sku' => 'SKU',
			'amt' => 'Transaction Amount',
			'revenue' => 'Net Revenue Impact',
			'total_revenue' => 'Total Revenue to Date',
			'box_credits' => 'Box Credits',
			'box_debits' => 'Box Debits',
			'total_credits' => 'Box Credits to Date',
			'total_debits' => 'Box Debits to Date',
			'months_since_join' => 'Months Since User Joined',
			'box_balance' => 'Credit Balance to Date',
			'boxes_behind' => 'Boxes Behind',
			'rev_per_box' => 'Revenue Per Box',
			'box_credits_alt' => 'Box Credits (alt)',
			'box_debits_alt' => 'Box Debits (alt)',
			'total_credits_alt' => 'Box Credits to Date (alt)',
			'total_debits_alt' => 'Box Debits to Date (alt)',
			'box_balance_alt' => 'Credit Balance to Date (alt)',
			'boxes_behind_alt' => 'Boxes Behind (alt)',
			'rev_per_box_alt' => 'Revenue Per Box (alt)',
		);
		if ($this->user_id) unset($columns['user_id']);
		return $columns;
	}
	public function get_hidden_columns() {
		/*
		return array(
			'box_credits_alt',
			'box_debits_alt',
			'total_credits_alt',
			'total_debits_alt',
			'box_balance_alt',
			'boxes_behind_alt',
			'rev_per_box_alt',
		);
		*/
	}
	public function get_sortable_columns() {
		//return array('title' => array('title', false));
		return array();
	}
	public function column_event($item) {
		switch($item['event_type']) {
		case 'renewal':
			$name = 'Woo Renewal';
			$url = get_edit_post_link($item['id']);
			$link_text = '#' . $item['id'];
			break;
		case 'box':
			$name = 'Woo Order';
			$url = get_edit_post_link($item['id']);
			$link_text = '#' . $item['id'];
			break;
		case 'payment':
			$name = 'Stripe Payment';
			$url = 'https://dashboard.stripe.com/payments/' . $item['id'];
			$link_text = '&helip;' . substr($item['id'], -9, 8);
			$link_text = substr($item['id'], 0, 8) . '&hellip;';
			break;
		case 'refund':
			$name = 'Stripe Refund';
			$url = 'https://dashboard.stripe.com/refunds/' . $item['id'];
			$link_text = '&helip;' . substr($item['id'], -9, 8);
			$link_text = substr($item['id'], 0, 8) . '&hellip;';
			break;
		}
		return "<nobr>$name</nobr> <a href=\"$url\">$link_text</a>";
	}
	/*
	public function column_sku($item) {
		return "<nobr>" . $item['sku'] . "</nobr>";
	}
	*/
	public function column_user_id($item) {
		$user_id = intval($item['user_id']);
		//$ud = get_userdata($user_id);
		$customer = new CBCustomer($user_id);
		$first = $customer->get_meta('first_name');
		$last = $customer->get_meta('last_name');
		$edit_url = admin_url("user-edit.php?user_id=$user_id");
		$detail_url = admin_url("admin.php?page=cb-ledger&user_id=$user_id");
		$result = "#$user_id <a href=\"$edit_url\">$first $last</a>";
		/*
		if ($this INSTANCEOF CBLedgerSummary_table) {
			$result .= "<br/><a href=\"$detail_url\">credit detail</a>";
		}
		*/
		return $result;
	}
	public function column_default( $item, $column_name ) {
		$val = $item[$column_name];
		if (is_numeric($val) && $val == 0) $val = '';
		
		switch ($column_name) {
			case 'total_revenue':
			case 'total_credits':
			case 'total_debits':
			case 'box_balance':
			case 'rev_per_box':
			case 'months_since_joined':
			case 'boxes_behind':
		/*
				if (!$this->user_id) {
					$user_id = intval($item['user_id']);
					$detail_url = admin_url("admin.php?page=cb-ledger&user_id=$user_id");
					$val .= " <a href=\"$detail_url\">[&hellip;]</a>";
				}
*/
				break;
			default:
				break;
		};

		// Flag where alt doesn't match regular column
		if (substr($column_name, strlen($column_name)-4, 4) === '_alt') {
			$regular_col = substr($column_name, 0, strlen($column_name)-4);

			if ($item[$regular_col] !== $item[$column_name]) {
				$val = "<span style=\"color:salmon;\">$val</span>";
			}
		}
			
		return $val;
	}

	private function table_data() {
		$ledger = new CBLedger();

		$limit = 25;
		$current_page = $this->get_pagenum();
		if ($this->user_id)	{
			$total_items = $ledger->get_ledger_count_for_user($this->user_id);
		} else {
			$total_items = $ledger->get_ledger_count();
		}
		$offset= ($current_page-1) * $limit;

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $limit
		));

		if ($this->user_id) {
			return $ledger->get_ledger_for_user($this->user_id, $limit, $offset);
		} else {
			return $ledger->get_ledger($limit, $offset);
		}
	}
}

class CBLedgerSummary_Table extends CBLedger_Table  {

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
			'user_id' => 'User',
			'subscription_status' => 'Subscription Status',
			'subscription_type' => 'Subscription Type',
			'total_revenue' => 'Total Revenue',
			'total_credits' => 'Box Credits',
			'total_debits' => 'Box Debits',
			'months_since_join' => 'Months Since Joined',
			'box_balance' => 'Credit Balance',
			'boxes_behind' => 'Boxes Behind',
			'rev_per_box' => 'Revenue Per Box',
			'total_credits_alt' => 'Box Credits (alt)',
			'total_debits_alt' => 'Box Debits (alt)',
			'box_balance_alt' => 'Credit Balance (alt)',
			'boxes_behind_alt' => 'Boxes Behind (alt)',
			'rev_per_box_alt' => 'Revenue Per Box (alt)',
			'mismatch' => 'Alt Mismatch',
			'detail' => 'Details',
		);
		if ($this->user_id) unset($columns['user_id']);
		return $columns;
	}

	public function get_sortable_columns() {
		return array(
				'user_id' => array('user_id', true),
				'subscription_status' => array('subscription_status', false),
				'subscription_type' => array('subscription_type', false),
				'total_revenue' => array('total_revenue', false),
				'total_credits' => array('total_credits', false),
				'total_debits' => array('total_debits', false),
				'months_since_join' => array('months_since_join', false),
				'box_balance' => array('box_balance', false),
				'boxes_behind' => array('boxes_behind', false),
				'rev_per_box' => array('rev_per_box', false),
				'total_credits_alt' => array('total_credits_alt', false),
				'total_debits_alt' => array('total_debits_alt', false),
				'box_balance_alt' => array('box_balance_alt', false),
				'boxes_behind_alt' => array('boxes_behind_alt', false),
				'rev_per_box_alt' => array('rev_per_box_alt', false),
				'mismatch' => array('mismatch', false),
				);
		if ($this->user_id) unset($columns['user_id']);
		return $columns;
	}

	public function column_detail($item) {
		$user_id = intval($item['user_id']);
		$detail_url = admin_url("admin.php?page=cb-ledger&user_id=$user_id");
		return "<a href=\"$detail_url\">[&hellip;]</a>";
	}

	private function table_data() {
		$ledger = new CBLedger();

		$limit        = 20;
		$current_page = $this->get_pagenum();
		$total_items  = $ledger->get_summary_count();
		$offset       = ($current_page-1) * $limit;

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $limit
		));

		if ($this->user_id) {
			return $ledger->get_summary_for_user($this->user_id, $limit, $offset);
		} else {
			$sortable = $this->get_sortable_columns();
			$orderby = 'user_id';
			$order = 'asc';
			if(!empty($_GET['orderby']) && isset($sortable[$_GET['orderby']])) {
				$orderby = $_GET['orderby'];
			}
			if(!empty($_GET['order']) && ($_GET['order'] == 'asc' || $_GET['order'] == 'desc')) {
				$order = $_GET['order'];
			}
			return $ledger->get_summary($limit, $offset, $orderby, $order);
		}
	}
}

