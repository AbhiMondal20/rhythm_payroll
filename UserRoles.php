<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/db_client.php';
require_once 'includes/config.php';
$page_title = 'User Roles';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
    /* ============================================================
       user_roles.css  –  PerkPayroll-style Roles & Permissions
       ============================================================ */
    .cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #E5E7EB;background:#fff;overflow-x:auto;scrollbar-width:none;}
    .cfg-tabs::-webkit-scrollbar{display:none;}
    .cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6B7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px;}
    .cfg-tab:hover{color:#111827;}
    .cfg-tab.active{color:#2563EB;border-bottom-color:#2563EB;font-weight:600;}

    .ur-view { background: #fff; font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; }

    /* ---------- LIST VIEW ---------- */
    .ur-list-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px 14px; }
    .ur-list-title { font-size: 15px; font-weight: 700; color: #0F172A; }
    .ur-btn-primary { display: flex; align-items: center; gap: 6px; background: #2563EB; color: #fff; border: none; border-radius: 6px; padding: 9px 18px; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: background .15s; }
    .ur-btn-primary:hover { background: #1D4ED8; }
    .ur-btn-primary:disabled { opacity: .55; cursor: not-allowed; }

    .ur-table-wrap { border-top: 1px solid #E2E8F0; border-bottom: 1px solid #E2E8F0; overflow-x: auto; }
    .ur-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .ur-table thead tr { background: #F1F5F9; }
    .ur-table th { padding: 11px 16px; text-align: left; font-size: 12px; font-weight: 700; color: #64748B; border-bottom: 1px solid #E2E8F0; }
    .ur-th-sno { width: 60px; }
    .ur-table td { padding: 13px 16px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
    .ur-table tbody tr:hover td { background: #F8FAFC; }

    .ur-row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
    .ur-del-btn, .ur-chevron-btn { background: none; border: none; cursor: pointer; color: #94A3B8; transition: color .12s; padding: 4px; }
    .ur-del-btn:hover { color: #EF4444; }
    .ur-chevron-btn:hover { color: #2563EB; }
    
    .ur-loading-row { text-align: center; padding: 40px !important; color: #94A3B8; font-size: 13px; }

    .ur-pagination { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 12px 16px; font-size: 12.5px; color: #64748B; }
    .ur-page-info { margin-right: auto; }
    .ur-page-show { display: flex; align-items: center; gap: 6px; }
    .ur-per-page { border: 1.5px solid #CBD5E1; border-radius: 4px; padding: 3px 6px; }
    .ur-page-nav { display: flex; gap: 2px; }
    .ur-page-btn { width: 28px; height: 28px; border: 1.5px solid #E2E8F0; background: #fff; border-radius: 4px; cursor: pointer; transition: background .1s; }
    .ur-page-btn:hover:not(:disabled) { background: #F0F9FF; color: #2563EB; border-color:#93C5FD; }
    .ur-page-btn.active { background: #2563EB; color: #fff; border-color: #2563EB; }

    /* ---------- DETAIL/FORM VIEW ---------- */
    .ur-detail-header, .ur-form-heading { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px 14px; border-bottom: 1px solid #E2E8F0; font-size: 15px; font-weight: 700; color: #0F172A; }
    .ur-btn-edit { background: none; border: none; color: #2563EB; font-weight: 600; cursor: pointer; padding: 4px 8px; border-radius: 4px; display:flex; gap:6px; align-items:center; font-size:13px; }
    .ur-btn-edit:hover { background: #EFF6FF; }

    .ur-detail-fields, .ur-form-fields { padding: 20px 24px 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px 48px; }
    .ur-full-width { grid-column: 1 / -1; }
    .ur-field-label, .ur-form-label { font-size: 12.5px; color: #64748B; margin-bottom: 6px; font-weight: 500; display:block; }
    .ur-field-value { font-size: 14px; color: #0F172A; font-weight: 500; padding-bottom: 6px; border-bottom: 1px solid #E2E8F0; min-height: 22px; }
    .ur-form-input { width: 100%; border: none; border-bottom: 1.5px solid #CBD5E1; background: transparent; padding: 6px 0; font-size: 14px; outline: none; transition: border-color .15s; }
    .ur-form-input:focus { border-bottom-color: #2563EB; }

    /* ---------- ACCESS MATRIX ---------- */
    .ur-permissions-section { padding: 16px 24px 24px; border-top: 1.5px dashed #E2E8F0; }
    .ur-section-label { font-size: 12px; font-weight: 700; letter-spacing: .6px; color: #374151; margin-bottom: 14px; }
    
    .ur-matrix-table { width: 100%; border-collapse: collapse; font-size: 13.5px; border: 1px solid #E2E8F0; border-radius: 6px; overflow: hidden; }
    .ur-matrix-table th { background: #F8FAFC; text-align: center; padding: 10px; font-size: 12.5px; font-weight: 600; color: #475569; border-bottom: 1px solid #E2E8F0; }
    .ur-matrix-table th:first-child { text-align: left; padding-left: 16px; }
    .ur-matrix-table td { text-align: center; padding: 10px; border-bottom: 1px solid #E2E8F0; color: #1E293B; }
    .ur-matrix-table td:first-child { text-align: left; padding-left: 16px; font-weight: 500; }
    .ur-matrix-table tbody tr:last-child td { border-bottom: none; }
    .ur-matrix-table tbody tr:hover { background: #F1F5F9; }
    .ur-matrix-checkbox { width: 15px; height: 15px; accent-color: #2563EB; cursor: pointer; }
    .ur-chk-lbl { display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; }

    .ur-form-actions { display: flex; justify-content: flex-end; gap: 12px; padding: 20px 24px; border-top: 1px solid #F1F5F9; }
    .ur-btn-cancel { padding: 9px 24px; border: 1.5px solid #CBD5E1; background: #fff; color: #64748B; border-radius: 6px; font-weight: 600; cursor: pointer; }

    .ur-toast { position: fixed; bottom: 28px; right: 28px; background: #1E293B; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 13.5px; z-index: 9999; opacity: 0; transform: translateY(12px); transition: all .25s; pointer-events: none; }
    .ur-toast.success { background: #166534; }
    .ur-toast.error { background: #991B1B; }
    .ur-toast.show { opacity: 1; transform: translateY(0); }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden;">
    <div class="cfg-tabs">
        <?php foreach(['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
        <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k==='Others'?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>


    <div class="ur-view" id="viewList">
        <div class="ur-list-header">
            <span class="ur-list-title">Roles</span>
            <button class="ur-btn-primary" onclick="UR.openNewForm()"><span>+</span> Add New Role</button>
        </div>
        <div class="ur-table-wrap">
            <table class="ur-table">
                <thead>
                    <tr>
                        <th class="ur-th-sno">S NO.</th>
                        <th>ROLE CODE</th>
                        <th>ROLE NAME</th>
                        <th>REMARKS</th>
                        <th style="width:90px;"></th>
                    </tr>
                </thead>
                <tbody id="urTbody">
                    <tr><td colspan="5" class="ur-loading-row">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="ur-pagination">
            <div class="ur-page-info" id="urPageInfo">Showing 0 entries</div>
            <div class="ur-page-show">Show <select class="ur-per-page" onchange="UR.setPerPage(this.value)"><option>5</option><option>10</option><option>25</option></select> entries</div>
            <div class="ur-page-nav" id="urPageNav"></div>
        </div>
    </div>

    <div class="ur-view" id="viewDetail" style="display:none;">
        <div class="ur-detail-header">
            <span id="dRoleNameHeading"></span>
            <button class="ur-btn-edit" onclick="UR.openEditForm()">Edit Details</button>
        </div>
        <div class="ur-detail-fields">
            <div><span class="ur-field-label">Role Code</span><div class="ur-field-value" id="dRoleCode"></div></div>
            <div><span class="ur-field-label">Role Name</span><div class="ur-field-value" id="dRoleName"></div></div>
            <div class="ur-full-width"><span class="ur-field-label">Remarks</span><div class="ur-field-value" id="dRemarks"></div></div>
        </div>
        <div class="ur-permissions-section">
            <div class="ur-section-label">ACCESS PERMISSIONS</div>
            <table class="ur-matrix-table">
                <thead>
                    <tr>
                        <th>Module / Page Name</th>
                        <th>View</th>
                        <th>Add</th>
                        <th>Edit</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody id="dMatrixBody"></tbody>
            </table>
        </div>
    </div>

    <div class="ur-view" id="viewForm" style="display:none;">
        <div class="ur-form-heading" id="fFormHeading">New Role</div>
        <div class="ur-form-fields">
            <div><label class="ur-form-label">Role Code</label><input type="text" class="ur-form-input" id="fRoleCode"></div>
            <div><label class="ur-form-label">Role Name</label><input type="text" class="ur-form-input" id="fRoleName"></div>
            <div class="ur-full-width"><label class="ur-form-label">Remarks</label><input type="text" class="ur-form-input" id="fRemarks"></div>
        </div>
        <div class="ur-permissions-section">
            <div class="ur-section-label">ACCESS PERMISSIONS</div>
            <table class="ur-matrix-table">
                <thead>
                    <tr>
                        <th>Module / Page Name</th>
                        <th><label class="ur-chk-lbl"><input type="checkbox" class="ur-matrix-checkbox" onchange="UR.toggleCol('can_view', this.checked)"> View</label></th>
                        <th><label class="ur-chk-lbl"><input type="checkbox" class="ur-matrix-checkbox" onchange="UR.toggleCol('can_add', this.checked)"> Add</label></th>
                        <th><label class="ur-chk-lbl"><input type="checkbox" class="ur-matrix-checkbox" onchange="UR.toggleCol('can_edit', this.checked)"> Edit</label></th>
                        <th><label class="ur-chk-lbl"><input type="checkbox" class="ur-matrix-checkbox" onchange="UR.toggleCol('can_delete', this.checked)"> Delete</label></th>
                    </tr>
                </thead>
                <tbody id="fMatrixBody"></tbody>
            </table>
        </div>
        <div class="ur-form-actions">
            <button class="ur-btn-cancel" onclick="UR.cancelForm()">Cancel</button>
            <button class="ur-btn-primary" id="fBtnSubmit" onclick="UR.submitForm()">Submit</button>
        </div>
    </div>
</div>
<script>
const UR = (() => {
    'use strict';
    const API = 'API/user_roles_api.php';
    const $ = id => document.getElementById(id);

    let allRoles = [], allPages = [], accessData = {};
    let currentPage = 1, perPage = 5, viewingId = null, editingId = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadRoles();
        loadPages();
    });

    function loadRoles() {
        fetch(`${API}?action=list`).then(r=>r.json()).then(res => {
            if(res.success) { allRoles = res.data; renderTable(); }
        });
    }

    function loadPages() {
        fetch(`${API}?action=pages_list`).then(r=>r.json()).then(res => {
            if(res.success) { allPages = res.data; initAccessData(); }
        });
    }

    function initAccessData() {
        accessData = {};
        allPages.forEach(p => {
            accessData[p.page_name] = { module_key: p.module_key, can_view: 0, can_add: 0, can_edit: 0, can_delete: 0 };
        });
    }

    function renderTable() {
        const tbody = $('urTbody');
        if(!allRoles.length) { tbody.innerHTML = `<tr><td colspan="5" class="ur-loading-row">No roles found.</td></tr>`; return; }
        
        const start = (currentPage - 1) * perPage;
        const slice = allRoles.slice(start, start + perPage);
        tbody.innerHTML = slice.map((r, i) => `
            <tr>
                <td>${start + i + 1}</td>
                <td>${esc(r.role_code)}</td>
                <td>${esc(r.role_name)}</td>
                <td>${esc(r.remarks)}</td>
                <td>
                    <div class="ur-row-actions">
                        <button class="ur-del-btn" onclick="UR.deleteRole(${r.id}, '${esc(r.role_name)}')">✕</button>
                        <button class="ur-chevron-btn" onclick="UR.openDetail(${r.id})">›</button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        $('urPageInfo').textContent = `Showing ${start+1} to ${Math.min(start+perPage, allRoles.length)} of ${allRoles.length}`;
        renderPageNav(Math.ceil(allRoles.length / perPage));
    }

    function renderPageNav(pages) {
        const nav = $('urPageNav');
        nav.innerHTML = '';
        for(let p=1; p<=pages; p++){
            const btn = document.createElement('button');
            btn.className = 'ur-page-btn' + (p === currentPage ? ' active' : '');
            btn.textContent = p;
            btn.onclick = () => { currentPage = p; renderTable(); };
            nav.appendChild(btn);
        }
    }

    function openDetail(id) {
        viewingId = id;
        fetch(`${API}?action=get&id=${id}`).then(r=>r.json()).then(res => {
            if(!res.success) return showToast(res.message, 'error');
            const d = res.data;
            $('dRoleNameHeading').textContent = d.role_name;
            $('dRoleCode').textContent = d.role_code;
            $('dRoleName').textContent = d.role_name;
            $('dRemarks').textContent = d.remarks || '—';
            
            $('dMatrixBody').innerHTML = d.access.map(a => `
                <tr>
                    <td>${esc(a.page_name)}</td>
                    <td>${a.can_view == 1 ? '✓' : '-'}</td>
                    <td>${a.can_add == 1 ? '✓' : '-'}</td>
                    <td>${a.can_edit == 1 ? '✓' : '-'}</td>
                    <td>${a.can_delete == 1 ? '✓' : '-'}</td>
                </tr>
            `).join('');
            showView('viewDetail');
        });
    }
    function openNewForm() {
        editingId = null;
        $('fFormHeading').textContent = 'New Role';
        $('fRoleCode').value = ''; $('fRoleName').value = ''; $('fRemarks').value = '';
        initAccessData();
        renderFormMatrix();
        showView('viewForm');
    }

    function openEditForm() {
        if(!viewingId) return;
        fetch(`${API}?action=get&id=${viewingId}`).then(r=>r.json()).then(res => {
            const d = res.data;
            editingId = d.id;
            $('fFormHeading').textContent = 'Edit: ' + d.role_name;
            $('fRoleCode').value = d.role_code;
            $('fRoleName').value = d.role_name;
            $('fRemarks').value = d.remarks || '';
            
            initAccessData();
            d.access.forEach(a => {
                if(accessData[a.page_name]) {
                    accessData[a.page_name].can_view = parseInt(a.can_view);
                    accessData[a.page_name].can_add = parseInt(a.can_add);
                    accessData[a.page_name].can_edit = parseInt(a.can_edit);
                    accessData[a.page_name].can_delete = parseInt(a.can_delete);
                }
            });
            renderFormMatrix();
            showView('viewForm');
        });
    }

    function renderFormMatrix() {
        const tbody = $('fMatrixBody');
        tbody.innerHTML = '';
        allPages.forEach(p => {
            const data = accessData[p.page_name];
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${esc(p.page_name)}</td>
                <td><input type="checkbox" class="ur-matrix-checkbox" data-page="${p.page_name}" data-col="can_view" ${data.can_view?'checked':''}></td>
                <td><input type="checkbox" class="ur-matrix-checkbox" data-page="${p.page_name}" data-col="can_add" ${data.can_add?'checked':''}></td>
                <td><input type="checkbox" class="ur-matrix-checkbox" data-page="${p.page_name}" data-col="can_edit" ${data.can_edit?'checked':''}></td>
                <td><input type="checkbox" class="ur-matrix-checkbox" data-page="${p.page_name}" data-col="can_delete" ${data.can_delete?'checked':''}></td>
            `;
            tbody.appendChild(tr);
        });

        // Attach listeners to individual checkboxes
        tbody.querySelectorAll('input[type="checkbox"]').forEach(chk => {
            chk.addEventListener('change', (e) => {
                const page = e.target.dataset.page;
                const col = e.target.dataset.col;
                accessData[page][col] = e.target.checked ? 1 : 0;
            });
        });
    }

    function toggleCol(col, isChecked) {
        Object.keys(accessData).forEach(page => accessData[page][col] = isChecked ? 1 : 0);
        renderFormMatrix();
    }

function submitForm() {
        const code = $('fRoleCode').value.trim();
        const name = $('fRoleName').value.trim();
        
        if(!code || !name) {
            return showToast('Role Code and Name are required.', 'error');
        }

        // 1. Disable the button so the user can't click it twice
        const btn = $('fBtnSubmit');
        btn.disabled = true;
        btn.textContent = 'Submitting...';

        // 2. Prepare the payload
        const payload = {
            action: editingId ? 'update' : 'add',
            role_code: code,
            role_name: name,
            remarks: $('fRemarks').value.trim(),
            access: Object.entries(accessData).map(([page, perms]) => ({ page_name: page, ...perms }))
        };

        if (editingId) {
            payload.id = editingId;
        }

        // 3. Format as FormData
        const fd = new FormData();
        Object.entries(payload).forEach(([k, v]) => {
            fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v);
        });

        console.log("Submitting Data:", payload); // Debugging log

        // 4. Send the Request
        fetch(API, { method: 'POST', body: fd })
            .then(async (r) => {
                const rawText = await r.text(); // Get raw response first
                try {
                    return JSON.parse(rawText); // Try to parse it as JSON
                } catch (e) {
                    console.error("Server returned Invalid JSON:", rawText);
                    throw new Error("Server error: Check the console for PHP output.");
                }
            })
            .then(res => {
                if(res.success) { 
                    showToast(res.message, 'success'); 
                    editingId = null; 
                    loadRoles(); 
                    showView('viewList'); 
                } else {
                    showToast(res.message || "Submission failed.", 'error');
                }
            })
            .catch(err => {
                console.error("Submit Error:", err);
                showToast("An error occurred. Please check the console.", 'error');
            })
            .finally(() => {
                // Always re-enable the button when done
                btn.disabled = false;
                btn.textContent = 'Submit';
            });
    }

   function deleteRole(id, name) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Delete role "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            fetch(API, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Role has been deleted successfully.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        loadRoles();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message
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
        }
    });
}
    function cancelForm() { showView(viewingId && editingId ? 'viewDetail' : 'viewList'); }
    function setPerPage(val) { perPage = parseInt(val); currentPage = 1; renderTable(); }
    function showView(id) { ['viewList','viewDetail','viewForm'].forEach(v => $(v).style.display = v===id?'block':'none'); }
    function esc(s) { return String(s??'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[m]); }
    
    let toastTimer;
    function showToast(msg, type) {
        let t=$('.ur-toast'); if(!t){ t=document.createElement('div'); t.className='ur-toast'; document.body.appendChild(t); }
        t.className=`ur-toast ${type}`; t.textContent=msg;
        clearTimeout(toastTimer); requestAnimationFrame(() => { t.classList.add('show'); toastTimer = setTimeout(()=>t.classList.remove('show'),3000); });
    }

    return { openNewForm, openEditForm, openDetail, submitForm, cancelForm, setPerPage, deleteRole, toggleCol };
})();
</script>
<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>