UNLOAD
	('SELECT * FROM $table')
	TO 's3://$bucket/from_redshift/$table.csv.gz'
	CREDENTIALS 'aws_iam_role=arn:aws:iam::150598675937:role/RedshiftCopyUnload'
	DELIMITER AS ','
	NULL AS ''
	ALLOWOVERWRITE
	PARALLEL OFF;
