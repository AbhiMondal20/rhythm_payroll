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

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$now = date('Y-m-d H:i:s');
$logged_in_emp_id = (int)($_SESSION['emp_id'] ?? 0);

// ========================================================================
// AJAX SEARCH ENDPOINT FOR EMPLOYEES (Reliever & Notify)
// ========================================================================
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $search = '%' . $_GET['ajax_search'] . '%';
    $stmt = $conn->prepare("SELECT id, employee_name, employee_code FROM employees WHERE employee_name LIKE ? OR employee_code LIKE ? LIMIT 10");
    $data = [];
    if ($stmt) {
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    }
    echo json_encode($data);
    exit();
}

// ========================================================================
// HANDLE POST ACTIONS (Apply Leave)
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_leave') {
    // Force the employee ID to be the logged-in user
    $e_id = $logged_in_emp_id;
    $lt_id = (int)($_POST['leave_type_id'] ?? 0);
    
    if ($e_id <= 0 || $lt_id <= 0) {
        $_SESSION['lv_flash'] = "❌ Error: Invalid Employee or Leave Type selected.";
        header("Location: ApplyLeave");
        exit();
    }

    $from = $_POST['from_date'];
    $to = $_POST['to_date'];
    $day_type = isset($_POST['is_half_day']) ? 'Half Day' : 'Full Day';
    $reason = $_POST['reason'] ?? '';
    
    // New fields from the mobile design
    $reliever_id = !empty($_POST['reliever_id']) ? (int)$_POST['reliever_id'] : NULL;
    $notify_others = $_POST['notify_others'] ?? '';

    // Fetch Employee Details
    $emp_res = $conn->query("SELECT employee_code, employee_name FROM employees WHERE id = $e_id");
    $emp_data = $emp_res ? $emp_res->fetch_assoc() : [];
    $emp_code = $emp_data['employee_code'] ?? '';
    $emp_name = $emp_data['employee_name'] ?? '';

    // Fetch Leave Type Details
    $lt_res = $conn->query("SELECT leave_code, leave_name FROM leave_types WHERE id = $lt_id");
    $lt_data = $lt_res ? $lt_res->fetch_assoc() : [];
    $leave_code = $lt_data['leave_code'] ?? '';
    $leave_name = $lt_data['leave_name'] ?? '';

    // 1. Insert into leave_requests (Updated with reliever_id & notify_others)
    $ins = $conn->prepare("INSERT INTO leave_requests (emp_id, emp_code, leave_type_id, from_date, to_date, day_type, reason, reliever_id, notify_others, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    
    if ($ins) {
        $ins->bind_param("isissssis", $e_id, $emp_code, $lt_id, $from, $to, $day_type, $reason, $reliever_id, $notify_others);
        
        if (!$ins->execute()) {
            $_SESSION['lv_flash'] = "❌ Error inserting leave request: " . $ins->error;
            $ins->close();
            header("Location: ApplyLeave");
            exit();
        }
        $ins->close();

        // 2. Insert into approval_requests
        $src = $conn->prepare("INSERT INTO `approval_requests`(`emp_code`, `emp_name`, `type`, `stage`, `request_date`, `requested_on`, `shift_date`, `leave_type`, `reasons`, `status`, `created_at`) VALUES (?, ?, 'leave', 'Stage_1', ?, ?, ?, ?, ?, 'pending', ?)");
        if ($src) {
            $src->bind_param("ssssssss", $emp_code, $emp_name, $from, $now, $from, $leave_name, $reason, $now);
            $src->execute();
            $src->close();
        }

        // 3. Deduct Balance Immediately from leave_accumulations
        $datetime1 = new DateTime($from);
        $datetime2 = new DateTime($to);
        $interval = $datetime1->diff($datetime2);
        $leave_days = (int)$interval->format('%a') + 1;
        
        if (isset($_POST['is_half_day'])) {
            $leave_days = 0.5;
        }

        $deduct_stmt = $conn->prepare("
            UPDATE leave_accumulations 
            SET balance = balance - ? 
            WHERE emp_id = ? AND leave_type_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        if ($deduct_stmt) {
            $deduct_stmt->bind_param("dii", $leave_days, $e_id, $lt_id);
            if(!$deduct_stmt->execute()) {
                error_log("Failed to deduct balance: " . $deduct_stmt->error);
            }
            $deduct_stmt->close();
        }

        $_SESSION['lv_flash'] = "Leave applied successfully!";
        header("Location: ApplyLeave");
        exit();
    } else {
        $_SESSION['lv_flash'] = "❌ SQL Prepare Error: " . $conn->error;
        header("Location: ApplyLeave");
        exit();
    }
}

$flash_message = $_SESSION['lv_flash'] ?? '';
unset($_SESSION['lv_flash']);

// Fetch Leave Types for the UI
$leave_types = [];
$lt_res = $conn->query("SELECT id, leave_name FROM leave_types ORDER BY leave_name ASC");
if ($lt_res) {
    while($row = $lt_res->fetch_assoc()) {
        $leave_types[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Apply Leave</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="16x16" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="apple-touch-icon" href="/rhythm_payroll/includes/assets/img/apple-touch-icon.png">
    
    <style>
        /* Hide scrollbar */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Autocomplete Dropdown styling */
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            z-index: 50;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            margin-top: 4px;
        }
        .autocomplete-item {
            padding: 10px 14px;
            font-size: 14px;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
        }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover { background-color: #f9fafb; color: #111827; }

        /* Toggle switch hidden input */
        #half_day_checkbox:checked + div > div {
            transform: translateX(1.25rem); /* 20px */
        }
        #half_day_checkbox:checked + div {
            background-color: #1c212d;
        }
    </style>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen">

    <!-- Mobile App Container -->
    <div class="w-full max-w-md bg-[#f4f5f9] min-h-screen flex flex-col font-sans shadow-2xl relative overflow-hidden">
        
        <!-- Header Section -->
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[60px]">
            <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition no-underline">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center mr-[80px]"> <!-- Offset to perfectly center text -->
                <h1 class="font-semibold text-[17px]">Apply Leave</h1>
            </div>
        </header>

        <!-- Main Form Content -->
        <main class="flex-1 overflow-y-auto no-scrollbar p-4 pb-28">
            
            <!-- Flash Message Alerts -->
            <?php if(!empty($flash_message)): ?>
                <?php $is_error = strpos($flash_message, '❌') !== false; ?>
                <div class="<?= $is_error ? 'bg-red-100 text-red-700 border-red-200' : 'bg-green-100 text-green-700 border-green-200' ?> px-4 py-3 rounded-xl border mb-4 text-sm font-medium flex items-center">
                    <i class="fa-solid <?= $is_error ? 'fa-circle-exclamation' : 'fa-circle-check' ?> mr-2 text-lg"></i>
                    <?= htmlspecialchars($flash_message) ?>
                </div>
            <?php endif; ?>

            <form id="leaveForm" method="POST" action="" class="space-y-4">
                <input type="hidden" name="action" value="apply_leave">

                <!-- Primary Details Card -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    
                    <!-- Leave Type Dropdown -->
                    <div class="mb-5 relative">
                        <label class="block text-gray-800 text-[14px] font-semibold mb-2">Leave Type <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="leave_type_id" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[15px] rounded-lg focus:ring-[#1c212d] focus:border-[#1c212d] block p-3.5 appearance-none" required>
                                <option value="" disabled selected>Select Leave Type</option>
                                <?php foreach($leave_types as $lt): ?>
                                    <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['leave_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-chevron-down text-sm"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Half Day Toggle -->
                    <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-5">
                        <label class="text-gray-800 text-[14px] font-semibold">Half Day Application</label>
                        <label for="half_day_checkbox" class="flex items-center cursor-pointer">
                            <input type="checkbox" id="half_day_checkbox" name="is_half_day" class="sr-only">
                            <div class="w-11 h-6 bg-gray-200 rounded-full transition duration-200 ease-in-out relative">
                                <div class="w-5 h-5 bg-white rounded-full shadow absolute left-[2px] top-[2px] transition duration-200 ease-in-out"></div>
                            </div>
                        </label>
                    </div>

                    <!-- Date Selection Grid -->
                    <div class="grid grid-cols-2 gap-3 mb-2">
                        <!-- From Date -->
                        <div class="relative">
                            <label class="block text-gray-800 text-[13px] font-semibold mb-1.5">From <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="text" name="from_date" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[14px] rounded-lg p-3 pl-9 focus:ring-[#1c212d] focus:border-[#1c212d]" placeholder="DD/MM/YYYY" onfocus="(this.type='date')" required>
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                    <i class="fa-regular fa-calendar text-[15px]"></i>
                                </div>
                            </div>
                        </div>

                        <!-- To Date -->
                        <div class="relative">
                            <label class="block text-gray-800 text-[13px] font-semibold mb-1.5">To <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="text" name="to_date" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[14px] rounded-lg p-3 pl-9 focus:ring-[#1c212d] focus:border-[#1c212d]" placeholder="DD/MM/YYYY" onfocus="(this.type='date')" required>
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                    <i class="fa-regular fa-calendar text-[15px]"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Details Card -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    
                    <!-- Reason Box -->
                    <div class="mb-4">
                        <label class="block text-gray-800 text-[14px] font-semibold mb-2">Reason</label>
                        <textarea name="reason" rows="2" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[14px] rounded-lg p-3 focus:ring-[#1c212d] focus:border-[#1c212d] resize-none" placeholder="Briefly state your reason..."></textarea>
                    </div>

                    <!-- Reliever Autocomplete -->
                    <div class="mb-4 relative">
                        <label class="block text-gray-800 text-[14px] font-semibold mb-2">Reliever <span class="text-gray-400 font-normal text-xs">(Optional)</span></label>
                        <div class="relative">
                            <input type="text" id="reliever_search" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[14px] rounded-lg p-3 pl-9 focus:ring-[#1c212d] focus:border-[#1c212d]" placeholder="Search employee" autocomplete="off">
                            <input type="hidden" name="reliever_id" id="reliever_id">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-user-group text-[13px]"></i>
                            </div>
                        </div>
                        <!-- AJAX Results Container -->
                        <div id="reliever_results" class="autocomplete-results"></div>
                    </div>

                    <!-- Notify Others -->
                    <div class="relative">
                        <label class="block text-gray-800 text-[14px] font-semibold mb-2">Notify Others <span class="text-gray-400 font-normal text-xs">(Optional)</span></label>
                        <div class="relative">
                            <input type="text" name="notify_others" class="w-full bg-[#f8f9fa] border border-gray-200 text-gray-800 text-[14px] rounded-lg p-3 pl-9 focus:ring-[#1c212d] focus:border-[#1c212d]" placeholder="Type names or emails...">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                <i class="fa-regular fa-bell text-[14px]"></i>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </main>

        <!-- Fixed Bottom Button Area -->
        <div class="absolute bottom-0 left-0 right-0 bg-white border-t border-gray-100 p-4 pb-6 z-20 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
            <button type="submit" form="leaveForm" id="submitBtn" class="w-full bg-gray-300 text-gray-500 rounded-xl py-3.5 font-bold text-[16px] transition-all duration-200" disabled>
                Apply Leave
            </button>
        </div>

    </div>

    <!-- JS Scripts for Validation & Autocomplete -->
    <script>
        // --- 1. Form Validation to activate "Apply" Button ---
        const form = document.getElementById('leaveForm');
        const submitBtn = document.getElementById('submitBtn');
        const requiredInputs = form.querySelectorAll('[required]');

        function checkFormValidity() {
            let isValid = true;
            requiredInputs.forEach(input => {
                if (!input.value) isValid = false;
            });

            if (isValid) {
                // Active State Styling
                submitBtn.classList.remove('bg-gray-300', 'text-gray-500');
                submitBtn.classList.add('bg-[#1c212d]', 'text-yellow-400', 'shadow-md');
                submitBtn.removeAttribute('disabled');
            } else {
                // Disabled State Styling
                submitBtn.classList.remove('bg-[#1c212d]', 'text-yellow-400', 'shadow-md');
                submitBtn.classList.add('bg-gray-300', 'text-gray-500');
                submitBtn.setAttribute('disabled', 'true');
            }
        }
        
        form.addEventListener('input', checkFormValidity);
        form.addEventListener('change', checkFormValidity);

        // --- 2. AJAX Live Search Logic for Reliever ---
        const searchInput = document.getElementById('reliever_search');
        const hiddenInput = document.getElementById('reliever_id');
        const resultsBox = document.getElementById('reliever_results');

        let timeout = null;

        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim();
            
            if(query.length < 2) {
                resultsBox.style.display = 'none';
                hiddenInput.value = ''; // Clear ID if user clears input
                return;
            }

            timeout = setTimeout(() => {
                fetch(`?ajax_search=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        resultsBox.innerHTML = '';
                        if(data.length > 0) {
                            data.forEach(emp => {
                                const div = document.createElement('div');
                                div.className = 'autocomplete-item';
                                div.innerHTML = `<strong>${emp.employee_name}</strong> <span class="text-gray-400 text-xs ml-1">(${emp.employee_code})</span>`;
                                div.onclick = function() {
                                    searchInput.value = emp.employee_name;
                                    hiddenInput.value = emp.id;
                                    resultsBox.style.display = 'none';
                                };
                                resultsBox.appendChild(div);
                            });
                            resultsBox.style.display = 'block';
                        } else {
                            resultsBox.style.display = 'none';
                        }
                    });
            }, 300); // 300ms debounce
        });

        // Hide dropdown if clicked outside
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput && e.target !== resultsBox) {
                resultsBox.style.display = 'none';
            }
        });
    </script>
</body>
</html>