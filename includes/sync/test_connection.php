<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Rats\Zkteco\Lib\ZKTeco;

$ip   = "192.168.2.201";
$port = 4370;

try {

    $zk = new ZKTeco($ip, $port);

    $zk->connect();

    echo "<h2 style='color:green'>Device Connected Successfully</h2>";

    echo "<hr>";

    echo "<h3>Device Info</h3>";

    echo "<pre>";
    print_r($zk->deviceName());
    echo "</pre>";

    echo "<hr>";

    echo "<h3>Attendance Count</h3>";

    $attendance = $zk->getAttendance();

    echo count($attendance);

    $zk->disconnect();

} catch (Exception $e) {

    echo "<h3 style='color:red'>Connection Failed</h3>";

    echo $e->getMessage();
}