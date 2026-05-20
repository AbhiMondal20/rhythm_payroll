<?php
require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Leave Structure';

if (!isset($conn)) {
    die("Database connection variable \$conn not found.");
}

function esc($v){
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/* ─────────────────────────────────────────
   TABLES
─────────────────────────────────────────

CREATE TABLE leave_structures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    structure_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE leave_structure_conditions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    structure_id INT NOT NULL,
    policy_name VARCHAR(255) DEFAULT NULL,
    condition_value VARCHAR(50) DEFAULT '0',
    condition_unit VARCHAR(50) DEFAULT 'Months',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

*/

/* ─────────────────────────────────────────
   TOAST
───────────────────────────────────────── */

$save_ok  = false;
$save_msg = '';

/* ─────────────────────────────────────────
   SAVE / UPDATE
───────────────────────────────────────── */

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $action = $_POST['_action'] ?? '';

    /* SAVE */
    if($action === 'save'){

        $structure_id   = (int)($_POST['structure_id'] ?? 0);
        $structure_name = trim($_POST['structure_name'] ?? '');

        $cond_policy = $_POST['cond_policy'] ?? [];
        $cond_value  = $_POST['cond_value'] ?? [];
        $cond_unit   = $_POST['cond_unit'] ?? [];

        if($structure_name != ''){

            /* UPDATE */
            if($structure_id > 0){

                mysqli_query($conn,"
                    UPDATE leave_structures
                    SET structure_name='".mysqli_real_escape_string($conn,$structure_name)."'
                    WHERE id='$structure_id'
                ");

                mysqli_query($conn,"
                    DELETE FROM leave_structure_conditions
                    WHERE structure_id='$structure_id'
                ");

            }else{

                /* INSERT */
                mysqli_query($conn,"
                    INSERT INTO leave_structures(
                        structure_name
                    )
                    VALUES(
                        '".mysqli_real_escape_string($conn,$structure_name)."'
                    )
                ");

                $structure_id = mysqli_insert_id($conn);
            }

            /* INSERT CONDITIONS */
            foreach($cond_policy as $k=>$policy){

                $policy = mysqli_real_escape_string($conn,$policy);
                $value  = mysqli_real_escape_string($conn,$cond_value[$k] ?? '0');
                $unit   = mysqli_real_escape_string($conn,$cond_unit[$k] ?? 'Months');

                mysqli_query($conn,"
                    INSERT INTO leave_structure_conditions(
                        structure_id,
                        policy_name,
                        condition_value,
                        condition_unit
                    )
                    VALUES(
                        '$structure_id',
                        '$policy',
                        '$value',
                        '$unit'
                    )
                ");
            }

            $save_ok  = true;
            $save_msg = 'Leave structure saved successfully!';
        }
    }

    /* DELETE */
    if($action === 'delete'){

        $structure_id = (int)($_POST['structure_id'] ?? 0);

        mysqli_query($conn,"
            DELETE FROM leave_structure_conditions
            WHERE structure_id='$structure_id'
        ");

        mysqli_query($conn,"
            DELETE FROM leave_structures
            WHERE id='$structure_id'
        ");

        $save_ok  = true;
        $save_msg = 'Leave structure deleted successfully!';
    }
}

/* ─────────────────────────────────────────
   FETCH DATA
───────────────────────────────────────── */

$structures = [];

$q = mysqli_query($conn,"
    SELECT *
    FROM leave_structures
    ORDER BY id ASC
");

while($row = mysqli_fetch_assoc($q)){

    $conditions = [];

    $cq = mysqli_query($conn,"
        SELECT *
        FROM leave_structure_conditions
        WHERE structure_id='".$row['id']."'
    ");

    while($crow = mysqli_fetch_assoc($cq)){

        $conditions[] = [
            'policy' => $crow['policy_name'],
            'value'  => $crow['condition_value'],
            'unit'   => $crow['condition_unit']
        ];
    }

    if(empty($conditions)){

        $conditions[] = [
            'policy' => '',
            'value'  => '0',
            'unit'   => 'Months'
        ];
    }

    $structures[] = [
        'id'         => $row['id'],
        'name'       => $row['structure_name'],
        'conditions' => $conditions
    ];
}

/* ─────────────────────────────────────────
   ACTIVE STRUCTURE
───────────────────────────────────────── */

$active_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : ($structures[0]['id'] ?? 0);

$active_str = null;

foreach($structures as $s){

    if($s['id'] == $active_id){
        $active_str = $s;
        break;
    }
}

if(!$active_str){

    $active_str = [
        'id' => 0,
        'name' => '',
        'conditions' => [
            [
                'policy' => '',
                'value'  => '0',
                'unit'   => 'Months'
            ]
        ]
    ];
}

/* ─────────────────────────────────────────
   POLICIES
───────────────────────────────────────── */

$leave_policies = [
    'Permanent leave policy',
    'Contract leave policy',
    'Probation leave policy',
    'Part-time leave policy',
];

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<!-- YOUR SAME CSS HERE -->

<style>
/* ════════════════════════════════════════
   LEAVE STRUCTURE PAGE
════════════════════════════════════════ */

/* config tab bar */
.cfg-tabs {
    display: flex;
    align-items: center;
    border-bottom: 1px solid #E5E7EB;
    background: #fff;
    overflow-x: auto;
    scrollbar-width: none;
}

.cfg-tabs::-webkit-scrollbar {
    display: none;
}

.cfg-tab {
    padding: 14px 20px;
    font-size: 13.5px;
    font-weight: 500;
    color: #6B7280;
    cursor: pointer;
    border: none;
    background: transparent;
    border-bottom: 2.5px solid transparent;
    white-space: nowrap;
    transition: color .15s, border-color .15s;
    text-decoration: none;
    display: block;
    margin-bottom: -1px;
}

.cfg-tab:hover {
    color: #111827;
}

.cfg-tab.active {
    color: #2563EB;
    border-bottom-color: #2563EB;
    font-weight: 600;
}

/* breadcrumb */
.ls-bc {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 500;
    color: #374151;
}

.ls-bc a {
    color: #374151;
    text-decoration: none;
}

.ls-bc a:hover {
    color: #2563EB;
}

.ls-bc .sep {
    color: #D1D5DB;
    font-size: 16px;
}

.ls-bc .cur {
    font-weight: 600;
    color: #374151;
}

/* subtitle */
.ls-subtitle {
    font-size: 13px;
    color: #6B7280;
    margin: 8px 0 0;
}

/* column labels row */
.ls-col-labels {
    display: grid;
    grid-template-columns: 420px 1fr;
    padding: 10px 0 10px;
    border-bottom: 1px solid #E5E7EB;
}

.ls-col-label {
    font-size: 12.5px;
    color: #6B7280;
    font-weight: 500;
}

.ls-col-label:last-child {
    padding-left: 24px;
}

/* main two-column layout */
.ls-layout {
    display: grid;
    grid-template-columns: 420px 1fr;
    align-items: start;
    min-height: 460px;
}

/* ── LEFT LIST ── */
.ls-left {
    border-right: 1px solid #E5E7EB;
}

.ls-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #E5E7EB;
    cursor: pointer;
    transition: background .15s;
    text-decoration: none;
    position: relative;
}

.ls-item:last-child {
    border-bottom: none;
}

.ls-item:hover {
    background: #F9FAFB;
}

.ls-item.active {
    background: #fff;
}

.ls-item-name {
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    transition: color .15s;
}

.ls-item.active .ls-item-name {
    color: #2563EB;
    font-weight: 600;
}

.ls-item-icon {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 1.5px solid #D1D5DB;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: .15s;
}

.ls-item.active .ls-item-icon {
    border-color: #2563EB;
}

.ls-item-icon svg {
    width: 11px;
    height: 11px;
    stroke: #9CA3AF;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: .15s;
}

.ls-item.active .ls-item-icon svg {
    stroke: #2563EB;
}

/* active item shows > arrow, others show ∨ */
.ls-item.active .ls-item-icon svg {
    transform: none;
}

/* ── RIGHT DETAIL ── */
.ls-right {
    padding: 22px 28px 32px;
}

/* section title */
.ls-right-title {
    font-size: 14.5px;
    font-weight: 700;
    color: #111827;
    letter-spacing: .4px;
    text-transform: uppercase;
    margin-bottom: 20px;
}

/* edit link */
.ls-edit-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13.5px;
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

.ls-edit-link:hover {
    color: #1D4ED8;
}

.ls-edit-link svg {
    width: 14px;
    height: 14px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* ── form fields (underline style) ── */
.ls-fg {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 22px;
}

.ls-fg label {
    font-size: 13px;
    color: #374151;
}

.ls-fg label .req {
    color: #DC2626;
    margin-right: 4px;
}

.ls-fg input[type=text],
.ls-fg input[type=number],
.ls-fg select {
    border: none;
    border-bottom: 1.5px solid #D1D5DB;
    border-radius: 0;
    padding: 7px 0;
    font-size: 14px;
    font-family: inherit;
    color: #111827;
    outline: none;
    background: transparent;
    transition: border-color .15s;
    width: 100%;
}

.ls-fg input:focus,
.ls-fg select:focus {
    border-bottom-color: #2563EB;
}

.ls-fg input::placeholder {
    color: #D1D5DB;
}

.ls-fg input[readonly] {
    color: #374151;
    cursor: default;
}

/* View mode: value under underline */
.ls-view-val {
    font-size: 14px;
    color: #111827;
    font-weight: 400;
    padding-bottom: 7px;
    border-bottom: 1.5px solid #D1D5DB;
    min-height: 30px;
}

.ls-view-val.empty {
    color: #D1D5DB;
}

/* ── Conditions section ── */
.ls-cond-title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 14px;
}

.ls-cond-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.ls-cond-label {
    font-size: 14px;
    color: #374151;
    flex-shrink: 0;
}

/* policy dropdown */
.ls-cond-policy {
    display: flex;
    align-items: center;
    gap: 0;
    border-bottom: 1.5px solid #D1D5DB;
    position: relative;
    min-width: 200px;
    transition: border-color .15s;
}

.ls-cond-policy:focus-within {
    border-bottom-color: #2563EB;
}

.ls-cond-policy select {
    border: none;
    outline: none;
    background: transparent;
    font-size: 14px;
    font-family: inherit;
    color: #374151;
    padding: 7px 28px 7px 0;
    flex: 1;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    width: 100%;
}

.ls-cond-policy::after {
    content: '';
    border: none;
    border-right: 1.5px solid #9CA3AF;
    border-bottom: 1.5px solid #9CA3AF;
    width: 7px;
    height: 7px;
    transform: rotate(45deg);
    position: absolute;
    right: 6px;
    top: calc(50% - 6px);
    pointer-events: none;
}

/* number input in condition */
.ls-cond-num {
    border: 1px solid #E5E7EB;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 14px;
    font-family: inherit;
    color: #111827;
    outline: none;
    background: #fff;
    width: 80px;
    text-align: center;
    transition: border-color .15s;
}

.ls-cond-num:focus {
    border-color: #2563EB;
}

/* unit dropdown */
.ls-cond-unit {
    display: flex;
    align-items: center;
    gap: 0;
    border: 1px solid #E5E7EB;
    border-radius: 4px;
    position: relative;
    background: #fff;
    transition: border-color .15s;
}

.ls-cond-unit:focus-within {
    border-color: #2563EB;
}

.ls-cond-unit select {
    border: none;
    outline: none;
    background: transparent;
    font-size: 14px;
    font-family: inherit;
    color: #374151;
    padding: 6px 28px 6px 10px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    min-width: 90px;
}

.ls-cond-unit::after {
    content: '';
    border: none;
    border-right: 1.5px solid #9CA3AF;
    border-bottom: 1.5px solid #9CA3AF;
    width: 7px;
    height: 7px;
    transform: rotate(45deg);
    position: absolute;
    right: 8px;
    top: calc(50% - 6px);
    pointer-events: none;
}

/* + / - circle buttons */
.ls-cond-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: #fff;
    font-size: 18px;
    font-weight: 300;
    line-height: 1;
    transition: .15s;
    flex-shrink: 0;
    padding: 0;
    font-family: inherit;
}

.ls-cond-btn.remove {
    border-color: #DC2626;
    color: #DC2626;
}

.ls-cond-btn.remove:hover {
    background: #FEE2E2;
}

.ls-cond-btn.add {
    border-color: #059669;
    color: #059669;
}

.ls-cond-btn.add:hover {
    background: #D1FAE5;
}

/* view mode condition row */
.ls-view-cond-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    font-size: 14px;
    color: #374151;
    flex-wrap: wrap;
}

.ls-view-cond-val {
    padding: 5px 12px;
    border: 1.5px solid #E5E7EB;
    border-radius: 4px;
    font-size: 14px;
    color: #374151;
    background: #FAFAFA;
}

/* action buttons */
.ls-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    position: absolute;
    bottom: 28px;
    right: 28px;
}

.ls-cancel-btn {
    padding: 9px 24px;
    background: #fff;
    color: #374151;
    border: 1.5px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    transition: .15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.ls-cancel-btn:hover {
    border-color: #374151;
}

.ls-save-btn {
    padding: 9px 28px;
    background: #2563EB;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}

.ls-save-btn:hover {
    background: #1D4ED8;
}

/* toast */
.ls-toast {
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
    box-shadow: 0 8px 28px rgba(0, 0, 0, .2);
    transition: transform .3s ease;
    white-space: nowrap;
}

.ls-toast.show {
    transform: translateX(-50%) translateY(0);
}

/* responsive */
@media(max-width:860px) {

    .ls-layout,
    .ls-col-labels {
        grid-template-columns: 1fr;
    }

    .ls-left {
        border-right: none;
        border-bottom: 1px solid #E5E7EB;
    }

    .ls-right {
        padding: 18px;
    }

    .ls-actions {
        position: static;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid #E5E7EB;
    }
}
</style>

<?php if($save_ok): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    lsToast('✅','<?= esc($save_msg) ?>');
});
</script>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden;">

    <div class="cfg-tabs">

        <?php foreach([
            'AccountInfo'=>'Account Info',
            'Organization'=>'Organization',
            'Payroll'=>'Payroll',
            'Attendance'=>'Attendance',
            'Leave'=>'Leave',
            'Training'=>'Training',
            'Others'=>'Others'
        ] as $k=>$l): ?>

        <a href="configuration#<?= $k ?>"
           class="cfg-tab <?= $k==='Leave'?'active':'' ?>">

            <?= $l ?>

        </a>

        <?php endforeach; ?>

    </div>

    <div style="padding:16px 24px 0">

        <div class="ls-bc">

            <a href="configuration#Leave">Leave</a>

            <span class="sep">›</span>

            <span class="cur">Leave Structure</span>

        </div>

        <p class="ls-subtitle">
            Set a structure of leave policies.
        </p>

    </div>

    <div style="padding:0 24px">

        <div class="ls-col-labels">

            <div class="ls-col-label">List of structures</div>

            <div class="ls-col-label">Policy details</div>

        </div>

    </div>

    <div class="ls-layout" style="position:relative;min-height:500px">

        <!-- LEFT -->
        <div class="ls-left">

            <?php foreach($structures as $s): ?>

            <a href="?id=<?= $s['id'] ?>"
               class="ls-item <?= $active_id==$s['id'] ? 'active':'' ?>">

                <span class="ls-item-name">
                    <?= esc($s['name']) ?>
                </span>

                <span class="ls-item-icon">

                    <?php if($active_id==$s['id']): ?>

                    <svg viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>

                    <?php else: ?>

                    <svg viewBox="0 0 24 24">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>

                    <?php endif; ?>

                </span>

            </a>

            <?php endforeach; ?>

        </div>

        <!-- RIGHT -->
        <div class="ls-right">

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">

                <div class="ls-right-title">

                    <?= esc(strtoupper($active_str['name'])) ?>

                </div>

            </div>

            <form method="POST" id="lsForm">

                <input type="hidden" name="_action" value="save">

                <input type="hidden"
                       name="structure_id"
                       value="<?= (int)$active_str['id'] ?>">

                <div class="ls-fg">

                    <label>

                        <span class="req">*</span>

                        Structure Name

                    </label>

                    <input type="text"
                           name="structure_name"
                           id="structureName"
                           value="<?= esc($active_str['name']) ?>"
                           required>

                </div>

                <div class="ls-cond-title">Conditions</div>

                <div id="conditionsWrap">

                    <?php foreach($active_str['conditions'] as $ci=>$cond): ?>

                    <div class="ls-cond-row">

                        <span class="ls-cond-label">Apply</span>

                        <div class="ls-cond-policy">

                            <select name="cond_policy[]">

                                <option value=""></option>

                                <?php foreach($leave_policies as $p): ?>

                                <option value="<?= esc($p) ?>"
                                    <?= $cond['policy']==$p ? 'selected':'' ?>>

                                    <?= esc($p) ?>

                                </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <span class="ls-cond-label">after</span>

                        <input type="number"
                               name="cond_value[]"
                               class="ls-cond-num"
                               value="<?= esc($cond['value']) ?>">

                        <div class="ls-cond-unit">

                            <select name="cond_unit[]">

                                <option value="Months"
                                    <?= $cond['unit']=='Months'?'selected':'' ?>>
                                    Months
                                </option>

                                <option value="Years"
                                    <?= $cond['unit']=='Years'?'selected':'' ?>>
                                    Years
                                </option>

                                <option value="Days"
                                    <?= $cond['unit']=='Days'?'selected':'' ?>>
                                    Days
                                </option>

                            </select>

                        </div>

                        <button type="button"
                                class="ls-cond-btn remove"
                                onclick="removeCond(this)">
                            −
                        </button>

                        <?php if($ci == count($active_str['conditions'])-1): ?>

                        <button type="button"
                                class="ls-cond-btn add"
                                onclick="addCond()">
                            +
                        </button>

                        <?php endif; ?>

                    </div>

                    <?php endforeach; ?>

                </div>

            </form>

            <div class="ls-actions">

                <button type="submit"
                        form="lsForm"
                        class="ls-save-btn"
                        onclick="return validateLsForm()">

                    Save

                </button>

            </div>

        </div>

    </div>

</div>

<div class="ls-toast" id="lsToastEl">

    <span id="lsToastIcon">✅</span>

    <span id="lsToastMsg">Done!</span>

</div>

<script>

var leavePolicies = <?= json_encode($leave_policies) ?>;

function lsToast(icon,msg){

    var t = document.getElementById('lsToastEl');

    document.getElementById('lsToastIcon').innerHTML = icon;
    document.getElementById('lsToastMsg').innerHTML = msg;

    t.classList.add('show');

    setTimeout(function(){

        t.classList.remove('show');

    },3000);
}

function buildPolicyOpts(selectedVal){

    var html = '<option value=""></option>';

    leavePolicies.forEach(function(p){

        var sel = p === selectedVal ? 'selected' : '';

        html += '<option value="'+p+'" '+sel+'>'+p+'</option>';
    });

    return html;
}

function addCond(){

    document.querySelectorAll('.ls-cond-btn.add').forEach(function(b){

        b.remove();

    });

    var row = document.createElement('div');

    row.className = 'ls-cond-row';

    row.innerHTML =
        '<span class="ls-cond-label">Apply</span>'+
        '<div class="ls-cond-policy">'+
        '<select name="cond_policy[]">'+
        buildPolicyOpts('')+
        '</select>'+
        '</div>'+
        '<span class="ls-cond-label">after</span>'+
        '<input type="number" name="cond_value[]" class="ls-cond-num" value="0">'+
        '<div class="ls-cond-unit">'+
        '<select name="cond_unit[]">'+
        '<option value="Months">Months</option>'+
        '<option value="Years">Years</option>'+
        '<option value="Days">Days</option>'+
        '</select>'+
        '</div>'+
        '<button type="button" class="ls-cond-btn remove" onclick="removeCond(this)">−</button>'+
        '<button type="button" class="ls-cond-btn add" onclick="addCond()">+</button>';

    document.getElementById('conditionsWrap').appendChild(row);
}

function removeCond(btn){

    var rows = document.querySelectorAll('.ls-cond-row');

    if(rows.length <= 1){

        lsToast('⚠','Minimum one condition required');

        return;
    }

    btn.closest('.ls-cond-row').remove();

    var last = document.querySelectorAll('.ls-cond-row');

    last.forEach(function(r,i){

        var addBtn = r.querySelector('.ls-cond-btn.add');

        if(i == last.length-1){

            if(!addBtn){

                var b = document.createElement('button');

                b.type = 'button';
                b.className = 'ls-cond-btn add';
                b.innerHTML = '+';
                b.setAttribute('onclick','addCond()');

                r.appendChild(b);
            }

        }else{

            if(addBtn){
                addBtn.remove();
            }
        }
    });
}

function validateLsForm(){

    var name = document.getElementById('structureName');

    if(name.value.trim() == ''){

        lsToast('⚠','Structure Name Required');

        name.focus();

        return false;
    }

    return true;
}
</script>

<?php

$page_content = ob_get_clean();

include 'includes/header.php';

echo $page_content;

include 'includes/footer.php';

?>