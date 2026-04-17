
<?php /* =====================================================
   FILE: taxes.php
   ===================================================== */ ?>
<?php
require_once 'includes/config.php';
$page_title = 'Taxes';
$total_pf  = array_sum(array_column($payroll,'pf'));
$total_esi = array_sum(array_column($payroll,'esi'));
$total_pt  = array_sum(array_column($payroll,'pt'));
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<div style="margin-bottom:20px"><h1 class="page-title">Tax & Compliance</h1><p class="page-sub">PF · ESI · Professional Tax — West Bengal</p></div>

<div class="grid-3" style="margin-bottom:20px">
  <?php
  $tcards = [
    ['l'=>'PF DEDUCTED (Apr)','v'=>fmt_inr($total_pf), 'sub'=>'Employee + Employer','bg'=>'#EDE9FE','c'=>'#7C3AED'],
    ['l'=>'ESI DEDUCTED (Apr)','v'=>fmt_inr($total_esi),'sub'=>'@ 0.75% employee','bg'=>'#DBEAFE','c'=>'#2563EB'],
    ['l'=>'PT DEDUCTED (Apr)', 'v'=>fmt_inr($total_pt), 'sub'=>'West Bengal slab','bg'=>'#D1FAE5','c'=>'#059669'],
  ];
  foreach ($tcards as $tc): ?>
  <div class="stat-card" style="border-top:3px solid <?= $tc['c'] ?>">
    <p style="font-size:11px;font-weight:600;color:#6B7280"><?= $tc['l'] ?></p>
    <p style="font-size:28px;font-weight:700;color:<?= $tc['c'] ?>;margin-top:4px"><?= $tc['v'] ?></p>
    <p style="font-size:12px;color:#6B7280;margin-top:4px"><?= $tc['sub'] ?></p>
  </div>
  <?php endforeach; ?>
</div>

<div class="grid-2">
  <div class="section-card">
    <div style="padding:14px 20px;border-bottom:1px solid #F3F4F6"><h2 style="font-size:15px;font-weight:700">PF Contribution Breakup</h2></div>
    <div style="padding:16px 20px">
      <?php
      $pf_rows = [
        ['label'=>'Employee PF (12% of Basic)','pct'=>'12%','color'=>'#7C3AED'],
        ['label'=>'Employer EPS (8.33%)',       'pct'=>'8.33%','color'=>'#2563EB'],
        ['label'=>'Employer EPF (3.67%)',       'pct'=>'3.67%','color'=>'#059669'],
        ['label'=>'Admin Charges (0.5%)',       'pct'=>'0.5%','color'=>'#EA580C'],
      ];
      foreach ($pf_rows as $pr): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #F3F4F6">
        <div style="display:flex;align-items:center;gap:10px">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $pr['color'] ?>;display:inline-block"></span>
          <span style="font-size:13px;color:#374151"><?= $pr['label'] ?></span>
        </div>
        <span class="badge" style="background:#F3F4F6;color:#374151"><?= $pr['pct'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section-card">
    <div style="padding:14px 20px;border-bottom:1px solid #F3F4F6"><h2 style="font-size:15px;font-weight:700">WB Professional Tax Slabs</h2></div>
    <table>
      <thead><tr><th>MONTHLY SALARY RANGE</th><th style="text-align:right">PT / MONTH</th></tr></thead>
      <tbody>
      <?php
      $pt_slabs = [
        ['range'=>'Up to ₹10,000','pt'=>'Nil'],
        ['range'=>'₹10,001 – ₹15,000','pt'=>'₹110'],
        ['range'=>'₹15,001 – ₹25,000','pt'=>'₹130'],
        ['range'=>'₹25,001 – ₹40,000','pt'=>'₹150'],
        ['range'=>'Above ₹40,000','pt'=>'₹200'],
      ];
      foreach ($pt_slabs as $ps): ?>
      <tr>
        <td><?= $ps['range'] ?></td>
        <td style="text-align:right;font-weight:600;color:#EA580C"><?= $ps['pt'] ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>
