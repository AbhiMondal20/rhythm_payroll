<?php
require_once '../includes/db_conn.php';
require_once '../includes/config.php';

$page_title = 'Clients';
ob_start();

if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection not found.");
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

function makeClientCode($name) {
    $code = strtolower(trim((string)$name));
    $code = preg_replace('/[^a-z0-9]+/', '_', $code);
    $code = trim($code, '_');
    return substr($code, 0, 80);
}

function uploadClientFile($fieldName, $oldFile = '') {
    if (empty($_FILES[$fieldName]['name'])) {
        return $oldFile;
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        return $oldFile;
    }

    $fileName = $fieldName . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $target = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $target)) {
        return 'uploads/' . $fileName;
    }

    return $oldFile;
}

/* EDIT LOAD */
$id   = (int)($_GET['id'] ?? 0);
$edit = $id > 0;

$formData = [
    'client_code'      => '',
    'client_name'      => '',
    'logo'             => '',
    'phone'            => '',
    'email'            => '',
    'website'          => '',
    'address'          => '',
    'status'           => 'active',
    'letter_head_type' => 'none',
    'latter_head'      => '',
];

if ($edit) {
    $stmt = $master->prepare("
        SELECT id, client_code, client_name, logo, phone, email, website, address, status, letter_head_type, latter_head
        FROM clients
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $formData = array_merge($formData, $row);
    } else {
        die("Client not found.");
    }
}

/* POST */
$msg  = "";
$swal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $client_name      = trim($_POST['client_name'] ?? '');
    $client_code      = strtolower(trim($_POST['client_code'] ?? ''));
    $phone            = trim($_POST['phone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $website          = trim($_POST['website'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $status           = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $letter_head_type = trim($_POST['letter_head_type'] ?? 'none');

    if ($client_code === '' && $client_name !== '') {
        $client_code = makeClientCode($client_name);
    }

    $oldLogo       = $formData['logo'] ?? '';
    $oldLatterHead = $formData['latter_head'] ?? '';

    $logo        = uploadClientFile('logo', $oldLogo);
    $latter_head = uploadClientFile('latter_head', $oldLatterHead);

    $formData = [
        'client_code'      => $client_code,
        'client_name'      => $client_name,
        'logo'             => $logo,
        'phone'            => $phone,
        'email'            => $email,
        'website'          => $website,
        'address'          => $address,
        'status'           => $status,
        'letter_head_type' => $letter_head_type,
        'latter_head'      => $latter_head,
    ];

    if ($client_name === '' || $client_code === '') {
        $msg = "Client name and client code are required.";
    } elseif (!preg_match('/^[a-z0-9_]+$/', $client_code)) {
        $msg = "Client code must contain only lowercase letters, numbers and underscores.";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Invalid email address.";
    }

    if ($msg === '') {
        $dupSql = $edit
            ? "SELECT id FROM clients WHERE client_code = ? AND id != ? LIMIT 1"
            : "SELECT id FROM clients WHERE client_code = ? LIMIT 1";

        $dup = $master->prepare($dupSql);
        if ($edit) {
            $dup->bind_param("si", $client_code, $id);
        } else {
            $dup->bind_param("s", $client_code);
        }

        $dup->execute();
        $dup->store_result();

        if ($dup->num_rows > 0) {
            $msg = "This client code already exists.";
        }

        $dup->close();
    }

    if ($msg === '') {
        if ($edit) {
            $stmt = $master->prepare("
                UPDATE clients
                SET client_code = ?,
                    client_name = ?,
                    logo = ?,
                    phone = ?,
                    email = ?,
                    website = ?,
                    address = ?,
                    status = ?,
                    letter_head_type = ?,
                    latter_head = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            if (!$stmt) {
                $msg = "Prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "ssssssssssi",
                    $client_code,
                    $client_name,
                    $logo,
                    $phone,
                    $email,
                    $website,
                    $address,
                    $status,
                    $letter_head_type,
                    $latter_head,
                    $id
                );
            }
        } else {
            $stmt = $master->prepare("
                INSERT INTO clients
                (client_code, client_name, logo, phone, email, website, address, status, letter_head_type, latter_head, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$stmt) {
                $msg = "Prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "ssssssssss",
                    $client_code,
                    $client_name,
                    $logo,
                    $phone,
                    $email,
                    $website,
                    $address,
                    $status,
                    $letter_head_type,
                    $latter_head
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
            'text' => $edit ? 'Client updated successfully.' : 'Client created successfully.',
            'redirect' => 'clients'
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
/* ════════════════════════════════════════
   CLIENT DATABASE — ADD / EDIT PAGE
════════════════════════════════════════ */

/* ── breadcrumb ── */
.cd-bc {
    display:flex;align-items:center;gap:8px;
    font-size:13px;color:#6B7280;flex-wrap:wrap;
}
.cd-bc a       { color:#6B7280;text-decoration:none;transition:color .15s; }
.cd-bc a:hover { color:#2563EB; }
.cd-bc .sep    { color:#D1D5DB; }
.cd-bc strong  { color:#111827;font-weight:600; }

/* ── page card ── */
.cd-card {
    background:#fff;border:1px solid #E5E7EB;border-radius:14px;
    overflow:hidden;box-shadow:0 2px 16px rgba(15,23,42,.05);
}

/* ── card header ── */
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

/* ── section blocks inside form ── */
.cd-section {
    border:1px solid #E5E7EB;border-radius:10px;overflow:hidden;margin-bottom:18px;
}
.cd-section-head {
    padding:11px 18px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;
    display:flex;align-items:center;gap:9px;
}
.cd-section-head i { font-size:13px;color:#6B7280;width:16px;text-align:center; }
.cd-section-head span { font-size:12.5px;font-weight:700;color:#374151;letter-spacing:.3px;text-transform:uppercase; }
.cd-section-body { padding:20px 20px 16px; }

/* ── grid ── */
.cd-grid { display:grid;gap:20px 24px; }
.cd-grid.c4 { grid-template-columns:repeat(4,1fr); }
.cd-grid.c3 { grid-template-columns:repeat(3,1fr); }
.cd-grid.c2 { grid-template-columns:repeat(2,1fr); }
.cd-grid.c1 { grid-template-columns:1fr; }
.cd-span2   { grid-column:span 2; }

/* ── field group ── */
.cd-fg { display:flex;flex-direction:column;gap:5px; }
.cd-fg label {
    font-size:11.5px;font-weight:700;color:#6B7280;
    letter-spacing:.4px;text-transform:uppercase;
}
.cd-fg label .req { color:#DC2626;margin-left:2px; }
.cd-fg input[type=text],
.cd-fg input[type=password],
.cd-fg input[type=number],
.cd-fg input[type=email],
.cd-fg select {
    padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;
    font-size:13.5px;font-family:inherit;color:#111827;
    outline:none;background:#fff;transition:.15s;width:100%;
}
.cd-fg input:focus,.cd-fg select:focus {
    border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.08);
}
.cd-fg input.is-invalid,.cd-fg select.is-invalid {
    border-color:#DC2626;background:#FFF5F5;
}
.cd-fg .cd-hint { font-size:11px;color:#9CA3AF;margin-top:3px; }

/* password toggle */
.cd-pw-wrap { position:relative; }
.cd-pw-wrap input { padding-right:38px; }
.cd-pw-toggle {
    position:absolute;right:10px;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:13px;
    transition:color .15s;
}
.cd-pw-toggle:hover { color:#374151; }

/* ── generate options checkboxes ── */
.cd-checks { display:flex;gap:20px;flex-wrap:wrap; }
.cd-check {
    display:flex;align-items:center;gap:8px;
    font-size:13.5px;color:#374151;cursor:pointer;
    padding:8px 14px;border:1.5px solid #E5E7EB;border-radius:8px;
    transition:.15s;user-select:none;
}
.cd-check:has(input:checked) { background:#EFF6FF;border-color:#BFDBFE;color:#1D4ED8; }
.cd-check:hover { border-color:#2563EB; }
.cd-check input { width:15px;height:15px;accent-color:#2563EB;cursor:pointer; }

/* ── test connection button ── */
.cd-test-btn {
    display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
    border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;
    font-size:13px;font-weight:600;color:#374151;cursor:pointer;
    font-family:inherit;transition:.15s;
}
.cd-test-btn:hover { border-color:#2563EB;color:#2563EB;background:#EFF6FF; }
.cd-test-btn i { font-size:12px; }

/* ── form footer ── */
.cd-footer {
    padding:18px 24px;border-top:1px solid #E5E7EB;
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:12px;background:#FAFAFA;
}
.cd-footer-note { font-size:12px;color:#9CA3AF; }
.cd-footer-btns { display:flex;gap:10px;flex-wrap:wrap; }
.cd-cancel-btn {
    padding:9px 24px;background:#fff;color:#374151;
    border:1.5px solid #D1D5DB;border-radius:8px;
    font-size:13.5px;font-weight:500;cursor:pointer;
    font-family:inherit;transition:.15s;text-decoration:none;
    display:inline-flex;align-items:center;gap:6px;
}
.cd-cancel-btn:hover { border-color:#374151; }
.cd-save-btn {
    padding:9px 28px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:13.5px;font-weight:700;cursor:pointer;
    font-family:inherit;transition:background .15s;
    display:inline-flex;align-items:center;gap:7px;
}
.cd-save-btn:hover { background:#1D4ED8; }

/* ── alert banner ── */
.cd-alert {
    display:flex;align-items:center;gap:10px;padding:12px 16px;
    border-radius:8px;font-size:13px;font-weight:500;margin-bottom:16px;
}
.cd-alert.error   { background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5; }
.cd-alert.success { background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7; }
.cd-alert i { font-size:14px;flex-shrink:0; }

/* responsive */
@media(max-width:1100px){
    .cd-grid.c4 { grid-template-columns:repeat(2,1fr); }
    .cd-span2   { grid-column:span 2; }
}
@media(max-width:700px){
    .cd-grid.c4,.cd-grid.c3,.cd-grid.c2 { grid-template-columns:1fr; }
    .cd-span2 { grid-column:span 1; }
    .cd-footer { flex-direction:column;align-items:stretch; }
    .cd-footer-btns { flex-direction:column; }
    .cd-save-btn,.cd-cancel-btn { width:100%;justify-content:center; }
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:#111827;margin-bottom:4px">
            <?= $edit ? 'Edit Client' : 'Create Client' ?>
        </h1>
        <div class="cd-bc">
            <a href="clients">Clients</a>
            <span class="sep">›</span>
            <strong><?= $edit ? 'Edit Client' : 'New Client' ?></strong>
        </div>
    </div>

    <?php if($edit): ?>
    <span class="cd-status-badge" style="background:<?= $formData['status']==='active'?'#D1FAE5':'#F3F4F6' ?>;color:<?= $formData['status']==='active'?'#065F46':'#6B7280' ?>">
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
                <i class="fa-solid fa-building"></i>
            </div>
            <?= $edit ? 'Client Information' : 'New Client Information' ?>
        </div>
        <div style="font-size:12px;color:#9CA3AF">
            <i class="fa-solid fa-circle-info" style="margin-right:4px"></i>
            Fields marked <span style="color:#DC2626;font-weight:600">*</span> are required
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" autocomplete="off" id="cdForm" novalidate>
        <div style="padding:24px">

            <div class="cd-section">
                <div class="cd-section-head">
                    <i class="fa-solid fa-building"></i>
                    <span>Client Details</span>
                </div>

                <div class="cd-section-body">
                    <div class="cd-grid c4">

                        <div class="cd-fg">
                            <label>Client Name <span class="req">*</span></label>
                            <input type="text" name="client_name" id="clientName"
                                   value="<?= e($formData['client_name']) ?>"
                                   placeholder="Client name" required>
                        </div>

                        <div class="cd-fg">
                            <label>Client Code <span class="req">*</span></label>
                            <input type="text" name="client_code" id="clientCode"
                                   value="<?= e($formData['client_code']) ?>"
                                   placeholder="client_code"
                                   oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'')"
                                   required>
                            <span class="cd-hint">Lowercase, numbers and underscore only</span>
                        </div>

                        <div class="cd-fg">
                            <label>Phone</label>
                            <input type="text" name="phone"
                                   value="<?= e($formData['phone']) ?>"
                                   placeholder="Phone number">
                        </div>

                        <div class="cd-fg">
                            <label>Email</label>
                            <input type="email" name="email"
                                   value="<?= e($formData['email']) ?>"
                                   placeholder="Email address">
                        </div>

                        <div class="cd-fg">
                            <label>Website</label>
                            <input type="text" name="website"
                                   value="<?= e($formData['website']) ?>"
                                   placeholder="https://example.com">
                        </div>

                        <div class="cd-fg">
                            <label>Status</label>
                            <select name="status">
                                <option value="active" <?= $formData['status']==='active'?'selected':'' ?>>Active</option>
                                <option value="inactive" <?= $formData['status']==='inactive'?'selected':'' ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>Logo</label>
                            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
                            <?php if(!empty($formData['logo'])): ?>
                                <span class="cd-hint">Current: <?= e($formData['logo']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="cd-fg">
                            <label>Letter Head Type</label>
                            <select name="letter_head_type">
                                <option value="none" <?= $formData['letter_head_type']==='none'?'selected':'' ?>>None</option>
                                <option value="image" <?= $formData['letter_head_type']==='image'?'selected':'' ?>>Image</option>
                                <option value="pdf" <?= $formData['letter_head_type']==='pdf'?'selected':'' ?>>PDF</option>
                                <option value="custom" <?= $formData['letter_head_type']==='custom'?'selected':'' ?>>Custom</option>
                            </select>
                        </div>

                        <div class="cd-fg cd-span2">
                            <label>Letter Head</label>
                            <input type="file" name="latter_head" accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <?php if(!empty($formData['latter_head'])): ?>
                                <span class="cd-hint">Current: <?= e($formData['latter_head']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="cd-fg cd-span2">
                            <label>Address</label>
                            <input type="text" name="address"
                                   value="<?= e($formData['address']) ?>"
                                   placeholder="Client address">
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="cd-footer">
            <span class="cd-footer-note">
                <?= $edit ? 'Last updated: ' . date('d M Y, h:i A') : 'All required fields must be filled before saving.' ?>
            </span>
            <div class="cd-footer-btns">
                <a href="clients" class="cd-cancel-btn">
                    <i class="fa-solid fa-arrow-left" style="font-size:12px"></i>
                    Cancel
                </a>
                <input type="hidden" name="save_client" value="1">
                <button type="submit" class="cd-save-btn" id="saveBtn">
                    <i class="fa-solid fa-<?= $edit ? 'floppy-disk' : 'circle-plus' ?>"></i>
                    <?= $edit ? 'Update Client' : 'Create Client' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('clientName').addEventListener('input', function() {
    var codeInput = document.getElementById('clientCode');

    if (!codeInput.value || codeInput.dataset.manual !== '1') {
        codeInput.value = this.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '')
            .slice(0, 80);
    }
});

document.getElementById('clientCode').addEventListener('input', function() {
    this.dataset.manual = '1';
});

document.getElementById('cdForm').addEventListener('submit', function(e) {
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
        return;
    }

    var saveBtn = document.getElementById('saveBtn');
    saveBtn.disabled = true;
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
<style>.swal-rounded { border-radius:14px!important; }</style>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
include 'header.php';
echo $page_content;
include 'footer.php';
?>
<script src="../includes/assets/scripts.js"></script>