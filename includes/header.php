<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $page_title ?? 'Dashboard' ?> — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php if (!empty($extra_head)) echo $extra_head; ?>
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content" id="mainContent">
        <header class="topbar">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;gap:12px">
                <div style="display:flex;align-items:center;gap:12px">
                    <button onclick="toggleSidebar()" class="btn" style="padding:7px 9px">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                    </button>
                    <span style="font-weight:600;font-size:15px;color:#1a1a2e"><?= APP_NAME ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <button style="position:relative;padding:7px 9px" class="btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                        </svg>
                        <span class="notif-dot"></span>
                    </button>


                    <!-- USER DROPDOWN -->
                    <div class="user-menu-wrap" id="userMenuWrap">
                        <div class="user-trigger" id="userTrigger" onclick="toggleUserDropdown(event)">
                            <div class="avatar">AD</div>
                            <span class="user-name">Admin</span>
                            <svg class="user-arrow" width="16" height="16" viewBox="0 0 20 20" fill="none">
                                <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>

                        <div class="user-dropdown" id="userDropdown">
                            <div class="user-dropdown-header">
                                <div class="avatar large">AD</div>
                                <div>
                                    <div class="dropdown-user-title">Admin</div>
                                    <div class="dropdown-user-sub">Administrator</div>
                                </div>
                            </div>

                            <div class="dropdown-divider"></div>

                            <button class="dropdown-item" onclick="openProfileModal()">
                                <i class="fa-solid fa-user dropdown-icon"></i>
                                <span>View Profile</span>
                            </button>

                            <button class="dropdown-item" onclick="openChangePasswordModal()">
                                <i class="fa-solid fa-lock dropdown-icon"></i>
                                <span>Change Password</span>
                            </button>

                            <button class="dropdown-item" onclick="openStatusModal()">
                                <i class="fa-solid fa-comment-dots dropdown-icon"></i>
                                <span>Set Status</span>
                            </button>

                            <button class="dropdown-item" onclick="openSettingsPage()">
                                <i class="fa-solid fa-gear dropdown-icon"></i>
                                <span>Settings</span>
                            </button>

                            <button class="dropdown-item" onclick="openHelpPage()">
                                <i class="fa-solid fa-circle-question dropdown-icon"></i>
                                <span>Help & Support</span>
                            </button>

                            <div class="dropdown-divider"></div>

                            <button class="dropdown-item danger" onclick="logoutUser()">
                                <i class="fa-solid fa-right-from-bracket dropdown-icon"></i>
                                <span>Logout</span>
                            </button>
                        </div>
                    </div>

                    <!-- STATUS MODAL -->
                    <div class="status-modal-overlay" id="statusModalOverlay">
                        <div class="status-modal">
                            <div class="status-modal-header">
                                <div>
                                    <h3>Set Status Message</h3>
                                    <p>Add Custom Message as Status</p>
                                </div>
                                <button class="status-close-btn" onclick="closeStatusModal()">✕</button>
                            </div>

                            <div class="status-modal-body">
                                <div class="status-input-wrap">
                                    <span class="status-input-icon">☺+</span>
                                    <input type="text" id="statusMessage" maxlength="50"
                                        placeholder="Update your status (50 characters max)">
                                </div>

                                <div class="status-field">
                                    <label>Clear Status After</label>
                                    <select id="clearStatusAfter">
                                        <option value="30 mins">30 mins</option>
                                        <option value="1 hour">1 hour</option>
                                        <option value="4 hours">4 hours</option>
                                        <option value="Today">Today</option>
                                        <option value="This week">This week</option>
                                        <option value="Don't clear">Don't clear</option>
                                    </select>
                                </div>
                            </div>

                            <div class="status-modal-footer">
                                <button class="btn-cancel" onclick="closeStatusModal()">Cancel</button>
                                <button class="btn-proceed" onclick="saveStatus()">Proceed</button>
                            </div>
                        </div>
                    </div>

                    <style>
                    :root {
                        --yellow: #FACC15;
                        --navy: #12132A;
                        --border: #E5E7EB;
                        --text: #374151;
                        --muted: #6B7280;
                        --bg: #ffffff;
                        --hover: #F9FAFB;
                        --danger: #EF4444;
                        --shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
                    }

                    .user-menu-wrap {
                        position: relative;
                        display: inline-block;
                    }

                    .user-trigger {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        padding: 6px 12px;
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        cursor: pointer;
                        background: #fff;
                        transition: all .2s ease;
                        user-select: none;
                    }

                    .dropdown-icon {
                        width: 18px;
                        font-size: 14px;
                        color: #6B7280;
                    }

                    .dropdown-item:hover .dropdown-icon {
                        color: #111827;
                    }

                    .dropdown-item.danger .dropdown-icon {
                        color: #EF4444;
                    }

                    .user-trigger:hover {
                        background: #f9fafb;
                        border-color: #d1d5db;
                    }

                    .avatar {
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        background: var(--yellow);
                        color: var(--navy);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 11px;
                        font-weight: 700;
                        flex-shrink: 0;
                    }

                    .avatar.large {
                        width: 42px;
                        height: 42px;
                        font-size: 13px;
                    }

                    .user-name {
                        font-size: 13px;
                        font-weight: 500;
                        color: var(--text);
                        white-space: nowrap;
                    }

                    .user-arrow {
                        color: #6B7280;
                        transition: transform .2s ease;
                    }

                    .user-menu-wrap.active .user-arrow {
                        transform: rotate(180deg);
                    }

                    .user-dropdown {
                        position: absolute;
                        top: calc(100% + 10px);
                        right: 0;
                        width: 240px;
                        background: #fff;
                        border: 1px solid var(--border);
                        border-radius: 14px;
                        box-shadow: var(--shadow);
                        padding: 8px;
                        display: none;
                        z-index: 999;
                    }

                    .user-menu-wrap.active .user-dropdown {
                        display: block;
                        animation: dropdownFade .18s ease;
                    }

                    @keyframes dropdownFade {
                        from {
                            opacity: 0;
                            transform: translateY(6px);
                        }

                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    .user-dropdown-header {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 8px;
                    }

                    .dropdown-user-title {
                        font-size: 14px;
                        font-weight: 700;
                        color: #111827;
                    }

                    .dropdown-user-sub {
                        font-size: 12px;
                        color: var(--muted);
                        margin-top: 2px;
                    }

                    .dropdown-divider {
                        height: 1px;
                        background: #EEF2F7;
                        margin: 6px 0;
                    }

                    .dropdown-item {
                        width: 100%;
                        border: none;
                        background: transparent;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 10px 12px;
                        border-radius: 10px;
                        cursor: pointer;
                        font-size: 13px;
                        color: #1F2937;
                        text-align: left;
                        transition: all .18s ease;
                    }

                    .dropdown-item:hover {
                        background: var(--hover);
                    }

                    .dropdown-item.danger {
                        color: var(--danger);
                    }

                    .dropdown-item.danger:hover {
                        background: #FEF2F2;
                    }

                    .dropdown-icon {
                        width: 18px;
                        text-align: center;
                        font-size: 15px;
                    }

                    /* STATUS MODAL */
                    .status-modal-overlay {
                        position: fixed;
                        inset: 0;
                        background: rgba(17, 24, 39, .35);
                        display: none;
                        align-items: center;
                        justify-content: center;
                        z-index: 2000;
                        padding: 16px;
                    }

                    .status-modal-overlay.show {
                        display: flex;
                    }

                    .status-modal {
                        width: 100%;
                        max-width: 480px;
                        background: #fff;
                        border-radius: 10px;
                        overflow: hidden;
                        box-shadow: 0 18px 45px rgba(0, 0, 0, .18);
                        animation: modalPop .2s ease;
                    }

                    @keyframes modalPop {
                        from {
                            opacity: 0;
                            transform: scale(.97);
                        }

                        to {
                            opacity: 1;
                            transform: scale(1);
                        }
                    }

                    .status-modal-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        padding: 16px 20px;
                        border-bottom: 1px solid #E5E7EB;
                    }

                    .status-modal-header h3 {
                        margin: 0;
                        font-size: 15px;
                        font-weight: 600;
                        color: #1F2937;
                    }

                    .status-modal-header p {
                        margin: 4px 0 0;
                        font-size: 12px;
                        color: #6B7280;
                    }

                    .status-close-btn {
                        border: none;
                        background: transparent;
                        color: #EF4444;
                        font-size: 20px;
                        cursor: pointer;
                        line-height: 1;
                    }

                    .status-modal-body {
                        padding: 18px 20px;
                    }

                    .status-input-wrap {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        border-bottom: 1px solid #E5E7EB;
                        padding-bottom: 10px;
                        margin-bottom: 18px;
                    }

                    .status-input-icon {
                        color: #6B7280;
                        font-size: 18px;
                        flex-shrink: 0;
                    }

                    .status-input-wrap input {
                        width: 100%;
                        border: none;
                        outline: none;
                        font-size: 14px;
                        color: #111827;
                        background: transparent;
                    }

                    .status-input-wrap input::placeholder {
                        color: #9CA3AF;
                    }

                    .status-field label {
                        display: block;
                        font-size: 13px;
                        color: #374151;
                        margin-bottom: 8px;
                        font-weight: 500;
                    }

                    .status-field select {
                        width: 100%;
                        border: none;
                        border-bottom: 1px solid #E5E7EB;
                        padding: 8px 0;
                        font-size: 14px;
                        color: #111827;
                        background: transparent;
                        outline: none;
                    }

                    .status-modal-footer {
                        display: flex;
                        justify-content: flex-end;
                        gap: 12px;
                        padding: 14px 20px 18px;
                    }

                    .btn-cancel,
                    .btn-proceed {
                        min-width: 100px;
                        padding: 10px 16px;
                        border-radius: 6px;
                        font-size: 14px;
                        cursor: pointer;
                        transition: .2s ease;
                    }

                    .btn-cancel {
                        background: #fff;
                        color: #EF4444;
                        border: 1px solid #EF4444;
                    }

                    .btn-cancel:hover {
                        background: #FEF2F2;
                    }

                    .btn-proceed {
                        background: #9CA3AF;
                        color: #fff;
                        border: 1px solid #9CA3AF;
                    }

                    .btn-proceed:hover {
                        opacity: .92;
                    }

                    @media (max-width:576px) {
                        .user-name {
                            display: none;
                        }

                        .user-dropdown {
                            width: 220px;
                            right: 0;
                        }

                        .status-modal {
                            max-width: 100%;
                        }
                    }
                    </style>

                    <script>
                    function toggleUserDropdown(event) {
                        event.stopPropagation();
                        const wrap = document.getElementById('userMenuWrap');
                        wrap.classList.toggle('active');
                    }

                    document.addEventListener('click', function(e) {
                        const wrap = document.getElementById('userMenuWrap');
                        if (wrap && !wrap.contains(e.target)) {
                            wrap.classList.remove('active');
                        }
                    });

                    function openStatusModal() {
                        document.getElementById('userMenuWrap').classList.remove('active');
                        document.getElementById('statusModalOverlay').classList.add('show');
                    }

                    function closeStatusModal() {
                        document.getElementById('statusModalOverlay').classList.remove('show');
                    }

                    function saveStatus() {
                        const status = document.getElementById('statusMessage').value.trim();
                        const clearAfter = document.getElementById('clearStatusAfter').value;

                        console.log("Status:", status);
                        console.log("Clear after:", clearAfter);

                        alert("Status saved successfully");
                        closeStatusModal();
                    }

                    function openProfileModal() {
                        document.getElementById('userMenuWrap').classList.remove('active');
                        window.location.href = 'profile';
                    }

                    function openChangePasswordModal() {
                        document.getElementById('userMenuWrap').classList.remove('active');
                        window.location.href = 'change_password';
                    }

                    function openSettingsPage() {
                        document.getElementById('userMenuWrap').classList.remove('active');
                        window.location.href = 'settings';
                    }

                    function openHelpPage() {
                        document.getElementById('userMenuWrap').classList.remove('active');
                        window.location.href = 'help';
                    }

                    function logoutUser() {
                        document.getElementById('userMenuWrap').classList.remove('active');
                        if (confirm('Are you sure you want to logout?')) {
                            window.location.href = 'logout';
                        }
                    }

                    document.getElementById('statusModalOverlay').addEventListener('click', function(e) {
                        if (e.target === this) {
                            closeStatusModal();
                        }
                    });
                    </script>


                </div>
            </div>
        </header>
        <main style="padding:10px">