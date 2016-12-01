#!/bin/bash
set -eo pipefail
[[ "$TRACE" ]] && set -x

aws=/usr/local/bin/aws

# Start cluster
$aws redshift restore-from-cluster-snapshot --cluster-identifier cb-analytics --snapshot-identifier cb-analytics-nightly

# Wait for cluster to spin up
CLUSTER_WAIT=60
while $aws redshift describe-clusters --cluster-identifier cb-analytics | grep ClusterStatus | grep -v available
do
	echo "Waiting $CLUSTER_WAIT seconds to check cluster status..."
	sleep $CLUSTER_WAIT
done

# Export to s3
sudo -u www-data wp cb export_users --all --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_subscriptions --all --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_subscription_events --all --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_orders --all --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_refunds --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_charges --redshift --redshift-upload-only "$@"
sudo -u www-data wp cb export_fitness_data --redshift --redshift-upload-only "$@"

# Load data into db
sudo -u www-data wp cb reload_redshift "$@"

# Load analytics results back into mysql
sudo -u www-data wp cb load_analytics_results_from_redshift "$@"

# Shutdown cluster
$aws redshift delete-cluster-snapshot --snapshot-identifier cb-analytics-nightly
$aws redshift delete-cluster --cluster-identifier cb-analytics --final-cluster-snapshot-identifier cb-analytics-nightly

# Wait for cluster to spin up
CLUSTER_WAIT=60
while $aws redshift describe-clusters --cluster-identifier cb-analytics
do
	echo "Waiting $CLUSTER_WAIT seconds for cluster shutdown..."
	sleep $CLUSTER_WAIT
done
