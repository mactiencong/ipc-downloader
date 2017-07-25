<?php
set_time_limit(0);
require_once dirname(__FILE__) . '/logger/LoggerConfiguration.php';
require_once dirname(__FILE__) . '/simple_html_dom.php';
require_once dirname(__FILE__) . '/db/Database.php';
require_once dirname(__FILE__) . '/downloader/Fileup.php';
require_once dirname(__FILE__) . '/downloader/Dailyupload.php';
require_once dirname(__FILE__) . '/downloader/SendSpace.php';

// Start Config
$total_download_begin = 3000;
$total_download_end = 7000;
$allow_download_hosts = array(
    'dailyuploads.net',
    'filepup.net',
    'sendspace.com'
);
$limit = 50;
$max_size = 1024 * 1000000;
define('SAVED_FILE_PATH', '/Volumes/IPAFiles/downloadipas/');
define('EXTRACTED_PATH', '/Volumes/IPAFiles/downloadipas/extracted_ipa/');
define('SUCCESSED_FILE_PATH', '/Volumes/IPAFiles/downloadipas/successed2/');
define('SUCCESSED_FILE_PATH2', '/Volumes/IPAFiles/downloadipas/successed/');
// End Config
$fileup = new FileUp();
$dailyUpload = new DailyUpload();
$sendSpace = new SendSpace();
LoggerConfiguration::logInfo('START');
$db = new Database();
// B1: Lay nhung app co total_download > 100000
$query = "SELECT iphonecake_id FROM store_apps WHERE size<$max_size AND total_download>=$total_download_begin AND total_download<$total_download_end AND is_download_ipa=0 ORDER BY total_download DESC,size ASC  LIMIT $limit";
LoggerConfiguration::logInfo($query);
$list_app = null;
try {
    if ($result = $db->query($query)) {
        while ($app = $result->fetch_assoc()) {
            $list_app[] = $app['iphonecake_id'];
        }
        $db->free_result($result);
    }
} catch (Exception $e) {
    LoggerConfiguration::logInfo($e->getMessage());
    exit(0);
}
if (! $list_app) {
    LoggerConfiguration::logInfo('No app');
    exit(0);
}
//$db->close();
//unset($db);
foreach ($list_app as $iphonecake_id) {
    LoggerConfiguration::logInfo("IphonecakeID=$iphonecake_id");
    // B2: Crawler link tu iphonecake
    $download_links = getDownloadLink($iphonecake_id, $allow_download_hosts);
    if (! $download_links) {
        LoggerConfiguration::logInfo('Not found download links');
        // khong co link download
        // set is_download=2
        if (updateIsDownloadStatus(2, $iphonecake_id) === false) {
            exit(0);
        }
        continue;
    }
    $is_success = false;
    foreach ($download_links as $link) {
        // kiem tra xem file thanh cong da ton tai chua
        if (file_exists(SUCCESSED_FILE_PATH . "{$iphonecake_id}.ipa") || file_exists(SUCCESSED_FILE_PATH . "{$iphonecake_id}_{$link['cracker']}.ipa" || file_exists(SUCCESSED_FILE_PATH2 . "{$iphonecake_id}.ipa") || file_exists(SUCCESSED_FILE_PATH2 . "{$iphonecake_id}_{$link['cracker']}.ipa"))) {
            LoggerConfiguration::logInfo('Successed file is exist');
            if (updateIsDownloadStatus(1, $iphonecake_id) === false) {
                exit(0);
            }
            $is_success = true;
            break;
        }
        LoggerConfiguration::logInfo('Try with link: ' . print_r($link, true));
        $saveFile = SAVED_FILE_PATH . "{$iphonecake_id}_{$link['cracker']}.ipa";
        if ($link['type'] === $allow_download_hosts[0]) {
            // dailyuploads.net
            LoggerConfiguration::logInfo("dailyUpload->downloadFile(link={$link['link']}, saveFile={$saveFile})");
            $rs = $dailyUpload->downloadFile($link['link'], $saveFile);
        } elseif ($link['type'] === $allow_download_hosts[1]) {
            LoggerConfiguration::logInfo("fileup->downloadFile(link={$link['link']}, saveFile={$saveFile})");
            $rs = $fileup->downloadFile($link['link'], $saveFile);
        } elseif ($link['type'] === $allow_download_hosts[2]) {
            LoggerConfiguration::logInfo("fileup->downloadFile(link={$link['link']}, saveFile={$saveFile})");
            $rs = $sendSpace->downloadFile($link['link'], $saveFile);
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
        // thanh cong
        // kiem tra xem co unzip dc ko
        $extract_dir = extractIPA($saveFile, $iphonecake_id);
        if (! $extract_dir) {
            // extract error
            continue;
        }
        // xoa file download
        // unlink($saveFile);
        // xoa file cracker
        $has_file = removeCrackerSign($extract_dir, $link['cracker']);
        // thuc hien zip lai file IPA
        $ipa_file = $has_file ? SUCCESSED_FILE_PATH . "{$iphonecake_id}.ipa" : SUCCESSED_FILE_PATH . "{$iphonecake_id}_{$link['cracker']}.ipa";
        LoggerConfiguration::logInfo("Chdir: $extract_dir");
        chdir($extract_dir);
        $zipcmd = "zip -qr $ipa_file Payload/";
        LoggerConfiguration::logInfo("Zip CMD=$zipcmd");
        exec($zipcmd);
        unlink($saveFile);
        exec("rm -rf {$extract_dir}/*");
        LoggerConfiguration::logInfo('SUCCESSFULLY');
        if (updateIsDownloadStatus(1, $iphonecake_id, $link['version']) === false) {
            exit(0);
        }
        $is_success = true;
        break;
    }
    if (! $is_success) {
        // download that bai
        LoggerConfiguration::logInfo('FAIL');
        if (updateIsDownloadStatus(2, $iphonecake_id) === false) {
            exit(0);
        }
        continue;
    }
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

function updateIsDownloadStatus($status, $iphonecake_id, $version=null)
{
    $db = new Database();
    $update_version = '';
    if ($version){
        $update_version = ",downloaded_version='$version'";
    }
    try {
        $query = "UPDATE store_apps SET is_download_ipa=$status $update_version WHERE iphonecake_id=$iphonecake_id";
        LoggerConfiguration::logInfo($query);
        if ($db->query($query) && $db->affected_rows()) {
            $db->close();
            return true;
        }
        LoggerConfiguration::logError($db->getError());
        //$db->close();
        return false;
    } catch (Exception $e) {
        LoggerConfiguration::logError($e->getMessage());
        //$db->close();
        return false;
    }
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
}

function getDownloadLink($iphonecake_id, $allow_download_hosts)
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
        $ui_app_uploader = $link->parent->parent->find('div[class="ui-app-uploader"]', 0);
        if ($ui_app_uploader) {
            $cu = trim(str_replace('Cracker/Uploader:', '', $ui_app_uploader->plaintext));
            if (strpos($cu, '/') === false) {
                $cracker = $cu;
            } else {
                $cu = explode('/', $cu);
                $cracker = $cu[0];
            }
        }
        if (! $cracker) {
            LoggerConfiguration::logError("Cannot get cracker: $iphonecake_id");
            $download_page->clear();
            unset($download_page);
            // khong lay duoc cracker
            continue;
        }
        // lay ver se download
        // <span class="text-warning ui-app-version">Version:</span>1.5.0<div class="ui-right-panel">
        if (!$version){
            $li_content = $link->parent->parent->parent->outertext;
            $pos_ver_1 = strpos($li_content, 'Version:</span>');
            $pos_ver_2 = strpos($li_content, '<div class="ui-right-panel">');
            if ($pos_ver_1===false || $pos_ver_2 === false) {
                LoggerConfiguration::logError("Can not get app version: $iphonecake_id");
                $download_page->clear();
                unset($download_page);
                continue;
            }
            $pos_ver_1 = $pos_ver_1 + strlen('Version:</span>');
            $version = trim(substr($li_content, $pos_ver_1, $pos_ver_2-$pos_ver_1));
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
            sleep(10);
        }
        return $content;
    }
    return false;
}