<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}
require_once 'includes/config.php';
require_once 'includes/db_client.php';

$page_title = 'Salary Components';

function esc($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/* ─────────────────────────────────────────
   FETCH ALL CATEGORIES FOR DROPDOWNS
───────────────────────────────────────── */
$allCategories = [];
$catQuery = mysqli_query($conn, "SELECT `id`, `name` FROM `salary_component_categories` WHERE status = 'active' ORDER BY name");
if ($catQuery) {
    while ($row = mysqli_fetch_assoc($catQuery)) {
        $allCategories[] = $row['name'];
    }
}

/* ── mode & tab ── */
$active_tab = $_GET['tab']  ?? 'earnings';
$mode       = $_GET['mode'] ?? 'list';
$edit_code  = $_GET['code'] ?? '';

$save_ok = false;
$save_msg = '';
$save_error = '';

/* ─────────────────────────────────────────
   POST: ADD / SAVE / DELETE
───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    if ($act === 'add') {
        $salary_type = trim($_POST['salary_type'] ?? 'Earning');

        if ($salary_type === 'Employer Contribution') {
            $salary_type = 'Employer';
        }

        $component_category = trim($_POST['component_category'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $component_name = trim($_POST['component_name'] ?? '');
        $expression = trim($_POST['expression'] ?? '');

        if ($code === '' || $component_name === '') {
            $save_error = 'Please fill in all required fields.';
            $mode = 'add';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO salary_components
                (salary_type, component_category, code, component_name, expression, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");

            if ($stmt) {
                $stmt->bind_param("sssss", $salary_type, $component_category, $code, $component_name, $expression);

                if ($stmt->execute()) {
                    $save_ok = true;
                    $save_msg = 'Salary component added!';
                    $mode = 'list';

                    if ($salary_type === 'Deduction') {
                        $active_tab = 'deductions';
                    } elseif ($salary_type === 'Employer') {
                        $active_tab = 'employer';
                    } else {
                        $active_tab = 'earnings';
                    }
                } else {
                    $save_error = 'Save failed: ' . $stmt->error;
                    $mode = 'add';
                }
            } else {
                $save_error = 'Prepare failed: ' . $conn->error;
                $mode = 'add';
            }
        }
    }

    if ($act === 'save') {
        $original_code = trim($_POST['original_code'] ?? '');
        $salary_type = trim($_POST['salary_type'] ?? 'Earning');

        if ($salary_type === 'Employer Contribution') {
            $salary_type = 'Employer';
        }

        $component_category = trim($_POST['component_category'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $component_name = trim($_POST['component_name'] ?? '');
        $expression = trim($_POST['expression'] ?? '');

        if ($original_code === '' || $code === '' || $component_name === '') {
            $save_error = 'Please fill in all required fields.';
            $mode = 'edit';
            $edit_code = $original_code;
        } else {
            $stmt = $conn->prepare("
                UPDATE salary_components
                SET salary_type = ?,
                    component_category = ?,
                    code = ?,
                    component_name = ?,
                    expression = ?,
                    updated_at = NOW()
                WHERE code = ?
            ");

            if ($stmt) {
                $stmt->bind_param("ssssss", $salary_type, $component_category, $code, $component_name, $expression, $original_code);

                if ($stmt->execute()) {
                    $save_ok = true;
                    $save_msg = 'Salary component updated!';
                    $mode = 'list';

                    if ($salary_type === 'Deduction') {
                        $active_tab = 'deductions';
                    } elseif ($salary_type === 'Employer') {
                        $active_tab = 'employer';
                    } else {
                        $active_tab = 'earnings';
                    }
                } else {
                    $save_error = 'Update failed: ' . $stmt->error;
                    $mode = 'edit';
                    $edit_code = $original_code;
                }
            } else {
                $save_error = 'Prepare failed: ' . $conn->error;
                $mode = 'edit';
                $edit_code = $original_code;
            }
        }
    }

    if ($act === 'delete') {
        $code = trim($_POST['code'] ?? '');

        if ($code !== '') {
            $stmt = $conn->prepare("
                UPDATE salary_components
                SET status = 'inactive', updated_at = NOW()
                WHERE code = ?
            ");

            if ($stmt) {
                $stmt->bind_param("s", $code);

                if ($stmt->execute()) {
                    $save_ok = true;
                    $save_msg = 'Salary component deleted!';
                    $mode = 'list';
                } else {
                    $save_error = 'Delete failed: ' . $stmt->error;
                }
            } else {
                $save_error = 'Prepare failed: ' . $conn->error;
            }
        }
    }
}

/* ─────────────────────────────────────────
   FETCH DATA FROM DB
───────────────────────────────────────── */
$earnings = [];
$deductions = [];
$employer = [];

$res = $conn->query("
    SELECT id, salary_type, component_category, code, component_name, expression
    FROM salary_components
    WHERE status = 'active'
    ORDER BY salary_type ASC, component_name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $item = [
            'id'       => (int)$row['id'],
            'type'     => $row['salary_type'],
            'category' => $row['component_category'],
            'code'     => $row['code'],
            'name'     => $row['component_name'],
            'expr'     => $row['expression'],
        ];

        if ($row['salary_type'] === 'Deduction') {
            $deductions[] = $item;
        } elseif ($row['salary_type'] === 'Employer') {
            $employer[] = $item;
        } else {
            $earnings[] = $item;
        }
    }
}

/* find edit record */
$edit_rec = null;

if ($mode === 'edit' && $edit_code !== '') {
    $stmt = $conn->prepare("
        SELECT id, salary_type, component_category, code, component_name, expression
        FROM salary_components
        WHERE code = ?
        AND status = 'active'
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("s", $edit_code);
        $stmt->execute();
        $editRes = $stmt->get_result();

        if ($editRes && $editRes->num_rows > 0) {
            $r = $editRes->fetch_assoc();

            $edit_rec = [
                'id'       => (int)$r['id'],
                'type'     => $r['salary_type'],
                'category' => $r['component_category'],
                'code'     => $r['code'],
                'name'     => $r['component_name'],
                'expr'     => $r['expression'],
            ];
        }
    }
}
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
/* ════════════════════════════════════════
   SALARY COMPONENTS PAGE
════════════════════════════════════════ */

/* config tab bar */
.cfg-tabs {
    display:flex;align-items:center;border-bottom:1px solid #E5E7EB;
    background:#fff;overflow-x:auto;scrollbar-width:none;
}
.cfg-tabs::-webkit-scrollbar { display:none; }
.cfg-tab {
    padding:14px 20px;font-size:13.5px;font-weight:500;color:#6B7280;
    cursor:pointer;border:none;background:transparent;
    border-bottom:2.5px solid transparent;white-space:nowrap;
    transition:color .15s,border-color .15s;text-decoration:none;
    display:block;margin-bottom:-1px;
}
.cfg-tab:hover  { color:#111827; }
.cfg-tab.active { color:#2563EB;border-bottom-color:#2563EB;font-weight:600; }

/* breadcrumb */
.sc-bc {
    display:flex;align-items:center;gap:8px;font-size:13.5px;
    font-weight:500;color:#374151;
}
.sc-bc a      { color:#374151;text-decoration:none; }
.sc-bc a:hover{ color:#2563EB; }
.sc-bc .sep   { color:#D1D5DB;font-size:16px; }
.sc-bc .cur   { font-weight:600;color:#374151; }

/* top bar */
.sc-topbar {
    padding:14px 24px;border-bottom:1px solid #E5E7EB;
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:10px;
}

/* ── type tabs (Earnings / Deductions / Employer) ── */
.sc-tabs {
    display:flex;align-items:center;gap:0;padding:0 24px;
    border-bottom:1px solid #E5E7EB;background:#fff;
}
.sc-tab {
    padding:12px 18px;font-size:13.5px;font-weight:500;color:#6B7280;
    cursor:pointer;border:none;background:transparent;
    border-bottom:2.5px solid transparent;white-space:nowrap;
    transition:color .15s,border-color .15s;text-decoration:none;
    display:block;margin-bottom:-1px;font-family:inherit;
}
.sc-tab:hover  { color:#111827; }
.sc-tab.active { color:#2563EB;border-bottom-color:#2563EB;font-weight:600; }

/* search */
.sc-search {
    display:flex;align-items:center;gap:8px;padding:8px 12px;
    border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;
    width:230px;transition:border-color .15s;
}
.sc-search:focus-within { border-color:#2563EB; }
.sc-search svg { width:14px;height:14px;stroke:#9CA3AF;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0; }
.sc-search input { border:none;outline:none;font-size:13px;font-family:inherit;color:#374151;background:transparent;width:100%; }

/* table */
.sc-table-wrap { overflow-y:auto;max-height:430px;overflow-x:auto; }
.sc-table { width:100%;border-collapse:collapse;font-size:13.5px; }
.sc-table thead tr { background:#fff;position:sticky;top:0;z-index:5; }
.sc-table th {
    padding:12px 18px;text-align:left;font-weight:600;color:#374151;
    font-size:13.5px;border-bottom:1px solid #E5E7EB;
    white-space:nowrap;background:#fff;
}
.sc-table td {
    padding:14px 18px;border-bottom:1px solid #F3F4F6;
    color:#374151;vertical-align:middle;
}
.sc-table tbody tr:hover td { background:#F9FAFB; }
.sc-table tr:last-child td  { border-bottom:none; }

/* code column */
.sc-code {
    font-size:13.5px;font-weight:600;color:#111827;
}
/* expression column */
.sc-expr {
    font-family:'Courier New',monospace;font-size:13px;color:#374151;
}
/* edit icon button */
.sc-edit-btn {
    color:#2563EB;cursor:pointer;font-size:16px;
    background:none;border:none;padding:4px 6px;
    border-radius:5px;transition:background .15s;
    text-decoration:none;display:inline-flex;align-items:center;
}
.sc-edit-btn:hover { background:#EFF6FF; }
/* action items col */
.sc-table th:last-child,
.sc-table td:last-child { text-align:center;width:100px; }

/* ── ADD / EDIT FORM ── */
.sc-form-wrap { padding:28px 28px 32px; }
.sc-form-title {
    font-size:14px;font-weight:700;color:#111827;
    letter-spacing:.4px;text-transform:uppercase;
    margin-bottom:24px;
}
/* underline field style (matching screenshots) */
.sc-row { display:grid;gap:24px;margin-bottom:24px; }
.sc-row.c2 { grid-template-columns:1fr 1fr; }
.sc-row.c1 { grid-template-columns:1fr; }
.sc-fg { display:flex;flex-direction:column;gap:5px; }
.sc-fg label { font-size:13px;font-weight:400;color:#374151; }
.sc-fg input,
.sc-fg select {
    border:none;border-bottom:1.5px solid #D1D5DB;border-radius:0;
    padding:8px 0;font-size:14px;font-family:inherit;
    color:#111827;outline:none;background:transparent;
    width:100%;transition:border-color .15s;
}
.sc-fg input:focus,.sc-fg select:focus { border-bottom-color:#2563EB; }
.sc-fg input::placeholder { color:#D1D5DB; }
/* expression field — full width */
.sc-fg.full { grid-column:1 / -1; }

/* statutory accordion */
.sc-stat-head {
    display:flex;align-items:center;justify-content:space-between;
    cursor:pointer;padding:12px 0;
    border-top:1px solid #E5E7EB;margin-top:8px;
    font-size:14px;font-weight:500;color:#374151;
    user-select:none;
}
.sc-stat-head svg { width:16px;height:16px;stroke:#9CA3AF;fill:none;stroke-width:2;stroke-linecap:round;transition:transform .2s; }
.sc-stat-head.open svg { transform:rotate(180deg); }
.sc-stat-body { display:none;padding:8px 0 14px;animation:scFade .2s ease; }
.sc-stat-body.open { display:block; }
@keyframes scFade { from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)} }
.sc-stat-item {
    font-size:13.5px;color:#C4C9D4;padding:7px 0;
    display:flex;align-items:center;gap:10px;
}
.sc-stat-note {
    font-size:13px;color:#9CA3AF;margin-top:10px;
}
.sc-stat-note a { color:#2563EB;text-decoration:none; }
.sc-stat-note a:hover { text-decoration:underline; }

/* form action buttons */
.sc-form-actions {
    display:flex;justify-content:flex-end;gap:10px;
    padding-top:20px;margin-top:12px;border-top:1px solid #E5E7EB;
}
.sc-cancel-btn {
    padding:9px 24px;background:#fff;color:#374151;
    border:1.5px solid #D1D5DB;border-radius:8px;
    font-size:14px;font-weight:500;cursor:pointer;
    font-family:inherit;transition:.15s;text-decoration:none;
    display:inline-flex;align-items:center;
}
.sc-cancel-btn:hover { border-color:#374151; }
.sc-save-btn {
    padding:9px 28px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
    font-family:inherit;transition:background .15s;
}
.sc-save-btn:hover { background:#1D4ED8; }
.sc-delete-btn {
    padding:9px 24px;background:#fff;color:#DC2626;
    border:1.5px solid #DC2626;border-radius:8px;
    font-size:14px;font-weight:500;cursor:pointer;
    font-family:inherit;transition:.15s;
}
.sc-delete-btn:hover { background:#FEE2E2; }

/* toast */
.sc-toast {
    position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
    background:#111827;color:#fff;padding:11px 20px;border-radius:10px;
    font-size:13px;font-weight:500;z-index:999;display:flex;align-items:center;
    gap:8px;box-shadow:0 8px 28px rgba(0,0,0,.2);transition:transform .3s ease;white-space:nowrap;
}
.sc-toast.show { transform:translateX(-50%) translateY(0); }

/* responsive */
@media(max-width:700px){
    .sc-row.c2 { grid-template-columns:1fr; }
    .sc-topbar  { flex-direction:column;align-items:flex-start; }
}
</style>

<?php if($save_ok): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){ 
    scToast('✅','<?= esc($save_msg) ?>'); 
});
</script>
<?php endif; ?>

<?php if($save_error): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){ 
    scToast('⚠️','<?= esc($save_error) ?>'); 
});
</script>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
    <div class="cfg-tabs">
        <?php foreach(['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
        <a href="configuration#<?= esc($k) ?>" class="cfg-tab <?= $k==='Payroll'?'active':'' ?>"><?= esc($l) ?></a>
        <?php endforeach; ?>
    </div>
    
    <div class="sc-topbar">
        <div class="sc-bc">
            <a href="configuration#Payroll">Payroll</a>
            <span class="sep">›</span>
            <span class="cur">Salary Components</span>
        </div>
        <?php if($mode==='list'): ?>
        <a href="?mode=add" class="btn btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Salary Component
        </a>
        <?php endif; ?>
    </div>

    <?php if($mode==='list'): ?>

    <div style="display:flex;align-items:center;justify-content:space-between;padding:0 24px 0 0;border-bottom:1px solid #E5E7EB;flex-wrap:wrap;gap:0">
        <div class="sc-tabs">
            <?php foreach(['earnings'=>'Earnings','deductions'=>'Deductions','employer'=>'Employer Contribution'] as $tk=>$tl): ?>
            <a href="?tab=<?= esc($tk) ?>&mode=list" class="sc-tab <?= $active_tab===$tk?'active':'' ?>"><?= esc($tl) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="sc-search" style="margin:8px 0">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="scSearchInput" placeholder="Search table items" oninput="filterScTable(this.value)">
        </div>
    </div>

    <?php
    $rows = match($active_tab) {
        'deductions' => $deductions,
        'employer'   => $employer,
        default      => $earnings,
    };
    ?>

    <div class="sc-table-wrap">
    <table class="sc-table" id="scTable">
        <thead>
            <tr>
                <th>Code</th>
                <th>Salary Component</th>
                <th>Expression</th>
                <th>Action Items</th>
            </tr>
        </thead>
        <tbody id="scTableBody">
        <?php if(empty($rows)): ?>
            <tr>
                <td colspan="4" style="text-align:center;color:#9CA3AF;padding:40px 18px">
                    No salary components found.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach($rows as $row): ?>
            <tr data-search="<?= esc(strtolower($row['code'].' '.$row['name'].' '.$row['expr'])) ?>">
                <td><span class="sc-code"><?= esc($row['code']) ?></span></td>
                <td><?= esc($row['name']) ?></td>
                <td><span class="sc-expr"><?= esc($row['expr']) ?></span></td>
                <td>
                    <a href="?mode=edit&code=<?= urlencode($row['code']) ?>&tab=<?= esc($active_tab) ?>" class="sc-edit-btn" title="Edit">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <div style="padding:10px 18px;border-top:1px solid #F3F4F6">
        <span style="font-size:12px;color:#9CA3AF"><?= count($rows) ?> components</span>
    </div>

    <?php elseif($mode==='add'): ?>

    <div class="sc-form-wrap">
        <div class="sc-form-title">NEW SALARY COMPONENT</div>
        <form method="POST" id="addScForm" novalidate>
        <input type="hidden" name="_action" value="add">

        <div class="sc-row c2">
            <div class="sc-fg">
                <label>Salary Type</label>
                <!-- Removed onchange event -->
                <select name="salary_type" id="addSalaryType">
                    <option value="Earning">Earning</option>
                    <option value="Deduction">Deduction</option>
                    <option value="Employer Contribution">Employer Contribution</option>
                </select>
            </div>
            <div class="sc-fg">
                <label>Component Category</label>
                <select name="component_category" id="addCategory">
                    <?php
                    // Load all categories
                    if (!empty($allCategories)) {
                        foreach ($allCategories as $cat) {
                            echo '<option value="'.esc($cat).'">'.esc($cat).'</option>';
                        }
                    } else {
                        echo '<option value="">No categories available</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="sc-row c2">
            <div class="sc-fg">
                <label>Code</label>
                <input type="text" name="code"
                    placeholder="Salary Component Code"
                    oninput="this.value=this.value.toUpperCase().replace(/\s/g,'')" required>
            </div>
            <div class="sc-fg">
                <label>Salary Component Name</label>
                <input type="text" name="component_name"
                    placeholder="Salary Component Name (Basic, HRA, etc.)" required>
            </div>
        </div>

        <div class="sc-row c1">
            <div class="sc-fg">
                <label>Expression</label>
                <input type="text" name="expression" placeholder="Expression"
                    style="font-family:'Courier New',monospace">
            </div>
        </div>

        <div>
            <div class="sc-stat-head" id="statHead" onclick="toggleStat()">
                <span>Statutory Considerations</span>
                <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="sc-stat-body" id="statBody">
                <div class="sc-stat-item">PF (Provident Fund)</div>
                <div class="sc-stat-item">ESI (Employee State Insurance)</div>
                <div class="sc-stat-item">TDS (Tax Deducted at Source)</div>
                <div class="sc-stat-item">CTC (Cost To Company)</div>
                <div class="sc-stat-item">PT (Professional Tax)</div>
                <div class="sc-stat-note">
                    Note: Choose these under <a href="SalaryComponentsCategory">Salary Component Category</a>
                </div>
            </div>
        </div>

        <div class="sc-form-actions">
            <a href="?tab=<?= esc($active_tab) ?>&mode=list" class="sc-cancel-btn">Cancel</a>
            <button type="submit" class="sc-save-btn" onclick="return validateScForm()">Add</button>
        </div>
        </form>
    </div>

    <?php elseif($mode==='edit' && $edit_rec): ?>

    <div class="sc-form-wrap">
        <div class="sc-form-title"><?= esc(strtoupper($edit_rec['name'])) ?></div>

        <form method="POST" id="editScForm" novalidate>
            <input type="hidden" name="_action" value="save">
            <input type="hidden" name="original_code" value="<?= esc($edit_rec['code']) ?>">

            <div class="sc-row c2">
                <div class="sc-fg">
                    <label>Salary Type</label>
                    <!-- Removed onchange event -->
                    <select name="salary_type" id="editSalaryType">
                        <option value="Earning" <?= ($edit_rec['type']??'')==='Earning'?'selected':'' ?>>Earning</option>
                        <option value="Deduction" <?= ($edit_rec['type']??'')==='Deduction'?'selected':'' ?>>Deduction</option>
                        <option value="Employer Contribution" <?= ($edit_rec['type']??'')==='Employer'?'selected':'' ?>>Employer Contribution</option>
                    </select>
                </div>
                <div class="sc-fg">
                    <label>Component Category</label>
                    <select name="component_category" id="editCategory">
                        <?php
                        // Load all categories
                        if (!empty($allCategories)) {
                            foreach ($allCategories as $cat) {
                                $selected = (($edit_rec['category'] ?? '') === $cat) ? 'selected' : '';
                                echo '<option value="'.esc($cat).'" '.$selected.'>'.esc($cat).'</option>';
                            }
                        } else {
                            echo '<option value="">No categories available</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="sc-row c2">
                <div class="sc-fg">
                    <label>Code</label>
                    <input type="text" name="code"
                        value="<?= esc($edit_rec['code']) ?>"
                        oninput="this.value=this.value.toUpperCase().replace(/\s/g,'')" required>
                </div>
                <div class="sc-fg">
                    <label>Salary Component Name</label>
                    <input type="text" name="component_name"
                        value="<?= esc($edit_rec['name']) ?>" required>
                </div>
            </div>

            <div class="sc-row c1">
                <div class="sc-fg">
                    <label>Expression</label>
                    <input type="text" name="expression"
                        value="<?= esc($edit_rec['expr']) ?>"
                        style="font-family:'Courier New',monospace">
                </div>
            </div>

            <div>
                <div class="sc-stat-head open" id="statHead" onclick="toggleStat()">
                    <span>Statutory Considerations</span>
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="sc-stat-body open" id="statBody">
                    <div class="sc-stat-item">PF (Provident Fund)</div>
                    <div class="sc-stat-item">ESI (Employee State Insurance)</div>
                    <div class="sc-stat-item">TDS (Tax Deducted at Source)</div>
                    <div class="sc-stat-item">CTC (Cost To Company)</div>
                    <div class="sc-stat-item">PT (Professional Tax)</div>
                    <div class="sc-stat-note">
                        Note: Choose these under <a href="SalaryComponentsCategory">Salary Component Category</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="sc-form-actions">
            <form method="POST" id="deleteForm" style="display:inline">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="code" value="<?= esc($edit_rec['code']) ?>">
                <button type="submit" class="sc-delete-btn" onclick="return confirmDelete('<?= esc(addslashes($edit_rec['name'])) ?>')">Delete</button>
            </form>

            <a href="?tab=<?= esc($active_tab) ?>&mode=list" class="sc-cancel-btn">Cancel</a>

            <button type="submit" form="editScForm" class="sc-save-btn" onclick="return validateScForm()">Save</button>
        </div>
    </div>

    <?php else: ?>
    <div style="padding:40px 24px;text-align:center;color:#9CA3AF;font-size:13.5px">
        Component not found. <a href="?tab=<?= esc($active_tab) ?>&mode=list" style="color:#2563EB">← Back to list</a>
    </div>
    <?php endif; ?>

</div>

<div class="sc-toast" id="scToastEl">
    <span id="scToastIcon">✅</span><span id="scToastMsg">Done!</span>
</div>

<script>
function scToast(icon, msg) {
    var t=document.getElementById('scToastEl');
    document.getElementById('scToastIcon').textContent=icon;
    document.getElementById('scToastMsg').textContent=msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t=setTimeout(function(){ t.classList.remove('show'); },3200);
}

function filterScTable(q) {
    q=q.toLowerCase().trim();
    document.querySelectorAll('#scTableBody tr').forEach(function(r){
        r.style.display=!q||(r.dataset.search||'').includes(q)?'':'none';
    });
}

function toggleStat() {
    var head=document.getElementById('statHead');
    var body=document.getElementById('statBody');
    if(!head||!body) return;
    head.classList.toggle('open');
    body.classList.toggle('open');
}

function validateScForm() {
    var form = document.getElementById('addScForm') || document.getElementById('editScForm');
    if (!form) return true;

    var ok = true;

    form.querySelectorAll('[required]').forEach(function(el) {
        if (!el.value.trim()) {
            el.style.borderBottomColor='#DC2626';
            ok=false;
        } else {
            el.style.borderBottomColor='';
        }
    });

    if (!ok) {
        scToast('⚠','Please fill in all required fields.');
    }

    return ok;
}

function confirmDelete(name) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) {
        return false;
    }
    scToast('🗑️','Deleting component...');
    return true;
}
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>