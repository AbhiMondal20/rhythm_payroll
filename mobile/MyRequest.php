<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

$page_title = 'My Requests';
$emp_code = $_SESSION['employee_code'] ?? '';

$my_requests = [];

if (!empty($emp_code) && isset($conn)) {
    $safe_emp_code = mysqli_real_escape_string($conn, $emp_code);

    // ========================================================================
    // 1. FETCH LEAVE REQUESTS
    // ========================================================================
    $leave_sql = "SELECT lr.id, lr.from_date, lr.to_date, lr.reason, lr.status, lr.created_at, lr.updated_at, lt.leave_name 
                  FROM leave_requests lr 
                  LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id 
                  WHERE lr.emp_code = '$safe_emp_code'";
                  
    $leave_res = mysqli_query($conn, $leave_sql);
    if ($leave_res) {
        while ($row = mysqli_fetch_assoc($leave_res)) {
            $status = strtolower($row['status']);
            $leave_name = $row['leave_name'] ?? 'Leave';
            
            $approval_msg = 'Pending Approval';
            if ($status === 'approved') $approval_msg = 'Request is approved by Admin';
            if ($status === 'rejected') $approval_msg = 'Request is rejected by Admin';

            $my_requests[] = [
                'id' => 'l_' . $row['id'],
                'type' => 'leave',
                'status' => $status,
                'title' => 'Leave Request - ' . htmlspecialchars($leave_name),
                'subtitle' => date('d.m.Y', strtotime($row['from_date'])) . ' - ' . date('d.m.Y', strtotime($row['to_date'])),
                'remarks' => htmlspecialchars($row['reason']),
                'approver' => 'Admin', 
                'approval_time' => ($status !== 'pending' && !empty($row['updated_at'])) ? date('d.m.Y h:i A', strtotime($row['updated_at'])) : '-',
                'approval_msg' => $approval_msg,
                'timestamp' => strtotime($row['created_at'])
            ];
        }
    }

    // ========================================================================
    // 2. FETCH ATTENDANCE / TIME ADJUSTMENT REQUESTS
    // ========================================================================
    $att_sql = "SELECT id, shift_date, reasons, status, created_at, updated_at 
                FROM approval_requests 
                WHERE emp_code = '$safe_emp_code' AND type = 'attendance'";
                
    $att_res = mysqli_query($conn, $att_sql);
    if ($att_res) {
        while ($row = mysqli_fetch_assoc($att_res)) {
            $status = strtolower($row['status']);
            
            // Parse the 'reasons' string created in the Time Entry submission
            // Format was: "Reason: X | In: Y | Out: Z | Remarks: W"
            $raw_reasons = $row['reasons'];
            $subtitle = '';
            $remarks = $raw_reasons; 
            
            if (strpos($raw_reasons, '| Remarks:') !== false) {
                $parts = explode('| Remarks:', $raw_reasons);
                $remarks = trim($parts[1]);
                $subtitle_parts = explode('| In:', $parts[0]);
                if (isset($subtitle_parts[1])) {
                    $in_out = explode('| Out:', $subtitle_parts[1]);
                    $in_time = trim($in_out[0] ?? '');
                    $out_time = trim($in_out[1] ?? '');
                    $subtitle = "$in_time to $out_time";
                }
            }

            $approval_msg = 'Pending Approval';
            if ($status === 'approved') $approval_msg = 'Request is approved by Admin';
            if ($status === 'rejected') $approval_msg = 'Request is rejected by Admin';

            $my_requests[] = [
                'id' => 'a_' . $row['id'],
                'type' => 'attendance',
                'status' => $status,
                'title' => 'Attendance - ' . date('d.m.Y', strtotime($row['shift_date'])),
                'subtitle' => htmlspecialchars($subtitle),
                'remarks' => htmlspecialchars($remarks),
                'approver' => 'Admin', 
                'approval_time' => ($status !== 'pending' && !empty($row['updated_at'])) ? date('d.m.Y h:i A', strtotime($row['updated_at'])) : '-',
                'approval_msg' => $approval_msg,
                'timestamp' => strtotime($row['created_at'])
            ];
        }
    }

    // Sort combined array by timestamp descending (newest first)
    usort($my_requests, function($a, $b) {
        return $b['timestamp'] <=> $a['timestamp'];
    });
}

// Counts for the Filter Tabs
$count_all = count($my_requests);
$count_leave = count(array_filter($my_requests, fn($r) => $r['type'] === 'leave'));
$count_attendance = count(array_filter($my_requests, fn($r) => $r['type'] === 'attendance'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="16x16" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="apple-touch-icon" href="/rhythm_payroll/includes/assets/img/apple-touch-icon.png">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        .tab-active {
            background-color: #1c212d;
            color: #ffe000;
            border-color: #1c212d;
        }
        .tab-inactive {
            background-color: white;
            color: #6b7280;
            border-color: #e5e7eb;
        }
    </style>
</head>

<body class="bg-gray-100 flex justify-center min-h-screen">

    <!-- Mobile App Container -->
    <div class="w-full max-w-md bg-[#f4f5f9] min-h-screen relative flex flex-col font-sans shadow-2xl overflow-hidden">
        
        <!-- ========================================== -->
        <!-- LIST SCREEN -->
        <!-- ========================================== -->
        <div id="list-screen" class="flex flex-col w-full h-full absolute inset-0 z-10 bg-[#f4f5f9] transition-transform duration-300">
            <!-- Header -->
            <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[60px]">
                <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition no-underline">
                    <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
                </a>
                <div class="flex-1 flex justify-center mr-[80px]">
                    <h1 class="font-semibold text-[17px]">My Requests</h1>
                </div>
            </header>

            <!-- Filter Tabs -->
            <div class="bg-white border-b border-gray-100 px-4 py-3 sticky top-[60px] z-20">
                <div class="flex gap-2 overflow-x-auto no-scrollbar" id="filter-buttons">
                    <button data-filter="all" class="filter-btn tab-active px-4 py-1.5 rounded-full whitespace-nowrap shadow-sm text-[13px] font-medium border transition-all">
                        All (<?= $count_all ?>)
                    </button>
                    <button data-filter="leave" class="filter-btn tab-inactive px-4 py-1.5 rounded-full whitespace-nowrap shadow-sm text-[13px] font-medium border transition-all hover:bg-gray-50">
                        Leave (<?= $count_leave ?>)
                    </button>
                    <button data-filter="attendance" class="filter-btn tab-inactive px-4 py-1.5 rounded-full whitespace-nowrap shadow-sm text-[13px] font-medium border transition-all hover:bg-gray-50">
                        Attendance (<?= $count_attendance ?>)
                    </button>
                </div>
            </div>

            <!-- Requests List -->
            <main class="flex-1 overflow-y-auto no-scrollbar p-4 pb-10 space-y-3" id="requests-list">
                <?php foreach ($my_requests as $req): 
                    // Determine Icon and Colors based on Status
                    if ($req['status'] === 'approved') {
                        $icon_bg = 'bg-[#10b981]'; // Green
                        $icon_class = 'fa-solid fa-check';
                    } elseif ($req['status'] === 'rejected') {
                        $icon_bg = 'bg-[#ef4444]'; // Red
                        $icon_class = 'fa-solid fa-xmark';
                    } else {
                        $icon_bg = 'bg-[#f59e0b]'; // Orange/Yellow
                        $icon_class = 'fa-solid fa-clock-rotate-left';
                    }
                ?>
                
                <!-- Request Card -->
                <div class="request-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-200" data-type="<?= $req['type'] ?>">
                    
                    <!-- Card Header (Clickable to expand) -->
                    <div class="p-4 flex items-center justify-between cursor-pointer" onclick="toggleAccordion(this)">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white <?= $icon_bg ?> shrink-0 shadow-sm">
                                <i class="<?= $icon_class ?> text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-gray-800 font-semibold text-[15px] leading-tight"><?= $req['title'] ?></h3>
                                <p class="text-gray-500 text-[12px] mt-1"><?= $req['subtitle'] ?></p>
                            </div>
                        </div>
                        <div class="shrink-0 pl-2">
                            <i class="fa-solid fa-chevron-down text-gray-400 text-sm transition-transform duration-300 chevron-icon"></i>
                        </div>
                    </div>

                    <!-- Accordion Expanded Content (Hidden by default) -->
                    <div class="hidden px-4 pb-4 pt-2 border-t border-gray-50 accordion-content bg-[#fafafa]">
                        <div class="flex gap-2.5 items-start mt-2">
                            <i class="fa-regular fa-message text-gray-400 mt-1 text-sm"></i>
                            <div>
                                <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wider">Remarks</p>
                                <p class="text-[14px] text-gray-800 mt-0.5"><?= $req['remarks'] ?></p>
                            </div>
                        </div>
                        
                        <!-- Open Full Details Button -->
                        <div class="mt-4 flex justify-end">
                            <button onclick="openDetails(<?= htmlspecialchars(json_encode($req)) ?>)" class="text-[#1c212d] text-[13px] font-bold flex items-center gap-1 hover:underline">
                                View Details <i class="fa-solid fa-arrow-right text-[11px]"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
                
                <!-- Empty State -->
                <div id="empty-state" class="<?= ($count_all > 0) ? 'hidden' : '' ?> text-center py-12 bg-white rounded-xl shadow-sm border border-gray-100">
                    <i class="fa-regular fa-folder-open text-4xl mb-3 text-gray-300"></i>
                    <p class="text-gray-500 text-sm font-medium">No requests found.</p>
                </div>
            </main>
        </div>

        <!-- ========================================== -->
        <!-- DETAILS SCREEN (Hidden Initially) -->
        <!-- ========================================== -->
        <div id="details-screen" class="flex flex-col w-full h-full absolute inset-0 z-40 bg-[#f4f5f9] translate-x-full transition-transform duration-300">
            <!-- Header -->
            <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-50 h-[60px]">
                <button onclick="closeDetails()" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition">
                    <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
                </button>
                <div class="flex-1 flex justify-center mr-[80px]">
                    <h1 class="font-semibold text-[17px]">Request Details</h1>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto no-scrollbar p-4 pb-24">
                
                <!-- Approval Stages Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
                    <h3 class="text-gray-500 text-[13px] font-medium mb-4">Approval Stages</h3>
                    
                    <div class="flex items-start gap-3">
                        <div id="det-icon" class="w-8 h-8 rounded-full flex items-center justify-center text-white shrink-0 mt-0.5">
                            <i id="det-icon-class" class="text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between items-start">
                                <h4 id="det-approver" class="text-gray-800 font-semibold text-[15px]"></h4>
                                <span id="det-time" class="text-gray-400 text-[11px] font-medium mt-0.5"></span>
                            </div>
                            <p id="det-msg" class="text-gray-500 text-[13px] mt-0.5"></p>
                        </div>
                    </div>
                </div>

                <!-- Request Data Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <h3 id="det-type-title" class="text-gray-500 text-[14px] font-medium mb-5 border-b border-gray-50 pb-3"></h3>
                    
                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-400 text-[11px] font-medium">Shift Date / Period :</p>
                            <p id="det-subtitle" class="text-gray-800 text-[15px] font-medium mt-0.5"></p>
                        </div>
                        
                        <div>
                            <p class="text-gray-400 text-[11px] font-medium">Remarks :</p>
                            <p id="det-remarks" class="text-gray-800 text-[15px] font-medium mt-0.5"></p>
                        </div>
                    </div>
                </div>

            </main>

            <!-- Bottom Actions (Only show if pending) -->
            <div id="det-actions" class="absolute bottom-0 left-0 right-0 bg-white border-t border-gray-100 p-4 pb-6 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] flex gap-3">
                <button class="flex-1 py-3.5 bg-white border border-red-200 text-red-500 rounded-xl text-[15px] font-bold hover:bg-red-50 transition">
                    Cancel Request
                </button>
                <button class="flex-1 py-3.5 bg-[#ffe000] text-[#1c212d] rounded-xl text-[15px] font-bold shadow-sm hover:bg-yellow-400 transition">
                    Send Reminder
                </button>
            </div>
        </div>

    </div>

    <script>
        // ==========================================
        // 1. Accordion Logic
        // ==========================================
        function toggleAccordion(element) {
            const card = element.parentElement;
            const content = card.querySelector('.accordion-content');
            const chevron = card.querySelector('.chevron-icon');

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                chevron.classList.add('rotate-180');
                card.classList.add('border-gray-200');
            } else {
                content.classList.add('hidden');
                chevron.classList.remove('rotate-180');
                card.classList.remove('border-gray-200');
            }
        }

        // ==========================================
        // 2. Tab Filtering Logic
        // ==========================================
        document.addEventListener("DOMContentLoaded", () => {
            const filterBtns = document.querySelectorAll('.filter-btn');
            const cards = document.querySelectorAll('.request-card');
            const emptyState = document.getElementById('empty-state');

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Reset Tabs
                    filterBtns.forEach(b => {
                        b.classList.remove('tab-active');
                        b.classList.add('tab-inactive', 'hover:bg-gray-50');
                    });
                    
                    // Set Active Tab
                    btn.classList.remove('tab-inactive', 'hover:bg-gray-50');
                    btn.classList.add('tab-active');

                    // Filter Cards
                    const filterValue = btn.getAttribute('data-filter');
                    let visibleCount = 0;
                    
                    cards.forEach(card => {
                        if (filterValue === 'all' || card.getAttribute('data-type') === filterValue) {
                            card.style.display = 'block';
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    // Show Empty State if needed
                    if(visibleCount === 0) {
                        emptyState.classList.remove('hidden');
                    } else {
                        emptyState.classList.add('hidden');
                    }
                });
            });
        });

        // ==========================================
        // 3. Screen Navigation Logic (Details Page)
        // ==========================================
        const listScreen = document.getElementById('list-screen');
        const detailsScreen = document.getElementById('details-screen');

        function openDetails(requestData) {
            // Populate Details Screen Data
            document.getElementById('det-approver').innerText = requestData.approver;
            document.getElementById('det-time').innerText = requestData.approval_time;
            document.getElementById('det-msg').innerText = requestData.approval_msg;
            document.getElementById('det-type-title').innerText = requestData.type === 'leave' ? 'Leave Details -' : 'Attendance Details -';
            document.getElementById('det-subtitle').innerHTML = requestData.subtitle;
            document.getElementById('det-remarks').innerText = requestData.remarks;

            // Handle Icon Colors
            const iconWrapper = document.getElementById('det-icon');
            const iconClass = document.getElementById('det-icon-class');
            iconWrapper.className = 'w-8 h-8 rounded-full flex items-center justify-center text-white shrink-0 mt-0.5'; // reset
            
            if (requestData.status === 'approved') {
                iconWrapper.classList.add('bg-[#10b981]');
                iconClass.className = 'fa-solid fa-check text-sm';
            } else if (requestData.status === 'rejected') {
                iconWrapper.classList.add('bg-[#ef4444]');
                iconClass.className = 'fa-solid fa-xmark text-sm';
            } else {
                iconWrapper.classList.add('bg-[#f59e0b]');
                iconClass.className = 'fa-solid fa-clock-rotate-left text-sm';
            }

            // Handle Bottom Actions visibility (Only show Cancel/Reminder if Pending)
            const actionArea = document.getElementById('det-actions');
            if (requestData.status === 'pending') {
                actionArea.classList.remove('hidden');
            } else {
                actionArea.classList.add('hidden');
            }

            // Slide in the details screen
            detailsScreen.classList.remove('translate-x-full');
            listScreen.classList.add('-translate-x-10', 'opacity-50'); // subtle background shift
        }

        function closeDetails() {
            // Slide out the details screen
            detailsScreen.classList.add('translate-x-full');
            listScreen.classList.remove('-translate-x-10', 'opacity-50');
        }
    </script>
</body>
</html>