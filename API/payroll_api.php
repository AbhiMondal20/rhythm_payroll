<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

// Enable mysqlixceptions for proper error catching
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

const MONTH_NAMES = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function periodLabel($year, $month) {
    return MONTH_NAMES[(int)$month] . '-' . $year;
}
function empName() {
    return "COALESCE(e.employee_name, CONCAT(e.first_name,' ',e.last_name))";
}

try {
    switch ($action) {

        /* ════════════════════════════════════════════════
           SHARED: EMPLOYEE SEARCH
        ════════════════════════════════════════════════ */
        case 'search_employees': {
            $q = '%' . trim($_GET['q'] ?? '') . '%';
            $stmt = $conn->prepare("
                SELECT id, employee_code, " . empName() . " AS name, designation
                FROM employees e
                WHERE (employee_name LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR employee_code LIKE ?)
                ORDER BY employee_code LIMIT 20
            ");
            $stmt->bind_param("ssss", $q, $q, $q, $q);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode(['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);
            break;
        }

        /* ════════════════════════════════════════════════
           PAYMENT / DEDUCTION
        ════════════════════════════════════════════════ */
        case 'pd_list': {
            $empId = (int)($_GET['employee_id'] ?? 0);
            $year  = (int)($_GET['year'] ?? 0);
            $month = (int)($_GET['month'] ?? 0);
            if (!$empId || !$year || !$month) { echo json_encode(['success'=>true,'data'=>[]]); break; }
            $stmt = $conn->prepare("
                SELECT id, entry_type, amount, remarks, created_at
                FROM payroll_advance_entries
                WHERE employee_id=? AND pay_year=? AND pay_month=? AND is_deleted=0
                ORDER BY created_at DESC
            ");
            $stmt->bind_param("iii", $empId, $year, $month);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode(['success'=>true,'data'=>$result->fetch_all(MYSQLI_ASSOC)]);
            break;
        }

        case 'pd_add': {
            $empId = (int)($_POST['employee_id'] ?? 0);
            $type  = trim($_POST['entry_type'] ?? 'Advance Payment');
            $amt   = (float)($_POST['amount'] ?? 0);
            $year  = (int)($_POST['pay_year'] ?? 0);
            $month = (int)($_POST['pay_month'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            if (!$empId || !$year || !$month || $amt <= 0) {
                echo json_encode(['success'=>false,'message'=>'Employee, period and a valid amount are required.']); break;
            }
            $stmt = $conn->prepare("INSERT INTO payroll_advance_entries (employee_id,entry_type,amount,pay_year,pay_month,remarks,created_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("isdiisi", $empId, $type, $amt, $year, $month, $remarks, $userId);
            $stmt->execute();
            echo json_encode(['success'=>true,'message'=>'Entry added.']);
            break;
        }

        case 'pd_delete': {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("UPDATE payroll_advance_entries SET is_deleted=1 WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success'=>true,'message'=>'Entry removed.']);
            break;
        }

        /* ════════════════════════════════════════════════
           HOLD / RELEASE SALARY
        ════════════════════════════════════════════════ */
        case 'hs_hold': {
            $ids   = json_decode($_POST['employee_ids'] ?? '[]', true) ?: [];
            $year  = (int)($_POST['pay_year'] ?? 0);
            $month = (int)($_POST['pay_month'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            if (!$ids || !$year || !$month) { echo json_encode(['success'=>false,'message'=>'Select employees and a pay period.']); break; }
            
            $stmt = $conn->prepare("
                INSERT INTO payroll_salary_holds (employee_id,pay_year,pay_month,status,remarks,created_by)
                VALUES (?,?,?,'Held',?,?)
                ON DUPLICATE KEY UPDATE status='Held', remarks=?, released_at=NULL
            ");
            foreach ($ids as $eid) {
                $eid_int = (int)$eid;
                $stmt->bind_param("iiisis", $eid_int, $year, $month, $remarks, $userId, $remarks);
                $stmt->execute();
            }
            
            $idList = implode(',', array_map('intval', $ids));
            $conn->query("UPDATE payslips SET status='Held' WHERE pay_year={$year} AND pay_month={$month} AND employee_id IN ({$idList})");
            
            echo json_encode(['success'=>true,'message'=>'Salary held for selected employees.']);
            break;
        }

        case 'hs_held_list': {
            $year  = (int)($_GET['year'] ?? 0);
            $month = (int)($_GET['month'] ?? 0);
            $stmt = $conn->prepare("
                SELECT h.id AS hold_id, h.employee_id, e.employee_code, " . empName() . " AS name
                FROM payroll_salary_holds h
                JOIN employees e ON e.id = h.employee_id
                WHERE h.pay_year=? AND h.pay_month=? AND h.status='Held'
                ORDER BY e.employee_code
            ");
            $stmt->bind_param("ii", $year, $month);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode(['success'=>true,'data'=>$result->fetch_all(MYSQLI_ASSOC)]);
            break;
        }

        case 'hs_release': {
            $ids   = json_decode($_POST['employee_ids'] ?? '[]', true) ?: [];
            $year  = (int)($_POST['pay_year'] ?? 0);
            $month = (int)($_POST['pay_month'] ?? 0);
            if (!$ids) { echo json_encode(['success'=>false,'message'=>'Select at least one employee.']); break; }
            
            $stmt = $conn->prepare("UPDATE payroll_salary_holds SET status='Released', released_at=NOW() WHERE employee_id=? AND pay_year=? AND pay_month=?");
            foreach ($ids as $eid) {
                $eid_int = (int)$eid;
                $stmt->bind_param("iii", $eid_int, $year, $month);
                $stmt->execute();
            }
            echo json_encode(['success'=>true,'message'=>'Salary released for selected employees.']);
            break;
        }

        /* ════════════════════════════════════════════════
           APPROVE PAYSLIP
        ════════════════════════════════════════════════ */
        case 'ap_action': {
            $ids    = json_decode($_POST['employee_ids'] ?? '[]', true) ?: [];
            $fy     = (int)($_POST['financial_year'] ?? 0);
            $month  = (int)($_POST['pay_month'] ?? 0);
            $year   = (int)($_POST['pay_year'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            
            if (!$ids || !$year || !$month || !in_array($status, ['Approved','Rejected'])) {
                echo json_encode(['success'=>false,'message'=>'Select employees and a valid pay period.']); break;
            }
            
            $idList = implode(',', array_map('intval', $ids));
            $col = $status === 'Approved' ? 'approved_at=NOW(),' : '';
            $conn->query("UPDATE payslips SET status='{$status}', {$col} updated_at=NOW() 
                          WHERE pay_year={$year} AND pay_month={$month} AND employee_id IN ({$idList}) AND is_deleted=0");
            $count = $conn->affected_rows;
            
            echo json_encode(['success'=>true,'message'=>"{$status}: {$count} payslip(s) updated."]);
            break;
        }

        /* ════════════════════════════════════════════════
           EDIT PAYSLIP
        ════════════════════════════════════════════════ */
        case 'ep_periods': {
            $empId = (int)($_GET['employee_id'] ?? 0);
            $stmt = $conn->prepare("SELECT pay_year, pay_month FROM payslips WHERE employee_id=? AND is_deleted=0 ORDER BY pay_year DESC, pay_month DESC");
            $stmt->bind_param("i", $empId);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $out = array_map(fn($r) => ['value' => $r['pay_year'].'-'.str_pad($r['pay_month'],2,'0',STR_PAD_LEFT), 'label' => periodLabel($r['pay_year'],$r['pay_month'])], $rows);
            echo json_encode(['success'=>true,'data'=>$out]);
            break;
        }

        case 'ep_load': {
            $empId = (int)($_GET['employee_id'] ?? 0);
            $year  = (int)($_GET['year'] ?? 0);
            $month = (int)($_GET['month'] ?? 0);
            
            $stmt = $conn->prepare("SELECT * FROM payslips WHERE employee_id=? AND pay_year=? AND pay_month=? AND is_deleted=0");
            $stmt->bind_param("iii", $empId, $year, $month);
            $stmt->execute();
            $ps = $stmt->get_result()->fetch_assoc();
            
            if (!$ps) { echo json_encode(['success'=>false,'message'=>'No payslip found for this period.']); break; }
            
            $stmt = $conn->prepare("SELECT id, component_type, code, name, amount FROM payslip_components WHERE payslip_id=? ORDER BY sort_order, id");
            $stmt->bind_param("i", $ps['id']);
            $stmt->execute();
            $ps['components'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success'=>true,'data'=>$ps]);
            break;
        }

        case 'ep_delete_payslip': {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("UPDATE payslips SET is_deleted=1 WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success'=>true,'message'=>'Payslip deleted.']);
            break;
        }

        /* ════════════════════════════════════════════════
           SHARED COMPONENT SAVE/DELETE  (payslip & salary structure)
        ════════════════════════════════════════════════ */
        case 'component_save': {
            $context = $_POST['context'] ?? ''; // 'payslip' | 'structure'
            $id      = (int)($_POST['id'] ?? 0);
            $parentId= (int)($_POST['parent_id'] ?? 0);
            $type    = trim($_POST['component_type'] ?? 'Earning');
            $code    = trim($_POST['code'] ?? '');
            $name    = trim($_POST['name'] ?? '');
            $amount  = (float)($_POST['amount'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            if (!$name) { echo json_encode(['success'=>false,'message'=>'Component name is required.']); break; }

            if ($context === 'payslip') {
                if ($id) {
                    $stmt = $conn->prepare("UPDATE payslip_components SET code=?,name=?,amount=? WHERE id=?");
                    $stmt->bind_param("ssdi", $code, $name, $amount, $id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("INSERT INTO payslip_components (payslip_id,component_type,code,name,amount) VALUES (?,?,?,?,?)");
                    $stmt->bind_param("isssd", $parentId, $type, $code, $name, $amount);
                    $stmt->execute();
                }
                recalcPayslipTotals($conn, $parentId ?: payslipIdFromComponent($conn, $id));
            } else {
                if ($id) {
                    $stmt = $conn->prepare("UPDATE salary_structure_components SET code=?,name=?,amount=?,remarks=? WHERE id=?");
                    $stmt->bind_param("ssdsi", $code, $name, $amount, $remarks, $id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("INSERT INTO salary_structure_components (structure_id,component_type,code,name,amount,remarks) VALUES (?,?,?,?,?,?)");
                    $stmt->bind_param("isssds", $parentId, $type, $code, $name, $amount, $remarks);
                    $stmt->execute();
                }
            }
            echo json_encode(['success'=>true,'message'=>'Component saved.']);
            break;
        }

        case 'component_delete': {
            $context = $_POST['context'] ?? '';
            $id      = (int)($_POST['id'] ?? 0);
            if ($context === 'payslip') {
                $psId = payslipIdFromComponent($conn, $id);
                $stmt = $conn->prepare("DELETE FROM payslip_components WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                recalcPayslipTotals($conn, $psId);
            } else {
                $stmt = $conn->prepare("DELETE FROM salary_structure_components WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
            echo json_encode(['success'=>true,'message'=>'Component removed.']);
            break;
        }

        /* ════════════════════════════════════════════════
           LOANS
        ════════════════════════════════════════════════ */
        case 'ln_stats': {
            $period = $_GET['period'] ?? 'month';
            $where = "is_deleted=0 AND status='Active'";
            if ($period === 'month') $where .= " AND YEAR(issue_date)=YEAR(CURDATE()) AND MONTH(issue_date)=MONTH(CURDATE())";
            elseif ($period === 'year') $where .= " AND YEAR(issue_date)=YEAR(CURDATE())";
            
            $totalStmt = $conn->query("SELECT COALESCE(SUM(amount),0) t FROM loans WHERE {$where}");
            $total = (float)$totalStmt->fetch_assoc()['t'];
            
            $byType = $conn->query("SELECT loan_type, COUNT(*) c, SUM(amount) s FROM loans WHERE {$where} GROUP BY loan_type")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success'=>true,'total'=>$total,'by_type'=>$byType]);
            break;
        }

        case 'ln_overview': {
            $offset = (int)($_GET['offset'] ?? 0);
            $data = monthlyLoanSeries($conn, $offset, 'COUNT(*)');
            echo json_encode(['success'=>true] + $data);
            break;
        }
        case 'ln_expenditure': {
            $offset = (int)($_GET['offset'] ?? 0);
            $data = monthlyLoanSeries($conn, $offset, 'COALESCE(SUM(amount),0)');
            echo json_encode(['success'=>true] + $data);
            break;
        }

        case 'ln_list': {
            $empId = (int)($_GET['employee_id'] ?? 0);
            $from  = trim($_GET['from'] ?? '');
            $to    = trim($_GET['to'] ?? '');
            $sql = "SELECT l.id, " . empName() . " AS employee_name, l.loan_type, l.amount, l.due_amount, l.issue_date, l.end_date, l.status 
                    FROM loans l JOIN employees e ON e.id=l.employee_id WHERE l.is_deleted=0";
            
            $types = ""; $params = [];
            if ($empId) { $sql .= " AND l.employee_id=?"; $types .= "i"; $params[] = $empId; }
            if ($from)  { $sql .= " AND l.issue_date>=?"; $types .= "s"; $params[] = $from; }
            if ($to)    { $sql .= " AND l.issue_date<=?"; $types .= "s"; $params[] = $to; }
            $sql .= " ORDER BY l.issue_date DESC";
            
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode(['success'=>true,'data'=>$result->fetch_all(MYSQLI_ASSOC)]);
            break;
        }

        case 'ln_get': {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $conn->prepare("SELECT l.*, " . empName() . " AS employee_name FROM loans l JOIN employees e ON e.id=l.employee_id WHERE l.id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            echo $row ? json_encode(['success'=>true,'data'=>$row]) : json_encode(['success'=>false,'message'=>'Not found']);
            break;
        }

        case 'ln_add': {
            $empId = (int)($_POST['employee_id'] ?? 0);
            $type  = trim($_POST['loan_type'] ?? 'Personal');
            $amt   = (float)($_POST['amount'] ?? 0);
            $issue = trim($_POST['issue_date'] ?? '');
            $end   = trim($_POST['end_date'] ?? '') ?: null;
            $remarks = trim($_POST['remarks'] ?? '');
            
            if (!$empId || $amt <= 0 || !$issue) { echo json_encode(['success'=>false,'message'=>'Employee, amount and issue date are required.']); break; }
            
            $stmt = $conn->prepare("INSERT INTO loans (employee_id,loan_type,amount,due_amount,issue_date,end_date,remarks,created_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("isddsssi", $empId, $type, $amt, $amt, $issue, $end, $remarks, $userId);
            $stmt->execute();
            echo json_encode(['success'=>true,'message'=>'Loan added.']);
            break;
        }

        case 'ln_delete': {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("UPDATE loans SET is_deleted=1 WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success'=>true,'message'=>'Loan removed.']);
            break;
        }

        /* ════════════════════════════════════════════════
           PROCESS PAYSLIP
        ════════════════════════════════════════════════ */
        case 'pp_process': {
            $ids   = json_decode($_POST['employee_ids'] ?? '[]', true) ?: [];
            $fy    = (int)($_POST['financial_year'] ?? 0);
            $year  = (int)($_POST['pay_year'] ?? 0);
            $month = (int)($_POST['pay_month'] ?? 0);
            $reprocess = (int)($_POST['reprocess'] ?? 0);
            if (!$ids || !$year || !$month) { echo json_encode(['success'=>false,'message'=>'Select employees and a pay period.']); break; }

            $processed = 0; $skipped = 0;
            foreach ($ids as $eid) {
                $eid = (int)$eid;
                
                $chk = $conn->prepare("SELECT id FROM payslips WHERE employee_id=? AND pay_year=? AND pay_month=? AND is_deleted=0");
                $chk->bind_param("iii", $eid, $year, $month);
                $chk->execute();
                $existing = $chk->get_result()->fetch_assoc();
                
                if ($existing && !$reprocess) { $skipped++; continue; }

                $sStmt = $conn->prepare("SELECT id FROM salary_structures WHERE employee_id=?");
                $sStmt->bind_param("i", $eid);
                $sStmt->execute();
                $structRow = $sStmt->get_result()->fetch_row();
                $structId = $structRow[0] ?? null;
                
                $earnings = 0; $deductions = 0; $comps = [];
                if ($structId) {
                    $cStmt = $conn->prepare("SELECT component_type,code,name,amount FROM salary_structure_components WHERE structure_id=? AND component_type IN ('Earning','Deduction','Employer Contribution')");
                    $cStmt->bind_param("i", $structId);
                    $cStmt->execute();
                    $comps = $cStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    foreach ($comps as $c) {
                        if ($c['component_type'] === 'Earning') $earnings += (float)$c['amount'];
                        if ($c['component_type'] === 'Deduction') $deductions += (float)$c['amount'];
                    }
                }
                $net = $earnings - $deductions;

                if ($existing) {
                    $psId = $existing['id'];
                    $uStmt = $conn->prepare("UPDATE payslips SET financial_year=?,gross_earnings=?,total_deductions=?,net_pay=?,status='Processed',processed_at=NOW() WHERE id=?");
                    $uStmt->bind_param("idddi", $fy, $earnings, $deductions, $net, $psId);
                    $uStmt->execute();
                    
                    $dStmt = $conn->prepare("DELETE FROM payslip_components WHERE payslip_id=?");
                    $dStmt->bind_param("i", $psId);
                    $dStmt->execute();
                } else {
                    $iStmt = $conn->prepare("INSERT INTO payslips (employee_id,financial_year,pay_year,pay_month,status,gross_earnings,total_deductions,net_pay,processed_at) VALUES (?,?,?,?,'Processed',?,?,?,NOW())");
                    $iStmt->bind_param("iiiiddd", $eid, $fy, $year, $month, $earnings, $deductions, $net);
                    $iStmt->execute();
                    $psId = $conn->insert_id;
                }
                
                $insC = $conn->prepare("INSERT INTO payslip_components (payslip_id,component_type,code,name,amount) VALUES (?,?,?,?,?)");
                foreach ($comps as $c) {
                    $insC->bind_param("isssd", $psId, $c['component_type'], $c['code'], $c['name'], $c['amount']);
                    $insC->execute();
                }
                $processed++;
            }
            echo json_encode(['success'=>true,'message'=>"Processed {$processed} payslip(s)." . ($skipped ? " {$skipped} skipped (already processed)." : '')]);
            break;
        }

        /* ════════════════════════════════════════════════
           FINAL SETTLEMENT
        ════════════════════════════════════════════════ */
        case 'fs_load': {
            $empId = (int)($_GET['employee_id'] ?? 0);
            $stmt = $conn->prepare("
                SELECT e.id, e.employee_code, " . empName() . " AS name, e.dob, e.gender,
                       e.department, e.designation, e.join_date
                FROM employees e WHERE e.id=?
            ");
            $stmt->bind_param("i", $empId);
            $stmt->execute();
            $emp = $stmt->get_result()->fetch_assoc();
            
            if (!$emp) { echo json_encode(['success'=>false,'message'=>'Employee not found.']); break; }
            $emp['age'] = $emp['dob'] ? (new DateTime($emp['dob']))->diff(new DateTime())->y : null;

            $ss = $conn->prepare("SELECT pf_applicable,esi_applicable,pt_applicable FROM salary_structures WHERE employee_id=?");
            $ss->bind_param("i", $empId);
            $ss->execute();
            $flags = $ss->get_result()->fetch_assoc() ?: ['pf_applicable'=>1,'esi_applicable'=>1,'pt_applicable'=>1];
            $emp = array_merge($emp, $flags);

            $fsStmt = $conn->prepare("SELECT * FROM final_settlements WHERE employee_id=?");
            $fsStmt->bind_param("i", $empId);
            $fsStmt->execute();
            $emp['settlement'] = $fsStmt->get_result()->fetch_assoc() ?: null;

            echo json_encode(['success'=>true,'data'=>$emp]);
            break;
        }

        case 'fs_save': {
            $empId = (int)($_POST['employee_id'] ?? 0);
            if (!$empId) { echo json_encode(['success'=>false,'message'=>'Invalid employee.']); break; }
            
            $resignation_date   = $_POST['resignation_date'] ?? null;
            $leaving_date       = $_POST['leaving_date'] ?? null;
            $reason_resignation = trim($_POST['reason_resignation'] ?? '');
            $settlement_period  = trim($_POST['settlement_period'] ?? '');
            $shortfall_notice   = (int)($_POST['shortfall_notice'] ?? 0);
            $reason_esi         = trim($_POST['reason_esi'] ?? '');
            $reason_pf          = trim($_POST['reason_pf'] ?? '');
            $remarks            = trim($_POST['remarks'] ?? '');
            
            $stmt = $conn->prepare("SELECT id FROM final_settlements WHERE employee_id=?");
            $stmt->bind_param("i", $empId);
            $stmt->execute();
            
            if ($stmt->get_result()->fetch_assoc()) {
                $upd = $conn->prepare("UPDATE final_settlements SET resignation_date=?,leaving_date=?,reason_resignation=?,settlement_period=?,shortfall_notice=?,reason_esi=?,reason_pf=?,remarks=?,status='Submitted' WHERE employee_id=?");
                $upd->bind_param("ssssissssi", $resignation_date, $leaving_date, $reason_resignation, $settlement_period, $shortfall_notice, $reason_esi, $reason_pf, $remarks, $empId);
                $upd->execute();
            } else {
                $ins = $conn->prepare("INSERT INTO final_settlements (employee_id,resignation_date,leaving_date,reason_resignation,settlement_period,shortfall_notice,reason_esi,reason_pf,remarks,status) VALUES (?,?,?,?,?,?,?,?,?,'Submitted')");
                $ins->bind_param("issssissss", $empId, $resignation_date, $leaving_date, $reason_resignation, $settlement_period, $shortfall_notice, $reason_esi, $reason_pf, $remarks);
                $ins->execute();
            }
            echo json_encode(['success'=>true,'message'=>'Resignation details saved.']);
            break;
        }

        case 'fs_loans': {
            $empId = (int)($_GET['employee_id'] ?? 0);
            $stmt = $conn->prepare("SELECT loan_type,amount,due_amount,issue_date,status FROM loans WHERE employee_id=? AND is_deleted=0 ORDER BY issue_date DESC");
            $stmt->bind_param("i", $empId);
            $stmt->execute();
            echo json_encode(['success'=>true,'data'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
            break;
        }
        case 'fs_advance': {
            $empId = (int)($_GET['employee_id'] ?? 0);
            $stmt = $conn->prepare("SELECT entry_type,amount,pay_year,pay_month,remarks FROM payroll_advance_entries WHERE employee_id=? AND is_deleted=0 ORDER BY pay_year DESC,pay_month DESC");
            $stmt->bind_param("i", $empId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as &$r) $r['period'] = periodLabel($r['pay_year'],$r['pay_month']);
            echo json_encode(['success'=>true,'data'=>$rows]);
            break;
        }

        /* ════════════════════════════════════════════════
           SALARY STRUCTURE
        ════════════════════════════════════════════════ */
        case 'ss_load': {
            $empId = (int)($_GET['employee_id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM salary_structures WHERE employee_id=?");
            $stmt->bind_param("i", $empId);
            $stmt->execute();
            $struct = $stmt->get_result()->fetch_assoc();
            
            if (!$struct) {
                $ins = $conn->prepare("INSERT INTO salary_structures (employee_id,pf_applicable,esi_applicable,pt_applicable,pt_state) VALUES (?,1,1,1,'')");
                $ins->bind_param("i", $empId);
                $ins->execute();
                $structId = $conn->insert_id;
                $struct = ['id'=>$structId,'employee_id'=>$empId,'pf_applicable'=>1,'esi_applicable'=>1,'pt_applicable'=>1,'pt_state'=>''];
            }
            
            $cStmt = $conn->prepare("SELECT id,component_type,code,name,amount,remarks FROM salary_structure_components WHERE structure_id=? ORDER BY component_type, sort_order, id");
            $cStmt->bind_param("i", $struct['id']);
            $cStmt->execute();
            $struct['components'] = $cStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success'=>true,'data'=>$struct]);
            break;
        }

        case 'ss_save_statutory': {
            $structId = (int)($_POST['structure_id'] ?? 0);
            $pf  = (int)($_POST['pf_applicable'] ?? 0);
            $esi = (int)($_POST['esi_applicable'] ?? 0);
            $pt  = (int)($_POST['pt_applicable'] ?? 0);
            $state = trim($_POST['pt_state'] ?? '');
            
            $stmt = $conn->prepare("UPDATE salary_structures SET pf_applicable=?,esi_applicable=?,pt_applicable=?,pt_state=? WHERE id=?");
            $stmt->bind_param("iiisi", $pf, $esi, $pt, $state, $structId);
            $stmt->execute();
            echo json_encode(['success'=>true,'message'=>'Statutory settings updated.']);
            break;
        }

        /* ════════════════════════════════════════════════
           TIMESHEET
        ════════════════════════════════════════════════ */
        case 'ts_list': {
            $empId = (int)($_GET['employee_id'] ?? 0);
            $year  = (int)($_GET['year'] ?? 0);
            $month = (int)($_GET['month'] ?? 0);
            $sql = "SELECT t.id, e.employee_code, " . empName() . " AS name, t.pay_year, t.pay_month 
                    FROM timesheets t JOIN employees e ON e.id=t.employee_id WHERE 1=1";
            
            $types = ""; $params = [];
            if ($year)  { $sql .= " AND t.pay_year=?"; $types .= "i"; $params[] = $year; }
            if ($month) { $sql .= " AND t.pay_month=?"; $types .= "i"; $params[] = $month; }
            if ($empId) { $sql .= " AND t.employee_id=?"; $types .= "i"; $params[] = $empId; }
            $sql .= " ORDER BY e.employee_code";
            
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as &$r) $r['period'] = periodLabel($r['pay_year'],$r['pay_month']);
            echo json_encode(['success'=>true,'data'=>$rows]);
            break;
        }

        case 'ts_get': {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $conn->prepare("SELECT t.*, e.employee_code, " . empName() . " AS name FROM timesheets t JOIN employees e ON e.id=t.employee_id WHERE t.id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) $row['period'] = periodLabel($row['pay_year'],$row['pay_month']);
            echo $row ? json_encode(['success'=>true,'data'=>$row]) : json_encode(['success'=>false,'message'=>'Not found']);
            break;
        }

        case 'ts_create': {
            $empId = (int)($_POST['employee_id'] ?? 0);
            $year  = (int)($_POST['pay_year'] ?? 0);
            $month = (int)($_POST['pay_month'] ?? 0);
            if (!$empId || !$year || !$month) { echo json_encode(['success'=>false,'message'=>'Employee and period required.']); break; }
            
            $stmt = $conn->prepare("INSERT INTO timesheets (employee_id,pay_year,pay_month) VALUES (?,?,?) ON DUPLICATE KEY UPDATE pay_year=pay_year");
            $stmt->bind_param("iii", $empId, $year, $month);
            $stmt->execute();
            echo json_encode(['success'=>true,'message'=>'Timesheet created.']);
            break;
        }

        case 'ts_save': {
            $id = (int)($_POST['id'] ?? 0);
            $cols = ['total_days','days_present','days_absent','holidays','holidays_worked','week_offs','week_offs_worked',
                     'short_hours_days','early_days','late_days','paid_leaves','unpaid_leaves',
                     'total_hours','hours_worked','overtime_hours','short_hours',
                     'comp_off_earned','comp_off_used','other_remarks'];
            
            $sets = []; $vals = [];
            foreach ($cols as $c) { 
                $sets[] = "{$c}=?"; 
                $vals[] = $_POST[$c] ?? 0; 
            }
            $vals[] = $id; // Append ID for WHERE clause
            
            // Build dynamic binding string (mostly 's' for strings/floats/ints interchangeably in MySQLi params)
            $types = str_repeat('s', count($cols)) . 'i'; 
            
            $stmt = $conn->prepare("UPDATE timesheets SET " . implode(',', $sets) . " WHERE id=?");
            $stmt->bind_param($types, ...$vals);
            $stmt->execute();
            
            echo json_encode(['success'=>true,'message'=>'Timesheet updated.']);
            break;
        }

        default:
            echo json_encode(['success'=>false,'message'=>'Invalid action.']);
    }
} catch (Exception $e) {
    error_log('Payroll API: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'A database error occurred.']);
}

/* ════════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════════ */
function recalcPayslipTotals(mysqli $conn, $payslipId) {
    if (!$payslipId) return;
    
    $stmt = $conn->prepare("SELECT component_type, SUM(amount) s FROM payslip_components WHERE payslip_id=? GROUP BY component_type");
    $stmt->bind_param("i", $payslipId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $earn = 0; $ded = 0;
    while ($r = $result->fetch_assoc()) {
        if ($r['component_type'] === 'Earning') $earn = (float)$r['s'];
        if ($r['component_type'] === 'Deduction') $ded = (float)$r['s'];
    }
    
    $upd = $conn->prepare("UPDATE payslips SET gross_earnings=?,total_deductions=?,net_pay=? WHERE id=?");
    $net = $earn - $ded;
    $upd->bind_param("dddi", $earn, $ded, $net, $payslipId);
    $upd->execute();
}

function payslipIdFromComponent(mysqli $conn, $componentId) {
    if (!$componentId) return 0;
    
    $stmt = $conn->prepare("SELECT payslip_id FROM payslip_components WHERE id=?");
    $stmt->bind_param("i", $componentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    
    return (int)($row[0] ?? 0);
}

function monthlyLoanSeries(mysqli $conn, $offset, $aggExpr) {
    // 6-month window, shifted by $offset windows
    $labels = []; $data = [];
    $base = new DateTime('first day of this month');
    $base->modify((($offset * 6) - 5) . ' months');
    
    for ($i = 0; $i < 6; $i++) {
        $start = clone $base; $start->modify("+{$i} months");
        $end = clone $start; $end->modify('last day of this month');
        
        $stmt = $conn->prepare("SELECT {$aggExpr} v FROM loans WHERE is_deleted=0 AND issue_date BETWEEN ? AND ?");
        $startDate = $start->format('Y-m-01');
        $endDate = $end->format('Y-m-d');
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        
        $row = $stmt->get_result()->fetch_assoc();
        
        $labels[] = $start->format('M Y');
        $data[] = (float)($row['v'] ?? 0);
    }
    return ['labels' => $labels, 'data' => $data];
}