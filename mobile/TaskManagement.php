<?php
ob_start();
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login']) || !isset($_SESSION['emp_id'])) {
    if (isset($_POST['action']) || isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: ../login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$emp_id = (int)$_SESSION['emp_id'];
$admin_code = "ADMIN";

// Fetch Current User's Employee Code
$emp_stmt = $conn->prepare("SELECT employee_code FROM employees WHERE id = ?");
if ($emp_stmt) {
    $emp_stmt->bind_param("i", $emp_id);
    $emp_stmt->execute();
    $res = $emp_stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $admin_code = $row['employee_code'];
    }
    $emp_stmt->close();
}

// ==========================================
// Check Permission: "Can Manage Task Management"
// ==========================================
$has_access = false;
$perm_stmt = $conn->prepare("
    SELECT arp.permission_key 
    FROM app_registrations ar
    JOIN app_registration_permissions arp ON ar.id = arp.registration_id
    WHERE ar.employee_id = ? AND ar.is_deleted = 0 AND ar.status = 'Active' AND arp.permission_key = 'Can Manage Task Management'
");
if ($perm_stmt) {
    $perm_stmt->bind_param("i", $emp_id);
    $perm_stmt->execute();
    $res = $perm_stmt->get_result();
    if ($res->num_rows > 0) {
        $has_access = true;
    }
    $perm_stmt->close();
}

if (!$has_access && !isset($_POST['action']) && !isset($_GET['action'])) {
    header('Location: AppDashboard?error=access_denied');
    exit();
}

// ==========================================
// Handle AJAX Requests
// ==========================================
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action) {
    if (ob_get_length()) ob_clean(); 
    header('Content-Type: application/json');

    try {
        switch ($action) {
            case 'list':
                $sql = "SELECT t.*, 
                               e.employee_name AS assigned_to_name, 
                               e.designation AS assigned_to_role
                        FROM tasks t
                        LEFT JOIN employees e ON t.employee_code COLLATE utf8mb4_unicode_ci = e.employee_code COLLATE utf8mb4_unicode_ci
                        ORDER BY t.created_at DESC";
                
                $result = mysqli_query($conn, $sql);
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
                $sql = "SELECT t.*, 
                               e.employee_name AS assigned_to_name, 
                               e.designation AS assigned_to_role
                        FROM tasks t
                        LEFT JOIN employees e ON t.employee_code COLLATE utf8mb4_unicode_ci = e.employee_code COLLATE utf8mb4_unicode_ci
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
                    echo json_encode(['success' => false, 'message' => 'Title and Assignee are required.']);
                    break;
                }

                $title_esc = mysqli_real_escape_string($conn, $title);
                $desc_esc  = mysqli_real_escape_string($conn, $description);
                $prior_esc = mysqli_real_escape_string($conn, $priority);
                $stat_esc  = mysqli_real_escape_string($conn, $status);
                $emp_esc   = mysqli_real_escape_string($conn, $emp_code);
                $due_sql   = $due_date ? "'" . mysqli_real_escape_string($conn, $due_date) . "'" : "NULL";

                $ins_sql = "INSERT INTO tasks (employee_code, assigned_by, title, description, priority, status, due_date) 
                            VALUES ('$emp_esc', '$admin_code', '$title_esc', '$desc_esc', '$prior_esc', '$stat_esc', $due_sql)";
                
                if (!mysqli_query($conn, $ins_sql)) { throw new Exception(mysqli_error($conn)); }
                echo json_encode(['success' => true, 'message' => 'Task created successfully.']);
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
                    echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
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
                mysqli_query($conn, "DELETE FROM tasks WHERE id = $id");
                echo json_encode(['success' => true, 'message' => 'Task deleted.']);
                break;

            case 'search_employees':
                $q = trim($_GET['q'] ?? '');
                $q_esc = mysqli_real_escape_string($conn, $q);
                $sql = "SELECT employee_code AS code, employee_name AS name, designation
                        FROM employees WHERE status = 'Active' 
                        AND (employee_name LIKE '%$q_esc%' OR employee_code LIKE '%$q_esc%')
                        ORDER BY employee_name LIMIT 30";
                $res = mysqli_query($conn, $sql);
                $data = [];
                if ($res) { while($row = mysqli_fetch_assoc($res)) { $data[] = $row; } }
                echo json_encode(['success' => true, 'data' => $data]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tasks - Rhythm</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Hidden scrollbar */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Sleek visible scrollbar for Task List */
        .thin-scrollbar::-webkit-scrollbar { width: 4px; }
        .thin-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .thin-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        /* Smooth transitions for screens */
        .screen-slide { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen">

    <!-- Mobile Device Container -->
    <!-- Added fixed height styling to strictly enforce internal scrolling -->
    <div class="w-full max-w-md bg-[#f4f5f9] h-[100dvh] relative flex flex-col font-sans shadow-2xl overflow-hidden text-gray-800">

        <!-- ========================================== -->
        <!-- SCREEN 1: TASK LIST -->
        <!-- ========================================== -->
        <div id="screen-list" class="flex flex-col w-full h-full absolute inset-0 z-10 bg-[#f4f5f9] screen-slide">
            
            <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 shrink-0 z-20 h-[60px]">
                <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition">
                    <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
                </a>
                <div class="flex-1 flex justify-center mr-[80px]"> 
                    <h1 class="font-semibold text-[17px]">Manage Tasks</h1>
                </div>
            </header>

            <!-- Changed to thin-scrollbar -->
            <main class="flex-1 overflow-y-auto thin-scrollbar pb-24 px-4 pt-4 relative" id="taskListContainer">
                <div class="flex justify-center items-center h-40 text-gray-400">Loading tasks...</div>
            </main>

            <!-- Floating Action Button -->
            <button onclick="openForm()" class="absolute bottom-6 right-6 w-14 h-14 bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-2xl hover:bg-blue-700 transition z-30">
                <i class="fa-solid fa-plus"></i>
            </button>
        </div>


        <!-- ========================================== -->
        <!-- SCREEN 2: TASK DETAILS -->
        <!-- ========================================== -->
        <div id="screen-detail" class="hidden flex-col w-full h-full absolute inset-0 z-20 bg-[#f4f5f9] screen-slide translate-x-full">
            <header class="bg-white text-gray-800 border-b border-gray-200 flex items-center px-4 py-3 shrink-0 z-30 h-[60px]">
                <button onclick="closeDetail()" class="text-gray-500 hover:text-gray-800 px-2 py-1 flex items-center text-[15px] font-medium">
                    <i class="fa-solid fa-chevron-left mr-2 text-lg"></i>
                </button>
                <div class="flex-1 flex justify-center mr-[30px]"> 
                    <h1 class="font-semibold text-[17px]">Task Info</h1>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto thin-scrollbar p-5 flex flex-col" id="detailBody">
                <!-- Injected via JS -->
            </main>

            <div class="bg-white border-t border-gray-200 p-4 flex justify-between gap-3 shrink-0">
                <button onclick="deleteTask()" class="flex-1 py-2.5 rounded-lg border border-red-200 text-red-500 font-semibold bg-red-50 hover:bg-red-100 transition">
                    <i class="fa-regular fa-trash-can mr-1"></i> Delete
                </button>
                <button onclick="editTask()" class="flex-1 py-2.5 rounded-lg bg-[#1c212d] text-white font-semibold hover:bg-gray-800 transition">
                    <i class="fa-solid fa-pen mr-1"></i> Edit Task
                </button>
            </div>
        </div>


        <!-- ========================================== -->
        <!-- SCREEN 3: ADD/EDIT FORM -->
        <!-- ========================================== -->
        <div id="screen-form" class="hidden flex-col w-full h-full absolute inset-0 z-30 bg-white screen-slide translate-y-full">
            <header class="bg-white text-gray-800 border-b border-gray-200 flex items-center px-4 py-3 shrink-0 z-40 h-[60px]">
                <button onclick="closeForm()" class="text-gray-500 hover:text-gray-800 px-2 py-1 flex items-center text-[15px] font-medium">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
                <div class="flex-1 flex justify-center mr-[30px]"> 
                    <h1 class="font-semibold text-[17px]" id="formTitle">Create Task</h1>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto thin-scrollbar p-5 space-y-5">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Task Title <span class="text-red-500">*</span></label>
                    <input type="text" id="fTitle" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Enter title">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Assign To <span class="text-red-500">*</span></label>
                    <div id="assigneeBox" onclick="openPicker()" class="w-full border border-gray-300 border-dashed rounded-lg p-3 flex items-center gap-3 cursor-pointer bg-gray-50 hover:bg-gray-100 transition">
                        <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center"><i class="fa-solid fa-user-plus"></i></div>
                        <div class="flex-1 text-sm text-gray-500">Tap to select employee</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Priority</label>
                        <select id="fPriority" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                        <select id="fStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Due Date</label>
                    <input type="date" id="fDueDate" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea id="fDesc" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Add task details..."></textarea>
                </div>
            </main>

            <div class="p-4 bg-white border-t border-gray-100 shrink-0">
                <button id="btnSave" onclick="saveTask()" class="w-full py-3 rounded-xl bg-blue-600 text-white font-bold text-[15px] shadow-md hover:bg-blue-700 transition">
                    Save Task
                </button>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SCREEN 4: EMPLOYEE PICKER -->
        <!-- ========================================== -->
        <!-- ========================================== -->
        <!-- SCREEN 4: EMPLOYEE PICKER -->
        <!-- ========================================== -->
        <!-- Added overflow-hidden to strict mobile boundaries -->
        <div id="screen-picker" class="hidden flex-col w-full h-full absolute inset-0 z-50 bg-white screen-slide translate-y-full overflow-hidden">
            
            <header class="bg-white text-gray-800 border-b border-gray-200 flex items-center px-4 py-3 shrink-0 z-40 h-[60px]">
                <button onclick="closePicker()" class="text-gray-500 hover:text-gray-800 px-2 py-1 flex items-center">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </button>
                <div class="flex-1 flex justify-center mr-[30px]"> 
                    <h1 class="font-semibold text-[17px]">Select Employee</h1>
                </div>
            </header>

            <div class="p-4 border-b border-gray-100 bg-gray-50 shrink-0">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400"></i>
                    <input type="text" id="searchEmp" oninput="filterEmployees(this.value)" class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Search name or code...">
                </div>
            </div>

            <!-- Added pb-20 for bottom spacing and -webkit-overflow-scrolling for smooth mobile scroll -->
            <main class="flex-1 overflow-y-auto thin-scrollbar p-2 pb-20" id="pickerList" style="-webkit-overflow-scrolling: touch;">
                <!-- Employees injected here -->
            </main>

        </div>

    </div>

    <script>
    // App State
    let allEmployees = [];
    let selectedAssignee = null;
    let editingTaskId = null;
    let currentTaskData = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadTasks();
        fetchEmployees();
    });

    // --- Data Fetching ---
    function loadTasks() {
        fetch('TaskManagement?action=list&_t=' + Date.now())
            .then(r => r.json())
            .then(res => {
                if (res.success) renderTasks(res.data);
                else document.getElementById('taskListContainer').innerHTML = `<div class="text-red-500 text-center mt-10">${res.message}</div>`;
            }).catch(e => console.error(e));
    }

    function fetchEmployees() {
        fetch('TaskManagement?action=search_employees&q=&_t=' + Date.now())
            .then(r => r.json())
            .then(res => { if (res.success) allEmployees = res.data; });
    }

    // --- Rendering UI ---
    function renderTasks(tasks) {
        const container = document.getElementById('taskListContainer');
        if (!tasks || tasks.length === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center h-64 text-gray-400">
                    <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mb-4"><i class="fa-solid fa-list-check text-3xl"></i></div>
                    <p class="font-medium text-gray-600">No tasks found</p>
                    <p class="text-sm mt-1">Tap + to create a new task</p>
                </div>`;
            return;
        }

        let html = '<div class="space-y-3">';
        tasks.forEach(t => {
            const badgeColor = t.priority === 'High' ? 'bg-red-100 text-red-600' : (t.priority === 'Medium' ? 'bg-yellow-100 text-yellow-600' : 'bg-green-100 text-green-600');
            const statusColor = t.status === 'Completed' ? 'bg-green-500' : (t.status === 'In Progress' ? 'bg-blue-500' : (t.status === 'On Hold' ? 'bg-gray-500' : 'bg-yellow-500'));
            const dateStr = t.due_date ? new Date(t.due_date).toLocaleDateString('en-GB', {day:'numeric', month:'short'}) : 'No Due Date';

            html += `
            <div onclick="openDetail(${t.id})" class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 cursor-pointer active:scale-[0.98] transition-transform relative overflow-hidden">
                <div class="absolute left-0 top-0 bottom-0 w-1 ${statusColor}"></div>
                <div class="flex justify-between items-start mb-2 pl-2">
                    <h3 class="font-bold text-[15px] text-gray-800 leading-tight pr-2">${esc(t.title)}</h3>
                    <span class="text-[10px] font-bold uppercase px-2 py-1 rounded ${badgeColor}">${t.priority}</span>
                </div>
                <div class="flex items-center gap-2 pl-2 mt-3 text-xs text-gray-500">
                    <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-[10px]">${getInitials(t.assigned_to_name)}</div>
                    <span class="font-medium truncate flex-1 text-gray-700">${esc(t.assigned_to_name || t.employee_code)}</span>
                    <span class="flex items-center gap-1"><i class="fa-regular fa-calendar"></i> ${dateStr}</span>
                </div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function renderAssigneeBox() {
        const box = document.getElementById('assigneeBox');
        if (!selectedAssignee) {
            box.innerHTML = `
                <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center"><i class="fa-solid fa-user-plus"></i></div>
                <div class="flex-1 text-sm text-gray-500">Tap to select employee</div>`;
            box.className = "w-full border border-gray-300 border-dashed rounded-lg p-3 flex items-center gap-3 cursor-pointer bg-gray-50 hover:bg-gray-100 transition";
        } else {
            box.innerHTML = `
                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">${getInitials(selectedAssignee.name)}</div>
                <div class="flex-1">
                    <div class="text-sm font-bold text-gray-800">${esc(selectedAssignee.name)}</div>
                    <div class="text-xs text-gray-500">${esc(selectedAssignee.code)}</div>
                </div>
                <div class="text-blue-600 text-sm"><i class="fa-solid fa-pen"></i></div>`;
            box.className = "w-full border border-blue-300 border-solid rounded-lg p-3 flex items-center gap-3 cursor-pointer bg-blue-50 transition";
        }
    }

    // --- Screens & Transitions ---
    function showScreen(id) {
        ['screen-list', 'screen-detail', 'screen-form', 'screen-picker'].forEach(s => {
            const el = document.getElementById(s);
            if (s === id) {
                el.classList.remove('hidden');
                // slight delay for transition effect
                setTimeout(() => {
                    el.classList.remove('translate-x-full', 'translate-y-full');
                }, 10);
            } else {
                if(!el.classList.contains('hidden')) {
                    el.classList.add(s === 'screen-detail' ? 'translate-x-full' : 'translate-y-full');
                    setTimeout(() => el.classList.add('hidden'), 300);
                }
            }
        });
    }

    function openDetail(id) {
        fetch('TaskManagement?action=get&id=' + id + '&_t=' + Date.now())
            .then(r => r.json())
            .then(res => {
                if(!res.success) return Swal.fire('Error', res.message, 'error');
                currentTaskData = res.data;
                const t = res.data;
                const dateStr = t.due_date ? new Date(t.due_date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'}) : 'Not set';
                
                document.getElementById('detailBody').innerHTML = `
                    <div class="mb-5">
                        <div class="text-xs font-bold text-gray-400 uppercase mb-1">Title</div>
                        <div class="text-lg font-bold text-gray-800">${esc(t.title)}</div>
                    </div>
                    <div class="flex gap-4 mb-5 border-b border-gray-100 pb-5">
                        <div class="flex-1">
                            <div class="text-xs font-bold text-gray-400 uppercase mb-1">Priority</div>
                            <div class="font-semibold text-gray-800">${t.priority}</div>
                        </div>
                        <div class="flex-1">
                            <div class="text-xs font-bold text-gray-400 uppercase mb-1">Status</div>
                            <div class="font-semibold text-gray-800">${t.status}</div>
                        </div>
                        <div class="flex-1">
                            <div class="text-xs font-bold text-gray-400 uppercase mb-1">Due Date</div>
                            <div class="font-semibold text-gray-800">${dateStr}</div>
                        </div>
                    </div>
                    <div class="mb-5">
                        <div class="text-xs font-bold text-gray-400 uppercase mb-2">Assignee</div>
                        <div class="flex items-center gap-3 bg-white border border-gray-200 p-3 rounded-lg">
                            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">${getInitials(t.assigned_to_name)}</div>
                            <div>
                                <div class="text-sm font-bold text-gray-800">${esc(t.assigned_to_name || t.employee_code)}</div>
                                <div class="text-xs text-gray-500">${esc(t.assigned_to_role || 'Employee')}</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-gray-400 uppercase mb-2">Description</div>
                        <div class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-4 rounded-lg border border-gray-100 min-h-[100px]">
                            ${t.description ? esc(t.description).replace(/\n/g, '<br>') : '<em>No description.</em>'}
                        </div>
                    </div>
                `;
                showScreen('screen-detail');
            });
    }

    function closeDetail() { showScreen('screen-list'); }

    function openForm() {
        editingTaskId = null;
        selectedAssignee = null;
        document.getElementById('formTitle').textContent = 'Create Task';
        document.getElementById('fTitle').value = '';
        document.getElementById('fDesc').value = '';
        document.getElementById('fPriority').value = 'Medium';
        document.getElementById('fStatus').value = 'Pending';
        document.getElementById('fDueDate').value = '';
        renderAssigneeBox();
        showScreen('screen-form');
    }

    function editTask() {
        if(!currentTaskData) return;
        const t = currentTaskData;
        editingTaskId = t.id;
        selectedAssignee = { code: t.employee_code, name: t.assigned_to_name || t.employee_code, designation: t.assigned_to_role };
        
        document.getElementById('formTitle').textContent = 'Edit Task';
        document.getElementById('fTitle').value = t.title;
        document.getElementById('fDesc').value = t.description;
        document.getElementById('fPriority').value = t.priority;
        document.getElementById('fStatus').value = t.status;
        document.getElementById('fDueDate').value = t.due_date || '';
        renderAssigneeBox();
        showScreen('screen-form');
    }

    function closeForm() { showScreen('screen-list'); }

    function openPicker() {
        document.getElementById('searchEmp').value = '';
        filterEmployees('');
        showScreen('screen-picker');
    }

    function closePicker() { showScreen('screen-form'); }

    function filterEmployees(q) {
        const term = q.toLowerCase();
        const list = allEmployees.filter(e => e.name.toLowerCase().includes(term) || e.code.toLowerCase().includes(term));
        
        const ul = document.getElementById('pickerList');
        if(!list.length) { ul.innerHTML = '<div class="text-center text-gray-400 p-5 text-sm">No employees found.</div>'; return; }

        let html = '';
        list.forEach(e => {
            const isSel = selectedAssignee && selectedAssignee.code === e.code;
            html += `
            <div onclick="selectEmployee('${e.code}', '${esc(e.name)}', '${esc(e.designation)}')" class="flex items-center gap-3 p-3 border-b border-gray-100 bg-white hover:bg-gray-50 active:bg-gray-100 cursor-pointer ${isSel ? 'border-l-4 border-l-blue-500' : ''}">
                <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-sm">${getInitials(e.name)}</div>
                <div class="flex-1">
                    <div class="text-sm font-bold text-gray-800">${esc(e.name)}</div>
                    <div class="text-xs text-gray-500">${esc(e.code)} - ${esc(e.designation || 'Employee')}</div>
                </div>
            </div>`;
        });
        ul.innerHTML = html;
    }

    function selectEmployee(code, name, designation) {
        selectedAssignee = { code, name, designation };
        renderAssigneeBox();
        closePicker();
    }

    // --- Server Actions ---
    function saveTask() {
        const title = document.getElementById('fTitle').value.trim();
        if(!title) return Swal.fire('Required', 'Task title cannot be empty', 'warning');
        if(!selectedAssignee) return Swal.fire('Required', 'Please assign an employee', 'warning');

        const btn = document.getElementById('btnSave');
        btn.disabled = true; btn.textContent = 'Saving...';

        const fd = new FormData();
        fd.append('action', editingTaskId ? 'update' : 'add');
        if(editingTaskId) fd.append('id', editingTaskId);
        fd.append('title', title);
        fd.append('description', document.getElementById('fDesc').value);
        fd.append('priority', document.getElementById('fPriority').value);
        fd.append('status', document.getElementById('fStatus').value);
        fd.append('due_date', document.getElementById('fDueDate').value);
        fd.append('employee_code', selectedAssignee.code);

        fetch('TaskManagement', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    Swal.fire({title:'Success', text:res.message, icon:'success', timer:1500, showConfirmButton:false});
                    closeForm();
                    loadTasks();
                } else { Swal.fire('Error', res.message, 'error'); }
            })
            .catch(() => Swal.fire('Error', 'Network error', 'error'))
            .finally(() => { btn.disabled = false; btn.textContent = 'Save Task'; });
    }

    function deleteTask() {
        if(!currentTaskData) return;
        Swal.fire({
            title: 'Delete Task?', text: "This cannot be undone.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData(); 
                fd.append('action', 'delete'); 
                fd.append('id', currentTaskData.id);
                fetch('TaskManagement', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if(res.success) {
                            closeDetail();
                            loadTasks();
                        } else Swal.fire('Error', res.message, 'error');
                    });
            }
        });
    }

    // --- Utilities ---
    function getInitials(name) { return name ? name.split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase() : '?'; }
    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
    </script>
</body>
</html>