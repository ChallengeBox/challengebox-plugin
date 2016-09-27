
# Development Setup

	# Start DigitalOcean droplet (Ubuntu 14.04 x64)

	# System requirements
	apt-get update
	apt-get upgrade -y
	apt-get install -y apache2 php5-mysql mysql-server libapache2-mod-php5 php5-mcrypt php5-gd php5-curl php5-oauth # LAMP
	apt-get install -y htop dstat vim screen git curl htop dstat vim screen git # dev stuff
	apt-get install -y php5-cli php5-curl php5-pgsql # redshift reqs
	apt-get install -y python-pip # python required for aws cli
	mysql_secure_installation
	
	# Finish setting up LAMP stack
	sed -i -e "s/index.html index.cgi index.pl index.php/index.php index.html index.cgi index.pl/" /etc/apache2/mods-enabled/dir.conf
	service apache2 restart

	# Composer
	curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

	# AWS cli
	pip install awscli
	aws configure

	# Restore wordpress install
	aws s3 ls s3://challengebox-backup/clean_  # locate the newest file, or the one you want to restore
	aws s3 cp s3://challengebox-backup/clean_ARCHIVE.tgz .
	tar --force-local -zxvf clean_ARCHIVE.tgz
	mv var/www/box /var/www/dev
	rmdir var/www; rmdir var

	# Restore database
	aws s3 cp s3://challengebox-backup/clean_SQL_ARCHIVE.sql.gz .
	mysql -u root -p -e "create database dev; GRANT ALL PRIVILEGES ON dev.* TO dev@localhost IDENTIFIED BY 'SOME_PASSWORD'"
	zcat clean_SQL_ARCHIVE.sql.gz | mysql -u root -p dev

	# Git repo
	ssh-keygen # USE A PASSWORD IF YOU ARE SHARING THE MACHINE
	cat ~/.ssh/id_rsa.pub  # Add this to your github account at https://github.com/settings/ssh
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
	cp ~/plugin/conf/apache.conf /etc/apache2/sites-available/dev.conf
	vim /etc/apache2/sites-available/dev.conf # replace challengeboxdev.com with YOURNAME.challengeboxdev.com
	a2enmod ssl
	a2enmod rewrite
	a2dissite 000-default
	a2ensite dev

	# Adjustments to code
	cd /var/www/dev
	cp wp-config-sample.php wp-config.php
	vim wp-config.php # setup database variables, set define('WP_DEBUG', true); etc.
	rm -rf /var/www/dev/wp-content/plugins/challengebox
	cp -r /root/plugin /var/www/dev/wp-content/plugins/challengebox

	# Adjustements to database
	mysql -u root -p -e "UPDATE dev.wp_options SET option_value = 'https://YOURNAME.challengeboxdev.com/' WHERE option_name in ('siteurl', 'home');"
	
	# Try it out!
	service apache2 restart
	open https://YOURNAME.challengeboxdev.com/

	# Add WP Client
	cd
	curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	chmod +x wp-cli.phar
	cp wp-cli.phar /usr/local/bin/wp
