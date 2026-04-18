<?php
require_once 'includes/config.php';

$page_title = 'Edit Profile';

/* =========================
   USER DATA (SESSION / DB FALLBACK)
========================= */
$user = [
    'id'          => $_SESSION['user_id'] ?? 1,
    'name'        => $_SESSION['username'] ?? 'Abhi Mondal',
    'email'       => $_SESSION['email'] ?? 'abhi@example.com',
    'phone'       => $_SESSION['phone'] ?? '+91 9876543210',
    'role'        => $_SESSION['role'] ?? 'Administrator',
    'department'  => $_SESSION['department'] ?? 'Administration',
    'designation' => $_SESSION['designation'] ?? 'HR Manager',
    'location'    => $_SESSION['location'] ?? 'Ramkrishna IVF Centre, Siliguri',
    'join_date'   => $_SESSION['join_date'] ?? '2024-01-15',
    'dob'         => $_SESSION['dob'] ?? '1998-08-20',
    'gender'      => $_SESSION['gender'] ?? 'Male',
    'address'     => $_SESSION['address'] ?? 'Siliguri, West Bengal',
    'photo'       => $_SESSION['photo'] ?? '',
    'about'       => $_SESSION['about'] ?? 'Experienced team member focused on HR operations, payroll process, employee onboarding, and staff management.',
];

/* =========================
   HELPERS
========================= */
function esc($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

if (!function_exists('initials')) {
    function initials(string $name): string {
        $parts = preg_split('/\s+/', trim($name));
        $first = strtoupper(substr($parts[0] ?? '', 0, 1));
        $last  = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
        return $first . ($last !== $first ? $last : '');
    }
}

$save_success = false;
$errors = [];

/* =========================
   HANDLE FORM SUBMIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user['name']        = trim($_POST['name'] ?? '');
    $user['email']       = trim($_POST['email'] ?? '');
    $user['phone']       = trim($_POST['phone'] ?? '');
    $user['designation'] = trim($_POST['designation'] ?? '');
    $user['department']  = trim($_POST['department'] ?? '');
    $user['location']    = trim($_POST['location'] ?? '');
    $user['dob']         = trim($_POST['dob'] ?? '');
    $user['gender']      = trim($_POST['gender'] ?? '');
    $user['address']     = trim($_POST['address'] ?? '');
    $user['about']       = trim($_POST['about'] ?? '');

    if ($user['name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($user['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($user['phone'] === '') {
        $errors[] = 'Phone number is required.';
    }

    if (empty($errors)) {
        /* =========================
           FILE UPLOAD (DEMO)
           Save under uploads/profile/
        ========================= */
        if (!empty($_FILES['photo']['name']) && isset($_FILES['photo']['tmp_name'])) {
            $uploadDir = __DIR__ . '/uploads/profile/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $fileName = $_FILES['photo']['name'];
            $tmpName  = $_FILES['photo']['tmp_name'];
            $fileSize = (int)($_FILES['photo']['size'] ?? 0);
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';
            } elseif ($fileSize > 2 * 1024 * 1024) {
                $errors[] = 'Profile image must be less than 2MB.';
            } else {
                $newName = 'profile_' . $user['id'] . '_' . time() . '.' . $ext;
                $target  = $uploadDir . $newName;

                if (move_uploaded_file($tmpName, $target)) {
                    $user['photo'] = 'uploads/profile/' . $newName;
                } else {
                    $errors[] = 'Image upload failed.';
                }
            }
        }

        if (empty($errors)) {
            /* =========================
               DEMO SESSION SAVE
               Replace with DB update query
            ========================= */
            $_SESSION['username']    = $user['name'];
            $_SESSION['email']       = $user['email'];
            $_SESSION['phone']       = $user['phone'];
            $_SESSION['designation'] = $user['designation'];
            $_SESSION['department']  = $user['department'];
            $_SESSION['location']    = $user['location'];
            $_SESSION['dob']         = $user['dob'];
            $_SESSION['gender']      = $user['gender'];
            $_SESSION['address']     = $user['address'];
            $_SESSION['about']       = $user['about'];
            $_SESSION['photo']       = $user['photo'];

            $save_success = true;
        }
    }
}

$profileInitials = initials($user['name']);

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<style>
.edit-profile-wrap{
    display:grid;
    grid-template-columns:320px 1fr;
    gap:20px;
    align-items:start;
}
.edit-sidebar,
.edit-main{
    background:#fff;
    border:1px solid #E5E7EB;
    border-radius:16px;
    overflow:hidden;
}
.sidebar-top{
    height:110px;
    background:linear-gradient(135deg,#6D28D9 0%,#2563EB 100%);
}
.sidebar-body{
    padding:0 22px 22px;
}
.avatar-wrap{
    margin-top:-42px;
}
.avatar{
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
}
.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}
.sidebar-name{
    font-size:20px;
    font-weight:700;
    color:#111827;
    margin-top:12px;
}
.sidebar-role{
    font-size:13px;
    color:#6B7280;
    margin-top:4px;
}
.sidebar-chip{
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
.sidebar-meta{
    display:grid;
    gap:12px;
    margin-top:18px;
}
.sidebar-meta-item{
    display:flex;
    align-items:flex-start;
    gap:10px;
}
.sidebar-meta-icon{
    width:30px;
    height:30px;
    border-radius:8px;
    background:#F3F4F6;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
}
.sidebar-meta-label{
    font-size:11px;
    font-weight:700;
    color:#9CA3AF;
    text-transform:uppercase;
    letter-spacing:.4px;
}
.sidebar-meta-value{
    font-size:13px;
    font-weight:600;
    color:#111827;
    margin-top:2px;
    word-break:break-word;
}
.edit-main-head{
    padding:18px 20px;
    border-bottom:1px solid #F3F4F6;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}
.edit-main-head h2{
    margin:0;
    font-size:16px;
    font-weight:700;
    color:#111827;
}
.edit-main-head p{
    margin:4px 0 0;
    font-size:12px;
    color:#9CA3AF;
}
.edit-main-body{
    padding:20px;
}
.form-section{
    border:1px solid #E5E7EB;
    border-radius:14px;
    overflow:hidden;
    margin-bottom:18px;
}
.form-section:last-child{
    margin-bottom:0;
}
.form-section-head{
    padding:14px 16px;
    border-bottom:1px solid #F3F4F6;
    display:flex;
    align-items:center;
    gap:10px;
}
.form-section-icon{
    width:32px;
    height:32px;
    border-radius:9px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#EDE9FE;
    flex-shrink:0;
}
.form-section-head h3{
    margin:0;
    font-size:14px;
    font-weight:700;
    color:#111827;
}
.form-section-head p{
    margin:2px 0 0;
    font-size:11.5px;
    color:#9CA3AF;
}
.form-section-body{
    padding:16px;
}
.fg-row{
    display:grid;
    gap:14px;
    margin-bottom:14px;
}
.fg-row:last-child{
    margin-bottom:0;
}
.col-1{ grid-template-columns:1fr; }
.col-2{ grid-template-columns:1fr 1fr; }
.col-3{ grid-template-columns:1fr 1fr 1fr; }

.fg{
    display:flex;
    flex-direction:column;
    gap:5px;
}
.fg label{
    font-size:11px;
    font-weight:700;
    color:#6B7280;
    letter-spacing:.4px;
    text-transform:uppercase;
}
.fg label .req{
    color:#DC2626;
}
.fg input,
.fg select,
.fg textarea{
    width:100%;
    padding:10px 12px;
    border:1.5px solid #E5E7EB;
    border-radius:10px;
    font-size:13.5px;
    color:#111827;
    outline:none;
    transition:border-color .15s, box-shadow .15s;
    background:#fff;
    font-family:inherit;
}
.fg input:focus,
.fg select:focus,
.fg textarea:focus{
    border-color:#6D28D9;
    box-shadow:0 0 0 3px rgba(109,40,217,.08);
}
.field-hint{
    font-size:11px;
    color:#9CA3AF;
}
.photo-upload{
    display:flex;
    align-items:center;
    gap:18px;
    flex-wrap:wrap;
}
.photo-preview{
    width:80px;
    height:80px;
    border-radius:50%;
    overflow:hidden;
    background:linear-gradient(135deg,#6D28D9,#2563EB);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:24px;
    font-weight:700;
    color:#fff;
    border:3px solid #E5E7EB;
    position:relative;
}
.photo-preview img{
    width:100%;
    height:100%;
    object-fit:cover;
    position:absolute;
    inset:0;
    display:none;
}
.photo-zone{
    flex:1;
    min-width:220px;
    border:2px dashed #E5E7EB;
    border-radius:12px;
    padding:18px;
    background:#FAFAFA;
    cursor:pointer;
    text-align:center;
    transition:.15s;
}
.photo-zone:hover{
    border-color:#6D28D9;
    background:#F5F3FF;
}
.photo-zone input{
    display:none;
}
.top-actions{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:20px;
    flex-wrap:wrap;
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
.btn-row{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:10px;
    margin-top:18px;
}
@media (max-width:960px){
    .edit-profile-wrap{
        grid-template-columns:1fr;
    }
}
@media (max-width:640px){
    .col-2,
    .col-3{
        grid-template-columns:1fr;
    }
}
</style>

<?php if ($save_success): ?>
<div class="success-banner">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>
    Profile updated successfully!
    <a href="ViewProfile" style="margin-left:auto;font-size:12px;color:#059669;text-decoration:underline">View Profile</a>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="error-banner">
    <strong>Please fix the following errors:</strong>
    <ul style="margin:6px 0 0 16px">
        <?php foreach ($errors as $err): ?>
            <li><?= esc($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="top-actions">
    <div>
        <h1 class="page-title">Edit Profile</h1>
        <p class="page-sub">Update your personal information and profile photo</p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="ViewProfile" class="btn" style="text-decoration:none">← Back to Profile</a>
    </div>
</div>

<div class="edit-profile-wrap">

    <!-- LEFT -->
    <div class="edit-sidebar">
        <div class="sidebar-top"></div>
        <div class="sidebar-body">
            <div class="avatar-wrap">
                <div class="avatar" id="sideAvatar">
                    <span id="sideAvatarInitials"><?= esc($profileInitials ?: 'U') ?></span>
                    <?php if (!empty($user['photo'])): ?>
                        <img src="<?= esc($user['photo']) ?>" id="sideAvatarImg" alt="<?= esc($user['name']) ?>" style="display:block;">
                    <?php else: ?>
                        <img src="" id="sideAvatarImg" alt="" style="display:none;">
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar-name" id="previewName"><?= esc($user['name']) ?></div>
            <div class="sidebar-role" id="previewRole"><?= esc($user['designation']) ?> · <?= esc($user['department']) ?></div>

            <div class="sidebar-chip">
                <span>●</span>
                <?= esc($user['role']) ?>
            </div>

            <div class="sidebar-meta">
                <div class="sidebar-meta-item">
                    <div class="sidebar-meta-icon">📧</div>
                    <div>
                        <div class="sidebar-meta-label">Email</div>
                        <div class="sidebar-meta-value" id="previewEmail"><?= esc($user['email']) ?></div>
                    </div>
                </div>

                <div class="sidebar-meta-item">
                    <div class="sidebar-meta-icon">📱</div>
                    <div>
                        <div class="sidebar-meta-label">Phone</div>
                        <div class="sidebar-meta-value" id="previewPhone"><?= esc($user['phone']) ?></div>
                    </div>
                </div>

                <div class="sidebar-meta-item">
                    <div class="sidebar-meta-icon">📍</div>
                    <div>
                        <div class="sidebar-meta-label">Location</div>
                        <div class="sidebar-meta-value" id="previewLocation"><?= esc($user['location']) ?></div>
                    </div>
                </div>

                <div class="sidebar-meta-item">
                    <div class="sidebar-meta-icon">🗓</div>
                    <div>
                        <div class="sidebar-meta-label">Joined</div>
                        <div class="sidebar-meta-value"><?= esc(date('d M Y', strtotime($user['join_date']))) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT -->
    <div class="edit-main">
        <div class="edit-main-head">
            <div>
                <h2>Edit Profile Details</h2>
                <p>Keep your information up to date</p>
            </div>
        </div>

        <div class="edit-main-body">
            <form method="POST" enctype="multipart/form-data" id="profileForm">

                <div class="form-section">
                    <div class="form-section-head">
                        <div class="form-section-icon">🖼</div>
                        <div>
                            <h3>Profile Photo</h3>
                            <p>Upload JPG, PNG, or WEBP image up to 2MB</p>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="photo-upload">
                            <div class="photo-preview" id="mainPhotoPreview">
                                <span id="mainPhotoInitials"><?= esc($profileInitials ?: 'U') ?></span>
                                <?php if (!empty($user['photo'])): ?>
                                    <img src="<?= esc($user['photo']) ?>" id="mainPhotoImg" alt="<?= esc($user['name']) ?>" style="display:block;">
                                <?php else: ?>
                                    <img src="" id="mainPhotoImg" alt="" style="display:none;">
                                <?php endif; ?>
                            </div>

                            <label class="photo-zone" for="photoInput">
                                <input type="file" id="photoInput" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <div style="font-size:14px;font-weight:700;color:#374151">Click to upload profile photo</div>
                                <div style="font-size:11px;color:#9CA3AF;margin-top:4px">JPG, PNG, WEBP · Max 2MB</div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <div class="form-section-icon" style="background:#EDE9FE">👤</div>
                        <div>
                            <h3>Personal Information</h3>
                            <p>Basic personal details</p>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="fg-row col-2">
                            <div class="fg">
                                <label>Full Name <span class="req">*</span></label>
                                <input type="text" name="name" id="nameInput" value="<?= esc($user['name']) ?>" required>
                            </div>
                            <div class="fg">
                                <label>Email <span class="req">*</span></label>
                                <input type="email" name="email" id="emailInput" value="<?= esc($user['email']) ?>" required>
                            </div>
                        </div>

                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>Phone <span class="req">*</span></label>
                                <input type="text" name="phone" id="phoneInput" value="<?= esc($user['phone']) ?>" required>
                            </div>
                            <div class="fg">
                                <label>Date of Birth</label>
                                <input type="date" name="dob" value="<?= esc($user['dob']) ?>">
                            </div>
                            <div class="fg">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">Select</option>
                                    <option value="Male" <?= $user['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $user['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $user['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="fg-row col-1">
                            <div class="fg">
                                <label>Address</label>
                                <textarea name="address" rows="3"><?= esc($user['address']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <div class="form-section-icon" style="background:#DBEAFE">💼</div>
                        <div>
                            <h3>Work Information</h3>
                            <p>Role and office details</p>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="fg-row col-3">
                            <div class="fg">
                                <label>Designation</label>
                                <input type="text" name="designation" id="designationInput" value="<?= esc($user['designation']) ?>">
                            </div>
                            <div class="fg">
                                <label>Department</label>
                                <input type="text" name="department" id="departmentInput" value="<?= esc($user['department']) ?>">
                            </div>
                            <div class="fg">
                                <label>Location</label>
                                <input type="text" name="location" id="locationInput" value="<?= esc($user['location']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <div class="form-section-icon" style="background:#D1FAE5">📝</div>
                        <div>
                            <h3>About</h3>
                            <p>Short summary about yourself</p>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="fg-row col-1">
                            <div class="fg">
                                <label>Profile Summary</label>
                                <textarea name="about" rows="4"><?= esc($user['about']) ?></textarea>
                                <span class="field-hint">This can be shown on your profile page.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="btn-row">
                    <a href="ViewProfile" class="btn" style="text-decoration:none">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
function updateProfilePreview() {
    const name = document.getElementById('nameInput')?.value?.trim() || 'User';
    const email = document.getElementById('emailInput')?.value?.trim() || '—';
    const phone = document.getElementById('phoneInput')?.value?.trim() || '—';
    const designation = document.getElementById('designationInput')?.value?.trim() || '—';
    const department = document.getElementById('departmentInput')?.value?.trim() || '—';
    const location = document.getElementById('locationInput')?.value?.trim() || '—';

    const parts = name.split(' ').filter(Boolean);
    const initials = ((parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1]?.[0] || '' : '')).toUpperCase() || 'U';

    const previewName = document.getElementById('previewName');
    const previewRole = document.getElementById('previewRole');
    const previewEmail = document.getElementById('previewEmail');
    const previewPhone = document.getElementById('previewPhone');
    const previewLocation = document.getElementById('previewLocation');
    const mainPhotoInitials = document.getElementById('mainPhotoInitials');
    const sideAvatarInitials = document.getElementById('sideAvatarInitials');

    if (previewName) previewName.textContent = name;
    if (previewRole) previewRole.textContent = designation + ' · ' + department;
    if (previewEmail) previewEmail.textContent = email;
    if (previewPhone) previewPhone.textContent = phone;
    if (previewLocation) previewLocation.textContent = location;
    if (mainPhotoInitials) mainPhotoInitials.textContent = initials;
    if (sideAvatarInitials) sideAvatarInitials.textContent = initials;
}

function previewProfilePhoto(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        alert('Only JPG, PNG, and WEBP images are allowed.');
        event.target.value = '';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert('Image must be less than 2MB.');
        event.target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const url = e.target.result;

        const mainImg = document.getElementById('mainPhotoImg');
        const sideImg = document.getElementById('sideAvatarImg');
        const mainInitials = document.getElementById('mainPhotoInitials');
        const sideInitials = document.getElementById('sideAvatarInitials');

        if (mainImg) {
            mainImg.src = url;
            mainImg.style.display = 'block';
        }
        if (sideImg) {
            sideImg.src = url;
            sideImg.style.display = 'block';
        }
        if (mainInitials) mainInitials.style.display = 'none';
        if (sideInitials) sideInitials.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

document.addEventListener('DOMContentLoaded', function() {
    updateProfilePreview();

    ['nameInput','emailInput','phoneInput','designationInput','departmentInput','locationInput'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateProfilePreview);
    });

    const photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', previewProfilePhoto);
    }

    const form = document.getElementById('profileForm');
    if (form) {
        form.addEventListener('submit', function() {
            const btn = document.getElementById('saveBtn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Saving...';
            }
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>