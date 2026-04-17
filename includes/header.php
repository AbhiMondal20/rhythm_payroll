<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $page_title ?? 'Dashboard' ?> — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<?php if (!empty($extra_head)) echo $extra_head; ?>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content" id="mainContent">
  <header class="topbar">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;gap:12px">
      <div style="display:flex;align-items:center;gap:12px">
        <button onclick="toggleSidebar()" class="btn" style="padding:7px 9px">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <span style="font-weight:600;font-size:15px;color:#1a1a2e"><?= APP_NAME ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <a href="employees.php" class="btn">+ Add Employee</a>
        <a href="payroll.php"   class="btn btn-primary">Run Payroll</a>
        <button style="position:relative;padding:7px 9px" class="btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="notif-dot"></span>
        </button>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 12px;border:1px solid #E5E7EB;border-radius:8px;cursor:pointer">
          <div class="avatar" style="background:var(--yellow);color:var(--navy);font-size:11px">AD</div>
          <span style="font-size:13px;font-weight:500;color:#374151">Admin</span>
        </div>
      </div>
    </div>
  </header>
  <main style="padding:20px">
