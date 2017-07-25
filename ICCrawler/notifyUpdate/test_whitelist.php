<?php
error_reporting(E_ALL);
ini_set('display_errors', true);
require_once dirname(__FILE__) . '/../ICCrawler.php';
$crawler = new ICCrawler();
$ipc_id = $argv[1];
if (!$ipc_id)
	exit('ID???');
$crawler->checkUpdateByWhiteListTest($ipc_id);
