<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
require_once 'includes/db_client.php';
$page_title = 'Payroll - Timesheet';

// ==========================================
// HANDLE AUTOSAVE (AJAX POST REQUEST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'autosave') {
    $emp_val = mysqli_real_escape_string($conn, $_POST['search_emp'] ?? '');
    preg_match('/\(#(.*?)\)/', $emp_val, $matches);
    $employee_code = $matches[1] ?? '';
    
    $pay_period = $_POST['month'] ?? '';
    $parts = explode('-', $pay_period);
    $pay_month = $parts[0] ?? '';
    $pay_year = $parts[1] ?? '';

    $total_days = mysqli_real_escape_string($conn, $_POST['total_days'] ?? 0);
    $days_present = mysqli_real_escape_string($conn, $_POST['days_present'] ?? 0);
    $days_absent = mysqli_real_escape_string($conn, $_POST['days_absent'] ?? 0);
    $holidays = mysqli_real_escape_string($conn, $_POST['holidays'] ?? 0);
    $holidays_worked = mysqli_real_escape_string($conn, $_POST['holidays_worked'] ?? 0);
    $week_offs = mysqli_real_escape_string($conn, $_POST['week_offs'] ?? 0);
    $week_offs_worked = mysqli_real_escape_string($conn, $_POST['week_offs_worked'] ?? 0);
    $short_hours_days = mysqli_real_escape_string($conn, $_POST['short_hours_days'] ?? 0);
    $early_days = mysqli_real_escape_string($conn, $_POST['early_days'] ?? 0);
    $late_days = mysqli_real_escape_string($conn, $_POST['late_days'] ?? 0);
    $paid_leaves = mysqli_real_escape_string($conn, $_POST['paid_leaves'] ?? 0);
    $unpaid_leaves = mysqli_real_escape_string($conn, $_POST['unpaid_leaves'] ?? 0);
    
    $hours_worked = mysqli_real_escape_string($conn, $_POST['hours_worked'] ?? '00:00');
    $overtime_hours = mysqli_real_escape_string($conn, $_POST['overtime_hours'] ?? '00:00');
    
    if ($employee_code != '') {
        // Find employee id
        $emp_query = mysqli_query($conn, "SELECT id FROM employees WHERE employee_code = '$employee_code'");
        $emp_data = mysqli_fetch_assoc($emp_query);
        $employee_id = $emp_data['id'] ?? 0;

        $check_sql = "SELECT id FROM timesheets WHERE employee_id = '$employee_id' AND pay_month = '$pay_month' AND pay_year = '$pay_year'";
        $check_res = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_res) > 0) {
            $row = mysqli_fetch_assoc($check_res);
            $ts_id = $row['id'];
            $update_sql = "UPDATE timesheets SET 
                total_days = '$total_days', days_present = '$days_present', days_absent = '$days_absent', 
                holidays = '$holidays', holidays_worked = '$holidays_worked', week_offs = '$week_offs', 
                week_offs_worked = '$week_offs_worked', short_hours_days = '$short_hours_days', 
                early_days = '$early_days', late_days = '$late_days', paid_leaves = '$paid_leaves', 
                unpaid_leaves = '$unpaid_leaves', hours_worked = '$hours_worked', overtime_hours = '$overtime_hours',
                updated_at = NOW() WHERE id = '$ts_id'";
            mysqli_query($conn, $update_sql);
        } else {
            $insert_sql = "INSERT INTO timesheets 
                (employee_id, pay_year, pay_month, total_days, days_present, days_absent, holidays, holidays_worked, week_offs, week_offs_worked, short_hours_days, early_days, late_days, paid_leaves, unpaid_leaves, hours_worked, overtime_hours, created_at) 
                VALUES 
                ('$employee_id', '$pay_year', '$pay_month', '$total_days', '$days_present', '$days_absent', '$holidays', '$holidays_worked', '$week_offs', '$week_offs_worked', '$short_hours_days', '$early_days', '$late_days', '$paid_leaves', '$unpaid_leaves', '$hours_worked', '$overtime_hours', NOW())";
            mysqli_query($conn, $insert_sql);
        }
    }
    echo json_encode(['status' => 'success']);
    exit();
}

// ==========================================
// FETCH EMPLOYEES FOR SEARCH DROPDOWN
// ==========================================
$employees = [];
$emp_sql = "SELECT `employee_code`, `employee_name` FROM `employees`"; 
$emp_result = @mysqli_query($conn, $emp_sql);

if ($emp_result && mysqli_num_rows($emp_result) > 0) {
    while ($row = mysqli_fetch_assoc($emp_result)) {
        $employees[] = $row;
    }
}

// Simulate UI states
$is_searched = isset($_GET['search_emp']) && !empty($_GET['search_emp']);
$is_add_new = isset($_GET['add_new']);
$view_detail = isset($_GET['view_id']) || $is_add_new;

// Auto-Calculate logic if viewing or adding new for an employee
$calc = [
    'total_days' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'early' => 0, 
    'hrs_worked' => '0:0', 'ot_hrs' => '0:0'
];

if ($is_searched && $view_detail) {
    $emp_val = mysqli_real_escape_string($conn, $_GET['search_emp']);
    preg_match('/\(#(.*?)\)/', $emp_val, $matches);
    $selected_code = $matches[1] ?? '';
    
    $pay_period = $_GET['month'] ?? '';
    $parts = explode('-', $pay_period);
    $m_abbr = $parts[0] ?? date('M');
    $y = $parts[1] ?? date('Y');
    $m_num = date('m', strtotime($m_abbr));
    
    $calc['total_days'] = cal_days_in_month(CAL_GREGORIAN, $m_num, $y);
    
    if ($selected_code != '') {
        $stats_sql = "SELECT 
            COUNT(id) as present_count,
            SUM(CASE WHEN late_hours > '00:00:00' THEN 1 ELSE 0 END) as total_late,
            SUM(CASE WHEN early_hours > '00:00:00' THEN 1 ELSE 0 END) as total_early,
            SUM(TIME_TO_SEC(hours_worked)) as sec_worked,
            SUM(TIME_TO_SEC(over_time_hours)) as sec_ot
            FROM time_entries 
            WHERE employee_code = '$selected_code' AND MONTH(entry_date) = '$m_num' AND YEAR(entry_date) = '$y'";
            
        $stats_res = mysqli_query($conn, $stats_sql);
        if ($stats_res && $s_row = mysqli_fetch_assoc($stats_res)) {
            $calc['present'] = $s_row['present_count'] ?? 0;
            $calc['late'] = $s_row['total_late'] ?? 0;
            $calc['early'] = $s_row['total_early'] ?? 0;
            $calc['absent'] = $calc['total_days'] - $calc['present']; // simplistic calculation
            
            $hrs = floor(($s_row['sec_worked'] ?? 0) / 3600);
            $mins = floor((($s_row['sec_worked'] ?? 0) / 60) % 60);
            $calc['hrs_worked'] = "$hrs : $mins";
            
            $othrs = floor(($s_row['sec_ot'] ?? 0) / 3600);
            $otmins = floor((($s_row['sec_ot'] ?? 0) / 60) % 60);
            $calc['ot_hrs'] = "$othrs : $otmins";
        }
    }
}

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
    
/* CSS Styles */
.btn-back {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    color: #6B7280;
    background: #fff;
    border: 1px solid #D1D5DB;
    text-decoration: none;
    cursor: pointer;
    transition: 0.2s;
}

.btn-back:hover {
    background: #F9FAFB;
}

.payroll-header-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.payroll-top-links {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.payroll-top-links a {
    font-size: 13px;
    color: #6B7280;
    text-decoration: none;
    transition: color 0.15s;
}

.payroll-top-links a:hover,
.payroll-top-links a.active {
    color: #2563EB;
}

.payroll-top-links a.active {
    font-weight: 600;
    border-bottom: 2px solid #2563EB;
    padding-bottom: 2px;
}

.payroll-top-links .separator {
    color: #D1D5DB;
    font-size: 14px;
}

.payroll-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 24px;
    min-height: 500px;
    margin-bottom: 20px;
    position: relative;
}

/* Add New Button & Breadcrumb Wrapper */
.payroll-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.breadcrumb {
    font-size: 14px;
    color: #374151;
}

.breadcrumb span {
    color: #6B7280;
}

.btn-add-new {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background-color: #10B981; /* Nice modern green */
    color: #fff;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: background-color 0.2s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.btn-add-new:hover {
    background-color: #059669;
    color: #fff;
}

/* Filter Styles & Modern Search */
.filter-container { display: flex; align-items: flex-end; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 250px; position: relative; }
.filter-group label { font-size: 12px; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; }
.filter-control { padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 14px; outline: none; transition: 0.2s; background: #fff; width: 100%; box-sizing: border-box; }
.filter-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

/* Custom Search Dropdown */
.search-icon { position: absolute; left: 12px; bottom: 12px; color: #9CA3AF; pointer-events: none; }
.modern-search-input { padding-left: 36px; }
.suggestions-box { display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #E5E7EB; border-radius: 6px; margin-top: 4px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); z-index: 10; max-height: 200px; overflow-y: auto; }
.suggestions-box.active { display: block; }
.suggestion-item { padding: 10px 12px; font-size: 14px; color: #374151; cursor: pointer; transition: 0.1s; border-bottom: 1px solid #F3F4F6; }
.suggestion-item:last-child { border-bottom: none; }
.suggestion-item:hover { background: #F3F4F6; color: #111827; }

.btn-primary { background-color: #007bff; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: 0.2s; }
.btn-primary:hover { background-color: #0056b3; }

/* Table */
.table-container { overflow-x: auto; border: 1px solid #E5E7EB; border-radius: 6px; }
.timesheet-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
.timesheet-table th { background-color: #F9FAFB; padding: 12px 16px; color: #374151; font-weight: 600; border-bottom: 1px solid #E5E7EB; text-transform: uppercase; font-size: 12px; }
.timesheet-table td { padding: 12px 16px; border-bottom: 1px solid #E5E7EB; color: #111827; }
.timesheet-table tr:hover { background-color: #F3F4F6; cursor: pointer; }

/* Tabs & Forms */
.tabs-header { display: flex; gap: 20px; border-bottom: 1px dashed #D1D5DB; margin-bottom: 25px; position: relative; }
.tab-btn { background: none; border: none; padding: 10px 0; font-size: 13px; font-weight: 600; color: #6B7280; cursor: pointer; text-transform: uppercase; border-bottom: 2px solid transparent; }
.tab-btn.active { color: #111827; border-bottom: 2px solid #111827; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 20px 15px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 12px; color: #6B7280; }
.form-group input { padding: 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 14px; background-color: #fff; transition: all 0.2s; width: 100%; box-sizing: border-box; }
.form-group input:focus { border-color: #3b82f6; outline: none; }

/* Action Buttons Bottom */
.action-buttons { display: flex; justify-content: flex-end; gap: 12px; margin-top: 40px; }
.btn-cancel { border: 1px solid #3b82f6; color: #3b82f6; background: #fff; padding: 8px 24px; border-radius: 6px; font-weight: 500; cursor: pointer; text-decoration: none; }
.btn-add { background: #007bff; color: #fff; border: none; padding: 8px 24px; border-radius: 6px; font-weight: 500; cursor: pointer; }

.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #9CA3AF; }

/* Toaste message */
/* Toast Notification Styles */
.toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast-message {
    background-color: #10B981; /* Success Green */
    color: white;
    padding: 12px 24px;
    border-radius: 6px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    font-size: 14px;
    font-weight: 500;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease-in-out;
    display: flex;
    align-items: center;
    gap: 8px;
}

.toast-message.show {
    opacity: 1;
    transform: translateY(0);
}

</style>

<!-- TOP LINKS -->
<div class="payroll-header-wrapper">
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="javascript:history.back()" class="btn-back" title="Go Back">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="page-title">Payroll</h1>
    </div>
    <div class="payroll-top-links">
        <a href="PaymentDeduction">Payment/Deduction</a> <span class="separator">|</span>
        <a href="HoldSalary">Hold Salary</a> <span class="separator">|</span>
        <a href="ApprovePayslip">Approve Payslip</a> <span class="separator">|</span>
        <a href="EditPayslip">Edit Payslip</a> <span class="separator">|</span>
        <a href="Loans">Loans</a> <span class="separator">|</span>
        <a href="ProcessPayslip">Process Payslip</a> <span class="separator">|</span>
        <a href="FullFinal">Final Settlement</a> <span class="separator">|</span>
        <a href="SalaryStructure" >Salary Structure</a> <span class="separator">|</span>
        <a href="Timesheet" class="active">Timesheet</a>
    </div>
</div>

<div class="payroll-card">
    
    <!-- HEADER (With aligned Add New) -->
    <div class="payroll-card-header">
        <div class="breadcrumb">
            <strong>Payroll</strong> <span>&nbsp;&gt;&nbsp; Timesheet</span>
        </div>
        
        <?php if (!$is_add_new): ?>
        <a href="?add_new=1" class="btn-add-new">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add New
        </a>
        <?php endif; ?>
    </div>

    <!-- SEARCH FILTERS (Always visible on Add New or Search) -->
    <form method="GET" class="filter-container" id="filterForm">
        <?php if($is_add_new): ?> <input type="hidden" name="add_new" value="1"> <?php endif; ?>
        
        <div class="filter-group" style="flex: 2;">
            <label>SELECT EMPLOYEE</label>
            <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" name="search_emp" id="empSearchInput" class="filter-control modern-search-input" placeholder="Search by name or #code" autocomplete="off" value="<?= isset($_GET['search_emp']) ? htmlspecialchars($_GET['search_emp']) : '' ?>">
            
            <div class="suggestions-box" id="empSuggestions">
                <?php foreach ($employees as $emp): ?>
                    <div class="suggestion-item"><?= htmlspecialchars($emp['employee_name'] . ' (#' . $emp['employee_code'] . ')') ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-group">
            <label>SELECT PAY PERIOD</label>
            <div style="display: flex; gap: 10px;">
                <select name="month" id="monthSelect" class="filter-control" style="flex: 1;">
                    <!-- JS populated -->
                </select>
                <select name="year" id="yearSelect" class="filter-control" style="flex: 1;">
                    <?php
                        $current_year = isset($_GET['year']) ? $_GET['year'] : '2026';
                        for ($y = 2024; $y <= 2028; $y++) {
                            $sel = ($y == $current_year) ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                    ?>
                </select>
            </div>
        </div>

        <div>
            <button type="submit" class="btn-primary" <?= $is_add_new ? 'style="display:none;"' : '' ?>>Get Details</button>
        </div>
    </form>

    <hr style="border: 0; border-top: 1px solid #E5E7EB; margin-bottom: 25px;">

    <!-- TABULAR RESULTS (When Searching only) -->
    <?php if (!$view_detail): ?>
        <?php if ($is_searched): ?>
            <div class="table-container">
                <table class="timesheet-table">
                    <thead>
                        <tr>
                            <th>S No.</th><th>Emp Code</th><th>Name</th><th>Pay Period</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $emp_code = preg_match('/\(#(.*?)\)/', $_GET['search_emp'], $m) ? $m[1] : ''; 
                        $emp_name = trim(preg_replace('/\(#.*?\)/', '', $_GET['search_emp']));
                        ?>
                        <tr onclick="window.location='?view_id=1&search_emp=<?= urlencode($_GET['search_emp']) ?>&month=<?= urlencode($_GET['month']) ?>&year=<?= urlencode($_GET['year']) ?>'">
                            <td>1</td>
                            <td><?= htmlspecialchars($emp_code) ?></td>
                            <td><?= htmlspecialchars($emp_name) ?></td>
                            <td><?= htmlspecialchars($_GET['month']) ?></td>
                            <td style="text-align: right; color: #9CA3AF; font-weight: bold;">&gt;</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <p>no records found</p>
            </div>
        <?php endif; ?>

    <!-- DETAIL / ADD NEW FORM -->
    <?php else: ?>
        <form id="timesheetForm">
            <!-- Hidden inputs to forward form filter data to AJAX -->
            <input type="hidden" name="search_emp" value="<?= htmlspecialchars($_GET['search_emp'] ?? '') ?>">
            <input type="hidden" name="month" value="<?= htmlspecialchars($_GET['month'] ?? '') ?>">
            
            <div class="tabs-header">
                <button type="button" class="tab-btn active" onclick="switchTab('days')">DAYS</button>
                <button type="button" class="tab-btn" onclick="switchTab('hours')">HOURS</button>
                <button type="button" class="tab-btn" onclick="switchTab('others')">OTHERS</button>
            </div>

            <!-- DAYS TAB -->
            <div id="tab-days" class="tab-content active">
                <div class="form-grid">
                    <div class="form-group"><label>Total Days</label><input type="number" name="total_days" value="<?= $calc['total_days'] ?>"></div>
                    <div class="form-group"><label>Days Present</label><input type="number" name="days_present" value="<?= $calc['present'] ?>"></div>
                    <div class="form-group"><label>Days Absent</label><input type="number" name="days_absent" value="<?= $calc['absent'] ?>"></div>
                    <div class="form-group"><label>Holidays</label><input type="number" name="holidays" value="0"></div>
                    <div class="form-group"><label>Holidays Worked</label><input type="number" name="holidays_worked" value="0"></div>
                    <div class="form-group"><label>Week Offs</label><input type="number" name="week_offs" value="0"></div>
                    <div class="form-group"><label>Week Offs Worked</label><input type="number" name="week_offs_worked" value="0"></div>
                    <div class="form-group"><label>Short Hours Days</label><input type="number" name="short_hours_days" value="0"></div>
                    <div class="form-group"><label>Early Days</label><input type="number" name="early_days" value="<?= $calc['early'] ?>"></div>
                    <div class="form-group"><label>Late Days</label><input type="number" name="late_days" value="<?= $calc['late'] ?>"></div>
                    <div class="form-group"><label>Paid Leaves</label><input type="number" name="paid_leaves" value="0"></div>
                    <div class="form-group"><label>Unpaid Leaves</label><input type="number" name="unpaid_leaves" value="0"></div>
                </div>
            </div>

            <!-- HOURS TAB -->
            <div id="tab-hours" class="tab-content">
                <div class="form-grid">
                    <div class="form-group"><label>Hours Worked</label><input type="text" name="hours_worked" value="<?= $calc['hrs_worked'] ?>"></div>
                    <div class="form-group"><label>Ot Hours Worked</label><input type="text" name="overtime_hours" value="<?= $calc['ot_hrs'] ?>"></div>
                </div>
            </div>

            <!-- OTHERS TAB -->
            <div id="tab-others" class="tab-content">
                <div class="form-grid">
                    <div class="form-group"><label>Other Remarks</label><input type="text" name="other_remarks" value=""></div>
                </div>
            </div>

            <div class="action-buttons">
                <a href="Timesheet" class="btn-cancel">Cancel</a>
                <button type="button" class="btn-add" id="saveBtn"><?= $is_add_new ? 'Add' : 'Save Changes' ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// 1. Dynamic Months based on Financial Year
const yearSelect = document.getElementById('yearSelect');
const monthSelect = document.getElementById('monthSelect');
const urlParams = new URLSearchParams(window.location.search);
const selectedMonth = urlParams.get('month') || 'Aug-2026';

function populateMonths() {
    const y = yearSelect.value;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    monthSelect.innerHTML = '';
    
    months.forEach(m => {
        const val = m + '-' + y;
        const opt = document.createElement('option');
        opt.value = val;
        opt.textContent = val;
        if (val === selectedMonth) opt.selected = true;
        monthSelect.appendChild(opt);
    });
}
yearSelect.addEventListener('change', populateMonths);
populateMonths(); // Initial load

// If "Add New" mode, auto-submit the filter form when selection changes so data auto-fills
<?php if ($is_add_new): ?>
monthSelect.addEventListener('change', () => document.getElementById('filterForm').submit());
yearSelect.addEventListener('change', () => document.getElementById('filterForm').submit());
<?php endif; ?>

// 2. Modern Search Employee Dropdown
const searchInput = document.getElementById('empSearchInput');
const suggestionsBox = document.getElementById('empSuggestions');
const items = suggestionsBox.querySelectorAll('.suggestion-item');

searchInput.addEventListener('focus', () => suggestionsBox.classList.add('active'));
document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.classList.remove('active');
    }
});

searchInput.addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    items.forEach(item => {
        if (item.textContent.toLowerCase().includes(filter)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

items.forEach(item => {
    item.addEventListener('click', function() {
        searchInput.value = this.textContent;
        suggestionsBox.classList.remove('active');
        <?php if ($is_add_new): ?> document.getElementById('filterForm').submit(); <?php endif; ?>
    });
});

// 3. Tab Logic
function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

// 4. Autosave via AJAX
const tsForm = document.getElementById('timesheetForm');
if (tsForm) {
    const saveBtn = document.getElementById('saveBtn');
    
    // Auto-save on blur
    const inputs = tsForm.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('blur', triggerSave);
    });

    // Save on button click
    saveBtn.addEventListener('click', function(e) {
        e.preventDefault();
        triggerSave().then(() => {
            alert('Saved successfully!');
            if (this.textContent === 'Add') {
                window.location = 'Timesheet';
            }
        });
    });

    async function triggerSave() {
        const formData = new FormData(tsForm);
        formData.append('action', 'autosave');
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            console.log('Autosaved', data);
        } catch (error) {
            console.error('Error auto-saving:', error);
        }
    }
}
</script>

<?php
    $page_content = ob_get_clean();
    include 'includes/header.php';
    echo $page_content;
    include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>