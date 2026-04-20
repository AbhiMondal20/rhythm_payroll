<?php
require_once 'includes/config.php';
$page_title = 'Account Information';

/* ─────────────────────────────────────────
   MOCK DATA  (replace with real DB queries)
───────────────────────────────────────── */
$account = [
    /* Company Info */
    'company_name'    => 'Ramkrishna IVF Centre',
    'address1'        => 'Ramkrishna IVF Centre, Pakurtala More',
    'address2'        => '',
    'city'            => 'Siliguri',
    'state'           => 'West Bengal',
    'country'         => 'India',
    'pincode'         => '734001',
    'fax'             => '',
    'phone'           => '+91 93750 17xxx',
    'website'         => 'https://ramkrishnaivf.in',
    'logo'            => '',

    /* Statutory Info */
    'pan'             => 'AABCR1234F',
    'tan'             => 'CALC12345D',
    'gstin'           => '19AABCR1234F1Z5',
    'pf_no'           => 'WB/SLG/0012345',
    'esi_no'          => '31-00-123456-000-0001',
    'pt_no'           => 'WBPT123456',
    'lwf_no'          => '',
    'factory_no'      => '',
    'incorporation_no'=> '',
    'cin'             => '',

    /* Mail Configuration */
    'mail_from_name'  => 'Ramkrishna IVF Centre',
    'mail_from_email' => 'hr@ramkrishnaivf.in',
    'mail_host'       => 'smtp.gmail.com',
    'mail_port'       => '587',
    'mail_encryption' => 'TLS',
    'mail_username'   => 'hr@ramkrishnaivf.in',
    'mail_password'   => '',
    'mail_signature'  => '',

    /* Other Configuration */
    'date_format'     => 'DD/MM/YYYY',
    'time_format'     => '12 Hour',
    'currency'        => 'INR (₹)',
    'timezone'        => 'Asia/Kolkata',
    'week_start'      => 'Monday',
    'financial_year'  => 'April – March',
    'payroll_cycle'   => 'Monthly',
    'payslip_format'  => 'Standard',

    'subscription_expiry' => '30-04-2026',
];

/* ─────────────────────────────────────────
   POST — handle save per section
───────────────────────────────────────── */
$save_success = false;
$save_section = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['section'])) {
    // TODO: validate and save to DB
    $save_section = $_POST['section'];
    $save_success = true;
}

/* active accordion section (from URL or POST) */
$active_section = $_GET['section'] ?? ($save_section ?: 'company');

/* active edit section */
$edit_section = $_GET['edit'] ?? ($save_success ? '' : '');

function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function sel($v,$o) { return $v===$o?'selected':''; }

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<style>
/* ═══════════════════════════════════════
   ACCOUNT INFO PAGE
═══════════════════════════════════════ */

/* breadcrumb row */
.ai-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.ai-breadcrumb a        { color: #374151; text-decoration: none; }
.ai-breadcrumb a:hover  { color: #2563EB; }
.ai-breadcrumb .sep     { color: #D1D5DB; font-size: 15px; }
.ai-breadcrumb .current { color: #374151; font-weight: 600; }
.ai-breadcrumb .sub-exp {
    margin-left: 4px;
    font-size: 13px;
    font-weight: 600;
    color: #F59E0B;
}

/* two-col header labels */
.ai-col-labels {
    display: grid;
    grid-template-columns: 420px 1fr;
    gap: 0;
    padding: 10px 0 12px;
    border-bottom: 1px solid #E5E7EB;
    margin-bottom: 20px;
}
.ai-col-label {
    font-size: 12.5px;
    font-weight: 600;
    color: #6B7280;
    padding-left: 4px;
}

/* main two-column layout */
.ai-layout {
    display: grid;
    grid-template-columns: 420px 1fr;
    gap: 0;
    align-items: start;
    min-height: 420px;
}

/* ── LEFT ACCORDION ── */
.ai-accordion {
    border-right: 1px solid #E5E7EB;
    padding-right: 0;
}

.ai-acc-item { border-bottom: 1px solid #E5E7EB; }
.ai-acc-item:last-child { border-bottom: none; }

.ai-acc-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 16px 0;
    background: none;
    border: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    text-align: left;
    transition: color .15s;
}

.ai-acc-btn:hover { color: #2563EB; }

.ai-acc-btn.active {
    color: #2563EB;
    font-weight: 600;
}

.ai-acc-arrow {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1.5px solid #D1D5DB;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: border-color .15s, transform .2s;
}

.ai-acc-btn.active .ai-acc-arrow {
    border-color: #2563EB;
}

.ai-acc-arrow svg {
    width: 10px;
    height: 10px;
    stroke: #9CA3AF;
    transition: stroke .15s, transform .2s;
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.ai-acc-btn.active .ai-acc-arrow svg { stroke: #2563EB; }
/* rotate arrow for active */
.ai-acc-btn.active .ai-acc-arrow { transform: none; }

/* ── RIGHT DETAIL PANEL ── */
.ai-detail {
    padding: 0 0 0 32px;
}

.ai-detail-panel { display: none; }
.ai-detail-panel.active {
    display: block;
    animation: aiSlideIn .2s ease;
}

@keyframes aiSlideIn {
    from { opacity:0; transform:translateY(4px); }
    to   { opacity:1; transform:translateY(0); }
}

/* detail header */
.ai-detail-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 8px;
}

.ai-detail-head h3 {
    font-size: 13px;
    font-weight: 700;
    color: #111827;
    letter-spacing: .6px;
    text-transform: uppercase;
}

/* edit details link */
.ai-edit-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 500;
    color: #2563EB;
    cursor: pointer;
    border: none;
    background: none;
    font-family: inherit;
    padding: 0;
    text-decoration: none;
    transition: color .15s;
}
.ai-edit-link:hover { color: #1D4ED8; }
.ai-edit-link svg { width: 13px; height: 13px; stroke: currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }

/* field grid  */
.ai-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}

.ai-field {
    padding: 0 24px 22px 0;
}

.ai-field-label {
    font-size: 12px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
}

/* view mode: underline value */
.ai-field-val {
    font-size: 13.5px;
    color: #111827;
    font-weight: 400;
    padding-bottom: 7px;
    border-bottom: 1px solid #D1D5DB;
    min-height: 32px;
    line-height: 1.4;
    word-break: break-word;
}

.ai-field-val.empty { color: #D1D5DB; }

/* edit mode inputs */
.ai-field input,
.ai-field select,
.ai-field textarea {
    width: 100%;
    padding: 8px 10px;
    border: none;
    border-bottom: 1.5px solid #D1D5DB;
    border-radius: 0;
    font-family: inherit;
    font-size: 13.5px;
    color: #111827;
    outline: none;
    background: transparent;
    transition: border-color .15s;
}

.ai-field input:focus,
.ai-field select:focus,
.ai-field textarea:focus {
    border-bottom-color: #2563EB;
}

/* full-width field */
.ai-field.full { grid-column: 1 / -1; }

/* section divider label inside right panel */
.ai-subsect {
    grid-column: 1 / -1;
    font-size: 11px;
    font-weight: 700;
    color: #9CA3AF;
    letter-spacing: .6px;
    text-transform: uppercase;
    padding: 16px 0 10px;
    border-bottom: 1px solid #F3F4F6;
    margin-bottom: 4px;
}

/* action bar (save/cancel) */
.ai-action-bar {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 14px;
    border-top: 1px solid #E5E7EB;
    margin-top: 8px;
}

/* ── toast ── */
.ai-toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: #111827;
    color: #fff;
    padding: 11px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    z-index: 999;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 8px 28px rgba(0,0,0,.2);
    transition: transform .3s ease;
    white-space: nowrap;
}
.ai-toast.show { transform: translateX(-50%) translateY(0); }

/* responsive */
@media (max-width: 900px) {
    .ai-layout,
    .ai-col-labels { grid-template-columns: 1fr; }
    .ai-accordion  { border-right: none; border-bottom: 1px solid #E5E7EB; padding-bottom: 0; }
    .ai-detail     { padding: 20px 0 0; }
}
@media (max-width: 600px) {
    .ai-fields { grid-template-columns: 1fr; }
    .ai-field.full { grid-column: 1; }
}
</style>

<?php if ($save_success): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showAiToast('✅', '<?= esc(ucfirst($save_section)) ?> information saved successfully!');
});
</script>
<?php endif; ?>

<!-- ── Breadcrumb ── -->
<div class="ai-breadcrumb">
    <a href="configuration#AccountInfo">Account Info</a>
    <span class="sep">›</span>
    <span class="current">Account Information</span>
    <span class="sub-exp">
        Subscription Expires on <?= esc($account['subscription_expiry']) ?>
    </span>
</div>

<!-- ── Column labels ── -->
<div class="section-card" style="padding:0">

    <div style="padding:0 24px">
        <div class="ai-col-labels">
            <div class="ai-col-label">Configuration</div>
            <div class="ai-col-label">Configuration Details</div>
        </div>
    </div>

    <div style="padding:0 24px 28px">
    <div class="ai-layout">

        <!-- ══════════════════════════════
             LEFT ACCORDION
        ══════════════════════════════ -->
        <div class="ai-accordion">

            <?php
            $sections = [
                'company'   => 'Company info',
                'statutory' => 'Statutory Info',
                'mail'      => 'Mail Configuration',
                'other'     => 'Other Configuration',
            ];
            foreach ($sections as $skey => $slabel):
                $is_active = ($active_section === $skey);
            ?>
            <div class="ai-acc-item">
                <button class="ai-acc-btn <?= $is_active ? 'active' : '' ?>"
                    onclick="switchSection('<?= $skey ?>')" type="button">
                    <?= esc($slabel) ?>
                    <div class="ai-acc-arrow">
                        <?php if ($is_active): ?>
                        <svg viewBox="0 0 12 12"><polyline points="2 8 6 4 10 8"/></svg>
                        <?php else: ?>
                        <svg viewBox="0 0 12 12"><polyline points="2 4 6 8 10 4"/></svg>
                        <?php endif; ?>
                    </div>
                </button>
            </div>
            <?php endforeach; ?>

        </div><!-- end accordion -->

        <!-- ══════════════════════════════
             RIGHT DETAIL PANELS
        ══════════════════════════════ -->
        <div class="ai-detail" id="aiDetail">

            <!-- ─────────────────────────
                 COMPANY INFO
            ───────────────────────────── -->
            <div class="ai-detail-panel <?= $active_section==='company' ? 'active' : '' ?>" id="panel-company">

                <div class="ai-detail-head">
                    <h3>COMPANY INFO</h3>
                    <?php if ($edit_section !== 'company'): ?>
                    <a class="ai-edit-link" onclick="startEdit('company')" href="#">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Details
                    </a>
                    <?php endif; ?>
                </div>

                <form method="POST" id="form-company" novalidate>
                <input type="hidden" name="section" value="company">

                <div class="ai-fields">

                    <?php
                    $company_fields = [
                        ['company_name', 'Company Name',  false, 'text'],
                        ['address1',     'Address 1',     false, 'text'],
                        ['address2',     'Address 2',     false, 'text'],
                        ['city',         'City',          false, 'text'],
                        ['state',        'State',         false, 'text'],
                        ['country',      'Country',       false, 'text'],
                        ['pincode',      'Pin Code',      false, 'text'],
                        ['fax',          'Fax',           false, 'text'],
                        ['phone',        'Phone Number',  false, 'tel'],
                        ['website',      'Website',       false, 'url'],
                    ];

                    foreach ($company_fields as [$fkey, $flabel, $full, $ftype]):
                        $fval   = $account[$fkey] ?? '';
                        $is_edit_mode = ($edit_section === 'company');
                    ?>
                    <div class="ai-field <?= $full ? 'full' : '' ?>">
                        <div class="ai-field-label"><?= esc($flabel) ?></div>
                        <?php if ($is_edit_mode): ?>
                        <input type="<?= $ftype ?>" name="<?= $fkey ?>" value="<?= esc($fval) ?>"
                            placeholder="<?= esc($flabel) ?>">
                        <?php else: ?>
                        <div class="ai-field-val <?= $fval==='' ? 'empty' : '' ?>">
                            <?= $fval !== '' ? esc($fval) : '—' ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Logo upload -->
                    <div class="ai-field">
                        <div class="ai-field-label">Company Logo</div>
                        <?php if ($edit_section === 'company'): ?>
                        <input type="file" name="logo" accept="image/*" style="padding-top:4px">
                        <?php else: ?>
                        <div class="ai-field-val empty">
                            <?= $account['logo'] ? '<img src="'.esc($account['logo']).'" style="height:32px">' : '—' ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($edit_section === 'company'): ?>
                    <div class="ai-action-bar">
                        <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                    <?php endif; ?>

                </div>
                </form>
            </div>

            <!-- ─────────────────────────
                 STATUTORY INFO
            ───────────────────────────── -->
            <div class="ai-detail-panel <?= $active_section==='statutory' ? 'active' : '' ?>" id="panel-statutory">

                <div class="ai-detail-head">
                    <h3>STATUTORY INFO</h3>
                    <?php if ($edit_section !== 'statutory'): ?>
                    <a class="ai-edit-link" onclick="startEdit('statutory')" href="#">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Details
                    </a>
                    <?php endif; ?>
                </div>

                <form method="POST" id="form-statutory" novalidate>
                <input type="hidden" name="section" value="statutory">

                <div class="ai-fields">
                    <?php
                    $stat_fields = [
                        ['pan',              'PAN Number',                false, 'text', 'AABC D 1234 E'],
                        ['tan',              'TAN Number',                false, 'text', 'CALC12345D'],
                        ['gstin',            'GSTIN',                     false, 'text', '22AAAAA0000A1Z5'],
                        ['pf_no',            'PF Registration Number',    false, 'text', 'WB/XXX/0012345'],
                        ['esi_no',           'ESI Registration Number',   false, 'text', '31-00-XXXXXX'],
                        ['pt_no',            'PT Registration Number',    false, 'text', 'WBPTXXXXXX'],
                        ['lwf_no',           'LWF Number',                false, 'text', ''],
                        ['factory_no',       'Factory Registration No.',  false, 'text', ''],
                        ['incorporation_no', 'Incorporation Number',      false, 'text', ''],
                        ['cin',              'CIN',                       false, 'text', 'U85110WB2010PTC000001'],
                    ];
                    foreach ($stat_fields as [$fkey, $flabel, $full, $ftype, $ph]):
                        $fval = $account[$fkey] ?? '';
                        $is_em = ($edit_section === 'statutory');
                    ?>
                    <div class="ai-field <?= $full ? 'full':'' ?>">
                        <div class="ai-field-label"><?= esc($flabel) ?></div>
                        <?php if ($is_em): ?>
                        <input type="<?= $ftype ?>" name="<?= $fkey ?>" value="<?= esc($fval) ?>" placeholder="<?= esc($ph) ?>">
                        <?php else: ?>
                        <div class="ai-field-val <?= $fval===''?'empty':'' ?>"><?= $fval!==''?esc($fval):'—' ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($edit_section === 'statutory'): ?>
                    <div class="ai-action-bar">
                        <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                    <?php endif; ?>
                </div>
                </form>
            </div>

            <!-- ─────────────────────────
                 MAIL CONFIGURATION
            ───────────────────────────── -->
            <div class="ai-detail-panel <?= $active_section==='mail' ? 'active' : '' ?>" id="panel-mail">

                <div class="ai-detail-head">
                    <h3>MAIL CONFIGURATION</h3>
                    <?php if ($edit_section !== 'mail'): ?>
                    <a class="ai-edit-link" onclick="startEdit('mail')" href="#">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Details
                    </a>
                    <?php endif; ?>
                </div>

                <form method="POST" id="form-mail" novalidate>
                <input type="hidden" name="section" value="mail">

                <div class="ai-fields">
                    <?php
                    $mail_fields = [
                        ['mail_from_name',  'From Name',       false, 'text',     ''],
                        ['mail_from_email', 'From Email',      false, 'email',    ''],
                        ['mail_host',       'SMTP Host',       false, 'text',     'smtp.gmail.com'],
                        ['mail_port',       'SMTP Port',       false, 'number',   '587'],
                        ['mail_encryption', 'Encryption',      false, 'text',     'TLS / SSL'],
                        ['mail_username',   'SMTP Username',   false, 'email',    ''],
                        ['mail_password',   'SMTP Password',   false, 'password', ''],
                    ];
                    foreach ($mail_fields as [$fkey,$flabel,$full,$ftype,$ph]):
                        $fval = $fkey==='mail_password' ? '' : ($account[$fkey]??'');
                        $is_em = ($edit_section==='mail');
                    ?>
                    <div class="ai-field <?= $full?'full':'' ?>">
                        <div class="ai-field-label"><?= esc($flabel) ?></div>
                        <?php if ($is_em): ?>
                        <input type="<?= $ftype ?>" name="<?= $fkey ?>" value="<?= esc($fval) ?>" placeholder="<?= esc($ph) ?>">
                        <?php else: ?>
                        <div class="ai-field-val <?= $fval===''?'empty':'' ?>">
                            <?= $fkey==='mail_password'&&$fval!=='' ? '••••••••' : ($fval!==''?esc($fval):'—') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Email Signature -->
                    <div class="ai-field full">
                        <div class="ai-field-label">Email Signature</div>
                        <?php if ($edit_section==='mail'): ?>
                        <textarea name="mail_signature" rows="3" placeholder="Optional HTML or plain-text signature..."><?= esc($account['mail_signature']) ?></textarea>
                        <?php else: ?>
                        <div class="ai-field-val <?= $account['mail_signature']===''?'empty':'' ?>">
                            <?= $account['mail_signature']!=='' ? nl2br(esc($account['mail_signature'])) : '—' ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($edit_section==='mail'): ?>
                    <div class="ai-action-bar">
                        <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                        <button type="button" class="btn" onclick="testMail()" style="background:#EFF6FF;color:#2563EB;border-color:#BFDBFE">Test Connection</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                    <?php endif; ?>
                </div>
                </form>
            </div>

            <!-- ─────────────────────────
                 OTHER CONFIGURATION
            ───────────────────────────── -->
            <div class="ai-detail-panel <?= $active_section==='other' ? 'active' : '' ?>" id="panel-other">

                <div class="ai-detail-head">
                    <h3>OTHER CONFIGURATION</h3>
                    <?php if ($edit_section !== 'other'): ?>
                    <a class="ai-edit-link" onclick="startEdit('other')" href="#">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Details
                    </a>
                    <?php endif; ?>
                </div>

                <form method="POST" id="form-other" novalidate>
                <input type="hidden" name="section" value="other">

                <div class="ai-fields">
                    <?php
                    $is_em   = ($edit_section==='other');
                    $opt_dft = ['DD/MM/YYYY','MM/DD/YYYY','YYYY-MM-DD','DD MMM YYYY'];
                    $opt_tf  = ['12 Hour','24 Hour'];
                    $opt_tz  = ['Asia/Kolkata','UTC','Asia/Dubai'];
                    $opt_ws  = ['Monday','Sunday'];
                    $opt_fy  = ['April – March','January – December','October – September'];
                    $opt_pc  = ['Monthly','Weekly','Bi-Weekly','Fortnightly'];
                    $opt_pf  = ['Standard','Detailed','Simple'];
                    $select_fields = [
                        ['date_format',   'Date Format',       $opt_dft],
                        ['time_format',   'Time Format',       $opt_tf ],
                        ['currency',      'Currency',          ['INR (₹)','USD ($)','EUR (€)']],
                        ['timezone',      'Timezone',          $opt_tz ],
                        ['week_start',    'Week Starts On',    $opt_ws ],
                        ['financial_year','Financial Year',    $opt_fy ],
                        ['payroll_cycle', 'Payroll Cycle',     $opt_pc ],
                        ['payslip_format','Payslip Format',    $opt_pf ],
                    ];
                    foreach ($select_fields as [$fkey,$flabel,$fopts]):
                        $fval = $account[$fkey]??'';
                    ?>
                    <div class="ai-field">
                        <div class="ai-field-label"><?= esc($flabel) ?></div>
                        <?php if ($is_em): ?>
                        <select name="<?= $fkey ?>">
                            <?php foreach ($fopts as $fo): ?>
                            <option <?= sel($fval,$fo) ?>><?= esc($fo) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <div class="ai-field-val <?= $fval===''?'empty':'' ?>"><?= $fval!==''?esc($fval):'—' ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($is_em): ?>
                    <div class="ai-action-bar">
                        <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                    <?php endif; ?>
                </div>
                </form>
            </div>

        </div><!-- end ai-detail -->
    </div><!-- end ai-layout -->
    </div>

</div><!-- end section-card -->


<!-- toast -->
<div class="ai-toast" id="aiToast">
    <span id="aiToastIcon">✅</span>
    <span id="aiToastMsg">Saved!</span>
</div>

<script>
/* ── Section switch ── */
function switchSection(key) {
    /* update accordion buttons */
    document.querySelectorAll('.ai-acc-btn').forEach(function(btn) {
        const active = btn.getAttribute('onclick').includes("'" + key + "'");
        btn.classList.toggle('active', active);
        /* flip arrow */
        const svg = btn.querySelector('.ai-acc-arrow svg');
        if (svg) {
            svg.querySelector('polyline').setAttribute('points',
                active ? '2 8 6 4 10 8' : '2 4 6 8 10 4');
        }
    });

    /* show panel */
    document.querySelectorAll('.ai-detail-panel').forEach(function(p) {
        p.classList.toggle('active', p.id === 'panel-' + key);
    });

    /* cancel any in-progress edit */
    cancelEdit(true);

    /* push to URL without reload */
    const url = new URL(window.location.href);
    url.searchParams.set('section', key);
    url.searchParams.delete('edit');
    history.replaceState(null, '', url.toString());
}

/* ── Edit mode ── */
let currentEditSection = null;

function startEdit(section) {
    currentEditSection = section;

    const panel = document.getElementById('panel-' + section);
    if (!panel) return;

    /* Hide all "Edit Details" links */
    panel.querySelectorAll('.ai-edit-link').forEach(function(el){ el.style.display='none'; });

    /* Convert all view values to inputs in this panel */
    panel.querySelectorAll('.ai-field').forEach(function(field) {
        const valDiv = field.querySelector('.ai-field-val');
        if (!valDiv) return;

        const name  = field.dataset.fname || '';
        const ftype = field.dataset.ftype || 'text';
        const val   = valDiv.dataset.val  || (valDiv.textContent.trim() === '—' ? '' : valDiv.textContent.trim());

        if (ftype === 'select') {
            /* handled server-side; ignore */
            return;
        }

        const input = document.createElement('input');
        input.type  = ftype;
        input.name  = name;
        input.value = val;
        input.className = '';
        input.style.cssText = 'width:100%;padding:8px 10px;border:none;border-bottom:1.5px solid #D1D5DB;font-family:inherit;font-size:13.5px;color:#111827;outline:none;background:transparent;transition:border-color .15s';
        input.addEventListener('focus', function(){ this.style.borderBottomColor='#2563EB'; });
        input.addEventListener('blur',  function(){ this.style.borderBottomColor='#D1D5DB'; });

        valDiv.replaceWith(input);
    });

    /* Show action bar */
    const bar = panel.querySelector('.ai-action-bar-js');
    if (bar) bar.style.display = 'flex';

    /* scroll panel into view */
    panel.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

function cancelEdit(silent) {
    /* Re-load the page to restore view state cleanly */
    if (!silent && currentEditSection) {
        const url = new URL(window.location.href);
        url.searchParams.delete('edit');
        window.location.href = url.toString();
    }
    currentEditSection = null;
}

function testMail() {
    showAiToast('⏳', 'Sending test email...');
    setTimeout(function(){ showAiToast('✅','Test email sent successfully!'); }, 1800);
}

/* ── Toast ── */
function showAiToast(icon, msg) {
    const t  = document.getElementById('aiToast');
    const ti = document.getElementById('aiToastIcon');
    const tm = document.getElementById('aiToastMsg');
    ti.textContent = icon;
    tm.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(function(){ t.classList.remove('show'); }, 3200);
}

/* ── Read URL params on load ── */
document.addEventListener('DOMContentLoaded', function() {
    const params  = new URLSearchParams(window.location.search);
    const section = params.get('section');
    const edit    = params.get('edit');

    if (section) switchSection(section);
    if (edit && ['company','statutory','mail','other'].includes(edit)) {
        startEdit(edit);
    }
});
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>
<script src="includes/assets/scripts.js"></script>