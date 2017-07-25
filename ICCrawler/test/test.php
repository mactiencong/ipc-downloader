<?php
var_dump(strtotime('-1 month'));
die();
require_once dirname(__FILE__) . '/../simple_html_dom.php';

$html_content = str_get_html(file_get_contents('ic.html'));
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
    return false;
}
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

echo $status;