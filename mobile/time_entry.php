<?php
ob_start();
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login'])) {
    header('Location: ../login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

$selected_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$display_date = date('d M Y, l', strtotime($selected_date));
$emp_code = $_SESSION['employee_code'] ?? '';
$emp_name = $_SESSION['employee_name'] ?? 'Employee';

// ========================================================================
// FETCH EXISTING PUNCH TIMES FOR THE SELECTED DATE
// ========================================================================
$existing_check_in = '';
$existing_check_out = '';

if (!empty($emp_code) && isset($conn)) {
    $safe_emp_code = mysqli_real_escape_string($conn, $emp_code);
    $safe_date = mysqli_real_escape_string($conn, $selected_date);

    $punch_query = "SELECT check_in_time, check_out_time FROM time_entries 
                    WHERE employee_code = '$safe_emp_code' AND entry_date = '$safe_date' LIMIT 1";
    $punch_result = mysqli_query($conn, $punch_query);
    
    if ($punch_result && mysqli_num_rows($punch_result) > 0) {
        $row = mysqli_fetch_assoc($punch_result);
        
        // HTML <input type="time"> requires 24-hour format (HH:MM)
        if (!empty($row['check_in_time']) && $row['check_in_time'] !== '00:00:00') {
            $existing_check_in = date('H:i', strtotime($row['check_in_time']));
        }
        if (!empty($row['check_out_time']) && $row['check_out_time'] !== '00:00:00') {
            $existing_check_out = date('H:i', strtotime($row['check_out_time']));
        }
    }
}

// ========================================================================
// HANDLE POST ACTIONS (Time Adjustment Request)
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'time_adjust') {
    
    $reason = $_POST['reason'] ?? '';
    $check_in = $_POST['check_in'] ?? null;
    $check_out = $_POST['check_out'] ?? null;
    $is_next_day = isset($_POST['next_day']) ? 1 : 0;
    $remarks = $_POST['remarks'] ?? '';
    $now = date('Y-m-d H:i:s');

    if (empty($reason) || empty($check_in) || empty($check_out) || empty($remarks)) {
        $_SESSION['ta_flash'] = "❌ Error: All fields are required.";
        header("Location: time_entry?date=" . urlencode($selected_date));
        exit();
    }

    $src = $conn->prepare("INSERT INTO `approval_requests`(`emp_code`, `emp_name`, `type`, `stage`, `request_date`, `requested_on`, `shift_date`, `reasons`, `status`, `created_at`) VALUES (?, ?, 'attendance', 'Stage_1', ?, ?, ?, ?, 'pending', ?)");
    
    if ($src) {
        $details = "Reason: $reason | In: $check_in | Out: $check_out " . ($is_next_day ? "(Next Day)" : "") . " | Remarks: $remarks";
        
        $src->bind_param("sssssss", $emp_code, $emp_name, $selected_date, $now, $selected_date, $details, $now);
        
        if ($src->execute()) {
            $_SESSION['ta_flash'] = "Time adjustment requested successfully!";
            $src->close();
            // Redirect to AttendanceOverview on Success
            header("Location: AttendanceOverview");
            exit();
        } else {
            $_SESSION['ta_flash'] = "❌ Error submitting request: " . $src->error;
        }
        $src->close();
    } else {
        $_SESSION['ta_flash'] = "❌ SQL Prepare Error: " . $conn->error;
    }
    
    // Redirect back to current page on Failure
    header("Location: time_entry?date=" . urlencode($selected_date));
    exit();
}

$flash_message = $_SESSION['ta_flash'] ?? '';
unset($_SESSION['ta_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Time Entry</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="16x16" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="apple-touch-icon" href="/rhythm_payroll/includes/assets/img/apple-touch-icon.png">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* Hide scrollbar */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Custom Time Input Icon overriding to make the whole field clickable on mobile */
        input[type="time"]::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
            z-index: 10;
        }

        /* Toggle switch hidden input */
        #next_day_checkbox:checked + div > div {
            transform: translateX(1.25rem); /* 20px */
        }
        #next_day_checkbox:checked + div {
            background-color: #1c212d;
        }
    </style>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen">

    <!-- Mobile App Container -->
    <div class="w-full max-w-md bg-[#f4f5f9] min-h-screen flex flex-col font-sans shadow-2xl relative overflow-hidden">

        <!-- Header Section -->
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[60px]">
            <a href="javascript:history.back()" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition no-underline">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center mr-[80px]"> 
                <h1 class="font-semibold text-[17px]">Time Entry</h1>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto no-scrollbar p-4 pb-28">

            <!-- Flash Message Alerts -->
            <?php if(!empty($flash_message)): ?>
                <?php $is_error = strpos($flash_message, '❌') !== false; ?>
                <div class="<?= $is_error ? 'bg-red-100 text-red-700 border-red-200' : 'bg-green-100 text-green-700 border-green-200' ?> px-4 py-3 rounded-xl border mb-4 text-sm font-medium flex items-center">
                    <i class="fa-solid <?= $is_error ? 'fa-circle-exclamation' : 'fa-circle-check' ?> mr-2 text-lg"></i>
                    <?= htmlspecialchars($flash_message) ?>
                </div>
            <?php endif; ?>

            <form id="timeEntryForm" action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="time_adjust">
                <input type="hidden" name="entry_date" value="<?= htmlspecialchars($selected_date) ?>">

                <!-- Primary Details Card -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    
                    <!-- Shift Date (Read-only) -->
                    <div class="mb-5 relative">
                        <label class="block text-gray-800 text-[14px] font-semibold mb-2">Shift Date</label>
                        <div class="relative">
                            <div class="w-full bg-[#f4f5f9] border border-gray-200 text-gray-600 text-[15px] font-medium rounded-lg p-3.5 pl-10 cursor-not-allowed">
                                <?= htmlspecialchars($display_date) ?>
                            </div>
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                <i class="fa-regular fa-calendar text-[15px]"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Reason Dropdown -->
                    <div class="relative">
                        <label class="block text-gray-800 text-[14px] font-semibold mb-2">Reason <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="reason" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[15px] rounded-lg focus:ring-[#1c212d] focus:border-[#1c212d] block p-3.5 appearance-none" required>
                                <option value="" disabled selected>Select adjustment reason</option>
                                <option value="Missed Punch">Missed Punch</option>
                                <option value="On Duty">On Duty</option>
                                <option value="Work From Home">Work From Home</option>
                                <option value="System Error">System Error</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-chevron-down text-sm"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timings Card -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    
                    <div class="grid grid-cols-2 gap-3 mb-5">
                        <!-- Check In (Pre-filled if exists) -->
                        <div class="relative">
                            <label class="block text-gray-800 text-[13px] font-semibold mb-1.5">Check In <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="time" name="check_in" value="<?= htmlspecialchars($existing_check_in) ?>" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[15px] rounded-lg p-3 pl-9 focus:ring-[#1c212d] focus:border-[#1c212d]" required>
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                    <i class="fa-regular fa-clock text-[14px]"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Check Out (Pre-filled if exists) -->
                        <div class="relative">
                            <label class="block text-gray-800 text-[13px] font-semibold mb-1.5">Check Out <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="time" name="check_out" value="<?= htmlspecialchars($existing_check_out) ?>" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[15px] rounded-lg p-3 pl-9 focus:ring-[#1c212d] focus:border-[#1c212d]" required>
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                    <i class="fa-regular fa-clock text-[14px]"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Day Toggle -->
                    <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                        <div>
                            <label class="text-gray-800 text-[14px] font-semibold block">Next Day Checkout?</label>
                            <span class="text-gray-400 text-[11px]">Enable if shift ends past midnight</span>
                        </div>
                        <label for="next_day_checkbox" class="flex items-center cursor-pointer">
                            <input type="checkbox" id="next_day_checkbox" name="next_day" class="sr-only">
                            <div class="w-11 h-6 bg-gray-200 rounded-full transition duration-200 ease-in-out relative">
                                <div class="w-5 h-5 bg-white rounded-full shadow absolute left-[2px] top-[2px] transition duration-200 ease-in-out"></div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Remarks Card -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-4">
                    <label class="block text-gray-800 text-[14px] font-semibold mb-2">Remarks <span class="text-red-500">*</span></label>
                    <textarea name="remarks" rows="3" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[14px] rounded-lg p-3 focus:ring-[#1c212d] focus:border-[#1c212d] resize-none" placeholder="Provide additional details..." required></textarea>
                </div>
            </form>
        </main>

        <!-- Fixed Bottom Button Area -->
        <div class="absolute bottom-0 left-0 right-0 bg-white border-t border-gray-100 p-4 pb-6 z-20 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
            <!-- Ensure button checks on load in case fields are pre-filled -->
            <button type="submit" form="timeEntryForm" id="submitBtn" class="w-full bg-gray-300 text-gray-500 rounded-xl py-3.5 font-bold text-[16px] transition-all duration-200" disabled>
                Submit Request
            </button>
        </div>

    </div>

    <!-- JS for Validation -->
    <script>
        const form = document.getElementById('timeEntryForm');
        const submitBtn = document.getElementById('submitBtn');
        const requiredInputs = form.querySelectorAll('[required]');

        function checkFormValidity() {
            let isValid = true;
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                }
            });

            if (isValid) {
                // Active State Styling (Navy & Yellow)
                submitBtn.classList.remove('bg-gray-300', 'text-gray-500');
                submitBtn.classList.add('bg-[#1c212d]', 'text-yellow-400', 'shadow-md');
                submitBtn.removeAttribute('disabled');
            } else {
                // Disabled State Styling (Gray)
                submitBtn.classList.remove('bg-[#1c212d]', 'text-yellow-400', 'shadow-md');
                submitBtn.classList.add('bg-gray-300', 'text-gray-500');
                submitBtn.setAttribute('disabled', 'true');
            }
        }
        
        // Listen for input changes
        form.addEventListener('input', checkFormValidity);
        form.addEventListener('change', checkFormValidity);

        // Run once on load just in case fields are pre-filled with punches
        document.addEventListener("DOMContentLoaded", checkFormValidity);
    </script>
</body>
</html>