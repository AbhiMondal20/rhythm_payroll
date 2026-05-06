<?php
require_once '../includes/db_conn.php';
require_once '../includes/config.php';

$page_title = 'Client Database';
ob_start();

if (!isset($master) || !($master instanceof mysqli)) {
    die("Master DB connection not found.");
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

function databaseExists(mysqli $master, string $dbName): bool {
    $stmt = $master->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("s", $dbName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function createDatabaseIfNotExists(mysqli $master, string $dbName) {
    $dbName = trim($dbName);

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        return "Invalid database name.";
    }

    if (databaseExists($master, $dbName)) {
        return true;
    }

    if (!$master->query("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        return "Database create failed: " . $master->error;
    }

    return true;
}

function connectClientDb(string $host, string $user, string $pass, string $dbname, int $port = 3306): ?mysqli {
    $conn = @new mysqli($host, $user, $pass, $dbname, $port);
    if ($conn->connect_errno) return null;
    $conn->set_charset("utf8mb4");
    return $conn;
}

function createClientTables(mysqli $clientDb) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_code VARCHAR(100) NOT NULL UNIQUE,
            client_name VARCHAR(200) NULL,
            logo VARCHAR(255) NULL,
            phone VARCHAR(30) NULL,
            email VARCHAR(150) NULL,
            website VARCHAR(255) NULL,
            address TEXT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_code VARCHAR(100) NOT NULL,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(150) NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(100) NOT NULL DEFAULT 'admin',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS user_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            client_code VARCHAR(100) NOT NULL,
            module_key VARCHAR(100) NOT NULL,
            page_name VARCHAR(150) NOT NULL,
            can_view TINYINT(1) NOT NULL DEFAULT 1,
            can_add TINYINT(1) NOT NULL DEFAULT 1,
            can_edit TINYINT(1) NOT NULL DEFAULT 1,
            can_delete TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS log_activity (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(200) NOT NULL,
            description TEXT NULL,
            ip_address VARCHAR(60) NULL,
            browser VARCHAR(255) NULL,
            page VARCHAR(255) NULL,
            status ENUM('success','failure') NOT NULL DEFAULT 'success',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($queries as $sql) {
        if (!$clientDb->query($sql)) {
            return $clientDb->error;
        }
    }

    return true;
}


function seedClientData(mysqli $clientDb, string $clientCode, string $moduleKey) {
    $clientCode = strtolower(trim($clientCode));
    $moduleKey  = strtolower(trim($moduleKey));
    $clientName = strtoupper($clientCode);

    /* ---------------- COMPANY INSERT ---------------- */
    $stmt = $clientDb->prepare("
        INSERT INTO companies (client_code, client_name, status)
        VALUES (?, ?, 'active')
        ON DUPLICATE KEY UPDATE 
            client_name = VALUES(client_name), 
            status = 'active'
    ");

    if (!$stmt) {
        return "Company prepare failed: " . $clientDb->error;
    }

    $stmt->bind_param("ss", $clientCode, $clientName);

    if (!$stmt->execute()) {
        return "Company insert failed: " . $stmt->error;
    }

    $stmt->close();

    /* ---------------- ADMIN USER INSERT ---------------- */
    $username     = "admin";
    $email        = "admin@example.com";
    $passwordHash = password_hash("Admin@123", PASSWORD_BCRYPT);
    $role         = "admin";
    $status       = "active";

    $stmt = $clientDb->prepare("
        INSERT INTO users (client_code, username, email, password_hash, role, status)
        SELECT ?, ?, ?, ?, ?, ?
        WHERE NOT EXISTS (
            SELECT 1 FROM users WHERE username = ? LIMIT 1
        )
    ");

    if (!$stmt) {
        return "Admin prepare failed: " . $clientDb->error;
    }

    $stmt->bind_param(
        "sssssss",
        $clientCode,
        $username,
        $email,
        $passwordHash,
        $role,
        $status,
        $username
    );

    if (!$stmt->execute()) {
        return "Admin insert failed: " . $stmt->error;
    }

    $stmt->close();

    /* ---------------- GET ADMIN USER ID ---------------- */
    $userId = 0;

    $stmt = $clientDb->prepare("
        SELECT id 
        FROM users 
        WHERE username = ? 
        LIMIT 1
    ");

    if (!$stmt) {
        return "Admin select prepare failed: " . $clientDb->error;
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $userId = (int)$row['id'];
    }

    $stmt->close();

    if ($userId <= 0) {
        return "Admin user ID not found.";
    }

    /* ---------------- USER ACCESS INSERT ---------------- */
    $pages = [
        "dashboard",
        "UserAccess"
    ];

    $stmt = $clientDb->prepare("
        INSERT INTO user_access 
            (user_id, client_code, module_key, page_name, can_view, can_add, can_edit, can_delete, created_at, updated_at)
        VALUES 
            (?, ?, ?, ?, 1, 1, 1, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            can_view = VALUES(can_view),
            can_add = VALUES(can_add),
            can_edit = VALUES(can_edit),
            can_delete = VALUES(can_delete),
            updated_at = NOW()
    ");

    if (!$stmt) {
        return "User access prepare failed: " . $clientDb->error;
    }

    foreach ($pages as $pageName) {
        $stmt->bind_param(
            "isss",
            $userId,
            $clientCode,
            $moduleKey,
            $pageName
        );

        if (!$stmt->execute()) {
            return "User access insert failed for {$pageName}: " . $stmt->error;
        }
    }

    $stmt->close();

    return true;
}

/* EDIT LOAD */
$id   = (int)($_GET['id'] ?? 0);
$edit = $id > 0;

$formData = [
    'client_id'   => '',
    'client_code' => '',
    'module_key'  => '',
    'db_host'     => 'localhost',
    'db_name'     => '',
    'db_user'     => '',
    'db_pass'     => '',
    'port'        => '3306',
    'status'      => 'active',
];

if ($edit) {
    $stmt = $master->prepare("
        SELECT id, client_id, client_code, module_key, db_host, db_name, db_user, db_pass, port, status
        FROM client_databases 
        WHERE id = ? 
        LIMIT 1
    ");

    if (!$stmt) {
        die("Edit prepare failed: " . $master->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $formData = array_merge($formData, $row);
    } else {
        die("Database configuration not found.");
    }
}

/* DROPDOWNS */
$clients = [];
$res = $master->query("SELECT id, client_code, client_name FROM clients WHERE status='active' ORDER BY client_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $clients[] = $r;
    }
}

$modules = [];
$res = $master->query("SELECT module_key, module_name FROM modules WHERE status='active' ORDER BY module_name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $modules[] = $r;
    }
}

/* POST */
$msg  = "";
$swal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $client_id   = (int)($_POST['client_id'] ?? 0);
    $client_code = strtolower(trim($_POST['client_code'] ?? ''));
    $module_key  = strtolower(trim($_POST['module_key'] ?? ''));
    $db_host     = trim($_POST['db_host'] ?? 'localhost');
    $db_name     = trim($_POST['db_name'] ?? '');
    $db_user     = trim($_POST['db_user'] ?? '');
    $db_pass_new = trim($_POST['db_pass'] ?? '');
    $port        = (int)($_POST['port'] ?? 3306);
    $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $createDb   = isset($_POST['create_db']);
    $createTbls = isset($_POST['create_tables']);
    $seedData   = isset($_POST['seed_data']);

    $oldPass = '';

    if ($edit) {
        $pstmt = $master->prepare("SELECT db_pass FROM client_databases WHERE id=? LIMIT 1");
        if ($pstmt) {
            $pstmt->bind_param("i", $id);
            $pstmt->execute();
            $prow = $pstmt->get_result()->fetch_assoc();
            $pstmt->close();
            $oldPass = $prow['db_pass'] ?? '';
        }
    }

    $db_pass = ($edit && $db_pass_new === '') ? $oldPass : $db_pass_new;

    $formData = [
        'client_id'   => $client_id,
        'client_code' => $client_code,
        'module_key'  => $module_key,
        'db_host'     => $db_host,
        'db_name'     => $db_name,
        'db_user'     => $db_user,
        'db_pass'     => $db_pass,
        'port'        => $port,
        'status'      => $status,
    ];

    if ($client_id <= 0 || $client_code === '' || $module_key === '' || $db_host === '' || $db_name === '' || $db_user === '') {
        $msg = "All required fields must be filled.";
    } elseif (!preg_match('/^[a-z0-9_]+$/', $client_code)) {
        $msg = "Client code must contain only lowercase letters, numbers and underscores.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
        $msg = "Invalid database name — use only letters, numbers and underscores.";
    }

    /* duplicate check */
    if ($msg === '') {
        if ($edit) {
            $dup = $master->prepare("
                SELECT id 
                FROM client_databases 
                WHERE client_id=? AND module_key=? AND id!=?
                LIMIT 1
            ");
            $dup->bind_param("isi", $client_id, $module_key, $id);
        } else {
            $dup = $master->prepare("
                SELECT id 
                FROM client_databases 
                WHERE client_id=? AND module_key=?
                LIMIT 1
            ");
            $dup->bind_param("is", $client_id, $module_key);
        }

        if (!$dup) {
            $msg = "Duplicate check prepare failed: " . $master->error;
        } else {
            $dup->execute();
            $dup->store_result();

            if ($dup->num_rows > 0) {
                $msg = "This client already has a database for this module.";
            }

            $dup->close();
        }
    }

    /* insert/update */
    if ($msg === '') {
        if ($edit) {
            $stmt = $master->prepare("
                UPDATE client_databases
                SET client_id=?,
                    client_code=?,
                    module_key=?,
                    db_host=?,
                    db_name=?,
                    db_user=?,
                    db_pass=?,
                    port=?,
                    status=?,
                    updated_at=NOW()
                WHERE id=?
            ");

            if (!$stmt) {
                $msg = "Update prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "issssssisi",
                    $client_id,
                    $client_code,
                    $module_key,
                    $db_host,
                    $db_name,
                    $db_user,
                    $db_pass,
                    $port,
                    $status,
                    $id
                );
            }
        } else {
            $stmt = $master->prepare("
                INSERT INTO client_databases
                (client_id, client_code, module_key, db_host, db_name, db_user, db_pass, port, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$stmt) {
                $msg = "Insert prepare failed: " . $master->error;
            } else {
                $stmt->bind_param(
                    "issssssis",
                    $client_id,
                    $client_code,
                    $module_key,
                    $db_host,
                    $db_name,
                    $db_user,
                    $db_pass,
                    $port,
                    $status
                );
            }
        }

        if ($msg === '') {
            if (!$stmt->execute()) {
                $msg = "Save failed: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($msg === '' && $createDb) {
        $r = createDatabaseIfNotExists($master, $db_name);
        if ($r !== true) {
            $msg = $r;
        }
    }

    if ($msg === '' && ($createTbls || $seedData)) {
        $clientDb = connectClientDb($db_host, $db_user, $db_pass, $db_name, $port);
        $usingMasterFallback = false;
        $oldMasterDb = null;

        if (!$clientDb) {
            $oldRes = $master->query("SELECT DATABASE() AS db");
            if ($oldRes) {
                $oldMasterDb = $oldRes->fetch_assoc()['db'] ?? null;
            }

            if (@$master->select_db($db_name)) {
                $clientDb = $master;
                $usingMasterFallback = true;
            }
        }

        if (!$clientDb) {
            $msg = "Database saved, but cannot connect to client database for table/seed operation.";
        } else {
            if ($createTbls) {
                $r = createClientTables($clientDb);
                if ($r !== true) {
                    $msg = "Table creation failed: " . $r;
                }
            }

            if ($msg === '' && $seedData) {
                $r = seedClientData($clientDb, $client_code, $module_key);
                if ($r !== true) {
                    $msg = "Seed failed: " . $r;
                }
            }

            if ($usingMasterFallback && $oldMasterDb) {
                @$master->select_db($oldMasterDb);
            } elseif (!$usingMasterFallback) {
                $clientDb->close();
            }
        }
    }

    $swal = ($msg === '')
        ? [
            'icon' => 'success',
            'title' => $edit ? 'Updated!' : 'Created!',
            'text' => $edit ? 'Client database updated successfully.' : 'Client database created successfully.',
            'redirect' => 'ClientDatabase'
        ]
        : [
            'icon' => 'error',
            'title' => 'Error',
            'text' => $msg,
            'redirect' => ''
        ];
}
?>

<link rel="stylesheet" href="../includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ════════════════════════════════════════
   CLIENT DATABASE — ADD / EDIT PAGE
════════════════════════════════════════ */

/* ── breadcrumb ── */
.cd-bc {
    display:flex;align-items:center;gap:8px;
    font-size:13px;color:#6B7280;flex-wrap:wrap;
}
.cd-bc a       { color:#6B7280;text-decoration:none;transition:color .15s; }
.cd-bc a:hover { color:#2563EB; }
.cd-bc .sep    { color:#D1D5DB; }
.cd-bc strong  { color:#111827;font-weight:600; }

/* ── page card ── */
.cd-card {
    background:#fff;border:1px solid #E5E7EB;border-radius:14px;
    overflow:hidden;box-shadow:0 2px 16px rgba(15,23,42,.05);
}

/* ── card header ── */
.cd-card-head {
    padding:18px 24px;border-bottom:1px solid #E5E7EB;
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:10px;
}
.cd-head-title {
    font-size:16px;font-weight:700;color:#111827;display:flex;align-items:center;gap:10px;
}
.cd-head-icon {
    width:36px;height:36px;border-radius:9px;background:#EFF6FF;
    display:flex;align-items:center;justify-content:center;
    font-size:15px;color:#2563EB;flex-shrink:0;
}
.cd-status-badge {
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;
}

/* ── section blocks inside form ── */
.cd-section {
    border:1px solid #E5E7EB;border-radius:10px;overflow:hidden;margin-bottom:18px;
}
.cd-section-head {
    padding:11px 18px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;
    display:flex;align-items:center;gap:9px;
}
.cd-section-head i { font-size:13px;color:#6B7280;width:16px;text-align:center; }
.cd-section-head span { font-size:12.5px;font-weight:700;color:#374151;letter-spacing:.3px;text-transform:uppercase; }
.cd-section-body { padding:20px 20px 16px; }

/* ── grid ── */
.cd-grid { display:grid;gap:20px 24px; }
.cd-grid.c4 { grid-template-columns:repeat(4,1fr); }
.cd-grid.c3 { grid-template-columns:repeat(3,1fr); }
.cd-grid.c2 { grid-template-columns:repeat(2,1fr); }
.cd-grid.c1 { grid-template-columns:1fr; }
.cd-span2   { grid-column:span 2; }

/* ── field group ── */
.cd-fg { display:flex;flex-direction:column;gap:5px; }
.cd-fg label {
    font-size:11.5px;font-weight:700;color:#6B7280;
    letter-spacing:.4px;text-transform:uppercase;
}
.cd-fg label .req { color:#DC2626;margin-left:2px; }
.cd-fg input[type=text],
.cd-fg input[type=password],
.cd-fg input[type=number],
.cd-fg input[type=email],
.cd-fg select {
    padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;
    font-size:13.5px;font-family:inherit;color:#111827;
    outline:none;background:#fff;transition:.15s;width:100%;
}
.cd-fg input:focus,.cd-fg select:focus {
    border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.08);
}
.cd-fg input.is-invalid,.cd-fg select.is-invalid {
    border-color:#DC2626;background:#FFF5F5;
}
.cd-fg .cd-hint { font-size:11px;color:#9CA3AF;margin-top:3px; }

/* password toggle */
.cd-pw-wrap { position:relative; }
.cd-pw-wrap input { padding-right:38px; }
.cd-pw-toggle {
    position:absolute;right:10px;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:13px;
    transition:color .15s;
}
.cd-pw-toggle:hover { color:#374151; }

/* ── generate options checkboxes ── */
.cd-checks { display:flex;gap:20px;flex-wrap:wrap; }
.cd-check {
    display:flex;align-items:center;gap:8px;
    font-size:13.5px;color:#374151;cursor:pointer;
    padding:8px 14px;border:1.5px solid #E5E7EB;border-radius:8px;
    transition:.15s;user-select:none;
}
.cd-check:has(input:checked) { background:#EFF6FF;border-color:#BFDBFE;color:#1D4ED8; }
.cd-check:hover { border-color:#2563EB; }
.cd-check input { width:15px;height:15px;accent-color:#2563EB;cursor:pointer; }

/* ── test connection button ── */
.cd-test-btn {
    display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
    border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;
    font-size:13px;font-weight:600;color:#374151;cursor:pointer;
    font-family:inherit;transition:.15s;
}
.cd-test-btn:hover { border-color:#2563EB;color:#2563EB;background:#EFF6FF; }
.cd-test-btn i { font-size:12px; }

/* ── form footer ── */
.cd-footer {
    padding:18px 24px;border-top:1px solid #E5E7EB;
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:12px;background:#FAFAFA;
}
.cd-footer-note { font-size:12px;color:#9CA3AF; }
.cd-footer-btns { display:flex;gap:10px;flex-wrap:wrap; }
.cd-cancel-btn {
    padding:9px 24px;background:#fff;color:#374151;
    border:1.5px solid #D1D5DB;border-radius:8px;
    font-size:13.5px;font-weight:500;cursor:pointer;
    font-family:inherit;transition:.15s;text-decoration:none;
    display:inline-flex;align-items:center;gap:6px;
}
.cd-cancel-btn:hover { border-color:#374151; }
.cd-save-btn {
    padding:9px 28px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:13.5px;font-weight:700;cursor:pointer;
    font-family:inherit;transition:background .15s;
    display:inline-flex;align-items:center;gap:7px;
}
.cd-save-btn:hover { background:#1D4ED8; }

/* ── alert banner ── */
.cd-alert {
    display:flex;align-items:center;gap:10px;padding:12px 16px;
    border-radius:8px;font-size:13px;font-weight:500;margin-bottom:16px;
}
.cd-alert.error   { background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5; }
.cd-alert.success { background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7; }
.cd-alert i { font-size:14px;flex-shrink:0; }

/* responsive */
@media(max-width:1100px){
    .cd-grid.c4 { grid-template-columns:repeat(2,1fr); }
    .cd-span2   { grid-column:span 2; }
}
@media(max-width:700px){
    .cd-grid.c4,.cd-grid.c3,.cd-grid.c2 { grid-template-columns:1fr; }
    .cd-span2 { grid-column:span 1; }
    .cd-footer { flex-direction:column;align-items:stretch; }
    .cd-footer-btns { flex-direction:column; }
    .cd-save-btn,.cd-cancel-btn { width:100%;justify-content:center; }
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:#111827;margin-bottom:4px">
            <?= $edit ? 'Edit Client Database' : 'Create Client Database' ?>
        </h1>
        <div class="cd-bc">
            <a href="ClientDatabase">Client Databases</a>
            <span class="sep">›</span>
            <strong><?= $edit ? 'Edit Database' : 'New Database' ?></strong>
        </div>
    </div>
</div>

<?php if(!empty($msg)): ?>
<div class="cd-alert error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <?= e($msg) ?>
</div>
<?php endif; ?>

<div class="cd-card">
    <div class="cd-card-head">
        <div class="cd-head-title">
            <div class="cd-head-icon">
                <i class="fa-solid fa-database"></i>
            </div>
            <?= $edit ? 'Database Configuration' : 'New Database Configuration' ?>
        </div>
    </div>

    <form method="POST" autocomplete="off" id="cdForm" novalidate>
        <input type="hidden" name="save_client" value="1">

        <div style="padding:24px">

            <div class="cd-section">
                <div class="cd-section-head">
                    <i class="fa-solid fa-building"></i>
                    <span>Client Information</span>
                </div>

                <div class="cd-section-body">
                    <div class="cd-grid c4">

                        <div class="cd-fg">
                            <label>Client <span class="req">*</span></label>
                            <select name="client_id" required id="clientSelect">
                                <option value="">— Select Client —</option>
                                <?php foreach($clients as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"
                                            data-code="<?= e($c['client_code'] ?? '') ?>"
                                        <?= ((int)$formData['client_id']===(int)$c['id'])?'selected':'' ?>>
                                        <?= e($c['client_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>Client Code <span class="req">*</span></label>
                            <input type="text"
                                   name="client_code"
                                   id="clientCode"
                                   value="<?= e($formData['client_code']) ?>"
                                   oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'')"
                                   required>
                        </div>

                        <div class="cd-fg">
                            <label>Module <span class="req">*</span></label>
                            <select name="module_key" required>
                                <option value="">— Select Module —</option>
                                <?php foreach($modules as $m): ?>
                                    <option value="<?= e($m['module_key']) ?>"
                                        <?= ($formData['module_key']===$m['module_key'])?'selected':'' ?>>
                                        <?= e($m['module_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="cd-fg">
                            <label>Status</label>
                            <select name="status">
                                <option value="active" <?= $formData['status']==='active'?'selected':'' ?>>Active</option>
                                <option value="inactive" <?= $formData['status']==='inactive'?'selected':'' ?>>Inactive</option>
                            </select>
                        </div>

                    </div>
                </div>
            </div>

            <div class="cd-section">
                <div class="cd-section-head">
                    <i class="fa-solid fa-server"></i>
                    <span>Database Connection</span>
                </div>

                <div class="cd-section-body">
                    <div class="cd-grid c4">

                        <div class="cd-fg">
                            <label>Host <span class="req">*</span></label>
                            <input type="text" name="db_host" value="<?= e($formData['db_host']) ?>" required>
                        </div>

                        <div class="cd-fg">
                            <label>Port <span class="req">*</span></label>
                            <input type="number" name="port" value="<?= e($formData['port']) ?>" required>
                        </div>

                        <div class="cd-fg">
                            <label>Database Name <span class="req">*</span></label>
                            <input type="text"
                                   name="db_name"
                                   id="dbName"
                                   value="<?= e($formData['db_name']) ?>"
                                   oninput="this.value=this.value.replace(/[^a-zA-Z0-9_]/g,'')"
                                   required>
                        </div>

                        <div class="cd-fg">
                            <label>Database User <span class="req">*</span></label>
                            <input type="text" name="db_user" value="<?= e($formData['db_user'] ?? 'root') ?>" required>
                        </div>

                        <div class="cd-fg cd-span2">
                            <label>Database Password</label>
                            <div class="cd-pw-wrap">
                                <input type="password"
                                       name="db_pass"
                                       id="dbPassInput"
                                       value=""
                                       placeholder="<?= $edit ? 'Leave blank to keep existing password' : 'Database password' ?>">
                                <button type="button" class="cd-pw-toggle" onclick="togglePass()">
                                    <i class="fa-solid fa-eye" id="passEyeIcon"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="cd-section">
                <div class="cd-section-head">
                    <i class="fa-solid fa-gears"></i>
                    <span>Generate Options</span>
                </div>

                <div class="cd-section-body">
                    <div class="cd-checks">
                        <label class="cd-check">
                            <input type="checkbox" name="create_db" checked>
                            Create Database
                        </label>

                        <label class="cd-check">
                            <input type="checkbox" name="create_tables" checked>
                            Create Tables
                        </label>

                        <label class="cd-check">
                            <input type="checkbox" name="seed_data" checked>
                            Seed Admin Data
                        </label>
                    </div>
                </div>
            </div>

        </div>

        <div class="cd-footer">
            <span class="cd-footer-note">
                All required fields must be filled before saving.
            </span>

            <div class="cd-footer-btns">
                <a href="ClientDatabase" class="cd-cancel-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Cancel
                </a>

                <button type="submit" class="cd-save-btn" id="saveBtn">
                    <i class="fa-solid fa-<?= $edit ? 'floppy-disk' : 'circle-plus' ?>"></i>
                    <?= $edit ? 'Update Database' : 'Create Database' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function togglePass() {
    const inp = document.getElementById('dbPassInput');
    const icon = document.getElementById('passEyeIcon');

    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'fa-solid fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fa-solid fa-eye';
    }
}

document.getElementById('clientSelect').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const code = selected.getAttribute('data-code') || selected.text.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');

    const clientCode = document.getElementById('clientCode');
    const dbName = document.getElementById('dbName');

    if (!clientCode.value) {
        clientCode.value = code;
    }

    if (!dbName.value) {
        dbName.value = code + '_db';
    }
});

document.getElementById('clientCode').addEventListener('input', function() {
    const dbName = document.getElementById('dbName');
    if (!dbName.value || dbName.dataset.manual !== '1') {
        dbName.value = this.value ? this.value + '_db' : '';
    }
});

document.getElementById('dbName').addEventListener('input', function() {
    this.dataset.manual = '1';
});

document.getElementById('cdForm').addEventListener('submit', function(e) {
    let ok = true;

    this.querySelectorAll('[required]').forEach(function(el) {
        el.classList.remove('is-invalid');

        if (!el.value.trim()) {
            el.classList.add('is-invalid');
            ok = false;
        }
    });

    if (!ok) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Required fields',
            text: 'Please fill in all required fields.',
            confirmButtonColor: '#2563EB'
        });
        return false;
    }

    const btn = document.getElementById('saveBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
});
</script>

<?php if(!empty($swal)): ?>
<script>
Swal.fire({
    icon: "<?= e($swal['icon']) ?>",
    title: "<?= e($swal['title']) ?>",
    text: "<?= e($swal['text']) ?>",
    confirmButtonColor: "#2563eb",
    customClass: { popup:'swal-rounded' }
}).then(function() {
    <?php if(!empty($swal['redirect'])): ?>
    window.location.href = "<?= e($swal['redirect']) ?>";
    <?php endif; ?>
});
</script>
<style>
.swal-rounded { border-radius:14px!important; }
</style>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
include 'header.php';
echo $page_content;
include 'footer.php';
?>
<script src="../includes/assets/scripts.js"></script>