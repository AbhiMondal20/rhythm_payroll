<?php
require_once 'includes/config.php';
$page_title = 'Approval Request';



$total_requests     = count($pending_requests);
$leave_count        = count(array_filter($pending_requests, fn($r) => $r['type'] === 'Leave'));
$attendance_count   = count(array_filter($pending_requests, fn($r) => $r['type'] === 'Attendance'));

/* active tab */
$active_tab = $_GET['tab'] ?? 'insights';

/* selected request for right panel */
$selected_id  = isset($_GET['req']) ? (int)$_GET['req'] : ($pending_requests[0]['id'] ?? null);
$selected_req = null;
foreach ($pending_requests as $r) {
    if ($r['id'] === $selected_id) { $selected_req = $r; break; }
}
if (!$selected_req && !empty($pending_requests)) {
    $selected_req = $pending_requests[0];
    $selected_id  = $selected_req['id'];
}

/* filter type */
$filter_type = $_GET['filter'] ?? 'All';

/* POST — approve / reject */
$action_done = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_done = $_POST['action'] ?? '';
    // TODO: DB update
}

function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ═══════════════════════════════════════
   APPROVAL REQUEST PAGE
═══════════════════════════════════════ */

/* ── Tab bar ── */
.ar-tabs {
    display: flex;
    align-items: center;
    gap: 0;
    margin-bottom: 0;
}
.ar-tab {
    padding: 10px 18px;
    font-size: 13.5px;
    font-weight: 500;
    color: #6B7280;
    cursor: pointer;
    border: none;
    background: transparent;
    border-bottom: 2.5px solid transparent;
    white-space: nowrap;
    transition: color .15s, border-color .15s;
    text-decoration: none;
    display: inline-block;
    margin-bottom: -1px;
    font-family: inherit;
}
.ar-tab:hover   { color: #111827; }
.ar-tab.active  { color: #2563EB; border-bottom-color: #2563EB; font-weight: 600; }
.ar-tab-divider { color: #E5E7EB; padding: 0 2px; line-height: 38px; }

/* ── Insight stat cards row ── */
.ar-stat-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 20px;
    background: #fff;
}
.ar-stat-card {
    padding: 18px 20px;
    border-right: 1px solid #E5E7EB;
    min-height: 210px;
}
.ar-stat-card:last-child { border-right: none; }

.ar-stat-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 6px;
}
.ar-stat-title {
    font-size: 13.5px;
    font-weight: 600;
    color: #111827;
}
.ar-stat-filter {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border: 1.5px solid #E5E7EB;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    background: #fff;
    font-family: inherit;
    transition: border-color .15s;
}
.ar-stat-filter:hover { border-color: #2563EB; }
.ar-stat-filter svg { width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round; }

.ar-stat-big {
    font-size: 52px;
    font-weight: 700;
    color: #2563EB;
    line-height: 1;
    margin-bottom: 6px;
    text-align: center;
}
.ar-stat-sub {
    font-size: 12.5px;
    color: #9CA3AF;
    text-align: center;
    margin-bottom: 20px;
}
.ar-stat-row-items { display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }
.ar-stat-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    color: #374151;
}
.ar-stat-item-left { display: flex; align-items: center; gap: 8px; }
.ar-stat-item-left svg { width:16px;height:16px;flex-shrink:0; }
.ar-stat-count { font-weight: 600; color: #111827; }

/* empty state inside stat card */
.ar-empty-mini {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 130px;
    gap: 10px;
}
.ar-empty-mini p { font-size: 12.5px; color: #9CA3AF; text-align: center; }

/* ── Pending section (below insights / pending tab) ── */
.ar-split {
    display: grid;
    grid-template-columns: 360px 1fr 320px;
    gap: 0;
    background: #fff;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    overflow: hidden;
    min-height: 360px;
}
.ar-split.no-right { grid-template-columns: 360px 1fr; }

/* left sidebar */
.ar-left {
    border-right: 1px solid #E5E7EB;
    padding: 16px 0;
}
.ar-left-head {
    font-size: 13.5px;
    font-weight: 700;
    color: #111827;
    padding: 0 16px 14px;
}
.ar-total-row {
    padding: 10px 16px;
    background: #F3F4F6;
    font-size: 13px;
    color: #374151;
    font-weight: 500;
    margin: 0 0 8px;
    border-radius: 0;
}
.ar-type-row {
    padding: 9px 16px;
    font-size: 13px;
    color: #374151;
    cursor: pointer;
    transition: background .15s;
    border-radius: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.ar-type-row:hover  { background: #F9FAFB; }
.ar-type-row.active { background: #EFF6FF; color: #1D4ED8; font-weight: 600; }

/* middle list */
.ar-mid {
    border-right: 1px solid #E5E7EB;
    overflow-y: auto;
    max-height: 560px;
}

/* toolbar inside mid */
.ar-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid #E5E7EB;
    background: #fff;
    flex-wrap: wrap;
    position: sticky;
    top: 0;
    z-index: 10;
}
.ar-search {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 160px;
    padding: 8px 12px;
    border: 1.5px solid #E5E7EB;
    border-radius: 8px;
    background: #fff;
    transition: border-color .15s;
}
.ar-search:focus-within { border-color: #2563EB; }
.ar-search svg { width:14px;height:14px;stroke:#9CA3AF;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0; }
.ar-search input {
    border: none;
    outline: none;
    font-size: 13px;
    font-family: inherit;
    color: #374151;
    background: transparent;
    width: 100%;
}
.ar-select-all {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    white-space: nowrap;
}
.ar-select-all input { width: 15px; height: 15px; cursor: pointer; accent-color: #2563EB; }
.ar-type-filter {
    padding: 7px 12px;
    border: 1.5px solid #E5E7EB;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    color: #374151;
    outline: none;
    min-width: 80px;
    cursor: pointer;
}

/* request card */
.ar-req-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #F3F4F6;
    cursor: pointer;
    transition: background .15s;
    position: relative;
}
.ar-req-card:hover     { background: #F9FAFB; }
.ar-req-card.active    { background: #EFF6FF; }
.ar-req-card input[type=checkbox] { margin-top: 4px; width:14px;height:14px;accent-color:#2563EB;cursor:pointer;flex-shrink:0; }

.ar-req-av {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #E5E7EB;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    flex-shrink: 0;
}

.ar-req-body { flex: 1; min-width: 0; }
.ar-req-name { font-size: 13.5px; font-weight: 600; color: #111827; margin-bottom: 2px; }
.ar-req-stage { font-size: 12px; color: #9CA3AF; margin-bottom: 4px; }
.ar-req-date  { font-size: 12px; color: #9CA3AF; margin-bottom: 8px; }
.ar-req-btns  { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.ar-btn-reject {
    padding: 4px 12px;
    border: 1.5px solid #DC2626;
    border-radius: 5px;
    background: #fff;
    color: #DC2626;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}
.ar-btn-reject:hover { background: #FEE2E2; }

.ar-btn-approve {
    padding: 4px 12px;
    border: 1.5px solid #2563EB;
    border-radius: 5px;
    background: #fff;
    color: #2563EB;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}
.ar-btn-approve:hover { background: #EFF6FF; }

.ar-btn-detail {
    font-size: 12px;
    font-weight: 500;
    color: #2563EB;
    background: none;
    border: none;
    cursor: pointer;
    font-family: inherit;
    padding: 4px 0;
    text-decoration: none;
}
.ar-btn-detail:hover { text-decoration: underline; }

/* type badge */
.ar-type-badge {
    position: absolute;
    top: 14px;
    right: 14px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 600;
}
.ar-type-badge.Attendance { background: #D1FAE5; color: #065F46; }
.ar-type-badge.Leave      { background: #FEE2E2; color: #991B1B; }

/* right detail panel */
.ar-right {
    padding: 18px 18px 24px;
    overflow-y: auto;
    max-height: 560px;
}
.ar-right-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid #F3F4F6;
}
.ar-right-av {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #E5E7EB;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: #374151;
    flex-shrink: 0;
}
.ar-right-name { font-size: 14px; font-weight: 700; color: #111827; }
.ar-right-type { font-size: 12px; color: #9CA3AF; margin-top: 2px; }

.ar-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 9px 0;
    border-bottom: 1px solid #F9FAFB;
    gap: 8px;
}
.ar-detail-row:last-child { border-bottom: none; }
.ar-detail-label { font-size: 12.5px; color: #6B7280; font-weight: 400; white-space: nowrap; flex-shrink: 0; min-width: 110px; }
.ar-detail-val   { font-size: 13px; color: #111827; font-weight: 500; text-align: right; }
.ar-detail-strike { text-decoration: line-through; color: #9CA3AF; margin-right: 6px; font-size: 12px; }

/* ── Completed tab ── */
.ar-comp-wrap { background:#fff; border:1px solid #E5E7EB; border-radius:10px; overflow:hidden; }
.ar-comp-head { padding:18px 22px 14px; border-bottom:1px solid #F3F4F6; }
.ar-comp-head h3 { font-size:15px;font-weight:700;color:#111827;margin-bottom:4px; }
.ar-comp-head p  { font-size:12.5px;color:#9CA3AF; }
.ar-comp-toolbar {
    display:flex;align-items:center;gap:10px;padding:14px 22px;
    flex-wrap:wrap;
}
.ar-comp-search {
    display:flex;align-items:center;gap:8px;
    padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;
    background:#fff;transition:border-color .15s;flex:1;min-width:200px;
}
.ar-comp-search:focus-within { border-color:#2563EB; }
.ar-comp-search svg { width:13px;height:13px;stroke:#9CA3AF;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0; }
.ar-comp-search input { border:none;outline:none;font-size:13px;font-family:inherit;color:#374151;background:transparent;width:100%; }
.ar-comp-date-btn {
    display:flex;align-items:center;gap:7px;padding:8px 14px;
    border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;
    font-weight:500;color:#374151;cursor:pointer;background:#fff;font-family:inherit;
    transition:border-color .15s;white-space:nowrap;
}
.ar-comp-date-btn:hover { border-color:#2563EB; }
.ar-comp-date-btn svg { width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round; }
.ar-comp-filter-btn {
    display:flex;align-items:center;gap:7px;padding:8px 14px;
    border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;
    font-weight:500;color:#374151;cursor:pointer;background:#fff;font-family:inherit;
    transition:border-color .15s;
}
.ar-comp-filter-btn:hover { border-color:#2563EB; }
.ar-comp-filter-btn svg { width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2; }
.ar-comp-select {
    padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:8px;
    font-size:13px;font-family:inherit;color:#374151;outline:none;min-width:80px;
}
.ar-comp-search-btn {
    padding:8px 22px;background:#2563EB;color:#fff;border:none;border-radius:8px;
    font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .15s;
    white-space:nowrap;
}
.ar-comp-search-btn:hover { background:#1D4ED8; }

/* empty states */
.ar-empty {
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:50px 24px;gap:12px;
}
.ar-empty p { font-size:13px;color:#9CA3AF;text-align:center; }

/* empty illustration — document style */
.ar-empty-doc {
    width:80px;height:96px;position:relative;
}
.ar-empty-doc-bg {
    width:72px;height:88px;border-radius:8px;background:#E9EEF7;
    position:relative;overflow:hidden;margin:0 auto;
}
.ar-empty-doc-top {
    width:100%;height:22px;background:#8BA7CC;border-radius:8px 8px 0 0;
}
.ar-empty-doc-lines { padding:8px 10px;display:flex;flex-direction:column;gap:6px; }
.ar-empty-doc-line {
    height:5px;border-radius:3px;background:#C8D8ED;
}
/* no-data robot */
.ar-empty-robot-svg {
    width:90px;height:90px;opacity:.35;
}

/* toast */
.ar-toast {
    position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
    background:#111827;color:#fff;padding:11px 20px;border-radius:10px;
    font-size:13px;font-weight:500;z-index:999;display:flex;align-items:center;
    gap:8px;box-shadow:0 8px 28px rgba(0,0,0,.2);transition:transform .3s ease;white-space:nowrap;
}
.ar-toast.show { transform:translateX(-50%) translateY(0); }

/* responsive */
@media(max-width:1000px){
    .ar-split { grid-template-columns:260px 1fr; }
    .ar-split.no-right { grid-template-columns:260px 1fr; }
    .ar-right { display:none; }
}
@media(max-width:700px){
    .ar-split { grid-template-columns:1fr; }
    .ar-left  { display:none; }
    .ar-stat-row { grid-template-columns:1fr; }
    .ar-stat-card { border-right:none; border-bottom:1px solid #E5E7EB; }
}
</style>

<?php if (!empty($action_done)): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    arToast('✅', '<?= $action_done === "approve" ? "Request approved!" : "Request rejected." ?>');
});
</script>
<?php endif; ?>

<!-- ══════════════════════════════════════
     PAGE HEADER + TAB BAR
══════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Approval Request</h1>
    <div class="ar-tabs">
        <?php
        $tabs = [
            'insights'     => 'Insights',
            'pending'      => 'Pending',
            'completed'    => 'Completed',
            'all_requests' => 'All Open Requests',
        ];
        $first = true;
        foreach ($tabs as $tkey => $tlabel):
            if (!$first): ?><span class="ar-tab-divider">|</span><?php endif; $first = false;
        ?>
        <a href="?tab=<?= $tkey ?>"
           class="ar-tab <?= $active_tab === $tkey ? 'active' : '' ?>">
           <?= esc($tlabel) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══════════════════════════════════════
     TAB PANELS
══════════════════════════════════════ -->

<?php /* ══ TAB 1: INSIGHTS ══ */ if ($active_tab === 'insights'): ?>

<!-- Stat cards -->
<div class="ar-stat-row">

    <!-- Total Approvals -->
    <div class="ar-stat-card">
        <div class="ar-stat-head">
            <span class="ar-stat-title">Total Approvals</span>
            <button class="ar-stat-filter">
                This Month
                <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
        </div>
        <div class="ar-stat-big">0</div>
        <div class="ar-stat-sub">Total Approvals Received</div>
        <div class="ar-stat-row-items">
            <div class="ar-stat-item">
                <div class="ar-stat-item-left">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2.5" stroke-linecap="round">
                        <path d="M12 22C12 22 12 12 12 12M12 12C12 12 7 15 7 15M12 12L17 15"/>
                        <circle cx="12" cy="7" r="3"/>
                    </svg>
                    Pending
                </div>
                <span class="ar-stat-count">0</span>
            </div>
            <div class="ar-stat-item">
                <div class="ar-stat-item-left">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Completed
                </div>
                <span class="ar-stat-count">0</span>
            </div>
        </div>
    </div>

    <!-- Pending Approvals -->
    <div class="ar-stat-card">
        <div class="ar-stat-head">
            <span class="ar-stat-title">Pending Approvals</span>
            <button class="ar-stat-filter">
                This Month
                <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
        </div>
        <div class="ar-empty-mini">
            <!-- Doc illustration -->
            <div class="ar-empty-doc-bg" style="width:64px;height:78px">
                <div class="ar-empty-doc-top" style="height:18px"></div>
                <div class="ar-empty-doc-lines">
                    <div class="ar-empty-doc-line" style="width:80%"></div>
                    <div class="ar-empty-doc-line" style="width:65%"></div>
                    <div class="ar-empty-doc-line" style="width:75%"></div>
                </div>
            </div>
            <p>You don't have any pending approvals!</p>
        </div>
    </div>

    <!-- Approval Request Stats -->
    <div class="ar-stat-card">
        <div class="ar-stat-head">
            <span class="ar-stat-title">Approval Request Stats - <?= date('Y') ?></span>
        </div>
        <div class="ar-empty-mini">
            <div class="ar-empty-doc-bg" style="width:64px;height:78px">
                <div class="ar-empty-doc-top" style="height:18px"></div>
                <div class="ar-empty-doc-lines">
                    <div class="ar-empty-doc-line" style="width:80%"></div>
                    <div class="ar-empty-doc-line" style="width:65%"></div>
                    <div class="ar-empty-doc-line" style="width:75%"></div>
                </div>
            </div>
            <p>You don't have any Approval Request Stats!</p>
        </div>
    </div>

</div>

<!-- Pending section below -->
<?php include_once __FILE__; // will be skipped — use inline render below ?>
<?php
// Render pending approvals section inline
?>
<div class="ar-split no-right">
    <div class="ar-left">
        <div class="ar-left-head">Pending Approvals</div>
        <div class="ar-total-row">Total Requests - 0</div>
    </div>
    <div class="ar-mid">
        <!-- toolbar -->
        <div class="ar-toolbar">
            <div class="ar-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search">
            </div>
            <label class="ar-select-all">
                <input type="checkbox"> Select All
            </label>
            <select class="ar-type-filter"><option>All</option><option>Attendance</option><option>Leave</option></select>
        </div>
        <div class="ar-empty" style="min-height:220px">
            <!-- robot svg -->
            <svg class="ar-empty-robot-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="20" y="40" width="60" height="45" rx="6" fill="#D1D5DB"/>
                <rect x="30" y="25" width="40" height="20" rx="4" fill="#9CA3AF"/>
                <circle cx="40" cy="55" r="5" fill="#E5E7EB"/>
                <circle cx="60" cy="55" r="5" fill="#E5E7EB"/>
                <path d="M42 66 Q50 72 58 66" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" fill="none"/>
                <rect x="10" y="50" width="8" height="20" rx="3" fill="#9CA3AF"/>
                <rect x="82" y="50" width="8" height="20" rx="3" fill="#9CA3AF"/>
                <rect x="30" y="85" width="12" height="10" rx="2" fill="#9CA3AF"/>
                <rect x="58" y="85" width="12" height="10" rx="2" fill="#9CA3AF"/>
            </svg>
            <p style="font-size:14px;font-weight:600;color:#374151">No Pending Approvals</p>
        </div>
    </div>
</div>

<?php /* ══ TAB 2: PENDING ══ */ elseif ($active_tab === 'pending'): ?>

<div class="ar-split no-right">
    <div class="ar-left">
        <div class="ar-left-head">Pending Approvals</div>
        <div class="ar-total-row">Total Requests - 0</div>
    </div>
    <div class="ar-mid">
        <div class="ar-toolbar">
            <div class="ar-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search">
            </div>
            <label class="ar-select-all">
                <input type="checkbox"> Select All
            </label>
            <select class="ar-type-filter"><option>All</option><option>Attendance</option><option>Leave</option></select>
        </div>
        <div class="ar-empty" style="min-height:280px">
            <svg class="ar-empty-robot-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="20" y="40" width="60" height="45" rx="6" fill="#D1D5DB"/>
                <rect x="30" y="25" width="40" height="20" rx="4" fill="#9CA3AF"/>
                <circle cx="40" cy="55" r="5" fill="#E5E7EB"/>
                <circle cx="60" cy="55" r="5" fill="#E5E7EB"/>
                <path d="M42 66 Q50 72 58 66" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" fill="none"/>
                <rect x="10" y="50" width="8" height="20" rx="3" fill="#9CA3AF"/>
                <rect x="82" y="50" width="8" height="20" rx="3" fill="#9CA3AF"/>
                <rect x="30" y="85" width="12" height="10" rx="2" fill="#9CA3AF"/>
                <rect x="58" y="85" width="12" height="10" rx="2" fill="#9CA3AF"/>
            </svg>
            <p style="font-size:14px;font-weight:600;color:#374151">No Pending Approvals</p>
        </div>
    </div>
</div>

<?php /* ══ TAB 3: COMPLETED ══ */ elseif ($active_tab === 'completed'): ?>

<div class="ar-comp-wrap">
    <div class="ar-comp-head">
        <h3>Completed Approvals</h3>
        <p>Select an employee or date range to view completed requests.</p>
    </div>
    <div class="ar-comp-toolbar">
        <div class="ar-comp-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="compSearch" placeholder="Search by name or #code">
        </div>
        <button class="ar-comp-date-btn" onclick="toggleDatePicker()">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Select Date
        </button>
        <button class="ar-comp-filter-btn">
            <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            Filter
        </button>
        <select class="ar-comp-select">
            <option>All</option>
            <option>Attendance</option>
            <option>Leave</option>
        </select>
        <button class="ar-comp-search-btn" onclick="searchCompleted()">Search</button>
    </div>

    <div class="ar-empty" id="compEmptyState" style="min-height:320px">
        <!-- Illustration matching screenshot (person + add screen) -->
        <svg viewBox="0 0 260 200" width="220" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- screen -->
            <rect x="60" y="30" width="140" height="110" rx="6" fill="#F3F4F6"/>
            <rect x="60" y="30" width="140" height="18" rx="6" fill="#D1D5DB"/>
            <circle cx="70" cy="39" r="3" fill="#EF4444"/>
            <circle cx="80" cy="39" r="3" fill="#F59E0B"/>
            <circle cx="90" cy="39" r="3" fill="#10B981"/>
            <!-- row 1 with avatar -->
            <circle cx="90" cy="70" r="12" fill="#F59E0B"/>
            <rect x="108" y="65" width="50" height="5" rx="2.5" fill="#D1D5DB"/>
            <rect x="108" y="75" width="40" height="5" rx="2.5" fill="#E5E7EB"/>
            <!-- row 2 with plus -->
            <circle cx="90" cy="112" r="12" fill="#2563EB"/>
            <line x1="90" y1="107" x2="90" y2="117" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="85" y1="112" x2="95" y2="112" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
            <rect x="108" y="107" width="50" height="5" rx="2.5" fill="#D1D5DB"/>
            <rect x="108" y="117" width="40" height="5" rx="2.5" fill="#E5E7EB"/>
            <!-- person -->
            <circle cx="200" cy="90" r="14" fill="#111827"/>
            <rect x="185" y="104" width="30" height="45" rx="4" fill="#374151"/>
            <circle cx="208" cy="138" r="8" fill="#10B981"/>
            <circle cx="208" cy="138" r="4" fill="#065F46"/>
        </svg>
        <p style="font-size:13.5px;font-weight:600;color:#374151">Search based on dates to view completed approvals</p>
    </div>

    <!-- Results table (hidden initially) -->
    <div id="compResults" style="display:none;overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="background:#FAFAFA">
                    <th style="padding:11px 16px;text-align:left;font-weight:600;color:#6B7280;font-size:11px;letter-spacing:.4px;border-bottom:1px solid #E5E7EB">EMPLOYEE</th>
                    <th style="padding:11px 16px;text-align:left;font-weight:600;color:#6B7280;font-size:11px;letter-spacing:.4px;border-bottom:1px solid #E5E7EB">TYPE</th>
                    <th style="padding:11px 16px;text-align:left;font-weight:600;color:#6B7280;font-size:11px;letter-spacing:.4px;border-bottom:1px solid #E5E7EB">DATE</th>
                    <th style="padding:11px 16px;text-align:left;font-weight:600;color:#6B7280;font-size:11px;letter-spacing:.4px;border-bottom:1px solid #E5E7EB">STAGE</th>
                    <th style="padding:11px 16px;text-align:center;font-weight:600;color:#6B7280;font-size:11px;letter-spacing:.4px;border-bottom:1px solid #E5E7EB">STATUS</th>
                </tr>
            </thead>
            <tbody id="compTableBody"></tbody>
        </table>
    </div>
</div>

<?php /* ══ TAB 4: ALL OPEN REQUESTS ══ */ elseif ($active_tab === 'all_requests'): ?>

<div class="ar-split" id="arSplit">
    <!-- LEFT sidebar -->
    <div class="ar-left">
        <div class="ar-left-head">All Open Requests</div>
        <div class="ar-total-row">Total Requests - <?= $total_requests ?></div>
        <?php if ($leave_count > 0): ?>
        <div class="ar-type-row <?= $filter_type==='Leave'?'active':'' ?>"
             onclick="setFilter('Leave')">
            Leave - <?= $leave_count ?>
        </div>
        <?php endif; ?>
        <?php if ($attendance_count > 0): ?>
        <div class="ar-type-row <?= $filter_type==='Attendance'?'active':'' ?>"
             onclick="setFilter('Attendance')">
            Attendance - <?= $attendance_count ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- MIDDLE request list -->
    <div class="ar-mid" id="arMid">
        <!-- toolbar -->
        <div class="ar-toolbar">
            <div class="ar-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="allSearch" placeholder="Search" oninput="filterAllReqs(this.value)">
            </div>
            <label class="ar-select-all">
                <input type="checkbox" id="selectAllChk" onchange="toggleSelectAll(this)"> Select All
            </label>
            <select class="ar-type-filter" id="typeFilterSel" onchange="filterAllReqs('')">
                <option value="All">All</option>
                <option value="Attendance" <?= $filter_type==='Attendance'?'selected':'' ?>>Attendance</option>
                <option value="Leave" <?= $filter_type==='Leave'?'selected':'' ?>>Leave</option>
            </select>
        </div>

        <!-- Request cards -->
        <div id="allReqCards">
        <?php foreach ($pending_requests as $req): ?>
        <div class="ar-req-card <?= $req['id']===$selected_id ? 'active':'' ?>"
             id="reqCard-<?= $req['id'] ?>"
             data-type="<?= esc($req['type']) ?>"
             data-name="<?= strtolower(esc($req['emp_name'])) ?> <?= strtolower(esc($req['emp_code'])) ?>"
             onclick="selectReq(<?= $req['id'] ?>)">
            <input type="checkbox" class="req-chk" onclick="event.stopPropagation()">
            <div class="ar-req-av"><?= initials($req['emp_name']) ?></div>
            <div class="ar-req-body">
                <div class="ar-req-name"><?= esc($req['emp_name']) ?> - <?= esc($req['emp_code']) ?></div>
                <div class="ar-req-stage"><?= esc($req['stage']) ?></div>
                <div class="ar-req-date"><?= esc($req['date']) ?></div>
                <div class="ar-req-btns">
                    <form method="POST" style="display:inline" onsubmit="event.stopPropagation()">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                        <button type="submit" class="ar-btn-reject"
                            onclick="handleAction(event,'reject',<?= $req['id'] ?>)">Reject</button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="event.stopPropagation()">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                        <button type="submit" class="ar-btn-approve"
                            onclick="handleAction(event,'approve',<?= $req['id'] ?>)">Approve</button>
                    </form>
                    <button class="ar-btn-detail" onclick="event.stopPropagation();arToast('📋','Opening detailed view...')">Detailed View</button>
                </div>
            </div>
            <span class="ar-type-badge <?= esc($req['type']) ?>"><?= esc($req['type']) ?></span>
        </div>
        <?php endforeach; ?>
        </div>

    </div>

    <!-- RIGHT detail panel -->
    <div class="ar-right" id="arRight">
        <?php if ($selected_req): ?>
        <div class="ar-right-head">
            <div class="ar-right-av"><?= initials($selected_req['emp_name']) ?></div>
            <div>
                <div class="ar-right-name"><?= esc($selected_req['emp_name']) ?></div>
                <div class="ar-right-type"><?= esc($selected_req['type']) ?> Request</div>
            </div>
        </div>

        <div class="ar-detail-row">
            <span class="ar-detail-label">Requested On :</span>
            <span class="ar-detail-val"><?= esc($selected_req['requested']) ?></span>
        </div>
        <?php if ($selected_req['type'] === 'Attendance'): ?>
        <div class="ar-detail-row">
            <span class="ar-detail-label">Shift Date :</span>
            <span class="ar-detail-val"><?= esc($selected_req['shift_date']) ?></span>
        </div>
        <?php if ($selected_req['in_old']): ?>
        <div class="ar-detail-row">
            <span class="ar-detail-label">In Time :</span>
            <span class="ar-detail-val">
                <span class="ar-detail-strike"><?= esc($selected_req['in_old']) ?></span>
                <?= esc($selected_req['in_new']) ?>
            </span>
        </div>
        <div class="ar-detail-row">
            <span class="ar-detail-label">Out Time :</span>
            <span class="ar-detail-val">
                <span class="ar-detail-strike"><?= esc($selected_req['out_old']) ?></span>
                <?= esc($selected_req['out_new']) ?>
            </span>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="ar-detail-row">
            <span class="ar-detail-label">Leave Type :</span>
            <span class="ar-detail-val"><?= esc($selected_req['reasons']) ?></span>
        </div>
        <?php endif; ?>
        <div class="ar-detail-row">
            <span class="ar-detail-label">Reasons :</span>
            <span class="ar-detail-val"><?= esc($selected_req['reasons']) ?: '—' ?></span>
        </div>
        <div class="ar-detail-row">
            <span class="ar-detail-label">Remarks :</span>
            <span class="ar-detail-val"><?= esc($selected_req['remarks']) ?: '—' ?></span>
        </div>

        <!-- Approve / Reject in detail panel -->
        <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="req_id" value="<?= $selected_req['id'] ?>">
                <button type="submit" class="ar-btn-reject" style="width:100%;padding:8px"
                    onclick="handleAction(event,'reject',<?= $selected_req['id'] ?>)">Reject</button>
            </form>
            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="req_id" value="<?= $selected_req['id'] ?>">
                <button type="submit" class="ar-btn-approve" style="width:100%;padding:8px"
                    onclick="handleAction(event,'approve',<?= $selected_req['id'] ?>)">Approve</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

</div><!-- end ar-split -->

<?php endif; ?>

<!-- ── Toast ── -->
<div class="ar-toast" id="arToastEl">
    <span id="arToastIcon">✅</span>
    <span id="arToastMsg">Done!</span>
</div>

<!-- ══════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════ -->
<script>
/* ── Toast ── */
function arToast(icon, msg) {
    var t  = document.getElementById('arToastEl');
    var ti = document.getElementById('arToastIcon');
    var tm = document.getElementById('arToastMsg');
    ti.textContent = icon;
    tm.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(function(){ t.classList.remove('show'); }, 3200);
}

/* ── All Open Requests: select request ── */
function selectReq(id) {
    document.querySelectorAll('.ar-req-card').forEach(function(c){
        c.classList.toggle('active', c.id === 'reqCard-' + id);
    });
    // update URL without reload
    var url = new URL(window.location.href);
    url.searchParams.set('req', id);
    history.replaceState(null,'', url.toString());
    // on mobile, could scroll to right panel
    var right = document.getElementById('arRight');
    if (right) right.scrollIntoView({behavior:'smooth', block:'nearest'});
}

/* ── Filter by type ── */
function setFilter(type) {
    var sel = document.getElementById('typeFilterSel');
    if (sel) sel.value = type;
    filterAllReqs('');
    // update sidebar active
    document.querySelectorAll('.ar-type-row').forEach(function(r){
        r.classList.toggle('active', r.textContent.trim().startsWith(type));
    });
}

/* ── Search + type filter ── */
function filterAllReqs(q) {
    q = q.toLowerCase().trim();
    var typeSel = document.getElementById('typeFilterSel');
    var typeVal = typeSel ? typeSel.value : 'All';

    var cards = document.querySelectorAll('.ar-req-card');
    var shown = 0;
    cards.forEach(function(card) {
        var nameMatch = !q || (card.dataset.name || '').includes(q);
        var typeMatch = typeVal === 'All' || card.dataset.type === typeVal;
        var vis = nameMatch && typeMatch;
        card.style.display = vis ? '' : 'none';
        if (vis) shown++;
    });
}

/* ── Select all ── */
function toggleSelectAll(chk) {
    document.querySelectorAll('.req-chk').forEach(function(c){
        c.checked = chk.checked;
    });
}

/* ── Approve / Reject (AJAX-style) ── */
function handleAction(event, action, reqId) {
    event.preventDefault();
    var card = document.getElementById('reqCard-' + reqId);

    if (action === 'approve') {
        arToast('✅', 'Request approved successfully!');
        if (card) {
            card.style.opacity = '.4';
            card.style.pointerEvents = 'none';
        }
    } else {
        arToast('✕', 'Request rejected.');
        if (card) {
            card.style.opacity = '.4';
            card.style.pointerEvents = 'none';
        }
    }
    // Update sidebar count
    // In real app, do fetch() call here
}

/* ── Completed tab search ── */
function toggleDatePicker() {
    arToast('📅', 'Date picker opened');
}

function searchCompleted() {
    var q = (document.getElementById('compSearch') || {}).value || '';
    if (!q) { arToast('⚠', 'Please enter a name or select a date range.'); return; }

    var emptyState  = document.getElementById('compEmptyState');
    var resultsWrap = document.getElementById('compResults');
    var tbody       = document.getElementById('compTableBody');

    if (emptyState)  emptyState.style.display  = 'none';
    if (resultsWrap) resultsWrap.style.display  = 'block';

    // Mock completed data
    var mockData = [
        ['Rajib Das – 1002',      'Attendance', '05 Apr 2026', 'Stage_1', 'Approved'],
        ['Sunita Paul – 1003',    'Leave',      '02 Apr 2026', 'Stage_1', 'Rejected'],
        ['Kavya Nair – 1010',     'Attendance', '28 Mar 2026', 'Stage_1', 'Approved'],
    ].filter(function(r){ return r[0].toLowerCase().includes(q.toLowerCase()); });

    if (tbody) {
        if (mockData.length === 0) {
            if (emptyState) { emptyState.style.display='flex'; resultsWrap.style.display='none'; }
            arToast('🔍','No completed approvals found for "'+q+'"'); return;
        }
        tbody.innerHTML = mockData.map(function(r){
            var statusBg  = r[4]==='Approved'?'#D1FAE5':'#FEE2E2';
            var statusCol = r[4]==='Approved'?'#065F46':'#991B1B';
            return '<tr style="border-bottom:1px solid #F3F4F6">'
                + '<td style="padding:11px 16px;font-weight:500">'+r[0]+'</td>'
                + '<td style="padding:11px 16px;color:#6B7280">'+r[1]+'</td>'
                + '<td style="padding:11px 16px;color:#6B7280">'+r[2]+'</td>'
                + '<td style="padding:11px 16px;color:#6B7280">'+r[3]+'</td>'
                + '<td style="padding:11px 16px;text-align:center">'
                + '<span style="display:inline-flex;align-items:center;justify-content:center;border-radius:20px;font-size:11.5px;font-weight:600;padding:3px 10px;background:'+statusBg+';color:'+statusCol+'">'+r[4]+'</span>'
                + '</td></tr>';
        }).join('');
    }
    arToast('🔍', mockData.length + ' completed approval(s) found.');
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>