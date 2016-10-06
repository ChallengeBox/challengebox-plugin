
CREATE OR REPLACE VIEW subscription_status_by_start_month AS
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
		subscriptions
	GROUP BY
		to_char(start_date, 'YYYY-MM')
	ORDER BY
		to_char(start_date, 'YYYY-MM')
;
