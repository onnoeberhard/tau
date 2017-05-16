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
    if (array_key_exists('p', $_GET)) {
        $json = curlJson("https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $_GET['p'] . "&key=" . $google_api_key)['results'];
        $city = "";
        foreach ($json as $try)
            foreach ($try['address_components'] as $part)
                if (in_array("locality", $part['types'])) {
                    $city = $part['long_name'];
                    break 2;
                }
    }
    $sql = "INSERT INTO `lightlog` (`timestamp`, `value`, `city`) VALUES ('" . date("Y-m-d H:i:s") . "', '" . $lights . "', '" . $city . "')";
    $db->query($sql);
    return $_GET['callback'] . "();";
} elseif ($_GET['a'] == "get_lights") {
    $sql = "SELECT `value` FROM `stuff` WHERE `key` = 'lights'";
    $result = $db->query($sql);
    $rows = $result->fetchAll();
    echo $_GET['callback'] . "(" . json_encode(
            array("result" => $rows[0]["value"])
        ) . ");";
}

function curlJson($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    curl_close($curl);
    return json_decode($result, true);
}