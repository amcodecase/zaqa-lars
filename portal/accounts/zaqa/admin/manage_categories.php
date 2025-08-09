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
        case 'add_category':
            $name = trim($_POST['name'] ?? '');

            if (!empty($name)) {
                try {
                    // Check if category already exists
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE name = ?");
                    $checkStmt->execute([$name]);

                    if ($checkStmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Category already exists']);
                        exit;
                    }

                    $stmt = $pdo->prepare("INSERT INTO category (name) VALUES (?)");
                    $result = $stmt->execute([$name]);

                    if ($result) {
                        $categoryId = $pdo->lastInsertId();
                        echo json_encode([
                            'success' => true,
                            'message' => 'Category added successfully',
                            'category' => ['id' => $categoryId, 'name' => $name]
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add category']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error adding category: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Category name is required']);
            }
            exit;

        case 'update_category':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');

            if ($categoryId && !empty($name)) {
                try {
                    // Check if another category with this name exists
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM category WHERE name = ? AND id != ?");
                    $checkStmt->execute([$name, $categoryId]);

                    if ($checkStmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Another category with this name already exists']);
                        exit;
                    }

                    $stmt = $pdo->prepare("UPDATE category SET name = ? WHERE id = ?");
                    $result = $stmt->execute([$name, $categoryId]);
                    echo json_encode(['success' => $result, 'message' => 'Category updated successfully']);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error updating category: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'delete_category':
            $categoryId = (int)($_POST['category_id'] ?? 0);

            if ($categoryId) {
                try {
                    // Check if category has users
                    $userCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE category_id = ?");
                    $userCheck->execute([$categoryId]);
                    $userCount = $userCheck->fetchColumn();

                    if ($userCount > 0) {
                        echo json_encode(['success' => false, 'message' => "Cannot delete category. It has {$userCount} users assigned to it."]);
                        exit;
                    }

                    // Check if category has roles
                    $roleCheck = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE category_id = ?");
                    $roleCheck->execute([$categoryId]);
                    $roleCount = $roleCheck->fetchColumn();

                    if ($roleCount > 0) {
                        echo json_encode(['success' => false, 'message' => "Cannot delete category. It has {$roleCount} roles assigned to it."]);
                        exit;
                    }

                    $stmt = $pdo->prepare("DELETE FROM category WHERE id = ?");
                    $result = $stmt->execute([$categoryId]);
                    echo json_encode(['success' => $result, 'message' => 'Category deleted successfully']);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error deleting category: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
            }
            exit;

        case 'add_role':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');

            if ($categoryId && !empty($name)) {
                try {
                    // Check if role already exists in this category
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE name = ? AND category_id = ?");
                    $checkStmt->execute([$name, $categoryId]);

                    if ($checkStmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Role already exists in this category']);
                        exit;
                    }

                    $stmt = $pdo->prepare("INSERT INTO roles (category_id, name) VALUES (?, ?)");
                    $result = $stmt->execute([$categoryId, $name]);

                    if ($result) {
                        $roleId = $pdo->lastInsertId();
                        echo json_encode([
                            'success' => true,
                            'message' => 'Role added successfully',
                            'role' => ['id' => $roleId, 'name' => $name, 'category_id' => $categoryId]
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to add role']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error adding role: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Category and role name are required']);
            }
            exit;

        case 'update_role':
            $roleId = (int)($_POST['role_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');

            if ($roleId && $categoryId && !empty($name)) {
                try {
                    // Check if another role with this name exists in this category
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE name = ? AND category_id = ? AND id != ?");
                    $checkStmt->execute([$name, $categoryId, $roleId]);

                    if ($checkStmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Another role with this name already exists in this category']);
                        exit;
                    }

                    $stmt = $pdo->prepare("UPDATE roles SET name = ?, category_id = ? WHERE id = ?");
                    $result = $stmt->execute([$name, $categoryId, $roleId]);
                    echo json_encode(['success' => $result, 'message' => 'Role updated successfully']);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error updating role: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'delete_role':
            $roleId = (int)($_POST['role_id'] ?? 0);

            if ($roleId) {
                try {
                    // Check if role has users
                    $userCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
                    $userCheck->execute([$roleId]);
                    $userCount = $userCheck->fetchColumn();

                    if ($userCount > 0) {
                        echo json_encode(['success' => false, 'message' => "Cannot delete role. It has {$userCount} users assigned to it."]);
                        exit;
                    }

                    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
                    $result = $stmt->execute([$roleId]);
                    echo json_encode(['success' => $result, 'message' => 'Role deleted successfully']);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error deleting role: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid role ID']);
            }
            exit;

        case 'get_category_stats':
            $categoryId = (int)($_GET['category_id'] ?? 0);

            if ($categoryId) {
                try {
                    // Get category info
                    $categoryStmt = $pdo->prepare("SELECT * FROM category WHERE id = ?");
                    $categoryStmt->execute([$categoryId]);
                    $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$category) {
                        echo json_encode(['success' => false, 'message' => 'Category not found']);
                        exit;
                    }

                    // Get roles count
                    $rolesStmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE category_id = ?");
                    $rolesStmt->execute([$categoryId]);
                    $rolesCount = $rolesStmt->fetchColumn();

                    // Get users count
                    $usersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE category_id = ?");
                    $usersStmt->execute([$categoryId]);
                    $usersCount = $usersStmt->fetchColumn();

                    // Get users by status
                    $statusStmt = $pdo->prepare("
                        SELECT status, COUNT(*) as count 
                        FROM users 
                        WHERE category_id = ? 
                        GROUP BY status
                    ");
                    $statusStmt->execute([$categoryId]);
                    $statusBreakdown = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'category' => $category,
                        'roles_count' => $rolesCount,
                        'users_count' => $usersCount,
                        'status_breakdown' => $statusBreakdown
                    ]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Error fetching stats: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
            }
            exit;

        case 'get_roles_by_category':
            $categoryId = (int)($_GET['category_id'] ?? 0);

            if ($categoryId) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT r.*, COUNT(u.id) as user_count 
                        FROM roles r 
                        LEFT JOIN users u ON r.id = u.role_id 
                        WHERE r.category_id = ? 
                        GROUP BY r.id 
                        ORDER BY r.name
                    ");
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

function getCategories($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT c.*, 
                   COUNT(DISTINCT r.id) as roles_count,
                   COUNT(DISTINCT u.id) as users_count,
                   COUNT(DISTINCT CASE WHEN u.status = 'pending' THEN u.id END) as pending_users,
                   COUNT(DISTINCT CASE WHEN u.status = 'active' THEN u.id END) as active_users
            FROM category c 
            LEFT JOIN roles r ON c.id = r.category_id 
            LEFT JOIN users u ON c.id = u.category_id 
            GROUP BY c.id 
            ORDER BY c.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        return [];
    }
}

function getAllRoles($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT r.*, c.name as category_name, COUNT(u.id) as user_count 
            FROM roles r 
            JOIN category c ON r.category_id = c.id 
            LEFT JOIN users u ON r.id = u.role_id 
            GROUP BY r.id 
            ORDER BY c.name, r.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching roles: " . $e->getMessage());
        return [];
    }
}

// Get data
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);
$categories = getCategories($pdo);
$allRoles = getAllRoles($pdo);

// Calculate totals
$totalCategories = count($categories);
$totalRoles = array_sum(array_column($categories, 'roles_count'));
$totalUsers = array_sum(array_column($categories, 'users_count'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category & Role Management - LARS Admin Dashboard</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/admin/manage_categories.css">

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
        <div class="welcome-section">
            <h1><i class="fas fa-layer-group"></i> Category & Role Management</h1>
<!--            --><?php //if ($currentUser): ?>
<!--                <p>Managing categories and roles as <strong>--><?php //echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?><!--</strong></p>-->
<!--            --><?php //endif; ?>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Summary Statistics -->
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-bar"></i> Overview</h3>
            <div class="content-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo number_format($totalCategories); ?></h4>
                        <p>Total Categories</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo number_format($totalRoles); ?></h4>
                        <p>Total Roles</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo number_format($totalUsers); ?></h4>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="stat-info">
                        <button class="btn btn-primary" onclick="showAddCategoryModal()">
                            <i class="fas fa-plus"></i> Add Category
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Management -->
        <div class="dashboard-card">
            <h3><i class="fas fa-layer-group"></i> Categories & Institutions</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Roles</th>
                        <th>Total Users</th>
                        <th>Active Users</th>
                        <th>Pending Users</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody id="categoriesTableBody">
                    <?php foreach ($categories as $category): ?>
                        <tr class="category-row" data-category-id="<?php echo $category['id']; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge"><?php echo number_format($category['roles_count']); ?></span>
                                <button class="btn btn-sm btn-info" onclick="viewCategoryRoles(<?php echo $category['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Roles
                                </button>
                            </td>
                            <td><?php echo number_format($category['users_count']); ?></td>
                            <td>
                                <span class="status-badge status-active"><?php echo number_format($category['active_users']); ?></span>
                            </td>
                            <td>
                                <?php if ($category['pending_users'] > 0): ?>
                                    <span class="status-badge status-pending"><?php echo number_format($category['pending_users']); ?></span>
                                <?php else: ?>
                                    <span class="status-badge status-success">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="showAddRoleModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                    <i class="fas fa-plus"></i> Add Role
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewCategoryStats(<?php echo $category['id']; ?>)">
                                    <i class="fas fa-chart-pie"></i>
                                </button>
                                <?php if ($category['users_count'] == 0 && $category['roles_count'] == 0): ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- All Roles Overview -->
        <div class="dashboard-card">
            <h3><i class="fas fa-user-tag"></i> All Roles Overview</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Category</th>
                        <th>Users Assigned</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody id="rolesTableBody">
                    <?php foreach ($allRoles as $role): ?>
                        <tr class="role-row" data-role-id="<?php echo $role['id']; ?>">
                            <td><strong><?php echo htmlspecialchars($role['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($role['category_name']); ?></td>
                            <td>
                                <span class="badge"><?php echo number_format($role['user_count']); ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['name']); ?>', <?php echo $role['category_id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($role['user_count'] == 0): ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Category</h3>
            <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="addCategoryForm">
                <div class="form-group">
                    <label for="categoryName">Category Name <span class="required">*</span></label>
                    <input type="text" id="categoryName" name="name" class="form-control" required>
                    <small>Examples: Government Ministry, Private Company, NGO, Educational Institution</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Category
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Category</h3>
            <span class="close" onclick="closeModal('editCategoryModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editCategoryForm">
                <input type="hidden" id="editCategoryId" name="category_id">
                <div class="form-group">
                    <label for="editCategoryName">Category Name <span class="required">*</span></label>
                    <input type="text" id="editCategoryName" name="name" class="form-control" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Category
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Role Modal -->
<div id="addRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Role</h3>
            <span class="close" onclick="closeModal('addRoleModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="addRoleForm">
                <input type="hidden" id="roleCategoryId" name="category_id">
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" id="roleCategoryName" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="roleName">Role Name <span class="required">*</span></label>
                    <input type="text" id="roleName" name="name" class="form-control" required>
                    <small>Examples: Director, Manager, Officer, Secretary, Administrator</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Role
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addRoleModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="editRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Role</h3>
            <span class="close" onclick="closeModal('editRoleModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editRoleForm">
                <input type="hidden" id="editRoleId" name="role_id">
                <div class="form-group">
                    <label for="editRoleCategoryId">Category <span class="required">*</span></label>
                    <select id="editRoleCategoryId" name="category_id" class="form-control" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editRoleName">Role Name <span class="required">*</span></label>
                    <input type="text" id="editRoleName" name="name" class="form-control" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Role
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editRoleModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Category Stats Modal -->
<div id="categoryStatsModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="statsModalTitle">Category Statistics</h3>
            <span class="close" onclick="closeModal('categoryStatsModal')">&times;</span>
        </div>
        <div id="categoryStatsBody" class="modal-body">
            <!-- Stats will be loaded here -->
        </div>
    </div>
</div>

<!-- Category Roles Modal -->
<div id="categoryRolesModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="rolesModalTitle">Category Roles</h3>
            <span class="close" onclick="closeModal('categoryRolesModal')">&times;</span>
        </div>
        <div id="categoryRolesBody" class="modal-body">
            <!-- Roles will be loaded here -->
        </div>
    </div>
</div>

<!-- External JavaScript -->
<script src="../../../../assets/js/admin-manage-categories.js"></script>

</body>
</html>