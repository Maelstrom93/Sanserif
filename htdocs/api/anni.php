<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header("Content-Type: application/json");

// Connessione al DB
require_once __DIR__ . '/../backend/assets/funzioni/db/db.php';

// Query per estrarre anni unici dalla colonna `data_pubblicazione`
$sql = "SELECT DISTINCT YEAR(data_pubblicazione) AS anno FROM articoli ORDER BY anno DESC";
$result = $conn->query($sql);

$anni = [];
while ($row = $result->fetch_assoc()) {
    $anni[] = $row['anno'];
}

echo json_encode($anni);
