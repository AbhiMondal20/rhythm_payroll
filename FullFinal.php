<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
require_once 'includes/db_client.php';

$page_title = 'Payroll - Full Final Settlement';

// Simulate an employee search query
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$employee_selected = !empty($search_query);

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
/* ── Common Styles ── */
.btn-back {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 6px;
    color: #6B7280; background: #fff; border: 1px solid #D1D5DB;
    text-decoration: none; transition: all 0.2s; cursor: pointer;
}
.btn-back:hover { background: #F3F4F6; color: #111827; }

.payroll-header-wrapper {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 15px; flex-wrap: wrap; gap: 15px;
}
.page-title { font-size: 20px; font-weight: 700; color: #111827; margin: 0; }

.payroll-top-links { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.payroll-top-links a { font-size: 13px; color: #6B7280; text-decoration: none; transition: color 0.15s; }
.payroll-top-links a:hover { color: #2563EB; }
.payroll-top-links a.active { color: #2563EB; font-weight: 600; border-bottom: 2px solid #2563EB; padding-bottom: 2px; }
.payroll-top-links .separator { color: #D1D5DB; font-size: 14px; }

/* ── Main Container ── */
.payroll-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    border: 1px solid #E5E7EB;
    padding: 24px;
    min-height: 400px;
    margin-bottom: 40px;
}

/* ── Search Bar ── */
.search-container {
    padding: 20px 24px;
    background: #fff;
    border-bottom: 1px solid #E5E7EB;
    display: flex;
    gap: 15px;
}
.search-container input {
    padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 4px;
    width: 300px; outline: none; font-size: 14px;
}
.search-container input:focus { border-color: #2563EB; }
.btn-search {
    background: #0066FF; color: #fff; border: none; padding: 8px 16px;
    border-radius: 4px; cursor: pointer; font-size: 14px;
}

/* ── Employee Details Header (From Screenshot) ── */
.employee-header-panel {
    background: #fff;
    padding: 24px;
    display: flex;
    justify-content: flex-start;
    gap: 80px;
}
.header-col {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.detail-row {
    display: flex;
    align-items: center;
}
.detail-label {
    width: 140px;
    font-size: 13px;
    color: #4B5563;
}
.detail-value {
    font-size: 13px;
    color: #111827;
    font-weight: 500;
}

/* Green Checkboxes */
.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
    border-left: 1px solid #E5E7EB;
    padding-left: 40px;
}
.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #111827;
    font-weight: 500;
}
.checkbox-group input[type="checkbox"] {
    appearance: none;
    width: 18px;
    height: 18px;
    background-color: #10B981;
    border-radius: 3px;
    position: relative;
}
.checkbox-group input[type="checkbox"]::after {
    content: ''; position: absolute; left: 6px; top: 2px;
    width: 5px; height: 10px; border: solid white;
    border-width: 0 2px 2px 0; transform: rotate(45deg);
}

/* ── Tab Navigation ── */
.tabs-header {
    background: #fff;
    padding: 0 24px;
    display: flex;
    align-items: center;
    border-top: 15px solid #F3F4F6; /* Gray separator space */
    border-bottom: 1px solid #E5E7EB;
    position: relative;
    gap: 24px;
}
.tab-link {
    background: none; border: none; font-size: 13px; color: #6B7280;
    padding: 16px 0; cursor: pointer; position: relative; font-weight: 500;
}
.tab-link.active { color: #0066FF; }
.tab-link.active::after {
    content: ''; position: absolute; bottom: -1px; left: 0;
    width: 100%; height: 2px; background: #0066FF;
}
.refresh-link {
    margin-left: auto; display: flex; align-items: center; gap: 6px;
    font-size: 13px; color: #0066FF; text-decoration: none; font-weight: 500;
}

/* ── Form Section ── */
.tab-content {
    background: #fff;
    padding: 30px 24px;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    column-gap: 40px;
    row-gap: 35px;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.form-group label {
    font-size: 12px;
    color: #6B7280;
    font-weight: 500;
}

/* Underline Inputs (Like Screenshot) */
.line-input {
    width: 100%;
    padding: 4px 0 8px 0;
    border: none;
    border-bottom: 1px solid #D1D5DB;
    font-size: 14px;
    color: #111827;
    background: transparent;
    outline: none;
    transition: border-color 0.2s;
}
.line-input:focus { border-bottom-color: #0066FF; }
select.line-input {
    cursor: pointer; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 5l3 3 3-3'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right center; padding-right: 20px;
}
input[type="date"].line-input::-webkit-calendar-picker-indicator {
    color: #0066FF; cursor: pointer; opacity: 0.6;
}

.full-width { grid-column: 1 / -1; }

/* ── Footer ── */
.action-footer {
    display: flex; justify-content: flex-end; padding: 20px 24px;
    background: #F9FAFB; border-top: 1px solid #E5E7EB;
}
.btn-next {
    background: #0066FF; color: #fff; border: none; padding: 8px 30px;
    border-radius: 4px; font-size: 14px; cursor: pointer; font-weight: 500;
}
</style>

<div class="payroll-header-wrapper">
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="javascript:history.back()" class="btn-back" title="Go Back">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
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
        <a href="FullFinal" class="active">Final Settlement</a> <span class="separator">|</span>
        <a href="SalaryStructure">Salary Structure</a> <span class="separator">|</span>
        <a href="Timesheet">Timesheet</a>
    </div>
</div>

<div class="payroll-card">
    <div class="card-top-bar">
        <div class="breadcrumb"><strong>Payroll</strong> &nbsp;&gt;&nbsp; Final Settlement</div>
    </div>
    <!-- Initial Search View -->
    <div class="search-container">
        <form method="GET" action="" style="display: flex; gap: 10px; margin: 0; width: 100%;">
            <input type="text" name="search" placeholder="Search by name or #code" value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit" class="btn-search">Get Details</button>
        </form>
    </div>

    <?php if ($employee_selected): ?>
    
    <!-- Redesigned Details Header based on image_0a26fe.png -->
    <div class="employee-header-panel">
        <div class="header-col">
            <div class="detail-row">
                <div class="detail-label">Name</div>
                <div class="detail-value">Abhijit Kumar Mondal</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Employee Code</div>
                <div class="detail-value">1104</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Age</div>
                <div class="detail-value">22</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Gender</div>
                <div class="detail-value">Male</div>
            </div>
        </div>

        <div class="header-col">
            <div class="detail-row">
                <div class="detail-label">Department</div>
                <div class="detail-value">SOFTWARE</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Designation</div>
                <div class="detail-value">COMPUTER OPERATOR</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Date Of Joining</div>
                <div class="detail-value">14 Oct 2025</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Date Of Leaving</div>
                <div class="detail-value"></div>
            </div>
        </div>

        <div class="checkbox-group">
            <label><input type="checkbox" checked readonly> ESI</label>
            <label><input type="checkbox" checked readonly> PF</label>
            <label><input type="checkbox" checked readonly> PT</label>
        </div>
    </div>

    <!-- Tabs underneath -->
    <div class="tabs-header">
        <button class="tab-link active">Resignation</button>
        <button class="tab-link">Pending Requests</button>
        <button class="tab-link">Loans</button>
        <button class="tab-link">Assets</button>
        <button class="tab-link">Attendance</button>
        <button class="tab-link">Leave</button>
        <button class="tab-link">Gratuity</button>
        <button class="tab-link">Advance Payment/ Deductions</button>
        
        <a href="#" class="refresh-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M2.13 15.57a9 9 0 1 0 3.84-10.15L2 8"/></svg>
            Refresh
        </a>
    </div>

    <!-- Resignation Tab Content Form Grid -->
    <div class="tab-content">
        <div class="form-grid">
            
            <div class="form-group">
                <label>Date Of Resignation</label>
                <input type="date" class="line-input" value="2026-07-01">
            </div>
            
            <div class="form-group">
                <label>Date Of Leaving</label>
                <input type="date" class="line-input">
            </div>
            
            <div class="form-group">
                <label>Reason For Resignation</label>
                <select class="line-input">
                    <option>Left Service/Job</option>
                    <option>Health Issues</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Settlement Period</label>
                <select class="line-input">
                    <option>Jul-2026</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Shortfall In Notice</label>
                <input type="text" class="line-input" value="0">
            </div>
            
            <div class="form-group">
                <label>Reason For Unavailing ESI</label>
                <select class="line-input">
                    <option>Left Service</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Reason For Unavailing PF</label>
                <select class="line-input">
                    <option>RETIREMENT</option>
                    <option>RESIGNATION</option>
                </select>
            </div>
            
            <div class="form-group full-width" style="margin-top: 10px;">
                <label>Remarks</label>
                <input type="text" class="line-input">
            </div>
            
        </div>
    </div>

    <!-- Footer Action -->
    <div class="action-footer">
        <button class="btn-next">Next</button>
    </div>
    
    <?php elseif(isset($_GET['search'])): ?>
        <div style="padding: 60px; text-align: center; color: #6B7280;">
            No record found for "<?= htmlspecialchars($search_query) ?>"
        </div>
    <?php endif; ?>

</div>

<?php
    $page_content = ob_get_clean();
    include 'includes/header.php';
    echo $page_content;
    include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>