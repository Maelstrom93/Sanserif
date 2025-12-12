<?php
// api/duplica_preventivo.php
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
function jok($data=[]){ echo json_encode(['ok'=>true]+$data, JSON_UNESCAPED_UNICODE); exit; }

$src = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$src) jerr('ID sorgente mancante o non valido');

$conn->begin_transaction();

try {
  // Carica preventivo sorgente
  $stmt = $conn->prepare("SELECT * FROM preventivi WHERE id=?");
  $stmt->bind_param('i', $src);
  $stmt->execute();
  $res = $stmt->get_result();
  $p = $res->fetch_assoc();
  $stmt->close();
  if (!$p) throw new Exception('Preventivo sorgente non trovato',404);

  // Carica righe
  $righe = [];
  $stmt = $conn->prepare("SELECT descrizione, quantita, prezzo_unitario, totale_riga FROM righe_preventivo WHERE preventivo_id=? ORDER BY id ASC");
  $stmt->bind_param('i', $src);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $righe[] = $r;
  $stmt->close();

  // Nuovo numero / anno
  $anno = (int)date('Y');
  $stmt = $conn->prepare("SELECT MAX(numero) AS ultimo FROM preventivi WHERE anno=?");
  $stmt->bind_param('i', $anno);
  $stmt->execute();
  $res = $stmt->get_result();
  $num = (int)($res->fetch_assoc()['ultimo'] ?? 0) + 1;
  $stmt->close();

  // Nuove date
  $data = date('Y-m-d');
  $valido_fino = date('Y-m-d', strtotime('+30 days'));

  // Inserisci nuovo preventivo (copiando campi principali)
  $stmt = $conn->prepare("
    INSERT INTO preventivi (
      numero, anno, cliente_id, cliente_nome_custom, referente_custom, data, valido_fino,
      pagamento, iva, sconto, note, totale, totale_con_iva, data_creazione
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())
  ");
  $stmt->bind_param(
    'iiisssssddssd',
    $num, $anno, $p['cliente_id'], $p['cliente_nome_custom'], $p['referente_custom'],
    $data, $valido_fino, $p['pagamento'], $p['iva'], $p['sconto'], $p['note'], $p['totale'], $p['totale_con_iva']
  );
  $stmt->execute();
  $newId = $stmt->insert_id;
  $stmt->close();

  // Copia righe
  if (!empty($righe)) {
    $stmt = $conn->prepare("INSERT INTO righe_preventivo (preventivo_id, descrizione, quantita, prezzo_unitario, totale_riga) VALUES (?,?,?,?,?)");
    foreach ($righe as $r) {
      $stmt->bind_param('isidd', $newId, $r['descrizione'], $r['quantita'], $r['prezzo_unitario'], $r['totale_riga']);
      $stmt->execute();
    }
    $stmt->close();
  }

  $conn->commit();
  jok(['new_id'=>$newId, 'numero'=>$num, 'anno'=>$anno]);

} catch (Exception $ex) {
  $conn->rollback();
  jerr($ex->getMessage(), $ex->getCode() ?: 400);
}
