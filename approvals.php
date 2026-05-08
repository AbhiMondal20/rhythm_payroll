<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}


require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Approval Request';

function esc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

if (!function_exists('initials')) {
    function initials($name) {
        $name = trim((string)$name);
        if ($name === '') return 'NA';

        $parts = preg_split('/\s+/', $name);
        return strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    }
}

function fmtDate($date) {
    if (empty($date) || $date === '0000-00-00') return '—';
    return date('d M Y', strtotime($date));
}

function fmtDateTime($date) {
    if (empty($date) || $date === '0000-00-00 00:00:00') return '—';
    return date('d M Y h:i A', strtotime($date));
}

$active_tab  = $_GET['tab'] ?? 'insights';
$filter_type = $_GET['filter'] ?? 'All';
$search_q    = trim($_GET['search'] ?? '');
$selected_id = isset($_GET['req']) ? (int)$_GET['req'] : 0;

$toast_msg  = $_SESSION['toast_msg'] ?? '';
$toast_icon = $_SESSION['toast_icon'] ?? '✅';
unset($_SESSION['toast_msg'], $_SESSION['toast_icon']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $req_id = (int)($_POST['req_id'] ?? 0);

    if (($action === 'approve' || $action === 'reject') && $req_id > 0) {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        $action_by  = (int)($_SESSION['user_id'] ?? $_SESSION['userid'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE approval_requests
            SET status = ?, action_by = ?, action_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $new_status, $action_by, $req_id);

        if ($stmt->execute()) {
            $_SESSION['toast_icon'] = $action === 'approve' ? '✅' : '✕';
            $_SESSION['toast_msg']  = $action === 'approve' ? 'Request approved successfully!' : 'Request rejected.';
        } else {
            $_SESSION['toast_icon'] = '❌';
            $_SESSION['toast_msg']  = 'Action failed: ' . $stmt->error;
        }

        header("Location: ?tab=" . urlencode($active_tab));
        exit;
    }
}

$where = [];
$params = [];
$types = '';

if ($active_tab === 'pending' || $active_tab === 'insights' || $active_tab === 'all_requests') {
    $where[] = "status = 'pending'";
}

if ($active_tab === 'completed') {
    $where[] = "status IN ('approved','rejected')";
}

if ($filter_type !== 'All' && in_array($filter_type, ['Leave', 'Attendance'], true)) {
    $where[] = "type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if ($search_q !== '') {
    $where[] = "(emp_name LIKE ? OR emp_code LIKE ?)";
    $like = '%' . $search_q . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT *
    FROM approval_requests
    $where_sql
    ORDER BY requested_on DESC, id DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$pending_requests = [];
while ($row = $res->fetch_assoc()) {
    $pending_requests[] = [
        'id'         => $row['id'],
        'emp_code'   => $row['emp_code'],
        'emp_name'   => $row['emp_name'],
        'type'       => $row['type'],
        'stage'      => $row['stage'],
        'date'       => fmtDate($row['request_date']),
        'requested'  => fmtDateTime($row['requested_on']),
        'shift_date' => fmtDate($row['shift_date']),
        'in_old'     => $row['in_old'],
        'in_new'     => $row['in_new'],
        'out_old'    => $row['out_old'],
        'out_new'    => $row['out_new'],
        'reasons'    => $row['reasons'],
        'remarks'    => $row['remarks'],
        'status'     => $row['status'],
        'action_at'  => fmtDateTime($row['action_at']),
    ];
}
$stmt->close();

$total_requests   = count($pending_requests);
$leave_count      = count(array_filter($pending_requests, fn($r) => ($r['type'] ?? '') === 'Leave'));
$attendance_count = count(array_filter($pending_requests, fn($r) => ($r['type'] ?? '') === 'Attendance'));

$completed_total = 0;
$approved_total  = 0;
$rejected_total  = 0;

$resStats = $conn->query("
    SELECT
      SUM(status='pending') AS pending_total,
      SUM(status='approved') AS approved_total,
      SUM(status='rejected') AS rejected_total,
      COUNT(*) AS all_total
    FROM approval_requests
");
if ($resStats && $stats = $resStats->fetch_assoc()) {
    $approved_total  = (int)$stats['approved_total'];
    $rejected_total  = (int)$stats['rejected_total'];
    $completed_total = $approved_total + $rejected_total;
}

if ($selected_id <= 0 && !empty($pending_requests)) {
    $selected_id = (int)$pending_requests[0]['id'];
}

$selected_req = null;
foreach ($pending_requests as $r) {
    if ((int)$r['id'] === (int)$selected_id) {
        $selected_req = $r;
        break;
    }
}

if (!$selected_req && !empty($pending_requests)) {
    $selected_req = $pending_requests[0];
    $selected_id  = (int)$selected_req['id'];
}

function renderRightPanel($selected_req) {
    if (!$selected_req) {
        return '<div class="ar-empty"><p>No request selected</p></div>';
    }

    ob_start();
    ?>

    
    <div class="ar-right-head">
        <div class="ar-right-av"><?= initials($selected_req['emp_name'] ?? '') ?></div>
        <div>
            <div class="ar-right-name"><?= esc($selected_req['emp_name'] ?? '') ?></div>
            <div class="ar-right-type"><?= esc($selected_req['type'] ?? '') ?> Request</div>
        </div>
    </div>

    <div class="ar-detail-row">
        <span class="ar-detail-label">Requested On :</span>
        <span class="ar-detail-val"><?= esc($selected_req['requested'] ?? '—') ?></span>
    </div>

    <?php if (($selected_req['type'] ?? '') === 'Attendance'): ?>
        <div class="ar-detail-row">
            <span class="ar-detail-label">Shift Date :</span>
            <span class="ar-detail-val"><?= esc($selected_req['shift_date'] ?? '—') ?></span>
        </div>

        <?php if (!empty($selected_req['in_old'])): ?>
            <div class="ar-detail-row">
                <span class="ar-detail-label">In Time :</span>
                <span class="ar-detail-val">
                    <span class="ar-detail-strike"><?= esc($selected_req['in_old']) ?></span>
                    <?= esc($selected_req['in_new'] ?? '—') ?>
                </span>
            </div>

            <div class="ar-detail-row">
                <span class="ar-detail-label">Out Time :</span>
                <span class="ar-detail-val">
                    <span class="ar-detail-strike"><?= esc($selected_req['out_old'] ?? '') ?></span>
                    <?= esc($selected_req['out_new'] ?? '—') ?>
                </span>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="ar-detail-row">
            <span class="ar-detail-label">Leave Type :</span>
            <span class="ar-detail-val"><?= esc($selected_req['reasons'] ?? '—') ?></span>
        </div>
    <?php endif; ?>

    <div class="ar-detail-row">
        <span class="ar-detail-label">Reasons :</span>
        <span class="ar-detail-val"><?= esc($selected_req['reasons'] ?? '') ?: '—' ?></span>
    </div>

    <div class="ar-detail-row">
        <span class="ar-detail-label">Remarks :</span>
        <span class="ar-detail-val"><?= esc($selected_req['remarks'] ?? '') ?: '—' ?></span>
    </div>

    <?php if (($selected_req['status'] ?? '') === 'pending'): ?>
    <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
        <form method="POST" style="flex:1">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="req_id" value="<?= esc($selected_req['id'] ?? '') ?>">
            <button type="submit" class="ar-btn-reject" style="width:100%;padding:8px">Reject</button>
        </form>

        <form method="POST" style="flex:1">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="req_id" value="<?= esc($selected_req['id'] ?? '') ?>">
            <button type="submit" class="ar-btn-approve" style="width:100%;padding:8px">Approve</button>
        </form>
    </div>
    <?php else: ?>
    <div class="ar-detail-row">
        <span class="ar-detail-label">Status :</span>
        <span class="ar-detail-val"><?= esc(ucfirst($selected_req['status'] ?? '')) ?></span>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

function insightFilterButton() {
    ob_start();
    ?>
    <div class="ar-filter-wrap">
        <button type="button" class="ar-stat-filter" onclick="toggleInsightFilter(this)">
            <span class="filter-label">This Month</span>
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        </button>

        <div class="ar-filter-menu">
            <button type="button" onclick="applyInsightFilter('this_month','This Month')">This Month</button>
            <button type="button" onclick="applyInsightFilter('last_month','Last Month')">Last Month</button>
            <button type="button" onclick="applyInsightFilter('this_year','This Year')">This Year</button>
            <button type="button" onclick="openCustomDateFilter()">Select Date</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">


<style>
/* ═══════════════════════════════════════
   APPROVAL REQUEST PAGE
═══════════════════════════════════════ */

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

.ar-empty-mini {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 130px;
    gap: 10px;
}
.ar-empty-mini p { font-size: 12.5px; color: #9CA3AF; text-align: center; }

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

.ar-mid {
    border-right: 1px solid #E5E7EB;
    overflow-y: auto;
    max-height: 560px;
}

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

.ar-empty {
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:50px 24px;gap:12px;
}
.ar-empty p { font-size:13px;color:#9CA3AF;text-align:center; }

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
.ar-empty-robot-svg {
    width:90px;height:90px;opacity:.35;
}

.ar-toast {
    position:fixed;bottom:24px;left:90%;transform:translateX(-50%) translateY(80px);
    background:#111827;color:#fff;padding:11px 20px;border-radius:10px;
    font-size:13px;font-weight:500;z-index:999;display:flex;align-items:center;
    gap:8px;box-shadow:0 8px 28px rgba(0,0,0,.2);transition:transform .3s ease;white-space:nowrap;
}
.ar-toast.show { transform:translateX(-50%) translateY(0); }

/* only functional css for dropdown/date modal */
.ar-filter-wrap {
    position: relative;
}
.ar-filter-menu {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    min-width: 150px;
    background: #fff;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    box-shadow: 0 12px 35px rgba(0,0,0,.12);
    padding: 6px;
    z-index: 50;
}
.ar-filter-wrap.open .ar-filter-menu {
    display: block;
}
.ar-filter-menu button {
    width: 100%;
    border: none;
    background: transparent;
    padding: 9px 10px;
    text-align: left;
    border-radius: 8px;
    font-size: 12.5px;
    color: #374151;
    cursor: pointer;
    font-family: inherit;
}
.ar-filter-menu button:hover {
    background: #EFF6FF;
    color: #2563EB;
}
.ar-date-modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.ar-date-modal.show {
    display: flex;
}
.ar-date-box {
    width: 100%;
    max-width: 360px;
    background: #fff;
    border-radius: 16px;
    padding: 18px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
}
.ar-date-box h3 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 14px;
    color: #111827;
}
.ar-date-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
}
.ar-date-field label {
    font-size: 12px;
    font-weight: 600;
    color: #6B7280;
}
.ar-date-field input {
    border: 1.5px solid #E5E7EB;
    border-radius: 9px;
    padding: 9px 10px;
    font-size: 13px;
    outline: none;
    font-family: inherit;
}
.ar-date-field input:focus {
    border-color: #2563EB;
}
.ar-date-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}

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
        <a href="?tab=<?= esc($tkey) ?>"
           class="ar-tab <?= $active_tab === $tkey ? 'active' : '' ?>">
           <?= esc($tlabel) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($active_tab === 'insights'): ?>

<div class="ar-stat-row">

    <div class="ar-stat-card">
        <div class="ar-stat-head">
            <span class="ar-stat-title">Total Approvals</span>
            <?= insightFilterButton() ?>
        </div>
        <div class="ar-stat-big" id="insightTotalBig"><?= (int)($total_requests + $completed_total) ?></div>
        <div class="ar-stat-sub">Total Approvals Received</div>
        <div class="ar-stat-row-items">
            <div class="ar-stat-item">
                <div class="ar-stat-item-left">Pending</div>
                <span class="ar-stat-count" id="insightPendingCount"><?= (int)$total_requests ?></span>
            </div>
            <div class="ar-stat-item">
                <div class="ar-stat-item-left">Completed</div>
                <span class="ar-stat-count" id="insightCompletedCount"><?= (int)$completed_total ?></span>
            </div>
        </div>
    </div>

    <div class="ar-stat-card">
        <div class="ar-stat-head">
            <span class="ar-stat-title">Pending Approvals</span>
            <?= insightFilterButton() ?>
        </div>
        <div class="ar-empty-mini">
            <p id="insightPendingText"><?= $total_requests ? $total_requests . ' pending approval(s)' : "You don't have any pending approvals!" ?></p>
        </div>
    </div>

    <div class="ar-stat-card">
        <div class="ar-stat-head">
            <span class="ar-stat-title">Approval Request Stats - <?= date('Y') ?></span>
            <?= insightFilterButton() ?>
        </div>
        <div class="ar-empty-mini">
            <p id="insightStatsText">
                Approved: <?= (int)$approved_total ?> | Rejected: <?= (int)$rejected_total ?>
            </p>
        </div>
    </div>

</div>

<div class="ar-split no-right">
    <div class="ar-left">
        <div class="ar-left-head">Pending Approvals</div>
        <div class="ar-total-row" id="insightTotalRow">Total Requests - <?= (int)$total_requests ?></div>
    </div>
    <div class="ar-mid">
        <div class="ar-toolbar">
            <div class="ar-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search" oninput="filterCards(this.value)">
            </div>
            <label class="ar-select-all">
                <input type="checkbox" onchange="toggleSelectAll(this)"> Select All
            </label>
            <select class="ar-type-filter" onchange="filterCards(document.getElementById('insightSearch')?.value || '')">
                <option>All</option>
                <option>Attendance</option>
                <option>Leave</option>
            </select>
        </div>

        <?php if (empty($pending_requests)): ?>
            <div class="ar-empty" style="min-height:220px">
                <p style="font-size:14px;font-weight:600;color:#374151">No Pending Approvals</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending_requests as $req): ?>
                <?php $rid = (int)$req['id']; ?>
                <div class="ar-req-card" data-type="<?= esc($req['type']) ?>" data-name="<?= esc(strtolower($req['emp_name'] . ' ' . $req['emp_code'])) ?>">
                    <input type="checkbox" class="req-chk" onclick="event.stopPropagation()">
                    <div class="ar-req-av"><?= initials($req['emp_name']) ?></div>
                    <div class="ar-req-body">
                        <div class="ar-req-name"><?= esc($req['emp_name']) ?> - <?= esc($req['emp_code']) ?></div>
                        <div class="ar-req-stage"><?= esc($req['stage']) ?></div>
                        <div class="ar-req-date"><?= esc($req['date']) ?></div>
                    </div>
                    <span class="ar-type-badge <?= esc($req['type']) ?>"><?= esc($req['type']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($active_tab === 'pending'): ?>

<div class="ar-split no-right">
    <div class="ar-left">
        <div class="ar-left-head">Pending Approvals</div>
        <div class="ar-total-row">Total Requests - <?= (int)$total_requests ?></div>
    </div>

    <div class="ar-mid">
        <div class="ar-toolbar">
            <div class="ar-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="pendingSearch" placeholder="Search" oninput="filterCards(this.value)">
            </div>
            <label class="ar-select-all">
                <input type="checkbox" onchange="toggleSelectAll(this)"> Select All
            </label>
            <select class="ar-type-filter" id="pendingTypeFilter" onchange="filterCards(document.getElementById('pendingSearch').value)">
                <option value="All">All</option>
                <option value="Attendance">Attendance</option>
                <option value="Leave">Leave</option>
            </select>
        </div>

        <?php if (empty($pending_requests)): ?>
            <div class="ar-empty" style="min-height:280px">
                <p style="font-size:14px;font-weight:600;color:#374151">No Pending Approvals</p>
            </div>
        <?php else: ?>
            <?php foreach ($pending_requests as $req): ?>
                <?php $rid = (int)$req['id']; ?>
                <div class="ar-req-card" data-type="<?= esc($req['type']) ?>" data-name="<?= esc(strtolower($req['emp_name'] . ' ' . $req['emp_code'])) ?>">
                    <input type="checkbox" class="req-chk" onclick="event.stopPropagation()">
                    <div class="ar-req-av"><?= initials($req['emp_name']) ?></div>
                    <div class="ar-req-body">
                        <div class="ar-req-name"><?= esc($req['emp_name']) ?> - <?= esc($req['emp_code']) ?></div>
                        <div class="ar-req-stage"><?= esc($req['stage']) ?></div>
                        <div class="ar-req-date"><?= esc($req['date']) ?></div>
                        <div class="ar-req-btns">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="req_id" value="<?= $rid ?>">
                                <button type="submit" class="ar-btn-reject">Reject</button>
                            </form>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="req_id" value="<?= $rid ?>">
                                <button type="submit" class="ar-btn-approve">Approve</button>
                            </form>
                        </div>
                    </div>
                    <span class="ar-type-badge <?= esc($req['type']) ?>"><?= esc($req['type']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($active_tab === 'completed'): ?>

<div class="ar-comp-wrap">
    <div class="ar-comp-head">
        <h3>Completed Approvals</h3>
        <p>Select an employee or date range to view completed requests.</p>
    </div>

    <form method="GET" class="ar-comp-toolbar">
        <input type="hidden" name="tab" value="completed">

        <div class="ar-comp-search">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="search" value="<?= esc($search_q) ?>" placeholder="Search by name or #code">
        </div>

        <select class="ar-comp-select" name="filter">
            <option value="All" <?= $filter_type === 'All' ? 'selected' : '' ?>>All</option>
            <option value="Attendance" <?= $filter_type === 'Attendance' ? 'selected' : '' ?>>Attendance</option>
            <option value="Leave" <?= $filter_type === 'Leave' ? 'selected' : '' ?>>Leave</option>
        </select>

        <button class="ar-comp-search-btn" type="submit">Search</button>
    </form>

    <?php if (empty($pending_requests)): ?>
        <div class="ar-empty" id="compEmptyState" style="min-height:320px">
            <p style="font-size:13.5px;font-weight:600;color:#374151">No completed approvals found</p>
        </div>
    <?php else: ?>
        <div id="compResults" style="overflow-x:auto">
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
                <tbody>
                    <?php foreach ($pending_requests as $req): ?>
                    <tr style="border-bottom:1px solid #F3F4F6">
                        <td style="padding:11px 16px;font-weight:500"><?= esc($req['emp_name']) ?> - <?= esc($req['emp_code']) ?></td>
                        <td style="padding:11px 16px;color:#6B7280"><?= esc($req['type']) ?></td>
                        <td style="padding:11px 16px;color:#6B7280"><?= esc($req['action_at']) ?></td>
                        <td style="padding:11px 16px;color:#6B7280"><?= esc($req['stage']) ?></td>
                        <td style="padding:11px 16px;text-align:center"><?= esc(ucfirst($req['status'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php elseif ($active_tab === 'all_requests'): ?>

<div class="ar-split" id="arSplit">
    <div class="ar-left">
        <div class="ar-left-head">All Open Requests</div>
        <div class="ar-total-row">Total Requests - <?= (int)$total_requests ?></div>

        <div class="ar-type-row <?= $filter_type==='All'?'active':'' ?>" onclick="setFilter('All')">
            All - <?= (int)$total_requests ?>
        </div>

        <?php if ($leave_count > 0): ?>
        <div class="ar-type-row <?= $filter_type==='Leave'?'active':'' ?>" onclick="setFilter('Leave')">
            Leave - <?= (int)$leave_count ?>
        </div>
        <?php endif; ?>

        <?php if ($attendance_count > 0): ?>
        <div class="ar-type-row <?= $filter_type==='Attendance'?'active':'' ?>" onclick="setFilter('Attendance')">
            Attendance - <?= (int)$attendance_count ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="ar-mid" id="arMid">
        <div class="ar-toolbar">
            <div class="ar-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="allSearch" placeholder="Search" oninput="filterAllReqs(this.value)">
            </div>

            <label class="ar-select-all">
                <input type="checkbox" id="selectAllChk" onchange="toggleSelectAll(this)"> Select All
            </label>

            <select class="ar-type-filter" id="typeFilterSel" onchange="filterAllReqs(document.getElementById('allSearch').value)">
                <option value="All" <?= $filter_type==='All'?'selected':'' ?>>All</option>
                <option value="Attendance" <?= $filter_type==='Attendance'?'selected':'' ?>>Attendance</option>
                <option value="Leave" <?= $filter_type==='Leave'?'selected':'' ?>>Leave</option>
            </select>
        </div>

        <div id="allReqCards">
        <?php if (empty($pending_requests)): ?>
            <div class="ar-empty" style="min-height:280px">
                <p style="font-size:14px;font-weight:600;color:#374151">No Open Requests</p>
            </div>
        <?php endif; ?>

        <?php foreach ($pending_requests as $req): ?>
        <?php $rid = (int)($req['id'] ?? 0); ?>
        <div class="ar-req-card <?= $rid===(int)$selected_id ? 'active':'' ?>"
             id="reqCard-<?= $rid ?>"
             data-type="<?= esc($req['type'] ?? '') ?>"
             data-name="<?= esc(strtolower(($req['emp_name'] ?? '') . ' ' . ($req['emp_code'] ?? ''))) ?>"
             onclick="selectReq(<?= $rid ?>)">
            <input type="checkbox" class="req-chk" onclick="event.stopPropagation()">
            <div class="ar-req-av"><?= initials($req['emp_name'] ?? '') ?></div>
            <div class="ar-req-body">
                <div class="ar-req-name"><?= esc($req['emp_name'] ?? '') ?> - <?= esc($req['emp_code'] ?? '') ?></div>
                <div class="ar-req-stage"><?= esc($req['stage'] ?? '') ?></div>
                <div class="ar-req-date"><?= esc($req['date'] ?? '') ?></div>
                <div class="ar-req-btns">
                    <form method="POST" style="display:inline" onclick="event.stopPropagation()">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="req_id" value="<?= $rid ?>">
                        <button type="submit" class="ar-btn-reject">Reject</button>
                    </form>
                    <form method="POST" style="display:inline" onclick="event.stopPropagation()">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="req_id" value="<?= $rid ?>">
                        <button type="submit" class="ar-btn-approve">Approve</button>
                    </form>
                    <button class="ar-btn-detail" onclick="event.stopPropagation();selectReq(<?= $rid ?>)">Detailed View</button>
                </div>
            </div>
            <span class="ar-type-badge <?= esc($req['type'] ?? '') ?>"><?= esc($req['type'] ?? '') ?></span>

            <script type="application/json" id="reqData-<?= $rid ?>">
                <?= json_encode($req, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
            </script>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="ar-right" id="arRight">
        <?= renderRightPanel($selected_req) ?>
    </div>
</div>

<?php endif; ?>

<div class="ar-date-modal" id="insightDateModal">
    <div class="ar-date-box">
        <h3>Select Date Range</h3>

        <div class="ar-date-field">
            <label>From Date</label>
            <input type="date" id="insightFromDate">
        </div>

        <div class="ar-date-field">
            <label>To Date</label>
            <input type="date" id="insightToDate">
        </div>

        <div class="ar-date-actions">
            <button type="button" class="ar-btn-reject" onclick="closeCustomDateFilter()">Cancel</button>
            <button type="button" class="ar-btn-approve" onclick="applyCustomDateFilter()">Apply</button>
        </div>
    </div>
</div>

<div class="ar-toast" id="arToastEl">
    <span id="arToastIcon">✅</span>
    <span id="arToastMsg">Done!</span>
</div>

<script>
function arToast(icon, msg) {
    var t  = document.getElementById('arToastEl');
    var ti = document.getElementById('arToastIcon');
    var tm = document.getElementById('arToastMsg');

    if (!t || !ti || !tm) return;

    ti.textContent = icon;
    tm.textContent = msg;
    t.classList.add('show');

    clearTimeout(t._t);
    t._t = setTimeout(function(){ t.classList.remove('show'); }, 3200);
}

function escHtml(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function makeInitials(name) {
    name = String(name || '').trim();
    if (!name) return 'NA';

    var parts = name.split(/\s+/);
    return ((parts[0] || '').charAt(0) + (parts[1] || '').charAt(0)).toUpperCase();
}

function selectReq(id) {
    document.querySelectorAll('.ar-req-card').forEach(function(c){
        c.classList.toggle('active', c.id === 'reqCard-' + id);
    });

    var dataEl = document.getElementById('reqData-' + id);
    var right = document.getElementById('arRight');

    if (!dataEl || !right) return;

    var req = {};

    try {
        req = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        arToast('⚠', 'Invalid request data');
        return;
    }

    var html = `
        <div class="ar-right-head">
            <div class="ar-right-av">${makeInitials(req.emp_name)}</div>
            <div>
                <div class="ar-right-name">${escHtml(req.emp_name || '')}</div>
                <div class="ar-right-type">${escHtml(req.type || '')} Request</div>
            </div>
        </div>

        <div class="ar-detail-row">
            <span class="ar-detail-label">Requested On :</span>
            <span class="ar-detail-val">${escHtml(req.requested || '—')}</span>
        </div>
    `;

    if (req.type === 'Attendance') {
        html += `
            <div class="ar-detail-row">
                <span class="ar-detail-label">Shift Date :</span>
                <span class="ar-detail-val">${escHtml(req.shift_date || '—')}</span>
            </div>
        `;

        if (req.in_old) {
            html += `
                <div class="ar-detail-row">
                    <span class="ar-detail-label">In Time :</span>
                    <span class="ar-detail-val">
                        <span class="ar-detail-strike">${escHtml(req.in_old || '')}</span>
                        ${escHtml(req.in_new || '—')}
                    </span>
                </div>

                <div class="ar-detail-row">
                    <span class="ar-detail-label">Out Time :</span>
                    <span class="ar-detail-val">
                        <span class="ar-detail-strike">${escHtml(req.out_old || '')}</span>
                        ${escHtml(req.out_new || '—')}
                    </span>
                </div>
            `;
        }
    } else {
        html += `
            <div class="ar-detail-row">
                <span class="ar-detail-label">Leave Type :</span>
                <span class="ar-detail-val">${escHtml(req.reasons || '—')}</span>
            </div>
        `;
    }

    html += `
        <div class="ar-detail-row">
            <span class="ar-detail-label">Reasons :</span>
            <span class="ar-detail-val">${escHtml(req.reasons || '—')}</span>
        </div>

        <div class="ar-detail-row">
            <span class="ar-detail-label">Remarks :</span>
            <span class="ar-detail-val">${escHtml(req.remarks || '—')}</span>
        </div>

        <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="req_id" value="${escHtml(req.id || '')}">
                <button type="submit" class="ar-btn-reject" style="width:100%;padding:8px">Reject</button>
            </form>

            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="req_id" value="${escHtml(req.id || '')}">
                <button type="submit" class="ar-btn-approve" style="width:100%;padding:8px">Approve</button>
            </form>
        </div>
    `;

    right.innerHTML = html;

    var url = new URL(window.location.href);
    url.searchParams.set('tab', 'all_requests');
    url.searchParams.set('req', id);
    history.replaceState(null, '', url.toString());

    if (window.innerWidth <= 1000) {
        right.scrollIntoView({behavior:'smooth', block:'nearest'});
    }
}

function setFilter(type) {
    var sel = document.getElementById('typeFilterSel');
    if (sel) sel.value = type;

    filterAllReqs(document.getElementById('allSearch')?.value || '');

    document.querySelectorAll('.ar-type-row').forEach(function(r){
        r.classList.toggle('active', r.textContent.trim().startsWith(type));
    });

    var url = new URL(window.location.href);
    url.searchParams.set('filter', type);
    history.replaceState(null, '', url.toString());
}

function filterCards(q) {
    q = String(q || '').toLowerCase().trim();

    document.querySelectorAll('.ar-req-card').forEach(function(card) {
        var nameMatch = !q || (card.dataset.name || '').includes(q);
        card.style.display = nameMatch ? '' : 'none';
    });
}

function filterAllReqs(q) {
    q = String(q || '').toLowerCase().trim();

    var typeSel = document.getElementById('typeFilterSel');
    var typeVal = typeSel ? typeSel.value : 'All';

    var cards = document.querySelectorAll('.ar-req-card');

    cards.forEach(function(card) {
        var nameMatch = !q || (card.dataset.name || '').includes(q);
        var typeMatch = typeVal === 'All' || card.dataset.type === typeVal;
        card.style.display = nameMatch && typeMatch ? '' : 'none';
    });
}

function toggleSelectAll(chk) {
    document.querySelectorAll('.req-chk').forEach(function(c){
        if (c.closest('.ar-req-card')?.style.display !== 'none') {
            c.checked = chk.checked;
        }
    });
}

function toggleInsightFilter(btn) {
    document.querySelectorAll('.ar-filter-wrap').forEach(function(wrap) {
        if (wrap !== btn.closest('.ar-filter-wrap')) {
            wrap.classList.remove('open');
        }
    });

    btn.closest('.ar-filter-wrap').classList.toggle('open');
}

function applyInsightFilter(type, label) {
    document.querySelectorAll('.ar-filter-wrap').forEach(function(wrap) {
        wrap.classList.remove('open');
        var labelEl = wrap.querySelector('.filter-label');
        if (labelEl) labelEl.textContent = label;
    });

    arToast('✅', 'Filter applied: ' + label);
}

function openCustomDateFilter() {
    document.querySelectorAll('.ar-filter-wrap').forEach(function(wrap) {
        wrap.classList.remove('open');
    });

    document.getElementById('insightDateModal')?.classList.add('show');
}

function closeCustomDateFilter() {
    document.getElementById('insightDateModal')?.classList.remove('show');
}

function applyCustomDateFilter() {
    var from = document.getElementById('insightFromDate')?.value || '';
    var to = document.getElementById('insightToDate')?.value || '';

    if (!from || !to) {
        arToast('⚠', 'Please select from and to date.');
        return;
    }

    if (from > to) {
        arToast('⚠', 'From date cannot be greater than to date.');
        return;
    }

    document.querySelectorAll('.filter-label').forEach(function(el) {
        el.textContent = 'Custom Date';
    });

    closeCustomDateFilter();
    arToast('📅', 'Filter applied: ' + from + ' to ' + to);
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.ar-filter-wrap')) {
        document.querySelectorAll('.ar-filter-wrap').forEach(function(wrap) {
            wrap.classList.remove('open');
        });
    }

    if (e.target.id === 'insightDateModal') {
        closeCustomDateFilter();
    }
});

<?php if ($toast_msg): ?>
document.addEventListener('DOMContentLoaded', function(){
    arToast(<?= json_encode($toast_icon) ?>, <?= json_encode($toast_msg) ?>);
});
<?php endif; ?>
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>