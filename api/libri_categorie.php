<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header('Content-Type: application/json');

require_once __DIR__ . '/../backend/assets/funzioni/db/db.php';

$query = "
SELECT DISTINCT cl.nome
FROM categorie_libri cl
JOIN libri_categorie lc ON cl.id = lc.categoria_id
WHERE LOWER(cl.nome) != 'in evidenza'
ORDER BY cl.nome ASC
";

$result = $conn->query($query);
$categorie = [];

while ($row = $result->fetch_assoc()) {
    $categorie[] = $row['nome'];
}

echo json_encode($categorie);
