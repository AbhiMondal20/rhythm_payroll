<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Devices';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

function e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function dtView($v) {
    if (empty($v)) return '--';
    return date('d/m/Y h:i:s A', strtotime($v));
}

/* DB TABLE */
$conn->query("
CREATE TABLE IF NOT EXISTS att_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(100) NOT NULL,
    serial_number VARCHAR(80) NOT NULL,
    code VARCHAR(50) NOT NULL,
    status ENUM('online','offline') NOT NULL DEFAULT 'online',
    last_download DATETIME NULL,
    last_ping DATETIME NULL,
    timezone VARCHAR(50) DEFAULT 'IST',
    direction ENUM('IN','OUT','IN/OUT') DEFAULT 'IN/OUT',
    port_no VARCHAR(20) NULL,
    connection_type VARCHAR(50) DEFAULT 'TCP/IP',
    location VARCHAR(120) NULL,
    ip_address VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_device_serial (serial_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* DUMMY DATA */
$count = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM att_devices");
if ($res) {
    $count = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($count === 0) {
    $dummy = [
        ['Siliguri', 'CEXJ232460764', 'MB160', 'online', '2026-05-12 14:47:31', '2026-05-12 16:50:54', 'IST', 'IN/OUT', '8888', 'TCP/IP', 'Siliguri', '192.168.29.1'],
        ['Coochbehar', 'CGKK211161864', 'MB161', 'online', '2026-05-12 12:16:24', '2026-05-12 16:50:51', 'IST', 'IN/OUT', '8888', 'TCP/IP', 'Coochbehar', '192.168.29.2'],
        ['Raiganj', 'NFZ8242700352', 'MB162', 'online', '2026-05-12 11:00:54', '2026-05-12 16:50:35', 'IST', 'IN/OUT', '8888', 'TCP/IP', 'Raiganj', '192.168.29.3'],
    ];

    $stmt = $conn->prepare("
        INSERT INTO att_devices
        (device_name, serial_number, code, status, last_download, last_ping, timezone, direction, port_no, connection_type, location, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        foreach ($dummy as $d) {
            $stmt->bind_param("ssssssssssss", $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], $d[8], $d[9], $d[10], $d[11]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

$mode = $_GET['mode'] ?? 'list';
$flash = '';
$flash_type = 'success';

$timezones  = ['IST','UTC','GMT','EST','PST','CST'];
$directions = ['IN','OUT','IN/OUT'];
$conn_types = ['TCP/IP','USB','Serial'];
$locations  = ['Siliguri','Coochbehar','Raiganj','Malda'];

/* POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $device_name     = trim($_POST['device_name'] ?? '');
    $serial_number   = trim($_POST['serial_number'] ?? '');
    $code            = trim($_POST['code'] ?? '');
    $timezone        = trim($_POST['timezone'] ?? 'IST');
    $port_no         = trim($_POST['port_no'] ?? '');
    $direction       = trim($_POST['direction'] ?? 'IN/OUT');
    $location        = trim($_POST['location_id'] ?? '');
    $connection_type = trim($_POST['connection_type'] ?? 'TCP/IP');
    $ip_address      = trim($_POST['ip_address'] ?? '');

    if ($action === 'add_device') {
        if ($device_name === '' || $serial_number === '' || $code === '') {
            $flash = 'Device Name, Serial Number and Code are required.';
            $flash_type = 'error';
            $mode = 'add';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO att_devices
                (device_name, serial_number, code, timezone, port_no, direction, location, connection_type, ip_address, status, last_download, last_ping, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'online', NOW(), NOW(), NOW(), NOW())
            ");

            if (!$stmt) {
                $flash = 'Prepare failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'add';
            } else {
                $stmt->bind_param("sssssssss", $device_name, $serial_number, $code, $timezone, $port_no, $direction, $location, $connection_type, $ip_address);

                if ($stmt->execute()) {
                    header("Location: Devices?saved=1");
                    exit;
                } else {
                    $flash = $stmt->errno === 1062 ? 'Serial Number already exists.' : 'Save failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'add';
                }

                $stmt->close();
            }
        }
    }

    if ($action === 'save_device') {
        $id = (int)($_POST['device_id'] ?? 0);

        if ($id <= 0 || $device_name === '' || $serial_number === '' || $code === '') {
            $flash = 'Invalid data. Device Name, Serial Number and Code are required.';
            $flash_type = 'error';
        } else {
            $stmt = $conn->prepare("
                UPDATE att_devices
                SET device_name=?,
                    serial_number=?,
                    code=?,
                    timezone=?,
                    port_no=?,
                    direction=?,
                    location=?,
                    connection_type=?,
                    ip_address=?,
                    last_ping=NOW(),
                    updated_at=NOW()
                WHERE id=?
            ");

            if (!$stmt) {
                $flash = 'Prepare failed: ' . $conn->error;
                $flash_type = 'error';
            } else {
                $stmt->bind_param("sssssssssi", $device_name, $serial_number, $code, $timezone, $port_no, $direction, $location, $connection_type, $ip_address, $id);

                if ($stmt->execute()) {
                    header("Location: Devices?updated=1");
                    exit;
                } else {
                    $flash = $stmt->errno === 1062 ? 'Serial Number already exists.' : 'Update failed: ' . $stmt->error;
                    $flash_type = 'error';
                }

                $stmt->close();
            }
        }
    }

    if ($action === 'delete_device') {
        $id = (int)($_POST['device_id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM att_devices WHERE id=?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    header("Location: Devices?deleted=1");
                    exit;
                } else {
                    $flash = 'Delete failed: ' . $stmt->error;
                    $flash_type = 'error';
                }
                $stmt->close();
            }
        }
    }
}

if (!empty($_GET['saved'])) {
    $flash = 'Device added successfully.';
    $flash_type = 'success';
}
if (!empty($_GET['updated'])) {
    $flash = 'Device updated successfully.';
    $flash_type = 'success';
}
if (!empty($_GET['deleted'])) {
    $flash = 'Device deleted successfully.';
    $flash_type = 'success';
}

/* FETCH */
$devices = [];
$res = $conn->query("
    SELECT *
    FROM att_devices
    ORDER BY device_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $devices[] = $row;
    }
}

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">
<style>
/* ── Config nav tabs ── */
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}
.dv-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.dv-inner{padding:20px 28px}
.dv-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.dv-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555}
.dv-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.dv-breadcrumb a:hover{text-decoration:underline}
.dv-breadcrumb .sep{color:#bbb;font-size:11px}
.btn-add-device{display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .16s}
.btn-add-device:hover{background:#1d4ed8}
.dv-list-heading{font-size:12.5px;color:#6b7280;font-weight:500;margin-bottom:12px}
.dv-table-wrap{border:1px solid #e8ecf0;border-radius:10px;overflow:hidden}
table.dv-table{width:100%;border-collapse:collapse}
table.dv-table thead th{background:#f8fafc;padding:13px 18px;text-align:left;font-size:12.5px;font-weight:700;color:#374151;border-bottom:1px solid #e8ecf0}
table.dv-table tbody tr.dv-row{border-bottom:1px solid #f1f4f8;cursor:pointer;transition:background .12s}
table.dv-table tbody tr.dv-row:last-child{border-bottom:none}
table.dv-table tbody tr.dv-row:hover{background:#f9fafb}
table.dv-table tbody tr.dv-row.expanded{background:#f0f7ff;border-bottom:none}
table.dv-table tbody td{padding:14px 18px;font-size:13.5px;color:#374151;vertical-align:middle}
.status-dot{display:inline-block;width:18px;height:18px;border-radius:50%;border:2px solid #22c55e;position:relative}
.status-dot::after{content:'';position:absolute;top:3px;left:3px;width:8px;height:8px;border-radius:50%;background:#22c55e}
.status-dot.offline{border-color:#9ca3af}
.status-dot.offline::after{background:#9ca3af}
.chevron-cell{text-align:right;width:32px;padding-right:16px}
.chevron-cell i{font-size:12px;color:#9ca3af;transition:transform .2s}
.dv-row.expanded .chevron-cell i{transform:rotate(180deg)}
tr.dv-edit-row{display:none}
tr.dv-edit-row.show{display:table-row}
tr.dv-edit-row td{padding:20px 18px 24px;background:#f0f7ff;border-bottom:1px solid #e8ecf0}
.dv-edit-grid{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:18px 24px;margin-bottom:8px}
.dv-ef label{display:block;font-size:12px;color:#6b7280;margin-bottom:6px;font-weight:500}
.dv-ef label .req{color:#ef4444;margin-right:2px}
.dv-ef-value{font-size:13.5px;color:#1e2d3d;padding-bottom:8px;border-bottom:1px solid #d1d5db;min-height:26px}
.dv-input{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:7px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s}
.dv-input::placeholder{color:#c4c9d4}
.dv-input:focus{border-color:#2563eb}
.dv-select{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:7px 20px 7px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;appearance:none;cursor:pointer;transition:border-color .16s}
.dv-select:focus{border-color:#2563eb}
.dv-edit-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid #e2e8f0}
.btn-dv-delete{padding:8px 20px;border:1.5px solid #ef4444;background:#fff;border-radius:6px;font-size:13px;color:#ef4444;cursor:pointer;font-weight:600;transition:background .14s}
.btn-dv-delete:hover{background:#fee2e2}
.btn-dv-cancel{padding:8px 20px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:13px;color:#374151;cursor:pointer;font-weight:600;transition:background .14s}
.btn-dv-cancel:hover{background:#f1f5f9}
.btn-dv-save{padding:8px 20px;background:#2563eb;border:none;border-radius:6px;font-size:13px;color:#fff;cursor:pointer;font-weight:600;transition:background .14s}
.btn-dv-save:hover{background:#1d4ed8}
.dv-add-card{background:#fff;border:1px solid #e8ecf0;border-radius:10px;padding:28px 28px 10px}
.dv-add-title{font-size:14px;font-weight:800;color:#1e2d3d;text-transform:uppercase;letter-spacing:.3px;margin-bottom:24px}
.dv-add-grid-top{display:grid;grid-template-columns:1fr 1fr;gap:18px 36px;margin-bottom:22px}
.dv-add-grid{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:18px 24px;margin-bottom:22px}
.dv-add-grid.col3{grid-template-columns:1fr 1fr 1fr}
.dv-af label{display:block;font-size:12.5px;color:#374151;margin-bottom:8px;font-weight:400}
.dv-af label .req{color:#ef4444;margin-right:2px}
.dv-add-actions{display:flex;justify-content:flex-end;gap:12px;padding-top:20px;padding-bottom:18px;border-top:1px solid #e8ecf0;margin-top:10px}
.flash-msg{padding:10px 16px;border-radius:7px;font-size:13px;margin-bottom:14px;font-weight:500}
.flash-msg.success{background:#dcfce7;color:#166534}
.flash-msg.error{background:#fee2e2;color:#991b1b}
.toast-container{position:fixed;top:20px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{display:flex;align-items:center;gap:10px;background:#fff;border-radius:8px;padding:13px 18px;box-shadow:0 4px 18px rgba(0,0,0,.14);font-size:13.5px;font-weight:500;min-width:260px;pointer-events:all;animation:toastIn .25s ease;border-left:4px solid #2563eb;color:#1e2d3d}
.toast.success{border-color:#22c55e}
.toast.error{border-color:#ef4444}
.toast i{font-size:16px}
.toast.success i{color:#22c55e}
.toast.error i{color:#ef4444}
.toast-close{margin-left:auto;cursor:pointer;color:#9ca3af;font-size:14px;background:none;border:none;padding:0;line-height:1}
@keyframes toastIn{from{transform:translateX(40px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes toastOut{from{opacity:1}to{opacity:0;transform:translateX(40px)}}
</style>

<div class="toast-container" id="toastContainer"></div>

<div class="cfg-page-head">
  <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
      <a href="configuration#<?= e($k) ?>" class="cfg-tab <?= $k==='Attendance'?'active':'' ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="dv-wrapper">
    <div class="dv-inner">

      <?php if ($flash): ?>
        <div class="flash-msg <?= e($flash_type) ?>"><?= e($flash) ?></div>
      <?php endif; ?>

      <?php if ($mode === 'add'): ?>

      <div class="dv-topbar" style="margin-bottom:20px">
        <nav class="dv-breadcrumb">
          <a href="Devices">Attendance</a>
          <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
          <span>Devices</span>
        </nav>
      </div>

      <div class="dv-add-card">
        <div class="dv-add-title">New Device</div>

        <form method="POST">
          <input type="hidden" name="action" value="add_device">

          <div class="dv-add-grid-top">
            <div class="dv-af">
              <label><span class="req">*</span> Device Name</label>
              <input type="text" name="device_name" class="dv-input" value="<?= e($_POST['device_name'] ?? '') ?>" required>
            </div>

            <div class="dv-af">
              <label><span class="req">*</span> Serial Number</label>
              <input type="text" name="serial_number" class="dv-input" value="<?= e($_POST['serial_number'] ?? '') ?>" required>
            </div>
          </div>

          <div class="dv-add-grid">
            <div class="dv-af">
              <label><span class="req">*</span> Code</label>
              <input type="text" name="code" class="dv-input" value="<?= e($_POST['code'] ?? '') ?>" required>
            </div>

            <div class="dv-af">
              <label>Time Zone</label>
              <select name="timezone" class="dv-select">
                <?php foreach ($timezones as $tz): ?>
                  <option value="<?= e($tz) ?>" <?= (($_POST['timezone'] ?? 'IST') === $tz) ? 'selected' : '' ?>><?= e($tz) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="dv-af">
              <label>Port No.</label>
              <input type="text" name="port_no" class="dv-input" value="<?= e($_POST['port_no'] ?? '') ?>">
            </div>

            <div class="dv-af">
              <label>Direction</label>
              <select name="direction" class="dv-select">
                <?php foreach ($directions as $d): ?>
                  <option value="<?= e($d) ?>" <?= (($_POST['direction'] ?? 'IN/OUT') === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="dv-add-grid col3">
            <div class="dv-af">
              <label>Location</label>
              <select name="location_id" class="dv-select">
                <option value="">Select</option>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= e($loc) ?>" <?= (($_POST['location_id'] ?? '') === $loc) ? 'selected' : '' ?>><?= e($loc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="dv-af">
              <label>Connection Type</label>
              <select name="connection_type" class="dv-select">
                <?php foreach ($conn_types as $ct): ?>
                  <option value="<?= e($ct) ?>" <?= (($_POST['connection_type'] ?? 'TCP/IP') === $ct) ? 'selected' : '' ?>><?= e($ct) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="dv-af">
              <label>IP Address</label>
              <input type="text" name="ip_address" class="dv-input" placeholder="192.168.x.x" value="<?= e($_POST['ip_address'] ?? '') ?>">
            </div>
          </div>

          <div class="dv-add-actions">
            <button type="button" class="btn-dv-cancel" onclick="window.location.href='Devices'">Cancel</button>
            <button type="submit" class="btn-dv-save">Add</button>
          </div>
        </form>
      </div>

      <?php else: ?>

      <div class="dv-topbar">
        <nav class="dv-breadcrumb">
          <a href="attendance_config">Attendance</a>
          <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
          <span>Devices</span>
        </nav>

        <button class="btn-add-device" onclick="window.location.href='Devices?mode=add'">
          <i class="fa-solid fa-plus"></i> Add New Device
        </button>
      </div>

      <div class="dv-list-heading">List of Devices</div>

      <div class="dv-table-wrap">
        <table class="dv-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Serial Number</th>
              <th>Status</th>
              <th>Last Download</th>
              <th>Last Ping</th>
              <th style="width:32px"></th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($devices as $dev): ?>
            <tr class="dv-row" id="drow-<?= (int)$dev['id'] ?>" onclick="toggleDevice(<?= (int)$dev['id'] ?>)">
              <td><?= e($dev['device_name']) ?></td>
              <td><?= e($dev['serial_number']) ?></td>
              <td><span class="status-dot <?= $dev['status'] === 'online' ? '' : 'offline' ?>"></span></td>
              <td><?= e(dtView($dev['last_download'])) ?></td>
              <td><?= e(dtView($dev['last_ping'])) ?></td>
              <td class="chevron-cell"><i class="fa-solid fa-chevron-down"></i></td>
            </tr>

            <tr class="dv-edit-row" id="dedit-<?= (int)$dev['id'] ?>">
              <td colspan="6">
                <form method="POST" onclick="event.stopPropagation()">
                  <input type="hidden" name="action" value="save_device">
                  <input type="hidden" name="device_id" value="<?= (int)$dev['id'] ?>">

                  <div class="dv-edit-grid">
                    <div class="dv-ef">
                      <label>Code</label>
                      <input type="text" name="code" class="dv-input" value="<?= e($dev['code']) ?>">
                    </div>

                    <div class="dv-ef">
                      <label>Name</label>
                      <input type="text" name="device_name" class="dv-input" value="<?= e($dev['device_name']) ?>">
                    </div>

                    <div class="dv-ef">
                      <label>Serial Number</label>
                      <input type="text" name="serial_number" class="dv-input" value="<?= e($dev['serial_number']) ?>">
                    </div>

                    <div class="dv-ef">
                      <label>Last Download</label>
                      <div class="dv-ef-value"><?= e(dtView($dev['last_download'])) ?></div>
                    </div>

                    <div class="dv-ef">
                      <label>Last Ping</label>
                      <div class="dv-ef-value"><?= e(dtView($dev['last_ping'])) ?></div>
                    </div>

                    <div class="dv-ef">
                      <label>Time Zone</label>
                      <select name="timezone" class="dv-select">
                        <?php foreach ($timezones as $tz): ?>
                          <option value="<?= e($tz) ?>" <?= ($dev['timezone'] === $tz) ? 'selected' : '' ?>><?= e($tz) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="dv-ef">
                      <label>Direction</label>
                      <select name="direction" class="dv-select">
                        <?php foreach ($directions as $d): ?>
                          <option value="<?= e($d) ?>" <?= ($dev['direction'] === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="dv-ef">
                      <label>Port Number</label>
                      <input type="text" name="port_no" class="dv-input" value="<?= e($dev['port_no']) ?>">
                    </div>

                    <div class="dv-ef">
                      <label>Connection Type</label>
                      <select name="connection_type" class="dv-select">
                        <?php foreach ($conn_types as $ct): ?>
                          <option value="<?= e($ct) ?>" <?= ($dev['connection_type'] === $ct) ? 'selected' : '' ?>><?= e($ct) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="dv-ef">
                      <label>Location</label>
                      <select name="location_id" class="dv-select">
                        <option value=""></option>
                        <?php foreach ($locations as $loc): ?>
                          <option value="<?= e($loc) ?>" <?= ($dev['location'] === $loc) ? 'selected' : '' ?>><?= e($loc) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="dv-ef">
                      <label>IP Address</label>
                      <input type="text" name="ip_address" class="dv-input" value="<?= e($dev['ip_address']) ?>">
                    </div>
                  </div>

                  <div class="dv-edit-actions">
                    <button type="button" class="btn-dv-delete" onclick="deleteDevice(<?= (int)$dev['id'] ?>)">Delete</button>
                    <button type="button" class="btn-dv-cancel" onclick="closeDevice(<?= (int)$dev['id'] ?>)">Cancel</button>
                    <button type="submit" class="btn-dv-save">Save</button>
                  </div>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($devices)): ?>
              <tr>
                <td colspan="6" style="text-align:center;padding:28px;color:#9ca3af">No devices found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php if ($flash): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
  showToast(<?= json_encode($flash) ?>, <?= json_encode($flash_type) ?>);
});
</script>
<?php endif; ?>

<script>
let openDevice = null;

function toggleDevice(id) {
  const row  = document.getElementById('drow-' + id);
  const edit = document.getElementById('dedit-' + id);
  if (!row || !edit) return;

  const isOpen = edit.classList.contains('show');

  document.querySelectorAll('.dv-edit-row.show').forEach(r => r.classList.remove('show'));
  document.querySelectorAll('.dv-row.expanded').forEach(r => r.classList.remove('expanded'));

  if (!isOpen) {
    edit.classList.add('show');
    row.classList.add('expanded');
    openDevice = id;
  } else {
    openDevice = null;
  }
}

function closeDevice(id) {
  event.stopPropagation();
  document.getElementById('drow-' + id)?.classList.remove('expanded');
  document.getElementById('dedit-' + id)?.classList.remove('show');
  openDevice = null;
}

function deleteDevice(id) {
  event.stopPropagation();

  if (!confirm('Delete this device?')) return;

  const f = document.createElement('form');
  f.method = 'POST';
  f.innerHTML = `
    <input type="hidden" name="action" value="delete_device">
    <input type="hidden" name="device_id" value="${id}">
  `;
  document.body.appendChild(f);
  f.submit();
}

const toastIcons = {
  success:'fa-circle-check',
  error:'fa-circle-xmark',
  warning:'fa-triangle-exclamation',
  info:'fa-circle-info'
};

function showToast(msg, type='success', dur=3500) {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');

  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fa-solid ${toastIcons[type]||toastIcons.info}"></i>
    <span>${msg}</span>
    <button class="toast-close" onclick="rmToast(this.parentElement)">
      <i class="fa-solid fa-xmark"></i>
    </button>`;

  c.appendChild(t);
  setTimeout(() => rmToast(t), dur);
}

function rmToast(el) {
  if (!el?.parentElement) return;
  el.style.animation = 'toastOut .25s ease forwards';
  setTimeout(() => el.remove(), 260);
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>