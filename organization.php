<?php
require_once 'includes/config.php';
$page_title = 'Organization Management';

/* ─────────────────────────────────────────
   MOCK DATA  (replace with real DB queries)
───────────────────────────────────────── */
$organizations = [
    [
        'id'           => 1,
        'code'         => 'RKIVF',
        'name'         => 'Ramkrishna IVF Centre',
        'address1'     => 'Ramkrishna IVF Centre, Pakurtala More',
        'address2'     => '',
        'city'         => 'Siliguri',
        'state'        => 'West Bengal',
        'country'      => 'India',
        'pincode'      => '734001',
        'phone'        => '+91 93750 17xxx',
        'email'        => 'info@ramkrishnaivf.in',
        'website'      => 'https://ramkrishnaivf.in',
        'gstin'        => '19AABCR1234F1Z5',
        'pan'          => 'AABCR1234F',
        'logo'         => '',
        'active'       => true,
    ],
];

/* ─────────────────────────────────────────
   SELECTED ORG
───────────────────────────────────────── */
$selected_id  = isset($_GET['id']) ? (int)$_GET['id'] : ($organizations[0]['id'] ?? null);
$selected_org = null;
foreach ($organizations as $org) {
    if ($org['id'] === $selected_id) { $selected_org = $org; break; }
}
if (!$selected_org && !empty($organizations)) {
    $selected_org = $organizations[0];
    $selected_id  = $selected_org['id'];
}

/* ─────────────────────────────────────────
   MODES
───────────────────────────────────────── */
$mode = $_GET['mode'] ?? 'view';   // view | edit | add

/* ─────────────────────────────────────────
   POST — save
───────────────────────────────────────── */
$save_success = false;
$save_error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'save_org' || $action === 'add_org') {
        // TODO: validate + DB insert/update
        $save_success = true;
        $mode = 'view';
    }
    if ($action === 'delete_org') {
        // TODO: DB delete
        $save_success = true;
        $mode = 'view';
    }
}

function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function sel($v,$o){ return $v===$o?'selected':''; }
function val_or_dash($v){ return $v!==''?esc($v):''; }

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ═══════════════════════════════════════════
   ORGANIZATION MANAGEMENT PAGE
═══════════════════════════════════════════ */

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

/* toast */
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
    z-index: 999;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 8px 28px rgba(0,0,0,.2);
    transition: transform .3s ease;
    white-space: nowrap;
}
.om-toast.show { transform: translateX(-50%) translateY(0); }

/* responsive */
@media (max-width: 860px) {
    .om-layout { grid-template-columns: 1fr; }
    .om-list-panel { border-right: none; border-bottom: 1px solid #E5E7EB; }
}
@media (max-width: 560px) {
    .om-fields { grid-template-columns: 1fr; }
    .om-field:nth-child(even) { padding-left: 0; border-left: none; }
    .om-field { border-bottom: 1px solid #F3F4F6; }
}
</style>

<?php if ($save_success): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ omToast('✅','Organization saved successfully!'); });</script>
<?php endif; ?>

<!-- ── Page header ── -->
<div class="om-header">
    <div class="om-header-left">
        <a class="om-back-btn" style="text-decoration: none;" href="configuration#Organization" title="Back">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div>
            <div class="om-title">Organization Management</div>
            <div class="om-subtitle">List of Organizations</div>
        </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($mode === 'view' && $selected_org): ?>
        <a href="?id=<?= $selected_id ?>&mode=edit" class="btn" style="text-decoration: none;">Edit Details</a>
        <?php endif; ?>
        <a href="?mode=add" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px; text-decoration:none;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Organization
        </a>
    </div>
</div>

<!-- ── Main card ── -->
<div class="section-card" style="padding:0;overflow:hidden">
<div class="om-layout">

    <!-- ════════════════════════════
         LEFT  — ORG LIST
    ════════════════════════════ -->
    <div class="om-list-panel">

        <!-- Search -->
        <div class="om-search-wrap">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="omSearchInput" placeholder="Search items"
                oninput="omSearch(this.value)">
        </div>

        <!-- List -->
        <div id="omOrgList">
            <?php foreach ($organizations as $org): ?>
            <a href="?id=<?= $org['id'] ?>&mode=view"
               class="om-list-item <?= $selected_id===$org['id']&&$mode!=='add' ? 'active' : '' ?>"
               data-name="<?= strtolower(esc($org['name'])) ?> <?= strtolower(esc($org['code'])) ?>">
                <div>
                    <div class="om-list-item-name"><?= esc($org['name']) ?></div>
                    <div class="om-list-item-code"><?= esc($org['code']) ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Empty search result -->
        <div id="omNoResults" style="display:none;padding:20px 16px;font-size:13px;color:#9CA3AF;text-align:center">
            No organisations found
        </div>

    </div>

    <!-- ════════════════════════════
         RIGHT  — DETAIL / FORM
    ════════════════════════════ -->
    <div class="om-detail-panel">

        <?php if ($mode === 'view' && $selected_org): ?>
        <!-- ─────────────────────
             VIEW MODE
        ───────────────────────── -->
        <div class="om-detail-label">Organization Details</div>

        <!-- Company Information -->
        <div class="om-info-card">
            <div class="om-info-card-head">Company Information</div>
            <div class="om-info-card-body">
                <div class="om-fields">
                    <?php
                    $view_fields = [
                        ['code',     'Code Name'],
                        ['name',     'Company Name'],
                        ['address1', 'Address 1'],
                        ['address2', 'Address 2'],
                        ['city',     'City'],
                        ['state',    'State'],
                        ['country',  'Country'],
                        ['pincode',  'Pincode'],
                        ['phone',    'Phone Number'],
                        ['email',    'Email Address'],
                        ['website',  'Website'],
                        ['gstin',    'GSTIN'],
                        ['pan',      'PAN'],
                    ];
                    foreach ($view_fields as [$fkey, $flabel]):
                        $fval = $selected_org[$fkey] ?? '';
                    ?>
                    <div class="om-field">
                        <div class="om-field-label"><?= esc($flabel) ?></div>
                        <div class="om-field-val <?= $fval===''?'empty':'' ?>">
                            <?= $fval!=='' ? esc($fval) : '&nbsp;' ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Status -->
                    <div class="om-field">
                        <div class="om-field-label">Status</div>
                        <div class="om-field-val">
                            <span class="om-status" style="background:<?= $selected_org['active']?'#D1FAE5':'#FEE2E2' ?>;color:<?= $selected_org['active']?'#065F46':'#991B1B' ?>">
                                ● <?= $selected_org['active']?'Active':'Inactive' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Danger zone -->
        <div style="display:flex;justify-content:flex-end;margin-top:4px">
            <button class="btn" style="color:#DC2626;border-color:#FEE2E2;background:#FFF5F5;font-size:12.5px"
                onclick="document.getElementById('delConfirm').classList.add('open')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;vertical-align:middle"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Delete Organization
            </button>
        </div>

        <?php elseif ($mode === 'edit' && $selected_org): ?>
        <!-- ─────────────────────
             EDIT MODE
        ───────────────────────── -->
        <div class="om-detail-label">Edit Organization Details</div>

        <form method="POST" id="editOrgForm" novalidate>
        <input type="hidden" name="form_action" value="save_org">
        <input type="hidden" name="org_id" value="<?= (int)$selected_org['id'] ?>">

        <div class="om-info-card">
            <div class="om-info-card-head">Company Information</div>
            <div class="om-info-card-body">
                <div class="om-fields">
                    <?php
                    $edit_fields = [
                        ['code',     'Code Name',     'text',  true,  false, 'e.g. RKIVF'],
                        ['name',     'Company Name',  'text',  true,  false, 'e.g. Ramkrishna IVF Centre'],
                        ['address1', 'Address 1',     'text',  false, false, ''],
                        ['address2', 'Address 2',     'text',  false, false, ''],
                        ['city',     'City',          'text',  false, false, ''],
                        ['state',    'State',         'text',  false, false, ''],
                        ['country',  'Country',       'text',  false, false, ''],
                        ['pincode',  'Pincode',       'text',  false, false, ''],
                        ['phone',    'Phone Number',  'tel',   false, false, ''],
                        ['email',    'Email Address', 'email', false, false, ''],
                        ['website',  'Website',       'url',   false, false, ''],
                        ['gstin',    'GSTIN',         'text',  false, false, ''],
                        ['pan',      'PAN',           'text',  false, false, ''],
                    ];
                    foreach ($edit_fields as [$fkey,$flabel,$ftype,$req,$full,$ph]):
                        $fval = $selected_org[$fkey] ?? '';
                    ?>
                    <div class="om-field <?= $full?'full':'' ?>">
                        <div class="om-field-label <?= $req?'req':'' ?>"><?= esc($flabel) ?></div>
                        <input type="<?= $ftype ?>" name="<?= $fkey ?>"
                            value="<?= esc($fval) ?>"
                            placeholder="<?= esc($ph) ?>"
                            <?= $req?'required':'' ?>>
                    </div>
                    <?php endforeach; ?>

                    <!-- Status -->
                    <div class="om-field">
                        <div class="om-field-label">Status</div>
                        <select name="active">
                            <option value="1" <?= $selected_org['active']?'selected':'' ?>>Active</option>
                            <option value="0" <?= !$selected_org['active']?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="om-action-bar">
            <a href="?id=<?= $selected_id ?>&mode=view" class="btn" style="text-decoration: none;">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
        </form>

        <?php elseif ($mode === 'add'): ?>
        <!-- ─────────────────────
             ADD MODE
        ───────────────────────── -->
        <div class="om-detail-label">Add New Organization</div>

        <form method="POST" id="addOrgForm" novalidate>
        <input type="hidden" name="form_action" value="add_org">

        <div class="om-info-card">
            <div class="om-info-card-head">Company Information</div>
            <div class="om-info-card-body">
                <div class="om-fields">
                    <?php
                    $add_fields = [
                        ['code',     'Code Name',     'text',  true,  false, 'e.g. RKIVF'],
                        ['name',     'Company Name',  'text',  true,  false, 'e.g. Ramkrishna IVF Centre'],
                        ['address1', 'Address 1',     'text',  false, false, 'Street address'],
                        ['address2', 'Address 2',     'text',  false, false, 'Area / Landmark'],
                        ['city',     'City',          'text',  false, false, 'e.g. Siliguri'],
                        ['state',    'State',         'text',  false, false, 'e.g. West Bengal'],
                        ['country',  'Country',       'text',  false, false, 'e.g. India'],
                        ['pincode',  'Pincode',       'text',  false, false, 'e.g. 734001'],
                        ['phone',    'Phone Number',  'tel',   false, false, '+91 XXXXX XXXXX'],
                        ['email',    'Email Address', 'email', false, false, 'info@company.com'],
                        ['website',  'Website',       'url',   false, false, 'https://'],
                        ['gstin',    'GSTIN',         'text',  false, false, '22AAAAA0000A1Z5'],
                        ['pan',      'PAN',           'text',  false, false, 'ABCDE1234F'],
                    ];
                    foreach ($add_fields as [$fkey,$flabel,$ftype,$req,$full,$ph]):
                    ?>
                    <div class="om-field <?= $full?'full':'' ?>">
                        <div class="om-field-label <?= $req?'req':'' ?>"><?= esc($flabel) ?></div>
                        <input type="<?= $ftype ?>" name="<?= $fkey ?>"
                            placeholder="<?= esc($ph) ?>"
                            <?= $req?'required':'' ?>>
                    </div>
                    <?php endforeach; ?>

                    <div class="om-field">
                        <div class="om-field-label">Status</div>
                        <select name="active">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="om-action-bar">
            <a href="?mode=view" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px;vertical-align:middle"><polyline points="20 6 9 17 4 12"/></svg>
                Save Organization
            </button>
        </div>
        </form>

        <?php else: ?>
        <!-- No selection -->
        <div class="om-empty">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Select an organization from the list to view details
        </div>
        <?php endif; ?>

    </div><!-- end detail panel -->

</div><!-- end om-layout -->
</div><!-- end section-card -->


<!-- ── Delete Confirm Modal ── -->
<div class="del-confirm" id="delConfirm" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="del-box">
        <div style="width:56px;height:56px;background:#FEE2E2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px">🗑</div>
        <h3 style="font-size:16px;font-weight:700;color:#111827;margin-bottom:8px">Delete Organization?</h3>
        <p style="font-size:13px;color:#6B7280;line-height:1.6;margin-bottom:20px">
            This will permanently delete <strong><?= esc($selected_org['name'] ?? '') ?></strong> and all associated data. This action cannot be undone.
        </p>
        <div style="display:flex;gap:8px;justify-content:center">
            <button class="btn" onclick="document.getElementById('delConfirm').classList.remove('open')" style="min-width:100px">Cancel</button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="form_action" value="delete_org">
                <input type="hidden" name="org_id" value="<?= (int)($selected_org['id']??0) ?>">
                <button type="submit" class="btn" style="background:#DC2626;color:#fff;border-color:#DC2626;min-width:100px">Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- ── Toast ── -->
<div class="om-toast" id="omToastEl">
    <span id="omToastIcon">✅</span>
    <span id="omToastMsg">Done!</span>
</div>

<script>
/* ── Search ── */
function omSearch(q) {
    q = q.toLowerCase().trim();
    const items   = document.querySelectorAll('.om-list-item');
    let   visible = 0;
    items.forEach(function(item) {
        const match = !q || (item.dataset.name||'').includes(q);
        item.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const noRes = document.getElementById('omNoResults');
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}

/* ── Form validation ── */
function validateOmForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    let ok = true;
    form.querySelectorAll('[required]').forEach(function(el) {
        if (!el.value.trim()) {
            el.style.borderColor = '#DC2626';
            el.style.boxShadow   = '0 0 0 3px rgba(220,38,38,.08)';
            ok = false;
        } else {
            el.style.borderColor = '';
            el.style.boxShadow   = '';
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

/* ── Toast ── */
function omToast(icon, msg) {
    const t  = document.getElementById('omToastEl');
    const ti = document.getElementById('omToastIcon');
    const tm = document.getElementById('omToastMsg');
    ti.textContent = icon;
    tm.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(function(){ t.classList.remove('show'); }, 3200);
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>