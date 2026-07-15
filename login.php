<?php
session_start();
require_once 'includes/db_conn.php';

$error = '';
$success = '';

if (!isset($master) || !($master instanceof mysqli)) {
    $error = "Master database connection not found.";
}

function hasAccessLocal(string $key): bool {
    $access = $_SESSION['user_access'] ?? [];
    return in_array(strtolower($key), $access, true);
}

function getRedirectPageLocal(): string {
    $access = $_SESSION['user_access'] ?? [];

    if (in_array('dashboard', $access, true)) {
        return 'http://192.168.2.161/rhythm_payroll/dashboard';
    }

    if (!empty($access[0])) {
        return 'http://192.168.2.161/rhythm_payroll/dashboard' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $access[0]);
    }

    return 'http://192.168.2.161/rhythm_payroll/dashboard';
}

if (!empty($_SESSION['login'])) {
    header("Location: " . getRedirectPageLocal());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $client_code = strtolower(trim($_POST['client_code'] ?? ''));
    $module_key  = strtolower(trim($_POST['module_key'] ?? 'payroll'));
    $username    = trim($_POST['username'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    if ($client_code === '') {
        $error = "Please enter Client Code.";
    } elseif ($username === '') {
        $error = "Please enter Username or Email.";
    } elseif ($password === '') {
        $error = "Please enter Password.";
    } elseif (!$master) {
        $error = "Master database not connected.";
    } else {

        // Sanitize inputs for master DB
        $client_code_esc = mysqli_real_escape_string($master, $client_code);
        $module_key_esc  = mysqli_real_escape_string($master, $module_key);

        $dbQuery = "
            SELECT 
                id, client_id, client_code, module_key, db_host, 
                db_name, db_user, db_pass, port, status
            FROM client_databases
            WHERE client_code = '$client_code_esc'
              AND module_key = '$module_key_esc'
              AND status = 'active'
            LIMIT 1
        ";

        $dbRes = mysqli_query($master, $dbQuery);

        if (!$dbRes) {
            $error = "Master query failed: " . mysqli_error($master);
        } else {
            $db = mysqli_fetch_assoc($dbRes);

            if (!$db) {
                $error = "Invalid Client Code or inactive module.";
            } else {

                /* ─────────────────────────────────────────
                    LICENSE CHECK FROM MASTER DB
                ───────────────────────────────────────── */
                $clientIdForLicense = (int)($db['client_id'] ?? 0);

                $licQuery = "
                    SELECT 
                        id, client_id, module_key, license_key, license_type, 
                        start_date, expiry_date, max_users, status, notes, 
                        created_at, updated_at
                    FROM licenses
                    WHERE client_id = $clientIdForLicense
                      AND module_key = '$module_key_esc'
                    LIMIT 1
                ";

                $licRes = mysqli_query($master, $licQuery);

                if (!$licRes) {
                    $error = "License check failed: " . mysqli_error($master);
                } else {
                    $license = mysqli_fetch_assoc($licRes);

                    if (!$license) {
                        $error = "No license found for this client/module.";
                    } else {

                        $licenseType   = strtolower(trim($license['license_type'] ?? 'trial'));
                        $licenseStatus = strtolower(trim($license['status'] ?? 'inactive'));
                        $today         = date('Y-m-d');

                        if ($licenseStatus !== 'active') {
                            $error = "License is not active. Please contact support.";
                        } elseif (!in_array($licenseType, ['trial', 'monthly', 'yearly', 'lifetime'], true)) {
                            $error = "Invalid license type. Please contact support.";
                        } else {

                            if ($licenseType === 'trial') {
                                if (empty($license['start_date'])) {
                                    $error = "Trial license start date not found.";
                                } else {
                                    $trialEndDate = date('Y-m-d', strtotime($license['start_date'] . ' +14 days'));
                                    if ($today > $trialEndDate) {
                                        $error = "Your 14 days trial expired on " . date('d M Y', strtotime($trialEndDate)) . ". Please upgrade your plan.";
                                    }
                                }
                            } elseif ($licenseType === 'monthly' || $licenseType === 'yearly') {
                                if (empty($license['expiry_date'])) {
                                    $error = ucfirst($licenseType) . " license expiry date not found.";
                                } elseif ($today > $license['expiry_date']) {
                                    $error = "Your " . $licenseType . " license expired on " . date('d M Y', strtotime($license['expiry_date'])) . ". Please renew to continue.";
                                }
                            } elseif ($licenseType === 'lifetime') {
                                // Lifetime license never expires.
                                $error = "";
                            }
                        }
                    }
                }

                if ($error === '') {

                    $conn = mysqli_init();
                    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

                    $db_port = !empty($db['port']) ? (int)$db['port'] : 3306;

                    $connected = @mysqli_real_connect(
                        $conn,
                        $db['db_host'],
                        $db['db_user'],
                        $db['db_pass'],
                        $db['db_name'],
                        $db_port
                    );

                    if (!$connected) {
                        $error = "Cannot connect to client database.";
                    } else {
                        mysqli_set_charset($conn, "utf8mb4");

                        // Sanitize username for client DB
                        $username_esc = mysqli_real_escape_string($conn, $username);

                        $userQuery = "
                            SELECT 
                                id, username, employee_name, email, password_hash, role, status, client_code
                            FROM users
                            WHERE username = '$username_esc' OR email = '$username_esc'
                            AND client_code = '{$db['client_code']}'
                            AND (status = 'active')
                            LIMIT 1
                        ";

                        $userRes = mysqli_query($conn, $userQuery);

                        if (!$userRes) {
                            $error = "User query failed: " . mysqli_error($conn);
                        } else {
                            $user = mysqli_fetch_assoc($userRes);

                            if (!$user) {
                                $error = "User not found.";
                            } elseif (($user['status'] ?? '') !== 'active') {
                                $error = "Your account is inactive.";
                            } elseif (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
                                $error = "Wrong password.";
                            } else {

                                $user_id = (int)$user['id'];
                                $accQuery = "
                                    SELECT page_name
                                    FROM user_access
                                    WHERE FIND_IN_SET('$user_id', REPLACE(REPLACE(REPLACE(user_id, '[', ''), ']', ''), '\"', '')) > 0
                                      AND (can_view = 1 OR can_add = 1 OR can_edit = 1 OR can_delete = 1)
                                ";

                                $accRes = mysqli_query($conn, $accQuery);

                                if (!$accRes) {
                                    $error = "Access query failed: " . mysqli_error($conn);
                                } else {
                                    $user_access = [];

                                    while ($row = mysqli_fetch_assoc($accRes)) {
                                        if (!empty($row['page_name'])) {
                                            $user_access[] = strtolower(trim($row['page_name']));
                                        }
                                    }

                                    if (empty($user_access)) {
                                        $error = "Login blocked. You do not have access to any page.";
                                    } else {
                                        session_regenerate_id(true);

                                        $_SESSION['login']       = true;
                                        $_SESSION['user_id']     = $user_id;
                                        $_SESSION['username']    = $user['username'];
                                        $_SESSION['email']       = $user['email'] ?? '';
                                        $_SESSION['role']        = $user['role'] ?? 'user';
                                        $_SESSION['employee_name'] = $user['employee_name'] ?? '';
                                        $_SESSION['client_code'] = $client_code;
                                        $_SESSION['module_key']  = $module_key;
                                        $_SESSION['client_db']   = $db['db_name'];
                                        $_SESSION['client_id']   = (int)($db['client_id'] ?? 0);
                                        $_SESSION['user_access'] = $user_access;
                                        $_SESSION['license_type'] = $license['license_type'] ?? 'trial';
                                        $_SESSION['expiry_date'] = $license['expiry_date'] ?? 'N/A';
                                        
                                        $_SESSION['license'] = [
                                            'id'           => (int)$license['id'],
                                            'license_key'  => $license['license_key'] ?? '',
                                            'license_type' => $license['license_type'] ?? '',
                                            'start_date'   => $license['start_date'] ?? '',
                                            'expiry_date'  => $license['expiry_date'] ?? '',
                                            'max_users'    => (int)($license['max_users'] ?? 0),
                                            'status'       => $license['status'] ?? '',
                                        ];

                                        $_SESSION['client_db_config'] = [
                                            'host' => $db['db_host'],
                                            'name' => $db['db_name'],
                                            'user' => $db['db_user'],
                                            'pass' => $db['db_pass'],
                                            'port' => (int)$db_port,
                                        ];

                                        // Update last login
                                        $updateLogin = "UPDATE users SET last_login_at = NOW() WHERE id = $user_id";
                                        mysqli_query($conn, $updateLogin);

                                        setcookie("client_code", $client_code, time() + (30 * 24 * 60 * 60), "/", "", false, true);

                                        mysqli_close($conn);

                                        header("Location: " . getRedirectPageLocal());
                                        exit;
                                    }
                                }
                            }
                        }

                        if (isset($conn) && $conn instanceof mysqli) {
                            mysqli_close($conn);
                        }
                    }
                }
            }
        }
    }
}

$savedClientCode = $_COOKIE['client_code'] ?? '';
$savedClientCode = is_string($savedClientCode) ? $savedClientCode : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhythm · Payroll & HR</title>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="includes/assets/img/favicon.svg">

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --yellow: #f4ea00;
        --black: #0b0f19;
        --gray-bg: #ececec;
        --text-gray: #8d9096;
        --line: rgba(11, 15, 25, 0.85);
    }

    html,
    body {
        width: 100%;
        height: 100%;
    }

    body {
        font-family: 'DM Sans', sans-serif;
        background: #fff;
        color: var(--black);
    }

    a {
        text-decoration: none;
    }

    button,
    input {
        font-family: inherit;
    }

    #loginPage {
        width: 100%;
        min-height: 100vh;
        display: flex;
        overflow: hidden;
    }

    .login-left {
        flex: 1;
        background: var(--gray-bg);
        padding: 38px 64px 38px 66px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .brand-box {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .brand-icon {
        width: 40px;
        height: 40px;
        background: var(--yellow);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .brand-name {
        color: #000;
        font-weight: 800;
        font-size: 24px;
    }

    .brand-sub {
        color: #6B6F8E;
        font-size: 10px;
        letter-spacing: 1px;
    }

    .hero-content {
        max-width: 760px;
        margin: auto 0;
    }

    .hero-title {
        font-size: clamp(44px, 5.4vw, 68px);
        line-height: 1.08;
        font-weight: 700;
        letter-spacing: -2.4px;
        color: var(--text-gray);
    }

    .hero-title strong {
        display: block;
        color: #000;
        font-weight: 800;
    }

    .support-link {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 15px;
        color: #0a1320;
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .login-right {
        width: 460px;
        min-width: 460px;
        background: var(--yellow);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 38px 42px;
    }

    .login-box {
        width: 100%;
        max-width: 330px;
    }

    .login-title {
        text-align: center;
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 10px;
        color: #000;
    }

    .alert {
        padding: 12px 14px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 18px;
        text-align: center;
    }

    .alert-error {
        background: rgba(255, 0, 0, 0.12);
        color: #a40000;
        border: 1px solid rgba(164, 0, 0, 0.25);
    }

    .alert-success {
        background: rgba(0, 120, 50, 0.12);
        color: #006b2d;
        border: 1px solid rgba(0, 120, 50, 0.25);
    }

    .input-group {
        margin-bottom: 10px;
    }

    .input-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1.5px solid var(--line);
        min-height: 46px;
        padding: 0 2px 10px;
    }

    .input-wrap i {
        width: 18px;
        text-align: center;
        font-size: 14px;
        color: #111;
        flex-shrink: 0;
    }

    .input-wrap input {
        border: none;
        outline: none;
        background: transparent;
        width: 100%;
        font-size: 16px;
        color: #111;
    }

    .input-wrap input::placeholder {
        color: #111;
        opacity: 1;
    }

    .toggle-password {
        cursor: pointer;
    }

    .login-btn {
        width: 100%;
        border: none;
        background: #000;
        color: #ffe600;
        font-size: 17px;
        font-weight: 800;
        border-radius: 5px;
        padding: 14px 18px;
        margin-top: 8px;
        cursor: pointer;
        transition: 0.2s;
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.14);
    }

    .login-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(0, 0, 0, 0.18);
    }

    .login-btn:disabled {
        opacity: 0.65;
        cursor: not-allowed;
        transform: none;
    }

    .forgot-link {
        display: block;
        text-align: center;
        margin-top: 14px;
        color: #111;
        font-size: 14px;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .or-divider {
        display: flex;
        align-items: center;
        gap: 14px;
        margin: 28px 0 15px;
    }

    .or-divider::before,
    .or-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: rgba(0, 0, 0, 0.4);
    }

    .or-divider span {
        font-size: 16px;
        font-weight: 600;
    }

    .social-btn {
        width: 100%;
        background: #fff;
        border: none;
        border-radius: 5px;
        min-height: 40px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        font-size: 15px;
        color: #111;
        cursor: pointer;
        margin-bottom: 12px;
    }

    .social-btn img {
        width: 20px;
        height: 20px;
    }

    .store-section {
        text-align: center;
        margin-top: 16px;
    }

    .store-section p {
        font-size: 14px;
        margin-bottom: 12px;
        color: #111;
        font-weight: 600;
    }

    .store-buttons {
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .store-badge {
        background: #000;
        color: #fff;
        border-radius: 6px;
        padding: 7px 12px;
        min-width: 124px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 12px;
        text-align: left;
    }

    .store-badge i {
        font-size: 22px;
    }

    .store-badge small {
        display: block;
        font-size: 8px;
        opacity: 0.82;
        line-height: 1.1;
    }

    .store-badge strong {
        display: block;
        font-size: 15px;
        line-height: 1.1;
    }

    @media (max-width: 900px) {
        #loginPage {
            flex-direction: column;
        }

        .login-left {
            padding: 28px 22px;
            gap: 36px;
        }

        .login-right {
            width: 100%;
            min-width: 100%;
            padding: 28px 22px 34px;
        }

        .hero-content {
            margin: 0;
        }

        .hero-title {
            font-size: clamp(34px, 8vw, 50px);
            letter-spacing: -1.4px;
        }

        .login-box {
            max-width: 100%;
        }
    }

    @media (max-width: 576px) {
        .login-left {
            padding: 24px 18px;
        }

        .login-right {
            padding: 24px 18px 28px;
        }

        .hero-title {
            font-size: 33px;
        }

        .login-title {
            font-size: 28px;
        }
    }
    </style>
</head>

<body>

    <div id="loginPage">

        <div class="login-left">

            <div class="brand-box">
                <div class="brand-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#12132A" stroke-width="2.5">
                        <polygon
                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                </div>
                <div>
                    <div class="brand-name">Rhythm</div>
                    <div class="brand-sub">PAYROLL · HR</div>
                </div>
            </div>

            <div class="hero-content">
                <h2 class="hero-title">
                    Simplifying workforce<br>
                    management with
                    <strong>smart Payroll &amp;<br>HR Solutions</strong>
                </h2>
            </div>

            <a href="contactSupport" class="support-link">
                <i class="fa-solid fa-headset"></i>
                Contact Support
            </a>

        </div>

        <div class="login-right">

            <div class="login-box">

                <h1 class="login-title">Login</h1>

                <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <form action="" method="POST" autocomplete="off" id="loginForm">

                    <input type="hidden" name="module_key" value="payroll">

                    <div class="input-group">
                        <div class="input-wrap">
                            <i class="fa-solid fa-building"></i>
                            <input type="text" name="client_code" placeholder="Client Code"
                                value="<?= htmlspecialchars($_POST['client_code'] ?? $savedClientCode, ENT_QUOTES, 'UTF-8') ?>"
                                required>
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="input-wrap">
                            <i class="fa-regular fa-circle-user"></i>
                            <input type="text" name="username" placeholder="Username or Email" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="input-wrap">
                            <i class="fa-solid fa-key"></i>
                            <input type="password" name="password" id="password" placeholder="Password" required>
                            <i class="fa-regular fa-eye-slash toggle-password" id="togglePassword"></i>
                        </div>
                    </div>

                    <button type="submit" name="login" value="1" class="login-btn" id="loginBtn">
                        Login
                    </button>

                </form>

                <a href="ForgotPassword" class="forgot-link">Forgot Password?</a>

                <div class="or-divider">
                    <span>OR</span>
                </div>

                <button type="button" class="social-btn">
                    <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google">
                    <span>Continue with Google</span>
                </button>

                <button type="button" class="social-btn">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/4/44/Microsoft_logo.svg" alt="Outlook">
                    <span>Continue with Outlook</span>
                </button>

                <div class="store-section">
                    <p>Also Available On</p>

                    <div class="store-buttons">
                        <div class="store-badge">
                            <i class="fa-brands fa-google-play"></i>
                            <div>
                                <small>GET IT ON</small>
                                <strong>Google Play</strong>
                            </div>
                        </div>

                        <div class="store-badge">
                            <i class="fa-brands fa-apple"></i>
                            <div>
                                <small>Download on the</small>
                                <strong>App Store</strong>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <script>
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');

    if (togglePassword && password) {
        togglePassword.addEventListener('click', function() {
            const isPassword = password.type === 'password';
            password.type = isPassword ? 'text' : 'password';

            this.classList.toggle('fa-eye', isPassword);
            this.classList.toggle('fa-eye-slash', !isPassword);
        });
    }

    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');

    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function() {
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Please wait...';
        });
    }
    </script>

</body>

</html>