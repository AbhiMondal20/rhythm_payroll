<?php
ob_start();
session_start();

// Ensure the user is logged in and has employee_code in session
if (!isset($_SESSION['login']) || !isset($_SESSION['employee_code'])) {
    if (isset($_POST['action']) || isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: ../login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

// ==========================================
// Handle AJAX Requests (Upload Logic)
// ==========================================
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action) {
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');

    try {
        if ($action === 'upload') {
            $emp_code = trim($_SESSION['employee_code'] ?? '');
            $doc_type = trim($_POST['doc_type'] ?? '');
            
            // Allowed columns mapping directly to DB schema
            $allowed_docs = [
                'profile_photo', 'aadhaar_doc', 'pan_doc', 
                'photo_doc', 'edu_doc', 'bank_doc', 'appt_doc'
            ];

            if (empty($emp_code) || empty($doc_type) || !isset($_FILES['document'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields or file.']);
                exit();
            }

            if (!in_array($doc_type, $allowed_docs, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid document type selected.']);
                exit();
            }

            $file = $_FILES['document'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'File upload error. Code: ' . $file['error']]);
                exit();
            }

            // File Size Validation (5 MB limit)
            $max_size = 5 * 1024 * 1024;
            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'message' => 'File size exceeds the 5MB limit.']);
                exit();
            }

            // Extension Validation
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
            if (!in_array($ext, $allowed_exts, true)) {
                echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and PDF files are allowed.']);
                exit();
            }

            // MIME Type Verification
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($mime_type, $allowed_mimes, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file format detected.']);
                exit();
            }

            // Ensure upload directory exists
            $upload_dir = '../uploads/docs/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to initialize upload directory.']);
                    exit();
                }
            }

            // Generate safe unique filename
            $safe_emp_prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $emp_code);
            $new_filename = $safe_emp_prefix . '_' . $doc_type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Prepared statement for database update
                $upd_sql = "UPDATE employees SET {$doc_type} = ?, updated_at = NOW() WHERE employee_code = ?";
                $stmt = mysqli_prepare($conn, $upd_sql);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ss", $new_filename, $emp_code);
                    if (mysqli_stmt_execute($stmt)) {
                        echo json_encode(['success' => true, 'message' => 'Your document was uploaded successfully!']);
                    } else {
                        if (file_exists($target_path)) unlink($target_path);
                        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    if (file_exists($target_path)) unlink($target_path);
                    echo json_encode(['success' => false, 'message' => 'Failed to prepare database query.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file to disk.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'A server error occurred: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch employee details to display on screen
$emp_id = (int)($_SESSION['emp_id'] ?? 0);
$emp_name = "Employee";

if ($emp_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT employee_name FROM employees WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $emp_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $emp_name = $row['employee_name'];
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - Rhythm</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .thin-scrollbar::-webkit-scrollbar { width: 4px; }
        .thin-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .thin-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen">

    <div class="w-full max-w-md bg-[#f4f5f9] h-[100dvh] relative flex flex-col font-sans shadow-2xl overflow-hidden text-gray-800">

        <!-- Header -->
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 shrink-0 z-20 h-[60px]">
            <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center mr-[80px]"> 
                <h1 class="font-semibold text-[17px]">Upload My Document</h1>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto thin-scrollbar p-5 space-y-6">
            
            <!-- Informational Banner -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex gap-3">
                <i class="fa-solid fa-shield-check text-blue-500 mt-0.5"></i>
                <div class="text-sm text-blue-800 leading-snug">
                    <strong>Hi <?= htmlspecialchars($emp_name, ENT_QUOTES, 'UTF-8') ?>,</strong><br>
                    Upload your documents securely here. Maximum file size is 5MB (JPG, PNG, PDF).
                </div>
            </div>

            <!-- Document Type -->
            <div>
                <label for="docType" class="block text-sm font-semibold text-gray-700 mb-1.5">Document Type <span class="text-red-500">*</span></label>
                <div class="relative">
                    <select id="docType" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white shadow-sm appearance-none cursor-pointer">
                        <option value="" disabled selected>Choose document type...</option>
                        <option value="profile_photo">Profile Photo</option>
                        <option value="aadhaar_doc">Aadhaar Card</option>
                        <option value="pan_doc">PAN Card</option>
                        <option value="photo_doc">ID Photo</option>
                        <option value="edu_doc">Educational Certificate</option>
                        <option value="bank_doc">Bank Passbook / Cancelled Cheque</option>
                        <option value="appt_doc">Appointment Letter</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-gray-400 pointer-events-none"></i>
                </div>
            </div>

            <!-- File Uploader Area -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Select File <span class="text-red-500">*</span></label>
                <div id="dropZone" onclick="document.getElementById('fileInput').click()" class="w-full border-2 border-dashed border-gray-300 rounded-xl p-8 flex flex-col items-center justify-center text-center cursor-pointer bg-white hover:bg-gray-50 hover:border-blue-400 transition shadow-sm">
                    <div id="fileIcon" class="w-14 h-14 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center text-2xl mb-3 transition-colors">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <p id="fileNameDisplay" class="text-sm font-bold text-gray-700 mb-1">Tap to browse files</p>
                    <p id="fileSubText" class="text-xs text-gray-400">JPG, PNG, PDF up to 5MB</p>
                    <input type="file" id="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.pdf" onchange="handleFileSelect(this)">
                </div>
            </div>

        </main>

        <!-- Submit Button -->
        <div class="p-4 bg-white border-t border-gray-100 shrink-0">
            <button id="btnUpload" onclick="uploadDocument()" class="w-full py-3.5 rounded-xl bg-[#1c212d] text-white font-bold text-[15px] shadow-lg hover:bg-gray-800 transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrow-up-from-bracket"></i> Upload Document
            </button>
        </div>
    </div>

    <script>
    let selectedFile = null;

    // --- File Handling UI ---
    function handleFileSelect(input) {
        if (input.files && input.files[0]) {
            selectedFile = input.files[0];
            const nameDisplay = document.getElementById('fileNameDisplay');
            const subText = document.getElementById('fileSubText');
            const icon = document.getElementById('fileIcon');
            const dropZone = document.getElementById('dropZone');

            nameDisplay.textContent = selectedFile.name;
            
            let size = (selectedFile.size / 1024 / 1024).toFixed(2);
            subText.textContent = `${size} MB`;
            subText.classList.remove('text-gray-400');
            subText.classList.add('text-green-600', 'font-medium');

            icon.innerHTML = '<i class="fa-solid fa-file-circle-check"></i>';
            icon.className = "w-14 h-14 bg-green-50 text-green-500 rounded-full flex items-center justify-center text-2xl mb-3 transition-colors";
            dropZone.classList.add('border-green-300', 'bg-green-50/30');
            dropZone.classList.remove('border-gray-300', 'bg-white');
        }
    }

    function resetFileUI() {
        selectedFile = null;
        document.getElementById('fileInput').value = '';
        document.getElementById('fileNameDisplay').textContent = 'Tap to browse files';
        
        const subText = document.getElementById('fileSubText');
        subText.textContent = 'JPG, PNG, PDF up to 5MB';
        subText.className = "text-xs text-gray-400";
        
        const icon = document.getElementById('fileIcon');
        icon.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i>';
        icon.className = "w-14 h-14 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center text-2xl mb-3 transition-colors";
        
        const dropZone = document.getElementById('dropZone');
        dropZone.classList.remove('border-green-300', 'bg-green-50/30');
        dropZone.classList.add('border-gray-300', 'bg-white');
    }

    // --- Upload Logic ---
    function uploadDocument() {
        const docType = document.getElementById('docType').value;
        if (!docType) return Swal.fire('Wait', 'Please choose a document type.', 'warning');
        if (!selectedFile) return Swal.fire('Wait', 'Please select a file to upload.', 'warning');

        const btn = document.getElementById('btnUpload');
        btn.disabled = true; 
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
        btn.classList.replace('bg-[#1c212d]', 'bg-gray-500');

        const fd = new FormData();
        fd.append('action', 'upload');
        fd.append('doc_type', docType);
        fd.append('document', selectedFile);

        fetch(window.location.href, { method: 'POST', body: fd }) 
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('Server non-JSON response:', text);
                    throw new Error('Server returned an invalid response.');
                }
            })
            .then(res => {
                if (res.success) {
                    Swal.fire({
                        title: 'Uploaded!',
                        text: res.message,
                        icon: 'success',
                        confirmButtonColor: '#1c212d',
                        customClass: { popup: 'rounded-2xl', confirmButton: 'rounded-lg px-6' }
                    }).then(() => {
                        document.getElementById('docType').value = '';
                        resetFileUI();
                    });
                } else { 
                    Swal.fire('Error', res.message, 'error'); 
                }
            })
            .catch(err => {
                console.error('Upload Error:', err);
                Swal.fire('Error', err.message || 'Network error or upload failed.', 'error');
            })
            .finally(() => { 
                btn.disabled = false; 
                btn.innerHTML = '<i class="fa-solid fa-arrow-up-from-bracket"></i> Upload Document'; 
                btn.classList.replace('bg-gray-500', 'bg-[#1c212d]');
            });
    }
    </script>
</body>
</html>