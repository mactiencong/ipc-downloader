<?php

class WebConsole {
	public $cookieFile;
	public $instanceId;
	public $logger;
	public $proxyName = "";
	public $proxyType = "";
	public $remoteFileName = "";

	public function __construct($cookieFile, $logger, $instanceId="") {
		$this->cookieFile = $cookieFile;
		$this->logger = $logger;
		$this->instanceId = $instanceId;


		set_time_limit(0);
	}

	public function getCookieFile($username) {
		$file = $this->cookieFile . ".$username.cookie";

		if (!file_exists(dirname($file)))
			mkdir(dirname($file), 0777, true);
		return $file;
	}

	public function setCookie($cookie, $username) {
		$file = $this->cookieFile . ".$username.cookie";

		if (!file_exists(dirname($file)))
			mkdir(dirname($file), 0777, true);
		file_put_contents($file, $cookie);
	}

	public function setProxy($proxyName, $proxyType) {
		$this->proxyName = $proxyName;
		$this->proxyType = $proxyType;
	}

	function LogInfo($msg) {
 		$this->logger->LogInfo("[" . $this->instanceId . "] $msg");
	}

	function LogError($msg) {
		$this->logger->LogError("[" . $this->instanceId . "] $msg");
	}

	function LogDebug($msg) {
		$this->logger->LogDebug("[" . $this->instanceId . "] $msg");
	}

	/**
	 * "key":"value";
	 * @param unknown_type $rs string to search
	 * @param unknown_type $key
	 * @return string
	 */
	public function getKeyValue($rs, $key, &$value, $fromPos=0, $keyWithoutQuote=false, $quoteChar="\"") {
		if ($keyWithoutQuote===false)
			$key = $quoteChar . $key . $quoteChar;
		$start = strpos($rs, $key, $fromPos);
		if ($start===false) {
			return "Cannot find $key";
		}
		//tim tiep value
		$start2 = strpos($rs, $quoteChar, $start + strlen($key));
		if ($start2===false) {
			return "incorrect $key value 1";
		}
		//tim tiep value
		$start2 = $start2 + 1;
		$start3 = strpos($rs, $quoteChar, $start2);
		if ($start3===false) {
			return "incorrect $key value 2";
		}
		$value = substr($rs, $start2, $start3-$start2);
		return "";
	}

	public function getGet($url, $username, &$errmsg="") {
		$curl = curl_init ();
		curl_setopt ( $curl, CURLOPT_URL,$url);
		curl_setopt ( $curl, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 );
		#curl_setopt ( $curl, CURLOPT_POST, 1 );
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, false );
		#curl_setopt ( $curl, CURLOPT_POSTFIELDS, "charset_test=" . $charsetTest . "&locale=" . $locale . "&non_com_login=&email=" . $username . "&pass=" . $password . "&charset_test=" . $charsetTest . "&lsd=" . $lsd );
		$header[] = "Accept-Language: en-us,en;q=0.5";
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

		curl_setopt ( $curl, CURLOPT_COOKIEFILE, $this->getCookieFile($username));
		curl_setopt ( $curl, CURLOPT_COOKIEJAR, $this->getCookieFile($username));
		curl_setopt ( $curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.2) Gecko/20100115 Firefox/3.6 (.NET CLR 3.5.30729)" );
		if ($this->proxyName!=="") {
			curl_setopt($curl, CURLOPT_PROXY,  $this->proxyName);
			curl_setopt($curl, CURLOPT_PROXYTYPE, $this->proxyType);
		}
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,0);
		curl_setopt($curl, CURLOPT_TIMEOUT, 60); //timeout in seconds
		curl_setopt($curl, CURLOPT_SSLVERSION,6);
		//$curlData is the html of your facebook page
		$rs = curl_exec ( $curl );
		$errmsg = "";
		if(curl_errno($curl))
		{
				$errmsg = curl_error($curl);
		}
		curl_close($curl);
		return $rs;
	}

	public function getPost($url, $username, $params, &$errmsg="", $allowFollow=true, &$header="") {
		$curl = curl_init ();
		curl_setopt ( $curl, CURLOPT_URL,$url);
		curl_setopt ( $curl, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $curl, CURLOPT_POST, 1 );
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt ( $curl, CURLOPT_HEADER, 1);
		curl_setopt ( $curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt ( $curl, CURLOPT_FOLLOWLOCATION, $allowFollow?1:0);
		curl_setopt ( $curl, CURLOPT_ENCODING, "" );
		curl_setopt ( $curl, CURLOPT_COOKIEFILE, $this->getCookieFile($username));
		curl_setopt ( $curl, CURLOPT_COOKIEJAR, $this->getCookieFile($username));
		curl_setopt ( $curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.2) Gecko/20100115 Firefox/3.6 (.NET CLR 3.5.30729)" );
		if ($this->proxyName!=="") {
			curl_setopt($curl, CURLOPT_PROXY,  $this->proxyName);
			curl_setopt($curl, CURLOPT_PROXYTYPE, $this->proxyType);
		}
		$header[] = "Accept-Language: en-us,en;q=0.5";
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,0);
		curl_setopt($curl, CURLOPT_TIMEOUT, 60); //timeout in seconds
		curl_setopt($curl, CURLOPT_SSLVERSION,6);
		//$curlData is the html of your facebook page
		$response = curl_exec ( $curl );
		$errmsg = "";
		if(curl_errno($curl))
		{
				$errmsg = curl_error($curl);
		}
		$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$body = substr($response, $header_size);

		curl_close($curl);

		return $body;
	}

	public function getPostFile($url, $file, $otherParams, &$rs, $dataField = "upload_file[]") {
		$path = pathinfo($file);
		$filename = $path['basename'];
		$filedata = $file;
		$filesize = filesize($file);
		$headers = array("Content-Type:multipart/form-data"); // cURL headers for file uploading
		$postfields = array($dataField => "@$filedata", "filename" => $filename);
		foreach ($otherParams as $key=>$value) {
			$postfields[$key] = $value;
		}
		$ch = curl_init();
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_POST => 1,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => $postfields,
			CURLOPT_INFILESIZE => $filesize,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_COOKIEFILE => $this->getCookieFile($username),
			CURLOPT_COOKIEJAR => $this->getCookieFile($username),
			CURLOPT_USERAGENT => "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.2) Gecko/20100115 Firefox/3.6 (.NET CLR 3.5.30729)"
		); // cURL options
		curl_setopt_array($ch, $options);
		$rs = curl_exec($ch);
		if(!curl_errno($ch))
		{
			$info = curl_getinfo($ch);
			if ($info['http_code'] == 200)
			{
					$errmsg = "";
			}
		}
		else
		{
			$errmsg = curl_error($ch);
		}
		curl_close($ch);
		return $errmsg;
	}

	public function headerCallback($ch, $string)
	{
			$len = strlen($string);
			if( !strstr($string, ':') )
			{
					$this->response = trim($string);
					return $len;
			}
			list($name, $value) = explode(':', $string, 2);
			if( strcasecmp($name, 'Content-Disposition') == 0 )
			{
					$parts = explode(';', $value);
					if( count($parts) > 1 )
					{
							foreach($parts AS $crumb)
							{
									if( strstr($crumb, '=') )
									{
											list($pname, $pval) = explode('=', $crumb);
											$pname = trim($pname);
											if( strcasecmp($pname, 'filename') == 0 )
											{
													// Using basename to prevent path injection
													// in malicious headers.
													$this->remoteFileName = basename(
															$this->unquote(trim($pval)));
											}
									}
							}
					}
			}
			return $len;
	}

	public function downloadFile($url, $outfile, $username) {
		set_time_limit(0);
		$this->remoteFileName = "";
		$fp = fopen ($outfile, 'w+');
		//Here is the file we are downloading, replace spaces with %20
		$ch = curl_init(str_replace(" ","%20",$url));
		curl_setopt($ch, CURLOPT_TIMEOUT, 500000);
		// write curl response to file
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION,
					 array($this, 'headerCallback'));
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt ( $ch, CURLOPT_COOKIEFILE, $this->getCookieFile($username));
		curl_setopt ( $ch, CURLOPT_COOKIEJAR, $this->getCookieFile($username));
		curl_setopt ( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.2) Gecko/20100115 Firefox/3.6 (.NET CLR 3.5.30729)" );
		if ($this->proxyName!=="") {
			curl_setopt($ch, CURLOPT_PROXY,  $this->proxyName);
			curl_setopt($ch, CURLOPT_PROXYTYPE, $this->proxyType);
		}

		// get curl response
		curl_exec($ch);
		if(!curl_errno($ch))
		{
			$info = curl_getinfo($ch);
			if ($info['http_code'] == 200)
			{
					$errmsg = "";
			}
		}
		else
		{
			$errmsg = curl_error($ch);
		}

		curl_close($ch);
		fclose($fp);
		return $errmsg;
	}

	private function unquote($string)
	 {
			 return str_replace(array("'", '"'), '', $string);
	 }
}
?>
