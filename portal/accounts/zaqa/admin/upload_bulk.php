<?php
ob_start();
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

    try {
        switch ($action) {
            case 'upload_bulk_he_data':
                handleBulkUpload($pdo, 'he_data', 'Higher Education', [
                    'student_id', 'certificate_no', 'last_name', 'first_name', 'other_names',
                    'gender', 'nrc_number', 'passport_no', 'programme_of_study', 'year_awarded'
                ]);
                break;

            case 'upload_bulk_ecz_data':
                handleBulkUpload($pdo, 'ecz_data', 'ECZ', [
                    'candidate_id', 'certificate_no', 'last_name', 'first_name', 'other_names',
                    'gender', 'nrc_number', 'passport_no', 'programme', 'level', 'year_awarded'
                ]);
                break;

            case 'upload_bulk_teveta_data':
                handleBulkUpload($pdo, 'teveta_data', 'TEVETA', [
                    'candidate_id', 'certificate_no', 'last_name', 'first_name', 'other_names',
                    'gender', 'nrc_number', 'passport_no', 'programme', 'level', 'trade_code',
                    'year_awarded', 'sub_institution'
                ]);
                break;

            case 'get_institutions':
                echo json_encode([
                    'success' => true,
                    'institutions' => getInstitutions($pdo)
                ], JSON_UNESCAPED_UNICODE);
                exit;
                break;

            default:
                throw new Exception('Invalid action specified');
        }
    } catch (Exception $e) {
        // Clean any output buffer to prevent HTML mixing with JSON
        if (ob_get_level()) {
            ob_clean();
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_details' => [
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function handleBulkUpload($pdo, $tableName, $dataType, $expectedColumns) {
    // Clean output buffer to prevent HTML/PHP errors from mixing with JSON
    if (ob_get_level()) {
        ob_clean();
    }

    // Disable error display to prevent HTML output
    ini_set('display_errors', 0);
    error_reporting(0);

    try {
        // Validate file upload
        $fileValidation = validateFileUpload();
        if (!$fileValidation['valid']) {
            throw new Exception($fileValidation['message']);
        }

        // Validate institution selection
        $institutionValidation = validateInstitution($pdo, $dataType);
        if (!$institutionValidation['valid']) {
            throw new Exception($institutionValidation['message']);
        }

        $institution = $institutionValidation['institution'];
        $uploadedFile = $_FILES['excel_file'];

        // Process Excel file
        $excelData = processExcelFile($uploadedFile, $expectedColumns);

        // Process data rows with transaction
        $results = processDataRows($pdo, $tableName, $dataType, $expectedColumns, $excelData, $institution['id']);

        // Return results
        echo json_encode([
            'success' => true,
            'message' => generateSuccessMessage($results, $institution['name']),
            'details' => [
                'success_count' => $results['successCount'],
                'error_count' => $results['errorCount'],
                'duplicate_count' => $results['duplicateCount'],
                'institution' => $institution['name'],
                'category' => $institution['type'],
                'errors' => array_slice($results['errors'], 0, 50),
                'total_processed' => $results['totalProcessed']
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Exception $e) {
        // Clean any output buffer to prevent HTML mixing with JSON
        if (ob_get_level()) {
            ob_clean();
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function validateFileUpload() {
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'No file uploaded or upload error occurred';
        if (isset($_FILES['excel_file'])) {
            $errorMsg .= ' (Error code: ' . $_FILES['excel_file']['error'] . ')';
        }
        return ['valid' => false, 'message' => $errorMsg];
    }

    $uploadedFile = $_FILES['excel_file'];
    $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

    // Check file extension
    if (!in_array($fileExtension, ['xlsx', 'xls'])) {
        return ['valid' => false, 'message' => 'Please upload an Excel file (.xlsx or .xls)'];
    }

    // Check file size (50MB limit)
    if ($uploadedFile['size'] > 50 * 1024 * 1024) {
        return ['valid' => false, 'message' => 'File size exceeds 50MB limit'];
    }

    return ['valid' => true];
}

function validateInstitution($pdo, $dataType) {
    if (!isset($_POST['institution_id']) || empty($_POST['institution_id'])) {
        return ['valid' => false, 'message' => 'Please select an institution'];
    }

    $institutionId = (int)$_POST['institution_id'];

    // Verify institution exists and get details
    $stmt = $pdo->prepare("SELECT id, name, type FROM institutions WHERE id = ?");
    $stmt->execute([$institutionId]);
    $institution = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$institution) {
        return ['valid' => false, 'message' => 'Selected institution not found'];
    }

    // Validate institution category matches data type
    $validCategories = [
        'Higher Education' => ['University', 'College'],
        'ECZ' => ['University', 'College', 'Institute'],
        'TEVETA' => ['Institute']
    ];

    if (!in_array($institution['type'], $validCategories[$dataType])) {
        return [
            'valid' => false,
            'message' => "Selected institution type ({$institution['type']}) is not valid for {$dataType} data"
        ];
    }

    return ['valid' => true, 'institution' => $institution];
}

function processExcelFile($uploadedFile, $expectedColumns) {
    require_once '../../../../vendor/autoload.php';

    try {
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader(ucfirst($fileExtension));
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($uploadedFile['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
    } catch (Exception $e) {
        throw new Exception('Error reading Excel file: ' . $e->getMessage());
    }

    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    // Check row limit
    if ($highestRow > 10001) {
        throw new Exception('File contains ' . ($highestRow - 1) . ' rows. Maximum allowed is 10,000 rows.');
    }

    // Check column count
    if ($highestColumnIndex < count($expectedColumns)) {
        throw new Exception('File does not have enough columns. Expected at least ' . count($expectedColumns) . ' columns, found ' . $highestColumnIndex);
    }

    return [
        'worksheet' => $worksheet,
        'highestRow' => $highestRow,
        'columnCount' => $highestColumnIndex
    ];
}

function processDataRows($pdo, $tableName, $dataType, $expectedColumns, $excelData, $institutionId) {
    $worksheet = $excelData['worksheet'];
    $highestRow = $excelData['highestRow'];

    $successCount = 0;
    $errorCount = 0;
    $duplicateCount = 0;
    $errors = [];
    $totalProcessed = 0;

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Process data rows (skip header row 1, start from row 2)
        for ($row = 2; $row <= $highestRow; $row++) {
            $totalProcessed++;
            $rowData = [];

            // Read data from Excel row - FIXED METHOD
            for ($col = 1; $col <= count($expectedColumns); $col++) {
                // Convert column index to letter coordinate
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $cellCoordinate = $columnLetter . $row;

                // Get cell value using proper method
                $cellValue = $worksheet->getCell($cellCoordinate)->getFormattedValue();
                $rowData[] = trim($cellValue ?? '');
            }

            // Pad array if needed
            while (count($rowData) < count($expectedColumns)) {
                $rowData[] = '';
            }

            // Create associative array mapping expected columns to values
            $columnMapping = array_combine($expectedColumns, $rowData);

            // Validate row data
            $validation = validateRowData($columnMapping, $dataType, $row);
            if (!$validation['valid']) {
                $errors[] = "Row $row: " . implode(', ', $validation['errors']);
                $errorCount++;
                continue;
            }

            // Check for duplicates
//            $duplicateCheck = checkForDuplicates($pdo, $tableName, $columnMapping, $dataType);
//            if ($duplicateCheck['isDuplicate']) {
//                $duplicateCount++;
//                $errors[] = "Row $row: " . $duplicateCheck['message'];
//                continue;
//            }

            // Insert record with institution_id and added_by
            if (insertRecord($pdo, $tableName, $columnMapping, $institutionId, $_SESSION['user_id'])) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Row $row: Failed to insert record";
            }
        }

        // Commit transaction
        $pdo->commit();

        return [
            'successCount' => $successCount,
            'errorCount' => $errorCount,
            'duplicateCount' => $duplicateCount,
            'errors' => $errors,
            'totalProcessed' => $totalProcessed
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Transaction failed: ' . $e->getMessage());
    }
}

function validateRowData($columnMapping, $dataType, $rowNumber) {
    $errors = [];

    // Data type specific validations
    switch ($dataType) {
        case 'Higher Education':
            if (empty($columnMapping['student_id'])) $errors[] = 'Student ID is required';
            if (empty($columnMapping['certificate_no'])) $errors[] = 'Certificate Number is required';
            if (empty($columnMapping['programme_of_study'])) $errors[] = 'Programme of Study is required';
            break;
        case 'ECZ':
            if (empty($columnMapping['candidate_id'])) $errors[] = 'Candidate ID is required';
            if (empty($columnMapping['certificate_no'])) $errors[] = 'Certificate Number is required';
            if (empty($columnMapping['programme'])) $errors[] = 'Programme is required';
            break;
        case 'TEVETA':
            if (empty($columnMapping['candidate_id'])) $errors[] = 'Candidate ID is required';
            if (empty($columnMapping['certificate_no'])) $errors[] = 'Certificate Number is required';
            if (empty($columnMapping['programme'])) $errors[] = 'Programme is required';
            break;
    }

    // Common required fields
    if (empty($columnMapping['last_name'])) $errors[] = 'Last Name is required';
    if (empty($columnMapping['first_name'])) $errors[] = 'First Name is required';

    // Gender validation - only validate if provided
    if (!empty($columnMapping['gender'])) {
        $normalizedGender = ucfirst(strtolower(trim($columnMapping['gender'])));
        if (!in_array($normalizedGender, ['Male', 'Female'])) {
            $errors[] = 'Gender must be either "Male" or "Female"';
        }
    }

    // Year validation - only validate if provided
    if (!empty($columnMapping['year_awarded'])) {
        $yearAwarded = trim($columnMapping['year_awarded']);
        if (!ctype_digit($yearAwarded)) {
            $errors[] = 'Year Awarded must be a numeric value';
        } else {
            $yearAwarded = (int)$yearAwarded;
            if ($yearAwarded < 1900 || $yearAwarded > (date('Y') + 10)) {
                $errors[] = 'Year Awarded must be a valid year between 1900 and ' . (date('Y') + 10);
            }
        }
    }

    // Either NRC or Passport required
    if (empty($columnMapping['nrc_number']) && empty($columnMapping['passport_no'])) {
        $errors[] = 'Either NRC Number or Passport Number is required';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

function checkForDuplicates($pdo, $tableName, $columnMapping, $dataType) {
    try {
        switch ($dataType) {
            case 'Higher Education':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tableName` WHERE student_id = ? OR certificate_no = ?");
                $stmt->execute([$columnMapping['student_id'], $columnMapping['certificate_no']]);
                break;
            case 'ECZ':
            case 'TEVETA':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tableName` WHERE candidate_id = ? OR certificate_no = ?");
                $stmt->execute([$columnMapping['candidate_id'], $columnMapping['certificate_no']]);
                break;
        }

        if ($stmt->fetchColumn() > 0) {
            $idField = $dataType === 'Higher Education' ? 'Student ID' : 'Candidate ID';
            return [
                'isDuplicate' => true,
                'message' => "$idField or Certificate Number already exists"
            ];
        }
    } catch (PDOException $e) {
        return [
            'isDuplicate' => true,
            'message' => 'Error checking for duplicates: ' . $e->getMessage()
        ];
    }

    return ['isDuplicate' => false];
}

function insertRecord($pdo, $tableName, $columnMapping, $institutionId, $userId) {
    try {
        // Define the exact database column order for each table
        $tableColumnMappings = [
            'he_data' => [
                'student_id', 'certificate_no', 'last_name', 'first_name', 'other_names',
                'gender', 'nrc_number', 'passport_no', 'programme_of_study', 'year_awarded',
                'institution_id', 'added_by'
            ],
            'ecz_data' => [
                'candidate_id', 'certificate_no', 'last_name', 'first_name', 'other_names',
                'gender', 'nrc_number', 'passport_no', 'programme', 'level', 'year_awarded',
                'institution_id', 'added_by'
            ],
            'teveta_data' => [
                'candidate_id', 'certificate_no', 'last_name', 'first_name', 'other_names',
                'gender', 'nrc_number', 'passport_no', 'programme', 'level', 'trade_code',
                'year_awarded', 'sub_institution', 'institution_id', 'added_by'
            ]
        ];

        if (!isset($tableColumnMappings[$tableName])) {
            throw new Exception("Unknown table: $tableName");
        }

        $dbColumns = $tableColumnMappings[$tableName];
        $values = [];

        // Map values to database columns in correct order
        foreach ($dbColumns as $dbColumn) {
            if ($dbColumn === 'institution_id') {
                $values[] = $institutionId;
            } elseif ($dbColumn === 'added_by') {
                $values[] = $userId;
            } else {
                $value = $columnMapping[$dbColumn] ?? '';

                // Handle special processing for specific fields
                if ($dbColumn === 'gender' && !empty($value)) {
                    $value = ucfirst(strtolower(trim($value)));
                } elseif ($dbColumn === 'year_awarded' && !empty($value)) {
                    $value = (int)$value;
                } elseif (empty($value)) {
                    // Set NULL for optional fields when empty
                    $optionalFields = ['other_names', 'nrc_number', 'passport_no', 'level', 'trade_code', 'sub_institution', 'gender'];
                    $value = in_array($dbColumn, $optionalFields) ? null : $value;
                }

                $values[] = $value;
            }
        }

        // Create SQL with explicit column names
        $columnList = '`' . implode('`, `', $dbColumns) . '`';
        $placeholders = str_repeat('?,', count($dbColumns) - 1) . '?';
        $sql = "INSERT INTO `$tableName` ($columnList) VALUES ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($values);

        if (!$result) {
            error_log("Insert failed for table $tableName. SQL: $sql. Values: " . json_encode($values));
            return false;
        }

        return true;

    } catch (PDOException $e) {
        error_log("Insert error for table $tableName: " . $e->getMessage());
        error_log("Column mapping: " . json_encode($columnMapping));
        error_log("Values: " . json_encode($values ?? []));
        return false;
    }
}

function generateSuccessMessage($results, $institutionName) {
    $message = "Bulk upload completed for {$institutionName}. ";
    $message .= "Successfully uploaded: {$results['successCount']} records. ";

    if ($results['errorCount'] > 0) {
        $message .= "Errors: {$results['errorCount']}. ";
    }

    if ($results['duplicateCount'] > 0) {
        $message .= "Duplicates skipped: {$results['duplicateCount']}. ";
    }

    return $message;
}

function getCurrentUser($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        return false;
    }
}

function getInstitutions($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, name, type
            FROM institutions 
            WHERE id IS NOT NULL AND name IS NOT NULL AND type IS NOT NULL
            ORDER BY name ASC
        ");

        $institutions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $institutions;

    } catch (PDOException $e) {
        error_log("Error fetching institutions: " . $e->getMessage());
        return [];
    }
}

// Get data for page rendering
$currentUser = getCurrentUser($pdo, $_SESSION['user_id']);
$institutions = getInstitutions($pdo);

// Debug: Log institutions for debugging
error_log("Institutions for page rendering: " . json_encode($institutions));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Data Upload - LARS Admin Dashboard</title>

    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/admin/bulk_upload.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../../assets/images/zaqa-logo.png">
</head>
<body>
<!-- Toast Notification Container -->
<div id="toastContainer" class="toast-container"></div>

<?php include 'generic/navbar.php'; ?>

<div class="dashboard-container">
    <?php include 'generic/sidebar.php'; ?>

    <main class="dashboard-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1><i class="fas fa-file-upload"></i> Bulk Data Upload</h1>
            <p>Upload Excel files containing multiple records for educational institutions</p>
        </div>

        <!-- Upload Options -->
        <div class="dashboard-card">
            <h3><i class="fas fa-database"></i> Select Data Type to Upload</h3>
            <div class="upload-options">
                <button class="upload-option-btn" data-type="he">
                    <i class="fas fa-graduation-cap"></i>
                    <h4>Higher Education Data</h4>
                    <p>Upload university and college graduate records</p>
                    <small>Expected: Student ID, Certificate No, Names, Gender, Programme, Year</small>
                </button>

                <button class="upload-option-btn" data-type="ecz">
                    <i class="fas fa-school"></i>
                    <h4>ECZ Data</h4>
                    <p>Upload secondary education examination records</p>
                    <small>Expected: Candidate ID, Certificate No, Names, Gender, Programme, Level, Year</small>
                </button>

                <button class="upload-option-btn" data-type="teveta">
                    <i class="fas fa-tools"></i>
                    <h4>TEVETA Data</h4>
                    <p>Upload technical and vocational training records</p>
                    <small>Expected: Candidate ID, Certificate No, Names, Programme, Trade Code, Year</small>
                </button>
            </div>
        </div>

        <!-- Higher Education Upload Form -->
        <div id="heUploadForm" class="upload-form-container" style="display: none;">
            <div class="dashboard-card">
                <div class="form-header">
                    <h3><i class="fas fa-graduation-cap"></i> Upload Higher Education Records</h3>
                    <button class="btn btn-secondary back-btn" data-form="he">
                        <i class="fas fa-arrow-left"></i> Back to Options
                    </button>
                </div>

                <div class="upload-info">
                    <h4>Expected Excel Column Order:</h4>
                    <div class="column-list">
                        <span class="column-item">1. Student ID</span>
                        <span class="column-item">2. Certificate No</span>
                        <span class="column-item">3. Last Name</span>
                        <span class="column-item">4. First Name</span>
                        <span class="column-item">5. Other Names</span>
                        <span class="column-item">6. Gender</span>
                        <span class="column-item">7. NRC Number</span>
                        <span class="column-item">8. Passport No</span>
                        <span class="column-item">9. Programme of Study</span>
                        <span class="column-item">10. Year Awarded</span>
                    </div>
                </div>

                <form id="heBulkForm" class="bulk-upload-form" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="he_institution_id">Select Institution <span class="required">*</span></label>
                            <select id="he_institution_id" name="institution_id" class="form-control" required>
                                <option value="">-- Select Institution --</option>
                                <?php
                                if (!empty($institutions)) {
                                    foreach ($institutions as $institution):
                                        if (in_array($institution['type'], ['University', 'College'])): ?>
                                            <option value="<?php echo htmlspecialchars($institution['id']); ?>" data-category="<?php echo htmlspecialchars($institution['type']); ?>">
                                                <?php echo htmlspecialchars($institution['name']); ?> (<?php echo htmlspecialchars($institution['type']); ?>)
                                            </option>
                                        <?php endif;
                                    endforeach;
                                } else {
                                    echo '<option value="">No institutions found</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="he_excel_file">Select Excel File <span class="required">*</span></label>
                            <div class="file-input-wrapper">
                                <input type="file" id="he_excel_file" name="excel_file" class="form-control file-input" accept=".xlsx,.xls" required>
                                <div class="file-input-info">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span class="file-text">Choose Excel file or drag and drop</span>
                                    <small>Max size: 50MB | Formats: .xlsx, .xls</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Records
                        </button>
                        <div class="upload-progress" id="heProgress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <span class="progress-text">Processing...</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ECZ Upload Form -->
        <div id="eczUploadForm" class="upload-form-container" style="display: none;">
            <div class="dashboard-card">
                <div class="form-header">
                    <h3><i class="fas fa-school"></i> Upload ECZ Records</h3>
                    <button class="btn btn-secondary back-btn" data-form="ecz">
                        <i class="fas fa-arrow-left"></i> Back to Options
                    </button>
                </div>

                <div class="upload-info">
                    <h4>Expected Excel Column Order:</h4>
                    <div class="column-list">
                        <span class="column-item">1. Candidate ID</span>
                        <span class="column-item">2. Certificate No</span>
                        <span class="column-item">3. Last Name</span>
                        <span class="column-item">4. First Name</span>
                        <span class="column-item">5. Other Names</span>
                        <span class="column-item">6. Gender</span>
                        <span class="column-item">7. NRC Number</span>
                        <span class="column-item">8. Passport No</span>
                        <span class="column-item">9. Programme</span>
                        <span class="column-item">10. Level</span>
                        <span class="column-item">11. Year Awarded</span>
                    </div>
                </div>

                <form id="eczBulkForm" class="bulk-upload-form" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ecz_institution_id">Select Institution <span class="required">*</span></label>
                            <select id="ecz_institution_id" name="institution_id" class="form-control" required>
                                <option value="">-- Select Institution --</option>
                                <?php
                                if (!empty($institutions)) {
                                    foreach ($institutions as $institution): ?>
                                        <option value="<?php echo htmlspecialchars($institution['id']); ?>" data-category="<?php echo htmlspecialchars($institution['type']); ?>">
                                            <?php echo htmlspecialchars($institution['name']); ?> (<?php echo htmlspecialchars($institution['type']); ?>)
                                        </option>
                                    <?php endforeach;
                                } else {
                                    echo '<option value="">No institutions found</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ecz_excel_file">Select Excel File <span class="required">*</span></label>
                            <div class="file-input-wrapper">
                                <input type="file" id="ecz_excel_file" name="excel_file" class="form-control file-input" accept=".xlsx,.xls" required>
                                <div class="file-input-info">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span class="file-text">Choose Excel file or drag and drop</span>
                                    <small>Max size: 50MB | Formats: .xlsx, .xls</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Records
                        </button>
                        <div class="upload-progress" id="eczProgress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <span class="progress-text">Processing...</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- TEVETA Upload Form -->
        <div id="tevetaUploadForm" class="upload-form-container" style="display: none;">
            <div class="dashboard-card">
                <div class="form-header">
                    <h3><i class="fas fa-tools"></i> Upload TEVETA Records</h3>
                    <button class="btn btn-secondary back-btn" data-form="teveta">
                        <i class="fas fa-arrow-left"></i> Back to Options
                    </button>
                </div>

                <div class="upload-info">
                    <h4>Expected Excel Column Order:</h4>
                    <div class="column-list">
                        <span class="column-item">1. Candidate ID</span>
                        <span class="column-item">2. Certificate No</span>
                        <span class="column-item">3. Last Name</span>
                        <span class="column-item">4. First Name</span>
                        <span class="column-item">5. Other Names</span>
                        <span class="column-item">6. Gender</span>
                        <span class="column-item">7. NRC Number</span>
                        <span class="column-item">8. Passport No</span>
                        <span class="column-item">9. Programme</span>
                        <span class="column-item">10. Level</span>
                        <span class="column-item">11. Trade Code</span>
                        <span class="column-item">12. Year Awarded</span>
                        <span class="column-item">13. Sub Institution</span>
                    </div>
                </div>

                <form id="tevetaBulkForm" class="bulk-upload-form" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="teveta_institution_id">Select Institution <span class="required">*</span></label>
                            <select id="teveta_institution_id" name="institution_id" class="form-control" required>
                                <option value="">-- Select Institution --</option>
                                <?php
                                if (!empty($institutions)) {
                                    foreach ($institutions as $institution):
                                        if ($institution['type'] === 'Institute'): ?>
                                            <option value="<?php echo htmlspecialchars($institution['id']); ?>" data-category="<?php echo htmlspecialchars($institution['type']); ?>">
                                                <?php echo htmlspecialchars($institution['name']); ?> (<?php echo htmlspecialchars($institution['type']); ?>)
                                            </option>
                                        <?php endif;
                                    endforeach;
                                } else {
                                    echo '<option value="">No institutions found</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="teveta_excel_file">Select Excel File <span class="required">*</span></label>
                            <div class="file-input-wrapper">
                                <input type="file" id="teveta_excel_file" name="excel_file" class="form-control file-input" accept=".xlsx,.xls" required>
                                <div class="file-input-info">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span class="file-text">Choose Excel file or drag and drop</span>
                                    <small>Max size: 50MB | Formats: .xlsx, .xls</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Records
                        </button>
                        <div class="upload-progress" id="tevetaProgress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <span class="progress-text">Processing...</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Upload Results -->
        <div id="uploadResults" class="dashboard-card results-card" style="display: none;">
            <div class="results-header">
                <h3><i class="fas fa-chart-bar"></i> Upload Results</h3>
                <button class="btn btn-secondary" id="clearResults">
                    <i class="fas fa-times"></i> Clear Results
                </button>
            </div>
            <div id="resultsContent" class="results-content"></div>
        </div>

        <!-- Instructions Card -->
        <div class="dashboard-card">
            <h3><i class="fas fa-info-circle"></i> Upload Instructions</h3>
            <div class="instructions-content">
                <div class="instruction-item">
                    <i class="fas fa-file-excel"></i>
                    <div>
                        <h4>File Format</h4>
                        <p>Upload Excel files (.xlsx or .xls) with data starting from row 2 (row 1 should contain headers)</p>
                    </div>
                </div>

                <div class="instruction-item">
                    <i class="fas fa-list-ol"></i>
                    <div>
                        <h4>Column Order</h4>
                        <p>Ensure columns are in the exact order shown for each data type. Empty cells are allowed for optional fields.</p>
                    </div>
                </div>

                <div class="instruction-item">
                    <i class="fas fa-users"></i>
                    <div>
                        <h4>Data Validation</h4>
                        <p>Each record must have either NRC Number or Passport Number. Gender should be "Male" or "Female".</p>
                    </div>
                </div>

                <div class="instruction-item">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h4>Duplicate Prevention</h4>
                        <p>System automatically checks for existing records based on ID numbers and certificate numbers.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Information (Remove in production) -->
        <?php if (isset($_GET['debug'])): ?>
            <div class="dashboard-card">
                <h3>Debug Information</h3>
                <pre><?php
                    echo "Institutions count: " . count($institutions) . "\n";
                    echo "Institutions data:\n";
                    print_r($institutions);
                    ?></pre>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- External JavaScript -->
<script>
    // Enhanced JavaScript with better error handling and debugging
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing bulk upload...');

        // Upload option buttons
        const uploadOptionBtns = document.querySelectorAll('.upload-option-btn');
        const uploadForms = document.querySelectorAll('.upload-form-container');
        const backBtns = document.querySelectorAll('.back-btn');

        // Show form based on data type
        uploadOptionBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const dataType = this.dataset.type;
                console.log('Selected data type:', dataType);

                // Hide all forms first
                uploadForms.forEach(form => form.style.display = 'none');

                // Show selected form
                const targetForm = document.getElementById(dataType + 'UploadForm');
                if (targetForm) {
                    targetForm.style.display = 'block';
                    console.log('Showing form:', dataType + 'UploadForm');
                } else {
                    console.error('Form not found:', dataType + 'UploadForm');
                }
            });
        });

        // Back button functionality
        backBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                uploadForms.forEach(form => form.style.display = 'none');
            });
        });

        // Form submissions
        const forms = ['heBulkForm', 'eczBulkForm', 'tevetaBulkForm'];
        const actions = ['upload_bulk_he_data', 'upload_bulk_ecz_data', 'upload_bulk_teveta_data'];

        forms.forEach((formId, index) => {
            const form = document.getElementById(formId);
            const progressDiv = document.getElementById(formId.replace('BulkForm', 'Progress'));

            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    console.log('Form submitted:', formId);

                    const formData = new FormData(this);
                    formData.append('action', actions[index]);

                    // Validate form data
                    const institutionSelect = this.querySelector('select[name="institution_id"]');
                    const fileInput = this.querySelector('input[name="excel_file"]');

                    if (!institutionSelect.value) {
                        showToast('Please select an institution', 'error');
                        return;
                    }

                    if (!fileInput.files.length) {
                        showToast('Please select an Excel file', 'error');
                        return;
                    }

                    // Show progress
                    if (progressDiv) {
                        progressDiv.style.display = 'block';
                    }

                    // Disable submit button
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    }

                    // Submit form
                    fetch(window.location.href + '?ajax=1', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            console.log('Response status:', response.status);
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Response data:', data);

                            if (data.success) {
                                showToast(data.message, 'success');
                                displayUploadResults(data.details);
                                form.reset();
                            } else {
                                showToast(data.message || 'Upload failed', 'error');
                                console.error('Upload error:', data);
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            showToast('An error occurred during upload: ' + error.message, 'error');
                        })
                        .finally(() => {
                            // Hide progress and re-enable button
                            if (progressDiv) {
                                progressDiv.style.display = 'none';
                            }
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Records';
                            }
                        });
                });
            } else {
                console.error('Form not found:', formId);
            }
        });

        // File input change handlers
        document.querySelectorAll('.file-input').forEach(input => {
            input.addEventListener('change', function() {
                const wrapper = this.closest('.file-input-wrapper');
                const infoDiv = wrapper.querySelector('.file-input-info');
                const textSpan = infoDiv.querySelector('.file-text');

                if (this.files.length > 0) {
                    const fileName = this.files[0].name;
                    const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
                    textSpan.textContent = `${fileName} (${fileSize}MB)`;
                    wrapper.classList.add('has-file');
                } else {
                    textSpan.textContent = 'Choose Excel file or drag and drop';
                    wrapper.classList.remove('has-file');
                }
            });
        });

        // Clear results functionality
        const clearResultsBtn = document.getElementById('clearResults');
        if (clearResultsBtn) {
            clearResultsBtn.addEventListener('click', function() {
                const resultsCard = document.getElementById('uploadResults');
                if (resultsCard) {
                    resultsCard.style.display = 'none';
                }
            });
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer') || document.body;
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

            container.appendChild(toast);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        // Display upload results
        function displayUploadResults(details) {
            const resultsCard = document.getElementById('uploadResults');
            const resultsContent = document.getElementById('resultsContent');

            if (!resultsCard || !resultsContent) return;

            let html = `
            <div class="results-summary">
                <div class="result-stat success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <span class="stat-number">${details.success_count}</span>
                        <span class="stat-label">Successful</span>
                    </div>
                </div>

                <div class="result-stat error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <span class="stat-number">${details.error_count}</span>
                        <span class="stat-label">Errors</span>
                    </div>
                </div>

                <div class="result-stat warning">
                    <i class="fas fa-copy"></i>
                    <div>
                        <span class="stat-number">${details.duplicate_count}</span>
                        <span class="stat-label">Duplicates</span>
                    </div>
                </div>

                <div class="result-stat info">
                    <i class="fas fa-list"></i>
                    <div>
                        <span class="stat-number">${details.total_processed}</span>
                        <span class="stat-label">Total Processed</span>
                    </div>
                </div>
            </div>

            <div class="results-details">
                <h4>Upload Details</h4>
                <p><strong>Institution:</strong> ${details.institution}</p>
                <p><strong>Category:</strong> ${details.category}</p>
            </div>
        `;

            if (details.errors && details.errors.length > 0) {
                html += `
                <div class="results-errors">
                    <h4>Error Details (First 50 errors)</h4>
                    <div class="error-list">
                        ${details.errors.map(error => `<div class="error-item">${error}</div>`).join('')}
                    </div>
                </div>
            `;
            }

            resultsContent.innerHTML = html;
            resultsCard.style.display = 'block';

            // Scroll to results
            resultsCard.scrollIntoView({ behavior: 'smooth' });
        }

        // Initialize institution dropdowns debugging
        const institutionSelects = document.querySelectorAll('select[name="institution_id"]');
        institutionSelects.forEach(select => {
            console.log('Institution select found:', select.id, 'Options:', select.options.length);
        });
    });
</script>

<style>
    /* Additional CSS for better functionality */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .toast {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        margin-bottom: 10px;
        min-width: 300px;
        animation: slideIn 0.3s ease-out;
    }

    .toast-success { border-left: 4px solid #28a745; }
    .toast-error { border-left: 4px solid #dc3545; }
    .toast-info { border-left: 4px solid #17a2b8; }

    .toast-content {
        display: flex;
        align-items: center;
        padding: 15px;
        gap: 10px;
    }

    .toast-content i {
        font-size: 18px;
    }

    .toast-success i { color: #28a745; }
    .toast-error i { color: #dc3545; }
    .toast-info i { color: #17a2b8; }

    .toast-close {
        background: none;
        border: none;
        margin-left: auto;
        cursor: pointer;
        color: #6c757d;
        padding: 0;
    }

    .file-input-wrapper.has-file .file-input-info {
        background: #e8f5e8;
        border-color: #28a745;
    }

    .results-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .result-stat {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 15px;
        border-radius: 8px;
        background: #f8f9fa;
    }

    .result-stat.success { background: #d4edda; color: #155724; }
    .result-stat.error { background: #f8d7da; color: #721c24; }
    .result-stat.warning { background: #fff3cd; color: #856404; }
    .result-stat.info { background: #cce7f0; color: #0c5460; }

    .stat-number {
        font-size: 24px;
        font-weight: bold;
        display: block;
    }

    .stat-label {
        font-size: 12px;
        text-transform: uppercase;
    }

    .error-list {
        max-height: 300px;
        overflow-y: auto;
        background: #f8f9fa;
        border-radius: 6px;
        padding: 10px;
    }

    .error-item {
        padding: 5px 0;
        border-bottom: 1px solid #dee2e6;
        font-family: monospace;
        font-size: 12px;
    }

    .error-item:last-child {
        border-bottom: none;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .results-summary {
            grid-template-columns: repeat(2, 1fr);
        }

        .toast {
            min-width: 250px;
        }
    }
</style>

</body>
</html>