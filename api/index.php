<?php
header("content-type: Access-Control-Allow-Origin: *");
header("content-type: Access-Control-Allow-Methods: GET");
include './credentials.php';
$db = new PDO("mysql:dbname=db;host=localhost", $mysql_user, $mysql_password);
if ($_GET['a'] == "push_lights" && preg_match("/^[0,1]{10}$/",$_GET['l']) == 1) {
    $lights = $_GET['l'];
    $sql = "UPDATE `stuff` SET `value` = '" . $lights . "' WHERE `key` = 'lights'";
    $db->query($sql);
    return $_GET['callback'] . "();";
} elseif ($_GET['a'] == "get_lights") {
    $sql = "SELECT `value` FROM `stuff` WHERE `key` = 'lights'";
    $result = $db->query($sql);
    $rows = $result->fetchAll();
    echo $_GET['callback']. "(" . json_encode(
        array("result" => $rows[0]["value"])
    ) . ");";
}
