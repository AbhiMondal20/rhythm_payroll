<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Day Status Report - View';

// 1. Determine Date Range from the GET request URL dynamically built from 'day status' page
$start_date_str = isset($_GET['start']) ? $_GET['start'] : '2026-07-01';
$end_date_str   = isset($_GET['end']) ? $_GET['end'] : '2026-07-31';

$start_time = strtotime($start_date_str);
$end_time   = strtotime($end_date_str);

// 2. Generate the days array for the table headers and logic
$days = [];
$current_time = $start_time;
while ($current_time <= $end_time) {
    $days[] = [
        'day_name'  => date('D', $current_time),
        'date_str'  => date('d/m', $current_time),
        'full_date' => date('Y-m-d', $current_time)
    ];
    $current_time = strtotime('+1 day', $current_time);
}

// 3. Setup Filter for Employees (if generated with selected employees)
$emp_filter = '';
if (!empty($_GET['emps'])) {
    $emps_arr = explode(',', $_GET['emps']);
    $emps_escaped = array_map(function($e) use ($conn) { 
        return "'" . mysqli_real_escape_string($conn, $e) . "'"; 
    }, $emps_arr);
    $emp_filter = " AND `employee_code` IN (" . implode(',', $emps_escaped) . ")";
}

// 4. Fetch the Data from 'time_entries' table
$all_report_data = [];
$daily_summary = [];

// Initialize array structures for aggregate counts
foreach ($days as $d) {
    $daily_summary[$d['full_date']] = ['reported' => 0, 'not_reported' => 0];
}

$sql = "SELECT `employee_code`, `employee_name`, `entry_date`, `day_status_1` 
        FROM `time_entries` 
        WHERE `entry_date` BETWEEN '" . mysqli_real_escape_string($conn, $start_date_str) . "' 
        AND '" . mysqli_real_escape_string($conn, $end_date_str) . "' 
        $emp_filter 
        ORDER BY `employee_code` ASC, `entry_date` ASC";

$result = @mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $emp_code = $row['employee_code'];
        $date_val = $row['entry_date'];
        $status   = trim($row['day_status_1']);

        if (!isset($all_report_data[$emp_code])) {
            $all_report_data[$emp_code] = [
                'name'       => $row['employee_name'],
                'dept'       => 'OEX - Operations', // Mock defaults
                'loc'        => 'SILIGURI - SILIGURI', 
                'paid_days'  => 0,
                'attendance' => []
            ];
            
            // Adjust mock labels based on the video output examples
            if($emp_code == '1009') $all_report_data[$emp_code]['dept'] = 'MED - Medicine';
            if($emp_code == '1013') $all_report_data[$emp_code]['dept'] = 'NUR - NURSING';
        }

        // Store daily status
        $all_report_data[$emp_code]['attendance'][$date_val] = $status;

        // Paid Days logic
        if (in_array($status, ['PP', 'WO', 'PH', 'SL', 'CL', 'EL', 'Co'])) {
            $all_report_data[$emp_code]['paid_days'] += 1;
        } elseif (in_array($status, ['HD'])) { 
            $all_report_data[$emp_code]['paid_days'] += 0.5;
        }

        // Generate Aggregate Row (Reported - Not Reported counters per day)
        if (isset($daily_summary[$date_val])) {
            if (in_array($status, ['AA', 'A', 'LWP', 'CL'])) {
                $daily_summary[$date_val]['not_reported']++;
            } elseif (!empty($status)) {
                $daily_summary[$date_val]['reported']++;
            }
        }
    }
}

// 5. Pagination Logic
$records_per_page = 25; 
$total_records = count($all_report_data);
$total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages) $current_page = $total_pages;

$offset = ($current_page - 1) * $records_per_page;
$report_data = array_slice($all_report_data, $offset, $records_per_page, true);

// Formatting string outputs
$display_from = date('M d, Y', $start_time);
$display_to   = date('M d, Y', $end_time);
$display_sel  = date('d/m/Y', $start_time) . ' - ' . date('d/m/Y', $end_time);
$gen_date     = date('M d, Y h:i:s A');

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
    /* Reset & Base Styles */
    .report-ng-container { font-family: Arial, sans-serif; font-size: 13px; color: #333; background: #fff; padding: 20px; min-height: 100vh; }
    
    /* Top Header Section */
    .rep-header-top { margin-bottom: 15px; }
    .rep-header-top h1 { font-size: 18px; font-weight: bold; color: #000; margin: 0 0 10px 0; }
    .rep-meta-info { display: flex; justify-content: space-between; color: #666; font-size: 12px; }

    /* SSRS-style Toolbar */
    .ssrs-toolbar { background-color: #e9e9d8; border: 1px solid #ccc; padding: 4px 10px; display: flex; align-items: center; gap: 15px; font-size: 12px; margin-bottom: 20px; }
    .ssrs-toolbar-group { display: flex; align-items: center; gap: 5px; }
    
    .ssrs-icon { cursor: pointer; color: #555; font-weight: bold; text-decoration: none; padding: 2px 4px; font-size: 11px; }
    .ssrs-icon:hover:not(.disabled) { background: #d4d4c3; border-radius: 2px; }
    .ssrs-icon.disabled { color: #b0b0b0; cursor: default; }
    
    .ssrs-toolbar input[type="text"] { border: 1px solid #7a9ea5; height: 18px; padding: 0 4px; font-size: 11px; outline: none; }
    .ssrs-toolbar input[type="text"]:focus { border-color: #000; }
    .ssrs-page-input { width: 30px; text-align: right; }
    .ssrs-search-input { width: 120px; }
    .ssrs-toolbar-divider { width: 1px; height: 18px; background-color: #ccc; margin: 0 5px; }
    
    .ssrs-action-text { color: #000; cursor: pointer; padding: 2px 4px; }
    .ssrs-action-text:hover { text-decoration: underline; background: #d4d4c3; border-radius: 2px; }

    /* Export Dropdown Specifics */
    .export-dropdown-wrapper { position: relative; display: inline-block; cursor: pointer; }
    .export-dropdown-wrapper .ssrs-icon { display: flex; align-items: center; gap: 2px; color: #005500; font-size: 14px; }
    .export-menu {
        position: absolute; top: 100%; left: 0; margin-top: 2px;
        background-color: #f1ebd8; border: 1px solid #000;
        display: none; flex-direction: column; min-width: 90px;
        z-index: 100; box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
        padding: 2px;
    }
    .export-menu a {
        padding: 4px 15px 4px 8px; text-decoration: none; color: #0033cc;
        font-family: Arial, sans-serif; font-size: 12px;
        border: 1px solid transparent;
    }
    .export-menu a:hover { background-color: #dce3f6; border: 1px solid #84a2d8; }
    .show-flex { display: flex !important; }

    /* Inner Report Titles */
    .inner-report-title { color: #008299; font-size: 16px; font-weight: bold; margin: 0 0 10px 0; }
    .inner-selection-text { font-weight: bold; font-size: 12px; margin-bottom: 10px; }

    /* Data Table */
    .table-responsive { width: 100%; overflow-x: auto; border: 1px solid #ddd; scrollbar-width: thin; scrollbar-color: #a0a0a0 #f1f1f1; }
    .table-responsive::-webkit-scrollbar { height: 14px; }
    .table-responsive::-webkit-scrollbar-track { background: #f1f1f1; border-top: 1px solid #ddd; }
    .table-responsive::-webkit-scrollbar-thumb { background: #a0a0a0; border-radius: 10px; border: 2px solid #f1f1f1; }

    .report-table { width: 100%; border-collapse: collapse; white-space: nowrap; }
    .report-table th, .report-table td { border: 1px solid #c0c0c0; padding: 4px 6px; font-size: 11px; text-align: center; }
    
    .report-table th { background-color: #00a2d1; color: #ffffff; font-weight: normal; vertical-align: bottom; }
    .row-summary td { background-color: #00a2d1; color: #ffffff; }
    .row-data td { background-color: #ffffff; color: #000000; }
    
    .report-table td.text-left, .report-table th.text-left { text-align: left; }
    
    /* Search Highlight */
    .highlight-cell { background-color: #ffff99 !important; font-weight: bold; }

    /* =========================================
       STICKY FIRST COLUMN (Freeze Pane)
       ========================================= */
    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 2;
    }
    .report-table thead .sticky-col {
        background-color: #00a2d1;
        z-index: 3; /* Must be higher than body cells so it stays on top */
        border-right: 2px solid #007a9e; /* Darker border for separation */
    }
    .report-table tbody .row-summary .sticky-col {
        background-color: #00a2d1;
        z-index: 2;
        border-right: 2px solid #007a9e;
    }
    .report-table tbody .row-data .sticky-col {
        background-color: #ffffff;
        z-index: 2;
        border-right: 2px solid #a0a0a0;
    }
</style>

<div class="report-ng-container">
    
    <!-- Top Header -->
    <div class="rep-header-top">
        <h1>Day Status Report</h1>
        <div class="rep-meta-info">
            <span>Report from : <?= $display_from ?> - <?= $display_to ?></span>
            <span>Generated on : <?= $gen_date ?></span>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="ssrs-toolbar">
        <!-- Pagination Controls -->
        <div class="ssrs-toolbar-group">
            <span class="ssrs-icon <?= ($current_page <= 1) ? 'disabled' : '' ?>" title="First Page" onclick="goToPage(1)">|&lt;</span>
            <span class="ssrs-icon <?= ($current_page <= 1) ? 'disabled' : '' ?>" title="Previous Page" onclick="goToPage(<?= $current_page - 1 ?>)">&lt;</span>
            <input type="text" class="ssrs-page-input" id="pageInput" value="<?= $current_page ?>" onchange="goToPage(this.value)">
            <span>of <?= $total_pages ?></span>
            <span class="ssrs-icon <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>" title="Next Page" onclick="goToPage(<?= $current_page + 1 ?>)">&gt;</span>
            <span class="ssrs-icon <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>" title="Last Page" onclick="goToPage(<?= $total_pages ?>)">&gt;|</span>
        </div>
        
        <div class="ssrs-toolbar-divider"></div>
        
        <!-- Find/Search Controls -->
        <div class="ssrs-toolbar-group">
            <input type="text" class="ssrs-search-input" id="findInput" placeholder="" onkeyup="if(event.key === 'Enter') findInReport()">
            <span class="ssrs-action-text" onclick="findInReport()">Find</span> | 
            <span class="ssrs-action-text" onclick="findNextInReport()">Next</span>
        </div>

        <div class="ssrs-toolbar-divider"></div>

        <!-- Export & Refresh -->
        <div class="ssrs-toolbar-group">
            
            <div class="export-dropdown-wrapper">
                <span class="ssrs-icon" title="Export" onclick="toggleExportMenu(event)">
                    &#128190; <span style="font-size:10px; color:#333;">&#9660;</span>
                </span>
                <div class="export-menu" id="exportMenu">
                    <a href="#" onclick="exportReport('Excel', event)">Excel</a>
                    <a href="#" onclick="exportReport('PDF', event)">PDF</a>
                    <a href="#" onclick="exportReport('Word', event)">Word</a>
                </div>
            </div>

            <span class="ssrs-icon" style="color: #0055bb; font-size:14px;" title="Refresh" onclick="location.reload();">
                &#10227;
            </span>
        </div>
    </div>

    <!-- Report Body -->
    <h2 class="inner-report-title">Day Status Report</h2>
    <div class="inner-selection-text">Selection :- <?= $display_sel ?></div>

    <div class="table-responsive">
        <table class="report-table" id="mainReportTable">
            <thead>
                <tr>
                    <!-- Added .sticky-col class to freeze this column -->
                    <th class="text-left sticky-col" style="min-width: 200px;">Employee Code - Name</th>
                    <th class="text-left" style="min-width: 120px;">Dept Code-Name</th>
                    <th class="text-left" style="min-width: 120px;">Loc Code-<br>Name</th>
                    <th style="min-width: 40px;">Paid<br>Days</th>
                    
                    <?php foreach($days as $d): ?>
                        <th style="min-width: 35px;"><?= $d['day_name'] ?><br><?= $d['date_str'] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <!-- Aggregate Report - Not Reported Row -->
                <tr class="row-summary">
                    <!-- Added .sticky-col class here -->
                    <td class="text-left sticky-col">Reported - Not Reported</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <?php foreach($days as $d): 
                        $rep = $daily_summary[$d['full_date']]['reported'];
                        $not_rep = $daily_summary[$d['full_date']]['not_reported'];
                    ?>
                        <td><?= $rep ?> - <?= $not_rep ?></td>
                    <?php endforeach; ?>
                </tr>

                <!-- Data Rows Generated -->
                <?php if (empty($report_data)): ?>
                    <tr class="row-data">
                        <td colspan="<?= count($days) + 4 ?>" style="padding:15px; color:#666;">No data available for the selected date range.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($report_data as $emp_code => $data): ?>
                        <tr class="row-data">
                            <!-- Added .sticky-col class here -->
                            <td class="text-left sticky-col"><?= htmlspecialchars($emp_code) ?> - <?= htmlspecialchars($data['name']) ?></td>
                            <td class="text-left"><?= htmlspecialchars($data['dept']) ?></td>
                            <td class="text-left"><?= htmlspecialchars($data['loc']) ?></td>
                            <td><?= number_format($data['paid_days'], 2) ?></td>
                            
                            <?php foreach($days as $d): 
                                $status = isset($data['attendance'][$d['full_date']]) ? $data['attendance'][$d['full_date']] : '';
                            ?>
                                <td><?= htmlspecialchars($status) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // ==========================================
    // EXPORT MENU LOGIC
    // ==========================================
    function toggleExportMenu(e) {
        e.stopPropagation();
        const menu = document.getElementById('exportMenu');
        menu.classList.toggle('show-flex');
    }

    // Placeholder export function. You will need to replace this with 
    // your actual backend logic (like redirecting to a PHP script that generates the file)
    function exportReport(type, e) {
        e.preventDefault();
        
        // This alerts the user, but you should replace it with your export script URL
        // Example: window.location.href = 'export.php?format=' + type.toLowerCase() + '&start=<?= $start_date_str ?>&end=<?= $end_date_str ?>';
        alert("Exporting to " + type + " requires backend logic. Please attach your export script here.");
        
        document.getElementById('exportMenu').classList.remove('show-flex');
    }

    document.addEventListener('click', function(e) {
        const menu = document.getElementById('exportMenu');
        if (menu && menu.classList.contains('show-flex')) {
            menu.classList.remove('show-flex');
        }
    });

    // ==========================================
    // PAGINATION LOGIC
    // ==========================================
    function goToPage(page) {
        const totalPages = <?= $total_pages ?>;
        let p = parseInt(page);
        
        if (isNaN(p) || p < 1) p = 1;
        if (p > totalPages) p = totalPages;

        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('page', p);
        window.location.search = currentParams.toString();
    }

    // ==========================================
    // FIND / SEARCH LOGIC
    // ==========================================
    let searchMatches = [];
    let currentMatchIndex = -1;

    function findInReport() {
        const query = document.getElementById('findInput').value.toLowerCase().trim();
        
        // Reset previous searches
        const previouslyHighlighted = document.querySelectorAll('.highlight-cell');
        previouslyHighlighted.forEach(el => el.classList.remove('highlight-cell'));
        searchMatches = [];
        currentMatchIndex = -1;

        if (!query) return;

        // Search through the table body rows
        const rows = document.querySelectorAll('#mainReportTable tbody .row-data');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(query)) {
                    cell.classList.add('highlight-cell');
                    searchMatches.push(cell);
                }
            });
        });

        if (searchMatches.length > 0) {
            currentMatchIndex = 0;
            scrollToMatch();
        } else {
            alert('No matches found for: ' + query);
        }
    }

    function findNextInReport() {
        if (searchMatches.length === 0) return;
        
        currentMatchIndex++;
        if (currentMatchIndex >= searchMatches.length) {
            currentMatchIndex = 0; // Loop back to start
        }
        scrollToMatch();
    }

    function scrollToMatch() {
        if (searchMatches[currentMatchIndex]) {
            searchMatches[currentMatchIndex].scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        }
    }
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>