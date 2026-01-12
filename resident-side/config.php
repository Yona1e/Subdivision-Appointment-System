<?php
/**
 * config.php
 * Database configuration file for XAMPP
 */

// Database credentials for XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'facilityreservationsystem');
define('DB_USER', 'root');
define('DB_PASS', '');  // Empty for default XAMPP
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection
 * @return PDO Database connection object
 * @throws PDOException if connection fails
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        
        $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $conn;
        
    } catch(PDOException $e) {
        // Log error
        error_log("Database Connection Error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Close database connection
 * @param PDO $conn Database connection object
 */
function closeDBConnection(&$conn) {
    $conn = null;
}
?>