<?php
session_start();
require_once '../includes/db_conn.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $client_code  = strtolower(trim($_POST['client_code'] ?? ''));
    $contact_info = trim($_POST['contact_info'] ?? '');

    if ($client_code === '') {
        $error = "Please enter Client Code.";
    } elseif ($contact_info === '') {
        $error = "Please enter Email Id or Mobile No.";
    } elseif (!isset($master) || !($master instanceof mysqli)) {
        $error = "Master database connection not found.";
    } else {
        
        // 1. Fetch Client Database Credentials from Master DB
        $client_code_esc = mysqli_real_escape_string($master, $client_code);
        
        $dbQuery = "
            SELECT db_host, db_name, db_user, db_pass, port 
            FROM client_databases 
            WHERE client_code = '$client_code_esc' 
              AND status = 'active' 
            LIMIT 1
        ";
        
        $dbRes = mysqli_query($master, $dbQuery);
        
        if (!$dbRes) {
            $error = "Master query failed: " . mysqli_error($master);
        } elseif (mysqli_num_rows($dbRes) === 0) {
            $error = "Invalid Client Code or inactive module.";
        } else {
            $db = mysqli_fetch_assoc($dbRes);
            
            // 2. Connect to the Client Database
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

                // 3. Search for the user in the Client DB
                $contact_esc = mysqli_real_escape_string($conn, $contact_info);
                
                // Assuming you search by email or username (or mobile if you have a mobile column)
                $userQuery = "
                    SELECT id, email, username 
                    FROM users 
                    WHERE (email = '$contact_esc' OR username = '$contact_esc') 
                      AND client_code = '$client_code_esc'
                      AND LOWER(status) = 'active' 
                    LIMIT 1
                ";
                
                $userRes = mysqli_query($conn, $userQuery);

                if (!$userRes) {
                    $error = "User query failed: " . mysqli_error($conn);
                } elseif (mysqli_num_rows($userRes) === 0) {
                    $error = "No active account found with those details.";
                } else {
                    $user = mysqli_fetch_assoc($userRes);
                    
                    // =========================================================
                    // TODO: INSERT YOUR EMAIL / OTP SENDING LOGIC HERE
                    // 1. Generate a random token or OTP
                    // 2. Save it to a password_resets table or update the user record
                    // 3. Use PHPMailer or SMS API to send the reset link/OTP
                    // =========================================================
                    
                    $success = "If your details match an active account, a reset link/OTP has been sent.";
                }
                
                mysqli_close($conn);
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
    <title>Rhythm Payroll - Forgot Password</title>
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
    <div class="pt-16 pb-8 px-6 flex flex-col w-full max-w-md mx-auto relative">
        
        <!-- Back Button -->
        <a href="login" class="absolute left-6 top-16 text-gray-400 hover:text-white transition-colors">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>

        <!-- Logo -->
        <div class="w-full flex justify-center mb-8">
            <div style="display:flex;align-items:center;gap:10px;">
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

        <!-- Headline -->
        <h1 class="text-white text-2xl leading-snug font-semibold mt-4">
            Reset your <br>password
        </h1>
    </div>

    <!-- Bottom White Card Section -->
    <div class="bg-white rounded-t-[30px] flex-1 px-6 pt-8 pb-6 w-full max-w-md mx-auto flex flex-col">
        
        <p class="text-gray-500 text-sm mb-6 leading-relaxed">
            Enter the client code and email address associated with your account and we'll send you a link or OTP to reset your password.
        </p>

        <!-- Display PHP Alerts -->
        <?php if (!empty($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-4 text-sm font-medium">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 text-sm font-medium">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="" class="flex-1 flex flex-col">
            
            <!-- Client Code Input -->
            <div class="mb-4">
                <label class="block text-sm text-gray-900 mb-1.5 font-medium">Client Code*</label>
                <input type="text" name="client_code" value="<?php echo htmlspecialchars($savedClientCode); ?>" placeholder="Enter Client Code" class="w-full border border-gray-200 rounded-xl px-4 py-3.5 text-sm focus:outline-none focus:border-gray-400 placeholder-gray-300" required>
            </div>

            <!-- Email / Mobile Input -->
            <div class="mb-6">
                <label class="block text-sm text-gray-900 mb-1.5 font-medium">Email Id or Mobile No.*</label>
                <input type="text" name="contact_info" placeholder="Enter Email or Mobile No." class="w-full border border-gray-200 rounded-xl px-4 py-3.5 text-sm focus:outline-none focus:border-gray-400 placeholder-gray-300" required>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full bg-[#E5E5E5] text-black font-medium py-3.5 rounded-xl hover:bg-gray-300 transition-colors mt-auto">
                Send Reset Instructions
            </button>
        </form>

        <!-- Back to Login Link -->
        <div class="text-center mt-6">
            <span class="text-sm text-gray-500">Remember password? </span>
            <a href="login" class="text-sm text-black font-semibold underline">Back to Login</a>
        </div>

        <!-- Version Info -->
        <div class="text-center pt-8 mt-auto">
            <span class="text-xs text-gray-300">Version 1.0.2</span>
        </div>
    </div>

</body>
</html>