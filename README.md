
# Development Setup

	# Start Wordpress DigitalOcean droplet (Ubuntu 14.04)

	# System requirements
	apt-get update
	apt-get upgrade
	apt-get installpt-get install htop dstat vim screen git htop dstat vim screen git
	apt-get install php5-pgsql
	mysql_secure_installation
	
	# SSL creds
	echo "# Paste private SSL key here" > KEY && vim KEY && install -o root -g ssl-cert -m 640 KEY /etc/ssl/private/getchallengebox_com.key && rm KEY
	echo "# Paste SSL cert here" > CERT && vim CERT && install -o root -g root -m 644 CERT /etc/ssl/certs/getchallengebox_com.crt && rm CERT
	echo "# Paste SSL trust chain bundle here" > BUNDLE && vim BUNDLE && install -o root -g root -m 644 BUNDLE /etc/ssl/certs/getchallengebox_com.ca-bundle && rm BUNDLE
	
	# Apache config
	cp bin/apache.conf /etc/apache2/sites-available/YOUR_DEV_SITE_NAME.conf
	vim /etc/apache2/sites-available/YOUR_DEV_SITE_NAME.conf # Edit path, ports, etc.
	a2ensite YOUR_DEV_SITE_NAME

	# Restore data
	tar -zxvf ARCHIVE.TGZ /var/www/YOUR_DEV_SITE_NAME
	zcat SQL_ARCHIVE.sql.gz | mysql -u root -p fit_box
	
	# Setup git environment


	# Adjustments to code
	define( 'JETPACK_DEV_DEBUG', true);

	# Adjustements to database


