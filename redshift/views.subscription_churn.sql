CREATE OR REPLACE VIEW months AS
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


CREATE OR REPLACE VIEW subscription_churn_calendar_backdrop AS
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

CREATE OR REPLACE VIEW subscription_churn_base AS
    SELECT 
          id
        , user_id
        , subscription_id
        , lead(user_id, 1) OVER (ORDER BY user_id, subscription_id, event_date, id) AS user_next
        , event
        , event_date
        , lag(event_date, 1) OVER (ORDER BY user_id, subscription_id, event_date, id) AS date_previous
        , to_char(event_date, 'YYYY-MM') AS calendar_month
        , lead(to_char(event_date, 'YYYY-MM'), 1) OVER (ORDER BY user_id, subscription_id, event_date, id) AS calendar_month_next
        , old_state
        , new_state
        , CASE WHEN new_state IS NOT NULL THEN new_state ELSE lag(new_state, 1) ignore nulls OVER (ORDER BY user_id, subscription_id, event_date, id) END AS current_state
        , comment
    FROM
       (SELECT * FROM subscription_events UNION SELECT * FROM subscription_churn_calendar_backdrop)
    ORDER BY
        user_id, subscription_id, event_date, id
;

CREATE OR REPLACE VIEW subscription_churn_month_ends AS
    SELECT
     --   id, user_id, subscription_id, event_date, calendar_month, old_state, current_state, comment
          user_id, subscription_id, calendar_month, current_state
        , CASE WHEN current_state = 'active' THEN 1 END AS active
        , lag(CASE WHEN current_state = 'active' THEN 1 END, 1) OVER (PARTITION BY user_id, subscription_id ORDER BY user_id, subscription_id, event_date, id) AS active_lag
        , lead(CASE WHEN current_state = 'active' THEN 1 END, 1)  OVER (PARTITION BY user_id, subscription_id ORDER BY user_id, subscription_id, event_date, id) AS active_lead
    FROM
        subscription_churn_base
    WHERE
     --   subscription_id = 2727
        calendar_month <> calendar_month_next
    ORDER BY
        user_id, subscription_id, event_date, id

;

CREATE OR REPLACE VIEW subscription_churn_stage1 AS
    SELECT
          user_id, subscription_id, calendar_month, current_state, active, active_lag, active_lead
        , sum(CASE WHEN active = 1 AND active_lag IS NULL THEN 1 END) ignore nulls OVER (PARTITION BY user_id, subscription_id ORDER BY user_id, subscription_id, calendar_month ROWS BETWEEN unbounded preceding AND CURRENT row) AS activated_count
        , CASE WHEN active = 1 AND active_lag IS NULL THEN 1 END AS activated
        , CASE WHEN active_lag = 1 AND active IS NULL THEN 1 END AS churned
    FROM
        subscription_churn_month_ends
;
CREATE OR REPLACE VIEW subscription_churn_stage2 AS
    SELECT
        user_id, subscription_id, calendar_month, activated, active, churned
        , CASE WHEN activated = 1 AND activated_count > 1 THEN 1 END AS reactivated
        , CASE WHEN churned = 1 AND current_state = 'pending' THEN 1 END AS churned_to_pending
        , CASE WHEN churned = 1 AND current_state = 'on-hold' THEN 1 END AS churned_to_on_hold
        , CASE WHEN churned = 1 AND current_state = 'cancelled' THEN 1 END AS churned_to_cancelled
        , CASE WHEN churned = 1 AND current_state = 'switched' THEN 1 END AS churned_to_switched
        , CASE WHEN churned = 1 AND current_state = 'expired' THEN 1 END AS churned_to_expired
        , CASE WHEN churned = 1 AND current_state = 'pending-cancel' THEN 1 END AS churned_to_pending_cancel
    FROM
        subscription_churn_stage1
;

CREATE OR REPLACE VIEW subscription_churn_by_calendar_month AS
    SELECT
          calendar_month
        , count(user_id) AS number_of_users
        , count(subscription_id) AS number_of_subs
        , sum(activated) AS activated
        , sum(active) AS active
        , sum(churned) AS churned
        , round(100.0 * sum(churned) / sum(active))::INTEGER AS churn_pct
        , sum(reactivated) AS reactivated
        , sum(churned_to_pending) AS churned_to_pending
        , sum(churned_to_on_hold) AS churned_to_on_hold
        , sum(churned_to_cancelled) AS churned_to_cancelled
        , sum(churned_to_switched) AS churned_to_switched
        , sum(churned_to_expired) AS churned_to_expired
        , sum(churned_to_pending_cancel) AS churned_to_pending_cancel
    FROM 
        subscription_churn_stage2
    GROUP BY
        calendar_month
    ORDER BY
        calendar_month
;

CREATE OR REPLACE VIEW subscription_churn_by_calendar_month_debug AS
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
        , sum(churned_to_pending) AS churned_to_pending
        , sum(churned_to_on_hold) AS churned_to_on_hold
        , sum(churned_to_cancelled) AS churned_to_cancelled
        , sum(churned_to_switched) AS churned_to_switched
        , sum(churned_to_expired) AS churned_to_expired
        , sum(churned_to_pending_cancel) AS churned_to_pending_cancel
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
        , count(CASE WHEN status = 'pending' THEN 1 end) AS churned_to_pending
        , count(CASE WHEN status = 'on-hold' THEN 1 end) AS churned_to_on_hold
        , count(CASE WHEN status = 'cancelled' THEN 1 end) AS churned_to_cancelled
        , count(CASE WHEN status = 'switched' THEN 1 end) AS churned_to_switched
        , count(CASE WHEN status = 'expired' THEN 1 end) AS churned_to_expired
        , count(CASE WHEN status = 'pending-cancel' THEN 1 end) AS churned_to_pending_cancel
    FROM
        subscriptions
    GROUP BY
    1
)
ORDER BY
    calendar_month
;
