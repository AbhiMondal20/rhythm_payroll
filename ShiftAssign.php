<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Assign Shift';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

function e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ════════════════════════════════════════════
   CREATE DB TABLES
════════════════════════════════════════════ */
$conn->query("
CREATE TABLE IF NOT EXISTS `att_shifts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shift_name` VARCHAR(100) NOT NULL,
  `shift_code` VARCHAR(30) NULL,
  `shift_start` TIME NULL,
  `shift_end` TIME NULL,
  `duration` VARCHAR(20) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS `att_shift_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `emp_id` INT NOT NULL,
  `shift_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `weekdays` VARCHAR(100) NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_emp_shift_date` (`emp_id`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ════════════════════════════════════════════
   DUMMY SHIFT DATA
════════════════════════════════════════════ */
$shiftCount = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM att_shifts");
if ($res) {
    $shiftCount = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($shiftCount === 0) {
    $dummyShifts = [
        ['General 9AM', 'G9AM', '09:00:00', '17:00:00', '8h'],
        ['General 9:30AM', 'G930AM', '09:30:00', '17:30:00', '8h'],
        ['General 10 AM', 'G10AM', '10:00:00', '18:00:00', '8h'],
        ['General 10:30 AM', 'G1030AM', '10:30:00', '18:30:00', '8h'],
        ['General 11:00 AM', 'G11AM', '11:00:00', '19:00:00', '8h'],
        ['General 11:30 AM', 'G1130AM', '11:30:00', '19:30:00', '8h'],
        ['Night shift', 'NIGHT', '21:00:00', '06:00:00', '9h'],
        ['Weekoff & Holiday Worked', 'WHW', '09:00:00', '17:00:00', '8h'],
    ];

    $stmt = $conn->prepare("
        INSERT INTO att_shifts
        (shift_name, shift_code, shift_start, shift_end, duration, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");

    if ($stmt) {
        foreach ($dummyShifts as $sh) {
            $stmt->bind_param("sssss", $sh[0], $sh[1], $sh[2], $sh[3], $sh[4]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

/* ════════════════════════════════════════════
   DUMMY EMPLOYEE DATA IF EMPTY
════════════════════════════════════════════ */
$empCount = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM employees");
if ($res) {
    $empCount = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($empCount === 0) {
    // $dummyEmployees = [
    //     ['1001','Abhijit Kumar Mondal','Human Resource','HR Manager'],
    //     ['1002','Priya Sharma','Finance','Accountant'],
    //     ['1003','Rajesh Dey','Information Technology','IT Executive'],
    //     ['1004','Sunita Pal','Administration','Admin Officer'],
    //     ['1005','Mohan Das','LAB','Lab Technician'],
    //     ['1006','Anita Roy','Accounts','Accounts Executive'],
    //     ['1007','Suresh Ghosh','Management','Manager'],
    // ];

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
$toast_msg  = '';
$toast_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_shift') {
    $emp_ids    = $_POST['emp_ids'] ?? [];
    $shift_id   = (int)($_POST['shift_id'] ?? 0);
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date'] ?? '');
    $weekdays   = implode(',', $_POST['weekdays'] ?? []);

    if (empty($emp_ids)) {
        $toast_msg  = 'Please select at least one employee.';
        $toast_type = 'error';
    } elseif (!$shift_id || !$start_date || !$end_date) {
        $toast_msg  = 'Start Date, End Date and Shift are required.';
        $toast_type = 'error';
    } elseif ($end_date < $start_date) {
        $toast_msg  = 'End Date must be on or after Start Date.';
        $toast_type = 'warning';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO att_shift_assignments
            (emp_id, shift_id, start_date, end_date, weekdays, assigned_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                shift_id = VALUES(shift_id),
                weekdays = VALUES(weekdays),
                updated_at = NOW()
        ");

        if (!$stmt) {
            $toast_msg  = 'Save failed: ' . $conn->error;
            $toast_type = 'error';
        } else {
            $saved = 0;

            foreach ($emp_ids as $eid) {
                $eid = (int)$eid;
                if ($eid <= 0) continue;

                $stmt->bind_param("iisss", $eid, $shift_id, $start_date, $end_date, $weekdays);

                if ($stmt->execute()) {
                    $saved++;
                }
            }

            $stmt->close();

            if ($saved > 0) {
                $toast_msg  = 'Shift assigned successfully to ' . $saved . ' employee(s).';
                $toast_type = 'success';
            } else {
                $toast_msg  = 'No employee assignment saved.';
                $toast_type = 'error';
            }
        }
    }
}

/* ════════ FETCH SHIFTS FROM DB ════════ */
$shifts = [];
$res = $conn->query("
    SELECT id, shift_name, shift_code
    FROM att_shifts
    WHERE status='active'
    ORDER BY shift_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $shifts[] = $row;
    }
}

/* ════════ FETCH EMPLOYEES FROM DB ════════ */
$q = strtolower(trim($_GET['q'] ?? ''));

$employees = [];
$params = [];
$types = '';

$sql = "
    SELECT 
        id,
        employee_code AS emp_code,
        employee_name AS emp_name,
        department,
        designation,
        grade,
        status
    FROM employees
    WHERE 1=1
";

if ($q !== '') {
    $sql .= " AND (LOWER(employee_name) LIKE ? OR LOWER(employee_code) LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$sql .= " ORDER BY employee_name ASC LIMIT 100";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $employees[] = $row;
    }

    $stmt->close();
}

$all_employees = [];
$resAll = $conn->query("
    SELECT 
        id,
        employee_code AS emp_code,
        employee_name AS emp_name,
        department,
        designation,
        grade,
        status
    FROM employees
    ORDER BY employee_name ASC
    LIMIT 500
");
if ($resAll) {
    while ($row = $resAll->fetch_assoc()) {
        $all_employees[] = $row;
    }
}

$departments = [];
$resDept = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department!='' ORDER BY department ASC");
if ($resDept) {
    while ($d = $resDept->fetch_assoc()) {
        $departments[] = $d['department'];
    }
}

$designations = [];
$resDes = $conn->query("SELECT DISTINCT designation FROM employees WHERE designation IS NOT NULL AND designation!='' ORDER BY designation ASC");
if ($resDes) {
    while ($d = $resDes->fetch_assoc()) {
        $designations[] = $d['designation'];
    }
}

$grades = [];
$resGrade = $conn->query("SELECT DISTINCT grade FROM employees WHERE grade IS NOT NULL AND grade!='' ORDER BY grade ASC");
if ($resGrade) {
    while ($g = $resGrade->fetch_assoc()) {
        $grades[] = $g['grade'];
    }
}

$today       = date('Y-m-d');
$default_end = date('Y-m-d', strtotime('+30 days'));
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ── Config nav tabs ── */
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}

.cfg-page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
    flex-wrap: wrap;
    gap: 10px;
}

.cfg-page-head h1 {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
}

/* ── Page ── */
.as-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.as-inner{padding:20px 28px}

/* breadcrumb */
.as-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.as-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555}
.as-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.as-breadcrumb a:hover{text-decoration:underline}
.as-breadcrumb .sep{color:#bbb;font-size:11px}
.link-bulk{font-size:13.5px;color:#2563eb;font-weight:600;text-decoration:none;cursor:pointer;background:none;border:none}
.link-bulk:hover{text-decoration:underline}

/* ── Card ── */
.as-card{background:#fff;border:1px solid #e8ecf0;border-radius:10px;padding:24px 28px;margin-bottom:20px}

/* ── Search + filter bar ── */
.as-filter-bar{display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap}
.as-search-wrap{position:relative;width:280px}
.as-search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:12px}
.as-search-input{width:100%;padding:8px 10px 8px 32px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;color:#1e2d3d;outline:none;box-sizing:border-box;background:#f9fafb;transition:border-color .15s}
.as-search-input:focus{border-color:#2563eb;background:#fff}
.btn-filter{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;font-size:13px;color:#374151;cursor:pointer;font-weight:500;transition:background .14s}
.btn-filter:hover{background:#f1f5f9}

/* Advance options toggle */
.as-adv-wrap{margin-left:auto;position:relative}
.btn-advance{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#374151;background:none;border:none;cursor:pointer;font-weight:500;padding:8px 4px}
.btn-advance i{transition:transform .2s}
.btn-advance.open i{transform:rotate(180deg)}
.adv-dropdown{display:none;position:absolute;right:0;top:36px;background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:14px 18px;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:100;min-width:160px}
.adv-dropdown.show{display:block}
.adv-day{display:flex;align-items:center;gap:9px;font-size:13.5px;color:#374151;padding:5px 0;cursor:pointer}
.adv-day input[type=checkbox]{width:15px;height:15px;accent-color:#2563eb;cursor:pointer}

/* ── Form row ── */
.as-form-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:22px 28px;margin-bottom:10px;align-items:end}
.as-field label{display:block;font-size:12.5px;color:#374151;margin-bottom:7px;font-weight:400}
.as-field label .req{color:#ef4444;margin-right:2px}
.as-date-wrap{position:relative}
.as-date-wrap input[type=date]{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 28px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s;cursor:pointer}
.as-date-wrap input[type=date]:focus{border-color:#2563eb}
.as-date-wrap i{position:absolute;right:4px;top:50%;transform:translateY(-50%);color:#2563eb;font-size:14px;pointer-events:none}
.as-select{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 24px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s;appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 4px center}
.as-select:focus{border-color:#2563eb}

/* ── Employees table ── */
.as-emp-table-wrap{border:1px solid #e8ecf0;border-radius:8px;overflow:hidden;margin-bottom:22px;display:none}
.as-emp-table-wrap.show{display:block}
table.as-emp-table{width:100%;border-collapse:collapse}
table.as-emp-table thead th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid #e8ecf0}
table.as-emp-table tbody tr{border-bottom:1px solid #f1f4f8;transition:background .12s}
table.as-emp-table tbody tr:last-child{border-bottom:none}
table.as-emp-table tbody tr:hover{background:#f9fafb}
table.as-emp-table tbody td{padding:10px 14px;font-size:13px;color:#374151}
.as-emp-table .cb-col{width:36px;text-align:center}
.as-emp-table input[type=checkbox]{accent-color:#2563eb;width:15px;height:15px;cursor:pointer}

/* ── Actions ── */
.as-form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:28px;padding-top:18px;border-top:1px solid #e8ecf0}
.btn-cancel{padding:9px 26px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:13.5px;color:#374151;cursor:pointer;font-weight:600;transition:background .14s}
.btn-cancel:hover{background:#f1f5f9}
.btn-assign{padding:9px 26px;background:#2563eb;border:none;border-radius:6px;font-size:13.5px;color:#fff;cursor:pointer;font-weight:600;transition:background .14s}
.btn-assign:hover{background:#1d4ed8}

/* ── Toast ── */
.toast-container{position:fixed;top:20px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;background:#fff;border-radius:8px;padding:13px 18px;box-shadow:0 4px 18px rgba(0,0,0,.14);font-size:13.5px;font-weight:500;min-width:260px;pointer-events:all;animation:toastIn .25s ease;border-left:4px solid #2563eb;color:#1e2d3d}
.toast.success{border-color:#22c55e}
.toast.error{border-color:#ef4444}
.toast.warning{border-color:#f59e0b}
.toast i{font-size:16px}
.toast.success i{color:#22c55e}
.toast.error   i{color:#ef4444}
.toast.warning i{color:#f59e0b}
.toast-close{margin-left:auto;cursor:pointer;color:#9ca3af;font-size:14px;background:none;border:none;padding:0;line-height:1}
.toast-close:hover{color:#374151}
@keyframes toastIn{from{transform:translateX(40px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes toastOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(40px)}}

/* ── Employee Filter Modal ── */
.emp-filter-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9998}
.emp-filter-overlay.show{display:block}
.emp-filter-modal{position:fixed;top:24px;left:50%;transform:translateX(-50%);width:calc(100% - 56px);max-width:1040px;max-height:calc(100vh - 48px);background:#fff;z-index:9999;border-radius:2px;box-shadow:0 12px 40px rgba(0,0,0,.25);display:none;overflow:hidden}
.emp-filter-modal.show{display:block}
.ef-body{padding:30px 38px 0;max-height:calc(100vh - 170px);overflow-y:auto}
.ef-title{font-size:14px;font-weight:700;color:#001f3f;margin-bottom:28px}
.ef-close{position:absolute;right:18px;top:18px;width:30px;height:30px;border-radius:50%;border:1px solid #cbd5e1;background:#fff;color:#777;font-size:18px;cursor:pointer}
.ef-search{position:relative;margin-bottom:18px}
.ef-search i{position:absolute;left:20px;top:50%;transform:translateY(-50%);color:#9ca3af}
.ef-search input{width:270px;border:none;outline:none;padding:8px 10px 8px 40px;font-size:13px;color:#1e2d3d}
.ef-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px 8px}
.ef-field label{display:block;font-size:13px;color:#001f3f;margin-bottom:10px}
.ef-field select{width:100%;height:36px;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;padding:0 10px;font-size:13px;color:#001f3f;outline:none}
.ef-record-row{display:flex;align-items:center;gap:8px;margin:12px 0 28px;font-size:13px;color:#001f3f}
.ef-record-row select{border:none;background:transparent;font-size:13px;outline:none}
.ef-search-btn{float:right;margin-top:-58px;width:90px;height:36px;border:none;border-radius:4px;background:#0475ff;color:#fff;font-size:13px;cursor:pointer}
.ef-line{border-top:1px solid #777;margin:30px -38px 0}
.ef-lower{display:grid;grid-template-columns:1fr 260px;gap:20px;min-height:210px;padding:34px 0}
.ef-all{display:flex;align-items:center;gap:8px;font-size:13px;color:#001f3f}
.ef-all input{accent-color:#0d6efd}
.ef-recent-tabs{display:flex;border:1px solid #9ca3af;border-radius:4px;overflow:hidden;width:212px;margin-left:auto}
.ef-recent-tabs button{flex:1;border:none;background:#fff;height:24px;font-size:11px;color:#666;cursor:pointer}
.ef-recent-tabs button.active{color:#0475ff;border-right:1px solid #0475ff}
.ef-recent-list{margin-top:8px}
.ef-recent-item{display:flex;align-items:center;justify-content:space-between;border-top:1px solid #ddd;padding:8px 0;font-size:13px;color:#001f3f}
.ef-dots{color:#cbd5e1;letter-spacing:2px;font-weight:bold}
.ef-minus{width:16px;height:16px;border:1px solid #111827;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px}
.ef-footer{border-top:1px solid #777;padding:10px 20px;display:flex;justify-content:flex-end;gap:12px;background:#fff}
.ef-footer button{min-width:90px;height:36px;border-radius:4px;font-size:13px;cursor:pointer}
.ef-clear,.ef-save{background:#fff;border:1px solid #111827;color:#111827}
.ef-apply{background:#0475ff;border:1px solid #0475ff;color:#fff}
@media(max-width:900px){.ef-grid{grid-template-columns:1fr 1fr}.ef-lower{grid-template-columns:1fr}}
</style>

<div class="toast-container" id="toastContainer"></div>


<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>


<div class="section-card" style="padding:0;overflow:hidden">
<div class="as-wrapper">

  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll',
                    'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= e($k) ?>" class="cfg-tab <?= $k==='Attendance'?'active':'' ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="as-inner">

    <div class="as-topbar">
      <nav class="as-breadcrumb">
        <a href="configuration#Attendance">Attendance</a>
        <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span>Assign Shift</span>
      </nav>
      <button type="button" class="link-bulk" onclick="showToast('Bulk assignment coming soon.','warning')">
        Assign Shift in Bulk
      </button>
    </div>

    <div class="as-card">
      <form method="POST" id="assignForm">
        <input type="hidden" name="action" value="assign_shift">

        <div class="as-filter-bar">
          <div class="as-search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="empSearch" class="as-search-input"
                   placeholder="Search by name or #code"
                   value="<?= e($_GET['q'] ?? '') ?>"
                   oninput="filterEmployees(this.value)">
          </div>

          <button type="button" class="btn-filter" onclick="openEmpFilterModal()">
            <i class="fa-solid fa-filter"></i> Filter
          </button>

          <div class="as-adv-wrap">
            <button type="button" class="btn-advance" id="advBtn" onclick="toggleAdvance()">
              Advance options <i class="fa-solid fa-chevron-down"></i>
            </button>

            <div class="adv-dropdown" id="advDropdown">
              <?php foreach ($days as $day): ?>
              <label class="adv-day">
                <input type="checkbox" name="weekdays[]" value="<?= e($day) ?>"> <?= e($day) ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="as-emp-table-wrap <?= !empty($employees) && $q ? 'show' : '' ?>" id="empTableWrap">
          <table class="as-emp-table">
            <thead>
              <tr>
                <th class="cb-col"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                <th>#Code</th>
                <th>Employee Name</th>
                <th>Department</th>
                <th>Designation</th>
              </tr>
            </thead>
            <tbody id="empTableBody">
              <?php foreach ($employees as $emp): ?>
              <tr>
                <td class="cb-col">
                  <input type="checkbox" name="emp_ids[]" value="<?= (int)$emp['id'] ?>" class="emp-cb">
                </td>
                <td><?= e($emp['emp_code']) ?></td>
                <td><?= e($emp['emp_name']) ?></td>
                <td><?= e($emp['department']) ?></td>
                <td><?= e($emp['designation']) ?></td>
              </tr>
              <?php endforeach; ?>

              <?php if (empty($employees)): ?>
              <tr>
                <td colspan="5" style="text-align:center;padding:20px;color:#9ca3af">
                    No employees found.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="as-form-row">
          <div class="as-field">
            <label><span class="req">*</span> Start Date</label>
            <div class="as-date-wrap">
              <input type="date" name="start_date"
                     value="<?= e($_POST['start_date'] ?? $today) ?>" required>
              <i class="fa-regular fa-calendar"></i>
            </div>
          </div>

          <div class="as-field">
            <label><span class="req">*</span> End Date</label>
            <div class="as-date-wrap">
              <input type="date" name="end_date"
                     value="<?= e($_POST['end_date'] ?? $default_end) ?>" required>
              <i class="fa-regular fa-calendar"></i>
            </div>
          </div>

          <div class="as-field">
            <label><span class="req">*</span> Shift</label>
            <select name="shift_id" class="as-select" required>
              <option value=""></option>
              <?php foreach ($shifts as $sh): ?>
                <option value="<?= (int)$sh['id'] ?>"
                  <?= (((int)($_POST['shift_id'] ?? 0)) === (int)$sh['id']) ? 'selected' : '' ?>>
                  <?= e($sh['shift_name']) ?> (<?= e($sh['shift_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="as-form-actions">
          <button type="button" class="btn-cancel"
                  onclick="window.location.href=window.location.pathname">Cancel</button>
          <button type="submit" class="btn-assign">Assign</button>
        </div>
      </form>
    </div>

  </div>
</div>
</div>

<!-- Employee Filter Modal -->
<div class="emp-filter-overlay" id="empFilterOverlay" onclick="closeEmpFilterModal()"></div>

<div class="emp-filter-modal" id="empFilterModal">
    <button type="button" class="ef-close" onclick="closeEmpFilterModal()">×</button>

    <div class="ef-body">
        <div class="ef-title">Advance Employee Search</div>

        <div class="ef-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="filterSearchText" placeholder="Search by name or #code">
        </div>

        <div class="ef-grid">
            <div class="ef-field">
                <label>Organization</label>
                <select id="filterOrg">
                    <option value="">Organization - 0</option>
                </select>
            </div>

            <div class="ef-field">
                <label>Locations</label>
                <select id="filterLocation">
                    <option value="">Locations - 0</option>
                </select>
            </div>

            <div class="ef-field">
                <label>Department</label>
                <select id="filterDepartment">
                    <option value="">Department - 0</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= e($d) ?>"><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ef-field">
                <label>Designation</label>
                <select id="filterDesignation">
                    <option value="">Designation - 0</option>
                    <?php foreach ($designations as $d): ?>
                        <option value="<?= e($d) ?>"><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ef-field">
                <label>Status</label>
                <select id="filterStatus">
                    <option value="active">Status - 1</option>
                    <option value="">All Status</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="ef-field">
                <label>Group</label>
                <select><option>Group - 0</option></select>
            </div>

            <div class="ef-field">
                <label>Sub Group</label>
                <select><option>Sub Group - 0</option></select>
            </div>

            <div class="ef-field">
                <label>Category</label>
                <select><option>Category - 0</option></select>
            </div>

            <div class="ef-field">
                <label>Grade</label>
                <select id="filterGrade">
                    <option value="">Grade - 0</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= e($g) ?>"><?= e($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ef-field">
                <label>Additional Field</label>
                <select><option>Additional Field - 0</option></select>
            </div>

            <div class="ef-field">
                <label>Field Value</label>
                <select><option>Field Value - 0</option></select>
            </div>
        </div>

        <button type="button" class="ef-search-btn" onclick="applyEmployeeAdvancedFilter()">Search</button>

        <div class="ef-record-row">
            <span>Records per page :</span>
            <select id="filterLimit">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>

        <div class="ef-line"></div>

        <div class="ef-lower">
            <label class="ef-all">
                <input type="checkbox" id="filterAllEmployees" checked>
                All Employees
            </label>

            <div>
                <div class="ef-recent-tabs">
                    <button type="button" class="active">Recent Search</button>
                    <button type="button">Saved Search</button>
                </div>

                <div class="ef-recent-list">
                    <div class="ef-recent-item">
                        <span><span class="ef-dots">⠿</span> <?= date('d-m H:i') ?></span>
                        <span class="ef-minus">−</span>
                    </div>
                    <div class="ef-recent-item">
                        <span><span class="ef-dots">⠿</span> 01-05 01:47</span>
                        <span class="ef-minus">−</span>
                    </div>
                    <div class="ef-recent-item">
                        <span><span class="ef-dots">⠿</span> 01-05-12:20</span>
                        <span class="ef-minus">−</span>
                    </div>
                    <div class="ef-recent-item">
                        <span><span class="ef-dots">⠿</span> 01-05 12:06</span>
                        <span class="ef-minus">−</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ef-footer">
        <button type="button" class="ef-clear" onclick="clearEmployeeAdvancedFilter()">Clear All</button>
        <button type="button" class="ef-save" onclick="showToast('Search saved.','success')">Save Search</button>
        <button type="button" class="ef-apply" onclick="applyEmployeeAdvancedFilter()">Apply</button>
    </div>
</div>

<?php if ($toast_msg): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
  showToast(<?= json_encode($toast_msg) ?>, <?= json_encode($toast_type) ?>);
});
</script>
<?php endif; ?>

<script>
const toastIcons = {
  success: 'fa-circle-check',
  error:   'fa-circle-xmark',
  warning: 'fa-triangle-exclamation',
  info:    'fa-circle-info'
};

function showToast(msg, type = 'success', duration = 3500) {
  const container = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fa-solid ${toastIcons[type] || toastIcons.info}"></i>
    <span>${msg}</span>
    <button class="toast-close" onclick="removeToast(this.parentElement)">
      <i class="fa-solid fa-xmark"></i>
    </button>`;
  container.appendChild(t);
  setTimeout(() => removeToast(t), duration);
}

function removeToast(el) {
  if (!el || !el.parentElement) return;
  el.style.animation = 'toastOut .25s ease forwards';
  setTimeout(() => el.remove(), 260);
}

function toggleAdvance() {
  const btn = document.getElementById('advBtn');
  const dd  = document.getElementById('advDropdown');
  btn.classList.toggle('open');
  dd.classList.toggle('show');
}

document.addEventListener('click', function(e) {
  const wrap = document.querySelector('.as-adv-wrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('advBtn').classList.remove('open');
    document.getElementById('advDropdown').classList.remove('show');
  }
});

function toggleAll(master) {
  document.querySelectorAll('.emp-cb').forEach(cb => cb.checked = master.checked);
}

const allEmployees = <?= json_encode(array_values($all_employees), JSON_UNESCAPED_UNICODE) ?>;

function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, function(m) {
    return ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    })[m];
  });
}

function filterEmployees(q) {
  q = q.trim().toLowerCase();

  const wrap = document.getElementById('empTableWrap');
  const tbody = document.getElementById('empTableBody');

  if (!q) {
    wrap.classList.remove('show');
    return;
  }

  const filtered = allEmployees.filter(e =>
    String(e.emp_name || '').toLowerCase().includes(q) ||
    String(e.emp_code || '').toLowerCase().includes(q)
  );

  renderEmployeeRows(filtered);
}

function renderEmployeeRows(rows) {
  const wrap = document.getElementById('empTableWrap');
  const tbody = document.getElementById('empTableBody');

  tbody.innerHTML = rows.length
    ? rows.map(e => `
      <tr>
        <td class="cb-col">
          <input type="checkbox" name="emp_ids[]" value="${Number(e.id)}" class="emp-cb">
        </td>
        <td>${escapeHtml(e.emp_code)}</td>
        <td>${escapeHtml(e.emp_name)}</td>
        <td>${escapeHtml(e.department)}</td>
        <td>${escapeHtml(e.designation)}</td>
      </tr>`).join('')
    : '<tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af">No employees found.</td></tr>';

  wrap.classList.add('show');

  const selectAll = document.getElementById('selectAll');
  if (selectAll) selectAll.checked = false;
}

document.getElementById('assignForm').addEventListener('submit', function(e) {
  const selected = document.querySelectorAll('.emp-cb:checked').length;
  const shift = document.querySelector('[name="shift_id"]').value;

  if (!selected) {
    e.preventDefault();
    showToast('Please select at least one employee.', 'error');
    return;
  }

  if (!shift) {
    e.preventDefault();
    showToast('Please select shift.', 'error');
    return;
  }
});

function openEmpFilterModal() {
    document.getElementById('empFilterOverlay').classList.add('show');
    document.getElementById('empFilterModal').classList.add('show');
}

function closeEmpFilterModal() {
    document.getElementById('empFilterOverlay').classList.remove('show');
    document.getElementById('empFilterModal').classList.remove('show');
}

function clearEmployeeAdvancedFilter() {
    document.getElementById('filterSearchText').value = '';
    document.getElementById('filterDepartment').value = '';
    document.getElementById('filterDesignation').value = '';
    document.getElementById('filterStatus').value = 'active';
    document.getElementById('filterGrade').value = '';
    document.getElementById('filterAllEmployees').checked = true;
    showToast('Filter cleared.', 'success');
}

function applyEmployeeAdvancedFilter() {
    const q = document.getElementById('filterSearchText').value.trim().toLowerCase();
    const dept = document.getElementById('filterDepartment').value.trim().toLowerCase();
    const desig = document.getElementById('filterDesignation').value.trim().toLowerCase();
    const grade = document.getElementById('filterGrade').value.trim().toLowerCase();
    const status = document.getElementById('filterStatus').value.trim().toLowerCase();
    const limit = parseInt(document.getElementById('filterLimit').value || '25', 10);

    let filtered = allEmployees.filter(e => {
        const empName = String(e.emp_name || '').toLowerCase();
        const empCode = String(e.emp_code || '').toLowerCase();
        const empDept = String(e.department || '').toLowerCase();
        const empDesig = String(e.designation || '').toLowerCase();
        const empGrade = String(e.grade || '').toLowerCase();
        const empStatus = String(e.status || '').toLowerCase();

        if (q && !empName.includes(q) && !empCode.includes(q)) return false;
        if (dept && empDept !== dept) return false;
        if (desig && empDesig !== desig) return false;
        if (grade && empGrade !== grade) return false;
        if (status && empStatus !== status) return false;

        return true;
    }).slice(0, limit);

    renderEmployeeRows(filtered);
    closeEmpFilterModal();
    showToast(filtered.length + ' employee(s) found.', filtered.length ? 'success' : 'warning');
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>