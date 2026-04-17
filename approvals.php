<?php
require_once 'includes/config.php';

$page_title = 'Approvals';

/* -----------------------------
   Inputs
----------------------------- */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['page']) ? (int) $_GET['page'] : 1;

/* -----------------------------
   Filter approvals
----------------------------- */
$filtered_approvals = array_values(array_filter($approvals, function ($a) use ($search) {
    if ($search === '') {
        return true;
    }

    $haystack = strtolower(
        ($a['type'] ?? '') . ' ' .
        ($a['employee'] ?? '') . ' ' .
        ($a['detail'] ?? '')
    );

    return str_contains($haystack, strtolower($search));
}));

/* -----------------------------
   Pagination
----------------------------- */
$per_page = 10;
$total_items = count($filtered_approvals);
$total_pages = max(1, (int) ceil($total_items / $per_page));
$current_page = max(1, min($page, $total_pages));
$offset = ($current_page - 1) * $per_page;
$approvals_page = array_slice($filtered_approvals, $offset, $per_page);

/* -----------------------------
   Render table block
----------------------------- */
function renderApprovalsTable($approvals_page, $total_items, $current_page, $total_pages, $offset)
{
    ob_start();
    ?>
    <div class="section-card">
      <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <h2 style="font-size:15px;font-weight:700">Pending Approval Requests</h2>
        <span style="font-size:12px;color:#6B7280">
          Showing <?= count($approvals_page) ?> of <?= $total_items ?> items
        </span>
      </div>

      <div id="approvalsTableWrap">
        <table id="approvalsTable">
          <thead>
            <tr>
              <th>#</th>
              <th>TYPE</th>
              <th>EMPLOYEE</th>
              <th>DETAIL</th>
              <th>REQUESTED</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($approvals_page)): ?>
            <?php foreach ($approvals_page as $index => $a): ?>
              <?php $row_no = $offset + $index + 1; ?>
              <tr>
                <td style="color:#9CA3AF;font-size:12px"><?= $row_no ?></td>

                <td>
                  <span style="font-size:18px"><?= htmlspecialchars($a['icon']) ?></span>
                  <?= htmlspecialchars($a['type']) ?>
                </td>

                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <div class="avatar" style="background:#EDE9FE;color:#7C3AED;font-size:11px;width:30px;height:30px">
                      <?= htmlspecialchars(initials($a['employee'])) ?>
                    </div>
                    <span style="font-weight:500"><?= htmlspecialchars($a['employee']) ?></span>
                  </div>
                </td>

                <td style="color:#6B7280"><?= htmlspecialchars($a['detail']) ?></td>
                <td style="color:#6B7280;font-size:12px"><?= date('d M Y') ?></td>

                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <button class="btn" style="background:#D1FAE5;color:#065F46;border-color:#A7F3D0;padding:5px 12px;font-size:12px">✓ Approve</button>
                    <button class="btn" style="color:#DC2626;border-color:#FEE2E2;padding:5px 12px;font-size:12px">✗ Reject</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align:center;padding:24px;color:#6B7280">No approvals found</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div style="padding:12px 20px;border-top:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <span style="font-size:12px;color:#6B7280">
          Page <?= $current_page ?> of <?= $total_pages ?>
        </span>

        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
          <?php if ($current_page > 1): ?>
            <a href="?page=<?= $current_page - 1 ?>" class="btn pagination-link ajax-page-link" data-page="<?= $current_page - 1 ?>">← Prev</a>
          <?php else: ?>
            <button class="btn" style="padding:5px 12px;font-size:12px;opacity:.5" disabled>← Prev</button>
          <?php endif; ?>

          <?php
          $visible = 5;
          $start = max(1, $current_page - 2);
          $end = min($total_pages, $start + $visible - 1);

          if (($end - $start + 1) < $visible) {
              $start = max(1, $end - $visible + 1);
          }
          ?>

          <?php if ($start > 1): ?>
            <a href="?page=1" class="btn pagination-link ajax-page-link" data-page="1">1</a>
            <?php if ($start > 2): ?>
              <span style="padding:0 4px;color:#6B7280">...</span>
            <?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $current_page): ?>
              <a href="?page=<?= $i ?>" class="btn pagination-link active ajax-page-link" data-page="<?= $i ?>"><?= $i ?></a>
            <?php else: ?>
              <a href="?page=<?= $i ?>" class="btn pagination-link ajax-page-link" data-page="<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?>
              <span style="padding:0 4px;color:#6B7280">...</span>
            <?php endif; ?>
            <a href="?page=<?= $total_pages ?>" class="btn pagination-link ajax-page-link" data-page="<?= $total_pages ?>"><?= $total_pages ?></a>
          <?php endif; ?>

          <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?= $current_page + 1 ?>" class="btn pagination-link ajax-page-link" data-page="<?= $current_page + 1 ?>">Next →</a>
          <?php else: ?>
            <button class="btn" style="padding:5px 12px;font-size:12px;opacity:.5" disabled>Next →</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

/* -----------------------------
   AJAX response
----------------------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    echo renderApprovalsTable($approvals_page, $total_items, $current_page, $total_pages, $offset);
    exit;
}

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
#approvalsTableWrap{
  max-height:420px;
  overflow-y:auto;
  overflow-x:auto;
  border-radius:0 0 12px 12px;
}

#approvalsTable{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  min-width:900px;
}

#approvalsTable thead th{
  position:sticky;
  top:0;
  background:#fff;
  z-index:5;
  box-shadow:0 1px 0 #E5E7EB;
}

#approvalsTable th,
#approvalsTable td{
  padding:14px 16px;
  vertical-align:middle;
}

#approvalsTable tbody tr:nth-child(even){
  background:#fcfcfd;
}

#approvalsTable tbody tr:hover{
  background:#f9fafb;
}

#approvalsTableWrap::-webkit-scrollbar{
  width:8px;
  height:8px;
}

#approvalsTableWrap::-webkit-scrollbar-thumb{
  background:#d1d5db;
  border-radius:10px;
}

#approvalsTableWrap::-webkit-scrollbar-track{
  background:#f3f4f6;
}

.pagination-link{
  padding:5px 12px;
  font-size:12px;
  text-decoration:none;
}

.pagination-link.active{
  background:#EDE9FE;
  color:#7C3AED;
}

.table-loading{
  opacity:.6;
  pointer-events:none;
}

@media (max-width:768px){
  .approvals-toolbar{
    flex-direction:column;
    align-items:stretch !important;
  }

  .approvals-toolbar .search-box{
    width:100%;
  }

  .approvals-toolbar input[type="text"]{
    width:100% !important;
  }
}
</style>

<div class="approvals-toolbar" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div>
    <h1 class="page-title">Approvals</h1>
    <p class="page-sub" id="approvalsSubText">
      <?= $total_items ?> pending items<?= $search !== '' ? ' for "' . htmlspecialchars($search) . '"' : '' ?>
    </p>
  </div>

  <div class="search-box" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input
      type="text"
      id="approvalSearch"
      value="<?= htmlspecialchars($search) ?>"
      placeholder="Search approvals..."
      autocomplete="off"
      style="padding:8px 14px;border:1px solid #E5E7EB;border-radius:8px;font-size:13px;outline:none;width:220px"
    >
  </div>
</div>

<div id="approvalsContent">
  <?= renderApprovalsTable($approvals_page, $total_items, $current_page, $total_pages, $offset); ?>
</div>

<?php
$extra_scripts = <<<JS
<script>
let approvalsSearchTimer = null;

function bindApprovalPagination() {
  document.querySelectorAll('.ajax-page-link').forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const page = this.dataset.page || 1;
      loadApprovals(page);
    });
  });
}

function updateApprovalsUrl(page, search) {
  const params = new URLSearchParams();
  if (page && Number(page) > 1) {
    params.set('page', page);
  }
  if (search && search.trim() !== '') {
    params.set('search', search.trim());
  }

  const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
  history.pushState({page, search}, '', newUrl);
}

function loadApprovals(page = 1, pushState = true) {
  const wrap = document.getElementById('approvalsContent');
  const input = document.getElementById('approvalSearch');
  const subText = document.getElementById('approvalsSubText');
  const search = input ? input.value.trim() : '';

  if (!wrap) return;

  wrap.classList.add('table-loading');

  const params = new URLSearchParams();
  params.set('ajax', '1');
  params.set('page', page);
  if (search !== '') {
    params.set('search', search);
  }

  fetch(window.location.pathname + '?' + params.toString(), {
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(res => res.text())
  .then(html => {
    wrap.innerHTML = html;
    wrap.classList.remove('table-loading');
    bindApprovalPagination();

    const countText = document.querySelector('#approvalsContent .section-card span');
    if (subText) {
      if (search !== '') {
        subText.innerHTML = 'Filtered results for "' + search.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '"';
      } else {
        subText.innerHTML = 'Approvals list';
      }
    }

    const scroller = document.getElementById('approvalsTableWrap');
    if (scroller) {
      scroller.scrollTop = 0;
    }

    if (pushState) {
      updateApprovalsUrl(page, search);
    }
  })
  .catch(() => {
    wrap.classList.remove('table-loading');
  });
}

document.addEventListener('DOMContentLoaded', function() {
  bindApprovalPagination();

  const input = document.getElementById('approvalSearch');
  if (input) {
    input.addEventListener('input', function() {
      clearTimeout(approvalsSearchTimer);
      approvalsSearchTimer = setTimeout(() => {
        loadApprovals(1);
      }, 250);
    });
  }
});

window.addEventListener('popstate', function() {
  const params = new URLSearchParams(window.location.search);
  const page = params.get('page') || 1;
  const search = params.get('search') || '';
  const input = document.getElementById('approvalSearch');

  if (input) {
    input.value = search;
  }

  loadApprovals(page, false);
});
</script>
JS;

$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>