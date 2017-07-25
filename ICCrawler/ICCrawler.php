<?php
require_once dirname(__FILE__) . '/simple_html_dom.php';
require_once dirname(__FILE__) . '/logger/LoggerConfiguration.php';
require_once dirname(__FILE__) . '/DataCompress.php';
require_once dirname(__FILE__) . '/db/DBAppStore.php';

class ICCrawler
{

    const JSON_DATA_PATH = '/var/spool/www/mine/wrapper.laughworld.net/store/';

    const CSV_PATH = '/var/spool/www/mine/wrapper.laughworld.net/IphoneCakeCrawler/file/';

    const IPHONECAKE_URL = 'https://www.iphonecake.com/';

    const ITUNES_LOOKUP_URL = 'https://itunes.apple.com/lookup?id=';

    const IMAGE_PATH = '/var/spool/www/mine/wrapper.laughworld.net/IphoneCakeCrawler/file/pic/';

    const LIST_UPDATED_NEW_APPS = '/var/spool/www/mine/wrapper.laughworld.net/IphoneCakeCrawler/file/list_update_new_apps.csv';

    const MAX_CRAWL_LOOP = 1000;

    const LIMIT_ITEM_A_PAGE_CATEGORY = 30;

    private $db;

    public function __construct()
    {
        //$this->db = new DBAppStore();
    }

    public function crawlByAppId($app_id)
    {
        return $this->_crawl($app_id);
    }

    private $list_updated_new_apps = null;

    public function __destruct()
    {
        if ($this->list_updated_new_apps)
            fclose($this->list_updated_new_apps);
    }

    private $total_new_app = 0;

    private $mail_content = '';

    public function crawl($from_page, $to_page)
    {
        // $ctx = stream_context_create(array(
        // 'http' => array(
        // 'timeout' => 120,
        // 'header' => 'Connection: close'
        // )
        // ) // 120 Seconds is 20 Minutes
        
        // );
        // $this->list_updated_new_apps = fopen(self::CSV_PATH . 'new_update_' . date('dmY') . '.csv', 'w');
        // fputcsv($this->list_updated_new_apps, array(
        // 'ID',
        // 'IPHONECAKE_LINK',
        // 'VERSION',
        // 'TOTAL_DOWNLOAD'
        // ));
        $url = self::IPHONECAKE_URL . 'index.php?device=0&c=0&p=';
        for ($i = $from_page; $i < $to_page; $i ++) {
            if (isset($full_html_content) && $full_html_content) {
                $full_html_content->clear();
                $full_html_content = null;
                unset($full_html_content);
            }
            $crawl_url = $url . $i;
            LoggerConfiguration::logInfo("Crawl URL: $crawl_url");
            // $rs = file_get_contents($crawl_url, false, $ctx);
            // if (! $rs) {
            // LoggerConfiguration::logError("Error get content from URL=$crawl_url", __CLASS__, __FUNCTION__, __LINE__);
            // fclose($this->list_updated_new_apps);
            // return false;
            // }
            $rs = $this->curlGetContent($crawl_url);
            LoggerConfiguration::logInfo('Parse result to dom');
            $full_html_content = str_get_html($rs);
            if (! $full_html_content) {
                LoggerConfiguration::logError('Error parse to dom', __CLASS__, __FUNCTION__, __LINE__);
                // fclose($this->list_updated_new_apps);
                return false;
            }
            LoggerConfiguration::logInfo('Successfully parse result to dom');
            $app_items = $full_html_content->find('a[class="ui-app-item"]');
            if (! $app_items) {
                LoggerConfiguration::logError('Not found a[class=ui-app-item]', __CLASS__, __FUNCTION__, __LINE__);
                $full_html_content->clear();
                unset($full_html_content);
                break;
            }
            foreach ($app_items as &$app) {
                // lay iphonecake_id
                // sleep(rand(3,30));
                $iphonecake_id = $this->_getIphonecakeId($app->href);
                // debug
                // $iphonecake_id = '996421194';
                if (! $iphonecake_id) {
                    LoggerConfiguration::logError("Cant get appID from href={$app->href}", __CLASS__, __FUNCTION__, __LINE__);
                    continue;
                }
                if (! $this->_crawl($iphonecake_id)) {
                    continue;
                }
            }
            $full_html_content->clear();
            $full_html_content = null;
            unset($full_html_content);
            LoggerConfiguration::logInfo("Crawled URL=$crawl_url");
        }
        // fclose($this->list_updated_new_apps);
        if ($this->mail_content) {
            // send mail
            $this->_sendMail($this->mail_content, 'New App');
        }
        LoggerConfiguration::logInfo('Crawled completely');
    }

    private function _crawl($iphonecake_id)
    {
        LoggerConfiguration::logInfo("Crawl app with iphonecake_id=$iphonecake_id");
        if ($app = $this->db->checkAppExist($iphonecake_id)) {
            LoggerConfiguration::logInfo("iphonecake_id=$iphonecake_id is existing");
            // check version
            $detail = $this->_getAppDetailFromIphoneCake($iphonecake_id);
            if (! $detail) {
                LoggerConfiguration::logError("Can not get detail with iphonecake_id=$iphonecake_id", __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
            if ($detail['version'] > $app['version']) {
                // co phien ban moi
                // 1. update DB
                // 2. Save vao file csv de download update
                LoggerConfiguration::logInfo("Update iphonecake_id=$iphonecake_id version from {$app['version']} to {$detail['version']}");
                $this->db->updateVersion($iphonecake_id, $detail['version'], $detail['updated'], $detail['release_notes']);
                // fputcsv($this->list_updated_new_apps, array(
                // $iphonecake_id,
                // self::IPHONECAKE_URL . "app_{$iphonecake_id}_.html",
                // $detail['version'],
                // $detail['total_download']
                // ));
            }
            return null;
        } else {
            LoggerConfiguration::logInfo("Not found iphonecake_id=$iphonecake_id");
            // $detail = $this->_getAppDetailFromItunes($iphonecake_id);
            $detail = null;
            if (! $detail) {
                $detail = $this->_getAppDetailFromIphoneCake($iphonecake_id);
            }
            if (! $detail) {
                LoggerConfiguration::logError("Can not get detail with iphonecake_id=$iphonecake_id", __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
            // debug
            // $detail['categories'][12345] = array(
            // 'name' => 'Reference',
            // 'parent_id' =>4321
            // );
            // save app detail
            LoggerConfiguration::logInfo("Save app - iphonecake_id=$iphonecake_id");
            $saved_app = $this->db->saveApp($detail);
            if (! $saved_app) {
                return false;
            }
            // fputcsv($this->list_updated_new_apps, array(
            // $iphonecake_id,
            // self::IPHONECAKE_URL . "app_{$iphonecake_id}_.html",
            // $detail['version'],
            // $detail['total_download']
            // ));
            // Quy tac:
            // 1. Co nhieu hon 20k download
            // 2. Co it hon 5k nhung thoi gian gian release trong 1 tuan
            // 3. Co > 5k va < 20k nhung thoi gian release trong 1 thang
            if (($detail['total_download'] >= 20000) || ($detail['total_download'] <= 5000 && $detail['updated'] >= strtotime('-1 week')) || ($detail['total_download'] > 5000 && $detail['total_download'] < 20000 && $detail['updated'] > strtotime('-1 month'))) {
                $this->total_new_app ++;
                $this->mail_content .= "{$iphonecake_id}-";
                LoggerConfiguration::logInfo("NEW APP: {$iphonecake_id}");
                if ($this->total_new_app === 500) { // cu co 5 app thi lai gui 1 mail
                                                    // send mail
                    $this->_sendMail($this->mail_content, 'New App');
                    // reset
                    $this->total_new_app = 0;
                    $this->mail_content = '';
                }
            }
            LoggerConfiguration::logInfo("SUCCESSFULLY SAVE APP_ID=$iphonecake_id");
            return $detail;
        }
    }

    private function _getAppDetailFromItunes($iphonecake_id)
    {
        // $iphonecake_id cung chinh la applestore_track_id ?????
        LoggerConfiguration::logInfo('Request: ' . self::ITUNES_LOOKUP_URL . $iphonecake_id);
        $detail = json_decode(file_get_contents(self::ITUNES_LOOKUP_URL . $iphonecake_id), true);
        if (! $detail || ! isset($detail['resultCount']) || ! $detail['resultCount'] || ! $detail['results'][0]) {
            LoggerConfiguration::logError("Cant get info from itunes with id=$iphonecake_id", __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        $detail = $detail['results'][0];
        // normal detail data
        // name,bundle_id,description,price,icon,rating,rating_point,review,author,version,updated,
        // size,language,compatibility,device_type,release_notes,applestore_url,applestore_track_id,applestore_artist_id,
        $app_detail = null;
        $app_detail['name'] = $detail['trackName'];
        $app_detail['bundle_id'] = $detail['bundleId'];
        $app_detail['description'] = $detail['description'];
        $app_detail['price'] = $detail['price'];
        $app_detail['applestore_icon'] = $detail['artworkUrl100'];
        // save file icon
        $saved_icon_path = $this->_saveFile($detail['artworkUrl100'], $iphonecake_id);
        if ($saved_icon_path)
            $app_detail['icon'] = $saved_icon_path;
        else
            $app_detail['icon'] = '';
        $app_detail['rating'] = isset($detail['userRatingCount']) ? $detail['userRatingCount'] : rand(1000, 9000);
        $app_detail['rating_point'] = isset($detail['averageUserRating']) ? $detail['averageUserRating'] : rand(4, 5);
        $app_detail['review'] = rand(1000, 9000);
        $app_detail['author'] = $detail['artistName'];
        $app_detail['version'] = $detail['version'];
        $app_detail['updated'] = strtotime($detail['releaseDate']);
        $app_detail['total_download'] = rand(1000, 2000);
        if (! $app_detail['updated'])
            $app_detail['updated'] = time();
        $app_detail['size'] = $detail['fileSizeBytes'];
        $app_detail['language'] = implode(',', $detail['languageCodesISO2A']);
        $app_detail['compatibility'] = $detail['minimumOsVersion'];
        $app_detail['device_type'] = 0;
        $app_detail['release_notes'] = isset($detail['releaseNotes']) ? $detail['releaseNotes'] : '';
        $app_detail['applestore_url'] = '';
        $app_detail['applestore_track_id'] = $detail['trackId'];
        $app_detail['applestore_artist_id'] = $detail['artistId'];
        $app_detail['categories'] = array();
        $app_detail['applestore_genre_id'] = array();
        foreach ($detail['genreIds'] as $idx => $applestore_genre_id) {
            $applestore_genre_id = intval($applestore_genre_id);
            $app_detail['applestore_genre_id'][] = $applestore_genre_id;
            if (isset($detail['genres'][$idx])) {
                $app_detail['categories'][$applestore_genre_id] = array(
                    'name' => $detail['genres'][$idx],
                    'parent_id' => $detail['primaryGenreId']
                );
            }
        }
        $app_detail['screen_shots'] = array();
        foreach ($detail['screenshotUrls'] as $img) {
            $screen_shot = array(
                'applestore_path' => $img,
                'path' => ''
            );
            // save file
            $saved_path = $this->_saveFile($img, $iphonecake_id);
            if ($saved_path)
                $screen_shot['path'] = $saved_path;
            $app_detail['screen_shots'][] = $screen_shot;
        }
        $app_detail['iphonecake_id'] = $iphonecake_id;
        return $app_detail;
    }

    private function _curlGetContent($url)
    {
        $ch = curl_init();
        $timeout = 120;
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function _getAppDetailFromIphoneCake($iphonecake_id, $hasnot_submition = false)
    {
        ini_set('default_socket_timeout', 120);
        $url = self::IPHONECAKE_URL . "app_{$iphonecake_id}_.html";
        LoggerConfiguration::logInfo("Crawl app detail from URL=$url");
        // $ctx = stream_context_create(array(
        // 'http' => array(
        // 'method'=>'GET',
        // 'timeout' => 120,
        // 'header'=>'Connection: close\r\n
        // User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.106 Safari/537.36\r\n
        // Content-Type: text/html; charset=UTF-8'
        // ) // 120 Seconds is 20 Minutes
        
        // ));
        
        // $rs = file_get_contents($url, false, $ctx);
        // if (!$rs) {
        // LoggerConfiguration::logError("Error get content from URL=$url", __CLASS__, __FUNCTION__, __LINE__);
        // return false;
        // }
        $content = $this->curlGetContent($url);
        LoggerConfiguration::logInfo('Parse result to dom');
        $html_content = str_get_html($content);
        if (! $html_content) {
            LoggerConfiguration::logError('Error parse to dom', __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        LoggerConfiguration::logInfo('Successfully parse result to dom');
        $app_detail = null;
        $app_detail['name'] = $html_content->find('h2[class="ui-app-name"]', 0)->plaintext;
        $app_detail['bundle_id'] = '';
        $app_detail['description'] = trim($html_content->find('div[id="app_desc"]', 0)->innertext);
        $app_detail['price'] = $this->_getFloatFromString($html_content->find('div[class="ui-app-price"] strong', 0)->plaintext);
        $app_detail['applestore_icon'] = $html_content->find('div[class="ui-app-logo"] img', 0)->src;
        // save file icon
        $saved_icon_path = $this->_saveFile($app_detail['applestore_icon'], $iphonecake_id);
        if ($saved_icon_path)
            $app_detail['icon'] = $saved_icon_path;
        else
            $app_detail['icon'] = '';
        $app_detail['rating'] = rand(1000, 9000);
        $app_detail['rating_point'] = rand(4, 5);
        $app_detail['review'] = rand(1000, 9000);
        $app_meta = $html_content->find('dl[class="ui-app-meta"]', 0);
        if (! $app_meta)
            return false;
        $app_meta_index = 0;
        if (strpos($app_meta->find('dt', 0)->plaintext, 'Apple Watch') !== false) {
            // truong hop thong tin apple watch
            $app_meta_index = 1;
        }
        $app_detail['author'] = $app_meta->find('dd', $app_meta_index + 1)->plaintext;
        $app_detail['version'] = $app_meta->find('dd', $app_meta_index + 2)->plaintext;
        $app_detail['updated'] = DateTime::createFromFormat('Y-m-d', $app_meta->find('dd', $app_meta_index + 3)->plaintext)->getTimestamp();
        if (! $app_detail['updated'])
            $app_detail['updated'] = 0;
        $app_detail['size'] = round(trim(str_replace('MB', '', $app_meta->find('dd', $app_meta_index + 5)->plaintext))) * 1024 * 1024;
        $app_detail['total_download'] = intval(trim($app_meta->find('dd', $app_meta_index + 6)->plaintext));
        $app_detail['language'] = 'EN';
        $app_detail['compatibility'] = $app_meta->find('dd', $app_meta_index + 7)->plaintext;
        $app_detail['device_type'] = 0;
        $app_detail['release_notes'] = trim($html_content->find('div[id="app_new"]', 0)->innertext);
        if (! $app_detail['release_notes'])
            $app_detail['release_notes'] = '';
        $app_detail['applestore_url'] = '';
        $app_detail['applestore_track_id'] = $iphonecake_id;
        $app_detail['applestore_artist_id'] = 0;
        $app_detail['categories'] = array();
        $app_detail['applestore_genre_id'] = array();
        $app_detail['categories']['IPHONECAKE'] = array(
            'name' => trim($app_meta->find('dd', $app_meta_index + 0)->plaintext),
            'parent_id' => 0
        );
        $app_detail['screen_shots'] = array();
        $screen_shots = $html_content->find('div[class="ui-screenshot"] img');
        foreach ($screen_shots as $img) {
            $screen_shot = array(
                'applestore_path' => $img->src,
                'path' => ''
            );
            // save file
            $saved_path = $this->_saveFile($img->src, $iphonecake_id);
            if ($saved_path)
                $screen_shot['path'] = $saved_path;
            $app_detail['screen_shots'][] = $screen_shot;
        }
        $app_detail['iphonecake_id'] = $iphonecake_id;
        // kiem tra xem co submit link chua?
        $app_detail['total_submit_link'] = 0;
        $list_download_link = $html_content->find('ul[class="ui-app-download-list"] li div[class="ui-app-uploader"]');
        if (is_array($list_download_link)) {
            // da co link roi
            $app_detail['total_submit_link'] = count($list_download_link);
        }
        $html_content->clear();
        $html_content = null;
        unset($html_content);
        return $app_detail;
    }

    private function _checkAppExistFromItunes($iphonecake_id)
    {
        // $iphonecake_id cung chinh la applestore_track_id ?????
        LoggerConfiguration::logInfo('Request: ' . self::ITUNES_LOOKUP_URL . $iphonecake_id);
        $detail = json_decode(file_get_contents(self::ITUNES_LOOKUP_URL . $iphonecake_id), true);
        if (! $detail || ! isset($detail['resultCount']) || ! $detail['resultCount'] || ! $detail['results'][0]) {
            LoggerConfiguration::logError("Cant get info from itunes with id=$iphonecake_id", __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return $detail['results'][0]['version'];
    }

    private function _getAppVersionAndCheckLinkFromIphoneCake($iphonecake_id)
    {
        $url = self::IPHONECAKE_URL . "app_{$iphonecake_id}_.html";
        LoggerConfiguration::logInfo("Crawl app detail from URL=$url");
        $content = $this->curlGetContent($url);
        LoggerConfiguration::logInfo('Parse result to dom');
        $html_content = str_get_html($content);
        if (! $html_content) {
            LoggerConfiguration::logError('Error parse to dom', __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        LoggerConfiguration::logInfo('Successfully parse result to dom');
        $app_meta = $html_content->find('dl[class="ui-app-meta"]', 0);
        if (! $app_meta)
            return false;
        $app_meta_index = 0;
        if (strpos($app_meta->find('dt', 0)->plaintext, 'Apple Watch') !== false) {
            // truong hop thong tin apple watch
            $app_meta_index = 1;
        }
        $version = $app_meta->find('dd', $app_meta_index + 2)->plaintext;
        if (! $version) {
            LoggerConfiguration::logError('Not found version', __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        LoggerConfiguration::logInfo("Current Version: $version");
        $total_download = intval(trim($app_meta->find('dd', $app_meta_index + 6)->plaintext));
        $release_notes = $html_content->find('div[id="app_new"]', 0)->innertext;
        $release_notes = $release_notes ? trim($release_notes) : '';
        // Kiem tra xem co submit link nao chua?
        $first_download_link = $html_content->find('ul[class="ui-app-download-list"] li', 2);
        $status = 1; // 1 - khong can update; 0 - can update
        if ($first_download_link) {
            // kiem tra version cua app chua co trong plaintext => coi nhu co version moi
            $first_download_link = $first_download_link->plaintext;
            if (strpos($first_download_link, $version) === false) {
                // khong ton tai => ver submit khong trung voi version cua thong tin app => co update
                $status = 0;
            }
        } else {
            // chua co link download
            $status = 0;
        }
        $html_content->clear();
        $html_content = null;
        unset($html_content);
        return array(
            'status' => $status,
            'ver' => $version,
            'total_download' => $total_download,
            'release_notes' => $release_notes
        );
    }

    private function _getIphonecakeId($href)
    {
        if (preg_match('/[0-9]{5,20}/', $href, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function _saveFile($url, $app_id)
    {
        $current_month = date('Y/m');
        $save_path = self::IMAGE_PATH . $current_month . '/' . $app_id;
        if (! file_exists($save_path)) {
            if (! mkdir($save_path, 777, true)) {
                LoggerConfiguration::logError("Cant make dir: $save_path", __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
        }
        $info = pathinfo($url);
        $ext = $info['extension'];
        $save_file = $save_path . '/' . md5($url) . '.' . $ext;
        if (! file_exists($save_file)) {
            if (! file_put_contents($save_file, file_get_contents($url))) {
                LoggerConfiguration::logError("Cant save file from $url => $save_file", __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
        }
        return str_replace(self::IMAGE_PATH, '', $save_file);
    }

    private function _getFloatFromString($str)
    {
        if (preg_match('/([0-9\.\,]+)/', $str, $match)) { // search for number that may contain '.'
            return floatval(str_replace(',', '.', $match[0]));
        } else {
            return floatval($str); // take some last chances with floatval
        }
    }

    public function setFeature()
    {
        LoggerConfiguration::logInfo(__FUNCTION__);
        $list_category = array(
            0 => 0,
            1 => 6,
            2 => 17,
            3 => 36,
            4 => 2,
            5 => 8,
            6 => 40,
            7 => 38,
            8 => 3,
            9 => 27,
            // 10 => 6,
            11 => 25,
            12 => 30,
            13 => 26,
            // 14 => 17,
            15 => 35,
            // 16 => 6,
            17 => 16,
            18 => 11,
            19 => 19,
            20 => 7,
            21 => 18,
            22 => 1,
            23 => 9,
            24 => 21
        );
        foreach ($list_category as $ic_category_id => $category_id) {
            $iphonecake_ids = null;
            $url = self::IPHONECAKE_URL . "index.php?device=0&p=1&c={$ic_category_id}";
            LoggerConfiguration::logInfo("URL=$url");
            $html_content = file_get_html($url);
            if (! $html_content) {
                // sleep(3);
                LoggerConfiguration::logInfo("Try URL=$url");
                $html_content = file_get_html($url);
            }
            if (! $html_content) {
                LoggerConfiguration::logError("Can not get content from url=$url", __CLASS__, __FILE__, __LINE__);
                continue;
            }
            $featured_list = $html_content->find('div[id="featured"] a[class="ui-app-item"]');
            if (! $featured_list) {
                LoggerConfiguration::logError('Not found featured list', __CLASS__, __FILE__, __LINE__);
                continue;
            }
            foreach ($featured_list as $featured) {
                $iphonecake_id = $this->_getIphonecakeId($featured->href);
                if ($this->_crawl($iphonecake_id))
                    $iphonecake_ids[] = $iphonecake_id;
            }
            if ($ic_category_id === 0) {
                // featured for all
                LoggerConfiguration::logInfo('Set featured for all with list_app:' . print_r($iphonecake_ids, true));
                $this->db->setFeatured($iphonecake_ids);
            } else {
                LoggerConfiguration::logInfo("Set featured for category_id=$category_id with list_app:" . print_r($iphonecake_ids, true));
                $this->db->setFeaturedCategories($category_id, $iphonecake_ids);
            }
        }
    }

    public function generateCategoryListDataJson()
    {
        $list_categories = $this->db->listCategories();
        if ($list_categories) {
            file_put_contents(self::JSON_DATA_PATH . 'list_categories.json', json_encode(array(
                'data_time' => time(),
                'request' => 'list_categories',
                'data' => $list_categories
            )));
        }
    }

    public function generateJsonData()
    {
        // top feature
        $top_feature = array(
            'data_time' => time(),
            'request' => 'feature',
            'top_free' => array(),
            'top_paid' => array()
        );
        $top_free = $this->db->getFreeTopFeature();
        if ($top_free) {
            $top_feature['top_free'] = $top_free;
        }
        $top_paid = $this->db->getTopFeature();
        if ($top_paid) {
            $top_feature['top_paid'] = $top_paid;
        }
        file_put_contents(self::JSON_DATA_PATH . 'feature.json', json_encode($top_feature));
        // list categories
        $list_categories = $this->db->listCategories();
        if ($list_categories) {
            file_put_contents(self::JSON_DATA_PATH . 'list_categories.json', json_encode(array(
                'data_time' => time(),
                'request' => 'list_categories',
                'data' => $list_categories
            )));
            foreach ($list_categories as $category) {
                $top_feature = array(
                    'data_time' => time(),
                    'request' => 'feature_category',
                    'category_id' => $category['id'],
                    'top_free' => array(),
                    'top_paid' => array()
                );
                $top_free = $this->db->getFreeTopFeatureByCategory($category['id']);
                if ($top_free) {
                    $top_feature['top_free'] = $top_free;
                }
                $top_paid = $this->db->getTopFeatureByCategory($category['id']);
                if ($top_paid) {
                    $top_feature['top_paid'] = $top_paid;
                }
                file_put_contents(self::JSON_DATA_PATH . "feature_category_{$category['id']}.json", json_encode($top_feature));
                // list moi nhat theo thoi gian
                $total_app = $this->db->countAppByCategory($category['id']);
                $data = array(
                    'data_time' => time(),
                    'request' => 'by_category',
                    'category_id' => $category['id'],
                    'total_page' => round($total_app / self::LIMIT_ITEM_A_PAGE_CATEGORY) + 1,
                    'data' => array()
                );
                $page = 0;
                while (true) {
                    $list_app = $this->db->getAppByCategory($category['id'], self::LIMIT_ITEM_A_PAGE_CATEGORY, $page);
                    $data['data'] = $list_app ? $list_app : array();
                    $page = $page + 1;
                    $data['current_page'] = $page;
                    file_put_contents(self::JSON_DATA_PATH . "category_{$category['id']}_{$page}.json", json_encode($data));
                    if (count($list_app) < self::LIMIT_ITEM_A_PAGE_CATEGORY) {
                        break;
                    }
                }
            }
        }
    }

    public function updateTotalDownload()
    {
        $limit = 100;
        $page = 0;
        while (true) {
            $list_app = $this->db->listApps($limit, $page);
            if (! $list_app) {
                break;
            }
            $page ++;
            foreach ($list_app as $iphonecake_id) {
                $url = self::IPHONECAKE_URL . "app_{$iphonecake_id}_.html";
                $html_content = file_get_html($url);
                if (! $html_content) {
                    // sleep(5);
                    // try 1 lan nua
                    LoggerConfiguration::logInfo("Try URL=$url");
                    $html_content = file_get_html($url);
                    if (! $html_content) {
                        LoggerConfiguration::logError("Error get content from url=$url", __CLASS__, __FUNCTION__, __LINE__);
                        return false;
                    }
                }
                $app_meta = $html_content->find('dl[class="ui-app-meta"]', 0);
                $app_meta_index = 0;
                if (strpos($app_meta->find('dt', 0)->plaintext, 'Apple Watch') !== false) {
                    // truong hop thong tin apple watch
                    $app_meta_index = 1;
                }
                $total_download = intval($app_meta->find('dd', $app_meta_index + 6)->plaintext);
                if ($total_download) {
                    LoggerConfiguration::logInfo("Update app_id=$iphonecake_id total_download=$total_download");
                    $this->db->updateTotalDownload($iphonecake_id, $total_download);
                }
            }
            if (count($list_app) < $limit) {
                break;
            }
        }
    }

    public function getTopForDownloadToCSV($limit, $page = 0)
    {
        $index = $page + 1;
        $fp = fopen(self::CSV_PATH . "top{$limit}_{$index}.csv", 'w');
        $list_app = $this->db->listAppsForDownload($limit, $page);
        fputcsv($fp, array(
            'ID',
            'IPHONECAKE_ID',
            'IS_FEATURE',
            'IS_CATE_FEATURE',
            'TOTAL_DOWNLOAD',
            'DOWNLOAD_LINK'
        ));
        $stt = 1;
        foreach ($list_app as $fields) {
            $fields['link'] = self::IPHONECAKE_URL . "app_{$fields['iphonecake_id']}_.html";
            fputcsv($fp, $fields);
        }
        fclose($fp);
    }

    public function updateCategoryIcon()
    {
        $list_categories = $this->db->listCategories();
        foreach ($list_categories as $category) {
            $top1_app = $this->db->getTop1AppByCategory($category['id']);
            if ($top1_app) {
                $image = $top1_app['icon'];
                $applestore_image = $top1_app['applestore_icon'];
                LoggerConfiguration::logInfo("Update category_id={$category['id']} by image=$image and applestore_image=$applestore_image");
                $this->db->updateCategoryIcon($category['id'], $image, $applestore_image);
            }
        }
    }

    public function search($keyword)
    {
        return json_encode(array(
            'data_time' => time(),
            'request' => 'search',
            'keyword' => $keyword,
            'data' => $this->db->searchApp($keyword)
        ));
    }

    public function curlGetContent($url)
    {
        // $url = 'https://www.iphonecake.com/app_1094591345_.html';
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
        $err = curl_errno($ch);
        curl_close($ch);
        return $err ? false : $content;
    }

    public function nofifyUpdate($from_page, $to_page)
    {
        $page = $from_page;
        $limit = 200;
        $total_app = 0;
        $mail_content = '';
        while ($page < $to_page) { // chi can kiem tra 5000 app thoi
            $list_app = $this->db->listAppsByTotalDownload($limit, $page);
            $page ++;
            if (! $list_app) {
                break;
            }
            foreach ($list_app as &$app) {
                // kiem tra xem co ton tai tren itunes khong
                $itunes_version = $this->_checkAppExistFromItunes($app['iphonecake_id']);
                if (! $itunes_version) {
                    // khong ton tai => bo qua
                    continue;
                }
                // kiem tra version app tren iphonecake
                $detail = $this->_getAppVersionAndCheckLinkFromIphoneCake($app['iphonecake_id']);
                if (! $detail) {
                    continue;
                }
                if (($itunes_version > $detail['ver']) || ($detail['ver'] >= $app['version'] && $detail['status'] === 0)) {
                    // Quy tac:
                    // 1. Co nhieu hon 20k download
                    // 2. Co it hon 5k nhung thoi gian gian release trong 1 tuan
                    // 3. Co > 5k va < 20k nhung thoi gian release trong 1 thang
                    if (($detail['total_download'] >= 20000) || ($detail['total_download'] <= 5000 && $detail['updated'] >= strtotime('-1 week')) || ($detail['total_download'] > 5000 && $detail['total_download'] < 20000 && $detail['updated'] > strtotime('-1 month'))) {
                        // co update va chua ai submit link
                        // dung >= vi co the crontab check update da chay roi
                        $total_app ++;
                        $mail_content .= "{$app['iphonecake_id']}-";
                        LoggerConfiguration::logInfo("UPDATED: {$app['iphonecake_id']}");
                        if ($total_app === 500) { // cu co 5 app thi lai gui 1 mail
                                                  // send mail
                            $this->_sendMail($mail_content, 'Update App');
                            // reset
                            $total_app = 0;
                            $mail_content = '';
                        }
                    }
                }
                if ($itunes_version > $app['version']) {
                    // co update => cap nhat lai version vao DB
                    $this->db->updateVersion($app['iphonecake_id'], $itunes_version, time(), $detail['release_notes'], $detail['total_download']);
                }
            }
            if (count($list_app) < $limit) {
                break;
            }
        }
        if ($mail_content) {
            // send mail
            $this->_sendMail($mail_content, 'Update App');
        }
    }
    public function checkUpdateByWhiteListTest($iphonecake_id){
        $itunes_version = $this->_checkAppExistFromItunes($iphonecake_id);
        if (! $itunes_version) {
            // khong ton tai => bo qua
            return;
        }
        // kiem tra version app tren iphonecake
        LoggerConfiguration::logInfo("Check whitelist for $iphonecake_id");
        $detail = $this->_getAppVersionAndCheckLinkFromIphoneCake($iphonecake_id);
        if (! $detail) {
            return;
        }
        //$current_detail = $this->db->checkAppExist($iphonecake_id);
        //if (!$current_detail){
        //    continue;
        //}
        //if ($itunes_version > $detail['ver']) {
            //    $current_time = time();
            //    if ($detail['ver']>$current_detail['version'] && ($current_time-intval($current_detail['updated'])>(3*60*60))) {
            // lan truoc chua update va da sau 3h truoc (co the lan truoc bi loi)
            //$mail_content .= "{$iphonecake_id}-";
            // goi API MTuan de tu dong submit version moi len
            //                          [9/26/2016 8:51:33 AM] ATI Nguyen Manh Tuan: nếu chưa thì download về (download về rồi thì thôi, dựa vào version check)
            //                          [9/26/2016 8:51:41 AM] ATI Nguyen Manh Tuan: sau đo gọi sang bên anh submit
            //                          [9/26/2016 8:52:35 AM] ATI Nguyen Manh Tuan: http://….submit.php?ipc_id=…&new_ver=…&old_ver=…&cracker=<tên thành mình download>
            //                          [9/26/2016 8:52:43 AM] ATI Nguyen Manh Tuan: vẫn như cũ, thêm tham số cracker thôi
            $rand = rand(10000,99999);
            $download_cmd = "php /Library/WebServer/Documents/AppStoreWrapper/src/Application/IPADownloader/download_app.php {$iphonecake_id} {$rand}";
            LoggerConfiguration::logInfo("Try to download app: $download_cmd");
            exec($download_cmd);
            $tmp_file = "/Library/WebServer/Documents/AppStoreWrapper/src/Application/IPADownloader/tmp/{$iphonecake_id}_{$rand}";
            LoggerConfiguration::logInfo("tmp_file: $tmp_file");
            if ($out = json_decode(file_get_contents($tmp_file), true)) {
                LoggerConfiguration::logInfo(print_r($out, true));
                //unlink($tmp_file);
                //if (is_array($out) && isset($out['cracker']) && isset($out['name'])) {
                if (is_array($out)) {
                    ////?ipc_id={$iphonecake_id}&ipaname={$out['name']}&version={$detail['ver']}&cracker={$out['cracker']}
                	$url = null;
                    // $url = "http://163.172.122.67/Job/Api/submit.php?ipc_id={$iphonecake_id}&old_ver={$detail['ver']}&new_ver={$itunes_version}&cracker=cartoonfk";
                	$url = "http://wrappersrv.laughworld.net/Job/Api/submit_orgipa.php?ipc_id={$iphonecake_id}&version={$itunes_version}&cracker=cartoonfk&ipaname={$iphonecake_id}.ipa";
                    //$background_cmd = 'wget "' .$url. '"';
                    LoggerConfiguration::logInfo($url);
                    //exec($background_cmd);
                	requestGetByCurl($url);
                    //$number ++;
                }
            }
            else
                LoggerConfiguration::logError("Not found file: $tmp_file");
                //LoggerConfiguration::logInfo("UPDATED: $iphonecake_id");
                // co update => cap nhat lai version vao DB
                //$this->db->updateVersion($iphonecake_id, $detail['version'], $current_time, $detail['release_notes'], $detail['total_download']);
                //    }
                 
        //}
    }

    public function checkUpdateByWhiteList()
    {
        // lay danh sach link trong file
        $handle = fopen('/Library/WebServer/Documents/AppStoreWrapper/src/Application/IPADownloader/update_white_list_10app.txt', 'r');
        //$last_mail_content_file = '/Library/WebServer/Documents/AppStoreWrapper/src/Application/IPADownloader/tmp/WhiteList_lastmail.txt';
        //$last_mail_content = @file_get_contents($last_mail_content_file, 'r');
        $mail_content = '';
        if ($handle) {
			$number = 0;
            while (($iphonecake_id = fgets($handle)) !== false) {
                // process the line read.
                $iphonecake_id = trim($iphonecake_id);
                if (! $iphonecake_id)
                    continue;
                $itunes_version = $this->_checkAppExistFromItunes($iphonecake_id);
                if (! $itunes_version) {
                    // khong ton tai => bo qua
                    continue;
                }
                // kiem tra version app tren iphonecake
				LoggerConfiguration::logInfo("Check whitelist for $iphonecake_id");
                $detail = $this->_getAppVersionAndCheckLinkFromIphoneCake($iphonecake_id);
                if (! $detail) {
                    continue;
                }
                //$current_detail = $this->db->checkAppExist($iphonecake_id);
                //if (!$current_detail){
                //    continue;
                //}
                if ($itunes_version === $detail['ver']) {
                //    $current_time = time();
                //    if ($detail['ver']>$current_detail['version'] && ($current_time-intval($current_detail['updated'])>(3*60*60))) {
                         // lan truoc chua update va da sau 3h truoc (co the lan truoc bi loi)
                         $mail_content .= "{$iphonecake_id}-";
                         // goi API MTuan de tu dong submit version moi len
//                          [9/26/2016 8:51:33 AM] ATI Nguyen Manh Tuan: nếu chưa thì download về (download về rồi thì thôi, dựa vào version check)
//                          [9/26/2016 8:51:41 AM] ATI Nguyen Manh Tuan: sau đo gọi sang bên anh submit
//                          [9/26/2016 8:52:35 AM] ATI Nguyen Manh Tuan: http://….submit.php?ipc_id=…&new_ver=…&old_ver=…&cracker=<tên thành mình download>
//                          [9/26/2016 8:52:43 AM] ATI Nguyen Manh Tuan: vẫn như cũ, thêm tham số cracker thôi
                         $rand = rand(10000,99999);
						 $download_cmd = "php /Library/WebServer/Documents/AppStoreWrapper/src/Application/IPADownloader/download_app.php {$iphonecake_id} {$rand}";
						 LoggerConfiguration::logInfo("Try to download app: $download_cmd");
                         exec($download_cmd);
                         $tmp_file = "/Library/WebServer/Documents/AppStoreWrapper/src/Application/IPADownloader/tmp/{$iphonecake_id}_{$rand}";
						 LoggerConfiguration::logInfo("tmp_file: $tmp_file");
                         if ($out = json_decode(file_get_contents($tmp_file), true)) {
							 LoggerConfiguration::logInfo(print_r($out, true));
                             unlink($tmp_file);
                             //if (is_array($out) && isset($out['cracker']) && isset($out['name'])) {
                             if (is_array($out)) {
                                 ////?ipc_id={$iphonecake_id}&ipaname={$out['name']}&version={$detail['ver']}&cracker={$out['cracker']}
                                 // $url = "http://163.172.122.67/Job/Api/submit.php?ipc_id={$iphonecake_id}&old_ver={$detail['ver']}&new_ver={$itunes_version}&cracker=cartoonfk";
                             	$url = "http://wrappersrv.laughworld.net/Job/Api/submit_orgipa.php?ipc_id={$iphonecake_id}&version={$itunes_version}&cracker=cartoonfk&ipaname={$iphonecake_id}.ipa";
                             	//$background_cmd = 'wget "' .$url. '"';
                             	LoggerConfiguration::logInfo($url);
                             	//exec($background_cmd);
                             	requestGetByCurl($url);
								 $number ++;
								 $rand_sleep_time = rand(300, 600);
								 LoggerConfiguration::logInfo("Wait random $rand_sleep_time mins");
								 sleep($rand_sleep_time);
								 LoggerConfiguration::logInfo('Continue');
                             }
                         }
						 else 
							 LoggerConfiguration::logError("Not found file: $tmp_file");
                         //LoggerConfiguration::logInfo("UPDATED: $iphonecake_id");
                         // co update => cap nhat lai version vao DB
                         //$this->db->updateVersion($iphonecake_id, $detail['version'], $current_time, $detail['release_notes'], $detail['total_download']);
                //    }
                 
                }
// 				if($number==50) {
// 					$this->_sendMail($mail_content, 'WhiteList');
// 					$mail_content = '';
// 					$number = 0;
// 				}
            }
            
            fclose($handle);
            // send Mail
//             if ($mail_content) {
//                 $this->_sendMail($mail_content, 'WhiteList');
//             }
        } else {
            // error opening the file.
        }
    }

    private function _sendMail(&$mail_content, $type)
    {
//         $mail_content = trim($mail_content);
//         if (! $mail_content)
//             return null;
//         LoggerConfiguration::logInfo('Send Mail');
//         $date = date('YmdHis');
//         $mail_content = "Subject: $type $date" . PHP_EOL . "To: laughworld.net@gmail.com" . PHP_EOL . PHP_EOL . "$mail_content";
//         $send_mail_tmp_path = '/var/spool/www/mine/wrapper.laughworld.net/IphoneCakeCrawler/tmp/send_mail/';
//         $file_mail_content = "{$send_mail_tmp_path}{$date}.txt";
//         if (! file_exists($send_mail_tmp_path)) {
//             if (! mkdir($send_mail_tmp_path, 777, true)) {
//                 LoggerConfiguration::logError("Cannot mkdir $send_mail_tmp_path", __CLASS__, __FUNCTION__, __LINE__);
//                 return false;
//             }
//         }
//         if (! file_put_contents($file_mail_content, $mail_content)) {
//             LoggerConfiguration::logError("Cant put content to file: $file_mail_content", __CLASS__, __FUNCTION__, __LINE__);
//             return false;
//         }
//         $list_mail = 'mactiencong@gmail.com';
//         $send_mail_command = "/usr/sbin/ssmtp $list_mail < $file_mail_content";
//         LoggerConfiguration::logInfo("send mail command: $send_mail_command");
//         exec($send_mail_command);
//cartoonforkids.org/minecron/Application/Service/send_mail/send_mail.php
            $url = 'http://cartoonforkids.org/minecron/Application/Service/send_mail/send_mail.php';
            $postData = '';
            $params = array(
                'content'=>$mail_content,
                'type'=>$type
            );
            // create name value pairs seperated by &
            foreach ($params as $k => $v) {
                $postData .= $k . '=' . $v . '&';
            }
            $postData = rtrim($postData, '&');
            
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_POST, count($postData));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $output = curl_exec($ch);
            
            curl_close($ch);
            return $output;
    }
}

function requestGetByCurl($url) {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL,$url);
	// in real life you should use something like:
	// curl_setopt($ch, CURLOPT_POSTFIELDS, 
	//          http_build_query(array('postvar1' => 'value1')));

	// receive server response ...
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$server_output = curl_exec ($ch);

	curl_close ($ch);

	// further processing ....
	return $server_output;
}