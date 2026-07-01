<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/db_client.php';
require_once 'includes/config.php';

// ==========================================
// 1. AJAX HANDLERS (Must be at the very top)
// ==========================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // -- Fetch Employees based on Filters --
    if ($_POST['ajax_action'] == 'search_employees') {
        $keyword = mysqli_real_escape_string($conn, $_POST['keyword'] ?? '');
        $org     = mysqli_real_escape_string($conn, $_POST['org'] ?? '');
        $loc     = mysqli_real_escape_string($conn, $_POST['loc'] ?? '');
        $dept    = mysqli_real_escape_string($conn, $_POST['dept'] ?? '');
        $desig   = mysqli_real_escape_string($conn, $_POST['desig'] ?? '');
        $status  = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
        $group   = mysqli_real_escape_string($conn, $_POST['group'] ?? '');
        $subGroup = mysqli_real_escape_string($conn, $_POST['subGroup'] ?? '');
        
        // Base query
        $sql = "SELECT `employee_code`, `employee_name` FROM `employees` WHERE (`status` = 'Active' OR `status` = '1')";
        
        // Append filters dynamically
        if (!empty($keyword)) {
            $sql .= " AND (`employee_name` LIKE '%$keyword%' OR `employee_code` LIKE '%$keyword%')";
        }
        if (!empty($loc)) {
            $sql .= " AND `location` = '$loc'";
        }
        if (!empty($dept)) {
            $sql .= " AND `department` = '$dept'";
        }
        if (!empty($desig)) {
            $sql .= " AND `designation` = '$desig'";
        }
        if (!empty($status)) {
            $sql .= " AND `status` = '$status'";
        }
        if (!empty($group)) {
            $sql .= " AND `grade` = '$group'"; // maps to structural configuration column
        }

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

    // -- Save Search to Database & Return Updated History Lists --
    if ($_POST['ajax_action'] == 'save_search') {
        $type = mysqli_real_escape_string($conn, $_POST['type']); // 'recent' or 'saved'
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $data = mysqli_real_escape_string($conn, $_POST['data']); // JSON string of filter inputs
        
        if ($type == 'recent') {
            $count_q = @mysqli_query($conn, "SELECT id FROM user_searches WHERE search_type='recent' ORDER BY id DESC LIMIT 4, 1");
            if ($count_q && mysqli_num_rows($count_q) > 0) {
                $fifth_id = mysqli_fetch_assoc($count_q)['id'];
                @mysqli_query($conn, "DELETE FROM user_searches WHERE search_type='recent' AND id < $fifth_id");
            }
        }
        
        $insert_sql = "INSERT INTO user_searches (search_type, search_name, filter_data) VALUES ('$type', '$name', '$data')";
        @mysqli_query($conn, $insert_sql);
        
        // Compile updated arrays to send back to UI instantly without reload
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

    // -- Delete Search from Database & Return Updated History Lists --
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
// 2. FORM SUBMISSION (Hold/Release Salary)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'hold' && !empty($_POST['selected_employees'])) {
        $pay_month = mysqli_real_escape_string($conn, $_POST['pay_month']);
        $remarks   = mysqli_real_escape_string($conn, $_POST['remarks']);
        
        foreach ($_POST['selected_employees'] as $emp_code) {
            $emp_code = mysqli_real_escape_string($conn, $emp_code);
            $name_res = @mysqli_query($conn, "SELECT employee_name FROM employees WHERE employee_code='$emp_code'");
            $emp_name = ($name_res && mysqli_num_rows($name_res) > 0) ? mysqli_fetch_assoc($name_res)['employee_name'] : '';
            
            $insert = "INSERT INTO held_salaries (employee_code, employee_name, pay_month, remarks, status) VALUES ('$emp_code', '$emp_name', '$pay_month', '$remarks', 'held')";
            @mysqli_query($conn, $insert);
        }
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Salaries successfully put on hold.'];

    } 
    elseif ($_POST['action'] == 'release' && isset($_POST['record_id'])) {
        $record_id = (int)$_POST['record_id'];
        $update = "UPDATE held_salaries SET status='released', released_on=NOW() WHERE id=$record_id";
        @mysqli_query($conn, $update);
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Salary released successfully.'];
    }
    // header("Location: " . $_SERVER['PHP_SELF']);
    ?>
<script>
    window.location.href = window.location.href; // Refresh the page to reflect changes";
</script>

<?php
    exit();
}

$page_title = 'Payroll - Hold/Release Salary';

// ==========================================
// 3. FETCH DATA FOR UI RENDER
// ==========================================
$employees = [];
$emp_sql = "SELECT `employee_code`, `employee_name` FROM `employees` WHERE `status` = 'Active' OR `status` = 1"; 
$emp_result = @mysqli_query($conn, $emp_sql);
if ($emp_result && mysqli_num_rows($emp_result) > 0) {
    while ($row = mysqli_fetch_assoc($emp_result)) { $employees[] = $row; }
}

$held_salaries_sql = "SELECT * FROM `held_salaries` WHERE `status` = 'held' ORDER BY `id` DESC";
$held_salaries_result = @mysqli_query($conn, $held_salaries_sql);

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

$organizations = [];
$org_result = @mysqli_query($conn, "SELECT `id`, `client_name` FROM `companies` WHERE `status` = 'Active' OR `status` = 1");
if ($org_result) { while ($row = mysqli_fetch_assoc($org_result)) { $organizations[] = $row; } }

$locations = [];
$loc_result = @mysqli_query($conn, "SELECT `id`, `location_name` FROM `org_locations` WHERE `status` = 'Active' OR `status` = 1");
if ($loc_result) { while ($row = mysqli_fetch_assoc($loc_result)) { $locations[] = $row; } }

$departments = [];
$dept_result = @mysqli_query($conn, "SELECT `id`, `dept_name` FROM `org_departments` WHERE `status` = 'Active' OR `status` = 1");
if ($dept_result) { while ($row = mysqli_fetch_assoc($dept_result)) { $departments[] = $row; } }

// Fetch Designations (Checking standard names: designations or org_designations)
$designations = [];
$desig_result = @mysqli_query($conn, "SELECT `id`, desig_name as `designation_name` FROM `designations` WHERE `status` = 'Active' OR `status` = 1");
if (!$desig_result) { 
    $desig_result = @mysqli_query($conn, "SELECT `id`, desig_name as `designation_name` FROM `org_designations` WHERE `status` = 'Active' OR `status` = 1"); 
}
if ($desig_result) { while ($row = mysqli_fetch_assoc($desig_result)) { $designations[] = $row; } }

$groups = [];
$group_result = @mysqli_query($conn, "SELECT `id`, `group_name` FROM `org_groups` WHERE `status` = 'Active' OR `status` = 1");
if ($group_result) { while ($row = mysqli_fetch_assoc($group_result)) { $groups[] = $row; } }

$sub_groups = [];
$sub_group_result = @mysqli_query($conn, "SELECT `id`, `sub_group_name` FROM `org_sub_groups` WHERE `status` = 'Active' OR `status` = 1");
if ($sub_group_result) { while ($row = mysqli_fetch_assoc($sub_group_result)) { $sub_groups[] = $row; } }

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
.inner-tab.active { color: #2563EB; border-bottom-color: #2563EB; font-weight: 600; }
.section-heading { font-size: 12px; font-weight: 700; color: #111827; margin-bottom: 12px; text-transform: uppercase; margin-top: 25px; }
.search-filter-row { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; max-width: 500px; }
.search-line-wrapper { position: relative; flex: 1; }
.search-line-wrapper svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: #9CA3AF; fill: none; stroke-width: 2; }
.search-line-wrapper input { width: 100%; padding: 8px 10px 8px 32px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 14px; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
.search-line-wrapper input:focus { border-color: #2563EB; }
.btn-filters { display: flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #D1D5DB; color: #4B5563; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; height: 36px; }
.btn-filters:hover { background: #F9FAFB; border-color: #9CA3AF; }
.selected-employee-box { border: 1px solid #D1D5DB; border-radius: 4px; padding: 15px; max-width: 800px; min-height: 50px; display: flex; flex-wrap: wrap; gap: 15px; }
.pay-period-row { display: flex; gap: 40px; align-items: flex-end; max-width: 600px; margin-bottom: 30px; }
.form-group { flex: 1; }
.form-group label { display: block; font-size: 12px; color: #4B5563; margin-bottom: 8px; }
.line-input { width: 100%; padding: 8px 0; border: none; border-bottom: 1px solid #D1D5DB; font-size: 14px; color: #111827; background: transparent; outline: none; transition: border-color 0.2s; }
.line-input::placeholder { color: #9CA3AF; }
.line-input:focus { border-bottom-color: #2563EB; }
select.line-input { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 5l3 3 3-3'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right center; padding-right: 20px; }
.form-actions { display: flex; justify-content: flex-end; margin-top: 20px; gap: 10px; }
.btn-primary { background: #2563EB; color: #fff; border: none; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #0052cc; }
.btn-outline { background: #fff; color: #4B5563; border: 1px solid #D1D5DB; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
.btn-outline:hover { background: #F3F4F6; }
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
.data-table th, .data-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #E5E7EB; font-size: 14px; }
.data-table th { background-color: #F9FAFB; color: #4B5563; font-weight: 600; }
.btn-success { background: #10B981; color: #fff; border: none; padding: 6px 16px; border-radius: 4px; font-size: 13px; cursor: pointer; }
.checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #111827; cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #2563EB; margin: 0; }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; box-sizing: border-box; }
.modal-content { background: #fff; width: 100%; max-width: 900px; max-height: 90vh; border-radius: 8px; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #E5E7EB; }
.modal-header h2 { margin: 0; font-size: 16px; font-weight: 600; color: #111827; }
.modal-close { background: none; border: 1px solid #D1D5DB; font-size: 20px; cursor: pointer; color: #6B7280; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
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
.recent-tab.active { color: #2563EB; border-bottom-color: #2563EB; font-weight: 500; }
.recent-list { list-style: none; padding: 0; margin: 0; }
.recent-list li { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #4B5563; padding: 8px 0; border-bottom: 1px dashed #E5E7EB; cursor: pointer; }
.recent-list li:hover { background: #F9FAFB; }
.recent-list li button { background: none; border: 1px solid #D1D5DB; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; color: #EF4444; }
.modal-footer { padding: 16px 24px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px; background: #F9FAFB; }

/* Custom Toast Alerts CSS */
.toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
.toast { min-width: 250px; background: #fff; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-left: 4px solid #2563EB; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; font-size: 14px; color: #111827; transform: translateX(110%); opacity: 0; transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55); }
.toast.show { transform: translateX(0); opacity: 1; }
.toast.success { border-left-color: #10B981; }
.toast.error { border-left-color: #EF4444; }
.toast-close-btn { background: none; border: none; font-size: 18px; cursor: pointer; color: #9CA3AF; display: flex; align-items: center; }
.toast-close-btn:hover { color: #4B5563; }
</style>

<div class="toast-container" id="toastContainer"></div>

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
        <a href="HoldSalary" class="payroll-tab active">Hold Salary</a> <span class="separator">|</span>
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
        <div class="breadcrumb"><strong>Payroll</strong> &nbsp;&gt;&nbsp; Hold/ Release Salary</div>
    </div>

    <div class="inner-tabs">
        <span class="inner-tab active" id="tabHold" onclick="switchTab('hold')">Hold</span>
        <span class="inner-tab" id="tabRelease" onclick="switchTab('release')">Release</span>
    </div>

    <div id="sectionHold" style="display: block;">
        <form action="" method="POST" id="holdSalaryForm">
            <div class="section-heading">Select Employees to Hold Salary</div>
            <div class="search-filter-row">
                <div class="search-line-wrapper">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="mainEmpSearch" list="employeeList" placeholder="Search by name or #code" autocomplete="off" onchange="addSingleEmployeeFromSearch()">
                </div>
                <button type="button" class="btn-filters" onclick="openFilterModal()">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    Filters
                </button>
            </div>

            <div class="selected-employee-box" id="mainSelectedEmployeesBox">
                <span style="color: #9CA3AF; font-size: 13px; align-self: center;" id="emptySelectionText">No employees selected. Use search or filters to add.</span>
            </div>

            <div class="section-heading">Select Pay Period</div>
            <div class="pay-period-row">
                <div class="form-group">
                    <label>Month</label>
                    <select name="pay_month" class="line-input" required>
                        <?php
                        for ($i = -2; $i <= 2; $i++) {
                            $timestamp = strtotime("$i month");
                            $value = date('M-Y', $timestamp);
                            $selected = ($i == 0) ? 'selected' : '';

                            echo "<option value='$value' $selected>$value</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="line-input" placeholder="Write your Remarks for holding the Salary">
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
                    <tr><th>Employee Name</th><th>Code</th><th>Pay Month</th><th>Remarks</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($held_salaries_result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                        <td><?= htmlspecialchars($row['employee_code']) ?></td>
                        <td><?= htmlspecialchars($row['pay_month']) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td>
                            <form action="" method="POST" style="margin:0;">
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
            <svg class="empty-state-svg" viewBox="0 0 100 100" fill="none"><circle cx="50" cy="50" r="40" fill="#EEF2FF" /><rect x="35" y="30" width="30" height="40" rx="2" fill="#fff" stroke="#D1D5DB" stroke-width="2" /><path d="M35 34C35 32.8954 35.8954 32 37 32H63C64.1046 32 65 32.8954 65 34V42H35V34Z" fill="#4B5563" /><rect x="40" y="35" width="12" height="2" fill="#9CA3AF" /><rect x="40" y="48" width="14" height="2" fill="#2563EB" /><rect x="40" y="52" width="20" height="2" fill="#E5E7EB" /><rect x="40" y="58" width="14" height="2" fill="#2563EB" /><rect x="40" y="62" width="10" height="2" fill="#E5E7EB" /></svg>
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
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="modalSearchInput" placeholder="Search by name or #code" style="border-radius: 4px; border: 1px solid #D1D5DB; padding-left: 35px;">
            </div>

            <div class="modal-filter-grid">
                <div class="form-group"><label>Organization</label><select id="filterOrg" class="line-input"><option value="">Select Organization</option><?php foreach($organizations as $org): ?><option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['client_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Locations</label><select id="filterLoc" class="line-input"><option value="">Select Location</option><?php foreach($locations as $loc): ?><option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Department</label><select id="filterDept" class="line-input"><option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['dept_name']) ?></option><?php endforeach; ?></select></div>
                
                <div class="form-group">
                    <label>Designation</label>
                    <select id="filterDesig" class="line-input">
                        <option value="">Select Designation</option>
                        <?php foreach($designations as $desig): ?>
                            <option value="<?= $desig['id'] ?>"><?= htmlspecialchars($desig['designation_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group"><label>Status</label><select id="filterStatus" class="line-input"><option value="">Select Status</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                <div class="form-group"><label>Group</label><select id="filterGroup" class="line-input"><option value="">Select Group</option><?php foreach($groups as $grp): ?><option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Sub Group</label><select id="filterSubGroup" class="line-input"><option value="">Select Sub Group</option><?php foreach($sub_groups as $sgrp): ?><option value="<?= $sgrp['id'] ?>"><?= htmlspecialchars($sgrp['sub_group_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Category</label><select id="filterCat" class="line-input"><option value="">Select Category</option></select></div>
                <div class="form-group"><label>Grade</label><select id="filterGrade" class="line-input"><option value="">Select Grade</option></select></div>
                <div class="form-group"><label>Additional Field</label><select id="filterAddField" class="line-input"><option value="">Select Field</option></select></div>
                <div class="form-group"><label>Field Value</label><select id="filterAddVal" class="line-input"><option value="">Select Value</option></select></div>
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
// Custom Toast Trigger Function
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span>${message}</span>
        <button class="toast-close-btn" onclick="this.parentElement.remove()">&times;</button>
    `;
    container.appendChild(toast);

    // Trigger reflow to animate
    void toast.offsetWidth;
    toast.classList.add('show');

    // Auto remove after 3.5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// PHP Session Toast Alert Interceptor
<?php if(isset($_SESSION['toast'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    showToast(<?= json_encode($_SESSION['toast']['msg']) ?>, <?= json_encode($_SESSION['toast']['type']) ?>);
});
<?php unset($_SESSION['toast']); endif; ?>


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

function renderSidebarLists() {
    const rList = document.getElementById('recentSearchList');
    const sList = document.getElementById('savedSearchList');
    
    rList.innerHTML = recentSearches.length === 0 ? '<li style="justify-content:center; color:#9CA3AF;">No recent searches</li>' : '';
    recentSearches.forEach(search => {
        let sd = JSON.stringify(search.filter_data).replace(/'/g, "&apos;");
        rList.innerHTML += `<li onclick='applySearchState(${sd})'><span>${search.search_name || "Filtered Search"}</span><button onclick="deleteSearchItem(${search.id}, event)" title="Remove">&minus;</button></li>`;
    });

    sList.innerHTML = savedSearches.length === 0 ? '<li style="justify-content:center; color:#9CA3AF;">No saved searches</li>' : '';
    savedSearches.forEach(search => {
        let sd = JSON.stringify(search.filter_data).replace(/'/g, "&apos;");
        sList.innerHTML += `<li onclick='applySearchState(${sd})'><span>${search.search_name}</span><button onclick="deleteSearchItem(${search.id}, event)" title="Remove">&minus;</button></li>`;
    });
}

function applySearchState(data) {
    document.getElementById('modalSearchInput').value = data.keyword || '';
    document.getElementById('filterOrg').value = data.org || '';
    document.getElementById('filterLoc').value = data.loc || '';
    document.getElementById('filterDept').value = data.dept || '';
    document.getElementById('filterDesig').value = data.desig || ''; 
    document.getElementById('filterStatus').value = data.status || '';
    document.getElementById('filterGroup').value = data.group || '';
    document.getElementById('filterSubGroup').value = data.subGroup || '';
    performModalSearch();
}

function captureSearchState() {
    return {
        keyword: document.getElementById('modalSearchInput').value.trim(),
        org: document.getElementById('filterOrg').value,
        loc: document.getElementById('filterLoc').value,
        dept: document.getElementById('filterDept').value,
        desig: document.getElementById('filterDesig').value, 
        status: document.getElementById('filterStatus').value,
        group: document.getElementById('filterGroup').value,
        subGroup: document.getElementById('filterSubGroup').value
    };
}

// ── ADVANCE EMPLOYEES SEARCH & AJAX ENG_FIX ──
async function performModalSearch() {
    const searchData = captureSearchState();
    
    // 1. Run dynamic search fetch across the DB
    const searchForm = new FormData();
    searchForm.append('ajax_action', 'search_employees');
    for (let key in searchData) { searchForm.append(key, searchData[key]); }

    const response = await fetch(window.location.href, { method: 'POST', body: searchForm });
    const employees = await response.json();

    const grid = document.getElementById('modalEmpGrid');
    document.getElementById('empFoundCount').innerText = employees.length;
    document.getElementById('selectAllModalEmp').checked = false;
    grid.innerHTML = '';
    
    if (employees.length === 0) {
        grid.innerHTML = '<span style="font-size: 13px; color: #9CA3AF;">No matching employees found.</span>';
    } else {
        employees.forEach(emp => {
            grid.innerHTML += `<label class="checkbox-label"><input type="checkbox" class="modal-emp-checkbox" value="${emp.id}" data-name="${emp.name}"> ${emp.name} - ${emp.id}</label>`;
        });
    }

    // 2. Process asynchronous history log directly without refreshing page layout
    let label = searchData.keyword ? searchData.keyword : "Filtered Search";
    const historyForm = new FormData();
    historyForm.append('ajax_action', 'save_search');
    historyForm.append('type', 'recent');
    historyForm.append('name', label);
    historyForm.append('data', JSON.stringify(searchData));

    fetch(window.location.href, { method: 'POST', body: historyForm })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                recentSearches = res.recent;
                savedSearches = res.saved;
                renderSidebarLists();
            }
        });
}

function saveCurrentSearch() {
    const searchData = captureSearchState();
    const name = prompt("Enter a name to save this search filter:", "My Saved Search");
    if(name) {
        const sfData = new FormData();
        sfData.append('ajax_action', 'save_search');
        sfData.append('type', 'saved');
        sfData.append('name', name);
        sfData.append('data', JSON.stringify(searchData));
        
        fetch(window.location.href, { method: 'POST', body: sfData })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success'){
                    recentSearches = res.recent;
                    savedSearches = res.saved;
                    renderSidebarLists();
                    switchSidebarTab('saved');
                    showToast('Search criteria saved successfully!', 'success');
                }
            });
    }
}
function deleteSearchItem(id, event) {
    event.stopPropagation();

    Swal.fire({
        title: 'Delete this search?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_search');
            formData.append('id', id);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    recentSearches = res.recent;
                    savedSearches = res.saved;
                    renderSidebarLists();
                    showToast('Search item deleted successfully', 'success');
                } else {
                    showToast('Failed to delete the search item.', 'error');
                }
            })
            .catch(() => {
                showToast('Something went wrong during deletion.', 'error');
            });
        }
    });
}

function openFilterModal() {
    document.getElementById('filterModal').style.display = 'flex';
    renderSidebarLists();
}
function closeFilterModal() {
    document.getElementById('filterModal').style.display = 'none';
}

// ── SELECTION MANAGER ──
let selectedEmployees = [];

function addSingleEmployeeFromSearch() {
    const input = document.getElementById('mainEmpSearch');
    const val = input.value.trim();
    if (val) {
        const match = val.match(/(.+) \(#(.+)\)/);
        if (match) { addEmployeeToSelection(match[2].trim(), match[1].trim()); }
        input.value = ''; 
    }
}

function toggleAllModalEmp(source) {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox');
    checkboxes.forEach(cb => { cb.checked = source.checked; });
}

function clearModalSelections() {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox');
    checkboxes.forEach(cb => { cb.checked = false; });
    document.getElementById('selectAllModalEmp').checked = false;
}

function applyModalFilters() {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox:checked');
    checkboxes.forEach(cb => { addEmployeeToSelection(cb.value, cb.getAttribute('data-name')); });
    closeFilterModal();
}

function addEmployeeToSelection(id, name) {
    if (!selectedEmployees.find(e => e.id === id)) {
        selectedEmployees.push({ id, name });
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
        box.innerHTML = '<span style="color: #9CA3AF; font-size: 13px; align-self: center;" id="emptySelectionText">No employees selected. Use search or filters to add.</span>';
        return;
    }
    box.innerHTML = '';
    selectedEmployees.forEach(emp => {
        box.innerHTML += `<label class="checkbox-label" style="background: #F3F4F6; padding: 6px 12px; border-radius: 4px; border: 1px solid #E5E7EB;"><input type="checkbox" name="selected_employees[]" value="${emp.id}" checked onclick="removeEmployee('${emp.id}')" style="accent-color: #EF4444;"> ${emp.name} - ${emp.id}</label>`;
    });
}
</script>
<script src="includes/assets/scripts.js"></script>