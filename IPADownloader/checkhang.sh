while [ 1 -lt 3 ]
do
	ls -l /Volumes/IPAFiles/downloadipas/ > /tmp/diff1
	sleep 1800
	ls -l /Volumes/IPAFiles/downloadipas/ > /tmp/diff2
 	diff /tmp/diff1 /tmp/diff2
	if [ $? -eq 0 ]; then
		echo "Kill php hang"
		pkill -9 php
		sleep 1800
	fi
done
