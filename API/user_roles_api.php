<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/db_client.php';
require_once '../includes/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ── Standard list of application modules/pages ─────────── */
function allPages(): array {
    return [
        ['module_key' => 'HR', 'page_name' => 'Organisation'],
        ['module_key' => 'HR', 'page_name' => 'Location'],
        ['module_key' => 'HR', 'page_name' => 'Department'],
        ['module_key' => 'HR', 'page_name' => 'Designation'],
        ['module_key' => 'HR', 'page_name' => 'Category'],
        ['module_key' => 'HR',    'page_name' => 'Employee'],
        ['module_key' => 'HR',    'page_name' => 'Payroll'],
        ['module_key' => 'HR',    'page_name' => 'Attendance'],
        ['module_key' => 'HR',    'page_name' => 'Leave'],
        ['module_key' => 'HR',    'page_name' => 'Training'],
        ['module_key' => 'HR','page_name' => 'Report'],
        ['module_key' => 'HR','page_name' => 'Configuration'],
        ['module_key' => 'HR','page_name' => 'User']
    ];
}

try {
    switch ($action) {

        /* ── LIST ROLES ───────────────────────────────────────── */
        case 'list':
            $query = "SELECT id, role_code, role_name, remarks FROM user_roles WHERE is_deleted=0 ORDER BY id";
            $result = mysqli_query($conn, $query);
            
            if (!$result) throw new Exception(mysqli_error($conn));
            
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        /* ── GET SINGLE ROLE & ACCESS ─────────────────────────── */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            
            // Fetch Role
            $query = "SELECT id, role_code, role_name, remarks FROM user_roles WHERE id=$id AND is_deleted=0";
            $result = mysqli_query($conn, $query);
            
            if (!$result) throw new Exception(mysqli_error($conn));
            
            $role = mysqli_fetch_assoc($result);
            if (!$role) { 
                echo json_encode(['success'=>false, 'message'=>'Not found']); 
                break; 
            }

            // Fetch structured access from the table
            $queryA = "SELECT module_key, page_name, can_view, can_add, can_edit, can_delete FROM user_access WHERE user_id=$id";
            $resultA = mysqli_query($conn, $queryA);
            
            if (!$resultA) throw new Exception(mysqli_error($conn));
            
            $role['access'] = mysqli_fetch_all($resultA, MYSQLI_ASSOC);
            
            echo json_encode(['success'=>true, 'data'=>$role]);
            break;

        /* ── FETCH PAGES STRUCTURE ────────────────────────────── */
        case 'pages_list':
            echo json_encode(['success'=>true, 'data'=>allPages()]);
            break;

        /* ── ADD NEW ROLE & ACCESS ────────────────────────────── */
        case 'add':
            $code    = mysqli_real_escape_string($conn, strtoupper(trim($_POST['role_code'] ?? '')));
            $name    = mysqli_real_escape_string($conn, trim($_POST['role_name'] ?? ''));
            $remarks = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));
            $access  = json_decode($_POST['access'] ?? '[]', true) ?: [];
            $clientCode = mysqli_real_escape_string($conn, $_SESSION['client_code'] ?? 'SYSTEM');
            $userId  = (int)($_SESSION['user_id'] ?? 0);

            if (!$code || !$name) {
                echo json_encode(['success'=>false, 'message'=>'Role Code and Role Name are required.']); break;
            }

            // Check if Role Code exists
            $chkResult = mysqli_query($conn, "SELECT id FROM user_roles WHERE role_code='$code' AND is_deleted=0");
            if (mysqli_num_rows($chkResult) > 0) {
                echo json_encode(['success'=>false, 'message'=>'Role Code already exists.']); break;
            }

            mysqli_begin_transaction($conn);

            // Insert Role
            $insertRole = "INSERT INTO user_roles (role_code, role_name, remarks, created_by) VALUES ('$code', '$name', '$remarks', $userId)";
            if (!mysqli_query($conn, $insertRole)) throw new Exception(mysqli_error($conn));
            
            $roleId = mysqli_insert_id($conn);

            // Insert Access Maps
            if (!empty($access)) {
                foreach ($access as $row) {
                    $can_view = (int)$row['can_view'];
                    $can_add = (int)$row['can_add'];
                    $can_edit = (int)$row['can_edit'];
                    $can_delete = (int)$row['can_delete'];

                    if ($can_view || $can_add || $can_edit || $can_delete) {
                        $modKey = mysqli_real_escape_string($conn, $row['module_key']);
                        $pageName = mysqli_real_escape_string($conn, $row['page_name']);
                        
                        $insertAccess = "INSERT INTO user_access (user_id, client_code, role_code, role_name,  module_key, page_name, can_view, can_add, can_edit, can_delete, created_at, updated_at) 
                                         VALUES ($roleId, '$clientCode', '$code', '$name', '$modKey', '$pageName', $can_view, $can_add, $can_edit, $can_delete, NOW(), NOW())";
                        
                        if (!mysqli_query($conn, $insertAccess)) throw new Exception(mysqli_error($conn));
                    }
                }
            }
            
            mysqli_commit($conn);
            echo json_encode(['success'=>true, 'message'=>'Role & permissions created.']);
            break;

        /* ── UPDATE ROLE & ACCESS ─────────────────────────────── */
        case 'update':
            $id      = (int)($_POST['id'] ?? 0);
            $code    = mysqli_real_escape_string($conn, strtoupper(trim($_POST['role_code'] ?? '')));
            $name    = mysqli_real_escape_string($conn, trim($_POST['role_name'] ?? ''));
            $remarks = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));
            $access  = json_decode($_POST['access'] ?? '[]', true) ?: [];
            $clientCode = mysqli_real_escape_string($conn, $_SESSION['client_code'] ?? 'SYSTEM');

            if (!$id || !$code || !$name) {
                echo json_encode(['success'=>false, 'message'=>'Role Code and Role Name are required.']); break;
            }

            // Check if Role Code is used by another ID
            $chkResult = mysqli_query($conn, "SELECT id FROM user_roles WHERE role_code='$code' AND id!=$id AND is_deleted=0");
            if (mysqli_num_rows($chkResult) > 0) {
                echo json_encode(['success'=>false, 'message'=>'Role Code used by another role.']); break;
            }

            mysqli_begin_transaction($conn);

            // Update Role
            $updateRole = "UPDATE user_roles SET role_code='$code', role_name='$name', remarks='$remarks', updated_at=NOW() WHERE id=$id AND is_deleted=0";
            if (!mysqli_query($conn, $updateRole)) throw new Exception(mysqli_error($conn));
            
            // Delete old access maps
            if (!mysqli_query($conn, "DELETE FROM user_access WHERE user_id=$id")) throw new Exception(mysqli_error($conn));
            
            // Insert fresh access maps
            if (!empty($access)) {
                foreach ($access as $row) {
                    $can_view = (int)$row['can_view'];
                    $can_add = (int)$row['can_add'];
                    $can_edit = (int)$row['can_edit'];
                    $can_delete = (int)$row['can_delete'];

                    if ($can_view || $can_add || $can_edit || $can_delete) {
                        $modKey = mysqli_real_escape_string($conn, $row['module_key']);
                        $pageName = mysqli_real_escape_string($conn, $row['page_name']);
                        
                        $insertAccess = "INSERT INTO user_access (user_id, client_code, role_code, role_name, module_key, page_name, can_view, can_add, can_edit, can_delete, created_at, updated_at) 
                                         VALUES ($id, '$clientCode', '$code', '$name', '$modKey', '$pageName', $can_view, $can_add, $can_edit, $can_delete, NOW(), NOW())";
                        
                        if (!mysqli_query($conn, $insertAccess)) throw new Exception(mysqli_error($conn));
                    }
                }
            }
            
            mysqli_commit($conn);
            echo json_encode(['success'=>true, 'message'=>'Role updated.']);
            break;

        /* ── DELETE ROLE ──────────────────────────────────────── */
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { 
                echo json_encode(['success'=>false, 'message'=>'Invalid ID']); 
                break; 
            }
            
            mysqli_begin_transaction($conn);
            
            if (!mysqli_query($conn, "UPDATE user_roles SET is_deleted=1, updated_at=NOW() WHERE id=$id")) throw new Exception(mysqli_error($conn));
            if (!mysqli_query($conn, "DELETE FROM user_access WHERE user_id=$id")) throw new Exception(mysqli_error($conn));
            
            mysqli_commit($conn);
            
            echo json_encode(['success'=>true, 'message'=>'Role deleted.']);
            break;

        default:
            echo json_encode(['success'=>false, 'message'=>'Invalid action.']);
    }
} catch (Exception $e) {
    // Rollback if a transaction is currently active
    if (isset($conn) && mysqli_ping($conn)) {
         mysqli_rollback($conn);
    }
    error_log('User Roles API MySQLi Error: ' . $e->getMessage());
    echo json_encode(['success'=>false, 'message'=>'A database error occurred. Please check logs.']);
}