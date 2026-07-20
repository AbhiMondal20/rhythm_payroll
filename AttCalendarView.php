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
    echo json_encode($results);
    exit;
}

$page_title = 'Attendance - Calendar View';

$search_query = isset($_GET['employee']) ? trim($_GET['employee']) : '';
$is_searched = !empty($search_query);

// Handle Date Range Default (Last 7 days if empty)
$date_range = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';
if (empty($date_range)) {
    $end_date_obj = new DateTime();
    $start_date_obj = (clone $end_date_obj)->modify('-6 days');
    $date_range = $start_date_obj->format('d M Y') . ' to ' . $end_date_obj->format('d M Y');
}

// Generate Array of Dates for the Grid
$dates_header = [];
if (strpos($date_range, ' to ') !== false) {
    $dates = explode(' to ', $date_range);
    if (count($dates) == 2) {
        $start = new DateTime($dates[0]);
        $end = new DateTime($dates[1]);
        $end->modify('+1 day'); // Include end date in period
        
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        
        foreach ($period as $dt) {
            $dates_header[$dt->format("Y-m-d")] = $dt->format("d M, D");
        }
    }
}

$calendar_data = [];
$start_iso = !empty($dates_header) ? array_key_first($dates_header) : '';
$end_iso = !empty($dates_header) ? array_key_last($dates_header) : '';

if ($is_searched && isset($conn) && !empty($dates_header)) {
    $safe_search = mysqli_real_escape_string($conn, $search_query);
    $start_date_str = mysqli_real_escape_string($conn, $start_iso);
    $end_date_str = mysqli_real_escape_string($conn, $end_iso);
    
    // Find matching employees
    $emp_sql = "SELECT employee_code, employee_name, profile_photo FROM employees 
                WHERE employee_name LIKE '%$safe_search%' 
                OR employee_code = '$safe_search' 
                LIMIT 10"; 
    $emp_result = mysqli_query($conn, $emp_sql);
    
    $emp_codes = [];
    if ($emp_result && mysqli_num_rows($emp_result) > 0) {
        while ($emp = mysqli_fetch_assoc($emp_result)) {
            $emp_codes[] = "'" . mysqli_real_escape_string($conn, $emp['employee_code']) . "'";
            // Initialize their row in the calendar matrix
            $calendar_data[$emp['employee_code']] = [
                'details' => $emp,
                'attendance' => []
            ];
            // Pre-fill empty dates
            foreach($dates_header as $iso => $display) {
                $calendar_data[$emp['employee_code']]['attendance'][$iso] = ''; 
            }
        }
        
        $emp_in_clause = implode(',', $emp_codes);
        
        // Fetch Attendance records for those employees in the date range
        $time_sql = "SELECT employee_code, entry_date, day_status_1 
                     FROM time_entries 
                     WHERE employee_code IN ($emp_in_clause) 
                     AND entry_date BETWEEN '$start_date_str' AND '$end_date_str'";
                     
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
}

// Helper Function to Render Status Badges
function renderStatusBadge($statusCode) {
    if (empty($statusCode)) return '<span class="text-muted" style="font-size:12px;">-</span>';
    
    switch($statusCode) {
        case 'PP': 
        case 'Worked On Week Off (WW)': 
        case 'Worked On Holiday (HW)':
            return '<span class="badge-solid bg-pres">Present</span>';
        case 'AA': 
        case 'Loss Of Pay in Second Half (*LOP)':
        case 'Loss Of Pay in First Half (LOP*)':
            return '<span class="badge-solid bg-abs">Absent</span>';
        case 'WO': 
            return '<span class="badge-solid bg-wo">WeekOff</span>';
        case 'HO': 
            return '<span class="badge-solid bg-wo">Holiday</span>';
        
        // Half Day Scenarios (Split Badges)
        case 'P*A': 
        case 'WW*':
        case 'HW*':
            return '<div class="badge-split"><div class="badge-half bg-pres">Pres...</div><div class="badge-half bg-abs">Abs...</div></div>';
        case 'A*P': 
        case '*WW':
        case '*HW':
            return '<div class="badge-split"><div class="badge-half bg-abs">Abs...</div><div class="badge-half bg-pres">Pres...</div></div>';
            
        default: 
            return '<span class="badge-solid bg-wo">'.htmlspecialchars($statusCode).'</span>';
    }
}

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>    
    .flex-between { display: flex; justify-content: space-between; align-items: center; }
    .flex-center { display: flex; justify-content: center; align-items: center; }
    .flex-end { display: flex; justify-content: flex-end; align-items: center; }
    .d-flex { display: flex; align-items: center; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 16px; }
    .mb-1 { margin-bottom: 4px; }
    .mb-4 { margin-bottom: 24px; }
    .mt-2 { margin-top: 8px; }
    .mt-3 { margin-top: 16px; }
    
    .text-dark { color: #111827; }
    .text-muted { color: #6b7280; }
    .fw-bold { font-weight: 600; }
    .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    
    .card { background: #fff; border-radius: 8px; min-height: 450px; }

    .attendance-tabs { border-bottom: 1px solid #dee2e6; }
    .attendance-tabs a { color: #6c757d; text-decoration: none; padding: 3px 5px; gap: 12px; display: inline-block; font-size: 14px; transition: color 0.2s; }
    .attendance-tabs .separator { color: #D1D5DB; font-size: 14px; }
    .attendance-tabs a:hover { color: #495057; }
    .attendance-tabs a.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; font-weight: 500; }
    
    .filter-bar { display: flex; gap: 15px; align-items: center; margin-bottom: 25px; }
    .search-wrapper { position: relative; width: 280px; }
    .search-wrapper .form-control { padding-left: 35px; }
    .search-wrapper .bi-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px; }
    
    .search-chip { position: absolute; top: 4px; left: 4px; bottom: 4px; background-color: #f3f4f6; border-radius: 3px; display: flex; align-items: center; padding: 0 8px; font-size: 13px; color: #111827; border: 1px solid #e5e7eb; z-index: 5; }
    .search-chip a { color: #6b7280; margin-left: 6px; text-decoration: none; font-size: 16px; display: flex; align-items: center; }
    .search-chip a:hover { color: #ef4444; }

    .autocomplete-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #d1d5db; border-top: none; border-radius: 0 0 4px 4px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); z-index: 1000; max-height: 250px; overflow-y: auto; display: none; margin-top: 2px; }
    .autocomplete-item { padding: 10px 14px; cursor: pointer; font-size: 13.5px; border-bottom: 1px solid #f3f4f6; display: flex; flex-direction: column; }
    .autocomplete-item:hover { background-color: #eff6ff; }
    .autocomplete-name { font-weight: 600; color: #111827; }
    .autocomplete-code { font-size: 11px; color: #6b7280; margin-top: 2px; }

    .form-control, .form-select { width: 100%; border-radius: 4px; font-size: 13.5px; border: 1px solid #d1d5db; height: 36px; padding: 6px 12px; background-color: #fff; outline: none; }
    .form-control:focus { border-color: #0d6efd; }
    .form-control:disabled { background-color: #f9fafb; cursor: not-allowed; }
    
    .date-dropdown { display: flex; align-items: center; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; height: 36px; width: 260px; padding: 0 12px; gap: 8px;}
    .date-dropdown .bi-calendar { color: #6b7280; border-right: 1px solid #d1d5db; padding-right: 8px; }
    .date-dropdown input { border: none; background: transparent; flex: 1; outline: none; font-size: 13.5px; color: #4b5563; }

    .btn-apply { background-color: #0d6efd; color: #fff; height: 36px; padding: 0 20px; font-size: 13.5px; border: none; border-radius: 4px; cursor: pointer; }
    .btn-apply:hover { background-color: #0b5ed7; }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state h5 { font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 20px; }

    /* Calendar Grid Specific Styles */
    .table-container { border-radius: 6px; overflow-x: auto; width: 100%; border: 1px solid #e5e7eb; }
    .table-calendar { width: 100%; border-collapse: collapse; text-align: center; white-space: nowrap; }
    .table-calendar th { background-color: #f8f9fa; font-weight: 600; font-size: 12.5px; color: #111827; border-bottom: 1px solid #e5e7eb; padding: 14px 12px; }
    .table-calendar td { vertical-align: middle; padding: 14px 12px; border-bottom: 1px solid #f3f4f6; }
    .table-calendar th:first-child, .table-calendar td:first-child { text-align: left; position: sticky; left: 0; background: #fff; border-right: 1px solid #f3f4f6; box-shadow: 2px 0 4px -2px rgba(0,0,0,0.05); }
    .table-calendar th:first-child { background: #f8f9fa; z-index: 10; }
    
    .emp-row-info { display: flex; align-items: center; gap: 12px; }
    .emp-mini-avatar { width: 32px; height: 32px; background: #e5e7eb; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    .emp-mini-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .emp-mini-avatar i { font-size: 20px; color: #9ca3af; }
    .emp-name-text { font-size: 13.5px; font-weight: 500; color: #374151; max-width: 150px; overflow: hidden; text-overflow: ellipsis; }

    /* Pill Badges matching Image */
    .badge-solid { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; min-width: 75px; text-align: center; }
    .bg-pres { background-color: #e6f4ea; color: #1e8e3e; }
    .bg-abs { background-color: #fce8e6; color: #d93025; }
    .bg-wo { background-color: #f1f3f4; color: #5f6368; }

    /* Split Badges matching Image */
    .badge-split { display: inline-flex; border-radius: 20px; font-size: 12px; font-weight: 500; min-width: 85px; overflow: hidden; border: 1px solid #f3f4f6; }
    .badge-half { padding: 4px 6px; flex: 1; text-align: center; }
    .badge-split .bg-pres { background-color: #e6f4ea; border-right: 1px solid rgba(255,255,255,0.5); }
    .badge-split .bg-abs { background-color: #fce8e6; }
    
    .nav-arrow { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: #fff; border: 1px solid #d1d5db; border-radius: 50%; cursor: pointer; color: #4b5563; }
    .nav-arrow:hover { background: #f3f4f6; }
</style>

<div class="container">    
    <div class="flex-between mb-1">
        <h4 class="text-dark fw-bold m-0" style="font-size: 1.25rem;">Attendance</h4>
        <div class="attendance-tabs m-0 border-0">
            <a href="attendance">Time Entries</a>
            <span class="separator">|</span>
            <a href="AttCalendarView" class="active">Calendar View</a>
            <span class="separator">|</span>
            <a href="ManualAttendance">Manual Attendance</a>
            <span class="separator">|</span>
            <a href="AttDiscrepancies">Discrepancies</a>
            <span class="separator">|</span>
            <a href="AttProcessTimeCard">Process Time Card</a>
            <span class="separator">|</span>
            <a href="AttApproveOvertime">Approve Overtime</a>
        </div>
    </div>

    <div class="card shadow-sm mt-2">
        <div class="card-body p-4">
            <h6 class="text-dark fw-bold mb-4" style="font-size: 13.5px; letter-spacing: 0.5px; margin-top: 0;">CALENDAR VIEW</h6>            
            <form method="GET" action="" class="filter-bar" id="filterForm">
                <div class="search-wrapper">
                    <?php if ($is_searched): ?>
                        <div class="search-chip">
                            <?= htmlspecialchars($search_query) ?> 
                            <a href="?"><i class="bi bi-x"></i></a>
                        </div>
                        <input type="hidden" name="employee" value="<?= htmlspecialchars($search_query) ?>">
                        <input type="text" class="form-control" disabled>
                    <?php else: ?>
                        <i class="bi bi-search"></i>
                        <input type="text" id="employeeSearchInput" class="form-control" placeholder="Search by name or #code" autocomplete="off">
                        <input type="hidden" id="employeeSearchHidden" name="employee" value="">
                        <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
                    <?php endif; ?>
                </div>
                
                <div class="date-dropdown">
                    <i class="bi bi-calendar"></i>
                    <input type="text" name="date_range" id="dateRange" placeholder="Select Date Range" value="<?= htmlspecialchars($date_range) ?>">
                </div>                
                <button type="submit" class="btn-apply">Apply</button>
            </form>

            <?php if (!$is_searched): ?>
                
                <div class="empty-state">
                    <h5>Search an employee to view their weekly calendar</h5>
                </div>

            <?php else: ?>
                
                <?php if (empty($calendar_data)): ?>
                    <div class="empty-state">
                        <h5 class="text-muted">No records found for <?= htmlspecialchars($search_query) ?></h5>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table-calendar">
                            <thead>
                                <tr>
                                    <th style="min-width: 220px; padding-left: 20px;">Employee Name</th>
                                    <?php 
                                    $col_count = count($dates_header);
                                    $current = 1;
                                    foreach($dates_header as $iso => $display_date): ?>
                                        <th>
                                            <?php if($current == 1): ?>
                                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                                    <span class="nav-arrow" onclick="shiftDateRange('<?= $start_iso ?>', '<?= $end_iso ?>', -7)"><i class="bi bi-chevron-left"></i></span>
                                                    <span><?= htmlspecialchars($display_date) ?></span>
                                                    <span style="width:24px;"></span>
                                                </div>
                                            <?php elseif($current == $col_count): ?>
                                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                                    <span style="width:24px;"></span>
                                                    <span><?= htmlspecialchars($display_date) ?></span>
                                                    <span class="nav-arrow" onclick="shiftDateRange('<?= $start_iso ?>', '<?= $end_iso ?>', 7)"><i class="bi bi-chevron-right"></i></span>
                                                </div>
                                            <?php else: ?>
                                                <?= htmlspecialchars($display_date) ?>
                                            <?php endif; ?>
                                        </th>
                                    <?php $current++; endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($calendar_data as $emp_id => $data): ?>
                                    <tr>
                                        <td style="padding-left: 20px;">
                                            <div class="emp-row-info">
                                                <div class="emp-mini-avatar">
                                                    <?php if(!empty($data['details']['profile_photo'])): ?>
                                                        <img src="<?= htmlspecialchars($data['details']['profile_photo']) ?>" alt="">
                                                    <?php else: ?>
                                                        <i class="bi bi-person-fill"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="emp-name-text"><?= htmlspecialchars($data['details']['employee_name']) ?></span>
                                            </div>
                                        </td>
                                        
                                        <?php foreach($dates_header as $iso => $display_date): 
                                            $status = $data['attendance'][$iso];
                                        ?>
                                            <td>
                                                <?= renderStatusBadge($status) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Search Autocomplete Logic
    const searchInput = document.getElementById('employeeSearchInput');
    const searchHidden = document.getElementById('employeeSearchHidden');
    const dropdown = document.getElementById('autocompleteDropdown');
    const filterForm = document.getElementById('filterForm');

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
                                item.addEventListener('click', () => {
                                    searchInput.value = emp.employee_name; 
                                    searchHidden.value = emp.employee_name; 
                                    dropdown.style.display = 'none';
                                    filterForm.submit();
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

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    // Flatpickr Initialization
    const dateRangeInput = document.getElementById("dateRange");
    if (dateRangeInput) {
        flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "d M Y",
            monthSelectorType: "static",
            animate: true,
            showMonths: 2
        });
    }

    // Arrow Navigation Logic
    function shiftDateRange(startIsoStr, endIsoStr, daysToShift) {
        if (!startIsoStr || !endIsoStr) return;

        let startDate = new Date(startIsoStr);
        let endDate = new Date(endIsoStr);

        // Add/Subtract the days
        startDate.setDate(startDate.getDate() + daysToShift);
        endDate.setDate(endDate.getDate() + daysToShift);

        // Helper to format date perfectly to "01 Jul 2026" standard
        function formatDateToDMY(dt) {
            const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            let d = String(dt.getDate()).padStart(2, '0');
            let m = months[dt.getMonth()];
            let y = dt.getFullYear();
            return `${d} ${m} ${y}`;
        }

        const newRangeString = formatDateToDMY(startDate) + ' to ' + formatDateToDMY(endDate);
        
        // Update input and submit
        document.getElementById('dateRange').value = newRangeString;
        document.getElementById('filterForm').submit();
    }
</script>
<script src="includes/assets/scripts.js"></script>