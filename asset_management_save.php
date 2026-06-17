<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

// Determine the referring page to redirect back to, fallback to the main UI
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'AssesstManagement';

// Only process if it is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ── 1. Add Asset Type ────────────────────────────────────────────────────────
    if ($action === 'add_asset_type') {
        $name                = trim($_POST['name'] ?? '');
        $category            = trim($_POST['category'] ?? '');
        $remarks             = trim($_POST['remarks'] ?? '');
        $warranty_applicable = isset($_POST['warranty_applicable']) ? 1 : 0;
        $service_applicable  = isset($_POST['service_applicable']) ? 1 : 0;

        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO asset_types (name, category, remarks, warranty_applicable, service_applicable) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssii", $name, $category, $remarks, $warranty_applicable, $service_applicable);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_msg'] = "Asset Type added successfully.";
            } else {
                $_SESSION['error_msg'] = "Error adding Asset Type: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_msg'] = "Asset Type name cannot be empty.";
        }
    }

    // ── 2. Update Asset Type ─────────────────────────────────────────────────────
    elseif ($action === 'update_asset_type') {
        $id                  = intval($_POST['at_id'] ?? 0);
        $name                = trim($_POST['name'] ?? '');
        $category            = trim($_POST['category'] ?? '');
        $remarks             = trim($_POST['remarks'] ?? '');
        $warranty_applicable = isset($_POST['warranty_applicable']) ? 1 : 0;
        $service_applicable  = isset($_POST['service_applicable']) ? 1 : 0;

        if ($id > 0 && !empty($name)) {
            $stmt = mysqli_prepare($conn, "UPDATE asset_types SET name=?, category=?, remarks=?, warranty_applicable=?, service_applicable=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssiii", $name, $category, $remarks, $warranty_applicable, $service_applicable, $id);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_msg'] = "Asset Type updated successfully.";
            } else {
                $_SESSION['error_msg'] = "Error updating Asset Type: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_msg'] = "Invalid data provided for update.";
        }
    }

    // ── 3. Delete Asset Type ─────────────────────────────────────────────────────
    elseif ($action === 'delete_asset_type') {
        $id = intval($_POST['at_id'] ?? 0);

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM asset_types WHERE id=?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_msg'] = "Asset Type deleted successfully.";
            } else {
                $_SESSION['error_msg'] = "Error deleting Asset Type: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }

    // ── 4. Add Asset ─────────────────────────────────────────────────────────────
    elseif ($action === 'add_asset') {
        $asset_type_id = intval($_POST['asset_type_id'] ?? 0);
        $serial_no     = trim($_POST['serial_no'] ?? '');
        $acquired_date = !empty($_POST['acquired_date']) ? $_POST['acquired_date'] : null;
        $location      = trim($_POST['location'] ?? '');
        $status        = trim($_POST['status'] ?? 'Active');

        if ($asset_type_id > 0) {
            // Generate a unique Asset ID (e.g., AST-0001)
            $res = mysqli_query($conn, "SELECT MAX(id) FROM assets");
            $max_id = mysqli_fetch_row($res)[0];
            $next_id = $max_id ? $max_id + 1 : 1;
            $generated_asset_id = 'AST-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);

            $stmt = mysqli_prepare($conn, "INSERT INTO assets (asset_id, asset_type_id, serial_no, acquired_date, location, status) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sissss", $generated_asset_id, $asset_type_id, $serial_no, $acquired_date, $location, $status);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_msg'] = "Asset added successfully with ID: " . $generated_asset_id;
            } else {
                $_SESSION['error_msg'] = "Error adding Asset: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_msg'] = "Please select a valid Asset Type.";
        }
    }

    // ── 5. Assign Asset ──────────────────────────────────────────────────────────
    elseif ($action === 'assign_asset') {
        $asset_id        = intval($_POST['asset_id'] ?? 0);
        $assigned_to     = trim($_POST['employee_search'] ?? ''); 
        $assignment_date = !empty($_POST['assignment_date']) ? $_POST['assignment_date'] : date('Y-m-d');
        
        if ($asset_id > 0 && !empty($assigned_to)) {
            // Update the asset status to 'Active' and assign it
            $stmt = mysqli_prepare($conn, "UPDATE assets SET assigned_to=?, status='Active' WHERE id=?");
            mysqli_stmt_bind_param($stmt, "si", $assigned_to, $asset_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_msg'] = "Asset assigned successfully.";
            } else {
                $_SESSION['error_msg'] = "Error assigning asset: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
             $_SESSION['error_msg'] = "Please provide both an Employee and an Asset.";
        }
    }

    // Redirect back to the main interface after successful operations
    header('Location: ' . $redirect_url);
    exit();

} else {
    // If accessed directly without POST data
    header('Location: ' . $redirect_url);
    exit();
}