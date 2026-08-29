<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: ../login');
    exit();
}               

require_once '../includes/config.php';
require_once '../includes/db_client.php';

$emp_code = $_SESSION['employee_code'] ?? '';

$earnings = [];
$deductions = [];
$total_earnings = 0;
$total_deductions = 0;
$net_pay = 0;
$employee_name = 'Employee';
$is_approved = false;

// ==========================================
// Professional Tax (PT) Helper Function
// ==========================================
function calculatePT($state, $gross_salary, $month = '') {
    $pt = 0;
    $state = strtolower(trim($state ?? ''));
    
    switch ($state) {
        case 'maharashtra':
            if ($gross_salary > 10000) { 
                $pt = (stripos($month, 'Feb') !== false) ? 300 : 200;
            }
            break;
        case 'karnataka':
            if ($gross_salary >= 25000) { $pt = 200; }
            break;
        case 'West Bengal':
            if ($gross_salary >= 40001) $pt = 200;
            elseif ($gross_salary >= 25001) $pt = 150;
            elseif ($gross_salary >= 15001) $pt = 130;
            elseif ($gross_salary >= 10001) $pt = 110;
            break;
        case 'telangana':
        case 'andhra pradesh':
            if ($gross_salary >= 20001) $pt = 200;
            elseif ($gross_salary >= 15001) $pt = 150;
            break;
        default:
            $pt = 0;
            break;
    }
    return $pt;
}

// ==========================================
// 1. Month Restriction Logic (Last 3 Months)
// ==========================================
$current_month_start = date('Y-m-01');
$current_month_ts = strtotime($current_month_start);

$allowed_months = [
    date('M-Y', $current_month_ts),
    date('M-Y', strtotime('-1 month', $current_month_ts)),
    date('M-Y', strtotime('-2 months', $current_month_ts))
];

$display_month = isset($_GET['month']) ? trim($_GET['month']) : $allowed_months[0];
if (!in_array($display_month, $allowed_months)) {
    $display_month = $allowed_months[0];
}

$current_index = array_search($display_month, $allowed_months);
$prev_month = ($current_index < 2) ? $allowed_months[$current_index + 1] : null; 
$next_month = ($current_index > 0) ? $allowed_months[$current_index - 1] : null; 

// ==========================================
// 2. Fetch Employee & Calculate Payslip Data
// ==========================================
if (isset($conn) && !empty($emp_code)) {
    $safe_emp_code = mysqli_real_escape_string($conn, $emp_code);
    $safe_display_month = mysqli_real_escape_string($conn, $display_month);

    // Fetch Employee Details
    $emp_query = "SELECT employee_name, ctc_monthly, ctc_template_id 
                  FROM `employees` 
                  WHERE employee_code = '$safe_emp_code' LIMIT 1";
    $emp_result = mysqli_query($conn, $emp_query);
    
    if ($emp_result && mysqli_num_rows($emp_result) > 0) {
        $emp_data = mysqli_fetch_assoc($emp_result);
        $employee_name = $emp_data['employee_name'];
        
        // NOTE: As per your requirement, ctc_monthly is acting as the Base/Basic Salary for calculations
        $base_salary = floatval($emp_data['ctc_monthly']); 
        $ctc_template_id = intval($emp_data['ctc_template_id']);
        
        $basic_pay_for_pf = 0; 
    
        // Check if payslip is generated and approved
        $approval_query = "SELECT id FROM `payslip_approvals` 
                           WHERE employee_code = '$safe_emp_code' 
                           AND pay_month = '$safe_display_month' 
                           AND LOWER(status) = 'approved' LIMIT 1";
        $approval_result = mysqli_query($conn, $approval_query);
        
        if ($approval_result && mysqli_num_rows($approval_result) > 0) {
            $is_approved = true;
        }

        if ($ctc_template_id > 0) {
            
            // A. Fetch Template Settings for PF, ESI, and PT
            $template_settings_query = "SELECT pt_state, pf_applicable, esi_applicable 
                                        FROM `ctc_templates` WHERE id = $ctc_template_id LIMIT 1";
            $template_settings_result = mysqli_query($conn, $template_settings_query);
            $template_settings = mysqli_fetch_assoc($template_settings_result);
            
            $pf_applicable = (isset($template_settings['pf_applicable']) && $template_settings['pf_applicable'] == 1);
            $esi_applicable = (isset($template_settings['esi_applicable']) && $template_settings['esi_applicable'] == 1);
            $pt_state = $template_settings['pt_state'] ?? '';

            // B. Fetch Salary Components
            $comp_query = "SELECT component_type, component_name, calc_type, calc_value, unit 
                           FROM `ctc_template_components` 
                           WHERE template_id = $ctc_template_id 
                           ORDER BY sort_order ASC";
            $comp_result = mysqli_query($conn, $comp_query);

            if ($comp_result && mysqli_num_rows($comp_result) > 0) {
                while ($comp = mysqli_fetch_assoc($comp_result)) {
                    $comp_type = strtolower(trim($comp['component_type'] ?? ''));
                    $calc_type = strtolower(trim($comp['calc_type'] ?? ''));
                    $unit      = strtolower(trim($comp['unit'] ?? ''));
                    $calc_value = floatval($comp['calc_value']);
                    
                    $amount = 0;

                    // STRONG PERCENTAGE DETECTION:
                    // Checks if calc_type or unit is "percent", "%", "p", "per", or "1"
                    if (
                        strpos($calc_type, 'per') !== false || 
                        $calc_type === '%' || 
                        $calc_type === 'p' || 
                        $calc_type === '1' || 
                        strpos($unit, 'per') !== false || 
                        $unit === '%'
                    ) {
                        // Calculate percentage of the base salary (ctc_monthly)
                        $amount = round($base_salary * ($calc_value / 100), 2);
                    } else {
                        // Fixed Amount
                        $amount = round($calc_value, 2);
                    }

                    if ($amount > 0) {
                        // Classify as Earning
                        if (strpos($comp_type, 'earning') !== false) {
                            $earnings[] = ['name' => $comp['component_name'], 'amount' => $amount];
                            $total_earnings += $amount;
                            
                            // Track Basic Pay for PF calculation (if it's named 'Basic')
                            if (strpos(strtolower($comp['component_name']), 'basic') !== false) {
                                $basic_pay_for_pf += $amount;
                            }
                        } 
                        // Classify as Deduction
                        elseif (strpos($comp_type, 'deduction') !== false) {
                            $deductions[] = ['name' => $comp['component_name'], 'amount' => $amount];
                            $total_deductions += $amount;
                        }
                    }
                }
            }

            // C. Statutory Deductions
            
            // 1. Provident Fund (PF) - 12% of Basic Pay 
            // (If Basic is not defined in template, fallback to 12% of base_salary)
            if ($pf_applicable) {
                $pf_base = ($basic_pay_for_pf > 0) ? $basic_pay_for_pf : $base_salary;
                if ($pf_base > 0) {
                    $pf_amount = round($pf_base * 0.12, 2);
                    $deductions[] = ['name' => 'Provident Fund (PF)', 'amount' => $pf_amount];
                    $total_deductions += $pf_amount;
                }
            }

            // 2. Employee State Insurance (ESI) - 0.75% of Gross Pay (Applicable if Gross <= 21,000)
            if ($esi_applicable && $total_earnings <= 21000 && $total_earnings > 0) {
                $esi_amount = round($total_earnings * 0.0075, 2);
                $deductions[] = ['name' => 'Employee State Insurance (ESI)', 'amount' => $esi_amount];
                $total_deductions += $esi_amount;
            }

            // 3. Professional Tax (PT)
            if (!empty($pt_state) && $total_earnings > 0) {
                $pt_amount = calculatePT($pt_state, $total_earnings, $display_month);
                if ($pt_amount > 0) {
                    $deductions[] = ['name' => 'Professional Tax (PT)', 'amount' => $pt_amount];
                    $total_deductions += $pt_amount;
                }
            }
        }
    }
}

$net_pay = $total_earnings - $total_deductions;

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Payslip</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen font-sans">

    <div class="w-full max-w-md bg-white min-h-screen relative flex flex-col shadow-2xl overflow-hidden">
        
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[65px]">
            <a href="javascript:history.back()" class="bg-[#e4e6eb] text-gray-900 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition no-underline">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center pr-[70px]">
                <h1 class="font-medium text-[16px] tracking-wide">Payslip</h1>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto no-scrollbar pb-8">
            
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-50">
                <?php if ($prev_month): ?>
                    <a href="?month=<?= urlencode($prev_month) ?>" class="text-gray-400 hover:text-gray-800 transition">
                        <i class="fa-solid fa-caret-left text-xl"></i>
                    </a>
                <?php else: ?>
                    <span class="text-gray-200 cursor-not-allowed"><i class="fa-solid fa-caret-left text-xl"></i></span>
                <?php endif; ?>

                <span class="text-[16px] font-bold text-gray-900 tracking-wide flex items-center gap-2">
                    <?= htmlspecialchars($display_month) ?>
                    <?php if (!$is_approved && (!empty($earnings) || !empty($deductions))): ?>
                        <span class="bg-yellow-100 text-yellow-800 text-[10px] font-bold px-2 py-0.5 rounded uppercase">Draft</span>
                    <?php endif; ?>
                </span>

                <?php if ($next_month): ?>
                    <a href="?month=<?= urlencode($next_month) ?>" class="text-gray-400 hover:text-gray-800 transition">
                        <i class="fa-solid fa-caret-right text-xl"></i>
                    </a>
                <?php else: ?>
                    <span class="text-gray-200 cursor-not-allowed"><i class="fa-solid fa-caret-right text-xl"></i></span>
                <?php endif; ?>
            </div>

            <?php if (empty($earnings) && empty($deductions)): ?>
                <div class="text-center py-12 px-4">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-400">
                        <i class="fa-solid fa-file-invoice-dollar text-2xl"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No components found for this month.</p>
                </div>
            <?php else: ?>

                <div class="grid grid-cols-2 gap-3 px-4 pt-4">
                    <div class="bg-[#f4f5f9] rounded-xl p-4 shadow-sm border border-gray-50">
                        <div class="text-[14px] text-gray-800 font-medium">Earnings</div>
                        <div class="text-[20px] font-bold text-gray-900 mt-1"><?= formatCurrency($total_earnings) ?></div>
                    </div>
                    <div class="bg-[#f4f5f9] rounded-xl p-4 shadow-sm border border-gray-50">
                        <div class="text-[14px] text-gray-800 font-medium">Deductions</div>
                        <div class="text-[20px] font-bold text-gray-900 mt-1"><?= formatCurrency($total_deductions) ?></div>
                    </div>
                </div>
 
                <div class="px-4 mt-3">
                    <div class="bg-[#f4f5f9] rounded-xl p-4 flex justify-between items-center shadow-sm border border-gray-50 relative overflow-hidden h-[90px]">
                        <div class="z-10">
                            <div class="text-[14px] text-gray-800 font-medium">Net Pay</div>
                            <div class="text-[24px] font-bold text-gray-900 mt-0.5"><?= formatCurrency($net_pay) ?></div>
                        </div>
                        <div class="absolute right-0 bottom-0 text-[70px] translate-y-3 translate-x-1 opacity-90">
                            💰
                        </div>
                    </div>
                </div>

                <?php if (!empty($earnings)): ?>
                <div class="px-4 mt-8">
                    <h3 class="font-bold text-[16px] text-gray-900 mb-4">Earnings</h3>
                    <div class="space-y-2.5">
                        <?php foreach ($earnings as $earning): ?>
                            <div class="flex justify-between items-center text-[15px]">
                                <span class="text-gray-500 font-medium"><?= htmlspecialchars($earning['name']) ?></span>
                                <span class="text-gray-900 font-semibold"><?= formatCurrency($earning['amount']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($deductions)): ?>
                <div class="px-4 mt-8">
                    <h3 class="font-bold text-[16px] text-gray-900 mb-4">Deductions & Statutory</h3>
                    <div class="space-y-2.5">
                        <?php foreach ($deductions as $deduction): ?>
                            <div class="flex justify-between items-center text-[15px]">
                                <span class="text-gray-500 font-medium"><?= htmlspecialchars($deduction['name']) ?></span>
                                <span class="text-gray-900 font-semibold text-red-500">- <?= formatCurrency($deduction['amount']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="px-4 mt-8 mb-4">
                    <?php if ($is_approved): ?>
                        <a href="generate_payslip_pdf?month=<?= urlencode($display_month) ?>" target="_blank" class="w-full bg-[#fae100] text-gray-900 font-medium text-[16px] py-3.5 rounded-lg transition-colors hover:bg-yellow-400 shadow-sm flex justify-center items-center no-underline">
                            Download Payslip
                        </a>
                    <?php else: ?>
                        <div class="w-full bg-gray-200 text-gray-500 font-medium text-[16px] py-3.5 rounded-lg text-center shadow-sm cursor-not-allowed">
                            Pending Approval
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>