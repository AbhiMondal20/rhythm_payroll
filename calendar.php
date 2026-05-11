<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Calendar Configuration';
ob_start();

function esc($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function formatHolidayDate($date) {
    if (!$date) return '';
    return date('d-M-Y, D', strtotime($date));
}

function calcDays($start, $end, $halfday = 0) {
    if ($halfday) return 0.5;
    $s = strtotime($start);
    $e = strtotime($end);
    if (!$s || !$e || $e < $s) return 1;
    return floor(($e - $s) / 86400) + 1;
}

if (!isset($conn) || !$conn) {
    die('Database connection not found.');
}

$active_id = (int)($_GET['id'] ?? 0);
$mode      = $_GET['mode'] ?? 'view';
$search    = trim($_GET['q'] ?? '');

$toast_msg  = $_SESSION['toast_msg'] ?? '';
$toast_type = $_SESSION['toast_type'] ?? '';
unset($_SESSION['toast_msg'], $_SESSION['toast_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_cal') {
        $code    = trim($_POST['code_name'] ?? '');
        $name    = trim($_POST['cal_name'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($code === '' || $name === '') {
            $_SESSION['toast_msg'] = 'Code Name and Calendar Name are required.';
            $_SESSION['toast_type'] = 'error';
            header("Location: ?mode=add");
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO org_calendars (code_name, cal_name, remarks) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $code, $name, $remarks);

        if ($stmt->execute()) {
            $_SESSION['toast_msg'] = 'Calendar added successfully.';
            $_SESSION['toast_type'] = 'success';
            header("Location: ?id=" . $stmt->insert_id . "&mode=view");
            exit;
        } else {
            $_SESSION['toast_msg'] = 'Save failed: ' . $stmt->error;
            $_SESSION['toast_type'] = 'error';
            header("Location: ?mode=add");
            exit;
        }
    }

    if ($action === 'edit_cal') {
        $id      = (int)($_POST['edit_id'] ?? 0);
        $code    = trim($_POST['code_name'] ?? '');
        $name    = trim($_POST['cal_name'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($id <= 0 || $code === '' || $name === '') {
            $_SESSION['toast_msg'] = 'Code Name and Calendar Name are required.';
            $_SESSION['toast_type'] = 'error';
            header("Location: ?id=" . $id . "&mode=edit");
            exit;
        }

        $stmt = $conn->prepare("UPDATE org_calendars SET code_name=?, cal_name=?, remarks=? WHERE id=?");
        $stmt->bind_param("sssi", $code, $name, $remarks, $id);

        if ($stmt->execute()) {
            $_SESSION['toast_msg'] = 'Calendar updated successfully.';
            $_SESSION['toast_type'] = 'success';
            header("Location: ?id=" . $id . "&mode=view");
            exit;
        } else {
            $_SESSION['toast_msg'] = 'Update failed: ' . $stmt->error;
            $_SESSION['toast_type'] = 'error';
            header("Location: ?id=" . $id . "&mode=edit");
            exit;
        }
    }

    if ($action === 'add_holiday') {
        $cal_id       = (int)($_POST['cal_id'] ?? 0);
        $code         = trim($_POST['code_name'] ?? '');
        $holiday_name = trim($_POST['holiday_name'] ?? '');
        $start_date   = trim($_POST['start_date'] ?? '');
        $end_date     = trim($_POST['end_date'] ?? '');
        $is_optional  = isset($_POST['is_optional']) ? 1 : 0;
        $holiday_type = trim($_POST['holiday_type'] ?? 'Holiday');
        $is_halfday   = isset($_POST['is_halfday']) ? 1 : 0;
        $days         = calcDays($start_date, $end_date, $is_halfday);

        if ($cal_id <= 0 || $code === '' || $holiday_name === '' || $start_date === '' || $end_date === '') {
            $_SESSION['toast_msg'] = 'Holiday required fields missing.';
            $_SESSION['toast_type'] = 'error';
            header("Location: ?id=" . $cal_id . "&mode=view");
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO org_holidays 
            (cal_id, code_name, holiday_name, start_date, end_date, days, is_optional, holiday_type, is_halfday)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssdisi", $cal_id, $code, $holiday_name, $start_date, $end_date, $days, $is_optional, $holiday_type, $is_halfday);

        if ($stmt->execute()) {
            $_SESSION['toast_msg'] = 'Holiday added successfully.';
            $_SESSION['toast_type'] = 'success';
        } else {
            $_SESSION['toast_msg'] = 'Holiday save failed: ' . $stmt->error;
            $_SESSION['toast_type'] = 'error';
        }

        header("Location: ?id=" . $cal_id . "&mode=view");
        exit;
    }
}

$cals = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT * FROM org_calendars WHERE cal_name LIKE ? OR code_name LIKE ? ORDER BY cal_name ASC");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query("SELECT * FROM org_calendars ORDER BY cal_name ASC");
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cals[] = $row;
    }
}

if ($active_id === 0 && $mode === 'view' && count($cals)) {
    $active_id = (int)$cals[0]['id'];
}

$active_cal = null;
if ($active_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM org_calendars WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $active_id);
    $stmt->execute();
    $active_cal = $stmt->get_result()->fetch_assoc();
}

$holidays = [];
if ($active_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM org_holidays WHERE cal_id=? ORDER BY start_date ASC");
    $stmt->bind_param("i", $active_id);
    $stmt->execute();
    $hres = $stmt->get_result();

    while ($row = $hres->fetch_assoc()) {
        $holidays[] = $row;
    }
}
?>

<link rel="stylesheet" href="includes/assets/style.css">

<style>

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


/* ── Config nav tabs ── */
.cfg-tabs {
  display: flex;
  align-items: center;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
  overflow-x: auto;
  scrollbar-width: none;
}
.cfg-tabs::-webkit-scrollbar { display: none; }
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
  margin-bottom: -1px;
}
.cfg-tab:hover { color: #111827; }
.cfg-tab.active { color: #2563eb; border-bottom-color: #2563eb; font-weight: 600; }

/* ── Page wrapper ── */
.cal-wrapper {
  font-family: 'Segoe UI', sans-serif;
  color: #1e2d3d;
  padding: 0 0 40px;
}
.cal-inner { padding: 20px 28px; }

/* ── Top bar ── */
.cal-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
}
.cal-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  color: #555;
}
.cal-breadcrumb a { color: #1e2d3d; text-decoration: none; font-weight: 600; }
.cal-breadcrumb a:hover { text-decoration: underline; }
.cal-breadcrumb .sep { color: #bbb; font-size: 11px; }

.btn-add-cal {
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
  transition: background .16s;
}
.btn-add-cal:hover { background: #1d4ed8; }

/* ── Split panel ── */
.cal-panel {
  display: flex;
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 10px;
  overflow: hidden;
  min-height: 520px;
}

/* Left */
.cal-list-col {
  width: 30%;
  min-width: 200px;
  border-right: 1px solid #e8ecf0;
  display: flex;
  flex-direction: column;
}
.cal-list-heading {
  padding: 14px 16px 10px;
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.cal-search-wrap { padding: 0 14px 12px; }
.cal-search-inner { position: relative; }
.cal-search-inner i {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 12px;
}
.cal-search-input {
  width: 100%;
  padding: 8px 10px 8px 32px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 13px;
  color: #1e2d3d;
  outline: none;
  box-sizing: border-box;
  background: #f9fafb;
  transition: border-color .15s;
}
.cal-search-input:focus { border-color: #2563eb; background: #fff; }

.cal-list-scroll {
  flex: 1;
  overflow-y: auto;
}
.cal-list-scroll::-webkit-scrollbar { width: 4px; }
.cal-list-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

.cal-item {
  padding: 13px 16px;
  border-bottom: 1px solid #f1f4f8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: background .12s;
}
.cal-item:last-child { border-bottom: none; }
.cal-item:hover { background: #f8fafc; }
.cal-item.active {
  background: #eff6ff;
  border-left: 3px solid #2563eb;
  padding-left: 13px;
}
.cal-item-name { font-size: 13.5px; font-weight: 500; color: #1e2d3d; }
.cal-item.active .cal-item-name { color: #2563eb; font-weight: 700; }
.cal-item-chevron { font-size: 11px; color: #9ca3af; }

/* Right */
.cal-detail-col {
  flex: 1;
  padding: 22px 28px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.cal-detail-heading {
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
  border-bottom: 1px solid #e8ecf0;
  padding-bottom: 12px;
  margin-bottom: 18px;
}
.cal-detail-title-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
}
.cal-detail-title {
  font-size: 15px;
  font-weight: 800;
  color: #1e2d3d;
  text-transform: uppercase;
  letter-spacing: .3px;
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
  padding: 0;
}
.btn-edit-link:hover { text-decoration: underline; }

/* Field row */
.cal-field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 36px;
  margin-bottom: 6px;
}
.cal-field-grid.single { grid-template-columns: 1fr; }
.cal-field label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
  font-weight: 500;
}
.cal-field label .req { color: #ef4444; margin-right: 2px; }
.cal-field-value {
  font-size: 13.5px;
  color: #1e2d3d;
  padding-bottom: 8px;
  border-bottom: 1px solid #e2e8f0;
  min-height: 26px;
}
.cal-input {
  width: 100%;
  border: none;
  border-bottom: 1.5px solid #d1d5db;
  padding: 7px 2px;
  font-size: 13.5px;
  color: #1e2d3d;
  background: transparent;
  outline: none;
  box-sizing: border-box;
  transition: border-color .16s;
}
.cal-input::placeholder { color: #c4c9d4; }
.cal-input:focus { border-color: #2563eb; }

/* ── Holidays section ── */
.cal-holidays-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 0 12px;
  border-top: 1px solid #e8ecf0;
  margin-top: 16px;
}
.cal-holidays-count {
  font-size: 13.5px;
  font-weight: 600;
  color: #1e2d3d;
}
.btn-add-new {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 13px;
  color: #2563eb;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 600;
  padding: 0;
}
.btn-add-new:hover { text-decoration: underline; }

/* Holidays table */
.cal-holidays-table-wrap {
  overflow-y: auto;
  max-height: 320px;
  border: 1px solid #e8ecf0;
  border-radius: 8px;
}
.cal-holidays-table-wrap::-webkit-scrollbar { width: 4px; }
.cal-holidays-table-wrap::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

table.cal-holidays-table {
  width: 100%;
  border-collapse: collapse;
}
table.cal-holidays-table thead th {
  background: #f8fafc;
  padding: 10px 14px;
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  border-bottom: 1px solid #e8ecf0;
  position: sticky;
  top: 0;
  z-index: 1;
}
table.cal-holidays-table tbody tr {
  border-bottom: 1px solid #f1f4f8;
  transition: background .12s;
}
table.cal-holidays-table tbody tr:last-child { border-bottom: none; }
table.cal-holidays-table tbody tr:hover { background: #f9fafb; }
table.cal-holidays-table tbody td {
  padding: 11px 14px;
  font-size: 13px;
  color: #374151;
}

/* ── Form actions ── */
.cal-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: auto;
  padding-top: 24px;
}
.btn-cancel-sm {
  padding: 9px 26px;
  border: 1.5px solid #d1d5db;
  background: #fff;
  border-radius: 6px;
  font-size: 13.5px;
  color: #374151;
  cursor: pointer;
  font-weight: 600;
  transition: background .14s;
}
.btn-cancel-sm:hover { background: #f1f5f9; }
.btn-save-sm {
  padding: 9px 26px;
  background: #2563eb;
  border: none;
  border-radius: 6px;
  font-size: 13.5px;
  color: #fff;
  cursor: pointer;
  font-weight: 600;
  transition: background .14s;
}
.btn-save-sm:hover { background: #1d4ed8; }

/* ── Modal ── */
.modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.4);
  z-index: 999;
  align-items: center;
  justify-content: center;
}
.modal-overlay.show { display: flex; }
.modal-box {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  padding: 28px 32px 24px;
  box-shadow: 0 12px 40px rgba(0,0,0,.18);
  animation: modalIn .2s ease;
}
@keyframes modalIn {
  from { transform: translateY(-14px); opacity: 0; }
  to   { transform: translateY(0);     opacity: 1; }
}
.modal-title {
  font-size: 15px;
  font-weight: 700;
  color: #1e2d3d;
  margin-bottom: 20px;
}

/* Toggle switch */
.toggle-row {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
}
.toggle-label { font-size: 13px; color: #374151; }
.toggle-switch {
  position: relative;
  width: 38px;
  height: 22px;
  cursor: pointer;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
  position: absolute;
  inset: 0;
  background: #d1d5db;
  border-radius: 22px;
  transition: background .2s;
}
.toggle-slider:before {
  content: '';
  position: absolute;
  width: 16px;
  height: 16px;
  left: 3px;
  top: 3px;
  background: #fff;
  border-radius: 50%;
  transition: transform .2s;
}
.toggle-switch input:checked + .toggle-slider { background: #2563eb; }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(16px); }

/* Modal fields */
.modal-field {
  margin-bottom: 18px;
}
.modal-field label {
  display: block;
  font-size: 12.5px;
  color: #374151;
  margin-bottom: 7px;
  font-weight: 400;
}
.modal-field label .req { color: #ef4444; margin-right: 2px; }
.modal-input {
  width: 100%;
  border: none;
  border-bottom: 1.5px solid #d1d5db;
  padding: 8px 2px;
  font-size: 13.5px;
  color: #1e2d3d;
  background: transparent;
  outline: none;
  box-sizing: border-box;
  transition: border-color .16s;
}
.modal-input::placeholder { color: #c4c9d4; }
.modal-input:focus { border-color: #2563eb; }

/* Date row */
.modal-date-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 28px;
  margin-bottom: 18px;
}
.modal-date-field label {
  display: block;
  font-size: 12.5px;
  color: #374151;
  margin-bottom: 7px;
  font-weight: 400;
}
.modal-date-field label .req { color: #ef4444; margin-right: 2px; }
.modal-date-wrap {
  position: relative;
}
.modal-date-wrap input[type="date"] {
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
  cursor: pointer;
}
.modal-date-wrap input[type="date"]:focus { border-color: #2563eb; }
.modal-date-wrap i {
  position: absolute;
  right: 4px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 13px;
  pointer-events: none;
}

/* Radio + checkbox row */
.modal-options-row {
  display: flex;
  align-items: center;
  gap: 22px;
  margin-bottom: 20px;
  font-size: 13.5px;
  color: #374151;
}
.modal-options-row label {
  display: flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
}
.modal-options-row input[type="radio"],
.modal-options-row input[type="checkbox"] {
  accent-color: #2563eb;
  width: 15px;
  height: 15px;
  cursor: pointer;
}

/* Modal actions */
.modal-actions {
  display: flex;
  justify-content: center;
  gap: 14px;
  margin-top: 6px;
  border-top: 1px solid #e8ecf0;
  padding-top: 20px;
}

/* Flash */
.flash-msg {
  padding: 10px 16px;
  border-radius: 7px;
  font-size: 13px;
  margin-bottom: 14px;
  font-weight: 500;
}
.flash-msg.success { background: #dcfce7; color: #166534; }
.flash-msg.error   { background: #fee2e2; color: #991b1b; }

.cal-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  font-size: 13.5px;
}
</style>

<?php if ($toast_msg): ?>
<div class="toast-box <?= esc($toast_type) ?>" id="toastBox">
  <i class="fa-solid <?= $toast_type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
  <?= esc($toast_msg) ?>
</div>
<style>
.toast-box{position:fixed;top:18px;right:18px;z-index:99999;min-width:280px;padding:13px 16px;border-radius:8px;color:#fff;font-size:13.5px;font-weight:600;box-shadow:0 10px 30px rgba(0,0,0,.18);display:flex;gap:10px;align-items:center}
.toast-box.success{background:#16a34a}
.toast-box.error{background:#dc2626}
</style>
<script>
setTimeout(() => {
  const t = document.getElementById('toastBox');
  if (t) t.remove();
}, 3500);
</script>
<?php endif; ?>


<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
<div class="cal-wrapper">

  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= esc($k) ?>" class="cfg-tab <?= $k==='Organization'?'active':'' ?>">
      <?= esc($l) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="cal-inner">

    <div class="cal-topbar">
      <nav class="cal-breadcrumb">
        <a href="configuration#Organization">Organization Masters</a>
        <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span>Calendar</span>
      </nav>

      <?php if ($mode !== 'add'): ?>
      <button class="btn-add-cal" onclick="setMode('add')">
        <i class="fa-solid fa-plus"></i> Add Calendar
      </button>
      <?php endif; ?>
    </div>

    <div class="cal-panel">

      <div class="cal-list-col">
        <div class="cal-list-heading">List of Calendars</div>

        <div class="cal-search-wrap">
          <form method="GET" style="display:contents" id="searchForm">
            <input type="hidden" name="mode" value="view">
            <div class="cal-search-inner">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" name="q" class="cal-search-input"
                     placeholder="Search items"
                     value="<?= esc($search) ?>">
            </div>
          </form>
        </div>

        <div class="cal-list-scroll">
          <?php foreach ($cals as $cal): ?>
            <div class="cal-item <?= ((int)$cal['id'] === $active_id && $mode !== 'add') ? 'active' : '' ?>"
                 onclick="selectCal(<?= (int)$cal['id'] ?>)">
              <span class="cal-item-name"><?= esc($cal['cal_name']) ?></span>
              <i class="fa-solid <?= ((int)$cal['id'] === $active_id && $mode !== 'add') ? 'fa-chevron-right' : 'fa-chevron-down' ?> cal-item-chevron"></i>
            </div>
          <?php endforeach; ?>

          <?php if (empty($cals)): ?>
            <div style="padding:22px 16px;color:#9ca3af;font-size:13px">No calendars found.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="cal-detail-col">
        <div class="cal-detail-heading">Calendar Details</div>

        <?php if ($mode === 'add'): ?>

        <div class="cal-detail-title" style="margin-bottom:24px">ADD CALENDAR</div>

        <form method="POST">
          <input type="hidden" name="action" value="add_cal">

          <div class="cal-field-grid" style="margin-bottom:20px">
            <div class="cal-field">
              <label><span class="req">*</span> Code Name</label>
              <input type="text" name="code_name" class="cal-input" placeholder="Code Name" required>
            </div>

            <div class="cal-field">
              <label><span class="req">*</span> Calendar Name</label>
              <input type="text" name="cal_name" class="cal-input" placeholder="Calendar Name" required>
            </div>
          </div>

          <div class="cal-field-grid single" style="margin-bottom:10px">
            <div class="cal-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="cal-input" placeholder="Remarks">
            </div>
          </div>

          <div class="cal-form-actions">
            <button type="button" class="btn-cancel-sm" onclick="setMode('view')">Cancel</button>
            <button type="submit" class="btn-save-sm">Add</button>
          </div>
        </form>

        <?php elseif ($mode === 'edit' && $active_cal): ?>

        <div class="cal-detail-title" style="margin-bottom:24px">
          EDIT — <?= esc($active_cal['cal_name']) ?>
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="edit_cal">
          <input type="hidden" name="edit_id" value="<?= (int)$active_cal['id'] ?>">

          <div class="cal-field-grid" style="margin-bottom:20px">
            <div class="cal-field">
              <label><span class="req">*</span> Code Name</label>
              <input type="text" name="code_name" class="cal-input"
                     value="<?= esc($active_cal['code_name']) ?>" required>
            </div>

            <div class="cal-field">
              <label><span class="req">*</span> Calendar Name</label>
              <input type="text" name="cal_name" class="cal-input"
                     value="<?= esc($active_cal['cal_name']) ?>" required>
            </div>
          </div>

          <div class="cal-field-grid single" style="margin-bottom:10px">
            <div class="cal-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="cal-input"
                     value="<?= esc($active_cal['remarks'] ?? '') ?>">
            </div>
          </div>

          <div class="cal-form-actions">
            <button type="button" class="btn-cancel-sm"
                    onclick="window.location.href='?id=<?= (int)$active_cal['id'] ?>&mode=view'">
              Cancel
            </button>
            <button type="submit" class="btn-save-sm">Update</button>
          </div>
        </form>

        <?php elseif ($active_cal): ?>

        <div class="cal-detail-title-bar">
          <div class="cal-detail-title">NAME</div>
          <button class="btn-edit-link"
                  onclick="window.location.href='?id=<?= (int)$active_cal['id'] ?>&mode=edit'">
            <i class="fa-regular fa-pen-to-square"></i> Edit Details
          </button>
        </div>

        <div class="cal-field-grid" style="margin-bottom:14px">
          <div class="cal-field">
            <label>Code Name</label>
            <div class="cal-field-value"><?= esc($active_cal['code_name']) ?></div>
          </div>

          <div class="cal-field">
            <label>Calendar Name</label>
            <div class="cal-field-value"><?= esc($active_cal['cal_name']) ?></div>
          </div>
        </div>

        <div class="cal-field-grid single" style="margin-bottom:0">
          <div class="cal-field">
            <label>Remarks</label>
            <div class="cal-field-value"><?= esc($active_cal['remarks'] ?? '') ?>&nbsp;</div>
          </div>
        </div>

        <div class="cal-holidays-bar">
          <span class="cal-holidays-count">Holidays - <?= count($holidays) ?></span>
          <button class="btn-add-new" onclick="openHolidayModal(<?= (int)$active_cal['id'] ?>)">
            <i class="fa-solid fa-plus"></i> Add New
          </button>
        </div>

        <div class="cal-holidays-table-wrap">
          <table class="cal-holidays-table">
            <thead>
              <tr>
                <th>Holiday Name</th>
                <th>Start Date - Day</th>
                <th>End Date - Day</th>
                <th>Days</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($holidays as $h): ?>
              <tr>
                <td><?= esc($h['holiday_name']) ?></td>
                <td><?= esc(formatHolidayDate($h['start_date'])) ?></td>
                <td><?= esc(formatHolidayDate($h['end_date'])) ?></td>
                <td><?= esc($h['days']) ?></td>
              </tr>
              <?php endforeach; ?>

              <?php if (empty($holidays)): ?>
              <tr>
                <td colspan="4" style="text-align:center;color:#9ca3af;padding:20px">
                  No holidays added yet.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php else: ?>

        <div class="cal-empty">Select a calendar to view details.</div>

        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
</div>

<div class="modal-overlay" id="holidayModal">
  <div class="modal-box">
    <div class="modal-title">Quick Add Holiday</div>

    <form method="POST" id="holidayForm">
      <input type="hidden" name="action" value="add_holiday">
      <input type="hidden" name="cal_id" id="modal_cal_id" value="">

      <div class="toggle-row">
        <label class="toggle-switch">
          <input type="checkbox" name="is_halfday" id="toggle_halfday">
          <span class="toggle-slider"></span>
        </label>
        <span class="toggle-label">Half-day</span>
      </div>

      <div class="modal-field">
        <label><span class="req">*</span> Code Name</label>
        <input type="text" name="code_name" class="modal-input" placeholder="Code Name" required>
      </div>

      <div class="modal-field">
        <label><span class="req">*</span> Holiday Name</label>
        <input type="text" name="holiday_name" class="modal-input" placeholder="Holiday Name" required>
      </div>

      <div class="modal-date-grid">
        <div class="modal-date-field">
          <label><span class="req">*</span> Start Date</label>
          <div class="modal-date-wrap">
            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
            <i class="fa-regular fa-calendar"></i>
          </div>
        </div>

        <div class="modal-date-field">
          <label><span class="req">*</span> End Date</label>
          <div class="modal-date-wrap">
            <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" required>
            <i class="fa-regular fa-calendar"></i>
          </div>
        </div>
      </div>

      <div class="modal-options-row">
        <label>
          <input type="checkbox" name="is_optional" value="1">
          Optional Holiday
        </label>

        <label>
          <input type="radio" name="holiday_type" value="Holiday" checked>
          Holiday
        </label>

        <label>
          <input type="radio" name="holiday_type" value="Week-Off">
          Week-Off
        </label>
      </div>

      <div style="border-top:1px solid #e8ecf0;margin-bottom:0"></div>

      <div class="modal-actions">
        <button type="button" class="btn-cancel-sm" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-save-sm">Add</button>
      </div>
    </form>
  </div>
</div>

<script>
function selectCal(id) {
  const url = new URL(window.location.href);
  url.searchParams.set('id', id);
  url.searchParams.set('mode', 'view');

  const q = document.querySelector('input[name="q"]');
  if (q && q.value.trim() !== '') {
    url.searchParams.set('q', q.value.trim());
  } else {
    url.searchParams.delete('q');
  }

  window.location.href = url.toString();
}

function setMode(mode) {
  const url = new URL(window.location.href);
  url.searchParams.set('mode', mode);

  if (mode === 'add') {
    url.searchParams.delete('id');
  }

  window.location.href = url.toString();
}

function openHolidayModal(calId) {
  document.getElementById('modal_cal_id').value = calId;
  document.getElementById('holidayModal').classList.add('show');
}

function closeModal() {
  document.getElementById('holidayModal').classList.remove('show');
}

const modal = document.getElementById('holidayModal');
if (modal) {
  modal.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });
}

let searchTimer;
const searchInput = document.querySelector('.cal-search-input');

if (searchInput) {
  searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      document.getElementById('searchForm').submit();
    }, 450);
  });
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>