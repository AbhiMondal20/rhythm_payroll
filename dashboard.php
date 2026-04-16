<?php
$pageTitle = 'Dashboard';
$title = 'Dashboard';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="page">
  <div class="ph">
    <div>
      <h1>Dashboard</h1>
      <p>Thursday, 16 April 2026 · Siliguri, West Bengal</p>
    </div>
  </div>

  <div class="stats-grid">
    <div class="sc">
      <div class="sc-label">HEADCOUNT</div>
      <div class="sc-val">65</div>
      <div class="sc-sub" style="color:var(--green)">+2 this month</div>
    </div>
    <div class="sc">
      <div class="sc-label">AT WORK</div>
      <div class="sc-val">49</div>
      <div class="sc-sub" style="color:var(--green)">75% present</div>
    </div>
    <div class="sc">
      <div class="sc-label">ON LEAVE</div>
      <div class="sc-val">1</div>
      <div class="sc-sub" style="color:#d97706">1.5% of total</div>
    </div>
    <div class="sc">
      <div class="sc-label">ABSENT</div>
      <div class="sc-val">15</div>
      <div class="sc-sub" style="color:var(--red)">23% of total</div>
    </div>
    <div class="sc">
      <div class="sc-label">APR PAYROLL</div>
      <div class="sc-val">₹8.4L</div>
      <div class="sc-sub">↓ 6% vs Mar</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Payroll Cost Trend</h2>
    </div>
    <div class="card-body">
      <canvas id="payrollChart" height="100"></canvas>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const ctx = document.getElementById('payrollChart');
  if (!ctx) return;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr'],
      datasets: [{
        label: 'Payroll',
        data: [9.2, 9.5, 9.8, 9.1, 9.0, 8.4],
        borderColor: '#6D28D9',
        backgroundColor: 'rgba(109,40,217,.08)',
        fill: true,
        tension: .4
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } }
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?>