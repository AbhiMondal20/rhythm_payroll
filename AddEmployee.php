<?php
require_once 'includes/config.php';

/* ─────────────────────────────────────────
   MODE DETECTION
   ?isAddEmployee=true          → Add new employee
   ?isEditEmployee=true&id=X    → Edit existing employee
───────────────────────────────────────── */
$is_edit    = isset($_GET['isEditEmployee']) && $_GET['isEditEmployee'] === 'true';
$is_add     = !$is_edit;
$page_title = $is_edit ? 'Edit Employee' : 'Add Employee';

/* ─────────────────────────────────────────
   SAFE FALLBACKS
───────────────────────────────────────── */
$employees = (isset($employees) && is_array($employees)) ? $employees : [];

/* ─────────────────────────────────────────
   DEFAULT EMPLOYEE DATA
───────────────────────────────────────── */
$emp = [
    'id'            => '',
    'name'          => '',
    'dept'          => '',
    'role'          => '',
    'desig'         => '',
    'emp_type'      => 'Permanent',
    'join'          => '',
    'email'         => '',
    'off_email'     => '',
    'phone'         => '',
    'phone2'        => '',
    'address'       => '',
    'salary'        => '',
    'basic_pct'     => 60,
    'hra_pct'       => 40,
    'dob'           => '',
    'gender'        => '',
    'blood'         => '',
    'marital'       => '',
    'aadhaar'       => '',
    'pan'           => '',
    'uan'           => '',
    'esi_no'        => '',
    'acc_name'      => '',
    'acc_no'        => '',
    'bank'          => '',
    'ifsc'          => '',
    'branch'        => '',
    'pay_mode'      => 'NEFT',
    'nom_name'      => '',
    'nom_rel'       => '',
    'emg_name'      => '',
    'emg_rel'       => '',
    'emg_phone'     => '',
    'manager'       => '',
    'grade'         => '',
    'shift'         => '',
    'probation'     => '',
    'notice'        => '',
    'qualification' => '',
    'reg_no'        => '',
    'status'        => 'Active',
    'notes'         => '',
    'title'         => '',
    'location'      => 'Ramkrishna IVF Centre, Siliguri',
    'nationality'   => 'Indian',
];

/* ─────────────────────────────────────────
   LOAD EMPLOYEE FOR EDIT
───────────────────────────────────────── */
if ($is_edit) {
    $edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    foreach ($employees as $e) {
        if ((int)($e['id'] ?? 0) === $edit_id) {
            $emp = array_merge($emp, $e);
            break;
        }
    }
}

/* ─────────────────────────────────────────
   HANDLE FORM SUBMISSION
───────────────────────────────────────── */
$save_success = false;
$save_errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required = [
        'first_name',
        'last_name',
        'dept',
        'desig',
        'join',
        'salary'
    ];

    foreach ($required as $field) {
        if (empty(trim((string)($_POST[$field] ?? '')))) {
            $save_errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    if (empty($save_errors)) {
        // Demo mode only. Keep your existing DB insert/update logic here.
        $save_success = true;
    }
}

/* ─────────────────────────────────────────
   HELPERS
───────────────────────────────────────── */
function sel($val, $option): string
{
    return (string)$val === (string)$option ? 'selected' : '';
}

function checked_val($val, $option): string
{
    return (string)$val === (string)$option ? 'checked' : '';
}

function esc($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function salary_components($gross, $basic_pct = 60, $hra_pct = 40): array
{
    $gross = (float)$gross;
    $basic_pct = (float)$basic_pct;
    $hra_pct = (float)$hra_pct;

    $basic = round($gross * $basic_pct / 100);
    $hra   = round($basic * $hra_pct / 100);
    $spec  = max(0, $gross - $basic - $hra);

    $pf_emp = round($basic * 0.12);
    $pf_er  = round($basic * 0.12);

    $esi_applicable = $gross > 0 && $gross <= 21000;
    $esi_emp = $esi_applicable ? round($gross * 0.0075) : 0;
    $esi_er  = $esi_applicable ? round($gross * 0.0325) : 0;

    $pt = $gross > 20000 ? 200 : ($gross > 15000 ? 150 : ($gross > 10000 ? 110 : 0));
    $net = max(0, $gross - $pf_emp - $esi_emp - $pt);
    $ctc = $gross + $pf_er + $esi_er;

    return compact('basic', 'hra', 'spec', 'pf_emp', 'pf_er', 'esi_emp', 'esi_er', 'esi_applicable', 'pt', 'net', 'ctc');
}

$dept_colors = [
    'Medical'        => ['bg' => '#EDE9FE', 'tc' => '#7C3AED'],
    'Nursing'        => ['bg' => '#D1FAE5', 'tc' => '#059669'],
    'Reception'      => ['bg' => '#DBEAFE', 'tc' => '#2563EB'],
    'Lab Tech'       => ['bg' => '#FFEDD5', 'tc' => '#EA580C'],
    'Administration' => ['bg' => '#FEE2E2', 'tc' => '#DC2626'],
    'Accounts'       => ['bg' => '#FEF3C7', 'tc' => '#D97706'],
    'Housekeeping'   => ['bg' => '#F3F4F6', 'tc' => '#374151'],
    'Security'       => ['bg' => '#FDF4FF', 'tc' => '#9333EA'],
];

$name_parts = preg_split('/\s+/', trim((string)($emp['name'] ?? '')));
$first_name_value = $name_parts[0] ?? '';
$last_name_value  = count($name_parts) > 1 ? end($name_parts) : '';

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">
<style>
.add-emp-wrap {
    display: grid;
    grid-template-columns: 220px 1fr 280px;
    gap: 20px;
    align-items: start;
}

.steps-sidebar {
    position: sticky;
    top: 80px;
}

.steps-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.steps-list li {
    position: relative;
}

.step-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: #6B7280;
    transition: all .15s;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    text-decoration: none;
}

.step-link:hover {
    background: #F9FAFB;
    color: #111827;
}

.step-link.active {
    background: #EDE9FE;
    color: #6D28D9;
    font-weight: 700;
}

.step-link.done {
    color: #059669;
}

.step-num {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 2px solid #E5E7EB;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
    background: #fff;
    transition: .2s;
}

.step-link.active .step-num {
    background: #6D28D9;
    color: #fff;
    border-color: #6D28D9;
}

.step-link.done .step-num {
    background: #059669;
    color: #fff;
    border-color: #059669;
}

.step-connector {
    width: 2px;
    height: 12px;
    background: #E5E7EB;
    margin: 0 auto 0 24px;
}

.progress-wrap {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #F3F4F6;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 600;
    color: #6B7280;
    margin-bottom: 6px;
}

.progress-bar-bg {
    height: 5px;
    background: #E5E7EB;
    border-radius: 3px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6D28D9, #2563EB);
    border-radius: 3px;
    transition: width .5s ease;
}

.form-section {
    display: none;
}

.form-section.active {
    display: block;
    animation: fadeUp .2s ease;
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(6px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-block {
    background: #fff;
    border: 1px solid #E5E7EB;
    border-radius: 12px;
    margin-bottom: 16px;
    overflow: hidden;
}

.form-block-header {
    padding: 13px 18px;
    border-bottom: 1px solid #F3F4F6;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-block-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}

.form-block-header h3 {
    font-size: 13.5px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.form-block-header p {
    font-size: 11.5px;
    color: #9CA3AF;
    margin: 2px 0 0;
}

.form-block-body {
    padding: 18px;
}

.fg-row {
    display: grid;
    gap: 14px;
    margin-bottom: 14px;
}

.fg-row:last-child {
    margin-bottom: 0;
}

.fg-row.col-1 {
    grid-template-columns: 1fr;
}

.fg-row.col-2 {
    grid-template-columns: 1fr 1fr;
}

.fg-row.col-3 {
    grid-template-columns: 1fr 1fr 1fr;
}

.fg-row.col-4 {
    grid-template-columns: 1fr 1fr 1fr 1fr;
}

.fg {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.fg label {
    font-size: 11px;
    font-weight: 700;
    color: #6B7280;
    letter-spacing: .4px;
    text-transform: uppercase;
}

.fg label .req {
    color: #DC2626;
    margin-left: 2px;
}

.fg input,
.fg select,
.fg textarea {
    padding: 9px 12px;
    border: 1.5px solid #E5E7EB;
    border-radius: 8px;
    font-size: 13.5px;
    font-family: inherit;
    color: #111827;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    background: #fff;
    width: 100%;
}

.fg input:focus,
.fg select:focus,
.fg textarea:focus {
    border-color: #6D28D9;
    box-shadow: 0 0 0 3px rgba(109, 40, 217, .08);
}

.fg input.is-invalid,
.fg select.is-invalid,
.fg textarea.is-invalid {
    border-color: #DC2626;
    background: #FFF5F5;
}

.fg .field-hint {
    font-size: 11px;
    color: #9CA3AF;
    margin-top: 2px;
}

.fg .field-error {
    font-size: 11px;
    color: #DC2626;
    margin-top: 2px;
    display: none;
}

.fg input.is-invalid~.field-error,
.fg select.is-invalid~.field-error,
.fg textarea.is-invalid~.field-error {
    display: block;
}

.photo-zone {
    border: 2px dashed #E5E7EB;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: .15s;
    background: #FAFAFA;
}

.photo-zone:hover {
    border-color: #6D28D9;
    background: #EDE9FE;
}

.photo-zone input {
    display: none;
}

.sal-table {
    width: 100%;
    font-size: 12.5px;
    border-collapse: collapse;
    margin-top: 12px;
}

.sal-table td {
    padding: 5px 0;
    color: #374151;
}

.sal-table td:last-child {
    text-align: right;
    font-weight: 600;
}

.sal-table tr.total td {
    border-top: 1px solid #E5E7EB;
    padding-top: 8px;
    font-weight: 700;
    font-size: 13.5px;
}

.sal-table tr.deduct td {
    color: #DC2626;
}

.sal-table tr.net-row td {
    color: #059669;
}

.toggle-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}

.toggle {
    position: relative;
    width: 38px;
    height: 21px;
    flex-shrink: 0;
}

.toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    inset: 0;
    background: #D1D5DB;
    border-radius: 11px;
    cursor: pointer;
    transition: .2s;
}

.toggle input:checked+.toggle-slider {
    background: #059669;
}

.toggle-slider::after {
    content: '';
    position: absolute;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: #fff;
    top: 3px;
    left: 3px;
    transition: .2s;
}

.toggle input:checked+.toggle-slider::after {
    transform: translateX(17px);
}

.doc-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border: 1.5px solid #E5E7EB;
    border-radius: 9px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: .15s;
    background: #FAFBFC;
}

.doc-row:hover {
    border-color: #6D28D9;
    background: #EDE9FE;
}

.doc-row.uploaded {
    border-color: #059669;
    background: #D1FAE5;
}

.doc-icon {
    width: 34px;
    height: 34px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
    background: #F3F4F6;
}

.doc-row.uploaded .doc-icon {
    background: #D1FAE5;
}

.form-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 14px;
    margin-top: 4px;
    border-top: 1px solid #F3F4F6;
}

.preview-panel {
    position: sticky;
    top: 80px;
}

.emp-preview-card {
    background: linear-gradient(135deg, #12132A 0%, #1E1F3B 100%);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 14px;
    border: 1px solid rgba(255, 255, 255, .06);
}

.epc-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6D28D9, #2563EB);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    margin: 0 auto 12px;
    border: 3px solid rgba(255, 255, 255, .1);
    overflow: hidden;
    position: relative;
}

.epc-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: none;
    position: absolute;
    inset: 0;
    border-radius: 50%;
}

.epc-name {
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    text-align: center;
}

.epc-desig {
    color: rgba(255, 255, 255, .45);
    font-size: 12px;
    text-align: center;
    margin-top: 3px;
}

.epc-divider {
    height: 1px;
    background: rgba(255, 255, 255, .06);
    margin: 12px 0;
}

.epc-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.epc-row-icon {
    width: 24px;
    height: 24px;
    border-radius: 5px;
    background: rgba(255, 255, 255, .06);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.epc-row-label {
    font-size: 9.5px;
    color: rgba(255, 255, 255, .3);
    font-weight: 700;
    letter-spacing: .3px;
    text-transform: uppercase;
}

.epc-row-val {
    font-size: 12px;
    color: rgba(255, 255, 255, .8);
    font-weight: 500;
}

.sal-preview-card {
    background: #fff;
    border: 1px solid #E5E7EB;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 14px;
}

.sal-preview-title {
    font-size: 11px;
    font-weight: 700;
    color: #6B7280;
    letter-spacing: .5px;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.spr-row {
    display: flex;
    justify-content: space-between;
    font-size: 12.5px;
    padding: 4px 0;
    border-bottom: 1px solid #F9FAFB;
}

.spr-row:last-child {
    border: none;
}

.spr-row.total {
    border-top: 1px solid #E5E7EB;
    padding-top: 8px;
    font-weight: 700;
    font-size: 13px;
}

.spr-row .spr-label {
    color: #6B7280;
}

.spr-row .spr-val {
    font-weight: 600;
    color: #111827;
}

.spr-row.deduct .spr-val {
    color: #DC2626;
}

.spr-row.net-row .spr-label,
.spr-row.net-row .spr-val {
    color: #059669;
}

.success-banner {
    background: #D1FAE5;
    border: 1px solid #6EE7B7;
    border-radius: 10px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    font-size: 13.5px;
    color: #065F46;
    font-weight: 600;
}

.error-banner {
    background: #FEE2E2;
    border: 1px solid #FCA5A5;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #991B1B;
}

.day-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.day-pill {
    padding: 5px 12px;
    border: 1.5px solid #E5E7EB;
    border-radius: 7px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    color: #6B7280;
    transition: .15s;
    user-select: none;
}

.day-pill.active {
    background: #EDE9FE;
    border-color: #6D28D9;
    color: #6D28D9;
}

@media (max-width: 1100px) {
    .add-emp-wrap {
        grid-template-columns: 200px 1fr;
    }

    .preview-panel {
        display: none;
    }
}

@media (max-width: 768px) {
    .add-emp-wrap {
        grid-template-columns: 1fr;
    }

    .steps-sidebar {
        display: none;
    }

    .fg-row.col-3,
    .fg-row.col-4 {
        grid-template-columns: 1fr 1fr;
    }

    .fg-row.col-2 {
        grid-template-columns: 1fr;
    }
}
</style>

<?php if ($save_success): ?>
<div class="success-banner">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
        <polyline points="22 4 12 14.01 9 11.01" />
    </svg>
    Employee record <?= $is_edit ? 'updated' : 'created' ?> successfully!
    <a href="employees" style="margin-left:auto;font-size:12px;color:#059669;text-decoration:underline">← Back to
        list</a>
</div>
<?php endif; ?>

<?php if (!empty($save_errors)): ?>
<div class="error-banner">
    <strong>Please fix the following errors:</strong>
    <ul style="margin:6px 0 0 16px">
        <?php foreach ($save_errors as $err): ?>
        <li><?= esc($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="employees" class="btn" style="padding:6px 10px;text-decoration:none">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6" />
            </svg>
        </a>
        <div>
            <h1 class="page-title"><?= $is_edit ? 'Edit Employee' : 'Add New Employee' ?></h1>
            <p class="page-sub">
                <?php if ($is_edit): ?>
                Editing: <strong><?= esc($emp['name'] ?: 'Employee #' . $emp['id']) ?></strong> &middot;
                EMP-<?= str_pad((string)($emp['id'] ?? 0), 3, '0', STR_PAD_LEFT) ?>
                <?php else: ?>
                Fill in all required fields to create an employee record
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php if ($is_edit): ?>
        <span class="badge" style="background:#FEF3C7;color:#92400E;font-size:12px;padding:5px 10px">✏ Edit Mode</span>
        <button class="btn" type="button" style="color:#DC2626;border-color:#FEE2E2;font-size:13px"
            onclick="confirmDelete(<?= (int)$emp['id'] ?>)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6" />
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            </svg>
            Delete
        </button>
        <?php else: ?>
        <span class="badge" style="background:#D1FAE5;color:#065F46;font-size:12px;padding:5px 10px">+ Add Mode</span>
        <?php endif; ?>

        <button type="button" class="btn btn-primary" onclick="submitEmployeeForm()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                <polyline points="17 21 17 13 7 13 7 21" />
                <polyline points="7 3 7 8 15 8" />
            </svg>
            <?= $is_edit ? 'Update Employee' : 'Save Employee' ?>
        </button>
    </div>
</div>

<div class="add-emp-wrap">

    <div class="steps-sidebar">
        <div class="section-card" style="padding:14px 10px">
            <div
                style="font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:1px;padding:0 8px 10px;text-transform:uppercase">
                Sections</div>
            <ul class="steps-list" id="stepsList">
                <?php
                $steps = [
                    ['Personal Info', 'Name, DOB, ID proofs'],
                    ['Employment', 'Role, dept, joining'],
                    ['Salary & CTC', 'Pay, PF, ESI, PT'],
                    ['Bank Details', 'Account, IFSC, nominee'],
                    ['Documents', 'Upload proofs & certs'],
                    ['Emergency & Other', 'Contact, notes, status'],
                ];
                foreach ($steps as $i => [$label, $sub]):
                    $n = $i + 1;
                ?>
                <li>
                    <button type="button" class="step-link <?= $n === 1 ? 'active' : '' ?>" id="snav-<?= $n ?>"
                        onclick="goToSection(<?= $n ?>)">
                        <div class="step-num" id="snum-<?= $n ?>"><?= $n ?></div>
                        <div style="min-width:0">
                            <div><?= esc($label) ?></div>
                            <div style="font-size:10.5px;color:#9CA3AF;margin-top:1px;font-weight:400"><?= esc($sub) ?>
                            </div>
                        </div>
                    </button>
                    <?php if ($n < count($steps)): ?>
                    <div class="step-connector"></div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="progress-wrap">
                <div class="progress-label">
                    <span>Completion</span>
                    <span id="progressPct" style="color:#6D28D9">0%</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" id="progressFill" style="width:0%"></div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <form id="empForm" method="POST" action="" enctype="multipart/form-data" novalidate>
            <?php if ($is_edit): ?>
            <input type="hidden" name="emp_id" value="<?= (int)$emp['id'] ?>">
            <?php endif; ?>

            <div class="form-section active" id="section-1">

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#EDE9FE">🖼</div>
                        <div>
                            <h3>Profile Photo</h3>
                            <p>JPG or PNG, max 2 MB</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
                            <div id="photoCircle"
                                style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#6D28D9,#2563EB);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;flex-shrink:0;border:3px solid #E5E7EB;overflow:hidden;position:relative">
                                <span id="photoInitials">??</span>
                                <img id="photoPreviewImg" src="" alt=""
                                    style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
                            </div>

                            <label class="photo-zone" for="photoInput" style="flex:1;min-width:180px">
                                <input type="file" id="photoInput" name="photo" accept="image/png,image/jpeg,image/jpg"
                                    onchange="previewPhoto(event)">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6D28D9"
                                    stroke-width="1.5">
                                    <polyline points="16 16 12 12 8 16"></polyline>
                                    <line x1="12" y1="12" x2="12" y2="21"></line>
                                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                                </svg>
                                <div style="font-size:13px;font-weight:600;color:#374151;margin-top:6px">Click or drag
                                    photo here</div>
                                <div style="font-size:11px;color:#9CA3AF;margin-top:3px">JPG, PNG up to 2 MB</div>
                            </label>
                        </div>
                    </div>
                </div>


                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#EDE9FE">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6D28D9"
                                stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                        </div>
                        <div>
                            <h3>Full Name</h3>
                            <p>Legal name as on government documents</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-4">
                            <div class="fg">
                                <label>TITLE</label>
                                <select name="title" onchange="updatePreview()">
                                    <option value="">Select</option>
                                    <?php foreach (['Mr.','Mrs.','Ms.','Dr.','Prof.'] as $t): ?>
                                    <option value="<?= esc($t) ?>" <?= sel($emp['title'], $t) ?>><?= esc($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>FIRST NAME <span class="req">*</span></label>
                                <input type="text" name="first_name" id="fFirstName"
                                    value="<?= esc($first_name_value) ?>" placeholder="e.g. Anjali"
                                    oninput="updatePreview();liveValidate(this)" required>
                                <span class="field-error">Required</span>
                            </div>
                            <div class="fg">
                                <label>MIDDLE NAME</label>
                                <input type="text" name="middle_name" placeholder="Optional">
                            </div>
                            <div class="fg">
                                <label>LAST NAME <span class="req">*</span></label>
                                <input type="text" name="last_name" id="fLastName" value="<?= esc($last_name_value) ?>"
                                    placeholder="e.g. Sharma" oninput="updatePreview();liveValidate(this)" required>
                                <span class="field-error">Required</span>
                            </div>
                        </div>

                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>DATE OF BIRTH <span class="req">*</span></label>
                                <input type="date" name="dob" value="<?= esc($emp['dob']) ?>"
                                    onchange="updatePreview()">
                            </div>
                            <div class="fg">
                                <label>GENDER <span class="req">*</span></label>
                                <select name="gender" onchange="updatePreview()">
                                    <option value="">Select</option>
                                    <?php foreach (['Male','Female','Other','Prefer not to say'] as $g): ?>
                                    <option value="<?= esc($g) ?>" <?= sel($emp['gender'], $g) ?>><?= esc($g) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>BLOOD GROUP</label>
                                <select name="blood">
                                    <option value="">Select</option>
                                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $b): ?>
                                    <option value="<?= esc($b) ?>" <?= sel($emp['blood'], $b) ?>><?= esc($b) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>MARITAL STATUS</label>
                                <select name="marital">
                                    <option value="">Select</option>
                                    <?php foreach (['Single','Married','Divorced','Widowed'] as $m): ?>
                                    <option value="<?= esc($m) ?>" <?= sel($emp['marital'], $m) ?>><?= esc($m) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>NATIONALITY</label>
                                <input type="text" name="nationality"
                                    value="<?= esc($emp['nationality'] ?: 'Indian') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#D1FAE5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669"
                                stroke-width="2">
                                <path
                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.38 2 2 0 0 1 3.58 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 18z" />
                            </svg>
                        </div>
                        <div>
                            <h3>Contact Details</h3>
                            <p>Phone, email, and address</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>MOBILE NUMBER <span class="req">*</span></label>
                                <input type="tel" name="phone" id="fPhone" value="<?= esc($emp['phone']) ?>"
                                    placeholder="+91 98321 00001" oninput="updatePreview()">
                            </div>
                            <div class="fg">
                                <label>ALTERNATE PHONE</label>
                                <input type="tel" name="phone2" value="<?= esc($emp['phone2']) ?>"
                                    placeholder="Optional">
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>PERSONAL EMAIL <span class="req">*</span></label>
                                <input type="email" name="email" id="fEmail" value="<?= esc($emp['email']) ?>" placeholder="personal@gmail.com" oninput="updatePreview()">
                            </div>
                            <div class="fg">
                                <label>OFFICIAL EMAIL</label>
                                <input type="email" name="off_email" id="fOffEmail"
                                    value="<?= esc($emp['off_email']) ?>" placeholder="name@ramkrishnaivf.in"
                                    oninput="updatePreview()">
                            </div>
                        </div>

                        <div class="fg-row col-1">
                            <div class="fg">
                                <label>CURRENT ADDRESS <span class="req">*</span></label>
                                <textarea name="address" rows="2"
                                    placeholder="House No., Street, Area, City, PIN"><?= esc($emp['address']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#DBEAFE">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563EB"
                                stroke-width="2">
                                <rect x="1" y="4" width="22" height="16" rx="2" />
                                <line x1="1" y1="10" x2="23" y2="10" />
                            </svg>
                        </div>
                        <div>
                            <h3>Identity Numbers</h3>
                            <p>Aadhaar, PAN, UAN, ESI</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>AADHAAR NUMBER</label>
                                <input type="text" name="aadhaar" value="<?= esc($emp['aadhaar']) ?>"
                                    placeholder="XXXX XXXX XXXX" maxlength="14">
                                <span class="field-hint">12-digit Aadhaar</span>
                            </div>
                            <div class="fg">
                                <label>PAN NUMBER</label>
                                <input type="text" name="pan" value="<?= esc($emp['pan']) ?>" placeholder="ABCDE1234F"
                                    style="text-transform:uppercase" maxlength="10">
                                <span class="field-hint">10-character PAN</span>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>UAN (PF)</label>
                                <input type="text" name="uan" value="<?= esc($emp['uan']) ?>"
                                    placeholder="100XXXXXXXXX">
                                <span class="field-hint">Universal Account Number</span>
                            </div>
                            <div class="fg">
                                <label>ESI NUMBER</label>
                                <input type="text" name="esi_no" value="<?= esc($emp['esi_no']) ?>"
                                    placeholder="31-XX-XXXXXX-XXX-XXXX">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <div></div>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">
                        Employment Details
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-section" id="section-2">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#DBEAFE">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563EB"
                                stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" />
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                            </svg>
                        </div>
                        <div>
                            <h3>Role Information</h3>
                            <p>Department, designation, employee type</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>EMPLOYEE ID</label>
                                <input type="text" name="emp_code" id="fEmpCode"
                                    value="<?= $is_edit ? 'EMP-' . str_pad((string)$emp['id'], 3, '0', STR_PAD_LEFT) : 'EMP-' . str_pad((string)(count($employees) + 1), 3, '0', STR_PAD_LEFT) ?>"
                                    oninput="updatePreview()">
                                <span class="field-hint">Auto-generated; edit if needed</span>
                            </div>
                            <div class="fg">
                                <label>DEPARTMENT <span class="req">*</span></label>
                                <select name="dept" id="fDept" onchange="updatePreview()" required>
                                    <option value="">Select Department</option>
                                    <?php foreach (array_keys($dept_colors) as $d): ?>
                                    <option value="<?= esc($d) ?>" <?= sel($emp['dept'], $d) ?>><?= esc($d) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>DESIGNATION <span class="req">*</span></label>
                                <input type="text" name="desig" id="fDesig"
                                    value="<?= esc($emp['desig'] ?: $emp['role']) ?>" placeholder="e.g. Sr. Nurse"
                                    oninput="updatePreview()" required>
                            </div>
                            <div class="fg">
                                <label>EMPLOYEE TYPE</label>
                                <select name="emp_type" onchange="updatePreview()">
                                    <?php foreach (['Permanent','Contract','Part-Time','Intern','Consultant'] as $t): ?>
                                    <option value="<?= esc($t) ?>" <?= sel($emp['emp_type'], $t) ?>><?= esc($t) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>REPORTING MANAGER</label>
                                <select name="manager">
                                    <option value="">None (Top Level)</option>
                                    <?php foreach ($employees as $mgr): ?>
                                    <?php if ((int)($mgr['id'] ?? 0) !== (int)($emp['id'] ?? -1)): ?>
                                    <option value="<?= (int)$mgr['id'] ?>"
                                        <?= sel($emp['manager'], (string)($mgr['id'] ?? '')) ?>>
                                        <?= esc($mgr['name'] ?? '') ?> — <?= esc($mgr['dept'] ?? '') ?>
                                    </option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>GRADE / LEVEL</label>
                                <select name="grade">
                                    <option value="">Select</option>
                                    <?php foreach (['Grade A – Senior','Grade B – Mid','Grade C – Junior','Grade D – Entry'] as $g): ?>
                                    <option value="<?= esc($g) ?>" <?= sel($emp['grade'], $g) ?>><?= esc($g) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>WORK LOCATION</label>
                                <input type="text" name="location"
                                    value="<?= esc($emp['location'] ?: 'Ramkrishna IVF Centre, Siliguri') ?>">
                            </div>
                            <div class="fg">
                                <label>STATUS</label>
                                <select name="status" id="fStatus">
                                    <?php foreach (['Active','Inactive','On Notice','Suspended','Resigned'] as $s): ?>
                                    <option value="<?= esc($s) ?>" <?= sel($emp['status'] ?: 'Active', $s) ?>>
                                        <?= esc($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#FEF3C7">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#D97706"
                                stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                        </div>
                        <div>
                            <h3>Dates &amp; Contract</h3>
                            <p>Joining, probation, confirmation, notice period</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>DATE OF JOINING <span class="req">*</span></label>
                                <input type="date" name="join" id="fJoin" value="<?= esc($emp['join']) ?>"
                                    onchange="updatePreview()" required>
                            </div>
                            <div class="fg">
                                <label>PROBATION PERIOD</label>
                                <select name="probation">
                                    <option value="">Select</option>
                                    <?php foreach (['None','1 Month','3 Months','6 Months','1 Year'] as $p): ?>
                                    <option value="<?= esc($p) ?>" <?= sel($emp['probation'], $p) ?>><?= esc($p) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>NOTICE PERIOD</label>
                                <select name="notice">
                                    <option value="">Select</option>
                                    <?php foreach (['15 Days','30 Days','60 Days','90 Days'] as $n): ?>
                                    <option value="<?= esc($n) ?>" <?= sel($emp['notice'], $n) ?>><?= esc($n) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>CONFIRMATION DATE</label>
                                <input type="date" name="confirm_date" placeholder="After probation">
                            </div>
                            <div class="fg">
                                <label>CONTRACT END DATE</label>
                                <input type="date" name="contract_end" placeholder="Contract employees only">
                                <span class="field-hint">Leave blank for permanent staff</span>
                            </div>
                            <div class="fg">
                                <label>SHIFT</label>
                                <select name="shift">
                                    <option value="">Select</option>
                                    <?php foreach (['General (9AM–5PM)','Morning (7AM–3PM)','Evening (3PM–11PM)','Night (11PM–7AM)','Rotational'] as $sh): ?>
                                    <option value="<?= esc($sh) ?>" <?= sel($emp['shift'], $sh) ?>><?= esc($sh) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg">
                            <label>WORKING DAYS</label>
                            <div class="day-pills">
                                <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $di => $d): ?>
                                <span class="day-pill <?= $di < 6 ? 'active' : '' ?>"
                                    onclick="this.classList.toggle('active')"><?= esc($d) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#EDE9FE">🎓</div>
                        <div>
                            <h3>Qualifications</h3>
                            <p>Education, specialisation, registration</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>HIGHEST QUALIFICATION</label>
                                <select name="qualification">
                                    <option value="">Select</option>
                                    <?php foreach (['10th/SSLC','12th/HSC','Diploma','B.Sc/BCA','MBA/MCA','MBBS','MD/MS','DM/MCh','PhD'] as $q): ?>
                                    <option value="<?= esc($q) ?>" <?= sel($emp['qualification'], $q) ?>><?= esc($q) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>SPECIALISATION</label>
                                <input type="text" name="specialisation" placeholder="e.g. Obstetrics & Gynaecology">
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>REGISTRATION NUMBER</label>
                                <input type="text" name="reg_no" value="<?= esc($emp['reg_no']) ?>"
                                    placeholder="Medical/Professional reg no.">
                            </div>
                            <div class="fg">
                                <label>YEAR OF PASSING</label>
                                <input type="number" name="yop" min="1970" max="<?= date('Y') ?>"
                                    placeholder="e.g. 2014">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                        Personal Info
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">
                        Salary &amp; CTC
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-section" id="section-3">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#D1FAE5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669"
                                stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23" />
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                            </svg>
                        </div>
                        <div>
                            <h3>Gross Salary</h3>
                            <p>Enter monthly gross; components auto-calculate</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>GROSS SALARY / MONTH (₹) <span class="req">*</span></label>
                                <input type="number" name="salary" id="fSalary" value="<?= esc($emp['salary']) ?>"
                                    min="0" placeholder="e.g. 38000" oninput="calcSalary();updatePreview()" required>
                            </div>
                            <div class="fg">
                                <label>BASIC % OF GROSS</label>
                                <input type="number" name="basic_pct" id="fBasicPct"
                                    value="<?= esc($emp['basic_pct'] ?: 60) ?>" min="1" max="100"
                                    oninput="calcSalary()">
                            </div>
                            <div class="fg">
                                <label>HRA % OF BASIC</label>
                                <input type="number" name="hra_pct" id="fHraPct"
                                    value="<?= esc($emp['hra_pct'] ?: 40) ?>" min="0" max="100" oninput="calcSalary()">
                            </div>
                        </div>

                        <div id="salBreakdownWrap" style="display:none">
                            <div
                                style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border:1px solid #E5E7EB;border-radius:9px;overflow:hidden;margin-top:4px">
                                <div style="padding:12px 14px;border-right:1px solid #E5E7EB">
                                    <div
                                        style="font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:.5px;margin-bottom:8px">
                                        EARNINGS</div>
                                    <table class="sal-table" id="tEarnings"></table>
                                </div>
                                <div style="padding:12px 14px;border-right:1px solid #E5E7EB">
                                    <div
                                        style="font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:.5px;margin-bottom:8px">
                                        DEDUCTIONS</div>
                                    <table class="sal-table" id="tDeductions"></table>
                                </div>
                                <div style="padding:12px 14px;background:#F9FAFB">
                                    <div
                                        style="font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:.5px;margin-bottom:8px">
                                        EMPLOYER COST</div>
                                    <table class="sal-table" id="tEmployer"></table>
                                </div>
                            </div>
                            <div
                                style="margin-top:8px;background:linear-gradient(90deg,#D1FAE5,#DBEAFE);border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center">
                                <span style="font-size:13px;font-weight:600;color:#111827">Monthly Net Take-Home</span>
                                <span style="font-size:18px;font-weight:700;color:#059669" id="salNetDisplay">₹0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#EDE9FE">📋</div>
                        <div>
                            <h3>Statutory Deductions</h3>
                            <p>PF, ESI, Profession Tax — West Bengal rules</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div style="padding:12px;background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:9px">
                                <div
                                    style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                                    <span style="font-size:13px;font-weight:700">Provident Fund (PF)</span>
                                    <label class="toggle-wrap">
                                        <label class="toggle">
                                            <input type="checkbox" name="pf_enabled" id="pfToggle" checked
                                                onchange="calcSalary()">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span style="font-size:12px;color:#059669">Enabled</span>
                                    </label>
                                </div>
                                <div class="fg-row col-2" style="margin-bottom:0">
                                    <div class="fg"><label>EMPLOYEE %</label><input type="number" name="pf_emp_pct"
                                            id="fPfEmp" value="12" min="0" max="100" oninput="calcSalary()"></div>
                                    <div class="fg"><label>EMPLOYER %</label><input type="number" name="pf_er_pct"
                                            id="fPfEr" value="12" min="0" max="100" oninput="calcSalary()"></div>
                                </div>
                            </div>

                            <div style="padding:12px;background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:9px">
                                <div
                                    style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                                    <span style="font-size:13px;font-weight:700">ESI</span>
                                    <span style="font-size:11px;color:#6B7280" id="esiStatusLabel">N/A (&gt;
                                        ₹21,000)</span>
                                </div>
                                <div class="fg-row col-2" style="margin-bottom:0">
                                    <div class="fg"><label>EMPLOYEE %</label><input type="number" name="esi_emp_pct"
                                            id="fEsiEmp" value="0.75" step="0.01" disabled
                                            style="background:#F3F4F6;color:#9CA3AF"></div>
                                    <div class="fg"><label>EMPLOYER %</label><input type="number" name="esi_er_pct"
                                            id="fEsiEr" value="3.25" step="0.01" disabled
                                            style="background:#F3F4F6;color:#9CA3AF"></div>
                                </div>
                            </div>
                        </div>

                        <div class="fg-row col-2" style="margin-top:4px">
                            <div style="padding:12px;background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:9px">
                                <div
                                    style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                                    <span style="font-size:13px;font-weight:700">Profession Tax (WB)</span>
                                    <span class="badge" id="ptBadge"
                                        style="background:#FEF3C7;color:#D97706">₹200/mo</span>
                                </div>
                                <div class="fg">
                                    <label>PT AMOUNT / MONTH (₹)</label>
                                    <input type="number" name="pt_amount" id="fPt" value="200" readonly
                                        style="background:#F3F4F6">
                                </div>
                            </div>

                            <div style="padding:12px;background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:9px">
                                <div style="font-size:13px;font-weight:700;margin-bottom:8px">TDS / Income Tax</div>
                                <div class="fg-row col-2" style="margin-bottom:0">
                                    <div class="fg">
                                        <label>TAX REGIME</label>
                                        <select name="tax_regime">
                                            <option value="New Regime">New Regime</option>
                                            <option value="Old Regime">Old Regime</option>
                                        </select>
                                    </div>
                                    <div class="fg">
                                        <label>MONTHLY TDS (₹)</label>
                                        <input type="number" name="tds_monthly" id="fTds" value="0" min="0"
                                            oninput="calcSalary()">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#DBEAFE">➕</div>
                        <div>
                            <h3>Additional Allowances</h3>
                            <p>Transport, medical, food, variable pay</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-4">
                            <div class="fg"><label>TRANSPORT (₹/mo)</label><input type="number" name="travel_allow"
                                    value="0" min="0" oninput="calcSalary()"></div>
                            <div class="fg"><label>MEDICAL (₹/mo)</label><input type="number" name="medical_allow"
                                    value="0" min="0" oninput="calcSalary()"></div>
                            <div class="fg"><label>FOOD (₹/mo)</label><input type="number" name="food_allow" value="0"
                                    min="0" oninput="calcSalary()"></div>
                            <div class="fg"><label>VARIABLE PAY (₹/mo)</label><input type="number" name="variable_pay"
                                    value="0" min="0" oninput="calcSalary()"></div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                        Employment
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">
                        Bank Details
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-section" id="section-4">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#D1FAE5">🏦</div>
                        <div>
                            <h3>Salary Bank Account</h3>
                            <p>Used for salary credit via NEFT / IMPS</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>ACCOUNT HOLDER NAME <span class="req">*</span></label>
                                <input type="text" name="acc_name" value="<?= esc($emp['acc_name']) ?>"
                                    placeholder="As on bank passbook">
                            </div>
                            <div class="fg">
                                <label>ACCOUNT TYPE</label>
                                <select name="acc_type">
                                    <option value="Savings">Savings</option>
                                    <option value="Current">Current</option>
                                    <option value="Salary Account">Salary Account</option>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>ACCOUNT NUMBER <span class="req">*</span></label>
                                <input type="text" name="acc_no" id="fAccNo" value="<?= esc($emp['acc_no']) ?>"
                                    placeholder="e.g. 001234567890">
                            </div>
                            <div class="fg">
                                <label>CONFIRM ACCOUNT NUMBER</label>
                                <input type="text" name="acc_no_confirm" value="<?= esc($emp['acc_no']) ?>"
                                    placeholder="Re-enter to confirm">
                            </div>
                        </div>

                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>BANK NAME <span class="req">*</span></label>
                                <select name="bank" id="fBank">
                                    <option value="">Select</option>
                                    <?php foreach (['State Bank of India (SBI)','Punjab National Bank','HDFC Bank','ICICI Bank','Axis Bank','Bank of Baroda','Canara Bank','UCO Bank','Other'] as $bk): ?>
                                    <option value="<?= esc($bk) ?>" <?= sel($emp['bank'], $bk) ?>><?= esc($bk) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>IFSC CODE <span class="req">*</span></label>
                                <input type="text" name="ifsc" id="fIfsc" value="<?= esc($emp['ifsc']) ?>"
                                    placeholder="e.g. SBIN0001234" style="text-transform:uppercase"
                                    oninput="lookupIfsc(this)">
                                <span class="field-hint" id="ifscHint">Branch will auto-fill on valid IFSC</span>
                            </div>
                            <div class="fg">
                                <label>BRANCH NAME</label>
                                <input type="text" name="branch" id="fBranch" value="<?= esc($emp['branch']) ?>"
                                    placeholder="Auto-filled" readonly style="background:#F9FAFB;color:#6B7280">
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>PAYMENT MODE</label>
                                <select name="pay_mode">
                                    <?php foreach (['NEFT','IMPS','RTGS','Cheque','Cash'] as $pm): ?>
                                    <option value="<?= esc($pm) ?>" <?= sel($emp['pay_mode'], $pm) ?>><?= esc($pm) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#DBEAFE">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563EB"
                                stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            </svg>
                        </div>
                        <div>
                            <h3>PF Nominee</h3>
                            <p>Provident Fund and gratuity nominee details</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>NOMINEE NAME</label>
                                <input type="text" name="nom_name" value="<?= esc($emp['nom_name']) ?>"
                                    placeholder="Full legal name">
                            </div>
                            <div class="fg">
                                <label>RELATIONSHIP</label>
                                <select name="nom_rel">
                                    <option value="">Select</option>
                                    <?php foreach (['Spouse','Father','Mother','Son','Daughter','Sibling','Other'] as $r): ?>
                                    <option value="<?= esc($r) ?>" <?= sel($emp['nom_rel'], $r) ?>><?= esc($r) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>NOMINEE SHARE %</label>
                                <input type="number" name="nom_share" value="100" min="1" max="100">
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>NOMINEE AADHAAR</label>
                                <input type="text" name="nom_aadhaar" placeholder="12-digit Aadhaar">
                            </div>
                            <div class="fg">
                                <label>NOMINEE DATE OF BIRTH</label>
                                <input type="date" name="nom_dob">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                        Salary &amp; CTC
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">
                        Documents
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-section" id="section-5">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#FFEDD5">📂</div>
                        <div>
                            <h3>Document Uploads</h3>
                            <p>Click a row to upload the corresponding document</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <?php
                        $docs = [
                            ['🪪', 'Aadhaar Card', 'aadhaar_doc', true, false],
                            ['💳', 'PAN Card', 'pan_doc', true, false],
                            ['📸', 'Passport-size Photo', 'photo_doc', true, true],
                            ['🎓', 'Educational Certificates', 'edu_doc', true, false],
                            ['🏥', 'Medical Registration Cert.', 'med_reg_doc', true, false],
                            ['📄', 'Previous Employment Letter', 'prev_emp_doc', false, false],
                            ['🏦', 'Bank Passbook / Cheque Copy', 'bank_doc', true, false],
                            ['📝', 'Appointment Letter', 'appt_doc', false, false],
                            ['✍️', 'Signed Offer Acceptance', 'offer_doc', false, false],
                            ['🔐', 'NDA / Non-Compete Agreement', 'nda_doc', false, false],
                        ];
                        foreach ($docs as [$icon, $label, $field, $req, $uploaded]):
                        ?>
                        <div class="doc-row <?= $uploaded ? 'uploaded' : '' ?>"
                            onclick="triggerDocUpload(this, '<?= esc($label) ?>')">
                            <input type="file" name="<?= esc($field) ?>" style="display:none"
                                onchange="handleDocUpload(this, '<?= esc($label) ?>')">
                            <div class="doc-icon"><?= $uploaded ? '✅' : $icon ?></div>
                            <div style="flex:1">
                                <div
                                    style="font-size:13px;font-weight:600;color:<?= $uploaded ? '#065F46' : '#111827' ?>">
                                    <?= esc($label) ?></div>
                                <div
                                    style="font-size:11px;color:<?= $uploaded ? '#059669' : '#9CA3AF' ?>;margin-top:1px">
                                    <?= $req ? 'Required' : 'Optional' ?> &middot;
                                    <?= $uploaded ? 'Uploaded ✓' : 'Click to upload' ?>
                                </div>
                            </div>
                            <div>
                                <span class="badge"
                                    style="background:<?= $uploaded ? '#D1FAE5' : '#F3F4F6' ?>;color:<?= $uploaded ? '#065F46' : '#6B7280' ?>">
                                    <?= $uploaded ? 'View' : 'Upload' ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                        Bank Details
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">
                        Emergency &amp; Other
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-section" id="section-6">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#FEE2E2">🆘</div>
                        <div>
                            <h3>Emergency Contact</h3>
                            <p>Person to contact in case of emergency</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>CONTACT NAME <span class="req">*</span></label>
                                <input type="text" name="emg_name" value="<?= esc($emp['emg_name']) ?>"
                                    placeholder="Full name">
                            </div>
                            <div class="fg">
                                <label>RELATIONSHIP</label>
                                <select name="emg_rel">
                                    <option value="">Select</option>
                                    <?php foreach (['Spouse','Father','Mother','Sibling','Friend','Other'] as $r): ?>
                                    <option value="<?= esc($r) ?>" <?= sel($emp['emg_rel'], $r) ?>><?= esc($r) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>PHONE <span class="req">*</span></label>
                                <input type="tel" name="emg_phone" value="<?= esc($emp['emg_phone']) ?>"
                                    placeholder="+91 98765 43210">
                            </div>
                        </div>

                        <div class="fg-row col-1">
                            <div class="fg">
                                <label>ADDRESS</label>
                                <input type="text" name="emg_address" placeholder="Emergency contact's address">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#FDF4FF">🩺</div>
                        <div>
                            <h3>Medical Information</h3>
                            <p>Health conditions and special requirements</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>KNOWN ALLERGIES</label>
                                <input type="text" name="allergies" placeholder="e.g. Penicillin, Latex (or None)">
                            </div>
                            <div class="fg">
                                <label>DISABILITY (IF ANY)</label>
                                <select name="disability">
                                    <option value="">None</option>
                                    <option value="Visually Impaired">Visually Impaired</option>
                                    <option value="Hearing Impaired">Hearing Impaired</option>
                                    <option value="Locomotor">Locomotor</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-1">
                            <div class="fg">
                                <label>MEDICAL NOTES</label>
                                <textarea name="med_notes" rows="2"
                                    placeholder="Any chronic conditions or special requirements for HR records..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#F9FAFB">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280"
                                stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                        </div>
                        <div>
                            <h3>HR Notes &amp; Reference</h3>
                            <p>Internal notes, background check, reference</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>BACKGROUND CHECK</label>
                                <select name="bg_check">
                                    <option value="Not Required">Not Required</option>
                                    <option value="Completed ✓">Completed ✓</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Failed">Failed</option>
                                </select>
                            </div>
                            <div class="fg">
                                <label>REFERRED BY</label>
                                <input type="text" name="reference" placeholder="Name of reference">
                            </div>
                            <div class="fg">
                                <label>EMPLOYEE STATUS</label>
                                <select name="status_final">
                                    <?php foreach (['Active','Inactive','On Notice','Suspended','Resigned'] as $s): ?>
                                    <option value="<?= esc($s) ?>" <?= sel($emp['status'] ?: 'Active', $s) ?>>
                                        <?= esc($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-1">
                            <div class="fg">
                                <label>INTERNAL HR NOTES</label>
                                <textarea name="notes" rows="3"
                                    placeholder="Internal notes — not visible to employee."><?= esc($emp['notes']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                        Documents
                    </button>
                    <button type="submit" class="btn btn-primary" style="padding:9px 22px;font-size:13.5px"
                        id="submitBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <?= $is_edit ? 'Update Employee Record' : 'Save Employee Record' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="preview-panel">
        <div class="emp-preview-card">
            <div class="epc-avatar" id="epcAvatar">
                <span id="epcInitials">??</span>
                <img id="epcAvatarImg" src="" alt="">
            </div>
            <div class="epc-name" id="epcName">New Employee</div>
            <div class="epc-desig" id="epcDesig">—</div>
            <div style="display:flex;justify-content:center;margin-top:8px">
                <span class="badge" id="epcDeptBadge"
                    style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.6);font-size:11px">No
                    Department</span>
            </div>
            <div class="epc-divider"></div>

            <div class="epc-row">
                <div class="epc-row-icon">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.4)"
                        stroke-width="2">
                        <rect x="2" y="7" width="20" height="14" rx="2" />
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                    </svg>
                </div>
                <div>
                    <div class="epc-row-label">EMP ID</div>
                    <div class="epc-row-val" id="epcId">—</div>
                </div>
            </div>

            <div class="epc-row">
                <div class="epc-row-icon">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.4)"
                        stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                </div>
                <div>
                    <div class="epc-row-label">JOINED</div>
                    <div class="epc-row-val" id="epcJoined">—</div>
                </div>
            </div>

            <div class="epc-row">
                <div class="epc-row-icon">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.4)"
                        stroke-width="2">
                        <path
                            d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.38 2 2 0 0 1 3.58 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 18z" />
                    </svg>
                </div>
                <div>
                    <div class="epc-row-label">PHONE</div>
                    <div class="epc-row-val" id="epcPhone">—</div>
                </div>
            </div>

            <div class="epc-row">
                <div class="epc-row-icon">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.4)"
                        stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                </div>
                <div>
                    <div class="epc-row-label">EMAIL</div>
                    <div class="epc-row-val" id="epcEmail" style="font-size:11px">—</div>
                </div>
            </div>
        </div>

        <div class="sal-preview-card" id="salPreviewCard" style="display:none">
            <div class="sal-preview-title">Salary Breakdown</div>
            <div class="spr-row"><span class="spr-label">Gross Salary</span><span class="spr-val" id="spGross">—</span>
            </div>
            <div class="spr-row"><span class="spr-label">Basic</span><span class="spr-val" id="spBasic">—</span></div>
            <div class="spr-row"><span class="spr-label">HRA</span><span class="spr-val" id="spHra">—</span></div>
            <div class="spr-row deduct"><span class="spr-label">PF (Employee)</span><span class="spr-val"
                    id="spPf">—</span></div>
            <div class="spr-row deduct"><span class="spr-label">ESI</span><span class="spr-val" id="spEsi">—</span>
            </div>
            <div class="spr-row deduct"><span class="spr-label">Prof. Tax</span><span class="spr-val" id="spPt">—</span>
            </div>
            <div class="spr-row total net-row"><span class="spr-label">Net Take-Home</span><span class="spr-val"
                    id="spNet">—</span></div>
        </div>

        <div class="section-card" style="padding:14px">
            <div
                style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:.4px;margin-bottom:8px;text-transform:uppercase">
                Form Completion</div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <div style="font-size:22px;font-weight:700;color:#6D28D9" id="panelPct">0%</div>
                <div style="flex:1">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" id="panelBar" style="width:0%"></div>
                    </div>
                </div>
            </div>
            <div style="font-size:11.5px;color:#9CA3AF" id="panelStatus">Fill in the required fields above</div>
        </div>
    </div>
</div>

<div id="deleteModal"
    style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:500;align-items:center;justify-content:center;padding:20px">
    <div style="background:#fff;border-radius:14px;max-width:400px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.2)">
        <div
            style="padding:16px 20px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between">
            <h3 style="font-size:15px;font-weight:700;color:#DC2626">Delete Employee?</h3>
            <button type="button" onclick="document.getElementById('deleteModal').style.display='none'"
                style="background:none;border:none;font-size:20px;cursor:pointer;color:#9CA3AF">×</button>
        </div>
        <div style="padding:18px 20px">
            <p style="font-size:13.5px;color:#374151;line-height:1.6">
                This will <strong>permanently delete</strong> this employee and all associated payroll, attendance, and
                document records. This cannot be undone.
            </p>
            <div style="margin-top:12px">
                <label style="font-size:12px;font-weight:600;color:#6B7280;display:block;margin-bottom:5px">
                    Type employee ID to confirm
                </label>
                <input type="text" id="deleteConfirmInput" placeholder="e.g. EMP-001"
                    style="padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:7px;font-size:13px;width:100%;outline:none">
            </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #E5E7EB;display:flex;justify-content:flex-end;gap:8px">
            <button type="button" class="btn"
                onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
            <button type="button" class="btn" style="background:#FEE2E2;color:#DC2626;border-color:#FCA5A5"
                onclick="doDelete()">Delete Permanently</button>
        </div>
    </div>
</div>

<?php
    $jsIsEdit = $is_edit ? 'true' : 'false';

$extra_scripts = <<<JSCODE
<script>
let currentSection = 1;
const totalSections = 6;
const doneSections = {};
const isEdit = {$jsIsEdit};

function getSectionEl(n) {
    return document.getElementById('section-' + n);
}

function getNavEl(n) {
    return document.getElementById('snav-' + n);
}

function getNumEl(n) {
    return document.getElementById('snum-' + n);
}

function goToSection(n) {
    if (n < 1 || n > totalSections) return;

    for (let i = 1; i <= totalSections; i++) {
        const sec = getSectionEl(i);
        const nav = getNavEl(i);

        if (sec) sec.classList.remove('active');
        if (nav) nav.classList.remove('active');
    }

    currentSection = n;

    const targetSection = getSectionEl(n);
    const targetNav = getNavEl(n);

    if (targetSection) targetSection.classList.add('active');
    if (targetNav) targetNav.classList.add('active');

    updateProgress();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextSection() {
    if (currentSection < totalSections) {
        markSectionDone(currentSection);
        goToSection(currentSection + 1);
    }
}

function prevSection() {
    if (currentSection > 1) {
        goToSection(currentSection - 1);
    }
}

function markSectionDone(n) {
    doneSections[n] = true;

    const snum = getNumEl(n);
    const snav = getNavEl(n);

    if (snum) snum.textContent = '✓';
    if (snav) snav.classList.add('done');

    updateProgress();
}

function updateProgress() {
    const done = Object.keys(doneSections).length;
    const pct = Math.round((done / totalSections) * 100);

    const progressFill = document.getElementById('progressFill');
    const panelBar = document.getElementById('panelBar');
    const progressPct = document.getElementById('progressPct');
    const panelPct = document.getElementById('panelPct');
    const panelStatus = document.getElementById('panelStatus');

    if (progressFill) progressFill.style.width = pct + '%';
    if (panelBar) panelBar.style.width = pct + '%';
    if (progressPct) progressPct.textContent = pct + '%';
    if (panelPct) panelPct.textContent = pct + '%';

    if (panelStatus) {
        panelStatus.textContent = pct === 100
            ? '✓ All sections complete — ready to save!'
            : done + ' of ' + totalSections + ' sections completed';
    }
}

function submitEmployeeForm() {
    const form = document.getElementById('empForm');
    if (!form) return;

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.submit();
    }
}

function updatePreview() {
    const first = document.getElementById('fFirstName')?.value?.trim() || '';
    const last = document.getElementById('fLastName')?.value?.trim() || '';
    const full = [first, last].filter(Boolean).join(' ') || 'New Employee';
    const init = ((first[0] || '') + (last[0] || '')).toUpperCase() || '??';

    const epcName = document.getElementById('epcName');
    const epcInit = document.getElementById('epcInitials');
    const photoInit = document.getElementById('photoInitials');

    if (epcName) epcName.textContent = full;
    if (epcInit) epcInit.textContent = init;
    if (photoInit) photoInit.textContent = init;

    const desig = document.getElementById('fDesig')?.value?.trim() || '—';
    const epcDesig = document.getElementById('epcDesig');
    if (epcDesig) epcDesig.textContent = desig;

    const dept = document.getElementById('fDept')?.value || '';
    const deptColors = {
        'Medical': ['#EDE9FE', '#7C3AED'],
        'Nursing': ['#D1FAE5', '#059669'],
        'Reception': ['#DBEAFE', '#2563EB'],
        'Lab Tech': ['#FFEDD5', '#EA580C'],
        'Administration': ['#FEE2E2', '#DC2626'],
        'Accounts': ['#FEF3C7', '#D97706'],
        'Housekeeping': ['rgba(255,255,255,.1)', 'rgba(255,255,255,.6)'],
        'Security': ['rgba(255,255,255,.1)', 'rgba(255,255,255,.6)']
    };

    const badge = document.getElementById('epcDeptBadge');
    if (badge) {
        badge.textContent = dept || 'No Department';
        const dc = deptColors[dept] || ['rgba(255,255,255,.1)', 'rgba(255,255,255,.6)'];
        badge.style.background = dc[0];
        badge.style.color = dc[1];
    }

    const code = document.getElementById('fEmpCode')?.value?.trim() || '—';
    const epcId = document.getElementById('epcId');
    if (epcId) epcId.textContent = code;

    const join = document.getElementById('fJoin')?.value || '';
    const epcJoined = document.getElementById('epcJoined');
    if (epcJoined) {
        if (join) {
            const d = new Date(join + 'T00:00:00');
            epcJoined.textContent = d.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        } else {
            epcJoined.textContent = '—';
        }
    }

    const epcPhone = document.getElementById('epcPhone');
    if (epcPhone) epcPhone.textContent = document.getElementById('fPhone')?.value?.trim() || '—';

    const epcEmail = document.getElementById('epcEmail');
    if (epcEmail) {
        epcEmail.textContent =
            document.getElementById('fOffEmail')?.value?.trim() ||
            document.getElementById('fEmail')?.value?.trim() ||
            '—';
    }
}

function calcSalary() {
    const gross = parseFloat(document.getElementById('fSalary')?.value) || 0;
    const basicPct = parseFloat(document.getElementById('fBasicPct')?.value) || 60;
    const hraPct = parseFloat(document.getElementById('fHraPct')?.value) || 40;

    const basic = Math.round(gross * basicPct / 100);
    const hra = Math.round(basic * hraPct / 100);
    const spec = Math.max(0, gross - basic - hra);

    const pfEnabled = document.getElementById('pfToggle')?.checked ?? true;
    const pfEmpPct = parseFloat(document.getElementById('fPfEmp')?.value) || 12;
    const pfErPct = parseFloat(document.getElementById('fPfEr')?.value) || 12;

    const pfEmp = pfEnabled ? Math.round(basic * pfEmpPct / 100) : 0;
    const pfEr = pfEnabled ? Math.round(basic * pfErPct / 100) : 0;

    const esiApply = gross > 0 && gross <= 21000;
    const esiEmp = esiApply ? Math.round(gross * 0.0075) : 0;
    const esiEr = esiApply ? Math.round(gross * 0.0325) : 0;

    const pt = gross > 20000 ? 200 : gross > 15000 ? 150 : gross > 10000 ? 110 : 0;
    const ptEl = document.getElementById('fPt');
    if (ptEl) ptEl.value = pt;

    const ptBadge = document.getElementById('ptBadge');
    if (ptBadge) ptBadge.textContent = pt ? ('₹' + pt + '/mo') : '₹0 Exempt';

    const esiLabel = document.getElementById('esiStatusLabel');
    if (esiLabel) {
        esiLabel.textContent = esiApply ? '✓ Applicable (≤ ₹21,000)' : 'N/A (> ₹21,000)';
        esiLabel.style.color = esiApply ? '#059669' : '#9CA3AF';
    }

    ['fEsiEmp', 'fEsiEr'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.disabled = !esiApply;
            el.style.background = esiApply ? '#fff' : '#F3F4F6';
            el.style.color = esiApply ? '#111827' : '#9CA3AF';
        }
    });

    const tds = parseFloat(document.getElementById('fTds')?.value) || 0;
    const totalDed = pfEmp + esiEmp + pt + tds;
    const net = Math.max(0, gross - totalDed);
    const ctc = gross + pfEr + esiEr;

    function fmt(n) {
        return '₹' + Number(n).toLocaleString('en-IN');
    }

    function tableRow(label, value, cls = '') {
        return '<tr class="' + cls + '"><td>' + label + '</td><td>' + fmt(value) + '</td></tr>';
    }

    const wrap = document.getElementById('salBreakdownWrap');
    const previewCard = document.getElementById('salPreviewCard');

    if (gross <= 0) {
        if (wrap) wrap.style.display = 'none';
        if (previewCard) previewCard.style.display = 'none';
        return;
    }

    if (wrap) wrap.style.display = 'block';

    const tE = document.getElementById('tEarnings');
    if (tE) {
        tE.innerHTML =
            tableRow('Basic', basic) +
            tableRow('HRA', hra) +
            tableRow('Special Allowance', spec) +
            '<tr class="total"><td>Total Earnings</td><td>' + fmt(gross) + '</td></tr>';
    }

    const tD = document.getElementById('tDeductions');
    if (tD) {
        tD.innerHTML =
            (pfEmp ? tableRow('PF (Employee)', pfEmp, 'deduct') : '') +
            (esiEmp ? tableRow('ESI (Employee)', esiEmp, 'deduct') : '') +
            (pt ? tableRow('Prof. Tax', pt, 'deduct') : '') +
            (tds ? tableRow('TDS', tds, 'deduct') : '') +
            '<tr class="total net-row"><td>Net Pay</td><td>' + fmt(net) + '</td></tr>';
    }

    const tEr = document.getElementById('tEmployer');
    if (tEr) {
        tEr.innerHTML =
            (pfEr ? tableRow('PF (Employer)', pfEr) : '') +
            (esiEr ? tableRow('ESI (Employer)', esiEr) : '') +
            '<tr class="total"><td>Total CTC</td><td>' + fmt(ctc) + '</td></tr>';
    }

    const salNetDisplay = document.getElementById('salNetDisplay');
    if (salNetDisplay) salNetDisplay.textContent = fmt(net);

    if (previewCard) previewCard.style.display = 'block';

    const updates = {
        spGross: fmt(gross),
        spBasic: fmt(basic),
        spHra: fmt(hra),
        spPf: fmt(pfEmp),
        spPt: fmt(pt),
        spNet: fmt(net)
    };

    Object.entries(updates).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    });

    const spEsi = document.getElementById('spEsi');
    if (spEsi) spEsi.textContent = esiApply ? fmt(esiEmp) : 'N/A';
}

function previewPhoto(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
        alert('Only JPG and PNG images are allowed.');
        event.target.value = '';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert('Image size must be less than 2 MB.');
        event.target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const url = e.target.result;

        const photoPreviewImg = document.getElementById('photoPreviewImg');
        const photoInitials = document.getElementById('photoInitials');
        const epcAvatarImg = document.getElementById('epcAvatarImg');
        const epcInitials = document.getElementById('epcInitials');

        if (photoPreviewImg) {
            photoPreviewImg.src = url;
            photoPreviewImg.style.display = 'block';
        }
        if (photoInitials) {
            photoInitials.style.display = 'none';
        }

        if (epcAvatarImg) {
            epcAvatarImg.src = url;
            epcAvatarImg.style.display = 'block';
        }
        if (epcInitials) {
            epcInitials.style.display = 'none';
        }
    };
    reader.readAsDataURL(file);
}

function lookupIfsc(input) {
    const val = (input.value || '').toUpperCase().replace(/\s/g, '');
    input.value = val;

    const branchEl = document.getElementById('fBranch');
    const hintEl = document.getElementById('ifscHint');

    if (val.length !== 11) {
        if (branchEl) branchEl.value = '';
        if (hintEl) {
            hintEl.textContent = 'Branch will auto-fill on valid IFSC';
            hintEl.style.color = '#9CA3AF';
        }
        return;
    }

    const map = {
        'SBIN0003872': 'SBI – Siliguri Main Branch',
        'SBIN0001234': 'SBI – Hill Cart Road, Siliguri',
        'HDFC0001234': 'HDFC Bank – Siliguri',
        'ICIC0003456': 'ICICI Bank – Jalpaiguri Road'
    };

    const branch = map[val] || 'Branch details found';
    if (branchEl) branchEl.value = branch;
    if (hintEl) {
        hintEl.textContent = '✓ ' + branch;
        hintEl.style.color = '#059669';
    }
}

function liveValidate(input) {
    if ((input.value || '').trim()) {
        input.classList.remove('is-invalid');
    } else {
        input.classList.add('is-invalid');
    }
}

function triggerDocUpload(row) {
    const input = row.querySelector('input[type="file"]');
    if (input) input.click();
}

function handleDocUpload(input) {
    const row = input.closest('.doc-row');
    if (!row || !input.files || !input.files.length) return;

    row.classList.add('uploaded');

    const icon = row.querySelector('.doc-icon');
    const title = row.querySelector('div[style*="font-size:13px"]');
    const sub = row.querySelector('div[style*="font-size:11px"]');
    const badge = row.querySelector('.badge');

    if (icon) icon.textContent = '✅';
    if (title) title.style.color = '#065F46';
    if (sub) {
        sub.innerHTML = 'Uploaded ✓';
        sub.style.color = '#059669';
    }
    if (badge) {
        badge.textContent = 'View';
        badge.style.background = '#D1FAE5';
        badge.style.color = '#065F46';
    }
}

function confirmDelete(id) {
    const modal = document.getElementById('deleteModal');
    const input = document.getElementById('deleteConfirmInput');

    if (modal) modal.style.display = 'flex';
    if (input) {
        input.value = '';
        input._empId = 'EMP-' + String(id).padStart(3, '0');
    }
}

function doDelete() {
    const input = document.getElementById('deleteConfirmInput');
    if (!input) return;

    const expected = input._empId || '';
    if (input.value.trim() === expected) {
        window.location.href = 'DeleteEmployee?id=' + expected.replace('EMP-', '');
    } else {
        input.style.borderColor = '#DC2626';
        input.style.background = '#FFF5F5';
        setTimeout(() => {
            input.style.borderColor = '';
            input.style.background = '';
        }, 1500);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
    updateProgress();

    if (isEdit) {
        for (let i = 1; i <= totalSections; i++) {
            markSectionDone(i);
        }
        goToSection(1);
    } else {
        goToSection(1);
    }

    const salInput = document.getElementById('fSalary');
    if (salInput && salInput.value) {
        calcSalary();
    }

    const form = document.getElementById('empForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        let valid = true;
        const required = form.querySelectorAll('[required]');

        required.forEach(el => {
            const value = (el.value || '').trim();
            if (!value) {
                el.classList.add('is-invalid');
                valid = false;
            } else {
                el.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            e.preventDefault();

            for (let s = 1; s <= totalSections; s++) {
                const section = getSectionEl(s);
                if (section && section.querySelector('.is-invalid')) {
                    goToSection(s);
                    break;
                }
            }
            return;
        }

        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .7s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Saving...';
        }
    });
});
</script>
<style>
@keyframes spin {
    from { transform: rotate(0); }
    to { transform: rotate(360deg); }
}
</style>
JSCODE;

$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;

if (!empty($extra_scripts)) {
    echo $extra_scripts;
}

include 'includes/footer.php';

?>