BEGIN;

DROP VIEW IF EXISTS fitness_improvement_base CASCADE;
CREATE VIEW fitness_improvement_base AS
    SELECT
          id AS user_id
        , registration_date
    --    , to_date(activity_date || ' 00:00:00', 'YYYY-MM-DD') AS activity_date
        , activity_date
        , datediff('days', registration_date, to_date(activity_date || ' 00:00:00', 'YYYY-MM-DD')) AS days_since_join
        , datediff('weeks', registration_date, to_date(activity_date || ' 00:00:00', 'YYYY-MM-DD')) AS weeks_since_join
        , datediff('months', registration_date, to_date(activity_date || ' 00:00:00', 'YYYY-MM-DD')) AS months_since_join
        , CASE WHEN datediff('days', registration_date, to_date(activity_date || ' 00:00:00', 'YYYY-MM-DD')) < 0 THEN 'before' ELSE 'after' END AS before_or_after 
        , any_activity
        , medium_activity
        , heavy_activity
        , water
        , food
        , distance
        , steps
        , very_active
        , fairly_active
        , lightly_active
        , light_30
        , light_60
        , light_90
        , moderate_10
        , moderate_30
        , moderate_45
        , moderate_60
        , moderate_90
        , heavy_10
        , heavy_30
        , heavy_45
        , heavy_60
        , heavy_90
        , water_days
        , food_days
        , food_or_water_days
        , distance_1
        , distance_2
        , distance_3
        , distance_4
        , distance_5
        , distance_6
        , distance_8
        , distance_10
        , distance_15
        , steps_8k
        , steps_10k
        , steps_12k
        , steps_15k
        , wearing_fitbit
    FROM
        users JOIN fitness_data
    ON
        users.id = fitness_data.user_id
;

DROP VIEW IF EXISTS fitness_improvement_before_after CASCADE;
CREATE VIEW fitness_improvement_before_after AS
    SELECT
          user_id
        , before_or_after
        , sum(CASE WHEN wearing_fitbit > 0 THEN 1 ELSE 0 end) AS days_wearing_fitbit
        , sum(CASE WHEN wearing_fitbit <= 0 THEN 1 ELSE 0 end) AS days_without_fitbit
        , 100 * sum(CASE WHEN wearing_fitbit > 0 THEN 1 ELSE 0 end) / (sum(CASE WHEN wearing_fitbit > 0 THEN 1 ELSE 0 end) + sum(CASE WHEN wearing_fitbit <= 0 THEN 1 ELSE 0 end)) AS fitbit_wearing_pct
        , CASE WHEN sum(wearing_fitbit) > 0 THEN sum(any_activity) / sum(wearing_fitbit) ELSE 0 END AS minutes_active_per_day
        , CASE WHEN sum(wearing_fitbit) > 0 THEN sum(steps) / sum(wearing_fitbit) ELSE 0 END AS steps_per_day
        , CASE WHEN sum(wearing_fitbit) > 0 THEN sum(distance) / sum(wearing_fitbit) ELSE 0 END AS distance_per_day
        , CASE WHEN sum(wearing_fitbit) > 0 THEN 30 * sum(light_30) / sum(wearing_fitbit) ELSE 0 END AS light_30_per_day
        , CASE WHEN sum(wearing_fitbit) > 0 THEN 30 * sum(moderate_30) / sum(wearing_fitbit) ELSE 0 END AS moderate_30_per_day
        , CASE WHEN sum(wearing_fitbit) > 0 THEN 30 * sum(heavy_30) / sum(wearing_fitbit) ELSE 0 END AS heavy_30_per_day
    FROM
        fitness_improvement_base
    WHERE
        days_since_join BETWEEN -60 AND 60
    GROUP BY
        user_id
      , before_or_after
    ORDER BY
        user_id
      , before_or_after DESC
;

DROP VIEW IF EXISTS fitness_improvement_by_user CASCADE;
CREATE VIEW fitness_improvement_by_user AS
    SELECT
          before.user_id AS user_id
        , before.days_wearing_fitbit AS days_before
        , after.days_wearing_fitbit AS days_after
        , before.fitbit_wearing_pct AS fitbit_before
        , after.fitbit_wearing_pct AS fitbit_after
        , before.minutes_active_per_day::INTEGER AS minutes_before
        , after.minutes_active_per_day::INTEGER AS minutes_after
        , before.steps_per_day::INTEGER AS steps_before
        , after.steps_per_day::INTEGER AS steps_after
        , before.distance_per_day::NUMERIC(10,2) AS distance_before
        , after.distance_per_day::NUMERIC(10,2) AS distance_after
        , before.light_30_per_day::NUMERIC(10,2) AS light_30_before
        , after.light_30_per_day::NUMERIC(10,2) AS light_30_after
        , before.moderate_30_per_day::NUMERIC(10,2) AS moderate_30_before
        , after.moderate_30_per_day::NUMERIC(10,2) AS moderate_30_after
        , before.heavy_30_per_day::NUMERIC(10,2) AS heavy_30_before
        , after.heavy_30_per_day::NUMERIC(10,2) AS heavy_30_after
    FROM
        (SELECT * FROM fitness_improvement_before_after WHERE before_or_after = 'before') AS before
    JOIN
        (SELECT * FROM fitness_improvement_before_after WHERE before_or_after = 'after') AS after
    ON
        before.user_id = after.user_id
;

DROP VIEW IF EXISTS fitness_improvement_average CASCADE;
CREATE VIEW fitness_improvement_average AS
    SELECT
          count(*) AS sample_size
        , avg(CASE WHEN days_before > 0 AND days_after > 0 THEN days_before ELSE NULL END) AS days_before
        , avg(CASE WHEN days_before > 0 AND days_after > 0 THEN days_after ELSE NULL END) AS days_after
        , avg(CASE WHEN fitbit_before > 0 AND fitbit_after > 0 THEN fitbit_before ELSE NULL END) AS fitbit_before
        , avg(CASE WHEN fitbit_before > 0 AND fitbit_after > 0 THEN fitbit_after ELSE NULL END) AS fitbit_after
        , avg(CASE WHEN minutes_before > 0 AND minutes_after > 0 THEN minutes_before ELSE NULL END) AS minutes_before
        , avg(CASE WHEN minutes_before > 0 AND minutes_after > 0 THEN minutes_after ELSE NULL END) AS minutes_after
        , avg(CASE WHEN steps_before > 0 AND steps_after > 0 THEN steps_before ELSE NULL END) AS steps_before
        , avg(CASE WHEN steps_before > 0 AND steps_after > 0 THEN steps_after ELSE NULL END) AS steps_after
        , avg(CASE WHEN distance_before > 0 AND distance_after > 0 THEN distance_before ELSE NULL END) AS distance_before
        , avg(CASE WHEN distance_before > 0 AND distance_after > 0 THEN distance_after ELSE NULL END) AS distance_after
        , avg(CASE WHEN light_30_before > 0 AND light_30_after > 0 THEN light_30_before ELSE NULL END) AS light_30_before
        , avg(CASE WHEN light_30_before > 0 AND light_30_after > 0 THEN light_30_after ELSE NULL END) AS light_30_after
        , avg(CASE WHEN moderate_30_before > 0 AND moderate_30_after > 0 THEN moderate_30_before ELSE NULL END) AS moderate_30_before
        , avg(CASE WHEN moderate_30_before > 0 AND moderate_30_after > 0 THEN moderate_30_after ELSE NULL END) AS moderate_30_after
        , avg(CASE WHEN heavy_30_before > 0 AND heavy_30_after > 0 THEN heavy_30_before ELSE NULL END) AS heavy_30_before
        , avg(CASE WHEN heavy_30_before > 0 AND heavy_30_after > 0 THEN heavy_30_after ELSE NULL END) AS heavy_30_after
    FROM fitness_improvement_by_user
;

COMMIT;