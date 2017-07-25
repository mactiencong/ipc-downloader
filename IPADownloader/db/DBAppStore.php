<?php
require_once dirname(__FILE__) . '/Database.php';
require_once dirname(__FILE__) . '/../logger/LoggerConfiguration.php';

class DBAppStore
{

    private $db = null;

    public function __construct()
    {
        $this->db = new Database();
    }
    
    public function listCategories(){
        $query = "SELECT id,name,image,applestore_image FROM store_categories";
        LoggerConfiguration::logInfo($query);
        $list_categories = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($cat = $result->fetch_assoc()) {
                    $cat['description'] = $cat['name'];
                    $cat['icon'] =  $cat['applestore_image'];
                    $cat['order'] = $cat['id'];
                    $list_categories[] = $cat;
                }
                $this->db->free_result($result);
            }
            return $list_categories;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function getTop1AppByCategory($category_id){
        $query = "SELECT a.* FROM store_app_category c
        INNER JOIN store_apps a ON c.app_id=a.id WHERE c.category_id=$category_id ORDER BY a.total_download DESC LIMIT 1";
        LoggerConfiguration::logInfo($query);
        try {
            if ($result = $this->db->query($query)) {
                if ($app = $result->fetch_assoc()) {
                    $this->db->free_result($result);
                    return $app;
                }
                $this->db->free_result($result);
            }
            return null;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
    }
    
    public function updateCategoryIcon($category_id, $image, $applestore_image){
        $query = "UPDATE store_categories SET image='$image',applestore_image='$applestore_image' WHERE id=$category_id";
        try {
            $result = $this->db->query($query);
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return true;
    }
    
    public function searchApp($keyword){
        $keyword = preg_replace('/\s+/', '%', $keyword);
        $keyword = mb_strtolower($this->db->real_escape_string($keyword));
        $query = "SELECT * FROM store_apps WHERE LOWER(name) LIKE '%$keyword%'
        ORDER BY is_feature DESC, total_download DESC LIMIT 50";
        LoggerConfiguration::logInfo($query);
        $list_app = array();
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $this->_appDetail($app);
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return array();
        }
        return array();
    }
    
    public function setFeaturedCategories($category_id, &$iphonecake_ids){
        $iphonecake_ids_filter = implode(',', $iphonecake_ids);
        $query = "UPDATE store_app_category SET is_feature=1 WHERE category_id=$category_id AND app_id IN 
        (
        SELECT id FROM store_apps WHERE iphonecake_id IN ($iphonecake_ids_filter)
        )";
        try {
            $result = $this->db->query($query);
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return true;
    }
    
    public function setFeatured(&$iphonecake_ids){
        $iphonecake_ids_filter = implode(',', $iphonecake_ids);
        $query = "UPDATE store_apps SET is_feature=1 WHERE iphonecake_id IN ($iphonecake_ids_filter)";
        try {
            $result = $this->db->query($query);
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return true;
    }
    
    public function updateTotalDownload($iphonecake_id, $total_download){
        $query = "UPDATE store_apps SET total_download=$total_download WHERE iphonecake_id=$iphonecake_id";
        try {
            $result = $this->db->query($query);
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return true;
    }

    public function checkAppExist($iphonecake_id)
    {
        $query = "SELECT iphonecake_id,version,updated FROM store_apps WHERE iphonecake_id=$iphonecake_id LIMIT 1";
        LoggerConfiguration::logInfo($query);
        try {
            $result = $this->db->query($query);
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if ($this->db->num_rows($result) === 1) {
            return $result->fetch_assoc();
        }
        return false;
    }
    
    
    public function updateVersion($iphonecake_id, $new_version, $update, &$release_notes, $total_download=null){
        $release_notes_update = '';
        if ($release_notes) {
            $release_notes = $this->db->real_escape_string($release_notes);
            $release_notes_update = ",release_notes='$release_notes'";
        }
        $total_download_update = '';
        if ($total_download) {
            $total_download_update = ",total_download=$total_download";
        }
        $query = "UPDATE store_apps SET version='$new_version',updated=$update,is_download_ipa=0 $release_notes_update $total_download_update WHERE iphonecake_id=$iphonecake_id";
        try {
            LoggerConfiguration::logInfo($query);
            $result = $this->db->query($query);
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return true;
    }

    public function saveCategories(&$categories)
    {
        $save_values = null;
        $current_time = time();
        foreach ($categories as $applestore_genre_id => $category) {
            $name = $this->db->real_escape_string($category['name']);
            $parent_id = $category['parent_id'];
            if ($applestore_genre_id !== 'IPHONECAKE'){
                $save_values = "('$name',$applestore_genre_id,$parent_id,$current_time)";
                $query = "INSERT INTO store_categories(name,applestore_genre_id,parent_id,update_time) VALUES $save_values
                ON DUPLICATE KEY UPDATE update_time={$current_time},applestore_genre_id=$applestore_genre_id,parent_id=$parent_id";
            }
            else {
                // truong hop category tu iphonecake thi ko cap nhat cac truong nay, vi khong co
                $save_values = "('$name',$current_time)";
                $query = "INSERT INTO store_categories(name,update_time) VALUES $save_values
                ON DUPLICATE KEY UPDATE update_time={$current_time}";
            }
            LoggerConfiguration::logInfo($query);
            try {
                if ($this->db->query($query)) {
                    continue;
                }
            } catch (Exception $e) {
                LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
                continue;
            }
        }
        return true;
    }

    public function getCategoriesByAppleGenre($applestore_genre_ids)
    {
        $limit = count($applestore_genre_ids);
        $applestore_genre_ids = implode(',', $applestore_genre_ids);
        $query = "SELECT id,applestore_genre_id FROM store_categories WHERE applestore_genre_id IN ($applestore_genre_ids) LIMIT $limit";
        LoggerConfiguration::logInfo($query);
        try {
            if ($result = $this->db->query($query)) {
                $categories = null;
                while ($c = $result->fetch_assoc()) {
                    $categories[] = $c;
                }
                $this->db->free_result($result);
                return $categories;
            }
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return null;
    }

    public function saveApp(&$detail)
    {
        $this->db->autocommit(false);
        $app_id = $this->saveAppDetail($detail);
        if (! $app_id) {
            $this->db->rollback();
            return false;
        }
        // save category
        if (! $this->saveCategories($detail['categories'])) {
            $this->db->rollback();
            return false;
        }
        // save Images
        if ($this->saveAppImages($app_id, $detail['screen_shots']) ===false) {
            $this->db->rollback();
            return false;
        }
        $category_ids = $this->getCategoriesByAppleGenre($detail['applestore_genre_id']);
        if ($category_ids) {
            // save category-app relation
            if (! $this->saveAppCategory($app_id, $category_ids)) {
                $this->db->rollback();
                return false;
            }
        }
        $this->db->commit();
        return $detail;
    }

    public function saveAppCategory($app_id, $category_ids)
    {
        $save_values = null;
        $current_time = time();
        foreach ($category_ids as $category) {
            $category_id = $category['id'];
            $applestore_genre_id = $category['applestore_genre_id'];
            $save_values[] = "($app_id,$category_id,$applestore_genre_id)";
        }
        if (! $save_values) {
            return false;
        }
        $save_values = implode(',', $save_values);
        $query = "INSERT INTO store_app_category(app_id,category_id,applestore_genre_id) VALUES $save_values";
        LoggerConfiguration::logInfo($query);
        try {
            if ($this->db->query($query)) {
                return true;
            }
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }

    public function saveAppDetail(&$detail)
    {
        $current_time = time();
        $save_value = '';
        $list_filed = explode(',', 'name,bundle_id,iphonecake_id,description,price,applestore_icon,icon,rating,rating_point,review,author,version,updated,size,language,compatibility,device_type,release_notes,applestore_url,applestore_track_id,applestore_artist_id,total_download');
        foreach ($list_filed as $k){
            $val = isset($detail[$k])?$this->db->real_escape_string($detail[$k]):'';
            $save_value[] = "'{$val}'";
        }
        $save_value[] =  $current_time;
        $save_value = implode(',', $save_value);
        $query = "INSERT INTO store_apps(name,bundle_id,iphonecake_id,description,price,applestore_icon,icon,rating,rating_point,review,author,version,updated,
            size,language,compatibility,device_type,release_notes,applestore_url,applestore_track_id,applestore_artist_id,total_download,crawl_time)
            VALUES ($save_value) ON DUPLICATE KEY UPDATE crawl_time=$current_time";
        LoggerConfiguration::logInfo($query);
        try {
            if ($this->db->query($query)) {
                return $this->db->insert_id();
            }
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }

    public function saveAppImages($app_id, &$images)
    {
        $save_values = null;
        $current_time = time();
        foreach ($images as $img) {
            $path = $this->db->real_escape_string($img['path']);
            $applestore_path = $this->db->real_escape_string($img['applestore_path']);
            $save_values[] = "($app_id,'$path','$applestore_path')";
        }
        if (! $save_values) {
            return null;
        }
        $save_values = implode(',', $save_values);
        $query = "INSERT INTO store_app_images(app_id,path,applestore_path) VALUES $save_values";
        LoggerConfiguration::logInfo($query);
        try {
            if ($this->db->query($query)) {
                return true;
            }
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function getTopFeature() {
        $query = 'SELECT * FROM store_apps WHERE is_feature=1 LIMIT 30';
        LoggerConfiguration::logInfo($query);
        $list_app = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $this->_appDetail($app);
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function getFreeTopFeature() {
        $query = 'SELECT * FROM store_apps WHERE price=0 LIMIT 30';
        LoggerConfiguration::logInfo($query);
        $list_app = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $this->_appDetail($app);
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function listApps($limit, $page){
        $start = $page * $limit;
        $query = "SELECT iphonecake_id FROM store_apps LIMIT $start,$limit";
        LoggerConfiguration::logInfo($query);
        $list_app = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $app['iphonecake_id'];
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function listAppsByTotalDownload($limit, $page){
        $start = $page * $limit; // 100 * 200
        $query = "SELECT iphonecake_id,version FROM store_apps LIMIT $start,$limit";
        LoggerConfiguration::logInfo($query);
        $list_app = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $app;
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function listAppsForDownload($limit, $page){
        $start = $page * $limit;
        $query = "SELECT a.id,a.iphonecake_id,a.is_feature,c.is_feature AS is_category_feature,a.total_download FROM store_apps a
        INNER JOIN store_app_category c ON a.id=c.app_id
        GROUP BY a.id
        ORDER BY a.is_feature DESC,c.is_feature DESC, a.total_download DESC
        LIMIT $start,$limit";
        LoggerConfiguration::logInfo($query);
        $list_app = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $app;
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function getTopFeatureByCategory($category_id) {
        $query = "SELECT a.* FROM store_app_category c
            INNER JOIN store_apps a ON c.app_id=a.id WHERE c.category_id=$category_id AND c.is_feature=1 LIMIT 30";
        LoggerConfiguration::logInfo($query);
        $list_app = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $this->_appDetail($app, $category_id);
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function getFreeTopFeatureByCategory($category_id) {
        $query = "SELECT a.* FROM store_app_category c
        INNER JOIN store_apps a ON c.app_id=a.id WHERE c.category_id=$category_id AND a.price=0 LIMIT 30";
        LoggerConfiguration::logInfo($query);
        $list_app = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $this->_appDetail($app, $category_id);
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function getAppScreenshots($app_id) {
        $query = "SELECT applestore_path FROM store_app_images WHERE app_id=$app_id LIMIT 10";
        LoggerConfiguration::logInfo($query);
        $list_img = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($img = $result->fetch_assoc()) {
                    $list_img[] = $img['applestore_path'];
                }
                $this->db->free_result($result);
            }
            return $list_img;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function getAppCategories($app_id) {
        $query = "SELECT category_id FROM store_app_category WHERE app_id=$app_id LIMIT 10";
        LoggerConfiguration::logInfo($query);
        $list_categories = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($cat = $result->fetch_assoc()) {
                    $list_categories[] = $cat['category_id'];
                }
                $this->db->free_result($result);
            }
            return $list_categories;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function countAppByCategory($category_id){
        //SELECT count(*) AS total FROM store_app_category c INNER JOIN store_apps a ON c.app_id=a.id WHERE c.category_id=1
        $query = "SELECT count(*) AS total FROM store_app_category c INNER JOIN store_apps a ON c.app_id=a.id WHERE c.category_id=$category_id";
        LoggerConfiguration::logInfo($query);
        try {
            if ($result = $this->db->query($query)) {
                if ($total = $result->fetch_assoc()) {
                    return $total['total'];
                }
            }
            return 0;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return 0;
    }
    
    public function getAppByCategory($category_id, $limit, $page) {
        $start = $limit * $page;
        $query = "SELECT a.* FROM store_app_category c
        INNER JOIN store_apps a ON c.app_id=a.id WHERE c.category_id=$category_id
        ORDER BY a.updated DESC
        LIMIT $start, $limit";
        LoggerConfiguration::logInfo($query);
        $list_app = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $this->_appDetail($app, $category_id);
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    public function listAppByTotalDownload($limit, $page) {
        $start = $limit * $page;
        $query = "SELECT  FROM store_apps
        ORDER BY total_download DESC
        LIMIT $start, $limit";
        LoggerConfiguration::logInfo($query);
        $list_app = null;
        try {
            if ($result = $this->db->query($query)) {
                while ($app = $result->fetch_assoc()) {
                    $list_app[] = $this->_appDetail($app, $category_id);
                }
                $this->db->free_result($result);
            }
            return $list_app;
        } catch (Exception $e) {
            LoggerConfiguration::logError($e->getMessage(), __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        return false;
    }
    
    private function _appDetail(&$app, $category_id=null) {
        $app['icon'] = $app['applestore_icon'];
        // lay anh screen_shot
        $screen_shots = $this->getAppScreenshots($app['id']);
        $app['screen_shots'] = $screen_shots?$screen_shots:array();
        if (!$category_id){
            $categories = $this->getAppCategories($app['id']);
            $app['category_id'] = $categories[0];
        }
        else {
            $app['category_id'] = $category_id;
        }
        $app['link'][] = 'http://appstore.hotvideo.vn';
        return $app;
    }
}