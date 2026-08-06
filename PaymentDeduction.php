<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
require_once 'includes/db_client.php';

$page_title = 'Payroll - Advance Payment/Deduction';

$msg = '';
$error = '';

// ==========================================
// HANDLE TOAST NOTIFICATION
// ==========================================
$toast_data = null;
if (isset($_SESSION['toast'])) {
    $toast_data = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

// ==========================================
// HANDLE FORM SUBMISSION (Same Page Save)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_record'])) {
    $employee_name  = mysqli_real_escape_string($conn, $_POST['employee_name']);
    $financial_year = mysqli_real_escape_string($conn, $_POST['financial_year']);
    $pay_period     = mysqli_real_escape_string($conn, $_POST['pay_period']);
    $component_type = mysqli_real_escape_string($conn, $_POST['component_type']);
    $component      = mysqli_real_escape_string($conn, $_POST['component']);
    $amount         = floatval($_POST['amount']);
    $remarks        = mysqli_real_escape_string($conn, $_POST['remarks']);

    $insert_sql = "INSERT INTO `advance_payments` 
                   (`employee_name`, `financial_year`, `pay_period`, `component_type`, `component`, `amount`, `remarks`) 
                   VALUES 
                   ('$employee_name', '$financial_year', '$pay_period', '$component_type', '$component', '$amount', '$remarks')";
    
    if (mysqli_query($conn, $insert_sql)) {
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Record saved successfully.'];
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    } else {
        $error = "Error saving record: " . mysqli_error($conn);
        $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Failed to save record.'];
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    }
}

// ==========================================
// DYNAMIC FINANCIAL YEARS (April to March)
// ==========================================
$current_month = (int)date('n');
$current_year = (int)date('Y');

// Determine current financial year start
if ($current_month < 4) {
    $current_fy_start = $current_year - 1;
} else {
    $current_fy_start = $current_year;
}

$financial_years = [];
for ($y = $current_fy_start - 2; $y <= $current_fy_start + 2; $y++) {
    $next_y = $y + 1;
    $financial_years[] = "$y-$next_y";
}
$current_fy_string = "$current_fy_start-" . ($current_fy_start + 1);

// ==========================================
// FETCH EMPLOYEES (For Autocomplete)
// ==========================================
$employees = [];
$emp_sql = "SELECT `employee_code`, `employee_name` FROM `employees`"; 
$emp_result = @mysqli_query($conn, $emp_sql);

if ($emp_result && mysqli_num_rows($emp_result) > 0) {
    while ($row = mysqli_fetch_assoc($emp_result)) {
        $employees[] = $row;
    }
}

// ==========================================
// FETCH SALARY COMPONENTS
// ==========================================
$components = [];
$comp_sql = "SELECT `id`, `salary_type`, `component_name` FROM `salary_components` WHERE `status` = 'Active' OR `status` = 1"; 
$comp_result = @mysqli_query($conn, $comp_sql);

if ($comp_result && mysqli_num_rows($comp_result) > 0) {
    while ($row = mysqli_fetch_assoc($comp_result)) {
        $components[] = $row;
    }
}

// ==========================================
// HANDLE FILTER SEARCH GET PARAMETERS
// ==========================================
$search_employee = isset($_GET['search_employee']) ? mysqli_real_escape_string($conn, $_GET['search_employee']) : '';
$filter_year = isset($_GET['filter_year']) ? mysqli_real_escape_string($conn, $_GET['filter_year']) : $current_fy_string;
$filter_month = isset($_GET['filter_month']) ? mysqli_real_escape_string($conn, $_GET['filter_month']) : date('M-Y');

ob_start();
?>
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
.payroll-divider { border: none; border-top: 1px solid #D1D5DB; margin: 25px 0; }
.payroll-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); border: 1px solid #E5E7EB; padding: 24px; min-height: 400px; }
.payroll-tab{ padding: 5px 2px; font-size: 13.5px; font-weight: 500; color: #6B7280; cursor: pointer; border: none; background: transparent; border-bottom: 2.5px solid transparent; white-space: nowrap; transition: color .15s, border-color .15s; font-family: inherit; text-decoration: none; display: block; margin-bottom: -1px; }
.payroll-tab:hover { color: #111827; border-bottom-color: #111827; }
.payroll-tab.active { color: #2563EB; border-bottom-color: #2563EB; font-weight: 600; }
.card-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.breadcrumb { font-size: 15px; color: #4B5563; }
.breadcrumb strong { color: #111827; font-weight: 600; }
.inner-tabs { display: flex; gap: 20px; border-bottom: 1px solid #E5E7EB; margin-bottom: 25px; }
.inner-tab { font-size: 14px; color: #6B7280; text-decoration: none; padding-bottom: 10px; border-bottom: 2px solid transparent; font-weight: 500; transition: all 0.2s; cursor: pointer; }
.inner-tab:hover { color: #111827; }


.payroll-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); border: 1px solid #E5E7EB; padding: 24px; min-height: 500px; margin: 0 20px; }
.card-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.breadcrumb { font-size: 15px; color: #4B5563; display: flex; align-items: center; gap: 8px; }
.breadcrumb strong { color: #111827; font-weight: 600; }
.btn-outline-primary { background: #fff; color: #2563EB; border: 1px solid #2563EB; padding: 8px 16px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s; }
.btn-outline-primary:hover { background: #F0F5FF; }

/* Add Form Section */
.add-form-section { display: none; }
.form-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px; margin-bottom: 25px; }
.form-row.two-col { grid-template-columns: 1fr 1fr 2fr; }
.form-group { position: relative; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: #111827; margin-bottom: 8px; text-transform: uppercase; }
.line-input { width: 100%; padding: 8px 0; border: none; border-bottom: 1px solid #D1D5DB; font-size: 14px; color: #111827; background: transparent; outline: none; transition: border-color 0.2s; }
.line-input:focus { border-bottom-color: #2563EB; }
select.line-input { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 5l3 3 3-3'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right center; padding-right: 20px; }
.search-line-wrapper { position: relative; display: flex; align-items: center; border: 1px solid #D1D5DB; border-radius: 4px; overflow: hidden; background: #fff;}
.search-line-wrapper svg { position: absolute; left: 10px; width: 16px; height: 16px; stroke: #9CA3AF; fill: none; stroke-width: 2; }
.search-line-wrapper input { padding: 10px 10px 10px 34px; border: none; width: 100%; font-size: 14px; outline: none; }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 40px; }
.btn-primary { background: #0066FF; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #0052cc; }

/* Filter & List View */
.list-view-section { display: block; }
.filters-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 40px; margin-bottom: 40px; align-items: end;}
.pay-period-controls { display: flex; align-items: flex-end; gap: 15px; }
.select-group { flex: 1; min-width: 150px; position: relative; }
.select-group label { display: block; font-size: 13px; font-weight: 500; color: #4B5563; margin-bottom: 6px; text-transform: none; }
.custom-select { width: 100%; padding: 10px; border: none; border-bottom: 1px solid #D1D5DB; font-size: 14px; color: #111827; background: transparent; appearance: none; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%234B5563' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 5l3 3 3-3'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 8px center; outline: none; }
.custom-select:focus { border-bottom-color: #2563EB; }

/* Custom Autocomplete Dropdown styles */
.autocomplete-dropdown { display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #E5E7EB; border-radius: 4px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); z-index: 50; margin-top: 4px; overflow: hidden; }
.autocomplete-list { max-height: 200px; overflow-y: auto; list-style: none; padding: 0; margin: 0; }
.autocomplete-item { padding: 10px 12px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: background 0.15s; border-bottom: 1px solid #F3F4F6; font-size: 14px; color: #374151; }
.autocomplete-item:hover { background-color: #F9FAFB; }
.autocomplete-avatar { width: 28px; height: 28px; background-color: #9CA3AF; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.autocomplete-footer { padding: 12px; background: #fff; border-top: 1px solid #E5E7EB; color: #0066FF; font-size: 13px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; }
.autocomplete-footer:hover { background-color: #F9FAFB; }

/* Table Styles */
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
.data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #E5E7EB; font-size: 14px; }
.data-table th { background-color: #F9FAFB; color: #4B5563; font-weight: 600; }
.data-table tbody tr:hover { background-color: #F9FAFB; }

/* Empty State */
.empty-state { text-align: center; padding: 80px 0; }
.empty-state-svg { width: 120px; height: auto; margin: 0 auto 16px; }
.empty-state p { color: #9CA3AF; font-size: 14px; margin: 0; }

@media (max-width: 900px) { .form-row, .form-row.two-col { grid-template-columns: 1fr 1fr; } .filters-grid { grid-template-columns: 1fr; } }

/* Toast Notification Styles */
#toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 12px; }
.toast { min-width: 280px; padding: 14px 20px; border-radius: 6px; color: #fff; font-size: 14px; font-weight: 500; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); display: flex; align-items: center; justify-content: space-between; transform: translateX(120%); opacity: 0; transition: transform 0.3s ease-out, opacity 0.3s ease-out; }
.toast.show { transform: translateX(0); opacity: 1; }
.toast.success { background-color: #10B981; border-left: 4px solid #047857; }
.toast.error { background-color: #EF4444; border-left: 4px solid #B91C1C; }
.toast-close { background: none; border: none; color: white; cursor: pointer; font-size: 18px; margin-left: 12px; padding: 0; line-height: 1; opacity: 0.8; }
.toast-close:hover { opacity: 1; }
</style>

<!-- Toast Container -->
<div id="toast-container"></div>

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
        <a href="PaymentDeduction" class="payroll-tab active">Payment/Deduction</a> <span class="separator">|</span>
        <a href="HoldSalary" class="payroll-tab">Hold Salary</a> <span class="separator">|</span>
        <a href="ApprovePayslip">Approve Payslip</a> <span class="separator">|</span>
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
        <div class="breadcrumb">
            <span style="color: #111827; font-weight:600;">Payroll</span> 
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            <span>Advance Payment/Deduction</span>
        </div>
        <button type="button" class="btn-outline-primary" id="btnAddNew" onclick="toggleFormView(true)">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add New
        </button>
    </div>

    <!-- ADD FORM SECTION -->
    <div class="add-form-section" id="addFormSection">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <div class="search-line-wrapper">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="employee_name" id="addEmployeeInput" placeholder="Search by name or #code" autocomplete="off" required onkeyup="filterEmployees('addEmployeeInput', 'addDropdownList')">
                    </div>
                    <div class="autocomplete-dropdown" id="addDropdownList">
                        <ul class="autocomplete-list"></ul>
                        <div class="autocomplete-footer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            Browse Active & Inactive Employees
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Financial Year</label>
                    <select name="financial_year" id="financialYearSelect" class="line-input" onchange="updatePayPeriods('financialYearSelect', 'payPeriodSelect')">
                        <?php foreach($financial_years as $fy): ?>
                            <option value="<?= $fy ?>" <?= $fy == $current_fy_string ? 'selected' : '' ?>><?= $fy ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pay Period</label>
                    <select name="pay_period" id="payPeriodSelect" class="line-input" required>
                    </select>
                </div>
                <div class="form-group">
                    <label>Component Type</label>
                    <select name="component_type" id="componentTypeSelect" class="line-input" onchange="updateComponents()" required>
                        <option value="">Select Type</option>
                        <option value="Earning">Earning</option>
                        <option value="Deduction">Deduction</option>
                    </select>
                </div>
            </div>

            <div class="form-row two-col">
                <div class="form-group">
                    <label>Component</label>
                    <select name="component" id="componentSelect" class="line-input" required>
                        <option value="">Select Component</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" name="amount" class="line-input" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="line-input">
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-outline-primary" onclick="toggleFormView(false)">Cancel</button>
                <button type="submit" name="add_record" class="btn-primary">Add</button>
            </div>
        </form>
    </div>

    <!-- LIST & FILTER SECTION -->
    <div class="list-view-section" id="listViewSection">        
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="form-group">
                    <label>SELECT EMPLOYEE</label>
                    <div class="search-line-wrapper">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="search_employee" id="filterEmployeeInput" placeholder="Search by name or #code" autocomplete="off" value="<?= htmlspecialchars($search_employee) ?>" onkeyup="filterEmployees('filterEmployeeInput', 'filterDropdownList')">
                    </div>
                    <div class="autocomplete-dropdown" id="filterDropdownList">
                        <ul class="autocomplete-list"></ul>
                        <div class="autocomplete-footer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            Browse Active & Inactive Employees
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>SELECT PAY PERIOD</label>
                    <div class="pay-period-controls">
                        <div class="select-group">
                            <label>Year</label>
                            <select name="filter_year" class="custom-select" id="filterYearSelect" onchange="updatePayPeriods('filterYearSelect', 'filterMonthSelect', true)">
                                <?php foreach($financial_years as $fy): ?>
                                    <option value="<?= $fy ?>" <?= $fy == $filter_year ? 'selected' : '' ?>><?= $fy ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="select-group">
                            <label>Month</label>
                            <select name="filter_month" class="custom-select" id="filterMonthSelect">
                                <!-- Populated by JS -->
                            </select>
                        </div>
                        <button type="submit" class="btn-primary" style="height: 40px; padding: 0 24px;">Get Details</button>
                    </div>
                </div>
            </div>
        </form>

        <?php
        // Build query based on filters
        $where_clauses = [];
        if (!empty($search_employee)) {
            $where_clauses[] = "`employee_name` LIKE '%$search_employee%'";
        }
        if (!empty($filter_month)) {
            $where_clauses[] = "`pay_period` = '$filter_month'";
        }

        $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";
        $sql = "SELECT * FROM advance_payments $where_sql ORDER BY id DESC"; 
        $result = @mysqli_query($conn, $sql); 

        if ($result && mysqli_num_rows($result) > 0) {
        ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Pay Period</th>
                            <th>Component Type</th>
                            <th>Component</th>
                            <th>Amount</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td><?= htmlspecialchars($row['employee_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['pay_period'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['component_type'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['component'] ?? 'N/A') ?></td>
                            <td>₹<?= number_format((float)$row['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <div class="empty-state">
                <svg class="empty-state-svg" viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="80" cy="80" r="70" fill="#EBF4FF"/>
                    <rect x="55" y="45" width="50" height="70" rx="4" fill="#fff" stroke="#94A3B8" stroke-width="3"/>
                    <path d="M55 55C55 52.7909 56.7909 51 59 51H101C103.209 51 105 52.7909 105 55V65H55V55Z" fill="#64748B"/>
                    <rect x="63" y="56" width="20" height="3" rx="1.5" fill="#CBD5E1"/>
                    <rect x="65" y="75" width="25" height="3" rx="1.5" fill="#0066FF"/>
                    <rect x="65" y="85" width="30" height="3" rx="1.5" fill="#E2E8F0"/>
                    <rect x="65" y="95" width="20" height="3" rx="1.5" fill="#0066FF"/>
                    <rect x="65" y="105" width="15" height="3" rx="1.5" fill="#E2E8F0"/>
                </svg>
                <p>No Advance Payment/Deduction is there!</p>
            </div>
        <?php } ?>

    </div>
</div>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script>
// =====================================
// TOAST NOTIFICATION LOGIC
// =====================================
function showToast(type, message) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    toast.innerHTML = `
        <span>${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => { toast.classList.add('show'); }, 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300); 
    }, 3500);
}

<?php if ($toast_data): ?>
    document.addEventListener('DOMContentLoaded', () => {
        showToast('<?= $toast_data['type'] ?>', '<?= addslashes($toast_data['msg']) ?>');
    });
<?php endif; ?>

// =====================================
// FORM TOGGLE
// =====================================
function toggleFormView(showForm) {
    const formSection = document.getElementById('addFormSection');
    const listSection = document.getElementById('listViewSection');
    const btnAddNew = document.getElementById('btnAddNew');

    if (showForm) {
        formSection.style.display = 'block';
        listSection.style.display = 'none';
        btnAddNew.style.display = 'none'; 
    } else {
        formSection.style.display = 'none';
        listSection.style.display = 'block';
        btnAddNew.style.display = 'flex'; 
    }
}

// =====================================
// DYNAMIC PAY PERIOD GENERATOR (April - March)
// =====================================
const financialMonths = [
    { name: "Apr", isNextYear: false },
    { name: "May", isNextYear: false },
    { name: "Jun", isNextYear: false },
    { name: "Jul", isNextYear: false },
    { name: "Aug", isNextYear: false },
    { name: "Sep", isNextYear: false },
    { name: "Oct", isNextYear: false },
    { name: "Nov", isNextYear: false },
    { name: "Dec", isNextYear: false },
    { name: "Jan", isNextYear: true },
    { name: "Feb", isNextYear: true },
    { name: "Mar", isNextYear: true }
];
const currentFilterMonth = "<?= htmlspecialchars($filter_month) ?>";

function updatePayPeriods(yearSelectId, periodSelectId, isFilter = false) {
    const yearSelect = document.getElementById(yearSelectId);
    const periodSelect = document.getElementById(periodSelectId);
    const selectedYear = yearSelect.value; // e.g. "2026-2027"
    
    let startYear = selectedYear;
    let endYear = selectedYear;
    
    if (selectedYear.includes('-')) {
        const parts = selectedYear.split('-');
        startYear = parts[0];
        endYear = parts[1];
    }

    periodSelect.innerHTML = ''; 

    financialMonths.forEach(month => {
        const option = document.createElement('option');
        const yearToUse = month.isNextYear ? endYear : startYear;
        const val = `${month.name}-${yearToUse}`;
        option.value = val;
        option.textContent = val;
        
        // Auto-select filter if matches GET param
        if(isFilter && val === currentFilterMonth) {
            option.selected = true;
        }

        periodSelect.appendChild(option);
    });
}

// =====================================
// DYNAMIC COMPONENT LISTING
// =====================================
const dbComponents = <?= json_encode($components); ?>;

function updateComponents() {
    const typeSelect = document.getElementById('componentTypeSelect');
    const compSelect = document.getElementById('componentSelect');
    const selectedType = typeSelect.value; 

    compSelect.innerHTML = '<option value="">Select Component</option>';

    if(selectedType !== '') {
        dbComponents.forEach(comp => {
            if (comp.salary_type === selectedType) {
                const option = document.createElement('option');
                option.value = comp.component_name;
                option.textContent = comp.component_name;
                compSelect.appendChild(option);
            }
        });
    }
}

// =====================================
// CUSTOM AUTOCOMPLETE LOGIC
// =====================================
const employeeData = <?= json_encode($employees); ?>;

function renderEmployeeDropdown(inputValue, dropdownId, inputId) {
    const dropdown = document.getElementById(dropdownId);
    const list = dropdown.querySelector('.autocomplete-list');
    list.innerHTML = '';

    const filtered = employeeData.filter(emp => {
        const searchStr = `${emp.employee_name} #${emp.employee_code}`.toLowerCase();
        return searchStr.includes(inputValue.toLowerCase());
    });

    if (inputValue.length > 0 && filtered.length > 0) {
        filtered.forEach(emp => {
            const li = document.createElement('li');
            li.className = 'autocomplete-item';
            li.innerHTML = `
                <div class="autocomplete-avatar">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <span>${emp.employee_name} - #${emp.employee_code}</span>
            `;
            li.onclick = () => {
                document.getElementById(inputId).value = `${emp.employee_name} - #${emp.employee_code}`;
                dropdown.style.display = 'none';
            };
            list.appendChild(li);
        });
        dropdown.style.display = 'block';
    } else {
        dropdown.style.display = 'none';
    }
}

function filterEmployees(inputId, dropdownId) {
    const input = document.getElementById(inputId);
    renderEmployeeDropdown(input.value, dropdownId, inputId);
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.form-group')) {
        document.getElementById('addDropdownList').style.display = 'none';
        document.getElementById('filterDropdownList').style.display = 'none';
    }
});

// Initialize inputs to show dropdown on focus if empty
document.getElementById('addEmployeeInput').addEventListener('focus', function() {
    renderEmployeeDropdown(this.value || ' ', 'addDropdownList', 'addEmployeeInput');
});
document.getElementById('filterEmployeeInput').addEventListener('focus', function() {
    renderEmployeeDropdown(this.value || ' ', 'filterDropdownList', 'filterEmployeeInput');
});


// Initialize Dropdowns on Page Load
document.addEventListener('DOMContentLoaded', () => {
    updatePayPeriods('financialYearSelect', 'payPeriodSelect');
    updatePayPeriods('filterYearSelect', 'filterMonthSelect', true);
});
</script>
<script src="includes/assets/scripts.js"></script>