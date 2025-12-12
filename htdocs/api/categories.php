<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../backend/assets/funzioni/db/db.php';

$result = $conn->query("SELECT DISTINCT nome FROM categorie_articoli ORDER BY nome ASC");

$categorie = [];
while ($row = $result->fetch_assoc()) {
    $categorie[] = $row['nome'];
}

header('Content-Type: application/json');
echo json_encode($categorie);
