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

$policies = [];
$policy_count = 0;

if (isset($conn)) {
    // Fetch active policies from the database
    $query = "SELECT `id`, `policy_name`, `file_name`, `file_url`, `manual_groups`, `is_deleted`, `created_by`, `created_at`, `updated_at` 
              FROM `hr_policies` 
              WHERE is_deleted = 0 
              ORDER BY `created_at` DESC";
              
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $policy_count = mysqli_num_rows($result);
        while ($row = mysqli_fetch_assoc($result)) {
            $policies[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Policies & Links</title>
     <link rel="icon" type="image/png" sizes="32x32" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="16x16" href="/rhythm_payroll/includes/assets/img/favicon.svg">
    <link rel="apple-touch-icon" href="/rhythm_payroll/includes/assets/img/apple-touch-icon.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Hide scrollbar for a clean app-like look */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Modal Transition */
        .modal-enter { opacity: 0; transform: translateY(10px); }
        .modal-enter-active { opacity: 1; transform: translateY(0); transition: all 0.3s ease-out; }
    </style>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen font-sans">
    <!-- Mobile App Container -->
    <div class="w-full max-w-md bg-[#f4f5f9] min-h-screen relative flex flex-col shadow-2xl overflow-hidden">
        <!-- Header Section -->
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[65px]">
            <!-- Back Button -->
            <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-900 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition no-underline">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <!-- Title -->
            <div class="flex-1 flex justify-center pr-[70px]">
                <h1 class="font-medium text-[16px] tracking-wide">Policies & Links</h1>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto no-scrollbar pb-10">
            
            <!-- Tab / Filter Section -->
            <div class="px-4 py-4">
                <span class="inline-block bg-[#1c212d] text-[#facc15] px-5 py-2 rounded-full text-[13px] font-medium shadow-sm border border-[#1c212d]">
                    Hr Policies (<?= $policy_count ?>)
                </span>
            </div>

            <!-- Policies List -->
            <div class="px-4 space-y-3">
                <?php if ($policy_count > 0): ?>
                    <?php foreach ($policies as $policy): ?>
                        <?php 
                            // Format date to match "09 Jul 2026"
                            $formatted_date = date('d M Y', strtotime($policy['created_at']));
                            
                            // File URL & Name
                            $file_url = !empty($policy['file_url']) ? htmlspecialchars($policy['file_url']) : '#';
                            $file_name = !empty($policy['file_name']) ? htmlspecialchars($policy['file_name']) : 'Document';
                            
                            // Determine File Extension
                            // First try to get from file_name, fallback to parsing URL
                            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            if (empty($ext)) {
                                $ext = strtolower(pathinfo(parse_url($file_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                            }
                            
                            $is_pdf = ($ext === 'pdf');
                            $is_word = in_array($ext, ['doc', 'docx']);
                            
                            // Icons based on file type
                            $icon_class = 'fa-file-lines text-gray-600';
                            if ($is_pdf) $icon_class = 'fa-file-pdf text-[#dc2626]'; // Red for PDF
                            elseif ($is_word) $icon_class = 'fa-file-word text-[#2563eb]';
                        ?>
                        
                        <?php if ($is_pdf): ?>
                            <!-- PDF File -> Preview Modal Trigger -->
                            <a href="javascript:void(0)" onclick="openPdfPreview('http://localhost/rhythm_payroll/<?= $file_url ?>')" class="block bg-white rounded-xl p-3 flex items-center gap-4 shadow-sm border border-gray-100 hover:shadow-md transition-shadow cursor-pointer">
                        <?php else: ?>
                            <!-- Word/Other File -> Direct Download -->
                            <!-- Note: The 'download' attribute forces download. target="_blank" is a fallback for cross-origin URLs. -->
                            <a href="http://localhost/rhythm_payroll/<?= $file_url ?>" download="http://localhost/rhythm_payroll/<?= $file_name ?>" target="_blank" class="block bg-white rounded-xl p-3 flex items-center gap-4 shadow-sm border border-gray-100 hover:shadow-md transition-shadow cursor-pointer">
                        <?php endif; ?>
                            
                            <!-- Document Icon Box -->
                            <div class="bg-[#eef0f6] w-[52px] h-[52px] rounded-lg flex items-center justify-center shrink-0 border border-gray-50">
                                <i class="fa-regular <?= $icon_class ?> text-[22px]"></i>
                            </div>

                            <!-- Policy Info -->
                            <div class="flex-1">
                                <h2 class="text-gray-900 font-medium text-[15px] leading-tight mb-1">
                                    <?= htmlspecialchars($policy['policy_name']) ?>
                                </h2>
                                <p class="text-gray-400 text-[12px] font-medium flex justify-between items-center pr-2">
                                    <span><?= $formatted_date ?></span>
                                    <!-- Contextual Action Label -->
                                    <?php if ($is_pdf): ?>
                                        <span class="text-gray-400"><i class="fa-solid fa-eye text-[10px] mr-1"></i>Preview</span>
                                    <?php else: ?>
                                        <span class="text-gray-400"><i class="fa-solid fa-arrow-down text-[10px] mr-1"></i>Download</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-12 bg-white rounded-xl shadow-sm border border-gray-100">
                        <i class="fa-regular fa-folder-open text-4xl mb-3 text-gray-300"></i>
                        <p class="text-gray-500 text-sm font-medium">No HR Policies found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        
        <!-- PDF Preview Modal Overlay -->
        <div id="pdfModal" class="fixed inset-0 z-50 hidden bg-[#1c212d] flex flex-col max-w-md mx-auto">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 bg-black/20 text-white shadow-sm">
                <div class="flex items-center flex-1 overflow-hidden">
                    <button onclick="closePdfPreview()" class="mr-3 bg-white/10 hover:bg-white/20 rounded-full w-8 h-8 flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-arrow-left text-sm"></i>
                    </button>
                    <h2 id="pdfTitle" class="font-medium text-[15px] truncate pr-4 text-gray-100">Document Preview</h2>
                </div>
            </div>
            
            <!-- Iframe wrapper -->
            <div class="flex-1 bg-white relative w-full h-full">
                <!-- Loading Spinner (Shows before PDF loads) -->
                <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
                    <i class="fa-solid fa-circle-notch fa-spin text-3xl mb-2"></i>
                    <span class="text-sm">Loading PDF...</span>
                </div>
                <!-- The actual iframe -->
                <iframe id="pdfFrame" class="relative z-10 w-full h-full bg-white" src="" frameborder="0" style="border: none;"></iframe>
            </div>
        </div>

    </div>

    <script>
        // PDF Modal Logic
        const modal = document.getElementById('pdfModal');
        const pdfFrame = document.getElementById('pdfFrame');
        const pdfTitle = document.getElementById('pdfTitle');

        function openPdfPreview(url, title) {
            pdfTitle.textContent = title;
            pdfFrame.src = "https://docs.google.com/gview?url=" + encodeURIComponent(url) + "&embedded=true";            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.add('modal-enter-active');
            }, 10);
        }

        function closePdfPreview() {
            modal.classList.remove('modal-enter-active');
            setTimeout(() => {
                modal.classList.add('hidden');
                pdfFrame.src = "";
            }, 300);
        }
    </script>
</body>
</html>