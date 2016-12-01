BEGIN;

DROP TABLE IF EXISTS `cb_analytics_monthly_analytics`;
CREATE TABLE `cb_analytics_monthly_analytics` ( 
	`calendar_month` VARCHAR(7) NOT NULL, 
	`boxes_created` BIGINT NOT NULL, 
	`boxes_shipped` BIGINT NOT NULL, 
	`shop_orders_created` BIGINT NOT NULL,
	`shop_orders_shipped` BIGINT NOT NULL,
	`charges_succeeded` BIGINT NOT NULL,
	`charges_failed` BIGINT NOT NULL,
	`total_amount_charged` DECIMAL(10,2) NOT NULL,
	`total_stripe_fee` DECIMAL(10,2) NOT NULL,
	`refunds_succeeded` BIGINT NOT NULL,
	`refunds_failed` BIGINT NOT NULL,
	`total_amount_refunded` DECIMAL(10,2) NOT NULL,
	`total_stripe_fee_refunded` DECIMAL(10,2) NOT NULL,
	`user_cancelled` BIGINT NOT NULL,
	`user_hold` BIGINT NOT NULL,
	`user_reactivated` BIGINT NOT NULL,
	`new_subscriptions` BIGINT NOT NULL,
	`net_revenue` DECIMAL(10,2) NOT NULL,
	`subs_reactivated` BIGINT NOT NULL,
	`subs_activated` BIGINT NOT NULL,
	`subs_active` BIGINT NOT NULL,
	`subs_churned` BIGINT NOT NULL,
	`subs_churn_pct` DECIMAL(10,2) NOT NULL,
	`box_count` BIGINT NOT NULL,
	`booked_revenue` DECIMAL(10,2) NOT NULL,
	`booked_revenue_per_box` DECIMAL(10,2) NOT NULL,
	`box_reactivated` BIGINT NOT NULL,
	`box_activated` BIGINT NOT NULL,
	`box_active` BIGINT NOT NULL,
	`box_churned` BIGINT NOT NULL,
	`box_churn_pct` DECIMAL(10,2) NOT NULL,
	`box_reactivated2` BIGINT NOT NULL,
	`box_activated2` BIGINT NOT NULL,
	`box_active2` BIGINT NOT NULL,
	`box_churned2` BIGINT NOT NULL,
	`box_churn_pct2` DECIMAL(10,2) NOT NULL
) ENGINE = InnoDB;

LOAD DATA
	INFILE '/tmp/s3/$bucket/from_redshift/monthly_analytics.csv.gz000'
	INTO TABLE `cb_analytics_monthly_analytics`
	COLUMNS TERMINATED BY ','
	OPTIONALLY ENCLOSED BY '"'
	LINES TERMINATED BY '\n'
;

COMMIT;
