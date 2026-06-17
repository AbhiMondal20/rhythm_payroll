<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

// Determine the referring page to redirect back to
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'AssetManagement';

// ── Form Processing ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Add Asset Type
    if ($action === 'add_asset_type') {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $warranty_applicable = isset($_POST['warranty_applicable']) ? 1 : 0;
        $service_applicable = isset($_POST['service_applicable']) ? 1 : 0;

        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO asset_types (name, category, remarks, warranty_applicable, service_applicable) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssii", $name, $category, $remarks, $warranty_applicable, $service_applicable);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    // 2. Update Asset Type
    elseif ($action === 'update_asset_type') {
        $id = intval($_POST['at_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $warranty_applicable = isset($_POST['warranty_applicable']) ? 1 : 0;
        $service_applicable = isset($_POST['service_applicable']) ? 1 : 0;

        if ($id > 0 && !empty($name)) {
            $stmt = mysqli_prepare($conn, "UPDATE asset_types SET name=?, category=?, remarks=?, warranty_applicable=?, service_applicable=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssiii", $name, $category, $remarks, $warranty_applicable, $service_applicable, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    // 3. Delete Asset Type
    elseif ($action === 'delete_asset_type') {
        $id = intval($_POST['at_id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM asset_types WHERE id=?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    // 4. Add Asset
    elseif ($action === 'add_asset') {
        $asset_type_id = intval($_POST['asset_type_id'] ?? 0);
        $serial_no = trim($_POST['serial_no'] ?? '');
        $acquired_date = !empty($_POST['acquired_date']) ? $_POST['acquired_date'] : null;
        $location = trim($_POST['location'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        if ($asset_type_id > 0) {
            $res = mysqli_query($conn, "SELECT MAX(id) FROM assets");
            $max_id = mysqli_fetch_row($res)[0];
            $next_id = $max_id ? $max_id + 1 : 1;
            $generated_asset_id = 'AID-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);

            $stmt = mysqli_prepare($conn, "INSERT INTO assets (asset_id, asset_type_id, serial_no, acquired_date, location, status) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sissss", $generated_asset_id, $asset_type_id, $serial_no, $acquired_date, $location, $status);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    // 5. Assign Asset
    elseif ($action === 'assign_asset') {
        $asset_id = intval($_POST['asset_id'] ?? 0);
        $employee_id = intval($_POST['employee_id'] ?? 0); // Using the selected employee ID from the autocomplete
        
        if ($asset_id > 0 && $employee_id > 0) {
            // Update the asset with the employee's ID from the 'employees' table
            $stmt = mysqli_prepare($conn, "UPDATE assets SET assigned_to=?, status='Active' WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ii", $employee_id, $asset_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    // 6. Update Asset Status (Missing / Junk)
    elseif ($action === 'update_asset_status') {
        $asset_id = intval($_POST['asset_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');
        
        if ($asset_id > 0 && !empty($new_status)) {
            $stmt = mysqli_prepare($conn, "UPDATE assets SET status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "si", $new_status, $asset_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    // 7. EDIT ASSET LOGIC
    elseif ($action === 'edit_asset') {
        $id = intval($_POST['asset_id'] ?? 0);
        $asset_type_id = intval($_POST['asset_type_id'] ?? 0);
        $serial_no = trim($_POST['serial_no'] ?? '');
        $acquired_date = !empty($_POST['acquired_date']) ? $_POST['acquired_date'] : null;
        $location = trim($_POST['location'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        if ($id > 0 && $asset_type_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE assets SET asset_type_id=?, serial_no=?, acquired_date=?, location=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "issssi", $asset_type_id, $serial_no, $acquired_date, $location, $status, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    header('Location: ' . $redirect_url);
    exit();
}

// ── DB Queries ──────────────────────────────────────────────────────────────
// Quick Insights
$res = mysqli_query($conn, "SELECT COUNT(*) FROM assets");
$total_assets = $res ? mysqli_fetch_row($res)[0] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) FROM assets WHERE status='Active'");
$active_assets = $res ? mysqli_fetch_row($res)[0] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) FROM assets WHERE status='Inactive'");
$inactive_assets = $res ? mysqli_fetch_row($res)[0] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) FROM assets WHERE status IN ('Out for Service', 'Under Service')");
$out_for_service = $res ? mysqli_fetch_row($res)[0] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) FROM assets WHERE assigned_to IS NOT NULL AND assigned_to != ''");
$assigned_assets = $res ? mysqli_fetch_row($res)[0] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) FROM assets WHERE assigned_to IS NULL OR assigned_to = ''");
$unassigned_assets = $res ? mysqli_fetch_row($res)[0] : 0;

// Fetch all active employees for the Assign Asset Dropdown
$res_all_emp = mysqli_query($conn, "SELECT id, employee_code, employee_name FROM employees WHERE status = 'Active' ORDER BY employee_name ASC");
$all_employees = $res_all_emp ? mysqli_fetch_all($res_all_emp, MYSQLI_ASSOC) : [];

// Asset Distribution by Category
$res = mysqli_query($conn, "
    SELECT at.category, COUNT(a.id) as cnt
    FROM assets a
    JOIN asset_types at ON a.asset_type_id = at.id
    GROUP BY at.category
");
$dist_cat = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Asset Distribution by Location
$res = mysqli_query($conn, "SELECT location_name AS location, COUNT(*) as cnt FROM org_locations GROUP BY location_name");
$dist_loc = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Upcoming Services
$res = mysqli_query($conn, "
    SELECT a.asset_id, at.name AS asset_type, a.next_service_date
    FROM assets a JOIN asset_types at ON a.asset_type_id = at.id
    WHERE a.next_service_date >= CURDATE()
    ORDER BY a.next_service_date ASC LIMIT 10
");
$upcoming = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Asset Types list
$res = mysqli_query($conn, "SELECT id, name, warranty_applicable, service_applicable, remarks FROM asset_types ORDER BY id ASC");
$asset_types = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Asset Repository – Assigned
$res = mysqli_query($conn, "
    SELECT a.id, at.category, at.name AS asset_type, a.asset_id,
           a.serial_no, a.acquired_date, a.assigned_to, a.location, a.status,
           e.employee_name, e.employee_code
    FROM assets a 
    JOIN asset_types at ON a.asset_type_id = at.id
    LEFT JOIN employees e ON a.assigned_to = e.id
    WHERE a.assigned_to IS NOT NULL AND a.assigned_to != ''
    ORDER BY a.asset_id DESC
");
$assigned_repo = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Asset Repository – Unassigned
$res = mysqli_query($conn, "
    SELECT a.id, at.category, at.name AS asset_type, a.asset_id,
           a.serial_no, a.acquired_date, a.assigned_to, a.location, a.status
    FROM assets a JOIN asset_types at ON a.asset_type_id = at.id
    WHERE a.assigned_to IS NULL OR a.assigned_to = ''
    ORDER BY a.asset_id DESC
");
$unassigned_repo = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Assets Under Service
$res = mysqli_query($conn, "
    SELECT a.id, at.category, at.name AS asset_type, a.asset_id,
           a.serial_no, a.acquired_date, a.assigned_to, a.location, a.status
    FROM assets a JOIN asset_types at ON a.asset_type_id = at.id
    WHERE a.status IN ('Out for Service', 'Under Service')
    ORDER BY a.asset_id DESC
");
$under_service_repo = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Junk Assets
$res = mysqli_query($conn, "
    SELECT a.id, at.category, at.name AS asset_type, a.asset_id,
           a.serial_no, a.acquired_date, a.assigned_to, a.location, a.status
    FROM assets a JOIN asset_types at ON a.asset_type_id = at.id
    WHERE a.status = 'Junk'
    ORDER BY a.asset_id DESC
");
$junk_repo = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Missing Assets
$res = mysqli_query($conn, "
    SELECT a.id, at.category, at.name AS asset_type, a.asset_id,
           a.serial_no, a.acquired_date, a.assigned_to, a.location, a.status
    FROM assets a JOIN asset_types at ON a.asset_type_id = at.id
    WHERE a.status = 'Missing'
    ORDER BY a.asset_id DESC
");
$missing_repo = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Employee Assets
$res = mysqli_query($conn, "
    SELECT e.`id`, e.`employee_code`, e.`employee_name`, e.`department`, a.`location`,
           COUNT(a.id) AS asset_count
    FROM `employees` e
    LEFT JOIN assets a ON a.assigned_to = e.id
    GROUP BY e.id
    HAVING asset_count > 0
    ORDER BY e.employee_code ASC
");
$emp_assets = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Locations for filter
$res = mysqli_query($conn, "SELECT DISTINCT  location_name AS location FROM org_locations ORDER BY location");
$loc_data = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
$locations = array_column($loc_data, 'location');

// Fetch Complete Asset Details for Javascript Detail Pane
$res = mysqli_query($conn, "
    SELECT a.*, at.name AS asset_type_name, at.category, e.employee_name, e.employee_code
    FROM assets a
    LEFT JOIN asset_types at ON a.asset_type_id = at.id
    LEFT JOIN employees e ON a.assigned_to = e.id
");
$all_assets_full = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];

// Fetch Assignment History
$res_history = mysqli_query($conn, "SELECT ah.*, e.employee_name FROM asset_assignment_history ah LEFT JOIN employees e ON ah.employee_id = e.id ORDER BY ah.assigned_date DESC");
$history_logs = $res_history ? mysqli_fetch_all($res_history, MYSQLI_ASSOC) : [];

// ── Chart data JSON ─────────────────────────────────────────────────────────
$chart_cat_labels = json_encode(array_column($dist_cat, 'category'));
$chart_cat_data   = json_encode(array_column($dist_cat, 'cnt'));
$chart_loc_labels = json_encode(array_column($dist_loc, 'location'));
$chart_loc_data   = json_encode(array_column($dist_loc, 'cnt'));

$page_title = 'Asset Management';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ── Reset / Base ── */
.am-wrap { font-family: 'Segoe UI', Arial, sans-serif; border-radius: 8px; font-size: 13px; color: #2d3748; background: #ffffff; min-height: calc(100vh - 60px); }
/* ── Top nav bar ── */
.am-header { display: flex; align-items: center; justify-content: space-between; padding: 0 24px; background: #fff; border-bottom: 1px solid #e2e8f0; height: 46px; }
.am-header h2 { font-size: 15px; font-weight: 600; margin: 0; }
.am-tabs { display: flex; gap: 0; }
.am-tab { padding: 14px 16px; font-size: 13px; color: #718096; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; text-decoration: none; }
.am-tab:hover { color: #3182ce; }
.am-tab.active { color: #3182ce; border-bottom-color: #3182ce; font-weight: 500; }
.am-tab-sep { color: #cbd5e0; padding: 14px 0; }
/* ── Content ── */
.am-body { padding: 20px 24px; }
/* ── Stat cards ── */
.am-stats { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
.am-stat { background: #fff; border-radius: 8px; padding: 18px 20px; flex: 1; min-width: 130px; box-shadow: 0 1px 3px rgba(0, 0, 0, .07); }
.am-stat-label { font-size: 12px; color: #718096; margin-bottom: 8px; }
.am-stat-val { font-size: 26px; font-weight: 700; color: #2d3748; }
.am-stat-val.green { color: #38a169; }
.am-stat-val.orange { color: #dd6b20; }
/* ── Two-col charts row ── */
.am-charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.am-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, .07); padding: 18px 20px; }
.am-card-title { font-size: 13px; font-weight: 600; margin-bottom: 14px; color: #2d3748; }
.am-chart-toggle { float: right; display: flex; gap: 0; font-size: 12px; margin-top: -2px; }
.am-chart-toggle button { padding: 3px 10px; border: 1px solid #cbd5e0; background: #fff; cursor: pointer; color: #718096; }
.am-chart-toggle button:first-child { border-radius: 4px 0 0 4px; }
.am-chart-toggle button:last-child { border-radius: 0 4px 4px 0; border-left: none; }
.am-chart-toggle button.active { color: #3182ce; border-color: #3182ce; background: #ebf8ff; }
.am-chart-wrap { position: relative; height: 220px; }
.am-empty-msg { text-align: center; color: #a0aec0; font-size: 12px; padding: 60px 0; }
/* ── Location filter ── */
.am-loc-select { float: right; font-size: 12px; padding: 4px 8px; border: 1px solid #cbd5e0; border-radius: 4px; color: #2d3748; background: #fff; cursor: pointer; margin-top: -4px; }
/* ── Generic table ── */
.am-table-wrap { overflow-x: auto; }
table.am-table { width: 100%; border-collapse: collapse; font-size: 13px; }
table.am-table thead th { background: #f7fafc; color: #718096; font-weight: 500; padding: 10px 14px; text-align: left; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
table.am-table tbody td { padding: 10px 14px; border-bottom: 1px solid #f0f2f5; vertical-align: middle; }
table.am-table tbody tr:hover { background: #f7fafc; }
.am-arrow { color: #a0aec0; font-size: 16px; cursor: pointer; }
.am-badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 12px; }
.am-badge.active { background: #c6f6d5; color: #276749; border: 1px solid #9ae6b4; }
.am-badge.inactive { background: #fed7d7; color: #9b2c2c; }
.am-badge.out { background: #feebc8; color: #c05621; }
.am-badge.count { background: #c6f6d5; color: #276749; font-weight: 600; }
/* ── Pagination ── */
.am-table-footer { display: flex; align-items: center; justify-content: space-between; padding: 12px 4px 0; font-size: 12px; color: #718096; }
.am-pg { display: flex; align-items: center; gap: 2px; }
.am-pg button { border: 1px solid #e2e8f0; background: #fff; width: 28px; height: 28px; cursor: pointer; border-radius: 4px; font-size: 12px; color: #718096; }
.am-pg button.active { background: #3182ce; color: #fff; border-color: #3182ce; }
.am-pg button:hover:not(.active) { background: #f7fafc; }
.am-show-entries { display: flex; align-items: center; gap: 6px; }
.am-show-entries select { padding: 2px 4px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 12px; }
/* ── Buttons ── */
.btn-blue { background: #3182ce; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
.btn-blue:hover { background: #2b6cb0; }
.btn-outline { background: #fff; color: #3182ce; border: 1px solid #3182ce; border-radius: 6px; padding: 7px 14px; font-size: 13px; cursor: pointer; }
.btn-outline:hover { background: #ebf8ff; }
.btn-red-outline { background: #fff; color: #e53e3e; border: 1px solid #e53e3e; border-radius: 6px; padding: 7px 14px; font-size: 13px; cursor: pointer; }
.btn-red-outline:hover { background: #fff5f5; }
/* ── Section header row ── */
.am-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.am-section-head h3 { font-size: 15px; font-weight: 600; margin: 0; }
/* ── Sub-tabs (Assigned / Unassigned) ── */
.am-subtabs { display: flex; gap: 0; border-bottom: 1px solid #e2e8f0; margin-bottom: 16px; }
.am-subtab { padding: 10px 16px; font-size: 13px; color: #718096; cursor: pointer; border-bottom: 2px solid transparent; }
.am-subtab.active { color: #3182ce; border-bottom-color: #3182ce; font-weight: 500; }
/* ── Filters bar ── */
.am-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.am-search { display: flex; align-items: center; gap: 6px; border: 1px solid #cbd5e0; border-radius: 6px; padding: 6px 10px; background: #fff; }
.am-search input { border: none; outline: none; font-size: 13px; width: 160px; }
.am-search svg { color: #a0aec0; }
.am-filter-select { padding: 7px 10px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 13px; background: #fff; color: #2d3748; }
.am-filter-btn { background: #3182ce; color: #fff; border: none; border-radius: 6px; padding: 7px 16px; font-size: 13px; cursor: pointer; }
.am-filters-right { margin-left: auto; display: flex; gap: 8px; }
/* ── Asset Type detail / edit ── */
.am-detail-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.am-back-btn { display: flex; align-items: center; gap: 6px; color: #2d3748; cursor: pointer; font-weight: 600; font-size: 14px; }
.am-back-btn:hover { color: #3182ce; }
.am-edit-link { color: #3182ce; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 4px; }
.am-form-section-title { font-size: 13px; font-weight: 600; margin-bottom: 14px; }
.am-form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.am-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 10px; }
.am-field label { display: block; font-size: 11px; color: #718096; margin-bottom: 4px; }
.am-field input, .am-field select, .am-field textarea { width: 100%; border: none; border-bottom: 1px solid #cbd5e0; padding: 6px 0; font-size: 13px; outline: none; background: transparent; font-family: inherit; box-sizing: border-box; }
.am-field input:focus, .am-field select:focus { border-bottom-color: #3182ce; }
.am-field-readonly { font-size: 13px; padding: 6px 0; border-bottom: 1px solid #e2e8f0; color: #2d3748; }
.am-checkbox-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-top: 1px solid #f0f2f5; }
.am-checkbox-row label { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.am-mandatory { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #718096; }
.am-toggle { position: relative; width: 36px; height: 20px; }
.am-toggle input { opacity: 0; width: 0; height: 0; }
.am-toggle-slider { position: absolute; inset: 0; background: #cbd5e0; border-radius: 20px; transition: .2s; cursor: pointer; }
.am-toggle input:checked+.am-toggle-slider { background: #3182ce; }
.am-toggle-slider:before { content: ''; position: absolute; width: 14px; height: 14px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
.am-toggle input:checked+.am-toggle-slider:before { transform: translateX(16px); }
.am-new-field-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.am-add-field { color: #3182ce; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 4px; }
.am-edit-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid #f0f2f5; margin-top: 10px; }
.am-edit-footer-right { display: flex; gap: 10px; }
/* ── Others grid ── */
.am-others-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
.am-other-card { display: flex; align-items: flex-start; gap: 14px; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, .07); cursor: pointer; }
.am-other-card:hover { box-shadow: 0 3px 8px rgba(0, 0, 0, .1); }
.am-other-icon { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.am-other-text h4 { font-size: 13px; font-weight: 600; margin: 0 0 5px; }
.am-other-text p { font-size: 12px; color: #718096; margin: 0; line-height: 1.5; }
/* ── Modal ── */
.am-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, .45); z-index: 1000; align-items: center; justify-content: center; }
.am-modal-overlay.open { display: flex; }
.am-modal { background: #fff; border-radius: 10px; padding: 24px; width: 540px; max-width: 95vw; box-shadow: 0 10px 40px rgba(0, 0, 0, .15); }
.am-modal h3 { font-size: 15px; font-weight: 600; margin: 0 0 20px; }
.am-modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
.am-modal .am-form-grid { grid-template-columns: 1fr 1fr; }
/* ── Dividers ── */
.am-divider { height: 1px; background: #f0f2f5; margin: 16px 0; }
/* ── Hidden ── */
.am-pane { display: none; }
.am-pane.active { display: block; }

/* ── Custom Autocomplete Dropdown ── */
.am-autocomplete-container { position: relative; width: 100%; }
.am-autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e0; border-radius: 4px; max-height: 180px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: none; }
.am-autocomplete-item { padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f0f2f5; color: #2d3748; }
.am-autocomplete-item:hover { background: #ebf8ff; color: #3182ce; }
</style>

<div class="am-wrap">
    <div class="am-header">
        <h2>Asset Management</h2>
        <div class="am-tabs">
            <a class="am-tab active" data-pane="insights">Insights</a>
            <span class="am-tab-sep">|</span>
            <a class="am-tab" data-pane="asset-types">Asset Types</a>
            <span class="am-tab-sep">|</span>
            <a class="am-tab" data-pane="asset-repo">Asset Repository</a>
            <span class="am-tab-sep">|</span>
            <a class="am-tab" data-pane="emp-assets">Employee Assets</a>
            <span class="am-tab-sep">|</span>
            <a class="am-tab" data-pane="others">Others</a>
        </div>
    </div>

    <div class="am-body">
        <div class="am-pane active" id="pane-insights">
            <div class="am-section-head">
                <h3>Quick Insights</h3>
                <select class="am-loc-select" id="locFilter" onchange="filterByLocation(this.value)">
                    <option value="">All Location</option>
                    <?php foreach ($locations as $loc): ?>
                    <option><?= htmlspecialchars($loc) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="am-stats">
                <div class="am-stat"><div class="am-stat-label">Total Assets</div><div class="am-stat-val"><?= $total_assets ?></div></div>
                <div class="am-stat"><div class="am-stat-label">Active Assets</div><div class="am-stat-val green"><?= $active_assets ?></div></div>
                <div class="am-stat"><div class="am-stat-label">Inactive Assets</div><div class="am-stat-val"><?= $inactive_assets ?></div></div>
                <div class="am-stat"><div class="am-stat-label">Out for Service</div><div class="am-stat-val orange"><?= $out_for_service ?></div></div>
                <div class="am-stat"><div class="am-stat-label">Assigned</div><div class="am-stat-val"><?= $assigned_assets ?></div></div>
                <div class="am-stat"><div class="am-stat-label">Unassigned</div><div class="am-stat-val"><?= $unassigned_assets ?></div></div>
            </div>
            <div class="am-charts-row">
                <div class="am-card">
                    <div class="am-card-title">Asset Distribution
                        <div class="am-chart-toggle">
                            <button class="active" onclick="switchChart('category',this)">Category</button>
                            <button onclick="switchChart('location',this)">Location</button>
                        </div>
                    </div>
                    <div class="am-chart-wrap"><canvas id="distChart"></canvas></div>
                </div>
                <div class="am-card">
                    <div class="am-card-title">Upcoming Services</div>
                    <?php if (empty($upcoming)): ?>
                    <div class="am-empty-msg">No upcoming services found</div>
                    <?php else: ?>
                    <table class="am-table">
                        <thead><tr><th>Asset ID</th><th>Type</th><th>Service Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($upcoming as $u): ?>
                            <tr><td><?= htmlspecialchars($u['asset_id']) ?></td><td><?= htmlspecialchars($u['asset_type']) ?></td><td><?= date('d M Y', strtotime($u['next_service_date'])) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="am-pane" id="pane-asset-types">
            <div id="at-list-view">
                <div class="am-section-head">
                    <h3>Asset Types</h3>
                    <button class="btn-blue" onclick="openModal('modalAddAssetType')">&#43; Asset Type</button>
                </div>
                <div class="am-card">
                    <div class="am-table-wrap">
                        <table class="am-table">
                            <thead><tr><th>S No.</th><th>Asset Type Name</th><th>Warranty Applicable</th><th>Service Applicable</th><th>Remarks</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($asset_types as $i => $at): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td><td><?= htmlspecialchars($at['name']) ?></td><td><?= $at['warranty_applicable'] ? 'Yes' : 'No' ?></td><td><?= $at['service_applicable'] ? 'Yes' : 'No' ?></td><td><?= htmlspecialchars($at['remarks'] ?? '') ?></td>
                                    <td class="am-arrow" onclick='showAssetTypeDetail(<?= json_encode($at, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>›</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($asset_types)): ?><tr><td colspan="6"><div class="am-empty-msg">No asset types found.</div></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div id="at-detail-view" style="display:none">
                <div class="am-card">
                    <div class="am-detail-head">
                        <span class="am-back-btn" onclick="backToAssetTypeList()">&#9664; <span id="at-detail-name"></span></span>
                        <span class="am-edit-link" onclick="switchToEditMode()"><svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/></svg> Edit Details</span>
                    </div>
                    <p class="am-form-section-title">Asset Type Details:</p>
                    <div class="am-form-grid">
                        <div class="am-field"><label>Name for Asset Type</label><div class="am-field-readonly" id="detail-name">—</div></div>
                        <div class="am-field"><label>Category</label><div class="am-field-readonly" id="detail-category">—</div></div>
                        <div class="am-field"><label>Remarks</label><div class="am-field-readonly" id="detail-remarks">—</div></div>
                    </div>
                    <div class="am-divider"></div>
                    <div class="am-checkbox-row"><label><input type="checkbox" id="detail-warranty" disabled> This Asset Type is Covered Under Warranty</label></div>
                    <div class="am-checkbox-row"><label><input type="checkbox" id="detail-service" disabled> This Asset Type Requires Service Dates</label></div>
                </div>
            </div>
            
            <div id="at-edit-view" style="display:none">
                <form method="POST" id="at-edit-form">
                    <input type="hidden" name="action" id="at-edit-action" value="update_asset_type">
                    <input type="hidden" name="at_id" id="edit-at-id">
                    <div class="am-card">
                        <div class="am-detail-head"><span class="am-back-btn" onclick="switchToDetailMode()">&#9664; <span id="at-edit-name"></span></span></div>
                        <p class="am-form-section-title">Asset Type Details:</p>
                        <div class="am-form-grid">
                            <div class="am-field"><label>Name for Asset Type</label><input type="text" name="name" id="edit-name" required></div>
                            <div class="am-field"><label>Category</label><select name="category" id="edit-category"><option>Laptops & PC</option><option>Mobiles & Tabs</option><option>Furniture</option><option>Electronics</option><option>Others</option></select></div>
                            <div class="am-field"><label>Remarks</label><input type="text" name="remarks" id="edit-remarks"></div>
                        </div>
                        <div class="am-divider"></div>
                        <div class="am-checkbox-row"><label><input type="checkbox" name="warranty_applicable" id="edit-warranty"> Warranty</label></div>
                        <div class="am-checkbox-row"><label><input type="checkbox" name="service_applicable" id="edit-service"> Service</label></div>
                        <div class="am-edit-footer">
                            <button type="button" class="btn-red-outline" onclick="confirmRemoveAssetType()">Remove</button>
                            <div class="am-edit-footer-right"><button type="button" class="btn-outline" onclick="switchToDetailMode()">Cancel</button><button type="submit" class="btn-blue">Save Changes</button></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="am-pane" id="pane-asset-repo">
            <div class="am-subtabs">
                <div class="am-subtab active" onclick="switchSubtab(this,'repo-assigned')">Assigned Assets</div>
                <div class="am-subtab" onclick="switchSubtab(this,'repo-unassigned')">Unassigned Assets</div>
            </div>
            <div class="am-filters">
                <div class="am-search"><svg width="14" height="14" fill="none" stroke="#a0aec0" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><input type="text" id="repoSearch" placeholder="Search table items" oninput="filterRepoTable()"></div>
                <button class="am-filter-btn" onclick="filterRepoTable()">Apply</button>
                <div class="am-filters-right">
                    <button class="btn-outline" onclick="exportCSV()">Export To CSV</button>
                    <button class="btn-blue" onclick="openModal('modalAddAsset')">&#43; Asset</button>
                </div>
            </div>

            <div id="repo-assigned" class="am-card">
                <div class="am-table-wrap">
                    <table class="am-table" id="assignedTable">
                        <thead><tr><th>S.No</th><th>Category</th><th>Asset Type</th><th>Asset ID</th><th>Product Serial No.</th><th>Acquired Date</th><th>Assigned To</th><th>Location</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($assigned_repo as $i => $a): ?>
                            <tr>
                                <td><?= $i + 1 ?></td><td><?= htmlspecialchars($a['category']) ?></td><td><?= htmlspecialchars($a['asset_type']) ?></td><td>AID-<?= htmlspecialchars($a['id']) ?></td><td><?= htmlspecialchars($a['serial_no']) ?></td>
                                <td><?= $a['acquired_date'] ? date('d M Y', strtotime($a['acquired_date'])) : '' ?></td>
                                <td><?= htmlspecialchars($a['employee_name'] ?? '') ?> <?= !empty($a['employee_code']) ? '('.htmlspecialchars($a['employee_code']).')' : '' ?></td>
                                <td><?= htmlspecialchars($a['location'] ?? '') ?></td>
                                <td><span class="am-badge <?= strtolower($a['status'])==='active' ? 'active' : 'inactive' ?>"><?= htmlspecialchars($a['status'] ?? '') ?></span></td>
                                <td class="am-arrow" onclick="viewAsset(<?= $a['id'] ?>)">›</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($assigned_repo)): ?><tr><td colspan="10"><div class="am-empty-msg">No assigned assets found.</div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="repo-unassigned" class="am-card" style="display:none">
                <div class="am-table-wrap">
                    <table class="am-table" id="unassignedTable">
                        <thead><tr><th>S.No</th><th>Category</th><th>Asset Type</th><th>Asset ID</th><th>Product Serial No.</th><th>Acquired Date</th><th>Location</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($unassigned_repo as $i => $a): ?>
                            <tr>
                                <td><?= $i + 1 ?></td><td><?= htmlspecialchars($a['category']) ?></td><td><?= htmlspecialchars($a['asset_type']) ?></td><td>AID-<?= htmlspecialchars($a['id']) ?></td><td><?= htmlspecialchars($a['serial_no']) ?></td>
                                <td><?= $a['acquired_date'] ? date('d M Y', strtotime($a['acquired_date'])) : '' ?></td><td><?= htmlspecialchars($a['location'] ?? '') ?></td>
                                <td><span class="am-badge active"><?= htmlspecialchars($a['status'] ?? 'Active') ?></span></td>
                                <td class="am-arrow" onclick="viewAsset(<?= $a['id'] ?>)">›</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($unassigned_repo)): ?><tr><td colspan="9"><div class="am-empty-msg">No unassigned assets found.</div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="am-pane" id="pane-emp-assets">
            <div class="am-section-head">
                <h3>Asset Allocation</h3>
                <button class="btn-blue" onclick="openModal('modalAssignAsset')">Assign Asset</button>
            </div>
            
            <div class="am-filters">
                <div class="am-search">
                    <svg width="14" height="14" fill="none" stroke="#a0aec0" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input type="text" id="empSearch" placeholder="Search by name or #code" oninput="filterEmpTable(this.value)">
                </div>
            </div>

            <div class="am-card">
                <div class="am-table-wrap">
                    <table class="am-table" id="empTable">
                        <thead><tr><th>Employee Code</th><th>Employee Name</th><th>Location</th><th>Department</th><th>No Of Assets Assigned</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($emp_assets as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['employee_code']) ?></td><td><?= htmlspecialchars($e['employee_name']) ?></td>
                                <td><?= htmlspecialchars($e['location'] ?? '') ?></td><td><?= htmlspecialchars($e['department'] ?? '') ?></td>
                                <td><span class="am-badge count"><?= (int)$e['asset_count'] ?></span></td>
                                <td class="am-arrow" onclick="viewEmployeeAssets('<?= htmlspecialchars($e['employee_code']) ?>')">›</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($emp_assets)): ?><tr><td colspan="6"><div class="am-empty-msg">No employee assets found.</div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="am-pane" id="pane-others">
            <div class="am-others-grid">
                <div class="am-other-card" onclick="showPane('under-service')">
                    <div class="am-other-icon">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="8" y="6" width="24" height="30" rx="3" fill="#EBF8FF" stroke="#90CDF4" stroke-width="1.5"/><rect x="12" y="12" width="16" height="2" rx="1" fill="#4299E1"/><rect x="12" y="17" width="12" height="2" rx="1" fill="#90CDF4"/><rect x="12" y="22" width="14" height="2" rx="1" fill="#90CDF4"/><path d="M24 28l4-4-4-4" stroke="#3182CE" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </div>
                    <div class="am-other-text"><h4>Assets Under Service</h4><p>List of all the assets which are under service at that moment.</p></div>
                </div>
                <div class="am-other-card" onclick="showPane('junk-assets')">
                    <div class="am-other-icon">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="14" width="16" height="20" rx="2" fill="#FFF5F5" stroke="#FC8181" stroke-width="1.5"/><path d="M10 14h20M16 14v-3a1 1 0 011-1h6a1 1 0 011 1v3" stroke="#FC8181" stroke-width="1.5" stroke-linecap="round"/><line x1="17" y1="19" x2="17" y2="29" stroke="#FC8181" stroke-width="1.5" stroke-linecap="round"/><line x1="20" y1="19" x2="20" y2="29" stroke="#FC8181" stroke-width="1.5" stroke-linecap="round"/><line x1="23" y1="19" x2="23" y2="29" stroke="#FC8181" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </div>
                    <div class="am-other-text"><h4>Junk Assets</h4><p>List of all the assets moved to junk till now.</p></div>
                </div>
                <div class="am-other-card" onclick="showPane('missing-assets')">
                    <div class="am-other-icon">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="8" y="6" width="18" height="24" rx="2" fill="#FEFCBF" stroke="#F6E05E" stroke-width="1.5"/><text x="30" y="22" font-size="14" font-weight="bold" fill="#D69E2E" text-anchor="middle">?</text><rect x="12" y="12" width="10" height="2" rx="1" fill="#D69E2E"/><rect x="12" y="17" width="7" height="2" rx="1" fill="#ECC94B"/></svg>
                    </div>
                    <div class="am-other-text"><h4>Missing Assets</h4><p>List of all the assets missing till now.</p></div>
                </div>
            </div>
        </div>

        <div class="am-pane" id="pane-under-service">
            <div class="am-section-head">
                <h3><span class="am-back-btn" onclick="showPane('others')">&#9664; Assets Under Service</span></h3>
            </div>
            <div class="am-card">
                <div class="am-table-wrap">
                    <table class="am-table">
                        <thead><tr><th>S.No</th><th>Category</th><th>Asset Type</th><th>Asset ID</th><th>Product Serial No.</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($under_service_repo as $i => $a): ?>
                            <tr><td><?= $i + 1 ?></td><td><?= htmlspecialchars($a['category']) ?></td><td><?= htmlspecialchars($a['asset_type']) ?></td><td><?= htmlspecialchars($a['asset_id']) ?></td><td><?= htmlspecialchars($a['serial_no']) ?></td><td><span class="am-badge out"><?= htmlspecialchars($a['status']) ?></span></td><td class="am-arrow" onclick="viewAsset(<?= $a['id'] ?>)">›</td></tr>
                            <?php endforeach; ?>
                            <?php if(empty($under_service_repo)): ?><tr><td colspan="7"><div class="am-empty-msg">No assets under service found.</div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="am-pane" id="pane-junk-assets">
            <div class="am-section-head">
                <h3><span class="am-back-btn" onclick="showPane('others')">&#9664; Junk Assets</span></h3>
            </div>
            <div class="am-card">
                <div class="am-table-wrap">
                    <table class="am-table">
                        <thead><tr><th>S.No</th><th>Category</th><th>Asset Type</th><th>Asset ID</th><th>Product Serial No.</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($junk_repo as $i => $a): ?>
                            <tr><td><?= $i + 1 ?></td><td><?= htmlspecialchars($a['category']) ?></td><td><?= htmlspecialchars($a['asset_type']) ?></td><td><?= htmlspecialchars($a['asset_id']) ?></td><td><?= htmlspecialchars($a['serial_no']) ?></td><td><span class="am-badge inactive"><?= htmlspecialchars($a['status']) ?></span></td><td class="am-arrow" onclick="viewAsset(<?= $a['id'] ?>)">›</td></tr>
                            <?php endforeach; ?>
                            <?php if(empty($junk_repo)): ?><tr><td colspan="7"><div class="am-empty-msg">No junk assets found.</div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="am-pane" id="pane-missing-assets">
            <div class="am-section-head">
                <h3><span class="am-back-btn" onclick="showPane('others')">&#9664; Missing Assets</span></h3>
            </div>
            <div class="am-card">
                <div class="am-table-wrap">
                    <table class="am-table">
                        <thead><tr><th>S.No</th><th>Category</th><th>Asset Type</th><th>Asset ID</th><th>Product Serial No.</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($missing_repo as $i => $a): ?>
                            <tr><td><?= $i + 1 ?></td><td><?= htmlspecialchars($a['category']) ?></td><td><?= htmlspecialchars($a['asset_type']) ?></td><td><?= htmlspecialchars($a['asset_id']) ?></td><td><?= htmlspecialchars($a['serial_no']) ?></td><td><span class="am-badge inactive"><?= htmlspecialchars($a['status']) ?></span></td><td class="am-arrow" onclick="viewAsset(<?= $a['id'] ?>)">›</td></tr>
                            <?php endforeach; ?>
                            <?php if(empty($missing_repo)): ?><tr><td colspan="7"><div class="am-empty-msg">No missing assets found.</div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="am-pane" id="pane-asset-details">
            <div class="am-detail-head">
                <span class="am-back-btn" onclick="showPane('asset-repo')">
                    &#9664; &nbsp; <span id="ad-header-title" style="font-weight:600; font-size:16px;">Asset Name</span> &nbsp;
                    <span class="am-badge active" id="ad-header-status">Active</span>
                </span>
                
                <span class="am-edit-link" onclick="openEditAssetModal()">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/></svg>
                    Edit Details
                </span>
            </div>

            <div class="am-subtabs" style="margin-bottom: 20px;">
                <div class="am-subtab active" onclick="switchAdTab(this, 'ad-details')">Asset Details</div>
                <div class="am-subtab" onclick="switchAdTab(this, 'ad-service')">Asset Service Logs</div>
                <div class="am-subtab" onclick="switchAdTab(this, 'ad-history')">Assign History Logs</div>
            </div>

            <div id="ad-details" class="ad-inner-pane active">
                <div class="am-card" style="box-shadow:none; border: 1px solid #e2e8f0;">
                    <div class="am-form-grid" style="grid-template-columns: repeat(4, 1fr); gap: 24px;">
                        <div class="am-field"><label>Asset Type</label><div class="am-field-readonly" id="ad-val-type"></div></div>
                        <div class="am-field"><label>Status</label><div class="am-field-readonly" id="ad-val-status"></div></div>
                        <div class="am-field"><label>Model Name</label><div class="am-field-readonly" id="ad-val-model"></div></div>
                        <div class="am-field"><label>Product Serial Number</label><div class="am-field-readonly" id="ad-val-serial"></div></div>

                        <div class="am-field"><label>Vendor Name</label><div class="am-field-readonly" id="ad-val-vendor">—</div></div>
                        <div class="am-field"><label>Purchase Date</label><div class="am-field-readonly" id="ad-val-purchase"></div></div>
                        <div class="am-field"><label>Device Brand</label><div class="am-field-readonly" id="ad-val-brand">—</div></div>
                        <div class="am-field"><label>Device Model No</label><div class="am-field-readonly" id="ad-val-modelno">—</div></div>

                        <div class="am-field"><label>Department</label><div class="am-field-readonly" id="ad-val-dept">—</div></div>
                        <div class="am-field"><label>Procurement Location</label><div class="am-field-readonly" id="ad-val-procloc"></div></div>
                        <div class="am-field"><label>Current Location</label><div class="am-field-readonly" id="ad-val-currloc"></div></div>
                        <div class="am-field"><label>Tags</label><div class="am-field-readonly" id="ad-val-tags">—</div></div>

                        <div class="am-field"><label>Assigned To</label><div class="am-field-readonly" id="ad-val-assignedto"></div></div>
                        <div class="am-field"><label>Assigned Date</label><div class="am-field-readonly" id="ad-val-assigndate">—</div></div>
                        <div class="am-field" style="grid-column: span 2;"><label>Notes</label><div class="am-field-readonly" id="ad-val-notes">—</div></div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; display: flex; justify-content: space-between;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="update_asset_status">
                        <input type="hidden" name="asset_id" id="form-asset-id-missing">
                        <input type="hidden" name="new_status" value="Missing">
                        <button type="submit" class="btn-red-outline">Mark As Missing</button>
                    </form>
                    <div>
                        <form method="POST" style="display:inline; margin-right:10px;">
                            <input type="hidden" name="action" value="update_asset_status">
                            <input type="hidden" name="asset_id" id="form-asset-id-junk">
                            <input type="hidden" name="new_status" value="Junk">
                            <button type="submit" class="btn-outline">Move To Junk</button>
                        </form>
                        <button class="btn-blue">Add Service Detail</button>
                    </div>
                </div>
            </div>

            <div id="ad-service" class="ad-inner-pane" style="display:none;">
                <div class="am-card" style="box-shadow:none; border: 1px solid #e2e8f0; min-height:300px; display:flex; align-items:center; justify-content:center;">
                    <div class="am-empty-msg">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#cbd5e0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:15px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
                        <br><span style="font-size:14px;">No data found</span>
                    </div>
                </div>
            </div>

            <div id="ad-history" class="ad-inner-pane" style="display:none;">
                <div class="am-card" style="box-shadow:none; border: 1px solid #e2e8f0;">
                    <div class="am-table-wrap">
                        <table class="am-table">
                            <thead><tr><th>S.No</th><th>Employee Name</th><th>Assigned Date</th><th>Recovery Date</th><th>Remarks</th></tr></thead>
                            <tbody id="ad-history-tbody">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="am-modal-overlay" id="modalAddAssetType">
        <div class="am-modal">
            <h3>Add Asset Type</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_asset_type">
                <div class="am-form-grid">
                    <div class="am-field"><label>Name for Asset Type *</label><input type="text" name="name" required></div>
                    <div class="am-field"><label>Category</label><select name="category"><option>Laptops & PC</option><option>Mobiles & Tabs</option><option>Furniture</option><option>Electronics</option><option>Others</option></select></div>
                    <div class="am-field"><label>Remarks</label><input type="text" name="remarks" placeholder="Type here"></div>
                    <div class="am-field"><label><input type="checkbox" name="warranty_applicable"> Warranty Applicable</label></div>
                    <div class="am-field"><label><input type="checkbox" name="service_applicable"> Service Applicable</label></div>
                </div>
                <div class="am-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('modalAddAssetType')">Cancel</button>
                    <button type="submit" class="btn-blue">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="am-modal-overlay" id="modalAddAsset">
        <div class="am-modal">
            <h3>Add Asset</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_asset">
                <div class="am-form-grid">
                    <div class="am-field">
                        <label>Asset Type *</label>
                        <select name="asset_type_id" required>
                            <option value="">Select…</option>
                            <?php foreach ($asset_types as $at): ?>
                            <option value="<?= $at['id'] ?>"><?= htmlspecialchars($at['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="am-field"><label>Product Serial No.</label><input type="text" name="serial_no"></div>
                    <div class="am-field"><label>Acquired Date</label><input type="date" name="acquired_date"></div>
                    <div class="am-field">
                        <label>Location</label>
                        <select name="location" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="am-field">
                        <label>Status</label>
                        <select name="status">
                            <option>Active</option>
                            <option>Inactive</option>
                            <option>Out for Service</option>
                        </select>
                    </div>
                </div>
                <div class="am-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('modalAddAsset')">Cancel</button>
                    <button type="submit" class="btn-blue">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="am-modal-overlay" id="modalEditAsset">
        <div class="am-modal">
            <h3>Edit Asset</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_asset">
                <input type="hidden" name="asset_id" id="edit_asset_id">
                <div class="am-form-grid">
                    <div class="am-field">
                        <label>Asset Type *</label>
                        <select name="asset_type_id" id="edit_asset_type_id" required>
                            <option value="">Select…</option>
                            <?php foreach ($asset_types as $at): ?>
                            <option value="<?= $at['id'] ?>"><?= htmlspecialchars($at['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="am-field"><label>Product Serial No.</label><input type="text" name="serial_no" id="edit_asset_serial_no"></div>
                    <div class="am-field"><label>Acquired Date</label><input type="date" name="acquired_date" id="edit_asset_acquired_date"></div>
                    <div class="am-field">
                        <label>Location</label>
                        <select name="location" id="edit_asset_location" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="am-field">
                        <label>Status</label>
                        <select name="status" id="edit_asset_status">
                            <option>Active</option>
                            <option>Inactive</option>
                            <option>Under Service</option>
                            <option>Out for Service</option>
                            <option>Missing</option>
                            <option>Junk</option>
                        </select>
                    </div>
                </div>
                <div class="am-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('modalEditAsset')">Cancel</button>
                    <button type="submit" class="btn-blue">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="am-modal-overlay" id="modalAssignAsset">
        <div class="am-modal">
            <h3>Assign Asset</h3>
            <form method="POST">
                <input type="hidden" name="action" value="assign_asset">
                <div class="am-form-grid">
                    <div class="am-field am-autocomplete-container">
                        <label>Employee *</label>
                        <input type="hidden" name="employee_id" id="hidden_employee_id" required>
                        <input type="text" id="employee_search_input" placeholder="Type name or code..." autocomplete="off" required>
                        <div id="employee_dropdown" class="am-autocomplete-list"></div>
                    </div>
                    <div class="am-field">
                        <label>Asset *</label>
                        <select name="asset_id" required>
                            <option value="">Select asset…</option>
                            <?php if(!empty($unassigned_repo)): ?>
                                <?php foreach ($unassigned_repo as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['asset_type'].' - '.$a['serial_no']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="am-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('modalAssignAsset')">Cancel</button>
                    <button type="submit" class="btn-blue">Assign</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
// --- Data Injection ---
const ALL_ASSETS = <?= json_encode($all_assets_full, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const ASSIGN_HISTORY = <?= json_encode($history_logs, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const ALL_EMPLOYEES = <?= !empty($all_employees) ? json_encode($all_employees, JSON_HEX_APOS | JSON_HEX_QUOT) : '[]' ?>;

let currentViewedAsset = null;
let currentAssetType = null; // Storing the selected Asset Type globally so Edit Mode can find the ID.

// --- Tab Switching Navigation ---
function showPane(paneId) {
    document.querySelectorAll('.am-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('pane-' + paneId).classList.add('active');
    
    const tabLink = document.querySelector(`.am-tab[data-pane='${paneId}']`);
    if(tabLink) {
        document.querySelectorAll('.am-tab').forEach(t => t.classList.remove('active'));
        tabLink.classList.add('active');
    } else {
        document.querySelectorAll('.am-tab').forEach(t => t.classList.remove('active'));
    }
}

document.querySelectorAll('.am-tab').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        showPane(this.dataset.pane);
    });
});

// --- Asset Detail Full View & Edit Logic ---
function viewAsset(assetId) {
    const asset = ALL_ASSETS.find(a => parseInt(a.id) === assetId);
    if(!asset) return;
    
    currentViewedAsset = asset;

    // Header updates
    document.getElementById('ad-header-title').textContent = asset.asset_type_name || 'Asset';
    
    const statusEl = document.getElementById('ad-header-status');
    statusEl.textContent = asset.status || 'Unknown';
    if(asset.status === 'Active') {
        statusEl.className = 'am-badge active';
    } else if(asset.status === 'Inactive' || asset.status === 'Junk' || asset.status === 'Missing') {
        statusEl.className = 'am-badge inactive';
    } else {
        statusEl.className = 'am-badge out';
    }

    // Grid mappings
    document.getElementById('ad-val-type').textContent = asset.asset_type_name || '—';
    document.getElementById('ad-val-status').textContent = asset.status || '—';
    document.getElementById('ad-val-model').textContent = asset.asset_type_name || '—'; 
    document.getElementById('ad-val-serial').textContent = asset.serial_no || '—';
    document.getElementById('ad-val-purchase').textContent = asset.acquired_date || '—';
    document.getElementById('ad-val-procloc').textContent = asset.location || '—';
    document.getElementById('ad-val-currloc').textContent = asset.location || '—';
    
    if (asset.employee_name) {
        document.getElementById('ad-val-assignedto').textContent = asset.employee_name + (asset.employee_code ? ' - ' + asset.employee_code : '');
    } else {
        document.getElementById('ad-val-assignedto').textContent = '—';
    }

    // Update form action hidden inputs
    document.getElementById('form-asset-id-missing').value = asset.id;
    document.getElementById('form-asset-id-junk').value = asset.id;

    // Load History Table
    const histTbody = document.getElementById('ad-history-tbody');
    histTbody.innerHTML = '';
    const assetHistory = ASSIGN_HISTORY.filter(h => parseInt(h.asset_id) === assetId);
    
    if (assetHistory.length === 0) {
        histTbody.innerHTML = '<tr><td colspan="5"><div class="am-empty-msg" style="padding:30px 0;">No history logs found.</div></td></tr>';
    } else {
        assetHistory.forEach((log, index) => {
            histTbody.innerHTML += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${log.employee_name || '—'}</td>
                    <td>${log.assigned_date || '—'}</td>
                    <td>${log.recovery_date || '—'}</td>
                    <td>${log.remarks || '—'}</td>
                </tr>
            `;
        });
    }

    document.querySelectorAll('.am-subtab[onclick^="switchAdTab"]').forEach((t, i) => {
        if(i===0) t.classList.add('active'); else t.classList.remove('active');
    });
    document.querySelectorAll('.ad-inner-pane').forEach((p, i) => {
        if(i===0) p.style.display = 'block'; else p.style.display = 'none';
    });

    showPane('asset-details');
}

function openEditAssetModal() {
    if (!currentViewedAsset) return;
    
    // Inject current values into the edit modal inputs
    document.getElementById('edit_asset_id').value = currentViewedAsset.id;
    document.getElementById('edit_asset_type_id').value = currentViewedAsset.asset_type_id;
    document.getElementById('edit_asset_serial_no').value = currentViewedAsset.serial_no || '';
    document.getElementById('edit_asset_acquired_date').value = currentViewedAsset.acquired_date || '';
    document.getElementById('edit_asset_location').value = currentViewedAsset.location || '';
    document.getElementById('edit_asset_status').value = currentViewedAsset.status || 'Active';
    
    openModal('modalEditAsset');
}

function switchAdTab(el, targetId) {
    el.parentElement.querySelectorAll('.am-subtab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.ad-inner-pane').forEach(p => p.style.display = 'none');
    document.getElementById(targetId).style.display = 'block';
}


// --- Employee Asset Allocation Logic (View Assets by Employee) ---
function viewEmployeeAssets(empCode) {
    // Switch to Asset Repo Tab
    showPane('asset-repo');
    
    // Switch to 'Assigned Assets' Subtab internally
    const assignedSubtab = document.querySelector('.am-subtab[onclick*="repo-assigned"]');
    if(assignedSubtab) switchSubtab(assignedSubtab, 'repo-assigned');

    // Filter table by employee code
    const searchInput = document.getElementById('repoSearch');
    if(searchInput) {
        searchInput.value = empCode;
        filterRepoTable();
    }
}

// --- Autocomplete JS for Assign Asset Modal ---
const searchInput = document.getElementById('employee_search_input');
const hiddenIdInput = document.getElementById('hidden_employee_id');
const dropdownList = document.getElementById('employee_dropdown');

searchInput.addEventListener('input', function() {
    const val = this.value.toLowerCase().trim();
    dropdownList.innerHTML = '';
    if (!val) {
        dropdownList.style.display = 'none';
        hiddenIdInput.value = '';
        return;
    }
    const filtered = ALL_EMPLOYEES.filter(emp => 
        (emp.employee_name && emp.employee_name.toLowerCase().includes(val)) || 
        (emp.employee_code && emp.employee_code.toLowerCase().includes(val))
    );
    if (filtered.length > 0) {
        filtered.forEach(emp => {
            const item = document.createElement('div');
            item.className = 'am-autocomplete-item';
            item.textContent = `${emp.employee_name} (${emp.employee_code})`;
            item.onclick = function() {
                searchInput.value = this.textContent;
                hiddenIdInput.value = emp.id;
                dropdownList.style.display = 'none';
            };
            dropdownList.appendChild(item);
        });
        dropdownList.style.display = 'block';
    } else {
        dropdownList.innerHTML = '<div style="padding: 8px 12px; color: #a0aec0; font-size: 12px;">No employee found</div>';
        dropdownList.style.display = 'block';
        hiddenIdInput.value = '';
    }
});
document.addEventListener('click', function(e) {
    if (e.target !== searchInput && e.target !== dropdownList) {
        dropdownList.style.display = 'none';
    }
});

// --- CSV Export Logic ---
function exportCSV() {
    // 1. Determine which repository tab is currently active
    let isAssignedActive = document.getElementById('repo-assigned').style.display !== 'none';
    let tableId = isAssignedActive ? 'assignedTable' : 'unassignedTable';
    let table = document.getElementById(tableId);

    if (!table) {
        console.error("Table not found for CSV export.");
        return;
    }

    let csv = [];
    let rows = table.querySelectorAll('tr');

    // 2. Loop through all rows in the active table
    for (let i = 0; i < rows.length; i++) {
        // Skip rows that are hidden by the search filter
        if (rows[i].style.display === 'none') continue;

        let rowData = [];
        let cols = rows[i].querySelectorAll('td, th');

        // 3. Loop through columns, skipping the last one (the '›' arrow column)
        for (let j = 0; j < cols.length - 1; j++) {
            // Grab the text, escape any double quotes, and wrap in quotes to safely handle inner commas
            let cellText = cols[j].innerText.trim().replace(/"/g, '""');
            rowData.push('"' + cellText + '"');
        }
        
        // 4. Add the formatted row array to the main CSV array
        if (rowData.length > 0) {
            csv.push(rowData.join(','));
        }
    }

    // 5. Create the CSV Blob and trigger the download
    let csvContent = csv.join('\n');
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    let url = URL.createObjectURL(blob);

    let link = document.createElement('a');
    link.href = url;
    
    // Dynamically name the file based on the active tab and current date
    let dateStr = new Date().toISOString().split('T')[0];
    let fileName = (isAssignedActive ? 'Assigned_Assets_' : 'Unassigned_Assets_') + dateStr + '.csv';
    
    link.setAttribute('download', fileName);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// --- Asset Type Settings Logic ---
function showAssetTypeDetail(at) {
    currentAssetType = at; // SAVE THE CURRENT ASSET TYPE

    document.getElementById('at-list-view').style.display = 'none';
    document.getElementById('at-detail-view').style.display = 'block';
    document.getElementById('at-edit-view').style.display = 'none';

    document.getElementById('at-detail-name').textContent = at.name;
    document.getElementById('detail-name').textContent = at.name;
    document.getElementById('detail-category').textContent = at.category || '—';
    document.getElementById('detail-remarks').textContent = at.remarks || '—';
    document.getElementById('detail-warranty').checked = !!parseInt(at.warranty_applicable);
    document.getElementById('detail-service').checked = !!parseInt(at.service_applicable);
}

function backToAssetTypeList() {
    document.getElementById('at-list-view').style.display = 'block';
    document.getElementById('at-detail-view').style.display = 'none';
    document.getElementById('at-edit-view').style.display = 'none';
}

function switchToEditMode() {
    if (!currentAssetType) return; // Prevent errors if not set
    
    document.getElementById('at-detail-view').style.display = 'none';
    document.getElementById('at-edit-view').style.display = 'block';
    
    // Inject the ID into the hidden input so update/delete works
    document.getElementById('edit-at-id').value = currentAssetType.id;

    // Prefill inputs
    const name = document.getElementById('detail-name').textContent;
    document.getElementById('at-edit-name').textContent = name;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-category').value = currentAssetType.category || 'Laptops & PC';
    document.getElementById('edit-remarks').value = currentAssetType.remarks || '';
}

function switchToDetailMode() {
    document.getElementById('at-detail-view').style.display = 'block';
    document.getElementById('at-edit-view').style.display = 'none';
}

// --- SweetAlert Confirm Remove Asset Type ---
function confirmRemoveAssetType() {
    Swal.fire({
        title: 'Are you sure?',
        text: "You are about to remove this Asset Type. This cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e53e3e', // Red matching the button
        cancelButtonColor: '#718096',  // Gray 
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Find the correct form and swap the action value to delete
            const form = document.getElementById('at-edit-form');
            document.getElementById('at-edit-action').value = 'delete_asset_type';
            form.submit();
        }
    });
}

// --- Charts & Table Filtering ---
const CAT_LABELS = <?= $chart_cat_labels ?>;
const CAT_DATA = <?= $chart_cat_data ?>;
const LOC_LABELS = <?= $chart_loc_labels ?>;
const LOC_DATA = <?= $chart_loc_data ?>;
let distChart = null;
function initChart(labels, data) {
    const ctx = document.getElementById('distChart').getContext('2d');
    if (distChart) distChart.destroy();
    distChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: labels.map((_, i) => i === 0 ? '#3182CE' : '#90CDF4'), borderRadius: 4, barPercentage: 0.5 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f0f2f5' } }, x: { grid: { display: false } } } }
    });
}
initChart(CAT_LABELS, CAT_DATA);
function switchChart(mode, btn) {
    document.querySelectorAll('.am-chart-toggle button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    if (mode === 'category') initChart(CAT_LABELS, CAT_DATA);
    else initChart(LOC_LABELS, LOC_DATA);
}

function switchSubtab(el, target) {
    el.parentElement.querySelectorAll('.am-subtab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('repo-assigned').style.display = target === 'repo-assigned' ? '' : 'none';
    document.getElementById('repo-unassigned').style.display = target === 'repo-unassigned' ? '' : 'none';
}

function filterRepoTable() {
    const q = document.getElementById('repoSearch').value.toLowerCase();
    document.querySelectorAll('#assignedTable tbody tr, #unassignedTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function filterEmpTable(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#empTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// --- General Modals ---
function openModal(id) { 
    document.getElementById(id).classList.add('open'); 
    if(id === 'modalAssignAsset') {
        document.getElementById('employee_search_input').value = '';
        document.getElementById('hidden_employee_id').value = '';
        document.getElementById('employee_dropdown').style.display = 'none';
    }
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.am-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>