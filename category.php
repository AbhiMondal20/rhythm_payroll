<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Category Configuration';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $code    = trim($_POST['code_name'] ?? '');
    $name    = trim($_POST['cat_name'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($code === '' || $name === '') {
        $_SESSION['toast_msg'] = 'Code Name and Category Name are required.';
        $_SESSION['toast_type'] = 'error';
        header("Location: ?mode=" . urlencode($mode) . "&id=" . $active_id);
        exit;
    }

    if ($action === 'add_cat') {
        $stmt = $conn->prepare("INSERT INTO org_categories (code_name, cat_name, remarks) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $code, $name, $remarks);

        if ($stmt->execute()) {
            $_SESSION['toast_msg'] = 'Category added successfully.';
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

    if ($action === 'edit_cat') {
        $id = (int)($_POST['edit_id'] ?? 0);

        $stmt = $conn->prepare("UPDATE org_categories SET code_name=?, cat_name=?, remarks=? WHERE id=?");
        $stmt->bind_param("sssi", $code, $name, $remarks, $id);

        if ($stmt->execute()) {
            $_SESSION['toast_msg'] = 'Category updated successfully.';
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

$cats = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT * FROM org_categories WHERE cat_name LIKE ? OR code_name LIKE ? ORDER BY cat_name ASC");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query("SELECT * FROM org_categories ORDER BY cat_name ASC");
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cats[] = $row;
    }
}

if ($active_id === 0 && $mode === 'view' && count($cats)) {
    $active_id = (int)$cats[0]['id'];
}

$active_cat = null;
if ($active_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM org_categories WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $active_id);
    $stmt->execute();
    $active_cat = $stmt->get_result()->fetch_assoc();
}
?>

<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}
.cat-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.cat-inner{padding:20px 28px}
.cat-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.cat-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555}
.cat-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.cat-breadcrumb a:hover{text-decoration:underline}
.cat-breadcrumb .sep{color:#bbb;font-size:11px}
.cat-breadcrumb span{color:#374151}
.btn-add-cat{display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .16s}
.btn-add-cat:hover{background:#1d4ed8}
.cat-panel{display:flex;background:#fff;border:1px solid #e8ecf0;border-radius:10px;overflow:hidden;min-height:440px}
.cat-list-col{width:37%;min-width:240px;border-right:1px solid #e8ecf0;display:flex;flex-direction:column}
.cat-list-heading{padding:14px 16px 10px;font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.4px}
.cat-search-wrap{padding:0 14px 12px}
.cat-search-inner{position:relative}
.cat-search-inner i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:12px}
.cat-search-input{width:100%;padding:8px 10px 8px 32px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;color:#1e2d3d;outline:none;box-sizing:border-box;background:#f9fafb;transition:border-color .15s}
.cat-search-input:focus{border-color:#2563eb;background:#fff}
.cat-list-scroll{flex:1;overflow-y:auto;max-height:520px}
.cat-list-scroll::-webkit-scrollbar{width:4px}
.cat-list-scroll::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px}
.cat-item{padding:13px 16px;border-bottom:1px solid #f1f4f8;cursor:pointer;display:flex;align-items:center;justify-content:space-between;transition:background .12s}
.cat-item:last-child{border-bottom:none}
.cat-item:hover{background:#f8fafc}
.cat-item.active{background:#eff6ff;border-left:3px solid #2563eb;padding-left:13px}
.cat-item-name{font-size:13.5px;font-weight:500;color:#1e2d3d}
.cat-item.active .cat-item-name{color:#2563eb;font-weight:700}
.cat-item-chevron{font-size:11px;color:#9ca3af}
.cat-detail-col{flex:1;padding:22px 32px;display:flex;flex-direction:column}
.cat-detail-heading{font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e8ecf0;padding-bottom:12px;margin-bottom:22px}
.cat-detail-title-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
.cat-detail-title{font-size:15px;font-weight:800;color:#1e2d3d;text-transform:uppercase;letter-spacing:.3px}
.btn-edit-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:600;padding:0}
.btn-edit-link:hover{text-decoration:underline}
.cat-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px 36px;margin-bottom:6px}
.cat-field-grid.single{grid-template-columns:1fr}
.cat-field label{display:block;font-size:12.5px;color:#374151;margin-bottom:8px;font-weight:400}
.cat-field label .req{color:#ef4444;margin-right:2px}
.cat-field-value{font-size:13.5px;color:#1e2d3d;padding-bottom:9px;border-bottom:1px solid #e2e8f0;min-height:28px}
.cat-input{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s}
.cat-input::placeholder{color:#c4c9d4}
.cat-input:focus{border-color:#2563eb}
.cat-form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:auto;padding-top:28px}
.btn-cancel{padding:9px 26px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:13.5px;color:#374151;cursor:pointer;font-weight:600;transition:background .14s}
.btn-cancel:hover{background:#f1f5f9}
.btn-save{padding:9px 26px;background:#2563eb;border:none;border-radius:6px;font-size:13.5px;color:#fff;cursor:pointer;font-weight:600;transition:background .14s}
.btn-save:hover{background:#1d4ed8}
.cat-empty{flex:1;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13.5px}
.toast-box{position:fixed;top:18px;right:18px;z-index:99999;min-width:280px;padding:13px 16px;border-radius:8px;color:#fff;font-size:13.5px;font-weight:600;box-shadow:0 10px 30px rgba(0,0,0,.18);display:flex;gap:10px;align-items:center}
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
<div class="cat-wrapper">

  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= esc($k) ?>" class="cfg-tab <?= $k==='Organization'?'active':'' ?>">
      <?= esc($l) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="cat-inner">

    <div class="cat-topbar">
      <nav class="cat-breadcrumb">
        <a href="org_masters.php">Organization Masters</a>
        <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span>Categories</span>
      </nav>

      <?php if ($mode !== 'add'): ?>
      <button class="btn-add-cat" onclick="setMode('add')">
        <i class="fa-solid fa-plus"></i> Add Category
      </button>
      <?php endif; ?>
    </div>

    <div class="cat-panel">

      <div class="cat-list-col">
        <div class="cat-list-heading">List of Categories</div>

        <div class="cat-search-wrap">
          <form method="GET" style="display:contents" id="searchForm">
            <input type="hidden" name="mode" value="view">
            <div class="cat-search-inner">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" name="q" class="cat-search-input"
                     placeholder="Search items"
                     value="<?= esc($search) ?>">
            </div>
          </form>
        </div>

        <div class="cat-list-scroll">
          <?php foreach ($cats as $cat): ?>
            <div class="cat-item <?= ((int)$cat['id'] === $active_id && $mode !== 'add') ? 'active' : '' ?>"
                 onclick="selectCat(<?= (int)$cat['id'] ?>)">
              <span class="cat-item-name"><?= esc($cat['cat_name']) ?></span>
              <i class="fa-solid <?= ((int)$cat['id'] === $active_id && $mode !== 'add') ? 'fa-chevron-right' : 'fa-chevron-down' ?> cat-item-chevron"></i>
            </div>
          <?php endforeach; ?>

          <?php if (empty($cats)): ?>
            <div style="padding:22px 16px;color:#9ca3af;font-size:13px">No categories found.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="cat-detail-col">
        <div class="cat-detail-heading">Categories Details</div>

        <?php if ($mode === 'add'): ?>

        <div class="cat-detail-title" style="margin-bottom:26px">ADD CATEGORY</div>

        <form method="POST">
          <input type="hidden" name="action" value="add_cat">

          <div class="cat-field-grid" style="margin-bottom:22px">
            <div class="cat-field">
              <label><span class="req">*</span> Code Name</label>
              <input type="text" name="code_name" class="cat-input" placeholder="Code Name" required>
            </div>

            <div class="cat-field">
              <label><span class="req">*</span> Category Name</label>
              <input type="text" name="cat_name" class="cat-input" placeholder="Category Name" required>
            </div>
          </div>

          <div class="cat-field-grid single">
            <div class="cat-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="cat-input" placeholder="Remarks">
            </div>
          </div>

          <div class="cat-form-actions">
            <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
            <button type="submit" class="btn-save">Add</button>
          </div>
        </form>

        <?php elseif ($mode === 'edit' && $active_cat): ?>

        <div class="cat-detail-title" style="margin-bottom:26px">
          EDIT — <?= esc($active_cat['cat_name']) ?>
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="edit_cat">
          <input type="hidden" name="edit_id" value="<?= (int)$active_cat['id'] ?>">

          <div class="cat-field-grid" style="margin-bottom:22px">
            <div class="cat-field">
              <label><span class="req">*</span> Code Name</label>
              <input type="text" name="code_name" class="cat-input"
                     value="<?= esc($active_cat['code_name']) ?>" required>
            </div>

            <div class="cat-field">
              <label><span class="req">*</span> Category Name</label>
              <input type="text" name="cat_name" class="cat-input"
                     value="<?= esc($active_cat['cat_name']) ?>" required>
            </div>
          </div>

          <div class="cat-field-grid single">
            <div class="cat-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="cat-input"
                     value="<?= esc($active_cat['remarks'] ?? '') ?>">
            </div>
          </div>

          <div class="cat-form-actions">
            <button type="button" class="btn-cancel"
                    onclick="window.location.href='?id=<?= (int)$active_cat['id'] ?>&mode=view'">
              Cancel
            </button>
            <button type="submit" class="btn-save">Update</button>
          </div>
        </form>

        <?php elseif ($active_cat): ?>

        <div class="cat-detail-title-bar">
          <div class="cat-detail-title"><?= esc($active_cat['cat_name']) ?></div>
          <button class="btn-edit-link"
                  onclick="window.location.href='?id=<?= (int)$active_cat['id'] ?>&mode=edit'">
            <i class="fa-regular fa-pen-to-square"></i> Edit Details
          </button>
        </div>

        <div class="cat-field-grid" style="margin-bottom:22px">
          <div class="cat-field">
            <label>Code Name</label>
            <div class="cat-field-value"><?= esc($active_cat['code_name']) ?></div>
          </div>

          <div class="cat-field">
            <label>Category Name</label>
            <div class="cat-field-value"><?= esc($active_cat['cat_name']) ?></div>
          </div>
        </div>

        <div class="cat-field-grid single">
          <div class="cat-field">
            <label>Remarks</label>
            <div class="cat-field-value"><?= esc($active_cat['remarks'] ?? '') ?>&nbsp;</div>
          </div>
        </div>

        <?php else: ?>

        <div class="cat-empty">Select a category to view details.</div>

        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
</div>

<script>
function selectCat(id) {
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
const searchInput = document.querySelector('.cat-search-input');

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