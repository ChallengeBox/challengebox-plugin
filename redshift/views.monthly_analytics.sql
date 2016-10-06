
CREATE OR REPLACE VIEW monthly_analytics_boxes_shipped AS
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

CREATE OR REPLACE VIEW monthly_analytics_boxes_created AS
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

CREATE OR REPLACE VIEW monthly_analytics_shop_orders_created AS
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

CREATE OR REPLACE VIEW monthly_analytics_shop_orders_shipped AS
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

CREATE OR REPLACE VIEW monthly_analytics_stripe_charges AS
	SELECT
		  to_char(charge_date, 'YYYY-MM') AS calendar_month
		, sum(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 end) AS charges_succeeded
		, sum(CASE WHEN status = 'failed' THEN 1 ELSE 0 end) AS charges_failed
		, sum(amount) AS total_amount_charged
	FROM
		charges
	GROUP BY
		to_char(charge_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

CREATE OR REPLACE VIEW monthly_analytics_stripe_refunds AS
	SELECT
		  to_char(refund_date, 'YYYY-MM') AS calendar_month
		, sum(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 end) AS refunds_succeeded
		, sum(CASE WHEN status = 'failed' THEN 1 ELSE 0 end) AS refunds_failed
		, sum(CASE WHEN amount > 0 THEN amount ELSE 0 end) AS total_amount_refunded
	FROM
		refunds
	GROUP BY
		to_char(refund_date, 'YYYY-MM')
	ORDER BY
		calendar_month
;

CREATE OR REPLACE VIEW monthly_analytics_user_actions AS
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

CREATE OR REPLACE VIEW monthly_analytics_subscription_starts AS
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

CREATE OR REPLACE VIEW monthly_analytics AS
	SELECT
		  calendar_month
		, boxes_created
		, boxes_shipped
		, shop_orders_created
		, shop_orders_shipped
		, charges_succeeded
		, charges_failed
		, total_amount_charged
		, refunds_succeeded
		, refunds_failed
		, total_amount_refunded
		, user_cancelled
		, user_hold
		, user_reactivated
		, new_subscriptions
		, total_amount_charged - total_amount_refunded as net_revenue
	FROM
		monthly_analytics_boxes_created
			NATURAL RIGHT JOIN monthly_analytics_boxes_shipped
			NATURAL RIGHT JOIN monthly_analytics_shop_orders_created
			NATURAL RIGHT JOIN monthly_analytics_shop_orders_shipped
			NATURAL RIGHT JOIN monthly_analytics_stripe_charges 
			NATURAL RIGHT JOIN monthly_analytics_stripe_refunds
			NATURAL RIGHT JOIN monthly_analytics_subscription_starts
			NATURAL RIGHT JOIN monthly_analytics_user_actions
	ORDER BY
		calendar_month
;
