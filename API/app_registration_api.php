<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Ensure $conn is defined inside this file using mysqli_connect
require_once '../includes/db_client.php';
require_once '../includes/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ── Helper to escape strings securely for mysqli_query ── */
function esc_sql($conn, $string) {
    return mysqli_real_escape_string($conn, $string);
}

/* ── Master app permissions list ───────────────────────────── */
function allAppPermissions(): array {
    return [
        'Check In/Out',
        'Apply Leave',
        'My Approvals',
        'View Attendance Logs',
        'View Payslip',
        'Apply Reimbursement',
        'Search Employee',
        'Raise TimeEntries Request',
        'Time Off Request',
        'Allow Employee Visit',
        'View Taxes',
        'View My Documents',
        'Can Access Tasks',
        'Can Access Time Sheet',
        'Can Manage Task Management',
        'Can View Employee Document',
        'Can Add Employee Document',
        'Can View form 16',
    ];
}

try {
    switch ($action) {

        /* ── LIST ─────────────────────────────────────────── */
        case 'list':
            $query = "SELECT ar.id, e.employee_code AS code, 
                             e.employee_name AS name, 
                             ar.mode, ar.activation_code, ar.device_name, ar.status
                      FROM app_registrations ar
                      JOIN employees e ON e.id = ar.employee_id
                      WHERE ar.is_deleted = 0
                      ORDER BY ar.created_at DESC";
            
            $result = mysqli_query($conn, $query);
            if (!$result) throw new Exception(mysqli_error($conn));

            $data = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        /* ── GET SINGLE ───────────────────────────────────── */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            
            $query = "SELECT ar.*, e.employee_name, e.employee_code
                      FROM app_registrations ar
                      JOIN employees e ON e.id = ar.employee_id
                      WHERE ar.id = $id AND ar.is_deleted = 0";
            
            $result = mysqli_query($conn, $query);
            if (!$result) throw new Exception(mysqli_error($conn));
            
            $row = mysqli_fetch_assoc($result);

            if (!$row) { 
                echo json_encode(['success'=>false,'message'=>'Not found']); 
                break; 
            }

            // Fetch Permissions
            $permQuery = "SELECT permission_key FROM app_registration_permissions WHERE registration_id = $id";
            $permResult = mysqli_query($conn, $permQuery);
            if (!$permResult) throw new Exception(mysqli_error($conn));
            
            $perms = [];
            while ($p = mysqli_fetch_assoc($permResult)) {
                $perms[] = $p['permission_key'];
            }
            $row['permissions'] = $perms;
            
            echo json_encode(['success'=>true,'data'=>$row]);
            break;

        /* ── TOGGLE STATUS ────────────────────────────────── */
        case 'toggle_status':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { 
                echo json_encode(['success'=>false,'message'=>'Invalid ID']); 
                break; 
            }
            
            $updateQuery = "UPDATE app_registrations SET status = IF(status='Active','Inactive','Active'), updated_at=NOW() WHERE id=$id";
            if (!mysqli_query($conn, $updateQuery)) throw new Exception(mysqli_error($conn));
            
            $statusQuery = "SELECT status FROM app_registrations WHERE id=$id";
            $statusResult = mysqli_query($conn, $statusQuery);
            $statusRow = mysqli_fetch_assoc($statusResult);
            
            echo json_encode(['success'=>true,'status'=>$statusRow['status']]);
            break;

        /* ── ADD ──────────────────────────────────────────── */
        case 'add':
            $empId   = (int)($_POST['employee_id'] ?? 0);
            $mode    = esc_sql($conn, trim($_POST['mode'] ?? 'User'));
            $capPh   = esc_sql($conn, trim($_POST['capture_photo'] ?? 'Required'));
            $capLoc  = esc_sql($conn, trim($_POST['capture_location'] ?? 'Required'));
            $status  = esc_sql($conn, trim($_POST['status'] ?? 'Active'));
            $perms   = json_decode($_POST['permissions'] ?? '[]', true) ?: [];

            if (!$empId) {
                echo json_encode(['success'=>false,'message'=>'Please select an employee.']); 
                break;
            }

            // Prevent duplicate registration
            $chkResult = mysqli_query($conn, "SELECT id FROM app_registrations WHERE employee_id=$empId AND is_deleted=0");
            if (mysqli_num_rows($chkResult) > 0) {
                echo json_encode(['success'=>false,'message'=>'This employee already has an app registration.']); 
                break;
            }

            // Generate unique activation code
            $activationCode = esc_sql($conn, strtoupper(substr(md5(uniqid((string)$empId, true)), 0, 10)));
            $createdBy = (int)($_SESSION['user_id'] ?? 0);

            mysqli_begin_transaction($conn);
            
            $insertQuery = "INSERT INTO app_registrations 
                            (employee_id, mode, capture_photo, capture_location, status, activation_code, created_by)
                            VALUES ($empId, '$mode', '$capPh', '$capLoc', '$status', '$activationCode', $createdBy)";
            
            if (!mysqli_query($conn, $insertQuery)) throw new Exception(mysqli_error($conn));
            
            $regId = mysqli_insert_id($conn);

            // Insert Permissions
            if ($perms) {
                foreach (array_unique($perms) as $p) {
                    $safeP = esc_sql($conn, $p);
                    $permQuery = "INSERT INTO app_registration_permissions (registration_id, permission_key) VALUES ($regId, '$safeP')";
                    if (!mysqli_query($conn, $permQuery)) throw new Exception(mysqli_error($conn));
                }
            }
            
            mysqli_commit($conn);
            echo json_encode(['success'=>true,'message'=>'App registration created.','id'=>$regId,'activation_code'=>$activationCode]);
            break;

        /* ── UPDATE ───────────────────────────────────────── */
        case 'update':
            $id      = (int)($_POST['id'] ?? 0);
            $mode    = esc_sql($conn, trim($_POST['mode'] ?? 'User'));
            $capPh   = esc_sql($conn, trim($_POST['capture_photo'] ?? 'Required'));
            $capLoc  = esc_sql($conn, trim($_POST['capture_location'] ?? 'Required'));
            $status  = esc_sql($conn, trim($_POST['status'] ?? 'Active'));
            $perms   = json_decode($_POST['permissions'] ?? '[]', true) ?: [];

            if (!$id) { 
                echo json_encode(['success'=>false,'message'=>'Invalid ID']); 
                break; 
            }

            mysqli_begin_transaction($conn);
            
            $updateQuery = "UPDATE app_registrations
                            SET mode='$mode', capture_photo='$capPh', capture_location='$capLoc', status='$status', updated_at=NOW()
                            WHERE id=$id";
                            
            if (!mysqli_query($conn, $updateQuery)) throw new Exception(mysqli_error($conn));

            // Overwrite permissions (delete old, insert new)
            if (!mysqli_query($conn, "DELETE FROM app_registration_permissions WHERE registration_id=$id")) throw new Exception(mysqli_error($conn));
            
            if ($perms) {
                foreach (array_unique($perms) as $p) {
                    $safeP = esc_sql($conn, $p);
                    $permQuery = "INSERT INTO app_registration_permissions (registration_id, permission_key) VALUES ($id, '$safeP')";
                    if (!mysqli_query($conn, $permQuery)) throw new Exception(mysqli_error($conn));
                }
            }
            
            mysqli_commit($conn);
            echo json_encode(['success'=>true,'message'=>'Registration updated.']);
            break;

        /* ── DELETE ───────────────────────────────────────── */
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { 
                echo json_encode(['success'=>false,'message'=>'Invalid ID']); 
                break; 
            }
            
            $delQuery = "UPDATE app_registrations SET is_deleted=1, updated_at=NOW() WHERE id=$id";
            if (!mysqli_query($conn, $delQuery)) throw new Exception(mysqli_error($conn));
            
            echo json_encode(['success'=>true,'message'=>'Registration deleted.']);
            break;

        /* ── SEARCH EMPLOYEES ─────────────────────────────── */
        case 'search_emp':
            $q = esc_sql($conn, trim($_GET['q'] ?? ''));
            
            $searchQuery = "SELECT id, employee_name AS name, employee_code, designation
                            FROM employees
                            WHERE status = 'Active'
                              AND (employee_name LIKE '%$q%' OR employee_code LIKE '%$q%')
                            ORDER BY employee_name LIMIT 20";
                            
            $result = mysqli_query($conn, $searchQuery);
            if (!$result) throw new Exception(mysqli_error($conn));
            
            $data = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
            
            echo json_encode(['success'=>true,'data'=>$data]);
            break;

        /* ── PERMISSIONS LIST ─────────────────────────────── */
        case 'permissions_list':
            echo json_encode(['success'=>true,'data'=>allAppPermissions()]);
            break;

        default:
            echo json_encode(['success'=>false,'message'=>'Invalid action.']);
    }
} catch (Exception $e) {
    // If a transaction was open, roll it back on failure
    mysqli_rollback($conn);
    error_log('App Registration API: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'A database error occurred.']);
}