<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>perk · Ramkrishna IVF Centre — Payroll & HR</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../includes/assets/style.css">
</head>
<body>

<!-- ═══════════════ APP SHELL ═══════════════ -->
<div id="appShell" style="display:block;">
<div class="mobile-overlay" id="mobOverlay" onclick="closeSidebar()" ></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0F1020" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    </div>
    <div>
      <div style="color:#fff;font-weight:700;font-size:17px;font-family:'Space Mono',monospace">perk</div>
      <div style="color:#5C6080;font-size:9px;letter-spacing:1.5px">PAYROLL · HR</div>
    </div>
  </div>
  <div class="sb-org">
    <div style="color:#5C6080;font-size:9px;font-weight:700;letter-spacing:.5px">ORGANISATION</div>
    <div style="color:#fff;font-size:12.5px;font-weight:600;margin-top:1px">Ramkrishna IVF Centre</div>
  </div>
  <nav style="flex:1;overflow-y:auto;padding:4px 0">
    <div class="sb-section">MAIN</div>
    <a class="nav-item" data-page="dashboard" onclick="nav('dashboard')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a class="nav-item" data-page="employees" onclick="nav('employees')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Employee List
      <span class="nb" style="background:var(--blue-l);color:var(--blue)">65</span>
    </a>
    <a class="nav-item" data-page="approvals" onclick="nav('approvals')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      Approvals
      <span class="nb" style="background:var(--red-l);color:var(--red)">3</span>
    </a>
    <a class="nav-item" data-page="attendance" onclick="nav('attendance')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Attendance
    </a>
    <a class="nav-item" data-page="leave" onclick="nav('leave')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Leave
    </a>
    <div class="sb-section">FINANCE</div>
    <a class="nav-item" data-page="payroll" onclick="nav('payroll')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      Payroll
    </a>
    <a class="nav-item" data-page="taxes" onclick="nav('taxes')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Taxes
    </a>
    <a class="nav-item" data-page="reports" onclick="nav('reports')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Reports
    </a>
    <div class="sb-section">SYSTEM</div>
    <a class="nav-item" data-page="import" onclick="nav('import')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
      Data Import
    </a>
    <a class="nav-item" data-page="users" onclick="nav('users')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Users
    </a>
    <a class="nav-item" data-page="config" onclick="nav('config')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M5.34 17.66l-1.41 1.41M2 12h2M20 12h2M5.34 6.34L3.93 4.93M18.66 17.66l1.41 1.41M12 20v2M12 2v2"/></svg>
      Configuration
    </a>
  </nav>
  <div style="padding:12px;border-top:1px solid rgba(255,255,255,.05)">
    <div style="display:flex;align-items:center;gap:9px;padding:8px">
      <div class="av" style="background:var(--y);color:var(--navy);width:34px;height:34px;font-size:12px">AD</div>
      <div style="flex:1;min-width:0">
        <div style="color:#fff;font-size:13px;font-weight:600">Admin</div>
        <div style="color:#5C6080;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">ramkrishnaivf.in</div>
      </div>
      <button onclick="doLogout()" style="background:none;border:none;cursor:pointer;color:#5C6080" title="Logout">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </button>
    </div>
    <div style="background:rgba(255,224,0,.08);border:1px solid rgba(255,224,0,.15);border-radius:8px;padding:8px 10px;margin-top:6px">
      <div style="color:var(--y);font-size:10px;font-weight:700;letter-spacing:.5px">SUBSCRIPTION</div>
      <div style="color:#8A90B8;font-size:11px;margin-top:2px">Expires 30 Apr 2026</div>
      <div class="pb" style="margin-top:5px"><div class="pf" style="width:92%;background:var(--y)"></div></div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main" id="mainArea">
  <!-- TOPBAR -->
  <header class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button onclick="toggleSidebar()" style="background:none;border:1px solid var(--border);border-radius:8px;padding:6px 8px;cursor:pointer;color:var(--muted)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <span id="pageTitle" style="font-weight:700;font-size:15px;color:var(--text)">Dashboard</span>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <button onclick="openModal('addEmployeeModal')" style="display:flex;align-items:center;gap:6px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:7px 13px;cursor:pointer;font-size:13px;font-weight:500;color:var(--text)">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Employee
      </button>
      <button onclick="runPayroll()" style="background:var(--y);border:none;border-radius:8px;padding:7px 13px;cursor:pointer;font-size:13px;font-weight:600;color:var(--navy)">Run Payroll</button>
      <button style="position:relative;background:none;border:1px solid var(--border);border-radius:8px;padding:6px 8px;cursor:pointer;color:var(--muted)">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="ndot"></span>
      </button>
      <div style="display:flex;align-items:center;gap:8px;padding:5px 10px;border:1px solid var(--border);border-radius:8px;cursor:pointer">
        <div class="av" style="background:var(--y);color:var(--navy);width:26px;height:26px;font-size:11px">AD</div>
        <span style="font-size:13px;font-weight:500">Admin</span>
      </div>
    </div>
  </header>

  <!-- PAGES -->
  <div class="page-content" id="pageContainer"></div>
</div>
</div>

<!-- TOAST -->
<div class="toast" id="toast">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4ADE80" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
  <span id="toastMsg">Done!</span>
</div>

<!-- ADD EMPLOYEE MODAL -->
<div class="modal-bg" id="addEmployeeModal" style="display:none" onclick="closeModalBg(event,'addEmployeeModal')">
<div class="modal">
  <div class="modal-header">
    <h3 style="font-size:16px;font-weight:700">Add New Employee</h3>
    <button onclick="closeModal('addEmployeeModal')" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:20px">×</button>
  </div>
  <div class="modal-body">
    <div class="form-row">
      <div class="form-group"><label>FIRST NAME</label><input type="text" placeholder="e.g. Ananya"></div>
      <div class="form-group"><label>LAST NAME</label><input type="text" placeholder="e.g. Ghosh"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>EMAIL</label><input type="email" placeholder="name@example.com"></div>
      <div class="form-group"><label>PHONE</label><input type="tel" placeholder="+91 98765 43210"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>DEPARTMENT</label>
        <select><option>Medical</option><option>Nursing</option><option>Administration</option><option>Lab Tech</option><option>Accounts</option></select>
      </div>
      <div class="form-group"><label>DESIGNATION</label><input type="text" placeholder="e.g. Sr. Nurse"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>DATE OF JOINING</label><input type="date"></div>
      <div class="form-group"><label>GROSS SALARY (₹)</label><input type="number" placeholder="e.g. 35000"></div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn-sm btn-outline" onclick="closeModal('addEmployeeModal')">Cancel</button>
    <button class="btn-sm btn-yellow" onclick="saveEmployee()">Save Employee</button>
  </div>
</div>
</div>

<!-- RUN PAYROLL MODAL -->
<div class="modal-bg" id="runPayrollModal" style="display:none" onclick="closeModalBg(event,'runPayrollModal')">
<div class="modal">
  <div class="modal-header">
    <h3 style="font-size:16px;font-weight:700">Run Payroll — April 2026</h3>
    <button onclick="closeModal('runPayrollModal')" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:20px">×</button>
  </div>
  <div class="modal-body">
    <div style="background:#F9FAFB;border-radius:10px;padding:16px;margin-bottom:16px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div><div style="font-size:11px;color:var(--muted);font-weight:600">TOTAL EMPLOYEES</div><div style="font-size:20px;font-weight:700;margin-top:2px">65</div></div>
        <div><div style="font-size:11px;color:var(--muted);font-weight:600">GROSS PAYROLL</div><div style="font-size:20px;font-weight:700;margin-top:2px">₹8,42,500</div></div>
        <div><div style="font-size:11px;color:var(--muted);font-weight:600">PF EMPLOYER</div><div style="font-size:20px;font-weight:700;margin-top:2px;color:var(--purple)">₹1,01,100</div></div>
        <div><div style="font-size:11px;color:var(--muted);font-weight:600">NET PAYABLE</div><div style="font-size:20px;font-weight:700;margin-top:2px;color:var(--green)">₹7,23,400</div></div>
      </div>
    </div>
    <p style="font-size:13px;color:var(--muted)">This will process payroll for all 65 active employees for April 2026. Salary slips will be generated and emailed automatically.</p>
  </div>
  <div class="modal-footer">
    <button class="btn-sm btn-outline" onclick="closeModal('runPayrollModal')">Cancel</button>
    <button class="btn-sm btn-yellow" onclick="confirmPayroll()">✓ Confirm & Process</button>
  </div>
</div>
</div>

<script src="../includes/assets/script.js"></script>
</body>
</html>