<?php
set_time_limit(0);
require_once dirname(__FILE__) . '/logger/LoggerConfiguration.php';
require_once dirname(__FILE__) . '/simple_html_dom.php';
require_once dirname(__FILE__) . '/downloader/Fileup.php';
require_once dirname(__FILE__) . '/downloader/Dailyupload.php';
require_once dirname(__FILE__) . '/downloader/SendSpace.php';

$allow_download_hosts = array(
    'dailyuploads.net',
    'filepup.net',
    'sendspace.com'
);
$crackers_skip = array(
    'cartoonfk',
    'laughworld',
    'flashsupporter',
	'cracker4pro',
	'steveg2513'
);
define('ALLOW_REMOVE_CRACKER', true);
define('SAVED_FILE_PATH', '/Volumes/IPAFiles/downloadipas/');
define('EXTRACTED_PATH', '/Volumes/IPAFiles/downloadipas/extracted_ipa/');
define('SUCCESSED_FILE_PATH', '/Volumes/IPAFiles/downloadipas/whitelist_orgipa/');
define('TMP_RAND_FILE_PATH', '/Library/WebServer/Documents/AppStoreWrapper/src/Application/IPADownloader/tmp/');
define('MAX_FILE_SIZE', 300000000);
$iphonecake_id = $argv[1];
$rand = $argv[2];
if (! $iphonecake_id || !$rand)
    return;
$fileup = new FileUp();
$dailyUpload = new DailyUpload();
$sendSpace = new SendSpace();
LoggerConfiguration::logInfo('START');
LoggerConfiguration::logInfo("IphonecakeID=$iphonecake_id");
// B2: Crawler link tu iphonecake
$download_links = getDownloadLink($iphonecake_id, $allow_download_hosts, $crackers_skip);
if (! $download_links) {
    LoggerConfiguration::logInfo('Not found download links');
    // khong co link download
    return;
}
foreach ($download_links as $link) {
    LoggerConfiguration::logInfo('Try with link: ' . print_r($link, true));
    //$saveFile = SAVED_FILE_PATH . "{$iphonecake_id}_{$link['cracker']}.ipa";
	$saveFile = SAVED_FILE_PATH . "{$iphonecake_id}.ipa";
	$ipa_file = SUCCESSED_FILE_PATH . "{$iphonecake_id}.ipa";
	$orin_file = '';
	$filename = $iphonecake_id;
	if (!file_exists($ipa_file)) {
	    if ($link['type'] === $allow_download_hosts[0]) {
	        // dailyuploads.net
	        LoggerConfiguration::logInfo("dailyUpload->downloadFile(link={$link['link']}, saveFile={$saveFile})");
	        $rs = $dailyUpload->downloadFile($link['link'], $saveFile, $orin_file);
	    } elseif ($link['type'] === $allow_download_hosts[1]) {
	        LoggerConfiguration::logInfo("fileup->downloadFile(link={$link['link']}, saveFile={$saveFile})");
	        $rs = $fileup->downloadFile($link['link'], $saveFile, $orin_file);
	    } elseif ($link['type'] === $allow_download_hosts[2]) {
	        LoggerConfiguration::logInfo("fileup->downloadFile(link={$link['link']}, saveFile={$saveFile})");
	        $rs = $sendSpace->downloadFile($link['link'], $saveFile, $orin_file);
	    } else {
	        continue;
	    }
	    LoggerConfiguration::logInfo("RS={$rs}");
	    if (in_array($rs, array(
	        - 1,
	        - 2,
	        - 3,
	        - 4,
	        - 5,
	        - 6,
	        - 7
	    ))) {
	        // that bai
	        // thu link khac
	        continue;
	    }
	    LoggerConfiguration::logError("File downloaded to $saveFile");
	    if (!file_exists($saveFile)) {
	    	LoggerConfiguration::logError("$saveFile not exist");
	    	continue;
	    }
	    // thanh cong
	    //$filename = $orin_file;
	    //$orin_file = SAVED_FILE_PATH. $orin_file;
	    //$saveFile = $orin_file;
	    if(ALLOW_REMOVE_CRACKER) {
	        $extract_dir = extractIPA($saveFile, $iphonecake_id);
	        if (! $extract_dir) {
	            // extract error
	            unlink($saveFile);
	            continue;
	        }
	        // xoa file download
	        // unlink($saveFile);
	        // xoa file cracker
	        $has_file = removeCrackerSign($extract_dir, $link['cracker']);
	        // thuc hien zip lai file IPA
	        //$ipa_file = $has_file ? SUCCESSED_FILE_PATH . "{$iphonecake_id}.ipa" : SUCCESSED_FILE_PATH . "{$iphonecake_id}_{$link['cracker']}.ipa";
	        
	        LoggerConfiguration::logInfo("Chdir: $extract_dir");
	        chdir($extract_dir);
	        $zipcmd = "zip -qr $ipa_file Payload/";
	        LoggerConfiguration::logInfo("Zip CMD=$zipcmd");
	        exec($zipcmd);
	        unlink($saveFile);
	        exec("rm -rf {$extract_dir}/*");
	    }
	    else {
	    	// just move
	    	move_uploaded_file($saveFile, $ipa_file);
	    }
	    if(filesize($saveFile)>MAX_FILE_SIZE) {
	        LoggerConfiguration::logError('The file is too large');
	        die();
	    }
	}
    // kiem tra xem co unzip dc ko
// 	if(!rename ($saveFile, $orin_file)) {
// 		break;
// 	}
//	LoggerConfiguration::logInfo("Renamed $saveFile => $orin_file");
    LoggerConfiguration::logInfo('SUCCESSFULLY');
    if(!file_put_contents(TMP_RAND_FILE_PATH . "{$iphonecake_id}_{$rand}", json_encode(array(
        'cracker' => $link['cracker'],
        'name' => $filename
    )))) {
		LoggerConfiguration::logError('Can not save file: ' . TMP_RAND_FILE_PATH . "{$iphonecake_id}_{$rand}");
		die;
	}
    LoggerConfiguration::logInfo('Saved successfully to ' . TMP_RAND_FILE_PATH . "{$iphonecake_id}_{$rand}");
    break;
}
LoggerConfiguration::logInfo('END');

function removeCrackerSign($dir, $cracker)
{
    $cracker = strtoupper($cracker);
    $hasFile = false;
    foreach (glob($dir . '/Payload/*.app/*') as $filename) {
        if (strpos(strtoupper(basename($filename)), $cracker) !== false) {
            // ton tai file
            // thuc hien xoa file
            if (! unlink($filename)) {
                LoggerConfiguration::logInfo("Cannot delete file: $filename");
                return false;
            }
            LoggerConfiguration::logInfo("Deleted file: $filename");
            $hasFile = true;
        }
    }
    return $hasFile;
}

function extractIPA($ipa_file, $iphonecakeid)
{
    $zip = new ZipArchive();
    if ($zip->open($ipa_file) === TRUE) {
        $extracted_dir = EXTRACTED_PATH . $iphonecakeid;
        if (! file_exists($extracted_dir)) {
            if (! mkdir($extracted_dir, 0777, true)) {
                LoggerConfiguration::logError("Cannot make dir: $extracted_dir");
                return false;
            }
        }
        if ($zip->extractTo($extracted_dir)) {
            $zip->close();
            return $extracted_dir;
        }
        LoggerConfiguration::logError("Cannot extract file: $ipa_file to $extracted_dir");
        $zip->close();
        return false;
    } else {
        LoggerConfiguration::logError("Cannot open file: $ipa_file");
        return false;
    }
      //unzip file.zip -d destination_folder
//       $extracted_dir = EXTRACTED_PATH . $iphonecakeid;
//       exec("unzip $ipa_file -d $extracted_dir");
      return true;
}

function getDownloadLink($iphonecake_id, $allow_download_hosts, $crackers_skip)
{
    $url = "https://www.iphonecake.com/app_{$iphonecake_id}_.html";
    $html_content = str_get_html(curlGetContent($url));
    if (! $html_content) {
        LoggerConfiguration::logError("Cannot get content URL=$url");
        return false;
    }
    // lay cac link download
    $links = $html_content->find('a[title="Download From This Filehost"]');
    if (! $links) {
        LoggerConfiguration::logInfo("Not found links for $iphonecake_id");
        $html_content->clear();
        unset($html_content);
        return false;
    }
    $download_links = null;
    $version = null;
    foreach ($links as &$link) {
        // chi support link tu dailyupload va filepup
        $link_domain = $link->plaintext;
        $link_type = null;
        foreach ($allow_download_hosts as $domain) {
            if (strpos($link_domain, $domain) !== false) {
                $link_type = $domain;
            }
        }
        if (! $link_type) {
            // khong nam trong danh sach host file
            LoggerConfiguration::logInfo("Dont support link: $link_domain");
            continue;
        }
        // Lay url download
        // https://www.iphonecake.com/dl.php?dlid=688570&id=1094591345&name=Pok%C3%A9mon+GO
        $download_url = 'https://www.iphonecake.com/' . $link->href;
        $download_page = str_get_html(curlGetContent($download_url));
        if (! $download_page) {
            LoggerConfiguration::logError("Cannot get download page: $download_url");
            continue;
        }
        // lay link
        $host_link = $download_page->find('a', 0)->href;
        // kiem tra lai link cho chac
        if (strpos($host_link, $link_type) === false) {
            // khong dung
            $download_page->clear();
            unset($download_page);
            LoggerConfiguration::logError("Can not get download link: $host_link");
            continue;
        }
        // lay uploader/cracker
        $cracker = '';
		$uploader = '';
        $ui_app_uploader = $link->parent->parent->find('div[class="ui-app-uploader"]', 0);
        if ($ui_app_uploader) {
            $cu = trim(str_replace('Cracker/Uploader:', '', $ui_app_uploader->plaintext));
            if (strpos($cu, '/') === false) {
                $cracker = $cu;
            } else {
                $cu = explode('/', $cu);
                $cracker = trim($cu[0]);
				$uploader = trim($cu[1]);
            }
        }
        if (! $cracker) {
            LoggerConfiguration::logError("Cannot get cracker: $iphonecake_id");
            $download_page->clear();
            unset($download_page);
            // khong lay duoc cracker
            continue;
        }
        if (in_array($cracker, $crackers_skip)) {
			LoggerConfiguration::logInfo("Cracker=$cracker will be skip");
            return null;
        }
		if (in_array($uploader, $crackers_skip)) {
			LoggerConfiguration::logInfo("Uploader=$uploader will be skip");
            return null;
        }
        // lay ver se download
        // <span class="text-warning ui-app-version">Version:</span>1.5.0<div class="ui-right-panel">
        if (! $version) {
            $li_content = $link->parent->parent->parent->outertext;
            $pos_ver_1 = strpos($li_content, 'Version:</span>');
            $pos_ver_2 = strpos($li_content, '<div class="ui-right-panel">');
            if ($pos_ver_1 === false || $pos_ver_2 === false) {
                LoggerConfiguration::logError("Can not get app version: $iphonecake_id");
                $download_page->clear();
                unset($download_page);
                continue;
            }
            $pos_ver_1 = $pos_ver_1 + strlen('Version:</span>');
            $version = trim(substr($li_content, $pos_ver_1, $pos_ver_2 - $pos_ver_1));
        }
        // lay link tai
        $download_links[] = array(
            'type' => $link_type,
            'link' => $host_link,
            'cracker' => $cracker,
            'version' => $version
        );
        $download_page->clear();
        unset($download_page);
    }
    $links = null;
    unset($links);
    if (! $download_links) {
        // khong lay dc link nao
        LoggerConfiguration::logError("Not found any valid links for app=$iphonecake_id");
        $html_content->clear();
        unset($html_content);
        return false;
    }
    return $download_links;
}

function curlGetContent($url)
{
    // $url = 'https://www.iphonecake.com/app_1094591345_.html';
    for ($try = 0; $try < 2; $try ++) {
        $options = array(
            CURLOPT_RETURNTRANSFER => true, // return web page
            CURLOPT_HEADER => false, // don't return headers
            CURLOPT_FOLLOWLOCATION => true, // follow redirects
            CURLOPT_ENCODING => '', // handle all encodings
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.76 Mobile Safari/537.36', // who am i
            CURLOPT_AUTOREFERER => true, // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120, // timeout on connect
            CURLOPT_TIMEOUT => 120, // timeout on response
            CURLOPT_MAXREDIRS => 10, // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => false
        ); // Disabled SSL Cert checks
        
        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $content = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            LoggerConfiguration::logError("Error: $err");
            LoggerConfiguration::logError("Html content: $content");
        }
        return $content;
    }
    return false;
}