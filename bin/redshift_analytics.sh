#!/bin/bash
set -eo pipefail
[[ "$TRACE" ]] && set -x

CLUSTER_WAIT=60
aws=/usr/local/bin/aws
if [ "$1" = "--debug" ]; then debug=true; else debug=false; fi

echo "Starting redshift cluster..."
start_cluster() {
	$aws redshift restore-from-cluster-snapshot --cluster-identifier cb-analytics --snapshot-identifier cb-analytics-nightly --iam-roles arn:aws:iam::150598675937:role/RedshiftCopyUnload
}
if $debug; then start_cluster; else start_cluster > /dev/null; fi

check_available() {
	$aws redshift describe-clusters --cluster-identifier cb-analytics | grep ClusterStatus | grep -v available
}
while if $debug; then check_available; else check_available > /dev/null; fi; do
	echo "Waiting $CLUSTER_WAIT seconds for redshift cluster to become available..."
	sleep $CLUSTER_WAIT
done

echo "Exporting users to s3..."
sudo -u www-data wp cb export_users --all --redshift --redshift-upload-only "$@"
echo "Exporting subscriptions to s3..."
sudo -u www-data wp cb export_subscriptions --all --redshift --redshift-upload-only "$@"
echo "Exporting subscription events to s3..."
sudo -u www-data wp cb export_subscription_events --all --redshift --redshift-upload-only "$@"
echo "Exporting orders to s3..."
sudo -u www-data wp cb export_orders --all --redshift --redshift-upload-only "$@"
echo "Exporting refunds to s3..."
sudo -u www-data wp cb export_refunds --redshift --redshift-upload-only "$@"
echo "Exporting charges to s3..."
sudo -u www-data wp cb export_charges --redshift --redshift-upload-only "$@"
echo "Exporting fitness data to s3..."
sudo -u www-data wp cb export_fitness_data --redshift --redshift-upload-only "$@"

echo "Loading s3 data into redshift..."
sudo -u www-data wp cb reload_redshift "$@"

echo "Loading analytics results back into mysql..."
sudo -u www-data wp cb load_analytics_results_from_redshift "$@"

delete_old_snapshot() {
	$aws redshift delete-cluster-snapshot --snapshot-identifier cb-analytics-nightly
}
delete_cluster() {
	$aws redshift delete-cluster --cluster-identifier cb-analytics --final-cluster-snapshot-identifier cb-analytics-nightly
}
echo "Deleting old redshift cluster snapshot..."
if $debug; then delete_old_snapshot; else delete_old_snapshot > /dev/null; fi
echo "Shutting down redshift cluster..."
if $debug; then delete_cluster; else delete_cluster > /dev/null; fi

wait_for_shutdown() {
	$aws redshift describe-clusters --cluster-identifier cb-analytics
}
while if $debug; then wait_for_shutdown; else wait_for_shutdown > /dev/null; fi; do
	echo "Waiting $CLUSTER_WAIT seconds for redshift cluster shutdown..."
	sleep $CLUSTER_WAIT
done
