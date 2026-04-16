<?php
$pageTitle = 'Approvals';
$title = 'Approvals';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';

$approvals = [
    ['type' => 'Leave Request', 'employee' => 'Amit Roy', 'dept' => 'Lab Tech', 'detail' => '2 days leave', 'priority' => 'High'],
    ['type' => 'Overtime Request', 'employee' => 'Priya Sen', 'dept' => 'Administration', 'detail' => '4 hrs overtime', 'priority' => 'Medium'],
    ['type' => 'Salary Revision', 'employee' => 'Mohan Das', 'dept' => 'Accounts', 'detail' => '+8%', 'priority' => 'High'],
];
?>

<div class="card">
  <div class="card-header">
    <h2>Pending Approvals</h2>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>TYPE</th>
          <th>EMPLOYEE</th>
          <th>DEPARTMENT</th>
          <th>DETAIL</th>
          <th>PRIORITY</th>
          <th>ACTION</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($approvals as $row): ?>
          <tr>
            <td><?= e($row['type']) ?></td>
            <td><?= e($row['employee']) ?></td>
            <td><?= e($row['dept']) ?></td>
            <td><?= e($row['detail']) ?></td>
            <td><?= e($row['priority']) ?></td>
            <td>
              <button class="btn-sm btn-green">Approve</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>