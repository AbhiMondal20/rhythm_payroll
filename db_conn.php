<?php
date_default_timezone_set('Asia/Kolkata');

mysqli_report(MYSQLI_REPORT_OFF);

$serverName = "localhost";
$username   = "root";
$password   = "";
$database   = "app_master";
$port       = 3307;

$master = mysqli_connect($serverName, $username, $password, $database, $port);

if (!$master) {
    die("Master DB connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($master, "utf8mb4");
// echo "Connected successfully";
?>