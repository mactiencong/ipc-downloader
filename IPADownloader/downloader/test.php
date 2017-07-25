<?php
require_once(dirname(__FILE__)."/Fileup.php");
require_once(dirname(__FILE__)."/DailyUpload.php");
//$r = new FileUp();
//echo $r->downloadFile("http://www.filepup.net/files/OyjOaUy1470724043.html", "fileup.ipa");

$r = new DailyUpload();
echo $r->downloadFile("https://dailyuploads.net/8n5z5j1n0ha4", "fileup.ipa");

?>
