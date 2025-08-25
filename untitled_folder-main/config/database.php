<?php
/**
 * Database Configuration and Connection
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'community_voting');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create database connection
function getDatabaseConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Alias for backward compatibility
function getDBConnection() {
    return getDatabaseConnection();
}

// Initialize database tables if they don't exist
function initDatabase() {
    $pdo = getDBConnection();
    
    // Read and execute the complete schema
    $schemaFile = __DIR__ . '/../database/complete_schema.sql';
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(--|\/\*|\*)/', $statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Ignore errors for existing tables
                    if (!strpos($e->getMessage(), 'already exists')) {
                        error_log("Database initialization error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

// Call init function
initDatabase();
?>
