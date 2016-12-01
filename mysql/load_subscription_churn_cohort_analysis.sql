BEGIN;

DROP TABLE IF EXISTS `cb_analytics_subscription_churn_cohort_analysis`;
CREATE TABLE `cb_analytics_subscription_churn_cohort_analysis` ( 
	`calendar_month` VARCHAR(7) NOT NULL , 
	`month_activated` VARCHAR(7) NOT NULL , 
	`activated` BIGINT NOT NULL, 
	`active` BIGINT NOT NULL, 
	`churned` BIGINT NOT NULL, 
	`reactivated` BIGINT NOT NULL, 
	`churn_danger` BIGINT NOT NULL, 
	`churn_prediction` BIGINT NOT NULL 
) ENGINE = InnoDB;

LOAD DATA
	INFILE '/tmp/s3/$bucket/from_redshift/subscription_churn_cohort_analysis.csv.gz000'
	INTO TABLE `cb_analytics_subscription_churn_cohort_analysis`
	COLUMNS TERMINATED BY ','
	OPTIONALLY ENCLOSED BY '"'
	LINES TERMINATED BY '\n'
;

COMMIT;

