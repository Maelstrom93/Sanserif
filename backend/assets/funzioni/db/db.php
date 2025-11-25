<?php
define('DB_HOST', 'sql.sansserifspazioeditoriale.it');
define('DB_USER', 'sansseri59283');
define('DB_PASS', 'sans39666');
define('DB_NAME', 'sansseri59283');

// Connessione globale (se vuoi continuare a usarla altrove)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Errore connessione DB: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

/**
 * Accessor idempotente alla connessione
 */
function db(): mysqli {
  static $c = null;
  if ($c instanceof mysqli) return $c;
  global $conn;               // riusa quella già aperta
  if ($conn instanceof mysqli) { $c = $conn; return $c; }

  // fallback (in pratica non ci arriverai se sopra è ok)
  $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($c->connect_error) {
      throw new RuntimeException('DB connection failed: ' . $c->connect_error);
  }
  $c->set_charset('utf8mb4');
  return $c;
}
