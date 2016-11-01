BEGIN;

DROP VIEW IF EXISTS monthly_analytics_boxes_shipped CASCADE;
CREATE VIEW monthly_analytics_boxes_shipped AS
	SELECT
		  to_char(completed_date, 'YYYY-MM') AS calendar_month
		, sum(CASE WHEN status IN ('completed', 'refunded') THEN 1 ELSE 0 end) AS boxes_shipped
	FROM
		box_orders
	GROUP BY
		to_char(completed_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

DROP VIEW IF EXISTS monthly_analytics_boxes_created CASCADE;
CREATE VIEW monthly_analytics_boxes_created AS
	SELECT
		  to_char(created_date, 'YYYY-MM') AS calendar_month
		, count(id) AS boxes_created
	FROM
		box_orders
	GROUP BY
		to_char(created_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

DROP VIEW IF EXISTS monthly_analytics_shop_orders_created CASCADE;
CREATE VIEW monthly_analytics_shop_orders_created AS
	SELECT
		  to_char(created_date, 'YYYY-MM') AS calendar_month
		, count(id) AS shop_orders_created
	FROM
		shop_orders
	GROUP BY
		to_char(created_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

DROP VIEW IF EXISTS monthly_analytics_shop_orders_shipped CASCADE;
CREATE VIEW monthly_analytics_shop_orders_shipped AS
	SELECT
		  to_char(completed_date, 'YYYY-MM') AS calendar_month
		, sum(CASE WHEN status IN ('completed', 'refunded') THEN 1 ELSE 0 end) AS shop_orders_shipped
	FROM
		shop_orders
	GROUP BY
		to_char(completed_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

DROP VIEW IF EXISTS monthly_analytics_stripe_charges CASCADE;
CREATE VIEW monthly_analytics_stripe_charges AS
	SELECT
		  to_char(charge_date, 'YYYY-MM') AS calendar_month
		, sum(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 end) AS charges_succeeded
		, sum(CASE WHEN status = 'failed' THEN 1 ELSE 0 end) AS charges_failed
		, sum(CASE WHEN status = 'succeeded' THEN amount ELSE 0 end) AS total_amount_charged
		, sum(CASE WHEN status = 'succeeded' THEN stripe_fee ELSE 0 end) AS total_stripe_fee
	FROM
		charges
	GROUP BY
		to_char(charge_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

DROP VIEW IF EXISTS monthly_analytics_stripe_refunds CASCADE;
CREATE VIEW monthly_analytics_stripe_refunds AS
	SELECT
		  to_char(refund_date, 'YYYY-MM') AS calendar_month
		, sum(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 end) AS refunds_succeeded
		, sum(CASE WHEN status = 'failed' THEN 1 ELSE 0 end) AS refunds_failed
		, sum(CASE WHEN status = 'succeeded' AND amount > 0 THEN amount ELSE 0 end) AS total_amount_refunded
		, sum(CASE WHEN status = 'succeeded' THEN stripe_fee_refunded ELSE 0 end) AS total_stripe_fee_refunded
	FROM
		refunds
	GROUP BY
		to_char(refund_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

DROP VIEW IF EXISTS monthly_analytics_user_actions CASCADE;
CREATE VIEW monthly_analytics_user_actions AS
	SELECT
		  to_char(event_date, 'YYYY-MM') AS calendar_month
		, sum(CASE WHEN event = 'user-cancelled' THEN 1 ELSE 0 end) AS user_cancelled
		, sum(CASE WHEN event = 'user-hold' THEN 1 ELSE 0 end) AS user_hold
		, sum(CASE WHEN event = 'user-reactivated' THEN 1 ELSE 0 end) AS user_reactivated
	FROM
		subscription_events
	GROUP BY
		to_char(event_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

DROP VIEW IF EXISTS monthly_analytics_subscription_starts CASCADE;
CREATE VIEW monthly_analytics_subscription_starts AS
	SELECT
		  to_char(start_date, 'YYYY-MM') AS calendar_month
		, count(id) AS new_subscriptions
	FROM
		subscriptions
	GROUP BY
		to_char(start_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

DROP TABLE IF EXISTS monthly_analytics_sub_churn CASCADE;
CREATE TABLE monthly_analytics_sub_churn AS
	SELECT
		  calendar_month
		, reactivated as subs_reactivated
		, activated as subs_activated
		, active as subs_active
		, churned AS subs_churned
		, churn_pct AS subs_churn_pct
	FROM
		subscription_churn_by_calendar_month
;

DROP TABLE IF EXISTS monthly_analytics_box_churn CASCADE;
CREATE TABLE monthly_analytics_box_churn AS
	SELECT
		  to_char(to_date(sku_month, 'bYYMM'), 'YYYY-MM') AS calendar_month
		, box_count
		, booked_revenue
		, booked_revenue_per_box
		, reactivated as box_reactivated
		, activated as box_activated
		, active as box_active
		, churned as box_churned
		, churn_pct as box_churn_pct
		, reactivated2 as box_reactivated2
		, activated2 as box_activated2
		, active2 as box_active2
		, churned2 as box_churned2
		, churn_pct2 as box_churn_pct2
	FROM
		box_churn_by_sku_month
;

DROP TABLE IF EXISTS monthly_analytics CASCADE;
CREATE TABLE monthly_analytics AS
	SELECT
		  calendar_month
		, boxes_created
		, boxes_shipped
		, shop_orders_created
		, shop_orders_shipped
		, charges_succeeded
		, charges_failed
		, total_amount_charged
		, total_stripe_fee
		, refunds_succeeded
		, refunds_failed
		, total_amount_refunded
		, total_stripe_fee_refunded
		, user_cancelled
		, user_hold
		, user_reactivated
		, new_subscriptions
		, coalesce(total_amount_charged,0) - coalesce(total_stripe_fee,0) - coalesce(total_amount_refunded,0) + coalesce(total_stripe_fee_refunded,0) AS net_revenue
		, subs_reactivated
		, subs_activated
		, subs_active
		, subs_churned
		, subs_churn_pct
		, box_count
		, booked_revenue
		, booked_revenue_per_box
		, box_reactivated
		, box_activated
		, box_active
		, box_churned
		, box_churn_pct
		, box_reactivated2
		, box_activated2
		, box_active2
		, box_churned2
		, box_churn_pct2
	FROM
		monthly_analytics_boxes_created
			NATURAL FULL JOIN monthly_analytics_boxes_shipped
			NATURAL FULL JOIN monthly_analytics_shop_orders_created
			NATURAL FULL JOIN monthly_analytics_shop_orders_shipped
			NATURAL FULL JOIN monthly_analytics_stripe_charges 
			NATURAL FULL JOIN monthly_analytics_stripe_refunds
			NATURAL FULL JOIN monthly_analytics_subscription_starts
			NATURAL FULL JOIN monthly_analytics_user_actions
			NATURAL FULL JOIN monthly_analytics_sub_churn
			NATURAL FULL JOIN monthly_analytics_box_churn
	ORDER BY
		calendar_month
;

COMMIT;
