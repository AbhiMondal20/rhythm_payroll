<?php
require_once '../includes/db_conn.php';
require_once '../includes/config.php';

$page_title = 'Add Module';
ob_start();

if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection not found.");
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

function makeModuleKey($name) {
    $key = strtolower(trim((string)$name));
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    $key = trim($key, '_');
    return substr($key, 0, 80);
}

/* EDIT LOAD */
$id   = (int)($_GET['id'] ?? 0);
$edit = $id > 0;

$formData = [
    'module_key'  => '',
    'module_name' => '',
    'status'      => 'active',
];

if ($edit) {
    $stmt = $master->prepare("
        SELECT id, module_key, module_name, status
        FROM modules
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        die("Edit prepare failed: " . $master->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        die("Module not found.");
    }

    $formData = array_merge($formData, $row);
}

/* POST */
$msg  = "";
$swal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $module_name = trim($_POST['module_name'] ?? '');
    $module_key  = strtolower(trim($_POST['module_key'] ?? ''));
    $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($module_key === '' && $module_name !== '') {
        $module_key = makeModuleKey($module_name);
    }

    $formData = [
        'module_key'  => $module_key,
        'module_name' => $module_name,
        'status'      => $status,
    ];

    if ($module_name === '' || $module_key === '') {
        $msg = "Module name and module key are required.";
    } elseif (!preg_match('/^[a-z0-9_]+$/', $module_key)) {
        $msg = "Module key must contain only lowercase letters, numbers and underscores.";
    }

    /* duplicate check */
    if ($msg === '') {
        if ($edit) {
            $dup = $master->prepare("
                SELECT id 
                FROM modules 
                WHERE module_key = ? AND id != ?
                LIMIT 1
            ");
            $dup->bind_param("si", $module_key, $id);
        } else {
            $dup = $master->prepare("
                SELECT id 
                FROM modules 
                WHERE module_key = ?
                LIMIT 1
            ");
            $dup->bind_param("s", $module_key);
        }

        if (!$dup) {
            $msg = "Duplicate check failed: " . $master->error;
        } else {
            $dup->execute();
            $dup->store_result();

            if ($dup->num_rows > 0) {
                $msg = "This module key already exists.";
            }

            $dup->close();
        }
    }

    /* insert/update */
    if ($msg === '') {
        if ($edit) {
            $stmt = $master->prepare("
                UPDATE modules
                SET module_key = ?,
                    module_name = ?,
                    status = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                $msg = "Update prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "sssi",
                    $module_key,
                    $module_name,
                    $status,
                    $id
                );
            }
        } else {
            $stmt = $master->prepare("
                INSERT INTO modules
                (module_key, module_name, status, created_at)
                VALUES (?, ?, ?, NOW())
            ");

            if (!$stmt) {
                $msg = "Insert prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "sss",
                    $module_key,
                    $module_name,
                    $status
                );
            }
        }

        if ($msg === '') {
            if (!$stmt->execute()) {
                $msg = "Save failed: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    $swal = ($msg === '')
        ? [
            'icon' => 'success',
            'title' => $edit ? 'Updated!' : 'Created!',
            'text' => $edit ? 'Module updated successfully.' : 'Module created successfully.',
            'redirect' => 'ModulesKeys'
        ]
        : [
            'icon' => 'error',
            'title' => 'Error',
            'text' => $msg,
            'redirect' => ''
        ];
}
?>

<link rel="stylesheet" href="../includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.cd-bc {
    display:flex;align-items:center;gap:8px;
    font-size:13px;color:#6B7280;flex-wrap:wrap;
}
.cd-bc a { color:#6B7280;text-decoration:none;transition:color .15s; }
.cd-bc a:hover { color:#2563EB; }
.cd-bc .sep { color:#D1D5DB; }
.cd-bc strong { color:#111827;font-weight:600; }

.cd-card {
    background:#fff;border:1px solid #E5E7EB;border-radius:14px;
    overflow:hidden;box-shadow:0 2px 16px rgba(15,23,42,.05);
}

.cd-card-head {
    padding:18px 24px;border-bottom:1px solid #E5E7EB;
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:10px;
}
.cd-head-title {
    font-size:16px;font-weight:700;color:#111827;display:flex;align-items:center;gap:10px;
}
.cd-head-icon {
    width:36px;height:36px;border-radius:9px;background:#EFF6FF;
    display:flex;align-items:center;justify-content:center;
    font-size:15px;color:#2563EB;flex-shrink:0;
}
.cd-status-badge {
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;
}

.cd-section {
    border:1px solid #E5E7EB;border-radius:10px;overflow:hidden;margin-bottom:18px;
}
.cd-section-head {
    padding:11px 18px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;
    display:flex;align-items:center;gap:9px;
}
.cd-section-head i {
    font-size:13px;color:#6B7280;width:16px;text-align:center;
}
.cd-section-head span {
    font-size:12.5px;font-weight:700;color:#374151;letter-spacing:.3px;text-transform:uppercase;
}
.cd-section-body {
    padding:20px 20px 16px;
}

.cd-grid {
    display:grid;gap:20px 24px;
}
.cd-grid.c4 {
    grid-template-columns:repeat(4,1fr);
}
.cd-grid.c2 {
    grid-template-columns:repeat(2,1fr);
}
.cd-span2 {
    grid-column:span 2;
}

.cd-fg {
    display:flex;flex-direction:column;gap:5px;
}
.cd-fg label {
    font-size:11.5px;font-weight:700;color:#6B7280;
    letter-spacing:.4px;text-transform:uppercase;
}
.cd-fg label .req {
    color:#DC2626;margin-left:2px;
}
.cd-fg input[type=text],
.cd-fg select {
    padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;
    font-size:13.5px;font-family:inherit;color:#111827;
    outline:none;background:#fff;transition:.15s;width:100%;
}
.cd-fg input:focus,
.cd-fg select:focus {
    border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.08);
}
.cd-fg input.is-invalid,
.cd-fg select.is-invalid {
    border-color:#DC2626;background:#FFF5F5;
}
.cd-fg .cd-hint {
    font-size:11px;color:#9CA3AF;margin-top:3px;
}

.cd-footer {
    padding:18px 24px;border-top:1px solid #E5E7EB;
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:12px;background:#FAFAFA;
}
.cd-footer-note {
    font-size:12px;color:#9CA3AF;
}
.cd-footer-btns {
    display:flex;gap:10px;flex-wrap:wrap;
}

.cd-cancel-btn {
    padding:9px 24px;background:#fff;color:#374151;
    border:1.5px solid #D1D5DB;border-radius:8px;
    font-size:13.5px;font-weight:500;cursor:pointer;
    font-family:inherit;transition:.15s;text-decoration:none;
    display:inline-flex;align-items:center;gap:6px;
}
.cd-cancel-btn:hover {
    border-color:#374151;
}

.cd-save-btn {
    padding:9px 28px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:13.5px;font-weight:700;cursor:pointer;
    font-family:inherit;transition:background .15s;
    display:inline-flex;align-items:center;gap:7px;
}
.cd-save-btn:hover {
    background:#1D4ED8;
}

.cd-alert {
    display:flex;align-items:center;gap:10px;padding:12px 16px;
    border-radius:8px;font-size:13px;font-weight:500;margin-bottom:16px;
}
.cd-alert.error {
    background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;
}
.cd-alert.success {
    background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;
}
.cd-alert i {
    font-size:14px;flex-shrink:0;
}

@media(max-width:900px){
    .cd-grid.c4,.cd-grid.c2 {
        grid-template-columns:1fr;
    }
    .cd-span2 {
        grid-column:span 1;
    }
    .cd-footer {
        flex-direction:column;align-items:stretch;
    }
    .cd-footer-btns {
        flex-direction:column;
    }
    .cd-save-btn,.cd-cancel-btn {
        width:100%;justify-content:center;
    }
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:#111827;margin-bottom:4px">
            <?= $edit ? 'Edit Module' : 'Create Module' ?>
        </h1>
        <div class="cd-bc">
            <a href="ModulesKeys">Modules</a>
            <span class="sep">›</span>
            <strong><?= $edit ? 'Edit Module' : 'New Module' ?></strong>
        </div>
    </div>

    <?php if($edit): ?>
        <span class="cd-status-badge"
              style="background:<?= $formData['status']==='active'?'#D1FAE5':'#F3F4F6' ?>;
                     color:<?= $formData['status']==='active'?'#065F46':'#6B7280' ?>">
            <i class="fa-solid fa-circle" style="font-size:7px"></i>
            <?= ucfirst(e($formData['status'])) ?>
        </span>
    <?php endif; ?>
</div>

<?php if(!empty($msg)): ?>
    <div class="cd-alert error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= e($msg) ?>
    </div>
<?php endif; ?>

<div class="cd-card">
    <div class="cd-card-head">
        <div class="cd-head-title">
            <div class="cd-head-icon">
                <i class="fa-solid fa-puzzle-piece"></i>
            </div>
            <?= $edit ? 'Module Information' : 'New Module Information' ?>
        </div>

        <div style="font-size:12px;color:#9CA3AF">
            <i class="fa-solid fa-circle-info" style="margin-right:4px"></i>
            Fields marked <span style="color:#DC2626;font-weight:600">*</span> are required
        </div>
    </div>

    <form method="POST" autocomplete="off" id="moduleForm" novalidate>
        <input type="hidden" name="save_module" value="1">

        <div style="padding:24px">
            <div class="cd-section">
                <div class="cd-section-head">
                    <i class="fa-solid fa-cubes"></i>
                    <span>Module Details</span>
                </div>

                <div class="cd-section-body">
                    <div class="cd-grid c4">

                        <div class="cd-fg cd-span2">
                            <label>Module Name <span class="req">*</span></label>
                            <input type="text"
                                   name="module_name"
                                   id="moduleName"
                                   value="<?= e($formData['module_name']) ?>"
                                   placeholder="e.g. Payroll"
                                   required>
                        </div>

                        <div class="cd-fg">
                            <label>Module Key <span class="req">*</span></label>
                            <input type="text"
                                   name="module_key"
                                   id="moduleKey"
                                   value="<?= e($formData['module_key']) ?>"
                                   placeholder="e.g. payroll"
                                   oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'')"
                                   required>
                            <span class="cd-hint">Lowercase, numbers and underscore only</span>
                        </div>

                        <div class="cd-fg">
                            <label>Status</label>
                            <select name="status">
                                <option value="active" <?= $formData['status']==='active'?'selected':'' ?>>Active</option>
                                <option value="inactive" <?= $formData['status']==='inactive'?'selected':'' ?>>Inactive</option>
                            </select>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="cd-footer">
            <span class="cd-footer-note">
                <?= $edit ? 'Update module details carefully.' : 'Create a module key for database mapping.' ?>
            </span>

            <div class="cd-footer-btns">
                <a href="ModalKeys" class="cd-cancel-btn">
                    <i class="fa-solid fa-arrow-left" style="font-size:12px"></i>
                    Cancel
                </a>

                <button type="submit" class="cd-save-btn" id="saveBtn">
                    <i class="fa-solid fa-<?= $edit ? 'floppy-disk' : 'circle-plus' ?>"></i>
                    <?= $edit ? 'Update Module' : 'Create Module' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('moduleName').addEventListener('input', function() {
    var keyInput = document.getElementById('moduleKey');

    if (!keyInput.value || keyInput.dataset.manual !== '1') {
        keyInput.value = this.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '')
            .slice(0, 80);
    }
});

document.getElementById('moduleKey').addEventListener('input', function() {
    this.dataset.manual = '1';
});

document.getElementById('moduleForm').addEventListener('submit', function(e) {
    var required = this.querySelectorAll('[required]');
    var ok = true;

    required.forEach(function(el) {
        el.classList.remove('is-invalid');

        if (!el.value.trim()) {
            el.classList.add('is-invalid');
            ok = false;
        }
    });

    if (!ok) {
        e.preventDefault();

        Swal.fire({
            icon:'warning',
            title:'Required fields',
            text:'Please fill in all required fields.',
            confirmButtonColor:'#2563EB'
        });

        return false;
    }

    var saveBtn = document.getElementById('saveBtn');
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
});
</script>

<?php if(!empty($swal)): ?>
<script>
Swal.fire({
    icon: "<?= e($swal['icon']) ?>",
    title: "<?= e($swal['title']) ?>",
    text: "<?= e($swal['text']) ?>",
    confirmButtonColor: "#2563eb",
    customClass: { popup:'swal-rounded' }
}).then(function() {
    <?php if(!empty($swal['redirect'])): ?>
    window.location.href = "<?= e($swal['redirect']) ?>";
    <?php endif; ?>
});
</script>
<style>
.swal-rounded { border-radius:14px!important; }
</style>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
include 'header.php';
echo $page_content;
include 'footer.php';
?>
<script src="../includes/assets/scripts.js"></script>