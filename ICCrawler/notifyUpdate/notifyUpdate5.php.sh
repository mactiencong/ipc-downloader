PID_FILE=$0.pid
[ -f $PID_FILE ] && {
   pid=`cat $PID_FILE`
   ps -p $pid && {
      echo -e "/usr/bin/php notifyUpdate5.php is processing ..."
      exit
   }
   rm -f $PID_FILE
}

echo $$ > $PID_FILE
      /usr/bin/php /var/spool/www/mine/wrapper.laughworld.net/IphoneCakeCrawler/notifyUpdate/notifyUpdate5.php
