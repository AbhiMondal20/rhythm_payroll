<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
require_once 'includes/db_client.php';

$page_title = 'Payroll - Advance Payment/Deduction';

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

// ==========================================
// FETCH SALARY COMPONENTS FOR DROPDOWN
// ==========================================
$components = [];
$comp_sql = "SELECT `id`, `name` FROM `salary_component_categories`"; 
$comp_result = @mysqli_query($conn, $comp_sql);

if ($comp_result && mysqli_num_rows($comp_result) > 0) {
    while ($row = mysqli_fetch_assoc($comp_result)) {
        $components[] = $row;
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
    border-top: 1px solid #D1D5DB; /* Change color here if needed */
    margin: 25px 0; /* Adjust spacing around the line here */
}
/* End Common Styles */


.page-title { font-size: 20px; font-weight: 700; color: #111827; margin: 0; }
.payroll-tabs { display: flex; align-items: center; gap: 5px; overflow-x: auto; scrollbar-width: none; }
.payroll-tabs::-webkit-scrollbar { display: none; }
.payroll-tab {
    font-size: 14px; color: #6B7280; text-decoration: none; padding: 12px 16px;
    border-bottom: 3px solid transparent; white-space: nowrap; transition: all 0.2s; font-weight: 500;
}
.payroll-tab:hover { color: #111827; }
.payroll-tab.active { color: #0066FF; border-bottom-color: #0066FF; font-weight: 600; }

/* ── Content Card ── */
.payroll-card {
    background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border: 1px solid #E5E7EB; padding: 24px;
}

/* ── Card Header ── */
.card-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.breadcrumb { font-size: 15px; color: #4B5563; }
.breadcrumb strong { color: #111827; font-weight: 600; }

.btn-outline-primary {
    background: #fff; color: #0066FF; border: 1px solid #0066FF; padding: 8px 16px;
    border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer;
    display: flex; align-items: center; gap: 6px; transition: all 0.2s;
}
.btn-outline-primary:hover { background: #F0F5FF; }

/* ── New Add Form Styles ── */
.add-form-section { display: none; }
.form-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px; margin-bottom: 25px; }
.form-row.two-col { grid-template-columns: 1fr 1fr 2fr; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: #111827; margin-bottom: 8px; }

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

.search-line-wrapper { position: relative; }
.search-line-wrapper svg {
    position: absolute; left: 0; top: 50%; transform: translateY(-50%);
    width: 16px; height: 16px; stroke: #9CA3AF; fill: none; stroke-width: 2;
}
.search-line-wrapper input { padding-left: 24px; }

.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 40px; }
.btn-primary {
    background: #0066FF; color: #fff; border: none; padding: 10px 24px;
    border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer;
}
.btn-primary:hover { background: #0052cc; }

/* ── Filters Section (List View) ── */
.list-view-section { display: block; }
.filters-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 40px; margin-bottom: 40px; }
.filters-grid label { display: block; font-size: 12px; font-weight: 600; color: #111827; margin-bottom: 12px; text-transform: uppercase; }
.search-wrapper { position: relative; display: flex; align-items: center; }
.search-wrapper svg { position: absolute; left: 12px; width: 16px; height: 16px; stroke: #9CA3AF; fill: none; }
.search-input { width: 100%; padding: 10px 10px 10px 36px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 14px; }
.pay-period-controls { display: flex; align-items: flex-end; gap: 20px; }
.select-group { flex: 1; min-width: 120px; }

/* ── Table Styles ── */
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
.data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #E5E7EB; font-size: 14px; }
.data-table th { background-color: #F9FAFB; color: #4B5563; font-weight: 600; }
.data-table tbody tr:hover { background-color: #F9FAFB; }

/* ── Empty State ── */
.empty-state { text-align: center; padding: 60px 0; }
.empty-state-svg { width: 120px; height: 120px; margin: 0 auto 16px; }
.empty-state p { color: #9CA3AF; font-size: 14px; margin: 0; }

@media (max-width: 900px) {
    .form-row, .form-row.two-col { grid-template-columns: 1fr 1fr; }
    .filters-grid { grid-template-columns: 1fr; }
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
            <strong>Payroll</strong> &nbsp;&gt;&nbsp; Advance Payment/Deduction
        </div>
        <button type="button" class="btn-outline-primary" id="btnAddNew" onclick="toggleFormView(true)">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add New
        </button>
    </div>

    <div class="add-form-section" id="addFormSection">
        <form action="process_payment_deduction.php" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <div class="search-line-wrapper">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="employee_name" list="employeeList" class="line-input" placeholder="Search by name or #code" autocomplete="off" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Financial Year</label>
                    <select name="financial_year" class="line-input">
                        <option value="2026">2026</option>
                        <option value="2025">2025</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pay Period</label>
                    <select name="pay_period" class="line-input">
                        <option value="Jun-2026">Jun-2026</option>
                        <option value="May-2026">May-2026</option>
                        <option value="Apr-2026">Apr-2026</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Component Type</label>
                    <select name="component_type" class="line-input" required>
                        <option value="">Select Type</option>
                        <option value="Earning">Earning</option>
                        <option value="Deduction">Deduction</option>
                    </select>
                </div>
            </div>

            <div class="form-row two-col">
                <div class="form-group">
                    <label>Component</label>
                    <select name="component" class="line-input" required>
                        <option value="">Select Component</option>
                        <?php foreach ($components as $comp): ?>
                            <option value="<?= htmlspecialchars($comp['name']) ?>">
                                <?= htmlspecialchars($comp['name']) ?>
                            </option>
                        <?php endforeach; ?>
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

    <div class="list-view-section" id="listViewSection">
        
        <div class="filters-grid">
            <div class="form-group">
                <label>SELECT EMPLOYEE</label>
                <div class="search-wrapper">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" class="search-input" list="employeeList" placeholder="Search by name or #code" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label>SELECT PAY PERIOD</label>
                <div class="pay-period-controls">
                    <div class="select-group">
                        <label style="text-transform:none;">Year</label>
                        <select class="line-input">
                            <option>2026</option>
                            <option>2025</option>
                        </select>
                    </div>
                    <div class="select-group">
                        <label style="text-transform:none;">Month</label>
                        <select class="line-input">
                            <option>May-2026</option>
                            <option>Apr-2026</option>
                        </select>
                    </div>
                    <button class="btn-primary" style="height: 38px;">Get Details</button>
                </div>
            </div>
        </div>

        <?php
        $sql = "SELECT * FROM advance_payments ORDER BY id DESC"; 
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
                <svg class="empty-state-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="40" fill="#EEF2FF"/>
                    <rect x="35" y="30" width="30" height="40" rx="2" fill="#fff" stroke="#D1D5DB" stroke-width="2"/>
                    <path d="M35 34C35 32.8954 35.8954 32 37 32H63C64.1046 32 65 32.8954 65 34V42H35V34Z" fill="#4B5563"/>
                    <rect x="40" y="35" width="12" height="2" fill="#9CA3AF"/>
                    <rect x="40" y="48" width="14" height="2" fill="#0066FF"/>
                    <rect x="40" y="52" width="20" height="2" fill="#E5E7EB"/>
                    <rect x="40" y="58" width="14" height="2" fill="#0066FF"/>
                    <rect x="40" y="62" width="10" height="2" fill="#E5E7EB"/>
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
</script>
<script src="includes/assets/scripts.js"></script>