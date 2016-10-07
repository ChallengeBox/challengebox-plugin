CREATE TABLE IF NOT EXISTS subscription_events (id INT8 DEFAULT NULL);

ALTER TABLE subscription_events RENAME TO subscription_events_old;

CREATE TABLE subscription_events (
	  id INT8 DEFAULT NULL -- some ids are falsely inserted to record initial state
	, subscription_id INT8 NOT NULL
	, user_id INT8 NOT NULL
	, event VARCHAR(32) DEFAULT NULL
	, event_date TIMESTAMP NOT NULL
	, old_state VARCHAR(64) DEFAULT NULL
	, new_state VARCHAR(64) DEFAULT NULL
	, comment VARCHAR(1024) DEFAULT NULL
	-- , primary key(id)
	-- , foreign key(sub_id) references subscriptions(id)
	-- , foreign key(user_id) references users(id)
)
DISTKEY(user_id)
SORTKEY(user_id, subscription_id, event_date, id);

COPY subscription_events FROM 's3://$bucket/command_results/subscription_events.csv.gz' 
	CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
	CSV IGNOREHEADER AS 1 NULL AS '' TIMEFORMAT 'auto' GZIP;
