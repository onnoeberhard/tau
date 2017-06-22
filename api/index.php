<?php
header("content-type: Access-Control-Allow-Origin: *");
header("content-type: Access-Control-Allow-Methods: GET");
date_default_timezone_set('UTC');
include './credentials.php';
$db = new PDO("mysql:dbname=db;host=localhost", $mysql_user, $mysql_password);
if ($_GET['a'] == "push_lights" && preg_match("/^[0,1]{10}$/", $_GET['l']) == 1) {
    $lights = $_GET['l'];
    $db->query("UPDATE `stuff` SET `value` = '" . $lights . "' WHERE `key` = 'lights'");
    $city = "";
    $state = "";
    $country = "";
    $p = "";
    if (array_key_exists('p', $_GET)) {
        $p = $_GET['p'];
        foreach (curlJson("https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $_GET['p'] . "&key=" . $google_api_key . "&language=en")['results'] as $try)
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
    $d = sprintf("%010d", decbin(abs(bindec($db->query("SELECT `value` FROM `lightlog` ORDER BY `id` DESC LIMIT 1")->fetchAll(PDO::FETCH_ASSOC)[0]['value']) - bindec($lights))));
    $color = (($d == "1000000000" || $d == "0000010000") ? "red" : (($d == "0100000000" || $d == "0000001000") ? "orange" :
            (($d == "0010000000" || $d == "0000000100") ? "yellow" : (($d == "0001000000" || $d == "0000000010") ? "green" :
            (($d == "0000100000" || $d == "0000000001") ? "blue" : "")))));
    $db->query("INSERT INTO `lightlog` (`timestamp`, `value`, `color`, `latlng`, `city`) VALUES ('" . date("Y-m-d H:i:s") . "', '" . $lights . "', '" . $color . "', '" . $p . "', '" . $city . "')");
    $ip = array_key_exists('ip', $_GET) ? $_GET['ip'] : "";
    $db->query("
            INSERT INTO `stats` (`type`, `name`, `value`) VALUES ('color', '" . $color . "', 1) ON DUPLICATE KEY UPDATE `value` = `value` + 1;
            INSERT INTO `stats` (`type`, `name`, `value`) VALUES ('city', '" . $city . "', 1) ON DUPLICATE KEY UPDATE `value` = `value` + 1;
            INSERT INTO `stats` (`type`, `name`, `value`, `data`) VALUES ('ip', '" . $ip . "', 1, '" . $city . "') ON DUPLICATE KEY UPDATE `value` = `value` + 1
    ");
    backcall(null);
} elseif ($_GET['a'] == "get_lights") {
    backcall(array("result" => $db->query("SELECT `value` FROM `stuff` WHERE `key` = 'lights'")->fetchAll(PDO::FETCH_ASSOC)[0]["value"]));
} elseif ($_GET['a'] == "get_map") {
    backcall($db->query("SELECT `latlng`, `color` FROM `lightlog` WHERE `latlng`<>''")->fetchAll(PDO::FETCH_ASSOC));
} elseif ($_GET['a'] == "get_log_stats") {
    $logs = $db->query("SELECT * FROM `lightlog` ORDER BY `id` DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $stats = $db->query("
            SELECT * FROM `stats` WHERE `type` = 'color' AND `name`<>''
            UNION
            SELECT * FROM (SELECT * FROM `stats` WHERE `type` = 'city' AND `name`<>'' ORDER BY `value` DESC LIMIT 3) AS `cities`
            UNION
            SELECT * FROM (SELECT * FROM `stats` WHERE `type` = 'ip' AND `name`<>'' AND `data`<>'' ORDER BY `value` DESC LIMIT 3) AS `people`
    ")->fetchAll(PDO::FETCH_ASSOC);
    backcall(array("logs" => $logs, "stats" => $stats));
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