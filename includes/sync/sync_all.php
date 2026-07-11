<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../vendor/autoload.php';
require '../db_client.php';

use Rats\Zkteco\Lib\ZKTeco;

date_default_timezone_set('Asia/Kolkata');

$devices = mysqli_query($conn, "SELECT * FROM att_devices WHERE status='online'");

if (!$devices) {
    die(mysqli_error($conn));
}

while ($device = mysqli_fetch_assoc($devices)) {

    echo "<h3>Device : {$device['device_name']}</h3>";

    try {

        $zk = new ZKTeco($device['ip_address'], (int)$device['port_no']);

        $zk->connect();

        mysqli_query($conn, "
            UPDATE att_devices
            SET last_ping = NOW()
            WHERE id='{$device['id']}'
        ");

        /*
        |--------------------------------------------------------------------------
        | Load Device Users
        |--------------------------------------------------------------------------
        */

        $users = [];

        $deviceUsers = $zk->getUser();

        if (is_array($deviceUsers)) {

            foreach ($deviceUsers as $u) {

                $userid = '';

                if (isset($u['userid'])) {
                    $userid = trim((string)$u['userid']);
                } elseif (isset($u['id'])) {
                    $userid = trim((string)$u['id']);
                }

                $name = '';

                if (isset($u['name'])) {

                    if (is_array($u['name'])) {
                        $name = implode(' ', $u['name']);
                    } else {
                        $name = trim((string)$u['name']);
                    }

                }

                if ($userid != '') {
                    $users[$userid] = $name;
                }

            }

        }

        /*
        |--------------------------------------------------------------------------
        | Attendance
        |--------------------------------------------------------------------------
        */

        $attendance = $zk->getAttendance();

        $inserted = 0;
        $duplicate = 0;

        if (!is_array($attendance)) {
            $attendance = [];
        }

        foreach ($attendance as $row) {

            if (!is_array($row)) {
                continue;
            }

            $employee_code = trim((string)($row['id'] ?? ''));

            if ($employee_code == '') {
                continue;
            }

            $employee_name = $users[$employee_code] ?? '';

            if (is_array($employee_name)) {
                $employee_name = implode(' ', $employee_name);
            }

            $timestamp = $row['timestamp'] ?? '';

            if ($timestamp == '') {
                continue;
            }

            $punch_time = date('Y-m-d H:i:s', strtotime($timestamp));
            $punch_date = date('Y-m-d', strtotime($timestamp));

            $punch_type = $row['type'] ?? 0;
            $verify_type = $row['state'] ?? 0;

            $raw = mysqli_real_escape_string(
                $conn,
                json_encode(
                    $row,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );

            $employee_code_db = mysqli_real_escape_string($conn, $employee_code);
            $employee_name_db = mysqli_real_escape_string($conn, $employee_name);

            $check = mysqli_query($conn, "
                SELECT id
                FROM att_machine_punches
                WHERE device_id='{$device['id']}'
                AND employee_code='$employee_code_db'
                AND punch_time='$punch_time'
                LIMIT 1
            ");

            if (mysqli_num_rows($check) == 0) {

                $sql = "
                INSERT INTO att_machine_punches
                (
                    device_id,
                    device_code,
                    serial_number,
                    employee_code,
                    employee_name,
                    punch_time,
                    punch_date,
                    punch_type,
                    verify_type,
                    raw_data,
                    synced_at,
                    created_at
                )
                VALUES
                (
                    '{$device['id']}',
                    '{$device['code']}',
                    '{$device['serial_number']}',
                    '$employee_code_db',
                    '$employee_name_db',
                    '$punch_time',
                    '$punch_date',
                    '$punch_type',
                    '$verify_type',
                    '$raw',
                    NOW(),
                    NOW()
                )";

                if (!mysqli_query($conn, $sql)) {
                    echo "<div style='color:red'>".mysqli_error($conn)."</div>";
                } else {
                    $inserted++;
                }

            } else {

                $duplicate++;

            }

        }

        mysqli_query($conn, "
            UPDATE att_devices
            SET last_download=NOW()
            WHERE id='{$device['id']}'
        ");

        $zk->disconnect();

        echo "<div style='padding:10px;background:#d4edda;border:1px solid #28a745;margin:10px 0'>";
        echo "<b>Total Attendance :</b> ".count($attendance)."<br>";
        echo "<b>Inserted :</b> ".$inserted."<br>";
        echo "<b>Duplicate :</b> ".$duplicate."<br>";
        echo "</div>";

    } catch (Throwable $e) {

        echo "<div style='color:red'>";
        echo "<b>Error:</b> ".$e->getMessage();
        echo "</div>";

    }

}