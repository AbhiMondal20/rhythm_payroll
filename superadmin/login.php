<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>perk · Ramkrishna IVF Centre — Payroll & HR</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../includes/assets/style.css">
</head>
<body>
<?php
    include '../db_conn.php';
    
    $loginErr = '';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                header('Location: dashboard.php');
                exit();
            } else {
                $loginErr = 'Invalid email or password. Please try again.';
            }
        } else {
            $loginErr = 'Invalid email or password. Please try again.';
        }
    }
?>

<!-- ═══════════════ LOGIN PAGE ═══════════════ -->
<div id="loginPage">
    <div class="login-left">
        <div class="login-brand">
            <div class="brand-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0F1020" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div>
                <div style="color:#fff;font-size:24px;font-weight:700;font-family:'Space Mono',monospace">perk</div>
                <div style="color:rgba(255,255,255,.4);font-size:10px;letter-spacing:2px;margin-top:-2px">PAYROLL · HR PLATFORM</div>
            </div>
        </div>
        <div style="max-width:340px;text-align:center;z-index:1">
            <h2 style="color:#fff;font-size:32px;font-weight:700;line-height:1.2;margin-bottom:12px">Manage your team,<br>effortlessly.</h2>
            <p style="color:rgba(255,255,255,.45);font-size:14px;line-height:1.6">Payroll processing, attendance, leaves, taxes, and HR workflows — all in one place for Ramkrishna IVF Centre.</p>
        </div>
        <div class="login-demo">
            <strong>🔐 Demo Credentials</strong>
            <div style="margin-bottom:4px"><span style="color:rgba(255,255,255,.5)">Email:</span> <span style="color:#fff;font-family:'Space Mono',monospace">admin@ramkrishnaivf.in</span></div>
            <div><span style="color:rgba(255,255,255,.5)">Password:</span> <span style="color:#fff;font-family:'Space Mono',monospace">admin123</span></div>
        </div>
    </div>

    <div class="login-right">
        <div style="text-align:center;margin-bottom:28px">
            <div style="width:48px;height:48px;background:#F0EDFC;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <h1 style="font-size:22px;font-weight:700;color:var(--text)">Welcome back</h1>
            <p style="font-size:13px;color:var(--muted);margin-top:4px">Sign in to your account</p>
        </div>
        <form method="POST">
            <div class="input-group">
                <label>USERNAME</label>
                <input type="text" id="loginEmail" name="username" placeholder="username" required>
            </div>
            <div class="input-group">
                <label>PASSWORD</label>
                <input type="password" id="loginPass" name="password" placeholder="••••••••" required>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;font-size:13px">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;color:var(--muted)">
                    <input type="checkbox" checked style="accent-color:var(--purple)"> Remember me
                </label>
                <a href="#" style="color:var(--purple);font-weight:500">Forgot password?</a>
            </div>
            <button type="submit" class="btn-primary">Sign In →</button>
        </form>

        <?php if($loginErr): ?>
            <p class="err-msg" id="loginErr"><?php echo $loginErr; ?></p>
        <?php endif; ?>
        <p style="font-size:12px;color:var(--muted);text-align:center;margin-top:24px">Ramkrishna IVF Centre · Siliguri, West Bengal</p>
    </div>
</div>
<script src="../includes/assets/script.js"></script>
</body>
</html>
