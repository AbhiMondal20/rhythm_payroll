<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
require_once 'includes/db_client.php';
$page_title = 'Payroll - Hold/Release Salary';

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

/* End Common Styles */

.payroll-tabs {
    display: flex;
    align-items: center;
    gap: 5px;
    overflow-x: auto;
    scrollbar-width: none;
}

.payroll-tabs::-webkit-scrollbar {
    display: none;
}

.payroll-tab {
    font-size: 14px;
    color: #6B7280;
    text-decoration: none;
    padding: 12px 16px;
    border-bottom: 3px solid transparent;
    white-space: nowrap;
    transition: all 0.2s;
    font-weight: 500;
}

.payroll-tab:hover {
    color: #111827;
}

.payroll-tab.active {
    color: #0066FF;
    border-bottom-color: #0066FF;
    font-weight: 600;
}

.payroll-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    border: 1px solid #E5E7EB;
    padding: 24px;
    min-height: 400px;
}

.card-top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.breadcrumb {
    font-size: 15px;
    color: #4B5563;
}

.breadcrumb strong {
    color: #111827;
    font-weight: 600;
}

/* Inner Tabs (Hold / Release) */
.inner-tabs {
    display: flex;
    gap: 20px;
    border-bottom: 1px solid #E5E7EB;
    margin-bottom: 25px;
}

.inner-tab {
    font-size: 14px;
    color: #6B7280;
    text-decoration: none;
    padding-bottom: 10px;
    border-bottom: 2px solid transparent;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}

.inner-tab:hover {
    color: #111827;
}

.inner-tab.active {
    color: #0066FF;
    border-bottom-color: #0066FF;
    font-weight: 600;
}

/* Form Sections */
.section-heading {
    font-size: 12px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 12px;
    text-transform: uppercase;
    margin-top: 25px;
}

.search-filter-row {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    max-width: 500px;
}

.search-line-wrapper {
    position: relative;
    flex: 1;
}

.search-line-wrapper svg {
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

.search-line-wrapper input {
    width: 100%;
    padding: 8px 10px 8px 32px;
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.search-line-wrapper input:focus {
    border-color: #0066FF;
}

.btn-filters {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #fff;
    border: 1px solid #D1D5DB;
    color: #4B5563;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    height: 36px;
}

.btn-filters:hover {
    background: #F9FAFB;
    border-color: #9CA3AF;
}

.selected-employee-box {
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    padding: 15px;
    max-width: 800px;
    min-height: 50px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #111827;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #0066FF;
    margin: 0;
}

.pay-period-row {
    display: flex;
    gap: 40px;
    align-items: flex-end;
    max-width: 600px;
    margin-bottom: 30px;
}

.form-group {
    flex: 1;
}

.form-group label {
    display: block;
    font-size: 12px;
    color: #4B5563;
    margin-bottom: 8px;
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

.line-input::placeholder {
    color: #9CA3AF;
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

.form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
    gap: 10px;
}

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

.btn-outline {
    background: #fff;
    color: #4B5563;
    border: 1px solid #D1D5DB;
    padding: 8px 24px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-outline:hover {
    background: #F3F4F6;
}

/* Table Styles for Release Tab */
.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.data-table th,
.data-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid #E5E7EB;
    font-size: 14px;
}

.data-table th {
    background-color: #F9FAFB;
    color: #4B5563;
    font-weight: 600;
}

.btn-success {
    background: #10B981;
    color: #fff;
    border: none;
    padding: 6px 16px;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
}

.btn-success:hover {
    background: #059669;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 0;
}

.empty-state-svg {
    width: 120px;
    height: 120px;
    margin: 0 auto 16px;
}

.empty-state p {
    color: #9CA3AF;
    font-size: 14px;
    margin: 0;
}

/* =========================================
       MODAL STYLES (Advance Employee Search)
       ========================================= */
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
    box-sizing: border-box;
}

.modal-content {
    background: #fff;
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #E5E7EB;
}

.modal-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.modal-close {
    background: none;
    border: 1px solid #D1D5DB;
    font-size: 20px;
    cursor: pointer;
    color: #6B7280;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-close:hover {
    background: #F3F4F6;
    color: #111827;
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

/* Modal Filter Grid */
.modal-filter-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.modal-filter-grid .form-group {
    margin-bottom: 0;
}

.modal-filter-grid select.line-input {
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    padding: 8px 12px;
    width: 100%;
    font-size: 13px;
    background-position: right 10px center;
}

.modal-search-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-search-row select {
    border: 1px solid #D1D5DB;
    border-radius: 4px;
    padding: 4px 8px;
}

/* Modal Results Layout */
.modal-results-layout {
    display: flex;
    gap: 30px;
    margin-top: 20px;
}

.modal-emp-list-sec {
    flex: 3;
}

.modal-recent-sec {
    flex: 1;
    border-left: 1px solid #E5E7EB;
    padding-left: 20px;
}

.modal-emp-header {
    margin-bottom: 15px;
    border-bottom: 1px solid #E5E7EB;
    padding-bottom: 10px;
}

.modal-emp-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    max-height: 200px;
    overflow-y: auto;
}

/* Recent Search Sidebar */
.recent-tabs {
    display: flex;
    border-bottom: 1px solid #E5E7EB;
    margin-bottom: 15px;
}

.recent-tab {
    padding: 6px 12px;
    font-size: 12px;
    color: #6B7280;
    cursor: pointer;
    border-bottom: 2px solid transparent;
}

.recent-tab.active {
    color: #0066FF;
    border-bottom-color: #0066FF;
    font-weight: 500;
}

.recent-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.recent-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #4B5563;
    padding: 8px 0;
    border-bottom: 1px dashed #E5E7EB;
}

.recent-list li button {
    background: none;
    border: 1px solid #D1D5DB;
    border-radius: 50%;
    cursor: pointer;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #E5E7EB;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #F9FAFB;
    border-radius: 0 0 8px 8px;
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
            <strong>Payroll</strong> &nbsp;&gt;&nbsp; Hold/ Release Salary
        </div>
    </div>

    <div class="inner-tabs">
        <span class="inner-tab active" id="tabHold" onclick="switchTab('hold')">Hold</span>
        <span class="inner-tab" id="tabRelease" onclick="switchTab('release')">Release</span>
    </div>

    <div id="sectionHold" style="display: block;">
        <form action="process_hold_salary.php" method="POST" id="holdSalaryForm">
            <div class="section-heading">Select Employees to Hold Salary</div>

            <div class="search-filter-row">
                <div class="search-line-wrapper">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="mainEmpSearch" list="employeeList" placeholder="Search by name or #code"
                        autocomplete="off" onchange="addSingleEmployeeFromSearch()">
                </div>
                <button type="button" class="btn-filters" onclick="openFilterModal()">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                    </svg>
                    Filters
                </button>
            </div>

            <div class="selected-employee-box" id="mainSelectedEmployeesBox">
                <span style="color: #9CA3AF; font-size: 13px; align-self: center;" id="emptySelectionText">No employees
                    selected. Use search or filters to add.</span>
            </div>

            <div class="section-heading">Select Pay Period</div>
            <div class="pay-period-row">
                <div class="form-group">
                    <label>Month</label>
                    <select name="pay_month" class="line-input" required>
                        <option value="May-2026" selected>May-2026</option>
                        <option value="Apr-2026">Apr-2026</option>
                        <option value="Mar-2026">Mar-2026</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="line-input"
                        placeholder="Write your Remarks for holding the Salary">
                </div>
            </div>

            <div class="form-actions" style="justify-content: flex-start;">
                <button type="submit" name="action" value="hold" class="btn-primary">Hold</button>
            </div>
        </form>
    </div>
    <div id="sectionRelease" style="display: none;">

        <?php if ($held_salaries_result && mysqli_num_rows($held_salaries_result) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Code</th>
                        <th>Pay Month</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($held_salaries_result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                        <td><?= htmlspecialchars($row['employee_code']) ?></td>
                        <td><?= htmlspecialchars($row['pay_month']) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td>
                            <form action="process_hold_salary.php" method="POST" style="margin:0;">
                                <input type="hidden" name="record_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="action" value="release" class="btn-success">Release</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <svg class="empty-state-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="40" fill="#EEF2FF" />
                <rect x="35" y="30" width="30" height="40" rx="2" fill="#fff" stroke="#D1D5DB" stroke-width="2" />
                <path d="M35 34C35 32.8954 35.8954 32 37 32H63C64.1046 32 65 32.8954 65 34V42H35V34Z" fill="#4B5563" />
                <rect x="40" y="35" width="12" height="2" fill="#9CA3AF" />
                <rect x="40" y="48" width="14" height="2" fill="#0066FF" />
                <rect x="40" y="52" width="20" height="2" fill="#E5E7EB" />
                <rect x="40" y="58" width="14" height="2" fill="#0066FF" />
                <rect x="40" y="62" width="10" height="2" fill="#E5E7EB" />
            </svg>
            <p>There are no salaries on hold for now.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<div id="filterModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Advance Employee Search</h2>
            <button type="button" class="modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="modal-body">

            <div class="search-line-wrapper" style="margin-bottom: 25px;">
                <svg viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" placeholder="Search by name or #code"
                    style="border-radius: 4px; border: 1px solid #D1D5DB; padding-left: 35px;">
            </div>

            <div class="modal-filter-grid">
                <div class="form-group"><label>Organization</label><select class="line-input">
                        <option>Organization - 1</option>
                    </select></div>
                <div class="form-group"><label>Locations</label><select class="line-input">
                        <option>Locations - 4</option>
                    </select></div>
                <div class="form-group"><label>Department</label><select class="line-input">
                        <option>Department - 22</option>
                    </select></div>
                <div class="form-group"><label>Designation</label><select class="line-input">
                        <option>Designation - 23</option>
                    </select></div>

                <div class="form-group"><label>Status</label><select class="line-input">
                        <option>Status - 1</option>
                    </select></div>
                <div class="form-group"><label>Group</label><select class="line-input">
                        <option>Group - 0</option>
                    </select></div>
                <div class="form-group"><label>Sub Group</label><select class="line-input">
                        <option>Sub Group - 0</option>
                    </select></div>
                <div class="form-group"><label>Category</label><select class="line-input">
                        <option>Category - 0</option>
                    </select></div>

                <div class="form-group"><label>Grade</label><select class="line-input">
                        <option>Grade - 0</option>
                    </select></div>
                <div class="form-group"><label>Additional Field</label><select class="line-input">
                        <option>Additional Field - 0</option>
                    </select></div>
                <div class="form-group"><label>Field Value</label><select class="line-input">
                        <option>Field Value - 0</option>
                    </select></div>
            </div>

            <div class="modal-search-row">
                <span style="font-size: 13px; color: #4B5563;">Records per page : <select>
                        <option>25</option>
                    </select></span>
                <button type="button" class="btn-primary" onclick="performModalSearch()">Search</button>
            </div>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #E5E7EB;">

            <div class="modal-results-layout">
                <div class="modal-emp-list-sec">
                    <div class="modal-emp-header">
                        <label class="checkbox-label" style="font-weight: 500;">
                            <input type="checkbox" id="selectAllModalEmp" onclick="toggleAllModalEmp(this)">
                            Employees Found - <span id="empFoundCount">0</span>
                        </label>
                    </div>
                    <div class="modal-emp-grid" id="modalEmpGrid">
                        <span style="font-size: 13px; color: #9CA3AF;">Click search to find employees.</span>
                    </div>
                </div>

                <div class="modal-recent-sec">
                    <div class="recent-tabs">
                        <span class="recent-tab active">Recent Search</span>
                        <span class="recent-tab">Saved Search</span>
                    </div>
                    <ul class="recent-list">
                        <li><span>08-06 07:45</span> <button>&minus;</button></li>
                        <li><span>05-06 17:34</span> <button>&minus;</button></li>
                        <li><span>02-06 05:17</span> <button>&minus;</button></li>
                        <li><span>01-06 18:36</span> <button>&minus;</button></li>
                        <li><span>01-06 18:20</span> <button>&minus;</button></li>
                    </ul>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button type="button" class="btn-outline" onclick="clearModalSelections()">Clear All</button>
            <button type="button" class="btn-outline">Save Search</button>
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
// Tab Switching
function switchTab(tab) {
    document.getElementById('tabHold').classList.remove('active');
    document.getElementById('tabRelease').classList.remove('active');
    document.getElementById('sectionHold').style.display = 'none';
    document.getElementById('sectionRelease').style.display = 'none';

    if (tab === 'hold') {
        document.getElementById('tabHold').classList.add('active');
        document.getElementById('sectionHold').style.display = 'block';
    } else {
        document.getElementById('tabRelease').classList.add('active');
        document.getElementById('sectionRelease').style.display = 'block';
    }
}

// Global array to track selected employees for the form
let selectedEmployees = [];

// Handle individual selection from main input
function addSingleEmployeeFromSearch() {
    const input = document.getElementById('mainEmpSearch');
    const val = input.value.trim();
    if (val) {
        // Simple extraction assuming format "Name (#Code)"
        const match = val.match(/(.+) \(#(.+)\)/);
        if (match) {
            const name = match[1].trim();
            const code = match[2].trim();
            addEmployeeToSelection(code, name);
        }
        input.value = ''; // clear input
    }
}

// ── MODAL LOGIC ──

function openFilterModal() {
    document.getElementById('filterModal').style.display = 'flex';
}

function closeFilterModal() {
    document.getElementById('filterModal').style.display = 'none';
}

// Mock Data for "Search" button in modal
const mockModalData = [{
        id: '1110',
        name: 'Riyanka Biswas'
    },
    {
        id: '1111',
        name: 'Mou Mahanta'
    },
    {
        id: '1112',
        name: 'Chandrima Sarkar'
    },
    {
        id: '1113',
        name: 'Sharmistha Raha'
    },
    {
        id: '1114',
        name: 'Sukla Sarkar'
    },
    {
        id: '1115',
        name: 'Souvik Dey'
    },
    {
        id: '1116',
        name: 'Tshuden Tamang'
    }
];

function performModalSearch() {
    const grid = document.getElementById('modalEmpGrid');
    document.getElementById('empFoundCount').innerText = mockModalData.length;
    document.getElementById('selectAllModalEmp').checked = false;

    grid.innerHTML = '';
    mockModalData.forEach(emp => {
        grid.innerHTML += `
            <label class="checkbox-label">
                <input type="checkbox" class="modal-emp-checkbox" value="${emp.id}" data-name="${emp.name}"> 
                ${emp.name} - ${emp.id}
            </label>
        `;
    });
}

function toggleAllModalEmp(source) {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = source.checked;
    });
}

function clearModalSelections() {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAllModalEmp').checked = false;
}

function applyModalFilters() {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox:checked');
    checkboxes.forEach(cb => {
        addEmployeeToSelection(cb.value, cb.getAttribute('data-name'));
    });
    closeFilterModal();
}

// ── SELECTION MANAGER ──

function addEmployeeToSelection(id, name) {
    // Avoid duplicates
    if (!selectedEmployees.find(e => e.id === id)) {
        selectedEmployees.push({
            id,
            name
        });
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
        box.innerHTML =
            '<span style="color: #9CA3AF; font-size: 13px; align-self: center;" id="emptySelectionText">No employees selected. Use search or filters to add.</span>';
        return;
    }

    box.innerHTML = '';
    selectedEmployees.forEach(emp => {
        box.innerHTML += `
            <label class="checkbox-label" style="background: #F3F4F6; padding: 6px 12px; border-radius: 4px; border: 1px solid #E5E7EB;">
                <input type="checkbox" name="selected_employees[]" value="${emp.id}" checked onclick="removeEmployee('${emp.id}')" style="accent-color: #EF4444;"> 
                ${emp.name} - ${emp.id}
            </label>
        `;
    });
}
</script>

<script src="includes/assets/scripts.js"></script>