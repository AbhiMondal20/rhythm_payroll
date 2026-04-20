<?php
include '../db_conn.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rhythm · Ramkrishna IVF Centre — Payroll & HR</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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
    <!-- LEFT -->
    <div class="login-left">
        <div class="brand-wrap">
            <div class="brand-logo">
                <svg width="320" height="120" viewBox="0 0 820 260" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="HR Solution logo">
                  <defs>
                    <style>
                      .navy { fill: #08163f; }
                      .yellow { fill: #f2c319; }
                      .text-main {
                        fill: #08163f;
                        font-family: Arial, Helvetica, sans-serif;
                        font-weight: 700;
                      }
                      .text-sub {
                        fill: #08163f;
                        font-family: Arial, Helvetica, sans-serif;
                        font-weight: 500;
                        letter-spacing: 10px;
                      }
                      .tag {
                        fill: #4b5563;
                        font-family: Arial, Helvetica, sans-serif;
                        font-weight: 600;
                        letter-spacing: 4px;
                      }
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

                    <path class="navy" d="M36 194
                                          C70 152,109 134,150 134
                                          C191 134,230 152,264 194
                                          L264 214
                                          C232 176,193 158,150 158
                                          C107 158,68 176,36 214 Z"/>
                  </g>

                  <g transform="translate(330,38)">
                    <text x="0" y="95" font-size="128" class="text-main">HR</text>
                    <text x="0" y="155" font-size="44" class="text-sub">SOLUTION</text>
                  </g>
                </svg>
            </div>
        </div>

        <div class="hero-content">
            <h2 class="hero-title">
                Simplifying workforce<br>
                management with
                <strong>smart Payroll &amp;<br>HR Solutions</strong>
            </h2>
        </div>

        <a href="#" class="support-link">
            <i class="fa-solid fa-headset"></i>
            Contact Support
        </a>
    </div>

    <!-- RIGHT -->
    <div class="login-right">
        <div class="login-box">
            <h1 class="login-title"> Super Admin Login</h1>

            <form action="" method="POST" autocomplete="off">
                <div class="input-group">
                    <div class="input-wrap">
                        <i class="fa-regular fa-circle-user"></i>
                        <input type="text" name="username" placeholder="Username" required>
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

            <a href="#" class="forgot-link">Forget Password?</a>

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

            <p class="err-msg" id="loginErr">Invalid username or password.</p>
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