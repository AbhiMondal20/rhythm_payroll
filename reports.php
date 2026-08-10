<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Reports';

$types = [];

$typeQuery = mysqli_query(
    $conn,
    "SELECT * FROM reimbursement_types ORDER BY id ASC"
);

while($row = mysqli_fetch_assoc($typeQuery)){
    $fields = [];
    $fieldQuery = mysqli_query(
        $conn,
        "SELECT * FROM reimbursement_fields
        WHERE reimbursement_type_id='".$row['id']."'"
    );

    while($field = mysqli_fetch_assoc($fieldQuery)){
        $fields[] = $field;
    }

    $row['fields'] = $fields;
    $types[] = $row;
}

$firstType = !empty($types) ? $types[0] : null;

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">


<style>
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

/* Styled tab buttons to look like links */
.tab-btn {
    font-size: 13px;
    color: #6B7280;
    text-decoration: none;
    transition: color 0.15s, border-bottom-color 0.15s;
    background: none;
    border: none;
    border-bottom: 2px solid transparent; /* Creates the invisible line for layout stability */
    cursor: pointer;
    padding: 0 0 4px 0; /* Adds spacing between the text and the line */
    font-family: inherit;
}

.tab-btn:hover, .tab-btn.active {
    color: #2563EB;
    border-bottom-color: #2563EB; /* Changes the line color to blue when active or hovered */
    font-weight: 600;
}

.payroll-top-links .separator {
    color: #D1D5DB;
    font-size: 14px;
    margin-bottom: 4px; /* Aligns the separator slightly upwards to match the padded text */
}

/* ── Page wrapper card ── */
.payroll-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    padding: 20px 0;
    
    /* Scrollbar Settings */
    max-height: 75vh;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Custom Scrollbar Styling */
.payroll-card::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.payroll-card::-webkit-scrollbar-track {
    background: #f1f3f4; 
    border-radius: 8px;
}
.payroll-card::-webkit-scrollbar-thumb {
    background: #dadce0; 
    border-radius: 8px;
}
.payroll-card::-webkit-scrollbar-thumb:hover {
    background: #bdc1c6; 
}

/* ── Grid ── */
.cfg-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    padding: 10px 20px;
}

/* ── Config item ── */
.cfg-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 15px 10px;
    cursor: pointer;
    text-decoration: none;
    border-radius: 8px;
    transition: background 0.15s, transform 0.1s;
}

.cfg-item:hover {
    background: #F9FAFB;
}

.cfg-item:hover .cfg-item-title {
    color: #2563EB;
}

/* ── Config icon ── */
.cfg-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #F3F6FF;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid #E5E7EB;
    transition: background 0.15s, border-color 0.15s;
}

.cfg-item:hover .cfg-icon {
    background: #EBF0FF;
    border-color: #BFDBFE;
}

.cfg-icon svg {
    width: 22px;
    height: 22px;
    stroke: #4B5563;
    fill: none;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: stroke 0.15s;
}

.cfg-item:hover .cfg-icon svg { stroke: #2563EB; }

.cfg-item-title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 6px;
    line-height: 1.3;
    transition: color 0.15s;
}

.cfg-item-desc {
    font-size: 12px;
    color: #6B7280;
    line-height: 1.5;
}

/* Actions Row for Report History button */
.reports-actions-row {
    display: flex;
    justify-content: flex-end;
    padding: 0 20px 15px 20px;
}
.btn-report-history {
    display: flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    border: 1px solid #2563EB;
    color: #2563EB;
    padding: 6px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.15s;
}
.btn-report-history:hover {
    background: #F3F6FF;
}

/* Tab Content Logic */
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* ── Responsive ── */
@media (max-width: 1100px) {
    .cfg-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .cfg-grid { grid-template-columns: repeat(2, 1fr); }
    .payroll-header-wrapper { flex-direction: column; align-items: flex-start; }
}

@media (max-width: 480px) {
    .cfg-grid { grid-template-columns: 1fr; }
    .payroll-top-links { overflow-x: auto; width: 100%; padding-bottom: 5px; }
}
</style>


<?php
$report_icon = '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';
?>

<div>
    <!-- Header & Tabs -->
    <div class="payroll-header-wrapper">
        <h1 class="page-title">Reports</h1>
        <div class="payroll-top-links">
            <button class="tab-btn active" onclick="openTab(event, 'time-attendance')">Time and Attendance</button> <span class="separator">|</span>
            <button class="tab-btn" onclick="openTab(event, 'leaves')">Leaves</button> <span class="separator">|</span>
            <button class="tab-btn" onclick="openTab(event, 'payroll')">Payroll</button> <span class="separator">|</span>
            <button class="tab-btn" onclick="openTab(event, 'taxes')">Taxes</button> <span class="separator">|</span>
            <button class="tab-btn" onclick="openTab(event, 'others')">Others</button>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="payroll-card">
        <div class="reports-actions-row">
            <button class="btn-report-history">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                    <path d="M3 3v5h5"></path>
                    <path d="M12 7v5l4 2"></path>
                </svg>
                Report History
            </button>
        </div>

        <!-- 1. TIME AND ATTENDANCE TAB -->
        <div id="time-attendance" class="tab-content active">
            <div class="cfg-grid">
                <a href="daystatus" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Day Status</div><div class="cfg-item-desc">Monthly day mark report. Eg. Leave, Week off, Holiday, etc.</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">In/Out</div><div class="cfg-item-desc">Check In/out times and total hrs</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Attendance Summary</div><div class="cfg-item-desc">Customised report from time card summary</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Missing Punches</div><div class="cfg-item-desc">Summary of missed Check In/ Out for the selected</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Time Card</div><div class="cfg-item-desc">Day mark, shift, In/out, total hrs, OT, late Early, short.</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Punches IP</div><div class="cfg-item-desc">Web check In/ out and their IP addresses</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Late In/ Early Out</div><div class="cfg-item-desc">No. of late and early instances and hrs.</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Device Logs</div><div class="cfg-item-desc">Attendance logs from mobile app and biometric device</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Shift Assigned</div><div class="cfg-item-desc">List of shifts assigned to employees including no. of days of the shift</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Time Sheet</div><div class="cfg-item-desc">Time report generated in Payroll process</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Hours Worked Weekly</div><div class="cfg-item-desc">Cumulative weekly hours worked</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Hours Worked Monthly</div><div class="cfg-item-desc">Cumulative monthly hours worked</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Time Card Summary</div><div class="cfg-item-desc">Summary of Attendance, Leaves, Hours worked, Early/Late, Short hours, Over Time, etc</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Clock In</div><div class="cfg-item-desc">Attendance during the selected period</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Verify Mobile Attendance</div><div class="cfg-item-desc">Employee attendance, selfies, and locations recorded on Perk Mobile App</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Track Mobile In/Out</div><div class="cfg-item-desc">View multiple check In/Out</div></div></a>
            </div>
        </div>

        <!-- 2. LEAVES TAB -->
        <div id="leaves" class="tab-content">
            <div class="cfg-grid">
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Leave Requests</div><div class="cfg-item-desc">List of Leave requests and their status</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Leave Balances</div><div class="cfg-item-desc">Summary of Employee Leave balance</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Leave Statement</div><div class="cfg-item-desc">Summary of used, accumulated leaves and Leave balance of employees for a given period</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Leave Summary</div><div class="cfg-item-desc">Yearly report of Opening & closing balance, Accumulated, Lapsed and Used Leaves</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Assign CompOffPolicy Report</div><div class="cfg-item-desc"></div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">CompOff Request Report</div><div class="cfg-item-desc"></div></div></a>
            </div>
        </div>

        <!-- 3. PAYROLL TAB -->
        <div id="payroll" class="tab-content">
            <div class="cfg-grid">
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Payslip</div><div class="cfg-item-desc">Detailed Pay/ Salary slip of Employees for any given month</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Salary Sheet</div><div class="cfg-item-desc">Consolidated monthly Salary/ Pay sheet through Organization/ Department/ Designation/ Group etc</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">YTD Salary Sheet</div><div class="cfg-item-desc">Year to till date financial year summary of Salary components of employees</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Salary Distribution file</div><div class="cfg-item-desc">File used to disburse salaries when Employer and Employees' banks are different</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Salary Summary</div><div class="cfg-item-desc">Summary of Salary Components grouped by location, Designation, Organization, Category, etc for a given month</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Year to Till Date</div><div class="cfg-item-desc">Year to till Date salary sheet report in detailed for a particular financial year</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Salary Structure</div><div class="cfg-item-desc">Detailed Bifurcation of Employee Salary</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Payslip Item Summary</div><div class="cfg-item-desc">Summary of Payslip items and CTC sorted by Location and department</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Advance Payment/ Deduction</div><div class="cfg-item-desc">Summary of Advance Payment/ Deduction for a Pay Period</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Salary Disbursement File</div><div class="cfg-item-desc">File used to disburse salaries when Employer and Employees' banks are the same</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Loan</div><div class="cfg-item-desc"></div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Hold Salary</div><div class="cfg-item-desc">List of Employee salaries on Hold/Released</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Payroll Variance Analysis</div><div class="cfg-item-desc">Comparison of previous to current month's Payroll data</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">CTC Reco Template</div><div class="cfg-item-desc">Comparison of CTC of the previous and current month</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Final Settlements</div><div class="cfg-item-desc">Summary of an Employee's loans, gratuity, leave encasement & last payslip</div></div></a>
            </div>
        </div>

        <!-- 4. TAXES TAB -->
        <div id="taxes" class="tab-content">
            <div class="cfg-grid">
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">PF Deduction</div><div class="cfg-item-desc">Detailed bifurcation of Employer and employee PF deduction and Admin charges</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">PF ECR Text File</div><div class="cfg-item-desc">Govt. authorised PF electronic challan receipt to upload on PF portal</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">ESI Deduction</div><div class="cfg-item-desc">Detailed ESI deduction of employee and employer</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">ESI Form IP Text File</div><div class="cfg-item-desc">Download ESI form IP text file format</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">ESI Form MC Text File</div><div class="cfg-item-desc">Govt. authorised file for ESI monthly contribution to upload on the portal</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">PT Deduction</div><div class="cfg-item-desc">Detailed professional tax deduction of different slabs according to states</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">PT Form V</div><div class="cfg-item-desc">NA</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">TDS Deduction</div><div class="cfg-item-desc">List view of TDS deduction with Employee PAN, Education cess, etc</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">IT Computation</div><div class="cfg-item-desc">Detailed Income Tax computation as per sections and chapters</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Form 130</div><div class="cfg-item-desc">TDS certificate issued by the employer to validate the financial deductions</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Tax Declaration</div><div class="cfg-item-desc">List of all tax-saving investments that employee commits to make in a year</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Form 24 Q</div><div class="cfg-item-desc">Indicates the total remuneration paid to the employee and the TDS deducted from the employee's salary</div></div></a>
            </div>
        </div>

        <!-- 5. OTHERS TAB -->
        <div id="others" class="tab-content">
            <div class="cfg-grid">
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Employee List</div><div class="cfg-item-desc">List view of employee profile, and master details. Etc</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Employee Statutory Report</div><div class="cfg-item-desc">View statutory compliance details of employees</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Employee Family Info Report</div><div class="cfg-item-desc">View family information details of employees</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">User List</div><div class="cfg-item-desc">List view of ESS username, Name and Role</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Probations Due</div><div class="cfg-item-desc">List of employees whose probation periods end</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Assets</div><div class="cfg-item-desc">List of all assets (laptop, mobiles, ID cards, warrant periods, etc)</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Custom Fields</div><div class="cfg-item-desc"></div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Reimbursement</div><div class="cfg-item-desc"></div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Geo Fencing</div><div class="cfg-item-desc"></div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Training</div><div class="cfg-item-desc"></div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Assigned Training</div><div class="cfg-item-desc"></div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Custom Reports</div><div class="cfg-item-desc">Reports with customized queries, columns, and formatting, designed to meet unique organizational data analysis requirements.</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">Resignation Request Report</div><div class="cfg-item-desc">View Resignation Request Report</div></div></a>
                <a href="#" class="cfg-item"><div class="cfg-icon"><?= $report_icon ?></div><div><div class="cfg-item-title">YTD Employee Summary Report</div><div class="cfg-item-desc">Year to till Date employee salary sheet summary report in detailed for a particular financial year</div></div></a>
            </div>
        </div>

    </div>
</div>

<script>
// JavaScript to handle the tab switching
function openTab(evt, tabName) {
    // Hide all elements with class="tab-content"
    var tabcontent = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove("active");
    }

    // Remove the class "active" from all elements with class="tab-btn"
    var tablinks = document.getElementsByClassName("tab-btn");
    for (var i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }

    // Show the current tab, and add an "active" class to the button that opened the tab
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}
</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';

echo $page_content;

include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>