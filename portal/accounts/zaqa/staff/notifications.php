<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit;
}
require '../../../../dbconnect.php';

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

function getCurrentUser($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, c.name as category_name, r.name as role_name 
            FROM users u 
            JOIN category c ON u.category_id = c.id 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        return false;
    }
}

function getNotifications($pdo, $userId, $limit = 50) {
    try {
        $stmt = $pdo->prepare("
            SELECT n.*, 
                   sender.first_name as sender_first_name,
                   sender.last_name as sender_last_name,
                   sender.email as sender_email
            FROM notifications n 
            LEFT JOIN users sender ON n.sender_id = sender.id 
            WHERE n.recipient_id = ? 
            ORDER BY n.sent_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}

function getNotificationStats($pdo, $userId) {
    $stats = [];

    try {
        // Total notifications
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE recipient_id = ?");
        $stmt->execute([$userId]);
        $stats['total'] = (int)$stmt->fetchColumn();

        // Notifications by priority
        $stmt = $pdo->prepare("
            SELECT priority, COUNT(*) as count 
            FROM notifications 
            WHERE recipient_id = ? 
            GROUP BY priority
        ");
        $stmt->execute([$userId]);
        $priorityStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats['high'] = 0;
        $stats['medium'] = 0;
        $stats['low'] = 0;

        foreach ($priorityStats as $priority) {
            $stats[$priority['priority']] = (int)$priority['count'];
        }

        // Recent notifications (last 7 days)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as recent 
            FROM notifications 
            WHERE recipient_id = ? AND sent_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$userId]);
        $stats['recent'] = (int)$stmt->fetchColumn();

        // Today's notifications
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as today 
            FROM notifications 
            WHERE recipient_id = ? AND DATE(sent_at) = CURDATE()
        ");
        $stmt->execute([$userId]);
        $stats['today'] = (int)$stmt->fetchColumn();

    } catch (PDOException $e) {
        error_log("Error fetching notification stats: " . $e->getMessage());
        $stats = [
            'total' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'recent' => 0,
            'today' => 0
        ];
    }

    return $stats;
}

function markAsRead($pdo, $notificationId, $userId) {
    try {
        // Note: You might want to add a 'read' column to the notifications table
        // For now, we'll just return success
        return true;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        $success = markAsRead($pdo, $notificationId, $_SESSION['user_id']);
        echo json_encode(['success' => $success]);
        exit;
    }
}

// Fetch data
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);
$notifications = getNotifications($pdo, $_SESSION['user_id']);
$notificationStats = getNotificationStats($pdo, $_SESSION['user_id']);

// Handle AJAX requests
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'stats' => $notificationStats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - LARS Staff</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/staff/notifications.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../../assets/images/zaqa-logo.png">

</head>
<body>

<?php include 'generic/navbar.php'; ?>
<div class="dashboard-container">
    <?php include 'generic/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="notifications-container">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-bell"></i> Notifications</h2>
                <button class="btn btn-primary" id="refreshNotifications">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid" id="notificationStats">
                <div class="stat-card total-notifications">
                    <h3><span class="stat-number" data-target="<?php echo $notificationStats['total']; ?>">0</span></h3>
                    <p><i class="fas fa-bell"></i> Total Notifications</p>
                </div>

                <div class="stat-card high-priority">
                    <h3><span class="stat-number" data-target="<?php echo $notificationStats['high']; ?>">0</span></h3>
                    <p><i class="fas fa-exclamation-triangle"></i> High Priority</p>
                </div>

                <div class="stat-card recent-notifications">
                    <h3><span class="stat-number" data-target="<?php echo $notificationStats['recent']; ?>">0</span></h3>
                    <p><i class="fas fa-clock"></i> This Week</p>
                </div>

                <div class="stat-card today-notifications">
                    <h3><span class="stat-number" data-target="<?php echo $notificationStats['today']; ?>">0</span></h3>
                    <p><i class="fas fa-calendar-day"></i> Today</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">All</button>
                <button class="filter-tab" data-filter="high">High Priority</button>
                <button class="filter-tab" data-filter="medium">Medium Priority</button>
                <button class="filter-tab" data-filter="low">Low Priority</button>
            </div>

            <!-- Notifications List -->
            <div class="notifications-list" id="notificationsList">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item priority-<?php echo htmlspecialchars($notification['priority']); ?>"
                             data-priority="<?php echo htmlspecialchars($notification['priority']); ?>"
                             data-notification-id="<?php echo $notification['id']; ?>">

                            <div class="notification-header">
                                <div class="notification-sender">
                                    <?php if ($notification['sender_first_name']): ?>
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($notification['sender_first_name'] . ' ' . $notification['sender_last_name']); ?>
                                    <?php else: ?>
                                        <i class="fas fa-cogs"></i> System
                                    <?php endif; ?>
                                </div>

                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="notification-priority priority-<?php echo htmlspecialchars($notification['priority']); ?>">
                                        <?php echo htmlspecialchars($notification['priority']); ?>
                                    </span>
                                    <span class="notification-time">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['sent_at'])); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No notifications</h3>
                        <p>You don't have any notifications at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Refresh Indicator -->
<div class="refresh-indicator" id="refreshIndicator">
    <i class="fas fa-sync-alt fa-spin"></i> Refreshing notifications...
</div>

<!-- Last Updated Timestamp -->
<div class="last-updated" id="lastUpdated">
    Last updated: <span id="updateTime"><?php echo date('H:i:s'); ?></span>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animate stat numbers
        function animateNumbers() {
            const statNumbers = document.querySelectorAll('.stat-number');

            statNumbers.forEach(function(element) {
                const target = parseInt(element.getAttribute('data-target'));
                const increment = Math.max(target / 50, 1);
                let current = 0;

                const timer = setInterval(function() {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    element.textContent = Math.floor(current);
                }, 30);
            });
        }

        // Filter notifications
        function filterNotifications(priority) {
            const notifications = document.querySelectorAll('.notification-item');

            notifications.forEach(function(notification) {
                if (priority === 'all' || notification.getAttribute('data-priority') === priority) {
                    notification.style.display = 'block';
                } else {
                    notification.style.display = 'none';
                }
            });
        }

        // Auto-refresh functionality
        function refreshNotifications() {
            const refreshIndicator = document.getElementById('refreshIndicator');
            const updateTime = document.getElementById('updateTime');

            if (refreshIndicator) {
                refreshIndicator.style.display = 'block';
            }

            fetch(window.location.href + '?ajax=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationsData(data);
                        if (updateTime) {
                            updateTime.textContent = new Date().toLocaleTimeString();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing notifications:', error);
                })
                .finally(() => {
                    if (refreshIndicator) {
                        refreshIndicator.style.display = 'none';
                    }
                });
        }

        // Update notifications data
        function updateNotificationsData(data) {
            // Update stats
            const stats = data.stats;
            document.querySelector('[data-target]').setAttribute('data-target', stats.total);

            // Re-animate numbers
            animateNumbers();

            console.log('Notifications refreshed at:', data.timestamp);
        }

        // Mark notification as read
        function markAsRead(notificationId) {
            fetch(window.location.href + '?ajax=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_read&notification_id=' + notificationId
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Notification marked as read');
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
        }

        // Event listeners

        // Filter tabs
        const filterTabs = document.querySelectorAll('.filter-tab');
        filterTabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                filterTabs.forEach(t => t.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');

                // Filter notifications
                const filter = this.getAttribute('data-filter');
                filterNotifications(filter);
            });
        });

        // Notification click handlers
        const notifications = document.querySelectorAll('.notification-item');
        notifications.forEach(function(notification) {
            notification.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-notification-id');
                markAsRead(notificationId);
            });
        });

        // Refresh button
        const refreshButton = document.getElementById('refreshNotifications');
        if (refreshButton) {
            refreshButton.addEventListener('click', refreshNotifications);
        }

        // Initialize
        animateNumbers();

        // Auto-refresh every 2 minutes
        setInterval(refreshNotifications, 120000);
    });
</script>

</body>
</html>