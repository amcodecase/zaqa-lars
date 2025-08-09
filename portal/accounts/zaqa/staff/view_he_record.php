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

function searchHeRecords($pdo, $searchTerm, $searchType) {
    $records = [];
    $totalCount = 0;

    try {
        // Build the search query based on search type
        $whereClause = '';
        $searchParam = '';

        switch ($searchType) {
            case 'name':
                $whereClause = "(first_name LIKE ? OR last_name LIKE ? OR other_names LIKE ?)";
                $searchParam = "%{$searchTerm}%";
                break;
            case 'student_id':
                $whereClause = "student_id LIKE ?";
                $searchParam = "%{$searchTerm}%";
                break;
            case 'certificate_no':
                $whereClause = "certificate_no LIKE ?";
                $searchParam = "%{$searchTerm}%";
                break;
            case 'nrc':
                // Only use first 6 digits for NRC
                $nrcSearch = substr(preg_replace('/[^0-9]/', '', $searchTerm), 0, 6);
                if (strlen($nrcSearch) >= 6) {
                    $whereClause = "nrc_number LIKE ?";
                    $searchParam = "{$nrcSearch}%";
                } else {
                    return ['records' => [], 'total' => 0, 'message' => 'NRC must have at least 6 digits'];
                }
                break;
            case 'passport':
                $whereClause = "passport_no LIKE ?";
                $searchParam = "%{$searchTerm}%";
                break;
            default:
                return ['records' => [], 'total' => 0, 'message' => 'Invalid search type'];
        }

        // Get total count first
        $countQuery = "SELECT COUNT(*) as total FROM he_data WHERE {$whereClause}";
        $countStmt = $pdo->prepare($countQuery);

        if ($searchType === 'name') {
            $countStmt->execute([$searchParam, $searchParam, $searchParam]);
        } else {
            $countStmt->execute([$searchParam]);
        }

        $totalCount = (int)$countStmt->fetchColumn();

        // Get the actual records (limited to 500)
        $query = "
            SELECT hd.*, i.name as institution_name 
            FROM he_data hd
            LEFT JOIN institutions i ON hd.institution_id = i.id
            WHERE {$whereClause}
            ORDER BY hd.created_at DESC
            LIMIT 500
        ";

        $stmt = $pdo->prepare($query);

        if ($searchType === 'name') {
            $stmt->execute([$searchParam, $searchParam, $searchParam]);
        } else {
            $stmt->execute([$searchParam]);
        }

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['records' => $records, 'total' => $totalCount];

    } catch (PDOException $e) {
        error_log("Error searching HE records: " . $e->getMessage());
        return ['records' => [], 'total' => 0, 'error' => 'Database error occurred'];
    }
}

// Handle search
$searchResults = [];
$searchPerformed = false;
$searchTerm = '';
$searchType = '';

if (isset($_POST['search']) && !empty($_POST['search_term']) && !empty($_POST['search_type'])) {
    $searchTerm = trim($_POST['search_term']);
    $searchType = $_POST['search_type'];
    $searchResults = searchHeRecords($pdo, $searchTerm, $searchType);
    $searchPerformed = true;
}

// Fetch current user
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);

// Handle AJAX requests
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'results' => $searchResults,
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
    <title>View Higher Education Records - Zambia Qualifications Authority</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/staff/records.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../../assets/images/zaqa-logo.png">

    <!-- HTML2Canvas for screenshots -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

<?php include 'generic/navbar.php'; ?>

<div class="dashboard-container">
    <?php include 'generic/sidebar.php'; ?>

    <main class="dashboard-content">
        <!-- Header Section -->

        <!-- Search Form -->
        <div class="dashboard-card">
            <h3><i class="fas fa-search"></i> Search Higher Education Records</h3>
            <form method="POST" class="search-form">
                <div class="form-group">
                    <label for="search_type">Search By:</label>
                    <select name="search_type" id="search_type" required>
                        <option value="">Select search criteria</option>
                        <option value="name" <?php echo ($searchType === 'name') ? 'selected' : ''; ?>>Name</option>
                        <option value="student_id" <?php echo ($searchType === 'student_id') ? 'selected' : ''; ?>>Student ID</option>
                        <option value="certificate_no" <?php echo ($searchType === 'certificate_no') ? 'selected' : ''; ?>>Certificate Number</option>
                        <option value="nrc" <?php echo ($searchType === 'nrc') ? 'selected' : ''; ?>>NRC (First 6 digits)</option>
                        <option value="passport" <?php echo ($searchType === 'passport') ? 'selected' : ''; ?>>Passport Number</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search_term">Search Term:</label>
                    <input type="text" name="search_term" id="search_term" value="<?php echo htmlspecialchars($searchTerm); ?>" required>
                </div>
                <button type="submit" name="search" class="action-btn he-btn">
                    <i class="fas fa-search"></i> Search Records
                </button>
            </form>
        </div>

        <!-- Search Results -->
        <?php if ($searchPerformed): ?>
            <div class="dashboard-card">
                <h3><i class="fas fa-list"></i> Search Results</h3>

                <?php if (isset($searchResults['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($searchResults['error']); ?>
                    </div>
                <?php elseif (isset($searchResults['message'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        <?php echo htmlspecialchars($searchResults['message']); ?>
                    </div>
                <?php elseif (empty($searchResults['records'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        No records found matching your search criteria.
                    </div>
                <?php else: ?>
                    <div class="results-summary">
                        <p><strong><?php echo count($searchResults['records']); ?></strong> records found
                            <?php if ($searchResults['total'] > 500): ?>
                                (showing first 500 of <?php echo number_format($searchResults['total']); ?> total matches)
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="records-grid">
                        <?php foreach ($searchResults['records'] as $record): ?>
                            <div class="record-card he-record" data-record-id="<?php echo (int)$record['id']; ?>">
                                <div class="record-header">
                                    <h4><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></h4>
                                    <button class="screenshot-btn" onclick="takeScreenshot(this)" title="Save Screenshot">
                                        <i class="fas fa-camera"></i>
                                    </button>
                                </div>

                                <div class="record-details">
                                    <div class="detail-row">
                                        <span class="label">Student ID:</span>
                                        <span class="value"><?php echo htmlspecialchars($record['student_id']); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Certificate No:</span>
                                        <span class="value"><?php echo htmlspecialchars($record['certificate_no']); ?></span>
                                    </div>
                                    <?php if (!empty($record['other_names'])): ?>
                                        <div class="detail-row">
                                            <span class="label">Other Names:</span>
                                            <span class="value"><?php echo htmlspecialchars($record['other_names']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-row">
                                        <span class="label">Gender:</span>
                                        <span class="value"><?php echo htmlspecialchars($record['gender']); ?></span>
                                    </div>
                                    <?php if (!empty($record['nrc_number'])): ?>
                                        <div class="detail-row">
                                            <span class="label">NRC Number:</span>
                                            <span class="value"><?php echo htmlspecialchars($record['nrc_number']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($record['passport_no'])): ?>
                                        <div class="detail-row">
                                            <span class="label">Passport No:</span>
                                            <span class="value"><?php echo htmlspecialchars($record['passport_no']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-row">
                                        <span class="label">Programme:</span>
                                        <span class="value"><?php echo htmlspecialchars($record['programme_of_study']); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Year Awarded:</span>
                                        <span class="value"><?php echo htmlspecialchars($record['year_awarded']); ?></span>
                                    </div>
                                    <?php if (!empty($record['institution_name'])): ?>
                                        <div class="detail-row">
                                            <span class="label">Institution:</span>
                                            <span class="value"><?php echo htmlspecialchars($record['institution_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="record-footer">
                                    <small>Record ID: <?php echo (int)$record['id']; ?> | Added: <?php echo date('d M Y', strtotime($record['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <h3><i class="fas fa-university"></i> Quick Actions</h3>
            <div class="quick-actions">
                <a href="dashboard.php" class="action-btn">
                    <i class="fas fa-dashboard"></i> Back to Dashboard
                </a>
                <a href="view_teveta_records.php" class="action-btn teveta-btn">
                    <i class="fas fa-tools"></i> Search TEVETA Records
                </a>
                <a href="view_ecz_records.php" class="action-btn ecz-btn">
                    <i class="fas fa-certificate"></i> Search ECZ Records
                </a>
            </div>
        </div>
    </main>
</div>

<!-- Loading Indicator -->
<div class="refresh-indicator" id="loadingIndicator" style="display: none;">
    <i class="fas fa-sync-alt fa-spin"></i> Processing...
</div>

<script src="../../../../assets/js/view_he_records.js"></script>

</body>
</html>
