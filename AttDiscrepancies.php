<?php
session_start();
if (!isset($_SESSION['login'])) {
    if (isset($_POST['action']) || isset($_GET['action']) || isset($_GET['ajax_search']) || isset($_GET['ajax_advanced_search'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login');
    exit();
}

// ==========================================
// 1. AJAX HANDLERS
// ==========================================

// Handle AJAX Request for Quick Autocomplete Search
if (isset($_GET['ajax_search'])) {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    
    header('Content-Type: application/json');
    $results = [];
    
    if (isset($conn)) {
        $search = mysqli_real_escape_string($conn, trim($_GET['ajax_search']));
        if (!empty($search)) {
            $sql = "SELECT employee_code, employee_name FROM employees 
                    WHERE employee_name LIKE '%$search%' 
                    OR employee_code LIKE '%$search%' 
                    LIMIT 10";
            $res = mysqli_query($conn, $sql);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $results[] = $row;
                }
            }
        }
    }
    echo json_encode($results);
    exit;
}

// Handle AJAX Request for Advanced Modal Search
if (isset($_GET['ajax_advanced_search'])) {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    
    header('Content-Type: application/json');
    $results = [];
    
    if (isset($conn)) {
        $sql = "SELECT id, employee_code, employee_name FROM employees WHERE 1=1";
        
        // Fix: Properly handle the Status filter to prevent 0 results when 'Inactive' is selected
        if (!empty($_GET['status'])) {
            $status = mysqli_real_escape_string($conn, $_GET['status']);
            if ($status === 'Active') {
                $sql .= " AND (status = 'Active' OR status = 1)";
            } else if ($status === 'Inactive') {
                $sql .= " AND (status = 'Inactive' OR status = 0)";
            }
        } else {
            $sql .= " AND (status = 'Active' OR status = 1)";
        }

        if (!empty($_GET['name_code'])) {
            $search = mysqli_real_escape_string($conn, trim($_GET['name_code']));
            $sql .= " AND (employee_name LIKE '%$search%' OR employee_code LIKE '%$search%')";
        }
        if (!empty($_GET['org'])) {
            $org = mysqli_real_escape_string($conn, $_GET['org']);
            $sql .= " AND company_id = '$org'"; 
        }
        if (!empty($_GET['loc'])) {
            $loc = mysqli_real_escape_string($conn, $_GET['loc']);
            $sql .= " AND location_id = '$loc'";
        }
        if (!empty($_GET['dept'])) {
            $dept = mysqli_real_escape_string($conn, $_GET['dept']);
            $sql .= " AND department_id = '$dept'";
        }
        if (!empty($_GET['desig'])) {
            $desig = mysqli_real_escape_string($conn, $_GET['desig']);
            $sql .= " AND designation_id = '$desig'";
        }
        if (!empty($_GET['group'])) {
            $group = mysqli_real_escape_string($conn, $_GET['group']);
            $sql .= " AND group_id = '$group'";
        }
        if (!empty($_GET['sub_group'])) {
            $sub_group = mysqli_real_escape_string($conn, $_GET['sub_group']);
            $sql .= " AND sub_group_id = '$sub_group'";
        }
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $sql .= " LIMIT $limit";
        
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $results[] = $row;
            }
        }
    }
    echo json_encode($results);
    exit;
}

// Handle AJAX Request for Deleting Time Entries
if (isset($_POST['action']) && $_POST['action'] === 'delete_entry') {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    header('Content-Type: application/json');

    if (isset($conn)) {
        $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code']);
        $entry_date = mysqli_real_escape_string($conn, $_POST['entry_date']);
        
        $delete_sql = "DELETE FROM time_entries WHERE employee_code = '$emp_code' AND entry_date = '$entry_date'";

        if (mysqli_query($conn, $delete_sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    }
    exit;
}

// Handle AJAX Request for Updating Time Entries
if (isset($_POST['action']) && $_POST['action'] === 'update_entry') {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    header('Content-Type: application/json');

    if (isset($conn)) {
        $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code']);
        $entry_date = mysqli_real_escape_string($conn, $_POST['entry_date']);
        $raw_day_status = mysqli_real_escape_string($conn, $_POST['day_status']);
        
        // Dynamically split combined statuses into day_status_1 and day_status_2
        $ds1 = 'A'; $ds2 = 'A';
        switch ($raw_day_status) {
            case 'PP': $ds1 = 'P'; $ds2 = 'P'; break;
            case 'P*A': $ds1 = 'P'; $ds2 = 'A'; break;
            case 'A*P': $ds1 = 'A'; $ds2 = 'P'; break;
            case 'AA': $ds1 = 'A'; $ds2 = 'A'; break;
            case 'WO': $ds1 = 'WO'; $ds2 = 'WO'; break;
            case 'HO': $ds1 = 'HO'; $ds2 = 'HO'; break;
            case 'WW': $ds1 = 'WW'; $ds2 = 'WW'; break;
            case 'HW': $ds1 = 'HW'; $ds2 = 'HW'; break;
            case 'WW*': $ds1 = 'WW'; $ds2 = 'A'; break;
            case '*WW': $ds1 = 'A'; $ds2 = 'WW'; break;
            case 'HW*': $ds1 = 'HW'; $ds2 = 'A'; break;
            case '*HW': $ds1 = 'A'; $ds2 = 'HW'; break;
            case 'LOP*': $ds1 = 'LOP'; $ds2 = 'A'; break;
            case '*LOP': $ds1 = 'A'; $ds2 = 'LOP'; break;
            default: $ds1 = $raw_day_status; $ds2 = $raw_day_status; break;
        }

        $check_in = mysqli_real_escape_string($conn, $_POST['check_in']);
        $check_out = mysqli_real_escape_string($conn, $_POST['check_out']);
        $hours_worked = mysqli_real_escape_string($conn, $_POST['hours_worked']);
        $over_time = mysqli_real_escape_string($conn, $_POST['over_time']);
        $under_time = mysqli_real_escape_string($conn, $_POST['under_time']);
        $normal_hours = mysqli_real_escape_string($conn, $_POST['normal_hours']);
        $late_hours = mysqli_real_escape_string($conn, $_POST['late_hours']);
        $early_hours = mysqli_real_escape_string($conn, $_POST['early_hours']);
        $status_code = mysqli_real_escape_string($conn, $_POST['status_code']);
        $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
        $calc_in_out = ($_POST['calc_in_out'] === 'true') ? 1 : 0;

        $check_in_val = !empty($check_in) ? "'$check_in'" : "NULL";
        $check_out_val = !empty($check_out) ? "'$check_out'" : "NULL";

        $check_sql = "SELECT 1 FROM time_entries WHERE employee_code = '$emp_code' AND entry_date = '$entry_date'";
        $check_res = mysqli_query($conn, $check_sql);

        if (mysqli_num_rows($check_res) > 0) {
            $sql = "UPDATE time_entries SET 
                day_status_1 = '$ds1', day_status_2 = '$ds2', check_in_time = $check_in_val, check_out_time = $check_out_val,
                hours_worked = '$hours_worked', over_time_hours = '$over_time', under_time_hours = '$under_time',
                normal_hours = '$normal_hours', late_hours = '$late_hours', early_hours = '$early_hours',
                status_code = '$status_code', remarks = '$remarks', calculate_per_in_out = '$calc_in_out', record_status = 'Manual'
                WHERE employee_code = '$emp_code' AND entry_date = '$entry_date'";
        } else {
            $sql = "INSERT INTO time_entries (
                employee_code, entry_date, day_status_1, day_status_2, check_in_time, check_out_time, 
                hours_worked, over_time_hours, under_time_hours, normal_hours, 
                late_hours, early_hours, status_code, remarks, calculate_per_in_out, record_status
            ) VALUES (
                '$emp_code', '$entry_date', '$ds1', '$ds2', $check_in_val, $check_out_val, 
                '$hours_worked', '$over_time', '$under_time', '$normal_hours', 
                '$late_hours', '$early_hours', '$status_code', '$remarks', '$calc_in_out', 'Manual'
            )";
        }

        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    }
    exit;
}

// Handle AJAX Request for Regularize Bulk
if (isset($_POST['action']) && $_POST['action'] === 'regularize') {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    header('Content-Type: application/json');

    if (isset($conn) && isset($_POST['entries'])) {
        $entries = json_decode($_POST['entries'], true);
        if(is_array($entries)) {
            $success_count = 0;
            foreach($entries as $entry) {
                $emp_code = mysqli_real_escape_string($conn, $entry['emp_code']);
                $entry_date = mysqli_real_escape_string($conn, $entry['entry_date']);
                
                $check_sql = "SELECT 1 FROM time_entries WHERE employee_code = '$emp_code' AND entry_date = '$entry_date'";
                $check_res = mysqli_query($conn, $check_sql);
                
                if (mysqli_num_rows($check_res) > 0) {
                    $sql = "UPDATE time_entries SET day_status_1 = 'P', day_status_2 = 'P', record_status = 'Manual' WHERE employee_code = '$emp_code' AND entry_date = '$entry_date'";
                } else {
                    $sql = "INSERT INTO time_entries (employee_code, entry_date, day_status_1, day_status_2, record_status) VALUES ('$emp_code', '$entry_date', 'P', 'P', 'Manual')";
                }
                if(mysqli_query($conn, $sql)) {
                    $success_count++;
                }
            }
            echo json_encode(['success' => true, 'updated' => $success_count]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data format']);
        }
    }
    exit;
}

// ==========================================
// 2. MAIN PAGE LOGIC & FILTERING
// ==========================================
require_once 'includes/config.php';
require_once 'includes/db_client.php';

$page_title = 'Discrepancies';

$search_query = isset($_GET['employee']) ? trim($_GET['employee']) : '';
$date_range = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';
$discrepancy_type = isset($_GET['discrepancy_type']) ? trim($_GET['discrepancy_type']) : 'all';
$is_searched = !empty($search_query);

$time_entries = [];
$employee_details = null;
$shift_assignments = [];

$status_options = [
    'PP' => 'Present (PP)', 'AA' => 'Absent (AA)', 'P*A' => 'First Half Present (P*A)', 'A*P' => 'Second Half Present (A*P)',
    'HO' => 'Holiday (HO)', 'WO' => 'Week Off (WO)', 'WW' => 'Worked On Week Off (WW)', 'HW' => 'Worked On Holiday (HW)',
    'WW*' => 'Worked On Week Off First Half (WW*)', '*WW' => 'Worked On Week Off Second Half (*WW)',
    'HW*' => 'Worked On Holiday First Half (HW*)', '*HW' => 'Worked On Holiday Second Half (*HW)',
    '*LOP' => 'Loss Of Pay in Second Half (*LOP)', 'LOP*' => 'Loss Of Pay in First Half (LOP*)'
];

function formatTimeForDisplay($timeString) {
    if (empty($timeString)) return '';
    if (strpos($timeString, 'Hrs') !== false) return $timeString;
    if (preg_match('/^(\d{2,}):(\d{2})/', $timeString, $matches)) {
        return $matches[1] . ':' . $matches[2] . ' Hrs';
    }
    return $timeString;
}

// Fix: Properly handle single dates vs date ranges from Flatpickr
if (!empty($date_range)) {
    if (strpos($date_range, ' to ') !== false) {
        $dates = explode(' to ', $date_range);
        if (count($dates) == 2) {
            $start_timestamp = strtotime($dates[0]);
            $end_timestamp = strtotime($dates[1]);
            if ($start_timestamp !== false && $end_timestamp !== false) {
                $start_date = date('Y-m-d', $start_timestamp);
                $end_date = date('Y-m-d', $end_timestamp);
            } else {
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-d'); 
                $date_range = date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date));
            }
        }
    } else {
        $single_timestamp = strtotime($date_range);
        if ($single_timestamp !== false) {
            $start_date = date('Y-m-d', $single_timestamp);
            $end_date = date('Y-m-d', $single_timestamp);
            $date_range = date('d M Y', $single_timestamp) . ' to ' . date('d M Y', $single_timestamp);
        } else {
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-d'); 
            $date_range = date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date));
        }
    }
} else {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d'); 
    $date_range = date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date));
}

$period_display = date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date));
if ($start_date === $end_date) {
    $period_display = date('d M Y', strtotime($start_date));
} elseif (date('m Y', strtotime($start_date)) === date('m Y', strtotime($end_date)) && date('d', strtotime($start_date)) == '01' && date('d', strtotime($end_date)) == date('t', strtotime($end_date))) {
    $period_display = date('F Y', strtotime($start_date));
}

if ($is_searched && isset($conn)) {
    $safe_search = mysqli_real_escape_string($conn, $search_query);
    
    $emp_sql = "SELECT * FROM employees WHERE employee_name LIKE '%$safe_search%' OR employee_code = '$safe_search' LIMIT 1";
    $emp_result = mysqli_query($conn, $emp_sql);
    
    $time_sql = "SELECT * FROM time_entries WHERE ";
    $emp_code = '';
    
    if ($emp_result && mysqli_num_rows($emp_result) > 0) {
        $employee_details = mysqli_fetch_assoc($emp_result);
        $emp_code = $employee_details['employee_code'];
        $safe_emp_code = mysqli_real_escape_string($conn, $emp_code);
        $safe_emp_id = mysqli_real_escape_string($conn, $employee_details['id'] ?? '');
        
        $time_sql .= "employee_code = '$safe_emp_code'";
        
        $assign_sql = "SELECT sa.start_date, sa.end_date, sa.weekdays, s.shift_name 
                       FROM att_shift_assignments sa
                       LEFT JOIN att_shifts s ON sa.shift_id = s.id
                       WHERE sa.emp_id = '$safe_emp_code' OR sa.emp_id = '$safe_emp_id'
                       ORDER BY sa.start_date DESC";
                       
        $assign_res = mysqli_query($conn, $assign_sql);
        if ($assign_res) {
            while ($row = mysqli_fetch_assoc($assign_res)) {
                $shift_assignments[] = $row;
            }
        }
    } else {
        $time_sql .= "(employee_name LIKE '%$safe_search%' OR employee_code LIKE '%$safe_search%')";
    }
    
    $start_safe = mysqli_real_escape_string($conn, $start_date);
    $end_safe = mysqli_real_escape_string($conn, $end_date);
    $time_sql .= " AND entry_date BETWEEN '$start_safe' AND '$end_safe' ORDER BY entry_date ASC";
    
    $db_entries = [];
    $time_result = mysqli_query($conn, $time_sql);
    if ($time_result) {
        while ($row = mysqli_fetch_assoc($time_result)) {
            $db_entries[$row['entry_date']] = $row;
        }
    }
    
    try {
        $begin = new DateTime($start_date);
        $end = new DateTime($end_date);
        $end->modify('+1 day'); 

        $interval = new DateInterval('P1D');
        $period = new DatePeriod($begin, $interval, $end);

        foreach ($period as $dt) {
            $date_str = $dt->format('Y-m-d');
            $day_num = $dt->format('N'); 
            $day_short = $dt->format('D'); 
            
            $assigned_shift_name = 'Not Assigned';
            foreach ($shift_assignments as $assign) {
                $assign_start = $assign['start_date'];
                $assign_end = $assign['end_date'];
                $weekdays = $assign['weekdays'] ?? '';
                
                $is_after_start = ($date_str >= $assign_start);
                $is_before_end = (empty($assign_end) || $assign_end === '0000-00-00' || $date_str <= $assign_end);
                
                if ($is_after_start && $is_before_end) {
                    if (empty(trim($weekdays))) {
                        $assigned_shift_name = $assign['shift_name'];
                        break;
                    } else {
                        $allowed_days = array_map('trim', explode(',', $weekdays));
                        if (in_array((string)$day_num, $allowed_days) || in_array($day_short, $allowed_days)) {
                            $assigned_shift_name = $assign['shift_name'];
                            break;
                        }
                    }
                }
            }
            
            $entry = [];
            if (isset($db_entries[$date_str])) {
                $entry = $db_entries[$date_str];
                $entry['assigned_shift_name'] = $assigned_shift_name;
            } else {
                $is_sunday = ($day_num == 7);
                $default_status = $is_sunday ? 'WO' : 'A'; 
                
                $entry = [
                    'employee_code' => $emp_code,
                    'entry_date' => $date_str,
                    'day_status_1' => $default_status,
                    'day_status_2' => $default_status,
                    'check_in_time' => null,
                    'check_out_time' => null,
                    'hours_worked' => '',
                    'over_time_hours' => '',
                    'under_time_hours' => '',
                    'normal_hours' => '',
                    'late_hours' => '',
                    'early_hours' => '',
                    'status_code' => '',
                    'remarks' => '',
                    'calculate_per_in_out' => 0,
                    'record_status' => 'System',
                    'assigned_shift_name' => $assigned_shift_name 
                ];
            }

            // Discrepancy Filter Logic
            $is_missing = empty($entry['check_in_time']) && empty($entry['check_out_time']) && $entry['day_status_1'] != 'WO';
            $is_short = !empty($entry['under_time_hours']) && $entry['under_time_hours'] !== '00:00:00';
            $is_late = !empty($entry['late_hours']) && $entry['late_hours'] !== '00:00:00';
            $is_no_attendance = ($entry['day_status_1'] === 'A' || $entry['day_status_1'] === 'AA');

            $skip = false;
            if ($discrepancy_type != 'all') {
                if ($discrepancy_type == 'short_hours' && !$is_short) $skip = true;
                if ($discrepancy_type == 'missing_swipes' && !$is_missing) $skip = true;
                if ($discrepancy_type == 'late_login' && !$is_late) $skip = true;
                if ($discrepancy_type == 'no_attendance' && !$is_no_attendance) $skip = true;
            }

            if (!$skip) {
                $time_entries[] = $entry;
            }
        }
    } catch (Exception $e) { }
}

// ==========================================
// 3. FETCH DATA FOR UI RENDER (Modal Data)
// ==========================================
$organizations = [];
$org_result = @mysqli_query($conn, "SELECT `id`, `client_name` FROM `companies` WHERE `status` = 'Active' OR `status` = 1");
if ($org_result) { while ($row = mysqli_fetch_assoc($org_result)) { $organizations[] = $row; } }

$locations = [];
$loc_result = @mysqli_query($conn, "SELECT `id`, `location_name` FROM `org_locations` WHERE `status` = 'Active' OR `status` = 1");
if ($loc_result) { while ($row = mysqli_fetch_assoc($loc_result)) { $locations[] = $row; } }

$departments = [];
$dept_result = @mysqli_query($conn, "SELECT `id`, `dept_name` FROM `org_departments` WHERE `status` = 'Active' OR `status` = 1");
if ($dept_result) { while ($row = mysqli_fetch_assoc($dept_result)) { $departments[] = $row; } }

$designations = [];
$desig_result = @mysqli_query($conn, "SELECT `id`, desig_name as `designation_name` FROM `designations` WHERE `status` = 'Active' OR `status` = 1");
if (!$desig_result) { 
    $desig_result = @mysqli_query($conn, "SELECT `id`, desig_name as `designation_name` FROM `org_designations` WHERE `status` = 'Active' OR `status` = 1"); 
}
if ($desig_result) { while ($row = mysqli_fetch_assoc($desig_result)) { $designations[] = $row; } }

$groups = [];
$group_result = @mysqli_query($conn, "SELECT `id`, `group_name` FROM `org_groups` WHERE `status` = 'Active' OR `status` = 1");
if ($group_result) { while ($row = mysqli_fetch_assoc($group_result)) { $groups[] = $row; } }

$sub_groups = [];
$sub_group_result = @mysqli_query($conn, "SELECT `id`, `sub_group_name` FROM `org_sub_groups` WHERE `status` = 'Active' OR `status` = 1");
if ($sub_group_result) { while ($row = mysqli_fetch_assoc($sub_group_result)) { $sub_groups[] = $row; } }
ob_start();
?>
<!-- Include CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="includes/assets/style.css">
<style>
.flex-between {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.flex-center {
    display: flex;
    justify-content: center;
    align-items: center;
}

.flex-end {
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

.d-flex {
    display: flex;
    align-items: center;
}

.gap-2 {
    gap: 8px;
}

.gap-3 {
    gap: 16px;
}

.mb-1 {
    margin-bottom: 4px;
}

.mb-3 {
    margin-bottom: 16px;
}

.mb-4 {
    margin-bottom: 24px;
}

.mt-2 {
    margin-top: 8px;
}

.mt-3 {
    margin-top: 16px;
}

.mt-4 {
    margin-top: 24px;
}

.mt-5 {
    margin-top: 32px;
}

.p-0 {
    padding: 0 !important;
}

.ps-4 {
    padding-left: 24px;
}

.pe-4 {
    padding-right: 24px;
}

.text-dark {
    color: #111827;
}

.text-muted {
    color: #6b7280;
}

.text-end {
    text-align: right;
}

.fw-bold {
    font-weight: 600;
}

.w-100 {
    width: 100%;
}

.shadow-sm {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.rounded-3 {
    border-radius: 8px;
}

.bg-white {
    background-color: #ffffff;
}

.grid-row {
    display: flex;
    flex-wrap: wrap;
    margin-left: -12px;
    margin-right: -12px;
}

.grid-col-3 {
    width: 25%;
    padding: 0 12px;
    flex: 0 0 auto;
}

.grid-col-6 {
    width: 50%;
    padding: 0 12px;
    flex: 0 0 auto;
}

.card {
    background: #fff;
    border-radius: 8px;
    min-height: 450px;
}

.attendance-tabs {
    border-bottom: 1px solid #dee2e6;
}

.attendance-tabs a {
    color: #6c757d;
    text-decoration: none;
    padding: 3px 5px;
    gap: 12px;
    display: inline-block;
    font-size: 14px;
    transition: color 0.2s;
}

.attendance-tabs .separator {
    color: #D1D5DB;
    font-size: 14px;
}

.attendance-tabs a:hover {
    color: #495057;
}

.attendance-tabs a.active {
    color: #0d6efd;
    border-bottom: 2px solid #0d6efd;
    font-weight: 500;
}

.filter-bar {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.search-wrapper {
    position: relative;
    width: 300px;
}

.search-wrapper .form-control {
    padding-left: 35px;
    border-radius: 4px;
}

.search-wrapper .bi-search {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 14px;
}

.search-chip {
    position: absolute;
    top: 4px;
    left: 4px;
    bottom: 4px;
    right: 4px;
    background-color: #fff;
    display: flex;
    align-items: center;
    padding: 0 8px;
    font-size: 13px;
    color: #111827;
    z-index: 5;
}

.search-chip a {
    color: #6b7280;
    margin-left: auto;
    text-decoration: none;
    font-size: 16px;
    display: flex;
    align-items: center;
}

.search-chip a:hover {
    color: #ef4444;
}

.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #d1d5db;
    border-top: none;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    max-height: 250px;
    overflow-y: auto;
    display: none;
    margin-top: 2px;
}

.autocomplete-item {
    padding: 10px 14px;
    cursor: pointer;
    font-size: 13.5px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    flex-direction: column;
    transition: background-color 0.1s;
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.autocomplete-item:hover {
    background-color: #eff6ff;
}

.autocomplete-name {
    font-weight: 600;
    color: #111827;
}

.autocomplete-code {
    font-size: 11px;
    color: #6b7280;
    margin-top: 2px;
}

.form-control {
    width: 100%;
    border-radius: 4px;
    font-size: 13.5px;
    border: 1px solid #d1d5db;
    height: 36px;
    padding: 6px 12px;
    background-color: #fff;
    outline: none;
    box-shadow: none;
}

.form-control:focus {
    border-color: #0d6efd;
}

.date-dropdown {
    display: flex;
    align-items: center;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: #fff;
    height: 36px;
    width: 260px;
    padding: 0 12px;
    gap: 8px;
    cursor: pointer;
}

.date-dropdown .bi-calendar {
    color: #6b7280;
    border-right: 1px solid #d1d5db;
    padding-right: 8px;
    font-size: 15px;
}

.date-dropdown input {
    border: none;
    background: transparent;
    flex: 1;
    outline: none;
    font-size: 13.5px;
    color: #4b5563;
    cursor: pointer;
}

.custom-select-wrapper {
    position: relative;
    width: 180px;
}

.custom-select {
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 36px;
    padding: 0 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background-color: #fff;
    cursor: pointer;
    font-size: 13.5px;
    color: #4b5563;
    user-select: none;
}

.custom-select:hover {
    border-color: #9ca3af;
}

.custom-select-options {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    margin-top: 4px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    display: none;
    overflow: hidden;
}

.custom-select-options.open {
    display: block;
}

.custom-select-option {
    padding: 8px 12px;
    font-size: 13.5px;
    color: #111827;
    cursor: pointer;
    transition: background 0.2s;
}

.custom-select-option:hover {
    background: #f3f4f6;
}

.custom-select-option.selected {
    background: #eff6ff;
    color: #0d6efd;
    font-weight: 500;
}

.btn-apply {
    background-color: #0d6efd;
    color: #fff;
    height: 36px;
    padding: 0 20px;
    font-size: 13.5px;
    border-radius: 4px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: 0.2s;
}

.btn-apply:hover,
.btn-primary:hover {
    background-color: #0b5ed7;
    color: #fff;
}

.btn-outline-secondary {
    border-color: #d1d5db;
    color: #4b5563;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    padding: 0 16px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

.btn-outline-secondary:hover {
    background: #f3f4f6;
    color: #111827;
}

.btn-primary {
    background: #0d6efd;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 8px 16px;
    cursor: pointer;
    font-weight: 500;
}

.btn-danger-outline {
    color: #ef4444;
    background: #fff;
    border: 1px solid #ef4444;
    border-radius: 4px;
    padding: 8px 16px;
    cursor: pointer;
}

.btn-danger-outline:hover {
    background: #fef2f2;
}

.btn-secondary-outline {
    color: #4b5563;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    padding: 8px 16px;
    cursor: pointer;
}

/* Dual Badges */
.status-badge-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 8px;
    margin-right: 4px;
}

.status-p-bg {
    background-color: #e6f4ea;
    color: #1e8e3e;
    border: 1px solid #ceead6;
}

.status-a-bg {
    background-color: #fff4e5;
    color: #ed6c02;
    border: 1px solid #ffe2c2;
}

.status-other-bg {
    background-color: #f3f4f6;
    color: #4b5563;
    border: 1px solid #d1d5db;
}

.badge-disc {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

.badge-short-hours {
    background-color: #fff4e5;
    color: #ed6c02;
    border: 1px solid #ffe2c2;
}

.badge-late-login {
    background-color: #e6f4ea;
    color: #1e8e3e;
    border: 1px solid #ceead6;
}

.badge-missing {
    background-color: #fef2f2;
    color: #ef4444;
    border: 1px solid #fee2e2;
}

.selected-emp-chip {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background-color: #fff;
    margin-bottom: 20px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.selected-emp-chip .form-check-input {
    margin-top: 0;
    margin-right: 12px;
    cursor: pointer;
}

.table-container {
    border-radius: 6px;
    overflow: hidden;
    width: 100%;
    border: 1px solid #e5e7eb;
    background: #fff;
}

.table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    margin-bottom: 0;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    font-size: 13px;
    color: #4b5563;
    border-bottom: 1px solid #e5e7eb;
    padding: 12px 16px;
}

.table td {
    vertical-align: middle;
    font-size: 13.5px;
    color: #111827;
    padding: 12px 16px;
    border-bottom: 1px solid #f3f4f6;
}

.table tbody tr:hover {
    background-color: #f9fafb;
}

.expandable-row {
    background-color: #ffffff;
    display: none;
    border-bottom: 1px solid #e5e7eb;
}

.expandable-row.show {
    display: table-row;
}

.edit-form-container {
    padding: 24px 30px;
    background-color: #ffffff;
    border-top: 1px dashed #e5e7eb;
}

.edit-form-container .form-label {
    display: block;
    font-size: 13px;
    color: #4b5563;
    font-weight: 500;
    margin-bottom: 4px;
}

.edit-form-container .form-control,
.edit-form-container select {
    background: transparent;
    border: none;
    border-bottom: 1px solid #d1d5db;
    border-radius: 0;
    padding-left: 0;
    padding-right: 0;
    box-shadow: none;
}

.edit-form-container .form-control:focus,
.edit-form-container select:focus {
    border-bottom-color: #0d6efd;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.form-check-input {
    width: 16px;
    height: 16px;
    cursor: pointer;
    margin: 0;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state-svg {
    max-width: 350px;
    height: auto;
    margin-top: 30px;
}

/* Modal Styling */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1050;
    display: none;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fff;
    width: 900px;
    max-width: 95%;
    border-radius: 8px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}

.modal-header {
    padding: 16px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6b7280;
    cursor: pointer;
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.search-line-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.search-line-wrapper svg {
    position: absolute;
    left: 10px;
    width: 16px;
    height: 16px;
    stroke: #9ca3af;
    stroke-width: 2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.search-line-wrapper input {
    width: 100%;
    height: 40px;
}

.modal-filter-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 12px;
    color: #4b5563;
    margin-bottom: 4px;
}

.line-input {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    height: 36px;
    padding: 0 10px;
    font-size: 13px;
    color: #111827;
}

.modal-search-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-search-row select {
    border: 1px solid #d1d5db;
    border-radius: 4px;
    padding: 4px;
    font-size: 13px;
}

.modal-results-layout {
    display: flex;
    gap: 24px;
}

.modal-emp-list-sec {
    flex: 1;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-emp-header {
    padding: 12px 16px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
    color: #111827;
}

.modal-emp-grid {
    flex: 1;
    min-height: 200px;
    max-height: 300px;
    overflow-y: auto;
}

.modal-recent-sec {
    width: 300px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.recent-tabs {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
}

.recent-tab {
    flex: 1;
    padding: 10px;
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    background: #f9fafb;
}

.recent-tab.active {
    background: #fff;
    color: #0d6efd;
    border-bottom: 2px solid #0d6efd;
}

.recent-list {
    list-style: none;
    padding: 0;
    margin: 0;
    overflow-y: auto;
    flex: 1;
    max-height: 300px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #f9fafb;
    border-radius: 0 0 8px 8px;
}

.btn-outline {
    border: 1px solid #d1d5db;
    background: #fff;
    color: #4b5563;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 13.5px;
    cursor: pointer;
}
</style>

<div class="container">
    <div class="flex-between mb-1">
        <h4 class="text-dark fw-bold m-0" style="font-size: 1.25rem;">Attendance</h4>
        <div class="attendance-tabs m-0 border-0">
            <a href="attendance">Time Entries</a>
            <span class="separator">|</span>
            <a href="AttCalendarView">Calendar View</a>
            <span class="separator">|</span>
            <a href="ManualAttendance">Manual Attendance</a>
            <span class="separator">|</span>
            <a href="AttDiscrepancies" class="active">Discrepancies</a>
            <span class="separator">|</span>
            <a href="AttProcessTimeCard">Process Time Card</a>
            <span class="separator">|</span>
            <a href="AttApproveOvertime">Approve Overtime</a>
        </div>
    </div>
    <div class="card shadow-sm mt-2">
        <div class="card-body p-4">
            <h6 class="text-dark fw-bold mb-1"
                style="font-size: 13.5px; letter-spacing: 0.5px; margin-top: 0; text-transform: uppercase;">
                Discrepancies</h6>
            <p class="text-muted mb-4" style="font-size: 12px;">Note: Once the time card has been processed, this
                discrepancies page will automatically reflect the updated and accurate data.</p>

            <form method="GET" action="" class="filter-bar" id="filterForm">
                <div class="search-wrapper">
                    <?php if ($is_searched): ?>
                    <div class="search-chip">
                        <?= htmlspecialchars($search_query) ?>
                        <a href="?"><i class="bi bi-x"></i></a>
                    </div>
                    <input type="text" name="employee" id="employeeSearchInput" class="form-control" disabled
                        style="background-color: #fff;" value="<?= htmlspecialchars($search_query) ?>">
                    <?php else: ?>
                    <i class="bi bi-search"></i>
                    <!-- Fix: Ensure the search input always maps to 'employee' parameter -->
                    <input type="text" name="employee" id="employeeSearchInput" class="form-control"
                        placeholder="Search by name or #code" autocomplete="off"
                        value="<?= htmlspecialchars($search_query) ?>">
                    <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
                    <?php endif; ?>
                </div>

                <div class="custom-select-wrapper" id="discrepancySelect">
                    <div class="custom-select" onclick="toggleDropdown()">
                        <span id="selectedDiscText">
                            <?php 
                                $disc_labels = ['all'=>'Discrepancy Type', 'short_hours'=>'Short Hours', 'missing_swipes'=>'Missing Swipes', 'no_attendance'=>'No Attendance', 'late_login'=>'Late Login'];
                                echo htmlspecialchars($disc_labels[$discrepancy_type] ?? 'Discrepancy Type');
                            ?>
                        </span>
                        <i class="bi bi-chevron-down text-muted" style="font-size:12px;"></i>
                    </div>
                    <div class="custom-select-options">
                        <div class="custom-select-option <?= $discrepancy_type=='all'?'selected':'' ?>"
                            data-value="all">Discrepancy Type (All)</div>
                        <div class="custom-select-option <?= $discrepancy_type=='short_hours'?'selected':'' ?>"
                            data-value="short_hours">Short Hours</div>
                        <div class="custom-select-option <?= $discrepancy_type=='missing_swipes'?'selected':'' ?>"
                            data-value="missing_swipes">Missing Swipes</div>
                        <div class="custom-select-option <?= $discrepancy_type=='no_attendance'?'selected':'' ?>"
                            data-value="no_attendance">No Attendance</div>
                        <div class="custom-select-option <?= $discrepancy_type=='late_login'?'selected':'' ?>"
                            data-value="late_login">Late Login</div>
                    </div>
                    <input type="hidden" name="discrepancy_type" id="discTypeValue"
                        value="<?= htmlspecialchars($discrepancy_type) ?>">
                </div>

                <div class="date-dropdown" onclick="document.getElementById('dateRange').focus();">
                    <i class="bi bi-calendar"></i>
                    <input type="text" name="date_range" id="dateRange" placeholder="Select Date"
                        value="<?= htmlspecialchars($date_range) ?>">
                </div>

                <button type="button" class="btn-outline-secondary" onclick="openFilterModal()">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>

                <button type="submit" class="btn-apply">Apply</button>

                <div class="ms-auto fw-bold text-dark" style="font-size: 13.5px;">
                    Period: <?= htmlspecialchars($period_display) ?>
                </div>
            </form>

            <?php if (!$is_searched): ?>
            <div class="empty-state">
                <svg class="empty-state-svg mt-3" viewBox="0 0 400 250" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="50" y="40" width="220" height="150" rx="4" fill="#F3F4F6" />
                    <rect x="50" y="40" width="220" height="15" rx="4" fill="#1F2937" />
                    <circle cx="60" cy="47.5" r="2.5" fill="#EF4444" />
                    <circle cx="70" cy="47.5" r="2.5" fill="#F59E0B" />
                    <circle cx="80" cy="47.5" r="2.5" fill="#10B981" />
                    <rect x="65" y="70" width="190" height="45" rx="2" fill="#FFFFFF" />
                    <circle cx="85" cy="92.5" r="12.5" fill="#F59E0B" />
                    <rect x="110" y="85" width="80" height="4" rx="2" fill="#E5E7EB" />
                    <rect x="110" y="95" width="50" height="4" rx="2" fill="#E5E7EB" />
                    <rect x="65" y="125" width="190" height="45" rx="2" fill="#FFFFFF" />
                    <circle cx="85" cy="147.5" r="12.5" fill="#3B82F6" />
                    <path d="M85 140V155M77.5 147.5H92.5" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" />
                    <rect x="110" y="140" width="80" height="4" rx="2" fill="#E5E7EB" />
                    <rect x="110" y="150" width="50" height="4" rx="2" fill="#E5E7EB" />
                </svg>
            </div>
            <?php else: ?>
            <?php if ($employee_details): ?>
            <div class="selected-emp-chip">
                <input class="form-check-input select-all-emp" type="checkbox" checked id="selectAllEmp">
                <label class="form-check-label text-dark" for="selectAllEmp">
                    <?= htmlspecialchars($employee_details['employee_name'] ?? '') ?> -
                    <?= htmlspecialchars($employee_details['employee_code'] ?? '') ?>
                </label>
            </div>
            <?php endif; ?>

            <div class="table-container border">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40px; padding-left: 20px;">
                                <input type="checkbox" class="form-check-input" checked id="selectAllRows">
                            </th>
                            <th>Date And Day</th>
                            <th>Day Status</th>
                            <th>Shift Name</th>
                            <th>In - Out</th>
                            <th>Hours Worked</th>
                            <th>Discrepancy Type</th>
                            <th style="width: 80px; text-align:right; padding-right:24px;">Status</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($time_entries)): ?>
                        <?php foreach ($time_entries as $index => $row): 
                                    $timestamp = strtotime($row['entry_date']);
                                    $dateFormatted = date('d M, D', $timestamp);                                
                                    
                                    $ds1 = $row['day_status_1'] ?? 'A';
                                    $ds2 = $row['day_status_2'] ?? 'A';
                                    
                                    $genBadge = function($status) {
                                        if($status == 'P') return '<span class="status-badge-sm status-p-bg">P</span>';
                                        if($status == 'A') return '<span class="status-badge-sm status-a-bg">A</span>';
                                        return '<span class="status-badge-sm status-other-bg">'.htmlspecialchars(substr($status, 0, 3)).'</span>';
                                    };
                                    $statusBadgesHtml = $genBadge($ds1) . ' ' . $genBadge($ds2);
                                    
                                    $discBadgeClass = 'badge-late-login';
                                    $discText = 'Late Login';
                                    if (!empty($row['under_time_hours']) && $row['under_time_hours'] !== '00:00:00') {
                                        $discBadgeClass = 'badge-short-hours';
                                        $discText = 'Short Hours';
                                    } elseif (empty($row['check_in_time']) && empty($row['check_out_time']) && $ds1 != 'WO') {
                                        $discBadgeClass = 'badge-missing';
                                        $discText = 'Missing Swipes';
                                    }

                                    $combined_for_edit = 'AA';
                                    if ($ds1 == 'P' && $ds2 == 'P') $combined_for_edit = 'PP';
                                    elseif ($ds1 == 'P' && $ds2 == 'A') $combined_for_edit = 'P*A';
                                    elseif ($ds1 == 'A' && $ds2 == 'P') $combined_for_edit = 'A*P';
                                    elseif ($ds1 == 'WO' && $ds2 == 'WO') $combined_for_edit = 'WO';
                                    elseif ($ds1 == 'HO' && $ds2 == 'HO') $combined_for_edit = 'HO';

                                    $rowId = "row-" . $index . "-details";
                                ?>
                        <tr>
                            <td style="padding-left: 20px;">
                                <input type="checkbox" class="form-check-input row-checkbox" checked
                                    value="<?= $row['entry_date'] ?>">
                                <input type="hidden" class="emp-code-val"
                                    value="<?= htmlspecialchars($row['employee_code']) ?>">
                            </td>
                            <td><?= htmlspecialchars($dateFormatted) ?></td>
                            <td><?= $statusBadgesHtml ?></td>
                            <td><?= htmlspecialchars($row['assigned_shift_name']) ?></td>
                            <td>
                                <?php if (empty($row['check_in_time']) && empty($row['check_out_time'])): ?>
                                -
                                <?php else: ?>
                                <?= !empty($row['check_in_time']) ? htmlspecialchars(date('h:i A', strtotime($row['check_in_time']))) : '--:--' ?>
                                -
                                <?= !empty($row['check_out_time']) ? htmlspecialchars(date('h:i A', strtotime($row['check_out_time']))) : '--:--' ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(formatTimeForDisplay($row['hours_worked'] ?? '00:00 Hrs')) ?></td>
                            <td>
                                <span
                                    class="badge-disc <?= $discBadgeClass ?>"><?= htmlspecialchars($discText) ?></span>
                            </td>
                            <td style="text-align:right; padding-right:24px;">
                                <span class="badge rounded-pill bg-light text-primary border"
                                    style="font-weight:500; font-size:12px; padding: 4px 12px;"><?= htmlspecialchars($row['record_status'] ?? 'Manual') ?></span>
                            </td>
                            <td class="text-end pe-4"><i class="bi bi-chevron-down text-muted fs-5"
                                    style="cursor:pointer;" onclick="toggleRow('<?= $rowId ?>', this)"></i></td>
                        </tr>
                        <tr id="<?= $rowId ?>" class="expandable-row">
                            <td colspan="9" class="p-0 border-0">
                                <div class="edit-form-container">
                                    <div class="grid-row mb-4">
                                        <div class="grid-col-3">
                                            <label class="form-label">Day Status</label>
                                            <select class="form-control upd-day-status">
                                                <?php foreach($status_options as $val => $label): ?>
                                                <option value="<?= htmlspecialchars($val) ?>"
                                                    <?= ($combined_for_edit === $val) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="grid-col-3">
                                            <label class="form-label">Check In Time</label>
                                            <div class="d-flex align-items-center form-control p-0"
                                                style="border:none;">
                                                <input type="datetime-local" step="1" class="upd-check-in w-100"
                                                    style="border:none; border-bottom: 1px solid #d1d5db; border-radius:0; height:36px; outline:none;"
                                                    value="<?= !empty($row['check_in_time']) ? date('Y-m-d\TH:i:s', strtotime($row['check_in_time'])) : '' ?>">
                                            </div>
                                        </div>
                                        <div class="grid-col-3">
                                            <label class="form-label">Check Out Time</label>
                                            <div class="d-flex align-items-center form-control p-0"
                                                style="border:none;">
                                                <input type="datetime-local" step="1" class="upd-check-out w-100"
                                                    style="border:none; border-bottom: 1px solid #d1d5db; border-radius:0; height:36px; outline:none;"
                                                    value="<?= !empty($row['check_out_time']) ? date('Y-m-d\TH:i:s', strtotime($row['check_out_time'])) : '' ?>">
                                            </div>
                                        </div>
                                        <div class="grid-col-3">
                                            <label class="form-label">Hours Worked</label>
                                            <input type="text" class="form-control upd-hours-worked"
                                                value="<?= htmlspecialchars(formatTimeForDisplay($row['hours_worked'] ?? '00:00 Hrs')) ?>"
                                                readonly>
                                        </div>
                                    </div>
                                    <div class="grid-row mb-4">
                                        <div class="grid-col-3"><label class="form-label">Over Time Hours</label><input
                                                type="text" class="form-control upd-over-time"
                                                value="<?= htmlspecialchars(formatTimeForDisplay($row['over_time_hours'] ?? '00:00 Hrs')) ?>"
                                                readonly></div>
                                        <div class="grid-col-3"><label class="form-label">Under Time Hours</label><input
                                                type="text" class="form-control upd-under-time"
                                                value="<?= htmlspecialchars(formatTimeForDisplay($row['under_time_hours'] ?? '00:00 Hrs')) ?>"
                                                readonly></div>
                                        <div class="grid-col-3"><label class="form-label">Normal Hours</label><input
                                                type="text" class="form-control upd-normal-hours"
                                                value="<?= htmlspecialchars(formatTimeForDisplay($row['normal_hours'] ?? '00:00 Hrs')) ?>"
                                                readonly></div>
                                        <div class="grid-col-3"><label class="form-label">Late Hours</label><input
                                                type="text" class="form-control upd-late-hours"
                                                value="<?= htmlspecialchars(formatTimeForDisplay($row['late_hours'] ?? '00:00 Hrs')) ?>"
                                                readonly></div>
                                    </div>
                                    <div class="grid-row mb-3 flex-end" style="align-items: flex-end;">
                                        <div class="grid-col-3"><label class="form-label">Early Hours</label><input
                                                type="text" class="form-control upd-early-hours"
                                                value="<?= htmlspecialchars(formatTimeForDisplay($row['early_hours'] ?? '00:00 Hrs')) ?>"
                                                readonly></div>
                                        <div class="grid-col-3"><label class="form-label">Status</label><input
                                                type="text" class="form-control upd-status-code"
                                                value="<?= htmlspecialchars($row['status_code'] ?? '') ?>"></div>
                                        <div class="grid-col-3"><label class="form-label">Remarks</label><input
                                                type="text" class="form-control upd-remarks"
                                                value="<?= htmlspecialchars($row['remarks'] ?? '') ?>"></div>
                                        <div class="grid-col-3 pb-2">
                                            <div class="form-check" style="margin-bottom: 8px;">
                                                <input class="form-check-input upd-calc-in-out" type="checkbox"
                                                    id="calcInOut_<?= $index ?>"
                                                    <?= !empty($row['calculate_per_in_out']) ? 'checked' : '' ?>>
                                                <label class="form-check-label text-dark fw-bold"
                                                    style="font-size:13px;" for="calcInOut_<?= $index ?>">Calc per
                                                    In/Out time</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-end gap-3 mt-4">
                                        <?php if (!empty($row['record_status']) && $row['record_status'] !== 'System'): ?>
                                        <button class="btn-danger-outline"
                                            onclick="deleteTimeEntry('<?= $row['employee_code'] ?>', '<?= $row['entry_date'] ?>')">Delete</button>
                                        <?php endif; ?>
                                        <button class="btn-secondary-outline"
                                            onclick="toggleRow('<?= $rowId ?>', this.closest('.expandable-row').previousElementSibling.querySelector('i'))">Cancel</button>
                                        <button class="btn-primary"
                                            onclick="saveTimeEntry(this, '<?= $row['employee_code'] ?>', '<?= $row['entry_date'] ?>')">Save</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #6b7280;">No data logs
                                matched your criteria within the specified date frame.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div style="font-size: 13px; color: #6b7280;">
                    Showing 1 to <?= count($time_entries) ?> of <?= count($time_entries) ?> entries
                    <span class="ms-3">
                        Show
                        <select class="form-select d-inline-block form-select-sm"
                            style="width: auto; height: 30px; padding: 2px 24px 2px 8px;">
                            <option>50</option>
                            <option>100</option>
                        </select>
                        entries
                    </span>
                </div>
                <div class="pagination-btn-group">
                    <button disabled>&laquo;</button>
                    <button disabled>&lsaquo;</button>
                    <button class="active">1</button>
                    <button disabled>&rsaquo;</button>
                    <button disabled>&raquo;</button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-5 mb-2 p-3 bg-light rounded border">
                <p class="text-muted mb-0" style="font-size: 13px; max-width: 75%;">
                    Note: Upon regularization, the selected employee's attendance status will be changed to 'Present'.
                    To exclude any entries, simply uncheck them and take no further action.
                </p>
                <button class="btn-primary px-4 py-2" id="btnRegularize">Regularize (<span
                        id="regCount"><?= count($time_entries) ?></span>)</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal HTML -->
<div id="filterModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Advance Employee Search</h2>
            <button type="button" class="modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="search-line-wrapper" style="margin-bottom: 25px;">
                <svg viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="modalSearchInput" placeholder="Search by name or #code"
                    style="border-radius: 4px; border: 1px solid #D1D5DB; padding-left: 35px;">
            </div>
            <div class="modal-filter-grid">
                <div class="form-group"><label>Organization</label><select id="filterOrg" class="line-input">
                        <option value="">Select Organization</option><?php foreach($organizations as $org): ?><option
                            value="<?= $org['id'] ?>"><?= htmlspecialchars($org['client_name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Locations</label><select id="filterLoc" class="line-input">
                        <option value="">Select Location</option><?php foreach($locations as $loc): ?><option
                            value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Department</label><select id="filterDept" class="line-input">
                        <option value="">Select Department</option><?php foreach($departments as $dept): ?><option
                            value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['dept_name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Designation</label><select id="filterDesig" class="line-input">
                        <option value="">Select Designation</option><?php foreach($designations as $desig): ?><option
                            value="<?= $desig['id'] ?>"><?= htmlspecialchars($desig['designation_name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Status</label><select id="filterStatus" class="line-input">
                        <option value="">Select Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select></div>
                <div class="form-group"><label>Group</label><select id="filterGroup" class="line-input">
                        <option value="">Select Group</option><?php foreach($groups as $grp): ?><option
                            value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Sub Group</label><select id="filterSubGroup" class="line-input">
                        <option value="">Select Sub Group</option><?php foreach($sub_groups as $sgrp): ?><option
                            value="<?= $sgrp['id'] ?>"><?= htmlspecialchars($sgrp['sub_group_name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
            <div class="modal-search-row">
                <span style="font-size: 13px; color: #4B5563;">Records per page : <select id="modalLimit">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select></span>
                <button type="button" class="btn-primary" onclick="performModalSearch()">Search</button>
            </div>
            <hr style="margin: 20px 0; border: none; border-top: 1px solid #E5E7EB;">
            <div class="modal-results-layout">
                <div class="modal-emp-list-sec">
                    <div class="modal-emp-header">
                        <!-- Fix: Removed the Select All as the main logic works gracefully with single selections -->
                        <label class="checkbox-label" style="font-weight: 500;">Employees Found - <span
                                id="empFoundCount">0</span></label>
                    </div>
                    <div class="modal-emp-grid" id="modalEmpGrid"><span
                            style="padding: 15px; font-size: 13px; color: #9CA3AF; display:block;">Click search to find
                            employees.</span></div>
                </div>
                <div class="modal-recent-sec">
                    <div class="recent-tabs">
                        <span class="recent-tab active" id="tabRecentSearch" onclick="switchSidebarTab('recent')">Recent
                            Search</span>
                        <span class="recent-tab" id="tabSavedSearch" onclick="switchSidebarTab('saved')">Saved
                            Search</span>
                    </div>
                    <ul class="recent-list" id="recentSearchList" style="padding:15px; font-size:13px; color:#6b7280;">
                        <li>No recent searches</li>
                    </ul>
                    <ul class="recent-list" id="savedSearchList"
                        style="display:none; padding:15px; font-size:13px; color:#6b7280;">
                        <li>No saved searches</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-outline" onclick="clearModalSelections()">Clear All</button>
            <button type="button" class="btn-outline" onclick="saveCurrentSearch()">Save Search</button>
            <button type="button" class="btn-primary" onclick="applyModalFilters()">Apply</button>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<!-- JavaScript Logic -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ---------------------------------------------------------
// Main UI Handlers
// ---------------------------------------------------------
function toggleDropdown() {
    document.querySelector('.custom-select-options').classList.toggle('open');
}

document.querySelectorAll('.custom-select-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.custom-select-option').forEach(opt => opt.classList.remove(
            'selected'));
        this.classList.add('selected');
        document.getElementById('selectedDiscText').innerText = this.innerText;
        document.getElementById('discTypeValue').value = this.dataset.value;
        document.querySelector('.custom-select-options').classList.remove('open');
    });
});

document.addEventListener('click', function(e) {
    const select = document.getElementById('discrepancySelect');
    if (select && !select.contains(e.target)) document.querySelector('.custom-select-options').classList.remove(
        'open');

    const searchInput = document.getElementById('employeeSearchInput');
    const dropdown = document.getElementById('autocompleteDropdown');
    if (searchInput && dropdown && !searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});

// Date Range Config
if (document.getElementById("dateRange")) {
    flatpickr("#dateRange", {
        mode: "range",
        dateFormat: "d M Y",
        showMonths: 2,
        animate: true
    });
}

// Toggle Table Row
function toggleRow(rowId, iconElement) {
    const row = document.getElementById(rowId);
    if (row.classList.contains('show')) {
        row.classList.remove('show');
        if (iconElement && iconElement.classList) iconElement.classList.replace('bi-chevron-up', 'bi-chevron-down');
    } else {
        document.querySelectorAll('.expandable-row.show').forEach(openRow => {
            openRow.classList.remove('show');
            let prevIcon = openRow.previousElementSibling.querySelector('.bi-chevron-up');
            if (prevIcon) prevIcon.classList.replace('bi-chevron-up', 'bi-chevron-down');
        });
        row.classList.add('show');
        if (iconElement && iconElement.classList) iconElement.classList.replace('bi-chevron-down', 'bi-chevron-up');
    }
}

// Autocomplete Logic
const searchInput = document.getElementById('employeeSearchInput');
const dropdown = document.getElementById('autocompleteDropdown');
const filterForm = document.getElementById('filterForm');

if (searchInput && dropdown) {
    let debounceTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        if (query.length < 2) {
            dropdown.style.display = 'none';
            return;
        }
        debounceTimer = setTimeout(() => {
            fetch(`?ajax_search=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(emp => {
                            const item = document.createElement('div');
                            item.className = 'autocomplete-item';
                            item.innerHTML =
                                `<span class="autocomplete-name">${emp.employee_name}</span><span class="autocomplete-code">#${emp.employee_code}</span>`;
                            item.addEventListener('click', () => {
                                searchInput.value = emp.employee_code;
                                dropdown.style.display = 'none';
                                filterForm.submit();
                            });
                            dropdown.appendChild(item);
                        });
                    } else {
                        dropdown.innerHTML =
                            '<div class="autocomplete-item text-muted">No employees found</div>';
                    }
                    dropdown.style.display = 'block';
                })
                .catch(error => console.error('Error:', error));
        }, 300);
    });
}

// Checkbox and Selection Counters
document.addEventListener('DOMContentLoaded', function() {
    const selectAllRows = document.getElementById('selectAllRows');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const regCount = document.getElementById('regCount');

    function updateCount() {
        let count = 0;
        rowCheckboxes.forEach(cb => {
            if (cb.checked) count++;
        });
        if (regCount) regCount.innerText = count;
    }

    if (selectAllRows) selectAllRows.addEventListener('change', function() {
        rowCheckboxes.forEach(cb => cb.checked = selectAllRows.checked);
        updateCount();
    });
    rowCheckboxes.forEach(cb => cb.addEventListener('change', function() {
        if (!this.checked && selectAllRows) selectAllRows.checked = false;
        updateCount();
    }));

    const selectAllEmp = document.getElementById('selectAllEmp');
    if (selectAllEmp && selectAllRows) selectAllEmp.addEventListener('change', function() {
        selectAllRows.checked = this.checked;
        rowCheckboxes.forEach(cb => cb.checked = this.checked);
        updateCount();
    });
});

// ---------------------------------------------------------
// DB Saving Actions (Save, Delete, Regularize)
// ---------------------------------------------------------
function saveTimeEntry(button, empCode, entryDate) {
    const container = button.closest('.edit-form-container');
    const formData = new URLSearchParams({
        action: 'update_entry',
        emp_code: empCode,
        entry_date: entryDate,
        day_status: container.querySelector('.upd-day-status').value,
        check_in: container.querySelector('.upd-check-in').value,
        check_out: container.querySelector('.upd-check-out').value,
        hours_worked: container.querySelector('.upd-hours-worked').value,
        over_time: container.querySelector('.upd-over-time').value,
        under_time: container.querySelector('.upd-under-time').value,
        normal_hours: container.querySelector('.upd-normal-hours').value,
        late_hours: container.querySelector('.upd-late-hours').value,
        early_hours: container.querySelector('.upd-early-hours').value,
        status_code: container.querySelector('.upd-status-code').value,
        remarks: container.querySelector('.upd-remarks').value,
        calc_in_out: container.querySelector('.upd-calc-in-out').checked
    });

    const originalText = button.innerHTML;
    button.innerHTML = 'Saving...';
    button.disabled = true;

    fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData.toString()
        })
        .then(r => r.json()).then(data => {
            if (data.success) {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'success',
                    title: 'Saved successfully',
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(() => window.location.reload(), 1500);
            } else {
                Swal.fire('Error', data.error || 'Failed to update', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        });
}

function deleteTimeEntry(empCode, entryDate) {
    Swal.fire({
        title: 'Are you sure?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new URLSearchParams({
                action: 'delete_entry',
                emp_code: empCode,
                entry_date: entryDate
            });
            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: formData.toString()
                })
                .then(r => r.json()).then(data => {
                    if (data.success) window.location.reload();
                });
        }
    });
}

document.getElementById('btnRegularize')?.addEventListener('click', function() {
    const selectedEntries = [];
    document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
        selectedEntries.push({
            emp_code: cb.closest('tr').querySelector('.emp-code-val').value,
            entry_date: cb.value
        });
    });
    if (selectedEntries.length === 0) {
        Swal.fire('Warning', 'Please select at least one entry to regularize.', 'warning');
        return;
    }

    const formData = new URLSearchParams({
        action: 'regularize',
        entries: JSON.stringify(selectedEntries)
    });
    const btn = this;
    const ogText = btn.innerHTML;
    btn.innerHTML = 'Processing...';
    btn.disabled = true;

    fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData.toString()
        })
        .then(r => r.json()).then(data => {
            if (data.success) {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'success',
                    title: 'Regularized Successfully',
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(() => window.location.reload(), 1500);
            } else {
                Swal.fire('Error', data.error || 'Failed to regularize', 'error');
                btn.innerHTML = ogText;
                btn.disabled = false;
            }
        });
});

// ---------------------------------------------------------
// Advanced Search Modal Logic
// ---------------------------------------------------------

// Trigger performModalSearch when 'Enter' key is pressed inside the search input
document.getElementById('modalSearchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        performModalSearch();
    }
});

function openFilterModal() {
    document.getElementById('filterModal').style.display = 'flex';
}

function closeFilterModal() {
    document.getElementById('filterModal').style.display = 'none';
}

function performModalSearch() {
    const grid = document.getElementById('modalEmpGrid');
    const countSpan = document.getElementById('empFoundCount');
    const limitSelect = document.getElementById('modalLimit');

    grid.innerHTML =
        '<div style="padding: 15px; color: #6B7280; text-align: center; font-size: 13px;">Searching...</div>';

    const params = new URLSearchParams({
        ajax_advanced_search: '1',
        name_code: document.getElementById('modalSearchInput').value,
        org: document.getElementById('filterOrg').value,
        loc: document.getElementById('filterLoc').value,
        dept: document.getElementById('filterDept').value,
        desig: document.getElementById('filterDesig').value,
        status: document.getElementById('filterStatus').value,
        group: document.getElementById('filterGroup').value,
        sub_group: document.getElementById('filterSubGroup').value,
        limit: limitSelect ? limitSelect.value : 50
    });

    // Fix: Using window.location.pathname safely targets current page dropping older query strings
    fetch(window.location.pathname + '?' + params.toString()).then(res => res.json()).then(data => {
        countSpan.innerText = data.length;
        if (data.length === 0) {
            grid.innerHTML =
                '<div style="padding: 15px; color: #6B7280; text-align: center; font-size: 13px;">No employees found matching criteria.</div>';
            return;
        }
        let html = '';
        data.forEach(emp => {
            html += `
                <div style="padding: 10px 15px; border-bottom: 1px solid #F3F4F6; display: flex; align-items: center; gap: 12px;">
                    <input type="radio" name="modal_emp_select" class="form-check-input modal-emp-cb" value="${emp.employee_code}" data-name="${emp.employee_name}" style="cursor: pointer; margin: 0;">
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 13.5px; font-weight: 500; color: #111827;">${emp.employee_name}</span>
                        <span style="font-size: 11px; color: #6B7280;">#${emp.employee_code}</span>
                    </div>
                </div>`;
        });
        grid.innerHTML = html;
    }).catch(err => {
        grid.innerHTML =
            '<div style="padding: 15px; color: #EF4444; text-align: center; font-size: 13px;">Failed to fetch results.</div>';
    });
}

function clearModalSelections() {
    document.getElementById('modalSearchInput').value = '';
    document.querySelectorAll('.modal-filter-grid select').forEach(s => s.value = '');
    document.getElementById('modalEmpGrid').innerHTML =
        '<span style="padding: 15px; font-size: 13px; color: #9CA3AF; display: block;">Click search to find employees.</span>';
    document.getElementById('empFoundCount').innerText = '0';
}

function applyModalFilters() {
    const selected = document.querySelector('.modal-emp-cb:checked');
    if (!selected) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: 'Select an employee first',
            showConfirmButton: false,
            timer: 2000
        });
        return;
    }

    const firstCode = selected.value;

    const mainSearchInput = document.getElementById('employeeSearchInput');
    const filterForm = document.getElementById('filterForm');

    if (filterForm) {
        if (mainSearchInput) {
            // FIX: You MUST enable the input otherwise the browser drops the employee value during form submission!
            mainSearchInput.disabled = false;
            mainSearchInput.value = firstCode;
        }
        closeFilterModal();
        filterForm.submit();
    } else {
        window.location.href = '?employee=' + encodeURIComponent(firstCode);
    }
}

function switchSidebarTab(tabName) {
    const recentTab = document.getElementById('tabRecentSearch');
    const savedTab = document.getElementById('tabSavedSearch');
    const recentList = document.getElementById('recentSearchList');
    const savedList = document.getElementById('savedSearchList');
    if (tabName === 'recent') {
        recentTab.classList.add('active');
        savedTab.classList.remove('active');
        recentList.style.display = 'block';
        savedList.style.display = 'none';
    } else {
        savedTab.classList.add('active');
        recentTab.classList.remove('active');
        savedList.style.display = 'block';
        recentList.style.display = 'none';
    }
}

function saveCurrentSearch() {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Search parameters saved',
        showConfirmButton: false,
        timer: 2000
    });
}
</script>
<script src="includes/assets/scripts.js"></script>