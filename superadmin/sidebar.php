<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$nav_main = [
    ['href' => 'dashboard',      'key' => 'dashboard', 'label' => 'Dashboard'],
    ['href' => 'clients',        'key' => 'clients',   'label' => 'Clients'],
    ['href' => 'ClientDatabase', 'key' => 'database',  'label' => 'Client Databases'],
    ['href' => 'ModulesKeys',    'key' => 'keys',      'label' => 'Module Keys'],
    ['href' => 'LicenseKeys',    'key' => 'license',      'label' => 'License Keys'],
    ['href' => 'Billing',       'key' => 'billing',   'label' => 'Billing'],
];

$icons = [

    'dashboard' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',

    'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 11v6c0 1.7 4 3 9 3s9-1.3 9-3v-6"/>',

    'keys' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="M12 11l8-8"/><path d="M17 6l3 3"/><path d="M14 9l3 3"/>',

    'clients' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',

    'license' => '<rect x="3" y="3" width="18" height="14" rx="2"/><path d="M7 11h10M7 15h10M7 7h10"/>',

    'billing' => '<path d="M7 7h10"/><path d="M7 10h10"/><path d="M9 7v10"/><path d="M7 13h8"/><path d="M9 17c0 1.2 1.2 2 3 2s3-.8 3-2-1.2-2-3-2-3-.8-3-2 1.2-2 3-2 3 .8 3 2"/>',
];

function render_nav(array $items, string $currentPage, array $icons): void {
    foreach ($items as $item) {
        $active = ($currentPage === $item['href']) ? 'active' : '';
        $icon   = $icons[$item['key']] ?? '';

        echo "<a class='nav-item {$active}' href='{$item['href']}'>";
        echo "<svg width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>{$icon}</svg>";
        echo "<span>{$item['label']}</span>";
        echo "</a>";
    }
}
?>

<!-- SIDEBAR -->
<aside class="sidebar">

    <!-- LOGO -->
    <div style="padding:20px 16px">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;background:var(--yellow);border-radius:8px;display:flex;align-items:center;justify-content:center">
                ⭐
            </div>
            <div>
                <div style="color:#fff;font-weight:700;font-size:16px">Rhythm</div>
                <div style="color:#6B6F8E;font-size:10px;letter-spacing:1px">PAYROLL · HR</div>
            </div>
        </div>
    </div>

    <!-- MAIN NAV -->
    <nav style="padding:8px 0">

        <div style="color:#4B5280;font-size:10px;font-weight:700;letter-spacing:1px;padding:6px 24px">
            MAIN
        </div>

        <?php render_nav($nav_main, $currentPage, $icons); ?>

    </nav>

</aside>