<?php require_once __DIR__ . '/functions.php'; ?>

<aside class="sidebar" id="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0F1020" stroke-width="2.5">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
      </svg>
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

    <a class="nav-item <?= isActivePage('dashboard') ?>" href="dashboard.php">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
      </svg>
      Dashboard
    </a>

    <a class="nav-item <?= isActivePage('employees') ?>" href="employees.php">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
      Employee List
      <span class="nb" style="background:var(--blue-l);color:var(--blue)">65</span>
    </a>

    <a class="nav-item <?= isActivePage('approvals') ?>" href="approvals.php">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
      </svg>
      Approvals
      <span class="nb" style="background:var(--red-l);color:var(--red)">3</span>
    </a>

    <a class="nav-item <?= isActivePage('attendance') ?>" href="attendance.php">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
      </svg>
      Attendance
    </a>

    <a class="nav-item <?= isActivePage('leave') ?>" href="leave.php">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      Leave
    </a>

    <div class="sb-section">FINANCE</div>

    <a class="nav-item <?= isActivePage('payroll') ?>" href="payroll.php">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
      Payroll
    </a>

    <a class="nav-item <?= isActivePage('taxes') ?>" href="taxes.php">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
      </svg>
      Taxes
    </a>

    <a class="nav-item <?= isActivePage('reports') ?>" href="reports.php">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
      </svg>
      Reports
    </a>

    <div class="sb-section">SYSTEM</div>

    <a class="nav-item <?= isActivePage('import') ?>" href="import.php">Data Import</a>
    <a class="nav-item <?= isActivePage('users') ?>" href="users.php">Users</a>
    <a class="nav-item <?= isActivePage('config') ?>" href="config.php">Configuration</a>
  </nav>

  <div style="padding:12px;border-top:1px solid rgba(255,255,255,.05)">
    <div style="display:flex;align-items:center;gap:9px;padding:8px">
      <div class="av" style="background:var(--y);color:var(--navy);width:34px;height:34px;font-size:12px">AD</div>
      <div style="flex:1;min-width:0">
        <div style="color:#fff;font-size:13px;font-weight:600">Admin</div>
        <div style="color:#5C6080;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">ramkrishnaivf.in</div>
      </div>
      <a href="logout.php" style="background:none;border:none;cursor:pointer;color:#5C6080;text-decoration:none" title="Logout">
        Logout
      </a>
    </div>
  </div>
</aside>