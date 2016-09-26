
# Development Setup

	# Start Wordpress DigitalOcean droplet (Ubuntu 14.04)

	# System requirements
	apt-get update
	apt-get upgrade
	apt-get installpt-get install htop dstat vim screen git curl htop dstat vim screen git
	apt-get install php5-cli php5-curl php5-pgsql
	apt-get install python-pip
	mysql_secure_installation

	# Composer
	curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

	# AWS cli
	pip install awscli
	aws configure

	# Restore wordpress install
	aws s3 ls challengebox-backup  # locate the newest file, or the one you want to restore
	aws s3 cp s3://challengebox-backup/ARCHIVE.tgz .
	tar --force-local -zxvf ARCHIVE.tgz
	mv var/www/box /var/www/YOUR_DEV_SITE_NAME
	rmdir var/www; rmdir var

	# Restore database
	aws s3 cp s3://challengebox-backup/SQL_ARCHIVE.sql.gz .
	echo create database fit_box; grant access mysql -u root -p 
	mysql -u root -p -e "create database dev; GRANT ALL PRIVILEGES ON dev.* TO dev@localhost IDENTIFIED BY '63YUevJAnNjuAKAPBTd'"
	zcat SQL_ARCHIVE.sql.gz | mysql -u root -p dev

	# Git repo
	ssh-keygen
	cat ~/.ssh/id_rsa.pub  # Add this to your github account (put a password on it if you're sharing the dev machine)
	git clone git@github.com:ChallengeBox/challengebox-plugin.git plugin
	cd plugin
	composer install
	
	# SSL creds (choose one)
	# 1) Self signed
	openssl req -config conf/self-sign.conf -new -x509 -sha256 -newkey rsa:2048 -nodes -keyout challengeboxdev_com.key -days 365 -out challengeboxdev_com.crt
	install -o root -g ssl-cert -m 640 challengeboxdev_com.key /etc/ssl/private/challengeboxdev_com.key && rm challengeboxdev_com.key
	install -o root -g ssl-cert -m 644 challengeboxdev_com.crt /etc/ssl/certs/challengeboxdev_com.crt && rm challengeboxdev_com.crt
	# 2) Authority sigend
	echo "# Paste private SSL key here" > KEY && vim KEY && install -o root -g ssl-cert -m 640 KEY /etc/ssl/private/getchallengebox_com.key && rm KEY
	echo "# Paste SSL cert here" > CERT && vim CERT && install -o root -g root -m 644 CERT /etc/ssl/certs/getchallengebox_com.crt && rm CERT
	echo "# Paste SSL trust chain bundle here" > BUNDLE && vim BUNDLE && install -o root -g root -m 644 BUNDLE /etc/ssl/certs/getchallengebox_com.ca-bundle && rm BUNDLE

	# Apache config
	cp ~/plugin/conf/apache.conf /etc/apache2/sites-available/YOUR_DEV_SITE_NAME.conf
	vim /etc/apache2/sites-available/YOUR_DEV_SITE_NAME.conf # Edit path, ports, etc.
	a2ensite YOUR_DEV_SITE_NAME

	# Adjustments to code
	cd /var/www/YOUR_DEV_SITE_NAME
	cp ~/plugin/conf/wp-config.conf .

	# Adjustements to database
	mysql -u root -p -e "UPDATE dev.wp_options SET option_value = 'https://challengeboxdev.com/' WHERE option_name in ('siteurl', 'home');"


