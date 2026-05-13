<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/db_client.php';
require_once __DIR__ . '/includes/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode([
        "status" => false,
        "message" => "Database connection not found"
    ]);
    exit;
}

function response($status, $message, $extra = []) {
    echo json_encode(array_merge([
        "status" => $status,
        "message" => $message
    ], $extra), JSON_PRETTY_PRINT);
    exit;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$apiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? ($_GET['api_key'] ?? '');

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    response(false, "Invalid JSON body");
}

$device_code   = trim($data['device_code'] ?? '');
$serial_number = trim($data['serial_number'] ?? '');
$punches       = $data['punches'] ?? [];

if ($device_code === '') {
    response(false, "device_code is required");
}

if (empty($punches) || !is_array($punches)) {
    response(false, "punches array is required");
}

/* Validate device */
$stmt = $conn->prepare("
    SELECT id, device_name, api_key
    FROM att_devices
    WHERE code = ?
       OR serial_number = ?
    LIMIT 1
");

if (!$stmt) {
    response(false, "Device query failed: " . $conn->error);
}

$stmt->bind_param("ss", $device_code, $serial_number);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$device) {
    response(false, "Device not registered");
}

if (!empty($device['api_key']) && $device['api_key'] !== $apiKey) {
    response(false, "Invalid API key");
}

$device_id = (int)$device['id'];

$insert = $conn->prepare("
    INSERT INTO att_machine_punches
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
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        verify_type = VALUES(verify_type),
        raw_data = VALUES(raw_data),
        synced_at = NOW()
");

if (!$insert) {
    response(false, "Insert prepare failed: " . $conn->error);
}

$saved = 0;
$skipped = 0;
$errors = [];

foreach ($punches as $index => $p) {
    $employee_code = trim($p['employee_code'] ?? $p['emp_code'] ?? $p['user_id'] ?? '');
    $punch_time_raw = trim($p['punch_time'] ?? $p['timestamp'] ?? '');
    $punch_type = strtoupper(trim($p['punch_type'] ?? 'UNKNOWN'));
    $verify_type = trim($p['verify_type'] ?? '');

    if ($employee_code === '' || $punch_time_raw === '') {
        $skipped++;
        $errors[] = "Row {$index}: employee_code or punch_time missing";
        continue;
    }

    $ts = strtotime($punch_time_raw);
    if (!$ts) {
        $skipped++;
        $errors[] = "Row {$index}: invalid punch_time";
        continue;
    }

    $punch_time = date("Y-m-d H:i:s", $ts);
    $punch_date = date("Y-m-d", $ts);

    if (!in_array($punch_type, ['IN', 'OUT', 'UNKNOWN'], true)) {
        $punch_type = 'UNKNOWN';
    }

    $raw_json = json_encode($p, JSON_UNESCAPED_UNICODE);

    $insert->bind_param(
        "issssssss",
        $device_id,
        $device_code,
        $serial_number,
        $employee_code,
        $punch_time,
        $punch_date,
        $punch_type,
        $verify_type,
        $raw_json
    );

    if ($insert->execute()) {
        $saved++;
    } else {
        $skipped++;
        $errors[] = "Row {$index}: " . $insert->error;
    }
}

$insert->close();

$up = $conn->prepare("
    UPDATE att_devices
    SET last_download = NOW(),
        last_ping = NOW(),
        status = 'online',
        updated_at = NOW()
    WHERE id = ?
");

if ($up) {
    $up->bind_param("i", $device_id);
    $up->execute();
    $up->close();
}

response(true, "Punch data received", [
    "device_id" => $device_id,
    "saved" => $saved,
    "skipped" => $skipped,
    "errors" => $errors
]);