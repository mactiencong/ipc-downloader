<?php
error_reporting(E_ALL);
ini_set('display_errors', true);
require_once dirname(__FILE__) . '/../ICCrawler.php';
$crawler = new ICCrawler();
$crawler->nofifyUpdate(10,20);