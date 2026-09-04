<?php
/**
 * Database Configuration & Connection (PDO)
 * Smart Cycle Rental Management System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_cycle_rental');
define('DB_PORT', '3306');

/**
 * Returns a singleton PDO instance with prepared statements enabled
 * and strict exception handling.
 * 
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Friendly error message for localhost setup
            die("<div style='font-family:sans-serif; padding:20px; background:#fff3f3; color:#c00; border:1px solid #ecc; border-radius:8px; max-width:600px; margin:40px auto;'>" .
                "<h3>Database Connection Error</h3>" .
                "<p>Unable to connect to MySQL database <strong>" . htmlspecialchars(DB_NAME) . "</strong>.</p>" .
                "<p><small>Details: " . htmlspecialchars($e->getMessage()) . "</small></p>" .
                "<p><strong>Troubleshooting:</strong></p>" .
                "<ul>" .
                "<li>Ensure MySQL is running in XAMPP Control Panel.</li>" .
                "<li>Ensure the database <code>smart_cycle_rental</code> is imported via phpMyAdmin using <code>database.sql</code>.</li>" .
                "</ul></div>");
        }
    }

    return $pdo;
}

// Establish global connection instance
$pdo = getDBConnection();
