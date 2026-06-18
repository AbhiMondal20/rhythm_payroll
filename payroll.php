<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
$page_title = 'Payroll';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
    /* Back button */
    .btn-back {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    color: #6B7280;
    background: #fff;
    border: 1px solid #D1D5DB;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
}
.btn-back:hover {
    background: #F3F4F6;
    color: #111827;
    border-color: #9CA3AF;
}
/* ── Page header & Top Links ── */
.payroll-header-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin: 0;
    
}

.payroll-top-links {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.payroll-top-links a {
    font-size: 13px;
    color: #6B7280;
    text-decoration: none;
    transition: color 0.15s;
}

.payroll-top-links a:hover {
    color: #2563EB;
}

.payroll-top-links .separator {
    color: #D1D5DB;
    font-size: 14px;
}

/* ── Page wrapper card ── */
.payroll-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    padding: 20px 0;
}

/* ── Grid ── */
.cfg-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    padding: 10px 20px;
}

/* ── Config item ── */
.cfg-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 15px 10px;
    cursor: pointer;
    text-decoration: none;
    border-radius: 8px;
    transition: background 0.15s, transform 0.1s;
}

.cfg-item:hover {
    background: #F9FAFB;
}

.cfg-item:hover .cfg-item-title {
    color: #2563EB;
}

/* ── Config icon ── */
.cfg-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #F3F6FF;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid #E5E7EB;
    transition: background 0.15s, border-color 0.15s;
}

.cfg-item:hover .cfg-icon {
    background: #EBF0FF;
    border-color: #BFDBFE;
}

.cfg-icon svg {
    width: 22px;
    height: 22px;
    stroke: #4B5563;
    fill: none;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: stroke 0.15s;
}

.cfg-item:hover .cfg-icon svg { stroke: #2563EB; }

.cfg-item-title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 6px;
    line-height: 1.3;
    transition: color 0.15s;
}

.cfg-item-desc {
    font-size: 12px;
    color: #6B7280;
    line-height: 1.5;
}

/* ── Divider Line Style ── */
.payroll-divider {
    border: none;
    border-top: 1px solid #D1D5DB; /* Change color here if needed */
    margin: 25px 0; /* Adjust spacing around the line here */
}

/* ── Responsive ── */
@media (max-width: 1100px) {
    .cfg-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .cfg-grid { grid-template-columns: repeat(2, 1fr); }
    .payroll-header-wrapper { flex-direction: column; align-items: flex-start; }
}

@media (max-width: 480px) {
    .cfg-grid { grid-template-columns: 1fr; }
}
</style>

<div class="payroll-header-wrapper">
    <h1 class="page-title">Payroll</h1>
    <div class="payroll-top-links">
        <a href="PaymentDeduction">Payment/Deduction</a> <span class="separator">|</span>
        <a href="HoldSalary">Hold Salary</a> <span class="separator">|</span>
        <a href="ApprovePayslip">Approve Payslip</a> <span class="separator">|</span>
        <a href="EditPayslip">Edit Payslip</a> <span class="separator">|</span>
        <a href="Loans">Loans</a> <span class="separator">|</span>
        <a href="ProcessPayslip">Process Payslip</a> <span class="separator">|</span>
        <a href="FullFinal">Final Settlement</a> <span class="separator">|</span>
        <a href="SalaryStructure">Salary Structure</a> <span class="separator">|</span>
        <a href="Timesheet">Timesheet</a>
    </div>
    <!-- <hr class="payroll-divider"> -->
</div>

<div class="payroll-card">
    <div class="cfg-grid">
        <?php foreach ($payroll_cards as $card): ?>
        <a href="<?= htmlspecialchars($card['href']) ?>" class="cfg-item">
            <div class="cfg-icon">
                <?= get_payroll_icon($card['icon']) ?>
            </div>
            <div>
                <div class="cfg-item-title"><?= htmlspecialchars($card['title']) ?></div>
                <div class="cfg-item-desc"><?= htmlspecialchars($card['desc']) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>



<?php


$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>