#!/bin/bash
set -eo pipefail
[[ "$TRACE" ]] && set -x

BDIR=/tmp/backup
mkdir -pv ${BDIR}

TIMESTAMP=`date -u +%Y-%m-%dT%H:%M:%SZ`

echo "Cleaning old files:"
find ${BDIR}/* -mtime +7 -exec rm {} \; || echo "No old files found"
echo "Backing up wordpress directory:"
tar -czf ${BDIR}/challengebox_${TIMESTAMP}.tgz /var/www/box/
echo "Backing up database:"
mysqldump fit_box | gzip > ${BDIR}/challengebox_${TIMESTAMP}.sql.gz
echo "Syncing to s3:"
/usr/local/bin/aws s3 sync /tmp/backup/ s3://challengebox-backup/
echo "DONE!"

