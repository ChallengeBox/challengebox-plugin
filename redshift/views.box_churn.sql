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
        , 0::DECIMAL(10,2) AS booked_revenue
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
        , CASE
            WHEN sku_month = '' THEN 'b' || to_char(created_date, 'YYMM')
            ELSE sku_month
          END AS sku_month
        , box_booked_revenue AS booked_revenue
    FROM
        -- join on just the booked revenue column for boxes in the credit ledger
        box_orders NATURAL JOIN (SELECT id::BIGINT, box_booked_revenue FROM box_credit_ledger WHERE event_type = 'box')
    UNION
        SELECT * FROM box_churn_backdrop
)
ORDER BY
    user_id, sku_month
;

DROP VIEW IF EXISTS box_churn_by_created_month_stage1 CASCADE;
CREATE VIEW box_churn_by_created_month_stage1 AS
SELECT
      user_id
    , to_char(created_date, 'YYYY-MM') AS created_month
    , sum(booked_revenue) AS month_revenue
    , CASE WHEN sum(booked_revenue) > 0 THEN 1 ELSE 0 END AS active
FROM
    box_churn_base
GROUP BY
    user_id, created_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_stage1 CASCADE;
CREATE VIEW box_churn_by_sku_month_stage1 AS
SELECT
      user_id
    , sku_month
    , sum(booked_revenue) AS month_revenue
    , CASE WHEN sum(booked_revenue) > 0 THEN 1 ELSE 0 END AS active
FROM
    box_churn_base
GROUP BY
    user_id, sku_month
;

DROP VIEW IF EXISTS box_churn_by_sku_month_stage2 CASCADE;
CREATE VIEW box_churn_by_sku_month_stage2 AS
SELECT
      user_id
    , sku_month
    , month_revenue
    , active
    , lag(active, 1) OVER (PARTITION BY user_id ORDER BY sku_month) AS active_lag
    , lag(active, 2) OVER (PARTITION BY user_id ORDER BY sku_month) AS active_lag2
    , lead(active, 1) OVER (PARTITION BY user_id ORDER BY sku_month) AS active_lead
FROM
    box_churn_by_sku_month_stage1
;

DROP VIEW IF EXISTS box_churn_by_sku_month_stage3 CASCADE;
CREATE VIEW box_churn_by_sku_month_stage3 AS
SELECT
      user_id
    , sku_month
    , month_revenue
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
;

DROP VIEW IF EXISTS box_churn_by_sku_month_stage4 CASCADE;
CREATE VIEW box_churn_by_sku_month_stage4 AS
SELECT
      user_id
    , sku_month
    , month_revenue
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
;

DROP VIEW IF EXISTS box_churn_by_sku_month CASCADE;
CREATE VIEW box_churn_by_sku_month AS
SELECT
      sku_month
    , sum(month_revenue)::decimal(10,2) AS booked_revenue
    , sum(reactivated) AS reactivated
    , sum(activated) AS activated
    , sum(active) AS active
    , sum(churned) AS churned
    , (100.0 * sum(churned) / sum(active))::decimal(10,2) AS churn_pct
    , sum(reactivated_2) AS reactivated2
    , sum(activated_2) AS activated2
    , sum(active_2) AS active2
    , sum(churned_2) AS churned2
    , (100.0 * sum(churned_2) / sum(active_2))::decimal(10,2) AS churn_pct2

FROM
    box_churn_by_sku_month_stage4
GROUP BY
    sku_month
ORDER BY
    sku_month
;

COMMIT;