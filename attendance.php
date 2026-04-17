<?php
require_once 'includes/config.php';

$page_title = 'Attendance';

/* -----------------------------
   Date filter
----------------------------- */
$selected_date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])
    ? $_GET['date']
    : date('Y-m-d');

/* -----------------------------
   Demo attendance data by employee
   Replace with DB data later if needed
----------------------------- */
$att_statuses = ['present', 'present', 'present', 'absent', 'on_leave', 'present'];
$check_times  = ['09:05', '09:12', '08:58', null, '—', '09:30'];
$check_out    = ['18:02', '18:15', '17:55', null, '—', '—'];

$attendance_rows = [];

foreach ($employees as $i => $e) {
    $status = $att_statuses[$i % 6];
    $ci     = $check_times[$i % 6];
    $co     = $check_out[$i % 6];
    $hrs    = ($ci && $co && $co !== '—') ? '9h 00m' : '—';

    $cfg = match ($status) {
        'present'  => ['bg' => '#D1FAE5', 'c' => '#065F46', 'label' => '✓ Present'],
        'absent'   => ['bg' => '#FEE2E2', 'c' => '#B91C1C', 'label' => '✗ Absent'],
        'on_leave' => ['bg' => '#FEF3C7', 'c' => '#92400E', 'label' => '📅 On Leave'],
        default    => ['bg' => '#F3F4F6', 'c' => '#6B7280', 'label' => ucfirst($status)],
    };

    $attendance_rows[] = [
        'employee' => $e,
        'status'   => $status,
        'check_in' => $ci,
        'check_out'=> $co,
        'hours'    => $hrs,
        'cfg'      => $cfg,
    ];
}

/* -----------------------------
   Stats from generated rows
----------------------------- */
$total_count    = count($attendance_rows);
$present_count  = count(array_filter($attendance_rows, fn($r) => $r['status'] === 'present'));
$leave_count    = count(array_filter($attendance_rows, fn($r) => $r['status'] === 'on_leave'));
$absent_count   = count(array_filter($attendance_rows, fn($r) => $r['status'] === 'absent'));

/* -----------------------------
   Pagination
----------------------------- */
$per_page = 10;
$total_pages = max(1, (int) ceil($total_count / $per_page));
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $per_page;
$attendance_page = array_slice($attendance_rows, $offset, $per_page);

/* -----------------------------
   Query builder helper
----------------------------- */
function buildAttendanceUrl(array $params = []): string
{
    $query = array_merge($_GET, $params);
    return '?' . http_build_query($query);
}
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
#attendanceTableWrap {
  max-height: 420px;
  overflow-y: auto;
  overflow-x: auto;
  border-radius: 0 0 12px 12px;
}

#attendanceTable {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 1000px;
}

#attendanceTable thead th {
  position: sticky;
  top: 0;
  background: #fff;
  z-index: 5;
  box-shadow: 0 1px 0 #E5E7EB;
}

#attendanceTable th,
#attendanceTable td {
  padding: 14px 16px;
  vertical-align: middle;
}

#attendanceTable tbody tr:nth-child(even) {
  background: #fcfcfd;
}

#attendanceTable tbody tr:hover {
  background: #f9fafb;
}

#attendanceTableWrap::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

#attendanceTableWrap::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 10px;
}

#attendanceTableWrap::-webkit-scrollbar-track {
  background: #f3f4f6;
}

.pagination-link {
  padding: 5px 12px;
  font-size: 12px;
  text-decoration: none;
}

.pagination-link.active {
  background: #EDE9FE;
  color: #7C3AED;
}

@media (max-width: 768px) {
  .attendance-toolbar {
    flex-direction: column;
    align-items: stretch !important;
  }

  .attendance-toolbar-right {
    width: 100%;
  }

  .attendance-toolbar-right form {
    width: 100%;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .attendance-toolbar-right input[type="date"] {
    flex: 1;
    min-width: 180px;
  }
}
</style>

<div class="attendance-toolbar" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div>
    <h1 class="page-title">Attendance</h1>
    <p class="page-sub"><?= htmlspecialchars(date('l, d F Y', strtotime($selected_date))) ?></p>
  </div>

  <div class="attendance-toolbar-right" style="display:flex;gap:8px">
    <form method="GET" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input
        type="date"
        name="date"
        value="<?= htmlspecialchars($selected_date) ?>"
        onchange="this.form.submit()"
        style="padding:8px 14px;border:1px solid #E5E7EB;border-radius:8px;font-size:13px"
      >
      <button type="submit" class="btn">Filter</button>
      <a href="?date=<?= htmlspecialchars(date('Y-m-d')) ?>" class="btn" style="text-decoration:none">Today</a>
      <button type="button" class="btn btn-primary">Mark Bulk Attendance</button>
    </form>
  </div>
</div>

<div class="grid-4" style="margin-bottom:20px">
  <?php
  $astats = [
    ['l' => 'TOTAL EMPLOYEES', 'v' => $total_count,   'c' => '#1a1a2e'],
    ['l' => 'PRESENT',         'v' => $present_count, 'c' => '#059669'],
    ['l' => 'ON LEAVE',        'v' => $leave_count,   'c' => '#D97706'],
    ['l' => 'ABSENT',          'v' => $absent_count,  'c' => '#DC2626'],
  ];
  foreach ($astats as $s):
  ?>
    <div class="stat-card">
      <p style="font-size:11px;font-weight:600;color:#6B7280;letter-spacing:.3px">
        <?= htmlspecialchars($s['l']) ?>
      </p>
      <p style="font-size:28px;font-weight:700;color:<?= htmlspecialchars($s['c']) ?>;margin-top:4px">
        <?= (int) $s['v'] ?>
      </p>
    </div>
  <?php endforeach; ?>
</div>

<div class="section-card">
  <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h2 style="font-size:15px;font-weight:700">Attendance Register</h2>
    <span style="font-size:12px;color:#6B7280">
      Date: <?= htmlspecialchars(date('d M Y', strtotime($selected_date))) ?>
    </span>
  </div>

  <div id="attendanceTableWrap">
    <table id="attendanceTable">
      <thead>
        <tr>
          <th>EMPLOYEE</th>
          <th>DEPARTMENT</th>
          <th>CHECK-IN</th>
          <th>CHECK-OUT</th>
          <th>HOURS</th>
          <th style="text-align:center">STATUS</th>
          <th>ACTION</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($attendance_page as $row): ?>
        <?php
          $e   = $row['employee'];
          $ci  = $row['check_in'];
          $co  = $row['check_out'];
          $hrs = $row['hours'];
          $cfg = $row['cfg'];
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar" style="background:#EDE9FE;color:#7C3AED;font-size:11px">
                <?= htmlspecialchars(initials($e['name'])) ?>
              </div>
              <span style="font-weight:500"><?= htmlspecialchars($e['name']) ?></span>
            </div>
          </td>

          <td style="color:#6B7280"><?= htmlspecialchars($e['dept']) ?></td>

          <td style="color:#374151;font-weight:500">
            <?php if ($ci === null): ?>
              <span style="color:#D1D5DB">—</span>
            <?php else: ?>
              <?= htmlspecialchars($ci) ?>
            <?php endif; ?>
          </td>

          <td style="color:#374151">
            <?php if ($co === null): ?>
              <span style="color:#D1D5DB">—</span>
            <?php else: ?>
              <?= htmlspecialchars($co) ?>
            <?php endif; ?>
          </td>

          <td style="color:#6B7280"><?= htmlspecialchars($hrs) ?></td>

          <td style="text-align:center">
            <span class="badge" style="background:<?= htmlspecialchars($cfg['bg']) ?>;color:<?= htmlspecialchars($cfg['c']) ?>">
              <?= htmlspecialchars($cfg['label']) ?>
            </span>
          </td>

          <td>
            <button class="btn" style="padding:4px 10px;font-size:12px">Edit</button>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (empty($attendance_page)): ?>
        <tr>
          <td colspan="7" style="text-align:center;padding:24px;color:#6B7280">No attendance records found</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="padding:12px 20px;border-top:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <span style="font-size:12px;color:#6B7280">
      Showing <?= count($attendance_page) ?> of <?= $total_count ?> employees
    </span>

    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <?php if ($current_page > 1): ?>
        <a href="<?= htmlspecialchars(buildAttendanceUrl(['page' => $current_page - 1, 'date' => $selected_date])) ?>" class="btn pagination-link">← Prev</a>
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
        <a href="<?= htmlspecialchars(buildAttendanceUrl(['page' => 1, 'date' => $selected_date])) ?>" class="btn pagination-link">1</a>
        <?php if ($start > 2): ?>
          <span style="padding:0 4px;color:#6B7280">...</span>
        <?php endif; ?>
      <?php endif; ?>

      <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i == $current_page): ?>
          <a href="<?= htmlspecialchars(buildAttendanceUrl(['page' => $i, 'date' => $selected_date])) ?>" class="btn pagination-link active"><?= $i ?></a>
        <?php else: ?>
          <a href="<?= htmlspecialchars(buildAttendanceUrl(['page' => $i, 'date' => $selected_date])) ?>" class="btn pagination-link"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?>
          <span style="padding:0 4px;color:#6B7280">...</span>
        <?php endif; ?>
        <a href="<?= htmlspecialchars(buildAttendanceUrl(['page' => $total_pages, 'date' => $selected_date])) ?>" class="btn pagination-link"><?= $total_pages ?></a>
      <?php endif; ?>

      <?php if ($current_page < $total_pages): ?>
        <a href="<?= htmlspecialchars(buildAttendanceUrl(['page' => $current_page + 1, 'date' => $selected_date])) ?>" class="btn pagination-link">Next →</a>
      <?php else: ?>
        <button class="btn" style="padding:5px 12px;font-size:12px;opacity:.5" disabled>Next →</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>