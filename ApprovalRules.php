<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

// Ensure DB connection is active
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$page_title = 'Approval Rules';

/* ─────────────────────────────────────────
   DATABASE SETUP (Runs once if table doesn't exist)
───────────────────────────────────────── */
$create_table = $conn->query("
CREATE TABLE IF NOT EXISTS `approval_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module` VARCHAR(100) NOT NULL,
    `module_name` VARCHAR(100) NOT NULL,
    `rule_name` VARCHAR(255) NOT NULL,
    `levels` TEXT NOT NULL,
    `auto_approve_days` INT DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

if (!$create_table) {
    die("Table creation failed: " . $conn->error);
}

// Failsafe: Ensure 'levels' column exists if table was created manually without it
$check_levels = $conn->query("SHOW COLUMNS FROM `approval_rules` LIKE 'levels'");
if ($check_levels && $check_levels->num_rows == 0) {
    $conn->query("ALTER TABLE `approval_rules` ADD `levels` TEXT NOT NULL AFTER `rule_name`");
}

/* ─────────────────────────────────────────
   HANDLE POST ACTIONS (Create, Update, Delete)
───────────────────────────────────────── */
$save_ok = false; 
$save_msg = '';
$mode = $_GET['mode'] ?? 'list'; // list | add | view | edit

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    
    // Process form data safely
    $module_name = $_POST['module_name'] ?? '';
    $module = strtolower(str_replace([' ', '/'], ['_', ''], $module_name)); // e.g. "Leave Request" -> "leave_request"
    $rule_name = $_POST['rule_name'] ?? '';
    $auto_approve_days = (int)($_POST['auto_approve_days'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Format levels safely into JSON
    $raw_levels = $_POST['levels'] ?? [];
    $levels = [];
    $lvl_count = 1;
    if (is_array($raw_levels)) {
        foreach ($raw_levels as $l) {
            $levels[] = [
                'level' => $lvl_count++,
                'approver' => $l['approver'] ?? '',
                'action' => $l['action'] ?? '',
                'notify' => isset($l['notify']) ? true : false
            ];
        }
    }
    $levels_json = json_encode($levels);

    if ($act === 'add') {
        $stmt = $conn->prepare("INSERT INTO approval_rules (module, module_name, rule_name, levels, auto_approve_days, is_active, is_deleted) VALUES (?, ?, ?, ?, ?, ?, 0)");
        if ($stmt) {
            $stmt->bind_param("ssssii", $module, $module_name, $rule_name, $levels_json, $auto_approve_days, $is_active);
            if($stmt->execute()) {
                $save_ok = true; 
                $save_msg = 'Approval rule added successfully!'; 
                $mode = 'list';
            } else {
                $save_ok = true; 
                $save_msg = 'Insert Error: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $save_ok = true; 
            $save_msg = 'Prepare Error: ' . $conn->error;
        }
    } 
    elseif ($act === 'save') {
        $rule_id = (int)($_POST['rule_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE approval_rules SET module=?, module_name=?, rule_name=?, levels=?, auto_approve_days=?, is_active=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssssiii", $module, $module_name, $rule_name, $levels_json, $auto_approve_days, $is_active, $rule_id);
            if($stmt->execute()) {
                $save_ok = true; 
                $save_msg = 'Approval rule updated successfully!';
                $mode = 'view'; // Return to view after saving
                $_GET['id'] = $rule_id; // maintain active view
            } else {
                $save_ok = true; 
                $save_msg = 'Update Error: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $save_ok = true; 
            $save_msg = 'Prepare Error: ' . $conn->error;
        }
    } 
    elseif ($act === 'delete') {
        $rule_id = (int)($_POST['rule_id'] ?? 0);
        // Using Soft Delete (Update is_deleted = 1)
        $stmt = $conn->prepare("UPDATE approval_rules SET is_deleted=1 WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("i", $rule_id);
            if($stmt->execute()) {
                $save_ok = true; 
                $save_msg = 'Approval rule deleted.'; 
                $mode = 'list';
                $_GET['id'] = null; // clear active id
            } else {
                $save_ok = true; 
                $save_msg = 'Delete Error: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $save_ok = true; 
            $save_msg = 'Prepare Error: ' . $conn->error;
        }
    }
}

/* ─────────────────────────────────────────
   DATA (Static lists & Dynamic DB Fetch)
───────────────────────────────────────── */
$modules_list = [
    'Attendance Regularization',
    'Leave Request',
    'Overtime',
    'Reimbursement',
    'Salary Revision',
    'Loan Request',
    'Exit / Resignation',
];

$action_options = ['Approve / Reject', 'Final Approve', 'Recommend', 'Notify Only'];

// Fetch Roles dynamically from database
$approver_roles = [];
$roles_query = $conn->query("SELECT role_name FROM user_roles WHERE is_deleted = 0 ORDER BY role_name ASC");
if ($roles_query) {
    while ($row = $roles_query->fetch_assoc()) {
        $approver_roles[] = trim($row['role_name']);
    }
}
// Fallback in case table is empty so it doesn't break the JS dropdown logic
if (empty($approver_roles)) {
    $approver_roles = ['Reporting Manager', 'HR Manager', 'Admin']; 
}

// Fetch all rules from database (Only active/non-deleted rules)
$approval_rules = [];
$res = $conn->query("SELECT * FROM approval_rules WHERE is_deleted = 0 ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['levels'] = json_decode($row['levels'], true) ?: [];
        $row['auto_approve_days'] = (int)$row['auto_approve_days'];
        $row['is_active'] = (bool)$row['is_active'];
        $approval_rules[] = $row;
    }
}

/* ── URL params ── */
$active_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$active_rule = null;
if ($active_id) {
    foreach ($approval_rules as $r) {
        if ($r['id'] === $active_id) { $active_rule = $r; break; }
    }
    if (!$active_rule && $mode !== 'add') $mode = 'list';
}

function esc($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ════════════════════════════════════════
   APPROVAL RULES PAGE
════════════════════════════════════════ */

/* config tab bar */
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #E5E7EB;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6B7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563EB;border-bottom-color:#2563EB;font-weight:600}

/* breadcrumb */
.ar-bc{display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:500;color:#374151}
.ar-bc a{color:#374151;text-decoration:none}
.ar-bc a:hover{color:#2563EB}
.ar-bc .sep{color:#D1D5DB;font-size:16px}
.ar-bc .cur{font-weight:600;color:#374151}

/* top-bar */
.ar-topbar{padding:14px 24px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}

/* col labels */
.ar-col-labels{display:grid;grid-template-columns:380px 1fr;padding:10px 0 10px 24px;font-size:12.5px;color:#9CA3AF;font-weight:500;border-bottom:1px solid #E5E7EB}

/* two-col layout */
.ar-layout{display:grid;grid-template-columns:380px 1fr;min-height:480px}

/* LEFT */
.ar-left{border-right:1px solid #E5E7EB;overflow-y:auto;max-height:560px}

/* search in left */
.ar-left-search{padding:12px 14px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;gap:8px;background:#fff;position:sticky;top:0;z-index:2}
.ar-left-search svg{width:14px;height:14px;stroke:#9CA3AF;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}
.ar-left-search input{border:none;outline:none;font-size:13px;font-family:inherit;color:#374151;background:transparent;width:100%}

.ar-rule-item{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:13px 18px;border-bottom:1px solid #F3F4F6;cursor:pointer;text-decoration:none;transition:background .15s}
.ar-rule-item:hover{background:#F9FAFB}
.ar-rule-item.active{background:#EFF6FF}
.ar-rule-item.active .ar-rule-name{color:#2563EB;font-weight:600}
.ar-rule-module{display:inline-flex;align-items:center;border-radius:20px;font-size:10.5px;font-weight:700;padding:2px 8px;margin-bottom:4px;background:#EDE9FE;color:#6D28D9;letter-spacing:.3px}
.ar-rule-name{font-size:13px;font-weight:500;color:#111827;margin-bottom:3px;line-height:1.4}
.ar-rule-meta{font-size:11.5px;color:#9CA3AF}
.ar-rule-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:6px}
.ar-rule-dot.on{background:#059669}
.ar-rule-dot.off{background:#D1D5DB}

/* RIGHT */
.ar-right{padding:28px 32px 32px}
.ar-right-title{font-size:14px;font-weight:700;color:#111827;letter-spacing:.4px;text-transform:uppercase;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}

/* edit link */
.ar-edit-link{display:inline-flex;align-items:center;gap:5px;font-size:13px;font-weight:500;color:#2563EB;cursor:pointer;text-decoration:none;border:none;background:none;font-family:inherit;padding:0;transition:color .15s}
.ar-edit-link:hover{color:#1D4ED8}
.ar-edit-link svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* underline fields */
.ar-fg{display:flex;flex-direction:column;gap:5px;margin-bottom:20px}
.ar-fg label{font-size:13px;color:#374151;font-weight:400;display:flex;align-items:center;gap:3px}
.ar-fg label .req{color:#DC2626}
.ar-fg input,.ar-fg select,.ar-fg textarea{border:none;border-bottom:1.5px solid #D1D5DB;border-radius:0;padding:8px 0;font-size:14px;font-family:inherit;color:#111827;outline:none;background:transparent;width:100%;max-width:480px;transition:border-color .15s}
.ar-fg input:focus,.ar-fg select:focus,.ar-fg textarea:focus{border-bottom-color:#2563EB}
.ar-fg input::placeholder{color:#C4C9D4}

/* row grid */
.ar-row-2{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px}
.ar-row-2 .ar-fg{margin-bottom:0}

/* view val */
.ar-view-val{font-size:14px;color:#111827;font-weight:500;padding-bottom:6px;border-bottom:1.5px solid #E5E7EB;min-height:28px;max-width:480px}

/* level badge */
.ar-level-badge{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#EDE9FE;color:#6D28D9;font-size:11px;font-weight:700;flex-shrink:0}

/* levels section */
.ar-levels-title{font-size:13px;font-weight:700;color:#374151;letter-spacing:.3px;text-transform:uppercase;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #E5E7EB}

/* level row (view) */
.ar-level-view-row{display:flex;align-items:center;gap:12px;padding:11px 14px;border:1px solid #E5E7EB;border-radius:9px;margin-bottom:8px;background:#FAFBFC}

/* level row (edit/add) */
.ar-level-edit-row{display:grid;grid-template-columns:24px 1fr 1fr auto auto;gap:10px;align-items:center;margin-bottom:10px}
.ar-level-edit-row select,.ar-level-edit-row input{padding:8px 10px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;font-family:inherit;color:#374151;outline:none;background:#fff;transition:border-color .15s;max-width:none}
.ar-level-edit-row select:focus,.ar-level-edit-row input:focus{border-color:#2563EB}

/* toggle in level row */
.ar-notify-toggle{position:relative;width:36px;height:20px;flex-shrink:0}
.ar-notify-toggle input{opacity:0;width:0;height:0}
.ar-notify-sl{position:absolute;inset:0;background:#D1D5DB;border-radius:10px;cursor:pointer;transition:.2s}
.ar-notify-toggle input:checked+.ar-notify-sl{background:#2563EB}
.ar-notify-sl::after{content:'';position:absolute;width:14px;height:14px;background:#fff;border-radius:50%;top:3px;left:3px;transition:.2s}
.ar-notify-toggle input:checked+.ar-notify-sl::after{transform:translateX(16px)}

/* remove / add level buttons */
.ar-level-del{width:28px;height:28px;border-radius:6px;border:1.5px solid #FEE2E2;background:#FFF5F5;color:#DC2626;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;transition:.15s;flex-shrink:0}
.ar-level-del:hover{background:#FEE2E2}
.ar-add-level-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1.5px dashed #2563EB;border-radius:8px;font-size:13px;font-weight:600;color:#2563EB;cursor:pointer;background:none;font-family:inherit;transition:.15s;margin-top:4px}
.ar-add-level-btn:hover{background:#EFF6FF}

/* auto-approve row */
.ar-auto-row{display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.ar-auto-row label{font-size:13.5px;color:#374151;font-weight:400}
.ar-auto-row input[type=number]{width:70px;padding:7px 10px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13.5px;font-family:inherit;color:#111827;outline:none;transition:border-color .15s}
.ar-auto-row input[type=number]:focus{border-color:#2563EB}

/* status toggle row */
.ar-status-row{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.ar-status-lbl{font-size:13.5px;color:#374151;font-weight:400}
.ar-status-toggle{position:relative;width:42px;height:24px}
.ar-status-toggle input{opacity:0;width:0;height:0}
.ar-status-sl{position:absolute;inset:0;background:#D1D5DB;border-radius:12px;cursor:pointer;transition:.2s}
.ar-status-toggle input:checked+.ar-status-sl{background:#059669}
.ar-status-sl::after{content:'';position:absolute;width:18px;height:18px;background:#fff;border-radius:50%;top:3px;left:3px;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.ar-status-toggle input:checked+.ar-status-sl::after{transform:translateX(18px)}

/* action buttons */
.ar-actions{display:flex;justify-content:flex-end;gap:10px;padding-top:20px;margin-top:8px;border-top:1px solid #E5E7EB}
.ar-cancel-btn{padding:9px 24px;background:#fff;color:#374151;border:1.5px solid #D1D5DB;border-radius:8px;font-size:13.5px;font-weight:500;cursor:pointer;font-family:inherit;transition:.15s;text-decoration:none;display:inline-flex;align-items:center}
.ar-cancel-btn:hover{border-color:#374151}
.ar-save-btn{padding:9px 28px;background:#2563EB;color:#fff;border:none;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .15s}
.ar-save-btn:hover{background:#1D4ED8}
.ar-delete-btn{padding:9px 20px;background:#fff;color:#DC2626;border:1.5px solid #DC2626;border-radius:8px;font-size:13.5px;font-weight:500;cursor:pointer;font-family:inherit;transition:.15s}
.ar-delete-btn:hover{background:#FEE2E2}

/* empty */
.ar-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:320px;color:#9CA3AF;font-size:13.5px;gap:12px;text-align:center}
.ar-empty svg{width:48px;height:48px;stroke:#D1D5DB;fill:none;stroke-width:1.5;stroke-linecap:round}

/* section block */
.ar-section-block{border:1px solid #E5E7EB;border-radius:10px;overflow:hidden;margin-bottom:20px}
.ar-section-block-head{padding:12px 16px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;font-size:12.5px;font-weight:700;color:#374151;letter-spacing:.3px;text-transform:uppercase}
.ar-section-block-body{padding:16px}

/* toast */
.ar-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);background:#111827;color:#fff;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:500;z-index:999;display:flex;align-items:center;gap:8px;box-shadow:0 8px 28px rgba(0,0,0,.2);transition:transform .3s ease;white-space:nowrap}
.ar-toast.show{transform:translateX(-50%) translateY(0)}

/* badge */
.ar-badge{display:inline-flex;align-items:center;border-radius:20px;font-size:11.5px;font-weight:600;padding:3px 10px}

/* responsive */
@media(max-width:960px){.ar-layout,.ar-col-labels{grid-template-columns:1fr}.ar-left{border-right:none;border-bottom:1px solid #E5E7EB;max-height:none}}
@media(max-width:640px){.ar-row-2{grid-template-columns:1fr}.ar-level-edit-row{grid-template-columns:24px 1fr;row-gap:8px}.ar-right{padding:18px 16px}}
</style>

<?php if($save_msg): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ arToast('<?= $save_ok ? '✅' : '❌' ?>','<?= esc($save_msg) ?>'); });</script>
<?php endif; ?>

<!-- ════════════════════════════════════════
    CONFIG TAB BAR
════════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">

    <div class="cfg-tabs">
        <?php foreach(['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
        <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k==='Others'?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>

    <!-- top bar -->
    <div class="ar-topbar">
        <div class="ar-bc">
            <a href="configuration#Others">Others</a>
            <span class="sep">›</span>
            <span class="cur">Approval Rules</span>
        </div>
        <a href="?mode=add" class="btn btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Approval Rule
        </a>
    </div>

    <!-- col labels -->
    <div class="ar-col-labels">
        <span>List of Approval Rules</span>
        <span style="padding-left:32px">Rule Details</span>
    </div>

    <!-- layout -->
    <div class="ar-layout">

        <!-- ════ LEFT ════ -->
        <div class="ar-left">
            <div class="ar-left-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="arSearch" placeholder="Search rules..." oninput="filterArList(this.value)">
            </div>

            <div id="arListItems">
            <?php foreach($approval_rules as $r): ?>
            <a href="?id=<?= $r['id'] ?>&mode=view"
               class="ar-rule-item <?= ($active_id===$r['id']&&$mode!=='add')?'active':'' ?>"
               data-search="<?= strtolower(esc($r['rule_name'])).' '.strtolower(esc($r['module_name'])) ?>">
                <div>
                    <div class="ar-rule-module"><?= esc($r['module_name']) ?></div>
                    <div class="ar-rule-name"><?= esc($r['rule_name']) ?></div>
                    <div class="ar-rule-meta"><?= count($r['levels']) ?> approval level<?= count($r['levels'])>1?'s':'' ?> · Auto-approve after <?= $r['auto_approve_days'] ?> day<?= $r['auto_approve_days']>1?'s':'' ?></div>
                </div>
                <div class="ar-rule-dot <?= $r['is_active']?'on':'off' ?>"></div>
            </a>
            <?php endforeach; ?>
            <?php if(empty($approval_rules)): ?>
                <div style="padding:20px; text-align:center; color:#9CA3AF; font-size:13px;">No rules found.</div>
            <?php endif; ?>
            </div>
        </div>

        <!-- ════ RIGHT ════ -->
        <div class="ar-right">

        <?php /* ── ADD ── */ if($mode==='add'): ?>

        <div class="ar-right-title">ADD APPROVAL RULE</div>

        <form method="POST" id="arAddForm" novalidate>
        <input type="hidden" name="_action" value="add">

        <div class="ar-row-2">
            <div class="ar-fg">
                <label><span class="req">* </span>Module</label>
                <select name="module_name" required>
                    <option value="">-- Select Module --</option>
                    <?php foreach($modules_list as $m): ?>
                    <option value="<?= esc($m) ?>"><?= esc($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ar-fg">
                <label><span class="req">* </span>Rule Name</label>
                <input type="text" name="rule_name" placeholder="e.g. Leave Approval Flow" required>
            </div>
        </div>

        <!-- Approval Levels -->
        <div class="ar-section-block">
            <div class="ar-section-block-head">Approval Levels</div>
            <div class="ar-section-block-body">
                <div style="display:grid;grid-template-columns:24px 1fr 1fr auto auto;gap:10px;align-items:center;margin-bottom:8px">
                    <span></span>
                    <span style="font-size:11px;font-weight:700;color:#9CA3AF;letter-spacing:.4px;text-transform:uppercase">APPROVER ROLE</span>
                    <span style="font-size:11px;font-weight:700;color:#9CA3AF;letter-spacing:.4px;text-transform:uppercase">ACTION</span>
                    <span style="font-size:11px;font-weight:700;color:#9CA3AF;letter-spacing:.4px;text-transform:uppercase">NOTIFY</span>
                    <span></span>
                </div>
                <div id="arLevelRows">
                    <!-- first row -->
                    <div class="ar-level-edit-row" id="arLevel-0">
                        <span class="ar-level-badge">1</span>
                        <select name="levels[0][approver]">
                            <?php foreach($approver_roles as $role): ?><option><?= esc($role) ?></option><?php endforeach; ?>
                        </select>
                        <select name="levels[0][action]">
                            <?php foreach($action_options as $ao): ?><option><?= esc($ao) ?></option><?php endforeach; ?>
                        </select>
                        <label class="ar-notify-toggle" title="Notify">
                            <input type="checkbox" name="levels[0][notify]" checked>
                            <span class="ar-notify-sl"></span>
                        </label>
                        <button type="button" class="ar-level-del" onclick="removeLevel(this)" title="Remove">×</button>
                    </div>
                </div>
                <button type="button" class="ar-add-level-btn" onclick="addLevel()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Level
                </button>
            </div>
        </div>

        <!-- Auto-approve -->
        <div class="ar-section-block">
            <div class="ar-section-block-head">Auto-Approve Settings</div>
            <div class="ar-section-block-body">
                <div class="ar-auto-row">
                    <label>Auto-approve if no action taken within</label>
                    <input type="number" name="auto_approve_days" value="3" min="0" max="30">
                    <label>days &nbsp;<span style="color:#9CA3AF;font-size:12px">(0 = disabled)</span></label>
                </div>
            </div>
        </div>

        <!-- Status -->
        <div class="ar-status-row">
            <span class="ar-status-lbl">Status:</span>
            <label class="ar-status-toggle" title="Toggle status">
                <input type="checkbox" name="is_active" checked>
                <span class="ar-status-sl"></span>
            </label>
            <span style="font-size:13px;color:#374151">Active</span>
        </div>

        <div class="ar-actions">
            <a href="?" class="ar-cancel-btn">Cancel</a>
            <button type="submit" class="ar-save-btn" onclick="return validateArForm('arAddForm')">Add Rule</button>
        </div>
        </form>

        <?php /* ── VIEW ── */ elseif($mode==='view' && $active_rule): ?>

        <div class="ar-right-title">
            <span><?= esc(strtoupper($active_rule['rule_name'])) ?></span>
            <a href="?id=<?= $active_id ?>&mode=edit" class="ar-edit-link">
                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Details
            </a>
        </div>

        <!-- Info fields -->
        <div class="ar-row-2">
            <div class="ar-fg">
                <label>Module</label>
                <div class="ar-view-val">
                    <span class="ar-badge" style="background:#EDE9FE;color:#6D28D9"><?= esc($active_rule['module_name']) ?></span>
                </div>
            </div>
            <div class="ar-fg">
                <label>Rule Name</label>
                <div class="ar-view-val"><?= esc($active_rule['rule_name']) ?></div>
            </div>
        </div>

        <!-- Approval Levels -->
        <div class="ar-section-block">
            <div class="ar-section-block-head">Approval Levels (<?= count($active_rule['levels']) ?>)</div>
            <div class="ar-section-block-body">
                <?php foreach($active_rule['levels'] as $lv): ?>
                <div class="ar-level-view-row">
                    <span class="ar-level-badge"><?= (int)$lv['level'] ?></span>
                    <div style="flex:1">
                        <div style="font-size:13.5px;font-weight:600;color:#111827"><?= esc($lv['approver']) ?></div>
                        <div style="font-size:12px;color:#9CA3AF;margin-top:1px"><?= esc($lv['action']) ?></div>
                    </div>
                    <div>
                        <span class="ar-badge" style="background:<?= $lv['notify']?'#D1FAE5':'#F3F4F6' ?>;color:<?= $lv['notify']?'#065F46':'#6B7280' ?>;font-size:11px">
                            <?= $lv['notify']?'● Notify':'○ No Notify' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Auto-approve -->
        <div class="ar-section-block">
            <div class="ar-section-block-head">Auto-Approve Settings</div>
            <div class="ar-section-block-body">
                <div style="font-size:13.5px;color:#374151">
                    <?php if($active_rule['auto_approve_days'] > 0): ?>
                    Auto-approve after <strong><?= $active_rule['auto_approve_days'] ?> day<?= $active_rule['auto_approve_days']>1?'s':'' ?></strong> of inaction.
                    <?php else: ?>
                    <span style="color:#9CA3AF">Auto-approve is disabled.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status -->
        <div class="ar-fg">
            <label>Status</label>
            <div class="ar-view-val">
                <span class="ar-badge" style="background:<?= $active_rule['is_active']?'#D1FAE5':'#F3F4F6' ?>;color:<?= $active_rule['is_active']?'#065F46':'#6B7280' ?>">
                    ● <?= $active_rule['is_active']?'Active':'Inactive' ?>
                </span>
            </div>
        </div>

        <?php /* ── EDIT ── */ elseif($mode==='edit' && $active_rule): ?>

        <div class="ar-right-title">EDIT APPROVAL RULE</div>

        <form method="POST" id="arEditForm" novalidate>
        <input type="hidden" name="_action" value="save">
        <input type="hidden" name="rule_id" value="<?= (int)$active_rule['id'] ?>">

        <div class="ar-row-2">
            <div class="ar-fg">
                <label><span class="req">* </span>Module</label>
                <select name="module_name" required>
                    <?php foreach($modules_list as $m): ?>
                    <option value="<?= esc($m) ?>" <?= $m===$active_rule['module_name']?'selected':'' ?>><?= esc($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ar-fg">
                <label><span class="req">* </span>Rule Name</label>
                <input type="text" name="rule_name" value="<?= esc($active_rule['rule_name']) ?>" required>
            </div>
        </div>

        <!-- Approval Levels -->
        <div class="ar-section-block">
            <div class="ar-section-block-head">Approval Levels</div>
            <div class="ar-section-block-body">
                <div style="display:grid;grid-template-columns:24px 1fr 1fr auto auto;gap:10px;align-items:center;margin-bottom:8px">
                    <span></span>
                    <span style="font-size:11px;font-weight:700;color:#9CA3AF;letter-spacing:.4px;text-transform:uppercase">APPROVER ROLE</span>
                    <span style="font-size:11px;font-weight:700;color:#9CA3AF;letter-spacing:.4px;text-transform:uppercase">ACTION</span>
                    <span style="font-size:11px;font-weight:700;color:#9CA3AF;letter-spacing:.4px;text-transform:uppercase">NOTIFY</span>
                    <span></span>
                </div>
                <div id="arLevelRows">
                <?php foreach($active_rule['levels'] as $li => $lv): ?>
                <div class="ar-level-edit-row" id="arLevel-<?= $li ?>">
                    <span class="ar-level-badge"><?= (int)$lv['level'] ?></span>
                    <select name="levels[<?= $li ?>][approver]">
                        <?php foreach($approver_roles as $role): ?>
                        <option <?= $role===$lv['approver']?'selected':'' ?>><?= esc($role) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="levels[<?= $li ?>][action]">
                        <?php foreach($action_options as $ao): ?>
                        <option <?= $ao===$lv['action']?'selected':'' ?>><?= esc($ao) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="ar-notify-toggle" title="Notify">
                        <input type="checkbox" name="levels[<?= $li ?>][notify]" <?= $lv['notify']?'checked':'' ?>>
                        <span class="ar-notify-sl"></span>
                    </label>
                    <button type="button" class="ar-level-del" onclick="removeLevel(this)" title="Remove">×</button>
                </div>
                <?php endforeach; ?>
                </div>
                <button type="button" class="ar-add-level-btn" onclick="addLevel()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Level
                </button>
            </div>
        </div>

        <!-- Auto-approve -->
        <div class="ar-section-block">
            <div class="ar-section-block-head">Auto-Approve Settings</div>
            <div class="ar-section-block-body">
                <div class="ar-auto-row">
                    <label>Auto-approve if no action taken within</label>
                    <input type="number" name="auto_approve_days" value="<?= (int)$active_rule['auto_approve_days'] ?>" min="0" max="30">
                    <label>days &nbsp;<span style="color:#9CA3AF;font-size:12px">(0 = disabled)</span></label>
                </div>
            </div>
        </div>

        <!-- Status -->
        <div class="ar-status-row">
            <span class="ar-status-lbl">Status:</span>
            <label class="ar-status-toggle" title="Toggle status">
                <input type="checkbox" name="is_active" <?= $active_rule['is_active']?'checked':'' ?>>
                <span class="ar-status-sl"></span>
            </label>
            <span style="font-size:13px;color:#374151"><?= $active_rule['is_active']?'Active':'Inactive' ?></span>
        </div>

        <div class="ar-actions">
            <!-- Delete form -->
            <form method="POST" style="display:inline" id="arDeleteForm">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="rule_id" value="<?= (int)$active_rule['id'] ?>">
                <button type="submit" class="ar-delete-btn"
                    onclick="return confirm('Delete this approval rule? This cannot be undone.')">Delete</button>
            </form>
            <a href="?id=<?= $active_id ?>&mode=view" class="ar-cancel-btn">Cancel</a>
            <button type="submit" class="ar-save-btn" onclick="return validateArForm('arEditForm')">Save Changes</button>
        </div>
        </form>

        <?php else: ?>
        <!-- default empty / no selection -->
        <div class="ar-empty">
            <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <p>Select an approval rule from the list<br>or click <strong>Add Approval Rule</strong> to create one.</p>
        </div>
        <?php endif; ?>

        </div><!-- ar-right -->
    </div><!-- ar-layout -->

</div><!-- section-card -->

<!-- toast -->
<div class="ar-toast" id="arToastEl">
    <span id="arToastIcon">✅</span><span id="arToastMsg">Done!</span>
</div>

<script>
/* ── Toast ── */
function arToast(icon, msg) {
    var t = document.getElementById('arToastEl');
    document.getElementById('arToastIcon').textContent = icon;
    document.getElementById('arToastMsg').textContent  = msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(function(){ t.classList.remove('show'); }, 3200);
}

/* ── Left list search ── */
function filterArList(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.ar-rule-item').forEach(function(item) {
        item.style.display = !q || (item.dataset.search||'').includes(q) ? '' : 'none';
    });
}

/* ── Level rows ── */
var approverRoles = <?= json_encode($approver_roles) ?>;
var actionOptions = <?= json_encode($action_options) ?>;
var levelCount    = document.querySelectorAll('.ar-level-edit-row').length;

function addLevel() {
    var container = document.getElementById('arLevelRows');
    if (!container) return;
    var idx = levelCount++;
    var lvNum = container.querySelectorAll('.ar-level-edit-row').length + 1;

    var roleOpts  = approverRoles.map(function(r){ return '<option value="'+r+'">'+r+'</option>'; }).join('');
    var actionOpts = actionOptions.map(function(a){ return '<option value="'+a+'">'+a+'</option>'; }).join('');

    var div = document.createElement('div');
    div.className = 'ar-level-edit-row';
    div.id = 'arLevel-' + idx;
    div.innerHTML =
        '<span class="ar-level-badge">'+lvNum+'</span>'
      + '<select name="levels['+idx+'][approver]">'+roleOpts+'</select>'
      + '<select name="levels['+idx+'][action]">'+actionOpts+'</select>'
      + '<label class="ar-notify-toggle" title="Notify"><input type="checkbox" name="levels['+idx+'][notify]" checked><span class="ar-notify-sl"></span></label>'
      + '<button type="button" class="ar-level-del" onclick="removeLevel(this)" title="Remove">×</button>';
    container.appendChild(div);
    renumberLevels();
}

function removeLevel(btn) {
    var container = document.getElementById('arLevelRows');
    var rows = container ? container.querySelectorAll('.ar-level-edit-row') : [];
    if (rows.length <= 1) { arToast('⚠', 'At least one approval level is required.'); return; }
    btn.closest('.ar-level-edit-row').remove();
    renumberLevels();
}

function renumberLevels() {
    var container = document.getElementById('arLevelRows');
    if (!container) return;
    container.querySelectorAll('.ar-level-edit-row').forEach(function(row, i) {
        var badge = row.querySelector('.ar-level-badge');
        if (badge) badge.textContent = i + 1;
    });
}

/* ── Validate ── */
function validateArForm(formId) {
    var form = document.getElementById(formId || 'arAddForm');
    if (!form) return true;
    var ok = true;
    form.querySelectorAll('[required]').forEach(function(el) {
        if (!el.value.trim()) {
            el.style.borderBottomColor = '#DC2626';
            ok = false;
        } else {
            el.style.borderBottomColor = '';
        }
    });
    if (!ok) { arToast('⚠', 'Please fill in all required fields.'); return false; }
    return true;
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>