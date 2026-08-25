<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

$selected_date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$display_date = date('d M Y, l', strtotime($selected_date));
require_once '../includes/config.php';
require_once '../includes/db_client.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Entry</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'DM Sans', sans-serif;
            background-color: #ffffff;
        }
        
        /* Custom Time Input Icon overriding */
        input[type="time"]::-webkit-calendar-picker-indicator {
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
<body class="text-slate-800 flex flex-col min-h-screen">

    <!-- Header -->
    <header class="bg-[#121826] text-white py-4 px-4 sticky top-0 z-50 flex items-center">
        <button onclick="history.back()" class="bg-slate-200 text-[#121826] px-4 py-1.5 rounded-full font-semibold text-sm flex items-center gap-2 hover:bg-white transition">
            <i class="fa-solid fa-chevron-left"></i> Back
        </button>
        <h1 class="text-xl font-medium tracking-wide flex-1 text-center pr-16">Time Entry</h1>
    </header>

    <main class="max-w-2xl w-full mx-auto p-5 flex flex-col flex-1">
        
        <form action="" method="POST" class="flex flex-col flex-1">
            <input type="hidden" name="entry_date" value="<?= htmlspecialchars($selected_date) ?>">

            <!-- Shift Date (Read-only display) -->
            <div class="mb-5">
                <label class="block text-sm text-slate-800 mb-1.5">Shift Date*</label>
                <div class="bg-[#f4f5f9] p-3.5 rounded-lg text-slate-700 text-[15px]">
                    <?= htmlspecialchars($display_date) ?>
                </div>
            </div>

            <!-- Reason Dropdown -->
            <div class="mb-5 relative">
                <label class="block text-sm text-slate-800 mb-1.5">Reason*</label>
                <div class="relative">
                    <select name="reason" class="w-full border border-slate-200 rounded-lg p-3.5 appearance-none focus:outline-none focus:border-slate-400 bg-white text-slate-500 text-[15px]" required>
                        <option value="" disabled selected>Select reason</option>
                        <option value="Missed Punch">Missed Punch</option>
                        <option value="On Duty">On Duty</option>
                        <option value="Work From Home">Work From Home</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-4 top-[18px] text-slate-400"></i>
                </div>
            </div>

            <!-- Check In -->
            <div class="mb-5">
                <label class="block text-sm text-slate-800 mb-1.5">Check In*</label>
                <div class="relative">
                    <input type="time" name="check_in" class="w-full border border-slate-200 rounded-lg p-3.5 focus:outline-none focus:border-slate-400 bg-white text-[15px]" required>
                    <i class="fa-regular fa-clock absolute right-4 top-[18px] text-slate-500 text-lg pointer-events-none"></i>
                </div>
            </div>

            <!-- Check Out -->
            <div class="mb-5">
                <label class="block text-sm text-slate-800 mb-1.5">Check Out*</label>
                <div class="relative">
                    <input type="time" name="check_out" class="w-full border border-slate-200 rounded-lg p-3.5 focus:outline-none focus:border-slate-400 bg-white text-[15px]" required>
                    <i class="fa-regular fa-clock absolute right-4 top-[18px] text-slate-500 text-lg pointer-events-none"></i>
                </div>
            </div>

            <!-- Next Day Checkbox -->
            <div class="mb-5 flex items-center gap-2">
                <input type="checkbox" id="next_day" name="next_day" class="w-5 h-5 rounded border border-slate-300 accent-[#121826] cursor-pointer">
                <label for="next_day" class="text-slate-500 font-medium cursor-pointer">Next Day</label>
            </div>

            <!-- Remarks -->
            <div class="mb-8 flex-1">
                <label class="block text-sm text-slate-800 mb-1.5">Remarks*</label>
                <textarea name="remarks" rows="2" class="w-full border border-slate-200 rounded-lg p-3 focus:outline-none focus:border-slate-400 bg-white text-[15px]" required></textarea>
            </div>

            <!-- Submit Button (Sticky at the bottom) -->
            <div class="mt-auto pb-4 pt-4 bg-white">
                <button type="submit" class="w-full bg-[#e5e7eb] text-slate-500 font-semibold py-3.5 rounded-lg text-lg transition hover:bg-[#121826] hover:text-white">
                    Submit
                </button>
            </div>
        </form>

    </main>
</body>
</html>