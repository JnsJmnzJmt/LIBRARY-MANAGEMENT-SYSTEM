<?php

$server = "localhost";
$user = "root";
$password = "";
$database_name = "library_database_third";

$connection = new mysqli($server, $user, $password, $database_name);

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

$connection->set_charset("utf8mb4");
?>


