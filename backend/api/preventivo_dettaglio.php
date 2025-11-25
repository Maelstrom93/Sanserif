<?php
// api/preventivo_dettaglio.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['utente'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
  exit;
}
require_once '../assets/funzioni/db/db.php';
$conn->set_charset('utf8mb4');

function jerr($msg,$code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) jerr('Param id mancante o non valido');

$preventivo = null;
$cliente = null;
$righe = [];

// Preventivo
$stmt = $conn->prepare("SELECT * FROM preventivi WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$preventivo = $res->fetch_assoc();
$stmt->close();
if (!$preventivo) jerr('Preventivo non trovato',404);

// Cliente (se presente)
if (!empty($preventivo['cliente_id'])) {
  $stmt = $conn->prepare("SELECT * FROM clienti WHERE id=?");
  $stmt->bind_param('i', $preventivo['cliente_id']);
  $stmt->execute();
  $res = $stmt->get_result();
  $cliente = $res->fetch_assoc();
  $stmt->close();
}

// Righe
$stmt = $conn->prepare("SELECT id, descrizione, quantita, prezzo_unitario, totale_riga FROM righe_preventivo WHERE preventivo_id=? ORDER BY id ASC");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $righe[] = $r;
$stmt->close();

echo json_encode([
  'ok'=>true,
  'preventivo'=>$preventivo,
  'cliente'=>$cliente,
  'righe'=>$righe
], JSON_UNESCAPED_UNICODE);
