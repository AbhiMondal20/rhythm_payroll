<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/config.php';
require_once 'includes/db_client.php';
$page_title = 'Organization Management';

function esc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function postv($key, $default = '') {
    return trim((string)($_POST[$key] ?? $default));
}

$mode = $_GET['mode'] ?? 'view';
$selected_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$toast_msg  = $_SESSION['toast_msg'] ?? '';
$toast_icon = $_SESSION['toast_icon'] ?? '✅';
unset($_SESSION['toast_msg'], $_SESSION['toast_icon']);

$fields = [
    'client_code', 'client_name', 'logo', 'phone', 'email', 'website', 'address', 'status',
    'pan', 'tan', 'gstin', 'pf_no', 'esi_no', 'pt_no', 'lwf_no', 'factory_no', 'incorporation_no', 'cin',
    'mail_from_name', 'mail_from_email', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'mail_signature',
    'date_format', 'time_format', 'currency', 'timezone', 'week_start', 'financial_year', 'payroll_cycle', 'payslip_format'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'add_org' || $action === 'save_org') {
        $data = [];
        foreach ($fields as $f) {
            $data[$f] = postv($f);
        }

        if ($data['client_code'] === '' || $data['client_name'] === '') {
            $_SESSION['toast_icon'] = '⚠';
            $_SESSION['toast_msg'] = 'Client Code and Company Name are required.';
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }

        if ($data['status'] === '') {
            $data['status'] = 'active';
        }

        if ($action === 'add_org') {
            $sql = "INSERT INTO companies
            (`client_code`, `client_name`, `logo`, `phone`, `email`, `website`, `address`, `status`,
            `pan`, `tan`, `gstin`, `pf_no`, `esi_no`, `pt_no`, `lwf_no`, `factory_no`, `incorporation_no`, `cin`,
            `mail_from_name`, `mail_from_email`, `mail_host`, `mail_port`, `mail_encryption`, `mail_username`, `mail_password`, `mail_signature`,
            `date_format`, `time_format`, `currency`, `timezone`, `week_start`, `financial_year`, `payroll_cycle`, `payslip_format`)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                str_repeat('s', 34),
                $data['client_code'], $data['client_name'], $data['logo'], $data['phone'], $data['email'], $data['website'], $data['address'], $data['status'],
                $data['pan'], $data['tan'], $data['gstin'], $data['pf_no'], $data['esi_no'], $data['pt_no'], $data['lwf_no'], $data['factory_no'], $data['incorporation_no'], $data['cin'],
                $data['mail_from_name'], $data['mail_from_email'], $data['mail_host'], $data['mail_port'], $data['mail_encryption'], $data['mail_username'], $data['mail_password'], $data['mail_signature'],
                $data['date_format'], $data['time_format'], $data['currency'], $data['timezone'], $data['week_start'], $data['financial_year'], $data['payroll_cycle'], $data['payslip_format']
            );

            if ($stmt->execute()) {
                $_SESSION['toast_icon'] = '✅';
                $_SESSION['toast_msg'] = 'Company added successfully!';
                header("Location: ?id=" . $stmt->insert_id . "&mode=view");
                exit;
            } else {
                $_SESSION['toast_icon'] = '❌';
                $_SESSION['toast_msg'] = 'Save failed: ' . $stmt->error;
                header("Location: ?mode=add");
                exit;
            }
        }

        if ($action === 'save_org') {
            $org_id = (int)($_POST['org_id'] ?? 0);

            $sql = "UPDATE companies SET
            `client_code`=?, `client_name`=?, `logo`=?, `phone`=?, `email`=?, `website`=?, `address`=?, `status`=?,
            `pan`=?, `tan`=?, `gstin`=?, `pf_no`=?, `esi_no`=?, `pt_no`=?, `lwf_no`=?, `factory_no`=?, `incorporation_no`=?, `cin`=?,
            `mail_from_name`=?, `mail_from_email`=?, `mail_host`=?, `mail_port`=?, `mail_encryption`=?, `mail_username`=?, `mail_password`=?, `mail_signature`=?,
            `date_format`=?, `time_format`=?, `currency`=?, `timezone`=?, `week_start`=?, `financial_year`=?, `payroll_cycle`=?, `payslip_format`=?
            WHERE `id`=?";

            $stmt = $conn->prepare($sql);
            $types = str_repeat('s', 34) . 'i';

            $stmt->bind_param(
                $types,
                $data['client_code'], $data['client_name'], $data['logo'], $data['phone'], $data['email'], $data['website'], $data['address'], $data['status'],
                $data['pan'], $data['tan'], $data['gstin'], $data['pf_no'], $data['esi_no'], $data['pt_no'], $data['lwf_no'], $data['factory_no'], $data['incorporation_no'], $data['cin'],
                $data['mail_from_name'], $data['mail_from_email'], $data['mail_host'], $data['mail_port'], $data['mail_encryption'], $data['mail_username'], $data['mail_password'], $data['mail_signature'],
                $data['date_format'], $data['time_format'], $data['currency'], $data['timezone'], $data['week_start'], $data['financial_year'], $data['payroll_cycle'], $data['payslip_format'],
                $org_id
            );

            if ($stmt->execute()) {
                $_SESSION['toast_icon'] = '✅';
                $_SESSION['toast_msg'] = 'Company updated successfully!';
            } else {
                $_SESSION['toast_icon'] = '❌';
                $_SESSION['toast_msg'] = 'Update failed: ' . $stmt->error;
            }

            header("Location: ?id=" . $org_id . "&mode=view");
            exit;
        }
    }

    if ($action === 'delete_org') {
        $org_id = (int)($_POST['org_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM companies WHERE id=?");
        $stmt->bind_param("i", $org_id);

        if ($stmt->execute()) {
            $_SESSION['toast_icon'] = '✅';
            $_SESSION['toast_msg'] = 'Company deleted successfully!';
        } else {
            $_SESSION['toast_icon'] = '❌';
            $_SESSION['toast_msg'] = 'Delete failed: ' . $stmt->error;
        }

        header("Location: ?mode=view");
        exit;
    }
}

$organizations = [];
$res = $conn->query("SELECT * FROM companies ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $organizations[] = $row;
    }
}

if ($selected_id <= 0 && !empty($organizations)) {
    $selected_id = (int)$organizations[0]['id'];
}

$selected_org = null;
if ($selected_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM companies WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $selected_id);
    $stmt->execute();
    $selected_org = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

function fieldVal($org, $key) {
    return $org[$key] ?? '';
}

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<style>


/* ═══════════════════════════════════════════
   ORGANIZATION MANAGEMENT PAGE
═══════════════════════════════════════════ */


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



/* ── Page header row ── */
.om-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
    flex-wrap: wrap;
    gap: 10px;
}

.om-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.om-back-btn {
    width: 28px;
    height: 28px;
    border: 1.5px solid #D1D5DB;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: #fff;
    color: #374151;
    text-decoration: none;
    flex-shrink: 0;
    transition: border-color .15s, color .15s;
}
.om-back-btn:hover { border-color: #2563EB; color: #2563EB; }
.om-back-btn svg   { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

.om-title {
    font-size: 15.5px;
    font-weight: 700;
    color: #111827;
}

.om-subtitle {
    font-size: 12.5px;
    color: #9CA3AF;
    margin-top: 2px;
}

/* ── Main layout ── */
.om-layout {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 0;
    min-height: 460px;
}

/* ── Left list panel ── */
.om-list-panel {
    border-right: 1px solid #E5E7EB;
    padding: 16px 0 16px 0;
}

/* search */
.om-search-wrap {
    padding: 0 16px 12px;
    position: relative;
}

.om-search-wrap svg {
    position: absolute;
    left: 28px;
    top: 50%;
    transform: translateY(-55%);
    width: 15px;
    height: 15px;
    stroke: #9CA3AF;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
}

.om-search-wrap input {
    width: 100%;
    padding: 9px 12px 9px 36px;
    border: 1.5px solid #E5E7EB;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    color: #374151;
    outline: none;
    transition: border-color .15s;
    background: #fff;
}

.om-search-wrap input:focus { border-color: #2563EB; }

/* org list item */
.om-list-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 16px;
    cursor: pointer;
    border-radius: 7px;
    margin: 0 8px 2px;
    transition: background .15s;
    text-decoration: none;
}

.om-list-item:hover { background: #F3F4F6; }

.om-list-item.active {
    background: #EFF6FF;
}

.om-list-item-name {
    font-size: 13.5px;
    font-weight: 600;
    color: #111827;
}

.om-list-item.active .om-list-item-name { color: #1D4ED8; }

.om-list-item-code {
    font-size: 11px;
    color: #9CA3AF;
    margin-top: 1px;
}

/* ── Right detail panel ── */
.om-detail-panel {
    padding: 20px 24px 28px;
}

.om-detail-label {
    font-size: 12.5px;
    font-weight: 600;
    color: #6B7280;
    margin-bottom: 16px;
}

/* Company Information card */
.om-info-card {
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 16px;
}

.om-info-card-head {
    padding: 13px 20px;
    background: #FAFAFA;
    border-bottom: 1px solid #E5E7EB;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.om-info-card-body {
    padding: 0 20px 8px;
}

/* field grid */
.om-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}

.om-field {
    padding: 16px 16px 16px 0;
    border-bottom: 1px solid #F3F4F6;
}

.om-field:nth-child(even) {
    padding-left: 16px;
    border-left: 1px solid #F3F4F6;
}

.om-field:last-child,
.om-field:nth-last-child(2):nth-child(odd) {
    border-bottom: none;
}

.om-field-label {
    font-size: 12px;
    color: #6B7280;
    font-weight: 400;
    margin-bottom: 6px;
}

.om-field-val {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    line-height: 1.4;
    min-height: 22px;
}

.om-field-val.empty { color: #D1D5DB; font-weight: 400; font-style: italic; font-size: 13px; }

/* edit/add inputs */
.om-field input,
.om-field select,
.om-field textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1.5px solid #E5E7EB;
    border-radius: 7px;
    font-size: 13.5px;
    font-family: inherit;
    color: #111827;
    outline: none;
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
}

.om-field input:focus,
.om-field select:focus,
.om-field textarea:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37,99,235,.08);
}

.om-field.full       { grid-column: 1 / -1; border-left: none; padding-left: 0; }
.om-field-label.req::after { content: ' *'; color: #DC2626; }

/* action bar */
.om-action-bar {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 0 0;
    margin-top: 4px;
    border-top: 1px solid #E5E7EB;
}

/* empty / no-selection state */
.om-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 300px;
    color: #9CA3AF;
    font-size: 13.5px;
    gap: 10px;
}

.om-empty svg {
    width: 48px;
    height: 48px;
    stroke: #D1D5DB;
    fill: none;
    stroke-width: 1.5;
}

/* status badge */
.om-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 20px;
}

/* delete confirm */
.del-confirm {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,16,32,.45);
    z-index: 600;
    align-items: center;
    justify-content: center;
    padding: 16px;
    backdrop-filter: blur(2px);
}
.del-confirm.open { display: flex; }
.del-box {
    background: #fff;
    border-radius: 14px;
    max-width: 400px;
    width: 100%;
    padding: 28px;
    text-align: center;
    box-shadow: 0 20px 50px rgba(0,0,0,.2);
    animation: popIn .2s ease;
}
@keyframes popIn { from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)} }


.om-toast {
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
    z-index: 99999;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 8px 28px rgba(0,0,0,.2);
    transition: transform .3s ease;
    white-space: nowrap;
}
.om-toast.show { transform: translateX(-50%) translateY(0); }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">

    <div class="cfg-tabs">
        <?php foreach(['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
            <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k==='Organization'?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>

    <div class="om-header" style="padding:10px 32px;overflow:hidden; border-bottom:1px solid #E5E7EB">
        <div style="padding:14px 20px;">
            <div class="ctc-bc">
                <a href="configuration#Organization">Organization</a>
                <span class="sep">›</span>
                <span class="cur">Details</span>
            </div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php if ($mode === 'view' && $selected_org): ?>
                <a href="?id=<?= (int)$selected_id ?>&mode=edit" class="btn" style="text-decoration:none;">Edit Details</a>
            <?php endif; ?>

            <a href="?mode=add" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Organization
            </a>
        </div>
    </div>

    <div class="om-layout">

        <div class="om-list-panel">
            <div class="om-search-wrap">
                <svg viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="omSearchInput" placeholder="Search items" oninput="omSearch(this.value)">
            </div>

            <div id="omOrgList">
                <?php foreach ($organizations as $org): ?>
                    <a href="?id=<?= (int)$org['id'] ?>&mode=view"
                       class="om-list-item <?= $selected_id == $org['id'] && $mode !== 'add' ? 'active' : '' ?>"
                       data-name="<?= strtolower(esc(($org['client_name'] ?? '') . ' ' . ($org['client_code'] ?? '') . ' ' . ($org['email'] ?? '') . ' ' . ($org['phone'] ?? ''))) ?>">
                        <div>
                            <div class="om-list-item-name"><?= esc($org['client_name']) ?></div>
                            <div class="om-list-item-code"><?= esc($org['client_code']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div id="omNoResults" style="display:none;padding:20px 16px;font-size:13px;color:#9CA3AF;text-align:center">
                No organisations found
            </div>
        </div>

        <div class="om-detail-panel">

            <?php if ($mode === 'view' && $selected_org): ?>

                <div class="om-detail-label">Organization Details</div>

                <div class="om-info-card">
                    <div class="om-info-card-head">Company Information</div>
                    <div class="om-info-card-body">
                        <div class="om-fields">
                            <?php
                            $view_fields = [
                                ['client_code', 'Client Code'],
                                ['client_name', 'Company Name'],
                                ['phone', 'Phone Number'],
                                ['email', 'Email Address'],
                                ['website', 'Website'],
                                ['address', 'Address'],
                                ['pan', 'PAN'],
                                ['tan', 'TAN'],
                                ['gstin', 'GSTIN'],
                                ['pf_no', 'PF No'],
                                ['esi_no', 'ESI No'],
                                ['pt_no', 'PT No'],
                                ['lwf_no', 'LWF No'],
                                ['factory_no', 'Factory No'],
                                ['incorporation_no', 'Incorporation No'],
                                ['cin', 'CIN'],
                                ['date_format', 'Date Format'],
                                ['time_format', 'Time Format'],
                                ['currency', 'Currency'],
                                ['timezone', 'Timezone'],
                                ['week_start', 'Week Start'],
                                ['financial_year', 'Financial Year'],
                                ['payroll_cycle', 'Payroll Cycle'],
                                ['payslip_format', 'Payslip Format'],
                            ];

                            foreach ($view_fields as [$key, $label]):
                                $value = fieldVal($selected_org, $key);
                            ?>
                                <div class="om-field">
                                    <div class="om-field-label"><?= esc($label) ?></div>
                                    <div class="om-field-val <?= $value === '' ? 'empty' : '' ?>">
                                        <?= $value !== '' ? esc($value) : '&nbsp;' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="om-field">
                                <div class="om-field-label">Status</div>
                                <div class="om-field-val">
                                    <?php $active = strtolower((string)$selected_org['status']) === 'active' || $selected_org['status'] == '1'; ?>
                                    <span class="om-status" style="background:<?= $active ? '#D1FAE5' : '#FEE2E2' ?>;color:<?= $active ? '#065F46' : '#991B1B' ?>">
                                        ● <?= $active ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="om-info-card">
                    <div class="om-info-card-head">Mail Settings</div>
                    <div class="om-info-card-body">
                        <div class="om-fields">
                            <?php
                            $mail_fields = [
                                ['mail_from_name', 'Mail From Name'],
                                ['mail_from_email', 'Mail From Email'],
                                ['mail_host', 'Mail Host'],
                                ['mail_port', 'Mail Port'],
                                ['mail_encryption', 'Mail Encryption'],
                                ['mail_username', 'Mail Username'],
                                ['mail_signature', 'Mail Signature'],
                            ];

                            foreach ($mail_fields as [$key, $label]):
                                $value = fieldVal($selected_org, $key);
                            ?>
                                <div class="om-field">
                                    <div class="om-field-label"><?= esc($label) ?></div>
                                    <div class="om-field-val <?= $value === '' ? 'empty' : '' ?>">
                                        <?= $value !== '' ? nl2br(esc($value)) : '&nbsp;' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="om-field">
                                <div class="om-field-label">Mail Password</div>
                                <div class="om-field-val">
                                    <?= !empty($selected_org['mail_password']) ? '••••••••' : '&nbsp;' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;margin-top:4px">
                    <button class="btn" style="color:#DC2626;border-color:#FEE2E2;background:#FFF5F5;font-size:12.5px"
                            onclick="document.getElementById('delConfirm').classList.add('open')">
                        Delete Organization
                    </button>
                </div>

            <?php elseif (($mode === 'edit' && $selected_org) || $mode === 'add'): ?>

                <?php
                $is_edit = $mode === 'edit';
                $form_org = $is_edit ? $selected_org : [];
                ?>

                <div class="om-detail-label"><?= $is_edit ? 'Edit Organization Details' : 'Add New Organization' ?></div>

                <form method="POST" id="<?= $is_edit ? 'editOrgForm' : 'addOrgForm' ?>" novalidate>
                    <input type="hidden" name="form_action" value="<?= $is_edit ? 'save_org' : 'add_org' ?>">
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="org_id" value="<?= (int)$selected_org['id'] ?>">
                    <?php endif; ?>

                    <div class="om-info-card">
                        <div class="om-info-card-head">Company Information</div>
                        <div class="om-info-card-body">
                            <div class="om-fields">
                                <?php
                                $input_fields = [
                                    ['client_code', 'Client Code', 'text', true],
                                    ['client_name', 'Company Name', 'text', true],
                                    ['logo', 'Logo URL', 'text', false],
                                    ['phone', 'Phone Number', 'text', false],
                                    ['email', 'Email Address', 'email', false],
                                    ['website', 'Website', 'url', false],
                                    ['address', 'Address', 'text', false],
                                    ['pan', 'PAN', 'text', false],
                                    ['tan', 'TAN', 'text', false],
                                    ['gstin', 'GSTIN', 'text', false],
                                    ['pf_no', 'PF No', 'text', false],
                                    ['esi_no', 'ESI No', 'text', false],
                                    ['pt_no', 'PT No', 'text', false],
                                    ['lwf_no', 'LWF No', 'text', false],
                                    ['factory_no', 'Factory No', 'text', false],
                                    ['incorporation_no', 'Incorporation No', 'text', false],
                                    ['cin', 'CIN', 'text', false],
                                ];

                                foreach ($input_fields as [$key, $label, $type, $required]):
                                ?>
                                    <div class="om-field">
                                        <div class="om-field-label <?= $required ? 'req' : '' ?>"><?= esc($label) ?></div>
                                        <input type="<?= esc($type) ?>" name="<?= esc($key) ?>"
                                               value="<?= esc(fieldVal($form_org, $key)) ?>"
                                               <?= $required ? 'required' : '' ?>>
                                    </div>
                                <?php endforeach; ?>

                                <div class="om-field">
                                    <div class="om-field-label">Status</div>
                                    <?php $st = fieldVal($form_org, 'status') ?: 'active'; ?>
                                    <select name="status">
                                        <option value="active" <?= strtolower($st) === 'active' || $st == '1' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= strtolower($st) === 'inactive' || $st == '0' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="om-info-card">
                        <div class="om-info-card-head">Mail Settings</div>
                        <div class="om-info-card-body">
                            <div class="om-fields">
                                <?php
                                $mail_inputs = [
                                    ['mail_from_name', 'Mail From Name', 'text'],
                                    ['mail_from_email', 'Mail From Email', 'email'],
                                    ['mail_host', 'Mail Host', 'text'],
                                    ['mail_port', 'Mail Port', 'text'],
                                    ['mail_encryption', 'Mail Encryption', 'text'],
                                    ['mail_username', 'Mail Username', 'text'],
                                    ['mail_password', 'Mail Password', 'password'],
                                    ['mail_signature', 'Mail Signature', 'text'],
                                ];

                                foreach ($mail_inputs as [$key, $label, $type]):
                                ?>
                                    <div class="om-field">
                                        <div class="om-field-label"><?= esc($label) ?></div>
                                        <input type="<?= esc($type) ?>" name="<?= esc($key) ?>"
                                               value="<?= esc(fieldVal($form_org, $key)) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="om-info-card">
                        <div class="om-info-card-head">Payroll Settings</div>
                        <div class="om-info-card-body">
                            <div class="om-fields">
                                <?php
                                $setting_inputs = [
                                    ['date_format', 'Date Format', 'text', 'd-m-Y'],
                                    ['time_format', 'Time Format', 'text', 'h:i A'],
                                    ['currency', 'Currency', 'text', 'INR'],
                                    ['timezone', 'Timezone', 'text', 'Asia/Kolkata'],
                                    ['week_start', 'Week Start', 'text', 'Monday'],
                                    ['financial_year', 'Financial Year', 'text', 'April-March'],
                                    ['payroll_cycle', 'Payroll Cycle', 'text', 'Monthly'],
                                    ['payslip_format', 'Payslip Format', 'text', 'Standard'],
                                ];

                                foreach ($setting_inputs as [$key, $label, $type, $placeholder]):
                                ?>
                                    <div class="om-field">
                                        <div class="om-field-label"><?= esc($label) ?></div>
                                        <input type="<?= esc($type) ?>" name="<?= esc($key) ?>"
                                               value="<?= esc(fieldVal($form_org, $key)) ?>"
                                               placeholder="<?= esc($placeholder) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="om-action-bar">
                        <a href="<?= $is_edit ? '?id=' . (int)$selected_id . '&mode=view' : '?mode=view' ?>" class="btn" style="text-decoration:none;">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <?= $is_edit ? 'Save Changes' : 'Save Organization' ?>
                        </button>
                    </div>
                </form>

            <?php else: ?>

                <div class="om-empty">
                    Select an organization from the list to view details
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<div class="del-confirm" id="delConfirm" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="del-box">
        <div style="width:56px;height:56px;background:#FEE2E2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px">🗑</div>
        <h3 style="font-size:16px;font-weight:700;color:#111827;margin-bottom:8px">Delete Organization?</h3>
        <p style="font-size:13px;color:#6B7280;line-height:1.6;margin-bottom:20px">
            This will permanently delete <strong><?= esc($selected_org['client_name'] ?? '') ?></strong>. This action cannot be undone.
        </p>
        <div style="display:flex;gap:8px;justify-content:center">
            <button class="btn" onclick="document.getElementById('delConfirm').classList.remove('open')" style="min-width:100px">Cancel</button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="form_action" value="delete_org">
                <input type="hidden" name="org_id" value="<?= (int)($selected_org['id'] ?? 0) ?>">
                <button type="submit" class="btn" style="background:#DC2626;color:#fff;border-color:#DC2626;min-width:100px">Delete</button>
            </form>
        </div>
    </div>
</div>

<div class="om-toast" id="omToastEl">
    <span id="omToastIcon">✅</span>
    <span id="omToastMsg">Done!</span>
</div>

<script>
function omSearch(q) {
    q = q.toLowerCase().trim();
    const items = document.querySelectorAll('.om-list-item');
    let visible = 0;

    items.forEach(function(item) {
        const match = !q || (item.dataset.name || '').includes(q);
        item.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    const noRes = document.getElementById('omNoResults');
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}

function validateOmForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    let ok = true;
    form.querySelectorAll('[required]').forEach(function(el) {
        if (!el.value.trim()) {
            el.style.borderColor = '#DC2626';
            el.style.boxShadow = '0 0 0 3px rgba(220,38,38,.08)';
            ok = false;
        } else {
            el.style.borderColor = '';
            el.style.boxShadow = '';
        }
    });

    return ok;
}

document.querySelectorAll('#editOrgForm, #addOrgForm').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (!validateOmForm(form.id)) {
            e.preventDefault();
            omToast('⚠', 'Please fill in all required fields.');
        }
    });
});

function omToast(icon, msg) {
    const t = document.getElementById('omToastEl');
    const ti = document.getElementById('omToastIcon');
    const tm = document.getElementById('omToastMsg');

    ti.textContent = icon;
    tm.textContent = msg;

    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(function() {
        t.classList.remove('show');
    }, 3200);
}

<?php if ($toast_msg): ?>
document.addEventListener('DOMContentLoaded', function() {
    omToast(<?= json_encode($toast_icon) ?>, <?= json_encode($toast_msg) ?>);
});
<?php endif; ?>
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>