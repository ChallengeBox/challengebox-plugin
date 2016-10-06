CREATE TABLE IF NOT EXISTS refunds (id VARCHAR(32) NOT NULL);

ALTER TABLE refunds RENAME TO refunds_old;

CREATE TABLE refunds (
	id VARCHAR(32) NOT NULL
	, order_id INT8 DEFAULT NULL
	, user_id INT8 DEFAULT NULL
	, refund_date TIMESTAMP NOT NULL
	, amount DECIMAL(10,2) DEFAULT '0.0'
	, charge_id VARCHAR(32) NOT NULL
	, reason VARCHAR(1024) DEFAULT NULL
	, receipt_number VARCHAR(32) DEFAULT NULL
	, status VARCHAR(16) NOT NULL
	, primary key(id)
)
DISTKEY(user_id)
SORTKEY(refund_date);

COPY refunds FROM 's3://$bucket/command_results/refunds.csv.gz' 
CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
CSV IGNOREHEADER AS 1 NULL AS '' TIMEFORMAT 'auto' GZIP;

DROP TABLE IF EXISTS refunds_old;
