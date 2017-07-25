<?php
require_once (dirname(__FILE__)."/WebConsole.php");

class SendSpace {
	
public function downloadFile($link, $filesave) {
	$webConsole = new WebConsole("/tmp/cookie_SendSpace.download", null);
	$homepage = $webConsole->getGet($link, "");
	file_put_contents("/tmp/SendSpace.download", $homepage);
	$pos = strpos($homepage,"download_button");
	if ($pos===false)
		return -1;
	
	$rs = $webConsole->getKeyValue($homepage, "href", $fileurl, $pos, true);
	if ($rs!="")
		return -2;

	//var_dump($fileurl, $header);
	return $webConsole->downloadFile($fileurl, $filesave, "");

}
	
  public function uploadFileAnon($file) {
    $webConsole = new WebConsole("/tmp/cookie_sendspace", null);
    $homepage = $webConsole->getGet("https://www.sendspace.com", "");
    //var_dump($homepage);
    $webConsole->getKeyValue($homepage, "PROGRESS_URL", $progressUrl);
    $webConsole->getKeyValue($homepage, "signature", $signature);
    $pos = strpos($homepage, "id=\"start\"");
    if ($pos===false)
      return false;
    $webConsole->getKeyValue($homepage, "action", $url, $pos, true);
    //var_dump($progressUrl, $signature, $url);
    $otherParams = array(
            "PROGRESS_URL" => $progressUrl,
            "signature" => $signature,
            "js_enabled" => "1",
            "upload_files" => "",
            "terms" => "1",
            "file[]" => "",
            "description[]" => "",
            "recpemail_fcbkinput"	=> "recipient@email.com",
            "ownemail" => "",
            "recpemail" => ""
        );
    $error = $webConsole->getPostFile($url, $file, $otherParams, $rs);
    if ($error!=="")
      return false;
    $pos = strpos($rs, ">Download Page Link<");
    if ($pos===false)
      return false;
    if ($webConsole->getKeyValue($rs, "href", $url, $pos, true)!=="")
      return false;
    return $url;
  }

  
}
?>
