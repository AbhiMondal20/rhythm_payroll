<?php
session_start();
if (!isset($_SESSION['login'])) {
    if (isset($_POST['action']) || isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login');
    exit();
}

require_once 'includes/config.php';
require_once 'includes/db_client.php';

// Handle Saving Attendance (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (isset($_POST['attendance']) && is_array($_POST['attendance'])) {
        $attendance_data = $_POST['attendance'];
        
        foreach ($attendance_data as $emp_code => $dates) {
            $clean_emp_code = mysqli_real_escape_string($conn, $emp_code);
            
            foreach ($dates as $date => $status) {
                $clean_date = mysqli_real_escape_string($conn, $date);
                $clean_status = mysqli_real_escape_string($conn, $status);
                
                // Check if a record already exists for this employee and date
                $check_sql = "SELECT id FROM time_entries WHERE employee_code = '$clean_emp_code' AND entry_date = '$clean_date'";
                $check_res = mysqli_query($conn, $check_sql);
                
                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    // Update existing record
                    $update_sql = "UPDATE time_entries 
                                   SET day_status_1 = '$clean_status', updated_at = NOW() 
                                   WHERE employee_code = '$clean_emp_code' AND entry_date = '$clean_date'";
                    mysqli_query($conn, $update_sql);
                } else {
                    // Insert new record
                    $insert_sql = "INSERT INTO time_entries 
                                   (employee_code, entry_date, day_status_1, created_at, updated_at) 
                                   VALUES ('$clean_emp_code', '$clean_date', '$clean_status', NOW(), NOW())";
                    mysqli_query($conn, $insert_sql);
                }
            }
        }
        
        // Redirect to same URL to prevent form resubmission
        $query_string = $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : '';
        header("Location: ?msg=saved" . $query_string);
        exit;
    }
}

// Handle AJAX Request for Live Employee Search
if (isset($_GET['ajax_search'])) {
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
    // Dummy fallback for UI demonstration if DB connection is unavailable
    if (empty($results) && !isset($conn)) {
        $results = [
            ['employee_code' => '1104', 'employee_name' => 'Abhijit Kumar Mondal']
        ];
    }
    echo json_encode($results);
    exit;
}

$page_title = 'Attendance - Manual Attendance';

// Process selected parameters
$selected_employees = isset($_GET['employees']) ? $_GET['employees'] : [];
$target_date_str = isset($_GET['target_date']) ? trim($_GET['target_date']) : date('d M Y');

$is_searched = !empty($selected_employees);

// Generate Array of 7 Dates for the Grid starting from target date
$dates_header = [];
$start_date_obj = new DateTime($target_date_str);

for ($i = 0; $i < 7; $i++) {
    $dates_header[$start_date_obj->format("Y-m-d")] = $start_date_obj->format("j M, D");
    $start_date_obj->modify('+1 day');
}

// Re-adjust for querying purposes
$start_iso = array_key_first($dates_header);
$end_iso = array_key_last($dates_header);

$calendar_data = [];
$employee_display_names = [];

if ($is_searched && isset($conn)) {
    $emp_in_clause = [];
    foreach ($selected_employees as $emp_id) {
        $clean_id = mysqli_real_escape_string($conn, $emp_id);
        $emp_in_clause[] = "'$clean_id'";
    }
    $in_str = implode(',', $emp_in_clause);
    
    // Find matching employees
    $emp_sql = "SELECT employee_code, employee_name, profile_photo FROM employees WHERE employee_code IN ($in_str)"; 
    $emp_result = mysqli_query($conn, $emp_sql);
    
    if ($emp_result && mysqli_num_rows($emp_result) > 0) {
        while ($emp = mysqli_fetch_assoc($emp_result)) {
            $employee_display_names[$emp['employee_code']] = $emp['employee_name'] . ' - ' . $emp['employee_code'];
            
            $calendar_data[$emp['employee_code']] = [
                'details' => $emp,
                'attendance' => []
            ];
            // Pre-fill empty dates
            foreach($dates_header as $iso => $display) {
                $calendar_data[$emp['employee_code']]['attendance'][$iso] = ''; 
            }
        }
        
        // Fetch Attendance records
        $time_sql = "SELECT employee_code, entry_date, day_status_1 
                     FROM time_entries 
                     WHERE employee_code IN ($in_str) 
                     AND entry_date BETWEEN '$start_iso' AND '$end_iso'";
                     
        $time_result = mysqli_query($conn, $time_sql);
        if ($time_result) {
            while ($row = mysqli_fetch_assoc($time_result)) {
                $emp_id = $row['employee_code'];
                $date_key = $row['entry_date'];
                if (isset($calendar_data[$emp_id]['attendance'][$date_key])) {
                    $calendar_data[$emp_id]['attendance'][$date_key] = $row['day_status_1'];
                }
            }
        }
    }
} else if ($is_searched && !isset($conn)) {
    // Dummy Data for UI demonstration
    $employee_display_names['1104'] = 'Abhijit Kumar Mondal - 1104';
    $calendar_data['1104'] = [
        'details' => ['employee_name' => 'Abhijit Kumar Mondal', 'employee_code' => '1104', 'profile_photo' => ''],
        'attendance' => []
    ];
    $idx = 0;
    $sample_statuses = ['Present', 'Loss of Pay', 'Casual Leave', 'Absent', 'Absent', 'Absent', 'Present'];
    foreach($dates_header as $iso => $display) {
        $calendar_data['1104']['attendance'][$iso] = $sample_statuses[$idx]; 
        $idx++;
    }
}

// Helper Function to Render Thin Status Icons
function renderStatusIcon($statusCode) {
    if (empty($statusCode)) return '<span class="status-icon empty">-</span>';
    
    $status = strtolower($statusCode);
    if (in_array($status, ['present', 'p', 'pp'])) {
        return '<i class="bi bi-check-lg text-success status-icon" data-status="Present"></i>';
    } else if (in_array($status, ['absent', 'a', 'aa'])) {
        return '<i class="bi bi-x-lg text-danger status-icon" data-status="Absent"></i>';
    } else if ($status == 'wo' || $status == 'week off') {
        return '<span class="status-icon text-muted" data-status="WO">WO</span>';
    } else if ($status == 'a*p') {
        return '<span class="status-icon text-dark" data-status="A*P">A*P</span>';
    } else if ($status == 'p*a') {
        return '<span class="status-icon text-dark" data-status="P*A">P*A</span>';
    }
    return '<span class="status-icon text-dark" data-status="'.htmlspecialchars($statusCode).'">'.htmlspecialchars($statusCode).'</span>';
}

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
    /* Layout & Generic */
    .flex-between { display: flex; justify-content: space-between; align-items: center; }
    .mb-1 { margin-bottom: 4px; }
    .d-flex { display: flex; align-items: center; }
    .text-dark { color: #111827; }
    .text-muted { color: #6b7280; }
    .text-success { color: #16a34a !important; }
    .text-danger { color: #ef4444 !important; }
    .fw-bold { font-weight: 600; }
    .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    .card { background: #fff; border-radius: 8px; min-height: 500px; }

    /* Tabs */
    .attendance-tabs { border-bottom: 1px solid #dee2e6; }
    .attendance-tabs a { color: #6c757d; text-decoration: none; padding: 3px 5px; gap: 12px; display: inline-block; font-size: 14px; transition: color 0.2s; }
    .attendance-tabs .separator { color: #D1D5DB; font-size: 14px; }
    .attendance-tabs a:hover { color: #495057; }
    .attendance-tabs a.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; font-weight: 500; }
    
    /* Search Bar Area */
    .filter-bar { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; }
    
    .search-wrapper { position: relative; width: 320px; }
    .search-wrapper .form-control { padding-left: 35px; border-radius: 4px; font-size: 14px; border: 1px solid #d1d5db; height: 38px; width: 100%; outline: none; }
    .search-wrapper .bi-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 15px; }
    
    .form-control:focus, .form-select:focus { border-color: #0d6efd; }
    /* Autocomplete */
    .autocomplete-dropdown { position: absolute; top: 100%; left: 0; right: 0; border-top:none; background: #fff; border: 1px solid #d1d5db; border-radius: 4px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); z-index: 1000; max-height: 250px; overflow-y: auto; display: none; margin-top: 4px; }
    .autocomplete-item { padding: 10px 14px; cursor: pointer; font-size: 13.5px; border-bottom: 1px solid #f3f4f6; display: flex; flex-direction: column; }
    .autocomplete-item:hover { background-color: #eff6ff; }
    .autocomplete-name { font-weight: 600; color: #111827; }
    .autocomplete-code { font-size: 11px; color: #6b7280; margin-top: 2px; }

    /* Checkbox List Styling */
    .selected-employees-container { margin-bottom: 30px; display: flex; flex-direction: column; gap: 8px; }
    .employee-checkbox-item { border: 1px solid #d1d5db; border-radius: 4px; padding: 10px 14px; display: flex; align-items: center; gap: 10px; font-size: 14px; color: #374151; background: #fff; }
    .employee-checkbox-item input[type="checkbox"] { width: 16px; height: 16px; accent-color: #0d6efd; cursor: pointer; }

    /* Date Dropdown & Buttons */
    .date-dropdown { display: flex; align-items: center; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; height: 38px; width: 194px; padding: 0 12px; gap: 8px; }
    .date-dropdown .bi-calendar { color: #6b7280; }
    .date-dropdown input { border: none; background: transparent; flex: 1; outline: none; font-size: 14px; color: #4b5563; cursor: pointer; width: 100%; }

    .btn-blue { background-color: #0d6efd; color: #fff; height: 38px; padding: 0 20px; font-size: 14px; font-weight: 500; border: none; border-radius: 4px; cursor: pointer; transition: 0.2s; }
    .btn-blue:hover { background-color: #0b5ed7; }
    
    .btn-outline-green { background: transparent; color: #16a34a; border: 1px solid #16a34a; height: 36px; padding: 0 16px; font-size: 13.5px; border-radius: 4px; cursor: pointer; transition: 0.2s; }
    .btn-outline-green:hover { background: #f0fdf4; }

    /* Empty State */
    .empty-state { text-align: center; padding: 60px 20px; display: flex; flex-direction: column; align-items: center; }
    .empty-state h6 { font-size: 15px; font-weight: 600; color: #374151; margin-bottom: 24px; }
    .empty-illustration { width: 350px; max-width: 100%; margin: 0 auto; opacity: 0.9; pointer-events: none; }

    /* Calendar Grid Specific Styles */
    .table-container { border-radius: 6px; overflow-x: auto; width: 100%; border: 1px solid #e5e7eb; margin-bottom: 20px; }
    .table-calendar { width: 100%; border-collapse: collapse; text-align: center; white-space: nowrap; table-layout: fixed; }
    .table-calendar th { background-color: #f8f9fa; font-weight: 600; font-size: 13px; color: #374151; border-bottom: 1px solid #e5e7eb; padding: 14px 10px; border-right: 1px solid #e5e7eb; }
    .table-calendar td { vertical-align: middle; padding: 14px 10px; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; position: relative; cursor: pointer; height: 50px; }
    .table-calendar td:hover { background-color: #f9fafb; }
    
    .table-calendar th:first-child, .table-calendar td:first-child { text-align: left; padding-left: 16px; border-right: 1px solid #e5e7eb; background: #fff; cursor: default; width: 250px; }
    .table-calendar th:first-child { background: #f8f9fa; }
    
    /* Small arrow columns */
    .col-arrow { width: 40px; cursor: pointer; color: #6b7280; font-weight: bold; font-size: 12px; }
    .col-arrow:hover { background-color: #f3f4f6; }

    .emp-row-info { display: flex; align-items: center; gap: 12px; }
    .emp-mini-avatar { width: 28px; height: 28px; background: #d1d5db; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; color: #fff; }
    .emp-mini-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .emp-name-text { font-size: 14px; font-weight: 400; color: #374151; }

    .status-icon { font-size: 1rem; font-weight: 500; }
    .status-icon.empty { color: #d1d5db; }
    
    /* In-Cell Scrollable Hover Menu matching the image */
    .attendance-cell {
        position: relative; 
    }
    .cell-dropdown { 
        position: absolute; 
        top: -1px; /* Align precisely with top border */
        left: -1px; /* Align precisely with left border */
        width: calc(100% + 2px); /* Span exactly across the td borders */
        background: #f8f9fa; 
        border: 1px solid #cbd5e1; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); 
        z-index: 100; 
        text-align: left; 
        display: none; 
        max-height: 120px; /* Forces scroll bar matching the image style */
        overflow-y: auto;
    }
    .cell-dropdown.show { display: block; }
    
    .dropdown-option { 
        padding: 8px 12px; 
        font-size: 13px; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
        cursor: pointer; 
        color: #1e293b; 
        background-color: #f8f9fa;
        transition: background 0.1s; 
    }
    .dropdown-option:hover { background: #e2e8f0; }
    .dropdown-option i { font-size: 13px; }
    
    /* Custom Scrollbar for Dropdown to match image */
    .cell-dropdown::-webkit-scrollbar {
        width: 8px;
    }
    .cell-dropdown::-webkit-scrollbar-track {
        background: transparent;
        margin: 2px 0;
    }
    .cell-dropdown::-webkit-scrollbar-thumb {
        background-color: #94a3b8;
        border-radius: 10px;
        border: 2px solid #f8f9fa; /* Matches dropdown background for padding effect */
    }
    
    /* Toast Notification Style */
   .toast-notification {
    position: fixed;
    bottom: 25px;
    right: 25px;
    background-color: #10b981;
    color: #fff;
    padding: 14px 24px;
    border-radius: 6px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14.5px;
    font-weight: 500;
    z-index: 9999;
    opacity: 0;
    transform: translateX(100%);
    animation: slideInRight 0.4s ease-out forwards;
    pointer-events: none;
}
    @keyframes slideInRight {
        to { opacity: 1; transform: translateX(0); }
    }
    @keyframes fadeOutRight {
        to { opacity: 0; transform: translateX(100%); }
    }
</style>

<!-- Toast Element -->
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
    <div id="successToast" class="toast-notification">
        <i class="bi bi-check-circle-fill" style="font-size: 18px;"></i>
        Attendance records saved successfully.
    </div>
<?php endif; ?>

<div class="container">
    <div class="flex-between mb-1">
        <h4 class="text-dark fw-bold m-0" style="font-size: 1.25rem;">Attendance</h4>
        <div class="attendance-tabs m-0 border-0">
            <a href="attendance">Time Entries</a>
            <span class="separator">|</span>
            <a href="AttCalendarView">Calendar View</a>
            <span class="separator">|</span>
            <a href="ManualAttendance" class="active">Manual Attendance</a>
            <span class="separator">|</span>
            <a href="AttDiscrepancies">Discrepancies</a>
            <span class="separator">|</span>
            <a href="AttProcessTimeCard">Process Time Card</a>
            <span class="separator">|</span>
            <a href="AttApproveOvertime">Approve Overtime</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h6 class="text-dark fw-bold mb-4" style="font-size: 13.5px; letter-spacing: 0.5px; margin-top: 0; text-transform: uppercase;">MANUAL ATTENDANCE</h6>            
            <!-- Filters Form (GET) -->
            <form method="GET" action="" id="filterForm">
                <div class="filter-bar">
                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text" id="employeeSearchInput" class="form-control" placeholder="Search by Employee Name or #code" autocomplete="off">
                        <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
                    </div>
                    
                    <div class="date-dropdown">
                        <i class="bi bi-calendar"></i>
                        <input type="text" name="target_date" id="targetDate" value="<?= htmlspecialchars($target_date_str) ?>" readonly>
                    </div>
                    
                    <button type="submit" class="btn-blue">Get Details</button>
                </div>

                <div class="selected-employees-container" id="selectedEmployeesContainer">
                    <?php if (!empty($employee_display_names)): ?>
                        <?php foreach ($employee_display_names as $code => $displayName): ?>
                            <div class="employee-checkbox-item" data-code="<?= htmlspecialchars($code) ?>">
                                <input type="checkbox" name="employees[]" value="<?= htmlspecialchars($code) ?>" checked>
                                <span><?= htmlspecialchars($displayName) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!$is_searched): ?>
                <div class="empty-state">
                    <h6>Add employees to process manual attendance</h6>
                    <img src="https://cdni.iconscout.com/illustration/premium/thumb/empty-state-2130362-1800926.png" class="empty-illustration" alt="Add employees illustration" style="opacity: 0.8; max-width: 320px;">
                </div>
            <?php else: ?>
                <?php if (empty($calendar_data)): ?>
                    <div class="empty-state">
                        <h6 class="text-muted">No attendance data found for the selected criteria.</h6>
                    </div>
                <?php else: ?>
                    
                    <div class="d-flex justify-content-end mb-3" style="justify-content: flex-end;">
                        <button type="button" class="btn-outline-green" id="btnMarkAllPresent">Mark all Present</button>
                    </div>

                    <!-- Attendance Saving Form (POST) -->
                    <form method="POST" action="" id="attendanceForm">
                        <input type="hidden" name="save_attendance" value="1">
                        
                        <div class="table-container" style="overflow: visible;">
                            <table class="table-calendar" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Employee Name</th>
                                        <th class="col-arrow" onclick="shiftDate(-7)"><i class="bi bi-chevron-left"></i></th>
                                        
                                        <?php foreach($dates_header as $iso => $display_date): ?>
                                            <th><?= htmlspecialchars($display_date) ?></th>
                                        <?php endforeach; ?>
                                        
                                        <th class="col-arrow" onclick="shiftDate(7)"><i class="bi bi-chevron-right"></i></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($calendar_data as $emp_id => $data): ?>
                                        <tr>
                                            <td>
                                                <div class="emp-row-info">
                                                    <div class="emp-mini-avatar">
                                                        <?php if(!empty($data['details']['profile_photo'])): ?>
                                                            <img src="<?= htmlspecialchars($data['details']['profile_photo']) ?>" alt="">
                                                        <?php else: ?>
                                                            <i class="bi bi-person-fill" style="font-size: 16px;"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="emp-name-text"><?= htmlspecialchars($data['details']['employee_name']) . ' - ' . htmlspecialchars($data['details']['employee_code']) ?></span>
                                                </div>
                                            </td>
                                            
                                            <td style="cursor: default;"></td>

                                            <?php foreach($dates_header as $iso => $display_date): 
                                                $status = $data['attendance'][$iso];
                                            ?>
                                                <td class="attendance-cell" data-date="<?= $iso ?>" data-emp="<?= htmlspecialchars($emp_id) ?>">
                                                    <div class="cell-content">
                                                        <?= renderStatusIcon($status) ?>
                                                    </div>
                                                    <input type="hidden" name="attendance[<?= htmlspecialchars($emp_id) ?>][<?= $iso ?>]" value="<?= htmlspecialchars($status) ?>" class="status-input">
                                                </td>
                                            <?php endforeach; ?>
                                            
                                            <td style="cursor: default;"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end; margin-top: 15px;">
                            <button type="submit" class="btn-blue" id="btnSaveAttendance">SAVE</button>
                        </div>
                    </form>
                    
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Updated template based exactly on the provided image styling -->
<div id="statusDropdownTemplate" class="cell-dropdown">
    <div class="dropdown-option" data-val="Present"><i class="bi bi-check-lg text-success"></i> Present</div>
    <div class="dropdown-option" data-val="Absent"><i class="bi bi-x-lg text-danger"></i> Absent</div>
    <div class="dropdown-option" data-val="Loss of Pay"><i class="bi bi-calendar-event" style="color: #f59e0b;"></i> Loss of Pay</div>
    <div class="dropdown-option" data-val="Compensatory Leave"><i class="bi bi-calendar-event" style="color: #f59e0b;"></i> Compensatory Leave</div>
    <div class="dropdown-option" data-val="Casual Leave"><i class="bi bi-calendar-event" style="color: #f59e0b;"></i> Casual Leave</div>
    <div class="dropdown-option" data-val="Sick Leave"><i class="bi bi-calendar-event" style="color: #f59e0b;"></i> Sick Leave</div>
    <div class="dropdown-option" data-val="Week Off"><i class="bi bi-calendar-event text-muted"></i> Week Off</div>
</div>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        
        // 1. Toast Notification Auto-Hide
        const toast = document.getElementById('successToast');
        if (toast) {
            setTimeout(() => {
                toast.style.animation = 'fadeOutRight 0.4s ease-in forwards';
                setTimeout(() => toast.remove(), 400); // Remove from DOM after animation
            }, 3000);
        }

        // 2. Single Date Picker Setup
        flatpickr("#targetDate", {
            dateFormat: "d M Y",
            allowInput: false
        });

        // 3. Search & Autocomplete Logic
        const searchInput = document.getElementById('employeeSearchInput');
        const dropdown = document.getElementById('autocompleteDropdown');
        const selectedContainer = document.getElementById('selectedEmployeesContainer');
        let debounceTimer;

        if (searchInput) {
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
                                    
                                    item.addEventListener('click', () => {
                                        addEmployeeCheckbox(emp.employee_code, emp.employee_name);
                                        searchInput.value = '';
                                        dropdown.style.display = 'none';
                                        searchInput.focus();
                                    });
                                    dropdown.appendChild(item);
                                });
                            } else {
                                dropdown.innerHTML = '<div class="autocomplete-item text-muted">No records found</div>';
                            }
                            dropdown.style.display = 'block';
                        })
                        .catch(error => console.error('Error fetching search:', error));
                }, 300);
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });
        }

        // Add employee to checkbox list
        function addEmployeeCheckbox(code, name) {
            if (selectedContainer.querySelector(`[data-code="${code}"]`)) {
                return; // Already exists
            }
            
            const div = document.createElement('div');
            div.className = 'employee-checkbox-item';
            div.setAttribute('data-code', code);
            
            div.innerHTML = `
                <input type="checkbox" name="employees[]" value="${code}" checked>
                <span>${name} - ${code}</span>
            `;
            selectedContainer.appendChild(div);
        }

        // 4. Arrow Navigation for Dates
        window.shiftDate = function(daysToAdd) {
            const dateInput = document.getElementById('targetDate');
            if (!dateInput.value) return;

            const currentObj = new Date(dateInput.value);
            currentObj.setDate(currentObj.getDate() + daysToAdd);
            
            const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            const d = String(currentObj.getDate()).padStart(2, '0');
            const m = months[currentObj.getMonth()];
            const y = currentObj.getFullYear();
            
            dateInput.value = `${d} ${m} ${y}`;
            
            // Auto submit filter form to load new dates
            document.getElementById('filterForm').submit();
        };

        // 5. In-Cell Hover Scroll Logic (Flushed to cell)
        const cells = document.querySelectorAll('.attendance-cell');
        const dropdownMenu = document.getElementById('statusDropdownTemplate');
        let activeCell = null;

        if (cells.length > 0 && dropdownMenu) {
            
            cells.forEach(cell => {
                cell.addEventListener('mouseenter', function() {
                    activeCell = this;
                    // Move the dropdown element inside the currently hovered table cell
                    this.appendChild(dropdownMenu);
                    dropdownMenu.classList.add('show');
                });
                
                cell.addEventListener('mouseleave', function() {
                    dropdownMenu.classList.remove('show');
                });
            });

            // Handle dropdown option click
            const options = dropdownMenu.querySelectorAll('.dropdown-option');
            options.forEach(opt => {
                opt.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (!activeCell) return;

                    const val = this.getAttribute('data-val');
                    const contentDiv = activeCell.querySelector('.cell-content');
                    const hiddenInput = activeCell.querySelector('.status-input');
                    
                    // Update visual representation based on selection
                    if (val === 'Present') {
                        contentDiv.innerHTML = '<i class="bi bi-check-lg text-success status-icon"></i>';
                    } else if (val === 'Absent') {
                        contentDiv.innerHTML = '<i class="bi bi-x-lg text-danger status-icon"></i>';
                    } else if (val === 'Week Off') {
                        contentDiv.innerHTML = '<span class="status-icon text-muted">WO</span>';
                    } else {
                        // Display text for leaves (Loss of Pay, Casual Leave, etc.)
                        contentDiv.innerHTML = `<span class="status-icon text-dark">${val}</span>`;
                    }
                    
                    // Update hidden input for POST request
                    hiddenInput.value = val;
                    dropdownMenu.classList.remove('show');
                });
            });
        }

        // 6. Mark All Present Logic
        const btnMarkAll = document.getElementById('btnMarkAllPresent');
        if (btnMarkAll) {
            btnMarkAll.addEventListener('click', function() {
                const allCells = document.querySelectorAll('.attendance-cell');
                allCells.forEach(cell => {
                    const hiddenInput = cell.querySelector('.status-input');
                    // Skip over Week Off unless you want to override it
                    if (hiddenInput.value !== 'WO' && hiddenInput.value !== 'Week Off') {
                        cell.querySelector('.cell-content').innerHTML = '<i class="bi bi-check-lg text-success status-icon"></i>';
                        hiddenInput.value = 'Present';
                    }
                });
            });
        }
        
    });
</script>