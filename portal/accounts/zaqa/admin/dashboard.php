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

function getDashboardStats($pdo) {
    $stats = [];

    try {
        // Total users
        $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
        $stats['total_users'] = (int)$stmt->fetchColumn();

        // Status breakdown
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM users GROUP BY status");
        $statusCounts = [];
        foreach ($stmt as $row) {
            $statusCounts[$row['status']] = (int)$row['count'];
        }
        $stats['pending_users'] = $statusCounts['pending'] ?? 0;
        $stats['active_users'] = $statusCounts['active'] ?? 0;
        $stats['suspended_users'] = $statusCounts['suspended'] ?? 0;
        $stats['rejected_users'] = $statusCounts['rejected'] ?? 0;

        // Category breakdown
        $stmt = $pdo->query("
            SELECT c.name, COUNT(u.id) as count 
            FROM category c 
            LEFT JOIN users u ON c.id = u.category_id 
            GROUP BY c.id, c.name
            ORDER BY c.name
        ");
        $stats['category_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Role breakdown
        $stmt = $pdo->query("
            SELECT r.name, COUNT(u.id) as count 
            FROM roles r 
            LEFT JOIN users u ON r.id = u.role_id 
            GROUP BY r.id, r.name
            ORDER BY r.name
        ");
        $stats['role_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent registrations (last 30 days)
        $stmt = $pdo->query("
            SELECT COUNT(*) as recent_registrations 
            FROM users 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stats['recent_registrations'] = (int)$stmt->fetchColumn();

        // Email verification stats
        $stmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_emails,
                SUM(CASE WHEN email_verified = 0 THEN 1 ELSE 0 END) as unverified_emails
            FROM users
        ");
        $emailStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['verified_emails'] = (int)($emailStats['verified_emails'] ?? 0);
        $stats['unverified_emails'] = (int)($emailStats['unverified_emails'] ?? 0);

    } catch (PDOException $e) {
        error_log("Error fetching dashboard stats: " . $e->getMessage());
        // Return default values on error
        $stats = [
            'total_users' => 0,
            'pending_users' => 0,
            'active_users' => 0,
            'suspended_users' => 0,
            'rejected_users' => 0,
            'category_breakdown' => [],
            'role_breakdown' => [],
            'recent_registrations' => 0,
            'verified_emails' => 0,
            'unverified_emails' => 0
        ];
    }

    return $stats;
}

function getRecentUsers($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, u.email, u.status, u.created_at, 
                   c.name as category_name, r.name as role_name
            FROM users u 
            JOIN category c ON u.category_id = c.id 
            JOIN roles r ON u.role_id = r.id 
            ORDER BY u.created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching recent users: " . $e->getMessage());
        return [];
    }
}

function getPendingApprovals($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.first_name, u.last_name, u.email, u.created_at,
                   c.name as category_name, r.name as role_name
            FROM users u 
            JOIN category c ON u.category_id = c.id 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.status = 'pending'
            ORDER BY u.created_at ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching pending approvals: " . $e->getMessage());
        return [];
    }
}

// Fetch data
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);
$dashboardStats = getDashboardStats($pdo);
$recentUsers = getRecentUsers($pdo, 5);
$pendingApprovals = getPendingApprovals($pdo);

// Handle AJAX requests
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $dashboardStats,
        'recentUsers' => $recentUsers,
        'pendingApprovals' => $pendingApprovals,
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
    <title>LARS Admin Dashboard - Zambia Qualifications Authority</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/admin/dash.css">

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


        <!-- Statistics Grid -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card" <?php echo ($dashboardStats['pending_users'] ?? 0) > 0 ? 'data-pending="true"' : ''; ?>>
                <h3><span class="stat-number" data-target="<?php echo $dashboardStats['total_users'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-users"></i> Total Users</p>
            </div>

            <div class="stat-card">
                <h3><span class="stat-number" data-target="<?php echo $dashboardStats['active_users'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-user-check"></i> Active Users</p>
            </div>

            <div class="stat-card" id="pendingCard">
                <h3><span class="stat-number" data-target="<?php echo $dashboardStats['pending_users'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-user-clock"></i> Pending Approvals</p>
            </div>

            <div class="stat-card">
                <h3><span class="stat-number" data-target="<?php echo $dashboardStats['recent_registrations'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-user-plus"></i> New This Month</p>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Users -->
            <div class="dashboard-card">
                <h3><i class="fas fa-clock"></i> Recent Registrations</h3>
                <div id="recentUsersContainer">
                    <?php if (!empty($recentUsers)): ?>
                        <ul class="user-list">
                            <?php foreach ($recentUsers as $user): ?>
                                <li class="user-item">
                                    <div class="user-info">
                                        <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                        <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
<!--                                        <p class="user-role">--><?php //echo htmlspecialchars($user['role_name'] . ' - ' . $user['category_name']); ?><!--</p>-->
<!--                                        <p class="user-date">--><?php //echo date('M j, Y', strtotime($user['created_at'])); ?><!--</p>-->
                                    </div>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-data">No recent registrations found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="dashboard-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Pending Approvals</h3>
                <div id="pendingApprovalsContainer">
                    <?php if (!empty($pendingApprovals)): ?>
                        <ul class="user-list">
                            <?php foreach (array_slice($pendingApprovals, 0, 5) as $user): ?>
                                <li class="user-item">
                                    <div class="user-info">
                                        <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                        <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                                        <p class="user-role"><?php echo htmlspecialchars($user['role_name'] . ' - ' . $user['category_name']); ?></p>
                                        <p class="user-date">Registered: <?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                                    </div>
                                    <div class="action-buttons">
                                        <button class="btn-approve" data-user-id="<?php echo $user['id']; ?>" title="Approve User">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn-reject" data-user-id="<?php echo $user['id']; ?>" title="Reject User">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($pendingApprovals) > 5): ?>
                            <p class="view-all"><a href="user-management.php">View all <?php echo count($pendingApprovals); ?> pending approvals</a></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="no-data">No pending approvals.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="content-grid">
            <!-- Category Breakdown -->
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-pie"></i> Users by Category</h3>
                <div id="categoryBreakdownContainer">
                    <?php if (!empty($dashboardStats['category_breakdown'])): ?>
                        <ul class="breakdown-list">
                            <?php foreach ($dashboardStats['category_breakdown'] as $category): ?>
                                <li class="breakdown-item">
                                    <span class="breakdown-name"><?php echo htmlspecialchars($category['name']); ?></span>
                                    <strong class="breakdown-count"><?php echo number_format($category['count']); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-data">No category data available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Role Breakdown -->
            <div class="dashboard-card">
                <h3><i class="fas fa-user-tag"></i> Users by Role</h3>
                <div id="roleBreakdownContainer">
                    <?php if (!empty($dashboardStats['role_breakdown'])): ?>
                        <ul class="breakdown-list">
                            <?php foreach ($dashboardStats['role_breakdown'] as $role): ?>
                                <li class="breakdown-item">
                                    <span class="breakdown-name"><?php echo htmlspecialchars($role['name']); ?></span>
                                    <strong class="breakdown-count"><?php echo number_format($role['count']); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-data">No role data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Email Verification Status -->
        <div class="dashboard-card">
            <h3><i class="fas fa-envelope-check"></i> Email Verification Status</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><span class="stat-number" data-target="<?php echo $dashboardStats['verified_emails'] ?? 0; ?>">0</span></h3>
                    <p><i class="fas fa-check-circle"></i> Verified Emails</p>
                </div>
                <div class="stat-card">
                    <h3><span class="stat-number" data-target="<?php echo $dashboardStats['unverified_emails'] ?? 0; ?>">0</span></h3>
                    <p><i class="fas fa-exclamation-circle"></i> Unverified Emails</p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Refresh Indicator -->
<div class="refresh-indicator" id="refreshIndicator">
    <i class="fas fa-sync-alt fa-spin"></i> Refreshing data...
</div>

<!-- Last Updated Timestamp -->
<div class="last-updated" id="lastUpdated">
    Last updated: <span id="updateTime"><?php echo date('H:i:s'); ?></span>
</div>

<script>
    // Dashboard JavaScript functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Animate stat numbers
        function animateNumbers() {
            const statNumbers = document.querySelectorAll('.stat-number');

            statNumbers.forEach(function(element) {
                const target = parseInt(element.getAttribute('data-target'));
                const increment = target / 50;
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

        // Auto-refresh functionality
        function refreshDashboard() {
            const refreshIndicator = document.getElementById('refreshIndicator');
            const updateTime = document.getElementById('updateTime');

            if (refreshIndicator) {
                refreshIndicator.style.display = 'block';
            }

            fetch(window.location.href + '?ajax=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateDashboardData(data);
                        if (updateTime) {
                            updateTime.textContent = new Date().toLocaleTimeString();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing dashboard:', error);
                })
                .finally(() => {
                    if (refreshIndicator) {
                        refreshIndicator.style.display = 'none';
                    }
                });
        }

        // Update dashboard data
        function updateDashboardData(data) {
            // Update stat numbers
            const stats = data.stats;
            document.querySelector('[data-target]').setAttribute('data-target', stats.total_users || 0);

            // Update other elements as needed
            // This would require more detailed implementation based on your needs
        }

        // Initialize
        animateNumbers();

        // Auto-refresh every 5 minutes
        setInterval(refreshDashboard, 300000);

        // Manual refresh button (if you add one)
        const refreshButton = document.getElementById('refreshButton');
        if (refreshButton) {
            refreshButton.addEventListener('click', refreshDashboard);
        }
    });
</script>

</body>
</html>