

<?php
require_once 'includes/config.php';
$page_title = 'Leave Management';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:10px">
  <div><h1 class="page-title">Holiday Management</h1><p class="page-sub">Holiday Calendar</p></div>
  <button class="btn btn-primary">+ New Holiday Request</button>
</div>

<div class="grid-1" style="margin-bottom:16px">
  <!-- Holiday List -->
  <div class="section-card">
    <div style="padding:14px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between">
      <h2 style="font-size:15px;font-weight:700">Holiday Calendar 2026</h2>
      <span class="badge" style="background:#EDE9FE;color:#7C3AED"><?= count($holidays) ?> holidays</span>
    </div>
    <div style="padding:12px 16px">
    <?php foreach ($holidays as $h):
      $dt = new DateTime($h['date']);
      $isPast = $dt < new DateTime();
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:<?= $isPast ? '#F9FAFB' : '#EDE9FE' ?>;border-radius:10px;margin-bottom:8px;opacity:<?= $isPast ? '.5' : '1' ?>">
      <div style="background:<?= $isPast ? '#9CA3AF' : '#7C3AED' ?>;color:#fff;border-radius:8px;padding:6px 10px;text-align:center;min-width:52px">
        <div style="font-size:16px;font-weight:700;line-height:1"><?= $dt->format('j') ?></div>
        <div style="font-size:10px;opacity:.8"><?= $dt->format('M') ?></div>
      </div>
      <div>
        <div style="font-size:13px;font-weight:600;color:#4C1D95"><?= htmlspecialchars($h['name']) ?></div>
        <div style="font-size:11px;color:#7C3AED;margin-top:2px"><?= $dt->format('l') ?> · <?= $h['type'] ?></div>
      </div>
      <?php if ($isPast): ?><span class="badge" style="margin-left:auto;background:#F3F4F6;color:#9CA3AF">Past</span><?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
  </div>

  
</div>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>
