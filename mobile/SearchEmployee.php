<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login']) || empty($_SESSION['employee_code'])) {
    header('Location: login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

$page_title = 'Search Employees';
$search_query = '';
$results = [];

// Handle Search Request
if (isset($_GET['q'])) {
    $search_query = mysqli_real_escape_string($conn, trim($_GET['q']));
    
    if (!empty($search_query)) {
        // Fetch extended details for the modal
        $sql = "SELECT 
                    id, employee_code, employee_name, designation, department, 
                    official_email, phone, profile_photo, status,
                    blood, gender, dob, location, join_date, 
                    pan, uan, aadhaar, emg_name, emg_phone 
                FROM employees 
                WHERE (employee_name LIKE '%$search_query%' 
                   OR employee_code LIKE '%$search_query%' 
                   OR phone LIKE '%$search_query%'
                   OR official_email LIKE '%$search_query%'
                   OR department LIKE '%$search_query%'
                   OR designation LIKE '%$search_query%')
                ORDER BY employee_name ASC LIMIT 50";
                
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $profile_photo = !empty($row['profile_photo']) ? $row['profile_photo'] : '../includes/assets/img/default_avatar.png';
                // Formatting dates and handling nulls for JS passing
                $row['join_date_fmt'] = !empty($row['join_date']) ? date('d M Y', strtotime($row['join_date'])) : 'N/A';
                $row['dob_fmt'] = !empty($row['dob']) ? date('d M Y', strtotime($row['dob'])) : 'N/A';
                $row['photo_url'] = "../" . $profile_photo;
                
                // Redact Sensitive Information (Strict Privacy Handling)
                $row['aadhaar_display'] = !empty($row['aadhaar']) ? '[Aadhaar Redacted]' : 'N/A';
                
                $results[] = $row;
            }
        }
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
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[60px] shadow-md">
            <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition no-underline">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center mr-[80px]">
                <h1 class="font-semibold text-[17px]">Directory</h1>
            </div>
        </header>

        <!-- Search Bar Section -->
        <div class="bg-white px-4 py-4 border-b border-gray-200 sticky top-[60px] z-20">
            <form action="" method="GET" class="relative">
                <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search by name, code, email..." 
                    class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3.5 pl-11 outline-none transition shadow-sm" autofocus>
                <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <?php if (!empty($search_query)): ?>
                    <a href="SearchEmployee" class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-red-500 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php else: ?>
                    <button type="submit" class="absolute inset-y-0 right-0 flex items-center pr-4 text-blue-500 font-medium hover:text-blue-700 transition">
                        Search
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <main class="flex-1 overflow-y-auto p-4 pb-10">
            
            <?php if (isset($_GET['q'])): ?>
                <div class="mb-3 text-sm text-gray-500 font-medium px-1">
                    Found <?= count($results) ?> result(s) for "<span class="text-gray-800"><?= htmlspecialchars($search_query) ?></span>"
                </div>
                
                <?php if (count($results) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($results as $index => $emp): 
                            $status_color = (strtolower($emp['status']) === 'active') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                        ?>
                        
                        <!-- Employee Card (Clickable) -->
                        <div onclick="openModal(<?= $index ?>)" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-4 cursor-pointer hover:bg-gray-50 hover:shadow-md transition">
                            
                            <!-- Profile Picture -->
                            <div class="w-14 h-14 rounded-full bg-gray-200 overflow-hidden flex-shrink-0 border border-gray-200">
                                <img src="<?= $emp['photo_url'] ?>" alt="Profile" class="w-full h-full object-cover">
                            </div>

                            <!-- Employee Info -->
                            <div class="flex-1 min-w-0 pointer-events-none">
                                <h3 class="text-gray-900 font-bold text-[15px] truncate mb-0.5">
                                    <?= htmlspecialchars($emp['employee_name']) ?>
                                </h3>
                                <p class="text-gray-500 text-[12px] truncate mb-1">
                                    <?= htmlspecialchars($emp['designation']) ?> • <?= htmlspecialchars($emp['department']) ?>
                                </p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="inline-block bg-gray-100 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider border border-gray-200">
                                        <?= htmlspecialchars($emp['employee_code']) ?>
                                    </span>
                                    <span class="inline-block <?= $status_color ?> text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">
                                        <?= htmlspecialchars($emp['status'] ?? 'Active') ?>
                                    </span>
                                </div>
                            </div>

                            <!-- View Icon -->
                            <div class="flex-shrink-0 text-gray-300">
                                <i class="fa-solid fa-chevron-right"></i>
                            </div>
                            
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center mt-6">
                        <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="fa-solid fa-user-slash"></i>
                        </div>
                        <h3 class="text-gray-800 font-bold text-lg">No matches found</h3>
                        <p class="text-gray-500 text-sm mt-1">Try adjusting your search criteria.</p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="flex flex-col items-center justify-center h-[50vh] text-center px-6">
                    <div class="w-20 h-20 bg-blue-50 text-blue-300 rounded-full flex items-center justify-center mb-4 text-3xl">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h3 class="text-gray-800 font-bold text-lg mb-1">Search Directory</h3>
                    <p class="text-gray-500 text-sm">Look up your colleagues by name, employee code, or department.</p>
                </div>
            <?php endif; ?>

        </main>
        
        <!-- ==============================================
             EMPLOYEE DETAILS MODAL 
             ============================================== -->
        <div id="emp-modal" class="fixed inset-0 z-50 hidden flex justify-center">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm w-full max-w-md mx-auto" onclick="closeModal()"></div>
            
            <!-- Modal Content (Bottom Sheet Style) -->
            <div class="absolute bottom-0 w-full max-w-md bg-white rounded-t-2xl shadow-2xl transform transition-transform translate-y-full duration-300 ease-out flex flex-col max-h-[85vh]" id="modal-content">
                
                <!-- Handle bar for visual cue -->
                <div class="w-full flex justify-center pt-3 pb-1" onclick="closeModal()">
                    <div class="w-12 h-1.5 bg-gray-300 rounded-full"></div>
                </div>

                <div class="flex-1 overflow-y-auto p-5 pt-2 pb-10">
                    
                    <!-- Header: Photo & Basic Info -->
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-20 h-20 rounded-full bg-gray-100 border-2 border-gray-200 overflow-hidden">
                            <img id="m-photo" src="" alt="Profile" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1">
                            <h2 id="m-name" class="text-xl font-bold text-gray-900 leading-tight"></h2>
                            <p id="m-designation" class="text-[#3b82f6] text-[13px] font-semibold mt-0.5"></p>
                            <p id="m-department" class="text-gray-500 text-[12px] mt-0.5"></p>
                            
                            <div class="mt-2 flex gap-2">
                                <a id="m-call-btn" href="#" class="bg-green-500 text-white w-8 h-8 rounded-full flex items-center justify-center hover:bg-green-600 transition shadow-sm">
                                    <i class="fa-solid fa-phone text-sm"></i>
                                </a>
                                <a id="m-email-btn" href="#" class="bg-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center hover:bg-blue-600 transition shadow-sm">
                                    <i class="fa-solid fa-envelope text-sm"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="space-y-4">
                        
                        <!-- Work Info -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <h3 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3">Work Information</h3>
                            <div class="grid grid-cols-2 gap-y-3 gap-x-2 text-[13px]">
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Emp Code</span>
                                    <span id="m-code" class="font-semibold text-gray-800"></span>
                                </div>
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Location</span>
                                    <span id="m-location" class="font-semibold text-gray-800"></span>
                                </div>
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Date of Joining</span>
                                    <span id="m-doj" class="font-semibold text-gray-800"></span>
                                </div>
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Status</span>
                                    <span id="m-status" class="font-semibold text-gray-800 capitalize"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Info -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <h3 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3">Personal Details</h3>
                            <div class="grid grid-cols-2 gap-y-3 gap-x-2 text-[13px]">
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Phone</span>
                                    <span id="m-phone" class="font-semibold text-gray-800"></span>
                                </div>
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Blood Group</span>
                                    <span id="m-blood" class="font-semibold text-red-500"></span>
                                </div>
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Date of Birth</span>
                                    <span id="m-dob" class="font-semibold text-gray-800"></span>
                                </div>
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Gender</span>
                                    <span id="m-gender" class="font-semibold text-gray-800"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Identifiers -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                            <h3 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-3">Identifiers</h3>
                            <div class="grid grid-cols-2 gap-y-3 gap-x-2 text-[13px]">
                                <div>
                                    <span class="block text-gray-500 mb-0.5">PAN</span>
                                    <span id="m-pan" class="font-semibold text-gray-800"></span>
                                </div>
                                <div>
                                    <span class="block text-gray-500 mb-0.5">UAN</span>
                                    <span id="m-uan" class="font-semibold text-gray-800"></span>
                                </div>
                                <div class="col-span-2">
                                    <span class="block text-gray-500 mb-0.5">Aadhaar</span>
                                    <span id="m-aadhaar" class="font-semibold text-gray-800 bg-gray-200 px-2 py-0.5 rounded text-xs"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Emergency Contact -->
                        <div class="bg-red-50 rounded-xl p-4 border border-red-100">
                            <h3 class="text-[11px] font-bold text-red-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <i class="fa-solid fa-heart-pulse"></i> Emergency Contact
                            </h3>
                            <div class="grid grid-cols-1 gap-y-3 gap-x-2 text-[13px]">
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Name</span>
                                    <span id="m-emg-name" class="font-semibold text-gray-800"></span>
                                </div>
                                <div>
                                    <span class="block text-gray-500 mb-0.5">Phone</span>
                                    <span id="m-emg-phone" class="font-semibold text-gray-800"></span>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                </div>
            </div>
        </div>

    </div>

    <!-- JavaScript to handle data and modal -->
    <script>
        // Store PHP results in a Javascript array
        const employees = <?= json_encode($results) ?>;
        
        const modal = document.getElementById('emp-modal');
        const modalContent = document.getElementById('modal-content');

        function openModal(index) {
            const emp = employees[index];
            if(!emp) return;

            // Populate Data
            document.getElementById('m-photo').src = emp.photo_url;
            document.getElementById('m-name').textContent = emp.employee_name || 'N/A';
            document.getElementById('m-designation').textContent = emp.designation || 'N/A';
            document.getElementById('m-department').textContent = emp.department || 'N/A';
            document.getElementById('m-code').textContent = emp.employee_code || 'N/A';
            document.getElementById('m-location').textContent = emp.location || 'N/A';
            document.getElementById('m-doj').textContent = emp.join_date_fmt;
            document.getElementById('m-status').textContent = emp.status || 'N/A';
            
            document.getElementById('m-phone').textContent = emp.phone || 'N/A';
            document.getElementById('m-blood').textContent = emp.blood || 'N/A';
            document.getElementById('m-dob').textContent = emp.dob_fmt;
            document.getElementById('m-gender').textContent = emp.gender || 'N/A';
            
            document.getElementById('m-pan').textContent = emp.pan || 'N/A';
            document.getElementById('m-uan').textContent = emp.uan || 'N/A';
            document.getElementById('m-aadhaar').textContent = emp.aadhaar_display;
            
            document.getElementById('m-emg-name').textContent = emp.emg_name || 'N/A';
            document.getElementById('m-emg-phone').textContent = emp.emg_phone || 'N/A';

            // Setup buttons
            const callBtn = document.getElementById('m-call-btn');
            if(emp.phone) {
                callBtn.href = "tel:" + emp.phone;
                callBtn.style.display = 'flex';
            } else {
                callBtn.style.display = 'none';
            }

            const emailBtn = document.getElementById('m-email-btn');
            if(emp.official_email) {
                emailBtn.href = "mailto:" + emp.official_email;
                emailBtn.style.display = 'flex';
            } else {
                emailBtn.style.display = 'none';
            }

            // Show Modal with Animation
            modal.classList.remove('hidden');
            // Small delay to allow display:block to apply before translating for animation
            setTimeout(() => {
                modalContent.classList.remove('translate-y-full');
            }, 10);
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            // Hide Modal with Animation
            modalContent.classList.add('translate-y-full');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                // Restore body scroll
                document.body.style.overflow = 'auto';
            }, 300); // Matches transition duration
        }
    </script>
</body>
</html>