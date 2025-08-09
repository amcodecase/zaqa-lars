<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit;
}
require '../../../../dbconnect.php';

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle AJAX actions
if ($isAjax && !empty($action)) {
    header('Content-Type: application/json');

    switch ($action) {
        case 'send_notification':
            $recipientId = (int)($_POST['recipient_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $validPriorities = ['low', 'medium', 'high'];

            if ($recipientId && !empty($message) && in_array($priority, $validPriorities)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO notifications (sender_id, recipient_id, message, priority) VALUES (?, ?, ?, ?)");
                    $result = $stmt->execute([$_SESSION['user_id'], $recipientId, $message, $priority]);

                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Notification sent successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to send notification']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error sending notification: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'send_bulk_notification':
            $userIds = json_decode($_POST['user_ids'] ?? '[]', true);
            $message = trim($_POST['message'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            $validPriorities = ['low', 'medium', 'high'];

            if (!empty($userIds) && !empty($message) && in_array($priority, $validPriorities)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO notifications (sender_id, recipient_id, message, priority) VALUES (?, ?, ?, ?)");
                    $successCount = 0;

                    foreach ($userIds as $userId) {
                        if ($stmt->execute([$_SESSION['user_id'], (int)$userId, $message, $priority])) {
                            $successCount++;
                        }
                    }

                    echo json_encode(['success' => true, 'message' => "Sent notifications to $successCount users successfully"]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error sending bulk notifications: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'get_sent_notifications':
            $page = (int)($_GET['page'] ?? 1);
            $limit = 20;
            $offset = ($page - 1) * $limit;

            try {
                // Get total count
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE sender_id = ?");
                $countStmt->execute([$_SESSION['user_id']]);
                $totalNotifications = $countStmt->fetchColumn();

                // Get paginated results
                $stmt = $pdo->prepare("
                    SELECT n.*, 
                           u.first_name, u.last_name, u.email,
                           c.name as category_name, r.name as role_name
                    FROM notifications n
                    JOIN users u ON n.recipient_id = u.id
                    JOIN category c ON u.category_id = c.id
                    JOIN roles r ON u.role_id = r.id
                    WHERE n.sender_id = ?
                    ORDER BY n.sent_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$_SESSION['user_id'], $limit, $offset]);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'notifications' => $notifications,
                    'total' => $totalNotifications,
                    'page' => $page,
                    'pages' => ceil($totalNotifications / $limit)
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching notifications: ' . $e->getMessage()]);
            }
            exit;

        case 'delete_notification':
            $notificationId = (int)($_POST['notification_id'] ?? 0);

            if ($notificationId) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND sender_id = ?");
                    $result = $stmt->execute([$notificationId, $_SESSION['user_id']]);
                    echo json_encode(['success' => $result, 'message' => 'Notification deleted successfully']);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error deleting notification: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            exit;

        case 'get_users_for_notification':
            $categoryId = (int)($_GET['category_id'] ?? 0);
            $roleId = (int)($_GET['role_id'] ?? 0);
            $status = $_GET['status'] ?? '';

            try {
                $where = ["u.id != ?"];
                $params = [$_SESSION['user_id']]; // Don't include current admin

                if ($categoryId) {
                    $where[] = "u.category_id = ?";
                    $params[] = $categoryId;
                }

                if ($roleId) {
                    $where[] = "u.role_id = ?";
                    $params[] = $roleId;
                }

                if ($status) {
                    $where[] = "u.status = ?";
                    $params[] = $status;
                }

                $whereClause = "WHERE " . implode(" AND ", $where);

                $stmt = $pdo->prepare("
                    SELECT u.id, u.first_name, u.last_name, u.email, u.status,
                           c.name as category_name, r.name as role_name
                    FROM users u
                    JOIN category c ON u.category_id = c.id
                    JOIN roles r ON u.role_id = r.id
                    $whereClause
                    ORDER BY u.first_name, u.last_name
                ");
                $stmt->execute($params);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'users' => $users]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching users: ' . $e->getMessage()]);
            }
            exit;

        case 'get_notification_stats':
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_sent,
                        COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority,
                        COUNT(CASE WHEN priority = 'medium' THEN 1 END) as medium_priority,
                        COUNT(CASE WHEN priority = 'low' THEN 1 END) as low_priority,
                        COUNT(CASE WHEN DATE(sent_at) = CURDATE() THEN 1 END) as sent_today
                    FROM notifications 
                    WHERE sender_id = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'stats' => $stats]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching stats: ' . $e->getMessage()]);
            }
            exit;
    }
}

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

function getUsers($pdo, $filters = []) {
    try {
        $where = ["u.id != ?"];
        $params = [$_SESSION['user_id']]; // Exclude current admin

        // Build WHERE conditions
        if (!empty($filters['status'])) {
            $where[] = "u.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['category_id'])) {
            $where[] = "u.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['role_id'])) {
            $where[] = "u.role_id = ?";
            $params[] = $filters['role_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM users u $whereClause";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $totalUsers = $stmt->fetchColumn();

        // Get paginated results
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT u.*, c.name as category_name, r.name as role_name,
                   (SELECT COUNT(*) FROM notifications n WHERE n.recipient_id = u.id AND n.sender_id = ?) as notification_count
            FROM users u 
            JOIN category c ON u.category_id = c.id 
            JOIN roles r ON u.role_id = r.id 
            $whereClause
            ORDER BY u.first_name, u.last_name
            LIMIT ? OFFSET ?
        ";

        $params[] = $_SESSION['user_id'];
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'users' => $users,
            'total' => $totalUsers,
            'page' => $page,
            'pages' => ceil($totalUsers / $limit)
        ];
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return ['users' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
    }
}

function getCategories($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM category ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        return [];
    }
}

function getRoles($pdo, $categoryId = null) {
    try {
        if ($categoryId) {
            $stmt = $pdo->prepare("SELECT * FROM roles WHERE category_id = ? ORDER BY name");
            $stmt->execute([$categoryId]);
        } else {
            $stmt = $pdo->query("SELECT r.*, c.name as category_name FROM roles r JOIN category c ON r.category_id = c.id ORDER BY c.name, r.name");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching roles: " . $e->getMessage());
        return [];
    }
}

function getNotificationStats($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sent,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority,
                COUNT(CASE WHEN priority = 'medium' THEN 1 END) as medium_priority,
                COUNT(CASE WHEN priority = 'low' THEN 1 END) as low_priority,
                COUNT(CASE WHEN DATE(sent_at) = CURDATE() THEN 1 END) as sent_today,
                COUNT(CASE WHEN sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as sent_this_week
            FROM notifications 
            WHERE sender_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching notification stats: " . $e->getMessage());
        return [];
    }
}

// Get data
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);
$categories = getCategories($pdo);
$allRoles = getRoles($pdo);
$notificationStats = getNotificationStats($pdo, $_SESSION['user_id']);

// Get filters from request
$filters = [
    'status' => $_GET['status'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'role_id' => $_GET['role_id'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$userData = getUsers($pdo, $filters);

// Handle AJAX request for user data
if ($isAjax && $action === 'get_users') {
    echo json_encode($userData);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Management - LARS Admin Dashboard</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/admin/notifications.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../../assets/images/icon.png">
</head>

<body>

<?php include 'generic/navbar.php'; ?>

<div class="dashboard-container">
    <?php include 'generic/sidebar.php'; ?>

    <main class="dashboard-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1><i class="fas fa-bell"></i> Notification Management</h1>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Notification Stats -->
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-bar"></i> Notification Statistics</h3>
            <div class="content-grid">
                <div class="stat-card">
                    <h4>Total Sent</h4>
                    <p class="stat-number"><?php echo number_format($notificationStats['total_sent'] ?? 0); ?></p>
                </div>
                <div class="stat-card">
                    <h4>Sent Today</h4>
                    <p class="stat-number"><?php echo number_format($notificationStats['sent_today'] ?? 0); ?></p>
                </div>
                <div class="stat-card">
                    <h4>This Week</h4>
                    <p class="stat-number"><?php echo number_format($notificationStats['sent_this_week'] ?? 0); ?></p>
                </div>
                <div class="stat-card">
                    <h4>High Priority</h4>
                    <p class="stat-number"><?php echo number_format($notificationStats['high_priority'] ?? 0); ?></p>
                </div>
            </div>
        </div>

        <!-- Send New Notification -->
        <div class="dashboard-card">
            <h3><i class="fas fa-paper-plane"></i> Send New Notification</h3>
            <form id="notificationForm">
                <div class="content-grid">
                    <div class="dashboard-card">
                        <label for="recipient_type">Send To</label>
                        <select id="recipient_type" class="form-control">
                            <option value="individual">Individual User</option>
                            <option value="bulk">Multiple Users</option>
                            <option value="category">All Users in Category</option>
                            <option value="role">All Users with Role</option>
                            <option value="all">All Users</option>
                        </select>
                    </div>

                    <div class="dashboard-card">
                        <label for="priority">Priority</label>
                        <select id="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>

                <div id="recipientSelector" class="content-grid">
                    <div class="dashboard-card">
                        <label for="recipient_id">Select Recipient</label>
                        <select id="recipient_id" class="form-control">
                            <option value="">Choose a user...</option>
                            <?php foreach ($userData['users'] as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="dashboard-card">
                    <label for="message">Message</label>
                    <textarea id="message" class="form-control" rows="4" placeholder="Enter your notification message here..." required></textarea>
                    <small class="form-text">Maximum 1000 characters</small>
                </div>

                <div class="dashboard-card">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                    <button type="button" id="previewBtn" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                </div>
            </form>
        </div>

        <!-- Filters for Recipients -->
        <div class="dashboard-card">
            <h3><i class="fas fa-filter"></i> Filter Recipients</h3>
            <form id="filterForm" method="GET">
                <div class="content-grid">
                    <div class="dashboard-card">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>

                    <div class="dashboard-card">
                        <label for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $filters['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dashboard-card">
                        <label for="role_id">Role</label>
                        <select name="role_id" id="role_id" class="form-control">
                            <option value="">All Roles</option>
                            <?php foreach ($allRoles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo $filters['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                    <?php if (isset($role['category_name'])): ?>
                                        (<?php echo htmlspecialchars($role['category_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dashboard-card">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Name or email..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                </div>

                <div class="content-grid">
                    <div class="dashboard-card">
                        <br>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Selection -->
        <div class="dashboard-card">
            <h3><i class="fas fa-tasks"></i> Bulk Notification</h3>
            <div class="content-grid">
                <div class="stat-card">
                    <button type="button" id="sendBulkNotification" class="btn btn-warning">
                        <i class="fas fa-bell"></i> Send to Selected
                    </button>
                    <span id="selectedCount">0 users selected</span>
                </div>
            </div>
        </div>

        <!-- Recipients Table -->
        <div class="dashboard-card">
            <h3>
                <i class="fas fa-users"></i> Recipients
                <span class="badge"><?php echo number_format($userData['total']); ?> users</span>
            </h3>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Category</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Notifications Sent</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody id="usersTableBody">
                    <?php foreach ($userData['users'] as $user): ?>
                        <tr class="user-row" data-user-id="<?php echo $user['id']; ?>">
                            <td><input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>"></td>
                            <td>
                                <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                <?php if ($user['other_names']): ?>
                                    <br><small><?php echo htmlspecialchars($user['other_names']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge"><?php echo $user['notification_count']; ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="sendQuickNotification(<?php echo $user['id']; ?>)">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($userData['pages'] > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($userData['page'] > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $userData['page'] - 1])); ?>">&laquo; Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $userData['pages']; $i++): ?>
                            <?php if ($i == $userData['page']): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($userData['page'] < $userData['pages']): ?>
                            <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $userData['page'] + 1])); ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sent Notifications History -->
        <div class="dashboard-card">
            <h3><i class="fas fa-history"></i> Sent Notifications History</h3>
            <div id="sentNotificationsContainer">
                <p>Loading sent notifications...</p>
            </div>
        </div>
    </main>
</div>

<!-- Quick Notification Modal -->
<div id="quickNotificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="quickModalTitle">Send Quick Notification</h3>
            <span class="close" onclick="closeQuickModal()">&times;</span>
        </div>
        <div id="quickModalBody">
            <form id="quickNotificationForm">
                <input type="hidden" id="quickRecipientId">
                <div class="dashboard-card">
                    <label for="quickPriority">Priority</label>
                    <select id="quickPriority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="dashboard-card">
                    <label for="quickMessage">Message</label>
                    <textarea id="quickMessage" class="form-control" rows="4" placeholder="Enter your notification message here..." required></textarea>
                </div>
                <div class="dashboard-card">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Notification Preview</h3>
            <span class="close" onclick="closePreviewModal()">&times;</span>
        </div>
        <div id="previewModalBody">
            <!-- Preview content will be loaded here -->
        </div>
    </div>
</div>

<!-- External JavaScript -->
<script src="../../../../assets/js/admin-notifications.js"></script>

</body>
</html>