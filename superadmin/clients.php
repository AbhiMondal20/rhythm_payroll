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

$msg  = "";
$swal = null;

/* DELETE / DEACTIVATE */
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];

    if ($deleteId > 0) {
        $stmt = $master->prepare("UPDATE clients SET status='inactive', updated_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $deleteId);

        if ($stmt->execute()) {
            $swal = [
                'icon' => 'success',
                'title' => 'Deactivated!',
                'text' => 'Client deactivated successfully.',
                'redirect' => 'clients.php'
            ];
        } else {
            $swal = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => 'Failed to deactivate client.',
                'redirect' => ''
            ];
        }

        $stmt->close();
    }
}

/* SEARCH */
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$where = [];
$params = [];
$types = "";

if ($search !== '') {
    $where[] = "(client_name LIKE ? OR client_code LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if ($statusFilter === 'active' || $statusFilter === 'inactive') {
    $where[] = "status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* FETCH CLIENTS */
$sql = "
    SELECT id, client_code, client_name, logo, phone, email, website, address,
           status, letter_head_type, latter_head, created_at, updated_at
    FROM clients
    $whereSql
    ORDER BY id DESC
";

$stmt = $master->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$clients = $stmt->get_result();
$stmt->close();
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
            Clients
        </h1>
        <div class="cd-bc">
            <a href="dashboard.php">Dashboard</a>
            <span class="sep">›</span>
            <strong>Clients</strong>
        </div>
    </div>

    <a href="AddClients" class="cd-save-btn" style="text-decoration:none">
        <i class="fa-solid fa-circle-plus"></i>
        Add Client
    </a>
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
            Client List
        </div>

        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <input type="text"
                   name="search"
                   value="<?= e($search) ?>"
                   placeholder="Search client..."
                   style="padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13.5px;outline:none">

            <select name="status"
                    style="padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13.5px;outline:none">
                <option value="">All Status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <button type="submit" class="cd-test-btn">
                <i class="fa-solid fa-magnifying-glass"></i>
                Search
            </button>

            <?php if($search !== '' || $statusFilter !== ''): ?>
                <a href="clients.php" class="cd-cancel-btn">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div style="padding:0;overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <thead>
                <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
                    <th style="padding:13px 16px;text-align:left;color:#6B7280;font-size:11.5px;text-transform:uppercase">#</th>
                    <th style="padding:13px 16px;text-align:left;color:#6B7280;font-size:11.5px;text-transform:uppercase">Logo</th>
                    <th style="padding:13px 16px;text-align:left;color:#6B7280;font-size:11.5px;text-transform:uppercase">Client</th>
                    <th style="padding:13px 16px;text-align:left;color:#6B7280;font-size:11.5px;text-transform:uppercase">Contact</th>
                    <th style="padding:13px 16px;text-align:left;color:#6B7280;font-size:11.5px;text-transform:uppercase">Website</th>
                    <th style="padding:13px 16px;text-align:left;color:#6B7280;font-size:11.5px;text-transform:uppercase">Letter Head</th>
                    <th style="padding:13px 16px;text-align:left;color:#6B7280;font-size:11.5px;text-transform:uppercase">Status</th>
                    <th style="padding:13px 16px;text-align:right;color:#6B7280;font-size:11.5px;text-transform:uppercase">Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if($clients && $clients->num_rows > 0): ?>
                    <?php $i = 1; while($row = $clients->fetch_assoc()): ?>
                        <tr style="border-bottom:1px solid #F3F4F6">
                            <td style="padding:14px 16px;color:#6B7280">
                                <?= $i++ ?>
                            </td>

                            <td style="padding:14px 16px">
                                <?php if(!empty($row['logo'])): ?>
                                    <img src="../<?= e($row['logo']) ?>"
                                         alt="Logo"
                                         style="width:42px;height:42px;border-radius:9px;object-fit:contain;border:1px solid #E5E7EB;background:#fff;padding:4px">
                                <?php else: ?>
                                    <div style="width:42px;height:42px;border-radius:9px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-weight:700">
                                        <?= strtoupper(substr($row['client_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td style="padding:14px 16px">
                                <div style="font-weight:700;color:#111827">
                                    <?= e($row['client_name']) ?>
                                </div>
                                <div style="font-size:12px;color:#6B7280;margin-top:3px">
                                    <?= e($row['client_code']) ?>
                                </div>
                                <?php if(!empty($row['address'])): ?>
                                    <div style="font-size:12px;color:#9CA3AF;margin-top:3px;max-width:260px">
                                        <?= e($row['address']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td style="padding:14px 16px">
                                <?php if(!empty($row['phone'])): ?>
                                    <div style="color:#374151">
                                        <i class="fa-solid fa-phone" style="font-size:11px;color:#9CA3AF;margin-right:5px"></i>
                                        <?= e($row['phone']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if(!empty($row['email'])): ?>
                                    <div style="color:#6B7280;font-size:12px;margin-top:5px">
                                        <i class="fa-solid fa-envelope" style="font-size:11px;color:#9CA3AF;margin-right:5px"></i>
                                        <?= e($row['email']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if(empty($row['phone']) && empty($row['email'])): ?>
                                    <span style="color:#9CA3AF">N/A</span>
                                <?php endif; ?>
                            </td>

                            <td style="padding:14px 16px">
                                <?php if(!empty($row['website'])): ?>
                                    <a href="<?= e($row['website']) ?>"
                                       target="_blank"
                                       style="color:#2563EB;text-decoration:none;font-weight:600">
                                        Visit
                                        <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:10px;margin-left:4px"></i>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#9CA3AF">N/A</span>
                                <?php endif; ?>
                            </td>

                            <td style="padding:14px 16px">
                                <div style="font-weight:600;color:#374151">
                                    <?= e(ucfirst($row['letter_head_type'] ?: 'none')) ?>
                                </div>

                                <?php if(!empty($row['latter_head'])): ?>
                                    <a href="../<?= e($row['latter_head']) ?>"
                                       target="_blank"
                                       style="font-size:12px;color:#2563EB;text-decoration:none">
                                        View File
                                    </a>
                                <?php endif; ?>
                            </td>

                            <td style="padding:14px 16px">
                                <span class="cd-status-badge"
                                      style="background:<?= $row['status']==='active'?'#D1FAE5':'#F3F4F6' ?>;color:<?= $row['status']==='active'?'#065F46':'#6B7280' ?>">
                                    <i class="fa-solid fa-circle" style="font-size:7px"></i>
                                    <?= ucfirst(e($row['status'])) ?>
                                </span>
                            </td>

                            <td style="padding:14px 16px;text-align:right">
                                <div style="display:flex;gap:8px;justify-content:flex-end">
                                    <a href="AddClients?id=<?= (int)$row['id'] ?>"
                                       class="cd-test-btn"
                                       style="text-decoration:none;padding:8px 12px">
                                        <i class="fa-solid fa-pen"></i>
                                        Edit
                                    </a>

                                    <?php if($row['status'] === 'active'): ?>
                                        <button type="button"
                                                onclick="deleteClient(<?= (int)$row['id'] ?>)"
                                                class="cd-cancel-btn"
                                                style="padding:8px 12px;color:#DC2626">
                                            <i class="fa-solid fa-ban"></i>
                                            Disable
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="padding:35px;text-align:center;color:#9CA3AF">
                            <i class="fa-solid fa-folder-open" style="font-size:28px;margin-bottom:8px"></i>
                            <div>No clients found.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function deleteClient(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Deactivate Client?',
        text: 'This client will be marked as inactive.',
        showCancelButton: true,
        confirmButtonText: 'Yes, deactivate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280'
    }).then(function(result) {
        if (result.isConfirmed) {
            window.location.href = 'clients.php?delete=' + id;
        }
    });
}
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