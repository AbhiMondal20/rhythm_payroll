<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Tax Profiles';

/* -------------------------------
   Load Organisations (Now Companies)
-------------------------------- */
$orgs = [];

// Updated to query `companies` table
$query = "
    SELECT id, client_name AS name
    FROM companies
    WHERE status = 1 OR status = 'Active'
    ORDER BY client_name
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $orgs[] = $row;
    }
}

/* -------------------------------
   Load Locations (Now org_locations)
-------------------------------- */
$locations = [];

// Updated to query `org_locations` table
$query = "
    SELECT id, location_name AS name
    FROM org_locations
    WHERE status = 1 OR status = 'Active'
    ORDER BY location_name
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $locations[] = $row;
    }
}

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
/* ============================================================
   tax_profiles.css  –  PerkPayroll-style Tax Profiles page
   ============================================================ */

/* ---------- Config tab bar ---------- */
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

/* ---------- Wrapper ---------- */
.tp-wrapper {
    background: #fff;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #1e293b;
    min-height: calc(100vh - 140px);
}

/* ---------- List view top bar ---------- */
.tp-list-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    border-bottom: 1px solid #E2E8F0;
}

.tp-breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13.5px;
}

.tp-bc-parent {
    color: #64748B;
}

.tp-bc-sep {
    color: #94A3B8;
    font-size: 16px;
}

.tp-bc-current {
    font-weight: 600;
    color: #1e293b;
}

.tp-btn-add {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #2563EB;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 9px 18px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}

.tp-btn-add>span {
    font-size: 18px;
    line-height: 1;
}

.tp-btn-add:hover {
    background: #1D4ED8;
}

/* ---------- Sub-tabs: ESI / TDS / PT ---------- */
.tp-subtabs {
    display: flex;
    align-items: center;
    gap: 0;
    padding: 0 24px;
    border-bottom: 1px solid #E2E8F0;
}

.tp-subtab {
    padding: 12px 18px;
    font-size: 13.5px;
    font-weight: 500;
    color: #6B7280;
    background: none;
    border: none;
    border-bottom: 2.5px solid transparent;
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
    transition: color .15s, border-color .15s;
    margin-bottom: -1px;
}

.tp-subtab:hover {
    color: #1e293b;
}

.tp-subtab.active {
    color: #2563EB;
    border-bottom-color: #2563EB;
    font-weight: 600;
}

/* ---------- Empty state ---------- */
.tp-profile-list {
    min-height: 300px;
    padding: 12px 24px;
}

.tp-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 70px 20px;
}

.tp-empty-art {
    margin-bottom: 16px;
}

.tp-empty-text {
    font-size: 14px;
    color: #64748B;
    margin: 0;
}

/* ---------- Profile table ---------- */
.tp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    margin-top: 8px;
}

.tp-table th {
    padding: 10px 14px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 700;
    color: #64748B;
    letter-spacing: .4px;
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
}

.tp-table td {
    padding: 13px 14px;
    border-bottom: 1px solid #F1F5F9;
    color: #1e293b;
    vertical-align: middle;
}

.tp-table tbody tr:last-child td {
    border-bottom: none;
}

.tp-table tbody tr:hover td {
    background: #F8FAFC;
}

.tp-row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}

.tp-icon-btn {
    width: 30px;
    height: 30px;
    border: 1.5px solid #E2E8F0;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .1s, border-color .1s;
}

.tp-icon-btn:hover {
    background: #F0F9FF;
    border-color: #93C5FD;
}

.tp-icon-btn.del:hover {
    background: #FEF2F2;
    border-color: #FCA5A5;
}

/* ============================================================
   FORM VIEW
   ============================================================ */
.tp-form-breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13.5px;
    padding: 14px 24px 10px;
    border-bottom: 1px solid #E2E8F0;
}

/* Form grid system */
.tp-form-grid {
    display: grid;
    gap: 0 32px;
    padding: 20px 24px 0;
}

.tp-grid1 {
    grid-template-columns: 1fr;
}

.tp-grid2 {
    grid-template-columns: 1fr 1fr;
}

.tp-grid4 {
    grid-template-columns: 1fr 1fr 1fr 1fr;
}

.tp-fg {
    padding-bottom: 20px;
    position: relative;
}

.tp-fg-span3 {
    grid-column: span 3;
}

/* Underline inputs */
.tp-lbl {
    display: block;
    font-size: 12px;
    color: #374151;
    margin-bottom: 8px;
}

.tp-lbl.required::before {
    content: '* ';
    color: #EF4444;
}

.tp-input {
    width: 100%;
    box-sizing: border-box;
    border: none;
    border-bottom: 1.5px solid #CBD5E1;
    background: transparent;
    padding: 5px 0;
    font-size: 13.5px;
    color: #1e293b;
    outline: none;
    font-family: inherit;
    transition: border-color .15s;
}

.tp-input:focus {
    border-bottom-color: #2563EB;
}

.tp-select {
    appearance: none;
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%2364748B' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 2px center;
    padding-right: 18px;
}

/* Signatory search field */
.tp-search-field {
    display: flex;
    align-items: center;
    gap: 6px;
    border-bottom: 1.5px solid #CBD5E1;
    padding-bottom: 5px;
    position: relative;
    transition: border-color .15s;
}

.tp-search-field:focus-within {
    border-bottom-color: #2563EB;
}

.tp-search-icon {
    flex-shrink: 0;
}

.tp-search-input {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 13.5px;
    color: #1e293b;
    outline: none;
    font-family: inherit;
    padding: 0;
}

.tp-search-input::placeholder {
    color: #94A3B8;
    font-size: 13px;
}

/* Signatory dropdown */
.tp-sig-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1.5px solid #E2E8F0;
    border-radius: 8px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, .1);
    z-index: 100;
    max-height: 220px;
    overflow-y: auto;
}

.tp-sig-item {
    padding: 10px 14px;
    cursor: pointer;
    font-size: 13px;
    transition: background .1s;
}

.tp-sig-item:hover {
    background: #F0F9FF;
}

.tp-sig-name {
    font-weight: 600;
    color: #0F172A;
}

.tp-sig-meta {
    font-size: 11.5px;
    color: #64748B;
    margin-top: 2px;
}

.tp-sig-empty {
    padding: 14px;
    font-size: 13px;
    color: #94A3B8;
    text-align: center;
}

/* TDS section headings */
.tp-section-heading {
    font-size: 13.5px;
    font-weight: 700;
    color: #374151;
    padding: 24px 24px 0;
    border-top: 1px solid #F1F5F9;
    margin-top: 8px;
}

/* ---------- Form actions ---------- */
.tp-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 24px 24px 20px;
    border-top: 1px solid #F1F5F9;
    margin-top: 16px;
}

.tp-btn-cancel {
    padding: 9px 24px;
    border: 1.5px solid #CBD5E1;
    background: #fff;
    color: #64748B;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}

.tp-btn-cancel:hover {
    background: #F8FAFC;
    border-color: #94A3B8;
}

/* ---------- Toast ---------- */
.tp-toast {
    position: fixed;
    bottom: 28px;
    right: 28px;
    background: #1E293B;
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 13.5px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, .18);
    z-index: 9999;
    opacity: 0;
    transform: translateY(12px);
    transition: opacity .25s, transform .25s;
    pointer-events: none;
}

.tp-toast.success {
    background: #166534;
}

.tp-toast.error {
    background: #991B1B;
}

.tp-toast.show {
    opacity: 1;
    transform: translateY(0);
}

/* ---------- Responsive ---------- */
@media (max-width: 900px) {
    .tp-grid4 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 560px) {
    .tp-grid4 {
        grid-template-columns: 1fr;
    }

    .tp-fg-span3 {
        grid-column: span 1;
    }
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden;">
    <div class="cfg-tabs">
        <?php foreach([
            'AccountInfo'=>'Account Info','Organization'=>'Organization',
            'Payroll'=>'Payroll','Attendance'=>'Attendance',
            'Leave'=>'Leave','Training'=>'Training','Others'=>'Others'
        ] as $k=>$l): ?>
        <a href="configuration#<?=$k?>" class="cfg-tab <?=$k==='Others'?'active':''?>"><?=$l?></a>
        <?php endforeach; ?>
    </div>


    <div class="tp-wrapper">

        <div id="tpViewList">

            <div class="tp-list-topbar">
                <div class="tp-breadcrumb">
                    <span class="tp-bc-parent">Others</span>
                    <span class="tp-bc-sep">›</span>
                    <span class="tp-bc-current">Tax Profiles</span>
                </div>
                <button class="tp-btn-add" id="tpBtnAdd" onclick="TP.openForm()">
                    <span>+</span> Add New Profile
                </button>
            </div>

            <div class="tp-subtabs">
                <button class="tp-subtab active" data-type="ESI" onclick="TP.switchTab('ESI',this)">ESI</button>
                <button class="tp-subtab" data-type="TDS" onclick="TP.switchTab('TDS',this)">TDS</button>
                <button class="tp-subtab" data-type="Professional Tax"
                    onclick="TP.switchTab('Professional Tax',this)">Professional Tax</button>
            </div>

            <div class="tp-profile-list" id="tpProfileList">
                <div class="tp-empty" id="tpEmpty">
                    <div class="tp-empty-art">
                        <svg width="100" height="100" viewBox="0 0 100 100" fill="none">
                            <circle cx="50" cy="50" r="50" fill="#EEF2FF" />
                            <rect x="22" y="18" width="56" height="64" rx="5" fill="#CBD5E1" />
                            <rect x="22" y="18" width="56" height="16" rx="5" fill="#94A3B8" />
                            <rect x="32" y="42" width="28" height="4" rx="2" fill="#3B82F6" />
                            <rect x="32" y="52" width="22" height="4" rx="2" fill="#3B82F6" />
                            <rect x="32" y="62" width="16" height="4" rx="2" fill="#93C5FD" />
                        </svg>
                    </div>
                    <p class="tp-empty-text" id="tpEmptyText">No ESI Profiles!</p>
                </div>
                <table class="tp-table" id="tpTable" style="display:none;">
                    <thead>
                        <tr>
                            <th>S NO.</th>
                            <th>CODE</th>
                            <th>NAME</th>
                            <th>ORGANISATION</th>
                            <th>REG. / PAN</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tpTbody"></tbody>
                </table>
            </div>
        </div>

        <div id="tpViewForm" style="display:none;">

            <div class="tp-form-breadcrumb">
                <span class="tp-bc-parent" style="cursor:pointer;" onclick="TP.backToList()">Tax profiles</span>
                <span class="tp-bc-sep">›</span>
                <span class="tp-bc-current" id="tpFormBcType">ESI</span>
            </div>

            <div class="tp-form-grid tp-grid4">
                <div class="tp-fg">
                    <label class="tp-lbl required">Organisation Name</label>
                    <select class="tp-input tp-select" id="fOrgName">
                        <option value="">Select</option>
                        <?php foreach($orgs as $o): ?>
                        <option value="<?=htmlspecialchars($o['id'])?>"><?=htmlspecialchars($o['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl">Signatory</label>
                    <div class="tp-search-field">
                        <svg width="13" height="13" viewBox="0 0 13 13" fill="none" class="tp-search-icon">
                            <circle cx="5.5" cy="5.5" r="4" stroke="#94A3B8" stroke-width="1.3" />
                            <path d="M9 9L11.5 11.5" stroke="#94A3B8" stroke-width="1.3" stroke-linecap="round" />
                        </svg>
                        <input type="text" class="tp-input tp-search-input" id="fSignatory"
                            placeholder="Search by name or #code" oninput="TP.signatorySearch(this.value)">
                        <div class="tp-sig-dropdown" id="tpSigDropdown" style="display:none;"></div>
                    </div>
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl required">Code</label>
                    <input type="text" class="tp-input" id="fCode" maxlength="50">
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl required">Name</label>
                    <input type="text" class="tp-input" id="fName" maxlength="200">
                </div>
            </div>

            <div class="tp-form-grid tp-grid4" id="rowRegPan">
                <div class="tp-fg">
                    <label class="tp-lbl required" id="lblRegPan">Registration Number</label>
                    <input type="text" class="tp-input" id="fRegNum" maxlength="50">
                </div>
                <div class="tp-fg" id="rowPan" style="display:none;">
                    <label class="tp-lbl required">PAN</label>
                    <input type="text" class="tp-input" id="fPan" maxlength="20">
                </div>
                <div class="tp-fg" id="rowTan" style="display:none;">
                    <label class="tp-lbl required">TAN</label>
                    <input type="text" class="tp-input" id="fTan" maxlength="20">
                </div>
                <div class="tp-fg" id="rowDescSNo" style="display:none;">
                    <label class="tp-lbl">Description S No.</label>
                    <input type="text" class="tp-input" id="fDescSNo" maxlength="50">
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl required">Address 1</label>
                    <input type="text" class="tp-input" id="fAddr1" maxlength="255">
                </div>
            </div>

            <div class="tp-form-grid tp-grid1">
                <div class="tp-fg">
                    <label class="tp-lbl">Address 2</label>
                    <input type="text" class="tp-input" id="fAddr2" maxlength="255">
                </div>
            </div>

            <div class="tp-form-grid tp-grid4">
                <div class="tp-fg">
                    <label class="tp-lbl required">City</label>
                    <input type="text" class="tp-input" id="fCity" maxlength="100">
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl required">State</label>
                    <input type="text" class="tp-input" id="fState" maxlength="100">
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl required">Country</label>
                    <input type="text" class="tp-input" id="fCountry" maxlength="100" value="India">
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl required">Pincode</label>
                    <input type="text" class="tp-input" id="fPincode" maxlength="10">
                </div>
            </div>

            <div class="tp-form-grid tp-grid4">
                <div class="tp-fg">
                    <label class="tp-lbl required">Phone 1</label>
                    <input type="text" class="tp-input" id="fPhone1" maxlength="20">
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl">Phone 2</label>
                    <input type="text" class="tp-input" id="fPhone2" maxlength="20">
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl">Fax</label>
                    <input type="text" class="tp-input" id="fFax" maxlength="30">
                </div>
                <div class="tp-fg">
                    <label class="tp-lbl">Website</label>
                    <input type="text" class="tp-input" id="fWebsite" maxlength="255">
                </div>
            </div>

            <div class="tp-form-grid tp-grid4">
                <div class="tp-fg">
                    <label class="tp-lbl">Locations</label>
                    <select class="tp-input tp-select" id="fLocations">
                        <option value=""></option>
                        <?php foreach($locations as $loc): ?>
                        <option value="<?=htmlspecialchars($loc['id'])?>"><?=htmlspecialchars($loc['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tp-fg tp-fg-span3">
                    <label class="tp-lbl">Note</label>
                    <input type="text" class="tp-input" id="fNote" maxlength="500">
                </div>
            </div>

            <div id="tpTdsExtra" style="display:none;">

                <div class="tp-section-heading">City TDS (Tax deducted at source)</div>
                <div class="tp-form-grid tp-grid1">
                    <div class="tp-fg"><label class="tp-lbl">Address 1</label><input type="text" class="tp-input"
                            id="fCityTdsAddr1" maxlength="255"></div>
                </div>
                <div class="tp-form-grid tp-grid1">
                    <div class="tp-fg"><label class="tp-lbl">Address 2</label><input type="text" class="tp-input"
                            id="fCityTdsAddr2" maxlength="255"></div>
                </div>
                <div class="tp-form-grid tp-grid4">
                    <div class="tp-fg"><label class="tp-lbl">City</label><input type="text" class="tp-input"
                            id="fCityTdsCity" maxlength="100"></div>
                    <div class="tp-fg"><label class="tp-lbl">State</label><input type="text" class="tp-input"
                            id="fCityTdsState" maxlength="100"></div>
                    <div class="tp-fg"><label class="tp-lbl">Country</label><input type="text" class="tp-input"
                            id="fCityTdsCountry" maxlength="100"></div>
                    <div class="tp-fg"><label class="tp-lbl">Pincode</label><input type="text" class="tp-input"
                            id="fCityTdsPincode" maxlength="10"></div>
                </div>
                <div class="tp-form-grid tp-grid4">
                    <div class="tp-fg"><label class="tp-lbl required">Phone 1</label><input type="text" class="tp-input"
                            id="fCityTdsPhone1" maxlength="20"></div>
                    <div class="tp-fg"><label class="tp-lbl">Phone 2</label><input type="text" class="tp-input"
                            id="fCityTdsPhone2" maxlength="20"></div>
                    <div class="tp-fg"><label class="tp-lbl">Fax</label><input type="text" class="tp-input"
                            id="fCityTdsFax" maxlength="30"></div>
                    <div class="tp-fg"><label class="tp-lbl">Website</label><input type="text" class="tp-input"
                            id="fCityTdsWebsite" maxlength="255"></div>
                </div>

                <div class="tp-section-heading">IT (Income Tax)</div>
                <div class="tp-form-grid tp-grid4">
                    <div class="tp-fg"><label class="tp-lbl">Ward</label><input type="text" class="tp-input"
                            id="fItWard" maxlength="100"></div>
                    <div class="tp-fg"><label class="tp-lbl">Circle</label><input type="text" class="tp-input"
                            id="fItCircle" maxlength="100"></div>
                    <div class="tp-fg"><label class="tp-lbl">Range</label><input type="text" class="tp-input"
                            id="fItRange" maxlength="100"></div>
                </div>

                <div class="tp-section-heading">TDS (Tax Deducted at Source)</div>
                <div class="tp-form-grid tp-grid4">
                    <div class="tp-fg"><label class="tp-lbl">Ward</label><input type="text" class="tp-input"
                            id="fTdsWard" maxlength="100"></div>
                    <div class="tp-fg"><label class="tp-lbl">Circle</label><input type="text" class="tp-input"
                            id="fTdsCircle" maxlength="100"></div>
                    <div class="tp-fg"><label class="tp-lbl">Range</label><input type="text" class="tp-input"
                            id="fTdsRange" maxlength="100"></div>
                </div>

            </div><input type="hidden" id="fEditingId" value="">
            <input type="hidden" id="fTaxType" value="ESI">

            <div class="tp-form-actions">
                <button class="tp-btn-cancel" onclick="TP.backToList()">Cancel</button>
                <button class="tp-btn-add" id="tpBtnSubmit" onclick="TP.submitForm()">Add</button>
            </div>

        </div>
    </div>

</div>
<script>
// Pass PHP data to JS
const TP_ORG_DATA = <?= json_encode($orgs) ?>;
</script>
<script>
/**
 * tax_profiles.js
 * ESI / TDS / Professional Tax profiles management
 */

const TP = (() => {
    'use strict';

    const API = 'API/tax_profiles_api.php';
    const $ = id => document.getElementById(id);

    /* ── State ──────────────────────────────────────────────── */
    let currentType = 'ESI';
    let editingId = null;
    let sigTimer = null;
    let sigSelected = null; // { id, name }

    /* ── Init ───────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        loadList('ESI');
        document.addEventListener('click', e => {
            if (!e.target.closest('.tp-search-field')) closeSigDropdown();
        });
    });

    /* ════════════════════════════════════════════════
       TAB SWITCHING
    ════════════════════════════════════════════════ */
    function switchTab(type, btn) {
        currentType = type;
        document.querySelectorAll('.tp-subtab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        loadList(type);
    }

    /* ════════════════════════════════════════════════
       LIST
    ════════════════════════════════════════════════ */
    function loadList(type) {
        showView('tpViewList');
        $('tpEmpty').style.display = 'flex';
        $('tpTable').style.display = 'none';
        $('tpEmptyText').textContent = `No ${type} Profiles!`;

        fetch(`${API}?action=list&type=${encodeURIComponent(type)}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showToast(res.message, 'error');
                    return;
                }
                renderList(res.data || []);
            })
            .catch(() => showToast('Network error.', 'error'));
    }

    function renderList(rows) {
        if (!rows.length) {
            $('tpEmpty').style.display = 'flex';
            $('tpTable').style.display = 'none';
            return;
        }
        $('tpEmpty').style.display = 'none';
        $('tpTable').style.display = 'table';

        $('tpTbody').innerHTML = '';
        rows.forEach((r, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
        <td>${i + 1}</td>
        <td>${esc(r.code)}</td>
        <td>${esc(r.name)}</td>
        <td>${esc(r.org_name || '—')}</td>
        <td>${esc(r.pan || r.registration_number || '—')}</td>
        <td>
          <div class="tp-row-actions">
            <button class="tp-icon-btn" title="Edit" data-id="${r.id}">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                <path d="M9 1.5L11.5 4L4 11.5H1.5V9L9 1.5Z"
                      stroke="#2563EB" stroke-width="1.3" stroke-linejoin="round"/>
              </svg>
            </button>
            <button class="tp-icon-btn del" title="Delete" data-id="${r.id}">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                <path d="M2 3.5h9M5 3.5V2h3v1.5M5.5 3.5l.5 7h1l.5-7"
                      stroke="#EF4444" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </td>`;
            tr.querySelector('.tp-icon-btn:not(.del)').onclick = () => loadForEdit(r.id);
            tr.querySelector('.tp-icon-btn.del').onclick = () => deleteProfile(r.id, r.name);
            $('tpTbody').appendChild(tr);
        });
    }

    /* ════════════════════════════════════════════════
       FORM OPEN (add)
    ════════════════════════════════════════════════ */
    function openForm() {
        editingId = null;
        sigSelected = null;
        clearForm();
        applyTypeUi(currentType);
        $('tpBtnSubmit').textContent = 'Add';
        showView('tpViewForm');
    }

    function loadForEdit(id) {
        fetch(`${API}?action=get&id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showToast(res.message, 'error');
                    return;
                }
                editingId = res.data.id;
                populateForm(res.data);
                applyTypeUi(res.data.tax_type);
                $('tpBtnSubmit').textContent = 'Save';
                showView('tpViewForm');
            });
    }

    /* ════════════════════════════════════════════════
       TYPE-SPECIFIC UI
    ════════════════════════════════════════════════ */
    function applyTypeUi(type) {
        $('fTaxType').value = type;
        $('tpFormBcType').textContent = type === 'Professional Tax' ? 'PT' : type;

        // Registration Number vs PAN row
        const isTds = type === 'TDS';
        $('fRegNum').closest('.tp-fg').style.display = isTds ? 'none' : '';
        $('rowPan').style.display = isTds ? '' : 'none';
        $('rowTan').style.display = isTds ? '' : 'none';
        $('rowDescSNo').style.display = isTds ? '' : 'none';
        $('tpTdsExtra').style.display = isTds ? 'block' : 'none';
    }

    /* ════════════════════════════════════════════════
       FORM CLEAR / POPULATE
    ════════════════════════════════════════════════ */
    function clearForm() {
        ['fOrgName', 'fCode', 'fName', 'fRegNum', 'fPan', 'fTan', 'fDescSNo',
            'fAddr1', 'fAddr2', 'fCity', 'fState', 'fCountry', 'fPincode',
            'fPhone1', 'fPhone2', 'fFax', 'fWebsite', 'fLocations', 'fNote',
            'fCityTdsAddr1', 'fCityTdsAddr2', 'fCityTdsCity', 'fCityTdsState',
            'fCityTdsCountry', 'fCityTdsPincode', 'fCityTdsPhone1', 'fCityTdsPhone2',
            'fCityTdsFax', 'fCityTdsWebsite',
            'fItWard', 'fItCircle', 'fItRange',
            'fTdsWard', 'fTdsCircle', 'fTdsRange',
            'fEditingId'
        ].forEach(id => {
            const el = $(id);
            if (el) el.value = '';
        });
        $('fCountry').value = 'India';
        $('fSignatory').value = '';
        sigSelected = null;
        $('fEditingId').value = '';
    }

    function populateForm(d) {
        const set = (id, val) => {
            const el = $(id);
            if (el) el.value = val ?? '';
        };
        set('fOrgName', d.organisation_id);
        set('fSignatory', d.signatory_name || '');
        if (d.signatory_id) sigSelected = {
            id: d.signatory_id,
            name: d.signatory_name
        };
        set('fCode', d.code);
        set('fName', d.name);
        set('fRegNum', d.registration_number);
        set('fPan', d.pan);
        set('fTan', d.tan);
        set('fDescSNo', d.description_s_no);
        set('fAddr1', d.address1);
        set('fAddr2', d.address2);
        set('fCity', d.city);
        set('fState', d.state);
        set('fCountry', d.country || 'India');
        set('fPincode', d.pincode);
        set('fPhone1', d.phone1);
        set('fPhone2', d.phone2);
        set('fFax', d.fax);
        set('fWebsite', d.website);
        set('fLocations', d.location_id);
        set('fNote', d.note);
        // TDS extras
        set('fCityTdsAddr1', d.city_tds_address1);
        set('fCityTdsAddr2', d.city_tds_address2);
        set('fCityTdsCity', d.city_tds_city);
        set('fCityTdsState', d.city_tds_state);
        set('fCityTdsCountry', d.city_tds_country);
        set('fCityTdsPincode', d.city_tds_pincode);
        set('fCityTdsPhone1', d.city_tds_phone1);
        set('fCityTdsPhone2', d.city_tds_phone2);
        set('fCityTdsFax', d.city_tds_fax);
        set('fCityTdsWebsite', d.city_tds_website);
        set('fItWard', d.it_ward);
        set('fItCircle', d.it_circle);
        set('fItRange', d.it_range);
        set('fTdsWard', d.tds_ward);
        set('fTdsCircle', d.tds_circle);
        set('fTdsRange', d.tds_range);
        set('fEditingId', d.id);
        $('fTaxType').value = d.tax_type;
    }

    /* ════════════════════════════════════════════════
       SUBMIT
    ════════════════════════════════════════════════ */
    function submitForm() {
        const type = $('fTaxType').value;
        const code = $('fCode').value.trim();
        const name = $('fName').value.trim();
        if (!code || !name) {
            showToast('Code and Name are required.', 'error');
            return;
        }

        const btn = $('tpBtnSubmit');
        btn.disabled = true;
        btn.textContent = editingId ? 'Saving…' : 'Adding…';

        const fd = new FormData();
        fd.append('action', editingId ? 'update' : 'add');
        if (editingId) fd.append('id', editingId);
        fd.append('tax_type', type);

        const fields = ['code', 'name', 'fOrgName', 'fSignatory', 'fRegNum', 'fPan', 'fTan', 'fDescSNo',
            'fAddr1', 'fAddr2', 'fCity', 'fState', 'fCountry', 'fPincode',
            'fPhone1', 'fPhone2', 'fFax', 'fWebsite', 'fLocations', 'fNote',
            'fCityTdsAddr1', 'fCityTdsAddr2', 'fCityTdsCity', 'fCityTdsState',
            'fCityTdsCountry', 'fCityTdsPincode', 'fCityTdsPhone1', 'fCityTdsPhone2',
            'fCityTdsFax', 'fCityTdsWebsite',
            'fItWard', 'fItCircle', 'fItRange',
            'fTdsWard', 'fTdsCircle', 'fTdsRange'
        ];

        // Map field IDs → API param names
        const idToParam = {
            fOrgName: 'organisation_id',
            fRegNum: 'registration_number',
            fPan: 'pan',
            fTan: 'tan',
            fDescSNo: 'description_s_no',
            fAddr1: 'address1',
            fAddr2: 'address2',
            fCity: 'city',
            fState: 'state',
            fCountry: 'country',
            fPincode: 'pincode',
            fPhone1: 'phone1',
            fPhone2: 'phone2',
            fFax: 'fax',
            fWebsite: 'website',
            fLocations: 'location_id',
            fNote: 'note',
            fCityTdsAddr1: 'city_tds_address1',
            fCityTdsAddr2: 'city_tds_address2',
            fCityTdsCity: 'city_tds_city',
            fCityTdsState: 'city_tds_state',
            fCityTdsCountry: 'city_tds_country',
            fCityTdsPincode: 'city_tds_pincode',
            fCityTdsPhone1: 'city_tds_phone1',
            fCityTdsPhone2: 'city_tds_phone2',
            fCityTdsFax: 'city_tds_fax',
            fCityTdsWebsite: 'city_tds_website',
            fItWard: 'it_ward',
            fItCircle: 'it_circle',
            fItRange: 'it_range',
            fTdsWard: 'tds_ward',
            fTdsCircle: 'tds_circle',
            fTdsRange: 'tds_range',
        };

        fd.append('code', code);
        fd.append('name', name);
        Object.entries(idToParam).forEach(([id, param]) => {
            const el = $(id);
            if (el) fd.append(param, el.value.trim());
        });
        // Signatory: use selected employee id if available
        if (sigSelected) fd.append('signatory_id', sigSelected.id);

        fetch(API, {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast(res.message, 'success');
                    editingId = null;
                    loadList(type);
                } else {
                    showToast(res.message || 'Failed.', 'error');
                    btn.disabled = false;
                    btn.textContent = editingId ? 'Save' : 'Add';
                }
            })
            .catch(() => {
                showToast('Network error.', 'error');
                btn.disabled = false;
                btn.textContent = editingId ? 'Save' : 'Add';
            });
    }

    /* ════════════════════════════════════════════════
       DELETE
    ════════════════════════════════════════════════ */
    function deleteProfile(id, name) {
        if (!confirm(`Delete profile "${name}"? This cannot be undone.`)) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch(API, {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('Profile deleted.', 'success');
                    loadList(currentType);
                } else showToast(res.message, 'error');
            });
    }

    /* ════════════════════════════════════════════════
       SIGNATORY AUTOCOMPLETE
    ════════════════════════════════════════════════ */
    function signatorySearch(val) {
        sigSelected = null;
        clearTimeout(sigTimer);
        if (!val.trim()) {
            closeSigDropdown();
            return;
        }
        sigTimer = setTimeout(() => {
            fetch(`${API}?action=search_signatory&q=${encodeURIComponent(val)}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) showSigDropdown(res.data || []);
                });
        }, 250);
    }

    function showSigDropdown(list) {
        const dd = $('tpSigDropdown');
        dd.innerHTML = '';
        if (!list.length) {
            dd.innerHTML = '<div class="tp-sig-empty">No employees found.</div>';
        } else {
            list.forEach(e => {
                const item = document.createElement('div');
                item.className = 'tp-sig-item';
                item.innerHTML = `<div class="tp-sig-name">${esc(e.name)} – #${esc(e.employee_code)}</div>
                          <div class="tp-sig-meta">${esc(e.designation||'')}</div>`;
                item.onclick = () => {
                    sigSelected = {
                        id: e.id,
                        name: `${e.name} – #${e.employee_code}`
                    };
                    $('fSignatory').value = sigSelected.name;
                    closeSigDropdown();
                };
                dd.appendChild(item);
            });
        }
        dd.style.display = 'block';
    }

    function closeSigDropdown() {
        $('tpSigDropdown').style.display = 'none';
    }

    /* ════════════════════════════════════════════════
       NAVIGATION
    ════════════════════════════════════════════════ */
    function backToList() {
        editingId = null;
        showView('tpViewList');
        loadList(currentType);
    }

    function showView(id) {
        ['tpViewList', 'tpViewForm'].forEach(v => {
            $(v).style.display = v === id ? 'block' : 'none';
        });
    }

    /* ── Utilities ──────────────────────────────────────────── */
    function esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    let toastTimer;

    function showToast(msg, type = '') {
        let t = document.querySelector('.tp-toast');
        if (!t) {
            t = document.createElement('div');
            t.className = 'tp-toast';
            document.body.appendChild(t);
        }
        t.className = `tp-toast ${type}`;
        t.textContent = msg;
        clearTimeout(toastTimer);
        requestAnimationFrame(() => {
            t.classList.add('show');
            toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
        });
    }

    return {
        switchTab,
        openForm,
        submitForm,
        backToList,
        signatorySearch
    };
})();
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>