<?php
declare(strict_types=1);

require_once __DIR__ . '/../email/_auth_guard.php';
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/db/contact_requests.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'ID non valido']);
  exit;
}

$row = cr_get($conn, $id);
if (!$row) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>'Richiesta non trovata']);
  exit;
}

// >>> aggiorna stato e viewers (così la dashboard riflette l’apertura) <<<
$userId = (int)($_SESSION['utente']['id'] ?? 0);
markRequestInReviewIfNew($conn, $id);
trackRequestView($conn, $id, $userId);

echo json_encode(['ok'=>true,'item'=>$row], JSON_UNESCAPED_UNICODE);
