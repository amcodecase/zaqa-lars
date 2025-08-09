<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Top-Level Menu Items for Staff
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
        'href' => 'myprofile.php',
        'active' => $current_page === 'profile.php'
    ]
];

// Learner Records (View only for TEVETA, HEA, ECZ)
$learner_records_submenu = [
    [
        'title' => 'TEVETA Records',
        'icon' => 'fas fa-table',
        'href' => 'view_teveta_records.php?source=teveta',
        'active' => ($current_page === 'manage_records.php' && isset($_GET['source']) && $_GET['source'] === 'teveta')
    ],
    [
        'title' => 'HEA Records',
        'icon' => 'fas fa-table',
        'href' => 'view_he_record.php?source=hea',
        'active' => ($current_page === 'manage_records.php' && isset($_GET['source']) && $_GET['source'] === 'hea')
    ],
    [
        'title' => 'ECZ Records',
        'icon' => 'fas fa-table',
        'href' => 'view_ecz_record.php?source=ecz',
        'active' => ($current_page === 'manage_records.php' && isset($_GET['source']) && $_GET['source'] === 'ecz')
    ]
];

$submenu_active = array_filter($learner_records_submenu, fn($item) => $item['active']);

// Updated menu item for reports specific to processed learner records
$additional_menu_items = [
//    [
//        'title' => 'Generate Report',
//        'icon' => 'fas fa-file-export',
//        'href' => 'record_reports.php',
//        'active' => $current_page === 'record_reports.php'
//    ],
    [
        'title' => 'Notifications',
        'icon' => 'fas fa-bell',
        'href' => 'notifications.php',
        'active' => $current_page === 'notifications.php'
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
        <h2><i class="fas fa-graduation-cap"></i>Leaner Records</h2>
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