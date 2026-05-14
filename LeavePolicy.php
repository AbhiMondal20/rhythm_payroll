<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Leave Policies';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

function e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function postInt($key) {
    return (int)($_POST[$key] ?? 0);
}

function postFloat($key) {
    return (float)($_POST[$key] ?? 0);
}

function postCheck($key) {
    return isset($_POST[$key]) ? 1 : 0;
}

function postStr($key, $default = '') {
    return trim($_POST[$key] ?? $default);
}

/* ================= DB TABLES ================= */

$conn->query("
CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leave_name VARCHAR(120) NOT NULL,
    leave_code VARCHAR(20) NOT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS leave_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(120) NOT NULL,
    policy_code VARCHAR(20) NOT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS leave_policy_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_id INT NOT NULL,
    leave_type_id INT NOT NULL,

    rule1_days INT DEFAULT 0,
    rule1_timing VARCHAR(20) DEFAULT 'Before',
    rule2_gt_days DECIMAL(6,1) DEFAULT 0,
    rule2_inform_days INT DEFAULT 0,
    rule2_timing VARCHAR(20) DEFAULT 'Before',
    rule3_max_days INT DEFAULT 0,
    rule3_min_days DECIMAL(6,1) DEFAULT 0,
    rule4_yearly_max INT DEFAULT 0,

    leave_paid TINYINT(1) DEFAULT 0,
    paid_times DECIMAL(6,1) DEFAULT 1,
    allow_attachment TINYINT(1) DEFAULT 0,
    attach_gt_days INT DEFAULT 0,

    hol_during TINYINT(1) DEFAULT 0,
    hol_after TINYINT(1) DEFAULT 0,
    hol_before TINYINT(1) DEFAULT 0,
    hol_optional_only TINYINT(1) DEFAULT 0,

    wkoff_during TINYINT(1) DEFAULT 0,
    wkoff_after TINYINT(1) DEFAULT 0,
    wkoff_before TINYINT(1) DEFAULT 0,

    accum_auto TINYINT(1) DEFAULT 0,
    accum_basis VARCHAR(30) DEFAULT 'joining',
    accum_starts_after INT DEFAULT 0,
    accum_leaves DECIMAL(6,1) DEFAULT 0,
    accum_every VARCHAR(30) DEFAULT '',
    accum_on VARCHAR(30) DEFAULT '',
    accum_max_balance INT DEFAULT 0,
    accum_max_year INT DEFAULT 0,
    accum_max_neg INT DEFAULT 0,
    accum_carry_fwd INT DEFAULT 0,
    remaining_leaves VARCHAR(30) DEFAULT 'Lapsed',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_policy_leave_type (policy_id, leave_type_id),
    INDEX idx_policy_id (policy_id),
    INDEX idx_leave_type_id (leave_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ================= DUMMY LEAVE TYPES ================= */

$typeCount = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM leave_types");
if ($res) {
    $typeCount = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($typeCount === 0) {
    $dummyTypes = [
        ['Loss of Pay', 'LOP', ''],
        ['Compensatory Leave', 'Compoff', ''],
        ['Casual Leave/Sick Leave', 'CLSL', ''],
        ['Maternity Leave', 'ML', ''],
        ['Paternity Leave', 'PL', '']
    ];

    $stmt = $conn->prepare("INSERT INTO leave_types (leave_name, leave_code, remarks) VALUES (?, ?, ?)");
    if ($stmt) {
        foreach ($dummyTypes as $t) {
            $stmt->bind_param("sss", $t[0], $t[1], $t[2]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

/* ================= DUMMY POLICY ================= */

$policyCount = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM leave_policies");
if ($res) {
    $policyCount = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($policyCount === 0) {
    $stmt = $conn->prepare("INSERT INTO leave_policies (policy_name, policy_code, remarks) VALUES (?, ?, ?)");
    if ($stmt) {
        $pname = 'General Structure';
        $pcode = 'GEN';
        $prem  = 'Leave Structure';
        $stmt->bind_param("sss", $pname, $pcode, $prem);
        $stmt->execute();
        $stmt->close();
    }
}

/* ================= SEED DEFAULT RULES ================= */

$generalPolicyId = 0;
$res = $conn->query("SELECT id FROM leave_policies WHERE policy_code='GEN' ORDER BY id ASC LIMIT 1");
if ($res && $res->num_rows > 0) {
    $generalPolicyId = (int)$res->fetch_assoc()['id'];
}

$ruleCount = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM leave_policy_rules");
if ($res) {
    $ruleCount = (int)($res->fetch_assoc()['total'] ?? 0);
}

if ($generalPolicyId > 0 && $ruleCount === 0) {
    $typesMap = [];
    $res = $conn->query("SELECT id, leave_code FROM leave_types");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $typesMap[$r['leave_code']] = (int)$r['id'];
        }
    }

    $dummyRules = [
        ['LOP', 31, 'After', 0, 0, 'After', 365, 0.5, 365, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0],
        ['Compoff', 35, 'After', 1, 35, 'After', 5, 1, 48, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ['CLSL', 35, 'After', 0.5, 35, 'After', 5, 1, 30, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0]
    ];

    $stmt = $conn->prepare("
        INSERT IGNORE INTO leave_policy_rules
        (policy_id, leave_type_id, rule1_days, rule1_timing, rule2_gt_days, rule2_inform_days, rule2_timing,
         rule3_max_days, rule3_min_days, rule4_yearly_max, leave_paid, paid_times, allow_attachment, attach_gt_days,
         hol_during, hol_after, hol_before, hol_optional_only, wkoff_during, wkoff_after, wkoff_before)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        foreach ($dummyRules as $d) {
            $ltid = $typesMap[$d[0]] ?? 0;
            if ($ltid <= 0) continue;

            $stmt->bind_param(
                "iiisdisididiiiiiiiiii",
                $generalPolicyId,
                $ltid,
                $d[1],
                $d[2],
                $d[3],
                $d[4],
                $d[5],
                $d[6],
                $d[7],
                $d[8],
                $d[9],
                $d[10],
                $d[11],
                $d[12],
                $d[13],
                $d[14],
                $d[15],
                $d[16],
                $d[17],
                $d[18],
                $d[19]
            );
            $stmt->execute();
        }
        $stmt->close();
    }
}

/* ================= STATE ================= */

$active_policy_id = (int)($_GET['policy_id'] ?? 0);
$active_rule_id   = (int)($_GET['rule_id'] ?? 0);
$mode             = $_GET['mode'] ?? 'policy_list';
$active_tab       = $_GET['tab'] ?? 'rules';
$flash            = '';
$flash_type       = 'success';

/* ================= POST HANDLERS ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_policy') {
        $name    = postStr('policy_name');
        $code    = postStr('policy_code');
        $remarks = postStr('remarks');

        if ($name === '' || $code === '') {
            $flash = 'Name and Code required.';
            $flash_type = 'error';
            $mode = 'add_policy';
        } else {
            $stmt = $conn->prepare("INSERT INTO leave_policies (policy_name, policy_code, remarks) VALUES (?, ?, ?)");
            if (!$stmt) {
                $flash = 'Save failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'add_policy';
            } else {
                $stmt->bind_param("sss", $name, $code, $remarks);
                if ($stmt->execute()) {
                    $active_policy_id = (int)$stmt->insert_id;
                    $flash = 'Policy "' . $name . '" added.';
                    $flash_type = 'success';
                    $mode = 'policy_list';
                } else {
                    $flash = 'Save failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'add_policy';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'save_policy') {
        $id      = postInt('policy_id');
        $name    = postStr('policy_name');
        $code    = postStr('policy_code');
        $remarks = postStr('remarks');

        if ($id <= 0 || $name === '' || $code === '') {
            $flash = 'Name and Code required.';
            $flash_type = 'error';
            $mode = 'edit_policy';
            $active_policy_id = $id;
        } else {
            $stmt = $conn->prepare("
                UPDATE leave_policies
                SET policy_name=?, policy_code=?, remarks=?, updated_at=NOW()
                WHERE id=?
            ");

            if (!$stmt) {
                $flash = 'Update failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'edit_policy';
            } else {
                $stmt->bind_param("sssi", $name, $code, $remarks, $id);
                if ($stmt->execute()) {
                    $flash = 'Policy updated.';
                    $flash_type = 'success';
                    $active_policy_id = $id;
                    $mode = 'policy_list';
                } else {
                    $flash = 'Update failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'edit_policy';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'delete_policy') {
        $pid = postInt('policy_id');

        if ($pid > 0) {
            $stmt = $conn->prepare("DELETE FROM leave_policy_rules WHERE policy_id=?");
            if ($stmt) {
                $stmt->bind_param("i", $pid);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $conn->prepare("DELETE FROM leave_policies WHERE id=?");
            if ($stmt) {
                $stmt->bind_param("i", $pid);
                if ($stmt->execute()) {
                    $flash = 'Policy deleted.';
                    $flash_type = 'success';
                    $active_policy_id = 0;
                    $mode = 'policy_list';
                } else {
                    $flash = 'Delete failed: ' . $stmt->error;
                    $flash_type = 'error';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'add_rule' || $action === 'save_rule') {
        $policy_id = postInt('policy_id');
        $rule_id   = postInt('rule_id');
        $lt_id     = postInt('leave_type_id');

        $rule1_days        = postInt('rule1_days');
        $rule1_timing      = postStr('rule1_timing', 'Before');
        $rule2_gt_days     = postFloat('rule2_gt_days');
        $rule2_inform_days = postInt('rule2_inform_days');
        $rule2_timing      = postStr('rule2_timing', 'Before');
        $rule3_max_days    = postInt('rule3_max_days');
        $rule3_min_days    = postFloat('rule3_min_days');
        $rule4_yearly_max  = postInt('rule4_yearly_max');

        $leave_paid       = postCheck('leave_paid');
        $paid_times       = postFloat('paid_times');
        $allow_attachment = postCheck('allow_attachment');
        $attach_gt_days   = postInt('attach_gt_days');

        $hol_during        = postCheck('hol_during');
        $hol_after         = postCheck('hol_after');
        $hol_before        = postCheck('hol_before');
        $hol_optional_only = postCheck('hol_optional_only');

        $wkoff_during = postCheck('wkoff_during');
        $wkoff_after  = postCheck('wkoff_after');
        $wkoff_before = postCheck('wkoff_before');

        $accum_auto         = postCheck('accum_auto');
        $accum_basis        = postStr('accum_basis', 'joining');
        $accum_starts_after = postInt('accum_starts_after');
        $accum_leaves       = postFloat('accum_leaves');
        $accum_every        = postStr('accum_every');
        $accum_on           = postStr('accum_on');
        $accum_max_balance  = postInt('accum_max_balance');
        $accum_max_year     = postInt('accum_max_year');
        $accum_max_neg      = postInt('accum_max_neg');
        $accum_carry_fwd    = postInt('accum_carry_fwd');
        $remaining_leaves   = postStr('remaining_leaves', 'Lapsed');

        if ($policy_id <= 0 || $lt_id <= 0) {
            $flash = 'Please select a leave name.';
            $flash_type = 'error';
            $mode = ($action === 'save_rule') ? 'edit_rule' : 'add_rule';
            $active_policy_id = $policy_id;
            $active_rule_id = $rule_id;
        } else {
            if ($action === 'add_rule') {
                $stmt = $conn->prepare("
                    INSERT INTO leave_policy_rules
                    (policy_id, leave_type_id, rule1_days, rule1_timing, rule2_gt_days, rule2_inform_days,
                     rule2_timing, rule3_max_days, rule3_min_days, rule4_yearly_max,
                     leave_paid, paid_times, allow_attachment, attach_gt_days,
                     hol_during, hol_after, hol_before, hol_optional_only,
                     wkoff_during, wkoff_after, wkoff_before,
                     accum_auto, accum_basis, accum_starts_after, accum_leaves, accum_every, accum_on,
                     accum_max_balance, accum_max_year, accum_max_neg, accum_carry_fwd, remaining_leaves)
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                     ?, ?, ?, ?,
                     ?, ?, ?, ?,
                     ?, ?, ?,
                     ?, ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?)
                ");

                if (!$stmt) {
                    $flash = 'Rule save failed: ' . $conn->error;
                    $flash_type = 'error';
                    $mode = 'add_rule';
                } else {
                    $stmt->bind_param(
    "iiisdisidiidiiiiiiiiiisidssiiiis",
    $policy_id,
    $lt_id,
    $rule1_days,
    $rule1_timing,
    $rule2_gt_days,
    $rule2_inform_days,
    $rule2_timing,
    $rule3_max_days,
    $rule3_min_days,
    $rule4_yearly_max,
    $leave_paid,
    $paid_times,
    $allow_attachment,
    $attach_gt_days,
    $hol_during,
    $hol_after,
    $hol_before,
    $hol_optional_only,
    $wkoff_during,
    $wkoff_after,
    $wkoff_before,
    $accum_auto,
    $accum_basis,
    $accum_starts_after,
    $accum_leaves,
    $accum_every,
    $accum_on,
    $accum_max_balance,
    $accum_max_year,
    $accum_max_neg,
    $accum_carry_fwd,
    $remaining_leaves
);

                    if ($stmt->execute()) {
                        $active_rule_id = (int)$stmt->insert_id;
                        $flash = 'Leave rule added.';
                        $flash_type = 'success';
                        $mode = 'gs_view';
                    } else {
                        $flash = 'Rule save failed: ' . $stmt->error;
                        $flash_type = 'error';
                        $mode = 'add_rule';
                    }
                    $stmt->close();
                }
            } else {
                $stmt = $conn->prepare("
                    UPDATE leave_policy_rules
                    SET leave_type_id=?,
                        rule1_days=?, rule1_timing=?, rule2_gt_days=?, rule2_inform_days=?,
                        rule2_timing=?, rule3_max_days=?, rule3_min_days=?, rule4_yearly_max=?,
                        leave_paid=?, paid_times=?, allow_attachment=?, attach_gt_days=?,
                        hol_during=?, hol_after=?, hol_before=?, hol_optional_only=?,
                        wkoff_during=?, wkoff_after=?, wkoff_before=?,
                        accum_auto=?, accum_basis=?, accum_starts_after=?, accum_leaves=?, accum_every=?, accum_on=?,
                        accum_max_balance=?, accum_max_year=?, accum_max_neg=?, accum_carry_fwd=?, remaining_leaves=?,
                        updated_at=NOW()
                    WHERE id=? AND policy_id=?
                ");

                if (!$stmt) {
                    $flash = 'Rule update failed: ' . $conn->error;
                    $flash_type = 'error';
                    $mode = 'edit_rule';
                } else {
                    $stmt->bind_param(
                            "iisdisidiidiiiiiiiiiisidssiiiisii",
                            $lt_id,
                            $rule1_days,
                            $rule1_timing,
                            $rule2_gt_days,
                            $rule2_inform_days,
                            $rule2_timing,
                            $rule3_max_days,
                            $rule3_min_days,
                            $rule4_yearly_max,
                            $leave_paid,
                            $paid_times,
                            $allow_attachment,
                            $attach_gt_days,
                            $hol_during,
                            $hol_after,
                            $hol_before,
                            $hol_optional_only,
                            $wkoff_during,
                            $wkoff_after,
                            $wkoff_before,
                            $accum_auto,
                            $accum_basis,
                            $accum_starts_after,
                            $accum_leaves,
                            $accum_every,
                            $accum_on,
                            $accum_max_balance,
                            $accum_max_year,
                            $accum_max_neg,
                            $accum_carry_fwd,
                            $remaining_leaves,
                            $rule_id,
                            $policy_id
                        );

                    if ($stmt->execute()) {
                        $active_rule_id = $rule_id;
                        $flash = 'Leave rule saved.';
                        $flash_type = 'success';
                        $mode = 'gs_view';
                    } else {
                        $flash = 'Rule update failed: ' . $stmt->error;
                        $flash_type = 'error';
                        $mode = 'edit_rule';
                    }
                    $stmt->close();
                }
            }

            $active_policy_id = $policy_id;
        }
    }

    if ($action === 'delete_rule') {
        $rid = postInt('rule_id');
        $pid = postInt('policy_id');

        if ($rid > 0) {
            $stmt = $conn->prepare("DELETE FROM leave_policy_rules WHERE id=?");
            if ($stmt) {
                $stmt->bind_param("i", $rid);
                if ($stmt->execute()) {
                    $flash = 'Leave rule deleted.';
                    $flash_type = 'success';
                } else {
                    $flash = 'Delete failed: ' . $stmt->error;
                    $flash_type = 'error';
                }
                $stmt->close();
            }
        }

        $active_policy_id = $pid;
        $active_rule_id = 0;
        $mode = 'gs_view';
    }
}

/* ================= FETCH DATA ================= */

$policies = [];
$res = $conn->query("SELECT * FROM leave_policies ORDER BY policy_name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $policies[] = $row;
    }
}

$leave_types = [];
$res = $conn->query("SELECT id, leave_name, leave_code FROM leave_types ORDER BY leave_name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $leave_types[] = $row;
    }
}

if ($active_policy_id === 0 && count($policies)) {
    $active_policy_id = (int)$policies[0]['id'];
}

$active_policy = null;
foreach ($policies as $p) {
    if ((int)$p['id'] === (int)$active_policy_id) {
        $active_policy = $p;
        break;
    }
}

$rules = [];
$stmt = $conn->prepare("
    SELECT r.*, lt.leave_name, lt.leave_code
    FROM leave_policy_rules r
    JOIN leave_types lt ON lt.id = r.leave_type_id
    WHERE r.policy_id = ?
    ORDER BY lt.leave_name ASC
");

if ($stmt) {
    $stmt->bind_param("i", $active_policy_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['policy_id'] = (int)$row['policy_id'];
        $row['leave_type_id'] = (int)$row['leave_type_id'];
        $rules[] = $row;
    }

    $stmt->close();
}

$active_rule = null;
foreach ($rules as $r) {
    if ((int)$r['id'] === (int)$active_rule_id) {
        $active_rule = $r;
        break;
    }
}

if (!$active_rule && count($rules)) {
    $active_rule = $rules[0];
    $active_rule_id = (int)$active_rule['id'];
}

$accum_every_opts = ['Monthly', 'Quarterly', 'Half Yearly', 'Yearly'];
$remain_opts = ['Lapsed', 'Carried Forward'];

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ── Config nav tabs ── */
.cfg-tabs{display:flex;align-items:center;border-bottom:1px solid #e5e7eb;background:#fff;overflow-x:auto;scrollbar-width:none}
.cfg-tabs::-webkit-scrollbar{display:none}
.cfg-tab{padding:14px 20px;font-size:13.5px;font-weight:500;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color .15s,border-color .15s;text-decoration:none;display:block;margin-bottom:-1px}
.cfg-tab:hover{color:#111827}
.cfg-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}

/* ── Page shell ── */
.lp-wrapper{font-family:'Segoe UI',sans-serif;color:#1e2d3d;padding:0 0 40px}
.lp-inner{padding:18px 24px}

/* breadcrumb */
.lp-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.lp-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#555}
.lp-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.lp-breadcrumb a:hover{text-decoration:underline}
.lp-breadcrumb .sep{color:#bbb;font-size:11px}

/* instructions */
.lp-instructions{font-size:13px;color:#374151;line-height:1.8;margin-bottom:18px}
.lp-instructions strong{color:#1e2d3d}

/* sub-header */
.lp-sub-header{display:grid;grid-template-columns:280px 1fr;border-bottom:1px solid #e8ecf0}
.lp-sub-left{padding:10px 16px;font-size:12px;color:#6b7280;font-weight:500}
.lp-sub-right{padding:10px 16px;font-size:12px;color:#6b7280;font-weight:500}

/* ── Split panel ── */
.lp-panel{display:flex;background:#fff;border:1px solid #e8ecf0;border-radius:10px;overflow:hidden;min-height:480px}

/* left list */
.lp-list-col{width:280px;min-width:220px;border-right:1px solid #e8ecf0;display:flex;flex-direction:column}
.lp-list-scroll{flex:1;overflow-y:auto;max-height:580px}
.lp-item{padding:13px 16px;border-bottom:1px solid #f1f4f8;cursor:pointer;display:flex;align-items:center;justify-content:space-between;transition:background .12s}
.lp-item:last-child{border-bottom:none}
.lp-item:hover{background:#f8fafc}
.lp-item.active{background:#eff6ff;border-left:3px solid #2563eb;padding-left:13px}
.lp-item-name{font-size:13.5px;font-weight:500;color:#1e2d3d}
.lp-item.active .lp-item-name{color:#2563eb;font-weight:700}
.lp-item-chevron{font-size:11px;color:#9ca3af}

/* right detail */
.lp-detail-col{flex:1;padding:22px 26px;display:flex;flex-direction:column;overflow-y:auto;max-height:700px}

.lp-detail-title-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.lp-detail-title{font-size:15px;font-weight:800;color:#1e2d3d;text-transform:uppercase;letter-spacing:.3px}
.btn-edit-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:600;padding:0}
.btn-edit-link:hover{text-decoration:underline}

/* field row */
.lp-field-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 36px;margin-bottom:14px}
.lp-field-grid.single{grid-template-columns:1fr}
.lp-field label{display:block;font-size:12px;color:#6b7280;margin-bottom:5px;font-weight:500}
.lp-field label .req{color:#ef4444;margin-right:2px}
.req{color:#ef4444;margin-right:2px}
.lp-field-value{font-size:13.5px;color:#1e2d3d;padding-bottom:8px;border-bottom:1px solid #e2e8f0;min-height:24px}
.lp-input{width:100%;border:none;border-bottom:1.5px solid #d1d5db;padding:7px 2px;font-size:13.5px;color:#1e2d3d;background:transparent;outline:none;box-sizing:border-box;transition:border-color .16s}
.lp-input::placeholder{color:#c4c9d4}
.lp-input:focus{border-color:#2563eb}

/* leave type cards */
.lt-cards-section{margin-top:16px}
.lt-cards-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.lt-cards-label{font-size:13.5px;font-weight:600;color:#1e2d3d}
.btn-add-rule{display:inline-flex;align-items:center;gap:5px;font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:600;padding:0}
.btn-add-rule:hover{text-decoration:underline}

.lt-cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.lt-card{background:#fff;border:1px solid #e8ecf0;border-radius:8px;padding:16px 18px;position:relative}
.lt-card-code{font-size:13px;font-weight:700;color:#374151;margin-bottom:4px}
.lt-card-name{font-size:12.5px;color:#6b7280;margin-top:8px;font-weight:500}
.lt-card-actions{position:absolute;top:12px;right:12px;display:flex;gap:8px}
.btn-card-link{background:none;border:none;cursor:pointer;color:#2563eb;font-size:14px;padding:2px}
.btn-card-link:hover{color:#1d4ed8}
.btn-card-del{background:none;border:none;cursor:pointer;color:#ef4444;font-size:14px;padding:2px}
.btn-card-del:hover{color:#dc2626}

/* ══════════ GENERAL STRUCTURE (leave rules) page ══════════ */
.gs-wrapper{padding:0}
.gs-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13.5px;margin-bottom:16px}
.gs-breadcrumb a{color:#1e2d3d;text-decoration:none;font-weight:600}
.gs-breadcrumb a:hover{text-decoration:underline}
.gs-breadcrumb .sep{color:#bbb;font-size:11px}

/* left panel in GS view */
.gs-left-item{padding:13px 16px;border-bottom:1px solid #f1f4f8;cursor:pointer;display:flex;align-items:center;justify-content:space-between;transition:background .12s}
.gs-left-item:last-child{border-bottom:none}
.gs-left-item:hover{background:#f8fafc}
.gs-left-item.active{background:#eff6ff;border-left:3px solid #2563eb;padding-left:13px}
.gs-left-name{font-size:13.5px;font-weight:500;color:#1e2d3d}
.gs-left-item.active .gs-left-name{color:#2563eb;font-weight:700}

/* right side of GS */
.gs-right{flex:1;padding:20px 26px;overflow-y:auto;max-height:780px}

/* leave detail header */
.gs-detail-name{font-size:15px;font-weight:700;color:#1e2d3d;display:flex;align-items:center;gap:10px}
.gs-edit-icon{color:#2563eb;cursor:pointer;font-size:14px}
.gs-accum-btn{font-size:13px;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:600;padding:0;margin-left:auto}
.gs-accum-btn:hover{text-decoration:underline}

/* tabs */
.gs-tabs{display:flex;border-bottom:1px solid #e8ecf0;margin:14px 0 18px}
.gs-tab{padding:10px 0;margin-right:24px;font-size:13.5px;color:#6b7280;font-weight:500;cursor:pointer;border-bottom:2.5px solid transparent;background:none;border-top:none;border-left:none;border-right:none;transition:color .15s}
.gs-tab.active{color:#2563eb;border-bottom-color:#2563eb;font-weight:600}

/* rules card */
.gs-rules-card{border:1px solid #e8ecf0;border-radius:8px;padding:20px 22px;margin-bottom:16px}
.gs-rules-title{font-size:13.5px;font-weight:700;color:#1e2d3d;margin-bottom:4px}
.gs-rules-sub{font-size:12px;color:#6b7280;margin-bottom:18px}

/* rule row */
.rule-row{display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:13px;color:#374151;flex-wrap:wrap}
.rule-row label{font-size:13px;color:#374151}
.rule-input{width:60px;border:1px solid #d1d5db;border-radius:5px;padding:5px 8px;font-size:13px;color:#1e2d3d;outline:none;text-align:center;transition:border-color .15s}
.rule-input:focus{border-color:#2563eb}
.rule-select{border:1px solid #d1d5db;border-radius:5px;padding:5px 22px 5px 8px;font-size:13px;color:#1e2d3d;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 5px center;outline:none;appearance:none;cursor:pointer;transition:border-color .15s}
.rule-select:focus{border-color:#2563eb}

/* toggle switch */
.toggle-switch{position:relative;width:36px;height:20px;cursor:pointer;flex-shrink:0}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#d1d5db;border-radius:20px;transition:background .2s}
.toggle-slider:before{content:'';position:absolute;width:14px;height:14px;left:3px;top:3px;background:#fff;border-radius:50%;transition:transform .2s}
.toggle-switch input:checked + .toggle-slider{background:#2563eb}
.toggle-switch input:checked + .toggle-slider:before{transform:translateX(16px)}

/* checkboxes for holiday/weekoff */
.rule-cb-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:6px}
.rule-cb{display:flex;align-items:center;gap:9px;font-size:13px;color:#374151;cursor:pointer}
.rule-cb input[type=checkbox]{width:15px;height:15px;accent-color:#2563eb;cursor:pointer;flex-shrink:0}

/* accumulation rules */
.accum-card{border:1px solid #e8ecf0;border-radius:8px;padding:20px 22px;margin-bottom:16px}
.accum-title{font-size:13.5px;font-weight:700;color:#1e2d3d;margin-bottom:4px}
.accum-sub{font-size:12px;color:#6b7280;margin-bottom:16px}
.accum-row{display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:13px;color:#374151;flex-wrap:wrap}
.accum-radio{display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;margin-bottom:8px}
.accum-radio input[type=radio]{width:15px;height:15px;accent-color:#2563eb;cursor:pointer}

/* add new leave rule form */
.anr-header{font-size:14.5px;font-weight:700;color:#1e2d3d;margin-bottom:12px}
.anr-select-wrap{margin-bottom:16px}
.anr-select-wrap label{display:block;font-size:12.5px;color:#374151;margin-bottom:6px;font-weight:400}
.anr-select-wrap label .req{color:#ef4444;margin-right:2px}
.lp-select{width:100%;max-width:400px;border:none;border-bottom:1.5px solid #d1d5db;padding:8px 20px 8px 2px;font-size:13.5px;color:#1e2d3d;background:transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 3px center;outline:none;box-sizing:border-box;appearance:none;cursor:pointer;transition:border-color .16s}
.lp-select:focus{border-color:#2563eb}

/* form actions */
.lp-form-actions{display:flex;justify-content:flex-end;gap:12px;padding-top:20px;border-top:1px solid #e8ecf0;margin-top:14px}
.btn-lp-cancel{padding:9px 24px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:13.5px;color:#374151;cursor:pointer;font-weight:600;transition:background .14s}
.btn-lp-cancel:hover{background:#f1f5f9}
.btn-lp-delete{padding:9px 24px;border:1.5px solid #ef4444;background:#fff;border-radius:6px;font-size:13.5px;color:#ef4444;cursor:pointer;font-weight:600;transition:background .14s}
.btn-lp-delete:hover{background:#fee2e2}
.btn-lp-save{padding:9px 24px;background:#2563eb;border:none;border-radius:6px;font-size:13.5px;color:#fff;cursor:pointer;font-weight:600;transition:background .14s}
.btn-lp-save:hover{background:#1d4ed8}

/* buttons */
.btn-add-policy{display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:6px;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .16s}
.btn-add-policy:hover{background:#1d4ed8}

/* toast */
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
.hidden{display:none}
</style>

<div class="toast-container" id="toastContainer"></div>

<div class="cfg-page-head">
  <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">

  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll',
                    'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= e($k) ?>" class="cfg-tab <?= $k==='Leave'?'active':'' ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="lp-wrapper">
    <div class="lp-inner">

      <?php if ($mode === 'gs_view' || $mode === 'add_rule' || $mode === 'edit_rule'): ?>

      <div class="lp-topbar" style="margin-bottom:0">
        <nav class="gs-breadcrumb">
          <a href="?mode=policy_list">Leave</a>
          <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
          <a href="?policy_id=<?= (int)$active_policy_id ?>&mode=gs_view"><?= e($active_policy['policy_name'] ?? 'General Structure') ?></a>
          <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
        </nav>
      </div>

      <div class="lp-sub-header" style="margin-bottom:0">
        <div class="lp-sub-left">List of Leaves</div>
        <div class="lp-sub-right">Leave Details</div>
      </div>

      <div class="lp-panel">
        <div class="lp-list-col">
          <div class="lp-list-scroll">
            <?php foreach ($rules as $r): ?>
            <div class="gs-left-item <?= ((int)$r['id'] === (int)$active_rule_id && $mode === 'gs_view') ? 'active' : '' ?>"
                 onclick="selectRule(<?= (int)$r['id'] ?>)">
              <span class="gs-left-name"><?= e($r['leave_name']) ?></span>
              <i class="fa-solid <?= ((int)$r['id'] === (int)$active_rule_id && $mode === 'gs_view') ? 'fa-chevron-right' : 'fa-chevron-down' ?> lp-item-chevron"></i>
            </div>
            <?php endforeach; ?>

            <?php if (empty($rules)): ?>
            <div style="padding:22px 16px;color:#9ca3af;font-size:13px">No leave rules found.</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="gs-right">
          <?php if ($mode === 'add_rule' || $mode === 'edit_rule'): ?>

          <?php $r = $active_rule ?? []; ?>

          <div class="anr-header"><?= $mode === 'edit_rule' ? 'Edit Leave Rule' : 'Add New Leave Rule' ?></div>

          <form method="POST">
            <input type="hidden" name="action" value="<?= $mode === 'edit_rule' ? 'save_rule' : 'add_rule' ?>">
            <input type="hidden" name="policy_id" value="<?= (int)$active_policy_id ?>">
            <input type="hidden" name="rule_id" value="<?= (int)$active_rule_id ?>">

            <div class="anr-select-wrap">
              <label><span class="req">* </span>Leave Name</label>
              <select name="leave_type_id" class="lp-select" required>
                <option value=""></option>
                <?php foreach ($leave_types as $lt): ?>
                <option value="<?= (int)$lt['id'] ?>"
                  <?= ((int)($r['leave_type_id'] ?? 0) === (int)$lt['id']) ? 'selected' : '' ?>>
                  <?= e($lt['leave_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="gs-tabs">
              <button type="button" class="gs-tab <?= $active_tab==='rules'?'active':'' ?>" onclick="switchTab('rules')">Rules</button>
              <button type="button" class="gs-tab <?= $active_tab==='accum'?'active':'' ?>" onclick="switchTab('accum')">Accumulations</button>
            </div>

            <div id="tab-rules" class="<?= $active_tab==='rules'?'':'hidden' ?>">
              <div class="gs-rules-card">
                <div class="gs-rules-title">Leave Rules</div>
                <div class="gs-rules-sub">Defines the restrictions for employees to apply/ take leave.</div>

                <div class="rule-row">
                  <span>1. Leave request should be submitted</span>
                  <input type="number" name="rule1_days" class="rule-input" value="<?= e($r['rule1_days'] ?? 0) ?>">
                  <span>Days</span>
                  <select name="rule1_timing" class="rule-select">
                    <option value="Before" <?= (($r['rule1_timing'] ?? 'Before') === 'Before') ? 'selected' : '' ?>>Before</option>
                    <option value="After" <?= (($r['rule1_timing'] ?? '') === 'After') ? 'selected' : '' ?>>After</option>
                  </select>
                  <span>Taking Leave</span>
                </div>

                <div class="rule-row">
                  <span>2. When requested leave is greater than</span>
                  <input type="number" name="rule2_gt_days" class="rule-input" step="0.5" value="<?= e($r['rule2_gt_days'] ?? 0) ?>">
                  <span>Days inform</span>
                  <input type="number" name="rule2_inform_days" class="rule-input" value="<?= e($r['rule2_inform_days'] ?? 0) ?>">
                  <span>Days</span>
                  <select name="rule2_timing" class="rule-select">
                    <option value="Before" <?= (($r['rule2_timing'] ?? 'Before') === 'Before') ? 'selected' : '' ?>>Before</option>
                    <option value="After" <?= (($r['rule2_timing'] ?? '') === 'After') ? 'selected' : '' ?>>After</option>
                  </select>
                  <span>Taking Leave</span>
                </div>

                <div class="rule-row">
                  <span>3. Leave request cannot be more than</span>
                  <input type="number" name="rule3_max_days" class="rule-input" value="<?= e($r['rule3_max_days'] ?? 0) ?>">
                  <span>Days and cannot be less than</span>
                  <input type="number" name="rule3_min_days" class="rule-input" step="0.5" value="<?= e($r['rule3_min_days'] ?? 0) ?>">
                  <span>Days</span>
                </div>

                <div class="rule-row">
                  <span>4. Total leaves requested during a year cannot be more than</span>
                  <input type="number" name="rule4_yearly_max" class="rule-input" style="width:70px" value="<?= e($r['rule4_yearly_max'] ?? 0) ?>">
                  <span>Days</span>
                </div>

                <div class="rule-row">
                  <label class="rule-cb"><input type="checkbox" name="leave_paid" value="1" <?= !empty($r['leave_paid']) ? 'checked' : '' ?>> Leave will be paid</label>
                  <input type="number" name="paid_times" class="rule-input" step="0.5" value="<?= e($r['paid_times'] ?? 1) ?>">
                  <span>Times of regular pay day.</span>
                </div>

                <div class="rule-row">
                  <label class="toggle-switch">
                    <input type="checkbox" name="allow_attachment" value="1" <?= !empty($r['allow_attachment']) ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                  </label>
                  <span>Allow file attachments. If yes, apply for leaves longer than</span>
                  <input type="number" name="attach_gt_days" class="rule-input" value="<?= e($r['attach_gt_days'] ?? 0) ?>">
                  <span>Days</span>
                </div>
              </div>

              <div class="gs-rules-card">
                <div class="gs-rules-title">Holidays Between Leave Period :</div>
                <div class="gs-rules-sub">Defines conditions for leave.</div>
                <div class="rule-cb-grid">
                  <label class="rule-cb"><input type="checkbox" name="hol_during" value="1" <?= !empty($r['hol_during']) ? 'checked' : '' ?>> Consider holidays during leave period as leaves.</label>
                  <label class="rule-cb"><input type="checkbox" name="hol_after" value="1" <?= !empty($r['hol_after']) ? 'checked' : '' ?>> Consider holiday after leave period as leaves.</label>
                  <label class="rule-cb"><input type="checkbox" name="hol_before" value="1" <?= !empty($r['hol_before']) ? 'checked' : '' ?>> Consider holiday before leave period as leaves.</label>
                  <label class="rule-cb"><input type="checkbox" name="hol_optional_only" value="1" <?= !empty($r['hol_optional_only']) ? 'checked' : '' ?>> Leaves can only be applied on optional holiday.</label>
                </div>
              </div>

              <div class="gs-rules-card">
                <div class="gs-rules-title">Weekoffs Between Leave Period :</div>
                <div class="gs-rules-sub">Defines weekoffs conditions for leave.</div>
                <div class="rule-cb-grid">
                  <label class="rule-cb"><input type="checkbox" name="wkoff_during" value="1" <?= !empty($r['wkoff_during']) ? 'checked' : '' ?>> Consider weekoff during leave period as leaves.</label>
                  <label class="rule-cb"><input type="checkbox" name="wkoff_after" value="1" <?= !empty($r['wkoff_after']) ? 'checked' : '' ?>> Consider weekoffs after leave period as leaves.</label>
                  <label class="rule-cb"><input type="checkbox" name="wkoff_before" value="1" <?= !empty($r['wkoff_before']) ? 'checked' : '' ?>> Consider weekoffs before leave period as leaves.</label>
                </div>
              </div>
            </div>

            <div id="tab-accum" class="<?= $active_tab==='accum'?'':'hidden' ?>">
              <div class="accum-card">
                <div class="accum-title">Accumulation Rules</div>
                <div class="accum-sub">Defines how employees with this policy will accumulate leave.</div>

                <div class="accum-row">
                  <label class="toggle-switch">
                    <input type="checkbox" name="accum_auto" value="1" id="accumAutoToggle"
                           <?= !empty($r['accum_auto']) ? 'checked' : '' ?>
                           onchange="toggleAccumBasis()">
                    <span class="toggle-slider"></span>
                  </label>
                  <span>Accumulate leave days automatically</span>
                </div>

                <div id="accumBasisSection" style="<?= !empty($r['accum_auto']) ? '' : 'opacity:.45;pointer-events:none' ?>">
                  <div class="accum-radio">
                    <input type="radio" name="accum_basis" value="joining"
                           <?= (($r['accum_basis'] ?? 'joining') === 'joining') ? 'checked' : '' ?>>
                    Consider Date of Joining for accumulations of Leave days.
                  </div>
                  <div class="accum-radio">
                    <input type="radio" name="accum_basis" value="probation"
                           <?= (($r['accum_basis'] ?? '') === 'probation') ? 'checked' : '' ?>>
                    Consider Date of Probation for accumulations of leave days.
                  </div>
                </div>

                <div class="accum-row" style="margin-top:12px">
                  <span>1. Accumulation starts after</span>
                  <input type="number" name="accum_starts_after" class="rule-input" value="<?= e($r['accum_starts_after'] ?? 0) ?>">
                  <span>Days from the above selected consideration. 0 or no input will start accumulation immediately</span>
                </div>

                <div class="accum-row">
                  <span>2.</span>
                  <span>Accumulate</span>
                  <input type="number" name="accum_leaves" class="rule-input" step="0.5" value="<?= e($r['accum_leaves'] ?? 0) ?>">
                  <span>Leaves Every</span>
                  <select name="accum_every" class="rule-select">
                    <option value=""></option>
                    <?php foreach ($accum_every_opts as $eo): ?>
                    <option value="<?= e($eo) ?>" <?= (($r['accum_every'] ?? '') === $eo) ? 'selected' : '' ?>><?= e($eo) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <span>on</span>
                  <input type="text" name="accum_on" class="rule-input" style="width:90px" value="<?= e($r['accum_on'] ?? '') ?>">
                </div>

                <div class="accum-row">
                  <span>3. Maximum leaves balance an employee can have to his credit at any given time</span>
                  <input type="number" name="accum_max_balance" class="rule-input" style="width:70px" value="<?= e($r['accum_max_balance'] ?? 0) ?>">
                  <span>Days</span>
                </div>

                <div class="accum-row">
                  <span>4. Maximum number of Leaves an employee can accumulate in a year</span>
                  <input type="number" name="accum_max_year" class="rule-input" style="width:70px" value="<?= e($r['accum_max_year'] ?? 0) ?>">
                  <span>Days</span>
                </div>

                <div class="accum-row">
                  <span>5. Maximum negative leave balance an employee can have at any given time</span>
                  <input type="number" name="accum_max_neg" class="rule-input" style="width:70px" value="<?= e($r['accum_max_neg'] ?? 0) ?>">
                  <span>Days</span>
                </div>

                <div class="accum-row">
                  <span>6. Maximum number of leaves an employee can carry forward to next year</span>
                  <input type="number" name="accum_carry_fwd" class="rule-input" style="width:70px" value="<?= e($r['accum_carry_fwd'] ?? 0) ?>">
                  <span>Days</span>
                </div>

                <div class="accum-row">
                  <span>7. Remaining Leaves will be</span>
                  <select name="remaining_leaves" class="rule-select">
                    <?php foreach ($remain_opts as $ro): ?>
                    <option value="<?= e($ro) ?>" <?= (($r['remaining_leaves'] ?? 'Lapsed') === $ro) ? 'selected' : '' ?>><?= e($ro) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="lp-form-actions">
              <button type="button" class="btn-lp-cancel"
                      onclick="window.location.href='?policy_id=<?= (int)$active_policy_id ?>&mode=gs_view&rule_id=<?= (int)$active_rule_id ?>'">
                Cancel
              </button>
              <button type="submit" class="btn-lp-save">Save</button>
            </div>
          </form>

          <?php else: ?>

          <?php if ($active_rule): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
            <div class="gs-detail-name">
              <?= e($active_rule['leave_name']) ?>
              <button class="gs-edit-icon btn-edit-link"
                      onclick="window.location.href='?policy_id=<?= (int)$active_policy_id ?>&rule_id=<?= (int)$active_rule_id ?>&mode=edit_rule&tab=rules'">
                <i class="fa-regular fa-pen-to-square"></i>
              </button>
            </div>
            <button class="gs-accum-btn"
                    onclick="window.location.href='?policy_id=<?= (int)$active_policy_id ?>&rule_id=<?= (int)$active_rule_id ?>&mode=edit_rule&tab=accum'">
              Accumulate Now
            </button>
          </div>

          <div class="gs-tabs">
            <button type="button" class="gs-tab <?= $active_tab==='rules'?'active':'' ?>" onclick="switchViewTab('rules')">Rules</button>
            <button type="button" class="gs-tab <?= $active_tab==='accum'?'active':'' ?>" onclick="switchViewTab('accum')">Accumulations</button>
          </div>

          <?php $r = $active_rule; ?>

          <div id="vtab-rules" style="<?= $active_tab==='accum'?'display:none':'' ?>">
            <div class="gs-rules-card">
              <div class="gs-rules-title">Leave Rules</div>
              <div class="gs-rules-sub">Defines the restrictions for employees to apply/ take leave.</div>

              <div class="rule-row">
                <span>1. Leave request should be submitted</span>
                <span style="border:1px solid #d1d5db;border-radius:5px;padding:5px 12px;font-weight:600;color:#1e2d3d;min-width:50px;text-align:center"><?= e($r['rule1_days']) ?></span>
                <span>Days</span>
                <span style="border:1px solid #d1d5db;border-radius:5px;padding:5px 14px;color:#374151"><?= e($r['rule1_timing']) ?> ▾</span>
                <span>Taking leave</span>
              </div>

              <div class="rule-row">
                <span>2. When requested leave is greater than</span>
                <span style="border:1px solid #d1d5db;border-radius:5px;padding:5px 12px;font-weight:600;color:#1e2d3d;min-width:50px;text-align:center"><?= e($r['rule2_gt_days']) ?></span>
                <span>Days inform</span>
                <span style="border:1px solid #d1d5db;border-radius:5px;padding:5px 14px;color:#374151"><?= e($r['rule2_inform_days']) ?> ▾</span>
                <span>Days</span>
                <span style="border:1px solid #d1d5db;border-radius:5px;padding:5px 14px;color:#374151"><?= e($r['rule2_timing']) ?> ▾</span>
                <span>Taking leave</span>
              </div>

              <div class="rule-row">
                <span>3. Leave request cannot be more than</span>
                <span style="border:1px solid #d1d5db;border-radius:5px;padding:5px 12px;font-weight:600;min-width:50px;text-align:center"><?= e($r['rule3_max_days']) ?></span>
                <span>Days and cannot be less than</span>
                <span style="border:1px solid #d1d5db;border-radius:5px;padding:5px 14px;color:#374151"><?= e($r['rule3_min_days']) ?> ▾</span>
                <span>Days</span>
              </div>

              <div class="rule-row">
                <span>4. Total leaves requested during a year cannot be more than</span>
                <span style="border:1px solid #d1d5db;border-radius:5px;padding:5px 12px;font-weight:600;min-width:60px;text-align:center"><?= e($r['rule4_yearly_max']) ?></span>
                <span>Days</span>
              </div>

              <div class="rule-row">
                <label class="rule-cb">
                  <input type="checkbox" <?= !empty($r['leave_paid']) ? 'checked' : '' ?> disabled style="accent-color:#2563eb">
                  Leave will be paid
                </label>
                <span style="border:1px solid #d1d5db;border-radius:5px;padding:5px 12px;min-width:50px;text-align:center"><?= e($r['paid_times']) ?></span>
                <span>Times of regular pay day.</span>
              </div>

              <div class="rule-row">
                <span class="toggle-switch" style="pointer-events:none">
                  <input type="checkbox" <?= !empty($r['allow_attachment']) ? 'checked' : '' ?> disabled>
                  <span class="toggle-slider"></span>
                </span>
                <span>Allow file attachments. If yes, apply for leaves longer than</span>
                <input type="text" class="rule-input" value="<?= e($r['attach_gt_days']) ?>" readonly style="background:#f9fafb">
                <span>Days</span>
              </div>
            </div>

            <div class="gs-rules-card">
              <div class="gs-rules-title">Holidays between leave period :</div>
              <div class="gs-rules-sub">Defines conditions for leave.</div>
              <div class="rule-cb-grid">
                <label class="rule-cb" style="pointer-events:none"><input type="checkbox" <?= !empty($r['hol_during']) ? 'checked' : '' ?> disabled style="accent-color:#2563eb"> Consider holidays during leave period as leaves.</label>
                <label class="rule-cb" style="pointer-events:none"><input type="checkbox" <?= !empty($r['hol_after']) ? 'checked' : '' ?> disabled style="accent-color:#2563eb"> Consider holiday after leave period as leaves.</label>
                <label class="rule-cb" style="pointer-events:none"><input type="checkbox" <?= !empty($r['hol_before']) ? 'checked' : '' ?> disabled style="accent-color:#2563eb"> Consider holiday before leave period as leaves.</label>
                <label class="rule-cb" style="pointer-events:none"><input type="checkbox" <?= !empty($r['hol_optional_only']) ? 'checked' : '' ?> disabled style="accent-color:#2563eb"> Leaves can only be applied on optional holiday.</label>
              </div>
            </div>

            <div class="gs-rules-card">
              <div class="gs-rules-title">Weekoffs Between leave Period :</div>
              <div class="gs-rules-sub">Defines weekoffs conditions for leave.</div>
              <div class="rule-cb-grid">
                <label class="rule-cb" style="pointer-events:none"><input type="checkbox" <?= !empty($r['wkoff_during']) ? 'checked' : '' ?> disabled style="accent-color:#2563eb"> Consider weekoff during leave period as leaves.</label>
                <label class="rule-cb" style="pointer-events:none"><input type="checkbox" <?= !empty($r['wkoff_after']) ? 'checked' : '' ?> disabled style="accent-color:#2563eb"> Consider weekoffs after leave period as leaves.</label>
                <label class="rule-cb" style="pointer-events:none"><input type="checkbox" <?= !empty($r['wkoff_before']) ? 'checked' : '' ?> disabled style="accent-color:#2563eb"> Consider weekoffs before leave period as leaves.</label>
              </div>
            </div>
          </div>

          <div id="vtab-accum" style="<?= $active_tab!=='accum'?'display:none':'' ?>">
            <div class="accum-card">
              <div class="accum-title">Accumulation Rules</div>
              <div class="accum-sub">Defines how employees with this policy will accumulate leave.</div>

              <div class="accum-row">
                <span>Automatic Accumulation:</span>
                <strong><?= !empty($r['accum_auto']) ? 'Yes' : 'No' ?></strong>
              </div>
              <div class="accum-row"><span>Accumulation Basis:</span> <strong><?= e($r['accum_basis']) ?></strong></div>
              <div class="accum-row"><span>Starts After:</span> <strong><?= e($r['accum_starts_after']) ?> Days</strong></div>
              <div class="accum-row"><span>Accumulate:</span> <strong><?= e($r['accum_leaves']) ?> Leaves Every <?= e($r['accum_every']) ?></strong></div>
              <div class="accum-row"><span>Maximum Balance:</span> <strong><?= e($r['accum_max_balance']) ?> Days</strong></div>
              <div class="accum-row"><span>Maximum Yearly Accumulation:</span> <strong><?= e($r['accum_max_year']) ?> Days</strong></div>
              <div class="accum-row"><span>Maximum Negative Balance:</span> <strong><?= e($r['accum_max_neg']) ?> Days</strong></div>
              <div class="accum-row"><span>Carry Forward:</span> <strong><?= e($r['accum_carry_fwd']) ?> Days</strong></div>
              <div class="accum-row"><span>Remaining Leaves:</span> <strong><?= e($r['remaining_leaves']) ?></strong></div>
            </div>
          </div>

          <?php else: ?>
          <div style="display:flex;align-items:center;justify-content:center;height:200px;color:#9ca3af;font-size:13.5px">Select a leave from the list.</div>
          <?php endif; ?>

          <?php endif; ?>
        </div>
      </div>

      <?php else: ?>

      <div class="lp-topbar">
        <nav class="lp-breadcrumb">
          <a href="leave_config.php">Leave</a>
          <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
          <span>Leave Policies</span>
        </nav>

        <?php if ($mode !== 'add_policy'): ?>
        <button class="btn-add-policy" onclick="setMode('add_policy')">
          <i class="fa-solid fa-plus"></i> Add New Leave Policy
        </button>
        <?php endif; ?>
      </div>

      <div class="lp-instructions">
        <strong>Instructions :</strong><br>
        1. Leave Structure is a set of leave policies. After defining a leave structure you can assign it to the employees to whom this set of leave policies apply.<br>
        2. You can define the following here: -<br>
        &nbsp;&nbsp;&nbsp;- The type of leaves an employee can avail, Employees can only apply those leaves which are present in their leave structure.<br>
        &nbsp;&nbsp;&nbsp;- You can define the rules applicable to each type of leave when an employee applies leave.<br>
        &nbsp;&nbsp;&nbsp;- You can also define the rules which are applicable when you accumulate the leaves for an employee.
      </div>

      <div class="lp-sub-header">
        <div class="lp-sub-left">List of Policies</div>
        <div class="lp-sub-right">Leave Policies Details</div>
      </div>

      <div class="lp-panel">
        <div class="lp-list-col">
          <div class="lp-list-scroll">
            <?php foreach ($policies as $pol): ?>
            <div class="lp-item <?= ((int)$pol['id'] === (int)$active_policy_id && $mode !== 'add_policy') ? 'active' : '' ?>"
                 onclick="selectPolicy(<?= (int)$pol['id'] ?>)">
              <span class="lp-item-name"><?= e($pol['policy_name']) ?></span>
              <i class="fa-solid <?= ((int)$pol['id'] === (int)$active_policy_id && $mode !== 'add_policy') ? 'fa-chevron-right' : 'fa-chevron-down' ?> lp-item-chevron"></i>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="lp-detail-col">

          <?php if ($mode === 'add_policy'): ?>

          <div class="lp-detail-title" style="margin-bottom:20px">ADD NEW LEAVE POLICY</div>
          <form method="POST">
            <input type="hidden" name="action" value="add_policy">
            <div class="lp-field-grid" style="margin-bottom:16px">
              <div class="lp-field">
                <label><span class="req">* </span>Leave Policy Name</label>
                <input type="text" name="policy_name" class="lp-input" placeholder="General Structure"
                       value="<?= e($_POST['policy_name'] ?? '') ?>" required>
              </div>
              <div class="lp-field">
                <label><span class="req">* </span>Leave Policy Code</label>
                <input type="text" name="policy_code" class="lp-input" placeholder="GEN"
                       value="<?= e($_POST['policy_code'] ?? '') ?>" required>
              </div>
            </div>
            <div class="lp-field-grid single" style="margin-bottom:10px">
              <div class="lp-field">
                <label>Remarks</label>
                <input type="text" name="remarks" class="lp-input" placeholder="Leave Structure"
                       value="<?= e($_POST['remarks'] ?? '') ?>">
              </div>
            </div>
            <div class="lp-form-actions">
              <button type="button" class="btn-lp-cancel" onclick="setMode('policy_list')">Cancel</button>
              <button type="submit" class="btn-lp-save">Add</button>
            </div>
          </form>

          <?php elseif ($mode === 'edit_policy' && $active_policy): ?>

          <div class="lp-detail-title" style="margin-bottom:20px">EDIT LEAVE POLICY</div>
          <form method="POST">
            <input type="hidden" name="action" value="save_policy">
            <input type="hidden" name="policy_id" value="<?= (int)$active_policy['id'] ?>">

            <div class="lp-field-grid" style="margin-bottom:16px">
              <div class="lp-field">
                <label><span class="req">* </span>Leave Policy Name</label>
                <input type="text" name="policy_name" class="lp-input"
                       value="<?= e($_POST['policy_name'] ?? $active_policy['policy_name']) ?>" required>
              </div>
              <div class="lp-field">
                <label><span class="req">* </span>Leave Policy Code</label>
                <input type="text" name="policy_code" class="lp-input"
                       value="<?= e($_POST['policy_code'] ?? $active_policy['policy_code']) ?>" required>
              </div>
            </div>

            <div class="lp-field-grid single" style="margin-bottom:10px">
              <div class="lp-field">
                <label>Remarks</label>
                <input type="text" name="remarks" class="lp-input"
                       value="<?= e($_POST['remarks'] ?? $active_policy['remarks']) ?>">
              </div>
            </div>

            <div class="lp-form-actions">
              <button type="button" class="btn-lp-delete" onclick="deletePolicy(<?= (int)$active_policy['id'] ?>)">Delete</button>
              <button type="button" class="btn-lp-cancel" onclick="window.location.href='?policy_id=<?= (int)$active_policy['id'] ?>&mode=policy_list'">Cancel</button>
              <button type="submit" class="btn-lp-save">Save</button>
            </div>
          </form>

          <?php elseif ($active_policy): ?>

          <div class="lp-detail-title-row">
            <div class="lp-detail-title"><?= e($active_policy['policy_name']) ?></div>
            <button class="btn-edit-link" onclick="setMode('edit_policy')">
              <i class="fa-regular fa-pen-to-square"></i> Edit Details
            </button>
          </div>

          <div class="lp-field-grid" style="margin-bottom:14px">
            <div class="lp-field">
              <label>Leave Policy Name</label>
              <div class="lp-field-value"><?= e($active_policy['policy_name']) ?></div>
            </div>
            <div class="lp-field">
              <label>Leave Policy Code</label>
              <div class="lp-field-value"><?= e($active_policy['policy_code']) ?></div>
            </div>
          </div>

          <div class="lp-field-grid single" style="margin-bottom:16px">
            <div class="lp-field">
              <label>Remarks</label>
              <div class="lp-field-value" style="color:#9ca3af"><?= e($active_policy['remarks'] ?: 'Leave Structure') ?></div>
            </div>
          </div>

          <div class="lt-cards-section">
            <div class="lt-cards-header">
              <span class="lt-cards-label">List of Leaves Types</span>
              <button class="btn-add-rule"
                      onclick="window.location.href='?policy_id=<?= (int)$active_policy['id'] ?>&mode=add_rule&tab=rules'">
                <i class="fa-solid fa-plus"></i> Add Leave Rule
              </button>
            </div>

            <div class="lt-cards-grid">
              <?php foreach ($rules as $r): ?>
              <div class="lt-card">
                <div class="lt-card-code"><?= e($r['leave_code']) ?></div>
                <div class="lt-card-actions">
                  <button class="btn-card-link" title="View/Edit"
                          onclick="window.location.href='?policy_id=<?= (int)$active_policy['id'] ?>&rule_id=<?= (int)$r['id'] ?>&mode=gs_view&tab=rules'">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </button>
                  <button class="btn-card-del" title="Delete"
                          onclick="deleteRule(<?= (int)$r['id'] ?>, <?= (int)$active_policy['id'] ?>)">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
                <div class="lt-card-name"><?= e($r['leave_name']) ?></div>
              </div>
              <?php endforeach; ?>

              <?php if (empty($rules)): ?>
              <div style="color:#9ca3af;font-size:13px">No leave rules added.</div>
              <?php endif; ?>
            </div>
          </div>

          <?php else: ?>
          <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13.5px">
            Select a policy to view details.
          </div>
          <?php endif; ?>

        </div>
      </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php if ($flash): ?>
<script>
window.addEventListener('DOMContentLoaded', function () {
    showToast(<?= json_encode($flash) ?>, <?= json_encode($flash_type) ?>);
});
</script>
<?php endif; ?>

<script>
function selectPolicy(id) {
    const u = new URL(window.location.href);
    u.searchParams.set('policy_id', id);
    u.searchParams.set('mode', 'policy_list');
    window.location.href = u.toString();
}

function selectRule(id) {
    const u = new URL(window.location.href);
    u.searchParams.set('rule_id', id);
    u.searchParams.set('mode', 'gs_view');
    u.searchParams.set('tab', 'rules');
    window.location.href = u.toString();
}

function setMode(mode) {
    const u = new URL(window.location.href);
    u.searchParams.set('mode', mode);
    window.location.href = u.toString();
}

function deleteRule(rid, pid) {
    if (!confirm('Delete this leave rule?')) return;

    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `
        <input type="hidden" name="action" value="delete_rule">
        <input type="hidden" name="rule_id" value="${rid}">
        <input type="hidden" name="policy_id" value="${pid}">
    `;
    document.body.appendChild(f);
    f.submit();
}

function deletePolicy(pid) {
    if (!confirm('Delete this policy and all leave rules?')) return;

    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `
        <input type="hidden" name="action" value="delete_policy">
        <input type="hidden" name="policy_id" value="${pid}">
    `;
    document.body.appendChild(f);
    f.submit();
}

function switchTab(t) {
    document.getElementById('tab-rules').classList.toggle('hidden', t !== 'rules');
    document.getElementById('tab-accum').classList.toggle('hidden', t !== 'accum');

    document.querySelectorAll('.gs-tab').forEach(function(btn) {
        btn.classList.remove('active');
    });

    event.target.classList.add('active');
}

function switchViewTab(t) {
    document.getElementById('vtab-rules').style.display = t === 'rules' ? '' : 'none';
    document.getElementById('vtab-accum').style.display = t === 'accum' ? '' : 'none';

    document.querySelectorAll('.gs-tab').forEach(function(btn) {
        btn.classList.remove('active');
    });

    event.target.classList.add('active');
}

function toggleAccumBasis() {
    const s = document.getElementById('accumBasisSection');
    const t = document.getElementById('accumAutoToggle');

    if (!s || !t) return;

    s.style.opacity = t.checked ? '1' : '0.45';
    s.style.pointerEvents = t.checked ? 'auto' : 'none';
}

const _ti = {
    success: 'fa-circle-check',
    error: 'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info: 'fa-circle-info'
};

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}

function showToast(msg, type = 'success', dur = 3500) {
    const c = document.getElementById('toastContainer');

    if (!c) {
        alert(msg);
        return;
    }

    const t = document.createElement('div');
    t.className = 'toast ' + type;

    t.innerHTML = `
        <i class="fa-solid ${_ti[type] || _ti.info}"></i>
        <span>${escapeHtml(msg)}</span>
        <button type="button" class="toast-close" onclick="rmToast(this.parentElement)">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;

    c.appendChild(t);
    setTimeout(() => rmToast(t), dur);
}

function rmToast(el) {
    if (!el || !el.parentElement) return;
    el.style.animation = 'toastOut .25s ease forwards';
    setTimeout(() => {
        if (el.parentElement) el.remove();
    }, 260);
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>