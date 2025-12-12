<?php
declare(strict_types=1);

require_once __DIR__ . '/../email/_auth_guard.php';
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/db/contact_requests.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Metodo non consentito']);
  exit;
}

try {
  $id             = (int)($_POST['id'] ?? 0);
  $status         = trim((string)($_POST['status'] ?? ''));
  $assigned_to    = trim((string)($_POST['assigned_to'] ?? ''));
  $internal_note  = trim((string)($_POST['internal_note'] ?? ''));
  $closure_reason = trim((string)($_POST['closure_reason'] ?? ''));

  $allowed = ['new','in_review','replied','closed'];
  if ($id <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Dati non validi']);
    exit;
  }

  // usa la full se vuoi gestire closure_reason
  $ok = cr_update_admin_full(
    $conn,
    $id,
    $status,
    $assigned_to !== '' ? $assigned_to : null,
    $internal_note !== '' ? $internal_note : null,
    $status === 'closed' ? ($closure_reason !== '' ? $closure_reason : null) : null
  );

  if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Aggiornamento non riuscito']);
    exit;
  }

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Errore server','detail'=>$e->getMessage()]);
}
