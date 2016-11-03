BEGIN;

DROP VIEW IF EXISTS box_churn_months CASCADE;
CREATE VIEW box_churn_months AS
    SELECT 
          to_char(month_start, 'YYYY-MM') AS calendar_month
        , extract(MONTH FROM month_start) AS month_num
        , extract(YEAR FROM month_start) AS year_num
        , extract(Quarter FROM month_start) AS quarter_num
        , to_char(month_start, 'Month') AS month_name
    FROM 
        (SELECT dateadd(day, row_number() OVER (), DATE '1/1/2016') AS month_start FROM subscription_events)
    WHERE
        month_start <= sysdate::DATE
            AND
        extract(DAY FROM month_start) = 1
;


DROP VIEW IF EXISTS box_churn_backdrop CASCADE;
CREATE VIEW box_churn_backdrop AS
    SELECT
          NULL::BIGINT AS id
        , user_id
        , NULL AS status
        , (calendar_month || '-01 00:00:00')::TIMESTAMP AS created_date
        , (calendar_month || '-01 00:00:00')::TIMESTAMP AS completed_date
        , NULL AS sku
        , NULL AS box_month
        , 'b' || to_char((calendar_month || '-01 00:00:00')::timestamp, 'YYMM') AS sku_month
        , 'b' || to_char((calendar_month || '-01 00:00:00')::timestamp, 'YYMM') AS sku_month_strict
        , 0::DECIMAL(10,2) AS booked_revenue
        , 0::DECIMAL(10,2) AS booked_price_items
        , 0::DECIMAL(10,2) AS booked_price_ship
        , 0::DECIMAL(10,2) AS booked_price_rush
        , 0::DECIMAL(10,2) AS booked_price
        , 0::DECIMAL(10,2) AS booked_stripe_charge_count
        , 0::DECIMAL(10,2) AS booked_stripe_charge_gross
        , 0::DECIMAL(10,2) AS booked_stripe_charge_fees
        , 0::DECIMAL(10,2) AS booked_stripe_charge_net
        , 0::DECIMAL(10,2) AS booked_stripe_refund_count
        , 0::DECIMAL(10,2) AS booked_stripe_refund_gross
        , 0::DECIMAL(10,2) AS booked_stripe_refund_fees
        , 0::DECIMAL(10,2) AS booked_stripe_refund_net
        , 0::DECIMAL(10,2) AS booked_stripe_fees_net
        , 0::DECIMAL(10,2) AS booked_stripe_net
        , 0::DECIMAL(10,2) AS todate_revenue
        , 0::DECIMAL(10,2) AS todate_price_items
        , 0::DECIMAL(10,2) AS todate_price_ship
        , 0::DECIMAL(10,2) AS todate_price_rush
        , 0::DECIMAL(10,2) AS todate_price
        , 0::DECIMAL(10,2) AS todate_stripe_charge_count
        , 0::DECIMAL(10,2) AS todate_stripe_charge_gross
        , 0::DECIMAL(10,2) AS todate_stripe_charge_fees
        , 0::DECIMAL(10,2) AS todate_stripe_charge_net
        , 0::DECIMAL(10,2) AS todate_stripe_refund_count
        , 0::DECIMAL(10,2) AS todate_stripe_refund_gross
        , 0::DECIMAL(10,2) AS todate_stripe_refund_fees
        , 0::DECIMAL(10,2) AS todate_stripe_refund_net
        , 0::DECIMAL(10,2) AS todate_stripe_fees_net
        , 0::DECIMAL(10,2) AS todate_stripe_net
        , 0::DECIMAL(10,2) AS ideal_revenue
        , 0::DECIMAL(10,2) AS ideal_price_items
        , 0::DECIMAL(10,2) AS ideal_price_ship
        , 0::DECIMAL(10,2) AS ideal_price_rush
        , 0::DECIMAL(10,2) AS ideal_price
        , 0::DECIMAL(10,2) AS ideal_stripe_charge_count
        , 0::DECIMAL(10,2) AS ideal_stripe_charge_gross
        , 0::DECIMAL(10,2) AS ideal_stripe_charge_fees
        , 0::DECIMAL(10,2) AS ideal_stripe_charge_net
        , 0::DECIMAL(10,2) AS ideal_stripe_refund_count
        , 0::DECIMAL(10,2) AS ideal_stripe_refund_gross
        , 0::DECIMAL(10,2) AS ideal_stripe_refund_fees
        , 0::DECIMAL(10,2) AS ideal_stripe_refund_net
        , 0::DECIMAL(10,2) AS ideal_stripe_fees_net
        , 0::DECIMAL(10,2) AS ideal_stripe_net
    FROM
        box_orders, months
    WHERE
        calendar_month >= (created_date - '31 days'::interval)
;

DROP VIEW IF EXISTS box_churn_base CASCADE;
CREATE VIEW box_churn_base AS
SELECT * FROM (
    SELECT 
          id
        , user_id
        , status
        , created_date
        , completed_date
        , sku
        , box_month
        -- use created date as sku month when we don't have the real value
        , sku_month as sku_month_strict
        , CASE
            WHEN sku_month = '' THEN 'b' || to_char(created_date, 'YYMM')
            ELSE sku_month
          END AS sku_month
        , booked_revenue
        , booked_price_items
        , booked_price_ship
        , booked_price_rush
        , booked_price
        , booked_stripe_charge_count
        , booked_stripe_charge_gross
        , booked_stripe_charge_fees
        , booked_stripe_charge_net
        , booked_stripe_refund_count
        , booked_stripe_refund_gross
        , booked_stripe_refund_fees
        , booked_stripe_refund_net
        , booked_stripe_fees_net
        , booked_stripe_net
        , todate_revenue
        , todate_price_items
        , todate_price_ship
        , todate_price_rush
        , todate_price
        , todate_stripe_charge_count
        , todate_stripe_charge_gross
        , todate_stripe_charge_fees
        , todate_stripe_charge_net
        , todate_stripe_refund_count
        , todate_stripe_refund_gross
        , todate_stripe_refund_fees
        , todate_stripe_refund_net
        , todate_stripe_fees_net
        , todate_stripe_net
        , ideal_revenue
        , ideal_price_items
        , ideal_price_ship
        , ideal_price_rush
        , ideal_price
        , ideal_stripe_charge_count
        , ideal_stripe_charge_gross
        , ideal_stripe_charge_fees
        , ideal_stripe_charge_net
        , ideal_stripe_refund_count
        , ideal_stripe_refund_gross
        , ideal_stripe_refund_fees
        , ideal_stripe_refund_net
        , ideal_stripe_fees_net
        , ideal_stripe_net
    FROM
        -- join on just the certain columns for boxes in the credit ledger
        box_orders NATURAL JOIN (
            SELECT
                  id::BIGINT
                , booked_revenue
                , booked_price_items
                , booked_price_ship
                , booked_price_rush
                , booked_price
                , booked_stripe_charge_count
                , booked_stripe_charge_gross
                , booked_stripe_charge_fees
                , booked_stripe_charge_net
                , booked_stripe_refund_count
                , booked_stripe_refund_gross
                , booked_stripe_refund_fees
                , booked_stripe_refund_net
                , booked_stripe_fees_net
                , booked_stripe_net
                , todate_revenue
                , todate_price_items
                , todate_price_ship
                , todate_price_rush
                , todate_price
                , todate_stripe_charge_count
                , todate_stripe_charge_gross
                , todate_stripe_charge_fees
                , todate_stripe_charge_net
                , todate_stripe_refund_count
                , todate_stripe_refund_gross
                , todate_stripe_refund_fees
                , todate_stripe_refund_net
                , todate_stripe_fees_net
                , todate_stripe_net
                , ideal_revenue
                , ideal_price_items
                , ideal_price_ship
                , ideal_price_rush
                , ideal_price
                , ideal_stripe_charge_count
                , ideal_stripe_charge_gross
                , ideal_stripe_charge_fees
                , ideal_stripe_charge_net
                , ideal_stripe_refund_count
                , ideal_stripe_refund_gross
                , ideal_stripe_refund_fees
                , ideal_stripe_refund_net
                , ideal_stripe_fees_net
                , ideal_stripe_net
            FROM
                box_credit_ledger
            WHERE
                event_type = 'box'
        )
    UNION
        SELECT * FROM box_churn_backdrop
)
ORDER BY
    user_id, sku_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_stage1 CASCADE;
CREATE VIEW box_churn_by_sku_month_stage1 AS
SELECT
      user_id
    , sku_month
    , count(DISTINCT id) AS box_count
    , sum(booked_revenue) AS booked_revenue
    , sum(booked_price_items) AS booked_price_items
    , sum(booked_price_ship) AS booked_price_ship
    , sum(booked_price_rush) AS booked_price_rush
    , sum(booked_price) AS booked_price
    , sum(booked_stripe_charge_count) AS booked_stripe_charge_count
    , sum(booked_stripe_charge_gross) AS booked_stripe_charge_gross
    , sum(booked_stripe_charge_fees) AS booked_stripe_charge_fees
    , sum(booked_stripe_charge_net) AS booked_stripe_charge_net
    , sum(booked_stripe_refund_count) AS booked_stripe_refund_count
    , sum(booked_stripe_refund_gross) AS booked_stripe_refund_gross
    , sum(booked_stripe_refund_fees) AS booked_stripe_refund_fees
    , sum(booked_stripe_refund_net) AS booked_stripe_refund_net
    , sum(booked_stripe_fees_net) AS booked_stripe_fees_net
    , sum(booked_stripe_net) AS booked_stripe_net
    , sum(todate_revenue) AS todate_revenue
    , sum(todate_price_items) AS todate_price_items
    , sum(todate_price_ship) AS todate_price_ship
    , sum(todate_price_rush) AS todate_price_rush
    , sum(todate_price) AS todate_price
    , sum(todate_stripe_charge_count) AS todate_stripe_charge_count
    , sum(todate_stripe_charge_gross) AS todate_stripe_charge_gross
    , sum(todate_stripe_charge_fees) AS todate_stripe_charge_fees
    , sum(todate_stripe_charge_net) AS todate_stripe_charge_net
    , sum(todate_stripe_refund_count) AS todate_stripe_refund_count
    , sum(todate_stripe_refund_gross) AS todate_stripe_refund_gross
    , sum(todate_stripe_refund_fees) AS todate_stripe_refund_fees
    , sum(todate_stripe_refund_net) AS todate_stripe_refund_net
    , sum(todate_stripe_fees_net) AS todate_stripe_fees_net
    , sum(todate_stripe_net) AS todate_stripe_net
    , sum(ideal_revenue) AS ideal_revenue
    , sum(ideal_price_items) AS ideal_price_items
    , sum(ideal_price_ship) AS ideal_price_ship
    , sum(ideal_price_rush) AS ideal_price_rush
    , sum(ideal_price) AS ideal_price
    , sum(ideal_stripe_charge_count) AS ideal_stripe_charge_count
    , sum(ideal_stripe_charge_gross) AS ideal_stripe_charge_gross
    , sum(ideal_stripe_charge_fees) AS ideal_stripe_charge_fees
    , sum(ideal_stripe_charge_net) AS ideal_stripe_charge_net
    , sum(ideal_stripe_refund_count) AS ideal_stripe_refund_count
    , sum(ideal_stripe_refund_gross) AS ideal_stripe_refund_gross
    , sum(ideal_stripe_refund_fees) AS ideal_stripe_refund_fees
    , sum(ideal_stripe_refund_net) AS ideal_stripe_refund_net
    , sum(ideal_stripe_fees_net) AS ideal_stripe_fees_net
    , sum(ideal_stripe_net) AS ideal_stripe_net
    , CASE WHEN sum(booked_revenue) > 0 THEN 1 ELSE 0 END AS active
FROM
    box_churn_base
GROUP BY
    user_id, sku_month
ORDER BY
    user_id, sku_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_strict_stage1 CASCADE;
CREATE VIEW box_churn_by_sku_month_strict_stage1 AS
SELECT
      user_id
    , sku_month_strict
    , count(DISTINCT id) AS box_count
    , sum(booked_revenue) AS booked_revenue
    , sum(booked_price_items) AS booked_price_items
    , sum(booked_price_ship) AS booked_price_ship
    , sum(booked_price_rush) AS booked_price_rush
    , sum(booked_price) AS booked_price
    , sum(booked_stripe_charge_count) AS booked_stripe_charge_count
    , sum(booked_stripe_charge_gross) AS booked_stripe_charge_gross
    , sum(booked_stripe_charge_fees) AS booked_stripe_charge_fees
    , sum(booked_stripe_charge_net) AS booked_stripe_charge_net
    , sum(booked_stripe_refund_count) AS booked_stripe_refund_count
    , sum(booked_stripe_refund_gross) AS booked_stripe_refund_gross
    , sum(booked_stripe_refund_fees) AS booked_stripe_refund_fees
    , sum(booked_stripe_refund_net) AS booked_stripe_refund_net
    , sum(booked_stripe_fees_net) AS booked_stripe_fees_net
    , sum(booked_stripe_net) AS booked_stripe_net
    , sum(todate_revenue) AS todate_revenue
    , sum(todate_price_items) AS todate_price_items
    , sum(todate_price_ship) AS todate_price_ship
    , sum(todate_price_rush) AS todate_price_rush
    , sum(todate_price) AS todate_price
    , sum(todate_stripe_charge_count) AS todate_stripe_charge_count
    , sum(todate_stripe_charge_gross) AS todate_stripe_charge_gross
    , sum(todate_stripe_charge_fees) AS todate_stripe_charge_fees
    , sum(todate_stripe_charge_net) AS todate_stripe_charge_net
    , sum(todate_stripe_refund_count) AS todate_stripe_refund_count
    , sum(todate_stripe_refund_gross) AS todate_stripe_refund_gross
    , sum(todate_stripe_refund_fees) AS todate_stripe_refund_fees
    , sum(todate_stripe_refund_net) AS todate_stripe_refund_net
    , sum(todate_stripe_fees_net) AS todate_stripe_fees_net
    , sum(todate_stripe_net) AS todate_stripe_net
    , sum(ideal_revenue) AS ideal_revenue
    , sum(ideal_price_items) AS ideal_price_items
    , sum(ideal_price_ship) AS ideal_price_ship
    , sum(ideal_price_rush) AS ideal_price_rush
    , sum(ideal_price) AS ideal_price
    , sum(ideal_stripe_charge_count) AS ideal_stripe_charge_count
    , sum(ideal_stripe_charge_gross) AS ideal_stripe_charge_gross
    , sum(ideal_stripe_charge_fees) AS ideal_stripe_charge_fees
    , sum(ideal_stripe_charge_net) AS ideal_stripe_charge_net
    , sum(ideal_stripe_refund_count) AS ideal_stripe_refund_count
    , sum(ideal_stripe_refund_gross) AS ideal_stripe_refund_gross
    , sum(ideal_stripe_refund_fees) AS ideal_stripe_refund_fees
    , sum(ideal_stripe_refund_net) AS ideal_stripe_refund_net
    , sum(ideal_stripe_fees_net) AS ideal_stripe_fees_net
    , sum(ideal_stripe_net) AS ideal_stripe_net
    , CASE WHEN sum(booked_revenue) > 0 THEN 1 ELSE 0 END AS active
FROM
    box_churn_base
GROUP BY
    user_id, sku_month_strict
ORDER BY
    user_id, sku_month_strict
;

DROP VIEW IF EXISTS box_churn_by_created_month_stage1 CASCADE;
CREATE VIEW box_churn_by_created_month_stage1 AS
SELECT
      user_id
    , to_char(created_date, 'YYYY-MM') AS created_month
    , count(DISTINCT id) AS box_count
    , sum(booked_revenue) AS booked_revenue
    , sum(booked_price_items) AS booked_price_items
    , sum(booked_price_ship) AS booked_price_ship
    , sum(booked_price_rush) AS booked_price_rush
    , sum(booked_price) AS booked_price
    , sum(booked_stripe_charge_count) AS booked_stripe_charge_count
    , sum(booked_stripe_charge_gross) AS booked_stripe_charge_gross
    , sum(booked_stripe_charge_fees) AS booked_stripe_charge_fees
    , sum(booked_stripe_charge_net) AS booked_stripe_charge_net
    , sum(booked_stripe_refund_count) AS booked_stripe_refund_count
    , sum(booked_stripe_refund_gross) AS booked_stripe_refund_gross
    , sum(booked_stripe_refund_fees) AS booked_stripe_refund_fees
    , sum(booked_stripe_refund_net) AS booked_stripe_refund_net
    , sum(booked_stripe_fees_net) AS booked_stripe_fees_net
    , sum(booked_stripe_net) AS booked_stripe_net
    , sum(todate_revenue) AS todate_revenue
    , sum(todate_price_items) AS todate_price_items
    , sum(todate_price_ship) AS todate_price_ship
    , sum(todate_price_rush) AS todate_price_rush
    , sum(todate_price) AS todate_price
    , sum(todate_stripe_charge_count) AS todate_stripe_charge_count
    , sum(todate_stripe_charge_gross) AS todate_stripe_charge_gross
    , sum(todate_stripe_charge_fees) AS todate_stripe_charge_fees
    , sum(todate_stripe_charge_net) AS todate_stripe_charge_net
    , sum(todate_stripe_refund_count) AS todate_stripe_refund_count
    , sum(todate_stripe_refund_gross) AS todate_stripe_refund_gross
    , sum(todate_stripe_refund_fees) AS todate_stripe_refund_fees
    , sum(todate_stripe_refund_net) AS todate_stripe_refund_net
    , sum(todate_stripe_fees_net) AS todate_stripe_fees_net
    , sum(todate_stripe_net) AS todate_stripe_net
    , sum(ideal_revenue) AS ideal_revenue
    , sum(ideal_price_items) AS ideal_price_items
    , sum(ideal_price_ship) AS ideal_price_ship
    , sum(ideal_price_rush) AS ideal_price_rush
    , sum(ideal_price) AS ideal_price
    , sum(ideal_stripe_charge_count) AS ideal_stripe_charge_count
    , sum(ideal_stripe_charge_gross) AS ideal_stripe_charge_gross
    , sum(ideal_stripe_charge_fees) AS ideal_stripe_charge_fees
    , sum(ideal_stripe_charge_net) AS ideal_stripe_charge_net
    , sum(ideal_stripe_refund_count) AS ideal_stripe_refund_count
    , sum(ideal_stripe_refund_gross) AS ideal_stripe_refund_gross
    , sum(ideal_stripe_refund_fees) AS ideal_stripe_refund_fees
    , sum(ideal_stripe_refund_net) AS ideal_stripe_refund_net
    , sum(ideal_stripe_fees_net) AS ideal_stripe_fees_net
    , sum(ideal_stripe_net) AS ideal_stripe_net
    , CASE WHEN sum(booked_revenue) > 0 THEN 1 ELSE 0 END AS active
FROM
    box_churn_base
GROUP BY
    user_id, created_month
ORDER BY
    user_id, created_month
;

DROP VIEW IF EXISTS box_churn_by_shipped_month_stage1 CASCADE;
CREATE VIEW box_churn_by_shipped_month_stage1 AS
SELECT
      user_id
    , to_char(completed_date, 'YYYY-MM') AS shipped_month
    , count(DISTINCT id) AS box_count
    , sum(booked_revenue) AS booked_revenue
    , sum(booked_price_items) AS booked_price_items
    , sum(booked_price_ship) AS booked_price_ship
    , sum(booked_price_rush) AS booked_price_rush
    , sum(booked_price) AS booked_price
    , sum(booked_stripe_charge_count) AS booked_stripe_charge_count
    , sum(booked_stripe_charge_gross) AS booked_stripe_charge_gross
    , sum(booked_stripe_charge_fees) AS booked_stripe_charge_fees
    , sum(booked_stripe_charge_net) AS booked_stripe_charge_net
    , sum(booked_stripe_refund_count) AS booked_stripe_refund_count
    , sum(booked_stripe_refund_gross) AS booked_stripe_refund_gross
    , sum(booked_stripe_refund_fees) AS booked_stripe_refund_fees
    , sum(booked_stripe_refund_net) AS booked_stripe_refund_net
    , sum(booked_stripe_fees_net) AS booked_stripe_fees_net
    , sum(booked_stripe_net) AS booked_stripe_net
    , sum(todate_revenue) AS todate_revenue
    , sum(todate_price_items) AS todate_price_items
    , sum(todate_price_ship) AS todate_price_ship
    , sum(todate_price_rush) AS todate_price_rush
    , sum(todate_price) AS todate_price
    , sum(todate_stripe_charge_count) AS todate_stripe_charge_count
    , sum(todate_stripe_charge_gross) AS todate_stripe_charge_gross
    , sum(todate_stripe_charge_fees) AS todate_stripe_charge_fees
    , sum(todate_stripe_charge_net) AS todate_stripe_charge_net
    , sum(todate_stripe_refund_count) AS todate_stripe_refund_count
    , sum(todate_stripe_refund_gross) AS todate_stripe_refund_gross
    , sum(todate_stripe_refund_fees) AS todate_stripe_refund_fees
    , sum(todate_stripe_refund_net) AS todate_stripe_refund_net
    , sum(todate_stripe_fees_net) AS todate_stripe_fees_net
    , sum(todate_stripe_net) AS todate_stripe_net
    , sum(ideal_revenue) AS ideal_revenue
    , sum(ideal_price_items) AS ideal_price_items
    , sum(ideal_price_ship) AS ideal_price_ship
    , sum(ideal_price_rush) AS ideal_price_rush
    , sum(ideal_price) AS ideal_price
    , sum(ideal_stripe_charge_count) AS ideal_stripe_charge_count
    , sum(ideal_stripe_charge_gross) AS ideal_stripe_charge_gross
    , sum(ideal_stripe_charge_fees) AS ideal_stripe_charge_fees
    , sum(ideal_stripe_charge_net) AS ideal_stripe_charge_net
    , sum(ideal_stripe_refund_count) AS ideal_stripe_refund_count
    , sum(ideal_stripe_refund_gross) AS ideal_stripe_refund_gross
    , sum(ideal_stripe_refund_fees) AS ideal_stripe_refund_fees
    , sum(ideal_stripe_refund_net) AS ideal_stripe_refund_net
    , sum(ideal_stripe_fees_net) AS ideal_stripe_fees_net
    , sum(ideal_stripe_net) AS ideal_stripe_net
    , CASE WHEN sum(booked_revenue) > 0 THEN 1 ELSE 0 END AS active
FROM
    box_churn_base
GROUP BY
    user_id, shipped_month
ORDER BY
    user_id, shipped_month
;


DROP VIEW IF EXISTS box_churn_by_sku_month_stage2 CASCADE;
CREATE VIEW box_churn_by_sku_month_stage2 AS
SELECT
      user_id
    , sku_month
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , active
    , lag(active, 1) OVER (PARTITION BY user_id ORDER BY sku_month) AS active_lag
    , lag(active, 2) OVER (PARTITION BY user_id ORDER BY sku_month) AS active_lag2
    , lead(active, 1) OVER (PARTITION BY user_id ORDER BY sku_month) AS active_lead
FROM
    box_churn_by_sku_month_stage1
ORDER BY
    user_id, sku_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_strict_stage2 CASCADE;
CREATE VIEW box_churn_by_sku_month_strict_stage2 AS
SELECT
      user_id
    , sku_month_strict
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , active
    , lag(active, 1) OVER (PARTITION BY user_id ORDER BY sku_month_strict) AS active_lag
    , lag(active, 2) OVER (PARTITION BY user_id ORDER BY sku_month_strict) AS active_lag2
    , lead(active, 1) OVER (PARTITION BY user_id ORDER BY sku_month_strict) AS active_lead
FROM
    box_churn_by_sku_month_strict_stage1
ORDER BY
    user_id, sku_month_strict
;

DROP VIEW IF EXISTS box_churn_by_created_month_stage2 CASCADE;
CREATE VIEW box_churn_by_created_month_stage2 AS
SELECT
      user_id
    , created_month
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , active
    , lag(active, 1) OVER (PARTITION BY user_id ORDER BY created_month) AS active_lag
    , lag(active, 2) OVER (PARTITION BY user_id ORDER BY created_month) AS active_lag2
    , lead(active, 1) OVER (PARTITION BY user_id ORDER BY created_month) AS active_lead
FROM
    box_churn_by_created_month_stage1
ORDER BY
    user_id, created_month
;

DROP VIEW IF EXISTS box_churn_by_shipped_month_stage2 CASCADE;
CREATE VIEW box_churn_by_shipped_month_stage2 AS
SELECT
      user_id
    , shipped_month
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , active
    , lag(active, 1) OVER (PARTITION BY user_id ORDER BY shipped_month) AS active_lag
    , lag(active, 2) OVER (PARTITION BY user_id ORDER BY shipped_month) AS active_lag2
    , lead(active, 1) OVER (PARTITION BY user_id ORDER BY shipped_month) AS active_lead
FROM
    box_churn_by_shipped_month_stage1
ORDER BY
    user_id, shipped_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_stage3 CASCADE;
CREATE VIEW box_churn_by_sku_month_stage3 AS
SELECT
      user_id
    , sku_month
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , active
    , CASE WHEN active = 1 AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END AS activated_raw
    , CASE WHEN active = 1 AND (active_lag2 IS NULL OR active_lag2 = 0) AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END AS activated2
    , CASE WHEN active = 0 AND active_lag = 1 THEN 1 ELSE 0 END AS churned_raw
    , CASE WHEN active = 0 AND (active_lag2 = 1) AND (active_lag = 0 OR active_lag IS NULL) THEN 1 ELSE 0 END AS churned2
    , sum(CASE WHEN active = 1 AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END) OVER (PARTITION BY user_id ORDER BY sku_month  ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS activation_count
    , active_lead
    , active_lag
    , active_lag2
FROM
    box_churn_by_sku_month_stage2
ORDER BY
    user_id, sku_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_strict_stage3 CASCADE;
CREATE VIEW box_churn_by_sku_month_strict_stage3 AS
SELECT
      user_id
    , sku_month_strict
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , active
    , CASE WHEN active = 1 AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END AS activated_raw
    , CASE WHEN active = 1 AND (active_lag2 IS NULL OR active_lag2 = 0) AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END AS activated2
    , CASE WHEN active = 0 AND active_lag = 1 THEN 1 ELSE 0 END AS churned_raw
    , CASE WHEN active = 0 AND (active_lag2 = 1) AND (active_lag = 0 OR active_lag IS NULL) THEN 1 ELSE 0 END AS churned2
    , sum(CASE WHEN active = 1 AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END) OVER (PARTITION BY user_id ORDER BY sku_month_strict  ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS activation_count
    , active_lead
    , active_lag
    , active_lag2
FROM
    box_churn_by_sku_month_strict_stage2
ORDER BY
    user_id, sku_month_strict
;

DROP VIEW IF EXISTS box_churn_by_created_month_stage3 CASCADE;
CREATE VIEW box_churn_by_created_month_stage3 AS
SELECT
      user_id
    , created_month
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , active
    , CASE WHEN active = 1 AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END AS activated_raw
    , CASE WHEN active = 1 AND (active_lag2 IS NULL OR active_lag2 = 0) AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END AS activated2
    , CASE WHEN active = 0 AND active_lag = 1 THEN 1 ELSE 0 END AS churned_raw
    , CASE WHEN active = 0 AND (active_lag2 = 1) AND (active_lag = 0 OR active_lag IS NULL) THEN 1 ELSE 0 END AS churned2
    , sum(CASE WHEN active = 1 AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END) OVER (PARTITION BY user_id ORDER BY created_month  ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS activation_count
    , active_lead
    , active_lag
    , active_lag2
FROM
    box_churn_by_created_month_stage2
ORDER BY
    user_id, created_month
;

DROP VIEW IF EXISTS box_churn_by_shipped_month_stage3 CASCADE;
CREATE VIEW box_churn_by_shipped_month_stage3 AS
SELECT
      user_id
    , shipped_month
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , active
    , CASE WHEN active = 1 AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END AS activated_raw
    , CASE WHEN active = 1 AND (active_lag2 IS NULL OR active_lag2 = 0) AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END AS activated2
    , CASE WHEN active = 0 AND active_lag = 1 THEN 1 ELSE 0 END AS churned_raw
    , CASE WHEN active = 0 AND (active_lag2 = 1) AND (active_lag = 0 OR active_lag IS NULL) THEN 1 ELSE 0 END AS churned2
    , sum(CASE WHEN active = 1 AND (active_lag IS NULL OR active_lag = 0) THEN 1 ELSE 0 END) OVER (PARTITION BY user_id ORDER BY shipped_month  ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS activation_count
    , active_lead
    , active_lag
    , active_lag2
FROM
    box_churn_by_shipped_month_stage2
ORDER BY
    user_id, shipped_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_stage4 CASCADE;
CREATE VIEW box_churn_by_sku_month_stage4 AS
SELECT
      user_id
    , sku_month
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , CASE WHEN activation_count > 1 AND activated_raw = 1 THEN 1 ELSE 0 END AS reactivated
    , activated_raw AS activated
    , active
    , churned_raw AS churned
    , CASE WHEN activation_count > 1 AND activated2 = 1 THEN 1 ELSE 0 END AS reactivated_2
    , activated2 AS activated_2
    , CASE WHEN active = 1 OR active_lag = 1 THEN 1 ELSE 0 END AS active_2
    , churned2 AS churned_2
    , activated_raw
    , activated2
    , churned_raw
    , churned2
    , activation_count
    , active_lead
    , active_lag
    , active_lag2
FROM
    box_churn_by_sku_month_stage3
ORDER BY
    user_id, sku_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_strict_stage4 CASCADE;
CREATE VIEW box_churn_by_sku_month_strict_stage4 AS
SELECT
      user_id
    , sku_month_strict
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , CASE WHEN activation_count > 1 AND activated_raw = 1 THEN 1 ELSE 0 END AS reactivated
    , activated_raw AS activated
    , active
    , churned_raw AS churned
    , CASE WHEN activation_count > 1 AND activated2 = 1 THEN 1 ELSE 0 END AS reactivated_2
    , activated2 AS activated_2
    , CASE WHEN active = 1 OR active_lag = 1 THEN 1 ELSE 0 END AS active_2
    , churned2 AS churned_2
    , activated_raw
    , activated2
    , churned_raw
    , churned2
    , activation_count
    , active_lead
    , active_lag
    , active_lag2
FROM
    box_churn_by_sku_month_strict_stage3
ORDER BY
    user_id, sku_month_strict
;

DROP VIEW IF EXISTS box_churn_by_created_month_stage4 CASCADE;
CREATE VIEW box_churn_by_created_month_stage4 AS
SELECT
      user_id
    , created_month
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , CASE WHEN activation_count > 1 AND activated_raw = 1 THEN 1 ELSE 0 END AS reactivated
    , activated_raw AS activated
    , active
    , churned_raw AS churned
    , CASE WHEN activation_count > 1 AND activated2 = 1 THEN 1 ELSE 0 END AS reactivated_2
    , activated2 AS activated_2
    , CASE WHEN active = 1 OR active_lag = 1 THEN 1 ELSE 0 END AS active_2
    , churned2 AS churned_2
    , activated_raw
    , activated2
    , churned_raw
    , churned2
    , activation_count
    , active_lead
    , active_lag
    , active_lag2
FROM
    box_churn_by_created_month_stage3
ORDER BY
    user_id, created_month
;

DROP VIEW IF EXISTS box_churn_by_shipped_month_stage4 CASCADE;
CREATE VIEW box_churn_by_shipped_month_stage4 AS
SELECT
      user_id
    , shipped_month
    , box_count
    , booked_revenue
    , booked_price_items
    , booked_price_ship
    , booked_price_rush
    , booked_price
    , booked_stripe_charge_count
    , booked_stripe_charge_gross
    , booked_stripe_charge_fees
    , booked_stripe_charge_net
    , booked_stripe_refund_count
    , booked_stripe_refund_gross
    , booked_stripe_refund_fees
    , booked_stripe_refund_net
    , booked_stripe_fees_net
    , booked_stripe_net
    , todate_revenue
    , todate_price_items
    , todate_price_ship
    , todate_price_rush
    , todate_price
    , todate_stripe_charge_count
    , todate_stripe_charge_gross
    , todate_stripe_charge_fees
    , todate_stripe_charge_net
    , todate_stripe_refund_count
    , todate_stripe_refund_gross
    , todate_stripe_refund_fees
    , todate_stripe_refund_net
    , todate_stripe_fees_net
    , todate_stripe_net
    , ideal_revenue
    , ideal_price_items
    , ideal_price_ship
    , ideal_price_rush
    , ideal_price
    , ideal_stripe_charge_count
    , ideal_stripe_charge_gross
    , ideal_stripe_charge_fees
    , ideal_stripe_charge_net
    , ideal_stripe_refund_count
    , ideal_stripe_refund_gross
    , ideal_stripe_refund_fees
    , ideal_stripe_refund_net
    , ideal_stripe_fees_net
    , ideal_stripe_net
    , CASE WHEN activation_count > 1 AND activated_raw = 1 THEN 1 ELSE 0 END AS reactivated
    , activated_raw AS activated
    , active
    , churned_raw AS churned
    , CASE WHEN activation_count > 1 AND activated2 = 1 THEN 1 ELSE 0 END AS reactivated_2
    , activated2 AS activated_2
    , CASE WHEN active = 1 OR active_lag = 1 THEN 1 ELSE 0 END AS active_2
    , churned2 AS churned_2
    , activated_raw
    , activated2
    , churned_raw
    , churned2
    , activation_count
    , active_lead
    , active_lag
    , active_lag2
FROM
    box_churn_by_shipped_month_stage3
ORDER BY
    user_id, shipped_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month CASCADE;
CREATE VIEW box_churn_by_sku_month AS
SELECT
      sku_month
    , sum(box_count) AS box_count
    , sum(booked_revenue)::DECIMAL(10,2) AS booked_revenue
    , sum(ideal_revenue)::DECIMAL(10,2) AS ideal_revenue
    , sum(todate_revenue)::DECIMAL(10,2) AS todate_revenue
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_net_per_box
    , sum(reactivated) AS reactivated
    , sum(activated) AS activated
    , sum(active) AS active
    , sum(churned) AS churned
    , CASE WHEN sum(active) > 0 THEN (100.0 * sum(churned) / sum(active))::decimal(10,2) ELSE 0 END AS churn_pct
    , sum(reactivated_2) AS reactivated2
    , sum(activated_2) AS activated2
    , sum(active_2) AS active2
    , sum(churned_2) AS churned2
    , CASE WHEN sum(active_2) > 0 THEN (100.0 * sum(churned_2) / sum(active_2))::decimal(10,2) ELSE 0 END AS churn_pct2

FROM
    box_churn_by_sku_month_stage4
GROUP BY
    sku_month
ORDER BY
    sku_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_strict CASCADE;
CREATE VIEW box_churn_by_sku_month_strict AS
SELECT
      sku_month_strict
    , sum(box_count) AS box_count
    , sum(booked_revenue)::DECIMAL(10,2) AS booked_revenue
    , sum(ideal_revenue)::DECIMAL(10,2) AS ideal_revenue
    , sum(todate_revenue)::DECIMAL(10,2) AS todate_revenue
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_net_per_box
    , sum(reactivated) AS reactivated
    , sum(activated) AS activated
    , sum(active) AS active
    , sum(churned) AS churned
    , CASE WHEN sum(active) > 0 THEN (100.0 * sum(churned) / sum(active))::decimal(10,2) ELSE 0 END AS churn_pct
    , sum(reactivated_2) AS reactivated2
    , sum(activated_2) AS activated2
    , sum(active_2) AS active2
    , sum(churned_2) AS churned2
    , CASE WHEN sum(active_2) > 0 THEN (100.0 * sum(churned_2) / sum(active_2))::decimal(10,2) ELSE 0 END AS churn_pct2

FROM
    box_churn_by_sku_month_strict_stage4
GROUP BY
    sku_month_strict
ORDER BY
    sku_month_strict
;

DROP VIEW IF EXISTS box_churn_by_created_month CASCADE;
CREATE VIEW box_churn_by_created_month AS
SELECT
      created_month
    , sum(box_count) AS box_count
    , sum(booked_revenue)::DECIMAL(10,2) AS booked_revenue
    , sum(ideal_revenue)::DECIMAL(10,2) AS ideal_revenue
    , sum(todate_revenue)::DECIMAL(10,2) AS todate_revenue
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_net_per_box
    , sum(reactivated) AS reactivated
    , sum(activated) AS activated
    , sum(active) AS active
    , sum(churned) AS churned
    , CASE WHEN sum(active) > 0 THEN (100.0 * sum(churned) / sum(active))::decimal(10,2) ELSE 0 END AS churn_pct
    , sum(reactivated_2) AS reactivated2
    , sum(activated_2) AS activated2
    , sum(active_2) AS active2
    , sum(churned_2) AS churned2
    , CASE WHEN sum(active_2) > 0 THEN (100.0 * sum(churned_2) / sum(active_2))::decimal(10,2) ELSE 0 END AS churn_pct2

FROM
    box_churn_by_created_month_stage4
GROUP BY
    created_month
ORDER BY
    created_month
;

DROP VIEW IF EXISTS box_churn_by_shipped_month CASCADE;
CREATE VIEW box_churn_by_shipped_month AS
SELECT
      shipped_month
    , sum(box_count) AS box_count
    , sum(booked_revenue)::DECIMAL(10,2) AS booked_revenue
    , sum(ideal_revenue)::DECIMAL(10,2) AS ideal_revenue
    , sum(todate_revenue)::DECIMAL(10,2) AS todate_revenue
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_revenue) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_revenue_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_items) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_items_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_ship) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_ship_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price_rush) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_rush_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_price) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_price_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_charge_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_charge_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_count) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_count_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_gross) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_gross_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_fees) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_fees_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_refund_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_refund_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_fees_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_fees_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(booked_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS booked_stripe_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(ideal_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS ideal_stripe_net_per_box
    , CASE WHEN sum(box_count) > 0 THEN sum(todate_stripe_net) / sum(box_count) ELSE 0 END::DECIMAL(10,2) AS todate_stripe_net_per_box
    , sum(reactivated) AS reactivated
    , sum(activated) AS activated
    , sum(active) AS active
    , sum(churned) AS churned
    , CASE WHEN sum(active) > 0 THEN (100.0 * sum(churned) / sum(active))::decimal(10,2) ELSE 0 END AS churn_pct
    , sum(reactivated_2) AS reactivated2
    , sum(activated_2) AS activated2
    , sum(active_2) AS active2
    , sum(churned_2) AS churned2
    , CASE WHEN sum(active_2) > 0 THEN (100.0 * sum(churned_2) / sum(active_2))::decimal(10,2) ELSE 0 END AS churn_pct2

FROM
    box_churn_by_shipped_month_stage4
GROUP BY
    shipped_month
ORDER BY
    shipped_month
;

COMMIT;
