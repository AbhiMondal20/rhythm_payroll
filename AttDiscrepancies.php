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

// Handle AJAX Request for Updating or Inserting Time Entries
if (isset($_POST['action']) && $_POST['action'] === 'update_entry') {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    header('Content-Type: application/json');

    if (isset($conn)) {
        $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code']);
        $entry_date = mysqli_real_escape_string($conn, $_POST['entry_date']);
        
        $day_status = mysqli_real_escape_string($conn, $_POST['day_status']);
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
                day_status_1 = '$day_status',
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
                employee_code, entry_date, day_status_1, check_in_time, check_out_time, 
                hours_worked, over_time_hours, under_time_hours, normal_hours, 
                late_hours, early_hours, status_code, remarks, calculate_per_in_out, record_status
            ) VALUES (
                '$emp_code', '$entry_date', '$day_status', $check_in_val, $check_out_val, 
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

$page_title = 'Discrepancies';

$search_query = isset($_GET['employee']) ? trim($_GET['employee']) : '';
$date_range = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';
$discrepancy_type = isset($_GET['discrepancy_type']) ? trim($_GET['discrepancy_type']) : 'all';
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

if (!empty($date_range) && strpos($date_range, ' to ') !== false) {
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
    } else {
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-d'); 
        $date_range = date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date));
    }
} else {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d'); 
    $date_range = date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date));
}

// Format period string for display
$period_display = date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date));
if (date('m Y', strtotime($start_date)) === date('m Y', strtotime($end_date)) && date('d', strtotime($start_date)) == '01' && date('d', strtotime($end_date)) == date('t', strtotime($end_date))) {
    $period_display = date('F Y', strtotime($start_date));
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
        
        // Fetch Shift Assignments checking BOTH id and employee_code
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
            $day_num = $dt->format('N'); // 1-7 (Mon-Sun)
            $day_short = $dt->format('D'); // Mon, Tue...
            
            // Shift Assignment Logic
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
                $entry['assigned_shift_name'] = $assigned_shift_name;
                $time_entries[] = $entry;
            } else {
                $is_sunday = ($day_num == 7);
                $default_status = $is_sunday ? 'WO' : 'AA'; 
                
                $time_entries[] = [
                    'employee_code' => $emp_code,
                    'entry_date' => $date_str,
                    'day_status_1' => $default_status,
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
    
    .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 25px; flex-wrap: wrap; }
    .search-wrapper { position: relative; width: 300px; }
    .search-wrapper .form-control { padding-left: 35px; border-radius: 4px; }
    .search-wrapper .bi-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px; }
    
    .search-chip { position: absolute; top: 4px; left: 4px; bottom: 4px; right: 4px; background-color: #fff; display: flex; align-items: center; padding: 0 8px; font-size: 13px; color: #111827; z-index: 5; }
    .search-chip a { color: #6b7280; margin-left: auto; text-decoration: none; font-size: 16px; display: flex; align-items: center; }
    .search-chip a:hover { color: #ef4444; }

    .autocomplete-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #d1d5db; border-top: none; border-radius: 0 0 4px 4px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); z-index: 1000; max-height: 250px; overflow-y: auto; display: none; margin-top: 2px; }
    .autocomplete-item { padding: 10px 14px; cursor: pointer; font-size: 13.5px; border-bottom: 1px solid #f3f4f6; display: flex; flex-direction: column; transition: background-color 0.1s; }
    .autocomplete-item:last-child { border-bottom: none; }
    .autocomplete-item:hover { background-color: #eff6ff; }
    .autocomplete-name { font-weight: 600; color: #111827; }
    .autocomplete-code { font-size: 11px; color: #6b7280; margin-top: 2px; }

    .form-control, .form-select { width: 100%; border-radius: 4px; font-size: 13.5px; border: 1px solid #d1d5db; height: 36px; padding: 6px 12px; background-color: #fff; outline: none; box-shadow: none; }
    .form-control:focus, .form-select:focus { border-color: #0d6efd; }
    
    .date-dropdown { display: flex; align-items: center; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; height: 36px; width: 220px; padding: 0 12px; gap: 8px;}
    .date-dropdown .bi-calendar { color: #6b7280; border-right: 1px solid #d1d5db; padding-right: 8px; }
    .date-dropdown input { border: none; background: transparent; flex: 1; outline: none; font-size: 13.5px; color: #4b5563; }
    .btn-apply { background-color: #0d6efd;
    color: #fff;
    height: 36px;
    padding: 0 20px;
    font-size: 13.5px;}
    .btn-apply:hover, .btn-primary:hover { background-color: #0b5ed7; color: #fff; }
    .btn-outline-secondary { border-color: #d1d5db; color: #4b5563; background: #fff; }
    .btn-outline-secondary:hover { background: #f3f4f6; color: #111827; }
    .btn-primary { background: #0d6efd; color: #fff; }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state-svg { max-width: 350px; height: auto; margin-top: 30px; }

    /* New Specific Badges based on image */
    .status-badge-sm { display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; font-size: 11px; font-weight: 600; padding: 3px 6px; margin-right: 2px; }
    .status-p-bg { background-color: #e6f4ea; color: #1e8e3e; border: 1px solid #ceead6; }
    .status-a-bg { background-color: #fce8e6; color: #d93025; border: 1px solid #fad2cf; }
    
    .badge-disc { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; display: inline-block; }
    .badge-short-hours { background-color: #fff4e5; color: #ed6c02; border: 1px solid #ffe2c2; }
    .badge-late-login { background-color: #e6f4ea; color: #1e8e3e; border: 1px solid #ceead6; }
    .badge-missing { background-color: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; }

    .selected-emp-chip { display: flex; align-items: center; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 6px; background-color: #fff; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .selected-emp-chip .form-check-input { margin-top: 0; margin-right: 12px; cursor: pointer; }

    .table-container { border-radius: 6px; overflow: hidden; width: 100%; border: 1px solid #e5e7eb; background: #fff; }
    .table { width: 100%; border-collapse: collapse; text-align: left; margin-bottom: 0; }
    .table th { background-color: #f8f9fa; font-weight: 600; font-size: 13px; color: #4b5563; border-bottom: 1px solid #e5e7eb; padding: 12px 16px; }
    .table td { vertical-align: middle; font-size: 13.5px; color: #111827; padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
    .table tbody tr:hover { background-color: #f9fafb; }
    
    .expandable-row { background-color: #f8fafc; display: none; border-bottom: 1px solid #e5e7eb;}
    .expandable-row.show { display: table-row; }
    .edit-form-container { padding: 24px 30px; background-color: #f8fafc; }
    .edit-form-container .form-label { display: block; font-size: 12px; color: #4b5563; font-weight: 500; margin-bottom: 4px; }
    .edit-form-container .form-control, .edit-form-container .form-select { background: #fff; }
    
    .form-check { display: flex; align-items: center; gap: 8px; margin: 0; }
    .form-check-input { width: 16px; height: 16px; cursor: pointer; margin: 0; }
    
    .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-size: 13px; color: #6b7280; }
    .pagination-btn-group { display: flex; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); border-radius: 4px; overflow: hidden; }
    .pagination-btn-group button { border: 1px solid #e5e7eb; background: #fff; padding: 6px 12px; cursor: pointer; color: #6b7280; outline: none; margin-left: -1px; }
    .pagination-btn-group button.active { background: #e9ecef; color: #111827; border-color: #d1d5db; z-index: 2; font-weight: 600; }
    .pagination-btn-group button:disabled { background: #f9fafb; color: #d1d5db; cursor: not-allowed; }

    /* Modal styling to match image */
    .modal-content { border-radius: 8px; border: none; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    .modal-header { border-bottom: 1px solid #e5e7eb; padding: 16px 24px; }
    .modal-title { font-size: 16px; font-weight: 600; color: #111827; }
    .modal-body { padding: 24px; }
    .modal-footer { border-top: 1px solid #e5e7eb; padding: 16px 24px; background: #f9fafb; display: flex; justify-content: space-between; border-radius: 0 0 8px 8px; }
    
    .advanced-search-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 16px; }
    .advanced-search-field label { font-size: 12px; color: #4b5563; margin-bottom: 4px; display: block; }
    
    .recent-saved-tabs { display: flex; border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden; }
    .recent-saved-tabs button { padding: 6px 12px; font-size: 12px; border: none; background: #fff; color: #4b5563; cursor: pointer; }
    .recent-saved-tabs button.active { background: #f3f4f6; color: #0d6efd; font-weight: 500; }
</style>

<div class="container">    
    <div class="flex-between mb-1">
        <h4 class="text-dark fw-bold m-0" style="font-size: 1.25rem;">Attendance</h4>
        <div class="attendance-tabs m-0 border-0">
            <a href="attendance" >Time Entries</a>
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
            <h6 class="text-dark fw-bold mb-1" style="font-size: 13.5px; letter-spacing: 0.5px; margin-top: 0; text-transform: uppercase;">Discrepancies</h6>
            <p class="text-muted mb-4" style="font-size: 12px;">Note: Once the time card has been processed, this discrepancies page will automatically reflect the updated and accurate data.</p>
            
            <form method="GET" action="" class="filter-bar" id="filterForm">
                <div class="search-wrapper">
                    <?php if ($is_searched): ?>
                        <div class="search-chip">
                            <?= htmlspecialchars($search_query) ?> 
                            <a href="?"><i class="bi bi-x"></i></a>
                        </div>
                        <input type="hidden" name="employee" value="<?= htmlspecialchars($search_query) ?>">
                        <input type="text" class="form-control" disabled>
                    <?php else: ?>
                        <i class="bi bi-search"></i>
                        <input type="text" id="employeeSearchInput" class="form-control" placeholder="Search by name or #code" autocomplete="off">
                        <input type="hidden" id="employeeSearchHidden" name="employee" value="">
                        <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
                    <?php endif; ?>
                </div>
                
                <select name="discrepancy_type" class="form-select" style="width: 170px;">
                    <option value="all" <?= $discrepancy_type == 'all' ? 'selected' : '' ?>>Discrepancy Type</option>
                    <option value="short_hours" <?= $discrepancy_type == 'short_hours' ? 'selected' : '' ?>>Short Hours</option>
                    <option value="missing_swipes" <?= $discrepancy_type == 'missing_swipes' ? 'selected' : '' ?>>Missing Swipes</option>
                    <option value="no_attendance" <?= $discrepancy_type == 'no_attendance' ? 'selected' : '' ?>>No Attendance</option>
                    <option value="late_login" <?= $discrepancy_type == 'late_login' ? 'selected' : '' ?>>Late Login</option>
                </select>

                <div class="date-dropdown">
                    <i class="bi bi-calendar"></i>
                    <input type="text" name="date_range" id="dateRange" placeholder="Select Date" value="<?= htmlspecialchars($date_range) ?>">
                </div>                
                
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#advancedSearchModal">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                
                <button type="submit" class="btn btn-apply">Apply</button>
                
                <div class="ms-auto fw-bold text-dark" style="font-size: 13.5px;">
                    Period: <?= htmlspecialchars($period_display) ?>
                </div>
            </form>

            <?php if (!$is_searched): ?>
                <div class="empty-state">
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
                    <div class="selected-emp-chip">
                        <input class="form-check-input select-all-emp" type="checkbox" checked id="selectAllEmp">
                        <label class="form-check-label text-dark" for="selectAllEmp">
                            <?= htmlspecialchars($employee_details['employee_name'] ?? '') ?> - <?= htmlspecialchars($employee_details['employee_code'] ?? '') ?>
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
                                <th>Code</th>
                                <th>Name</th>
                                <th>Day Status</th>
                                <th>Date</th>
                                <th>In - Out</th>
                                <th>Discrepancy Type</th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($time_entries)): ?>
                                <?php foreach ($time_entries as $index => $row): 
                                    $dateFormatted = date('d-m-Y', strtotime($row['entry_date']));                                 
                                    
                                    $rawStatus = $row['day_status_1'] ?? '';
                                    // Make badges like 'P P' for full day based on image
                                    $statusBadgesHtml = '';
                                    if (in_array($rawStatus, ['PP'])) {
                                        $statusBadgesHtml = '<span class="status-badge-sm status-p-bg">P</span> <span class="status-badge-sm status-p-bg">P</span>';
                                    } elseif (in_array($rawStatus, ['AA'])) {
                                        $statusBadgesHtml = '<span class="status-badge-sm status-a-bg">A</span> <span class="status-badge-sm status-a-bg">A</span>';
                                    } else {
                                        $displaySt = $status_options[$rawStatus] ?? ($rawStatus ?: '-');
                                        $statusBadgesHtml = '<span class="status-badge-sm status-p-bg">'.htmlspecialchars(substr($displaySt, 0, 3)).'</span>';
                                    }
                                    
                                    // Determine discrepancy type (mock logic for demo matching design)
                                    $discBadgeClass = 'badge-late-login';
                                    $discText = 'Late Login';
                                    if (!empty($row['under_time_hours']) && $row['under_time_hours'] !== '00:00:00') {
                                        $discBadgeClass = 'badge-short-hours';
                                        $discText = 'Short Hours';
                                    } elseif (empty($row['check_in_time']) && empty($row['check_out_time']) && $rawStatus != 'WO') {
                                        $discBadgeClass = 'badge-missing';
                                        $discText = 'Missing Swipes';
                                    }

                                    $rowId = "row-" . $index . "-details";
                                ?>
                                    <tr>
                                        <td style="padding-left: 20px;">
                                            <input type="checkbox" class="form-check-input row-checkbox" checked value="<?= $row['entry_date'] ?>">
                                        </td>
                                        <td><?= htmlspecialchars($row['employee_code']) ?></td>
                                        <td><?= htmlspecialchars($employee_details['employee_name'] ?? 'Employee') ?></td>
                                        <td>
                                            <?= $statusBadgesHtml ?>
                                        </td>
                                        <td><?= htmlspecialchars($dateFormatted) ?></td>
                                        
                                        <td>
                                            <?php if (empty($row['check_in_time']) && empty($row['check_out_time'])): ?>
                                                -
                                            <?php else: ?>
                                                <?= !empty($row['check_in_time']) ? htmlspecialchars(date('h:i A', strtotime($row['check_in_time']))) : '--:--' ?> - 
                                                <?= !empty($row['check_out_time']) ? htmlspecialchars(date('h:i A', strtotime($row['check_out_time']))) : '--:--' ?>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <span class="badge-disc <?= $discBadgeClass ?>"><?= htmlspecialchars($discText) ?></span>
                                        </td>
                                        <td class="text-end pe-4"><i class="bi bi-chevron-down text-muted" style="cursor:pointer;" onclick="toggleRow('<?= $rowId ?>', this)"></i></td>
                                    </tr>
                                    
                                    <tr id="<?= $rowId ?>" class="expandable-row">
                                        <td colspan="8" class="p-0 border-0">
                                            <div class="edit-form-container">
                                                <div class="grid-row mb-4">
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Day Status</label>
                                                        <select class="form-select upd-day-status">
                                                            <?php foreach($status_options as $val => $label): ?>
                                                                <option value="<?= htmlspecialchars($val) ?>" <?= (($row['day_status_1'] ?? '') === $val) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Check In Time</label>
                                                        <input type="datetime-local" step="1" class="form-control upd-check-in" value="<?= !empty($row['check_in_time']) ? date('Y-m-d\TH:i:s', strtotime($row['check_in_time'])) : '' ?>">
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Check Out Time</label>
                                                        <input type="datetime-local" step="1" class="form-control upd-check-out" value="<?= !empty($row['check_out_time']) ? date('Y-m-d\TH:i:s', strtotime($row['check_out_time'])) : '' ?>">
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

                                                <div class="flex-end gap-2 mt-4">
                                                    <?php if (!empty($row['record_status']) && $row['record_status'] !== 'System'): ?>
                                                        <button class="btn btn-outline-danger" onclick="deleteTimeEntry('<?= $row['employee_code'] ?>', '<?= $row['entry_date'] ?>')">Delete</button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-secondary" onclick="toggleRow('<?= $rowId ?>', this.closest('.expandable-row').previousElementSibling.querySelector('i'))">Cancel</button>
                                                    <button class="btn btn-primary" onclick="saveTimeEntry(this, '<?= $row['employee_code'] ?>', '<?= $row['entry_date'] ?>')">Save</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">No data logs matched your criteria within the specified date frame.</td>
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
                            <select class="form-select d-inline-block form-select-sm" style="width: auto; height: 30px; padding: 2px 24px 2px 8px;">
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
                    <p class="text-muted mb-0" style="font-size: 12px; max-width: 75%;">
                        Note: Upon regularization, the selected employee's attendance status will be changed to 'Present'. To exclude any entries, simply uncheck them and take no further action.
                    </p>
                    <button class="btn btn-primary" id="btnRegularize">Regularize (<span id="regCount"><?= count($time_entries) ?></span>)</button>
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

<script>
    const searchInput = document.getElementById('employeeSearchInput');
    const searchHidden = document.getElementById('employeeSearchHidden');
    const dropdown = document.getElementById('autocompleteDropdown');
    const filterForm = document.getElementById('filterForm');

    // Autocomplete Logic
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
                                    searchHidden.value = emp.employee_code; // Usually pass code or ID
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

    // Toggle Details Row
    function toggleRow(rowId, iconElement) {
        const row = document.getElementById(rowId);
        if (row.classList.contains('show')) {
            row.classList.remove('show');
            if (iconElement && iconElement.classList) {
                iconElement.classList.replace('bi-chevron-up', 'bi-chevron-down');
            }
        } else {
            // Close others
            document.querySelectorAll('.expandable-row.show').forEach(openRow => {
                openRow.classList.remove('show');
                let prevRowIcon = openRow.previousElementSibling.querySelector('.bi-chevron-up');
                if (prevRowIcon) {
                    prevRowIcon.classList.replace('bi-chevron-up', 'bi-chevron-down');
                }
            });

            row.classList.add('show');
            if (iconElement && iconElement.classList) {
                iconElement.classList.replace('bi-chevron-down', 'bi-chevron-up');
            }
        }
    }

    // Checkbox Logic for Count
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllRows = document.getElementById('selectAllRows');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        const regCount = document.getElementById('regCount');

        function updateCount() {
            let count = 0;
            rowCheckboxes.forEach(cb => { if(cb.checked) count++; });
            if(regCount) regCount.innerText = count;
        }

        if (selectAllRows) {
            selectAllRows.addEventListener('change', function() {
                rowCheckboxes.forEach(cb => cb.checked = selectAllRows.checked);
                updateCount();
            });
        }

        rowCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                if(!this.checked && selectAllRows) selectAllRows.checked = false;
                updateCount();
            });
        });

        const selectAllEmp = document.getElementById('selectAllEmp');
        if (selectAllEmp && selectAllRows) {
            selectAllEmp.addEventListener('change', function(){
                selectAllRows.checked = this.checked;
                rowCheckboxes.forEach(cb => cb.checked = this.checked);
                updateCount();
            });
        }
    });

    // Date Picker
    const dateRangeInput = document.getElementById("dateRange");
    if (dateRangeInput) {
        flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "d-m-Y",
            showMonths: 2,
            animate: true
        });
    }

    // Save Logic (Ajax)
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
                Swal.fire({ toast: true, position: 'bottom-end', icon: 'success', title: 'Saved successfully', showConfirmButton: false, timer: 1500 });
                setTimeout(() => window.location.reload(), 1500); 
            } else {
                Swal.fire('Error', data.error || 'Failed to update', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Network Error', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }

    // Delete Logic
    function deleteTimeEntry(empCode, entryDate) {
        Swal.fire({
            title: 'Are you sure?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Delete'
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
                }).then(response => response.json()).then(data => {
                    if (data.success) window.location.reload();
                });
            }
        });
    }
</script>
<script src="includes/assets/scripts.js"></script>