<?php
session_start();

// Handle Authentication
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

// If your connection variable is different (e.g., $db or $link), assign it here:
$conn = $conn ?? $db ?? null;

// Writable directory for uploaded face images (outside webroot is better; adjust as needed)
define('FACE_UPLOAD_DIR', __DIR__ . '/../uploads/face_data/');
define('FACE_UPLOAD_URL', '/uploads/face_data/');   // relative URL served to browser
define('MAX_SLOTS', 6);

// Ensure upload dir exists
if (!is_dir(FACE_UPLOAD_DIR)) {
    mkdir(FACE_UPLOAD_DIR, 0755, true);
}

// ========================================================================
// API / AJAX HANDLER (Self-contained, using strictly mysqli)
// ========================================================================
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action) {
    // Clear out any accidental whitespace or HTML from included files
    // This prevents the "Network Error" caused by invalid JSON
    if (ob_get_length()) ob_clean(); 
    
    header('Content-Type: application/json');
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection missing. Check your variable name in db_client.php']);
        exit();
    }

    try {
        switch ($action) {

            /* ── SEARCH EMPLOYEES ─────────────────────────────────────── */
            case 'search':
                $q = trim($_GET['q'] ?? '');
                if (strlen($q) < 1) {
                    echo json_encode(['success' => true, 'data' => []]);
                    break;
                }
                
                $q_esc = mysqli_real_escape_string($conn, $q);
                $like = '%' . $q_esc . '%';
                
                $sql = "SELECT id,
                               CONCAT(first_name, ' ', last_name) AS name,
                               employee_code,
                               designation,
                               COALESCE(branch, location, city, '') AS location,
                               profile_photo
                        FROM employees
                        WHERE is_active = 1
                          AND (first_name LIKE '$like'
                               OR last_name LIKE '$like'
                               OR CONCAT(first_name,' ',last_name) LIKE '$like'
                               OR employee_code LIKE '$like')
                        ORDER BY first_name
                        LIMIT 20";
                
                $res = mysqli_query($conn, $sql);
                if (!$res) throw new Exception(mysqli_error($conn));

                $data = [];
                while ($row = mysqli_fetch_assoc($res)) {
                    $data[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $data]);
                break;

            /* ── GET EXISTING FACE DATA FOR EMPLOYEE ─────────────────── */
            case 'get_faces':
                $empId = (int)($_GET['employee_id'] ?? 0);
                if (!$empId) { echo json_encode(['success' => false, 'message' => 'Invalid employee']); break; }

                $sql = "SELECT id, slot_index, file_url
                        FROM employee_face_data
                        WHERE employee_id = $empId AND is_deleted = 0
                        ORDER BY slot_index";
                
                $res = mysqli_query($conn, $sql);
                if (!$res) throw new Exception(mysqli_error($conn));

                $data = [];
                while ($row = mysqli_fetch_assoc($res)) {
                    $data[] = $row;
                }

                echo json_encode(['success' => true, 'data' => $data]);
                break;

            /* ── UPLOAD SINGLE FACE IMAGE ─────────────────────────────── */
            case 'upload_face':
                $empId     = (int)($_POST['employee_id'] ?? 0);
                $slotIndex = (int)($_POST['slot_index']  ?? 0);

                if (!$empId || $slotIndex < 0 || $slotIndex >= MAX_SLOTS) {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); break;
                }
                if (empty($_FILES['face_image']) || $_FILES['face_image']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'File upload failed']); break;
                }

                $file     = $_FILES['face_image'];
                $mimeType = mime_content_type($file['tmp_name']);
                $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
                
                if (!in_array($mimeType, $allowed)) {
                    echo json_encode(['success' => false, 'message' => 'Only JPEG/PNG/WebP images are allowed']); break;
                }
                if ($file['size'] > 5 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'File size must be under 5 MB']); break;
                }

                $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeType];
                $filename = "emp_{$empId}_slot{$slotIndex}_" . time() . ".{$ext}";
                $dest     = FACE_UPLOAD_DIR . $filename;

                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    echo json_encode(['success' => false, 'message' => 'Could not save file']); break;
                }

                $fileUrlEsc = mysqli_real_escape_string($conn, FACE_UPLOAD_URL . $filename);
                $userId     = (int)($_SESSION['user_id'] ?? 0);

                // Upsert: remove old record for this slot, insert new
                $upd_sql = "UPDATE employee_face_data SET is_deleted=1, updated_at=NOW() 
                            WHERE employee_id=$empId AND slot_index=$slotIndex AND is_deleted=0";
                if (!mysqli_query($conn, $upd_sql)) throw new Exception(mysqli_error($conn));

                $ins_sql = "INSERT INTO employee_face_data (employee_id, slot_index, file_url, created_by)
                            VALUES ($empId, $slotIndex, '$fileUrlEsc', $userId)";
                if (!mysqli_query($conn, $ins_sql)) throw new Exception(mysqli_error($conn));

                $recordId = mysqli_insert_id($conn);

                echo json_encode([
                    'success'   => true,
                    'file_url'  => FACE_UPLOAD_URL . $filename,
                    'record_id' => $recordId,
                ]);
                break;

            /* ── SAVE ALL (batch confirm) ─────────────────────────────── */
            case 'save':
                // Images are already uploaded individually; this just marks the record as finalised.
                $empId = (int)($_POST['employee_id'] ?? 0);
                if (!$empId) { echo json_encode(['success' => false, 'message' => 'Invalid employee']); break; }

                $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employee_face_data WHERE employee_id=$empId AND is_deleted=0");
                if (!$res) throw new Exception(mysqli_error($conn));
                
                $row = mysqli_fetch_assoc($res);
                $count = (int)($row['cnt'] ?? 0);

                if ($count === 0) {
                    echo json_encode(['success' => false, 'message' => 'Please upload at least one face image.']);
                    break;
                }

                // Optionally mark employee as face-enrolled
                if (!mysqli_query($conn, "UPDATE employees SET face_enrolled=1 WHERE id=$empId")) {
                     throw new Exception(mysqli_error($conn));
                }

                echo json_encode(['success' => true, 'message' => "Facial data saved for employee #{$empId}. ({$count} image(s))"]);
                break;

            /* ── DELETE SINGLE FACE IMAGE ─────────────────────────────── */
            case 'delete_face':
                $recordId = (int)($_POST['record_id'] ?? 0);
                if (!$recordId) { echo json_encode(['success' => false, 'message' => 'Invalid record']); break; }

                if (!mysqli_query($conn, "UPDATE employee_face_data SET is_deleted=1, updated_at=NOW() WHERE id=$recordId")) {
                    throw new Exception(mysqli_error($conn));
                }

                echo json_encode(['success' => true, 'message' => 'Image removed.']);
                break;

            /* ── RESET (delete all faces for employee) ───────────────── */
            case 'reset_faces':
                $empId = (int)($_POST['employee_id'] ?? 0);
                if (!$empId) { echo json_encode(['success' => false, 'message' => 'Invalid employee']); break; }

                if (!mysqli_query($conn, "UPDATE employee_face_data SET is_deleted=1, updated_at=NOW() WHERE employee_id=$empId AND is_deleted=0")) {
                     throw new Exception(mysqli_error($conn));
                }

                echo json_encode(['success' => true, 'message' => 'All face data cleared.']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        error_log('Face Enrolment API Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred: ' . $e->getMessage()]);
    }

    // Crucial: Stop script execution so HTML is not returned in the AJAX response
    exit();
}

// ========================================================================
// PAGE RENDERER
// ========================================================================
$page_title = 'Facial Enrolment';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<style>
/* ── Config tab bar (reuse from config page) ── */
.cfg-tabs {
    display: flex;
    align-items: center;
    border-bottom: 1px solid #E5E7EB;
    background: #fff;
    overflow-x: auto;
    scrollbar-width: none;
}

.cfg-tabs::-webkit-scrollbar {
    display: none;
}

.cfg-tab {
    padding: 14px 20px;
    font-size: 13.5px;
    font-weight: 500;
    color: #6B7280;
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

.cfg-tab:hover {
    color: #111827;
}

.cfg-tab.active {
    color: #2563EB;
    border-bottom-color: #2563EB;
    font-weight: 600;
}


/* ============================================================
   face_enrolment.css  –  PerkPayroll-style Face Enrolment
   ============================================================ */

.fe-wrapper {
    background: #fff;
    min-height: calc(100vh - 130px);
    display: flex;
    flex-direction: column;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #1e293b;
}

/* ---------- Header ---------- */
.fe-header {
    padding: 20px 24px 12px;
    border-bottom: 1px solid #E2E8F0;
}

.fe-title {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
    margin: 0;
    letter-spacing: .2px;
}

/* ---------- Search row ---------- */
.fe-search-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 24px;
}

.fe-search-box {
    position: relative;
    display: flex;
    align-items: center;
    border: 1.5px solid #CBD5E1;
    border-radius: 6px;
    background: #fff;
    width: 280px;
    padding: 0 10px;
    transition: border-color .15s;
}

.fe-search-box:focus-within {
    border-color: #2563EB;
}

.fe-search-icon {
    flex-shrink: 0;
}

.fe-search-input {
    flex: 1;
    border: none;
    outline: none;
    padding: 8px 6px;
    font-size: 13.5px;
    color: #1e293b;
    background: transparent;
    font-family: inherit;
    min-width: 0;
}

.fe-search-input::placeholder {
    color: #94A3B8;
}

.fe-clear-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #94A3B8;
    font-size: 14px;
    padding: 2px 4px;
    line-height: 1;
    flex-shrink: 0;
}

.fe-clear-btn:hover {
    color: #475569;
}

/* Autocomplete dropdown */
.fe-autocomplete {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1.5px solid #E2E8F0;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
    z-index: 100;
    max-height: 260px;
    overflow-y: auto;
}

.fe-ac-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    cursor: pointer;
    transition: background .1s;
}

.fe-ac-item:hover {
    background: #F0F9FF;
}

.fe-ac-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #DBEAFE;
    color: #2563EB;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    text-transform: uppercase;
    object-fit: cover;
}

.fe-ac-name {
    font-size: 13px;
    font-weight: 600;
    color: #0F172A;
}

.fe-ac-sub {
    font-size: 11.5px;
    color: #64748B;
}

.fe-ac-empty {
    padding: 16px 14px;
    font-size: 13px;
    color: #94A3B8;
    text-align: center;
}

/* Search button */
.fe-btn-search {
    padding: 8px 20px;
    background: #2563EB;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
    white-space: nowrap;
}

.fe-btn-search:hover {
    background: #1D4ED8;
}

/* ---------- Body (two-column) ---------- */
.fe-body {
    display: flex;
    flex: 1;
    padding: 20px 24px;
    gap: 32px;
    align-items: flex-start;
}

/* Left panel */
.fe-left {
    width: 300px;
    flex-shrink: 0;
}

.fe-empty-prompt {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 340px;
    font-size: 13.5px;
    color: #94A3B8;
    text-align: center;
    line-height: 1.6;
}

/* Employee card */
.fe-emp-card {
    display: flex;
    align-items: center;
    gap: 14px;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 16px 18px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
    cursor: default;
}

.fe-emp-avatar {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #E2E8F0;
    color: #64748B;
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    text-transform: uppercase;
    object-fit: cover;
    overflow: hidden;
}

.fe-emp-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.fe-emp-info {
    flex: 1;
    min-width: 0;
}

.fe-emp-name {
    font-size: 13.5px;
    font-weight: 700;
    color: #0F172A;
}

.fe-emp-meta {
    font-size: 12px;
    color: #64748B;
    margin-top: 3px;
}

/* Right panel */
.fe-right {
    flex: 1;
}

/* Upload slots grid: 4 top + 2 bottom = 6 total */
.fe-slots-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

/* slot positions 5 and 6 are shifted under col 1-2 */
.fe-slot:nth-child(n+5) {
    grid-column: auto;
}

/* Bottom row only 2 wide — handled by separate row wrapper in JS */

.fe-slot {
    aspect-ratio: 1 / 1;
    border: 1.5px dashed #CBD5E1;
    border-radius: 10px;
    background: #FAFAFA;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: border-color .15s, background .15s;
}

.fe-slot:hover {
    border-color: #93C5FD;
    background: #F0F9FF;
}

.fe-slot.has-image {
    border-color: #93C5FD;
    border-style: solid;
    background: #fff;
}

.fe-slot-plus {
    font-size: 28px;
    color: #CBD5E1;
    line-height: 1;
    user-select: none;
    transition: color .15s;
}

.fe-slot:hover .fe-slot-plus {
    color: #2563EB;
}

.fe-slot-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
}

.fe-slot-remove {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(239, 68, 68, .9);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    line-height: 1;
    opacity: 0;
    transition: opacity .15s;
    z-index: 2;
}

.fe-slot.has-image:hover .fe-slot-remove {
    opacity: 1;
}

/* Uploading overlay */
.fe-slot-loading {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, .8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3;
}

.fe-spinner {
    width: 22px;
    height: 22px;
    border: 2.5px solid #E2E8F0;
    border-top-color: #2563EB;
    border-radius: 50%;
    animation: fe-spin .6s linear infinite;
}

@keyframes fe-spin {
    to {
        transform: rotate(360deg);
    }
}

/* ---------- Footer ---------- */
.fe-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-top: 1px solid #E2E8F0;
    background: #fff;
}

.fe-footer-right {
    display: flex;
    gap: 12px;
}

.fe-btn-reset {
    padding: 8px 20px;
    border: 1.5px solid #EF4444;
    background: #fff;
    color: #EF4444;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}

.fe-btn-reset:hover {
    background: #FEF2F2;
}

.fe-btn-cancel {
    padding: 8px 22px;
    border: 1.5px solid #CBD5E1;
    background: #fff;
    color: #64748B;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}

.fe-btn-cancel:hover {
    background: #F8FAFC;
    border-color: #94A3B8;
}

.fe-btn-save {
    padding: 8px 24px;
    border: none;
    background: #2563EB;
    color: #fff;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background .15s;
}

.fe-btn-save:hover {
    background: #1D4ED8;
}

.fe-btn-save:disabled {
    background: #93C5FD;
    cursor: not-allowed;
}

/* ---------- Toast ---------- */
.fe-toast {
    position: fixed;
    bottom: 28px;
    right: 28px;
    background: #1E293B;
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 13.5px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, .18);
    z-index: 9999;
    opacity: 0;
    transform: translateY(12px);
    transition: opacity .25s, transform .25s;
    pointer-events: none;
    max-width: 340px;
}

.fe-toast.success {
    background: #166534;
}

.fe-toast.error {
    background: #991B1B;
}

.fe-toast.show {
    opacity: 1;
    transform: translateY(0);
}

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
    .fe-body {
        flex-direction: column;
    }

    .fe-left {
        width: 100%;
    }

    .fe-slots-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 480px) {
    .fe-slots-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px">
    <h1 class="page-title">Configuration</h1>
</div>

<div class="section-card" style="padding:0;overflow:hidden">

    <div class="cfg-tabs">
        <?php foreach(['AccountInfo'=>'Account Info','Organization'=>'Organization','Payroll'=>'Payroll','Attendance'=>'Attendance','Leave'=>'Leave','Training'=>'Training','Others'=>'Others'] as $k=>$l): ?>
        <a href="configuration#<?= $k ?>" class="cfg-tab <?= $k==='Others'?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
    <div class="fe-wrapper">

        <div class="fe-header">
            <h2 class="fe-title">Add New Facial Data</h2>
        </div>

        <div class="fe-search-row">
            <div class="fe-search-box" id="feSearchBox">
                <svg class="fe-search-icon" width="15" height="15" viewBox="0 0 15 15" fill="none">
                    <circle cx="6.5" cy="6.5" r="5" stroke="#94A3B8" stroke-width="1.5" />
                    <path d="M10.5 10.5L13.5 13.5" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" />
                </svg>
                <input type="text" class="fe-search-input" id="feSearchInput" placeholder="Search by name or #code"
                    oninput="FE.onInput(this.value)" onkeydown="if(event.key==='Enter') FE.doSearch()">
                <button class="fe-clear-btn" id="feClearBtn" onclick="FE.clearSearch()" style="display:none;">✕</button>
                <div class="fe-autocomplete" id="feAutocomplete" style="display:none;"></div>
            </div>
            <button class="fe-btn-search" onclick="FE.doSearch()">Search</button>
        </div>

        <div class="fe-body">

            <div class="fe-left" id="feLeft">
                <div class="fe-empty-prompt" id="feEmptyPrompt">
                    Search and select an employee to add facial data
                </div>
                <div class="fe-emp-card" id="feEmpCard" style="display:none;">
                    <div class="fe-emp-avatar" id="feEmpAvatar"></div>
                    <div class="fe-emp-info">
                        <div class="fe-emp-name" id="feEmpName"></div>
                        <div class="fe-emp-meta" id="feEmpMeta"></div>
                    </div>
                </div>
            </div>

            <div class="fe-right" id="feRight">
                <div class="fe-slots-empty" id="feSlotsEmpty">
                </div>
                <div class="fe-slots-grid" id="feSlotsGrid" style="display:none;">
                </div>
            </div>

        </div>

        <div class="fe-footer" id="feFooter" style="display:none;">
            <button class="fe-btn-reset" onclick="FE.reset()">Reset</button>
            <div class="fe-footer-right">
                <button class="fe-btn-cancel" onclick="FE.cancel()">Cancel</button>
                <button class="fe-btn-save" id="feBtnSave" onclick="FE.save()">Save Details</button>
            </div>
        </div>

    </div><input type="file" id="feFileInput" accept="image/jpeg,image/png,image/webp" style="display:none;"
        onchange="FE.onFileChosen(event)">
</div>
<script>

const FE = (() => {
    'use strict';

    const API = 'API/face_enrolment_api.php';
    const MAX_SLOTS = 6;

    /* ── State ─────────────────────────────────────────────────── */
    let currentEmployee = null; // { id, name, designation, location, profile_photo }
    let slotData = []; // array[6]: { record_id, file_url } | null
    let activeSlot = -1; // which slot is waiting for file input
    let searchTimer = null;
    let acVisible = false;

    /* ── DOM ────────────────────────────────────────────────────── */
    const $ = id => document.getElementById(id);

    /* ── Init ───────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        initSlotData();
        document.addEventListener('click', e => {
            if (!e.target.closest('.fe-search-box')) closeAutocomplete();
        });
    });

    function initSlotData() {
        slotData = Array(MAX_SLOTS).fill(null);
    }

    /* ─────────────────────────────────────────────────────────────
       SEARCH
    ─────────────────────────────────────────────────────────────── */
    function onInput(val) {
        $('feClearBtn').style.display = val ? 'block' : 'none';
        clearTimeout(searchTimer);
        if (!val.trim()) {
            closeAutocomplete();
            return;
        }
        searchTimer = setTimeout(() => fetchAutocomplete(val.trim()), 250);
    }

    function fetchAutocomplete(q) {
        fetch(`${API}?action=search&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) showAutocomplete(res.data || []);
            })
            .catch(() => {});
    }

    function showAutocomplete(list) {
        const box = $('feAutocomplete');
        box.innerHTML = '';
        if (!list.length) {
            box.innerHTML = '<div class="fe-ac-empty">No employees found.</div>';
            box.style.display = 'block';
            acVisible = true;
            return;
        }
        list.forEach(emp => {
            const item = document.createElement('div');
            item.className = 'fe-ac-item';
            item.innerHTML = `
        ${avatarTag(emp, 'fe-ac-avatar')}
        <div>
          <div class="fe-ac-name">${esc(emp.name)} – #${esc(emp.employee_code)}</div>
          <div class="fe-ac-sub">${esc(emp.designation || '')}${emp.location ? ' • ' + esc(emp.location) : ''}</div>
        </div>`;
            item.addEventListener('click', () => selectEmployee(emp));
            box.appendChild(item);
        });
        box.style.display = 'block';
        acVisible = true;
    }

    function closeAutocomplete() {
        $('feAutocomplete').style.display = 'none';
        acVisible = false;
    }

    function doSearch() {
        const q = $('feSearchInput').value.trim();
        if (!q) return;
        closeAutocomplete();
        fetch(`${API}?action=search&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data.length) {
                    if (res.data.length === 1) {
                        selectEmployee(res.data[0]);
                    } else {
                        showAutocomplete(res.data);
                    }
                } else {
                    showToast('No employee found.', 'error');
                }
            })
            .catch(() => showToast('Network error.', 'error'));
    }

    function clearSearch() {
        $('feSearchInput').value = '';
        $('feClearBtn').style.display = 'none';
        closeAutocomplete();
        $('feSearchInput').focus();
    }

    /* ─────────────────────────────────────────────────────────────
       SELECT EMPLOYEE
    ─────────────────────────────────────────────────────────────── */
    function selectEmployee(emp) {
        currentEmployee = emp;
        closeAutocomplete();

        // Fill search box
        $('feSearchInput').value = `${emp.name} – ${emp.employee_code}`;
        $('feClearBtn').style.display = 'block';

        // Show employee card
        const avatarEl = $('feEmpAvatar');
        avatarEl.innerHTML = '';
        if (emp.profile_photo) {
            const img = document.createElement('img');
            img.src = emp.profile_photo;
            img.alt = emp.name;
            img.onerror = () => {
                avatarEl.innerHTML = initials(emp.name);
            };
            avatarEl.appendChild(img);
        } else {
            avatarEl.innerHTML = initials(emp.name);
        }
        $('feEmpName').textContent = `${emp.name} – #${emp.employee_code}`;
        $('feEmpMeta').textContent = [emp.designation, emp.location].filter(Boolean).join(' • ');

        $('feEmptyPrompt').style.display = 'none';
        $('feEmpCard').style.display = 'flex';

        // Reset slot data and render slots
        initSlotData();
        renderSlots();

        // Load existing face data
        loadExistingFaces(emp.id);

        // Show footer
        $('feFooter').style.display = 'flex';
    }

    /* ─────────────────────────────────────────────────────────────
       LOAD EXISTING FACES
    ─────────────────────────────────────────────────────────────── */
    function loadExistingFaces(empId) {
        fetch(`${API}?action=get_faces&employee_id=${empId}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data.length) {
                    res.data.forEach(row => {
                        const idx = parseInt(row.slot_index);
                        if (idx >= 0 && idx < MAX_SLOTS) {
                            slotData[idx] = {
                                record_id: row.id,
                                file_url: row.file_url
                            };
                        }
                    });
                    renderSlots();
                }
            })
            .catch(() => {});
    }

    /* ─────────────────────────────────────────────────────────────
       RENDER SLOTS
    ─────────────────────────────────────────────────────────────── */
    function renderSlots() {
        const grid = $('feSlotsGrid');
        grid.innerHTML = '';

        // Row 1: slots 0–3
        // Row 2: slots 4–5 (left-aligned, same size)
        // We use a wrapper with two rows:
        const row1 = document.createElement('div');
        row1.style.cssText = 'display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:16px;';
        const row2 = document.createElement('div');
        row2.style.cssText = 'display:grid;grid-template-columns:repeat(4,1fr);gap:16px;';

        for (let i = 0; i < MAX_SLOTS; i++) {
            const slot = buildSlot(i);
            if (i < 4) row1.appendChild(slot);
            else row2.appendChild(slot);
        }

        grid.appendChild(row1);
        grid.appendChild(row2);
        grid.style.display = 'block';
        $('feSlotsEmpty').style.display = 'none';
    }

    function buildSlot(index) {
        const data = slotData[index];
        const div = document.createElement('div');
        div.className = 'fe-slot' + (data ? ' has-image' : '');
        div.dataset.slot = index;

        if (data) {
            // Image present
            const img = document.createElement('img');
            img.src = data.file_url;
            img.className = 'fe-slot-img';
            img.alt = `Face ${index + 1}`;
            div.appendChild(img);

            const rmBtn = document.createElement('button');
            rmBtn.className = 'fe-slot-remove';
            rmBtn.title = 'Remove image';
            rmBtn.innerHTML = '✕';
            rmBtn.addEventListener('click', e => {
                e.stopPropagation();
                removeSlot(index, data.record_id);
            });
            div.appendChild(rmBtn);
        } else {
            const plus = document.createElement('span');
            plus.className = 'fe-slot-plus';
            plus.textContent = '+';
            div.appendChild(plus);
            div.addEventListener('click', () => openFilePicker(index));
        }
        return div;
    }

    /* ─────────────────────────────────────────────────────────────
       FILE UPLOAD
    ─────────────────────────────────────────────────────────────── */
    function openFilePicker(slotIndex) {
        if (!currentEmployee) return;
        activeSlot = slotIndex;
        const fi = $('feFileInput');
        fi.value = '';
        fi.click();
    }

    function onFileChosen(e) {
        const file = e.target.files[0];
        if (!file || activeSlot < 0) return;

        // Preview locally while uploading
        const reader = new FileReader();
        reader.onload = evt => {
            const tmpUrl = evt.target.result;
            showSlotLoading(activeSlot, tmpUrl);
            uploadFaceImage(activeSlot, file);
        };
        reader.readAsDataURL(file);
    }

    function showSlotLoading(index, previewUrl) {
        const slot = document.querySelector(`.fe-slot[data-slot="${index}"]`);
        if (!slot) return;
        slot.innerHTML = `
      <img src="${previewUrl}" class="fe-slot-img" alt="Uploading…" style="opacity:.4;">
      <div class="fe-slot-loading"><div class="fe-spinner"></div></div>`;
        slot.classList.add('has-image');
        slot.style.cursor = 'default';
    }

    function uploadFaceImage(slotIndex, file) {
        const fd = new FormData();
        fd.append('action', 'upload_face');
        fd.append('employee_id', currentEmployee.id);
        fd.append('slot_index', slotIndex);
        fd.append('face_image', file);

        fetch(API, {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    slotData[slotIndex] = {
                        record_id: res.record_id,
                        file_url: res.file_url
                    };
                    renderSlots();
                    showToast('Image uploaded.', 'success');
                } else {
                    slotData[slotIndex] = null;
                    renderSlots();
                    showToast(res.message || 'Upload failed.', 'error');
                }
            })
            .catch(() => {
                slotData[slotIndex] = null;
                renderSlots();
                showToast('Network error during upload.', 'error');
            });
    }

    /* ─────────────────────────────────────────────────────────────
       REMOVE SLOT
    ─────────────────────────────────────────────────────────────── */
    function removeSlot(index, recordId) {
        slotData[index] = null;
        renderSlots();

        if (!recordId) return;
        const fd = new FormData();
        fd.append('action', 'delete_face');
        fd.append('record_id', recordId);
        fetch(API, {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) showToast(res.message, 'error');
            })
            .catch(() => {});
    }

    /* ─────────────────────────────────────────────────────────────
       FOOTER ACTIONS
    ─────────────────────────────────────────────────────────────── */
    function save() {
        if (!currentEmployee) return;
        const hasAny = slotData.some(s => s !== null);
        if (!hasAny) {
            showToast('Please upload at least one face image.', 'error');
            return;
        }

        const btn = $('feBtnSave');
        btn.disabled = true;
        btn.textContent = 'Saving…';

        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('employee_id', currentEmployee.id);

        fetch(API, {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast(res.message, 'success');
                    setTimeout(() => resetAll(), 1200);
                } else {
                    showToast(res.message || 'Save failed.', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Save Details';
                }
            })
            .catch(() => {
                showToast('Network error.', 'error');
                btn.disabled = false;
                btn.textContent = 'Save Details';
            });
    }

    function reset() {
        if (!currentEmployee) return;
        if (!confirm('Clear all uploaded face images for this employee?')) return;

        const fd = new FormData();
        fd.append('action', 'reset_faces');
        fd.append('employee_id', currentEmployee.id);

        fetch(API, {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    initSlotData();
                    renderSlots();
                    showToast('All face images cleared.', 'success');
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(() => showToast('Network error.', 'error'));
    }

    function cancel() {
        resetAll();
    }

    function resetAll() {
        currentEmployee = null;
        initSlotData();
        activeSlot = -1;

        $('feSearchInput').value = '';
        $('feClearBtn').style.display = 'none';
        $('feEmptyPrompt').style.display = 'flex';
        $('feEmpCard').style.display = 'none';
        $('feSlotsGrid').style.display = 'none';
        $('feSlotsEmpty').style.display = 'block';
        $('feFooter').style.display = 'none';

        const btn = $('feBtnSave');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Save Details';
        }
    }

    /* ─────────────────────────────────────────────────────────────
       UTILITIES
    ─────────────────────────────────────────────────────────────── */
    function initials(name) {
        return (name || '?').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
    }

    function avatarTag(emp, cls) {
        if (emp.profile_photo) {
            return `<img src="${esc(emp.profile_photo)}" class="${cls}" alt="${esc(emp.name)}"
               onerror="this.outerHTML='<div class=\'${cls}\'>${initials(emp.name)}</div>'">`;
        }
        return `<div class="${cls}">${initials(emp.name)}</div>`;
    }

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    let toastTimer;

    function showToast(msg, type = '') {
        let t = document.querySelector('.fe-toast');
        if (!t) {
            t = document.createElement('div');
            t.className = 'fe-toast';
            document.body.appendChild(t);
        }
        t.className = `fe-toast ${type}`;
        t.textContent = msg;
        clearTimeout(toastTimer);
        requestAnimationFrame(() => {
            t.classList.add('show');
            toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
        });
    }

    /* ── Public ──────────────────────────────────────────────────── */
    return {
        onInput,
        doSearch,
        clearSearch,
        onFileChosen,
        save,
        reset,
        cancel
    };
})();
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>