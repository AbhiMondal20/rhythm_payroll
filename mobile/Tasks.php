<?php
ob_start();
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login']) || !isset($_SESSION['emp_id'])) {
    header('Location: ../login');
    exit();
}

require_once '../includes/config.php';
require_once '../includes/db_client.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$emp_id = (int)$_SESSION['emp_id'];
$emp_code = "";

// ==========================================
// Fetch Employee Code
// ==========================================
$emp_stmt = $conn->prepare("SELECT employee_code FROM employees WHERE id = ?");
if ($emp_stmt) {
    $emp_stmt->bind_param("i", $emp_id);
    $emp_stmt->execute();
    $res = $emp_stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $emp_code = $row['employee_code'];
    }
    $emp_stmt->close();
}

if (empty($emp_code)) {
    die("Employee code not found.");
}

// ==========================================
// Check Permission: "Can Access Tasks"
// ==========================================
$has_access = false;
$perm_stmt = $conn->prepare("
    SELECT arp.permission_key 
    FROM app_registrations ar
    JOIN app_registration_permissions arp ON ar.id = arp.registration_id
    WHERE ar.employee_id = ? AND ar.is_deleted = 0 AND ar.status = 'Active' AND arp.permission_key = 'Can Access Tasks'
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

// If no access, kick them back to dashboard
if (!$has_access) {
    header('Location: AppDashboard?error=access_denied');
    exit();
}

// ==========================================
// Handle AJAX Request: Update Task Status
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
    header('Content-Type: application/json');
    $task_id = (int)$_POST['task_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Ensure the task actually belongs to this employee code
    $update_stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND employee_code = ?");
    // "sis" -> String, Integer, String
    $update_stmt->bind_param("sis", $new_status, $task_id, $emp_code);
    
    if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Task updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update task.']);
    }
    $update_stmt->close();
    exit();
}

// ==========================================
// Fetch User's Tasks (Using employee_code)
// ==========================================
$tasks = [];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'Pending';

// Modify query based on filter
if ($filter === 'Completed') {
    $query = "SELECT * FROM tasks WHERE employee_code = ? AND status = 'Completed' ORDER BY updated_at DESC";
} else {
    $query = "SELECT * FROM tasks WHERE employee_code = ? AND status != 'Completed' ORDER BY due_date ASC";
}

$task_stmt = $conn->prepare($query);
if ($task_stmt) {
    $task_stmt->bind_param("s", $emp_code);
    $task_stmt->execute();
    $res = $task_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $tasks[] = $row;
    }
    $task_stmt->close();
}

// Helper function for priority colors
function getPriorityColor($priority) {
    switch ($priority) {
        case 'High': return 'bg-red-100 text-red-700 border-red-200';
        case 'Medium': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        case 'Low': return 'bg-green-100 text-green-700 border-green-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Rhythm</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="bg-gray-100 flex justify-center min-h-screen">

    <!-- Mobile Device Container -->
    <div class="w-full max-w-md bg-[#f4f5f9] min-h-screen relative flex flex-col font-sans shadow-2xl overflow-hidden">
        
        <!-- Header -->
        <header class="bg-[#1c212d] text-white flex items-center px-4 py-3 sticky top-0 z-30 h-[60px]">
            <a href="AppDashboard" class="bg-[#e4e6eb] text-gray-800 px-4 py-1.5 rounded-full flex items-center text-[15px] font-medium shadow-sm hover:bg-gray-300 transition">
                <i class="fa-solid fa-chevron-left mr-2 text-sm"></i> Back
            </a>
            <div class="flex-1 flex justify-center mr-[80px]"> 
                <h1 class="font-semibold text-[17px]">My Tasks</h1>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto no-scrollbar flex flex-col">
            
            <!-- Tabs -->
            <div class="bg-white px-4 py-3 flex space-x-4 shadow-sm mb-3">
                <a href="?filter=Pending" class="flex-1 py-2 text-center rounded-lg text-[14px] font-medium transition <?= $filter !== 'Completed' ? 'bg-[#1c212d] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    Active Tasks
                </a>
                <a href="?filter=Completed" class="flex-1 py-2 text-center rounded-lg text-[14px] font-medium transition <?= $filter === 'Completed' ? 'bg-[#1c212d] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    Completed
                </a>
            </div>

            <!-- Task List -->
            <div class="px-4 pb-8 space-y-4">
                <?php if (empty($tasks)): ?>
                    <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                        <i class="fa-solid fa-clipboard-check text-5xl mb-4 opacity-50"></i>
                        <p class="text-sm font-medium">No tasks found here.</p>
                        <p class="text-xs mt-1">You're all caught up!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): 
                        $due_date = !empty($task['due_date']) ? date('M d, Y', strtotime($task['due_date'])) : 'No Due Date';
                        $priority_class = getPriorityColor($task['priority']);
                    ?>
                    
                    <div class="bg-white rounded-xl p-4 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.1)] border border-gray-50 relative overflow-hidden">
                        
                        <!-- Side Color Accent based on status -->
                        <div class="absolute left-0 top-0 bottom-0 w-1 <?= $task['status'] === 'Completed' ? 'bg-green-500' : ($task['status'] === 'In Progress' ? 'bg-blue-500' : 'bg-yellow-400') ?>"></div>
                        
                        <div class="flex justify-between items-start mb-2 pl-2">
                            <h2 class="text-gray-800 font-bold text-[15px] leading-tight flex-1 pr-3">
                                <?= htmlspecialchars($task['title']) ?>
                            </h2>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-md border <?= $priority_class ?>">
                                <?= htmlspecialchars($task['priority']) ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($task['description'])): ?>
                            <p class="text-gray-500 text-[13px] mb-4 pl-2 leading-snug">
                                <?= nl2br(htmlspecialchars($task['description'])) ?>
                            </p>
                        <?php endif; ?>

                        <div class="flex items-center justify-between pl-2 pt-3 border-t border-gray-100">
                            <div class="flex items-center text-gray-400 text-xs font-medium">
                                <i class="fa-regular fa-calendar mr-1.5"></i> <?= $due_date ?>
                            </div>
                            
                            <?php if ($task['status'] !== 'Completed'): ?>
                                <select 
                                    onchange="updateTaskStatus(<?= $task['id'] ?>, this.value)" 
                                    class="bg-[#f4f5f9] border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-1.5 outline-none cursor-pointer">
                                    <option value="Pending" <?= $task['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="In Progress" <?= $task['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="On Hold" <?= $task['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                                    <option value="Completed">Mark Complete</option>
                                </select>
                            <?php else: ?>
                                <span class="flex items-center text-green-600 text-xs font-bold">
                                    <i class="fa-solid fa-check-circle mr-1"></i> Completed
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <script>
        function updateTaskStatus(taskId, newStatus) {
            Swal.fire({
                title: 'Update Task?',
                text: "Change status to '" + newStatus + "'?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1c212d',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, update it!',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-lg px-6',
                    cancelButton: 'rounded-lg px-6'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    
                    const formData = new FormData();
                    formData.append('action', 'update_status');
                    formData.append('task_id', taskId);
                    formData.append('status', newStatus);

                    fetch('tasks<?= isset($_GET["filter"]) ? "?filter=" . htmlspecialchars($_GET["filter"]) : "" ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Updated!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonColor: '#1c212d',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Something went wrong.', 'error');
                        console.error('Error:', error);
                    });
                } else {
                    window.location.reload(); 
                }
            });
        }
    </script>
</body>

</html>