<?php
require_once 'includes/config.php';
$page_title = 'Salary Components';

/* ─────────────────────────────────────────
   DATA
───────────────────────────────────────── */
$earnings = [
    ['code'=>'ALL12','name'=>'Allowances',               'expr'=>'ALL12'],
    ['code'=>'ARR',  'name'=>'Arrears',                  'expr'=>'ARR'],
    ['code'=>'BAS',  'name'=>'Basic',                    'expr'=>'BAS * (PAID_DAYS / TOTALDAYS)'],
    ['code'=>'BON',  'name'=>'Bonus',                    'expr'=>'BON'],
    ['code'=>'CEA',  'name'=>'Children Eduction Allowance','expr'=>'CEA * (PAID_DAYS / TOTALDAYS)'],
    ['code'=>'CF',   'name'=>'Consultant Fee',           'expr'=>'CF * (PAID_DAYS / TOTALDAYS)'],
    ['code'=>'CONV', 'name'=>'Conveyance',               'expr'=>'CONV * (PAID_DAYS / TOTALDAYS)'],
    ['code'=>'DA',   'name'=>'Dearness Allowance',       'expr'=>'DA * (PAID_DAYS / TOTALDAYS)'],
    ['code'=>'HRA',  'name'=>'House Rent Allowance',     'expr'=>'HRA * (PAID_DAYS / TOTALDAYS)'],
    ['code'=>'LTA',  'name'=>'Leave Travel Allowance',   'expr'=>'LTA * (PAID_DAYS / TOTALDAYS)'],
    ['code'=>'MED',  'name'=>'Medical Allowance',        'expr'=>'MED * (PAID_DAYS / TOTALDAYS)'],
    ['code'=>'NPA',  'name'=>'Night Shift Allowance',    'expr'=>'NPA'],
    ['code'=>'OT',   'name'=>'Overtime',                 'expr'=>'OT'],
    ['code'=>'SA',   'name'=>'Special Allowance',        'expr'=>'SA * (PAID_DAYS / TOTALDAYS)'],
    ['code'=>'VPA',  'name'=>'Variable Pay',             'expr'=>'VPA'],
    ['code'=>'WA',   'name'=>'Washing Allowance',        'expr'=>'WA * (PAID_DAYS / TOTALDAYS)'],
];

$deductions = [
    ['code'=>'ADD',       'name'=>'Advance Deduction',       'expr'=>'ADD'],
    ['code'=>'LNA',       'name'=>'Loans & Advances',        'expr'=>'LNA'],
    ['code'=>'LND',       'name'=>'Loan Deduction',          'expr'=>'LND'],
    ['code'=>'MOBD',      'name'=>'Mobile Deductions',       'expr'=>'MOBD'],
    ['code'=>'PNL',       'name'=>'Penalties',               'expr'=>'PNL'],
    ['code'=>'PSF1',      'name'=>'PSF1',                    'expr'=>'(GRSAL)*(PAID_DAYS / TOTALDAYS)*0.01'],
    ['code'=>'TDS',       'name'=>'TDS',                     'expr'=>'TDS'],
    ['code'=>'TDS1',      'name'=>'TDS1',                    'expr'=>'(GRSAL + ALL) * (PAID_DAYS / TOTALDAYS) * 0.01'],
    ['code'=>'ESIEMPDED', 'name'=>'ESI Employee Deduction',  'expr'=>'ESIEMPDED'],
    ['code'=>'PFEMPDED',  'name'=>'PF Employee Deduction',   'expr'=>'PFEMPDED'],
    ['code'=>'PT',        'name'=>'Professional Tax',        'expr'=>'PT'],
];

$employer = [
    ['code'=>'ESIEMPLRDED',       'name'=>'ESI Employer Deduction',   'expr'=>'ESIEMPLRDED'],
    ['code'=>'PFADMCHGSEDLIAC21', 'name'=>'PF Admin Charges AC22',    'expr'=>'PFADMCHGSEDLIAC21'],
    ['code'=>'PFADMCHGSEPFAC01',  'name'=>'PF Admin Charges AC02',    'expr'=>'PFADMCHGSEPFAC01'],
    ['code'=>'PFEDLIAC21',        'name'=>'PF EDLI AC21',             'expr'=>'PFEDLIAC21'],
    ['code'=>'PFEMPLRDED',        'name'=>'PF Employer Deduction',    'expr'=>'PFEMPLRDED'],
    ['code'=>'PFPENSIONFUND',     'name'=>'PF Pension Fund',          'expr'=>'PFPENSIONFUND'],
];

$categories = [
    'Earning'  => ['Allowances','Basic','HRA','Bonus','Special Pay','Overtime'],
    'Deduction'=> ['Tax Deductions','Loan Deductions','Other Deductions'],
    'Employer' => ['PF Contributions','ESI Contributions','Admin Charges'],
];

/* ── mode & tab ── */
$active_tab = $_GET['tab']  ?? 'earnings'; // earnings | deductions | employer
$mode       = $_GET['mode'] ?? 'list';     // list | add | edit
$edit_code  = $_GET['code'] ?? '';

/* find edit record */
$edit_rec = null;
if ($mode === 'edit' && $edit_code !== '') {
    $all = array_merge($earnings, $deductions, $employer);
    foreach ($all as $r) {
        if ($r['code'] === $edit_code) { $edit_rec = $r; break; }
    }
    // determine type
    foreach ($earnings   as $r) if ($r['code']==$edit_code) $edit_rec['type']='Earning';
    foreach ($deductions as $r) if ($r['code']==$edit_code) $edit_rec['type']='Deduction';
    foreach ($employer   as $r) if ($r['code']==$edit_code) $edit_rec['type']='Employer';
}

/* POST */
$save_ok=false; $save_msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act=$_POST['_action']??'';
    if ($act==='add')    { $save_ok=true; $save_msg='Salary component added!'; }
    if ($act==='save')   { $save_ok=true; $save_msg='Salary component updated!'; }
    if ($act==='delete') { $save_ok=true; $save_msg='Salary component deleted!'; }
}

function esc($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ════════════════════════════════════════
   SALARY COMPONENTS PAGE
════════════════════════════════════════ */

/* config tab bar */
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

/* breadcrumb */
.sc-bc {
    display:flex;align-items:center;gap:8px;font-size:13.5px;
    font-weight:500;color:#374151;
}
.sc-bc a      { color:#374151;text-decoration:none; }
.sc-bc a:hover{ color:#2563EB; }
.sc-bc .sep   { color:#D1D5DB;font-size:16px; }
.sc-bc .cur   { font-weight:600;color:#374151; }

/* top bar */
.sc-topbar {
    padding:14px 24px;border-bottom:1px solid #E5E7EB;
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:10px;
}

/* ── type tabs (Earnings / Deductions / Employer) ── */
.sc-tabs {
    display:flex;align-items:center;gap:0;padding:0 24px;
    border-bottom:1px solid #E5E7EB;background:#fff;
}
.sc-tab {
    padding:12px 18px;font-size:13.5px;font-weight:500;color:#6B7280;
    cursor:pointer;border:none;background:transparent;
    border-bottom:2.5px solid transparent;white-space:nowrap;
    transition:color .15s,border-color .15s;text-decoration:none;
    display:block;margin-bottom:-1px;font-family:inherit;
}
.sc-tab:hover  { color:#111827; }
.sc-tab.active { color:#2563EB;border-bottom-color:#2563EB;font-weight:600; }

/* search */
.sc-search {
    display:flex;align-items:center;gap:8px;padding:8px 12px;
    border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;
    width:230px;transition:border-color .15s;
}
.sc-search:focus-within { border-color:#2563EB; }
.sc-search svg { width:14px;height:14px;stroke:#9CA3AF;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0; }
.sc-search input { border:none;outline:none;font-size:13px;font-family:inherit;color:#374151;background:transparent;width:100%; }

/* table */
.sc-table-wrap { overflow-y:auto;max-height:430px;overflow-x:auto; }
.sc-table { width:100%;border-collapse:collapse;font-size:13.5px; }
.sc-table thead tr { background:#fff;position:sticky;top:0;z-index:5; }
.sc-table th {
    padding:12px 18px;text-align:left;font-weight:600;color:#374151;
    font-size:13.5px;border-bottom:1px solid #E5E7EB;
    white-space:nowrap;background:#fff;
}
.sc-table td {
    padding:14px 18px;border-bottom:1px solid #F3F4F6;
    color:#374151;vertical-align:middle;
}
.sc-table tbody tr:hover td { background:#F9FAFB; }
.sc-table tr:last-child td  { border-bottom:none; }

/* code column */
.sc-code {
    font-size:13.5px;font-weight:600;color:#111827;
}
/* expression column */
.sc-expr {
    font-family:'Courier New',monospace;font-size:13px;color:#374151;
}
/* edit icon button */
.sc-edit-btn {
    color:#2563EB;cursor:pointer;font-size:16px;
    background:none;border:none;padding:4px 6px;
    border-radius:5px;transition:background .15s;
    text-decoration:none;display:inline-flex;align-items:center;
}
.sc-edit-btn:hover { background:#EFF6FF; }
/* action items col */
.sc-table th:last-child,
.sc-table td:last-child { text-align:center;width:100px; }

/* ── ADD / EDIT FORM ── */
.sc-form-wrap { padding:28px 28px 32px; }
.sc-form-title {
    font-size:14px;font-weight:700;color:#111827;
    letter-spacing:.4px;text-transform:uppercase;
    margin-bottom:24px;
}
/* underline field style (matching screenshots) */
.sc-row { display:grid;gap:24px;margin-bottom:24px; }
.sc-row.c2 { grid-template-columns:1fr 1fr; }
.sc-row.c1 { grid-template-columns:1fr; }
.sc-fg { display:flex;flex-direction:column;gap:5px; }
.sc-fg label { font-size:13px;font-weight:400;color:#374151; }
.sc-fg input,
.sc-fg select {
    border:none;border-bottom:1.5px solid #D1D5DB;border-radius:0;
    padding:8px 0;font-size:14px;font-family:inherit;
    color:#111827;outline:none;background:transparent;
    width:100%;transition:border-color .15s;
}
.sc-fg input:focus,.sc-fg select:focus { border-bottom-color:#2563EB; }
.sc-fg input::placeholder { color:#D1D5DB; }
/* expression field — full width */
.sc-fg.full { grid-column:1 / -1; }

/* statutory accordion */
.sc-stat-head {
    display:flex;align-items:center;justify-content:space-between;
    cursor:pointer;padding:12px 0;
    border-top:1px solid #E5E7EB;margin-top:8px;
    font-size:14px;font-weight:500;color:#374151;
    user-select:none;
}
.sc-stat-head svg { width:16px;height:16px;stroke:#9CA3AF;fill:none;stroke-width:2;stroke-linecap:round;transition:transform .2s; }
.sc-stat-head.open svg { transform:rotate(180deg); }
.sc-stat-body { display:none;padding:8px 0 14px;animation:scFade .2s ease; }
.sc-stat-body.open { display:block; }
@keyframes scFade { from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)} }
.sc-stat-item {
    font-size:13.5px;color:#C4C9D4;padding:7px 0;
    display:flex;align-items:center;gap:10px;
}
.sc-stat-note {
    font-size:13px;color:#9CA3AF;margin-top:10px;
}
.sc-stat-note a { color:#2563EB;text-decoration:none; }
.sc-stat-note a:hover { text-decoration:underline; }

/* form action buttons */
.sc-form-actions {
    display:flex;justify-content:flex-end;gap:10px;
    padding-top:20px;margin-top:12px;border-top:1px solid #E5E7EB;
}
.sc-cancel-btn {
    padding:9px 24px;background:#fff;color:#374151;
    border:1.5px solid #D1D5DB;border-radius:8px;
    font-size:14px;font-weight:500;cursor:pointer;
    font-family:inherit;transition:.15s;text-decoration:none;
    display:inline-flex;align-items:center;
}
.sc-cancel-btn:hover { border-color:#374151; }
.sc-save-btn {
    padding:9px 28px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
    font-family:inherit;transition:background .15s;
}
.sc-save-btn:hover { background:#1D4ED8; }
.sc-delete-btn {
    padding:9px 24px;background:#fff;color:#DC2626;
    border:1.5px solid #DC2626;border-radius:8px;
    font-size:14px;font-weight:500;cursor:pointer;
    font-family:inherit;transition:.15s;
}
.sc-delete-btn:hover { background:#FEE2E2; }

/* toast */
.sc-toast {
    position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
    background:#111827;color:#fff;padding:11px 20px;border-radius:10px;
    font-size:13px;font-weight:500;z-index:999;display:flex;align-items:center;
    gap:8px;box-shadow:0 8px 28px rgba(0,0,0,.2);transition:transform .3s ease;white-space:nowrap;
}
.sc-toast.show { transform:translateX(-50%) translateY(0); }

/* responsive */
@media(max-width:700px){
    .sc-row.c2 { grid-template-columns:1fr; }
    .sc-topbar  { flex-direction:column;align-items:flex-start; }
}
</style>

<?php if($save_ok): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ scToast('✅','<?= esc($save_msg) ?>'); });</script>
<?php endif; ?>

<!-- ════════════════════════════════════════
     CONFIG TAB BAR
════════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
    <div class="cfg-tabs">
        <?php foreach(['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
        <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k==='Payroll'?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
    
    <!-- ── TOP BAR ── -->
    <div class="sc-topbar">
        <div class="sc-bc">
            <a href="configuration#Payroll">Payroll</a>
            <span class="sep">›</span>
            <span class="cur">Salary Components</span>
        </div>
        <?php if($mode==='list'): ?>
        <a href="?mode=add" class="btn btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Salary Component
        </a>
        <?php endif; ?>
    </div>

    <?php /* ══════════════════════════
        LIST VIEW
    ══════════════════════════ */ if($mode==='list'): ?>

    <!-- Type tabs + search row -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:0 24px 0 0;border-bottom:1px solid #E5E7EB;flex-wrap:wrap;gap:0">
        <div class="sc-tabs">
            <?php foreach(['earnings'=>'Earnings','deductions'=>'Deductions','employer'=>'Employer Contribution'] as $tk=>$tl): ?>
            <a href="?tab=<?= $tk ?>&mode=list" class="sc-tab <?= $active_tab===$tk?'active':'' ?>"><?= $tl ?></a>
            <?php endforeach; ?>
        </div>
        <div class="sc-search" style="margin:8px 0">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="scSearchInput" placeholder="Search table items" oninput="filterScTable(this.value)">
        </div>
    </div>

    <!-- Table -->
    <?php
    $rows = match($active_tab) {
        'deductions' => $deductions,
        'employer'   => $employer,
        default      => $earnings,
    };
    ?>
    <div class="sc-table-wrap">
    <table class="sc-table" id="scTable">
        <thead>
            <tr>
                <th>Code</th>
                <th>Salary Component</th>
                <th>Expression</th>
                <th>Action Items</th>
            </tr>
        </thead>
        <tbody id="scTableBody">
        <?php foreach($rows as $row): ?>
        <tr data-search="<?= strtolower(esc($row['code'])) ?> <?= strtolower(esc($row['name'])) ?> <?= strtolower(esc($row['expr'])) ?>">
            <td><span class="sc-code"><?= esc($row['code']) ?></span></td>
            <td><?= esc($row['name']) ?></td>
            <td><span class="sc-expr"><?= esc($row['expr']) ?></span></td>
            <td>
                <a href="?mode=edit&code=<?= urlencode($row['code']) ?>&tab=<?= esc($active_tab) ?>" class="sc-edit-btn" title="Edit">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div style="padding:10px 18px;border-top:1px solid #F3F4F6">
        <span style="font-size:12px;color:#9CA3AF"><?= count($rows) ?> components</span>
    </div>

    <?php /* ══════════════════════════
        ADD VIEW
    ══════════════════════════ */ elseif($mode==='add'): ?>

    <div class="sc-form-wrap">
        <div class="sc-form-title">NEW SALARY COMPONENT</div>

        <form method="POST" id="addScForm" novalidate>
        <input type="hidden" name="_action" value="add">

        <div class="sc-row c2">
            <div class="sc-fg">
                <label>Salary Type</label>
                <select name="salary_type" id="addSalaryType" onchange="updateCategories()">
                    <option value="Earning">Earning</option>
                    <option value="Deduction">Deduction</option>
                    <option value="Employer">Employer Contribution</option>
                </select>
            </div>
            <div class="sc-fg">
                <label>Component Category</label>
                <select name="component_category" id="addCategory">
                    <option>Allowances</option>
                    <option>Basic</option>
                    <option>HRA</option>
                    <option>Bonus</option>
                    <option>Special Pay</option>
                    <option>Overtime</option>
                </select>
            </div>
        </div>

        <div class="sc-row c2">
            <div class="sc-fg">
                <label>Code</label>
                <input type="text" name="code"
                    placeholder="Salary Component Code"
                    oninput="this.value=this.value.toUpperCase().replace(/\s/g,'')" required>
            </div>
            <div class="sc-fg">
                <label>Salary Component Name</label>
                <input type="text" name="component_name"
                    placeholder="Salary Component Name (Basic, HRA, etc.)" required>
            </div>
        </div>

        <div class="sc-row c1">
            <div class="sc-fg">
                <label>Expression</label>
                <input type="text" name="expression" placeholder="Expression"
                    style="font-family:'Courier New',monospace">
            </div>
        </div>

        <!-- Statutory Considerations -->
        <div>
            <div class="sc-stat-head" id="statHead" onclick="toggleStat()">
                <span>Statutory Considerations</span>
                <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="sc-stat-body" id="statBody">
                <div class="sc-stat-item">PF (Provident Fund)</div>
                <div class="sc-stat-item">ESI (Employee State Insurance)</div>
                <div class="sc-stat-item">TDS (Tax Deducted at Source)</div>
                <div class="sc-stat-item">CTC (Cost To Company)</div>
                <div class="sc-stat-item">PT (Professional Tax)</div>
                <div class="sc-stat-note">
                    Note: Choose these under <a href="SalaryComponentsCategory">Salary Component Category</a>
                </div>
            </div>
        </div>

        <div class="sc-form-actions">
            <a href="?tab=<?= esc($active_tab) ?>&mode=list" class="sc-cancel-btn">Cancel</a>
            <button type="submit" class="sc-save-btn" onclick="return validateScForm()">Add</button>
        </div>
        </form>
    </div>

    <?php /* ══════════════════════════
        EDIT VIEW
    ══════════════════════════ */ elseif($mode==='edit' && $edit_rec): ?>

    <div class="sc-form-wrap">
        <div class="sc-form-title"><?= esc(strtoupper($edit_rec['name'])) ?></div>

        <form method="POST" id="editScForm" novalidate>
        <input type="hidden" name="_action" value="save">
        <input type="hidden" name="original_code" value="<?= esc($edit_rec['code']) ?>">

        <div class="sc-row c2">
            <div class="sc-fg">
                <label>Salary Type</label>
                <select name="salary_type" onchange="updateCategories(this)">
                    <option <?= ($edit_rec['type']??'')==='Earning'  ?'selected':'' ?>>Earning</option>
                    <option <?= ($edit_rec['type']??'')==='Deduction'?'selected':'' ?>>Deduction</option>
                    <option <?= ($edit_rec['type']??'')==='Employer' ?'selected':'' ?>>Employer Contribution</option>
                </select>
            </div>
            <div class="sc-fg">
                <label>Component Category</label>
                <select name="component_category">
                    <option selected>Allowances</option>
                    <option>Basic</option>
                    <option>HRA</option>
                    <option>Bonus</option>
                    <option>Special Pay</option>
                    <option>Overtime</option>
                    <option>Tax Deductions</option>
                    <option>Loan Deductions</option>
                    <option>PF Contributions</option>
                    <option>ESI Contributions</option>
                </select>
            </div>
        </div>

        <div class="sc-row c2">
            <div class="sc-fg">
                <label>Code</label>
                <input type="text" name="code"
                    value="<?= esc($edit_rec['code']) ?>"
                    oninput="this.value=this.value.toUpperCase().replace(/\s/g,'')" required>
            </div>
            <div class="sc-fg">
                <label>Salary Component Name</label>
                <input type="text" name="component_name"
                    value="<?= esc($edit_rec['name']) ?>" required>
            </div>
        </div>

        <div class="sc-row c1">
            <div class="sc-fg">
                <label>Expression</label>
                <input type="text" name="expression"
                    value="<?= esc($edit_rec['expr']) ?>"
                    style="font-family:'Courier New',monospace">
            </div>
        </div>

        <!-- Statutory Considerations — expanded in edit mode -->
        <div>
            <div class="sc-stat-head open" id="statHead" onclick="toggleStat()">
                <span>Statutory Considerations</span>
                <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="sc-stat-body open" id="statBody">
                <div class="sc-stat-item">PF (Provident Fund)</div>
                <div class="sc-stat-item">ESI (Employee State Insurance)</div>
                <div class="sc-stat-item">TDS (Tax Deducted at Source)</div>
                <div class="sc-stat-item">CTC (Cost To Company)</div>
                <div class="sc-stat-item">PT (Professional Tax)</div>
                <div class="sc-stat-note">
                    Note: Choose these under <a href="SalaryComponentsCategory">Salary Component Category</a>
                </div>
            </div>
        </div>

        <div class="sc-form-actions">
            <form method="POST" style="display:inline" id="deleteForm">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="code" value="<?= esc($edit_rec['code']) ?>">
                <button type="submit" class="sc-delete-btn" onclick="return confirmDelete('<?= esc($edit_rec['name']) ?>')">Delete</button>
            </form>
            <a href="?tab=<?= esc($active_tab) ?>&mode=list" class="sc-cancel-btn">Cancel</a>
            <button type="submit" form="editScForm" class="sc-save-btn" onclick="return validateScForm()">Save</button>
        </div>
        </form>
    </div>

    <?php else: /* edit_code not found */ ?>
    <div style="padding:40px 24px;text-align:center;color:#9CA3AF;font-size:13.5px">
        Component not found. <a href="?tab=<?= esc($active_tab) ?>&mode=list" style="color:#2563EB">← Back to list</a>
    </div>
    <?php endif; ?>

</div><!-- end .section-card -->

<!-- ── Toast ── -->
<div class="sc-toast" id="scToastEl">
    <span id="scToastIcon">✅</span><span id="scToastMsg">Done!</span>
</div>

<!-- ════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════ -->
<script>
/* ── Toast ── */
function scToast(icon, msg) {
    var t=document.getElementById('scToastEl');
    document.getElementById('scToastIcon').textContent=icon;
    document.getElementById('scToastMsg').textContent=msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t=setTimeout(function(){ t.classList.remove('show'); },3200);
}

/* ── Table search ── */
function filterScTable(q) {
    q=q.toLowerCase().trim();
    document.querySelectorAll('#scTableBody tr').forEach(function(r){
        r.style.display=!q||(r.dataset.search||'').includes(q)?'':'none';
    });
}

/* ── Statutory accordion ── */
function toggleStat() {
    var head=document.getElementById('statHead');
    var body=document.getElementById('statBody');
    if(!head||!body) return;
    head.classList.toggle('open');
    body.classList.toggle('open');
}

/* ── Category options per type ── */
var catOptions = {
    'Earning':   ['Allowances','Basic','HRA','Bonus','Special Pay','Overtime','Variable Pay'],
    'Deduction': ['Tax Deductions','Loan Deductions','PF Deductions','ESI Deductions','Other Deductions'],
    'Employer Contribution': ['PF Contributions','ESI Contributions','Admin Charges','Pension Fund'],
};

function updateCategories(selEl) {
    var type = (selEl||document.getElementById('addSalaryType'))?.value || 'Earning';
    var catSel = document.getElementById('addCategory') || document.querySelector('select[name="component_category"]');
    if (!catSel) return;
    var opts = catOptions[type] || catOptions['Earning'];
    catSel.innerHTML = opts.map(function(o){ return '<option>'+o+'</option>'; }).join('');
}

/* ── Validate form ── */
function validateScForm() {
    var form = document.getElementById('addScForm') || document.getElementById('editScForm');
    if (!form) return true;
    var ok = true;
    form.querySelectorAll('[required]').forEach(function(el) {
        if (!el.value.trim()) {
            el.style.borderBottomColor='#DC2626';
            ok=false;
        } else {
            el.style.borderBottomColor='';
        }
    });
    if (!ok) { scToast('⚠','Please fill in all required fields.'); }
    return ok;
}

/* ── Confirm delete ── */
function confirmDelete(name) {
    return confirm('Delete "' + name + '"? This cannot be undone.');
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>