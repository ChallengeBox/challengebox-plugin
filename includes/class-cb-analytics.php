<?php

/**
 * Routines for generating analytics queries from redshift.
 */

class CBAnalytics extends CBRedshift {

	public function get_monthly_analytics() {
		return $this->execute_query($this->sql_monthly_analytics());
	}

	public function get_monthly_ss() {
		return $this->execute_query($this->sql_monthly_subscription_statuses());
	}

	public function sql_monthly_subscription_statuses() {
		return <<<SQL
SELECT
      to_char(start_date, 'YYYY-MM') AS start_month
    , count(id) AS total
    , sum(CASE WHEN status = 'active' THEN 1 ELSE 0 end) AS active
    , sum(CASE WHEN status = 'pending' THEN 1 ELSE 0 end) AS pending
    , sum(CASE WHEN status = 'pending-cancel' THEN 1 ELSE 0 end) AS pending_cancel
    , sum(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 end) AS cancelled
    , sum(CASE WHEN status = 'switched' THEN 1 ELSE 0 end) AS switched
    , sum(CASE WHEN status = 'expired' THEN 1 ELSE 0 end) AS expired
    , sum(CASE WHEN status = 'on-hold' THEN 1 ELSE 0 end) AS on_hold
FROM
    $this->schema.subscriptions
GROUP BY
    to_char(start_date, 'YYYY-MM')
ORDER BY
    to_char(start_date, 'YYYY-MM')
SQL;
	}

	public function sql_monthly_analytics() {
		return <<<SQL
WITH boxes_shipped AS (
SELECT
      to_char(completed_date, 'YYYY-MM') AS calendar_month
    , sum(CASE WHEN status IN ('completed', 'refunded') THEN 1 ELSE 0 end) AS boxes_shipped
FROM
    $this->schema.box_orders
GROUP BY
    to_char(completed_date, 'YYYY-MM')
ORDER BY
    calendar_month
)
, boxes_created AS (
SELECT
      to_char(created_date, 'YYYY-MM') AS calendar_month
    , count(id) AS boxes_created
FROM
    $this->schema.box_orders
GROUP BY
    to_char(created_date, 'YYYY-MM')
ORDER BY
    calendar_month
)
, shop_orders_created AS (
SELECT
      to_char(created_date, 'YYYY-MM') AS calendar_month
    , count(id) AS shop_orders_created
FROM
    $this->schema.shop_orders
GROUP BY
    to_char(created_date, 'YYYY-MM')
ORDER BY
    calendar_month
)
, shop_orders_shipped AS (
SELECT
      to_char(completed_date, 'YYYY-MM') AS calendar_month
    , sum(CASE WHEN status IN ('completed', 'refunded') THEN 1 ELSE 0 end) AS shop_orders_shipped
FROM
    $this->schema.shop_orders
GROUP BY
    to_char(completed_date, 'YYYY-MM')
ORDER BY
    calendar_month
)

, stripe_charges AS (
SELECT
      to_char(charge_date, 'YYYY-MM') AS calendar_month
    , sum(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 end) AS charges_succeeded
    , sum(CASE WHEN status = 'failed' THEN 1 ELSE 0 end) AS charges_failed
    , sum(amount) AS total_amount_charged
FROM
    $this->schema.charges
GROUP BY
    to_char(charge_date, 'YYYY-MM')
ORDER BY
    calendar_month
)
, stripe_refunds AS (
SELECT
      to_char(refund_date, 'YYYY-MM') AS calendar_month
    , sum(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 end) AS refunds_succeeded
    , sum(CASE WHEN status = 'failed' THEN 1 ELSE 0 end) AS refunds_failed
    , sum(CASE WHEN amount > 0 THEN amount ELSE 0 end) AS total_amount_refunded
FROM
    $this->schema.refunds
GROUP BY
    to_char(refund_date, 'YYYY-MM')
ORDER BY
    calendar_month
)
, user_actions AS (
SELECT
      to_char(date, 'YYYY-MM') AS calendar_month
    , sum(CASE WHEN event = 'user-cancelled' THEN 1 ELSE 0 end) AS user_cancelled
    , sum(CASE WHEN event = 'user-hold' THEN 1 ELSE 0 end) AS user_hold
    , sum(CASE WHEN event = 'user-reactivated' THEN 1 ELSE 0 end) AS user_reactivated
FROM
    $this->schema.subscription_events
GROUP BY
    to_char(date, 'YYYY-MM')
ORDER BY
    calendar_month
)
, subscription_starts AS (
SELECT
      to_char(start_date, 'YYYY-MM') AS calendar_month
    , count(id) AS new_subscriptions
FROM
    $this->schema.subscriptions
GROUP BY
    to_char(start_date, 'YYYY-MM')
ORDER BY
    calendar_month
)
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
    boxes_created NATURAL RIGHT JOIN boxes_shipped
                  NATURAL RIGHT JOIN shop_orders_created
                  NATURAL RIGHT JOIN shop_orders_shipped
                  NATURAL RIGHT JOIN stripe_charges 
                  NATURAL RIGHT JOIN stripe_refunds
                  NATURAL RIGHT JOIN subscription_starts
                  NATURAL RIGHT JOIN user_actions
ORDER BY
    calendar_month
SQL;
	}

}
