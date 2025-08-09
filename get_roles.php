<?php
/**
 * ============================================================================
 * LARS - Get Roles by Category AJAX Endpoint
 * ============================================================================
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once "dbconnect.php";

try {
    $categoryId = $_GET['category_id'] ?? '';

    if (empty($categoryId) || !is_numeric($categoryId)) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM roles 
        WHERE category_id = ? AND name NOT LIKE '%admin%' 
        ORDER BY name ASC
    ");

    $stmt->execute([$categoryId]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($roles);

} catch (PDOException $e) {
    error_log('LARS Get Roles Error: ' . $e->getMessage());
    echo json_encode([]);
}
?>