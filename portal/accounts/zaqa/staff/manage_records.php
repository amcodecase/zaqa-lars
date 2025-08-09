<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/wamp64/www/lars/php_errors.log');
error_reporting(E_ALL);
ob_start();

require '../../../../dbconnect.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? '';

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        ob_end_flush();
        exit;
    }

    switch ($action) {
        case 'search_all_records':
            $searchType = $_POST['search_type'] ?? '';
            $searchValue = trim($_POST['search_value'] ?? '');
            $limit = min(max((int)($_POST['limit'] ?? 25), 1), 100);
            $tables = isset($_POST['tables']) ? (array)$_POST['tables'] : ['he_data', 'ecz_data', 'teveta_data'];

            if (empty($searchType) || empty($searchValue)) {
                echo json_encode(['success' => false, 'message' => 'Search type and value are required']);
                exit;
            }

            try {
                $records = searchAllRecords($pdo, $searchType, $searchValue, $limit, $tables);
                echo json_encode(['success' => true, 'records' => $records]);
            } catch (Exception $e) {
                error_log("Search error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Search error: ' . $e->getMessage()]);
            }
            exit;

        case 'get_record_details':
            $table = $_POST['table'] ?? '';
            $recordId = (int)($_POST['record_id'] ?? 0);

            if (!isValidTable($table) || $recordId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }

            try {
                $record = getRecordDetails($pdo, $table, $recordId);
                if ($record) {
                    echo json_encode(['success' => true, 'record' => $record, 'table' => $table]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Record not found']);
                }
            } catch (Exception $e) {
                error_log("Record details error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error fetching record: ' . $e->getMessage()]);
            }
            exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    ob_end_flush();
    exit;
}

function isValidTable($table) {
    return in_array($table, ['he_data', 'ecz_data', 'teveta_data']);
}

function tableExists($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Table check error for {$table}: " . $e->getMessage());
        return false;
    }
}

function searchAllRecords($pdo, $searchType, $searchValue, $limit = 25, $tables = ['he_data', 'ecz_data', 'teveta_data']) {
    $validSearchTypes = ['certificate_no', 'nrc_number', 'passport_no', 'name', 'candidate_id', 'student_id', 'programme'];
    if (!in_array($searchType, $validSearchTypes)) {
        throw new Exception('Invalid search type');
    }

    $allRecords = [];
    $searchPattern = '%' . htmlspecialchars(strip_tags($searchValue)) . '%';

    foreach ($tables as $table) {
        if (!isValidTable($table) || !tableExists($pdo, $table)) {
            error_log("Skipping table {$table}: Invalid or does not exist");
            continue;
        }
        try {
            $records = searchSingleTable($pdo, $table, $searchType, $searchPattern, $limit);
            foreach ($records as &$record) {
                $record['source_table'] = $table;
                $record['source_name'] = getTableDisplayName($table);
            }
            $allRecords = array_merge($allRecords, $records);
        } catch (Exception $e) {
            error_log("Error searching table {$table}: " . $e->getMessage());
        }
    }

    usort($allRecords, function($a, $b) {
        $timeA = strtotime($a['created_at'] ?? '1970-01-01');
        $timeB = strtotime($b['created_at'] ?? '1970-01-01');
        return $timeB - $timeA;
    });

    return array_slice($allRecords, 0, $limit);
}

function searchSingleTable($pdo, $table, $searchType, $searchPattern, $limit = 25) {
    // Build base query with proper table escaping
    $sql = "SELECT 
                r.*,
                COALESCE(i.name, 'Unknown Institution') as institution_name,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as added_by_name
            FROM `{$table}` r 
            LEFT JOIN institutions i ON r.institution_id = i.id 
            LEFT JOIN users u ON r.added_by = u.id 
            WHERE ";

    $params = [];

    switch ($searchType) {
        case 'name':
            $sql .= "(r.first_name LIKE ? OR r.last_name LIKE ? OR 
                     CONCAT(COALESCE(r.first_name, ''), ' ', COALESCE(r.last_name, ''), ' ', COALESCE(r.other_names, '')) LIKE ?)";
            $params = [$searchPattern, $searchPattern, $searchPattern];
            break;
        case 'programme':
            if ($table === 'he_data') {
                $sql .= "r.programme_of_study LIKE ?";
            } else {
                $sql .= "r.programme LIKE ?";
            }
            $params = [$searchPattern];
            break;
        case 'student_id':
            if ($table !== 'he_data') {
                return [];
            }
            $sql .= "r.student_id LIKE ?";
            $params = [$searchPattern];
            break;
        case 'candidate_id':
            if (!in_array($table, ['teveta_data', 'ecz_data'])) {
                return [];
            }
            $sql .= "r.candidate_id LIKE ?";
            $params = [$searchPattern];
            break;
        default:
            // For other fields like certificate_no, nrc_number, passport_no
            $sql .= "r.`{$searchType}` LIKE ?";
            $params = [$searchPattern];
            break;
    }

    $sql .= " ORDER BY r.created_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTableDisplayName($table) {
    $names = [
        'he_data' => 'Higher Education',
        'ecz_data' => 'ECZ',
        'teveta_data' => 'TEVETA'
    ];
    return $names[$table] ?? ucfirst(str_replace('_', ' ', $table));
}

function getRecordDetails($pdo, $table, $recordId) {
    if (!tableExists($pdo, $table)) {
        error_log("Table {$table} does not exist");
        return false;
    }

    $sql = "SELECT 
                r.*,
                COALESCE(i.name, 'Unknown Institution') as institution_name,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as added_by_name
            FROM `{$table}` r 
            LEFT JOIN institutions i ON r.institution_id = i.id 
            LEFT JOIN users u ON r.added_by = u.id 
            WHERE r.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$recordId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getInstitutions($pdo) {
    if (!tableExists($pdo, 'institutions')) {
        error_log("Table institutions does not exist");
        return [];
    }
    try {
        $stmt = $pdo->query("SELECT id, name FROM institutions ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching institutions: " . $e->getMessage());
        return [];
    }
}

function getSearchStats($pdo) {
    try {
        $stats = [];
        $tables = ['he_data', 'ecz_data', 'teveta_data'];

        foreach ($tables as $table) {
            $key = str_replace('_data', '_total', $table);
            if (!tableExists($pdo, $table)) {
                $stats[$key] = 0;
                continue;
            }
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM `{$table}`");
                $stats[$key] = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                error_log("Error counting records in {$table}: " . $e->getMessage());
                $stats[$key] = 0;
            }
        }
        $stats['total_records'] = array_sum(array_values($stats));
        return $stats;
    } catch (Exception $e) {
        error_log("Error fetching search stats: " . $e->getMessage());
        return ['he_total' => 0, 'ecz_total' => 0, 'teveta_total' => 0, 'total_records' => 0];
    }
}

// Check authentication before proceeding
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit;
}

$institutions = getInstitutions($pdo);
$searchStats = getSearchStats($pdo);
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Search Records - LARS Staff Dashboard</title>
        <link rel="stylesheet" href="../../../../assets/css/root.css">
        <link rel="stylesheet" href="../../../../assets/css/admin-sidebar.css">
        <link rel="stylesheet" href="../../../../assets/css/admin-manage-records.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <link rel="icon" type="image/x-icon" href="../../../../assets/images/zaqa-logo.png">
    </head>
    <body>
    <?php include 'generic/navbar.php'; ?>
    <div class="dashboard-container">
        <?php include 'generic/sidebar.php'; ?>
        <main class="dashboard-content">
            <div class="welcome-section">
                <h1><i class="fas fa-search"></i> Search Educational Records</h1>
                <p>Search across TEVETA, Higher Education, and ECZ databases</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card highlight">
                    <h3><?php echo number_format($searchStats['total_records']); ?></h3>
                    <p><i class="fas fa-database"></i> Total Records</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($searchStats['teveta_total']); ?></h3>
                    <p><i class="fas fa-tools"></i> TEVETA</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($searchStats['he_total']); ?></h3>
                    <p><i class="fas fa-university"></i> Higher Ed</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($searchStats['ecz_total']); ?></h3>
                    <p><i class="fas fa-certificate"></i> ECZ</p>
                </div>
            </div>

            <div id="alertContainer"></div>

            <div class="dashboard-card">
                <h3><i class="fas fa-search"></i> Search Records</h3>
                <form id="searchForm" class="search-form">
                    <div class="search-grid">
                        <div class="form-group">
                            <label for="search_type">Search By</label>
                            <select id="search_type" name="search_type" class="form-control" required>
                                <option value="">Select search type...</option>
                                <option value="name">Student Name</option>
                                <option value="certificate_no">Certificate Number</option>
                                <option value="nrc_number">NRC Number</option>
                                <option value="passport_no">Passport Number</option>
                                <option value="candidate_id">Candidate ID</option>
                                <option value="student_id">Student ID</option>
                                <option value="programme">Programme/Course</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="search_value">Search Term</label>
                            <input type="text" id="search_value" name="search_value" class="form-control"
                                   placeholder="Enter search term..." required>
                        </div>

                        <div class="form-group">
                            <label for="result_limit">Max Results</label>
                            <select id="result_limit" name="limit" class="form-control">
                                <option value="10">10 records</option>
                                <option value="25" selected>25 records</option>
                                <option value="50">50 records</option>
                                <option value="100">100 records</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Search In</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="tables[]" value="teveta_data" checked>
                                    TEVETA
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="tables[]" value="he_data" checked>
                                    Higher Education
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="tables[]" value="ecz_data" checked>
                                    ECZ
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="search-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <button type="button" id="clearSearch" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </form>
            </div>

            <div id="resultsSection" class="dashboard-card" style="display: none;">
                <div class="results-header">
                    <h3 id="resultsTitle"><i class="fas fa-list"></i> Search Results</h3>
                    <div class="results-info">
                        <span id="resultsCount">0 records found</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="resultsTable" class="data-table">
                        <thead>
                        <tr>
                            <th>Source</th>
                            <th>Name</th>
                            <th>ID/Certificate</th>
                            <th>Programme</th>
                            <th>Year</th>
                            <th>Institution</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody id="resultsTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="noResults" class="no-results" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h4>No Records Found</h4>
                    <p>Try different search terms or adjust your filters</p>
                </div>
            </div>

            <div id="loadingIndicator" class="loading-indicator" style="display: none;">
                <div class="loading-spinner"></div>
                <p>Searching records...</p>
            </div>
        </main>
    </div>

    <div id="recordModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Record Details</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                <button type="button" id="screenshotRecordBtn" class="btn btn-info">
                    <i class="fas fa-camera"></i> Take Screenshot
                </button>
            </div>
        </div>
    </div>

    <script>
        window.institutions = <?php echo json_encode($institutions); ?>;
        window.searchStats = <?php echo json_encode($searchStats); ?>;
    </script>
    <script src="../../../../../lars/assets/js/staff-search-all-records.js"></script>
    </body>
    </html>
<?php ob_end_flush(); ?>