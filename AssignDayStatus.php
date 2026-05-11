<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Assign Day Status';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

function e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ════════ DB TABLES ════════ */
$conn->query("
CREATE TABLE IF NOT EXISTS att_day_status_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id INT NOT NULL,
    shift_date DATE NOT NULL,
    day_status VARCHAR(80) NOT NULL,
    assigned_by INT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_date (emp_id, shift_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS attendance_day_status_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ════════ DUMMY STATUS DATA ════════ */
$statusCount = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM attendance_day_status_master");
if ($res) {
    $statusCount = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($statusCount === 0) {
    $dummyStatus = [
        'WeekOff',
        'Holiday',
        'Worked On Week Off',
        'Worked On Holiday',
        'Worked On Week Off First Half',
        'Worked On Week Off Second Half',
        'Worked On Holiday First Half',
        'Worked On Holiday Second Half'
    ];

    $stmt = $conn->prepare("
        INSERT INTO attendance_day_status_master
        (status_name, status)
        VALUES (?, 'active')
    ");

    if ($stmt) {
        foreach ($dummyStatus as $s) {
            $stmt->bind_param("s", $s);
            $stmt->execute();
        }
        $stmt->close();
    }
}

/* ════════ DUMMY EMPLOYEE DATA IF EMPTY ════════ */
$empCount = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM employees");
if ($res) {
    $empCount = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($empCount === 0) {
    $dummyEmployees = [
        ['1104','Abhijit Kumar Mondal','Human Resource','HR Manager'],
        ['1105','Priya Sharma','Finance','Accountant'],
        ['1106','Rajesh Dey','Information Technology','IT Executive'],
        ['1107','Sunita Pal','Administration','Admin Officer'],
        ['1108','Mohan Das','LAB','Lab Technician'],
    ];

    $stmt = $conn->prepare("
        INSERT INTO employees
        (employee_code, employee_name, department, designation, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
    ");

    if ($stmt) {
        foreach ($dummyEmployees as $emp) {
            $stmt->bind_param("ssss", $emp[0], $emp[1], $emp[2], $emp[3]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

/* ════════ POST HANDLER ════════ */
$flash = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_day_status') {
    $emp_ids     = $_POST['emp_ids'] ?? [];
    $shift_date  = trim($_POST['shift_date'] ?? '');
    $day_status  = trim($_POST['day_status'] ?? '');
    $assigned_by = (int)($_SESSION['user_id'] ?? 0);

    if (empty($emp_ids)) {
        $flash = 'Please select at least one employee.';
        $flash_type = 'error';
    } elseif ($shift_date === '' || $day_status === '') {
        $flash = 'Shift Date and Day Status are required.';
        $flash_type = 'error';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO att_day_status_assignments
            (emp_id, shift_date, day_status, assigned_by, assigned_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                day_status = VALUES(day_status),
                assigned_by = VALUES(assigned_by),
                updated_at = NOW()
        ");

        if (!$stmt) {
            $flash = 'Save failed: ' . $conn->error;
            $flash_type = 'error';
        } else {
            $saved = 0;

            foreach ($emp_ids as $eid) {
                $eid = (int)$eid;
                if ($eid <= 0) continue;

                $stmt->bind_param("issi", $eid, $shift_date, $day_status, $assigned_by);

                if ($stmt->execute()) {
                    $saved++;
                }
            }

            $stmt->close();

            if ($saved > 0) {
                $flash = 'Day status "' . $day_status . '" assigned to ' . $saved . ' employee(s).';
                $flash_type = 'success';
            } else {
                $flash = 'No assignment saved.';
                $flash_type = 'error';
            }
        }
    }
}

/* ════════ FETCH EMPLOYEES FROM DB ════════ */
$all_employees = [];

$resEmp = $conn->query("
    SELECT
        id,
        employee_code AS emp_code,
        employee_name AS emp_name,
        department,
        designation,
        status
    FROM employees
    ORDER BY employee_name ASC
");

if ($resEmp) {
    while ($row = $resEmp->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $all_employees[] = $row;
    }
}

/* ════════ FETCH DAY STATUS OPTIONS ════════ */
$day_status_options = [];

$resStatus = $conn->query("
    SELECT status_name
    FROM attendance_day_status_master
    WHERE status='active'
    ORDER BY id ASC
");

if ($resStatus) {
    while ($row = $resStatus->fetch_assoc()) {
        $day_status_options[] = $row['status_name'];
    }
}

$departments = [];
$resDept = $conn->query("
    SELECT DISTINCT department
    FROM employees
    WHERE department IS NOT NULL AND department!=''
    ORDER BY department ASC
");
if ($resDept) {
    while ($d = $resDept->fetch_assoc()) {
        $departments[] = $d['department'];
    }
}

$designations = [];
$resDes = $conn->query("
    SELECT DISTINCT designation
    FROM employees
    WHERE designation IS NOT NULL AND designation!=''
    ORDER BY designation ASC
");
if ($resDes) {
    while ($d = $resDes->fetch_assoc()) {
        $designations[] = $d['designation'];
    }
}

$today = date('Y-m-d');
$selected_ids_post = array_map('intval', $_POST['emp_ids'] ?? []);

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}

.ads-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.ads-inner{padding:20px 28px}
.ads-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555;margin-bottom:22px}
.ads-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.ads-breadcrumb a:hover{text-decoration:underline}
.ads-breadcrumb .sep{color:#bbb;font-size:11px}

.ads-filter-bar{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.ads-search-wrap{position:relative;width:330px}
.ads-search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:12px}
.ads-search-input{width:100%;padding:8px 10px 8px 32px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;color:#1e2d3d;outline:none;box-sizing:border-box;background:#f9fafb;transition:border-color .15s}
.ads-search-input:focus{border-color:#2563eb;background:#fff}
.btn-filter{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;font-size:13px;color:#374151;cursor:pointer;font-weight:500;transition:background .14s}
.btn-filter:hover{background:#f1f5f9}

.ads-sel-header{display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer;user-select:none;width:fit-content}
.ads-sel-label{font-size:13.5px;font-weight:500;color:#374151}
.ads-sel-chevron{font-size:12px;color:#6b7280;transition:transform .2s}
.ads-sel-chevron.open{transform:rotate(180deg)}

.ads-emp-box{border:1px solid #e8ecf0;border-radius:8px;padding:14px 16px;margin-bottom:20px;background:#fff;display:none}
.ads-emp-box.show{display:block}
.ads-emp-item{display:flex;align-items:center;gap:10px;padding:6px 0;font-size:13.5px;color:#374151}
.ads-emp-item input[type=checkbox]{width:16px;height:16px;accent-color:#2563eb;cursor:pointer;flex-shrink:0}

.ads-form-row{display:grid;grid-template-columns:1fr 1fr;gap:22px 36px;margin-bottom:28px;max-width:700px}
.ads-field label{display:block;font-size:12.5px;color:#374151;margin-bottom:8px;font-weight:400}
.ads-field label .req{color:#ef4444;margin-right:2px}
.ads-date-wrap{position:relative}
.ads-date-wrap input[type=date]{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 28px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s;cursor:pointer}
.ads-date-wrap input[type=date]:focus{border-color:#2563eb}
.ads-date-wrap i{position:absolute;right:4px;top:50%;transform:translateY(-50%);color:#2563eb;font-size:14px;pointer-events:none}
.ads-select{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 22px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 4px center;outline:none;box-sizing:border-box;transition:border-color .16s;appearance:none;cursor:pointer}
.ads-select:focus{border-color:#2563eb}

.ads-actions{display:flex;justify-content:flex-end;max-width:700px}
.btn-assign{padding:9px 28px;background:#2563eb;border:none;border-radius:6px;font-size:13.5px;color:#fff;cursor:pointer;font-weight:600;transition:background .14s}
.btn-assign:hover{background:#1d4ed8}

/* right side center redesigned modal */
.modal-overlay{
  display:none;
  position:fixed;
  inset:0;
  background:rgba(15,23,42,.45);
  z-index:999;
  align-items:center;
  justify-content:flex-end;
  padding-right:28px;
}
.modal-overlay.show{display:flex}

.adv-modal{
  background:#fff;
  width:100%;
  max-width:980px;
  height:82vh;
  border-radius:18px;
  display:flex;
  flex-direction:column;
  overflow:hidden;
  box-shadow:0 18px 55px rgba(15,23,42,.28);
  animation:slideIn .25s ease;
}
@keyframes slideIn{from{transform:translateX(70px);opacity:0}to{transform:translateX(0);opacity:1}}

.adv-top{
  padding:24px 30px 18px;
  border-bottom:1px solid #e8ecf0;
  background:linear-gradient(180deg,#ffffff,#f8fafc);
  flex-shrink:0;
}
.adv-title-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.adv-title{font-size:16px;font-weight:800;color:#0f172a}
.adv-close{
  width:34px;
  height:34px;
  border-radius:50%;
  border:1px solid #cbd5e1;
  background:#fff;
  color:#64748b;
  cursor:pointer;
}
.adv-close:hover{background:#f1f5f9}

.adv-search-wrap{position:relative;margin-bottom:18px}
.adv-search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px}
.adv-search-input{width:100%;padding:10px 12px 10px 34px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;color:#1e2d3d;outline:none;box-sizing:border-box;background:#fff;transition:border-color .15s,box-shadow .15s}
.adv-search-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}

.adv-filter-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px 20px;margin-bottom:14px}
.adv-field label{display:block;font-size:12px;color:#64748b;margin-bottom:6px;font-weight:600}
.adv-filter-select{width:100%;padding:8px 28px 8px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#374151;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 8px center;outline:none;box-sizing:border-box;appearance:none;cursor:pointer;transition:border-color .15s,box-shadow .15s}
.adv-filter-select:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.10)}
.adv-filter-select.active{background-color:#eff6ff;border-color:#2563eb;color:#2563eb;font-weight:600}

.adv-bottom-bar{display:flex;align-items:center;justify-content:space-between;margin-top:6px}
.adv-per-page{display:flex;align-items:center;gap:8px;font-size:13px;color:#374151}
.adv-per-page select{padding:6px 24px 6px 8px;border:1px solid #e2e8f0;border-radius:7px;font-size:13px;color:#374151;background:#fff;outline:none;cursor:pointer}
.btn-adv-search{padding:10px 30px;background:#2563eb;border:none;border-radius:8px;font-size:13.5px;color:#fff;cursor:pointer;font-weight:700;transition:background .14s}
.btn-adv-search:hover{background:#1d4ed8}

.adv-results-area{flex:1;overflow-y:auto;padding:18px 28px;background:#fff}
.adv-results-table{width:100%;border-collapse:collapse;font-size:13px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden}
.adv-results-table th{padding:10px 12px;text-align:left;font-weight:700;color:#475569;background:#f8fafc;border-bottom:1px solid #e5e7eb}
.adv-results-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;color:#334155}
.adv-results-table tr:hover{background:#f8fafc}

.adv-footer{border-top:1px solid #e8ecf0;padding:16px 28px;display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-shrink:0;background:#f8fafc}
.adv-sel-section{flex:1}
.adv-sel-count{font-size:13.5px;font-weight:700;color:#374151;margin-bottom:10px}
.adv-sel-emp{display:flex;align-items:center;gap:9px;font-size:13px;color:#374151;padding:4px 0}
.adv-sel-emp input[type=checkbox]{width:15px;height:15px;accent-color:#2563eb}

.adv-history{width:300px;flex-shrink:0}
.adv-history-tabs{display:flex;border-bottom:1px solid #e8ecf0;margin-bottom:10px}
.adv-hist-tab{padding:8px 16px;font-size:13px;color:#6b7280;border-bottom:2px solid transparent;cursor:pointer;background:none;border-top:none;border-left:none;border-right:none;font-weight:500;transition:color .15s}
.adv-hist-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:700}
.adv-hist-item{display:flex;align-items:center;justify-content:space-between;padding:8px 4px;font-size:12.5px;color:#6b7280;border-bottom:1px solid #e5e7eb;cursor:pointer}
.adv-hist-item:hover{color:#374151}
.adv-hist-drag{color:#cbd5e1;margin-right:8px;cursor:grab}
.btn-hist-del{background:#fff;border:1px solid #e2e8f0;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#9ca3af;font-size:11px}
.btn-hist-del:hover{border-color:#ef4444;color:#ef4444}

.toast-container{position:fixed;top:20px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;background:#fff;border-radius:8px;padding:13px 18px;box-shadow:0 4px 18px rgba(0,0,0,.14);font-size:13.5px;font-weight:500;min-width:260px;pointer-events:all;animation:toastIn .25s ease;border-left:4px solid #2563eb;color:#1e2d3d}
.toast.success{border-color:#22c55e}
.toast.error{border-color:#ef4444}
.toast.warning{border-color:#f59e0b}
.toast i{font-size:16px}
.toast.success i{color:#22c55e}
.toast.error i{color:#ef4444}
.toast.warning i{color:#f59e0b}
.toast-close{margin-left:auto;cursor:pointer;color:#9ca3af;font-size:14px;background:none;border:none;padding:0;line-height:1}
@keyframes toastIn{from{transform:translateX(40px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes toastOut{from{opacity:1}to{opacity:0;transform:translateX(40px)}}

@media(max-width:900px){
  .modal-overlay{justify-content:center;padding:14px}
  .adv-modal{height:90vh;max-width:100%}
  .adv-filter-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:600px){
  .adv-filter-grid,.ads-form-row{grid-template-columns:1fr}
}
</style>

<div class="toast-container" id="toastContainer"></div>

<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
<div class="ads-wrapper">

  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll',
                    'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= e($k) ?>" class="cfg-tab <?= $k==='Attendance'?'active':'' ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="ads-inner">

    <nav class="ads-breadcrumb">
      <a href="configuration#Attendance">Attendance</a>
      <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
      <span>Assign Day Status</span>
    </nav>

    <form method="POST" id="adsForm">
      <input type="hidden" name="action" value="assign_day_status">

      <div class="ads-filter-bar">
        <div class="ads-search-wrap">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="empSearch" class="ads-search-input"
                 list="employeeList"
                 placeholder="Search by employee name or #code"
                 oninput="selectEmployeeFromSearch(this.value)">

          <datalist id="employeeList">
            <?php foreach ($all_employees as $emp): ?>
              <option value="<?= e($emp['emp_name']) ?> - #<?= e($emp['emp_code']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <button type="button" class="btn-filter" onclick="openAdvModal()">
          <i class="fa-solid fa-filter"></i> Filter
        </button>
      </div>

      <div class="ads-sel-header" id="selHeader" onclick="toggleEmpBox()" style="display:none">
        <span class="ads-sel-label" id="selLabel">Selected Employees - 0</span>
        <i class="fa-solid fa-chevron-down ads-sel-chevron" id="selChevron"></i>
      </div>

      <div class="ads-emp-box" id="empBox"></div>

      <div class="ads-form-row">
        <div class="ads-field">
          <label><span class="req">*</span> Shift Date</label>
          <div class="ads-date-wrap">
            <input type="date" name="shift_date"
                   value="<?= e($_POST['shift_date'] ?? $today) ?>" required>
            <i class="fa-regular fa-calendar"></i>
          </div>
        </div>

        <div class="ads-field">
          <label><span class="req">*</span> Day Status</label>
          <select name="day_status" class="ads-select" required>
            <option value="">Select</option>
            <?php foreach ($day_status_options as $opt): ?>
            <option value="<?= e($opt) ?>"
              <?= (($_POST['day_status'] ?? '') === $opt) ? 'selected' : '' ?>>
              <?= e($opt) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="ads-actions">
        <button type="submit" class="btn-assign">Assign</button>
      </div>
    </form>
  </div>
</div>
</div>

<div class="modal-overlay" id="advModal">
  <div class="adv-modal">

    <div class="adv-top">
      <div class="adv-title-bar">
        <span class="adv-title">Advance Employee Search</span>
        <button type="button" class="adv-close" onclick="closeAdvModal()">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="adv-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="adv-search-input" id="advSearchInput"
               list="employeeList"
               placeholder="Search by employee name or #code"
               oninput="advLiveSearch(this.value)">
      </div>

      <div class="adv-filter-grid">
        <div class="adv-field">
          <label>Organization</label>
          <select class="adv-filter-select" id="f-org" onchange="updateLabel(this)">
            <option>Organization - 0</option>
            <option>Ramkrishna IVF Centre</option>
          </select>
        </div>

        <div class="adv-field">
          <label>Locations</label>
          <select class="adv-filter-select" id="f-loc" onchange="updateLabel(this)">
            <option>Locations - 0</option>
            <option>COOCHBEHAR</option>
            <option>MALDA</option>
            <option>RAIGANJ</option>
            <option>SILIGURI</option>
          </select>
        </div>

        <div class="adv-field">
          <label>Department</label>
          <select class="adv-filter-select" id="f-dept" onchange="updateLabel(this)">
            <option value="">Department - 0</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?= e($dept) ?>"><?= e($dept) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="adv-field">
          <label>Designation</label>
          <select class="adv-filter-select" id="f-desig" onchange="updateLabel(this)">
            <option value="">Designation - 0</option>
            <?php foreach ($designations as $desig): ?>
              <option value="<?= e($desig) ?>"><?= e($desig) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="adv-field">
          <label>Status</label>
          <select class="adv-filter-select active" id="f-status" onchange="updateLabel(this)">
            <option value="active" selected>Status - 1</option>
            <option value="">All Status</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="adv-field">
          <label>Group</label>
          <select class="adv-filter-select"><option>Group - 0</option></select>
        </div>

        <div class="adv-field">
          <label>Sub Group</label>
          <select class="adv-filter-select"><option>Sub Group - 0</option></select>
        </div>

        <div class="adv-field">
          <label>Category</label>
          <select class="adv-filter-select">
            <option>Category - 0</option>
            <option>REGULAR</option>
          </select>
        </div>

        <div class="adv-field">
          <label>Grade</label>
          <select class="adv-filter-select"><option>Grade - 0</option></select>
        </div>

        <div class="adv-field">
          <label>Additional Field</label>
          <select class="adv-filter-select"><option>Additional Field - 0</option></select>
        </div>

        <div class="adv-field">
          <label>Field Value</label>
          <select class="adv-filter-select"><option>Field Value - 0</option></select>
        </div>
      </div>

      <div class="adv-bottom-bar">
        <div class="adv-per-page">
          Records per page :
          <select id="recordsPerPage">
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>

        <button type="button" class="btn-adv-search" onclick="advSearch()">Search</button>
      </div>
    </div>

    <div class="adv-results-area" id="advResults">
      <div style="color:#9ca3af;font-size:13px;text-align:center;padding:20px">
        Search by employee name or code.
      </div>
    </div>

    <div class="adv-footer">
      <div class="adv-sel-section">
        <div class="adv-sel-count" id="advSelCount">Selected Employees - 0</div>
        <div id="advSelList"></div>
      </div>

      <div class="adv-history">
        <div class="adv-history-tabs">
          <button type="button" class="adv-hist-tab active" onclick="switchHistTab(this,'recent')">Recent Search</button>
          <button type="button" class="adv-hist-tab" onclick="switchHistTab(this,'saved')">Saved Search</button>
        </div>

        <div id="hist-recent">
          <div class="adv-hist-item">
            <span class="adv-hist-drag"><i class="fa-solid fa-grip-dots-vertical"></i></span>
            <span style="flex:1"><?= date('d-m H:i') ?></span>
            <button type="button" class="btn-hist-del"><i class="fa-solid fa-minus"></i></button>
          </div>
        </div>

        <div id="hist-saved" style="display:none">
          <div style="color:#9ca3af;font-size:13px;padding:12px 0">No saved searches.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($flash): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
  showToast(<?= json_encode($flash) ?>, <?= json_encode($flash_type) ?>);
});
</script>
<?php endif; ?>

<script>
const ALL_EMPLOYEES = <?= json_encode(array_values($all_employees), JSON_UNESCAPED_UNICODE) ?>;
let selectedIds = new Set(<?= json_encode($selected_ids_post) ?>);

function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, function(m) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
  });
}

function selectEmployeeFromSearch(value) {
  value = String(value || '').trim().toLowerCase();
  if (!value) return;

  const emp = ALL_EMPLOYEES.find(e => {
    const full = `${e.emp_name} - #${e.emp_code}`.toLowerCase();
    return full === value ||
           String(e.emp_name || '').toLowerCase() === value ||
           String(e.emp_code || '').toLowerCase() === value.replace('#','');
  });

  if (emp) {
    selectedIds.add(Number(emp.id));
    renderSelected();
    document.getElementById('empBox').classList.add('show');
    document.getElementById('selChevron').classList.add('open');
    document.getElementById('empSearch').value = '';
    showToast(emp.emp_name + ' selected.', 'success');
  }
}

function renderSelected() {
  const header = document.getElementById('selHeader');
  const label  = document.getElementById('selLabel');
  const box    = document.getElementById('empBox');
  const count  = selectedIds.size;

  if (count === 0) {
    header.style.display = 'none';
    box.classList.remove('show');
    box.innerHTML = '';
    renderAdvSelList();
    return;
  }

  header.style.display = 'flex';
  label.textContent = 'Selected Employees - ' + count;

  box.innerHTML = [...selectedIds].map(id => {
    const emp = ALL_EMPLOYEES.find(e => Number(e.id) === Number(id));
    if (!emp) return '';
    return `<div class="ads-emp-item">
      <input type="checkbox" name="emp_ids[]" value="${Number(emp.id)}" checked
             onchange="toggleEmp(${Number(emp.id)}, this.checked)">
      ${escapeHtml(emp.emp_name)} - ${escapeHtml(emp.emp_code)}
    </div>`;
  }).join('');

  renderAdvSelList();
}

function toggleEmp(id, checked) {
  id = Number(id);
  if (checked) selectedIds.add(id);
  else selectedIds.delete(id);
  renderSelected();
  syncAdvCheckboxes();
}

function toggleEmpBox() {
  const box = document.getElementById('empBox');
  const chev = document.getElementById('selChevron');
  box.classList.toggle('show');
  chev.classList.toggle('open');
}

renderSelected();

function openAdvModal() {
  document.getElementById('advModal').classList.add('show');
  renderAdvSelList();
}

function closeAdvModal() {
  document.getElementById('advModal').classList.remove('show');
  renderSelected();
}

function advSearch() {
  const q = document.getElementById('advSearchInput').value.trim().toLowerCase();
  const dept = document.getElementById('f-dept').value.trim().toLowerCase();
  const desig = document.getElementById('f-desig').value.trim().toLowerCase();
  const status = document.getElementById('f-status').value.trim().toLowerCase();
  const limit = parseInt(document.getElementById('recordsPerPage').value || '25', 10);

  let filtered = ALL_EMPLOYEES.filter(e => {
    const empName = String(e.emp_name || '').toLowerCase();
    const empCode = String(e.emp_code || '').toLowerCase();
    const empDept = String(e.department || '').toLowerCase();
    const empDesig = String(e.designation || '').toLowerCase();
    const empStatus = String(e.status || '').toLowerCase();

    if (q && !empName.includes(q) && !empCode.includes(q.replace('#',''))) return false;
    if (dept && empDept !== dept) return false;
    if (desig && empDesig !== desig) return false;
    if (status && empStatus !== status) return false;

    return true;
  }).slice(0, limit);

  renderAdvResults(filtered);
  showToast(filtered.length + ' employee(s) found.', filtered.length ? 'success' : 'warning');
}

function advLiveSearch(q) {
  q = String(q || '').trim().toLowerCase();

  if (!q) {
    document.getElementById('advResults').innerHTML =
      '<div style="color:#9ca3af;font-size:13px;text-align:center;padding:20px">Search by employee name or code.</div>';
    return;
  }

  const filtered = ALL_EMPLOYEES.filter(e =>
    String(e.emp_name || '').toLowerCase().includes(q) ||
    String(e.emp_code || '').toLowerCase().includes(q.replace('#',''))
  );

  renderAdvResults(filtered);
}

function renderAdvResults(list) {
  const results = document.getElementById('advResults');

  if (!list.length) {
    results.innerHTML = '<div style="color:#9ca3af;font-size:13px;text-align:center;padding:20px">No employees found.</div>';
    return;
  }

  results.innerHTML = `<table class="adv-results-table">
    <thead>
      <tr>
        <th style="width:40px">
          <input type="checkbox" onchange="toggleAllAdv(this)" style="accent-color:#2563eb;width:15px;height:15px">
        </th>
        <th>#Code</th>
        <th>Employee Name</th>
        <th>Department</th>
        <th>Designation</th>
      </tr>
    </thead>
    <tbody>
      ${list.map(e => `<tr>
        <td>
          <input type="checkbox" value="${Number(e.id)}"
            style="accent-color:#2563eb;width:15px;height:15px"
            ${selectedIds.has(Number(e.id)) ? 'checked' : ''}
            onchange="toggleEmp(${Number(e.id)}, this.checked)">
        </td>
        <td>${escapeHtml(e.emp_code)}</td>
        <td>${escapeHtml(e.emp_name)}</td>
        <td>${escapeHtml(e.department)}</td>
        <td>${escapeHtml(e.designation)}</td>
      </tr>`).join('')}
    </tbody>
  </table>`;

  renderAdvSelList();
}

function toggleAllAdv(master) {
  document.querySelectorAll('#advResults input[type=checkbox][value]').forEach(cb => {
    cb.checked = master.checked;
    toggleEmp(Number(cb.value), master.checked);
  });
}

function syncAdvCheckboxes() {
  document.querySelectorAll('#advResults input[type=checkbox][value]').forEach(cb => {
    cb.checked = selectedIds.has(Number(cb.value));
  });
}

function renderAdvSelList() {
  const countEl = document.getElementById('advSelCount');
  const listEl = document.getElementById('advSelList');

  if (!countEl || !listEl) return;

  countEl.textContent = 'Selected Employees - ' + selectedIds.size;

  listEl.innerHTML = [...selectedIds].map(id => {
    const emp = ALL_EMPLOYEES.find(e => Number(e.id) === Number(id));
    if (!emp) return '';
    return `<div class="adv-sel-emp">
      <input type="checkbox" checked onchange="toggleEmp(${Number(emp.id)}, this.checked)">
      ${escapeHtml(emp.emp_name)} - ${escapeHtml(emp.emp_code)}
    </div>`;
  }).join('');
}

function switchHistTab(btn, tab) {
  document.querySelectorAll('.adv-hist-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('hist-recent').style.display = tab === 'recent' ? 'block' : 'none';
  document.getElementById('hist-saved').style.display  = tab === 'saved'  ? 'block' : 'none';
}

function updateLabel(sel) {
  if (sel.selectedIndex > 0) sel.classList.add('active');
  else sel.classList.remove('active');
}

const toastIcons = {success:'fa-circle-check',error:'fa-circle-xmark',warning:'fa-triangle-exclamation',info:'fa-circle-info'};

function showToast(msg, type='success', dur=3500) {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fa-solid ${toastIcons[type]||toastIcons.info}"></i>
    <span>${msg}</span>
    <button class="toast-close" onclick="rmToast(this.parentElement)"><i class="fa-solid fa-xmark"></i></button>`;
  c.appendChild(t);
  setTimeout(() => rmToast(t), dur);
}

function rmToast(el) {
  if (!el?.parentElement) return;
  el.style.animation = 'toastOut .25s ease forwards';
  setTimeout(() => el.remove(), 260);
}

document.getElementById('adsForm').addEventListener('submit', function(e) {
  if (selectedIds.size === 0) {
    e.preventDefault();
    showToast('Please select at least one employee.', 'error');
    return;
  }

  document.querySelectorAll('input[name="emp_ids[]"]').forEach(i => i.remove());

  selectedIds.forEach(id => {
    const inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = 'emp_ids[]';
    inp.value = id;
    document.getElementById('adsForm').appendChild(inp);
  });
});
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>