<?php

$server = "sql307.infinityfree.com";
$user = "if0_42141024";
$password = "BGplJduBYWxTaI";
$database_name = "if0_42141024_lms_database";

$connection = new mysqli($server, $user, $password, $database_name);

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

$connection->set_charset("utf8mb4");
?>


