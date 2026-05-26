<?php
session_start();

if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'News / Announcements';

function esc($v){
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function fmt_date($d){
    return $d ? date('d-m-Y', strtotime($d)) : '';
}

/* ─────────────────────────────────────────
   SAVE / UPDATE / DELETE
───────────────────────────────────────── */

$save_ok  = false;
$save_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['_action'] ?? '';

    /* ── ADD ── */
    if ($action === 'add') {

        $type           = mysqli_real_escape_string($conn, trim($_POST['type'] ?? ''));
        $heading        = mysqli_real_escape_string($conn, trim($_POST['heading'] ?? ''));
        $description    = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
        $schedule_from  = mysqli_real_escape_string($conn, $_POST['schedule_from'] ?? '');
        $schedule_to    = mysqli_real_escape_string($conn, $_POST['schedule_to'] ?? '');

        if ($heading != '' && $description != '') {

            mysqli_query($conn,"
                INSERT INTO news_announcements(
                    type,
                    heading,
                    description,
                    schedule_from,
                    schedule_to,
                    status
                )
                VALUES(
                    '$type',
                    '$heading',
                    '$description',
                    '$schedule_from',
                    '$schedule_to',
                    '1'
                )
            ");

            $save_ok  = true;
            $save_msg = 'Announcement added successfully!';
        }
    }

    /* ── UPDATE ── */
    if ($action === 'save') {

        $ann_id         = (int)($_POST['ann_id'] ?? 0);

        $type           = mysqli_real_escape_string($conn, trim($_POST['type'] ?? ''));
        $heading        = mysqli_real_escape_string($conn, trim($_POST['heading'] ?? ''));
        $description    = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
        $schedule_from  = mysqli_real_escape_string($conn, $_POST['schedule_from'] ?? '');
        $schedule_to    = mysqli_real_escape_string($conn, $_POST['schedule_to'] ?? '');
        $status         = (int)($_POST['status'] ?? 1);

        mysqli_query($conn,"
            UPDATE news_announcements
            SET
                type='$type',
                heading='$heading',
                description='$description',
                schedule_from='$schedule_from',
                schedule_to='$schedule_to',
                status='$status'
            WHERE id='$ann_id'
        ");

        $save_ok  = true;
        $save_msg = 'Announcement updated successfully!';
    }

    /* ── DELETE ── */
    if ($action === 'delete') {

        $ann_id = (int)($_POST['ann_id'] ?? 0);

        mysqli_query($conn,"
            DELETE FROM news_announcements
            WHERE id='$ann_id'
        ");

        $save_ok  = true;
        $save_msg = 'Announcement deleted successfully!';
    }
}

/* ─────────────────────────────────────────
   FETCH DATA
───────────────────────────────────────── */

$announcements = [];

$q = mysqli_query($conn,"
    SELECT *
    FROM news_announcements
    ORDER BY id DESC
");

while($row = mysqli_fetch_assoc($q)){

    $announcements[] = [
        'id'      => $row['id'],
        'type'    => $row['type'],
        'heading' => $row['heading'],
        'desc'    => $row['description'],
        'from'    => $row['schedule_from'],
        'to'      => $row['schedule_to'],
        'active'  => $row['status']
    ];
}

$types = ['ANNOUNCEMENTS','NEWS'];

/* ─────────────────────────────────────────
   ACTIVE ITEM
───────────────────────────────────────── */

$active_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : null;

$mode = $_GET['mode'] ?? 'add';

$active_ann = null;

if($active_id){

    foreach($announcements as $a){

        if($a['id'] == $active_id){

            $active_ann = $a;
            break;
        }
    }

    if(!$active_ann){
        $mode = 'add';
    }
}

ob_start();
?>

<link rel="stylesheet" href="includes/assets/style.css">

<style>

/* KEEPING YOUR ORIGINAL CSS */

.cfg-tabs {
    display:flex;
    align-items:center;
    border-bottom:1px solid #E5E7EB;
    background:#fff;
    overflow-x:auto;
    scrollbar-width:none;
}

.cfg-tabs::-webkit-scrollbar {
    display:none;
}

.cfg-tab {
    padding:14px 20px;
    font-size:13.5px;
    font-weight:500;
    color:#6B7280;
    cursor:pointer;
    border:none;
    background:transparent;
    border-bottom:2.5px solid transparent;
    white-space:nowrap;
    transition:color .15s,border-color .15s;
    text-decoration:none;
    display:block;
    margin-bottom:-1px;
}

.cfg-tab:hover {
    color:#111827;
}

.cfg-tab.active {
    color:#2563EB;
    border-bottom-color:#2563EB;
    font-weight:600;
}

.na-bc {
    display:flex;
    align-items:center;
    gap:8px;
    font-size:13.5px;
    font-weight:500;
    color:#374151;
    padding:14px 24px 0;
}

.na-bc a {
    color:#374151;
    text-decoration:none;
}

.na-bc .sep {
    color:#D1D5DB;
}

.na-bc .cur {
    font-weight:600;
}

.na-col-labels {
    display:grid;
    grid-template-columns:440px 1fr;
    padding:10px 0 10px 24px;
    font-size:12.5px;
    color:#9CA3AF;
    font-weight:500;
    border-bottom:1px solid #E5E7EB;
}

.na-layout {
    display:grid;
    grid-template-columns:440px 1fr;
    min-height:480px;
}

.na-left {
    border-right:1px solid #E5E7EB;
    padding:12px 0;
}

.na-add-btn-wrap {
    padding:0 16px 12px;
}

.na-add-list-btn {
    display:flex;
    align-items:center;
    gap:6px;
    width:100%;
    padding:9px 14px;
    background:#2563EB;
    color:#fff;
    border:none;
    border-radius:8px;
    font-size:13px;
    font-weight:600;
    cursor:pointer;
    text-decoration:none;
    justify-content:center;
}

.na-list-item {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    padding:12px 18px;
    border-bottom:1px solid #F3F4F6;
    text-decoration:none;
    transition:background .15s;
}

.na-list-item:hover {
    background:#F9FAFB;
}

.na-list-item.active {
    background:#EFF6FF;
}

.na-list-type {
    display:inline-flex;
    align-items:center;
    border-radius:20px;
    font-size:10.5px;
    font-weight:700;
    padding:2px 8px;
    margin-bottom:4px;
}

.na-list-type.ANNOUNCEMENTS {
    background:#EDE9FE;
    color:#6D28D9;
}

.na-list-type.NEWS {
    background:#DBEAFE;
    color:#1D4ED8;
}

.na-list-heading {
    font-size:13px;
    font-weight:600;
    color:#111827;
    margin-bottom:3px;
}

.na-list-dates {
    font-size:11.5px;
    color:#9CA3AF;
}

.na-list-status {
    width:8px;
    height:8px;
    border-radius:50%;
    margin-top:5px;
}

.na-list-status.active {
    background:#059669;
}

.na-list-status.inactive {
    background:#D1D5DB;
}

.na-right {
    padding:28px 32px 32px;
}

.na-form-title {
    font-size:14px;
    font-weight:700;
    color:#111827;
    letter-spacing:.4px;
    text-transform:uppercase;
    margin-bottom:24px;
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.na-fg {
    display:flex;
    flex-direction:column;
    gap:5px;
    margin-bottom:22px;
}

.na-fg label {
    font-size:13px;
    color:#374151;
}

.na-fg label .req {
    color:#DC2626;
}

.na-fg input,
.na-fg textarea,
.na-fg select {
    border:none;
    border-bottom:1.5px solid #D1D5DB;
    padding:8px 0;
    background:transparent;
    outline:none;
    width:100%;
    font-size:14px;
}

.na-date-row {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:24px;
}

.na-actions {
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding-top:24px;
}

.na-save-btn {
    padding:9px 30px;
    background:#2563EB;
    color:#fff;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

.na-cancel-btn {
    padding:9px 26px;
    border:1px solid #D1D5DB;
    border-radius:8px;
    text-decoration:none;
    color:#374151;
}

.na-delete-btn {
    padding:9px 22px;
    background:#fff;
    color:#DC2626;
    border:1px solid #DC2626;
    border-radius:8px;
    cursor:pointer;
}

.na-view-val {
    font-size:14px;
    color:#111827;
    padding-bottom:6px;
    border-bottom:1.5px solid #D1D5DB;
}

.na-toast {
    position:fixed;
    bottom:24px;
    left:50%;
    transform:translateX(-50%) translateY(80px);
    background:#111827;
    color:#fff;
    padding:11px 20px;
    border-radius:10px;
    font-size:13px;
    z-index:999;
    display:flex;
    align-items:center;
    gap:8px;
    transition:transform .3s ease;
}

.na-toast.show {
    transform:translateX(-50%) translateY(0);
}

</style>

<?php if($save_ok): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    naToast('✅','<?= esc($save_msg) ?>');
});
</script>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">

    <div class="cfg-tabs">
        <?php foreach([
            'AccountInfo'=>'Account Info',
            'Organization'=>'Organization',
            'Payroll'=>'Payroll',
            'Attendance'=>'Attendance',
            'Leave'=>'Leave',
            'Training'=>'Training',
            'Others'=>'Others'
        ] as $k=>$l): ?>

        <a href="configuration#<?= $k ?>"
           class="cfg-tab <?= $k==='Others'?'active':'' ?>">
            <?= $l ?>
        </a>

        <?php endforeach; ?>
    </div>

    <div class="na-bc">
        <a href="configuration#Others">Others</a>
        <span class="sep">›</span>
        <span class="cur">News / Announcement</span>
    </div>

    <div class="na-col-labels">
        <span>List of News / Announcement</span>
        <span>News / Announcement Details</span>
    </div>

    <div class="na-layout">

        <!-- LEFT -->
        <div class="na-left">

            <div class="na-add-btn-wrap">
                <a href="?mode=add" class="na-add-list-btn">
                    + Add News / Announcement
                </a>
            </div>

            <?php foreach($announcements as $a): ?>

            <a href="?id=<?= $a['id'] ?>&mode=view"
               class="na-list-item <?= ($active_id==$a['id'] && $mode!='add') ? 'active' : '' ?>">

                <div style="flex:1">

                    <div class="na-list-type <?= esc($a['type']) ?>">
                        <?= esc($a['type']) ?>
                    </div>

                    <div class="na-list-heading">
                        <?= esc($a['heading']) ?>
                    </div>

                    <div class="na-list-dates">
                        <?= fmt_date($a['from']) ?> → <?= fmt_date($a['to']) ?>
                    </div>

                </div>

                <div class="na-list-status <?= $a['active'] ? 'active':'inactive' ?>"></div>

            </a>

            <?php endforeach; ?>

        </div>

        <!-- RIGHT -->
        <div class="na-right">

        <?php if($mode == 'add'): ?>

            <div class="na-form-title">
                ADD NEWS / ANNOUNCEMENT
            </div>

            <form method="POST" id="naForm">

                <input type="hidden" name="_action" value="add">

                <div class="na-fg">
                    <label><span class="req">*</span> Type</label>

                    <select name="type">
                        <?php foreach($types as $t): ?>
                        <option value="<?= esc($t) ?>">
                            <?= esc($t) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="na-fg">
                    <label><span class="req">*</span> Heading</label>
                    <input type="text" name="heading" required>
                </div>

                <div class="na-fg">
                    <label><span class="req">*</span> Description</label>
                    <textarea name="description" rows="4" required></textarea>
                </div>

                <div class="na-date-row">

                    <div class="na-fg">
                        <label><span class="req">*</span> Schedule From</label>
                        <input type="date"
                               name="schedule_from"
                               value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="na-fg">
                        <label><span class="req">*</span> Schedule To</label>
                        <input type="date"
                               name="schedule_to"
                               value="<?= date('Y-m-d') ?>">
                    </div>

                </div>

                <div class="na-actions">
                    <button type="submit"
                            class="na-save-btn"
                            onclick="return validateNaForm()">
                        Add
                    </button>
                </div>

            </form>

        <?php elseif($mode == 'view' && $active_ann): ?>

            <div class="na-form-title">

                <span><?= esc($active_ann['heading']) ?></span>

                <a href="?id=<?= $active_id ?>&mode=edit">
                    Edit
                </a>

            </div>

            <div class="na-fg">
                <label>Type</label>
                <div class="na-view-val">
                    <?= esc($active_ann['type']) ?>
                </div>
            </div>

            <div class="na-fg">
                <label>Heading</label>
                <div class="na-view-val">
                    <?= esc($active_ann['heading']) ?>
                </div>
            </div>

            <div class="na-fg">
                <label>Description</label>
                <div class="na-view-val">
                    <?= nl2br(esc($active_ann['desc'])) ?>
                </div>
            </div>

            <div class="na-date-row">

                <div class="na-fg">
                    <label>Schedule From</label>
                    <div class="na-view-val">
                        <?= fmt_date($active_ann['from']) ?>
                    </div>
                </div>

                <div class="na-fg">
                    <label>Schedule To</label>
                    <div class="na-view-val">
                        <?= fmt_date($active_ann['to']) ?>
                    </div>
                </div>

            </div>

        <?php elseif($mode == 'edit' && $active_ann): ?>

            <div class="na-form-title">
                EDIT NEWS / ANNOUNCEMENT
            </div>

            <form method="POST" id="naForm">

                <input type="hidden" name="_action" value="save">
                <input type="hidden" name="ann_id" value="<?= (int)$active_ann['id'] ?>">

                <div class="na-fg">
                    <label><span class="req">*</span> Type</label>

                    <select name="type">

                        <?php foreach($types as $t): ?>

                        <option value="<?= esc($t) ?>"
                            <?= $active_ann['type']==$t ? 'selected':'' ?>>

                            <?= esc($t) ?>

                        </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="na-fg">
                    <label><span class="req">*</span> Heading</label>

                    <input type="text"
                           name="heading"
                           value="<?= esc($active_ann['heading']) ?>">
                </div>

                <div class="na-fg">
                    <label><span class="req">*</span> Description</label>

                    <textarea name="description" rows="4"><?= esc($active_ann['desc']) ?></textarea>
                </div>

                <div class="na-date-row">

                    <div class="na-fg">
                        <label><span class="req">*</span> Schedule From</label>

                        <input type="date"
                               name="schedule_from"
                               value="<?= esc($active_ann['from']) ?>">
                    </div>

                    <div class="na-fg">
                        <label><span class="req">*</span> Schedule To</label>

                        <input type="date"
                               name="schedule_to"
                               value="<?= esc($active_ann['to']) ?>">
                    </div>

                </div>

                <div class="na-fg">
                    <label>Status</label>

                    <select name="status">

                        <option value="1"
                            <?= $active_ann['active'] ? 'selected':'' ?>>
                            Active
                        </option>

                        <option value="0"
                            <?= !$active_ann['active'] ? 'selected':'' ?>>
                            Inactive
                        </option>

                    </select>
                </div>

                <div class="na-actions">

                    <button type="submit"
                            formaction=""
                            formmethod="POST"
                            name="_action"
                            value="delete"
                            class="na-delete-btn"
                            onclick="return confirm('Delete announcement?')">
                        Delete
                    </button>

                    <a href="?id=<?= $active_id ?>&mode=view"
                       class="na-cancel-btn">
                        Cancel
                    </a>

                    <button type="submit"
                            class="na-save-btn"
                            onclick="return validateNaForm()">
                        Save
                    </button>

                </div>

            </form>

        <?php endif; ?>

        </div>

    </div>

</div>

<div class="na-toast" id="naToastEl">
    <span id="naToastIcon">✅</span>
    <span id="naToastMsg">Done!</span>
</div>

<script>

function naToast(icon,msg){

    var t = document.getElementById('naToastEl');

    document.getElementById('naToastIcon').innerHTML = icon;
    document.getElementById('naToastMsg').innerHTML = msg;

    t.classList.add('show');

    setTimeout(function(){
        t.classList.remove('show');
    },3000);
}

function validateNaForm(){

    var form = document.getElementById('naForm');

    var req = form.querySelectorAll('[required]');

    for(var i=0;i<req.length;i++){

        if(req[i].value.trim() == ''){

            naToast('⚠','Please fill all required fields');
            req[i].focus();

            return false;
        }
    }

    return true;
}

</script>

<?php
$page_content = ob_get_clean();

include 'includes/header.php';

echo $page_content;

include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>