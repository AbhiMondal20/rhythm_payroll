<?php
session_start();
include 'db_conn.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === "" || $email === "" || $subject === "" || $message === "") {
        $error = "Please fill all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        /*
        Optional DB Table:

        CREATE TABLE support_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NULL,
            subject VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(30) DEFAULT 'open',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        */

        $stmt = $master->prepare("
            INSERT INTO support_requests 
            (name, email, phone, subject, message, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'open', NOW())
        ");

        if ($stmt) {
            $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);

            if ($stmt->execute()) {
                $success = "Your support request has been submitted. Our team will contact you shortly.";
            } else {
                $error = "Unable to submit request. Please try again.";
            }
        } else {
            $error = "Support table not found. Please create support_requests table first.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Support · HR Solution</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/png" sizes="32x32" href="includes/assets/img/favicon.svg">

<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
    --yellow:#f4ea00;
    --black:#0b0f19;
    --gray-bg:#ececec;
    --text-gray:#8d9096;
    --line:rgba(11,15,25,.85);
}
html,body{width:100%;min-height:100%}
body{
    font-family:'DM Sans',sans-serif;
    background:#fff;
    color:var(--black);
}
a{text-decoration:none;color:inherit}
button,input,textarea,select{font-family:inherit}

#supportPage{
    width:100%;
    min-height:100vh;
    display:flex;
}

/* LEFT */
.support-left{
    flex:1;
    background:var(--gray-bg);
    padding:38px 64px 38px 66px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
}
.brand-logo svg{
    width:240px;
    height:auto;
    display:block;
}
.hero-content{
    max-width:760px;
    margin:auto 0;
}
.hero-title{
    font-size:clamp(44px,5.4vw,68px);
    line-height:1.08;
    font-weight:700;
    letter-spacing:-2.4px;
    color:var(--text-gray);
}
.hero-title strong{
    display:block;
    color:#000;
    font-weight:800;
}
.support-info{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:16px;
    margin-top:34px;
    max-width:820px;
}
.info-card{
    background:#fff;
    border:1px solid rgba(0,0,0,.08);
    border-radius:12px;
    padding:18px;
    box-shadow:0 8px 22px rgba(0,0,0,.05);
}
.info-card i{
    width:38px;
    height:38px;
    background:#000;
    color:#f4ea00;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-bottom:12px;
}
.info-card h4{
    font-size:15px;
    margin-bottom:5px;
}
.info-card p{
    font-size:13px;
    line-height:1.5;
    color:#555;
}
.back-login{
    display:inline-flex;
    align-items:center;
    gap:10px;
    font-size:15px;
    color:#0a1320;
    font-weight:700;
    text-decoration:underline;
    text-underline-offset:3px;
}

/* RIGHT */
.support-right{
    width:500px;
    min-width:500px;
    background:var(--yellow);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:38px 42px;
}
.support-box{
    width:100%;
    max-width:370px;
}
.support-title{
    text-align:center;
    font-size:32px;
    line-height:1.1;
    font-weight:800;
    margin-bottom:12px;
    color:#000;
    letter-spacing:-.8px;
}
.support-subtitle{
    text-align:center;
    font-size:14px;
    line-height:1.5;
    color:#111;
    margin-bottom:28px;
    font-weight:500;
}
.input-group{margin-bottom:18px}
.input-wrap{
    display:flex;
    align-items:center;
    gap:12px;
    border-bottom:1.5px solid var(--line);
    min-height:44px;
    padding:0 2px 9px;
}
.input-wrap.textarea{
    align-items:flex-start;
}
.input-wrap i{
    width:18px;
    text-align:center;
    font-size:14px;
    color:#111;
    flex-shrink:0;
    margin-top:5px;
}
.input-wrap input,
.input-wrap select,
.input-wrap textarea{
    border:none;
    outline:none;
    background:transparent;
    width:100%;
    font-size:15px;
    color:#111;
}
.input-wrap textarea{
    min-height:86px;
    resize:none;
}
.input-wrap input::placeholder,
.input-wrap textarea::placeholder{
    color:#111;
    opacity:1;
}
.input-wrap select{
    cursor:pointer;
}
.submit-btn{
    width:100%;
    border:none;
    background:#000;
    color:#ffe600;
    font-size:17px;
    font-weight:700;
    border-radius:4px;
    padding:14px 18px;
    margin-top:8px;
    cursor:pointer;
    transition:.2s ease;
    box-shadow:0 8px 18px rgba(0,0,0,.12);
}
.submit-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 10px 22px rgba(0,0,0,.16);
}
.msg{
    margin-bottom:18px;
    padding:12px 14px;
    border-radius:6px;
    font-size:14px;
    font-weight:700;
    line-height:1.5;
    text-align:center;
}
.msg.error{
    background:rgba(177,0,0,.12);
    color:#9b0000;
    border:1px solid rgba(177,0,0,.25);
}
.msg.success{
    background:rgba(0,100,20,.12);
    color:#075f19;
    border:1px solid rgba(0,100,20,.25);
}
.quick-links{
    margin-top:24px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}
.quick-link{
    background:rgba(255,255,255,.55);
    border:1px solid rgba(0,0,0,.12);
    border-radius:8px;
    padding:11px 12px;
    font-size:13px;
    font-weight:700;
    color:#111;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}

/* RESPONSIVE */
@media(max-width:1180px){
    .support-left{padding:34px 42px}
    .support-right{width:460px;min-width:460px;padding:30px}
    .brand-logo svg{width:220px}
}
@media(max-width:980px){
    #supportPage{
        flex-direction:column;
    }
    .support-left{
        padding:28px 22px 30px;
        gap:34px;
    }
    .support-right{
        width:100%;
        min-width:100%;
        padding:30px 22px 36px;
    }
    .support-box{
        max-width:100%;
    }
    .hero-content{
        margin:0;
    }
    .hero-title{
        font-size:clamp(34px,8vw,50px);
        letter-spacing:-1.4px;
    }
    .support-info{
        grid-template-columns:1fr;
    }
}
@media(max-width:576px){
    .support-left{padding:24px 18px 24px}
    .support-right{padding:24px 18px 30px}
    .brand-logo svg{width:180px}
    .hero-title{
        font-size:33px;
        letter-spacing:-1px;
    }
    .support-title{font-size:28px}
    .quick-links{grid-template-columns:1fr}
}
</style>
</head>

<body>

<div id="supportPage">

    <div class="support-left">
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
                Need help with<br>
                your account?
                <strong>Our support team<br>is ready to help</strong>
            </h2>

            <div class="support-info">
                <div class="info-card">
                    <i class="fa-solid fa-envelope"></i>
                    <h4>Email Support</h4>
                    <p>Send your issue and our team will reply as soon as possible.</p>
                </div>

                <div class="info-card">
                    <i class="fa-solid fa-lock"></i>
                    <h4>Login Issues</h4>
                    <p>Get help with password, account access and inactive account problems.</p>
                </div>

                <div class="info-card">
                    <i class="fa-solid fa-headset"></i>
                    <h4>Technical Help</h4>
                    <p>Report bugs, payroll issues, HR module errors or dashboard problems.</p>
                </div>
            </div>
        </div>

        <a href="login.php" class="back-login">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Login
        </a>
    </div>

    <div class="support-right">
        <div class="support-box">

            <h1 class="support-title">Contact Support</h1>
            <p class="support-subtitle">
                Fill the form below and our team will connect with you shortly.
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
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" autocomplete="off">
                <div class="input-group">
                    <div class="input-wrap">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" name="name" placeholder="Your Name *" required>
                    </div>
                </div>

                <div class="input-group">
                    <div class="input-wrap">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address *" required>
                    </div>
                </div>

                <div class="input-group">
                    <div class="input-wrap">
                        <i class="fa-solid fa-phone"></i>
                        <input type="text" name="phone" placeholder="Phone Number">
                    </div>
                </div>

                <div class="input-group">
                    <div class="input-wrap">
                        <i class="fa-solid fa-circle-question"></i>
                        <select name="subject" required>
                            <option value="">Select Issue *</option>
                            <option value="Login Issue">Login Issue</option>
                            <option value="Forgot Password">Forgot Password</option>
                            <option value="Payroll Issue">Payroll Issue</option>
                            <option value="Attendance Issue">Attendance Issue</option>
                            <option value="Leave Management Issue">Leave Management Issue</option>
                            <option value="Technical Error">Technical Error</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <div class="input-wrap textarea">
                        <i class="fa-regular fa-message"></i>
                        <textarea name="message" placeholder="Describe your issue *" required></textarea>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    Submit Request
                </button>
            </form>

            <div class="quick-links">
                <a href="ForgotPassword" class="quick-link">
                    <i class="fa-solid fa-key"></i>
                    Forgot Password
                </a>

                <a href="login" class="quick-link">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Login Page
                </a>
            </div>

        </div>
    </div>

</div>

</body>
</html>