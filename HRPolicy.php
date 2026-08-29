<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php'; // should contain $conn
require_once 'includes/config.php';

$page_title = 'HR Policy';

/* ── Pre-load group data for checkboxes ── */
$organisations = [];
$locations_list = [];
$departments = [];
$categories = [];
$groups = [];
$sub_groups = [];

try {

    $result = mysqli_query($conn, "SELECT id, client_name AS name FROM companies ORDER BY name");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $organisations[] = $row;
        }
    }

    $result = mysqli_query($conn, "SELECT id, location_name AS name FROM org_locations WHERE status='active' ORDER BY name");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $locations_list[] = $row;
        }
    }

    $result = mysqli_query($conn, "SELECT id, dept_name AS name FROM org_departments WHERE status='active' ORDER BY name");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $departments[] = $row;
        }
    }

    $result = mysqli_query($conn, "SELECT id, cat_name AS name FROM org_categories WHERE status='active' ORDER BY name");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row;
        }
    }

    $result = mysqli_query($conn, "SELECT id, group_name AS name FROM org_groups WHERE status='active' ORDER BY name");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $groups[] = $row;
        }
    }

    $result = mysqli_query($conn, "SELECT id, sub_group_name AS name FROM org_sub_groups WHERE status='active' ORDER BY name");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $sub_groups[] = $row;
        }
    }

} catch (Exception $e) {
    // tables may not exist yet
}

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">
<style>
    /* ============================================================
   hr_policy.css  –  PerkPayroll-style HR Policy page
   ============================================================ */

/* Config tab bar */
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #E5E7EB;background:#fff;overflow-x:auto;scrollbar-width:none;}
.cfg-tabs::-webkit-scrollbar{display:none;}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6B7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px;}
.cfg-tab:hover{color:#111827;}
.cfg-tab.active{color:#2563EB;border-bottom-color:#2563EB;font-weight:600;}

/* ---------- Wrapper ---------- */
.hp-wrapper {
  background: #fff;
  font-family: 'Segoe UI', Arial, sans-serif;
  color: #1e293b;
  min-height: calc(100vh - 140px);
}

/* ---------- Top bar ---------- */
.hp-list-topbar {
  display: flex;
  align-items: center;
  padding: 14px 20px;
  border-bottom: 1px solid #E2E8F0;
  gap: 10px;
}
.hp-topbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.hp-back-btn {
  width: 28px; height: 28px;
  border: 1.5px solid #CBD5E1;
  background: #fff; border-radius: 5px;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background .1s;
}
.hp-back-btn:hover { background: #F1F5F9; }
.hp-topbar-title {
  font-size: 14px;
  font-weight: 700;
  color: #0F172A;
}

/* ---------- Empty state ---------- */
.hp-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px 60px;
}
.hp-empty-art  { margin-bottom: 18px; }
.hp-empty-text {
  font-size: 14px; color: #94A3B8; margin: 0 0 24px;
}

/* ---------- Add button ---------- */
.hp-btn-add {
  background: #2563EB; color: #fff;
  border: none; border-radius: 6px;
  padding: 9px 22px; font-size: 13.5px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.hp-btn-add:hover { background: #1D4ED8; }

/* ---------- Policy list ---------- */
.hp-list-header {
  display: flex;
  justify-content: flex-end;
  padding: 16px 24px 0;
}
.hp-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
  padding: 16px 24px 24px;
}
.hp-card {
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  padding: 18px 20px;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,.05);
  transition: box-shadow .15s, border-color .15s;
}
.hp-card:hover { box-shadow: 0 4px 14px rgba(37,99,235,.1); border-color: #93C5FD; }
.hp-card-name  { font-size: 14px; font-weight: 700; color: #0F172A; margin: 0 0 8px; }
.hp-card-file  { font-size: 12px; color: #64748B; margin-bottom: 12px; word-break: break-all; }
.hp-card-date  { font-size: 11.5px; color: #94A3B8; margin-bottom: 14px; }
.hp-card-actions { display: flex; gap: 8px; }
.hp-card-btn {
  flex: 1; padding: 7px; border-radius: 6px;
  font-size: 12.5px; font-weight: 600; cursor: pointer;
  font-family: inherit; border: 1.5px solid #E2E8F0;
  background: #fff; color: #374151; transition: background .1s;
  text-align: center; text-decoration: none; display: inline-block;
}
.hp-card-btn:hover    { background: #F8FAFC; }
.hp-card-btn.primary  { background: #2563EB; color: #fff; border-color: #2563EB; }
.hp-card-btn.primary:hover { background: #1D4ED8; }
.hp-card-btn.danger { border-color: #FCA5A5; color: #EF4444; }
.hp-card-btn.danger:hover { background: #FEF2F2; }

/* ============================================================
   FORM VIEW
   ============================================================ */
.hp-form-body { padding: 20px 24px 0; }

/* Policy name */
.hp-form-field   { margin-bottom: 22px; }
.hp-field-label  {
  display: block; font-size: 12.5px; color: #374151;
  margin-bottom: 8px; font-weight: 400;
}
.hp-field-input {
  width: 100%; max-width: 440px; box-sizing: border-box;
  border: none; border-bottom: 1.5px solid #CBD5E1;
  background: transparent; padding: 5px 0;
  font-size: 14px; color: #1e293b;
  outline: none; font-family: inherit; transition: border-color .15s;
}
.hp-field-input:focus { border-bottom-color: #2563EB; }

/* Dropzone */
.hp-dropzone {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 8px;
  border: 1.5px dashed #CBD5E1;
  border-radius: 8px;
  background: #F8FAFC;
  padding: 32px 20px;
  cursor: pointer;
  transition: background .15s, border-color .15s;
  margin-bottom: 20px;
}
.hp-dropzone:hover, .hp-dropzone.drag-over {
  background: #EFF6FF;
  border-color: #93C5FD;
}
.hp-cloud-icon   { flex-shrink: 0; }
.hp-dropzone-text {
  font-size: 13.5px; font-weight: 500; color: #2563EB;
}
.hp-file-name {
  font-size: 12.5px; color: #16A34A; font-weight: 600;
  display: flex; align-items: center; gap: 6px;
}

/* Toggle switch */
.hp-toggle-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}
.hp-toggle {
  position: relative; display: inline-block;
  width: 42px; height: 24px; flex-shrink: 0; cursor: pointer;
}
.hp-toggle input  { display: none; }
.hp-toggle-track  {
  position: absolute; inset: 0;
  background: #CBD5E1; border-radius: 20px;
  transition: background .2s;
}
.hp-toggle input:checked ~ .hp-toggle-track { background: #2563EB; }
.hp-toggle-thumb {
  position: absolute; top: 3px; left: 3px;
  width: 18px; height: 18px;
  background: #fff; border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0,0,0,.2);
  transition: transform .2s;
}
.hp-toggle input:checked ~ .hp-toggle-thumb { transform: translateX(18px); }
.hp-toggle-label { font-size: 13px; color: #374151; line-height: 1.45; }

/* Groups panel */
.hp-groups-intro {
  font-size: 13px; color: #475569; margin: 0 0 16px;
}
.hp-groups-container {
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 4px;
}

/* Accordion section */
.hp-group-section {
  border-bottom: 1px solid #E2E8F0;
}
.hp-group-section:last-child { border-bottom: none; }

.hp-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 18px;
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 600;
  color: #1e293b;
  background: #fff;
  user-select: none;
  transition: background .1s;
}
.hp-section-header:hover { background: #F8FAFC; }

.hp-section-chevron {
  transition: transform .2s;
  flex-shrink: 0;
}
.hp-section-chevron.down { transform: rotate(180deg); }

.hp-section-body {
  padding: 14px 18px 16px;
  border-top: 1px solid #F1F5F9;
  background: #FAFAFA;
}

/* Checkbox grid */
.hp-check-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 10px 8px;
}
.hp-check-item {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  color: #374151;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.hp-check-item input[type="checkbox"] {
  accent-color: #2563EB;
  width: 15px; height: 15px;
  cursor: pointer; flex-shrink: 0;
}
.hp-no-items { font-size: 13px; color: #94A3B8; margin: 0; }

/* ---------- Form actions ---------- */
.hp-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 24px;
  border-top: 1px solid #F1F5F9;
  margin-top: 20px;
}
.hp-btn-cancel {
  padding: 9px 24px;
  border: 1.5px solid #CBD5E1; background: #fff;
  color: #64748B; border-radius: 6px;
  font-size: 13.5px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.hp-btn-cancel:hover { background: #F8FAFC; }
.hp-btn-upload {
  padding: 9px 28px; border: none;
  background: #2563EB; color: #fff;
  border-radius: 6px; font-size: 13.5px; font-weight: 600;
  cursor: pointer; font-family: inherit; transition: background .15s;
}
.hp-btn-upload:hover    { background: #1D4ED8; }
.hp-btn-upload:disabled { background: #93C5FD; cursor: not-allowed; }

/* ---------- Toast ---------- */
.hp-toast {
  position: fixed; bottom: 28px; right: 28px;
  background: #1E293B; color: #fff;
  padding: 12px 20px; border-radius: 8px;
  font-size: 13.5px; box-shadow: 0 4px 20px rgba(0,0,0,.18);
  z-index: 9999; opacity: 0; transform: translateY(12px);
  transition: opacity .25s, transform .25s; pointer-events: none;
}
.hp-toast.success { background: #166534; }
.hp-toast.error   { background: #991B1B; }
.hp-toast.show    { opacity: 1; transform: translateY(0); }

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
  .hp-check-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 480px) {
  .hp-check-grid { grid-template-columns: repeat(2, 1fr); }
  .hp-cards      { grid-template-columns: 1fr; }
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


<div class="hp-wrapper">

  <!-- ══════════════════════════════════════
       LIST VIEW
  ══════════════════════════════════════ -->
  <div id="hpViewList">
    <div class="hp-list-topbar">
      <div class="hp-topbar-left">
        <button class="hp-back-btn" onclick="HP.goBack()" title="Back">
          <svg width="8" height="12" viewBox="0 0 8 12" fill="none">
            <path d="M7 1L1 6l6 5" stroke="#374151" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <span class="hp-topbar-title">HR Policy</span>
      </div>
    </div>

    <!-- Empty state -->
    <div class="hp-empty" id="hpEmpty">
      <div class="hp-empty-art">
        <svg width="110" height="110" viewBox="0 0 110 110" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="55" cy="55" r="55" fill="#F1F5F9"/>
          <!-- Browser window shape -->
          <rect x="22" y="28" width="66" height="54" rx="6" fill="#E2E8F0"/>
          <rect x="22" y="28" width="66" height="14" rx="6" fill="#CBD5E1"/>
          <!-- Dots -->
          <circle cx="33" cy="35" r="2.5" fill="#94A3B8"/>
          <circle cx="41" cy="35" r="2.5" fill="#94A3B8"/>
          <circle cx="49" cy="35" r="2.5" fill="#94A3B8"/>
          <!-- Sad face -->
          <circle cx="55" cy="60" r="14" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="1.5"/>
          <circle cx="50" cy="57" r="1.5" fill="#94A3B8"/>
          <circle cx="60" cy="57" r="1.5" fill="#94A3B8"/>
          <path d="M49 65c1.5-2 7.5-2 7 0" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
      <p class="hp-empty-text">No data found</p>
      <button class="hp-btn-add" onclick="HP.openForm()">Add New Policy</button>
    </div>

    <!-- Policy list (shown when records exist) -->
    <div class="hp-list" id="hpList" style="display:none;">
      <div class="hp-list-header">
        <button class="hp-btn-add" onclick="HP.openForm()">Add New Policy</button>
      </div>
      <div class="hp-cards" id="hpCards"></div>
    </div>
  </div>

  <!-- ══════════════════════════════════════
       UPLOAD / EDIT FORM VIEW
  ══════════════════════════════════════ -->
  <div id="hpViewForm" style="display:none;">
    <div class="hp-list-topbar">
      <div class="hp-topbar-left">
        <button class="hp-back-btn" onclick="HP.backToList()" title="Back">
          <svg width="8" height="12" viewBox="0 0 8 12" fill="none">
            <path d="M7 1L1 6l6 5" stroke="#374151" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <span class="hp-topbar-title">Upload HR Policy</span>
      </div>
    </div>
    <div class="hp-form-body">
      <!-- Policy Name -->
      <div class="hp-form-field">
        <label class="hp-field-label">Policy Name</label>
        <input type="text" class="hp-field-input" id="fPolicyName" maxlength="255" placeholder="">
      </div>

      <!-- File upload dropzone -->
      <div class="hp-dropzone" id="hpDropzone"
           onclick="document.getElementById('hpFileInput').click()"
           ondragover="HP.onDragOver(event)" ondragleave="HP.onDragLeave(event)"
           ondrop="HP.onDrop(event)">
        <svg width="36" height="30" viewBox="0 0 36 30" fill="none" class="hp-cloud-icon">
          <path d="M28 22H8a7 7 0 1 1 1.4-13.86A9 9 0 0 1 27 10.5a5.5 5.5 0 0 1 1 11.5Z"
                stroke="#2563EB" stroke-width="1.8" fill="none"/>
          <path d="M18 26V14M14 18l4-4 4 4" stroke="#2563EB" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="hp-dropzone-text">Browse files to upload</span>
        <div class="hp-file-name" id="hpFileName" style="display:none;"></div>
      </div>
      <input type="file" id="hpFileInput" accept=".pdf,.doc,.docx,.ppt,.pptx"
             style="display:none;" onchange="HP.onFileChosen(this)">

      <!-- Group visibility toggle -->
      <div class="hp-toggle-row">
        <label class="hp-toggle">
          <input type="checkbox" id="fManualGroups" onchange="HP.toggleGroups(this.checked)">
          <div class="hp-toggle-track"></div>
          <div class="hp-toggle-thumb"></div>
        </label>
        <span class="hp-toggle-label">
          Manually select groups who can view this policy.
          (By default, the policy is visible to all the groups.)
        </span>
      </div>

      <!-- Groups selector (shown when toggle is ON) -->
      <div id="hpGroupsPanel" style="display:none;">
        <p class="hp-groups-intro">
          Select the groups you want this policy to be visible to by checking the checkbox.
        </p>

        <div class="hp-groups-container" id="hpGroupsContainer">

          <?php
          $sections = [
            'Organisation' => ['key'=>'org',      'items'=>$organisations],
            'Locations'    => ['key'=>'location',  'items'=>$locations_list],
            'Department'   => ['key'=>'department','items'=>$departments],
            'Category'     => ['key'=>'category',  'items'=>$categories],
            'Group'        => ['key'=>'group',     'items'=>$groups],
            'Sub Group'    => ['key'=>'sub_group', 'items'=>$sub_groups],
          ];
          foreach ($sections as $label => $cfg):
          ?>
          <div class="hp-group-section" id="section_<?=$cfg['key']?>">
            <div class="hp-section-header" onclick="HP.toggleSection('<?=$cfg['key']?>')">
              <span><?=htmlspecialchars($label)?></span>
              <svg class="hp-section-chevron" id="chev_<?=$cfg['key']?>"
                   width="12" height="8" viewBox="0 0 12 8" fill="none">
                <path d="M1 7l5-5 5 5" stroke="#374151" stroke-width="1.6"
                      stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="hp-section-body" id="body_<?=$cfg['key']?>">
              <?php if (!empty($cfg['items'])): ?>
              <div class="hp-check-grid">
                <label class="hp-check-item">
                  <input type="checkbox" class="hp-select-all"
                         data-group="<?=$cfg['key']?>"
                         onchange="HP.selectAll('<?=$cfg['key']?>',this.checked)">
                  Select All
                </label>
                <?php foreach ($cfg['items'] as $item): ?>
                <label class="hp-check-item">
                  <input type="checkbox"
                         class="hp-check-<?=$cfg['key']?>"
                         name="groups[<?=$cfg['key']?>][]"
                         value="<?=(int)$item['id']?>"
                         onchange="HP.onCheckChange('<?=$cfg['key']?>')">
                  <?=htmlspecialchars(mb_strimwidth($item['name'], 0, 18, '...'))?>
                </label>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <p class="hp-no-items">No <?=strtolower($label)?> found.</p>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>

        </div>
      </div>

    </div><!-- /.hp-form-body -->

    <input type="hidden" id="fEditingId" value="">

    <div class="hp-form-actions">
      <button class="hp-btn-cancel" onclick="HP.backToList()">Cancel</button>
      <button class="hp-btn-upload" id="hpBtnUpload" onclick="HP.submitForm()">Upload</button>
    </div>

  </div><!-- /#hpViewForm -->

</div><!-- /.hp-wrapper -->
</div>
<script>
  // Pass PHP group data to JS for dynamic rendering if needed
  const HP_GROUPS_DATA = {
    org:        <?=json_encode(array_column($organisations,  'name','id'))?>,
    location:   <?=json_encode(array_column($locations_list, 'name','id'))?>,
    department: <?=json_encode(array_column($departments,    'name','id'))?>,
    category:   <?=json_encode(array_column($categories,     'name','id'))?>,
    group:      <?=json_encode(array_column($groups,         'name','id'))?>,
    sub_group:  <?=json_encode(array_column($sub_groups,     'name','id'))?>
  };
</script>
<script>
    /**
 * hr_policy.js
 * HR Policy – list, upload form, group visibility accordion
 */

const HP = (() => {
  'use strict';

  const API = 'API/hr_policy_api.php';
  const $   = id => document.getElementById(id);

  /* ── State ─────────────────────────────────────────────── */
  let editingId     = null;
  let chosenFile    = null;
  // Track which accordion sections are open (all open by default)
  const openSections = new Set(['org','location','department','category','group','sub_group']);

  /* ── Init ───────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    loadList();
    // Default: all sections expanded, chevrons pointing up
    openSections.forEach(k => {
      const chev = $(`chev_${k}`);
      if (chev) chev.classList.add('down');
    });
  });

  /* ════════════════════════════════════════════════
     LIST
  ════════════════════════════════════════════════ */
  function loadList() {
    fetch(`${API}?action=list`)
      .then(r => r.json())
      .then(res => {
        if (res.success) renderList(res.data || []);
        else showToast(res.message, 'error');
      })
      .catch(() => showToast('Network error.', 'error'));
  }

  function renderList(policies) {
    const empty = $('hpEmpty');
    const list  = $('hpList');
    const cards = $('hpCards');

    if (!policies.length) {
      empty.style.display = 'flex';
      list.style.display  = 'none';
      return;
    }

    empty.style.display = 'none';
    list.style.display  = 'block';
    cards.innerHTML     = '';

    policies.forEach(p => {
      const card = document.createElement('div');
      card.className = 'hp-card';
      card.innerHTML = `
        <p class="hp-card-name">${esc(p.policy_name)}</p>
        <p class="hp-card-file">📄 ${esc(p.file_name)}</p>
        <p class="hp-card-date">${esc(p.created_date)}</p>
        <div class="hp-card-actions">
          <a href="${esc(p.file_url)}" target="_blank" class="hp-card-btn primary">View</a>
          <button class="hp-card-btn" onclick="HP.openEdit(${p.id})">Edit</button>
          <button class="hp-card-btn danger" onclick="HP.deletePolicy(${p.id}, '${esc(p.policy_name)}')">Delete</button>
        </div>`;
      cards.appendChild(card);
    });
  }

  /* ════════════════════════════════════════════════
     OPEN FORM (new)
  ════════════════════════════════════════════════ */
  function openForm() {
    editingId  = null;
    chosenFile = null;
    clearForm();
    $('hpBtnUpload').textContent  = 'Upload';
    $('hpBtnUpload').disabled     = false;
    showView('hpViewForm');
  }

  /* ════════════════════════════════════════════════
     OPEN EDIT
  ════════════════════════════════════════════════ */
  function openEdit(id) {
    fetch(`${API}?action=get&id=${id}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) { showToast(res.message, 'error'); return; }
        const d = res.data;
        editingId = d.id;
        $('fPolicyName').value  = d.policy_name;
        $('fEditingId').value   = d.id;
        $('fManualGroups').checked = !!d.manual_groups;
        $('hpGroupsPanel').style.display = d.manual_groups ? 'block' : 'none';

        // Pre-tick group checkboxes
        if (d.manual_groups && d.groups.length) {
          d.groups.forEach(g => {
            const chk = document.querySelector(
              `.hp-check-${g.group_type}[value="${g.group_id}"]`
            );
            if (chk) chk.checked = true;
          });
          // Sync select-all states
          ['org','location','department','category','group','sub_group'].forEach(k => syncSelectAll(k));
        }

        // Update dropzone to show existing file
        $('hpFileName').textContent = `📄 ${d.file_name} (keep or choose new)`;
        $('hpFileName').style.display = 'block';
        $('hpBtnUpload').textContent  = 'Save';
        showView('hpViewForm');
      });
  }

  /* ════════════════════════════════════════════════
     CLEAR FORM
  ════════════════════════════════════════════════ */
  function clearForm() {
    $('fPolicyName').value     = '';
    $('fEditingId').value      = '';
    $('fManualGroups').checked = false;
    $('hpGroupsPanel').style.display = 'none';
    $('hpFileName').style.display    = 'none';
    $('hpFileName').textContent      = '';
    $('hpFileInput').value           = '';
    chosenFile = null;
    // Uncheck all group checkboxes
    document.querySelectorAll('.hp-groups-container input[type="checkbox"]')
      .forEach(c => c.checked = false);
  }

  /* ════════════════════════════════════════════════
     FILE HANDLING
  ════════════════════════════════════════════════ */
  function onFileChosen(input) {
    const file = input.files[0];
    if (!file) return;
    chosenFile = file;
    $('hpFileName').textContent = `📄 ${file.name}`;
    $('hpFileName').style.display = 'flex';
  }

  function onDragOver(e)  { e.preventDefault(); $('hpDropzone').classList.add('drag-over'); }
  function onDragLeave(e) { $('hpDropzone').classList.remove('drag-over'); }
  function onDrop(e) {
    e.preventDefault();
    $('hpDropzone').classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) {
      // Inject into file input
      const dt = new DataTransfer();
      dt.items.add(file);
      $('hpFileInput').files = dt.files;
      onFileChosen($('hpFileInput'));
    }
  }

  /* ════════════════════════════════════════════════
     TOGGLE GROUP VISIBILITY
  ════════════════════════════════════════════════ */
  function toggleGroups(on) {
    $('hpGroupsPanel').style.display = on ? 'block' : 'none';
  }

  /* ════════════════════════════════════════════════
     ACCORDION SECTIONS
  ════════════════════════════════════════════════ */
  function toggleSection(key) {
    const body = $(`body_${key}`);
    const chev = $(`chev_${key}`);
    if (!body) return;
    const isOpen = openSections.has(key);
    if (isOpen) {
      body.style.display = 'none';
      chev.classList.remove('down');
      openSections.delete(key);
    } else {
      body.style.display = 'block';
      chev.classList.add('down');
      openSections.add(key);
    }
  }

  /* ════════════════════════════════════════════════
     SELECT ALL / SYNC
  ════════════════════════════════════════════════ */
  function selectAll(key, checked) {
    document.querySelectorAll(`.hp-check-${key}`)
      .forEach(c => c.checked = checked);
  }

  function onCheckChange(key) { syncSelectAll(key); }

  function syncSelectAll(key) {
    const all     = document.querySelectorAll(`.hp-check-${key}`);
    const checked = [...all].filter(c => c.checked);
    const sa = document.querySelector(`.hp-select-all[data-group="${key}"]`);
    if (!sa) return;
    sa.checked       = all.length > 0 && checked.length === all.length;
    sa.indeterminate = checked.length > 0 && checked.length < all.length;
  }

  /* ════════════════════════════════════════════════
     SUBMIT
  ════════════════════════════════════════════════ */
  function submitForm() {
    const name = $('fPolicyName').value.trim();
    if (!name) { showToast('Policy Name is required.', 'error'); return; }
    if (!editingId && !chosenFile) { showToast('Please choose a file to upload.', 'error'); return; }

    const btn = $('hpBtnUpload');
    btn.disabled    = true;
    btn.textContent = editingId ? 'Saving…' : 'Uploading…';

    const fd = new FormData();
    fd.append('action',        editingId ? 'update' : 'upload');
    if (editingId) fd.append('id', editingId);
    fd.append('policy_name',   name);
    fd.append('manual_groups', $('fManualGroups').checked ? 1 : 0);
    if (chosenFile) fd.append('policy_file', chosenFile);

    // Collect group selections
    const groups = {};
    ['org','location','department','category','group','sub_group'].forEach(key => {
      const ids = [...document.querySelectorAll(`.hp-check-${key}:checked`)].map(c => +c.value);
      if (ids.length) groups[key] = ids;
    });
    fd.append('groups', JSON.stringify(groups));

    fetch(API, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          showToast(res.message, 'success');
          editingId = null;
          chosenFile = null;
          loadList();
          showView('hpViewList');
        } else {
          showToast(res.message || 'Failed.', 'error');
          btn.disabled    = false;
          btn.textContent = editingId ? 'Save' : 'Upload';
        }
      })
      .catch(() => {
        showToast('Network error.', 'error');
        btn.disabled    = false;
        btn.textContent = editingId ? 'Save' : 'Upload';
      });
  }

  /* ════════════════════════════════════════════════
     DELETE
  ════════════════════════════════════════════════ */
function deletePolicy(id, name) {

    Swal.fire({
        title: 'Delete Policy?',
        text: `Are you sure you want to delete "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true
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
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'Policy deleted successfully.',
                    timer: 1500,
                    showConfirmButton: false
                });

                loadList();

            } else {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.message || 'Unable to delete policy.'
                });

            }

        })
        .catch(() => {

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Something went wrong. Please try again.'
            });

        });

    });
}
  /* ════════════════════════════════════════════════
     NAVIGATION
  ════════════════════════════════════════════════ */
  function backToList() {
    editingId  = null;
    chosenFile = null;
    showView('hpViewList');
    loadList();
  }

  function goBack() { window.history.back(); }

  function showView(id) {
    ['hpViewList','hpViewForm'].forEach(v => {
      $(v).style.display = v === id ? 'block' : 'none';
    });
  }

  /* ── Utilities ──────────────────────────────────────────── */
  function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                          .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  let toastTimer;
  function showToast(msg, type = '') {
    let t = document.querySelector('.hp-toast');
    if (!t) { t = document.createElement('div'); t.className = 'hp-toast'; document.body.appendChild(t); }
    t.className = `hp-toast ${type}`; t.textContent = msg;
    clearTimeout(toastTimer);
    requestAnimationFrame(() => {
      t.classList.add('show');
      toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
    });
  }

  return {
    openForm, openEdit, backToList, goBack,
    toggleGroups, toggleSection, selectAll, onCheckChange,
    onFileChosen, onDragOver, onDragLeave, onDrop,
    submitForm, deletePolicy,
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