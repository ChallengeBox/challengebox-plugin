CREATE TABLE `wp_aggregate_tracking_data` ( 
`user_id` BIGINT NOT NULL , 
`date` DATE NOT NULL , 
`any_activity` DECIMAL(12,2) NULL DEFAULT NULL , 
`medium_activity` DECIMAL(12,2) NULL DEFAULT NULL , 
`heavy_activity` DECIMAL(12,2) NULL DEFAULT NULL , 
`water` DECIMAL(12,2) NULL DEFAULT NULL , 
`food` DECIMAL(12,2) NULL DEFAULT NULL , 
`distance` DECIMAL(12,2) NULL DEFAULT NULL , 
`steps` DECIMAL(12,2) NULL DEFAULT NULL , 
`very_active` DECIMAL(12,2) NULL DEFAULT NULL , 
`fairly_active` DECIMAL(12,2) NULL DEFAULT NULL ,
`lightly_active` DECIMAL(12,2) NULL DEFAULT NULL , 
`create_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , 
`last_modified` TIMESTAMP -- on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP 
) ENGINE = InnoDB;

ALTER TABLE `wp_aggregate_tracking_data` 
ADD UNIQUE (`user_id`, `date`);
