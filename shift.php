<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Shift Details';
ob_start();

function esc($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function timeInput($time) {
    if (!$time) return '00:00';
    return date('H:i', strtotime($time));
}

function timeDisplay($time) {
    if (!$time) return '';
    return date('h:i A', strtotime($time));
}

function durationTime($start, $end, $nextDay = 0) {
    $s = strtotime($start);
    $e = strtotime($end);

    if ($nextDay) {
        $e += 86400;
    }

    if ($e < $s) {
        $e += 86400;
    }

    $diff = max(0, $e - $s);
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);

    return sprintf('%02d:%02d', $h, $m);
}

function shift_label(array $s): string {
    return $s['shift_start_display'] . ' - ' . $s['shift_end_display'] . ' ( ' . $s['duration'] . ' Hrs )';
}

if (!isset($conn) || !$conn) {
    die('Database connection not found.');
}

$active_id = (int)($_GET['id'] ?? 0);
$mode      = $_GET['mode'] ?? 'view';

$toast_msg  = $_SESSION['toast_msg'] ?? '';
$toast_type = $_SESSION['toast_type'] ?? '';
unset($_SESSION['toast_msg'], $_SESSION['toast_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $shift_name   = trim($_POST['shift_name'] ?? '');
    $shift_code   = trim($_POST['shift_code'] ?? '');
    $shift_start  = trim($_POST['shift_start'] ?? '');
    $shift_end    = trim($_POST['shift_end'] ?? '');
    $next_day     = isset($_POST['next_day']) ? 1 : 0;

    $grace_in     = trim($_POST['grace_in'] ?? '00:00');
    $grace_out    = trim($_POST['grace_out'] ?? '00:00');
    $early_start  = trim($_POST['early_start'] ?? '00:00');
    $late_end     = trim($_POST['late_end'] ?? '00:00');

    $min_ot_hrs   = trim($_POST['min_ot_hrs'] ?? '00:00');
    $min_full_day = trim($_POST['min_full_day'] ?? '00:00');
    $min_half_day = trim($_POST['min_half_day'] ?? '00:00');
    $round_hrs    = trim($_POST['round_hrs'] ?? '00:00');

    $remarks      = trim($_POST['remarks'] ?? '');

    if ($action === 'add_shift') {
        if ($shift_name === '' || $shift_start === '' || $shift_end === '') {
            $_SESSION['toast_msg']  = 'Shift Name, Start Time and End Time are required.';
            $_SESSION['toast_type'] = 'error';
            header("Location: ?mode=add");
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO att_shifts
            (shift_name, shift_code, shift_start, shift_end, next_day, grace_in, grace_out, early_start, late_end, min_ot_hrs, min_full_day, min_half_day, round_hrs, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssisssssssss",
            $shift_name,
            $shift_code,
            $shift_start,
            $shift_end,
            $next_day,
            $grace_in,
            $grace_out,
            $early_start,
            $late_end,
            $min_ot_hrs,
            $min_full_day,
            $min_half_day,
            $round_hrs,
            $remarks
        );

        if ($stmt->execute()) {
            $_SESSION['toast_msg']  = 'Shift added successfully.';
            $_SESSION['toast_type'] = 'success';
            header("Location: ?id=" . $stmt->insert_id . "&mode=view");
            exit;
        } else {
            $_SESSION['toast_msg']  = 'Save failed: ' . $stmt->error;
            $_SESSION['toast_type'] = 'error';
            header("Location: ?mode=add");
            exit;
        }
    }

    if ($action === 'edit_shift') {
        $id = (int)($_POST['edit_id'] ?? 0);

        if ($id <= 0 || $shift_name === '' || $shift_start === '' || $shift_end === '') {
            $_SESSION['toast_msg']  = 'Shift Name, Start Time and End Time are required.';
            $_SESSION['toast_type'] = 'error';
            header("Location: ?id=" . $id . "&mode=edit");
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE att_shifts SET
              shift_name=?,
              shift_code=?,
              shift_start=?,
              shift_end=?,
              next_day=?,
              grace_in=?,
              grace_out=?,
              early_start=?,
              late_end=?,
              min_ot_hrs=?,
              min_full_day=?,
              min_half_day=?,
              round_hrs=?,
              remarks=?
            WHERE id=?
        ");

        $stmt->bind_param(
            "ssssisssssssssi",
            $shift_name,
            $shift_code,
            $shift_start,
            $shift_end,
            $next_day,
            $grace_in,
            $grace_out,
            $early_start,
            $late_end,
            $min_ot_hrs,
            $min_full_day,
            $min_half_day,
            $round_hrs,
            $remarks,
            $id
        );

        if ($stmt->execute()) {
            $_SESSION['toast_msg']  = 'Shift updated successfully.';
            $_SESSION['toast_type'] = 'success';
        } else {
            $_SESSION['toast_msg']  = 'Update failed: ' . $stmt->error;
            $_SESSION['toast_type'] = 'error';
        }

        header("Location: ?id=" . $id . "&mode=view");
        exit;
    }
}

$shifts = [];
$res = $conn->query("SELECT * FROM att_shifts WHERE status='active' ORDER BY shift_name ASC");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['shift_start_display'] = timeDisplay($row['shift_start']);
        $row['shift_end_display']   = timeDisplay($row['shift_end']);
        $row['duration']            = durationTime($row['shift_start'], $row['shift_end'], (int)$row['next_day']);

        $row['grace_in_input']      = timeInput($row['grace_in']);
        $row['grace_out_input']     = timeInput($row['grace_out']);
        $row['early_start_input']   = timeInput($row['early_start']);
        $row['late_end_input']      = timeInput($row['late_end']);
        $row['min_ot_hrs_input']    = timeInput($row['min_ot_hrs']);
        $row['min_full_day_input']  = timeInput($row['min_full_day']);
        $row['min_half_day_input']  = timeInput($row['min_half_day']);
        $row['round_hrs_input']     = timeInput($row['round_hrs']);

        $row['grace_in_display']     = date('H:i', strtotime($row['grace_in']));
        $row['grace_out_display']    = date('H:i', strtotime($row['grace_out']));
        $row['early_start_display']  = date('H:i', strtotime($row['early_start']));
        $row['late_end_display']     = date('H:i', strtotime($row['late_end']));
        $row['min_ot_hrs_display']   = date('H:i', strtotime($row['min_ot_hrs']));
        $row['min_full_day_display'] = date('H:i', strtotime($row['min_full_day']));
        $row['min_half_day_display'] = date('H:i', strtotime($row['min_half_day']));
        $row['round_hrs_display']    = date('H:i', strtotime($row['round_hrs']));

        $shifts[] = $row;
    }
}

if ($active_id === 0 && $mode === 'view' && count($shifts)) {
    $active_id = (int)$shifts[0]['id'];
}

$active_shift = null;
foreach ($shifts as $s) {
    if ((int)$s['id'] === $active_id) {
        $active_shift = $s;
        break;
    }
}
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
.sh-wrapper {
  font-family: 'Segoe UI', sans-serif;
  color: #1e2d3d;
  padding: 0 0 40px;
}
.sh-inner { padding: 20px 28px; }

/* ── Top bar ── */
.sh-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
}
.sh-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  color: #555;
}
.sh-breadcrumb a { color: #1e2d3d; text-decoration: none; font-weight: 600; }
.sh-breadcrumb a:hover { text-decoration: underline; }
.sh-breadcrumb .sep { color: #bbb; font-size: 11px; }
.sh-breadcrumb span { color: #374151; }

.btn-add-shift {
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
.btn-add-shift:hover { background: #1d4ed8; }

/* ── Split panel ── */
.sh-panel {
  display: flex;
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 10px;
  overflow: hidden;
  min-height: 580px;
}

/* Left */
.sh-list-col {
  width: 35%;
  min-width: 240px;
  border-right: 1px solid #e8ecf0;
  display: flex;
  flex-direction: column;
}
.sh-list-heading {
  padding: 14px 16px 12px;
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
  border-bottom: 1px solid #f1f4f8;
}
.sh-list-scroll {
  flex: 1;
  overflow-y: auto;
  max-height: 620px;
}
.sh-list-scroll::-webkit-scrollbar { width: 4px; }
.sh-list-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

.sh-item {
  padding: 12px 16px;
  border-bottom: 1px solid #f1f4f8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: background .12s;
}
.sh-item:last-child { border-bottom: none; }
.sh-item:hover { background: #f8fafc; }
.sh-item.active {
  background: #eff6ff;
  border-left: 3px solid #2563eb;
  padding-left: 13px;
}
.sh-item-info { display: flex; flex-direction: column; gap: 2px; }
.sh-item-name { font-size: 13.5px; font-weight: 500; color: #1e2d3d; }
.sh-item.active .sh-item-name { color: #2563eb; font-weight: 700; }
.sh-item-time { font-size: 12px; color: #6b7280; }
.sh-item-chevron { font-size: 11px; color: #9ca3af; flex-shrink: 0; }

/* Right */
.sh-detail-col {
  flex: 1;
  padding: 22px 28px;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}
.sh-detail-heading {
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
  border-bottom: 1px solid #e8ecf0;
  padding-bottom: 12px;
  margin-bottom: 20px;
}
.sh-detail-title-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.sh-detail-title {
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

/* Section heading inside detail */
.sh-section-title {
  font-size: 13.5px;
  font-weight: 700;
  color: #1e2d3d;
  margin: 18px 0 14px;
}

/* Field grids */
.sh-field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px 36px;
  margin-bottom: 6px;
}
.sh-field-grid.col3 { grid-template-columns: 1fr 1fr auto; }
.sh-field-grid.single { grid-template-columns: 1fr; }

.sh-field label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
  font-weight: 500;
}
.sh-field label .req { color: #ef4444; margin-right: 2px; }

/* View value */
.sh-field-value {
  font-size: 13.5px;
  color: #1e2d3d;
  padding-bottom: 8px;
  border-bottom: 1px solid #e2e8f0;
  min-height: 26px;
}

/* Inputs */
.sh-input {
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
.sh-input::placeholder { color: #c4c9d4; }
.sh-input:focus { border-color: #2563eb; }

/* Time input with clock icon */
.sh-time-wrap {
  position: relative;
}
.sh-time-wrap input[type="time"] {
  width: 100%;
  border: none;
  border-bottom: 1.5px solid #d1d5db;
  padding: 8px 28px 8px 2px;
  font-size: 13.5px;
  color: #1e2d3d;
  background: transparent;
  outline: none;
  box-sizing: border-box;
  transition: border-color .16s;
  cursor: pointer;
}
.sh-time-wrap input[type="time"]:focus { border-color: #2563eb; }
.sh-time-wrap i {
  position: absolute;
  right: 4px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 13px;
  pointer-events: none;
}

/* Next day checkbox */
.sh-nextday {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #374151;
  padding-top: 24px;
  white-space: nowrap;
}
.sh-nextday input[type="checkbox"] {
  width: 15px;
  height: 15px;
  accent-color: #2563eb;
  cursor: pointer;
}

/* Form actions */
.sh-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 28px;
  padding-top: 16px;
  border-top: 1px solid #e8ecf0;
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
  transition: background .14s;
}
.btn-cancel:hover { background: #f1f5f9; }
.btn-save {
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
.btn-save:hover { background: #1d4ed8; }

/* Empty */
.sh-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  font-size: 13.5px;
}

/* Toast */
.toast-box{
  position:fixed;
  top:18px;
  right:18px;
  z-index:99999;
  min-width:280px;
  padding:13px 16px;
  border-radius:8px;
  color:#fff;
  font-size:13.5px;
  font-weight:600;
  box-shadow:0 10px 30px rgba(0,0,0,.18);
  display:flex;
  gap:10px;
  align-items:center;
}
.toast-box.success{background:#16a34a}
.toast-box.error{background:#dc2626}
</style>

<?php if ($toast_msg): ?>
<div class="toast-box <?= esc($toast_type) ?>" id="toastBox">
  <i class="fa-solid <?= $toast_type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
  <?= esc($toast_msg) ?>
</div>
<script>
setTimeout(() => {
  const t = document.getElementById('toastBox');
  if (t) t.remove();
}, 3500);
</script>
<?php endif; ?>

<div class="section-card" style="padding:0;overflow:hidden">
<div class="sh-wrapper">

  <!-- Config nav tabs -->
  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll',
                    'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= esc($k) ?>"
       class="cfg-tab <?= $k==='Attendance'?'active':'' ?>">
      <?= esc($l) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="sh-inner">

    <!-- Top bar -->
    <div class="sh-topbar">
      <nav class="sh-breadcrumb">
        <a href="attendance_config.php">Attendance</a>
        <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span>Shift Details</span>
      </nav>

      <?php if ($mode !== 'add'): ?>
      <button class="btn-add-shift" onclick="setMode('add')">
        <i class="fa-solid fa-plus"></i> Add Shift
      </button>
      <?php endif; ?>
    </div>

    <!-- Split panel -->
    <div class="sh-panel">

      <!-- Left list -->
      <div class="sh-list-col">
        <div class="sh-list-heading">List of Shifts</div>

        <div class="sh-list-scroll">
          <?php foreach ($shifts as $shift): ?>
            <div class="sh-item <?= ((int)$shift['id'] === $active_id && $mode !== 'add') ? 'active' : '' ?>"
                 onclick="selectShift(<?= (int)$shift['id'] ?>)">
              <div class="sh-item-info">
                <span class="sh-item-name"><?= esc($shift['shift_name']) ?></span>
                <span class="sh-item-time"><?= esc(shift_label($shift)) ?></span>
              </div>
              <i class="fa-solid <?= ((int)$shift['id'] === $active_id && $mode !== 'add') ? 'fa-chevron-right' : 'fa-chevron-down' ?> sh-item-chevron"></i>
            </div>
          <?php endforeach; ?>

          <?php if (empty($shifts)): ?>
            <div style="padding:22px 16px;color:#9ca3af;font-size:13px">No shifts found.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right panel -->
      <div class="sh-detail-col">
        <div class="sh-detail-heading">Shift Details</div>

        <?php if ($mode === 'add'): ?>

        <div class="sh-detail-title" style="margin-bottom:22px">ADD SHIFT</div>

        <form method="POST">
          <input type="hidden" name="action" value="add_shift">

          <div class="sh-field-grid" style="margin-bottom:20px">
            <div class="sh-field">
              <label><span class="req">*</span> Shift Name</label>
              <input type="text" name="shift_name" class="sh-input" placeholder="Shift Name" required>
            </div>

            <div class="sh-field">
              <label>Shift Code</label>
              <input type="text" name="shift_code" class="sh-input" placeholder="Shift Code">
            </div>
          </div>

          <div class="sh-field-grid col3" style="margin-bottom:20px;align-items:end">
            <div class="sh-field">
              <label><span class="req">*</span> Shift Start Time</label>
              <div class="sh-time-wrap">
                <input type="time" name="shift_start" value="00:00" required>
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label><span class="req">*</span> Shift End Time</label>
              <div class="sh-time-wrap">
                <input type="time" name="shift_end" value="00:00" required>
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <label class="sh-nextday">
              <input type="checkbox" name="next_day" value="1">
              Next Day
            </label>
          </div>

          <div class="sh-section-title">Late &amp; Early Check In / Check Out</div>

          <div class="sh-field-grid" style="margin-bottom:20px">
            <div class="sh-field">
              <label>Grace Period for Check In (Come Late)</label>
              <div class="sh-time-wrap">
                <input type="time" name="grace_in" value="00:00">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Grace Period for Check Out ( Go Early )</label>
              <div class="sh-time-wrap">
                <input type="time" name="grace_out" value="00:00">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Early Start allowed for Check In</label>
              <div class="sh-time-wrap">
                <input type="time" name="early_start" value="00:00">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Late End allowed for Check Out</label>
              <div class="sh-time-wrap">
                <input type="time" name="late_end" value="00:00">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>
          </div>

          <div class="sh-section-title">Working Hours</div>

          <div class="sh-field-grid" style="margin-bottom:20px">
            <div class="sh-field">
              <label>Min. Working Hours for OT</label>
              <div class="sh-time-wrap">
                <input type="time" name="min_ot_hrs" value="00:00">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Min. Shift Duration (Full Day)</label>
              <div class="sh-time-wrap">
                <input type="time" name="min_full_day" value="00:00">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Min Shift Duration (Half Day)</label>
              <div class="sh-time-wrap">
                <input type="time" name="min_half_day" value="00:00">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Round Worked Hours</label>
              <div class="sh-time-wrap">
                <input type="time" name="round_hrs" value="00:00">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>
          </div>

          <div class="sh-field-grid single" style="margin-bottom:10px">
            <div class="sh-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="sh-input" placeholder="Remarks">
            </div>
          </div>

          <div class="sh-form-actions">
            <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
            <button type="submit" class="btn-save">Add</button>
          </div>
        </form>

        <?php elseif ($mode === 'edit' && $active_shift): ?>

        <div class="sh-detail-title" style="margin-bottom:22px">
          EDIT — <?= esc($active_shift['shift_name']) ?>
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="edit_shift">
          <input type="hidden" name="edit_id" value="<?= (int)$active_shift['id'] ?>">

          <div class="sh-field-grid" style="margin-bottom:20px">
            <div class="sh-field">
              <label><span class="req">*</span> Shift Name</label>
              <input type="text" name="shift_name" class="sh-input"
                     value="<?= esc($active_shift['shift_name']) ?>" required>
            </div>

            <div class="sh-field">
              <label>Shift Code</label>
              <input type="text" name="shift_code" class="sh-input"
                     value="<?= esc($active_shift['shift_code']) ?>">
            </div>
          </div>

          <div class="sh-field-grid col3" style="margin-bottom:20px;align-items:end">
            <div class="sh-field">
              <label><span class="req">*</span> Shift Start Time</label>
              <div class="sh-time-wrap">
                <input type="time" name="shift_start"
                       value="<?= esc(timeInput($active_shift['shift_start'])) ?>" required>
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label><span class="req">*</span> Shift End Time</label>
              <div class="sh-time-wrap">
                <input type="time" name="shift_end"
                       value="<?= esc(timeInput($active_shift['shift_end'])) ?>" required>
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <label class="sh-nextday">
              <input type="checkbox" name="next_day" value="1"
                     <?= ((int)$active_shift['next_day'] === 1) ? 'checked' : '' ?>>
              Next Day
            </label>
          </div>

          <div class="sh-section-title">Late &amp; Early Check In / Check Out</div>

          <div class="sh-field-grid" style="margin-bottom:20px">
            <div class="sh-field">
              <label>Grace Period for Check In (Come Late)</label>
              <div class="sh-time-wrap">
                <input type="time" name="grace_in" value="<?= esc($active_shift['grace_in_input']) ?>">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Grace Period for Check Out ( Go Early )</label>
              <div class="sh-time-wrap">
                <input type="time" name="grace_out" value="<?= esc($active_shift['grace_out_input']) ?>">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Early Start allowed for Check In</label>
              <div class="sh-time-wrap">
                <input type="time" name="early_start" value="<?= esc($active_shift['early_start_input']) ?>">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Late End allowed for Check Out</label>
              <div class="sh-time-wrap">
                <input type="time" name="late_end" value="<?= esc($active_shift['late_end_input']) ?>">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>
          </div>

          <div class="sh-section-title">Working Hours</div>

          <div class="sh-field-grid" style="margin-bottom:20px">
            <div class="sh-field">
              <label>Min. Working Hours for OT</label>
              <div class="sh-time-wrap">
                <input type="time" name="min_ot_hrs" value="<?= esc($active_shift['min_ot_hrs_input']) ?>">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Min. Shift Duration (Full Day)</label>
              <div class="sh-time-wrap">
                <input type="time" name="min_full_day" value="<?= esc($active_shift['min_full_day_input']) ?>">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Min Shift Duration (Half Day)</label>
              <div class="sh-time-wrap">
                <input type="time" name="min_half_day" value="<?= esc($active_shift['min_half_day_input']) ?>">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>

            <div class="sh-field">
              <label>Round Worked Hours</label>
              <div class="sh-time-wrap">
                <input type="time" name="round_hrs" value="<?= esc($active_shift['round_hrs_input']) ?>">
                <i class="fa-regular fa-clock"></i>
              </div>
            </div>
          </div>

          <div class="sh-field-grid single" style="margin-bottom:10px">
            <div class="sh-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="sh-input"
                     value="<?= esc($active_shift['remarks']) ?>">
            </div>
          </div>

          <div class="sh-form-actions">
            <button type="button" class="btn-cancel"
                    onclick="window.location.href='?id=<?= (int)$active_shift['id'] ?>&mode=view'">
              Cancel
            </button>
            <button type="submit" class="btn-save">Update</button>
          </div>
        </form>

        <?php elseif ($active_shift): ?>

        <div class="sh-detail-title-bar">
          <div class="sh-detail-title"><?= esc($active_shift['shift_name']) ?></div>
          <button class="btn-edit-link"
                  onclick="window.location.href='?id=<?= (int)$active_shift['id'] ?>&mode=edit'">
            <i class="fa-regular fa-pen-to-square"></i> Edit Details
          </button>
        </div>

        <div class="sh-field-grid" style="margin-bottom:16px">
          <div class="sh-field">
            <label>Shift Name</label>
            <div class="sh-field-value"><?= esc($active_shift['shift_name']) ?></div>
          </div>

          <div class="sh-field">
            <label>Shift Code</label>
            <div class="sh-field-value"><?= esc($active_shift['shift_code']) ?></div>
          </div>
        </div>

        <div class="sh-field-grid" style="margin-bottom:16px">
          <div class="sh-field">
            <label>Shift Start Time</label>
            <div class="sh-field-value"><?= esc($active_shift['shift_start_display']) ?></div>
          </div>

          <div class="sh-field">
            <label>Shift End Time</label>
            <div style="display:flex;align-items:center;gap:16px">
              <div class="sh-field-value" style="flex:1"><?= esc($active_shift['shift_end_display']) ?></div>

              <?php if ((int)$active_shift['next_day'] === 1): ?>
                <span style="display:flex;align-items:center;gap:5px;font-size:13px;color:#374151">
                  <input type="checkbox" checked disabled style="accent-color:#2563eb"> Next Day
                </span>
              <?php else: ?>
                <span style="display:flex;align-items:center;gap:5px;font-size:13px;color:#9ca3af">
                  <input type="checkbox" disabled> Next Day
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="sh-section-title">Late &amp; Early Check In / Check Out</div>

        <div class="sh-field-grid" style="margin-bottom:16px">
          <div class="sh-field">
            <label>Grace Period for Check In (Come Late)</label>
            <div class="sh-field-value"><?= esc($active_shift['grace_in_display']) ?></div>
          </div>

          <div class="sh-field">
            <label>Grace Period for Check Out ( Go Early )</label>
            <div class="sh-field-value"><?= esc($active_shift['grace_out_display']) ?></div>
          </div>

          <div class="sh-field">
            <label>Early Start allowed for Check In</label>
            <div class="sh-field-value"><?= esc($active_shift['early_start_display']) ?></div>
          </div>

          <div class="sh-field">
            <label>Late End allowed for Check Out</label>
            <div class="sh-field-value"><?= esc($active_shift['late_end_display']) ?></div>
          </div>
        </div>

        <div class="sh-section-title">Working Hours</div>

        <div class="sh-field-grid" style="margin-bottom:16px">
          <div class="sh-field">
            <label>Min. Working Hours for OT</label>
            <div class="sh-field-value"><?= esc($active_shift['min_ot_hrs_display']) ?></div>
          </div>

          <div class="sh-field">
            <label>Min. Shift Duration (Full Day)</label>
            <div class="sh-field-value"><?= esc($active_shift['min_full_day_display']) ?></div>
          </div>

          <div class="sh-field">
            <label>Min Shift Duration (Half Day)</label>
            <div class="sh-field-value"><?= esc($active_shift['min_half_day_display']) ?></div>
          </div>

          <div class="sh-field">
            <label>Round Worked Hours</label>
            <div class="sh-field-value"><?= esc($active_shift['round_hrs_display']) ?></div>
          </div>
        </div>

        <div class="sh-field-grid single">
          <div class="sh-field">
            <label>Remarks</label>
            <div class="sh-field-value"><?= esc($active_shift['remarks']) ?>&nbsp;</div>
          </div>
        </div>

        <?php else: ?>

        <div class="sh-empty">Select a shift to view details.</div>

        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
</div>

<script>
function selectShift(id) {
  const url = new URL(window.location.href);
  url.searchParams.set('id', id);
  url.searchParams.set('mode', 'view');
  window.location.href = url.toString();
}

function setMode(mode, id) {
  const url = new URL(window.location.href);
  url.searchParams.set('mode', mode);

  if (mode === 'add') {
    url.searchParams.delete('id');
  }

  if (id !== undefined) {
    url.searchParams.set('id', id);
  }

  window.location.href = url.toString();
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>