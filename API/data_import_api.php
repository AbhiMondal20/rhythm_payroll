<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/db_client.php';
require_once '../includes/config.php';

define('IMPORT_UPLOAD_DIR', __DIR__ . '/uploads/data_imports/');
if (!is_dir(IMPORT_UPLOAD_DIR)) mkdir(IMPORT_UPLOAD_DIR, 0755, true);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ── Template column definitions per data type ─────────────── */
function getTemplateColumns(string $type): array {
    $map = [
        'Employee'                    => ['employee_code','first_name','last_name','email','mobile','date_of_joining','department','designation','branch','gender','date_of_birth'],
        'Advance Payment/Deduction'   => ['employee_code','type','amount','month','year','remarks'],
        'Approve Leaves'              => ['employee_code','leave_type','from_date','to_date','reason'],
        'Assets'                      => ['employee_code','asset_name','asset_code','assigned_date','remarks'],
        'Assign Shift'                => ['employee_code','shift_name','effective_from','effective_to'],
        'Day Status'                  => ['employee_code','date','status','remarks'],
        'Additional Fields'           => ['employee_code','field_name','value'],
        'Employee Images'             => ['employee_code','image_url'],
        'Employee Statutory Details'  => ['employee_code','pan_number','uan_number','esic_number','pf_number'],
        'Leave Accumulation'          => ['employee_code','leave_type','opening_balance','year'],
        'Leave Application'           => ['employee_code','leave_type','from_date','to_date','reason'],
        'Loan'                        => ['employee_code','loan_type','amount','emi','start_month','start_year'],
        'Pay Structure'               => ['employee_code','structure_name','effective_from'],
        'Payroll Variables'           => ['employee_code','variable_name','amount','month','year'],
        'Payslips'                    => ['employee_code','month','year','net_pay'],
        'Reimbursement'               => ['employee_code','type','amount','month','year','remarks'],
        'Training'                    => ['employee_code','training_name','from_date','to_date','trainer'],
    ];
    return $map[$type] ?? ['column1','column2','column3'];
}

/* ── Import instructions ────────────────────────────────────── */
function getInstructions(string $type): string {
    $cols = implode(', ', getTemplateColumns($type));
    return "<ul>
      <li>Download the CSV template and fill in your data.</li>
      <li>Required columns: <strong>{$cols}</strong></li>
      <li>Dates must be in <strong>DD/MM/YYYY</strong> or <strong>YYYY-MM-DD</strong> format.</li>
      <li>Employee codes must match existing employees in the system.</li>
      <li>Do not remove or rename the header row.</li>
      <li>Save the file as CSV (comma-separated) before uploading.</li>
    </ul>";
}

try {
    switch ($action) {

        /* ── DOWNLOAD TEMPLATE ──────────────────────────────── */
        case 'template':
            $type = trim($_GET['type'] ?? '');
            if (!$type) { echo json_encode(['success'=>false,'message'=>'Type required']); break; }

            $cols    = getTemplateColumns($type);
            $slug    = preg_replace('/[^a-z0-9]+/i','_', $type);
            $filename = "Template_{$slug}.csv";

            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Pragma: no-cache');
            $out = fopen('php://output','w');
            fputcsv($out, $cols);
            fclose($out);
            exit();

        /* ── GET INSTRUCTIONS ───────────────────────────────── */
        case 'instructions':
            $type = trim($_GET['type'] ?? '');
            echo json_encode(['success'=>true,'html'=>getInstructions($type)]);
            break;

        /* ── UPLOAD & PARSE CSV ─────────────────────────────── */
        case 'upload':
            $type = trim($_POST['type'] ?? '');
            if (!$type) { echo json_encode(['success'=>false,'message'=>'Data type required']); break; }
            if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success'=>false,'message'=>'File upload error: '.$_FILES['file']['error']]); break;
            }

            $file     = $_FILES['file'];
            $origName = basename($file['name']);
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv','xlsx','xls'])) {
                echo json_encode(['success'=>false,'message'=>'Only CSV/XLSX files allowed']); break;
            }
            if ($file['size'] > 10*1024*1024) {
                echo json_encode(['success'=>false,'message'=>'File too large (max 10 MB)']); break;
            }

            $savedName = uniqid('import_').'.'.$ext;
            move_uploaded_file($file['tmp_name'], IMPORT_UPLOAD_DIR.$savedName);

            // Parse CSV for preview (first 10 rows)
            $rows = [];
            $headers = [];
            if ($ext === 'csv') {
                if (($fh = fopen(IMPORT_UPLOAD_DIR.$savedName,'r')) !== false) {
                    $headers = fgetcsv($fh) ?: [];
                    $count = 0;
                    while (($row = fgetcsv($fh)) !== false && $count < 10) {
                        $rows[] = $row;
                        $count++;
                    }
                    fclose($fh);
                }
            } else {
                // Minimal XLSX stub (would need PhpSpreadsheet for full support)
                $headers = getTemplateColumns($type);
                $rows    = [array_fill(0, count($headers), '(preview not available for XLSX)')];
            }

            // Count total rows
            $totalRows = 0;
            if ($ext === 'csv') {
                $totalRows = max(0, count(file(IMPORT_UPLOAD_DIR.$savedName)) - 1);
            }

            echo json_encode([
                'success'    => true,
                'saved_name' => $savedName,
                'orig_name'  => $origName,
                'headers'    => $headers,
                'preview'    => $rows,
                'total_rows' => $totalRows,
            ]);
            break;

        /* ── PROCESS IMPORT ─────────────────────────────────── */
        case 'import':
            $type      = trim($_POST['type']       ?? '');
            $savedName = trim($_POST['saved_name'] ?? '');
            $origName  = trim($_POST['orig_name']  ?? '');

            if (!$type || !$savedName) {
                echo json_encode(['success'=>false,'message'=>'Missing parameters']); break;
            }

            $filePath = IMPORT_UPLOAD_DIR . basename($savedName);
            if (!file_exists($filePath)) {
                echo json_encode(['success'=>false,'message'=>'Uploaded file not found. Please re-upload.']); break;
            }

            // ── Log the import attempt ──
            $pdo->prepare(
                "INSERT INTO data_import_history
                    (import_type, import_name, file_uploaded, file_path, status, imported_by)
                 VALUES (?, ?, ?, ?, 'In Progress', ?)"
            )->execute([
                'Upload'.str_replace([' ','/'],'',$type),
                $origName,
                'Import'.str_replace([' ','/'],'',$type),
                $savedName,
                $_SESSION['user_id'] ?? 0
            ]);
            $importId = $pdo->lastInsertId();

            // ── Actual import logic (stub – replace with real DB writes per type) ──
            $errors   = [];
            $imported = 0;
            $ext = strtolower(pathinfo($savedName, PATHINFO_EXTENSION));

            if ($ext === 'csv' && ($fh = fopen($filePath,'r')) !== false) {
                $headers  = array_map('strtolower', array_map('trim', fgetcsv($fh) ?: []));
                $rowNum   = 1;
                while (($row = fgetcsv($fh)) !== false) {
                    $rowNum++;
                    if (array_filter($row) === []) continue;   // skip blank rows
                    $data = array_combine(array_slice($headers,0,count($row)), $row) ?: [];

                    // ── Type-specific validation / insert (add your own logic here) ──
                    // Example for Employee:
                    // if ($type === 'Employee') { ... $pdo->prepare("INSERT INTO employees…")->execute(…); }

                    $imported++;
                }
                fclose($fh);
            }

            $status   = empty($errors) ? 'Completed' : 'Error';
            $errJson  = empty($errors) ? null : json_encode($errors);

            $pdo->prepare(
                "UPDATE data_import_history
                 SET status=?, error_log=?, rows_imported=?, updated_at=NOW()
                 WHERE id=?"
            )->execute([$status, $errJson, $imported, $importId]);

            if ($status === 'Completed') {
                echo json_encode(['success'=>true,'message'=>"Import completed. {$imported} row(s) processed.",'imported'=>$imported]);
            } else {
                echo json_encode(['success'=>false,'message'=>"Import finished with errors ({$imported} rows processed).",'errors'=>$errors]);
            }
            break;

        /* ── HISTORY LIST ───────────────────────────────────── */
        case 'history':
            $stmt = $pdo->prepare(
                "SELECT id,
                        DATE_FORMAT(created_at,'%d/%m/%Y %h:%i %p') AS date_fmt,
                        import_type, import_name, file_uploaded, status, error_log
                 FROM data_import_history
                 WHERE imported_by = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 ORDER BY created_at DESC
                 LIMIT 200"
            );
            $stmt->execute([$_SESSION['user_id'] ?? 0]);
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            echo json_encode(['success'=>false,'message'=>'Invalid action.']);
    }
} catch (PDOException $e) {
    error_log('Data Import API: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'A database error occurred.']);
}