<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header('Content-Type: application/json');
require_once __DIR__ . '/../backend/assets/funzioni/db/db.php';

$query = "
SELECT DISTINCT YEAR(data_pubblicazione) as anno
FROM libri
WHERE data_pubblicazione IS NOT NULL
ORDER BY anno DESC
";

$result = $conn->query($query);
$anni = [];

while ($row = $result->fetch_assoc()) {
    $anni[] = $row['anno'];
}

echo json_encode($anni);
