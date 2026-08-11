<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Give PHP unlimited time and high memory to process 51,000+ records
set_time_limit(0); 
ini_set('memory_limit', '1G'); 

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
    // Strip out any null bytes or weird characters the device might return
    echo "<b>Device Name:</b> " . htmlspecialchars(trim(preg_replace('/[[:cntrl:]]/', '', (string)$zk->deviceName()))) . "<br>";
    echo "<b>Serial Number:</b> " . htmlspecialchars((string)$zk->serialNumber()) . "<br>";
    echo "<b>Firmware Version:</b> " . htmlspecialchars((string)$zk->version()) . "<br>";

    echo "<hr>";

    echo "<h3>Attendance Count</h3>";
    
    // This fetches all 51,136 logs into memory
    $attendance = $zk->getAttendance();
    
    $count = is_array($attendance) ? count($attendance) : 0;
    echo "Total Records Currently on Machine: <b>" . $count . "</b><br>";

    /*
    |--------------------------------------------------------------------------
    | HOW TO FIX YOUR TIMEOUTS PERMANENTLY:
    |--------------------------------------------------------------------------
    | Once you successfully run your MAIN script (the one with the SQL queries) 
    | and confirm all 51,136 records are safely in your database, you should 
    | uncomment the line below and run this script ONCE to delete the logs 
    | from the machine. 
    |
    | After this, the machine will only have a few records to sync each day, 
    | making your sync instantaneous.
    */
    
    // $zk->clearAttendance(); 
    // echo "<p style='color:red'>SUCCESS: Attendance logs have been cleared from the device memory!</p>";

    $zk->disconnect();

} catch (Throwable $e) { // Use Throwable to catch both runtime Errors and Exceptions
    echo "<h3 style='color:red'>Connection Failed</h3>";
    echo $e->getMessage();
}
?>