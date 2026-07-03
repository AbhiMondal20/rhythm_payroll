<?php
require_once 'includes/config.php';
$page_title = 'Attendance - Time Entries';

// Check if an employee search was submitted
$search_query = isset($_GET['employee']) ? trim($_GET['employee']) : '';
$is_searched = !empty($search_query);
$time_entries = [];

// Implement mysqli_query for database search
if ($is_searched && isset($conn)) {
    $safe_search = mysqli_real_escape_string($conn, $search_query);
    // Search by employee name or status code
    $sql = "SELECT * FROM time_entries 
            WHERE employee_name LIKE '%$safe_search%' 
            OR status_code LIKE '%$safe_search%' 
            ORDER BY entry_date ASC";
            
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $time_entries[] = $row;
        }
    }
}

ob_start();
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="includes/assets/style.css">

<style>
    /* Reset & Base Custom Styles (Replacing Bootstrap) */
   
    .container { padding: 16px 24px; max-width: 100%; margin: 0 auto; }
    
    /* Utility Classes */
    .flex-between { display: flex; justify-content: space-between; align-items: center; }
    .flex-center { display: flex; justify-content: center; align-items: center; }
    .flex-end { display: flex; justify-content: flex-end; align-items: center; }
    .d-flex { display: flex; align-items: center; }
    .gap-2 { gap: 8px; }
    .gap-3 { gap: 16px; }
    .mb-1 { margin-bottom: 4px; }
    .mb-3 { margin-bottom: 16px; }
    .mb-4 { margin-bottom: 24px; }
    .mt-2 { margin-top: 8px; }
    .mt-3 { margin-top: 16px; }
    .mt-4 { margin-top: 24px; }
    .mt-5 { margin-top: 32px; }
    .p-0 { padding: 0 !important; }
    .p-4 { padding: 24px; }
    .ps-4 { padding-left: 24px; }
    .pe-4 { padding-right: 24px; }
    .text-dark { color: #111827; }
    .text-muted { color: #6b7280; }
    .text-end { text-align: right; }
    .fw-bold { font-weight: 600; }
    .w-100 { width: 100%; }
    .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    .border { border: 1px solid #e5e7eb; }
    .border-0 { border: none !important; }
    .rounded-1 { border-radius: 4px; }
    .rounded-3 { border-radius: 8px; }
    .bg-white { background-color: #ffffff; }

    /* Custom Grid System */
    .grid-row { display: flex; flex-wrap: wrap; margin-left: -12px; margin-right: -12px; }
    .grid-col-3 { width: 25%; padding: 0 12px; flex: 0 0 auto; }
    .grid-col-6 { width: 50%; padding: 0 12px; flex: 0 0 auto; }

    /* Card */
    .card { background: #fff; border-radius: 8px; min-height: 450px; }

    /* Tabs */
    .attendance-tabs { border-bottom: 1px solid #dee2e6; margin-bottom: 10px; }
    .attendance-tabs a { color: #6c757d; text-decoration: none; padding: 10px 15px; display: inline-block; font-size: 14px; transition: color 0.2s; }
    .attendance-tabs a:hover { color: #495057; }
    .attendance-tabs a.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; font-weight: 500; }
    
    /* Search & Filter Bar */
    .filter-bar { display: flex; gap: 15px; align-items: center; margin-bottom: 25px; }
    .search-wrapper { position: relative; width: 280px; }
    .search-wrapper .form-control { padding-left: 35px; }
    .search-wrapper .bi-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px; }
    
    .search-chip { position: absolute; top: 4px; left: 4px; bottom: 4px; background-color: #f3f4f6; border-radius: 3px; display: flex; align-items: center; padding: 0 8px; font-size: 13px; color: #111827; border: 1px solid #e5e7eb; z-index: 5; }
    .search-chip a { color: #6b7280; margin-left: 6px; text-decoration: none; font-size: 16px; display: flex; align-items: center; }
    .search-chip a:hover { color: #ef4444; }

    /* Forms & Inputs */
    .form-control, .form-select {
        width: 100%;
        border-radius: 4px;
        font-size: 13.5px;
        border: 1px solid #d1d5db;
        height: 36px;
        padding: 6px 12px;
        background-color: #fff;
        outline: none;
    }
    .form-control:focus, .form-select:focus { border-color: #0d6efd; }
    
    .date-dropdown { display: flex; align-items: center; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; height: 36px; width: 260px; padding: 0 12px; gap: 8px;}
    .date-dropdown .bi-calendar { color: #6b7280; border-right: 1px solid #d1d5db; padding-right: 8px; }
    .date-dropdown input { border: none; background: transparent; flex: 1; outline: none; font-size: 13.5px; color: #4b5563; }

    /* Buttons */
    .btn { display: inline-flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid transparent; border-radius: 4px; font-size: 13px; font-weight: 500; height: 32px; padding: 0 16px; transition: all 0.2s; }
    .btn-apply { background-color: #0d6efd; color: #fff; height: 36px; padding: 0 20px; font-size: 13.5px; }
    .btn-apply:hover, .btn-primary:hover { background-color: #0b5ed7; color: #fff; }
    .btn-primary { background: #0d6efd; color: #fff; }
    .btn-outline-primary { border-color: #0d6efd; color: #0d6efd; background: transparent; }
    .btn-outline-primary:hover { background: #eff6ff; color: #0b5ed7; }
    .btn-outline-danger { border-color: #ef4444; color: #ef4444; background: transparent; }
    .btn-outline-danger:hover { background: #fef2f2; color: #dc2626; border-color: #dc2626; }

    /* Empty State */
    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state h5 { font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 20px; }
    .empty-state-svg { max-width: 300px; height: auto; opacity: 0.9; }

    /* Badges */
    .status-badge { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; font-size: 12px; font-weight: 600; margin-right: 2px; }
    .status-p { background-color: #e6f4ea; color: #1e8e3e; border: 1px solid #ceead6; }
    .status-a { background-color: #fce8e6; color: #d93025; border: 1px solid #fad2cf; }
    .system-badge { background-color: #e8f0fe; color: #1967d2; border-radius: 20px; padding: 3px 25px; border: 1px solid #d2e3fc; font-size: 12.5px; font-weight: 500; }
    
    /* Table Styles */
    .table-container { border-radius: 6px; overflow: hidden; width: 100%; border: 1px solid #e5e7eb; }
    .table { width: 100%; border-collapse: collapse; text-align: left; }
    .table th { background-color: #f8f9fa; font-weight: 600; font-size: 12px; color: #4b5563; border-bottom: 1px solid #e5e7eb; padding: 12px 16px; }
    .table td { vertical-align: middle; font-size: 13.5px; color: #111827; padding: 14px 16px; border-bottom: 1px solid #f3f4f6; }
    .table tbody tr:hover { background-color: #f9fafb; }
    
    /* Expandable Row Edit Form */
    .expandable-row { background-color: #fff; display: none; border-bottom: 1px solid #e5e7eb;}
    .expandable-row.show { display: table-row; }
    .edit-form-container { padding: 24px 30px; background-color: #fff; border-top: 1px solid #e5e7eb; }
    .edit-form-container .form-label { display: block; font-size: 12px; color: #4b5563; font-weight: 500; margin-bottom: 4px; }
    .edit-form-container .form-control, .edit-form-container .form-select { 
        border: none; border-bottom: 1px solid #d1d5db; border-radius: 0; padding: 4px 0 8px 0; background: transparent; box-shadow: none; height: auto;
    }
    .edit-form-container .form-control:focus, .edit-form-container .form-select:focus { border-bottom-color: #0d6efd; }
    
    .input-icon-wrapper { position: relative; }
    .input-icon-wrapper .bi-calendar { position: absolute; right: 0; top: 50%; transform: translateY(-50%); color: #0d6efd; cursor: pointer; }

    /* Custom Form Checkbox */
    .form-check { display: flex; align-items: center; gap: 8px; }
    .form-check-input { width: 16px; height: 16px; cursor: pointer; margin: 0; }
    .form-check-label { font-size: 13.5px; color: #111827; cursor: pointer; }
    
    /* Pagination */
    .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-size: 13px; color: #6b7280; }
    .pagination-btn-group { display: flex; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); border-radius: 4px; overflow: hidden; }
    .pagination-btn-group button { border: 1px solid #e5e7eb; background: #fff; padding: 6px 12px; cursor: pointer; color: #6b7280; outline: none; margin-left: -1px; }
    .pagination-btn-group button.active { background: #0d6efd; color: #fff; border-color: #0d6efd; z-index: 2; }
    .pagination-btn-group button:disabled { background: #f9fafb; color: #d1d5db; cursor: not-allowed; }

    @media (max-width: 768px) {
        .flatpickr-calendar.multiMonth { width: 100% !important; }
        .flatpickr-calendar .flatpickr-months { flex-wrap: wrap; }
        .grid-col-3, .grid-col-6 { width: 100%; margin-bottom: 16px; }
    }
</style>

<div class="container py-3">
    
    <div class="flex-between mb-1">
        <h4 class="text-dark fw-bold m-0" style="font-size: 1.25rem;">Attendance</h4>
        <div class="attendance-tabs m-0 border-0">
            <a href="TimeEntries" class="active">Time Entries</a>
            <a href="CalendarView">Calendar View</a>
            <a href="ManualAttendance">Manual Attendance</a>
            <a href="Discrepancies">Discrepancies</a>
            <a href="ProcessTimeCard">Process Time Card</a>
            <a href="ApproveOvertime">Approve Overtime</a>
        </div>
    </div>

    <div class="card shadow-sm mt-2">
        <div class="card-body p-4">
            <h6 class="text-dark fw-bold mb-4" style="font-size: 13.5px; letter-spacing: 0.5px; margin-top: 0; text-transform: uppercase;">TIME ENTRIES</h6>
            
            <form method="GET" action="" class="filter-bar">
                <div class="search-wrapper">
                    <?php if ($is_searched): ?>
                        <div class="search-chip">
                            <?= htmlspecialchars($search_query) ?> 
                            <a href="?"><i class="bi bi-x"></i></a>
                        </div>
                        <input type="hidden" name="employee" value="<?= htmlspecialchars($search_query) ?>">
                        <input type="text" class="form-control" disabled style="background-color: #fff;">
                    <?php else: ?>
                        <i class="bi bi-search"></i>
                        <input type="text" name="employee" class="form-control" placeholder="Search by name or #code">
                    <?php endif; ?>
                </div>
                
                <div class="date-dropdown">
                    <i class="bi bi-calendar"></i>
                    <input type="text" id="dateRange" placeholder="Select Date Range">
                </div>                
                <button type="submit" class="btn btn-apply">Apply</button>
            </form>

            <?php if (!$is_searched): ?>
                
                <div class="empty-state">
                    <h5>Search employees to edit their time entries</h5>
                    <svg class="empty-state-svg mt-3" viewBox="0 0 400 250" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="50" y="40" width="220" height="150" rx="4" fill="#F3F4F6"/>
                        <rect x="50" y="40" width="220" height="15" rx="4" fill="#1F2937"/>
                        <circle cx="60" cy="47.5" r="2.5" fill="#EF4444"/>
                        <circle cx="70" cy="47.5" r="2.5" fill="#F59E0B"/>
                        <circle cx="80" cy="47.5" r="2.5" fill="#10B981"/>
                        <rect x="65" y="70" width="190" height="45" rx="2" fill="#FFFFFF"/>
                        <circle cx="85" cy="92.5" r="12.5" fill="#F59E0B"/>
                        <rect x="110" y="85" width="80" height="4" rx="2" fill="#E5E7EB"/>
                        <rect x="110" y="95" width="50" height="4" rx="2" fill="#E5E7EB"/>
                        <rect x="65" y="125" width="190" height="45" rx="2" fill="#FFFFFF"/>
                        <circle cx="85" cy="147.5" r="12.5" fill="#3B82F6"/>
                        <path d="M85 140V155M77.5 147.5H92.5" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round"/>
                        <rect x="110" y="140" width="80" height="4" rx="2" fill="#E5E7EB"/>
                        <rect x="110" y="150" width="50" height="4" rx="2" fill="#E5E7EB"/>
                        <g transform="translate(230, 110)">
                            <circle cx="45" cy="25" r="15" fill="#10B981" fill-opacity="0.2"/>
                            <circle cx="45" cy="25" r="6" fill="#10B981"/>
                            <path d="M35 45 C35 38 55 38 55 45" stroke="#10B981" stroke-width="4" stroke-linecap="round"/>
                            <path d="M70 85L70 45C70 40 85 40 85 45L85 85" fill="#374151"/>
                            <path d="M72 85H67" stroke="#111827" stroke-width="2"/>
                            <path d="M87 85H82" stroke="#111827" stroke-width="2"/>
                            <path d="M75 10C75 5 82 5 82 10V20C82 25 75 25 75 20V10Z" fill="#FCD34D"/>
                            <path d="M65 25C65 15 90 15 90 25V45L65 45V25Z" fill="#4B5563"/>
                            <circle cx="77" cy="8" r="6" fill="#1F2937"/>
                        </g>
                        <line x1="250" y1="195" x2="330" y2="195" stroke="#E5E7EB" stroke-width="2"/>
                    </svg>
                </div>

            <?php else: ?>

                <div class="table-container border">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="ps-4">Date And Day</th>
                                <th>Day Status</th>
                                <th>Shift Name</th>
                                <th>In - Out</th>
                                <th>Hours Worked</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($time_entries)): ?>
                                <?php foreach ($time_entries as $index => $row): 
                                    // Basic formatting logic. Adapt based on your DB columns
                                    $dateFormatted = date('d M, D', strtotime($row['entry_date']));
                                    $status1Class = ($row['day_status_1'] == 'P') ? 'status-p' : 'status-a';
                                    $status2Class = ($row['day_status_2'] == 'P') ? 'status-p' : 'status-a';
                                    $rowId = "row-" . $index . "-details";
                                ?>
                                    <tr>
                                        <td class="ps-4"><?= htmlspecialchars($dateFormatted) ?></td>
                                        <td>
                                            <span class="status-badge <?= $status1Class ?>"><?= htmlspecialchars($row['day_status_1']) ?></span> 
                                            <span class="status-badge <?= $status2Class ?>"><?= htmlspecialchars($row['day_status_2']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['shift_name']) ?></td>
                                        <td>
                                            <?= htmlspecialchars(date('h:i A', strtotime($row['check_in_time']))) ?> - 
                                            <?= htmlspecialchars(date('h:i A', strtotime($row['check_out_time']))) ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['hours_worked']) ?></td>
                                        <td><span class="system-badge"><?= htmlspecialchars($row['record_status']) ?></span></td>
                                        <td class="text-end pe-4"><i class="bi bi-chevron-down text-muted" style="cursor:pointer;" onclick="toggleRow('<?= $rowId ?>', this)"></i></td>
                                    </tr>
                                    
                                    <tr id="<?= $rowId ?>" class="expandable-row">
                                        <td colspan="7" class="p-0 border-0">
                                            <div class="edit-form-container">
                                                <div class="grid-row mb-4">
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Day Status</label>
                                                        <select class="form-select">
                                                            <option <?= ($row['day_status_1'] == 'P') ? 'selected' : '' ?>>Present (PP)</option>
                                                            <option <?= ($row['day_status_1'] == 'A') ? 'selected' : '' ?>>Absent (AA)</option>
                                                        </select>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Check In Time</label>
                                                        <div class="input-icon-wrapper">
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($row['check_in_time']) ?>">
                                                            <i class="bi bi-calendar"></i>
                                                        </div>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Check Out Time</label>
                                                        <div class="input-icon-wrapper">
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($row['check_out_time']) ?>">
                                                            <i class="bi bi-calendar"></i>
                                                        </div>
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Over Time Hours</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['over_time_hours']) ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="grid-row mb-4">
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Under Time Hours</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['under_time_hours']) ?>">
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Normal Hours</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['normal_hours']) ?>">
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Late Hours</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['late_hours']) ?>">
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Early Hours</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['early_hours']) ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="grid-row mb-3 flex-end" style="align-items: flex-end;">
                                                    <div class="grid-col-3">
                                                        <label class="form-label">Status</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['status_code']) ?>">
                                                    </div>
                                                    <div class="grid-col-6">
                                                        <label class="form-label">Remarks</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['remarks']) ?>">
                                                    </div>
                                                    <div class="grid-col-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="calcInOut_<?= $index ?>" <?= $row['calculate_per_in_out'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="calcInOut_<?= $index ?>">Calculate as per In/Out time</label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex-end gap-2 mt-5">
                                                    <button class="btn btn-outline-danger">Delete</button>
                                                    <button class="btn btn-outline-primary" onclick="toggleRow('<?= $rowId ?>', this.closest('.expandable-row').previousElementSibling.querySelector('i'))">Cancel</button>
                                                    <button class="btn btn-primary">Save</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px;">No entries found for this search.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    <div>
                        Showing 1 to <?= count($time_entries) ?> of <?= count($time_entries) ?> entries 
                        <span class="border rounded-1 bg-white" style="margin-left:16px; padding: 4px 8px;">
                            Show 
                            <select style="border:none; outline:none; background:transparent;">
                                <option>25</option>
                            </select>
                            entries
                        </span>
                    </div>
                    <div class="pagination-btn-group">
                        <button disabled>&laquo;</button>
                        <button disabled>&lsaquo;</button>
                        <button class="active">1</button>
                        <button disabled>&rsaquo;</button>
                        <button disabled>&raquo;</button>
                    </div>
                </div>

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
    function toggleRow(rowId, iconElement) {
        const row = document.getElementById(rowId);
        if (row.classList.contains('show')) {
            row.classList.remove('show');
            if (iconElement && iconElement.classList) {
                iconElement.classList.replace('bi-chevron-up', 'bi-chevron-down');
                iconElement.classList.replace('text-dark', 'text-muted');
            }
        } else {
            document.querySelectorAll('.expandable-row.show').forEach(openRow => {
                openRow.classList.remove('show');
                let prevRowIcon = openRow.previousElementSibling.querySelector('i');
                if (prevRowIcon) {
                    prevRowIcon.classList.replace('bi-chevron-up', 'bi-chevron-down');
                    prevRowIcon.classList.replace('text-dark', 'text-muted');
                }
            });

            row.classList.add('show');
            if (iconElement && iconElement.classList) {
                iconElement.classList.replace('bi-chevron-down', 'bi-chevron-up');
                iconElement.classList.replace('text-muted', 'text-dark');
            }
        }
    }

    // Flatpickr initialization
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

    flatpickr("#dateRange", {
        mode: "range",
        dateFormat: "d M Y",
        defaultDate: [firstDay, lastDay],
        showMonths: 2,
        monthSelectorType: "static",
        animate: true
    });
</script>
<script src="includes/assets/scripts.js"></script>