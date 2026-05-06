<?php
require_once '../includes/db_conn.php';
require_once '../includes/config.php';

$page_title = 'Dashboard';
$extra_head = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>';

ob_start();

if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection not found.");
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

function countRows($db, $sql) {
    $res = $db->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

function sumAmount($db, $sql) {
    $res = $db->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (float)($row['total'] ?? 0);
}

$total_clients   = countRows($master, "SELECT COUNT(*) AS total FROM clients");
$active_clients  = countRows($master, "SELECT COUNT(*) AS total FROM clients WHERE status='active'");
$total_modules   = countRows($master, "SELECT COUNT(*) AS total FROM modules");
$total_databases = countRows($master, "SELECT COUNT(*) AS total FROM client_databases");
$total_licenses  = countRows($master, "SELECT COUNT(*) AS total FROM licenses");
$active_licenses = countRows($master, "SELECT COUNT(*) AS total FROM licenses WHERE status='active'");
$trial_licenses  = countRows($master, "SELECT COUNT(*) AS total FROM licenses WHERE license_type='trial'");
$expired_licenses = countRows($master, "SELECT COUNT(*) AS total FROM licenses WHERE status='expired'");

$total_billing   = sumAmount($master, "SELECT SUM(amount) AS total FROM billing WHERE payment_status!='cancelled'");
$paid_billing    = sumAmount($master, "SELECT SUM(amount) AS total FROM billing WHERE payment_status='paid'");
$unpaid_billing  = sumAmount($master, "SELECT SUM(amount) AS total FROM billing WHERE payment_status='unpaid'");

$recentClients = $master->query("
    SELECT id, client_code, client_name, logo, phone, email, status, created_at
    FROM clients
    ORDER BY id DESC
    LIMIT 6
");

$recentBilling = $master->query("
    SELECT b.id, b.client_id, b.module_key, b.billing_month, b.license_type, b.users_count, b.amount, b.payment_status,
           c.client_name, c.client_code
    FROM billing b
    LEFT JOIN clients c ON c.id = b.client_id
    ORDER BY b.id DESC
    LIMIT 6
");

$moduleStats = $master->query("
    SELECT m.module_key, m.module_name, COUNT(cd.id) AS total_db
    FROM modules m
    LEFT JOIN client_databases cd ON cd.module_key = m.module_key
    GROUP BY m.module_key, m.module_name
    ORDER BY total_db DESC
    LIMIT 6
");

$monthlyBillingLabels = [];
$monthlyBillingValues = [];

$res = $master->query("
    SELECT billing_month, SUM(amount) AS total
    FROM billing
    WHERE payment_status!='cancelled'
    GROUP BY billing_month
    ORDER BY billing_month ASC
    LIMIT 6
");

if ($res) {
    while ($r = $res->fetch_assoc()) {
        $monthlyBillingLabels[] = $r['billing_month'];
        $monthlyBillingValues[] = (float)$r['total'];
    }
}

if (empty($monthlyBillingLabels)) {
    $monthlyBillingLabels = [date('Y-m')];
    $monthlyBillingValues = [0];
}
?>

<link rel="stylesheet" href="../includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.dash-bc{display:flex;align-items:center;gap:8px;font-size:13px;color:#6B7280;flex-wrap:wrap}
.dash-bc a{color:#6B7280;text-decoration:none}
.dash-bc .sep{color:#D1D5DB}
.dash-bc strong{color:#111827;font-weight:600}

.dash-card{
    background:#fff;
    border:1px solid #E5E7EB;
    border-radius:18px;
    box-shadow:0 8px 28px rgba(15,23,42,.06);
    overflow:hidden;
}

.dash-hero{
    background:linear-gradient(135deg,#111827,#1D4ED8);
    border-radius:22px;
    padding:24px;
    color:#fff;
    margin-bottom:20px;
    position:relative;
    overflow:hidden;
}

.dash-hero:before{
    content:"";
    position:absolute;
    width:260px;
    height:260px;
    border-radius:50%;
    background:rgba(255,255,255,.09);
    right:-80px;
    top:-90px;
}

.dash-hero h1{
    font-size:26px;
    font-weight:800;
    margin:0 0 6px;
}

.dash-hero p{
    color:rgba(255,255,255,.78);
    font-size:13px;
}

.dash-grid-4{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:18px;
}

.dash-grid-3{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:16px;
    margin-bottom:18px;
}

.metric-card{
    background:#fff;
    border:1px solid #E5E7EB;
    border-radius:18px;
    padding:18px;
    box-shadow:0 6px 20px rgba(15,23,42,.05);
    position:relative;
    overflow:hidden;
}

.metric-card:after{
    content:"";
    position:absolute;
    width:84px;
    height:84px;
    border-radius:50%;
    background:#EFF6FF;
    right:-25px;
    bottom:-28px;
}

.metric-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    position:relative;
    z-index:1;
}

.metric-label{
    font-size:11px;
    color:#6B7280;
    font-weight:800;
    letter-spacing:.5px;
    text-transform:uppercase;
}

.metric-value{
    font-size:28px;
    line-height:1.1;
    margin-top:6px;
    color:#111827;
    font-weight:900;
}

.metric-sub{
    margin-top:6px;
    font-size:12px;
    color:#6B7280;
}

.metric-icon{
    width:42px;
    height:42px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:17px;
    flex-shrink:0;
}

.card-head{
    padding:16px 18px;
    border-bottom:1px solid #F3F4F6;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
}

.card-title{
    font-size:15px;
    font-weight:800;
    color:#111827;
}

.card-sub{
    font-size:12px;
    color:#6B7280;
    margin-top:2px;
}

.quick-actions{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:10px;
    padding:16px;
}

.quick-actions a{
    text-decoration:none;
    border:1px solid #E5E7EB;
    border-radius:14px;
    padding:14px;
    color:#111827;
    font-weight:700;
    font-size:13px;
    display:flex;
    align-items:center;
    gap:10px;
    transition:.15s;
}

.quick-actions a:hover{
    border-color:#2563EB;
    background:#EFF6FF;
    color:#2563EB;
}

.badge{
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:4px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:700;
}

.status-active{background:#D1FAE5;color:#065F46}
.status-unpaid{background:#FEF3C7;color:#92400E}
.status-paid{background:#D1FAE5;color:#065F46}
.status-inactive{background:#F3F4F6;color:#6B7280}
.status-expired{background:#FEE2E2;color:#991B1B}

.clean-table{
    width:100%;
    border-collapse:collapse;
    font-size:13.5px;
}

.clean-table th{
    background:#F9FAFB;
    padding:12px 16px;
    text-align:left;
    font-size:11px;
    color:#6B7280;
    text-transform:uppercase;
    letter-spacing:.4px;
    border-bottom:1px solid #E5E7EB;
}

.clean-table td{
    padding:14px 16px;
    border-bottom:1px solid #F3F4F6;
}

.client-avatar{
    width:38px;
    height:38px;
    border-radius:12px;
    background:#EFF6FF;
    color:#2563EB;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
    flex-shrink:0;
    overflow:hidden;
}

.client-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.progress{
    width:100%;
    height:8px;
    background:#F3F4F6;
    border-radius:10px;
    overflow:hidden;
}

.progress-fill{
    height:100%;
    background:#2563EB;
    border-radius:10px;
}

@media(max-width:1100px){
    .dash-grid-4{grid-template-columns:repeat(2,1fr)}
    .dash-grid-3{grid-template-columns:1fr}
}

@media(max-width:650px){
    .dash-grid-4{grid-template-columns:1fr}
    .quick-actions{grid-template-columns:1fr}
}
</style>

<div class="dash-hero">
    <div style="position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
        <div>
            <h1>Welcome Back 👋</h1>
            <p><?= date('l, d F Y') ?> · Master Admin Dashboard</p>
            <div class="dash-bc" style="margin-top:12px;color:rgba(255,255,255,.75)">
                <a href="dashboard" style="color:rgba(255,255,255,.75)">Dashboard</a>
                <span class="sep">›</span>
                <strong style="color:#fff">Overview</strong>
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="clients" style="background:#fff;color:#1D4ED8;text-decoration:none;padding:10px 15px;border-radius:12px;font-weight:800;font-size:13px">
                <i class="fa-solid fa-plus"></i> Add Client
            </a>
            <a href="ManageBilling" style="background:rgba(255,255,255,.14);color:#fff;text-decoration:none;padding:10px 15px;border-radius:12px;font-weight:800;font-size:13px;border:1px solid rgba(255,255,255,.25)">
                <i class="fa-solid fa-file-invoice-dollar"></i> New Billing
            </a>
        </div>
    </div>
</div>

<div class="dash-grid-4">
    <div class="metric-card">
        <div class="metric-top">
            <div>
                <div class="metric-label">Total Clients</div>
                <div class="metric-value"><?= $total_clients ?></div>
                <div class="metric-sub"><?= $active_clients ?> active clients</div>
            </div>
            <div class="metric-icon" style="background:#DBEAFE;color:#2563EB">
                <i class="fa-solid fa-building"></i>
            </div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-top">
            <div>
                <div class="metric-label">Modules</div>
                <div class="metric-value"><?= $total_modules ?></div>
                <div class="metric-sub"><?= $total_databases ?> database configs</div>
            </div>
            <div class="metric-icon" style="background:#EDE9FE;color:#7C3AED">
                <i class="fa-solid fa-puzzle-piece"></i>
            </div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-top">
            <div>
                <div class="metric-label">Licenses</div>
                <div class="metric-value"><?= $total_licenses ?></div>
                <div class="metric-sub"><?= $active_licenses ?> active · <?= $trial_licenses ?> trial</div>
            </div>
            <div class="metric-icon" style="background:#D1FAE5;color:#059669">
                <i class="fa-solid fa-key"></i>
            </div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-top">
            <div>
                <div class="metric-label">Billing</div>
                <div class="metric-value">₹<?= number_format($total_billing, 0) ?></div>
                <div class="metric-sub">Unpaid ₹<?= number_format($unpaid_billing, 0) ?></div>
            </div>
            <div class="metric-icon" style="background:#FEF3C7;color:#D97706">
                <i class="fa-solid fa-indian-rupee-sign"></i>
            </div>
        </div>
    </div>
</div>

<div class="dash-grid-3">
    <div class="dash-card">
        <div class="card-head">
            <div>
                <div class="card-title">Billing Trend</div>
                <div class="card-sub">Recent monthly billing amount</div>
            </div>
            <div style="display:flex;gap:18px">
                <div>
                    <div style="font-size:11px;color:#6B7280;font-weight:800">PAID</div>
                    <div style="font-size:15px;font-weight:900;color:#059669">₹<?= number_format($paid_billing, 0) ?></div>
                </div>
                <div>
                    <div style="font-size:11px;color:#6B7280;font-weight:800">UNPAID</div>
                    <div style="font-size:15px;font-weight:900;color:#D97706">₹<?= number_format($unpaid_billing, 0) ?></div>
                </div>
            </div>
        </div>
        <div style="height:280px;padding:18px">
            <canvas id="billingChart"></canvas>
        </div>
    </div>

    <div class="dash-card">
        <div class="card-head">
            <div>
                <div class="card-title">Quick Actions</div>
                <div class="card-sub">Fast admin shortcuts</div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="clients"><i class="fa-solid fa-building"></i> Clients</a>
            <a href="ClientDatabase"><i class="fa-solid fa-database"></i> Databases</a>
            <a href="ModalKeys"><i class="fa-solid fa-puzzle-piece"></i> Modules</a>
            <a href="Licenses"><i class="fa-solid fa-key"></i> Licenses</a>
            <a href="Billing"><i class="fa-solid fa-file-invoice"></i> Billing</a>
            <a href="ManageBilling"><i class="fa-solid fa-plus"></i> Add Bill</a>
        </div>

        <div style="padding:0 16px 16px">
            <div style="background:#F9FAFB;border:1px dashed #CBD5E1;border-radius:14px;padding:14px">
                <div style="font-size:12px;color:#6B7280;font-weight:800;text-transform:uppercase">Alert</div>
                <div style="font-size:22px;font-weight:900;color:#991B1B;margin-top:4px"><?= $expired_licenses ?></div>
                <div style="font-size:12px;color:#6B7280">Expired licenses need review</div>
            </div>
        </div>
    </div>
</div>

<div class="dash-grid-3">
    <div class="dash-card">
        <div class="card-head">
            <div>
                <div class="card-title">Recent Clients</div>
                <div class="card-sub">Latest registered clients</div>
            </div>
            <a href="clients" style="font-size:12px;font-weight:800;color:#2563EB;text-decoration:none">View All →</a>
        </div>

        <div style="overflow-x:auto">
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($recentClients && $recentClients->num_rows > 0): ?>
                        <?php while($c = $recentClients->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div class="client-avatar">
                                            <?php if(!empty($c['logo'])): ?>
                                                <img src="../<?= e($c['logo']) ?>" alt="">
                                            <?php else: ?>
                                                <?= strtoupper(substr($c['client_name'],0,1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:800;color:#111827"><?= e($c['client_name']) ?></div>
                                            <div style="font-size:12px;color:#6B7280"><?= e($c['client_code']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= e($c['phone'] ?: 'N/A') ?></div>
                                    <div style="font-size:12px;color:#6B7280"><?= e($c['email'] ?: '') ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $c['status']==='active'?'status-active':'status-inactive' ?>">
                                        <?= ucfirst(e($c['status'])) ?>
                                    </span>
                                </td>
                                <td><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;color:#9CA3AF;padding:30px">No clients found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="dash-card">
        <div class="card-head">
            <div>
                <div class="card-title">Module Usage</div>
                <div class="card-sub">Database configs by module</div>
            </div>
        </div>

        <div style="padding:16px">
            <?php if($moduleStats && $moduleStats->num_rows > 0): ?>
                <?php 
                $maxModuleCount = 1;
                $rows = [];
                while($m = $moduleStats->fetch_assoc()) {
                    $rows[] = $m;
                    $maxModuleCount = max($maxModuleCount, (int)$m['total_db']);
                }
                foreach($rows as $m):
                    $pct = ((int)$m['total_db'] / $maxModuleCount) * 100;
                ?>
                    <div style="margin-bottom:14px">
                        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                            <div>
                                <div style="font-size:13px;font-weight:800;color:#111827"><?= e($m['module_name']) ?></div>
                                <div style="font-size:11px;color:#6B7280"><?= e($m['module_key']) ?></div>
                            </div>
                            <div style="font-size:13px;font-weight:900;color:#2563EB"><?= (int)$m['total_db'] ?></div>
                        </div>
                        <div class="progress">
                            <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:20px;text-align:center;color:#9CA3AF">No modules found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="dash-card">
    <div class="card-head">
        <div>
            <div class="card-title">Recent Billing</div>
            <div class="card-sub">Latest billing activity</div>
        </div>
        <a href="Billing" style="font-size:12px;font-weight:800;color:#2563EB;text-decoration:none">View All →</a>
    </div>

    <div style="overflow-x:auto">
        <table class="clean-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Module</th>
                    <th>Month</th>
                    <th>Type</th>
                    <th>Users</th>
                    <th>Amount</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php if($recentBilling && $recentBilling->num_rows > 0): ?>
                    <?php while($b = $recentBilling->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= e($b['client_name'] ?: 'N/A') ?></strong>
                                <div style="font-size:12px;color:#6B7280"><?= e($b['client_code'] ?: '') ?></div>
                            </td>
                            <td><?= e($b['module_key']) ?></td>
                            <td><?= e($b['billing_month']) ?></td>
                            <td><?= ucfirst(e($b['license_type'] ?? 'monthly')) ?></td>
                            <td><?= (int)$b['users_count'] ?></td>
                            <td style="font-weight:900;color:#111827">₹<?= number_format((float)$b['amount'], 2) ?></td>
                            <td>
                                <span class="badge <?= $b['payment_status']==='paid'?'status-paid':'status-unpaid' ?>">
                                    <?= ucfirst(e($b['payment_status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;color:#9CA3AF;padding:30px">No billing records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$labelsJson = json_encode($monthlyBillingLabels);
$valuesJson = json_encode($monthlyBillingValues);

$extra_scripts = <<<JS
<script>
new Chart(document.getElementById('billingChart'), {
    type: 'line',
    data: {
        labels: $labelsJson,
        datasets: [{
            label: 'Billing Amount',
            data: $valuesJson,
            borderColor: '#2563EB',
            backgroundColor: 'rgba(37,99,235,.10)',
            fill: true,
            tension: .42,
            pointRadius: 4,
            pointBackgroundColor: '#2563EB',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return '₹' + Number(ctx.raw || 0).toLocaleString('en-IN');
                    }
                }
            }
        },
        scales: {
            y: {
                ticks: {
                    callback: function(v) {
                        return '₹' + Number(v).toLocaleString('en-IN');
                    },
                    color: '#9CA3AF',
                    font: { size: 11 }
                },
                grid: { color: 'rgba(0,0,0,.05)' },
                border: { display: false }
            },
            x: {
                ticks: { color: '#9CA3AF', font: { size: 11 } },
                grid: { display: false },
                border: { display: false }
            }
        }
    }
});
</script>
JS;

$page_content = ob_get_clean();
include 'header.php';
echo $page_content;
include 'footer.php';
?>

<script src="../includes/assets/scripts.js"></script>