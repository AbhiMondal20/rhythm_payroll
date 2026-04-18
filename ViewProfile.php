<?php
require_once 'includes/config.php';

$page_title = 'My Profile';

/* =========================
   HELPERS
========================= */
function esc($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

if (!function_exists('profile_initials')) {
    function profile_initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = strtoupper(substr($parts[0] ?? '', 0, 1));
        $last  = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
        return $first . (($last && $last !== $first) ? $last : '');
    }
}

/* =========================
   CURRENT USER ID
========================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo "<script>window.location.href='index';</script>";
    exit;
}

$userId = 0;

if (!empty($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
} elseif (!empty($_SESSION['userid'])) {
    $userId = (int)$_SESSION['userid'];
} elseif (!empty($_SESSION['id'])) {
    $userId = (int)$_SESSION['id'];
}

if ($userId <= 0) {
    echo "<script>alert('Invalid session. Please login again.'); window.location.href='index';</script>";
    exit;
}
/* =========================
   SAVE PROFILE
   Adjust table/column names if needed
========================= */
$save_success = false;
$save_errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $dob         = trim($_POST['dob'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $about       = trim($_POST['about'] ?? '');
    $photoPath   = trim($_POST['existing_photo'] ?? '');

    if ($name === '') {
        $save_errors[] = 'Name is required.';
    }
    if ($email === '') {
        $save_errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $save_errors[] = 'Please enter a valid email address.';
    }
    if ($phone === '') {
        $save_errors[] = 'Phone number is required.';
    }

    /* Photo upload */
    if (empty($save_errors) && isset($_FILES['photo']) && !empty($_FILES['photo']['name'])) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $fileName   = $_FILES['photo']['name'];
        $fileTmp    = $_FILES['photo']['tmp_name'];
        $fileSize   = (int)($_FILES['photo']['size'] ?? 0);
        $fileErr    = (int)($_FILES['photo']['error'] ?? 0);
        $ext        = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileErr !== 0) {
            $save_errors[] = 'Photo upload failed.';
        } elseif (!in_array($ext, $allowedExt, true)) {
            $save_errors[] = 'Only JPG, PNG, or WEBP images are allowed.';
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $save_errors[] = 'Photo size must be less than 2 MB.';
        } else {
            $uploadDir = __DIR__ . '/uploads/profile/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $newName = 'profile_' . $userId . '_' . time() . '.' . $ext;
            $destAbs = $uploadDir . $newName;
            $destRel = 'uploads/profile/' . $newName;

            if (move_uploaded_file($fileTmp, $destAbs)) {
                $photoPath = $destRel;
            } else {
                $save_errors[] = 'Unable to save uploaded photo.';
            }
        }
    }

    if (empty($save_errors)) {
        /**
         * Adjust this query if your users table/columns differ.
         * Expected columns:
         * users(id, username, email, phone, gender, dob, address, department, designation, location, about, photo)
         */
        $sql = "UPDATE users 
                SET username = ?, email = ?, phone = ?, gender = ?, dob = ?, address = ?, department = ?, designation = ?, location = ?, about = ?, photo = ?
                WHERE id = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssssssi",
                $name,
                $email,
                $phone,
                $gender,
                $dob,
                $address,
                $department,
                $designation,
                $location,
                $about,
                $photoPath,
                $userId
            );

            if (mysqli_stmt_execute($stmt)) {
                $save_success = true;

                $_SESSION['username']    = $name;
                $_SESSION['email']       = $email;
                $_SESSION['phone']       = $phone;
                $_SESSION['gender']      = $gender;
                $_SESSION['dob']         = $dob;
                $_SESSION['address']     = $address;
                $_SESSION['department']  = $department;
                $_SESSION['designation'] = $designation;
                $_SESSION['location']    = $location;
                $_SESSION['photo']       = $photoPath;
            } else {
                $save_errors[] = 'Database update failed: ' . mysqli_error($conn);
            }

            mysqli_stmt_close($stmt);
        } else {
            $save_errors[] = 'Unable to prepare update statement.';
        }
    }
}

/* =========================
   LOAD USER
   Adjust table/columns if needed
========================= */
$user = [
    'id'          => $userId,
    'name'        => $_SESSION['username'] ?? 'User',
    'email'       => $_SESSION['email'] ?? '',
    'phone'       => $_SESSION['phone'] ?? '',
    'role'        => $_SESSION['role'] ?? 'User',
    'department'  => $_SESSION['department'] ?? '',
    'designation' => $_SESSION['designation'] ?? '',
    'location'    => $_SESSION['location'] ?? '',
    'join_date'   => '',
    'dob'         => $_SESSION['dob'] ?? '',
    'gender'      => $_SESSION['gender'] ?? '',
    'address'     => $_SESSION['address'] ?? '',
    'photo'       => $_SESSION['photo'] ?? '',
    'about'       => $_SESSION['about'] ?? '',
];

$selectSql = "SELECT id, username, email, phone, role, department, designation, location, created_at, dob, gender, address, photo, about
              FROM users
              WHERE id = ?
              LIMIT 1";

if ($stmt = mysqli_prepare($conn, $selectSql)) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $user['id']          = (int)($row['id'] ?? $userId);
        $user['name']        = $row['username'] ?? $user['name'];
        $user['email']       = $row['email'] ?? '';
        $user['phone']       = $row['phone'] ?? '';
        $user['role']        = $row['role'] ?? $user['role'];
        $user['department']  = $row['department'] ?? '';
        $user['designation'] = $row['designation'] ?? '';
        $user['location']    = $row['location'] ?? '';
        $user['join_date']   = $row['created_at'] ?? '';
        $user['dob']         = $row['dob'] ?? '';
        $user['gender']      = $row['gender'] ?? '';
        $user['address']     = $row['address'] ?? '';
        $user['photo']       = $row['photo'] ?? '';
        $user['about']       = $row['about'] ?? '';
    }

    mysqli_stmt_close($stmt);
}

$profileInitials = profile_initials($user['name']);

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<style>
.profile-wrap{
    display:grid;
    grid-template-columns:320px 1fr;
    gap:20px;
    align-items:start;
}
.profile-sidebar,
.profile-main-card{
    background:#fff;
    border:1px solid #E5E7EB;
    border-radius:16px;
    overflow:hidden;
}
.profile-cover{
    height:110px;
    background:linear-gradient(135deg,#6D28D9 0%,#2563EB 100%);
    position:relative;
}
.profile-avatar-wrap{
    margin-top:-42px;
    padding:0 22px 18px;
}
.profile-avatar{
    width:84px;
    height:84px;
    border-radius:50%;
    border:4px solid #fff;
    background:linear-gradient(135deg,#6D28D9,#2563EB);
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:28px;
    font-weight:700;
    overflow:hidden;
    box-shadow:0 8px 24px rgba(0,0,0,.12);
    position:relative;
}
.profile-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}
.profile-name{
    font-size:20px;
    font-weight:700;
    color:#111827;
    margin-top:12px;
}
.profile-role{
    font-size:13px;
    color:#6B7280;
    margin-top:4px;
}
.profile-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:999px;
    background:#EDE9FE;
    color:#6D28D9;
    font-size:12px;
    font-weight:700;
    margin-top:12px;
}
.profile-meta{
    padding:0 22px 22px;
    display:grid;
    gap:12px;
}
.profile-meta-item{
    display:flex;
    align-items:flex-start;
    gap:10px;
    font-size:13px;
    color:#374151;
}
.profile-meta-icon{
    width:30px;
    height:30px;
    border-radius:8px;
    background:#F3F4F6;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
}
.profile-meta-label{
    font-size:11px;
    font-weight:700;
    color:#9CA3AF;
    text-transform:uppercase;
    letter-spacing:.4px;
}
.profile-meta-value{
    margin-top:2px;
    font-weight:600;
    color:#111827;
}
.profile-main-card-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:18px 20px;
    border-bottom:1px solid #F3F4F6;
}
.profile-main-card-header h2{
    margin:0;
    font-size:16px;
    font-weight:700;
    color:#111827;
}
.profile-main-card-header p{
    margin:4px 0 0;
    font-size:12px;
    color:#9CA3AF;
}
.profile-sections{
    padding:20px;
    display:grid;
    gap:18px;
}
.profile-section{
    border:1px solid #E5E7EB;
    border-radius:14px;
    overflow:hidden;
}
.profile-section-head{
    padding:14px 16px;
    border-bottom:1px solid #F3F4F6;
    display:flex;
    align-items:center;
    gap:10px;
}
.profile-section-icon{
    width:32px;
    height:32px;
    border-radius:9px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    background:#EDE9FE;
}
.profile-section-head h3{
    margin:0;
    font-size:14px;
    font-weight:700;
    color:#111827;
}
.profile-section-head p{
    margin:2px 0 0;
    font-size:11.5px;
    color:#9CA3AF;
}
.profile-section-body{
    padding:16px;
}
.profile-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}
.info-box{
    padding:12px 14px;
    border:1px solid #F3F4F6;
    border-radius:10px;
    background:#FAFAFA;
}
.info-label{
    font-size:10.5px;
    font-weight:700;
    color:#9CA3AF;
    text-transform:uppercase;
    letter-spacing:.4px;
}
.info-value{
    margin-top:6px;
    font-size:13.5px;
    font-weight:600;
    color:#111827;
    word-break:break-word;
}
.about-box{
    padding:14px;
    border-radius:12px;
    background:#F9FAFB;
    border:1px solid #F3F4F6;
    font-size:13.5px;
    line-height:1.7;
    color:#374151;
}
.stats-row{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
}
.stat-card{
    padding:14px;
    border-radius:12px;
    border:1px solid #E5E7EB;
    background:#fff;
}
.stat-label{
    font-size:11px;
    color:#9CA3AF;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.4px;
}
.stat-value{
    margin-top:8px;
    font-size:20px;
    font-weight:800;
    color:#111827;
}
.page-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:20px;
    gap:12px;
    flex-wrap:wrap;
}
.form-input,
.form-select,
.form-textarea{
    width:100%;
    padding:10px 12px;
    border:1.5px solid #E5E7EB;
    border-radius:10px;
    background:#fff;
    outline:none;
    font-size:13.5px;
    color:#111827;
    transition:.15s ease;
}
.form-input:focus,
.form-select:focus,
.form-textarea:focus{
    border-color:#6D28D9;
    box-shadow:0 0 0 3px rgba(109,40,217,.08);
}
.form-input[readonly],
.form-select:disabled,
.form-textarea[readonly]{
    background:#FAFAFA;
    color:#111827;
    cursor:default;
}
.hidden-btn{
    display:none;
}
.success-banner{
    background:#D1FAE5;
    border:1px solid #6EE7B7;
    border-radius:10px;
    padding:14px 16px;
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:20px;
    font-size:13.5px;
    color:#065F46;
    font-weight:600;
}
.error-banner{
    background:#FEE2E2;
    border:1px solid #FCA5A5;
    border-radius:10px;
    padding:14px 16px;
    margin-bottom:20px;
    font-size:13px;
    color:#991B1B;
}
.upload-photo-btn{
    display:none;
    margin-top:10px;
}
@media (max-width: 960px){
    .profile-wrap{
        grid-template-columns:1fr;
    }
}
@media (max-width: 640px){
    .profile-grid,
    .stats-row{
        grid-template-columns:1fr;
    }
}
</style>

<?php if ($save_success): ?>
<div class="success-banner">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    Profile updated successfully.
</div>
<?php endif; ?>

<?php if (!empty($save_errors)): ?>
<div class="error-banner">
    <strong>Please fix the following:</strong>
    <ul style="margin:6px 0 0 16px">
        <?php foreach ($save_errors as $err): ?>
            <li><?= esc($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="page-top">
    <div>
        <h1 class="page-title">My Profile</h1>
        <p class="page-sub">View and edit your personal and employment information</p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="Dashboard" class="btn" style="text-decoration:none">← Back</a>
        <button type="button" class="btn btn-primary" id="editBtn" onclick="enableEditMode()">Edit Profile</button>
        <button type="button" class="btn hidden-btn" id="cancelBtn" onclick="cancelEditMode()">Cancel</button>
        <button type="submit" form="profileForm" class="btn btn-primary hidden-btn" id="saveBtn">Save Changes</button>
    </div>
</div>

<form method="POST" id="profileForm" enctype="multipart/form-data">
    <input type="hidden" name="existing_photo" value="<?= esc($user['photo']) ?>">

    <div class="profile-wrap">

        <!-- LEFT -->
        <div class="profile-sidebar">
            <div class="profile-cover"></div>

            <div class="profile-avatar-wrap">
                <div class="profile-avatar" id="profileAvatar">
                    <?php if (!empty($user['photo'])): ?>
                        <img src="<?= esc($user['photo']) ?>" alt="<?= esc($user['name']) ?>" id="profilePreviewImg">
                    <?php else: ?>
                        <span id="profileInitials"><?= esc($profileInitials ?: 'U') ?></span>
                        <img src="" alt="<?= esc($user['name']) ?>" id="profilePreviewImg" style="display:none;">
                    <?php endif; ?>
                </div>

                <label class="btn upload-photo-btn" id="photoUploadBtn" for="photoInput" style="text-decoration:none;cursor:pointer">
                    Change Photo
                </label>
                <input type="file" name="photo" id="photoInput" accept="image/*" style="display:none" onchange="previewProfilePhoto(event)">

                <div class="profile-name" id="sidebarName"><?= esc($user['name']) ?></div>
                <div class="profile-role" id="sidebarRole"><?= esc($user['designation']) ?><?= !empty($user['department']) ? ' · ' . esc($user['department']) : '' ?></div>

                <div class="profile-badge">
                    <span>●</span>
                    <?= esc($user['role']) ?>
                </div>
            </div>

            <div class="profile-meta">
                <div class="profile-meta-item">
                    <div class="profile-meta-icon">📧</div>
                    <div>
                        <div class="profile-meta-label">Email</div>
                        <div class="profile-meta-value" id="sidebarEmail"><?= esc($user['email']) ?></div>
                    </div>
                </div>

                <div class="profile-meta-item">
                    <div class="profile-meta-icon">📱</div>
                    <div>
                        <div class="profile-meta-label">Phone</div>
                        <div class="profile-meta-value" id="sidebarPhone"><?= esc($user['phone']) ?></div>
                    </div>
                </div>

                <div class="profile-meta-item">
                    <div class="profile-meta-icon">📍</div>
                    <div>
                        <div class="profile-meta-label">Location</div>
                        <div class="profile-meta-value" id="sidebarLocation"><?= esc($user['location']) ?></div>
                    </div>
                </div>

                <div class="profile-meta-item">
                    <div class="profile-meta-icon">🗓</div>
                    <div>
                        <div class="profile-meta-label">Joined</div>
                        <div class="profile-meta-value">
                            <?= !empty($user['join_date']) ? esc(date('d M Y', strtotime($user['join_date']))) : '—' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="profile-main-card">
            <div class="profile-main-card-header">
                <div>
                    <h2>Profile Overview</h2>
                    <p>Personal, role, and employment information in one place</p>
                </div>
            </div>

            <div class="profile-sections">

                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-label">Employee ID</div>
                        <div class="stat-value">EMP-<?= str_pad((string)$user['id'], 3, '0', STR_PAD_LEFT) ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Department</div>
                        <div class="stat-value" style="font-size:18px" id="topDepartment"><?= esc($user['department'] ?: '—') ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Role Access</div>
                        <div class="stat-value" style="font-size:18px"><?= esc($user['role']) ?></div>
                    </div>
                </div>

                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-icon">👤</div>
                        <div>
                            <h3>Personal Information</h3>
                            <p>Basic account and contact details</p>
                        </div>
                    </div>
                    <div class="profile-section-body">
                        <div class="profile-grid">
                            <div class="info-box">
                                <div class="info-label">Full Name</div>
                                <div class="info-value">
                                    <input type="text" class="form-input editable-field" name="name" id="nameField" value="<?= esc($user['name']) ?>" readonly oninput="syncProfilePreview()">
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Email Address</div>
                                <div class="info-value">
                                    <input type="email" class="form-input editable-field" name="email" id="emailField" value="<?= esc($user['email']) ?>" readonly oninput="syncProfilePreview()">
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value">
                                    <input type="text" class="form-input editable-field" name="phone" id="phoneField" value="<?= esc($user['phone']) ?>" readonly oninput="syncProfilePreview()">
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Gender</div>
                                <div class="info-value">
                                    <select class="form-select editable-field" name="gender" id="genderField" disabled>
                                        <option value="">Select</option>
                                        <option value="Male" <?= $user['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= $user['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= $user['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Date of Birth</div>
                                <div class="info-value">
                                    <input type="date" class="form-input editable-field" name="dob" value="<?= esc($user['dob']) ?>" readonly>
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Address</div>
                                <div class="info-value">
                                    <textarea class="form-textarea editable-field" name="address" rows="3" readonly><?= esc($user['address']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-icon" style="background:#DBEAFE">💼</div>
                        <div>
                            <h3>Employment Details</h3>
                            <p>Department, designation, and work information</p>
                        </div>
                    </div>
                    <div class="profile-section-body">
                        <div class="profile-grid">
                            <div class="info-box">
                                <div class="info-label">Role</div>
                                <div class="info-value"><?= esc($user['role']) ?></div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Department</div>
                                <div class="info-value">
                                    <input type="text" class="form-input editable-field" name="department" id="departmentField" value="<?= esc($user['department']) ?>" readonly oninput="syncProfilePreview()">
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Designation</div>
                                <div class="info-value">
                                    <input type="text" class="form-input editable-field" name="designation" id="designationField" value="<?= esc($user['designation']) ?>" readonly oninput="syncProfilePreview()">
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Work Location</div>
                                <div class="info-value">
                                    <input type="text" class="form-input editable-field" name="location" id="locationField" value="<?= esc($user['location']) ?>" readonly oninput="syncProfilePreview()">
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Joining Date</div>
                                <div class="info-value">
                                    <?= !empty($user['join_date']) ? esc(date('d M Y', strtotime($user['join_date']))) : '—' ?>
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Employee Code</div>
                                <div class="info-value">
                                    EMP-<?= str_pad((string)$user['id'], 3, '0', STR_PAD_LEFT) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-icon" style="background:#D1FAE5">📝</div>
                        <div>
                            <h3>About</h3>
                            <p>Short profile summary</p>
                        </div>
                    </div>
                    <div class="profile-section-body">
                        <div class="about-box">
                            <textarea class="form-textarea editable-field" name="about" id="aboutField" rows="5" readonly><?= esc($user['about']) ?></textarea>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</form>

<script>
let editMode = false;
let initialFormData = {};

function collectInitialData() {
    const form = document.getElementById('profileForm');
    if (!form) return;

    initialFormData = {};
    form.querySelectorAll('input, textarea, select').forEach(el => {
        if (el.type === 'file') return;
        initialFormData[el.name] = el.value;
    });
}

function enableEditMode() {
    editMode = true;

    document.querySelectorAll('.editable-field').forEach(el => {
        if (el.tagName === 'SELECT') {
            el.disabled = false;
        } else {
            el.readOnly = false;
        }
    });

    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const saveBtn = document.getElementById('saveBtn');
    const photoUploadBtn = document.getElementById('photoUploadBtn');

    if (editBtn) editBtn.classList.add('hidden-btn');
    if (cancelBtn) cancelBtn.classList.remove('hidden-btn');
    if (saveBtn) saveBtn.classList.remove('hidden-btn');
    if (photoUploadBtn) photoUploadBtn.style.display = 'inline-flex';
}

function cancelEditMode() {
    const form = document.getElementById('profileForm');
    if (!form) return;

    Object.keys(initialFormData).forEach(name => {
        const el = form.querySelector('[name="' + CSS.escape(name) + '"]');
        if (el) el.value = initialFormData[name];
    });

    const fileInput = document.getElementById('photoInput');
    if (fileInput) fileInput.value = '';

    document.querySelectorAll('.editable-field').forEach(el => {
        if (el.tagName === 'SELECT') {
            el.disabled = true;
        } else {
            el.readOnly = true;
        }
    });

    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const saveBtn = document.getElementById('saveBtn');
    const photoUploadBtn = document.getElementById('photoUploadBtn');

    if (editBtn) editBtn.classList.remove('hidden-btn');
    if (cancelBtn) cancelBtn.classList.add('hidden-btn');
    if (saveBtn) saveBtn.classList.add('hidden-btn');
    if (photoUploadBtn) photoUploadBtn.style.display = 'none';

    editMode = false;
    syncProfilePreview(true);
    resetPhotoPreview();
}

function resetPhotoPreview() {
    const originalPhoto = <?= json_encode($user['photo']) ?>;
    const initials = <?= json_encode($profileInitials ?: 'U') ?>;

    const img = document.getElementById('profilePreviewImg');
    const initialsEl = document.getElementById('profileInitials');

    if (!img) return;

    if (originalPhoto) {
        img.src = originalPhoto;
        img.style.display = 'block';
        if (initialsEl) initialsEl.style.display = 'none';
    } else {
        img.src = '';
        img.style.display = 'none';
        if (initialsEl) {
            initialsEl.textContent = initials;
            initialsEl.style.display = 'inline';
        }
    }
}

function makeInitials(name) {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    const first = parts[0] ? parts[0][0] : '';
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return (first + (last && last !== first ? last : '')).toUpperCase() || 'U';
}

function syncProfilePreview(forceOriginal = false) {
    const nameField = document.getElementById('nameField');
    const emailField = document.getElementById('emailField');
    const phoneField = document.getElementById('phoneField');
    const departmentField = document.getElementById('departmentField');
    const designationField = document.getElementById('designationField');
    const locationField = document.getElementById('locationField');

    const sidebarName = document.getElementById('sidebarName');
    const sidebarEmail = document.getElementById('sidebarEmail');
    const sidebarPhone = document.getElementById('sidebarPhone');
    const sidebarLocation = document.getElementById('sidebarLocation');
    const sidebarRole = document.getElementById('sidebarRole');
    const topDepartment = document.getElementById('topDepartment');
    const initialsEl = document.getElementById('profileInitials');

    const name = nameField ? nameField.value.trim() : '';
    const email = emailField ? emailField.value.trim() : '';
    const phone = phoneField ? phoneField.value.trim() : '';
    const department = departmentField ? departmentField.value.trim() : '';
    const designation = designationField ? designationField.value.trim() : '';
    const location = locationField ? locationField.value.trim() : '';

    if (sidebarName) sidebarName.textContent = name || 'User';
    if (sidebarEmail) sidebarEmail.textContent = email || '—';
    if (sidebarPhone) sidebarPhone.textContent = phone || '—';
    if (sidebarLocation) sidebarLocation.textContent = location || '—';
    if (sidebarRole) sidebarRole.textContent = (designation || '—') + (department ? ' · ' + department : '');
    if (topDepartment) topDepartment.textContent = department || '—';

    if (!forceOriginal && initialsEl && initialsEl.style.display !== 'none') {
        initialsEl.textContent = makeInitials(name);
    }
}

function previewProfilePhoto(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        alert('Only JPG, PNG, or WEBP images are allowed.');
        event.target.value = '';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert('Image size must be less than 2 MB.');
        event.target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const url = e.target.result;
        const img = document.getElementById('profilePreviewImg');
        const initialsEl = document.getElementById('profileInitials');

        if (img) {
            img.src = url;
            img.style.display = 'block';
        }
        if (initialsEl) {
            initialsEl.style.display = 'none';
        }
    };
    reader.readAsDataURL(file);
}

document.addEventListener('DOMContentLoaded', function() {
    collectInitialData();
    syncProfilePreview();
});
</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>