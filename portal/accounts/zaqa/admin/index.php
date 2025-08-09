<?php
/**
 * ============================================================================
 * LEARNER ACHIEVEMENT RECORDS SYSTEM (LARS) - ADMIN GUARD
 * ============================================================================
 * Redirects non-admin users to an access denied page
 * Used for protecting admin-only sections
 *
 * Lead Developer: amcodecase (justus.michelo@zaqa.gov.zm)
 * ============================================================================
 */

session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /access-denied.php');
    exit;
}

header('Location: dashboard.php');
exit;
