<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
require_once 'includes/db_client.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_structure') {
    header('Content-Type: application/json');
    
    $emp_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
    $base_ctc = mysqli_real_escape_string($conn, $_POST['base_ctc']);
    $pf = (int)$_POST['pf_applicable'];
    $esi = (int)$_POST['esi_applicable'];
    $pt = (int)$_POST['pt_applicable'];
    $pt_state = mysqli_real_escape_string($conn, $_POST['pt_state']);
    $components_data = mysqli_real_escape_string($conn, $_POST['components_data']);
    $date = date('Y-m-d H:i:s');

    // Check if record exists
    $check_sql = "SELECT `id` FROM `salary_structures` WHERE `employee_id` = '$emp_id'";
    $check_res = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_res) > 0) {
        $sql = "UPDATE `salary_structures` SET 
                `base_ctc` = '$base_ctc', 
                `pf_applicable` = '$pf', 
                `esi_applicable` = '$esi', 
                `pt_applicable` = '$pt', 
                `pt_state` = '$pt_state', 
                `components_data` = '$components_data', 
                `updated_at` = '$date' 
                WHERE `employee_id` = '$emp_id'";
    } else {
        $sql = "INSERT INTO `salary_structures` (`employee_id`, `base_ctc`, `pf_applicable`, `esi_applicable`, `pt_applicable`, `pt_state`, `components_data`, `created_at`, `updated_at`) 
                VALUES ('$emp_id', '$base_ctc', '$pf', '$esi', '$pt', '$pt_state', '$components_data', '$date', '$date')";
    }

    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Saved']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit();
}
$page_title = 'Payroll - Salary Structure';
$pt_states = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 
    'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 
    'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 
    'Uttarakhand', 'West Bengal'
];

// 2. FETCH EMPLOYEES
$employees = [];
$emp_sql = "SELECT `id`, `employee_code`, `employee_name`, `ctc_monthly` FROM `employees` WHERE `status` = 'Active' OR `status` = '1'"; 
$emp_result = @mysqli_query($conn, $emp_sql);
if ($emp_result && mysqli_num_rows($emp_result) > 0) {
    while ($row = mysqli_fetch_assoc($emp_result)) {
        $employees[] = $row;
    }
}

// 3. FETCH STATUTORY TEMPLATE SETTINGS
$statutory = ['id' => null, 'pf_applicable' => 0, 'esi_applicable' => 0, 'pt_state' => ''];
$template_sql = "SELECT `id`, `name`, `pt_state`, `pf_applicable`, `esi_applicable`, `remarks`, `status`, `created_at`, `updated_at` FROM `ctc_templates` WHERE `status` = 'active' OR `status` = '1' LIMIT 1";
$template_result = @mysqli_query($conn, $template_sql);
if ($template_result && $row = mysqli_fetch_assoc($template_result)) {
    $statutory = $row;
}

// 4. FETCH STATUTORY EMPLOYEE CONFIGURATIONS
$stat_configs = [];
$stat_sql = "SELECT `id`, `employee_id`, `epf_emp_rate`, `epf_employer_rate`, `pension_fund`, `edli`, `pf_admin`, `edli_admin`, `pf_max_ceil`, `pf_edli_max_ceil`, `esi_emp`, `esi_employer`, `esi_max_ceil`, `tax_start`, `tax_end`, `updated_at` FROM `statutory_employee_config` WHERE 1";
$stat_result = @mysqli_query($conn, $stat_sql);
if ($stat_result) {
    while ($row = mysqli_fetch_assoc($stat_result)) {
        $stat_configs[$row['employee_id']] = $row;
    }
}

// 5. FETCH COMPONENTS FROM ACTIVE TEMPLATE
$template_components = [
    'Earnings' => [],
    'Deductions' => [],
    'Employer Contribution' => []
];
if (!empty($statutory['id'])) {
    $template_id = $statutory['id'];
    $comp_sql = "SELECT `id`, `template_id`, `component_type`, `component_name`, `calc_type`, `calc_value`, `unit`, `sort_order`, `created_at` FROM `ctc_template_components` WHERE `template_id` = '$template_id' ORDER BY `sort_order` ASC";
    $comp_result = @mysqli_query($conn, $comp_sql);
    if ($comp_result) {
        while ($row = mysqli_fetch_assoc($comp_result)) {
            $type = $row['component_type']; 
            if (stripos($type, 'earning') !== false) {
                $template_components['Earnings'][] = $row;
            } elseif (stripos($type, 'deduction') !== false) {
                $template_components['Deductions'][] = $row;
            } else {
                $template_components['Employer Contribution'][] = $row;
            }
        }
    }
}

// 6. FETCH MASTER SALARY COMPONENTS
$master_components = [];
$master_sql = "SELECT `id`, `salary_type`, `component_category`, `code`, `component_name`, `expression`, `status` FROM `salary_components` WHERE `status` = '1' OR `status` = 'Active'";
$master_result = @mysqli_query($conn, $master_sql);
if ($master_result) {
    while ($row = mysqli_fetch_assoc($master_result)) {
        $master_components[] = $row;
    }
}

// 7. FETCH PAYROLL VARIABLES
$variables = [];
$var_sql = "SELECT `name`, `value`, `expression`, `execution_order` FROM `payroll_variables` WHERE `status` = 1 ORDER BY `execution_order` ASC";
$var_result = @mysqli_query($conn, $var_sql);
if ($var_result) {
    while ($row = mysqli_fetch_assoc($var_result)) {
        $variables[] = $row;
    }
}

// 8. FETCH EXISTING SALARY STRUCTURES (For Auto-Loading)
$saved_structures = [];
$ss_sql = "SELECT `employee_id`, `base_ctc`, `pf_applicable`, `esi_applicable`, `pt_applicable`, `pt_state`, `components_data` FROM `salary_structures` WHERE 1";
$ss_result = @mysqli_query($conn, $ss_sql);
if ($ss_result) {
    while ($row = mysqli_fetch_assoc($ss_result)) {
        $saved_structures[$row['employee_id']] = $row;
    }
}

ob_start();
?>
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

.breadcrumb {
    font-size: 14px;
    color: #111827;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
}

.breadcrumb span {
    color: #6B7280;
}

.search-container {
    display: flex;
    gap: 12px;
    margin-bottom: 30px;
    align-items: center;
}

.search-input-wrapper {
    position: relative;
    width: 320px;
}

.search-input-wrapper svg {
    position: absolute;
    left: 12px;
    top: 10px;
    color: #9CA3AF;
    width: 16px;
    height: 16px;
}

.search-input {
    padding: 8px 12px 8px 36px;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    width: 100%;
    outline: none;
    font-size: 14px;
    transition: border-color 0.2s;
}

.search-input:focus {
    border-color: #0066FF;
    box-shadow: 0 0 0 2px rgba(0, 102, 255, 0.1);
}

.search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    margin-top: 4px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    display: none;
    max-height: 200px;
    overflow-y: auto;
    z-index: 10;
}

.search-dropdown-item {
    padding: 8px 12px;
    font-size: 14px;
    color: #374151;
    cursor: pointer;
    border-bottom: 1px solid #F3F4F6;
}

.search-dropdown-item:last-child {
    border-bottom: none;
}

.search-dropdown-item:hover {
    background: #EFF6FF;
    color: #0066FF;
}

.btn-primary {
    background: #0066FF;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
}

.btn-primary:hover {
    background: #0052CC;
}

.selected-emp-wrapper {
    display: none;
    align-items: center;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    padding: 4px 8px;
    width: 320px;
    background: #fff;
}

.selected-emp-wrapper svg {
    color: #9CA3AF;
    width: 16px;
    height: 16px;
    margin-right: 8px;
}

.selected-emp-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #F3F4F6;
    padding: 2px 10px;
    border-radius: 16px;
    font-size: 13px;
    color: #111827;
}

.remove-emp {
    cursor: pointer;
    font-weight: bold;
    color: #6B7280;
    font-size: 14px;
    margin-top: -2px;
}

.remove-emp:hover {
    color: #EF4444;
}

.statutory-section {
    background: #fff;
    border: 1px solid #F3F4F6;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    padding: 24px;
    border-radius: 8px;
    margin-bottom: 24px;
    position: relative;
}

.statutory-title {
    font-size: 12px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 20px;
    text-transform: uppercase;
}

.edit-icon {
    position: absolute;
    right: 24px;
    top: 24px;
    color: #0066FF;
    cursor: pointer;
    width: 18px;
    height: 18px;
}

.statutory-grid {
    display: flex;
    gap: 48px;
    align-items: center;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #374151;
    cursor: pointer;
}

.custom-checkbox {
    appearance: none;
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    background-color: #22C55E;
    border-radius: 3px;
    cursor: pointer;
    position: relative;
    border: none;
    outline: none;
}

.custom-checkbox:after {
    content: '';
    position: absolute;
    left: 6px;
    top: 2px;
    width: 6px;
    height: 12px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    display: block;
}

.custom-checkbox:not(:checked) {
    background-color: #fff;
    border: 1px solid #D1D5DB;
}

.custom-checkbox:not(:checked):after {
    display: none;
}

.custom-checkbox:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

.state-dropdown {
    padding: 4px 8px;
    border: 1px solid transparent;
    border-radius: 4px;
    font-size: 14px;
    color: #111827;
    cursor: pointer;
    background: transparent;
    outline: none;
}

.state-dropdown:disabled {
    cursor: not-allowed;
    opacity: 0.8;
    pointer-events: none;
}

.state-dropdown:not(:disabled) {
    border: 1px solid #D1D5DB;
    background: #fff;
}

.modern-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    font-size: 14px;
    color: #111827;
    background-color: #fff;
    outline: none;
    transition: border-color 0.2s;
    appearance: none;
    background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%236B7280%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E');
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 16px;
    padding-right: 32px;
}

.modern-select:focus {
    border-color: #0066FF;
    box-shadow: 0 0 0 2px rgba(0, 102, 255, 0.1);
}

.inline-input {
    padding: 8px 12px;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    width: 140px;
    font-size: 14px;
    outline: none;
    transition: 0.2s;
}

.inline-input:focus {
    border-color: #0066FF;
    box-shadow: 0 0 0 2px rgba(0, 102, 255, 0.1);
}

.tabs-container {
    border-bottom: 1px solid #E5E7EB;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
}

.tabs {
    display: flex;
    gap: 32px;
    margin-bottom: -1px;
}

.tab {
    padding: 12px 0;
    color: #6B7280;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    border-bottom: 2px solid transparent;
}

.tab.active {
    color: #0066FF;
    border-bottom-color: #0066FF;
}

.btn-add {
    background: #fff;
    color: #0066FF;
    border: 1px solid #0066FF;
    padding: 6px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
}

.btn-add:hover {
    background: #EFF6FF;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    text-align: left;
    margin-bottom: 24px;
}

.data-table th {
    background: #F9FAFB;
    padding: 16px;
    color: #6B7280;
    font-weight: 500;
    border-bottom: 1px solid #E5E7EB;
}

.data-table td {
    padding: 16px;
    border-bottom: 1px solid #F3F4F6;
    color: #111827;
    vertical-align: middle;
}

.data-table tr:hover {
    background: #F9FAFB;
}

.row-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    align-items: center;
}

.btn-delete {
    background: #fff;
    border: 1px solid #EF4444;
    color: #EF4444;
    padding: 6px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: 0.2s;
}

.btn-delete:hover {
    background: #FEF2F2;
}

.btn-save {
    background: #0066FF;
    border: 1px solid #0066FF;
    color: #fff;
    padding: 6px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}

.line-divider {
    border-bottom: 1px solid #D1D5DB;
    width: 120px;
    display: inline-block;
    margin-right: 20px;
}

.arrow-icon {
    color: #111827;
    font-weight: bold;
    cursor: pointer;
    font-size: 18px;
    padding: 4px 8px;
    border-radius: 4px;
    transition: 0.2s;
}

.arrow-icon:hover {
    color: #0066FF;
    background: #EFF6FF;
}

.row-total td {
    font-weight: 700;
    color: #111827;
    background: #fff;
    border-top: 1px solid #E5E7EB;
    padding-top: 20px;
}

.auto-save-text {
    font-size: 13px;
    font-weight: 500;
    transition: opacity 0.3s;
    opacity: 0;
}

.auto-save-text.show {
    opacity: 1;
    color: #10B981;
}
</style>

<script>
const dbEmployees = <?= json_encode($employees) ?>;
const statConfigs = <?= json_encode($stat_configs) ?>;
const dbTemplateComponents = <?= json_encode($template_components) ?>;
const dbMasterComponents = <?= json_encode($master_components) ?>;
const dbVariables = <?= json_encode($variables) ?>;
const dbSavedStructures = <?= json_encode($saved_structures) ?>;

let currentActiveTab = 'earnings';
let currentEmployeeId = null;
let currentBaseCtc = 0;
</script>

<div class="payroll-header-wrapper">
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="javascript:history.back()" class="btn-back" title="Go Back">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
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
        <a href="SalaryStructure" class="active">Salary Structure</a> <span class="separator">|</span>
        <a href="Timesheet">Timesheet</a>
    </div>
</div>

<div class="payroll-card">
    <div class="breadcrumb">
        <div><strong>Payroll</strong> <span>&nbsp;&gt;&nbsp; Salary Structure</span></div>
        <div id="saveStatus" class="auto-save-text">✓ All changes saved</div>
    </div>

    <!-- MODERN SEARCH UI -->
    <div class="search-container">
        <div class="search-input-wrapper" id="searchInputWrapper">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" class="search-input" id="empSearch" placeholder="Search by name or #code"
                autocomplete="off">
            <div class="search-dropdown" id="searchDropdown"></div>
        </div>

        <div class="selected-emp-wrapper" id="selectedEmpWrapper">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <div class="selected-emp-pill">
                <span id="selectedEmpName"></span>
                <span class="remove-emp" onclick="clearSearch()" title="Clear Selection">×</span>
            </div>
        </div>
        <button class="btn-primary" id="btnGetDetails" onclick="loadSalaryDetails()">Get Details</button>
    </div>

    <div id="salaryDetailsSection" style="display: none;">

        <div class="statutory-section">
            <div class="statutory-title">CALCULATE STATUTORY
                <?= !empty($statutory['name']) ? '- <span style="color:#0066FF; text-transform:none;">('.$statutory['name'].')</span>' : '' ?>
            </div>

            <svg class="edit-icon" id="editStatutoryBtn" onclick="toggleStatutoryEdit()" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            <button class="btn-save" id="saveStatutoryBtn" onclick="saveStatutorySettings()"
                style="display:none; position:absolute; right:24px; top:18px;">Save Changes</button>

            <div class="statutory-grid">
                <label class="checkbox-group">
                    <input type="checkbox" id="chk_pf" class="custom-checkbox" disabled> PF (Provident Fund)
                </label>
                <label class="checkbox-group">
                    <input type="checkbox" id="chk_esi" class="custom-checkbox" disabled> ESI (Employee State Insurance)
                </label>
                <label class="checkbox-group">
                    <input type="checkbox" id="chk_pt" class="custom-checkbox" disabled> PT (Professional Tax)
                </label>
                <div class="checkbox-group" style="margin-left: -20px;">
                    <span style="color: #6B7280; font-size: 13px;">State :</span>
                    <select class="modern-select state-dropdown" id="sel_state"
                        style="padding: 6px 32px 6px 12px; width: 180px;" disabled onchange="checkPtState()">
                        <option value="">-- Select State --</option>
                        <?php foreach($pt_states as $state): ?>
                        <option value="<?= $state ?>"><?= $state ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="tabs-container">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('earnings')">Earnings</div>
                <div class="tab" onclick="switchTab('deductions')">Deductions</div>
                <div class="tab" onclick="switchTab('employer_contribution')">Employer Contribution</div>
                <div class="tab" onclick="switchTab('employee_variables')">Employee Variables</div>
            </div>
            <button class="btn-add" onclick="addNewComponent()">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add New Component
            </button>
        </div>

        <!-- TAB CONTENT AREAS -->
        <div id="tab-earnings" class="tab-content"
            style="border: 1px solid #E5E7EB; border-top: none; border-radius: 0 0 8px 8px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="8%">S No.</th>
                        <th width="30%">Salary Component</th>
                        <th width="20%">Amount</th>
                        <th width="42%">Remarks</th>
                    </tr>
                </thead>
                <tbody id="earningsTableBody"></tbody>
                <tfoot>
                    <tr class="row-total">
                        <td colspan="2" style="padding-left: 100px;">Total</td>
                        <td id="earningsTotal" colspan="2">₹0</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div id="tab-deductions" class="tab-content"
            style="display: none; border: 1px solid #E5E7EB; border-top: none; border-radius: 0 0 8px 8px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="10%">S No.</th>
                        <th width="35%">Salary Component</th>
                        <th width="25%">Amount</th>
                        <th width="30%">Remarks</th>
                    </tr>
                </thead>
                <tbody id="deductionsTableBody"></tbody>
                <tfoot>
                    <tr class="row-total">
                        <td colspan="2" style="text-align:center;">Total</td>
                        <td id="deductionsTotal" colspan="2">₹0</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div id="tab-employer_contribution" class="tab-content"
            style="display: none; border: 1px solid #E5E7EB; border-top: none; border-radius: 0 0 8px 8px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="10%">S No.</th>
                        <th width="35%">Salary Component</th>
                        <th width="25%">Amount</th>
                        <th width="30%">Remarks</th>
                    </tr>
                </thead>
                <tbody id="employerTableBody"></tbody>
                <tfoot>
                    <tr class="row-total">
                        <td colspan="2" style="text-align:center;">Total</td>
                        <td id="employerTotal" colspan="2">₹0</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div id="tab-employee_variables" class="tab-content"
            style="display: none; border: 1px solid #E5E7EB; border-top: none; border-radius: 0 0 8px 8px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="10%">S No.</th>
                        <th width="30%">Payroll variable</th>
                        <th width="20%">Value</th>
                        <th width="30%">Remarks</th>
                        <th width="10%">Execution Order</th>
                    </tr>
                </thead>
                <tbody id="variablesTableBody"></tbody>
            </table>
        </div>

    </div>
</div>

<script>
// --- FORMULA PARSER ---
function parseExpression(expression, baseAmount) {
    if (!expression) return 0;
    try {
        let parsed = expression.toUpperCase().replace(/GRSAL/g, baseAmount).replace(/MONCTC/g, baseAmount);
        if (parsed.includes('%')) {
            parsed = parsed.replace(/([0-9.]+)%/g, "($1 / 100)");
        }
        const result = new Function('return ' + parsed)();
        return isNaN(result) ? 0 : parseFloat(result);
    } catch (e) {
        return 0;
    }
}

// --- SEARCH LOGIC ---
const searchInput = document.getElementById('empSearch');
const searchDropdown = document.getElementById('searchDropdown');

searchInput.addEventListener('input', function() {
    const val = this.value.toLowerCase().trim();
    searchDropdown.innerHTML = '';

    if (!val) {
        searchDropdown.style.display = 'none';
        return;
    }

    const filtered = dbEmployees.filter(emp =>
        emp.employee_name.toLowerCase().includes(val) ||
        emp.employee_code.toLowerCase().includes(val)
    );

    if (filtered.length > 0) {
        filtered.forEach(emp => {
            const div = document.createElement('div');
            div.className = 'search-dropdown-item';
            div.innerText = `${emp.employee_name} - #${emp.employee_code}`;
            div.onclick = function() {
                searchInput.value = this.innerText;
                currentEmployeeId = emp.id;
                currentBaseCtc = parseFloat(emp.ctc_monthly) || 0;
                searchDropdown.style.display = 'none';
                loadSalaryDetails();
            };
            searchDropdown.appendChild(div);
        });
        searchDropdown.style.display = 'block';
    } else {
        const div = document.createElement('div');
        div.className = 'search-dropdown-item';
        div.innerText = 'No employee found';
        div.style.color = '#9CA3AF';
        searchDropdown.appendChild(div);
        searchDropdown.style.display = 'block';
    }
});

document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
        searchDropdown.style.display = 'none';
    }
});

function loadSalaryDetails() {
    if (searchInput.value.trim() !== '') {
        document.getElementById('searchInputWrapper').style.display = 'none';
        document.getElementById('btnGetDetails').style.display = 'none';
        document.getElementById('selectedEmpName').innerText = searchInput.value;
        document.getElementById('selectedEmpWrapper').style.display = 'flex';
        document.getElementById('salaryDetailsSection').style.display = 'block';

        let saved = dbSavedStructures[currentEmployeeId];

        if (saved) {
            // Re-sync base CTC to the master employee table value just in case it updated
            currentBaseCtc = parseFloat(dbEmployees.find(e => e.id === currentEmployeeId).ctc_monthly) || parseFloat(
                saved.base_ctc) || 0;

            document.getElementById('chk_pf').checked = (saved.pf_applicable == 1);
            document.getElementById('chk_esi').checked = (saved.esi_applicable == 1);
            document.getElementById('chk_pt').checked = (saved.pt_applicable == 1);
            document.getElementById('sel_state').value = saved.pt_state;

            try {
                let parsedData = JSON.parse(saved.components_data);
                renderFromSavedData(parsedData);
            } catch (e) {
                console.error("Failed to parse saved components", e);
                renderVariablesTable();
                calculateAndRenderComponents();
            }
        } else {
            // New structure - load from template settings
            document.getElementById('chk_pf').checked = <?= $statutory['pf_applicable'] == 1 ? 'true' : 'false' ?>;
            document.getElementById('chk_esi').checked = <?= $statutory['esi_applicable'] == 1 ? 'true' : 'false' ?>;
            document.getElementById('sel_state').value = "<?= $statutory['pt_state'] ?>";
            document.getElementById('chk_pt').checked = "<?= $statutory['pt_state'] ?>" !== "";

            renderVariablesTable();
            calculateAndRenderComponents();
            autoSaveStructure();
        }
    }
}

function clearSearch() {
    searchInput.value = '';
    currentEmployeeId = null;
    currentBaseCtc = 0;
    document.getElementById('selectedEmpWrapper').style.display = 'none';
    document.getElementById('salaryDetailsSection').style.display = 'none';
    document.getElementById('searchInputWrapper').style.display = 'block';
    document.getElementById('btnGetDetails').style.display = 'block';
    document.getElementById('saveStatus').classList.remove('show');
}

// --- STATUTORY EDIT LOGIC ---
function toggleStatutoryEdit() {
    document.getElementById('chk_pf').disabled = false;
    document.getElementById('chk_esi').disabled = false;
    document.getElementById('sel_state').disabled = false;
    document.getElementById('chk_pt').disabled = false;

    document.getElementById('editStatutoryBtn').style.display = 'none';
    document.getElementById('saveStatutoryBtn').style.display = 'block';
}

function checkPtState() {
    const sel = document.getElementById('sel_state');
    document.getElementById('chk_pt').checked = sel.value !== '';
}

function saveStatutorySettings() {
    document.getElementById('chk_pf').disabled = true;
    document.getElementById('chk_esi').disabled = true;
    document.getElementById('chk_pt').disabled = true;
    document.getElementById('sel_state').disabled = true;
    document.getElementById('editStatutoryBtn').style.display = 'block';
    document.getElementById('saveStatutoryBtn').style.display = 'none';

    calculateAndRenderComponents();
    autoSaveStructure();
}


// --- TABS & DYNAMIC ADD/EDIT/DELETE ---
function switchTab(tabId) {
    currentActiveTab = tabId;
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabId).style.display = 'block';
    event.target.classList.add('active');
}

function addNewComponent() {
    let tbodyId = currentActiveTab === 'earnings' ? 'earningsTableBody' :
        (currentActiveTab === 'deductions' ? 'deductionsTableBody' :
            (currentActiveTab === 'employer_contribution' ? 'employerTableBody' : 'variablesTableBody'));

    let tbody = document.getElementById(tbodyId);
    if (tbody.innerHTML.includes('No records found')) {
        tbody.innerHTML = '';
    }
    let rowCount = tbody.querySelectorAll('tr').length + 1;
    let tr = document.createElement('tr');

    if (currentActiveTab === 'employee_variables') {
        let selectHtml = `<select class="modern-select component-select">
            <option value="">-- Select Variable --</option>`;
        dbVariables.forEach(v => {
            selectHtml += `<option value="${v.value}" data-name="${v.name}">${v.name}</option>`;
        });
        selectHtml += `</select>`;

        tr.innerHTML = `
            <td>${rowCount}</td>
            <td>${selectHtml}</td>
            <td><input type="number" class="inline-input" value="0"></td>
            <td>Custom Variable</td>
            <td>
                <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                    <span>New</span>
                    <div class="row-actions">
                        <button class="btn-delete" onclick="deleteRow(this)">Delete</button>
                        <button class="btn-save" onclick="saveVarRow(this)">Save</button>
                    </div>
                </div>
            </td>
        `;
    } else {
        let typeMap = {
            'earnings': 'Earning',
            'deductions': 'Deduction',
            'employer_contribution': 'Employer Contribution'
        };
        let requiredType = typeMap[currentActiveTab].toLowerCase();
        let availableOpts = dbMasterComponents.filter(c => c.salary_type.toLowerCase().includes(requiredType));

        let selectHtml = `<select class="modern-select component-select" onchange="autoFillAmount(this)">
            <option value="">-- Select Component --</option>`;
        availableOpts.forEach(opt => {
            selectHtml +=
                `<option value="${opt.expression}" data-name="${opt.component_name}">${opt.component_name}</option>`;
        });
        selectHtml += `</select>`;

        tr.innerHTML = `
            <td>${rowCount}</td>
            <td>${selectHtml}</td>
            <td><input type="number" class="inline-input" value="0"></td>
            <td>
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div class="line-divider"></div>
                    <div class="row-actions">
                        <button class="btn-delete" onclick="deleteRow(this)">Delete</button>
                        <button class="btn-save" onclick="saveComponentRow(this)">Save <span style="font-size:10px; margin-left:4px;">▼</span></button>
                    </div>
                </div>
            </td>
        `;
    }

    tbody.appendChild(tr);
}

function autoFillAmount(selectElem) {
    const expr = selectElem.value;
    if (expr) {
        let amount = parseExpression(expr, currentBaseCtc);
        selectElem.closest('tr').querySelector('.inline-input').value = amount.toFixed(0);
    }
}

// --- SWEETALERT CONFIRMATION FOR DELETE ---
function deleteRow(btn) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This component will be removed from the structure.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            btn.closest('tr').remove();
            calculateTotals();
            autoSaveStructure();

            Swal.fire({
                title: 'Deleted!',
                text: 'The component has been removed.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

function saveComponentRow(btn) {
    const tr = btn.closest('tr');
    const selectElem = tr.querySelector('.component-select');
    let compName = '';

    if (selectElem) {
        if (selectElem.selectedIndex === 0) {
            alert('Please select an option first.');
            return;
        }
        compName = selectElem.options[selectElem.selectedIndex].getAttribute('data-name');
    } else {
        compName = tr.cells[1].innerHTML;
    }

    const amount = parseFloat(tr.querySelector('.inline-input').value || 0);

    tr.innerHTML = `
        <td>${tr.cells[0].innerText}</td>
        <td class="comp-name-cell">${compName}</td>
        <td class="amount-cell" data-val="${amount}">₹${amount.toFixed(0)}</td>
        <td style="text-align: right; padding-right: 24px;">
            <span class="arrow-icon" title="Edit row" onclick="editComponentRow(this)">&rsaquo;</span>
        </td>
    `;
    calculateTotals();
    autoSaveStructure();
}

function editComponentRow(iconElem) {
    const tr = iconElem.closest('tr');
    if (tr.querySelector('.inline-input')) return;

    const compName = tr.querySelector('.comp-name-cell').innerText.trim();
    const amountCell = tr.querySelector('.amount-cell');
    const currentAmount = parseFloat(amountCell.getAttribute('data-val') || amountCell.innerText.replace(/[^0-9.-]+/g,
        ""));

    let typeMap = {
        'earnings': 'Earning',
        'deductions': 'Deduction',
        'employer_contribution': 'Employer Contribution'
    };
    let activeTypeRaw = tr.closest('.tab-content').id.replace('tab-', '');
    let requiredType = typeMap[activeTypeRaw].toLowerCase();

    let availableOpts = dbMasterComponents.filter(c => c.salary_type.toLowerCase().includes(requiredType));

    let selectHtml = `<select class="modern-select component-select" onchange="autoFillAmount(this)">
        <option value="">-- Select Component --</option>`;
    let found = false;
    availableOpts.forEach(opt => {
        let isSelected = (opt.component_name === compName) ? 'selected' : '';
        if (isSelected) found = true;
        selectHtml +=
            `<option value="${opt.expression}" data-name="${opt.component_name}" ${isSelected}>${opt.component_name}</option>`;
    });
    if (!found) selectHtml += `<option value="" data-name="${compName}" selected>${compName}</option>`;
    selectHtml += `</select>`;

    tr.cells[1].innerHTML = selectHtml;
    amountCell.innerHTML = `<input type="number" class="inline-input" value="${currentAmount}">`;

    tr.cells[3].innerHTML = `
        <div style="display:flex; align-items:center; justify-content:flex-end;">
            <div class="line-divider"></div>
            <div class="row-actions">
                <button class="btn-delete" onclick="deleteRow(this)">Delete</button>
                <button class="btn-save" onclick="saveComponentRow(this)">Save <span style="font-size:10px; margin-left:4px;">▼</span></button>
            </div>
        </div>
    `;
}

function saveVarRow(btn) {
    const tr = btn.closest('tr');
    const selectElem = tr.querySelector('.component-select');
    let varName = '';

    if (selectElem) {
        if (selectElem.selectedIndex === 0) {
            alert('Please select a variable first.');
            return;
        }
        varName = selectElem.options[selectElem.selectedIndex].getAttribute('data-name');
    } else {
        varName = tr.cells[1].innerHTML;
    }

    const amount = parseFloat(tr.querySelector('.inline-input').value || 0);
    let orderVal = tr.cells[4].innerText.replace(/[^0-9]/g, '') || "1";

    tr.innerHTML = `
        <td>${tr.cells[0].innerText}</td>
        <td class="var-name-cell">${varName}</td>
        <td class="amount-cell" data-val="${amount}">₹${amount.toFixed(0)}</td>
        <td>Derived Custom Value</td>
        <td style="display:flex; justify-content:space-between; align-items:center;">
            ${orderVal} <span class="arrow-icon" title="Edit row" onclick="editVarRow(this)">&rsaquo;</span>
        </td>
    `;
    autoSaveStructure();
}

function editVarRow(iconElem) {
    const tr = iconElem.closest('tr');
    if (tr.querySelector('.inline-input')) return;

    const varName = tr.querySelector('.var-name-cell').innerText.trim();
    const amountCell = tr.querySelector('.amount-cell');
    const currentAmount = parseFloat(amountCell.getAttribute('data-val') || amountCell.innerText.replace(/[^0-9.-]+/g,
        ""));

    let selectHtml = `<select class="modern-select component-select">
        <option value="">-- Select Variable --</option>`;
    dbVariables.forEach(v => {
        let isSelected = (v.name === varName) ? 'selected' : '';
        selectHtml += `<option value="${v.value}" data-name="${v.name}" ${isSelected}>${v.name}</option>`;
    });
    if (!selectHtml.includes('selected')) {
        selectHtml += `<option value="" data-name="${varName}" selected>${varName}</option>`;
    }
    selectHtml += `</select>`;

    tr.cells[1].innerHTML = selectHtml;
    amountCell.innerHTML = `<input type="number" class="inline-input" value="${currentAmount}">`;

    tr.cells[4].innerHTML = `
        <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
            <span>Edit</span>
            <div class="row-actions">
                <button class="btn-delete" onclick="deleteRow(this)">Delete</button>
                <button class="btn-save" onclick="saveVarRow(this)">Save</button>
            </div>
        </div>
    `;
}

function calculateTotals() {
    let eTotal = 0,
        dTotal = 0,
        emTotal = 0;
    document.querySelectorAll('#earningsTableBody .amount-cell').forEach(td => eTotal += parseFloat(td.getAttribute(
        'data-val') || 0));
    document.querySelectorAll('#deductionsTableBody .amount-cell').forEach(td => dTotal += parseFloat(td.getAttribute(
        'data-val') || 0));
    document.querySelectorAll('#employerTableBody .amount-cell').forEach(td => emTotal += parseFloat(td.getAttribute(
        'data-val') || 0));

    if (document.getElementById('earningsTotal')) document.getElementById('earningsTotal').innerText =
        `₹${eTotal.toFixed(0)}`;
    if (document.getElementById('deductionsTotal')) document.getElementById('deductionsTotal').innerText =
        `₹${dTotal.toFixed(0)}`;
    if (document.getElementById('employerTotal')) document.getElementById('employerTotal').innerText =
        `₹${emTotal.toFixed(0)}`;
}

// --- RENDER FUNCTIONS ---
function renderFromSavedData(data) {
    const buildRows = (arr, type) => {
        let html = '';
        if (!arr || arr.length === 0)
        return `<tr><td colspan="4" style="text-align:center; padding: 40px; color: #9CA3AF;">No records found</td></tr>`;

        arr.forEach((item, idx) => {
            let itemAmount = item.amount;
            if (type === 'var' && (item.name.includes('GRSAL') || item.name.includes('MONCTC'))) {
                itemAmount = currentBaseCtc;
            }
            html += `
            <tr>
                <td>${idx + 1}</td>
                <td class="${type === 'var' ? 'var-name-cell' : 'comp-name-cell'}">${item.name}</td>
                <td class="amount-cell" data-val="${itemAmount}">₹${itemAmount.toFixed(0)}</td>
                ${type === 'var' ? '<td>Derived Custom Value</td>' : ''}
                <td style="${type === 'var' ? 'display:flex; justify-content:space-between; align-items:center;' : 'text-align: right; padding-right: 24px;'}">
                    ${type === 'var' ? `1 <span class="arrow-icon" title="Edit row" onclick="editVarRow(this)">&rsaquo;</span>` : `<span class="arrow-icon" title="Edit row" onclick="editComponentRow(this)">&rsaquo;</span>`}
                </td>
            </tr>`;
        });
        return html;
    };

    document.getElementById('earningsTableBody').innerHTML = buildRows(data.earnings, 'comp');
    document.getElementById('deductionsTableBody').innerHTML = buildRows(data.deductions, 'comp');
    document.getElementById('employerTableBody').innerHTML = buildRows(data.employer_contributions, 'comp');
    document.getElementById('variablesTableBody').innerHTML = buildRows(data.variables, 'var');

    calculateTotals();
}

function renderVariablesTable() {
    const tbody = document.getElementById('variablesTableBody');
    tbody.innerHTML = `
        <tr>
            <td>1</td>
            <td class="var-name-cell">GRSAL (Base Amount)</td>
            <td class="amount-cell" data-val="${currentBaseCtc}">₹${currentBaseCtc.toFixed(0)}</td>
            <td>Derived from employee ctc_monthly</td>
            <td style="display:flex; justify-content:space-between; align-items:center;">1 <span class="arrow-icon" title="Edit row" onclick="editVarRow(this)">&rsaquo;</span></td>
        </tr>
        <tr>
            <td>2</td>
            <td class="var-name-cell">MONCTC</td>
            <td class="amount-cell" data-val="${currentBaseCtc}">₹${currentBaseCtc.toFixed(0)}</td>
            <td>Derived from employee ctc_monthly</td>
            <td style="display:flex; justify-content:space-between; align-items:center;">1 <span class="arrow-icon" title="Edit row" onclick="editVarRow(this)">&rsaquo;</span></td>
        </tr>
    `;
}

function calculateAndRenderComponents() {
    let baseAmount = currentBaseCtc || 0;
    let globalEarningTotal = 0;

    let eBody = document.getElementById('earningsTableBody');
    eBody.innerHTML = '';
    if (dbTemplateComponents['Earnings'] && dbTemplateComponents['Earnings'].length > 0) {
        dbTemplateComponents['Earnings'].forEach((comp, idx) => {
            let calcVal = parseFloat(comp.calc_value) || 0;
            let isFixed = (comp.calc_type && comp.calc_type.toLowerCase().includes('fixed'));
            let amount = isFixed ? calcVal : (baseAmount * calcVal) / 100;
            globalEarningTotal += amount;
            eBody.innerHTML +=
                `<tr><td>${idx + 1}</td><td class="comp-name-cell">${comp.component_name}</td><td class="amount-cell" data-val="${amount}">₹${amount.toFixed(0)}</td><td style="text-align: right; padding-right: 24px;"><span class="arrow-icon" title="Edit row" onclick="editComponentRow(this)">&rsaquo;</span></td></tr>`;
        });
    } else {
        eBody.innerHTML =
            `<tr><td colspan="4" style="text-align:center; padding: 40px; color: #9CA3AF;">No earnings records found</td></tr>`;
    }

    let empConfig = statConfigs[currentEmployeeId] || null;

    let dBody = document.getElementById('deductionsTableBody');
    dBody.innerHTML = '';
    let dIndex = 1;
    if (dbTemplateComponents['Deductions'] && dbTemplateComponents['Deductions'].length > 0) {
        dbTemplateComponents['Deductions'].forEach((comp) => {
            let calcVal = parseFloat(comp.calc_value) || 0;
            let isFixed = (comp.calc_type && comp.calc_type.toLowerCase().includes('fixed'));
            let amount = isFixed ? calcVal : (baseAmount * calcVal) / 100;
            dBody.innerHTML +=
                `<tr><td>${dIndex++}</td><td class="comp-name-cell">${comp.component_name}</td><td class="amount-cell" data-val="${amount}">₹${amount.toFixed(0)}</td><td style="text-align: right; padding-right: 24px;"><span class="arrow-icon" title="Edit row" onclick="editComponentRow(this)">&rsaquo;</span></td></tr>`;
        });
    }

    if (document.getElementById('chk_pf').checked && empConfig) {
        let pfAmount = (baseAmount * 0.50) * (parseFloat(empConfig.epf_emp_rate) / 100);
        dBody.innerHTML +=
            `<tr><td>${dIndex++}</td><td class="comp-name-cell">EPF Contribution</td><td class="amount-cell" data-val="${pfAmount}">₹${pfAmount.toFixed(0)}</td><td style="text-align: right; padding-right: 24px;"><span class="arrow-icon" title="Edit row" onclick="editComponentRow(this)">&rsaquo;</span></td></tr>`;
    }
    if (document.getElementById('chk_esi').checked && empConfig) {
        let esiAmount = globalEarningTotal * (parseFloat(empConfig.esi_emp) / 100);
        dBody.innerHTML +=
            `<tr><td>${dIndex++}</td><td class="comp-name-cell">ESI Contribution</td><td class="amount-cell" data-val="${esiAmount}">₹${esiAmount.toFixed(0)}</td><td style="text-align: right; padding-right: 24px;"><span class="arrow-icon" title="Edit row" onclick="editComponentRow(this)">&rsaquo;</span></td></tr>`;
    }

    let emBody = document.getElementById('employerTableBody');
    emBody.innerHTML = '';
    let emIndex = 1;
    if (dbTemplateComponents['Employer Contribution'] && dbTemplateComponents['Employer Contribution'].length > 0) {
        dbTemplateComponents['Employer Contribution'].forEach((comp) => {
            let calcVal = parseFloat(comp.calc_value) || 0;
            let isFixed = (comp.calc_type && comp.calc_type.toLowerCase().includes('fixed'));
            let amount = isFixed ? calcVal : (baseAmount * calcVal) / 100;
            emBody.innerHTML +=
                `<tr><td>${emIndex++}</td><td class="comp-name-cell">${comp.component_name}</td><td class="amount-cell" data-val="${amount}">₹${amount.toFixed(0)}</td><td style="text-align: right; padding-right: 24px;"><span class="arrow-icon" title="Edit row" onclick="editComponentRow(this)">&rsaquo;</span></td></tr>`;
        });
    }

    if (document.getElementById('chk_pf').checked && empConfig) {
        let employerSum = (baseAmount * 0.50) * ((parseFloat(empConfig.epf_employer_rate) + parseFloat(empConfig
            .pension_fund)) / 100);
        emBody.innerHTML +=
            `<tr><td>${emIndex++}</td><td class="comp-name-cell">EPF Employer + Pension</td><td class="amount-cell" data-val="${employerSum}">₹${employerSum.toFixed(0)}</td><td style="text-align: right; padding-right: 24px;"><span class="arrow-icon" title="Edit row" onclick="editComponentRow(this)">&rsaquo;</span></td></tr>`;
    }
    if (document.getElementById('chk_esi').checked && empConfig) {
        let esiEmployer = globalEarningTotal * (parseFloat(empConfig.esi_employer) / 100);
        emBody.innerHTML +=
            `<tr><td>${emIndex++}</td><td class="comp-name-cell">ESI Employer</td><td class="amount-cell" data-val="${esiEmployer}">₹${esiEmployer.toFixed(0)}</td><td style="text-align: right; padding-right: 24px;"><span class="arrow-icon" title="Edit row" onclick="editComponentRow(this)">&rsaquo;</span></td></tr>`;
    }

    calculateTotals();
}

// --- AUTO SAVE LOGIC ---
function getTableData(tbodyId) {
    let data = [];
    document.querySelectorAll(`#${tbodyId} tr`).forEach(tr => {
        if (!tr.querySelector('.amount-cell')) return;
        let nameCell = tr.querySelector('.comp-name-cell') || tr.querySelector('.var-name-cell');
        data.push({
            name: nameCell ? nameCell.innerText.trim() : '',
            amount: parseFloat(tr.querySelector('.amount-cell').getAttribute('data-val') || 0)
        });
    });
    return data;
}

function autoSaveStructure() {
    if (!currentEmployeeId) return;
    if (document.querySelector('.inline-input')) return; // Don't auto-save while actively editing

    const statusText = document.getElementById('saveStatus');
    statusText.innerText = 'Saving...';
    statusText.style.color = '#6B7280';
    statusText.classList.add('show');

    const payload = {
        earnings: getTableData('earningsTableBody'),
        deductions: getTableData('deductionsTableBody'),
        employer_contributions: getTableData('employerTableBody'),
        variables: getTableData('variablesTableBody')
    };

    const formData = new FormData();
    formData.append('action', 'save_structure');
    formData.append('employee_id', currentEmployeeId);
    formData.append('base_ctc', currentBaseCtc);
    formData.append('pf_applicable', document.getElementById('chk_pf').checked ? 1 : 0);
    formData.append('esi_applicable', document.getElementById('chk_esi').checked ? 1 : 0);
    formData.append('pt_applicable', document.getElementById('chk_pt').checked ? 1 : 0);
    formData.append('pt_state', document.getElementById('sel_state').value);
    formData.append('components_data', JSON.stringify(payload));

    fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                statusText.innerText = '✓ All changes saved';
                statusText.style.color = '#10B981';
                dbSavedStructures[currentEmployeeId] = {
                    employee_id: currentEmployeeId,
                    base_ctc: currentBaseCtc,
                    pf_applicable: document.getElementById('chk_pf').checked ? 1 : 0,
                    esi_applicable: document.getElementById('chk_esi').checked ? 1 : 0,
                    pt_applicable: document.getElementById('chk_pt').checked ? 1 : 0,
                    pt_state: document.getElementById('sel_state').value,
                    components_data: JSON.stringify(payload)
                };
            } else {
                statusText.innerText = '⚠ Save failed';
                statusText.style.color = '#EF4444';
            }
            setTimeout(() => statusText.classList.remove('show'), 3000);
        })
        .catch(err => {
            statusText.innerText = '⚠ Connection error';
            statusText.style.color = '#EF4444';
            setTimeout(() => statusText.classList.remove('show'), 3000);
        });
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>