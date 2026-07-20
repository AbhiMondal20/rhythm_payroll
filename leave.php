<?php
ob_start();
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/config.php';
require_once 'includes/db_client.php'; // Required for database access

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
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
    $day_type = $_POST['day_type'];
    $reason = $_POST['reason'];

    $ins = $conn->prepare("INSERT INTO leave_requests (emp_id, leave_type_id, from_date, to_date, day_type, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    if ($ins) {
        $ins->bind_param("iissss", $e_id, $lt_id, $from, $to, $day_type, $reason);
        $ins->execute();
        $ins->close();
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
$hist_month = isset($_GET['hm']) ? (int)$_GET['hm'] : (int)date('m') - 1;

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
$employees = [];
$emp_res = $conn->query("SELECT id, employee_name, department FROM employees WHERE status='Active' ORDER BY employee_name ASC");
if ($emp_res) {
    while($row = $emp_res->fetch_assoc()) $employees[] = $row;
}

$leave_types_db = [];
$colors = ['#F59E0B', '#7C3AED', '#3B82F6', '#22C55E', '#EF4444', '#EC4899', '#06B6D4'];
$lt_res = $conn->query("SELECT id, leave_name, leave_code FROM leave_types ORDER BY leave_name ASC");
$color_index = 0;
if ($lt_res) {
    while($row = $lt_res->fetch_assoc()) {
        $row['color'] = $colors[$color_index % count($colors)];
        $row['count'] = 0; // Will be aggregated below
        $row['pct'] = 0;
        $row['up'] = true;
        $leave_types_db[$row['id']] = $row;
        $color_index++;
    }
}

// Aggregate Leave Stats for current month (Insights)
$total_leaves_month = 0;
$stats_sql = "SELECT leave_type_id, COUNT(*) as cnt FROM leave_requests WHERE MONTH(from_date) = ? AND YEAR(from_date) = ? GROUP BY leave_type_id";
$s_stmt = $conn->prepare($stats_sql);
if ($s_stmt) {
    $m_now = (int)date('m');
    $y_now = (int)date('Y');
    $s_stmt->bind_param("ii", $m_now, $y_now);
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
$cal_leaves_js_db = []; 

$start_date = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$end_date = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $days_in_month);

$c_sql = "
    SELECT lr.from_date, lr.to_date, lr.status, e.employee_name, lt.leave_code 
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
                }
                
                if(!isset($cal_leaves_js_db[$dt_key])) { $cal_leaves_js_db[$dt_key] = []; }
                $cal_leaves_js_db[$dt_key][] = [
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
    ];
}

// Fetch Leave History if on history tab
$history_data = [];
if ($active_tab === 'history') {
    $h_start = sprintf('%04d-%02d-01', $hist_year, $hist_month);
    $h_end = date('Y-m-t', strtotime($h_start));
    
    $h_sql = "
        SELECT lr.*, e.employee_name, lt.leave_name, DATEDIFF(lr.to_date, lr.from_date) + 1 AS days 
        FROM leave_requests lr
        JOIN employees e ON lr.emp_id = e.id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.from_date >= ? AND lr.from_date <= ?
        ORDER BY lr.created_at DESC
    ";
    $h_stmt = $conn->prepare($h_sql);
    if($h_stmt) {
        $h_stmt->bind_param("ss", $h_start, $h_end);
        $h_stmt->execute();
        $h_res = $h_stmt->get_result();
        while($r = $h_res->fetch_assoc()) $history_data[] = $r;
        $h_stmt->close();
    }
}

// Data for Insights Charts (Dynamically generated JS)
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
    // Fallback if empty
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
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-blue-500">
                    Last 3 Months
                    <span>⌄</span>
                </button>
            </div>
            <div class="relative h-[190px]">
                <canvas id="teamStatsChart"></canvas>
            </div>
        </div>

        <!-- Overview -->
        <div class="p-5 border-b xl:border-b-0 xl:border-r border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Leaves Overview</h3>
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-blue-500">
                    This Month
                    <span>⌄</span>
                </button>
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
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-blue-500">
                    This Month
                    <span>⌄</span>
                </button>
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
                                $leaves = $calendar_leaves[$date_key] ?? ['approved'=>0,'pending'=>0];

                                echo '<td class="h-[96px] align-top p-2 border border-slate-200 dark:border-slate-700 ' . ($is_today ? 'bg-blue-50 dark:bg-blue-950/30' : 'bg-white dark:bg-slate-900') . '">';
                                echo '<span class="block mb-1 text-sm font-bold ' . ($is_today ? 'text-blue-600' : 'text-slate-700 dark:text-slate-200') . '">' . $day . '</span>';

                                if ($leaves['pending'] > 0) {
                                    echo '<button type="button" class="lv-cal-pill block w-full mb-1 rounded-md bg-amber-500 px-2 py-1 text-[11px] font-bold text-white hover:opacity-90 truncate" data-date="' . esc($date_key) . '" data-status="pending" data-count="' . (int)$leaves['pending'] . '">' . (int)$leaves['pending'] . ' Pending</button>';
                                }

                                if ($leaves['approved'] > 0) {
                                    echo '<button type="button" class="lv-cal-pill block w-full mb-1 rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white hover:opacity-90 truncate" data-date="' . esc($date_key) . '" data-status="approved" data-count="' . (int)$leaves['approved'] . '">' . (int)$leaves['approved'] . ' Approved</button>';
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
        
        <!-- Searchable Employee Select -->
        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Select Employee <span class="text-red-600">*</span></label>
            <input type="text" id="empSearch" placeholder="Search employee..." 
                onkeyup="filterSelect('empSearch', 'empSelect')"
                class="w-full mb-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm outline-none focus:border-blue-500">
            
            <select name="emp_id" id="empSelect" required
                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                <option value="">-- Select Employee --</option>
                <?php foreach($employees as $emp): ?>
                    <option value="<?= esc($emp['id']) ?>"><?= esc($emp['employee_name']) ?> (<?= esc($emp['department']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Searchable Leave Type Select -->
        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Leave Type <span class="text-red-600">*</span></label>
            <input type="text" id="typeSearch" placeholder="Search leave type..." 
                onkeyup="filterSelect('typeSearch', 'typeSelect')"
                class="w-full mb-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm outline-none focus:border-blue-500">

            <select name="leave_type_id" id="typeSelect" required
                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                <option value="">-- Select Type --</option>
                <?php foreach($leave_types_db as $lt): ?>
                    <option value="<?= esc($lt['id']) ?>"><?= esc($lt['leave_name']) ?></option>
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
        
        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Day Type</label>
            <select name="day_type" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500">
                <option value="Full Day">Full Day</option>
                <option value="First Half">First Half</option>
                <option value="Second Half">Second Half</option>
            </select>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Reason</label>
            <textarea name="reason" rows="3" placeholder="Reason for leave..." class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500"></textarea>
        </div>
        
        <div class="flex justify-end gap-2 pt-4">
            <button type="button" onclick="document.getElementById('applyOnBehalfModal').classList.remove('open')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100">Cancel</button>
            <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">Apply Leave</button>
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

            <button type="button" onclick="showMonthPicker()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:border-blue-500">
                <?= month_name($hist_month) . '-' . $hist_year ?>
            </button>
        </div>

        <div class="px-5 pt-4">
            <div class="max-w-sm flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2.5 focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/10">
                <span class="text-slate-400">⌕</span>
                <input type="text" id="histSearch" placeholder="Search table items"
                    oninput="filterHistTable(this.value)"
                    class="w-full bg-transparent outline-none text-sm text-slate-700 dark:text-slate-200">
            </div>
        </div>

        <?php if(empty($history_data)): ?>
        <div class="py-16 px-6 text-center" id="histEmpty">
            <p class="text-sm text-slate-400">No Leave statement available!</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto w-full mb-4">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                        <th class="p-3 text-xs font-bold text-slate-500 uppercase">Employee</th>
                        <th class="p-3 text-xs font-bold text-slate-500 uppercase">Leave Type</th>
                        <th class="p-3 text-xs font-bold text-slate-500 uppercase">Duration</th>
                        <th class="p-3 text-xs font-bold text-slate-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($history_data as $h): ?>
                    <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="p-3 text-sm font-semibold text-slate-900 dark:text-white"><?= esc($h['employee_name']) ?></td>
                        <td class="p-3 text-sm text-slate-600 dark:text-slate-300"><?= esc($h['leave_name']) ?></td>
                        <td class="p-3 text-sm text-slate-600 dark:text-slate-300"><?= date('d M', strtotime($h['from_date'])) ?> to <?= date('d M', strtotime($h['to_date'])) ?> <span class="text-slate-400 ml-1">(<?= $h['days'] ?> Days)</span></td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-[11px] font-bold rounded-full <?= $h['status'] === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($h['status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
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

    title.textContent = status === 'approved' ? 'Approved Leave requests' : 'Pending Leave requests';
    if (dateEl) dateEl.textContent = dateText;

    // Fetch dynamically loaded requests from PHP injection
    const dailyItems = calendarLeaveDetails[date] || [];
    const filteredItems = dailyItems.filter(item => item.status === status);

    let html = '';
    filteredItems.forEach((item, index) => {
        const safeName = escapeHtml(item.name || 'Unknown');
        const safeType = escapeHtml(item.type || 'Leave');

        html += `
            <div class="leave-day-item flex items-center gap-3 min-w-0" data-name="${safeName.toLowerCase()}">
                <input type="checkbox" class="leave-day-check w-4 h-4 accent-blue-600 shrink-0" onchange="updateLeaveDaySelected()">
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
    const total = document.querySelectorAll('.leave-day-check:checked').length;
    if (!total) {
        lvToast('⚠️', 'Please select at least one leave request.');
        return;
    }
    document.getElementById('leaveDayModal')?.classList.remove('open');
    lvToast('✅', total + ' leave request(s) selected.');
}

function showMonthPicker() {
    lvToast('📅', 'Month picker opened');
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