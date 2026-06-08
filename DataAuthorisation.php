<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Data Import';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
/* ============================================================
    data_import.css  –  PerkPayroll-style Data Import wizard
    ============================================================ */

.di-wrapper {
    background: #FFF;
    min-height: calc(100vh - 130px);
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #1e293b;
    padding-bottom: 40px;
}

/* ---------- Page header ---------- */
.di-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px 14px;
    background: #fff;
    border-bottom: 1px solid #E2E8F0;
}

.di-page-title {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
    margin: 0;
}

.di-history-link {
    font-size: 13.5px;
    font-weight: 600;
    color: #2563EB;
    text-decoration: none;
}

.di-history-link:hover {
    text-decoration: underline;
}

/* ---------- Step indicator ---------- */
.di-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 36px 24px 28px;
    gap: 0;
    background: #fff;
}

.di-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    min-width: 110px;
}

.di-step-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 2px dashed #CBD5E1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    color: #94A3B8;
    background: #fff;
    transition: all .2s;
    position: relative;
}

.di-step.active .di-step-circle {
    border-color: #2563EB;
    border-style: dashed;
    color: #2563EB;
}

.di-step.done .di-step-circle {
    border: none;
    background: #22C55E;
    color: #fff;
    font-size: 16px;
}

.di-step.done .di-step-circle span::before {
    content: '✓';
}

.di-step.done .di-step-circle span {
    font-size: 0;
}

/* hide number */

.di-step-label {
    font-size: 12.5px;
    color: #94A3B8;
    white-space: nowrap;
}

.di-step.active .di-step-label {
    color: #2563EB;
    font-weight: 600;
}

.di-step.done .di-step-label {
    color: #374151;
}

.di-step-line {
    flex: 1;
    height: 1.5px;
    background: #E2E8F0;
    border-top: 1.5px dashed #CBD5E1;
    margin-bottom: 26px;
    /* align with circles */
    min-width: 60px;
    max-width: 180px;
}

/* ---------- Wizard cards ---------- */
.di-card {
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 28px 32px 24px;
    width: 600px;
    max-width: calc(100% - 48px);
    margin: 30px auto;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .04);
}

.di-card-wide {
    width: 900px;
}

.di-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
    text-align: center;
    margin-bottom: 24px;
}

/* ── Step 1 ── */
.di-step1-body {
    display: flex;
    align-items: center;
    gap: 16px;
    justify-content: center;
}

.di-select {
    flex: 1;
    max-width: 400px;
    border: 1.5px solid #E2E8F0;
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 13.5px;
    color: #374151;
    background: #F8FAFC;
    outline: none;
    cursor: pointer;
    font-family: inherit;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%2364748B' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 32px;
    transition: border-color .15s;
}

.di-select:focus {
    border-color: #2563EB;
}

/* ── Step 2 ── */
.di-upload-area {
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    background: #F8FAFC;
    padding: 2px;
    margin-bottom: 22px;
}

.di-file-row {
    display: flex;
    align-items: center;
    gap: 0;
}

.di-file-label {
    flex: 1;
    padding: 12px 16px;
    font-size: 13.5px;
    color: #94A3B8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.di-file-label.chosen {
    color: #1e293b;
}

.di-btn-choose {
    display: inline-flex;
    align-items: center;
    padding: 10px 20px;
    background: #2563EB;
    color: #fff;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    margin: 4px;
    white-space: nowrap;
    transition: background .15s;
}

.di-btn-choose:hover {
    background: #1D4ED8;
}

.di-help-section {
    text-align: center;
    margin-bottom: 4px;
}

.di-help-text {
    font-size: 13px;
    color: #64748B;
    margin-bottom: 6px;
}

.di-help-links {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.di-link {
    font-size: 13px;
    color: #2563EB;
    text-decoration: none;
    cursor: pointer;
}

.di-link:hover {
    text-decoration: underline;
}

.di-link-sep {
    color: #CBD5E1;
}

/* ── Preview table ── */
.di-preview-info {
    font-size: 13px;
    color: #64748B;
    margin-bottom: 12px;
    text-align: center;
}

.di-preview-table-wrap {
    overflow-x: auto;
    max-height: 320px;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    margin-bottom: 20px;
}

.di-preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}

.di-preview-table th {
    background: #F1F5F9;
    padding: 9px 12px;
    text-align: left;
    font-weight: 700;
    color: #475569;
    white-space: nowrap;
    position: sticky;
    top: 0;
    border-bottom: 1px solid #E2E8F0;
}

.di-preview-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #F1F5F9;
    color: #374151;
    white-space: nowrap;
}

.di-preview-table tbody tr:last-child td {
    border-bottom: none;
}

.di-preview-table tbody tr:hover td {
    background: #F8FAFC;
}

/* ── Step 4 states ── */
.di-import-progress,
.di-import-done,
.di-import-error {
    text-align: center;
    padding: 24px 0;
}

.di-big-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #E2E8F0;
    border-top-color: #2563EB;
    border-radius: 50%;
    animation: di-spin .7s linear infinite;
    margin: 0 auto 18px;
}

@keyframes di-spin {
    to {
        transform: rotate(360deg);
    }
}

.di-import-progress p {
    font-size: 14px;
    color: #64748B;
}

.di-done-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #22C55E;
    color: #fff;
    font-size: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.di-done-msg {
    font-size: 14px;
    color: #166534;
    margin-bottom: 20px;
}

.di-error-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #EF4444;
    color: #fff;
    font-size: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.di-error-msg {
    font-size: 14px;
    color: #991B1B;
    margin-bottom: 20px;
}

.di-import-summary {
    font-size: 13.5px;
    color: #475569;
    text-align: center;
    margin-bottom: 6px;
}

/* ---------- Shared footer actions ---------- */
.di-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid #F1F5F9;
}

.di-btn-next {
    padding: 9px 28px;
    background: #2563EB;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}

.di-btn-next:hover {
    background: #1D4ED8;
}

.di-btn-next:disabled {
    background: #93C5FD;
    cursor: not-allowed;
}

.di-btn-back {
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

.di-btn-back:hover {
    background: #F8FAFC;
    border-color: #94A3B8;
}

/* ---------- History view ---------- */
.di-history-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 18px 24px 12px;
    background: #fff;
    border-bottom: 1px solid #E2E8F0;
}

.di-history-title {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
    margin: 0 0 4px;
}

.di-history-note {
    font-size: 12px;
    color: #94A3B8;
    margin: 0;
}

.di-history-actions {
    display: flex;
    gap: 10px;
}

.di-btn-outline {
    padding: 8px 20px;
    border: 1.5px solid #CBD5E1;
    background: #fff;
    color: #374151;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}

.di-btn-outline:hover {
    background: #F8FAFC;
    border-color: #94A3B8;
}

.di-history-search-wrap {
    display: flex;
    align-items: center;
    margin: 16px 24px;
    border: 1.5px solid #E2E8F0;
    border-radius: 6px;
    padding: 0 12px;
    background: #fff;
}

.di-search-icon {
    flex-shrink: 0;
    margin-right: 8px;
}

.di-history-search {
    flex: 1;
    border: none;
    outline: none;
    padding: 9px 0;
    font-size: 13.5px;
    color: #1e293b;
    font-family: inherit;
    background: transparent;
}

.di-history-table-wrap {
    margin: 0 24px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
}

.di-history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.di-history-table thead tr {
    background: #F8FAFC;
}

.di-history-table th {
    padding: 11px 16px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 700;
    color: #64748B;
    letter-spacing: .4px;
    border-bottom: 1px solid #E2E8F0;
    white-space: nowrap;
}

.di-history-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
}

.di-history-table tbody tr:last-child td {
    border-bottom: none;
}

.di-history-table tbody tr:hover td {
    background: #F8FAFC;
}

.di-status-completed {
    color: #16A34A;
    font-weight: 600;
}

.di-status-error {
    color: #DC2626;
    font-weight: 600;
}

.di-status-progress {
    color: #D97706;
    font-weight: 600;
}

.di-file-link {
    color: #2563EB;
    text-decoration: none;
    font-size: 13px;
}

.di-file-link:hover {
    text-decoration: underline;
}

.di-info-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #2563EB;
    font-size: 17px;
    padding: 0 4px;
    vertical-align: middle;
    line-height: 1;
}

.di-info-btn:hover {
    color: #1D4ED8;
}

.di-loading-row {
    text-align: center;
    padding: 36px !important;
    color: #94A3B8;
    font-size: 13px;
}

.di-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid #E2E8F0;
    border-top-color: #2563EB;
    border-radius: 50%;
    animation: di-spin .6s linear infinite;
    vertical-align: middle;
    margin-right: 6px;
}

/* ---------- Modals ---------- */
.di-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .35);
    z-index: 7000;
}

.di-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 12px;
    width: 480px;
    max-width: 96vw;
    z-index: 7001;
    box-shadow: 0 12px 40px rgba(0, 0, 0, .18);
}

.di-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px 14px;
    border-bottom: 1px solid #E2E8F0;
}

.di-modal-title {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
}

.di-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 17px;
    color: #EF4444;
    padding: 2px 8px;
    border-radius: 4px;
    transition: background .1s;
}

.di-modal-close:hover {
    background: #FEF2F2;
}

.di-modal-body {
    padding: 20px 22px 24px;
    font-size: 13.5px;
    color: #374151;
    line-height: 1.65;
}

.di-modal-body ul {
    margin: 8px 0 0 16px;
    padding: 0;
}

.di-modal-body li {
    margin-bottom: 6px;
}

/* ---------- Toast ---------- */
.di-toast {
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

.di-toast.success {
    background: #166534;
}

.di-toast.error {
    background: #991B1B;
}

.di-toast.show {
    opacity: 1;
    transform: translateY(0);
}

/* ---------- Responsive ---------- */
@media (max-width: 640px) {
    .di-step-label {
        font-size: 11px;
    }

    .di-card {
        padding: 20px 16px;
    }

    .di-step1-body {
        flex-direction: column;
    }

    .di-select {
        max-width: 100%;
    }
}
</style>

<div class="di-wrapper">

    <div class="di-page-header">
        <h1 class="di-page-title">Data Import</h1>
        <a href="#" class="di-history-link" onclick="DI.showHistory(event)">Import History</a>
    </div>

    <div id="viewWizard">

        <div class="di-steps" id="diSteps">
            <div class="di-step active" data-step="1">
                <div class="di-step-circle" id="sc1"><span>1</span></div>
                <div class="di-step-label">Choose Data Type</div>
            </div>
            <div class="di-step-line"></div>
            <div class="di-step" data-step="2">
                <div class="di-step-circle" id="sc2"><span>2</span></div>
                <div class="di-step-label">Upload File</div>
            </div>
            <div class="di-step-line"></div>
            <div class="di-step" data-step="3">
                <div class="di-step-circle" id="sc3"><span>3</span></div>
                <div class="di-step-label">Preview</div>
            </div>
            <div class="di-step-line"></div>
            <div class="di-step" data-step="4">
                <div class="di-step-circle" id="sc4"><span>4</span></div>
                <div class="di-step-label">Import</div>
            </div>
        </div>

        <div class="di-card" id="step1">
            <div class="di-card-title">Choose data type to import</div>
            <div class="di-step1-body">
                <select class="di-select" id="diDataType">
                    <option value="" disabled selected>Choose the data type you want to import</option>
                    <option value="Additional Fields">Additional Fields</option>
                    <option value="Advance Payment/Deduction">Advance Payment/Deduction</option>
                    <option value="Approve Leaves">Approve Leaves</option>
                    <option value="Assets">Assets</option>
                    <option value="Assign Shift">Assign Shift</option>
                    <option value="Day Status">Day Status</option>
                    <option value="Employee">Employee</option>
                    <option value="Employee Images">Employee Images</option>
                    <option value="Employee Statutory Details">Employee Statutory Details</option>
                    <option value="Leave Accumulation">Leave Accumulation</option>
                    <option value="Leave Application">Leave Application</option>
                    <option value="Loan">Loan</option>
                    <option value="Pay Structure">Pay Structure</option>
                    <option value="Payroll Variables">Payroll Variables</option>
                    <option value="Payslips">Payslips</option>
                    <option value="Reimbursement">Reimbursement</option>
                    <option value="Training">Training</option>
                </select>
                <button class="di-btn-next" onclick="DI.step1Next()">Next</button>
            </div>
        </div>

        <div class="di-card" id="step2" style="display:none;">
            <div class="di-card-title" id="s2Title">Upload File</div>
            <div class="di-upload-area">
                <div class="di-file-row">
                    <div class="di-file-label" id="diFileName">No file chosen.</div>
                    <label class="di-btn-choose" for="diFileInput">Choose file</label>
                    <input type="file" id="diFileInput" accept=".csv,.xlsx,.xls" style="display:none;"
                        onchange="DI.onFileChosen(this)">
                </div>
            </div>
            <div class="di-help-section">
                <div class="di-help-text">Need help getting started?</div>
                <div class="di-help-links">
                    <a href="#" class="di-link" onclick="DI.viewInstructions(event)">View Instructions</a>
                    <span class="di-link-sep">|</span>
                    <a href="#" class="di-link" onclick="DI.downloadTemplate(event)">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                            style="vertical-align:-2px;margin-right:3px;">
                            <path d="M7 1v8M4 6l3 3 3-3" stroke="#2563EB" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path d="M2 11h10" stroke="#2563EB" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        Download Template
                    </a>
                </div>
            </div>
            <div class="di-footer-actions">
                <button class="di-btn-back" onclick="DI.goStep(1)">Back</button>
                <button class="di-btn-next" onclick="DI.step2Next()">Next</button>
            </div>
        </div>

        <div class="di-card di-card-wide" id="step3" style="display:none;">
            <div class="di-card-title" id="s3Title">Preview</div>
            <div class="di-preview-info" id="diPreviewInfo"></div>
            <div class="di-preview-table-wrap" id="diPreviewTableWrap">
            </div>
            <div class="di-footer-actions">
                <button class="di-btn-back" onclick="DI.goStep(2)">Back</button>
                <button class="di-btn-next" onclick="DI.step3Next()">Next</button>
            </div>
        </div>

        <div class="di-card" id="step4" style="display:none;">
            <div class="di-import-state" id="diImportState">
                <div id="importIdle">
                    <div class="di-card-title">Ready to Import</div>
                    <p class="di-import-summary" id="diImportSummary"></p>
                    <div class="di-footer-actions">
                        <button class="di-btn-back" onclick="DI.goStep(3)">Back</button>
                        <button class="di-btn-next" id="btnStartImport" onclick="DI.startImport()">Import</button>
                    </div>
                </div>
                <div id="importProcessing" style="display:none;" class="di-import-progress">
                    <div class="di-big-spinner"></div>
                    <p>Importing data, please wait…</p>
                </div>
                <div id="importDone" style="display:none;" class="di-import-done">
                    <div class="di-done-icon">✓</div>
                    <p class="di-done-msg" id="diDoneMsg">Import completed successfully.</p>
                    <button class="di-btn-next" onclick="DI.newImport()">Start New Import</button>
                </div>
                <div id="importError" style="display:none;" class="di-import-error">
                    <div class="di-error-icon">✕</div>
                    <p class="di-error-msg" id="diErrorMsg">Import failed.</p>
                    <div class="di-footer-actions" style="justify-content:center;">
                        <button class="di-btn-back" onclick="DI.goStep(3)">Back</button>
                        <button class="di-btn-next" onclick="DI.newImport()">Start New Import</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="viewHistory" style="display:none;">
        <div class="di-history-header">
            <div>
                <h2 class="di-history-title">Import History</h2>
                <p class="di-history-note">Note: Only the last 30 days of history can be viewed.</p>
            </div>
            <div class="di-history-actions">
                <button class="di-btn-outline" onclick="DI.refreshHistory()">Refresh</button>
                <button class="di-btn-outline" onclick="DI.backFromHistory()">Back</button>
            </div>
        </div>

        <div class="di-history-search-wrap">
            <span class="di-search-icon">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <circle cx="6" cy="6" r="4.5" stroke="#94A3B8" stroke-width="1.4" />
                    <path d="M10 10L13 13" stroke="#94A3B8" stroke-width="1.4" stroke-linecap="round" />
                </svg>
            </span>
            <input type="text" class="di-history-search" id="diHistorySearch" placeholder="Search imports..."
                oninput="DI.filterHistory(this.value)">
        </div>

        <div class="di-history-table-wrap">
            <table class="di-history-table" id="diHistoryTable">
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>IMPORT TYPE</th>
                        <th>IMPORT NAME</th>
                        <th>FILE UPLOADED</th>
                        <th>STATUS</th>
                    </tr>
                </thead>
                <tbody id="diHistoryTbody">
                    <tr>
                        <td colspan="5" class="di-loading-row">
                            <span class="di-spinner"></span> Loading…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="di-overlay" id="diOverlay" onclick="DI.closeErrorModal()" style="display:none;"></div>
<div class="di-modal" id="diErrorModal" style="display:none;">
    <div class="di-modal-header">
        <span class="di-modal-title">Error List</span>
        <button class="di-modal-close" onclick="DI.closeErrorModal()">✕</button>
    </div>
    <div class="di-modal-body" id="diErrorModalBody"></div>
</div>

<div class="di-overlay" id="diInstrOverlay" onclick="DI.closeInstrModal()" style="display:none;"></div>
<div class="di-modal" id="diInstrModal" style="display:none;">
    <div class="di-modal-header">
        <span class="di-modal-title">Import Instructions</span>
        <button class="di-modal-close" onclick="DI.closeInstrModal()">✕</button>
    </div>
    <div class="di-modal-body" id="diInstrBody">
        <ul>
            <li>Ensure your file is in .csv or .xlsx format.</li>
            <li>Do not change or remove the header rows from the template.</li>
            <li>Mandatory fields must be completely filled out.</li>
            <li>Verify dates are formatted properly (YYYY-MM-DD).</li>
        </ul>
    </div>
</div>

<input type="hidden" id="diHiddenType" value="">
<input type="hidden" id="diHiddenRows" value="">
<input type="hidden" id="diHiddenFileName" value="">

<script>
const API_URL = 'API/data_import_api.php'; // <-- UPDATE THIS to the path of your PHP file

const DI = {
    selectedDataType: '',
    uploadedFile: null,
    savedFileName: '', // Returned by the backend during upload
    parsedData: [],
    historyData: [],

    // ==== Navigation & Wizard ====
    goStep: function(stepNum) {
        document.querySelectorAll('.di-card').forEach(card => card.style.display = 'none');

        const targetCard = document.getElementById('step' + stepNum);
        if (targetCard) targetCard.style.display = 'block';

        document.querySelectorAll('.di-step').forEach(stepEl => {
            const elStepNum = parseInt(stepEl.getAttribute('data-step'));
            stepEl.classList.remove('active', 'done');

            if (elStepNum < stepNum) {
                stepEl.classList.add('done');
            } else if (elStepNum === stepNum) {
                stepEl.classList.add('active');
            }
        });
    },

    step1Next: function() {
        const selectEl = document.getElementById('diDataType');
        if (!selectEl.value) {
            alert('Please select a data type to proceed.');
            return;
        }
        this.selectedDataType = selectEl.value;
        document.getElementById('diHiddenType').value = this.selectedDataType;
        this.goStep(2);
    },

    onFileChosen: function(input) {
        if (input.files && input.files.length > 0) {
            this.uploadedFile = input.files[0];
            const label = document.getElementById('diFileName');
            label.textContent = this.uploadedFile.name;
            label.classList.add('chosen');
            document.getElementById('diHiddenFileName').value = this.uploadedFile.name;
        }
    },

    // ==== API: Upload & Preview ====
    step2Next: async function() {
        if (!this.uploadedFile) {
            await Swal.fire({
                icon: 'warning',
                title: 'No File Selected',
                text: 'Please choose a file to upload.',
                confirmButtonText: 'OK'
            });
            return;
        }

        const btnNext = document.querySelector('#step2 .di-btn-next');
        const originalText = btnNext.innerText;
        btnNext.innerText = 'Uploading...';
        btnNext.disabled = true;

        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('type', this.selectedDataType);
        formData.append('file', this.uploadedFile);

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                this.savedFileName = data.saved_name;
                this.renderPreview(data.headers, data.preview, data.total_rows);
                this.goStep(3);
            } else {
                alert(data.message || 'Upload failed.');
            }
        } catch (err) {
            console.error(err);
            alert('Network error occurred during upload.');
        } finally {
            btnNext.innerText = originalText;
            btnNext.disabled = false;
        }
    },

    renderPreview: function(headers, rows, totalRows) {
        let html = '<table class="di-preview-table"><thead><tr>';

        // Render headers
        if (headers && headers.length) {
            headers.forEach(h => {
                html += `<th>${h || 'N/A'}</th>`;
            });
        }
        html += '</tr></thead><tbody>';

        // Render body
        if (rows && rows.length) {
            rows.forEach(row => {
                html += '<tr>';
                row.forEach(cell => {
                    html += `<td>${cell || ''}</td>`;
                });
                html += '</tr>';
            });
        } else {
            html +=
                `<tr><td colspan="${headers.length}" style="text-align:center;">No data found in file.</td></tr>`;
        }
        html += '</tbody></table>';

        document.getElementById('diPreviewTableWrap').innerHTML = html;
        document.getElementById('diHiddenRows').value = totalRows;
        document.getElementById('diPreviewInfo').innerText =
            `Showing preview of first ${rows.length} rows. Total detected records: ${totalRows}`;
    },

    step3Next: function() {
        const totalRows = document.getElementById('diHiddenRows').value;
        document.getElementById('diImportSummary').innerText =
            `You are about to import ${totalRows} records into the '${this.selectedDataType}' module.`;
        this.goStep(4);
    },

    // ==== API: Execute Import ====
    startImport: async function() {
        document.getElementById('importIdle').style.display = 'none';
        document.getElementById('importProcessing').style.display = 'block';

        const formData = new FormData();
        formData.append('action', 'import');
        formData.append('type', this.selectedDataType);
        formData.append('saved_name', this.savedFileName);
        formData.append('orig_name', this.uploadedFile.name);

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            document.getElementById('importProcessing').style.display = 'none';

            if (data.success) {
                document.getElementById('importDone').style.display = 'block';
                document.getElementById('diDoneMsg').innerText = data.message;
            } else {
                document.getElementById('importError').style.display = 'block';
                document.getElementById('diErrorMsg').innerText = data.message;

                // Show detailed errors if provided by the backend
                if (data.errors && data.errors.length > 0) {
                    console.error("Import Errors: ", data.errors);
                    // Optionally: trigger the error modal here to list them out
                }
            }
        } catch (err) {
            console.error(err);
            document.getElementById('importProcessing').style.display = 'none';
            document.getElementById('importError').style.display = 'block';
            document.getElementById('diErrorMsg').innerText = 'A critical network or server error occurred.';
        }
    },

    newImport: function() {
        this.uploadedFile = null;
        this.savedFileName = '';
        document.getElementById('diDataType').value = '';
        document.getElementById('diFileInput').value = '';
        document.getElementById('diFileName').textContent = 'No file chosen.';
        document.getElementById('diFileName').classList.remove('chosen');

        document.getElementById('importIdle').style.display = 'block';
        document.getElementById('importProcessing').style.display = 'none';
        document.getElementById('importDone').style.display = 'none';
        document.getElementById('importError').style.display = 'none';

        this.goStep(1);
    },

    // ==== API: History View ====
    showHistory: function(e) {
        if (e) e.preventDefault();
        document.getElementById('viewWizard').style.display = 'none';
        document.getElementById('viewHistory').style.display = 'block';
        this.refreshHistory();
    },

    backFromHistory: function() {
        document.getElementById('viewHistory').style.display = 'none';
        document.getElementById('viewWizard').style.display = 'block';
    },

    refreshHistory: async function() {
        const tbody = document.getElementById('diHistoryTbody');
        tbody.innerHTML =
            '<tr><td colspan="5" class="di-loading-row"><span class="di-spinner"></span> Loading…</td></tr>';

        try {
            const response = await fetch(`${API_URL}?action=history`);
            const data = await response.json();

            if (data.success) {
                this.historyData = data.data;
                this.renderHistoryTable();
            } else {
                tbody.innerHTML =
                    `<tr><td colspan="5" style="text-align:center; color:#EF4444;">${data.message || 'Failed to load history.'}</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML =
                `<tr><td colspan="5" style="text-align:center; color:#EF4444;">Network error fetching history.</td></tr>`;
        }
    },

    renderHistoryTable: function(filterText = '') {
        const tbody = document.getElementById('diHistoryTbody');
        tbody.innerHTML = '';

        const filtered = this.historyData.filter(item => {
            const ft = filterText.toLowerCase();
            const type = (item.import_type || '').toLowerCase();
            const name = (item.import_name || '').toLowerCase();
            return type.includes(ft) || name.includes(ft);
        });

        if (filtered.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="5" style="text-align:center; color:#94A3B8; padding: 20px;">No history records found.</td></tr>';
            return;
        }

        filtered.forEach(item => {
            let statusClass = 'di-status-progress';
            if (item.status === 'Completed') statusClass = 'di-status-completed';
            if (item.status === 'Error') statusClass = 'di-status-error';

            // Formatting the names based on your backend output (UploadEmployee -> Employee)
            const friendlyType = item.import_type.replace('Upload', '');

            const tr = document.createElement('tr');
            tr.innerHTML = `
        <td>${item.date_fmt}</td>
        <td>${friendlyType}</td>
        <td>${item.import_name}</td>
        <td>${item.file_uploaded}</td>
        <td class="${statusClass}">${item.status}</td>
      `;
            tbody.appendChild(tr);
        });
    },

    filterHistory: function(val) {
        this.renderHistoryTable(val);
    },

    // ==== API: Helpers & Modals ====
    downloadTemplate: function(e) {
        if (e) e.preventDefault();
        if (!this.selectedDataType) {
            alert("Please select a Data Type on Step 1 first to download its template.");
            return;
        }
        // Direct browser to the download endpoint
        window.location.href = `${API_URL}?action=template&type=${encodeURIComponent(this.selectedDataType)}`;
    },

    viewInstructions: async function(e) {
        if (e) e.preventDefault();
        if (!this.selectedDataType) {
            alert("Please select a Data Type on Step 1 first.");
            return;
        }

        document.getElementById('diInstrBody').innerHTML =
            '<div style="text-align:center; padding: 20px;"><span class="di-spinner"></span> Loading...</div>';
        document.getElementById('diInstrOverlay').style.display = 'block';
        document.getElementById('diInstrModal').style.display = 'block';

        try {
            const response = await fetch(
                `${API_URL}?action=instructions&type=${encodeURIComponent(this.selectedDataType)}`);
            const data = await response.json();

            if (data.success) {
                document.getElementById('diInstrBody').innerHTML = data.html;
            } else {
                document.getElementById('diInstrBody').innerHTML = `<p style="color:red;">${data.message}</p>`;
            }
        } catch (err) {
            document.getElementById('diInstrBody').innerHTML =
                `<p style="color:red;">Failed to load instructions.</p>`;
        }
    },

    closeInstrModal: function() {
        document.getElementById('diInstrOverlay').style.display = 'none';
        document.getElementById('diInstrModal').style.display = 'none';
    },

    closeErrorModal: function() {
        document.getElementById('diOverlay').style.display = 'none';
        document.getElementById('diErrorModal').style.display = 'none';
    }
};
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>