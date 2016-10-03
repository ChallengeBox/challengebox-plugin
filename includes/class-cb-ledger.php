<?php

/**
 * Routines for generating a box credit ledger from redshift daa.
 */

class CBLedger {

	public function __construct() {
		$this->db = pg_connect(file_get_contents('/home/www-data/.aws/redshift.string'));
		if (!$this->db) throw new Exception(pg_last_error());
	}

	public function get_ledger($limit = false, $offset = false, $sort_column = false, $sort_direction = false) {
		$query = $this->get_credit_ledger_sql();
		if (is_numeric($limit)) $query .= "\nLIMIT $limit";
		if (is_numeric($offset)) $query .= "\nOFFSET $offset";
		$result = pg_query($query);
		if (!$result) throw new Exception(pg_last_error());
		return pg_fetch_all($result);
	}

	public function get_ledger_count() {
		$result = pg_query($this->credit_ledger_count);
		if (!$result) throw new Exception(pg_last_error());
		return pg_fetch_all($result)[0]['n'];
	}

	public function get_ledger_for_user($user_id) {
		global $wpdb;
		$prepared = $wpdb->prepare(str_replace('-- WHERE', "WHERE\n\tuser_id = %d", $this->get_credit_ledger_sql()), $user_id);
		$result = pg_query($prepared);
		if (!$result) throw new Exception(pg_last_error());
		return pg_fetch_all($result);
	}

	public function get_ledger_count_for_user($user_id) {
		global $wpdb;
		$prepared = str_replace('-- WHERE', "WHERE\n\tid = $user_id", $this->credit_ledger_count);
		$result = pg_query($prepared);
		if (!$result) throw new Exception(pg_last_error());
		return pg_fetch_all($result)[0]['n'];
	}


	public function get_summary($limit=false, $offset=false, $orderby='user_id', $order='asc') {
		$query = $this->get_credit_summary_sql($orderby, $order);
		if (is_numeric($limit)) $query .= "\nLIMIT $limit";
		if (is_numeric($offset)) $query .= "\nOFFSET $offset";
		$result = pg_query($query);
		if (!$result) throw new Exception(pg_last_error());
		return pg_fetch_all($result);
	}

	public function get_summary_count() {
		$result = pg_query($this->summary_count);
		if (!$result) throw new Exception(pg_last_error());
		return pg_fetch_all($result)[0]['n'];
	}

	public function get_summary_for_user($user_id) {
		global $wpdb;
		$prepared = $wpdb->prepare(str_replace('-- AND', "AND\n\tuser_id = %d", $this->get_credit_summary_sql()), $user_id);
		$result = pg_query($prepared);
		if (!$result) throw new Exception(pg_last_error());
		return pg_fetch_all($result);
	}

	private $db;

	private $box_orders_base = <<<SQL
SELECT
		  box_orders.id::varchar(32)
		, user_id
		, 'box' AS kind
		, status
		, created_date
		, sku
		, 0 AS box_credits
		, CASE WHEN status IN ('completed', 'refunded', 'processing') THEN 1 ELSE 0 END AS box_debits
		, total
		, 0 as revenue
		, registration_date
FROM
		box_orders JOIN users ON box_orders.user_id = users.id
SQL;

	private $renewal_orders_base = <<<SQL
SELECT
		  renewal_orders.id::varchar(32)
		, user_id
		, 'renewal' AS kind
		, status
		, created_date
		, sku
		, CASE
				WHEN status IN ('completed', 'refunded') THEN (CASE 
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
			END::INTEGER AS box_credits
		, 0 AS box_debits
		, total
		, 0 as revenue
		, registration_date
FROM
		renewal_orders JOIN users ON renewal_orders.user_id = users.id
SQL;

	private $payment_base = <<<SQL
SELECT
		  charges.id
		, user_id
		, 'payment' AS kind
		, status
		, charge_date AS created_date
		, NULL AS sku
		, 0 AS box_credits
		, 0 AS box_debits
		, amount AS total
		, CASE WHEN status = 'succeeded' THEN amount ELSE 0 END AS revenue
		, registration_date
FROM
		charges JOIN users ON charges.user_id = users.id
SQL;

	private $refund_base = <<<SQL
SELECT
		  refunds.id
		, user_id
		, 'refund' AS kind
		, status
		, refund_date AS created_date
		, NULL AS sku
		, 0 AS box_credits
		, 0 AS box_debits
		, -amount AS total
		, CASE WHEN status = 'succeeded' THEN -amount ELSE 0 END AS revenue
		, registration_date
FROM
		refunds JOIN users ON refunds.user_id = users.id
SQL;

	private $credit_ledger_count = <<<SQL
		SELECT sum(n) as n FROM
		( 
			SELECT count(id) as n FROM box_orders
			-- WHERE
				UNION
			SELECT count(id) as n FROM renewal_orders
			-- WHERE
				UNION
			SELECT count(id) as n FROM charges
			-- WHERE
				UNION
			SELECT count(id) as n FROM refunds
			-- WHERE
		)
SQL;

	private $credit_ledger_base = <<<SQL
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
		, box_debits
		, sum(box_credits) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_credits
		, sum(box_debits) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_debits
		, sum(revenue) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_revenue
FROM
		(SELECT * FROM box_orders_base UNION SELECT * FROM renewal_orders_base UNION SELECT * FROM refund_base UNION SELECT * FROM payment_base)
ORDER BY
		user_id, created_date, kind DESC
SQL;

	public function get_credit_ledger_sql() {
		return <<<SQL
WITH 
	renewal_orders_base AS ($this->renewal_orders_base), 
	box_orders_base AS ($this->box_orders_base), 
	payment_base AS ($this->payment_base), 
	refund_base AS ($this->refund_base), 
	credit_ledger_base AS ($this->credit_ledger_base)
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
		, total_credits
		, box_debits
		, total_debits
		, months_since_join
		, total_credits - total_debits AS box_balance
		, months_since_join - total_debits AS boxes_behind
		, CASE WHEN total_credits > 0 THEN (total_revenue / total_credits)::DECIMAL(10,2) ELSE 0 END as rev_per_box
FROM
	credit_ledger_base
-- WHERE
ORDER BY
		user_id, created_date, kind DESC
SQL;
	}

	private $summary_count = <<<SQL
		SELECT count(id) as n FROM users
SQL;

	public function get_credit_summary_sql($orderby='user_id',$order='asc') {
		return <<<SQL
WITH 
	renewal_orders_base AS ($this->renewal_orders_base), 
	box_orders_base AS ($this->box_orders_base), 
	payment_base AS ($this->payment_base), 
	refund_base AS ($this->refund_base), 
	credit_ledger_base AS ($this->credit_ledger_base)
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
FROM
	credit_ledger_base
JOIN
	users
ON
	credit_ledger_base.user_id = users.id
WHERE
	next_user_id is NULL
-- AND
ORDER BY $orderby $order
SQL;
	}

}
