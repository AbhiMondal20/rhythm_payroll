<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$edit_id    = (int)($_GET['id'] ?? $_POST['emp_id'] ?? 0);
$is_edit    = $edit_id > 0 || (isset($_GET['isEditEmployee']) && $_GET['isEditEmployee'] === 'true');
$is_add     = !$is_edit;
$page_title = $is_edit ? 'Edit Employee' : 'Add Employee';

function esc($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function sel($val, $option): string {
    return (string)$val === (string)$option ? 'selected' : '';
}

function clean_post($key, $default = '') {
    return trim((string)($_POST[$key] ?? $default));
}

function date_or_null($v) {
    $v = trim((string)$v);
    return $v !== '' ? $v : null;
}

function initials_name($name) {
    $name = trim((string)$name);
    if ($name === '') return 'NA';
    $p = preg_split('/\s+/', $name);
    return strtoupper(substr($p[0] ?? '', 0, 1) . substr($p[1] ?? '', 0, 1));
}

function sql_val($conn, $val) {
    if ($val === null) {
        return 'NULL';
    }
    return "'" . mysqli_real_escape_string($conn, (string)$val) . "'";
}

// Switched from Emojis to text identifiers for the toaster
$toast_msg  = $_SESSION['toast_msg'] ?? '';
$toast_icon = $_SESSION['toast_icon'] ?? 'success';
unset($_SESSION['toast_msg'], $_SESSION['toast_icon']);

$emp = [
    'id' => '',
    'employee_code' => '',
    'name' => '',
    'title' => '',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'dob' => '',
    'gender' => '',
    'blood' => '',
    'marital' => '',
    'nationality' => 'Indian',
    'phone' => '',
    'phone2' => '',
    'email' => '',
    'off_email' => '',
    'address' => '',
    'aadhaar' => '',
    'pan' => '',
    'uan' => '',
    'esi_no' => '',
    'dept' => '',
    'desig' => '',
    'emp_type' => 'Permanent',
    'manager' => '',
    'grade' => '',
    'location' => '',
    'status' => 'Active',
    'join' => '',
    'probation' => '',
    'notice' => '',
    'confirm_date' => '',
    'contract_end' => '',
    'shift' => '',
    'qualification' => '',
    'specialisation' => '',
    'reg_no' => '',
    'salary' => '',
    'basic_pct' => 60,
    'hra_pct' => 40,
    'acc_name' => '',
    'acc_no' => '',
    'bank' => '',
    'ifsc' => '',
    'branch' => '',
    'pay_mode' => 'NEFT',
    'nom_name' => '',
    'nom_rel' => '',
    'emg_name' => '',
    'emg_rel' => '',
    'emg_phone' => '',
    'notes' => '',
    'profile_photo' => '', 
    'aadhaar_doc' => '',
    'pan_doc' => '',
    'photo_doc' => '',
    'edu_doc' => '',
    'bank_doc' => '',
    'appt_doc' => '',
];

$employees = [];
$resMgr = mysqli_query($conn, "SELECT id, employee_name, department FROM employees ORDER BY employee_name ASC");
if ($resMgr) {
    while ($r = mysqli_fetch_assoc($resMgr)) {
        $employees[] = ['id' => $r['id'], 'name' => $r['employee_name'], 'dept' => $r['department']];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'delete_employee') {
    $del_id = (int)($_POST['emp_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $del_sql = "DELETE FROM employees WHERE id = " . $del_id;
            if (!mysqli_query($conn, $del_sql)) throw new Exception(mysqli_error($conn));
            $_SESSION['toast_icon'] = 'success';
            $_SESSION['toast_msg']  = 'Employee deleted successfully.';
            header("Location: employees");
            exit;
        } catch (Exception $e) {
            $_SESSION['toast_icon'] = 'error';
            $_SESSION['toast_msg']  = 'Delete failed: ' . $e->getMessage();
            header("Location: AddEmployee?isEditEmployee=true&id=" . $del_id);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') !== 'delete_employee') {

    $required = ['first_name', 'last_name', 'dept', 'desig', 'join', 'salary'];
    $errors = [];

    foreach ($required as $field) {
        if (trim((string)($_POST[$field] ?? '')) === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['toast_icon'] = 'warning';
        $_SESSION['toast_msg'] = implode(' ', $errors);
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $emp_id = (int)($_POST['emp_id'] ?? 0);
    $first_name  = clean_post('first_name');
    $middle_name = clean_post('middle_name');
    $last_name   = clean_post('last_name');
    $full_name   = trim($first_name . ' ' . $middle_name . ' ' . $last_name);

    $employee_code = clean_post('emp_code');
    if ($employee_code === '') $employee_code = 'EMP-' . date('YmdHis');

    $status      = clean_post('status_final', clean_post('status', 'Active'));
    $list_status = strtolower($status) === 'active' ? 'active' : 'inactive';

    $data = [
        'employee_code'  => $employee_code,
        'employee_name'  => $full_name,
        'title'          => clean_post('title'),
        'first_name'     => $first_name,
        'middle_name'    => $middle_name,
        'last_name'      => $last_name,
        'dob'            => date_or_null($_POST['dob'] ?? ''),
        'gender'         => clean_post('gender'),
        'blood'          => clean_post('blood'),
        'marital'        => clean_post('marital'),
        'nationality'    => clean_post('nationality', 'Indian'),
        'phone'          => clean_post('phone'),
        'phone2'         => clean_post('phone2'),
        'personal_email' => clean_post('email'),
        'official_email' => clean_post('off_email'),
        'address'        => clean_post('address'),
        'aadhaar'        => clean_post('aadhaar'),
        'pan'            => strtoupper(clean_post('pan')),
        'uan'            => clean_post('uan'),
        'esi_no'         => clean_post('esi_no'),
        'department'     => clean_post('dept'),
        'designation'    => clean_post('desig'),
        'emp_type'       => clean_post('emp_type', 'Permanent'),
        'manager'        => clean_post('manager') !== '' ? (int)clean_post('manager') : null,
        'grade'          => clean_post('grade'),
        'location'       => clean_post('location'),
        'join_date'      => date_or_null($_POST['join'] ?? ''),
        'probation'      => clean_post('probation'),
        'notice'         => clean_post('notice'),
        'confirm_date'   => date_or_null($_POST['confirm_date'] ?? ''),
        'contract_end'   => date_or_null($_POST['contract_end'] ?? ''),
        'shift'          => clean_post('shift'),
        'qualification'  => clean_post('qualification'),
        'specialisation' => clean_post('specialisation'),
        'reg_no'         => clean_post('reg_no'),
        'ctc_monthly'    => (float)clean_post('salary', 0),
        'basic_pct'      => (float)clean_post('basic_pct', 60),
        'hra_pct'        => (float)clean_post('hra_pct', 40),
        'acc_name'       => clean_post('acc_name'),
        'acc_no'         => clean_post('acc_no'),
        'bank'           => clean_post('bank'),
        'ifsc'           => strtoupper(clean_post('ifsc')),
        'branch'         => clean_post('branch'),
        'pay_mode'       => clean_post('pay_mode', 'NEFT'),
        'nom_name'       => clean_post('nom_name'),
        'nom_rel'        => clean_post('nom_rel'),
        'emg_name'       => clean_post('emg_name'),
        'emg_rel'        => clean_post('emg_rel'),
        'emg_phone'      => clean_post('emg_phone'),
        'notes'          => clean_post('notes'),
        'status'         => $list_status,
    ];

    $upload_dir_photos = 'uploads/photos/';
    $upload_dir_docs   = 'uploads/docs/';
    
    if (!is_dir($upload_dir_photos)) mkdir($upload_dir_photos, 0755, true); 
    if (!is_dir($upload_dir_docs)) mkdir($upload_dir_docs, 0755, true); 

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png']) && $_FILES['photo']['size'] <= 2097152) { 
            $dest = $upload_dir_photos . 'emp_' . time() . '_' . uniqid() . '.' . $file_ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                $data['profile_photo'] = $dest;
            }
        }
    }

    $doc_fields = ['aadhaar_doc', 'pan_doc', 'photo_doc', 'edu_doc', 'bank_doc', 'appt_doc'];
    foreach ($doc_fields as $df) {
        if (isset($_FILES[$df]) && $_FILES[$df]['error'] === UPLOAD_ERR_OK) {
            $file_ext = strtolower(pathinfo($_FILES[$df]['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'])) { 
                $dest = $upload_dir_docs . $df . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                if (move_uploaded_file($_FILES[$df]['tmp_name'], $dest)) {
                    $data[$df] = $dest;
                }
            }
        }
    }

    try {
        if ($emp_id > 0) { 
            $set_parts = [];
            foreach ($data as $col => $val) {
                $set_parts[] = "`$col` = " . sql_val($conn, $val);
            }
            $sql = "UPDATE employees SET " . implode(", ", $set_parts) . ", updated_at=NOW() WHERE id=" . $emp_id;
            if (!mysqli_query($conn, $sql)) throw new Exception(mysqli_error($conn));
            $employee_id = $emp_id;
            $_SESSION['toast_msg'] = 'Employee record updated successfully!';
        } else {
            $cols = array_keys($data);
            $vals = [];
            foreach ($data as $col => $val) {
                $vals[] = sql_val($conn, $val);
            }
            $sql = "INSERT INTO employees (`" . implode('`,`', $cols) . "`, ctc_template_id, created_at, updated_at) VALUES (" . implode(',', $vals) . ", NULL, NOW(), NOW())";
            if (!mysqli_query($conn, $sql)) throw new Exception(mysqli_error($conn));
            
            $employee_id = mysqli_insert_id($conn);

            $default_password = 'Password@123';
            $hashed_password  = password_hash($default_password, PASSWORD_DEFAULT);
            $user_email       = !empty($data['official_email']) ? $data['official_email'] : $data['personal_email'];
            $username         = $data['employee_code']; 
            
            $u_emp_code = sql_val($conn, $data['employee_code']);
            $u_username = sql_val($conn, $username);
            $u_email    = sql_val($conn, $user_email);
            $u_pass     = sql_val($conn, $hashed_password);

            $user_sql = "INSERT INTO users (employee_code, username, email, password_hash, role, status, created_at, updated_at) VALUES ($u_emp_code, $u_username, $u_email, $u_pass, 'Employee', 'Active', NOW(), NOW())";
            if (!mysqli_query($conn, $user_sql)) throw new Exception("Employee created, but user account failed: " . mysqli_error($conn));

            $_SESSION['toast_msg'] = 'Employee record and user account created successfully!';
        }

        $_SESSION['toast_icon'] = 'success';
        header("Location: AddEmployee?isEditEmployee=true&id=" . $employee_id);
        exit;

    } catch (Exception $e) {
        $_SESSION['toast_icon'] = 'error';
        $_SESSION['toast_msg'] = 'Save failed: ' . $e->getMessage();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

if ($is_edit) {
    $sql = "SELECT * FROM employees WHERE id = $edit_id LIMIT 1";
    $res = mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;

    if (!$row) {
        $_SESSION['toast_icon'] = 'error';
        $_SESSION['toast_msg'] = 'Employee not found.';
        header("Location: employees");
        exit;
    }

    $emp = array_merge($emp, [
        'id' => $row['id'],
        'employee_code' => $row['employee_code'],
        'name' => $row['employee_name'],
        'title' => $row['title'],
        'first_name' => $row['first_name'],
        'middle_name' => $row['middle_name'],
        'last_name' => $row['last_name'],
        'dob' => $row['dob'],
        'gender' => $row['gender'],
        'blood' => $row['blood'],
        'marital' => $row['marital'],
        'nationality' => $row['nationality'],
        'phone' => $row['phone'],
        'phone2' => $row['phone2'],
        'email' => $row['personal_email'],
        'off_email' => $row['official_email'],
        'address' => $row['address'],
        'aadhaar' => $row['aadhaar'],
        'pan' => $row['pan'],
        'uan' => $row['uan'],
        'esi_no' => $row['esi_no'],
        'dept' => $row['department'],
        'desig' => $row['designation'],
        'emp_type' => $row['emp_type'],
        'manager' => $row['manager'],
        'grade' => $row['grade'],
        'location' => $row['location'],
        'status' => strtolower($row['status']) === 'active' ? 'Active' : 'Inactive',
        'join' => $row['join_date'],
        'probation' => $row['probation'],
        'notice' => $row['notice'],
        'confirm_date' => $row['confirm_date'],
        'contract_end' => $row['contract_end'],
        'shift' => $row['shift'],
        'qualification' => $row['qualification'],
        'specialisation' => $row['specialisation'],
        'reg_no' => $row['reg_no'],
        'salary' => $row['ctc_monthly'],
        'basic_pct' => $row['basic_pct'],
        'hra_pct' => $row['hra_pct'],
        'acc_name' => $row['acc_name'],
        'acc_no' => $row['acc_no'],
        'bank' => $row['bank'],
        'ifsc' => $row['ifsc'],
        'branch' => $row['branch'],
        'pay_mode' => $row['pay_mode'],
        'nom_name' => $row['nom_name'],
        'nom_rel' => $row['nom_rel'],
        'emg_name' => $row['emg_name'],
        'emg_rel' => $row['emg_rel'],
        'emg_phone' => $row['emg_phone'],
        'notes' => $row['notes'],
        'profile_photo' => $row['profile_photo'] ?? '', 
        'aadhaar_doc' => $row['aadhaar_doc'] ?? '',
        'pan_doc' => $row['pan_doc'] ?? '',
        'photo_doc' => $row['photo_doc'] ?? '',
        'edu_doc' => $row['edu_doc'] ?? '',
        'bank_doc' => $row['bank_doc'] ?? '',
        'appt_doc' => $row['appt_doc'] ?? '',
    ]);
}

$db_departments = [];
$resDept = mysqli_query($conn, "SELECT dept_name FROM org_departments ORDER BY dept_name ASC");
if ($resDept) {
    while ($r = mysqli_fetch_assoc($resDept)) {
        $db_departments[] = $r['dept_name'];
    }
}

$db_designations = [];
$resDesig = mysqli_query($conn, "SELECT desig_name FROM org_designations ORDER BY desig_name ASC");
if ($resDesig) {
    while ($r = mysqli_fetch_assoc($resDesig)) {
        $db_designations[] = $r['desig_name'];
    }
}

$db_emp_types = [];
$resGroup = mysqli_query($conn, "SELECT group_name FROM org_groups ORDER BY group_name ASC");
if ($resGroup) {
    while ($r = mysqli_fetch_assoc($resGroup)) {
        $db_emp_types[] = $r['group_name'];
    }
}

$db_locations = [];
$resLoc = mysqli_query($conn, "SELECT location_name FROM org_locations ORDER BY location_name ASC");
if ($resLoc) {
    while ($r = mysqli_fetch_assoc($resLoc)) {
        $db_locations[] = $r['location_name'];
    }
}

$first_name_value = $emp['first_name'];
$last_name_value  = $emp['last_name'];

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="employees" class="btn" style="padding:6px 10px;text-decoration:none">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6" /></svg>
        </a>
        <div>
            <h1 class="page-title"><?= $is_edit ? 'Edit Employee' : 'Add New Employee' ?></h1>
            <p class="page-sub">
                <?php if ($is_edit): ?>
                    Editing: <strong><?= esc($emp['name'] ?: 'Employee #' . $emp['id']) ?></strong> &middot; <?= esc($emp['employee_code']) ?>
                <?php else: ?>
                    Fill in all required fields to create an employee record
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php if ($is_edit): ?>
            <span class="badge" style="background:#FEF3C7;color:#92400E;font-size:12px;padding:5px 10px">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Mode
            </span>
            <button class="btn" type="button" style="color:#DC2626;border-color:#FEE2E2;font-size:13px" onclick="confirmDelete(<?= (int)$emp['id'] ?>)">
                Delete
            </button>
        <?php else: ?>
            <span class="badge" style="background:#D1FAE5;color:#065F46;font-size:12px;padding:5px 10px">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg> Add Mode
            </span>
        <?php endif; ?>

        <button type="button" class="btn btn-primary" onclick="submitEmployeeForm()">
            <?= $is_edit ? 'Update Employee' : 'Save Employee' ?>
        </button>
    </div>
</div>

<form method="POST" id="deleteForm" style="display:none">
    <input type="hidden" name="form_action" value="delete_employee">
    <input type="hidden" name="emp_id" value="<?= (int)$emp['id'] ?>">
</form>

<div class="add-emp-wrap">

    <div class="steps-sidebar">
        <div class="section-card" style="padding:14px 10px">
            <div style="font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:1px;padding:0 8px 10px;text-transform:uppercase">Sections</div>
            <ul class="steps-list" id="stepsList">
                <?php
                $steps = [
                    ['Personal Info', 'Name, DOB, ID proofs'],
                    ['Employment', 'Role, dept, joining'],
                    ['Salary & CTC', 'Pay, PF, ESI, PT'],
                    ['Bank Details', 'Account, IFSC, nominee'],
                    ['Documents', 'Upload proofs & certs'],
                    ['Emergency & Other', 'Contact, notes, status'],
                ];
                foreach ($steps as $i => [$label, $sub]):
                    $n = $i + 1;
                ?>
                <li>
                    <button type="button" class="step-link <?= $n === 1 ? 'active' : '' ?>" id="snav-<?= $n ?>" onclick="goToSection(<?= $n ?>)">
                        <div class="step-num" id="snum-<?= $n ?>"><?= $n ?></div>
                        <div style="min-width:0">
                            <div><?= esc($label) ?></div>
                            <div style="font-size:10.5px;color:#9CA3AF;margin-top:1px;font-weight:400"><?= esc($sub) ?></div>
                        </div>
                    </button>
                    <?php if ($n < count($steps)): ?><div class="step-connector"></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="progress-wrap">
                <div class="progress-label">
                    <span>Completion</span>
                    <span id="progressPct" style="color:#6D28D9">0%</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" id="progressFill" style="width:0%"></div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <form id="empForm" method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" enctype="multipart/form-data" novalidate>
            <?php if ($is_edit): ?>
                <input type="hidden" name="emp_id" value="<?= (int)$emp['id'] ?>">
            <?php endif; ?>

            <div class="form-section active" id="section-1">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#EDE9FE; color:#7C3AED;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                        </div>
                        <div>
                            <h3>Profile Photo</h3>
                            <p>JPG or PNG, max 2 MB</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
                            <div id="photoCircle" style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#6D28D9,#2563EB);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;flex-shrink:0;border:3px solid #E5E7EB;overflow:hidden;position:relative">
                                <?php if (!empty($emp['profile_photo'])): ?>
                                    <img id="photoPreviewImg" src="<?= esc($emp['profile_photo']) ?>" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                                    <span id="photoInitials" style="display:none;"><?= esc(initials_name($emp['name'])) ?></span>
                                <?php else: ?>
                                    <span id="photoInitials"><?= esc(initials_name($emp['name'])) ?></span>
                                    <img id="photoPreviewImg" src="" alt="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                <?php endif; ?>
                            </div>

                            <label class="photo-zone" for="photoInput" style="flex:1;min-width:180px">
                                <input type="file" id="photoInput" name="photo" accept="image/png,image/jpeg,image/jpg" onchange="previewPhoto(event)">
                                <div style="font-size:13px;font-weight:600;color:#374151;margin-top:6px">Click or drag photo here</div>
                                <div style="font-size:11px;color:#9CA3AF;margin-top:3px">JPG, PNG up to 2 MB</div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#EDE9FE; color:#7C3AED;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div>
                            <h3>Full Name</h3>
                            <p>Legal name as on government documents</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-4">
                            <div class="fg">
                                <label>TITLE</label>
                                <select name="title" onchange="updatePreview()">
                                    <option value="">Select</option>
                                    <?php foreach (['Mr.','Mrs.','Ms.','Dr.','Prof.'] as $t): ?>
                                        <option value="<?= esc($t) ?>" <?= sel($emp['title'], $t) ?>><?= esc($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>FIRST NAME <span class="req">*</span></label>
                                <input type="text" name="first_name" id="fFirstName" value="<?= esc($first_name_value) ?>" placeholder="e.g. Abhi" oninput="updatePreview();liveValidate(this)" required>
                                <span class="field-error">Required</span>
                            </div>
                            <div class="fg">
                                <label>MIDDLE NAME</label>
                                <input type="text" name="middle_name" value="<?= esc($emp['middle_name']) ?>" placeholder="Optional">
                            </div>
                            <div class="fg">
                                <label>LAST NAME <span class="req">*</span></label>
                                <input type="text" name="last_name" id="fLastName" value="<?= esc($last_name_value) ?>" placeholder="e.g. Sharma" oninput="updatePreview();liveValidate(this)" required>
                                <span class="field-error">Required</span>
                            </div>
                        </div>

                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>DATE OF BIRTH</label>
                                <input type="date" name="dob" value="<?= esc($emp['dob']) ?>" onchange="updatePreview()">
                            </div>
                            <div class="fg">
                                <label>GENDER</label>
                                <select name="gender" onchange="updatePreview()">
                                    <option value="">Select</option>
                                    <?php foreach (['Male','Female','Other','Prefer not to say'] as $g): ?>
                                        <option value="<?= esc($g) ?>" <?= sel($emp['gender'], $g) ?>><?= esc($g) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>BLOOD GROUP</label>
                                <select name="blood">
                                    <option value="">Select</option>
                                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $b): ?>
                                        <option value="<?= esc($b) ?>" <?= sel($emp['blood'], $b) ?>><?= esc($b) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>MARITAL STATUS</label>
                                <select name="marital">
                                    <option value="">Select</option>
                                    <?php foreach (['Single','Married','Divorced','Widowed'] as $m): ?>
                                        <option value="<?= esc($m) ?>" <?= sel($emp['marital'], $m) ?>><?= esc($m) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>NATIONALITY</label>
                                <input type="text" name="nationality" value="<?= esc($emp['nationality'] ?: 'Indian') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#D1FAE5; color:#059669;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </div>
                        <div>
                            <h3>Contact Details</h3>
                            <p>Phone, email, and address</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>MOBILE NUMBER</label>
                                <input type="tel" name="phone" id="fPhone" value="<?= esc($emp['phone']) ?>" placeholder="+91 98321 00001" oninput="updatePreview()">
                            </div>
                            <div class="fg">
                                <label>ALTERNATE PHONE</label>
                                <input type="tel" name="phone2" value="<?= esc($emp['phone2']) ?>" placeholder="Optional">
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>PERSONAL EMAIL</label>
                                <input type="email" name="email" id="fEmail" value="<?= esc($emp['email']) ?>" placeholder="personal@gmail.com" oninput="updatePreview()">
                            </div>
                            <div class="fg">
                                <label>OFFICIAL EMAIL</label>
                                <input type="email" name="off_email" id="fOffEmail" value="<?= esc($emp['off_email']) ?>" placeholder="name@company.com" oninput="updatePreview()">
                            </div>
                        </div>

                        <div class="fg-row col-1">
                            <div class="fg">
                                <label>CURRENT ADDRESS</label>
                                <textarea name="address" rows="2" placeholder="House No., Street, Area, City, PIN"><?= esc($emp['address']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#DBEAFE; color:#2563EB;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="14" x="3" y="5" rx="2" ry="2"/><path d="M10 11h4"/><path d="M10 15h4"/><circle cx="7" cy="11" r="2"/></svg>
                        </div>
                        <div>
                            <h3>Identity Numbers</h3>
                            <p>Aadhaar, PAN, UAN, ESI</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>AADHAAR NUMBER</label>
                                <input type="text" name="aadhaar" value="<?= esc($emp['aadhaar']) ?>" placeholder="XXXX XXXX XXXX" maxlength="14">
                            </div>
                            <div class="fg">
                                <label>PAN NUMBER</label>
                                <input type="text" name="pan" value="<?= esc($emp['pan']) ?>" placeholder="ABCDE1234F" style="text-transform:uppercase" maxlength="10">
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>UAN (PF)</label>
                                <input type="text" name="uan" value="<?= esc($emp['uan']) ?>" placeholder="100XXXXXXXXX">
                            </div>
                            <div class="fg">
                                <label>ESI NUMBER</label>
                                <input type="text" name="esi_no" value="<?= esc($emp['esi_no']) ?>" placeholder="31-XX-XXXXXX-XXX-XXXX">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <div></div>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">Employment Details</button>
                </div>
            </div>

            <div class="form-section" id="section-2">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#DBEAFE; color:#2563EB;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        </div>
                        <div>
                            <h3>Role Information</h3>
                            <p>Department, designation, employee type</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>EMPLOYEE ID</label>
                                <input type="text" name="emp_code" id="fEmpCode" value="<?= esc($is_edit ? $emp['employee_code'] : '1' . str_pad((string)(count($employees) + 1), 3, '0', STR_PAD_LEFT)) ?>" oninput="updatePreview()">
                            </div>
                            <div class="fg">
                                <label>DEPARTMENT <span class="req">*</span></label>
                                <select name="dept" id="fDept" onchange="updatePreview()" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($db_departments as $d): ?>
                                        <option value="<?= esc($d) ?>" <?= sel($emp['dept'], $d) ?>><?= esc($d) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>DESIGNATION <span class="req">*</span></label>
                                <select name="desig" id="fDesig" onchange="updatePreview()" required>
                                    <option value="">Select Designation</option>
                                    <?php foreach ($db_designations as $dsg): ?>
                                        <option value="<?= esc($dsg) ?>" <?= sel($emp['desig'], $dsg) ?>><?= esc($dsg) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>EMPLOYEE TYPE</label>
                                <select name="emp_type" onchange="updatePreview()">
                                    <option value="">Select Employee Type</option>
                                    <?php foreach ($db_emp_types as $t): ?>
                                        <option value="<?= esc($t) ?>" <?= sel($emp['emp_type'], $t) ?>><?= esc($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>REPORTING MANAGER</label>
                                <select name="manager">
                                    <option value="">None (Top Level)</option>
                                    <?php foreach ($employees as $mgr): ?>
                                        <?php if ((int)$mgr['id'] !== (int)($emp['id'] ?: 0)): ?>
                                            <option value="<?= (int)$mgr['id'] ?>" <?= sel($emp['manager'], $mgr['id']) ?>>
                                                <?= esc($mgr['name']) ?> — <?= esc($mgr['dept']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>GRADE / LEVEL</label>
                                <select name="grade">
                                    <option value="">Select</option>
                                    <?php foreach (['Grade A – Senior','Grade B – Mid','Grade C – Junior','Grade D – Entry'] as $g): ?>
                                        <option value="<?= esc($g) ?>" <?= sel($emp['grade'], $g) ?>><?= esc($g) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>WORK LOCATION</label>
                                <select name="location">
                                    <option value="">Select Location</option>
                                    <?php foreach ($db_locations as $loc): ?>
                                        <option value="<?= esc($loc) ?>" <?= sel($emp['location'], $loc) ?>><?= esc($loc) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>STATUS</label>
                                <select name="status" id="fStatus">
                                    <?php foreach (['Active','Inactive','On Notice','Suspended','Resigned'] as $s): ?>
                                        <option value="<?= esc($s) ?>" <?= sel($emp['status'] ?: 'Active', $s) ?>><?= esc($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#FEF3C7; color:#D97706;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                        </div>
                        <div>
                            <h3>Dates &amp; Contract</h3>
                            <p>Joining, probation, confirmation, notice period</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>DATE OF JOINING <span class="req">*</span></label>
                                <input type="date" name="join" id="fJoin" value="<?= esc($emp['join']) ?>" onchange="updatePreview()" required>
                            </div>
                            <div class="fg">
                                <label>PROBATION PERIOD</label>
                                <select name="probation">
                                    <option value="">Select</option>
                                    <?php foreach (['None','1 Month','3 Months','6 Months','1 Year'] as $p): ?>
                                        <option value="<?= esc($p) ?>" <?= sel($emp['probation'], $p) ?>><?= esc($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>NOTICE PERIOD</label>
                                <select name="notice">
                                    <option value="">Select</option>
                                    <?php foreach (['15 Days','30 Days','60 Days','90 Days'] as $n): ?>
                                        <option value="<?= esc($n) ?>" <?= sel($emp['notice'], $n) ?>><?= esc($n) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>CONFIRMATION DATE</label>
                                <input type="date" name="confirm_date" value="<?= esc($emp['confirm_date']) ?>">
                            </div>
                            <div class="fg">
                                <label>CONTRACT END DATE</label>
                                <input type="date" name="contract_end" value="<?= esc($emp['contract_end']) ?>">
                            </div>
                            <div class="fg">
                                <label>SHIFT</label>
                                <select name="shift">
                                    <option value="">Select</option>
                                    <?php foreach (['General (9AM–5PM)','Morning (7AM–3PM)','Evening (3PM–11PM)','Night (11PM–7AM)','Rotational'] as $sh): ?>
                                        <option value="<?= esc($sh) ?>" <?= sel($emp['shift'], $sh) ?>><?= esc($sh) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#EDE9FE; color:#7C3AED;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        </div>
                        <div>
                            <h3>Qualifications</h3>
                            <p>Education, specialisation, registration</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>HIGHEST QUALIFICATION</label>
                                <select name="qualification">
                                    <option value="">Select</option>
                                    <?php foreach (['10th/SSLC','12th/HSC','Diploma','B.Sc/BCA','MBA/MCA','MBBS','MD/MS','DM/MCh','PhD'] as $q): ?>
                                        <option value="<?= esc($q) ?>" <?= sel($emp['qualification'], $q) ?>><?= esc($q) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>SPECIALISATION</label>
                                <input type="text" name="specialisation" value="<?= esc($emp['specialisation']) ?>" placeholder="e.g. Obstetrics & Gynaecology">
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>REGISTRATION NUMBER</label>
                                <input type="text" name="reg_no" value="<?= esc($emp['reg_no']) ?>" placeholder="Medical/Professional reg no.">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">Personal Info</button>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">Salary &amp; CTC</button>
                </div>
            </div>

            <div class="form-section" id="section-3">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#D1FAE5; color:#059669;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 8h12"/><path d="m6 13 8.5 8"/><path d="M6 13h3c3.314 0 6-2.686 6-6s-2.686-6-6-6H6"/></svg>
                        </div>
                        <div>
                            <h3>Gross Salary</h3>
                            <p>Enter monthly gross; components auto-calculate</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>GROSS SALARY / MONTH (₹) <span class="req">*</span></label>
                                <input type="number" name="salary" id="fSalary" value="<?= esc($emp['salary']) ?>" min="0" placeholder="e.g. 38000" oninput="calcSalary();updatePreview()" required>
                            </div>
                            <div class="fg">
                                <label>BASIC % OF GROSS</label>
                                <input type="number" name="basic_pct" id="fBasicPct" value="<?= esc($emp['basic_pct'] ?: 60) ?>" min="1" max="100" oninput="calcSalary()">
                            </div>
                            <div class="fg">
                                <label>HRA % OF BASIC</label>
                                <input type="number" name="hra_pct" id="fHraPct" value="<?= esc($emp['hra_pct'] ?: 40) ?>" min="0" max="100" oninput="calcSalary()">
                            </div>
                        </div>

                        <div id="salBreakdownWrap" style="display:none">
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border:1px solid #E5E7EB;border-radius:9px;overflow:hidden;margin-top:4px">
                                <div style="padding:12px 14px;border-right:1px solid #E5E7EB">
                                    <div style="font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:.5px;margin-bottom:8px">EARNINGS</div>
                                    <table class="sal-table" id="tEarnings"></table>
                                </div>
                                <div style="padding:12px 14px;border-right:1px solid #E5E7EB">
                                    <div style="font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:.5px;margin-bottom:8px">DEDUCTIONS</div>
                                    <table class="sal-table" id="tDeductions"></table>
                                </div>
                                <div style="padding:12px 14px;background:#F9FAFB">
                                    <div style="font-size:10px;font-weight:700;color:#9CA3AF;letter-spacing:.5px;margin-bottom:8px">EMPLOYER COST</div>
                                    <table class="sal-table" id="tEmployer"></table>
                                </div>
                            </div>
                            <div style="margin-top:8px;background:linear-gradient(90deg,#D1FAE5,#DBEAFE);border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center">
                                <span style="font-size:13px;font-weight:600;color:#111827">Monthly Net Take-Home</span>
                                <span style="font-size:18px;font-weight:700;color:#059669" id="salNetDisplay">₹0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">Employment</button>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">Bank Details</button>
                </div>
            </div>

            <div class="form-section" id="section-4">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#D1FAE5; color:#059669;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
                        </div>
                        <div>
                            <h3>Salary Bank Account</h3>
                            <p>Used for salary credit via NEFT / IMPS</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>ACCOUNT HOLDER NAME</label>
                                <input type="text" name="acc_name" value="<?= esc($emp['acc_name']) ?>" placeholder="As on bank passbook">
                            </div>
                            <div class="fg">
                                <label>ACCOUNT NUMBER</label>
                                <input type="text" name="acc_no" id="fAccNo" value="<?= esc($emp['acc_no']) ?>" placeholder="e.g. 001234567890">
                            </div>
                        </div>

                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>BANK NAME</label>
                                <select name="bank" id="fBank">
                                    <option value="">Select</option>
                                    <?php foreach (['State Bank of India (SBI)','Punjab National Bank','HDFC Bank','ICICI Bank','Axis Bank','Bank of Baroda','Canara Bank','UCO Bank','Other'] as $bk): ?>
                                        <option value="<?= esc($bk) ?>" <?= sel($emp['bank'], $bk) ?>><?= esc($bk) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>IFSC CODE</label>
                                <input type="text" name="ifsc" id="fIfsc" value="<?= esc($emp['ifsc']) ?>" placeholder="e.g. SBIN0001234" style="text-transform:uppercase">
                            </div>
                            <div class="fg">
                                <label>BRANCH NAME</label>
                                <input type="text" name="branch" id="fBranch" value="<?= esc($emp['branch']) ?>" placeholder="Branch name">
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>PAYMENT MODE</label>
                                <select name="pay_mode">
                                    <?php foreach (['NEFT','IMPS','RTGS','Cheque','Cash'] as $pm): ?>
                                        <option value="<?= esc($pm) ?>" <?= sel($emp['pay_mode'], $pm) ?>><?= esc($pm) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>NOMINEE NAME</label>
                                <input type="text" name="nom_name" value="<?= esc($emp['nom_name']) ?>" placeholder="Full legal name">
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>NOMINEE RELATIONSHIP</label>
                                <select name="nom_rel">
                                    <option value="">Select</option>
                                    <?php foreach (['Spouse','Father','Mother','Son','Daughter','Sibling','Other'] as $r): ?>
                                        <option value="<?= esc($r) ?>" <?= sel($emp['nom_rel'], $r) ?>><?= esc($r) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">Salary &amp; CTC</button>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">Documents</button>
                </div>
            </div>

            <div class="form-section" id="section-5">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#FFEDD5; color:#EA580C;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
                        </div>
                        <div>
                            <h3>Document Uploads</h3>
                            <p>Click a row to upload the corresponding document</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <?php
                        $docs = [
                            ['<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="14" x="3" y="5" rx="2" ry="2"/><path d="M10 11h4"/><path d="M10 15h4"/><circle cx="7" cy="11" r="2"/></svg>', 'Aadhaar Card', 'aadhaar_doc', true],
                            ['<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>', 'PAN Card', 'pan_doc', true],
                            ['<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>', 'Passport-size Photo', 'photo_doc', true],
                            ['<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>', 'Educational Certificates', 'edu_doc', true],
                            ['<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>', 'Bank Passbook / Cheque', 'bank_doc', true],
                            ['<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>', 'Appointment Letter', 'appt_doc', false],
                        ];
                        foreach ($docs as [$icon, $label, $field, $req]):
                            $existing = $emp[$field] ?? '';
                        ?>
                            <div class="doc-row <?= $existing ? 'uploaded' : '' ?>">
                                <input type="file" name="<?= esc($field) ?>" id="doc_<?= esc($field) ?>" style="display:none" onchange="handleDocUpload(this, '<?= esc($label) ?>')">
                                <div class="doc-icon" style="color:#6B7280" onclick="document.getElementById('doc_<?= esc($field) ?>').click()"><?= $icon ?></div>
                                <div style="flex:1; cursor:pointer;" onclick="document.getElementById('doc_<?= esc($field) ?>').click()">
                                    <div style="font-size:13px;font-weight:600;color:#111827"><?= esc($label) ?></div>
                                    <div class="doc-meta" style="font-size:11px;color:#9CA3AF;margin-top:1px">
                                        <?php if ($existing): ?>
                                            <span style="color:#059669;font-weight:600">Document Uploaded</span> &middot; Click to replace
                                        <?php else: ?>
                                            <?= $req ? 'Required' : 'Optional' ?> &middot; Click to upload
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <?php if ($existing): ?>
                                        <a href="<?= esc($existing) ?>" target="_blank" class="badge" style="background:#DBEAFE;color:#1D4ED8;text-decoration:none;font-weight:600;">View</a>
                                    <?php endif; ?>
                                    <span class="badge" style="background:#F3F4F6;color:#6B7280;cursor:pointer;" onclick="document.getElementById('doc_<?= esc($field) ?>').click()">
                                        <?= $existing ? 'Replace' : 'Upload' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">Bank Details</button>
                    <button type="button" class="btn btn-primary" onclick="nextSection()">Emergency &amp; Other</button>
                </div>
            </div>

            <div class="form-section" id="section-6">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#FEE2E2; color:#DC2626;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><line x1="4.93" x2="9.17" y1="4.93" y2="9.17"/><line x1="14.83" x2="19.07" y1="14.83" y2="19.07"/><line x1="14.83" x2="19.07" y1="9.17" y2="4.93"/><line x1="14.83" x2="4.93" y1="9.17" y2="19.07"/></svg>
                        </div>
                        <div>
                            <h3>Emergency Contact</h3>
                            <p>Person to contact in case of emergency</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>CONTACT NAME</label>
                                <input type="text" name="emg_name" value="<?= esc($emp['emg_name']) ?>" placeholder="Full name">
                            </div>
                            <div class="fg">
                                <label>RELATIONSHIP</label>
                                <select name="emg_rel">
                                    <option value="">Select</option>
                                    <?php foreach (['Spouse','Father','Mother','Sibling','Friend','Other'] as $r): ?>
                                        <option value="<?= esc($r) ?>" <?= sel($emp['emg_rel'], $r) ?>><?= esc($r) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fg">
                                <label>PHONE</label>
                                <input type="tel" name="emg_phone" value="<?= esc($emp['emg_phone']) ?>" placeholder="+91 98765 43210">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#F9FAFB; color:#4B5563;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>
                        </div>
                        <div>
                            <h3>HR Notes &amp; Reference</h3>
                            <p>Internal notes, background check, reference</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>EMPLOYEE STATUS</label>
                                <select name="status_final">
                                    <?php foreach (['Active','Inactive','On Notice','Suspended','Resigned'] as $s): ?>
                                        <option value="<?= esc($s) ?>" <?= sel($emp['status'] ?: 'Active', $s) ?>><?= esc($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-1">
                            <div class="fg">
                                <label>INTERNAL HR NOTES</label>
                                <textarea name="notes" rows="3" placeholder="Internal notes — not visible to employee."><?= esc($emp['notes']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-nav">
                    <button type="button" class="btn" onclick="prevSection()">Documents</button>
                    <button type="submit" class="btn btn-primary" style="padding:9px 22px;font-size:13.5px" id="submitBtn">
                        <?= $is_edit ? 'Update Employee Record' : 'Save Employee Record' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="preview-panel">
        <div class="emp-preview-card">
            <!-- Avatar container firmly positioned with relative -->
            <div class="epc-avatar" id="epcAvatar" style="position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; width:80px; height:80px; border-radius:50%; margin:0 auto 12px; background:linear-gradient(135deg,#6D28D9,#2563EB); color:#fff; font-size:26px; font-weight:700; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                <?php if (!empty($emp['profile_photo'])): ?>
                    <img id="epcAvatarImg" src="<?= esc($emp['profile_photo']) ?>" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; display:block;">
                    <span id="epcInitials" style="display:none;"><?= esc(initials_name($emp['name'])) ?></span>
                <?php else: ?>
                    <span id="epcInitials"><?= esc(initials_name($emp['name'])) ?></span>
                    <img id="epcAvatarImg" src="" alt="" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; display:none;">
                <?php endif; ?>
            </div>
            
            <div class="epc-name" id="epcName"><?= esc($emp['name'] ?: 'New Employee') ?></div>
            <div class="epc-desig" id="epcDesig"><?= esc($emp['desig'] ?: '—') ?></div>
            <div style="display:flex;justify-content:center;margin-top:8px">
                <span class="badge" id="epcDeptBadge" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.6);font-size:11px">
                    <?= esc($emp['dept'] ?: 'No Department') ?>
                </span>
            </div>
            <div class="epc-divider"></div>

            <div class="epc-row">
                <div>
                    <div class="epc-row-label">EMP ID</div>
                    <div class="epc-row-val" id="epcId"><?= esc($emp['employee_code'] ?: '—') ?></div>
                </div>
            </div>

            <div class="epc-row">
                <div>
                    <div class="epc-row-label">JOINED</div>
                    <div class="epc-row-val" id="epcJoined"><?= esc($emp['join'] ?: '—') ?></div>
                </div>
            </div>

            <div class="epc-row">
                <div>
                    <div class="epc-row-label">PHONE</div>
                    <div class="epc-row-val" id="epcPhone"><?= esc($emp['phone'] ?: '—') ?></div>
                </div>
            </div>

            <div class="epc-row">
                <div>
                    <div class="epc-row-label">EMAIL</div>
                    <div class="epc-row-val" id="epcEmail" style="font-size:11px"><?= esc($emp['email'] ?: '—') ?></div>
                </div>
            </div>
        </div>

        <div class="sal-preview-card" id="salPreviewCard" style="display:none">
            <div class="sal-preview-title">Salary Breakdown</div>
            <div class="spr-row"><span class="spr-label">Gross Salary</span><span class="spr-val" id="spGross">—</span></div>
            <div class="spr-row"><span class="spr-label">Basic</span><span class="spr-val" id="spBasic">—</span></div>
            <div class="spr-row"><span class="spr-label">HRA</span><span class="spr-val" id="spHra">—</span></div>
            <div class="spr-row deduct"><span class="spr-label">PF</span><span class="spr-val" id="spPf">—</span></div>
            <div class="spr-row deduct"><span class="spr-label">Prof. Tax</span><span class="spr-val" id="spPt">—</span></div>
            <div class="spr-row total"><span class="spr-label">Net Salary</span><span class="spr-val" id="spNet">—</span></div>
        </div>
    </div>
</div>

<div id="empToastBox" style="display:none;position:fixed;right:22px;bottom:22px;background:#111827;color:#fff;padding:12px 18px;border-radius:10px;z-index:99999;box-shadow:0 8px 28px rgba(0,0,0,.2);font-size:14px;font-weight:500;align-items:center;gap:8px;">
    <span id="empToastIcon" style="font-size:16px; display:flex; align-items:center;"></span>
    <span id="empToastMsg"></span>
</div>

<script>
let currentSection = 1;
const totalSections = 6;

const svgIcons = {
    success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>',
    warning: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>'
};

function showEmpToast(type, msg) {
    const box = document.getElementById('empToastBox');
    const ti = document.getElementById('empToastIcon');
    const tm = document.getElementById('empToastMsg');

    if (!box || !ti || !tm) return alert(msg);

    ti.innerHTML = svgIcons[type] || svgIcons.success;
    tm.textContent = msg;
    box.style.display = 'flex';

    clearTimeout(box._timer);
    box._timer = setTimeout(() => {
        box.style.display = 'none';
    }, 3200);
}

function goToSection(n) {
    currentSection = n;

    document.querySelectorAll('.form-section').forEach(sec => sec.classList.remove('active'));
    const target = document.getElementById('section-' + n);
    if (target) target.classList.add('active');

    document.querySelectorAll('.step-link').forEach(btn => btn.classList.remove('active'));
    const nav = document.getElementById('snav-' + n);
    if (nav) nav.classList.add('active');

    updateProgress();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextSection() {
    if (currentSection < totalSections) {
        goToSection(currentSection + 1);
    }
}

function prevSection() {
    if (currentSection > 1) {
        goToSection(currentSection - 1);
    }
}

function updateProgress() {
    const pct = Math.round((currentSection / totalSections) * 100);
    const p = document.getElementById('progressPct');
    const f = document.getElementById('progressFill');

    if (p) p.textContent = pct + '%';
    if (f) f.style.width = pct + '%';
}

function liveValidate(el) {
    if (!el) return;
    if (el.required && !el.value.trim()) {
        el.classList.add('invalid');
    } else {
        el.classList.remove('invalid');
    }
}

function submitEmployeeForm() {
    const form = document.getElementById('empForm');
    if (!form) return;

    const required = form.querySelectorAll('[required]');
    let ok = true;

    required.forEach(el => {
        if (!el.value.trim()) {
            ok = false;
            el.classList.add('invalid');
        } else {
            el.classList.remove('invalid');
        }
    });

    if (!ok) {
        showEmpToast('warning', 'Please fill all required fields.');
        return;
    }

    form.submit();
}

function confirmDelete(id) {
    if (confirm('Delete this employee?')) {
        document.getElementById('deleteForm').submit();
    }
}

function previewPhoto(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const img1 = document.getElementById('photoPreviewImg');
        const img2 = document.getElementById('epcAvatarImg');
        const txt1 = document.getElementById('photoInitials');
        const txt2 = document.getElementById('epcInitials');

        if (img1) {
            img1.src = e.target.result;
            img1.style.display = 'block';
        }

        if (img2) {
            img2.src = e.target.result;
            img2.style.display = 'block';
        }

        if (txt1) txt1.style.display = 'none';
        if (txt2) txt2.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function updatePreview() {
    const first = document.getElementById('fFirstName')?.value || '';
    const last = document.getElementById('fLastName')?.value || '';
    const name = (first + ' ' + last).trim() || 'New Employee';

    const desig = document.getElementById('fDesig')?.value || '—';
    const dept = document.getElementById('fDept')?.value || 'No Department';
    const empCode = document.getElementById('fEmpCode')?.value || '—';
    const joined = document.getElementById('fJoin')?.value || '—';
    const phone = document.getElementById('fPhone')?.value || '—';
    const email = document.getElementById('fEmail')?.value || document.getElementById('fOffEmail')?.value || '—';

    const nameEl = document.getElementById('epcName');
    const desigEl = document.getElementById('epcDesig');
    const deptEl = document.getElementById('epcDeptBadge');
    const idEl = document.getElementById('epcId');
    const joinEl = document.getElementById('epcJoined');
    const phoneEl = document.getElementById('epcPhone');
    const emailEl = document.getElementById('epcEmail');
    const initEl = document.getElementById('epcInitials');
    const photoInit = document.getElementById('photoInitials');

    if (nameEl) nameEl.textContent = name;
    if (desigEl) desigEl.textContent = desig;
    if (deptEl) deptEl.textContent = dept;
    if (idEl) idEl.textContent = empCode;
    if (joinEl) joinEl.textContent = joined;
    if (phoneEl) phoneEl.textContent = phone;
    if (emailEl) emailEl.textContent = email;

    const initials = ((first.charAt(0) || '') + (last.charAt(0) || '')).toUpperCase() || '??';
    
    const avatarImg = document.getElementById('epcAvatarImg');
    if (!avatarImg || avatarImg.style.display === 'none' || avatarImg.getAttribute('src') === '') {
        if (initEl) initEl.textContent = initials;
        if (photoInit) photoInit.textContent = initials;
    }
}

function money(n) {
    return '₹' + Math.round(Number(n || 0)).toLocaleString('en-IN');
}

function calcSalary() {
    const gross = Number(document.getElementById('fSalary')?.value || 0);
    const basicPct = Number(document.getElementById('fBasicPct')?.value || 60);
    const hraPct = Number(document.getElementById('fHraPct')?.value || 40);

    if (gross <= 0) {
        const wrap = document.getElementById('salBreakdownWrap');
        const card = document.getElementById('salPreviewCard');
        if (wrap) wrap.style.display = 'none';
        if (card) card.style.display = 'none';
        return;
    }

    const basic = gross * basicPct / 100;
    const hra = basic * hraPct / 100;
    const special = Math.max(0, gross - basic - hra);
    const pf = basic * 0.12;
    const pt = gross > 20000 ? 200 : (gross > 15000 ? 150 : (gross > 10000 ? 110 : 0));
    const net = gross - pf - pt;
    const erpf = basic * 0.12;
    const ctc = gross + erpf;

    document.getElementById('salBreakdownWrap').style.display = 'block';
    document.getElementById('salPreviewCard').style.display = 'block';

    document.getElementById('tEarnings').innerHTML =
        `<tr><td>Basic</td><td>${money(basic)}</td></tr>
         <tr><td>HRA</td><td>${money(hra)}</td></tr>
         <tr><td>Special</td><td>${money(special)}</td></tr>`;

    document.getElementById('tDeductions').innerHTML =
        `<tr><td>PF</td><td>${money(pf)}</td></tr>
         <tr><td>PT</td><td>${money(pt)}</td></tr>`;

    document.getElementById('tEmployer').innerHTML =
        `<tr><td>Employer PF</td><td>${money(erpf)}</td></tr>
         <tr><td>CTC</td><td>${money(ctc)}</td></tr>`;

    document.getElementById('salNetDisplay').textContent = money(net);

    document.getElementById('spGross').textContent = money(gross);
    document.getElementById('spBasic').textContent = money(basic);
    document.getElementById('spHra').textContent = money(hra);
    document.getElementById('spPf').textContent = money(pf);
    document.getElementById('spPt').textContent = money(pt);
    document.getElementById('spNet').textContent = money(net);
}

function handleDocUpload(input, label) {
    if (input.files && input.files.length) {
        const row = input.closest('.doc-row');
        if (row) {
            row.classList.add('uploaded');
            const meta = row.querySelector('.doc-meta');
            if(meta) meta.innerHTML = `<span style="color:#059669;font-weight:600">${input.files[0].name}</span> &middot; Ready to save`;
        }
        showEmpToast('success', label + ' selected.');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateProgress();
    updatePreview();
    calcSalary();

    <?php if (!empty($toast_msg)): ?>
    showEmpToast(
        <?= json_encode($toast_icon, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, 
        <?= json_encode($toast_msg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );
    <?php endif; ?>
});
</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>