<?php
// ============================================================
// config/db.php  –  PDO Database Connection
// ============================================================

define('DB_HOST', 'sql204.infinityfree.com');   // from screenshot
define('DB_PORT', '3306');
define('DB_NAME', 'if0_41704911_lost_knowledge'); // exact DB name
define('DB_USER', 'if0_41704911');              // MySQL username
define('DB_PASS', 'rdkdhe747883');               // your password
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO instance.
 * Throws a RuntimeException on connection failure (never exposes credentials).
 */
function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the real error server-side; show a generic message to visitors
            error_log('[Lost Knowledge] DB connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please try again later.');
        }
    }

    return $pdo;
}
