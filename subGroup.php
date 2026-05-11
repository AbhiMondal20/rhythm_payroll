<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Sub Group Configuration';
ob_start();

function esc($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
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

$groups = [];
$groupRes = $conn->query("SELECT id, group_name FROM org_groups ORDER BY group_name ASC");
if ($groupRes) {
    while ($row = $groupRes->fetch_assoc()) {
        $groups[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $group_id = (int)($_POST['group_id'] ?? 0);
    $code     = trim($_POST['code_name'] ?? '');
    $name     = trim($_POST['sub_group_name'] ?? '');
    $remarks  = trim($_POST['remarks'] ?? '');

    if ($code === '' || $name === '') {
        $_SESSION['toast_msg'] = 'Code Name and Sub Group Name are required.';
        $_SESSION['toast_type'] = 'error';
        header("Location: ?mode=" . urlencode($mode) . "&id=" . $active_id);
        exit;
    }

    if ($action === 'add_sub_group') {
        $stmt = $conn->prepare("INSERT INTO org_sub_groups (group_id, code_name, sub_group_name, remarks) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $group_id, $code, $name, $remarks);

        if ($stmt->execute()) {
            $_SESSION['toast_msg'] = 'Sub Group added successfully.';
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

    if ($action === 'edit_sub_group') {
        $id = (int)($_POST['edit_id'] ?? 0);

        $stmt = $conn->prepare("UPDATE org_sub_groups SET group_id=?, code_name=?, sub_group_name=?, remarks=? WHERE id=?");
        $stmt->bind_param("isssi", $group_id, $code, $name, $remarks, $id);

        if ($stmt->execute()) {
            $_SESSION['toast_msg'] = 'Sub Group updated successfully.';
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
}

$sub_groups = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("
        SELECT sg.*, g.group_name
        FROM org_sub_groups sg
        LEFT JOIN org_groups g ON g.id = sg.group_id
        WHERE sg.sub_group_name LIKE ? 
           OR sg.code_name LIKE ?
           OR g.group_name LIKE ?
        ORDER BY sg.sub_group_name ASC
    ");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query("
        SELECT sg.*, g.group_name
        FROM org_sub_groups sg
        LEFT JOIN org_groups g ON g.id = sg.group_id
        ORDER BY sg.sub_group_name ASC
    ");
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sub_groups[] = $row;
    }
}

if ($active_id === 0 && $mode === 'view' && count($sub_groups)) {
    $active_id = (int)$sub_groups[0]['id'];
}

$active_sub_group = null;
if ($active_id > 0) {
    $stmt = $conn->prepare("
        SELECT sg.*, g.group_name
        FROM org_sub_groups sg
        LEFT JOIN org_groups g ON g.id = sg.group_id
        WHERE sg.id=?
        LIMIT 1
    ");
    $stmt->bind_param("i", $active_id);
    $stmt->execute();
    $active_sub_group = $stmt->get_result()->fetch_assoc();
}
?>

<link rel="stylesheet" href="includes/assets/style.css">

<style>
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}
.subgrp-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.subgrp-inner{padding:20px 28px}
.subgrp-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.subgrp-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555}
.subgrp-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.subgrp-breadcrumb a:hover{text-decoration:underline}
.subgrp-breadcrumb .sep{color:#bbb;font-size:11px}
.subgrp-breadcrumb span{color:#374151}
.btn-add-subgrp{display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .16s}
.btn-add-subgrp:hover{background:#1d4ed8}
.subgrp-panel{display:flex;background:#fff;border:1px solid #e8ecf0;border-radius:10px;overflow:hidden;min-height:440px}
.subgrp-list-col{width:37%;min-width:240px;border-right:1px solid #e8ecf0;display:flex;flex-direction:column}
.subgrp-list-heading{padding:14px 16px 10px;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.4px}
.subgrp-search-wrap{padding:0 14px 12px}
.subgrp-search-inner{position:relative}
.subgrp-search-inner i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:12px}
.subgrp-search-input{width:100%;padding:8px 10px 8px 32px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;color:#1e2d3d;outline:none;box-sizing:border-box;background:#f9fafb;transition:border-color .15s}
.subgrp-search-input:focus{border-color:#2563eb;background:#fff}
.subgrp-list-scroll{flex:1;overflow-y:auto;max-height:520px}
.subgrp-list-scroll::-webkit-scrollbar{width:4px}
.subgrp-list-scroll::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px}
.subgrp-item{padding:13px 16px;border-bottom:1px solid #f1f4f8;cursor:pointer;display:flex;align-items:center;justify-content:space-between;transition:background .12s}
.subgrp-item:last-child{border-bottom:none}
.subgrp-item:hover{background:#f8fafc}
.subgrp-item.active{background:#eff6ff;border-left:3px solid #2563eb;padding-left:13px}
.subgrp-item-name{font-size:13.5px;font-weight:500;color:#1e2d3d}
.subgrp-item-small{display:block;font-size:11.5px;color:#8a94a6;margin-top:3px}
.subgrp-item.active .subgrp-item-name{color:#2563eb;font-weight:700}
.subgrp-item-chevron{font-size:11px;color:#9ca3af}
.subgrp-detail-col{flex:1;padding:22px 32px;display:flex;flex-direction:column}
.subgrp-detail-heading{font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e8ecf0;padding-bottom:12px;margin-bottom:22px}
.subgrp-detail-title-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
.subgrp-detail-title{font-size:15px;font-weight:800;color:#1e2d3d;text-transform:uppercase;letter-spacing:.3px}
.btn-edit-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:600;padding:0}
.btn-edit-link:hover{text-decoration:underline}
.subgrp-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px 36px;margin-bottom:6px}
.subgrp-field-grid.single{grid-template-columns:1fr}
.subgrp-field label{display:block;font-size:12.5px;color:#374151;margin-bottom:8px;font-weight:400}
.subgrp-field label .req{color:#ef4444;margin-right:2px}
.subgrp-field-value{font-size:13.5px;color:#1e2d3d;padding-bottom:9px;border-bottom:1px solid #e2e8f0;min-height:28px}
.subgrp-input{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s}
.subgrp-input::placeholder{color:#c4c9d4}
.subgrp-input:focus{border-color:#2563eb}
.subgrp-select{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s}
.subgrp-select:focus{border-color:#2563eb}
.subgrp-form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:auto;padding-top:28px}
.btn-cancel{padding:9px 26px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:13.5px;color:#374151;cursor:pointer;font-weight:600;transition:background .14s}
.btn-cancel:hover{background:#f1f5f9}
.btn-save{padding:9px 26px;background:#2563eb;border:none;border-radius:6px;font-size:13.5px;color:#fff;cursor:pointer;font-weight:600;transition:background .14s}
.btn-save:hover{background:#1d4ed8}
.subgrp-empty{flex:1;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13.5px}
.toast-box{position:fixed;top:18px;right:18px;z-index:99999;min-width:280px;padding:13px 16px;border-radius:8px;color:#fff;font-size:13.5px;font-weight:600;box-shadow:0 10px 30px rgba(0,0,0,.18);display:flex;gap:10px;align-items:center}
.toast-box.success{background:#16a34a}
.toast-box.error{background:#dc2626}

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

<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>
<div class="section-card" style="padding:0;overflow:hidden">
<div class="subgrp-wrapper">

  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= esc($k) ?>" class="cfg-tab <?= $k==='Organization'?'active':'' ?>">
      <?= esc($l) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="subgrp-inner">

    <div class="subgrp-topbar">
      <nav class="subgrp-breadcrumb">
        <a href="configuration#Organization">Organization Masters</a>
        <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span>Sub Groups</span>
      </nav>

      <?php if ($mode !== 'add'): ?>
      <button class="btn-add-subgrp" onclick="setMode('add')">
        <i class="fa-solid fa-plus"></i> Add Sub Group
      </button>
      <?php endif; ?>
    </div>

    <div class="subgrp-panel">

      <div class="subgrp-list-col">
        <div class="subgrp-list-heading">List of Sub Groups</div>

        <div class="subgrp-search-wrap">
          <form method="GET" style="display:contents" id="searchForm">
            <input type="hidden" name="mode" value="view">
            <div class="subgrp-search-inner">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" name="q" class="subgrp-search-input"
                     placeholder="Search items"
                     value="<?= esc($search) ?>">
            </div>
          </form>
        </div>

        <div class="subgrp-list-scroll">
          <?php foreach ($sub_groups as $sg): ?>
            <div class="subgrp-item <?= ((int)$sg['id'] === $active_id && $mode !== 'add') ? 'active' : '' ?>"
                 onclick="selectSubGroup(<?= (int)$sg['id'] ?>)">
              <div>
                <span class="subgrp-item-name"><?= esc($sg['sub_group_name']) ?></span>
                <span class="subgrp-item-small"><?= esc($sg['group_name'] ?? 'No Group') ?></span>
              </div>
              <i class="fa-solid <?= ((int)$sg['id'] === $active_id && $mode !== 'add') ? 'fa-chevron-right' : 'fa-chevron-down' ?> subgrp-item-chevron"></i>
            </div>
          <?php endforeach; ?>

          <?php if (empty($sub_groups)): ?>
            <div style="padding:22px 16px;color:#9ca3af;font-size:13px">No sub groups found.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="subgrp-detail-col">
        <div class="subgrp-detail-heading">Sub Group Details</div>

        <?php if ($mode === 'add'): ?>

        <div class="subgrp-detail-title" style="margin-bottom:26px">ADD SUB GROUP</div>

        <form method="POST">
          <input type="hidden" name="action" value="add_sub_group">

          <div class="subgrp-field-grid" style="margin-bottom:22px">
            <div class="subgrp-field">
              <label>Group</label>
              <select name="group_id" class="subgrp-select">
                <option value="0">Select Group</option>
                <?php foreach ($groups as $g): ?>
                  <option value="<?= (int)$g['id'] ?>"><?= esc($g['group_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="subgrp-field">
              <label><span class="req">*</span> Code Name</label>
              <input type="text" name="code_name" class="subgrp-input" placeholder="Code Name" required>
            </div>
          </div>

          <div class="subgrp-field-grid" style="margin-bottom:22px">
            <div class="subgrp-field">
              <label><span class="req">*</span> Sub Group Name</label>
              <input type="text" name="sub_group_name" class="subgrp-input" placeholder="Sub Group Name" required>
            </div>

            <div class="subgrp-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="subgrp-input" placeholder="Remarks">
            </div>
          </div>

          <div class="subgrp-form-actions">
            <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
            <button type="submit" class="btn-save">Add</button>
          </div>
        </form>

        <?php elseif ($mode === 'edit' && $active_sub_group): ?>

        <div class="subgrp-detail-title" style="margin-bottom:26px">
          EDIT — <?= esc($active_sub_group['sub_group_name']) ?>
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="edit_sub_group">
          <input type="hidden" name="edit_id" value="<?= (int)$active_sub_group['id'] ?>">

          <div class="subgrp-field-grid" style="margin-bottom:22px">
            <div class="subgrp-field">
              <label>Group</label>
              <select name="group_id" class="subgrp-select">
                <option value="0">Select Group</option>
                <?php foreach ($groups as $g): ?>
                  <option value="<?= (int)$g['id'] ?>" <?= ((int)$active_sub_group['group_id'] === (int)$g['id']) ? 'selected' : '' ?>>
                    <?= esc($g['group_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="subgrp-field">
              <label><span class="req">*</span> Code Name</label>
              <input type="text" name="code_name" class="subgrp-input"
                     value="<?= esc($active_sub_group['code_name']) ?>" required>
            </div>
          </div>

          <div class="subgrp-field-grid" style="margin-bottom:22px">
            <div class="subgrp-field">
              <label><span class="req">*</span> Sub Group Name</label>
              <input type="text" name="sub_group_name" class="subgrp-input"
                     value="<?= esc($active_sub_group['sub_group_name']) ?>" required>
            </div>

            <div class="subgrp-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="subgrp-input"
                     value="<?= esc($active_sub_group['remarks'] ?? '') ?>">
            </div>
          </div>

          <div class="subgrp-form-actions">
            <button type="button" class="btn-cancel"
                    onclick="window.location.href='?id=<?= (int)$active_sub_group['id'] ?>&mode=view'">
              Cancel
            </button>
            <button type="submit" class="btn-save">Update</button>
          </div>
        </form>

        <?php elseif ($active_sub_group): ?>

        <div class="subgrp-detail-title-bar">
          <div class="subgrp-detail-title"><?= esc($active_sub_group['sub_group_name']) ?></div>
          <button class="btn-edit-link"
                  onclick="window.location.href='?id=<?= (int)$active_sub_group['id'] ?>&mode=edit'">
            <i class="fa-regular fa-pen-to-square"></i> Edit Details
          </button>
        </div>

        <div class="subgrp-field-grid" style="margin-bottom:22px">
          <div class="subgrp-field">
            <label>Group</label>
            <div class="subgrp-field-value"><?= esc($active_sub_group['group_name'] ?? 'No Group') ?></div>
          </div>

          <div class="subgrp-field">
            <label>Code Name</label>
            <div class="subgrp-field-value"><?= esc($active_sub_group['code_name']) ?></div>
          </div>
        </div>

        <div class="subgrp-field-grid" style="margin-bottom:22px">
          <div class="subgrp-field">
            <label>Sub Group Name</label>
            <div class="subgrp-field-value"><?= esc($active_sub_group['sub_group_name']) ?></div>
          </div>

          <div class="subgrp-field">
            <label>Remarks</label>
            <div class="subgrp-field-value"><?= esc($active_sub_group['remarks'] ?? '') ?>&nbsp;</div>
          </div>
        </div>

        <?php else: ?>

        <div class="subgrp-empty">Select a sub group to view details.</div>

        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
</div>

<script>
function selectSubGroup(id) {
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

let searchTimer;
const searchInput = document.querySelector('.subgrp-search-input');

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