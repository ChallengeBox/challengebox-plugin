#!/bin/bash
set -eo pipefail
[[ "$TRACE" ]] && set -x

# Export to s3
sudo -u www-data wp cb export_users --all --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_subscriptions --all --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_subscription_events --all --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_orders --all --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_refunds --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_charges --redshift --redshift-upload-only "$@"

# Load data into db
sudo -u www-data wp cb reload_redshift "$@"

