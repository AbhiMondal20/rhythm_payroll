<?php
require_once 'includes/config.php';
$page_title  = 'Dashboard';
$extra_head  = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>';
$total_payroll  = array_sum(array_column($payroll, 'net'));
$total_pf       = array_sum(array_column($payroll, 'pf'));
$total_esi      = array_sum(array_column($payroll, 'esi'));
$pending_count  = count(array_filter($payroll, fn($r) => $r['status'] === 'pending'));
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub"><?= date('l, d F Y') ?> · <?= APP_CITY ?></p>
    </div>
</div>

<!-- STATS -->
<div class="grid-4" style="margin-bottom:20px">
    <?php
  $stats = [
    ['label'=>'HEADCOUNT',   'value'=>$attendance_today['total'], 'sub'=>'+2 this month',              'icon_bg'=>'#EDE9FE','icon_color'=>'#7C3AED','sub_color'=>'#059669','border'=>''],
    ['label'=>'AT WORK',     'value'=>$attendance_today['present'],'sub'=>round($attendance_today['present']/$attendance_today['total']*100).'% present','icon_bg'=>'#D1FAE5','icon_color'=>'#059669','sub_color'=>'#6B7280','border'=>'border-left:3px solid #059669'],
    ['label'=>'ON LEAVE',    'value'=>$attendance_today['on_leave'],'sub'=>'Today','icon_bg'=>'#FEF3C7','icon_color'=>'#D97706','sub_color'=>'#6B7280','border'=>'border-left:3px solid #F59E0B'],
    ['label'=>'ABSENT',      'value'=>$attendance_today['absent'], 'sub'=>round($attendance_today['absent']/$attendance_today['total']*100).'% of total','icon_bg'=>'#FEE2E2','icon_color'=>'#DC2626','sub_color'=>'#DC2626','border'=>'border-left:3px solid #DC2626'],
    ['label'=>'APR PAYROLL', 'value'=>'₹'.number_format($total_payroll),'sub'=>'Net disbursed','icon_bg'=>'#DBEAFE','icon_color'=>'#2563EB','sub_color'=>'#6B7280','border'=>''],
  ];
  foreach ($stats as $s):
  ?>
    <div class="stat-card" style="<?= $s['border'] ?>">
        <div style="display:flex;align-items:flex-start;justify-content:space-between">
            <div>
                <p style="font-size:12px;font-weight:600;color:#6B7280;letter-spacing:.3px"><?= $s['label'] ?></p>
                <p style="font-size:26px;font-weight:700;color:#1a1a2e;line-height:1.1;margin-top:4px">
                    <?= $s['value'] ?></p>
                <p style="font-size:12px;color:<?= $s['sub_color'] ?>;margin-top:4px;font-weight:500"><?= $s['sub'] ?>
                </p>
            </div>
            <div style="background:<?= $s['icon_bg'] ?>;border-radius:10px;padding:10px">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?= $s['icon_color'] ?>"
                    stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                </svg>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- PAYROLL CHART + HOLIDAYS -->
<div class="grid-3" style="margin-bottom:16px">
    <div class="section-card" style="grid-column:span 2">
        <div
            style="padding:16px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between">
            <div>
                <h2 style="font-size:15px;font-weight:700">Payroll Cost Trend</h2>
                <p style="font-size:12px;color:#6B7280;margin-top:2px">Nov 2025 — Apr 2026</p>
            </div>
            <div style="display:flex;gap:20px">
                <div><span style="font-size:11px;color:#6B7280;font-weight:600">PF TOTAL</span>
                    <div style="font-size:16px;font-weight:700;color:#7C3AED"><?= fmt_inr($total_pf) ?></div>
                </div>
                <div><span style="font-size:11px;color:#6B7280;font-weight:600">ESI TOTAL</span>
                    <div style="font-size:16px;font-weight:700;color:#2563EB"><?= fmt_inr($total_esi) ?></div>
                </div>
            </div>
        </div>
        <div style="padding:16px 20px;height:240px"><canvas id="payrollChart"></canvas></div>
    </div>

    <div class="section-card">
        <div
            style="padding:14px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between">
            <h2 style="font-size:14px;font-weight:700">Upcoming Holidays</h2>
            <span class="badge" style="background:#EDE9FE;color:#7C3AED"><?= count($holidays) ?> ahead</span>
        </div>
        <div style="padding:12px 16px">
            <?php foreach (array_slice($holidays, 0, 3) as $h):
        $dt = new DateTime($h['date']); ?>
            <div
                style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:#EDE9FE;border-radius:10px;margin-bottom:8px">
                <div
                    style="background:#7C3AED;color:#fff;border-radius:8px;padding:6px 10px;text-align:center;min-width:52px">
                    <div style="font-size:18px;font-weight:700;line-height:1"><?= $dt->format('j') ?></div>
                    <div style="font-size:10px;opacity:.8"><?= $dt->format('M') ?></div>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#4C1D95"><?= htmlspecialchars($h['name']) ?></div>
                    <div style="font-size:11px;color:#7C3AED;margin-top:2px"><?= $dt->format('l') ?> · <?= $h['type'] ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <a href="leave" class="btn"
                style="width:100%;justify-content:center;margin-top:4px;border-style:dashed;color:#7C3AED">View All
                Holidays →</a>
        </div>
    </div>
</div>

<!-- PAYROLL TABLE + DEPT -->
<div class="grid-3" style="margin-bottom:16px">
    <div class="section-card" style="grid-column:span 2">
        <div
            style="padding:16px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between">
            <h2 style="font-size:15px;font-weight:700">Recent Payroll — April 2026</h2>
            <a href="payroll" class="btn btn-primary" style="font-size:12px">View All</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>EMPLOYEE</th>
                    <th>DEPARTMENT</th>
                    <th style="text-align:right">GROSS</th>
                    <th style="text-align:right">DEDUCTIONS</th>
                    <th style="text-align:right">NET</th>
                    <th style="text-align:center">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payroll as $r):
        $ded = $r['pf'] + $r['esi'] + $r['pt'];
        $ini = initials($r['name']);
        $processed = $r['status'] === 'processed';
      ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar" style="background:#EDE9FE;color:#7C3AED;font-size:11px"><?= $ini ?>
                            </div>
                            <span style="font-weight:500"><?= htmlspecialchars($r['name']) ?></span>
                        </div>
                    </td>
                    <td style="color:#6B7280"><?= $r['dept'] ?></td>
                    <td style="text-align:right;font-weight:500"><?= fmt_inr($r['gross']) ?></td>
                    <td style="text-align:right;color:#DC2626">-<?= fmt_inr($ded) ?></td>
                    <td style="text-align:right;font-weight:600;color:#059669"><?= fmt_inr($r['net']) ?></td>
                    <td style="text-align:center">
                        <span class="badge"
                            style="background:<?= $processed ? '#D1FAE5' : '#FEF3C7' ?>;color:<?= $processed ? '#065F46' : '#92400E' ?>">
                            <?= $processed ? '✓ Processed' : '⏳ Pending' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        <!-- Dept Headcount -->
        <div class="section-card">
            <div style="padding:14px 16px;border-bottom:1px solid #F3F4F6">
                <h2 style="font-size:14px;font-weight:700">Dept. Headcount</h2>
            </div>
            <div style="padding:12px 16px">
                <?php $total_emp = array_sum(array_column($departments,'count'));
        foreach ($departments as $d): $pct = round($d['count']/$total_emp*100); ?>
                <div style="margin-bottom:10px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                        <span style="font-size:12px;font-weight:500;color:#374151"><?= $d['name'] ?></span>
                        <span style="font-size:12px;font-weight:600"><?= $d['count'] ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $d['color'] ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Pending Approvals -->
        <div class="section-card">
            <div
                style="padding:14px 16px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between">
                <h2 style="font-size:14px;font-weight:700">Pending Approvals</h2>
                <span class="badge" style="background:#FEE2E2;color:#B91C1C"><?= count($approvals) ?></span>
            </div>
            <div style="padding:12px 16px;display:flex;flex-direction:column;gap:8px">
                <?php foreach ($approvals as $a): ?>
                <div
                    style="display:flex;align-items:center;gap:10px;padding:10px;background:#FFF7ED;border-radius:8px;border-left:3px solid #EA580C">
                    <span style="font-size:18px"><?= $a['icon'] ?></span>
                    <div style="flex:1">
                        <div style="font-size:12px;font-weight:600"><?= $a['type'] ?></div>
                        <div style="font-size:11px;color:#6B7280"><?= htmlspecialchars($a['employee']) ?> ·
                            <?= $a['detail'] ?></div>
                    </div>
                    <a href="approvals"
                        style="font-size:11px;background:#D1FAE5;color:#065F46;border:none;border-radius:5px;padding:3px 8px;cursor:pointer;font-weight:600;text-decoration:none">Review</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = <<<JS
<script>
new Chart(document.getElementById('payrollChart'),{
  type:'line',
  data:{
    labels:['Nov','Dec','Jan','Feb','Mar','Apr'],
    datasets:[{
      label:'Payroll Cost (₹L)',
      data:[9.2,9.5,9.8,9.1,9.0,8.4],
      borderColor:'#7C3AED',backgroundColor:'rgba(124,58,237,0.1)',
      fill:true,tension:0.4,pointBackgroundColor:'#7C3AED',pointRadius:4,borderWidth:2
    },{
      label:'PF',
      data:[1.1,1.1,1.2,1.1,1.1,1.0],
      borderColor:'#2563EB',backgroundColor:'transparent',
      fill:false,tension:0.4,pointRadius:3,borderWidth:1.5,borderDash:[4,2]
    }]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>'₹'+ctx.raw+'L'}}},
    scales:{
      y:{min:0,max:12,ticks:{callback:v=>'₹'+v+'L',font:{size:11},color:'#9CA3AF'},grid:{color:'rgba(0,0,0,0.04)'},border:{display:false}},
      x:{ticks:{font:{size:11},color:'#9CA3AF'},grid:{display:false},border:{display:false}}
    }
  }
});
</script>
JS;

$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>