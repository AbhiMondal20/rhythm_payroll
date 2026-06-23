<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
require_once 'includes/db_client.php';

// ==========================================
// FORM SUBMISSION HANDLERS
// ==========================================

// 1. ADD NEW LOAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_loan'])) {
    $emp_code         = mysqli_real_escape_string($conn, $_POST['employee_code'] ?? '');
    $emp_name         = mysqli_real_escape_string($conn, $_POST['employee_name'] ?? '');
    $loan_type        = mysqli_real_escape_string($conn, $_POST['loan_type'] ?? '');
    $loan_amount      = mysqli_real_escape_string($conn, $_POST['loan_amount'] ?? '0');
    $emi_amount       = mysqli_real_escape_string($conn, $_POST['emi_amount'] ?? '0');
    $issue_date       = mysqli_real_escape_string($conn, $_POST['issue_date'] ?? '');
    $repayment_start  = mysqli_real_escape_string($conn, $_POST['repayment_start'] ?? '');
    $repaid_amount    = mysqli_real_escape_string($conn, $_POST['repaid_amount'] ?? '0');
    $salary_component = mysqli_real_escape_string($conn, $_POST['salary_component'] ?? '');
    $issue_reference  = mysqli_real_escape_string($conn, $_POST['issue_reference'] ?? '');
    $remarks          = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');

    // Default to the provided end date just in case, but we will auto-calculate it below
    $repayment_end    = mysqli_real_escape_string($conn, $_POST['repayment_end'] ?? '');

    $insert_sql = "INSERT INTO `employee_loans` 
        (`employee_code`, `employee_name`, `loan_type`, `loan_amount`, `emi_amount`, `issue_date`, `repayment_start`, `repayment_end`, `repaid_amount`, `salary_component`, `issue_reference`, `remarks`) 
        VALUES 
        ('$emp_code', '$emp_name', '$loan_type', '$loan_amount', '$emi_amount', '$issue_date', '$repayment_start', '$repayment_end', '$repaid_amount', '$salary_component', '$issue_reference', '$remarks')";
    
    if(@mysqli_query($conn, $insert_sql)) {
        $loan_id = mysqli_insert_id($conn);
        
        // ---------------------------------------------------------
        // DYNAMIC EMI CALCULATION (Handles remainders automatically)
        // ---------------------------------------------------------
        $remaining_amount = (float)$loan_amount;
        $emi = (float)$emi_amount;
        
        // Safety fallback: if EMI is 0, make it a 1-month repayment
        if ($emi <= 0) { $emi = $remaining_amount; }
        
        $start_date = strtotime($repayment_start . '-01');
        $i = 0;
        $last_period_date = '';

        if ($remaining_amount > 0) {
            while ($remaining_amount > 0) {
                // If the remaining balance is less than the standard EMI, use the balance (e.g. 500)
                $current_emi = ($remaining_amount >= $emi) ? $emi : $remaining_amount;
                
                $period = date('M-Y', strtotime("+$i months", $start_date));
                $last_period_date = date('Y-m', strtotime("+$i months", $start_date));
                
                // Insert standard or remainder EMI
                @mysqli_query($conn, "INSERT INTO `loan_deductions` (`loan_id`, `pay_period`, `emi_amount`) VALUES ('$loan_id', '$period', '$current_emi')");
                
                $remaining_amount -= $current_emi;
                $i++;
            }
            
            // Automatically update the Repayment End Date to the actual calculated final month
            @mysqli_query($conn, "UPDATE `employee_loans` SET `repayment_end` = '$last_period_date' WHERE `id` = $loan_id");
        }
    }
    // header("Location: " . $_SERVER['PHP_SELF'] . "?status=added");
    ?>
<script>
window.location.href = window.location.href.split('?')[0]; // Remove query params to prevent resubmission
</script>
<?php
    exit();
}

// 2. UPDATE EXISTING LOAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_loan'])) {
    $loan_id          = (int)$_POST['loan_id'];
    $loan_type        = mysqli_real_escape_string($conn, $_POST['loan_type'] ?? '');
    $loan_amount      = mysqli_real_escape_string($conn, $_POST['loan_amount'] ?? '0');
    $emi_amount       = mysqli_real_escape_string($conn, $_POST['emi_amount'] ?? '0');
    $issue_date       = mysqli_real_escape_string($conn, $_POST['issue_date'] ?? '');
    $repayment_start  = mysqli_real_escape_string($conn, $_POST['repayment_start'] ?? '');
    $repayment_end    = mysqli_real_escape_string($conn, $_POST['repayment_end'] ?? '');
    $repaid_amount    = mysqli_real_escape_string($conn, $_POST['repaid_amount'] ?? '0');
    $salary_component = mysqli_real_escape_string($conn, $_POST['salary_component'] ?? '');
    $issue_reference  = mysqli_real_escape_string($conn, $_POST['issue_reference'] ?? '');
    $remarks          = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');

    $update_sql = "UPDATE `employee_loans` SET 
        `loan_type` = '$loan_type', `loan_amount` = '$loan_amount', `emi_amount` = '$emi_amount', 
        `issue_date` = '$issue_date', `repayment_start` = '$repayment_start', `repayment_end` = '$repayment_end', 
        `repaid_amount` = '$repaid_amount', `salary_component` = '$salary_component', 
        `issue_reference` = '$issue_reference', `remarks` = '$remarks' 
        WHERE `id` = $loan_id";
    
    @mysqli_query($conn, $update_sql);
    // header("Location: " . $_SERVER['PHP_SELF'] . "?status=loan_updated");
    ?>
<script>
window.location.href = window.location.href.split('?')[0]; // Remove query params to prevent resubmission
</script>
<?php
    exit();
}

// 3. EDIT DEDUCTION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_deduction'])) {
    $deduction_id = (int)$_POST['deduction_id'];
    $amount       = mysqli_real_escape_string($conn, $_POST['amount'] ?? '0');
    $remarks      = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
    
    @mysqli_query($conn, "UPDATE `loan_deductions` SET `emi_amount` = '$amount', `remarks` = '$remarks' WHERE `id` = $deduction_id");
    // header("Location: " . $_SERVER['PHP_SELF'] . "?status=deduction_updated");
    ?>
<script>
window.location.href = window.location.href.split('?')[0]; // Remove query params to prevent resubmission
</script>
<?php
    exit();
}

// 4. DELETE DEDUCTION (AJAX)
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'delete_deduction') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id'];
    if(@mysqli_query($conn, "DELETE FROM `loan_deductions` WHERE `id` = $id")) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit();
}

$page_title = 'Payroll - Loans';

// ==========================================
// FETCH DATA FOR UI
// ==========================================

// Employees
$employees = [];
$emp_sql = "SELECT `employee_code`, `employee_name`, `department`, `designation`, `location` FROM `employees` WHERE `status` = 'Active' OR `status` = '1'"; 
$emp_result = @mysqli_query($conn, $emp_sql);
if ($emp_result && mysqli_num_rows($emp_result) > 0) {
    while ($row = mysqli_fetch_assoc($emp_result)) { $employees[] = $row; }
}

// Loans
$loans = [];
$loans_sql = "SELECT
    el.*,
    e.department,
    e.designation,
    e.location
FROM employee_loans el
LEFT JOIN employees e
    ON el.employee_code COLLATE utf8mb4_general_ci =
       e.employee_code COLLATE utf8mb4_general_ci
ORDER BY el.id DESC;";
$loans_result = @mysqli_query($conn, $loans_sql);
if ($loans_result && mysqli_num_rows($loans_result) > 0) {
    while ($row = mysqli_fetch_assoc($loans_result)) { $loans[] = $row; }
}

// Deductions, Additions, Recoveries
$deductions = [];
$ded_res = @mysqli_query($conn, "SELECT * FROM `loan_deductions` ORDER BY `id` ASC");
if ($ded_res) { while ($row = mysqli_fetch_assoc($ded_res)) { $deductions[] = $row; } }

$additions = [];
$add_res = @mysqli_query($conn, "SELECT * FROM `loan_additions` ORDER BY `id` ASC");
if ($add_res) { while ($row = mysqli_fetch_assoc($add_res)) { $additions[] = $row; } }

$recoveries = [];
$rec_res = @mysqli_query($conn, "SELECT * FROM `loan_recoveries` ORDER BY `id` ASC");
if ($rec_res) { while ($row = mysqli_fetch_assoc($rec_res)) { $recoveries[] = $row; } }


// ==========================================
// DYNAMIC DASHBOARD CALCULATIONS (From DB)
// ==========================================
$dash_total_amount = 0;
$dash_by_type = [];
$dash_monthly = [];
$dash_labels = [];

// Initialize last 6 months dynamically (e.g. Jan, Feb, Mar, Apr, May, Jun)
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months")); // Format: YYYY-MM
    $dash_monthly[$m] = 0;
    $dash_labels[] = date('M', strtotime($m . '-01')); // Format: Jan, Feb
}

// Group Loan Data dynamically
foreach ($loans as $l) {
    $amt = (float)$l['loan_amount'];
    $dash_total_amount += $amt;
    
    // Group by Loan Type
    $type = !empty($l['loan_type']) ? $l['loan_type'] : 'Personal';
    if (!isset($dash_by_type[$type])) $dash_by_type[$type] = ['count' => 0, 'amount' => 0];
    $dash_by_type[$type]['count']++;
    $dash_by_type[$type]['amount'] += $amt;

    // Group by Month (Issue Date)
    $m = substr($l['issue_date'], 0, 7); // Extract YYYY-MM
    if (isset($dash_monthly[$m])) {
        $dash_monthly[$m] += $amt;
    }
}

// Generate SVG Paths based on Monthly DB Data
$monthly_values = array_values($dash_monthly);
$max_val = max($monthly_values);
if ($max_val == 0) $max_val = 1; // Prevent division by zero

$chart_points = [];
$area_path = "M0,60 "; // Start bottom left for Area chart
$line_path = "M"; // Start for Line chart

foreach ($monthly_values as $i => $val) {
    $x = $i * 40; // Total width is 200, 5 intervals -> 40px each
    $y = 50 - (($val / $max_val) * 40); // Total height 60, padding 10px top & bottom
    
    $chart_points[] = ['x' => $x, 'y' => $y];
    $area_path .= "L$x,$y ";
    $line_path .= "$x,$y ";
    if ($i < count($monthly_values) - 1) $line_path .= "L";
}
$area_path .= "L200,60 Z"; // Close Area chart path at bottom right


ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* Common Styles */
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
    margin-bottom: 40px;
}

.payroll-tab {
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

/* ── Typography & Forms ── */
.section-heading {
    font-size: 14px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 25px;
    text-transform: uppercase;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 25px;
    max-width: 900px;
}

.form-row.four-col {
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 20px;
    max-width: 100%;
}

.form-group label {
    display: block;
    font-size: 12px;
    color: #4B5563;
    margin-bottom: 5px;
}

.line-input {
    width: 100%;
    padding: 8px 0;
    border: none;
    border-bottom: 1px solid #D1D5DB;
    font-size: 14px;
    color: #111827;
    background: transparent;
    outline: none;
    transition: border-color 0.2s;
}

.line-input:focus {
    border-bottom-color: #0066FF;
}

select.line-input {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 5l3 3 3-3'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right center;
    padding-right: 20px;
}

input[type="date"].line-input::-webkit-calendar-picker-indicator,
input[type="month"].line-input::-webkit-calendar-picker-indicator {
    color: #0066FF;
    cursor: pointer;
    opacity: 0.6;
}

.search-line-wrapper {
    position: relative;
}

.search-line-wrapper svg {
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    stroke: #9CA3AF;
    fill: none;
    stroke-width: 2;
}

.search-line-wrapper input {
    padding-left: 24px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 30px;
}

/* ── Buttons ── */
.btn-primary {
    background: #0066FF;
    color: #fff;
    border: none;
    padding: 8px 24px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-primary:hover {
    background: #0052cc;
}

.btn-outline-primary {
    background: #fff;
    color: #0066FF;
    border: 1px solid #0066FF;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-outline-primary:hover {
    background: #F0F5FF;
}

.btn-outline {
    background: #fff;
    color: #0066FF;
    border: 1px solid #0066FF;
    padding: 8px 24px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-outline:hover {
    background: #F0F5FF;
}

/* ── Dashboard Cards ── */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.dash-card {
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    padding: 20px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.dash-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.dash-title {
    font-size: 14px;
    font-weight: 700;
    color: #111827;
}

.dash-select {
    border: none;
    color: #4B5563;
    font-size: 12px;
    cursor: pointer;
    outline: none;
    background: transparent;
}

.donut-container {
    display: flex;
    align-items: center;
    gap: 20px;
}

.donut-chart {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: conic-gradient(#A3E635 0% 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.donut-inner {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563EB;
    font-weight: bold;
    font-size: 14px;
}

.donut-legend {
    font-size: 12px;
    color: #4B5563;
    display: flex;
    align-items: flex-start;
    flex-direction: column;
    gap: 6px;
}

.legend-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #A3E635;
    margin-right: 5px;
}

.chart-placeholder {
    height: 120px;
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    position: relative;
}

.chart-axes {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #9CA3AF;
    margin-top: 10px;
    border-top: 2px solid #E5E7EB;
    padding-top: 5px;
    position: relative;
}

.area-svg {
    width: 100%;
    height: 100px;
}

/* ── Table Toolbar & Data Table ── */
.table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-top: 20px;
    border-top: 1px dashed #E5E7EB;
    flex-wrap: wrap;
    gap: 15px;
}

.toolbar-filters {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.search-wrapper {
    position: relative;
    width: 250px;
}

.search-wrapper svg {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    stroke: #9CA3AF;
    fill: none;
    stroke-width: 2;
}

.search-wrapper input {
    width: 100%;
    padding: 8px 10px 8px 32px;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
}

.date-filter {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #4B5563;
}

.date-input {
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    padding: 7px 10px;
    font-size: 13px;
    outline: none;
    color: #111827;
}

.table-responsive {
    overflow-x: auto;
    border: 1px solid #E5E7EB;
    border-radius: 4px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid #E5E7EB;
    font-size: 13px;
    color: #111827;
}

.data-table th {
    background-color: #F9FAFB;
    color: #4B5563;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
}

/* ── Loan Details View ── */
.details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.details-link {
    font-size: 13px;
    color: #0066FF;
    text-decoration: underline;
    cursor: pointer;
    font-weight: 500;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px 20px;
    margin-bottom: 40px;
    border-bottom: 1px dashed #E5E7EB;
    padding-bottom: 30px;
}

.detail-item label {
    display: block;
    font-size: 11px;
    color: #6B7280;
    margin-bottom: 5px;
    text-transform: uppercase;
}

.detail-item div {
    font-size: 14px;
    color: #111827;
    font-weight: 500;
}

.bottom-tabs {
    display: flex;
    gap: 25px;
    margin-bottom: 20px;
    border-bottom: 1px solid #E5E7EB;
    width: 100%;
}

.bottom-tab {
    font-size: 14px;
    color: #6B7280;
    text-decoration: none;
    padding-bottom: 10px;
    border-bottom: 2px solid transparent;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.bottom-tab:hover {
    color: #111827;
}

.bottom-tab.active {
    color: #0066FF;
    border-bottom-color: #0066FF;
}

/* ── Modal ── */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.modal-content {
    background: #fff;
    width: 100%;
    max-width: 500px;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px 24px;
    border-bottom: 1px solid #E5E7EB;
}

.modal-header h2 {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.modal-header p {
    margin: 0;
    font-size: 12px;
    color: #6B7280;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #EF4444;
    line-height: 1;
}

.modal-body {
    padding: 24px;
}

.radio-group {
    margin-bottom: 15px;
}

.radio-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #111827;
    margin-bottom: 10px;
    cursor: pointer;
}

.radio-label input[type="radio"] {
    accent-color: #0066FF;
    width: 16px;
    height: 16px;
    margin: 0;
}

.action-btns {
    display: flex;
    gap: 10px;
    cursor: pointer;
}

.action-btns svg {
    stroke: #6B7280;
    transition: stroke 0.2s;
}

.action-btns svg:hover {
    stroke: #0066FF;
}

.action-btns svg.delete:hover {
    stroke: #EF4444;
}
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
    <h1 class="page-title" id="mainPageTitle">Payroll</h1>
    <div class="payroll-top-links" id="mainTopLinks">
        <a href="PaymentDeduction">Payment/Deduction</a> <span class="separator">|</span>
        <a href="HoldSalary">Hold Salary</a> <span class="separator">|</span>
        <a href="ApprovePayslip">Approve Payslip</a> <span class="separator">|</span>
        <a href="EditPayslip">Edit Payslip</a> <span class="separator">|</span>
        <a href="Loans" class="payroll-tab active">Loans</a> <span class="separator">|</span>
        <a href="ProcessPayslip">Process Payslip</a> <span class="separator">|</span>
        <a href="FullFinal">Final Settlement</a> <span class="separator">|</span>
        <a href="SalaryStructure">Salary Structure</a> <span class="separator">|</span>
        <a href="Timesheet">Timesheet</a>
    </div>
</div>

<div class="payroll-card" id="mainCard">

    <div id="listView">
        <div class="dashboard-grid">

            <div class="dash-card">
                <div class="dash-header">
                    <span class="dash-title">Total Loans</span>
                    <select class="dash-select">
                        <option>This Month</option>
                    </select>
                </div>
                <div class="donut-container">
                    <div class="donut-chart">
                        <div class="donut-inner">₹<?= number_format($dash_total_amount) ?></div>
                    </div>
                    <div class="donut-legend">
                        <?php if(!empty($dash_by_type)): ?>
                        <?php foreach($dash_by_type as $type => $data): ?>
                        <div><span class="legend-dot"></span> <?= $data['count'] ?> <?= htmlspecialchars($type) ?> -
                            ₹<?= number_format($data['amount']) ?></div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div><span class="legend-dot" style="background:#E5E7EB;"></span> No active loans</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="dash-card">
                <div class="dash-header">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="#4B5563" stroke-width="2" fill="none">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span class="dash-title">Overview</span>
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="#4B5563" stroke-width="2" fill="none">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
                <div class="chart-placeholder">
                    <span
                        style="font-size: 11px; color: #4B5563; position:absolute; top: -10px; left: 50%; transform: translateX(-50%);">Loan</span>

                    <svg class="area-svg" viewBox="0 0 200 60" preserveAspectRatio="none"
                        style="position:absolute; bottom:30px;">
                        <path d="<?= $line_path ?>" fill="none" stroke="#38BDF8" stroke-width="2"></path>
                        <?php foreach($chart_points as $p): ?>
                        <circle cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="3" fill="#38BDF8" stroke="#fff"
                            stroke-width="1.5"></circle>
                        <?php endforeach; ?>
                    </svg>

                    <div class="chart-axes">
                        <?php foreach($dash_labels as $lbl): ?> <span><?= $lbl ?></span> <?php endforeach; ?>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="#" style="font-size: 11px; color: #38BDF8; text-decoration: underline;">See Top
                        contributors for this expense</a>
                </div>
            </div>

            <div class="dash-card">
                <div class="dash-header">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="#4B5563" stroke-width="2" fill="none">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span class="dash-title" style="font-size:12px; font-weight:normal;"><?= reset($dash_labels) ?>
                        <?= date('Y', strtotime('-5 months')) ?>-<?= end($dash_labels) ?> <?= date('Y') ?></span>
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="#E5E7EB" stroke-width="2" fill="none">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
                <div style="font-weight: 700; margin-top:-10px; margin-bottom: 10px; font-size:14px;">Loan Expenditure
                </div>
                <div class="chart-placeholder">
                    <svg class="area-svg" viewBox="0 0 200 60" preserveAspectRatio="none">
                        <path d="<?= $area_path ?>" fill="#38BDF8" opacity="0.6"></path>
                        <path d="<?= $line_path ?>" fill="none" stroke="#0ea5e9" stroke-width="2"></path>
                        <?php foreach($chart_points as $p): ?>
                        <circle cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="3" fill="#38BDF8" stroke="#fff"
                            stroke-width="1.5"></circle>
                        <?php endforeach; ?>
                    </svg>
                    <div class="chart-axes" style="border-top:none; margin-top:0;">
                        <?php foreach($dash_labels as $lbl): ?> <span><?= $lbl ?></span> <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <h2 style="font-size: 16px; margin: 0;">Loans</h2>
        <div class="table-toolbar">
            <div class="toolbar-filters">
                <div class="search-wrapper">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" placeholder="Search by name or #code">
                </div>
                <div class="date-filter">From <input type="date" class="date-input" value="<?= date('Y-m-01') ?>"></div>
                <div class="date-filter">To <input type="date" class="date-input" value="<?= date('Y-m-d') ?>"></div>
                <button type="button" class="btn-primary">Get Details</button>
            </div>
            <button type="button" class="btn-outline-primary" onclick="toggleView('add')">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add New Loan
            </button>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>EMPLOYEE NAME</th>
                        <th>TYPE</th>
                        <th>AMOUNT</th>
                        <th>DUE AMOUNT</th>
                        <th>ISSUE DATE</th>
                        <th>END DATE</th>
                        <th>DETAILS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($loans) > 0): ?>
                    <?php foreach($loans as $loan): ?>
                    <tr>
                        <td><?= htmlspecialchars($loan['employee_name']) ?></td>
                        <td><?= htmlspecialchars($loan['loan_type']) ?></td>
                        <td>₹<?= number_format($loan['loan_amount'], 0) ?></td>
                        <td>₹<?= number_format($loan['loan_amount'] - $loan['repaid_amount'], 0) ?></td>
                        <td><?= date('d M Y', strtotime($loan['issue_date'])) ?></td>
                        <td><?= date('M-Y', strtotime($loan['repayment_end'])) ?></td>
                        <td>
                            <a href="#" onclick="viewDetails(<?= $loan['id'] ?>)" style="color:#6B7280;"
                                title="View Details">
                                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2"
                                    fill="none">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:#9CA3AF;">No loans found in the database. Click
                            'Add New Loan' to start.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:space-between; margin-top:15px; font-size:13px; color:#6B7280;">
            <div>Showing 1 to <?= max(1, count($loans)) ?> of <?= count($loans) ?> entries</div>
            <div style="display:flex; align-items:center; gap:10px;">
                Show <select style="padding:2px 5px; border:1px solid #D1D5DB; border-radius:4px;">
                    <option>25</option>
                </select> entries
            </div>
        </div>
    </div>

    <div id="addView" style="display: none;">
        <div class="section-heading" id="formHeading">NEW LOAN</div>
        <form action="" method="POST" id="loanForm">
            <input type="hidden" name="loan_id" id="formLoanId">
            <input type="hidden" name="employee_code" id="hiddenEmpCode">
            <input type="hidden" name="employee_name" id="hiddenEmpName">

            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <div class="search-line-wrapper">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" id="empSearchInput" list="employeeList" class="line-input"
                            placeholder="Search by name or #code" autocomplete="off" onchange="parseEmployeeSelection()"
                            required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <input type="text" name="loan_type" id="formLoanType" class="line-input" placeholder="e.g. Personal"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Loan Amount</label>
                    <input type="number" name="loan_amount" id="formLoanAmount" class="line-input" value="0" step="0.01"
                        required>
                </div>
                <div class="form-group">
                    <label>EMI</label>
                    <input type="number" name="emi_amount" id="formEmiAmount" class="line-input" value="0" step="0.01"
                        required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" id="formIssueDate" class="line-input"
                        value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Repayment Start</label>
                    <input type="month" name="repayment_start" id="formRepaymentStart" class="line-input"
                        value="<?= date('Y-m') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Repayment End</label>
                    <input type="month" name="repayment_end" id="formRepaymentEnd" class="line-input"
                        value="<?= date('Y-m', strtotime('+6 months')) ?>" required>
                    <small style="color:#9CA3AF; font-size:11px;">(Auto-calculated on Add)</small>
                </div>
                <div class="form-group">
                    <label>Repaid Till Date</label>
                    <input type="number" name="repaid_amount" id="formRepaidAmount" class="line-input" value="0"
                        step="0.01">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Salary Component</label>
                    <select name="salary_component" id="formSalaryComponent" class="line-input">
                        <option value="Loans & Advances">Loans & Advances</option>
                        <option value="Other Deductions">Other Deductions</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Issue Reference</label>
                    <input type="text" name="issue_reference" id="formIssueRef" class="line-input">
                </div>
            </div>

            <div class="form-row" style="grid-template-columns: 1fr;">
                <div class="form-group">
                    <label>Remarks</label>
                    <input type="text" name="remarks" id="formRemarks" class="line-input">
                </div>
            </div>

            <div class="form-actions" style="margin-top: 50px;">
                <button type="button" class="btn-outline" onclick="toggleView('list')">Cancel</button>
                <button type="submit" name="add_loan" id="formSubmitBtn" class="btn-primary">Add</button>
            </div>
        </form>
    </div>

    <div id="detailsView" style="display: none;">

        <div class="details-header">
            <div class="section-heading" style="margin:0;">LOAN DETAILS</div>
            <a href="#" class="details-link" onclick="openStatementView()">View Statement</a>
        </div>

        <div class="details-grid">
            <div class="detail-item"><label>Name</label>
                <div id="detName">-</div>
            </div>
            <div class="detail-item"><label>Type</label>
                <div id="detType">-</div>
            </div>
            <div class="detail-item"><label>Department</label>
                <div id="detDept">-</div>
            </div>
            <div class="detail-item"><label>Designation</label>
                <div id="detDesig">-</div>
            </div>

            <div class="detail-item"><label>Total Amount</label>
                <div id="detTotal">-</div>
            </div>
            <div class="detail-item"><label>Due Amount</label>
                <div id="detDue">-</div>
            </div>
            <div class="detail-item"><label>Repaid Till Date</label>
                <div id="detRepaid">-</div>
            </div>
            <div class="detail-item"><label>EMI</label>
                <div id="detEmi">-</div>
            </div>

            <div class="detail-item"><label>Issue Date</label>
                <div id="detIssue">-</div>
            </div>
            <div class="detail-item"><label>Repayment Start</label>
                <div id="detStart">-</div>
            </div>
            <div class="detail-item"><label>Repayment End</label>
                <div id="detEnd">-</div>
            </div>
            <div class="detail-item"><label>Salary Component</label>
                <div id="detComp">-</div>
            </div>

            <div class="detail-item"><label>Issue Reference</label>
                <div id="detRef">-</div>
            </div>
            <div class="detail-item"><label>Note</label>
                <div id="detNote">-</div>
            </div>
        </div>

        <div class="form-actions" style="margin-top:0; margin-bottom:40px; justify-content:flex-start;">
            <button type="button" class="btn-outline" onclick="toggleView('list')"
                style="color:#111827; border-color:#D1D5DB;">Back</button>
            <button type="button" class="btn-primary" onclick="editLoan()">Edit</button>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:flex-end;">
            <div class="bottom-tabs">
                <div class="bottom-tab active" id="tabDeduction" onclick="switchDetailsTab('Deduction')">Payroll
                    Deduction</div>
                <div class="bottom-tab" id="tabAdditions" onclick="switchDetailsTab('Additions')">Additions to Loan
                </div>
                <div class="bottom-tab" id="tabRecoveries" onclick="switchDetailsTab('Recoveries')">Recoveries</div>
            </div>
            <button class="btn-outline-primary" style="margin-bottom:15px; padding: 6px 12px; font-size:12px;"
                onclick="alert('Feature under development.')">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add
            </button>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr id="detailsTableHeader">
                    </tr>
                </thead>
                <tbody id="detailsTableBody">
                </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:space-between; margin-top:15px; font-size:13px; color:#6B7280;">
            <div>Showing 1 to <span id="detCount">0</span> of <span id="detCountTotal">0</span> entries</div>
            <div style="display:flex; align-items:center; gap:10px;">
                Show <select style="padding:2px 5px; border:1px solid #D1D5DB; border-radius:4px;">
                    <option>10</option>
                </select> entries
            </div>
        </div>
    </div>

    <div id="statementView" style="display: none;">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="font-size: 16px; font-weight: 700; color: #111827; margin:0;">Loan Statement</h2>
            <button class="btn-primary">Export</button>
        </div>

        <div style="border: 1px solid #E5E7EB; border-radius: 8px; margin-bottom: 40px;">
            <div
                style="padding: 20px 24px; border-bottom: 1px solid #E5E7EB; font-size: 14px; font-weight: 700; color: #111827; text-transform: uppercase;">
                LOAN DETAILS</div>
            <div class="details-grid" style="border:none; margin:0; padding:24px;">
                <div class="detail-item"><label>Employee Code - Name</label>
                    <div id="stName">-</div>
                </div>
                <div class="detail-item"><label>Location</label>
                    <div id="stLoc">-</div>
                </div>
                <div class="detail-item"><label>Department</label>
                    <div id="stDept">-</div>
                </div>
                <div class="detail-item"><label>Designation</label>
                    <div id="stDesig">-</div>
                </div>

                <div class="detail-item"><label>Loan Type</label>
                    <div id="stType">-</div>
                </div>
                <div class="detail-item"><label>Loan Amount</label>
                    <div id="stTotal">-</div>
                </div>
                <div class="detail-item"><label>Issue Date</label>
                    <div id="stIssue">-</div>
                </div>
                <div class="detail-item"><label>Due Date</label>
                    <div id="stDue">-</div>
                </div>

                <div class="detail-item"><label>Salary Recoveries</label>
                    <div id="stSalRec">-</div>
                </div>
                <div class="detail-item"><label>Other Recoveries</label>
                    <div id="stOthRec">-</div>
                </div>
                <div class="detail-item"><label>Total Recoveries</label>
                    <div id="stTotRec">-</div>
                </div>
                <div class="detail-item"><label>Total Additions</label>
                    <div id="stTotAdd">-</div>
                </div>

                <div class="detail-item"><label>Number of Scheduled Deductions</label>
                    <div id="stNumDed">-</div>
                </div>
                <div class="detail-item"><label>Total Scheduled Deductions</label>
                    <div id="stTotSched">-</div>
                </div>
                <div class="detail-item"><label>Due as on Date</label>
                    <div id="stDueOnDate">-</div>
                </div>
                <div class="detail-item"><label>EMI</label>
                    <div id="stEmi">-</div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>S NO.</th>
                        <th>TRANSACTION DATE</th>
                        <th>TRANSACTION TYPE</th>
                        <th>ADDITIONS</th>
                        <th>RECOVERIES</th>
                        <th>BALANCE</th>
                        <th>REMARKS</th>
                    </tr>
                </thead>
                <tbody id="statementTableBody">
                </tbody>
            </table>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:15px; font-size:13px; color:#6B7280;">
            <div>Showing 1 to <span id="stCount">0</span> of <span id="stCountTotal">0</span> entries</div>
            <div style="display:flex; align-items:center; gap:10px;">
                Show <select style="padding:2px 5px; border:1px solid #D1D5DB; border-radius:4px;">
                    <option>10</option>
                </select> entries
            </div>
        </div>
    </div>
</div>

<div id="editDeductionModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2>Edit Deduction</h2>
                <p id="modalSubtitle">Select reason for editing deduction.</p>
            </div>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>

        <form action="" method="POST">
            <input type="hidden" name="deduction_id" id="modDedId">
            <div class="modal-body">
                <div class="form-row" style="margin-bottom: 25px;">
                    <div class="form-group">
                        <label>Pay Period</label>
                        <input type="text" id="modPayPeriod" class="line-input" style="color:#6B7280;" readonly>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <div style="position:relative; display:flex; align-items:center;">
                            <span style="position:absolute; left:0; font-size:14px;">₹</span>
                            <input type="number" name="amount" id="modAmount" class="line-input"
                                style="padding-left:15px;" required>
                        </div>
                    </div>
                </div>

                <div style="font-size:12px; color:#6B7280; margin-bottom:15px;">Select reason for editing this payroll
                    deduction</div>
                <div class="radio-group">
                    <label class="radio-label"><input type="radio" name="reason" value="schedule_new" checked> Schedule
                        new EMI for remaining amount</label>
                    <label class="radio-label"><input type="radio" name="reason" value="recovered"> EMI
                        Recovered</label>
                </div>
                <div class="form-group" style="margin-top:20px;">
                    <label>Remarks</label>
                    <input type="text" name="remarks" id="modRemarks" class="line-input" placeholder="Type here">
                </div>
            </div>
            <div class="modal-footer" style="padding:20px 24px; border-top:none; background:#fff;">
                <button type="button" class="btn-outline" onclick="closeEditModal()"
                    style="color:#111827; border-color:#D1D5DB;">Cancel</button>
                <button type="submit" name="edit_deduction" class="btn-primary">Save</button>
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
const allLoans = <?= json_encode($loans) ?>;
const allDeductions = <?= json_encode($deductions) ?>;
const allAdditions = <?= json_encode($additions) ?>;
const allRecoveries = <?= json_encode($recoveries) ?>;

let activeLoan = null;

// ==========================================
// VIEW CONTROLLER
// ==========================================
function toggleView(view) {
    document.getElementById('listView').style.display = 'none';
    document.getElementById('addView').style.display = 'none';
    document.getElementById('detailsView').style.display = 'none';
    document.getElementById('statementView').style.display = 'none';

    // UI Layout tweaks for Statement full width
    if (view === 'statement') {
        document.getElementById('mainPageTitle').style.display = 'none';
        document.getElementById('mainTopLinks').style.display = 'none';
        document.getElementById('mainCard').style.padding = '0';
        document.getElementById('mainCard').style.border = 'none';
        document.getElementById('mainCard').style.boxShadow = 'none';
        document.getElementById('mainCard').style.backgroundColor = 'transparent';
    } else {
        document.getElementById('mainPageTitle').style.display = 'block';
        document.getElementById('mainTopLinks').style.display = 'flex';
        document.getElementById('mainCard').style.padding = '24px';
        document.getElementById('mainCard').style.border = '1px solid #E5E7EB';
        document.getElementById('mainCard').style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.05)';
        document.getElementById('mainCard').style.backgroundColor = '#fff';
    }

    if (view === 'add') {
        document.getElementById('loanForm').reset();
        document.getElementById('formLoanId').value = '';
        document.getElementById('formSubmitBtn').name = 'add_loan';
        document.getElementById('formSubmitBtn').innerText = 'Add';
        document.getElementById('formHeading').innerText = 'NEW LOAN';
        document.getElementById('addView').style.display = 'block';
    } else if (view === 'details') {
        document.getElementById('detailsView').style.display = 'block';
    } else if (view === 'statement') {
        document.getElementById('statementView').style.display = 'block';
    } else {
        document.getElementById('listView').style.display = 'block';
    }
}

// ==========================================
// LOAN DETAILS POPULATOR
// ==========================================
function viewDetails(loanId) {
    activeLoan = allLoans.find(l => l.id == loanId);
    if (!activeLoan) return;

    const issueDate = new Date(activeLoan.issue_date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
    const repStart = activeLoan.repayment_start.length === 7 ? activeLoan.repayment_start : new Date(activeLoan
        .repayment_start).toLocaleDateString('en-GB', {
        month: 'short',
        year: 'numeric'
    });
    const repEnd = activeLoan.repayment_end.length === 7 ? activeLoan.repayment_end : new Date(activeLoan.repayment_end)
        .toLocaleDateString('en-GB', {
            month: 'short',
            year: 'numeric'
        });

    document.getElementById('detName').innerText = activeLoan.employee_name;
    document.getElementById('detType').innerText = activeLoan.loan_type;
    document.getElementById('detDept').innerText = activeLoan.department || '-';
    document.getElementById('detDesig').innerText = activeLoan.designation || '-';

    document.getElementById('detTotal').innerText = activeLoan.loan_amount;
    document.getElementById('detDue').innerText = activeLoan.loan_amount - activeLoan.repaid_amount;
    document.getElementById('detRepaid').innerText = activeLoan.repaid_amount || 0;
    document.getElementById('detEmi').innerText = activeLoan.emi_amount;

    document.getElementById('detIssue').innerText = issueDate;
    document.getElementById('detStart').innerText = repStart;
    document.getElementById('detEnd').innerText = repEnd;
    document.getElementById('detComp').innerText = activeLoan.salary_component || '-';

    document.getElementById('detRef').innerText = activeLoan.issue_reference || '-';
    document.getElementById('detNote').innerText = activeLoan.remarks || '-';

    // Start with Deduction tab open
    switchDetailsTab('Deduction');
    toggleView('details');
}

function switchDetailsTab(tab) {
    document.getElementById('tabDeduction').classList.remove('active');
    document.getElementById('tabAdditions').classList.remove('active');
    document.getElementById('tabRecoveries').classList.remove('active');
    document.getElementById(`tab${tab}`).classList.add('active');

    const thead = document.getElementById('detailsTableHeader');
    const tbody = document.getElementById('detailsTableBody');
    tbody.innerHTML = '';

    let data = [];
    if (tab === 'Deduction') {
        data = allDeductions.filter(d => d.loan_id == activeLoan.id);
        thead.innerHTML =
            `<th width="10%">S.NO</th><th width="25%">PAY PERIOD</th><th width="25%">EMI AMOUNT</th><th width="30%">REMARKS</th><th width="10%">ACTIONS</th>`;
        if (data.length === 0) {
            tbody.innerHTML =
                `<tr><td colspan="5" style="text-align:center; color:#9CA3AF;">No data available.</td></tr>`;
        } else {
            data.forEach((d, index) => {
                const sno = String(index + 1).padStart(2, '0');
                tbody.innerHTML += `<tr>
                    <td>${sno}</td><td>${d.pay_period}</td><td>₹${parseInt(d.emi_amount)}</td><td>${d.remarks || ''}</td>
                    <td>
                        <div class="action-btns">
                            <svg onclick="openEditModal('${d.id}', '${d.pay_period}', '${d.emi_amount}', '${d.remarks}')" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Edit"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                            <svg class="delete" onclick="deleteDeduction('${d.id}')" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" title="Delete"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </div>
                    </td>
                </tr>`;
            });
        }
    } else if (tab === 'Additions') {
        data = allAdditions.filter(d => d.loan_id == activeLoan.id);
        thead.innerHTML =
            `<th width="10%">S.NO</th><th width="30%">PAY PERIOD</th><th width="30%">ADDITION AMOUNT</th><th width="30%">REMARKS</th>`;
        if (data.length === 0) {
            tbody.innerHTML =
                `<tr><td colspan="4" style="text-align:center; color:#9CA3AF;">No data available.</td></tr>`;
        } else {
            data.forEach((d, i) => {
                tbody.innerHTML +=
                    `<tr><td>${String(i+1).padStart(2,'0')}</td><td>${d.pay_period}</td><td>₹${parseInt(d.amount)}</td><td>${d.remarks || ''}</td></tr>`;
            });
        }
    } else if (tab === 'Recoveries') {
        data = allRecoveries.filter(d => d.loan_id == activeLoan.id);
        thead.innerHTML =
            `<th width="10%">S.NO</th><th width="30%">PAY PERIOD</th><th width="30%">RECOVERY AMOUNT</th><th width="30%">REMARKS</th>`;
        if (data.length === 0) {
            tbody.innerHTML =
                `<tr><td colspan="4" style="text-align:center; color:#9CA3AF;">No data available.</td></tr>`;
        } else {
            data.forEach((d, i) => {
                tbody.innerHTML +=
                    `<tr><td>${String(i+1).padStart(2,'0')}</td><td>${d.pay_period}</td><td>₹${parseInt(d.amount)}</td><td>${d.remarks || ''}</td></tr>`;
            });
        }
    }

    document.getElementById('detCount').innerText = data.length;
    document.getElementById('detCountTotal').innerText = data.length;
}

// Ensure Edit Populates the Form
function editLoan() {
    if (!activeLoan) return;

    document.getElementById('formLoanId').value = activeLoan.id;
    document.getElementById('hiddenEmpCode').value = activeLoan.employee_code;
    document.getElementById('hiddenEmpName').value = activeLoan.employee_name;
    document.getElementById('empSearchInput').value = `${activeLoan.employee_name} (#${activeLoan.employee_code})`;

    document.getElementById('formLoanType').value = activeLoan.loan_type;
    document.getElementById('formLoanAmount').value = activeLoan.loan_amount;
    document.getElementById('formEmiAmount').value = activeLoan.emi_amount;
    document.getElementById('formIssueDate').value = activeLoan.issue_date;
    document.getElementById('formRepaymentStart').value = activeLoan.repayment_start.substring(0, 7);
    document.getElementById('formRepaymentEnd').value = activeLoan.repayment_end.substring(0, 7);

    document.getElementById('formRepaidAmount').value = activeLoan.repaid_amount;
    document.getElementById('formSalaryComponent').value = activeLoan.salary_component;
    document.getElementById('formIssueRef').value = activeLoan.issue_reference;
    document.getElementById('formRemarks').value = activeLoan.remarks;

    document.getElementById('formSubmitBtn').name = 'update_loan';
    document.getElementById('formSubmitBtn').innerText = 'Update';
    document.getElementById('formHeading').innerText = 'EDIT LOAN';

    toggleView('add');
}

// ==========================================
// LOAN STATEMENT LOGIC
// ==========================================
function openStatementView() {
    if (!activeLoan) return;

    document.getElementById('stName').innerText = `${activeLoan.employee_code} - ${activeLoan.employee_name}`;
    document.getElementById('stLoc').innerText = activeLoan.location || 'SILIGURI';
    document.getElementById('stDept').innerText = activeLoan.department || '-';
    document.getElementById('stDesig').innerText = activeLoan.designation || '-';

    const issueDate = new Date(activeLoan.issue_date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
    let dueStr = activeLoan.repayment_end;
    if (dueStr.length === 7) dueStr += '-01';
    const dueDate = new Date(dueStr).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });

    document.getElementById('stType').innerText = activeLoan.loan_type;
    document.getElementById('stTotal').innerText = `₹ ${parseInt(activeLoan.loan_amount)}`;
    document.getElementById('stIssue').innerText = issueDate;
    document.getElementById('stDue').innerText = dueDate;

    // Build the Ledger Array
    let ledger = [];
    ledger.push({
        dateStr: issueDate,
        dateObj: new Date(activeLoan.issue_date),
        type: 'Loan Amount',
        add: parseFloat(activeLoan.loan_amount),
        rec: 0,
        remarks: activeLoan.issue_reference || ''
    });

    let totalSalRec = 0,
        totalOthRec = 0,
        totalAdd = 0,
        numDed = 0,
        totalSched = 0;

    // Deductions
    const deds = allDeductions.filter(d => d.loan_id == activeLoan.id);
    deds.forEach(d => {
        let periodDate = new Date('01 ' + d.pay_period);
        if (isNaN(periodDate)) periodDate = new Date(d.pay_period + '-01');

        const amt = parseFloat(d.emi_amount);
        totalSalRec += amt;
        totalSched += amt;
        numDed++;
        ledger.push({
            dateStr: periodDate.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }),
            dateObj: periodDate,
            type: 'Payslip Deductions',
            add: 0,
            rec: amt,
            remarks: d.pay_period
        });
    });

    // Recoveries
    const recs = allRecoveries.filter(d => d.loan_id == activeLoan.id);
    recs.forEach(d => {
        let periodDate = new Date('01 ' + d.pay_period);
        if (isNaN(periodDate)) periodDate = new Date(d.pay_period + '-01');
        const amt = parseFloat(d.amount);
        totalOthRec += amt;
        ledger.push({
            dateStr: periodDate.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }),
            dateObj: periodDate,
            type: 'Other Recoveries',
            add: 0,
            rec: amt,
            remarks: d.remarks || ''
        });
    });

    // Additions
    const adds = allAdditions.filter(d => d.loan_id == activeLoan.id);
    adds.forEach(d => {
        let periodDate = new Date('01 ' + d.pay_period);
        if (isNaN(periodDate)) periodDate = new Date(d.pay_period + '-01');
        const amt = parseFloat(d.amount);
        totalAdd += amt;
        ledger.push({
            dateStr: periodDate.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }),
            dateObj: periodDate,
            type: 'Additions',
            add: amt,
            rec: 0,
            remarks: d.remarks || ''
        });
    });

    const overallRecoveries = totalSalRec + totalOthRec;
    const loanWithAdditions = parseFloat(activeLoan.loan_amount) + totalAdd;
    const dueAmount = Math.max(0, loanWithAdditions - overallRecoveries);

    // Populate stats
    document.getElementById('stSalRec').innerText = `₹ ${totalSalRec}`;
    document.getElementById('stOthRec').innerText = `₹ ${totalOthRec}`;
    document.getElementById('stTotRec').innerText = `₹ ${overallRecoveries}`;
    document.getElementById('stTotAdd').innerText = `₹ ${totalAdd}`;
    document.getElementById('stNumDed').innerText = numDed;
    document.getElementById('stTotSched').innerText = `₹ ${totalSched}`;
    document.getElementById('stDueOnDate').innerText = `₹ ${dueAmount}`;
    document.getElementById('stEmi').innerText = `₹ ${parseInt(activeLoan.emi_amount)}`;

    // Render ledger
    ledger.sort((a, b) => a.dateObj - b.dateObj);
    const tbody = document.getElementById('statementTableBody');
    tbody.innerHTML = '';
    let balance = 0;
    ledger.forEach((item, idx) => {
        balance = balance + item.add - item.rec;
        tbody.innerHTML += `
            <tr>
                <td>${idx + 1}</td>
                <td>${item.dateStr}</td>
                <td>${item.type}</td>
                <td>₹ ${item.add}</td>
                <td>₹ ${item.rec}</td>
                <td>₹ ${balance}</td>
                <td>${item.remarks}</td>
            </tr>
        `;
    });

    document.getElementById('stCount').innerText = ledger.length;
    document.getElementById('stCountTotal').innerText = ledger.length;

    toggleView('statement');
}

// ==========================================
// MODAL & DELETE ACTIONS
// ==========================================
function openEditModal(id, period, amount, remarks) {
    document.getElementById('modDedId').value = id;
    document.getElementById('modPayPeriod').value = period;
    document.getElementById('modAmount').value = parseInt(amount);
    document.getElementById('modRemarks').value = remarks !== 'null' ? remarks : '';
    document.getElementById('modalSubtitle').innerText =
        `Select reason for editing ${period} payroll deduction amounting ₹ ${parseInt(amount)} from the list.`;
    document.getElementById('editDeductionModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editDeductionModal').style.display = 'none';
}

function deleteDeduction(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this deduction deletion!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_deduction');
            formData.append('id', id);

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        Swal.fire('Deleted!', 'The deduction has been deleted.', 'success').then(() => {
                            location.reload();
                        });
                    }
                });
        }
    });
}

// ==========================================
// FORM HELPERS & STARTUP
// ==========================================
function parseEmployeeSelection() {
    const input = document.getElementById('empSearchInput');
    const val = input.value.trim();
    if (val) {
        const match = val.match(/(.+) \(#(.+)\)/);
        if (match) {
            document.getElementById('hiddenEmpName').value = match[1].trim();
            document.getElementById('hiddenEmpCode').value = match[2].trim();
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    if (status === 'added') {
        Swal.fire({
            title: 'Loan Added!',
            text: 'The new loan has been recorded successfully.',
            icon: 'success',
            confirmButtonColor: '#0066FF'
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (status === 'loan_updated') {
        Swal.fire({
            title: 'Loan Updated!',
            text: 'The loan has been updated successfully.',
            icon: 'success',
            confirmButtonColor: '#0066FF'
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (status === 'deduction_updated') {
        Swal.fire({
            title: 'Deduction Updated!',
            text: 'The deduction details were updated.',
            icon: 'success',
            confirmButtonColor: '#0066FF'
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>
<script src="includes/assets/scripts.js"></script>