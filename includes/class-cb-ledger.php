<?php

/**
 * Routines for generating a box credit ledger from redshift daa.
 */

class CBLedger extends CBRedshift {

	public function get_ledger($limit = false, $offset = false, $sort_column = false, $sort_direction = false) {
		$query = $this->sql_credit_ledger();
		if (is_numeric($limit)) $query .= "\nLIMIT $limit";
		if (is_numeric($offset)) $query .= "\nOFFSET $offset";
		return $this->execute_query($query);
	}

	public function get_ledger_count() {
		$query = $this->sql_credit_ledger_count();
		return $this->execute_query($query)[0]['n'];
	}

	public function get_ledger_for_user($user_id) {
		global $wpdb;
		$query = $wpdb->prepare(str_replace('-- WHERE', "WHERE\n\tuser_id = %d", $this->sql_credit_ledger()), $user_id);
		return $this->execute_query($query);
	}

	public function get_ledger_count_for_user($user_id) {
		global $wpdb;
		$query = str_replace('-- WHERE', "WHERE\n\tid = $user_id", $this->sql_credit_ledger_count());
		return $this->execute_query($query)[0]['n'];
	}


	public function get_summary($limit=false, $offset=false, $orderby='user_id', $order='asc') {
		$query = $this->sql_credit_summary($orderby, $order);
		if (is_numeric($limit)) $query .= "\nLIMIT $limit";
		if (is_numeric($offset)) $query .= "\nOFFSET $offset";
		return $this->execute_query($query);
	}

	public function get_summary_count() {
		$query = $this->sql_summary_count();
		return $this->execute_query($query)[0]['n'];
	}

	public function get_summary_for_user($user_id) {
		global $wpdb;
		$query = $wpdb->prepare(str_replace('-- AND', "AND\n\tuser_id = %d", $this->sql_credit_summary()), $user_id);
		return $this->execute_query($query);
	}

	private function sql_box_base() {
		return <<<SQL
SELECT
		  box_orders.id::varchar(32)
		, user_id
		, 'box' AS kind
		, status
		, created_date
		, sku
		, 0 AS box_credits
		, CASE WHEN status IN ('completed', 'refunded', 'processing') THEN box_debits ELSE 0 END AS box_debits
		, 0 as box_credits_alt
		, CASE WHEN status IN ('completed', 'refunded', 'processing') THEN 1 ELSE 0 END AS box_debits_alt
		, total
		, 0 as revenue
		, registration_date
FROM
		$this->schema.box_orders
JOIN
		$this->schema.users
ON
		$this->schema.box_orders.user_id = $this->schema.users.id
SQL;
	}

	private function sql_renewal_base() {
		return <<<SQL
SELECT
		  renewal_orders.id::varchar(32)
		, user_id
		, 'renewal' AS kind
		, status
		, created_date
		, sku
		, CASE WHEN status IN ('completed', 'refunded', 'processing') THEN box_credits else 0 END as box_credits
		, 0 AS box_debits
		, CASE
				WHEN status IN ('completed', 'refunded', 'processing') THEN (CASE 
						WHEN sku = '#livefit' THEN (CASE
								WHEN box_credits > 0 THEN box_credits
								ELSE (CASE
												WHEN total > 0 THEN (CASE WHEN round(total/30.0) > 0 THEN round(total/30.0) ELSE 1 END)
												ELSE 0
										END)
								END)
						WHEN sku = 'subscription_monthly' THEN 1
						WHEN sku = 'subscription_3month' THEN 3
						WHEN sku = 'subscription_12month' THEN 12
						WHEN sku = 'subscription_monthly-v2' THEN 1
						WHEN sku = 'subscription_3month-v2' THEN 3
						WHEN sku = 'subscription_12month-v2' THEN 12
						WHEN sku = 'subscription_single_box' THEN 1
						WHEN sku = 'sbox' THEN 1
						WHEN substring(sku, 1, 5) = 'sbox_' THEN 1
						WHEN substring(sku, 1, 3) = 'cb_' THEN (CASE
								WHEN substring(sku, length(sku)-2, 3) = '_3m' THEN 1 
								WHEN substring(sku, length(sku)-3, 4) = '_12m' THEN 1
								ELSE 1 
						END)
						ELSE 0
				END)
				ELSE 0
			END::INTEGER AS box_credits_alt
		, 0 as box_debits_alt
		, total
		, 0 as revenue
		, registration_date
FROM
		$this->schema.renewal_orders
JOIN
		$this->schema.users
ON
		$this->schema.renewal_orders.user_id = $this->schema.users.id
SQL;
	}

	private function sql_payment_base() {
		return <<<SQL
SELECT
		  charges.id
		, user_id
		, 'payment' AS kind
		, status
		, charge_date AS created_date
		, NULL AS sku
		, 0 AS box_credits
		, 0 AS box_debits
		, 0 as box_credits_alt
		, 0 as box_debits_alt
		, amount AS total
		, CASE WHEN status = 'succeeded' THEN amount ELSE 0 END AS revenue
		, registration_date
FROM
		$this->schema.charges
JOIN
		$this->schema.users
ON
		$this->schema.charges.user_id = $this->schema.users.id
SQL;
	}

	private function sql_refund_base() {
		return <<<SQL
SELECT
		  refunds.id
		, user_id
		, 'refund' AS kind
		, status
		, refund_date AS created_date
		, NULL AS sku
		, 0 AS box_credits
		, 0 AS box_debits
		, 0 as box_credits_alt
		, 0 as box_debits_alt
		, -amount AS total
		, CASE WHEN status = 'succeeded' THEN -amount ELSE 0 END AS revenue
		, registration_date
FROM
		$this->schema.refunds
JOIN
		$this->schema.users
ON
		$this->schema.refunds.user_id = $this->schema.users.id
SQL;
	}

	private function sql_credit_ledger_count() {
		return <<<SQL
		SELECT sum(n) as n FROM
		( 
			SELECT count(id) as n FROM $this->schema.box_orders
			-- WHERE
				UNION
			SELECT count(id) as n FROM $this->schema.renewal_orders
			-- WHERE
				UNION
			SELECT count(id) as n FROM $this->schema.charges
			-- WHERE
				UNION
			SELECT count(id) as n FROM $this->schema.refunds
			-- WHERE
		)
SQL;
	}

	private function sql_credit_ledger_base() {
		return <<<SQL
SELECT
		  id
		, datediff('months', registration_date, created_date) AS months_since_join
		, user_id
		, lead(user_id, 1) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC) as next_user_id
		, kind
		, status
		, created_date
		, sku
		, total
		, revenue
		, box_credits
		, box_credits_alt
		, box_debits
		, box_debits_alt
		, sum(box_credits) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_credits
		, sum(box_debits) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_debits
		, sum(box_credits_alt) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_credits_alt
		, sum(box_debits_alt) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_debits_alt
		, sum(revenue) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_revenue
FROM
		(SELECT * FROM box_orders_base UNION SELECT * FROM renewal_orders_base UNION SELECT * FROM refund_base UNION SELECT * FROM payment_base)
ORDER BY
		user_id, created_date, kind DESC
SQL;
	}

	public function sql_credit_ledger() {
		list(
			$renewals,
			$boxes,
			$payments,
			$refunds,
			$credit_ledger_base,
		) = array(
			$this->sql_renewal_base(),
			$this->sql_box_base(),
			$this->sql_payment_base(),
			$this->sql_refund_base(),
			$this->sql_credit_ledger_base(),
		);
			
		return <<<SQL
WITH 
	renewal_orders_base AS ($renewals), 
	box_orders_base AS ($boxes), 
	payment_base AS ($payments), 
	refund_base AS ($refunds), 
	credit_ledger_base AS ($credit_ledger_base)
SELECT 
		  id
		, user_id
		, to_char(convert_timezone('est', created_date), 'YYYY-MM-DD hh:MM pm') AS event_date
		, kind as event_type
		, status
		, sku
		, total as amt
		, revenue
		, total_revenue
		, box_credits
		, box_credits_alt
		, total_credits
		, total_credits_alt
		, box_debits
		, box_debits_alt
		, total_debits
		, total_debits_alt
		, months_since_join
		, total_credits - total_debits AS box_balance
		, total_credits_alt - total_debits_alt AS box_balance_alt
		, months_since_join - total_debits AS boxes_behind
		, months_since_join - total_debits_alt AS boxes_behind_alt
		, CASE WHEN total_credits > 0 THEN (total_revenue / total_credits)::DECIMAL(10,2) ELSE 0 END as rev_per_box
		, CASE WHEN total_credits_alt > 0 THEN (total_revenue / total_credits_alt)::DECIMAL(10,2) ELSE 0 END as rev_per_box_alt
		, user_id as detail
FROM
	credit_ledger_base
-- WHERE
ORDER BY
		user_id, created_date, kind DESC
SQL;
	}

	private function sql_summary_count() {
		return <<<SQL
		SELECT count(id) as n FROM $this->schema.users
SQL;
	}

	public function sql_credit_summary($orderby='user_id', $order='asc') {
		list(
			$renewals,
			$boxes,
			$payments,
			$refunds,
			$credit_ledger_base,
		) = array(
			$this->sql_renewal_base(),
			$this->sql_box_base(),
			$this->sql_payment_base(),
			$this->sql_refund_base(),
			$this->sql_credit_ledger_base(),
		);
			
		return <<<SQL
WITH 
	renewal_orders_base AS ($renewals), 
	box_orders_base AS ($boxes), 
	payment_base AS ($payments), 
	refund_base AS ($refunds), 
	credit_ledger_base AS ($credit_ledger_base)
SELECT 
		  user_id
		, subscription_status
		, subscription_type
		, total_revenue
		, total_credits
		, total_debits
		, months_since_join
		, total_credits - total_debits AS box_balance
		, months_since_join - total_debits AS boxes_behind
		, CASE WHEN total_credits > 0 THEN (total_revenue / total_credits)::DECIMAL(10,2) ELSE 0 END as rev_per_box
		, total_credits_alt
		, total_debits_alt
		, total_credits_alt - total_debits_alt AS box_balance_alt
		, months_since_join - total_debits_alt AS boxes_behind_alt
		, CASE WHEN total_credits_alt > 0 THEN (total_revenue / total_credits_alt)::DECIMAL(10,2) ELSE 0 END as rev_per_box_alt
		, user_id as detail
		, CASE WHEN total_credits <> total_credits_alt OR total_debits <> total_debits_alt THEN 1 ELSE 0 END as mismatch
FROM
	credit_ledger_base
JOIN
	$this->schema.users
ON
	credit_ledger_base.user_id = $this->schema.users.id
WHERE
	next_user_id is NULL
-- AND
ORDER BY $orderby $order
SQL;
	}

}
