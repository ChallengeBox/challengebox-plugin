#!/bin/bash
set -ex
URL=https://hooks.slack.com/services/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
LOG=$(mktemp -t command_log_XXXX)
tail -f $LOG &
command="$@"
echo LOG=$LOG
curl -X POST -H 'Content-type: application/json' --data "{\"text\":\"$(hostname) running '$command'\"}" $URL
if $command >>$LOG 2>>$LOG
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
python -c "import json; file(\"$PAYLOAD\",'w+').write(json.dumps({'attachments':[{'color':\"$COLOR\",'text':file(\"$LOG\").read()}]}))"
curl -X POST -H 'Content-type: application/json' --data @$PAYLOAD $URL
echo
rm $LOG $PAYLOAD
curl -X POST -H 'Content-type: application/json' --data "{\"text\":\"$STATUS $VERB '$command'\"}" $URL
