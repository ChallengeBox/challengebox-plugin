#!/bin/bash
set -eo pipefail
[[ "$TRACE" ]] && set -x

cd /var/www/box/wp-content/plugins/challengebox

# Export to s3
/var/www/box/wp-content/plugins/challengebox/bin/slack-command.sh sudo -u www-data wp cb export_users --all --redshift --redshift-upload-only
/var/www/box/wp-content/plugins/challengebox/bin/slack-command.sh sudo -u www-data wp cb export_subscriptions --all --redshift --redshift-upload-only
/var/www/box/wp-content/plugins/challengebox/bin/slack-command.sh sudo -u www-data wp cb export_subscription_events --all --redshift --redshift-upload-only
/var/www/box/wp-content/plugins/challengebox/bin/slack-command.sh sudo -u www-data wp cb export_orders --all --redshift  --redshift-upload-only
/var/www/box/wp-content/plugins/challengebox/bin/slack-command.sh sudo -u www-data wp cb export_refunds --redshift --redshift-upload-only
/var/www/box/wp-content/plugins/challengebox/bin/slack-command.sh sudo -u www-data wp cb export_charges --redshift --redshift-upload-only
/var/www/box/wp-content/plugins/challengebox/bin/slack-command.sh sudo -u www-data wp cb export_charges --redshift --redshift-upload-only

# Load data into db
/var/www/box/wp-content/plugins/challengebox/bin/slack-command.sh sudo -u www-data wp cb reload_redshift

