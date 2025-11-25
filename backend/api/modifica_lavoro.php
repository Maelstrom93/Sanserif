<?php
// file: api/modifica_lavoro.php
session_start();

// Blocca qualsiasi output spurio (notice, BOM, ecc.) per non rompere il JSON
ini_set('display_errors', '0');
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/csrf.php';
// subito sotto i require/CSRF:
$__audit = []; // coda messaggi log da scrivere dopo il commit

csrf_check_from_post();

if (!isLogged()) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'Non autenticato'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

/* ===== Helpers generali ===== */
function clean_date(?string $s): ?string {
  $s = trim((string)$s);
  if ($s==='') return null;
  $ts = strtotime($s);
  return $ts ? date('Y-m-d',$ts) : null;
}
function clean_float($v): ?float {
  if ($v==='' || $v===null) return null;
  if (is_string($v)) $v = str_replace(',', '.', $v);
  return is_numeric($v) ? floatval($v) : null;
}
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
/* ==== Helper: leggi attività minime post-salvataggio ==== */
function fetch_attivita_min(mysqli $conn, int $lavoroId): array {
  if (!tableExists($conn,'lavori_attivita')) return [];
  $rows = [];
  $sql = "
    SELECT la.id, la.titolo, la.descrizione, la.scadenza, la.prezzo,
           la.utente_id, la.categoria_id,
           COALESCE(NULLIF(TRIM(u.nome),''), NULLIF(TRIM(u.email),'')) AS utente_lbl
    FROM lavori_attivita la
    LEFT JOIN utenti u ON u.id = la.utente_id
    WHERE la.lavoro_id = ".(int)$lavoroId."
    ORDER BY la.id ASC
  ";
  $res = $conn->query($sql);
  while($a = $res->fetch_assoc()){
    $descrRaw = (string)($a['descrizione'] ?? '');
    $utente_ex = null;
    if ($descrRaw !== '' && preg_match('/\[(?:ex[_\s-]?utente)\s*:\s*(.+?)\]/iu', $descrRaw, $m)) {
      $utente_ex = trim($m[1]);
    }
    $descrPulita = trim(preg_replace('/\s*\[(?:ex[_\s-]?utente)\s*:\s*.+?\]\s*/iu', '', $descrRaw));
    $rows[] = [
      'id'         => (int)$a['id'],
      'titolo'     => (string)($a['titolo'] ?? ''),
      'descrizione'=> $descrPulita,
      'scadenza'   => $a['scadenza'] ? (string)$a['scadenza'] : null,
      'prezzo'     => $a['prezzo'] !== null ? (float)$a['prezzo'] : null,
      'utente'     => (string)($a['utente_lbl'] ?? ''),
      'utente_id'  => $a['utente_id'] !== null ? (int)$a['utente_id'] : null,
      'utente_ex'  => $utente_ex,
      'categoria_id'=> $a['categoria_id'] !== null ? (int)$a['categoria_id'] : null
    ];
  }
  return $rows;
}

// === Helper: aggiunge [ex_utente: ...] se manca nelle attività con utente assegnato ===
function tag_ex_assegnatari(mysqli $conn, int $lavoroId): void {
  if (!tableExists($conn,'lavori_attivita')) return;

  $rs = $conn->prepare("
    SELECT la.id, la.utente_id,
           COALESCE(NULLIF(TRIM(u.nome),''), NULLIF(TRIM(u.email),''), CONCAT('#', u.id)) AS ulabel,
           la.descrizione
      FROM lavori_attivita la
      LEFT JOIN utenti u ON u.id = la.utente_id
     WHERE la.lavoro_id = ? AND la.utente_id IS NOT NULL
  ");
  $rs->bind_param('i', $lavoroId);
  $rs->execute();
  $cur = $rs->get_result();

  while ($row = $cur->fetch_assoc()){
    $rid   = (int)$row['id'];
    $label = trim((string)($row['ulabel'] ?? ''));
    if ($label==='') $label = '#'.(int)$row['utente_id'];
    $desc  = (string)($row['descrizione'] ?? '');

    if (!preg_match('/\[(?:ex[_\s-]?utente)\s*:\s*.+?\]/iu', $desc)) {
      $newDesc = rtrim($desc).($desc!=='' ? "\n" : '').'[ex_utente: '.$label.']';
      $up = $conn->prepare("UPDATE lavori_attivita SET descrizione=? WHERE id=? LIMIT 1");
      $up->bind_param('si', $newDesc, $rid);
      $up->execute();
      $up->close();
    }
  }
  $rs->close();
}

function lastColorForType(mysqli $conn, string $tipo): ?string {
  if (!tableExists($conn,'flusso_lavoro')) return null;
  $st=$conn->prepare("SELECT colore FROM flusso_lavoro WHERE tipo=? AND TRIM(COALESCE(colore,''))<>'' ORDER BY id DESC LIMIT 1");
  $st->bind_param('s',$tipo); $st->execute();
  $row=$st->get_result()->fetch_assoc(); $st->close();
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

// Ritorna la lista degli stati ammessi (ENUM) oppure [] se non è un enum
function getAllowedStati(mysqli $conn): array {
  $res = $conn->query("SHOW COLUMNS FROM lavori LIKE 'stato'");
  if (!$res || !($col = $res->fetch_assoc())) return [];
  $type = (string)$col['Type'];
  if (stripos($type, "enum(")!==0) return [];
  preg_match_all("/'([^']+)'/u", $type, $m);
  return $m[1] ?? [];
}
// Sceglie lo stato "closing" migliore consentito dall'ENUM
function pickClosingState(mysqli $conn): string {
  $allowed = getAllowedStati($conn);
  if (in_array('completato', $allowed, true)) return 'completato';
  if (in_array('chiuso',      $allowed, true)) return 'chiuso';
  // fallback prudente
  return $allowed[0] ?? 'aperto';
}
// Sceglie lo stato "in lavorazione" migliore consentito dall'ENUM
function pickWorkingState(mysqli $conn): string {
  $allowed = getAllowedStati($conn);
  if (in_array('in_lavorazione', $allowed, true)) return 'in_lavorazione';
  if (in_array('aperto',         $allowed, true)) return 'aperto';
  return $allowed[0] ?? 'aperto';
}


/* === Risoluzione categorie su categorie_lavoro === */
function risolviCategorieLavoro(mysqli $conn, $input, array $accumulator = []): array {
  if (!tableExists($conn,'categorie_lavoro')) return $accumulator;

  $nomi = [];
  if (is_string($input)) {
    foreach (explode(',', $input) as $raw) {
      $n = trim($raw);
      if ($n !== '') $nomi[] = $n;
    }
  } elseif (is_array($input)) {
    foreach ($input as $raw) {
      $n = trim((string)$raw);
      if ($n !== '') $nomi[] = $n;
    }
  }
  if (!$nomi) return $accumulator;

  $ins = $conn->prepare("INSERT IGNORE INTO categorie_lavoro (nome) VALUES (?)");
  $sel = $conn->prepare("SELECT id FROM categorie_lavoro WHERE nome = ? LIMIT 1");

  foreach ($nomi as $nome) {
    $ins->bind_param('s', $nome);
    $ins->execute();
    $sel->bind_param('s', $nome);
    $sel->execute();
    if ($row = $sel->get_result()->fetch_assoc()) {
      $accumulator[] = (int)$row['id'];
    }
  }
  $ins->close(); $sel->close();

  return array_values(array_unique($accumulator));
}

/* ===== Input macro ===== */
$id            = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$titolo        = trim((string)($_POST['titolo'] ?? ''));
$cliente_id    = isset($_POST['cliente_id']) && $_POST['cliente_id']!=='' ? (int)$_POST['cliente_id'] : null;
$assegnato_a   = isset($_POST['assegnato_a']) && $_POST['assegnato_a']!=='' ? (int)$_POST['assegnato_a'] : null;
$data_ricezione= clean_date($_POST['data_ricezione'] ?? null);
$scadenza      = clean_date($_POST['scadenza'] ?? null);
$prezzo        = clean_float($_POST['prezzo'] ?? null);
$provenienza   = trim((string)($_POST['provenienza'] ?? ''));
$descrizione   = trim((string)($_POST['descrizione'] ?? ''));
$stato_raw     = trim((string)($_POST['stato'] ?? 'aperto'));
$closure_log   = trim((string)($_POST['closure_log'] ?? ''));
$cliente_nome = getClienteNome($conn, $cliente_id);
$prezzoSql    = $prezzo;

/* ===== priorità + checklist ===== */
$priorita = isset($_POST['priorita']) ? trim((string)$_POST['priorita']) : 'media';
if (!in_array($priorita, ['bassa','media','alta'], true)) $priorita = 'media';

$prioritaForDb = $priorita;
if (tableHasColumn($conn, 'lavori', 'priorita')) {
  $colInfo = $conn->query("SHOW COLUMNS FROM lavori LIKE 'priorita'");
  $typeStr = strtolower((string)($colInfo && ($ci = $colInfo->fetch_assoc()) ? $ci['Type'] : ''));
  if (preg_match('/int/i', $typeStr)) {
    $map = ['bassa'=>1,'media'=>2,'alta'=>3];
    $prioritaForDb = $map[$priorita] ?? 2;
  } else if (strpos($typeStr, 'enum(') === 0) {
    if (preg_match_all("/'([^']+)'/", $typeStr, $m)) {
      if (!in_array($priorita, $m[1], true)) $prioritaForDb = in_array('media',$m[1],true) ? 'media' : $m[1][0];
    }
  }
}

$checklist_in = (string)($_POST['checklist_json'] ?? '');
$checklist_arr = [];
if ($checklist_in !== '') {
  $tmp = json_decode($checklist_in, true);
  if (is_array($tmp)) $checklist_arr = $tmp;
  else {
    $parts = preg_split('/[\n;,]+/u', $checklist_in);
    foreach ($parts as $p) { $p = trim($p); if ($p!=='') $checklist_arr[] = $p; }
  }
}
$checklist_json = json_encode($checklist_arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/* ===== Categorie / cartelle ===== */
$categoriePost = [];
if (isset($_POST['category']))      $categoriePost = (array)$_POST['category'];
elseif (isset($_POST['categorie'])) $categoriePost = (array)$_POST['categorie'];

$newCategory   = trim((string)($_POST['new_category'] ?? ''));
$cartelle_json = (string)($_POST['cartelle_json'] ?? '[]');
$tmpDecoded = json_decode($cartelle_json, true);
if (!is_array($tmpDecoded)) {
  $csv = array_map('trim', explode(',', $cartelle_json));
  $tmpDecoded = array_filter($csv, function($v){ return $v !== ''; });
}
$cartelleArr = array_values(array_map('strval', $tmpDecoded));
$cartelle_json = json_encode($cartelleArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/* ===== Righe: JSON dal form ===== */
$righe_json_raw = isset($_POST['righe_json']) ? (string)$_POST['righe_json'] : '';
$righe_json = $righe_json_raw!=='' ? json_decode($righe_json_raw,true) : null;
if ($righe_json!==null && !is_array($righe_json)) $righe_json = null;

/* ===== Validazioni ===== */
if ($id<=0){
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'ID non valido'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
if ($titolo===''){
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Titolo obbligatorio'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
if (!$data_ricezione){
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Data di ricezione obbligatoria'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

list($stato,$stato_warn) = normalizeStatoForDB($conn,$stato_raw);

/* ===== Risoluzione categorie ===== */
$categorie_ids = [];
foreach ($categoriePost as $raw){
  $raw=trim((string)$raw); if($raw==='') continue;
  if (strpos($raw,'__new__:')===0){ $name=trim(substr($raw,8)); if($name!=='') $newCategory=$newCategory?($newCategory.','.$name):$name; }
  elseif (ctype_digit($raw)) { $categorie_ids[]=(int)$raw; }
}
if ($newCategory!=='') $categorie_ids = risolviCategorieLavoro($conn, $newCategory, $categorie_ids);
$categorie_ids = array_values(array_unique(array_map('intval',$categorie_ids)));

$utente_corrente_id = isset($_SESSION['utente']['id']) ? (int)$_SESSION['utente']['id'] : null;

/* ===== Helper pivot categorie ===== */
function syncPivotCategorieFromAttivita(mysqli $conn, int $lavoroId): void {
  if (!tableExists($conn,'lavori_categorie') || !tableExists($conn,'lavori_attivita')) return;
  $sql = "
    INSERT IGNORE INTO lavori_categorie (lavoro_id, categoria_id)
    SELECT ?, la.categoria_id
      FROM lavori_attivita la
     WHERE la.lavoro_id = ? AND la.categoria_id IS NOT NULL
     GROUP BY la.categoria_id
  ";
  $st = $conn->prepare($sql);
  $st->bind_param('ii', $lavoroId, $lavoroId);
  $st->execute();
  $st->close();
}
function purgeUnusedPivotCategories(mysqli $conn, int $lavoroId, array $keepIds = []): void {
  if (!tableExists($conn,'lavori_categorie') || !tableExists($conn,'lavori_attivita')) return;

  $keep = '';
  if (!empty($keepIds)) {
    $list = implode(',', array_map('intval', $keepIds));
    $keep = "AND lc.categoria_id NOT IN ($list)";
  }

  $sql = "
    DELETE lc
      FROM lavori_categorie lc
 LEFT JOIN lavori_attivita la
        ON la.lavoro_id = lc.lavoro_id
       AND la.categoria_id = lc.categoria_id
     WHERE lc.lavoro_id = ?
       $keep
       AND la.id IS NULL
  ";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $lavoroId);
  $st->execute();
  $st->close();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  // Stato/descrizione correnti
 $curr = $conn->prepare("SELECT cliente_id, stato, descrizione FROM lavori WHERE id = ? LIMIT 1");
$curr->bind_param('i',$id); $curr->execute();
$currRow = $curr->get_result()->fetch_assoc(); $curr->close();

$currStato = (string)($currRow['stato'] ?? 'aperto');
$currDescr = (string)($currRow['descrizione'] ?? '');

// Stato di lavoro che useremo nella logica: parte dal DB
$stato = $currStato;

// Valori dal form
$stato_explicit = !empty($_POST['stato_explicit']);               // hidden che metti a 1 solo su interazione umana
$stato_raw      = array_key_exists('stato', $_POST) ? trim((string)$_POST['stato']) : null;

// Se e solo se è esplicito, normalizza e usa il valore del POST
if ($stato_explicit && $stato_raw !== null) {
    list($statoNorm,) = normalizeStatoForDB($conn, $stato_raw);
    $stato = $statoNorm;
}
if ($stato_explicit && $stato !== $currStato) {
  $__audit[] = "Lavoro #$id: stato cambiato MANUALMENTE da \"$currStato\" a \"$stato\"";
}
// Campi base (senza stato)
$fields = ["titolo=?","cliente_id=?","assegnato_a=?","data_ricezione=?","scadenza=?","prezzo=?","provenienza=?","cartelle_json=?","descrizione=?"];
$types  = "siissssss";
$args   = [$titolo,$cliente_id,$assegnato_a,$data_ricezione,$scadenza,$prezzoSql,$provenienza,$cartelle_json,$descrizione];

// Stato: aggiornalo SOLO se esplicito e diverso dal corrente
if ($stato_explicit && $stato !== $currStato) {
  $fields[] = "stato=?";
  $types   .= "s";
  $args[]   = $stato;
}

  if (tableHasColumn($conn,'lavori','priorita')) {
    $fields[] = "priorita=?";
    $colInfo = $conn->query("SHOW COLUMNS FROM lavori LIKE 'priorita'");
    $typeStr = strtolower((string)($colInfo && ($ci=$colInfo->fetch_assoc()) ? $ci['Type'] : ''));
    $types .= preg_match('/int/i',$typeStr) ? "i" : "s";
    $args[] = $prioritaForDb;
  }
  if (tableHasColumn($conn,'lavori','checklist_json')) {
    $fields[] = "checklist_json=?"; $types .= "s"; $args[] = $checklist_json;
  }

  $sql = "UPDATE lavori SET ".implode(', ',$fields).", updated_at=NOW() WHERE id=? LIMIT 1";
  $types .= "i"; $args[] = $id;
  $stmt = $conn->prepare($sql); $stmt->bind_param($types, ...$args); $stmt->execute(); $stmt->close();
$__audit[] = "Lavoro #$id aggiornato (titolo=\"$titolo\", cliente_id=" . ($cliente_id ?? 'null') . ", scadenza=" . ($scadenza ?? 'null') . ")";

  /* ===== Pivot categorie ===== */
  if (tableExists($conn,'lavori_categorie')) {
    $categorieProvided = (isset($_POST['category']) || isset($_POST['categorie']) || isset($_POST['new_category']));
    $forceClear        = !empty($_POST['force_clear_categories']); // opzionale se vuoi uno "svuota tutto" esplicito

    if ($categorieProvided) {
      if ($forceClear) {
        $conn->query("DELETE FROM lavori_categorie WHERE lavoro_id = ".(int)$id);
      }
      if (!empty($categorie_ids)) {
         $__audit[] = "Lavoro #$id: categorie aggiunte/assicurate [" . implode(',', $categorie_ids) . "]";
        $ins = $conn->prepare("INSERT IGNORE INTO lavori_categorie (lavoro_id, categoria_id) VALUES (?,?)");
        foreach ($categorie_ids as $cid) {
          $cid = (int)$cid;
          $ins->bind_param('ii', $id, $cid);
          $ins->execute();
        }
        $ins->close();
      }
      // NON rimuoviamo qui: manteniamo lo storico manuale
    }

    // Allinea comunque dal dettaglio attività (aggiunge senza duplicare)
    syncPivotCategorieFromAttivita($conn, (int)$id);
    // E ripulisci quelle non più presenti in nessuna attività (preservando eventuali scelte manuali del form)
    purgeUnusedPivotCategories($conn, (int)$id, $categorie_ids);
    $__audit[] = "Lavoro #$id: pivot categorie riallineata dalle attività";

  }

  // === RIGHE (+ gestione completamento) ===
  $hasLA = tableExists($conn,'lavori_attivita');
  $hasFL = tableExists($conn,'flusso_lavoro');
  $hasDone = tableHasColumn($conn,'lavori_attivita','completata');
  $hasDoneTs = tableHasColumn($conn,'lavori_attivita','completato_il');
  $hasDoneBy = tableHasColumn($conn,'lavori_attivita','completato_da');

  // helper categoria: **categorie_lavoro**
  $getCatName = function(int $cid) use($conn): string {
    if (!tableExists($conn,'categorie_lavoro')) return '';
    $r = $conn->query("SELECT TRIM(nome) AS n FROM categorie_lavoro WHERE id=$cid LIMIT 1");
    return $r ? (string)($r->fetch_assoc()['n'] ?? '') : '';
  };

  if ($hasLA && is_array($righe_json)) {
    $insR = $conn->prepare("INSERT INTO lavori_attivita (lavoro_id, utente_id, categoria_id, titolo, descrizione, scadenza, prezzo) VALUES (?,?,?,?,?,?,?)");
    $updR = $conn->prepare("UPDATE lavori_attivita SET utente_id=?, categoria_id=?, titolo=?, descrizione=?, scadenza=?, prezzo=? WHERE id=? AND lavoro_id=? LIMIT 1");
    $delR = $conn->prepare("DELETE FROM lavori_attivita WHERE id=? AND lavoro_id=? LIMIT 1");

    if ($hasFL){
      $updEvento=$conn->prepare("UPDATE flusso_lavoro SET nome=?, descrizione=?, data_evento=?, assegnato_a=?, note=?, tipo=?, colore=? WHERE id=? LIMIT 1");
      $insEvento=$conn->prepare("INSERT INTO flusso_lavoro (nome, creato_il, descrizione, data_evento, assegnato_a, note, tipo, colore) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
      $linkEvento=$conn->prepare("UPDATE lavori_attivita SET evento_id = ? WHERE id = ? LIMIT 1");
    }

    foreach ($righe_json as $r){
      $ridRaw = trim((string)($r['id'] ?? ''));
      $toDel  = !empty($r['_delete']);
      $uid    = (isset($r['utente_id']) && $r['utente_id']!=='') ? (int)$r['utente_id'] : null;
      $cid    = (isset($r['categoria_id']) && $r['categoria_id']!=='') ? (int)$r['categoria_id'] : null;
      $titR   = trim((string)($r['titolo'] ?? ''));
      $desR   = trim((string)($r['descrizione'] ?? ''));
      $scaR   = clean_date(isset($r['scadenza']) ? $r['scadenza'] : null);
      $przR   = clean_float(isset($r['prezzo']) ? $r['prezzo'] : null);

      $tipoSel = isset($r['evento_tipo']) && $r['evento_tipo']!=='' ? (string)$r['evento_tipo'] : 'lavorazione';
      $colRaw  = isset($r['evento_colore']) ? trim((string)$r['evento_colore']) : '';
      $evIdRaw = isset($r['evento_id']) ? trim((string)$r['evento_id']) : '';

      $compIn  = null;
      if (array_key_exists('completata',$r)) {
        $v = $r['completata'];
        $compIn = ($v === 1 || $v === '1' || $v === true || $v === 'true') ? 1 : 0;
      }

      $isEmptyNew = ($ridRaw==='' && $uid===null && $cid===null && $titR==='' && $desR==='' && !$scaR && $przR===null && $compIn===null);
      if ($isEmptyNew) continue;

      // UPDATE esistente
      if ($ridRaw!=='' && ctype_digit($ridRaw)){
        $rid=(int)$ridRaw;
        if ($toDel){
          if ($hasFL){
            $resEvt=$conn->query("SELECT evento_id FROM lavori_attivita WHERE id=$rid AND lavoro_id=$id LIMIT 1");
            $evId=$resEvt?(int)($resEvt->fetch_assoc()['evento_id']??0):0;
            if($evId) $conn->query("DELETE FROM flusso_lavoro WHERE id=$evId LIMIT 1");
          }
          $delR->bind_param('ii',$rid,$id); $delR->execute(); continue;
          $__audit[] = "Lavoro #$id: eliminata attività #$rid";

        }

       // --- PRESERVA EVENTUALE [ex_utente: ...] già presente nel DB
$prevDescRow = $conn->query("SELECT descrizione FROM lavori_attivita WHERE id=$rid AND lavoro_id=$id LIMIT 1");
$prevDesc    = $prevDescRow ? (string)($prevDescRow->fetch_assoc()['descrizione'] ?? '') : '';
$prevTag     = null;
if ($prevDesc !== '' && preg_match('/\[(?:ex[_\s-]?utente)\s*:\s*(.+?)\]/iu', $prevDesc, $mPrev)) {
  $prevTag = trim($mPrev[1]);
}
// normalizza la nuova descrizione arrivata dal form (potrebbe NON contenere il tag)
$desClean = trim(preg_replace('/\s*\[(?:ex[_\s-]?utente)\s*:\s*.+?\]\s*/iu', '', (string)$desR));
if ($prevTag && !preg_match('/\[(?:ex[_\s-]?utente)\s*:\s*.+?\]/iu', (string)$desR)) {
  // riattacca il tag alla fine SOLO se non è già nel testo postato
  $desR = rtrim($desClean) . ($desClean !== '' ? "\n" : '') . '[ex_utente: ' . $prevTag . ']';
} else {
  $desR = $desClean;
}

$updR->bind_param('iisssdii',$uid,$cid,$titR,$desR,$scaR,$przR,$rid,$id);
$updR->execute();
$__audit[] = "Lavoro #$id: aggiornata attività #$rid (utente_id=" . ($uid ?? 'null') . ", categoria_id=" . ($cid ?? 'null') . ", scad=" . ($scaR ?? 'null') . ", prezzo=" . ($przR ?? 'null') . ")";



        // Gestione completamento (solo se colonne presenti e input fornito)
        if ($hasDone && $compIn !== null) {
          // leggi stato precedente
          $prevRow = $conn->query("SELECT completata FROM lavori_attivita WHERE id=$rid AND lavoro_id=$id LIMIT 1");
          $prevDone = $prevRow ? (int)($prevRow->fetch_assoc()['completata'] ?? 0) : 0;

          if ($compIn === 1 && $prevDone === 0) {
            $now = date('Y-m-d H:i:s');
            if ($hasDoneTs && $hasDoneBy) {
              $u2 = $conn->prepare("UPDATE lavori_attivita SET completata=1, completato_il=?, completato_da=? WHERE id=? AND lavoro_id=? LIMIT 1");
              $u2->bind_param('siii', $now, $utente_corrente_id, $rid, $id);
            } elseif ($hasDoneTs) {
              $u2 = $conn->prepare("UPDATE lavori_attivita SET completata=1, completato_il=? WHERE id=? AND lavoro_id=? LIMIT 1");
              $u2->bind_param('sii', $now, $rid, $id);
            } else {
              $u2 = $conn->prepare("UPDATE lavori_attivita SET completata=1 WHERE id=? AND lavoro_id=? LIMIT 1");
              $u2->bind_param('ii', $rid, $id);
            }
            $u2->execute(); $u2->close();

            // PATCH: aggiorna evento collegato (no nuova riga)
            if ($hasFL){
              $evento_id = 0;
              $resEvt = $conn->query("SELECT evento_id FROM lavori_attivita WHERE id=$rid AND lavoro_id=$id LIMIT 1");
              if ($resEvt) $evento_id = (int)($resEvt->fetch_assoc()['evento_id'] ?? 0);

              if ($evento_id > 0) {
                $catName  = $cid ? $getCatName($cid) : '';
                $attTitle = $titR !== '' ? $titR : ($catName !== '' ? $catName : 'Attività');
                $nomeNew  = $titolo.' — '.$attTitle.($cliente_nome ? ' — '.$cliente_nome : '');
                $noteAdd  = 'Segnata come completata'.($utente_corrente_id ? ' da #'.$utente_corrente_id : '').' il '.$now;

                if ($up = $conn->prepare("
                  UPDATE flusso_lavoro
                     SET note = TRIM(CONCAT(COALESCE(note,''), CASE WHEN note IS NULL OR note='' THEN '' ELSE '\n' END, ?)),
                         nome = CASE WHEN nome LIKE CONCAT('%', ' — Completata') THEN nome ELSE CONCAT(?, ' — Completata') END,
                         data_evento = COALESCE(data_evento, ?)
                   WHERE id = ? LIMIT 1
                ")){
                  $up->bind_param('sssi', $noteAdd, $nomeNew, $now, $evento_id);
                  $up->execute();
                  $up->close();
                }
              }
            }

          } elseif ($compIn === 0 && $prevDone === 1) {
            // riapri
            if ($hasDoneTs && $hasDoneBy) {
              $u2 = $conn->prepare("UPDATE lavori_attivita SET completata=0, completato_il=NULL, completato_da=NULL WHERE id=? AND lavoro_id=? LIMIT 1");
              $u2->bind_param('ii', $rid, $id);
            } elseif ($hasDoneTs) {
              $u2 = $conn->prepare("UPDATE lavori_attivita SET completata=0, completato_il=NULL WHERE id=? AND lavoro_id=? LIMIT 1");
              $u2->bind_param('ii', $rid, $id);
            } else {
              $u2 = $conn->prepare("UPDATE lavori_attivita SET completata=0 WHERE id=? AND lavoro_id=? LIMIT 1");
              $u2->bind_param('ii', $rid, $id);
            }
       $u2->execute(); 
$u2->close();

          }
        }

        // eventi su scadenza
        if ($hasFL){
          $evento_id=0;
          if ($evIdRaw!=='' && ctype_digit($evIdRaw)) { $evento_id=(int)$evIdRaw; }
          else { $res=$conn->query("SELECT evento_id FROM lavori_attivita WHERE id=$rid AND lavoro_id=$id LIMIT 1"); $evento_id=$res?(int)($res->fetch_assoc()['evento_id']??0):0; }
          if ($scaR){
            $titAtt=$titR!==''?$titR:'Attività'; $nome=$titolo.' — '.$titAtt.' — '.$cliente_nome; $note=$desR!==''?$desR:'Non ci sono note';
            $descEv='Lavoro #'.$id.' / Attività #'.$rid.' — Cliente: '.$cliente_nome; $ass=($uid!==null)?(string)$uid:null;
            $tipoEv = $tipoSel ?: 'lavorazione';
            $col    = $colRaw!=='' ? $colRaw : (lastColorForType($conn,$tipoEv)?:defaultColorFor($tipoEv));
            if ($evento_id){ $updEvento->bind_param('sssssssi',$nome,$descEv,$scaR,$ass,$note,$tipoEv,$col,$evento_id); $updEvento->execute(); 
              $__audit[] = "Lavoro #$id: aggiornato evento #$evento_id per attività #$rid (tipo=\"$tipoEv\", data=$scaR)";

            }
            else { $insEvento->bind_param('sssssss',$nome,$descEv,$scaR,$ass,$note,$tipoEv,$col); $insEvento->execute(); $newEvId=(int)$conn->insert_id; $linkEvento->bind_param('ii',$newEvId,$rid); $linkEvento->execute(); 
                 $__audit[] = "Lavoro #$id: creato evento #$newEvId per attività #" . ($newRigaId ?? $rid) . " (tipo=\"$tipoEv\", data=$scaR)";
            }
       

          } else {
            if ($evento_id){ $conn->query("DELETE FROM flusso_lavoro WHERE id = ".(int)$evento_id." LIMIT 1"); $conn->query("UPDATE lavori_attivita SET evento_id=NULL WHERE id = ".(int)$rid." LIMIT 1"); 
              $__audit[] = "Lavoro #$id: eliminato evento #$evento_id (attività #$rid) per scadenza rimossa";

            }
          
          }
        }

      } else {
        // INSERT nuova riga
        if ($toDel) continue;
        $insR->bind_param('iiisssd',$id,$uid,$cid,$titR,$desR,$scaR,$przR); $insR->execute(); $newRigaId=(int)$conn->insert_id;
$__audit[] = "Lavoro #$id: creata nuova attività #$newRigaId (utente_id=" . ($uid ?? 'null') . ", categoria_id=" . ($cid ?? 'null') . ", scad=" . ($scaR ?? 'null') . ")";

        // eventuale scadenza -> evento
        if ($hasFL && $scaR){
          $titAtt=$titR!==''?$titR:'Attività'; $nome=$titolo.' — '.$titAtt.' — '.$cliente_nome; $note=$desR!==''?$desR:'Non ci sono note';
          $descEv='Lavoro #'.$id.' / Attività #'.$newRigaId.' — Cliente: '.$cliente_nome; $ass=($uid!==null)?(string)$uid:null;
          $tipoEv = $tipoSel ?: 'lavorazione';
          $col    = $colRaw!=='' ? $colRaw : (lastColorForType($conn,$tipoEv)?:defaultColorFor($tipoEv));
          $insEvento->bind_param('sssssss',$nome,$descEv,$scaR,$ass,$note,$tipoEv,$col); $insEvento->execute();
          $newEvId=(int)$conn->insert_id; $linkEvento->bind_param('ii',$newEvId,$newRigaId); $linkEvento->execute();
        }

        // gestisci completamento anche su nuova riga
        if ($hasDone && $compIn === 1) {
          $now = date('Y-m-d H:i:s');
          if ($hasDoneTs && $hasDoneBy) {
            $u2 = $conn->prepare("UPDATE lavori_attivita SET completata=1, completato_il=?, completato_da=? WHERE id=? AND lavoro_id=? LIMIT 1");
            $u2->bind_param('siii',$now,$utente_corrente_id,$newRigaId,$id);
          } elseif ($hasDoneTs) {
            $u2 = $conn->prepare("UPDATE lavori_attivita SET completata=1, completato_il=? WHERE id=? AND lavoro_id=? LIMIT 1");
            $u2->bind_param('sii',$now,$newRigaId,$id);
          } else {
            $u2 = $conn->prepare("UPDATE lavori_attivita SET completata=1 WHERE id=? AND lavoro_id=? LIMIT 1");
            $u2->bind_param('ii',$newRigaId,$id);
          }
          $u2->execute(); $u2->close();

          // PATCH: aggiorna evento collegato (no nuova riga)
          if ($hasFL){
            $evento_id = 0;
            $resEvt = $conn->query("SELECT evento_id FROM lavori_attivita WHERE id=$newRigaId AND lavoro_id=$id LIMIT 1");
            if ($resEvt) $evento_id = (int)($resEvt->fetch_assoc()['evento_id'] ?? 0);

            if ($evento_id > 0) {
              $catName  = $cid ? $getCatName($cid) : '';
              $attTitle = $titR !== '' ? $titR : ($catName !== '' ? $catName : 'Attività');
              $nomeNew  = $titolo.' — '.$attTitle.($cliente_nome ? ' — '.$cliente_nome : '');
              $noteAdd  = 'Segnata come completata'.($utente_corrente_id ? ' da #'.$utente_corrente_id : '').' il '.$now;

              if ($up = $conn->prepare("
                UPDATE flusso_lavoro
                   SET note = TRIM(CONCAT(COALESCE(note,''), CASE WHEN note IS NULL OR note='' THEN '' ELSE '\n' END, ?)),
                       nome = CASE WHEN nome LIKE CONCAT('%', ' — Completata') THEN nome ELSE CONCAT(?, ' — Completata') END,
                       data_evento = COALESCE(data_evento, ?)
                 WHERE id = ? LIMIT 1
              ")){
                $up->bind_param('sssi', $noteAdd, $nomeNew, $now, $evento_id);
             $up->execute();
$up->close();
              }
            }
          }
        }
      }
    }

    $insR->close(); $updR->close(); $delR->close();
    if ($hasFL){ $updEvento->close(); $insEvento->close(); $linkEvento->close(); }
  }


/* === AUTOCOMPLETA IL LAVORO SE TUTTE LE LAVORAZIONI SONO COMPLETE === */
$autoCompleted = false;
if (!empty($autoCompleted)) {
  $__audit[] = "Lavoro #$id: CHIUSO automaticamente (tutte le attività complete)";
} elseif ($statoPrima !== $stato) {
  $__audit[] = "Lavoro #$id: stato variato automaticamente da \"$statoPrima\" a \"$stato\" in base alle attività";
}
$statoPrima = $stato; // traccia lo stato che avevamo

if ($hasLA) {
  // calcolo robusto: tot, incomplete (NULL trattato come 0)
  $q = $conn->query("
    SELECT
      COUNT(*)                                            AS tot,
      SUM(CASE WHEN COALESCE(completata,0) = 0 THEN 1 ELSE 0 END) AS incomplete
    FROM lavori_attivita
    WHERE lavoro_id = ".(int)$id."
  ");
  $tot = 0; $incomplete = 0;
  if ($q && ($r = $q->fetch_assoc())) {
    $tot        = (int)($r['tot'] ?? 0);
    $incomplete = (int)($r['incomplete'] ?? 0);
  }

  // se esistono attività e non ce n'è nessuna incompleta -> chiudi
  if ($tot > 0 && $incomplete === 0) {
    // promuovi a uno stato "closing" valido per l'ENUM
    $target = pickClosingState($conn); // 'completato' se esiste, altrimenti 'chiuso'
    if (mb_strtolower($stato, 'UTF-8') !== mb_strtolower($target, 'UTF-8')) {
      $up = $conn->prepare("UPDATE lavori SET stato=?, updated_at=NOW() WHERE id=? LIMIT 1");
      $up->bind_param('si', $target, $id);
      $up->execute();
      $up->close();
      $stato = $target;
      $autoCompleted = true;
      $__audit[] = "Lavoro #$id: attività #$rid segnata COMPLETATA" . ($utente_corrente_id ? " da utente #$utente_corrente_id" : "");

    }
  } else {
    // se era già in stato di chiusura ma ora c'è almeno un'incompleta -> riapri
    if (in_array(mb_strtolower($stato, 'UTF-8'), ['completato','chiuso'], true) && $incomplete > 0) {
      $reopen = pickWorkingState($conn); // 'in_lavorazione' se esiste, altrimenti 'aperto'
      if (mb_strtolower($stato, 'UTF-8') !== mb_strtolower($reopen, 'UTF-8')) {
        $up = $conn->prepare("UPDATE lavori SET stato=?, updated_at=NOW() WHERE id=? LIMIT 1");
        $up->bind_param('si', $reopen, $id);
        $up->execute();
        $up->close();
        $stato = $reopen;
        $__audit[] = "Lavoro #$id: attività #$rid RIAPERTA";

      }
    }
  }

  // (facoltativo) utile per debug dal front-end
  $out_debug_auto = [
    'tot'        => $tot,
    'incomplete' => $incomplete,
    'stato_prima'=> $statoPrima,
    'stato_finale'=> $stato,
  ];
}



  /* ===== SINCRONIZZA CALENDARIO CON LO STATO ===== */
  $stNow  = mb_strtolower($stato,'UTF-8');
  $stPrev = mb_strtolower($currStato,'UTF-8');

  $closingStates     = ['completato','chiuso','annullato'];
  $becameClosing     = (in_array($stNow, $closingStates, true) && !in_array($stPrev, $closingStates, true));
  $clearAssignments  = in_array($stNow, $closingStates, true);

  // 1) tag ex-assegnatari nelle attività (solo quando entra in stato closing)
  // 1) tag ex-assegnatari nelle attività (PRIMA di svuotare gli assegnatari)
if (($becameClosing || $clearAssignments) && $hasLA){
  tag_ex_assegnatari($conn, (int)$id);
}


  // 2) Aggiorna tipo/colore eventi per stato (NON toccare eventi marcati "— Completata")
  if (tableExists($conn,'flusso_lavoro')) {
    $calTipo = mapStatoToCalTipo($stato);
    if ($calTipo !== null) {
      $calCol = lastColorForType($conn, $calTipo) ?: defaultColorFor($calTipo);
      $updMeta = $conn->prepare("
        UPDATE flusso_lavoro
           SET tipo = ?, colore = ?
         WHERE descrizione LIKE CONCAT('Lavoro #', ?, '%')
           AND nome NOT LIKE '% — Completata'
      ");
      $updMeta->bind_param('ssi', $calTipo, $calCol, $id);
      $updMeta->execute(); $updMeta->close();

      if ($clearAssignments){
        $conn->query("UPDATE flusso_lavoro SET assegnato_a = NULL WHERE descrizione LIKE CONCAT('Lavoro #', ".(int)$id.", '%')");
      }

      $stamp = date('d/m/Y H:i');
      $extra = "Stato lavoro: ".strtoupper($calTipo)." il ".$stamp;
      $updNote = $conn->prepare("
        UPDATE flusso_lavoro
           SET note = TRIM(CONCAT(COALESCE(note,''), CASE WHEN note IS NULL OR note='' THEN '' ELSE '\n' END, ?))
         WHERE descrizione LIKE CONCAT('Lavoro #', ?, '%')
      ");
      $updNote->bind_param('si', $extra, $id);
      $updNote->execute(); $updNote->close();
    }
  }

  // 3) Svuota assegnatari se chiuso
  if ($clearAssignments) {
    if ($hasLA) $conn->query("UPDATE lavori_attivita SET utente_id = NULL WHERE lavoro_id = ".(int)$id);
    $conn->query("UPDATE lavori SET assegnato_a = NULL WHERE id = ".(int)$id." LIMIT 1");
     $__audit[] = "Lavoro #$id: svuotati assegnatari (macro e attività) per stato \"$stato\"";
  }

  // 4) Log opzionale in descrizione lavoro
  if ($becameClosing && $closure_log!==''){
    $newDescr = rtrim($currDescr)."\n".$closure_log."\n";
    $upD=$conn->prepare("UPDATE lavori SET descrizione=? WHERE id=? LIMIT 1");
    $upD->bind_param('si',$newDescr,$id); $upD->execute(); $upD->close();
  }

  // 5) Macro-evento scadenza (del lavoro)
  if (tableExists($conn,'flusso_lavoro')) {
    $selMacro=$conn->prepare("SELECT id FROM flusso_lavoro WHERE descrizione LIKE CONCAT('Lavoro #', ?, ' — Scadenza macro — Cliente:%') ORDER BY id DESC LIMIT 1");
    $selMacro->bind_param('i',$id); $selMacro->execute(); $macroRow=$selMacro->get_result()->fetch_assoc(); $selMacro->close();
    if ($scadenza){
      $tipo='scadenza';
      $col = lastColorForType($conn,$tipo) ?: defaultColorFor($tipo);
      $nome=$titolo.' — Scadenza — '.$cliente_nome; $note=$descrizione!==''?$descrizione:'Non ci sono note'; $desc='Lavoro #'.$id.' — Scadenza macro — Cliente: '.$cliente_nome; $ass=null;
      if ($macroRow){
        $updM=$conn->prepare("UPDATE flusso_lavoro SET nome=?, descrizione=?, data_evento=?, assegnato_a=?, note=?, tipo=?, colore=? WHERE id=? LIMIT 1");
        $updM->bind_param('sssssssi',$nome,$desc,$scadenza,$ass,$note,$tipo,$col,$macroRow['id']); $updM->execute(); $updM->close();
      } else {
        $insM=$conn->prepare("INSERT INTO flusso_lavoro (nome, creato_il, descrizione, data_evento, assegnato_a, note, tipo, colore) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
        $insM->bind_param('sssssss',$nome,$desc,$scadenza,$ass,$note,$tipo,$col); $insM->execute(); $insM->close();
      }
    } else {
      if ($macroRow){ $conn->query("DELETE FROM flusso_lavoro WHERE id=".(int)$macroRow['id']." LIMIT 1"); }
    }
  }
$stato = recalcJobStateAndSyncCalendar($conn, (int)$id);
  $conn->commit();
foreach ($__audit as $msg) {
  registraAttivita($conn, $msg);
}
$attivitaAgg = fetch_attivita_min($conn, (int)$id);

$out = [
  'success'    => true,
  'stato'      => $stato,
  'attivita'   => $attivitaAgg   // <-- lista aggiornata, già con ex_utente estratto
];
if (isset($out_debug_auto)) $out['debug_auto'] = $out_debug_auto;
if ($stato_warn)            $out['warning']    = $stato_warn;
if (!empty($autoCompleted)) $out['auto_completed'] = true;

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);



} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

function recalcJobStateAndSyncCalendar(mysqli $conn, int $lavoroId): string {
  // Quante attività e quante incomplete
  $tot = 0; $incomplete = 0;
  $q = $conn->query("
    SELECT COUNT(*) AS tot,
           SUM(CASE WHEN COALESCE(completata,0)=0 THEN 1 ELSE 0 END) AS incomplete
    FROM lavori_attivita WHERE lavoro_id = ".(int)$lavoroId
  );
  if ($q && ($r=$q->fetch_assoc())) {
    $tot = (int)($r['tot'] ?? 0);
    $incomplete = (int)($r['incomplete'] ?? 0);
  }

  // stato corrente
  $cur = ''; 
  $rs = $conn->query("SELECT stato FROM lavori WHERE id=".(int)$lavoroId." LIMIT 1");
  if ($rs) $cur = (string)($rs->fetch_assoc()['stato'] ?? '');

  $new = $cur;
  if ($tot > 0 && $incomplete === 0) {
    $new = pickClosingState($conn);
  } elseif (in_array(mb_strtolower($cur,'UTF-8'), ['completato','chiuso'], true) && $incomplete > 0) {
    $new = pickWorkingState($conn);
  }

  if ($new !== $cur) {
    $st = $conn->prepare("UPDATE lavori SET stato=?, updated_at=NOW() WHERE id=? LIMIT 1");
    $st->bind_param('si', $new, $lavoroId);
    $st->execute(); $st->close();

    // Sincronizza eventi flusso_lavoro (come in modifica_lavoro.php)
    if (tableExists($conn,'flusso_lavoro')) {
      $calTipo = mapStatoToCalTipo($new);
      if ($calTipo !== null) {
        $calCol = lastColorForType($conn, $calTipo) ?: defaultColorFor($calTipo);
        $updMeta = $conn->prepare("
          UPDATE flusso_lavoro
             SET tipo=?, colore=?
           WHERE descrizione LIKE CONCAT('Lavoro #', ?, '%')
             AND nome NOT LIKE '% — Completata'
        ");
        $updMeta->bind_param('ssi', $calTipo, $calCol, $lavoroId);
        $updMeta->execute(); $updMeta->close();

        // se chiuso → svuota assegnatari
       // se chiuso → prima tagga ex_utente, poi svuota assegnatari
if (in_array($new, ['completato','chiuso','annullato'], true)) {
  // IMPORTANTISSIMO: se lo stato è cambiato in questa funzione (chiusura automatica),
  // tagga gli ex assegnatari PRIMA di azzerare i campi utente_id.
  tag_ex_assegnatari($conn, (int)$lavoroId);

  if (tableExists($conn,'lavori_attivita')) {
    $conn->query("UPDATE lavori_attivita SET utente_id=NULL WHERE lavoro_id=".(int)$lavoroId);
  }
  $conn->query("UPDATE lavori SET assegnato_a=NULL WHERE id=".(int)$lavoroId." LIMIT 1");
  $conn->query("UPDATE flusso_lavoro SET assegnato_a=NULL WHERE descrizione LIKE CONCAT('Lavoro #', ".(int)$lavoroId.", '%')");
}


        $stamp = date('d/m/Y H:i');
        $extra = "Stato lavoro: ".strtoupper($calTipo)." il ".$stamp;
        $updNote = $conn->prepare("
          UPDATE flusso_lavoro
             SET note = TRIM(CONCAT(COALESCE(note,''), CASE WHEN note IS NULL OR note='' THEN '' ELSE '\n' END, ?))
           WHERE descrizione LIKE CONCAT('Lavoro #', ?, '%')
        ");
        $updNote->bind_param('si', $extra, $lavoroId);
        $updNote->execute(); $updNote->close();
      }
    }
  }

  return $new ?: $cur ?: 'aperto';
}

