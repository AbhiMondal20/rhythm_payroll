<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Holidays Configuration';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

function e($v) {

    if ($v === null) {
        return '';
    }

    // JSON string check
    if (is_string($v) && str_starts_with(trim($v), '{')) {

        $decoded = json_decode($v, true);

        if (json_last_error() === JSON_ERROR_NONE) {

            if (isset($decoded['cal_name'])) {
                $v = $decoded['cal_name'];
            } else {
                $v = implode(', ', $decoded);
            }
        }
    }

    // Array handle
    if (is_array($v)) {
        $v = implode(', ', $v);
    }

    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function inputDate($date) {
    if (!$date) return '';
    return date('Y-m-d', strtotime($date));
}

function displayDate($date) {
    if (!$date) return '';
    return date('d/m/Y', strtotime($date));
}

/* CREATE TABLE */
$conn->query("
CREATE TABLE IF NOT EXISTS att_holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_name VARCHAR(30) NOT NULL,
    holiday_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    type ENUM('Holiday','Week-Off') DEFAULT 'Holiday',
    is_halfday TINYINT(1) DEFAULT 0,
    is_optional TINYINT(1) DEFAULT 0,
    calendars VARCHAR(200),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* DUMMY DATA */
$count = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM att_holidays");
if ($res) {
    $count = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($count === 0) {
    $dummy = [
        ['01012026','New Year','2026-01-01','2026-01-01'],
        ['23012026','Netaji Birthday','2026-01-23','2026-01-23'],
        ['26012026','Republic Day','2026-01-26','2026-01-26'],
        ['04032026','Holi','2026-03-04','2026-03-04'],
        ['15042026','Bengali New Year','2026-04-15','2026-04-15'],
        ['01052026','May Day','2026-05-01','2026-05-01'],
        ['15082026','Independence Day','2026-08-15','2026-08-15'],
        ['02102026','Gandhi Jayanti','2026-10-02','2026-10-02'],
        ['01112026','Diwali','2026-11-01','2026-11-01'],
        ['25122026','Christmas','2026-12-25','2026-12-25'],
    ];

    $stmt = $conn->prepare("
        INSERT INTO att_holidays
        (code_name, holiday_name, start_date, end_date, type, is_halfday, is_optional, calendars, remarks)
        VALUES (?, ?, ?, ?, 'Holiday', 0, 0, 'India', '')
    ");

    if ($stmt) {
        foreach ($dummy as $d) {
            $stmt->bind_param("ssss", $d[0], $d[1], $d[2], $d[3]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

/* STATE */
$active_id  = (int)($_GET['id'] ?? 0);
$mode       = $_GET['mode'] ?? 'view';
$year       = (int)($_GET['year'] ?? date('Y'));
$flash      = '';
$flash_type = 'success';

$years = [];
for ($y = date('Y') + 2; $y >= date('Y') - 3; $y--) {
    $years[] = (int)$y;
}


$res = $conn->query("SELECT code_name, cal_name, remarks FROM org_calendars");

$calendars = [];

while ($row = $res->fetch_assoc()) {
    $calendars[] = [
        'cal_name'  => $row['cal_name']
    ];
}

/* POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_holiday') {
        $code      = trim($_POST['code_name'] ?? '');
        $name      = trim($_POST['holiday_name'] ?? '');
        $sd        = trim($_POST['start_date'] ?? '');
        $ed        = trim($_POST['end_date'] ?? '');
        $type      = trim($_POST['type'] ?? 'Holiday');
        $halfday   = isset($_POST['is_halfday']) ? 1 : 0;
        $optional  = isset($_POST['is_optional']) ? 1 : 0;
        $calendars_post = implode(',', $_POST['calendars'] ?? []);
        $remarks   = trim($_POST['remarks'] ?? '');

        if ($code === '' || $name === '' || $sd === '') {
            $flash = 'Code Name, Holiday Name and Start Date are required.';
            $flash_type = 'error';
            $mode = 'add';
        } else {
            if ($ed === '') {
                $ed = $sd;
            }

            $stmt = $conn->prepare("
                INSERT INTO att_holidays
                (code_name, holiday_name, start_date, end_date, type, is_halfday, is_optional, calendars, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $flash = 'Save failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'add';
            } else {
                $stmt->bind_param(
                    "sssssiiss",
                    $code,
                    $name,
                    $sd,
                    $ed,
                    $type,
                    $halfday,
                    $optional,
                    $calendars_post,
                    $remarks
                );

                if ($stmt->execute()) {
                    $active_id = $stmt->insert_id;
                    $flash = 'Holiday "' . $name . '" added.';
                    $flash_type = 'success';
                    $mode = 'view';
                } else {
                    $flash = 'Save failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'add';
                }

                $stmt->close();
            }
        }
    }

    if ($action === 'save_holiday') {
        $id        = (int)($_POST['holiday_id'] ?? 0);
        $code      = trim($_POST['code_name'] ?? '');
        $name      = trim($_POST['holiday_name'] ?? '');
        $sd        = trim($_POST['start_date'] ?? '');
        $ed        = trim($_POST['end_date'] ?? '');
        $type      = trim($_POST['type'] ?? 'Holiday');
        $halfday   = isset($_POST['is_halfday']) ? 1 : 0;
        $optional  = isset($_POST['is_optional']) ? 1 : 0;
        $calendars_post = implode(',', $_POST['calendars'] ?? []);
        $remarks   = trim($_POST['remarks'] ?? '');

        if ($id <= 0 || $code === '' || $name === '' || $sd === '') {
            $flash = 'Code Name, Holiday Name and Start Date are required.';
            $flash_type = 'error';
            $mode = 'edit';
            $active_id = $id;
        } else {
            if ($ed === '') {
                $ed = $sd;
            }

            $stmt = $conn->prepare("
                UPDATE att_holidays
                SET code_name = ?,
                    holiday_name = ?,
                    start_date = ?,
                    end_date = ?,
                    type = ?,
                    is_halfday = ?,
                    is_optional = ?,
                    calendars = ?,
                    remarks = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            if (!$stmt) {
                $flash = 'Update failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'edit';
                $active_id = $id;
            } else {
                $stmt->bind_param(
                    "sssssiissi",
                    $code,
                    $name,
                    $sd,
                    $ed,
                    $type,
                    $halfday,
                    $optional,
                    $calendars_post,
                    $remarks,
                    $id
                );

                if ($stmt->execute()) {
                    $flash = 'Holiday updated.';
                    $flash_type = 'success';
                    $mode = 'view';
                    $active_id = $id;
                    $year = (int)date('Y', strtotime($sd));
                } else {
                    $flash = 'Update failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'edit';
                    $active_id = $id;
                }

                $stmt->close();
            }
        }
    }

    if ($action === 'delete_holiday') {
        $id = (int)($_POST['holiday_id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM att_holidays WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $flash = 'Holiday deleted.';
                    $flash_type = 'success';
                    $active_id = 0;
                    $mode = 'view';
                } else {
                    $flash = 'Delete failed: ' . $stmt->error;
                    $flash_type = 'error';
                }
                $stmt->close();
            } else {
                $flash = 'Delete failed: ' . $conn->error;
                $flash_type = 'error';
            }
        }
    }
}

/* FETCH HOLIDAYS */
$holidays = [];

$stmt = $conn->prepare("
    SELECT *
    FROM att_holidays
    WHERE YEAR(start_date) = ?
    ORDER BY start_date ASC, id ASC
");

if ($stmt) {
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['is_halfday'] = (int)$row['is_halfday'];
        $row['is_optional'] = (int)$row['is_optional'];
        $holidays[] = $row;
    }

    $stmt->close();
}

/* DEFAULT ACTIVE */
if ($active_id === 0 && $mode === 'view' && count($holidays)) {
    $active_id = (int)$holidays[0]['id'];
}

/* ACTIVE HOLIDAY */
$active_hol = null;

if ($active_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM att_holidays WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $active_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $active_hol = $res->fetch_assoc();
        if ($active_hol) {
            $active_hol['id'] = (int)$active_hol['id'];
            $active_hol['is_halfday'] = (int)$active_hol['is_halfday'];
            $active_hol['is_optional'] = (int)$active_hol['is_optional'];
        }
        $stmt->close();
    }
}

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
*{box-sizing:border-box}
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}

.hc-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.hc-inner{padding:18px 24px}
.hc-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:14px}
.hc-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555;flex-wrap:wrap}
.hc-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.hc-breadcrumb a:hover{text-decoration:underline}
.hc-breadcrumb .sep{color:#bbb;font-size:11px}

.btn-add-hol{display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .16s;white-space:nowrap}
.btn-add-hol:hover{background:#1d4ed8}

.hc-sub-header{display:grid;grid-template-columns:36% 64%;border-bottom:1px solid #e8ecf0;margin-bottom:0}
.hc-sub-left,.hc-sub-right{padding:10px 16px;font-size:12px;color:#6b7280;font-weight:600;display:flex;align-items:center;gap:14px}
.hc-year-select{padding:5px 22px 5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;color:#374151;background:#fff;outline:none;appearance:auto;cursor:pointer}

.hc-panel{display:flex;background:#fff;border:1px solid #e8ecf0;border-radius:10px;overflow:hidden;min-height:500px}
.hc-list-col{width:36%;min-width:240px;border-right:1px solid #e8ecf0;display:flex;flex-direction:column}
.hc-list-scroll{flex:1;overflow-y:auto;max-height:600px}
.hc-item{padding:12px 16px;border-bottom:1px solid #f1f4f8;cursor:pointer;display:flex;align-items:center;transition:background .12s}
.hc-item:hover{background:#f8fafc}
.hc-item.active{background:#eff6ff;border-left:3px solid #2563eb;padding-left:13px}
.hc-item-name{flex:1;font-size:13.5px;font-weight:500;color:#1e2d3d}
.hc-item.active .hc-item-name{color:#2563eb;font-weight:700}
.hc-item-num{width:24px;font-size:12px;color:#9ca3af;font-weight:600;flex-shrink:0}
.hc-item-date{font-size:12.5px;color:#2563eb;min-width:90px;flex-shrink:0}
.hc-item:not(.active) .hc-item-date{color:#6b7280}
.hc-item-chevron{font-size:11px;color:#9ca3af;flex-shrink:0;margin-left:8px}

.hc-detail-col{flex:1;padding:20px 26px;display:flex;flex-direction:column;overflow-y:auto}
.hc-detail-title-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:14px}
.hc-detail-title{font-size:15px;font-weight:800;color:#1e2d3d;text-transform:uppercase;letter-spacing:.3px}

.hc-toggles{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.hc-toggle-wrap{display:flex;align-items:center;gap:8px;font-size:13px;color:#374151}
.toggle-switch{position:relative;width:36px;height:20px;cursor:pointer;flex-shrink:0}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#d1d5db;border-radius:20px;transition:background .2s}
.toggle-slider:before{content:'';position:absolute;width:14px;height:14px;left:3px;top:3px;background:#fff;border-radius:50%;transition:transform .2s}
.toggle-switch input:checked + .toggle-slider{background:#2563eb}
.toggle-switch input:checked + .toggle-slider:before{transform:translateX(16px)}

.btn-edit-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:600;padding:0;white-space:nowrap}
.btn-edit-link:hover{text-decoration:underline}

.hc-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 36px;margin-bottom:18px}
.hc-field label{display:block;font-size:12px;color:#6b7280;margin-bottom:6px;font-weight:500}
.hc-field label .req,.req{color:#ef4444;margin-right:2px}
.hc-field-value{font-size:13.5px;color:#1e2d3d;padding-bottom:8px;border-bottom:1px solid #e2e8f0;min-height:26px}

.hc-input{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;transition:border-color .16s}
.hc-input:focus{border-color:#2563eb}
.hc-date-wrap{position:relative}
.hc-date-wrap input[type=date]{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 28px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;transition:border-color .16s;cursor:pointer}
.hc-date-wrap input[type=date]:focus{border-color:#2563eb}
.hc-date-wrap i{position:absolute;right:4px;top:50%;transform:translateY(-50%);color:#2563eb;font-size:14px;pointer-events:none}

.hc-type-section,.hc-cal-section,.hc-remarks-section{margin-bottom:18px}
.hc-type-label,.hc-cal-label{font-size:12.5px;color:#374151;margin-bottom:10px;font-weight:400}
.hc-radio-group{display:flex;flex-direction:column;gap:8px}
.hc-radio-item{display:flex;align-items:center;gap:9px;font-size:13.5px;color:#374151;cursor:pointer}
.hc-radio-item input[type=radio]{width:16px;height:16px;accent-color:#2563eb;cursor:pointer}
.hc-cal-chip{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:20px;padding:4px 12px;font-size:12.5px;color:#1d4ed8;font-weight:500}
.hc-cal-chip input[type=checkbox]{width:14px;height:14px;accent-color:#2563eb;cursor:pointer}

.hc-remarks-label{font-size:12px;color:#6b7280;margin-bottom:6px;display:block;font-weight:500}
.hc-remarks-value{font-size:13.5px;color:#1e2d3d;padding-bottom:8px;border-bottom:1px solid #e2e8f0;min-height:26px}

.hc-form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:auto;padding-top:20px;border-top:1px solid #e8ecf0;flex-wrap:wrap}
.btn-cancel,.btn-delete,.btn-save{padding:9px 26px;border-radius:6px;font-size:13.5px;cursor:pointer;font-weight:600;transition:background .14s}
.btn-cancel{border:1.5px solid #d1d5db;background:#fff;color:#374151}
.btn-cancel:hover{background:#f1f5f9}
.btn-delete{border:1.5px solid #ef4444;background:#fff;color:#ef4444}
.btn-delete:hover{background:#fee2e2}
.btn-save{background:#2563eb;border:none;color:#fff}
.btn-save:hover{background:#1d4ed8}

.hc-add-card{background:#fff;border:1px solid #e8ecf0;border-radius:10px;padding:26px 28px 10px}
.hc-add-title{font-size:14px;font-weight:800;color:#1e2d3d;text-transform:uppercase;letter-spacing:.3px;margin-bottom:22px}

.flash-msg{padding:10px 16px;border-radius:7px;font-size:13px;margin-bottom:14px;font-weight:500}
.flash-msg.success{background:#dcfce7;color:#166534}
.flash-msg.error{background:#fee2e2;color:#991b1b}

.toast-container{position:fixed;top:20px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;background:#fff;border-radius:8px;padding:13px 18px;box-shadow:0 4px 18px rgba(0,0,0,.14);font-size:13.5px;font-weight:500;min-width:260px;pointer-events:all;animation:toastIn .25s ease;border-left:4px solid #2563eb;color:#1e2d3d}
.toast.success{border-color:#22c55e}
.toast.error{border-color:#ef4444}
.toast i{font-size:16px}
.toast.success i{color:#22c55e}
.toast.error i{color:#ef4444}
.toast-close{margin-left:auto;cursor:pointer;color:#9ca3af;font-size:14px;background:none;border:none;padding:0;line-height:1}
@keyframes toastIn{from{transform:translateX(40px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes toastOut{to{opacity:0;transform:translateX(40px)}}

@media(max-width:900px){
    .hc-inner{padding:16px}
    .hc-topbar{align-items:flex-start;flex-direction:column}
    .btn-add-hol{width:100%;justify-content:center}
    .hc-sub-header{display:none}
    .hc-panel{flex-direction:column}
    .hc-list-col{width:100%;border-right:none;border-bottom:1px solid #e8ecf0}
    .hc-list-scroll{max-height:260px}
    .hc-detail-col{padding:18px}
    .hc-detail-title-row{align-items:flex-start;flex-direction:column}
}

@media(max-width:640px){
    .cfg-tab{padding:13px 15px;font-size:13px}
    .hc-inner{padding:14px}
    .hc-field-grid{grid-template-columns:1fr;gap:16px}
    .hc-add-card{padding:20px 16px 10px}
    .hc-item{padding:11px 12px}
    .hc-item.active{padding-left:9px}
    .hc-item-date{min-width:78px;font-size:12px}
    .hc-toggles{gap:12px}
    .hc-form-actions{display:grid;grid-template-columns:1fr;width:100%}
    .btn-cancel,.btn-delete,.btn-save{width:100%}
    .toast-container{left:12px;right:12px;top:12px}
    .toast{min-width:0;width:100%}
}
</style>
<!-- ── Page header ── -->
<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>
<div class="toast-container" id="toastContainer"></div>

<div class="section-card" style="padding:0;overflow:hidden">
<div class="hc-wrapper">

    <div class="cfg-tabs">
        <?php foreach ([
            'AccountInfo'=>'Account Info',
            'Organization'=>'Organization',
            'Payroll'=>'Payroll',
            'Attendance'=>'Attendance',
            'Leave'=>'Leave',
            'Training'=>'Training',
            'Others'=>'Others'
        ] as $k=>$l): ?>
            <a href="configuration#<?= e($k) ?>" class="cfg-tab <?= $k === 'Attendance' ? 'active' : '' ?>">
                <?= e($l) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="hc-inner">

        <?php if ($flash): ?>
            <div class="flash-msg <?= e($flash_type) ?>"><?= e($flash) ?></div>
        <?php endif; ?>

        <div class="hc-topbar">
            <nav class="hc-breadcrumb">
                <a href="attendance_config.php">Attendance</a>
                <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
                <span>Holidays Configuration</span>
            </nav>

            <?php if ($mode !== 'add'): ?>
                <button class="btn-add-hol" onclick="setMode('add')">
                    <i class="fa-solid fa-plus"></i> Add Holiday
                </button>
            <?php endif; ?>
        </div>

        <?php if ($mode === 'add'): ?>

            <div class="hc-add-card">
                <div class="hc-add-title">Add Holiday</div>

                <form method="POST">
                    <input type="hidden" name="action" value="add_holiday">

                    <div class="hc-field-grid">
                        <div class="hc-field">
                            <label><span class="req">*</span> Code Name</label>
                            <input type="text" name="code_name" class="hc-input" value="<?= e($_POST['code_name'] ?? '') ?>" required>
                        </div>

                        <div class="hc-field">
                            <label><span class="req">*</span> Holiday Name</label>
                            <input type="text" name="holiday_name" class="hc-input" value="<?= e($_POST['holiday_name'] ?? '') ?>" required>
                        </div>

                        <div class="hc-field">
                            <label><span class="req">*</span> Start Date</label>
                            <div class="hc-date-wrap">
                                <input type="date" name="start_date" value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>" required>
                                <i class="fa-regular fa-calendar"></i>
                            </div>
                        </div>

                        <div class="hc-field">
                            <label>End Date</label>
                            <div class="hc-date-wrap">
                                <input type="date" name="end_date" value="<?= e($_POST['end_date'] ?? date('Y-m-d')) ?>">
                                <i class="fa-regular fa-calendar"></i>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:20px;margin-bottom:18px;flex-wrap:wrap">
                        <label class="hc-toggle-wrap">
                            <span class="toggle-switch">
                                <input type="checkbox" name="is_halfday" value="1" <?= isset($_POST['is_halfday']) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </span>
                            Half-day
                        </label>

                        <label class="hc-toggle-wrap">
                            <span class="toggle-switch">
                                <input type="checkbox" name="is_optional" value="1" <?= isset($_POST['is_optional']) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </span>
                            Optional
                        </label>
                    </div>

                    <div class="hc-type-section">
                        <div class="hc-type-label">Select Type</div>
                        <div class="hc-radio-group">
                            <label class="hc-radio-item">
                                <input type="radio" name="type" value="Holiday" <?= (($_POST['type'] ?? 'Holiday') === 'Holiday') ? 'checked' : '' ?>>
                                Holiday
                            </label>
                            <label class="hc-radio-item">
                                <input type="radio" name="type" value="Week-Off" <?= (($_POST['type'] ?? '') === 'Week-Off') ? 'checked' : '' ?>>
                                Week-Off
                            </label>
                        </div>
                    </div>

                    <div class="hc-cal-section">
                        <div class="hc-cal-label"><span class="req">*</span> Applicable to Calendar</div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap">
                            <?php foreach ($calendars as $cal): ?>
                                <label class="hc-cal-chip">
                                    <input type="checkbox" name="calendars[]" value="<?= e($cal) ?>"
                                        <?= ($cal === 'India' || (isset($_POST['calendars']) && in_array($cal, $_POST['calendars']))) ? 'checked' : '' ?>>
                                    <?= e($cal) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="hc-field-grid" style="grid-template-columns:1fr">
                        <div class="hc-field">
                            <label>Remarks</label>
                            <input type="text" name="remarks" class="hc-input" value="<?= e($_POST['remarks'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="hc-form-actions">
                        <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
                        <button type="submit" class="btn-save">Add</button>
                    </div>
                </form>
            </div>

        <?php else: ?>

            <div class="hc-sub-header">
                <div class="hc-sub-left">
                    List of Holidays (<?= count($holidays) ?>)
                    <form method="GET" style="display:inline">
                        <input type="hidden" name="mode" value="view">
                        <select name="year" class="hc-year-select" onchange="this.form.submit()">
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y ?>" <?= ($y === $year) ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="hc-sub-right">Holiday Details</div>
            </div>

            <div class="hc-panel">

                <div class="hc-list-col">
                    <div class="hc-list-scroll">
                        <?php foreach ($holidays as $i => $hol): ?>
                            <div class="hc-item <?= ((int)$hol['id'] === (int)$active_id && $mode !== 'edit') ? 'active' : '' ?>"
                                 onclick="selectHol(<?= (int)$hol['id'] ?>)">
                                <span class="hc-item-name"><?= e($hol['holiday_name']) ?></span>
                                <span class="hc-item-num"><?= $i + 1 ?></span>
                                <span class="hc-item-date"><?= e(displayDate($hol['start_date'])) ?></span>
                                <i class="fa-solid <?= ((int)$hol['id'] === (int)$active_id && $mode !== 'edit') ? 'fa-chevron-right' : 'fa-chevron-down' ?> hc-item-chevron"></i>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($holidays)): ?>
                            <div style="padding:24px 16px;color:#9ca3af;font-size:13px">
                                No holidays for <?= e($year) ?>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="hc-detail-col">

                    <?php if ($mode === 'edit' && $active_hol): ?>

                        <div class="hc-detail-title" style="margin-bottom:18px">
                            EDIT — <?= e($active_hol['holiday_name']) ?>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="save_holiday">
                            <input type="hidden" name="holiday_id" value="<?= (int)$active_hol['id'] ?>">

                            <div style="display:flex;gap:20px;margin-bottom:16px;flex-wrap:wrap">
                                <label class="hc-toggle-wrap">
                                    <span class="toggle-switch">
                                        <input type="checkbox" name="is_halfday" value="1" <?= $active_hol['is_halfday'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </span>
                                    Half-day
                                </label>

                                <label class="hc-toggle-wrap">
                                    <span class="toggle-switch">
                                        <input type="checkbox" name="is_optional" value="1" <?= $active_hol['is_optional'] ? 'checked' : '' ?>>
                                        <span class="toggle-slider"></span>
                                    </span>
                                    Optional
                                </label>
                            </div>

                            <div class="hc-field-grid">
                                <div class="hc-field">
                                    <label><span class="req">*</span> Code Name</label>
                                    <input type="text" name="code_name" class="hc-input" value="<?= e($active_hol['code_name']) ?>" required>
                                </div>

                                <div class="hc-field">
                                    <label><span class="req">*</span> Holiday Name</label>
                                    <input type="text" name="holiday_name" class="hc-input" value="<?= e($active_hol['holiday_name']) ?>" required>
                                </div>

                                <div class="hc-field">
                                    <label><span class="req">*</span> Start Date</label>
                                    <div class="hc-date-wrap">
                                        <input type="date" name="start_date" value="<?= e(inputDate($active_hol['start_date'])) ?>" required>
                                        <i class="fa-regular fa-calendar"></i>
                                    </div>
                                </div>

                                <div class="hc-field">
                                    <label>End Date</label>
                                    <div class="hc-date-wrap">
                                        <input type="date" name="end_date" value="<?= e(inputDate($active_hol['end_date'])) ?>">
                                        <i class="fa-regular fa-calendar"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="hc-type-section">
                                <div class="hc-type-label">Select Type</div>
                                <div class="hc-radio-group">
                                    <label class="hc-radio-item">
                                        <input type="radio" name="type" value="Holiday" <?= ($active_hol['type'] === 'Holiday') ? 'checked' : '' ?>>
                                        Holiday
                                    </label>

                                    <label class="hc-radio-item">
                                        <input type="radio" name="type" value="Week-Off" <?= ($active_hol['type'] === 'Week-Off') ? 'checked' : '' ?>>
                                        Week-Off
                                    </label>
                                </div>
                            </div>

                            <div class="hc-cal-section">
                                <div class="hc-cal-label"><span class="req">*</span> Applicable to Calendar</div>
                                <div style="display:flex;gap:10px;flex-wrap:wrap">
                                    <?php
                                    $selCals = array_map('trim', explode(',', $active_hol['calendars'] ?? ''));
                                    foreach ($calendars as $cal):
                                    ?>
                                        <label class="hc-cal-chip">
                                            <input type="checkbox" name="calendars[]" value="<?= e($cal) ?>" <?= in_array($cal, $selCals) ? 'checked' : '' ?>>
                                            <?= e($cal) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="hc-field-grid" style="grid-template-columns:1fr">
                                <div class="hc-field">
                                    <label>Remarks</label>
                                    <input type="text" name="remarks" class="hc-input" value="<?= e($active_hol['remarks']) ?>">
                                </div>
                            </div>

                            <div class="hc-form-actions">
                                <button type="button" class="btn-delete" onclick="deleteHol(<?= (int)$active_hol['id'] ?>)">Delete</button>
                                <button type="button" class="btn-cancel" onclick="window.location.href='?id=<?= (int)$active_hol['id'] ?>&year=<?= (int)$year ?>&mode=view'">Cancel</button>
                                <button type="submit" class="btn-save">Save</button>
                            </div>
                        </form>

                    <?php elseif ($active_hol): ?>

                        <div class="hc-detail-title-row">
                            <div class="hc-detail-title"><?= e($active_hol['holiday_name']) ?></div>

                            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                                <div class="hc-toggles">
                                    <span class="hc-toggle-wrap">
                                        <span class="toggle-switch" style="pointer-events:none">
                                            <input type="checkbox" <?= $active_hol['is_halfday'] ? 'checked' : '' ?> disabled>
                                            <span class="toggle-slider"></span>
                                        </span>
                                        Half-day
                                    </span>

                                    <span class="hc-toggle-wrap">
                                        <span class="toggle-switch" style="pointer-events:none">
                                            <input type="checkbox" <?= $active_hol['is_optional'] ? 'checked' : '' ?> disabled>
                                            <span class="toggle-slider"></span>
                                        </span>
                                        Optional
                                    </span>
                                </div>

                                <button class="btn-edit-link" onclick="window.location.href='?id=<?= (int)$active_hol['id'] ?>&year=<?= (int)$year ?>&mode=edit'">
                                    <i class="fa-regular fa-pen-to-square"></i> Edit Details
                                </button>
                            </div>
                        </div>

                        <div class="hc-field-grid">
                            <div class="hc-field">
                                <label>Code Name</label>
                                <div class="hc-field-value"><?= e($active_hol['code_name']) ?></div>
                            </div>

                            <div class="hc-field">
                                <label>Holiday Name</label>
                                <div class="hc-field-value"><?= e($active_hol['holiday_name']) ?></div>
                            </div>

                            <div class="hc-field">
                                <label>Start Date</label>
                                <div class="hc-field-value">
                                    <?= e(displayDate($active_hol['start_date'])) ?>
                                    <i class="fa-regular fa-calendar" style="color:#9ca3af;font-size:13px"></i>
                                </div>
                            </div>

                            <div class="hc-field">
                                <label>End Date</label>
                                <div class="hc-field-value">
                                    <?= e(displayDate($active_hol['end_date'])) ?>
                                    <i class="fa-regular fa-calendar" style="color:#9ca3af;font-size:13px"></i>
                                </div>
                            </div>
                        </div>

                        <div class="hc-type-section">
                            <div class="hc-type-label">Select Type</div>
                            <div class="hc-radio-group" style="pointer-events:none">
                                <label class="hc-radio-item">
                                    <input type="radio" <?= ($active_hol['type'] === 'Holiday') ? 'checked' : '' ?> disabled>
                                    Holiday
                                </label>

                                <label class="hc-radio-item">
                                    <input type="radio" <?= ($active_hol['type'] === 'Week-Off') ? 'checked' : '' ?> disabled>
                                    Week-Off
                                </label>
                            </div>
                        </div>

                        <div class="hc-cal-section">
                            <div class="hc-cal-label"><span class="req">*</span> Applicable to Calendar</div>
                            <div style="display:flex;gap:10px;flex-wrap:wrap">
                                <?php
                                $selCals = array_filter(array_map('trim', explode(',', $active_hol['calendars'] ?? '')));
                                foreach ($selCals as $cal):
                                ?>
                                    <span class="hc-cal-chip">
                                        <input type="checkbox" checked disabled>
                                        <?= e($cal) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="hc-remarks-section">
                            <label class="hc-remarks-label">Remarks</label>
                            <div class="hc-remarks-value"><?= e($active_hol['remarks']) ?>&nbsp;</div>
                        </div>

                        <div class="hc-form-actions">
                            <button class="btn-delete" onclick="deleteHol(<?= (int)$active_hol['id'] ?>)">Delete</button>
                            <button class="btn-cancel" onclick="setMode('view')">Cancel</button>
                            <button class="btn-save" onclick="window.location.href='?id=<?= (int)$active_hol['id'] ?>&year=<?= (int)$year ?>&mode=edit'">Save</button>
                        </div>

                    <?php else: ?>

                        <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13.5px">
                            Select a holiday to view details.
                        </div>

                    <?php endif; ?>

                </div>
            </div>

        <?php endif; ?>

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
function selectHol(id) {
    const url = new URL(window.location.href);
    url.searchParams.set('id', id);
    url.searchParams.set('mode', 'view');
    window.location.href = url.toString();
}

function setMode(mode, id) {
    const url = new URL(window.location.href);
    url.searchParams.set('mode', mode);

    if (id !== undefined) {
        url.searchParams.set('id', id);
    }

    window.location.href = url.toString();
}

function deleteHol(id) {
    if (!confirm('Delete this holiday?')) {
        return;
    }

    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `
        <input type="hidden" name="action" value="delete_holiday">
        <input type="hidden" name="holiday_id" value="${id}">
    `;
    document.body.appendChild(f);
    f.submit();
}

const toastIcons = {
    success: 'fa-circle-check',
    error: 'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info: 'fa-circle-info'
};

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}

function showToast(msg, type = 'success', dur = 3500) {
    const c = document.getElementById('toastContainer');

    if (!c) {
        alert(msg);
        return;
    }

    const t = document.createElement('div');
    t.className = 'toast ' + type;

    t.innerHTML = `
        <i class="fa-solid ${toastIcons[type] || toastIcons.info}"></i>
        <span>${escapeHtml(msg)}</span>
        <button type="button" class="toast-close" onclick="rmToast(this.parentElement)">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;

    c.appendChild(t);
    setTimeout(() => rmToast(t), dur);
}

function rmToast(el) {
    if (!el || !el.parentElement) return;
    el.style.animation = 'toastOut .25s ease forwards';
    setTimeout(() => {
        if (el.parentElement) el.remove();
    }, 260);
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>