<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Day Status Report';

// ==========================================
// FETCH DATA FOR UI RENDER
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
/* --- Page Base --- */
.report-page-container { padding: 20px; background: #f9f9fb; font-family: Arial, sans-serif; min-height: 100vh; }
.report-card { background: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.report-title { font-size: 16px; font-weight: 600; color: #111827; margin-bottom: 20px; margin-top: 0; }

/* --- Top Controls Row --- */
.controls-row { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; position: relative; z-index: 10; }

/* Date Picker Dropdown */
.date-picker-container { position: relative; width: 250px; }
.input-field { display: flex; align-items: center; justify-content: space-between; border: 1px solid #D1D5DB; border-radius: 4px; padding: 8px 12px; background: #fff; cursor: pointer; font-size: 13px; color: #374151; }
.input-field:hover { border-color: #9CA3AF; }
.date-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #D1D5DB; border-radius: 4px; margin-top: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; z-index: 20; }
.date-dropdown.show { display: block; }
.date-dropdown ul { list-style: none; padding: 0; margin: 0; }
.date-dropdown li { padding: 10px 15px; font-size: 13px; color: #374151; cursor: pointer; transition: background 0.15s; }
.date-dropdown li:hover { background: #F3F4F6; }

/* Custom Date Popover */
.custom-date-popover { position: absolute; top: 100%; left: 0; background: #fff; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); margin-top: 8px; width: 600px; display: none; z-index: 30; cursor: default; }
.cdp-header { display: flex; justify-content: center; align-items: center; gap: 15px; padding: 15px; border-bottom: 1px solid #F3F4F6; background: #FAFAFA; border-radius: 8px 8px 0 0; }
.cdp-header label { font-size: 13px; color: #4B5563; }
.cdp-header input[type="date"] { border: 1px solid #D1D5DB; padding: 8px 12px; border-radius: 4px; font-size: 13px; background: #F3F4F6; outline: none; }
.cdp-calendars { display: flex; padding: 20px; gap: 20px; }
.cdp-calendar-pane { flex: 1; user-select: none; }
.cdp-cal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; font-weight: 600; font-size: 14px; }
.cdp-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center; font-size: 13px; }
.cdp-cal-grid .day-name { color: #6B7280; margin-bottom: 5px; }
.cdp-cal-grid .day { padding: 6px; border-radius: 4px; cursor: pointer; }
.cdp-cal-grid .day:hover { background: #F3F4F6; }
.cdp-cal-grid .day.active { background: #007BFF; color: #fff; }
.cdp-cal-grid .day.in-range { background: #F0F4F8; }
.cdp-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 15px; border-top: 1px solid #E5E7EB; }

/* Search Box & Autocomplete */
.search-container { position: relative; width: 320px; }
.search-input-wrapper { display: flex; align-items: center; border: 1px solid #D1D5DB; border-radius: 4px; padding: 8px 12px; background: #fff; }
.search-input-wrapper svg { width: 16px; height: 16px; stroke: #9CA3AF; margin-right: 8px; fill: none; stroke-width: 2; }
.search-input-wrapper input { border: none; outline: none; width: 100%; font-size: 13px; }
.autocomplete-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #D1D5DB; border-radius: 4px; margin-top: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: none; z-index: 20; padding: 8px 0; max-height: 250px; overflow-y: auto; }
.autocomplete-item { display: flex; align-items: center; padding: 10px 15px; cursor: pointer; gap: 12px; font-size: 14px; color: #374151; }
.autocomplete-item:hover { background: #F3F4F6; }
.autocomplete-avatar { width: 30px; height: 30px; background: #E5E7EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #9CA3AF; }
.browse-employees-btn { display: block; padding: 12px 15px 4px 15px; color: #2563EB; font-size: 13px; text-align: center; border-top: 1px solid #E5E7EB; cursor: pointer; text-decoration: none; margin-top: 5px; }

/* Filter Button */
.btn-filter { display: flex; align-items: center; gap: 6px; border: 1px solid #D1D5DB; background: #fff; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; color: #374151; }
.btn-filter:hover { background: #F9FAFB; }

/* --- Selected Employees Section --- */
.selected-section { margin-bottom: 30px; display: none; }
.selected-title { font-size: 13px; color: #4B5563; margin-bottom: 10px; display: block; }
.selected-cards { border: 1px solid #E5E7EB; border-radius: 6px; padding: 15px; display: flex; flex-wrap: wrap; gap: 15px; background: #FAFAFA; }
.emp-card { display: flex; align-items: flex-start; gap: 10px; }
.emp-card input[type="checkbox"] { margin-top: 3px; cursor: pointer; }
.emp-card-details { font-size: 13px; color: #111827; line-height: 1.4; }

/* --- Access Your Reports Section --- */
.access-section h3 { font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 15px; }
.radio-group { display: flex; flex-direction: column; gap: 12px; margin-bottom: 30px; }
.radio-label { display: flex; align-items: center; font-size: 13px; color: #111827; cursor: pointer; gap: 8px; }
.radio-desc { color: #6B7280; }
.diff-email-input { margin-top: 10px; margin-left: 24px; display: none; }
.diff-email-input input { border: 1px solid #D1D5DB; padding: 8px 12px; border-radius: 4px; width: 250px; font-size: 13px; }

/* Action Button */
.action-row { display: flex; justify-content: flex-end; margin-top: 20px; }
.btn-generate { background: #007BFF; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.15s; text-decoration: none; display: inline-block; }
.btn-generate:hover { background: #0056b3; }
.btn-primary { background: #007BFF; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; }
.btn-outline { background: transparent; border: 1px solid #D1D5DB; color: #374151; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; }

/* --- Advanced Filter Modal Styles --- */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 999; }
.modal-content { background: #fff; width: 800px; max-width: 95%; max-height: 90vh; border-radius: 8px; display: flex; flex-direction: column; }
.modal-header { padding: 15px 20px; border-bottom: 1px solid #E5E7EB; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { margin: 0; font-size: 16px; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; }
.modal-body { padding: 20px; overflow-y: auto; }
.modal-footer { padding: 15px 20px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px; }
.search-line-wrapper svg { width: 16px; height: 16px; stroke: #9CA3AF; fill:none; stroke-width: 2; position: absolute; left: 12px; top: 50%; transform: translateY(-50%); }
.search-line-wrapper input { width: 100%; box-sizing: border-box; padding: 10px 10px 10px 35px; }
.modal-filter-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
.form-group label { display: block; font-size: 12px; color: #4B5563; margin-bottom: 5px; }
.line-input { width: 100%; box-sizing: border-box; border: 1px solid #D1D5DB; padding: 8px; border-radius: 4px; font-size: 13px; }
.modal-search-row { display: flex; justify-content: space-between; align-items: center; }
.modal-results-layout { display: flex; gap: 20px; }
.modal-emp-list-sec { flex: 2; border-right: 1px solid #E5E7EB; padding-right: 20px; }
.modal-emp-grid { border: 1px solid #E5E7EB; border-radius: 4px; height: 200px; overflow-y: auto; padding: 10px; margin-top: 10px; background: #FAFAFA; }
.modal-recent-sec { flex: 1; }
.recent-tabs { display: flex; gap: 15px; border-bottom: 1px solid #E5E7EB; margin-bottom: 15px; }
.recent-tab { font-size: 13px; color: #6B7280; padding-bottom: 5px; cursor: pointer; }
.recent-tab.active { color: #007BFF; border-bottom: 2px solid #007BFF; font-weight: 500; }
</style>

<div class="report-page-container">
    
    <div class="report-card" id="reportSetupContainer">
        <h2 class="report-title">Day Status Report</h2>

        <!-- Controls Row -->
        <div class="controls-row">
            
            <!-- Date Picker -->
            <div class="date-picker-container">
                <div class="input-field" onclick="toggleDateDropdown(event)">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <span id="selectedDateText">01 Jul 2026 TO 31 Jul 2026</span>
                    </div>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                
                <div class="date-dropdown" id="dateDropdown">
                    <ul>
                        <li onclick="selectDateOption('Today')">Today</li>
                        <li onclick="selectDateOption('Yesterday')">Yesterday</li>
                        <li onclick="selectDateOption('Last 7 Days')">Last 7 Days</li>
                        <li onclick="selectDateOption('This Month')">This Month</li>
                        <li onclick="selectDateOption('Last Month')">Last Month</li>
                        <li onclick="openCustomDatePopover()">Custom Date Range</li>
                    </ul>
                </div>

                <div class="custom-date-popover" id="customDatePopover" onclick="event.stopPropagation();">
                    <div class="cdp-header">
                        <label>From</label>
                        <input type="date" id="customDateFrom" value="2026-07-01" onchange="handleInputSync()">
                        <label>To</label>
                        <input type="date" id="customDateTo" value="2026-07-31" onchange="handleInputSync()">
                    </div>
                    
                    <div class="cdp-calendars">
                        <div class="cdp-calendar-pane" id="cdp-left-pane"></div>
                        <div class="cdp-calendar-pane" id="cdp-right-pane"></div>
                    </div>
                    
                    <div class="cdp-footer">
                        <button class="btn-outline" onclick="closeCustomDatePopover()">Cancel</button>
                        <button class="btn-primary" onclick="applyCustomDate()">Apply</button>
                    </div>
                </div>
            </div>

            <!-- Search Autocomplete -->
            <div class="search-container">
                <div class="search-input-wrapper">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="empSearchInput" placeholder="Search by name or #code" onkeyup="handleSearchInput()" onclick="event.stopPropagation()" autocomplete="off">
                </div>
                <div class="autocomplete-dropdown" id="searchDropdown" onclick="event.stopPropagation()">
                    <div id="searchResultsList"></div>
                    <a href="#" class="browse-employees-btn" onclick="openFilterModal(event)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> 
                        Browse Active & Inactive Employees
                    </a>
                </div>
            </div>

            <button class="btn-filter" onclick="openFilterModal()">
                <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filter
            </button>
        </div>

        <div class="selected-section" id="selectedSection">
            <span class="selected-title">Selected Employees - <span id="selCount">0</span></span>
            <div class="selected-cards" id="selectedCardsContainer"></div>
        </div>

        <div class="access-section">
            <h3>Access Your Reports</h3>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="report_access" value="now" checked onchange="toggleEmailInput()">
                    <span><strong>Generate Reports Now</strong> <span class="radio-desc">(Get the report immediately in report history.)</span></span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="report_access" value="my_email" onchange="toggleEmailInput()">
                    <span><strong>Send to My Email</strong> <span class="radio-desc">(Receive the file at your registered email address.)</span></span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="report_access" value="diff_email" onchange="toggleEmailInput()">
                    <span><strong>Send to a Different Email</strong> <span class="radio-desc">(Enter an alternative email address to send the file.)</span></span>
                </label>
                
                <div class="diff-email-input" id="diffEmailContainer">
                    <label style="font-size:12px; color:#666; display:block; margin-bottom:5px;">Enter Email ID</label>
                    <input type="email" placeholder="Enter email address" style="border-bottom: 1px solid #ccc; border-top:none; border-left:none; border-right:none; border-radius:0; width:300px; padding-left:0;">
                </div>
            </div>
        </div>

        <div class="action-row">
            <button type="button" class="btn-generate" onclick="generateReport()">Generate Report</button>
        </div>
    </div> 
</div>


<!-- ========================================== -->
<!-- ADVANCED FILTER MODAL -->
<!-- ========================================== -->
<div id="filterModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Advance Employee Search</h2>
            <button type="button" class="modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="modal-body">
            
            <div class="search-line-wrapper" style="margin-bottom: 25px; position: relative;">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="modalSearchInput" placeholder="Search by name or #code" style="border-radius: 4px; border: 1px solid #D1D5DB; padding-left: 35px;" onkeyup="if(event.key === 'Enter') performModalSearch();">
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
            </div>

            <div class="modal-search-row">
                <span style="font-size: 13px; color: #4B5563;">Records per page : <select><option>25</option><option>50</option><option>100</option></select></span>
                <button type="button" class="btn-primary" onclick="performModalSearch()">Search</button>
            </div>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #E5E7EB;">

            <div class="modal-results-layout">
                <div class="modal-emp-list-sec">
                    <div class="modal-emp-header">
                        <label class="checkbox-label" style="font-weight: 500; font-size:13px;"><input type="checkbox" id="selectAllModalEmp" onclick="toggleAllModalEmp(this)"> Employees Found - <span id="empFoundCount">0</span></label>
                    </div>
                    <div class="modal-emp-grid" id="modalEmpGrid">
                        <span style="font-size: 13px; color: #9CA3AF; padding: 10px; display:block;">Click search to find employees.</span>
                    </div>
                </div>

                <div class="modal-recent-sec">
                    <div class="recent-tabs">
                        <span class="recent-tab active" id="tabRecentSearch" onclick="switchSidebarTab('recent')">Recent Search</span>
                        <span class="recent-tab" id="tabSavedSearch" onclick="switchSidebarTab('saved')">Saved Search</span>
                    </div>
                    <ul class="recent-list" id="recentSearchList" style="list-style: none; padding:0; margin:0; font-size:13px;">
                        <?php if(empty($recent_searches)): ?>
                            <li style="color:#9CA3AF;">No recent searches</li>
                        <?php endif; ?>
                    </ul>
                    <ul class="recent-list" id="savedSearchList" style="display:none; list-style: none; padding:0; margin:0; font-size:13px;">
                        <?php if(empty($saved_searches)): ?>
                            <li style="color:#9CA3AF;">No saved searches</li>
                        <?php endif; ?>
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

<script>
const employeeList = <?= json_encode($employees) ?>;
let selectedEmployees = [];

// ==========================================
// CALENDAR & DATE LOGIC
// ==========================================
let currentCalDate = new Date();
let selectionStartDate = null;
let selectionEndDate = null;

document.addEventListener('click', function(e) {
    if (!e.target.closest('.date-picker-container')) {
        document.getElementById('dateDropdown').classList.remove('show');
        document.getElementById('customDatePopover').style.display = 'none';
    }
    if (!e.target.closest('.search-container')) {
        document.getElementById('searchDropdown').style.display = 'none';
    }
});

function toggleDateDropdown(e) {
    e.stopPropagation();
    document.getElementById('customDatePopover').style.display = 'none';
    document.getElementById('dateDropdown').classList.toggle('show');
}

function selectDateOption(optionText) {
    document.getElementById('selectedDateText').innerText = optionText;
    document.getElementById('dateDropdown').classList.remove('show');
}

function openCustomDatePopover() {
    document.getElementById('dateDropdown').classList.remove('show');
    document.getElementById('customDatePopover').style.display = 'block';
    initCalendar();
}

function closeCustomDatePopover() {
    document.getElementById('customDatePopover').style.display = 'none';
}

function applyCustomDate() {
    const from = document.getElementById('customDateFrom').value;
    const to = document.getElementById('customDateTo').value;
    
    if(from && to) {
        const formatDt = dt => {
            const [y, m, d] = dt.split('-');
            const dateObj = new Date(y, m-1, d);
            const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            return `${d} ${monthNames[dateObj.getMonth()]} ${y}`;
        };
        document.getElementById('selectedDateText').innerText = formatDt(from) + " TO " + formatDt(to);
        closeCustomDatePopover();
    } else {
        alert('Please select both From and To dates.');
    }
}

function parseDateStr(str) {
    if(!str) return null;
    const [y, m, d] = str.split('-');
    return new Date(y, m - 1, d);
}

function formatDateForInput(date) {
    if(!date) return '';
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function initCalendar() {
    const fromVal = document.getElementById('customDateFrom').value;
    const toVal = document.getElementById('customDateTo').value;
    
    selectionStartDate = fromVal ? parseDateStr(fromVal) : null;
    selectionEndDate = toVal ? parseDateStr(toVal) : null;
    
    if (selectionStartDate) {
        currentCalDate = new Date(selectionStartDate.getFullYear(), selectionStartDate.getMonth(), 1);
    } else {
        currentCalDate = new Date();
        currentCalDate.setDate(1);
    }
    
    renderCalendars();
}

function renderCalendars() {
    const leftMonth = new Date(currentCalDate);
    const rightMonth = new Date(currentCalDate);
    rightMonth.setMonth(rightMonth.getMonth() + 1);

    renderPane('cdp-left-pane', leftMonth, true);
    renderPane('cdp-right-pane', rightMonth, false);
}

function renderPane(paneId, monthDate, isLeft) {
    const pane = document.getElementById(paneId);
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    
    let headHtml = `<div class="cdp-cal-head">`;
    if (isLeft) {
        headHtml += `<span style="cursor:pointer; color:#6b7280; padding:0 10px;" onclick="changeMonth(-1)">&larr;</span>`;
    } else {
        headHtml += `<span></span>`;
    }
    headHtml += `<span>${monthNames[monthDate.getMonth()]} ${monthDate.getFullYear()}</span>`;
    if (!isLeft) {
        headHtml += `<span style="cursor:pointer; color:#6b7280; padding:0 10px;" onclick="changeMonth(1)">&rarr;</span>`;
    } else {
        headHtml += `<span></span>`;
    }
    headHtml += `</div>`;

    let gridHtml = `<div class="cdp-cal-grid">
        <div class="day-name">Su</div><div class="day-name">Mo</div><div class="day-name">Tu</div>
        <div class="day-name">We</div><div class="day-name">Th</div><div class="day-name">Fr</div><div class="day-name">Sa</div>`;
    
    const firstDay = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1).getDay();
    const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();

    for (let i = 0; i < firstDay; i++) {
        gridHtml += `<div></div>`;
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${monthDate.getFullYear()}-${String(monthDate.getMonth()+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const currDate = new Date(monthDate.getFullYear(), monthDate.getMonth(), d);
        currDate.setHours(0,0,0,0);
        
        let classes = "day";
        const currTime = currDate.getTime();
        const sTime = selectionStartDate ? selectionStartDate.getTime() : null;
        const eTime = selectionEndDate ? selectionEndDate.getTime() : null;
        
        if (sTime === currTime) classes += " active";
        if (eTime === currTime) classes += " active";
        if (sTime && eTime && currTime > sTime && currTime < eTime) classes += " in-range";
        
        gridHtml += `<div class="${classes}" onclick="selectDate('${dateStr}')">${d}</div>`;
    }
    gridHtml += `</div>`;
    
    pane.innerHTML = headHtml + gridHtml;
}

function changeMonth(delta) {
    currentCalDate.setMonth(currentCalDate.getMonth() + delta);
    renderCalendars();
}

function selectDate(dateStr) {
    const selected = parseDateStr(dateStr);
    
    if (!selectionStartDate || (selectionStartDate && selectionEndDate)) {
        selectionStartDate = selected;
        selectionEndDate = null;
    } else if (selectionStartDate && !selectionEndDate) {
        if (selected < selectionStartDate) {
            selectionEndDate = selectionStartDate;
            selectionStartDate = selected;
        } else {
            selectionEndDate = selected;
        }
    }
    
    if (selectionStartDate) document.getElementById('customDateFrom').value = formatDateForInput(selectionStartDate);
    if (selectionEndDate) document.getElementById('customDateTo').value = formatDateForInput(selectionEndDate);
    
    renderCalendars();
}

function handleInputSync() {
    const fromVal = document.getElementById('customDateFrom').value;
    const toVal = document.getElementById('customDateTo').value;
    
    if(fromVal) {
        selectionStartDate = parseDateStr(fromVal);
        currentCalDate = new Date(selectionStartDate.getFullYear(), selectionStartDate.getMonth(), 1);
    } else { selectionStartDate = null; }
    
    if(toVal) {
        selectionEndDate = parseDateStr(toVal);
    } else { selectionEndDate = null; }
    
    renderCalendars();
}

function handleSearchInput() {
    const query = document.getElementById('empSearchInput').value.toLowerCase();
    const dropdown = document.getElementById('searchDropdown');
    const resultsList = document.getElementById('searchResultsList');
    
    if(query.length < 2) {
        dropdown.style.display = 'none';
        return;
    }

    const filtered = employeeList.filter(emp => 
        emp.employee_name.toLowerCase().includes(query) || 
        emp.employee_code.toLowerCase().includes(query)
    );

    resultsList.innerHTML = '';

    if (filtered.length > 0) {
        filtered.forEach(emp => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.innerHTML = `
                <div class="autocomplete-avatar">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <div>${emp.employee_name} - #${emp.employee_code}</div>
            `;
            item.onclick = function(e) { 
                e.stopPropagation();
                selectEmployee(emp); 
            };
            resultsList.appendChild(item);
        });
    } else {
        resultsList.innerHTML = '<div style="padding: 10px 15px; font-size: 13px; color: #9CA3AF;">No employees found.</div>';
    }

    dropdown.style.display = 'block';
}

function selectEmployee(emp) {
    if(!selectedEmployees.find(e => e.employee_code === emp.employee_code)) {
        selectedEmployees.push(emp);
        renderSelectedEmployees();
    }
    document.getElementById('empSearchInput').value = '';
    document.getElementById('searchDropdown').style.display = 'none';
}

function renderSelectedEmployees() {
    const container = document.getElementById('selectedSection');
    const cardsContainer = document.getElementById('selectedCardsContainer');
    const countSpan = document.getElementById('selCount');
    
    if(selectedEmployees.length === 0) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    countSpan.innerText = selectedEmployees.length;
    
    cardsContainer.innerHTML = '';
    selectedEmployees.forEach((emp, index) => {
        const card = document.createElement('div');
        card.className = 'emp-card';
        card.innerHTML = `
            <input type="checkbox" checked onclick="removeEmployee(${index})">
            <div class="emp-card-details">
                <strong>${emp.employee_name}</strong><br>
                ${emp.employee_code}
            </div>
        `;
        cardsContainer.appendChild(card);
    });
}

function removeEmployee(index) {
    selectedEmployees.splice(index, 1);
    renderSelectedEmployees();
}

function toggleEmailInput() {
    const radios = document.getElementsByName('report_access');
    let diffEmailSelected = false;
    
    radios.forEach(radio => {
        if(radio.checked && radio.value === 'diff_email') {
            diffEmailSelected = true;
        }
    });

    document.getElementById('diffEmailContainer').style.display = diffEmailSelected ? 'block' : 'none';
}

function openFilterModal(e) {
    if(e) e.preventDefault();
    document.getElementById('searchDropdown').style.display = 'none';
    document.getElementById('filterModal').style.display = 'flex';
}

function closeFilterModal() {
    document.getElementById('filterModal').style.display = 'none';
}

function switchSidebarTab(tabName) {
    document.querySelectorAll('.recent-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('recentSearchList').style.display = 'none';
    document.getElementById('savedSearchList').style.display = 'none';

    if(tabName === 'recent') {
        document.getElementById('tabRecentSearch').classList.add('active');
        document.getElementById('recentSearchList').style.display = 'block';
    } else {
        document.getElementById('tabSavedSearch').classList.add('active');
        document.getElementById('savedSearchList').style.display = 'block';
    }
}

function clearModalSelections() {
    document.querySelectorAll('.modal-filter-grid select, #modalSearchInput').forEach(el => el.value = '');
    document.getElementById('modalEmpGrid').innerHTML = '<span style="font-size: 13px; color: #9CA3AF; padding: 10px; display:block;">Click search to find employees.</span>';
    document.getElementById('empFoundCount').innerText = '0';
    document.getElementById('selectAllModalEmp').checked = false;
}

function performModalSearch() {
    const query = document.getElementById('modalSearchInput').value.toLowerCase();
    const grid = document.getElementById('modalEmpGrid');
    const countSpan = document.getElementById('empFoundCount');
    
    let html = '';
    let count = 0;
    
    employeeList.forEach(emp => {
        if(emp.employee_name.toLowerCase().includes(query) || emp.employee_code.toLowerCase().includes(query)) {
            html += `
                <label style="display: flex; align-items: center; gap: 10px; padding: 8px; border-bottom: 1px solid #eee; font-size: 13px; cursor:pointer;">
                    <input type="checkbox" class="modal-emp-checkbox" value="${emp.employee_code}" data-name="${emp.employee_name}">
                    ${emp.employee_name} - #${emp.employee_code}
                </label>
            `;
            count++;
        }
    });
    
    if(count > 0) {
        grid.innerHTML = html;
    } else {
        grid.innerHTML = '<span style="font-size: 13px; color: #9CA3AF; padding: 10px; display:block;">No employees found matching criteria.</span>';
    }
    
    countSpan.innerText = count;
    document.getElementById('selectAllModalEmp').checked = false;
}

function toggleAllModalEmp(source) {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
}

function applyModalFilters() {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox:checked');
    let addedCount = 0;
    
    checkboxes.forEach(cb => {
        const empCode = cb.value;
        const empName = cb.getAttribute('data-name');
        
        if(!selectedEmployees.find(e => e.employee_code === empCode)) {
            selectedEmployees.push({ employee_code: empCode, employee_name: empName });
            addedCount++;
        }
    });
    
    if(addedCount > 0) {
        renderSelectedEmployees();
    }
    
    closeFilterModal();
}

// Dynamically generate the report URL with date filters & selected employees
function generateReport() {
    const fromVal = document.getElementById('customDateFrom').value || '2026-07-01';
    const toVal = document.getElementById('customDateTo').value || '2026-07-31';
    
    let url = `reportsng?start=${fromVal}&end=${toVal}`;
    
    if (selectedEmployees.length > 0) {
        const empCodes = selectedEmployees.map(e => e.employee_code).join(',');
        url += `&emps=${empCodes}`;
    }
    
    window.location.href = url;
}

</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>