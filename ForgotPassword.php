<?php
session_start();
include 'includes/db_conn.php';
include 'includes/auth.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');

    if ($email === "") {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        $stmt = $master->prepare("SELECT id, username, email, status FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if ((int)$user['status'] !== 1) {
                $error = "Your account is inactive. Please contact support.";
            } else {
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));

                $update = $master->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $update->bind_param("ssi", $token, $expires, $user['id']);

                if ($update->execute()) {
                    $resetLink = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password?token=" . urlencode($token);

                    /*
                    mail(
                        $email,
                        "Reset Your Password",
                        "Hello " . $user['username'] . ",\n\nReset your password using this link:\n" . $resetLink . "\n\nThis link will expire in 30 minutes."
                    );
                    */

                    $success = "Password reset link has been generated. Please check your email.";

                    // Remove this line after email setup
                    $success .= "<br><small>Demo Reset Link: <a href='{$resetLink}'>{$resetLink}</a></small>";
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        } else {
            $error = "Email address not found";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password · HR Solution</title>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="includes/assets/img/favicon.svg">

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box
    }

    :root {
        --yellow: #f4ea00;
        --black: #0b0f19;
        --gray-bg: #ececec;
        --text-gray: #8d9096;
        --line: rgba(11, 15, 25, .85);
        --white: #fff;
    }

    html,
    body {
        width: 100%;
        height: 100%;
        overflow: hidden
    }

    body {
        font-family: 'DM Sans', sans-serif;
        background: #fff;
        color: var(--black)
    }

    a {
        text-decoration: none
    }

    button,
    input {
        font-family: inherit
    }

    #loginPage {
        width: 100%;
        height: 100vh;
        display: flex;
        overflow: hidden;
    }

    /* LEFT */
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

    /* RIGHT */
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
        line-height: 1.1;
        font-weight: 800;
        margin-bottom: 18px;
        color: #000;
        letter-spacing: -.8px;
    }

    .login-subtitle {
        text-align: center;
        font-size: 14px;
        line-height: 1.5;
        color: #111;
        margin-bottom: 36px;
        font-weight: 500;
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
    }

    .input-wrap input::placeholder {
        color: #111;
        opacity: 1;
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
        transition: .2s ease;
        box-shadow: 0 8px 18px rgba(0, 0, 0, .12);
    }

    .login-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(0, 0, 0, .16);
    }

    .back-link {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 16px;
        color: #111;
        font-size: 14px;
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .msg {
        margin-bottom: 18px;
        padding: 12px 14px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 700;
        line-height: 1.5;
        text-align: center;
    }

    .msg.error {
        background: rgba(177, 0, 0, .12);
        color: #9b0000;
        border: 1px solid rgba(177, 0, 0, .25);
    }

    .msg.success {
        background: rgba(0, 100, 20, .12);
        color: #075f19;
        border: 1px solid rgba(0, 100, 20, .25);
    }

    .msg small {
        display: block;
        margin-top: 8px;
        font-size: 11px;
        word-break: break-all;
    }

    .msg a {
        color: #000;
        text-decoration: underline;
    }

    .help-card {
        margin-top: 28px;
        background: rgba(255, 255, 255, .5);
        border: 1px solid rgba(0, 0, 0, .12);
        border-radius: 8px;
        padding: 14px;
        font-size: 13px;
        line-height: 1.5;
        color: #111;
    }

    .help-card strong {
        display: block;
        margin-bottom: 4px;
    }

    .help-card i {
        margin-right: 6px;
    }

    /* RESPONSIVE */
    @media(max-width:1180px) {
        .login-left {
            padding: 34px 42px
        }

        .login-right {
            width: 420px;
            min-width: 420px;
            padding: 30px
        }

        .brand-logo svg {
            width: 220px
        }
    }

    @media(max-width:900px) {

        html,
        body {
            overflow: auto
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
            width: 210px
        }

        .hero-content {
            margin: 0;
            max-width: 100%;
        }

        .hero-title {
            font-size: clamp(34px, 8vw, 50px);
            letter-spacing: -1.4px;
        }

        .login-box {
            max-width: 100%
        }
    }

    @media(max-width:576px) {
        .login-left {
            padding: 24px 18px 22px
        }

        .login-right {
            padding: 24px 18px 28px
        }

        .brand-logo svg {
            width: 180px
        }

        .hero-title {
            font-size: 33px;
            letter-spacing: -1px;
        }

        .login-title {
            font-size: 28px
        }
    }
    </style>
</head>

<body>

    <div id="loginPage">

        <div class="login-left">
            <!-- <div class="brand-logo">
            <svg width="320" height="120" viewBox="0 0 820 260" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <style>
                        .navy{fill:#08163f}
                        .yellow{fill:#f2c319}
                        .text-main{fill:#08163f;font-family:Arial,Helvetica,sans-serif;font-weight:700}
                        .text-sub{fill:#08163f;font-family:Arial,Helvetica,sans-serif;font-weight:500;letter-spacing:10px}
                    </style>
                </defs>
                <g transform="translate(20,20)">
                    <circle class="navy" cx="72" cy="42" r="22"/>
                    <rect class="navy" x="38" y="82" width="68" height="126" rx="34"/>
                    <circle class="yellow" cx="150" cy="24" r="28"/>
                    <path class="yellow" d="M112 86 C112 68,126 56,144 56 L156 56 C174 56,188 68,188 86 L188 168 C163 162,137 162,112 168 Z"/>
                    <path fill="#ffffff" d="M148 58 L162 58 L170 78 L160 124 L150 138 L140 124 L130 78 Z"/>
                    <circle class="navy" cx="228" cy="42" r="22"/>
                    <rect class="navy" x="194" y="82" width="68" height="126" rx="34"/>
                    <path class="navy" d="M36 194 C70 152,109 134,150 134 C191 134,230 152,264 194 L264 214 C232 176,193 158,150 158 C107 158,68 176,36 214 Z"/>
                </g>
                <g transform="translate(330,38)">
                    <text x="0" y="95" font-size="128" class="text-main">HR</text>
                    <text x="0" y="155" font-size="44" class="text-sub">SOLUTION</text>
                </g>
            </svg>

        </div> -->


            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <div
                    style="width:40px;height:40px;background:var(--yellow);border-radius:8px;display:flex;align-items:center;justify-content:center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#12132A" stroke-width="2.5">
                        <polygon
                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                </div>
                <div>
                    <div style="color:#000;font-weight:700;font-size:24px">Rhythm</div>
                    <div style="color:#6B6F8E;font-size:10px;letter-spacing:1px">PAYROLL · HR</div>
                </div>
            </div>

            <div class="hero-content">
                <h2 class="hero-title">
                    Forgot your<br>
                    password?
                    <strong>Reset it securely<br>in minutes</strong>
                </h2>
            </div>

            <a href="contactSupport" class="support-link">
                <i class="fa-solid fa-headset"></i>
                Contact Support
            </a>
        </div>

        <div class="login-right">
            <div class="login-box">

                <h1 class="login-title">Reset Password</h1>
                <p class="login-subtitle">
                    Enter your registered email address. We will send you a secure password reset link.
                </p>

                <?php if (!empty($error)): ?>
                <div class="msg error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                <div class="msg success">
                    <i class="fa-solid fa-circle-check"></i>
                    <?= $success ?>
                </div>
                <?php endif; ?>

                <form action="" method="POST" autocomplete="off">
                    <div class="input-group">
                        <div class="input-wrap">
                            <i class="fa-regular fa-envelope"></i>
                            <input type="email" name="email" placeholder="Registered Email" required>
                        </div>
                    </div>

                    <button type="submit" class="login-btn">
                        Send Reset Link
                    </button>
                </form>

                <a href="login" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Login
                </a>

                <div class="help-card">
                    <strong><i class="fa-solid fa-shield-halved"></i>Security Note</strong>
                    Reset link will expire after 30 minutes. Do not share this link with anyone.
                </div>

            </div>
        </div>

    </div>

</body>

</html>