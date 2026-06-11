<?php
/**
 * PATIENTDATAPROGRAM
 * db_connect.php — PDO Database Connection
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'patientdataprogram');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('<div style="font-family:monospace;background:#fdecea;color:#c0392b;padding:20px;border-radius:8px;margin:20px;">
        <strong>Database Connection Failed</strong><br><br>
        ' . htmlspecialchars($e->getMessage()) . '<br><br>
        Check your <code>db_connect.php</code> settings.
    </div>');
}
