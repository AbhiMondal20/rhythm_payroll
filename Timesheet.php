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

$held_salaries_sql = "SELECT * FROM `held_salaries` WHERE `status` = 'held' ORDER BY `id` DESC";
$held_salaries_result = @mysqli_query($conn, $held_salaries_sql);

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

/* End Common Styles */
</style>

<datalist id="employeeList">
    <?php foreach ($employees as $emp): ?>
    <option value="<?= htmlspecialchars($emp['employee_name'] . ' (#' . $emp['employee_code'] . ')') ?>">
        <?php endforeach; ?>
</datalist>

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
            <a href="EditPayslip">Edit Payslip</a> <span class="separator">|</span>
            <a href="Loans">Loans</a> <span class="separator">|</span>
            <a href="ProcessPayslip">Process Payslip</a> <span class="separator">|</span>
            <a href="FullFinal">Final Settlement</a> <span class="separator">|</span>
            <a href="SalaryStructure">Salary Structure</a> <span class="separator">|</span>
            <a href="Timesheet">Timesheet</a>
        </div>
        <!-- <hr class="payroll-divider"> -->
</div>

<div class="payroll-card">

    <div class="card-top-bar">
        <div class="breadcrumb">
            <strong>Payroll</strong> &nbsp;&gt;&nbsp; Timesheet
        </div>
    </div>
</div>
<?php
    $page_content = ob_get_clean();
    include 'includes/header.php';
    echo $page_content;
    include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>