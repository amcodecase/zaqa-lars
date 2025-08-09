<?php
/**
 * ============================================================================
 * LARS - Get Institutions by Category AJAX Endpoint
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
        SELECT id, name, type 
        FROM institutions 
        WHERE category_id = ? 
        ORDER BY name ASC
    ");

    $stmt->execute([$categoryId]);
    $institutions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($institutions);

} catch (PDOException $e) {
    error_log('LARS Get Institutions Error: ' . $e->getMessage());
    echo json_encode([]);
}
?><?php
