<?php
require_once 'includes/config.php';

$page_title = 'Leave Management';
$active_tab = $_GET['tab'] ?? 'insights';

$cal_year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$cal_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');

if ($cal_month < 1)  { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1;  $cal_year++; }

$hist_year  = isset($_GET['hy']) ? (int)$_GET['hy'] : (int)date('Y');
$hist_month = isset($_GET['hm']) ? (int)$_GET['hm'] : (int)date('m') - 1;

if ($hist_month < 1)  { $hist_month = 12; $hist_year--; }
if ($hist_month > 12) { $hist_month = 1;  $hist_year++; }

function esc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function month_name($m) {
    return date('M', mktime(0, 0, 0, $m, 1, 2000));
}

$leave_types = [
    ['name'=>'Compensatory Leave', 'color'=>'#F59E0B', 'count'=>66, 'pct'=>73.7, 'up'=>true],
    ['name'=>'Casual Leave/Sick Leave', 'color'=>'#7C3AED', 'count'=>27, 'pct'=>87.4, 'up'=>false],
];

$recent_requests = [
    ['type'=>'Casual Leave/Sick Leave','from'=>'25 Apr','to'=>'25 Apr','days'=>1,'status'=>'pending'],
    ['type'=>'Compensatory Leave','from'=>'24 Apr','to'=>'24 Apr','days'=>1,'status'=>'pending'],
    ['type'=>'Casual Leave/Sick Leave','from'=>'22 Apr','to'=>'22 Apr','days'=>1,'status'=>'approved'],
    ['type'=>'Compensatory Leave','from'=>'19 Apr','to'=>'19 Apr','days'=>1,'status'=>'approved'],
    ['type'=>'Casual Leave/Sick Leave','from'=>'15 Apr','to'=>'16 Apr','days'=>2,'status'=>'approved'],
];

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $cal_month, $cal_year);

$mock_approved = [
    1=>1,2=>8,3=>4,4=>7,6=>3,7=>8,8=>2,9=>5,10=>3,11=>5,
    13=>4,14=>6,16=>5,17=>4,18=>5,20=>2,22=>11,23=>1,
    25=>4,26=>3,27=>5,28=>2,29=>4,30=>3
];

$mock_pending = [
    6=>1,7=>1,8=>1,10=>1,21=>1,24=>1,25=>1
];

$calendar_leaves = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $key = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
    $calendar_leaves[$key] = [
        'approved' => $mock_approved[$d] ?? 0,
        'pending'  => $mock_pending[$d] ?? 0,
    ];
}

$history_data = [];

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<script>
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui']
            },
            colors: {
                brand: {
                    500: '#2563EB',
                    600: '#1D4ED8'
                }
            }
        }
    }
};
</script>


<style>
.lv-toast {
    transform: translateX(-50%) translateY(80px);
    transition: transform .3s ease;
}

.lv-toast.show {
    transform: translateX(-50%) translateY(0);
}

.lv-modal-bg {
    display: none;
}

.lv-modal-bg.open {
    display: flex;
}

@keyframes lvPop {
    from {
        opacity: 0;
        transform: scale(.97);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

.lv-pop {
    animation: lvPop .22s ease;
}
</style>

<div class="w-full space-y-5">

    <!-- HEADER -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Leaves
        </h1>

        <div class="flex items-center gap-0 border-b border-slate-200 dark:border-slate-700">
            <?php foreach(['insights'=>'Insights','calendar'=>'Leave Calendar','history'=>'Leave History'] as $tk=>$tl): ?>
            <a href="?tab=<?= esc($tk) ?>" class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition
                   <?= $active_tab === $tk
                        ? 'text-blue-600 border-blue-600'
                        : 'text-slate-500 border-transparent hover:text-slate-900 dark:hover:text-white' ?>">
                <?= esc($tl) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($active_tab === 'insights'): ?>

    <!-- STATS -->
    <div
        class="grid grid-cols-1 xl:grid-cols-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">

        <!-- Team Stats -->
        <div class="p-5 border-b xl:border-b-0 xl:border-r border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Team Leave Stats</h3>
                <button
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-blue-500">
                    Last 3 Months
                    <span>⌄</span>
                </button>
            </div>
            <div class="relative h-[190px]">
                <canvas id="teamStatsChart"></canvas>
            </div>
        </div>

        <!-- Overview -->
        <div class="p-5 border-b xl:border-b-0 xl:border-r border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Leaves Overview</h3>
                <button
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-blue-500">
                    This Month
                    <span>⌄</span>
                </button>
            </div>

            <div class="flex items-center justify-center gap-6 flex-wrap py-3">
                <div class="relative w-[130px] h-[130px] shrink-0">
                    <canvas id="overviewDonut" width="130" height="130"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-xs font-medium text-slate-400">Leaves</span>
                        <span class="text-2xl font-bold text-slate-900 dark:text-white">93</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <?php foreach($leave_types as $lt): ?>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0"
                            style="background:<?= esc($lt['color']) ?>"></span>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                            <?= esc($lt['name']) ?> -
                        </span>
                        <span class="text-xs font-bold text-slate-900 dark:text-white">
                            <?= (int)$lt['count'] ?>
                        </span>
                        <span class="text-xs font-bold <?= $lt['up'] ? 'text-red-600' : 'text-emerald-600' ?>">
                            <?= esc($lt['pct']) ?>%
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Leaves Taken -->
        <div class="p-5">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Leaves Taken</h3>
                <button
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-blue-500">
                    This Month
                    <span>⌄</span>
                </button>
            </div>

            <div class="relative h-[160px]">
                <canvas id="leavesTakenChart"></canvas>
            </div>

            <div class="flex items-center justify-center gap-3 flex-wrap pt-3">
                <?php
                    $day_colors = [
                        'Mon'=>'#3B82F6',
                        'Tue'=>'#22C55E',
                        'Wed'=>'#F59E0B',
                        'Thu'=>'#6366F1',
                        'Fri'=>'#EF4444',
                        'Sat'=>'#EC4899',
                        'Sun'=>'#9CA3AF'
                    ];
                    foreach($day_colors as $day=>$col):
                    ?>
                <div class="flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                    <span class="w-2.5 h-2.5 rounded-full" style="background:<?= esc($col) ?>"></span>
                    <?= esc($day) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RECENT -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Recent Leave Requests</h3>
                <a href="?tab=history" class="text-xs font-semibold text-blue-600 hover:underline">View All →</a>
            </div>

            <?php foreach($recent_requests as $req): ?>
            <div
                class="px-5 py-3 flex items-center gap-3 border-b last:border-b-0 border-slate-50 dark:border-slate-800">
                <span class="w-2.5 h-2.5 rounded-full shrink-0"
                    style="background:<?= $req['type'] === 'Compensatory Leave' ? '#F59E0B' : '#7C3AED' ?>"></span>

                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200 flex-1 min-w-0 truncate">
                    <?= esc($req['type']) ?>
                </span>

                <span class="text-xs text-slate-400 whitespace-nowrap">
                    <?= esc($req['from']) ?> - <?= esc($req['to']) ?>
                </span>

                <span class="text-xs font-bold text-slate-600 dark:text-slate-300 whitespace-nowrap">
                    <?= (int)$req['days'] ?> Day<?= $req['days'] > 1 ? 's' : '' ?>
                </span>

                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs
                            <?= $req['status'] === 'pending'
                                ? 'bg-amber-100 text-amber-700'
                                : 'bg-emerald-100 text-emerald-700' ?>">
                    <?= $req['status'] === 'pending' ? '⏳' : '✓' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <div
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Leave Request by Month</h3>

                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="monthToggle" class="sr-only peer" onchange="toggleMonthChart()">
                    <div
                        class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[18px] after:w-[18px] after:transition-all peer-checked:after:translate-x-5">
                    </div>
                </label>
            </div>

            <div class="p-5 relative h-[230px]" id="monthChartWrap">
                <canvas id="monthReqChart"></canvas>
            </div>

            <div id="monthEmptyWrap" class="hidden">
                <div class="py-12 px-5 text-center">
                    <p class="text-sm text-slate-400">No Leave statement available!</p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($active_tab === 'calendar'): ?>
<div class="section-card m-4 p-4">
    <!-- CALENDAR HEADER -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2 text-sm font-semibold">
            <a href="?tab=insights" class="text-slate-600 dark:text-slate-300 hover:text-blue-600">Leaves</a>
            <span class="text-slate-300">›</span>
            <span class="text-slate-800 dark:text-white">Leave Calendar</span>
        </div>

        <button type="button" onclick="document.getElementById('applyOnBehalfModal').classList.add('open')"
            class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold shadow-sm hover:bg-blue-700">
            Apply on Behalf
        </button>
    </div>
    <!-- MONTH NAV -->
    <div
        class="inline-flex items-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden shadow-sm mb-2">
        <a href="?tab=calendar&y=<?= $cal_month === 1 ? $cal_year - 1 : $cal_year ?>&m=<?= $cal_month === 1 ? 12 : $cal_month - 1 ?>"
            class="w-10 h-10 flex items-center justify-center text-xl text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800">
            ‹
        </a>

        <span
            class="h-10 min-w-[140px] px-5 border-x border-slate-200 dark:border-slate-700 flex items-center justify-center text-sm font-bold text-slate-900 dark:text-white">
            <?= date('M-Y', mktime(0, 0, 0, $cal_month, 1, $cal_year)) ?>
        </span>

        <a href="?tab=calendar&y=<?= $cal_month === 12 ? $cal_year + 1 : $cal_year ?>&m=<?= $cal_month === 12 ? 1 : $cal_month + 1 ?>"
            class="w-10 h-10 flex items-center justify-center text-xl text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800">
            ›
        </a>
    </div>
    <!-- CALENDAR TABLE -->
    <div
        class="">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] border-collapse table-fixed">
                <thead>
                    <tr class="bg-slate-100 dark:bg-slate-800">
                        <th
                            class="w-12 p-3 border border-slate-200 dark:border-slate-700 bg-slate-200 dark:bg-slate-700">
                        </th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            SUN</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            MON</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            TUE</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            WED</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            THU</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            FRI</th>
                        <th class="p-3 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-500">
                            SAT</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                        $first_day = (int)date('w', mktime(0, 0, 0, $cal_month, 1, $cal_year));
                        $total_cells = ceil(($first_day + $days_in_month) / 7) * 7;
                        $day = 1;
                        $week_num = (int)date('W', mktime(0, 0, 0, $cal_month, 1, $cal_year));
                        $today = (int)date('j');
                        $this_month = (int)date('m');
                        $this_year = (int)date('Y');

                        for ($cell = 0; $cell < $total_cells; $cell++) {
                            if ($cell % 7 === 0) {
                                echo '<tr>';
                                echo '<td class="border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-center text-sm font-bold text-slate-400">' . $week_num . '</td>';
                                $week_num++;
                            }

                            if ($cell < $first_day || $day > $days_in_month) {
                                echo '<td class="h-[96px] border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-950"></td>';
                            } else {
                                $date_key = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $day);
                                $is_today = ($day === $today && $cal_month === $this_month && $cal_year === $this_year);
                                $leaves = $calendar_leaves[$date_key] ?? ['approved'=>0,'pending'=>0];

                                echo '<td class="h-[96px] align-top p-2 border border-slate-200 dark:border-slate-700 ' . ($is_today ? 'bg-blue-50 dark:bg-blue-950/30' : 'bg-white dark:bg-slate-900') . '">';
                                echo '<span class="block mb-1 text-sm font-bold ' . ($is_today ? 'text-blue-600' : 'text-slate-700 dark:text-slate-200') . '">' . $day . '</span>';

                                if ($leaves['pending'] > 0) {
                                    echo '<button type="button" class="lv-cal-pill block w-full mb-1 rounded-md bg-amber-500 px-2 py-1 text-[11px] font-bold text-white hover:opacity-90 truncate" data-date="' . esc($date_key) . '" data-status="pending" data-count="' . (int)$leaves['pending'] . '">' . (int)$leaves['pending'] . ' Pending</button>';
                                }

                                if ($leaves['approved'] > 0) {
                                    echo '<button type="button" class="lv-cal-pill block w-full mb-1 rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white hover:opacity-90 truncate" data-date="' . esc($date_key) . '" data-status="approved" data-count="' . (int)$leaves['approved'] . '">' . (int)$leaves['approved'] . ' Approved</button>';
                                }

                                echo '</td>';
                                $day++;
                            }

                            if ($cell % 7 === 6) {
                                echo '</tr>';
                            }
                        }
                        ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


    <!-- APPLY MODAL -->
    <div class="lv-modal-bg fixed inset-0 z-[600] bg-slate-950/50 backdrop-blur-sm items-center justify-center p-4"
        id="applyOnBehalfModal" onclick="if(event.target===this)this.classList.remove('open')">

        <div
            class="lv-pop w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white dark:bg-slate-900 shadow-2xl">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Apply Leave on Behalf</h3>
                <button type="button" onclick="document.getElementById('applyOnBehalfModal').classList.remove('open')"
                    class="text-2xl leading-none text-slate-400 hover:text-slate-900 dark:hover:text-white">
                    ×
                </button>
            </div>

            <div class="p-6">
                <form id="applyBehalfForm" novalidate class="space-y-4">
                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Select Employee <span
                                class="text-red-600">*</span></label>
                        <select required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
                            <option value="">-- Select Employee --</option>
                            <option value="1">Dr. Anjali Sharma — Medical</option>
                            <option value="2">Rajib Das — Nursing</option>
                            <option value="3">Sunita Paul — Reception</option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Leave Type <span
                                class="text-red-600">*</span></label>
                        <select required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
                            <option value="">-- Select Type --</option>
                            <option>Casual Leave/Sick Leave</option>
                            <option>Compensatory Leave</option>
                            <option>Earned Leave</option>
                            <option>Maternity Leave</option>
                            <option>Paternity Leave</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">From Date <span
                                    class="text-red-600">*</span></label>
                            <input type="date" required value="<?= date('Y-m-d') ?>"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
                        </div>

                        <div>
                            <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">To Date <span
                                    class="text-red-600">*</span></label>
                            <input type="date" required value="<?= date('Y-m-d') ?>"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
                        </div>
                    </div>

                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Day Type</label>
                        <select
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
                            <option>Full Day</option>
                            <option>First Half</option>
                            <option>Second Half</option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 uppercase">Reason</label>
                        <textarea rows="3" placeholder="Reason for leave..."
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"></textarea>
                    </div>
                </form>
            </div>

            <div
                class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('applyOnBehalfModal').classList.remove('open')"
                    class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800">
                    Cancel
                </button>

                <button type="button" onclick="submitApply()"
                    class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">
                    Apply Leave
                </button>
            </div>
        </div>
    </div>

    <!-- DAY MODAL -->
    <div class="lv-modal-bg fixed inset-0 z-[600] bg-slate-950/50 backdrop-blur-sm items-center justify-center p-4"
        id="leaveDayModal" onclick="if(event.target===this)this.classList.remove('open')">

        <div
            class="lv-pop w-full max-w-5xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white dark:bg-slate-900 shadow-2xl">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-4">
                <h3 id="leaveDayModalTitle" class="text-base font-bold text-slate-900 dark:text-white">Leave Requests
                </h3>
                <div id="leaveDayModalDate" class="ml-auto text-sm font-semibold text-slate-700 dark:text-slate-200">
                </div>
                <button type="button" onclick="document.getElementById('leaveDayModal').classList.remove('open')"
                    class="text-2xl leading-none text-slate-400 hover:text-slate-900 dark:hover:text-white">
                    ×
                </button>
            </div>

            <div class="p-6">
                <input type="text" id="leaveDaySearch" oninput="filterLeaveDayItems(this.value)"
                    placeholder="Filter items"
                    class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">

                <label class="flex items-center gap-2 my-5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <input type="checkbox" id="leaveDaySelectAll" onchange="toggleLeaveDaySelectAll(this)"
                        class="w-4 h-4 accent-blue-600">
                    Selected -
                    <span id="leaveDaySelectedCount">0</span>
                </label>

                <div id="leaveDayList" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4"></div>
            </div>

            <div
                class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('leaveDayModal').classList.remove('open')"
                    class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-800">
                    Back
                </button>

                <button type="button" onclick="cancelSelectedLeaves()"
                    class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">
                    Cancel Selected
                </button>
            </div>
        </div>
    </div>

    <?php elseif ($active_tab === 'history'): ?>

    <div
        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
        <div
            class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Team Leave History</h3>

            <button type="button" onclick="showMonthPicker()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:border-blue-500">
                <?= month_name($hist_month) . '-' . $hist_year ?>
                <span>📅</span>
            </button>
        </div>

        <div class="px-5 pt-4">
            <div
                class="max-w-sm flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2.5 focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/10">
                <span class="text-slate-400">⌕</span>
                <input type="text" id="histSearch" placeholder="Search table items"
                    oninput="filterHistTable(this.value)"
                    class="w-full bg-transparent outline-none text-sm text-slate-700 dark:text-slate-200">
            </div>
        </div>

        <?php if(empty($history_data)): ?>
        <div class="py-16 px-6 text-center" id="histEmpty">
            <p class="text-sm text-slate-400">No Leave statement available!</p>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<!-- TOAST -->
<div id="lvToastEl"
    class="lv-toast fixed bottom-6 left-1/2 z-[9999] flex items-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-2xl whitespace-nowrap">
    <span id="lvToastIcon">✅</span>
    <span id="lvToastMsg">Done!</span>
</div>

<script>
function lvToast(icon, msg) {
    const t = document.getElementById('lvToastEl');
    const ti = document.getElementById('lvToastIcon');
    const tm = document.getElementById('lvToastMsg');

    if (!t || !ti || !tm) return;

    ti.textContent = icon;
    tm.textContent = msg;
    t.classList.add('show');

    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.remove('show'), 3200);
}

function submitApply() {
    document.getElementById('applyOnBehalfModal')?.classList.remove('open');
    lvToast('✅', 'Leave applied successfully on behalf of employee!');
}

document.addEventListener('click', function(e) {
    const pill = e.target.closest('.lv-cal-pill');
    if (!pill) return;

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    openLeaveDayModal(
        pill.dataset.date,
        pill.dataset.status,
        parseInt(pill.dataset.count || '0', 10)
    );
}, true);

function openLeaveDayModal(date, status, count) {
    const modal = document.getElementById('leaveDayModal');
    const title = document.getElementById('leaveDayModalTitle');
    const dateEl = document.getElementById('leaveDayModalDate');
    const list = document.getElementById('leaveDayList');
    const search = document.getElementById('leaveDaySearch');
    const selectedCount = document.getElementById('leaveDaySelectedCount');
    const selectAll = document.getElementById('leaveDaySelectAll');

    if (!modal || !title || !list) return;

    if (search) search.value = '';
    if (selectedCount) selectedCount.textContent = '0';
    if (selectAll) selectAll.checked = false;

    const dateText = new Date(date + 'T00:00:00').toLocaleDateString('en-IN', {
        weekday: 'long',
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });

    title.textContent = status === 'approved' ?
        'Approved Leave requests' :
        'Pending Leave requests';

    if (dateEl) dateEl.textContent = dateText;

    const approvedNames = [
        'Suman Karmakar-1040',
        'Prolay Sarkar-1063',
        'Laxmi Roy-1065',
        'Priyanka Das-1071',
        'Santana Barman-1094',
        'Supriya Mitra-1100',
        'Chandrima Sarkar-1112',
        'Rina Paul-1026',
        'Biswajit Roy-1031',
        'Amit Sharma-1008',
        'Kavya Nair-1010'
    ];

    const pendingNames = [
        'Sharmistha Raha-1113',
        'Rajib Das-1002',
        'Sunita Paul-1003',
        'Anjali Singh-1020',
        'Mohan Gupta-1022'
    ];

    const names = status === 'approved' ? approvedNames : pendingNames;
    let html = '';

    for (let i = 0; i < count; i++) {
        const name = names[i] || ('Employee-' + (1000 + i));
        const safeName = escapeHtml(name);
        const sub = status === 'approved' ?
            (i % 2 === 0 ? 'CLSL-1' : 'Compoff-1') :
            'Compoff-1';

        html += `
            <div class="leave-day-item flex items-center gap-3 min-w-0" data-name="${safeName.toLowerCase()}">
                <input type="checkbox" class="leave-day-check w-4 h-4 accent-blue-600 shrink-0" onchange="updateLeaveDaySelected()">
                <div class="w-9 h-9 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold text-slate-600 dark:text-slate-200 shrink-0">
                    ${safeName.charAt(0)}
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-bold text-slate-900 dark:text-white truncate">${safeName}</div>
                    <div class="text-xs text-slate-400">${sub}</div>
                </div>
            </div>
        `;
    }

    list.innerHTML = html || '<p class="text-sm text-slate-400">No leave requests found.</p>';
    modal.classList.add('open');
}

function escapeHtml(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function filterLeaveDayItems(q) {
    q = String(q || '').toLowerCase().trim();

    document.querySelectorAll('.leave-day-item').forEach(function(item) {
        item.style.display = !q || (item.dataset.name || '').includes(q) ? 'flex' : 'none';
    });
}

function updateLeaveDaySelected() {
    const total = document.querySelectorAll('.leave-day-check:checked').length;
    const el = document.getElementById('leaveDaySelectedCount');

    if (el) el.textContent = total;
}

function toggleLeaveDaySelectAll(chk) {
    document.querySelectorAll('.leave-day-item').forEach(function(item) {
        if (item.style.display !== 'none') {
            const cb = item.querySelector('.leave-day-check');
            if (cb) cb.checked = chk.checked;
        }
    });

    updateLeaveDaySelected();
}

function cancelSelectedLeaves() {
    const total = document.querySelectorAll('.leave-day-check:checked').length;

    if (!total) {
        lvToast('⚠️', 'Please select at least one leave request.');
        return;
    }

    document.getElementById('leaveDayModal')?.classList.remove('open');
    lvToast('✅', total + ' leave request(s) selected.');
}

function showMonthPicker() {
    lvToast('📅', 'Month picker opened');
}

function filterHistTable(q) {}

function toggleMonthChart() {
    const tog = document.getElementById('monthToggle');
    const cw = document.getElementById('monthChartWrap');
    const ew = document.getElementById('monthEmptyWrap');

    if (!cw || !ew) return;

    if (tog && tog.checked) {
        cw.style.display = 'none';
        ew.style.display = 'block';
    } else {
        cw.style.display = 'block';
        ew.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const tsCtx = document.getElementById('teamStatsChart');
    if (tsCtx) {
        new Chart(tsCtx, {
            type: 'bar',
            data: {
                labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                datasets: [{
                    label: 'Leaves',
                    data: [0, 0, 0, 0, 0, 0, 0],
                    backgroundColor: 'rgba(59,130,246,.5)',
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        min: -1,
                        max: 0,
                        ticks: {
                            font: {
                                size: 10
                            },
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: 'rgba(0,0,0,.04)'
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11
                            },
                            color: '#9CA3AF'
                        },
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    const doCtx = document.getElementById('overviewDonut');
    if (doCtx) {
        new Chart(doCtx, {
            type: 'doughnut',
            data: {
                labels: ['Compensatory Leave', 'Casual Leave/Sick Leave'],
                datasets: [{
                    data: [66, 27],
                    backgroundColor: ['#F59E0B', '#7C3AED'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    const ltCtx = document.getElementById('leavesTakenChart');
    if (ltCtx) {
        const dayColors = ['#3B82F6', '#22C55E', '#F59E0B', '#6366F1', '#EF4444', '#EC4899', '#9CA3AF'];

        const datasets = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(function(d, i) {
            return {
                label: d,
                data: [1, 2, 3, 4],
                borderColor: dayColors[i],
                backgroundColor: 'transparent',
                borderWidth: 1.5,
                pointRadius: 3,
                tension: 0.4
            };
        });

        new Chart(ltCtx, {
            type: 'line',
            data: {
                labels: ['W1', 'W2', 'W3', 'W4'],
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        ticks: {
                            font: {
                                size: 10
                            },
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: 'rgba(0,0,0,.04)'
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 11
                            },
                            color: '#9CA3AF'
                        },
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    const mrCtx = document.getElementById('monthReqChart');
    if (mrCtx) {
        new Chart(mrCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov',
                    'Dec'
                ],
                datasets: [{
                        label: 'Compensatory',
                        data: [12, 8, 5, 14, 0, 0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: '#F59E0B',
                        borderRadius: 6
                    },
                    {
                        label: 'Casual/Sick',
                        data: [4, 3, 2, 8, 0, 0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: '#7C3AED',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: {
                                size: 10
                            },
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: 'rgba(0,0,0,.04)'
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            },
                            color: '#9CA3AF'
                        },
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>