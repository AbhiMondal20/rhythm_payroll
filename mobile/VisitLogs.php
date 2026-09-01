<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login']) || empty($_SESSION['employee_code'])) {
    header('Location: login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

$page_title = 'Visit Logs';
$emp_code = $_SESSION['employee_code'];

// Fetch the visits for this employee, ordered by newest first
$sql = "SELECT * FROM employee_visits WHERE employee_code = '$emp_code' ORDER BY visit_date DESC, start_time DESC";
$result = mysqli_query($conn, $sql);
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
                <h1 class="font-semibold text-[17px]">Visit History</h1>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 pb-10">
            
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="space-y-4">
                    <?php while ($row = mysqli_fetch_assoc($result)): 
                        $visit_date = date('d M Y', strtotime($row['visit_date']));
                        $start_time = date('h:i A', strtotime($row['start_time']));
                        
                        $is_completed = !empty($row['end_time']);
                        $end_time = $is_completed ? date('h:i A', strtotime($row['end_time'])) : '--:--';
                    ?>
                    
                    <!-- Visit Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 relative overflow-hidden">
                        
                        <!-- Status Indicator Ribbon -->
                        <?php if (!$is_completed): ?>
                            <div class="absolute top-0 right-0 bg-blue-500 text-white text-[10px] font-bold px-3 py-1 rounded-bl-lg uppercase tracking-wider">
                                Active Now
                            </div>
                        <?php endif; ?>

                        <!-- Client & Date -->
                        <div class="mb-3 border-b border-gray-100 pb-3">
                            <h3 class="text-gray-800 font-bold text-lg leading-tight pr-16">
                                <?= htmlspecialchars($row['client_name']) ?>
                            </h3>
                            <div class="flex items-center text-gray-500 text-[12px] mt-1 gap-3">
                                <span><i class="fa-regular fa-calendar mr-1"></i> <?= $visit_date ?></span>
                                <?php if (!empty($row['purpose'])): ?>
                                    <span class="truncate max-w-[150px]" title="<?= htmlspecialchars($row['purpose']) ?>">
                                        <i class="fa-solid fa-bullseye mr-1"></i> <?= htmlspecialchars($row['purpose']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Timeline / Times -->
                        <div class="flex items-center justify-between bg-gray-50 rounded-lg p-3 mb-3 border border-gray-100">
                            <!-- Start -->
                            <div class="flex-1">
                                <p class="text-gray-400 text-[10px] uppercase font-bold tracking-wider mb-0.5">Started</p>
                                <p class="text-gray-800 text-[14px] font-semibold">
                                    <i class="fa-regular fa-clock text-green-500 mr-1 text-[12px]"></i> <?= $start_time ?>
                                </p>
                            </div>
                            
                            <!-- Divider Arrow -->
                            <div class="text-gray-300 px-2">
                                <i class="fa-solid fa-arrow-right-long"></i>
                            </div>

                            <!-- End -->
                            <div class="flex-1 text-right">
                                <p class="text-gray-400 text-[10px] uppercase font-bold tracking-wider mb-0.5">Ended</p>
                                <p class="<?= $is_completed ? 'text-gray-800' : 'text-gray-400' ?> text-[14px] font-semibold">
                                    <i class="fa-regular fa-clock <?= $is_completed ? 'text-red-500' : 'text-gray-300' ?> mr-1 text-[12px]"></i> <?= $end_time ?>
                                </p>
                            </div>
                        </div>

                        <!-- Action Buttons (Location / Photos) -->
                        <div class="flex gap-2 mt-2">
                            <!-- Start Location & Photo -->
                            <?php if (!empty($row['start_location'])): ?>
                                <a href="https://maps.google.com/?q=<?= htmlspecialchars($row['start_location']) ?>" target="_blank" 
                                   class="flex-1 bg-white border border-gray-200 text-gray-600 text-[12px] font-medium py-2 rounded-lg flex items-center justify-center gap-1.5 hover:bg-gray-50 transition">
                                    <i class="fa-solid fa-location-dot text-green-500"></i> Start Map
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($row['start_photo'])): ?>
                                <a href="<?= htmlspecialchars($row['start_photo']) ?>" target="_blank" 
                                   class="flex-1 bg-white border border-gray-200 text-gray-600 text-[12px] font-medium py-2 rounded-lg flex items-center justify-center gap-1.5 hover:bg-gray-50 transition">
                                    <i class="fa-regular fa-image text-green-500"></i> Start Pic
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if ($is_completed): ?>
                            <div class="flex gap-2 mt-2">
                                <!-- End Location & Photo -->
                                <?php if (!empty($row['end_location'])): ?>
                                    <a href="https://maps.google.com/?q=<?= htmlspecialchars($row['end_location']) ?>" target="_blank" 
                                       class="flex-1 bg-white border border-gray-200 text-gray-600 text-[12px] font-medium py-2 rounded-lg flex items-center justify-center gap-1.5 hover:bg-gray-50 transition">
                                        <i class="fa-solid fa-location-dot text-red-500"></i> End Map
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($row['end_photo'])): ?>
                                    <a href="<?= htmlspecialchars($row['end_photo']) ?>" target="_blank" 
                                       class="flex-1 bg-white border border-gray-200 text-gray-600 text-[12px] font-medium py-2 rounded-lg flex items-center justify-center gap-1.5 hover:bg-gray-50 transition">
                                        <i class="fa-regular fa-image text-red-500"></i> End Pic
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center mt-8">
                    <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                    <h3 class="text-gray-800 font-bold text-lg">No Visits Logged</h3>
                    <p class="text-gray-500 text-sm mt-1">You haven't logged any client visits yet.</p>
                    <a href="EmployeeVisit" class="inline-block mt-4 bg-[#1c212d] text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">
                        Log a Visit Now
                    </a>
                </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>