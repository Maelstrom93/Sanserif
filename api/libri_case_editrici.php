<?php
// api/libri_case_editrici.php – usa la connessione centralizzata

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json; charset=utf-8');

// include il file di connessione DB
require_once __DIR__ . '/../backend/assets/funzioni/db/db.php';

try {
    // usa la funzione helper definita in db.php
    $conn = db();
    $conn->set_charset('utf8mb4');

    $sql = "
        SELECT DISTINCT casa_editrice
        FROM libri
        WHERE casa_editrice IS NOT NULL
          AND casa_editrice <> ''
        ORDER BY casa_editrice ASC
    ";
    $res = $conn->query($sql);

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row['casa_editrice'];
    }

    http_response_code(200);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "error"   => "DB_ERROR",
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
