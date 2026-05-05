<?php
require_once 'includes/config.php';
$page_title = 'Update CTC';

/* ─────────────────────────────────────────
   DUMMY DATA
───────────────────────────────────────── */
$templates = [
    ['id'=>1,'name'=>'Default'],
    ['id'=>2,'name'=>'Senior Staff'],
    ['id'=>3,'name'=>'Contract'],
    ['id'=>4,'name'=>'Part-Time'],
];

$all_employees = [
    ['id'=>1,  'code'=>'1006','name'=>'Sumi Das',             'ctc_monthly'=>18000, 'template_id'=>1],
    ['id'=>2,  'code'=>'1008','name'=>'Shubhankar Naha',      'ctc_monthly'=>21000, 'template_id'=>1],
    ['id'=>3,  'code'=>'1009','name'=>'Biswajit Dutta',       'ctc_monthly'=>19500, 'template_id'=>1],
    ['id'=>4,  'code'=>'1011','name'=>'Sujata Roy',           'ctc_monthly'=>17000, 'template_id'=>2],
    ['id'=>5,  'code'=>'1013','name'=>'Priyanka Chakraborty', 'ctc_monthly'=>23000, 'template_id'=>2],
    ['id'=>6,  'code'=>'1015','name'=>'Babli Mallik',         'ctc_monthly'=>15500, 'template_id'=>1],
    ['id'=>7,  'code'=>'1017','name'=>'Dibakar Sarkar',       'ctc_monthly'=>20000, 'template_id'=>1],
    ['id'=>8,  'code'=>'1018','name'=>'Lal Bahadur Pradhan',  'ctc_monthly'=>16000, 'template_id'=>1],
    ['id'=>9,  'code'=>'1020','name'=>'Anita Sharma',         'ctc_monthly'=>22000, 'template_id'=>2],
    ['id'=>10, 'code'=>'1022','name'=>'Rahul Verma',          'ctc_monthly'=>28000, 'template_id'=>2],
    ['id'=>11, 'code'=>'1024','name'=>'Kavita Singh',         'ctc_monthly'=>19000, 'template_id'=>1],
    ['id'=>12, 'code'=>'1104','name'=>'Abhijit Kumar Mondal', 'ctc_monthly'=>22000, 'template_id'=>1],
];

/* ── POST: update CTC ── */
$save_ok  = false;
$save_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update_ctc') {
    // TODO: validate & update in DB
    $save_ok  = true;
    $save_msg = 'CTC updated successfully!';
}

/* ── Search result (GET) ── */
$search_q      = trim($_GET['q'] ?? '');
$searched_emps = [];
if ($search_q !== '') {
    $ql = strtolower($search_q);
    foreach ($all_employees as $e) {
        if (str_contains(strtolower($e['name']), $ql) ||
            str_contains(strtolower($e['code']), $ql)  ||
            str_contains(strtolower($e['name'].' - '.$e['code']), $ql)) {
            $searched_emps[] = $e;
        }
    }
}

/* ── Selected employees (checkbox ticked) ── */
$selected_ids = array_map('intval', $_GET['sel'] ?? []);
$table_emps = [];
foreach ($all_employees as $e) {
    if (in_array($e['id'], $selected_ids, true)) $table_emps[] = $e;
}

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
// function fmt_inr($n){ return '₹ ' . number_format($n); }

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ════════════════════════════════════════
   UPDATE CTC PAGE
════════════════════════════════════════ */
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
.uc-bc {
    display:flex;align-items:center;gap:8px;font-size:13.5px;
    font-weight:500;color:#374151;margin-bottom:20px;
}
.uc-bc a      { color:#374151;text-decoration:none; }
.uc-bc a:hover{ color:#2563EB; }
.uc-bc .sep   { color:#D1D5DB;font-size:16px; }
.uc-bc .cur   { font-weight:600;color:#374151; }

/* ── Section title ── */
.uc-title {
    font-size:13.5px;font-weight:700;color:#111827;
    letter-spacing:.4px;text-transform:uppercase;
    margin-bottom:16px;
}

/* ── Search row ── */
.uc-search-row {
    display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap;
}
.uc-search-wrap {
    display:flex;align-items:center;gap:8px;padding:9px 14px;
    border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;
    min-width:280px;flex:1;max-width:420px;transition:border-color .15s;
}
.uc-search-wrap:focus-within { border-color:#2563EB; }
.uc-search-wrap svg {
    width:14px;height:14px;stroke:#9CA3AF;fill:none;
    stroke-width:2;stroke-linecap:round;flex-shrink:0;
}
.uc-search-wrap input {
    border:none;outline:none;font-size:13.5px;font-family:inherit;
    color:#374151;background:transparent;width:100%;
}
.uc-filter-btn {
    display:flex;align-items:center;gap:6px;padding:9px 16px;
    border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;
    font-size:13.5px;font-weight:500;color:#374151;cursor:pointer;
    font-family:inherit;transition:border-color .15s;white-space:nowrap;
}
.uc-filter-btn:hover { border-color:#2563EB;color:#2563EB; }
.uc-filter-btn svg {
    width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;
}
.uc-get-btn {
    padding:9px 22px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;
    font-family:inherit;transition:background .15s;white-space:nowrap;
}
.uc-get-btn:hover { background:#1D4ED8; }

/* ── Note ── */
.uc-note {
    font-size:13px;color:#6B7280;margin-bottom:14px;
    line-height:1.5;
}
.uc-note span { font-weight:500; }

/* ── Search results dropdown ── */
.uc-results {
    border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;
    background:#fff;margin-bottom:14px;max-height:220px;overflow-y:auto;
    box-shadow:0 4px 16px rgba(0,0,0,.06);
}
.uc-result-item {
    display:flex;align-items:center;gap:10px;padding:10px 14px;
    border-bottom:1px solid #F3F4F6;cursor:pointer;transition:background .15s;
    font-size:13.5px;color:#374151;
}
.uc-result-item:last-child { border-bottom:none; }
.uc-result-item:hover { background:#F3F4F6; }
.uc-result-item.selected { background:#EFF6FF; }
.uc-result-item input[type=checkbox] {
    width:16px;height:16px;accent-color:#2563EB;cursor:pointer;flex-shrink:0;
}

/* ── Selected employee chip ── */
.uc-selected-chips {
    display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;
}
.uc-chip {
    display:flex;align-items:center;gap:8px;
    padding:8px 12px;background:#EFF6FF;border:1px solid #BFDBFE;
    border-radius:8px;font-size:13px;color:#1D4ED8;font-weight:500;max-width:100%;
}
.uc-chip input[type=checkbox] { width:15px;height:15px;accent-color:#2563EB;cursor:pointer;flex-shrink:0; }
.uc-chip-name { word-break:break-all; }

/* ── Table ── */
.uc-table-wrap {
    border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;
    background:#fff;margin-bottom:20px;overflow-x:auto;
}
.uc-table { width:100%;border-collapse:collapse;font-size:13.5px;min-width:640px; }
.uc-table thead tr { background:#F3F4F6; }
.uc-table th {
    padding:11px 16px;text-align:left;font-weight:600;color:#374151;
    font-size:13px;border-bottom:1px solid #E5E7EB;white-space:nowrap;
}
.uc-table th:first-child { width:44px;text-align:center; }
.uc-table td {
    padding:12px 16px;border-bottom:1px solid #F3F4F6;
    color:#374151;vertical-align:middle;
}
.uc-table tr:last-child td { border-bottom:none; }
.uc-table tbody tr:hover td { background:#FAFBFF; }
.uc-table input[type=checkbox] { width:15px;height:15px;accent-color:#2563EB;cursor:pointer; }

/* template dropdown in table */
.uc-tpl-sel {
    padding:7px 10px;border:1.5px solid #E5E7EB;border-radius:7px;
    font-size:13px;font-family:inherit;color:#374151;outline:none;
    background:#fff;width:100%;min-width:160px;transition:border-color .15s;cursor:pointer;
}
.uc-tpl-sel:focus { border-color:#2563EB; }

/* new CTC input */
.uc-new-ctc-wrap {
    display:flex;align-items:center;gap:4px;
}
.uc-new-ctc-wrap span { color:#374151;font-size:14px;font-weight:500;flex-shrink:0; }
.uc-new-ctc {
    border:none;border-bottom:1.5px solid #D1D5DB;border-radius:0;
    padding:5px 4px;font-size:13.5px;font-family:inherit;
    color:#111827;outline:none;background:transparent;
    width:100%;min-width:130px;transition:border-color .15s;
}
.uc-new-ctc:focus { border-bottom-color:#2563EB; }
.uc-new-ctc::placeholder { color:#C4C9D4;font-size:13px; }

/* current CTC */
.uc-curr-ctc {
    font-size:13.5px;font-weight:500;color:#374151;white-space:nowrap;
}

/* empty table state */
.uc-table-empty {
    padding:40px 24px;text-align:center;
    font-size:13.5px;color:#9CA3AF;
}

/* ── Action buttons ── */
.uc-actions {
    display:flex;justify-content:flex-end;gap:10px;
    padding-top:4px;
}
.uc-cancel-btn {
    padding:9px 28px;background:#fff;color:#374151;
    border:1.5px solid #D1D5DB;border-radius:8px;
    font-size:13.5px;font-weight:500;cursor:pointer;
    font-family:inherit;transition:.15s;text-decoration:none;
    display:inline-flex;align-items:center;
}
.uc-cancel-btn:hover { border-color:#6B7280; }
.uc-update-btn {
    padding:9px 32px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:13.5px;font-weight:600;
    cursor:pointer;font-family:inherit;transition:background .15s;
}
.uc-update-btn:hover { background:#1D4ED8; }

/* filter dropdown */
.uc-filter-popup {
    display:none;position:absolute;top:calc(100% + 6px);left:0;
    background:#fff;border:1px solid #E5E7EB;border-radius:10px;
    box-shadow:0 8px 24px rgba(0,0,0,.1);z-index:200;padding:14px 16px;min-width:220px;
}
.uc-filter-popup.open { display:block; }
.uc-filter-label { font-size:11px;font-weight:700;color:#9CA3AF;letter-spacing:.4px;text-transform:uppercase;margin-bottom:8px; }
.uc-filter-sel {
    width:100%;padding:8px 10px;border:1.5px solid #E5E7EB;border-radius:7px;
    font-size:13px;font-family:inherit;outline:none;color:#374151;
    transition:border-color .15s;
}
.uc-filter-sel:focus { border-color:#2563EB; }
.uc-filter-actions { display:flex;justify-content:flex-end;gap:8px;margin-top:10px; }

/* toast */
.uc-toast {
    position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
    background:#111827;color:#fff;padding:11px 20px;border-radius:10px;
    font-size:13px;font-weight:500;z-index:999;display:flex;align-items:center;
    gap:8px;box-shadow:0 8px 28px rgba(0,0,0,.2);transition:transform .3s ease;white-space:nowrap;
}
.uc-toast.show { transform:translateX(-50%) translateY(0); }

@media(max-width:680px){
    .uc-search-row { flex-direction:column;align-items:stretch; }
    .uc-search-wrap { max-width:100%; }
}
</style>

<?php if($save_ok): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ ucToast('✅','<?= esc($save_msg) ?>'); });</script>
<?php endif; ?>

<!-- ════════════════════════════════════════
     CONFIG TAB BAR
════════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden;margin-bottom:0">
    <div class="cfg-tabs">
        <?php $cfg_tabs=['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'];
        foreach($cfg_tabs as $k=>$l): ?>
        <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k==='Payroll'?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Breadcrumb -->
    <div style="padding:14px 24px;border-bottom:1px solid #E5E7EB">
        <div class="uc-bc">
            <a href="configuration#Payroll">Payroll</a>
            <span class="sep">›</span>
            <span class="cur">Update CTC</span>
        </div>

        <!-- Page title -->
        <div class="uc-title">UPDATE CTC</div>

        <!-- Search + Filter + Get Details -->
        <form method="GET" id="ucSearchForm" autocomplete="off">
        <div class="uc-search-row">
            <div class="uc-search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" id="ucSearchInput"
                    value="<?= esc($search_q) ?>"
                    placeholder="Search by employee name or #code"
                    oninput="liveSearch(this.value)">
            </div>

            <!-- Filter button -->
            <div style="position:relative">
                <button type="button" class="uc-filter-btn" onclick="toggleFilter(event)">
                    <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filter
                </button>
                <div class="uc-filter-popup" id="ucFilterPopup">
                    <div class="uc-filter-label">DEPARTMENT</div>
                    <select class="uc-filter-sel" id="filterDept">
                        <option value="">All Departments</option>
                        <option>Medical</option><option>Nursing</option>
                        <option>Administration</option><option>Lab Tech</option>
                        <option>Accounts</option><option>Reception</option>
                    </select>
                    <div class="uc-filter-label" style="margin-top:10px">CTC TEMPLATE</div>
                    <select class="uc-filter-sel" id="filterTpl">
                        <option value="">All Templates</option>
                        <?php foreach($templates as $t): ?>
                        <option><?= esc($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="uc-filter-actions">
                        <button type="button" class="btn" onclick="clearFilter()" style="font-size:12px">Clear</button>
                        <button type="button" class="btn btn-primary" onclick="applyFilter()" style="font-size:12px">Apply</button>
                    </div>
                </div>
            </div>

            <button type="submit" class="uc-get-btn">Get Details</button>
        </div>

        <!-- Live-search results list -->
        <div id="ucResultsList" style="display:none;max-width:420px;margin-bottom:6px">
            <div class="uc-results" id="ucResultsInner"></div>
        </div>

        <!-- Hidden: track selected IDs via checkboxes -->
        <div id="ucSelectedInputs">
            <?php foreach($selected_ids as $sid): ?>
            <input type="hidden" name="sel[]" value="<?= (int)$sid ?>">
            <?php endforeach; ?>
        </div>
        </form>

        <!-- Note -->
        <p class="uc-note">
            Note : To check the assigned CTC of an employee search using the <span>employee name or code</span>
        </p>

        <!-- Selected employee chips -->
        <?php if(!empty($searched_emps) || !empty($table_emps)): ?>
        <div class="uc-selected-chips" id="ucChips">
            <?php
            // show searched employees as chips (matching screenshot)
            $chip_emps = !empty($searched_emps) ? $searched_emps : $table_emps;
            foreach($chip_emps as $e):
                $checked = in_array($e['id'], $selected_ids, true);
            ?>
            <div class="uc-chip" id="chip-<?= $e['id'] ?>">
                <input type="checkbox"
                    <?= $checked ? 'checked' : '' ?>
                    onchange="toggleChip(<?= $e['id'] ?>, this)">
                <span class="uc-chip-name">
                    <?= esc($e['name']) ?> -<br><?= esc($e['code']) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- CTC Update Table -->
        <form method="POST" id="ucUpdateForm" novalidate>
        <input type="hidden" name="_action" value="update_ctc">
        <?php foreach($selected_ids as $sid): ?>
        <input type="hidden" name="sel_ids[]" value="<?= (int)$sid ?>">
        <?php endforeach; ?>

        <div class="uc-table-wrap">
        <table class="uc-table" id="ucTable">
            <thead>
                <tr>
                    <th>
                        <input type="checkbox" id="ucSelectAll" onchange="toggleAllRows(this)"
                            style="width:15px;height:15px;accent-color:#2563EB;cursor:pointer">
                    </th>
                    <th>Employee</th>
                    <th>CTC Template Assigned</th>
                    <th>Current CTC</th>
                    <th>New CTC</th>
                </tr>
            </thead>
            <tbody id="ucTableBody">
            <?php if(empty($table_emps) && empty($searched_emps)): ?>
            <tr>
                <td colspan="5" class="uc-table-empty">
                    Search for an employee and click <strong>Get Details</strong> to load CTC data.
                </td>
            </tr>
            <?php else:
                $display_emps = !empty($table_emps) ? $table_emps : $searched_emps;
                foreach($display_emps as $e):
            ?>
            <tr id="row-<?= $e['id'] ?>">
                <td style="text-align:center">
                    <input type="checkbox" name="row_sel[]" value="<?= $e['id'] ?>"
                        class="uc-row-chk"
                        style="width:15px;height:15px;accent-color:#2563EB;cursor:pointer">
                </td>
                <td style="font-weight:500;color:#111827">
                    <?= esc($e['name']) ?> - #<?= esc($e['code']) ?>
                </td>
                <td>
                    <select name="tpl_<?= $e['id'] ?>" class="uc-tpl-sel">
                        <option value=""></option>
                        <?php foreach($templates as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id']===$e['template_id']?'selected':'' ?>>
                            <?= esc($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <span class="uc-curr-ctc">
                        <?= fmt_inr($e['ctc_monthly']) ?>
                        (<?= fmt_inr($e['ctc_monthly'] * 12) ?> yearly)
                    </span>
                </td>
                <td>
                    <div class="uc-new-ctc-wrap">
                        <span>₹</span>
                        <input type="number" name="new_ctc_<?= $e['id'] ?>" class="uc-new-ctc"
                            placeholder="Add the new CTC here" min="0">
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Actions -->
        <div class="uc-actions">
            <a href="configuration#Payroll" class="uc-cancel-btn">Cancel</a>
            <button type="submit" class="uc-update-btn"
                onclick="return validateUcForm()">Update</button>
        </div>

        </form>
    </div>
</div>

<!-- ── Toast ── -->
<div class="uc-toast" id="ucToastEl">
    <span id="ucToastIcon">✅</span><span id="ucToastMsg">Done!</span>
</div>

<!-- ════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════ -->
<script>
var allEmployees = <?= json_encode(array_map(function($e){
    return ['id'=>$e['id'],'code'=>$e['code'],'name'=>$e['name'],
            'ctc_monthly'=>$e['ctc_monthly'],'template_id'=>$e['template_id']];
}, $all_employees)) ?>;

var selectedIds = <?= json_encode($selected_ids) ?>;

var templates = <?= json_encode($templates) ?>;

/* ── Toast ── */
function ucToast(icon, msg) {
    var t=document.getElementById('ucToastEl');
    document.getElementById('ucToastIcon').textContent=icon;
    document.getElementById('ucToastMsg').textContent=msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t=setTimeout(function(){ t.classList.remove('show'); },3200);
}

/* ── Live search ── */
function liveSearch(q) {
    q = q.trim().toLowerCase();
    var resultsList = document.getElementById('ucResultsList');
    var inner = document.getElementById('ucResultsInner');
    if (!q) { resultsList.style.display='none'; return; }

    var matches = allEmployees.filter(function(e){
        return e.name.toLowerCase().includes(q) || e.code.toLowerCase().includes(q);
    }).slice(0, 8);

    if (matches.length === 0) { resultsList.style.display='none'; return; }

    inner.innerHTML = matches.map(function(e){
        var checked = selectedIds.includes(e.id);
        return '<div class="uc-result-item '+(checked?'selected':'')+'" id="res-'+e.id+'" onclick="toggleResult('+e.id+')">'
            + '<input type="checkbox" '+(checked?'checked':'')+' onclick="event.stopPropagation();toggleResult('+e.id+')">'
            + '<span>' + e.name + ' - #' + e.code + '</span>'
            + '</div>';
    }).join('');
    resultsList.style.display = 'block';
}

function toggleResult(empId) {
    var idx = selectedIds.indexOf(empId);
    if (idx > -1) {
        selectedIds.splice(idx, 1);
    } else {
        selectedIds.push(empId);
    }
    // update checkbox state in result list
    var resItem = document.getElementById('res-'+empId);
    if (resItem) {
        resItem.classList.toggle('selected', selectedIds.includes(empId));
        var chk = resItem.querySelector('input[type=checkbox]');
        if(chk) chk.checked = selectedIds.includes(empId);
    }
    // re-render chips
    renderChips();
    // re-render table rows
    renderTableRows();
}

/* ── Chip toggle ── */
function toggleChip(empId, chk) {
    if (!chk.checked) {
        var idx = selectedIds.indexOf(empId);
        if (idx > -1) selectedIds.splice(idx, 1);
    } else {
        if (!selectedIds.includes(empId)) selectedIds.push(empId);
    }
    renderChips();
    renderTableRows();
}

function renderChips() {
    var chipsDiv = document.getElementById('ucChips');
    if (!chipsDiv) return;
    var emps = allEmployees.filter(function(e){ return selectedIds.includes(e.id); });
    if (emps.length === 0) { chipsDiv.innerHTML=''; return; }
    chipsDiv.innerHTML = emps.map(function(e){
        return '<div class="uc-chip" id="chip-'+e.id+'">'
            + '<input type="checkbox" checked onchange="toggleChip('+e.id+',this)">'
            + '<span class="uc-chip-name">'+e.name+' -<br>'+e.code+'</span>'
            + '</div>';
    }).join('');
}

/* ── Render table rows dynamically ── */
function renderTableRows() {
    var tbody = document.getElementById('ucTableBody');
    if (!tbody) return;
    var emps = allEmployees.filter(function(e){ return selectedIds.includes(e.id); });
    if (emps.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="uc-table-empty">Search for an employee and click <strong>Get Details</strong> to load CTC data.</td></tr>';
        return;
    }
    var tplOpts = templates.map(function(t){
        return '<option value="'+t.id+'">'+t.name+'</option>';
    }).join('');

    tbody.innerHTML = emps.map(function(e){
        var monthly = e.ctc_monthly;
        var yearly  = monthly * 12;
        var fmtN = function(n){ return '₹ ' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g,','); };
        return '<tr id="row-'+e.id+'">'
            + '<td style="text-align:center"><input type="checkbox" name="row_sel[]" value="'+e.id+'" class="uc-row-chk" style="width:15px;height:15px;accent-color:#2563EB;cursor:pointer"></td>'
            + '<td style="font-weight:500;color:#111827">'+e.name+' - #'+e.code+'</td>'
            + '<td><select name="tpl_'+e.id+'" class="uc-tpl-sel"><option value=""></option>'+tplOpts+'</select></td>'
            + '<td><span class="uc-curr-ctc">'+fmtN(monthly)+' ('+fmtN(yearly)+' yearly)</span></td>'
            + '<td><div class="uc-new-ctc-wrap"><span>₹</span><input type="number" name="new_ctc_'+e.id+'" class="uc-new-ctc" placeholder="Add the new CTC here" min="0"></div></td>'
            + '</tr>';
    }).join('');

    // pre-select template
    emps.forEach(function(e){
        var sel = tbody.querySelector('select[name="tpl_'+e.id+'"]');
        if(sel) sel.value = e.template_id;
    });
}

/* ── Select all rows ── */
function toggleAllRows(masterChk) {
    document.querySelectorAll('.uc-row-chk').forEach(function(c){ c.checked=masterChk.checked; });
}

/* ── Filter popup ── */
function toggleFilter(e) {
    e.stopPropagation();
    document.getElementById('ucFilterPopup').classList.toggle('open');
}
function applyFilter() {
    document.getElementById('ucFilterPopup').classList.remove('open');
    ucToast('🔍','Filter applied!');
}
function clearFilter() {
    document.getElementById('filterDept').value='';
    document.getElementById('filterTpl').value='';
    document.getElementById('ucFilterPopup').classList.remove('open');
    ucToast('✓','Filters cleared');
}
document.addEventListener('click',function(e){
    var popup=document.getElementById('ucFilterPopup');
    if(popup&&!popup.closest('.uc-filter-popup')?.contains(e.target)&&!e.target.closest('.uc-filter-btn')){
        popup.classList.remove('open');
    }
});

/* ── Close search results on click outside ── */
document.addEventListener('click',function(e){
    var rl=document.getElementById('ucResultsList');
    if(rl&&!document.getElementById('ucSearchForm')?.contains(e.target)){
        rl.style.display='none';
    }
});

/* ── Form validation ── */
function validateUcForm() {
    var rows = document.querySelectorAll('.uc-row-chk:checked');
    if (rows.length === 0 && selectedIds.length === 0) {
        ucToast('⚠','Please select at least one employee to update.');
        return false;
    }
    var hasNewCtc = false;
    document.querySelectorAll('.uc-new-ctc').forEach(function(inp){
        if(inp.value.trim()) hasNewCtc=true;
    });
    if (!hasNewCtc) {
        ucToast('⚠','Please enter the new CTC for at least one employee.');
        return false;
    }
    return true;
}

/* ── New CTC yearly preview ── */
document.addEventListener('input', function(e) {
    if (!e.target.classList.contains('uc-new-ctc')) return;
    var val = parseFloat(e.target.value);
    if (isNaN(val) || val <= 0) { e.target.title=''; return; }
    var yearly = val * 12;
    e.target.title = '₹ ' + yearly.toLocaleString('en-IN') + ' yearly';
});
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>