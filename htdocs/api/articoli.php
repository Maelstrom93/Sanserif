<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../backend/assets/funzioni/db/db.php';

$sql = "
    SELECT a.id, a.titolo, a.descrizione, a.copertina, a.data_pubblicazione, a.categoria, c.nome AS categorie
    FROM articoli a
    JOIN categorie_articoli c ON a.categoria = c.id
    ORDER BY a.data_pubblicazione DESC
";

$result = $conn->query($sql);

$articoli = [];

while ($row = $result->fetch_assoc()) {
   $articoli[] = [
    "title" => $row['titolo'],
    "category" => $row['categorie'], // ✅ corretto
    "date" => substr($row['data_pubblicazione'], 0, 7),
    "excerpt" => $row['descrizione'],
    "img" => "backend/" . $row['copertina'],
    "link" => "articolo.php?id=" . $row['id']
];

}

header('Content-Type: application/json');
echo json_encode($articoli);
?>
