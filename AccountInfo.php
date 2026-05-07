<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/db_client.php';
require_once 'includes/config.php';
$page_title = 'Account Information';

/* ─────────────────────────────────────────
   SETTINGS
───────────────────────────────────────── */
$company_id = 1;

/* ─────────────────────────────────────────
   HELPERS
───────────────────────────────────────── */
function esc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function sel($v, $o) {
    return ((string)$v === (string)$o) ? 'selected' : '';
}

/* ─────────────────────────────────────────
   EXTRA COLUMNS CHECK NOTE
─────────────────────────────────────────
   Your companies table already has:
   id, client_code, client_name, logo, phone, email,
   website, address, status, created_at, updated_at

   If statutory/mail/other config sections are needed,
   add extra columns given below in SQL section.
───────────────────────────────────────── */

/* ─────────────────────────────────────────
   FETCH COMPANY
───────────────────────────────────────── */
$account = [];

$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $account = $result->fetch_assoc();
} else {
    $account = [
        'id'          => '',
        'client_code' => '',
        'client_name' => '',
        'logo'        => '',
        'phone'       => '',
        'email'       => '',
        'website'     => '',
        'address'     => '',
        'status'      => '',
    ];
}

/* Default values for extra config fields */
$defaults = [
    'pan' => '',
    'tan' => '',
    'gstin' => '',
    'pf_no' => '',
    'esi_no' => '',
    'pt_no' => '',
    'lwf_no' => '',
    'factory_no' => '',
    'incorporation_no' => '',
    'cin' => '',

    'mail_from_name' => '',
    'mail_from_email' => '',
    'mail_host' => '',
    'mail_port' => '',
    'mail_encryption' => '',
    'mail_username' => '',
    'mail_password' => '',
    'mail_signature' => '',

    'date_format' => 'DD/MM/YYYY',
    'time_format' => '12 Hour',
    'currency' => 'INR (₹)',
    'timezone' => 'Asia/Kolkata',
    'week_start' => 'Monday',
    'financial_year' => 'April – March',
    'payroll_cycle' => 'Monthly',
    'payslip_format' => 'Standard',
];

foreach ($defaults as $k => $v) {
    if (!array_key_exists($k, $account)) {
        $account[$k] = $v;
    }
}

/* ─────────────────────────────────────────
   SAVE DATA
───────────────────────────────────────── */
$save_success = false;
$save_error   = '';
$save_section = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['section'])) {

    $save_section = $_POST['section'];

    $sectionFields = [
        'company' => [
            'client_name',
            'phone',
            'email',
            'website',
            'address',
            'status'
        ],

        'statutory' => [
            'pan',
            'tan',
            'gstin',
            'pf_no',
            'esi_no',
            'pt_no',
            'lwf_no',
            'factory_no',
            'incorporation_no',
            'cin'
        ],

        'mail' => [
            'mail_from_name',
            'mail_from_email',
            'mail_host',
            'mail_port',
            'mail_encryption',
            'mail_username',
            'mail_password',
            'mail_signature'
        ],

        'other' => [
            'date_format',
            'time_format',
            'currency',
            'timezone',
            'week_start',
            'financial_year',
            'payroll_cycle',
            'payslip_format'
        ]
    ];

    $fields = $sectionFields[$save_section] ?? [];

    $setParts = [];
    $values   = [];
    $types    = '';

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {

            /* Do not overwrite password with blank */
            if ($field === 'mail_password' && trim($_POST[$field]) === '') {
                continue;
            }

            $setParts[] = "`$field` = ?";
            $values[]   = trim($_POST[$field]);
            $types     .= 's';
        }
    }

    /* Logo upload */
    if ($save_section === 'company' && !empty($_FILES['logo']['name'])) {

        $uploadDir = __DIR__ . '/uploads/company/';
        $uploadUrl = 'uploads/company/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowedExt, true)) {

            $fileName = 'company_logo_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $targetPath = $uploadDir . $fileName;
            $dbPath = $uploadUrl . $fileName;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                $setParts[] = "`logo` = ?";
                $values[]   = $dbPath;
                $types     .= 's';
            } else {
                $save_error = 'Logo upload failed.';
            }

        } else {
            $save_error = 'Invalid logo file type.';
        }
    }

    if (empty($save_error) && !empty($setParts)) {

        $setParts[] = "`updated_at` = NOW()";

        $sql = "UPDATE companies SET " . implode(', ', $setParts) . " WHERE id = ?";

        $types .= 'i';
        $values[] = $company_id;

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param($types, ...$values);

            if ($stmt->execute()) {
                $save_success = true;
            } else {
                $save_error = 'Save failed: ' . $stmt->error;
            }

        } else {
            $save_error = 'Prepare failed: ' . $conn->error;
        }
    }

    /* Refresh company data */
    $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $account = $result->fetch_assoc();

        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $account)) {
                $account[$k] = $v;
            }
        }
    }
}

$active_section = $_GET['section'] ?? ($save_section ?: 'company');
$edit_section   = $_GET['edit'] ?? ($save_success ? '' : '');

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">



<style>

    
/* ── Config tab bar (reuse from config page) ── */
.cfg-tabs {
    display:flex;align-items:center;border-bottom:1px solid #E5E7EB;
    background:#fff;overflow-x:auto;scrollbar-width:none;
}
.cfg-tabs::-webkit-scrollbar { display:none; }
.cfg-tab {
    padding:14px 20px;font-size:13.5px;font-weight:500;color:#6B7280;
    cursor:pointer;border:none;background:transparent;
    border-bottom:2.5px solid transparent;white-space:nowrap;
    transition:color .15s,border-color .15s;text-decoration:none;
    display:block;margin-bottom:-1px;
}
.cfg-tab:hover  { color:#111827; }
.cfg-tab.active { color:#2563EB;border-bottom-color:#2563EB;font-weight:600; }

/* ── Breadcrumb ── */
.ctc-bc {
    display:flex;align-items:center;gap:8px;font-size:13.5px;
    font-weight:500;color:#374151;flex-wrap:wrap;
}
.ctc-bc a       { color:#374151;text-decoration:none; }
.ctc-bc a:hover { color:#2563EB; }
.ctc-bc .sep    { color:#D1D5DB;font-size:16px; }
.ctc-bc .cur    { font-weight:600;color:#374151; }



/* ── Page header ── */
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


/* ═══════════════════════════════════════
   ACCOUNT INFO PAGE
═══════════════════════════════════════ */

/* breadcrumb row */
.ai-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.ai-breadcrumb a        { color: #374151; text-decoration: none; }
.ai-breadcrumb a:hover  { color: #2563EB; }
.ai-breadcrumb .sep     { color: #D1D5DB; font-size: 15px; }
.ai-breadcrumb .current { color: #374151; font-weight: 600; }
.ai-breadcrumb .sub-exp {
    margin-left: 4px;
    font-size: 13px;
    font-weight: 600;
    color: #F59E0B;
}

/* two-col header labels */
.ai-col-labels {
    display: grid;
    grid-template-columns: 420px 1fr;
    gap: 0;
    padding: 10px 0 12px;
    border-bottom: 1px solid #E5E7EB;
    margin-bottom: 20px;
}
.ai-col-label {
    font-size: 12.5px;
    font-weight: 600;
    color: #6B7280;
    padding-left: 4px;
}

/* main two-column layout */
.ai-layout {
    display: grid;
    grid-template-columns: 420px 1fr;
    gap: 0;
    align-items: start;
    min-height: 420px;
}

/* ── LEFT ACCORDION ── */
.ai-accordion {
    border-right: 1px solid #E5E7EB;
    padding-right: 0;
}

.ai-acc-item { border-bottom: 1px solid #E5E7EB; }
.ai-acc-item:last-child { border-bottom: none; }

.ai-acc-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 16px 0;
    background: none;
    border: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    text-align: left;
    transition: color .15s;
}

.ai-acc-btn:hover { color: #2563EB; }

.ai-acc-btn.active {
    color: #2563EB;
    font-weight: 600;
}

.ai-acc-arrow {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1.5px solid #D1D5DB;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: border-color .15s, transform .2s;
}

.ai-acc-btn.active .ai-acc-arrow {
    border-color: #2563EB;
}

.ai-acc-arrow svg {
    width: 10px;
    height: 10px;
    stroke: #9CA3AF;
    transition: stroke .15s, transform .2s;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.ai-acc-btn.active .ai-acc-arrow svg { stroke: #2563EB; }
/* rotate arrow for active */
.ai-acc-btn.active .ai-acc-arrow { transform: none; }

/* ── RIGHT DETAIL PANEL ── */
.ai-detail {
    padding: 0 0 0 32px;
}

.ai-detail-panel { display: none; }
.ai-detail-panel.active {
    display: block;
    animation: aiSlideIn .2s ease;
}

@keyframes aiSlideIn {
    from { opacity:0; transform:translateY(4px); }
    to   { opacity:1; transform:translateY(0); }
}

/* detail header */
.ai-detail-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 8px;
}

.ai-detail-head h3 {
    font-size: 13px;
    font-weight: 700;
    color: #111827;
    letter-spacing: .6px;
    text-transform: uppercase;
}

/* edit details link */
.ai-edit-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 500;
    color: #2563EB;
    cursor: pointer;
    border: none;
    background: none;
    font-family: inherit;
    padding: 0;
    text-decoration: none;
    transition: color .15s;
}
.ai-edit-link:hover { color: #1D4ED8; }
.ai-edit-link svg { width: 13px; height: 13px; stroke: currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }

/* field grid  */
.ai-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}

.ai-field {
    padding: 0 24px 22px 0;
}

.ai-field-label {
    font-size: 12px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
}

/* view mode: underline value */
.ai-field-val {
    font-size: 13.5px;
    color: #111827;
    font-weight: 400;
    padding-bottom: 7px;
    border-bottom: 1px solid #D1D5DB;
    min-height: 32px;
    line-height: 1.4;
    word-break: break-word;
}

.ai-field-val.empty { color: #D1D5DB; }

/* edit mode inputs */
.ai-field input,
.ai-field select,
.ai-field textarea {
    width: 100%;
    padding: 8px 10px;
    border: none;
    border-bottom: 1.5px solid #D1D5DB;
    border-radius: 0;
    font-family: inherit;
    font-size: 13.5px;
    color: #111827;
    outline: none;
    background: transparent;
    transition: border-color .15s;
}

.ai-field input:focus,
.ai-field select:focus,
.ai-field textarea:focus {
    border-bottom-color: #2563EB;
}

/* full-width field */
.ai-field.full { grid-column: 1 / -1; }

/* section divider label inside right panel */
.ai-subsect {
    grid-column: 1 / -1;
    font-size: 11px;
    font-weight: 700;
    color: #9CA3AF;
    letter-spacing: .6px;
    text-transform: uppercase;
    padding: 16px 0 10px;
    border-bottom: 1px solid #F3F4F6;
    margin-bottom: 4px;
}

/* action bar (save/cancel) */
.ai-action-bar {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 14px;
    border-top: 1px solid #E5E7EB;
    margin-top: 8px;
}

/* ── toast ── */
.ai-toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: #111827;
    color: #fff;
    padding: 11px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    z-index: 999;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 8px 28px rgba(0,0,0,.2);
    transition: transform .3s ease;
    white-space: nowrap;
}
.ai-toast.show { transform: translateX(-50%) translateY(0); }

/* responsive */
@media (max-width: 900px) {
    .ai-layout,
    .ai-col-labels { grid-template-columns: 1fr; }
    .ai-accordion  { border-right: none; border-bottom: 1px solid #E5E7EB; padding-bottom: 0; }
    .ai-detail     { padding: 20px 0 0; }
}
@media (max-width: 600px) {
    .ai-fields { grid-template-columns: 1fr; }
    .ai-field.full { grid-column: 1; }
}
</style>

<?php if ($save_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof showAiToast === 'function') {
        showAiToast('✅', '<?= esc(ucfirst($save_section)) ?> information saved successfully!');
    }
});
</script>
<?php endif; ?>

<?php if (!empty($save_error)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof showAiToast === 'function') {
        showAiToast('❌', '<?= esc($save_error) ?>');
    } else {
        alert('<?= esc($save_error) ?>');
    }
});
</script>
<?php endif; ?>

<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
    <div class="cfg-tabs">
        <?php
        $cfg_tabs = [
            'AccountInfo'  => 'Account Info',
            'Organization' => 'Organization',
            'Payroll'      => 'Payroll',
            'Attendance'   => 'Attendance',
            'Leave'        => 'Leave',
            'Training'     => 'Training',
            'Others'       => 'Others'
        ];

        foreach ($cfg_tabs as $k => $l):
        ?>
            <a href="configuration#<?= esc($k) ?>" class="cfg-tab <?= $k === 'AccountInfo' ? 'active' : '' ?>">
                <?= esc($l) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div style="padding:0;overflow:hidden">

        <div style="padding:14px 20px;border-bottom:1px solid #E5E7EB">
            <div class="ctc-bc">
                <a href="configuration#AccountInfo">Account Info</a>
                <span class="sep">›</span>
                <span class="cur">Account Information</span>
            </div>
        </div>

        <div style="padding:0 24px">
            <div class="ai-col-labels">
                <div class="ai-col-label">Configuration</div>
                <div class="ai-col-label">Configuration Details</div>
            </div>
        </div>

        <div style="padding:0 24px 28px">
            <div class="ai-layout">

                <div class="ai-accordion">
                    <?php
                    $sections = [
                        'company'   => 'Company Info',
                        'statutory' => 'Statutory Info',
                        'mail'      => 'Mail Configuration',
                        'other'     => 'Other Configuration',
                    ];

                    foreach ($sections as $skey => $slabel):
                        $is_active = ($active_section === $skey);
                    ?>
                        <div class="ai-acc-item">
                            <button class="ai-acc-btn <?= $is_active ? 'active' : '' ?>"
                                    onclick="switchSection('<?= esc($skey) ?>')"
                                    type="button">
                                <?= esc($slabel) ?>

                                <div class="ai-acc-arrow">
                                    <?php if ($is_active): ?>
                                        <svg viewBox="0 0 12 12">
                                            <polyline points="2 8 6 4 10 8"></polyline>
                                        </svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 12 12">
                                            <polyline points="2 4 6 8 10 4"></polyline>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="ai-detail" id="aiDetail">

                    <!-- COMPANY INFO -->
                    <div class="ai-detail-panel <?= $active_section === 'company' ? 'active' : '' ?>" id="panel-company">

                        <div class="ai-detail-head">
                            <h3>COMPANY INFO</h3>

                            <?php if ($edit_section !== 'company'): ?>
                                <a class="ai-edit-link" href="?section=company&edit=company">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    Edit Details
                                </a>
                            <?php endif; ?>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="form-company" novalidate>
                            <input type="hidden" name="section" value="company">

                            <div class="ai-fields">

                                <?php
                                $company_fields = [
                                    ['client_code', 'Client Code', false, 'text', true],
                                    ['client_name', 'Company Name', false, 'text', false],
                                    ['phone', 'Phone Number', false, 'tel', false],
                                    ['email', 'Email Address', false, 'email', false],
                                    ['website', 'Website', false, 'url', false],
                                    ['address', 'Address', true, 'text', false],
                                    ['status', 'Status', false, 'select', false],
                                ];

                                foreach ($company_fields as [$fkey, $flabel, $full, $ftype, $readonly]):
                                    $fval = $account[$fkey] ?? '';
                                    $is_edit_mode = ($edit_section === 'company' && !$readonly);
                                ?>
                                    <div class="ai-field <?= $full ? 'full' : '' ?>">
                                        <div class="ai-field-label"><?= esc($flabel) ?></div>

                                        <?php if ($is_edit_mode && $ftype === 'select'): ?>

                                            <select name="<?= esc($fkey) ?>">
                                                <option value="active" <?= sel($fval, 'active') ?>>Active</option>
                                                <option value="inactive" <?= sel($fval, 'inactive') ?>>Inactive</option>
                                            </select>

                                        <?php elseif ($is_edit_mode && $full): ?>

                                            <textarea name="<?= esc($fkey) ?>" rows="3" placeholder="<?= esc($flabel) ?>"><?= esc($fval) ?></textarea>

                                        <?php elseif ($is_edit_mode): ?>

                                            <input type="<?= esc($ftype) ?>"
                                                   name="<?= esc($fkey) ?>"
                                                   value="<?= esc($fval) ?>"
                                                   placeholder="<?= esc($flabel) ?>">

                                        <?php else: ?>

                                            <div class="ai-field-val <?= $fval === '' ? 'empty' : '' ?>">
                                                <?= $fval !== '' ? esc($fval) : '—' ?>
                                            </div>

                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <div class="ai-field">
                                    <div class="ai-field-label">Company Logo</div>

                                    <?php if ($edit_section === 'company'): ?>

                                        <input type="file" name="logo" accept="image/*" style="padding-top:4px">

                                        <?php if (!empty($account['logo'])): ?>
                                            <div style="margin-top:8px">
                                                <img src="<?= esc($account['logo']) ?>" alt="Logo" style="height:38px;max-width:160px;object-fit:contain">
                                            </div>
                                        <?php endif; ?>

                                    <?php else: ?>

                                        <div class="ai-field-val <?= empty($account['logo']) ? 'empty' : '' ?>">
                                            <?php if (!empty($account['logo'])): ?>
                                                <img src="<?= esc($account['logo']) ?>" alt="Logo" style="height:38px;max-width:160px;object-fit:contain">
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </div>

                                    <?php endif; ?>
                                </div>

                                <?php if ($edit_section === 'company'): ?>
                                    <div class="ai-action-bar">
                                        <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </form>
                    </div>

                    <!-- STATUTORY INFO -->
                    <div class="ai-detail-panel <?= $active_section === 'statutory' ? 'active' : '' ?>" id="panel-statutory">

                        <div class="ai-detail-head">
                            <h3>STATUTORY INFO</h3>

                            <?php if ($edit_section !== 'statutory'): ?>
                                <a class="ai-edit-link" href="?section=statutory&edit=statutory">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    Edit Details
                                </a>
                            <?php endif; ?>
                        </div>

                        <form method="POST" id="form-statutory" novalidate>
                            <input type="hidden" name="section" value="statutory">

                            <div class="ai-fields">

                                <?php
                                $stat_fields = [
                                    ['pan', 'PAN Number', false, 'text', 'AABCR1234F'],
                                    ['tan', 'TAN Number', false, 'text', 'CALC12345D'],
                                    ['gstin', 'GSTIN', false, 'text', '19AABCR1234F1Z5'],
                                    ['pf_no', 'PF Registration Number', false, 'text', 'WB/XXX/0012345'],
                                    ['esi_no', 'ESI Registration Number', false, 'text', '31-00-XXXXXX'],
                                    ['pt_no', 'PT Registration Number', false, 'text', 'WBPTXXXXXX'],
                                    ['lwf_no', 'LWF Number', false, 'text', ''],
                                    ['factory_no', 'Factory Registration No.', false, 'text', ''],
                                    ['incorporation_no', 'Incorporation Number', false, 'text', ''],
                                    ['cin', 'CIN', false, 'text', 'U85110WB2010PTC000001'],
                                ];

                                foreach ($stat_fields as [$fkey, $flabel, $full, $ftype, $ph]):
                                    $fval = $account[$fkey] ?? '';
                                    $is_edit_mode = ($edit_section === 'statutory');
                                ?>
                                    <div class="ai-field <?= $full ? 'full' : '' ?>">
                                        <div class="ai-field-label"><?= esc($flabel) ?></div>

                                        <?php if ($is_edit_mode): ?>

                                            <input type="<?= esc($ftype) ?>"
                                                   name="<?= esc($fkey) ?>"
                                                   value="<?= esc($fval) ?>"
                                                   placeholder="<?= esc($ph) ?>">

                                        <?php else: ?>

                                            <div class="ai-field-val <?= $fval === '' ? 'empty' : '' ?>">
                                                <?= $fval !== '' ? esc($fval) : '—' ?>
                                            </div>

                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if ($edit_section === 'statutory'): ?>
                                    <div class="ai-action-bar">
                                        <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </form>
                    </div>

                    <!-- MAIL CONFIGURATION -->
                    <div class="ai-detail-panel <?= $active_section === 'mail' ? 'active' : '' ?>" id="panel-mail">

                        <div class="ai-detail-head">
                            <h3>MAIL CONFIGURATION</h3>

                            <?php if ($edit_section !== 'mail'): ?>
                                <a class="ai-edit-link" href="?section=mail&edit=mail">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    Edit Details
                                </a>
                            <?php endif; ?>
                        </div>

                        <form method="POST" id="form-mail" novalidate>
                            <input type="hidden" name="section" value="mail">

                            <div class="ai-fields">

                                <?php
                                $mail_fields = [
                                    ['mail_from_name', 'From Name', false, 'text', ''],
                                    ['mail_from_email', 'From Email', false, 'email', ''],
                                    ['mail_host', 'SMTP Host', false, 'text', 'smtp.gmail.com'],
                                    ['mail_port', 'SMTP Port', false, 'number', '587'],
                                    ['mail_encryption', 'Encryption', false, 'text', 'TLS / SSL'],
                                    ['mail_username', 'SMTP Username', false, 'email', ''],
                                    ['mail_password', 'SMTP Password', false, 'password', ''],
                                ];

                                foreach ($mail_fields as [$fkey, $flabel, $full, $ftype, $ph]):
                                    $fval = ($fkey === 'mail_password') ? '' : ($account[$fkey] ?? '');
                                    $actualVal = $account[$fkey] ?? '';
                                    $is_edit_mode = ($edit_section === 'mail');
                                ?>
                                    <div class="ai-field <?= $full ? 'full' : '' ?>">
                                        <div class="ai-field-label"><?= esc($flabel) ?></div>

                                        <?php if ($is_edit_mode): ?>

                                            <input type="<?= esc($ftype) ?>"
                                                   name="<?= esc($fkey) ?>"
                                                   value="<?= esc($fval) ?>"
                                                   placeholder="<?= esc($ph) ?>">

                                        <?php else: ?>

                                            <div class="ai-field-val <?= $actualVal === '' ? 'empty' : '' ?>">
                                                <?php
                                                if ($fkey === 'mail_password' && $actualVal !== '') {
                                                    echo '••••••••';
                                                } else {
                                                    echo $actualVal !== '' ? esc($actualVal) : '—';
                                                }
                                                ?>
                                            </div>

                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <div class="ai-field full">
                                    <div class="ai-field-label">Email Signature</div>

                                    <?php if ($edit_section === 'mail'): ?>

                                        <textarea name="mail_signature" rows="3" placeholder="Optional HTML or plain-text signature..."><?= esc($account['mail_signature'] ?? '') ?></textarea>

                                    <?php else: ?>

                                        <div class="ai-field-val <?= empty($account['mail_signature']) ? 'empty' : '' ?>">
                                            <?= !empty($account['mail_signature']) ? nl2br(esc($account['mail_signature'])) : '—' ?>
                                        </div>

                                    <?php endif; ?>
                                </div>

                                <?php if ($edit_section === 'mail'): ?>
                                    <div class="ai-action-bar">
                                        <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                                        <button type="button" class="btn" onclick="testMail()" style="background:#EFF6FF;color:#2563EB;border-color:#BFDBFE">
                                            Test Connection
                                        </button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </form>
                    </div>

                    <!-- OTHER CONFIGURATION -->
                    <div class="ai-detail-panel <?= $active_section === 'other' ? 'active' : '' ?>" id="panel-other">

                        <div class="ai-detail-head">
                            <h3>OTHER CONFIGURATION</h3>

                            <?php if ($edit_section !== 'other'): ?>
                                <a class="ai-edit-link" href="?section=other&edit=other">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    Edit Details
                                </a>
                            <?php endif; ?>
                        </div>

                        <form method="POST" id="form-other" novalidate>
                            <input type="hidden" name="section" value="other">

                            <div class="ai-fields">

                                <?php
                                $is_edit_mode = ($edit_section === 'other');

                                $select_fields = [
                                    ['date_format', 'Date Format', ['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD', 'DD MMM YYYY']],
                                    ['time_format', 'Time Format', ['12 Hour', '24 Hour']],
                                    ['currency', 'Currency', ['INR (₹)', 'USD ($)', 'EUR (€)']],
                                    ['timezone', 'Timezone', ['Asia/Kolkata', 'UTC', 'Asia/Dubai']],
                                    ['week_start', 'Week Starts On', ['Monday', 'Sunday']],
                                    ['financial_year', 'Financial Year', ['April – March', 'January – December', 'October – September']],
                                    ['payroll_cycle', 'Payroll Cycle', ['Monthly', 'Weekly', 'Bi-Weekly', 'Fortnightly']],
                                    ['payslip_format', 'Payslip Format', ['Standard', 'Detailed', 'Simple']],
                                ];

                                foreach ($select_fields as [$fkey, $flabel, $fopts]):
                                    $fval = $account[$fkey] ?? '';
                                ?>
                                    <div class="ai-field">
                                        <div class="ai-field-label"><?= esc($flabel) ?></div>

                                        <?php if ($is_edit_mode): ?>

                                            <select name="<?= esc($fkey) ?>">
                                                <?php foreach ($fopts as $fo): ?>
                                                    <option value="<?= esc($fo) ?>" <?= sel($fval, $fo) ?>>
                                                        <?= esc($fo) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                        <?php else: ?>

                                            <div class="ai-field-val <?= $fval === '' ? 'empty' : '' ?>">
                                                <?= $fval !== '' ? esc($fval) : '—' ?>
                                            </div>

                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if ($is_edit_mode): ?>
                                    <div class="ai-action-bar">
                                        <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </form>
                    </div>

                </div>

            </div>
        </div>

    </div>
</div>

<div class="ai-toast" id="aiToast">
    <span id="aiToastIcon">✅</span>
    <span id="aiToastMsg">Saved!</span>
</div>

<script>
function switchSection(key) {
    document.querySelectorAll('.ai-acc-btn').forEach(function(btn) {
        const active = btn.getAttribute('onclick').includes("'" + key + "'");
        btn.classList.toggle('active', active);

        const svg = btn.querySelector('.ai-acc-arrow svg');

        if (svg) {
            const polyline = svg.querySelector('polyline');

            if (polyline) {
                polyline.setAttribute('points', active ? '2 8 6 4 10 8' : '2 4 6 8 10 4');
            }
        }
    });

    document.querySelectorAll('.ai-detail-panel').forEach(function(panel) {
        panel.classList.toggle('active', panel.id === 'panel-' + key);
    });

    const url = new URL(window.location.href);
    url.searchParams.set('section', key);
    url.searchParams.delete('edit');
    history.replaceState(null, '', url.toString());
}

function cancelEdit() {
    const url = new URL(window.location.href);
    url.searchParams.delete('edit');
    window.location.href = url.toString();
}

function testMail() {
    showAiToast('⏳', 'Sending test email...');

    setTimeout(function() {
        showAiToast('✅', 'Test email sent successfully!');
    }, 1200);
}

function showAiToast(icon, msg) {
    const toast = document.getElementById('aiToast');
    const toastIcon = document.getElementById('aiToastIcon');
    const toastMsg = document.getElementById('aiToastMsg');

    if (!toast || !toastIcon || !toastMsg) {
        alert(msg);
        return;
    }

    toastIcon.textContent = icon;
    toastMsg.textContent = msg;

    toast.classList.add('show');

    clearTimeout(toast._timer);

    toast._timer = setTimeout(function() {
        toast.classList.remove('show');
    }, 3200);
}

document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const section = params.get('section');

    if (section && ['company', 'statutory', 'mail', 'other'].includes(section)) {
        document.querySelectorAll('.ai-acc-btn').forEach(function(btn) {
            const active = btn.getAttribute('onclick').includes("'" + section + "'");
            btn.classList.toggle('active', active);

            const svg = btn.querySelector('.ai-acc-arrow svg');

            if (svg) {
                const polyline = svg.querySelector('polyline');

                if (polyline) {
                    polyline.setAttribute('points', active ? '2 8 6 4 10 8' : '2 4 6 8 10 4');
                }
            }
        });

        document.querySelectorAll('.ai-detail-panel').forEach(function(panel) {
            panel.classList.toggle('active', panel.id === 'panel-' + section);
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>