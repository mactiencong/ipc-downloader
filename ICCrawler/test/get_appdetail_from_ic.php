<?php
require 'simple_html_dom.php';
$url = "https://www.iphonecake.com/app_576718804_.html";
$html_content = file_get_html($url);
if (! $html_content) {
    die();
}
$iphonecake_id = 576718804;
$app_detail = null;
$app_detail['name'] = $html_content->find('h2[class="ui-app-name"]', 0)->plaintext;
$app_detail['bundle_id'] = '';
$app_detail['description'] = trim($html_content->find('div[id="app_desc"]', 0)->innertext);
$app_detail['price'] = str_replace('$', '', $html_content->find('div[class="ui-app-price"] strong', 0)->plaintext);
$app_detail['applestore_icon'] = $html_content->find('div[class="ui-app-logo"] img', 0)->src;
// save file icon
$saved_icon_path = '';
if ($saved_icon_path)
    $app_detail['icon'] = $saved_icon_path;
else
    $app_detail['icon'] = '';
$app_detail['rating'] = rand(1000, 9000);
$app_detail['rating_point'] = rand(4, 5);
$app_detail['review'] = rand(1000, 9000);
$app_meta = $html_content->find('dl[class="ui-app-meta"]', 0);
$app_detail['author'] = $app_meta->find('dd', 2)->plaintext;
$app_detail['version'] = $app_meta->find('dd', 3)->plaintext;
$app_detail['updated'] = DateTime::createFromFormat('Y-m-d', $app_meta->find('dd', 4)->plaintext)->getTimestamp();
if (! $app_detail['updated'])
    $app_detail['updated'] = time();
$app_detail['size'] = round(intval(trim(str_replace('MB', '', $app_meta->find('dd', 6)->plaintext)) * 1024 * 1024));
$app_detail['language'] = 'EN';
$app_detail['compatibility'] = $app_meta->find('dd', 8)->plaintext;
$app_detail['device_type'] = 0;
$app_detail['release_notes'] = $html_content->find('div[id="app_new"]', 0)->innertext;
$app_detail['applestore_url'] = '';
$app_detail['applestore_track_id'] = $iphonecake_id;
$app_detail['applestore_artist_id'] = 0;
$app_detail['categories'] = array();
$app_detail['applestore_genre_id'] = array();
$app_detail['categories'][0] = array(
    'name' => trim($app_meta->find('dd', 1)->plaintext),
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
    $saved_path = '';
    if ($saved_path)
        $screen_shot['path'] = $saved_path;
    $app_detail['screen_shots'][] = $screen_shot;
}
$app_detail['iphonecake_id'] = $iphonecake_id;
$html_content->clear();
unset($html_content);
var_dump($app_detail);