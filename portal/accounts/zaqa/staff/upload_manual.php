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
        case 'upload_he_data':
            $studentId = trim($_POST['student_id'] ?? '');
            $certificateNo = trim($_POST['certificate_no'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $otherNames = trim($_POST['other_names'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $nrcNumber = trim($_POST['nrc_number'] ?? '');
            $passportNo = trim($_POST['passport_no'] ?? '');
            $programmeOfStudy = trim($_POST['programme_of_study'] ?? '');
            $yearAwarded = (int)($_POST['year_awarded'] ?? 0);
            $institutionId = (int)($_POST['institution_id'] ?? 0);

            // Validation
            $errors = [];
            if (empty($studentId)) $errors[] = 'Student ID is required';
            if (empty($certificateNo)) $errors[] = 'Certificate Number is required';
            if (empty($lastName)) $errors[] = 'Last Name is required';
            if (empty($firstName)) $errors[] = 'First Name is required';
            if (empty($gender) || !in_array($gender, ['Male', 'Female'])) $errors[] = 'Valid Gender is required';
            if (empty($programmeOfStudy)) $errors[] = 'Programme of Study is required';
            if ($yearAwarded < 1950 || $yearAwarded > date('Y')) $errors[] = 'Valid Year Awarded is required';
            if ($institutionId <= 0) $errors[] = 'Valid Institution is required';
            if (empty($nrcNumber) && empty($passportNo)) $errors[] = 'Either NRC Number or Passport Number is required';

            if (!empty($errors)) {
                echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
                exit;
            }

            try {
                // Check if student already exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM he_data WHERE student_id = ? OR certificate_no = ?");
                $checkStmt->execute([$studentId, $certificateNo]);

                if ($checkStmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Student ID or Certificate Number already exists']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO he_data (student_id, certificate_no, last_name, first_name, other_names, 
                                       gender, nrc_number, passport_no, programme_of_study, year_awarded, 
                                       institution_id, added_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $result = $stmt->execute([
                    $studentId, $certificateNo, $lastName, $firstName,
                    $otherNames ?: null, $gender, $nrcNumber ?: null,
                    $passportNo ?: null, $programmeOfStudy, $yearAwarded,
                    $institutionId, $_SESSION['user_id']
                ]);

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Higher Education record added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add record']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;

        case 'upload_ecz_data':
            $candidateId = trim($_POST['candidate_id'] ?? '');
            $certificateNo = trim($_POST['certificate_no'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $otherNames = trim($_POST['other_names'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $nrcNumber = trim($_POST['nrc_number'] ?? '');
            $passportNo = trim($_POST['passport_no'] ?? '');
            $programme = trim($_POST['programme'] ?? '');
            $level = trim($_POST['level'] ?? '');
            $yearAwarded = (int)($_POST['year_awarded'] ?? 0);
            $institutionId = (int)($_POST['institution_id'] ?? 0);

            // Validation
            $errors = [];
            if (empty($candidateId)) $errors[] = 'Candidate ID is required';
            if (empty($certificateNo)) $errors[] = 'Certificate Number is required';
            if (empty($lastName)) $errors[] = 'Last Name is required';
            if (empty($firstName)) $errors[] = 'First Name is required';
            if (empty($gender) || !in_array($gender, ['Male', 'Female'])) $errors[] = 'Valid Gender is required';
            if (empty($programme)) $errors[] = 'Programme is required';
            if ($yearAwarded < 1950 || $yearAwarded > date('Y')) $errors[] = 'Valid Year Awarded is required';
            if ($institutionId <= 0) $errors[] = 'Valid Institution is required';
            if (empty($nrcNumber) && empty($passportNo)) $errors[] = 'Either NRC Number or Passport Number is required';

            if (!empty($errors)) {
                echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
                exit;
            }

            try {
                // Check if candidate already exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ecz_data WHERE candidate_id = ? OR certificate_no = ?");
                $checkStmt->execute([$candidateId, $certificateNo]);

                if ($checkStmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Candidate ID or Certificate Number already exists']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO ecz_data (candidate_id, certificate_no, last_name, first_name, other_names, 
                                        gender, nrc_number, passport_no, programme, level, year_awarded, 
                                        institution_id, added_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $result = $stmt->execute([
                    $candidateId, $certificateNo, $lastName, $firstName,
                    $otherNames ?: null, $gender, $nrcNumber ?: null,
                    $passportNo ?: null, $programme, $level ?: null,
                    $yearAwarded, $institutionId, $_SESSION['user_id']
                ]);

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'ECZ record added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add record']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;

        case 'upload_teveta_data':
            $candidateId = trim($_POST['candidate_id'] ?? '');
            $certificateNo = trim($_POST['certificate_no'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $otherNames = trim($_POST['other_names'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $nrcNumber = trim($_POST['nrc_number'] ?? '');
            $passportNo = trim($_POST['passport_no'] ?? '');
            $programme = trim($_POST['programme'] ?? '');
            $level = trim($_POST['level'] ?? '');
            $tradeCode = trim($_POST['trade_code'] ?? '');
            $yearAwarded = (int)($_POST['year_awarded'] ?? 0);
            $institutionId = (int)($_POST['institution_id'] ?? 0);
            $subInstitution = trim($_POST['sub_institution'] ?? '');

            // Validation
            $errors = [];
            if (empty($candidateId)) $errors[] = 'Candidate ID is required';
            if (empty($certificateNo)) $errors[] = 'Certificate Number is required';
            if (empty($lastName)) $errors[] = 'Last Name is required';
            if (empty($firstName)) $errors[] = 'First Name is required';
            if (empty($gender) || !in_array($gender, ['Male', 'Female'])) $errors[] = 'Valid Gender is required';
            if (empty($programme)) $errors[] = 'Programme is required';
            if ($yearAwarded < 1950 || $yearAwarded > date('Y')) $errors[] = 'Valid Year Awarded is required';
            if ($institutionId <= 0) $errors[] = 'Valid Institution is required';
            if (empty($nrcNumber) && empty($passportNo)) $errors[] = 'Either NRC Number or Passport Number is required';

            if (!empty($errors)) {
                echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
                exit;
            }

            try {
                // Check if candidate already exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM teveta_data WHERE candidate_id = ? OR certificate_no = ?");
                $checkStmt->execute([$candidateId, $certificateNo]);

                if ($checkStmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Candidate ID or Certificate Number already exists']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO teveta_data (candidate_id, certificate_no, last_name, first_name, other_names, 
                                           gender, nrc_number, passport_no, programme, level, trade_code, 
                                           year_awarded, institution_id, sub_institution, added_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $result = $stmt->execute([
                    $candidateId, $certificateNo, $lastName, $firstName,
                    $otherNames ?: null, $gender, $nrcNumber ?: null,
                    $passportNo ?: null, $programme, $level ?: null,
                    $tradeCode ?: null, $yearAwarded, $institutionId,
                    $subInstitution ?: null, $_SESSION['user_id']
                ]);

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'TEVETA record added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add record']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
    <title>Manual Data Upload - LARS Admin Dashboard</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/admin-sidebar.css">
    <link rel="stylesheet" href="../../../../assets/css/admin-upload-manual.css">

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
            <h1><i class="fas fa-upload"></i> Manual Data Upload</h1>
<!--            --><?php //if ($currentUser): ?>
<!--                <p>Upload individual records as <strong>--><?php //echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?><!--</strong></p>-->
<!--            --><?php //endif; ?>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Upload Options -->
        <div class="dashboard-card">
            <h3><i class="fas fa-database"></i> Select Data Type to Upload</h3>
            <div class="upload-options">
                <button class="upload-option-btn" onclick="showUploadForm('he')">
                    <i class="fas fa-graduation-cap"></i>
                    <h4>Higher Education Data</h4>
                    <p>Upload university and college graduate records</p>
                </button>
                <button class="upload-option-btn" onclick="showUploadForm('ecz')">
                    <i class="fas fa-school"></i>
                    <h4>ECZ Data</h4>
                    <p>Upload secondary education examination records</p>
                </button>
                <button class="upload-option-btn" onclick="showUploadForm('teveta')">
                    <i class="fas fa-tools"></i>
                    <h4>TEVETA Data</h4>
                    <p>Upload technical and vocational training records</p>
                </button>
            </div>
        </div>

        <!-- Higher Education Upload Form -->
        <div id="heUploadForm" class="upload-form-container" style="display: none;">
            <div class="dashboard-card">
                <div class="form-header">
                    <h3><i class="fas fa-graduation-cap"></i> Upload Higher Education Record</h3>
                    <button class="btn btn-secondary" onclick="hideUploadForms()">
                        <i class="fas fa-arrow-left"></i> Back to Options
                    </button>
                </div>
                <form id="heDataForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="he_student_id">Student ID <span class="required">*</span></label>
                            <input type="text" id="he_student_id" name="student_id" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="he_certificate_no">Certificate Number <span class="required">*</span></label>
                            <input type="text" id="he_certificate_no" name="certificate_no" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="he_first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="he_first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="he_last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="he_last_name" name="last_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="he_other_names">Other Names</label>
                            <input type="text" id="he_other_names" name="other_names" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="he_gender">Gender <span class="required">*</span></label>
                            <select id="he_gender" name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="he_nrc_number">NRC Number</label>
                            <input type="text" id="he_nrc_number" name="nrc_number" class="form-control" placeholder="e.g., 123456/10/1">
                        </div>
                        <div class="form-group">
                            <label for="he_passport_no">Passport Number</label>
                            <input type="text" id="he_passport_no" name="passport_no" class="form-control">
                            <small>Either NRC or Passport number is required</small>
                        </div>
                        <div class="form-group form-group-full">
                            <label for="he_programme_of_study">Programme of Study <span class="required">*</span></label>
                            <input type="text" id="he_programme_of_study" name="programme_of_study" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="he_year_awarded">Year Awarded <span class="required">*</span></label>
                            <select id="he_year_awarded" name="year_awarded" class="form-control" required>
                                <option value="">Select Year</option>
                                <?php for ($year = date('Y'); $year >= 1950; $year--): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="he_institution_id">Institution <span class="required">*</span></label>
                            <select id="he_institution_id" name="institution_id" class="form-control" required>
                                <option value="">Select Institution</option>
                                <?php foreach ($institutions as $institution): ?>
                                    <option value="<?php echo $institution['id']; ?>"><?php echo htmlspecialchars($institution['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Upload Record
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ECZ Upload Form -->
        <div id="eczUploadForm" class="upload-form-container" style="display: none;">
            <div class="dashboard-card">
                <div class="form-header">
                    <h3><i class="fas fa-school"></i> Upload ECZ Record</h3>
                    <button class="btn btn-secondary" onclick="hideUploadForms()">
                        <i class="fas fa-arrow-left"></i> Back to Options
                    </button>
                </div>
                <form id="eczDataForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="ecz_candidate_id">Candidate ID <span class="required">*</span></label>
                            <input type="text" id="ecz_candidate_id" name="candidate_id" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ecz_certificate_no">Certificate Number <span class="required">*</span></label>
                            <input type="text" id="ecz_certificate_no" name="certificate_no" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ecz_first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="ecz_first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ecz_last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="ecz_last_name" name="last_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ecz_other_names">Other Names</label>
                            <input type="text" id="ecz_other_names" name="other_names" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="ecz_gender">Gender <span class="required">*</span></label>
                            <select id="ecz_gender" name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ecz_nrc_number">NRC Number</label>
                            <input type="text" id="ecz_nrc_number" name="nrc_number" class="form-control" placeholder="e.g., 123456/10/1">
                        </div>
                        <div class="form-group">
                            <label for="ecz_passport_no">Passport Number</label>
                            <input type="text" id="ecz_passport_no" name="passport_no" class="form-control">
                            <small>Either NRC or Passport number is required</small>
                        </div>
                        <div class="form-group">
                            <label for="ecz_programme">Programme <span class="required">*</span></label>
                            <input type="text" id="ecz_programme" name="programme" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ecz_level">Level</label>
                            <select id="ecz_level" name="level" class="form-control">
                                <option value="">Select Level</option>
                                <option value="Grade 7">Grade 7</option>
                                <option value="Grade 9">Grade 9</option>
                                <option value="Grade 12">Grade 12</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ecz_year_awarded">Year Awarded <span class="required">*</span></label>
                            <select id="ecz_year_awarded" name="year_awarded" class="form-control" required>
                                <option value="">Select Year</option>
                                <?php for ($year = date('Y'); $year >= 1950; $year--): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ecz_institution_id">Institution <span class="required">*</span></label>
                            <select id="ecz_institution_id" name="institution_id" class="form-control" required>
                                <option value="">Select Institution</option>
                                <?php foreach ($institutions as $institution): ?>
                                    <option value="<?php echo $institution['id']; ?>"><?php echo htmlspecialchars($institution['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Upload Record
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TEVETA Upload Form -->
        <div id="tevetaUploadForm" class="upload-form-container" style="display: none;">
            <div class="dashboard-card">
                <div class="form-header">
                    <h3><i class="fas fa-tools"></i> Upload TEVETA Record</h3>
                    <button class="btn btn-secondary" onclick="hideUploadForms()">
                        <i class="fas fa-arrow-left"></i> Back to Options
                    </button>
                </div>
                <form id="tevetaDataForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="teveta_candidate_id">Candidate ID <span class="required">*</span></label>
                            <input type="text" id="teveta_candidate_id" name="candidate_id" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="teveta_certificate_no">Certificate Number <span class="required">*</span></label>
                            <input type="text" id="teveta_certificate_no" name="certificate_no" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="teveta_first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="teveta_first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="teveta_last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="teveta_last_name" name="last_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="teveta_other_names">Other Names</label>
                            <input type="text" id="teveta_other_names" name="other_names" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="teveta_gender">Gender <span class="required">*</span></label>
                            <select id="teveta_gender" name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="teveta_nrc_number">NRC Number</label>
                            <input type="text" id="teveta_nrc_number" name="nrc_number" class="form-control" placeholder="e.g., 123456/10/1">
                        </div>
                        <div class="form-group">
                            <label for="teveta_passport_no">Passport Number</label>
                            <input type="text" id="teveta_passport_no" name="passport_no" class="form-control">
                            <small>Either NRC or Passport number is required</small>
                        </div>
                        <div class="form-group">
                            <label for="teveta_programme">Programme <span class="required">*</span></label>
                            <input type="text" id="teveta_programme" name="programme" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="teveta_level">Level</label>
                            <input type="text" id="teveta_level" name="level" class="form-control" placeholder="e.g., Certificate, Diploma">
                        </div>
                        <div class="form-group">
                            <label for="teveta_trade_code">Trade Code</label>
                            <input type="text" id="teveta_trade_code" name="trade_code" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="teveta_year_awarded">Year Awarded <span class="required">*</span></label>
                            <select id="teveta_year_awarded" name="year_awarded" class="form-control" required>
                                <option value="">Select Year</option>
                                <?php for ($year = date('Y'); $year >= 1950; $year--): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="teveta_sub_institution">Sub Institution</label>
                            <input type="text" id="teveta_sub_institution" name="sub_institution" class="form-control" placeholder="Department or Campus">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Upload Record
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- External JavaScript -->
<script src="../../../../assets/js/admin-upload-manual.js"></script>

</body>
</html>