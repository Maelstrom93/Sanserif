<?php
// file: api/duplica_lavoro.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';

if (!isLogged()) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non autenticato']); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;

$src_id = isset($in['id']) ? (int)$in['id'] : 0;
$prefix = isset($in['titolo_prefix']) ? trim((string)$in['titolo_prefix']) : 'Copia di ';

if ($src_id <= 0){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID sorgente non valido']); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  // 1) Lavoro origine
  $st = $conn->prepare("
    SELECT titolo, cliente_id, assegnato_a, data_ricezione, scadenza, prezzo,
           provenienza, cartelle_json, descrizione, stato
      FROM lavori WHERE id = ? LIMIT 1
  ");
  $st->bind_param('i', $src_id);
  $st->execute();
  $src = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$src) { throw new Exception('Lavoro sorgente non trovato'); }

  // 2) Inserisci nuovo lavoro (assegnato_a NULL per non ereditare assegnazioni)
  $titoloNew = $prefix . (string)$src['titolo'];
  $prezzoSql = ($src['prezzo'] !== null && $src['prezzo']!=='') ? number_format((float)$src['prezzo'], 2, '.', '') : null;

  $ins = $conn->prepare("
    INSERT INTO lavori
      (titolo, cliente_id, assegnato_a, data_ricezione, scadenza, prezzo,
       provenienza, cartelle_json, descrizione, stato, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?, NOW(), NOW())
  ");
  $assegnatoNull = null;
  $ins->bind_param(
    'siisssssss',
    $titoloNew,
    $src['cliente_id'],
    $assegnatoNull,
    $src['data_ricezione'],
    $src['scadenza'],
    $prezzoSql,
    $src['provenienza'],
    $src['cartelle_json'],
    $src['descrizione'],
    $src['stato']
  );
  $ins->execute();
  $new_id = (int)$conn->insert_id;
  $ins->close();

  // 3) Categorie (pivot)
  if (tableExists($conn,'lavori_categorie')) {
    $q = $conn->prepare("SELECT categoria_id FROM lavori_categorie WHERE lavoro_id = ?");
    $q->bind_param('i', $src_id);
    $q->execute();
    $rs = $q->get_result();
    $q->close();

    if ($rs && $rs->num_rows) {
      $insC = $conn->prepare("INSERT INTO lavori_categorie (lavoro_id, categoria_id) VALUES (?,?)");
      while ($r = $rs->fetch_assoc()) {
        $cid = (int)$r['categoria_id'];
        $insC->bind_param('ii', $new_id, $cid);
        $insC->execute();
      }
      $insC->close();
    }
  }

  // 4) Attività (senza eventi calendario)
  if (tableExists($conn,'lavori_attivita')) {
    $qa = $conn->prepare("
      SELECT utente_id, categoria_id, titolo, descrizione, scadenza, prezzo
        FROM lavori_attivita WHERE lavoro_id = ? ORDER BY id ASC
    ");
    $qa->bind_param('i', $src_id);
    $qa->execute();
    $rsa = $qa->get_result();
    $qa->close();

    if ($rsa && $rsa->num_rows){
      $insA = $conn->prepare("
        INSERT INTO lavori_attivita (lavoro_id, utente_id, categoria_id, titolo, descrizione, scadenza, prezzo)
        VALUES (?,?,?,?,?,?,?)
      ");
      while ($a = $rsa->fetch_assoc()){
        $uid = isset($a['utente_id']) ? (int)$a['utente_id'] : null;
        $cid = isset($a['categoria_id']) ? (int)$a['categoria_id'] : null;
        $tit = (string)$a['titolo'];
        $des = (string)$a['descrizione'];
        $sca = $a['scadenza'] ? (string)$a['scadenza'] : null;
        $prz = ($a['prezzo']!==null && $a['prezzo']!=='') ? (float)$a['prezzo'] : null;
        $insA->bind_param('iiisssd', $new_id, $uid, $cid, $tit, $des, $sca, $prz);
        $insA->execute();
      }
      $insA->close();
    }
  }

  $conn->commit();
  echo json_encode(['success'=>true, 'new_id'=>$new_id, 'titolo'=>$titoloNew], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
