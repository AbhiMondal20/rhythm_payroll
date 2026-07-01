<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/db_client.php';
require_once 'includes/config.php';

// ==========================================
// 1. AJAX HANDLERS (For Advance Search Modal)
// ==========================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // -- Fetch Employees based on Filters --
    if ($_POST['ajax_action'] == 'search_employees') {
        $keyword = mysqli_real_escape_string($conn, $_POST['keyword'] ?? '');
        $org     = mysqli_real_escape_string($conn, $_POST['org'] ?? '');
        $loc     = mysqli_real_escape_string($conn, $_POST['loc'] ?? '');
        $dept    = mysqli_real_escape_string($conn, $_POST['dept'] ?? '');
        $status  = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
        $group   = mysqli_real_escape_string($conn, $_POST['group'] ?? '');
        $subGroup = mysqli_real_escape_string($conn, $_POST['subGroup'] ?? '');
        
        $sql = "SELECT `employee_code`, `employee_name` FROM `employees` WHERE (`status` = 'Active' OR `status` = '1')";
        
        if (!empty($keyword)) { $sql .= " AND (`employee_name` LIKE '%$keyword%' OR `employee_code` LIKE '%$keyword%')"; }
        if (!empty($loc)) { $sql .= " AND `location` = '$loc'"; }
        if (!empty($dept)) { $sql .= " AND `department` = '$dept'"; }
        if (!empty($status)) { $sql .= " AND `status` = '$status'"; }
        if (!empty($group)) { $sql .= " AND `grade` = '$group'"; }

        $res = @mysqli_query($conn, $sql);
        $emps = [];
        if ($res && mysqli_num_rows($res) > 0) {
            while($row = mysqli_fetch_assoc($res)){
                $emps[] = ['id' => $row['employee_code'], 'name' => $row['employee_name']];
            }
        }
        echo json_encode($emps);
        exit;
    }

    // -- Save Search to Database --
    if ($_POST['ajax_action'] == 'save_search') {
        $type = mysqli_real_escape_string($conn, $_POST['type']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $data = mysqli_real_escape_string($conn, $_POST['data']);
        
        if ($type == 'recent') {
            $count_q = @mysqli_query($conn, "SELECT id FROM user_searches WHERE search_type='recent' ORDER BY id DESC LIMIT 4, 1");
            if ($count_q && mysqli_num_rows($count_q) > 0) {
                $fifth_id = mysqli_fetch_assoc($count_q)['id'];
                @mysqli_query($conn, "DELETE FROM user_searches WHERE search_type='recent' AND id < $fifth_id");
            }
        }
        
        $insert_sql = "INSERT INTO user_searches (search_type, search_name, filter_data) VALUES ('$type', '$name', '$data')";
        @mysqli_query($conn, $insert_sql);
        
        $recent_searches = [];
        $saved_searches = [];
        $history_res = @mysqli_query($conn, "SELECT * FROM user_searches ORDER BY id DESC");
        if($history_res) {
            while($h_row = mysqli_fetch_assoc($history_res)){
                $h_row['filter_data'] = json_decode($h_row['filter_data'], true); 
                if($h_row['search_type'] == 'recent') $recent_searches[] = $h_row;
                else $saved_searches[] = $h_row;
            }
        }
        echo json_encode(['status' => 'success', 'recent' => $recent_searches, 'saved' => $saved_searches]);
        exit;
    }

    // -- Delete Search from Database --
    if ($_POST['ajax_action'] == 'delete_search') {
        $id = (int)$_POST['id'];
        @mysqli_query($conn, "DELETE FROM user_searches WHERE id=$id");
        
        $recent_searches = [];
        $saved_searches = [];
        $history_res = @mysqli_query($conn, "SELECT * FROM user_searches ORDER BY id DESC");
        if($history_res) {
            while($h_row = mysqli_fetch_assoc($history_res)){
                $h_row['filter_data'] = json_decode($h_row['filter_data'], true); 
                if($h_row['search_type'] == 'recent') $recent_searches[] = $h_row;
                else $saved_searches[] = $h_row;
            }
        }
        echo json_encode(['status' => 'success', 'recent' => $recent_searches, 'saved' => $saved_searches]);
        exit;
    }
}

// ==========================================
// 2. FORM SUBMISSION (Approve/Reject Payslip)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && !isset($_POST['ajax_action'])) {
    $action = $_POST['action']; // 'approve' or 'reject'
    
    if (($action == 'approve' || $action == 'reject') && !empty($_POST['selected_employees'])) {
        $financial_year = mysqli_real_escape_string($conn, $_POST['financial_year']);
        $pay_month = mysqli_real_escape_string($conn, $_POST['pay_month']);
        
        foreach ($_POST['selected_employees'] as $emp_code) {
            $emp_code = mysqli_real_escape_string($conn, $emp_code);
            $name_res = @mysqli_query($conn, "SELECT employee_name FROM employees WHERE employee_code='$emp_code'");
            $emp_name = ($name_res && mysqli_num_rows($name_res) > 0) ? mysqli_fetch_assoc($name_res)['employee_name'] : '';
            
            $insert = "INSERT INTO payslip_approvals (employee_code, employee_name, financial_year, pay_month, status) 
                       VALUES ('$emp_code', '$emp_name', '$financial_year', '$pay_month', '$action')";
            @mysqli_query($conn, $insert);
        }
        
        // Refresh to avoid resubmission and pass success flag
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=success&type=$action");
        exit();
    } elseif (empty($_POST['selected_employees'])) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=empty");
        exit();
    }
}

$page_title = 'Payroll - Approve Payslip';

// ==========================================
// 3. FETCH DATA FOR UI RENDER
// ==========================================
$employees = [];
$emp_sql = "SELECT `employee_code`, `employee_name` FROM `employees` WHERE `status` = 'Active' OR `status` = 1"; 
$emp_result = @mysqli_query($conn, $emp_sql);
if ($emp_result && mysqli_num_rows($emp_result) > 0) {
    while ($row = mysqli_fetch_assoc($emp_result)) { $employees[] = $row; }
}

$organizations = [];
$org_result = @mysqli_query($conn, "SELECT `id`, `client_name` FROM `companies` WHERE `status` = 'Active' OR `status` = 1");
if ($org_result) { while ($row = mysqli_fetch_assoc($org_result)) { $organizations[] = $row; } }

$locations = [];
$loc_result = @mysqli_query($conn, "SELECT `id`, `location_name` FROM `org_locations` WHERE `status` = 'Active' OR `status` = 1");
if ($loc_result) { while ($row = mysqli_fetch_assoc($loc_result)) { $locations[] = $row; } }

$departments = [];
$dept_result = @mysqli_query($conn, "SELECT `id`, `dept_name` FROM `org_departments` WHERE `status` = 'Active' OR `status` = 1");
if ($dept_result) { while ($row = mysqli_fetch_assoc($dept_result)) { $departments[] = $row; } }

// Fetch Designations
$designations = [];
$desig_result = @mysqli_query($conn, "SELECT `id`, `desig_name` FROM `org_designations` WHERE `status` = 'Active' OR `status` = 1");
if ($desig_result) { while ($row = mysqli_fetch_assoc($desig_result)) { $designations[] = $row; } }

// Fetch Categories
$categories = [];
$cat_result = @mysqli_query($conn, "SELECT `id`, `cat_name` FROM `org_categories` WHERE `status` = 'Active' OR `status` = 1");
if ($cat_result) { while ($row = mysqli_fetch_assoc($cat_result)) { $categories[] = $row; } }

$groups = [];
$group_result = @mysqli_query($conn, "SELECT `id`, `group_name` FROM `org_groups` WHERE `status` = 'Active' OR `status` = 1");
if ($group_result) { while ($row = mysqli_fetch_assoc($group_result)) { $groups[] = $row; } }

$sub_groups = [];
$sub_group_result = @mysqli_query($conn, "SELECT `id`, `sub_group_name` FROM `org_sub_groups` WHERE `status` = 'Active' OR `status` = 1");
if ($sub_group_result) { while ($row = mysqli_fetch_assoc($sub_group_result)) { $sub_groups[] = $row; } }

$recent_searches = [];
$saved_searches = [];
$search_history_res = @mysqli_query($conn, "SELECT * FROM user_searches ORDER BY id DESC");
if($search_history_res) {
    while($row = mysqli_fetch_assoc($search_history_res)){
        $row['filter_data'] = json_decode($row['filter_data'], true); 
        if($row['search_type'] == 'recent') $recent_searches[] = $row;
        else $saved_searches[] = $row;
    }
}

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* Common Styles */
/* Back button */
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
    transition: all 0.2s;
    cursor: pointer;
}

.btn-back:hover {
    background: #F3F4F6;
    color: #111827;
    border-color: #9CA3AF;
}

/* ── Page header & Top Links ── */
.payroll-header-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 5px;
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

.payroll-top-links a:hover {
    color: #2563EB;
}

.payroll-top-links .separator {
    color: #D1D5DB;
    font-size: 14px;
}

/* ── Divider Line Style ── */
.payroll-divider {
    border: none;
    border-top: 1px solid #D1D5DB;
    margin: 25px 0;
}

.payroll-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    border: 1px solid #E5E7EB;
    padding: 24px;
    min-height: 400px;
}
.payroll-tab{
    padding: 5px 2px;
    font-size: 13.5px;
    font-weight: 500;
    color: #6B7280;
    cursor: pointer;
    border: none;
    background: transparent;
    border-bottom: 2.5px solid transparent;
    white-space: nowrap;
    transition: color .15s, border-color .15s;
    font-family: inherit;
    text-decoration: none;
    display: block;
    margin-bottom: -1px;
}
.payroll-tab:hover {
    color: #111827;
    border-bottom-color: #111827;
}
.payroll-tab.active {
    color: #2563EB;
    border-bottom-color: #2563EB;
    font-weight: 600;
}

/* End Common Styles */

.card-top-bar { display: flex; flex-direction: column; align-items: flex-start; margin-bottom: 30px; }
.breadcrumb { font-size: 15px; color: #4B5563; margin-bottom: 8px; }
.breadcrumb strong { color: #111827; font-weight: 600; }
.subtitle-text { font-size: 13px; color: #6B7280; }

.section-heading { font-size: 12px; font-weight: 700; color: #111827; margin-bottom: 12px; text-transform: uppercase; margin-top: 25px; }

.search-filter-row { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; max-width: 500px; }
.search-line-wrapper { position: relative; flex: 1; }
.search-line-wrapper svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: #9CA3AF; fill: none; stroke-width: 2; }
.search-line-wrapper input { width: 100%; padding: 8px 10px 8px 32px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 14px; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
.search-line-wrapper input:focus { border-color: #0066FF; }

.btn-filters {
    display: flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #D1D5DB;
    color: #4B5563; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: 500;
    cursor: pointer; transition: all 0.2s; height: 36px;
}
.btn-filters:hover { background: #F9FAFB; border-color: #9CA3AF; }

.selected-employee-box {
    border: 1px solid #D1D5DB; border-radius: 4px; padding: 15px; max-width: 800px;
    min-height: 50px; display: flex; flex-wrap: wrap; gap: 15px;
}
.checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #111827; cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #0066FF; margin: 0; }

.pay-period-row { display: flex; gap: 40px; align-items: flex-end; max-width: 500px; margin-bottom: 40px; }
.form-group { flex: 1; }
.form-group label { display: block; font-size: 12px; color: #4B5563; margin-bottom: 8px; }

.line-input {
    width: 100%; padding: 8px 0; border: none; border-bottom: 1px solid #D1D5DB;
    font-size: 14px; color: #111827; background: transparent; outline: none; transition: border-color 0.2s;
}
.line-input:focus { border-bottom-color: #0066FF; }
select.line-input {
    cursor: pointer; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 5l3 3 3-3'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right center; padding-right: 20px;
}

.form-actions { display: flex; justify-content: flex-end; margin-top: 20px; gap: 10px; }
.btn-primary { background: #0066FF; color: #fff; border: none; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #0052cc; }
.btn-outline { background: #fff; color: #0066FF; border: 1px solid #0066FF; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn-outline:hover { background: #F0F5FF; }
.btn-danger-outline { background: #fff; color: #EF4444; border: 1px solid #EF4444; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn-danger-outline:hover { background: #FEF2F2; }

/* MODAL STYLES (Advance Employee Search) */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; box-sizing: border-box; }
.modal-content { background: #fff; width: 100%; max-width: 900px; max-height: 90vh; border-radius: 8px; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #E5E7EB; }
.modal-header h2 { margin: 0; font-size: 16px; font-weight: 600; color: #111827; }
.modal-close { background: none; border: 1px solid #D1D5DB; font-size: 20px; cursor: pointer; color: #6B7280; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.modal-close:hover { background: #F3F4F6; color: #111827; }
.modal-body { padding: 24px; overflow-y: auto; flex: 1; }
.modal-filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
.modal-filter-grid .form-group { margin-bottom: 0; }
.modal-filter-grid select.line-input { border: 1px solid #D1D5DB; border-radius: 4px; padding: 8px 12px; width: 100%; font-size: 13px; background-position: right 10px center; }
.modal-search-row { display: flex; justify-content: space-between; align-items: center; }
.modal-search-row select { border: 1px solid #D1D5DB; border-radius: 4px; padding: 4px 8px; }
.modal-results-layout { display: flex; gap: 30px; margin-top: 20px; }
.modal-emp-list-sec { flex: 3; }
.modal-recent-sec { flex: 1; border-left: 1px solid #E5E7EB; padding-left: 20px; }
.modal-emp-header { margin-bottom: 15px; border-bottom: 1px solid #E5E7EB; padding-bottom: 10px; }
.modal-emp-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; max-height: 200px; overflow-y: auto; }
.recent-tabs { display: flex; border-bottom: 1px solid #E5E7EB; margin-bottom: 15px; }
.recent-tab { padding: 6px 12px; font-size: 12px; color: #6B7280; cursor: pointer; border-bottom: 2px solid transparent; }
.recent-tab.active { color: #0066FF; border-bottom-color: #0066FF; font-weight: 500; }
.recent-list { list-style: none; padding: 0; margin: 0; }
.recent-list li { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #4B5563; padding: 8px 0; border-bottom: 1px dashed #E5E7EB; cursor: pointer; transition: background 0.1s; }
.recent-list li:hover { background: #F9FAFB; }
.recent-list li span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.recent-list li button { background: none; border: 1px solid #D1D5DB; border-radius: 50%; cursor: pointer; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; color: #EF4444; transition: all 0.2s; margin-left: 10px; flex-shrink: 0; }
.recent-list li button:hover { background: #FEE2E2; border-color: #EF4444; }
.modal-footer { padding: 16px 24px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px; background: #F9FAFB; border-radius: 0 0 8px 8px; }

/* ── Custom Toast Styles ── */
#customToast {
    visibility: hidden;
    min-width: 250px;
    background-color: #333;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 16px;
    position: fixed;
    z-index: 9999;
    right: 20px;
    bottom: -50px; /* Start hidden below viewport */
    font-size: 14px;
    font-weight: 500;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    opacity: 0;
    transition: opacity 0.3s ease-in-out, bottom 0.3s ease-in-out, visibility 0.3s ease-in-out;
}
#customToast.show {
    visibility: visible;
    opacity: 1;
    bottom: 30px;
}
#customToast.success { background-color: #10B981; } /* Tailwind Emerald-500 */
#customToast.error { background-color: #EF4444; }   /* Tailwind Red-500 */
#customToast.warning { background-color: #F59E0B; } /* Tailwind Amber-500 */
</style>

<datalist id="employeeList">
    <?php foreach ($employees as $emp): ?>
    <option value="<?= htmlspecialchars($emp['employee_name'] . ' (#' . $emp['employee_code'] . ')') ?>">
    <?php endforeach; ?>
</datalist>

<div class="payroll-header-wrapper">
    <div class="title-wrapper">
        <a href="javascript:history.back()" class="btn-back" title="Go Back">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
        </a>
    </div>
    <h1 class="page-title">Payroll</h1>
     <div class="payroll-top-links">
        <a href="PaymentDeduction">Payment/Deduction</a> <span class="separator">|</span>
        <a href="HoldSalary">Hold Salary</a> <span class="separator">|</span>
        <a href="ApprovePayslip" class="payroll-tab active">Approve Payslip</a> <span class="separator">|</span>
        <a href="EditPayslip" class="payroll-tab">Edit Payslip</a> <span class="separator">|</span>
        <a href="Loans">Loans</a> <span class="separator">|</span>
        <a href="ProcessPayslip">Process Payslip</a> <span class="separator">|</span>
        <a href="FullFinal">Final Settlement</a> <span class="separator">|</span>
        <a href="SalaryStructure">Salary Structure</a> <span class="separator">|</span>
        <a href="Timesheet">Timesheet</a>
    </div>
</div>

<div class="payroll-card">
    <div class="card-top-bar">
        <div class="breadcrumb"><strong>Payroll</strong> &nbsp;&gt;&nbsp; Approve Payslip</div>
        <div class="subtitle-text">Upon Approval, Payslips are sent to the selected Employees.</div>
    </div>

    <form action="" method="POST" id="approvePayslipForm">
        <div class="section-heading">SELECT EMPLOYEES</div>
        
        <div class="search-filter-row">
            <div class="search-line-wrapper">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="mainEmpSearch" list="employeeList" placeholder="Search by name or #code" autocomplete="off" onchange="addSingleEmployeeFromSearch()">
            </div>
            <button type="button" class="btn-filters" onclick="openFilterModal()">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filters
            </button>
        </div>

        <div class="selected-employee-box" id="mainSelectedEmployeesBox">
            <span style="color: #9CA3AF; font-size: 13px; align-self: center;" id="emptySelectionText">No employees selected. Use search or filters to add.</span>
        </div>

        <div class="section-heading">SELECT PAY PERIOD</div>
        <div class="pay-period-row">
            <div class="form-group">
                <label>Financial Year</label>
                <select name="financial_year" id="financialYear" class="line-input" required onchange="updateMonthsDropdown()">
                    <?php 
                        $currentYear = (int)date('Y');
                        for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++): 
                    ?>
                        <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Month</label>
                <select name="pay_month" id="payMonth" class="line-input" required>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="action" value="reject" class="btn-danger-outline">Reject</button>
            <button type="button" class="btn-outline" onclick="window.location.reload();">Cancel</button>
            <button type="submit" name="action" value="approve" class="btn-primary">Approve</button>
        </div>
    </form>
</div>

<div id="filterModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Advance Employee Search</h2>
            <button type="button" class="modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="search-line-wrapper" style="margin-bottom: 25px;">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="modalSearchInput" placeholder="Search by name or #code" style="border-radius: 4px; border: 1px solid #D1D5DB; padding-left: 35px;">
            </div>
            <div class="modal-filter-grid">
                <div class="form-group"><label>Organization</label><select id="filterOrg" class="line-input"><option value="">Select Organization</option><?php foreach($organizations as $org): ?><option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['client_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Locations</label><select id="filterLoc" class="line-input"><option value="">Select Location</option><?php foreach($locations as $loc): ?><option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Department</label><select id="filterDept" class="line-input"><option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['dept_name']) ?></option><?php endforeach; ?></select></div>
                
                <div class="form-group">
                    <label>Designation</label>
                    <select id="filterDesig" class="line-input">
                        <option value="">Select Designation</option>
                        <?php foreach($designations as $desig): ?>
                            <option value="<?= $desig['id'] ?>"><?= htmlspecialchars($desig['desig_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group"><label>Status</label><select id="filterStatus" class="line-input"><option value="">Select Status</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                <div class="form-group"><label>Group</label><select id="filterGroup" class="line-input"><option value="">Select Group</option><?php foreach($groups as $grp): ?><option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Sub Group</label><select id="filterSubGroup" class="line-input"><option value="">Select Sub Group</option><?php foreach($sub_groups as $sgrp): ?><option value="<?= $sgrp['id'] ?>"><?= htmlspecialchars($sgrp['sub_group_name']) ?></option><?php endforeach; ?></select></div>
                
                <div class="form-group">
                    <label>Category</label>
                    <select id="filterCat" class="line-input">
                        <option value="">Select Category</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['cat_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group"><label>Grade</label><select id="filterGrade" class="line-input"><option value="">Select Grade</option></select></div>
                <div class="form-group"><label>Additional Field</label><select id="filterAddField" class="line-input"><option value="">Select Field</option></select></div>
                <div class="form-group"><label>Field Value</label><select id="filterAddVal" class="line-input"><option value="">Select Value</option></select></div>
            </div>

            <div class="modal-search-row">
                <span style="font-size: 13px; color: #4B5563;">Records per page : <select><option>25</option><option>50</option><option>100</option></select></span>
                <button type="button" class="btn-primary" onclick="performModalSearch()">Search</button>
            </div>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #E5E7EB;">

            <div class="modal-results-layout">
                <div class="modal-emp-list-sec">
                    <div class="modal-emp-header">
                        <label class="checkbox-label" style="font-weight: 500;"><input type="checkbox" id="selectAllModalEmp" onclick="toggleAllModalEmp(this)"> Employees Found - <span id="empFoundCount">0</span></label>
                    </div>
                    <div class="modal-emp-grid" id="modalEmpGrid"><span style="font-size: 13px; color: #9CA3AF;">Click search to find employees.</span></div>
                </div>

                <div class="modal-recent-sec">
                    <div class="recent-tabs">
                        <span class="recent-tab active" id="tabRecentSearch" onclick="switchSidebarTab('recent')">Recent Search</span>
                        <span class="recent-tab" id="tabSavedSearch" onclick="switchSidebarTab('saved')">Saved Search</span>
                    </div>
                    <ul class="recent-list" id="recentSearchList"></ul>
                    <ul class="recent-list" id="savedSearchList" style="display:none;"></ul>
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

<div id="customToast"></div>

<?php
    $page_content = ob_get_clean();
    include 'includes/header.php';
    echo $page_content;
    include 'includes/footer.php';
?>

<script>
// ── CUSTOM TOAST NOTIFICATION LOGIC ──
function showToast(message, type) {
    const toast = document.getElementById("customToast");
    toast.textContent = message;
    toast.className = "show " + type;
    
    // Hide toast after 3.5 seconds
    setTimeout(function(){ 
        toast.className = toast.className.replace("show " + type, ""); 
    }, 3500);
}

// ── DYNAMIC MONTHS GENERATOR ──
function updateMonthsDropdown() {
    const yearSelect = document.getElementById('financialYear');
    const monthSelect = document.getElementById('payMonth');
    const selectedYear = yearSelect.value;
    
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    const currentMonthIndex = new Date().getMonth(); // 0 to 11
    const currentYear = new Date().getFullYear().toString();

    // Clear existing options
    monthSelect.innerHTML = '';
    
    // Populate new options for the selected year
    months.forEach((month, index) => {
        const option = document.createElement('option');
        const monthValue = `${month}-${selectedYear}`;
        
        option.value = monthValue;
        option.textContent = monthValue;
        
        // Auto-select the current month if we are looking at the current year
        if (selectedYear === currentYear && index === currentMonthIndex) {
            option.selected = true;
        } 
        // If looking at a past/future year, default to January
        else if (selectedYear !== currentYear && index === 0) {
            option.selected = true;
        }
        
        monthSelect.appendChild(option);
    });
}

document.addEventListener("DOMContentLoaded", function () {
    // Generate Months right when page loads
    updateMonthsDropdown();

    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get("status");
    const type = urlParams.get("type");

    // Handle Toast Alerts based on URL parameters
    if (status === "success") {
        const textMsg = type === "approve"
            ? "Payslips have been approved successfully."
            : "Payslips have been rejected successfully.";

        const toastType = type === "approve" ? "success" : "warning";
        showToast(textMsg, toastType);

        window.history.replaceState({}, document.title, window.location.pathname);

    } else if (status === "empty") {
        showToast("Please select at least one employee.", "error");
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

// ── MODAL SIDEBAR LOGIC ──
let recentSearches = <?= json_encode($recent_searches ?? []) ?>;
let savedSearches = <?= json_encode($saved_searches ?? []) ?>;

function switchSidebarTab(tab) {
    document.getElementById('tabRecentSearch').classList.remove('active');
    document.getElementById('tabSavedSearch').classList.remove('active');
    document.getElementById('recentSearchList').style.display = 'none';
    document.getElementById('savedSearchList').style.display = 'none';

    if (tab === 'recent') {
        document.getElementById('tabRecentSearch').classList.add('active');
        document.getElementById('recentSearchList').style.display = 'block';
    } else {
        document.getElementById('tabSavedSearch').classList.add('active');
        document.getElementById('savedSearchList').style.display = 'block';
    }
}

function renderSidebarLists() {
    const rList = document.getElementById('recentSearchList');
    const sList = document.getElementById('savedSearchList');
    
    rList.innerHTML = recentSearches.length === 0 ? '<li style="justify-content:center; color:#9CA3AF;">No recent searches</li>' : '';
    recentSearches.forEach(search => {
        let sd = JSON.stringify(search.filter_data).replace(/'/g, "&apos;");
        rList.innerHTML += `<li onclick='applySearchState(${sd})'><span>${search.search_name || "Filtered Search"}</span><button onclick="deleteSearchItem(${search.id}, event)" title="Remove">&minus;</button></li>`;
    });

    sList.innerHTML = savedSearches.length === 0 ? '<li style="justify-content:center; color:#9CA3AF;">No saved searches</li>' : '';
    savedSearches.forEach(search => {
        let sd = JSON.stringify(search.filter_data).replace(/'/g, "&apos;");
        sList.innerHTML += `<li onclick='applySearchState(${sd})'><span>${search.search_name}</span><button onclick="deleteSearchItem(${search.id}, event)" title="Remove">&minus;</button></li>`;
    });
}

function applySearchState(data) {
    document.getElementById('modalSearchInput').value = data.keyword || '';
    document.getElementById('filterOrg').value = data.org || '';
    document.getElementById('filterLoc').value = data.loc || '';
    document.getElementById('filterDept').value = data.dept || '';
    document.getElementById('filterStatus').value = data.status || '';
    document.getElementById('filterGroup').value = data.group || '';
    document.getElementById('filterSubGroup').value = data.subGroup || '';
    performModalSearch();
}

function captureSearchState() {
    return {
        keyword: document.getElementById('modalSearchInput').value.trim(),
        org: document.getElementById('filterOrg').value,
        loc: document.getElementById('filterLoc').value,
        dept: document.getElementById('filterDept').value,
        status: document.getElementById('filterStatus').value,
        group: document.getElementById('filterGroup').value,
        subGroup: document.getElementById('filterSubGroup').value
    };
}

// ── ADVANCE EMPLOYEES SEARCH & AJAX ──
async function performModalSearch() {
    const searchData = captureSearchState();
    
    const searchForm = new FormData();
    searchForm.append('ajax_action', 'search_employees');
    for (let key in searchData) { searchForm.append(key, searchData[key]); }

    const response = await fetch(window.location.href, { method: 'POST', body: searchForm });
    const employees = await response.json();

    const grid = document.getElementById('modalEmpGrid');
    document.getElementById('empFoundCount').innerText = employees.length;
    document.getElementById('selectAllModalEmp').checked = false;
    grid.innerHTML = '';
    
    if (employees.length === 0) {
        grid.innerHTML = '<span style="font-size: 13px; color: #9CA3AF;">No matching employees found.</span>';
    } else {
        employees.forEach(emp => {
            grid.innerHTML += `<label class="checkbox-label"><input type="checkbox" class="modal-emp-checkbox" value="${emp.id}" data-name="${emp.name}"> ${emp.name} - ${emp.id}</label>`;
        });
    }

    let label = searchData.keyword ? searchData.keyword : "Filtered Search";
    const historyForm = new FormData();
    historyForm.append('ajax_action', 'save_search');
    historyForm.append('type', 'recent');
    historyForm.append('name', label);
    historyForm.append('data', JSON.stringify(searchData));

    fetch(window.location.href, { method: 'POST', body: historyForm })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                recentSearches = res.recent;
                savedSearches = res.saved;
                renderSidebarLists();
            }
        });
}

function saveCurrentSearch() {
    // If you want to remove SweetAlert entirely, you can replace this with a standard prompt
    const name = prompt("Enter a name to save this search filter:", "My Saved Search");
    if (name) {
        const searchData = captureSearchState();
        const sfData = new FormData();
        sfData.append('ajax_action', 'save_search');
        sfData.append('type', 'saved');
        sfData.append('name', name);
        sfData.append('data', JSON.stringify(searchData));
        
        fetch(window.location.href, { method: 'POST', body: sfData })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success'){
                    showToast("Your search has been saved.", "success");
                    recentSearches = res.recent;
                    savedSearches = res.saved;
                    renderSidebarLists();
                    switchSidebarTab('saved');
                }
            });
    }
}

function deleteSearchItem(id, event) {
    event.stopPropagation();
    if(confirm("Are you sure you want to delete this saved search?")) {
        const formData = new FormData();
        formData.append('ajax_action', 'delete_search');
        formData.append('id', id);
        
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    showToast("Your saved search has been deleted.", "success");
                    recentSearches = res.recent;
                    savedSearches = res.saved;
                    renderSidebarLists();
                }
            });
    }
}

function openFilterModal() {
    document.getElementById('filterModal').style.display = 'flex';
    renderSidebarLists();
}
function closeFilterModal() {
    document.getElementById('filterModal').style.display = 'none';
}

// ── SELECTION MANAGER ──
let selectedEmployees = [];

function addSingleEmployeeFromSearch() {
    const input = document.getElementById('mainEmpSearch');
    const val = input.value.trim();
    if (val) {
        const match = val.match(/(.+) \(#(.+)\)/);
        if (match) { addEmployeeToSelection(match[2].trim(), match[1].trim()); }
        input.value = ''; 
    }
}

function toggleAllModalEmp(source) {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox');
    checkboxes.forEach(cb => { cb.checked = source.checked; });
}

function clearModalSelections() {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox');
    checkboxes.forEach(cb => { cb.checked = false; });
    document.getElementById('selectAllModalEmp').checked = false;
}

function applyModalFilters() {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox:checked');
    checkboxes.forEach(cb => { addEmployeeToSelection(cb.value, cb.getAttribute('data-name')); });
    closeFilterModal();
}

function addEmployeeToSelection(id, name) {
    if (!selectedEmployees.find(e => e.id === id)) {
        selectedEmployees.push({ id, name });
        renderSelectedEmployees();
    }
}

function removeEmployee(id) {
    selectedEmployees = selectedEmployees.filter(e => e.id !== id);
    renderSelectedEmployees();
}

function renderSelectedEmployees() {
    const box = document.getElementById('mainSelectedEmployeesBox');
    if (selectedEmployees.length === 0) {
        box.innerHTML = '<span style="color: #9CA3AF; font-size: 13px; align-self: center;" id="emptySelectionText">No employees selected. Use search or filters to add.</span>';
        return;
    }
    box.innerHTML = '';
    selectedEmployees.forEach(emp => {
        box.innerHTML += `<label class="checkbox-label" style="background: #F3F4F6; padding: 6px 12px; border-radius: 4px; border: 1px solid #E5E7EB;"><input type="checkbox" name="selected_employees[]" value="${emp.id}" checked onclick="removeEmployee('${emp.id}')" style="accent-color: #EF4444;"> ${emp.name} - ${emp.id}</label>`;
    });
}
</script>
<script src="includes/assets/scripts.js"></script>