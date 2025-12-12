<?php
// api/libri.php – usa la connessione centralizzata di backend/assets/funzioni/db/db.php

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json; charset=utf-8');

// includo il file di connessione globale
require_once __DIR__ . '/../backend/assets/funzioni/db/db.php';

try {
    // usa la funzione helper definita in db.php
    $conn = db();
    $conn->set_charset('utf8mb4');

    // 1) Verifica se la colonna casa_editrice esiste nella tabella libri
    $sqlCheckCol = "
        SELECT COUNT(*) AS n
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'libri' AND COLUMN_NAME = 'casa_editrice'
    ";
    $stmt = $conn->prepare($sqlCheckCol);
   $dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? '';
    $stmt->bind_param('s', $dbName);
    $stmt->execute();
    $hasCol = 0;
    $stmt->bind_result($hasCol);
    $stmt->fetch();
    $stmt->close();

    // 2) Query principale: ONE ROW PER BOOK, compatibile con ONLY_FULL_GROUP_BY
 $casaSel = $hasCol
    ? "MAX(l.casa_editrice) AS casa_editrice"
    : "'' AS casa_editrice";

$sql = "
    SELECT
        l.id,
        MAX(l.titolo)             AS titolo,
        MAX(l.sinossi)            AS sinossi,
        MAX(l.immagine)           AS immagine,
        MAX(l.cover_json)         AS cover_json,      -- << NEW
        MAX(l.data_pubblicazione) AS data_pubblicazione,
        MAX(l.link)               AS link,
        $casaSel,
        GROUP_CONCAT(DISTINCT cl.nome ORDER BY cl.nome SEPARATOR ', ') AS categorie
    FROM libri l
    LEFT JOIN libri_categorie lc ON l.id = lc.libro_id
    LEFT JOIN categorie_libri cl ON lc.categoria_id = cl.id
    GROUP BY l.id
ORDER BY MAX(l.aggiunto_il) DESC, l.id DESC
";

    $result = $conn->query($sql);

   $libri = [];
while ($row = $result->fetch_assoc()) {
    $cover = null;
    if (!empty($row['cover_json'])) {
        $tmp = json_decode($row['cover_json'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $cover = $tmp;
        }
    }

    $libri[] = [
        "id"                 => (int)$row['id'],                    // << NEW
        "titolo"             => (string)$row['titolo'],
        "sinossi"            => (string)$row['sinossi'],
        "img"                => (string)$row['immagine'],           // << niente "backend/" qui
        "cover_json"         => $cover,                             // << NEW
        "data_pubblicazione" => (string)$row['data_pubblicazione'],
        "categorie"          => $row['categorie'] ? explode(', ', $row['categorie']) : [],
        "link"               => (string)$row['link'],
        "casa_editrice"      => (string)$row['casa_editrice']
    ];
}

echo json_encode($libri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    // In caso di errore DB, restituisco 500 + info (per debug)
    http_response_code(500);
    echo json_encode([
        "error"   => "DB_ERROR",
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
