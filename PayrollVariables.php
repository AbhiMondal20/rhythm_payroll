<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Payroll Variables';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

    /* ── Config tab bar (reuse from config page) ── */
.cfg-tabs {
    display:flex;align-items:center;border-bottom:1px solid #E5E7EB;
    background:#fff;overflow-x:auto;scrollbar-width:none;
}
.cfg-tabs::-webkit-scrollbar { display:none; }
.cfg-tab {
    padding:14px 20px;font-size:13.5px;font-weight:500;color:#6B7280;
    cursor:pointer;border:none;background:transparent;
    border-bottom:2.5px solid transparent;white-space:nowrap;
    transition:color .15s,border-color .15s;text-decoration:none;
    display:block;margin-bottom:-1px;
}
.cfg-tab:hover  { color:#111827; }
.cfg-tab.active { color:#2563EB;border-bottom-color:#2563EB;font-weight:600; }

/* ── Layout ── */
.pv-wrapper {
  padding: 20px 28px;
  font-family: 'Segoe UI', sans-serif;
  color: #1e2d3d;
  background: #f5f7fa;
  min-height: calc(100vh - 64px);
}

/* ── Top bar ── */
.pv-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  /* margin-bottom: 18px; */
}
.pv-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  color: #555;
}
.pv-breadcrumb a {
  color: #2563eb;
  text-decoration: none;
  font-weight: 500;
}
.pv-breadcrumb a:hover { text-decoration: underline; }
.pv-breadcrumb .sep { color: #bbb; font-size: 11px; }

.btn-add-var {
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
  text-decoration: none;
}
.btn-add-var:hover { background: #1d4ed8; }

/* ── Split panel ── */
.pv-panel {
  display: flex;
  background: #fff;
  /* border-radius: 10px; */
  border-top: 1px solid #e8ecf0;
  /* box-shadow: 0 1px 5px rgba(0,0,0,.08); */
  overflow: hidden;
  min-height: 480px;
}

/* ── Left list ── */
.pv-list-col {
  width: 36%;
  min-width: 260px;
  border-right: 1px solid #e8ecf0;
  display: flex;
  flex-direction: column;
}
.pv-list-heading {
  padding: 14px 18px;
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  border-bottom: 1px solid #e8ecf0;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.pv-list-scroll {
  overflow-y: auto;
  flex: 1;
  max-height: 520px;
}
.pv-list-scroll::-webkit-scrollbar { width: 4px; }
.pv-list-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

.pv-item {
  padding: 13px 18px;
  border-bottom: 1px solid #f1f4f8;
  cursor: pointer;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  transition: background .12s;
}
.pv-item:last-child { border-bottom: none; }
.pv-item:hover { background: #f8fafc; }
.pv-item.active {
  background: #eff6ff;
  border-left: 3px solid #2563eb;
}
.pv-item.active .pv-item-name { color: #2563eb; font-weight: 700; }
.pv-item-name {
  font-size: 13.5px;
  color: #1e2d3d;
  font-weight: 500;
  word-break: break-all;
}
.pv-item-expr {
  font-size: 11.5px;
  color: #9ca3af;
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 220px;
}
.pv-item-chevron {
  font-size: 11px;
  color: #9ca3af;
  margin-top: 2px;
  flex-shrink: 0;
}

/* ── Right detail / form ── */
.pv-detail-col {
  flex: 1;
  padding: 24px 30px;
  display: flex;
  flex-direction: column;
}
.pv-detail-title-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}
.pv-detail-title {
  font-size: 16px;
  font-weight: 700;
  color: #1e2d3d;
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

/* field rows */
.pv-field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 32px;
  margin-bottom: 22px;
}
.pv-field-row.single { grid-template-columns: 1fr; }

.pv-field label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
  font-weight: 500;
}
.pv-field label .req { color: #ef4444; }
.pv-field-value {
  font-size: 14px;
  color: #1e2d3d;
  padding-bottom: 9px;
  border-bottom: 1px solid #e2e8f0;
  min-height: 28px;
}

/* form inputs */
.pv-input, .pv-select {
  width: 100%;
  border: none;
  border-bottom: 1.5px solid #d1d5db;
  padding: 7px 2px;
  font-size: 13.5px;
  color: #1e2d3d;
  background: transparent;
  outline: none;
  transition: border-color .16s;
  box-sizing: border-box;
}
.pv-input::placeholder { color: #c4c9d4; }
.pv-input:focus, .pv-select:focus { border-color: #2563eb; }
.pv-select {
  appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 4px center;
  padding-right: 22px;
}

/* form actions */
.pv-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: auto;
  padding-top: 28px;
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
.btn-submit {
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
.btn-submit:hover { background: #1d4ed8; }

/* empty/placeholder state */
.pv-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  font-size: 14px;
}

/* flash */
.flash-msg {
  padding: 10px 16px;
  border-radius: 7px;
  font-size: 13px;
  margin-bottom: 14px;
  font-weight: 500;
}
.flash-msg.success { background:#dcfce7; color:#166534; }
.flash-msg.error   { background:#fee2e2; color:#991b1b; }

/* list panel heading row */
.pv-detail-heading {
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
  border-bottom: 1px solid #e8ecf0;
  padding-bottom: 14px;
  margin-bottom: 24px;
}
</style>

<?php
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ── Handle POST ── */
$flash = '';
$flash_type = '';
$active_id = (int)($_GET['id'] ?? 0);
$mode = $_GET['mode'] ?? 'view';

$data_types = ['Number','String','Boolean','Date','Formula'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_variable') {
        $name  = trim($_POST['name'] ?? '');
        $dtype = trim($_POST['data_type'] ?? 'Number');
        $expr  = trim($_POST['expression'] ?? '');
        $exec_order = (int)($_POST['execution_order'] ?? 0);
        $values     = trim($_POST['values'] ?? '');
        $remarks    = trim($_POST['remarks'] ?? '');

        if ($name === '') {
            $flash = 'Name is required.';
            $flash_type = 'error';
            $mode = 'add';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO payroll_variables
                (`name`, `data_type`, `expression`, `execution_order`, `value`, `remarks`, `status`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "sssiss",
                    $name,
                    $dtype,
                    $expr,
                    $exec_order,
                    $values,
                    $remarks
                );

                if ($stmt->execute()) {
                    $active_id = (int)$stmt->insert_id;
                    $flash = "Variable \"$name\" added successfully.";
                    $flash_type = 'success';
                    $mode = 'view';
                } else {
                    $flash = 'Insert failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'add';
                }
            } else {
                $flash = 'Prepare failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'add';
            }
        }
    }

    if ($action === 'edit_variable') {
        $id    = (int)($_POST['edit_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $dtype = trim($_POST['data_type'] ?? 'Number');
        $expr  = trim($_POST['expression'] ?? '');
        $exec_order = (int)($_POST['execution_order'] ?? 0);
        $values     = trim($_POST['values'] ?? '');
        $remarks    = trim($_POST['remarks'] ?? '');

        if ($name === '' || $id === 0) {
            $flash = 'Invalid data.';
            $flash_type = 'error';
            $mode = 'edit';
            $active_id = $id;
        } else {
            $stmt = $conn->prepare("
                UPDATE payroll_variables
                SET
                    `name` = ?,
                    `data_type` = ?,
                    `expression` = ?,
                    `execution_order` = ?,
                    `value` = ?,
                    `remarks` = ?,
                    `updated_at` = NOW()
                WHERE id = ?
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "sssissi",
                    $name,
                    $dtype,
                    $expr,
                    $exec_order,
                    $values,
                    $remarks,
                    $id
                );

                if ($stmt->execute()) {
                    $flash = "Variable updated successfully.";
                    $flash_type = 'success';
                    $active_id = $id;
                    $mode = 'view';
                } else {
                    $flash = 'Update failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $active_id = $id;
                    $mode = 'edit';
                }
            } else {
                $flash = 'Prepare failed: ' . $conn->error;
                $flash_type = 'error';
                $active_id = $id;
                $mode = 'edit';
            }
        }
    }
}

/* ── Fetch variables from DB ── */
$vars = [];

$res = $conn->query("
    SELECT *
    FROM payroll_variables
    WHERE status = 'active'
    ORDER BY execution_order ASC, name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $vars[] = $row;
    }
}

/* Default: show first item */
if ($active_id === 0 && $mode === 'view' && count($vars)) {
    $active_id = (int)$vars[0]['id'];
}

/* Find active variable */
$active_var = null;

if ($active_id > 0) {
    $stmt = $conn->prepare("
        SELECT *
        FROM payroll_variables
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("i", $active_id);
        $stmt->execute();
        $active_res = $stmt->get_result();

        if ($active_res && $active_res->num_rows > 0) {
            $active_var = $active_res->fetch_assoc();
        }
    }
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
 
    <div class="cfg-tabs">
        <?php foreach(['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
        <a href="configuration#<?= e($k) ?>" class="cfg-tab <?= $k==='Payroll'?'active':'' ?>"><?= e($l) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($flash): ?>
        <div class="flash-msg <?= e($flash_type) ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- Top bar -->
    <div class="pv-topbar" style="padding:10px 32px;overflow:hidden">
        <nav class="pv-breadcrumb">
        <a href="payroll.php">Payroll</a>
        <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span>Payroll Variables</span>
        </nav>
        <button class="btn-add-var" onclick="setMode('add')">
        <i class="fa-solid fa-plus"></i> Add Payroll Variables
        </button>
    </div>

    <!-- Split panel -->
    <div class="pv-panel" style="padding:10px 32px;overflow:hidden">

        <!-- ── Left list ── -->
        <div class="pv-list-col">
        <div class="pv-list-heading">List of Payroll Variables</div>
        <div class="pv-list-scroll">
            <?php foreach ($vars as $v): ?>
            <div class="pv-item <?= ((int)$v['id'] === (int)$active_id && $mode === 'view') ? 'active' : '' ?>"
                onclick="selectVar(<?= (int)$v['id'] ?>)">
                <div>
                <div class="pv-item-name"><?= e($v['name']) ?></div>
                <?php if (!empty($v['expression'])): ?>
                    <div class="pv-item-expr"><?= e($v['expression']) ?></div>
                <?php endif; ?>
                </div>
                <i class="fa-solid fa-chevron-down pv-item-chevron"></i>
            </div>
            <?php endforeach; ?>

            <?php if (empty($vars)): ?>
                <div style="padding:24px 18px;color:#9ca3af;font-size:13px">No payroll variables found.</div>
            <?php endif; ?>
        </div>
        </div>

        <!-- ── Right panel ── -->
        <div class="pv-detail-col">

        <?php if ($mode === 'add'): ?>
        <!-- ════ ADD FORM ════ -->
        <div class="pv-detail-heading">Payroll Variables Details</div>
        <div class="pv-detail-title" style="margin-bottom:22px">ADD PAYROLL VARIABLE</div>
        <form method="POST">
            <input type="hidden" name="action" value="add_variable">

            <div class="pv-field-row">
            <div class="pv-field">
                <label><span class="req">* </span>Name</label>
                <input type="text" name="name" class="pv-input"
                    placeholder="Name"
                    value="<?= e($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="pv-field">
                <label>Data Type</label>
                <select name="data_type" class="pv-select">
                <?php foreach ($data_types as $dt): ?>
                    <option value="<?= e($dt) ?>" <?= (($_POST['data_type'] ?? 'Number') === $dt) ? 'selected' : '' ?>>
                    <?= e($dt) ?>
                    </option>
                <?php endforeach; ?>
                </select>
            </div>
            </div>

            <div class="pv-field-row single">
            <div class="pv-field">
                <label><span class="req">* </span>Expression</label>
                <input type="text" name="expression" class="pv-input"
                    placeholder="DaysPresent+DaysHolidays+DaysWeekOffsWorked+PaidLeaves"
                    value="<?= e($_POST['expression'] ?? '') ?>">
            </div>
            </div>

            <div class="pv-field-row">
            <div class="pv-field">
                <label>Execution Order</label>
                <input type="number" name="execution_order" class="pv-input"
                    placeholder="Execution Order"
                    value="<?= e($_POST['execution_order'] ?? '') ?>">
            </div>
            <div class="pv-field">
                <label>Values</label>
                <input type="text" name="values" class="pv-input"
                    placeholder="Values"
                    value="<?= e($_POST['values'] ?? '') ?>">
            </div>
            </div>

            <div class="pv-field-row single">
            <div class="pv-field">
                <label><span class="req">* </span>Remarks</label>
                <input type="text" name="remarks" class="pv-input"
                    placeholder="Remarks"
                    value="<?= e($_POST['remarks'] ?? '') ?>">
            </div>
            </div>

            <div class="pv-form-actions">
            <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
            <button type="submit" class="btn-submit">Add</button>
            </div>
        </form>

        <?php elseif ($mode === 'edit' && $active_var): ?>
        <!-- ════ EDIT FORM ════ -->
        <div class="pv-detail-heading">Payroll Variables Details</div>
        <div class="pv-detail-title" style="margin-bottom:22px">
            EDIT — <?= e($active_var['name']) ?>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_variable">
            <input type="hidden" name="edit_id" value="<?= (int)$active_var['id'] ?>">

            <div class="pv-field-row">
            <div class="pv-field">
                <label><span class="req">* </span>Name</label>
                <input type="text" name="name" class="pv-input"
                    value="<?= e($_POST['name'] ?? $active_var['name']) ?>" required>
            </div>
            <div class="pv-field">
                <label>Data Type</label>
                <select name="data_type" class="pv-select">
                <?php foreach ($data_types as $dt): ?>
                    <option value="<?= e($dt) ?>"
                    <?= (($_POST['data_type'] ?? $active_var['data_type']) === $dt) ? 'selected' : '' ?>>
                    <?= e($dt) ?>
                    </option>
                <?php endforeach; ?>
                </select>
            </div>
            </div>

            <div class="pv-field-row single">
            <div class="pv-field">
                <label><span class="req">* </span>Expression</label>
                <input type="text" name="expression" class="pv-input"
                    value="<?= e($_POST['expression'] ?? $active_var['expression']) ?>">
            </div>
            </div>

            <div class="pv-field-row">
            <div class="pv-field">
                <label>Execution Order</label>
                <input type="number" name="execution_order" class="pv-input"
                    value="<?= e($_POST['execution_order'] ?? $active_var['execution_order']) ?>">
            </div>
            <div class="pv-field">
                <label>Values</label>
                <input type="text" name="values" class="pv-input"
                    value="<?= e($_POST['values'] ?? $active_var['value']) ?>">
            </div>
            </div>

            <div class="pv-field-row single">
            <div class="pv-field">
                <label><span class="req">* </span>Remarks</label>
                <input type="text" name="remarks" class="pv-input"
                    value="<?= e($_POST['remarks'] ?? $active_var['remarks']) ?>">
            </div>
            </div>

            <div class="pv-form-actions">
            <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
            <button type="submit" class="btn-submit">Update</button>
            </div>
        </form>

        <?php elseif ($active_var): ?>
        <!-- ════ VIEW DETAIL ════ -->
        <div class="pv-detail-heading">Payroll Variables Details</div>
        <div class="pv-detail-title-bar">
            <div class="pv-detail-title"><?= e($active_var['name']) ?></div>
            <button class="btn-edit-link" onclick="setMode('edit', <?= (int)$active_var['id'] ?>)">
            <i class="fa-regular fa-pen-to-square"></i> Edit Details
            </button>
        </div>

        <div class="pv-field-row">
            <div class="pv-field">
            <label>Name</label>
            <div class="pv-field-value"><?= e($active_var['name']) ?></div>
            </div>
            <div class="pv-field">
            <label>Data Type</label>
            <div class="pv-field-value"><?= e($active_var['data_type']) ?></div>
            </div>
        </div>

        <div class="pv-field-row single">
            <div class="pv-field">
            <label>Expression</label>
            <div class="pv-field-value"><?= e($active_var['expression']) ?>&nbsp;</div>
            </div>
        </div>

        <div class="pv-field-row">
            <div class="pv-field">
            <label>Execution Order</label>
            <div class="pv-field-value"><?= e($active_var['execution_order']) ?></div>
            </div>
            <div class="pv-field">
            <label>Values</label>
            <div class="pv-field-value"><?= e($active_var['value']) ?></div>
            </div>
        </div>

        <div class="pv-field-row single">
            <div class="pv-field">
            <label>Remarks</label>
            <div class="pv-field-value"><?= e($active_var['remarks']) ?>&nbsp;</div>
            </div>
        </div>

        <?php else: ?>
        <div class="pv-empty">Select a variable from the list to view details.</div>
        <?php endif; ?>

        </div><!-- /pv-detail-col -->
    </div>
    <!-- /pv-panel -->
</div>

<script>
function selectVar(id) {
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

(function() {
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');
  const mode = params.get('mode') || 'view';
  if (mode !== 'view' || !id) return;
  document.querySelectorAll('.pv-item').forEach(function(el) {
    el.classList.remove('active');
  });
  const items = document.querySelectorAll('.pv-item');
  items.forEach(function(el) {
    if (el.getAttribute('onclick') && el.getAttribute('onclick').includes('(' + id + ')')) {
      el.classList.add('active');
    }
  });
})();
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>