<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Top-Level Menu Items
$menu_items = [
    [
        'title' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'href' => 'dashboard.php',
        'active' => $current_page === 'dashboard.php'
    ],
    [
        'title' => 'Profile',
        'icon' => 'fas fa-user-circle',
        'href' => 'profile.php',
        'active' => $current_page === 'profile.php'
    ],
    [
        'title' => 'User Management',
        'icon' => 'fas fa-users-cog',
        'href' => 'manage_users.php',
        'active' => in_array($current_page, ['manage_users.php', 'manage_roles.php'])
    ],
    [
        'title' => 'Categories & Roles',
        'icon' => 'fas fa-university',
        'href' => 'manage_categories.php',
        'active' => $current_page === 'manage_categories.php'
    ],
    [
        'title' => 'Institutions',
        'icon' => 'fas fa-school',
        'href' => 'manage_institutions.php',
        'active' => $current_page === 'manage_institutions.php'
    ]
];

// Learner Records Submenu
$learner_records_submenu = [
    [
        'title' => 'View Records',
        'icon' => 'fas fa-table',
        'href' => 'manage_records.php',
        'active' => $current_page === 'manage_records.php'
    ],
    [
        'title' => 'Upload Manually',
        'icon' => 'fas fa-keyboard',
        'href' => 'upload_manual.php',
        'active' => $current_page === 'upload_manual.php'
    ],
    [
        'title' => 'Bulk Upload (CSV)',
        'icon' => 'fas fa-file-upload',
        'href' => 'upload_bulk.php',
        'active' => $current_page === 'upload_bulk.php'
    ]
];

$submenu_active = array_filter($learner_records_submenu, fn($item) => $item['active']);

// Additional menu items
$additional_menu_items = [
    [
        'title' => 'System Reports',
        'icon' => 'fas fa-chart-line',
        'href' => 'reports.php',
        'active' => $current_page === 'reports.php'
    ],
    [
        'title' => 'Notifications',
        'icon' => 'fas fa-bell',
        'href' => 'notifications.php',
        'active' => $current_page === 'notifications.php'
    ],
    [
        'title' => 'Audit Logs',
        'icon' => 'fas fa-file-alt',
        'href' => 'audit_logs.php',
        'active' => $current_page === 'audit_logs.php'
    ],
    [
        'title' => 'System Settings',
        'icon' => 'fas fa-tools',
        'href' => 'system_settings.php',
        'active' => $current_page === 'system_settings.php'
    ],
    [
        'title' => 'Help & Support',
        'icon' => 'fas fa-question-circle',
        'href' => 'help.php',
        'active' => $current_page === 'help.php'
    ]
];
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-graduation-cap"></i> LARS Admin</h2>
    </div>

    <ul class="sidebar-menu">
        <?php foreach ($menu_items as $item): ?>
            <li>
                <a href="<?= $item['href'] ?>" class="<?= $item['active'] ? 'active' : '' ?>">
                    <i class="<?= $item['icon'] ?>"></i> <?= $item['title'] ?>
                </a>
            </li>
        <?php endforeach; ?>

        <!-- Learner Records Submenu -->
        <li class="submenu <?= !empty($submenu_active) ? 'open' : '' ?>">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)">
                <i class="fas fa-clipboard-list"></i> Learner Records
                <i class="fas fa-angle-down submenu-toggle-icon"></i>
            </a>
            <ul class="submenu-items">
                <?php foreach ($learner_records_submenu as $subitem): ?>
                    <li>
                        <a href="<?= $subitem['href'] ?>" class="<?= $subitem['active'] ? 'active' : '' ?>">
                            <i class="<?= $subitem['icon'] ?>"></i> <?= $subitem['title'] ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </li>

        <?php foreach ($additional_menu_items as $item): ?>
            <li>
                <a href="<?= $item['href'] ?>" class="<?= $item['active'] ? 'active' : '' ?>">
                    <i class="<?= $item['icon'] ?>"></i> <?= $item['title'] ?>
                </a>
            </li>
        <?php endforeach; ?>

        <!-- Logout -->
        <li>
            <a href="../../../logout.php">
                <i class="fas fa-power-off"></i> Logout
            </a>
        </li>
    </ul>
</aside>

<script>
    function toggleSubmenu(element) {
        const submenu = element.parentElement;
        const isOpen = submenu.classList.contains('open');

        document.querySelectorAll('.submenu.open').forEach(menu => {
            if (menu !== submenu) menu.classList.remove('open');
        });

        submenu.classList.toggle('open');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const activeSubmenuItem = document.querySelector('.submenu-items .active');
        if (activeSubmenuItem) {
            const parentSubmenu = activeSubmenuItem.closest('.submenu');
            if (parentSubmenu) parentSubmenu.classList.add('open');
        }
    });
</script>
