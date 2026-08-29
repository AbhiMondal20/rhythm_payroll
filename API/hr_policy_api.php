<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

require_once '../includes/db_client.php';
require_once '../includes/config.php';

define('HR_POLICY_DIR', __DIR__ . '/../uploads/hr_policies/');
define('HR_POLICY_URL', 'uploads/hr_policies/');

if (!is_dir(HR_POLICY_DIR)) {
    mkdir(HR_POLICY_DIR, 0755, true);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {

    switch ($action) {

        /* =====================================================
           LIST
        ====================================================== */
        case 'list':

            $user_id = (int)($_SESSION['user_id'] ?? 0);

            $sql = "
                SELECT
                    id,
                    policy_name,
                    file_url,
                    file_name,
                    manual_groups,
                    DATE_FORMAT(created_at,'%d %b %Y') AS created_date
                FROM hr_policies
                WHERE is_deleted=0
                ORDER BY created_at DESC
            ";

            $result = mysqli_query($conn, $sql);

            $rows = [];

            while ($row = mysqli_fetch_assoc($result)) {

                if ($row['manual_groups']) {

                    $policyId = (int)$row['id'];

                    $grpRes = mysqli_query(
                        $conn,
                        "SELECT group_type, group_id
                         FROM hr_policy_groups
                         WHERE policy_id='$policyId'"
                    );

                    $groups = [];

                    while ($g = mysqli_fetch_assoc($grpRes)) {
                        $groups[] = $g;
                    }

                    $row['groups'] = $groups;

                } else {

                    $row['groups'] = [];
                }

                $rows[] = $row;
            }

            echo json_encode([
                'success' => true,
                'data' => $rows
            ]);
            break;


        /* =====================================================
           UPLOAD
        ====================================================== */
        case 'upload':

            $policyName = trim($_POST['policy_name'] ?? '');
            $manualGroups = (int)($_POST['manual_groups'] ?? 0);
            $groupsJson = $_POST['groups'] ?? '{}';

            if ($policyName == '') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Policy Name is required.'
                ]);
                break;
            }

            if (
                empty($_FILES['policy_file']) ||
                $_FILES['policy_file']['error'] != UPLOAD_ERR_OK
            ) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please choose a file to upload.'
                ]);
                break;
            }

            $file = $_FILES['policy_file'];

            $origName = basename($file['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            $allowed = ['pdf','doc','docx','ppt','pptx'];

            if (!in_array($ext, $allowed)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Only PDF, Word and PowerPoint files are allowed.'
                ]);
                break;
            }

            if ($file['size'] > (20 * 1024 * 1024)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'File size must be under 20 MB.'
                ]);
                break;
            }

            $savedName = uniqid('policy_') . '.' . $ext;

            move_uploaded_file(
                $file['tmp_name'],
                HR_POLICY_DIR . $savedName
            );

            mysqli_begin_transaction($conn);

            $policyNameEsc = mysqli_real_escape_string($conn, $policyName);
            $origNameEsc   = mysqli_real_escape_string($conn, $origName);

            $fileUrl = HR_POLICY_URL . $savedName;
            $fileUrlEsc = mysqli_real_escape_string($conn, $fileUrl);

            $createdBy = (int)($_SESSION['user_id'] ?? 0);

            $insertSql = "
                INSERT INTO hr_policies
                (
                    policy_name,
                    file_name,
                    file_url,
                    manual_groups,
                    created_by
                )
                VALUES
                (
                    '$policyNameEsc',
                    '$origNameEsc',
                    '$fileUrlEsc',
                    '$manualGroups',
                    '$createdBy'
                )
            ";

            mysqli_query($conn, $insertSql);

            $policyId = mysqli_insert_id($conn);

            if ($manualGroups) {

                $groups = json_decode($groupsJson, true) ?: [];

                foreach ($groups as $type => $ids) {

                    $typeEsc = mysqli_real_escape_string($conn, $type);

                    foreach ((array)$ids as $gid) {

                        $gid = (int)$gid;

                        mysqli_query(
                            $conn,
                            "INSERT INTO hr_policy_groups
                             (policy_id, group_type, group_id)
                             VALUES
                             ('$policyId','$typeEsc','$gid')"
                        );
                    }
                }
            }

            mysqli_commit($conn);

            echo json_encode([
                'success' => true,
                'message' => 'HR Policy uploaded successfully.',
                'id' => $policyId
            ]);
            break;


        /* =====================================================
           UPDATE
        ====================================================== */
        case 'update':

            $id = (int)($_POST['id'] ?? 0);
            $policyName = trim($_POST['policy_name'] ?? '');
            $manualGroups = (int)($_POST['manual_groups'] ?? 0);
            $groupsJson = $_POST['groups'] ?? '{}';

            if (!$id || !$policyName) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Policy Name is required.'
                ]);
                break;
            }

            mysqli_begin_transaction($conn);

            $policyNameEsc = mysqli_real_escape_string($conn, $policyName);

            if (
                !empty($_FILES['policy_file']) &&
                $_FILES['policy_file']['error'] == UPLOAD_ERR_OK
            ) {

                $file = $_FILES['policy_file'];

                $origName = basename($file['name']);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if (!in_array($ext, ['pdf','doc','docx','ppt','pptx'])) {

                    mysqli_rollback($conn);

                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid file type.'
                    ]);
                    break;
                }

                $savedName = uniqid('policy_') . '.' . $ext;

                move_uploaded_file(
                    $file['tmp_name'],
                    HR_POLICY_DIR . $savedName
                );

                $origNameEsc = mysqli_real_escape_string($conn, $origName);
                $fileUrlEsc = mysqli_real_escape_string(
                    $conn,
                    HR_POLICY_URL . $savedName
                );

                mysqli_query(
                    $conn,
                    "UPDATE hr_policies
                     SET
                        policy_name='$policyNameEsc',
                        file_name='$origNameEsc',
                        file_url='$fileUrlEsc',
                        manual_groups='$manualGroups',
                        updated_at=NOW()
                     WHERE id='$id'"
                );

            } else {

                mysqli_query(
                    $conn,
                    "UPDATE hr_policies
                     SET
                        policy_name='$policyNameEsc',
                        manual_groups='$manualGroups',
                        updated_at=NOW()
                     WHERE id='$id'"
                );
            }

            mysqli_query(
                $conn,
                "DELETE FROM hr_policy_groups
                 WHERE policy_id='$id'"
            );

            if ($manualGroups) {

                $groups = json_decode($groupsJson, true) ?: [];

                foreach ($groups as $type => $ids) {

                    $typeEsc = mysqli_real_escape_string($conn, $type);

                    foreach ((array)$ids as $gid) {

                        $gid = (int)$gid;

                        mysqli_query(
                            $conn,
                            "INSERT INTO hr_policy_groups
                             (policy_id,group_type,group_id)
                             VALUES
                             ('$id','$typeEsc','$gid')"
                        );
                    }
                }
            }

            mysqli_commit($conn);

            echo json_encode([
                'success' => true,
                'message' => 'Policy updated.'
            ]);
            break;


        /* =====================================================
           DELETE
        ====================================================== */
        case 'delete':

            $id = (int)($_POST['id'] ?? 0);

            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid ID'
                ]);
                break;
            }

            mysqli_query(
                $conn,
                "UPDATE hr_policies
                 SET
                    is_deleted=1,
                    updated_at=NOW()
                 WHERE id='$id'"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Policy deleted.'
            ]);
            break;


        /* =====================================================
           GET SINGLE
        ====================================================== */
        case 'get':

            $id = (int)($_GET['id'] ?? 0);

            $result = mysqli_query(
                $conn,
                "SELECT *
                 FROM hr_policies
                 WHERE id='$id'
                 AND is_deleted=0"
            );

            $row = mysqli_fetch_assoc($result);

            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Not found'
                ]);
                break;
            }

            $grpRes = mysqli_query(
                $conn,
                "SELECT group_type, group_id
                 FROM hr_policy_groups
                 WHERE policy_id='$id'"
            );

            $groups = [];

            while ($g = mysqli_fetch_assoc($grpRes)) {
                $groups[] = $g;
            }

            $row['groups'] = $groups;

            echo json_encode([
                'success' => true,
                'data' => $row
            ]);
            break;


        default:

            echo json_encode([
                'success' => false,
                'message' => 'Invalid action.'
            ]);
    }

} catch (Exception $e) {

    mysqli_rollback($conn);

    error_log('HR Policy API : ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred.'
    ]);
}
?>
