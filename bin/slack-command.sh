#!/bin/bash
set -ex
URL=https://hooks.slack.com/services/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
LOG_DIR=/var/www/box/wp-content/plugins/challengebox/ops-logs
CMD_DIR=$(date +"%Y/%m/%d/%H:%M:%S_$RANDOM")
LINK_BASE=https://www.getchallengebox.com/wp-content/plugins/challengebox/ops-logs
mkdir -p $LOG_DIR/$CMD_DIR
LOG=$(mktemp -t command_log_XXXX)
tail -f $LOG &
command="$@"
echo LOG=$LOG
curl -X POST -H 'Content-type: application/json' --data "{\"text\":\"$(hostname) running '$command'\"}" $URL
if nice $command >>$LOG 2>>$LOG
then
  STATUS="✅"
  VERB="successfully ran"
  COLOR="good"
else
  STATUS="❌"
  VERB="failed to run"
  COLOR="danger"
fi
PAYLOAD=$(mktemp -t deploy_slack_payload_XXXX)
echo PAYLOAD=$PAYLOAD
python -c "import json; log=file(\"$LOG\"); file(\"$PAYLOAD\",'w+').write(json.dumps({'attachments':[{'color':\"$COLOR\",'text':log.read(1024) + (\"...\n<$LINK_BASE/$CMD_DIR/log|download log>\" if log.read(1) else \"\")}]}))"
curl -X POST -H 'Content-type: application/json' --data @$PAYLOAD $URL
echo
# Save state
echo $STATUS > $LOG_DIR/$CMD_DIR/status
echo $VERB > $LOG_DIR/$CMD_DIR/verb
echo $COLOR > $LOG_DIR/$CMD_DIR/color
echo $command > $LOG_DIR/$CMD_DIR/command
chmod 755 $LOG
chmod 755 $PAYLOAD
mv $LOG $LOG_DIR/$CMD_DIR/log
mv $PAYLOAD $LOG_DIR/$CMD_DIR/payload
curl -X POST -H 'Content-type: application/json' --data "{\"text\":\"$STATUS $VERB '$command'\"}" $URL
