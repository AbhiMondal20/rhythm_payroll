<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login'])) {
    if (isset($_POST['action']) || isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login');
    exit();
}

$emp_code = $_SESSION['employee_code'] ?? '';

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

// Helper: Convert time string "HH:MM:SS" or decimal "8.5" to seconds
function timeToSeconds($timeStr) {
    if (empty($timeStr)) return 0;
    if (is_numeric($timeStr)) {
        $h = floor($timeStr);
        $m = round(($timeStr - $h) * 60);
        return ($h * 3600) + ($m * 60);
    }
    $parts = explode(':', $timeStr);
    $h = isset($parts[0]) ? (int)$parts[0] : 0;
    $m = isset($parts[1]) ? (int)$parts[1] : 0;
    $s = isset($parts[2]) ? (int)$parts[2] : 0;
    return ($h * 3600) + ($m * 60) + $s;
}

// Helper: Convert seconds back to "HH:MM" format
function secondsToTime($seconds) {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return sprintf("%02d:%02d", $h, $m);
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

$page_title = 'Attendance Overview';
$time_entries = [];
$shift_assignments = [];

// Determine Month Range (Current + previous 3 months)
$current_month = date('Y-m');
$allowed_months = [
    date('Y-m'),
    date('Y-m', strtotime('-1 month')),
    date('Y-m', strtotime('-2 months')),
    date('Y-m', strtotime('-3 months'))
];

$selected_month = isset($_GET['m']) ? trim($_GET['m']) : $current_month;

// Validate that selected month is within the allowed previous 3 months range
if (!in_array($selected_month, $allowed_months)) {
    $selected_month = $current_month;
}

$start_date = $selected_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// Determine Previous / Next Links for Arrows
$current_index = array_search($selected_month, $allowed_months);
$prev_month = ($current_index < 3) ? $allowed_months[$current_index + 1] : null; 
$next_month = ($current_index > 0) ? $allowed_months[$current_index - 1] : null; 

if (!empty($emp_code) && isset($conn)) {
    $safe_emp_code = mysqli_real_escape_string($conn, $emp_code);
    $safe_emp_id = mysqli_real_escape_string($conn, $_SESSION['emp_id'] ?? '');

    // Fetch shift assignments & timings for the employee
    $assign_sql = "SELECT sa.start_date, sa.end_date, sa.weekdays, s.shift_name, s.start_time, s.end_time 
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
    
    // Fetch time entries for the selected month
    $time_sql = "SELECT * FROM time_entries 
                 WHERE employee_code = '$safe_emp_code' 
                 AND entry_date BETWEEN '$start_date' AND '$end_date' 
                 ORDER BY entry_date ASC";
    
    $db_entries = [];
    $time_result = mysqli_query($conn, $time_sql);
    if ($time_result) {
        while ($row = mysqli_fetch_assoc($time_result)) {
            $db_entries[$row['entry_date']] = $row;
        }
    }

    // =================================================================
    // FETCH PENDING LEAVES AND TIME ADJUSTMENTS FOR THE MESSAGES
    // =================================================================
    
    // 1. Fetch pending leaves (spans across from_date to to_date)
    $pending_leaves = [];
    $leave_sql = "SELECT from_date, to_date FROM leave_requests 
                  WHERE emp_code = '$safe_emp_code' AND status = 'pending' 
                  AND (from_date <= '$end_date' AND to_date >= '$start_date')";
    $leave_res = mysqli_query($conn, $leave_sql);
    if ($leave_res) {
        while ($l_row = mysqli_fetch_assoc($leave_res)) {
            $l_start = strtotime($l_row['from_date']);
            $l_end = strtotime($l_row['to_date']);
            // Mark every day in the date range as having a pending leave
            for ($i = $l_start; $i <= $l_end; $i += 86400) {
                $pending_leaves[date('Y-m-d', $i)] = true;
            }
        }
    }

    // 2. Fetch pending time adjustments
    $pending_adjs = [];
    $adj_sql = "SELECT shift_date FROM approval_requests 
                WHERE emp_code = '$safe_emp_code' AND type = 'attendance' AND status = 'pending' 
                AND shift_date BETWEEN '$start_date' AND '$end_date'";
    $adj_res = mysqli_query($conn, $adj_sql);
    if ($adj_res) {
        while ($a_row = mysqli_fetch_assoc($adj_res)) {
            $pending_adjs[$a_row['shift_date']] = true;
        }
    }
    
    try {
        $begin = new DateTime($start_date);
        
        $today = date('Y-m-d');
        if ($end_date > $today) {
            $end_target = $today;
        } else {
            $end_target = $end_date;
        }
        
        $end = new DateTime($end_target);
        $end->modify('+1 day'); 

        $interval = new DateInterval('P1D');
        $period = new DatePeriod($begin, $interval, $end);

        foreach ($period as $dt) {
            $date_str = $dt->format('Y-m-d');
            $day_num = $dt->format('N');
            $day_short = $dt->format('D');
            
            $assigned_shift_name = 'Not Assigned';
            $assigned_shift_start = null;
            $assigned_shift_end = null;
            
            foreach ($shift_assignments as $assign) {
                $assign_start = $assign['start_date'];
                $assign_end = $assign['end_date'];
                $weekdays = $assign['weekdays'] ?? '';
                
                $is_after_start = ($date_str >= $assign_start);
                $is_before_end = (empty($assign_end) || $assign_end === '0000-00-00' || $date_str <= $assign_end);
                
                if ($is_after_start && $is_before_end) {
                    if (empty(trim($weekdays))) {
                        $assigned_shift_name = $assign['shift_name'];
                        $assigned_shift_start = $assign['start_time'] ?? null;
                        $assigned_shift_end = $assign['end_time'] ?? null;
                        break;
                    } else {
                        $allowed_days = array_map('trim', explode(',', $weekdays));
                        if (in_array((string)$day_num, $allowed_days) || in_array($day_short, $allowed_days)) {
                            $assigned_shift_name = $assign['shift_name'];
                            $assigned_shift_start = $assign['start_time'] ?? null;
                            $assigned_shift_end = $assign['end_time'] ?? null;
                            break;
                        }
                    }
                }
            }
            
            if (isset($db_entries[$date_str])) {
                $entry = $db_entries[$date_str];
                $entry['assigned_shift_name'] = !empty($entry['shift_name']) ? $entry['shift_name'] : $assigned_shift_name;
                $entry['assigned_shift_start'] = $assigned_shift_start;
                $entry['assigned_shift_end'] = $assigned_shift_end;
                $entry['pending_leave'] = isset($pending_leaves[$date_str]);
                $entry['pending_adj'] = isset($pending_adjs[$date_str]);
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
                    'assigned_shift_name' => $assigned_shift_name,
                    'assigned_shift_start' => $assigned_shift_start,
                    'assigned_shift_end' => $assigned_shift_end,
                    'pending_leave' => isset($pending_leaves[$date_str]),
                    'pending_adj' => isset($pending_adjs[$date_str])
                ];
            }
        }
    } catch (Exception $e) { }
}

// ==========================================
// CALCULATE STATS & DYNAMIC SHIFT TIMINGS
// ==========================================
$total_worked_sec = 0;
$total_early_sec  = 0;
$total_late_sec   = 0;
$total_short_sec  = 0;
$paid_halves      = 0;
$unpaid_halves    = 0;

$paid_statuses   = ['P', 'WO', 'HO', 'CL', 'SL', 'PL', 'Present', 'Week Off', 'WW', 'HW'];
$unpaid_statuses = ['A', 'LOP', 'Absent', 'AA'];

foreach ($time_entries as &$entry) {
    // Basic Worked Hours
    $total_worked_sec += timeToSeconds($entry['hours_worked'] ?? '0');
    
    // Late & Early Seconds Init
    $late_sec = timeToSeconds($entry['late_hours'] ?? '0');
    $early_sec = timeToSeconds($entry['early_hours'] ?? '0');

    // DYNAMIC CALCULATION: If DB didn't provide Late/Early, calculate it from Shift Timings!
    if ($late_sec == 0 && !empty($entry['check_in_time']) && !empty($entry['assigned_shift_start'])) {
        $in_sec = strtotime($entry['entry_date'] . ' ' . $entry['check_in_time']);
        $shift_start_sec = strtotime($entry['entry_date'] . ' ' . $entry['assigned_shift_start']);
        if ($in_sec > $shift_start_sec) {
            $late_sec = $in_sec - $shift_start_sec;
        }
    }

    if ($early_sec == 0 && !empty($entry['check_out_time']) && !empty($entry['assigned_shift_end'])) {
        $out_sec = strtotime($entry['entry_date'] . ' ' . $entry['check_out_time']);
        $shift_end_sec = strtotime($entry['entry_date'] . ' ' . $entry['assigned_shift_end']);
        if ($out_sec < $shift_end_sec) {
            $early_sec = $shift_end_sec - $out_sec;
        }
    }

    // Short Hours Calculation
    $short_sec = timeToSeconds($entry['under_time_hours'] ?? '0');
    if ($short_sec == 0) {
        $short_sec = $late_sec + $early_sec; // Short hours = late in + early out
    }

    // Add to Totals
    $total_early_sec  += $early_sec;
    $total_late_sec   += $late_sec;
    $total_short_sec  += $short_sec;

    // Save back to entry for card display if needed
    $entry['calc_late_sec'] = $late_sec;
    $entry['calc_early_sec'] = $early_sec;

    // Accumulate Days
    $s1 = trim($entry['day_status_1'] ?? '');
    $s2 = trim($entry['day_status_2'] ?? '');
    
    if (empty($s1) && empty($s2) && !empty($entry['status_code'])) {
        $halves = splitStatusToHalves($entry['status_code']);
        $s1 = trim($halves[0]);
        $s2 = trim($halves[1]);
    }

    if (in_array($s1, $paid_statuses) || $s1 === 'PP') $paid_halves++;
    elseif (in_array($s1, $unpaid_statuses)) $unpaid_halves++;
    
    if (in_array($s2, $paid_statuses) || $s2 === 'PP') $paid_halves++;
    elseif (in_array($s2, $unpaid_statuses)) $unpaid_halves++;
}

// Format Calculated Days
$total_paid_days = rtrim(rtrim(number_format($paid_halves / 2, 1), '0'), '.');
$total_paid_days = ($total_paid_days === '') ? '0' : $total_paid_days;

$total_unpaid_days = rtrim(rtrim(number_format($unpaid_halves / 2, 1), '0'), '.');
$total_unpaid_days = ($total_unpaid_days === '') ? '0' : $total_unpaid_days;

// Format Calculated Hours
$str_worked_hrs = secondsToTime($total_worked_sec);
$str_early_hrs  = secondsToTime($total_early_sec);
$str_late_hrs   = secondsToTime($total_late_sec);
$str_short_hrs  = secondsToTime($total_short_sec);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="16x16" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="apple-touch-icon" href="/rhythm_payroll/includes/assets/img/apple-touch-icon.png">
    <style>
        /* Custom scrollbar hiding */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Styles for active/inactive tabs */
        .tab-active {
            background-color: #1c212d;
            color: #facc15; /* Yellow-400 */
            border-color: #1c212d;
        }
        .tab-inactive {
            background-color: white;
            color: #6b7280; /* Gray-500 */
            border-color: #e5e7eb;
        }
    </style>
</head>

<body class="bg-gray-100 flex justify-center min-h-screen">

    <!-- Mobile App Container -->
    <div class="w-full max-w-md bg-[#f4f5f9] min-h-screen relative flex flex-col font-sans shadow-2xl overflow-hidden">
        
        <!-- Header Section -->
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[60px]">
            <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition no-underline">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center mr-[80px]">
                <h1 class="font-semibold text-[17px]">Attendance Overview</h1>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto no-scrollbar p-4 pb-10">

            <?php if(!empty($emp_code)): ?>
            
            <!-- Month Selector -->
            <div class="flex items-center justify-between bg-white rounded-xl shadow-sm p-3 mb-4 border border-gray-100">
                <?php if($prev_month): ?>
                    <a href="?m=<?= $prev_month ?>" class="w-8 h-8 flex items-center justify-center bg-[#f4f5f9] rounded-lg text-gray-500 hover:text-gray-800 transition">
                        <i class="fa-solid fa-chevron-left text-sm"></i>
                    </a>
                <?php else: ?>
                    <span class="w-8 h-8 flex items-center justify-center text-gray-300"><i class="fa-solid fa-chevron-left text-sm"></i></span>
                <?php endif; ?>

                <span class="text-[15px] font-bold text-gray-800 tracking-tight">
                    <?= date('F Y', strtotime($start_date)) ?>
                </span>

                <?php if($next_month): ?>
                    <a href="?m=<?= $next_month ?>" class="w-8 h-8 flex items-center justify-center bg-[#f4f5f9] rounded-lg text-gray-500 hover:text-gray-800 transition">
                        <i class="fa-solid fa-chevron-right text-sm"></i>
                    </a>
                <?php else: ?>
                    <span class="w-8 h-8 flex items-center justify-center text-gray-300"><i class="fa-solid fa-chevron-right text-sm"></i></span>
                <?php endif; ?>
            </div>

            <!-- Stats Dashboard Card -->
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-5">
                
                <!-- Main Days Row -->
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="bg-[#f0f3f8] p-3 rounded-lg flex justify-between items-center border border-gray-50">
                        <span class="text-[12px] text-gray-500 font-medium">Paid Days</span>
                        <span class="font-bold text-lg text-gray-800"><?= htmlspecialchars($total_paid_days) ?></span>
                    </div>
                    <div class="bg-[#f0f3f8] p-3 rounded-lg flex justify-between items-center border border-gray-50">
                        <span class="text-[12px] text-gray-500 font-medium">Unpaid Days</span>
                        <span class="font-bold text-lg text-gray-800"><?= htmlspecialchars($total_unpaid_days) ?></span>
                    </div>
                </div>

                <!-- Hours Breakdown Row -->
                <div class="grid grid-cols-4 gap-2 text-center">
                    <div class="bg-[#f4f5f9] p-2.5 rounded-lg flex flex-col justify-center">
                        <span class="text-[10px] text-gray-500 font-medium mb-0.5">Worked</span>
                        <span class="font-bold text-[15px] leading-tight text-gray-800"><?= htmlspecialchars($str_worked_hrs) ?></span>
                    </div>
                    <div class="bg-[#fcead8] p-2.5 rounded-lg flex flex-col justify-center border border-[#fae0c8]">
                        <span class="text-[10px] text-[#b87638] font-medium mb-0.5">Early Out</span>
                        <span class="font-bold text-[15px] leading-tight text-[#d97706]"><?= htmlspecialchars($str_early_hrs) ?></span>
                    </div>
                    <div class="bg-[#fef9c3] p-2.5 rounded-lg flex flex-col justify-center border border-[#fef08a]">
                        <span class="text-[10px] text-[#a16207] font-medium mb-0.5">Late In</span>
                        <span class="font-bold text-[15px] leading-tight text-[#ca8a04]"><?= htmlspecialchars($str_late_hrs) ?></span>
                    </div>
                    <div class="bg-[#fee2e2] p-2.5 rounded-lg flex flex-col justify-center border border-[#fecaca]">
                        <span class="text-[10px] text-[#b91c1c] font-medium mb-0.5">Short Hrs</span>
                        <span class="font-bold text-[15px] leading-tight text-[#ef4444]"><?= htmlspecialchars($str_short_hrs) ?></span>
                    </div>
                </div>
            </div>

            <!-- Scrollable Filter Pills -->
            <div class="flex gap-2.5 overflow-x-auto no-scrollbar mb-5 pb-1 px-1" id="filter-buttons">
                <button data-filter="All" class="filter-btn tab-active px-5 py-2 rounded-full whitespace-nowrap shadow-sm text-[12px] font-semibold tracking-wide border transition-all">
                    All (<?= count($time_entries) ?>)
                </button>
                <button data-filter="Present" class="filter-btn tab-inactive px-5 py-2 rounded-full whitespace-nowrap shadow-sm text-[12px] font-medium hover:bg-gray-50 border transition-all">
                    Present
                </button>
                <button data-filter="Absent" class="filter-btn tab-inactive px-5 py-2 rounded-full whitespace-nowrap shadow-sm text-[12px] font-medium hover:bg-gray-50 border transition-all">
                    Absent
                </button>
                <button data-filter="Week Off" class="filter-btn tab-inactive px-5 py-2 rounded-full whitespace-nowrap shadow-sm text-[12px] font-medium hover:bg-gray-50 border transition-all">
                    Week Off
                </button>
            </div>

            <?php if(!empty($time_entries)): ?>
            
            <!-- List of Entries -->
            <div class="space-y-3" id="attendance-list">
                <?php 
                    $reversed_entries = array_reverse($time_entries);
                    foreach ($reversed_entries as $entry): 
                        $date_time = strtotime($entry['entry_date']);
                        $day_num = date('d', $date_time);
                        $day_str = date('D', $date_time);
                        
                        // Style determination based on status
                        $status_val = $entry['day_status_1'] ?? '';
                        if($status_val === 'P' || $status_val === 'PP') $status_val = 'Present';
                        if($status_val === 'A' || $status_val === 'AA') $status_val = 'Absent';

                        // Check if day_status_1 and day_status_2 differ for display (e.g. Absent/Present)
                        $status_val_2 = $entry['day_status_2'] ?? '';
                        if($status_val_2 === 'P' || $status_val_2 === 'PP') $status_val_2 = 'Present';
                        if($status_val_2 === 'A' || $status_val_2 === 'AA') $status_val_2 = 'Absent';
                        
                        $display_status = $status_val;
                        if ($status_val !== $status_val_2 && !empty($status_val_2) && $status_val_2 !== '-') {
                            $display_status = $status_val . '/' . $status_val_2;
                        }

                        // Mobile App Theme Colors
                        $theme_bg = 'bg-[#f4f5f9]';
                        $theme_text = 'text-gray-500';
                        $status_color = 'text-gray-500';

                        if (strpos($display_status, 'Present') !== false && strpos($display_status, 'Absent') === false) {
                            $theme_bg = 'bg-[#eef9f2]';
                            $theme_text = 'text-[#16a34a]'; // Green
                            $status_color = 'text-[#16a34a]';
                        } elseif (strpos($display_status, 'Absent') !== false && strpos($display_status, 'Present') === false) {
                            $theme_bg = 'bg-[#fee2e2]';
                            $theme_text = 'text-[#dc2626]'; // Red
                            $status_color = 'text-[#dc2626]';
                        } elseif (strpos($display_status, 'Present') !== false && strpos($display_status, 'Absent') !== false) {
                            // Mixed Half Day
                            $theme_bg = 'bg-[#f4f5f9]';
                            $theme_text = 'text-gray-500'; 
                            $status_color = 'text-gray-500';
                        } elseif ($status_val === 'WO' || $status_val === 'Week Off') {
                            $theme_bg = 'bg-[#f4f5f9]';
                            $theme_text = 'text-gray-500';
                            $display_status = 'Week Off';
                            $status_color = 'text-gray-500';
                        } elseif (in_array($status_val, ['CL', 'SL', 'PL', 'HO'])) {
                            $theme_bg = 'bg-[#fcead8]';
                            $theme_text = 'text-[#ea580c]'; // Orange
                            $status_color = 'text-[#ea580c]';
                        }

                        // Format Hours to strictly HH:MM
                        $formatted_hours = '-';
                        if (!empty($entry['hours_worked'])) {
                            if (strpos($entry['hours_worked'], ':') !== false) {
                                $parts = explode(':', $entry['hours_worked']);
                                $formatted_hours = str_pad($parts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                            } elseif (is_numeric($entry['hours_worked'])) {
                                $h = floor($entry['hours_worked']);
                                $m = round(($entry['hours_worked'] - $h) * 60);
                                $formatted_hours = sprintf("%02d:%02d", $h, $m);
                            } else {
                                $formatted_hours = $entry['hours_worked'];
                            }
                        }
                    ?>
                <!-- Attendance Card (Added data-status attribute for JS filtering) -->
                <a href="time_entry?date=<?= $entry['entry_date'] ?>" data-status="<?= $status_val ?>" class="attendance-card block bg-white rounded-xl shadow-sm border border-gray-100 p-3 flex items-start gap-4 hover:shadow-md transition duration-200">
                    
                    <!-- Left Date Box -->
                    <div class="<?= $theme_bg ?> rounded-lg w-14 h-14 flex flex-col items-center justify-center shrink-0 border border-white/50 mt-1">
                        <span class="<?= $theme_text ?> text-[18px] font-bold leading-none mb-0.5"><?= $day_num ?></span>
                        <span class="<?= $theme_text ?> text-[10px] font-semibold uppercase tracking-wider"><?= $day_str ?></span>
                    </div>

                    <!-- Right Info -->
                    <div class="flex-1 pr-1">
                        <div class="font-bold text-[13px] mb-2 <?= $status_color ?>"><?= $display_status ?></div>

                        <div class="flex justify-between items-end">
                            <!-- Check In -->
                            <div>
                                <div class="font-bold text-gray-800 text-[13px] leading-none mb-1">
                                    <?= !empty($entry['check_in_time']) ? date('h:i A', strtotime($entry['check_in_time'])) : '-' ?>
                                </div>
                                <div class="text-[10px] text-gray-400 font-medium tracking-wide">Check In</div>
                            </div>
                            <!-- Check Out -->
                            <div>
                                <div class="font-bold text-gray-800 text-[13px] leading-none mb-1">
                                    <?= !empty($entry['check_out_time']) ? date('h:i A', strtotime($entry['check_out_time'])) : '-' ?>
                                </div>
                                <div class="text-[10px] text-gray-400 font-medium tracking-wide">Check Out</div>
                            </div>
                            <!-- Total Hours -->
                            <div class="text-right">
                                <div class="font-bold text-gray-800 text-[13px] leading-none mb-1">
                                    <?= $formatted_hours ?>
                                </div>
                                <div class="text-[10px] text-gray-400 font-medium tracking-wide">Total Hours</div>
                            </div>
                        </div>

                        <!-- NEW: Approval Pending Messages -->
                        <?php if (!empty($entry['pending_adj'])): ?>
                            <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center gap-1">
                                <span class="text-[11px] text-gray-400 font-medium">Time Entry Raised</span>
                                <span class="text-[11px] text-[#f59e0b] font-medium">(Approval Pending)</span>
                            </div>
                        <?php elseif (!empty($entry['pending_leave'])): ?>
                            <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center gap-1">
                                <span class="text-[11px] text-gray-400 font-medium">Leave Applied</span>
                                <span class="text-[11px] text-[#f59e0b] font-medium">(Approval Pending)</span>
                            </div>
                        <?php endif; ?>

                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            
            <?php else: ?>
            <div class="text-center py-12 bg-white rounded-xl shadow-sm border border-gray-100">
                <i class="fa-regular fa-folder-open text-4xl mb-3 text-gray-300"></i>
                <p class="text-gray-500 text-sm font-medium">No records found for <?= date('F Y', strtotime($start_date)) ?>.</p>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="text-center py-12 bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="w-12 h-12 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                </div>
                <p class="text-gray-800 font-medium text-sm">Employee code is missing.</p>
                <p class="text-gray-500 text-xs mt-1">Please log in again to view attendance.</p>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- JavaScript for Filter Tabs -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const filterBtns = document.querySelectorAll('.filter-btn');
            const cards = document.querySelectorAll('.attendance-card');

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    
                    // 1. Reset all buttons to inactive state
                    filterBtns.forEach(b => {
                        b.classList.remove('tab-active', 'font-semibold');
                        b.classList.add('tab-inactive', 'font-medium');
                    });
                    
                    // 2. Set clicked button to active state
                    btn.classList.remove('tab-inactive', 'font-medium');
                    btn.classList.add('tab-active', 'font-semibold');

                    // 3. Filter the cards
                    const filterValue = btn.getAttribute('data-filter');
                    
                    cards.forEach(card => {
                        if (filterValue === 'All' || card.getAttribute('data-status') === filterValue) {
                            card.style.display = 'flex'; 
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>