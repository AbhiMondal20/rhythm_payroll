<?php
require_once 'includes/config.php';
require_once 'includes/db_client.php';
$page_title = 'Salary Components Category';

ob_start();

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Client database connection not found.");
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

/* Create table if not exists */
$conn->query("
    CREATE TABLE IF NOT EXISTS salary_component_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL UNIQUE,
        esi TINYINT(1) NOT NULL DEFAULT 0,
        pf TINYINT(1) NOT NULL DEFAULT 0,
        pt TINYINT(1) NOT NULL DEFAULT 0,
        tds TINYINT(1) NOT NULL DEFAULT 0,
        ctc TINYINT(1) NOT NULL DEFAULT 0,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$flash = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['category_name'] ?? '');

    $esi = isset($_POST['esi']) ? 1 : 0;
    $pf  = isset($_POST['pf'])  ? 1 : 0;
    $pt  = isset($_POST['pt'])  ? 1 : 0;
    $tds = isset($_POST['tds']) ? 1 : 0;
    $ctc = isset($_POST['ctc']) ? 1 : 0;

    if ($name === '') {
        $flash = 'Category Name is required.';
        $flash_type = 'error';
    } elseif ($action === 'add_category') {

        $stmt = $conn->prepare("
            INSERT INTO salary_component_categories
            (name, esi, pf, pt, tds, ctc, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
        ");

        if (!$stmt) {
            $flash = 'Prepare failed: ' . $conn->error;
            $flash_type = 'error';
        } else {
            $stmt->bind_param("siiiii", $name, $esi, $pf, $pt, $tds, $ctc);

            if ($stmt->execute()) {
                $flash = "Category \"$name\" added successfully.";
                $flash_type = 'success';
            } else {
                $flash = $stmt->errno === 1062 ? 'This category already exists.' : 'Save failed: ' . $stmt->error;
                $flash_type = 'error';
            }

            $stmt->close();
        }

    } elseif ($action === 'edit_category') {

        $id = (int)($_POST['edit_id'] ?? 0);

        if ($id <= 0) {
            $flash = 'Invalid category.';
            $flash_type = 'error';
        } else {
            $stmt = $conn->prepare("
                UPDATE salary_component_categories
                SET name = ?,
                    esi = ?,
                    pf = ?,
                    pt = ?,
                    tds = ?,
                    ctc = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            if (!$stmt) {
                $flash = 'Prepare failed: ' . $conn->error;
                $flash_type = 'error';
            } else {
                $stmt->bind_param("siiiiii", $name, $esi, $pf, $pt, $tds, $ctc, $id);

                if ($stmt->execute()) {
                    $flash = "Category updated successfully.";
                    $flash_type = 'success';
                } else {
                    $flash = $stmt->errno === 1062 ? 'This category already exists.' : 'Update failed: ' . $stmt->error;
                    $flash_type = 'error';
                }

                $stmt->close();
            }
        }
    }
}

/* Fetch categories */
$categories = [];
$res = $conn->query("
    SELECT id, name, esi, pf, pt, tds, ctc, status
    FROM salary_component_categories
    WHERE status = 'active'
    ORDER BY name ASC
");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.scc-wrapper {
    padding: 28px 32px;
    font-family: 'Segoe UI', sans-serif;
    color: #1e2d3d;
    background: #fff;
    min-height: calc(100vh - 64px);
}

.cfg-tabs {
    display: flex;
    align-items: center;
    border-bottom: 1px solid #E5E7EB;
    background: #fff;
    overflow-x: auto;
    scrollbar-width: none;
}
.cfg-tabs::-webkit-scrollbar { display: none; }

.cfg-tab {
    padding: 14px 20px;
    font-size: 13.5px;
    font-weight: 500;
    color: #6B7280;
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
.cfg-tab.active {
    color: #2563EB;
    border-bottom-color: #2563EB;
    font-weight: 600;
}

.scc-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
    gap: 14px;
    flex-wrap: wrap;
}

.scc-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    color: #555;
    flex-wrap: wrap;
}
.scc-breadcrumb a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}
.scc-breadcrumb a:hover { text-decoration: underline; }
.scc-breadcrumb .sep { color: #aaa; }

.btn-add {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 9px 18px;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background .18s;
    text-decoration: none;
}
.btn-add:hover { background: #1d4ed8; }

.scc-instructions {
    font-size: 12.5px;
    color: #6b7280;
    margin: 10px 0 22px;
    line-height: 1.6;
}
.scc-instructions strong { color: #374151; }

.scc-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
   
    border: 1px solid #e8ecf0;

}
.scc-card-header {
    padding: 16px 22px;
    font-weight: 700;
    font-size: 14.5px;
    color: #1e2d3d;
    border-bottom: 1px solid #e8ecf0;
}

.scc-table-wrap {
    width: 100%;
    overflow-x: auto;
     overflow-y: scroll;
    max-height: 480px;
}

table.scc-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 780px;
    
}
table.scc-table thead th {
    background: #f8fafc;
    padding: 11px 18px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 1px solid #e8ecf0;
}
table.scc-table thead th:not(:first-child) {
    text-align: center;
}
table.scc-table tbody tr {
    border-bottom: 1px solid #f1f4f8;
    transition: background .12s;
}
table.scc-table tbody tr:last-child { border-bottom: none; }
table.scc-table tbody tr:hover { background: #f9fafb; }
table.scc-table tbody td {
    padding: 11px 18px;
    font-size: 13.5px;
    color: #374151;
}
table.scc-table tbody td:not(:first-child) {
    text-align: center;
}

.check-icon {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #d1d5db;
    background: #fff;
}
.check-icon.active {
    background: #22c55e;
    border-color: #22c55e;
    color: #fff;
    font-size: 11px;
}

.btn-edit {
    background: none;
    border: none;
    cursor: pointer;
    color: #2563eb;
    font-size: 15px;
    padding: 5px 8px;
    border-radius: 4px;
    transition: background .15s;
}
.btn-edit:hover { background: #eff6ff; }

.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.35);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 18px;
}
.modal-overlay.show { display: flex; }

.modal-box {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 620px;
    padding: 32px 36px 28px;
    box-shadow: 0 12px 40px rgba(0,0,0,.18);
    animation: modalIn .22s ease;
}
@keyframes modalIn {
    from { transform: translateY(-16px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-title {
    font-size: 15px;
    font-weight: 700;
    color: #1e2d3d;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.modal-subtitle {
    font-size: 12.5px;
    color: #6b7280;
    margin-bottom: 22px;
}

.form-group { margin-bottom: 22px; }
.form-label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}
.form-label .req { color: #ef4444; }

.form-input {
    width: 100%;
    border: none;
    border-bottom: 2px solid #d1d5db;
    padding: 8px 2px;
    font-size: 14px;
    color: #1e2d3d;
    outline: none;
    background: transparent;
    transition: border-color .18s;
    box-sizing: border-box;
}
.form-input:focus { border-color: #2563eb; }

.checkbox-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 30px;
    margin-top: 6px;
}
.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
    color: #374151;
    cursor: pointer;
}
.checkbox-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #2563eb;
    cursor: pointer;
}

.modal-actions {
    display: flex;
    justify-content: center;
    gap: 14px;
    margin-top: 28px;
    flex-wrap: wrap;
}
.btn-cancel {
    padding: 9px 28px;
    border: 1.5px solid #d1d5db;
    background: #fff;
    border-radius: 6px;
    font-size: 13.5px;
    color: #374151;
    cursor: pointer;
    font-weight: 600;
    transition: background .15s;
}
.btn-cancel:hover { background: #f1f5f9; }

.btn-submit {
    padding: 9px 28px;
    background: #2563eb;
    border: none;
    border-radius: 6px;
    font-size: 13.5px;
    color: #fff;
    cursor: pointer;
    font-weight: 600;
    transition: background .15s;
}
.btn-submit:hover { background: #1d4ed8; }

.flash-msg {
    padding: 11px 18px;
    border-radius: 7px;
    font-size: 13px;
    margin-bottom: 16px;
    font-weight: 500;
}
.flash-msg.success { background: #dcfce7; color: #166534; }
.flash-msg.error { background: #fee2e2; color: #991b1b; }

@media(max-width: 700px) {
    .scc-wrapper { padding: 20px 14px; }
    .modal-box { padding: 24px 20px; }
    .checkbox-grid { grid-template-columns: 1fr; }
    .btn-add { width: 100%; justify-content: center; }
    .modal-actions button { width: 100%; }
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
    <div class="cfg-tabs">
        <?php foreach(['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
            <a href="configuration#<?= e($k) ?>" class="cfg-tab <?= $k==='Payroll'?'active':'' ?>">
                <?= e($l) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="scc-wrapper">

        <?php if ($flash): ?> 
            <div class="flash-msg <?= e($flash_type) ?>">
                <?= e($flash) ?>
            </div>
        <?php endif; ?>

        <div class="scc-top-bar">
            <nav class="scc-breadcrumb">
                <a href="configuration#Payroll">Payroll</a>
                <span class="sep"><i class="fa-solid fa-chevron-right" style="font-size:10px"></i></span>
                <span>Salary Components Category</span>
            </nav>

            <button type="button" class="btn-add" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Add New Category
            </button>
        </div>

        <p class="scc-instructions">
            <strong>Instructions:</strong><br>
            Define salary component categories and select whether PF, ESI, PT, TDS and CTC are applicable.
        </p>

        <div class="scc-card">
            <div class="scc-card-header">List of Salary Component Categories</div>

            <div class="scc-table-wrap">
                <table class="scc-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>ESI</th>
                            <th>PF</th>
                            <th>PT</th>
                            <th>TDS</th>
                            <th>CTC</th>
                            <th>Action Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= e($cat['name']) ?></td>

                                <?php foreach (['esi','pf','pt','tds','ctc'] as $col): ?>
                                    <td>
                                        <span class="check-icon <?= !empty($cat[$col]) ? 'active' : '' ?>">
                                            <?php if (!empty($cat[$col])): ?>
                                                <i class="fa-solid fa-check"></i>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>

                                <td>
                                    <button type="button"
                                            class="btn-edit"
                                            onclick='openEditModal(<?= json_encode([
                                                "id" => (int)$cat["id"],
                                                "name" => $cat["name"],
                                                "esi" => (int)$cat["esi"],
                                                "pf" => (int)$cat["pf"],
                                                "pt" => (int)$cat["pt"],
                                                "tds" => (int)$cat["tds"],
                                                "ctc" => (int)$cat["ctc"],
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                            title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;color:#9ca3af;padding:28px">
                                    No categories found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-title">Add New Category</div>
        <p class="modal-subtitle">Select if PF, ESI, PT, TDS, CTC would be a part of employee's salary structure</p>

        <form method="POST">
            <input type="hidden" name="action" value="add_category">

            <div class="form-group">
                <label class="form-label"><span class="req">*</span> Category Name</label>
                <input type="text" name="category_name" class="form-input" placeholder="Enter category name" required>
            </div>

            <div class="checkbox-grid">
                <label class="checkbox-item"><input type="checkbox" name="esi"> ESI (Employee State Insurance)</label>
                <label class="checkbox-item"><input type="checkbox" name="pf"> PF (Provident Fund)</label>
                <label class="checkbox-item"><input type="checkbox" name="tds"> TDS (Tax Deducted at Source)</label>
                <label class="checkbox-item"><input type="checkbox" name="ctc"> CTC (Cost To Company)</label>
                <label class="checkbox-item"><input type="checkbox" name="pt"> PT (Professional Tax)</label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn-submit">Add</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-title">Edit Category</div>
        <p class="modal-subtitle">Update PF, ESI, PT, TDS, CTC settings for this salary component</p>

        <form method="POST">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="edit_id" id="edit_id">

            <div class="form-group">
                <label class="form-label"><span class="req">*</span> Category Name</label>
                <input type="text" name="category_name" id="edit_name" class="form-input" required>
            </div>

            <div class="checkbox-grid">
                <label class="checkbox-item"><input type="checkbox" name="esi" id="edit_esi"> ESI (Employee State Insurance)</label>
                <label class="checkbox-item"><input type="checkbox" name="pf" id="edit_pf"> PF (Provident Fund)</label>
                <label class="checkbox-item"><input type="checkbox" name="tds" id="edit_tds"> TDS (Tax Deducted at Source)</label>
                <label class="checkbox-item"><input type="checkbox" name="ctc" id="edit_ctc"> CTC (Cost To Company)</label>
                <label class="checkbox-item"><input type="checkbox" name="pt" id="edit_pt"> PT (Professional Tax)</label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-submit">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('show');
}

function openEditModal(data) {
    document.getElementById('edit_id').value = data.id || '';
    document.getElementById('edit_name').value = data.name || '';
    document.getElementById('edit_esi').checked = !!Number(data.esi);
    document.getElementById('edit_pf').checked = !!Number(data.pf);
    document.getElementById('edit_pt').checked = !!Number(data.pt);
    document.getElementById('edit_tds').checked = !!Number(data.tds);
    document.getElementById('edit_ctc').checked = !!Number(data.ctc);
    document.getElementById('editModal').classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('show');
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
            overlay.classList.remove('show');
        });
    }
});

<?php if ($flash_type === 'error' && ($_POST['action'] ?? '') === 'add_category'): ?>
openAddModal();
<?php endif; ?>
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>