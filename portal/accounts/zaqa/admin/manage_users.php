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
        case 'update_status':
            $userId = (int)($_POST['user_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $validStatuses = ['pending', 'active', 'suspended', 'rejected'];

            if ($userId && in_array($status, $validStatuses)) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
                    $result = $stmt->execute([$status, $_SESSION['user_id'], $userId]);
                    echo json_encode(['success' => $result, 'message' => 'Status updated successfully']);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'verify_email':
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
                    $result = $stmt->execute([$userId]);
                    echo json_encode(['success' => $result, 'message' => 'Email verified successfully']);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error verifying email: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            }
            exit;

        case 'update_role':
            $userId = (int)($_POST['user_id'] ?? 0);
            $roleId = (int)($_POST['role_id'] ?? 0);

            if ($userId && $roleId) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
                    $result = $stmt->execute([$roleId, $userId]);
                    echo json_encode(['success' => $result, 'message' => 'Role updated successfully']);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error updating role: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'update_category':
            $userId = (int)($_POST['user_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);

            if ($userId && $categoryId) {
                try {
                    // First get roles for this category
                    $stmt = $pdo->prepare("SELECT id FROM roles WHERE category_id = ? LIMIT 1");
                    $stmt->execute([$categoryId]);
                    $defaultRole = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($defaultRole) {
                        $stmt = $pdo->prepare("UPDATE users SET category_id = ?, role_id = ? WHERE id = ?");
                        $result = $stmt->execute([$categoryId, $defaultRole['id'], $userId]);
                        echo json_encode(['success' => $result, 'message' => 'Category and role updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'No roles found for this category']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error updating category: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'get_roles':
            $categoryId = (int)($_GET['category_id'] ?? 0);

            if ($categoryId) {
                try {
                    $stmt = $pdo->prepare("SELECT id, name FROM roles WHERE category_id = ? ORDER BY name");
                    $stmt->execute([$categoryId]);
                    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'roles' => $roles]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error fetching roles: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
            }
            exit;

        case 'get_user_details':
            $userId = (int)($_GET['user_id'] ?? 0);

            if ($userId) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT u.*, c.name as category_name, r.name as role_name,
                               approver.first_name as approver_first_name, 
                               approver.last_name as approver_last_name
                        FROM users u 
                        JOIN category c ON u.category_id = c.id 
                        JOIN roles r ON u.role_id = r.id 
                        LEFT JOIN users approver ON u.approved_by = approver.id
                        WHERE u.id = ?
                    ");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        echo json_encode(['success' => true, 'user' => $user]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'User not found']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error fetching user details: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            }
            exit;

        case 'bulk_action':
            $userIds = json_decode($_POST['user_ids'] ?? '[]', true);
            $bulkAction = $_POST['bulk_action'] ?? '';

            if (!empty($userIds) && !empty($bulkAction)) {
                try {
                    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';

                    switch ($bulkAction) {
                        case 'activate':
                            $stmt = $pdo->prepare("UPDATE users SET status = 'active', approved_by = ?, approved_at = NOW() WHERE id IN ($placeholders)");
                            $params = array_merge([$_SESSION['user_id']], $userIds);
                            break;
                        case 'suspend':
                            $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id IN ($placeholders)");
                            $params = $userIds;
                            break;
                        case 'verify_emails':
                            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id IN ($placeholders)");
                            $params = $userIds;
                            break;
                        default:
                            echo json_encode(['success' => false, 'message' => 'Invalid bulk action']);
                            exit;
                    }

                    $result = $stmt->execute($params);
                    echo json_encode(['success' => $result, 'message' => 'Bulk action completed successfully']);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error performing bulk action: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No users or action selected']);
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
        $where = [];
        $params = [];

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

        if (!empty($filters['email_verified'])) {
            $where[] = "u.email_verified = ?";
            $params[] = $filters['email_verified'] === 'verified' ? 1 : 0;
        }

        if (!empty($filters['search'])) {
            $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

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
                   approver.first_name as approver_first_name, 
                   approver.last_name as approver_last_name
            FROM users u 
            JOIN category c ON u.category_id = c.id 
            JOIN roles r ON u.role_id = r.id 
            LEFT JOIN users approver ON u.approved_by = approver.id
            $whereClause
            ORDER BY 
                CASE WHEN u.status = 'pending' THEN 1 ELSE 2 END,
                CASE WHEN u.email_verified = 0 THEN 1 ELSE 2 END,
                u.created_at DESC
            LIMIT ? OFFSET ?
        ";

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

// Get data
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);
$categories = getCategories($pdo);
$allRoles = getRoles($pdo);

// Get filters from request
$filters = [
    'status' => $_GET['status'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'role_id' => $_GET['role_id'] ?? '',
    'email_verified' => $_GET['email_verified'] ?? '',
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
    <title>User Management - LARS Admin Dashboard</title>

    <!-- CSS Files -->
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/admin/manage_users.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../../assets/images/icon.png">
</head>
<body>

<?php include 'generic/navbar.php'; ?>

<div class="user-management-container">
    <?php include 'generic/sidebar.php'; ?>

    <main class="user-management-content">
        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Filters -->
        <div class="user-management-card">
            <h3><i class="fas fa-filter"></i> Filter Users</h3>
            <form id="filterForm" method="GET">
                <div class="user-management-grid">
                    <div class="user-management-card">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="user-management-card">
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

                    <div class="user-management-card">
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

                    <div class="user-management-card">
                        <label for="email_verified">Email Status</label>
                        <select name="email_verified" id="email_verified" class="form-control">
                            <option value="">All Emails</option>
                            <option value="verified" <?php echo $filters['email_verified'] === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="unverified" <?php echo $filters['email_verified'] === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                        </select>
                    </div>
                </div>

                <div class="user-management-grid">
                    <div class="user-management-card">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Name or email..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>

                    <div class="user-management-card">
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

        <!-- Quick Actions for New Users -->
        <div class="user-management-card">
            <h3><i class="fas fa-star"></i> Quick Actions - New Users Needing Attention</h3>
            <div id="newUsersContainer">
                <?php
                $newUsers = array_filter($userData['users'], function($user) {
                    return $user['status'] === 'pending' || !$user['email_verified'];
                });
                ?>
                <?php if (!empty($newUsers)): ?>
                    <p><strong><?php echo count($newUsers); ?> users need your attention!</strong></p>
                    <div class="user-management-grid">
                        <?php foreach (array_slice($newUsers, 0, 6) as $user): ?>
                            <div class="user-management-stat-card" data-user-id="<?php echo $user['id']; ?>">
                                <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                <p><?php echo htmlspecialchars($user['email']); ?></p>
                                <p><?php echo htmlspecialchars($user['role_name'] . ' - ' . $user['category_name']); ?></p>
                                <div class="status-indicators">
                                    <?php if ($user['status'] === 'pending'): ?>
                                        <span class="status-badge status-pending">Pending Approval</span>
                                    <?php endif; ?>
                                    <?php if (!$user['email_verified']): ?>
                                        <span class="status-badge status-rejected">Email Unverified</span>
                                    <?php endif; ?>
                                </div>
                                <div class="quick-actions">
                                    <?php if ($user['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success" onclick="updateStatus(<?php echo $user['id']; ?>, 'active')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!$user['email_verified']): ?>
                                        <button class="btn btn-sm btn-info" onclick="verifyEmail(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-envelope-check"></i> Verify Email
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><i class="fas fa-check-circle"></i> All users are up to date! No pending actions required.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="user-management-card">
            <h3><i class="fas fa-tasks"></i> Bulk Actions</h3>
            <div class="user-management-grid">
                <div class="user-management-stat-card">
                    <select id="bulkAction" class="form-control">
                        <option value="">Select Bulk Action</option>
                        <option value="activate">Activate Selected Users</option>
                        <option value="suspend">Suspend Selected Users</option>
                        <option value="verify_emails">Verify Selected Emails</option>
                    </select>
                </div>
                <div class="user-management-stat-card">
                    <button type="button" id="applyBulkAction" class="btn btn-warning">
                        <i class="fas fa-bolt"></i> Apply to Selected
                    </button>
                    <span id="selectedCount">0 users selected</span>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="user-management-card">
            <h3>
                <i class="fas fa-list"></i> All Users
                <span class="badge"><?php echo number_format($userData['total']); ?> total</span>
            </h3>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Category</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Email Verified</th>
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
                                <?php if ($user['status'] === 'pending' && !$user['email_verified']): ?>
                                    <span class="status-badge status-rejected">NEW</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td>
                                <select class="form-control category-select" data-user-id="<?php echo $user['id']; ?>" data-current="<?php echo $user['category_id']; ?>">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $user['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="form-control role-select" data-user-id="<?php echo $user['id']; ?>" data-current="<?php echo $user['role_id']; ?>">
                                    <?php foreach ($allRoles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" <?php echo $user['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="form-control status-select" data-user-id="<?php echo $user['id']; ?>">
                                    <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    <option value="rejected" <?php echo $user['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['email_verified'] ? 'status-active' : 'status-rejected'; ?>">
                                    <?php echo $user['email_verified'] ? 'Verified' : 'Unverified'; ?>
                                </span>
                                <?php if (!$user['email_verified']): ?>
                                    <br>
                                    <button class="btn btn-sm btn-info" onclick="verifyEmail(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-check"></i> Verify
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewUserDetails(<?php echo $user['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($userData['pages'] > 1): ?>
                <div class="user-management-pagination-container">
                    <div class="user-management-pagination">
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
    </main>
</div>

<!-- User Details Modal -->
<div id="userDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">User Details</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalBody">
            <!-- User details will be loaded here -->
        </div>
    </div>
</div>

<!-- External JavaScript -->
<script src="../../../../assets/js/admin-manage-users.js"></script>

</body>
</html>