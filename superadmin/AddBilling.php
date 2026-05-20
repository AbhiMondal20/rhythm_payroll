<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: index');
    exit();
}
require_once '../includes/db_conn.php';
require_once '../includes/config.php';

$page_title = 'Manage Billing';
ob_start();

if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection not found.");
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

$id   = (int)($_GET['id'] ?? 0);
$edit = $id > 0;

$formData = [
    'client_id'      => '',
    'module_key'     => '',
    'license_id'     => '',
    'billing_month'  => date('Y-m'),
    'license_type'   => 'trial',
    'users_count'    => 1,
    'rate_per_user'  => 50.00,
    'amount'         => 0.00,
    'payment_status' => 'unpaid',
    'payment_date'   => '',
    'notes'          => '',
];

if ($edit) {
    $stmt = $master->prepare("SELECT * FROM billing WHERE id=? LIMIT 1");
    if (!$stmt) die("Edit prepare failed: " . $master->error);

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) die("Billing record not found.");

    $formData = array_merge($formData, $row);
}

/* Load Clients */
$clients = [];
$res = $master->query("SELECT id, client_name, client_code FROM clients WHERE status='active' ORDER BY client_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $clients[] = $r;
    }
}

/* Load Modules */
$modules = [];
$res = $master->query("SELECT module_key, module_name FROM modules WHERE status='active' ORDER BY module_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $modules[] = $r;
    }
}

/* Load Licenses */
$licenses = [];
$res = $master->query("
    SELECT 
        l.id,
        l.client_id,
        l.module_key,
        l.max_users,
        l.license_key,
        l.license_type,
        c.client_name
    FROM licenses l
    LEFT JOIN clients c ON c.id = l.client_id
    WHERE l.status='active'
    ORDER BY l.id DESC
");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $licenses[] = $r;
    }
}

$msg  = "";
$swal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $client_id      = (int)($_POST['client_id'] ?? 0);
    $module_key     = strtolower(trim($_POST['module_key'] ?? ''));
    $license_id     = (int)($_POST['license_id'] ?? 0);
    $billing_month  = trim($_POST['billing_month'] ?? date('Y-m'));
    $license_type   = $_POST['license_type'] ?? 'trial';
    $users_count    = (int)($_POST['users_count'] ?? 1);
    $rate_per_user  = 50.00;
    $payment_status = $_POST['payment_status'] ?? 'unpaid';
    $payment_date   = trim($_POST['payment_date'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');

    if (!in_array($license_type, ['trial','monthly','yearly','lifetime'], true)) {
        $license_type = 'trial';
    }

    if (!in_array($payment_status, ['unpaid','paid','partial','cancelled'], true)) {
        $payment_status = 'unpaid';
    }

    if ($license_type === 'monthly') {
        $amount = $users_count * $rate_per_user;
    } elseif ($license_type === 'yearly') {
        $amount = $users_count * $rate_per_user * 12;
    } else {
        $amount = 0.00;
    }

    $paymentDateDb = $payment_date !== '' ? $payment_date : null;
    $licenseDb     = $license_id > 0 ? $license_id : null;

    $formData = [
        'client_id'      => $client_id,
        'module_key'     => $module_key,
        'license_id'     => $license_id,
        'billing_month'  => $billing_month,
        'license_type'   => $license_type,
        'users_count'    => $users_count,
        'rate_per_user'  => $rate_per_user,
        'amount'         => $amount,
        'payment_status' => $payment_status,
        'payment_date'   => $payment_date,
        'notes'          => $notes,
    ];

    if ($client_id <= 0 || $module_key === '' || $billing_month === '') {
        $msg = "Client, module and billing month are required.";
    } elseif ($users_count <= 0) {
        $msg = "Users count must be greater than 0.";
    }

    /* Duplicate Check */
    if ($msg === '') {
        if ($edit) {
            $dup = $master->prepare("
                SELECT id 
                FROM billing 
                WHERE client_id=? 
                  AND module_key=? 
                  AND billing_month=? 
                  AND license_type=?
                  AND id!=?
                LIMIT 1
            ");

            if (!$dup) {
                $msg = "Duplicate check failed: " . $master->error;
            } else {
                $dup->bind_param("isssi", $client_id, $module_key, $billing_month, $license_type, $id);
            }
        } else {
            $dup = $master->prepare("
                SELECT id 
                FROM billing 
                WHERE client_id=? 
                  AND module_key=? 
                  AND billing_month=? 
                  AND license_type=?
                LIMIT 1
            ");

            if (!$dup) {
                $msg = "Duplicate check failed: " . $master->error;
            } else {
                $dup->bind_param("isss", $client_id, $module_key, $billing_month, $license_type);
            }
        }

        if ($msg === '') {
            $dup->execute();
            $dup->store_result();

            if ($dup->num_rows > 0) {
                $msg = "Billing already exists for this client, module, month and license type.";
            }

            $dup->close();
        }
    }

    /* Insert / Update */
    if ($msg === '') {
        if ($edit) {
            $stmt = $master->prepare("
                UPDATE billing
                SET client_id=?,
                    module_key=?,
                    license_id=?,
                    billing_month=?,
                    license_type=?,
                    users_count=?,
                    rate_per_user=?,
                    amount=?,
                    payment_status=?,
                    payment_date=?,
                    notes=?,
                    updated_at=NOW()
                WHERE id=?
            ");

            if (!$stmt) {
                $msg = "Update prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "isissiddsssi",
                    $client_id,
                    $module_key,
                    $licenseDb,
                    $billing_month,
                    $license_type,
                    $users_count,
                    $rate_per_user,
                    $amount,
                    $payment_status,
                    $paymentDateDb,
                    $notes,
                    $id
                );
            }
        } else {
            $stmt = $master->prepare("
                INSERT INTO billing
                (client_id, module_key, license_id, billing_month, license_type, users_count, rate_per_user, amount, payment_status, payment_date, notes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$stmt) {
                $msg = "Insert prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "isissiddsss",
                    $client_id,
                    $module_key,
                    $licenseDb,
                    $billing_month,
                    $license_type,
                    $users_count,
                    $rate_per_user,
                    $amount,
                    $payment_status,
                    $paymentDateDb,
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
            'text' => $edit ? 'Billing updated successfully.' : 'Billing created successfully.',
            'redirect' => 'Billing'
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
.cd-bc{display:flex;align-items:center;gap:8px;font-size:13px;color:#6B7280;flex-wrap:wrap}.cd-bc a{color:#6B7280;text-decoration:none}.cd-bc .sep{color:#D1D5DB}.cd-bc strong{color:#111827;font-weight:600}
.cd-card{background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden;box-shadow:0 2px 16px rgba(15,23,42,.05)}
.cd-card-head{padding:18px 24px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.cd-head-title{font-size:16px;font-weight:700;color:#111827;display:flex;align-items:center;gap:10px}
.cd-head-icon{width:36px;height:36px;border-radius:9px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;font-size:15px;color:#2563EB}
.cd-status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
.cd-section{border:1px solid #E5E7EB;border-radius:10px;overflow:hidden;margin-bottom:18px}.cd-section-head{padding:11px 18px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;gap:9px}.cd-section-head span{font-size:12.5px;font-weight:700;color:#374151;letter-spacing:.3px;text-transform:uppercase}.cd-section-body{padding:20px 20px 16px}
.cd-grid{display:grid;gap:20px 24px}.cd-grid.c4{grid-template-columns:repeat(4,1fr)}.cd-span2{grid-column:span 2}
.cd-fg{display:flex;flex-direction:column;gap:5px}.cd-fg label{font-size:11.5px;font-weight:700;color:#6B7280;letter-spacing:.4px;text-transform:uppercase}.cd-fg label .req{color:#DC2626;margin-left:2px}.cd-fg input,.cd-fg select,.cd-fg textarea{padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13.5px;font-family:inherit;color:#111827;outline:none;background:#fff;transition:.15s;width:100%}.cd-fg input:focus,.cd-fg select:focus,.cd-fg textarea:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.08)}.cd-fg input.is-invalid,.cd-fg select.is-invalid{border-color:#DC2626;background:#FFF5F5}.cd-hint{font-size:11px;color:#9CA3AF;margin-top:3px}
.cd-footer{padding:18px 24px;border-top:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;background:#FAFAFA}.cd-footer-note{font-size:12px;color:#9CA3AF}.cd-footer-btns{display:flex;gap:10px;flex-wrap:wrap}
.cd-cancel-btn{padding:9px 24px;background:#fff;color:#374151;border:1.5px solid #D1D5DB;border-radius:8px;font-size:13.5px;font-weight:500;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.cd-save-btn{padding:9px 28px;background:#2563EB;color:#fff;border:none;border-radius:8px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:7px}
.cd-test-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;font-size:13px;font-weight:600;color:#374151;cursor:pointer;font-family:inherit}
.cd-alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:16px}.cd-alert.error{background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5}
@media(max-width:900px){.cd-grid.c4{grid-template-columns:1fr}.cd-span2{grid-column:span 1}.cd-footer{flex-direction:column;align-items:stretch}.cd-footer-btns{flex-direction:column}.cd-save-btn,.cd-cancel-btn{width:100%;justify-content:center}}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:#111827;margin-bottom:4px">
            <?= $edit ? 'Edit Billing' : 'Create Billing' ?>
        </h1>
        <div class="cd-bc">
            <a href="Billing">Billing</a>
            <span class="sep">›</span>
            <strong><?= $edit ? 'Edit Billing' : 'New Billing' ?></strong>
        </div>
    </div>

    <?php if($edit): ?>
        <span class="cd-status-badge"
              style="background:<?= $formData['payment_status']==='paid'?'#D1FAE5':'#FEF3C7' ?>;
                     color:<?= $formData['payment_status']==='paid'?'#065F46':'#92400E' ?>">
            <i class="fa-solid fa-circle" style="font-size:7px"></i>
            <?= ucfirst(e($formData['payment_status'])) ?>
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
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <?= $edit ? 'Billing Information' : 'New Billing Information' ?>
        </div>

        <div style="font-size:12px;color:#9CA3AF">
            Trial: <strong>14 Days Free</strong> | Monthly: <strong>₹50/user</strong> | Yearly: <strong>₹50×12/user</strong>
        </div>
    </div>

    <form method="POST" autocomplete="off" id="billingForm" novalidate>
        <input type="hidden" name="save_billing" value="1">

        <div style="padding:24px">
            <div class="cd-section">
                <div class="cd-section-head">
                    <i class="fa-solid fa-calculator"></i>
                    <span>Billing Details</span>
                </div>

                <div class="cd-section-body">
                    <div class="cd-grid c4">

                        <div class="cd-fg">
                            <label>Client <span class="req">*</span></label>
                            <select name="client_id" id="clientId" required>
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
                            <select name="module_key" id="moduleKey" required>
                                <option value="">— Select Module —</option>
                                <?php foreach($modules as $m): ?>
                                    <option value="<?= e($m['module_key']) ?>" <?= ($formData['module_key']===$m['module_key'])?'selected':'' ?>>
                                        <?= e($m['module_name']) ?> - <?= e($m['module_key']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>License</label>
                            <select name="license_id" id="licenseId">
                                <option value="">— Optional License —</option>
                                <?php foreach($licenses as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>"
                                            data-client="<?= (int)$l['client_id'] ?>"
                                            data-module="<?= e($l['module_key']) ?>"
                                            data-users="<?= (int)$l['max_users'] ?>"
                                            data-type="<?= e($l['license_type']) ?>"
                                        <?= ((int)$formData['license_id']===(int)$l['id'])?'selected':'' ?>>
                                        <?= e($l['client_name']) ?> | <?= e($l['module_key']) ?> | <?= e($l['license_key']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>Billing Month <span class="req">*</span></label>
                            <input type="month" name="billing_month" value="<?= e($formData['billing_month']) ?>" required>
                        </div>

                        <div class="cd-fg">
                            <label>License Type <span class="req">*</span></label>
                            <select name="license_type" id="licenseType" required>
                                <option value="trial" <?= ($formData['license_type'] ?? 'trial')==='trial'?'selected':'' ?>>
                                    Trial - 14 Days
                                </option>
                                <option value="monthly" <?= ($formData['license_type'] ?? 'trial')==='monthly'?'selected':'' ?>>
                                    Monthly
                                </option>
                                <option value="yearly" <?= ($formData['license_type'] ?? 'trial')==='yearly'?'selected':'' ?>>
                                    Yearly
                                </option>
                                <option value="lifetime" <?= ($formData['license_type'] ?? 'trial')==='lifetime'?'selected':'' ?>>
                                    Lifetime
                                </option>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>Users Count <span class="req">*</span></label>
                            <input type="number" name="users_count" id="usersCount" min="1" value="<?= e($formData['users_count']) ?>" required>
                        </div>

                        <div class="cd-fg">
                            <label>Rate / User</label>
                            <input type="text" id="ratePerUser" value="₹50.00" readonly>
                        </div>

                        <div class="cd-fg">
                            <label>Total Amount</label>
                            <input type="text" id="totalAmount" value="₹<?= number_format((float)$formData['amount'], 2) ?>" readonly>
                            <span class="cd-hint">Trial & Lifetime = ₹0 | Monthly = users × ₹50 | Yearly = users × ₹50 × 12</span>
                        </div>

                        <div class="cd-fg">
                            <label>Payment Status</label>
                            <select name="payment_status">
                                <option value="unpaid" <?= $formData['payment_status']==='unpaid'?'selected':'' ?>>Unpaid</option>
                                <option value="paid" <?= $formData['payment_status']==='paid'?'selected':'' ?>>Paid</option>
                                <option value="partial" <?= $formData['payment_status']==='partial'?'selected':'' ?>>Partial</option>
                                <option value="cancelled" <?= $formData['payment_status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>Payment Date</label>
                            <input type="date" name="payment_date" value="<?= e($formData['payment_date']) ?>">
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
                Trial license is free for 14 days. Monthly and yearly billing calculated automatically.
            </span>

            <div class="cd-footer-btns">
                <a href="Billing" class="cd-cancel-btn">
                    <i class="fa-solid fa-arrow-left" style="font-size:12px"></i>
                    Cancel
                </a>

                <button type="submit" class="cd-save-btn" id="saveBtn">
                    <i class="fa-solid fa-<?= $edit ? 'floppy-disk' : 'circle-plus' ?>"></i>
                    <?= $edit ? 'Update Billing' : 'Create Billing' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
const RATE = 50;

function calculateAmount() {
    const users = parseInt(document.getElementById('usersCount').value || '0', 10);
    const type = document.getElementById('licenseType').value;

    let amount = 0;

    if (type === 'monthly') {
        amount = users * RATE;
    } else if (type === 'yearly') {
        amount = users * RATE * 12;
    } else {
        amount = 0;
    }

    document.getElementById('totalAmount').value = '₹' + amount.toFixed(2);
}

document.getElementById('usersCount').addEventListener('input', calculateAmount);
document.getElementById('licenseType').addEventListener('change', calculateAmount);

calculateAmount();

document.getElementById('licenseId').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];

    if (!opt || !opt.value) return;

    const client = opt.getAttribute('data-client');
    const module = opt.getAttribute('data-module');
    const users  = opt.getAttribute('data-users');
    const type   = opt.getAttribute('data-type');

    if (client) document.getElementById('clientId').value = client;
    if (module) document.getElementById('moduleKey').value = module;
    if (users) document.getElementById('usersCount').value = users;
    if (type) document.getElementById('licenseType').value = type;

    calculateAmount();
});

document.getElementById('billingForm').addEventListener('submit', function(e) {
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