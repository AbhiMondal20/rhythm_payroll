<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/db_client.php';
require_once 'includes/config.php';

// ==========================================
// 1. API / AJAX HANDLERS 
// ==========================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // API: FETCH DYNAMIC PAYSLIP DATA
    if ($_POST['ajax_action'] == 'get_payslip_data') {
        $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code'] ?? '');
        $pay_period = mysqli_real_escape_string($conn, $_POST['pay_period'] ?? '');

        // Initialize empty structure
        $data = [
            'Earnings' => [],
            'Deductions' => [],
            'Employer Contribution' => []
        ];

        // Safely join both old salary_components (for legacy) and new ctc_template_components
        $sql = "
            SELECT 
                p.salary_type, 
                p.amount, 
                COALESCE(c.code, 'N/A') as code, 
                COALESCE(tc.component_name, c.component_name, 'Unknown Component') as component_name 
            FROM `payslip_approvals` p
            LEFT JOIN `salary_components` c ON p.component_id = c.id
            LEFT JOIN `ctc_template_components` tc ON p.component_id = tc.id
            WHERE p.employee_code = '$emp_code' 
              AND p.pay_month = '$pay_period'
        ";

        $res = @mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            $e_count = 1; $d_count = 1; $em_count = 1;
            
            while($row = mysqli_fetch_assoc($res)) {
                // Normalize salary types to match our UI tabs
                $type = $row['salary_type'];
                if (stripos($type, 'Earning') !== false) $type = 'Earnings';
                if (stripos($type, 'Deduction') !== false) $type = 'Deductions';
                if (stripos($type, 'Employer') !== false) $type = 'Employer Contribution';

                $item = [
                    'code' => $row['code'] ?? 'N/A',
                    'component' => $row['component_name'] ?? 'Unknown Component',
                    'amount' => number_format((float)$row['amount'], 2, '.', '')
                ];

                if ($type == 'Earnings') { 
                    $item['sno'] = $e_count++; 
                    $data['Earnings'][] = $item; 
                } elseif ($type == 'Deductions') { 
                    $item['sno'] = $d_count++; 
                    $data['Deductions'][] = $item; 
                } elseif ($type == 'Employer Contribution') { 
                    $item['sno'] = $em_count++; 
                    $data['Employer Contribution'][] = $item; 
                }
            }
        }
        
        echo json_encode($data);
        exit;
    }

    // API: SEARCH EMPLOYEES (ADVANCED SEARCH)
    if ($_POST['ajax_action'] == 'search_employees') {
        $keyword = mysqli_real_escape_string($conn, $_POST['keyword'] ?? '');
        $org     = mysqli_real_escape_string($conn, $_POST['org'] ?? '');
        $loc     = mysqli_real_escape_string($conn, $_POST['loc'] ?? '');
        $dept    = mysqli_real_escape_string($conn, $_POST['dept'] ?? '');
        $status  = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
        
        $sql = "SELECT `employee_code`, `employee_name`, `ctc_template_id` FROM `employees` WHERE (`status` = 'Active' OR `status` = '1')";
        
        if (!empty($keyword)) { $sql .= " AND (`employee_name` LIKE '%$keyword%' OR `employee_code` LIKE '%$keyword%')"; }
        if (!empty($loc)) { $sql .= " AND `location` = '$loc'"; }
        if (!empty($dept)) { $sql .= " AND `department` = '$dept'"; }
        if (!empty($status)) { $sql .= " AND `status` = '$status'"; }

        $res = @mysqli_query($conn, $sql);
        $emps = [];
        if ($res && mysqli_num_rows($res) > 0) {
            while($row = mysqli_fetch_assoc($res)){
                $emps[] = [
                    'id' => $row['employee_code'], 
                    'name' => $row['employee_name'],
                    'ctc_template_id' => $row['ctc_template_id']
                ];
            }
        }
        echo json_encode($emps);
        exit;
    }

    // API: SAVE SEARCH
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

    // API: DELETE SEARCH
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
// 2. FORM SUBMISSION (Add Salary Component)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_component'])) {

    $salary_type = mysqli_real_escape_string($conn, $_POST['salary_type'] ?? '');
    $component_id = mysqli_real_escape_string($conn, $_POST['component_id'] ?? '');
    $dr_account = mysqli_real_escape_string($conn, $_POST['dr_account'] ?? '');
    $cr_account = mysqli_real_escape_string($conn, $_POST['cr_account'] ?? '');
    $amount = mysqli_real_escape_string($conn, $_POST['amount'] ?? '');
    $emp_code = mysqli_real_escape_string($conn, $_POST['selected_employee_code'] ?? '');
    $pay_period = mysqli_real_escape_string($conn, $_POST['selected_pay_period'] ?? '');

    // Employee name fetch
    $emp_name = '';
    $emp_qry = mysqli_query($conn, "SELECT employee_name FROM employees WHERE employee_code='$emp_code'");
    if ($emp_row = mysqli_fetch_assoc($emp_qry)) {
        $emp_name = $emp_row['employee_name'];
    }

    $financial_year = date('Y');
    $pay_month = $pay_period;

    $insert = "
        INSERT INTO payslip_approvals
        (employee_code, employee_name, financial_year, pay_month, salary_type, component_id, dr_account, cr_account, amount, status, action_date, created_at)
        VALUES
        ('$emp_code', '$emp_name', '$financial_year', '$pay_month', '$salary_type', '$component_id', '$dr_account', '$cr_account', '$amount', 'Pending', NOW(), NOW())
    ";

    mysqli_query($conn, $insert);

    header("Location: " . $_SERVER['PHP_SELF'] . "?status=added");
    exit();
}

$page_title = 'Payroll - Edit Payslip';

// ==========================================
// 3. FETCH DATA FOR UI RENDER
// ==========================================
$employees = [];
// Added ctc_template_id to fetch
$emp_sql = "SELECT `employee_code`, `employee_name`, `join_date`, `ctc_template_id` FROM `employees` WHERE `status` = 'Active' OR `status` = 1"; 
$emp_result = @mysqli_query($conn, $emp_sql);
if ($emp_result && mysqli_num_rows($emp_result) > 0) {
    while ($row = mysqli_fetch_assoc($emp_result)) { $employees[] = $row; }
}

// Fetch new CTC Template Components
$ctc_template_components = [];
$tc_sql = "SELECT `id`, `template_id`, `component_type`, `component_name`, `calc_type`, `calc_value` FROM `ctc_template_components` WHERE 1";
$tc_result = @mysqli_query($conn, $tc_sql);
if ($tc_result && mysqli_num_rows($tc_result) > 0) {
    while ($row = mysqli_fetch_assoc($tc_result)) {
        $ctc_template_components[] = $row;
    }
}

// Fetch Filter dropdowns
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
$desig_result = @mysqli_query($conn, "SELECT `id`, `desig_name` FROM `org_designations` WHERE `status` = 'Active' OR `status` = 1");
if ($desig_result) { while ($row = mysqli_fetch_assoc($desig_result)) { $designations[] = $row; } }

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* Common Styles */
.btn-back { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; color: #6B7280; background: #fff; border: 1px solid #D1D5DB; text-decoration: none; transition: all 0.2s; cursor: pointer; }
.btn-back:hover { background: #F3F4F6; color: #111827; border-color: #9CA3AF; }
.payroll-header-wrapper { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 5px; }
.page-title { font-size: 20px; font-weight: 700; color: #111827; margin: 0; }
.payroll-top-links { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.payroll-top-links a { font-size: 13px; color: #6B7280; text-decoration: none; transition: color 0.15s; }
.payroll-top-links a:hover { color: #2563EB; }
.payroll-top-links .separator { color: #D1D5DB; font-size: 14px; }
.payroll-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); border: 1px solid #E5E7EB; padding: 24px; min-height: 400px; }
.payroll-tab{ padding: 5px 2px; font-size: 13.5px; font-weight: 500; color: #6B7280; cursor: pointer; border: none; background: transparent; border-bottom: 2.5px solid transparent; white-space: nowrap; transition: color .15s, border-color .15s; text-decoration: none; display: block; margin-bottom: -1px; }
.payroll-tab:hover { color: #111827; border-bottom-color: #111827; }
.payroll-tab.active { color: #0066FF; border-bottom-color: #0066FF; font-weight: 600; }

.card-top-bar { display: flex; align-items: center; margin-bottom: 20px; }
.breadcrumb { font-size: 15px; color: #4B5563; }
.breadcrumb strong { color: #111827; font-weight: 600; }
.instructions-block { font-size: 13.5px; color: #4B5563; line-height: 1.8; margin-bottom: 30px; }
.instructions-block strong { color: #111827; font-size: 14px; }
.section-heading { font-size: 13px; font-weight: 700; color: #111827; margin-bottom: 15px; text-transform: uppercase; }

/* Selection Grid */
.selection-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; max-width: 900px; padding-bottom: 30px; border-bottom: 1px dashed #E5E7EB; margin-bottom: 25px; }
.search-line-wrapper { position: relative; }
.search-line-wrapper > svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: #9CA3AF; fill: none; stroke-width: 2; }
.search-line-wrapper input { width: 100%; padding: 8px 10px 8px 36px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 14px; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
.search-line-wrapper input:focus { border-color: #0066FF; }

/* Employee Chip */
.employee-chip-wrapper { border: 1px solid #D1D5DB; border-radius: 4px; padding: 4px 10px 4px 36px; display: flex; align-items: center; min-height: 36px; background: #fff; position: relative; box-sizing: border-box; width: 100%; }
.employee-chip-wrapper > svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: #9CA3AF; fill: none; stroke-width: 2; }
.employee-chip { background: #F3F4F6; border: 1px solid #E5E7EB; border-radius: 4px; padding: 2px 8px; display: flex; align-items: center; gap: 8px; font-size: 13px; color: #111827; }
.employee-chip span.remove { cursor: pointer; color: #6B7280; font-weight: normal; font-size: 16px; line-height: 1; transition: color 0.15s; }
.employee-chip span.remove:hover { color: #EF4444; }

.line-input { width: 100%; padding: 8px 0; border: none; border-bottom: 1px solid #D1D5DB; font-size: 14px; color: #111827; background: transparent; outline: none; transition: border-color 0.2s; }
.line-input:focus { border-bottom-color: #0066FF; }
select.line-input { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 5l3 3 3-3'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right center; padding-right: 20px; }
.form-group label { display: block; font-size: 12px; color: #4B5563; margin-bottom: 8px; }

/* Data View (Tabs & Table) */
.data-tabs-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; }
.inner-tabs { display: flex; gap: 20px; border-bottom: 1px solid #E5E7EB; margin-bottom: 0; flex: 1; }
.inner-tab { font-size: 14px; color: #6B7280; text-decoration: none; padding-bottom: 10px; border-bottom: 2px solid transparent; font-weight: 500; transition: all 0.2s; cursor: pointer; }
.inner-tab:hover { color: #111827; }
.inner-tab.active { color: #0066FF; border-bottom-color: #0066FF; font-weight: 600; }

.btn-outline-primary { background: #fff; color: #0066FF; border: 1px solid #0066FF; padding: 6px 16px; border-radius: 4px; font-size: 13.5px; font-weight: 500; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
.btn-outline-primary:hover { background: #F0F5FF; }
.btn-outline { background: #fff; color: #0066FF; border: 1px solid #0066FF; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn-outline:hover { background: #F0F5FF; }
.btn-primary { background: #0066FF; color: #fff; border: none; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #0052cc; }

.table-responsive { overflow-x: auto; border: 1px solid #E5E7EB; border-radius: 4px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 14px 20px; text-align: left; border-bottom: 1px solid #E5E7EB; font-size: 13.5px; color: #111827; }
.data-table th { background-color: #F9FAFB; color: #4B5563; font-weight: 600; font-size: 12px; }

/* Add Component Form */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 30px; max-width: 800px; }
.rupee-input-wrapper { position: relative; display: flex; align-items: center; }
.rupee-input-wrapper span { position: absolute; left: 0; color: #111827; font-size: 14px; }
.rupee-input-wrapper input { padding-left: 15px; }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; max-width: 800px; margin-top: 10px; }

/* Modal and Dropdown Styles */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; box-sizing: border-box; }
.modal-content { background: #fff; width: 100%; max-width: 900px; max-height: 90vh; border-radius: 8px; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #E5E7EB; }
.modal-header h2 { margin: 0; font-size: 16px; font-weight: 600; color: #111827; }
.modal-close { background: none; border: 1px solid #D1D5DB; font-size: 20px; cursor: pointer; color: #6B7280; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.modal-close:hover { background: #F3F4F6; color: #111827; }
.modal-body { padding: 24px; overflow-y: auto; flex: 1; }
.modal-filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
.modal-search-row { display: flex; justify-content: space-between; align-items: center; }
.modal-results-layout { display: flex; gap: 30px; margin-top: 20px; }
.modal-emp-list-sec { flex: 3; }
.modal-recent-sec { flex: 1; border-left: 1px solid #E5E7EB; padding-left: 20px; }
.modal-emp-header { margin-bottom: 15px; border-bottom: 1px solid #E5E7EB; padding-bottom: 10px; }
.modal-emp-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; max-height: 200px; overflow-y: auto; }
.recent-tabs { display: flex; border-bottom: 1px solid #E5E7EB; margin-bottom: 15px; }
.recent-tab { padding: 6px 12px; font-size: 12px; color: #6B7280; cursor: pointer; border-bottom: 2px solid transparent; }
.recent-tab.active { color: #0066FF; border-bottom-color: #0066FF; font-weight: 500; }
.recent-list { list-style: none; padding: 0; margin: 0; }
.recent-list li { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #4B5563; padding: 8px 0; border-bottom: 1px dashed #E5E7EB; cursor: pointer; }
.recent-list li:hover { background: #F9FAFB; }
.recent-list li button { background: none; border: 1px solid #D1D5DB; border-radius: 50%; cursor: pointer; width: 20px; height: 20px; color: #EF4444; }
.modal-footer { padding: 16px 24px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px; background: #F9FAFB; }

.emp-search-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #D1D5DB; border-top: none; border-radius: 0 0 6px 6px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); z-index: 50; display: flex; flex-direction: column; overflow: hidden; }
#empSearchList { list-style: none; padding: 0; margin: 0; overflow-y: auto; max-height: 250px; }
#empSearchList li { padding: 10px 15px; display: flex; align-items: center; gap: 15px; cursor: pointer; transition: background 0.2s; }
#empSearchList li:hover { background: #F9FAFB; }
.emp-avatar { width: 32px; height: 32px; border-radius: 50%; background: #8da2c3; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.emp-avatar svg { width: 22px !important; height: 22px !important; fill: #e5eaf2; margin-top: 6px; }
.emp-info { font-size: 14px; color: #374151; }
.emp-search-footer { padding: 12px 15px; font-size: 13px; color: #1a73e8; cursor: pointer; display: flex; align-items: center; gap: 8px; border-top: 1px solid #E5E7EB; background: #fff; font-weight: 500; }
.emp-search-footer:hover { background: #F9FAFB; text-decoration: underline; }
.emp-search-footer svg { width: 16px !important; height: 16px !important; stroke: currentColor; fill: none; }
.emp-search-dropdown svg { position: static !important; transform: none !important; margin: 0; }

/* Custom Toast Alerts */
.toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
.toast { min-width: 250px; background: #fff; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-left: 4px solid #2563EB; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; font-size: 14px; color: #111827; transform: translateX(110%); opacity: 0; transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55); }
.toast.show { transform: translateX(0); opacity: 1; }
.toast.success { border-left-color: #10B981; }
.toast.error { border-left-color: #EF4444; }
.toast-close-btn { background: none; border: none; font-size: 18px; cursor: pointer; color: #9CA3AF; display: flex; align-items: center; }
.toast-close-btn:hover { color: #4B5563; }
</style>

<div class="toast-container" id="toastContainer"></div>

<div class="payroll-header-wrapper">
    <div class="title-wrapper">
        <a href="javascript:history.back()" class="btn-back" title="Go Back">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
        </a>
    </div>
    <h1 class="page-title">Payroll</h1>
     <div class="payroll-top-links">
        <a href="PaymentDeduction">Payment/Deduction</a> <span class="separator">|</span>
        <a href="HoldSalary">Hold Salary</a> <span class="separator">|</span>
        <a href="ApprovePayslip">Approve Payslip</a> <span class="separator">|</span>
        <a href="EditPayslip" class="payroll-tab active">Edit Payslip</a> <span class="separator">|</span>
        <a href="Loans">Loans</a> <span class="separator">|</span>
        <a href="ProcessPayslip">Process Payslip</a> <span class="separator">|</span>
        <a href="FullFinal">Final Settlement</a> <span class="separator">|</span>
        <a href="SalaryStructure">Salary Structure</a> <span class="separator">|</span>
        <a href="Timesheet">Timesheet</a>
    </div>
</div>

<div class="payroll-card">
    <div class="card-top-bar">
        <div class="breadcrumb"><strong>Payroll</strong> &nbsp;&gt;&nbsp; Edit Payslip</div>
    </div>

    <div class="instructions-block">
        <strong>Instructions :</strong><br><br>
        - Edit salary components from a payslip of any Employee after a Payroll run.<br>
        - You can also delete Payslip from your records here.
    </div>

    <div class="selection-grid">
        <div>
            <div class="section-heading">SELECT EMPLOYEE</div>            
            <div id="empInputState">
                <div class="search-line-wrapper" style="position: relative;">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="empSearchInput" placeholder="Search by name or #code" autocomplete="off">
                    
                    <div id="empSearchDropdown" class="emp-search-dropdown" style="display: none;">
                        <ul id="empSearchList"></ul>
                        <div class="emp-search-footer" onclick="openFilterModal()">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            Browse Active & Inactive Employees
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="empChipState" style="display:none; position: relative;">
                <div class="employee-chip-wrapper">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <div class="employee-chip">
                        <span id="empChipText"></span>
                        <span class="remove" onclick="removeEmployeeSelection()">&times;</span>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="section-heading">SELECT PAY PERIOD</div>
            <div class="form-group">
                <label>Pay Period</label>
                <select id="payPeriodSelect" class="line-input" onchange="checkSelections()">
                    <!-- Populated via JS -->
                </select>
            </div>
        </div>
    </div>

    <div class="data-view-wrapper" id="dataViewWrapper" style="display: none; border-top: none; margin-top: 0; padding-top: 0;">
        <div class="data-tabs-header">
            <div class="inner-tabs">
                <span class="inner-tab active" id="tabEarnings" onclick="switchDataTab('Earnings')">Earnings</span>
                <span class="inner-tab" id="tabDeductions" onclick="switchDataTab('Deductions')">Deductions</span>
                <span class="inner-tab" id="tabEmployer" onclick="switchDataTab('Employer Contribution')">Employer Contribution</span>
            </div>
            <button type="button" class="btn-outline-primary btn-sm" onclick="showAddComponentForm()">
                Add New Component
            </button>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="10%">S No.</th>
                        <th width="25%">Code</th>
                        <th width="45%">salary Component</th>
                        <th width="20%">Amount</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                </tbody>
            </table>
        </div>
    </div>

    <div class="data-view-wrapper" id="addComponentWrapper" style="display: none; border-top: none; margin-top: 0; padding-top: 0;">
        <div class="section-heading">NEW SALARY COMPONENT</div>
        
        <form action="" method="POST" id="newComponentForm">
            <input type="hidden" name="selected_employee_code" id="hiddenEmpCode">
            <input type="hidden" name="selected_pay_period" id="hiddenPayPeriod">

            <div class="form-row">
                <div class="form-group">
                    <label>Salary Type</label>
                    <select name="salary_type" id="salaryTypeSelect" class="line-input" onchange="populateComponentsDropdown()" required>
                        <option value="">Select Type</option>
                        <option value="Earnings">Earnings</option>
                        <option value="Deductions">Deductions</option>
                        <option value="EmployerContribution">EmployerContribution</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Component</label>
                    <select name="component_id" id="componentSelect" class="line-input" required>
                        <option value="">Select Component</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Dr Account</label>
                    <input type="text" name="dr_account" class="line-input">
                </div>
                <div class="form-group">
                    <label>Cr Account</label>
                    <input type="text" name="cr_account" class="line-input">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Amount</label>
                    <div class="rupee-input-wrapper">
                        <span>₹</span>
                        <input type="number" name="amount" class="line-input" step="0.01" required>
                    </div>
                </div>
                <div></div> 
            </div>

            <div class="form-actions">
                <button type="button" class="btn-outline" onclick="hideAddComponentForm()">Cancel</button>
                <button type="submit" name="add_component" class="btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>

<div id="filterModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Advance Employee Search</h2>
            <button type="button" class="modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Modal filter elements... keeping consistent with earlier logic -->
            <div class="search-line-wrapper" style="margin-bottom: 25px; position: relative;">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="modalSearchInput" placeholder="Search by name or #code" style="border-radius: 4px; border: 1px solid #D1D5DB; padding-left: 35px;">
            </div>
            
            <div class="modal-filter-grid">
                <div class="form-group"><label>Organization</label><select id="filterOrg" class="line-input"><option value="">Select Organization</option><?php foreach($organizations as $org): ?><option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['client_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Locations</label><select id="filterLoc" class="line-input"><option value="">Select Location</option><?php foreach($locations as $loc): ?><option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Department</label><select id="filterDept" class="line-input"><option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['dept_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Status</label><select id="filterStatus" class="line-input"><option value="">Select Status</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
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

<?php
    $page_content = ob_get_clean();
    include 'includes/header.php';
    echo $page_content;
    include 'includes/footer.php';
?>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${message}</span><button class="toast-close-btn" onclick="this.parentElement.remove()">&times;</button>`;
    container.appendChild(toast);
    void toast.offsetWidth;
    toast.classList.add('show');
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3500);
}

document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('status') === 'added') {
        Swal.fire({ title: 'Added Successfully!', text: 'The salary component has been added to the payslip.', icon: 'success', confirmButtonColor: '#0066FF' });
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

const employeesData = <?= json_encode($employees) ?>;
const ctcTemplateComponents = <?= json_encode($ctc_template_components) ?>;

let currentEmployeeCode = '';
let currentTemplateId = '';
let currentPayslipData = { Earnings: [], Deductions: [], 'Employer Contribution': [] };

// ── CUSTOM DROPDOWN SEARCH LOGIC ──
const searchInput = document.getElementById('empSearchInput');
const searchDropdown = document.getElementById('empSearchDropdown');
const searchList = document.getElementById('empSearchList');

searchInput.addEventListener('input', function() {
    const val = this.value.toLowerCase().trim();
    if (val.length === 0) {
        searchDropdown.style.display = 'none';
        searchInput.style.borderRadius = "4px"; 
        return;
    }

    const filtered = employeesData.filter(emp => 
        emp.employee_name.toLowerCase().includes(val) || 
        emp.employee_code.toLowerCase().includes(val)
    ).slice(0, 8);

    searchList.innerHTML = '';
    
    if (filtered.length > 0) {
        filtered.forEach(emp => {
            const li = document.createElement('li');
            li.innerHTML = `
                <div class="emp-avatar"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>
                <div class="emp-info">${emp.employee_name} - #${emp.employee_code}</div>
            `;
            li.onclick = () => {
                currentEmployeeCode = emp.employee_code;
                currentTemplateId = emp.ctc_template_id; 
                
                document.getElementById('hiddenEmpCode').value = currentEmployeeCode;
                populatePayPeriods(currentEmployeeCode);
                
                document.getElementById('empInputState').style.display = 'none';
                document.getElementById('empChipText').innerText = `${emp.employee_name} (#${emp.employee_code})`;
                document.getElementById('empChipState').style.display = 'block';
                
                searchInput.value = '';
                searchDropdown.style.display = 'none';
                searchInput.style.borderRadius = "4px";
                
                checkSelections();
            };
            searchList.appendChild(li);
        });
    } else {
        searchList.innerHTML = '<li style="color:#9CA3AF; justify-content:center; padding: 15px;">No matching employees found</li>';
    }
    
    searchDropdown.style.display = 'flex';
    searchInput.style.borderRadius = "4px 4px 0 0";
});

document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
        searchDropdown.style.display = 'none';
        searchInput.style.borderRadius = "4px";
    }
});

// ── DYNAMIC COMPONENT LOADING LOGIC FOR TEMPLATES ──
function populateComponentsDropdown() {
    const typeSelect = document.getElementById('salaryTypeSelect').value;
    const compSelect = document.getElementById('componentSelect');
    
    compSelect.innerHTML = '<option value="">Select Component</option>';

    if(typeSelect && currentTemplateId) {
        let expectedDBTypes = [typeSelect];
        if (typeSelect === 'Earnings') expectedDBTypes.push('Earning');
        if (typeSelect === 'Deductions') expectedDBTypes.push('Deduction');
        if (typeSelect === 'EmployerContribution') {
            expectedDBTypes.push('Employer Contribution');
            expectedDBTypes.push('Employer');
        }

        const filtered = ctcTemplateComponents.filter(c => {
            return c.template_id == currentTemplateId && 
                   expectedDBTypes.some(exp => c.component_type === exp);
        });
        
        filtered.forEach(comp => {
            compSelect.innerHTML += `<option value="${comp.id}">${comp.component_name}</option>`;
        });

        if(filtered.length === 0) {
            compSelect.innerHTML = '<option value="">No components found for this template</option>';
        }
    } else if (!currentTemplateId) {
        compSelect.innerHTML = '<option value="">Employee has no CTC template assigned</option>';
    }
}

// ── DYNAMIC PAY PERIOD (JOIN DATE CALCULATION) ──
function populatePayPeriods(empCode) {
    const select = document.getElementById('payPeriodSelect');
    select.innerHTML = '';

    const emp = employeesData.find(e => e.employee_code === empCode);
    if (!emp || !emp.join_date) {
        select.innerHTML = '<option value="">N/A (Missing Join Date)</option>';
        return;
    }

    const parts = emp.join_date.split('-');
    if(parts.length !== 3) return;

    const joinYear = parseInt(parts[0]);
    const joinMonth = parseInt(parts[1]) - 1;

    let currentDate = new Date(); 
    let current = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    let start = new Date(joinYear, joinMonth, 1);

    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

    while (current >= start) {
        let val = `${monthNames[current.getMonth()]}-${current.getFullYear()}`;
        select.innerHTML += `<option value="${val}">${val}</option>`;
        current.setMonth(current.getMonth() - 1); 
    }
    
    if(select.options.length > 0) {
        checkSelections();
    }
}

function removeEmployeeSelection() {
    document.getElementById('empChipState').style.display = 'none';
    document.getElementById('empInputState').style.display = 'block';
    
    document.getElementById('payPeriodSelect').innerHTML = '';
    document.getElementById('hiddenEmpCode').value = '';
    currentEmployeeCode = '';
    currentTemplateId = '';
    checkSelections();
}

function checkSelections() {
    const isEmpSelected = document.getElementById('empChipState').style.display === 'block';
    const periodVal = document.getElementById('payPeriodSelect').value;
    const isPeriodSelected = (periodVal && periodVal !== '');

    document.getElementById('hiddenPayPeriod').value = periodVal;

    if (isEmpSelected && isPeriodSelected) {
        const formData = new FormData();
        formData.append('ajax_action', 'get_payslip_data');
        formData.append('emp_code', currentEmployeeCode);
        formData.append('pay_period', periodVal);

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not OK');
            return response.json();
        })
        .then(data => {
            currentPayslipData = data;
            document.getElementById('dataViewWrapper').style.display = 'block';
            document.getElementById('addComponentWrapper').style.display = 'none';
            switchDataTab('Earnings');
        })
        .catch(error => console.error('Error fetching payslip data:', error));

    } else {
        document.getElementById('dataViewWrapper').style.display = 'none';
        document.getElementById('addComponentWrapper').style.display = 'none';
    }
}

function switchDataTab(tabName) {
    document.getElementById('tabEarnings').classList.remove('active');
    document.getElementById('tabDeductions').classList.remove('active');
    document.getElementById('tabEmployer').classList.remove('active');
    
    if(tabName === 'Earnings') document.getElementById('tabEarnings').classList.add('active');
    if(tabName === 'Deductions') document.getElementById('tabDeductions').classList.add('active');
    if(tabName === 'Employer Contribution') document.getElementById('tabEmployer').classList.add('active');
    
    renderTable(tabName);
}

function renderTable(tabName) {
    const tbody = document.getElementById('dataTableBody');
    tbody.innerHTML = '';
    
    const data = currentPayslipData[tabName];
    if(data && data.length > 0) {
        data.forEach(item => {
            tbody.innerHTML += `
                <tr>
                    <td>${item.sno}</td>
                    <td>${item.code}</td>
                    <td>${item.component}</td>
                    <td>₹ ${item.amount}</td>
                </tr>
            `;
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 25px; color:#9CA3AF;">No data available for this category in the selected Pay Period.</td></tr>`;
    }
}

function showAddComponentForm() {
    document.getElementById('dataViewWrapper').style.display = 'none';
    document.getElementById('addComponentWrapper').style.display = 'block';
    
    // Automatically populate the components dropdown when the form opens 
    // (in case a salary type is already selected by default)
    populateComponentsDropdown();
}

function hideAddComponentForm() {
    document.getElementById('addComponentWrapper').style.display = 'none';
    document.getElementById('dataViewWrapper').style.display = 'block';
}


// --- MODAL AND ADVANCE SEARCH SCRIPTS KEEP AS IS FROM PREVIOUS CODE ---
function openFilterModal() { document.getElementById('filterModal').style.display = 'flex'; }
function closeFilterModal() { document.getElementById('filterModal').style.display = 'none'; }
function clearModalSelections() { document.getElementById('selectAllModalEmp').checked = false; document.querySelectorAll('.modal-emp-checkbox').forEach(cb => cb.checked = false); }
function toggleAllModalEmp(source) { document.querySelectorAll('.modal-emp-checkbox').forEach(cb => cb.checked = source.checked); }

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

// Re-using simplified performModalSearch
async function performModalSearch() {
    const searchData = {
        keyword: document.getElementById('modalSearchInput').value.trim(),
        org: document.getElementById('filterOrg').value,
        loc: document.getElementById('filterLoc').value,
        dept: document.getElementById('filterDept').value,
        status: document.getElementById('filterStatus').value
    };
    
    const searchForm = new FormData();
    searchForm.append('ajax_action', 'search_employees');
    for (let key in searchData) { searchForm.append(key, searchData[key]); }

    const response = await fetch(window.location.href, { method: 'POST', body: searchForm });
    const modalEmps = await response.json();

    const grid = document.getElementById('modalEmpGrid');
    document.getElementById('empFoundCount').innerText = modalEmps.length;
    document.getElementById('selectAllModalEmp').checked = false;
    grid.innerHTML = '';
    
    if (modalEmps.length === 0) {
        grid.innerHTML = '<span style="font-size: 13px; color: #9CA3AF;">No matching employees found.</span>';
    } else {
        modalEmps.forEach(emp => {
            // Note: Since this is "Edit Payslip" we usually only select ONE employee at a time in this modal design.
            grid.innerHTML += `<label class="checkbox-label"><input type="radio" name="modal_emp_radio" class="modal-emp-checkbox" value="${emp.id}" data-name="${emp.name}" data-template="${emp.ctc_template_id}"> ${emp.name} - ${emp.id}</label>`;
        });
    }
}

// Re-using applyModalFilters to grab the selected radio button for this single-employee flow
function applyModalFilters() {
    const selected = document.querySelector('input[name="modal_emp_radio"]:checked');
    if (selected) {
        currentEmployeeCode = selected.value;
        currentTemplateId = selected.getAttribute('data-template');
        document.getElementById('hiddenEmpCode').value = currentEmployeeCode;
        
        populatePayPeriods(currentEmployeeCode);
        
        document.getElementById('empInputState').style.display = 'none';
        document.getElementById('empChipText').innerText = `${selected.getAttribute('data-name')} (#${currentEmployeeCode})`;
        document.getElementById('empChipState').style.display = 'block';
        
        checkSelections();
    }
    closeFilterModal();
}
</script>
<script src="includes/assets/scripts.js"></script>