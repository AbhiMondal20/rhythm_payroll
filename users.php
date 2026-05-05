<?php
require_once 'includes/config.php';
$page_title = 'Users';

/* ─────────────────────────────────────────
   ACTIVE TAB & VIEW
───────────────────────────────────────── */
$active_tab = $_GET['tab']  ?? 'list';
$view       = $_GET['view'] ?? '';

/* ─────────────────────────────────────────
   DUMMY USERS DATA
───────────────────────────────────────── */
$users = [
    ['id'=>1,  'code'=>'',    'username'=>'ramkrishnaivfsiliguri@gmail.com','display'=>'Admin',              'role'=>'Administrator','active'=>true],
    ['id'=>2,  'code'=>'1006','username'=>'1006@RKIVFCentre.com',           'display'=>'Sumi Das',           'role'=>'Employee',      'active'=>true],
    ['id'=>3,  'code'=>'1008','username'=>'1008@RKIVFCentre.com',           'display'=>'Shubhankar Naha',    'role'=>'Employee',      'active'=>true],
    ['id'=>4,  'code'=>'1009','username'=>'1009@RKIVFCentre.com',           'display'=>'Biswajit Dutta',     'role'=>'Employee',      'active'=>true],
    ['id'=>5,  'code'=>'1011','username'=>'1011@RKIVFCentre.com',           'display'=>'Sujata Roy',         'role'=>'Employee',      'active'=>true],
    ['id'=>6,  'code'=>'1013','username'=>'1013@RKIVFCentre.com',           'display'=>'Priyanka Chakraborty','role'=>'Employee',      'active'=>true],
    ['id'=>7,  'code'=>'1015','username'=>'1015@RKIVFCentre.com',           'display'=>'Babli Mallik',       'role'=>'Employee',      'active'=>true],
    ['id'=>8,  'code'=>'1017','username'=>'1017@RKIVFCentre.com',           'display'=>'Dibakar Sarkar',     'role'=>'Employee',      'active'=>true],
    ['id'=>9,  'code'=>'1018','username'=>'1018@RKIVFCentre.com',           'display'=>'Lal Bahadur Pradhan','role'=>'Employee',      'active'=>true],
    ['id'=>10, 'code'=>'1020','username'=>'1020@RKIVFCentre.com',           'display'=>'Anita Sharma',       'role'=>'Employee',      'active'=>false],
    ['id'=>11, 'code'=>'1022','username'=>'1022@RKIVFCentre.com',           'display'=>'Rahul Verma',        'role'=>'Manager',       'active'=>true],
    ['id'=>12, 'code'=>'1024','username'=>'1024@RKIVFCentre.com',           'display'=>'Kavita Singh',       'role'=>'Edit Tax',      'active'=>true],
];


$roles_list = ['Administrator','Employee','Edit Tax','Manager','WebEmployee'];

/* ─────────────────────────────────────────
   PAGINATION ONLY FOR USER LIST
───────────────────────────────────────── */
$per_page = 10;
$page_no  = max(1, (int)($_GET['page'] ?? 1));

$total_users = count($users);
$total_pages = max(1, (int)ceil($total_users / $per_page));

if ($page_no > $total_pages) {
    $page_no = $total_pages;
}

$offset      = ($page_no - 1) * $per_page;
$paged_users = array_slice($users, $offset, $per_page);

/* detail view user */
$detail_user = null;
if ($view === 'detail' && isset($_GET['id'])) {
    foreach ($users as $u) {
        if ($u['id'] === (int)$_GET['id']) {
            $detail_user = $u;
            break;
        }
    }
}

/* POST handlers */
$save_success = false;
$save_msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'add_user') {
        $save_success = true;
        $save_msg = 'User added successfully!';
    }

    if ($action === 'save_user') {
        $save_success = true;
        $save_msg = 'User updated successfully!';
    }

    if ($action === 'assign_role') {
        $save_success = true;
        $save_msg = 'Roles assigned successfully!';
    }

    if ($action === 'toggle_status') {
        $save_success = true;
        $save_msg = 'Status updated!';
    }
}

function esc($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ════════════════════════════════════════
   USERS PAGE
════════════════════════════════════════ */

.usr-tabs {
    display:flex;align-items:center;gap:0;
}
.usr-tab {
    padding:9px 18px;font-size:13.5px;font-weight:500;color:#6B7280;
    cursor:pointer;border:none;background:transparent;
    border-bottom:2.5px solid transparent;white-space:nowrap;
    transition:color .15s,border-color .15s;text-decoration:none;
    display:inline-block;margin-bottom:-1px;font-family:inherit;
}
.usr-tab:hover  { color:#111827; }
.usr-tab.active { color:#2563EB;border-bottom-color:#2563EB;font-weight:600; }

.usr-bc {
    display:flex;align-items:center;gap:8px;font-size:13.5px;
    font-weight:500;color:#374151;margin-bottom:20px;flex-wrap:wrap;
}
.usr-bc a        { color:#374151;text-decoration:none; }
.usr-bc a:hover  { color:#2563EB; }
.usr-bc .sep     { color:#D1D5DB;font-size:15px; }
.usr-bc .cur     { font-weight:600;color:#374151; }

.usr-search {
    display:flex;align-items:center;gap:8px;padding:8px 14px;
    border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;
    max-width:300px;transition:border-color .15s;
}
.usr-search:focus-within { border-color:#2563EB; }
.usr-search svg { width:14px;height:14px;stroke:#9CA3AF;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0; }
.usr-search input { border:none;outline:none;font-size:13px;font-family:inherit;color:#374151;background:transparent;width:100%; }

.usr-table { width:100%;border-collapse:collapse;font-size:13.5px; }
.usr-table thead tr { background:#F3F4F6; }
.usr-table th {
    padding:11px 16px;text-align:left;font-weight:600;color:#374151;
    font-size:13px;border-bottom:1px solid #E5E7EB;white-space:nowrap;
}
.usr-table td {
    padding:13px 16px;border-bottom:1px solid #F3F4F6;
    color:#374151;vertical-align:middle;
}
.usr-table tr:last-child td { border-bottom:none; }
.usr-table tbody tr:hover td { background:#F9FAFB; }

.usr-toggle { position:relative;width:46px;height:26px;cursor:pointer;display:inline-block; }
.usr-toggle input { opacity:0;width:0;height:0; }
.usr-toggle-sl {
    position:absolute;inset:0;background:#D1D5DB;border-radius:13px;
    cursor:pointer;transition:.2s;
}
.usr-toggle input:checked + .usr-toggle-sl { background:#2563EB; }
.usr-toggle-sl::after {
    content:'';position:absolute;width:20px;height:20px;background:#fff;
    border-radius:50%;top:3px;left:3px;transition:.2s;
    box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.usr-toggle input:checked + .usr-toggle-sl::after { transform:translateX(20px); }

.usr-arrow {
    color:#9CA3AF;cursor:pointer;font-size:16px;padding:0 4px;
    transition:color .15s;user-select:none;
}
.usr-arrow:hover { color:#2563EB; }

.ar-layout { display:grid;grid-template-columns:320px 1fr;gap:0;align-items:start; }
.ar-roles-panel {
    border:1px solid #E5E7EB;border-radius:10px 0 0 10px;
    overflow:hidden;background:#fff;
}
.ar-role-item {
    padding:13px 18px;font-size:13.5px;color:#374151;cursor:pointer;
    border-bottom:1px solid #F9FAFB;transition:background .15s;
    display:flex;align-items:center;gap:10px;
}
.ar-role-item:last-child { border-bottom:none; }
.ar-role-item:hover  { background:#F3F4F6; }
.ar-role-item.selected { background:#EFF6FF;color:#2563EB;font-weight:600; }

.ar-assign-wrap {
    padding:0 0 16px 16px;
}
.ar-assign-btn {
    padding:9px 28px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;
    font-family:inherit;transition:background .15s;
}
.ar-assign-btn:hover { background:#1D4ED8; }

.ar-sel-count {
    display:flex;align-items:center;gap:8px;padding:10px 0;
    font-size:13.5px;color:#374151;font-weight:500;cursor:pointer;
    border-bottom:1px solid #E5E7EB;margin-bottom:0;
}
.ar-sel-count svg { width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round; }

.ar-table { width:100%;border-collapse:collapse;font-size:13.5px; }
.ar-table thead tr { background:#F3F4F6; }
.ar-table th {
    padding:11px 16px;text-align:left;font-weight:600;color:#374151;
    font-size:13px;border-bottom:1px solid #E5E7EB;white-space:nowrap;
}
.ar-table td {
    padding:12px 16px;border-bottom:1px solid #F3F4F6;
    color:#374151;vertical-align:middle;
}
.ar-table tr:last-child td { border-bottom:none; }
.ar-table tbody tr:hover td { background:#F9FAFB; }
.ar-table input[type=checkbox] {
    width:16px;height:16px;accent-color:#2563EB;cursor:pointer;
}

.ud-form-wrap { background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:28px 24px; }

.ud-row { display:grid;gap:28px;margin-bottom:28px; }
.ud-row.c4 { grid-template-columns:1fr 1fr 1fr 1fr; }
.ud-row.c3 { grid-template-columns:1fr 1fr 1fr; }
.ud-row.c2 { grid-template-columns:1fr 1fr 1fr; }
.ud-row.c1 { grid-template-columns:1fr; }

.ud-fg { display:flex;flex-direction:column;gap:6px; }
.ud-fg label {
    font-size:13px;font-weight:400;color:#374151;
}
.ud-fg input,
.ud-fg select {
    border:none;border-bottom:1.5px solid #D1D5DB;
    border-radius:0;padding:6px 0;font-size:13.5px;
    font-family:inherit;color:#111827;outline:none;
    background:transparent;transition:border-color .15s;
    width:100%;
}
.ud-fg input:focus,
.ud-fg select:focus { border-bottom-color:#2563EB; }
.ud-fg input::placeholder { color:#C4C9D4; }

.ud-code-wrap { position:relative; }
.ud-code-wrap svg {
    position:absolute;left:0;top:50%;transform:translateY(-50%);
    width:14px;height:14px;stroke:#9CA3AF;fill:none;stroke-width:2;stroke-linecap:round;
}
.ud-code-wrap input { padding-left:22px; }

.ud-roles-section { margin-top:4px; }
.ud-roles-title { font-size:13.5px;font-weight:500;color:#374151;margin-bottom:14px; }
.ud-roles-list { display:flex;flex-direction:column;gap:12px; }
.ud-role-chk {
    display:flex;align-items:center;gap:10px;
    font-size:13.5px;color:#374151;cursor:pointer;
}
.ud-role-chk input[type=checkbox] {
    width:16px;height:16px;accent-color:#2563EB;cursor:pointer;
    flex-shrink:0;
}

.ud-actions {
    display:flex;justify-content:flex-end;gap:10px;
    padding-top:20px;margin-top:16px;border-top:1px solid #E5E7EB;
}
.ud-cancel {
    padding:9px 24px;background:#fff;color:#374151;
    border:1.5px solid #D1D5DB;border-radius:8px;font-size:13.5px;
    font-weight:500;cursor:pointer;font-family:inherit;transition:.15s;
}
.ud-cancel:hover { border-color:#374151; }
.ud-add-btn {
    padding:9px 30px;background:#2563EB;color:#fff;border:none;
    border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;
    font-family:inherit;transition:background .15s;
}
.ud-add-btn:hover { background:#1D4ED8; }

.usr-toast {
    position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
    background:#111827;color:#fff;padding:11px 20px;border-radius:10px;
    font-size:13px;font-weight:500;z-index:999;display:flex;align-items:center;
    gap:8px;box-shadow:0 8px 28px rgba(0,0,0,.2);transition:transform .3s ease;white-space:nowrap;
}
.usr-toast.show { transform:translateX(-50%) translateY(0); }

@media(max-width:900px){
    .ar-layout { grid-template-columns:1fr; }
    .ar-roles-panel { border-radius:10px;border:1px solid #E5E7EB; }
}
@media(max-width:680px){
    .ud-row.c4,.ud-row.c3 { grid-template-columns:1fr 1fr; }
}
@media(max-width:420px){
    .ud-row.c4,.ud-row.c3 { grid-template-columns:1fr; }
}
</style>

<?php if($save_success): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    usrToast('✅','<?= esc($save_msg) ?>');
});
</script>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <h1 class="page-title">Users</h1>
    <div class="usr-tabs">
        <a href="?tab=list"
           class="usr-tab <?= $active_tab==='list'&&$view!=='add'&&$view!=='detail'?'active':'' ?>">
            List of Users
        </a>
        <a href="?tab=assign_roles"
           class="usr-tab <?= $active_tab==='assign_roles'?'active':'' ?>">
            Assign roles
        </a>
    </div>
</div>

<?php if(($active_tab==='list' || $active_tab==='') && $view==='' ): ?>

<div class="section-card" style="padding:0;overflow:hidden">

    <div style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;border-bottom:1px solid #F3F4F6">
        <div class="usr-bc" style="margin-bottom:0">
            <a href="?tab=list">Users</a>
            <span class="sep">›</span>
            <span class="cur">List of Users</span>
        </div>
        <a href="?tab=list&view=add" class="btn btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add User
        </a>
    </div>

    <div style="padding:14px 20px 10px">
        <div class="usr-search" style="max-width:280px">
            <svg viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="usrSearch" placeholder="Search table items" oninput="filterUsrTable(this.value)">
        </div>
    </div>

    <div style="overflow-x:auto">
        <table class="usr-table" id="usrTable">
            <thead>
                <tr>
                    <th style="width:64px">S No.</th>
                    <th style="width:90px">Code</th>
                    <th>Username</th>
                    <th>Display Name</th>
                    <th>Roles Assigned</th>
                    <th style="width:100px">Status</th>
                    <th style="width:44px"></th>
                </tr>
            </thead>
            <tbody id="usrTableBody">
                <?php foreach($paged_users as $i=>$u): ?>
                    <tr data-search="<?= strtolower(esc($u['username'])) ?> <?= strtolower(esc($u['display'])) ?> <?= strtolower(esc($u['code'])) ?>">
                        <td style="color:#6B7280"><?= $offset + $i + 1 ?></td>
                        <td style="color:#374151;font-weight:500"><?= esc($u['code']) ?></td>
                        <td style="color:#374151"><?= esc($u['username']) ?></td>
                        <td style="font-weight:500;color:#111827"><?= esc($u['display']) ?></td>
                        <td style="color:#374151"><?= esc($u['role']) ?></td>
                        <td>
                            <label class="usr-toggle" title="Toggle status" onclick="toggleStatus(<?= $u['id'] ?>,this)">
                                <input type="checkbox" <?= $u['active']?'checked':'' ?>>
                                <span class="usr-toggle-sl"></span>
                            </label>
                        </td>
                        <td>
                            <a href="?tab=list&view=detail&id=<?= $u['id'] ?>" class="usr-arrow" title="View / Edit">›</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="padding:10px 20px;border-top:1px solid #F3F4F6">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
            <span style="font-size:12px;color:#9CA3AF">
                Showing <?= min($offset + 1, $total_users) ?> - <?= min($offset + $per_page, $total_users) ?> of <?= $total_users ?> users
            </span>

            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                <?php if($page_no > 1): ?>
                    <a href="?tab=list&page=<?= $page_no - 1 ?>"
                       style="padding:6px 10px;border:1px solid #E5E7EB;border-radius:6px;text-decoration:none;font-size:12px;color:#374151">
                        Prev
                    </a>
                <?php endif; ?>

                <?php for($p=1; $p<=$total_pages; $p++): ?>
                    <a href="?tab=list&page=<?= $p ?>"
                       style="padding:6px 10px;border:1px solid <?= $p==$page_no?'#2563EB':'#E5E7EB' ?>;background:<?= $p==$page_no?'#2563EB':'#fff' ?>;color:<?= $p==$page_no?'#fff':'#374151' ?>;border-radius:6px;text-decoration:none;font-size:12px">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if($page_no < $total_pages): ?>
                    <a href="?tab=list&page=<?= $page_no + 1 ?>"
                       style="padding:6px 10px;border:1px solid #E5E7EB;border-radius:6px;text-decoration:none;font-size:12px;color:#374151">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif($view==='add'): ?>

<div class="section-card" style="padding:0;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6">
        <div class="usr-bc" style="margin-bottom:0">
            <a href="?tab=list">Users</a>
            <span class="sep">›</span>
            <a href="?tab=list">List of Users</a>
            <span class="sep">›</span>
            <span class="cur">User details</span>
        </div>
    </div>

    <div style="padding:28px 24px">
        <form method="POST" id="addUserForm" novalidate>
            <input type="hidden" name="_action" value="add_user">

            <div class="ud-row c4">
                <div class="ud-fg">
                    <label>Username</label>
                    <input type="email" name="username" placeholder="e.g- user@domain.com" required>
                </div>

                <div class="ud-fg">
                    <label>Employee Code</label>
                    <div class="ud-code-wrap">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" name="emp_code" placeholder="Search by name or #code" id="addEmpCode">
                    </div>
                </div>

                <div class="ud-fg">
                    <label>Display Name</label>
                    <input type="text" name="display_name" placeholder="">
                </div>

                <div class="ud-fg">
                    <label>Status</label>
                    <select name="status">
                        <option selected>Active</option>
                        <option>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="ud-row c4">
                <div class="ud-fg">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="">
                </div>

                <div class="ud-fg">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="">
                </div>

                <div class="ud-fg" style="grid-column:span 2">
                    <label>Note</label>
                    <input type="text" name="note" placeholder="">
                </div>
            </div>

            <div class="ud-roles-section">
                <div class="ud-roles-title">Roles</div>
                <div class="ud-roles-list">
                    <?php foreach($roles_list as $role): ?>
                        <label class="ud-role-chk">
                            <input type="checkbox" name="roles[]" value="<?= esc($role) ?>">
                            <?= esc($role) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ud-actions">
                <a href="?tab=list"><button type="button" class="ud-cancel">Cancel</button></a>
                <button type="submit" class="ud-add-btn" onclick="return validateAddUser()">Add</button>
            </div>
        </form>
    </div>
</div>

<?php elseif($view==='detail' && $detail_user): ?>

<div class="section-card" style="padding:0;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6">
        <div class="usr-bc" style="margin-bottom:0">
            <a href="?tab=list">Users</a>
            <span class="sep">›</span>
            <a href="?tab=list">List of Users</a>
            <span class="sep">›</span>
            <span class="cur">User details</span>
        </div>
    </div>

    <div style="padding:28px 24px">
        <form method="POST" id="editUserForm" novalidate>
            <input type="hidden" name="_action" value="save_user">
            <input type="hidden" name="user_id" value="<?= (int)$detail_user['id'] ?>">

            <div class="ud-row c4">
                <div class="ud-fg">
                    <label>Username</label>
                    <input type="email" name="username" value="<?= esc($detail_user['username']) ?>" required>
                </div>

                <div class="ud-fg">
                    <label>Employee Code</label>
                    <div class="ud-code-wrap">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" name="emp_code" value="<?= esc($detail_user['code']) ?>" placeholder="Search by name or #code">
                    </div>
                </div>

                <div class="ud-fg">
                    <label>Display Name</label>
                    <input type="text" name="display_name" value="<?= esc($detail_user['display']) ?>">
                </div>

                <div class="ud-fg">
                    <label>Status</label>
                    <select name="status">
                        <option <?= $detail_user['active']?'selected':'' ?>>Active</option>
                        <option <?= !$detail_user['active']?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="ud-row c4">
                <div class="ud-fg">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Leave blank to keep">
                </div>

                <div class="ud-fg">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="">
                </div>

                <div class="ud-fg" style="grid-column:span 2">
                    <label>Note</label>
                    <input type="text" name="note" placeholder="">
                </div>
            </div>

            <div class="ud-roles-section">
                <div class="ud-roles-title">Roles</div>
                <div class="ud-roles-list">
                    <?php foreach($roles_list as $role): ?>
                        <label class="ud-role-chk">
                            <input type="checkbox" name="roles[]" value="<?= esc($role) ?>"
                                <?= $detail_user['role']===$role?'checked':'' ?>>
                            <?= esc($role) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ud-actions">
                <a href="?tab=list"><button type="button" class="ud-cancel">Cancel</button></a>
                <button type="submit" class="ud-add-btn">Save</button>
            </div>
        </form>
    </div>
</div>

<?php elseif($active_tab==='assign_roles'): ?>

<div class="section-card" style="padding:0;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6">
        <div class="usr-bc" style="margin-bottom:0">
            <a href="?tab=list">Users</a>
            <span class="sep">›</span>
            <span class="cur">Assign role</span>
        </div>
    </div>

    <div style="padding:20px">
        <form method="POST" id="assignRolesForm" novalidate>
            <input type="hidden" name="_action" value="assign_role">
            <!-- <input type="hidden" name="selected_role" id="selectedRoleInput" value="Administrator"> -->

            <div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:20px;flex-wrap:wrap">
                <div style="min-width:240px;max-width:320px;flex:1">
                    <select name="selected_role"
                            id="selectedRoleInput"
                            onchange="selectedRole=this.value"
                            style="
                                width:100%;
                                height:42px;
                                border:1px solid #E5E7EB;
                                border-radius:10px;
                                padding:0 12px;
                                font-size:13.5px;
                                color:#374151;
                                background:#fff;
                                outline:none;
                                font-family:inherit;
                            ">
                        <?php foreach($roles_list as $role): ?>
                            <option value="<?= esc($role) ?>" <?= $role==='Administrator'?'selected':'' ?>>
                                <?= esc($role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>            

                <div class="ar-assign-wrap" style="padding:0">
                    <button type="submit" class="ar-assign-btn" onclick="return confirmAssign()">Assign</button>
                </div>
            </div>

            <div>
                <div class="ar-sel-count" id="arSelCount" onclick="toggleSelAll()">
                    <span id="arSelCountText">Selected Employees - 0</span>
                    <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </div>

                <div style="overflow-x:auto">
                    <table class="ar-table" id="arTable">
                        <thead>
                            <tr>
                                <th style="width:44px">
                                    <input type="checkbox" id="arSelectAll" onchange="toggleArAll(this)"
                                        style="width:16px;height:16px;accent-color:#2563EB;cursor:pointer">
                                </th>
                                <th style="width:70px">S No.</th>
                                <th>Username</th>
                                <th>Employee Name</th>
                                <th>Employee Code</th>
                                <th>Roles Assigned</th>
                            </tr>
                        </thead>
                        <tbody id="arTableBody">
                            <?php foreach($users as $i=>$u): if($u['id']===1) continue; ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_users[]" value="<?= $u['id'] ?>"
                                            class="ar-chk"
                                            style="width:16px;height:16px;accent-color:#2563EB;cursor:pointer"
                                            onchange="updateArCount()">
                                    </td>
                                    <td style="color:#6B7280"><?= $i ?></td>
                                    <td style="color:#374151"><?= esc($u['username']) ?></td>
                                    <td style="font-weight:500;color:#111827"><?= esc($u['display']) ?></td>
                                    <td style="color:#6B7280"><?= esc($u['code']) ?></td>
                                    <td style="color:#374151"><?= esc($u['role']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<div class="usr-toast" id="usrToastEl">
    <span id="usrToastIcon">✅</span><span id="usrToastMsg">Done!</span>
</div>

<script>
function usrToast(icon, msg) {
    var t=document.getElementById('usrToastEl');
    var ti=document.getElementById('usrToastIcon');
    var tm=document.getElementById('usrToastMsg');

    if (!t || !ti || !tm) return;

    ti.textContent=icon;
    tm.textContent=msg;

    t.classList.add('show');

    clearTimeout(t._t);
    t._t=setTimeout(function(){
        t.classList.remove('show');
    },3000);
}

function filterUsrTable(q) {
    q=q.toLowerCase().trim();

    var rows=document.querySelectorAll('#usrTableBody tr');

    rows.forEach(function(r){
        r.style.display=!q||(r.dataset.search||'').includes(q)?'':'none';
    });
}

function toggleStatus(id, label) {
    var chk=label.querySelector('input');
    var newStatus=chk.checked;

    usrToast('⚡','User ' + (newStatus?'activated':'deactivated') + ' successfully!');
}

var selectedRole='Administrator';

function selectRole(role, el) {
    selectedRole=role;

    document.querySelectorAll('.ar-role-item').forEach(function(r){
        r.classList.remove('selected');
    });

    el.classList.add('selected');

    // var inp=document.getElementById('selectedRoleInput');
    var selectedRole = document.getElementById('selectedRoleInput').value;

    if(inp) {
        inp.value=role;
    }
}

function toggleArAll(masterChk) {
    document.querySelectorAll('.ar-chk').forEach(function(c){
        c.checked=masterChk.checked;
    });

    updateArCount();
}

function updateArCount() {
    var checked=document.querySelectorAll('.ar-chk:checked').length;
    var el=document.getElementById('arSelCountText');

    if(el) {
        el.textContent='Selected Employees - '+checked;
    }

    var master=document.getElementById('arSelectAll');
    var total=document.querySelectorAll('.ar-chk').length;

    if(master) {
        master.indeterminate = checked>0 && checked<total;
        master.checked = checked===total && total>0;
    }
}

function toggleSelAll() {
    var master=document.getElementById('arSelectAll');

    if(master) {
        master.checked = !master.checked;
        toggleArAll(master);
    }
}

function confirmAssign() {
    var checked=document.querySelectorAll('.ar-chk:checked').length;

    if(!selectedRole) {
        usrToast('⚠','Please select a role.');
        return false;
    }

    if(checked===0) {
        usrToast('⚠','Please select at least one employee.');
        return false;
    }

    usrToast('✅','Role "'+selectedRole+'" assigned to '+checked+' employee(s)!');

    document.querySelectorAll('.ar-chk:checked').forEach(function(c){
        var row=c.closest('tr');

        if(row) {
            var roleTd=row.cells[row.cells.length-1];

            if(roleTd) {
                roleTd.textContent=selectedRole;
            }
        }

        c.checked=false;
    });

    updateArCount();

    return false;
}

function validateAddUser() {
    var uname=document.querySelector('#addUserForm input[name="username"]');
    var pw=document.querySelector('#addUserForm input[name="password"]');
    var cpw=document.querySelector('#addUserForm input[name="confirm_password"]');

    if(uname && !uname.value.trim()) {
        usrToast('⚠','Username is required.');
        uname.focus();
        return false;
    }

    if(pw && cpw && pw.value !== cpw.value) {
        usrToast('⚠','Passwords do not match.');
        cpw.focus();
        return false;
    }

    return true;
}

var empNames=[
    {code:'1006',name:'Sumi Das'},
    {code:'1008',name:'Shubhankar Naha'},
    {code:'1009',name:'Biswajit Dutta'},
    {code:'1011',name:'Sujata Roy'},
    {code:'1013',name:'Priyanka Chakraborty'},
    {code:'1015',name:'Babli Mallik'},
    {code:'1017',name:'Dibakar Sarkar'},
    {code:'1018',name:'Lal Bahadur Pradhan'}
];

document.addEventListener('DOMContentLoaded',function(){
    var codeInput=document.getElementById('addEmpCode');

    if(!codeInput) return;

    codeInput.addEventListener('input',function(){
        var q=this.value.toLowerCase().trim();

        if(!q) return;

        var match=empNames.find(function(e){
            return e.code.includes(q) || e.name.toLowerCase().includes(q);
        });

        if(match) {
            var dn=document.querySelector('#addUserForm input[name="display_name"]');
            var un=document.querySelector('#addUserForm input[name="username"]');

            if(dn && !dn.value) {
                dn.value=match.name;
            }

            if(un && !un.value) {
                un.value=match.code+'@RKIVFCentre.com';
            }
        }
    });
});
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>