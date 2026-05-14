<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');

/* PHP 8 FIX FOR OLD ZKLibrary */
if (!function_exists('each')) {
    function each(&$array)
    {
        $key = key($array);
        if ($key === null) {
            return false;
        }

        $value = current($array);
        next($array);

        return [
            1 => $value,
            'value' => $value,
            0 => $key,
            'key' => $key
        ];
    }
}

require_once '../db_client.php';
require_once 'ZKLibrary/zklibrary.php';

$response = [
    'success' => false,
    'devices' => [],
    'message' => ''
];

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection $conn not found'
    ], JSON_PRETTY_PRINT);
    exit;
}

$q = mysqli_query($conn, "
    SELECT *
    FROM att_devices
    WHERE connection_type='TCP/IP'
");

if (!$q) {
    echo json_encode([
        'success' => false,
        'message' => mysqli_error($conn)
    ], JSON_PRETTY_PRINT);
    exit;
}

while ($device = mysqli_fetch_assoc($q)) {

    $deviceId     = (int)$device['id'];
    $deviceCode   = mysqli_real_escape_string($conn, $device['code'] ?? '');
    $serialNumber = mysqli_real_escape_string($conn, $device['serial_number'] ?? '');
    $ip           = trim($device['ip_address'] ?? '');
    $port         = (int)($device['port_no'] ?? 4370);
    $directionRaw = $device['direction'] ?? 'IN/OUT';
    $direction    = mysqli_real_escape_string($conn, $directionRaw);

    $deviceResult = [
        'device_name' => $device['device_name'] ?? '',
        'ip' => $ip,
        'port' => $port,
        'status' => '',
        'punch_count' => 0,
        'error' => ''
    ];

    if ($ip === '' || $port <= 0) {
        $deviceResult['status'] = 'error';
        $deviceResult['error'] = 'Invalid IP or port';
        $response['devices'][] = $deviceResult;
        continue;
    }

    try {

        $zk = new ZKLibrary($ip, $port);

        if (!$zk->connect()) {
            mysqli_query($conn, "
                UPDATE att_devices
                SET status='offline', last_ping=NOW()
                WHERE id='$deviceId'
            ");

            $deviceResult['status'] = 'offline';
            $deviceResult['error'] = 'Device connection failed';

            $response['devices'][] = $deviceResult;
            continue;
        }

        $zk->disableDevice();

        $attendance = $zk->getAttendance();

        if (!is_array($attendance)) {
            $attendance = [];
        }

        foreach ($attendance as $att) {

            if (!is_array($att)) {
                continue;
            }

            $employeeCode = $att['id']
                ?? $att['uid']
                ?? $att['user_id']
                ?? '';

            $rawPunchTime = $att['timestamp']
                ?? $att['time']
                ?? '';

            if ($employeeCode === '' || $rawPunchTime === '') {
                continue;
            }

            $ts = strtotime($rawPunchTime);
            if (!$ts) {
                continue;
            }

            $punchTime = date('Y-m-d H:i:s', $ts);
            $punchDate = date('Y-m-d', $ts);

            $punchType = $att['state']
                ?? $att['status']
                ?? $directionRaw;

            $verifyType = $att['type']
                ?? $att['verify']
                ?? '';

            $employeeCodeEsc = mysqli_real_escape_string($conn, $employeeCode);
            $punchTimeEsc    = mysqli_real_escape_string($conn, $punchTime);
            $punchDateEsc    = mysqli_real_escape_string($conn, $punchDate);
            $punchTypeEsc    = mysqli_real_escape_string($conn, $punchType);
            $verifyTypeEsc   = mysqli_real_escape_string($conn, $verifyType);

            $rawDataEsc = mysqli_real_escape_string(
                $conn,
                json_encode($att, JSON_UNESCAPED_UNICODE)
            );

            mysqli_query($conn, "
                INSERT IGNORE INTO att_machine_punches
                (
                    device_id,
                    device_code,
                    serial_number,
                    employee_code,
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
                    '$deviceId',
                    '$deviceCode',
                    '$serialNumber',
                    '$employeeCodeEsc',
                    '$punchTimeEsc',
                    '$punchDateEsc',
                    '$punchTypeEsc',
                    '$verifyTypeEsc',
                    '$rawDataEsc',
                    NOW(),
                    NOW()
                )
            ");

            mysqli_query($conn, "
                INSERT IGNORE INTO att_punch_logs
                (
                    employee_code,
                    punch_time,
                    device_code,
                    direction,
                    created_at
                )
                VALUES
                (
                    '$employeeCodeEsc',
                    '$punchTimeEsc',
                    '$deviceCode',
                    '$direction',
                    NOW()
                )
            ");

            $deviceResult['punch_count']++;
        }

        mysqli_query($conn, "
            UPDATE att_devices
            SET
                status='online',
                last_download=NOW(),
                last_ping=NOW()
            WHERE id='$deviceId'
        ");

        $zk->enableDevice();
        $zk->disconnect();

        $deviceResult['status'] = 'online';

    } catch (Throwable $e) {

        mysqli_query($conn, "
            UPDATE att_devices
            SET status='offline', last_ping=NOW()
            WHERE id='$deviceId'
        ");

        $deviceResult['status'] = 'error';
        $deviceResult['error'] = $e->getMessage();
    }

    $response['devices'][] = $deviceResult;
}

$response['success'] = true;
$response['message'] = 'Biometric sync completed';

echo json_encode($response, JSON_PRETTY_PRINT);