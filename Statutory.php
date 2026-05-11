<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Statutory Configuration';

function esc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$flash = '';
$flash_type = '';
$active_tab   = $_GET['tab'] ?? 'global';
$edit_section = $_GET['edit'] ?? '';
$emp_query    = trim($_GET['emp_q'] ?? '');
$emp_found    = null;

/* ─────────────────────────────────────────
   POST SAVE
───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'save_global_epf') {
        $stmt = $conn->prepare("
            UPDATE statutory_global_config SET
            epf_emp_rate = ?,
            epf_employer_rate = ?,
            pension_fund = ?,
            edli = ?,
            pf_admin = ?,
            edli_admin = ?,
            pf_max_ceil = ?,
            pf_edli_max_ceil = ?,
            updated_at = NOW()
            WHERE id = 1
        ");

        $stmt->bind_param(
            "ssssssss",
            $_POST['epf_emp_rate'],
            $_POST['epf_employer_rate'],
            $_POST['pension_fund'],
            $_POST['edli'],
            $_POST['pf_admin'],
            $_POST['edli_admin'],
            $_POST['pf_max_ceil'],
            $_POST['pf_edli_max_ceil']
        );

        if ($stmt->execute()) {
            $flash = 'Global EPF configuration saved.';
            $flash_type = 'success';
        } else {
            $flash = 'Save failed: ' . $stmt->error;
            $flash_type = 'error';
        }

        $active_tab = 'global';
        $edit_section = '';
    }

    if ($action === 'save_global_esi') {
        $stmt = $conn->prepare("
            UPDATE statutory_global_config SET
            esi_emp = ?,
            esi_employer = ?,
            esi_max_ceil = ?,
            updated_at = NOW()
            WHERE id = 1
        ");

        $stmt->bind_param(
            "sss",
            $_POST['esi_emp'],
            $_POST['esi_employer'],
            $_POST['esi_max_ceil']
        );

        if ($stmt->execute()) {
            $flash = 'Global ESI configuration saved.';
            $flash_type = 'success';
        } else {
            $flash = 'Save failed: ' . $stmt->error;
            $flash_type = 'error';
        }

        $active_tab = 'global';
        $edit_section = '';
    }

    if ($action === 'save_emp_epf') {
        $emp_id = (int)($_POST['emp_id'] ?? 0);
        $emp_query = trim($_POST['emp_q'] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO statutory_employee_config
            (
                employee_id, epf_emp_rate, epf_employer_rate, pension_fund,
                edli, pf_admin, edli_admin, pf_max_ceil, pf_edli_max_ceil,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                epf_emp_rate = VALUES(epf_emp_rate),
                epf_employer_rate = VALUES(epf_employer_rate),
                pension_fund = VALUES(pension_fund),
                edli = VALUES(edli),
                pf_admin = VALUES(pf_admin),
                edli_admin = VALUES(edli_admin),
                pf_max_ceil = VALUES(pf_max_ceil),
                pf_edli_max_ceil = VALUES(pf_edli_max_ceil),
                updated_at = NOW()
        ");

        $stmt->bind_param(
            "issssssss",
            $emp_id,
            $_POST['epf_emp_rate'],
            $_POST['epf_employer_rate'],
            $_POST['pension_fund'],
            $_POST['edli'],
            $_POST['pf_admin'],
            $_POST['edli_admin'],
            $_POST['pf_max_ceil'],
            $_POST['pf_edli_max_ceil']
        );

        if ($stmt->execute()) {
            $flash = 'Employee EPF configuration saved.';
            $flash_type = 'success';
        } else {
            $flash = 'Save failed: ' . $stmt->error;
            $flash_type = 'error';
        }

        $active_tab = 'employee';
        $edit_section = '';
    }

    if ($action === 'save_tax_dates') {
        $emp_id = (int)($_POST['emp_id'] ?? 0);
        $emp_query = trim($_POST['emp_q'] ?? '');
        $tax_start = $_POST['tax_start'] ?: null;
        $tax_end   = $_POST['tax_end'] ?: null;

        $stmt = $conn->prepare("
            INSERT INTO statutory_employee_config
            (employee_id, tax_start, tax_end, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                tax_start = VALUES(tax_start),
                tax_end = VALUES(tax_end),
                updated_at = NOW()
        ");

        $stmt->bind_param("iss", $emp_id, $tax_start, $tax_end);

        if ($stmt->execute()) {
            $flash = 'Tax declaration dates saved.';
            $flash_type = 'success';
        } else {
            $flash = 'Save failed: ' . $stmt->error;
            $flash_type = 'error';
        }

        $active_tab = 'employee';
    }
}

/* ─────────────────────────────────────────
   FETCH GLOBAL CONFIG
───────────────────────────────────────── */
$global = [
    'epf_emp_rate'       => '12%',
    'epf_employer_rate'  => '3.67%',
    'pension_fund'       => '8.33%',
    'edli'               => '0.5',
    'pf_admin'           => '0.50',
    'edli_admin'         => '0.00',
    'pf_max_ceil'        => '1800',
    'pf_edli_max_ceil'   => '15000',
    'esi_emp'            => '0.75%',
    'esi_employer'       => '3.25%',
    'esi_max_ceil'       => '₹21000',
];

$resGlobal = $conn->query("SELECT * FROM statutory_global_config WHERE id = 1 LIMIT 1");
if ($resGlobal && $resGlobal->num_rows > 0) {
    $global = array_merge($global, $resGlobal->fetch_assoc());
}

/* ─────────────────────────────────────────
   EMPLOYEE SEARCH
───────────────────────────────────────── */
if ($emp_query !== '') {
    $like = '%' . $emp_query . '%';

    $stmt = $conn->prepare("
        SELECT 
            e.id,
            e.employee_name AS name,
            e.employee_code AS code,
            COALESCE(sec.epf_emp_rate, sgc.epf_emp_rate) AS epf_emp_rate,
            COALESCE(sec.epf_employer_rate, sgc.epf_employer_rate) AS epf_employer_rate,
            COALESCE(sec.pension_fund, sgc.pension_fund) AS pension_fund,
            COALESCE(sec.edli, sgc.edli) AS edli,
            COALESCE(sec.pf_admin, sgc.pf_admin) AS pf_admin,
            COALESCE(sec.edli_admin, sgc.edli_admin) AS edli_admin,
            COALESCE(sec.pf_max_ceil, sgc.pf_max_ceil) AS pf_max_ceil,
            COALESCE(sec.pf_edli_max_ceil, sgc.pf_edli_max_ceil) AS pf_edli_max_ceil,
            COALESCE(sec.esi_emp, sgc.esi_emp) AS esi_emp,
            COALESCE(sec.esi_employer, sgc.esi_employer) AS esi_employer,
            COALESCE(sec.esi_max_ceil, sgc.esi_max_ceil) AS esi_max_ceil,
            sec.tax_start,
            sec.tax_end
        FROM employees e
        CROSS JOIN statutory_global_config sgc
        LEFT JOIN statutory_employee_config sec ON sec.employee_id = e.id
        WHERE e.status = 'active'
        AND (e.employee_name LIKE ? OR e.employee_code LIKE ?)
        LIMIT 1
    ");

    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();

    $resEmp = $stmt->get_result();

    if ($resEmp && $resEmp->num_rows > 0) {
        $emp_found = $resEmp->fetch_assoc();
    }
}

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ── Shared ── */
.sc-wrapper {
  padding: 0 0 40px;
  font-family: 'Segoe UI', sans-serif;
  color: #1e2d3d;
}

/* ── Config tab bar ── */
.cfg-tabs {
  display: flex;
  align-items: center;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
  overflow-x: auto;
  scrollbar-width: none;
}
.cfg-tabs::-webkit-scrollbar { display: none; }
.cfg-tab {
  padding: 14px 20px;
  font-size: 13.5px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  border: none;
  background: transparent;
  border-bottom: 2.5px solid transparent;
  white-space: nowrap;
  transition: color .15s, border-color .15s;
  text-decoration: none;
  display: block;
  margin-bottom: -1px;
}
.cfg-tab:hover { color: #111827; }
.cfg-tab.active { color: #2563eb; border-bottom-color: #2563eb; font-weight: 600; }

/* ── Page inner ── */
.sc-inner {
  padding: 20px 32px;
}

/* breadcrumb */
.sc-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  color: #555;
  margin-bottom: 18px;
}
.sc-breadcrumb a { color: #2563eb; text-decoration: none; font-weight: 500; }
.sc-breadcrumb a:hover { text-decoration: underline; }
.sc-breadcrumb .sep { color: #bbb; font-size: 11px; }

/* instructions box */
.sc-instructions {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 8px;
  padding: 18px 22px;
  margin-bottom: 20px;
  font-size: 13px;
  line-height: 1.7;
  color: #374151;
}
.sc-instructions strong { color: #1e2d3d; }
.sc-instructions .label-tag { color: #111; font-weight: 700; }

/* sub-label tag */
.sc-label-tag {
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 8px 16px;
  font-size: 13px;
  color: #374151;
  display: inline-block;
  margin-bottom: 20px;
}

/* ── Section card ── */
.sc-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 24px;
}

/* ── Inner tabs ── */
.sc-inner-tabs {
  display: flex;
  border-bottom: 1px solid #e8ecf0;
  background: #fff;
  padding: 0 22px;
}
.sc-inner-tab {
  padding: 13px 0;
  margin-right: 28px;
  font-size: 13.5px;
  color: #6b7280;
  font-weight: 500;
  cursor: pointer;
  border-bottom: 2.5px solid transparent;
  margin-bottom: -1px;
  background: none;
  border-top: none;
  border-left: none;
  border-right: none;
  transition: color .15s, border-color .15s;
}
.sc-inner-tab:hover { color: #1e2d3d; }
.sc-inner-tab.active { color: #2563eb; border-bottom-color: #2563eb; font-weight: 600; }

/* ── Tab panes ── */
.sc-tab-pane { display: none; padding: 26px 26px 10px; }
.sc-tab-pane.active { display: block; }

/* ── Section heading ── */
.sc-section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 6px;
}
.sc-section-title {
  font-size: 13.5px;
  font-weight: 800;
  color: #1e2d3d;
  text-transform: uppercase;
  letter-spacing: .3px;
}
.sc-section-note {
  font-size: 12.5px;
  color: #6b7280;
  margin-bottom: 22px;
  line-height: 1.5;
}
.btn-edit-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #2563eb;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 600;
  white-space: nowrap;
  padding: 0;
}
.btn-edit-link:hover { text-decoration: underline; }

/* ── Field grid ── */
.sc-field-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px 28px;
  margin-bottom: 22px;
}
.sc-field-grid.col2 { grid-template-columns: repeat(2, 1fr); }

.sc-field label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 7px;
  font-weight: 500;
}

/* read-only value display */
.sc-field-row-val {
  display: flex;
  align-items: center;
  gap: 8px;
}
.sc-val-box {
  border: 1px solid #d1d5db;
  border-radius: 5px;
  padding: 7px 12px;
  font-size: 13.5px;
  color: #1e2d3d;
  min-width: 80px;
  background: #fff;
}
.sc-val-suffix {
  font-size: 12.5px;
  color: #6b7280;
  white-space: nowrap;
}

/* full-width underline value */
.sc-val-line {
  border: none;
  border-bottom: 1px solid #d1d5db;
  padding: 7px 2px;
  font-size: 13.5px;
  color: #1e2d3d;
  background: transparent;
  width: 100%;
}

/* Edit form inputs */
.sc-input {
  width: 100%;
  border: none;
  border-bottom: 1.5px solid #d1d5db;
  padding: 7px 2px;
  font-size: 13.5px;
  color: #1e2d3d;
  background: transparent;
  outline: none;
  box-sizing: border-box;
  transition: border-color .16s;
}
.sc-input:focus { border-color: #2563eb; }
.sc-input-with-suffix { display: flex; align-items: center; gap: 8px; }
.sc-input-with-suffix .sc-input { flex: 0 0 90px; }

/* section divider */
.sc-divider { border: none; border-top: 1px solid #e8ecf0; margin: 8px 0 26px; }

/* ── Employee search ── */
.sc-emp-search {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 26px;
}
.sc-search-wrap {
  position: relative;
  width: 320px;
}
.sc-search-wrap i {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 13px;
}
.sc-search-input {
  width: 100%;
  padding: 9px 12px 9px 34px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13.5px;
  color: #1e2d3d;
  outline: none;
  box-sizing: border-box;
  transition: border-color .16s;
}
.sc-search-input:focus { border-color: #2563eb; }
.btn-get-details {
  padding: 9px 22px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s;
}
.btn-get-details:hover { background: #1d4ed8; }

/* employee name heading */
.sc-emp-name {
  font-size: 14px;
  font-weight: 700;
  color: #1e2d3d;
  margin-bottom: 20px;
}

/* ── Tax declaration date row ── */
.sc-date-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px 28px;
  margin-bottom: 10px;
}
.sc-date-field label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 7px;
  font-weight: 500;
}
.sc-date-input-wrap {
  position: relative;
}
.sc-date-input-wrap input[type="date"] {
  width: 100%;
  border: none;
  border-bottom: 1.5px solid #d1d5db;
  padding: 7px 30px 7px 2px;
  font-size: 13.5px;
  color: #1e2d3d;
  background: transparent;
  outline: none;
  box-sizing: border-box;
  transition: border-color .16s;
  cursor: pointer;
}
.sc-date-input-wrap input[type="date"]:focus { border-color: #2563eb; }
.sc-date-input-wrap i {
  position: absolute;
  right: 4px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 13px;
  pointer-events: none;
}

/* form save bar */
.sc-save-bar {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 18px 26px;
  border-top: 1px solid #e8ecf0;
  background: #f9fafb;
}
.btn-cancel {
  padding: 9px 26px;
  border: 1.5px solid #d1d5db;
  background: #fff;
  border-radius: 6px;
  font-size: 13.5px;
  color: #374151;
  cursor: pointer;
  font-weight: 600;
  transition: background .14s;
}
.btn-cancel:hover { background: #f1f5f9; }
.btn-save {
  padding: 9px 26px;
  background: #2563eb;
  border: none;
  border-radius: 6px;
  font-size: 13.5px;
  color: #fff;
  cursor: pointer;
  font-weight: 600;
  transition: background .14s;
}
.btn-save:hover { background: #1d4ed8; }

/* flash */
.flash-msg {
  padding: 10px 16px;
  border-radius: 7px;
  font-size: 13px;
  margin-bottom: 14px;
  font-weight: 500;
}
.flash-msg.success { background: #dcfce7; color: #166534; }
.flash-msg.error   { background: #fee2e2; color: #991b1b; }

/* ── Edit state: hides read fields, shows inputs ── */
.view-only   { display: block; }
.edit-only   { display: none;  }
.editing .view-only { display: none; }
.editing .edit-only { display: block; }

.sc-toast {
    position:fixed;
    bottom:24px;
    left:50%;
    transform:translateX(-50%) translateY(80px);
    background:#111827;
    color:#fff;
    padding:11px 20px;
    border-radius:10px;
    font-size:13px;
    font-weight:500;
    z-index:999;
    display:flex;
    align-items:center;
    gap:8px;
    box-shadow:0 8px 28px rgba(0,0,0,.2);
    transition:transform .3s ease;
    white-space:nowrap;
}
.sc-toast.show {
    transform:translateX(-50%) translateY(0);
}
</style>

<?php if ($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    scToast('<?= $flash_type === 'success' ? '✅' : '⚠️' ?>', '<?= esc($flash) ?>');
});
</script>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>
<div class="section-card" style="padding:0;overflow:hidden">
    <div class="sc-wrapper">
        <div class="cfg-tabs">
            <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll',
                            'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
            <a href="configuration#<?= esc($k) ?>" class="cfg-tab <?= $k==='Payroll'?'active':'' ?>"><?= esc($l) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="sc-inner">

            <nav class="sc-breadcrumb">
                <a href="configuration#Payroll">Payroll</a>
                <span class="sep"><i class="fa-solid fa-chevron-right"></i></span>
                <span>Statutory Components ( Tax Configuration )</span>
            </nav>

            <div class="sc-instructions">
                <p style="margin:0 0 6px"><strong>Instructions :</strong></p>
                <p style="margin:0 0 4px">
                    <span class="label-tag">EPF :</span>
                    Any organisation with 20 or more employees must register for the Employee Provident Fund (EPF),
                    scheme, a retirement benefit plan for all salaried employees.
                </p>
                <p style="margin:0">
                    <span class="label-tag">ESI :</span>
                    Organisations having 10 or more employees must register for Employee State Insurance (ESI).
                    This scheme provides cash allowances and medical benefits for employees whose monthly salary is
                    less than ₹21,000.
                </p>
            </div>

            <div class="sc-label-tag">Statutory Components ( Tax Configuration )</div>

            <div class="sc-card">
                <div class="sc-inner-tabs">
                    <button class="sc-inner-tab <?= $active_tab==='global'?'active':'' ?>" onclick="switchTab('global')">Global Configuration</button>
                    <button class="sc-inner-tab <?= $active_tab==='employee'?'active':'' ?>" onclick="switchTab('employee')">Employee Level Configuration</button>
                </div>

                <div class="sc-tab-pane <?= $active_tab==='global'?'active':'' ?>" id="tab-global">

                    <div class="sc-section-head">
                        <div><div class="sc-section-title">Employees' Provident Fund (EPF)</div></div>
                        <?php if ($edit_section !== 'epf'): ?>
                        <button class="btn-edit-link" onclick="startEdit('epf')">
                            <i class="fa-regular fa-pen-to-square"></i> Edit Details
                        </button>
                        <?php endif; ?>
                    </div>

                    <p class="sc-section-note">
                        Note : ESI Contribution for each month should be deposited to the ESIC with in the 15th of the following month.
                    </p>

                    <?php if ($edit_section === 'epf'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_global_epf">
                        <div class="sc-field-grid">
                            <?php
                            $epfFields = [
                                ['epf_emp_rate','Employee Contribution Rate','of PF Wage'],
                                ['epf_employer_rate','Employer Contribution Rate','of PF Wage'],
                                ['pension_fund','Pension Fund','of PF Wage'],
                            ];
                            foreach($epfFields as [$key,$label,$suffix]):
                            ?>
                            <div class="sc-field">
                                <label><?= esc($label) ?></label>
                                <div class="sc-input-with-suffix">
                                    <input type="text" name="<?= esc($key) ?>" class="sc-input" value="<?= esc($global[$key]) ?>">
                                    <span class="sc-val-suffix"><?= esc($suffix) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php foreach(['edli'=>'EDLI','pf_admin'=>'PF Admin Charges','edli_admin'=>'EDLI Admin Charges','pf_max_ceil'=>'PF Max Ceil Value','pf_edli_max_ceil'=>'PF EDLI Max Ceil Value'] as $key=>$label): ?>
                            <div class="sc-field">
                                <label><?= esc($label) ?></label>
                                <input type="text" name="<?= esc($key) ?>" class="sc-input" value="<?= esc($global[$key]) ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:18px">
                            <button type="button" class="btn-cancel" onclick="window.location.href='?tab=global'">Cancel</button>
                            <button type="submit" class="btn-save">Save</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="sc-field-grid">
                        <?php foreach([['epf_emp_rate','Employee Contribution Rate'],['epf_employer_rate','Employer Contribution Rate'],['pension_fund','Pension Fund']] as [$key,$label]): ?>
                        <div class="sc-field">
                            <label><?= esc($label) ?></label>
                            <div class="sc-field-row-val">
                                <span class="sc-val-box"><?= esc($global[$key]) ?></span>
                                <span class="sc-val-suffix">of PF Wage</span>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php foreach(['edli'=>'EDLI','pf_admin'=>'PF Admin Charges','edli_admin'=>'EDLI Admin Charges','pf_max_ceil'=>'PF Max Ceil Value','pf_edli_max_ceil'=>'PF EDLI Max Ceil Value'] as $key=>$label): ?>
                        <div class="sc-field">
                            <label><?= esc($label) ?></label>
                            <div class="sc-val-line"><?= esc($global[$key]) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <hr class="sc-divider">

                    <div class="sc-section-head">
                        <div class="sc-section-title">Employee State Insurance (ESI)</div>
                        <?php if ($edit_section !== 'esi'): ?>
                        <button class="btn-edit-link" onclick="startEdit('esi')">
                            <i class="fa-regular fa-pen-to-square"></i> Edit Details
                        </button>
                        <?php endif; ?>
                    </div>

                    <p class="sc-section-note">
                        Note : ESI Contribution for each month should be deposited to the ESIC with in the 21st of the following month.
                    </p>

                    <?php if ($edit_section === 'esi'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_global_esi">
                        <div class="sc-field-grid">
                            <div class="sc-field">
                                <label>Employees' Contribution</label>
                                <div class="sc-input-with-suffix">
                                    <input type="text" name="esi_emp" class="sc-input" value="<?= esc($global['esi_emp']) ?>">
                                    <span class="sc-val-suffix">of Gross Pay</span>
                                </div>
                            </div>
                            <div class="sc-field">
                                <label>Employer's Contribution</label>
                                <div class="sc-input-with-suffix">
                                    <input type="text" name="esi_employer" class="sc-input" value="<?= esc($global['esi_employer']) ?>">
                                    <span class="sc-val-suffix">of Gross Pay</span>
                                </div>
                            </div>
                            <div class="sc-field">
                                <label>ESI Max Ceiling Value</label>
                                <input type="text" name="esi_max_ceil" class="sc-input" value="<?= esc($global['esi_max_ceil']) ?>">
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:18px">
                            <button type="button" class="btn-cancel" onclick="window.location.href='?tab=global'">Cancel</button>
                            <button type="submit" class="btn-save">Save</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="sc-field-grid">
                        <div class="sc-field">
                            <label>Employees' Contribution</label>
                            <div class="sc-field-row-val">
                                <span class="sc-val-box"><?= esc($global['esi_emp']) ?></span>
                                <span class="sc-val-suffix">of Gross Pay</span>
                            </div>
                        </div>
                        <div class="sc-field">
                            <label>Employer's Contribution</label>
                            <div class="sc-field-row-val">
                                <span class="sc-val-box"><?= esc($global['esi_employer']) ?></span>
                                <span class="sc-val-suffix">of Gross Pay</span>
                            </div>
                        </div>
                        <div class="sc-field">
                            <label>ESI Max Ceiling Value</label>
                            <div class="sc-val-line"><?= esc($global['esi_max_ceil']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <hr class="sc-divider">

                    <div class="sc-section-title" style="margin-bottom:6px">
                        Tax Declaration Form (Enable Tax Declaration Form On These Dates)
                    </div>
                    <p class="sc-section-note">Note : Can add multiple start date &amp; end date to enable tax declaration form on these dates</p>
                </div>

                <div class="sc-tab-pane <?= $active_tab==='employee'?'active':'' ?>" id="tab-employee">

                    <form method="GET" style="display:contents">
                        <input type="hidden" name="tab" value="employee">
                        <div class="sc-emp-search">
                            <div class="sc-search-wrap">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" name="emp_q" class="sc-search-input"
                                    placeholder="Search by name or #code"
                                    value="<?= esc($emp_query) ?>">
                            </div>
                            <button type="submit" class="btn-get-details">Get Details</button>
                        </div>
                    </form>

                    <?php if ($emp_query && !$emp_found): ?>
                    <p style="color:#ef4444;font-size:13px;margin-bottom:16px">
                        No employee found for "<?= esc($emp_query) ?>". Please check the name or code.
                    </p>

                    <?php elseif ($emp_found): ?>
                    <div class="sc-emp-name">
                        <?= esc($emp_found['name']) ?> - <?= esc($emp_found['code']) ?>
                    </div>

                    <div class="sc-section-head">
                        <div class="sc-section-title">Employees' Provident Fund (EPF)</div>
                        <?php if ($edit_section !== 'emp_epf'): ?>
                        <button class="btn-edit-link" onclick="startEdit('emp_epf')">
                            <i class="fa-regular fa-pen-to-square"></i> Edit Details
                        </button>
                        <?php endif; ?>
                    </div>

                    <p class="sc-section-note">
                        Note : ESI Contribution for each month should be deposited to the ESIC with in the 15th of the following month.
                    </p>

                    <?php if ($edit_section === 'emp_epf'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_emp_epf">
                        <input type="hidden" name="emp_id" value="<?= (int)$emp_found['id'] ?>">
                        <input type="hidden" name="emp_q" value="<?= esc($emp_query) ?>">

                        <div class="sc-field-grid">
                            <?php foreach([['epf_emp_rate','Employee Contribution Rate'],['epf_employer_rate','Employer Contribution Rate'],['pension_fund','Pension Fund']] as [$key,$label]): ?>
                            <div class="sc-field">
                                <label><?= esc($label) ?></label>
                                <div class="sc-input-with-suffix">
                                    <input type="text" name="<?= esc($key) ?>" class="sc-input" value="<?= esc($emp_found[$key]) ?>">
                                    <span class="sc-val-suffix">of PF Wage</span>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php foreach(['edli'=>'EDLI','pf_admin'=>'PF Admin Charges','edli_admin'=>'EDLI Admin Charges','pf_max_ceil'=>'PF Max Ceil Value','pf_edli_max_ceil'=>'PF EDLI Max Ceil Value'] as $key=>$label): ?>
                            <div class="sc-field">
                                <label><?= esc($label) ?></label>
                                <input type="text" name="<?= esc($key) ?>" class="sc-input" value="<?= esc($emp_found[$key]) ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:18px">
                            <button type="button" class="btn-cancel"
                                onclick="window.location.href='?tab=employee&emp_q=<?= urlencode($emp_query) ?>'">Cancel</button>
                            <button type="submit" class="btn-save">Save</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="sc-field-grid">
                        <?php foreach([['epf_emp_rate','Employee Contribution Rate'],['epf_employer_rate','Employer Contribution Rate'],['pension_fund','Pension Fund']] as [$key,$label]): ?>
                        <div class="sc-field">
                            <label><?= esc($label) ?></label>
                            <div class="sc-field-row-val">
                                <span class="sc-val-box"><?= esc($emp_found[$key]) ?></span>
                                <span class="sc-val-suffix">of PF Wage</span>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php foreach(['edli'=>'EDLI','pf_admin'=>'PF Admin Charges','edli_admin'=>'EDLI Admin Charges','pf_max_ceil'=>'PF Max Ceil Value','pf_edli_max_ceil'=>'PF EDLI Max Ceil Value'] as $key=>$label): ?>
                        <div class="sc-field">
                            <label><?= esc($label) ?></label>
                            <div class="sc-val-line"><?= esc($emp_found[$key] ?: '—') ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <hr class="sc-divider">

                    <div class="sc-section-title" style="margin-bottom:6px">Employee State Insurance (ESI)</div>
                    <p class="sc-section-note">
                        Note : ESI Contribution for each month should be deposited to the ESIC with in the 21st of the following month.
                    </p>
                    <div class="sc-field-grid">
                        <div class="sc-field">
                            <label>Employees' Contribution</label>
                            <div class="sc-field-row-val">
                                <span class="sc-val-box"><?= esc($emp_found['esi_emp']) ?></span>
                                <span class="sc-val-suffix">of Gross Pay</span>
                            </div>
                        </div>
                        <div class="sc-field">
                            <label>Employer's Contribution</label>
                            <div class="sc-field-row-val">
                                <span class="sc-val-box"><?= esc($emp_found['esi_employer']) ?></span>
                                <span class="sc-val-suffix">of Gross Pay</span>
                            </div>
                        </div>
                        <div class="sc-field">
                            <label>ESI Max Ceiling Value</label>
                            <div class="sc-val-line"><?= esc($emp_found['esi_max_ceil']) ?></div>
                        </div>
                    </div>

                    <hr class="sc-divider">

                    <div class="sc-section-title" style="margin-bottom:6px">
                        Tax Declaration Form (Enable Tax Declaration Form On These Dates)
                    </div>
                    <p class="sc-section-note">
                        Note : Can add multiple start date &amp; end date to enable tax declaration form on these dates
                    </p>

                    <form method="POST">
                        <input type="hidden" name="action" value="save_tax_dates">
                        <input type="hidden" name="emp_id" value="<?= (int)$emp_found['id'] ?>">
                        <input type="hidden" name="emp_q" value="<?= esc($emp_query) ?>">
                        <div class="sc-date-row">
                            <div class="sc-date-field">
                                <label>Start Date</label>
                                <div class="sc-date-input-wrap">
                                    <input type="date" name="tax_start" value="<?= esc($emp_found['tax_start']) ?>">
                                    <i class="fa-regular fa-calendar"></i>
                                </div>
                            </div>
                            <div class="sc-date-field">
                                <label>End Date</label>
                                <div class="sc-date-input-wrap">
                                    <input type="date" name="tax_end" value="<?= esc($emp_found['tax_end']) ?>">
                                    <i class="fa-regular fa-calendar"></i>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:12px;margin:18px 0 10px">
                            <button type="submit" class="btn-save">Save Dates</button>
                        </div>
                    </form>

                    <?php else: ?>
                    <p style="color:#9ca3af;font-size:13.5px">
                        Search for an employee above to view or edit their statutory configuration.
                    </p>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="sc-toast" id="scToastEl">
    <span id="scToastIcon">✅</span><span id="scToastMsg">Done!</span>
</div>

<script>
function scToast(icon, msg) {
    var t = document.getElementById('scToastEl');
    document.getElementById('scToastIcon').textContent = icon;
    document.getElementById('scToastMsg').textContent = msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(function(){
        t.classList.remove('show');
    }, 3200);
}

function switchTab(tab) {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', tab);
  url.searchParams.delete('edit');
  <?php if ($emp_query): ?>
  url.searchParams.set('emp_q', '<?= addslashes($emp_query) ?>');
  <?php endif; ?>
  window.location.href = url.toString();
}

function startEdit(section) {
  const url = new URL(window.location.href);
  url.searchParams.set('edit', section);
  window.location.href = url.toString();
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>