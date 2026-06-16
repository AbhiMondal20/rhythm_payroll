<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Additional Fields';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
    /* ============================================================
   additional_fields.css  –  PerkPayroll Additional Fields
   ============================================================ */

.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #E5E7EB;background:#fff;overflow-x:auto;scrollbar-width:none;}
.cfg-tabs::-webkit-scrollbar{display:none;}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6B7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px;}
.cfg-tab:hover{color:#111827;}
.cfg-tab.active{color:#2563EB;border-bottom-color:#2563EB;font-weight:600;}

/* ---------- Wrapper ---------- */
.af-wrapper {
  background: #fff;
  font-family: 'Segoe UI', Arial, sans-serif;
  color: #1e293b;
  min-height: calc(100vh - 140px);
}

/* ---------- Top bar ---------- */
.af-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 24px;
  border-bottom: 1px solid #E2E8F0;
}
.af-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13.5px; }
.af-bc-parent  { color: #64748B; font-weight: 500; }
.af-bc-sep     { color: #94A3B8; font-size: 16px; }
.af-bc-current { font-weight: 600; color: #1e293b; }

.af-btn-add {
  display: flex; align-items: center; gap: 6px;
  background: #2563EB; color: #fff;
  border: none; border-radius: 6px;
  padding: 9px 18px; font-size: 13.5px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.af-btn-add > span { font-size: 18px; line-height: 1; }
.af-btn-add:hover  { background: #1D4ED8; }

/* ---------- Empty state ---------- */
.af-empty {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  padding: 90px 20px;
}
.af-empty-art  { margin-bottom: 18px; }
.af-empty-text { font-size: 13.5px; color: #94A3B8; margin: 0; }

/* ---------- Table view ---------- */
.af-table-view { padding: 14px 0 0; }

/* Search bar */
.af-search-wrap {
  display: flex; align-items: center; gap: 8px;
  border: 1.5px solid #E2E8F0; border-radius: 6px;
  padding: 7px 12px;
  width: 240px; background: #fff; margin: 0 24px 14px;
  transition: border-color .15s;
}
.af-search-wrap:focus-within { border-color: #2563EB; }
.af-search-input {
  border: none; outline: none; flex: 1;
  font-size: 13.5px; color: #1e293b;
  background: transparent; font-family: inherit;
}
.af-search-input::placeholder { color: #94A3B8; }

/* Table */
.af-table-wrap { border-top: 1px solid #E2E8F0; overflow-x: auto; }
.af-table {
  width: 100%; border-collapse: collapse; font-size: 13.5px;
}
.af-table thead tr { background: #F1F5F9; }
.af-table th {
  padding: 11px 16px; text-align: left;
  font-size: 12px; font-weight: 700; color: #64748B;
  letter-spacing: .4px; border-bottom: 1px solid #E2E8F0;
  white-space: nowrap;
}
.af-col-sno     { width: 72px; }
.af-col-entity  { width: 200px; }
.af-col-name    { width: 280px; }
.af-col-type    { width: 200px; }
.af-col-regex   { }
.af-col-actions { width: 200px; }

.af-table td {
  padding: 12px 16px; border-bottom: 1px solid #F1F5F9;
  vertical-align: middle; color: #1e293b;
}
.af-table tbody tr:last-child td { border-bottom: none; }
.af-table tbody tr:hover:not(.af-inline-row) td { background: #F8FAFC; }

/* ── Inline add/edit row ── */
.af-inline-row td { padding: 10px 16px; background: #fff; }
.af-inline-row:hover td { background: #fff !important; }

/* Entity type native select (underline style) */
.af-entity-select {
  border: none; border-bottom: 1.5px solid #CBD5E1;
  background: transparent; padding: 4px 20px 4px 0;
  font-size: 13.5px; color: #1e293b;
  outline: none; font-family: inherit;
  cursor: pointer; width: 100%;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%2364748B' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 2px center;
  transition: border-color .15s;
}
.af-entity-select:focus { border-bottom-color: #2563EB; }

/* Field name input */
.af-field-input {
  border: none; border-bottom: 1.5px solid #CBD5E1;
  background: transparent; padding: 4px 0;
  font-size: 13.5px; color: #1e293b;
  outline: none; font-family: inherit; width: 100%;
  transition: border-color .15s;
}
.af-field-input:focus { border-bottom-color: #2563EB; }

/* ── Custom Field Type dropdown ── */
.af-ft-trigger {
  display: flex; align-items: center; justify-content: space-between;
  border: none; border-bottom: 1.5px solid #CBD5E1;
  background: transparent; padding: 4px 2px;
  font-size: 13.5px; color: #1e293b;
  cursor: pointer; width: 100%;
  font-family: inherit; transition: border-color .15s;
  user-select: none;
}
.af-ft-trigger.open,
.af-ft-trigger:focus { border-bottom-color: #2563EB; outline: none; }
.af-ft-arrow { flex-shrink: 0; }

/* Dropdown panel (portal, appended to body) */
.af-ft-dropdown {
  position: fixed;
  background: #fff;
  border: 1.5px solid #E2E8F0;
  border-radius: 6px;
  box-shadow: 0 6px 20px rgba(0,0,0,.12);
  z-index: 9000;
  min-width: 160px;
  overflow: hidden;
}
.af-ft-item {
  padding: 10px 16px;
  font-size: 13.5px;
  color: #374151;
  cursor: pointer;
  transition: background .1s;
}
.af-ft-item:hover       { background: #F0F9FF; color: #2563EB; }
.af-ft-item.af-ft-selected { background: #2563EB; color: #fff; }

/* Regex input */
.af-regex-input {
  border: none; border-bottom: 1.5px solid #CBD5E1;
  background: transparent; padding: 4px 0;
  font-size: 13px; color: #1e293b;
  outline: none; font-family: monospace; width: 100%;
  transition: border-color .15s;
}
.af-regex-input:focus { border-bottom-color: #2563EB; }

/* Inline action buttons */
.af-inline-actions { display: flex; justify-content: flex-end; gap: 10px; }
.af-btn-save {
  padding: 7px 22px; background: #2563EB; color: #fff;
  border: none; border-radius: 6px; font-size: 13px;
  font-weight: 600; cursor: pointer; font-family: inherit;
  transition: background .15s;
}
.af-btn-save:hover    { background: #1D4ED8; }
.af-btn-save:disabled { background: #93C5FD; cursor: not-allowed; }
.af-btn-cancel-inline {
  padding: 7px 18px; background: #fff;
  border: 1.5px solid #CBD5E1; color: #64748B;
  border-radius: 6px; font-size: 13px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.af-btn-cancel-inline:hover { background: #F8FAFC; }

/* Row action buttons (edit/delete on existing rows) */
.af-row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
.af-icon-btn {
  width: 30px; height: 30px;
  border: 1.5px solid #E2E8F0; background: #fff;
  border-radius: 6px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background .1s, border-color .1s;
}
.af-icon-btn:hover { background: #F0F9FF; border-color: #93C5FD; }
.af-icon-btn.del:hover { background: #FEF2F2; border-color: #FCA5A5; }

/* ---------- Pagination ---------- */
.af-pagination {
  display: flex; align-items: center; flex-wrap: wrap;
  gap: 12px; padding: 12px 16px;
  font-size: 12.5px; color: #64748B;
  border-top: 1px solid #F1F5F9;
}
.af-page-info  { margin-right: auto; }
.af-page-show  { display: flex; align-items: center; gap: 6px; }
.af-per-page {
  border: 1.5px solid #CBD5E1; border-radius: 4px;
  padding: 3px 6px; font-size: 12.5px; color: #374151; outline: none;
}
.af-page-nav   { display: flex; align-items: center; gap: 2px; }
.af-page-btn {
  width: 28px; height: 28px; border: 1.5px solid #E2E8F0;
  background: #fff; border-radius: 4px; font-size: 12.5px;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: #374151; transition: background .1s, border-color .1s;
}
.af-page-btn:hover:not(:disabled) { background: #F0F9FF; border-color: #93C5FD; color: #2563EB; }
.af-page-btn.active { background: #2563EB; border-color: #2563EB; color: #fff; }
.af-page-btn:disabled { opacity: .4; cursor: not-allowed; }

/* ---------- Toast ---------- */
.af-toast {
  position: fixed; bottom: 28px; right: 28px;
  background: #1E293B; color: #fff; padding: 12px 20px;
  border-radius: 8px; font-size: 13.5px; box-shadow: 0 4px 20px rgba(0,0,0,.18);
  z-index: 9999; opacity: 0; transform: translateY(12px);
  transition: opacity .25s, transform .25s; pointer-events: none;
}
.af-toast.success { background: #166534; }
.af-toast.error   { background: #991B1B; }
.af-toast.show    { opacity: 1; transform: translateY(0); }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
    <h1 class="page-title">Configuration</h1>
</div>

<!-- Config Tab Bar -->
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
</div>

<div class="af-wrapper">

  <!-- ── Top bar: breadcrumb + Add button ── -->
  <div class="af-topbar">
    <div class="af-breadcrumb">
      <span class="af-bc-parent">Others</span>
      <span class="af-bc-sep">›</span>
      <span class="af-bc-current">Additional Fields</span>
    </div>
    <button class="af-btn-add" id="afBtnAdd" onclick="AF.addRow()">
      <span>+</span> Add New Field
    </button>
  </div>

  <!-- ══════════════════════════
       EMPTY STATE
  ══════════════════════════ -->
  <div class="af-empty" id="afEmpty">
    <div class="af-empty-art">
      <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="50" cy="50" r="50" fill="#EEF2FF"/>
        <rect x="20" y="18" width="60" height="64" rx="5" fill="#CBD5E1"/>
        <rect x="20" y="18" width="60" height="16" rx="5" fill="#94A3B8"/>
        <rect x="28" y="42" width="30" height="4" rx="2" fill="#3B82F6"/>
        <rect x="28" y="52" width="22" height="4" rx="2" fill="#3B82F6"/>
        <rect x="28" y="62" width="16" height="4" rx="2" fill="#93C5FD"/>
        <!-- dots in header bar -->
        <circle cx="29" cy="26" r="2" fill="#fff" opacity=".6"/>
        <circle cx="36" cy="26" r="2" fill="#fff" opacity=".6"/>
      </svg>
    </div>
    <p class="af-empty-text">There are No Additional Fields!</p>
  </div>

  <!-- ══════════════════════════
       TABLE VIEW
  ══════════════════════════ -->
  <div class="af-table-view" id="afTableView" style="display:none;">

    <!-- Search -->
    <div class="af-search-wrap">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
        <circle cx="6" cy="6" r="4.5" stroke="#94A3B8" stroke-width="1.4"/>
        <path d="M10 10L13 13" stroke="#94A3B8" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <input type="text" id="afSearchInput" class="af-search-input"
             placeholder="Search table items"
             oninput="AF.filterTable(this.value)">
    </div>

    <!-- Table -->
    <div class="af-table-wrap">
      <table class="af-table">
        <thead>
          <tr>
            <th class="af-col-sno">S. No.</th>
            <th class="af-col-entity">Entity Type</th>
            <th class="af-col-name">Field Name</th>
            <th class="af-col-type">Field Type</th>
            <th class="af-col-regex">Regular Expressions</th>
            <th class="af-col-actions"></th>
          </tr>
        </thead>
        <tbody id="afTbody">
          <!-- Rows injected by JS -->
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="af-pagination">
      <div class="af-page-info" id="afPageInfo">Showing 0 entries</div>
      <div class="af-page-show">
        Show
        <select class="af-per-page" id="afPerPage" onchange="AF.setPerPage(this.value)">
          <option value="10">10</option>
          <option value="25" selected>25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        entries
      </div>
      <div class="af-page-nav" id="afPageNav"></div>
    </div>

  </div><!-- /#afTableView -->

</div><!-- /.af-wrapper -->

<!-- Custom Field Type Dropdown (portal-style, appended to body) -->
<div class="af-ft-dropdown" id="afFtDropdown" style="display:none;">
  <div class="af-ft-item af-ft-selected" data-value="Text">Text</div>
  <div class="af-ft-item" data-value="Number">Number</div>
  <div class="af-ft-item" data-value="Date">Date</div>
  <div class="af-ft-item" data-value="Yes/No">Yes/No</div>
</div>

<script>
    /**
 * additional_fields.js
 * Inline add/edit rows, custom Field Type dropdown, pagination
 */

const AF = (() => {
  'use strict';

  const API = 'API/additional_fields_api.php';
  const $   = id => document.getElementById(id);

  const ENTITY_TYPES = ['Employee','Department','Location','Organisation','Category','Designation'];
  const FIELD_TYPES  = ['Text','Number','Date','Yes/No'];

  /* ── State ─────────────────────────────────────────────── */
  let allRows      = [];
  let filteredRows = [];
  let currentPage  = 1;
  let perPage      = 25;
  let inlineOpen   = false; // is the inline add/edit row showing?
  let editingId    = null;  // null = new row, number = editing existing
  let activeFtTrigger = null; // the trigger button for the open ft dropdown
  let currentFtValue  = 'Text';

  /* ── Init ───────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    loadList();
    buildFtDropdown();
    document.addEventListener('click', e => {
      if (!e.target.closest('.af-ft-trigger') && !e.target.closest('#afFtDropdown')) {
        closeFtDropdown();
      }
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
          syncView();
        } else showToast(res.message, 'error');
      })
      .catch(() => showToast('Network error.', 'error'));
  }

  function filterTable(q) {
    const lq = q.toLowerCase();
    filteredRows = !lq ? allRows : allRows.filter(r =>
      (r.entity_type||'').toLowerCase().includes(lq) ||
      (r.field_name ||'').toLowerCase().includes(lq) ||
      (r.field_type ||'').toLowerCase().includes(lq)
    );
    currentPage = 1;
    renderTable();
  }

  function syncView() {
    if (!allRows.length && !inlineOpen) {
      $('afEmpty').style.display      = 'flex';
      $('afTableView').style.display  = 'none';
    } else {
      $('afEmpty').style.display      = 'none';
      $('afTableView').style.display  = 'block';
      renderTable();
    }
  }

  /* ════════════════════════════════════════════════
     TABLE RENDER
  ════════════════════════════════════════════════ */
  function renderTable() {
    const total  = filteredRows.length + (inlineOpen ? 1 : 0);
    const pages  = Math.max(1, Math.ceil(
      (filteredRows.length + (inlineOpen && currentPage === Math.ceil(filteredRows.length/perPage) ? 0 : 0)) / perPage
    ));

    // For display count, inline row counts as entry if saving
    const displayTotal = filteredRows.length;
    const tbody = $('afTbody');
    tbody.innerHTML = '';

    // Compute slice
    const start = (currentPage - 1) * perPage;
    const slice = filteredRows.slice(start, start + perPage);

    slice.forEach((r, i) => {
      const tr = document.createElement('tr');
      tr.dataset.id = r.id;
      tr.innerHTML = `
        <td>${start + i + 1}</td>
        <td>${esc(r.entity_type)}</td>
        <td>${esc(r.field_name)}</td>
        <td>${esc(r.field_type)}</td>
        <td>${esc(r.regular_expression || '')}</td>
        <td>
          <div class="af-row-actions">
            <button class="af-icon-btn" title="Edit">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                <path d="M9 1.5L11.5 4L4 11.5H1.5V9L9 1.5Z"
                      stroke="#2563EB" stroke-width="1.3" stroke-linejoin="round"/>
              </svg>
            </button>
            <button class="af-icon-btn del" title="Delete">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                <path d="M2 3.5h9M5 3.5V2h3v1.5M5.5 3.5l.5 7h1l.5-7"
                      stroke="#EF4444" stroke-width="1.3"
                      stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </td>`;
      tr.querySelectorAll('.af-icon-btn')[0].onclick = () => openEditRow(r.id);
      tr.querySelectorAll('.af-icon-btn')[1].onclick = () => deleteField(r.id, r.field_name);
      tbody.appendChild(tr);
    });

    // Inline add/edit row
    if (inlineOpen) {
      tbody.appendChild(buildInlineRow());
    }

    // Page info
    $('afPageInfo').textContent = `Showing ${displayTotal} entr${displayTotal===1?'y':'ies'}`;
    renderPageNav(Math.max(1, Math.ceil(displayTotal / perPage)));
  }

  function renderPageNav(pages) {
    const nav = $('afPageNav');
    nav.innerHTML = '';
    const mk = (lbl, pg, dis, act) => {
      const b = document.createElement('button');
      b.className = 'af-page-btn' + (act?' active':'');
      b.textContent = lbl; b.disabled = dis;
      if (!dis && !act) b.onclick = () => { currentPage = pg; renderTable(); };
      return b;
    };
    nav.appendChild(mk('«',1,currentPage===1));
    nav.appendChild(mk('‹',currentPage-1,currentPage===1));
    const s=Math.max(1,currentPage-2), e=Math.min(pages,s+4);
    for(let p=s;p<=e;p++) nav.appendChild(mk(p,p,false,p===currentPage));
    nav.appendChild(mk('›',currentPage+1,currentPage===pages));
    nav.appendChild(mk('»',pages,currentPage===pages));
  }

  function setPerPage(v) { perPage=parseInt(v); currentPage=1; renderTable(); }

  /* ════════════════════════════════════════════════
     INLINE ROW
  ════════════════════════════════════════════════ */
  function buildInlineRow() {
    const tr = document.createElement('tr');
    tr.className = 'af-inline-row';
    tr.id = 'afInlineRow';

    const existingRow = editingId ? allRows.find(r=>r.id==editingId) : null;
    const defEntity   = existingRow?.entity_type || 'Employee';
    const defName     = existingRow?.field_name  || '';
    const defType     = existingRow?.field_type  || 'Text';
    const defRegex    = existingRow?.regular_expression || '';
    currentFtValue    = defType;

    // S.No cell (empty for new)
    const tdSno = document.createElement('td');
    tr.appendChild(tdSno);

    // Entity Type
    const tdEntity = document.createElement('td');
    const sel = document.createElement('select');
    sel.className = 'af-entity-select';
    sel.id        = 'afInlineEntity';
    ENTITY_TYPES.forEach(et => {
      const opt = document.createElement('option');
      opt.value = et; opt.textContent = et;
      if (et === defEntity) opt.selected = true;
      sel.appendChild(opt);
    });
    tdEntity.appendChild(sel);
    tr.appendChild(tdEntity);

    // Field Name
    const tdName = document.createElement('td');
    const inp = document.createElement('input');
    inp.type = 'text'; inp.className = 'af-field-input';
    inp.id = 'afInlineName'; inp.value = defName;
    inp.placeholder = ''; inp.maxLength = 100;
    tdName.appendChild(inp);
    tr.appendChild(tdName);

    // Field Type (custom dropdown trigger)
    const tdType = document.createElement('td');
    const trigger = document.createElement('button');
    trigger.className = 'af-ft-trigger';
    trigger.id        = 'afFtTrigger';
    trigger.type      = 'button';
    trigger.innerHTML = `<span id="afFtLabel">${esc(defType)}</span>
      <svg class="af-ft-arrow" width="10" height="6" viewBox="0 0 10 6" fill="none">
        <path d="M1 1l4 4 4-4" stroke="#64748B" stroke-width="1.5"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>`;
    trigger.onclick = (e) => { e.stopPropagation(); toggleFtDropdown(trigger); };
    tdType.appendChild(trigger);
    tr.appendChild(tdType);

    // Regular Expression
    const tdRegex = document.createElement('td');
    const rinp = document.createElement('input');
    rinp.type = 'text'; rinp.className = 'af-regex-input';
    rinp.id = 'afInlineRegex'; rinp.value = defRegex;
    tdRegex.appendChild(rinp);
    tr.appendChild(tdRegex);

    // Actions
    const tdAct = document.createElement('td');
    tdAct.innerHTML = `
      <div class="af-inline-actions">
        <button class="af-btn-cancel-inline" type="button" onclick="AF.cancelInline()">Cancel</button>
        <button class="af-btn-save" type="button" id="afBtnSave" onclick="AF.saveInline()">Save</button>
      </div>`;
    tr.appendChild(tdAct);

    return tr;
  }

  /* ════════════════════════════════════════════════
     ADD ROW
  ════════════════════════════════════════════════ */
  function addRow() {
    if (inlineOpen) return; // already open
    editingId  = null;
    inlineOpen = true;
    currentFtValue = 'Text';

    // Make table visible if it was hidden (empty state)
    $('afEmpty').style.display     = 'none';
    $('afTableView').style.display = 'block';
    renderTable();
    // Focus field name
    setTimeout(() => { const el = $('afInlineName'); if(el) el.focus(); }, 50);
  }

  /* ════════════════════════════════════════════════
     EDIT ROW
  ════════════════════════════════════════════════ */
  function openEditRow(id) {
    if (inlineOpen) cancelInline();
    editingId  = id;
    inlineOpen = true;
    renderTable();
    setTimeout(() => { const el = $('afInlineName'); if(el) el.focus(); }, 50);
  }

  /* ════════════════════════════════════════════════
     SAVE
  ════════════════════════════════════════════════ */
  function saveInline() {
    const entity = $('afInlineEntity').value;
    const name   = $('afInlineName').value.trim();
    const type   = currentFtValue;
    const regex  = $('afInlineRegex').value.trim();

    if (!name) { showToast('Field Name is required.','error'); $('afInlineName').focus(); return; }

    const btn = $('afBtnSave');
    btn.disabled = true; btn.textContent = 'Saving…';

    const fd = new FormData();
    fd.append('action',            editingId ? 'update' : 'add');
    if (editingId) fd.append('id', editingId);
    fd.append('entity_type',        entity);
    fd.append('field_name',         name);
    fd.append('field_type',         type);
    fd.append('regular_expression', regex);

    fetch(API, {method:'POST',body:fd})
      .then(r=>r.json())
      .then(res=>{
        if(res.success){
          showToast(res.message,'success');
          inlineOpen = false; editingId = null;
          loadList();
        } else {
          showToast(res.message||'Failed.','error');
          btn.disabled=false; btn.textContent='Save';
        }
      })
      .catch(()=>{
        showToast('Network error.','error');
        btn.disabled=false; btn.textContent='Save';
      });
  }

  /* ════════════════════════════════════════════════
     CANCEL / DELETE
  ════════════════════════════════════════════════ */
  function cancelInline() {
    inlineOpen = false; editingId = null;
    closeFtDropdown();
    syncView();
  }

function deleteField(id, name) {
  Swal.fire({
    title: 'Are you sure?',
    text: `Delete field "${name}"? This cannot be undone.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (!result.isConfirmed) return;

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
        Swal.fire({
          title: 'Deleted!',
          text: 'Field deleted successfully.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false
        });
        loadList();
      } else {
        Swal.fire({
          title: 'Error!',
          text: res.message,
          icon: 'error'
        });
      }
    })
    .catch(() => {
      Swal.fire({
        title: 'Error!',
        text: 'Something went wrong.',
        icon: 'error'
      });
    });
  });
}

  /* ════════════════════════════════════════════════
     CUSTOM FIELD TYPE DROPDOWN
  ════════════════════════════════════════════════ */
  function buildFtDropdown() {
    const dd = $('afFtDropdown');
    dd.innerHTML = '';
    FIELD_TYPES.forEach(ft => {
      const item = document.createElement('div');
      item.className = 'af-ft-item' + (ft==='Text'?' af-ft-selected':'');
      item.dataset.value = ft;
      item.textContent   = ft;
      item.onclick = (e) => { e.stopPropagation(); selectFtItem(ft); };
      dd.appendChild(item);
    });
  }

  function toggleFtDropdown(trigger) {
    const dd = $('afFtDropdown');
    if (dd.style.display === 'block' && activeFtTrigger === trigger) {
      closeFtDropdown(); return;
    }
    activeFtTrigger = trigger;
    trigger.classList.add('open');

    // Highlight current value
    dd.querySelectorAll('.af-ft-item').forEach(item => {
      item.classList.toggle('af-ft-selected', item.dataset.value === currentFtValue);
    });

    // Position under trigger
    const rect = trigger.getBoundingClientRect();
    dd.style.top    = (rect.bottom + window.scrollY + 2) + 'px';
    dd.style.left   = rect.left + 'px';
    dd.style.width  = Math.max(rect.width, 160) + 'px';
    dd.style.display = 'block';
  }

  function closeFtDropdown() {
    $('afFtDropdown').style.display = 'none';
    if (activeFtTrigger) activeFtTrigger.classList.remove('open');
    activeFtTrigger = null;
  }

  function selectFtItem(value) {
    currentFtValue = value;
    const lbl = $('afFtLabel');
    if (lbl) lbl.textContent = value;
    closeFtDropdown();
  }

  /* ── Utilities ──────────────────────────────────────────── */
  function esc(s) {
    return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  let toastTimer;
  function showToast(msg, type='') {
    let t = document.querySelector('.af-toast');
    if (!t) { t=document.createElement('div'); t.className='af-toast'; document.body.appendChild(t); }
    t.className=`af-toast ${type}`; t.textContent=msg;
    clearTimeout(toastTimer);
    requestAnimationFrame(()=>{
      t.classList.add('show');
      toastTimer=setTimeout(()=>t.classList.remove('show'),3200);
    });
  }

  return { addRow, cancelInline, saveInline, filterTable, setPerPage };
})();
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>