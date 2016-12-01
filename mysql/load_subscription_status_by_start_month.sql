BEGIN;

DROP TABLE IF EXISTS `cb_analytics_subscription_status_by_start_month`;
CREATE TABLE `cb_analytics_subscription_status_by_start_month` ( 
	`start_month` VARCHAR(7) NOT NULL , 
	`total` BIGINT NOT NULL, 
	`active` BIGINT NOT NULL, 
	`pending` BIGINT NOT NULL, 
	`pending_cancel` BIGINT NOT NULL, 
	`cancelled` BIGINT NOT NULL, 
	`switched` BIGINT NOT NULL, 
	`expired` BIGINT NOT NULL, 
	`on_hold` BIGINT NOT NULL
) ENGINE = InnoDB;

LOAD DATA
	INFILE '/tmp/s3/$bucket/from_redshift/subscription_status_by_start_month.csv.gz000'
	INTO TABLE `cb_analytics_subscription_status_by_start_month`
	COLUMNS TERMINATED BY ','
	OPTIONALLY ENCLOSED BY '"'
	LINES TERMINATED BY '\n'
;

COMMIT;
