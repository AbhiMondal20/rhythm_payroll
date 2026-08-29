<?php
ob_start();
session_start();

// Ensure the user is logged in
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

$emp_code = trim($_SESSION['employee_code']);

// ==========================================
// Handle AJAX Upload Requests
// ==========================================
if (($_POST['action'] ?? '') === 'upload') {
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');

    try {
        $doc_type = trim($_POST['doc_type'] ?? '');
        
        $allowed_docs = [
            'profile_photo', 'aadhaar_doc', 'pan_doc', 
            'photo_doc', 'edu_doc', 'bank_doc', 'appt_doc'
        ];

        if (empty($doc_type) || !isset($_FILES['document'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields or file.']);
            exit();
        }

        if (!in_array($doc_type, $allowed_docs, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid document type.']);
            exit();
        }

        $file = $_FILES['document'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Upload error. Code: ' . $file['error']]);
            exit();
        }

        // File Size Validation (5 MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
            exit();
        }

        // Extension & MIME Type Validation
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];

        if (!in_array($ext, $allowed_exts, true) || !in_array($mime_type, $allowed_mimes, true)) {
            echo json_encode(['success' => false, 'message' => 'Only valid JPG, PNG, and PDF files are allowed.']);
            exit();
        }

        // Initialize upload directory
        $upload_dir = '../uploads/docs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate safe unique filename
        $safe_emp_prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $emp_code);
        $new_filename = $safe_emp_prefix . '_' . $doc_type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target_path = $upload_dir . $new_filename;

        // Fetch old file to delete later (optional cleanup)
        $old_file = null;
        $stmt_old = mysqli_prepare($conn, "SELECT {$doc_type} FROM employees WHERE employee_code = ?");
        if ($stmt_old) {
            mysqli_stmt_bind_param($stmt_old, "s", $emp_code);
            mysqli_stmt_execute($stmt_old);
            $res = mysqli_stmt_get_result($stmt_old);
            if ($row = mysqli_fetch_assoc($res)) {
                $old_file = $row[$doc_type];
            }
            mysqli_stmt_close($stmt_old);
        }

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Update Database
            $upd_sql = "UPDATE employees SET {$doc_type} = ?, updated_at = NOW() WHERE employee_code = ?";
            $stmt = mysqli_prepare($conn, $upd_sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $new_filename, $emp_code);
                if (mysqli_stmt_execute($stmt)) {
                    // Clean up old file if it exists
                    if (!empty($old_file) && file_exists($upload_dir . $old_file)) {
                        unlink($upload_dir . $old_file);
                    }
                    echo json_encode(['success' => true, 'message' => 'Document updated successfully!']);
                } else {
                    unlink($target_path);
                    echo json_encode(['success' => false, 'message' => 'Database update failed.']);
                }
                mysqli_stmt_close($stmt);
            } else {
                unlink($target_path);
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

// ==========================================
// Fetch Employee Documents for Display
// ==========================================
$emp_docs = [];
$stmt = mysqli_prepare($conn, "SELECT profile_photo, aadhaar_doc, pan_doc, photo_doc, edu_doc, bank_doc, appt_doc FROM employees WHERE employee_code = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $emp_code);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $emp_docs = $row;
    }
    mysqli_stmt_close($stmt);
}

// Define the document metadata for UI rendering
$docDefinitions = [
    'profile_photo' => ['title' => 'Profile Photo', 'icon' => 'fa-user-circle'],
    'aadhaar_doc'   => ['title' => 'Aadhaar Card', 'icon' => 'fa-id-card'],
    'pan_doc'       => ['title' => 'PAN Card', 'icon' => 'fa-money-check'],
    'photo_doc'     => ['title' => 'ID Photo', 'icon' => 'fa-camera'],
    'edu_doc'       => ['title' => 'Educational Certificate', 'icon' => 'fa-graduation-cap'],
    'bank_doc'      => ['title' => 'Bank Passbook / Cheque', 'icon' => 'fa-building-columns'],
    'appt_doc'      => ['title' => 'Appointment Letter', 'icon' => 'fa-file-signature']
];
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

    <div class="w-full max-w-2xl bg-[#f4f5f9] min-h-[100dvh] flex flex-col font-sans shadow-2xl overflow-hidden text-gray-800">

        <!-- Header -->
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 shrink-0 z-20 h-[60px] sticky top-0">
            <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center mr-[80px]"> 
                <h1 class="font-semibold text-[17px]">My Documents</h1>
            </div>
        </header>

        <main class="flex-1 p-5 space-y-4">
            
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex gap-3 mb-2">
                <i class="fa-solid fa-circle-info text-blue-500 mt-0.5"></i>
                <div class="text-sm text-blue-800 leading-snug">
                    View your uploaded documents below. You can update any document by tapping the <strong>Update</strong> button. Allowed formats: JPG, PNG, PDF (Max 5MB).
                </div>
            </div>

            <!-- Documents Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($docDefinitions as $key => $doc): 
                    $file_path = $emp_docs[$key] ?? null;
                    $is_uploaded = !empty($file_path);
                ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 flex flex-col justify-between">
                    <div class="flex items-start gap-4 mb-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 <?= $is_uploaded ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400' ?>">
                            <i class="fa-solid <?= $doc['icon'] ?> text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-[15px]"><?= htmlspecialchars($doc['title']) ?></h3>
                            <?php if ($is_uploaded): ?>
                                <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-md bg-green-100 text-green-700 text-xs font-semibold">
                                    <i class="fa-solid fa-check"></i> Uploaded
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-md bg-red-50 text-red-600 text-xs font-semibold">
                                    <i class="fa-solid fa-xmark"></i> Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex gap-2 border-t border-gray-100 pt-3">
                        <?php if ($is_uploaded): ?>
                            <a href="../uploads/docs/<?= htmlspecialchars($file_path) ?>" target="_blank" class="flex-1 text-center py-2 bg-gray-50 border border-gray-200 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-100 transition">
                                <i class="fa-solid fa-eye mr-1"></i> View
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="document.getElementById('file_<?= $key ?>').click()" class="flex-1 text-center py-2 bg-blue-50 border border-blue-100 text-blue-600 rounded-lg text-sm font-semibold hover:bg-blue-100 transition">
                            <i class="fa-solid fa-arrow-up-from-bracket mr-1"></i> <?= $is_uploaded ? 'Update' : 'Upload' ?>
                        </button>
                        
                        <!-- Hidden File Input for this specific document -->
                        <input type="file" id="file_<?= $key ?>" class="hidden" accept=".jpg,.jpeg,.png,.pdf" onchange="processUpload(this, '<?= $key ?>', '<?= addslashes($doc['title']) ?>')">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </main>
    </div>

    <script>
    function processUpload(inputElement, docKey, docTitle) {
        if (!inputElement.files || inputElement.files.length === 0) return;
        
        const file = inputElement.files[0];
        
        // Frontend Validations
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            Swal.fire('Too Large', 'File size must be less than 5MB.', 'error');
            inputElement.value = ''; // reset
            return;
        }

        // Confirm Upload
        Swal.fire({
            title: 'Confirm Upload',
            html: `Are you sure you want to upload this file for <strong>${docTitle}</strong>?<br><br><span class="text-sm text-gray-500">${file.name}</span>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1c212d',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, upload it!',
            customClass: { popup: 'rounded-2xl', confirmButton: 'rounded-lg px-6', cancelButton: 'rounded-lg px-6' }
        }).then((result) => {
            if (result.isConfirmed) {
                uploadFile(file, docKey);
            } else {
                inputElement.value = ''; // reset if cancelled
            }
        });
    }

    function uploadFile(file, docType) {
        Swal.fire({
            title: 'Uploading...',
            text: 'Please wait while we secure your document.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const fd = new FormData();
        fd.append('action', 'upload');
        fd.append('doc_type', docType);
        fd.append('document', file);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(async r => {
                if (!r.ok) throw new Error(`HTTP Error ${r.status}`);
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Non-JSON Response:", text);
                    throw new Error("Invalid response from server.");
                }
            })
            .then(res => {
                if (res.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: res.message,
                        icon: 'success',
                        confirmButtonColor: '#1c212d',
                        customClass: { popup: 'rounded-2xl', confirmButton: 'rounded-lg px-6' }
                    }).then(() => {
                        window.location.reload(); // Reload to show new document link/status
                    });
                } else {
                    Swal.fire('Upload Failed', res.message, 'error');
                }
            })
            .catch(err => {
                console.error("Upload error:", err);
                Swal.fire('Error', 'A network or server error occurred.', 'error');
            })
            .finally(() => {
                // Clear the hidden inputs so the same file can trigger 'onchange' again if needed
                document.getElementById('file_' + docType).value = ''; 
            });
    }
    </script>
</body>
</html>