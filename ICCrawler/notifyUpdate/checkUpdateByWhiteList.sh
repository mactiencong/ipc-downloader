PID_FILE=$0.pid
[ -f $PID_FILE ] && {
   pid=`cat $PID_FILE`
   ps -p $pid && {
      echo -e "/usr/bin/php checkUpdateWhiteList.php is processing ..."
      exit
   }
   rm -f $PID_FILE
}

echo $$ > $PID_FILE
      /usr/bin/php /Library/WebServer/Documents/AppStoreWrapper/src/Application/ICCrawler/notifyUpdate/checkUpdateWhiteList.php
