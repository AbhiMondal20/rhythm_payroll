
<?php /* =====================================================
   FILE: payroll.php
   ===================================================== */ ?>
<?php
require_once 'includes/config.php';
$page_title = 'Payroll';
$month = $_GET['month'] ?? 'April 2026';

$processed = array_filter($payroll, fn($r) => $r['status'] === 'processed');
$pending   = array_filter($payroll, fn($r) => $r['status'] === 'pending');
$total_net = array_sum(array_column($payroll, 'net'));
$total_pf  = array_sum(array_column($payroll, 'pf'));
$total_esi = array_sum(array_column($payroll, 'esi'));
$total_pt  = array_sum(array_column($payroll, 'pt'));

ob_start();
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div><h1 class="page-title">Payroll</h1><p class="page-sub"><?= htmlspecialchars($month) ?></p></div>
  <div style="display:flex;gap:8px">
    <button class="btn">Export CSV</button>
    <button class="btn btn-primary">▶ Run Payroll</button>
  </div>
</div>

<div class="grid-4" style="margin-bottom:20px">
  <?php
  $pstats = [
    ['l'=>'TOTAL NET PAID','v'=>fmt_inr($total_net),'bg'=>'#D1FAE5','c'=>'#059669'],
    ['l'=>'PF DEDUCTED',   'v'=>fmt_inr($total_pf), 'bg'=>'#EDE9FE','c'=>'#7C3AED'],
    ['l'=>'ESI DEDUCTED',  'v'=>fmt_inr($total_esi),'bg'=>'#DBEAFE','c'=>'#2563EB'],
    ['l'=>'PT DEDUCTED',   'v'=>fmt_inr($total_pt), 'bg'=>'#FFEDD5','c'=>'#EA580C'],
    ['l'=>'PROCESSED',     'v'=>count($processed),  'bg'=>'#D1FAE5','c'=>'#059669'],
    ['l'=>'PENDING',       'v'=>count($pending),    'bg'=>'#FEF3C7','c'=>'#D97706'],
  ];
  foreach ($pstats as $s): ?>
  <div class="stat-card">
    <p style="font-size:11px;font-weight:600;color:#6B7280;letter-spacing:.3px"><?= $s['l'] ?></p>
    <p style="font-size:24px;font-weight:700;color:#1a1a2e;margin-top:4px"><?= $s['v'] ?></p>
  </div>
  <?php endforeach; ?>
</div>

<div class="section-card">
  <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6">
    <h2 style="font-size:15px;font-weight:700">Payroll Register — <?= htmlspecialchars($month) ?></h2>
  </div>
  <table>
    <thead><tr>
      <th>EMPLOYEE</th><th>DEPT</th>
      <th style="text-align:right">GROSS</th>
      <th style="text-align:right">PF</th>
      <th style="text-align:right">ESI</th>
      <th style="text-align:right">PT</th>
      <th style="text-align:right">NET PAY</th>
      <th style="text-align:center">STATUS</th>
      <th>PAYSLIP</th>
    </tr></thead>
    <tbody>
    <?php foreach ($payroll as $r):
      $processed_row = $r['status'] === 'processed'; ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="avatar" style="background:#EDE9FE;color:#7C3AED;font-size:11px"><?= initials($r['name']) ?></div>
          <span style="font-weight:500"><?= htmlspecialchars($r['name']) ?></span>
        </div>
      </td>
      <td style="color:#6B7280"><?= $r['dept'] ?></td>
      <td style="text-align:right"><?= fmt_inr($r['gross']) ?></td>
      <td style="text-align:right;color:#7C3AED"><?= fmt_inr($r['pf']) ?></td>
      <td style="text-align:right;color:#2563EB"><?= fmt_inr($r['esi']) ?></td>
      <td style="text-align:right;color:#EA580C"><?= fmt_inr($r['pt']) ?></td>
      <td style="text-align:right;font-weight:700;color:#059669"><?= fmt_inr($r['net']) ?></td>
      <td style="text-align:center">
        <span class="badge" style="background:<?= $processed_row ? '#D1FAE5' : '#FEF3C7' ?>;color:<?= $processed_row ? '#065F46' : '#92400E' ?>">
          <?= $processed_row ? '✓ Processed' : '⏳ Pending' ?>
        </span>
      </td>
      <td>
        <?php if ($processed_row): ?>
        <button class="btn" style="padding:4px 10px;font-size:12px">📄 Download</button>
        <?php else: ?>
        <button class="btn btn-primary" style="padding:4px 10px;font-size:12px">Process</button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:#F9FAFB;font-weight:700">
        <td colspan="2" style="padding:12px 20px;color:#374151">TOTAL</td>
        <td style="text-align:right;padding:12px 20px"><?= fmt_inr(array_sum(array_column($payroll,'gross'))) ?></td>
        <td style="text-align:right;padding:12px 20px;color:#7C3AED"><?= fmt_inr($total_pf) ?></td>
        <td style="text-align:right;padding:12px 20px;color:#2563EB"><?= fmt_inr($total_esi) ?></td>
        <td style="text-align:right;padding:12px 20px;color:#EA580C"><?= fmt_inr($total_pt) ?></td>
        <td style="text-align:right;padding:12px 20px;color:#059669"><?= fmt_inr($total_net) ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
</div>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
