<?php
session_start();
if (!isset($_SESSION['login'])) {
    if (isset($_POST['action']) || isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login');
    exit();
}

// Helper: Split full status code into two halves (day_status_1 & day_status_2)
function splitStatusToHalves($code) {
    switch (trim($code)) {
        case 'PP':   return ['P', 'P'];
        case 'AA':   return ['A', 'A'];
        case 'P*A':  return ['P', 'A'];
        case 'A*P':  return ['A', 'P'];
        case 'HO':   return ['HO', 'HO'];
        case 'WO':   return ['WO', 'WO'];
        case 'WW':   return ['WW', 'WW'];
        case 'HW':   return ['HW', 'HW'];
        case 'WW*':  return ['WW', 'WO'];
        case '*WW':  return ['WO', 'WW'];
        case 'HW*':  return ['HW', 'HO'];
        case '*HW':  return ['HO', 'HW'];
        case '*LOP': return ['P', 'LOP'];
        case 'LOP*': return ['LOP', 'P'];
        default:     return [$code ?: '-', $code ?: '-'];
    }
}

function getCodeFromHalves($s1, $s2) {
    $s1 = trim($s1);
    $s2 = trim($s2);
    if (in_array($s1, ['PP', 'AA', 'P*A', 'A*P', 'HO', 'WO', 'WW', 'HW', 'WW*', '*WW', 'HW*', '*HW', '*LOP', 'LOP*'])) {
        return $s1;
    }
    if ($s1 === 'P' && $s2 === 'P') return 'PP';
    if ($s1 === 'A' && $s2 === 'A') return 'AA';
    if ($s1 === 'P' && $s2 === 'A') return 'P*A';
    if ($s1 === 'A' && $s2 === 'P') return 'A*P';
    if ($s1 === 'HO' && $s2 === 'HO') return 'HO';
    if ($s1 === 'WO' && $s2 === 'WO') return 'WO';
    if ($s1 === 'WW' && $s2 === 'WW') return 'WW';
    if ($s1 === 'HW' && $s2 === 'HW') return 'HW';
    if ($s1 === 'WW' && $s2 === 'WO') return 'WW*';
    if ($s1 === 'WO' && $s2 === 'WW') return '*WW';
    if ($s1 === 'HW' && $s2 === 'HO') return 'HW*';
    if ($s1 === 'HO' && $s2 === 'HW') return '*HW';
    if ($s1 === 'P' && $s2 === 'LOP') return '*LOP';
    if ($s1 === 'LOP' && $s2 === 'P') return 'LOP*';
    return 'PP';
}

function getPillClass($status) {
    $status = strtoupper(trim($status));
    if ($status === 'P') return 'pill-p';
    if ($status === 'A' || $status === 'LOP') return 'pill-a';
    if ($status === 'WO' || $status === 'HO') return 'pill-wo';
    return 'pill-other';
}

if (isset($_GET['ajax_search'])) {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    
    header('Content-Type: application/json');
    $results = [];
    
    if (isset($conn)) {
        $search = mysqli_real_escape_string($conn, trim($_GET['ajax_search'] ?? ''));
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

// Handle AJAX Request for Deleting Time Entries
if (isset($_POST['action']) && $_POST['action'] === 'delete_entry') {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    header('Content-Type: application/json');

    if (isset($conn)) {
        $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code'] ?? '');
        $entry_date = mysqli_real_escape_string($conn, $_POST['entry_date'] ?? '');
        
        if (empty($emp_code) || empty($entry_date)) {
            echo json_encode(['success' => false, 'error' => 'Missing employee code or entry date.']);
            exit;
        }

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

// Handle AJAX Request for Updating or Inserting Time Entries
if (isset($_POST['action']) && $_POST['action'] === 'update_entry') {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    header('Content-Type: application/json');

    if (isset($conn)) {
        $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code'] ?? '');
        $entry_date = mysqli_real_escape_string($conn, $_POST['entry_date'] ?? '');
        
        if (empty($emp_code) || empty($entry_date)) {
            echo json_encode(['success' => false, 'error' => 'Missing employee code or entry date.']);
            exit;
        }

        $day_status = mysqli_real_escape_string($conn, $_POST['day_status'] ?? '');
        $halves = splitStatusToHalves($day_status);
        $day_status_1 = mysqli_real_escape_string($conn, $halves[0]);
        $day_status_2 = mysqli_real_escape_string($conn, $halves[1]);

        $check_in = mysqli_real_escape_string($conn, $_POST['check_in'] ?? '');
        $check_out = mysqli_real_escape_string($conn, $_POST['check_out'] ?? '');
        $hours_worked = mysqli_real_escape_string($conn, $_POST['hours_worked'] ?? '');
        $over_time = mysqli_real_escape_string($conn, $_POST['over_time'] ?? '');
        $under_time = mysqli_real_escape_string($conn, $_POST['under_time'] ?? '');
        $normal_hours = mysqli_real_escape_string($conn, $_POST['normal_hours'] ?? '');
        $late_hours = mysqli_real_escape_string($conn, $_POST['late_hours'] ?? '');
        $early_hours = mysqli_real_escape_string($conn, $_POST['early_hours'] ?? '');
        $status_code = mysqli_real_escape_string($conn, $_POST['status_code'] ?? '');
        $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
        $calc_in_out = (isset($_POST['calc_in_out']) && $_POST['calc_in_out'] === 'true') ? 1 : 0;

        $check_in_val = !empty($check_in) ? "'$check_in'" : "NULL";
        $check_out_val = !empty($check_out) ? "'$check_out'" : "NULL";

        $check_sql = "SELECT 1 FROM time_entries WHERE employee_code = '$emp_code' AND entry_date = '$entry_date'";
        $check_res = mysqli_query($conn, $check_sql);

        if (mysqli_num_rows($check_res) > 0) {
            $sql = "UPDATE time_entries SET 
                day_status_1 = '$day_status_1',
                day_status_2 = '$day_status_2',
                check_in_time = $check_in_val,
                check_out_time = $check_out_val,
                hours_worked = '$hours_worked',
                over_time_hours = '$over_time',
                under_time_hours = '$under_time',
                normal_hours = '$normal_hours',
                late_hours = '$late_hours',
                early_hours = '$early_hours',
                status_code = '$status_code',
                remarks = '$remarks',
                calculate_per_in_out = '$calc_in_out',
                record_status = 'Manual'
                WHERE employee_code = '$emp_code' AND entry_date = '$entry_date'";
        } else {
            $sql = "INSERT INTO time_entries (
                employee_code, entry_date, day_status_1, day_status_2, check_in_time, check_out_time, 
                hours_worked, over_time_hours, under_time_hours, normal_hours, 
                late_hours, early_hours, status_code, remarks, calculate_per_in_out, record_status
            ) VALUES (
                '$emp_code', '$entry_date', '$day_status_1', '$day_status_2', $check_in_val, $check_out_val, 
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

require_once 'includes/config.php';
require_once 'includes/db_client.php';

$page_title = 'Attendance - Time Entries';

$search_query = isset($_GET['employee']) ? trim($_GET['employee']) : '';
$date_range = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';
$is_searched = !empty($search_query);

$time_entries = [];
$employee_details = null;
$shift_assignments = [];

// Status Mapping Dictionary
$status_options = [
    'PP' => 'Present (PP)',
    'AA' => 'Absent (AA)',
    'P*A' => 'First Half Present (P*A)',
    'A*P' => 'Second Half Present (A*P)',
    'HO' => 'Holiday (HO)',
    'WO' => 'Week Off (WO)',
    'WW' => 'Worked On Week Off (WW)',
    'HW' => 'Worked On Holiday (HW)',
    'WW*' => 'Worked On Week Off First Half (WW*)',
    '*WW' => 'Worked On Week Off Second Half (*WW)',
    'HW*' => 'Worked On Holiday First Half (HW*)',
    '*HW' => 'Worked On Holiday Second Half (*HW)',
    '*LOP' => 'Loss Of Pay in Second Half (*LOP)',
    'LOP*' => 'Loss Of Pay in First Half (LOP*)'
];

function formatTimeForDisplay($timeString) {
    if (empty($timeString)) return '';
    if (strpos($timeString, 'Hrs') !== false) return $timeString;
    if (preg_match('/^(\d{2,}):(\d{2})/', $timeString, $matches)) {
        return $matches[1] . ':' . $matches[2] . ' Hrs';
    }
    return $timeString;
}

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

if ($is_searched && isset($conn)) {
    $safe_search = mysqli_real_escape_string($conn, $search_query);
    
    $emp_sql = "SELECT * FROM employees 
                WHERE employee_name LIKE '%$safe_search%' 
                OR employee_code = '$safe_search' 
                LIMIT 1";
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
            
            if (isset($db_entries[$date_str])) {
                $entry = $db_entries[$date_str];
                // FETCH FROM time_entries: Prefer database shift_name, fallback to assigned
                $entry['assigned_shift_name'] = !empty($entry['shift_name']) ? $entry['shift_name'] : $assigned_shift_name;
                $time_entries[] = $entry;
            } else {
                $is_sunday = ($day_num == 7);
                $default_status = $is_sunday ? 'WO' : 'AA'; 
                $default_halves = splitStatusToHalves($default_status);
                
                $time_entries[] = [
                    'employee_code' => $emp_code,
                    'entry_date' => $date_str,
                    'day_status_1' => $default_halves[0],
                    'day_status_2' => $default_halves[1],
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
        }
    } catch (Exception $e) { }
}
ob_start();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="includes/assets/style.css">

<style>    
    .flex-between { display: flex; justify-content: space-between; align-items: center; }
    .flex-center { display: flex; justify-content: center; align-items: center; }
    .flex-end { display: flex; justify-content: flex-end; align-items: center; }
    .d-flex { display: flex; align-items: center; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 16px; }
    .mb-1 { margin-bottom: 4px; }
    .mb-3 { margin-bottom: 16px; }
    .mb-4 { margin-bottom: 24px; }
    .mt-2 { margin-top: 8px; }
    .mt-3 { margin-top: 16px; }
    .mt-4 { margin-top: 24px; }
    .mt-5 { margin-top: 32px; }
    .p-0 { padding: 0 !important; }
    .ps-4 { padding-left: 24px; }
    .pe-4 { padding-right: 24px; }
    .text-dark { color: #111827; }
    .text-muted { color: #6b7280; }
    .text-end { text-align: right; }
    .fw-bold { font-weight: 600; }
    .w-100 { width: 100%; }
    .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    .rounded-3 { border-radius: 8px; }
    .bg-white { background-color: #ffffff; }

    .grid-row { display: flex; flex-wrap: wrap; margin-left: -12px; margin-right: -12px; }
    .grid-col-3 { width: 25%; padding: 0 12px; flex: 0 0 auto; }
    .grid-col-6 { width: 50%; padding: 0 12px; flex: 0 0 auto; }

    .card { background: #fff; border-radius: 8px; min-height: 450px; }

    .attendance-tabs { border-bottom: 1px solid #dee2e6; }
    .attendance-tabs a { color: #6c757d; text-decoration: none; padding: 3px 5px; gap: 12px; display: inline-block; font-size: 14px; transition: color 0.2s; }
    .attendance-tabs .separator { color: #D1D5DB; font-size: 14px; }
    .attendance-tabs a:hover { color: #495057; }
    .attendance-tabs a.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; font-weight: 500; }
    
    .filter-bar { display: flex; gap: 15px; align-items: center; margin-bottom: 25px; }
    .search-wrapper { position: relative; width: 280px; }
    .search-wrapper .form-control { padding-left: 35px; }
    .search-wrapper .bi-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px; }
    
    .search-chip { position: absolute; top: 4px; left: 4px; bottom: 4px; background-color: #f3f4f6; border-radius: 3px; display: flex; align-items: center; padding: 0 8px; font-size: 13px; color: #111827; border: 1px solid #e5e7eb; z-index: 5; }
    .search-chip a { color: #6b7280; margin-left: 6px; text-decoration: none; font-size: 16px; display: flex; align-items: center; }
    .search-chip a:hover { color: #ef4444; }

    .autocomplete-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #d1d5db; border-top: none; border-radius: 0 0 4px 4px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); z-index: 1000; max-height: 250px; overflow-y: auto; display: none; margin-top: 2px; }
    .autocomplete-item { padding: 10px 14px; cursor: pointer; font-size: 13.5px; border-bottom: 1px solid #f3f4f6; display: flex; flex-direction: column; transition: background-color 0.1s; }
    .autocomplete-item:last-child { border-bottom: none; }
    .autocomplete-item:hover { background-color: #eff6ff; }
    .autocomplete-name { font-weight: 600; color: #111827; }
    .autocomplete-code { font-size: 11px; color: #6b7280; margin-top: 2px; }

    .employee-profile-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 24px; }
    .emp-avatar { width: 56px; height: 56px; background: #0d6efd; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 600; text-transform: uppercase; overflow: hidden; }
    .emp-details-grid { flex: 1; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
    .emp-detail-item { display: flex; flex-direction: column; gap: 4px; }
    .emp-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .emp-val { font-size: 14px; font-weight: 500; color: #0f172a; }

    .form-control, .form-select { width: 100%; border-radius: 4px; font-size: 13.5px; border: 1px solid #d1d5db; height: 36px; padding: 6px 12px; background-color: #fff; outline: none; }
    .form-control:focus, .form-select:focus { border-color: #0d6efd; }
    .form-control[readonly] { background-color: #f9fafb; cursor: not-allowed; color: #6b7280; font-weight: 500; }
    
    .date-dropdown { display: flex; align-items: center; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; height: 36px; width: 260px; padding: 0 12px; gap: 8px;}
    .date-dropdown .bi-calendar { color: #6b7280; border-right: 1px solid #d1d5db; padding-right: 8px; }
    .date-dropdown input { border: none; background: transparent; flex: 1; outline: none; font-size: 13.5px; color: #4b5563; }
    .btn-apply { background-color: #0d6efd; color: #fff; height: 36px; padding: 0 20px; font-size: 13.5px; }
    .btn-apply:hover, .btn-primary:hover { background-color: #0b5ed7; color: #fff; }
    .btn-primary { background: #0d6efd; color: #fff; }
    .btn-outline-primary { border-color: #0d6efd; color: #0d6efd; background: transparent; }
    .btn-outline-primary:hover { background: #eff6ff; color: #0b5ed7; }
    .btn-outline-danger { border-color: #ef4444; color: #ef4444; background: transparent; }
    .btn-outline-danger:hover { background: #fef2f2; color: #dc2626; border-color: #dc2626; }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state h5 { font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 20px; }
    .empty-state-svg { max-width: 300px; height: auto; opacity: 0.9; }

    /* Updated Dual Status Pill Styling */
    .status-pill-group {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 28px;
        padding: 0 8px;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 600;
        letter-spacing: 0.3px;
    }
    .status-pill.pill-p {
        background-color: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }
    .status-pill.pill-a {
        background-color: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    .status-pill.pill-wo {
        background-color: #f3f4f6;
        color: #4b5563;
        border: 1px solid #e5e7eb;
    }
    .status-pill.pill-other {
        background-color: #fffbeb;
        color: #d97706;
        border: 1px solid #fde68a;
    }

    .system-badge { background-color: #e8f0fe; color: #1967d2; border-radius: 20px; padding: 3px 25px; border: 1px solid #d2e3fc; font-size: 12.5px; font-weight: 500; }
    
    .table-container { border-radius: 6px; overflow: hidden; width: 100%; border: 1px solid #e5e7eb; }
    .table { width: 100%; border-collapse: collapse; text-align: left; }
    .table th { background-color: #f8f9fa; font-weight: 600; font-size: 12px; color: #4b5563; border-bottom: 1px solid #e5e7eb; padding: 12px 16px; }
    .table td { vertical-align: middle; font-size: 13.5px; color: #111827; padding: 14px 16px; border-bottom: 1px solid #f3f4f6; }
    .table tbody tr:hover { background-color: #f9fafb; }
    
    .current-date-row { background-color: #eff6ff !important; border-left: 3px solid #0d6efd; }
    
    .expandable-row { background-color: #fff; display: none; border-bottom: 1px solid #e5e7eb;}
    .expandable-row.show { display: table-row; }
    .edit-form-container { padding: 24px 30px; background-color: #fff; border-top: 1px solid #e5e7eb; }
    .edit-form-container .form-label { display: block; font-size: 12px; color: #4b5563; font-weight: 500; margin-bottom: 4px; }
    .edit-form-container .form-control, .edit-form-container .form-select { border: none; border-bottom: 1px solid #d1d5db; border-radius: 0; padding: 4px 0 8px 0; background: transparent; box-shadow: none; height: auto; }
    .edit-form-container .form-control:focus, .edit-form-container .form-select:focus { border-bottom-color: #0d6efd; }
    .input-icon-wrapper { position: relative; }
    .input-icon-wrapper .bi-clock { position: absolute; right: 0; top: 50%; transform: translateY(-50%); color: #0d6efd; pointer-events: none; }

    .form-check { display: flex; align-items: center; gap: 8px; }
    .form-check-input { width: 16px; height: 16px; cursor: pointer; margin: 0; }
    .form-check-label { font-size: 13.5px; color: #111827; cursor: pointer; }
    
    .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-size: 13px; color: #6b7280; }
    .pagination-btn-group { display: flex; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); border-radius: 4px; overflow: hidden; }
    .pagination-btn-group button { border: 1px solid #e5e7eb; background: #fff; padding: 6px 12px; cursor: pointer; color: #6b7280; outline: none; margin-left: -1px; }
    .pagination-btn-group button.active { background: #0d6efd; color: #fff; border-color: #0d6efd; z-index: 2; }
    .pagination-btn-group button:disabled { background: #f9fafb; color: #d1d5db; cursor: not-allowed; }

    .toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 12px; }
    .custom-toast { min-width: 280px; background: #ffffff; border-radius: 6px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); padding: 16px; display: flex; align-items: flex-start; gap: 12px; transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55); border-left: 4px solid #0d6efd; }
    .custom-toast.show { transform: translateX(0); }
    .custom-toast.success { border-left-color: #10B981; }
    .custom-toast.error { border-left-color: #EF4444; }
    .toast-icon { font-size: 18px; margin-top: -2px; }
    .custom-toast.success .toast-icon { color: #10B981; }
    .custom-toast.error .toast-icon { color: #EF4444; }
    .toast-content { flex: 1; }
    .toast-title { font-weight: 600; font-size: 14px; color: #111827; margin-bottom: 4px; }
    .toast-message { font-size: 13px; color: #6b7280; margin: 0; }
    .toast-close { cursor: pointer; color: #9ca3af; font-size: 18px; transition: color 0.2s; margin-top: -2px; }
    .toast-close:hover { color: #4b5563; }

    @media (max-width: 768px) {
        .flatpickr-calendar.multiMonth { width: 100% !important; }
        .flatpickr-calendar .flatpickr-months { flex-wrap: wrap; }
        .grid-col-3, .grid-col-6 { width: 100%; margin-bottom: 16px; }
        .employee-profile-card { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="container">    
    <div class="flex-between mb-1">
        <h4 class="text-dark fw-bold m-0" style="font-size: 1.25rem;">Attendance</h4>
        <div class="attendance-tabs m-0 border-0">
            <a href="attendance" class="active">Time Entries</a>
            <span class="separator">|</span>
            <a href="AttCalendarView">Calendar View</a>
            <span class="separator">|</span>
            <a href="ManualAttendance">Manual Attendance</a>
            <span class="separator">|</span>
            <a href="AttDiscrepancies">Discrepancies</a>
            <span class="separator">|</span>
            <a href="AttProcessTimeCard">Process Time Card</a>
            <span class="separator">|</span>
            <a href="AttApproveOvertime">Approve Overtime</a>
        </div>
    </div>

    <div class="card shadow-sm mt-2">
        <div class="card-body p-4">
            <h6 class="text-dark fw-bold mb-4" style="font-size: 13.5px; letter-spacing: 0.5px; margin-top: 0; text-transform: uppercase;">TIME ENTRIES</h6>            
            <form method="GET" action="" class="filter-bar" id="filterForm">
                <div class="search-wrapper">
                    <?php if ($is_searched): ?>
                        <div class="search-chip">
                            <?= htmlspecialchars($search_query) ?> 
                            <a href="?"><i class="bi bi-x"></i></a>
                        </div>
                        <input type="hidden" name="employee" value="<?= htmlspecialchars($search_query) ?>">
                        <input type="text" class="form-control" disabled style="background-color: #fff;">
                    <?php else: ?>
                        <i class="bi bi-search"></i>
                        <input type="text" id="employeeSearchInput" class="form-control" placeholder="Search by name or #code" autocomplete="off">
                        <input type="hidden" id="employeeSearchHidden" name="employee" value="">
                        <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
                    <?php endif; ?>
                </div>                
                <div class="date-dropdown">
                    <i class="bi bi-calendar"></i>
                    <input type="text" name="date_range" id="dateRange" placeholder="Select Date Range" value="<?= htmlspecialchars($date_range) ?>">
                </div>                
                <button type="submit" class="btn btn-apply">Apply</button>
            </form>

            <?php if (!$is_searched): ?>
                <div class="empty-state">
                    <h5>Search employees to edit their time entries</h5>
                    <svg class="empty-state-svg mt-3" viewBox="0 0 400 250" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="50" y="40" width="220" height="150" rx="4" fill="#F3F4F6"/>
                        <rect x="50" y="40" width="220" height="15" rx="4" fill="#1F2937"/>
                        <circle cx="60" cy="47.5" r="2.5" fill="#EF4444"/>
                        <circle cx="70" cy="47.5" r="2.5" fill="#F59E0B"/>
                        <circle cx="80" cy="47.5" r="2.5" fill="#10B981"/>
                        <rect x="65" y="70" width="190" height="45" rx="2" fill="#FFFFFF"/>
                        <circle cx="85" cy="92.5" r="12.5" fill="#F59E0B"/>
                        <rect x="110" y="85" width="80" height="4" rx="2" fill="#E5E7EB"/>
                        <rect x="110" y="95" width="50" height="4" rx="2" fill="#E5E7EB"/>
                        <rect x="65" y="125" width="190" height="45" rx="2" fill="#FFFFFF"/>
                        <circle cx="85" cy="147.5" r="12.5" fill="#3B82F6"/>
                        <path d="M85 140V155M77.5 147.5H92.5" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round"/>
                        <rect x="110" y="140" width="80" height="4" rx="2" fill="#E5E7EB"/>
                        <rect x="110" y="150" width="50" height="4" rx="2" fill="#E5E7EB"/>
                        <g transform="translate(230, 110)">
                            <circle cx="45" cy="25" r="15" fill="#10B981" fill-opacity="0.2"/>
                            <circle cx="45" cy="25" r="6" fill="#10B981"/>
                            <path d="M35 45 C35 38 55 38 55 45" stroke="#10B981" stroke-width="4" stroke-linecap="round"/>
                            <path d="M70 85L70 45C70 40 85 40 85 45L85 85" fill="#374151"/>
                            <path d="M72 85H67" stroke="#111827" stroke-width="2"/>
                            <path d="M87 85H82" stroke="#111827" stroke-width="2"/>
                            <path d="M75 10C75 5 82 5 82 10V20C82 25 75 25 75 20V10Z" fill="#FCD34D"/>
                            <path d="M65 25C65 15 90 15 90 25V45L65 45V25Z" fill="#4B5563"/>
                            <circle cx="77" cy="8" r="6" fill="#1F2937"/>
                        </g>
                        <line x1="250" y1="195" x2="330" y2="195" stroke="#E5E7EB" stroke-width="2"/>
                    </svg>
                </div>
            <?php else: ?>
                <?php if ($employee_details): ?>
                    <div class="employee-profile-card">
                        <div class="emp-avatar">
                            <?= '<img src="' . htmlspecialchars($employee_details['profile_photo'] ?? 'uploads/photos/user.png') . '" alt="Profile Image" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">'; ?>
                        </div>
                        <div class="emp-details-grid">
                            <div class="emp-detail-item">
                                <span class="emp-label">Name</span>
                                <span class="emp-val"><?= htmlspecialchars($employee_details['employee_name'] ?? '') ?></span>
                            </div>
                            <div class="emp-detail-item">
                                <span class="emp-label">Emp Code</span>
                                <span class="emp-val"><?= htmlspecialchars($employee_details['employee_code'] ?? '') ?></span>
                            </div>
                            <div class="emp-detail-item">
                                <span class="emp-label">Department</span>
                                <span class="emp-val"><?= htmlspecialchars($employee_details['department'] ?? 'N/A') ?></span>
                            </div>
                            <div class="emp-detail-item">
                                <span class="emp-label">Designation</span>
                                <span class="emp-val"><?= htmlspecialchars($employee_details['designation'] ?? 'N/A') ?></span>
                            </div>
                            <div class="emp-detail-item">
                                <span class="emp-label">Join Date</span>
                                <span class="emp-val"><?= !empty($employee_details['join_date']) ? date('d M Y', strtotime($employee_details['join_date'])) : 'N/A' ?></span>
                            </div>
                            <div class="emp-detail-item">
                                <span class="emp-label">Official Email</span>
                                <span class="emp-val"><?= htmlspecialchars($employee_details['official_email'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="table-container border">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="ps-4">Date And Day</th>
                                <th>Day Status</th>
                                <th>Shift Name</th>
                                <th>In - Out</th>
                                <th>Hours Worked</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($time_entries)): ?>
                                <?php foreach ($time_entries as $index => $row): 
                                    $dateFormatted = date('d M, D', strtotime($row['entry_date']));                                    
                                    $isToday = ($row['entry_date'] === date('Y-m-d'));
                                    $highlightClass = $isToday ? 'current-date-row' : '';

                                    // Get status halves for side-by-side pills
                                    $half1 = !empty($row['day_status_1']) ? trim($row['day_status_1']) : '-';
                                    $half2 = !empty($row['day_status_2']) ? trim($row['day_status_2']) : '-';

                                    // If old row only had full code like 'PP' stored in day_status_1, split it
                                    if ($half2 === '-' && in_array($half1, array_keys($status_options))) {
                                        $split = splitStatusToHalves($half1);
                                        $half1 = $split[0];
                                        $half2 = $split[1];
                                    }

                                    $pillClass1 = getPillClass($half1);
                                    $pillClass2 = getPillClass($half2);

                                    $selectedCode = getCodeFromHalves($row['day_status_1'] ?? '', $row['day_status_2'] ?? '');
                                    
                                    // Priority 1: `shift_name` from DB. Priority 2: Calculated shift. Priority 3: Not Assigned
                                    $shiftDisplay = !empty($row['shift_name']) ? $row['shift_name'] : ($row['assigned_shift_name'] ?? 'Not Assigned');
                                    
                                    $rowId = "row-" . $index . "-details";
                                ?>
                                    <tr class="<?= $highlightClass ?>">
                                        <td class="ps-4">
                                            <?= htmlspecialchars($dateFormatted) ?>
                                            <?php if($isToday): ?>
                                                <span class="badge bg-primary ms-1" style="color: #000; font-size:10px; padding:2px 5px; border-radius:3px;">Today</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="status-pill-group">
                                                <span class="status-pill <?= $pillClass1 ?>"><?= htmlspecialchars($half1) ?></span>
                                                <span class="status-pill <?= $pillClass2 ?>"><?= htmlspecialchars($half2) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($shiftDisplay) ?></td>
                                        <td>
                                            <?php
                                            $checkIn  = (!empty($row['check_in_time']) && $row['check_in_time'] != '0000-00-00 00:00:00')
                                                        ? date('h:i A', strtotime($row['check_in_time']))
                                                        : '';

                                            $checkOut = (!empty($row['check_out_time']) && $row['check_out_time'] != '0000-00-00 00:00:00')
                                                        ? date('h:i A', strtotime($row['check_out_time']))
                                                        : '';

                                            if ($checkIn && $checkOut) {
                                                echo htmlspecialchars($checkIn) . ' - ' . htmlspecialchars($checkOut);
                                            } elseif ($checkIn) {
                                                echo htmlspecialchars($checkIn);
                                            } elseif ($checkOut) {
                                                echo htmlspecialchars($checkOut);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $hrs = formatTimeForDisplay($row['hours_worked'] ?? '');
                                            echo !empty($hrs) ? htmlspecialchars($hrs) : '-';
                                            ?>
                                        </td>
                                        <td><span class="system-badge"><?= htmlspecialchars($row['record_status'] ?? 'System') ?></span></td>
                                        <td class="text-end pe-4"><i class="bi bi-chevron-down text-muted" style="cursor:pointer;" onclick="toggleRow('<?= $rowId ?>', this)"></i></td>
                                    </tr>
                                    
                                    <tr id="<?= $rowId ?>" class="expandable-row">
                                        <td colspan="7" class="p-0 border-0">
                                            <div class="edit-form-container">
                                                <div class="grid-row mb-4">
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Day Status</label>
                                                        <select class="form-select upd-day-status">
                                                            <?php foreach($status_options as $val => $label): ?>
                                                                <option value="<?= htmlspecialchars($val) ?>" <?= ($selectedCode === $val) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Check In Time</label>
                                                        <div class="input-icon-wrapper">
                                                            <input type="datetime-local" step="1" class="form-control upd-check-in" value="<?= !empty($row['check_in_time']) ? date('Y-m-d\TH:i:s', strtotime($row['check_in_time'])) : '' ?>">
                                                        </div>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Check Out Time</label>
                                                        <div class="input-icon-wrapper">
                                                            <input type="datetime-local" step="1" class="form-control upd-check-out" value="<?= !empty($row['check_out_time']) ? date('Y-m-d\TH:i:s', strtotime($row['check_out_time'])) : '' ?>">
                                                        </div>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Hours Worked</label>
                                                        <input type="text" class="form-control upd-hours-worked" value="<?= htmlspecialchars(formatTimeForDisplay($row['hours_worked'] ?? '')) ?>" readonly>
                                                    </div>
                                                </div>
                                                
                                                <div class="grid-row mb-4">
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Over Time Hours</label>
                                                        <input type="text" class="form-control upd-over-time" value="<?= htmlspecialchars(formatTimeForDisplay($row['over_time_hours'] ?? '')) ?>" readonly>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Under Time Hours</label>
                                                        <input type="text" class="form-control upd-under-time" value="<?= htmlspecialchars(formatTimeForDisplay($row['under_time_hours'] ?? '')) ?>" readonly>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Normal Hours</label>
                                                        <input type="text" class="form-control upd-normal-hours" value="<?= htmlspecialchars(formatTimeForDisplay($row['normal_hours'] ?? '')) ?>" readonly>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Late Hours</label>
                                                        <input type="text" class="form-control upd-late-hours" value="<?= htmlspecialchars(formatTimeForDisplay($row['late_hours'] ?? '')) ?>" readonly>
                                                    </div>
                                                </div>
                                                
                                                <div class="grid-row mb-3 flex-end" style="align-items: flex-end;">
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Early Hours</label>
                                                        <input type="text" class="form-control upd-early-hours" value="<?= htmlspecialchars(formatTimeForDisplay($row['early_hours'] ?? '')) ?>" readonly>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Status</label>
                                                        <input type="text" class="form-control upd-status-code" value="<?= htmlspecialchars($row['status_code'] ?? '') ?>">
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Remarks</label>
                                                        <input type="text" class="form-control upd-remarks" value="<?= htmlspecialchars($row['remarks'] ?? '') ?>">
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <div class="form-check" style="margin-bottom: 8px;">
                                                            <input class="form-check-input upd-calc-in-out" type="checkbox" id="calcInOut_<?= $index ?>" <?= !empty($row['calculate_per_in_out']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="calcInOut_<?= $index ?>">Calc per In/Out time</label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex-end gap-2 mt-5">
                                                    <?php if (!empty($row['record_status']) && $row['record_status'] !== 'System'): ?>
                                                        <button class="btn btn-outline-danger" onclick="deleteTimeEntry('<?= htmlspecialchars($row['employee_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['entry_date'], ENT_QUOTES) ?>')">Delete</button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-primary" onclick="toggleRow('<?= $rowId ?>', this.closest('.expandable-row').previousElementSibling.querySelector('i'))">Cancel</button>
                                                    <button class="btn btn-primary" onclick="saveTimeEntry(this, '<?= htmlspecialchars($row['employee_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['entry_date'], ENT_QUOTES) ?>')">Save</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">No data logs matched your criteria within the specified date frame.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <div>
                        Showing 1 to <?= count($time_entries) ?> of <?= count($time_entries) ?> entries 
                        <span class="border rounded-1 bg-white" style="margin-left:16px; padding: 4px 8px;">
                            Show 
                            <select style="border:none; outline:none; background:transparent;">
                                <option>25</option>
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

            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 

<script>
    const searchInput = document.getElementById('employeeSearchInput');
    const searchHidden = document.getElementById('employeeSearchHidden');
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
                                item.innerHTML = `<span class="autocomplete-name">${emp.employee_name}</span><span class="autocomplete-code">#${emp.employee_code}</span>`;
                                item.addEventListener('click', () => {
                                    searchInput.value = emp.employee_name; 
                                    searchHidden.value = emp.employee_name; 
                                    dropdown.style.display = 'none';
                                    filterForm.submit();
                                });
                                dropdown.appendChild(item);
                            });
                        } else {
                            dropdown.innerHTML = '<div class="autocomplete-item text-muted">No employees found</div>';
                        }
                        dropdown.style.display = 'block';
                    })
                    .catch(error => console.error('Error:', error));
            }, 300); 
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    function toggleRow(rowId, iconElement) {
        const row = document.getElementById(rowId);
        if (row.classList.contains('show')) {
            row.classList.remove('show');
            if (iconElement && iconElement.classList) {
                iconElement.classList.replace('bi-chevron-up', 'bi-chevron-down');
                iconElement.classList.replace('text-dark', 'text-muted');
            }
        } else {
            document.querySelectorAll('.expandable-row.show').forEach(openRow => {
                openRow.classList.remove('show');
                let prevRowIcon = openRow.previousElementSibling.querySelector('i');
                if (prevRowIcon) {
                    prevRowIcon.classList.replace('bi-chevron-up', 'bi-chevron-down');
                    prevRowIcon.classList.replace('text-dark', 'text-muted');
                }
            });

            row.classList.add('show');
            if (iconElement && iconElement.classList) {
                iconElement.classList.replace('bi-chevron-down', 'bi-chevron-up');
                iconElement.classList.replace('text-muted', 'text-dark');
            }
        }
    }

    function formatMsToHrs(ms) {
        if (ms <= 0) return '00:00 Hrs';
        let totalMins = Math.floor(ms / 60000);
        let hours = Math.floor(totalMins / 60);
        let mins = totalMins % 60;
        return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0') + ' Hrs';
    }

    function runTimeCalculations(container) {
        const checkIn = container.querySelector('.upd-check-in').value;
        const checkOut = container.querySelector('.upd-check-out').value;
        const isAutoCalc = container.querySelector('.upd-calc-in-out').checked;

        const inputs = ['upd-over-time', 'upd-under-time', 'upd-normal-hours', 'upd-late-hours', 'upd-early-hours'];
        inputs.forEach(cls => {
            container.querySelector('.' + cls).readOnly = isAutoCalc;
        });

        if (!isAutoCalc || !checkIn || !checkOut) return;

        const inTime = new Date(checkIn);
        const outTime = new Date(checkOut);
        
        if(isNaN(inTime) || isNaN(outTime)) return; 

        const shiftStart = new Date(inTime);
        shiftStart.setHours(9, 0, 0, 0); 
        
        const shiftEnd = new Date(inTime);
        shiftEnd.setHours(18, 0, 0, 0); 
        
        const standardHoursMs = 9 * 60 * 60 * 1000; 

        let hoursWorkedMs = outTime - inTime;
        if(hoursWorkedMs < 0) hoursWorkedMs = 0; 

        let lateMs = inTime > shiftStart ? (inTime - shiftStart) : 0;
        let earlyMs = outTime < shiftEnd ? (shiftEnd - outTime) : 0;
        let overTimeMs = hoursWorkedMs > standardHoursMs ? (hoursWorkedMs - standardHoursMs) : 0;
        let underTimeMs = hoursWorkedMs < standardHoursMs ? (standardHoursMs - hoursWorkedMs) : 0;
        let normalMs = Math.min(hoursWorkedMs, standardHoursMs);

        container.querySelector('.upd-hours-worked').value = formatMsToHrs(hoursWorkedMs);
        container.querySelector('.upd-late-hours').value = formatMsToHrs(lateMs);
        container.querySelector('.upd-early-hours').value = formatMsToHrs(earlyMs);
        container.querySelector('.upd-over-time').value = formatMsToHrs(overTimeMs);
        container.querySelector('.upd-under-time').value = formatMsToHrs(underTimeMs);
        container.querySelector('.upd-normal-hours').value = formatMsToHrs(normalMs);
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('upd-check-in') || 
            e.target.classList.contains('upd-check-out') || 
            e.target.classList.contains('upd-calc-in-out')) {
            
            const container = e.target.closest('.edit-form-container');
            runTimeCalculations(container);
        }
    });

    const dateRangeInput = document.getElementById("dateRange");
    if (dateRangeInput) {
        flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "d M Y",
            monthSelectorType: "static",
            animate: true,
            showMonths: 2
        });
    }

    function showToast(title, message, type = 'success') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `custom-toast ${type}`;
        
        const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        
        toast.innerHTML = `
            <i class="bi ${icon} toast-icon"></i>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <i class="bi bi-x toast-close" onclick="this.parentElement.classList.remove('show'); setTimeout(() => this.parentElement.remove(), 300);"></i>
        `;

        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            if(toast.classList.contains('show')) {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300); 
            }
        }, 3000);
    }

    function saveTimeEntry(button, empCode, entryDate) {
        const container = button.closest('.edit-form-container');
        
        const formData = new URLSearchParams();
        formData.append('action', 'update_entry');
        formData.append('emp_code', empCode);
        formData.append('entry_date', entryDate);
        formData.append('day_status', container.querySelector('.upd-day-status').value);
        formData.append('check_in', container.querySelector('.upd-check-in').value);
        formData.append('check_out', container.querySelector('.upd-check-out').value);
        formData.append('hours_worked', container.querySelector('.upd-hours-worked').value);
        formData.append('over_time', container.querySelector('.upd-over-time').value);
        formData.append('under_time', container.querySelector('.upd-under-time').value);
        formData.append('normal_hours', container.querySelector('.upd-normal-hours').value);
        formData.append('late_hours', container.querySelector('.upd-late-hours').value);
        formData.append('early_hours', container.querySelector('.upd-early-hours').value);
        formData.append('status_code', container.querySelector('.upd-status-code').value);
        formData.append('remarks', container.querySelector('.upd-remarks').value);
        formData.append('calc_in_out', container.querySelector('.upd-calc-in-out').checked);

        const originalText = button.innerHTML;
        button.innerHTML = 'Saving...';
        button.disabled = true;

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Saved!', 'Time entry updated successfully.', 'success');
                setTimeout(() => {
                    window.location.reload(); 
                }, 1500); 
            } else {
                showToast('Error', data.error || 'Failed to update record.', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network Error', 'Something went wrong communicating with the server.', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }

    function deleteTimeEntry(empCode, entryDate) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new URLSearchParams();
                formData.append('action', 'delete_entry');
                formData.append('emp_code', empCode);
                formData.append('entry_date', entryDate);

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            'Deleted!',
                            'The time entry has been deleted.',
                            'success'
                        ).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire(
                            'Error!',
                            data.error || 'Failed to delete the record.',
                            'error'
                        );
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire(
                        'Network Error',
                        'Something went wrong communicating with the server.',
                        'error'
                    );
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const editContainers = document.querySelectorAll('.edit-form-container');
        
        editContainers.forEach(container => {
            const calcCheckbox = container.querySelector('.upd-calc-in-out');
            
            if (calcCheckbox && calcCheckbox.checked) {
                runTimeCalculations(container);
            }
        });
    });
</script>
<script src="includes/assets/scripts.js"></script>