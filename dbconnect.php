<?php
/**
 * ============================================================================
 * LEARNER ACHIEVEMENT RECORDS SYSTEM (LARS) - DATABASE CONNECTION
 * ============================================================================
 * National education data infrastructure owned and managed by
 * Zambia Qualifications Authority (ZAQA)
 *
 * Lead Developer: amcodecase (justus.michelo@zaqa.gov.zm)
 * ============================================================================
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables using vlucas/phpdotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database configuration from .env
$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$database = $_ENV['DB_DATABASE'] ?? '';
$username = $_ENV['DB_USERNAME'] ?? '';
$password = $_ENV['DB_PASSWORD'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// Validate required database credentials
if (empty($database) || empty($username)) {
    throw new Exception('Database credentials not configured in .env file');
}

// Build DSN (Data Source Name)
$dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

// PDO options for security and performance
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,          // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Default fetch mode
    PDO::ATTR_EMULATE_PREPARES => false,                  // Use real prepared statements
    PDO::ATTR_PERSISTENT => false,                        // No persistent connections
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci"
];

try {
    // Create PDO connection
    $pdo = new PDO($dsn, $username, $password, $options);

    // Log successful connection in development
    if (($_ENV['APP_ENV'] ?? '') === 'development') {
        error_log('LARS: Database connection established successfully');
    }

} catch (PDOException $e) {
    // Log error securely
    error_log('LARS Database Connection Error: ' . $e->getMessage());

    // Show generic error in production
    if (($_ENV['APP_ENV'] ?? '') === 'production') {
        die('Database connection failed. Please contact system administrator.');
    } else {
        die('Database Connection Error: ' . $e->getMessage());
    }
}

/**
 * Get database connection instance
 * @return PDO
 */
function getDB() {
    global $pdo;
    return $pdo;
}

/**
 * Test database connection
 * @return bool
 */
function testConnection() {
    try {
        global $pdo;
        $stmt = $pdo->query('SELECT 1');
        return $stmt !== false;
    } catch (PDOException $e) {
        return false;
    }
}

// Optional: Auto-test connection
if (($_ENV['APP_ENV'] ?? '') === 'development') {
    if (!testConnection()) {
        error_log('LARS: Database connection test failed');
    }
}