#!/bin/bash
set -eo pipefail
[[ "$TRACE" ]] && set -x 

while true; do
	cd /var/www/box/wp-content/plugins/challengebox; sudo -u www-data wp cb challenge_bot --debug 1>>/var/log/challenge_bot.log 2>> /var/log/challenge_bot.log
	sleep 1
done
