<?php
/**
 * quick_links_api.php
 * CRUD API for Quick Links (MySQLi Version)
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

switch ($action) {

    /* =====================================================
       LIST
    ===================================================== */
    case 'list':

        $sql = "
            SELECT
                id,
                display_name,
                link_url,
                visible_to_all,
                DATE_FORMAT(created_at,'%d %b %Y') AS created_date
            FROM quick_links
            WHERE is_deleted = 0
            AND created_by = {$userId}
            ORDER BY created_at DESC
        ";

        $result = mysqli_query($conn, $sql);

        if (!$result) {
            echo json_encode([
                'success' => false,
                'message' => mysqli_error($conn)
            ]);
            exit;
        }

        $data = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        break;


    /* =====================================================
       GET
    ===================================================== */
    case 'get':

        $id = (int)($_GET['id'] ?? 0);

        $sql = "
            SELECT
                id,
                display_name,
                link_url,
                visible_to_all
            FROM quick_links
            WHERE id = {$id}
            AND is_deleted = 0
            LIMIT 1
        ";

        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {

            echo json_encode([
                'success' => true,
                'data' => mysqli_fetch_assoc($result)
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => 'Not found'
            ]);
        }

        break;


    /* =====================================================
       ADD
    ===================================================== */
    case 'add':

        $name = trim($_POST['display_name'] ?? '');
        $url = trim($_POST['link_url'] ?? '');
        $visible = (int)($_POST['visible_to_all'] ?? 0);

        if ($name == '' || $url == '') {
            echo json_encode([
                'success' => false,
                'message' => 'Display Name and Link are required.'
            ]);
            exit;
        }

        $name = mysqli_real_escape_string($conn, $name);
        $url = mysqli_real_escape_string($conn, $url);

        $sql = "
            INSERT INTO quick_links
            (
                display_name,
                link_url,
                visible_to_all,
                created_by
            )
            VALUES
            (
                '{$name}',
                '{$url}',
                {$visible},
                {$userId}
            )
        ";

        if (mysqli_query($conn, $sql)) {

            echo json_encode([
                'success' => true,
                'message' => 'Quick link added.',
                'id' => mysqli_insert_id($conn)
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => mysqli_error($conn)
            ]);
        }

        break;


    /* =====================================================
       UPDATE
    ===================================================== */
    case 'update':

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['display_name'] ?? '');
        $url = trim($_POST['link_url'] ?? '');
        $visible = (int)($_POST['visible_to_all'] ?? 0);

        if ($id <= 0 || $name == '' || $url == '') {

            echo json_encode([
                'success' => false,
                'message' => 'Display Name and Link are required.'
            ]);
            exit;
        }

        $name = mysqli_real_escape_string($conn, $name);
        $url = mysqli_real_escape_string($conn, $url);

        $sql = "
            UPDATE quick_links
            SET
                display_name = '{$name}',
                link_url = '{$url}',
                visible_to_all = {$visible},
                updated_at = NOW()
            WHERE id = {$id}
            AND is_deleted = 0
        ";

        if (mysqli_query($conn, $sql)) {

            echo json_encode([
                'success' => true,
                'message' => 'Quick link updated.'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => mysqli_error($conn)
            ]);
        }

        break;


    /* =====================================================
       DELETE
    ===================================================== */
    case 'delete':

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {

            echo json_encode([
                'success' => false,
                'message' => 'Invalid ID'
            ]);
            exit;
        }

        $sql = "
            UPDATE quick_links
            SET
                is_deleted = 1,
                updated_at = NOW()
            WHERE id = {$id}
        ";

        if (mysqli_query($conn, $sql)) {

            echo json_encode([
                'success' => true,
                'message' => 'Link deleted.'
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => mysqli_error($conn)
            ]);
        }

        break;


    /* =====================================================
       DEFAULT
    ===================================================== */
    default:

        echo json_encode([
            'success' => false,
            'message' => 'Invalid action.'
        ]);
}
?>
