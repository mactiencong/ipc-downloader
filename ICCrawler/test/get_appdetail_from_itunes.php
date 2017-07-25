<?php
// var_dump(basename('http://a4.mzstatic.com/us/r30/Purple49/v4/3b/c4/e5/3bc4e509-60bb-e839-af3f-abb75c43f565/screen480x480.jpeg'));
// $info = pathinfo('http://a4.mzstatic.com/us/r30/Purple49/v4/3b/c4/e5/3bc4e509-60bb-e839-af3f-abb75c43f565/screen480x480.jpeg');
// var_dump($info['extension']);
// exit();
//var_dump(json_decode(file_get_contents('https://itunes.apple.com/lookup?id=687218959'), true));
//var_dump(parseFloat('asfaf6.5asff'));
if(preg_match('/([0-9\.\,]+)/', '4,99 €', $match)) { // search for number that may contain '.'
    echo floatval(str_replace(',', '.', $match[0]));
} else {
    echo floatval('4,99 €'); // take some last chances with floatval
}