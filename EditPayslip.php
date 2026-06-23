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

        // Join with salary_components to get the component Code and Name
        $sql = "
            SELECT 
                p.salary_type, 
                p.amount, 
                c.code, 
                c.component_name 
            FROM `payslip_approvals` p
            LEFT JOIN `salary_components` c ON p.component_id = c.id
            WHERE p.employee_code = '$emp_code' 
              AND p.pay_month = '$pay_period'
        ";

        $res = @mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            $e_count = 1; $d_count = 1; $em_count = 1;
            
            while($row = mysqli_fetch_assoc($res)) {
                // Normalize salary types to match our UI tabs
                $type = $row['salary_type'];
                if ($type == 'Earning') $type = 'Earnings';
                if ($type == 'Deduction') $type = 'Deductions';
                if ($type == 'Employer') $type = 'Employer Contribution';

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

    // Employee name & details fetch
    $emp_name = '';
    $emp_join_date = '';
    $emp_qry = mysqli_query($conn, "SELECT employee_name, join_date FROM employees WHERE employee_code='$emp_code'");
    if ($emp_row = mysqli_fetch_assoc($emp_qry)) {
        $emp_name = $emp_row['employee_name'];
        $emp_join_date = $emp_row['join_date'];
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
$emp_sql = "SELECT `employee_code`, `employee_name`, `join_date` FROM `employees` WHERE `status` = 'Active' OR `status` = 1"; 
$emp_result = @mysqli_query($conn, $emp_sql);
if ($emp_result && mysqli_num_rows($emp_result) > 0) {
    while ($row = mysqli_fetch_assoc($emp_result)) { $employees[] = $row; }
}

$salary_components = [];
$comp_sql = "SELECT `id`, `salary_type`, `component_category`, `code`, `component_name`, `expression`, `status` FROM `salary_components` WHERE `status` = 'Active' OR `status` = '1'"; 
$comp_result = @mysqli_query($conn, $comp_sql);
if ($comp_result && mysqli_num_rows($comp_result) > 0) {
    while ($row = mysqli_fetch_assoc($comp_result)) {
        $salary_components[] = $row;
    }
}

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    /* Change color here if needed */
    margin: 25px 0;
    /* Adjust spacing around the line here */
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

.card-top-bar { display: flex; align-items: center; margin-bottom: 20px; }
.breadcrumb { font-size: 15px; color: #4B5563; }
.breadcrumb strong { color: #111827; font-weight: 600; }
.instructions-block { font-size: 13.5px; color: #4B5563; line-height: 1.8; margin-bottom: 30px; }
.instructions-block strong { color: #111827; font-size: 14px; }
.section-heading { font-size: 12px; font-weight: 700; color: #111827; margin-bottom: 15px; text-transform: uppercase; }
.selection-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; max-width: 800px; }
.search-line-wrapper { position: relative; }
.search-line-wrapper svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: #9CA3AF; fill: none; stroke-width: 2; }
.search-line-wrapper input { width: 100%; padding: 8px 10px 8px 36px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 14px; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
.search-line-wrapper input:focus { border-color: #0066FF; }
.employee-chip { display: inline-flex; align-items: center; gap: 10px; border: 1px solid #D1D5DB; padding: 6px 12px; border-radius: 4px; font-size: 14px; color: #111827; background: #fff; }
.employee-chip span.remove { cursor: pointer; color: #9CA3AF; font-weight: bold; font-size: 14px; margin-left: 5px; }
.employee-chip span.remove:hover { color: #EF4444; }
.line-input { width: 100%; padding: 8px 0; border: none; border-bottom: 1px solid #D1D5DB; font-size: 14px; color: #111827; background: transparent; outline: none; transition: border-color 0.2s; }
.line-input:focus { border-bottom-color: #0066FF; }
select.line-input { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 5l3 3 3-3'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right center; padding-right: 20px; }
.form-group label { display: block; font-size: 12px; color: #4B5563; margin-bottom: 8px; }

/* Data View (Tabs & Table) */
.data-view-wrapper { margin-top: 40px; padding-top: 30px; border-top: 1px dashed #E5E7EB; }
.data-tabs-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; }
.inner-tabs { display: flex; gap: 20px; border-bottom: 1px solid #E5E7EB; margin-bottom: 0;}
.inner-tab { font-size: 14px; color: #6B7280; text-decoration: none; padding-bottom: 10px; border-bottom: 2px solid transparent; font-weight: 500; transition: all 0.2s; cursor: pointer; }
.inner-tab:hover { color: #111827; }
.inner-tab.active { color: #0066FF; border-bottom-color: #0066FF; font-weight: 600; }

.btn-outline-primary { background: #fff; color: #0066FF; border: 1px solid #0066FF; padding: 8px 16px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
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
        <a href="ApprovePayslip">Approve Payslip</a> <span class="separator">|</span>
        <a href="EditPayslip"  class="payroll-tab active">Edit Payslip</a> <span class="separator">|</span>
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
            <div class="search-line-wrapper" id="empSearchWrapper">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="empSearchInput" list="employeeList" placeholder="Search by name or #code" autocomplete="off" onchange="handleEmployeeSelection()">
            </div>
            <div class="employee-chip" id="empChip" style="display:none;">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="#9CA3AF" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <span id="empChipText"></span>
                <span class="remove" onclick="removeEmployeeSelection()">&#x2715;</span>
            </div>
        </div>

        <div>
            <div class="section-heading">SELECT PAY PERIOD</div>
            <div class="form-group">
                <select id="payPeriodSelect" class="line-input" onchange="checkSelections()">
                    <option value="">Select Payperiod</option>
                </select>
            </div>
        </div>
    </div>

    <div class="data-view-wrapper" id="dataViewWrapper" style="display: none;">
        <div class="data-tabs-header">
            <div class="inner-tabs">
                <span class="inner-tab active" id="tabEarnings" onclick="switchDataTab('Earnings')">Earnings</span>
                <span class="inner-tab" id="tabDeductions" onclick="switchDataTab('Deductions')">Deductions</span>
                <span class="inner-tab" id="tabEmployer" onclick="switchDataTab('Employer Contribution')">Employer Contribution</span>
            </div>
            <button type="button" class="btn-outline-primary" onclick="showAddComponentForm()">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Add New Component
            </button>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="10%">S No.</th>
                        <th width="25%">Code</th>
                        <th width="45%">Salary Component</th>
                        <th width="20%">Amount</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                </tbody>
            </table>
        </div>
    </div>

    <div class="data-view-wrapper" id="addComponentWrapper" style="display: none;">
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
                        <option value="Employer">Employer Contribution</option>
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

<?php
    $page_content = ob_get_clean();
    include 'includes/header.php';
    echo $page_content;
    include 'includes/footer.php';
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('status') === 'added') {
        Swal.fire({
            title: 'Added Successfully!',
            text: 'The salary component has been added to the payslip.',
            icon: 'success',
            confirmButtonColor: '#0066FF'
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

// JSON data fetched from PHP variables
const dbComponents = <?= json_encode($salary_components) ?>;
const employeesData = <?= json_encode($employees) ?>;

let currentEmployeeCode = '';

// Empty structure for live AJAX data
let currentPayslipData = {
    Earnings: [],
    Deductions: [],
    'Employer Contribution': []
};

function populateComponentsDropdown() {
    const typeSelect = document.getElementById('salaryTypeSelect').value;
    const compSelect = document.getElementById('componentSelect');
    
    compSelect.innerHTML = '<option value="">Select Component</option>';

    if(typeSelect) {
        let expectedDBType = typeSelect;
        if(typeSelect === 'Earnings') expectedDBType = 'Earning';
        if(typeSelect === 'Deductions') expectedDBType = 'Deduction';

        const filtered = dbComponents.filter(c => 
            c.salary_type === typeSelect || 
            c.salary_type === expectedDBType ||
            c.component_category === typeSelect ||
            c.component_category === expectedDBType
        );
        
        filtered.forEach(comp => {
            compSelect.innerHTML += `<option value="${comp.id}">${comp.component_name} (${comp.code})</option>`;
        });
    }
}

function handleEmployeeSelection() {
    const input = document.getElementById('empSearchInput');
    const val = input.value.trim();
    if(val) {
        const match = val.match(/(.+) \(#(.+)\)/);
        let displayName = val;
        if(match) {
            displayName = match[1].trim(); 
            currentEmployeeCode = match[2].trim();
            document.getElementById('hiddenEmpCode').value = currentEmployeeCode;
            
            // Generate pay periods dynamically
            populatePayPeriods(currentEmployeeCode);
        }
        
        document.getElementById('empSearchWrapper').style.display = 'none';
        document.getElementById('empChipText').innerText = displayName;
        document.getElementById('empChip').style.display = 'inline-flex';
        
        checkSelections();
    }
}

function populatePayPeriods(empCode) {
    const select = document.getElementById('payPeriodSelect');
    select.innerHTML = '<option value="">Select Payperiod</option>';

    const emp = employeesData.find(e => e.employee_code === empCode);
    if (!emp || !emp.join_date) return;

    let joinDate = new Date(emp.join_date);
    let currentDate = new Date(); 

    if (isNaN(joinDate.getTime())) return; 

    let current = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    let start = new Date(joinDate.getFullYear(), joinDate.getMonth(), 1);

    while (current >= start) {
        let monthStr = current.toLocaleString('en-US', { month: 'short' });
        let year = current.getFullYear();
        let val = `${monthStr}-${year}`;
        
        select.innerHTML += `<option value="${val}">${val}</option>`;
        current.setMonth(current.getMonth() - 1); 
    }
}

function removeEmployeeSelection() {
    document.getElementById('empSearchInput').value = '';
    document.getElementById('empChip').style.display = 'none';
    document.getElementById('empSearchWrapper').style.display = 'block';
    
    document.getElementById('payPeriodSelect').innerHTML = '<option value="">Select Payperiod</option>';
    document.getElementById('hiddenEmpCode').value = '';
    currentEmployeeCode = '';
    checkSelections();
}

function checkSelections() {
    const isEmpSelected =
        document.getElementById('empChip').style.display === 'inline-flex';

    const periodVal =
        document.getElementById('payPeriodSelect').value;

    const isPeriodSelected = periodVal !== '';

    document.getElementById('hiddenPayPeriod').value = periodVal;

    if (isEmpSelected && isPeriodSelected) {

        const formData = new FormData();
        formData.append('ajax_action', 'API/get_payslip_data');
        formData.append('emp_code', currentEmployeeCode);
        formData.append('pay_period', periodVal);

        fetch('API/get_payslip_data.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not OK');
            }
            return response.json();
        })
        .then(data => {

            console.log('Payslip Data:', data);

            currentPayslipData = data;

            document.getElementById('dataViewWrapper').style.display = 'block';
            document.getElementById('addComponentWrapper').style.display = 'none';

            switchDataTab('Earnings');
        })
        .catch(error => {
            console.error('Error fetching payslip data:', error);
        });

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
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:#9CA3AF;">No data available for this category.</td></tr>`;
    }
}

function showAddComponentForm() {
    document.getElementById('dataViewWrapper').style.display = 'none';
    document.getElementById('addComponentWrapper').style.display = 'block';
}

function hideAddComponentForm() {
    document.getElementById('addComponentWrapper').style.display = 'none';
    document.getElementById('dataViewWrapper').style.display = 'block';
}
</script>
<script src="includes/assets/scripts.js"></script>