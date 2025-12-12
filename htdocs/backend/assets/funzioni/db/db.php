<?php
require_once __DIR__ . '/../env.php';


$envPath = realpath(__DIR__ . '/../../../../../.env');
if ($envPath) load_env($envPath);

// Prendi variabili da env (con fallback “safe” per dev)
$DB_HOST = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? '';
$DB_NAME = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Non fermare tutto con die(): lancia eccezione gestibile dagli endpoint
    throw new RuntimeException("Errore connessione DB", 0, $e);
}


/**
 * Accessor idempotente alla connessione
 */
function db(): mysqli {
    static $c = null;
    if ($c instanceof mysqli) return $c;
    global $conn;
    if ($conn instanceof mysqli) { $c = $conn; return $c; }

    throw new RuntimeException('Connessione DB non disponibile');
}
