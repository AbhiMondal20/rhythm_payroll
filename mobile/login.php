<?php
session_start();
require_once '../includes/db_conn.php';

$error = '';
$success = '';

if (!isset($master) || !($master instanceof mysqli)) {
    $error = "Master database connection not found.";
}

function hasAccessLocal(string $key): bool {
    $access = $_SESSION['user_access'] ?? [];
    return in_array(strtolower($key), $access, true);
}

// Updated redirect function to point to AppDashboard
function getRedirectPageLocal(): string {
    return 'AppDashboard';
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

                        // Sanitize username and client_code for client DB
                        $username_esc = mysqli_real_escape_string($conn, $username);
                        $client_code_db_esc = mysqli_real_escape_string($conn, $db['client_code']);

                        $userQuery = "
                            SELECT 
                                id, username, employee_name, employee_code, email, password_hash, role, status, client_code
                            FROM users
                            WHERE (username = '$username_esc' OR email = '$username_esc')
                            AND client_code = '$client_code_db_esc'
                            AND LOWER(status) = 'active'
                            LIMIT 1
                        ";

                        $userRes = mysqli_query($conn, $userQuery);

                        // ====================================================================
                        // UNCOMMENT THE 3 LINES BELOW TO DEBUG IF YOU STILL GET THE ERROR
                        // ====================================================================
                        if (!$userRes || mysqli_num_rows($userRes) === 0) {
                            die("<div style='background:#fff;padding:20px;color:red;'><strong>Debug Query:</strong><br>" . $userQuery . "</div>");
                        }
                        // ====================================================================

                        if (!$userRes) {
                            $error = "User query failed: " . mysqli_error($conn);
                        } else {
                            $user = mysqli_fetch_assoc($userRes);

                            if (!$user) {
                                $error = "User not found or inactive.";
                            } elseif (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
                                $error = "Wrong password.";
                            } else {

                                $user_id = (int)$user['id'];
                                
                                // Fetch corresponding employee record if employee_code exists
                                $emp_id = 0;
                                $emp_code_val = trim($user['employee_code'] ?? '');
                                
                                if ($emp_code_val !== '') {
                                    $empCodeEsc = mysqli_real_escape_string($conn, $emp_code_val);
                                    $empQuery = "SELECT id, employee_code, employee_name FROM employees WHERE employee_code = '$empCodeEsc' LIMIT 1";
                                    $empRes = mysqli_query($conn, $empQuery);
                                    if ($empRes && $empRow = mysqli_fetch_assoc($empRes)) {
                                        $emp_id = (int)($empRow['id'] ?? 0);
                                    }
                                }

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

                                        $_SESSION['login']         = true;
                                        $_SESSION['user_id']     = $user_id;
                                        $_SESSION['emp_id']      = $emp_id; 
                                        $_SESSION['username']    = $user['username'];
                                        $_SESSION['email']       = $user['email'] ?? '';
                                        $_SESSION['role']        = $user['role'] ?? 'user';
                                        $_SESSION['employee_name'] = $user['employee_name'] ?? '';
                                        $_SESSION['employee_code'] = $user['employee_code'] ?? '';
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

                                        // Save client_code in cookie for 30 days
                                        setcookie("client_code", $client_code, time() + (30 * 24 * 60 * 60), "/", "", false, true);

                                        mysqli_close($conn);

                                        // Redirect directly to AppDashboard
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

// Fetch the saved client code from the cookie to pre-fill the input
$savedClientCode = $_COOKIE['client_code'] ?? '';
$savedClientCode = is_string($savedClientCode) ? $savedClientCode : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhythm Payroll Login</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="16x16" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="apple-touch-icon" href="/rhythm_payroll/includes/assets/img/apple-touch-icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #171c23; 
        }
    </style>
</head>
<body class="h-screen flex flex-col justify-between overflow-x-hidden">

    <!-- Top Dark Section -->
    <div class="pt-16 pb-8 px-6 flex flex-col items-start w-full max-w-md mx-auto">
        <!-- Logo -->
        <div class="w-full flex justify-center mb-10">
            <div class="flex items-center gap-1">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                    <div style="width:36px;height:36px;background:#ffe000;border-radius:8px;display:flex;align-items:center;justify-content:center">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#12132A" stroke-width="2.5">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <div>
                        <div style="color:#fff;font-weight:700;font-size:16px">Rhythm</div>
                        <div style="color:#6B6F8E;font-size:10px;letter-spacing:1px">PAYROLL · HR</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Headline -->
        <h1 class="text-[#8c949e] text-2xl leading-snug font-medium">
            Transforming<br>
            workplaces into Hubs<br>
            of <span class="text-white font-bold">Happiness</span>
        </h1>
    </div>

    <!-- Bottom White Card Section -->
    <div class="bg-white rounded-t-[30px] flex-1 px-6 pt-6 pb-4 w-full max-w-md mx-auto flex flex-col">
        
        <!-- Toggle Switch -->
        <div class="bg-gray-100 rounded-full p-1 flex mb-6">
            <button id="btn-email" onclick="switchTab('email')" class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-full bg-white shadow-sm text-sm font-semibold transition-all">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                Email
            </button>
            <button id="btn-mobile" onclick="switchTab('mobile')" class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-full text-gray-500 text-sm font-semibold transition-all">
                <svg width="14" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                Mobile
            </button>
        </div>

        <p class="text-black text-[17px] mb-4">Welcome to Rhythm Payroll, Please Login</p>

        <!-- Display PHP Errors -->
        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-4 text-sm font-medium">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- WRAPPED IN A FORM TAG -->
        <form method="POST" action="" class="flex-1 flex flex-col">
            <!-- Hidden Input for module_key -->
            <input type="hidden" name="module_key" value="payroll">

            <!-- EMAIL FORM -->
            <div id="form-email" class="flex-1 flex flex-col">
                <!-- Client code -->
                <div class="mb-4">
                    <label class="block text-sm text-gray-900 mb-1.5">Enter Client Code*</label>
                    <input type="text" name="client_code" placeholder="Client Code" value="<?php echo htmlspecialchars($savedClientCode); ?>" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-gray-400 placeholder-gray-300">
                </div>

                <!-- Username/Email -->
                <div class="mb-4">
                    <label class="block text-sm text-gray-900 mb-1.5">Enter Username or Email Id*</label>
                    <input type="text" name="username" placeholder="Username or Email Id" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-gray-400 placeholder-gray-300">
                </div>

                <!-- Password -->
                <div class="mb-2">
                    <label class="block text-sm text-gray-900 mb-1.5">Password*</label>
                    <div class="relative">
                        <input type="password" name="password" placeholder="Password" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-gray-400 placeholder-gray-300">
                        <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Forgot Password -->
                <a href="ForgotPassword" class="text-sm text-black underline mb-6 block w-fit">Forgot Password?</a>

                <!-- Login Button - Changed type to submit -->
                <button type="submit" class="w-full bg-[#E5E5E5] text-black font-medium py-3.5 rounded-xl mt-auto">
                    Login
                </button>
            </div>
        </form>

        <!-- MOBILE FORM (Initially Hidden) -->
        <div id="form-mobile" class="flex-1 flex flex-col hidden">
            <!-- Mobile Number -->
            <div class="mb-4">
                <label class="block text-sm text-gray-900 mb-1.5">Enter Registered Mobile No.*</label>
                <input type="tel" placeholder="Mobile No." class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-gray-400 placeholder-gray-300">
            </div>

            <!-- Send OTP Button -->
            <button type="button" class="w-full bg-[#E5E5E5] text-black font-medium py-3.5 rounded-xl mt-auto">
                Send OTP
            </button>
        </div>

        <!-- Social Login Separator -->
        <div class="flex items-center my-6">
            <hr class="flex-1 border-gray-300">
            <span class="px-3 text-sm text-black">Or login using</span>
            <hr class="flex-1 border-gray-300">
        </div>

        <!-- Social Buttons -->
        <div class="flex gap-4 mb-6">
            <button type="button" class="flex-1 flex items-center justify-center gap-2 bg-[#F4F5F9] py-3 rounded-xl text-sm font-medium">
                <svg width="18" height="18" viewBox="0 0 21 21"><path fill="#f25022" d="M1 1h9v9H1z"/><path fill="#00a4ef" d="M1 11h9v9H1z"/><path fill="#7fba00" d="M11 1h9v9h-9z"/><path fill="#ffb900" d="M11 11h9v9h-9z"/></svg>
                Microsoft
            </button>
            <button type="button" class="flex-1 flex items-center justify-center gap-2 bg-[#F4F5F9] py-3 rounded-xl text-sm font-medium">
                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Google
            </button>
        </div>

        <!-- Version Info -->
        <div class="text-center pb-2">
            <span class="text-xs text-gray-300">Version 1.0.2</span>
        </div>
    </div>

    <!-- JavaScript to handle Email/Mobile Tab Switching -->
    <script>
        function switchTab(tab) {
            const btnEmail = document.getElementById('btn-email');
            const btnMobile = document.getElementById('btn-mobile');
            const formEmail = document.getElementById('form-email');
            const formMobile = document.getElementById('form-mobile');

            if (tab === 'email') {
                formEmail.classList.remove('hidden');
                formMobile.classList.add('hidden');
                
                btnEmail.classList.add('bg-white', 'shadow-sm', 'text-black');
                btnEmail.classList.remove('text-gray-500');
                
                btnMobile.classList.remove('bg-white', 'shadow-sm', 'text-black');
                btnMobile.classList.add('text-gray-500');
            } else {
                formMobile.classList.remove('hidden');
                formEmail.classList.add('hidden');
                
                btnMobile.classList.add('bg-white', 'shadow-sm', 'text-black');
                btnMobile.classList.remove('text-gray-500');
                
                btnEmail.classList.remove('bg-white', 'shadow-sm', 'text-black');
                btnEmail.classList.add('text-gray-500');
            }
        }
    </script>
</body>
</html>