<?php
require_once 'includes/config.php';
$page_title = 'Employee List';

/* -----------------------------
   Pagination
----------------------------- */
$per_page = 10;
$total_employees = count($employees);
$total_pages = max(1, (int) ceil($total_employees / $per_page));

$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $per_page;
$employees_page = array_slice($employees, $offset, $per_page);

/* -----------------------------
   Table render function
----------------------------- */
function renderEmployeeTable($employees_page, $total_employees, $current_page, $total_pages)
{
    ob_start();
    ?>
<div class="section-card">
    <div id="empTableScroll" style="max-height:400px;overflow-y:auto;overflow-x:auto;border-radius:12px;">
        <table id="empTable" style="width:100%;border-collapse:separate;border-spacing:0;">
            <thead>
                <tr>
                    <th>EMPLOYEE</th>
                    <th>DEPARTMENT</th>
                    <th>ROLE</th>
                    <th>DATE JOINED</th>
                    <th style="text-align:right">SALARY (GROSS)</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees_page as $e):
              $ini = initials($e['name']);

              $colors = [
                  'Medical' => '#EDE9FE',
                  'Nursing' => '#D1FAE5',
                  'Reception' => '#DBEAFE',
                  'Lab Tech' => '#FFEDD5',
                  'Administration' => '#FEE2E2',
                  'Accounts' => '#FEF3C7'
              ];

              $tc = [
                  'Medical' => '#7C3AED',
                  'Nursing' => '#059669',
                  'Reception' => '#2563EB',
                  'Lab Tech' => '#EA580C',
                  'Administration' => '#DC2626',
                  'Accounts' => '#D97706'
              ];

              $bg = $colors[$e['dept']] ?? '#F3F4F6';
              $fg = $tc[$e['dept']] ?? '#374151';
          ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar"
                                style="background:<?= htmlspecialchars($bg) ?>;color:<?= htmlspecialchars($fg) ?>">
                                <?= htmlspecialchars($ini) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;color:#1a1a2e"><?= htmlspecialchars($e['name']) ?></div>
                                <div style="font-size:11px;color:#6B7280">
                                    EMP-<?= str_pad((string) $e['id'], 3, '0', STR_PAD_LEFT) ?></div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="badge"
                            style="background:<?= htmlspecialchars($bg) ?>;color:<?= htmlspecialchars($fg) ?>">
                            <?= htmlspecialchars($e['dept']) ?>
                        </span>
                    </td>

                    <td style="color:#6B7280"><?= htmlspecialchars($e['role']) ?></td>
                    <td style="color:#6B7280"><?= date('d M Y', strtotime($e['join'])) ?></td>
                    <td style="text-align:right;font-weight:600"><?= fmt_inr($e['salary']) ?></td>
                    <td><span class="badge" style="background:#D1FAE5;color:#065F46">● Active</span></td>

                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button class="btn" style="padding:4px 10px;font-size:12px">Edit</button>
                            <button class="btn"
                                style="padding:4px 10px;font-size:12px;color:#DC2626;border-color:#FEE2E2">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($employees_page)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:24px;color:#6B7280">No employees found</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div
        style="padding:12px 20px;border-top:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <span style="font-size:12px;color:#6B7280">
            Showing <?= count($employees_page) ?> of <?= (int) $total_employees ?> employees
        </span>

        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">

            <!-- Prev -->
            <?php if ($current_page > 1): ?>
            <a href="?page=<?= $current_page - 1 ?>" class="btn page-link" data-page="<?= $current_page - 1 ?>"
                style="padding:6px 12px;font-size:12px;text-decoration:none">
                Prev
            </a>
            <?php else: ?>
            <button class="btn" style="padding:6px 12px;font-size:12px;opacity:.5" disabled>
                Prev
            </button>
            <?php endif; ?>

            <?php
              $visible = 5;
              $start = max(1, $current_page - 2);
              $end = min($total_pages, $start + $visible - 1);

              if (($end - $start + 1) < $visible) {
                  $start = max(1, $end - $visible + 1);
              }
            ?>

            <!-- Starting pages -->
            <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $current_page): ?>
            <a href="?page=<?= $i ?>" class="btn page-link" data-page="<?= $i ?>"
                style="padding:6px 12px;font-size:12px;background:#2563EB;color:#fff;border-color:#2563EB;text-decoration:none">
                <?= $i ?>
            </a>
            <?php else: ?>
            <a href="?page=<?= $i ?>" class="btn page-link" data-page="<?= $i ?>"
                style="padding:6px 12px;font-size:12px;text-decoration:none">
                <?= $i ?>
            </a>
            <?php endif; ?>
            <?php endfor; ?>

            <!-- Dots -->
            <?php if ($end < $total_pages - 1): ?>
            <span style="padding:6px 6px;font-size:12px;color:#6B7280">...</span>
            <?php endif; ?>

            <!-- Last page -->
            <?php if ($end < $total_pages): ?>
            <?php if ($total_pages == $current_page): ?>
            <a href="?page=<?= $total_pages ?>" class="btn page-link" data-page="<?= $total_pages ?>"
                style="padding:6px 12px;font-size:12px;background:#2563EB;color:#fff;border-color:#2563EB;text-decoration:none">
                <?= $total_pages ?>
            </a>
            <?php else: ?>
            <a href="?page=<?= $total_pages ?>" class="btn page-link" data-page="<?= $total_pages ?>"
                style="padding:6px 12px;font-size:12px;text-decoration:none">
                <?= $total_pages ?>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?= $current_page + 1 ?>" class="btn page-link" data-page="<?= $current_page + 1 ?>"
                style="padding:6px 12px;font-size:12px;text-decoration:none">
                Next
            </a>
            <?php else: ?>
            <button class="btn" style="padding:6px 12px;font-size:12px;opacity:.5" disabled>
                Next
            </button>
            <?php endif; ?>

        </div>
    </div>



</div>
<?php
    return ob_get_clean();
}

/* -----------------------------
   AJAX pagination response
----------------------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    echo renderEmployeeTable($employees_page, $total_employees, $current_page, $total_pages);
    exit;
}

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

@media (max-width:768px) {
    #empTable {
        min-width: 900px;
    }
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div>
        <h1 class="page-title">Employees</h1>
        <p class="page-sub">Total <?= (int) $total_employees ?> employees</p>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" id="empSearch" oninput="filterTable()" placeholder="Search employee..."
            style="padding:8px 14px;border:1px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;width:220px">
        <button class="btn btn-primary">+ Add Employee</button>
    </div>
</div>

<div id="employeeTableWrap">
    <?= renderEmployeeTable($employees_page, $total_employees, $current_page, $total_pages); ?>
</div>

<?php
$extra_scripts = <<<JS
<script>
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
      if (!wrap || !page) return;

      wrap.classList.add('page-loading');

      fetch(window.location.pathname + '?page=' + page + '&ajax=1')
        .then(response => response.text())
        .then(html => {
          wrap.innerHTML = html;
          wrap.classList.remove('page-loading');
          bindPagination();
          filterTable();

          const scroller = document.getElementById('empTableScroll');
          if (scroller) {
            scroller.scrollTop = 0;
          }

          if (history.pushState) {
            history.pushState(null, '', '?page=' + page);
          }
        })
        .catch(() => {
          wrap.classList.remove('page-loading');
        });
    });
  });
}

document.addEventListener('DOMContentLoaded', function() {
  bindPagination();
});

window.addEventListener('popstate', function() {
  const params = new URLSearchParams(window.location.search);
  const page = params.get('page') || '1';
  const wrap = document.getElementById('employeeTableWrap');
  if (!wrap) return;

  fetch(window.location.pathname + '?page=' + page + '&ajax=1')
    .then(response => response.text())
    .then(html => {
      wrap.innerHTML = html;
      bindPagination();
      filterTable();
    });
});
</script>
JS;

$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>