<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Ensure $conn is defined inside this file using mysqli_connect
require_once '../includes/db_client.php';
require_once '../includes/config.php';

// API: FETCH DYNAMIC PAYSLIP DATA
    if ($_POST['ajax_action'] == 'API/get_payslip_data') {
        $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code'] ?? '');
        $pay_period = mysqli_real_escape_string($conn, $_POST['pay_period'] ?? '');

        // Initialize empty structure for the 3 tabs
        $data = [
            'Earnings' => [],
            'Deductions' => [],
            'Employer Contribution' => []
        ];

        // Fetching from your exact `payslip_approvals` table
        // We use LEFT JOIN to get the text names of the components using component_id
        $sql = "
            SELECT 
                p.id,
                p.employee_code,
                p.employee_name,
                p.salary_type, 
                p.amount, 
                c.code, 
                c.component_name 
            FROM `payslip_approvals` p
            LEFT JOIN `salary_components` c ON p.component_id = c.id
            WHERE p.employee_code = '$emp_code' 
              AND p.pay_month = '$pay_period'
        ";

        $res = @mysqli_query($conn, $sql);
        
        if ($res && mysqli_num_rows($res) > 0) {
            $e_count = 1; $d_count = 1; $em_count = 1; // Serial Number Counters
            
            while($row = mysqli_fetch_assoc($res)) {
                
                // Fix spelling mismatches between Database and UI Tabs
                $type = $row['salary_type'];
                if ($type == 'Earning') $type = 'Earnings';
                if ($type == 'Deduction') $type = 'Deductions';
                if ($type == 'Employer') $type = 'Employer Contribution';

                // Format the data for the JavaScript table
                $item = [
                    'code' => $row['code'] ?? 'N/A',
                    'component' => $row['component_name'] ?? 'Unknown Component',
                    'amount' => number_format((float)$row['amount'], 2, '.', '')
                ];

                // Push data into the correct category
                if ($type == 'Earnings') { 
                    $item['sno'] = $e_count++; 
                    $data['Earnings'][] = $item; 
                } elseif ($type == 'Deductions') { 
                    $item['sno'] = $d_count++; 
                    $data['Deductions'][] = $item; 
                } elseif ($type == 'Employer Contribution') { 
                    $item['sno'] = $em_count++; 
                    $data['Employer Contribution'][] = $item; 
                }
            }
        }
        
        // Return as JSON
        echo json_encode($data);
        exit;
    }