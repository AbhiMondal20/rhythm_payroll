<?php
session_start();

// Handle Authentication
if (!isset($_SESSION['login'])) {
    if (isset($_POST['action']) || isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

// If your connection variable is different (e.g., $db or $link), assign it here:
$conn = $conn ?? $db ?? null; 

// ========================================================================
// API / AJAX HANDLER (Self-contained, using strictly mysqli)
// ========================================================================
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action) {
    // Clear out any accidental whitespace or HTML from included files
    // This prevents the "Network Error" caused by invalid JSON
    if (ob_get_length()) ob_clean(); 
    
    header('Content-Type: application/json');
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection missing. Check your variable name in db_client.php']);
        exit();
    }

    try {
        switch ($action) {

            /* ── LIST TEAMS ────────────────────────────────────────────── */
            case 'list':
                $sql = "SELECT t.id, t.name,
                            (SELECT COUNT(*) FROM team_owners WHERE team_id = t.id) AS owner_count,
                            (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) AS member_count,
                            t.created_at
                        FROM teams t
                        WHERE t.is_deleted = 0
                        ORDER BY t.created_at DESC";
                
                $result = mysqli_query($conn, $sql);
                $teams = [];
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $teams[] = $row;
                    }
                }
                echo json_encode(['success' => true, 'data' => $teams]);
                break;

            /* ── GET SINGLE TEAM (with owners + members) ───────────────── */
            case 'get':
                $id = (int)($_GET['id'] ?? 0);
                if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); break; }

                // Get Team
                $resT = mysqli_query($conn, "SELECT id, name FROM teams WHERE id=$id AND is_deleted=0");
                $team = mysqli_fetch_assoc($resT);
                if (!$team) { echo json_encode(['success' => false, 'message' => 'Not found']); break; }

                // Get Owners (Updated to match provided schema)
                $team['owners'] = [];
                $sqlO = "SELECT e.id, e.employee_name AS name, e.designation
                         FROM team_owners to2
                         JOIN employees e ON e.id = to2.employee_id
                         WHERE to2.team_id = $id";
                $resO = mysqli_query($conn, $sqlO);
                if ($resO) {
                    while ($row = mysqli_fetch_assoc($resO)) { $team['owners'][] = $row; }
                }

                // Get Members (Updated to match provided schema)
                $team['members'] = [];
                $sqlM = "SELECT e.id, e.employee_name AS name, e.designation
                         FROM team_members tm2
                         JOIN employees e ON e.id = tm2.employee_id
                         WHERE tm2.team_id = $id";
                $resM = mysqli_query($conn, $sqlM);
                if ($resM) {
                    while ($row = mysqli_fetch_assoc($resM)) { $team['members'][] = $row; }
                }

                echo json_encode(['success' => true, 'data' => $team]);
                break;

            /* ── CREATE TEAM ───────────────────────────────────────────── */
            case 'add':
                $name    = trim($_POST['name'] ?? '');
                $owners  = json_decode($_POST['owners'] ?? '[]', true) ?: [];
                $members = json_decode($_POST['members'] ?? '[]', true) ?: [];
                $user_id = (int)($_SESSION['user_id'] ?? 0);

                if (!$name) {
                    echo json_encode(['success' => false, 'message' => 'Team name is required.']);
                    break;
                }

                $name_esc = mysqli_real_escape_string($conn, $name);

                mysqli_autocommit($conn, false); // Start transaction

                $ins_sql = "INSERT INTO teams (name, created_by) VALUES ('$name_esc', $user_id)";
                if (!mysqli_query($conn, $ins_sql)) {
                    throw new Exception(mysqli_error($conn));
                }
                
                $teamId = mysqli_insert_id($conn);

                // Insert Owners
                if (!empty($owners)) {
                    foreach (array_unique(array_map('intval', $owners)) as $eid) {
                        mysqli_query($conn, "INSERT INTO team_owners (team_id, employee_id) VALUES ($teamId, $eid)");
                    }
                }

                // Insert Members
                if (!empty($members)) {
                    foreach (array_unique(array_map('intval', $members)) as $eid) {
                        mysqli_query($conn, "INSERT INTO team_members (team_id, employee_id) VALUES ($teamId, $eid)");
                    }
                }

                mysqli_commit($conn);
                mysqli_autocommit($conn, true); // Restore autocommit

                echo json_encode(['success' => true, 'message' => 'Team created successfully.', 'id' => $teamId]);
                break;

            /* ── UPDATE TEAM ───────────────────────────────────────────── */
            case 'update':
                $id      = (int)($_POST['id'] ?? 0);
                $name    = trim($_POST['name'] ?? '');
                $owners  = json_decode($_POST['owners'] ?? '[]', true) ?: [];
                $members = json_decode($_POST['members'] ?? '[]', true) ?: [];

                if (!$id || !$name) {
                    echo json_encode(['success' => false, 'message' => 'Team name is required.']);
                    break;
                }

                $name_esc = mysqli_real_escape_string($conn, $name);

                mysqli_autocommit($conn, false);

                // Update team name
                mysqli_query($conn, "UPDATE teams SET name='$name_esc', updated_at=NOW() WHERE id=$id AND is_deleted=0");

                // Replace Owners
                mysqli_query($conn, "DELETE FROM team_owners WHERE team_id=$id");
                if (!empty($owners)) {
                    foreach (array_unique(array_map('intval', $owners)) as $eid) {
                        mysqli_query($conn, "INSERT INTO team_owners (team_id, employee_id) VALUES ($id, $eid)");
                    }
                }

                // Replace Members
                mysqli_query($conn, "DELETE FROM team_members WHERE team_id=$id");
                if (!empty($members)) {
                    foreach (array_unique(array_map('intval', $members)) as $eid) {
                        mysqli_query($conn, "INSERT INTO team_members (team_id, employee_id) VALUES ($id, $eid)");
                    }
                }

                mysqli_commit($conn);
                mysqli_autocommit($conn, true);

                echo json_encode(['success' => true, 'message' => 'Team updated successfully.']);
                break;

            /* ── DELETE TEAM (soft) ────────────────────────────────────── */
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); break; }
                
                mysqli_query($conn, "UPDATE teams SET is_deleted=1, updated_at=NOW() WHERE id=$id");
                
                echo json_encode(['success' => true, 'message' => 'Team deleted.']);
                break;

            /* ── SEARCH EMPLOYEES ──────────────────────────────────────── */
            case 'search_employees':
                $q = trim($_GET['q'] ?? '');
                $q_esc = mysqli_real_escape_string($conn, $q);
                
                // Updated to match your exact schema columns
                $sql = "SELECT id, employee_name AS name, designation, employee_code
                        FROM employees
                        WHERE status = 'Active' 
                          AND (employee_name LIKE '%$q_esc%' OR employee_code LIKE '%$q_esc%')
                        ORDER BY employee_name
                        LIMIT 50";
                        
                $res = mysqli_query($conn, $sql);
                $data = [];
                if ($res) {
                    while($row = mysqli_fetch_assoc($res)) { $data[] = $row; }
                }
                
                echo json_encode(['success' => true, 'data' => $data]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_autocommit($conn, true);
        error_log('Teams API Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred. Check error logs.']);
    }
    
    // Crucial: Stop script execution so HTML is not returned
    exit();
}

// ========================================================================
// PAGE RENDERER
// ========================================================================
$page_title = 'Teams';
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
.cfg-tabs { display: flex; align-items: center; border-bottom: 1px solid #E5E7EB; background: #fff; overflow-x: auto; scrollbar-width: none; }
.cfg-tabs::-webkit-scrollbar { display: none; }
.cfg-tab { padding: 14px 20px; font-size: 13.5px; font-weight: 500; color: #6B7280; cursor: pointer; border: none; background: transparent; border-bottom: 2.5px solid transparent; white-space: nowrap; transition: color .15s, border-color .15s; text-decoration: none; display: block; margin-bottom: -1px; }
.cfg-tab:hover  { color: #111827; }
.cfg-tab.active { color: #2563EB; border-bottom-color: #2563EB; font-weight: 600; }
.tm-wrapper { background: #fff; min-height: calc(100vh - 120px); padding: 0 0 40px; font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; }
.tm-topbar { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: #fff; border-bottom: 1px solid #E2E8F0; }
.tm-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13.5px; }
.tm-bc-parent  { color: #64748B; cursor: pointer; }
.tm-bc-parent:hover { text-decoration: underline; }
.tm-bc-arrow   { color: #94A3B8; font-size: 16px; }
.tm-bc-current { font-weight: 600; color: #1e293b; }
.tm-btn-create { display: flex; align-items: center; gap: 6px; background: #2563EB; color: #fff; border: none; border-radius: 6px; padding: 9px 18px; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: background .15s; }
.tm-btn-create > span { font-size: 18px; line-height: 1; }
.tm-btn-create:hover  { background: #1D4ED8; }
.tm-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 90px 20px; background: #fff; }
.tm-empty-art  { margin-bottom: 18px; }
.tm-empty-text { font-size: 14px; color: #64748B; margin: 0; }
.tm-list-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; padding: 24px; }
.tm-card { background: #fff; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px 22px; cursor: pointer; transition: box-shadow .18s, border-color .18s; }
.tm-card:hover { box-shadow: 0 4px 16px rgba(37,99,235,.1); border-color: #93C5FD; }
.tm-card-name { font-size: 15px; font-weight: 700; color: #0F172A; margin: 0 0 10px; }
.tm-card-meta { display: flex; gap: 16px; font-size: 12.5px; color: #64748B; }
.tm-card-meta span { display: flex; align-items: center; gap: 5px; }
.tm-avatar-stack { display: flex; margin-top: 14px; }
.tm-avatar-sm { width: 30px; height: 30px; border-radius: 50%; border: 2px solid #fff; background: #DBEAFE; color: #2563EB; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; margin-left: -8px; object-fit: cover; text-transform: uppercase; flex-shrink: 0; }
.tm-avatar-sm:first-child { margin-left: 0; }
.tm-avatar-more { width: 30px; height: 30px; border-radius: 50%; border: 2px solid #fff; background: #F1F5F9; color: #64748B; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; margin-left: -8px; }
.tm-form-wrap { background: #fff; padding: 28px 28px 16px; }
.tm-form-heading { font-size: 15px; font-weight: 700; color: #0F172A; letter-spacing: .3px; margin: 0 0 24px; }
.tm-field-group  { margin-bottom: 24px; }
.tm-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }
.tm-label.required::before { content: '* '; color: #EF4444; }
.tm-input { width: 320px; max-width: 100%; border: none; border-bottom: 1.5px solid #CBD5E1; background: transparent; padding: 6px 0; font-size: 14px; color: #1e293b; outline: none; font-family: inherit; transition: border-color .18s; }
.tm-input:focus { border-bottom-color: #2563EB; }
.tm-section-card { border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px 20px 20px; margin-bottom: 20px; }
.tm-section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.tm-section-title { font-size: 13px; font-weight: 700; letter-spacing: .5px; color: #374151; }
.tm-sec-edit-btn { width: 30px; height: 30px; border: 1.5px solid #E2E8F0; background: #fff; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s, border-color .15s; }
.tm-sec-edit-btn:hover { background: #F0F9FF; border-color: #93C5FD; }
.tm-owners-grid { display: flex; flex-wrap: wrap; gap: 14px; }
.tm-owner-card { border: 1.5px dashed #CBD5E1; border-radius: 8px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; min-width: 180px; max-width: 220px; position: relative; }
.tm-owner-card.filled { border-color: #93C5FD; background: #F0F9FF; }
.tm-owner-avatar { width: 38px; height: 38px; border-radius: 50%; background: #DBEAFE; color: #2563EB; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; text-transform: uppercase; flex-shrink: 0; object-fit: cover; }
.tm-owner-info { flex: 1; min-width: 0; }
.tm-owner-name { font-size: 13px; font-weight: 600; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tm-owner-role { font-size: 11px; color: #64748B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tm-owner-remove { position: absolute; top: 6px; right: 6px; width: 18px; height: 18px; border-radius: 50%; background: #FEE2E2; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #EF4444; line-height: 1; padding: 0; }
.tm-owner-remove:hover { background: #FECACA; }
.tm-placeholder .tm-avatar-ph { width: 38px; height: 38px; border-radius: 50%; background: #E2E8F0; display: block; flex-shrink: 0; }
.tm-ph-lines { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.tm-ph-line  { height: 8px; border-radius: 4px; background: #E2E8F0; }
.tm-ph-line.w70 { width: 70%; }
.tm-ph-line.w50 { width: 50%; }
.tm-members-wrap { display: flex; flex-wrap: wrap; gap: 10px; min-height: 44px; }
.tm-member-pill { border: 1.5px dashed #CBD5E1; border-radius: 30px; padding: 8px 16px; font-size: 13px; color: #374151; display: flex; align-items: center; gap: 8px; white-space: nowrap; background: #fff; }
.tm-member-pill.filled { border-color: #93C5FD; background: #F0F9FF; color: #1D4ED8; }
.tm-member-pill-del { border: none; background: none; cursor: pointer; color: #94A3B8; padding: 0; font-size: 14px; line-height: 1; margin-left: 2px; }
.tm-member-pill-del:hover { color: #EF4444; }
.tm-placeholder-pill { height: 38px; width: 80px; border-radius: 30px; background: #E2E8F0; border: none; }
.tm-placeholder-pill.w120 { width: 120px; }
.tm-placeholder-pill.w90  { width: 90px; }
.tm-form-actions { display: flex; justify-content: flex-end; gap: 12px; padding: 20px 0 12px; border-top: 1px solid #F1F5F9; margin-top: 12px; }
.tm-btn-cancel { padding: 9px 24px; border: 1.5px solid #CBD5E1; background: #fff; color: #64748B; border-radius: 6px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background .15s; }
.tm-btn-cancel:hover { background: #F8FAFC; border-color: #94A3B8; }
.tm-btn-save { padding: 9px 32px; border: none; background: #2563EB; color: #fff; border-radius: 6px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background .15s; }
.tm-btn-save:hover    { background: #1D4ED8; }
.tm-btn-save:disabled { background: #93C5FD; cursor: not-allowed; }
.tm-btn-del { padding: 9px 24px; border: 1.5px solid #FCA5A5; background: #fff; color: #EF4444; border-radius: 6px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background .15s; margin-right: auto; }
.tm-btn-del:hover { background: #FEF2F2; }
.tm-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 7000; }
.tm-picker { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 12px; width: 440px; max-width: 96vw; max-height: 80vh; display: flex; flex-direction: column; z-index: 7001; box-shadow: 0 12px 40px rgba(0,0,0,.18); }
.tm-picker-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px 14px; border-bottom: 1px solid #E2E8F0; }
.tm-picker-title { font-size: 15px; font-weight: 700; color: #0F172A; margin: 0; }
.tm-picker-close { background: none; border: none; cursor: pointer; font-size: 16px; color: #94A3B8; padding: 4px 8px; border-radius: 4px; }
.tm-picker-close:hover { background: #F1F5F9; color: #374151; }
.tm-picker-search { padding: 14px 22px 10px; }
.tm-search-input { width: 100%; box-sizing: border-box; border: 1.5px solid #CBD5E1; border-radius: 8px; padding: 9px 14px; font-size: 13.5px; color: #1e293b; outline: none; font-family: inherit; transition: border-color .15s; }
.tm-search-input:focus { border-color: #2563EB; }
.tm-picker-list { flex: 1; overflow-y: auto; padding: 4px 12px 8px; }
.tm-picker-emp { display: flex; align-items: center; gap: 12px; padding: 10px 10px; border-radius: 8px; cursor: pointer; transition: background .12s; }
.tm-picker-emp:hover { background: #F8FAFC; }
.tm-picker-emp.selected { background: #EFF6FF; }
.tm-picker-emp-avatar { width: 36px; height: 36px; border-radius: 50%; background: #DBEAFE; color: #2563EB; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; text-transform: uppercase; flex-shrink: 0; object-fit: cover; }
.tm-picker-emp-info { flex: 1; min-width: 0; }
.tm-picker-emp-name { font-size: 13.5px; font-weight: 600; color: #0F172A; }
.tm-picker-emp-role { font-size: 12px; color: #64748B; }
.tm-picker-emp-check { width: 18px; height: 18px; border-radius: 4px; border: 2px solid #CBD5E1; background: #fff; flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: background .12s, border-color .12s; }
.tm-picker-emp.selected .tm-picker-emp-check { background: #2563EB; border-color: #2563EB; color: #fff; font-size: 11px; }
.tm-picker-emp.selected .tm-picker-emp-check::after { content: '✓'; }
.tm-picker-footer { padding: 14px 22px; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; gap: 12px; }
.tm-picker-empty { text-align: center; padding: 32px 20px; color: #94A3B8; font-size: 13.5px; }
.tm-detail-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 12px; width: 500px; max-width: 96vw; max-height: 80vh; display: flex; flex-direction: column; z-index: 7001; box-shadow: 0 12px 40px rgba(0,0,0,.18); }
.tm-detail-body { flex: 1; overflow-y: auto; padding: 20px 22px; }
.tm-detail-section { margin-bottom: 22px; }
.tm-detail-section-title { font-size: 12px; font-weight: 700; letter-spacing: .6px; color: #64748B; text-transform: uppercase; margin-bottom: 12px; }
.tm-detail-owners-list { display: flex; flex-wrap: wrap; gap: 12px; }
.tm-detail-member-list { display: flex; flex-wrap: wrap; gap: 8px; }
.tm-detail-member-chip { background: #EFF6FF; color: #1D4ED8; border-radius: 20px; padding: 5px 14px; font-size: 12.5px; font-weight: 500; }
.tm-toast { position: fixed; bottom: 28px; right: 28px; background: #1E293B; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 13.5px; box-shadow: 0 4px 20px rgba(0,0,0,.18); z-index: 9999; opacity: 0; transform: translateY(12px); transition: opacity .25s, transform .25s; pointer-events: none; }
.tm-toast.success { background: #166534; }
.tm-toast.error   { background: #991B1B; }
.tm-toast.show    { opacity: 1; transform: translateY(0); }
@media (max-width: 640px) { .tm-input { width: 100%; } .tm-owners-grid { flex-direction: column; } .tm-owner-card { max-width: 100%; } }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden;">
    <div class="cfg-tabs">
        <?php foreach(['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k => $l): ?>
        <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k === 'Others' ? 'active' : '' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="tm-wrapper">

    <div class="tm-topbar">
        <div class="tm-breadcrumb">
            <span class="tm-bc-parent">Others</span>
            <span class="tm-bc-arrow">›</span>
            <span class="tm-bc-current">Teams</span>
        </div>
        <button class="tm-btn-create" id="btnCreateTeam" onclick="TM.openForm()">
            <span>+</span> Create New Team
        </button>
    </div>

    <div class="tm-empty" id="tmEmpty">
        <div class="tm-empty-art">
            <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="50" fill="#EEF2FF"/>
                <rect x="24" y="20" width="52" height="60" rx="5" fill="#CBD5E1"/>
                <rect x="24" y="20" width="52" height="16" rx="5" fill="#94A3B8"/>
                <rect x="33" y="44" width="26" height="4" rx="2" fill="#3B82F6"/>
                <rect x="33" y="52" width="20" height="4" rx="2" fill="#3B82F6"/>
                <rect x="33" y="60" width="14" height="4" rx="2" fill="#93C5FD"/>
            </svg>
        </div>
        <p class="tm-empty-text">There are No Teams!</p>
    </div>

    <div class="tm-list-grid" id="tmListGrid" style="display:none;"></div>

    <div class="tm-form-wrap" id="tmFormWrap" style="display:none;">
        <h2 class="tm-form-heading" id="tmFormHeading">NEW TEAM</h2>
        <div class="tm-field-group">
            <label class="tm-label required">Team Name</label>
            <input type="text" class="tm-input" id="fTeamName" placeholder="Enter team name" maxlength="100">
        </div>

        <div class="tm-section-card">
            <div class="tm-section-header">
                <span class="tm-section-title">ADD OWNER(S)</span>
                <button class="tm-sec-edit-btn" onclick="TM.openPicker('owners')">
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M10.5 1.5L13.5 4.5L5 13H2V10L10.5 1.5Z" stroke="#64748B" stroke-width="1.4" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <div class="tm-owners-grid" id="tmOwnersGrid"></div>
        </div>

        <div class="tm-section-card">
            <div class="tm-section-header">
                <span class="tm-section-title">ADD TEAM MEMBERS</span>
                <button class="tm-sec-edit-btn" onclick="TM.openPicker('members')">
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M10.5 1.5L13.5 4.5L5 13H2V10L10.5 1.5Z" stroke="#64748B" stroke-width="1.4" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <div class="tm-members-wrap" id="tmMembersWrap"></div>
        </div>

        <div class="tm-form-actions">
            <button class="tm-btn-cancel" onclick="TM.cancelForm()">Cancel</button>
            <button class="tm-btn-save" id="btnSaveTeam" onclick="TM.saveTeam()">Add</button>
        </div>
    </div>
</div>

<div class="tm-overlay" id="tmOverlay" onclick="TM.closePicker()" style="display:none;"></div>
<div class="tm-picker" id="tmPicker" style="display:none;">
    <div class="tm-picker-header">
        <h3 class="tm-picker-title" id="tmPickerTitle">Select Owners</h3>
        <button class="tm-picker-close" onclick="TM.closePicker()">✕</button>
    </div>
    <div class="tm-picker-search">
        <input type="text" class="tm-search-input" id="tmSearchInput" placeholder="Search employees…" oninput="TM.filterEmployees(this.value)">
    </div>
    <div class="tm-picker-list" id="tmPickerList"></div>
    <div class="tm-picker-footer">
        <button class="tm-btn-cancel" onclick="TM.closePicker()">Cancel</button>
        <button class="tm-btn-save" onclick="TM.confirmPicker()">Confirm</button>
    </div>
</div>

<div class="tm-overlay" id="tmDetailOverlay" onclick="TM.closeDetail()" style="display:none;"></div>
<div class="tm-detail-modal" id="tmDetailModal" style="display:none;">
    <div class="tm-picker-header">
        <h3 class="tm-picker-title" id="dTeamName">Team Details</h3>
        <button class="tm-picker-close" onclick="TM.closeDetail()">✕</button>
    </div>
    <div class="tm-detail-body" id="tmDetailBody"></div>
    <div class="tm-picker-footer">
        <button class="tm-btn-del" onclick="TM.deleteTeam()">Delete Team</button>
        <button class="tm-btn-save" onclick="TM.editTeam()">Edit Team</button>
    </div>
</div>

<script>
const TM = (() => {
  'use strict';
  let allEmployees = [], pickerMode = '', pickerSelected = [], selectedOwners = [], selectedMembers = [];
  let editingTeamId = null, viewingTeamId = null;

  // Uses exactly the current page URL to avoid routing 404s
  const API = window.location.href.split('?')[0]; 
  const $ = id => document.getElementById(id);

  document.addEventListener('DOMContentLoaded', () => { loadTeams(); prefetchEmployees(); });

  function loadTeams() {
    fetch(`${API}?action=list`)
      .then(r => {
          if (!r.ok) throw new Error("Server error");
          return r.json();
      })
      .then(res => {
        if (res.success) renderTeamList(res.data || []);
        else showToast(res.message || 'Failed to load teams.', 'error');
      })
      .catch((e) => {
          console.error(e);
          showToast('Network/JSON error. Check server logs.', 'error');
      });
  }

  function renderTeamList(teams) {
    const grid = $('tmListGrid'), empty = $('tmEmpty'), form = $('tmFormWrap');
    form.style.display = 'none';
    if (!teams.length) { grid.style.display = 'none'; empty.style.display = 'flex'; return; }
    empty.style.display = 'none'; grid.style.display = 'grid'; grid.innerHTML = '';
    teams.forEach(t => {
      const card = document.createElement('div');
      card.className = 'tm-card';
      card.innerHTML = `<p class="tm-card-name">${esc(t.name)}</p>
        <div class="tm-card-meta"><span>👤 ${t.owner_count} Owner(s)</span><span>👥 ${t.member_count} Member(s)</span></div>`;
      card.onclick = () => openDetail(t.id);
      grid.appendChild(card);
    });
  }

  function prefetchEmployees() {
    fetch(`${API}?action=search_employees&q=`)
      .then(r => r.json())
      .then(res => { if (res.success) allEmployees = res.data || []; })
      .catch(() => {});
  }

  function openForm() {
    editingTeamId = null; selectedOwners = []; selectedMembers = [];
    $('tmEmpty').style.display = 'none'; $('tmListGrid').style.display = 'none'; $('tmFormWrap').style.display = 'block';
    $('tmFormHeading').textContent = 'NEW TEAM'; $('fTeamName').value = ''; $('btnSaveTeam').textContent = 'Add';
    renderOwnerCards(); renderMemberPills();
  }

  function cancelForm() { editingTeamId = null; $('tmFormWrap').style.display = 'none'; loadTeams(); }

  function saveTeam() {
    const name = $('fTeamName').value.trim();
    if (!name) { showToast('Team name is required.', 'error'); $('fTeamName').focus(); return; }
    const btn = $('btnSaveTeam'); btn.disabled = true; btn.textContent = editingTeamId ? 'Updating…' : 'Adding…';

    const data = new FormData();
    data.append('action', editingTeamId ? 'update' : 'add');
    if (editingTeamId) data.append('id', editingTeamId);
    data.append('name', name);
    data.append('owners', JSON.stringify(selectedOwners.map(e => e.id)));
    data.append('members', JSON.stringify(selectedMembers.map(e => e.id)));

    fetch(API, { method: 'POST', body: data })
      .then(r => r.json())
      .then(res => {
        if (res.success) { showToast(res.message, 'success'); editingTeamId = null; loadTeams(); }
        else { showToast(res.message || 'Failed.', 'error'); }
      })
      .catch(() => showToast('Network error.', 'error'))
      .finally(() => { btn.disabled = false; btn.textContent = editingTeamId ? 'Update' : 'Add'; });
  }

  function openDetail(id) {
    viewingTeamId = id;
    fetch(`${API}?action=get&id=${id}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) { showToast(res.message, 'error'); return; }
        const t = res.data;
        $('dTeamName').textContent = esc(t.name);
        $('tmDetailBody').innerHTML = `
          <div class="tm-detail-section"><div class="tm-detail-section-title">Owners (${t.owners.length})</div><div class="tm-detail-owners-list">
              ${t.owners.length ? t.owners.map(o => `<div class="tm-owner-card filled">${avatarEl(o)}<div class="tm-owner-info"><div class="tm-owner-name">${esc(o.name)}</div><div class="tm-owner-role">${esc(o.designation || '')}</div></div></div>`).join('') : '<p>No owners.</p>'}
          </div></div>
          <div class="tm-detail-section"><div class="tm-detail-section-title">Members (${t.members.length})</div><div class="tm-detail-member-list">
              ${t.members.length ? t.members.map(m => `<span class="tm-detail-member-chip">${esc(m.name)}</span>`).join('') : '<p>No members.</p>'}
          </div></div>`;
        $('tmDetailModal')._data = t;
        $('tmDetailOverlay').style.display = 'block'; $('tmDetailModal').style.display = 'flex';
      });
  }

  function closeDetail() { $('tmDetailOverlay').style.display = 'none'; $('tmDetailModal').style.display = 'none'; viewingTeamId = null; }

  function editTeam() {
    const data = $('tmDetailModal')._data; if (!data) return;
    closeDetail(); editingTeamId = data.id;
    selectedOwners = [...data.owners]; selectedMembers = [...data.members];
    $('tmEmpty').style.display = 'none'; $('tmListGrid').style.display = 'none'; $('tmFormWrap').style.display = 'block';
    $('tmFormHeading').textContent = 'EDIT TEAM'; $('fTeamName').value = data.name; $('btnSaveTeam').textContent = 'Update';
    renderOwnerCards(); renderMemberPills();
  }


  function deleteTeam() {
    if (!viewingTeamId) return;

    // 1. Save the ID locally because closeDetail() will clear viewingTeamId
    const targetId = viewingTeamId;

    // 2. Hide the team modal immediately upon clicking "Delete Team"
    closeDetail();

    // 3. Show the confirmation popup
    Swal.fire({
        title: 'Are you sure?',
        text: "You are about to delete this team. This cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#94A3B8',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData(); 
            fd.append('action', 'delete'); 
            fd.append('id', targetId); // Use the saved ID here
            
            fetch(API, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) { 
                        showToast('Deleted.', 'success'); 
                        loadTeams(); 
                    } else {
                        showToast(res.message, 'error');
                    }
                })
                .catch(() => showToast('Network error deleting team.', 'error'));
        }
    });
  }


  function renderOwnerCards() {
    const grid = $('tmOwnersGrid'); grid.innerHTML = '';
    if (!selectedOwners.length) { grid.innerHTML = '<p style="color:#94a3b8;font-size:13px">Click + to add</p>'; return; }
    selectedOwners.forEach(o => {
      const card = document.createElement('div'); card.className = 'tm-owner-card filled';
      card.innerHTML = `${avatarEl(o)}<div class="tm-owner-info"><div class="tm-owner-name">${esc(o.name)}</div><div class="tm-owner-role">${esc(o.designation || '')}</div></div><button class="tm-owner-remove" title="Remove">✕</button>`;
      card.querySelector('.tm-owner-remove').onclick = () => { selectedOwners = selectedOwners.filter(x => x.id != o.id); renderOwnerCards(); };
      grid.appendChild(card);
    });
  }

  function renderMemberPills() {
    const wrap = $('tmMembersWrap'); wrap.innerHTML = '';
    if (!selectedMembers.length) { wrap.innerHTML = '<p style="color:#94a3b8;font-size:13px">Click + to add</p>'; return; }
    selectedMembers.forEach(m => {
      const pill = document.createElement('div'); pill.className = 'tm-member-pill filled';
      pill.innerHTML = `${esc(m.name)}<button class="tm-member-pill-del" title="Remove">✕</button>`;
      pill.querySelector('.tm-member-pill-del').onclick = () => { selectedMembers = selectedMembers.filter(x => x.id != m.id); renderMemberPills(); };
      wrap.appendChild(pill);
    });
  }

  function openPicker(mode) {
    pickerMode = mode; pickerSelected = mode === 'owners' ? selectedOwners.map(e => e.id) : selectedMembers.map(e => e.id);
    $('tmPickerTitle').textContent = mode === 'owners' ? 'Select Owners' : 'Select Members';
    $('tmSearchInput').value = ''; renderPickerList(allEmployees);
    $('tmOverlay').style.display = 'block'; $('tmPicker').style.display = 'flex'; $('tmSearchInput').focus();
  }

  function closePicker() { $('tmOverlay').style.display = 'none'; $('tmPicker').style.display = 'none'; }
  function filterEmployees(q) { renderPickerList(allEmployees.filter(e => e.name.toLowerCase().includes(q.toLowerCase()))); }

  function renderPickerList(list) {
    const ul = $('tmPickerList'); ul.innerHTML = '';
    if (!list.length) { ul.innerHTML = '<div class="tm-picker-empty">No employees found.</div>'; return; }
    list.forEach(e => {
      const row = document.createElement('div'); row.className = `tm-picker-emp${pickerSelected.includes(e.id) ? ' selected' : ''}`;
      row.innerHTML = `${avatarEl(e, 'tm-picker-emp-avatar')}<div class="tm-picker-emp-info"><div class="tm-picker-emp-name">${esc(e.name)}</div><div class="tm-picker-emp-role">${esc(e.designation || '')}</div></div><div class="tm-picker-emp-check"></div>`;
      row.onclick = () => {
        const idx = pickerSelected.indexOf(e.id); idx === -1 ? pickerSelected.push(e.id) : pickerSelected.splice(idx, 1);
        row.classList.toggle('selected', pickerSelected.includes(e.id));
      };
      ul.appendChild(row);
    });
  }

  function confirmPicker() {
    const chosen = allEmployees.filter(e => pickerSelected.includes(e.id));
    if (pickerMode === 'owners') { selectedOwners = chosen; renderOwnerCards(); } else { selectedMembers = chosen; renderMemberPills(); }
    closePicker();
  }

  function avatarEl(emp, cls = 'tm-owner-avatar') {
    // Generate initials safely since profile_photo doesn't exist
    const initials = (emp.name || '?').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
    return `<div class="${cls}">${initials}</div>`;
  }
  
  function esc(str) { return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

  let toastTimer;
  function showToast(msg, type = '') {
    let t = document.querySelector('.tm-toast');
    if (!t) { t = document.createElement('div'); t.className = 'tm-toast'; document.body.appendChild(t); }
    t.className = `tm-toast ${type}`; t.textContent = msg; clearTimeout(toastTimer);
    requestAnimationFrame(() => { t.classList.add('show'); toastTimer = setTimeout(() => t.classList.remove('show'), 3200); });
  }

  return { openForm, cancelForm, saveTeam, openPicker, closePicker, confirmPicker, filterEmployees, openDetail, closeDetail, editTeam, deleteTeam };
})();
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>