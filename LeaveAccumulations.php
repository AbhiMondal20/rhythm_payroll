<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Leave Accumulation';

/*
RUN ONCE:

CREATE TABLE `leave_accumulations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL DEFAULT 0,
  `emp_name` varchar(150) NOT NULL,
  `leave_type_id` int(11) NOT NULL DEFAULT 0,
  `leave_name` varchar(150) NOT NULL,
  `accumulated` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `accumulation_date` date NOT NULL,
  `accum_from` date DEFAULT NULL,
  `accum_to` date DEFAULT NULL,
  `avail_from` date DEFAULT NULL,
  `avail_to` date DEFAULT NULL,
  `is_opening_balance` tinyint(1) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function dbDate($date) {
    if (!$date) return null;
    $t = strtotime($date);
    return $t ? date('Y-m-d', $t) : null;
}

function showDate($date) {
    if (!$date || $date === '0000-00-00') return '';
    return date('d M Y', strtotime($date));
}

function parseDateRange($range) {
    $parts = preg_split('/\s+TO\s+/i', trim($range));
    if (count($parts) === 2) {
        return [dbDate($parts[0]), dbDate($parts[1])];
    }
    return [date('Y-01-01'), date('Y-12-31')];
}

if (!isset($_SESSION['la_flash'])) {
    $_SESSION['la_flash'] = '';
    $_SESSION['la_flash_type'] = 'success';
}

$mode       = $_GET['mode'] ?? 'list';
$detail_id  = (int)($_GET['id'] ?? 0);
$page       = max(1, (int)($_GET['p'] ?? 1));
$per_page   = max(5, (int)($_GET['pp'] ?? 10));
$date_range = $_GET['date_range'] ?? (date('01 Jan Y') . ' TO ' . date('31 Dec Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_accumulation') {
        $emp_name   = trim($_POST['emp_name'] ?? '');
        $leave_name = trim($_POST['leave_type_id'] ?? '');
        $accum_date = dbDate($_POST['accum_date'] ?? '');
        $leaves     = (float)($_POST['no_of_leaves'] ?? 0);
        $accum_from = dbDate($_POST['accum_from'] ?? '');
        $accum_to   = dbDate($_POST['accum_to'] ?? '');
        $avail_from = dbDate($_POST['avail_from'] ?? '');
        $avail_to   = dbDate($_POST['avail_to'] ?? '');
        $opening    = isset($_POST['is_opening_balance']) ? 1 : 0;
        $note       = trim($_POST['note'] ?? '');

        if ($emp_name === '' || $leave_name === '' || !$accum_date) {
            $_SESSION['la_flash'] = 'Employee Name, Leave Name and Accumulation Date are required.';
            $_SESSION['la_flash_type'] = 'error';
            header("Location: ?mode=add");
            exit;
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO leave_accumulations
            (emp_id, emp_name, leave_type_id, leave_name, accumulated, balance, accumulation_date,
             accum_from, accum_to, avail_from, avail_to, is_opening_balance, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $emp_id = 0;
        $leave_type_id = 0;
        $balance = $leaves;

        mysqli_stmt_bind_param(
            $stmt,
            "isisddsssssiss",
            $emp_id,
            $emp_name,
            $leave_type_id,
            $leave_name,
            $leaves,
            $balance,
            $accum_date,
            $accum_from,
            $accum_to,
            $avail_from,
            $avail_to,
            $opening,
            $note
        );

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['la_flash'] = 'Accumulation saved successfully.';
            $_SESSION['la_flash_type'] = 'success';
            header("Location: ?mode=list");
            exit;
        } else {
            $_SESSION['la_flash'] = 'Database error: ' . mysqli_error($conn);
            $_SESSION['la_flash_type'] = 'error';
            header("Location: ?mode=add");
            exit;
        }
    }
}

$flash = $_SESSION['la_flash'] ?? '';
$flash_type = $_SESSION['la_flash_type'] ?? 'success';
$_SESSION['la_flash'] = '';
$_SESSION['la_flash_type'] = 'success';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ── Config nav tabs ── */
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}

/* ── Page ── */
.la-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.la-inner{padding:20px 26px}

/* breadcrumb */
.la-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.la-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555}
.la-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.la-breadcrumb a:hover{text-decoration:underline}
.la-breadcrumb .sep{color:#bbb;font-size:11px}
.btn-add-accum{display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .16s}
.btn-add-accum:hover{background:#1d4ed8}

/* ── Filter bar ── */
.la-filter-bar{display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.la-search-wrap{position:relative;width:280px}
.la-search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:12px}
.la-search-input{width:100%;padding:8px 10px 8px 32px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;color:#1e2d3d;outline:none;box-sizing:border-box;background:#f9fafb;transition:border-color .15s}
.la-search-input:focus{border-color:#2563eb;background:#fff}

.la-date-range-wrap{position:relative}
.la-date-range-wrap i.cal-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px;pointer-events:none}
.la-date-range-select{padding:8px 22px 8px 32px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;color:#374151;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 6px center;outline:none;appearance:none;cursor:pointer;transition:border-color .15s}
.la-date-range-select:focus{border-color:#2563eb}

.btn-get-details{padding:8px 20px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s}
.btn-get-details:hover{background:#1d4ed8}

.la-table-search-wrap{position:relative;width:280px;margin-bottom:14px}
.la-table-search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:12px}
.la-table-search-input{width:100%;padding:8px 10px 8px 32px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;color:#1e2d3d;outline:none;box-sizing:border-box;background:#f9fafb;transition:border-color .15s}
.la-table-search-input:focus{border-color:#2563eb;background:#fff}

/* ── Table ── */
.la-table-wrap{border:1px solid #e8ecf0;border-radius:10px;overflow:hidden;margin-bottom:18px}
table.la-table{width:100%;border-collapse:collapse}
table.la-table thead th{background:#f8fafc;padding:12px 16px;text-align:left;font-size:12.5px;font-weight:700;color:#374151;border-bottom:1px solid #e8ecf0}
table.la-table tbody tr{border-bottom:1px solid #f1f4f8;transition:background .12s}
table.la-table tbody tr:last-child{border-bottom:none}
table.la-table tbody tr:hover{background:#f9fafb}
table.la-table tbody td{padding:13px 16px;font-size:13.5px;color:#374151}
.la-link-icon{background:none;border:none;cursor:pointer;color:#6b7280;font-size:14px;padding:2px 6px;border-radius:4px;transition:color .14s}
.la-link-icon:hover{color:#2563eb}

/* ── Pagination ── */
.la-pagination{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.la-showing{font-size:12.5px;color:#6b7280}
.la-per-page{display:flex;align-items:center;gap:8px;font-size:12.5px;color:#374151}
.la-per-page select{padding:4px 20px 4px 8px;border:1px solid #e2e8f0;border-radius:5px;font-size:12.5px;color:#374151;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 4px center;outline:none;appearance:none;cursor:pointer}
.la-pages{display:flex;align-items:center;gap:4px}
.la-page-btn{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;border-radius:5px;font-size:13px;color:#374151;cursor:pointer;background:#fff;transition:background .14s,color .14s}
.la-page-btn:hover{background:#f1f5f9}
.la-page-btn.active{background:#2563eb;color:#fff;border-color:#2563eb}
.la-page-btn.nav{font-size:11px;color:#6b7280}

/* ── Detail view ── */
.la-detail-card{background:#fff;border:1px solid #e8ecf0;border-radius:10px;padding:28px 30px}
.la-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px 40px;margin-bottom:22px}
.la-field-grid.quad{grid-template-columns:1fr 1fr 1fr 1fr}
.la-field label{display:block;font-size:12px;color:#6b7280;margin-bottom:6px;font-weight:500}
.la-field label .req{color:#ef4444;margin-right:2px}
.la-field-value{font-size:14px;color:#1e2d3d;padding-bottom:8px;border-bottom:1px solid #e2e8f0;min-height:26px;font-weight:500}

.la-section-head{font-size:13.5px;font-weight:700;color:#1e2d3d;margin-bottom:14px;display:flex;align-items:center;gap:7px}
.info-icon{color:#2563eb;font-size:13px;cursor:pointer}

/* ── Add/Edit form ── */
.la-input{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s}
.la-input::placeholder{color:#c4c9d4}
.la-input:focus{border-color:#2563eb}
.la-select{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 20px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 3px center;outline:none;box-sizing:border-box;appearance:none;cursor:pointer;transition:border-color .16s}
.la-select:focus{border-color:#2563eb}
.la-date-wrap{position:relative}
.la-date-wrap input[type=date]{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 28px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s;cursor:pointer}
.la-date-wrap input[type=date]:focus{border-color:#2563eb}
.la-date-wrap i{position:absolute;right:4px;top:50%;transform:translateY(-50%);color:#2563eb;font-size:14px;pointer-events:none}

.la-checkbox-wrap{display:flex;align-items:center;gap:9px;font-size:13.5px;color:#374151;padding-top:18px}
.la-checkbox-wrap input[type=checkbox]{width:16px;height:16px;accent-color:#2563eb;cursor:pointer}

.la-form-actions{display:flex;justify-content:flex-end;gap:12px;padding-top:22px;border-top:1px solid #e8ecf0;margin-top:14px}
.btn-la-back,.btn-la-cancel{padding:9px 24px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:13.5px;color:#374151;cursor:pointer;font-weight:600;transition:background .14s;text-decoration:none;display:inline-flex;align-items:center}
.btn-la-back:hover,.btn-la-cancel:hover{background:#f1f5f9}
.btn-la-save{padding:9px 24px;background:#2563eb;border:none;border-radius:6px;font-size:13.5px;color:#fff;cursor:pointer;font-weight:600;transition:background .14s}
.btn-la-save:hover{background:#1d4ed8}

/* toast */
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
[$from_date, $to_date] = parseDateRange($date_range);
$offset = ($page - 1) * $per_page;

$where = "WHERE accumulation_date BETWEEN ? AND ?";
$params = [$from_date, $to_date];

$total_entries = 0;
$stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM leave_accumulations $where");
mysqli_stmt_bind_param($stmtCount, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmtCount);
$countRes = mysqli_stmt_get_result($stmtCount);
if ($countRow = mysqli_fetch_assoc($countRes)) {
    $total_entries = (int)$countRow['total'];
}

$total_pages = max(1, (int)ceil($total_entries / $per_page));

$rows = [];
$stmtRows = mysqli_prepare($conn, "
    SELECT *
    FROM leave_accumulations
    $where
    ORDER BY accumulation_date DESC, id DESC
    LIMIT ? OFFSET ?
");
mysqli_stmt_bind_param($stmtRows, "ssii", $from_date, $to_date, $per_page, $offset);
mysqli_stmt_execute($stmtRows);
$resRows = mysqli_stmt_get_result($stmtRows);
while ($r = mysqli_fetch_assoc($resRows)) {
    $rows[] = $r;
}

$active_row = null;
if ($mode === 'detail' && $detail_id > 0) {
    $stmtDetail = mysqli_prepare($conn, "SELECT * FROM leave_accumulations WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtDetail, "i", $detail_id);
    mysqli_stmt_execute($stmtDetail);
    $detailRes = mysqli_stmt_get_result($stmtDetail);
    $active_row = mysqli_fetch_assoc($detailRes);
}

$leave_types = [
    'Compensatory Leave',
    'Casual Leave/Sick Leave',
    'Loss of Pay',
    'Maternity Leave',
    'Paternity Leave'
];

$date_ranges = [
    date('01 Jan Y') . ' TO ' . date('31 Dec Y'),
    date('01 Jan Y', strtotime('-1 year')) . ' TO ' . date('31 Dec Y', strtotime('-1 year')),
    '01 Apr ' . date('Y') . ' TO 31 Mar ' . date('Y', strtotime('+1 year')),
    '01 Jan ' . date('Y') . ' TO 31 Mar ' . date('Y'),
];

$today = date('Y-m-d');
?>

<div class="toast-container" id="toastContainer"></div>

<div class="cfg-page-head"><h1 class="page-title">Configuration</h1></div>

<div class="section-card" style="padding:0;overflow:hidden">

  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll',
                    'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= h($k) ?>" class="cfg-tab <?= $k==='Leave'?'active':'' ?>"><?= h($l) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="la-wrapper">
    <div class="la-inner">

      <div class="la-topbar">
        <nav class="la-breadcrumb">
          <a href="leave_config.php">Leave</a>
          <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
          <?php if ($mode !== 'list'): ?>
          <a href="?mode=list">Leave Accumulation</a>
          <?php else: ?>
          <span>Leave Accumulation</span>
          <?php endif; ?>
        </nav>

        <?php if ($mode === 'list'): ?>
        <button class="btn-add-accum" onclick="window.location.href='?mode=add'">
          <i class="fa-solid fa-plus"></i> Add New Accumulation
        </button>
        <?php endif; ?>
      </div>

      <?php if ($mode === 'detail' && $active_row): ?>

      <div class="la-detail-card">
        <div class="la-field-grid" style="margin-bottom:22px">
          <div class="la-field">
            <label>Employee Name</label>
            <div class="la-field-value"><?= h($active_row['emp_name']) ?></div>
          </div>
          <div class="la-field">
            <label>Leave Name</label>
            <div class="la-field-value"><?= h($active_row['leave_name']) ?></div>
          </div>
        </div>

        <div class="la-field-grid" style="margin-bottom:22px">
          <div class="la-field">
            <label>Accumulation Date <i class="fa-solid fa-circle-info info-icon"></i></label>
            <div class="la-field-value"><?= h(showDate($active_row['accumulation_date'])) ?></div>
          </div>
          <div style="display:flex;gap:24px;align-items:flex-end">
            <div class="la-field" style="flex:1">
              <label>No. Of Leaves</label>
              <div class="la-field-value"><?= h($active_row['accumulated']) ?></div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;padding-bottom:9px;white-space:nowrap">
              <input type="checkbox" disabled <?= ((int)$active_row['is_opening_balance'] === 1) ? 'checked' : '' ?>
                     style="width:15px;height:15px;accent-color:#2563eb">
              Mark as Opening Balance.
              <i class="fa-solid fa-circle-info info-icon"></i>
            </label>
          </div>
        </div>

        <div class="la-section-head">
          Accumulation Period
          <i class="fa-solid fa-circle-info info-icon"></i>
        </div>
        <div class="la-field-grid quad" style="margin-bottom:22px">
          <div class="la-field">
            <label>From</label>
            <div class="la-field-value"><?= h(showDate($active_row['accum_from'])) ?></div>
          </div>
          <div class="la-field">
            <label>To</label>
            <div class="la-field-value"><?= h(showDate($active_row['accum_to'])) ?></div>
          </div>
          <div class="la-field"></div>
          <div class="la-field"></div>
        </div>

        <div class="la-section-head">
          Availability Period
          <i class="fa-solid fa-circle-info info-icon"></i>
        </div>
        <div class="la-field-grid quad" style="margin-bottom:22px">
          <div class="la-field">
            <label>From</label>
            <div class="la-field-value"><?= h(showDate($active_row['avail_from'])) ?></div>
          </div>
          <div class="la-field">
            <label>To</label>
            <div class="la-field-value"><?= h(showDate($active_row['avail_to'])) ?></div>
          </div>
          <div class="la-field"></div>
          <div class="la-field"></div>
        </div>

        <div class="la-field" style="margin-bottom:16px">
          <label>Note</label>
          <div class="la-field-value"><?= h($active_row['note']) ?>&nbsp;</div>
        </div>

        <div class="la-form-actions">
          <a href="?mode=list" class="btn-la-back">Back</a>
        </div>
      </div>

      <?php elseif ($mode === 'add'): ?>

      <div class="la-detail-card">
        <form method="POST">
          <input type="hidden" name="action" value="add_accumulation">

          <div class="la-field-grid" style="margin-bottom:22px">
            <div class="la-field">
              <label><span class="req">*</span> Employee Name</label>
              <div style="position:relative">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:2px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:12px"></i>
                <input type="text" name="emp_name" class="la-input" style="padding-left:20px"
                       placeholder="Search by name or #code" required>
              </div>
            </div>

            <div class="la-field">
              <label>Leave Name</label>
              <select name="leave_type_id" class="la-select" required>
                <option value=""></option>
                <?php foreach ($leave_types as $lt): ?>
                <option value="<?= h($lt) ?>"><?= h($lt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="la-field-grid" style="margin-bottom:22px">
            <div class="la-field">
              <label>Accumulation Date <i class="fa-solid fa-circle-info info-icon"></i></label>
              <div class="la-date-wrap">
                <input type="date" name="accum_date" value="<?= h($today) ?>" required>
                <i class="fa-regular fa-calendar"></i>
              </div>
            </div>

            <div style="display:flex;gap:24px;align-items:flex-end">
              <div class="la-field" style="flex:1">
                <label>No. Of Leaves</label>
                <input type="number" name="no_of_leaves" class="la-input" step="0.5" min="0" required>
              </div>
              <label class="la-checkbox-wrap" style="white-space:nowrap">
                <input type="checkbox" name="is_opening_balance" value="1">
                Mark as Opening Balance.
                <i class="fa-solid fa-circle-info info-icon"></i>
              </label>
            </div>
          </div>

          <div class="la-section-head">
            Accumulation Period
            <i class="fa-solid fa-circle-info info-icon"></i>
          </div>

          <div class="la-field-grid quad" style="margin-bottom:22px">
            <div class="la-field">
              <label>From</label>
              <div class="la-date-wrap">
                <input type="date" name="accum_from" value="<?= h(date('Y-m-01')) ?>">
                <i class="fa-regular fa-calendar"></i>
              </div>
            </div>
            <div class="la-field">
              <label>To</label>
              <div class="la-date-wrap">
                <input type="date" name="accum_to" value="<?= h($today) ?>">
                <i class="fa-regular fa-calendar"></i>
              </div>
            </div>
            <div class="la-field"></div>
            <div class="la-field"></div>
          </div>

          <div class="la-section-head">
            Availability Period
            <i class="fa-solid fa-circle-info info-icon"></i>
          </div>

          <div class="la-field-grid quad" style="margin-bottom:22px">
            <div class="la-field">
              <label>From</label>
              <div class="la-date-wrap">
                <input type="date" name="avail_from" value="<?= h(date('Y-m-01')) ?>">
                <i class="fa-regular fa-calendar"></i>
              </div>
            </div>
            <div class="la-field">
              <label>To</label>
              <div class="la-date-wrap">
                <input type="date" name="avail_to" value="<?= h($today) ?>">
                <i class="fa-regular fa-calendar"></i>
              </div>
            </div>
            <div class="la-field"></div>
            <div class="la-field"></div>
          </div>

          <div class="la-field" style="margin-bottom:10px">
            <label>Note</label>
            <input type="text" name="note" class="la-input">
          </div>

          <div class="la-form-actions">
            <button type="button" class="btn-la-cancel" onclick="window.location.href='?mode=list'">Cancel</button>
            <button type="submit" class="btn-la-save">Save</button>
          </div>
        </form>
      </div>

      <?php else: ?>

      <div class="la-filter-bar">
        <div class="la-search-wrap">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="empSearchInput" class="la-search-input"
                 placeholder="Search by name or #code"
                 oninput="filterTable(this.value)">
        </div>

        <div class="la-date-range-wrap">
          <i class="fa-regular fa-calendar cal-icon"></i>
          <select class="la-date-range-select" id="dateRangeSelect">
            <?php foreach ($date_ranges as $dr): ?>
            <option value="<?= h($dr) ?>" <?= ($dr === $date_range) ? 'selected' : '' ?>>
              <?= h($dr) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="btn-get-details" onclick="applyDateRange()">
          Get Details
        </button>
      </div>

      <div class="la-table-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="tableSearchInput" class="la-table-search-input"
               placeholder="Search table items"
               oninput="filterTable(this.value)">
      </div>

      <div class="la-table-wrap">
        <table class="la-table" id="laTable">
          <thead>
            <tr>
              <th>S No.</th>
              <th>Employee Name</th>
              <th>Leave Name</th>
              <th>Accumulated</th>
              <th>Balance</th>
              <th>Accumulation Date</th>
              <th>Availability Period</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody id="laTableBody">
            <?php if (!empty($rows)): ?>
              <?php foreach ($rows as $i => $row): ?>
              <tr>
                <td><?= h($offset + $i + 1) ?></td>
                <td><?= h($row['emp_name']) ?></td>
                <td><?= h($row['leave_name']) ?></td>
                <td><?= h($row['accumulated']) ?></td>
                <td><?= h($row['balance']) ?></td>
                <td><?= h(showDate($row['accumulation_date'])) ?></td>
                <td><?= h(showDate($row['avail_from'])) ?> To <?= h(showDate($row['avail_to'])) ?></td>
                <td>
                  <button class="la-link-icon" title="View Details"
                          onclick="window.location.href='?mode=detail&id=<?= (int)$row['id'] ?>'">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" style="text-align:center;color:#6b7280;padding:22px">
                  No records found.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="la-pagination">
        <div style="display:flex;align-items:center;gap:18px">
          <span class="la-showing">
            Showing <?= $total_entries ? h($offset + 1) : 0 ?> to <?= h(min($offset + $per_page, $total_entries)) ?> of <?= h($total_entries) ?> entries
          </span>

          <div class="la-per-page">
            Show
            <select onchange="changePerPage(this.value)">
              <?php foreach ([5,10,25,50] as $pp): ?>
              <option value="<?= $pp ?>" <?= ($pp === $per_page) ? 'selected' : '' ?>><?= $pp ?></option>
              <?php endforeach; ?>
            </select>
            entries
          </div>
        </div>

        <div class="la-pages">
          <button class="la-page-btn nav" onclick="goPage(1)" title="First">«</button>
          <button class="la-page-btn nav" onclick="goPage(<?= max(1,$page-1) ?>)" title="Prev">‹</button>

          <?php
          $start = max(1, $page - 1);
          $end   = min($total_pages, $start + 2);
          for ($pg = $start; $pg <= $end; $pg++):
          ?>
          <button class="la-page-btn <?= $pg === $page ? 'active' : '' ?>"
                  onclick="goPage(<?= $pg ?>)"><?= $pg ?></button>
          <?php endfor; ?>

          <?php if ($end < $total_pages): ?>
          <span style="color:#9ca3af;padding:0 4px">…</span>
          <button class="la-page-btn" onclick="goPage(<?= $total_pages ?>)"><?= $total_pages ?></button>
          <?php endif; ?>

          <button class="la-page-btn nav" onclick="goPage(<?= min($total_pages,$page+1) ?>)" title="Next">›</button>
        </div>
      </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php if ($flash): ?>
<script>
window.addEventListener('DOMContentLoaded', function () {
  showToast(<?= json_encode($flash) ?>, <?= json_encode($flash_type) ?>);
});
</script>
<?php endif; ?>

<script>
function goPage(p){
  const u = new URL(window.location.href);
  u.searchParams.set('p', p);
  window.location.href = u.toString();
}

function changePerPage(pp){
  const u = new URL(window.location.href);
  u.searchParams.set('pp', pp);
  u.searchParams.set('p', 1);
  window.location.href = u.toString();
}

function applyDateRange(){
  const val = document.getElementById('dateRangeSelect').value;
  const u = new URL(window.location.href);
  u.searchParams.set('date_range', val);
  u.searchParams.set('p', 1);
  u.searchParams.set('mode', 'list');
  window.location.href = u.toString();
}

function filterTable(q){
  q = q.trim().toLowerCase();
  document.querySelectorAll('#laTableBody tr').forEach(function(row){
    row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
  });
}

const _ti = {
  success:'fa-circle-check',
  error:'fa-circle-xmark',
  warning:'fa-triangle-exclamation',
  info:'fa-circle-info'
};

function showToast(msg, type='success', dur=3500){
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fa-solid ${_ti[type] || _ti.info}"></i><span>${msg}</span>
    <button class="toast-close" onclick="rmToast(this.parentElement)">
      <i class="fa-solid fa-xmark"></i>
    </button>`;
  c.appendChild(t);
  setTimeout(() => rmToast(t), dur);
}

function rmToast(el){
  if(!el || !el.parentElement) return;
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