<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kolkata');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure DB connection is available
if (!isset($conn)) {
    include_once('db_conn.php');
}

/* ==========================================================
   1. GET CLIENT IP
========================================================== */
if (!function_exists('getClientIP')) {
    function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

/* ==========================================================
   2. GET BROWSER
========================================================== */
if (!function_exists('getBrowserInfo')) {
    function getBrowserInfo($userAgent = null) {
        $ua = strtolower($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (strpos($ua, 'edg') !== false) return 'Microsoft Edge';
        elseif (strpos($ua, 'chrome') !== false) return 'Google Chrome';
        elseif (strpos($ua, 'safari') !== false) return 'Safari';
        elseif (strpos($ua, 'firefox') !== false) return 'Mozilla Firefox';
        elseif (strpos($ua, 'opera') !== false || strpos($ua, 'opr/') !== false) return 'Opera';
        elseif (strpos($ua, 'msie') !== false || strpos($ua, 'trident') !== false) return 'Internet Explorer';
        else return 'Unknown Browser';
    }
}

if (!function_exists('getOS')) {
    function getOS($userAgent = null) {
        $ua = strtolower($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    
        if (strpos($ua, 'windows nt 10.0') !== false) return 'Windows 10';
        elseif (strpos($ua, 'windows nt 6.3') !== false) return 'Windows 8.1';
        elseif (strpos($ua, 'windows nt 6.2') !== false) return 'Windows 8';
        elseif (strpos($ua, 'windows nt 6.1') !== false) return 'Windows 7';
        elseif (strpos($ua, 'mac os x') !== false) return 'macOS';
        elseif (strpos($ua, 'android') !== false) return 'Android';
        elseif (strpos($ua, 'iphone') !== false) return 'iOS';
        elseif (strpos($ua, 'ipad') !== false) return 'iPadOS';
        else return 'Unknown OS';
    }
}

/* ==========================================================
   3. LOG ACTIVITY
========================================================== */
if (!function_exists('logActivity')) {
    function logActivity($conn, $action, $description, $status = 'success', $page = null) {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $user_id = intval($_SESSION['user_id'] ?? 0);
        $role    = $_SESSION['role'] ?? '';
        $ip      = getClientIP();
        $browser = getBrowserInfo();
        $device  = getOS();
        $page    = $page ?? basename($_SERVER['PHP_SELF']);
        $date    = date("Y-m-d H:i:s");

        if (!($conn instanceof mysqli)) {
            error_log("logActivity: invalid DB connection");
            return false;
        }

        $stmt = $conn->prepare("INSERT INTO log_activity
            (user_id, role, action, description, ip_address, browser, device, page, log_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            error_log("logActivity prepare failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param("isssssssss",
            $user_id, $role, $action, $description,
            $ip, $browser, $device, $page, $date, $status
        );

        $ok = $stmt->execute();
        if (!$ok) error_log("logActivity execute failed: " . $stmt->error);

        $stmt->close();
        return $ok;
    }
}

/* ==========================================================
   4. CHECK PAGE ACCESS (BLOCK PAGE)
========================================================== */
if (!function_exists('checkPageAccess')) {
    function checkPageAccess(mysqli $conn, string $page_name, string $action = 'view'): void {

        if (session_status() === PHP_SESSION_NONE) session_start();

        if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
            header("Location: ../../index");
            exit;
        }

        $user_id = intval($_SESSION['user_id'] ?? 0);
        $role    = strtolower($_SESSION['role'] ?? '');

        // Admin always allowed
        if ($role === 'admin') return;

        // Validate action
        $valid_actions = ['view', 'add', 'edit', 'delete'];
        if (!in_array($action, $valid_actions)) $action = 'view';
        $column = "can_" . $action;

        // Check DB permission
        $stmt = $conn->prepare("SELECT $column FROM user_access WHERE user_id = ? AND page_name = ? LIMIT 1");
        if (!$stmt) {
            error_log("checkPageAccess error: " . $conn->error);
            denyAccessAndExit($_SESSION['username'] ?? 'unknown', getClientIP(), $page_name, $action, $conn);
        }
        $stmt->bind_param("is", $user_id, $page_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row || (int)$row[$column] !== 1) {
            if (function_exists('logActivity')) {
                logActivity($conn, 'Access Denied', "User {$user_id} attempted {$action} on {$page_name}", 'failed', $page_name);
            }

            echo "
            <script>
            Swal.fire({
                icon: 'error',
                title: 'Access Denied',
                text: 'You do not have permission to {$action} this page.'
            }).then(() => { window.history.back(); });
            </script>";
            exit;
        }
    }
}

/* ==========================================================
   5. CAN PERFORM ACTION (RETURN TRUE/FALSE)
========================================================== */
if (!function_exists('canPerformAction')) {
    function canPerformAction(mysqli $conn, string $page_name, string $action = 'view'): bool {

        $user_id = intval($_SESSION['user_id'] ?? 0);
        $role    = strtolower($_SESSION['role'] ?? '');

        if ($role === 'administrator') return true;
        if ($user_id <= 0) return false;

        $valid_actions = ['view', 'add', 'edit', 'delete'];
        if (!in_array($action, $valid_actions)) $action = 'view';
        $column = "can_" . $action;

        $stmt = $conn->prepare("SELECT $column FROM user_access WHERE user_id = ? AND page_name = ? LIMIT 1");
        if (!$stmt) return false;

        $stmt->bind_param("is", $user_id, $page_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        return ($row && (int)$row[$column] === 1);
    }
}

/* ==========================================================
   6. DENY ACCESS (GLOBAL HANDLER)
========================================================== */
if (!function_exists('denyAccessAndExit')) {
    function denyAccessAndExit($username, $ip, $page_name, $action, $conn = null) {

        if ($conn && function_exists('logActivity')) {
            logActivity($conn, 'Access Check Failed',
                "DB error while checking access for {$username} on {$page_name}",
                'failed',
                $page_name
            );
        }

        echo "
        <script>
        Swal.fire({
            icon: 'error',
            title: 'Access Error',
            text: 'Unable to verify access rights. Contact admin.'
        }).then(() => { window.history.back(); });
        </script>";
        exit();
    }
}
?>
