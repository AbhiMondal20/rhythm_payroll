<?php
ob_start();
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/config.php';
require_once 'includes/db_client.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$now = date('Y-m-d H:i:s');
// ========================================================================
// AJAX SEARCH ENDPOINT FOR EMPLOYEES
// ========================================================================
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $search = '%' . $_GET['ajax_search'] . '%';
    $stmt = $conn->prepare("SELECT id, employee_name, employee_code FROM employees WHERE employee_name LIKE ? OR employee_code LIKE ? LIMIT 10");
    $data = [];
    if ($stmt) {
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    }
    echo json_encode($data);
    exit();
}

// ========================================================================
// AJAX ENDPOINT TO CANCEL/REJECT SELECTED LEAVES
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_leaves') {
    header('Content-Type: application/json');
    $ids = json_decode($_POST['ids'] ?? '[]');
    if (is_array($ids) && count($ids) > 0) {
        // Sanitize IDs
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        
        $stmt = $conn->prepare("UPDATE leave_requests SET status = 'rejected' WHERE id IN ($placeholders)");
        if ($stmt) {
            $stmt->bind_param($types, ...$ids);
            if($stmt->execute()) {
                echo json_encode(['success' => true]);
                $stmt->close();
                exit();
            }
            $stmt->close();
        }
    }
    echo json_encode(['success' => false]);
    exit();
}

// ========================================================================
// SECURITY CHECK: Verify if the employee has 'approval_leave' access = 1
// ========================================================================
$logged_in_emp_id = (int)($_SESSION['emp_id'] ?? 0); 

if ($logged_in_emp_id > 0) {
    $stmt = $conn->prepare("SELECT approval_leave FROM employees WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $logged_in_emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ((int)$row['approval_leave'] !== 1) {
                // Deny access if approval_leave is 0 or NULL
                die("
                <div style='display:flex; height:100vh; width:100%; align-items:center; justify-content:center; font-family:sans-serif; background-color:#f8fafc; color:#1e293b;'>
                    <div style='text-align:center; background:#fff; padding:40px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);'>
                        <h2 style='color:#ef4444; margin-top:0;'>Access Denied</h2>
                        <p style='color:#64748b; margin-bottom:20px;'>You do not have the required permissions to access the Leave Management module.</p>
                        <a href='index.php' style='display:inline-block; padding:10px 20px; background:#2563eb; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;'>Return to Dashboard</a>
                    </div>
                </div>");
            }
        }
        $stmt->close();
    }
}

// ========================================================================
// ENSURE LEAVE REQUESTS TABLE EXISTS
// ========================================================================
$conn->query("
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id INT NOT NULL,
    emp_code VARCHAR(50) NULL, 
    leave_type_id INT NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    day_type VARCHAR(20) DEFAULT 'Full Day',
    reason TEXT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_emp_id (emp_id),
    INDEX idx_leave_type_id (leave_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ========================================================================
// HANDLE POST ACTIONS (Apply Leave)
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_leave') {
    $e_id = (int)$_POST['emp_id'];
    $lt_id = (int)$_POST['leave_type_id'];
    $from = $_POST['from_date'];
    $to = $_POST['to_date'];
    $day_type = isset($_POST['is_half_day']) ? 'Half Day' : 'Full Day';
    $reason = $_POST['reason'];

    // FIX: Avoid fatal errors by safely fetching associative arrays
    $emp_res = $conn->query("SELECT employee_code, employee_name FROM employees WHERE id = $e_id");
    $emp_data = $emp_res ? $emp_res->fetch_assoc() : [];
    $emp_code = $emp_data['employee_code'] ?? '';
    $emp_name = $emp_data['employee_name'] ?? '';

    $lt_res = $conn->query("SELECT leave_code, leave_name FROM leave_types WHERE id = $lt_id");
    $lt_data = $lt_res ? $lt_res->fetch_assoc() : [];
    $leave_code = $lt_data['leave_code'] ?? '';
    $leave_name = $lt_data['leave_name'] ?? '';

    // Proceed with Insert now that $emp_code is defined safely
    $ins = $conn->prepare("INSERT INTO leave_requests (emp_id, emp_code, leave_type_id, from_date, to_date, day_type, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?,'pending')");
    if ($ins) {
        $ins->bind_param("isissss", $e_id, $emp_code, $lt_id, $from, $to, $day_type, $reason);
        $ins->execute();
        $ins->close();

        // Safely insert into approval_requests
        $src = $conn->prepare("INSERT INTO `approval_requests`(`emp_code`, `emp_name`, `type`, `stage`, `request_date`, `requested_on`, `shift_date`, `leave_type`, `reasons`, `status`, `created_at`) VALUES (?, ?, 'leave', 'Stage_1', ?, ?, ?, ?, ?, 'pending', ?)");
        if ($src) {
            $src->bind_param("ssssssss", $emp_code, $emp_name, $from, $now, $from, $leave_name, $reason, $now);
            $src->execute();
            $src->close();
        }

        $_SESSION['lv_flash'] = "Leave applied successfully on behalf of employee!";
        header("Location: ?tab=calendar");
        exit();
    }
}
$flash_message = $_SESSION['lv_flash'] ?? '';
unset($_SESSION['lv_flash']);

// ========================================================================
// SETUP DATE AND TABS
// ========================================================================
$page_title = 'Leave Management';
$active_tab = $_GET['tab'] ?? 'insights';

$cal_year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$cal_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');

if ($cal_month < 1)  { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1;  $cal_year++; }

$hist_year  = isset($_GET['hy']) ? (int)$_GET['hy'] : (int)date('Y');
$hist_month = isset($_GET['hm']) ? (int)$_GET['hm'] : (int)date('m');

if ($hist_month < 1)  { $hist_month = 12; $hist_year--; }
if ($hist_month > 12) { $hist_month = 1;  $hist_year++; }

function esc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function month_name($m) {
    return date('M', mktime(0, 0, 0, $m, 1, 2000));
}

// ========================================================================
// FETCH DATA FROM DB
// ========================================================================
$leave_types_db = [];
$colors = ['#F59E0B', '#7C3AED', '#3B82F6', '#22C55E', '#EF4444', '#EC4899', '#06B6D4'];
$lt_res = $conn->query("SELECT id, leave_name, leave_code FROM leave_types ORDER BY leave_name ASC");
$color_index = 0;
if ($lt_res) {
    while($row = $lt_res->fetch_assoc()) {
        $row['color'] = $colors[$color_index % count($colors)];
        $row['count'] = 0; 
        $row['pct'] = 0;
        $row['up'] = true;
        $leave_types_db[$row['id']] = $row;
        $color_index++;
    }
}

// Apply Filters for Insights Tab
$insight_filter = $_GET['filter'] ?? 'this_month';
$start_date_filter = date('Y-m-01');
$end_date_filter = date('Y-m-t');

if ($insight_filter === 'last_month') {
    $start_date_filter = date('Y-m-01', strtotime('first day of last month'));
    $end_date_filter = date('Y-m-t', strtotime('last day of last month'));
} elseif ($insight_filter === 'last_3_months') {
    $start_date_filter = date('Y-m-01', strtotime('-2 months'));
    $end_date_filter = date('Y-m-t');
} elseif ($insight_filter === 'this_year') {
    $start_date_filter = date('Y-01-01');
    $end_date_filter = date('Y-12-31');
}

// FIX: Exclude rejected leaves from stats and use overlap logic (from_date <= End AND to_date >= Start)
$total_leaves_month = 0;
$stats_sql = "SELECT leave_type_id, COUNT(*) as cnt FROM leave_requests WHERE from_date <= ? AND to_date >= ? AND status != 'rejected' GROUP BY leave_type_id";
$s_stmt = $conn->prepare($stats_sql);
if ($s_stmt) {
    $s_stmt->bind_param("ss", $end_date_filter, $start_date_filter);
    $s_stmt->execute();
    $s_res = $s_stmt->get_result();
    while($r = $s_res->fetch_assoc()) {
        if (isset($leave_types_db[$r['leave_type_id']])) {
            $leave_types_db[$r['leave_type_id']]['count'] = (int)$r['cnt'];
            $total_leaves_month += (int)$r['cnt'];
        }
    }
    $s_stmt->close();
}

// Calculate percentages
foreach ($leave_types_db as &$lt) {
    if ($total_leaves_month > 0) {
        $lt['pct'] = round(($lt['count'] / $total_leaves_month) * 100, 1);
    }
}
unset($lt);

// Fetch Recent Requests (Global)
$recent_requests = [];
$rec_res = $conn->query("
    SELECT lr.id, lr.from_date, lr.to_date, lr.status, lt.leave_name, lt.id as lt_id, e.employee_name,
           DATEDIFF(lr.to_date, lr.from_date) + 1 AS days
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    JOIN employees e ON lr.emp_id = e.id
    ORDER BY lr.created_at DESC LIMIT 5
");
if ($rec_res) {
    while($row = $rec_res->fetch_assoc()) {
        $row['type'] = $row['leave_name'];
        $row['from'] = date('d M', strtotime($row['from_date']));
        $row['to'] = date('d M', strtotime($row['to_date']));
        $row['color'] = $leave_types_db[$row['lt_id']]['color'] ?? '#9CA3AF';
        $recent_requests[] = $row;
    }
}

// Calendar Specific Data
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $cal_month, $cal_year);
$mock_approved = array_fill(1, $days_in_month, 0);
$mock_pending = array_fill(1, $days_in_month, 0);
$mock_rejected = array_fill(1, $days_in_month, 0);
$cal_leaves_js_db = []; 

$start_date = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$end_date = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $days_in_month);

// FIX: Added lr.id to be able to cancel requests via checkboxes
$c_sql = "
    SELECT lr.id, lr.from_date, lr.to_date, lr.status, e.employee_name, lt.leave_code 
    FROM leave_requests lr
    JOIN employees e ON lr.emp_id = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.from_date <= ? AND lr.to_date >= ?
";
$c_stmt = $conn->prepare($c_sql);
if ($c_stmt) {
    $c_stmt->bind_param("ss", $end_date, $start_date);
    $c_stmt->execute();
    $c_result = $c_stmt->get_result();
    
    while ($r = $c_result->fetch_assoc()) {
        $curr = strtotime($r['from_date']);
        $end = strtotime($r['to_date']);
        
        while ($curr <= $end) {
            $m = (int)date('m', $curr);
            $y = (int)date('Y', $curr);
            $d = (int)date('j', $curr);
            $dt_key = date('Y-m-d', $curr);
            
            if ($m === $cal_month && $y === $cal_year) {
                if ($r['status'] === 'approved') {
                    $mock_approved[$d]++;
                } elseif ($r['status'] === 'pending') {
                    $mock_pending[$d]++;
                } elseif ($r['status'] === 'rejected') {
                    $mock_rejected[$d]++;
                }
                
                if(!isset($cal_leaves_js_db[$dt_key])) { $cal_leaves_js_db[$dt_key] = []; }
                $cal_leaves_js_db[$dt_key][] = [
                    'id' => $r['id'], // passed to script
                    'name' => $r['employee_name'],
                    'type' => $r['leave_code'],
                    'status' => $r['status']
                ];
            }
            $curr = strtotime("+1 day", $curr);
        }
    }
    $c_stmt->close();
}

$calendar_leaves = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $key = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
    $calendar_leaves[$key] = [
        'approved' => $mock_approved[$d] ?? 0,
        'pending'  => $mock_pending[$d] ?? 0,
        'rejected' => $mock_rejected[$d] ?? 0,
    ];
}

$history_data = [];
if ($active_tab === 'history') {
    $h_start = sprintf('%04d-%02d-01', $hist_year, $hist_month);
    $h_end = date('Y-m-t', strtotime($h_start));
    
    $h_sql = "
        SELECT lr.*, e.employee_name, lt.leave_name, DATEDIFF(lr.to_date, lr.from_date) + 1 AS days 
        FROM leave_requests lr
        JOIN employees e ON lr.emp_id = e.id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.from_date <= ? AND lr.to_date >= ?
        ORDER BY lr.created_at DESC
    ";
    $h_stmt = $conn->prepare($h_sql);
    if($h_stmt) {
        $h_stmt->bind_param("ss", $h_end, $h_start);
        $h_stmt->execute();
        $h_res = $h_stmt->get_result();
        while($r = $h_res->fetch_assoc()) $history_data[] = $r;
        $h_stmt->close();
    }
}

// Data for Insights Charts
$donut_labels = [];
$donut_data = [];
$donut_colors = [];
foreach ($leave_types_db as $lt) {
    if ($lt['count'] > 0) {
        $donut_labels[] = $lt['leave_name'];
        $donut_data[] = $lt['count'];
        $donut_colors[] = $lt['color'];
    }
}
if(empty($donut_data)) {
    $donut_labels = ['No Leaves']; $donut_data = [1]; $donut_colors = ['#E2E8F0'];
}

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<script>
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui']
            },
            colors: {
                brand: {
                    500: '#2563EB',
                    600: '#1D4ED8'
                }
            }
        }
    }
};

// Expose dynamic calendar data to JS
const calendarLeaveDetails = <?= json_encode($cal_leaves_js_db) ?>;
const toastMessage = <?= json_encode($flash_message) ?>;
</script>

<style>
.lv-toast {
    transform: translateX(-50%) translateY(80px);
    transition: transform .3s ease;
}

.lv-toast.show {
    transform: translateX(-50%) translateY(0);
}

.lv-modal-bg {
    display: none;
}

.lv-modal-bg.open {
    display: flex;
}

@keyframes lvPop {
    from { opacity: 0; transform: scale(.97); }
    to { opacity: 1; transform: scale(1); }
}

.lv-pop {
    animation: lvPop .22s ease;
}
</style>

<div class="w-full space-y-5">

    <!-- HEADER -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Leaves
        </h1>

        <div class="flex items-center gap-0 border-b border-slate-200 dark:border-slate-700">
            <?php foreach(['insights'=>'Insights','calendar'=>'Leave Calendar','history'=>'Leave History'] as $tk=>$tl): ?>
            <a href="?tab=<?= esc($tk) ?>" class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition
                   <?= $active_tab === $tk
                       ? 'text-blue-600 border-blue-600'
                       : 'text-slate-500 border-transparent hover:text-slate-900 dark:hover:text-white' ?>">
                <?= esc($tl) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($active_tab === 'insights'): ?>

    <!-- STATS -->
    <div class="grid grid-cols-1 xl:grid-cols-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">

        <!-- Team Stats -->
        <div class="p-5 border-b xl:border-b-0 xl:border-r border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Team Leave Stats</h3>
                
                <select onchange="window.location.href='?tab=insights&filter='+this.value" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 dark:bg-slate-900 bg-white hover:border-blue-500 cursor-pointer outline-none">
                    <option value="this_month" <?= $insight_filter === 'this_month' ? 'selected' : '' ?>>This Month</option>
                    <option value="last_month" <?= $insight_filter === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                    <option value="last_3_months" <?= $insight_filter === 'last_3_months' ? 'selected' : '' ?>>Last 3 Months</option>
                    <option value="this_year" <?= $insight_filter === 'this_year' ? 'selected' : '' ?>>This Year</option>
                </select>
            </div>
            <div class="relative h-[190px]">
                <canvas id="teamStatsChart"></canvas>
            </div>
        </div>

        <!-- Overview -->
        <div class="p-5 border-b xl:border-b-0 xl:border-r border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Leaves Overview</h3>
                
                <select onchange="window.location.href='?tab=insights&filter='+this.value" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 dark:bg-slate-900 bg-white hover:border-blue-500 cursor-pointer outline-none">
                    <option value="this_month" <?= $insight_filter === 'this_month' ? 'selected' : '' ?>>This Month</option>
                    <option value="last_month" <?= $insight_filter === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                    <option value="last_3_months" <?= $insight_filter === 'last_3_months' ? 'selected' : '' ?>>Last 3 Months</option>
                    <option value="this_year" <?= $insight_filter === 'this_year' ? 'selected' : '' ?>>This Year</option>
                </select>
            </div>

            <div class="flex items-center justify-center gap-6 flex-wrap py-3">
                <div class="relative w-[130px] h-[130px] shrink-0">
                    <canvas id="overviewDonut" width="130" height="130"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-xs font-medium text-slate-400">Leaves</span>
                        <span class="text-2xl font-bold text-slate-900 dark:text-white"><?= $total_leaves_month ?></span>
                    </div>
                </div>

                <div class="space-y-3">
                    <?php foreach($leave_types_db as $lt): ?>
                        <?php if($lt['count'] > 0): ?>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0"
                                style="background:<?= esc($lt['color']) ?>"></span>
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                <?= esc($lt['leave_name']) ?> -
                            </span>
                            <span class="text-xs font-bold text-slate-900 dark:text-white">
                                <?= (int)$lt['count'] ?>
                            </span>
                            <span class="text-xs font-bold <?= $lt['up'] ? 'text-red-600' : 'text-emerald-600' ?>">
                                <?= esc($lt['pct']) ?>%
                            </span>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Leaves Taken -->
        <div class="p-5">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Leaves Taken</h3>
                
                <select onchange="window.location.href='?tab=insights&filter='+this.value" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 dark:bg-slate-900 bg-white hover:border-blue-500 cursor-pointer outline-none">
                    <option value="this_month" <?= $insight_filter === 'this_month' ? 'selected' : '' ?>>This Month</option>
                    <option value="last_month" <?= $insight_filter === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                    <option value="last_3_months" <?= $insight_filter === 'last_3_months' ? 'selected' : '' ?>>Last 3 Months</option>
                    <option value="this_year" <?= $insight_filter === 'this_year' ? 'selected' : '' ?>>This Year</option>
                </select>
            </div>

            <div class="relative h-[160px]">
                <canvas id="leavesTakenChart"></canvas>
            </div>

            <div class="flex items-center justify-center gap-3 flex-wrap pt-3">
                <?php
                    $day_colors = [
                        'Mon'=>'#3B82F6', 'Tue'=>'#22C55E', 'Wed'=>'#F59E0B',
                        'Thu'=>'#6366F1', 'Fri'=>'#EF4444', 'Sat'=>'#EC4899', 'Sun'=>'#9CA3AF'
                    ];
                    foreach($day_colors as $day=>$col):
                    ?>
                <div class="flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                    <span class="w-2.5 h-2.5 rounded-full" style="background:<?= esc($col) ?>"></span>
                    <?= esc($day) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RECENT -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Recent Leave Requests</h3>
                <a href="?tab=history" class="text-xs font-semibold text-blue-600 hover:underline">View All →</a>
            </div>

            <?php if(count($recent_requests) > 0): ?>
                <?php foreach($recent_requests as $req): ?>
                <div class="px-5 py-3 flex items-center gap-3 border-b last:border-b-0 border-slate-50 dark:border-slate-800">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:<?= esc($req['color']) ?>"></span>
                    
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200 flex-1 min-w-0 truncate">
                        <?= esc($req['employee_name']) ?> <span class="text-slate-400 font-normal ml-1">(<?= esc($req['type']) ?>)</span>
                    </span>

                    <span class="text-xs text-slate-400 whitespace-nowrap">
                        <?= esc($req['from']) ?> - <?= esc($req['to']) ?>
                    </span>

                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300 whitespace-nowrap">
                        <?= (int)$req['days'] ?> Day<?= $req['days'] > 1 ? 's' : '' ?>
                    </span>

                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs
                                <?= $req['status'] === 'pending'
                                    ? 'bg-amber-100 text-amber-700'
                                    : ($req['status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700') ?>">
                        <?= $req['status'] === 'pending' ? '⏳' : ($req['status'] === 'rejected' ? '✕' : '✓') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-5 text-center text-sm text-slate-400">No recent leave requests found.</div>
            <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Leave Request by Month</h3>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="monthToggle" class="sr-only peer" onchange="toggleMonthChart()">
                    <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[18px] after:w-[18px] after:transition-all peer-checked:after:translate-x-5">
                    </div>
                </label>
            </div>

            <div class="p-5 relative h-[230px]" id="monthChartWrap">
                <canvas id="monthReqChart"></canvas>
            </div>

            <div id="monthEmptyWrap" class="hidden">
                <div class="py-12 px-5 text-center">
                    <p class="text-sm text-slate-400">No Leave statement available!</p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($active_tab === 'calendar'): ?>
<div class="section-card m-4 p-4">
    <!-- CALENDAR HEADER -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2 text-sm font-semibold">
            <a href="?tab=insights" class="text-slate-600 dark:text-slate-300 hover:text-blue-600">Leaves</a>
            <span class="text-slate-300">›</span>
            <span class="text-slate-800 dark:text-white">Leave Calendar</span>
        </div>

        <button type="button" onclick="document.getElementById('applyOnBehalfModal').classList.add('open')"
            class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold shadow-sm hover:bg-blue-700">
            Apply on Behalf
        </button>
    </div>
    <!-- MONTH NAV -->
    <div class="inline-flex items-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden shadow-sm mb-2">
        <a href="?tab=calendar&y=<?= $cal_month === 1 ? $cal_year - 1 : $cal_year ?>&m=<?= $cal_month === 1 ? 12 : $cal_month - 1 ?>"
            class="w-10 h-10 flex items-center justify-center text-xl text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800">
            ‹
        </a>

        <span class="h-10 min-w-[140px] px-5 border-x border-slate-200 dark:border-slate-700 flex items-center justify-center text-sm font-bold text-slate-900 dark:text-white">
            <?= date('M-Y', mktime(0, 0, 0, $cal_month, 1, $cal_year)) ?>
        </span>

        <a href="?tab=calendar&y=<?= $cal_month === 12 ? $cal_year + 1 : $cal_year ?>&m=<?= $cal_month === 12 ? 1 : $cal_month + 1 ?>"
            class="w-10 h-10 flex items-center justify-center text-xl text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800">
            ›
        </a>
    </div>
    <!-- CALENDAR TABLE -->
    <div class="">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] border-collapse table-fixed">
                <thead>
                    <tr class="bg-slate-100 dark:bg-slate-800">
                        <th class="w-12 p-3 border border-slate-200 dark:border-slate-700 bg-slate-200 dark:bg-slate-700">
                        </th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            SUN</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            MON</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            TUE</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            WED</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            THU</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            FRI</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            SAT</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                        $first_day = (int)date('w', mktime(0, 0, 0, $cal_month, 1, $cal_year));
                        $total_cells = ceil(($first_day + $days_in_month) / 7) * 7;
                        $day = 1;
                        $week_num = (int)date('W', mktime(0, 0, 0, $cal_month, 1, $cal_year));
                        $today = (int)date('j');
                        $this_month = (int)date('m');
                        $this_year = (int)date('Y');

                        for ($cell = 0; $cell < $total_cells; $cell++) {
                            if ($cell % 7 === 0) {
                                echo '<tr>';
                                echo '<td class="border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-center text-sm font-bold text-slate-400">' . $week_num . '</td>';
                                $week_num++;
                            }

                            if ($cell < $first_day || $day > $days_in_month) {
                                echo '<td class="h-[96px] border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-950"></td>';
                            } else {
                                $date_key = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $day);
                                $is_today = ($day === $today && $cal_month === $this_month && $cal_year === $this_year);
                                $leaves = $calendar_leaves[$date_key] ?? ['approved'=>0,'pending'=>0,'rejected'=>0];

                                echo '<td class="h-[96px] align-top p-2 border border-slate-200 dark:border-slate-700 ' . ($is_today ? 'bg-blue-50 dark:bg-blue-950/30' : 'bg-white dark:bg-slate-900') . '">';
                                echo '<span class="block mb-1 text-sm font-bold ' . ($is_today ? 'text-blue-600' : 'text-slate-700 dark:text-slate-200') . '">' . $day . '</span>';

                                if ($leaves['pending'] > 0) {
                                    echo '<button type="button" class="lv-cal-pill block w-full mb-1 rounded-md bg-amber-500 px-2 py-1 text-[11px] font-bold text-white hover:opacity-90 truncate" data-date="' . esc($date_key) . '" data-status="pending" data-count="' . (int)$leaves['pending'] . '">' . (int)$leaves['pending'] . ' Pending</button>';
                                }

                                if ($leaves['approved'] > 0) {
                                    echo '<button type="button" class="lv-cal-pill block w-full mb-1 rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white hover:opacity-90 truncate" data-date="' . esc($date_key) . '" data-status="approved" data-count="' . (int)$leaves['approved'] . '">' . (int)$leaves['approved'] . ' Approved</button>';
                                }

                                // FIX: Added code to show Rejected Leaves button pill on the Calendar
                                if ($leaves['rejected'] > 0) {
                                    echo '<button type="button" class="lv-cal-pill block w-full mb-1 rounded-md bg-red-500 px-2 py-1 text-[11px] font-bold text-white hover:opacity-90 truncate" data-date="' . esc($date_key) . '" data-status="rejected" data-count="' . (int)$leaves['rejected'] . '">' . (int)$leaves['rejected'] . ' Rejected</button>';
                                }

                                echo '</td>';
                                $day++;
                            }

                            if ($cell % 7 === 6) {
                                echo '</tr>';
                            }
                        }
                        ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- APPLY MODAL -->
<div class="lv-modal-bg fixed inset-0 z-[600] bg-slate-950/50 backdrop-blur-sm items-center justify-center p-4"
    id="applyOnBehalfModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="lv-pop w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white dark:bg-slate-900 shadow-2xl">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Apply Leave on Behalf</h3>
            <button type="button" onclick="document.getElementById('applyOnBehalfModal').classList.remove('open')"
                class="text-2xl leading-none text-slate-400 hover:text-slate-900 dark:hover:text-white">
                ×
            </button>
        </div>
        <div class="p-6">
            <form id="applyBehalfForm" method="POST" action="?tab=calendar" class="space-y-4">
                <input type="hidden" name="action" value="apply_leave">               
                
                <!-- NEW CUSTOM SEARCH BAR -->
                <div>
                    <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Select Employee <span class="text-red-600">*</span></label>
                    <div class="relative w-full">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                            </svg>
                        </span>
                        <input type="text" id="employeeSearchInput" placeholder="Search by name or #code" autocomplete="off" required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-blue-500 transition-colors">
                        
                        <!-- Hidden input to store selected employee ID for form submission -->
                        <input type="hidden" id="hiddenEmpId" name="emp_id" required>
                        
                        <!-- Dropdown for autocomplete results -->
                        <div id="autocompleteDropdown" class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg hidden max-h-48 overflow-y-auto"></div>
                    </div>
                </div>
                <!-- Leave Type Select -->
                <div>
                    <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Leave Type <span class="text-red-600">*</span></label>
                    <select name="leave_type_id" id="typeSelect" required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                        <option value="">Select</option>
                        <?php foreach($leave_types_db as $lt): 
                            $balance = '0.00';
                            if (stripos($lt['leave_name'], 'Compensatory') !== false) {
                                $balance = '17.00';
                            } elseif (stripos($lt['leave_name'], 'Casual') !== false || stripos($lt['leave_name'], 'Sick') !== false) {
                                $balance = '14.00';
                            }
                        ?>
                            <option value="<?= esc($lt['id']) ?>"><?= esc($lt['leave_name']) ?> (<?= $balance ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Remaining fields (Date, Day Type, Reason) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">From Date <span class="text-red-600">*</span></label>
                        <input type="date" name="from_date" required value="<?= date('Y-m-d') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">To Date <span class="text-red-600">*</span></label>
                        <input type="date" name="to_date" required value="<?= date('Y-m-d') ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                    </div>
                </div>
                
                <div class="flex items-center pt-2 pb-1">
                    <label class="flex items-center gap-2 cursor-pointer text-sm font-bold text-slate-700 dark:text-slate-300 select-none">
                        <input type="checkbox" name="is_half_day" value="1" class="w-4 h-4 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-slate-800 focus:ring-2 dark:bg-slate-700 dark:border-slate-600">
                        Apply as Half Day
                    </label>
                </div>

                <div>
                    <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Reason</label>
                    <textarea name="reason" rows="3" placeholder="Reason for leave..." class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="document.getElementById('applyOnBehalfModal').classList.remove('open')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 transition-colors">Apply Leave</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DAY MODAL -->
<div class="lv-modal-bg fixed inset-0 z-[600] bg-slate-950/50 backdrop-blur-sm items-center justify-center p-4"
    id="leaveDayModal" onclick="if(event.target===this)this.classList.remove('open')">

    <div class="lv-pop w-full max-w-5xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white dark:bg-slate-900 shadow-2xl">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-4">
            <h3 id="leaveDayModalTitle" class="text-base font-bold text-slate-900 dark:text-white">Leave Requests
            </h3>
            <div id="leaveDayModalDate" class="ml-auto text-sm font-semibold text-slate-700 dark:text-slate-200">
            </div>
            <button type="button" onclick="document.getElementById('leaveDayModal').classList.remove('open')"
                class="text-2xl leading-none text-slate-400 hover:text-slate-900 dark:hover:text-white">
                ×
            </button>
        </div>
        <div class="p-6">
            <input type="text" id="leaveDaySearch" oninput="filterLeaveDayItems(this.value)"
                placeholder="Filter items"
                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">

            <label class="flex items-center gap-2 my-5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <input type="checkbox" id="leaveDaySelectAll" onchange="toggleLeaveDaySelectAll(this)"
                    class="w-4 h-4 accent-blue-600">
                Selected -
                <span id="leaveDaySelectedCount">0</span>
            </label>

            <div id="leaveDayList" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4"></div>
        </div>

        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 flex justify-end gap-2">
            <button type="button" onclick="document.getElementById('leaveDayModal').classList.remove('open')"
                class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800">
                Back
            </button>

            <button type="button" onclick="cancelSelectedLeaves()"
                class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">
                Cancel Selected
            </button>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'history'): ?>
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3 flex-wrap">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Team Leave History</h3>
        
        <!-- Functional Month Navigator -->
        <div class="inline-flex items-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden shadow-sm">
            <a href="?tab=history&hy=<?= $hist_month === 1 ? $hist_year - 1 : $hist_year ?>&hm=<?= $hist_month === 1 ? 12 : $hist_month - 1 ?>" 
                class="w-10 h-10 flex items-center justify-center text-xl text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800">
                ‹
            </a>
            
            <div class="relative flex items-center justify-center h-10 min-w-[140px] px-5 border-x border-slate-200 dark:border-slate-700">
                <input type="month" 
                       onchange="window.location.href='?tab=history&hy=' + this.value.split('-')[0] + '&hm=' + this.value.split('-')[1]"
                       value="<?= sprintf('%04d-%02d', $hist_year, $hist_month) ?>"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                <span class="text-sm font-bold text-slate-900 dark:text-white pointer-events-none">
                    <?= month_name($hist_month) . ' ' . $hist_year ?>
                </span>
            </div>

            <a href="?tab=history&hy=<?= $hist_month === 12 ? $hist_year + 1 : $hist_year ?>&hm=<?= $hist_month === 12 ? 1 : $hist_month + 1 ?>" 
                class="w-10 h-10 flex items-center justify-center text-xl text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800">
                ›
            </a>
        </div>
    </div>
    
    <div class="px-5 pt-4">
        <div class="max-w-sm flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2.5 focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/10">
            <span class="text-slate-400">⌕</span>
            <input type="text" id="histSearch" placeholder="Search team members or leave types..." 
                oninput="filterHistTable(this.value)" 
                class="w-full bg-transparent outline-none text-sm text-slate-700 dark:text-slate-200">
        </div>
    </div>
    
    <?php if(empty($history_data)): ?>
    <div class="py-16 px-6 text-center" id="histEmpty">
        <p class="text-sm text-slate-400 mb-2">No leave records found for <?= month_name($hist_month) . ' ' . $hist_year ?>.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto w-full mb-4 mt-4">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 dark:bg-slate-800 border-y border-slate-200 dark:border-slate-700">
                    <th class="p-3 pl-5 text-xs font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                    <th class="p-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Leave Type</th>
                    <th class="p-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Duration</th>
                    <th class="p-3 pr-5 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($history_data as $h): ?>
                <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <td class="p-3 pl-5 text-sm font-semibold text-slate-900 dark:text-white"><?= esc($h['employee_name']) ?></td>
                    <td class="p-3 text-sm text-slate-600 dark:text-slate-300">
                        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-800 font-medium text-xs">
                            <?= esc($h['leave_name']) ?>
                        </span>
                    </td>
                    <td class="p-3 text-sm text-slate-600 dark:text-slate-300">
                        <?= date('d M Y', strtotime($h['from_date'])) ?> <span class="mx-1 text-slate-400">→</span> <?= date('d M Y', strtotime($h['to_date'])) ?> 
                        <span class="text-slate-400 ml-1 text-xs font-semibold">(<?= $h['days'] ?> Day<?= $h['days'] > 1 ? 's' : '' ?>)</span>
                        <?php if($h['day_type'] === 'Half Day'): ?>
                            <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] bg-blue-100 text-blue-700 font-bold">1/2</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 pr-5">
                        <span class="inline-flex px-2.5 py-1 text-[11px] font-bold rounded-full border 
                            <?= $h['status'] === 'approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                               ($h['status'] === 'rejected' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-700 border-amber-200') ?>">
                            <?= strtoupper($h['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>

<!-- TOAST -->
<div id="lvToastEl"
    class="lv-toast fixed bottom-6 left-1/2 z-[9999] flex items-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-2xl whitespace-nowrap">
    <span id="lvToastIcon">✅</span>
    <span id="lvToastMsg">Done!</span>
</div>

<script>
    // FORM VALIDATION SCRIPT 
    document.getElementById('applyBehalfForm')?.addEventListener('submit', function(e) {
        if (!document.getElementById('hiddenEmpId').value) {
            e.preventDefault();
            lvToast('⚠️', 'Please select a valid employee from the search dropdown.');
            document.getElementById('employeeSearchInput').focus();
        }
    });

    // NEW CUSTOM AUTOCOMPLETE SEARCH SCRIPT
    const searchInput = document.getElementById('employeeSearchInput');
    const hiddenEmpId = document.getElementById('hiddenEmpId');
    const dropdown = document.getElementById('autocompleteDropdown');

    if (searchInput && dropdown) {
        let debounceTimer;

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            
            // Clear hidden ID if user changes the text manually without selecting
            hiddenEmpId.value = ''; 
            
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
                                item.className = 'px-4 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 flex justify-between items-center border-b border-slate-100 dark:border-slate-800 last:border-0 transition-colors';
                                item.innerHTML = `<span class="text-sm font-semibold text-slate-700 dark:text-slate-200">${emp.employee_name}</span><span class="text-xs text-slate-400">#${emp.employee_code}</span>`;
                                item.addEventListener('click', () => {
                                    searchInput.value = emp.employee_name; 
                                    hiddenEmpId.value = emp.id; // Assign ID to hidden input for main form submission
                                    dropdown.style.display = 'none';
                                });
                                dropdown.appendChild(item);
                            });
                        } else {
                            dropdown.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400 text-center">No employees found</div>';
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

    // GENERAL SCRIPT LOGIC
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
            if(container) runTimeCalculations(container);
        }
    });
</script>

<script>
// Trigger Toast if message was set in PHP session
if (toastMessage) {
    document.addEventListener('DOMContentLoaded', () => {
        lvToast('✅', toastMessage);
    });
}

function lvToast(icon, msg) {
    const t = document.getElementById('lvToastEl');
    const ti = document.getElementById('lvToastIcon');
    const tm = document.getElementById('lvToastMsg');

    if (!t || !ti || !tm) return;

    ti.textContent = icon;
    tm.textContent = msg;
    t.classList.add('show');

    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.remove('show'), 3200);
}

document.addEventListener('click', function(e) {
    const pill = e.target.closest('.lv-cal-pill');
    if (!pill) return;

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    openLeaveDayModal(
        pill.dataset.date,
        pill.dataset.status,
        parseInt(pill.dataset.count || '0', 10)
    );
}, true);

function openLeaveDayModal(date, status, count) {
    const modal = document.getElementById('leaveDayModal');
    const title = document.getElementById('leaveDayModalTitle');
    const dateEl = document.getElementById('leaveDayModalDate');
    const list = document.getElementById('leaveDayList');
    const search = document.getElementById('leaveDaySearch');
    const selectedCount = document.getElementById('leaveDaySelectedCount');
    const selectAll = document.getElementById('leaveDaySelectAll');

    if (!modal || !title || !list) return;

    if (search) search.value = '';
    if (selectedCount) selectedCount.textContent = '0';
    if (selectAll) selectAll.checked = false;

    const dateText = new Date(date + 'T00:00:00').toLocaleDateString('en-IN', {
        weekday: 'long',
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });

    // FIX: Update title logic to include Rejected status
    if (status === 'approved') {
        title.textContent = 'Approved Leave requests';
    } else if (status === 'rejected') {
        title.textContent = 'Rejected Leave requests';
    } else {
        title.textContent = 'Pending Leave requests';
    }

    if (dateEl) dateEl.textContent = dateText;

    // Fetch dynamically loaded requests from PHP injection
    const dailyItems = calendarLeaveDetails[date] || [];
    const filteredItems = dailyItems.filter(item => item.status === status);

    let html = '';
    filteredItems.forEach((item, index) => {
        const safeName = escapeHtml(item.name || 'Unknown');
        const safeType = escapeHtml(item.type || 'Leave');
        const itemId = item.id || 0;

        html += `
            <div class="leave-day-item flex items-center gap-3 min-w-0" data-name="${safeName.toLowerCase()}">
                <input type="checkbox" class="leave-day-check w-4 h-4 accent-blue-600 shrink-0" value="${itemId}" onchange="updateLeaveDaySelected()">
                <div class="w-9 h-9 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold text-slate-600 dark:text-slate-200 shrink-0">
                    ${safeName.charAt(0)}
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-slate-900 dark:text-white truncate">${safeName}</div>
                    <div class="text-xs text-slate-400">${safeType}</div>
                </div>
            </div>
        `;
    });

    list.innerHTML = html || '<p class="text-sm text-slate-400">No leave requests found.</p>';
    modal.classList.add('open');
}

function escapeHtml(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function filterLeaveDayItems(q) {
    q = String(q || '').toLowerCase().trim();
    document.querySelectorAll('.leave-day-item').forEach(function(item) {
        item.style.display = !q || (item.dataset.name || '').includes(q) ? 'flex' : 'none';
    });
}

function updateLeaveDaySelected() {
    const total = document.querySelectorAll('.leave-day-check:checked').length;
    const el = document.getElementById('leaveDaySelectedCount');
    if (el) el.textContent = total;
}

function toggleLeaveDaySelectAll(chk) {
    document.querySelectorAll('.leave-day-item').forEach(function(item) {
        if (item.style.display !== 'none') {
            const cb = item.querySelector('.leave-day-check');
            if (cb) cb.checked = chk.checked;
        }
    });
    updateLeaveDaySelected();
}

function cancelSelectedLeaves() {
    const checkboxes = document.querySelectorAll('.leave-day-check:checked');
    const total = checkboxes.length;
    if (!total) {
        lvToast('⚠️', 'Please select at least one leave request.');
        return;
    }

    const ids = Array.from(checkboxes).map(cb => cb.value);

    // Backend AJAX to process cancelling the request IDs
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=cancel_leaves&ids=' + encodeURIComponent(JSON.stringify(ids))
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('leaveDayModal')?.classList.remove('open');
            lvToast('✅', total + ' leave request(s) cancelled/rejected.');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            lvToast('❌', 'Failed to update leaves. Please try again.');
        }
    })
    .catch(err => {
        console.error(err);
        lvToast('❌', 'An error occurred.');
    });
}

function filterHistTable(q) {
    q = String(q || '').toLowerCase().trim();
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

function toggleMonthChart() {
    const tog = document.getElementById('monthToggle');
    const cw = document.getElementById('monthChartWrap');
    const ew = document.getElementById('monthEmptyWrap');

    if (!cw || !ew) return;

    if (tog && tog.checked) {
        cw.style.display = 'none';
        ew.style.display = 'block';
    } else {
        cw.style.display = 'block';
        ew.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Team Stats (Last 3 Months mocked view for structural integrity)
    const tsCtx = document.getElementById('teamStatsChart');
    if (tsCtx) {
        new Chart(tsCtx, {
            type: 'bar',
            data: {
                labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                datasets: [{
                    label: 'Leaves',
                    data: [0, 2, 4, 3, 5, 6, 0],
                    backgroundColor: 'rgba(59,130,246,.5)',
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 }, color: '#9CA3AF' },
                        grid: { color: 'rgba(0,0,0,.04)' },
                        border: { display: false }
                    },
                    x: {
                        ticks: { font: { size: 11 }, color: '#9CA3AF' },
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });
    }

    // Dynamic Donut Chart based on Database Values
    const doCtx = document.getElementById('overviewDonut');
    if (doCtx) {
        new Chart(doCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($donut_labels) ?>,
                datasets: [{
                    data: <?= json_encode($donut_data) ?>,
                    backgroundColor: <?= json_encode($donut_colors) ?>,
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: false,
                cutout: '72%',
                plugins: { legend: { display: false } }
            }
        });
    }

    const ltCtx = document.getElementById('leavesTakenChart');
    if (ltCtx) {
        const dayColors = ['#3B82F6', '#22C55E', '#F59E0B', '#6366F1', '#EF4444', '#EC4899', '#9CA3AF'];

        const datasets = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(function(d, i) {
            return {
                label: d,
                data: [1, 2, 3, 4],
                borderColor: dayColors[i],
                backgroundColor: 'transparent',
                borderWidth: 1.5,
                pointRadius: 3,
                tension: 0.4
            };
        });

        new Chart(ltCtx, {
            type: 'line',
            data: {
                labels: ['W1', 'W2', 'W3', 'W4'],
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        min: 0,
                        ticks: { font: { size: 10 }, color: '#9CA3AF' },
                        grid: { color: 'rgba(0,0,0,.04)' },
                        border: { display: false }
                    },
                    x: {
                        ticks: { font: { size: 11 }, color: '#9CA3AF' },
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });
    }

    const mrCtx = document.getElementById('monthReqChart');
    if (mrCtx) {
        new Chart(mrCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                        label: 'Compensatory',
                        data: [12, 8, 5, 14, 0, 0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: '#F59E0B',
                        borderRadius: 6
                    },
                    {
                        label: 'Casual/Sick',
                        data: [4, 3, 2, 8, 0, 0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: '#7C3AED',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 11 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 }, color: '#9CA3AF' },
                        grid: { color: 'rgba(0,0,0,.04)' },
                        border: { display: false }
                    },
                    x: {
                        ticks: { font: { size: 10 }, color: '#9CA3AF' },
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>