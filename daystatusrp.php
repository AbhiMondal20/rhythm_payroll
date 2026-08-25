<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

// --- FUNCTIONS ---
function splitStatusToHalves($code) {
    switch (trim($code)) {
        case 'PP':   return ['P', 'P'];
        case 'AA':   return ['A', 'A'];
        case 'P*A':  return ['P', 'A'];
        case 'A*P':  return ['A', 'P'];
        case 'HO':   return ['HO', 'HO'];
        case 'WO':   return ['WO', 'WO'];
        case 'WW':   return ['WW', 'WW'];
        case 'HW':   return ['HW', 'HW'];
        case 'WW*':  return ['WW', 'WO'];
        case '*WW':  return ['WO', 'WW'];
        case 'HW*':  return ['HW', 'HO'];
        case '*HW':  return ['HO', 'HW'];
        case '*LOP': return ['P', 'LOP'];
        case 'LOP*': return ['LOP', 'P'];
        default:     return [$code ?: '-', $code ?: '-'];
    }
}

function getCodeFromHalves($s1, $s2) {
    $s1 = trim($s1);
    $s2 = trim($s2);
    if (in_array($s1, ['PP', 'AA', 'P*A', 'A*P', 'HO', 'WO', 'WW', 'HW', 'WW*', '*WW', 'HW*', '*HW', '*LOP', 'LOP*'])) {
        return $s1;
    }
    if ($s1 === 'P' && $s2 === 'P') return 'PP';
    if ($s1 === 'A' && $s2 === 'A') return 'AA';
    if ($s1 === 'P' && $s2 === 'A') return 'P*A';
    if ($s1 === 'A' && $s2 === 'P') return 'A*P';
    if ($s1 === 'HO' && $s2 === 'HO') return 'HO';
    if ($s1 === 'WO' && $s2 === 'WO') return 'WO';
    if ($s1 === 'WW' && $s2 === 'WW') return 'WW';
    if ($s1 === 'HW' && $s2 === 'HW') return 'HW';
    if ($s1 === 'WW' && $s2 === 'WO') return 'WW*';
    if ($s1 === 'WO' && $s2 === 'WW') return '*WW';
    if ($s1 === 'HW' && $s2 === 'HO') return 'HW*';
    if ($s1 === 'HO' && $s2 === 'HW') return '*HW';
    if ($s1 === 'P' && $s2 === 'LOP') return '*LOP';
    if ($s1 === 'LOP' && $s2 === 'P') return 'LOP*';
    return 'PP';
}

function getStatusColor($status) {
    $status = trim($status);
    if (in_array($status, ['WO', 'HO', 'WW', 'HW'])) {
        return 'color: #008000;'; // Green for off days/holidays
    } elseif (in_array($status, ['AA', 'A*P', 'P*A', 'A'])) {
        return 'color: #FF0000;'; // Red for absent combinations
    }
    return 'color: #000000;'; // Default black
}

// 1. Determine Date Range from the GET request URL
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

// 3. Setup Filter for Employees
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
                'dept'       => 'OEX - Operations', 
                'loc'        => 'SILIGURI - SILIGURI', 
                'paid_days'  => 0,
                'attendance' => []
            ];
            
            if($emp_code == '1009') $all_report_data[$emp_code]['dept'] = 'MED - Medicine';
            if($emp_code == '1013') $all_report_data[$emp_code]['dept'] = 'NUR - NURSING';
        }

        $all_report_data[$emp_code]['attendance'][$date_val] = $status;

        if (in_array($status, ['PP', 'WO', 'PH', 'SL', 'CL', 'EL', 'Co'])) {
            $all_report_data[$emp_code]['paid_days'] += 1;
        } elseif (in_array($status, ['HD'])) { 
            $all_report_data[$emp_code]['paid_days'] += 0.5;
        }

        if (isset($daily_summary[$date_val])) {
            if (in_array($status, ['AA', 'A', 'LWP', 'CL'])) {
                $daily_summary[$date_val]['not_reported']++;
            } elseif (!empty($status)) {
                $daily_summary[$date_val]['reported']++;
            }
        }
    }
}

// Formatting string outputs
$display_from = date('M d, Y', $start_time);
$display_to   = date('M d, Y', $end_time);
$display_sel  = date('d/m/Y', $start_time) . ' - ' . date('d/m/Y', $end_time);
$gen_date     = date('M d, Y h:i:s A');


// --- HANDLE EXPORT REQUESTS ---
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $filename_base = "Day_Status_Report_" . date('Ymd');
    
    // EXCEL / WORD EXPORT
    if ($export_type === 'excel' || $export_type === 'word') {
        if ($export_type === 'excel') {
            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: attachment; filename=\"{$filename_base}.xls\"");
        } else {
            header("Content-Type: application/vnd.ms-word");
            header("Content-Disposition: attachment; filename=\"{$filename_base}.doc\"");
        }
        header("Pragma: no-cache");
        header("Expires: 0");
        ?>
        <table border="1" style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; text-align: center;">
            <thead>
                <tr>
                    <th style="background-color: #00a2d1; color: #ffffff; text-align: left;">Employee Code - Name</th>
                    <th style="background-color: #00a2d1; color: #ffffff; text-align: left;">Dept Code-Name</th>
                    <th style="background-color: #00a2d1; color: #ffffff; text-align: left;">Loc Code-Name</th>
                    <th style="background-color: #00a2d1; color: #ffffff;">Paid Days</th>
                    <?php foreach($days as $d): ?>
                        <th style="background-color: #00a2d1; color: #ffffff;"><?= $d['day_name'] ?> - <?= $d['date_str'] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="background-color: #00a2d1; color: #ffffff; text-align: left;">Reported - Not Reported</td>
                    <td style="background-color: #00a2d1; color: #ffffff;"></td>
                    <td style="background-color: #00a2d1; color: #ffffff;"></td>
                    <td style="background-color: #00a2d1; color: #ffffff;"></td>
                    <?php foreach($days as $d): 
                        $rep = $daily_summary[$d['full_date']]['reported'];
                        $not_rep = $daily_summary[$d['full_date']]['not_reported'];
                    ?>
                        <td style="background-color: #00a2d1; color: #ffffff;"><?= $rep ?> - <?= $not_rep ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php foreach ($all_report_data as $emp_code => $data): ?>
                    <tr>
                        <td style="text-align: left;"><?= htmlspecialchars($emp_code) ?> - <?= htmlspecialchars($data['name']) ?></td>
                        <td style="text-align: left;"><?= htmlspecialchars($data['dept']) ?></td>
                        <td style="text-align: left;"><?= htmlspecialchars($data['loc']) ?></td>
                        <td><?= number_format($data['paid_days'], 2) ?></td>
                        <?php foreach($days as $d): 
                            $status = isset($data['attendance'][$d['full_date']]) ? $data['attendance'][$d['full_date']] : '';
                        ?>
                            <td style="<?= getStatusColor($status) ?> font-weight: bold;"><?= htmlspecialchars($status) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        exit();
    }
    
    // PDF EXPORT (Print View)
    if ($export_type === 'pdf') {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?= $filename_base ?></title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 10px; }
                .header { text-align: center; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #000; padding: 4px; text-align: center; }
                th { background-color: #d9d9d9; }
                .text-left { text-align: left; }
                @media print {
                    @page { size: landscape; margin: 10mm; }
                    body { margin: 0; }
                }
            </style>
        </head>
        <body onload="window.print(); setTimeout(function(){ window.close(); }, 500);">
            <div class="header">
                <h2>Day Status Report</h2>
                <p>Selection: <?= $display_sel ?> | Generated on: <?= $gen_date ?></p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th class="text-left">Employee Code - Name</th>
                        <th class="text-left">Dept Code-Name</th>
                        <th class="text-left">Loc Code-Name</th>
                        <th>Paid Days</th>
                        <?php foreach($days as $d): ?>
                            <th><?= $d['day_name'] ?><br><?= $d['date_str'] ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-left" style="background-color: #d9d9d9; font-weight: bold;">Reported - Not Reported</td>
                        <td style="background-color: #d9d9d9;"></td>
                        <td style="background-color: #d9d9d9;"></td>
                        <td style="background-color: #d9d9d9;"></td>
                        <?php foreach($days as $d): 
                            $rep = $daily_summary[$d['full_date']]['reported'];
                            $not_rep = $daily_summary[$d['full_date']]['not_reported'];
                        ?>
                            <td style="background-color: #d9d9d9; font-weight: bold;"><?= $rep ?> - <?= $not_rep ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php foreach ($all_report_data as $emp_code => $data): ?>
                        <tr>
                            <td class="text-left"><?= htmlspecialchars($emp_code) ?> - <?= htmlspecialchars($data['name']) ?></td>
                            <td class="text-left"><?= htmlspecialchars($data['dept']) ?></td>
                            <td class="text-left"><?= htmlspecialchars($data['loc']) ?></td>
                            <td><?= number_format($data['paid_days'], 2) ?></td>
                            <?php foreach($days as $d): 
                                $status = isset($data['attendance'][$d['full_date']]) ? $data['attendance'][$d['full_date']] : '';
                            ?>
                                <td style="<?= getStatusColor($status) ?>"><?= htmlspecialchars($status) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit();
    }
}


// 5. Pagination Logic for View Mode
$records_per_page = 25; 
$total_records = count($all_report_data);
$total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages) $current_page = $total_pages;

$offset = ($current_page - 1) * $records_per_page;
$report_data = array_slice($all_report_data, $offset, $records_per_page, true);


$page_title = 'Day Status Report - View';
ob_start();
?>

<!-- Keep this path relative to browser URL -->
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

    /* =========================================
       DATA TABLE & SCROLLBARS
       ========================================= */
    .table-responsive { 
        width: 100%; 
        max-height: 60vh; 
        overflow-x: auto; 
        overflow-y: auto; 
        border: 1px solid #ddd; 
        scrollbar-width: thin; 
        scrollbar-color: #a0a0a0 #f1f1f1; 
    }
    .table-responsive::-webkit-scrollbar { 
        height: 14px; 
        width: 14px;  
    }
    .table-responsive::-webkit-scrollbar-track { 
        background: #f1f1f1; 
        border-top: 1px solid #ddd; 
        border-left: 1px solid #ddd;
    }
    .table-responsive::-webkit-scrollbar-thumb { 
        background: #a0a0a0; 
        border-radius: 10px; 
        border: 2px solid #f1f1f1; 
    }

    .report-table { width: 100%; border-collapse: separate; border-spacing: 0; white-space: nowrap; }
    .report-table th, .report-table td { border-bottom: 1px solid #c0c0c0; border-right: 1px solid #c0c0c0; padding: 4px 6px; font-size: 11px; text-align: center; }
    
    .report-table th { background-color: #00a2d1; color: #ffffff; font-weight: normal; vertical-align: bottom; }
    .row-summary td { background-color: #00a2d1; color: #ffffff; }
    .row-data td { background-color: #ffffff; color: #000000; }
    
    .report-table td.text-left, .report-table th.text-left { text-align: left; }
    
    /* Search Highlight */
    .highlight-cell { background-color: #ffff99 !important; font-weight: bold; }

    /* =========================================
       STICKY HEADERS & COLUMNS (Freeze Panes)
       ========================================= */
    .report-table thead th {
        position: sticky;
        top: 0;
        z-index: 4;
        border-top: 1px solid #c0c0c0;
    }

    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 2;
    }
    
    .report-table thead .sticky-col {
        background-color: #00a2d1;
        z-index: 5;
        border-right: 2px solid #007a9e;
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
                            <td class="text-left sticky-col"><?= htmlspecialchars($emp_code) ?> - <?= htmlspecialchars($data['name']) ?></td>
                            <td class="text-left"><?= htmlspecialchars($data['dept']) ?></td>
                            <td class="text-left"><?= htmlspecialchars($data['loc']) ?></td>
                            <td><?= number_format($data['paid_days'], 2) ?></td>
                            
                            <?php foreach($days as $d): 
                                $status = isset($data['attendance'][$d['full_date']]) ? $data['attendance'][$d['full_date']] : '';
                            ?>
                                <td style="<?= getStatusColor($status) ?>">
                                    <?= htmlspecialchars($status) ?>
                                </td>
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

    function exportReport(type, e) {
        e.preventDefault();
        document.getElementById('exportMenu').classList.remove('show-flex');

        const currentParams = new URLSearchParams(window.location.search);
        
        if (type === 'Excel') {
            currentParams.set('export', 'excel');
            window.location.href = window.location.pathname + '?' + currentParams.toString();
        } else if (type === 'Word') {
            currentParams.set('export', 'word');
            window.location.href = window.location.pathname + '?' + currentParams.toString();
        } else if (type === 'PDF') {
            currentParams.set('export', 'pdf');
            // Opens a new tab which triggers the print to PDF dialog automatically
            window.open(window.location.pathname + '?' + currentParams.toString(), '_blank');
        }
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
        currentParams.delete('export'); // Clear export query if present
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
        
        const previouslyHighlighted = document.querySelectorAll('.highlight-cell');
        previouslyHighlighted.forEach(el => el.classList.remove('highlight-cell'));
        searchMatches = [];
        currentMatchIndex = -1;

        if (!query) return;

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
            currentMatchIndex = 0; 
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

<script src="includes/assets/scripts.js"></script>