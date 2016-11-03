BEGIN;

DROP VIEW IF EXISTS refund_totals_by_charge CASCADE;
CREATE VIEW refund_totals_by_charge AS
	SELECT
		  charge_id
		, count(id) AS stripe_refund_count
		, - sum(amount) AS stripe_refund_gross
		, sum(stripe_fee_refunded) AS stripe_refund_fees
		, sum(stripe_fee_refunded - amount) AS stripe_refund_net
	FROM
		refunds
	WHERE
		status = 'succeeded'
	GROUP BY
		charge_id
	ORDER BY
		charge_id
;

DROP VIEW IF EXISTS charge_totals_by_order CASCADE;
CREATE VIEW charge_totals_by_order AS
	SELECT
		  order_id
		, count(id) AS stripe_charge_count
		, sum(amount) AS stripe_charge_gross
		, -sum(stripe_fee) AS stripe_charge_fees
		, sum(amount - stripe_fee) AS stripe_charge_net
		, sum(coalesce(stripe_refund_count,0)) AS stripe_refund_count
		, sum(coalesce(stripe_refund_gross,0)) AS stripe_refund_gross
		, sum(coalesce(stripe_refund_fees,0)) AS stripe_refund_fees
		, sum(coalesce(stripe_refund_net,0)) AS stripe_refund_net
		, sum(coalesce(stripe_refund_fees,0) - stripe_fee) AS stripe_fees_net
		, sum(amount - stripe_fee + coalesce(stripe_refund_net,0)) AS stripe_net
	FROM
		charges
	FULL JOIN
		refund_totals_by_charge
	ON
		charges.id = refund_totals_by_charge.charge_id
	WHERE
		status = 'succeeded'
	GROUP BY
		order_id
	ORDER BY
		order_id
;

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
		, 0 AS renewal_revenue
		, 0 AS price_items
		, 0 AS price_ship
		, 0 AS price_rush
		, 0 AS price
		, 0 AS stripe_charge_count
		, 0 AS stripe_charge_gross
		, 0 AS stripe_charge_fees
		, 0 AS stripe_charge_net
		, 0 AS stripe_refund_count
		, 0 AS stripe_refund_gross
		, 0 AS stripe_refund_fees
		, 0 AS stripe_refund_net
		, 0 AS stripe_fees_net
		, 0 AS stripe_net
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
--		, CASE WHEN status IN ('completed', 'refunded', 'processing') THEN total ELSE 0 END AS renewal_revenue
		, coalesce(stripe_net, 0) AS renewal_revenue
		, revenue_items AS price_items
		, revenue_ship AS price_ship
		, revenue_rush AS price_rush
		, coalesce(revenue_items,0) + coalesce(revenue_ship,0) + coalesce(revenue_rush,0) AS price
		, stripe_charge_count
		, stripe_charge_gross
		, stripe_charge_fees
		, stripe_charge_net
		, stripe_refund_count
		, stripe_refund_gross
		, stripe_refund_fees
		, stripe_refund_net
		, stripe_fees_net
		, stripe_net
		, registration_date
	FROM
		renewal_orders
	JOIN
		users
	ON
		renewal_orders.user_id = users.id
	FULL JOIN
		charge_totals_by_order
	ON
		charge_totals_by_order.order_id = renewal_orders.id
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
		, 0 AS renewal_revenue
		, 0 AS price_items
		, 0 AS price_ship
		, 0 AS price_rush
		, 0 AS price
		, 0 AS stripe_charge_count
		, 0 AS stripe_charge_gross
		, 0 AS stripe_charge_fees
		, 0 AS stripe_charge_net
		, 0 AS stripe_refund_count
		, 0 AS stripe_refund_gross
		, 0 AS stripe_refund_fees
		, 0 AS stripe_refund_net
		, 0 AS stripe_fees_net
		, 0 AS stripe_net
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
		, 0 AS renewal_revenue
		, 0 AS price_items
		, 0 AS price_ship
		, 0 AS price_rush
		, 0 AS price
		, 0 AS stripe_charge_count
		, 0 AS stripe_charge_gross
		, 0 AS stripe_charge_fees
		, 0 AS stripe_charge_net
		, 0 AS stripe_refund_count
		, 0 AS stripe_refund_gross
		, 0 AS stripe_refund_fees
		, 0 AS stripe_refund_net
		, 0 AS stripe_fees_net
		, 0 AS stripe_net
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

DROP VIEW IF EXISTS box_credit_ledger_stage1 CASCADE;
CREATE VIEW box_credit_ledger_stage1 AS
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
		, renewal_revenue
		, box_credits
		, box_credits_alt
		, box_debits
		, box_debits_alt
		, sum(box_credits) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_credits
		, sum(box_debits) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_debits
		, sum(box_credits_alt) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_credits_alt
		, sum(box_debits_alt) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_debits_alt
		, sum(revenue) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_revenue
		, sum(renewal_revenue) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_renewal_revenue
		
		, max(renewal_revenue) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_renewal_revenue

		, max(price_items) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_price_items
		, max(price_ship) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_price_ship
		, max(price_rush) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_price_rush
		, max(price) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_price

		, max(stripe_charge_count) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_charge_count
		, max(stripe_charge_gross) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_charge_gross
		, max(stripe_charge_fees) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_charge_fees
		, max(stripe_charge_net) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_charge_net

		, max(stripe_refund_count) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_refund_count
		, max(stripe_refund_gross) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_refund_gross
		, max(stripe_refund_fees) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_refund_fees
		, max(stripe_refund_net) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_refund_net

		, max(stripe_fees_net) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_fees_net
		, max(stripe_net) IGNORE NULLS OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS last_stripe_net

		, sum(CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN 1 ELSE 0 end) OVER (PARTITION by user_id ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total_boxes

FROM
		(SELECT * FROM box_credit_box_base 
			UNION SELECT * FROM box_credit_renewal_base
			UNION SELECT * FROM box_credit_refund_base
			UNION SELECT * FROM box_credit_payment_base)
ORDER BY
		user_id, created_date, kind DESC
;

DROP VIEW IF EXISTS box_credit_ledger_stage2 CASCADE;
CREATE VIEW box_credit_ledger_stage2 AS
	SELECT
		  id
		, months_since_join
		, user_id
		, next_user_id
		, kind
		, status
		, created_date
		, sku
		, total
		, revenue
		, renewal_revenue
		, box_credits
		, box_credits_alt
		, box_debits
		, box_debits_alt
		, total_credits
		, total_debits
		, total_credits_alt
		, total_debits_alt
		, total_revenue
		, total_renewal_revenue
		, last_renewal_revenue
		, last_price_items
		, last_price_ship
		, last_price_rush
		, last_price
		, last_stripe_charge_count
		, last_stripe_charge_gross
		, last_stripe_charge_fees
		, last_stripe_charge_net
		, last_stripe_refund_count
		, last_stripe_refund_gross
		, last_stripe_refund_fees
		, last_stripe_refund_net
		, last_stripe_fees_net
		, last_stripe_net
		, total_boxes
		, sum(CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN 1 ELSE 0 end) OVER (PARTITION by user_id, total_renewal_revenue ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS boxes_since_last_renewal
FROM
		box_credit_ledger_stage1
ORDER BY
		user_id, created_date, kind DESC
;

DROP VIEW IF EXISTS box_credit_ledger_stage3 CASCADE;
CREATE VIEW box_credit_ledger_stage3 AS
	SELECT
		  id
		, months_since_join
		, user_id
		, next_user_id
		, kind
		, status
		, created_date
		, sku
		, total
		, revenue
		, renewal_revenue
		, box_credits
		, box_credits_alt
		, box_debits
		, box_debits_alt
		, total_credits
		, total_debits
		, total_credits_alt
		, total_debits_alt
		, total_revenue
		, total_renewal_revenue
		, last_renewal_revenue
		, last_price_items
		, last_price_ship
		, last_price_rush
		, last_price
		, last_stripe_charge_count
		, last_stripe_charge_gross
		, last_stripe_charge_fees
		, last_stripe_charge_net
		, last_stripe_refund_count
		, last_stripe_refund_gross
		, last_stripe_refund_fees
		, last_stripe_refund_net
		, last_stripe_fees_net
		, last_stripe_net
		, total_boxes
		, boxes_since_last_renewal
		, max(boxes_since_last_renewal) OVER (PARTITION by user_id, total_renewal_revenue ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) AS max_boxes_since_last_renewal
		, max(box_credits) OVER (PARTITION by user_id, total_renewal_revenue ORDER BY user_id, created_date, kind DESC ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) AS box_credits_since_last_renewal
FROM
		box_credit_ledger_stage2
ORDER BY
		user_id, created_date, kind DESC
;

DROP VIEW IF EXISTS box_credit_ledger_base CASCADE;
CREATE VIEW box_credit_ledger_base AS
	SELECT
		  id
		, months_since_join
		, user_id
		, next_user_id
		, kind
		, status
		, created_date
		, sku
		, total
		, revenue
		, renewal_revenue
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_renewal_revenue / max_boxes_since_last_renewal ELSE 0 END AS todate_revenue
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_price_items / max_boxes_since_last_renewal ELSE 0 END AS todate_price_items
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_price_ship / max_boxes_since_last_renewal ELSE 0 END AS todate_price_ship
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_price_rush / max_boxes_since_last_renewal ELSE 0 END AS todate_price_rush
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_price / max_boxes_since_last_renewal ELSE 0 END AS todate_price
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_charge_count / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_charge_count
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_charge_gross / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_charge_gross
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_charge_fees / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_charge_fees
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_charge_net / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_charge_net
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_refund_count / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_refund_count
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_refund_gross / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_refund_gross
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_refund_fees / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_refund_fees
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_refund_net / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_refund_net
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_fees_net / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_fees_net
		, CASE WHEN kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_net / max_boxes_since_last_renewal ELSE 0 END AS todate_stripe_net
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_renewal_revenue / box_credits_since_last_renewal ELSE 0 END AS ideal_revenue
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_price_items / box_credits_since_last_renewal ELSE 0 END AS ideal_price_items
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_price_ship / box_credits_since_last_renewal ELSE 0 END AS ideal_price_ship
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_price_rush / box_credits_since_last_renewal ELSE 0 END AS ideal_price_rush
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_price / box_credits_since_last_renewal ELSE 0 END AS ideal_price
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_charge_count / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_charge_count
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_charge_gross / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_charge_gross
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_charge_fees / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_charge_fees
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_charge_net / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_charge_net
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_refund_count / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_refund_count
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_refund_gross / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_refund_gross
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_refund_fees / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_refund_fees
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_refund_net / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_refund_net
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_fees_net / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_fees_net
		, CASE WHEN box_credits_since_last_renewal > 0 AND kind = 'box' AND status IN ('completed', 'refunded', 'processing') THEN last_stripe_net / box_credits_since_last_renewal ELSE 0 END AS ideal_stripe_net
		, box_credits
		, box_credits_alt
		, box_debits
		, box_debits_alt
		, total_credits
		, total_debits
		, total_credits_alt
		, total_debits_alt
		, total_revenue
		, total_renewal_revenue
		, last_renewal_revenue
		, total_boxes
		, boxes_since_last_renewal
		, max_boxes_since_last_renewal
		, box_credits_since_last_renewal
FROM
		box_credit_ledger_stage3
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
		, renewal_revenue
		, least(todate_revenue, ideal_revenue) AS booked_revenue, ideal_revenue , todate_revenue
		, least(ideal_price_items, todate_price_items) AS booked_price_items, ideal_price_items, todate_price_items
		, least(ideal_price_ship, todate_price_ship) AS booked_price_ship, ideal_price_ship, todate_price_ship
		, least(ideal_price_rush, todate_price_rush) AS booked_price_rush, ideal_price_rush, todate_price_rush
		, least(ideal_price, todate_price) AS booked_price, ideal_price, todate_price
		, least(todate_stripe_charge_count, ideal_stripe_charge_count) AS booked_stripe_charge_count, todate_stripe_charge_count, ideal_stripe_charge_count
		, least(todate_stripe_charge_gross, ideal_stripe_charge_gross) AS booked_stripe_charge_gross, todate_stripe_charge_gross, ideal_stripe_charge_gross
		, least(ideal_stripe_charge_fees, todate_stripe_charge_fees) AS booked_stripe_charge_fees, ideal_stripe_charge_fees, todate_stripe_charge_fees
		, least(ideal_stripe_charge_net, todate_stripe_charge_net) AS booked_stripe_charge_net, ideal_stripe_charge_net, todate_stripe_charge_net
		, least(todate_stripe_refund_count, ideal_stripe_refund_count) AS booked_stripe_refund_count, todate_stripe_refund_count, ideal_stripe_refund_count
		, least(todate_stripe_refund_gross, ideal_stripe_refund_gross) AS booked_stripe_refund_gross, todate_stripe_refund_gross, ideal_stripe_refund_gross
		, least(todate_stripe_refund_fees, ideal_stripe_refund_fees) AS booked_stripe_refund_fees, todate_stripe_refund_fees, ideal_stripe_refund_fees
		, least(todate_stripe_refund_net, ideal_stripe_refund_net) AS booked_stripe_refund_net, todate_stripe_refund_net, ideal_stripe_refund_net
		, least(todate_stripe_fees_net, ideal_stripe_fees_net) AS booked_stripe_fees_net, todate_stripe_fees_net, ideal_stripe_fees_net
		, least(todate_stripe_net, ideal_stripe_net) AS booked_stripe_net, todate_stripe_net, ideal_stripe_net
		, total_revenue
		, total_renewal_revenue
		, last_renewal_revenue
		, total_boxes
		, boxes_since_last_renewal
		, max_boxes_since_last_renewal
		, box_credits_since_last_renewal
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
