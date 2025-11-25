<?php
// api/elimina_lavoro.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/csrf.php';

if (!isLogged()) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non autenticato']); exit; }
csrf_check_from_json();

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
$id = isset($in['id']) && ctype_digit((string)$in['id']) ? (int)$in['id'] : 0;
if ($id<=0){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID non valido']); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();
try {
  // cancella attività collegate (se esistono le tabelle)
  if (tableExists($conn,'lavori_attivita')) {
    // elimina eventuali eventi collegati
    if (tableExists($conn,'flusso_lavoro')) {
      $res = $conn->query("SELECT evento_id FROM lavori_attivita WHERE lavoro_id = ".(int)$id." AND evento_id IS NOT NULL");
      while ($r = $res->fetch_assoc()) {
        $ev = (int)$r['evento_id'];
        if ($ev>0) $conn->query("DELETE FROM flusso_lavoro WHERE id = $ev LIMIT 1");
      }
    }
    $conn->query("DELETE FROM lavori_attivita WHERE lavoro_id = ".(int)$id);
  }
  if (tableExists($conn,'lavori_categorie')) {
    $conn->query("DELETE FROM lavori_categorie WHERE lavoro_id = ".(int)$id);
  }
  // macro-evento scadenza
  if (tableExists($conn,'flusso_lavoro')) {
    $conn->query("DELETE FROM flusso_lavoro WHERE descrizione LIKE CONCAT('Lavoro #', ".(int)$id.", '%')");
  }
  $conn->query("DELETE FROM lavori WHERE id = ".(int)$id." LIMIT 1");

  $conn->commit();
  echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
