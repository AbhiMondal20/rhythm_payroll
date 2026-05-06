<?php
session_start();

include '../includes/db_conn.php';
include '../includes/auth.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== "" && $password !== "") {

        $stmt = $master->prepare("
            SELECT id, client_id, username, email, password_hash, role, status
            FROM users
            WHERE username = ? OR email = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $error = "Database query failed.";
        } else {
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {

                if (password_verify($password, $user['password_hash'])) {

                    $role = strtolower(trim($user['role'] ?? ''));

                    /*
                        Login rule:
                        - superadmin can login even if status is inactive
                        - all other roles need status = 1
                    */
                    if ((int)$user['status'] === 1 || $role === 'superadmin') {

                        session_regenerate_id(true);

                        $_SESSION['login']    = true;
                        $_SESSION['user_id']  = (int)$user['id'];
                        $_SESSION['client_id'] = (int)$user['client_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email']    = $user['email'];
                        $_SESSION['role']     = $role;

                        $update = $master->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                        if ($update) {
                            $uid = (int)$user['id'];
                            $update->bind_param("i", $uid);
                            $update->execute();
                        }

                        if ($role === 'superadmin') {
                            header("Location: dashboard");
                        } else {
                            header("Location: index");
                        }
                        exit;

                    } else {
                        $error = "Account is inactive";
                    }

                } else {
                    $error = "Invalid password";
                }

            } else {
                $error = "User not found";
            }
        }

    } else {
        $error = "Please fill all fields";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rhythm · Ramkrishna IVF Centre — Payroll & HR</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/png" sizes="32x32" href="../includes/assets/img/favicon.svg">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root{
        --yellow: #f4ea00;
        --black: #0b0f19;
        --gray-bg: #ececec;
        --text-gray: #8d9096;
        --line: rgba(11, 15, 25, 0.85);
        --white: #ffffff;
        --muted: #5f6470;
        --panel-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }

    html, body {
        width: 100%;
        height: 100%;
        overflow: hidden;
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
        height: 100vh;
        display: flex;
        overflow: hidden;
    }

    /* LEFT PANEL */
    .login-left {
        flex: 1 1 auto;
        background: var(--gray-bg);
        padding: 38px 64px 38px 66px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .brand-wrap {
        display: flex;
        align-items: center;
    }

    .brand-logo {
        display: inline-flex;
        align-items: center;
        max-width: 320px;
    }

    .brand-logo svg {
        width: 240px;
        height: auto;
        display: block;
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

    .support-link i {
        font-size: 17px;
    }

    /* RIGHT PANEL */
    .login-right {
        width: 460px;
        min-width: 460px;
        background: var(--yellow);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 38px 42px;
        position: relative;
    }

    .login-box {
        width: 100%;
        max-width: 330px;
    }

    .login-title {
        text-align: center;
        font-size: 32px;
        line-height: 1;
        font-weight: 800;
        margin-bottom: 46px;
        color: #000;
        letter-spacing: -0.8px;
    }

    .input-group {
        margin-bottom: 22px;
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
        min-width: 0;
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
        font-weight: 700;
        border-radius: 4px;
        padding: 14px 18px;
        margin-top: 8px;
        cursor: pointer;
        transition: transform .2s ease, box-shadow .2s ease, opacity .2s ease;
        box-shadow: 0 8px 18px rgba(0,0,0,0.12);
    }

    .login-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(0,0,0,0.16);
    }

    .login-btn:active {
        transform: translateY(0);
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
        margin: 28px 0 26px;
    }

    .or-divider::before,
    .or-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: rgba(0,0,0,0.4);
    }

    .or-divider span {
        font-size: 16px;
        font-weight: 500;
        color: #111;
        line-height: 1;
    }

    .social-btn {
        width: 100%;
        background: #fff;
        border: none;
        border-radius: 4px;
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
        box-shadow: 0 0 0 1px rgba(0,0,0,0.05) inset;
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .social-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(0,0,0,0.08);
    }

    .social-btn img {
        width: 20px;
        height: 20px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .store-section {
        text-align: center;
        margin-top: 16px;
    }

    .store-section p {
        font-size: 14px;
        margin-bottom: 12px;
        color: #111;
        font-weight: 500;
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
        transition: transform .2s ease;
    }

    .store-badge:hover {
        transform: translateY(-1px);
    }

    .store-badge i {
        font-size: 22px;
    }

    .store-badge small {
        display: block;
        font-size: 8px;
        opacity: 0.82;
        line-height: 1.1;
        letter-spacing: .4px;
    }

    .store-badge strong {
        display: block;
        font-size: 15px;
        line-height: 1.1;
        font-weight: 700;
    }

    .err-msg {
        display: none;
        margin-top: 14px;
        color: #b10000;
        font-size: 14px;
        text-align: center;
        font-weight: 600;
    }

    /* TABLET */
    @media (max-width: 1180px) {
        .login-left {
            padding: 34px 42px;
        }

        .login-right {
            width: 420px;
            min-width: 420px;
            padding: 30px;
        }

        .brand-logo svg {
            width: 220px;
        }

        .hero-title {
            letter-spacing: -1.8px;
        }
    }

    /* MOBILE / STACK */
    @media (max-width: 900px) {
        html, body {
            overflow: auto;
        }

        #loginPage {
            min-height: 100vh;
            height: auto;
            flex-direction: column;
            overflow: visible;
        }

        .login-left {
            min-height: auto;
            padding: 28px 22px 24px;
            gap: 32px;
        }

        .login-right {
            width: 100%;
            min-width: 100%;
            padding: 28px 22px 34px;
        }

        .brand-logo svg {
            width: 210px;
        }

        .hero-content {
            margin: 0;
            max-width: 100%;
        }

        .hero-title {
            font-size: clamp(34px, 8vw, 50px);
            line-height: 1.08;
            letter-spacing: -1.4px;
        }

        .login-box {
            max-width: 100%;
        }
    }

    @media (max-width: 576px) {
        .login-left {
            padding: 24px 18px 22px;
        }

        .login-right {
            padding: 24px 18px 28px;
        }

        .brand-logo svg {
            width: 180px;
        }

        .hero-title {
            font-size: 33px;
            letter-spacing: -1px;
        }

        .login-title {
            font-size: 28px;
            margin-bottom: 34px;
        }

        .input-wrap input {
            font-size: 15px;
        }

        .store-buttons {
            gap: 10px;
        }

        .store-badge {
            min-width: 118px;
            padding: 7px 10px;
        }
    }
</style>
</head>
<body>

<div id="loginPage">
    <div class="login-left">
        <div class="brand-wrap">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <div style="width:40px;height:40px;background:var(--yellow);border-radius:8px;display:flex;align-items:center;justify-content:center">
                    <i class="fa-solid fa-star" style="color:#12132A"></i>
                </div>
                <div>
                    <div style="color:#000;font-weight:700;font-size:24px">Rhythm</div>
                    <div style="color:#6B6F8E;font-size:10px;letter-spacing:1px">PAYROLL · HR</div>
                </div>
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
                <p style="background:rgba(177,0,0,.12);color:#9b0000;border:1px solid rgba(177,0,0,.25);padding:11px 12px;border-radius:6px;text-align:center;font-size:14px;font-weight:700;margin-bottom:18px;">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <form action="" method="POST" autocomplete="off">
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

                <button type="submit" class="login-btn">Login</button>
            </form>

            <a href="ForgotPassword" class="forgot-link">Forget Password?</a>

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
    togglePassword.addEventListener('click', function () {
        const isPassword = password.type === 'password';
        password.type = isPassword ? 'text' : 'password';
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
}
</script>

</body>
</html>