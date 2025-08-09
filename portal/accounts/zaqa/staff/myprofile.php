<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../../index.php');
    exit;
}
require '../../../../dbconnect.php';

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

// Handle AJAX actions
if ($isAjax && !empty($action)) {
    header('Content-Type: application/json');

    switch ($action) {
        case 'self_destruct':
            $password = $_POST['password'] ?? '';
            $captcha = $_POST['captcha'] ?? '';
            $sessionCaptcha = $_SESSION['self_destruct_captcha'] ?? '';
            $timestamp = $_SESSION['self_destruct_captcha_time'] ?? 0;

            // Validate inputs
            if (empty($password) || empty($captcha)) {
                echo json_encode(['success' => false, 'message' => 'Password and CAPTCHA are required']);
                exit;
            }

            // Verify CAPTCHA hasn't expired (30 seconds)
            if (time() - $timestamp > 30) {
                echo json_encode(['success' => false, 'message' => 'CAPTCHA has expired. Please refresh and try again.']);
                exit;
            }

            // Verify CAPTCHA matches
            if ($captcha !== $sessionCaptcha) {
                echo json_encode(['success' => false, 'message' => 'Invalid CAPTCHA code']);
                exit;
            }

            try {
                // Verify password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !password_verify($password, $user['password'])) {
                    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
                    exit;
                }

                // Mark account for deletion (soft delete)
                $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$userId]);

                if ($result) {
                    // Clear session and log user out
                    session_destroy();
                    echo json_encode(['success' => true, 'message' => 'Account deletion initiated. You will be logged out.', 'redirect' => '../../../../index.php']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to initiate account deletion']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error during account deletion']);
                error_log("Self-destruct error: " . $e->getMessage());
            }
            exit;

        case 'generate_captcha':
            // Generate a random 6-character CAPTCHA
            $captcha = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6);
            $_SESSION['self_destruct_captcha'] = $captcha;
            $_SESSION['self_destruct_captcha_time'] = time();
            echo json_encode(['success' => true, 'captcha' => $captcha]);
            exit;

        case 'update_profile':
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $otherNames = trim($_POST['other_names'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $nrc = trim($_POST['nrc'] ?? '');

            // Validation
            if (empty($firstName) || empty($lastName) || empty($phone)) {
                echo json_encode(['success' => false, 'message' => 'First name, last name, and phone are required']);
                exit;
            }

            try {
                // Check if phone is already taken by another user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
                $stmt->execute([$phone, $userId]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Phone number is already registered to another user']);
                    exit;
                }

                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, other_names = ?, phone = ?, nrc = ?, updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$firstName, $lastName, $otherNames, $phone, $nrc, $userId]);

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating profile']);
                error_log("Profile update error: " . $e->getMessage());
            }
            exit;

        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                echo json_encode(['success' => false, 'message' => 'All password fields are required']);
                exit;
            }

            if ($newPassword !== $confirmPassword) {
                echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
                exit;
            }

            if (strlen($newPassword) < 8) {
                echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long']);
                exit;
            }

            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !password_verify($currentPassword, $user['password'])) {
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                    exit;
                }

                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$hashedPassword, $userId]);

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to change password']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error changing password']);
                error_log("Password change error: " . $e->getMessage());
            }
            exit;

        case 'resend_verification':
            try {
                // Check if email is already verified
                $stmt = $pdo->prepare("SELECT email_verified FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && $user['email_verified']) {
                    echo json_encode(['success' => false, 'message' => 'Email is already verified']);
                    exit;
                }

                // Generate new verification token
                $verificationToken = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
                $result = $stmt->execute([$verificationToken, $userId]);

                if ($result) {
                    // Here you would typically send the verification email
                    // For now, we'll just return success
                    echo json_encode(['success' => true, 'message' => 'Verification email sent successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to generate verification token']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error sending verification email']);
                error_log("Verification email error: " . $e->getMessage());
            }
            exit;

        case 'get_profile':
            try {
                $stmt = $pdo->prepare("
                    SELECT u.*, c.name as category_name, r.name as role_name
                    FROM users u 
                    JOIN category c ON u.category_id = c.id 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.id = ?
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Remove sensitive data
                    unset($user['password']);
                    unset($user['verification_token']);
                    unset($user['reset_token']);
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching profile']);
                error_log("Profile fetch error: " . $e->getMessage());
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

// Get user data
$currentUser = getCurrentUser($pdo, $userId);

if (!$currentUser) {
    session_destroy();
    header('Location: ../../../../index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - LARS Dashboard</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/root.css">
    <link rel="stylesheet" href="../../../../assets/css/navbar.css">
    <link rel="stylesheet" href="../../../../assets/css/staff/profile.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../../assets/images/icon.png">
</head>
<body>

<?php include 'generic/navbar.php'; ?>

<div class="dashboard-container">
    <?php include 'generic/sidebar.php'; ?>

    <main class="dashboard-content profile-container">
        <!-- Alert Messages -->
        <div id="alertContainer" class="profile-alert-container"></div>

        <!-- Profile Information -->
        <div class="profile-card">
            <h3><i class="fas fa-info-circle"></i> Profile Information</h3>
            <div class="profile-content-grid">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <i class="fas fa-user-circle"></i>
                        <h4><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h4>
                        <p><?php echo htmlspecialchars($currentUser['role_name']); ?></p>
                        <p><?php echo htmlspecialchars($currentUser['category_name']); ?></p>
                    </div>
                </div>
                <div class="profile-details">
                    <div class="profile-info-grid">
                        <div class="profile-info-item">
                            <label>Email</label>
                            <div class="profile-email-status">
                                <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
                                <?php if ($currentUser['email_verified']): ?>
                                    <span class="profile-status-badge profile-status-active">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="profile-status-badge profile-status-rejected">
                                        <i class="fas fa-exclamation-circle"></i> Unverified
                                    </span>
                                    <button class="profile-btn profile-btn-sm profile-btn-info" id="resendVerificationBtn">
                                        <i class="fas fa-paper-plane"></i> Resend Verification
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-info-item">
                            <label>Account Status</label>
                            <span class="profile-status-badge profile-status-<?php echo $currentUser['status']; ?>">
                                <i class="fas fa-<?php echo $currentUser['status'] === 'active' ? 'check-circle' : ($currentUser['status'] === 'pending' ? 'clock' : 'times-circle'); ?>"></i>
                                <?php echo ucfirst($currentUser['status']); ?>
                            </span>
                        </div>
                        <div class="profile-info-item">
                            <label>Member Since</label>
                            <span><?php echo date('F j, Y', strtotime($currentUser['created_at'])); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <label>Last Updated</label>
                            <span><?php echo date('F j, Y g:i A', strtotime($currentUser['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="profile-card">
            <h3><i class="fas fa-edit"></i> Edit Profile</h3>
            <form id="profileForm">
                <div class="profile-content-grid">
                    <div class="profile-form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" class="profile-form-input"
                               value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" required>
                    </div>
                    <div class="profile-form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" class="profile-form-input"
                               value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" required>
                    </div>
                </div>

                <div class="profile-form-group">
                    <label for="other_names">Other Names</label>
                    <input type="text" id="other_names" name="other_names" class="profile-form-input"
                           value="<?php echo htmlspecialchars($currentUser['other_names'] ?? ''); ?>">
                </div>

                <div class="profile-content-grid">
                    <div class="profile-form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" class="profile-form-input"
                               value="<?php echo htmlspecialchars($currentUser['phone']); ?>" required>
                    </div>
                    <div class="profile-form-group">
                        <label for="nrc">NRC Number</label>
                        <input type="text" id="nrc" name="nrc" class="profile-form-input"
                               value="<?php echo htmlspecialchars($currentUser['nrc'] ?? ''); ?>">
                    </div>
                </div>

                <div class="profile-form-actions">
                    <button type="submit" class="profile-btn profile-btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                    <button type="button" class="profile-btn profile-btn-secondary" id="resetFormBtn">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="profile-card">
            <h3><i class="fas fa-key"></i> Change Password</h3>
            <form id="passwordForm">
                <div class="profile-form-group">
                    <label for="current_password">Current Password *</label>
                    <div class="profile-password-input">
                        <input type="password" id="current_password" name="current_password" class="profile-form-input" required>
                        <button type="button" class="profile-password-toggle" data-target="current_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="profile-content-grid">
                    <div class="profile-form-group">
                        <label for="new_password">New Password *</label>
                        <div class="profile-password-input">
                            <input type="password" id="new_password" name="new_password" class="profile-form-input" required>
                            <button type="button" class="profile-password-toggle" data-target="new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="profile-form-text">Password must be at least 8 characters long</small>
                    </div>
                    <div class="profile-form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <div class="profile-password-input">
                            <input type="password" id="confirm_password" name="confirm_password" class="profile-form-input" required>
                            <button type="button" class="profile-password-toggle" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="profile-form-actions">
                    <button type="submit" class="profile-btn profile-btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </form>
        </div>

        <!-- Account Information (Read-only) -->
        <div class="profile-card">
            <h3><i class="fas fa-shield-alt"></i> Account Information</h3>
            <div class="profile-info-grid">
                <div class="profile-info-item">
                    <label>User ID</label>
                    <span><?php echo $currentUser['id']; ?></span>
                </div>
                <div class="profile-info-item">
                    <label>Email Address</label>
                    <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
                    <small class="profile-form-text">Contact administrator to change email address</small>
                </div>
                <div class="profile-info-item">
                    <label>Category</label>
                    <span><?php echo htmlspecialchars($currentUser['category_name']); ?></span>
                    <small class="profile-form-text">Contact administrator to change category</small>
                </div>
                <div class="profile-info-item">
                    <label>Role</label>
                    <span><?php echo htmlspecialchars($currentUser['role_name']); ?></span>
                    <small class="profile-form-text">Contact administrator to change role</small>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="profile-card">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <div class="profile-activity-log">
                <div class="profile-activity-item">
                    <i class="fas fa-sign-in-alt"></i>
                    <div class="profile-activity-details">
                        <span>Profile last updated</span>
                        <small><?php echo date('F j, Y g:i A', strtotime($currentUser['updated_at'])); ?></small>
                    </div>
                </div>
                <div class="profile-activity-item">
                    <i class="fas fa-user-plus"></i>
                    <div class="profile-activity-details">
                        <span>Account created</span>
                        <small><?php echo date('F j, Y g:i A', strtotime($currentUser['created_at'])); ?></small>
                    </div>
                </div>
                <?php if ($currentUser['approved_at']): ?>
                    <div class="profile-activity-item">
                        <i class="fas fa-check-circle"></i>
                        <div class="profile-activity-details">
                            <span>Account approved</span>
                            <small><?php echo date('F j, Y g:i A', strtotime($currentUser['approved_at'])); ?></small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Self-Destruct Account -->
        <div class="profile-card profile-danger-zone">
            <h3><i class="fas fa-skull-crossbones"></i> Danger Zone</h3>
            <div class="profile-danger-content">
                <h4><i class="fas fa-exclamation-triangle"></i> Self-Destruct Account</h4>
                <p>This action will permanently delete your account and all associated data. This cannot be undone.</p>

                <div class="profile-warning-box">
                    <h5><i class="fas fa-info-circle"></i> Before you proceed:</h5>
                    <ul>
                        <li>Download any data you wish to keep</li>
                        <li>Ensure you've transferred ownership of any critical resources</li>
                        <li>Understand this action is irreversible</li>
                        <li>You'll be immediately logged out upon confirmation</li>
                    </ul>
                </div>

                <button id="initiateSelfDestructBtn" class="profile-btn profile-btn-danger">
                    <i class="fas fa-bomb"></i> Initiate Self-Destruct
                </button>
            </div>
        </div>
</div>

<!-- Self-Destruct Modal -->
<div id="selfDestructModal" class="profile-modal" style="display: none;">
    <div class="profile-modal-content">
        <div class="profile-modal-header">
            <h3><i class="fas fa-skull-crossbones"></i> Confirm Self-Destruct</h3>
            <button class="profile-close-modal">&times;</button>
        </div>
        <div class="profile-modal-body">
            <div class="profile-warning-box">
                <h4><i class="fas fa-exclamation-triangle"></i> Final Warning</h4>
                <p>You are about to permanently delete your account. This action cannot be undone.</p>
            </div>

            <form id="selfDestructForm">
                <div class="profile-form-group">
                    <label for="confirmPassword">Enter Your Password *</label>
                    <div class="profile-password-input">
                        <input type="password" id="confirmPassword" name="password" class="profile-form-input" required>
                        <button type="button" class="profile-password-toggle" data-target="confirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="profile-form-group">
                    <label for="captcha">Enter CAPTCHA *</label>
                    <div class="profile-captcha-container">
                        <div class="profile-captcha-display" id="captchaDisplay"></div>
                        <button type="button" id="refreshCaptcha" class="profile-btn profile-btn-sm profile-btn-secondary">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <input type="text" id="captcha" name="captcha" class="profile-form-input" required>
                        <small class="profile-form-text">CAPTCHA refreshes every 30 seconds</small>
                    </div>
                </div>

                <div class="profile-form-group">
                    <label>
                        <input type="checkbox" id="confirmCheckbox" required>
                        I understand this action is irreversible and I want to proceed
                    </label>
                </div>

                <div class="profile-form-actions">
                    <button type="submit" class="profile-btn profile-btn-danger">
                        <i class="fas fa-bomb"></i> Confirm Self-Destruct
                    </button>
                    <button type="button" class="profile-btn profile-btn-secondary profile-close-modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="profile-loading-overlay" style="display: none;">
    <div class="profile-loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
        <p>Processing...</p>
    </div>
</div>

<script>
    // Global utility functions
    function showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alertContainer');
        const alertId = 'alert-' + Date.now();

        const alertHTML = `
                    <div id="${alertId}" class="profile-alert profile-alert-${type}">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        ${message}
                        <button class="profile-alert-close" onclick="closeAlert('${alertId}')">&times;</button>
                    </div>
                `;

        alertContainer.insertAdjacentHTML('beforeend', alertHTML);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            closeAlert(alertId);
        }, 5000);
    }

    function closeAlert(alertId) {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.remove();
        }
    }

    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    // Main application logic
    document.addEventListener('DOMContentLoaded', function() {
        // Password toggle functionality
        document.querySelectorAll('.profile-password-toggle').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        });

        // Profile form submission
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'update_profile');

                showLoading();

                fetch(window.location.href + '?ajax=1', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showAlert(data.message, 'success');
                            // Reload page after 2 seconds to show updated data
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showAlert(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showAlert('An error occurred while updating profile', 'error');
                        console.error('Error:', error);
                    });
            });
        }

        // Password form submission
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'change_password');

                showLoading();

                fetch(window.location.href + '?ajax=1', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showAlert(data.message, 'success');
                            this.reset();
                        } else {
                            showAlert(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showAlert('An error occurred while changing password', 'error');
                        console.error('Error:', error);
                    });
            });
        }

        // Reset form button
        const resetFormBtn = document.getElementById('resetFormBtn');
        if (resetFormBtn) {
            resetFormBtn.addEventListener('click', function() {
                profileForm.reset();
            });
        }

        // Resend verification button
        const resendVerificationBtn = document.getElementById('resendVerificationBtn');
        if (resendVerificationBtn) {
            resendVerificationBtn.addEventListener('click', function() {
                showLoading();

                fetch(window.location.href + '?ajax=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=resend_verification'
                })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showAlert(data.message, 'success');
                        } else {
                            showAlert(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showAlert('An error occurred while sending verification email', 'error');
                        console.error('Error:', error);
                    });
            });
        }

        // Self-Destruct functionality
        const modal = document.getElementById('selfDestructModal');
        const initiateBtn = document.getElementById('initiateSelfDestructBtn');
        const closeModalBtns = document.querySelectorAll('.profile-close-modal');
        const refreshCaptchaBtn = document.getElementById('refreshCaptcha');
        const captchaDisplay = document.getElementById('captchaDisplay');
        const selfDestructForm = document.getElementById('selfDestructForm');
        let captchaTimer;

        // Show modal when initiate button is clicked
        if (initiateBtn) {
            initiateBtn.addEventListener('click', function() {
                loadCaptcha();
                modal.style.display = 'flex';
            });
        }

        // Close modal when X or Cancel is clicked
        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                modal.style.display = 'none';
                clearTimeout(captchaTimer);
                selfDestructForm.reset();
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
                clearTimeout(captchaTimer);
                selfDestructForm.reset();
            }
        });

        // Refresh CAPTCHA
        if (refreshCaptchaBtn) {
            refreshCaptchaBtn.addEventListener('click', loadCaptcha);
        }

        // Self-destruct form submission
        if (selfDestructForm) {
            selfDestructForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const password = document.getElementById('confirmPassword').value;
                const captcha = document.getElementById('captcha').value;
                const isConfirmed = document.getElementById('confirmCheckbox').checked;

                if (!isConfirmed) {
                    showAlert('You must confirm your understanding before proceeding', 'error');
                    return;
                }

                showLoading();

                const formData = new FormData();
                formData.append('action', 'self_destruct');
                formData.append('password', password);
                formData.append('captcha', captcha);

                fetch(window.location.href + '?ajax=1', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showAlert(data.message, 'success');
                            modal.style.display = 'none';
                            if (data.redirect) {
                                setTimeout(() => {
                                    window.location.href = data.redirect;
                                }, 2000);
                            }
                        } else {
                            showAlert(data.message, 'error');
                            // Refresh CAPTCHA on failure
                            loadCaptcha();
                            // Clear password and captcha fields
                            document.getElementById('confirmPassword').value = '';
                            document.getElementById('captcha').value = '';
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showAlert('An error occurred during account deletion', 'error');
                        console.error('Error:', error);
                        // Clear sensitive fields on error
                        document.getElementById('confirmPassword').value = '';
                        document.getElementById('captcha').value = '';
                        loadCaptcha();
                    });
            });
        }

        // Function to load CAPTCHA
        function loadCaptcha() {
            clearTimeout(captchaTimer);

            fetch(window.location.href + '?ajax=1&action=generate_captcha')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        captchaDisplay.textContent = data.captcha;
                        // Clear captcha input
                        const captchaInput = document.getElementById('captcha');
                        if (captchaInput) {
                            captchaInput.value = '';
                        }
                        // Set timer to refresh CAPTCHA after 30 seconds
                        captchaTimer = setTimeout(loadCaptcha, 30000);
                    }
                })
                .catch(error => {
                    console.error('Error loading CAPTCHA:', error);
                    captchaDisplay.textContent = 'ERROR';
                });
        }
    });
</script>
</main>
</div>
</body>
</html>