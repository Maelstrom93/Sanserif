<?php
// file: api/update_stato.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/csrf.php';
csrf_check_from_post(); // usa token 'csrf' nel POST

if (!isLogged()) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non autenticato']); exit; }

function defaultColorFor(string $tipo): string {
  $k = mb_strtolower(trim($tipo),'UTF-8');
  if ($k==='articolo')        return '#0EA5E9';
  if ($k==='revisione')       return '#F59E0B';
  if ($k==='incontro')        return '#10B981';
  if ($k==='scadenza')        return '#EF4444';
  if ($k==='lavorazione')     return '#2563EB';
  if ($k==='in_lavorazione')  return '#8B5CF6';
  if ($k==='pausa')           return '#F59E0B';
  if ($k==='completato')      return '#10B981';
  if ($k==='chiuso')          return '#64748B';
  if ($k==='annullato')       return '#EF4444';
  return '#64748B';
}
function lastColorForType(mysqli $conn, string $tipo): ?string {
  if (!tableExists($conn,'flusso_lavoro')) return null;
  $st=$conn->prepare("SELECT colore FROM flusso_lavoro WHERE tipo=? AND TRIM(COALESCE(colore,''))<>'' ORDER BY id DESC LIMIT 1");
  $st->bind_param('s',$tipo); $st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
  $c=trim((string)($row['colore']??'')); return $c!==''?$c:null;
}
function mapStatoToCalTipo(string $stato): ?string {
  $k = mb_strtolower(trim($stato),'UTF-8');
  if ($k==='aperto')          return 'lavorazione';
  if ($k==='in_lavorazione')  return 'in_lavorazione';
  if ($k==='pausa')           return 'pausa';
  if ($k==='completato')      return 'completato';
  if ($k==='chiuso')          return 'chiuso';
  if ($k==='annullato')       return 'annullato';
  return null;
}
function normalizeStatoForDB(mysqli $conn, string $st): array {
  $st = trim($st)==='' ? 'aperto' : trim($st);
  $res = $conn->query("SHOW COLUMNS FROM lavori LIKE 'stato'");
  if ($res && ($col = $res->fetch_assoc())) {
    $type = (string)$col['Type'];
    if (stripos($type,'enum(')===0) {
      if (preg_match_all("/'([^']+)'/u",$type,$m)) {
        $allowed = $m[1];
        if (!in_array($st,$allowed,true)) {
          if ($st==='chiuso' && in_array('completato',$allowed,true)) {
            return ['completato','stato mappato: chiuso→completato (ENUM non contiene chiuso)'];
          }
          return [$allowed[0] ?? 'aperto',"stato mappato: $st→".($allowed[0]??'aperto')." (non presente in ENUM)"];
        }
      }
    }
  }
  return [$st,null];
}

$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$stIn = trim((string)($_POST['stato'] ?? ''));

if ($id<=0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID non valido']); exit; }
if ($stIn==='') { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Stato mancante']); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  $rs = $conn->prepare("SELECT stato FROM lavori WHERE id=? LIMIT 1");
  $rs->bind_param('i',$id); $rs->execute(); $cur = $rs->get_result()->fetch_assoc(); $rs->close();
  if (!$cur) { throw new Exception('Lavoro non trovato'); }
  $stPrev = (string)($cur['stato'] ?? 'aperto');

  list($stato,$stWarn) = normalizeStatoForDB($conn,$stIn);
  $up=$conn->prepare("UPDATE lavori SET stato=?, updated_at=NOW() WHERE id=? LIMIT 1");
  $up->bind_param('si',$stato,$id); $up->execute(); $up->close();

  // calendario: mappa tipo/colore + note
  if (tableExists($conn,'flusso_lavoro')) {
    $calTipo = mapStatoToCalTipo($stato);
    if ($calTipo!==null) {
      $calCol = lastColorForType($conn,$calTipo) ?: defaultColorFor($calTipo);
      $upd = $conn->prepare("UPDATE flusso_lavoro SET tipo=?, colore=? WHERE descrizione LIKE CONCAT('Lavoro #', ?, '%')");
      $upd->bind_param('ssi',$calTipo,$calCol,$id); $upd->execute(); $upd->close();

      $stamp = date('d/m/Y H:i');
      $extra = "Stato lavoro: ".strtoupper($calTipo)." il ".$stamp;
      $note = $conn->prepare("
        UPDATE flusso_lavoro
           SET note = TRIM(CONCAT(COALESCE(note,''), CASE WHEN note IS NULL OR note='' THEN '' ELSE '\n' END, ?))
         WHERE descrizione LIKE CONCAT('Lavoro #', ?, '%')
      ");
      $note->bind_param('si',$extra,$id); $note->execute(); $note->close();
    }
  }

  // chiusura → tag ex-utente e svuota assegnazioni
  $closing = in_array(mb_strtolower($stato,'UTF-8'), ['completato','chiuso','annullato'], true);
  $wasClosing = in_array(mb_strtolower($stPrev,'UTF-8'), ['completato','chiuso','annullato'], true);
  if ($closing && !$wasClosing) {
    if (tableExists($conn,'lavori_attivita')) {
      $rs = $conn->prepare("
        SELECT la.id, la.utente_id,
               COALESCE(NULLIF(TRIM(u.nome),''), NULLIF(TRIM(u.email),'')) AS ulabel,
               la.descrizione
          FROM lavori_attivita la
          LEFT JOIN utenti u ON u.id = la.utente_id
         WHERE la.lavoro_id = ? AND la.utente_id IS NOT NULL
      ");
      $rs->bind_param('i',$id); $rs->execute(); $cur = $rs->get_result();
      while ($row = $cur->fetch_assoc()){
        $rid   = (int)$row['id'];
        $label = trim((string)($row['ulabel'] ?? '')); if ($label==='') $label = '#'.(int)$row['utente_id'];
        $desc  = (string)($row['descrizione'] ?? '');
        if (strpos($desc, '[ex_utente:') === false){
          $newDesc = rtrim($desc).($desc!=='' ? "\n" : '').'[ex_utente: '.$label.']';
          $up2 = $conn->prepare("UPDATE lavori_attivita SET descrizione=? WHERE id=? LIMIT 1");
          $up2->bind_param('si',$newDesc,$rid); $up2->execute(); $up2->close();
        }
      }
      $rs->close();
    }
    // svuota assegnazioni (righe, lavoro, eventi)
    if (tableExists($conn,'lavori_attivita')) $conn->query("UPDATE lavori_attivita SET utente_id=NULL WHERE lavoro_id=".(int)$id);
    $conn->query("UPDATE lavori SET assegnato_a=NULL WHERE id=".(int)$id." LIMIT 1");
    if (tableExists($conn,'flusso_lavoro')) $conn->query("UPDATE flusso_lavoro SET assegnato_a=NULL WHERE descrizione LIKE CONCAT('Lavoro #', ".(int)$id.", '%')");
  }

  $conn->commit();
  $out=['success'=>true,'stato'=>$stato];
  if ($stWarn) $out['warning']=$stWarn;
  echo json_encode($out);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
