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

function getRecordStats($pdo) {
    $stats = [];

    try {
        // TEVETA records count
        $stmt = $pdo->query("SELECT COUNT(*) as total_teveta FROM teveta_data");
        $stats['total_teveta'] = (int)$stmt->fetchColumn();

        // Higher Education records count
        $stmt = $pdo->query("SELECT COUNT(*) as total_he FROM he_data");
        $stats['total_he'] = (int)$stmt->fetchColumn();

        // ECZ records count
        $stmt = $pdo->query("SELECT COUNT(*) as total_ecz FROM ecz_data");
        $stats['total_ecz'] = (int)$stmt->fetchColumn();

        // Total records across all systems
        $stats['total_records'] = $stats['total_teveta'] + $stats['total_he'] + $stats['total_ecz'];

        // Gender distribution for TEVETA
        $stmt = $pdo->query("
            SELECT gender, COUNT(*) as count 
            FROM teveta_data 
            GROUP BY gender
        ");
        $stats['teveta_gender'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent TEVETA records
        $stmt = $pdo->query("
            SELECT COUNT(*) as recent_teveta 
            FROM teveta_data 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stats['recent_teveta'] = (int)$stmt->fetchColumn();

        // Recent HE records
        $stmt = $pdo->query("
            SELECT COUNT(*) as recent_he 
            FROM he_data 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stats['recent_he'] = (int)$stmt->fetchColumn();

        // Recent ECZ records
        $stmt = $pdo->query("
            SELECT COUNT(*) as recent_ecz 
            FROM ecz_data 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stats['recent_ecz'] = (int)$stmt->fetchColumn();

    } catch (PDOException $e) {
        error_log("Error fetching record stats: " . $e->getMessage());
        // Return default values on error
        $stats = [
            'total_teveta' => 0,
            'total_he' => 0,
            'total_ecz' => 0,
            'total_records' => 0,
            'teveta_gender' => [],
            'recent_teveta' => 0,
            'recent_he' => 0,
            'recent_ecz' => 0
        ];
    }

    return $stats;
}

// Fetch data
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);
$recordStats = getRecordStats($pdo);

// Handle AJAX requests
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $recordStats,
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
    <title>LARS Staff Dashboard - Zambia Qualifications Authority</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/staff/dash.css">

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
        <!-- Welcome Section -->
<!--        <div class="welcome-section"></div>-->

        <!-- Statistics Grid -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card total-records">
                <h3><span class="stat-number" data-target="<?php echo $recordStats['total_records'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-database"></i> Total Records</p>
            </div>

            <div class="stat-card teveta-records">
                <h3><span class="stat-number" data-target="<?php echo $recordStats['total_teveta'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-tools"></i> TEVETA Records</p>
            </div>

            <div class="stat-card he-records">
                <h3><span class="stat-number" data-target="<?php echo $recordStats['total_he'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-university"></i> Higher Education Records</p>
            </div>

            <div class="stat-card ecz-records">
                <h3><span class="stat-number" data-target="<?php echo $recordStats['total_ecz'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-certificate"></i> ECZ Records</p>
            </div>
        </div>

        <!-- Recent Activity Grid -->
        <div class="stats-grid">
            <div class="stat-card recent-teveta">
                <h3><span class="stat-number" data-target="<?php echo $recordStats['recent_teveta'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-plus-circle"></i> New TEVETA (30 days)</p>
            </div>

            <div class="stat-card recent-he">
                <h3><span class="stat-number" data-target="<?php echo $recordStats['recent_he'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-plus-circle"></i> New HE (30 days)</p>
            </div>

            <div class="stat-card recent-ecz">
                <h3><span class="stat-number" data-target="<?php echo $recordStats['recent_ecz'] ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-plus-circle"></i> New ECZ (30 days)</p>
            </div>

            <div class="stat-card total-recent">
                <h3><span class="stat-number" data-target="<?php echo ($recordStats['recent_teveta'] + $recordStats['recent_he'] + $recordStats['recent_ecz']) ?? 0; ?>">0</span></h3>
                <p><i class="fas fa-chart-line"></i> Total New Records</p>
            </div>
        </div>

        <!-- Gender Distribution -->
        <?php if (!empty($recordStats['teveta_gender'])): ?>
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-pie"></i> TEVETA Gender Distribution</h3>
                <div class="gender-stats">
                    <?php foreach ($recordStats['teveta_gender'] as $gender): ?>
                        <div class="gender-item">
                            <span class="gender-label"><?php echo htmlspecialchars(ucfirst($gender['gender'])); ?></span>
                            <strong class="gender-count"><?php echo number_format($gender['count']); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <h3><i class="fas fa-search"></i> Quick Search</h3>
            <div class="quick-actions">
                <a href="search-teveta.php" class="action-btn teveta-btn">
                    <i class="fas fa-tools"></i> Search TEVETA Records
                </a>
                <a href="search-he.php" class="action-btn he-btn">
                    <i class="fas fa-university"></i> Search HE Records
                </a>
                <a href="search-ecz.php" class="action-btn ecz-btn">
                    <i class="fas fa-certificate"></i> Search ECZ Records
                </a>
                <a href="advanced-search.php" class="action-btn advanced-btn">
                    <i class="fas fa-search-plus"></i> Advanced Search
                </a>
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
            const statElements = document.querySelectorAll('.stat-number');

            statElements.forEach(function(element) {
                const target = parseInt(element.getAttribute('data-target'));
                element.textContent = target;
            });

            // Update timestamp
            console.log('Dashboard refreshed at:', data.timestamp);
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

        // Add click handlers for quick actions
        const actionBtns = document.querySelectorAll('.action-btn');
        actionBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                // Add loading state or other interactions as needed
                console.log('Navigating to:', this.href);
            });
        });
    });
</script>

</body>
</html>