CREATE TABLE `cb_fitness_data` ( 
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

ALTER TABLE `cb_fitness_data` 
ADD UNIQUE (`user_id`, `date`);

ALTER TABLE `cb_fitness_data` 
ADD `light_30` BOOLEAN NOT NULL AFTER `lightly_active`, 
ADD `light_60` BOOLEAN NOT NULL AFTER `light_30`, 
ADD `light_90` BOOLEAN NOT NULL AFTER `light_60`, 
ADD `moderate_10` BOOLEAN NOT NULL AFTER `light_90`, 
ADD `moderate_30` BOOLEAN NOT NULL AFTER `moderate_10`, 
ADD `moderate_45` BOOLEAN NOT NULL AFTER `moderate_30`, 
ADD `moderate_60` BOOLEAN NOT NULL AFTER `moderate_45`, 
ADD `moderate_90` BOOLEAN NOT NULL AFTER `moderate_60`, 
ADD `heavy_10` BOOLEAN NOT NULL AFTER `moderate_90`, 
ADD `heavy_30` BOOLEAN NOT NULL AFTER `heavy_10`, 
ADD `heavy_45` BOOLEAN NOT NULL AFTER `heavy_30`, 
ADD `heavy_60` BOOLEAN NOT NULL AFTER `heavy_45`, 
ADD `heavy_90` BOOLEAN NOT NULL AFTER `heavy_60`, 
ADD `water_days` BOOLEAN NOT NULL AFTER `heavy_90`, 
ADD `food_days` BOOLEAN NOT NULL AFTER `water_days`, 
ADD `food_or_water_days` BOOLEAN NOT NULL AFTER `food_days`, 
ADD `distance_1` BOOLEAN NOT NULL AFTER `food_or_water_days`, 
ADD `distance_2` BOOLEAN NOT NULL AFTER `distance_1`, 
ADD `distance_3` BOOLEAN NOT NULL AFTER `distance_2`, 
ADD `distance_4` BOOLEAN NOT NULL AFTER `distance_3`, 
ADD `distance_5` BOOLEAN NOT NULL AFTER `distance_4`, 
ADD `distance_6` BOOLEAN NOT NULL AFTER `distance_5`, 
ADD `distance_8` BOOLEAN NOT NULL AFTER `distance_6`, 
ADD `distance_10` BOOLEAN NOT NULL AFTER `distance_8`, 
ADD `distance_15` BOOLEAN NOT NULL AFTER `distance_10`, 
ADD `steps_8k` BOOLEAN NOT NULL AFTER `distance_15`, 
ADD `steps_10k` BOOLEAN NOT NULL AFTER `steps_8k`, 
ADD `steps_12k` BOOLEAN NOT NULL AFTER `steps_10k`, 
ADD `steps_15k` BOOLEAN NOT NULL AFTER `steps_12k`, 
ADD `wearing_fitbit` BOOLEAN NOT NULL AFTER `steps_15k`;