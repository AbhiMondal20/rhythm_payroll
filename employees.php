<?php
$pageTitle = 'Employee List';
$title = 'Employee List';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';

$employees = [
    ['name' => 'Dr. Anjali Sharma', 'dept' => 'Medical', 'desig' => 'Sr. Gynaecologist', 'join' => '2019-03-12', 'gross' => 62000, 'status' => 'Active'],
    ['name' => 'Rajib Das', 'dept' => 'Nursing', 'desig' => 'Head Nurse', 'join' => '2020-04-16', 'gross' => 38500, 'status' => 'Active'],
    ['name' => 'Sunita Paul', 'dept' => 'Reception', 'desig' => 'Sr. Receptionist', 'join' => '2021-04-20', 'gross' => 28000, 'status' => 'Active'],
    ['name' => 'Amit Roy', 'dept' => 'Lab Tech', 'desig' => 'Lab Technician', 'join' => '2022-06-01', 'gross' => 32000, 'status' => 'Active'],
];
?>

<div class="page">
  <div class="ph">
    <div>
      <h1>Employee List</h1>
      <p><?= count($employees) ?> active employees</p>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Employees</h2>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>NAME</th>
            <th>DEPARTMENT</th>
            <th>DESIGNATION</th>
            <th>JOINING DATE</th>
            <th>SALARY</th>
            <th>STATUS</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($employees as $emp): ?>
            <tr>
              <td><?= e($emp['name']) ?></td>
              <td><?= e($emp['dept']) ?></td>
              <td><?= e($emp['desig']) ?></td>
              <td><?= date('d M Y', strtotime($emp['join'])) ?></td>
              <td>₹<?= number_format($emp['gross']) ?></td>
              <td>
                <span class="badge" style="background:var(--green-l);color:#065F46">
                  <?= e($emp['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>