<?php
function getNotificationCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch()['count'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}
$notificationCount = 0;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $notificationCount = getNotificationCount($pdo, $_SESSION['user_id']);
}
?>

<nav class="admin-navbar">
    <div class="navbar-left">
        <button class="mobile-menu-toggle" style="display: none;">
            <i class="fas fa-bars"></i>
        </button>
<!--        <span class="navbar-subtitle">Zambia Qualifications Authority</span>-->
    </div>

    <div class="navbar-right">
        <ul class="navbar-icons">
            <li class="notification-item">
<!--                <a href="notifications.php" title="Notifications" class="notification-link">-->
<!--                    <i class="fas fa-bell"></i>-->
<!--                    --><?php //if ($notificationCount > 0): ?>
<!--                        <span class="notification-badge">--><?php //= $notificationCount > 99 ? '99+' : $notificationCount ?><!--</span>-->
<!--                    --><?php //endif; ?>
<!--                </a>-->
            </li>
<!--            <li>-->
<!--                <a href="settings.php" title="Settings">-->
<!--                    <i class="fas fa-cog"></i>-->
<!--                </a>-->
<!--            </li>-->
            <li>
                <a href="help.php" title="Help & Support">
                    <i class="fas fa-question-circle"></i>
                </a>
            </li>
        </ul>
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-details">
                <span class="user-name">
                    <?php if (isset($_SESSION['user_email'])): ?>
                        <?= htmlspecialchars($_SESSION['user_email']) ?>
                    <?php else: ?>
                        admin@zaqa.gov.zm
                    <?php endif; ?>
                </span>
                <span class="user-role">
                    <?php if (isset($_SESSION['user_role'])): ?>
                        <?= ucfirst(htmlspecialchars($_SESSION['user_role'])) ?>
                    <?php else: ?>
                        Administrator
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_category'])): ?>
                        • <?= htmlspecialchars($_SESSION['user_category']) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="user-dropdown">
            <button class="dropdown-toggle" title="User Menu">
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    My Profile
                </a>
                <a href="change-password.php" class="dropdown-item">
                    <i class="fas fa-key"></i>
                    Change Password
                </a>
                <a href="activity-log.php" class="dropdown-item">
                    <i class="fas fa-history"></i>
                    Activity Log
                </a>
                <div class="dropdown-divider"></div>
                <a href="../../../../logout" class="dropdown-item logout-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>
<!-- Enhanced JavaScript functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle functionality
        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.querySelector('.mobile-menu-toggle');

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-open');

                // Add overlay for mobile
                let overlay = document.querySelector('.sidebar-overlay');
                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.className = 'sidebar-overlay';
                    overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 999;
                    display: none;
                `;
                    document.body.appendChild(overlay);

                    overlay.addEventListener('click', function() {
                        sidebar.classList.remove('mobile-open');
                        overlay.style.display = 'none';
                    });
                }

                if (sidebar.classList.contains('mobile-open')) {
                    overlay.style.display = 'block';
                } else {
                    overlay.style.display = 'none';
                }
            });
        }

        // Dropdown menu functionality (alternative to CSS hover for better mobile support)
        const dropdownToggle = document.querySelector('.dropdown-toggle');
        const dropdownMenu = document.querySelector('.dropdown-menu');

        if (dropdownToggle && dropdownMenu) {
            dropdownToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.style.opacity = dropdownMenu.style.opacity === '1' ? '0' : '1';
                dropdownMenu.style.visibility = dropdownMenu.style.visibility === 'visible' ? 'hidden' : 'visible';
                dropdownMenu.style.transform = dropdownMenu.style.transform === 'translateY(0px)' ? 'translateY(-10px)' : 'translateY(0px)';
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                dropdownMenu.style.opacity = '0';
                dropdownMenu.style.visibility = 'hidden';
                dropdownMenu.style.transform = 'translateY(-10px)';
            });
        }

        // Real-time notification updates (optional)
        function updateNotifications() {
            fetch('api/get-notifications-count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.notification-badge');
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                        } else {
                            // Create badge if it doesn't exist
                            const notificationLink = document.querySelector('.notification-link');
                            const newBadge = document.createElement('span');
                            newBadge.className = 'notification-badge';
                            newBadge.textContent = data.count > 99 ? '99+' : data.count;
                            notificationLink.appendChild(newBadge);
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(error => console.log('Notification update failed:', error));
        }

        // Update notifications every 30 seconds
        setInterval(updateNotifications, 30000);
    });
</script>