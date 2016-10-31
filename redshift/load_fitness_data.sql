CREATE TABLE IF NOT EXISTS fitness_data (id INT8 NOT NULL);

ALTER TABLE fitness_data RENAME TO fitness_data_old;

CREATE TABLE fitness_data (
	  user_id INT8 NOT NULL
	, activity_date VARCHAR(10) NOT NULL
	, any_activity NUMERIC(10,2) DEFAULT '0.0'
	, medium_activity NUMERIC(10,2) DEFAULT '0.0'
	, heavy_activity NUMERIC(10,2) DEFAULT '0.0'
	, water NUMERIC(10,2) DEFAULT '0.0'
	, food NUMERIC(10,2) DEFAULT '0.0'
	, distance NUMERIC(10,2) DEFAULT '0.0'
	, steps NUMERIC(10,2) DEFAULT '0.0'
	, very_active NUMERIC(10,2) DEFAULT '0.0'
	, fairly_active NUMERIC(10,2) DEFAULT '0.0'
	, lightly_active NUMERIC(10,2) DEFAULT '0.0'
	, light_30 NUMERIC(10,2) DEFAULT '0.0'
	, light_60 NUMERIC(10,2) DEFAULT '0.0'
	, light_90 NUMERIC(10,2) DEFAULT '0.0'
	, moderate_10 NUMERIC(10,2) DEFAULT '0.0'
	, moderate_30 NUMERIC(10,2) DEFAULT '0.0'
	, moderate_45 NUMERIC(10,2) DEFAULT '0.0'
	, moderate_60 NUMERIC(10,2) DEFAULT '0.0'
	, moderate_90 NUMERIC(10,2) DEFAULT '0.0'
	, heavy_10 NUMERIC(10,2) DEFAULT '0.0'
	, heavy_30 NUMERIC(10,2) DEFAULT '0.0'
	, heavy_45 NUMERIC(10,2) DEFAULT '0.0'
	, heavy_60 NUMERIC(10,2) DEFAULT '0.0'
	, heavy_90 NUMERIC(10,2) DEFAULT '0.0'
	, water_days NUMERIC(10,2) DEFAULT '0.0'
	, food_days NUMERIC(10,2) DEFAULT '0.0'
	, food_or_water_days NUMERIC(10,2) DEFAULT '0.0'
	, distance_1 NUMERIC(10,2) DEFAULT '0.0'
	, distance_2 NUMERIC(10,2) DEFAULT '0.0'
	, distance_3 NUMERIC(10,2) DEFAULT '0.0'
	, distance_4 NUMERIC(10,2) DEFAULT '0.0'
	, distance_5 NUMERIC(10,2) DEFAULT '0.0'
	, distance_6 NUMERIC(10,2) DEFAULT '0.0'
	, distance_8 NUMERIC(10,2) DEFAULT '0.0'
	, distance_10 NUMERIC(10,2) DEFAULT '0.0'
	, distance_15 NUMERIC(10,2) DEFAULT '0.0'
	, steps_8k NUMERIC(10,2) DEFAULT '0.0'
	, steps_10k NUMERIC(10,2) DEFAULT '0.0'
	, steps_12k NUMERIC(10,2) DEFAULT '0.0'
	, steps_15k NUMERIC(10,2) DEFAULT '0.0'
	, wearing_fitbit NUMERIC(10,2) DEFAULT '0.0'
	, create_date TIMESTAMP DEFAULT NULL
	, last_modified TIMESTAMP DEFAULT NULL
--  , foreign key(user_id) references users(id)
	)
DISTKEY(user_id)
SORTKEY(user_id, activity_date);

COPY fitness_data FROM 's3://$bucket/command_results/fitness_data/' 
	CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
	CSV IGNOREHEADER AS 1 NULL AS '' TIMEFORMAT 'auto' GZIP;
