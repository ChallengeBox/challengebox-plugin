<?php

/**
 * Routines for generating a box credit ledger from redshift daa.
 */

class CBLedger extends CBRedshift {

	public function get_ledger($limit=false, $offset=false) {
		$query = "SELECT * FROM box_credit_ledger";
		if (is_numeric($limit)) $query .= "\nLIMIT $limit";
		if (is_numeric($offset)) $query .= "\nOFFSET $offset";
		return $this->execute_query($query);
	}

	public function get_ledger_count() {
		return $this->execute_query("SELECT count(id) as n FROM box_credit_joint_ids")[0]['n'];
	}

	public function get_ledger_for_user($user_id) {
		global $wpdb;
		$query = $wpdb->prepare("SELECT * FROM box_credit_ledger WHERE user_id = %d", $user_id);
		return $this->execute_query($query);
	}

	public function get_ledger_count_for_user($user_id) {
		global $wpdb;
		$query = $wpdb->prepare("SELECT count(id) as n FROM box_credit_joint_ids WHERE user_id = %d", $user_id);
		return $this->execute_query($query)[0]['n'];
	}


	public function get_summary($limit=false, $offset=false, $orderby='user_id', $order='asc', $sortable=null) {
		if (!isset($sortable[$orderby])) {
			throw new Exception("$orderby must be one of ". implode(', ', array_keys($sortable)));
		}
		$order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
		$query = "SELECT * FROM box_credit_summary ORDER BY $orderby $order";
		if (is_numeric($limit)) $query .= "  LIMIT $limit";
		if (is_numeric($offset)) $query .= "  OFFSET $offset";
		return $this->execute_query($query);
	}

	public function get_summary_count() {
		$query = "SELECT count(id) as n FROM users";
		return $this->execute_query($query)[0]['n'];
	}

	public function get_summary_for_user($user_id) {
		$query = $wpdb->prepare("SELECT * FROM box_credit_summary WHERE user_id = %d", $user_id);
		return $this->execute_query($query);
	}
}
