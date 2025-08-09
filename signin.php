<?php
/**
 * ============================================================================
 * LEARNER ACHIEVEMENT RECORDS SYSTEM (LARS) - AUTHENTICATION PAGE
 * ============================================================================
 * National education data infrastructure owned and managed by
 * Zambia Qualifications Authority (ZAQA)
 *
 * Combined Sign In, Sign Up, and Password Reset functionality
 * Redirects to: portal/accounts/{category}/{role}/index.php
 *
 * Lead Developer: amcodecase (justus.michelo@zaqa.gov.zm)
 * ============================================================================
 */

session_start();
require "dbconnect.php";

$error = '';
$success = '';
$action = $_GET['action'] ?? 'signin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'signin';

    switch ($action) {
        case 'signin':
            handleSignIn();
            break;
        case 'signup':
            handleSignUp();
            break;
        case 'forgot':
            handleForgotPassword();
            break;
        case 'reset':
            handlePasswordReset();
            break;
    }
}

// Sign In Handler
function handleSignIn() {
    global $pdo, $error;

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.password, c.name as category, r.name as role, u.status, u.email_verified 
            FROM users u 
            JOIN category c ON u.category_id = c.id 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'pending') {
                $error = 'Your account is pending approval. Please wait for administrator approval.';
                return;
            }

            if ($user['status'] === 'rejected') {
                $error = 'Your account has been rejected. Please contact administrator.';
                return;
            }

            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact administrator.';
                return;
            }

            if ($user['status'] !== 'active') {
                $error = 'Account is not active. Please contact administrator.';
                return;
            }

            if (!$user['email_verified']) {
                $error = 'Please verify your email address before signing in.';
                return;
            }

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_category'] = $user['category'];
            $_SESSION['user_role'] = strtolower($user['role']);

            // Redirect to appropriate portal - Convert both category and role to lowercase
            $categoryLower = strtolower($user['category']);
            $roleLower = strtolower($user['role']);
            $redirectUrl = "portal/accounts/{$categoryLower}/{$roleLower}/index.php";
            header("Location: $redirectUrl");
            exit;

        } else {
            $error = 'Invalid email or password';
        }
    } catch (PDOException $e) {
        $error = 'System error. Please try again later.';
        error_log('LARS Sign In Error: ' . $e->getMessage());
    }
}

// Sign Up Handler
function handleSignUp() {
    global $pdo, $error, $success;

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $otherNames = trim($_POST['other_names'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $categoryId = $_POST['category_id'] ?? '';
    $roleId = $_POST['role_id'] ?? '';
    $nrc = trim($_POST['nrc'] ?? '');

    // Auto-format NRC
    if (!empty($nrc)) {
        $nrc = preg_replace('/[^0-9]/', '', $nrc);
        if (strlen($nrc) >= 6) {
            $nrc = substr($nrc, 0, 6) . '/' .
                (strlen($nrc) > 6 ? substr($nrc, 6, 2) : '') .
                (strlen($nrc) > 8 ? '/' . substr($nrc, 8, 1) : '');
        }
    }

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password) || empty($categoryId) || empty($roleId)) {
        $error = 'Please fill in all required fields';
        return;
    }

    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
        return;
    }

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
        return;
    }

    // Phone validation
    if (!preg_match('/^[0-9]{9}$/', $phone)) {
        $error = 'Phone number must be 9 digits (without country code)';
        return;
    }

    $validPrefixes = ['77', '57', '97', '76', '96', '78', '98', '95', '75'];
    $phonePrefix = substr($phone, 0, 2);
    if (!in_array($phonePrefix, $validPrefixes)) {
        $error = 'Invalid phone number prefix. Must start with 77, 57, 97, 76, 96, 78, 98, 95, or 75';
        return;
    }

    // Validate role is not admin
    try {
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE id = ? AND name NOT LIKE '%admin%'");
        $stmt->execute([$roleId]);
        if ($stmt->rowCount() === 0) {
            $error = 'Invalid role selected';
            return;
        }
    } catch (PDOException $e) {
        $error = 'System error. Please try again later.';
        error_log('LARS Role Validation Error: ' . $e->getMessage());
        return;
    }

    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $error = 'Email address already registered';
            return;
        }

        // Check if phone exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->rowCount() > 0) {
            $error = 'Phone number already registered';
            return;
        }

        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, other_names, email, phone, password, 
                             category_id, role_id, nrc, verification_token, status, email_verified, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW())
        ");

        $stmt->execute([
            $firstName, $lastName, $otherNames, $email, $phone, $hashedPassword,
            $categoryId, $roleId, $nrc, $verificationToken
        ]);

        $success = 'Account created successfully! Your account is pending approval. You will receive an email once approved.';

    } catch (PDOException $e) {
        $error = 'Registration failed. Please try again.';
        error_log('LARS Sign Up Error: ' . $e->getMessage());
    }
}

// Forgot Password Handler
function handleForgotPassword() {
    global $pdo, $error, $success;

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address';
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $resetToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("
                UPDATE users SET 
                    reset_token = ?, 
                    reset_token_expiry = ? 
                WHERE email = ?
            ");
            $stmt->execute([$resetToken, $expiry, $email]);

            $success = 'Password reset link has been sent to your email address.';
        } else {
            $success = 'If this email is registered, you will receive a password reset link.';
        }

    } catch (PDOException $e) {
        $error = 'System error. Please try again later.';
        error_log('LARS Forgot Password Error: ' . $e->getMessage());
    }
}

// Password Reset Handler
function handlePasswordReset() {
    global $pdo, $error, $success;

    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($token) || empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
        return;
    }

    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
        return;
    }

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE reset_token = ? AND reset_token_expiry > NOW()
        ");
        $stmt->execute([$token]);

        if ($stmt->rowCount() > 0) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE users SET 
                    password = ?, 
                    reset_token = NULL, 
                    reset_token_expiry = NULL 
                WHERE reset_token = ?
            ");
            $stmt->execute([$hashedPassword, $token]);

            $success = 'Password reset successfully! You can now sign in with your new password.';
        } else {
            $error = 'Invalid or expired reset token';
        }

    } catch (PDOException $e) {
        $error = 'System error. Please try again later.';
        error_log('LARS Password Reset Error: ' . $e->getMessage());
    }
}

// Get categories and roles for dropdowns
function getCategories() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT id, name FROM category ORDER BY name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getRolesByCategory($categoryId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM roles WHERE category_id = ? AND name NOT LIKE '%admin%' ORDER BY name");
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

$categories = getCategories();

// Check for reset token in URL
$resetToken = $_GET['token'] ?? '';
if ($resetToken) {
    $action = 'reset';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LARS - Authentication | Zambia Qualifications Authority</title>
    <meta name="description" content="Learner Achievement Records System - Sign in to access your educational records and qualifications">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/root.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body id="auth-body" class="auth-page">

<!-- Header -->
<header id="auth-header" class="auth-header">
    <div class="auth-header-container">
        <div class="auth-logo">
<!--            <img src="assets/images/zaqa-logo.png" alt="ZAQA Logo" class="logo-image" id="zaqa-logo">-->
            <div class="logo-text">
                <h1 class="system-title">L.A.R.S.</h1>
                <p class="system-subtitle">Learner Achievement Records System</p>
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<main id="auth-main" class="auth-main">
    <div class="auth-container">

        <!-- Authentication Card -->
        <div id="auth-card" class="auth-card">

            <!-- Tab Navigation -->
            <div id="auth-tabs" class="auth-tabs">
                <button type="button" class="auth-tab <?= $action === 'signin' ? 'active' : '' ?>"
                        onclick="switchTab('signin')" id="signin-tab">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
                <button type="button" class="auth-tab <?= $action === 'signup' ? 'active' : '' ?>"
                        onclick="switchTab('signup')" id="signup-tab">
                    <i class="fas fa-user-plus"></i> Sign Up
                </button>
                <button type="button" class="auth-tab <?= $action === 'forgot' ? 'active' : '' ?>"
                        onclick="switchTab('forgot')" id="forgot-tab">
                    <i class="fas fa-key"></i> Reset
                </button>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
                <div id="error-message" class="message error-message">
                    <span class="message-icon"><i class="fas fa-exclamation-circle"></i></span>
                    <span class="message-text"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div id="success-message" class="message success-message">
                    <span class="message-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="message-text"><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- Sign In Form -->
            <form id="signin-form" class="auth-form <?= $action !== 'signin' ? 'hidden' : '' ?>"
                  method="POST" action="">
                <input type="hidden" name="action" value="signin">

                <div class="form-header">
                    <h2 class="form-title">
                        <i class="fas fa-sign-in-alt"></i> Learner Records by
                        <span style="color: #d97706; font-weight: bold;">ZAQA</span>
                    </h2>
                    <p class="form-subtitle">Sign in to access your LARS account</p>
                </div>

                <div class="form-group">
                    <label for="signin-email" class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="signin-email" name="email" class="form-input"
                           placeholder="Enter your email address" required>
                </div>

                <div class="form-group">
                    <label for="signin-password" class="form-label"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="signin-password" name="password" class="form-input"
                           placeholder="Enter your password" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-full" id="signin-btn">
                        <i class="fas fa-sign-in-alt"></i>&nbsp;Sign In
                    </button>
                </div>

                <div class="form-links">
                    <a href="#" onclick="switchTab('forgot')" class="link-secondary">
                        <i class="fas fa-question-circle"></i> Forgot your password?
                    </a>
                </div>
            </form>

            <!-- Sign Up Form -->
            <form id="signup-form" class="auth-form <?= $action !== 'signup' ? 'hidden' : '' ?>"
                  method="POST" action="">
                <input type="hidden" name="action" value="signup">

                <div class="form-header">
                    <h2 class="form-title"><i class="fas fa-user-plus"></i> Create Account</h2>
                    <p class="form-subtitle">Join LARS to manage your educational records</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="signup-firstname" class="form-label"><i class="fas fa-user"></i> First Name *</label>
                        <input type="text" id="signup-firstname" name="first_name" class="form-input"
                               placeholder="First name" required>
                    </div>
                    <div class="form-group">
                        <label for="signup-lastname" class="form-label"><i class="fas fa-user"></i> Last Name *</label>
                        <input type="text" id="signup-lastname" name="last_name" class="form-input"
                               placeholder="Last name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="signup-othernames" class="form-label"><i class="fas fa-user"></i> Other Names</label>
                    <input type="text" id="signup-othernames" name="other_names" class="form-input"
                           placeholder="Other names (optional)">
                </div>

                <div class="form-group">
                    <label for="signup-email" class="form-label"><i class="fas fa-envelope"></i> Email Address *</label>
                    <input type="email" id="signup-email" name="email" class="form-input"
                           placeholder="Enter your email address" required>
                </div>

                <div class="form-group">
                    <label for="signup-phone" class="form-label"><i class="fas fa-phone"></i> Phone Number *</label>
                    <div class="phone-input-container">
                        <span class="country-code">+260</span>
                        <input type="tel" id="signup-phone" name="phone" class="form-input phone-input"
                               placeholder="771234567" required pattern="[0-9]{9}"
                               oninput="detectNetwork(this.value)">
                        <div id="network-logo" class="network-logo"></div>
                        <div id="network-status" class="network-status"></div>
                    </div>
                    <small class="form-hint">Enter 9 digits without country code</small>
                </div>

                <div class="form-group">
                    <label for="signup-nrc" class="form-label"><i class="fas fa-id-card"></i> NRC Number</label>
                    <input type="text" id="signup-nrc" name="nrc" class="form-input"
                           placeholder="e.g., 123456/78/9" maxlength="11"
                           oninput="formatNrc(this)">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="signup-category" class="form-label"><i class="fas fa-list"></i> Category *</label>
                        <select id="signup-category" name="category_id" class="form-select" required onchange="loadRoles(this.value)">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="signup-role" class="form-label"><i class="fas fa-user-tag"></i> Role *</label>
                        <select id="signup-role" name="role_id" class="form-select" required>
                            <option value="">Select Category First</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="signup-password" class="form-label"><i class="fas fa-lock"></i> Password *</label>
                        <input type="password" id="signup-password" name="password" class="form-input"
                               placeholder="Create password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="signup-confirm" class="form-label"><i class="fas fa-lock"></i> Confirm Password *</label>
                        <input type="password" id="signup-confirm" name="confirm_password" class="form-input"
                               placeholder="Confirm password" required minlength="8">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-full" id="signup-btn">
                        <i class="fas fa-user-plus"></i>&nbsp;Create Account
                    </button>
                </div>
            </form>

            <!-- Forgot Password Form -->
            <form id="forgot-form" class="auth-form <?= $action !== 'forgot' ? 'hidden' : '' ?>"
                  method="POST" action="">
                <input type="hidden" name="action" value="forgot">

                <div class="form-header">
                    <h2 class="form-title"><i class="fas fa-key"></i> Reset Password</h2>
                    <p class="form-subtitle">Enter your email to receive a password reset link</p>
                </div>

                <div class="form-group">
                    <label for="forgot-email" class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="forgot-email" name="email" class="form-input"
                           placeholder="Enter your email address" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-full" id="forgot-btn">
                        <i class="fas fa-paper-plane"></i>&nbsp;Send Reset Link
                    </button>
                </div>

                <div class="form-links">
                    <a href="#" onclick="switchTab('signin')" class="link-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Sign In
                    </a>
                </div>
            </form>

            <!-- Password Reset Form -->
            <?php if ($resetToken): ?>
                <form id="reset-form" class="auth-form <?= $action !== 'reset' ? 'hidden' : '' ?>"
                      method="POST" action="">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($resetToken) ?>">

                    <div class="form-header">
                        <h2 class="form-title"><i class="fas fa-key"></i> Set New Password</h2>
                        <p class="form-subtitle">Enter your new password below</p>
                    </div>

                    <div class="form-group">
                        <label for="reset-password" class="form-label"><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" id="reset-password" name="password" class="form-input"
                               placeholder="Enter new password" required minlength="8">
                    </div>

                    <div class="form-group">
                        <label for="reset-confirm" class="form-label"><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" id="reset-confirm" name="confirm_password" class="form-input"
                               placeholder="Confirm new password" required minlength="8">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-full" id="reset-btn">
                            <i class="fas fa-save"></i> Update Password
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>

        <!-- Footer Info -->
        <div id="auth-footer" class="auth-footer">
            <p class="footer-text">
                <i class="fas fa-copyright"></i> <?php echo date('Y');?> ZAQA |
                <a href="#" class="link-secondary"><i class="fas fa-shield-alt"></i> Privacy Policy</a> |
                <a href="#" class="link-secondary"><i class="fas fa-file-contract"></i> Terms of Service</a>
            </p>
        </div>

    </div>
</main>

<!-- JavaScript -->
<script>
    // Tab switching functionality
    function switchTab(tabName) {
        // Hide all forms
        document.getElementById('signin-form').classList.add('hidden');
        document.getElementById('signup-form').classList.add('hidden');
        document.getElementById('forgot-form').classList.add('hidden');
        if (document.getElementById('reset-form')) {
            document.getElementById('reset-form').classList.add('hidden');
        }

        // Remove active class from all tabs
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Show selected form and activate tab
        document.getElementById(tabName + '-form').classList.remove('hidden');
        document.getElementById(tabName + '-tab').classList.add('active');

        // Update URL without reload
        const url = new URL(window.location);
        url.searchParams.set('action', tabName);
        window.history.pushState({}, '', url);
    }

    // Load roles based on category selection
    function loadRoles(categoryId) {
        const roleSelect = document.getElementById('signup-role');
        const loadingAnimation = '<span class="loading-animation"><i class="fas fa-spinner fa-spin"></i> Loading roles...</span>';

        if (!categoryId) {
            roleSelect.innerHTML = '<option value="">Select Category First</option>';
            return;
        }

        // Show loading animation
        roleSelect.innerHTML = loadingAnimation;

        // Fetch roles via AJAX
        fetch('get_roles.php?category_id=' + categoryId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                let options = '<option value="">Select Role</option>';
                data.forEach(role => {
                    // Skip admin roles
                    if (!role.name.toLowerCase().includes('admin')) {
                        options += `<option value="${role.id}">${role.name.toLowerCase()}</option>`;
                    }
                });
                roleSelect.innerHTML = options;
            })
            .catch(error => {
                console.error('Error loading roles:', error);
                roleSelect.innerHTML = '<option value="">Error loading roles</option>';
            });
    }

    // Network detection function with status indicators
    function detectNetwork(phoneNumber) {
        const networkLogo = document.getElementById('network-logo');
        const networkStatus = document.getElementById('network-status');

        if (phoneNumber.length >= 2) {
            const prefix = phoneNumber.substring(0, 2);
            let logoHtml = '';
            let statusHtml = '';
            let networkName = '';

            switch (prefix) {
                case '77':
                case '57':
                case '97':
                    logoHtml = '<i class="fas fa-signal network-icon airtel"></i>';
                    networkName = 'Airtel';
                    break;
                case '76':
                case '96':
                    logoHtml = '<i class="fas fa-signal network-icon mtn"></i>';
                    networkName = 'MTN';
                    break;
                case '78':
                case '98':
                    logoHtml = '<i class="fas fa-signal network-icon zedmobile"></i>';
                    networkName = 'Zed Mobile';
                    break;
                case '95':
                case '75':
                    logoHtml = '<i class="fas fa-signal network-icon zamtel"></i>';
                    networkName = 'Zamtel';
                    break;
                default:
                    logoHtml = '<i class="fas fa-question-circle network-icon unknown"></i>';
                    networkName = 'Unknown';
            }

            networkLogo.innerHTML = logoHtml;

            // Show network status with signal strength animation
            if (networkName !== 'Unknown') {
                statusHtml = `
                    <span class="network-name">${networkName}</span>
                    <span class="signal-strength">
                        <span class="signal-bar"></span>
                        <span class="signal-bar"></span>
                        <span class="signal-bar"></span>
                        <span class="signal-bar"></span>
                    </span>
                `;
            } else {
                statusHtml = '<span class="network-status-error">Invalid prefix</span>';
            }

            networkStatus.innerHTML = statusHtml;
        } else {
            networkLogo.innerHTML = '';
            networkStatus.innerHTML = '';
        }
    }

    // Auto-format NRC number
    function formatNrc(input) {
        let value = input.value.replace(/\D/g, '');

        // Auto-format as XXXXXX/XX/X
        if (value.length > 6) {
            value = value.substring(0, 6) + '/' + value.substring(6);
        }
        if (value.length > 9) {
            value = value.substring(0, 9) + '/' + value.substring(9);
        }
        if (value.length > 11) {
            value = value.substring(0, 11);
        }

        input.value = value;
    }

    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        // Password confirmation validation
        const signupForm = document.getElementById('signup-form');
        const resetForm = document.getElementById('reset-form');

        if (signupForm) {
            signupForm.addEventListener('submit', function(e) {
                const password = document.getElementById('signup-password').value;
                const confirm = document.getElementById('signup-confirm').value;

                if (password !== confirm) {
                    e.preventDefault();
                    showAlert('Passwords do not match', 'error');
                }
            });
        }

        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const password = document.getElementById('reset-password').value;
                const confirm = document.getElementById('reset-confirm').value;

                if (password !== confirm) {
                    e.preventDefault();
                    showAlert('Passwords do not match', 'error');
                }
            });
        }

        // Phone number validation
        const phoneInput = document.getElementById('signup-phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                // Remove any non-digit characters
                let value = e.target.value.replace(/\D/g, '');

                // Limit to 9 digits
                if (value.length > 9) {
                    value = value.substring(0, 9);
                }

                e.target.value = value;
                detectNetwork(value);
            });
        }
    });

    // Show alert message
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i>
            ${message}
        `;

        document.getElementById('auth-card').prepend(alertDiv);

        // Remove alert after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    // Show loading spinner
    function showLoading(element) {
        const spinner = document.createElement('div');
        spinner.className = 'loading-spinner';
        spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        element.appendChild(spinner);
        return spinner;
    }

    // Hide loading spinner
    function hideLoading(spinner) {
        if (spinner) {
            spinner.remove();
        }
    }
</script>

</body>
</html>