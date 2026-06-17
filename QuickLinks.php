<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Quick Links';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
  /* ============================================================
   quick_links.css  –  PerkPayroll Quick Links
   ============================================================ */

.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #E5E7EB;background:#fff;overflow-x:auto;scrollbar-width:none;}
.cfg-tabs::-webkit-scrollbar{display:none;}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6B7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px;}
.cfg-tab:hover{color:#111827;}
.cfg-tab.active{color:#2563EB;border-bottom-color:#2563EB;font-weight:600;}

/* ---------- Wrapper ---------- */
.ql-wrapper {
  background: #fff;
  font-family: 'Segoe UI', Arial, sans-serif;
  color: #1e293b;
  min-height: calc(100vh - 140px);
  display: flex;
  flex-direction: column;
}

/* ---------- Page header ---------- */
.ql-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 24px;
}
.ql-page-title {
  font-size: 15px;
  font-weight: 700;
  color: #0F172A;
}
.ql-btn-add {
  display: flex; align-items: center; gap: 6px;
  background: #2563EB; color: #fff;
  border: none; border-radius: 6px;
  padding: 9px 18px; font-size: 13.5px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.ql-btn-add > span { font-size: 18px; line-height: 1; }
.ql-btn-add:hover  { background: #1D4ED8; }

/* ---------- Column labels row ---------- */
.ql-col-labels {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border-top: 1px solid #E2E8F0;
  border-bottom: 1px solid #E2E8F0;
}
.ql-col-label {
  padding: 10px 20px;
  font-size: 13px;
  color: #64748B;
  font-weight: 400;
}
.ql-col-label:first-child {
  border-right: 1px solid #E2E8F0;
}

/* ---------- Split body ---------- */
.ql-body {
  display: grid;
  grid-template-columns: 1fr 1fr;
  flex: 1;
  min-height: 380px;
}

/* Left panel */
.ql-left {
  border-right: 1px solid #E2E8F0;
  display: flex;
  flex-direction: column;
}

/* Right panel */
.ql-right {
  display: flex;
  flex-direction: column;
}

/* ---------- Empty states ---------- */
.ql-empty, .ql-right-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  padding: 60px 20px;
}
.ql-empty-text {
  font-size: 13px;
  color: #94A3B8;
  margin: 14px 0 0;
}

/* ---------- Links list ---------- */
.ql-list {
  padding: 8px 0;
  overflow-y: auto;
}
.ql-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  border-bottom: 1px solid #F1F5F9;
  cursor: pointer;
  transition: background .1s;
}
.ql-item:last-child { border-bottom: none; }
.ql-item:hover { background: #F8FAFC; }
.ql-item.active {
  background: #EFF6FF;
  border-left: 3px solid #2563EB;
  padding-left: 17px;
}
.ql-item-name {
  font-size: 13.5px;
  font-weight: 600;
  color: #0F172A;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 260px;
}
.ql-item-url {
  font-size: 12px;
  color: #64748B;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 260px;
  margin-top: 3px;
}
.ql-item-vis {
  font-size: 11px;
  color: #22C55E;
  font-weight: 600;
  flex-shrink: 0;
  margin-left: 8px;
}

/* ---------- Detail view ---------- */
.ql-detail {
  padding: 20px 24px;
  flex: 1;
}
.ql-detail-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.ql-detail-name {
  font-size: 15px;
  font-weight: 700;
  color: #0F172A;
}
.ql-detail-btns { display: flex; gap: 8px; }
.ql-icon-btn {
  width: 30px; height: 30px;
  border: 1.5px solid #E2E8F0; background: #fff;
  border-radius: 6px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background .1s, border-color .1s;
}
.ql-icon-btn:hover { background: #F0F9FF; border-color: #93C5FD; }
.ql-icon-btn.ql-del:hover { background: #FEF2F2; border-color: #FCA5A5; }

.ql-detail-field  { margin-bottom: 20px; }
.ql-df-label { font-size: 12px; color: #94A3B8; margin-bottom: 6px; }
.ql-df-value { font-size: 14px; color: #0F172A; font-weight: 500; padding-bottom: 6px; min-height: 22px; }
.ql-df-link  {
  display: block; font-size: 14px; color: #2563EB;
  text-decoration: none; padding-bottom: 6px;
  word-break: break-all;
}
.ql-df-link:hover { text-decoration: underline; }
.ql-df-line  { border-bottom: 1px solid #E2E8F0; }

/* ---------- Form ---------- */
.ql-form {
  padding: 22px 24px;
  flex: 1;
}
.ql-ff {
  margin-bottom: 24px;
}
.ql-fl {
  display: block;
  font-size: 13px;
  color: #374151;
  margin-bottom: 8px;
  font-weight: 400;
}
.ql-fi {
  width: 100%; box-sizing: border-box;
  border: none; border-bottom: 1.5px solid #CBD5E1;
  background: transparent; padding: 5px 0;
  font-size: 14px; color: #1e293b;
  outline: none; font-family: inherit; transition: border-color .15s;
}
.ql-fi:focus { border-bottom-color: #2563EB; }

/* Checkbox row */
.ql-check-row { margin-bottom: 28px; }
.ql-check-label {
  display: flex; align-items: center; gap: 10px;
  font-size: 13.5px; color: #374151; cursor: pointer;
  user-select: none;
}
.ql-checkbox {
  width: 17px; height: 17px;
  accent-color: #2563EB; cursor: pointer;
  flex-shrink: 0;
  border: 1.5px solid #CBD5E1;
  border-radius: 3px;
}

/* Form actions */
.ql-form-actions {
  display: flex; justify-content: flex-end; gap: 12px;
  border-top: 1px solid #F1F5F9; padding-top: 20px;
}
.ql-btn-cancel {
  padding: 8px 24px; border: 1.5px solid #CBD5E1;
  background: #fff; color: #64748B; border-radius: 6px;
  font-size: 13.5px; font-weight: 600; cursor: pointer;
  font-family: inherit; transition: background .15s;
}
.ql-btn-cancel:hover { background: #F8FAFC; border-color: #94A3B8; }
.ql-btn-save {
  padding: 8px 28px; border: none; background: #2563EB;
  color: #fff; border-radius: 6px; font-size: 13.5px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.ql-btn-save:hover    { background: #1D4ED8; }
.ql-btn-save:disabled { background: #93C5FD; cursor: not-allowed; }

/* ---------- Toast ---------- */
.ql-toast {
  position: fixed; bottom: 28px; right: 28px;
  background: #1E293B; color: #fff; padding: 12px 20px;
  border-radius: 8px; font-size: 13.5px; box-shadow: 0 4px 20px rgba(0,0,0,.18);
  z-index: 9999; opacity: 0; transform: translateY(12px);
  transition: opacity .25s, transform .25s; pointer-events: none;
}
.ql-toast.success { background: #166534; }
.ql-toast.error   { background: #991B1B; }
.ql-toast.show    { opacity: 1; transform: translateY(0); }

/* ---------- Responsive ---------- */
@media (max-width: 640px) {
  .ql-col-labels, .ql-body { grid-template-columns: 1fr; }
  .ql-col-label:first-child { border-right: none; border-bottom: 1px solid #E2E8F0; }
  .ql-left { border-right: none; border-bottom: 1px solid #E2E8F0; }
}
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


  <div class="ql-wrapper">

    <!-- ── Page header ── -->
    <div class="ql-page-header">
      <span class="ql-page-title">Quick Links</span>
      <button class="ql-btn-add" onclick="QL.openForm()">
        <span>+</span> Add New
      </button>
    </div>

    <!-- ── Column labels ── -->
    <div class="ql-col-labels">
      <div class="ql-col-label">Links</div>
      <div class="ql-col-label">Link Details</div>
    </div>

    <!-- ── Split body ── -->
    <div class="ql-body">

      <!-- LEFT: links list -->
      <div class="ql-left" id="qlLeft">
        <div class="ql-empty" id="qlLeftEmpty">
          <svg width="90" height="90" viewBox="0 0 90 90" fill="none">
            <circle cx="45" cy="45" r="45" fill="#EEF2FF"/>
            <rect x="16" y="14" width="58" height="62" rx="5" fill="#CBD5E1"/>
            <rect x="16" y="14" width="58" height="16" rx="5" fill="#94A3B8"/>
            <rect x="26" y="38" width="28" height="4" rx="2" fill="#3B82F6"/>
            <rect x="26" y="48" width="22" height="4" rx="2" fill="#3B82F6"/>
            <rect x="26" y="58" width="16" height="4" rx="2" fill="#93C5FD"/>
            <circle cx="24" cy="22" r="2" fill="#fff" opacity=".5"/>
            <circle cx="31" cy="22" r="2" fill="#fff" opacity=".5"/>
          </svg>
          <p class="ql-empty-text">No Quick Links!</p>
        </div>
        <div class="ql-list" id="qlList" style="display:none;"></div>
      </div>

      <!-- RIGHT: detail / form -->
      <div class="ql-right" id="qlRight">

        <!-- Empty right state -->
        <div class="ql-right-empty" id="qlRightEmpty">
          <svg width="70" height="70" viewBox="0 0 70 70" fill="none">
            <circle cx="35" cy="35" r="35" fill="#EEF2FF"/>
            <rect x="13" y="11" width="44" height="48" rx="4" fill="#CBD5E1"/>
            <rect x="13" y="11" width="44" height="13" rx="4" fill="#94A3B8"/>
            <rect x="21" y="30" width="20" height="3" rx="1.5" fill="#3B82F6"/>
            <rect x="21" y="38" width="16" height="3" rx="1.5" fill="#3B82F6"/>
            <rect x="21" y="46" width="12" height="3" rx="1.5" fill="#93C5FD"/>
          </svg>
          <p class="ql-empty-text">No Quick Links!</p>
        </div>

        <!-- Detail view -->
        <div class="ql-detail" id="qlDetail" style="display:none;">
          <div class="ql-detail-topbar">
            <span class="ql-detail-name" id="qdName"></span>
            <div class="ql-detail-btns">
              <button class="ql-icon-btn" title="Edit" onclick="QL.openEditFromDetail()">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                  <path d="M9 1.5L11.5 4L4 11.5H1.5V9L9 1.5Z"
                        stroke="#2563EB" stroke-width="1.3" stroke-linejoin="round"/>
                </svg>
              </button>
              <button class="ql-icon-btn ql-del" title="Delete" onclick="QL.deleteLink()">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                  <path d="M2 3.5h9M5 3.5V2h3v1.5M5.5 3.5l.5 7h1l.5-7"
                        stroke="#EF4444" stroke-width="1.3"
                        stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="ql-detail-field">
            <div class="ql-df-label">Display Name</div>
            <div class="ql-df-value" id="qdDisplayName"></div>
            <div class="ql-df-line"></div>
          </div>
          <div class="ql-detail-field">
            <div class="ql-df-label">Link</div>
            <a class="ql-df-link" id="qdLink" href="#" target="_blank" rel="noopener"></a>
            <div class="ql-df-line"></div>
          </div>
          <div class="ql-detail-field">
            <div class="ql-df-label">Visibility</div>
            <div class="ql-df-value" id="qdVisibility"></div>
            <div class="ql-df-line"></div>
          </div>
        </div>

        <!-- Add / Edit form -->
        <div class="ql-form" id="qlForm" style="display:none;">
          <div class="ql-ff">
            <label class="ql-fl">Display Name</label>
            <input type="text" class="ql-fi" id="fDisplayName" maxlength="200">
          </div>
          <div class="ql-ff">
            <label class="ql-fl">Link</label>
            <input type="text" class="ql-fi" id="fLink" maxlength="2000" autocomplete="url">
          </div>
          <div class="ql-check-row">
            <label class="ql-check-label">
              <input type="checkbox" id="fVisibleToAll" class="ql-checkbox"> Visible to Everyone
            </label>
          </div>
          <input type="hidden" id="fEditingId" value="">
          <div class="ql-form-actions">
            <button class="ql-btn-cancel" onclick="QL.cancelForm()">Cancel</button>
            <button class="ql-btn-save"   id="qlBtnSave" onclick="QL.saveForm()">Save</button>
          </div>
        </div>

      </div><!-- /.ql-right -->
    </div><!-- /.ql-body -->
  </div><!-- /.ql-wrapper -->

</div>  
<script>
  /**
 * quick_links.js
 * Split-panel: Links list (left) ↔ Detail / Form (right)
 */

const QL = (() => {
  'use strict';

  const API = 'API/quick_links_api.php';
  const $   = id => document.getElementById(id);

  /* ── State ─────────────────────────────────────────────── */
  let allLinks    = [];
  let activeId    = null;
  let editingId   = null;

  /* ── Init ───────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', loadList);

  /* ════════════════════════════════════════════════
     LIST
  ════════════════════════════════════════════════ */
  function loadList() {
    fetch(`${API}?action=list`)
      .then(r => r.json())
      .then(res => {
        if (res.success) { allLinks = res.data || []; renderList(); }
        else showToast(res.message, 'error');
      })
      .catch(() => showToast('Network error.', 'error'));
  }

  function renderList() {
    const listEl  = $('qlList');
    const emptyEl = $('qlLeftEmpty');

    if (!allLinks.length) {
      emptyEl.style.display = 'flex';
      listEl.style.display  = 'none';
      // If no active item reset right panel
      if (!editingId) showRightPanel('empty');
      return;
    }

    emptyEl.style.display = 'none';
    listEl.style.display  = 'block';
    listEl.innerHTML      = '';

    allLinks.forEach(lnk => {
      const item = document.createElement('div');
      item.className = 'ql-item' + (lnk.id == activeId ? ' active' : '');
      item.dataset.id = lnk.id;
      item.innerHTML = `
        <div>
          <div class="ql-item-name">${esc(lnk.display_name)}</div>
          <div class="ql-item-url">${esc(lnk.link_url)}</div>
        </div>
        ${lnk.visible_to_all == 1 ? '<span class="ql-item-vis">Everyone</span>' : ''}`;
      item.addEventListener('click', () => viewLink(lnk.id));
      listEl.appendChild(item);
    });

    // Auto-select first if nothing selected
    if (!activeId && allLinks.length) viewLink(allLinks[0].id);
  }

  /* ════════════════════════════════════════════════
     VIEW
  ════════════════════════════════════════════════ */
  function viewLink(id) {
    activeId = id;
    const lnk = allLinks.find(l => l.id == id);
    if (!lnk) return;

    // Highlight in list
    document.querySelectorAll('.ql-item').forEach(el =>
      el.classList.toggle('active', el.dataset.id == id)
    );

    $('qdName').textContent       = lnk.display_name;
    $('qdDisplayName').textContent = lnk.display_name;
    $('qdLink').textContent       = lnk.link_url;
    $('qdLink').href              = lnk.link_url.startsWith('http') ? lnk.link_url : 'https://'+lnk.link_url;
    $('qdVisibility').textContent = lnk.visible_to_all == 1 ? 'Visible to Everyone' : 'Admin only';

    showRightPanel('detail');
  }

  /* ════════════════════════════════════════════════
     OPEN ADD FORM
  ════════════════════════════════════════════════ */
  function openForm() {
    editingId = null;
    clearForm();
    showRightPanel('form');
  }

  /* ════════════════════════════════════════════════
     OPEN EDIT FROM DETAIL
  ════════════════════════════════════════════════ */
  function openEditFromDetail() {
    if (!activeId) return;
    const lnk = allLinks.find(l => l.id == activeId);
    if (!lnk) return;
    editingId = lnk.id;
    $('fDisplayName').value      = lnk.display_name;
    $('fLink').value             = lnk.link_url;
    $('fVisibleToAll').checked   = lnk.visible_to_all == 1;
    $('fEditingId').value        = lnk.id;
    $('qlBtnSave').textContent   = 'Save';
    showRightPanel('form');
  }

  /* ════════════════════════════════════════════════
     SAVE
  ════════════════════════════════════════════════ */
  function saveForm() {
    const name    = $('fDisplayName').value.trim();
    const url     = $('fLink').value.trim();
    const visible = $('fVisibleToAll').checked ? 1 : 0;

    if (!name) { showToast('Display Name is required.', 'error'); $('fDisplayName').focus(); return; }
    if (!url)  { showToast('Link is required.', 'error'); $('fLink').focus(); return; }

    const btn = $('qlBtnSave');
    btn.disabled = true; btn.textContent = 'Saving…';

    const fd = new FormData();
    fd.append('action',         editingId ? 'update' : 'add');
    if (editingId) fd.append('id', editingId);
    fd.append('display_name',   name);
    fd.append('link_url',       url);
    fd.append('visible_to_all', visible);

    fetch(API, {method:'POST',body:fd})
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          showToast(res.message, 'success');
          if (!editingId && res.id) activeId = res.id;
          editingId = null;
          loadList();
          // After load, detail will be shown for activeId
        } else {
          showToast(res.message || 'Failed.', 'error');
          btn.disabled = false; btn.textContent = 'Save';
        }
      })
      .catch(() => {
        showToast('Network error.', 'error');
        btn.disabled = false; btn.textContent = 'Save';
      });
  }

  /* ════════════════════════════════════════════════
     CANCEL
  ════════════════════════════════════════════════ */
  function cancelForm() {
    editingId = null;
    if (activeId) {
      viewLink(activeId);
    } else if (allLinks.length) {
      viewLink(allLinks[0].id);
    } else {
      showRightPanel('empty');
    }
  }

  /* ════════════════════════════════════════════════
     DELETE
  ════════════════════════════════════════════════ */
function deleteLink() {
    if (!activeId) return;

    const lnk = allLinks.find(l => l.id == activeId);

    Swal.fire({
        title: 'Delete Link?',
        text: `Are you sure you want to delete "${lnk?.display_name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
        reverseButtons: true
    }).then((result) => {

        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', activeId);

        fetch(API, {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {

            if (res.success) {

                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'Link deleted successfully.',
                    timer: 1500,
                    showConfirmButton: false
                });

                activeId = null;
                loadList();

            } else {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.message || 'Failed to delete link.'
                });
            }

        })
        .catch(() => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Something went wrong.'
            });
        });

    });
}
  /* ════════════════════════════════════════════════
     PANEL SWITCHING
  ════════════════════════════════════════════════ */
  function showRightPanel(mode) {
    $('qlRightEmpty').style.display = mode==='empty'  ? 'flex'  : 'none';
    $('qlDetail').style.display     = mode==='detail' ? 'block' : 'none';
    $('qlForm').style.display       = mode==='form'   ? 'block' : 'none';
    if (mode==='form') {
      const btn = $('qlBtnSave');
      if (!editingId) { clearForm(); btn.textContent='Save'; btn.disabled=false; }
    }
  }

  function clearForm() {
    $('fDisplayName').value    = '';
    $('fLink').value           = '';
    $('fVisibleToAll').checked = false;
    $('fEditingId').value      = '';
  }

  /* ── Utilities ──────────────────────────────────────────── */
  function esc(s) {
    return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  let toastTimer;
  function showToast(msg, type='') {
    let t = document.querySelector('.ql-toast');
    if (!t) { t=document.createElement('div'); t.className='ql-toast'; document.body.appendChild(t); }
    t.className=`ql-toast ${type}`; t.textContent=msg;
    clearTimeout(toastTimer);
    requestAnimationFrame(()=>{
      t.classList.add('show');
      toastTimer=setTimeout(()=>t.classList.remove('show'),3200);
    });
  }

  return { openForm, openEditFromDetail, saveForm, cancelForm, deleteLink };
})();
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>