<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'App Registration';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
  /* ============================================================
   app_registration.css  –  PerkPayroll App Registration
   ============================================================ */

.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #E5E7EB;background:#fff;overflow-x:auto;scrollbar-width:none;}
.cfg-tabs::-webkit-scrollbar{display:none;}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6B7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px;}
.cfg-tab:hover{color:#111827;}
.cfg-tab.active{color:#2563EB;border-bottom-color:#2563EB;font-weight:600;}

.ar-wrapper {
  background: #fff;
  font-family: 'Segoe UI', Arial, sans-serif;
  color: #1e293b;
  min-height: calc(100vh - 140px);
}

/* ---------- Topbar / breadcrumb ---------- */
.ar-list-topbar, .ar-sub-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 24px;
  border-bottom: 1px solid #E2E8F0;
}
.ar-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13.5px; }
.ar-bc-parent  { color: #64748B; }
.ar-bc-sep     { color: #94A3B8; font-size: 16px; }
.ar-bc-current { font-weight: 600; color: #1e293b; }

/* ---------- Toolbar ---------- */
.ar-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 24px 10px;
}
.ar-search-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1.5px solid #E2E8F0;
  border-radius: 6px;
  padding: 6px 12px;
  width: 280px;
  background: #fff;
  transition: border-color .15s;
}
.ar-search-wrap:focus-within { border-color: #2563EB; }
.ar-search-input {
  border: none; outline: none; flex: 1;
  font-size: 13.5px; color: #1e293b;
  background: transparent; font-family: inherit;
}
.ar-search-input::placeholder { color: #94A3B8; }

.ar-btn-register {
  display: flex; align-items: center; gap: 6px;
  background: #2563EB; color: #fff;
  border: none; border-radius: 6px;
  padding: 9px 18px; font-size: 13.5px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.ar-btn-register > span { font-size: 18px; line-height: 1; }
.ar-btn-register:hover { background: #1D4ED8; }

/* ---------- Table ---------- */
.ar-table-wrap {
  border-top: 1px solid #E2E8F0;
  overflow-x: auto;
}
.ar-table {
  width: 100%; border-collapse: collapse; font-size: 13.5px;
}
.ar-table thead tr { background: #F8FAFC; }
.ar-table th {
  padding: 11px 16px; text-align: left;
  font-size: 12px; font-weight: 700; color: #64748B;
  letter-spacing: .4px; border-bottom: 1px solid #E2E8F0;
  white-space: nowrap;
}
.ar-col-sno { width: 60px; }
.ar-table td {
  padding: 13px 16px; border-bottom: 1px solid #F1F5F9;
  vertical-align: middle;
}
.ar-table tbody tr:last-child td { border-bottom: none; }
.ar-table tbody tr:hover td { background: #F8FAFC; }

/* Toggle switch */
.ar-toggle {
  position: relative; display: inline-block;
  width: 40px; height: 22px; cursor: pointer;
}
.ar-toggle input { display: none; }
.ar-toggle-track {
  position: absolute; inset: 0;
  background: #CBD5E1; border-radius: 20px;
  transition: background .2s;
}
.ar-toggle input:checked ~ .ar-toggle-track { background: #2563EB; }
.ar-toggle-thumb {
  position: absolute;
  top: 3px; left: 3px;
  width: 16px; height: 16px;
  background: #fff; border-radius: 50%;
  transition: transform .2s;
  box-shadow: 0 1px 3px rgba(0,0,0,.2);
}
.ar-toggle input:checked ~ .ar-toggle-thumb { transform: translateX(18px); }

.ar-chevron-btn {
  background: none; border: none; cursor: pointer;
  font-size: 18px; color: #374151; padding: 2px 6px;
  transition: color .1s;
}
.ar-chevron-btn:hover { color: #2563EB; }

.ar-loading-row {
  text-align: center; padding: 40px !important;
  color: #94A3B8; font-size: 13px;
}
.ar-spinner {
  display: inline-block; width: 15px; height: 15px;
  border: 2px solid #E2E8F0; border-top-color: #2563EB;
  border-radius: 50%; animation: ar-spin .6s linear infinite;
  vertical-align: middle; margin-right: 6px;
}
@keyframes ar-spin { to { transform: rotate(360deg); } }

/* ---------- Pagination ---------- */
.ar-pagination {
  display: flex; align-items: center; flex-wrap: wrap;
  gap: 12px; padding: 12px 16px;
  font-size: 12.5px; color: #64748B;
  border-top: 1px solid #F1F5F9;
}
.ar-page-info  { margin-right: auto; }
.ar-page-show  { display: flex; align-items: center; gap: 6px; }
.ar-per-page {
  border: 1.5px solid #CBD5E1; border-radius: 4px;
  padding: 3px 6px; font-size: 12.5px; color: #374151; outline: none;
}
.ar-page-nav   { display: flex; align-items: center; gap: 2px; }
.ar-page-btn {
  width: 28px; height: 28px; border: 1.5px solid #E2E8F0;
  background: #fff; border-radius: 4px; font-size: 12.5px;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: #374151; transition: background .1s, border-color .1s;
}
.ar-page-btn:hover:not(:disabled) { background: #F0F9FF; border-color: #93C5FD; color: #2563EB; }
.ar-page-btn.active { background: #2563EB; border-color: #2563EB; color: #fff; }
.ar-page-btn:disabled { opacity: .4; cursor: not-allowed; }

/* ---------- Form ---------- */
.ar-form-body  { padding: 20px 24px 0; }
.ar-form-grid  { display: grid; gap: 0 32px; }
.ar-grid4      { grid-template-columns: 1fr 1fr 1fr 1fr; }
.ar-fg         { padding-bottom: 20px; position: relative; }
.ar-lbl        { display: block; font-size: 12.5px; color: #374151; margin-bottom: 8px; }

.ar-input {
  width: 100%; box-sizing: border-box;
  border: none; border-bottom: 1.5px solid #CBD5E1;
  background: transparent; padding: 5px 0;
  font-size: 13.5px; color: #1e293b;
  outline: none; font-family: inherit; transition: border-color .15s;
}
.ar-input:focus { border-bottom-color: #2563EB; }
.ar-select {
  appearance: none; cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%2364748B' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 2px center; padding-right: 18px;
}

/* Employee search field */
.ar-emp-field {
  display: flex; align-items: center; gap: 6px;
  border-bottom: 1.5px solid #CBD5E1; padding-bottom: 5px;
  position: relative; transition: border-color .15s;
}
.ar-emp-field:focus-within { border-bottom-color: #2563EB; }
.ar-emp-icon  { flex-shrink: 0; }
.ar-emp-search {
  flex: 1; border: none; background: transparent;
  font-size: 13.5px; color: #1e293b;
  outline: none; font-family: inherit; padding: 0;
}
.ar-emp-search::placeholder { color: #94A3B8; font-size: 13px; }

.ar-emp-dropdown {
  position: absolute; top: calc(100% + 4px); left: 0; right: 0;
  background: #fff; border: 1.5px solid #E2E8F0;
  border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,.1);
  z-index: 100; max-height: 220px; overflow-y: auto;
}
.ar-emp-item { padding: 10px 14px; cursor: pointer; font-size: 13px; transition: background .1s; }
.ar-emp-item:hover { background: #F0F9FF; }
.ar-emp-name { font-weight: 600; color: #0F172A; }
.ar-emp-meta { font-size: 11.5px; color: #64748B; margin-top: 2px; }
.ar-emp-empty { padding: 14px; font-size: 13px; color: #94A3B8; text-align: center; }

/* Radio group */
.ar-radio-group { display: flex; align-items: center; gap: 20px; padding: 6px 0; }
.ar-radio-label {
  display: flex; align-items: center; gap: 6px;
  font-size: 13.5px; color: #374151; cursor: pointer;
}
.ar-radio-label input[type="radio"] { accent-color: #2563EB; width: 16px; height: 16px; cursor: pointer; }

/* Permissions in form (checkboxes) */
.ar-perms-title { font-size: 13.5px; font-weight: 700; color: #374151; margin-bottom: 14px; }
.ar-perms-form-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 6px 24px; margin-bottom: 8px;
}
.ar-perm-check-item {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; color: #1e293b; padding: 6px 0;
}
.ar-perm-check-item input[type="checkbox"] { accent-color: #2563EB; width: 15px; height: 15px; cursor: pointer; flex-shrink: 0; }

/* ---------- Detail view ---------- */
.ar-detail-body { padding: 20px 24px 8px; }
.ar-detail-field { padding-bottom: 18px; }
.ar-detail-label { font-size: 12px; color: #94A3B8; margin-bottom: 6px; }
.ar-detail-value { font-size: 14px; color: #0F172A; font-weight: 500; padding-bottom: 6px; min-height: 22px; }
.ar-detail-line  { border-bottom: 1px solid #E2E8F0; }

.ar-perms-section { padding: 8px 24px 24px; }
.ar-perms-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 6px 24px;
}
.ar-perm-row {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; color: #1e293b; padding: 6px 0;
}
.ar-perm-check { color: #22C55E; font-size: 14px; flex-shrink: 0; }
.ar-perm-blank { width: 14px; flex-shrink: 0; }

/* Edit Details btn */
.ar-btn-edit {
  display: flex; align-items: center; gap: 6px;
  background: none; border: none; color: #2563EB;
  font-size: 13px; font-weight: 600; cursor: pointer;
  font-family: inherit; padding: 4px 8px; border-radius: 4px;
  transition: background .1s;
}
.ar-btn-edit:hover { background: #EFF6FF; }

/* ---------- Form actions ---------- */
.ar-form-actions {
  display: flex; justify-content: flex-end; gap: 12px;
  padding: 20px 24px;
  border-top: 1px solid #F1F5F9;
  margin-top: 8px;
}
.ar-btn-cancel {
  padding: 9px 24px; border: 1.5px solid #CBD5E1;
  background: #fff; color: #64748B; border-radius: 6px;
  font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit;
  transition: background .15s;
}
.ar-btn-cancel:hover { background: #F8FAFC; }
.ar-btn-primary {
  padding: 9px 28px; border: none; background: #2563EB;
  color: #fff; border-radius: 6px; font-size: 13.5px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.ar-btn-primary:hover    { background: #1D4ED8; }
.ar-btn-primary:disabled { background: #93C5FD; cursor: not-allowed; }

/* ---------- Toast ---------- */
.ar-toast {
  position: fixed; bottom: 28px; right: 28px;
  background: #1E293B; color: #fff; padding: 12px 20px;
  border-radius: 8px; font-size: 13.5px; box-shadow: 0 4px 20px rgba(0,0,0,.18);
  z-index: 9999; opacity: 0; transform: translateY(12px);
  transition: opacity .25s, transform .25s; pointer-events: none;
}
.ar-toast.success { background: #166534; }
.ar-toast.error   { background: #991B1B; }
.ar-toast.show    { opacity: 1; transform: translateY(0); }

/* ---------- Responsive ---------- */
@media (max-width: 900px) { .ar-grid4 { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .ar-grid4 { grid-template-columns: 1fr; } }
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

  <div class="ar-wrapper">

    <div id="arViewList">
      <div class="ar-list-topbar">
        <div class="ar-breadcrumb">
          <span class="ar-bc-parent">Others</span>
          <span class="ar-bc-sep">›</span>
          <span class="ar-bc-current">App Registration</span>
        </div>
      </div>

      <div class="ar-toolbar">
        <div class="ar-search-wrap">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <circle cx="6" cy="6" r="4.5" stroke="#94A3B8" stroke-width="1.4"/>
            <path d="M10 10L13 13" stroke="#94A3B8" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
          <input type="text" id="arSearchInput" class="ar-search-input"
                placeholder="Search table items" oninput="AR.filterTable(this.value)">
        </div>
        <button class="ar-btn-register" onclick="AR.openForm()">
          <span>+</span> Register New
        </button>
      </div>

      <div class="ar-table-wrap">
        <table class="ar-table">
          <thead>
            <tr>
              <th class="ar-col-sno">S No</th>
              <th>Code</th>
              <th>Name</th>
              <th>Mode</th>
              <th>Activation Code</th>
              <th>Device Name</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="arTbody">
            <tr><td colspan="8" class="ar-loading-row">
              <span class="ar-spinner"></span> Loading…
            </td></tr>
          </tbody>
        </table>
      </div>

      <div class="ar-pagination">
        <div class="ar-page-info" id="arPageInfo">Showing 0 entries</div>
        <div class="ar-page-show">
          Show
          <select class="ar-per-page" id="arPerPage" onchange="AR.setPerPage(this.value)">
            <option value="10">10</option>
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          entries
        </div>
        <div class="ar-page-nav" id="arPageNav"></div>
      </div>
    </div>

    <div id="arViewForm" style="display:none;">
      <div class="ar-sub-topbar">
        <div class="ar-breadcrumb">
          <span class="ar-bc-parent" style="cursor:pointer;" onclick="AR.backToList()">Others</span>
          <span class="ar-bc-sep">›</span>
          <span class="ar-bc-current">App Registration</span>
        </div>
      </div>

      <div class="ar-form-body">
        <div class="ar-form-grid ar-grid4">

          <div class="ar-fg">
            <label class="ar-lbl">Name</label>
            <div class="ar-emp-field">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none" class="ar-emp-icon">
                <circle cx="5.5" cy="5.5" r="4" stroke="#94A3B8" stroke-width="1.3"/>
                <path d="M9 9L11.5 11.5" stroke="#94A3B8" stroke-width="1.3" stroke-linecap="round"/>
              </svg>
              <input type="text" class="ar-input ar-emp-search" id="fEmpSearch"
                    placeholder="Search by name or #code"
                    oninput="AR.empSearch(this.value)" autocomplete="off">
              <div class="ar-emp-dropdown" id="arEmpDropdown" style="display:none;"></div>
            </div>
          </div>

          <div class="ar-fg">
            <label class="ar-lbl">Mode</label>
            <div class="ar-radio-group">
              <label class="ar-radio-label">
                <input type="radio" name="fMode" id="fModeUser" value="User" checked> User
              </label>
              <label class="ar-radio-label">
                <input type="radio" name="fMode" id="fModeDevice" value="Device"> Device
              </label>
            </div>
          </div>

          <div class="ar-fg">
            <label class="ar-lbl">Capture Photograph</label>
            <select class="ar-input ar-select" id="fCapturePhoto">
              <option value="Required">Required</option>
              <option value="Optional">Optional</option>
              <option value="Not Required">Not Required</option>
            </select>
          </div>

          <div class="ar-fg">
            <label class="ar-lbl">Capture Location</label>
            <select class="ar-input ar-select" id="fCaptureLocation">
              <option value="Required">Required</option>
              <option value="Optional">Optional</option>
              <option value="Not Required">Not Required</option>
            </select>
          </div>

        </div>

        <div class="ar-form-grid ar-grid4" style="margin-top:6px;">
          <div class="ar-fg">
            <label class="ar-lbl">Status</label>
            <select class="ar-input ar-select" id="fStatus">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>

        <div id="arFormPerms" style="display:none;">
          <div class="ar-perms-title" style="margin-top:24px;">App Permissions</div>
          <div class="ar-perms-form-grid" id="arFormPermsGrid"></div>
        </div>

      </div>

      <input type="hidden" id="fEditingId"    value="">
      <input type="hidden" id="fEmployeeId"   value="">
      <input type="hidden" id="fEmployeeName" value="">

      <div class="ar-form-actions">
        <button class="ar-btn-cancel"  onclick="AR.backToList()">Cancel</button>
        <button class="ar-btn-primary" id="arBtnSubmit" onclick="AR.submitForm()">Add</button>
      </div>
    </div>

    <div id="arViewDetail" style="display:none;">
      <div class="ar-sub-topbar">
        <div class="ar-breadcrumb">
          <span class="ar-bc-parent" style="cursor:pointer;" onclick="AR.backToList()">Others</span>
          <span class="ar-bc-sep">›</span>
          <span class="ar-bc-current">App Registration</span>
        </div>
        <button class="ar-btn-edit" onclick="AR.openEditFromDetail()">
          <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
            <path d="M9 1.5L11.5 4L4 11.5H1.5V9L9 1.5Z"
                  stroke="#2563EB" stroke-width="1.3" stroke-linejoin="round"/>
          </svg>
          Edit Details
        </button>
      </div>

      <div class="ar-detail-body">
        <div class="ar-form-grid ar-grid4">
          <div class="ar-detail-field">
            <div class="ar-detail-label">Name</div>
            <div class="ar-detail-value" id="dName"></div>
            <div class="ar-detail-line"></div>
          </div>
          <div class="ar-detail-field">
            <div class="ar-detail-label">Mode</div>
            <div class="ar-detail-value" id="dMode"></div>
            <div class="ar-detail-line"></div>
          </div>
          <div class="ar-detail-field">
            <div class="ar-detail-label">Capture Photograph</div>
            <div class="ar-detail-value" id="dCapturePhoto"></div>
            <div class="ar-detail-line"></div>
          </div>
          <div class="ar-detail-field">
            <div class="ar-detail-label">Capture Location</div>
            <div class="ar-detail-value" id="dCaptureLocation"></div>
            <div class="ar-detail-line"></div>
          </div>
        </div>
        <div class="ar-form-grid ar-grid4" style="margin-top:6px;">
          <div class="ar-detail-field">
            <div class="ar-detail-label">Status</div>
            <div class="ar-detail-value" id="dStatus"></div>
            <div class="ar-detail-line"></div>
          </div>
        </div>
      </div>

      <div class="ar-perms-section">
        <div class="ar-perms-title">App Permissions</div>
        <div class="ar-perms-grid" id="dPermsGrid"></div>
      </div>
    </div>

  </div>

</div>
<script>
const AR = (() => {
  'use strict';

  const API = 'API/app_registration_api.php';
  const $   = id => document.getElementById(id);

  /* ── State ─────────────────────────────────────────────── */
  let allRows      = [];
  let filteredRows = [];
  let allPerms     = [];
  let currentPage  = 1;
  let perPage      = 25;
  let viewingId    = null;
  let editingId    = null;
  let empSelected  = null;
  let empTimer     = null;

  /* ── Init ───────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    loadList();
    loadPermissions();
    document.addEventListener('click', e => {
      if (!e.target.closest('.ar-emp-field')) closeEmpDropdown();
    });
  });

  /* ════════════════════════════════════════════════
     LIST
  ════════════════════════════════════════════════ */
  function loadList() {
    fetch(`${API}?action=list`)
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          allRows = res.data || [];
          filteredRows = allRows;
          renderTable();
        } else showToast(res.message, 'error');
      })
      .catch(() => showToast('Network error.', 'error'));
  }

  function filterTable(q) {
    const lq = q.toLowerCase();
    filteredRows = !lq ? allRows : allRows.filter(r =>
      (r.name||'').toLowerCase().includes(lq) ||
      (r.code||'').toLowerCase().includes(lq) ||
      (r.activation_code||'').toLowerCase().includes(lq) ||
      (r.device_name||'').toLowerCase().includes(lq)
    );
    currentPage = 1;
    renderTable();
  }

  function renderTable() {
    const total  = filteredRows.length;
    const pages  = Math.max(1, Math.ceil(total / perPage));
    currentPage  = Math.min(currentPage, pages);
    const start  = (currentPage - 1) * perPage;
    const slice  = filteredRows.slice(start, start + perPage);

    const tbody  = $('arTbody');
    tbody.innerHTML = '';

    if (!total) {
      tbody.innerHTML = `<tr><td colspan="8" class="ar-loading-row" style="color:#94A3B8;">No records found.</td></tr>`;
      $('arPageInfo').textContent = 'Showing 0 entries';
      $('arPageNav').innerHTML = '';
      return;
    }

    slice.forEach((r, i) => {
      const tr = document.createElement('tr');
      const isActive = r.status === 'Active';
      tr.innerHTML = `
        <td>${start + i + 1}</td>
        <td>${esc(r.code)}</td>
        <td>${esc(r.name)}</td>
        <td>${esc(r.mode)}</td>
        <td>${esc(r.activation_code)}</td>
        <td>${esc(r.device_name || '—')}</td>
        <td>
          <label class="ar-toggle">
            <input type="checkbox" ${isActive ? 'checked' : ''} data-id="${r.id}">
            <div class="ar-toggle-track"></div>
            <div class="ar-toggle-thumb"></div>
          </label>
        </td>
        <td>
          <button class="ar-chevron-btn" data-id="${r.id}" title="View">›</button>
        </td>`;

      tr.querySelector('.ar-toggle input').addEventListener('change', function () {
        toggleStatus(r.id, this);
      });
      tr.querySelector('.ar-chevron-btn').addEventListener('click', () => openDetail(r.id));
      tbody.appendChild(tr);
    });

    $('arPageInfo').textContent =
      `Showing ${start + 1} to ${Math.min(start + perPage, total)} of ${total} entries`;
    renderPageNav(pages);
  }

  function renderPageNav(pages) {
    const nav   = $('arPageNav');
    nav.innerHTML = '';
    const mk = (label, pg, disabled, active) => {
      const b = document.createElement('button');
      b.className = 'ar-page-btn' + (active ? ' active' : '');
      b.textContent = label;
      b.disabled = disabled;
      if (!disabled && !active) b.onclick = () => { currentPage = pg; renderTable(); };
      return b;
    };
    nav.appendChild(mk('«', 1, currentPage === 1));
    nav.appendChild(mk('‹', currentPage - 1, currentPage === 1));
    const s = Math.max(1, currentPage - 2), e = Math.min(pages, s + 4);
    for (let p = s; p <= e; p++) nav.appendChild(mk(p, p, false, p === currentPage));
    nav.appendChild(mk('›', currentPage + 1, currentPage === pages));
    nav.appendChild(mk('»', pages, currentPage === pages));
  }

  function setPerPage(v) { perPage = parseInt(v); currentPage = 1; renderTable(); }

  /* ════════════════════════════════════════════════
     TOGGLE STATUS
  ════════════════════════════════════════════════ */
  function toggleStatus(id, chkEl) {
    const fd = new FormData();
    fd.append('action', 'toggle_status'); fd.append('id', id);
    fetch(API, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          const row = allRows.find(x => x.id == id);
          if (row) row.status = res.status;
          showToast(`Status changed to ${res.status}.`, 'success');
        } else {
          showToast(res.message, 'error');
          chkEl.checked = !chkEl.checked; // revert
        }
      });
  }

  /* ════════════════════════════════════════════════
     DETAIL VIEW
  ════════════════════════════════════════════════ */
  function openDetail(id) {
    viewingId = id;
    fetch(`${API}?action=get&id=${id}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) { showToast(res.message, 'error'); return; }
        const d = res.data;
        $('dName').textContent          = `${d.employee_name} – #${d.employee_code}`;
        $('dMode').textContent          = d.mode;
        $('dCapturePhoto').textContent  = d.capture_photo;
        $('dCaptureLocation').textContent = d.capture_location;
        $('dStatus').textContent        = d.status;

        const grid = $('dPermsGrid');
        grid.innerHTML = '';
        const grantedSet = new Set(d.permissions || []);
        allPerms.forEach(p => {
          const row = document.createElement('div');
          row.className = 'ar-perm-row';
          if (grantedSet.has(p)) {
            row.innerHTML = `<span class="ar-perm-check">✓</span>${esc(p)}`;
          } else {
            row.innerHTML = `<span class="ar-perm-blank"></span>${esc(p)}`;
          }
          grid.appendChild(row);
        });

        showView('arViewDetail');
      })
      .catch(() => showToast('Network error.', 'error'));
  }

  /* ════════════════════════════════════════════════
     OPEN FORM (new)
  ════════════════════════════════════════════════ */
  function openForm() {
    editingId   = null;
    empSelected = null;
    clearForm();
    
    // Yahan Permissions block ko 'block' kar diya taki Naye form me bhi dikhe
    $('arFormPerms').style.display = 'block'; 
    renderFormPerms(new Set()); // Empty set -> koi bhi pre-checked nahi hoga
    
    $('arBtnSubmit').textContent   = 'Add';
    showView('arViewForm');
  }

  /* ════════════════════════════════════════════════
     OPEN EDIT FROM DETAIL
  ════════════════════════════════════════════════ */
  function openEditFromDetail() {
    if (!viewingId) return;
    fetch(`${API}?action=get&id=${viewingId}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) { showToast(res.message, 'error'); return; }
        const d = res.data;
        editingId = d.id;
        empSelected = { id: d.employee_id, name: `${d.employee_name} – #${d.employee_code}` };

        $('fEditingId').value    = d.id;
        $('fEmployeeId').value   = d.employee_id;
        $('fEmployeeName').value = empSelected.name;
        $('fEmpSearch').value    = empSelected.name;

        document.querySelectorAll('input[name="fMode"]').forEach(r => {
          r.checked = r.value === d.mode;
        });
        $('fCapturePhoto').value    = d.capture_photo;
        $('fCaptureLocation').value = d.capture_location;
        $('fStatus').value          = d.status;

        renderFormPerms(new Set(d.permissions || []));
        $('arFormPerms').style.display = 'block';
        $('arBtnSubmit').textContent   = 'Save';
        showView('arViewForm');
      });
  }

  function renderFormPerms(checkedSet) {
    const grid = $('arFormPermsGrid');
    grid.innerHTML = '';
    allPerms.forEach(p => {
      const lbl = document.createElement('label');
      lbl.className = 'ar-perm-check-item';
      const chk = document.createElement('input');
      chk.type    = 'checkbox';
      chk.value   = p;
      chk.checked = checkedSet.has(p);
      lbl.appendChild(chk);
      lbl.appendChild(document.createTextNode(p));
      grid.appendChild(lbl);
    });
  }

  function clearForm() {
    $('fEditingId').value    = '';
    $('fEmployeeId').value   = '';
    $('fEmployeeName').value = '';
    $('fEmpSearch').value    = '';
    document.querySelector('input[name="fMode"][value="User"]').checked = true;
    $('fCapturePhoto').value    = 'Required';
    $('fCaptureLocation').value = 'Required';
    $('fStatus').value          = 'Active';
    $('arFormPermsGrid').innerHTML = '';
  }

  /* ════════════════════════════════════════════════
     EMPLOYEE SEARCH
  ════════════════════════════════════════════════ */
  function empSearch(val) {
    empSelected = null;
    clearTimeout(empTimer);
    if (!val.trim()) { closeEmpDropdown(); return; }
    empTimer = setTimeout(() => {
      fetch(`${API}?action=search_emp&q=${encodeURIComponent(val)}`)
        .then(r => r.json())
        .then(res => { if (res.success) showEmpDropdown(res.data || []); });
    }, 250);
  }

  function showEmpDropdown(list) {
    const dd = $('arEmpDropdown');
    dd.innerHTML = '';
    if (!list.length) {
      dd.innerHTML = '<div class="ar-emp-empty">No employees found.</div>';
    } else {
      list.forEach(e => {
        const item = document.createElement('div');
        item.className = 'ar-emp-item';
        item.innerHTML = `<div class="ar-emp-name">${esc(e.name)} – #${esc(e.employee_code)}</div>
                          <div class="ar-emp-meta">${esc(e.designation||'')}</div>`;
        item.onclick = () => {
          empSelected = { id: e.id, name: `${e.name} – #${e.employee_code}` };
          $('fEmpSearch').value  = empSelected.name;
          $('fEmployeeId').value = e.id;
          closeEmpDropdown();
        };
        dd.appendChild(item);
      });
    }
    dd.style.display = 'block';
  }

  function closeEmpDropdown() { $('arEmpDropdown').style.display = 'none'; }

  /* ════════════════════════════════════════════════
     SUBMIT
  ════════════════════════════════════════════════ */
  function submitForm() {
    const empId = $('fEmployeeId').value;
    if (!empId) { showToast('Please select an employee.', 'error'); return; }

    const btn = $('arBtnSubmit');
    btn.disabled = true;
    btn.textContent = editingId ? 'Saving…' : 'Adding…';

    const fd  = new FormData();
    const act = editingId ? 'update' : 'add';
    fd.append('action', act);
    if (editingId) fd.append('id', editingId);
    fd.append('employee_id',      empId);
    fd.append('mode',             document.querySelector('input[name="fMode"]:checked').value);
    fd.append('capture_photo',    $('fCapturePhoto').value);
    fd.append('capture_location', $('fCaptureLocation').value);
    fd.append('status',           $('fStatus').value);

    const checkedPerms = [...$('arFormPermsGrid').querySelectorAll('input:checked')].map(c => c.value);
    fd.append('permissions', JSON.stringify(checkedPerms));

    fetch(API, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          showToast(res.message, 'success');
          if (res.activation_code) showToast(`Activation Code: ${res.activation_code}`, 'success');
          editingId = null;
          loadList();
          showView('arViewList');
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
     HELPERS
  ════════════════════════════════════════════════ */
  function loadPermissions() {
    fetch(`${API}?action=permissions_list`)
      .then(r => r.json())
      .then(res => { if (res.success) allPerms = res.data || []; });
  }

  function backToList() {
    editingId = null; viewingId = null;
    showView('arViewList');
  }

  function showView(id) {
    ['arViewList','arViewForm','arViewDetail'].forEach(v => {
      $(v).style.display = v === id ? 'block' : 'none';
    });
  }

  function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                          .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  let toastTimer;
  function showToast(msg, type = '') {
    let t = document.querySelector('.ar-toast');
    if (!t) { t = document.createElement('div'); t.className = 'ar-toast'; document.body.appendChild(t); }
    t.className = `ar-toast ${type}`; t.textContent = msg;
    clearTimeout(toastTimer);
    requestAnimationFrame(() => {
      t.classList.add('show');
      toastTimer = setTimeout(() => t.classList.remove('show'), 3400);
    });
  }

  return { filterTable, setPerPage, openForm, openEditFromDetail, submitForm, backToList, empSearch };
})();
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>