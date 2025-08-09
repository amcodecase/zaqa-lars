<?php
session_start();

// Check if user is logged in
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
        case 'add_institution':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? '';
            $validTypes = ['University', 'College', 'Institute'];

            if ($categoryId && !empty($name) && in_array($type, $validTypes)) {
                try {
                    // Check if category exists
                    $stmt = $pdo->prepare("SELECT id FROM category WHERE id = ?");
                    $stmt->execute([$categoryId]);
                    if (!$stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Invalid category selected']);
                        exit;
                    }

                    // Check for duplicate name
                    $stmt = $pdo->prepare("SELECT id FROM institutions WHERE name = ?");
                    $stmt->execute([$name]);
                    if ($stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Institution name already exists']);
                        exit;
                    }

                    $stmt = $pdo->prepare("INSERT INTO institutions (category_id, name, type, created_at) VALUES (?, ?, ?, NOW())");
                    $result = $stmt->execute([$categoryId, $name, $type]);
                    echo json_encode(['success' => $result, 'message' => 'Institution added successfully']);
                } catch (PDOException $e) {
                    error_log("Error adding institution: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error adding institution. Please try again.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'All fields are required and must be valid']);
            }
            exit;

        case 'update_institution':
            $institutionId = (int)($_POST['institution_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? '';
            $validTypes = ['University', 'College', 'Institute'];

            if ($institutionId && $categoryId && !empty($name) && in_array($type, $validTypes)) {
                try {
                    // Check if institution exists
                    $stmt = $pdo->prepare("SELECT id FROM institutions WHERE id = ?");
                    $stmt->execute([$institutionId]);
                    if (!$stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Institution not found']);
                        exit;
                    }

                    // Check if category exists
                    $stmt = $pdo->prepare("SELECT id FROM category WHERE id = ?");
                    $stmt->execute([$categoryId]);
                    if (!$stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Invalid category selected']);
                        exit;
                    }

                    // Check for duplicate name (excluding current institution)
                    $stmt = $pdo->prepare("SELECT id FROM institutions WHERE name = ? AND id != ?");
                    $stmt->execute([$name, $institutionId]);
                    if ($stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Institution name already exists']);
                        exit;
                    }

                    $stmt = $pdo->prepare("UPDATE institutions SET category_id = ?, name = ?, type = ? WHERE id = ?");
                    $result = $stmt->execute([$categoryId, $name, $type, $institutionId]);
                    echo json_encode(['success' => $result, 'message' => 'Institution updated successfully']);
                } catch (PDOException $e) {
                    error_log("Error updating institution: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error updating institution. Please try again.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'All fields are required and must be valid']);
            }
            exit;

        case 'delete_institution':
            $institutionId = (int)($_POST['institution_id'] ?? 0);

            if ($institutionId) {
                try {
                    // Check if institution exists
                    $stmt = $pdo->prepare("SELECT id FROM institutions WHERE id = ?");
                    $stmt->execute([$institutionId]);
                    if (!$stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Institution not found']);
                        exit;
                    }

                    // Check if institution is being used by users (if users table has institution_id column)
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE institution_id = ?");
                        $stmt->execute([$institutionId]);
                        $userCount = $stmt->fetchColumn();

                        if ($userCount > 0) {
                            echo json_encode(['success' => false, 'message' => "Cannot delete institution. It is being used by $userCount user(s)."]);
                            exit;
                        }
                    } catch (PDOException $e) {
                        // If users table doesn't have institution_id column, continue with deletion
                        error_log("Users table might not have institution_id column: " . $e->getMessage());
                    }

                    $stmt = $pdo->prepare("DELETE FROM institutions WHERE id = ?");
                    $result = $stmt->execute([$institutionId]);
                    echo json_encode(['success' => $result, 'message' => 'Institution deleted successfully']);
                } catch (PDOException $e) {
                    error_log("Error deleting institution: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error deleting institution. Please try again.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid institution ID']);
            }
            exit;

        case 'get_institution_details':
            $institutionId = (int)($_GET['institution_id'] ?? 0);

            if ($institutionId) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT i.*, c.name as category_name
                        FROM institutions i 
                        JOIN category c ON i.category_id = c.id 
                        WHERE i.id = ?
                    ");
                    $stmt->execute([$institutionId]);
                    $institution = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($institution) {
                        // Try to get user count if column exists
                        try {
                            $userStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE institution_id = ?");
                            $userStmt->execute([$institutionId]);
                            $institution['user_count'] = $userStmt->fetchColumn();
                        } catch (PDOException $e) {
                            $institution['user_count'] = 0;
                        }

                        echo json_encode(['success' => true, 'institution' => $institution]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Institution not found']);
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching institution details: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error fetching institution details']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid institution ID']);
            }
            exit;

        case 'bulk_delete':
            $institutionIds = json_decode($_POST['institution_ids'] ?? '[]', true);

            // Validate that all IDs are integers
            $institutionIds = array_filter(array_map('intval', $institutionIds), function($id) {
                return $id > 0;
            });

            if (!empty($institutionIds)) {
                try {
                    $placeholders = str_repeat('?,', count($institutionIds) - 1) . '?';

                    // Check if any institutions are being used by users (if column exists)
                    try {
                        $stmt = $pdo->prepare("SELECT institution_id, COUNT(*) as user_count FROM users WHERE institution_id IN ($placeholders) GROUP BY institution_id");
                        $stmt->execute($institutionIds);
                        $usedInstitutions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($usedInstitutions)) {
                            $usedCount = array_sum(array_column($usedInstitutions, 'user_count'));
                            echo json_encode(['success' => false, 'message' => "Cannot delete selected institutions. They are being used by $usedCount user(s)."]);
                            exit;
                        }
                    } catch (PDOException $e) {
                        // If users table doesn't have institution_id column, continue with deletion
                        error_log("Users table might not have institution_id column: " . $e->getMessage());
                    }

                    $stmt = $pdo->prepare("DELETE FROM institutions WHERE id IN ($placeholders)");
                    $result = $stmt->execute($institutionIds);
                    $deletedCount = $stmt->rowCount();
                    echo json_encode(['success' => $result, 'message' => "$deletedCount institution(s) deleted successfully"]);
                } catch (PDOException $e) {
                    error_log("Error deleting institutions: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error deleting institutions. Please try again.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No valid institutions selected']);
            }
            exit;

        case 'get_institutions':
            echo json_encode(getInstitutions($pdo, [
                'category_id' => $_GET['category_id'] ?? '',
                'type' => $_GET['type'] ?? '',
                'search' => $_GET['search'] ?? ''
            ]));
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

function getCurrentUser($pdo, $userId) {
    try {
        // Updated query to match your actual table structure
        $stmt = $pdo->prepare("
            SELECT u.*, c.name as category_name, r.name as role_name 
            FROM users u 
            JOIN category c ON u.category_id = c.id 
            JOIN roles r ON u.category_id = r.category_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        // Try simpler query if join fails
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $user['category_name'] = 'Unknown';
                $user['role_name'] = 'Unknown';
            }
            return $user;
        } catch (PDOException $e2) {
            error_log("Error fetching user with simple query: " . $e2->getMessage());
            return false;
        }
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

function getInstitutions($pdo, $filters = []) {
    try {
        $where = [];
        $params = [];

        if (!empty($filters['category_id']) && is_numeric($filters['category_id'])) {
            $where[] = "i.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['type']) && in_array($filters['type'], ['University', 'College', 'Institute'])) {
            $where[] = "i.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['search'])) {
            $where[] = "i.name LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Get total count
        $countSql = "SELECT COUNT(*) FROM institutions i $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalInstitutions = $countStmt->fetchColumn();

        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Get data with pagination - Fixed the query
        $dataSql = "
            SELECT i.*, c.name as category_name
            FROM institutions i 
            JOIN category c ON i.category_id = c.id 
            $whereClause
            ORDER BY i.name ASC
            LIMIT ? OFFSET ?
        ";

        $dataParams = array_merge($params, [$limit, $offset]);
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($dataParams);
        $institutions = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        // Add user count for each institution (if users table has institution_id)
        foreach ($institutions as &$institution) {
            try {
                $userStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE institution_id = ?");
                $userStmt->execute([$institution['id']]);
                $institution['user_count'] = $userStmt->fetchColumn();
            } catch (PDOException $e) {
                $institution['user_count'] = 0;
            }
        }

        return [
            'institutions' => $institutions,
            'total' => $totalInstitutions,
            'page' => $page,
            'pages' => ceil($totalInstitutions / $limit)
        ];
    } catch (PDOException $e) {
        error_log("Error fetching institutions: " . $e->getMessage());
        error_log("SQL Error: " . $e->getMessage());
        error_log("Error Code: " . $e->getCode());
        return ['institutions' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
    }
}

function getInstitutionStats($pdo) {
    try {
        $stats = [];

        // Total institutions
        $stmt = $pdo->query("SELECT COUNT(*) FROM institutions");
        $stats['total'] = $stmt->fetchColumn();

        // By type
        $stmt = $pdo->query("SELECT type, COUNT(*) as count FROM institutions GROUP BY type ORDER BY count DESC");
        $typeData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['by_type'] = [];
        foreach ($typeData as $row) {
            $stats['by_type'][$row['type']] = $row['count'];
        }

        // By category - Fixed the query
        $stmt = $pdo->query("
            SELECT c.name, COUNT(i.id) as count 
            FROM category c 
            LEFT JOIN institutions i ON c.id = i.category_id 
            GROUP BY c.id, c.name 
            ORDER BY count DESC
        ");
        $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    } catch (PDOException $e) {
        error_log("Error fetching institution stats: " . $e->getMessage());
        return ['total' => 0, 'by_type' => [], 'by_category' => []];
    }
}

// Debug function to help troubleshoot
function debugInstitutions($pdo) {
    if (!isset($_GET['debug'])) {
        return; // Only show debug info if debug=1 is in URL
    }

    try {
        echo "<!-- DEBUG INFO -->\n";

        // Test basic query
        $stmt = $pdo->query("SELECT COUNT(*) FROM institutions");
        $count = $stmt->fetchColumn();
        echo "<!-- Total institutions in DB: $count -->\n";

        // Test categories
        $stmt = $pdo->query("SELECT COUNT(*) FROM category");
        $catCount = $stmt->fetchColumn();
        echo "<!-- Total categories in DB: $catCount -->\n";

        // Test the join
        $stmt = $pdo->query("
            SELECT i.*, c.name as category_name 
            FROM institutions i 
            JOIN category c ON i.category_id = c.id 
            LIMIT 5
        ");
        $testResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<!-- Test join results: " . json_encode($testResults) . " -->\n";

        // Check if users table has institution_id column
        try {
            $stmt = $pdo->query("DESCRIBE users");
            $userColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasInstitutionId = false;
            foreach ($userColumns as $column) {
                if ($column['Field'] === 'institution_id') {
                    $hasInstitutionId = true;
                    break;
                }
            }
            echo "<!-- Users table has institution_id column: " . ($hasInstitutionId ? 'YES' : 'NO') . " -->\n";
        } catch (PDOException $e) {
            echo "<!-- Error checking users table structure: " . $e->getMessage() . " -->\n";
        }

    } catch (PDOException $e) {
        echo "<!-- Debug error: " . $e->getMessage() . " -->\n";
    }
}

// Load data for non-AJAX requests
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);
$categories = getCategories($pdo);
$stats = getInstitutionStats($pdo);

// Handle filters with validation
$filters = [
    'category_id' => !empty($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : '',
    'type' => !empty($_GET['type']) && in_array($_GET['type'], ['University', 'College', 'Institute']) ? $_GET['type'] : '',
    'search' => !empty($_GET['search']) ? trim($_GET['search']) : ''
];

$institutionData = getInstitutions($pdo, $filters);

// Add debug output
debugInstitutions($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institution Management - LARS Admin Dashboard</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/admin-sidebar.css">
    <link rel="stylesheet" href="../../../../assets/css/admin-manage-institutions.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../../assets/images/icon.png">
</head>
<body>

<?php
// Include navbar if file exists
if (file_exists('generic/navbar.php')) {
    include 'generic/navbar.php';
} else {
    echo '<!-- navbar.php not found -->';
}
?>

<div class="dashboard-container">
    <?php
    // Include sidebar if file exists
    if (file_exists('generic/sidebar.php')) {
        include 'generic/sidebar.php';
    } else {
        echo '<!-- sidebar.php not found -->';
    }
    ?>

    <main class="dashboard-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1><i class="fas fa-university"></i> Institution Management</h1>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
            <!-- Debug Info (only shown when debug=1 in URL) -->
            <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; font-family: monospace;">
                <strong>Debug Info:</strong><br>
                Total institutions: <?php echo $stats['total']; ?><br>
                Total categories: <?php echo count($categories); ?><br>
                Institution data count: <?php echo count($institutionData['institutions']); ?><br>
                Filters: <?php echo json_encode($filters); ?><br>
                Current page: <?php echo $institutionData['page']; ?><br>
                Total pages: <?php echo $institutionData['pages']; ?><br>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-bar"></i> Institution Statistics</h3>
            <div class="content-grid">
                <div class="stat-card">
                    <h4><?php echo number_format($stats['total']); ?></h4>
                    <p>Total Institutions</p>
                </div>
                <?php if (!empty($stats['by_type'])): ?>
                    <?php foreach ($stats['by_type'] as $type => $count): ?>
                        <div class="stat-card">
                            <h4><?php echo number_format($count); ?></h4>
                            <p><?php echo htmlspecialchars($type); ?>s</p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add New Institution -->
        <div class="dashboard-card">
            <h3><i class="fas fa-plus"></i> Add New Institution</h3>
            <form id="addInstitutionForm">
                <div class="content-grid">
                    <div class="dashboard-card">
                        <label for="add_category_id">Category *</label>
                        <select name="category_id" id="add_category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dashboard-card">
                        <label for="add_name">Institution Name *</label>
                        <input type="text" name="name" id="add_name" class="form-control" placeholder="Institution name..." required maxlength="150">
                    </div>

                    <div class="dashboard-card">
                        <label for="add_type">Type *</label>
                        <select name="type" id="add_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="University">University</option>
                            <option value="College">College</option>
                            <option value="Institute">Institute</option>
                        </select>
                    </div>

                    <div class="dashboard-card">
                        <br>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Institution
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Filters -->
        <div class="dashboard-card">
            <h3><i class="fas fa-filter"></i> Filter Institutions</h3>
            <form id="filterForm" method="GET">
                <div class="content-grid">
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
                        <label for="type">Type</label>
                        <select name="type" id="type" class="form-control">
                            <option value="">All Types</option>
                            <option value="University" <?php echo $filters['type'] === 'University' ? 'selected' : ''; ?>>University</option>
                            <option value="College" <?php echo $filters['type'] === 'College' ? 'selected' : ''; ?>>College</option>
                            <option value="Institute" <?php echo $filters['type'] === 'Institute' ? 'selected' : ''; ?>>Institute</option>
                        </select>
                    </div>

                    <div class="dashboard-card">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Institution name..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>

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

        <!-- Bulk Actions -->
        <div class="dashboard-card">
            <h3><i class="fas fa-tasks"></i> Bulk Actions</h3>
            <div class="content-grid">
                <div class="stat-card">
                    <button type="button" id="bulkDeleteBtn" class="btn btn-danger" disabled>
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <span id="selectedCount">0 institutions selected</span>
                </div>
            </div>
        </div>

        <!-- Institutions Table -->
        <div class="dashboard-card">
            <h3>
                <i class="fas fa-list"></i> All Institutions
                <span class="badge"><?php echo number_format($institutionData['total']); ?> total</span>
            </h3>

            <?php if (!empty($institutionData['institutions'])): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Users</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody id="institutionsTableBody">
                        <?php foreach ($institutionData['institutions'] as $institution): ?>
                            <tr class="institution-row" data-institution-id="<?php echo $institution['id']; ?>">
                                <td><input type="checkbox" class="institution-checkbox" value="<?php echo $institution['id']; ?>"></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($institution['name']); ?></strong>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo strtolower($institution['type']); ?>">
                                        <?php echo htmlspecialchars($institution['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($institution['category_name']); ?></td>
                                <td>
                                    <span class="badge"><?php echo number_format($institution['user_count']); ?></span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($institution['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="viewInstitutionDetails(<?php echo $institution['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="editInstitution(<?php echo $institution['id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($institution['user_count'] == 0): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteInstitution(<?php echo $institution['id']; ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled title="Cannot delete - has users">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($institutionData['pages'] > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination">
                            <?php if ($institutionData['page'] > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $institutionData['page'] - 1])); ?>">&laquo; Previous</a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $institutionData['page'] - 2);
                            $endPage = min($institutionData['pages'], $institutionData['page'] + 2);

                            if ($startPage > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($filters, ['page' => 1])); ?>">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span>...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php if ($i == $institutionData['page']): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($endPage < $institutionData['pages']): ?>
                                <?php if ($endPage < $institutionData['pages'] - 1): ?>
                                    <span>...</span>
                                <?php endif; ?>
                                <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $institutionData['pages']])); ?>"><?php echo $institutionData['pages']; ?></a>
                            <?php endif; ?>

                            <?php if ($institutionData['page'] < $institutionData['pages']): ?>
                                <a href="?<?php echo http_build_query(array_merge($filters, ['page' => $institutionData['page'] + 1])); ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-info">
                            Showing <?php echo (($institutionData['page'] - 1) * 20) + 1; ?>-<?php echo min($institutionData['page'] * 20, $institutionData['total']); ?> of <?php echo number_format($institutionData['total']); ?> institutions
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-university fa-3x"></i>
                    <h3>No institutions found</h3>
                    <p>No institutions match your current filter criteria.</p>
                    <?php if (!empty(array_filter($filters))): ?>
                        <button type="button" class="btn btn-primary" onclick="clearFilters()">Clear Filters</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Institution Details Modal -->
<div id="institutionDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Institution Details</h3>
            <span class="close" onclick="closeModal('institutionDetailsModal')">&times;</span>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Institution details will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('institutionDetailsModal')">Close</button>
        </div>
    </div>
</div>

<!-- Edit Institution Modal -->
<div id="editInstitutionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Institution</h3>
            <span class="close" onclick="closeModal('editInstitutionModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editInstitutionForm">
                <input type="hidden" id="edit_institution_id" name="institution_id">

                <div class="form-group">
                    <label for="edit_category_id">Category *</label>
                    <select name="category_id" id="edit_category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_name">Institution Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" placeholder="Institution name..." required maxlength="150">
                </div>

                <div class="form-group">
                    <label for="edit_type">Type *</label>
                    <select name="type" id="edit_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="University">University</option>
                        <option value="College">College</option>
                        <option value="Institute">Institute</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editInstitutionModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="updateInstitution()">
                <i class="fas fa-save"></i> Update Institution
            </button>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Bulk Delete</h3>
            <span class="close" onclick="closeModal('bulkDeleteModal')">&times;</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete the selected institutions?</p>
            <p><strong>This action cannot be undone.</strong></p>
            <div id="bulkDeleteList"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('bulkDeleteModal')">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()">
                <i class="fas fa-trash"></i> Delete Selected
            </button>
        </div>
    </div>
</div>

<!-- JavaScript Functions -->
<script>
    // Clear filters function
    function clearFilters() {
        window.location.href = window.location.pathname;
    }

    // Modal functions
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function viewInstitutionDetails(institutionId) {
        fetch(`?ajax=1&action=get_institution_details&institution_id=${institutionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const institution = data.institution;
                    const modalBody = document.getElementById('modalBody');
                    modalBody.innerHTML = `
                    <div class="institution-details">
                        <div class="detail-row">
                            <strong>Name:</strong> ${institution.name}
                        </div>
                        <div class="detail-row">
                            <strong>Type:</strong> ${institution.type}
                        </div>
                        <div class="detail-row">
                            <strong>Category:</strong> ${institution.category_name}
                        </div>
                        <div class="detail-row">
                            <strong>Users:</strong> ${institution.user_count || 0}
                        </div>
                        <div class="detail-row">
                            <strong>Created:</strong> ${new Date(institution.created_at).toLocaleDateString()}
                        </div>
                    </div>
                `;
                    document.getElementById('institutionDetailsModal').style.display = 'block';
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Error fetching institution details');
            });
    }

    function editInstitution(institutionId) {
        fetch(`?ajax=1&action=get_institution_details&institution_id=${institutionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const institution = data.institution;
                    document.getElementById('edit_institution_id').value = institution.id;
                    document.getElementById('edit_category_id').value = institution.category_id;
                    document.getElementById('edit_name').value = institution.name;
                    document.getElementById('edit_type').value = institution.type;
                    document.getElementById('editInstitutionModal').style.display = 'block';
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Error fetching institution details');
            });
    }

    function updateInstitution() {
        const form = document.getElementById('editInstitutionForm');
        const formData = new FormData(form);
        formData.append('action', 'update_institution');

        fetch('?ajax=1', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    closeModal('editInstitutionModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Error updating institution');
            });
    }

    function deleteInstitution(institutionId) {
        if (confirm('Are you sure you want to delete this institution? This action cannot be undone.')) {
            const formData = new FormData();
            formData.append('action', 'delete_institution');
            formData.append('institution_id', institutionId);

            fetch('?ajax=1', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'Error deleting institution');
                });
        }
    }

    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

        alertContainer.innerHTML = `
        <div class="alert ${alertClass}" style="margin: 10px 0; padding: 10px; border-radius: 5px; ${type === 'success' ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'}">
            ${message}
        </div>
    `;

        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);
    }

    // Bulk operations
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('selectAll');
        const institutionCheckboxes = document.querySelectorAll('.institution-checkbox');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const selectedCount = document.getElementById('selectedCount');

        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                institutionCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkActions();
            });
        }

        // Individual checkbox functionality
        institutionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });

        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.institution-checkbox:checked');
            const count = checkedBoxes.length;

            selectedCount.textContent = `${count} institution${count !== 1 ? 's' : ''} selected`;
            bulkDeleteBtn.disabled = count === 0;

            // Update select all checkbox state
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = count === institutionCheckboxes.length && count > 0;
                selectAllCheckbox.indeterminate = count > 0 && count < institutionCheckboxes.length;
            }
        }

        // Bulk delete functionality
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.institution-checkbox:checked');
                const institutionIds = Array.from(checkedBoxes).map(cb => cb.value);

                if (institutionIds.length > 0) {
                    // Show bulk delete modal with list of institutions
                    const institutionNames = Array.from(checkedBoxes).map(cb => {
                        const row = cb.closest('tr');
                        return row.querySelector('strong').textContent;
                    });

                    document.getElementById('bulkDeleteList').innerHTML = `
                    <ul>
                        ${institutionNames.map(name => `<li>${name}</li>`).join('')}
                    </ul>
                `;

                    document.getElementById('bulkDeleteModal').style.display = 'block';
                }
            });
        }

        // Add institution form
        const addForm = document.getElementById('addInstitutionForm');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'add_institution');

                fetch('?ajax=1', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', data.message);
                            this.reset();
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showAlert('error', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('error', 'Error adding institution');
                    });
            });
        }
    });

    function confirmBulkDelete() {
        const checkedBoxes = document.querySelectorAll('.institution-checkbox:checked');
        const institutionIds = Array.from(checkedBoxes).map(cb => cb.value);

        const formData = new FormData();
        formData.append('action', 'bulk_delete');
        formData.append('institution_ids', JSON.stringify(institutionIds));

        fetch('?ajax=1', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    closeModal('bulkDeleteModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Error deleting institutions');
            });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
</script>

<!-- Include jQuery if your existing JS files need it -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</body>
</html>