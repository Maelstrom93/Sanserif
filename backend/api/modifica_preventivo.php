<?php
// api/modifica_preventivo.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../assets/funzioni/db/db.php';
if (!isset($_SESSION['utente'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
  exit;
}


$conn->set_charset('utf8mb4');

function jerr($msg,$code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
function jok($data=[]){ echo json_encode(['ok'=>true]+$data, JSON_UNESCAPED_UNICODE); exit; }

$id            = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : 0;
$data          = $_POST['data'] ?? '';
$valido_fino   = $_POST['valido_fino'] ?? '';
$pagamento     = $_POST['pagamento'] ?? '';
$iva           = isset($_POST['iva']) ? (float)$_POST['iva'] : 22.0;
$sconto        = isset($_POST['sconto']) ? (float)$_POST['sconto'] : 0.0;
$note          = trim($_POST['note'] ?? '');

$descrizioni   = $_POST['descrizione'] ?? [];
$quantita      = $_POST['quantita'] ?? [];
$prezzi        = $_POST['prezzo'] ?? [];

if (!$id) jerr('ID preventivo mancante o non valido');
if (!is_array($descrizioni) || !is_array($quantita) || !is_array($prezzi)) jerr('Voci non valide');

$conn->begin_transaction();

try {
  // Verifica esistenza
  $stmt = $conn->prepare("SELECT id FROM preventivi WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$res->fetch_assoc()) { $stmt->close(); throw new Exception('Preventivo inesistente',404); }
  $stmt->close();

  // Ricalcolo righe e totali
  $righe = [];
  $totale = 0.0;
  foreach ($descrizioni as $i => $desc) {
    $desc = trim((string)$desc);
    $qta = (int)($quantita[$i] ?? 0);
    $prezzo = (float)($prezzi[$i] ?? 0);
    if ($desc === '' || $qta <= 0) continue;
    $sub = $qta * $prezzo;
    $totale += $sub;
    $righe[] = [
      'descrizione'=>$desc,
      'quantita'=>$qta,
      'prezzo_unitario'=>$prezzo,
      'totale_riga'=>$sub
    ];
  }

  $scontoVal      = $totale * ($sconto/100);
  $totScontato    = $totale - $scontoVal;
  $ivaVal         = $totScontato * ($iva/100);
  $totaleFinale   = $totScontato + $ivaVal;

  // Update preventivo
  $stmt = $conn->prepare("
    UPDATE preventivi
    SET data=?, valido_fino=?, pagamento=?, iva=?, sconto=?, note=?, totale=?, totale_con_iva=?
    WHERE id=?
  ");
  $stmt->bind_param('sssddsddi',
    $data, $valido_fino, $pagamento, $iva, $sconto, $note, $totale, $totaleFinale, $id
  );
  $stmt->execute();
  $stmt->close();

  // Delete righe esistenti
  $stmt = $conn->prepare("DELETE FROM righe_preventivo WHERE preventivo_id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $stmt->close();

  // Insert nuove righe
  if (!empty($righe)) {
    $stmt = $conn->prepare("INSERT INTO righe_preventivo (preventivo_id, descrizione, quantita, prezzo_unitario, totale_riga) VALUES (?,?,?,?,?)");
    foreach ($righe as $r) {
      $stmt->bind_param('isidd', $id, $r['descrizione'], $r['quantita'], $r['prezzo_unitario'], $r['totale_riga']);
      $stmt->execute();
    }
    $stmt->close();
  }

  $conn->commit();
  jok(['id'=>$id, 'totale'=>$totale, 'totale_con_iva'=>$totaleFinale]);

} catch (Exception $ex) {
  $conn->rollback();
  $code = is_int($ex->getCode()) && $ex->getCode() > 0 ? $ex->getCode() : 400;
  jerr($ex->getMessage(), $code);
}
