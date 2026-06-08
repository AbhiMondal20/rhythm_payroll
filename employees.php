<?php
session_start();
require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Employee List';

function esc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$toast_msg  = $_SESSION['toast_msg'] ?? '';
$toast_icon = $_SESSION['toast_icon'] ?? '✅';
unset($_SESSION['toast_msg'], $_SESSION['toast_icon']);

// ==========================================
// 1. HANDLE POST REQUESTS (DELETE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_employee') {
        // Cast to integer to prevent SQL injection
        $id = (int)($_POST['employee_id'] ?? 0);

        if ($id > 0) {
            $deleteSql = "DELETE FROM employees WHERE id = $id";
            
            if (mysqli_query($conn, $deleteSql)) {
                $_SESSION['toast_icon'] = '✅';
                $_SESSION['toast_msg']  = 'Employee deleted successfully.';
            } else {
                $_SESSION['toast_icon'] = '❌';
                $_SESSION['toast_msg']  = 'Delete failed: ' . mysqli_error($conn);
            }
        }

        // Clean redirect to prevent form resubmission
        header("Location: " . explode('?', $_SERVER["REQUEST_URI"])[0]);
        exit;
    }
}

// ==========================================
// 2. FETCH AND PAGINATE DATA
// ==========================================
$search = trim($_GET['search'] ?? '');
$per_page = 10;
$where = '';

if ($search !== '') {
    // Sanitize the search string to prevent SQL injection
    $safe_search = mysqli_real_escape_string($conn, $search);
    $like = "'%" . $safe_search . "%'";
    
    // Using 'e.' alias to prevent ambiguous column errors on JOIN
    $where = "WHERE e.employee_code LIKE $like 
                 OR e.employee_name LIKE $like 
                 OR e.department LIKE $like 
                 OR e.status LIKE $like";
}

// Get Total Count
$countSql = "SELECT COUNT(*) AS total FROM employees AS e $where";
$countRes = mysqli_query($conn, $countSql);

$total_employees = 0;
if ($countRes) {
    $row = mysqli_fetch_assoc($countRes);
    $total_employees = (int)($row['total'] ?? 0);
} else {
    die("Count Query Failed: " . mysqli_error($conn)); 
}

$total_pages = max(1, (int)ceil($total_employees / $per_page));

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages) $current_page = $total_pages;

$offset = ($current_page - 1) * $per_page;

// Fetch Employee Data (Using LEFT JOIN so employees without templates still show up)
$sql = "
    SELECT `e`.`id`, `e`.`employee_code`, `e`.`employee_name`, `e`.`department`, 
           `e`.`personal_email`, `e`.`location`,`e`.`designation`, `e`.`ctc_monthly`, 
           `e`.`emp_type`, `ct`.`name` AS `ctc_template_name`, `e`.`status`, 
           `e`.`created_at`, `e`.`updated_at`
    FROM `employees` AS `e`
    LEFT JOIN `ctc_templates` AS `ct` ON `e`.`ctc_template_id` = `ct`.`id`
    $where
    ORDER BY e.id DESC
    LIMIT $per_page OFFSET $offset
";

$res = mysqli_query($conn, $sql);
$employees_page = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $employees_page[] = $row;
    }
} else {
    die("Main Query Failed: " . mysqli_error($conn));
}

// ==========================================
// 3. UI RENDERING FUNCTIONS
// ==========================================
function pageUrl($page, $search = '') {
    $qs = ['page' => $page];
    if ($search !== '') $qs['search'] = $search;
    return '?' . http_build_query($qs);
}

function renderEmployeeTable($employees_page, $total_employees, $current_page, $total_pages, $search)
{
    ob_start();
    ?>
<div class="section-card">
    <div id="empTableScroll" style="max-height:400px;overflow-y:auto;overflow-x:auto;border-radius:12px;">
        <table id="empTable" style="width:100%;border-collapse:separate;border-spacing:0;">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th style="text-align:right">CTC (Monthly)</th>
                    <th>Template</th>
                    <th>Created At</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees_page as $e): 
                    $ini = function_exists('initials') ? initials($e['employee_name']) : substr($e['employee_name'], 0, 1);

                    $colors = [
                        'Medical' => '#EDE9FE',
                        'Nursing' => '#D1FAE5',
                        'Reception' => '#DBEAFE',
                        'Lab Tech' => '#FFEDD5',
                        'Administration' => '#FEE2E2',
                        'Accounts' => '#FEF3C7',
                        'Human Resource' => '#DCFCE7',
                        'Information Technology' => '#E0F2FE'
                    ];

                    $tc = [
                        'Medical' => '#7C3AED',
                        'Nursing' => '#059669',
                        'Reception' => '#2563EB',
                        'Lab Tech' => '#EA580C',
                        'Administration' => '#DC2626',
                        'Accounts' => '#D97706',
                        'Human Resource' => '#15803D',
                        'Information Technology' => '#0369A1'
                    ];

                    $dept = $e['department'] ?: 'N/A';
                    $bg = $colors[$dept] ?? '#F3F4F6';
                    $fg = $tc[$dept] ?? '#374151';

                    $isActive = strtolower((string)$e['status']) === 'active' || $e['status'] == '1';
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar" style="background:<?= esc($bg) ?>;color:<?= esc($fg) ?>;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%;font-weight:bold;">
                                <?= esc($ini) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;color:#1a1a2e"><?= esc($e['employee_name']) ?></div>
                                <div style="font-size:11px;color:#6B7280">
                                    EMP-<?= esc($e['employee_code']) ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="badge" style="background:<?= esc($bg) ?>;color:<?= esc($fg) ?>;padding:2px 8px;border-radius:12px;font-size:12px;">
                            <?= esc($dept) ?>
                        </span>
                    </td>

                    <td style="text-align:right;font-weight:600">
                        <?= function_exists('fmt_inr') ? fmt_inr($e['ctc_monthly']) : esc($e['ctc_monthly']) ?>
                    </td>
                    <td style="color:#6B7280"><?= esc($e['ctc_template_name'] ?: 'N/A') ?></td>
                    <td style="color:#6B7280"><?= !empty($e['created_at']) ? date('d M Y', strtotime($e['created_at'])) : 'N/A' ?></td>
                    <td style="color:#6B7280"><?= !empty($e['emp_type']) ? esc($e['emp_type']) : 'N/A' ?></td>

                    <td>
                        <?php if ($isActive): ?>
                            <span class="badge" style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:12px;font-size:12px;">● Active</span>
                        <?php else: ?>
                            <span class="badge" style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:12px;font-size:12px;">● Inactive</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="AddEmployee?isEditEmployee=true&id=<?= (int)$e['id'] ?>"
                               class="btn"
                               style="padding:4px 10px;font-size:12px;text-decoration:none;border:1px solid #d1d5db;border-radius:6px;color:#374151;">Edit</a>

                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this employee?')">
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="employee_id" value="<?= (int)$e['id'] ?>">
                                <button type="submit" class="btn"
                                    style="padding:4px 10px;font-size:12px;color:#DC2626;border:1px solid #FEE2E2;background:#fff;border-radius:6px;cursor:pointer;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($employees_page)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:24px;color:#6B7280">No employees found</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="padding:12px 20px;border-top:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <span style="font-size:12px;color:#6B7280">
            Showing <?= count($employees_page) ?> of <?= (int)$total_employees ?> employees
        </span>

        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">

            <?php if ($current_page > 1): ?>
            <a href="<?= esc(pageUrl($current_page - 1, $search)) ?>" class="btn page-link" data-page="<?= $current_page - 1 ?>"
                style="padding:6px 12px;font-size:12px;text-decoration:none;border:1px solid #d1d5db;border-radius:6px;color:#374151;">Prev</a>
            <?php else: ?>
            <button class="btn" style="padding:6px 12px;font-size:12px;opacity:.5;border:1px solid #d1d5db;border-radius:6px;color:#374151;" disabled>Prev</button>
            <?php endif; ?>

            <?php
            $visible = 5;
            $start = max(1, $current_page - 2);
            $end = min($total_pages, $start + $visible - 1);

            if (($end - $start + 1) < $visible) {
                $start = max(1, $end - $visible + 1);
            }
            ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i == $current_page): ?>
                    <a href="<?= esc(pageUrl($i, $search)) ?>" class="btn page-link" data-page="<?= $i ?>"
                       style="padding:6px 12px;font-size:12px;background:#2563EB;color:#fff;border:1px solid #2563EB;border-radius:6px;text-decoration:none">
                        <?= $i ?>
                    </a>
                <?php else: ?>
                    <a href="<?= esc(pageUrl($i, $search)) ?>" class="btn page-link" data-page="<?= $i ?>"
                       style="padding:6px 12px;font-size:12px;text-decoration:none;border:1px solid #d1d5db;border-radius:6px;color:#374151;">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $total_pages - 1): ?>
                <span style="padding:6px 6px;font-size:12px;color:#6B7280">...</span>
            <?php endif; ?>

            <?php if ($end < $total_pages): ?>
                <a href="<?= esc(pageUrl($total_pages, $search)) ?>" class="btn page-link" data-page="<?= $total_pages ?>"
                   style="padding:6px 12px;font-size:12px;text-decoration:none;border:1px solid #d1d5db;border-radius:6px;color:#374151;">
                    <?= $total_pages ?>
                </a>
            <?php endif; ?>

            <?php if ($current_page < $total_pages): ?>
            <a href="<?= esc(pageUrl($current_page + 1, $search)) ?>" class="btn page-link" data-page="<?= $current_page + 1 ?>"
                style="padding:6px 12px;font-size:12px;text-decoration:none;border:1px solid #d1d5db;border-radius:6px;color:#374151;">Next</a>
            <?php else: ?>
            <button class="btn" style="padding:6px 12px;font-size:12px;opacity:.5;border:1px solid #d1d5db;border-radius:6px;color:#374151;" disabled>Next</button>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php
    return ob_get_clean();
}

// Handle AJAX Request for Pagination
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    echo renderEmployeeTable($employees_page, $total_employees, $current_page, $total_pages, $search);
    exit;
}

// ==========================================
// 4. MAIN PAGE HTML
// ==========================================
ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<style>
#empTable thead th {
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 10;
    box-shadow: 0 1px 0 #E5E7EB;
    white-space: nowrap;
    text-align: left;
}

#empTable th,
#empTable td {
    padding: 14px 16px;
    vertical-align: middle;
}

#empTable tbody tr:nth-child(even) {
    background: #fcfcfd;
}

#empTable tbody tr:hover {
    background: #f9fafb;
}

#empTableScroll::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

#empTableScroll::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
}

#empTableScroll::-webkit-scrollbar-track {
    background: #f3f4f6;
}

.page-loading {
    opacity: .6;
    pointer-events: none;
}

.emp-toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: #111827;
    color: #fff;
    padding: 11px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    z-index: 99999;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 8px 28px rgba(0,0,0,.2);
    transition: transform .3s ease;
    white-space: nowrap;
}

.emp-toast.show {
    transform: translateX(-50%) translateY(0);
}

@media (max-width:768px) {
    #empTable {
        min-width: 900px;
    }
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 class="page-title" style="margin:0;font-size:24px;color:#111827;">Employees</h1>
        <p class="page-sub" style="margin:4px 0 0;font-size:14px;color:#6B7280;">Total <?= (int)$total_employees ?> employees</p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="text" name="search" id="empSearch" value="<?= esc($search) ?>"
                placeholder="Search employee..."
                style="padding:8px 14px;border:1px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;width:220px">

            <button type="submit" class="btn" style="padding:8px 14px;font-size:13px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer;">Search</button>

            <?php if ($search !== ''): ?>
                <a href="?" class="btn" style="padding:8px 14px;font-size:13px;text-decoration:none;border:1px solid #d1d5db;border-radius:8px;background:#f3f4f6;color:#374151;display:flex;align-items:center;">Clear</a>
            <?php endif; ?>
        </form>

        <a href="AddEmployee?isAddEmployee=true" class="btn btn-primary" style="text-decoration:none;background:#2563EB;color:#fff;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:500;">+ Add Employee</a>
    </div>
</div>

<div id="employeeTableWrap">
    <?= renderEmployeeTable($employees_page, $total_employees, $current_page, $total_pages, $search); ?>
</div>

<div class="emp-toast" id="empToastEl">
    <span id="empToastIcon">✅</span>
    <span id="empToastMsg">Done!</span>
</div>

<script>
function empToast(icon, msg) {
    const t = document.getElementById('empToastEl');
    const ti = document.getElementById('empToastIcon');
    const tm = document.getElementById('empToastMsg');

    if (!t || !ti || !tm) return;

    ti.textContent = icon;
    tm.textContent = msg;

    t.classList.add('show');

    clearTimeout(t._timer);
    t._timer = setTimeout(function() {
        t.classList.remove('show');
    }, 3200);
}

function filterTable() {
    const input = document.getElementById('empSearch');
    if (!input) return;

    const q = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#empTable tbody tr');

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}

function bindPagination() {
    const links = document.querySelectorAll('.page-link');

    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            const page = this.dataset.page;
            const wrap = document.getElementById('employeeTableWrap');
            const search = <?= json_encode($search) ?>;

            if (!wrap || !page) return;

            wrap.classList.add('page-loading');

            let url = window.location.pathname + '?page=' + page + '&ajax=1';
            if (search) {
                url += '&search=' + encodeURIComponent(search);
            }

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    wrap.innerHTML = html;
                    wrap.classList.remove('page-loading');
                    bindPagination();

                    const scroller = document.getElementById('empTableScroll');
                    if (scroller) scroller.scrollTop = 0;

                    if (history.pushState) {
                        let pageUrl = '?page=' + page;
                        if (search) pageUrl += '&search=' + encodeURIComponent(search);
                        history.pushState(null, '', pageUrl);
                    }
                })
                .catch(() => {
                    wrap.classList.remove('page-loading');
                    empToast('❌', 'Pagination failed.');
                });
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    bindPagination();

    <?php if ($toast_msg): ?>
    empToast(<?= json_encode($toast_icon) ?>, <?= json_encode($toast_msg) ?>);
    <?php endif; ?>
});

window.addEventListener('popstate', function() {
    const params = new URLSearchParams(window.location.search);
    const page = params.get('page') || '1';
    const search = params.get('search') || '';
    const wrap = document.getElementById('employeeTableWrap');

    if (!wrap) return;

    let url = window.location.pathname + '?page=' + page + '&ajax=1';
    if (search) url += '&search=' + encodeURIComponent(search);

    fetch(url)
        .then(response => response.text())
        .then(html => {
            wrap.innerHTML = html;
            bindPagination();
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