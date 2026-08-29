<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
require_once 'includes/db_client.php';

// ==========================================
// 1. AJAX HANDLERS (For Advance Search Modal)
// ==========================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] == 'search_employees') {
        $keyword = mysqli_real_escape_string($conn, $_POST['keyword'] ?? '');
        $org     = mysqli_real_escape_string($conn, $_POST['org'] ?? '');
        $loc     = mysqli_real_escape_string($conn, $_POST['loc'] ?? '');
        $dept    = mysqli_real_escape_string($conn, $_POST['dept'] ?? '');
        $status  = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
        $group   = mysqli_real_escape_string($conn, $_POST['group'] ?? '');
        $subGroup = mysqli_real_escape_string($conn, $_POST['subGroup'] ?? '');
        
        $sql = "SELECT `employee_code`, `employee_name` FROM `employees` WHERE (`status` = 'Active' OR `status` = '1')";
        
        if (!empty($keyword)) { $sql .= " AND (`employee_name` LIKE '%$keyword%' OR `employee_code` LIKE '%$keyword%')"; }
        if (!empty($loc)) { $sql .= " AND `location` = '$loc'"; }
        if (!empty($dept)) { $sql .= " AND `department` = '$dept'"; }
        if (!empty($status)) { $sql .= " AND `status` = '$status'"; }
        if (!empty($group)) { $sql .= " AND `grade` = '$group'"; }

        $res = @mysqli_query($conn, $sql);
        $emps = [];
        if ($res && mysqli_num_rows($res) > 0) {
            while($row = mysqli_fetch_assoc($res)){
                $emps[] = ['id' => $row['employee_code'], 'name' => $row['employee_name']];
            }
        }
        echo json_encode($emps);
        exit;
    }

    if ($_POST['ajax_action'] == 'save_search') {
        $type = mysqli_real_escape_string($conn, $_POST['type']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $data = mysqli_real_escape_string($conn, $_POST['data']);
        
        if ($type == 'recent') {
            $count_q = @mysqli_query($conn, "SELECT id FROM user_searches WHERE search_type='recent' ORDER BY id DESC LIMIT 4, 1");
            if ($count_q && mysqli_num_rows($count_q) > 0) {
                $fifth_id = mysqli_fetch_assoc($count_q)['id'];
                @mysqli_query($conn, "DELETE FROM user_searches WHERE search_type='recent' AND id < $fifth_id");
            }
        }
        
        $insert_sql = "INSERT INTO user_searches (search_type, search_name, filter_data) VALUES ('$type', '$name', '$data')";
        @mysqli_query($conn, $insert_sql);
        
        $recent_searches = [];
        $saved_searches = [];
        $history_res = @mysqli_query($conn, "SELECT * FROM user_searches ORDER BY id DESC");
        if($history_res) {
            while($h_row = mysqli_fetch_assoc($history_res)){
                $h_row['filter_data'] = json_decode($h_row['filter_data'], true); 
                if($h_row['search_type'] == 'recent') $recent_searches[] = $h_row;
                else $saved_searches[] = $h_row;
            }
        }
        echo json_encode(['status' => 'success', 'recent' => $recent_searches, 'saved' => $saved_searches]);
        exit;
    }

    if ($_POST['ajax_action'] == 'delete_search') {
        $id = (int)$_POST['id'];
        @mysqli_query($conn, "DELETE FROM user_searches WHERE id=$id");
        
        $recent_searches = [];
        $saved_searches = [];
        $history_res = @mysqli_query($conn, "SELECT * FROM user_searches ORDER BY id DESC");
        if($history_res) {
            while($h_row = mysqli_fetch_assoc($history_res)){
                $h_row['filter_data'] = json_decode($h_row['filter_data'], true); 
                if($h_row['search_type'] == 'recent') $recent_searches[] = $h_row;
                else $saved_searches[] = $h_row;
            }
        }
        echo json_encode(['status' => 'success', 'recent' => $recent_searches, 'saved' => $saved_searches]);
        exit;
    }
}

// ==========================================
// 2. FORM SUBMISSION (Process Payslip & Calculations)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payslip']) && !isset($_POST['ajax_action'])) {
    
    if (!empty($_POST['selected_employees'])) {
        $financial_year = mysqli_real_escape_string($conn, $_POST['financial_year']);
        $pay_month      = mysqli_real_escape_string($conn, $_POST['pay_month']);
        $start_date     = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end_date       = mysqli_real_escape_string($conn, $_POST['end_date']);
        $reprocess      = isset($_POST['reprocess']) ? 1 : 0;
        
        $db_start_date  = date('Y-m-d', strtotime($start_date));
        $db_end_date    = date('Y-m-d', strtotime($end_date));
        
        // Total calendar days in the selected period
        $total_period_days = (strtotime($db_end_date) - strtotime($db_start_date)) / 86400 + 1;

        foreach ($_POST['selected_employees'] as $emp_code) {
            $emp_code = mysqli_real_escape_string($conn, $emp_code);
            
            // --- A. Fetch Employee & CTC Template Details ---
            $emp_query = "
                SELECT e.*, t.pt_state, t.pf_applicable, t.esi_applicable 
                FROM employees e
                LEFT JOIN ctc_templates t ON e.ctc_template_id = t.id
                WHERE e.employee_code = '$emp_code'
            ";
            $emp_res = @mysqli_query($conn, $emp_query);
            if (!$emp_res || mysqli_num_rows($emp_res) == 0) continue;
            
            $emp_data = mysqli_fetch_assoc($emp_res);
            $emp_name = mysqli_real_escape_string($conn, $emp_data['employee_name']);
            $ctc_monthly = (float)$emp_data['ctc_monthly'];
            $template_id = (int)$emp_data['ctc_template_id'];

            // --- B. Calculate Attendance & Leaves ---
            $att_query = "
                SELECT COUNT(DISTINCT entry_date) as worked_days 
                FROM time_entries 
                WHERE employee_code = '$emp_code' 
                AND entry_date BETWEEN '$db_start_date' AND '$db_end_date'
                AND (day_status_1 = 'P' OR day_status_1 = 'Present' OR hours_worked > 0)
            ";
            $att_res = @mysqli_query($conn, $att_query);
            $worked_days = $att_res ? (int)mysqli_fetch_assoc($att_res)['worked_days'] : 0;

            $leave_query = "
                SELECT SUM(DATEDIFF(LEAST(to_date, '$db_end_date'), GREATEST(from_date, '$db_start_date')) + 1) as paid_leaves
                FROM leave_requests
                WHERE emp_code = '$emp_code' AND status = 'Approved'
                AND from_date <= '$db_end_date' AND to_date >= '$db_start_date'
            ";
            $leave_res = @mysqli_query($conn, $leave_query);
            $paid_leaves = $leave_res ? (int)mysqli_fetch_assoc($leave_res)['paid_leaves'] : 0;

            $total_payable_days = min($worked_days + $paid_leaves, $total_period_days);
            $proration_ratio = ($total_period_days > 0) ? ($total_payable_days / $total_period_days) : 0;

            // --- C. Calculate CTC Components ---
            $gross_earnings = 0;
            $gross_deductions = 0;
            $basic_salary = 0; 
            
            $comp_query = "SELECT * FROM ctc_template_components WHERE template_id = '$template_id' ORDER BY sort_order";
            $comp_res = @mysqli_query($conn, $comp_query);
            
            $component_breakdown = [];

            if ($comp_res && mysqli_num_rows($comp_res) > 0) {
                while ($comp = mysqli_fetch_assoc($comp_res)) {
                    $amount = 0;
                    
                    // Force lowercase for robust matching against database typos
                    $calc_type = strtolower(trim($comp['calc_type']));
                    $comp_type = strtolower(trim($comp['component_type']));
                    
                    if (strpos($calc_type, 'flat') !== false || strpos($calc_type, 'fixed') !== false) {
                        $amount = ((float)$comp['calc_value']) * $proration_ratio;
                    } elseif (strpos($calc_type, 'percent') !== false || strpos($calc_type, '%') !== false || strpos($calc_type, 'per') !== false) {
                        $amount = ($ctc_monthly * ((float)$comp['calc_value']) / 100) * $proration_ratio;
                    }
                    
                    $amount = round($amount, 2);

                    // Earning vs Deduction classification
                    if (strpos($comp_type, 'earning') !== false) {
                        $gross_earnings += $amount;
                        if (stripos($comp['component_name'], 'basic') !== false) {
                            $basic_salary = $amount;
                        }
                    } else {
                        $gross_deductions += $amount;
                    }
                    
                    $component_breakdown[$comp['component_name']] = $amount;
                }
                
                // Safety Net: If basic wasn't found by name, assume 50% for PF purposes
                if ($basic_salary == 0 && $gross_earnings > 0) {
                    $basic_salary = $gross_earnings * 0.50; 
                }
                
            } else {
                // FALLBACK
                $basic_salary = $ctc_monthly * $proration_ratio;
                $gross_earnings = $basic_salary;
                $component_breakdown['Basic Pay'] = round($basic_salary, 2);
            }

            // --- D. Calculate Statutory Deductions (PF, ESI, PT) ---
            $pf_amount = 0;
            $esi_amount = 0;
            $pt_amount = 0;

            if ($emp_data['pf_applicable'] == '1') {
                $pf_amount = round($basic_salary * 0.12, 2); 
            }

            if ($emp_data['esi_applicable'] == '1') {
                if ($gross_earnings <= 21000) {
                    $esi_amount = round($gross_earnings * 0.0075, 2); 
                }
            }

            if (!empty($emp_data['pt_state'])) {
                if ($gross_earnings > 15000) {
                    $pt_amount = 200;
                }
            }
            
            $total_statutory = $pf_amount + $esi_amount + $pt_amount;

            // --- E. Calculate Loan EMI Deductions ---
            $loan_query = "
                SELECT SUM(emi_amount) as total_emi FROM employee_loans 
                WHERE employee_code = '$emp_code' AND status = 'Active'
                AND repayment_start <= '$db_end_date'
            ";
            $loan_res = @mysqli_query($conn, $loan_query);
            $loan_emi = $loan_res ? round((float)mysqli_fetch_assoc($loan_res)['total_emi'], 2) : 0;

            // --- F. Final Net Pay Calculation ---
            $total_all_deductions = $gross_deductions + $total_statutory + $loan_emi;
            $net_pay = $gross_earnings - $total_all_deductions;
            
            $component_json_str = mysqli_real_escape_string($conn, json_encode($component_breakdown));

            // --- G. Database UPSERT Logic (Check if Exists -> Update, Else -> Insert) ---
            $check_query = "SELECT id FROM `processed_payslips` WHERE `employee_code` = '$emp_code' AND `pay_month` = '$pay_month'";
            $check_res = @mysqli_query($conn, $check_query);

            if ($check_res && mysqli_num_rows($check_res) > 0) {
                // Update Existing Record for this Month
                $update_sql = "
                    UPDATE `processed_payslips` SET 
                        `financial_year` = '$financial_year',
                        `start_date` = '$db_start_date',
                        `end_date` = '$db_end_date',
                        `total_days` = '$total_period_days',
                        `paid_days` = '$total_payable_days',
                        `gross_earnings` = '$gross_earnings',
                        `total_deductions` = '$total_all_deductions',
                        `net_pay` = '$net_pay',
                        `pf_amount` = '$pf_amount',
                        `esi_amount` = '$esi_amount',
                        `pt_amount` = '$pt_amount',
                        `loan_emi` = '$loan_emi',
                        `components_json` = '$component_json_str',
                        `is_reprocessed` = '$reprocess',
                        `status` = 'Processed'
                    WHERE `employee_code` = '$emp_code' AND `pay_month` = '$pay_month'
                ";
                @mysqli_query($conn, $update_sql);
            } else {
                // Insert New Record
                $insert_sql = "
                    INSERT INTO `processed_payslips` 
                    (
                        `employee_code`, `employee_name`, `financial_year`, `pay_month`, `start_date`, `end_date`, 
                        `total_days`, `paid_days`, `gross_earnings`, `total_deductions`, `net_pay`,
                        `pf_amount`, `esi_amount`, `pt_amount`, `loan_emi`, `components_json`, `is_reprocessed`, `status`
                    ) 
                    VALUES 
                    (
                        '$emp_code', '$emp_name', '$financial_year', '$pay_month', '$db_start_date', '$db_end_date', 
                        '$total_period_days', '$total_payable_days', '$gross_earnings', '$total_all_deductions', '$net_pay',
                        '$pf_amount', '$esi_amount', '$pt_amount', '$loan_emi', '$component_json_str', '$reprocess', 'Processed'
                    )
                ";
                @mysqli_query($conn, $insert_sql);
            }
        }
        ?>
        <script>window.location.href = window.location.href.split('?')[0] + "?status=success";</script>
        <?php exit();
    } else {
        ?>
        <script>window.location.href = window.location.href.split('?')[0] + "?status=empty";</script>
        <?php exit();
    }
}

$page_title = 'Payroll - Process Payslip';

// ==========================================
// 3. FETCH DATA FOR UI & DYNAMIC GENERATION
// ==========================================
$current_year = (int)date('Y');
$financial_years = [];
for ($y = $current_year - 2; $y <= $current_year + 2; $y++) {
    $financial_years[] = $y;
}

$employees = [];
$emp_sql = "SELECT `employee_code`, `employee_name` FROM `employees` WHERE `status` = 'Active' OR `status` = 1"; 
$emp_result = @mysqli_query($conn, $emp_sql);
if ($emp_result && mysqli_num_rows($emp_result) > 0) {
    while ($row = mysqli_fetch_assoc($emp_result)) { $employees[] = $row; }
}

$organizations = [];
$org_result = @mysqli_query($conn, "SELECT `id`, `client_name` FROM `companies` WHERE `status` = 'Active' OR `status` = 1");
if ($org_result) { while ($row = mysqli_fetch_assoc($org_result)) { $organizations[] = $row; } }

$locations = [];
$loc_result = @mysqli_query($conn, "SELECT `id`, `location_name` FROM `org_locations` WHERE `status` = 'Active' OR `status` = 1");
if ($loc_result) { while ($row = mysqli_fetch_assoc($loc_result)) { $locations[] = $row; } }

$departments = [];
$dept_result = @mysqli_query($conn, "SELECT `id`, `dept_name` FROM `org_departments` WHERE `status` = 'Active' OR `status` = 1");
if ($dept_result) { while ($row = mysqli_fetch_assoc($dept_result)) { $departments[] = $row; } }

$groups = [];
$group_result = @mysqli_query($conn, "SELECT `id`, `group_name` FROM `org_groups` WHERE `status` = 'Active' OR `status` = 1");
if ($group_result) { while ($row = mysqli_fetch_assoc($group_result)) { $groups[] = $row; } }

$sub_groups = [];
$sub_group_result = @mysqli_query($conn, "SELECT `id`, `sub_group_name` FROM `org_sub_groups` WHERE `status` = 'Active' OR `status` = 1");
if ($sub_group_result) { while ($row = mysqli_fetch_assoc($sub_group_result)) { $sub_groups[] = $row; } }

$recent_searches = [];
$saved_searches = [];
$search_history_res = @mysqli_query($conn, "SELECT * FROM user_searches ORDER BY id DESC");
if($search_history_res) {
    while($row = mysqli_fetch_assoc($search_history_res)){
        $row['filter_data'] = json_decode($row['filter_data'], true); 
        if($row['search_type'] == 'recent') $recent_searches[] = $row;
        else $saved_searches[] = $row;
    }
}

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* Common Styles */
.btn-back { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; color: #6B7280; background: #fff; border: 1px solid #D1D5DB; text-decoration: none; transition: all 0.2s; cursor: pointer; }
.btn-back:hover { background: #F3F4F6; color: #111827; border-color: #9CA3AF; }

.payroll-header-wrapper { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 5px; }
.page-title { font-size: 20px; font-weight: 700; color: #111827; margin: 0; }

.payroll-top-links { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.payroll-top-links a { font-size: 13px; color: #6B7280; text-decoration: none; transition: color 0.15s; }
.payroll-top-links a:hover { color: #2563EB; }
.payroll-top-links .separator { color: #D1D5DB; font-size: 14px; }

.payroll-divider { border: none; border-top: 1px solid #D1D5DB; margin: 25px 0; }

.payroll-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); border: 1px solid #E5E7EB; padding: 24px; min-height: 400px; margin-bottom: 40px; }

.payroll-tab { padding: 5px 2px; font-size: 13.5px; font-weight: 500; color: #6B7280; cursor: pointer; border: none; background: transparent; border-bottom: 2.5px solid transparent; white-space: nowrap; transition: color .15s, border-color .15s; font-family: inherit; text-decoration: none; display: block; margin-bottom: -1px; }
.payroll-tab:hover { color: #111827; border-bottom-color: #111827; }
.payroll-tab.active { color: #2563EB; border-bottom-color: #2563EB; font-weight: 600; }

.card-top-bar { display: flex; align-items: center; margin-bottom: 30px; }
.breadcrumb { font-size: 15px; color: #4B5563; }
.breadcrumb strong { color: #111827; font-weight: 600; }

/* ── Typography & Forms ── */
.section-heading { font-size: 13px; font-weight: 700; color: #111827; margin-bottom: 15px; text-transform: uppercase; }
.form-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 30px; margin-bottom: 30px; align-items: end; }
.form-group label { display: block; font-size: 12px; color: #4B5563; margin-bottom: 8px; font-weight: 500; }

.line-input { width: 100%; padding: 8px 0; border: none; border-bottom: 1px solid #D1D5DB; font-size: 14px; color: #111827; background: transparent; outline: none; transition: border-color 0.2s; }
.line-input:focus { border-bottom-color: #0066FF; }
select.line-input { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 5l3 3 3-3'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right center; padding-right: 20px; }

/* Dates & Toggles */
input[type="date"].line-input::-webkit-calendar-picker-indicator { color: #0066FF; cursor: pointer; opacity: 0.6; }
.date-range-link { font-size: 14px; color: #0066FF; text-decoration: underline; white-space: nowrap; display: inline-block; cursor: pointer; font-weight: 500;}
.date-range-link:hover { color: #0052cc; }

/* ── Search & Filter Row ── */
.search-filter-row { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; max-width: 500px; }
.search-line-wrapper { position: relative; flex: 1; }
.search-line-wrapper > svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: #9CA3AF; fill: none; stroke-width: 2; }
.search-line-wrapper > input { width: 100%; padding: 8px 10px 8px 36px; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 14px; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
.search-line-wrapper > input:focus { border-color: #0066FF; }

/* ── Custom Employee Search Dropdown ── */
.autocomplete-dropdown { position: absolute; top: calc(100% + 4px); left: 0; width: 100%; background: #fff; border: 1px solid #D1D5DB; border-radius: 4px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); z-index: 1000; display: none; flex-direction: column; }
.autocomplete-list { max-height: 250px; overflow-y: auto; margin: 0; padding: 0; list-style: none; }
.autocomplete-item { display: flex; align-items: center; padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #F3F4F6; transition: background-color 0.1s; }
.autocomplete-item:last-child { border-bottom: none; }
.autocomplete-item:hover { background: #F9FAFB; }
.autocomplete-avatar { width: 32px; height: 32px; border-radius: 50%; background: #9CA3AF; color: #fff; display: flex; align-items: center; justify-content: center; margin-right: 15px; flex-shrink: 0; }
.autocomplete-text { font-size: 14px; color: #4B5563; }
.autocomplete-footer { padding: 12px 15px; border-top: 1px solid #E5E7EB; font-size: 13px; color: #0066FF; cursor: pointer; display: flex; align-items: center; gap: 8px; background: #fff; border-radius: 0 0 4px 4px; }
.autocomplete-footer:hover { background: #F9FAFB; text-decoration: underline; }

.btn-filters { display: flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #D1D5DB; color: #4B5563; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; height: 36px; }
.btn-filters:hover { background: #F9FAFB; border-color: #9CA3AF; }

.reprocess-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #111827; font-weight: 500; cursor: pointer; white-space: nowrap; margin-left: 40px; }
.reprocess-checkbox input { width: 16px; height: 16px; accent-color: #0066FF; cursor: pointer; }

/* ── Selected Employees List ── */
.selected-employee-box { border: 1px solid #D1D5DB; border-radius: 4px; padding: 15px; max-width: 900px; min-height: 60px; display: flex; flex-direction: column; gap: 10px; margin-bottom: 40px; }
.employee-chip { display: flex; align-items: center; gap: 10px; }
.employee-chip input[type="checkbox"] { width: 16px; height: 16px; accent-color: #0066FF; cursor: pointer; }
.employee-chip label { font-size: 14px; color: #111827; cursor: pointer; }

/* ── Action Buttons ── */
.form-actions { display: flex; justify-content: flex-end; gap: 12px; max-width: 900px; }
.btn-primary { background: #0066FF; color: #fff; border: none; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #0052cc; }
.btn-outline { background: #fff; color: #0066FF; border: 1px solid #0066FF; padding: 8px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
.btn-outline:hover { background: #F0F5FF; }

/* ── MODAL STYLES (Advance Employee Search) ── */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; box-sizing: border-box; }
.modal-content { background: #fff; width: 100%; max-width: 900px; max-height: 90vh; border-radius: 8px; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #E5E7EB; }
.modal-header h2 { margin: 0; font-size: 16px; font-weight: 600; color: #111827; }
.modal-close { background: none; border: 1px solid #D1D5DB; font-size: 20px; cursor: pointer; color: #6B7280; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.modal-close:hover { background: #F3F4F6; color: #111827; }
.modal-body { padding: 24px; overflow-y: auto; flex: 1; }

.modal-filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
.modal-filter-grid .form-group { margin-bottom: 0; }
.modal-filter-grid select.line-input { border: 1px solid #D1D5DB; border-radius: 4px; padding: 8px 12px; width: 100%; font-size: 13px; background-position: right 10px center; }
.modal-search-row { display: flex; justify-content: space-between; align-items: center; }
.modal-search-row select { border: 1px solid #D1D5DB; border-radius: 4px; padding: 4px 8px; }

.modal-results-layout { display: flex; gap: 30px; margin-top: 20px; }
.modal-emp-list-sec { flex: 3; }
.modal-recent-sec { flex: 1; border-left: 1px solid #E5E7EB; padding-left: 20px; }
.modal-emp-header { margin-bottom: 15px; border-bottom: 1px solid #E5E7EB; padding-bottom: 10px; }
.modal-emp-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; max-height: 200px; overflow-y: auto; }

.recent-tabs { display: flex; border-bottom: 1px solid #E5E7EB; margin-bottom: 15px; }
.recent-tab { padding: 6px 12px; font-size: 12px; color: #6B7280; cursor: pointer; border-bottom: 2px solid transparent; }
.recent-tab.active { color: #0066FF; border-bottom-color: #0066FF; font-weight: 500; }

.recent-list { list-style: none; padding: 0; margin: 0; }
.recent-list li { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #4B5563; padding: 8px 0; border-bottom: 1px dashed #E5E7EB; cursor: pointer; transition: background 0.1s; }
.recent-list li:hover { background: #F9FAFB; }
.recent-list li span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.recent-list li button { background: none; border: 1px solid #D1D5DB; border-radius: 50%; cursor: pointer; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; color: #EF4444; transition: all 0.2s; margin-left: 10px; flex-shrink: 0; }
.recent-list li button:hover { background: #FEE2E2; border-color: #EF4444; }

.modal-footer { padding: 16px 24px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px; background: #F9FAFB; border-radius: 0 0 8px 8px; }
</style>

<div class="payroll-header-wrapper">
    <div class="title-wrapper">
        <a href="javascript:history.back()" class="btn-back" title="Go Back">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
        </a>
    </div>
    <h1 class="page-title" id="mainPageTitle">Payroll</h1>
    <div class="payroll-top-links" id="mainTopLinks">
        <a href="PaymentDeduction">Payment/Deduction</a> <span class="separator">|</span>
        <a href="HoldSalary">Hold Salary</a> <span class="separator">|</span>
        <a href="ApprovePayslip">Approve Payslip</a> <span class="separator">|</span>
        <a href="EditPayslip">Edit Payslip</a> <span class="separator">|</span>
        <a href="Loans" >Loans</a> <span class="separator">|</span>
        <a href="ProcessPayslip" class="payroll-tab active">Process Payslip</a> <span class="separator">|</span>
        <a href="FullFinal">Final Settlement</a> <span class="separator">|</span>
        <a href="SalaryStructure">Salary Structure</a> <span class="separator">|</span>
        <a href="Timesheet">Timesheet</a>
    </div>
</div>

<div class="payroll-card">
    <div class="card-top-bar">
        <div class="breadcrumb"><strong>Payroll</strong> &nbsp;&gt;&nbsp; Process Payslip</div>
    </div>

    <form action="" method="POST" id="processForm">
        
        <div class="form-row" style="align-items: center; border-bottom: 1px dashed #E5E7EB; padding-bottom: 30px; margin-bottom: 30px;">
            <div class="form-group" style="margin:0;">
                <label>Financial Year</label>
                <select name="financial_year" id="financialYearSelect" class="line-input" onchange="updatePayPeriods()">
                    <?php foreach($financial_years as $fy): ?>
                        <option value="<?= $fy ?>" <?= $fy == $current_year ? 'selected' : '' ?>><?= $fy ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin:0;">
                <label>Pay Period</label>
                <select name="pay_month" id="payPeriodSelect" class="line-input" onchange="updateDateRange()">
                </select>
            </div>
            
            <div id="dateToggleArea" style="grid-column: span 2; display: flex; align-items: flex-end; width: 100%;">
                
                <div id="dateLinkView" style="display:flex; align-items:center; width: 100%; height: 100%;">
                    <a href="#" class="date-range-link" id="dateRangeLink" onclick="showCustomDates(event)" style="margin: 0; padding-bottom: 8px;">Loading Dates...</a>
                </div>

                <div id="customDateView" style="display:none; gap: 15px; width: 100%;">
                    <div class="form-group" style="margin:0; flex:1;">
                        <label>Start Date</label>
                        <input type="date" name="start_date" id="startDate" class="line-input" required>
                    </div>
                    <div class="form-group" style="margin:0; flex:1;">
                        <label>End Date</label>
                        <input type="date" name="end_date" id="endDate" class="line-input" required>
                    </div>
                    <div style="display: flex; align-items: flex-end; padding-bottom: 8px;">
                        <button type="button" class="btn-outline" style="padding: 4px 12px; height: 32px; border-color:#D1D5DB; color:#4B5563;" onclick="hideCustomDates()">Cancel</button>
                    </div>
                </div>

            </div>
        </div>

        <div class="section-heading">SELECT EMPLOYEES TO PROCESS</div>
        
        <div style="display: flex; align-items: center; max-width: 900px; margin-bottom: 25px;">
            <div class="search-filter-row" style="margin: 0;">
                <div class="search-line-wrapper">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="mainEmpSearch" placeholder="Search by name or #code" autocomplete="off">
                    
                    <!-- Custom Dropdown Container -->
                    <div id="customEmpDropdown" class="autocomplete-dropdown">
                        <ul id="customEmpList" class="autocomplete-list"></ul>
                        <div class="autocomplete-footer" onclick="openFilterModal()">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            Browse Active & Inactive Employees
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-filters" onclick="openFilterModal()">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    Filters
                </button>
            </div>
            <label class="reprocess-checkbox">
                <input type="checkbox" name="reprocess" value="1">
                Overwrite Manual Changes
            </label>
        </div>

        <div class="selected-employee-box" id="mainSelectedEmployeesBox">
            <span style="color: #9CA3AF; font-size: 13px; align-self: center;" id="emptySelectionText">No employees selected. Use search or filters to add.</span>
        </div>

        <div class="form-actions">
            <button type="button" class="btn-outline" style="border-color:#D1D5DB; color:#111827;" onclick="window.location.reload();">Cancel</button>
            <button type="submit" name="process_payslip" class="btn-primary">Process</button>
        </div>
    </form>
</div>

<div id="filterModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Advance Employee Search</h2>
            <button type="button" class="modal-close" onclick="closeFilterModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="search-line-wrapper" style="margin-bottom: 25px;">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="modalSearchInput" placeholder="Search by name or #code" style="border-radius: 4px; border: 1px solid #D1D5DB; padding-left: 35px;">
            </div>

            <div class="modal-filter-grid">
                <div class="form-group"><label>Organization</label><select id="filterOrg" class="line-input"><option value="">Select Organization</option><?php foreach($organizations as $org): ?><option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['client_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Locations</label><select id="filterLoc" class="line-input"><option value="">Select Location</option><?php foreach($locations as $loc): ?><option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['location_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Department</label><select id="filterDept" class="line-input"><option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['dept_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Designation</label><select id="filterDesig" class="line-input"><option value="">Select Designation</option></select></div>
                <div class="form-group"><label>Status</label><select id="filterStatus" class="line-input"><option value="">Select Status</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                <div class="form-group"><label>Group</label><select id="filterGroup" class="line-input"><option value="">Select Group</option><?php foreach($groups as $grp): ?><option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['group_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Sub Group</label><select id="filterSubGroup" class="line-input"><option value="">Select Sub Group</option><?php foreach($sub_groups as $sgrp): ?><option value="<?= $sgrp['id'] ?>"><?= htmlspecialchars($sgrp['sub_group_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Category</label><select id="filterCat" class="line-input"><option value="">Select Category</option></select></div>
                <div class="form-group"><label>Grade</label><select id="filterGrade" class="line-input"><option value="">Select Grade</option></select></div>
                <div class="form-group"><label>Additional Field</label><select id="filterAddField" class="line-input"><option value="">Select Field</option></select></div>
                <div class="form-group"><label>Field Value</label><select id="filterAddVal" class="line-input"><option value="">Select Value</option></select></div>
            </div>

            <div class="modal-search-row">
                <span style="font-size: 13px; color: #4B5563;">Records per page : <select><option>25</option><option>50</option><option>100</option></select></span>
                <button type="button" class="btn-primary" onclick="performModalSearch()">Search</button>
            </div>

            <hr style="margin: 20px 0; border: none; border-top: 1px solid #E5E7EB;">

            <div class="modal-results-layout">
                <div class="modal-emp-list-sec">
                    <div class="modal-emp-header">
                        <label class="checkbox-label" style="font-weight: 500;"><input type="checkbox" id="selectAllModalEmp" onclick="toggleAllModalEmp(this)"> Employees Found - <span id="empFoundCount">0</span></label>
                    </div>
                    <div class="modal-emp-grid" id="modalEmpGrid"><span style="font-size: 13px; color: #9CA3AF;">Click search to find employees.</span></div>
                </div>

                <div class="modal-recent-sec">
                    <div class="recent-tabs">
                        <span class="recent-tab active" id="tabRecentSearch" onclick="switchSidebarTab('recent')">Recent Search</span>
                        <span class="recent-tab" id="tabSavedSearch" onclick="switchSidebarTab('saved')">Saved Search</span>
                    </div>
                    <ul class="recent-list" id="recentSearchList"></ul>
                    <ul class="recent-list" id="savedSearchList" style="display:none;"></ul>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-outline" onclick="clearModalSelections()">Clear All</button>
            <button type="button" class="btn-outline" onclick="saveCurrentSearch()">Save Search</button>
            <button type="button" class="btn-primary" onclick="applyModalFilters()">Apply</button>
        </div>
    </div>
</div>

<?php
    $page_content = ob_get_clean();
    include 'includes/header.php';
    echo $page_content;
    include 'includes/footer.php';
?>

<script>
// ==========================================
// CUSTOM EMPLOYEE SEARCH DROPDOWN LOGIC
// ==========================================
const allEmployeesList = <?= json_encode($employees) ?>;

document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('mainEmpSearch');
    const dropdown = document.getElementById('customEmpDropdown');
    const list = document.getElementById('customEmpList');

    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const val = this.value.toLowerCase().trim();
            list.innerHTML = '';
            
            if (!val) {
                dropdown.style.display = 'none';
                return;
            }

            const filtered = allEmployeesList.filter(emp => 
                emp.employee_name.toLowerCase().includes(val) || 
                emp.employee_code.toLowerCase().includes(val)
            );

            if (filtered.length > 0) {
                filtered.forEach(emp => {
                    const li = document.createElement('li');
                    li.className = 'autocomplete-item';
                    li.innerHTML = `
                        <div class="autocomplete-avatar">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </div>
                        <div class="autocomplete-text">${emp.employee_name} - #${emp.employee_code}</div>
                    `;
                    
                    li.addEventListener('click', function() {
                        addEmployeeToSelection(emp.employee_code, emp.employee_name);
                        searchInput.value = '';
                        dropdown.style.display = 'none';
                    });
                    
                    list.appendChild(li);
                });
            } else {
                list.innerHTML = '<li style="padding:15px; color:#9CA3AF; font-size:13px; text-align:center;">No employees found</li>';
            }
            
            dropdown.style.display = 'flex';
        });

        searchInput.addEventListener('focus', function() {
            if (this.value.trim() !== '') { dropdown.style.display = 'flex'; }
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    updatePayPeriods();

    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    
    if (status === 'success') {
        Swal.fire({
            title: 'Processed!',
            text: 'Payslips for the selected employees have been successfully calculated and saved.',
            icon: 'success',
            confirmButtonColor: '#0066FF'
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (status === 'empty') {
        Swal.fire({
            title: 'Error!',
            text: 'Please select at least one employee.',
            icon: 'error',
            confirmButtonColor: '#EF4444'
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

// ==========================================
// FORM SUBMIT PROGRESS BAR LOGIC
// ==========================================
document.getElementById('processForm').addEventListener('submit', function(e) {
    if (selectedEmployees.length === 0) {
        e.preventDefault();
        Swal.fire({
            title: 'Error!',
            text: 'Please select at least one employee.',
            icon: 'error',
            confirmButtonColor: '#EF4444'
        });
        return;
    }

    e.preventDefault(); 
    const form = this;

    if (!document.querySelector('input[name="process_payslip"]')) {
        const hiddenBtn = document.createElement('input');
        hiddenBtn.type = 'hidden';
        hiddenBtn.name = 'process_payslip';
        hiddenBtn.value = '1';
        form.appendChild(hiddenBtn);
    }

    let timerInterval;
    Swal.fire({
        title: 'Processing Payslips...',
        html: 'Calculating attendance, components, and deductions... <b>0</b>%',
        timer: 2500,
        timerProgressBar: true,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            const b = Swal.getHtmlContainer().querySelector('b');
            timerInterval = setInterval(() => {
                let left = Swal.getTimerLeft();
                if(left !== undefined) {
                    let percent = Math.min(100, Math.round(((2500 - left) / 2500) * 100));
                    b.textContent = percent;
                }
            }, 50);
        },
        willClose: () => { clearInterval(timerInterval); }
    }).then(() => { form.submit(); });
});

// ==========================================
// DATE UI TOGGLE & CALCULATION LOGIC
// ==========================================
function updatePayPeriods() {
    const year = document.getElementById('financialYearSelect').value;
    const monthSelect = document.getElementById('payPeriodSelect');
    const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    
    let currentVal = monthSelect.value;
    let selectedMonth = currentVal ? currentVal.split('-')[0] : months[new Date().getMonth()];

    monthSelect.innerHTML = '';
    
    months.forEach(month => {
        const val = `${month}-${year}`;
        const selected = (month === selectedMonth) ? 'selected' : '';
        monthSelect.innerHTML += `<option value="${val}" ${selected}>${val}</option>`;
    });

    updateDateRange();
}

function showCustomDates(e) {
    if(e) e.preventDefault();
    document.getElementById('dateLinkView').style.display = 'none';
    document.getElementById('customDateView').style.display = 'flex';
}

function hideCustomDates() {
    document.getElementById('customDateView').style.display = 'none';
    document.getElementById('dateLinkView').style.display = 'flex';
    updateDateRange();
}

function updateDateRange() {
    const periodVal = document.getElementById('payPeriodSelect').value;
    if(!periodVal) return;
    
    const parts = periodVal.split('-');
    const monthStr = parts[0];
    const year = parseInt(parts[1]);
    
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const monthIndex = monthNames.indexOf(monthStr);
    
    if(monthIndex > -1) {
        const endDate = new Date(year, monthIndex + 1, 0); 
        
        const mm = String(monthIndex + 1).padStart(2, '0');
        const startFormatted = `${year}-${mm}-01`;
        const endFormatted = `${year}-${mm}-${String(endDate.getDate()).padStart(2, '0')}`;
        
        document.getElementById('startDate').value = startFormatted;
        document.getElementById('endDate').value = endFormatted;
        
        document.getElementById('dateRangeLink').innerText = `1 ${monthStr} ${year} to ${endDate.getDate()} ${monthStr} ${year}`;
    }
}

// ==========================================
// EMPLOYEE SELECTION LOGIC
// ==========================================
let selectedEmployees = [];

function addEmployeeToSelection(id, name) {
    if (!selectedEmployees.find(e => e.id === id)) {
        selectedEmployees.push({ id, name });
        renderSelectedEmployees();
    }
}

function removeEmployee(id) {
    selectedEmployees = selectedEmployees.filter(e => e.id !== id);
    renderSelectedEmployees();
}

function renderSelectedEmployees() {
    const box = document.getElementById('mainSelectedEmployeesBox');
    box.innerHTML = '';
    
    if (selectedEmployees.length === 0) {
        box.innerHTML = '<span style="color: #9CA3AF; font-size: 13px; align-self: center;" id="emptySelectionText">No employees selected. Use search or filters to add.</span>';
        return;
    }
    
    selectedEmployees.forEach(emp => {
        box.innerHTML += `
            <div class="employee-chip">
                <input type="checkbox" name="selected_employees[]" value="${emp.id}" checked onclick="removeEmployee('${emp.id}')">
                <label>${emp.name} - ${emp.id}</label>
            </div>
        `;
    });
}

// ==========================================
// MODAL SIDEBAR & AJAX LOGIC
// ==========================================
let recentSearches = <?= json_encode($recent_searches ?? []) ?>;
let savedSearches = <?= json_encode($saved_searches ?? []) ?>;

function switchSidebarTab(tab) {
    document.getElementById('tabRecentSearch').classList.remove('active');
    document.getElementById('tabSavedSearch').classList.remove('active');
    document.getElementById('recentSearchList').style.display = 'none';
    document.getElementById('savedSearchList').style.display = 'none';

    if (tab === 'recent') {
        document.getElementById('tabRecentSearch').classList.add('active');
        document.getElementById('recentSearchList').style.display = 'block';
    } else {
        document.getElementById('tabSavedSearch').classList.add('active');
        document.getElementById('savedSearchList').style.display = 'block';
    }
}

function renderSidebarLists() {
    const rList = document.getElementById('recentSearchList');
    const sList = document.getElementById('savedSearchList');
    
    rList.innerHTML = recentSearches.length === 0 ? '<li style="justify-content:center; color:#9CA3AF;">No recent searches</li>' : '';
    recentSearches.forEach(search => {
        let sd = JSON.stringify(search.filter_data).replace(/'/g, "&apos;");
        rList.innerHTML += `<li onclick='applySearchState(${sd})'><span>${search.search_name || "Filtered Search"}</span><button type="button" onclick="deleteSearchItem(${search.id}, event)" title="Remove">&minus;</button></li>`;
    });

    sList.innerHTML = savedSearches.length === 0 ? '<li style="justify-content:center; color:#9CA3AF;">No saved searches</li>' : '';
    savedSearches.forEach(search => {
        let sd = JSON.stringify(search.filter_data).replace(/'/g, "&apos;");
        sList.innerHTML += `<li onclick='applySearchState(${sd})'><span>${search.search_name}</span><button type="button" onclick="deleteSearchItem(${search.id}, event)" title="Remove">&minus;</button></li>`;
    });
}

function applySearchState(data) {
    document.getElementById('modalSearchInput').value = data.keyword || '';
    document.getElementById('filterOrg').value = data.org || '';
    document.getElementById('filterLoc').value = data.loc || '';
    document.getElementById('filterDept').value = data.dept || '';
    document.getElementById('filterStatus').value = data.status || '';
    document.getElementById('filterGroup').value = data.group || '';
    document.getElementById('filterSubGroup').value = data.subGroup || '';
    performModalSearch();
}

function captureSearchState() {
    return {
        keyword: document.getElementById('modalSearchInput').value.trim(),
        org: document.getElementById('filterOrg').value,
        loc: document.getElementById('filterLoc').value,
        dept: document.getElementById('filterDept').value,
        status: document.getElementById('filterStatus').value,
        group: document.getElementById('filterGroup').value,
        subGroup: document.getElementById('filterSubGroup').value
    };
}

async function performModalSearch() {
    const searchData = captureSearchState();
    
    const searchForm = new FormData();
    searchForm.append('ajax_action', 'search_employees');
    for (let key in searchData) { searchForm.append(key, searchData[key]); }

    const response = await fetch(window.location.href, { method: 'POST', body: searchForm });
    const employees = await response.json();

    const grid = document.getElementById('modalEmpGrid');
    document.getElementById('empFoundCount').innerText = employees.length;
    document.getElementById('selectAllModalEmp').checked = false;
    grid.innerHTML = '';
    
    if (employees.length === 0) {
        grid.innerHTML = '<span style="font-size: 13px; color: #9CA3AF;">No matching employees found.</span>';
    } else {
        employees.forEach(emp => {
            grid.innerHTML += `<label class="checkbox-label"><input type="checkbox" class="modal-emp-checkbox" value="${emp.id}" data-name="${emp.name}"> ${emp.name} - ${emp.id}</label>`;
        });
    }

    let label = searchData.keyword ? searchData.keyword : "Filtered Search";
    const historyForm = new FormData();
    historyForm.append('ajax_action', 'save_search');
    historyForm.append('type', 'recent');
    historyForm.append('name', label);
    historyForm.append('data', JSON.stringify(searchData));

    fetch(window.location.href, { method: 'POST', body: historyForm })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                recentSearches = res.recent;
                savedSearches = res.saved;
                renderSidebarLists();
            }
        });
}

function saveCurrentSearch() {
    Swal.fire({
        title: 'Save Search',
        input: 'text',
        inputLabel: 'Enter a name to save this search filter:',
        inputValue: 'My Saved Search',
        showCancelButton: true,
        confirmButtonColor: '#0066FF',
        inputValidator: (value) => {
            if (!value) { return 'You need to write something!' }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const name = result.value;
            const searchData = captureSearchState();
            const sfData = new FormData();
            sfData.append('ajax_action', 'save_search');
            sfData.append('type', 'saved');
            sfData.append('name', name);
            sfData.append('data', JSON.stringify(searchData));
            
            fetch(window.location.href, { method: 'POST', body: sfData })
                .then(res => res.json())
                .then(res => {
                    if(res.status === 'success'){
                        Swal.fire({
                            title: 'Saved!',
                            text: 'Your search has been saved.',
                            icon: 'success',
                            confirmButtonColor: '#0066FF'
                        }).then(() => {
                            recentSearches = res.recent;
                            savedSearches = res.saved;
                            renderSidebarLists();
                            switchSidebarTab('saved');
                        });
                    }
                });
        }
    });
}

function deleteSearchItem(id, event) {
    event.stopPropagation();
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('ajax_action', 'delete_search');
            formData.append('id', id);
            
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    if(res.status === 'success') {
                        Swal.fire({
                            title: 'Deleted!',
                            text: 'Your saved search has been deleted.',
                            icon: 'success',
                            confirmButtonColor: '#0066FF'
                        }).then(() => {
                            recentSearches = res.recent;
                            savedSearches = res.saved;
                            renderSidebarLists();
                        });
                    }
                });
        }
    });
}

function toggleAllModalEmp(source) {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox');
    checkboxes.forEach(cb => { cb.checked = source.checked; });
}

function clearModalSelections() {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox');
    checkboxes.forEach(cb => { cb.checked = false; });
    document.getElementById('selectAllModalEmp').checked = false;
}

function applyModalFilters() {
    const checkboxes = document.querySelectorAll('.modal-emp-checkbox:checked');
    checkboxes.forEach(cb => { addEmployeeToSelection(cb.value, cb.getAttribute('data-name')); });
    closeFilterModal();
}

function openFilterModal() {
    document.getElementById('filterModal').style.display = 'flex';
    renderSidebarLists();
}
function closeFilterModal() {
    document.getElementById('filterModal').style.display = 'none';
}
</script>
<script src="includes/assets/scripts.js"></script>