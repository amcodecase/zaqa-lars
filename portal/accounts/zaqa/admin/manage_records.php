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
        case 'search_records':
            $table = $_POST['table'] ?? '';
            $searchType = $_POST['search_type'] ?? '';
            $searchValue = trim($_POST['search_value'] ?? '');
            $limit = min((int)($_POST['limit'] ?? 50), 100); // Max 100 records

            if (empty($table) || !in_array($table, ['he_data', 'ecz_data', 'teveta_data'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid table selection']);
                exit;
            }

            if (empty($searchType) || empty($searchValue)) {
                echo json_encode(['success' => false, 'message' => 'Search type and value are required']);
                exit;
            }

            try {
                $records = searchRecords($pdo, $table, $searchType, $searchValue, $limit);
                echo json_encode(['success' => true, 'records' => $records, 'table' => $table]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Search error: ' . $e->getMessage()]);
            }
            exit;

        case 'get_record_details':
            $table = $_POST['table'] ?? '';
            $recordId = (int)($_POST['record_id'] ?? 0);

            if (empty($table) || !in_array($table, ['he_data', 'ecz_data', 'teveta_data'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid table selection']);
                exit;
            }

            if ($recordId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
                exit;
            }

            try {
                $record = getRecordDetails($pdo, $table, $recordId);
                if ($record) {
                    echo json_encode(['success' => true, 'record' => $record]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Record not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching record: ' . $e->getMessage()]);
            }
            exit;

        case 'update_record':
            $table = $_POST['table'] ?? '';
            $recordId = (int)($_POST['record_id'] ?? 0);

            if (empty($table) || !in_array($table, ['he_data', 'ecz_data', 'teveta_data'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid table selection']);
                exit;
            }

            if ($recordId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
                exit;
            }

            try {
                $result = updateRecord($pdo, $table, $recordId, $_POST);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update record']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Update error: ' . $e->getMessage()]);
            }
            exit;

        case 'delete_record':
            $table = $_POST['table'] ?? '';
            $recordId = (int)($_POST['record_id'] ?? 0);

            if (empty($table) || !in_array($table, ['he_data', 'ecz_data', 'teveta_data'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid table selection']);
                exit;
            }

            if ($recordId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
                exit;
            }

            try {
                $result = deleteRecord($pdo, $table, $recordId);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete record']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Delete error: ' . $e->getMessage()]);
            }
            exit;
    }
}

function searchRecords($pdo, $table, $searchType, $searchValue, $limit = 50) {
    $validSearchTypes = [
        'certificate_no' => 'certificate_no',
        'nrc_number' => 'nrc_number',
        'passport_no' => 'passport_no',
        'name' => 'name', // Special case - searches both first_name and last_name
    ];

    if ($table === 'he_data') {
        $validSearchTypes['student_id'] = 'student_id';
    } else {
        $validSearchTypes['candidate_id'] = 'candidate_id';
    }

    if (!isset($validSearchTypes[$searchType])) {
        throw new Exception('Invalid search type');
    }

    $sql = "SELECT r.*, i.name as institution_name, u.first_name as added_by_name, u.last_name as added_by_lastname 
            FROM {$table} r 
            JOIN institutions i ON r.institution_id = i.id 
            JOIN users u ON r.added_by = u.id 
            WHERE ";

    $params = [];

    if ($searchType === 'name') {
        $sql .= "(r.first_name LIKE ? OR r.last_name LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
        $searchPattern = '%' . $searchValue . '%';
        $params = [$searchPattern, $searchPattern, $searchPattern];
    } else {
        $sql .= "r.{$validSearchTypes[$searchType]} LIKE ?";
        $params = ['%' . $searchValue . '%'];
    }

    $sql .= " ORDER BY r.created_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecordDetails($pdo, $table, $recordId) {
    $sql = "SELECT r.*, i.name as institution_name, u.first_name as added_by_name, u.last_name as added_by_lastname 
            FROM {$table} r 
            JOIN institutions i ON r.institution_id = i.id 
            JOIN users u ON r.added_by = u.id 
            WHERE r.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recordId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateRecord($pdo, $table, $recordId, $data) {
    // Define updateable fields for each table
    $fields = [];

    switch ($table) {
        case 'he_data':
            $fields = [
                'student_id', 'certificate_no', 'last_name', 'first_name', 'other_names',
                'gender', 'nrc_number', 'passport_no', 'programme_of_study', 'year_awarded', 'institution_id'
            ];
            break;
        case 'ecz_data':
            $fields = [
                'candidate_id', 'certificate_no', 'last_name', 'first_name', 'other_names',
                'gender', 'nrc_number', 'passport_no', 'programme', 'level', 'year_awarded', 'institution_id'
            ];
            break;
        case 'teveta_data':
            $fields = [
                'candidate_id', 'certificate_no', 'last_name', 'first_name', 'other_names',
                'gender', 'nrc_number', 'passport_no', 'programme', 'level', 'trade_code',
                'year_awarded', 'institution_id', 'sub_institution'
            ];
            break;
    }

    $updateFields = [];
    $params = [];

    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "{$field} = ?";
            $params[] = $data[$field] ?: null;
        }
    }

    if (empty($updateFields)) {
        return false;
    }

    $params[] = $recordId;

    $sql = "UPDATE {$table} SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute($params);
}

function deleteRecord($pdo, $table, $recordId) {
    $sql = "DELETE FROM {$table} WHERE id = ?";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([$recordId]);
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

function getInstitutions($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM institutions ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching institutions: " . $e->getMessage());
        return [];
    }
}

// Get data
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);
$institutions = getInstitutions($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Records - LARS Admin Dashboard</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/admin/manage_records.css">

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
            <h1><i class="fas fa-database"></i> Manage Records</h1>
            <p>Search, view, edit, and manage educational records</p>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Search and Filter Section -->
        <div class="dashboard-card">
            <h3><i class="fas fa-search"></i> Search Records</h3>
            <form id="searchForm" class="search-form">
                <div class="search-grid">
                    <div class="form-group">
                        <label for="table_select">Data Type <span class="required">*</span></label>
                        <select id="table_select" name="table" class="form-control" required>
                            <option value="">Select Data Type</option>
                            <option value="he_data">Higher Education Data</option>
                            <option value="ecz_data">ECZ Data</option>
                            <option value="teveta_data">TEVETA Data</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="search_type">Search By <span class="required">*</span></label>
                        <select id="search_type" name="search_type" class="form-control" required>
                            <option value="">Select Search Type</option>
                            <option value="certificate_no">Certificate Number</option>
                            <option value="nrc_number">NRC Number</option>
                            <option value="passport_no">Passport Number</option>
                            <option value="name">Name (First/Last)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="search_value">Search Value <span class="required">*</span></label>
                        <input type="text" id="search_value" name="search_value" class="form-control"
                               placeholder="Enter search value..." required>
                    </div>

                    <div class="form-group">
                        <label for="result_limit">Results Limit</label>
                        <select id="result_limit" name="limit" class="form-control">
                            <option value="25">25 Records</option>
                            <option value="50" selected>50 Records</option>
                            <option value="100">100 Records</option>
                        </select>
                    </div>
                </div>

                <div class="search-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search Records
                    </button>
                    <button type="button" id="clearSearch" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div id="resultsSection" class="dashboard-card" style="display: none;">
            <div class="results-header">
                <h3 id="resultsTitle"><i class="fas fa-list"></i> Search Results</h3>
                <div class="results-info">
                    <span id="resultsCount">0 records found</span>
                </div>
            </div>

            <div class="table-responsive">
                <table id="resultsTable" class="data-table">
                    <thead id="resultsTableHead">
                        <!-- Dynamic headers will be inserted here -->
                    </thead>
                    <tbody id="resultsTableBody">
                        <!-- Dynamic data will be inserted here -->
                    </tbody>
                </table>
            </div>

            <div id="noResults" class="no-results" style="display: none;">
                <i class="fas fa-search"></i>
                <h4>No Records Found</h4>
                <p>Try adjusting your search criteria</p>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="loading-indicator" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Searching records...</p>
        </div>
    </main>
</div>

<!-- Record Details Modal -->
<div id="recordModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Record Details</h3>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Dynamic content will be inserted here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
            <button type="button" id="editRecordBtn" class="btn btn-primary" style="display: none;">
                <i class="fas fa-edit"></i> Edit Record
            </button>
            <button type="button" id="deleteRecordBtn" class="btn btn-danger" style="display: none;">
                <i class="fas fa-trash"></i> Delete Record
            </button>
        </div>
    </div>
</div>

<!-- Edit Record Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="editModalTitle">Edit Record</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editRecordForm">
                <div id="editFormFields">
                    <!-- Dynamic form fields will be inserted here -->
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            <button type="button" id="saveRecordBtn" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- Hidden data for institutions -->
<script>
    window.institutions = <?php echo json_encode($institutions); ?>;
</script>

<!-- External JavaScript -->
<script src="../../../../assets/js/admin-manage-records.js"></script>

</body>
</html>