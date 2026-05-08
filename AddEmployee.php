<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$is_edit    = isset($_GET['isEditEmployee']) && $_GET['isEditEmployee'] === 'true';
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

$toast_msg  = $_SESSION['toast_msg'] ?? '';
$toast_icon = $_SESSION['toast_icon'] ?? '✅';
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
    'location' => 'Ramkrishna IVF Centre, Siliguri',
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
];

$employees = [];
$resMgr = $conn->query("SELECT id, employee_name, department FROM employees ORDER BY employee_name ASC");
if ($resMgr) {
    while ($r = $resMgr->fetch_assoc()) {
        $employees[] = [
            'id' => $r['id'],
            'name' => $r['employee_name'],
            'dept' => $r['department']
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? 'save_employee';

    if ($form_action === 'delete_employee') {
        $del_id = (int)($_POST['emp_id'] ?? 0);

        if ($del_id > 0) {
            $stmt = $conn->prepare("DELETE FROM employee_profiles WHERE employee_id=?");
            $stmt->bind_param("i", $del_id);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM employees WHERE id=?");
            $stmt->bind_param("i", $del_id);

            if ($stmt->execute()) {
                $_SESSION['toast_icon'] = '✅';
                $_SESSION['toast_msg']  = 'Employee deleted successfully.';
                header("Location: employees");
                exit;
            } else {
                $_SESSION['toast_icon'] = '❌';
                $_SESSION['toast_msg']  = 'Delete failed: ' . $stmt->error;
                header("Location: AddEmployee?isEditEmployee=true&id=" . $del_id);
                exit;
            }
        }
    }

    $required = ['first_name', 'last_name', 'dept', 'desig', 'join', 'salary'];
    $errors = [];

    foreach ($required as $field) {
        if (trim((string)($_POST[$field] ?? '')) === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['toast_icon'] = '⚠';
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
    if ($employee_code === '') {
        $employee_code = 'EMP-' . date('YmdHis');
    }

    $title       = clean_post('title');
    $department  = clean_post('dept');
    $designation = clean_post('desig');
    $salary      = (float)clean_post('salary', 0);
    $status      = clean_post('status_final', clean_post('status', 'Active'));

    $list_status = strtolower($status) === 'active' ? 'active' : 'inactive';

    if ($is_edit && $emp_id > 0) {
        $stmt = $conn->prepare("
            UPDATE employees
            SET employee_code=?, employee_name=?, department=?, ctc_monthly=?, status=?
            WHERE id=?
        ");
        $stmt->bind_param("sssdsi", $employee_code, $full_name, $department, $salary, $list_status, $emp_id);

        if (!$stmt->execute()) {
            $_SESSION['toast_icon'] = '❌';
            $_SESSION['toast_msg'] = 'Employee update failed: ' . $stmt->error;
            header("Location: AddEmployee?isEditEmployee=true&id=" . $emp_id);
            exit;
        }

        $employee_id = $emp_id;
    } else {
        $stmt = $conn->prepare("
            INSERT INTO employees
            (employee_code, employee_name, department, ctc_monthly, ctc_template_id, status)
            VALUES (?, ?, ?, ?, NULL, ?)
        ");
        $stmt->bind_param("sssds", $employee_code, $full_name, $department, $salary, $list_status);

        if (!$stmt->execute()) {
            $_SESSION['toast_icon'] = '❌';
            $_SESSION['toast_msg'] = 'Employee save failed: ' . $stmt->error;
            header("Location: AddEmployee?isAddEmployee=true");
            exit;
        }

        $employee_id = $stmt->insert_id;
    }

    $profile_data = [
        'employee_id' => $employee_id,
        'title' => $title,
        'first_name' => $first_name,
        'middle_name' => $middle_name,
        'last_name' => $last_name,
        'dob' => date_or_null($_POST['dob'] ?? ''),
        'gender' => clean_post('gender'),
        'blood' => clean_post('blood'),
        'marital' => clean_post('marital'),
        'nationality' => clean_post('nationality', 'Indian'),
        'phone2' => clean_post('phone2'),
        'personal_email' => clean_post('email'),
        'official_email' => clean_post('off_email'),
        'address' => clean_post('address'),
        'aadhaar' => clean_post('aadhaar'),
        'pan' => strtoupper(clean_post('pan')),
        'uan' => clean_post('uan'),
        'esi_no' => clean_post('esi_no'),
        'designation' => $designation,
        'emp_type' => clean_post('emp_type', 'Permanent'),
        'manager' => clean_post('manager') !== '' ? (int)clean_post('manager') : null,
        'grade' => clean_post('grade'),
        'location' => clean_post('location'),
        'join_date' => date_or_null($_POST['join'] ?? ''),
        'probation' => clean_post('probation'),
        'notice' => clean_post('notice'),
        'confirm_date' => date_or_null($_POST['confirm_date'] ?? ''),
        'contract_end' => date_or_null($_POST['contract_end'] ?? ''),
        'shift' => clean_post('shift'),
        'qualification' => clean_post('qualification'),
        'specialisation' => clean_post('specialisation'),
        'reg_no' => clean_post('reg_no'),
        'basic_pct' => (float)clean_post('basic_pct', 60),
        'hra_pct' => (float)clean_post('hra_pct', 40),
        'acc_name' => clean_post('acc_name'),
        'acc_no' => clean_post('acc_no'),
        'bank' => clean_post('bank'),
        'ifsc' => strtoupper(clean_post('ifsc')),
        'branch' => clean_post('branch'),
        'pay_mode' => clean_post('pay_mode', 'NEFT'),
        'nom_name' => clean_post('nom_name'),
        'nom_rel' => clean_post('nom_rel'),
        'emg_name' => clean_post('emg_name'),
        'emg_rel' => clean_post('emg_rel'),
        'emg_phone' => clean_post('emg_phone'),
        'notes' => clean_post('notes'),
    ];

    $check = $conn->prepare("SELECT id FROM employee_profiles WHERE employee_id=? LIMIT 1");
    $check->bind_param("i", $employee_id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();

    if ($exists) {
        $sql = "
            UPDATE employee_profiles SET
            title=?, first_name=?, middle_name=?, last_name=?, dob=?, gender=?, blood=?, marital=?, nationality=?,
            phone2=?, personal_email=?, official_email=?, address=?, aadhaar=?, pan=?, uan=?, esi_no=?,
            designation=?, emp_type=?, manager=?, grade=?, location=?, join_date=?, probation=?, notice=?,
            confirm_date=?, contract_end=?, shift=?, qualification=?, specialisation=?, reg_no=?,
            basic_pct=?, hra_pct=?, acc_name=?, acc_no=?, bank=?, ifsc=?, branch=?, pay_mode=?,
            nom_name=?, nom_rel=?, emg_name=?, emg_rel=?, emg_phone=?, notes=?
            WHERE employee_id=?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssssssssssssisssssssssssddsssssssssssssi",
            $profile_data['title'],
            $profile_data['first_name'],
            $profile_data['middle_name'],
            $profile_data['last_name'],
            $profile_data['dob'],
            $profile_data['gender'],
            $profile_data['blood'],
            $profile_data['marital'],
            $profile_data['nationality'],
            $profile_data['phone2'],
            $profile_data['personal_email'],
            $profile_data['official_email'],
            $profile_data['address'],
            $profile_data['aadhaar'],
            $profile_data['pan'],
            $profile_data['uan'],
            $profile_data['esi_no'],
            $profile_data['designation'],
            $profile_data['emp_type'],
            $profile_data['manager'],
            $profile_data['grade'],
            $profile_data['location'],
            $profile_data['join_date'],
            $profile_data['probation'],
            $profile_data['notice'],
            $profile_data['confirm_date'],
            $profile_data['contract_end'],
            $profile_data['shift'],
            $profile_data['qualification'],
            $profile_data['specialisation'],
            $profile_data['reg_no'],
            $profile_data['basic_pct'],
            $profile_data['hra_pct'],
            $profile_data['acc_name'],
            $profile_data['acc_no'],
            $profile_data['bank'],
            $profile_data['ifsc'],
            $profile_data['branch'],
            $profile_data['pay_mode'],
            $profile_data['nom_name'],
            $profile_data['nom_rel'],
            $profile_data['emg_name'],
            $profile_data['emg_rel'],
            $profile_data['emg_phone'],
            $profile_data['notes'],
            $employee_id
        );
    } else {
        $sql = "
            INSERT INTO employee_profiles
            (employee_id, title, first_name, middle_name, last_name, dob, gender, blood, marital, nationality,
            phone2, personal_email, official_email, address, aadhaar, pan, uan, esi_no,
            designation, emp_type, manager, grade, location, join_date, probation, notice,
            confirm_date, contract_end, shift, qualification, specialisation, reg_no,
            basic_pct, hra_pct, acc_name, acc_no, bank, ifsc, branch, pay_mode,
            nom_name, nom_rel, emg_name, emg_rel, emg_phone, notes)
            VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issssssssssssssssssissssssssssssddssssssssssss",
            $profile_data['employee_id'],
            $profile_data['title'],
            $profile_data['first_name'],
            $profile_data['middle_name'],
            $profile_data['last_name'],
            $profile_data['dob'],
            $profile_data['gender'],
            $profile_data['blood'],
            $profile_data['marital'],
            $profile_data['nationality'],
            $profile_data['phone2'],
            $profile_data['personal_email'],
            $profile_data['official_email'],
            $profile_data['address'],
            $profile_data['aadhaar'],
            $profile_data['pan'],
            $profile_data['uan'],
            $profile_data['esi_no'],
            $profile_data['designation'],
            $profile_data['emp_type'],
            $profile_data['manager'],
            $profile_data['grade'],
            $profile_data['location'],
            $profile_data['join_date'],
            $profile_data['probation'],
            $profile_data['notice'],
            $profile_data['confirm_date'],
            $profile_data['contract_end'],
            $profile_data['shift'],
            $profile_data['qualification'],
            $profile_data['specialisation'],
            $profile_data['reg_no'],
            $profile_data['basic_pct'],
            $profile_data['hra_pct'],
            $profile_data['acc_name'],
            $profile_data['acc_no'],
            $profile_data['bank'],
            $profile_data['ifsc'],
            $profile_data['branch'],
            $profile_data['pay_mode'],
            $profile_data['nom_name'],
            $profile_data['nom_rel'],
            $profile_data['emg_name'],
            $profile_data['emg_rel'],
            $profile_data['emg_phone'],
            $profile_data['notes']
        );
    }

    if ($stmt->execute()) {
        $_SESSION['toast_icon'] = '✅';
        $_SESSION['toast_msg'] = $is_edit ? 'Employee record updated successfully!' : 'Employee record created successfully!';
        header("Location: AddEmployee?isEditEmployee=true&id=" . $employee_id);
        exit;
    }

    $_SESSION['toast_icon'] = '❌';
    $_SESSION['toast_msg'] = 'Profile save failed: ' . $stmt->error;
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

if ($is_edit) {
    $edit_id = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare("
        SELECT e.*
        FROM employees e
        WHERE e.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        $_SESSION['toast_icon'] = '❌';
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
        'status' => ucfirst($row['status']),
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
    ]);
}

$dept_colors = [
    'Medical'        => ['bg' => '#EDE9FE', 'tc' => '#7C3AED'],
    'Nursing'        => ['bg' => '#D1FAE5', 'tc' => '#059669'],
    'Reception'      => ['bg' => '#DBEAFE', 'tc' => '#2563EB'],
    'Lab Tech'       => ['bg' => '#FFEDD5', 'tc' => '#EA580C'],
    'Administration' => ['bg' => '#FEE2E2', 'tc' => '#DC2626'],
    'Accounts'       => ['bg' => '#FEF3C7', 'tc' => '#D97706'],
    'Human Resource' => ['bg' => '#DCFCE7', 'tc' => '#15803D'],
    'Information Technology' => ['bg' => '#E0F2FE', 'tc' => '#0369A1'],
    'Housekeeping'   => ['bg' => '#F3F4F6', 'tc' => '#374151'],
    'Security'       => ['bg' => '#FDF4FF', 'tc' => '#9333EA'],
];

$first_name_value = $emp['first_name'];
$last_name_value  = $emp['last_name'];

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:10px">
        <a href="employees" class="btn" style="padding:6px 10px;text-decoration:none">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6" />
            </svg>
        </a>
        <div>
            <h1 class="page-title"><?= $is_edit ? 'Edit Employee' : 'Add New Employee' ?></h1>
            <p class="page-sub">
                <?php if ($is_edit): ?>
                    Editing: <strong><?= esc($emp['name'] ?: 'Employee #' . $emp['id']) ?></strong> &middot;
                    <?= esc($emp['employee_code']) ?>
                <?php else: ?>
                    Fill in all required fields to create an employee record
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php if ($is_edit): ?>
            <span class="badge" style="background:#FEF3C7;color:#92400E;font-size:12px;padding:5px 10px">✏ Edit Mode</span>
            <button class="btn" type="button" style="color:#DC2626;border-color:#FEE2E2;font-size:13px"
                onclick="confirmDelete(<?= (int)$emp['id'] ?>)">
                Delete
            </button>
        <?php else: ?>
            <span class="badge" style="background:#D1FAE5;color:#065F46;font-size:12px;padding:5px 10px">+ Add Mode</span>
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
        <form id="empForm" method="POST" action="" enctype="multipart/form-data" novalidate>
            <?php if ($is_edit): ?>
                <input type="hidden" name="emp_id" value="<?= (int)$emp['id'] ?>">
            <?php endif; ?>

            <div class="form-section active" id="section-1">
                <div class="form-block">
                    <div class="form-block-header">
                        <div class="form-block-icon" style="background:#EDE9FE">🖼</div>
                        <div>
                            <h3>Profile Photo</h3>
                            <p>JPG or PNG, max 2 MB</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
                            <div id="photoCircle" style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#6D28D9,#2563EB);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;flex-shrink:0;border:3px solid #E5E7EB;overflow:hidden;position:relative">
                                <span id="photoInitials"><?= esc(initials_name($emp['name'])) ?></span>
                                <img id="photoPreviewImg" src="" alt="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
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
                        <div class="form-block-icon" style="background:#EDE9FE">👤</div>
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
                                <input type="text" name="first_name" id="fFirstName" value="<?= esc($first_name_value) ?>" placeholder="e.g. Anjali" oninput="updatePreview();liveValidate(this)" required>
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
                        <div class="form-block-icon" style="background:#D1FAE5">☎</div>
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
                        <div class="form-block-icon" style="background:#DBEAFE">🪪</div>
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
                        <div class="form-block-icon" style="background:#DBEAFE">💼</div>
                        <div>
                            <h3>Role Information</h3>
                            <p>Department, designation, employee type</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>EMPLOYEE ID</label>
                                <input type="text" name="emp_code" id="fEmpCode" value="<?= esc($is_edit ? $emp['employee_code'] : 'EMP-' . str_pad((string)(count($employees) + 1), 3, '0', STR_PAD_LEFT)) ?>" oninput="updatePreview()">
                            </div>
                            <div class="fg">
                                <label>DEPARTMENT <span class="req">*</span></label>
                                <select name="dept" id="fDept" onchange="updatePreview()" required>
                                    <option value="">Select Department</option>
                                    <?php foreach (array_keys($dept_colors) as $d): ?>
                                        <option value="<?= esc($d) ?>" <?= sel($emp['dept'], $d) ?>><?= esc($d) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>DESIGNATION <span class="req">*</span></label>
                                <input type="text" name="desig" id="fDesig" value="<?= esc($emp['desig']) ?>" placeholder="e.g. Sr. Nurse" oninput="updatePreview()" required>
                            </div>
                            <div class="fg">
                                <label>EMPLOYEE TYPE</label>
                                <select name="emp_type" onchange="updatePreview()">
                                    <?php foreach (['Permanent','Contract','Part-Time','Intern','Consultant'] as $t): ?>
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
                                <input type="text" name="location" value="<?= esc($emp['location']) ?>">
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
                        <div class="form-block-icon" style="background:#FEF3C7">📅</div>
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
                        <div class="form-block-icon" style="background:#EDE9FE">🎓</div>
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
                        <div class="form-block-icon" style="background:#D1FAE5">₹</div>
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
                        <div class="form-block-icon" style="background:#D1FAE5">🏦</div>
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
                        <div class="form-block-icon" style="background:#FFEDD5">📂</div>
                        <div>
                            <h3>Document Uploads</h3>
                            <p>Click a row to upload the corresponding document</p>
                        </div>
                    </div>
                    <div class="form-block-body">
                        <?php
                        $docs = [
                            ['🪪', 'Aadhaar Card', 'aadhaar_doc', true],
                            ['💳', 'PAN Card', 'pan_doc', true],
                            ['📸', 'Passport-size Photo', 'photo_doc', true],
                            ['🎓', 'Educational Certificates', 'edu_doc', true],
                            ['🏦', 'Bank Passbook / Cheque Copy', 'bank_doc', true],
                            ['📝', 'Appointment Letter', 'appt_doc', false],
                        ];
                        foreach ($docs as [$icon, $label, $field, $req]):
                        ?>
                            <div class="doc-row" onclick="triggerDocUpload(this, '<?= esc($label) ?>')">
                                <input type="file" name="<?= esc($field) ?>" style="display:none" onchange="handleDocUpload(this, '<?= esc($label) ?>')">
                                <div class="doc-icon"><?= $icon ?></div>
                                <div style="flex:1">
                                    <div style="font-size:13px;font-weight:600;color:#111827"><?= esc($label) ?></div>
                                    <div style="font-size:11px;color:#9CA3AF;margin-top:1px"><?= $req ? 'Required' : 'Optional' ?> &middot; Click to upload</div>
                                </div>
                                <div><span class="badge" style="background:#F3F4F6;color:#6B7280">Upload</span></div>
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
                        <div class="form-block-icon" style="background:#FEE2E2">🆘</div>
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
                        <div class="form-block-icon" style="background:#F9FAFB">📝</div>
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
            <div class="epc-avatar" id="epcAvatar">
                <span id="epcInitials"><?= esc(initials_name($emp['name'])) ?></span>
                <img id="epcAvatarImg" src="" alt="">
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

<div id="empToastBox" style="display:none;position:fixed;right:22px;bottom:22px;background:#111827;color:#fff;padding:12px 18px;border-radius:10px;z-index:99999;box-shadow:0 8px 28px rgba(0,0,0,.2);font-size:13px;font-weight:600">
    <span id="empToastIcon">✅</span>
    <span id="empToastMsg">Done</span>
</div>

<script>
let currentSection = 1;
const totalSections = 6;

function showToast(icon, msg) {
    const box = document.getElementById('empToastBox');
    const ti = document.getElementById('empToastIcon');
    const tm = document.getElementById('empToastMsg');

    if (!box || !ti || !tm) return alert(msg);

    ti.textContent = icon;
    tm.textContent = msg;
    box.style.display = 'block';

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
        showToast('⚠', 'Please fill all required fields.');
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
    if (initEl) initEl.textContent = initials;
    if (photoInit) photoInit.textContent = initials;
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

function triggerDocUpload(row, label) {
    const input = row.querySelector('input[type=file]');
    if (input) input.click();
}

function handleDocUpload(input, label) {
    if (input.files && input.files.length) {
        const row = input.closest('.doc-row');
        if (row) row.classList.add('uploaded');
        showToast('✅', label + ' selected.');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateProgress();
    updatePreview();
    calcSalary();

    <?php if ($toast_msg): ?>
    showToast(<?= json_encode($toast_icon) ?>, <?= json_encode($toast_msg) ?>);
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