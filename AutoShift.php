<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Auto Shift Configuration';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

function e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function timeToDb($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '--') return null;

    if (preg_match('/^\d{2}:\d{2}$/', $v)) {
        return $v . ':00';
    }

    $ts = strtotime($v);
    return $ts ? date('H:i:s', $ts) : null;
}

function timeToView($v) {
    if (empty($v)) return '--';
    return date('h:i A', strtotime($v));
}

function timeToInput($v) {
    if (empty($v)) return '';
    return date('H:i', strtotime($v));
}

/* ════════ DB TABLES ════════ */
$conn->query("
CREATE TABLE IF NOT EXISTS auto_shift_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_name VARCHAR(100) NOT NULL,
    config_code VARCHAR(30) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_config_code (config_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS auto_shift_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cfg_id INT NOT NULL,
    shift_name VARCHAR(100) NOT NULL,
    direction ENUM('IN','OUT') DEFAULT 'IN',
    in_start TIME NULL,
    in_end TIME NULL,
    out_start TIME NULL,
    out_end TIME NULL,
    next_day TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cfg_id (cfg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ════════ DUMMY DATA ════════ */
$count = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM auto_shift_configs");
if ($res) {
    $count = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($count === 0) {
    $stmt = $conn->prepare("
        INSERT INTO auto_shift_configs
        (config_name, config_code, status)
        VALUES (?, ?, 'active')
    ");

    $n1 = 'Auto Shift';
    $c1 = 'AS';
    $stmt->bind_param("ss", $n1, $c1);
    $stmt->execute();
    $cfg1 = $stmt->insert_id;

    $n2 = 'Auto Shift 1';
    $c2 = 'AS1';
    $stmt->bind_param("ss", $n2, $c2);
    $stmt->execute();
    $cfg2 = $stmt->insert_id;

    $stmt->close();

    $dummyRules = [
        ['General 9AM',     'IN', '09:00:00', '09:30:00', null, null, 0, 1],
        ['General 9:30AM',  'IN', '09:30:00', '10:00:00', null, null, 0, 2],
        ['General 10 AM',   'IN', '10:00:00', '10:30:00', null, null, 0, 3],
        ['General 10:30AM', 'IN', '10:30:00', '11:00:00', null, null, 0, 4],
        ['General 11:00 AM','IN', '11:00:00', '11:30:00', null, null, 0, 5],
        ['General 11:30 AM','IN', '11:30:00', '13:30:00', null, null, 0, 6],
        ['General 8AM',     'IN', '08:00:00', '08:30:00', null, null, 0, 7],
        ['General 7AM',     'IN', '07:00:00', '07:30:00', null, null, 0, 8],
    ];

    $stmt = $conn->prepare("
        INSERT INTO auto_shift_rules
        (cfg_id, shift_name, direction, in_start, in_end, out_start, out_end, next_day, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($dummyRules as $r) {
        $stmt->bind_param(
            "issssssii",
            $cfg1,
            $r[0],
            $r[1],
            $r[2],
            $r[3],
            $r[4],
            $r[5],
            $r[6],
            $r[7]
        );
        $stmt->execute();
    }

    $stmt->close();
}

/* ════════ STATE ════════ */
$active_id  = (int)($_GET['id'] ?? 0);
$mode       = $_GET['mode'] ?? 'view';
$flash      = '';
$flash_type = 'success';

/* ════════ POST HANDLER ════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_asc') {
        $edit_id = (int)($_POST['edit_id'] ?? 0);
        $name = trim($_POST['asc_name'] ?? '');
        $code = strtoupper(trim($_POST['asc_code'] ?? ''));

        if ($name === '') {
            $flash = 'Auto Shift Configuration Name is required.';
            $flash_type = 'error';
            $mode = $edit_id > 0 ? 'edit' : 'add';
            $active_id = $edit_id;
        } elseif ($edit_id > 0) {
            $stmt = $conn->prepare("
                UPDATE auto_shift_configs
                SET config_name=?, config_code=?, updated_at=NOW()
                WHERE id=?
            ");

            if (!$stmt) {
                $flash = 'Update prepare failed: ' . $conn->error;
                $flash_type = 'error';
            } else {
                $stmt->bind_param("ssi", $name, $code, $edit_id);

                if ($stmt->execute()) {
                    header("Location: AutoShift?id=" . $edit_id . "&updated=1");
                    exit;
                } else {
                    $flash = $stmt->errno === 1062 ? 'Configuration code already exists.' : 'Update failed: ' . $stmt->error;
                    $flash_type = 'error';
                }

                $stmt->close();
            }

            $active_id = $edit_id;
            $mode = 'edit';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO auto_shift_configs
                (config_name, config_code, status)
                VALUES (?, ?, 'active')
            ");

            if (!$stmt) {
                $flash = 'Insert prepare failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'add';
            } else {
                $stmt->bind_param("ss", $name, $code);

                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    header("Location: AutoShift?id=" . $newId . "&saved=1");
                    exit;
                } else {
                    $flash = $stmt->errno === 1062 ? 'Configuration code already exists.' : 'Save failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'add';
                }

                $stmt->close();
            }
        }
    }

    if ($action === 'save_shift_rule') {
        $rule_id   = (int)($_POST['rule_id'] ?? 0);
        $cfg_id    = (int)($_POST['cfg_id'] ?? 0);
        $direction = strtoupper(trim($_POST['direction'] ?? 'IN'));
        $shiftName = trim($_POST['shift_name'] ?? '');
        $inStart   = timeToDb($_POST['in_start'] ?? '');
        $inEnd     = timeToDb($_POST['in_end'] ?? '');
        $outStart  = timeToDb($_POST['out_start'] ?? '');
        $outEnd    = timeToDb($_POST['out_end'] ?? '');
        $nextDay   = isset($_POST['next_day']) ? 1 : 0;

        if (!in_array($direction, ['IN','OUT'], true)) {
            $direction = 'IN';
        }

        if ($cfg_id <= 0 || $shiftName === '') {
            $flash = 'Shift name is required.';
            $flash_type = 'error';
            $active_id = $cfg_id;
            $mode = 'view';
        } elseif ($rule_id > 0) {
            $stmt = $conn->prepare("
                UPDATE auto_shift_rules
                SET direction=?,
                    shift_name=?,
                    in_start=?,
                    in_end=?,
                    out_start=?,
                    out_end=?,
                    next_day=?,
                    updated_at=NOW()
                WHERE id=? AND cfg_id=?
            ");

            if (!$stmt) {
                $flash = 'Rule update prepare failed: ' . $conn->error;
                $flash_type = 'error';
            } else {
                $stmt->bind_param(
                    "ssssssiii",
                    $direction,
                    $shiftName,
                    $inStart,
                    $inEnd,
                    $outStart,
                    $outEnd,
                    $nextDay,
                    $rule_id,
                    $cfg_id
                );

                if ($stmt->execute()) {
                    header("Location: AutoShift?id=" . $cfg_id . "&rule_saved=1");
                    exit;
                } else {
                    $flash = 'Rule save failed: ' . $stmt->error;
                    $flash_type = 'error';
                }

                $stmt->close();
            }

            $active_id = $cfg_id;
            $mode = 'view';
        } else {
            $sort_order = 100;

            $stmt = $conn->prepare("
                INSERT INTO auto_shift_rules
                (cfg_id, shift_name, direction, in_start, in_end, out_start, out_end, next_day, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $flash = 'Rule insert prepare failed: ' . $conn->error;
                $flash_type = 'error';
            } else {
                $stmt->bind_param(
                    "issssssii",
                    $cfg_id,
                    $shiftName,
                    $direction,
                    $inStart,
                    $inEnd,
                    $outStart,
                    $outEnd,
                    $nextDay,
                    $sort_order
                );

                if ($stmt->execute()) {
                    header("Location: AutoShift?id=" . $cfg_id . "&rule_added=1");
                    exit;
                } else {
                    $flash = 'Rule add failed: ' . $stmt->error;
                    $flash_type = 'error';
                }

                $stmt->close();
            }

            $active_id = $cfg_id;
            $mode = 'view';
        }
    }

    if ($action === 'delete_shift_rule') {
        $rule_id = (int)($_POST['rule_id'] ?? 0);
        $cfg_id  = (int)($_POST['cfg_id'] ?? 0);

        if ($rule_id > 0 && $cfg_id > 0) {
            $stmt = $conn->prepare("DELETE FROM auto_shift_rules WHERE id=? AND cfg_id=?");
            if ($stmt) {
                $stmt->bind_param("ii", $rule_id, $cfg_id);

                if ($stmt->execute()) {
                    header("Location: AutoShift?id=" . $cfg_id . "&rule_deleted=1");
                    exit;
                } else {
                    $flash = 'Delete failed: ' . $stmt->error;
                    $flash_type = 'error';
                }

                $stmt->close();
            }
        }

        $active_id = $cfg_id;
        $mode = 'view';
    }
}

/* ════════ FETCH FROM DB ════════ */
$configs = [];
$res = $conn->query("
    SELECT id, config_name, config_code, status
    FROM auto_shift_configs
    WHERE status='active'
    ORDER BY id ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $configs[] = $row;
    }
}

if ($active_id === 0 && $mode === 'view' && count($configs)) {
    $active_id = (int)$configs[0]['id'];
}

$active_cfg = null;
foreach ($configs as $c) {
    if ((int)$c['id'] === (int)$active_id) {
        $active_cfg = $c;
        break;
    }
}

$rules = [];
if ($active_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, cfg_id, shift_name, direction, in_start, in_end, out_start, out_end, next_day
        FROM auto_shift_rules
        WHERE cfg_id=?
        ORDER BY sort_order ASC, in_start ASC, id ASC
    ");

    if ($stmt) {
        $stmt->bind_param("i", $active_id);
        $stmt->execute();
        $rs = $stmt->get_result();

        while ($row = $rs->fetch_assoc()) {
            $rules[] = $row;
        }

        $stmt->close();
    }
}

$shift_list = [];
$resShift = $conn->query("
    SELECT shift_name
    FROM att_shifts
    WHERE status='active'
    ORDER BY shift_name ASC
");
if ($resShift) {
    while ($s = $resShift->fetch_assoc()) {
        $shift_list[] = $s['shift_name'];
    }
}

if (empty($shift_list)) {
    $shift_list = [
        'General 9AM',
        'General 9:30AM',
        'General 10 AM',
        'General 10:30AM',
        'General 11:00 AM',
        'General 11:30 AM',
        'General 8AM',
        'General 7AM',
        'Night shift',
        'Weekoff & Holiday Worked'
    ];
}

if (!empty($_GET['saved'])) {
    $flash = 'Auto Shift Configuration added successfully.';
    $flash_type = 'success';
}
if (!empty($_GET['updated'])) {
    $flash = 'Auto Shift Configuration updated successfully.';
    $flash_type = 'success';
}
if (!empty($_GET['rule_saved'])) {
    $flash = 'Shift rule saved successfully.';
    $flash_type = 'success';
}
if (!empty($_GET['rule_added'])) {
    $flash = 'Shift rule added successfully.';
    $flash_type = 'success';
}
if (!empty($_GET['rule_deleted'])) {
    $flash = 'Shift rule deleted successfully.';
    $flash_type = 'success';
}

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ── Config nav tabs ── */
.cfg-tabs {
    display: flex;
    align-items: center;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
    overflow-x: auto;
    scrollbar-width: none
}

.cfg-tabs::-webkit-scrollbar {
    display: none
}

.cfg-tab {
    padding: 14px 20px;
    font-size: 13.5px;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    border: none;
    background: transparent;
    border-bottom: 2.5px solid transparent;
    white-space: nowrap;
    transition: color .15s, border-color .15s;
    text-decoration: none;
    display: block;
    margin-bottom: -1px
}

.cfg-tab:hover {
    color: #111827
}

.cfg-tab.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
    font-weight: 600
}

/* ── Page ── */
.asc-wrapper {
    font-family: 'Segoe UI', sans-serif;
    color: #1e2d3d;
    padding: 0 0 40px
}

.asc-inner {
    padding: 20px 28px
}

/* topbar */
.asc-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px
}

.asc-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    color: #555
}

.asc-breadcrumb a {
    color: #1e2d3d;
    text-decoration: none;
    font-weight: 600
}

.asc-breadcrumb a:hover {
    text-decoration: underline
}

.asc-breadcrumb .sep {
    color: #bbb;
    font-size: 11px
}

.btn-add-asc {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 9px 18px;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background .16s
}

.btn-add-asc:hover {
    background: #1d4ed8
}

/* split panel */
.asc-panel {
    display: flex;
    background: #fff;
    border: 1px solid #e8ecf0;
    border-radius: 10px;
    overflow: hidden;
    min-height: 560px
}

/* left */
.asc-list-col {
    width: 35%;
    min-width: 220px;
    border-right: 1px solid #e8ecf0;
    display: flex;
    flex-direction: column
}

.asc-list-heading {
    padding: 14px 16px 12px;
    font-size: 12px;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .4px;
    border-bottom: 1px solid #f1f4f8
}

.asc-list-scroll {
    flex: 1;
    overflow-y: auto;
    max-height: 600px
}

.asc-list-scroll::-webkit-scrollbar {
    width: 4px
}

.asc-list-scroll::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px
}

.asc-item {
    padding: 13px 16px;
    border-bottom: 1px solid #f1f4f8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background .12s
}

.asc-item:last-child {
    border-bottom: none
}

.asc-item:hover {
    background: #f8fafc
}

.asc-item.active {
    background: #eff6ff;
    border-left: 3px solid #2563eb;
    padding-left: 13px
}

.asc-item-name {
    font-size: 13.5px;
    font-weight: 500;
    color: #1e2d3d
}

.asc-item.active .asc-item-name {
    color: #2563eb;
    font-weight: 700
}

.asc-item-chevron {
    font-size: 11px;
    color: #9ca3af
}

/* right */
.asc-detail-col {
    flex: 1;
    padding: 22px 28px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    max-height: 700px
}

.asc-detail-heading {
    font-size: 12px;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .4px;
    border-bottom: 1px solid #e8ecf0;
    padding-bottom: 12px;
    margin-bottom: 20px
}

.asc-detail-title-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px
}

.asc-detail-title {
    font-size: 15px;
    font-weight: 800;
    color: #1e2d3d;
    text-transform: uppercase;
    letter-spacing: .3px
}

.btn-edit-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #2563eb;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    padding: 0
}

.btn-edit-link:hover {
    text-decoration: underline
}

/* field grid */
.asc-field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px 36px;
    margin-bottom: 20px
}

.asc-field label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 6px;
    font-weight: 500
}

.asc-field label .req {
    color: #ef4444;
    margin-right: 2px
}

.asc-field-value {
    font-size: 13.5px;
    color: #1e2d3d;
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
    min-height: 26px
}

.asc-input {
    width: 100%;
    border: none;
    border-bottom: 1.5px solid #d1d5db;
    padding: 8px 2px;
    font-size: 13.5px;
    color: #1e2d3d;
    background: transparent;
    outline: none;
    box-sizing: border-box;
    transition: border-color .16s
}

.asc-input::placeholder {
    color: #c4c9d4
}

.asc-input:focus {
    border-color: #2563eb
}

.asc-select {
    width: 100%;
    border: none;
    border-bottom: 1.5px solid #d1d5db;
    padding: 8px 22px 8px 2px;
    font-size: 13.5px;
    color: #1e2d3d;
    background: transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 4px center;
    outline: none;
    box-sizing: border-box;
    transition: border-color .16s;
    appearance: none;
    cursor: pointer
}

.asc-select:focus {
    border-color: #2563eb
}

/* section sub-title */
.asc-sub-title-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px
}

.asc-sub-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #1e2d3d
}

.asc-sub-note {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 14px
}

.btn-add-inline {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    color: #2563eb;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    padding: 0
}

.btn-add-inline:hover {
    text-decoration: underline
}

/* shifts table */
.asc-shifts-table-wrap {
    border: 1px solid #e8ecf0;
    border-radius: 8px;
    overflow: hidden
}

table.asc-shifts-table {
    width: 100%;
    border-collapse: collapse
}

table.asc-shifts-table thead th {
    background: #f8fafc;
    padding: 11px 14px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    border-bottom: 1px solid #e8ecf0;
    text-transform: uppercase;
    letter-spacing: .3px
}

table.asc-shifts-table tbody tr.shift-row {
    border-bottom: 1px solid #f1f4f8;
    transition: background .12s;
    cursor: pointer
}

table.asc-shifts-table tbody tr.shift-row:hover {
    background: #f9fafb
}

table.asc-shifts-table tbody tr.shift-row.expanded {
    background: #f0f7ff;
    border-bottom: none
}

table.asc-shifts-table tbody td {
    padding: 11px 14px;
    font-size: 13px;
    color: #374151;
    vertical-align: middle
}

.chevron-cell {
    text-align: right;
    width: 32px
}

.chevron-cell i {
    font-size: 12px;
    color: #9ca3af;
    transition: transform .2s
}

.shift-row.expanded .chevron-cell i {
    transform: rotate(90deg)
}

/* inline edit row */
tr.inline-edit-row {
    display: none
}

tr.inline-edit-row.show {
    display: table-row
}

tr.inline-edit-row td {
    padding: 16px 14px 20px;
    background: #f0f7ff;
    border-bottom: 1px solid #e8ecf0
}

.inline-edit-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 16px 20px;
    align-items: end;
    margin-bottom: 14px
}

.inline-edit-grid-row2 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr auto;
    gap: 16px 20px;
    align-items: end;
    margin-bottom: 14px
}

.ie-field label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 6px;
    font-weight: 500
}

.ie-field label .req {
    color: #ef4444;
    margin-right: 2px
}

.ie-time-wrap {
    position: relative
}

.ie-time-wrap input[type=time] {
    width: 100%;
    border: none;
    border-bottom: 1.5px solid #d1d5db;
    padding: 7px 28px 7px 2px;
    font-size: 13.5px;
    color: #1e2d3d;
    background: transparent;
    outline: none;
    box-sizing: border-box;
    transition: border-color .16s;
    cursor: pointer
}

.ie-time-wrap input[type=time]:focus {
    border-color: #2563eb
}

.ie-time-wrap i {
    position: absolute;
    right: 4px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 13px;
    pointer-events: none
}

.ie-nextday {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    color: #374151;
    padding-top: 20px;
    white-space: nowrap
}

.ie-nextday input[type=checkbox] {
    width: 15px;
    height: 15px;
    accent-color: #2563eb;
    cursor: pointer
}

.ie-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 4px
}

.btn-ie-delete {
    padding: 8px 20px;
    border: 1.5px solid #ef4444;
    background: #fff;
    border-radius: 6px;
    font-size: 13px;
    color: #ef4444;
    cursor: pointer;
    font-weight: 600;
    transition: background .14s
}

.btn-ie-delete:hover {
    background: #fee2e2
}

.btn-ie-save {
    padding: 8px 20px;
    background: #2563eb;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    color: #fff;
    cursor: pointer;
    font-weight: 600;
    transition: background .14s
}

.btn-ie-save:hover {
    background: #1d4ed8
}

/* form actions */
.asc-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 22px;
    border-top: 1px solid #e8ecf0;
    margin-top: auto
}

.btn-cancel {
    padding: 9px 26px;
    border: 1.5px solid #d1d5db;
    background: #fff;
    border-radius: 6px;
    font-size: 13.5px;
    color: #374151;
    cursor: pointer;
    font-weight: 600;
    transition: background .14s
}

.btn-cancel:hover {
    background: #f1f5f9
}

.btn-save {
    padding: 9px 26px;
    background: #2563eb;
    border: none;
    border-radius: 6px;
    font-size: 13.5px;
    color: #fff;
    cursor: pointer;
    font-weight: 600;
    transition: background .14s
}

.btn-save:hover {
    background: #1d4ed8
}

/* toast */
.toast-container {
    position: fixed;
    top: 20px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none
}

.toast {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border-radius: 8px;
    padding: 13px 18px;
    box-shadow: 0 4px 18px rgba(0, 0, 0, .14);
    font-size: 13.5px;
    font-weight: 500;
    min-width: 260px;
    pointer-events: all;
    animation: toastIn .25s ease;
    border-left: 4px solid #2563eb;
    color: #1e2d3d
}

.toast.success {
    border-color: #22c55e
}

.toast.error {
    border-color: #ef4444
}

.toast i {
    font-size: 16px
}

.toast.success i {
    color: #22c55e
}

.toast.error i {
    color: #ef4444
}

.toast-close {
    margin-left: auto;
    cursor: pointer;
    color: #9ca3af;
    font-size: 14px;
    background: none;
    border: none;
    padding: 0;
    line-height: 1
}

@keyframes toastIn {
    from {
        transform: translateX(40px);
        opacity: 0
    }

    to {
        transform: translateX(0);
        opacity: 1
    }
}

@keyframes toastOut {
    from {
        opacity: 1
    }

    to {
        opacity: 0;
        transform: translateX(40px)
    }
}

.asc-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 13.5px
}


.cfg-page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
    flex-wrap: wrap;
    gap: 10px;
}

.cfg-page-head h1 {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
}


</style>

<div class="toast-container" id="toastContainer"></div>


<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
    <div class="asc-wrapper">

        <!-- Config nav tabs -->
        <div class="cfg-tabs">
            <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll',
                    'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
            <a href="configuration#<?= e($k) ?>" class="cfg-tab <?= $k==='Attendance'?'active':'' ?>"><?= e($l) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="asc-inner">

            <!-- Top bar -->
            <div class="asc-topbar">
                <nav class="asc-breadcrumb">
                    <a href="configuration#Attendance">Attendance</a>
                    <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
                    <span>Auto Shift Configuration</span>
                </nav>
                <?php if ($mode !== 'add'): ?>
                <button class="btn-add-asc" onclick="setMode('add')">
                    <i class="fa-solid fa-plus"></i> Add Shift Configuration
                </button>
                <?php endif; ?>
            </div>

            <!-- Split panel -->
            <div class="asc-panel">

                <!-- ── Left list ── -->
                <div class="asc-list-col">
                    <div class="asc-list-heading">List of Auto Shift Configuration</div>
                    <div class="asc-list-scroll">
                        <?php foreach ($configs as $cfg): ?>
                        <div class="asc-item <?= ((int)$cfg['id'] === $active_id && $mode !== 'add') ? 'active' : '' ?>"
                            onclick="selectCfg(<?= (int)$cfg['id'] ?>)">
                            <span class="asc-item-name"><?= e($cfg['config_name']) ?></span>
                            <i
                                class="fa-solid <?= ((int)$cfg['id'] === $active_id && $mode !== 'add') ? 'fa-chevron-right' : 'fa-chevron-down' ?> asc-item-chevron"></i>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── Right panel ── -->
                <div class="asc-detail-col">
                    <div class="asc-detail-heading">Auto Shift Configuration Details</div>

                    <?php if ($mode === 'add'): ?>
                    <!-- ════ ADD FORM ════ -->
                    <div class="asc-detail-title" style="margin-bottom:24px">ADD AUTO SHIFT</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_asc">
                        <div class="asc-field-grid">
                            <div class="asc-field">
                                <label><span class="req">*</span> Auto Shift Configuration Name</label>
                                <input type="text" name="asc_name" class="asc-input"
                                    placeholder="Auto Shift Configuration Name"
                                    value="<?= e($_POST['asc_name'] ?? '') ?>" required>
                            </div>
                            <div class="asc-field">
                                <label>Auto Shift Configuration Code</label>
                                <input type="text" name="asc_code" class="asc-input" placeholder="CS"
                                    value="<?= e($_POST['asc_code'] ?? 'CS') ?>">
                            </div>
                        </div>
                        <div class="asc-form-actions">
                            <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
                            <button type="submit" class="btn-save">Add</button>
                        </div>
                    </form>

                    <?php elseif ($mode === 'edit' && $active_cfg): ?>
                    <!-- ════ EDIT HEADER FORM ════ -->
                    <div class="asc-detail-title" style="margin-bottom:24px">
                        EDIT — <?= e($active_cfg['config_name']) ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_asc">
                        <input type="hidden" name="edit_id" value="<?= (int)$active_cfg['id'] ?>">
                        <div class="asc-field-grid">
                            <div class="asc-field">
                                <label><span class="req">*</span> Auto Shift Configuration Name</label>
                                <input type="text" name="asc_name" class="asc-input"
                                    value="<?= e($active_cfg['config_name']) ?>" required>
                            </div>
                            <div class="asc-field">
                                <label>Auto Shift Configuration Code</label>
                                <input type="text" name="asc_code" class="asc-input"
                                    value="<?= e($active_cfg['config_code']) ?>">
                            </div>
                        </div>
                        <div class="asc-form-actions">
                            <button type="button" class="btn-cancel"
                                onclick="window.location.href='AutoShift?id=<?= (int)$active_cfg['id'] ?>&mode=view'">Cancel</button>
                            <button type="submit" class="btn-save">Update</button>
                        </div>
                    </form>

                    <?php elseif ($active_cfg): ?>
                    <!-- ════ VIEW DETAIL ════ -->
                    <div class="asc-detail-title-bar">
                        <div class="asc-detail-title"><?= e($active_cfg['config_name']) ?></div>
                        <button class="btn-edit-link"
                            onclick="window.location.href='AutoShift?id=<?= (int)$active_cfg['id'] ?>&mode=edit'">
                            <i class="fa-regular fa-pen-to-square"></i> Edit Details
                        </button>
                    </div>

                    <!-- Name + Code -->
                    <div class="asc-field-grid" style="margin-bottom:22px">
                        <div class="asc-field">
                            <label>Auto Shift Configuration Name</label>
                            <div class="asc-field-value"><?= e($active_cfg['config_name']) ?></div>
                        </div>
                        <div class="asc-field">
                            <label>Auto Shift Configuration Code</label>
                            <div class="asc-field-value"><?= e($active_cfg['config_code']) ?></div>
                        </div>
                    </div>

                    <!-- Details section -->
                    <div class="asc-sub-title-bar">
                        <div class="asc-sub-title">
                            AUTO SHIFT CONFIGURATION DETAILS - <?= e($active_cfg['config_name']) ?>
                        </div>
                        <button class="btn-add-inline" onclick="addNewRuleRow(<?= (int)$active_cfg['id'] ?>)">
                            <i class="fa-solid fa-plus"></i> Add shifts to auto shift configuration
                        </button>
                    </div>
                    <p class="asc-sub-note">Shifts will be automatically assigned to employees based on these punch In
                        &amp; Out</p>

                    <!-- Shifts table -->
                    <div class="asc-shifts-table-wrap">
                        <table class="asc-shifts-table">
                            <thead>
                                <tr>
                                    <th>Shift Name</th>
                                    <th>Start Time (IN)</th>
                                    <th>End Time (IN)</th>
                                    <th>Start Time (OUT)</th>
                                    <th>End Time (OUT)</th>
                                    <th style="width:32px"></th>
                                </tr>
                            </thead>
                            <tbody id="rulesTbody">
                                <?php foreach ($rules as $rule): ?>
                                <tr class="shift-row" id="row-<?= (int)$rule['id'] ?>"
                                    onclick="toggleRow(<?= (int)$rule['id'] ?>)">
                                    <td><?= e($rule['shift_name']) ?></td>
                                    <td><?= e(timeToView($rule['in_start'])) ?></td>
                                    <td><?= e(timeToView($rule['in_end'])) ?></td>
                                    <td><?= e(timeToView($rule['out_start'])) ?></td>
                                    <td><?= e(timeToView($rule['out_end'])) ?></td>
                                    <td class="chevron-cell"><i class="fa-solid fa-chevron-right"></i></td>
                                </tr>

                                <tr class="inline-edit-row" id="edit-row-<?= (int)$rule['id'] ?>">
                                    <td colspan="6">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="save_shift_rule">
                                            <input type="hidden" name="rule_id" value="<?= (int)$rule['id'] ?>">
                                            <input type="hidden" name="cfg_id" value="<?= (int)$active_cfg['id'] ?>">

                                            <div class="inline-edit-grid">
                                                <div class="ie-field">
                                                    <label><span class="req">*</span> Direction</label>
                                                    <select name="direction" class="asc-select">
                                                        <option value="IN" <?= $rule['direction']==='IN' ?'selected':'' ?>>IN</option>
                                                        <option value="OUT" <?= $rule['direction']==='OUT'?'selected':'' ?>>OUT</option>
                                                    </select>
                                                </div>
                                                <div class="ie-field">
                                                    <label><span class="req">*</span> Shift Name</label>
                                                    <select name="shift_name" class="asc-select">
                                                        <?php foreach ($shift_list as $sn): ?>
                                                        <option value="<?= e($sn) ?>"
                                                            <?= ($rule['shift_name']===$sn)?'selected':'' ?>>
                                                            <?= e($sn) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="ie-field">
                                                    <label><span class="req">*</span> IN Start Time</label>
                                                    <div class="ie-time-wrap">
                                                        <input type="time" name="in_start"
                                                            value="<?= e(timeToInput($rule['in_start'])) ?>">
                                                        <i class="fa-regular fa-clock"></i>
                                                    </div>
                                                </div>
                                                <label class="ie-nextday">
                                                    <input type="checkbox" name="next_day" value="1"
                                                        <?= (int)$rule['next_day'] ? 'checked' : '' ?>> Next Day
                                                </label>
                                            </div>

                                            <div class="inline-edit-grid-row2">
                                                <div class="ie-field">
                                                    <label><span class="req">*</span> IN End Time</label>
                                                    <div class="ie-time-wrap">
                                                        <input type="time" name="in_end"
                                                            value="<?= e(timeToInput($rule['in_end'])) ?>">
                                                        <i class="fa-regular fa-clock"></i>
                                                    </div>
                                                </div>

                                                <div class="ie-field">
                                                    <label>OUT Start Time</label>
                                                    <div class="ie-time-wrap">
                                                        <input type="time" name="out_start"
                                                            value="<?= e(timeToInput($rule['out_start'])) ?>">
                                                        <i class="fa-regular fa-clock"></i>
                                                    </div>
                                                </div>

                                                <div class="ie-field">
                                                    <label>OUT End Time</label>
                                                    <div class="ie-time-wrap">
                                                        <input type="time" name="out_end"
                                                            value="<?= e(timeToInput($rule['out_end'])) ?>">
                                                        <i class="fa-regular fa-clock"></i>
                                                    </div>
                                                </div>

                                                <div class="ie-actions">
                                                    <button type="button" class="btn-ie-delete"
                                                        onclick="deleteRule(<?= (int)$rule['id'] ?>, <?= (int)$active_cfg['id'] ?>)">
                                                        Delete
                                                    </button>
                                                    <button type="submit" class="btn-ie-save">Save</button>
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($rules)): ?>
                                <tr id="emptyRow">
                                    <td colspan="6" style="text-align:center;padding:28px;color:#9ca3af;font-size:13px">
                                        No shift rules added yet. Click "+ Add shifts to auto shift configuration".
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php else: ?>
                    <div class="asc-empty">Select a configuration to view details.</div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($flash): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    showToast(<?= json_encode($flash) ?>, <?= json_encode($flash_type) ?>);
});
</script>
<?php endif; ?>

<script>
const shiftListOptions = <?= json_encode($shift_list, JSON_UNESCAPED_UNICODE) ?>;

function selectCfg(id) {
    const url = new URL(window.location.href);
    url.searchParams.set('id', id);
    url.searchParams.set('mode', 'view');
    window.location.href = url.toString();
}

function setMode(mode, id) {
    const url = new URL(window.location.href);
    url.searchParams.set('mode', mode);
    if (id !== undefined) url.searchParams.set('id', id);
    window.location.href = url.toString();
}

let openRow = null;

function toggleRow(id) {
    const row = document.getElementById('row-' + id);
    const editRow = document.getElementById('edit-row-' + id);
    if (!row || !editRow) return;

    const isOpen = editRow.classList.contains('show');

    document.querySelectorAll('.inline-edit-row.show').forEach(r => r.classList.remove('show'));
    document.querySelectorAll('.shift-row.expanded').forEach(r => r.classList.remove('expanded'));

    if (!isOpen) {
        editRow.classList.add('show');
        row.classList.add('expanded');
        openRow = id;
    } else {
        openRow = null;
    }
}

function addNewRuleRow(cfgId) {
    const tbody = document.getElementById('rulesTbody');
    const empty = document.getElementById('emptyRow');
    if (empty) empty.remove();

    const existing = document.getElementById('edit-row-new');
    if (existing) {
        existing.classList.add('show');
        return;
    }

    const options = shiftListOptions.map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join('');

    const html = `
        <tr class="inline-edit-row show" id="edit-row-new">
            <td colspan="6">
                <form method="POST">
                    <input type="hidden" name="action" value="save_shift_rule">
                    <input type="hidden" name="rule_id" value="0">
                    <input type="hidden" name="cfg_id" value="${cfgId}">

                    <div class="inline-edit-grid">
                        <div class="ie-field">
                            <label><span class="req">*</span> Direction</label>
                            <select name="direction" class="asc-select">
                                <option value="IN">IN</option>
                                <option value="OUT">OUT</option>
                            </select>
                        </div>

                        <div class="ie-field">
                            <label><span class="req">*</span> Shift Name</label>
                            <select name="shift_name" class="asc-select">
                                ${options}
                            </select>
                        </div>

                        <div class="ie-field">
                            <label><span class="req">*</span> IN Start Time</label>
                            <div class="ie-time-wrap">
                                <input type="time" name="in_start">
                                <i class="fa-regular fa-clock"></i>
                            </div>
                        </div>

                        <label class="ie-nextday">
                            <input type="checkbox" name="next_day" value="1"> Next Day
                        </label>
                    </div>

                    <div class="inline-edit-grid-row2">
                        <div class="ie-field">
                            <label><span class="req">*</span> IN End Time</label>
                            <div class="ie-time-wrap">
                                <input type="time" name="in_end">
                                <i class="fa-regular fa-clock"></i>
                            </div>
                        </div>

                        <div class="ie-field">
                            <label>OUT Start Time</label>
                            <div class="ie-time-wrap">
                                <input type="time" name="out_start">
                                <i class="fa-regular fa-clock"></i>
                            </div>
                        </div>

                        <div class="ie-field">
                            <label>OUT End Time</label>
                            <div class="ie-time-wrap">
                                <input type="time" name="out_end">
                                <i class="fa-regular fa-clock"></i>
                            </div>
                        </div>

                        <div class="ie-actions">
                            <button type="button" class="btn-ie-delete" onclick="this.closest('tr').remove()">Cancel</button>
                            <button type="submit" class="btn-ie-save">Save</button>
                        </div>
                    </div>
                </form>
            </td>
        </tr>`;

    tbody.insertAdjacentHTML('afterbegin', html);
}

function deleteRule(ruleId, cfgId) {
    event.stopPropagation();
    if (!confirm('Delete this shift rule?')) return;

    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `
        <input type="hidden" name="action" value="delete_shift_rule">
        <input type="hidden" name="rule_id" value="${ruleId}">
        <input type="hidden" name="cfg_id" value="${cfgId}">
    `;
    document.body.appendChild(f);
    f.submit();
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, function(m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m];
    });
}

const toastIcons = {
    success: 'fa-circle-check',
    error: 'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info: 'fa-circle-info'
};

function showToast(msg, type = 'success', dur = 3500) {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = `<i class="fa-solid ${toastIcons[type]||toastIcons.info}"></i>
        <span>${msg}</span>
        <button class="toast-close" onclick="rmToast(this.parentElement)">
            <i class="fa-solid fa-xmark"></i>
        </button>`;
    c.appendChild(t);
    setTimeout(() => rmToast(t), dur);
}

function rmToast(el) {
    if (!el?.parentElement) return;
    el.style.animation = 'toastOut .25s ease forwards';
    setTimeout(() => el.remove(), 260);
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>