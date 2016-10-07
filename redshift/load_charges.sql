CREATE TABLE IF NOT EXISTS charges (id VARCHAR(32) NOT NULL);

ALTER TABLE charges RENAME TO charges_old;

CREATE TABLE charges (
	  id VARCHAR(32) NOT NULL
	, order_id INT8 DEFAULT NULL
	, user_id INT8 DEFAULT NULL
	, customer_id VARCHAR(32) DEFAULT ''
	, charge_date TIMESTAMP NOT NULL
	, status VARCHAR(16) NOT NULL
	, description VARCHAR(1024) DEFAULT NULL
	, paid INT2 NOT NULL DEFAULT 0
	, captured INT2 NOT NULL DEFAULT 0
	, refunded INT2 NOT NULL DEFAULT 0
	, disputed INT2 NOT NULL DEFAULT 0
	, amount DECIMAL(10,2) DEFAULT '0.0'
	, stripe_fee DECIMAL(10,2) DEFAULT '0.0'
	, amount_refunded DECIMAL(10,2) DEFAULT '0.0'
	, failure_code VARCHAR(32) DEFAULT NULL
	, failure_message VARCHAR(1024) DEFAULT NULL
	, has_fraud_details INT2 NOT NULL DEFAULT 0
	, receipt_email VARCHAR(1024) DEFAULT NULL
	, receipt_number VARCHAR(16) DEFAULT NULL
	, primary key(id)
)
DISTKEY(user_id)
SORTKEY(charge_date);

COPY charges FROM 's3://$bucket/command_results/charges.csv.gz' 
	CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
	CSV IGNOREHEADER AS 1 NULL AS '' TIMEFORMAT 'auto' GZIP;
