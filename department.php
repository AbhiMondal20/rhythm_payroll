<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Department Configuration';

function esc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$active_id = (int)($_GET['id'] ?? 0);
$mode      = $_GET['mode'] ?? 'view';
$search    = trim($_GET['q'] ?? '');

$toast_msg  = $_SESSION['toast_msg'] ?? '';
$toast_icon = $_SESSION['toast_icon'] ?? '✅';
unset($_SESSION['toast_msg'], $_SESSION['toast_icon']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_dept') {
        $code    = trim($_POST['code_name'] ?? '');
        $name    = trim($_POST['dept_name'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($code === '' || $name === '') {
            $_SESSION['toast_icon'] = '⚠';
            $_SESSION['toast_msg']  = 'Code Name and Department Name are required.';
            header("Location: ?mode=add");
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO org_departments (code_name, dept_name, remarks, status) VALUES (?, ?, ?, 'active')");
        $stmt->bind_param("sss", $code, $name, $remarks);

        if ($stmt->execute()) {
            $_SESSION['toast_icon'] = '✅';
            $_SESSION['toast_msg']  = 'Department added successfully.';
            header("Location: ?id=" . $stmt->insert_id . "&mode=view");
            exit;
        } else {
            $_SESSION['toast_icon'] = '❌';
            $_SESSION['toast_msg']  = 'Save failed: ' . $stmt->error;
            header("Location: ?mode=add");
            exit;
        }
    }

    if ($action === 'edit_dept') {
        $id      = (int)($_POST['edit_id'] ?? 0);
        $code    = trim($_POST['code_name'] ?? '');
        $name    = trim($_POST['dept_name'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($id <= 0 || $code === '' || $name === '') {
            $_SESSION['toast_icon'] = '⚠';
            $_SESSION['toast_msg']  = 'Code Name and Department Name are required.';
            header("Location: ?id=" . $id . "&mode=edit");
            exit;
        }

        $stmt = $conn->prepare("UPDATE org_departments SET code_name=?, dept_name=?, remarks=? WHERE id=?");
        $stmt->bind_param("sssi", $code, $name, $remarks, $id);

        if ($stmt->execute()) {
            $_SESSION['toast_icon'] = '✅';
            $_SESSION['toast_msg']  = 'Department updated successfully.';
        } else {
            $_SESSION['toast_icon'] = '❌';
            $_SESSION['toast_msg']  = 'Update failed: ' . $stmt->error;
        }

        header("Location: ?id=" . $id . "&mode=view");
        exit;
    }

    if ($action === 'delete_dept') {
        $id = (int)($_POST['delete_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM org_departments WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['toast_icon'] = '✅';
            $_SESSION['toast_msg']  = 'Department deleted successfully.';
        } else {
            $_SESSION['toast_icon'] = '❌';
            $_SESSION['toast_msg']  = 'Delete failed: ' . $stmt->error;
        }

        header("Location: ?mode=view");
        exit;
    }
}

$depts = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("
        SELECT * FROM org_departments
        WHERE dept_name LIKE ? OR code_name LIKE ?
        ORDER BY dept_name ASC
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query("SELECT * FROM org_departments ORDER BY dept_name ASC");
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $depts[] = $row;
    }
}

if ($active_id === 0 && $mode === 'view' && !empty($depts)) {
    $active_id = (int)$depts[0]['id'];
}

$active_dept = null;
if ($active_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM org_departments WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $active_id);
    $stmt->execute();
    $active_dept = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

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
.dept-wrapper {
  font-family: 'Segoe UI', sans-serif;
  color: #1e2d3d;
  padding: 0 0 40px;
}
.dept-inner { padding: 20px 28px; }

/* ── Top bar ── */
.dept-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
}
.dept-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  color: #555;
}
.dept-breadcrumb a { color: #1e2d3d; text-decoration: none; font-weight: 600; }
.dept-breadcrumb a:hover { text-decoration: underline; }
.dept-breadcrumb .sep { color: #bbb; font-size: 11px; }
.dept-breadcrumb span { color: #374151; }

.btn-add-dept {
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
.btn-add-dept:hover { background: #1d4ed8; }

/* ── Split panel ── */
.dept-panel {
  display: flex;
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 10px;
  overflow: hidden;
  min-height: 520px;
}

/* ── Left list ── */
.dept-list-col {
  width: 37%;
  min-width: 240px;
  border-right: 1px solid #e8ecf0;
  display: flex;
  flex-direction: column;
}
.dept-list-heading {
  padding: 14px 16px 10px;
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.dept-search-wrap {
  padding: 0 14px 12px;
}
.dept-search-inner {
  position: relative;
}
.dept-search-inner i {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 12px;
}
.dept-search-input {
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
.dept-search-input:focus { border-color: #2563eb; background: #fff; }

.dept-list-scroll {
  flex: 1;
  overflow-y: auto;
  max-height: 580px;
}
.dept-list-scroll::-webkit-scrollbar { width: 4px; }
.dept-list-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

.dept-item {
  padding: 13px 16px;
  border-bottom: 1px solid #f1f4f8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: background .12s;
}
.dept-item:last-child { border-bottom: none; }
.dept-item:hover { background: #f8fafc; }
.dept-item.active {
  background: #eff6ff;
  border-left: 3px solid #2563eb;
  padding-left: 13px;
}
.dept-item-name {
  font-size: 13.5px;
  font-weight: 500;
  color: #1e2d3d;
}
.dept-item.active .dept-item-name { color: #2563eb; font-weight: 700; }
.dept-item-chevron { font-size: 11px; color: #9ca3af; }

/* ── Right panel ── */
.dept-detail-col {
  flex: 1;
  padding: 22px 32px;
  display: flex;
  flex-direction: column;
}
.dept-detail-heading {
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
  border-bottom: 1px solid #e8ecf0;
  padding-bottom: 12px;
  margin-bottom: 22px;
}
.dept-detail-title-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22px;
}
.dept-detail-title {
  font-size: 16px;
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

/* Field grid */
.dept-field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 22px 36px;
  margin-bottom: 6px;
}
.dept-field-grid.single { grid-template-columns: 1fr; }

.dept-field label {
  display: block;
  font-size: 12.5px;
  color: #374151;
  margin-bottom: 8px;
  font-weight: 400;
}
.dept-field label .req { color: #ef4444; margin-right: 2px; }

.dept-field-value {
  font-size: 13.5px;
  color: #1e2d3d;
  padding-bottom: 9px;
  border-bottom: 1px solid #e2e8f0;
  min-height: 28px;
}

/* Inputs */
.dept-input {
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
.dept-input::placeholder { color: #c4c9d4; }
.dept-input:focus { border-color: #2563eb; }

/* Form actions */
.dept-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: auto;
  padding-top: 28px;
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

/* Empty state */
.dept-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  font-size: 13.5px;
}

/* Toast */
.dept-toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%) translateY(80px);
  background: #111827;
  color: #fff;
  padding: 11px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  z-index: 99999;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 8px 28px rgba(0,0,0,.2);
  transition: transform .3s ease;
  white-space: nowrap;
}
.dept-toast.show {
  transform: translateX(-50%) translateY(0);
}

/* Delete modal */
.dept-del-confirm {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15,16,32,.45);
  z-index: 600;
  align-items: center;
  justify-content: center;
  padding: 16px;
  backdrop-filter: blur(2px);
}
.dept-del-confirm.open { display: flex; }
.dept-del-box {
  background: #fff;
  border-radius: 14px;
  max-width: 400px;
  width: 100%;
  padding: 28px;
  text-align: center;
  box-shadow: 0 20px 50px rgba(0,0,0,.2);
}
</style>

<div class="section-card" style="padding:0;overflow:hidden">
<div class="dept-wrapper">

  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll',
                    'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= esc($k) ?>"
       class="cfg-tab <?= $k==='Organization'?'active':'' ?>">
      <?= esc($l) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="dept-inner">

    <div class="dept-topbar">
      <nav class="dept-breadcrumb">
        <a href="org_masters.php">Organization Masters</a>
        <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span>Department</span>
      </nav>

      <?php if ($mode !== 'add'): ?>
      <button class="btn-add-dept" onclick="setMode('add')">
        <i class="fa-solid fa-plus"></i> Add Department
      </button>
      <?php endif; ?>
    </div>

    <div class="dept-panel">

      <div class="dept-list-col">
        <div class="dept-list-heading">List of Departments</div>

        <div class="dept-search-wrap">
          <form method="GET" style="display:contents">
            <input type="hidden" name="id" value="<?= (int)$active_id ?>">
            <input type="hidden" name="mode" value="view">
            <div class="dept-search-inner">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" name="q" class="dept-search-input"
                     placeholder="Search items"
                     value="<?= esc($search) ?>"
                     oninput="searchDelay(this.form)">
            </div>
          </form>
        </div>

        <div class="dept-list-scroll">
          <?php foreach ($depts as $dept): ?>
            <div class="dept-item <?= ((int)$dept['id'] === (int)$active_id && $mode !== 'add') ? 'active' : '' ?>"
                 onclick="selectDept(<?= (int)$dept['id'] ?>)">
              <span class="dept-item-name"><?= esc($dept['dept_name']) ?></span>
              <i class="fa-solid <?= ((int)$dept['id'] === (int)$active_id && $mode !== 'add') ? 'fa-chevron-right' : 'fa-chevron-down' ?> dept-item-chevron"></i>
            </div>
          <?php endforeach; ?>

          <?php if (empty($depts)): ?>
            <div style="padding:22px 16px;color:#9ca3af;font-size:13px">No departments found.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="dept-detail-col">
        <div class="dept-detail-heading">Department Details</div>

        <?php if ($mode === 'add'): ?>

        <div class="dept-detail-title" style="margin-bottom:26px">ADD DEPARTMENT</div>

        <form method="POST">
          <input type="hidden" name="action" value="add_dept">

          <div class="dept-field-grid" style="margin-bottom:22px">
            <div class="dept-field">
              <label><span class="req">*</span> Code Name</label>
              <input type="text" name="code_name" class="dept-input"
                     placeholder="Code Name"
                     value="<?= esc($_POST['code_name'] ?? '') ?>" required>
            </div>

            <div class="dept-field">
              <label><span class="req">*</span> Department Name</label>
              <input type="text" name="dept_name" class="dept-input"
                     placeholder="Department Name"
                     value="<?= esc($_POST['dept_name'] ?? '') ?>" required>
            </div>
          </div>

          <div class="dept-field-grid single" style="margin-bottom:10px">
            <div class="dept-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="dept-input"
                     placeholder="Remarks"
                     value="<?= esc($_POST['remarks'] ?? '') ?>">
            </div>
          </div>

          <div class="dept-form-actions">
            <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
            <button type="submit" class="btn-save">Add</button>
          </div>
        </form>

        <?php elseif ($mode === 'edit' && $active_dept): ?>

        <div class="dept-detail-title" style="margin-bottom:26px">
          EDIT — <?= esc($active_dept['dept_name']) ?>
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="edit_dept">
          <input type="hidden" name="edit_id" value="<?= (int)$active_dept['id'] ?>">

          <div class="dept-field-grid" style="margin-bottom:22px">
            <div class="dept-field">
              <label><span class="req">*</span> Code Name</label>
              <input type="text" name="code_name" class="dept-input"
                     value="<?= esc($_POST['code_name'] ?? $active_dept['code_name']) ?>" required>
            </div>

            <div class="dept-field">
              <label><span class="req">*</span> Department Name</label>
              <input type="text" name="dept_name" class="dept-input"
                     value="<?= esc($_POST['dept_name'] ?? $active_dept['dept_name']) ?>" required>
            </div>
          </div>

          <div class="dept-field-grid single" style="margin-bottom:10px">
            <div class="dept-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="dept-input"
                     value="<?= esc($_POST['remarks'] ?? $active_dept['remarks']) ?>">
            </div>
          </div>

          <div class="dept-form-actions">
            <button type="button" class="btn-cancel"
                    onclick="window.location.href='?id=<?= (int)$active_dept['id'] ?>&mode=view'">Cancel</button>
            <button type="submit" class="btn-save">Update</button>
          </div>
        </form>

        <?php elseif ($active_dept): ?>

        <div class="dept-detail-title-bar">
          <div class="dept-detail-title"><?= esc($active_dept['dept_name']) ?></div>

          <div style="display:flex;gap:14px;align-items:center">
            <button class="btn-edit-link"
                    onclick="window.location.href='?id=<?= (int)$active_dept['id'] ?>&mode=edit'">
              <i class="fa-regular fa-pen-to-square"></i> Edit Details
            </button>

            <button class="btn-edit-link" style="color:#dc2626"
                    onclick="document.getElementById('deptDelConfirm').classList.add('open')">
              <i class="fa-regular fa-trash-can"></i> Delete
            </button>
          </div>
        </div>

        <div class="dept-field-grid" style="margin-bottom:22px">
          <div class="dept-field">
            <label>Code Name</label>
            <div class="dept-field-value"><?= esc($active_dept['code_name']) ?></div>
          </div>

          <div class="dept-field">
            <label>Department Name</label>
            <div class="dept-field-value"><?= esc($active_dept['dept_name']) ?></div>
          </div>
        </div>

        <div class="dept-field-grid single">
          <div class="dept-field">
            <label>Remarks</label>
            <div class="dept-field-value"><?= esc($active_dept['remarks']) ?>&nbsp;</div>
          </div>
        </div>

        <?php else: ?>

        <div class="dept-empty">Select a department to view details.</div>

        <?php endif; ?>

      </div>
    </div>

  </div>
</div>
</div>

<div class="dept-del-confirm" id="deptDelConfirm" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="dept-del-box">
    <div style="width:56px;height:56px;background:#FEE2E2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px">🗑</div>
    <h3 style="font-size:16px;font-weight:700;color:#111827;margin-bottom:8px">Delete Department?</h3>
    <p style="font-size:13px;color:#6B7280;line-height:1.6;margin-bottom:20px">
      This will permanently delete <strong><?= esc($active_dept['dept_name'] ?? '') ?></strong>.
    </p>

    <div style="display:flex;gap:8px;justify-content:center">
      <button class="btn-cancel" onclick="document.getElementById('deptDelConfirm').classList.remove('open')" style="min-width:100px">Cancel</button>

      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="delete_dept">
        <input type="hidden" name="delete_id" value="<?= (int)($active_dept['id'] ?? 0) ?>">
        <button type="submit" class="btn-save" style="background:#DC2626;min-width:100px">Delete</button>
      </form>
    </div>
  </div>
</div>

<div class="dept-toast" id="deptToastEl">
  <span id="deptToastIcon">✅</span>
  <span id="deptToastMsg">Done!</span>
</div>

<script>
let searchTimer = null;

function searchDelay(form) {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(function() {
    form.submit();
  }, 450);
}

function selectDept(id) {
  const url = new URL(window.location.href);
  url.searchParams.set('id', id);
  url.searchParams.set('mode', 'view');

  <?php if ($search): ?>
  url.searchParams.set('q', <?= json_encode($search) ?>);
  <?php endif; ?>

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

function deptToast(icon, msg) {
  const t  = document.getElementById('deptToastEl');
  const ti = document.getElementById('deptToastIcon');
  const tm = document.getElementById('deptToastMsg');

  ti.textContent = icon;
  tm.textContent = msg;

  t.classList.add('show');

  clearTimeout(t._timer);
  t._timer = setTimeout(function() {
    t.classList.remove('show');
  }, 3200);
}

<?php if ($toast_msg): ?>
document.addEventListener('DOMContentLoaded', function() {
  deptToast(<?= json_encode($toast_icon) ?>, <?= json_encode($toast_msg) ?>);
});
<?php endif; ?>
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>