BEGIN;

DROP VIEW IF EXISTS months CASCADE;
CREATE VIEW months AS
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

DROP VIEW IF EXISTS subscription_churn_calendar_frontdrop CASCADE;
CREATE VIEW subscription_churn_calendar_frontdrop AS
    SELECT
          0::BIGINT AS id
        , id AS subscription_id
        , user_id
        , NULL AS event
        , (last_day((calendar_month||'-01')::DATE)||' 23:59:59')::TIMESTAMP AS event_date
        , NULL AS old_state
        , NULL AS new_state
        , NULL AS comment
    FROM
        subscriptions, months
    WHERE
        calendar_month >= start_date
;

DROP VIEW IF EXISTS subscription_churn_calendar_backdrop CASCADE;
CREATE VIEW subscription_churn_calendar_backdrop AS
    SELECT
          0::BIGINT AS id
        , id AS subscription_id
        , user_id
        , NULL AS event
        , (calendar_month || '-01 00:00:00')::DATE AS event_date
        , NULL AS old_state
        , NULL AS new_state
        , NULL AS comment
    FROM
        subscriptions, months
    WHERE
        calendar_month >= start_date
;

DROP VIEW IF EXISTS subscription_event_prep CASCADE;
CREATE VIEW subscription_event_prep AS
    SELECT
          id
        , subscription_id
        , user_id
        , event
        , event_date
        , CASE WHEN old_state = '' THEN NULL ELSE old_state END AS old_state
        , CASE WHEN new_state = '' THEN NULL ELSE new_state END AS new_state
        , NULL AS comment
    FROM
        subscription_events
;

DROP VIEW IF EXISTS subscription_churn_base CASCADE;
CREATE VIEW subscription_churn_base AS
    SELECT 
          id
        , user_id
        , subscription_id
        , event
        , event_date
        , lag(event_date, 1) OVER (PARTITION BY subscription_id ORDER BY event_date, id DESC)
            AS date_previous
        , coalesce(lead(event_date, 1) OVER (PARTITION BY subscription_id ORDER BY event_date, id DESC), sysdate)
            AS date_next
        , to_char(event_date, 'YYYY-MM')
            AS calendar_month
        , lead(to_char(event_date, 'YYYY-MM'), 1) OVER (PARTITION BY subscription_id ORDER BY event_date, id DESC)
            AS calendar_month_next
        , old_state
        , new_state
        , CASE
            WHEN new_state IS NOT NULL AND new_state <> '' THEN new_state
            ELSE lag(new_state, 1) IGNORE NULLS OVER (PARTITION BY subscription_id ORDER BY event_date, id DESC)
          END
            AS current_state
        , coalesce(datediff('days', event_date, lead(event_date, 1) OVER (PARTITION BY user_id, subscription_id ORDER BY event_date, id DESC)), 0)
            AS days_in_current_state
        , datediff('days', to_char(event_date, 'YYYY-MM-01')::DATE, last_day(to_char(event_date, 'YYYY-MM-01')::DATE)) AS days_in_month
        , comment
    FROM
       (SELECT * FROM subscription_event_prep UNION SELECT * FROM subscription_churn_calendar_backdrop UNION SELECT * FROM subscription_churn_calendar_frontdrop)
    ORDER BY
        user_id, subscription_id, event_date, id DESC
;

DROP VIEW IF EXISTS subscription_churn_state_setup CASCADE;
CREATE VIEW subscription_churn_state_setup AS
    SELECT
        id, user_id, subscription_id, event, event_date, date_previous, calendar_month, calendar_month_next, old_state, new_state
        , current_state, days_in_current_state, days_in_month
        , CASE WHEN current_state = 'active' THEN days_in_current_state ELSE 0 END AS a
        , CASE WHEN current_state = 'on-hold' THEN days_in_current_state ELSE 0 END AS h
        , CASE WHEN current_state = 'cancelled' THEN days_in_current_state ELSE 0 END AS c
        , CASE WHEN current_state = 'pending' THEN days_in_current_state ELSE 0 END AS p
        , CASE WHEN current_state = 'pending-cancel' THEN days_in_current_state ELSE 0 END AS pc
    FROM
        subscription_churn_base
    ORDER BY
        user_id, subscription_id, event_date, id DESC
;

DROP VIEW IF EXISTS subscription_churn_state_sums CASCADE;
CREATE VIEW subscription_churn_state_sums AS
    SELECT
        id, user_id, subscription_id, event, event_date, date_previous, calendar_month, calendar_month_next, old_state, new_state
        , current_state, days_in_current_state, days_in_month
        , sum(a) OVER (PARTITION BY subscription_id, calendar_month) AS a
        , sum(h) OVER (PARTITION BY subscription_id, calendar_month) AS h
        , sum(c) OVER (PARTITION BY subscription_id, calendar_month) AS c
        , sum(p) OVER (PARTITION BY subscription_id, calendar_month) AS p
        , sum(pc) OVER (PARTITION BY subscription_id, calendar_month) AS pc
    FROM
        subscription_churn_state_setup
    ORDER BY
        user_id, subscription_id, event_date, id DESC
;

DROP VIEW IF EXISTS subscription_churn_month_ends CASCADE;
CREATE VIEW subscription_churn_month_ends AS
    SELECT
        id, user_id, subscription_id, event, event_date, date_previous, calendar_month, calendar_month_next, old_state, new_state
        , current_state, days_in_current_state, days_in_month, a, h, c, p, pc
    FROM
        subscription_churn_state_sums
    WHERE
        calendar_month <> calendar_month_next
        OR calendar_month_next IS NULL
    ORDER BY
        user_id, subscription_id, event_date, id DESC
;

DROP VIEW IF EXISTS subscription_churn_stage0 CASCADE;
CREATE VIEW subscription_churn_stage0 AS
    SELECT
        id, user_id, subscription_id, event, event_date, date_previous, calendar_month, calendar_month_next, old_state, new_state
        , current_state, days_in_current_state, days_in_month, a, h, c, p, pc
        , CASE WHEN a > 0 THEN 1 END AS active
        , lag(CASE WHEN a > 0 THEN 1 END, 1) OVER (PARTITION BY subscription_id ORDER BY event_date, id DESC) AS active_lag
        , lead(CASE WHEN a > 0 THEN 1 END, 1) OVER (PARTITION BY subscription_id ORDER BY event_date, id DESC) AS active_lead
        , CASE WHEN (a > 0 AND (h > 0 OR c > 0 OR p > 0 OR pc >0)) THEN 1 END AS churn_danger
        , CASE WHEN current_state <> 'active' AND a > 0 THEN 1 END AS churn_prediction
    FROM
        subscription_churn_month_ends
    ORDER BY
        user_id, subscription_id, event_date, id DESC
;

DROP VIEW IF EXISTS subscription_churn_stage1 CASCADE;
CREATE VIEW subscription_churn_stage1 AS
    SELECT
          user_id, subscription_id, calendar_month, current_state, active, active_lag, active_lead, a, h, c, p, pc
        , sum(CASE WHEN active = 1 AND active_lag IS NULL THEN 1 END) ignore nulls OVER (PARTITION BY subscription_id ORDER BY subscription_id, calendar_month ROWS BETWEEN unbounded preceding AND CURRENT row) AS activated_count
        , CASE WHEN active = 1 AND active_lag IS NULL THEN 1 END AS activated
        , CASE WHEN active_lag = 1 AND active IS NULL THEN 1 END AS churned
        , churn_danger, churn_prediction
    FROM
        subscription_churn_stage0
    ORDER BY
        user_id, subscription_id, event_date, id DESC
;

DROP VIEW IF EXISTS subscription_churn_stage2 CASCADE;
CREATE VIEW subscription_churn_stage2 AS
    SELECT
        user_id, subscription_id, calendar_month, activated, active, churned
        , CASE WHEN activated = 1 AND activated_count > 1 THEN 1 END AS reactivated
        , churn_danger, churn_prediction
        /*
        , CASE WHEN churned = 1 AND current_state = 'pending' THEN 1 END AS churned_to_pending
        , CASE WHEN churned = 1 AND current_state = 'on-hold' THEN 1 END AS churned_to_on_hold
        , CASE WHEN churned = 1 AND current_state = 'cancelled' THEN 1 END AS churned_to_cancelled
        , CASE WHEN churned = 1 AND current_state = 'switched' THEN 1 END AS churned_to_switched
        , CASE WHEN churned = 1 AND current_state = 'expired' THEN 1 END AS churned_to_expired
        , CASE WHEN churned = 1 AND current_state = 'pending-cancel' THEN 1 END AS churned_to_pending_cancel
        */
    FROM
        subscription_churn_stage1
    ORDER BY
        user_id, subscription_id, calendar_month
;

DROP VIEW IF EXISTS subscription_churn_by_calendar_month CASCADE;
CREATE VIEW subscription_churn_by_calendar_month AS
    SELECT
          calendar_month
        , count(DISTINCT user_id) AS number_of_users
        , count(DISTINCT subscription_id) AS number_of_subs
        , sum(activated) AS activated
        , sum(active) AS active
        , CASE
            WHEN calendar_month = to_char(sysdate, 'YYYY-MM')
            THEN sum(churn_prediction)
            ELSE lead(sum(churned), 1) OVER (ORDER BY calendar_month)
          END AS churned
        , round(100.0 * CASE 
            WHEN calendar_month = to_char(sysdate, 'YYYY-MM')
            THEN sum(churn_prediction)
            ELSE lead(sum(churned), 1) OVER (ORDER BY calendar_month)
          END / sum(active))::INTEGER
          AS churn_pct
        , sum(reactivated) AS reactivated
        , sum(churn_danger) AS churn_danger
        , sum(churn_prediction) AS churn_prediction
    FROM 
        subscription_churn_stage2
    GROUP BY
        calendar_month
    ORDER BY
        calendar_month
;

DROP VIEW IF EXISTS subscription_churn_by_calendar_month_debug CASCADE;
CREATE VIEW subscription_churn_by_calendar_month_debug AS
    (
        SELECT * FROM subscription_churn_by_calendar_month
    )
    UNION
    (
        SELECT 
              '2016-10 (-total-)' AS calendar_month
            , max(number_of_users) AS number_of_users
            , max(number_of_subs) AS number_of_subs
            , sum(activated) AS activated
            , NULL AS active
            , sum(churned) AS churned
            , round(100.0 * sum(churned) / max(number_of_users))::INTEGER AS churn_pct
            , sum(reactivated) AS reactivated
        FROM
            subscription_churn_by_calendar_month
        GROUP BY
            1
    )
    UNION
    (
        SELECT
              to_char(sysdate, 'YYYY-MM (from subs table)') AS calendar_month
            , count(user_id) AS number_of_users
            , count(id) AS number_of_subs
            , NULL AS activated
            , count(CASE WHEN status = 'active' THEN 1 end) AS active
            , count(CASE WHEN status <> 'active' THEN 1 end) AS churned
            , round(100.0 * count(CASE WHEN status <> 'active' THEN 1 end) / count(user_id))::INTEGER AS churn_pct
            , NULL AS reactivated
        FROM
            subscriptions
        GROUP BY
        1
    )
    ORDER BY
        calendar_month
;

COMMIT;
