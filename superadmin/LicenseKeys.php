<?php
require_once '../includes/db_conn.php';
require_once '../includes/config.php';

$page_title = 'Licenses';
ob_start();

if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection not found.");
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

$swal = null;

if (isset($_GET['disable'])) {
    $id = (int)$_GET['disable'];

    if ($id > 0) {
        $stmt = $master->prepare("UPDATE licenses SET status='inactive', updated_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $swal = [
                'icon' => 'success',
                'title' => 'Disabled!',
                'text' => 'License disabled successfully.',
                'redirect' => 'Licenses'
            ];
        } else {
            $swal = [
                'icon' => 'error',
                'title' => 'Error',
                'text' => $stmt->error,
                'redirect' => ''
            ];
        }

        $stmt->close();
    }
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(
        c.client_name LIKE ?
        OR l.module_key LIKE ?
        OR l.license_key LIKE ?
        OR l.license_type LIKE ?
    )";
    $like = "%$search%";
    $params = [$like, $like, $like, $like];
    $types .= 'ssss';
}

if (in_array($status, ['active','inactive','expired','cancelled'], true)) {
    $where[] = "l.status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT 
        l.id,
        l.client_id,
        l.module_key,
        l.license_key,
        l.license_type,
        l.start_date,
        l.expiry_date,
        l.max_users,
        l.status,
        l.notes,
        l.created_at,
        l.updated_at,
        c.client_name,
        c.client_code,
        c.logo
    FROM licenses l
    LEFT JOIN clients c ON c.id = l.client_id
    $whereSql
    ORDER BY l.id DESC
";

$stmt = $master->prepare($sql);

if (!$stmt) {
    die("Query prepare failed: " . $master->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$licenses = $stmt->get_result();
$stmt->close();
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
.cd-test-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;font-size:13px;font-weight:600;color:#374151;cursor:pointer;font-family:inherit;transition:.15s}
.cd-test-btn:hover{border-color:#2563EB;color:#2563EB;background:#EFF6FF}
.cd-cancel-btn{padding:9px 24px;background:#fff;color:#374151;border:1.5px solid #D1D5DB;border-radius:8px;font-size:13.5px;font-weight:500;cursor:pointer;font-family:inherit;transition:.15s;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.cd-save-btn{padding:9px 28px;background:#2563EB;color:#fff;border:none;border-radius:8px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;display:inline-flex;align-items:center;gap:7px}
.cd-save-btn:hover{background:#1D4ED8}
@media(max-width:700px){.cd-card-head{flex-direction:column;align-items:stretch}.cd-save-btn,.cd-cancel-btn,.cd-test-btn{justify-content:center}}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:#111827;margin-bottom:4px">Licenses</h1>
        <div class="cd-bc">
            <a href="dashboard">Dashboard</a>
            <span class="sep">›</span>
            <strong>Licenses</strong>
        </div>
    </div>

    <a href="AddLicense" class="cd-save-btn" style="text-decoration:none">
        <i class="fa-solid fa-circle-plus"></i>
        Add License
    </a>
</div>

<div class="cd-card">
    <div class="cd-card-head">
        <div class="cd-head-title">
            <div class="cd-head-icon">
                <i class="fa-solid fa-key"></i>
            </div>
            License List
        </div>

        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search license..."
                   style="padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13.5px">

            <select name="status" style="padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13.5px">
                <option value="">All Status</option>
                <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
                <option value="expired" <?= $status==='expired'?'selected':'' ?>>Expired</option>
                <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
            </select>

            <button class="cd-test-btn" type="submit">
                <i class="fa-solid fa-magnifying-glass"></i>
                Search
            </button>

            <?php if($search !== '' || $status !== ''): ?>
                <a href="LicenseKeys" class="cd-cancel-btn">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <thead>
                <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
                    <th style="padding:13px 16px;text-align:left">#</th>
                    <th style="padding:13px 16px;text-align:left">Client</th>
                    <th style="padding:13px 16px;text-align:left">Module</th>
                    <th style="padding:13px 16px;text-align:left">License Key</th>
                    <th style="padding:13px 16px;text-align:left">Type</th>
                    <th style="padding:13px 16px;text-align:left">Validity</th>
                    <th style="padding:13px 16px;text-align:left">Users</th>
                    <th style="padding:13px 16px;text-align:left">Status</th>
                    <th style="padding:13px 16px;text-align:right">Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if($licenses && $licenses->num_rows > 0): ?>
                    <?php $i=1; while($row = $licenses->fetch_assoc()): ?>
                        <tr style="border-bottom:1px solid #F3F4F6">
                            <td style="padding:14px 16px"><?= $i++ ?></td>

                            <td style="padding:14px 16px">
                                <div style="display:flex;align-items:center;gap:10px">
                                    <?php if(!empty($row['logo'])): ?>
                                        <img src="../<?= e($row['logo']) ?>" style="width:42px;height:42px;border-radius:10px;object-fit:cover;border:1px solid #E5E7EB">
                                    <?php else: ?>
                                        <div style="width:42px;height:42px;border-radius:10px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-weight:700">
                                            <?= strtoupper(substr($row['client_name'] ?: 'L', 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div>
                                        <div style="font-weight:700;color:#111827"><?= e($row['client_name'] ?: 'N/A') ?></div>
                                        <div style="font-size:12px;color:#6B7280"><?= e($row['client_code'] ?: '') ?></div>
                                    </div>
                                </div>
                            </td>

                            <td style="padding:14px 16px;font-weight:600;color:#374151">
                                <?= e($row['module_key']) ?>
                            </td>

                            <td style="padding:14px 16px">
                                <div style="font-family:monospace;font-size:12.5px;color:#111827">
                                    <?= e($row['license_key']) ?>
                                </div>
                            </td>

                            <td style="padding:14px 16px">
                                <?= ucfirst(e($row['license_type'])) ?>
                            </td>

                            <td style="padding:14px 16px">
                                <div><?= e($row['start_date'] ?: 'N/A') ?></div>
                                <div style="font-size:12px;color:#6B7280">to <?= e($row['expiry_date'] ?: 'Lifetime') ?></div>
                            </td>

                            <td style="padding:14px 16px">
                                <?= (int)$row['max_users'] ?>
                            </td>

                            <td style="padding:14px 16px">
                                <span class="cd-status-badge"
                                      style="background:<?= $row['status']==='active'?'#D1FAE5':'#F3F4F6' ?>;
                                             color:<?= $row['status']==='active'?'#065F46':'#6B7280' ?>">
                                    <i class="fa-solid fa-circle" style="font-size:7px"></i>
                                    <?= ucfirst(e($row['status'])) ?>
                                </span>
                            </td>

                            <td style="padding:14px 16px;text-align:right">
                                <div style="display:flex;gap:8px;justify-content:flex-end">
                                    <a href="AddLicense?id=<?= (int)$row['id'] ?>" class="cd-test-btn" style="text-decoration:none;padding:8px 12px">
                                        <i class="fa-solid fa-pen"></i>
                                        Edit
                                    </a>

                                    <?php if($row['status']==='active'): ?>
                                        <button type="button" onclick="disableLicense(<?= (int)$row['id'] ?>)" class="cd-cancel-btn" style="padding:8px 12px;color:#DC2626">
                                            Disable
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="padding:35px;text-align:center;color:#9CA3AF">
                            No licenses found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function disableLicense(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Disable License?',
        text: 'This license will be marked inactive.',
        showCancelButton: true,
        confirmButtonText: 'Yes, disable',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280'
    }).then(function(result) {
        if (result.isConfirmed) {
            window.location.href = 'Licenses?disable=' + id;
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
    confirmButtonColor: "#2563eb"
}).then(function() {
    <?php if(!empty($swal['redirect'])): ?>
    window.location.href = "<?= e($swal['redirect']) ?>";
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
include 'header.php';
echo $page_content;
include 'footer.php';
?>
<script src="../includes/assets/scripts.js"></script>