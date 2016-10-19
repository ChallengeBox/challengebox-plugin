CREATE TABLE IF NOT EXISTS shop_orders (id INT8 NOT NULL);

ALTER TABLE shop_orders RENAME TO shop_orders_old;

CREATE TABLE shop_orders (
	  id INT8 NOT NULL
	, user_id INT8 NOT NULL
	, status VARCHAR(16) DEFAULT NULL
	, created_date TIMESTAMP NOT NULL
	, completed_date TIMESTAMP NOT NULL
	, skus VARCHAR(1024) DEFAULT NULL
	, total DECIMAL(10,2) DEFAULT '0.0'
	, revenue_items DECIMAL(10,2) DEFAULT '0.0'
	, revenue_ship DECIMAL(10,2) DEFAULT '0.0'
	, revenue_rush DECIMAL(10,2) DEFAULT '0.0'
-- , refund DECIMAL(10,2) DEFAULT '0.0'
	, primary key(id)
--  , foreign key(user_id) references users(id)
	)
DISTKEY(user_id)
SORTKEY(user_id,id);

COPY shop_orders FROM 's3://$bucket/command_results/shop_orders.csv.gz' 
	CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
	CSV IGNOREHEADER AS 1 NULL AS '' TIMEFORMAT 'auto' GZIP;
