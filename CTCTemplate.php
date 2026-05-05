<?php
require_once 'includes/config.php';
$page_title = 'CTC Templates';

/* ─────────────────────────────────────────
   DUMMY DATA
───────────────────────────────────────── */
$templates = [
    [
        'id'       => 1,
        'name'     => 'Default',
        'pt_state' => 'West Bengal',
        'pf'       => true,
        'esi'      => true,
        'remarks'  => '',
        'earnings' => [
            ['id'=>1,'name'=>'Basic Salary',       'calc'=>'Fixed','value'=>'60','unit'=>'% of CTC'],
            ['id'=>2,'name'=>'House Rent Allowance','calc'=>'Fixed','value'=>'40','unit'=>'% of Basic'],
            ['id'=>3,'name'=>'Special Allowance',   'calc'=>'Fixed','value'=>'0', 'unit'=>'Balance'],
        ],
        'deductions' => [
            ['id'=>1,'name'=>'Professional Tax','calc'=>'Slab','value'=>'200','unit'=>'/month'],
            ['id'=>2,'name'=>'PF Employee',     'calc'=>'Fixed','value'=>'12','unit'=>'% of Basic'],
            ['id'=>3,'name'=>'ESI Employee',    'calc'=>'Fixed','value'=>'0.75','unit'=>'% of Gross'],
        ],
        'employer' => [
            ['id'=>1,'name'=>'PF Employer',  'calc'=>'Fixed','value'=>'12','unit'=>'% of Basic'],
            ['id'=>2,'name'=>'ESI Employer', 'calc'=>'Fixed','value'=>'3.25','unit'=>'% of Gross'],
        ],
    ],
    [
        'id'=>2,'name'=>'Senior Staff','pt_state'=>'West Bengal',
        'pf'=>true,'esi'=>false,'remarks'=>'For senior grade employees',
        'earnings'=>[
            ['id'=>1,'name'=>'Basic Salary','calc'=>'Fixed','value'=>'50','unit'=>'% of CTC'],
            ['id'=>2,'name'=>'HRA',         'calc'=>'Fixed','value'=>'40','unit'=>'% of Basic'],
        ],
        'deductions'=>[
            ['id'=>1,'name'=>'Professional Tax','calc'=>'Slab','value'=>'200','unit'=>'/month'],
            ['id'=>2,'name'=>'PF Employee',     'calc'=>'Fixed','value'=>'12','unit'=>'% of Basic'],
        ],
        'employer'=>[
            ['id'=>1,'name'=>'PF Employer','calc'=>'Fixed','value'=>'12','unit'=>'% of Basic'],
        ],
    ],
];

$pt_states = ['West Bengal','Maharashtra','Karnataka','Tamil Nadu','Andhra Pradesh','Telangana','Gujarat','Madhya Pradesh'];

$salary_components = [
    'earnings'    => ['Basic Salary','House Rent Allowance','Special Allowance','Conveyance Allowance','Medical Allowance','Variable Pay','Performance Bonus'],
    'deductions'  => ['Professional Tax','PF Employee','ESI Employee','TDS','Loan Recovery'],
    'employer'    => ['PF Employer','ESI Employer','Gratuity'],
];
$calc_types = ['Fixed','Slab','Formula','% of Basic','% of CTC','% of Gross'];

/* ── Active template ── */
$active_id = isset($_GET['id']) ? (int)$_GET['id'] : ($templates[0]['id'] ?? null);
$active_tpl = null;
foreach ($templates as $t) { if ($t['id'] === $active_id) { $active_tpl = $t; break; } }
if (!$active_tpl && !empty($templates)) { $active_tpl = $templates[0]; $active_id = $active_tpl['id']; }

/* ── Mode ── */
$mode = $_GET['mode'] ?? 'view';   // view | add | edit

/* ── POST ── */
$save_ok = false; $save_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'add_template')  { $save_ok=true; $save_msg='CTC Template added successfully!';   $mode='view'; }
    if ($act === 'save_template') { $save_ok=true; $save_msg='CTC Template updated successfully!'; $mode='view'; }
    if ($act === 'delete_template'){ $save_ok=true; $save_msg='Template deleted.'; }
}

function esc($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ════════════════════════════════════════
   CTC TEMPLATES PAGE
════════════════════════════════════════ */

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

/* ── Two-column layout ── */
.ctc-layout {
    display:grid;
    grid-template-columns:280px 1fr;
    gap:0;
    min-height:520px;
}

/* ── Left panel ── */
.ctc-left {
    border-right:1px solid #E5E7EB;
    padding:12px 0;
}
.ctc-left-head {
    font-size:12px;font-weight:600;color:#9CA3AF;
    letter-spacing:.4px;padding:0 16px 10px;
    text-transform:uppercase;
}
.ctc-tpl-item {
    display:flex;align-items:center;justify-content:space-between;
    padding:11px 16px;cursor:pointer;transition:background .15s;
    font-size:13.5px;color:#374151;border-bottom:1px solid #F9FAFB;
    position:relative;text-decoration:none;
}
.ctc-tpl-item:hover  { background:#F9FAFB; }
.ctc-tpl-item.active { background:#EFF6FF; }
.ctc-tpl-item.active .ctc-tpl-name { color:#2563EB;font-weight:600; }
.ctc-tpl-name { font-size:13.5px;font-weight:500; }
.ctc-tpl-arrow {
    width:20px;height:20px;border:1.5px solid #E5E7EB;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:10px;color:#9CA3AF;flex-shrink:0;transition:.15s;
}
.ctc-tpl-item.active .ctc-tpl-arrow { border-color:#2563EB;color:#2563EB; }

.ctc-left-sub { font-size:12px;color:#9CA3AF; font-weight:600;letter-spacing:.4px;padding:6px 16px;text-transform:uppercase; }

/* add template btn in left */
.ctc-add-tpl-btn {
    display:flex;align-items:center;gap:6px;padding:10px 16px;
    font-size:13px;font-weight:600;color:#2563EB;cursor:pointer;
    background:none;border:none;font-family:inherit;width:100%;
    transition:background .15s;text-align:left;
}
.ctc-add-tpl-btn:hover { background:#EFF6FF; }

/* ── Right panel ── */
.ctc-right { padding:24px 32px 32px; }


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

/* section header */
.ctc-right-title {
    font-size:14px;font-weight:700;color:#111827;
    letter-spacing:.5px;text-transform:uppercase;margin-bottom:20px;
}

/* ── Form fields (underline style matching screenshot) ── */
.ctc-row { display:grid;gap:20px;margin-bottom:20px; }
.ctc-row.c1 { grid-template-columns:1fr; }
.ctc-row.c2 { grid-template-columns:1fr 1fr; }
.ctc-row.c3 { grid-template-columns:1fr 1fr 1fr; }

.ctc-fg { display:flex;flex-direction:column;gap:5px; }
.ctc-fg label {
    font-size:13px;font-weight:400;color:#374151;
}
.ctc-fg label .req { color:#DC2626;margin-left:2px; }
.ctc-fg input[type=text],
.ctc-fg input[type=email],
.ctc-fg select,
.ctc-fg textarea {
    border:none;border-bottom:1.5px solid #D1D5DB;border-radius:0;
    padding:6px 0;font-size:13.5px;font-family:inherit;
    color:#111827;outline:none;background:transparent;
    transition:border-color .15s;width:100%;
}
.ctc-fg input:focus,.ctc-fg select:focus,.ctc-fg textarea:focus {
    border-bottom-color:#2563EB;
}
.ctc-fg input::placeholder,.ctc-fg textarea::placeholder { color:#C4C9D4; }

/* checkboxes row */
.ctc-checks { display:flex;align-items:center;flex-wrap:wrap;gap:24px;margin-bottom:20px; }
.ctc-check-item { display:flex;align-items:center;gap:8px;font-size:13.5px;color:#374151; }
.ctc-check-item input[type=checkbox] { width:16px;height:16px;accent-color:#2563EB;cursor:pointer; }

/* PT State inline (appears after checkbox is checked) */
.ctc-pt-wrap { display:flex;align-items:center;gap:10px;flex-wrap:wrap; }
.ctc-pt-wrap select {
    border:none;border-bottom:1.5px solid #D1D5DB;
    padding:4px 0;font-size:13.5px;font-family:inherit;
    color:#374151;outline:none;background:transparent;
    min-width:160px;transition:border-color .15s;
}
.ctc-pt-wrap select:focus { border-bottom-color:#2563EB; }

/* ── Salary Components ── */
.ctc-sc-title {
    font-size:13px;font-weight:700;color:#111827;
    letter-spacing:.4px;text-transform:uppercase;
    padding-bottom:12px;border-bottom:1px solid #E5E7EB;margin-bottom:16px;
}

.ctc-sc-block {
    border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;margin-bottom:14px;
}
.ctc-sc-head {
    padding:13px 18px;background:#FAFAFA;border-bottom:1px solid #E5E7EB;
}
.ctc-sc-head-title { font-size:13px;font-weight:700;color:#111827;letter-spacing:.3px; }
.ctc-sc-head-sub   { font-size:12px;color:#9CA3AF;margin-top:3px; }

.ctc-sc-body { padding:12px 18px 14px; }

/* component row */
.ctc-comp-row {
    display:grid;grid-template-columns:1fr 1fr 1fr 1fr 36px;
    gap:10px;align-items:center;margin-bottom:10px;
}
.ctc-comp-row input,
.ctc-comp-row select {
    padding:7px 10px;border:1.5px solid #E5E7EB;border-radius:7px;
    font-size:13px;font-family:inherit;color:#374151;outline:none;
    background:#fff;transition:border-color .15s;
}
.ctc-comp-row input:focus,
.ctc-comp-row select:focus { border-color:#2563EB; }

.ctc-comp-del {
    width:30px;height:30px;border-radius:6px;border:1.5px solid #FEE2E2;
    background:#FFF5F5;color:#DC2626;cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:14px;
    transition:.15s;flex-shrink:0;
}
.ctc-comp-del:hover { background:#FEE2E2; }

/* add component link */
.ctc-add-comp {
    display:inline-flex;align-items:center;gap:5px;
    font-size:13px;font-weight:500;color:#2563EB;cursor:pointer;
    background:none;border:none;font-family:inherit;padding:4px 0;
    transition:color .15s;margin-top:2px;
}
.ctc-add-comp:hover { color:#1D4ED8; }

/* ── Action buttons ── */
.ctc-actions {
    display:flex;justify-content:flex-end;gap:10px;
    padding-top:20px;margin-top:8px;border-top:1px solid #E5E7EB;
}
.ctc-cancel-btn {
    padding:9px 28px;background:#fff;color:#374151;
    border:1.5px solid #D1D5DB;border-radius:8px;font-size:13.5px;
    font-weight:500;cursor:pointer;font-family:inherit;transition:.15s;
    text-decoration:none;display:inline-flex;align-items:center;
}
.ctc-cancel-btn:hover { border-color:#6B7280; }
.ctc-save-btn {
    padding:9px 32px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;
    font-family:inherit;transition:background .15s;
}
.ctc-save-btn:hover { background:#1D4ED8; }

/* ── View mode ── */
.ctc-view-field { margin-bottom:18px; }
.ctc-view-label { font-size:12px;color:#9CA3AF;font-weight:500;margin-bottom:4px; }
.ctc-view-val   {
    font-size:13.5px;color:#111827;font-weight:500;
    padding-bottom:6px;border-bottom:1px solid #E5E7EB;min-height:28px;
}
.ctc-view-val.empty { color:#D1D5DB; }

.ctc-view-comp-table { width:100%;border-collapse:collapse;font-size:13px; }
.ctc-view-comp-table th {
    text-align:left;padding:8px 12px;font-weight:600;color:#6B7280;
    font-size:11px;letter-spacing:.4px;background:#F9FAFB;
    border-bottom:1px solid #E5E7EB;
}
.ctc-view-comp-table td {
    padding:10px 12px;border-bottom:1px solid #F9FAFB;color:#374151;
}
.ctc-view-comp-table tr:last-child td { border-bottom:none; }

/* delete confirm */
.ctc-del-modal {
    display:none;position:fixed;inset:0;background:rgba(15,16,32,.45);
    z-index:600;align-items:center;justify-content:center;padding:16px;
    backdrop-filter:blur(2px);
}
.ctc-del-modal.open { display:flex; }
.ctc-del-box {
    background:#fff;border-radius:14px;max-width:380px;width:100%;
    padding:28px;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.2);
    animation:ctcPop .2s ease;
}
@keyframes ctcPop{ from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)} }

/* toast */
.ctc-toast {
    position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
    background:#111827;color:#fff;padding:11px 20px;border-radius:10px;
    font-size:13px;font-weight:500;z-index:999;display:flex;align-items:center;
    gap:8px;box-shadow:0 8px 28px rgba(0,0,0,.2);transition:transform .3s ease;white-space:nowrap;
}
.ctc-toast.show { transform:translateX(-50%) translateY(0); }

/* responsive */
@media(max-width:900px){
    .ctc-layout { grid-template-columns:1fr; }
    .ctc-left   { border-right:none;border-bottom:1px solid #E5E7EB; }
}
@media(max-width:640px){
    .ctc-row.c2,.ctc-row.c3 { grid-template-columns:1fr; }
    .ctc-comp-row { grid-template-columns:1fr 1fr;gap:8px; }
    .ctc-comp-row .ctc-comp-del { grid-column:2;justify-self:end; }
}
</style>

<?php if($save_ok): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ ctcToast('✅','<?= esc($save_msg) ?>'); });</script>
<?php endif; ?>


<!-- new main tab -->



<!-- ════════════════════════════════════════
     CONFIG TAB BAR
════════════════════════════════════════ -->
<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>
<div class="section-card" style="padding:0;overflow:hidden">
    <div class="cfg-tabs">
        <?php $cfg_tabs=['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'];
        foreach($cfg_tabs as $k=>$l): ?>
        <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k==='Payroll'?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
    <!-- ════════════════════════════════════════
        MAIN CARD
    ════════════════════════════════════════ -->
    <div  style="padding:0;overflow:hidden">
        <!-- top bar: breadcrumb + col labels -->
        <div style="padding:14px 20px;border-bottom:1px solid #E5E7EB">
            <div class="ctc-bc">  
                <a href="configuration#Payroll">Payroll</a>
                <span class="sep">›</span>
                <span class="cur">CTC Templates</span>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:280px 1fr;border-bottom:1px solid #F3F4F6">
            <div style="padding:10px 16px;font-size:12px;color:#6B7280;font-weight:600;border-right:1px solid #E5E7EB">List of CTC Templates</div>
            <div style="padding:10px 16px;font-size:12px;color:#6B7280;font-weight:600">Templates Details</div>
        </div>

        <!-- Two-column body -->
        <div class="ctc-layout">

            <!-- ════════════ LEFT ════════════ -->
            <div class="ctc-left">
                <?php foreach($templates as $t): ?>
                <a href="?id=<?= $t['id'] ?>&mode=view"
                class="ctc-tpl-item <?= $active_id===$t['id']&&$mode!=='add'?'active':'' ?>">
                    <span class="ctc-tpl-name"><?= esc($t['name']) ?></span>
                    <span class="ctc-tpl-arrow"><?= $active_id===$t['id']&&$mode!=='add'?'↑':'↓' ?></span>
                </a>
                <?php endforeach; ?>

                <button class="ctc-add-tpl-btn" onclick="window.location='?mode=add'">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Template
                </button>
            </div>

            <!-- ════════════ RIGHT ════════════ -->
            <div class="ctc-right">

            <?php /* ══ ADD TEMPLATE ══ */ if($mode==='add'): ?>

                <div class="ctc-right-title">ADD CTC TEMPLATE</div>

                <form method="POST" id="addCtcForm" novalidate>
                <input type="hidden" name="_action" value="add_template">

                <!-- Template Name -->
                <div class="ctc-row c1">
                    <div class="ctc-fg">
                        <label><span class="req">* </span>Template Name</label>
                        <input type="text" name="tpl_name" placeholder="Template Name" required id="tplNameInput">
                    </div>
                </div>

                <!-- Checkboxes -->
                <div class="ctc-checks">
                    <!-- PT -->
                    <div class="ctc-pt-wrap">
                        <label class="ctc-check-item">
                            <input type="checkbox" name="pt_applicable" id="ptChk" onchange="togglePT()">
                            Profession Tax ( PT ) State
                        </label>
                        <select name="pt_state" id="ptStateSelect" style="display:none">
                            <option value="">-- Select State --</option>
                            <?php foreach($pt_states as $s): ?>
                            <option><?= esc($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- PF -->
                    <label class="ctc-check-item">
                        <input type="checkbox" name="pf_applicable" id="pfChk">
                        Is Provident Fund (PF) Applicable
                    </label>

                    <!-- ESI -->
                    <label class="ctc-check-item">
                        <input type="checkbox" name="esi_applicable" id="esiChk">
                        Is Employee State Insurance (ESI) Applicable
                    </label>
                </div>

                <!-- Remarks -->
                <div class="ctc-row c1" style="margin-bottom:28px">
                    <div class="ctc-fg">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="1" placeholder="Remarks"></textarea>
                    </div>
                </div>

                <!-- Salary Components -->
                <div class="ctc-sc-title">SALARY COMPONENTS</div>

                <!-- EARNINGS -->
                <div class="ctc-sc-block">
                    <div class="ctc-sc-head">
                        <div class="ctc-sc-head-title">EARNINGS</div>
                        <div class="ctc-sc-head-sub">Include earning related salary components under this template.</div>
                    </div>
                    <div class="ctc-sc-body">
                        <div id="earningsRows"></div>
                        <button type="button" class="ctc-add-comp" onclick="addCompRow('earningsRows','earnings')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Earnings
                        </button>
                    </div>
                </div>

                <!-- DEDUCTIONS -->
                <div class="ctc-sc-block">
                    <div class="ctc-sc-head">
                        <div class="ctc-sc-head-title">DEDUCTIONS</div>
                        <div class="ctc-sc-head-sub">Include deduction related salary components under this template.</div>
                    </div>
                    <div class="ctc-sc-body">
                        <div id="deductionsRows"></div>
                        <button type="button" class="ctc-add-comp" onclick="addCompRow('deductionsRows','deductions')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Deductions
                        </button>
                    </div>
                </div>

                <!-- EMPLOYER CONTRIBUTION -->
                <div class="ctc-sc-block">
                    <div class="ctc-sc-head">
                        <div class="ctc-sc-head-title">EMPLOYER CONTRIBUTION</div>
                        <div class="ctc-sc-head-sub">Include employer contribution related salary components under this template.</div>
                    </div>
                    <div class="ctc-sc-body">
                        <div id="employerRows"></div>
                        <button type="button" class="ctc-add-comp" onclick="addCompRow('employerRows','employer')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Employer Contribution
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="ctc-actions">
                    <a href="?id=<?= $active_id ?>&mode=view" class="ctc-cancel-btn">Cancel</a>
                    <button type="submit" class="ctc-save-btn" onclick="return validateCtcForm()">Add</button>
                </div>
                </form>

            <?php /* ══ EDIT TEMPLATE ══ */ elseif($mode==='edit' && $active_tpl): ?>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
                    <div class="ctc-right-title" style="margin-bottom:0">EDIT CTC TEMPLATE</div>
                    <button class="btn" style="color:#DC2626;border-color:#FEE2E2;font-size:12.5px"
                        onclick="document.getElementById('ctcDelModal').classList.add('open')">
                        Delete Template
                    </button>
                </div>

                <form method="POST" id="editCtcForm" novalidate>
                <input type="hidden" name="_action" value="save_template">
                <input type="hidden" name="tpl_id" value="<?= (int)$active_tpl['id'] ?>">

                <div class="ctc-row c1">
                    <div class="ctc-fg">
                        <label><span class="req">* </span>Template Name</label>
                        <input type="text" name="tpl_name" value="<?= esc($active_tpl['name']) ?>" required id="tplNameInput">
                    </div>
                </div>

                <div class="ctc-checks">
                    <div class="ctc-pt-wrap">
                        <label class="ctc-check-item">
                            <input type="checkbox" name="pt_applicable" id="ptChk" onchange="togglePT()"
                                <?= $active_tpl['pt_state']?'checked':'' ?>>
                            Profession Tax ( PT ) State
                        </label>
                        <select name="pt_state" id="ptStateSelect"
                            style="<?= $active_tpl['pt_state']?'display:inline-block':'display:none' ?>">
                            <?php foreach($pt_states as $s): ?>
                            <option <?= $active_tpl['pt_state']===$s?'selected':'' ?>><?= esc($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="ctc-check-item">
                        <input type="checkbox" name="pf_applicable" <?= $active_tpl['pf']?'checked':'' ?>>
                        Is Provident Fund (PF) Applicable
                    </label>
                    <label class="ctc-check-item">
                        <input type="checkbox" name="esi_applicable" <?= $active_tpl['esi']?'checked':'' ?>>
                        Is Employee State Insurance (ESI) Applicable
                    </label>
                </div>

                <div class="ctc-row c1" style="margin-bottom:28px">
                    <div class="ctc-fg">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="1"><?= esc($active_tpl['remarks']) ?></textarea>
                    </div>
                </div>

                <div class="ctc-sc-title">SALARY COMPONENTS</div>

                <!-- EARNINGS -->
                <div class="ctc-sc-block">
                    <div class="ctc-sc-head">
                        <div class="ctc-sc-head-title">EARNINGS</div>
                        <div class="ctc-sc-head-sub">Include earning related salary components under this template.</div>
                    </div>
                    <div class="ctc-sc-body">
                        <div id="earningsRows">
                        <?php foreach($active_tpl['earnings'] as $c): ?>
                        <?= compRowHTML('earnings',$c) ?>
                        <?php endforeach; ?>
                        </div>
                        <button type="button" class="ctc-add-comp" onclick="addCompRow('earningsRows','earnings')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Earnings
                        </button>
                    </div>
                </div>

                <!-- DEDUCTIONS -->
                <div class="ctc-sc-block">
                    <div class="ctc-sc-head">
                        <div class="ctc-sc-head-title">DEDUCTIONS</div>
                        <div class="ctc-sc-head-sub">Include deduction related salary components under this template.</div>
                    </div>
                    <div class="ctc-sc-body">
                        <div id="deductionsRows">
                        <?php foreach($active_tpl['deductions'] as $c): ?>
                        <?= compRowHTML('deductions',$c) ?>
                        <?php endforeach; ?>
                        </div>
                        <button type="button" class="ctc-add-comp" onclick="addCompRow('deductionsRows','deductions')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Deductions
                        </button>
                    </div>
                </div>

                <!-- EMPLOYER -->
                <div class="ctc-sc-block">
                    <div class="ctc-sc-head">
                        <div class="ctc-sc-head-title">EMPLOYER CONTRIBUTION</div>
                        <div class="ctc-sc-head-sub">Include employer contribution related salary components under this template.</div>
                    </div>
                    <div class="ctc-sc-body">
                        <div id="employerRows">
                        <?php foreach($active_tpl['employer'] as $c): ?>
                        <?= compRowHTML('employer',$c) ?>
                        <?php endforeach; ?>
                        </div>
                        <button type="button" class="ctc-add-comp" onclick="addCompRow('employerRows','employer')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Employer Contribution
                        </button>
                    </div>
                </div>

                <div class="ctc-actions">
                    <a href="?id=<?= $active_id ?>&mode=view" class="ctc-cancel-btn">Cancel</a>
                    <button type="submit" class="ctc-save-btn" onclick="return validateCtcForm()">Save</button>
                </div>
                </form>

            <?php /* ══ VIEW TEMPLATE ══ */ elseif($active_tpl): ?>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
                    <div class="ctc-right-title" style="margin-bottom:0"><?= esc(strtoupper($active_tpl['name'])) ?> — CTC TEMPLATE</div>
                    <div style="display:flex;gap:8px">
                        <a href="?id=<?= $active_id ?>&mode=edit" class="btn">Edit Template</a>
                        <button class="btn" style="color:#DC2626;border-color:#FEE2E2;font-size:12.5px"
                            onclick="document.getElementById('ctcDelModal').classList.add('open')">Delete</button>
                    </div>
                </div>

                <!-- View fields -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:20px;margin-bottom:24px">
                    <div class="ctc-view-field">
                        <div class="ctc-view-label">Template Name</div>
                        <div class="ctc-view-val"><?= esc($active_tpl['name']) ?></div>
                    </div>
                    <div class="ctc-view-field">
                        <div class="ctc-view-label">Profession Tax (PT) State</div>
                        <div class="ctc-view-val <?= $active_tpl['pt_state']?'':'empty' ?>"><?= $active_tpl['pt_state']?esc($active_tpl['pt_state']):'—' ?></div>
                    </div>
                    <div class="ctc-view-field">
                        <div class="ctc-view-label">PF Applicable</div>
                        <div class="ctc-view-val">
                            <span style="background:<?= $active_tpl['pf']?'#D1FAE5':'#FEE2E2' ?>;color:<?= $active_tpl['pf']?'#065F46':'#991B1B' ?>;display:inline-flex;align-items:center;border-radius:20px;font-size:11.5px;font-weight:600;padding:3px 10px">
                                <?= $active_tpl['pf']?'✓ Yes':'✕ No' ?>
                            </span>
                        </div>
                    </div>
                    <div class="ctc-view-field">
                        <div class="ctc-view-label">ESI Applicable</div>
                        <div class="ctc-view-val">
                            <span style="background:<?= $active_tpl['esi']?'#D1FAE5':'#FEE2E2' ?>;color:<?= $active_tpl['esi']?'#065F46':'#991B1B' ?>;display:inline-flex;align-items:center;border-radius:20px;font-size:11.5px;font-weight:600;padding:3px 10px">
                                <?= $active_tpl['esi']?'✓ Yes':'✕ No' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php if($active_tpl['remarks']): ?>
                <div class="ctc-view-field" style="margin-bottom:24px">
                    <div class="ctc-view-label">Remarks</div>
                    <div class="ctc-view-val"><?= esc($active_tpl['remarks']) ?></div>
                </div>
                <?php endif; ?>

                <!-- Salary components view -->
                <div class="ctc-sc-title">SALARY COMPONENTS</div>

                <?php foreach(['earnings'=>['EARNINGS','earning'],'deductions'=>['DEDUCTIONS','deduction'],'employer'=>['EMPLOYER CONTRIBUTION','employer contribution']] as $skey=>[$stitle,$ssub]): ?>
                <div class="ctc-sc-block">
                    <div class="ctc-sc-head">
                        <div class="ctc-sc-head-title"><?= $stitle ?></div>
                        <div class="ctc-sc-head-sub">Include <?= $ssub ?> related salary components under this template.</div>
                    </div>
                    <div class="ctc-sc-body">
                        <?php if(empty($active_tpl[$skey])): ?>
                        <p style="font-size:12.5px;color:#9CA3AF">No components added.</p>
                        <?php else: ?>
                        <table class="ctc-view-comp-table">
                            <thead><tr><th>#</th><th>COMPONENT NAME</th><th>CALCULATION TYPE</th><th>VALUE</th><th>UNIT</th></tr></thead>
                            <tbody>
                            <?php foreach($active_tpl[$skey] as $ci=>$c): ?>
                            <tr>
                                <td style="color:#9CA3AF"><?= $ci+1 ?></td>
                                <td style="font-weight:500;color:#111827"><?= esc($c['name']) ?></td>
                                <td style="color:#6B7280"><?= esc($c['calc']) ?></td>
                                <td style="font-weight:600"><?= esc($c['value']) ?></td>
                                <td style="color:#6B7280"><?= esc($c['unit']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:center;height:300px;color:#9CA3AF;font-size:13.5px">
                    Select a template from the list or click Add Template.
                </div>
            <?php endif; ?>

            </div><!-- end ctc-right -->
        </div><!-- end ctc-layout -->
    </div><!-- end section-card -->
</div>
<!-- ── Delete Confirm Modal ── -->
<div class="ctc-del-modal" id="ctcDelModal" onclick="if(event.target===this)this.classList.remove('open')">
<div class="ctc-del-box">
    <div style="width:56px;height:56px;background:#FEE2E2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px">🗑</div>
    <h3 style="font-size:16px;font-weight:700;color:#111827;margin-bottom:8px">Delete Template?</h3>
    <p style="font-size:13px;color:#6B7280;line-height:1.6;margin-bottom:20px">
        This will permanently delete the <strong><?= esc($active_tpl['name']??'') ?></strong> template and remove it from all assigned employees. This cannot be undone.
    </p>
    <div style="display:flex;gap:8px;justify-content:center">
        <button class="ctc-cancel-btn" onclick="document.getElementById('ctcDelModal').classList.remove('open')" style="min-width:100px">Cancel</button>
        <form method="POST" style="display:inline">
            <input type="hidden" name="_action" value="delete_template">
            <input type="hidden" name="tpl_id" value="<?= (int)($active_tpl['id']??0) ?>">
            <button type="submit" style="min-width:100px;padding:9px 24px;background:#DC2626;color:#fff;border:none;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer">Delete</button>
        </form>
    </div>
</div>
</div>

<!-- ── Toast ── -->
<div class="ctc-toast" id="ctcToastEl">
    <span id="ctcToastIcon">✅</span><span id="ctcToastMsg">Done!</span>
</div>

<script>
/* ── Toast ── */
function ctcToast(icon, msg) {
    var t=document.getElementById('ctcToastEl');
    document.getElementById('ctcToastIcon').textContent=icon;
    document.getElementById('ctcToastMsg').textContent=msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t=setTimeout(function(){ t.classList.remove('show'); },3200);
}

/* ── PT State toggle ── */
function togglePT() {
    var chk=document.getElementById('ptChk');
    var sel=document.getElementById('ptStateSelect');
    if(!chk||!sel) return;
    sel.style.display=chk.checked?'inline-block':'none';
    if(!chk.checked) sel.value='';
}

/* ── Salary components options ── */
var compOptions = {
    earnings:   ['Basic Salary','House Rent Allowance','Special Allowance','Conveyance Allowance','Medical Allowance','Variable Pay','Performance Bonus','Overtime Pay'],
    deductions: ['Professional Tax','PF Employee','ESI Employee','TDS','Loan Recovery','Advance Recovery'],
    employer:   ['PF Employer','ESI Employer','Gratuity','EDLI'],
};
var calcTypes=['Fixed','Slab','Formula','% of Basic','% of CTC','% of Gross'];
var unitTypes=['₹ Fixed','% of CTC','% of Basic','% of Gross','/month','/day','Balance'];
var rowCounters={earningsRows:0,deductionsRows:0,employerRows:0};

function addCompRow(containerId, compType) {
    var container=document.getElementById(containerId);
    if(!container) return;
    var idx=rowCounters[containerId]++;
    var prefix=containerId+'_'+idx;

    var compOpts=(compOptions[compType]||[]).map(function(o){
        return '<option>'+o+'</option>';
    }).join('');
    var calcOpts=calcTypes.map(function(o){ return '<option>'+o+'</option>'; }).join('');
    var unitOpts=unitTypes.map(function(o){ return '<option>'+o+'</option>'; }).join('');

    var row=document.createElement('div');
    row.className='ctc-comp-row';
    row.setAttribute('data-row-id',prefix);
    row.innerHTML='<select name="'+prefix+'_name" title="Component Name">'+compOpts+'</select>'
        +'<select name="'+prefix+'_calc" title="Calculation Type">'+calcOpts+'</select>'
        +'<input type="text" name="'+prefix+'_value" placeholder="Value" title="Value">'
        +'<select name="'+prefix+'_unit" title="Unit">'+unitOpts+'</select>'
        +'<button type="button" class="ctc-comp-del" onclick="removeCompRow(this)" title="Remove">×</button>';
    container.appendChild(row);
}

function removeCompRow(btn) {
    var row=btn.closest('.ctc-comp-row');
    if(row) row.remove();
}

/* ── Validate form ── */
function validateCtcForm() {
    var name=document.getElementById('tplNameInput');
    if(name&&!name.value.trim()){
        name.style.borderBottomColor='#DC2626';
        ctcToast('⚠','Template Name is required.');
        name.focus();
        return false;
    }
    return true;
}
</script>

<?php
/* ─────────────────────────────────────────
   PHP HELPER: render a component row for edit mode
───────────────────────────────────────── */
function compRowHTML($type, $c) {
    global $salary_components, $calc_types;
    $opts = $salary_components[$type] ?? [];
    $prefix = $type.'_'.($c['id']??rand(100,999));
    $calcOpts = ['Fixed','Slab','Formula','% of Basic','% of CTC','% of Gross'];
    $unitOpts = ['₹ Fixed','% of CTC','% of Basic','% of Gross','/month','/day','Balance'];
    ob_start();
    ?>
    <div class="ctc-comp-row">
        <select name="<?= esc($prefix) ?>_name" title="Component">
            <?php foreach($opts as $o): ?><option <?= $o===$c['name']?'selected':'' ?>><?= esc($o) ?></option><?php endforeach; ?>
        </select>
        <select name="<?= esc($prefix) ?>_calc" title="Calculation">
            <?php foreach($calcOpts as $o): ?><option <?= $o===$c['calc']?'selected':'' ?>><?= esc($o) ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="<?= esc($prefix) ?>_value" value="<?= esc($c['value']) ?>" placeholder="Value">
        <select name="<?= esc($prefix) ?>_unit" title="Unit">
            <?php foreach($unitOpts as $o): ?><option <?= $o===$c['unit']?'selected':'' ?>><?= esc($o) ?></option><?php endforeach; ?>
        </select>
        <button type="button" class="ctc-comp-del" onclick="removeCompRow(this)">×</button>
    </div>
    <?php return ob_get_clean();
}

$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>