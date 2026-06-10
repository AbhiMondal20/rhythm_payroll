<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Reimbursement';

/* =====================================================
   ADD REIMBURSEMENT
===================================================== */

if(isset($_POST['save_reimbursement'])){

    $type_name        = trim($_POST['type_name']);
    $type_code        = trim($_POST['type_code']);
    $remarks          = trim($_POST['remarks']);
    $max_limit        = trim($_POST['max_limit']);
    $receipt_required = trim($_POST['receipt_required']);
    $claim_period     = trim($_POST['claim_period']);
    $monthly_limit    = trim($_POST['monthly_limit']);
    $yearly_limit     = trim($_POST['yearly_limit']);

    if($type_name != '' && $type_code != ''){

        $stmt = $conn->prepare("
            INSERT INTO reimbursement_types
            (
                type_name,
                type_code,
                remarks,
                max_limit,
                receipt_required,
                claim_period,
                monthly_limit,
                yearly_limit
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?
            )
        ");

        $stmt->bind_param(
            "ssssssss",
            $type_name,
            $type_code,
            $remarks,
            $max_limit,
            $receipt_required,
            $claim_period,
            $monthly_limit,
            $yearly_limit
        );

        if($stmt->execute()){

            $typeId = $stmt->insert_id;

            if(isset($_POST['field_name'])){

                foreach($_POST['field_name'] as $key => $fieldName){

                    if(trim($fieldName)=='') continue;

                    $fieldType = $_POST['field_type'][$key];
                    $required  = isset($_POST['field_required'][$key]) ? 1 : 0;

                    $fieldStmt = $conn->prepare("
                        INSERT INTO reimbursement_fields
                        (
                            reimbursement_type_id,
                            field_name,
                            field_type,
                            is_required
                        )
                        VALUES
                        (
                            ?,?,?,?
                        )
                    ");

                    $fieldStmt->bind_param(
                        "issi",
                        $typeId,
                        $fieldName,
                        $fieldType,
                        $required
                    );

                    $fieldStmt->execute();
                }
            }

            $_SESSION['toast_success'] = "Reimbursement Type Added Successfully";

        }else{

            $_SESSION['toast_error'] = "Failed To Save Data";
        }

    }else{

        $_SESSION['toast_error'] = "Type & Code Required";
    }

    header("Location: reimbursement");
    exit;
}

/* =====================================================
   UPDATE REIMBURSEMENT
===================================================== */

if(isset($_POST['update_reimbursement'])){

    $id               = intval($_POST['edit_id']);
    $type_name        = trim($_POST['type_name']);
    $type_code        = trim($_POST['type_code']);
    $remarks          = trim($_POST['remarks']);
    $max_limit        = trim($_POST['max_limit']);
    $receipt_required = trim($_POST['receipt_required']);
    $claim_period     = trim($_POST['claim_period']);
    $monthly_limit    = trim($_POST['monthly_limit']);
    $yearly_limit     = trim($_POST['yearly_limit']);

    $stmt = $conn->prepare("
        UPDATE reimbursement_types
        SET
            type_name=?,
            type_code=?,
            remarks=?,
            max_limit=?,
            receipt_required=?,
            claim_period=?,
            monthly_limit=?,
            yearly_limit=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "ssssssssi",
        $type_name,
        $type_code,
        $remarks,
        $max_limit,
        $receipt_required,
        $claim_period,
        $monthly_limit,
        $yearly_limit,
        $id
    );

    if($stmt->execute()){

        mysqli_query(
            $conn,
            "DELETE FROM reimbursement_fields
            WHERE reimbursement_type_id='$id'"
        );

        if(isset($_POST['field_name'])){

            foreach($_POST['field_name'] as $key => $fieldName){

                if(trim($fieldName)=='') continue;

                $fieldType = $_POST['field_type'][$key];
                $required  = isset($_POST['field_required'][$key]) ? 1 : 0;

                $fieldStmt = $conn->prepare("
                    INSERT INTO reimbursement_fields
                    (
                        reimbursement_type_id,
                        field_name,
                        field_type,
                        is_required
                    )
                    VALUES
                    (
                        ?,?,?,?
                    )
                ");

                $fieldStmt->bind_param(
                    "issi",
                    $id,
                    $fieldName,
                    $fieldType,
                    $required
                );

                $fieldStmt->execute();
            }
        }

        $_SESSION['toast_success'] = "Updated Successfully";

    }else{

        $_SESSION['toast_error'] = "Update Failed";
    }

    header("Location: reimbursement");
    exit;
}

/* =====================================================
   FETCH TYPES
===================================================== */

$types = [];

$typeQuery = mysqli_query(
    $conn,
    "SELECT * FROM reimbursement_types ORDER BY id ASC"
);

while($row = mysqli_fetch_assoc($typeQuery)){

    $fields = [];

    $fieldQuery = mysqli_query(
        $conn,
        "SELECT * FROM reimbursement_fields
        WHERE reimbursement_type_id='".$row['id']."'"
    );

    while($field = mysqli_fetch_assoc($fieldQuery)){

        $fields[] = $field;
    }

    $row['fields'] = $fields;

    $types[] = $row;
}

$firstType = !empty($types) ? $types[0] : null;

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
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


/* .reimb-wrap { font-family: 'DM Sans', sans-serif; font-size: 14px; color: #1e293b; background: #f5f6fa; } */

/* ── Top header row (breadcrumb + Add Type btn) ── */
.reimb-toprow {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 24px 0;
}
.reimb-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: #1e293b; }
.reimb-breadcrumb .sep { color: #94a3b8; font-size: 16px; }
.btn-add-type {
    display: flex; align-items: center; gap: 7px;
    background: #2563eb; color: #fff; border: none;
    padding: 9px 20px; border-radius: 7px; font-size: 13px; font-weight: 500;
    cursor: pointer; font-family: inherit; transition: background .18s;
}
.btn-add-type:hover { background: #1d4ed8; }

/* ── Column label row ── */
.reimb-col-headers { display: flex; padding: 10px 24px 4px; }
.reimb-col-headers .ch-left  { width: 302px; flex-shrink: 0; font-size: 12px; color: #94a3b8; }
.reimb-col-headers .ch-right { flex: 1; font-size: 12px; color: #94a3b8; padding-left: 22px; }

/* ── Main card ── */
.reimb-card {
    margin: 0 24px 28px;
    display: flex; background: #fff;
    border: 1px solid #e2e8f0; border-radius: 10px;
    overflow: hidden; min-height: 620px;
}

/* ── Sidebar ── */
.reimb-sidebar { width: 302px; flex-shrink: 0; border-right: 1px solid #e2e8f0; }
.reimb-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 15px 18px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background .15s;
}
.reimb-item:hover { background: #f8fafc; }
.reimb-item.active { background: #f0f7ff; border-left: 3px solid #2563eb; }
.reimb-item.active .ri-name { color: #2563eb; font-weight: 600; }
.ri-name  { font-size: 14px; color: #334155; font-weight: 500; }
.ri-arrow { color: #94a3b8; font-size: 18px; line-height: 1; }
.reimb-item.active .ri-arrow { color: #2563eb; }

/* ── Right panel ── */
.reimb-detail { flex: 1; padding: 26px 30px; overflow-y: auto; display: flex; flex-direction: column; min-width: 0; }
.panel-view, .panel-new { display: none; flex-direction: column; flex: 1; }
.panel-view.active, .panel-new.active { display: flex; }

/* ── VIEW panel ── */
.detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 26px; }
.detail-title  { font-size: 17px; font-weight: 700; color: #1e293b; letter-spacing: .03em; }
.btn-edit-detail {
    display: flex; align-items: center; gap: 6px; color: #2563eb;
    background: none; border: none; font-size: 13px; font-weight: 500; cursor: pointer;
    font-family: inherit; padding: 0;
}
.btn-edit-detail svg { flex-shrink: 0; }

/* ── Shared form styles ── */
.form-row        { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 32px; margin-bottom: 20px; }
.form-row.single { grid-template-columns: 1fr; }
.form-group      { display: flex; flex-direction: column; gap: 4px; }
.form-group label { font-size: 13px; color: #475569; font-weight: 400; }

.form-input {
    border: none; border-bottom: 1.5px solid #e2e8f0;
    padding: 7px 0; font-size: 14px; color: #1e293b;
    background: transparent; outline: none; font-family: inherit;
    transition: border-color .15s; width: 100%;
}
.form-input:focus { border-bottom-color: #2563eb; }
.form-input[readonly] { color: #334155; cursor: default; }
.form-input::placeholder { color: #b0b8c9; font-size: 13px; }

.form-select {
    border: 1px solid #e2e8f0; border-radius: 6px;
    padding: 7px 10px; font-size: 14px; color: #1e293b;
    background: #fff; outline: none; font-family: inherit;
    cursor: pointer; width: 100%; transition: border-color .15s;
}
.form-select:focus { border-color: #2563eb; }

.section-divider { border: none; border-top: 1px solid #f1f5f9; margin: 18px 0; }

/* ── VIEW: Additional fields table ── */
.add-fields-bar { display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 5px; }
.add-fields-bar span.afl { font-size: 14px; font-weight: 600; color: #1e293b; }
.add-fields-bar .afl-icon { color: #475569; font-size: 16px; }
.add-fields-note { font-size: 12px; color: #94a3b8; margin-bottom: 14px; }

.field-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.field-table th { text-align: left; padding: 8px 10px; font-weight: 600; color: #64748b; font-size: 12px; border-bottom: 1px solid #e2e8f0; }
.field-table td { padding: 10px 10px; border-bottom: 1px solid #f8fafc; color: #334155; vertical-align: middle; }
.field-table tr:last-child td { border-bottom: none; }
.badge-req { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: #dcfce7; border-radius: 4px; }
.badge-req svg { width: 13px; height: 13px; stroke: #16a34a; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.badge-opt { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: #f1f5f9; border-radius: 4px; color: #94a3b8; font-size: 12px; }

.applicable-tag {
    margin-top: 20px; display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: #1e293b; font-weight: 600;
}

/* ── NEW TYPE panel ── */
.new-type-title { font-size: 17px; font-weight: 700; color: #1e293b; letter-spacing: .03em; margin-bottom: 26px; }

/* Custom attributes section */
.custom-attr-header { display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 5px; }
.custom-attr-header .ca-label { font-size: 14px; font-weight: 600; color: #1e293b; }
.custom-attr-header .ca-icon  { color: #475569; font-size: 16px; }
.custom-attr-note { font-size: 12px; color: #94a3b8; margin-bottom: 14px; }

/* Attribute row: Name | Type | Required checkbox | minus btn | plus btn */
.attr-row {
    display: grid;
    grid-template-columns: 1fr 180px 70px 32px 32px;
    gap: 10px; align-items: end; margin-bottom: 12px;
}
.attr-row .form-group { margin: 0; }
.attr-req-wrap { display: flex; flex-direction: column; align-items: center; gap: 5px; }
.attr-req-wrap .req-label { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.attr-req-wrap input[type=checkbox] { width: 16px; height: 16px; accent-color: #2563eb; cursor: pointer; margin-bottom: 6px; }

.btn-circle {
    width: 30px; height: 30px; border-radius: 50%; border: 2px solid;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; background: #fff; font-size: 18px; line-height: 1;
    font-weight: 400; transition: opacity .15s; flex-shrink: 0; margin-bottom: 4px;
}
.btn-circle-minus { border-color: #ef4444; color: #ef4444; }
.btn-circle-minus:hover { background: #fee2e2; }
.btn-circle-plus  { border-color: #22c55e; color: #22c55e; }
.btn-circle-plus:hover  { background: #dcfce7; }

/* Reimbursement Applicable To */
.reimb-applicable-section { margin-top: 20px; }
.reimb-applicable-title   { font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 12px; }
.reimb-search-row { display: flex; align-items: center; gap: 10px; }
.search-box {
    display: flex; align-items: center; gap: 8px;
    border: 1px solid #e2e8f0; border-radius: 7px;
    padding: 8px 12px; background: #fff; flex: 0 0 280px;
}
.search-box input {
    border: none; outline: none; font-family: inherit; font-size: 13px;
    color: #1e293b; background: transparent; width: 100%;
}
.search-box input::placeholder { color: #b0b8c9; }
.search-box svg { flex-shrink: 0; color: #94a3b8; }
.btn-filter {
    display: flex; align-items: center; gap: 6px;
    border: 1px solid #e2e8f0; border-radius: 7px;
    padding: 8px 16px; background: #fff; font-size: 13px;
    color: #475569; font-weight: 500; cursor: pointer; font-family: inherit;
    transition: background .15s;
}
.btn-filter:hover { background: #f8fafc; }

/* Footer buttons */
.new-type-footer {
    display: flex; justify-content: flex-end; gap: 12px;
    margin-top: auto; padding-top: 28px;
}
.btn-cancel {
    padding: 9px 22px; border: 1px solid #e2e8f0; border-radius: 7px;
    background: #fff; color: #475569; font-size: 13px; font-weight: 500;
    cursor: pointer; font-family: inherit; transition: background .15s;
}
.btn-cancel:hover { background: #f8fafc; }
.btn-submit-add {
    padding: 9px 28px; border: none; border-radius: 7px;
    background: #2563eb; color: #fff; font-size: 13px; font-weight: 500;
    cursor: pointer; font-family: inherit; transition: background .18s;
}
.btn-submit-add:hover { background: #1d4ed8; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .reimb-card { flex-direction: column; }
    .reimb-sidebar { width: 100%; border-right: none; border-bottom: 1px solid #e2e8f0; }
    .form-row { grid-template-columns: 1fr; }
    .attr-row { grid-template-columns: 1fr 1fr; }
}
</style>

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
           class="cfg-tab <?= $k==='Others'?'active':'' ?>">
           <?= $l ?>
        </a>

        <?php endforeach; ?>

    </div>

    <div class="reimb-wrap">

        <div class="reimb-toprow">

            <div>
                Reimbursement Policy › Reimbursement Types
            </div>

            <button class="btn-add-type" onclick="openNewType()">
                + Add Type
            </button>

        </div>

        <div class="reimb-col-headers">
            <div class="ch-left">Reimbursement Type</div>
            <div class="ch-right">Reimbursement Details</div>
        </div>

        <div class="reimb-card">

            <!-- SIDEBAR -->

            <div class="reimb-sidebar">

                <?php foreach($types as $index => $type){ ?>

                <div
                class="reimb-item <?= $index==0 ? 'active' : '' ?>"
                onclick='selectType(<?= json_encode($type) ?>,this)'>

                    <span>
                        <?= htmlspecialchars($type['type_name']) ?>
                    </span>

                    <span class="ri-arrow">
                        <?= $index==0 ? '›' : '⌄' ?>
                    </span>

                </div>

                <?php } ?>

            </div>

            <!-- RIGHT -->

            <div class="reimb-detail">

                <!-- VIEW PANEL -->

                <div class="panel-view active" id="panel-view">

                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">

                        <h3 id="view-title">
                            <?= strtoupper($firstType['type_name'] ?? '') ?>
                        </h3>

                        <button
                        type="button"
                        class="btn-submit-add"
                        onclick="enableEdit()">

                            Edit Details

                        </button>

                    </div>

                    <form method="POST" id="editForm">

                        <input
                        type="hidden"
                        name="edit_id"
                        id="edit_id"
                        value="<?= $firstType['id'] ?? '' ?>">

                        <div class="form-row">

                            <div>
                                <label>Type</label>

                                <input
                                type="text"
                                name="type_name"
                                id="v-type"
                                class="form-input"
                                readonly
                                value="<?= $firstType['type_name'] ?? '' ?>">
                            </div>

                            <div>
                                <label>Code</label>

                                <input
                                type="text"
                                name="type_code"
                                id="v-code"
                                class="form-input"
                                readonly
                                value="<?= $firstType['type_code'] ?? '' ?>">
                            </div>

                        </div>

                        <div class="form-row single">

                            <div>
                                <label>Remarks</label>

                                <input
                                type="text"
                                name="remarks"
                                id="v-remarks"
                                class="form-input"
                                readonly
                                value="<?= $firstType['remarks'] ?? '' ?>">
                            </div>

                        </div>

                        <div class="form-row">

                            <div>
                                <label>Maximum Limit</label>

                                <input
                                type="text"
                                name="max_limit"
                                id="v-max"
                                class="form-input"
                                readonly
                                value="<?= $firstType['max_limit'] ?? '' ?>">
                            </div>

                            <div>
                                <label>Receipt Required</label>

                                <input
                                type="text"
                                name="receipt_required"
                                id="v-receipt"
                                class="form-input"
                                readonly
                                value="<?= $firstType['receipt_required'] ?? '' ?>">
                            </div>

                        </div>

                        <div class="form-row">

                            <div>
                                <label>Claim Period</label>

                                <input
                                type="text"
                                name="claim_period"
                                id="v-claim"
                                class="form-input"
                                readonly
                                value="<?= $firstType['claim_period'] ?? '' ?>">
                            </div>

                            <div>
                                <label>Monthly Limit</label>

                                <input
                                type="text"
                                name="monthly_limit"
                                id="v-monthly"
                                class="form-input"
                                readonly
                                value="<?= $firstType['monthly_limit'] ?? '' ?>">
                            </div>

                        </div>

                        <div class="form-row single">

                            <div>
                                <label>Yearly Limit</label>

                                <input
                                type="text"
                                name="yearly_limit"
                                id="v-yearly"
                                class="form-input"
                                readonly
                                value="<?= $firstType['yearly_limit'] ?? '' ?>">
                            </div>

                        </div>

                        <div style="margin-top:20px;">

                            <button
                            type="submit"
                            name="update_reimbursement"
                            id="saveBtn"
                            class="btn-submit-add"
                            style="display:none;">

                                Save Changes

                            </button>

                        </div>

                    </form>

                </div>

                <!-- ADD PANEL -->

                <div class="panel-new" id="panel-new">

                    <form method="POST">

                        <h3 style="margin-bottom:20px;">
                            NEW REIMBURSEMENT TYPE
                        </h3>

                        <div class="form-row">

                            <div>
                                <label>Type</label>

                                <input
                                type="text"
                                name="type_name"
                                class="form-input">
                            </div>

                            <div>
                                <label>Code</label>

                                <input
                                type="text"
                                name="type_code"
                                class="form-input">
                            </div>

                        </div>

                        <div class="form-row single">

                            <div>
                                <label>Remarks</label>

                                <input
                                type="text"
                                name="remarks"
                                class="form-input">
                            </div>

                        </div>

                        <div class="form-row">

                            <div>
                                <label>Maximum Limit</label>

                                <input
                                type="text"
                                name="max_limit"
                                class="form-input">
                            </div>

                            <div>
                                <label>Receipt Required</label>

                                <input
                                type="text"
                                name="receipt_required"
                                class="form-input">
                            </div>

                        </div>

                        <div class="form-row">

                            <div>
                                <label>Claim Period</label>

                                <select
                                name="claim_period"
                                class="form-select">

                                    <option>30 Days</option>
                                    <option>60 Days</option>
                                    <option>90 Days</option>

                                </select>
                            </div>

                            <div>
                                <label>Monthly Limit</label>

                                <input
                                type="text"
                                name="monthly_limit"
                                class="form-input">
                            </div>

                        </div>

                        <div class="form-row single">

                            <div>
                                <label>Yearly Limit</label>

                                <input
                                type="text"
                                name="yearly_limit"
                                class="form-input">
                            </div>

                        </div>

                        <hr>

                        <h4 style="margin-bottom:15px;">
                            Add Custom Attributes
                        </h4>

                        <div id="attr-rows">

                            <div class="attr-row">

                                <input
                                type="text"
                                name="field_name[]"
                                class="form-input">

                                <select
                                name="field_type[]"
                                class="form-select">

                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="date">Date</option>
                                    <option value="file">File</option>

                                </select>

                                <div>
                                    <input
                                    type="checkbox"
                                    name="field_required[0]"
                                    checked>
                                </div>

                                <button
                                type="button"
                                class="btn-circle btn-circle-plus"
                                onclick="addAttrRow()">

                                    +

                                </button>

                            </div>

                        </div>

                        <div class="new-type-footer">

                            <button
                            type="button"
                            class="btn-cancel"
                            onclick="cancelNewType()">

                                Cancel

                            </button>

                            <button
                            type="submit"
                            name="save_reimbursement"
                            class="btn-submit-add">

                                Add

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<script>

function openNewType(){

    document.getElementById('panel-view')
    .classList.remove('active');

    document.getElementById('panel-new')
    .classList.add('active');
}

function cancelNewType(){

    document.getElementById('panel-new')
    .classList.remove('active');

    document.getElementById('panel-view')
    .classList.add('active');
}

function enableEdit(){

    document.querySelectorAll('#editForm .form-input')
    .forEach(input => {

        input.removeAttribute('readonly');
    });

    document.getElementById('saveBtn')
    .style.display='inline-block';
}

function selectType(data,el){

    document.querySelectorAll('.reimb-item')
    .forEach(item=>{

        item.classList.remove('active');

        item.querySelector('.ri-arrow').innerHTML='⌄';
    });

    el.classList.add('active');

    el.querySelector('.ri-arrow').innerHTML='›';

    document.getElementById('edit_id').value = data.id;
    document.getElementById('view-title').innerHTML = data.type_name.toUpperCase();

    document.getElementById('v-type').value     = data.type_name;
    document.getElementById('v-code').value     = data.type_code;
    document.getElementById('v-remarks').value  = data.remarks;
    document.getElementById('v-max').value      = data.max_limit;
    document.getElementById('v-receipt').value  = data.receipt_required;
    document.getElementById('v-claim').value    = data.claim_period;
    document.getElementById('v-monthly').value  = data.monthly_limit;
    document.getElementById('v-yearly').value   = data.yearly_limit;

    document.querySelectorAll('#editForm .form-input')
    .forEach(input => {

        input.setAttribute('readonly',true);
    });

    document.getElementById('saveBtn')
    .style.display='none';

    cancelNewType();
}

function addAttrRow(){

    let index = document.querySelectorAll('.attr-row').length;

    let html = `

    <div class="attr-row">

        <input
        type="text"
        name="field_name[]"
        class="form-input">

        <select
        name="field_type[]"
        class="form-select">

            <option value="text">Text</option>
            <option value="number">Number</option>
            <option value="date">Date</option>
            <option value="file">File</option>

        </select>

        <div>
            <input
            type="checkbox"
            name="field_required[${index}]"
            checked>
        </div>

        <button
        type="button"
        class="btn-circle btn-circle-minus"
        onclick="removeRow(this)">

            -

        </button>

    </div>
    `;

    document.getElementById('attr-rows')
    .insertAdjacentHTML('beforeend',html);
}

function removeRow(btn){

    btn.closest('.attr-row').remove();
}

</script>

<?php if(isset($_SESSION['toast_success'])){ ?>

<script>

Swal.fire({
    toast:true,
    position:'top-end',
    icon:'success',
    title:'<?= $_SESSION['toast_success'] ?>',
    showConfirmButton:false,
    timer:3000
});

</script>

<?php unset($_SESSION['toast_success']); } ?>

<?php if(isset($_SESSION['toast_error'])){ ?>

<script>

Swal.fire({
    toast:true,
    position:'top-end',
    icon:'error',
    title:'<?= $_SESSION['toast_error'] ?>',
    showConfirmButton:false,
    timer:3000
});

</script>

<?php unset($_SESSION['toast_error']); } ?>

<?php

$page_content = ob_get_clean();

include 'includes/header.php';

echo $page_content;

include 'includes/footer.php';

?>

<script src="includes/assets/scripts.js"></script>