CREATE TABLE `wp_raw_tracking_data` (
  `user_id` bigint(20) NOT NULL,
  `date` date NOT NULL,
  `source` char(64) NOT NULL,
  `data` text NOT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modified` timestamp NOT NULL -- DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `wp_raw_tracking_data` 
ADD INDEX (`user_id`);
