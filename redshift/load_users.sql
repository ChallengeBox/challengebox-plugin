CREATE TABLE IF NOT EXISTS users (id INT8 NOT NULL);

ALTER TABLE users RENAME TO users_old;

CREATE TABLE users (
	  id INT8 NOT NULL
	, registration_date TIMESTAMP NOT NULL
	, subscription_status VARCHAR(16) DEFAULT NULL
	, subscription_type VARCHAR(16) DEFAULT NULL
	, clothing_gender VARCHAR(6) DEFAULT NULL
	, tshirt_size VARCHAR(16) DEFAULT NULL
	, pant_size VARCHAR(16) DEFAULT NULL
	, glove_size VARCHAR(16) DEFAULT NULL
	, sock_size VARCHAR(16) DEFAULT NULL
	, challenge_points INT8 NOT NULL
	, fitbit_oauth_status VARCHAR(16) DEFAULT NULL
	, fitness_goal VARCHAR(16) DEFAULT NULL
	, special_segment VARCHAR(64) DEFAULT NULL
	, has_rush_order INT8 DEFAULT NULL
	, has_failed_order INT8 DEFAULT NULL
	, primary key(id)
)
DISTKEY(id)
SORTKEY(id);

COPY users FROM 's3://$bucket/command_results/users.csv.gz' 
	CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
	CSV IGNOREHEADER AS 1 NULL AS '' TIMEFORMAT 'auto' GZIP;
