<?php
session_start();
if (!isset($_SESSION['login'])) {
    if (isset($_POST['action']) || isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login');
    exit();
}
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
    SELECT `e`.`id`,  `e`.`profile_photo`, `e`.`employee_code`, `e`.`employee_name`, `e`.`department`, 
           `e`.`personal_email`, `e`.`location`,`e`.`designation`, `e`.`emp_type`, `e`.`ctc_monthly`, 
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

<style>
    .ur-del-btn, .ur-chevron-btn { background: none; border: none; cursor: pointer; color: #94A3B8; transition: color .12s; padding: 4px; }
    .ur-del-btn:hover { color: #EF4444; }
    .ur-del-btn, .ur-chevron-btn { background: none; border: none; cursor: pointer; color: #94A3B8; transition: color .12s; padding: 4px; }
    .ur-chevron-btn:hover { color: #2563EB; }

</style>

<div class="section-card">
    <div id="empTableScroll" style="max-height:400px;overflow-y:auto;overflow-x:auto;border-radius:12px;">
        <table id="empTable" style="width:100%;border-collapse:separate;border-spacing:0;">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Email ID</th>
                    <th>Location</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Group</th>
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
                            <div>
                                <div style="font-weight:600;color:#1a1a2e"><?= esc($e['employee_code']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <a href="AddEmployee?isEditEmployee=true&id=<?= (int)$e['id'] ?>"
                            style="display:flex;align-items:center;gap:8px;font-weight:600;color:#1a1a2e;text-decoration:none;">
                            
                                <img src="<?= esc(!empty($e['profile_photo']) ? $e['profile_photo'] : 'uploads/photos/user.png') ?>"
                            alt="Profile"
                            style="width:32px;height:32px;border-radius:50%;object-fit:cover;">

                                <span><?= esc($e['employee_name']) ?></span>
                            </a>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:#1a1a2e"><?= esc($e['personal_email']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:#1a1a2e"><?= esc($e['location']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:#1a1a2e"><?= esc($e['department']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:#1a1a2e"><?= esc($e['designation']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:#1a1a2e"><?= esc($e['emp_type']) ?></div>
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

    <div
        style="padding:12px 20px;border-top:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <span style="font-size:12px;color:#6B7280">
            Showing <?= count($employees_page) ?> of <?= (int)$total_employees ?> employees
        </span>

        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">

            <?php if ($current_page > 1): ?>
            <a href="<?= esc(pageUrl($current_page - 1, $search)) ?>" class="btn page-link"
                data-page="<?= $current_page - 1 ?>"
                style="padding:6px 12px;font-size:12px;text-decoration:none;border:1px solid #d1d5db;border-radius:6px;color:#374151;">Prev</a>
            <?php else: ?>
            <button class="btn"
                style="padding:6px 12px;font-size:12px;opacity:.5;border:1px solid #d1d5db;border-radius:6px;color:#374151;"
                disabled>Prev</button>
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
            <a href="<?= esc(pageUrl($current_page + 1, $search)) ?>" class="btn page-link"
                data-page="<?= $current_page + 1 ?>"
                style="padding:6px 12px;font-size:12px;text-decoration:none;border:1px solid #d1d5db;border-radius:6px;color:#374151;">Next</a>
            <?php else: ?>
            <button class="btn"
                style="padding:6px 12px;font-size:12px;opacity:.5;border:1px solid #d1d5db;border-radius:6px;color:#374151;"
                disabled>Next</button>
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
    left: 80%;
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
    box-shadow: 0 8px 28px rgba(0, 0, 0, .2);
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
        <p class="page-sub" style="margin:4px 0 0;font-size:14px;color:#6B7280;">Total <?= (int)$total_employees ?>
            employees</p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="text" name="search" id="empSearch" value="<?= esc($search) ?>" placeholder="Search employee..."
                style="padding:8px 14px;border:1px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;width:220px">

            <button type="submit" class="btn"
                style="padding:8px 14px;font-size:13px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer;">Search</button>

            <?php if ($search !== ''): ?>
            <a href="?" class="btn"
                style="padding:8px 14px;font-size:13px;text-decoration:none;border:1px solid #d1d5db;border-radius:8px;background:#f3f4f6;color:#374151;display:flex;align-items:center;">Clear</a>
            <?php endif; ?>
        </form>

        <a href="AddEmployee?isAddEmployee=true" class="btn btn-primary"
            style="text-decoration:none;background:#2563EB;color:#fff;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:500;">+
            Add Employee</a>
            
        <button id="btnSyncDevices" class="btn"
            style="background:#10B981;color:#fff;padding:8px 14px;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px;">
           <i class="fa-solid fa-rotate"></i> Sync
        </button>
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

<script>
document.querySelectorAll('.delete-employee-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Delete Employee?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
// ==========================================
// NEW AJAX SYNC SCRIPT INTEGRATION (WITH PROGRESS)
// ==========================================
const btnSync = document.getElementById('btnSyncDevices');
if (btnSync) {
    btnSync.addEventListener('click', function(e) {
        e.preventDefault();
        
        let progressInterval;
        let currentProgress = 0;

        // Show loading state with SweetAlert and a Progress Bar
        Swal.fire({
            title: 'Syncing Data...',
            html: `
                <div style="margin-bottom: 15px;">Please wait while devices are being synced.</div>
                <div style="font-weight: bold; margin-bottom: 5px;">Progress: <span id="progress-text">0</span>%</div>
                <div style="width: 100%; background-color: #e9ecef; border-radius: 4px; overflow: hidden; height: 20px;">
                    <div id="sync-progress-bar" style="width: 0%; height: 100%; background-color: #10B981; transition: width 0.4s ease;"></div>
                </div>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
                
                const progressText = Swal.getHtmlContainer().querySelector('#progress-text');
                const progressBar = Swal.getHtmlContainer().querySelector('#sync-progress-bar');
                
                // Simulate progress climbing up to 90% while waiting for server response
                progressInterval = setInterval(() => {
                    if (currentProgress < 90) {
                        // Increments randomly between 1% and 5% to look natural
                        const increment = Math.floor(Math.random() * 5) + 1;
                        currentProgress = Math.min(currentProgress + increment, 90);
                        
                        progressText.textContent = currentProgress;
                        progressBar.style.width = currentProgress + '%';
                    }
                }, 600); // Updates every 600ms
            },
            willClose: () => {
                clearInterval(progressInterval); // Clean up interval if closed
            }
        });

        // Trigger the sync file
        fetch('http://localhost/rhythm_payroll/includes/sync/sync_all.php')
            .then(async response => {
                if (!response.ok) {
                    // If the PHP script throws a 503 or 500 error, catch the text
                    const errText = await response.text();
                    throw new Error(errText || "Network error");
                }
                return response.text(); 
            })
            .then(data => {
                // Clear the interval and force progress to 100%
                clearInterval(progressInterval);
                const progressText = Swal.getHtmlContainer().querySelector('#progress-text');
                const progressBar = Swal.getHtmlContainer().querySelector('#sync-progress-bar');
                
                if (progressText && progressBar) {
                    progressText.textContent = 100;
                    progressBar.style.width = '100%';
                }
                
                // Add a tiny delay so the user sees it hit 100% before changing screens
                setTimeout(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sync Complete!',
                        html: '<div style="font-size: 14px; text-align: left; max-height: 200px; overflow-y: auto;">' + data + '</div>',
                        confirmButtonColor: '#10B981'
                    }).then(() => {
                        window.location.reload(); 
                    });
                }, 500);
            })
            .catch(error => {
                clearInterval(progressInterval);
                Swal.fire({
                    icon: 'error',
                    title: 'Sync Failed',
                    text: error.message || 'Could not connect to the device. Ensure the IP is correct and the device is powered on.',
                    confirmButtonColor: '#dc3545'
                });
            });
    });
}
</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>