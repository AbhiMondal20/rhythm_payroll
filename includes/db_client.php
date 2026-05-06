<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "db_conn.php";
$client_code = $_SESSION['client_code'] ?? 0;
if ($client_code <= 0) die("Client not found");

$module_key = $_SESSION['module_key'] ?? 'Payroll';
$stmt = mysqli_prepare($master, "
    SELECT db_host, db_name, db_user, db_pass
    FROM client_databases
    WHERE client_code = ? AND module_key = ?
    LIMIT 1");

mysqli_stmt_bind_param($stmt, "ss", $client_code, $module_key);
mysqli_stmt_execute($stmt);

$res = mysqli_stmt_get_result($stmt);
$db  = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$db) die("Client DB mapping missing for module: " . htmlspecialchars($module_key));

$conn = mysqli_connect($db['db_host'], $db['db_user'], $db['db_pass'], $db['db_name']);
if (!$conn) die("Client DB connection failed");

mysqli_set_charset($conn, "utf8mb4");



