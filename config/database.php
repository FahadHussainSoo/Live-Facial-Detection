<?php
/**
 * Database Configuration
 * Live Facial Authentication System
 *
 * Update the constants below to match your local XAMPP / MySQL setup.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'face_auth_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO connection instance.
 * Uses a static variable so the connection is only created once per request.
 *
 * @return PDO
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Avoid leaking sensitive connection details to the browser.
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed. Please check your configuration and try again.');
        }
    }

    return $pdo;
}
