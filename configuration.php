<?php
require_once 'includes/config.php';
$page_title = 'Configuration';

/* ─────────────────────────────────────────
   ACTIVE TAB  (hash-based via GET fallback)
───────────────────────────────────────── */
$valid_tabs = ['AccountInfo','Organization','Payroll','Attendance','Leave','Training','Others'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs)
    ? $_GET['tab']
    : 'AccountInfo';

/* ─────────────────────────────────────────
   PERMISSION CHECK  (mock — replace with real role check)
───────────────────────────────────────── */
$user_role      = 'Admin';   // e.g. from $_SESSION['role']
$restricted_tabs = ['Training'];   // tabs the current role cannot access

/* ─────────────────────────────────────────
   TAB DEFINITIONS
───────────────────────────────────────── */
$tabs = [
    'AccountInfo'  => 'Account Info',
    'Organization' => 'Organization',
    'Payroll'      => 'Payroll',
    'Attendance'   => 'Attendance',
    'Leave'        => 'Leave',
    'Training'     => 'Training',
    'Others'       => 'Others',
];

/* ─────────────────────────────────────────
   CONFIG CARDS DATA
───────────────────────────────────────── */
$config_data = [

    /* ── Account Info ── */
    'AccountInfo' => [
        'title' => 'Account Info Configuration',
        'sub'   => 'Set up business profile with locations, addresses, branding and other configurations.',
        'cards' => [
            [
                'icon'  => 'document',
                'title' => 'Account Information',
                'desc'  => "Set up your Company's Profile, Branding, Mail & Statutory configuration among others on to PERK.",
                'href'  => 'AccountInfo',
            ],
        ],
    ],

    /* ── Organization ── */
    'Organization' => [
        'title' => 'Organization Configuration',
        'sub'   => 'Set up business profile with locations, addresses, branding and other configurations.',
        'cards' => [
            ['icon'=>'building',     'title'=>'Organization',  'desc'=>"Enter the Organizations' Name(s) & Code.",                                          'href'=>'organization'],
            ['icon'=>'location',     'title'=>'Location',      'desc'=>'Add multiple locations & manage details.',                                           'href'=>'location'],
            ['icon'=>'department',   'title'=>'Department',    'desc'=>'Add Departments & manage details.',                                                   'href'=>'department'],
            ['icon'=>'designation',  'title'=>'Designation',   'desc'=>'Add and Manage Designations of your Organization.',                                   'href'=>'designation'],
            ['icon'=>'category',     'title'=>'Category',      'desc'=>"Categorise the employees of your Organisation like 'Management', 'Trainee', Etc.",   'href'=>'category'],
            ['icon'=>'group',        'title'=>'Group',         'desc'=>'Group Employees across multiple Departments, Categories or Designations.',             'href'=>'group'],
            ['icon'=>'subgroup',     'title'=>'Sub Group',     'desc'=>'Create & Manage Sub Groups.',                                                         'href'=>'subgroup'],
            ['icon'=>'calendar',     'title'=>'Calendar',      'desc'=>'Create custom office calendars.',                                                     'href'=>'calendar'],
        ],
    ],

    /* ── Payroll ── */
    'Payroll' => [
        'title' => 'Payroll Configuration',
        'sub'   => 'You can manage your company accounts info, activity, security options here.',
        'cards' => [
            ['icon'=>'template',    'title'=>'CTC Breakup Template',       'desc'=>'Create & manage Templates using Salary Components and Statutory options.',                    'href'=>'ctc-template'],
            ['icon'=>'assign',      'title'=>'Assign CTC and Templates',   'desc'=>'Add/Update the CTC of Employee(s). Also, assign them a suitable Template.',                  'href'=>'assign-ctc'],
            ['icon'=>'salary',      'title'=>'Salary Components',          'desc'=>'Create & Manage Components like Earnings, Deduction, Contribution, Etc. that appear in the CTC.','href'=>'salary-components'],
            ['icon'=>'salcat',      'title'=>'Salary Component Category',  'desc'=>'Statutory Configuration of the Salary components.',                                           'href'=>'salary-category'],
            ['icon'=>'variable',    'title'=>'Payroll Variables',          'desc'=>'Mathematical expression to calculate salary.',                                                 'href'=>'payroll-variables'],
            ['icon'=>'statutory',   'title'=>'Statutory Configuration',    'desc'=>'Configure ESI, EPF Percentages and Tax declaration details.',                                 'href'=>'statutory'],
            ['icon'=>'rounding',    'title'=>'Rounding Rule',              'desc'=>'Create rules to round off numbers.',                                                          'href'=>'rounding-rule'],
        ],
    ],

    /* ── Attendance ── */
    'Attendance' => [
        'title' => 'Attendance Configuration',
        'sub'   => 'You can manage your company accounts info, activity, security options here.',
        'cards' => [
            ['icon'=>'shift',       'title'=>'Shift Configuration',       'desc'=>'View, edit, add shifts & set their details like timings and working hours.',                   'href'=>'shift'],
            ['icon'=>'assignshift', 'title'=>'Assign Shift',              'desc'=>'Assign respective shifts to employees.',                                                       'href'=>'assign-shift'],
            ['icon'=>'autoshift',   'title'=>'Auto Shift Configuration',  'desc'=>'Auto assign shifts to Employees based on their Check In & Out.',                              'href'=>'auto-shift'],
            ['icon'=>'daystatus',   'title'=>'Assign Day Status',         'desc'=>'Manually assign status to employees with Present/ Absent for the day or half day.',           'href'=>'day-status'],
            ['icon'=>'device',      'title'=>'Devices',                   'desc'=>'View, Edit and Add Biometric Devices.',                                                        'href'=>'devices'],
            ['icon'=>'holiday',     'title'=>'Holidays List',             'desc'=>"Create a custom list of Holidays and add to your Organization's Calendar.",                   'href'=>'holidays'],
        ],
    ],

    /* ── Leave ── */
    'Leave' => [
        'title' => 'Leave Configuration',
        'sub'   => 'You can manage your company leave policies, types and approval workflows here.',
        'cards' => [
            ['icon'=>'leavetype',   'title'=>'Leave Type',         'desc'=>'Create and manage different types of leaves like Casual, Sick, Earned, etc.',             'href'=>'leave-type'],
            ['icon'=>'leavepol',    'title'=>'Leave Policy',       'desc'=>'Define leave policies, accrual rules, and carryover settings for your organization.',      'href'=>'leave-policy'],
            ['icon'=>'leaveassign', 'title'=>'Assign Leave Policy','desc'=>'Assign leave policies to employees based on their department, designation or category.',   'href'=>'assign-leave-policy'],
            ['icon'=>'leavebal',    'title'=>'Leave Balance',       'desc'=>'View and manage employee leave balances. Set opening balances for each leave type.',       'href'=>'leave-balance'],
            ['icon'=>'holiday',     'title'=>'Holiday Calendar',   'desc'=>'Map holidays to leave calendars for accurate leave day calculations.',                     'href'=>'holiday-calendar'],
        ],
    ],

    /* ── Training ── (access-restricted) */
    'Training' => [
        'title'      => 'Training Configuration',
        'sub'        => '',
        'restricted' => true,
        'cards'      => [],
    ],

    /* ── Others ── */
    'Others' => [
        'title' => 'Other Configuration',
        'sub'   => 'You can manage your company accounts info, activity, security options here.',
        'cards' => [
            ['icon'=>'news',        'title'=>'NEWS / Announcement',  'desc'=>'Share updates, NEWS and announcements across Organization.',                                        'href'=>'news'],
            ['icon'=>'approval',    'title'=>'Approval Rules',       'desc'=>'Define approval workflows for attendance, reimbursement, etc.',                                     'href'=>'approval-rules'],
            ['icon'=>'reimburse',   'title'=>'Reimbursement Policy', 'desc'=>'Create reimbursement policies with amount limits, required fields.',                                'href'=>'reimbursement'],
            ['icon'=>'teams',       'title'=>'Teams',                'desc'=>'Create and edit teams in your Organization.',                                                       'href'=>'teams'],
            ['icon'=>'fields',      'title'=>'Additional Fields',    'desc'=>'Add other required fields related to Employees.',                                                   'href'=>'additional-fields'],
            ['icon'=>'taxpro',      'title'=>'Tax Profiles*',        'desc'=>"Set up your Organisation's profile for ESI, PF, TDS and PT.",                                      'href'=>'tax-profiles'],
            ['icon'=>'apprego',     'title'=>'App Registration',     'desc'=>"Register mobile app on employees' mobiles or on attendance device and configure settings.",        'href'=>'app-registration'],
            ['icon'=>'face',        'title'=>'Face Enrolment',       'desc'=>'Upload images of Employees for facial recognition.',                                                'href'=>'face-enrolment'],
            ['icon'=>'links',       'title'=>'Quick Links',          'desc'=>'Add links to pages that your employees can quickly access from their Dashboard.',                   'href'=>'quick-links'],
            ['icon'=>'roles',       'title'=>'User Roles',           'desc'=>'Create custom roles and add permissions.',                                                          'href'=>'user-roles'],
            ['icon'=>'dataauth',    'title'=>'Data Authorisation',   'desc'=>'Set Restrictions on users access to data.',                                                         'href'=>'data-auth'],
            ['icon'=>'hrpolicy',    'title'=>'HR Policy',            'desc'=>'Select document and upload HR Policies, manage their active / inactive states.',                    'href'=>'hr-policy'],
        ],
    ],
];

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ════════════════════════════════════════
   CONFIGURATION PAGE STYLES
════════════════════════════════════════ */

/* ── Tab bar ── */
.cfg-tabs {
    display: flex;
    align-items: center;
    gap: 0;
    border-bottom: 1px solid #E5E7EB;
    background: #fff;
    padding: 0 0 0 0;
    margin-bottom: 0;
    overflow-x: auto;
    scrollbar-width: none;    
    /* flex-direction: row-reverse; */
    
}
.cfg-tabs::-webkit-scrollbar { display: none; }

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
    font-family: inherit;
    text-decoration: none;
    display: block;
    margin-bottom: -1px;
    /* flex-direction: row-reverse; */
}

.cfg-tab:hover { color: #111827; }

.cfg-tab.active {
    color: #2563EB;
    border-bottom-color: #2563EB;
    font-weight: 600;
}

/* ── Page wrapper card ── */
.cfg-card {
    background: #fff;
    border: 1px solid #E5E7EB;
    border-radius: 12px;
    margin-top: 18px;
    overflow: hidden;
}

.cfg-card-head {
    padding: 22px 28px 18px;
    border-bottom: 1px solid #F3F4F6;
}

.cfg-card-head h2 {
    font-size: 15.5px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 4px;
}

.cfg-card-head p {
    font-size: 13px;
    color: #6B7280;
    margin: 0;
    line-height: 1.5;
}

/* ── Config item grid ── */
.cfg-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
    padding: 8px 0;
}

/* ── Config item ── */
.cfg-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 22px 24px;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s;
    border-bottom: 1px solid #F9FAFB;
    border-right: 1px solid #F9FAFB;
    position: relative;
}

.cfg-item:hover {
    background: #F8F9FF;
}

.cfg-item:hover .cfg-item-title {
    color: #2563EB;
}

/* Remove right border on last in row */
.cfg-item:nth-child(4n) { border-right: none; }

/* ── Config icon ── */
.cfg-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background .15s;
}

.cfg-item:hover .cfg-icon {
    background: #EBF0FF;
}

.cfg-icon svg {
    width: 20px;
    height: 20px;
    stroke: #6B7280;
    fill: none;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: stroke .15s;
}

.cfg-item:hover .cfg-icon svg { stroke: #2563EB; }

.cfg-item-title {
    font-size: 13.5px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
    line-height: 1.3;
    transition: color .15s;
}

.cfg-item-desc {
    font-size: 12px;
    color: #9CA3AF;
    line-height: 1.55;
}

/* ── Restricted / access denied ── */
.cfg-restricted {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 40px;
    text-align: center;
}

.cfg-restricted-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 18px;
    opacity: .5;
}

.cfg-restricted-text {
    font-size: 13.5px;
    color: #9CA3AF;
}

/* ── Page header ── */
.cfg-page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
    flex-wrap: wrap;
    gap: 10px;
}

.cfg-page-head h1 {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
}

/* ── Tab content panels ── */
.cfg-panel { display: none; }
.cfg-panel.active { display: block; animation: cfgFadeIn .2s ease; }

@keyframes cfgFadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ── */
@media (max-width: 1100px) {
    .cfg-grid { grid-template-columns: repeat(3, 1fr); }
    .cfg-item:nth-child(4n)  { border-right: 1px solid #F9FAFB; }
    .cfg-item:nth-child(3n)  { border-right: none; }
}

@media (max-width: 768px) {
    .cfg-grid { grid-template-columns: repeat(2, 1fr); }
    .cfg-item:nth-child(3n)  { border-right: 1px solid #F9FAFB; }
    .cfg-item:nth-child(2n)  { border-right: none; }
    .cfg-tab { padding: 12px 14px; font-size: 12.5px; }
}

@media (max-width: 480px) {
    .cfg-grid { grid-template-columns: 1fr; }
    .cfg-item { border-right: none !important; }
    .cfg-item:nth-child(2n) { border-right: none; }
}
</style>

<!-- ── Page header ── -->
<div class="cfg-page-head">
    <h1 class="page-title">Configuration</h1>
</div>

<!-- ════════════════════════════════════════
     TAB BAR
════════════════════════════════════════ -->
<div class="section-card" style="padding:0;overflow:hidden">

    <div class="cfg-tabs" id="cfgTabs">
        <?php foreach ($tabs as $key => $label): ?>
        <a href="#"
           class="cfg-tab <?= $active_tab === $key ? 'active' : '' ?>"
           data-tab="<?= $key ?>"
           onclick="switchTab('<?= $key ?>');return false">
            <?= htmlspecialchars($label) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ════════════════════════════════════
         TAB PANELS
    ════════════════════════════════════ -->
    <?php foreach ($config_data as $tab_key => $tab): ?>
    <div class="cfg-panel <?= $active_tab === $tab_key ? 'active' : '' ?>" id="panel-<?= $tab_key ?>">

        <!-- Panel header -->
        <div class="cfg-card-head">
            <h2><?= htmlspecialchars($tab['title']) ?></h2>
            <?php if (!empty($tab['sub'])): ?>
            <p><?= htmlspecialchars($tab['sub']) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($tab['restricted'])): ?>
        <!-- ── ACCESS DENIED ── -->
        <div class="cfg-restricted">
            <!-- Inline SVG illustration matching the screenshot -->
            <svg class="cfg-restricted-icon" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="20" y="30" width="80" height="70" rx="6" fill="#E5E7EB"/>
                <rect x="28" y="22" width="64" height="10" rx="3" fill="#6B7280"/>
                <rect x="28" y="50" width="45" height="5" rx="2.5" fill="#D1D5DB"/>
                <rect x="28" y="62" width="38" height="5" rx="2.5" fill="#D1D5DB"/>
                <rect x="28" y="74" width="50" height="5" rx="2.5" fill="#D1D5DB"/>
            </svg>
            <p class="cfg-restricted-text">You don't have Access to this Page. Please contact Admin!</p>
        </div>

        <?php elseif (!empty($tab['cards'])): ?>
        <!-- ── CONFIG CARDS GRID ── -->
        <div class="cfg-grid">
            <?php foreach ($tab['cards'] as $card): ?>
            <a href="<?= htmlspecialchars($card['href']) ?>" class="cfg-item">
                <div class="cfg-icon">
                    <?= cfg_icon($card['icon']) ?>
                </div>
                <div>
                    <div class="cfg-item-title"><?= htmlspecialchars($card['title']) ?></div>
                    <div class="cfg-item-desc"><?= htmlspecialchars($card['desc']) ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <!-- Empty state -->
        <div class="cfg-restricted">
            <p class="cfg-restricted-text">No configuration options available.</p>
        </div>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>

</div><!-- end .section-card -->


<!-- ════════════════════════════════════════
     JAVASCRIPT — hash routing + tab switch
════════════════════════════════════════ -->
<script>
const VALID_TABS = <?= json_encode(array_keys($tabs)) ?>;

function switchTab(tabKey) {
    if (!VALID_TABS.includes(tabKey)) return;

    // Update tab buttons
    document.querySelectorAll('.cfg-tab').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.tab === tabKey);
    });

    // Update panels
    document.querySelectorAll('.cfg-panel').forEach(function(panel) {
        panel.classList.toggle('active', panel.id === 'panel-' + tabKey);
    });

    // Update URL hash without reload
    history.replaceState(null, '', '#' + tabKey);
}

/* ── Read hash on load ── */
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash.replace('#', '');
    if (hash && VALID_TABS.includes(hash)) {
        switchTab(hash);
    }
});

/* ── Popstate (back/forward) ── */
window.addEventListener('popstate', function() {
    const hash = window.location.hash.replace('#', '');
    if (hash && VALID_TABS.includes(hash)) {
        switchTab(hash);
    } else {
        switchTab('AccountInfo');
    }
});
</script>

<?php
/* ════════════════════════════════════════
   SVG ICON HELPER
   Returns an inline SVG for each icon key
════════════════════════════════════════ */
function cfg_icon(string $key): string {
    $icons = [

        /* document / account info */
        'document' => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',

        /* organization */
        'building' => '<svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'location' => '<svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        'department'=> '<svg viewBox="0 0 24 24"><rect x="2" y="3" width="6" height="6" rx="1"/><rect x="9" y="3" width="6" height="6" rx="1"/><rect x="16" y="3" width="6" height="6" rx="1"/><rect x="2" y="12" width="6" height="6" rx="1"/><rect x="16" y="12" width="6" height="6" rx="1"/><line x1="5" y1="9" x2="5" y2="12"/><line x1="19" y1="9" x2="19" y2="12"/><line x1="5" y1="12" x2="19" y2="12"/><line x1="12" y1="9" x2="12" y2="21"/><rect x="9" y="18" width="6" height="3" rx="1"/></svg>',
        'designation'=>'<svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
        'category'  => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>',
        'group'     => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'subgroup'  => '<svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
        'calendar'  => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',

        /* payroll */
        'template'  => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'assign'    => '<svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><polyline points="9 12 11 14 15 10"/></svg>',
        'salary'    => '<svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'salcat'    => '<svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
        'variable'  => '<svg viewBox="0 0 24 24"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><line x1="6" y1="9" x2="6" y2="21"/></svg>',
        'statutory' => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="12 18 12 12"/><polyline points="9 15 12 12 15 15"/></svg>',
        'rounding'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',

        /* attendance */
        'shift'       => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'assignshift' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/><polyline points="9 20.5 12 21.5 15 20.5"/></svg>',
        'autoshift'   => '<svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
        'daystatus'   => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="8 14 10 16 14 12"/></svg>',
        'device'      => '<svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
        'holiday'     => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>',

        /* leave */
        'leavetype'   => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'leavepol'    => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'leaveassign' => '<svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        'leavebal'    => '<svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',

        /* others */
        'news'      => '<svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
        'approval'  => '<svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        'reimburse' => '<svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        'teams'     => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'fields'    => '<svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
        'taxpro'    => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'apprego'   => '<svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
        'face'      => '<svg viewBox="0 0 24 24"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/><circle cx="12" cy="12" r="3"/></svg>',
        'links'     => '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        'roles'     => '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'dataauth'  => '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'hrpolicy'  => '<svg viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>',
    ];

    return $icons[$key] ?? $icons['document'];
}

$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>