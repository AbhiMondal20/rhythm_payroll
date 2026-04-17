<?php
require_once 'includes/config.php';
$page_title = 'Leave Management';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 class="page-title">Leave Management</h1>
        <p class="page-sub">Requests & Holiday Calendar</p>
    </div>
    <button class="btn btn-primary">+ New Leave Request</button>
</div>

<div class="grid-1" style="margin-bottom:16px">
    <!-- Leave Requests -->
    <div class="section-card">
        <div style="padding:14px 20px;border-bottom:1px solid #F3F4F6">
            <h2 style="font-size:15px;font-weight:700">Leave Requests</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>EMPLOYEE</th>
                    <th>TYPE</th>
                    <th>DATES</th>
                    <th>DAYS</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leave_requests as $lr):
        $pending_lr = $lr['status'] === 'pending'; ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="avatar"
                                style="background:#EDE9FE;color:#7C3AED;font-size:11px;width:30px;height:30px">
                                <?= initials($lr['name']) ?></div>
                            <div>
                                <div style="font-weight:500;font-size:13px"><?= htmlspecialchars($lr['name']) ?></div>
                                <div style="font-size:11px;color:#6B7280"><?= $lr['dept'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:#6B7280"><?= $lr['type'] ?></td>
                    <td style="font-size:12px;color:#6B7280">
                        <?= date('d M', strtotime($lr['from'])) ?>–<?= date('d M', strtotime($lr['to'])) ?></td>
                    <td style="font-weight:600;text-align:center"><?= $lr['days'] ?></td>
                    <td>
                        <span class="badge"
                            style="background:<?= $pending_lr ? '#FEF3C7' : '#D1FAE5' ?>;color:<?= $pending_lr ? '#92400E' : '#065F46' ?>">
                            <?= $pending_lr ? '⏳ Pending' : '✓ Approved' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($pending_lr): ?>
                        <div style="display:flex;gap:4px">
                            <button class="btn"
                                style="padding:3px 8px;font-size:11px;background:#D1FAE5;color:#065F46;border-color:#A7F3D0">Approve</button>
                            <button class="btn"
                                style="padding:3px 8px;font-size:11px;color:#DC2626;border-color:#FEE2E2">Reject</button>
                        </div>
                        <?php else: ?>
                        <span style="font-size:11px;color:#6B7280">—</span>
                        <?php endif; ?>
                    </td>
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