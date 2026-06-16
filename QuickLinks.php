<?php
session_start();
if (!isset($_SESSION['login'])) {
    header('Location: login');
    exit();
}

require_once 'includes/db_client.php';
require_once 'includes/config.php';

$page_title = 'Quick Links';
ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">
<link rel="stylesheet" href="includes/assets/quick_links.css">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
    <h1 class="page-title">Configuration</h1>
</div>

<!-- Config Tab Bar -->
<div class="section-card" style="padding:0;overflow:hidden;">
  <div class="cfg-tabs">
    <?php foreach([
      'AccountInfo'=>'Account Info','Organization'=>'Organization',
      'Payroll'=>'Payroll','Attendance'=>'Attendance',
      'Leave'=>'Leave','Training'=>'Training','Others'=>'Others'
    ] as $k=>$l): ?>
    <a href="configuration#<?=$k?>" class="cfg-tab <?=$k==='Others'?'active':''?>"><?=$l?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="ql-wrapper">

  <!-- ── Page header ── -->
  <div class="ql-page-header">
    <span class="ql-page-title">Quick Links</span>
    <button class="ql-btn-add" onclick="QL.openForm()">
      <span>+</span> Add New
    </button>
  </div>

  <!-- ── Split column headers ── -->
  <div class="ql-col-headers">
    <div class="ql-col-label">Links</div>
    <div class="ql-col-label">Link Details</div>
  </div>

  <!-- ── Split body ── -->
  <div class="ql-body">

    <!-- LEFT: links list -->
    <div class="ql-left" id="qlLeft">
      <div class="ql-empty" id="qlLeftEmpty">
        <div class="ql-empty-art">
          <?= docIcon() ?>
        </div>
        <p class="ql-empty-text">No Quick Links!</p>
      </div>
      <div class="ql-list" id="qlList" style="display:none;"></div>
    </div>

    <!-- RIGHT: detail / form -->
    <div class="ql-right" id="qlRight">

      <!-- Empty state (default) -->
      <div class="ql-empty" id="qlRightEmpty">
        <div class="ql-empty-art">
          <?= docIconSm() ?>
        </div>
        <p class="ql-empty-text">No Quick Links!</p>
      </div>

      <!-- Add / Edit form -->
      <div class="ql-form" id="qlForm" style="display:none;">
        <div class="ql-form-field">
          <label class="ql-field-label">Display Name</label>
          <input type="text" class="ql-field-input" id="fDisplayName" maxlength="100" placeholder="">
        </div>
        <div class="ql-form-field">
          <label class="ql-field-label">Link</label>
          <input type="text" class="ql-field-input" id="fLink" maxlength="500" placeholder="">
        </div>
        <div class="ql-form-check">
          <label class="ql-check-label">
            <input type="checkbox" id="fVisibleAll" class="ql-checkbox">
            <span class="ql-check-box"></span>
            Visible to Everyone
          </label>
        </div>
        <input type="hidden" id="fEditingId" value="">
        <div class="ql-form-actions">
          <button class="ql-btn-cancel" onclick="QL.cancelForm()">Cancel</button>
          <button class="ql-btn-save"   id="qlBtnSave" onclick="QL.saveForm()">Save</button>
        </div>
      </div>

      <!-- Detail view -->
      <div class="ql-detail" id="qlDetail" style="display:none;">
        <div class="ql-detail-header">
          <h3 class="ql-detail-name" id="dName"></h3>
          <div class="ql-detail-actions">
            <button class="ql-icon-btn" title="Edit"   onclick="QL.editFromDetail()">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M9.5 1.5L12.5 4.5L5 12H2V9L9.5 1.5Z"
                      stroke="#2563EB" stroke-width="1.4" stroke-linejoin="round"/>
              </svg>
            </button>
            <button class="ql-icon-btn del" title="Delete" onclick="QL.deleteFromDetail()">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M2 4h10M5.5 4V2.5h3V4M6 4l.5 7.5h1L8 4"
                      stroke="#EF4444" stroke-width="1.4"
                      stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="ql-detail-row">
          <span class="ql-detail-label">Link</span>
          <a class="ql-detail-link" id="dLink" href="#" target="_blank" rel="noopener"></a>
        </div>
        <div class="ql-detail-row">
          <span class="ql-detail-label">Visible to Everyone</span>
          <span class="ql-detail-val" id="dVisible"></span>
        </div>
      </div>

    </div><!-- /.ql-right -->
  </div><!-- /.ql-body -->

</div><!-- /.ql-wrapper -->

<script src="includes/assets/quick_links.js"></script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>

<?php
function docIcon(): string {
    return <<<SVG
<svg width="90" height="90" viewBox="0 0 90 90" fill="none" xmlns="http://www.w3.org/2000/svg">
  <circle cx="45" cy="45" r="45" fill="#EEF2FF"/>
  <rect x="18" y="16" width="54" height="58" rx="5" fill="#CBD5E1"/>
  <rect x="18" y="16" width="54" height="16" rx="5" fill="#94A3B8"/>
  <rect x="27" y="40" width="28" height="4" rx="2" fill="#3B82F6"/>
  <rect x="27" y="50" width="22" height="4" rx="2" fill="#3B82F6"/>
  <rect x="27" y="60" width="16" height="4" rx="2" fill="#93C5FD"/>
  <rect x="27" y="21" width="10" height="6" rx="2" fill="#fff" opacity=".5"/>
</svg>
SVG;
}

function docIconSm(): string {
    return <<<SVG
<svg width="70" height="70" viewBox="0 0 70 70" fill="none" xmlns="http://www.w3.org/2000/svg">
  <circle cx="35" cy="35" r="35" fill="#EEF2FF"/>
  <rect x="14" y="12" width="42" height="46" rx="4" fill="#CBD5E1"/>
  <rect x="14" y="12" width="42" height="13" rx="4" fill="#94A3B8"/>
  <rect x="21" y="32" width="22" height="3" rx="1.5" fill="#3B82F6"/>
  <rect x="21" y="40" width="17" height="3" rx="1.5" fill="#3B82F6"/>
  <rect x="21" y="48" width="12" height="3" rx="1.5" fill="#93C5FD"/>
</svg>
SVG;
}
?>