<?php
ob_start();
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login']) || !isset($_SESSION['emp_id'])) {
    header('Location: ../login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$emp_id = (int)$_SESSION['emp_id'];
$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');
$current_month_name = date('M Y');

// 1. Fetch Employee Details
$emp_name = "Employee";
$emp_code = "";
$profile_photo = "../includes/assets/img/default_avatar.png";
$emp_stmt = $conn->prepare("SELECT employee_name, employee_code, profile_photo FROM employees WHERE id = ?");
if ($emp_stmt) {
    $emp_stmt->bind_param("i", $emp_id);
    $emp_stmt->execute();
    $res = $emp_stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $emp_name = $row['employee_name'];
        $emp_code = $row['employee_code'];
        $profile_photo = !empty($row['profile_photo']) ? "../" . $row['profile_photo'] : $profile_photo;
    }
    $emp_stmt->close();
}

// ==========================================
// Fetch User's App Permissions
// ==========================================
$user_permissions = [];
$perm_stmt = $conn->prepare("
    SELECT arp.permission_key 
    FROM app_registrations ar
    JOIN app_registration_permissions arp ON ar.id = arp.registration_id
    WHERE ar.employee_id = ? AND ar.is_deleted = 0 AND ar.status = 'Active'
");
if ($perm_stmt) {
    $perm_stmt->bind_param("i", $emp_id);
    $perm_stmt->execute();
    $res = $perm_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $user_permissions[] = $row['permission_key'];
    }
    $perm_stmt->close();
}

// 2. Fetch Today's Attendance (Check-out time)
$checkout_time = "--:-- --";
$checkout_status = "Not checked out yet";
$att_stmt = $conn->prepare("SELECT check_in_time FROM time_entries WHERE employee_id = ? AND entry_date = ? LIMIT 1");
if ($att_stmt) {
    $att_stmt->bind_param("is", $emp_id, $today);
    $att_stmt->execute();
    $res = $att_stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['check_in_time']) && $row['check_in_time'] != '00:00:00') {
            $checkout_time = date('h:i A', strtotime($row['check_in_time']));
            $checkout_status = "You've successfully checked out today";
        }
    }
    $att_stmt->close();
}

// 3. Fetch Monthly Attendance Stats
$paid_days = 0;
$unpaid_days = 0;
$short_hrs = "0:00";
$stats_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN day_status_1 IN ('Present', 'P', 'WO', 'H', 'Half Day') THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN day_status_1 IN ('Absent', 'A', 'LWP') THEN 1 ELSE 0 END) as unpaid_count,
        SEC_TO_TIME(SUM(TIME_TO_SEC(under_time_hours))) as total_short_time
    FROM time_entries 
    WHERE employee_id = ? AND MONTH(entry_date) = ? AND YEAR(entry_date) = ?
");
if ($stats_stmt) {
    $stats_stmt->bind_param("iii", $emp_id, $current_month, $current_year);
    $stats_stmt->execute();
    $res = $stats_stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $paid_days = floatval($row['paid_count']);
        $unpaid_days = floatval($row['unpaid_count']);
        if (!empty($row['total_short_time'])) {
            $time_parts = explode(':', $row['total_short_time']);
            $short_hrs = (int)$time_parts[0] . ':' . $time_parts[1];
        }
    }
    $stats_stmt->close();
}

// 4. Fetch Leave Balances
$total_leave_balance = 0;
$leaves = [];
$leave_stmt = $conn->prepare("SELECT leave_name, balance, accumulated FROM leave_accumulations WHERE emp_id = ?");
if ($leave_stmt) {
    $leave_stmt->bind_param("i", $emp_id);
    $leave_stmt->execute();
    $res = $leave_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $total_leave_balance += floatval($row['balance']);
        $leaves[] = $row;
    }
    $leave_stmt->close();
}

// 5. Fetch Upcoming Holidays
$upcoming_holidays = [];
$hol_stmt = $conn->prepare("SELECT holiday_name, start_date FROM att_holidays WHERE start_date >= ? ORDER BY start_date ASC LIMIT 10");
if ($hol_stmt) {
    $hol_stmt->bind_param("s", $today);
    $hol_stmt->execute();
    $res = $hol_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $upcoming_holidays[] = $row;
    }
    $hol_stmt->close();
}

// ==========================================
// Define All Quick Actions configuration
// ==========================================
$quick_actions = [
    ['perm' => 'Check In/Out',               'icon' => 'fa-clock',                 'url' => 'CheckInOut',                 'label' => 'Check In / Out'],
    ['perm' => 'Apply Leave',                'icon' => 'fa-umbrella-beach',        'url' => 'ApplyLeave',        'label' => 'Apply Leave'],
    ['perm' => 'My Approvals',               'icon' => 'fa-file-signature',        'url' => 'MyApprovals',       'label' => 'My Approvals'],
    ['perm' => 'View Attendance Logs',       'icon' => 'fa-clipboard-list',        'url' => 'AttendanceOverview','label' => 'Attendance Logs'],
    ['perm' => 'View Payslip',               'icon' => 'fa-envelope-open-text',    'url' => 'Payslips',          'label' => 'Payslips'],
    ['perm' => 'Apply Reimbursement',        'icon' => 'fa-hand-holding-dollar',   'url' => 'Reimbursements',    'label' => 'Reimbursements'],
    ['perm' => 'Search Employee',            'icon' => 'fa-users-viewfinder',      'url' => 'SearchEmployee',    'label' => 'Search Employee'],
    ['perm' => 'Raise TimeEntries Request',  'icon' => 'fa-file-circle-plus',      'url' => 'time_entry',        'label' => 'Create Request'],
    ['perm' => 'Time Off Request',           'icon' => 'fa-file-circle-question',  'url' => 'MyRequest',         'label' => 'Time Off Request'],
    ['perm' => 'Allow Employee Visit',       'icon' => 'fa-address-card',          'url' => 'EmployeeVisit',     'label' => 'Employee Visit'],
    ['perm' => 'Allow Employee Visit',       'icon' => 'fa-list-check',            'url' => 'VisitLogs',         'label' => 'Visit Logs'],
    ['perm' => 'View Taxes',                 'icon' => 'fa-file-invoice-dollar',   'url' => 'Tax',               'label' => 'Tax'],
    ['perm' => 'Can Access Tasks',           'icon' => 'fa-list-check',            'url' => 'Tasks',             'label' => 'My Tasks'],
    ['perm' => 'Can Access Time Sheet',      'icon' => 'fa-calendar-days',         'url' => 'TimeSheet',         'label' => 'Time Sheet'],
    ['perm' => 'Can Manage Task Management', 'icon' => 'fa-bars-progress',         'url' => 'TaskManagement',    'label' => 'Manage Tasks'],
    ['perm' => 'Can View Employee Document', 'icon' => 'fa-file-pdf',              'url' => 'EmployeeDocuments', 'label' => 'Emp Documents'],
    ['perm' => 'Can Add Employee Document',  'icon' => 'fa-file-arrow-up',         'url' => 'AddDocument',       'label' => 'Upload Docs'],
    ['perm' => 'Can View form 16',           'icon' => 'fa-file-invoice',          'url' => 'Form16',            'label' => 'Form 16'],
    ['perm' => 'ALWAYS',                     'icon' => 'fa-shield-halved',         'url' => 'Policies',          'label' => 'Policies & Links']
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhythm Dashboard - Profile & Slider</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <link rel="icon" type="image/png" sizes="32x32" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .thin-scrollbar::-webkit-scrollbar { width: 4px; }
        .thin-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .thin-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .cursor-grab { cursor: grab; }
        .cursor-grabbing { cursor: grabbing !important; }
    </style>
</head>

<body class="bg-gray-100 flex justify-center min-h-screen">

    <div class="w-full max-w-md bg-[#f4f5f9] min-h-screen relative flex flex-col font-sans shadow-2xl overflow-hidden">

        <!-- DASHBOARD SCREEN -->
        <div id="dashboard-screen" class="flex flex-col w-full min-h-screen h-full transition-opacity duration-300">
            <!-- Header Section -->
            <header class="bg-[#1c212d] text-white flex justify-between items-center px-4 py-3 sticky top-0 z-10">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                    <div style="width:36px;height:36px;background:#ffe000;border-radius:8px;display:flex;align-items:center;justify-content:center">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#12132A" stroke-width="2.5">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <div>
                        <div style="color:#fff;font-weight:700;font-size:16px">Rhythm</div>
                        <div style="color:#6B6F8E;font-size:10px;letter-spacing:1px">PAYROLL · HR</div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="text-yellow-400 text-lg"><i class="fa-regular fa-bell"></i></button>
                    <button class="text-yellow-400 text-lg"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <div onclick="openSettings()" class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center overflow-hidden cursor-pointer hover:bg-gray-200 transition">
                        <!-- profile -->
                        <img src="<?= $profile_photo ?>" alt="Profile" class="w-full h-full object-cover">
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto no-scrollbar pb-6 flex flex-col">
                <!-- SLIDER SECTION -->
                <div class="w-full relative pt-4">
                    <div id="card-slider" class="flex overflow-x-auto snap-x snap-mandatory no-scrollbar w-full pb-2 cursor-grab">
                        
                        <!-- Card 1: Attendance/Checkout -->
                        <div class="min-w-full flex-shrink-0 snap-center px-4">
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 h-full select-none">
                                <div class="flex justify-between items-center mb-4">
                                    <h2 class="text-gray-800 font-semibold text-lg">Hi <?= htmlspecialchars($emp_name) ?> 👋</h2>
                                    <span class="text-gray-400 text-sm font-medium"><?= htmlspecialchars($current_month_name) ?></span>
                                </div>
                                <div class="bg-[#f0f3f8] rounded-lg p-4 mb-4">
                                    <p class="text-gray-600 text-sm mb-2"><?= htmlspecialchars($checkout_status) ?></p>
                                    <div class="flex justify-between items-end">
                                        <span class="text-3xl font-bold text-gray-800 tracking-tight"><?= htmlspecialchars($checkout_time) ?></span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="bg-[#f4f5f9] rounded-lg p-3 text-center flex flex-col justify-center">
                                        <span class="text-gray-500 text-xs font-medium mb-1">Paid Days</span>
                                        <span class="text-gray-800 font-bold text-xl"><?= htmlspecialchars($paid_days) ?></span>
                                    </div>
                                    <div class="bg-[#f4f5f9] rounded-lg p-3 text-center flex flex-col justify-center">
                                        <span class="text-gray-500 text-xs font-medium mb-1">Unpaid Days</span>
                                        <span class="text-gray-800 font-bold text-xl"><?= htmlspecialchars($unpaid_days) ?></span>
                                    </div>
                                    <div class="bg-[#ffeeeb] rounded-lg p-3 text-center flex flex-col justify-center border border-red-100">
                                        <span class="text-gray-500 text-xs font-medium mb-1">Short Hrs</span>
                                        <span class="text-red-500 font-bold text-xl"><?= htmlspecialchars($short_hrs) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Leave Balance -->
                        <div class="min-w-full flex-shrink-0 snap-center px-4">
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 h-full select-none">
                                <h2 class="text-gray-800 font-bold text-[15px] mb-5 flex items-center">
                                    Total Leave Balance - <?= number_format($total_leave_balance, 2) ?> <i class="fa-solid fa-chevron-right text-gray-400 text-xs ml-1 mt-0.5"></i>
                                </h2>
                                
                                <div class="max-h-[160px] overflow-y-auto thin-scrollbar pr-2">
                                    <?php if (empty($leaves)): ?>
                                        <p class="text-sm text-gray-500 text-center py-4">No leave balances found.</p>
                                    <?php else: ?>
                                        <?php foreach ($leaves as $index => $leave): 
                                            $bg_colors = ['bg-[#fcead8]', 'bg-[#e3eef9]', 'bg-[#eef9f2]'];
                                            $fill_colors = ['bg-[#f7c28a]', 'bg-[#66aef1]', 'bg-[#6ee7b7]'];
                                            $color_idx = $index % 3;
                                            
                                            $acc = floatval($leave['accumulated']) > 0 ? floatval($leave['accumulated']) : 1; 
                                            $bal = floatval($leave['balance']);
                                            $pct = ($bal / $acc) * 100;
                                            $pct = $pct > 100 ? 100 : ($pct < 0 ? 0 : $pct);
                                        ?>
                                        <div class="mb-4 last:mb-0">
                                            <div class="flex justify-between text-[13px] mb-1.5">
                                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($leave['leave_name']) ?></span>
                                                <span class="font-bold text-gray-800"><?= number_format($bal, 2) ?> <span class="text-gray-400 font-normal">/<?= number_format($leave['accumulated'], 2) ?></span></span>
                                            </div>
                                            <div class="w-full <?= $bg_colors[$color_idx] ?> rounded-full h-[9px]">
                                                <div class="<?= $fill_colors[$color_idx] ?> h-[9px] rounded-full transition-all duration-500" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Upcoming Holidays -->
                        <div class="min-w-full flex-shrink-0 snap-center px-4">
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 h-full select-none flex flex-col">
                                <h2 class="text-gray-800 font-bold text-[15px] mb-4 flex items-center shrink-0">
                                    Upcoming Holidays <i class="fa-solid fa-chevron-right text-gray-400 text-xs ml-1 mt-0.5"></i>
                                </h2>
                                <div class="space-y-2.5 overflow-y-auto thin-scrollbar pr-2 flex-1 max-h-[160px]">
                                    <?php if (empty($upcoming_holidays)): ?>
                                        <p class="text-sm text-gray-500 text-center py-4">No upcoming holidays scheduled.</p>
                                    <?php else: ?>
                                        <?php foreach ($upcoming_holidays as $index => $hol): 
                                            $h_date = strtotime($hol['start_date']);
                                            $day_num = date('d', $h_date);
                                            $month_str = date('F', $h_date);
                                            $day_str = date('l', $h_date);
                                            $card_bg = ['bg-[#fef8f3]', 'bg-[#eef5fa]', 'bg-[#eef9f2]'][$index % 3];
                                        ?>
                                        <div class="flex <?= $card_bg ?> rounded-lg p-2.5 items-center">
                                            <div class="flex flex-col items-center justify-center min-w-[3.5rem] border-r border-gray-200 pr-3 mr-3">
                                                <span class="text-lg font-bold text-gray-800 leading-tight"><?= $day_num ?></span>
                                                <span class="text-[11px] text-gray-400"><?= $month_str ?></span>
                                            </div>
                                            <div>
                                                <div class="text-[12px] text-gray-500 mb-0.5"><?= $day_str ?></div>
                                                <div class="text-[14px] font-bold text-gray-800"><?= htmlspecialchars($hol['holiday_name']) ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card 4: Cheers To Peers -->
                        <div class="min-w-full flex-shrink-0 snap-center px-4">
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 h-full flex flex-col select-none">
                                <h2 class="text-gray-800 font-bold text-[15px] mb-6 flex items-center">
                                    Cheers To Peers <i class="fa-solid fa-chevron-right text-gray-400 text-xs ml-1 mt-0.5"></i>
                                </h2>
                                <div class="flex flex-col items-center justify-center flex-1 pb-2">
                                    <div class="flex -space-x-3 mb-5 items-center">
                                        <div class="w-12 h-12 rounded-full bg-gray-300 border-[3px] border-white flex items-center justify-center text-gray-50 z-10"><i class="fa-solid fa-user text-xl"></i></div>
                                        <div class="w-16 h-16 rounded-full bg-[#8ba4c3] border-[3px] border-white flex items-center justify-center text-gray-50 z-20 shadow-md"><i class="fa-solid fa-user text-3xl opacity-80"></i></div>
                                        <div class="w-12 h-12 rounded-full bg-gray-300 border-[3px] border-white flex items-center justify-center text-gray-50 z-10"><i class="fa-solid fa-user text-xl"></i></div>
                                    </div>
                                    <p class="text-center text-gray-800 text-[14px] px-2 leading-snug">
                                        Celebrate your peers on their birthdays and work anniversaries.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Dots -->
                    <div class="flex justify-center space-x-1.5 mt-2 mb-2" id="slider-dots">
                        <div class="dot w-[5px] h-[5px] rounded-full bg-gray-500 transition-colors duration-200"></div>
                        <div class="dot w-[5px] h-[5px] rounded-full bg-gray-300 transition-colors duration-200"></div>
                        <div class="dot w-[5px] h-[5px] rounded-full bg-gray-300 transition-colors duration-200"></div>
                        <div class="dot w-[5px] h-[5px] rounded-full bg-gray-300 transition-colors duration-200"></div>
                    </div>
                </div>

                <!-- QUICK ACTIONS SECTION -->
                <div class="bg-white rounded-t-3xl shadow-[0_-4px_10px_-2px_rgba(0,0,0,0.03)] mt-2 px-5 pt-2 pb-8 flex-1">
                    <div class="w-full flex justify-center mb-4">
                        <i class="fa-solid fa-angle-up text-[#d1d5df] text-2xl"></i>
                    </div>
                    <h3 class="text-gray-800 font-semibold mb-6">Quick Actions</h3>
                    
                    <div class="grid grid-cols-3 gap-y-7 gap-x-2">
                        
                        <?php 
                        foreach ($quick_actions as $action): 
                            
                            $has_access = ($action['perm'] === 'ALWAYS') || in_array($action['perm'], $user_permissions);
                            
                            $href = $has_access ? $action['url'] : '#';
                            $escaped_label = addslashes($action['label']);
                            $onclick = $has_access ? '' : "onclick=\"showAccessDenied('{$escaped_label}'); return false;\"";
                            
                            $opacityClass = $has_access ? 'hover:opacity-80' : 'opacity-50 cursor-not-allowed';
                        ?>
                        <a href="<?= htmlspecialchars($href) ?>" <?= $onclick ?> class="flex flex-col items-center transition <?= $opacityClass ?>">
                            <div class="w-[50px] h-[50px] bg-[#f4f5f9] rounded-full flex items-center justify-center mb-2 text-[#5c6e8a] text-lg">
                                <i class="fa-solid <?= $action['icon'] ?>"></i>
                            </div>
                            <span class="text-[11px] text-gray-800 font-medium text-center"><?= htmlspecialchars($action['label']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>

        <!-- SETTINGS / PROFILE SCREEN -->
        <div id="settings-screen" class="hidden flex-col w-full min-h-screen bg-[#f4f5f9] absolute inset-0 z-20">
            <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[60px]">
                <button onclick="closeSettings()" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition">
                    <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
                </button>
                <div class="flex-1 flex justify-center mr-[80px]"> 
                    <h1 class="font-semibold text-[17px]">Settings</h1>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto pb-8">
                <!-- User Profile Card -->
                <a href="#" class="block bg-white p-5 flex items-center justify-between border-b border-gray-100 shadow-sm hover:bg-gray-50 transition mb-4">
                    <div class="flex items-center">
                        <div class="w-[52px] h-[52px] rounded-full bg-[#e4e6eb] flex items-center justify-center text-gray-400 text-3xl mr-4 overflow-hidden">
                            <img src="<?= $profile_photo ?>" alt="Profile" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <h2 class="text-gray-900 font-medium text-[16px]"><?= htmlspecialchars($emp_name) ?></h2>
                            <p class="text-gray-400 text-[13px] mt-0.5"><?= htmlspecialchars($emp_code) ?></p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-800 text-sm"></i>
                </a>

                <div class="px-4 space-y-3">
                    <div class="bg-white p-4 rounded-[10px] shadow-sm flex items-center justify-between cursor-pointer">
                        <div class="flex items-center">
                            <div class="w-6 text-center mr-3"><i class="fa-solid fa-camera text-gray-800 text-lg"></i></div>
                            <span class="text-gray-900 font-medium text-[15px]">Fast Check-In</span>
                        </div>
                        <div class="w-12 h-[26px] bg-[#cfd1d8] rounded-full relative">
                            <div class="w-5 h-5 bg-white rounded-full absolute left-[3px] top-[3px] shadow-sm"></div>
                        </div>
                    </div>

                    <a href="#" class="block bg-white p-4 rounded-[10px] shadow-sm flex items-center hover:bg-gray-50 transition">
                        <div class="w-6 text-center mr-3"><i class="fa-solid fa-sitemap text-gray-800 text-lg"></i></div>
                        <span class="text-gray-900 font-medium text-[15px]">Organization Chart</span>
                    </a>

                    <a href="#" class="block bg-white p-4 rounded-[10px] shadow-sm flex items-center hover:bg-gray-50 transition">
                        <div class="w-6 text-center mr-3"><i class="fa-solid fa-shield-halved text-gray-800 text-lg"></i></div>
                        <span class="text-gray-900 font-medium text-[15px]">Change Password</span>
                    </a>

                    <a href="#" class="block bg-white p-4 rounded-[10px] shadow-sm flex items-center hover:bg-gray-50 transition">
                        <div class="w-6 text-center mr-3"><i class="fa-solid fa-lock text-gray-800 text-lg"></i></div>
                        <span class="text-gray-900 font-medium text-[15px]">Set Payslip Pin</span>
                    </a>

                    <a href="#" class="block bg-white p-4 rounded-[10px] shadow-sm flex items-center hover:bg-gray-50 transition">
                        <div class="w-6 text-center mr-3"><i class="fa-solid fa-circle-question text-gray-800 text-lg"></i></div>
                        <span class="text-gray-900 font-medium text-[15px]">Support</span>
                    </a>

                    <a href="#" class="block bg-white p-4 rounded-[10px] shadow-sm flex items-center hover:bg-gray-50 transition">
                        <div class="w-6 text-center mr-3"><i class="fa-solid fa-share-nodes text-gray-800 text-lg"></i></div>
                        <span class="text-gray-900 font-medium text-[15px]">Share</span>
                    </a>

                    <a href="#" class="block bg-white p-4 rounded-[10px] shadow-sm flex items-center hover:bg-gray-50 transition">
                        <div class="w-6 text-center mr-3"><i class="fa-solid fa-circle-info text-gray-800 text-lg"></i></div>
                        <span class="text-gray-900 font-medium text-[15px]">About Rhythm Payroll</span>
                    </a>

                    <a href="logout" class="block bg-white p-4 rounded-[10px] shadow-sm flex items-center text-red-600 hover:bg-red-50 transition">
                        <div class="w-6 text-center mr-3"><i class="fa-solid fa-arrow-right-from-bracket text-red-600 text-lg"></i></div>
                        <span class="font-medium text-[15px]">Logout</span>
                    </a>
                </div>

                <div class="text-center text-gray-500 text-[13px] py-8">
                    Made with <i class="fa-solid fa-heart text-gray-600 mx-1"></i> in India
                </div>
            </main>
        </div>
    </div>

    <!-- Script to handle interactions -->
    <script>
        // SweetAlert Handler for restricted actions
        function showAccessDenied(actionName) {
            Swal.fire({
                icon: 'lock', // Using custom icon or standard sweet alert icons like 'error' / 'warning'
                title: 'Access Restricted',
                text: 'You do not have permission to access ' + actionName + '.',
                confirmButtonColor: '#1c212d',
                confirmButtonText: 'Okay',
                iconColor: '#5c6e8a',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-lg px-6'
                }
            });
        }

        function openSettings() {
            document.getElementById('dashboard-screen').classList.add('hidden');
            document.getElementById('dashboard-screen').classList.remove('flex');
            
            document.getElementById('settings-screen').classList.remove('hidden');
            document.getElementById('settings-screen').classList.add('flex');
        }

        function closeSettings() {
            document.getElementById('settings-screen').classList.add('hidden');
            document.getElementById('settings-screen').classList.remove('flex');
            
            document.getElementById('dashboard-screen').classList.remove('hidden');
            document.getElementById('dashboard-screen').classList.add('flex');
        }

        const slider = document.getElementById('card-slider');
        const dots = document.querySelectorAll('.dot');

        slider.addEventListener('scroll', () => {
            const scrollPosition = slider.scrollLeft;
            const slideWidth = slider.clientWidth;
            const activeIndex = Math.round(scrollPosition / slideWidth);

            dots.forEach((dot, index) => {
                if (index === activeIndex) {
                    dot.classList.remove('bg-gray-300');
                    dot.classList.add('bg-gray-500');
                } else {
                    dot.classList.remove('bg-gray-500');
                    dot.classList.add('bg-gray-300');
                }
            });
        });

        // Drag to Scroll functionality
        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            slider.classList.add('cursor-grabbing');
            slider.classList.remove('snap-mandatory');
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });

        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.classList.remove('cursor-grabbing');
            slider.classList.add('snap-mandatory');
        });

        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.classList.remove('cursor-grabbing');
            slider.classList.add('snap-mandatory');
        });

        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2; 
            slider.scrollLeft = scrollLeft - walk;
        });
    </script>
</body>

</html>