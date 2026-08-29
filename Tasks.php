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

$conn = $conn ?? $db ?? null; 

// ========================================================================
// API / AJAX HANDLER
// ========================================================================
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action) {
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection missing.']);
        exit();
    }

    $admin_emp_id = (int)($_SESSION['emp_id'] ?? 0);
    $admin_code = 'ADMIN';
    if ($admin_emp_id > 0) {
        $resAdmin = mysqli_query($conn, "SELECT employee_code FROM employees WHERE id = $admin_emp_id");
        if ($resAdmin && $rowAdmin = mysqli_fetch_assoc($resAdmin)) {
            $admin_code = $rowAdmin['employee_code'];
        }
    }

    try {
        switch ($action) {
            case 'list':
                $sql = "SELECT t.*, 
                               e.employee_name AS assigned_to_name, 
                               e.designation AS assigned_to_role
                        FROM tasks t
                        LEFT JOIN employees e ON t.employee_code = e.employee_code
                        ORDER BY t.created_at DESC";
                
                $result = mysqli_query($conn, $sql);
                
                // If the query fails, explicitly return the SQL error so you know why it's empty
                if (!$result) {
                    echo json_encode(['success' => false, 'message' => 'SQL Error: ' . mysqli_error($conn)]);
                    exit();
                }

                $tasks = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $tasks[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $tasks]);
                break;

            case 'get':
                $id = (int)($_GET['id'] ?? 0);
                if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); break; }

                $sql = "SELECT t.*, 
                               e.employee_name AS assigned_to_name, 
                               e.designation AS assigned_to_role
                        FROM tasks t
                        LEFT JOIN employees e ON t.employee_code = e.employee_code
                        WHERE t.id = $id";
                        
                $result = mysqli_query($conn, $sql);
                $task = mysqli_fetch_assoc($result);
                
                if (!$task) { 
                    echo json_encode(['success' => false, 'message' => 'Not found']); 
                } else {
                    echo json_encode(['success' => true, 'data' => $task]);
                }
                break;

            case 'add':
                $title       = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $priority    = trim($_POST['priority'] ?? 'Medium');
                $status      = trim($_POST['status'] ?? 'Pending');
                $due_date    = trim($_POST['due_date'] ?? '');
                $emp_code    = trim($_POST['employee_code'] ?? '');

                if (!$title || !$emp_code) {
                    echo json_encode(['success' => false, 'message' => 'Task title and Assignee are required.']);
                    break;
                }

                $title_esc = mysqli_real_escape_string($conn, $title);
                $desc_esc  = mysqli_real_escape_string($conn, $description);
                $prior_esc = mysqli_real_escape_string($conn, $priority);
                $stat_esc  = mysqli_real_escape_string($conn, $status);
                $emp_esc   = mysqli_real_escape_string($conn, $emp_code);
                
                $due_sql = $due_date ? "'" . mysqli_real_escape_string($conn, $due_date) . "'" : "NULL";

                $ins_sql = "INSERT INTO tasks (employee_code, assigned_by, title, description, priority, status, due_date) 
                            VALUES ('$emp_esc', '$admin_code', '$title_esc', '$desc_esc', '$prior_esc', '$stat_esc', $due_sql)";
                
                if (!mysqli_query($conn, $ins_sql)) { throw new Exception(mysqli_error($conn)); }
                
                echo json_encode(['success' => true, 'message' => 'Task created successfully.', 'id' => mysqli_insert_id($conn)]);
                break;

            case 'update':
                $id          = (int)($_POST['id'] ?? 0);
                $title       = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $priority    = trim($_POST['priority'] ?? 'Medium');
                $status      = trim($_POST['status'] ?? 'Pending');
                $due_date    = trim($_POST['due_date'] ?? '');
                $emp_code    = trim($_POST['employee_code'] ?? '');

                if (!$id || !$title || !$emp_code) {
                    echo json_encode(['success' => false, 'message' => 'Task ID, title, and assignee are required.']);
                    break;
                }

                $title_esc = mysqli_real_escape_string($conn, $title);
                $desc_esc  = mysqli_real_escape_string($conn, $description);
                $prior_esc = mysqli_real_escape_string($conn, $priority);
                $stat_esc  = mysqli_real_escape_string($conn, $status);
                $emp_esc   = mysqli_real_escape_string($conn, $emp_code);
                $due_sql   = $due_date ? "'" . mysqli_real_escape_string($conn, $due_date) . "'" : "NULL";

                $upd_sql = "UPDATE tasks SET 
                            title = '$title_esc', description = '$desc_esc', priority = '$prior_esc', 
                            status = '$stat_esc', due_date = $due_sql, employee_code = '$emp_esc', updated_at = NOW() 
                            WHERE id = $id";

                if (!mysqli_query($conn, $upd_sql)) { throw new Exception(mysqli_error($conn)); }

                echo json_encode(['success' => true, 'message' => 'Task updated successfully.']);
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); break; }
                mysqli_query($conn, "DELETE FROM tasks WHERE id = $id");
                echo json_encode(['success' => true, 'message' => 'Task deleted successfully.']);
                break;

            case 'search_employees':
                $q = trim($_GET['q'] ?? '');
                $q_esc = mysqli_real_escape_string($conn, $q);
                $sql = "SELECT employee_code AS code, employee_name AS name, designation
                        FROM employees WHERE status = 'Active' 
                        AND (employee_name LIKE '%$q_esc%' OR employee_code LIKE '%$q_esc%')
                        ORDER BY employee_name LIMIT 50";
                $res = mysqli_query($conn, $sql);
                $data = [];
                if ($res) { while($row = mysqli_fetch_assoc($res)) { $data[] = $row; } }
                echo json_encode(['success' => true, 'data' => $data]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        error_log('Tasks API Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
    }
    exit();
}

// ========================================================================
// REPORT DATA AGGREGATION
// ========================================================================
$stats = [
    'total' => 0, 'completed' => 0, 'pending' => 0, 'in_progress' => 0, 'on_hold' => 0,
    'high' => 0, 'medium' => 0, 'low' => 0, 'overdue_tasks' => []
];

$stat_sql = "SELECT t.*, e.employee_name 
             FROM tasks t 
             LEFT JOIN employees e ON t.employee_code COLLATE utf8mb4_unicode_ci = e.employee_code COLLATE utf8mb4_unicode_ci";
$stat_res = mysqli_query($conn, $stat_sql);
$today_date = date('Y-m-d');

if ($stat_res) {
    while ($row = mysqli_fetch_assoc($stat_res)) {
        $stats['total']++;
        if ($row['status'] === 'Completed') $stats['completed']++;
        elseif ($row['status'] === 'Pending') $stats['pending']++;
        elseif ($row['status'] === 'In Progress') $stats['in_progress']++;
        elseif ($row['status'] === 'On Hold') $stats['on_hold']++;

        if ($row['priority'] === 'High') $stats['high']++;
        elseif ($row['priority'] === 'Medium') $stats['medium']++;
        elseif ($row['priority'] === 'Low') $stats['low']++;

        if ($row['status'] !== 'Completed' && !empty($row['due_date']) && $row['due_date'] < $today_date) {
            $stats['overdue_tasks'][] = $row;
        }
    }
}

$tot = $stats['total'] > 0 ? $stats['total'] : 1; 
$pct_comp = round(($stats['completed'] / $tot) * 100);
$pct_prog = round(($stats['in_progress'] / $tot) * 100);
$pct_pend = round(($stats['pending'] / $tot) * 100);
$pct_hold = round(($stats['on_hold'] / $tot) * 100);

$pct_high = round(($stats['high'] / $tot) * 100);
$pct_med  = round(($stats['medium'] / $tot) * 100);
$pct_low  = round(($stats['low'] / $tot) * 100);


// ========================================================================
// PAGE RENDERER
// ========================================================================
$page_title = 'Manage Tasks';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* Existing UI Styles */
.cfg-tabs { display: flex; align-items: center; gap: 0; border-bottom: 1px solid #E5E7EB; background: #fff; padding: 0; margin-bottom: 0; overflow-x: auto; scrollbar-width: none; }
.cfg-tabs::-webkit-scrollbar { display: none; }
.cfg-tab { padding: 14px 20px; font-size: 13.5px; font-weight: 500; color: #6B7280; cursor: pointer; border: none; background: transparent; border-bottom: 2.5px solid transparent; white-space: nowrap; transition: color .15s, border-color .15s; text-decoration: none; display: block; margin-bottom: -1px; }
.cfg-tab:hover  { color: #111827; }
.cfg-tab.active { color: #2563EB; border-bottom-color: #2563EB; font-weight: 600; }

.tsk-wrapper { background: #fff; min-height: calc(100vh - 120px); padding: 0 0 40px; font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; }
.tsk-topbar { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: #fff; border-bottom: 1px solid #E2E8F0; }
.tsk-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13.5px; }
.tsk-bc-parent  { color: #64748B; cursor: pointer; }
.tsk-bc-current { font-weight: 600; color: #1e293b; }
.tsk-btn-create { display: flex; align-items: center; gap: 6px; background: #2563EB; color: #fff; border: none; border-radius: 6px; padding: 9px 18px; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: background .15s; }
.tsk-btn-create:hover  { background: #1D4ED8; }

.tsk-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 90px 20px; background: #fff; }
.tsk-empty-text { font-size: 15px; color: #64748B; margin-top: 15px; }

.tsk-list-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; padding: 24px; }
.tsk-card { background: #fff; border: 1px solid #E2E8F0; border-radius: 10px; padding: 20px; cursor: pointer; transition: box-shadow .18s, border-color .18s; position: relative; overflow: hidden;}
.tsk-card:hover { box-shadow: 0 4px 16px rgba(37,99,235,.1); border-color: #93C5FD; }
.tsk-card-title { font-size: 15px; font-weight: 700; color: #0F172A; margin: 0 0 10px; padding-right: 70px;}
.tsk-badge { position: absolute; top: 18px; right: 20px; font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; }
.badge-high { background: #FEE2E2; color: #EF4444; }
.badge-medium { background: #FEF3C7; color: #D97706; }
.badge-low { background: #D1FAE5; color: #10B981; }

.tsk-card-meta { display: flex; flex-direction: column; gap: 8px; font-size: 12.5px; color: #64748B; margin-top: 15px; padding-top: 15px; border-top: 1px solid #F1F5F9;}
.tsk-card-meta span { display: flex; align-items: center; gap: 6px; }
.tsk-status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.status-Pending { background: #F59E0B; }
.status-InProgress { background: #3B82F6; }
.status-Completed { background: #10B981; }
.status-OnHold { background: #6B7280; }

.tsk-form-wrap { background: #fff; padding: 28px 28px 16px; }
.tsk-form-heading { font-size: 15px; font-weight: 700; color: #0F172A; margin: 0 0 24px; }
.tsk-field-row { display: flex; gap: 24px; margin-bottom: 20px; flex-wrap: wrap;}
.tsk-field-group { flex: 1; min-width: 250px;}
.tsk-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }
.tsk-input, .tsk-select, .tsk-textarea { width: 100%; border: 1.5px solid #CBD5E1; border-radius: 6px; padding: 10px 14px; font-size: 14px; color: #1e293b; outline: none; }
.tsk-input:focus, .tsk-select:focus, .tsk-textarea:focus { border-color: #2563EB; }

.tsk-section-card { border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px 20px 20px; margin-bottom: 20px; }
.tsk-assignee-card { border: 1.5px dashed #CBD5E1; border-radius: 8px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; max-width: 300px; cursor: pointer;}
.tsk-assignee-card.filled { border-color: #93C5FD; background: #F0F9FF; border-style: solid;}
.tsk-avatar { width: 38px; height: 38px; border-radius: 50%; background: #DBEAFE; color: #2563EB; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.tsk-owner-info { flex: 1; min-width: 0; }
.tsk-owner-name { font-size: 13.5px; font-weight: 600; color: #0F172A; }
.tsk-owner-role { font-size: 12px; color: #64748B; }

.tsk-form-actions { display: flex; justify-content: flex-end; gap: 12px; padding: 20px 0 12px; border-top: 1px solid #F1F5F9; margin-top: 12px; }
.tsk-btn-cancel { padding: 9px 24px; border: 1.5px solid #CBD5E1; background: #fff; border-radius: 6px; font-size: 13.5px; font-weight: 600; cursor: pointer; }
.tsk-btn-save { padding: 9px 32px; border: none; background: #2563EB; color: #fff; border-radius: 6px; font-size: 13.5px; font-weight: 600; cursor: pointer; }

/* Modals */
.tsk-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 7000; }
.tsk-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 12px; width: 480px; max-width: 96vw; max-height: 85vh; display: flex; flex-direction: column; z-index: 7001; }
.tsk-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #E2E8F0; }
.tsk-modal-title { font-size: 15px; font-weight: 700; margin: 0; }
.tsk-modal-close { background: none; border: none; cursor: pointer; font-size: 18px; color: #94A3B8; }
.tsk-picker-search { padding: 14px 22px 10px; }
.tsk-picker-list { flex: 1; overflow-y: auto; padding: 4px 12px 8px; }
.tsk-picker-emp { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 8px; cursor: pointer; }
.tsk-picker-emp:hover { background: #F8FAFC; }
.tsk-detail-body { padding: 24px; overflow-y: auto;}
.tsk-detail-label { font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; margin-bottom: 4px; display:block;}
.tsk-detail-value { font-size: 14px; color: #1E293B; margin-bottom: 18px;}
.tsk-detail-footer { padding: 14px 22px; border-top: 1px solid #E2E8F0; display: flex; justify-content: space-between; background:#F8FAFC; border-radius: 0 0 12px 12px;}
.tsk-btn-del { color: #EF4444; background: none; border: none; font-weight: 600; cursor: pointer;}

/* Reports UI */
#viewReports { background: #f8fafc; padding: 24px; min-height: 100%; display: none; }
.rep-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
.rep-card { background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.rep-title { font-size: 13px; font-weight: 600; color: #64748B; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;}
.rep-val { font-size: 28px; font-weight: 700; color: #0F172A; }

.rep-breakdown-wrap { display: flex; gap: 24px; margin-bottom: 24px; flex-wrap: wrap;}
.rep-breakdown-card { flex: 1; min-width: 300px; background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px;}
.rep-bar-row { margin-bottom: 16px; }
.rep-bar-labels { display: flex; justify-content: space-between; font-size: 13px; font-weight: 500; color: #333; margin-bottom: 6px; }
.rep-bar-bg { background: #E2E8F0; border-radius: 6px; height: 8px; overflow: hidden; width: 100%; }
.rep-bar-fill { height: 100%; border-radius: 6px; }

.rep-overdue-list { border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; }
.rep-overdue-item { display: flex; justify-content: space-between; padding: 14px 20px; border-bottom: 1px solid #E2E8F0; background: #fff; align-items: center;}
.rep-overdue-item:last-child { border-bottom: none; }
.rep-od-title { font-size: 14px; font-weight: 600; color: #0F172A; margin-bottom: 4px; }
.rep-od-meta { font-size: 12px; color: #64748B; }

@media (max-width: 768px) { .rep-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .rep-grid { grid-template-columns: 1fr; } }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
    <h1 class="page-title">Tasks Management</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden;">
    
    <!-- TABS -->
    <div class="cfg-tabs">
        <a href="javascript:void(0)" class="cfg-tab active" id="tabAllTasks" onclick="TSK.switchTab('tasks')">All Tasks</a>
        <a href="javascript:void(0)" class="cfg-tab" id="tabReports" onclick="TSK.switchTab('reports')">Task Reports</a>
    </div>

    <!-- VIEW 1: TASK LIST -->
    <div class="tsk-wrapper" id="viewTasks">
        <div class="tsk-topbar">
            <div class="tsk-breadcrumb">
                <span class="tsk-bc-parent">Dashboard</span>
                <span class="tsk-bc-arrow">›</span>
                <span class="tsk-bc-current">Manage Tasks</span>
            </div>
            <button class="tsk-btn-create" onclick="TSK.openForm()">
                <i class="fa-solid fa-plus"></i> Create New Task
            </button>
        </div>

        <div class="tsk-empty" id="tskEmpty" style="display:none;">
            <i class="fa-solid fa-clipboard-check text-gray-300" style="font-size: 60px;"></i>
            <p class="tsk-empty-text">No tasks have been created yet.</p>
            <button class="tsk-btn-create" style="margin-top:20px;" onclick="TSK.openForm()">Create First Task</button>
        </div>

        <div class="tsk-list-grid" id="tskListGrid" style="display:none;"></div>

        <!-- Task Form -->
        <div class="tsk-form-wrap" id="tskFormWrap" style="display:none;">
            <h2 class="tsk-form-heading" id="tskFormHeading">NEW TASK</h2>
            
            <div class="tsk-field-row">
                <div class="tsk-field-group">
                    <label class="tsk-label required">Task Title</label>
                    <input type="text" class="tsk-input" id="fTitle" placeholder="E.g., Review Monthly Report">
                </div>
            </div>

            <div class="tsk-field-row">
                <div class="tsk-field-group">
                    <label class="tsk-label">Description</label>
                    <textarea class="tsk-textarea" id="fDesc" placeholder="Detailed instructions..."></textarea>
                </div>
            </div>

            <div class="tsk-field-row">
                <div class="tsk-field-group">
                    <label class="tsk-label required">Priority</label>
                    <select class="tsk-select" id="fPriority">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="tsk-field-group">
                    <label class="tsk-label required">Status</label>
                    <select class="tsk-select" id="fStatus">
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="On Hold">On Hold</option>
                    </select>
                </div>
                <div class="tsk-field-group">
                    <label class="tsk-label">Due Date</label>
                    <input type="date" class="tsk-input" id="fDueDate">
                </div>
            </div>

            <div class="tsk-section-card">
                <label class="tsk-label required" style="margin-bottom:12px;">Assign To</label>
                <div id="assigneeWrapper"></div>
            </div>

            <div class="tsk-form-actions">
                <button class="tsk-btn-cancel" onclick="TSK.cancelForm()">Cancel</button>
                <button class="tsk-btn-save" id="btnSaveTask" onclick="TSK.saveTask()">Save Task</button>
            </div>
        </div>
    </div>

    <!-- VIEW 2: TASK REPORTS -->
    <div id="viewReports">
        <div class="rep-grid">
            <div class="rep-card border-t-4 border-blue-500">
                <div class="rep-title"><i class="fa-solid fa-list-check mr-2 text-blue-500"></i> Total Tasks</div>
                <div class="rep-val"><?= $stats['total'] ?></div>
            </div>
            <div class="rep-card border-t-4 border-green-500">
                <div class="rep-title"><i class="fa-solid fa-check-circle mr-2 text-green-500"></i> Completed</div>
                <div class="rep-val"><?= $stats['completed'] ?></div>
            </div>
            <div class="rep-card border-t-4 border-orange-400">
                <div class="rep-title"><i class="fa-solid fa-spinner mr-2 text-orange-400"></i> In Progress</div>
                <div class="rep-val"><?= $stats['in_progress'] ?></div>
            </div>
            <div class="rep-card border-t-4 border-red-500">
                <div class="rep-title"><i class="fa-solid fa-clock-rotate-left mr-2 text-red-500"></i> Overdue</div>
                <div class="rep-val"><?= count($stats['overdue_tasks']) ?></div>
            </div>
        </div>

        <div class="rep-breakdown-wrap">
            <div class="rep-breakdown-card">
                <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 20px; color: #0F172A;">Task Status Breakdown</h3>
                
                <div class="rep-bar-row">
                    <div class="rep-bar-labels"><span>Completed</span><span><?= $pct_comp ?>% (<?= $stats['completed'] ?>)</span></div>
                    <div class="rep-bar-bg"><div class="rep-bar-fill bg-green-500" style="width: <?= $pct_comp ?>%"></div></div>
                </div>
                
                <div class="rep-bar-row">
                    <div class="rep-bar-labels"><span>In Progress</span><span><?= $pct_prog ?>% (<?= $stats['in_progress'] ?>)</span></div>
                    <div class="rep-bar-bg"><div class="rep-bar-fill bg-blue-500" style="width: <?= $pct_prog ?>%"></div></div>
                </div>

                <div class="rep-bar-row">
                    <div class="rep-bar-labels"><span>Pending</span><span><?= $pct_pend ?>% (<?= $stats['pending'] ?>)</span></div>
                    <div class="rep-bar-bg"><div class="rep-bar-fill bg-yellow-500" style="width: <?= $pct_pend ?>%"></div></div>
                </div>

                <div class="rep-bar-row">
                    <div class="rep-bar-labels"><span>On Hold</span><span><?= $pct_hold ?>% (<?= $stats['on_hold'] ?>)</span></div>
                    <div class="rep-bar-bg"><div class="rep-bar-fill bg-gray-500" style="width: <?= $pct_hold ?>%"></div></div>
                </div>
            </div>

            <div class="rep-breakdown-card">
                <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 20px; color: #0F172A;">Task Priority Breakdown</h3>
                
                <div class="rep-bar-row">
                    <div class="rep-bar-labels"><span>High Priority</span><span><?= $pct_high ?>% (<?= $stats['high'] ?>)</span></div>
                    <div class="rep-bar-bg"><div class="rep-bar-fill bg-red-500" style="width: <?= $pct_high ?>%"></div></div>
                </div>
                
                <div class="rep-bar-row">
                    <div class="rep-bar-labels"><span>Medium Priority</span><span><?= $pct_med ?>% (<?= $stats['medium'] ?>)</span></div>
                    <div class="rep-bar-bg"><div class="rep-bar-fill bg-yellow-400" style="width: <?= $pct_med ?>%"></div></div>
                </div>

                <div class="rep-bar-row">
                    <div class="rep-bar-labels"><span>Low Priority</span><span><?= $pct_low ?>% (<?= $stats['low'] ?>)</span></div>
                    <div class="rep-bar-bg"><div class="rep-bar-fill bg-green-400" style="width: <?= $pct_low ?>%"></div></div>
                </div>
            </div>
        </div>

        <?php if (count($stats['overdue_tasks']) > 0): ?>
        <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 12px; color: #EF4444;"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Overdue Tasks</h3>
        <div class="rep-overdue-list">
            <?php foreach($stats['overdue_tasks'] as $od): 
                $od_date = date('d M, Y', strtotime($od['due_date']));
            ?>
            <div class="rep-overdue-item">
                <div>
                    <div class="rep-od-title"><?= htmlspecialchars($od['title']) ?></div>
                    <div class="rep-od-meta">
                        <i class="fa-solid fa-user mr-1"></i> <?= htmlspecialchars($od['employee_name'] ?: $od['employee_code']) ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 12px; font-weight:600; color: #EF4444; margin-bottom:4px;">Due: <?= $od_date ?></div>
                    <span class="tsk-badge badge-high" style="position:static; padding:3px 6px;">Overdue</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="rep-card" style="text-align: center; padding: 30px; color: #10B981; font-weight: 600;">
            <i class="fa-solid fa-face-smile text-3xl mb-2"></i><br>
            Great job! There are no overdue tasks right now.
        </div>
        <?php endif; ?>

    </div>

    <!-- Modals -->
    <div class="tsk-overlay" id="tskOverlay" onclick="TSK.closePicker()" style="display:none;"></div>
    <div class="tsk-modal" id="tskPicker" style="display:none;">
        <div class="tsk-modal-header">
            <h3 class="tsk-modal-title">Select Assignee</h3>
            <button class="tsk-modal-close" onclick="TSK.closePicker()">✕</button>
        </div>
        <div class="tsk-picker-search">
            <input type="text" class="tsk-input" id="tskSearchInput" placeholder="Search by name or code…" oninput="TSK.filterEmployees(this.value)">
        </div>
        <div class="tsk-picker-list" id="tskPickerList"></div>
    </div>

    <div class="tsk-overlay" id="detailOverlay" onclick="TSK.closeDetail()" style="display:none;"></div>
    <div class="tsk-modal" id="detailModal" style="display:none;">
        <div class="tsk-modal-header">
            <h3 class="tsk-modal-title">Task Details</h3>
            <button class="tsk-modal-close" onclick="TSK.closeDetail()">✕</button>
        </div>
        <div class="tsk-detail-body" id="detailBody"></div>
        <div class="tsk-detail-footer">
            <button class="tsk-btn-del" onclick="TSK.deleteTask()"><i class="fa-regular fa-trash-can"></i> Delete Task</button>
            <button class="tsk-btn-save" onclick="TSK.editTask()"><i class="fa-solid fa-pen"></i> Edit Task</button>
        </div>
    </div>
</div>

<script>
const TSK = (() => {
    'use strict';
    let allEmployees = [];
    let selectedAssignee = null;
    let editingTaskId = null;
    let viewingTaskId = null;

    const API = window.location.href.split('?')[0]; 
    const $ = id => document.getElementById(id);

    document.addEventListener('DOMContentLoaded', () => { 
        loadTasks(); 
        prefetchEmployees(); 
    });

    function switchTab(tab) {
        if (tab === 'tasks') {
            $('tabAllTasks').classList.add('active');
            $('tabReports').classList.remove('active');
            $('viewTasks').style.display = 'block';
            $('viewReports').style.display = 'none';
            loadTasks(); 
        } else {
            $('tabReports').classList.add('active');
            $('tabAllTasks').classList.remove('active');
            $('viewReports').style.display = 'block';
            $('viewTasks').style.display = 'none';
        }
    }

    function loadTasks() {
        // Cache Buster added `&_t=Date.now()` to ensure the browser doesn't load a cached empty response
        fetch(`${API}?action=list&_t=${Date.now()}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) renderTaskList(res.data || []);
                else Swal.fire('Database Error', res.message, 'error'); // This will now show the exact SQL error if it fails
            })
            .catch(e => {
                console.error(e);
                Swal.fire('Network Error', 'Failed to connect to server', 'error');
            });
    }

    function renderTaskList(tasks) {
        const grid = $('tskListGrid'), empty = $('tskEmpty'), form = $('tskFormWrap');
        form.style.display = 'none';
        
        if (!tasks || tasks.length === 0) { 
            grid.style.display = 'none'; 
            empty.style.display = 'flex'; 
            return; 
        }
        
        empty.style.display = 'none'; 
        grid.style.display = 'grid'; 
        grid.innerHTML = '';
        
        tasks.forEach(t => {
            const card = document.createElement('div');
            card.className = 'tsk-card';
            
            const badgeClass = t.priority === 'High' ? 'badge-high' : (t.priority === 'Medium' ? 'badge-medium' : 'badge-low');
            const statusClass = 'status-' + t.status.replace(/\s+/g, '');
            const dateFmt = t.due_date ? new Date(t.due_date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'}) : 'No Due Date';
            
            card.innerHTML = `
                <div class="tsk-badge ${badgeClass}">${t.priority}</div>
                <h3 class="tsk-card-title">${esc(t.title)}</h3>
                <div class="tsk-card-meta">
                    <span>
                        <div class="tsk-avatar" style="width:24px;height:24px;font-size:10px;">${getInitials(t.assigned_to_name)}</div>
                        <strong style="color:#333">${esc(t.assigned_to_name || t.employee_code)}</strong>
                    </span>
                    <span><span class="tsk-status-dot ${statusClass}"></span> ${esc(t.status)}</span>
                    <span><i class="fa-regular fa-calendar" style="color:#94A3B8"></i> Due: ${dateFmt}</span>
                </div>
            `;
            card.onclick = () => openDetail(t.id);
            grid.appendChild(card);
        });
    }

    function prefetchEmployees() {
        fetch(`${API}?action=search_employees&q=&_t=${Date.now()}`)
            .then(r => r.json())
            .then(res => { if (res.success) allEmployees = res.data || []; })
            .catch(() => {});
    }

    function renderAssigneeBox() {
        const wrap = $('assigneeWrapper');
        if (!selectedAssignee) {
            wrap.innerHTML = `
                <div class="tsk-assignee-card" onclick="TSK.openPicker()">
                    <div class="tsk-avatar" style="background:#F1F5F9;color:#94A3B8"><i class="fa-solid fa-user-plus"></i></div>
                    <div class="tsk-owner-info"><div class="tsk-owner-name" style="color:#64748B">Click to assign employee</div></div>
                </div>`;
        } else {
            wrap.innerHTML = `
                <div class="tsk-assignee-card filled" onclick="TSK.openPicker()">
                    <div class="tsk-avatar">${getInitials(selectedAssignee.name)}</div>
                    <div class="tsk-owner-info">
                        <div class="tsk-owner-name">${esc(selectedAssignee.name)}</div>
                        <div class="tsk-owner-role">${esc(selectedAssignee.designation || selectedAssignee.code)}</div>
                    </div>
                    <div style="color:#94A3B8; font-size:12px;"><i class="fa-solid fa-pen"></i> Change</div>
                </div>`;
        }
    }

    function openPicker() {
        $('tskSearchInput').value = '';
        renderPickerList(allEmployees);
        $('tskOverlay').style.display = 'block'; 
        $('tskPicker').style.display = 'flex';
        $('tskSearchInput').focus();
    }

    function closePicker() {
        $('tskOverlay').style.display = 'none'; $('tskPicker').style.display = 'none';
    }

    function filterEmployees(q) {
        const term = q.toLowerCase();
        renderPickerList(allEmployees.filter(e => e.name.toLowerCase().includes(term) || e.code.toLowerCase().includes(term)));
    }

    function renderPickerList(list) {
        const ul = $('tskPickerList'); ul.innerHTML = '';
        if (!list.length) { ul.innerHTML = '<div style="text-align:center;padding:20px;color:#94A3B8;">No employees found.</div>'; return; }
        
        list.forEach(e => {
            const isSelected = selectedAssignee && selectedAssignee.code === e.code;
            const row = document.createElement('div');
            row.className = `tsk-picker-emp ${isSelected ? 'selected' : ''}`;
            row.innerHTML = `<div class="tsk-avatar">${getInitials(e.name)}</div>
                             <div class="tsk-owner-info"><div class="tsk-owner-name">${esc(e.name)}</div><div class="tsk-owner-role">${esc(e.code)}</div></div>`;
            row.onclick = () => { selectedAssignee = e; renderAssigneeBox(); closePicker(); };
            ul.appendChild(row);
        });
    }

    function openForm() {
        editingTaskId = null; selectedAssignee = null;
        $('tskEmpty').style.display = 'none'; $('tskListGrid').style.display = 'none'; $('tskFormWrap').style.display = 'block';
        $('tskFormHeading').textContent = 'CREATE NEW TASK'; $('btnSaveTask').textContent = 'Save Task';
        $('fTitle').value = ''; $('fDesc').value = ''; $('fPriority').value = 'Medium'; $('fStatus').value = 'Pending'; $('fDueDate').value = '';
        renderAssigneeBox();
    }

    function cancelForm() {
        editingTaskId = null; $('tskFormWrap').style.display = 'none'; loadTasks();
    }

    function saveTask() {
        const title = $('fTitle').value.trim();
        if (!title) { Swal.fire('Wait', 'Task Title is required', 'warning'); return; }
        if (!selectedAssignee) { Swal.fire('Wait', 'Please assign an employee', 'warning'); return; }

        const btn = $('btnSaveTask'); btn.disabled = true; btn.textContent = 'Saving...';

        const data = new FormData();
        data.append('action', editingTaskId ? 'update' : 'add');
        if (editingTaskId) data.append('id', editingTaskId);
        data.append('title', title); data.append('description', $('fDesc').value);
        data.append('priority', $('fPriority').value); data.append('status', $('fStatus').value);
        data.append('due_date', $('fDueDate').value); data.append('employee_code', selectedAssignee.code);

        fetch(API, { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) { 
                    Swal.fire({ title: 'Success!', text: res.message, icon: 'success', timer: 1500, showConfirmButton: false })
                        .then(() => window.location.reload()); 
                } else { Swal.fire('Error', res.message, 'error'); }
            })
            .catch(() => Swal.fire('Error', 'Network request failed', 'error'))
            .finally(() => { btn.disabled = false; btn.textContent = 'Save Task'; });
    }

    function openDetail(id) {
        viewingTaskId = id;
        fetch(`${API}?action=get&id=${id}&_t=${Date.now()}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) { Swal.fire('Error', res.message, 'error'); return; }
                const t = res.data;
                $('detailModal')._data = t;
                const dateFmt = t.due_date ? new Date(t.due_date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'}) : 'Not Set';
                const statusClass = 'status-' + t.status.replace(/\s+/g, '');
                
                $('detailBody').innerHTML = `
                    <div style="display:flex; justify-content:space-between; margin-bottom: 20px;">
                        <div><span class="tsk-detail-label">Task Title</span><div style="font-size:18px; font-weight:700; color:#0F172A">${esc(t.title)}</div></div>
                        <div class="tsk-badge ${t.priority === 'High' ? 'badge-high' : (t.priority === 'Medium' ? 'badge-medium' : 'badge-low')}" style="position:static;">${t.priority}</div>
                    </div>
                    <div style="display:flex; gap: 40px; margin-bottom:20px;">
                        <div><span class="tsk-detail-label">Status</span><div style="font-size:14px; font-weight:600;"><span class="tsk-status-dot ${statusClass}"></span> ${esc(t.status)}</div></div>
                        <div><span class="tsk-detail-label">Due Date</span><div style="font-size:14px; font-weight:600;">${dateFmt}</div></div>
                    </div>
                    <span class="tsk-detail-label">Assigned To</span>
                    <div class="tsk-assignee-card filled" style="margin-bottom:20px; cursor:default;">
                        <div class="tsk-avatar">${getInitials(t.assigned_to_name)}</div>
                        <div class="tsk-owner-info"><div class="tsk-owner-name">${esc(t.assigned_to_name || t.employee_code)}</div><div class="tsk-owner-role">${esc(t.assigned_to_role || 'Employee')}</div></div>
                    </div>
                    <span class="tsk-detail-label">Description</span>
                    <div class="tsk-detail-value" style="background:#F8FAFC; padding:15px; border-radius:8px; border:1px solid #E2E8F0;">
                        ${t.description ? esc(t.description).replace(/\n/g, '<br>') : '<em style="color:#94A3B8">No description provided.</em>'}
                    </div>
                `;
                $('detailOverlay').style.display = 'block'; $('detailModal').style.display = 'flex';
            });
    }

    function closeDetail() {
        $('detailOverlay').style.display = 'none'; $('detailModal').style.display = 'none'; viewingTaskId = null;
    }

    function editTask() {
        const data = $('detailModal')._data; if (!data) return;
        closeDetail(); editingTaskId = data.id;
        selectedAssignee = { code: data.employee_code, name: data.assigned_to_name || data.employee_code, designation: data.assigned_to_role || '' };

        $('tskEmpty').style.display = 'none'; $('tskListGrid').style.display = 'none'; $('tskFormWrap').style.display = 'block';
        $('tskFormHeading').textContent = 'EDIT TASK'; $('btnSaveTask').textContent = 'Update Task';
        
        $('fTitle').value = data.title; $('fDesc').value = data.description || '';
        $('fPriority').value = data.priority; $('fStatus').value = data.status; $('fDueDate').value = data.due_date || '';
        renderAssigneeBox();
    }

    function deleteTask() {
        if (!viewingTaskId) return;
        const targetId = viewingTaskId;
        closeDetail();

        Swal.fire({
            title: 'Delete Task?', text: "This action cannot be undone.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#EF4444', confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', targetId);
                fetch(API, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) { Swal.fire('Deleted!', res.message, 'success').then(() => window.location.reload()); } 
                        else { Swal.fire('Error', res.message, 'error'); }
                    });
            }
        });
    }

    function getInitials(name) { return name ? name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() : '?'; }
    function esc(str) { return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    return { switchTab, openForm, cancelForm, saveTask, openPicker, closePicker, filterEmployees, openDetail, closeDetail, editTask, deleteTask };
})();
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>