<?php
require_once '../includes/db_conn.php';
require_once '../includes/config.php';

$page_title = 'Add License';
ob_start();

if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection not found.");
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

function generateLicenseKey() {
    return strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
}

$id = (int)($_GET['id'] ?? 0);
$edit = $id > 0;

$formData = [
    'client_id' => '',
    'module_key' => '',
    'license_key' => '',
    'license_type' => 'trial',
    'start_date' => date('Y-m-d'),
    'expiry_date' => '',
    'max_users' => 1,
    'status' => 'active',
    'notes' => '',
];

if ($edit) {
    $stmt = $master->prepare("SELECT * FROM licenses WHERE id=? LIMIT 1");
    if (!$stmt) die("Edit prepare failed: " . $master->error);

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) die("License not found.");

    $formData = array_merge($formData, $row);
}

$clients = [];
$res = $master->query("SELECT id, client_name, client_code FROM clients WHERE status='active' ORDER BY client_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $clients[] = $r;
    }
}

$modules = [];
$res = $master->query("SELECT module_key, module_name FROM modules WHERE status='active' ORDER BY module_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $modules[] = $r;
    }
}

$msg = "";
$swal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)($_POST['client_id'] ?? 0);
    $module_key = strtolower(trim($_POST['module_key'] ?? ''));
    $license_key = strtoupper(trim($_POST['license_key'] ?? ''));
    $license_type = $_POST['license_type'] ?? 'trial';
    $start_date = trim($_POST['start_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $max_users = (int)($_POST['max_users'] ?? 1);
    $status = $_POST['status'] ?? 'active';
    $notes = trim($_POST['notes'] ?? '');

    if ($license_key === '') {
        $license_key = generateLicenseKey();
    }

    if (!in_array($license_type, ['trial','monthly','yearly','lifetime'], true)) {
        $license_type = 'trial';
    }

    if (!in_array($status, ['active','inactive','expired','cancelled'], true)) {
        $status = 'active';
    }

    if ($license_type === 'lifetime') {
        $expiry_date = '';
    }

    $expiryDb = $expiry_date !== '' ? $expiry_date : null;
    $startDb = $start_date !== '' ? $start_date : null;

    $formData = [
        'client_id' => $client_id,
        'module_key' => $module_key,
        'license_key' => $license_key,
        'license_type' => $license_type,
        'start_date' => $start_date,
        'expiry_date' => $expiry_date,
        'max_users' => $max_users,
        'status' => $status,
        'notes' => $notes,
    ];

    if ($client_id <= 0 || $module_key === '' || $license_key === '') {
        $msg = "Client, module and license key are required.";
    } elseif ($max_users <= 0) {
        $msg = "Max users must be greater than 0.";
    } elseif ($license_type !== 'lifetime' && $expiry_date === '') {
        $msg = "Expiry date is required except lifetime license.";
    }

    if ($msg === '') {
        if ($edit) {
            $dup = $master->prepare("SELECT id FROM licenses WHERE client_id=? AND module_key=? AND id!=? LIMIT 1");
            $dup->bind_param("isi", $client_id, $module_key, $id);
        } else {
            $dup = $master->prepare("SELECT id FROM licenses WHERE client_id=? AND module_key=? LIMIT 1");
            $dup->bind_param("is", $client_id, $module_key);
        }

        if (!$dup) {
            $msg = "Duplicate check failed: " . $master->error;
        } else {
            $dup->execute();
            $dup->store_result();

            if ($dup->num_rows > 0) {
                $msg = "This client already has a license for this module.";
            }

            $dup->close();
        }
    }

    if ($msg === '') {
        if ($edit) {
            $stmt = $master->prepare("
                UPDATE licenses
                SET client_id=?,
                    module_key=?,
                    license_key=?,
                    license_type=?,
                    start_date=?,
                    expiry_date=?,
                    max_users=?,
                    status=?,
                    notes=?,
                    updated_at=NOW()
                WHERE id=?
            ");

            if (!$stmt) {
                $msg = "Update prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "isssssissi",
                    $client_id,
                    $module_key,
                    $license_key,
                    $license_type,
                    $startDb,
                    $expiryDb,
                    $max_users,
                    $status,
                    $notes,
                    $id
                );
            }
        } else {
            $stmt = $master->prepare("
                INSERT INTO licenses
                (client_id, module_key, license_key, license_type, start_date, expiry_date, max_users, status, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$stmt) {
                $msg = "Insert prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "isssssiss",
                    $client_id,
                    $module_key,
                    $license_key,
                    $license_type,
                    $startDb,
                    $expiryDb,
                    $max_users,
                    $status,
                    $notes
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
            'text' => $edit ? 'License updated successfully.' : 'License created successfully.',
            'redirect' => 'LicenseKeys'
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
.cd-bc{display:flex;align-items:center;gap:8px;font-size:13px;color:#6B7280;flex-wrap:wrap}
.cd-bc a{color:#6B7280;text-decoration:none;transition:color .15s}
.cd-bc a:hover{color:#2563EB}
.cd-bc .sep{color:#D1D5DB}
.cd-bc strong{color:#111827;font-weight:600}
.cd-card{background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden;box-shadow:0 2px 16px rgba(15,23,42,.05)}
.cd-card-head{padding:18px 24px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.cd-head-title{font-size:16px;font-weight:700;color:#111827;display:flex;align-items:center;gap:10px}
.cd-head-icon{width:36px;height:36px;border-radius:9px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;font-size:15px;color:#2563EB;flex-shrink:0}
.cd-status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
.cd-section{border:1px solid #E5E7EB;border-radius:10px;overflow:hidden;margin-bottom:18px}
.cd-section-head{padding:11px 18px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;gap:9px}
.cd-section-head i{font-size:13px;color:#6B7280;width:16px;text-align:center}
.cd-section-head span{font-size:12.5px;font-weight:700;color:#374151;letter-spacing:.3px;text-transform:uppercase}
.cd-section-body{padding:20px 20px 16px}
.cd-grid{display:grid;gap:20px 24px}
.cd-grid.c4{grid-template-columns:repeat(4,1fr)}
.cd-grid.c2{grid-template-columns:repeat(2,1fr)}
.cd-span2{grid-column:span 2}
.cd-fg{display:flex;flex-direction:column;gap:5px}
.cd-fg label{font-size:11.5px;font-weight:700;color:#6B7280;letter-spacing:.4px;text-transform:uppercase}
.cd-fg label .req{color:#DC2626;margin-left:2px}
.cd-fg input,.cd-fg select,.cd-fg textarea{padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13.5px;font-family:inherit;color:#111827;outline:none;background:#fff;transition:.15s;width:100%}
.cd-fg input:focus,.cd-fg select:focus,.cd-fg textarea:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.08)}
.cd-fg input.is-invalid,.cd-fg select.is-invalid{border-color:#DC2626;background:#FFF5F5}
.cd-hint{font-size:11px;color:#9CA3AF;margin-top:3px}
.cd-footer{padding:18px 24px;border-top:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;background:#FAFAFA}
.cd-footer-note{font-size:12px;color:#9CA3AF}
.cd-footer-btns{display:flex;gap:10px;flex-wrap:wrap}
.cd-cancel-btn{padding:9px 24px;background:#fff;color:#374151;border:1.5px solid #D1D5DB;border-radius:8px;font-size:13.5px;font-weight:500;cursor:pointer;font-family:inherit;transition:.15s;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.cd-save-btn{padding:9px 28px;background:#2563EB;color:#fff;border:none;border-radius:8px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;display:inline-flex;align-items:center;gap:7px}
.cd-save-btn:hover{background:#1D4ED8}
.cd-test-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;font-size:13px;font-weight:600;color:#374151;cursor:pointer;font-family:inherit;transition:.15s}
.cd-alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:16px}
.cd-alert.error{background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5}
@media(max-width:900px){.cd-grid.c4,.cd-grid.c2{grid-template-columns:1fr}.cd-span2{grid-column:span 1}.cd-footer{flex-direction:column;align-items:stretch}.cd-footer-btns{flex-direction:column}.cd-save-btn,.cd-cancel-btn{width:100%;justify-content:center}}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:#111827;margin-bottom:4px">
            <?= $edit ? 'Edit License' : 'Create License' ?>
        </h1>
        <div class="cd-bc">
            <a href="Licenses">Licenses</a>
            <span class="sep">›</span>
            <strong><?= $edit ? 'Edit License' : 'New License' ?></strong>
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
                <i class="fa-solid fa-key"></i>
            </div>
            <?= $edit ? 'License Information' : 'New License Information' ?>
        </div>

        <div style="font-size:12px;color:#9CA3AF">
            <i class="fa-solid fa-circle-info" style="margin-right:4px"></i>
            Fields marked <span style="color:#DC2626;font-weight:600">*</span> are required
        </div>
    </div>

    <form method="POST" autocomplete="off" id="licenseForm" novalidate>
        <input type="hidden" name="save_license" value="1">

        <div style="padding:24px">
            <div class="cd-section">
                <div class="cd-section-head">
                    <i class="fa-solid fa-building"></i>
                    <span>License Details</span>
                </div>

                <div class="cd-section-body">
                    <div class="cd-grid c4">

                        <div class="cd-fg">
                            <label>Client <span class="req">*</span></label>
                            <select name="client_id" required>
                                <option value="">— Select Client —</option>
                                <?php foreach($clients as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= ((int)$formData['client_id']===(int)$c['id'])?'selected':'' ?>>
                                        <?= e($c['client_name']) ?> - <?= e($c['client_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>Module <span class="req">*</span></label>
                            <select name="module_key" required>
                                <option value="">— Select Module —</option>
                                <?php foreach($modules as $m): ?>
                                    <option value="<?= e($m['module_key']) ?>" <?= ($formData['module_key']===$m['module_key'])?'selected':'' ?>>
                                        <?= e($m['module_name']) ?> - <?= e($m['module_key']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>License Type <span class="req">*</span></label>
                            <select name="license_type" id="licenseType" required>
                                <option value="trial" <?= $formData['license_type']==='trial'?'selected':'' ?>>Trial</option>
                                <option value="monthly" <?= $formData['license_type']==='monthly'?'selected':'' ?>>Monthly</option>
                                <option value="yearly" <?= $formData['license_type']==='yearly'?'selected':'' ?>>Yearly</option>
                                <option value="lifetime" <?= $formData['license_type']==='lifetime'?'selected':'' ?>>Lifetime</option>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>Status</label>
                            <select name="status">
                                <option value="active" <?= $formData['status']==='active'?'selected':'' ?>>Active</option>
                                <option value="inactive" <?= $formData['status']==='inactive'?'selected':'' ?>>Inactive</option>
                                <option value="expired" <?= $formData['status']==='expired'?'selected':'' ?>>Expired</option>
                                <option value="cancelled" <?= $formData['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="cd-fg cd-span2">
                            <label>License Key</label>
                            <input type="text" name="license_key" id="licenseKey" value="<?= e($formData['license_key']) ?>" placeholder="Auto generated if blank">
                            <span class="cd-hint">Leave blank to auto generate license key</span>
                        </div>

                        <div class="cd-fg">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="<?= e($formData['start_date']) ?>">
                        </div>

                        <div class="cd-fg">
                            <label>Expiry Date</label>
                            <input type="date" name="expiry_date" id="expiryDate" value="<?= e($formData['expiry_date']) ?>">
                        </div>

                        <div class="cd-fg">
                            <label>Max Users <span class="req">*</span></label>
                            <input type="number" name="max_users" min="1" value="<?= e($formData['max_users']) ?>" required>
                        </div>

                        <div class="cd-fg cd-span2">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" placeholder="Any notes..."><?= e($formData['notes']) ?></textarea>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="cd-footer">
            <span class="cd-footer-note">
                <?= $edit ? 'Update license details carefully.' : 'Create license for selected client and module.' ?>
            </span>

            <div class="cd-footer-btns">
                <a href="Licenses" class="cd-cancel-btn">
                    <i class="fa-solid fa-arrow-left" style="font-size:12px"></i>
                    Cancel
                </a>

                <button type="button" class="cd-test-btn" onclick="generateKey()">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Generate Key
                </button>

                <button type="submit" class="cd-save-btn" id="saveBtn">
                    <i class="fa-solid fa-<?= $edit ? 'floppy-disk' : 'circle-plus' ?>"></i>
                    <?= $edit ? 'Update License' : 'Create License' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function generateKey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let parts = [];

    for (let p = 0; p < 3; p++) {
        let part = '';
        for (let i = 0; i < 8; i++) {
            part += chars[Math.floor(Math.random() * chars.length)];
        }
        parts.push(part);
    }

    document.getElementById('licenseKey').value = parts.join('-');
}

document.getElementById('licenseType').addEventListener('change', function() {
    const expiry = document.getElementById('expiryDate');

    if (this.value === 'lifetime') {
        expiry.value = '';
        expiry.disabled = true;
    } else {
        expiry.disabled = false;
    }
});

document.getElementById('licenseType').dispatchEvent(new Event('change'));

document.getElementById('licenseForm').addEventListener('submit', function(e) {
    let ok = true;

    this.querySelectorAll('[required]').forEach(function(el) {
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

    const btn = document.getElementById('saveBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
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