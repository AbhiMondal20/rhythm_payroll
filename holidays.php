<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/db_client.php';
require_once 'includes/config.php';
$page_title = 'Holidays Configuration';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ── Config nav tabs ── */
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}

/* ── Page ── */
.hc-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.hc-inner{padding:18px 24px}

/* topbar */
.hc-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.hc-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555}
.hc-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.hc-breadcrumb a:hover{text-decoration:underline}
.hc-breadcrumb .sep{color:#bbb;font-size:11px}
.btn-add-hol{display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .16s}
.btn-add-hol:hover{background:#1d4ed8}

/* ── Sub-header row ── */
.hc-sub-header{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #e8ecf0;margin-bottom:0}
.hc-sub-left{padding:10px 16px;font-size:12px;color:#6b7280;font-weight:600;display:flex;align-items:center;gap:14px}
.hc-sub-right{padding:10px 16px;font-size:12px;color:#6b7280;font-weight:600}

/* year select */
.hc-year-select{padding:5px 22px 5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;color:#374151;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 6px center;outline:none;appearance:none;cursor:pointer;transition:border-color .15s}
.hc-year-select:focus{border-color:#2563eb}

/* ── Split panel ── */
.hc-panel{display:flex;background:#fff;border:1px solid #e8ecf0;border-radius:10px;overflow:hidden;min-height:500px}

/* Left list */
.hc-list-col{width:36%;min-width:240px;border-right:1px solid #e8ecf0;display:flex;flex-direction:column}
.hc-list-scroll{flex:1;overflow-y:auto;max-height:600px}
.hc-list-scroll::-webkit-scrollbar{width:4px}
.hc-list-scroll::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px}

.hc-item{padding:12px 16px;border-bottom:1px solid #f1f4f8;cursor:pointer;display:flex;align-items:center;gap:0;transition:background .12s}
.hc-item:last-child{border-bottom:none}
.hc-item:hover{background:#f8fafc}
.hc-item.active{background:#eff6ff;border-left:3px solid #2563eb;padding-left:13px}
.hc-item-name{flex:1;font-size:13.5px;font-weight:500;color:#1e2d3d}
.hc-item.active .hc-item-name{color:#2563eb;font-weight:700}
.hc-item-num{width:24px;font-size:12px;color:#9ca3af;font-weight:600;flex-shrink:0}
.hc-item-date{font-size:12.5px;color:#2563eb;min-width:90px;flex-shrink:0}
.hc-item:not(.active) .hc-item-date{color:#6b7280}
.hc-item-chevron{font-size:11px;color:#9ca3af;flex-shrink:0;margin-left:8px}

/* Right detail */
.hc-detail-col{flex:1;padding:20px 26px;display:flex;flex-direction:column;overflow-y:auto}

.hc-detail-heading{font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e8ecf0;padding-bottom:12px;margin-bottom:18px}

.hc-detail-title-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.hc-detail-title{font-size:15px;font-weight:800;color:#1e2d3d;text-transform:uppercase;letter-spacing:.3px}

/* toggles */
.hc-toggles{display:flex;align-items:center;gap:18px}
.hc-toggle-wrap{display:flex;align-items:center;gap:8px;font-size:13px;color:#374151}
.toggle-switch{position:relative;width:36px;height:20px;cursor:pointer;flex-shrink:0}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#d1d5db;border-radius:20px;transition:background .2s}
.toggle-slider:before{content:'';position:absolute;width:14px;height:14px;left:3px;top:3px;background:#fff;border-radius:50%;transition:transform .2s}
.toggle-switch input:checked + .toggle-slider{background:#2563eb}
.toggle-switch input:checked + .toggle-slider:before{transform:translateX(16px)}

.btn-edit-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:600;padding:0}
.btn-edit-link:hover{text-decoration:underline}

/* field grid */
.hc-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 36px;margin-bottom:18px}
.hc-field label{display:block;font-size:12px;color:#6b7280;margin-bottom:6px;font-weight:500}
.hc-field label .req{color:#ef4444;margin-right:2px}
.hc-field-value{font-size:13.5px;color:#1e2d3d;padding-bottom:8px;border-bottom:1px solid #e2e8f0;min-height:26px}

/* inputs */
.hc-input{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s}
.hc-input::placeholder{color:#c4c9d4}
.hc-input:focus{border-color:#2563eb}
.hc-select{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 20px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 3px center;outline:none;box-sizing:border-box;appearance:none;cursor:pointer;transition:border-color .16s}
.hc-select:focus{border-color:#2563eb}
.hc-date-wrap{position:relative}
.hc-date-wrap input[type=date]{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 28px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s;cursor:pointer}
.hc-date-wrap input[type=date]:focus{border-color:#2563eb}
.hc-date-wrap i{position:absolute;right:4px;top:50%;transform:translateY(-50%);color:#2563eb;font-size:14px;pointer-events:none}

/* type radios */
.hc-type-section{margin-bottom:18px}
.hc-type-label{font-size:12.5px;color:#374151;margin-bottom:10px;font-weight:400}
.hc-radio-group{display:flex;flex-direction:column;gap:8px}
.hc-radio-item{display:flex;align-items:center;gap:9px;font-size:13.5px;color:#374151;cursor:pointer}
.hc-radio-item input[type=radio]{width:16px;height:16px;accent-color:#2563eb;cursor:pointer}

/* calendar checkboxes */
.hc-cal-section{margin-bottom:18px}
.hc-cal-label{font-size:12px;color:#374151;margin-bottom:8px;font-weight:400}
.hc-cal-chip{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:20px;padding:4px 12px;font-size:12.5px;color:#1d4ed8;font-weight:500}
.hc-cal-chip input[type=checkbox]{width:14px;height:14px;accent-color:#2563eb;cursor:pointer}

/* remarks */
.hc-remarks-section{margin-bottom:14px}
.hc-remarks-label{font-size:12px;color:#6b7280;margin-bottom:6px;display:block;font-weight:500}
.hc-remarks-value{font-size:13.5px;color:#1e2d3d;padding-bottom:8px;border-bottom:1px solid #e2e8f0;min-height:26px}

/* form actions */
.hc-form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:auto;padding-top:20px;border-top:1px solid #e8ecf0}
.btn-cancel{padding:9px 26px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:13.5px;color:#374151;cursor:pointer;font-weight:600;transition:background .14s}
.btn-cancel:hover{background:#f1f5f9}
.btn-delete{padding:9px 26px;border:1.5px solid #ef4444;background:#fff;border-radius:6px;font-size:13.5px;color:#ef4444;cursor:pointer;font-weight:600;transition:background .14s}
.btn-delete:hover{background:#fee2e2}
.btn-save{padding:9px 26px;background:#2563eb;border:none;border-radius:6px;font-size:13.5px;color:#fff;cursor:pointer;font-weight:600;transition:background .14s}
.btn-save:hover{background:#1d4ed8}

/* add form card */
.hc-add-card{background:#fff;border:1px solid #e8ecf0;border-radius:10px;padding:26px 28px 10px}
.hc-add-title{font-size:14px;font-weight:800;color:#1e2d3d;text-transform:uppercase;letter-spacing:.3px;margin-bottom:22px}

/* flash / toast */
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
@keyframes toastOut{from{opacity:1}to{opacity:0;transform:translateX(40px)}}
</style>

<?php
/* ════════ STATE ════════ */
$active_id  = (int)($_GET['id']   ?? 0);
$mode       = $_GET['mode']       ?? 'view';
$year       = (int)($_GET['year'] ?? date('Y'));
$flash      = '';
$flash_type = 'success';

/* ════════ POST HANDLERS ════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_holiday') {
        $code = trim($_POST['code_name']    ?? '');
        $name = trim($_POST['holiday_name'] ?? '');
        $sd   = trim($_POST['start_date']   ?? '');
        $ed   = trim($_POST['end_date']     ?? '');
        if ($code === '' || $name === '' || !$sd) {
            $flash = 'Code Name, Holiday Name and Start Date are required.';
            $flash_type = 'error';
            $mode = 'add';
        } else {
            // DB insert:
            $stmt = $pdo->prepare("INSERT INTO att_holidays
                (code_name, holiday_name, start_date, end_date, type, is_halfday, is_optional, calendars, remarks)
                VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$code,$name,$sd,$ed??$sd,
                $_POST['type']??'Holiday',
                isset($_POST['is_halfday'])?1:0,
                isset($_POST['is_optional'])?1:0,
                implode(',', $_POST['calendars']??[]),
                trim($_POST['remarks']??'')
            ]);
            $flash = "Holiday \"$name\" added.";
            $flash_type = 'success';
            $mode = 'view';
        }
    }

    if ($action === 'save_holiday') {
        $id = (int)($_POST['holiday_id'] ?? 0);
        // DB update similar to insert
        $flash = 'Holiday updated.';
        $flash_type = 'success';
        $active_id = $id;
        $mode = 'view';
    }

    if ($action === 'delete_holiday') {
        $id = (int)($_POST['holiday_id'] ?? 0);
        // $pdo->prepare("DELETE FROM att_holidays WHERE id=?")->execute([$id]);
        $flash = 'Holiday deleted.';
        $flash_type = 'success';
        $active_id = 0;
        $mode = 'view';
    }
}

/* ════════ DEMO DATA ════════
CREATE TABLE att_holidays (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  code_name    VARCHAR(30) NOT NULL,
  holiday_name VARCHAR(100) NOT NULL,
  start_date   DATE NOT NULL,
  end_date     DATE NOT NULL,
  type         ENUM('Holiday','Week-Off') DEFAULT 'Holiday',
  is_halfday   TINYINT DEFAULT 0,
  is_optional  TINYINT DEFAULT 0,
  calendars    VARCHAR(200) COMMENT 'comma-separated calendar names',
  remarks      TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
*/
// $holidays = $pdo->prepare("SELECT * FROM att_holidays WHERE YEAR(start_date)=? ORDER BY start_date")->...
$all_holidays = [
    2026 => [
        ['id'=>1, 'code_name'=>'01012026','holiday_name'=>'New Year',         'start_date'=>'01/01/2026','end_date'=>'01/01/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>2, 'code_name'=>'23012026','holiday_name'=>'Netaji Birthday',  'start_date'=>'23/01/2026','end_date'=>'23/01/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>3, 'code_name'=>'26012026','holiday_name'=>'Republic Day',     'start_date'=>'26/01/2026','end_date'=>'26/01/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>4, 'code_name'=>'04032026','holiday_name'=>'Holi',             'start_date'=>'04/03/2026','end_date'=>'04/03/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>5, 'code_name'=>'15042026','holiday_name'=>'Bengali New Year', 'start_date'=>'15/04/2026','end_date'=>'15/04/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>6, 'code_name'=>'23042026','holiday_name'=>'Election',         'start_date'=>'23/04/2026','end_date'=>'23/04/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>7, 'code_name'=>'01052026','holiday_name'=>'May Day',          'start_date'=>'01/05/2026','end_date'=>'01/05/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>8, 'code_name'=>'15082026','holiday_name'=>'Independence Day', 'start_date'=>'15/08/2026','end_date'=>'15/08/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>9, 'code_name'=>'02102026','holiday_name'=>'Gandhi Jayanti',   'start_date'=>'02/10/2026','end_date'=>'02/10/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>10,'code_name'=>'20102026','holiday_name'=>'Dussehra',         'start_date'=>'20/10/2026','end_date'=>'20/10/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>11,'code_name'=>'01112026','holiday_name'=>'Diwali',           'start_date'=>'01/11/2026','end_date'=>'01/11/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>12,'code_name'=>'25122026','holiday_name'=>'Christmas',        'start_date'=>'25/12/2026','end_date'=>'25/12/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
        ['id'=>13,'code_name'=>'31122026','holiday_name'=>'New Year Eve',     'start_date'=>'31/12/2026','end_date'=>'31/12/2026','type'=>'Holiday','is_halfday'=>0,'is_optional'=>0,'calendars'=>'India','remarks'=>''],
    ],
    2025 => [],
    2024 => [],
];

$holidays = $all_holidays[$year] ?? [];

/* default active */
if ($active_id === 0 && $mode === 'view' && count($holidays)) {
    $active_id = $holidays[0]['id'];
}
$active_hol = null;
foreach ($holidays as $h) { if ($h['id'] === $active_id) { $active_hol = $h; break; } }

$years     = [2028,2027,2026,2025,2024];
$calendars = ['India','Auto Shift','Auto Shift 1'];

/* convert dd/mm/yyyy to yyyy-mm-dd for date inputs */
function toInputDate(string $d): string {
    $p = explode('/', $d);
    return count($p) === 3 ? $p[2].'-'.$p[1].'-'.$p[0] : $d;
}
?>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<div class="section-card" style="padding:0;overflow:hidden">
<div class="hc-wrapper">

  <!-- Config nav tabs -->
  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll',
                    'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k==='Attendance'?'active':'' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <div class="hc-inner">

    <?php if ($flash): ?>
      <div class="flash-msg <?= htmlspecialchars($flash_type) ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <!-- Top bar -->
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
    <!-- ════════ ADD HOLIDAY ════════ -->
    <div class="hc-add-card">
      <div class="hc-add-title">Add Holiday</div>
      <form method="POST">
        <input type="hidden" name="action" value="add_holiday">
        <div class="hc-field-grid" style="margin-bottom:18px">
          <div class="hc-field">
            <label><span class="req">*</span> Code Name</label>
            <input type="text" name="code_name" class="hc-input"
                   placeholder="Code Name"
                   value="<?= htmlspecialchars($_POST['code_name'] ?? '') ?>" required>
          </div>
          <div class="hc-field">
            <label><span class="req">*</span> Holiday Name</label>
            <input type="text" name="holiday_name" class="hc-input"
                   placeholder="Holiday Name"
                   value="<?= htmlspecialchars($_POST['holiday_name'] ?? '') ?>" required>
          </div>
          <div class="hc-field">
            <label><span class="req">*</span> Start Date</label>
            <div class="hc-date-wrap">
              <input type="date" name="start_date"
                     value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')) ?>" required>
              <i class="fa-regular fa-calendar"></i>
            </div>
          </div>
          <div class="hc-field">
            <label>End Date</label>
            <div class="hc-date-wrap">
              <input type="date" name="end_date"
                     value="<?= htmlspecialchars($_POST['end_date'] ?? date('Y-m-d')) ?>">
              <i class="fa-regular fa-calendar"></i>
            </div>
          </div>
        </div>

        <!-- Toggles -->
        <div style="display:flex;gap:20px;margin-bottom:18px">
          <label class="hc-toggle-wrap">
            <span class="toggle-switch">
              <input type="checkbox" name="is_halfday" value="1"
                     <?= isset($_POST['is_halfday']) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </span>
            Half-day
          </label>
          <label class="hc-toggle-wrap">
            <span class="toggle-switch">
              <input type="checkbox" name="is_optional" value="1"
                     <?= isset($_POST['is_optional']) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </span>
            Optional
          </label>
        </div>

        <!-- Type -->
        <div class="hc-type-section">
          <div class="hc-type-label">Select Type</div>
          <div class="hc-radio-group">
            <label class="hc-radio-item">
              <input type="radio" name="type" value="Holiday"
                     <?= (($_POST['type'] ?? 'Holiday') === 'Holiday') ? 'checked' : '' ?>>
              Holiday
            </label>
            <label class="hc-radio-item">
              <input type="radio" name="type" value="Week-Off"
                     <?= (($_POST['type'] ?? '') === 'Week-Off') ? 'checked' : '' ?>>
              Week-Off
            </label>
          </div>
        </div>

        <!-- Applicable to Calendar -->
        <div class="hc-cal-section">
          <div class="hc-cal-label"><span class="req">*</span> Applicable to Calendar</div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php foreach ($calendars as $cal): ?>
            <label class="hc-cal-chip">
              <input type="checkbox" name="calendars[]" value="<?= htmlspecialchars($cal) ?>"
                     <?= ($cal === 'India' || isset($_POST['calendars']) && in_array($cal, $_POST['calendars'])) ? 'checked' : '' ?>>
              <?= htmlspecialchars($cal) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Remarks -->
        <div class="hc-field-grid" style="grid-template-columns:1fr;margin-bottom:14px">
          <div class="hc-field">
            <label>Remarks</label>
            <input type="text" name="remarks" class="hc-input"
                   placeholder="Remarks"
                   value="<?= htmlspecialchars($_POST['remarks'] ?? '') ?>">
          </div>
        </div>

        <div class="hc-form-actions">
          <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
          <button type="submit" class="btn-save">Add</button>
        </div>
      </form>
    </div>

    <?php else: ?>
    <!-- ════════ SPLIT PANEL ════════ -->

    <!-- Sub-header -->
    <div class="hc-sub-header">
      <div class="hc-sub-left">
        List of Holidays (<?= count($holidays) ?>)
        <!-- Year dropdown -->
        <form method="GET" style="display:inline">
          <input type="hidden" name="id"   value="<?= $active_id ?>">
          <input type="hidden" name="mode" value="view">
          <select name="year" class="hc-year-select" onchange="this.form.submit()">
            <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= ($y === $year) ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="hc-sub-right">Holiday Details</div>
    </div>

    <div class="hc-panel">
      <!-- Left list -->
      <div class="hc-list-col">
        <div class="hc-list-scroll">
          <?php foreach ($holidays as $i => $hol): ?>
          <div class="hc-item <?= ($hol['id'] === $active_id && $mode !== 'edit') ? 'active' : '' ?>"
               onclick="selectHol(<?= $hol['id'] ?>)">
            <span class="hc-item-name"><?= htmlspecialchars($hol['holiday_name']) ?></span>
            <span class="hc-item-num"><?= $i + 1 ?></span>
            <span class="hc-item-date"><?= htmlspecialchars($hol['start_date']) ?></span>
            <i class="fa-solid <?= ($hol['id'] === $active_id && $mode !== 'edit') ? 'fa-chevron-right' : 'fa-chevron-down' ?> hc-item-chevron"></i>
          </div>
          <?php endforeach; ?>
          <?php if (empty($holidays)): ?>
          <div style="padding:24px 16px;color:#9ca3af;font-size:13px">No holidays for <?= $year ?>.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right detail/edit -->
      <div class="hc-detail-col">

        <?php if ($mode === 'edit' && $active_hol): ?>
        <!-- EDIT FORM -->
        <div class="hc-detail-title" style="margin-bottom:18px">
          EDIT — <?= htmlspecialchars($active_hol['holiday_name']) ?>
        </div>
        <form method="POST">
          <input type="hidden" name="action"     value="save_holiday">
          <input type="hidden" name="holiday_id" value="<?= $active_hol['id'] ?>">

          <!-- Toggles -->
          <div style="display:flex;gap:20px;margin-bottom:16px">
            <label class="hc-toggle-wrap">
              <span class="toggle-switch">
                <input type="checkbox" name="is_halfday" value="1"
                       <?= $active_hol['is_halfday'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
              </span>
              Half-day
            </label>
            <label class="hc-toggle-wrap">
              <span class="toggle-switch">
                <input type="checkbox" name="is_optional" value="1"
                       <?= $active_hol['is_optional'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
              </span>
              Optional
            </label>
          </div>

          <div class="hc-field-grid" style="margin-bottom:16px">
            <div class="hc-field">
              <label><span class="req">*</span> Code Name</label>
              <input type="text" name="code_name" class="hc-input"
                     value="<?= htmlspecialchars($active_hol['code_name']) ?>" required>
            </div>
            <div class="hc-field">
              <label><span class="req">*</span> Holiday Name</label>
              <input type="text" name="holiday_name" class="hc-input"
                     value="<?= htmlspecialchars($active_hol['holiday_name']) ?>" required>
            </div>
            <div class="hc-field">
              <label>Start Date</label>
              <div class="hc-date-wrap">
                <input type="date" name="start_date"
                       value="<?= toInputDate($active_hol['start_date']) ?>">
                <i class="fa-regular fa-calendar"></i>
              </div>
            </div>
            <div class="hc-field">
              <label>End Date</label>
              <div class="hc-date-wrap">
                <input type="date" name="end_date"
                       value="<?= toInputDate($active_hol['end_date']) ?>">
                <i class="fa-regular fa-calendar"></i>
              </div>
            </div>
          </div>

          <div class="hc-type-section">
            <div class="hc-type-label">Select Type</div>
            <div class="hc-radio-group">
              <label class="hc-radio-item">
                <input type="radio" name="type" value="Holiday"
                       <?= ($active_hol['type'] === 'Holiday') ? 'checked' : '' ?>>
                Holiday
              </label>
              <label class="hc-radio-item">
                <input type="radio" name="type" value="Week-Off"
                       <?= ($active_hol['type'] === 'Week-Off') ? 'checked' : '' ?>>
                Week-Off
              </label>
            </div>
          </div>

          <div class="hc-cal-section">
            <div class="hc-cal-label"><span class="req">*</span> Applicable to Calendar</div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <?php
              $selCals = explode(',', $active_hol['calendars']);
              foreach ($calendars as $cal): ?>
              <label class="hc-cal-chip">
                <input type="checkbox" name="calendars[]" value="<?= htmlspecialchars($cal) ?>"
                       <?= in_array($cal, $selCals) ? 'checked' : '' ?>>
                <?= htmlspecialchars($cal) ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="hc-field-grid" style="grid-template-columns:1fr;margin-bottom:6px">
            <div class="hc-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="hc-input"
                     value="<?= htmlspecialchars($active_hol['remarks']) ?>">
            </div>
          </div>

          <div class="hc-form-actions">
            <button type="button" class="btn-delete"
                    onclick="deleteHol(<?= $active_hol['id'] ?>)">Delete</button>
            <button type="button" class="btn-cancel"
                    onclick="window.location.href='?id=<?= $active_hol['id'] ?>&year=<?= $year ?>&mode=view'">
              Cancel
            </button>
            <button type="submit" class="btn-save">Save</button>
          </div>
        </form>

        <?php elseif ($active_hol): ?>
        <!-- VIEW DETAIL -->
        <div class="hc-detail-title-row">
          <div class="hc-detail-title"><?= htmlspecialchars($active_hol['holiday_name']) ?></div>
          <div style="display:flex;align-items:center;gap:16px">
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
            <button class="btn-edit-link"
                    onclick="window.location.href='?id=<?= $active_hol['id'] ?>&year=<?= $year ?>&mode=edit'">
              <i class="fa-regular fa-pen-to-square"></i> Edit Details
            </button>
          </div>
        </div>

        <div class="hc-field-grid" style="margin-bottom:16px">
          <div class="hc-field">
            <label>Code Name</label>
            <div class="hc-field-value"><?= htmlspecialchars($active_hol['code_name']) ?></div>
          </div>
          <div class="hc-field">
            <label>Holiday Name</label>
            <div class="hc-field-value"><?= htmlspecialchars($active_hol['holiday_name']) ?></div>
          </div>
          <div class="hc-field">
            <label>Start Date</label>
            <div class="hc-field-value" style="display:flex;align-items:center;gap:8px">
              <?= htmlspecialchars($active_hol['start_date']) ?>
              <i class="fa-regular fa-calendar" style="color:#9ca3af;font-size:13px"></i>
            </div>
          </div>
          <div class="hc-field">
            <label>End Date</label>
            <div class="hc-field-value" style="display:flex;align-items:center;gap:8px">
              <?= htmlspecialchars($active_hol['end_date']) ?>
              <i class="fa-regular fa-calendar" style="color:#9ca3af;font-size:13px"></i>
            </div>
          </div>
        </div>

        <!-- Type -->
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

        <!-- Calendars -->
        <div class="hc-cal-section">
          <div class="hc-cal-label"><span class="req">*</span> Applicable to Calendar</div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php
            $selCals = explode(',', $active_hol['calendars']);
            foreach ($selCals as $cal): if (!trim($cal)) continue; ?>
            <span class="hc-cal-chip">
              <input type="checkbox" checked disabled>
              <?= htmlspecialchars(trim($cal)) ?>
            </span>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Remarks -->
        <div class="hc-remarks-section">
          <label class="hc-remarks-label">Remarks</label>
          <div class="hc-remarks-value"><?= htmlspecialchars($active_hol['remarks']) ?>&nbsp;</div>
        </div>

        <!-- Bottom actions (Delete / Cancel / Save visible on edit; here we show nothing on view except the bottom bar) -->
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:auto;padding-top:18px;border-top:1px solid #e8ecf0">
          <button class="btn-delete" onclick="deleteHol(<?= $active_hol['id'] ?>)">Delete</button>
          <button class="btn-cancel" onclick="setMode('view')">Cancel</button>
          <button class="btn-save"
                  onclick="window.location.href='?id=<?= $active_hol['id'] ?>&year=<?= $year ?>&mode=edit'">
            Save
          </button>
        </div>

        <?php else: ?>
        <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13.5px">
          Select a holiday to view details.
        </div>
        <?php endif; ?>

      </div><!-- /hc-detail-col -->
    </div><!-- /hc-panel -->
    <?php endif; ?>

  </div><!-- /hc-inner -->
</div><!-- /hc-wrapper -->
</div><!-- /section-card -->

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
  if (id !== undefined) url.searchParams.set('id', id);
  window.location.href = url.toString();
}
function deleteHol(id) {
  if (!confirm('Delete this holiday?')) return;
  const f = document.createElement('form');
  f.method = 'POST';
  f.innerHTML = `<input name="action" value="delete_holiday">
                 <input name="holiday_id" value="${id}">`;
  document.body.appendChild(f);
  f.submit();
}

/* Toast */
const toastIcons = {success:'fa-circle-check',error:'fa-circle-xmark',warning:'fa-triangle-exclamation',info:'fa-circle-info'};
function showToast(msg, type='success', dur=3500) {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fa-solid ${toastIcons[type]||toastIcons.info}"></i>
    <span>${msg}</span>
    <button class="toast-close" onclick="rmToast(this.parentElement)"><i class="fa-solid fa-xmark"></i></button>`;
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