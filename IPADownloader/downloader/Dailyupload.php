<?php
require_once (dirname(__FILE__)."/WebConsole.php");

class DailyUpload {
	public function login($user, $pass) {
		$webConsole = new WebConsole("/tmp/cookie_dailyupload.upload", null);
		$homepage = $webConsole->getPost("https://dailyuploads.net/", "", "op=login&redirect=https%3A%2F%2Fdailyuploads.net%2F&login=" . urlencode($user) . "&password=" . urlencode($pass));
		if (strpos($homepage, "Logout")===false)
			return -1;
		return 0;
	}

	public function uploadFile($file) {
		$webConsole = new WebConsole("/tmp/cookie_dailyupload.upload", null);
		$homepage = $webConsole->getGet("https://dailyuploads.net/", "");
		$pos = strpos($homepage, "upload_block");
		if ($pos===false)
			return -1;
		$webConsole->getKeyValue($homepage, "action", $url, $pos, true);

		$pos1 = strpos($homepage, "name=\"sess_id\"");
		if ($pos1===false)
			return -2;
		$rs = $webConsole->getKeyValue($homepage, "value", $sess_id, $pos1+1, true);
		if ($rs!="")
			return -3;

		$pos1 = strpos($homepage, "name=\"srv_tmp_url\"");
		if ($pos1===false)
			return -4;
		$rs = $webConsole->getKeyValue($homepage, "value", $srv_tmp_url, $pos1+1, true);
		if ($rs!="")
			return -5;

		$uploadId = "";
		for ($i=0; $i<12; $i++) {
			$uploadId = $uploadId . trim(rand(0,9));
		}
		$url = $url . $uploadId . "&js_on=1&utype=reg&upload_type=file";
		$otherParams = array(
							"upload_type" => "file",
							"sess_id" => $sess_id,
							"srv_tmp_url" => $srv_tmp_url,
							"file_0_descr" => "",
							"link_rcpt" => "",
							"link_pass" => "",
							"to_folder" => "",
							"file_1" => "",
							"submit_btn" =>	"Start Upload"
						);

		$rs = $webConsole->getPostFile($url, $file, $otherParams, $ret, "file_0");
		echo "POST $url => " . print_r($otherParams, true);
		file_put_contents("/tmp/dailyuploads_upload.html", $ret);
		$pos = strpos($ret, "name='fn'");
		if ($pos===false)
			return -6;
		$pos1 = strpos($ret, ">", $pos);
		if ($pos1===false)
			return -7;
		$pos2 = strpos($ret, "<", $pos1);
		if ($pos2===false)
			return -8;
		$id = trim(substr($ret, $pos1+1, $pos2-$pos1-1));
		return "https://dailyuploads.net/" . $id;
	}

    public function downloadFile($link, $filesave, &$orgFile="") {
		$webConsole = new WebConsole("/tmp/cookie_dailyupload.download", null);
		$homepage = $webConsole->getGet($link, "");
		file_put_contents("/tmp/dailyupload", $homepage);
		$pos1 = strpos($homepage, "name=\"id\"");
		if ($pos1===false)
			return -1;
		$rs = $webConsole->getKeyValue($homepage, "value", $id, $pos1+1, true);
		if ($rs!="")
			return -2;

		$pos1 = strpos($homepage, "name=\"rand\"");
		if ($pos1===false)
			return -3;
		$rs = $webConsole->getKeyValue($homepage, "value", $rand, $pos1+1, true);
		if ($rs!="")
			return -4;

		//var_dump($id, $rand);
		$rs = $webConsole->getPost($link, "", "op=download2&id=$id&rand=$rand&referer=&method_free=&method_premium=&down_script=1&fs_download_file=", $errmsg);
		$pos1 = strpos($rs, "This direct link will be available for your IP");
		if ($pos1===false)
			return -5;
		$rs = $webConsole->getKeyValue($rs, "href", $ipaLink, $pos1+1, true);
		if ($rs!="")
			return -7;
		$rs = $webConsole->downloadFile($ipaLink, $filesave, "");
		$orgFile = $webConsole->remoteFileName;
		if ($orgFile=="") {
			$pathin = pathinfo($ipaLink);
			$orgFile = $pathin['basename'];
		}
		return $rs;
	}

}

?>
