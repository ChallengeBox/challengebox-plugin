CREATE TABLE IF NOT EXISTS renewal_orders (id INT8 NOT NULL);

ALTER TABLE renewal_orders RENAME TO renewal_orders_old;

CREATE TABLE renewal_orders (
	  id INT8 NOT NULL
	, user_id INT8 NOT NULL
	, status VARCHAR(16) DEFAULT NULL
	, created_date TIMESTAMP NOT NULL
	, completed_date TIMESTAMP NOT NULL
	, sku VARCHAR(1024) DEFAULT NULL
	, box_credits INT8 NOT NULL
	, total DECIMAL(10,2) DEFAULT '0.0'
	, revenue_items DECIMAL(10,2) DEFAULT '0.0'
	, revenue_ship DECIMAL(10,2) DEFAULT '0.0'
	, revenue_rush DECIMAL(10,2) DEFAULT '0.0'
-- , refund DECIMAL(10,2) DEFAULT '0.0'
	, primary key(id)
-- , foreign key(user_id) references users(id)
)
DISTKEY(user_id)
SORTKEY(user_id,id);

COPY renewal_orders FROM 's3://$bucket/command_results/renewal_orders.csv.gz' 
CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
CSV IGNOREHEADER AS 1 NULL AS '' TIMEFORMAT 'auto' GZIP;

DROP TABLE IF EXISTS renewal_orders_old;

