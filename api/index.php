<?php
header("content-type: Access-Control-Allow-Origin: *");
header("content-type: Access-Control-Allow-Methods: GET");
date_default_timezone_set('UTC');
include './credentials.php';
$db = new PDO("mysql:dbname=db;host=localhost", $mysql_user, $mysql_password);
if ($_GET['a'] == "push_lights" && preg_match("/^[0,1]{10}$/", $_GET['l']) == 1) {
    $lights = $_GET['l'];
    $sql = "UPDATE `stuff` SET `value` = '" . $lights . "' WHERE `key` = 'lights'";
    $db->query($sql);
    $city = "";
    $state = "";
    $country = "";
    if (array_key_exists('p', $_GET)) {
        $json = curlJson("https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $_GET['p'] . "&key=" . $google_api_key)['results'];
        foreach ($json as $try)
            foreach ($try['address_components'] as $part) {
                if (in_array("locality", $part['types']) && $city == "")
                    $city = $part['long_name'];
                elseif (in_array("administrative_area_level_1", $part['types']) && $state == "")
                    $state = $part['long_name'];
                elseif (in_array("country", $part['types']) && $country == "")
                    $country = $part['long_name'];
                if ($country != "" && $state != "" && $city != "") break 2;
            }
        $city .= ($state != "" ? ", " . $state : "") . ($country != "" ? ", " . $country : "");
    }
    $sql = "SELECT `value` FROM `lightlog` ORDER BY `id` DESC LIMIT 1";
    $val = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC)[0]['value'];
    $d = sprintf("%010d", decbin(abs(bindec($val) - bindec($lights))));
    $color = (($d == "1000000000" || $d == "0000010000") ? "red" : (($d == "0100000000" || $d == "0000001000") ? "orange" :
            (($d == "0010000000" || $d == "0000000100") ? "yellow" : (($d == "0001000000" || $d == "0000000010") ? "green" :
            (($d == "0000100000" || $d == "0000000001") ? "blue" : "")))));
    $sql = "INSERT INTO `lightlog` (`timestamp`, `value`, `color`, `latlng`, `city`) VALUES ('" . date("Y-m-d H:i:s") . "', '" . $lights . "', '" . $color . "', '" . $_GET['p'] . "', '" . $city . "')";
    $db->query($sql);
    backcall(null);
} elseif ($_GET['a'] == "get_lights") {
    $sql = "SELECT `value` FROM `stuff` WHERE `key` = 'lights'";
    $result = $db->query($sql);
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    backcall(array("result" => $rows[0]["value"]));
} elseif ($_GET['a'] == "get_map") {
    $sql = "SELECT `latlng`, `color` FROM `lightlog` WHERE `latlng`<>''";
    $result = $db->query($sql);
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    backcall($rows);
} elseif ($_GET['a'] == "get_last_log") {
    $sql = "SELECT * FROM `lightlog` ORDER BY `id` DESC LIMIT 20";
    $result = $db->query($sql);
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    backcall($rows);
}

function backcall($array) {
    $callback = key_exists('callback', $_GET);
    echo ($callback ? $_GET['callback'] . "(" : "") .
        json_encode($array, JSON_PRETTY_PRINT) .
        ($callback ? ");" : "");
}

function curlJson($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    curl_close($curl);
    return json_decode($result, true);
}