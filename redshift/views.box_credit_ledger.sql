BEGIN;

DROP VIEW IF EXISTS box_credit_box_base CASCADE;
CREATE VIEW box_credit_box_base AS
	SELECT
		  box_orders.id::varchar(32)
		, user_id
		, 'box' AS kind
		, status
		, created_date
		, sku
		, 0 AS box_credits
		, CASE WHEN status IN ('completed', 'refunded', 'processing') THEN box_debits ELSE 0 END AS box_debits
		, 0 AS box_credits_alt
		, CASE WHEN status IN ('completed', 'refunded', 'processing') THEN 1 ELSE 0 END AS box_debits_alt
		, total
		, 0 AS revenue
		, registration_date
	FROM
		box_orders
	JOIN
		users
	ON
		box_orders.user_id = users.id
;

DROP VIEW IF EXISTS box_credit_renewal_base CASCADE;
CREATE VIEW box_credit_renewal_base AS
	SELECT
		  renewal_orders.id::varchar(32)
		, user_id
		, 'renewal' AS kind
		, status
		, created_date
		, sku
		, CASE WHEN status IN ('completed', 'refunded', 'processing') THEN box_credits ELSE 0 END AS box_credits
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
		, 0 AS box_debits_alt
		, total
		, 0 AS revenue
		, registration_date
	FROM
		renewal_orders
	JOIN
		users
	ON
		renewal_orders.user_id = users.id
;

DROP VIEW IF EXISTS box_credit_payment_base CASCADE;
CREATE VIEW box_credit_payment_base AS
	SELECT
		  charges.id
		, user_id
		, 'payment' AS kind
		, status
		, charge_date AS created_date
		, NULL AS sku
		, 0 AS box_credits
		, 0 AS box_debits
		, 0 AS box_credits_alt
		, 0 AS box_debits_alt
		, amount AS total
		, CASE WHEN status = 'succeeded' THEN amount ELSE 0 END AS revenue
		, registration_date
	FROM
		charges
	JOIN
		users
	ON
		charges.user_id = users.id
;

DROP VIEW IF EXISTS box_credit_refund_base CASCADE;
CREATE VIEW box_credit_refund_base AS
	SELECT
		  refunds.id
		, user_id
		, 'refund' AS kind
		, status
		, refund_date AS created_date
		, NULL AS sku
		, 0 AS box_credits
		, 0 AS box_debits
		, 0 AS box_credits_alt
		, 0 AS box_debits_alt
		, -amount AS total
		, CASE WHEN status = 'succeeded' THEN -amount ELSE 0 END AS revenue
		, registration_date
	FROM
		refunds
	JOIN
		users
	ON
		refunds.user_id = users.id
;

DROP VIEW IF EXISTS box_credit_joint_ids CASCADE;
CREATE VIEW box_credit_joint_ids AS
	SELECT id::VARCHAR(32), user_id FROM box_orders
		UNION
	SELECT id::VARCHAR(32), user_id FROM renewal_orders
		UNION
	SELECT id, user_id FROM charges
		UNION
	SELECT id, user_id FROM refunds
;

DROP VIEW IF EXISTS box_credit_ledger_base CASCADE;
CREATE VIEW box_credit_ledger_base AS
	SELECT
		  id
		, datediff('months', registration_date, created_date) AS months_since_join
		, user_id
		, lead(user_id, 1) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC) AS next_user_id
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
		(SELECT * FROM box_credit_box_base 
			UNION SELECT * FROM box_credit_renewal_base
			UNION SELECT * FROM box_credit_refund_base
			UNION SELECT * FROM box_credit_payment_base)
ORDER BY
		user_id, created_date, kind DESC
;

DROP VIEW IF EXISTS box_credit_ledger CASCADE;
CREATE VIEW box_credit_ledger AS
	SELECT 
		  id
		, user_id
		, to_char(convert_timezone('est', created_date), 'YYYY-MM-DD hh:MM pm') AS event_date
		, kind AS event_type
		, status
		, sku
		, total AS amt
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
		, CASE WHEN total_credits > 0 THEN (total_revenue / total_credits)::DECIMAL(10,2) ELSE 0 END AS rev_per_box
		, CASE WHEN total_credits_alt > 0 THEN (total_revenue / total_credits_alt)::DECIMAL(10,2) ELSE 0 END AS rev_per_box_alt
		, user_id AS detail
	FROM
		box_credit_ledger_base
	ORDER BY
		user_id, created_date, kind DESC
;

DROP VIEW IF EXISTS box_credit_summary CASCADE;
CREATE VIEW box_credit_summary AS
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
		, CASE WHEN total_credits > 0 THEN (total_revenue / total_credits)::DECIMAL(10,2) ELSE 0 END AS rev_per_box
		, total_credits_alt
		, total_debits_alt
		, total_credits_alt - total_debits_alt AS box_balance_alt
		, months_since_join - total_debits_alt AS boxes_behind_alt
		, CASE WHEN total_credits_alt > 0 THEN (total_revenue / total_credits_alt)::DECIMAL(10,2) ELSE 0 END AS rev_per_box_alt
		, user_id AS detail
		, CASE WHEN total_credits <> total_credits_alt OR total_debits <> total_debits_alt THEN 1 ELSE 0 END AS mismatch
	FROM
		box_credit_ledger_base
	JOIN
		users
	ON
		box_credit_ledger_base.user_id = users.id
	WHERE
		next_user_id IS NULL
;

COMMIT;