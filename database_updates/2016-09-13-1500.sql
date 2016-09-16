CREATE TABLE `raw_tracking_data` 
( 
	`user_id` BIGINT NOT NULL , 
	`date` DATE NOT NULL , 
	`source` CHAR(64) NOT NULL , 
	`data` TEXT NOT NULL , 
	`create_date` TIMESTAMP NOT NULL , 
	`last_modified` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL , 
	UNIQUE KEY (`user_id`, `date`, `source`)
) 
ENGINE = InnoDB;

ALTER TABLE `raw_tracking_data` 
ADD INDEX (`user_id`);