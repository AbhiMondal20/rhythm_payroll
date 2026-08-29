<?php
ob_start();
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: ../login');
    exit();
}
require_once '../includes/config.php';
require_once '../includes/db_client.php';

$emp_code = $_SESSION['employee_code'] ?? '';
$reimbursement_types = [];
$grouped_fields = [];
$alert_script = '';

if (isset($conn)) {

    // ==========================================
    // 1. HANDLE FORM SUBMISSION (INSERT DATA)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $safe_emp_code = mysqli_real_escape_string($conn, $emp_code);
        $type_id = mysqli_real_escape_string($conn, $_POST['reimbursement_type_id']);
        $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
        $amount = mysqli_real_escape_string($conn, $_POST['amount']);
        $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
        
        // Handle File Uploads
        $uploaded_files = [];
        if (!empty($_FILES['documents']['name'][0])) {
            $upload_dir = '../uploads/reimbursements/';
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($_FILES['documents']['name'] as $key => $filename) {
                $tmp_name = $_FILES['documents']['tmp_name'][$key];
                // Clean filename to prevent issues
                $clean_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($filename));
                $target_path = $upload_dir . $clean_filename;
                
                if (move_uploaded_file($tmp_name, $target_path)) {
                    $uploaded_files[] = $clean_filename;
                }
            }
        }
        $documents_str = mysqli_real_escape_string($conn, implode(',', $uploaded_files));

        // Insert into Main Table (Adjust table name 'reimbursement_requests' as per your DB)
        $insert_main_sql = "INSERT INTO reimbursement_requests 
                            (emp_code, reimbursement_type_id, expense_date, amount, remarks, documents, status, created_at) 
                            VALUES 
                            ('$safe_emp_code', '$type_id', '$expense_date', '$amount', '$remarks', '$documents_str', 'Pending', NOW())";

        if (mysqli_query($conn, $insert_main_sql)) {
            $request_id = mysqli_insert_id($conn); // Get the inserted ID

            // Insert Dynamic Fields
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'dynamic_') === 0) {
                    $field_name = mysqli_real_escape_string($conn, substr($key, 8)); // Remove 'dynamic_' prefix
                    $field_value = mysqli_real_escape_string($conn, $value);
                    
                    // Insert into details/dynamic table (Adjust table name as per your DB)
                    $insert_dynamic_sql = "INSERT INTO reimbursement_dynamic_values 
                                           (request_id, field_name, field_value) 
                                           VALUES 
                                           ('$request_id', '$field_name', '$field_value')";
                    mysqli_query($conn, $insert_dynamic_sql);
                }
            }

            // Success Alert
            $alert_script = "Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Reimbursement applied successfully.',
                confirmButtonColor: '#1c212d'
            }).then(() => {
                window.location.href = 'AppDashboard'; // Redirect to list page or refresh
            });";
        } else {
            // Error Alert
            $error_msg = mysqli_error($conn);
            $alert_script = "Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Something went wrong! " . addslashes($error_msg) . "',
                confirmButtonColor: '#1c212d'
            });";
        }
    }

    // ==========================================
    // 2. FETCH DATA FOR FORM DISPLAY
    // ==========================================
    
    // Fetch Reimbursement Types
    $types_query = "SELECT `id`, `type_name`, `type_code`, `remarks`, `max_limit`, `receipt_required`, `claim_period`, `monthly_limit`, `yearly_limit`, `applicable_to` FROM `reimbursement_types` WHERE 1";
    $types_result = mysqli_query($conn, $types_query);
    
    if ($types_result) {
        while ($row = mysqli_fetch_assoc($types_result)) {
            $reimbursement_types[] = $row;
        }
    }

    // Fetch Dynamic Fields
    $fields_query = "SELECT `id`, `reimbursement_type_id`, `field_name`, `field_type`, `is_required` FROM `reimbursement_fields` WHERE 1";
    $fields_result = mysqli_query($conn, $fields_query);
    
    if ($fields_result) {
        while ($row = mysqli_fetch_assoc($fields_result)) {
            $type_id = $row['reimbursement_type_id'];
            if (!isset($grouped_fields[$type_id])) {
                $grouped_fields[$type_id] = [];
            }
            $grouped_fields[$type_id][] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reimbursement Form</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Hide scrollbar for clean app-like look */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Ensure date input placeholder works properly on iOS/Webkit */
        input[type="date"]::-webkit-calendar-picker-indicator {
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
    </style>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen font-sans text-gray-800">

    <!-- Mobile App Container -->
    <div class="w-full max-w-md bg-white min-h-screen relative flex flex-col shadow-2xl overflow-hidden">
        
        <!-- Header Section -->
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[65px]">
            <a href="javascript:history.back()" class="bg-[#e4e6eb] text-gray-900 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition no-underline">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center pr-[70px]">
                <h1 class="font-medium text-[17px] tracking-wide">Reimbursement</h1>
            </div>
        </header>

        <!-- Main Form Content Area -->
        <main class="flex-1 overflow-y-auto no-scrollbar p-5 flex flex-col">
            
            <form id="reimbursementForm" action="" method="POST" enctype="multipart/form-data" class="flex flex-col flex-1">
                
                <!-- Select Reimbursement Type -->
                <div class="mb-5">
                    <label class="block text-[14px] font-medium text-gray-800 mb-1.5">
                        Select Reimbursement Type<span class="text-gray-800">*</span>
                    </label>
                    <div class="relative">
                        <select id="reimbursement_type" name="reimbursement_type_id" required class="w-full h-[48px] border border-gray-200 rounded-lg px-3 appearance-none bg-white text-gray-700 text-[15px] focus:outline-none focus:border-gray-400 transition-colors cursor-pointer" onchange="loadDynamicFields(this.value)">
                            <option value="" disabled selected class="text-gray-300">Select one</option>
                            <?php foreach ($reimbursement_types as $type): ?>
                                <option value="<?= htmlspecialchars($type['id']) ?>">
                                    <?= htmlspecialchars($type['type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-500">
                            <i class="fa-solid fa-chevron-down text-sm"></i>
                        </div>
                    </div>
                </div>

                <!-- Container for Dynamic Fields -->
                <div id="dynamic-fields-container"></div>

                <!-- Select Expense Date -->
                <div class="mb-5">
                    <label class="block text-[14px] font-medium text-gray-800 mb-1.5">
                        Select Expense Date<span class="text-gray-800">*</span>
                    </label>
                    <div class="relative">
                        <input type="date" name="expense_date" required class="w-full h-[48px] border border-gray-200 rounded-lg px-3 bg-white text-gray-700 text-[15px] focus:outline-none focus:border-gray-400 transition-colors">
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-regular fa-calendar text-lg"></i>
                        </div>
                    </div>
                </div>

                <!-- Amount -->
                <div class="mb-5">
                    <label class="block text-[14px] font-medium text-gray-800 mb-1.5">
                        Amount<span class="text-gray-800">*</span>
                    </label>
                    <input type="number" name="amount" step="0.01" placeholder="Type here" required class="w-full h-[48px] border border-gray-200 rounded-lg px-3 bg-white text-gray-700 text-[15px] placeholder-gray-300 focus:outline-none focus:border-gray-400 transition-colors">
                </div>

                <!-- Remarks -->
                <div class="mb-5">
                    <label class="block text-[14px] font-medium text-gray-800 mb-1.5">
                        Remarks
                    </label>
                    <input type="text" name="remarks" placeholder="Type here" class="w-full h-[48px] border border-gray-200 rounded-lg px-3 bg-white text-gray-700 text-[15px] placeholder-gray-300 focus:outline-none focus:border-gray-400 transition-colors">
                </div>

                <!-- Attach Documents -->
                <div class="mb-8">
                    <label class="block text-[14px] font-medium text-gray-800 mb-1.5">
                        Attach Documents
                    </label>
                    <label class="w-full h-[60px] bg-[#f4f5f9] rounded-lg flex items-center justify-center cursor-pointer hover:bg-gray-200 transition-colors relative">
                        <div class="flex items-center gap-2 text-gray-700">
                            <i class="fa-solid fa-cloud-arrow-up text-lg"></i>
                            <span class="text-[14px] font-medium" id="file-name-display">Browse files to upload</span>
                        </div>
                        <input type="file" name="documents[]" class="hidden" multiple accept="image/*,.pdf,.doc,.docx" onchange="updateFileName(this)">
                    </label>
                </div>

                <div class="flex-1"></div>

                <!-- Apply Button -->
                <div class="mt-4 pb-4">
                    <button type="submit" id="submitBtn" class="w-full bg-[#1c212d] text-white font-medium text-[16px] py-3.5 rounded-lg transition-colors hover:bg-gray-800 shadow-md">
                        Apply
                    </button>
                </div>

            </form>
        </main>
    </div>

    <script>
        // Trigger SweetAlert if a PHP message exists
        <?php if (!empty($alert_script)) echo $alert_script; ?>

        const dynamicFieldsData = <?= json_encode($grouped_fields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function loadDynamicFields(typeId) {
            const container = document.getElementById('dynamic-fields-container');
            container.innerHTML = ''; 

            const fields = dynamicFieldsData[typeId];

            if (fields && fields.length > 0) {
                fields.forEach(field => {
                    const isRequired = parseInt(field.is_required) === 1;
                    const requiredHtml = isRequired ? '<span class="text-gray-800">*</span>' : '';
                    const requiredAttr = isRequired ? 'required' : '';
                    const safeFieldName = field.field_name.replace(/[^a-zA-Z0-9_]/g, '_').toLowerCase();
                    
                    let inputHtml = '';
                    const fieldType = field.field_type.toLowerCase();

                    if (fieldType === 'date') {
                        inputHtml = `
                            <div class="relative">
                                <input type="date" name="dynamic_${safeFieldName}" ${requiredAttr} class="w-full h-[48px] border border-gray-200 rounded-lg px-3 bg-white text-gray-700 text-[15px] focus:outline-none focus:border-gray-400 transition-colors">
                                <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-regular fa-calendar text-lg"></i>
                                </div>
                            </div>
                        `;
                    } else if (fieldType === 'number') {
                        inputHtml = `<input type="number" step="any" name="dynamic_${safeFieldName}" placeholder="Type here" ${requiredAttr} class="w-full h-[48px] border border-gray-200 rounded-lg px-3 bg-white text-gray-700 text-[15px] placeholder-gray-300 focus:outline-none focus:border-gray-400 transition-colors">`;
                    } else {
                        inputHtml = `<input type="text" name="dynamic_${safeFieldName}" placeholder="Type here" ${requiredAttr} class="w-full h-[48px] border border-gray-200 rounded-lg px-3 bg-white text-gray-700 text-[15px] placeholder-gray-300 focus:outline-none focus:border-gray-400 transition-colors">`;
                    }

                    const wrapper = document.createElement('div');
                    wrapper.className = 'mb-5 dynamic-field-animation';
                    wrapper.innerHTML = `
                        <label class="block text-[14px] font-medium text-gray-800 mb-1.5">
                            ${field.field_name}${requiredHtml}
                        </label>
                        ${inputHtml}
                    `;
                    
                    container.appendChild(wrapper);
                });
            }
        }

        function updateFileName(input) {
            const display = document.getElementById('file-name-display');
            if (input.files && input.files.length > 1) {
                display.textContent = input.files.length + ' files selected';
                display.classList.add('text-gray-900', 'font-semibold');
            } else if (input.files && input.files.length === 1) {
                display.textContent = input.files[0].name;
                display.classList.add('text-gray-900', 'font-semibold');
            } else {
                display.textContent = 'Browse files to upload';
                display.classList.remove('text-gray-900', 'font-semibold');
            }
        }
    </script>
</body>
</html>