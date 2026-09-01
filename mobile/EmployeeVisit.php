<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login']) || empty($_SESSION['employee_code'])) {
    header('Location: login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

$page_title = 'Employee Visit';
$emp_code = $_SESSION['employee_code'];
$today = date('Y-m-d');
$current_time = date('H:i:s');
$message = '';
$status_type = '';

// Fetch Employee Details
$emp_name = '';
$emp_sql = "SELECT employee_name FROM employees WHERE employee_code = '$emp_code' LIMIT 1";
$emp_res = mysqli_query($conn, $emp_sql);
if ($emp_res && mysqli_num_rows($emp_res) > 0) {
    $row = mysqli_fetch_assoc($emp_res);
    $emp_name = $row['employee_name'];
}

// Handle Form Submission (Start Visit / End Visit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $lat = mysqli_real_escape_string($conn, $_POST['latitude']);
    $lng = mysqli_real_escape_string($conn, $_POST['longitude']);
    $photo_base64 = $_POST['photo'];
    
    $location_string = "$lat,$lng";
    $photo_path = '';

    // Save Base64 Photo to Server
    if (!empty($photo_base64)) {
        $img_parts = explode(";base64,", $photo_base64);
        if (count($img_parts) == 2) {
            $img_type_aux = explode("image/", $img_parts[0]);
            $img_type = $img_type_aux[1];
            $img_base64 = base64_decode($img_parts[1]);
            
            // Define upload path for visits
            $file_name = $emp_code . '_visit_' . $action . '_' . time() . '.png';
            $upload_dir = '../uploads/visits/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_path = $upload_dir . $file_name;
            
            if (file_put_contents($file_path, $img_base64)) {
                $photo_path = mysqli_real_escape_string($conn, $file_path);
            }
        }
    }

    if ($action === 'start_visit') {
        $client_name = mysqli_real_escape_string($conn, $_POST['client_name']);
        $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);

        $insert_sql = "INSERT INTO employee_visits 
            (employee_code, employee_name, client_name, purpose, visit_date, start_time, start_photo, start_location, created_at) 
            VALUES 
            ('$emp_code', '$emp_name', '$client_name', '$purpose', '$today', '$current_time', '$photo_path', '$location_string', NOW())";
            
        if (mysqli_query($conn, $insert_sql)) {
            $message = "Visit started successfully at " . date('h:i A');
            $status_type = "success";
        } else {
            $message = "Failed to start visit. Please try again.";
            $status_type = "error";
        }
    } elseif ($action === 'end_visit') {
        $visit_id = mysqli_real_escape_string($conn, $_POST['active_visit_id']);

        $update_sql = "UPDATE employee_visits 
            SET end_time = '$current_time', 
                end_photo = '$photo_path', 
                end_location = '$location_string'
            WHERE id = '$visit_id' AND employee_code = '$emp_code' AND end_time IS NULL";
            
        if (mysqli_query($conn, $update_sql)) {
            $message = "Visit ended successfully at " . date('h:i A');
            $status_type = "success";
        } else {
            $message = "Failed to end visit. Please try again.";
            $status_type = "error";
        }
    }
}

// Check current status (Are they currently on an active visit?)
$is_visiting = false;
$active_visit_id = '';
$active_client = '';
$start_time = '--:--';

// Look for an ongoing visit today where end_time is NULL
$check_sql = "SELECT id, client_name, start_time FROM employee_visits WHERE employee_code = '$emp_code' AND visit_date = '$today' AND end_time IS NULL ORDER BY id DESC LIMIT 1";
$check_res = mysqli_query($conn, $check_sql);

if ($check_res && mysqli_num_rows($check_res) > 0) {
    $record = mysqli_fetch_assoc($check_res);
    $is_visiting = true;
    $active_visit_id = $record['id'];
    $active_client = $record['client_name'];
    $start_time = date('h:i A', strtotime($record['start_time']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex justify-center min-h-screen">

    <div class="w-full max-w-md bg-[#f4f5f9] min-h-screen relative flex flex-col font-sans shadow-2xl overflow-hidden">
        
        <!-- Header -->
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[60px]">
            <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition no-underline">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center mr-[80px]">
                <h1 class="font-semibold text-[17px]">Log Visit</h1>
            </div>
        </header>

        <form id="visit-form" method="POST" action="" class="flex-1 flex flex-col relative h-full">
            <main class="flex-1 overflow-y-auto p-4 pb-28">
                
                <?php if (!empty($message)): ?>
                    <div class="mb-4 p-3 rounded-lg text-sm font-medium text-center <?= $status_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <!-- Visit Details Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
                    <h3 class="text-gray-800 font-bold mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-handshake text-[#1c212d]"></i> Visit Details
                    </h3>

                    <?php if ($is_visiting): ?>
                        <!-- Active Visit View -->
                        <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-center">
                            <span class="inline-block px-3 py-1 bg-blue-100 text-blue-700 text-[11px] font-bold uppercase tracking-wider rounded-full mb-2">Active Visit</span>
                            <h2 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($active_client) ?></h2>
                            <p class="text-gray-500 text-sm">Started at: <strong><?= $start_time ?></strong></p>
                        </div>
                        <input type="hidden" name="active_visit_id" value="<?= $active_visit_id ?>">
                    <?php else: ?>
                        <!-- New Visit Inputs -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Client / Company Name *</label>
                                <input type="text" id="client_name" name="client_name" required placeholder="Enter client or location name" 
                                    class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-[13px] font-medium text-gray-700 mb-1">Purpose of Visit</label>
                                <textarea id="purpose" name="purpose" rows="2" placeholder="Briefly describe the purpose" 
                                    class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition"></textarea>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Camera & Location Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4 relative overflow-hidden">
                    <h3 class="text-gray-500 text-[13px] font-medium mb-3 flex justify-between items-center">
                        Location Proof
                        <span id="gps-status" class="text-[11px] text-red-500 flex items-center gap-1">
                            <i class="fa-solid fa-location-dot"></i> Fetching GPS...
                        </span>
                    </h3>
                    
                    <!-- Camera Viewfinder -->
                    <div class="w-full h-64 bg-gray-200 rounded-lg overflow-hidden relative border border-gray-100">
                        <video id="camera-stream" autoplay playsinline class="w-full h-full object-cover"></video>
                        <canvas id="photo-canvas" class="hidden"></canvas>
                        
                        <!-- Camera overlay grid -->
                        <div id="camera-grid" class="absolute inset-0 pointer-events-none border-[2px] border-white/20 m-4 rounded"></div>

                        <!-- Permission Error State -->
                        <div id="error-overlay" class="hidden absolute inset-0 bg-white/95 flex flex-col items-center justify-center p-6 text-center z-20">
                            <div class="w-12 h-12 bg-red-100 text-red-500 rounded-full flex items-center justify-center mb-3">
                                <i class="fa-solid fa-video-slash text-xl"></i>
                            </div>
                            <h4 class="text-gray-800 font-bold text-sm mb-1">Camera Access Denied</h4>
                            <p class="text-gray-500 text-[12px] mb-4 leading-relaxed">
                                Please allow Camera & Location access in your browser settings to log visits.
                            </p>
                            <button type="button" onclick="window.location.reload()" class="bg-[#1c212d] text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm hover:bg-gray-800 transition">
                                <i class="fa-solid fa-rotate-right mr-1"></i> Try Again
                            </button>
                        </div>
                    </div>
                </div>

            </main>

            <!-- Fixed Bottom Action Bar -->
            <div class="absolute bottom-0 left-0 right-0 bg-white border-t border-gray-100 p-4 pb-6 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-40">
                <input type="hidden" name="action" id="action-input" value="">
                <input type="hidden" name="latitude" id="lat-input" value="">
                <input type="hidden" name="longitude" id="lng-input" value="">
                <input type="hidden" name="photo" id="photo-input" value="">

                <div class="flex gap-3">
                    <?php if (!$is_visiting): ?>
                        <!-- Start Visit Button -->
                        <button type="button" id="btn-start-visit" 
                            class="w-full py-3.5 bg-[#10b981] text-white rounded-xl text-[16px] font-bold shadow-sm flex justify-center items-center gap-2 hover:bg-[#059669] transition disabled:opacity-50 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-play"></i>
                            Start Visit
                        </button>
                    <?php else: ?>
                        <!-- End Visit Button -->
                        <button type="button" id="btn-end-visit" 
                            class="w-full py-3.5 bg-[#ef4444] text-white rounded-xl text-[16px] font-bold shadow-sm flex justify-center items-center gap-2 hover:bg-[#dc2626] transition disabled:opacity-50 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-stop"></i>
                            End Visit
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

    </div>

    <script>
        // ==========================================
        // Camera & GPS Logic
        // ==========================================
        const video = document.getElementById('camera-stream');
        const canvas = document.getElementById('photo-canvas');
        const btnStart = document.getElementById('btn-start-visit');
        const btnEnd = document.getElementById('btn-end-visit');
        const gpsStatus = document.getElementById('gps-status');
        const errorOverlay = document.getElementById('error-overlay');
        const cameraGrid = document.getElementById('camera-grid');
        const clientInput = document.getElementById('client_name');
        
        let streamActive = false;
        let coordsFetched = false;
        let isVisiting = <?= $is_visiting ? 'true' : 'false' ?>;

        function showPermissionError(type) {
            video.classList.add('hidden');
            cameraGrid.classList.add('hidden');
            errorOverlay.classList.remove('hidden');
            
            if(type === 'location') {
                errorOverlay.querySelector('h4').innerText = "Location Access Denied";
                errorOverlay.querySelector('i').className = "fa-solid fa-location-dot text-xl";
            }
        }

        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: "environment" }, // Prioritize back camera for visits
                    audio: false 
                });
                video.srcObject = stream;
                streamActive = true;
                checkReadyState();
            } catch (err) {
                console.error("Error accessing camera: ", err);
                
                // Fallback to front camera if back camera is not available
                try {
                    const fallbackStream = await navigator.mediaDevices.getUserMedia({ 
                        video: { facingMode: "user" }, 
                        audio: false 
                    });
                    video.srcObject = fallbackStream;
                    streamActive = true;
                    checkReadyState();
                } catch (fallbackErr) {
                    showPermissionError('camera');
                }
            }
        }

        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        document.getElementById('lat-input').value = position.coords.latitude;
                        document.getElementById('lng-input').value = position.coords.longitude;
                        
                        gpsStatus.innerHTML = '<i class="fa-solid fa-location-dot"></i> GPS Locked';
                        gpsStatus.classList.replace('text-red-500', 'text-[#10b981]');
                        coordsFetched = true;
                        checkReadyState();
                    },
                    (error) => {
                        console.error("Error accessing location: ", error);
                        gpsStatus.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> GPS Failed';
                        showPermissionError('location');
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            } else {
                showPermissionError('location');
            }
        }

        function checkReadyState() {
            if(streamActive && coordsFetched) {
                if (!isVisiting && btnStart) {
                    btnStart.disabled = false;
                } else if (isVisiting && btnEnd) {
                    btnEnd.disabled = false;
                }
            } else {
                if (btnStart) btnStart.disabled = true;
                if (btnEnd) btnEnd.disabled = true;
            }
        }

        // Initialize Hardware requests
        if (btnStart) btnStart.disabled = true; 
        if (btnEnd) btnEnd.disabled = true;
        startCamera();
        getLocation();

        // Submit Action Handler
        function performAction(actionType, btnElement) {
            if (!streamActive || !coordsFetched) return;

            // Validate client name if starting a visit
            if (actionType === 'start_visit') {
                if(clientInput && clientInput.value.trim() === '') {
                    alert("Please enter a Client/Company Name.");
                    clientInput.focus();
                    return;
                }
            }

            // Update UI to show loading state
            btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-lg"></i> Processing...';
            btnElement.disabled = true;

            // Set the form action
            document.getElementById('action-input').value = actionType;

            // Draw video frame to canvas
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Convert to Base64 image
            const photoData = canvas.toDataURL('image/png');
            document.getElementById('photo-input').value = photoData;

            // Submit the form
            document.getElementById('visit-form').submit();
        }

        // Event Listeners
        if (btnStart) {
            btnStart.addEventListener('click', function() {
                performAction('start_visit', this);
            });
        }

        if (btnEnd) {
            btnEnd.addEventListener('click', function() {
                performAction('end_visit', this);
            });
        }
    </script>
</body>
</html>