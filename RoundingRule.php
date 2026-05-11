<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Rounding Rules';
ob_start();

function esc($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$mode = $_GET['mode'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);

$toast = $_SESSION['toast'] ?? '';
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast'], $_SESSION['toast_type']);

$rounding_types  = ['Nearest','Ceiling','Floor'];
$applicable_opts = ['Salary heads','All components','Deductions','Allowances'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_rule' || $action === 'edit_rule') {
        $id = (int)($_POST['edit_id'] ?? 0);
        $rule_code = trim($_POST['rule_code'] ?? '');
        $rule_name = trim($_POST['rule_name'] ?? '');
        $rounding_type = $_POST['rounding_type'] ?? 'Nearest';
        $rounding_value = trim($_POST['rounding_value'] ?? '1');
        $note = trim($_POST['note'] ?? '');
        $applicable = trim($_POST['applicable'] ?? 'Salary heads');

        if ($rule_code === '' || $rule_name === '') {
            $_SESSION['toast'] = 'Rule Code and Rule Name are required.';
            $_SESSION['toast_type'] = 'error';
            header("Location: ?mode=" . ($action === 'edit_rule' ? "edit&id=".$id : "add"));
            exit;
        }

        if (!in_array($rounding_type, $rounding_types, true)) {
            $rounding_type = 'Nearest';
        }

        if ($action === 'add_rule') {
            $stmt = $conn->prepare("
                INSERT INTO payroll_rounding_rules
                (rule_code, rule_name, rounding_type, rounding_value, note, applicable_to)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssiss", $rule_code, $rule_name, $rounding_type, $rounding_value, $note, $applicable);

            if ($stmt->execute()) {
                $_SESSION['toast'] = 'Rounding rule added successfully.';
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast'] = 'Save failed: ' . $stmt->error;
                $_SESSION['toast_type'] = 'error';
            }
            $stmt->close();

            header("Location: ?mode=list");
            exit;
        }

        if ($action === 'edit_rule') {
            $stmt = $conn->prepare("
                UPDATE payroll_rounding_rules
                SET rule_code=?, rule_name=?, rounding_type=?, rounding_value=?, note=?, applicable_to=?
                WHERE id=?
            ");
            $stmt->bind_param("sssissi", $rule_code, $rule_name, $rounding_type, $rounding_value, $note, $applicable, $id);

            if ($stmt->execute()) {
                $_SESSION['toast'] = 'Rounding rule updated successfully.';
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast'] = 'Update failed: ' . $stmt->error;
                $_SESSION['toast_type'] = 'error';
            }
            $stmt->close();

            header("Location: ?mode=list");
            exit;
        }
    }

    if ($action === 'delete_rule') {
        $id = (int)($_POST['del_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM payroll_rounding_rules WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['toast'] = 'Rounding rule deleted successfully.';
            $_SESSION['toast_type'] = 'success';
        } else {
            $_SESSION['toast'] = 'Delete failed: ' . $stmt->error;
            $_SESSION['toast_type'] = 'error';
        }
        $stmt->close();

        header("Location: ?mode=list");
        exit;
    }
}

$rules = [];
$res = $conn->query("SELECT * FROM payroll_rounding_rules ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rules[] = $row;
    }
}

$edit_rule = [
    'rule_code' => '',
    'rule_name' => '',
    'rounding_type' => 'Nearest',
    'rounding_value' => '',
    'note' => '',
    'applicable_to' => 'Salary heads'
];

if ($mode === 'edit' && $edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM payroll_rounding_rules WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $edit_rule = $result->fetch_assoc();
    } else {
        $_SESSION['toast'] = 'Rule not found.';
        $_SESSION['toast_type'] = 'error';
        header("Location: ?mode=list");
        exit;
    }
    $stmt->close();
}

$badge_class = [
    'Nearest' => 'nearest',
    'Ceiling' => 'ceiling',
    'Floor' => 'floor'
];
?>

<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

/* ── Shared shell ── */
.rr-wrapper {
  font-family: 'Segoe UI', sans-serif;
  color: #1e2d3d;
  padding: 0 0 40px;
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

/* ── Inner page ── */
.rr-inner { padding: 24px 32px; }

/* ── List view top bar ── */
.rr-list-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  /* margin-bottom: 18px; */
}

.rr-page-title {
  font-size: 14px;
  font-weight: 800;
  color: #1e2d3d;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.btn-add-rule {
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
.btn-add-rule:hover { background: #1d4ed8; }

/* ── Empty state ── */
.rr-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
}
.rr-empty-icon {
  width: 100px;
  height: 100px;
  background: #eff6ff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 18px;
  position: relative;
}
.rr-empty-icon svg { width: 56px; height: 56px; }
.rr-empty-text {
  font-size: 13.5px;
  color: #9ca3af;
}

/* ── Rules table ── */
.rr-table-wrap {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 8px;
  overflow: hidden;
}
table.rr-table {
  width: 100%;
  border-collapse: collapse;
}
table.rr-table thead th {
  background: #f8fafc;
  padding: 11px 18px;
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .4px;
  border-bottom: 1px solid #e8ecf0;
}
table.rr-table tbody tr {
  border-bottom: 1px solid #f1f4f8;
  transition: background .12s;
}
table.rr-table tbody tr:last-child { border-bottom: none; }
table.rr-table tbody tr:hover { background: #f9fafb; }
table.rr-table tbody td {
  padding: 12px 18px;
  font-size: 13.5px;
  color: #374151;
}
.btn-tbl-edit {
  background: none;
  border: none;
  color: #2563eb;
  font-size: 14px;
  cursor: pointer;
  padding: 3px 8px;
  border-radius: 4px;
  transition: background .13s;
}
.btn-tbl-edit:hover { background: #eff6ff; }
.btn-tbl-del {
  background: none;
  border: none;
  color: #ef4444;
  font-size: 14px;
  cursor: pointer;
  padding: 3px 8px;
  border-radius: 4px;
  transition: background .13s;
}
.btn-tbl-del:hover { background: #fef2f2; }
.rr-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}
.rr-badge.ceiling  { background: #fef3c7; color: #92400e; }
.rr-badge.floor    { background: #dcfce7; color: #166534; }
.rr-badge.nearest  { background: #eff6ff; color: #1d4ed8; }

/* ── Breadcrumb ── */
.rr-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  color: #555;
  margin-bottom: 22px;
}
.rr-breadcrumb a {
  color: #2563eb; text-decoration: none; font-weight: 600;
}
.rr-breadcrumb a:hover { text-decoration: underline; }
.rr-breadcrumb .sep { color: #bbb; font-size: 11px; }
.rr-breadcrumb span { color: #374151; }

/* ── Note box ── */
.rr-note-box {
  border: 1px solid #e8ecf0;
  border-radius: 8px;
  padding: 18px 22px;
  background: #fff;
  margin-bottom: 28px;
  font-size: 13px;
  color: #374151;
  line-height: 1.9;
}
.rr-note-box .note-title {
  font-weight: 700;
  color: #1e2d3d;
  margin-bottom: 6px;
  font-size: 13.5px;
}
.rr-note-box .note-sub {
  font-size: 13px;
  color: #6b7280;
  font-weight: 600;
  margin-bottom: 4px;
}
.rr-note-box ul {
  margin: 0; padding: 0 0 0 4px; list-style: none;
}
.rr-note-box ul li { padding: 2px 0; color: #6b7280; }
.rr-note-box ul li strong { color: #374151; }

/* ── Add form ── */
.rr-form-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 8px;
  padding: 28px 28px 10px;
  margin-bottom: 20px;
}
.rr-field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr 1fr;
  gap: 18px 28px;
  margin-bottom: 22px;
}
.rr-field-grid.single { grid-template-columns: 1fr; }

.rr-field label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 7px;
  font-weight: 500;
}
.rr-input, .rr-select {
  width: 100%;
  border: none;
  border-bottom: 1.5px solid #d1d5db;
  padding: 8px 2px;
  font-size: 13.5px;
  color: #1e2d3d;
  background: transparent;
  outline: none;
  transition: border-color .16s;
  box-sizing: border-box;
}
.rr-input::placeholder { color: #c4c9d4; }
.rr-input:focus, .rr-select:focus { border-color: #2563eb; }
.rr-select {
  appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 4px center;
  padding-right: 22px;
}

/* ── Applicable To section ── */
.rr-applicable {
  border-top: 1px solid #e8ecf0;
  padding-top: 20px;
  margin-top: 4px;
}
.rr-applicable-header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 700;
  color: #1e2d3d;
  cursor: pointer;
  margin-bottom: 16px;
  user-select: none;
}
.rr-applicable-header i {
  font-size: 13px;
  color: #6b7280;
  transition: transform .2s;
}
.rr-applicable-header i.collapsed { transform: rotate(-180deg); }
.rr-applicable-body { margin-bottom: 24px; }

.rr-applicable-body .rr-select-box {
  display: inline-block;
  min-width: 240px;
}
.rr-applicable-body .rr-select-box select {
  width: 100%;
  padding: 9px 32px 9px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13.5px;
  color: #374151;
  background: #fff;
  outline: none;
  appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  transition: border-color .15s;
}
.rr-applicable-body .rr-select-box select:focus { border-color: #2563eb; }

/* form actions */
.rr-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 18px 0 10px;
  border-top: 1px solid #e8ecf0;
  margin-top: 8px;
}
.btn-cancel {
  padding: 9px 28px;
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
  padding: 9px 28px;
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



.toast-wrap{position:fixed;top:20px;right:20px;z-index:99999}
.toast-alert{min-width:280px;padding:13px 16px;border-radius:8px;color:#fff;font-size:13.5px;font-weight:600;box-shadow:0 12px 30px rgba(0,0,0,.18);animation:toastIn .25s ease}
.toast-alert.success{background:#16a34a}
.toast-alert.error{background:#dc2626}
@keyframes toastIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

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

<?php if ($toast): ?>
<div class="toast-wrap">
  <div class="toast-alert <?= esc($toast_type) ?>">
    <?= esc($toast) ?>
  </div>
</div>
<script>
setTimeout(() => {
  const t = document.querySelector('.toast-wrap');
  if (t) t.remove();
}, 3000);
</script>
<?php endif; ?>

<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
<div class="rr-wrapper">

  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
      <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k==='Payroll'?'active':'' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <div class="rr-inner">

    <?php if ($mode === 'list'): ?>

    <div class="pv-topbar rr-list-topbar">
      <nav class="pv-breadcrumb">
        <a href="configuration#Payroll">Payroll</a>
        <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span>Rounding Rules</span>
      </nav>

      <button class="btn-add-rule" onclick="window.location.href='?mode=add'">
        <i class="fa-solid fa-plus"></i> Add New Rule
      </button>
    </div>

    <?php if (empty($rules)): ?>
      <div class="rr-empty">
        <div class="rr-empty-icon">
          <svg viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="8" y="4" width="40" height="48" rx="4" fill="#dbeafe"/>
            <rect x="8" y="4" width="40" height="14" rx="4" fill="#2563eb" opacity=".85"/>
            <rect x="15" y="24" width="26" height="3" rx="1.5" fill="#93c5fd"/>
            <rect x="15" y="32" width="20" height="3" rx="1.5" fill="#93c5fd"/>
            <rect x="15" y="40" width="16" height="3" rx="1.5" fill="#93c5fd"/>
          </svg>
        </div>
        <p class="rr-empty-text">You don't have any Rounding Rules!</p>
      </div>
    <?php else: ?>
      <div class="rr-table-wrap">
        <table class="rr-table">
          <thead>
            <tr>
              <th>Rule Code</th>
              <th>Rule Name</th>
              <th>Rounding Type</th>
              <th>Rounding Value</th>
              <th>Applicable To</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rules as $rule): ?>
            <tr>
              <td><?= esc($rule['rule_code']) ?></td>
              <td><?= esc($rule['rule_name']) ?></td>
              <td>
                <span class="rr-badge <?= esc($badge_class[$rule['rounding_type']] ?? 'nearest') ?>">
                  <?= esc($rule['rounding_type']) ?>
                </span>
              </td>
              <td><?= esc(rtrim(rtrim($rule['rounding_value'], '0'), '.')) ?></td>
              <td><?= esc($rule['applicable_to']) ?></td>
              <td>
                <button class="btn-tbl-edit" onclick="window.location.href='?mode=edit&id=<?= (int)$rule['id'] ?>'" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>

                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this rule?')">
                  <input type="hidden" name="action" value="delete_rule">
                  <input type="hidden" name="del_id" value="<?= (int)$rule['id'] ?>">
                  <button type="submit" class="btn-tbl-del" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php elseif ($mode === 'add' || $mode === 'edit'): ?>

    <nav class="rr-breadcrumb">
      <a href="?mode=list">Rounding rules</a>
      <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
      <span><?= $mode === 'edit' ? 'Edit' : 'New' ?></span>
    </nav>

    <div class="rr-note-box">
      <div class="note-title">Note :</div>
      <div class="note-sub">There are 3 Rounding types</div>
      <ul>
        <li>- <strong>Ceiling</strong> - Rounds the number to the next highest value. E.g, 23.4 is rounded off to 24.</li>
        <li>- <strong>Floor</strong> - Rounds the number to the previous highest value. E.g, 23.4 is rounded off to 23.</li>
        <li>- <strong>Nearest</strong> - Rounds the number to the nearest round value. E.g, 23.4 is rounded off to 23. And 24.7 is rounded off to 25.</li>
      </ul>
    </div>

    <form method="POST">
      <input type="hidden" name="action" value="<?= $mode === 'edit' ? 'edit_rule' : 'add_rule' ?>">
      <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="edit_id" value="<?= (int)$edit_id ?>">
      <?php endif; ?>

      <div class="rr-form-card">
        <div class="rr-field-grid">
          <div class="rr-field">
            <label>Rule Code</label>
            <input type="text" name="rule_code" class="rr-input" value="<?= esc($edit_rule['rule_code']) ?>">
          </div>

          <div class="rr-field">
            <label>Rule Name</label>
            <input type="text" name="rule_name" class="rr-input" value="<?= esc($edit_rule['rule_name']) ?>">
          </div>

          <div class="rr-field">
            <label>Rounding Type</label>
            <select name="rounding_type" class="rr-select">
              <?php foreach ($rounding_types as $rt): ?>
                <option value="<?= esc($rt) ?>" <?= $edit_rule['rounding_type'] === $rt ? 'selected' : '' ?>>
                  <?= esc($rt) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="rr-field">
            <label>Rounding Value</label>
            <input type="text" name="rounding_value" class="rr-input" value="<?= esc(rtrim(rtrim($edit_rule['rounding_value'], '0'), '.')) ?>">
          </div>
        </div>

        <div class="rr-field-grid single" style="margin-bottom:28px">
          <div class="rr-field">
            <label>Note</label>
            <input type="text" name="note" class="rr-input" value="<?= esc($edit_rule['note']) ?>">
          </div>
        </div>

        <div class="rr-applicable">
          <div class="rr-applicable-header" onclick="toggleApplicable(this)">
            Applicable To -
            <i class="fa-solid fa-chevron-up"></i>
          </div>

          <div class="rr-applicable-body" id="applicable-body">
            <div class="rr-select-box">
              <select name="applicable">
                <?php foreach ($applicable_opts as $opt): ?>
                  <option value="<?= esc($opt) ?>" <?= $edit_rule['applicable_to'] === $opt ? 'selected' : '' ?>>
                    <?= esc($opt) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="rr-form-actions">
          <button type="button" class="btn-cancel" onclick="window.location.href='?mode=list'">Cancel</button>
          <button type="submit" class="btn-save">
            <?= $mode === 'edit' ? 'Update' : 'Add' ?>
          </button>
        </div>
      </div>
    </form>

    <?php endif; ?>

  </div>
</div>
</div>

<script>
function toggleApplicable(header) {
  const body = document.getElementById('applicable-body');
  const icon = header.querySelector('i');
  const hidden = body.style.display === 'none';
  body.style.display = hidden ? 'block' : 'none';
  icon.classList.toggle('collapsed', !hidden);
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>