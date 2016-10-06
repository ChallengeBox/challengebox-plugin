CREATE TABLE IF NOT EXISTS subscriptions (id INT8 NOT NULL);

ALTER TABLE subscriptions RENAME TO subscriptions_old;

CREATE TABLE subscriptions (
	  id INT8 NOT NULL
	, user_id INT8 NOT NULL
	, status VARCHAR(32) DEFAULT NULL
	, period VARCHAR(16) DEFAULT NULL
	, interval INT8 NOT NULL
	, start_date TIMESTAMP NOT NULL
	, end_date TIMESTAMP DEFAULT NULL
	, next_payment_date TIMESTAMP DEFAULT NULL
	-- , foreign key(user_id) references users(id)
)
DISTKEY(user_id)
SORTKEY(user_id, id);

COPY subscriptions FROM 's3://$bucket/command_results/subscriptions.csv.gz' 
CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
CSV IGNOREHEADER AS 1 NULL AS '' TIMEFORMAT 'auto' GZIP;

DROP TABLE IF EXISTS subscriptions_old;
