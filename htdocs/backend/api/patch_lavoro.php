<?php
// file: api/patch_lavoro.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/csrf.php';
csrf_check_from_post();

if (!isLogged()) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non autenticato']); exit; }

function clean_date(?string $s): ?string { $s=trim((string)$s); if($s==='')return null; $ts=strtotime($s); return $ts?date('Y-m-d',$ts):null; }
function clean_float($v): ?float { if($v===''||$v===null) return null; if(is_string($v)) $v=str_replace(',','.',$v); return is_numeric($v)?floatval($v):null; }
function defaultColorFor(string $tipo): string {
  $k = mb_strtolower(trim($tipo),'UTF-8');
  if ($k==='scadenza') return '#EF4444';
  return '#64748B';
}
function lastColorForType(mysqli $conn, string $tipo): ?string {
  if (!tableExists($conn,'flusso_lavoro')) return null;
  $st=$conn->prepare("SELECT colore FROM flusso_lavoro WHERE tipo=? AND TRIM(COALESCE(colore,''))<>'' ORDER BY id DESC LIMIT 1");
  $st->bind_param('s',$tipo); $st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
  $c=trim((string)($row['colore']??'')); return $c!==''?$c:null;
}
function getClienteNome(mysqli $conn, ?int $cliente_id): string {
  if ($cliente_id===null) return 'Cliente';
  if (!tableExists($conn,'clienti')) return 'Cliente #'.$cliente_id;
  $col = tableHasColumn($conn,'clienti','nome') ? 'nome' : (tableHasColumn($conn,'clienti','rgs') ? 'rgs' : null);
  if (!$col) return 'Cliente #'.$cliente_id;
  $q=$conn->query("SELECT TRIM($col) AS n FROM clienti WHERE id = ".(int)$cliente_id." LIMIT 1");
  $nome=$q?(string)($q->fetch_assoc()['n']??''):''; return $nome!==''?$nome:('Cliente #'.$cliente_id);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id<=0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID non valido']); exit; }

$scadenza_in = isset($_POST['scadenza']) ? clean_date($_POST['scadenza']) : null; // se key non presente, non toccare
$prezzo_in   = array_key_exists('prezzo', $_POST) ? clean_float($_POST['prezzo']) : null;
$touch_scad  = array_key_exists('scadenza', $_POST);
$touch_prez  = array_key_exists('prezzo', $_POST);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  $rs = $conn->prepare("SELECT titolo, descrizione, cliente_id FROM lavori WHERE id=? LIMIT 1");
  $rs->bind_param('i',$id); $rs->execute(); $row=$rs->get_result()->fetch_assoc(); $rs->close();
  if (!$row) throw new Exception('Lavoro non trovato');

  $titolo      = (string)($row['titolo'] ?? 'Lavoro');
  $descrizione = (string)($row['descrizione'] ?? '');
  $cliente_id  = $row['cliente_id']!==null ? (int)$row['cliente_id'] : null;
  $cliente_nome= getClienteNome($conn,$cliente_id);

  if ($touch_scad) {
    $upS = $conn->prepare("UPDATE lavori SET scadenza=?, updated_at=NOW() WHERE id=? LIMIT 1");
    $upS->bind_param('si',$scadenza_in,$id); $upS->execute(); $upS->close();

    // Macro evento scadenza
    if (tableExists($conn,'flusso_lavoro')) {
      $sel = $conn->prepare("SELECT id FROM flusso_lavoro WHERE descrizione LIKE CONCAT('Lavoro #', ?, ' — Scadenza macro — Cliente:%') ORDER BY id DESC LIMIT 1");
      $sel->bind_param('i',$id); $sel->execute(); $m = $sel->get_result()->fetch_assoc(); $sel->close();

      if ($scadenza_in){
        $tipo='scadenza';
        $col = lastColorForType($conn,$tipo) ?: defaultColorFor($tipo);
        $nome = $titolo.' — Scadenza — '.$cliente_nome;
        $note = $descrizione!==''?$descrizione:'Non ci sono note';
        $desc = 'Lavoro #'.$id.' — Scadenza macro — Cliente: '.$cliente_nome;
        $ass  = null;

        if ($m){
          $upd=$conn->prepare("UPDATE flusso_lavoro SET nome=?, descrizione=?, data_evento=?, assegnato_a=?, note=?, tipo=?, colore=? WHERE id=? LIMIT 1");
          $upd->bind_param('sssssssi',$nome,$desc,$scadenza_in,$ass,$note,$tipo,$col,$m['id']); $upd->execute(); $upd->close();
        } else {
          $ins=$conn->prepare("INSERT INTO flusso_lavoro (nome, creato_il, descrizione, data_evento, assegnato_a, note, tipo, colore) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
          $ins->bind_param('sssssss',$nome,$desc,$scadenza_in,$ass,$note,$tipo,$col); $ins->execute(); $ins->close();
        }
      } else {
        if ($m){ $conn->query("DELETE FROM flusso_lavoro WHERE id=".(int)$m['id']." LIMIT 1"); }
      }
    }
  }

  if ($touch_prez) {
    $prezzoSql = ($prezzo_in !== null) ? number_format($prezzo_in, 2, '.', '') : null;
    $upP = $conn->prepare("UPDATE lavori SET prezzo=?, updated_at=NOW() WHERE id=? LIMIT 1");
    $upP->bind_param('si',$prezzoSql,$id); $upP->execute(); $upP->close();
  }

  $conn->commit();
  echo json_encode([
    'success'=>true,
    'updated'=>[
      'scadenza'=>$touch_scad ? $scadenza_in : null,
      'prezzo'  =>$touch_prez ? ($prezzo_in===null?null:$prezzo_in) : null
    ]
  ]);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
