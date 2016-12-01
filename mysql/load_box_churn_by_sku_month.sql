BEGIN;

DROP TABLE IF EXISTS `cb_analytics_box_churn_by_sku_month`;
CREATE TABLE `cb_analytics_box_churn_by_sku_month` ( 
	`sku_month` VARCHAR(7) NOT NULL,
	`box_count` BIGINT NOT NULL,
	`booked_revenue` DECIMAL(10,2) NOT NULL,
	`ideal_revenue` DECIMAL(10,2) NOT NULL,
	`todate_revenue` DECIMAL(10,2) NOT NULL,
	`booked_revenue_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_revenue_per_box` DECIMAL(10,2) NOT NULL,
	`todate_revenue_per_box` DECIMAL(10,2) NOT NULL,
	`booked_price_items_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_price_items_per_box` DECIMAL(10,2) NOT NULL,
	`todate_price_items_per_box` DECIMAL(10,2) NOT NULL,
	`booked_price_ship_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_price_ship_per_box` DECIMAL(10,2) NOT NULL,
	`todate_price_ship_per_box` DECIMAL(10,2) NOT NULL,
	`booked_price_rush_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_price_rush_per_box` DECIMAL(10,2) NOT NULL,
	`todate_price_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_charge_count_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_charge_count_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_charge_count_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_charge_gross_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_charge_gross_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_charge_gross_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_charge_net_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_charge_net_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_charge_net_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_charge_fees_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_charge_fees_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_charge_fees_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_refund_count_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_refund_count_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_refund_count_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_refund_gross_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_refund_gross_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_refund_gross_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_refund_fees_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_refund_fees_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_refund_fees_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_refund_net_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_refund_net_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_refund_net_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_fees_net_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_fees_net_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_fees_net_per_box` DECIMAL(10,2) NOT NULL,
	`booked_stripe_net_per_box` DECIMAL(10,2) NOT NULL,
	`ideal_stripe_net_per_box` DECIMAL(10,2) NOT NULL,
	`todate_stripe_net_per_box` DECIMAL(10,2) NOT NULL,
	`reactivated` BIGINT NOT NULL,
	`activated` BIGINT NOT NULL,
	`active` BIGINT NOT NULL,
	`churned` BIGINT NOT NULL,
	`churn_pct` DECIMAL(10,2) NOT NULL,
	`reactivated2` BIGINT NOT NULL,
	`activated2` BIGINT NOT NULL,
	`active2` BIGINT NOT NULL,
	`churned2` BIGINT NOT NULL,
	`churn_pct2` DECIMAL(10,2) NOT NULL
) ENGINE = InnoDB;

LOAD DATA
	INFILE '/tmp/s3/$bucket/from_redshift/box_churn_by_sku_month.csv.gz000'
	INTO TABLE `cb_analytics_box_churn_by_sku_month`
	COLUMNS TERMINATED BY ','
	OPTIONALLY ENCLOSED BY '"'
	LINES TERMINATED BY '\n'
;

COMMIT;
