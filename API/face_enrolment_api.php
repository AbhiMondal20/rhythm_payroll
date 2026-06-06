<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/db_client.php';
require_once '../includes/config.php';

// If your connection variable is different (e.g., $db or $link), assign it here:
$conn = $conn ?? $db ?? null; 

// Writable directory for uploaded face images (outside webroot is better; adjust as needed)
define('FACE_UPLOAD_DIR', __DIR__ . '/../uploads/face_data');
define('FACE_UPLOAD_URL', 'uploads/face_data');   // relative URL served to browser
define('MAX_SLOTS', 6);

// Ensure upload dir exists
if (!is_dir(FACE_UPLOAD_DIR)) {
    mkdir(FACE_UPLOAD_DIR, 0755, true);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // Clear out any accidental whitespace from included files to prevent JSON errors
    if (ob_get_length()) ob_clean(); 

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection missing. Check your variable name in db_client.php']);
        exit();
    }

    switch ($action) {

        /* ── SEARCH EMPLOYEES ─────────────────────────────────────── */
        case 'search':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 1) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $q_esc = mysqli_real_escape_string($conn, $q);
            $like = '%' . $q_esc . '%';
            
            // Using the comprehensive schema provided
            $sql = "SELECT `id`, `employee_code`, `employee_name` AS `name`, `title`, `first_name`, `middle_name`, `last_name`, `dob`, `gender`, `blood`, `marital`, `nationality`, `phone`, `phone2`, `personal_email`, `official_email`, `address`, `aadhaar`, `pan`, `uan`, `esi_no`, `department`, `designation`, `emp_type`, `manager`, `grade`, `location`, `join_date`, `probation`, `notice`, `confirm_date`, `contract_end`, `shift`, `qualification`, `specialisation`, `reg_no`, `ctc_monthly`, `basic_pct`, `hra_pct`, `acc_name`, `acc_no`, `bank`, `ifsc`, `branch`, `pay_mode`, `nom_name`, `nom_rel`, `emg_name`, `emg_rel`, `emg_phone`, `notes`, `ctc_template_id`, `status`, `created_at`, `updated_at`, `face_enrolled` 
                    FROM `employees` 
                    WHERE `status` = 'Active' 
                      AND (`employee_name` LIKE '$like' 
                           OR `employee_code` LIKE '$like' 
                           OR `phone` LIKE '$like' 
                           OR `official_email` LIKE '$like')
                    ORDER BY `employee_name`
                    LIMIT 20";

            $res = mysqli_query($conn, $sql);
            if (!$res) throw new Exception(mysqli_error($conn));

            $data = [];
            while ($row = mysqli_fetch_assoc($res)) {
                // Privacy Safeguard: Mask the sensitive Aadhaar ID in the JSON response
                if (isset($row['aadhaar'])) {
                    $row['aadhaar'] = '[Aadhaar Redacted]';
                }
                
                // Set location logic based on available data, matching previous functionality
                $row['location'] = $row['branch'] ?: ($row['location'] ?: '');
                
                $data[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        /* ── GET EXISTING FACE DATA FOR EMPLOYEE ─────────────────── */
        case 'get_faces':
            $empId = (int)($_GET['employee_id'] ?? 0);
            if (!$empId) { echo json_encode(['success' => false, 'message' => 'Invalid employee']); break; }

            $sql = "SELECT id, slot_index, file_url
                    FROM employee_face_data
                    WHERE employee_id = $empId AND is_deleted = 0
                    ORDER BY slot_index";
            
            $res = mysqli_query($conn, $sql);
            if (!$res) throw new Exception(mysqli_error($conn));

            $data = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        /* ── UPLOAD SINGLE FACE IMAGE ─────────────────────────────── */
        case 'upload_face':
            $empId     = (int)($_POST['employee_id'] ?? 0);
            $slotIndex = (int)($_POST['slot_index']  ?? 0);

            if (!$empId || $slotIndex < 0 || $slotIndex >= MAX_SLOTS) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']); break;
            }
            if (empty($_FILES['face_image']) || $_FILES['face_image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'File upload failed']); break;
            }

            $file     = $_FILES['face_image'];
            $mimeType = mime_content_type($file['tmp_name']);
            $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
            
            if (!in_array($mimeType, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Only JPEG/PNG/WebP images are allowed']); break;
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File size must be under 5 MB']); break;
            }

            $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeType];
            $filename = "emp_{$empId}_slot{$slotIndex}_" . time() . ".{$ext}";
            $dest     = rtrim(FACE_UPLOAD_DIR, '/') . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                echo json_encode(['success' => false, 'message' => 'Could not save file']); break;
            }

            $fileUrlEsc = mysqli_real_escape_string($conn, rtrim(FACE_UPLOAD_URL, '/') . '/' . $filename);
            $userId     = (int)($_SESSION['user_id'] ?? 0);

            mysqli_autocommit($conn, false);

            // Upsert: remove old record for this slot, insert new
            $upd_sql = "UPDATE employee_face_data SET is_deleted=1, updated_at=NOW()
                        WHERE employee_id=$empId AND slot_index=$slotIndex AND is_deleted=0";
            if (!mysqli_query($conn, $upd_sql)) throw new Exception(mysqli_error($conn));

            $ins_sql = "INSERT INTO employee_face_data (employee_id, slot_index, file_url, created_by)
                        VALUES ($empId, $slotIndex, '$fileUrlEsc', $userId)";
            if (!mysqli_query($conn, $ins_sql)) throw new Exception(mysqli_error($conn));

            $recordId = mysqli_insert_id($conn);

            mysqli_commit($conn);
            mysqli_autocommit($conn, true);

            echo json_encode([
                'success'   => true,
                'file_url'  => rtrim(FACE_UPLOAD_URL, '/') . '/' . $filename,
                'record_id' => $recordId,
            ]);
            break;

        /* ── SAVE ALL (batch confirm) ─────────────────────────────── */
        case 'save':
            // Images are already uploaded individually; this just marks the record as finalised.
            $empId = (int)($_POST['employee_id'] ?? 0);
            if (!$empId) { echo json_encode(['success' => false, 'message' => 'Invalid employee']); break; }

            $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employee_face_data WHERE employee_id=$empId AND is_deleted=0");
            if (!$res) throw new Exception(mysqli_error($conn));
            
            $row = mysqli_fetch_assoc($res);
            $count = (int)($row['cnt'] ?? 0);

            if ($count === 0) {
                echo json_encode(['success' => false, 'message' => 'Please upload at least one face image.']);
                break;
            }

            // Mark employee as face-enrolled
            if (!mysqli_query($conn, "UPDATE employees SET face_enrolled=1 WHERE id=$empId")) {
                 throw new Exception(mysqli_error($conn));
            }

            echo json_encode(['success' => true, 'message' => "Facial data saved for employee #{$empId}. ({$count} image(s))"]);
            break;

        /* ── DELETE SINGLE FACE IMAGE ─────────────────────────────── */
        case 'delete_face':
            $recordId = (int)($_POST['record_id'] ?? 0);
            if (!$recordId) { echo json_encode(['success' => false, 'message' => 'Invalid record']); break; }

            if (!mysqli_query($conn, "UPDATE employee_face_data SET is_deleted=1, updated_at=NOW() WHERE id=$recordId")) {
                throw new Exception(mysqli_error($conn));
            }

            echo json_encode(['success' => true, 'message' => 'Image removed.']);
            break;

        /* ── RESET (delete all faces for employee) ───────────────── */
        case 'reset_faces':
            $empId = (int)($_POST['employee_id'] ?? 0);
            if (!$empId) { echo json_encode(['success' => false, 'message' => 'Invalid employee']); break; }

            if (!mysqli_query($conn, "UPDATE employee_face_data SET is_deleted=1, updated_at=NOW() WHERE employee_id=$empId AND is_deleted=0")) {
                 throw new Exception(mysqli_error($conn));
            }

            echo json_encode(['success' => true, 'message' => 'All face data cleared.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    if (isset($conn)) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
    }
    error_log('Face Enrolment API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Check error logs.']);
}