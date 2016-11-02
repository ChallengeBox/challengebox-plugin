#!/bin/bash
set -eo pipefail
[[ "$TRACE" ]] && set -x

BDIR=/tmp/backup
mkdir -pv ${BDIR}

TIMESTAMP=`date -u +%Y-%m-%dT%H:%M:%SZ`

echo "Cleaning old files:"
find ${BDIR}/* -mtime +7 -exec rm {} \; || echo "No old files found"
echo "Backing up wordpress directory:"
tar --exclude='/var/www/box/wp-content/cache' -czf ${BDIR}/challengebox_${TIMESTAMP}.tgz /var/www/box/
echo "Backing up wordpress directory with exclusions:"
tar --exclude='/var/www/box/wp-config.php' --exclude='/var/www/box/wp-content/plugins/challengebox/vendor' --exclude='/var/www/box/wp-content/cache' -czf ${BDIR}/clean_challengebox_${TIMESTAMP}.tgz /var/www/box/
echo "Backing up database:"
mysqldump fit_box | gzip > ${BDIR}/challengebox_${TIMESTAMP}.sql.gz
echo "Backing up database with exclusions:"
mysqldump fit_box | sed 's/sk_live_[A-Za-z0-9_]*/sk_live_XXXXXXXXXXXXXXXXXXXXXXXX/g' | gzip > ${BDIR}/clean_challengebox_${TIMESTAMP}.sql.gz
echo "Syncing to s3:"
/usr/local/bin/aws s3 sync ${BDIR}/ s3://challengebox-backup/
echo "DONE!"

