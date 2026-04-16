<?php require_once __DIR__ . '/functions.php'; ?>
<?php $title = $title ?? ucfirst(currentPageName()); ?>

<div class="main" id="mainArea">
  <header class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button onclick="toggleSidebar()" style="background:none;border:1px solid var(--border);border-radius:8px;padding:6px 8px;cursor:pointer;color:var(--muted)">
        ☰
      </button>
      <span id="pageTitle" style="font-weight:700;font-size:15px;color:var(--text)"><?= e($title) ?></span>
    </div>

    <div style="display:flex;align-items:center;gap:8px">
      <button onclick="openModal('addEmployeeModal')" style="display:flex;align-items:center;gap:6px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:7px 13px;cursor:pointer;font-size:13px;font-weight:500;color:var(--text)">
        + Add Employee
      </button>
      <button onclick="openModal('runPayrollModal')" style="background:var(--y);border:none;border-radius:8px;padding:7px 13px;cursor:pointer;font-size:13px;font-weight:600;color:var(--navy)">
        Run Payroll
      </button>
    </div>
  </header>

  <div class="page-content"></div>