<?php
session_start();
if (!isset($_SESSION['login'])) {
    if (isset($_POST['action']) || isset($_GET['action']) || isset($_GET['ajax_search']) || isset($_GET['ajax_advanced_search'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login');
    exit();
}

// ==========================================
// 1. AJAX HANDLERS
// ==========================================

// Handle AJAX Request for Quick Autocomplete Search
if (isset($_GET['ajax_search'])) {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    
    header('Content-Type: application/json');
    $results = [];
    
    if (isset($conn)) {
        $search = mysqli_real_escape_string($conn, trim($_GET['ajax_search']));
        if (!empty($search)) {
            $sql = "SELECT employee_code, employee_name FROM employees 
                    WHERE employee_name LIKE '%$search%' 
                    OR employee_code LIKE '%$search%' 
                    LIMIT 10";
            $res = mysqli_query($conn, $sql);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $results[] = $row;
                }
            }
        }
    }
    echo json_encode($results);
    exit;
}

// Handle AJAX Request for Advanced Modal Search
if (isset($_GET['ajax_advanced_search'])) {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    
    header('Content-Type: application/json');
    $results = [];
    
    if (isset($conn)) {
        $sql = "SELECT id, employee_code, employee_name FROM employees WHERE 1=1";
        
        if (!empty($_GET['status'])) {
            $status = mysqli_real_escape_string($conn, $_GET['status']);
            if ($status === 'Active') {
                $sql .= " AND (status = 'Active' OR status = 1)";
            } else if ($status === 'Inactive') {
                $sql .= " AND (status = 'Inactive' OR status = 0)";
            }
        } else {
            $sql .= " AND (status = 'Active' OR status = 1)";
        }

        if (!empty($_GET['name_code'])) {
            $search = mysqli_real_escape_string($conn, trim($_GET['name_code']));
            $sql .= " AND (employee_name LIKE '%$search%' OR employee_code LIKE '%$search%')";
        }
        if (!empty($_GET['org'])) {
            $org = mysqli_real_escape_string($conn, $_GET['org']);
            $sql .= " AND company_id = '$org'"; 
        }
        if (!empty($_GET['loc'])) {
            $loc = mysqli_real_escape_string($conn, $_GET['loc']);
            $sql .= " AND location_id = '$loc'";
        }
        if (!empty($_GET['dept'])) {
            $dept = mysqli_real_escape_string($conn, $_GET['dept']);
            $sql .= " AND department_id = '$dept'";
        }
        if (!empty($_GET['desig'])) {
            $desig = mysqli_real_escape_string($conn, $_GET['desig']);
            $sql .= " AND designation_id = '$desig'";
        }
        if (!empty($_GET['group'])) {
            $group = mysqli_real_escape_string($conn, $_GET['group']);
            $sql .= " AND group_id = '$group'";
        }
        if (!empty($_GET['sub_group'])) {
            $sub_group = mysqli_real_escape_string($conn, $_GET['sub_group']);
            $sql .= " AND sub_group_id = '$sub_group'";
        }
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $sql .= " LIMIT $limit";
        
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $results[] = $row;
            }
        }
    }
    echo json_encode($results);
    exit;
}

// Handle AJAX Request for Processing Time Card
if (isset($_POST['action']) && $_POST['action'] === 'process_timecard') {
    require_once 'includes/config.php';
    require_once 'includes/db_client.php';
    header('Content-Type: application/json');

    if (isset($conn)) {
        $date_range = $_POST['date_range'] ?? '';
        $emp_code_string = $_POST['emp_code'] ?? 'all';
        $overwrite = $_POST['overwrite'] === 'true' ? true : false;
        
        // Simulate Processing Delay
        sleep(1); 

        echo json_encode(['success' => true, 'message' => 'Time card processed successfully.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    }
    exit;
}

// ==========================================
// 2. MAIN PAGE LOGIC & FILTERING
// ==========================================
require_once 'includes/config.php';
require_once 'includes/db_client.php';

$page_title = 'Process Time Card';

// Capture comma-separated employees
$search_query = isset($_GET['employee']) ? trim($_GET['employee']) : '';
$is_searched = !empty($search_query);
$selected_employees = [];

// Determine Initial Date Range (Default to This Month)
$start_date = date('Y-m-01');
$end_date = date('Y-m-t'); 
$date_range_display = date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));

if ($is_searched && isset($conn)) {
    // Break comma separated values into an array
    $codes = array_map('trim', explode(',', $search_query));
    $safe_codes = [];
    
    foreach ($codes as $c) {
        if (!empty($c)) {
            $safe_codes[] = "'" . mysqli_real_escape_string($conn, $c) . "'";
        }
    }
    
    if (count($safe_codes) > 0) {
        $in_clause = implode(',', $safe_codes);
        $emp_sql = "SELECT * FROM employees WHERE employee_code IN ($in_clause)";
        $emp_result = mysqli_query($conn, $emp_sql);
        
        if ($emp_result) {
            while ($row = mysqli_fetch_assoc($emp_result)) {
                $selected_employees[] = $row;
            }
        }
    }
}

// ==========================================
// 3. FETCH DATA FOR UI RENDER (Modal Data)
// ==========================================
$organizations = [];
$org_result = @mysqli_query($conn, "SELECT `id`, `client_name` FROM `companies` WHERE `status` = 'Active' OR `status` = 1");
if ($org_result) { while ($row = mysqli_fetch_assoc($org_result)) { $organizations[] = $row; } }

$locations = [];
$loc_result = @mysqli_query($conn, "SELECT `id`, `location_name` FROM `org_locations` WHERE `status` = 'Active' OR `status` = 1");
if ($loc_result) { while ($row = mysqli_fetch_assoc($loc_result)) { $locations[] = $row; } }

$departments = [];
$dept_result = @mysqli_query($conn, "SELECT `id`, `dept_name` FROM `org_departments` WHERE `status` = 'Active' OR `status` = 1");
if ($dept_result) { while ($row = mysqli_fetch_assoc($dept_result)) { $departments[] = $row; } }

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

<!-- Include CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="includes/assets/style.css">
<style>
/* Common Utility Classes */
.flex-between { display: flex; justify-content: space-between; align-items: center; }
.text-dark { color: #111827; }
.text-muted { color: #6b7280; }
.fw-bold { font-weight: 600; }
.shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
.card { background: #fff; border-radius: 8px; min-height: 450px; }

/* Tabs */
.attendance-tabs { border-bottom: 1px solid #dee2e6; }
.attendance-tabs a { color: #6c757d; text-decoration: none; padding: 3px 5px; display: inline-block; font-size: 14px; transition: color 0.2s; border-bottom: 2px solid transparent; }
.attendance-tabs .separator { color: #D1D5DB; font-size: 14px; margin: 0 -5px; }
.attendance-tabs a:hover { color: #495057; }
.attendance-tabs a.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; font-weight: 500; }

/* Form Controls & Layout */
.process-container { display: flex; flex-direction: column; gap: 20px; padding-top: 10px; }
.filter-bar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

.search-wrapper { position: relative; width: 300px; }
.search-wrapper .form-control { padding-left: 35px; border-radius: 4px; height: 38px; border: 1px solid #d1d5db; width: 100%; outline: none; }
.search-wrapper .form-control:focus { border-color: #0d6efd; }
.search-wrapper .bi-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px; }

.btn-outline-secondary { border-color: #d1d5db; color: #4b5563; background: #fff; border: 1px solid #d1d5db; border-radius: 4px; padding: 0 16px; height: 38px; display: inline-flex; align-items: center; cursor: pointer; }
.btn-outline-secondary:hover { background: #f3f4f6; color: #111827; }

/* Custom Date Dropdown */
.custom-date-select { position: relative; width: 280px; }
.date-dropdown { display: flex; align-items: center; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; height: 38px; padding: 0 12px; gap: 8px; cursor: pointer; user-select: none; }
.date-dropdown:hover { border-color: #9ca3af; }
.date-dropdown .bi-calendar { color: #6b7280; font-size: 15px; }
.date-dropdown span { font-size: 13.5px; color: #4b5563; flex: 1; }
.date-options-menu { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #d1d5db; border-radius: 4px; margin-top: 4px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 1000; display: none; overflow: hidden; padding: 8px 0; }
.date-options-menu.open { display: block; }
.date-option { padding: 10px 16px; font-size: 13.5px; color: #111827; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 8px; }
.date-option:hover { background: #f3f4f6; }

/* Autocomplete Menu */
.autocomplete-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #d1d5db; border-top: none; border-radius: 0 0 4px 4px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); z-index: 1000; max-height: 250px; overflow-y: auto; display: none; margin-top: 2px; }
.autocomplete-item { padding: 10px 14px; cursor: pointer; font-size: 13.5px; border-bottom: 1px solid #f3f4f6; display: flex; flex-direction: column; transition: background-color 0.1s; }
.autocomplete-item:hover { background-color: #eff6ff; }
.autocomplete-name { font-weight: 600; color: #111827; }
.autocomplete-code { font-size: 11px; color: #6b7280; margin-top: 2px; }

/* Multiple Employee Render CSS */
.employee-selection-card { margin-top: 5px; border: 1px solid #d1d5db; border-radius: 6px; padding: 16px; background-color: #f9fafb; width: 100%; max-height: 250px; overflow-y: auto; }
.grid-row { display: flex; flex-wrap: wrap; margin-left: -10px; margin-right: -10px; }
.grid-col-3 { width: 33.33%; padding: 0 10px; margin-bottom: 12px; }
.form-check { display: flex; align-items: center; gap: 10px; margin: 0; }
.form-check-input { width: 16px; height: 16px; cursor: pointer; margin: 0; }
.form-check-label { font-size: 13.5px; color: #111827; font-weight: 500; cursor: pointer; word-break: break-word; }

.process-action-bar { display: flex; justify-content: flex-end; margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 20px; }
.btn-primary { background: #0d6efd; color: #fff; border: none; border-radius: 4px; padding: 10px 30px; cursor: pointer; font-weight: 500; font-size: 14px; transition: 0.2s; }
.btn-primary:hover { background-color: #0b5ed7; }

/* Modal Styling */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1050; display: none; align-items: center; justify-content: center; }
.modal-content { background: #fff; width: 900px; max-width: 95%; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; max-height: 90vh; }
.modal-header { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { margin: 0; font-size: 18px; font-weight: 600; color: #111827; }
.modal-close { background: none; border: none; font-size: 24px; color: #6b7280; cursor: pointer; }
.modal-body { padding: 24px; overflow-y: auto; flex: 1; }
.search-line-wrapper svg { position: absolute; left: 10px; width: 16px; height: 16px; stroke: #9ca3af; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
.search-line-wrapper input { width: 100%; height: 40px; padding-left: 35px; border-radius: 4px; border: 1px solid #D1D5DB; }
.modal-filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
.form-group label { display: block; font-size: 12px; color: #4b5563; margin-bottom: 4px; }
.line-input { width: 100%; border: 1px solid #d1d5db; border-radius: 4px; height: 36px; padding: 0 10px; font-size: 13px; color: #111827; }
.modal-search-row { display: flex; justify-content: space-between; align-items: center; }
.modal-results-layout { display: flex; gap: 24px; }
.modal-emp-list-sec { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; display: flex; flex-direction: column; }
.modal-emp-header { padding: 12px 16px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #111827; font-weight: 500; }
.modal-emp-grid { flex: 1; min-height: 200px; max-height: 300px; overflow-y: auto; }
.modal-recent-sec { width: 300px; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; display: flex; flex-direction: column; }
.recent-tabs { display: flex; border-bottom: 1px solid #e5e7eb; }
.recent-tab { flex: 1; padding: 10px; text-align: center; font-size: 13px; font-weight: 500; color: #6b7280; cursor: pointer; background: #f9fafb; }
.recent-tab.active { background: #fff; color: #0d6efd; border-bottom: 2px solid #0d6efd; }
.recent-list { list-style: none; padding: 0; margin: 0; overflow-y: auto; flex: 1; max-height: 300px; padding: 15px; font-size: 13px; color: #6b7280; }
.modal-footer { padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
.btn-outline { border: 1px solid #d1d5db; background: #fff; color: #4b5563; padding: 8px 16px; border-radius: 4px; font-size: 13.5px; cursor: pointer; }
</style>

<div class="container">
    <div class="flex-between mb-1">
        <h4 class="text-dark fw-bold m-0" style="font-size: 1.25rem;">Attendance</h4>
        <div class="attendance-tabs m-0 border-0">
            <a href="attendance">Time Entries</a>
            <span class="separator">|</span>
            <a href="AttCalendarView">Calendar View</a>
            <span class="separator">|</span>
            <a href="ManualAttendance">Manual Attendance</a>
            <span class="separator">|</span>
            <a href="AttDiscrepancies">Discrepancies</a>
            <span class="separator">|</span>
            <a href="AttProcessTimeCard" class="active">Process Time Card</a>
            <span class="separator">|</span>
            <a href="AttApproveOvertime">Approve Overtime</a>
        </div>
    </div>

    <div class="card shadow-sm mt-2">
        <div class="card-body p-4">
            <h6 class="text-dark fw-bold mb-1" style="font-size: 14px; letter-spacing: 0.5px; margin-top: 0; text-transform: uppercase;">
                PROCESS TIME CARD
            </h6>
            <p class="text-muted mb-4" style="font-size: 13px;">Processing time card locks the employee attendance at the end of each pay period</p>

            <form method="GET" action="" class="process-container" id="filterForm" onsubmit="event.preventDefault();">
                <div class="filter-bar">
                    <!-- Search Input (ALWAYS REMAINS EMPTY VISUALLY) -->
                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text" id="employeeSearchInput" class="form-control" placeholder="Search by name or #code" autocomplete="off">
                        <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
                    </div>

                    <!-- Filter Button -->
                    <button type="button" class="btn-outline-secondary" onclick="openFilterModal()">
                        <i class="bi bi-funnel me-2"></i> Filter
                    </button>

                    <!-- Custom Date Dropdown -->
                    <div class="custom-date-select">
                        <div class="date-dropdown" onclick="toggleDateMenu(event)">
                            <i class="bi bi-calendar"></i>
                            <span id="dateDisplay"><?= htmlspecialchars($date_range_display) ?></span>
                            <i class="bi bi-chevron-down ms-auto text-muted" style="font-size:12px;"></i>
                        </div>
                        <div class="date-options-menu" id="dateOptionsMenu">
                            <div class="date-option" onclick="setDateRange('last_month')">LastMonth</div>
                            <div class="date-option" onclick="setDateRange('this_month')">ThisMonth</div>
                                <i class="bi bi-calendar"></i>
                                <input type="hidden" name="date_range" id="hiddenDateRange" value="Custom Dates">
                        </div>
                    </div>
                </div>

                <p class="text-muted" style="font-size: 13px; margin-bottom: 0;">
                    Note: All employees consist of only those employees to whose data you have access.
                </p>

                <!-- DYNAMIC EMPLOYEE SELECTION GRID CONTAINER -->
                <div id="dynamicEmployeeContainer">
                    <?php if (!empty($selected_employees)): ?>
                    <div class="employee-selection-card" id="employeeSelectionCard">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                            <span style="font-size: 14px; font-weight: 600; color: #374151;">Selected Employees (<span id="selectedCount"><?= count($selected_employees) ?></span>)</span>
                            <a href="javascript:void(0)" onclick="clearSelectedEmployees()" style="font-size: 13px; color: #ef4444; text-decoration: none;">Clear All</a>
                        </div>
                        <div class="grid-row" id="employeeGridRow">
                            <?php foreach ($selected_employees as $emp): ?>
                            <div class="grid-col-3" id="emp_col_<?= htmlspecialchars($emp['employee_code']) ?>">
                                <div class="form-check">
                                    <input class="form-check-input emp-process-cb" type="checkbox" checked id="emp_<?= htmlspecialchars($emp['employee_code']) ?>" value="<?= htmlspecialchars($emp['employee_code']) ?>" onchange="updateSelectedCount()">
                                    <label class="form-check-label" for="emp_<?= htmlspecialchars($emp['employee_code']) ?>">
                                        <?= htmlspecialchars($emp['employee_name']) ?> - <?= htmlspecialchars($emp['employee_code']) ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-check mt-3 mb-0">
                    <input class="form-check-input" type="checkbox" id="overwriteManual" name="overwrite_manual">
                    <label class="form-check-label" for="overwriteManual" style="font-weight: 400; color: #4b5563;">
                        Overwrite Manual Changes
                    </label>
                </div>

                <div class="process-action-bar">
                    <button type="button" class="btn-primary" id="btnProcessTimeCard">Process</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal HTML (Advanced Search) -->
<div id="filterModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Advance Employee Search</h2>
            <button type="button" class="modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="position: relative; margin-bottom: 25px;" class="search-line-wrapper">
                <svg viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="modalSearchInput" placeholder="Search by name or #code">
            </div>
            <div class="modal-filter-grid">
                <div class="form-group"><label>Organization</label><select id="filterOrg" class="line-input">
                        <option value="">Select Organization</option><?php foreach($organizations as $org): ?><option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['client_name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Locations</label><select id="filterLoc" class="line-input">
                        <option value="">Select Location</option><?php foreach($locations as $loc): ?><option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Department</label><select id="filterDept" class="line-input">
                        <option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['dept_name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Designation</label><select id="filterDesig" class="line-input">
                        <option value="">Select Designation</option><?php foreach($designations as $desig): ?><option value="<?= $desig['id'] ?>"><?= htmlspecialchars($desig['designation_name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Status</label><select id="filterStatus" class="line-input">
                        <option value="">Select Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select></div>
                <div class="form-group"><label>Group</label><select id="filterGroup" class="line-input">
                        <option value="">Select Group</option><?php foreach($groups as $grp): ?><option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Sub Group</label><select id="filterSubGroup" class="line-input">
                        <option value="">Select Sub Group</option><?php foreach($sub_groups as $sgrp): ?><option value="<?= $sgrp['id'] ?>"><?= htmlspecialchars($sgrp['sub_group_name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="modal-search-row">
                <span style="font-size: 13px; color: #4B5563;">Records per page : <select id="modalLimit" class="line-input" style="width: auto; display: inline-block;">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select></span>
                <button type="button" class="btn-primary" style="padding: 6px 20px;" onclick="performModalSearch()">Search</button>
            </div>
            <hr style="margin: 20px 0; border: none; border-top: 1px solid #E5E7EB;">
            <div class="modal-results-layout">
                <div class="modal-emp-list-sec">
                    <!-- Added Select All Checkbox -->
                    <div class="modal-emp-header d-flex align-items-center" style="gap: 10px;">
                        <input type="checkbox" id="modalSelectAll" class="form-check-input m-0" onchange="toggleModalSelectAll(this)" style="cursor: pointer;">
                        <label for="modalSelectAll" style="font-weight: 500; cursor: pointer; margin: 0;">Select All (<span id="empFoundCount">0</span> Found)</label>
                    </div>
                    <div class="modal-emp-grid" id="modalEmpGrid"><span style="padding: 15px; font-size: 13px; color: #9CA3AF; display:block;">Click search to find employees.</span></div>
                </div>
                <div class="modal-recent-sec">
                    <div class="recent-tabs">
                        <span class="recent-tab active" id="tabRecentSearch" onclick="switchSidebarTab('recent')">Recent Search</span>
                        <span class="recent-tab" id="tabSavedSearch" onclick="switchSidebarTab('saved')">Saved Search</span>
                    </div>
                    <ul class="recent-list" id="recentSearchList"><li>No recent searches</li></ul>
                    <ul class="recent-list" id="savedSearchList" style="display:none;"><li>No saved searches</li></ul>
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

<!-- JavaScript Logic -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ---------------------------------------------------------
// DOM Multi-Select Add Functions
// ---------------------------------------------------------
function addEmployeeToGrid(empCode, empName) {
    let container = document.getElementById('dynamicEmployeeContainer');
    let card = document.getElementById('employeeSelectionCard');
    
    // Create card if it doesn't exist
    if (!card) {
        container.innerHTML = `
        <div class="employee-selection-card" id="employeeSelectionCard">
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                <span style="font-size: 14px; font-weight: 600; color: #374151;">Selected Employees (<span id="selectedCount">0</span>)</span>
                <a href="javascript:void(0)" onclick="clearSelectedEmployees()" style="font-size: 13px; color: #ef4444; text-decoration: none;">Clear All</a>
            </div>
            <div class="grid-row" id="employeeGridRow"></div>
        </div>`;
    }

    const gridRow = document.getElementById('employeeGridRow');
    
    // Check if employee already exists in grid to avoid duplicates
    if (document.getElementById('emp_' + empCode)) {
        return; 
    }

    const html = `
    <div class="grid-col-3" id="emp_col_${empCode}">
        <div class="form-check">
            <input class="form-check-input emp-process-cb" type="checkbox" checked id="emp_${empCode}" value="${empCode}" onchange="updateSelectedCount()">
            <label class="form-check-label" for="emp_${empCode}">
                ${empName} - ${empCode}
            </label>
        </div>
    </div>`;
    
    gridRow.insertAdjacentHTML('beforeend', html);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkedBoxes = document.querySelectorAll('.emp-process-cb:checked');
    const countEl = document.getElementById('selectedCount');
    if (countEl) countEl.innerText = checkedBoxes.length;
    updateURLParams();
}

function updateURLParams() {
    const checkedBoxes = document.querySelectorAll('.emp-process-cb:checked');
    const empCodes = Array.from(checkedBoxes).map(cb => cb.value);
    
    const urlParams = new URLSearchParams(window.location.search);
    if (empCodes.length > 0) {
        urlParams.set('employee', empCodes.join(','));
    } else {
        urlParams.delete('employee');
    }
    
    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
    window.history.replaceState({}, '', newUrl);
}

function clearSelectedEmployees() {
    const container = document.getElementById('dynamicEmployeeContainer');
    if (container) {
        container.innerHTML = '';
    }
    updateURLParams();
}

// ---------------------------------------------------------
// Autocomplete Logic
// ---------------------------------------------------------
const searchInput = document.getElementById('employeeSearchInput');
const dropdown = document.getElementById('autocompleteDropdown');

if (searchInput && dropdown) {
    let debounceTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        if (query.length < 2) {
            dropdown.style.display = 'none';
            return;
        }
        debounceTimer = setTimeout(() => {
            fetch(`?ajax_search=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(emp => {
                            const item = document.createElement('div');
                            item.className = 'autocomplete-item';
                            item.innerHTML = `<span class="autocomplete-name">${emp.employee_name}</span><span class="autocomplete-code">#${emp.employee_code}</span>`;
                            
                            // Appends directly to grid without reloading
                            item.addEventListener('click', () => {
                                searchInput.value = ''; // Clears search box immediately
                                dropdown.style.display = 'none';
                                addEmployeeToGrid(emp.employee_code, emp.employee_name);
                            });
                            
                            dropdown.appendChild(item);
                        });
                    } else {
                        dropdown.innerHTML = '<div class="autocomplete-item text-muted">No employees found</div>';
                    }
                    dropdown.style.display = 'block';
                })
                .catch(error => console.error('Error:', error));
        }, 300);
    });
}

// Close Dropdowns on outside click
document.addEventListener('click', function(e) {
    if (searchInput && dropdown && !searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
    }
    const dateMenu = document.getElementById('dateOptionsMenu');
    const dateWrap = document.querySelector('.custom-date-select');
    if (dateMenu && dateWrap && !dateWrap.contains(e.target)) {
        dateMenu.classList.remove('open');
    }
});

// ---------------------------------------------------------
// Custom Date Picker Logic
// ---------------------------------------------------------
const hiddenDateInput = document.getElementById('hiddenDateRange');
const dateDisplay = document.getElementById('dateDisplay');
const dateMenu = document.getElementById('dateOptionsMenu');

const fp = flatpickr(hiddenDateInput, {
    mode: "range",
    dateFormat: "d M Y",
    showMonths: 2,
    onClose: function(selectedDates, dateStr, instance) {
        if (selectedDates.length === 2) {
            dateDisplay.innerText = dateStr.replace(' to ', ' - ');
            hiddenDateInput.value = dateStr.replace(' to ', ' - ');
            dateMenu.classList.remove('open');
        }
    }
});

function toggleDateMenu(e) {
    e.stopPropagation();
    dateMenu.classList.toggle('open');
}

function setDateRange(rangeType) {
    const today = new Date();
    let start, end;
    
    if (rangeType === 'this_month') {
        start = new Date(today.getFullYear(), today.getMonth(), 1);
        end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    } else if (rangeType === 'last_month') {
        start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        end = new Date(today.getFullYear(), today.getMonth(), 0);
    }

    if (start && end) {
        fp.setDate([start, end], true);
        const formatFpDate = (d) => {
            const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            return String(d.getDate()).padStart(2, '0') + " " + months[d.getMonth()] + " " + d.getFullYear();
        };
        const str = formatFpDate(start) + " - " + formatFpDate(end);
        dateDisplay.innerText = str;
        hiddenDateInput.value = str;
    }
    dateMenu.classList.remove('open');
}

function openCustomDatePicker(e) {
    e.stopPropagation();
    dateMenu.classList.remove('open');
    fp.open();
}

// ---------------------------------------------------------
// Process Action Logic
// ---------------------------------------------------------
document.getElementById('btnProcessTimeCard')?.addEventListener('click', function() {
    const checkedBoxes = document.querySelectorAll('.emp-process-cb:checked');
    const overwriteCheckbox = document.getElementById('overwriteManual');
    
    if (checkedBoxes.length === 0 && document.getElementById('employeeSelectionCard')) {
        Swal.fire('Warning', 'Please select at least one employee from the list to process.', 'warning');
        return;
    }
    
    const empCodes = Array.from(checkedBoxes).map(cb => cb.value);
    const empCodeStr = empCodes.length > 0 ? empCodes.join(',') : 'all';
    
    const overwrite = overwriteCheckbox ? overwriteCheckbox.checked : false;
    const dateRange = hiddenDateInput.value;

    Swal.fire({
        title: 'Confirm Processing',
        text: `Are you sure you want to process the time card for ${empCodeStr === 'all' ? 'All Employees' : checkedBoxes.length + ' selected employee(s)'}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        confirmButtonText: 'Yes, Process'
    }).then((result) => {
        if (result.isConfirmed) {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Processing...';
            btn.disabled = true;

            const formData = new URLSearchParams({ 
                action: 'process_timecard',
                emp_code: empCodeStr,
                date_range: dateRange,
                overwrite: overwrite
            });

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
                } else {
                    Swal.fire('Error', data.error || 'Processing failed.', 'error');
                }
                btn.innerHTML = originalText;
                btn.disabled = false;
            })
            .catch(err => {
                Swal.fire('Error', 'A server error occurred.', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    });
});

// ---------------------------------------------------------
// Advanced Search Modal Logic
// ---------------------------------------------------------
document.getElementById('modalSearchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        performModalSearch();
    }
});

function openFilterModal() { document.getElementById('filterModal').style.display = 'flex'; }
function closeFilterModal() { document.getElementById('filterModal').style.display = 'none'; }

function performModalSearch() {
    const grid = document.getElementById('modalEmpGrid');
    const countSpan = document.getElementById('empFoundCount');
    const limitSelect = document.getElementById('modalLimit');
    
    const selectAllCb = document.getElementById('modalSelectAll');
    if (selectAllCb) selectAllCb.checked = false;

    grid.innerHTML = '<div style="padding: 15px; color: #6B7280; text-align: center; font-size: 13px;">Searching...</div>';

    const params = new URLSearchParams({
        ajax_advanced_search: '1',
        name_code: document.getElementById('modalSearchInput').value,
        org: document.getElementById('filterOrg').value,
        loc: document.getElementById('filterLoc').value,
        dept: document.getElementById('filterDept').value,
        desig: document.getElementById('filterDesig').value,
        status: document.getElementById('filterStatus').value,
        group: document.getElementById('filterGroup').value,
        sub_group: document.getElementById('filterSubGroup').value,
        limit: limitSelect ? limitSelect.value : 50
    });

    fetch(window.location.pathname + '?' + params.toString()).then(res => res.json()).then(data => {
        countSpan.innerText = data.length;
        if (data.length === 0) {
            grid.innerHTML = '<div style="padding: 15px; color: #6B7280; text-align: center; font-size: 13px;">No employees found matching criteria.</div>';
            return;
        }
        let html = '';
        data.forEach(emp => {
            html += `
                <div style="padding: 10px 15px; border-bottom: 1px solid #F3F4F6; display: flex; align-items: center; gap: 12px;">
                    <input type="checkbox" class="form-check-input modal-emp-cb" value="${emp.employee_code}" data-name="${emp.employee_name}" style="cursor: pointer; margin: 0;">
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 13.5px; font-weight: 500; color: #111827;">${emp.employee_name}</span>
                        <span style="font-size: 11px; color: #6B7280;">#${emp.employee_code}</span>
                    </div>
                </div>`;
        });
        grid.innerHTML = html;
    }).catch(err => {
        grid.innerHTML = '<div style="padding: 15px; color: #EF4444; text-align: center; font-size: 13px;">Failed to fetch results.</div>';
    });
}

function toggleModalSelectAll(checkboxElement) {
    const checkboxes = document.querySelectorAll('.modal-emp-cb');
    checkboxes.forEach(cb => cb.checked = checkboxElement.checked);
}

function clearModalSelections() {
    document.getElementById('modalSearchInput').value = '';
    document.querySelectorAll('.modal-filter-grid select').forEach(s => s.value = '');
    document.getElementById('modalEmpGrid').innerHTML = '<span style="padding: 15px; font-size: 13px; color: #9CA3AF; display: block;">Click search to find employees.</span>';
    document.getElementById('empFoundCount').innerText = '0';
    const selectAllCb = document.getElementById('modalSelectAll');
    if (selectAllCb) selectAllCb.checked = false;
}

function applyModalFilters() {
    const selected = document.querySelectorAll('.modal-emp-cb:checked');
    if (selected.length === 0) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Select at least one employee', showConfirmButton: false, timer: 2000 });
        return;
    }

    // Add them dynamically to the background page without reloading
    selected.forEach(cb => {
        addEmployeeToGrid(cb.value, cb.getAttribute('data-name'));
    });

    closeFilterModal();
    clearModalSelections();
}

function switchSidebarTab(tabName) {
    const recentTab = document.getElementById('tabRecentSearch');
    const savedTab = document.getElementById('tabSavedSearch');
    const recentList = document.getElementById('recentSearchList');
    const savedList = document.getElementById('savedSearchList');
    if (tabName === 'recent') {
        recentTab.classList.add('active'); savedTab.classList.remove('active');
        recentList.style.display = 'block'; savedList.style.display = 'none';
    } else {
        savedTab.classList.add('active'); recentTab.classList.remove('active');
        savedList.style.display = 'block'; recentList.style.display = 'none';
    }
}

function saveCurrentSearch() {
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Search parameters saved', showConfirmButton: false, timer: 2000 });
}
</script>
<script src="includes/assets/scripts.js"></script>