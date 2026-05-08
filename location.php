<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Location';

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ── Config nav tabs ── */
.cfg-tabs {
  display: flex;
  align-items: center;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
  overflow-x: auto;
  scrollbar-width: none;
}
.cfg-tabs::-webkit-scrollbar { display: none; }
.cfg-tab {
  padding: 14px 20px;
  font-size: 13.5px;
  font-weight: 500;
  color: #6b7280;
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
.cfg-tab.active { color: #2563eb; border-bottom-color: #2563eb; font-weight: 600; }

/* ── Page wrapper ── */
.loc-wrapper {
  font-family: 'Segoe UI', sans-serif;
  color: #1e2d3d;
  padding: 0 0 40px;
}
.loc-inner { padding: 20px 28px; }

/* ── Top bar ── */
.loc-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
}
.loc-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  color: #555;
}
.loc-breadcrumb a { color: #2563eb; text-decoration: none; font-weight: 600; }
.loc-breadcrumb a:hover { text-decoration: underline; }
.loc-breadcrumb .sep { color: #bbb; font-size: 11px; }
.loc-breadcrumb span { color: #374151; }
.btn-add-loc {
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
  transition: background .16s;
  text-decoration: none;
}
.btn-add-loc:hover { background: #1d4ed8; }

/* ── Split panel ── */
.loc-panel {
  display: flex;
  background: #fff;
  border-radius: 10px;
  overflow: hidden;
  min-height: 500px;
}

/* ── Left list ── */
.loc-list-col {
  width: 35%;
  min-width: 240px;
  border-right: 1px solid #e8ecf0;
  display: flex;
  flex-direction: column;
}
.loc-list-heading {
  padding: 13px 16px 10px;
  font-size: 12px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.loc-search-wrap {
  padding: 0 14px 12px;
}
.loc-search-inner {
  position: relative;
}
.loc-search-inner i {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  font-size: 12px;
}
.loc-search-input {
  width: 100%;
  padding: 8px 10px 8px 32px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 13px;
  color: #1e2d3d;
  outline: none;
  box-sizing: border-box;
  background: #f9fafb;
  transition: border-color .15s;
}
.loc-search-input:focus { border-color: #2563eb; background: #fff; }

.loc-list-scroll {
  flex: 1;
  overflow-y: auto;
  max-height: 540px;
}
.loc-list-scroll::-webkit-scrollbar { width: 4px; }
.loc-list-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

.loc-item {
  padding: 13px 16px;
  border-bottom: 1px solid #f1f4f8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: background .12s;
}
.loc-item:last-child { border-bottom: none; }
.loc-item:hover { background: #f8fafc; }
.loc-item.active {
  background: #eff6ff;
  border-left: 3px solid #2563eb;
  padding-left: 13px;
}
.loc-item-name {
  font-size: 13.5px;
  font-weight: 500;
  color: #1e2d3d;
}
.loc-item.active .loc-item-name { color: #2563eb; font-weight: 700; }
.loc-item-chevron { font-size: 11px; color: #9ca3af; }

/* ── Right detail ── */
.loc-detail-col {
  flex: 1;
  padding: 22px 28px;
  display: flex;
  flex-direction: column;
}
.loc-detail-heading {
  font-size: 11.5px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .4px;
  border-bottom: 1px solid #e8ecf0;
  padding-bottom: 12px;
  margin-bottom: 20px;
}
.loc-detail-title-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22px;
}
.loc-detail-title {
  font-size: 16px;
  font-weight: 700;
  color: #1e2d3d;
}
.btn-edit-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #2563eb;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 600;
  padding: 0;
}
.btn-edit-link:hover { text-decoration: underline; }

/* Field grid */
.loc-field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px 36px;
  margin-bottom: 6px;
}
.loc-field label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
  font-weight: 500;
}
.loc-field-value {
  font-size: 13.5px;
  color: #1e2d3d;
  padding-bottom: 8px;
  border-bottom: 1px solid #e2e8f0;
  min-height: 28px;
}

/* Edit form inputs */
.loc-input, .loc-select {
  width: 100%;
  border: none;
  border-bottom: 1.5px solid #d1d5db;
  padding: 7px 2px;
  font-size: 13.5px;
  color: #1e2d3d;
  background: transparent;
  outline: none;
  box-sizing: border-box;
  transition: border-color .16s;
}
.loc-input::placeholder { color: #c4c9d4; }
.loc-input:focus, .loc-select:focus { border-color: #2563eb; }
.loc-select {
  appearance: none;
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 4px center;
  padding-right: 20px;
}

/* form actions */
.loc-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: auto;
  padding-top: 24px;
}
.btn-cancel {
  padding: 9px 26px;
  border: 1.5px solid #d1d5db;
  background: #fff;
  border-radius: 6px;
  font-size: 13.5px;
  color: #374151;
  cursor: pointer;
  font-weight: 600;
  transition: background .14s;
}
.btn-cancel:hover { background: #f1f5f9; }
.btn-save {
  padding: 9px 26px;
  background: #2563eb;
  border: none;
  border-radius: 6px;
  font-size: 13.5px;
  color: #fff;
  cursor: pointer;
  font-weight: 600;
  transition: background .14s;
}
.btn-save:hover { background: #1d4ed8; }

/* Empty / placeholder */
.loc-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  font-size: 13.5px;
}

/* flash */
.flash-msg {
  padding: 10px 16px;
  border-radius: 7px;
  font-size: 13px;
  margin-bottom: 14px;
  font-weight: 500;
}
.flash-msg.success { background: #dcfce7; color: #166534; }
.flash-msg.error   { background: #fee2e2; color: #991b1b; }
</style>

<?php
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$active_id  = (int)($_GET['id'] ?? 0);
$mode       = $_GET['mode'] ?? 'view';
$search     = trim($_GET['q'] ?? '');
$flash      = '';
$flash_type = '';

$timezones = [
    'UTC+05:30 (Indian Standard Time)',
    'UTC+00:00 (Greenwich Mean Time)',
    'UTC+01:00 (Central European Time)',
    'UTC-05:00 (Eastern Standard Time)',
    'UTC-08:00 (Pacific Standard Time)',
];

/* ── POST handlers ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    $code_name     = trim($_POST['code_name'] ?? '');
    $location_name = trim($_POST['location_name'] ?? '');
    $address1      = trim($_POST['address1'] ?? '');
    $address2      = trim($_POST['address2'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $state         = trim($_POST['state'] ?? '');
    $country       = trim($_POST['country'] ?? '');
    $pin_code      = trim($_POST['pin_code'] ?? '');
    $office_phone  = trim($_POST['office_phone'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $fax           = trim($_POST['fax'] ?? '');
    $website       = trim($_POST['website'] ?? '');
    $timezone      = trim($_POST['timezone'] ?? 'UTC+05:30 (Indian Standard Time)');
    $remarks       = trim($_POST['remarks'] ?? '');

    if ($action === 'add_location') {

        if ($code_name === '') {

            $flash = 'Code Name is required.';
            $flash_type = 'error';
            $mode = 'add';

        } else {

            $stmt = $conn->prepare("
                INSERT INTO org_locations
                (
                    code_name,
                    location_name,
                    address1,
                    address2,
                    city,
                    state,
                    country,
                    pin_code,
                    office_phone,
                    phone,
                    fax,
                    website,
                    timezone,
                    remarks,
                    status,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "ssssssssssssss",
                    $code_name,
                    $location_name,
                    $address1,
                    $address2,
                    $city,
                    $state,
                    $country,
                    $pin_code,
                    $office_phone,
                    $phone,
                    $fax,
                    $website,
                    $timezone,
                    $remarks
                );

                if ($stmt->execute()) {
                    $active_id = (int)$stmt->insert_id;
                    $flash = "Location \"$code_name\" added successfully.";
                    $flash_type = 'success';
                    $mode = 'view';
                } else {
                    $flash = 'Insert failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $mode = 'add';
                }

            } else {
                $flash = 'Prepare failed: ' . $conn->error;
                $flash_type = 'error';
                $mode = 'add';
            }
        }
    }

    if ($action === 'edit_location') {

        $id = (int)($_POST['edit_id'] ?? 0);

        if ($id <= 0) {

            $flash = 'Invalid location ID.';
            $flash_type = 'error';
            $mode = 'view';

        } elseif ($code_name === '') {

            $flash = 'Code Name is required.';
            $flash_type = 'error';
            $active_id = $id;
            $mode = 'edit';

        } else {

            $stmt = $conn->prepare("
                UPDATE org_locations
                SET
                    code_name = ?,
                    location_name = ?,
                    address1 = ?,
                    address2 = ?,
                    city = ?,
                    state = ?,
                    country = ?,
                    pin_code = ?,
                    office_phone = ?,
                    phone = ?,
                    fax = ?,
                    website = ?,
                    timezone = ?,
                    remarks = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "ssssssssssssssi",
                    $code_name,
                    $location_name,
                    $address1,
                    $address2,
                    $city,
                    $state,
                    $country,
                    $pin_code,
                    $office_phone,
                    $phone,
                    $fax,
                    $website,
                    $timezone,
                    $remarks,
                    $id
                );

                if ($stmt->execute()) {
                    $flash = 'Location updated successfully.';
                    $flash_type = 'success';
                    $active_id = $id;
                    $mode = 'view';
                } else {
                    $flash = 'Update failed: ' . $stmt->error;
                    $flash_type = 'error';
                    $active_id = $id;
                    $mode = 'edit';
                }

            } else {
                $flash = 'Prepare failed: ' . $conn->error;
                $flash_type = 'error';
                $active_id = $id;
                $mode = 'edit';
            }
        }
    }
}

/* ── Fetch locations from DB ── */
$locs = [];

if ($search !== '') {

    $like = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT *
        FROM org_locations
        WHERE status = 'active'
        AND (
            code_name LIKE ?
            OR location_name LIKE ?
            OR city LIKE ?
            OR state LIKE ?
        )
        ORDER BY code_name ASC
    ");

    if ($stmt) {
        $stmt->bind_param("ssss", $like, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $locs[] = $row;
        }
    }

} else {

    $res = $conn->query("
        SELECT *
        FROM org_locations
        WHERE status = 'active'
        ORDER BY code_name ASC
    ");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $locs[] = $row;
        }
    }
}

/* Default first item */
if ($active_id === 0 && $mode === 'view' && count($locs) > 0) {
    $active_id = (int)$locs[0]['id'];
}

/* Find active location */
$active_loc = null;

if ($active_id > 0) {

    $stmt = $conn->prepare("SELECT * FROM org_locations WHERE id = ? LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("i", $active_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $active_loc = $res->fetch_assoc();
        }
    }
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">
<div class="loc-wrapper">

  <!-- Config nav tabs -->
  <div class="cfg-tabs">
    <?php foreach (['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll',
                    'Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
    <a href="configuration#<?= e($k) ?>"
       class="cfg-tab <?= $k==='Organization'?'active':'' ?>">
       <?= e($l) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="loc-inner">

    <?php if ($flash): ?>
      <div class="flash-msg <?= e($flash_type) ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- Top bar -->
    <div class="loc-topbar" style="padding:10px 32px;overflow:hidden; border-bottom:1px solid #E5E7EB">
      <div class="oct">
            <div class="ctc-bc">
                <a href="configuration#Organization">Organization</a>
                <span class="sep">›</span>
                <span class="cur">Location</span>
            </div>
        </div>

      <?php if ($mode !== 'add'): ?>
      <button class="btn-add-loc" onclick="setMode('add')">
        <i class="fa-solid fa-plus"></i> Add Location
      </button>
      <?php endif; ?>
    </div>

    <?php if ($mode === 'add'): ?>

    <!-- ════════════ ADD FORM ════════════ -->
    <div style="background:#fff;border:1px solid #e8ecf0;border-radius:10px;padding:28px 32px">
      <div style="font-size:15px;font-weight:700;color:#1e2d3d;margin-bottom:24px">Add Location</div>

      <form method="POST">
        <input type="hidden" name="action" value="add_location">

        <div class="loc-field-grid" style="margin-bottom:20px">

          <div class="loc-field">
            <label>Code Name <span style="color:#ef4444">*</span></label>
            <input type="text" name="code_name" class="loc-input"
                   value="<?= e($_POST['code_name'] ?? '') ?>" required>
          </div>

          <div class="loc-field">
            <label>Location Name</label>
            <input type="text" name="location_name" class="loc-input"
                   value="<?= e($_POST['location_name'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>Address 1</label>
            <input type="text" name="address1" class="loc-input"
                   value="<?= e($_POST['address1'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>Address 2</label>
            <input type="text" name="address2" class="loc-input"
                   value="<?= e($_POST['address2'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>City</label>
            <input type="text" name="city" class="loc-input"
                   value="<?= e($_POST['city'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>State</label>
            <input type="text" name="state" class="loc-input"
                   value="<?= e($_POST['state'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>Country</label>
            <input type="text" name="country" class="loc-input"
                   value="<?= e($_POST['country'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>Pin Code</label>
            <input type="text" name="pin_code" class="loc-input"
                   value="<?= e($_POST['pin_code'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>Office Phone Number</label>
            <input type="text" name="office_phone" class="loc-input"
                   value="<?= e($_POST['office_phone'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>Phone Number</label>
            <input type="text" name="phone" class="loc-input"
                   value="<?= e($_POST['phone'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>Fax</label>
            <input type="text" name="fax" class="loc-input"
                   value="<?= e($_POST['fax'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>Website</label>
            <input type="text" name="website" class="loc-input"
                   value="<?= e($_POST['website'] ?? '') ?>">
          </div>

          <div class="loc-field">
            <label>Timezone</label>
            <select name="timezone" class="loc-select">
              <?php foreach ($timezones as $tz): ?>
                <option value="<?= e($tz) ?>"
                  <?= (($_POST['timezone'] ?? $timezones[0]) === $tz) ? 'selected' : '' ?>>
                  <?= e($tz) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="loc-field">
            <label>Remarks</label>
            <input type="text" name="remarks" class="loc-input"
                   value="<?= e($_POST['remarks'] ?? '') ?>">
          </div>

        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px;padding-top:16px;border-top:1px solid #e8ecf0">
          <button type="button" class="btn-cancel" onclick="setMode('view')">Cancel</button>
          <button type="submit" class="btn-save">Add</button>
        </div>
      </form>
    </div>

    <?php else: ?>

    <!-- ════════════ SPLIT PANEL ════════════ -->
    <div class="loc-panel">

      <!-- Left list -->
      <div class="loc-list-col">
        <div class="loc-list-heading">List of Locations</div>

        <div class="loc-search-wrap">
          <form method="GET" style="display:contents">
            <input type="hidden" name="id" value="<?= (int)$active_id ?>">
            <input type="hidden" name="mode" value="view">

            <div class="loc-search-inner">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" name="q" class="loc-search-input"
                     placeholder="Search items"
                     value="<?= e($search) ?>"
                     onchange="this.form.submit()">
            </div>
          </form>
        </div>

        <div class="loc-list-scroll">
          <?php foreach ($locs as $loc): ?>
            <div class="loc-item <?= ((int)$loc['id'] === (int)$active_id && $mode === 'view') ? 'active' : '' ?>"
                 onclick="selectLoc(<?= (int)$loc['id'] ?>)">
              <span class="loc-item-name"><?= e($loc['code_name']) ?></span>
              <i class="fa-solid <?= ((int)$loc['id'] === (int)$active_id && $mode === 'view') ? 'fa-chevron-right' : 'fa-chevron-down' ?> loc-item-chevron"></i>
            </div>
          <?php endforeach; ?>

          <?php if (empty($locs)): ?>
            <div style="padding:24px 16px;color:#9ca3af;font-size:13px">No locations found.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right detail / edit -->
      <div class="loc-detail-col">
        <div class="loc-detail-heading">Location Details</div>

        <?php if ($mode === 'edit' && $active_loc): ?>

        <!-- EDIT FORM -->
        <div class="loc-detail-title" style="margin-bottom:22px">
          Edit — <?= e($active_loc['code_name']) ?>
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="edit_location">
          <input type="hidden" name="edit_id" value="<?= (int)$active_loc['id'] ?>">

          <div class="loc-field-grid" style="margin-bottom:20px">

            <div class="loc-field">
              <label>Code Name <span style="color:#ef4444">*</span></label>
              <input type="text" name="code_name" class="loc-input"
                     value="<?= e($_POST['code_name'] ?? $active_loc['code_name']) ?>" required>
            </div>

            <div class="loc-field">
              <label>Location Name</label>
              <input type="text" name="location_name" class="loc-input"
                     value="<?= e($_POST['location_name'] ?? $active_loc['location_name']) ?>">
            </div>

            <div class="loc-field">
              <label>Address 1</label>
              <input type="text" name="address1" class="loc-input"
                     value="<?= e($_POST['address1'] ?? $active_loc['address1']) ?>">
            </div>

            <div class="loc-field">
              <label>Address 2</label>
              <input type="text" name="address2" class="loc-input"
                     value="<?= e($_POST['address2'] ?? $active_loc['address2']) ?>">
            </div>

            <div class="loc-field">
              <label>City</label>
              <input type="text" name="city" class="loc-input"
                     value="<?= e($_POST['city'] ?? $active_loc['city']) ?>">
            </div>

            <div class="loc-field">
              <label>State</label>
              <input type="text" name="state" class="loc-input"
                     value="<?= e($_POST['state'] ?? $active_loc['state']) ?>">
            </div>

            <div class="loc-field">
              <label>Country</label>
              <input type="text" name="country" class="loc-input"
                     value="<?= e($_POST['country'] ?? $active_loc['country']) ?>">
            </div>

            <div class="loc-field">
              <label>Pin Code</label>
              <input type="text" name="pin_code" class="loc-input"
                     value="<?= e($_POST['pin_code'] ?? $active_loc['pin_code']) ?>">
            </div>

            <div class="loc-field">
              <label>Office Phone Number</label>
              <input type="text" name="office_phone" class="loc-input"
                     value="<?= e($_POST['office_phone'] ?? $active_loc['office_phone']) ?>">
            </div>

            <div class="loc-field">
              <label>Phone Number</label>
              <input type="text" name="phone" class="loc-input"
                     value="<?= e($_POST['phone'] ?? $active_loc['phone']) ?>">
            </div>

            <div class="loc-field">
              <label>Fax</label>
              <input type="text" name="fax" class="loc-input"
                     value="<?= e($_POST['fax'] ?? $active_loc['fax']) ?>">
            </div>

            <div class="loc-field">
              <label>Website</label>
              <input type="text" name="website" class="loc-input"
                     value="<?= e($_POST['website'] ?? $active_loc['website']) ?>">
            </div>

            <div class="loc-field">
              <label>Timezone</label>
              <select name="timezone" class="loc-select">
                <?php foreach ($timezones as $tz): ?>
                  <option value="<?= e($tz) ?>"
                    <?= (($_POST['timezone'] ?? $active_loc['timezone']) === $tz) ? 'selected' : '' ?>>
                    <?= e($tz) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="loc-field">
              <label>Remarks</label>
              <input type="text" name="remarks" class="loc-input"
                     value="<?= e($_POST['remarks'] ?? $active_loc['remarks']) ?>">
            </div>

          </div>

          <div class="loc-form-actions">
            <button type="button" class="btn-cancel"
                    onclick="window.location.href='?id=<?= (int)$active_loc['id'] ?>&mode=view'">
              Cancel
            </button>
            <button type="submit" class="btn-save">Update</button>
          </div>
        </form>

        <?php elseif ($active_loc): ?>

        <!-- VIEW DETAIL -->
        <div class="loc-detail-title-bar">
          <div class="loc-detail-title"><?= e($active_loc['code_name']) ?></div>

          <button class="btn-edit-link"
                  onclick="window.location.href='?id=<?= (int)$active_loc['id'] ?>&mode=edit'">
            <i class="fa-regular fa-pen-to-square"></i> Edit Details
          </button>
        </div>

        <div class="loc-field-grid">

          <div class="loc-field">
            <label>Code Name</label>
            <div class="loc-field-value"><?= e($active_loc['code_name']) ?></div>
          </div>

          <div class="loc-field">
            <label>Location Name</label>
            <div class="loc-field-value"><?= e($active_loc['location_name']) ?></div>
          </div>

          <div class="loc-field">
            <label>Address 1</label>
            <div class="loc-field-value"><?= e($active_loc['address1']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>Address 2</label>
            <div class="loc-field-value"><?= e($active_loc['address2']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>City</label>
            <div class="loc-field-value"><?= e($active_loc['city']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>State</label>
            <div class="loc-field-value"><?= e($active_loc['state']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>Country</label>
            <div class="loc-field-value"><?= e($active_loc['country']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>Pin Code</label>
            <div class="loc-field-value"><?= e($active_loc['pin_code']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>Office Phone Number</label>
            <div class="loc-field-value"><?= e($active_loc['office_phone']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>Phone Number</label>
            <div class="loc-field-value"><?= e($active_loc['phone']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>Fax</label>
            <div class="loc-field-value"><?= e($active_loc['fax']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>Website</label>
            <div class="loc-field-value"><?= e($active_loc['website']) ?>&nbsp;</div>
          </div>

          <div class="loc-field">
            <label>Timezone</label>
            <div class="loc-field-value"><?= e($active_loc['timezone']) ?></div>
          </div>

          <div class="loc-field">
            <label>Remarks</label>
            <div class="loc-field-value"><?= e($active_loc['remarks']) ?>&nbsp;</div>
          </div>

        </div>

        <?php else: ?>

        <div class="loc-empty">Select a location to view details.</div>

        <?php endif; ?>

      </div>
    </div>

    <?php endif; ?>

  </div>
</div>
</div>

<script>
function selectLoc(id) {
  const url = new URL(window.location.href);
  url.searchParams.set('id', id);
  url.searchParams.set('mode', 'view');
  window.location.href = url.toString();
}

function setMode(mode, id) {
  const url = new URL(window.location.href);
  url.searchParams.set('mode', mode);

  if (id !== undefined) {
    url.searchParams.set('id', id);
  }

  window.location.href = url.toString();
}
</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>