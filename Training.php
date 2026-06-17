<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Training';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<div class="training-wrapper">

  <!-- Top Bar -->
  <div class="training-topbar">
    <span class="training-page-title">Training</span>
    <a href="#" class="training-tab-link active">List of Assigned Training</a>
  </div>

  <!-- Card -->
  <div class="training-card">
    <div class="training-card-header">
      <span class="training-section-title">Assigned Trainings</span>
    </div>

    <!-- Empty State -->
    <div class="training-empty-state">
      <div class="training-empty-icon">
        <!-- Document / list illustration -->
        <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="40" cy="44" r="34" fill="#E8EDF5"/>
          <!-- Document body -->
          <rect x="22" y="26" width="36" height="42" rx="3" fill="#ffffff" stroke="#C8D3E8" stroke-width="1.5"/>
          <!-- Dark header bar -->
          <rect x="22" y="26" width="36" height="10" rx="3" fill="#4A5568"/>
          <rect x="34" y="26" width="24" height="10" fill="#4A5568"/>
          <!-- Lines -->
          <rect x="28" y="44" width="20" height="3" rx="1.5" fill="#4299E1"/>
          <rect x="28" y="51" width="15" height="3" rx="1.5" fill="#4299E1"/>
          <rect x="28" y="58" width="18" height="3" rx="1.5" fill="#90CDF4"/>
        </svg>
      </div>
      <p class="training-empty-text">No Assigned Trainings!</p>
    </div>
  </div>

</div>

<style>
/* ── Wrapper ── */
.training-wrapper {
  padding: 7px 16px;
  min-height: calc(100vh - 60px);
  background: #f0f2f5;
  font-family: 'Segoe UI', Arial, sans-serif;
}

/* ── Top Bar ── */
.training-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.training-page-title {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
}

.training-tab-link {
  font-size: 13px;
  font-weight: 500;
  color: #3182ce;
  text-decoration: none;
  padding-bottom: 4px;
  border-bottom: 2px solid #3182ce;
  transition: color 0.2s;
}

.training-tab-link:hover {
  color: #2b6cb0;
  border-color: #2b6cb0;
}

/* ── Card ── */
.training-card {
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
  min-height: 480px;
  display: flex;
  flex-direction: column;
}

.training-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid #edf2f7;
}

.training-section-title {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}

/* ── Empty State ── */
.training-empty-state {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  gap: 14px;
}

.training-empty-icon svg {
  display: block;
}

.training-empty-text {
  margin: 0;
  font-size: 13px;
  color: #a0aec0;
  font-weight: 400;
}
</style>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>