<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login']) || empty($_SESSION['employee_code'])) {
    header('Location: login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

$page_title = 'Remote Attendance';
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

// Handle Form Submission (Check In / Check Out)
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
            
            // Define upload path
            $file_name = $emp_code . '_' . $action . '_' . time() . '.png';
            $upload_dir = '../uploads/attendance/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_path = $upload_dir . $file_name;
            
            if (file_put_contents($file_path, $img_base64)) {
                $photo_path = mysqli_real_escape_string($conn, $file_path);
            }
        }
    }

    if ($action === 'check_in') {
        $insert_sql = "INSERT INTO time_entries 
            (employee_code, employee_name, entry_date, check_in_time, check_in_photo, check_in_location, created_at) 
            VALUES 
            ('$emp_code', '$emp_name', '$today', '$current_time', '$photo_path', '$location_string', NOW())";
            
        if (mysqli_query($conn, $insert_sql)) {
            $message = "Checked In successfully at " . date('h:i A');
            $status_type = "success";
        } else {
            $message = "Failed to Check In. Please try again.";
            $status_type = "error";
        }
    } elseif ($action === 'check_out') {
        $update_sql = "UPDATE time_entries 
            SET check_out_time = '$current_time', 
                check_out_photo = '$photo_path', 
                check_out_location = '$location_string',
                updated_at = NOW() 
            WHERE employee_code = '$emp_code' AND entry_date = '$today' AND check_out_time IS NULL";
            
        if (mysqli_query($conn, $update_sql)) {
            $message = "Checked Out successfully at " . date('h:i A');
            $status_type = "success";
        } else {
            $message = "Failed to Check Out. Make sure you are checked in first.";
            $status_type = "error";
        }
    }
}

// Check current status for the UI buttons
$is_checked_in = false;
$is_checked_out = false;
$in_time = '--:--';
$out_time = '--:--';

$check_sql = "SELECT check_in_time, check_out_time FROM time_entries WHERE employee_code = '$emp_code' AND entry_date = '$today' LIMIT 1";
$check_res = mysqli_query($conn, $check_sql);

if ($check_res && mysqli_num_rows($check_res) > 0) {
    $record = mysqli_fetch_assoc($check_res);
    $is_checked_in = true;
    $in_time = date('h:i A', strtotime($record['check_in_time']));
    
    if (!empty($record['check_out_time'])) {
        $is_checked_out = true;
        $out_time = date('h:i A', strtotime($record['check_out_time']));
    }
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
                <h1 class="font-semibold text-[17px]">Live Attendance</h1>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 pb-28">
            
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-3 rounded-lg text-sm font-medium text-center <?= $status_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Live Clock Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center mb-4">
                <p class="text-gray-500 text-[13px] font-medium uppercase tracking-wider mb-1"><?= date('l, d M Y') ?></p>
                <h2 id="live-clock" class="text-4xl font-bold text-[#1c212d] tracking-tight mb-4">00:00:00</h2>
                
                <div class="flex justify-between border-t border-gray-100 pt-4 mt-2">
                    <div class="text-center w-1/2 border-r border-gray-100">
                        <p class="text-gray-400 text-[11px] uppercase">Check In Time</p>
                        <p class="text-gray-800 font-semibold text-[15px] mt-0.5"><?= $in_time ?></p>
                    </div>
                    <div class="text-center w-1/2">
                        <p class="text-gray-400 text-[11px] uppercase">Check Out Time</p>
                        <p class="text-gray-800 font-semibold text-[15px] mt-0.5"><?= $out_time ?></p>
                    </div>
                </div>
            </div>

            <!-- Camera & Location Section -->
            <?php if (!$is_checked_out): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4 relative overflow-hidden">
                    <h3 class="text-gray-500 text-[13px] font-medium mb-3 flex justify-between items-center">
                        Identity Verification
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

                        <!-- Permission Error State (Hidden by default) -->
                        <div id="error-overlay" class="hidden absolute inset-0 bg-white/95 flex flex-col items-center justify-center p-6 text-center z-20">
                            <div class="w-12 h-12 bg-red-100 text-red-500 rounded-full flex items-center justify-center mb-3">
                                <i class="fa-solid fa-video-slash text-xl"></i>
                            </div>
                            <h4 class="text-gray-800 font-bold text-sm mb-1">Camera Access Denied</h4>
                            <p class="text-gray-500 text-[12px] mb-4 leading-relaxed">
                                Please tap the <strong>lock icon</strong> in your browser's address bar, choose <strong>Permissions / Site Settings</strong>, and allow Camera & Location.
                            </p>
                            <button onclick="window.location.reload()" class="bg-[#1c212d] text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm hover:bg-gray-800 transition">
                                <i class="fa-solid fa-rotate-right mr-1"></i> Try Again
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Day Complete State -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center mt-8">
                    <div class="w-16 h-16 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                    <h3 class="text-gray-800 font-bold text-lg">Shift Completed</h3>
                    <p class="text-gray-500 text-sm mt-1">You have successfully logged your check in and check out times for today.</p>
                </div>
            <?php endif; ?>

        </main>

        <!-- Fixed Bottom Action Bar -->
        <?php if (!$is_checked_out): ?>
            <div class="absolute bottom-0 left-0 right-0 bg-white border-t border-gray-100 p-4 pb-6 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-40">
                <form id="attendance-form" method="POST" action="">
                    <input type="hidden" name="action" id="action-input" value="">
                    <input type="hidden" name="latitude" id="lat-input" value="">
                    <input type="hidden" name="longitude" id="lng-input" value="">
                    <input type="hidden" name="photo" id="photo-input" value="">

                    <div class="flex gap-3">
                        <!-- Check In Button (Disabled if already checked in) -->
                        <button type="button" id="btn-check-in" 
                            class="flex-1 py-3.5 bg-[#10b981] text-white rounded-xl text-[15px] font-bold shadow-sm flex flex-col justify-center items-center gap-1 hover:bg-[#059669] transition disabled:opacity-50 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed"
                            <?= $is_checked_in ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-right-to-bracket text-lg"></i>
                            Check In
                        </button>
                        
                        <!-- Check Out Button (Disabled if NOT checked in, or already checked out) -->
                        <button type="button" id="btn-check-out" 
                            class="flex-1 py-3.5 bg-[#ef4444] text-white rounded-xl text-[15px] font-bold shadow-sm flex flex-col justify-center items-center gap-1 hover:bg-[#dc2626] transition disabled:opacity-50 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed"
                            <?= (!$is_checked_in || $is_checked_out) ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-right-from-bracket text-lg"></i>
                            Check Out
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // ==========================================
        // 1. Live Clock
        // ==========================================
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            
            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            const clockEl = document.getElementById('live-clock');
            if(clockEl) clockEl.innerText = `${hours}:${minutes}:${seconds}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        <?php if (!$is_checked_out): ?>
        // ==========================================
        // 2. Camera & GPS Logic
        // ==========================================
        const video = document.getElementById('camera-stream');
        const canvas = document.getElementById('photo-canvas');
        const btnCheckIn = document.getElementById('btn-check-in');
        const btnCheckOut = document.getElementById('btn-check-out');
        const gpsStatus = document.getElementById('gps-status');
        const errorOverlay = document.getElementById('error-overlay');
        const cameraGrid = document.getElementById('camera-grid');
        
        let streamActive = false;
        let coordsFetched = false;
        let hasCheckedIn = <?= $is_checked_in ? 'true' : 'false' ?>;

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
                    video: { facingMode: "user" }, 
                    audio: false 
                });
                video.srcObject = stream;
                streamActive = true;
                checkReadyState();
            } catch (err) {
                console.error("Error accessing camera: ", err);
                showPermissionError('camera');
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
                // Only enable the appropriate button based on DB status
                if (!hasCheckedIn) {
                    btnCheckIn.disabled = false;
                } else {
                    btnCheckOut.disabled = false;
                }
            } else {
                btnCheckIn.disabled = true;
                btnCheckOut.disabled = true;
            }
        }

        // Initialize Hardware requests
        btnCheckIn.disabled = true; 
        btnCheckOut.disabled = true;
        startCamera();
        getLocation();

        // Submit Action Handler
        function performAttendance(actionType, btnElement) {
            if (!streamActive || !coordsFetched) return;

            // Update UI to show loading state
            btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-lg"></i> Processing...';
            btnCheckIn.disabled = true;
            btnCheckOut.disabled = true;

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
            document.getElementById('attendance-form').submit();
        }

        // Event Listeners for both buttons
        btnCheckIn.addEventListener('click', function() {
            performAttendance('check_in', this);
        });

        btnCheckOut.addEventListener('click', function() {
            performAttendance('check_out', this);
        });

        <?php endif; ?>
    </script>
</body>
</html>