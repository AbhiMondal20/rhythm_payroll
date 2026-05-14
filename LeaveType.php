<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Leave Types';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

function e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ── DB TABLE ── */
$conn->query("
CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leave_name VARCHAR(120) NOT NULL,
    leave_code VARCHAR(20) NOT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ── DUMMY DATA IF EMPTY ── */
$count = 0;
$resCount = $conn->query("SELECT COUNT(*) AS total FROM leave_types");
if ($resCount) {
    $count = (int)($resCount->fetch_assoc()['total'] ?? 0);
}

if ($count === 0) {
    $dummyLeaves = [
        ['Loss of Pay', 'LOP', ''],
        ['Maternity Leave', 'ML', ''],
        ['Paternity Leave', 'PL', ''],
        ['Compensatory Leave', 'CL', ''],
        ['Casual Leave/Sick Leave', 'CSL', '']
    ];

    $stmt = $conn->prepare("
        INSERT INTO leave_types (leave_name, leave_code, remarks)
        VALUES (?, ?, ?)
    ");

    if ($stmt) {
        foreach ($dummyLeaves as $lt) {
            $stmt->bind_param("sss", $lt[0], $lt[1], $lt[2]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

/* ── State ── */
$active_id  = (int)($_GET['id'] ?? 0);
$mode       = $_GET['mode'] ?? 'view';
$flash      = '';
$flash_type = 'success';

/* ── POST handlers ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_leave') {
        $name    = trim($_POST['leave_name'] ?? '');
        $code    = trim($_POST['leave_code'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($name === '' || $code === '') {
            $flash = 'Leave Name and Leave Code are required.';
            $flash_type = 'error';
            $mode = 'add';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO leave_types (leave_name, leave_code, remarks)
                VALUES (?, ?, ?)
            ");

            if (!$stmt) {
                $flash = 'Save failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'add';
            } else {
                $stmt->bind_param("sss", $name, $code, $remarks);

                if ($stmt->execute()) {
                    $active_id = (int)$stmt->insert_id;
                    $flash = 'Leave type "' . $name . '" added successfully.';
                    $flash_type = 'success';
                    $mode = 'view';
                } else {
                    $flash = 'Save failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'add';
                }

                $stmt->close();
            }
        }
    }

    if ($action === 'save_leave') {
        $id      = (int)($_POST['leave_id'] ?? 0);
        $name    = trim($_POST['leave_name'] ?? '');
        $code    = trim($_POST['leave_code'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($id <= 0 || $name === '' || $code === '') {
            $flash = 'Leave Name and Leave Code are required.';
            $flash_type = 'error';
            $mode = 'edit';
            $active_id = $id;
        } else {
            $stmt = $conn->prepare("
                UPDATE leave_types
                SET leave_name = ?,
                    leave_code = ?,
                    remarks = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            if (!$stmt) {
                $flash = 'Update failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'edit';
                $active_id = $id;
            } else {
                $stmt->bind_param("sssi", $name, $code, $remarks, $id);

                if ($stmt->execute()) {
                    $flash = 'Leave type updated successfully.';
                    $flash_type = 'success';
                    $active_id = $id;
                    $mode = 'view';
                } else {
                    $flash = 'Update failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'edit';
                    $active_id = $id;
                }

                $stmt->close();
            }
        }
    }

    if ($action === 'delete_leave') {
        $id = (int)($_POST['leave_id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM leave_types WHERE id = ?");

            if (!$stmt) {
                $flash = 'Delete failed: ' . $conn->error;
                $flash_type = 'error';
            } else {
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $flash = 'Leave type deleted.';
                    $flash_type = 'success';
                    $active_id = 0;
                    $mode = 'view';
                } else {
                    $flash = 'Delete failed: ' . $stmt->error;
                    $flash_type = 'error';
                }

                $stmt->close();
            }
        }
    }
}

/* ── Fetch leave types from DB ── */
$leave_types = [];

$res = $conn->query("
    SELECT id, leave_name, leave_code, remarks, created_at, updated_at
    FROM leave_types
    ORDER BY leave_name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $leave_types[] = $row;
    }
}

/* default active */
if ($active_id === 0 && $mode === 'view' && count($leave_types)) {
    $active_id = (int)$leave_types[0]['id'];
}

/* active leave */
$active_lt = null;

if ($active_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, leave_name, leave_code, remarks, created_at, updated_at
        FROM leave_types
        WHERE id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $active_id);
        $stmt->execute();
        $resActive = $stmt->get_result();

        if ($resActive && $resActive->num_rows > 0) {
            $active_lt = $resActive->fetch_assoc();
            $active_lt['id'] = (int)$active_lt['id'];
        }

        $stmt->close();
    }
}

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
.lt-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.lt-inner{padding:18px 24px}

/* topbar */
.lt-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.lt-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555}
.lt-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.lt-breadcrumb a:hover{text-decoration:underline}
.lt-breadcrumb .sep{color:#bbb;font-size:11px}
.btn-add-lt{display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .16s}
.btn-add-lt:hover{background:#1d4ed8}

/* ── Sub-header ── */
.lt-sub-header{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #e8ecf0}
.lt-sub-left{padding:10px 16px;font-size:12px;color:#6b7280;font-weight:500}
.lt-sub-right{padding:10px 16px;font-size:12px;color:#6b7280;font-weight:500}

/* ── Split panel ── */
.lt-panel{display:flex;background:#fff;border:1px solid #e8ecf0;border-radius:10px;overflow:hidden;min-height:420px}

/* Left list */
.lt-list-col{width:36%;min-width:220px;border-right:1px solid #e8ecf0;display:flex;flex-direction:column}
.lt-list-scroll{flex:1;overflow-y:auto;max-height:580px}
.lt-list-scroll::-webkit-scrollbar{width:4px}
.lt-list-scroll::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px}

.lt-item{padding:13px 16px;border-bottom:1px solid #f1f4f8;cursor:pointer;display:flex;align-items:center;justify-content:space-between;transition:background .12s}
.lt-item:last-child{border-bottom:none}
.lt-item:hover{background:#f8fafc}
.lt-item.active{background:#eff6ff;border-left:3px solid #2563eb;padding-left:13px}
.lt-item-name{font-size:13.5px;font-weight:500;color:#1e2d3d}
.lt-item.active .lt-item-name{color:#2563eb;font-weight:700}
.lt-item-chevron{font-size:11px;color:#9ca3af}

/* Right detail */
.lt-detail-col{flex:1;padding:22px 28px;display:flex;flex-direction:column}

.lt-detail-title-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.lt-detail-title{font-size:15px;font-weight:800;color:#1e2d3d;text-transform:uppercase;letter-spacing:.3px}

.btn-edit-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:600;padding:0}
.btn-edit-link:hover{text-decoration:underline}

/* Field grid */
.lt-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 36px;margin-bottom:18px}
.lt-field-grid.single{grid-template-columns:1fr}
.lt-field label{display:block;font-size:12.5px;color:#374151;margin-bottom:8px;font-weight:400}
.lt-field label .req{color:#ef4444;margin-right:2px}
.lt-field-value{font-size:13.5px;color:#1e2d3d;padding-bottom:9px;border-bottom:1px solid #e2e8f0;min-height:26px}

/* Inputs */
.lt-input{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s}
.lt-input::placeholder{color:#c4c9d4}
.lt-input:focus{border-color:#2563eb}

/* Form actions */
.lt-form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:auto;padding-top:22px}
.btn-lt-delete{padding:9px 22px;border:1.5px solid #ef4444;background:#fff;border-radius:6px;font-size:13.5px;color:#ef4444;cursor:pointer;font-weight:600;transition:background .14s}
.btn-lt-delete:hover{background:#fee2e2}
.btn-lt-cancel{padding:9px 22px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:13.5px;color:#374151;cursor:pointer;font-weight:600;transition:background .14s}
.btn-lt-cancel:hover{background:#f1f5f9}
.btn-lt-save{padding:9px 22px;background:#2563eb;border:none;border-radius:6px;font-size:13.5px;color:#fff;cursor:pointer;font-weight:600;transition:background .14s}
.btn-lt-save:hover{background:#1d4ed8}

/* ── Toast ── */
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

<div class="toast-container" id="toastContainer"></div>

<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">

    <div class="cfg-tabs">
        <?php foreach ([
            'AccountInfo' => 'Account Info',
            'Organization'=> 'Organization',
            'Payroll'     => 'Payroll',
            'Attendance'  => 'Attendance',
            'Leave'       => 'Leave',
            'Training'    => 'Training',
            'Others'      => 'Others',
        ] as $k => $l): ?>
        <a href="configuration#<?= e($k) ?>"
           class="cfg-tab <?= $k === 'Leave' ? 'active' : '' ?>">
            <?= e($l) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="lt-wrapper">
        <div class="lt-inner">

            <div class="lt-topbar">
                <nav class="lt-breadcrumb">
                    <a href="leave_config.php">Leave</a>
                    <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
                    <span>Leave Types</span>
                </nav>

                <?php if ($mode !== 'add'): ?>
                <button class="btn-add-lt" onclick="setMode('add')">
                    <i class="fa-solid fa-plus"></i> Add New Leave
                </button>
                <?php endif; ?>
            </div>

            <div class="lt-sub-header">
                <div class="lt-sub-left">
                    <?= $mode === 'add' ? 'Add new Leave' : 'List of Leave Types' ?>
                </div>
                <div class="lt-sub-right">Details of Leave</div>
            </div>

            <div class="lt-panel">

                <div class="lt-list-col">
                    <div class="lt-list-scroll">
                        <?php foreach ($leave_types as $lt): ?>
                        <div class="lt-item <?= ((int)$lt['id'] === (int)$active_id && $mode !== 'add') ? 'active' : '' ?>"
                             onclick="selectLT(<?= (int)$lt['id'] ?>)">
                            <span class="lt-item-name"><?= e($lt['leave_name']) ?></span>
                            <i class="fa-solid <?= ((int)$lt['id'] === (int)$active_id && $mode !== 'add') ? 'fa-chevron-right' : 'fa-chevron-down' ?> lt-item-chevron"></i>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($leave_types)): ?>
                        <div style="padding:22px 16px;color:#9ca3af;font-size:13px">
                            No leave types found.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lt-detail-col">

                    <?php if ($mode === 'add'): ?>

                    <div class="lt-detail-title" style="margin-bottom:22px">NEW LEAVE</div>

                    <form method="POST">
                        <input type="hidden" name="action" value="add_leave">

                        <div class="lt-field-grid" style="margin-bottom:18px">
                            <div class="lt-field">
                                <label><span class="req">* </span>Leave Name</label>
                                <input type="text" name="leave_name" class="lt-input"
                                       placeholder="Leave"
                                       value="<?= e($_POST['leave_name'] ?? '') ?>" required>
                            </div>

                            <div class="lt-field">
                                <label><span class="req">* </span>Leave Code</label>
                                <input type="text" name="leave_code" class="lt-input"
                                       placeholder="CL"
                                       value="<?= e($_POST['leave_code'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="lt-field-grid single" style="margin-bottom:10px">
                            <div class="lt-field">
                                <label>Remarks</label>
                                <input type="text" name="remarks" class="lt-input"
                                       value="<?= e($_POST['remarks'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="lt-form-actions">
                            <button type="button" class="btn-lt-cancel" onclick="setMode('view')">Cancel</button>
                            <button type="submit" class="btn-lt-save">Add</button>
                        </div>
                    </form>

                    <?php elseif ($mode === 'edit' && $active_lt): ?>

                    <div class="lt-detail-title" style="margin-bottom:22px">
                        <?= e(strtoupper($active_lt['leave_name'])) ?>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="save_leave">
                        <input type="hidden" name="leave_id" value="<?= (int)$active_lt['id'] ?>">

                        <div class="lt-field-grid" style="margin-bottom:18px">
                            <div class="lt-field">
                                <label><span class="req">* </span>Leave Name</label>
                                <input type="text" name="leave_name" class="lt-input"
                                       value="<?= e($_POST['leave_name'] ?? $active_lt['leave_name']) ?>"
                                       required>
                            </div>

                            <div class="lt-field">
                                <label><span class="req">* </span>Leave Code</label>
                                <input type="text" name="leave_code" class="lt-input"
                                       value="<?= e($_POST['leave_code'] ?? $active_lt['leave_code']) ?>"
                                       required>
                            </div>
                        </div>

                        <div class="lt-field-grid single" style="margin-bottom:10px">
                            <div class="lt-field">
                                <label>Remarks</label>
                                <input type="text" name="remarks" class="lt-input"
                                       value="<?= e($_POST['remarks'] ?? $active_lt['remarks']) ?>">
                            </div>
                        </div>

                        <div class="lt-form-actions">
                            <button type="button" class="btn-lt-delete"
                                    onclick="deleteLT(<?= (int)$active_lt['id'] ?>)">Delete</button>

                            <button type="button" class="btn-lt-cancel"
                                    onclick="window.location.href='?id=<?= (int)$active_lt['id'] ?>&mode=view'">
                                Cancel
                            </button>

                            <button type="submit" class="btn-lt-save">Save</button>
                        </div>
                    </form>

                    <?php elseif ($active_lt): ?>

                    <div class="lt-detail-title-row">
                        <div class="lt-detail-title">
                            <?= e(strtoupper($active_lt['leave_name'])) ?>
                        </div>

                        <button class="btn-edit-link"
                                onclick="window.location.href='?id=<?= (int)$active_lt['id'] ?>&mode=edit'">
                            <i class="fa-regular fa-pen-to-square"></i> Edit Details
                        </button>
                    </div>

                    <div class="lt-field-grid" style="margin-bottom:18px">
                        <div class="lt-field">
                            <label>Leave Name</label>
                            <div class="lt-field-value"><?= e($active_lt['leave_name']) ?></div>
                        </div>

                        <div class="lt-field">
                            <label>Leave Code</label>
                            <div class="lt-field-value"><?= e($active_lt['leave_code']) ?></div>
                        </div>
                    </div>

                    <div class="lt-field-grid single">
                        <div class="lt-field">
                            <label>Remarks</label>
                            <div class="lt-field-value"><?= e($active_lt['remarks']) ?>&nbsp;</div>
                        </div>
                    </div>

                    <?php else: ?>

                    <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13.5px">
                        Select a leave type to view details.
                    </div>

                    <?php endif; ?>

                </div>
            </div>

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
function selectLT(id) {
    const url = new URL(window.location.href);
    url.searchParams.set('id', id);
    url.searchParams.set('mode', 'view');
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

function deleteLT(id) {
    if (typeof Swal === 'undefined') {
        if (!confirm('Delete this leave type?')) {
            return;
        }

        const f = document.createElement('form');
        f.method = 'POST';
        f.innerHTML = `
            <input type="hidden" name="action" value="delete_leave">
            <input type="hidden" name="leave_id" value="${id}">
        `;
        document.body.appendChild(f);
        f.submit();
        return;
    }

    Swal.fire({
        title: 'Delete this leave type?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.innerHTML = `
                <input type="hidden" name="action" value="delete_leave">
                <input type="hidden" name="leave_id" value="${id}">
            `;
            document.body.appendChild(f);
            f.submit();
        }
    });
}

const _icons = {
    success: 'fa-circle-check',
    error: 'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info: 'fa-circle-info'
};

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}

function showToast(msg, type = 'success', dur = 3500) {
    const c = document.getElementById('toastContainer');

    if (!c) {
        alert(msg);
        return;
    }

    const t = document.createElement('div');
    t.className = 'toast ' + type;

    t.innerHTML = `
        <i class="fa-solid ${_icons[type] || _icons.info}"></i>
        <span>${escapeHtml(msg)}</span>
        <button type="button" class="toast-close" onclick="rmToast(this.parentElement)">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;

    c.appendChild(t);
    setTimeout(() => rmToast(t), dur);
}

function rmToast(el) {
    if (!el || !el.parentElement) {
        return;
    }

    el.style.animation = 'toastOut .25s ease forwards';

    setTimeout(() => {
        if (el.parentElement) {
            el.remove();
        }
    }, 260);
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>