<?php
// Start output buffering IMMEDIATELY to catch any accidental spaces or text
ob_start();
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login'])) {
    header('Location: index.php');
    exit();
}

// Temporarily hide errors from printing to the screen (which breaks PDFs)
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/db_client.php';
require_once '../vendor/autoload.php'; // Verify this path points to your Dompdf vendor folder

use Dompdf\Dompdf;
use Dompdf\Options;

$emp_code = $_SESSION['employee_code'] ?? '';

// Default Variables
$employee_name = 'Employee';
$designation = '';
$doj = '';
$location = '';
$ctc_monthly = 0;
$actual_ctc = 22000;

$earnings = [];
$deductions = [];
$total_earnings = 0;
$total_deductions = 0;

$display_month = isset($_GET['month']) ? trim($_GET['month']) : date('M-Y');

if (isset($conn)) {
    // 1. Fetch Company Details
    $comp_query = "SELECT `client_name`, `address`, `logo` FROM `companies` LIMIT 1";
    $comp_result = mysqli_query($conn, $comp_query);
    if ($comp_result && mysqli_num_rows($comp_result) > 0) {
        $comp_data = mysqli_fetch_assoc($comp_result);
        $company_name = htmlspecialchars($comp_data['client_name']);
        $company_address = nl2br(htmlspecialchars($comp_data['address'])); 
        $company_logo = $comp_data['logo'] ?? ''; 
    }

    // 2. Fetch Employee & CTC Details
    $safe_emp_code = mysqli_real_escape_string($conn, $emp_code);
    $emp_query = "SELECT e.employee_name, e.designation, e.join_date, e.location, e.ctc_monthly, e.basic_pct, 
                         t.id as template_id, t.pf_applicable, t.esi_applicable, t.pt_state 
                  FROM `employees` e
                  LEFT JOIN `ctc_templates` t ON e.ctc_template_id = t.id
                  WHERE e.employee_code = '$safe_emp_code' LIMIT 1";
                  
    $emp_result = mysqli_query($conn, $emp_query);
    
    if ($emp_result && mysqli_num_rows($emp_result) > 0) {
        $emp_data = mysqli_fetch_assoc($emp_result);
        
        $employee_name = $emp_data['employee_name'];
        $designation   = $emp_data['designation'];
        $doj           = date('d/m/Y', strtotime($emp_data['join_date']));
        $location      = $emp_data['location'];
        $ctc_monthly   = floatval($emp_data['ctc_monthly']);
        
        $template_id       = intval($emp_data['template_id']);
        $basic_pct         = floatval($emp_data['basic_pct']);
        $is_pf_applicable  = in_array(strtolower(trim($emp_data['pf_applicable'] ?? '')), ['1', 'yes', 'true', 'y']);
        $is_esi_applicable = in_array(strtolower(trim($emp_data['esi_applicable'] ?? '')), ['1', 'yes', 'true', 'y']);
        $pt_state          = trim($emp_data['pt_state'] ?? '');

        $basic_amount = 0;

        // Fetch CTC Components
        if ($template_id > 0 && $ctc_monthly > 0) {
            $comp_query = "SELECT `component_type`, `component_name`, `calc_type`, `calc_value` 
                           FROM `ctc_template_components` 
                           WHERE `template_id` = '$template_id' ORDER BY `sort_order` ASC";
                           
            $comp_result = mysqli_query($conn, $comp_query);
            if ($comp_result && mysqli_num_rows($comp_result) > 0) {
                while ($row = mysqli_fetch_assoc($comp_result)) {
                    $type = strtolower(trim($row['component_type']));
                    $calc_type = strtolower(trim($row['calc_type']));
                    $calc_value = floatval($row['calc_value']);
                    
                    if (in_array($calc_type, ['percentage', 'pct', '%'])) {
                        $amount = ($calc_value / 100) * $ctc_monthly;
                    } else {
                         $amount = $calc_value; 
                    }

                    if (in_array($type, ['earning', 'earnings'])) {
                        $earnings[] = ['name' => $row['component_name'], 'amount' => $amount];
                        $total_earnings += $amount;
                        if (strtolower($row['component_name']) === 'basic') {
                            $basic_amount = $amount;
                        }
                    } elseif (in_array($type, ['deduction', 'deductions'])) {
                        $c_name = strtolower($row['component_name']);
                        // Fixed for older PHP versions: replaced str_contains with strpos
                        if (strpos($c_name, 'pf') === false && strpos($c_name, 'esi') === false && strpos($c_name, 'pt') === false) {
                            $deductions[] = ['name' => $row['component_name'], 'amount' => $amount];
                            $total_deductions += $amount;
                        }
                    }
                }
            }
        }

        // Auto-Calculate Statutory Deductions
        if ($is_pf_applicable) {
            if ($basic_amount == 0 && $basic_pct > 0) $basic_amount = ($ctc_monthly * $basic_pct) / 100;
            if ($basic_amount > 0) {
                $pf = round($basic_amount * 0.12, 2);
                $deductions[] = ['name' => 'Provident Fund Deduction', 'amount' => $pf];
                $total_deductions += $pf;
            }
        }

        if ($is_esi_applicable && $total_earnings <= 21000 && $total_earnings > 0) {
            $esi = ceil($total_earnings * 0.0075);
            $deductions[] = ['name' => 'ESI Deduction', 'amount' => $esi];
            $total_deductions += $esi;
        }

        if (!empty($pt_state)) {
            $pt_deduction = 130; 
            $deductions[] = ['name' => 'Professional Tax', 'amount' => $pt_deduction];
            $total_deductions += $pt_deduction;
        }
    }
}

$net_pay = $total_earnings - $total_deductions;

function fmt($amount) {
    return number_format($amount, 2, '.', ',');
}

function convertNumberToWordsForIndia($number){
    $no = floor($number);
    $point = round($number - $no, 2) * 100;
    $hundred = null;
    $digits_1 = strlen((string)$no);
    $i = 0;
    $str = array();
    $words = array('0' => '', '1' => 'One', '2' => 'Two',
        '3' => 'Three', '4' => 'Four', '5' => 'Five', '6' => 'Six',
        '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
        '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve',
        '13' => 'Thirteen', '14' => 'Fourteen', '15' => 'Fifteen',
        '16' => 'Sixteen', '17' => 'Seventeen', '18' => 'Eighteen', '19' => 'Nineteen', '20' => 'Twenty',
        '30' => 'Thirty', '40' => 'Forty', '50' => 'Fifty',
        '60' => 'Sixty', '70' => 'Seventy',
        '80' => 'Eighty', '90' => 'Ninety');
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    while ($i < $digits_1) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += ($divider == 10) ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number] . " " . $digits[$counter] . $plural . " " . $hundred
                : $words[floor($number / 10) * 10] . " " . $words[$number % 10] . " " . $digits[$counter] . $plural . " " . $hundred;
        } else $str[] = null;
    }
    $str = array_reverse($str);
    $result = implode('', $str);
    return empty($result) ? "Rupees Zero Only" : "Rupees " . trim($result) . " Only";
}

$net_pay_words = convertNumberToWordsForIndia($net_pay);

$earnings_html = '';
foreach ($earnings as $e) {
    $earnings_html .= '<tr><td>' . htmlspecialchars($e['name']) . '</td><td class="text-right">' . fmt($e['amount']) . '</td></tr>';
}

$deductions_html = '';
foreach ($deductions as $d) {
    $deductions_html .= '<tr><td>' . htmlspecialchars($d['name']) . '</td><td class="text-right">' . fmt($d['amount']) . '</td></tr>';
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #333; margin: 0; padding: 0; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        td { vertical-align: top; }
        .data-table th, .data-table td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        .data-table th { background-color: #f8f9fa; font-weight: bold; }
        .header-logo { font-size: 40px; font-weight: bold; color: #d32f2f; margin-bottom: 5px; }
        .header-title { font-size: 16px; font-weight: bold; margin-bottom: 2px; color: #000; }
        .header-sub { font-size: 11px; color: #555; }
        .section-title { font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #000; }
        .payslip-title { text-align: center; font-size: 14px; font-weight: bold; background: #eee; padding: 6px; border: 1px solid #ccc; margin-bottom: 10px; }
        .col-33 { width: 33.33%; }
        .col-50 { width: 50%; }
        .footer-note { font-size: 9px; color: #777; margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 5px;}
    </style>
</head>
<body>
    <div class="text-center" style="margin-bottom: 15px;">
        <div class="header-logo"><img src="https://www.ramkrishnaivfcentre.com/images/logo.png" alt="Company Logo" style="max-width: 100px; height: auto;" /></div>
    </div>
    <div class="payslip-title">Payslip for ' . htmlspecialchars($display_month) . '</div>
    <table class="data-table" style="margin-bottom: 15px;">
        <tr>
            <td class="col-33">
                <div class="section-title">Company</div>
                <span class="font-bold">' . htmlspecialchars($company_name ?? 'Company Name') . '</span><br>
                ' . ($company_address ?? '') . '
            </td>
            <td class="col-33">
                <div class="section-title">Employee</div>
                <span class="font-bold">' . htmlspecialchars($employee_name) . '</span><br>
                Code: ' . htmlspecialchars($emp_code) . '<br>
                Desg: ' . htmlspecialchars($designation) . '<br>
                DOJ: ' . htmlspecialchars($doj) . '<br>
                Loc: ' . htmlspecialchars($location) . '
            </td>
            <td class="col-33">
                <div class="section-title">Summary</div>
                Net Salary: ' . fmt($net_pay) . '<br>
                CTC/Actual CTC: ' . fmt($ctc_monthly) . '/' . fmt($actual_ctc) . '<br>
                Paid/Total Days: 31.00/31.00
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td class="col-50" style="padding-right: 5px;">
                <div class="section-title">Earnings</div>
                <table class="data-table">
                    <tr><th>Component Name</th><th class="text-right">Amount (₹)</th></tr>
                    ' . $earnings_html . '
                    <tr><td style="border:none; height:10px;"></td><td style="border:none;"></td></tr>
                    <tr><td class="font-bold">Total</td><td class="text-right font-bold">' . fmt($total_earnings) . '</td></tr>
                </table>
            </td>
            <td class="col-50" style="padding-left: 5px;">
                <div class="section-title">Deductions</div>
                <table class="data-table">
                    <tr><th>Component Name</th><th class="text-right">Amount (₹)</th></tr>
                    ' . $deductions_html . '
                    <tr><td style="border:none; height:10px;"></td><td style="border:none;"></td></tr>
                    <tr><td class="font-bold">Total</td><td class="text-right font-bold">' . fmt($total_deductions) . '</td></tr>
                </table>
            </td>
        </tr>
    </table>
    <table>
        <tr>
            <td class="col-50" style="padding-right: 5px;">
                <div class="section-title">Days Summary</div>
                <table class="data-table">
                    <tr><td>Present/Absent</td><td class="text-right">24.00/0.00</td></tr>
                    <tr><td>Holidays / Worked</td><td class="text-right">0.00/0.00</td></tr>
                    <tr><td>Weekoffs / Worked</td><td class="text-right">4.00/0.00</td></tr>
                    <tr><td>Paid Lev / Unpaid Lev</td><td class="text-right">3.00/0.00</td></tr>
                </table>
                <div class="section-title" style="margin-top: 10px;">Leave</div>
                <table class="data-table">
                    <tr><th>Type</th><th class="text-right">AVL</th><th class="text-right">BAL</th></tr>
                    <tr><td>CLSL</td><td class="text-right">3.00</td><td class="text-right">14.00</td></tr>
                    <tr><td>Compoff</td><td class="text-right">0.00</td><td class="text-right">17.00</td></tr>
                </table>
            </td>
            <td class="col-50" style="padding-left: 5px;">
                <div class="section-title">Punctuality</div>
                <table class="data-table">
                    <tr><td>Normal Hrs / OT Hrs</td><td class="text-right">203.98/11.78</td></tr>
                    <tr><td>Late Hours / Days</td><td class="text-right">5.55/20.00</td></tr>
                    <tr><td>Early Hours / Days</td><td class="text-right">0.00/0.00</td></tr>
                    <tr><td>Short Hours/ Days</td><td class="text-right">0.02/1.00</td></tr>
                </table>
                <div style="border: 1px solid #ccc; background-color: #f8f9fa; padding: 10px; margin-top: 10px;">
                    <span class="font-bold">Net Pay In Words:</span><br>
                    ' . $net_pay_words . '
                </div>
            </td>
        </tr>
    </table>
    <div class="footer-note">
        Note: This is a system generated payslip hence signature is not required.
    </div>
</body>
</html>';

// --- CRITICAL FIX ---
// This loop completely destroys any hidden spacing or errors that were printed before this point.
while (ob_get_level()) {
    ob_end_clean();
}

// Generate PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Payslip_" . htmlspecialchars($display_month) . ".pdf", ["Attachment" => true]);
exit();
?>