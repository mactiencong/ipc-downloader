<?php
require_once (dirname(__FILE__)."/WebConsole.php");

class FileUp {
  public function uploadFile($file) {
  }

  public function downloadFile($link, $filesave, &$orgFile="") {
		$webConsole = new WebConsole("/tmp/cookie_fileup.send", null);
		$homepage = $webConsole->getGet($link, "");
		file_put_contents("/tmp/fileup", $homepage);
		$rs = $webConsole->getKeyValue($homepage, " onclick", $link, 0, true);
		if ($rs!="")
			return -1;
		$pos1 = strpos($link, "'");
		if ($pos1===false)
			return -2;
		$pos2 = strpos($link, "'", $pos1+1);
		if ($pos2===false)
			return -3;
		$link = substr($link, $pos1+1, $pos2-$pos1-1);
		if (strpos($link,"http")!==0)
			return -4;
		//var_dump($rs, $link);
		$rs = $webConsole->getPost($link, "", "task=download&go=", $err, false, $header);
		//tim location
		$pos1 = strpos($header, "Location: ");
		if ($pos1===false)
			return -5;
		$pos1 = $pos1 + strlen("Location: ");
		//tim \r\n
		$pos2 = strpos($header, "\n", $pos1);
		if ($pos2===false)
			return -6;
		$fileurl = trim(substr($header, $pos1, $pos2-$pos1));


		//var_dump($fileurl, $header);
		$rs = $webConsole->downloadFile($fileurl, $filesave, "");
    $orgFile = $webConsole->remoteFileName;
    return $rs;
	}

}

?>
