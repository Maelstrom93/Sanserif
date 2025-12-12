<?php
// file: lavori/toggle_attivita.php
session_start();
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/authz.php';
require_once '../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/authz.php';

requireLogin();
// === SOLO ADMIN: users.manage richiesto ==============================
$IS_ADMIN = currentUserCan('users.manage');
if (!$IS_ADMIN) {
  // Config pagina (icona, etichetta, permessi richiesti)
  $SEZIONE_ICON  = 'fa-envelope';
  $SEZIONE_LABEL = 'Richieste';
  $RICHIESTI     = ['users.manage'];

  // helper e()
  if (!function_exists('e')) {
    function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
  }

  // helper etichette permessi + display utente (riusabili altrove)
  $PERM_LABELS = [
    'users.manage'   => 'Amministrazione',
    'portfolio.view' => 'Accesso al portfolio',
    'blog.view'      => 'Accesso agli articoli',
    // aggiungi qui altre mappature se ti servono
  ];
  if (!function_exists('permLabel')) {
    function permLabel(string $slug): string {
      global $PERM_LABELS; return $PERM_LABELS[$slug] ?? $slug;
    }
  }
  if (!function_exists('currentUserDisplay')) {
    function currentUserDisplay(): string {
      $u = $_SESSION['utente'] ?? [];
      $pieces = array_filter([trim($u['nome'] ?? ''), trim($u['cognome'] ?? '')]);
      if ($pieces) return implode(' ', $pieces);
      if (!empty($u['username'])) return (string)$u['username'];
      if (!empty($u['email']))    return (string)$u['email'];
      if (!empty($u['id']))       return '#'.(int)$u['id'];
      return 'utente';
    }
  }

  http_response_code(403);
  ?>
  <!DOCTYPE html>
  <html lang="it">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>403 • Accesso negato</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style1.css">
    <style>
      .hero403{padding:28px 22px;border-radius:14px;background:linear-gradient(135deg,#f8fafc 0%,#eef7fb 100%);border:1px solid #dbe8ef}
      .hero403 h1{margin:0 0 6px 0;font-size:22px;color:#0f172a;display:flex;align-items:center;gap:10px}
      .hero403 p{margin:6px 0 0 0;color:#334155}
      .hero403 .tips{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
      .hero403 .chip{font-weight:600}
      .hero403 .need{margin-top:10px;font-size:13px;color:#475569}
      .hero403 .need code{background:#fff;border:1px solid #e2e8f0;padding:2px 6px;border-radius:6px}
    </style>
  </head>
  <body>
  <main>
    <header class="topbar" style="margin-bottom:12px;">
      <div class="user-badge">
        <i class="fas <?= e($SEZIONE_ICON) ?> icon-user"></i>
        <div>
          <div class="muted">Gestione</div>
          <div style="font-weight:800; letter-spacing:.2px;"><?= e($SEZIONE_LABEL) ?></div>
        </div>
        <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
      </div>
      <div class="right">
        <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <?php include '../partials/navbar.php'; ?>
      </div>
    </header>

    <section class="panel">
      <div class="hero403">
        <h1><i class="fa-solid fa-lock"></i> Accesso negato</h1>
        <p>Non disponi delle autorizzazioni necessarie per visualizzare questa sezione.</p>

        <div class="need">
          Permessi richiesti:
          <?php foreach ($RICHIESTI as $perm): ?>
            <code><?= e(permLabel($perm)) ?></code>
          <?php endforeach; ?>
          &middot; Utente: <code><?= e(currentUserDisplay()) ?></code>
        </div>

        <div class="tips">
          <a class="chip" href="/backend/index.php"><i class="fa-solid fa-house"></i> Vai alla Dashboard</a>
          <button class="chip" onclick="history.back()"><i class="fa-solid fa-rotate-left"></i> Torna indietro</button>
        </div>
      </div>
    </section>
  </main>
  <?php include '../partials/footer.php'; ?>
  </body>
  </html>
  <?php
  exit;
}
$uid    = (int)($_SESSION['utente']['id'] ?? 0);
$actId  = (int)($payload['id'] ?? 0);

$canAny = currentUserCan('attivita.complete_any');
$canOwn = currentUserCan('attivita.complete_own');

if (!$canAny) {
  // recupera assegnatario dell'attività
  $stmt = $conn->prepare('SELECT utente_id FROM attivita WHERE id = ? LIMIT 1');
  $stmt->bind_param('i', $actId);
  $stmt->execute();
  $stmt->bind_result($ass);
  $stmt->fetch();
  $stmt->close();

  if (!$canOwn || (int)$ass !== $uid) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'err'=>'Non autorizzato']);
    exit;
  }
}

requireLogin();

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = db();
$conn->set_charset('utf8mb4');

/* ------------ Helpers ------------ */
function defaultColorFor($tipo){
  $k = mb_strtolower(trim((string)$tipo), 'UTF-8');
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
function lastColorForType(mysqli $conn, $tipo){
  if (!tableExists($conn,'flusso_lavoro')) return null;
  $stmt=$conn->prepare("SELECT colore FROM flusso_lavoro WHERE tipo=? AND TRIM(COALESCE(colore,''))<>'' ORDER BY id DESC LIMIT 1");
  $stmt->bind_param('s',$tipo); $stmt->execute();
  $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
  $c=trim((string)($row['colore']??'')); return $c!==''?$c:null;
}
function json_out($arr, $code=200){
  http_response_code($code);
  while (ob_get_level()) ob_end_clean();
  echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

/* --- Stati ammessi + chooser per compatibilità ENUM --- */
function getAllowedStati(mysqli $conn): array {
  $res = $conn->query("SHOW COLUMNS FROM lavori LIKE 'stato'");
  if (!$res || !($col = $res->fetch_assoc())) return [];
  $type = (string)$col['Type'];
  if (stripos($type, "enum(")!==0) return [];
  preg_match_all("/'([^']+)'/u", $type, $m);
  return $m[1] ?? [];
}
function pickClosingState(mysqli $conn): string {
  $a = getAllowedStati($conn);
  if (in_array('completato',$a,true)) return 'completato';
  if (in_array('chiuso',$a,true))     return 'chiuso';
  return $a[0] ?? 'aperto';
}
function pickWorkingState(mysqli $conn): string {
  $a = getAllowedStati($conn);
  if (in_array('in_lavorazione',$a,true)) return 'in_lavorazione';
  if (in_array('aperto',$a,true))         return 'aperto';
  return $a[0] ?? 'aperto';
}

/* --- Aggiorna lo stato del lavoro in base alle lavorazioni --- */
function autoupdate_lavoro_stato(mysqli $conn, int $lavoroId): string {
  $tot=0; $inc=0; $statoNow='';
  $q = $conn->query("
    SELECT COUNT(*) AS tot,
           SUM(CASE WHEN COALESCE(completata,0)=0 THEN 1 ELSE 0 END) AS inc
    FROM lavori_attivita WHERE lavoro_id = ".(int)$lavoroId
  );
  if ($q && ($r=$q->fetch_assoc())) { $tot=(int)$r['tot']; $inc=(int)($r['inc']??0); }
  $r = $conn->query("SELECT stato FROM lavori WHERE id=".(int)$lavoroId." LIMIT 1");
  if ($r && ($row=$r->fetch_assoc())) $statoNow = (string)$row['stato'];

  if ($tot > 0 && $inc === 0) {
    $target = pickClosingState($conn);
    if (mb_strtolower($statoNow,'UTF-8') !== mb_strtolower($target,'UTF-8')){
      $st = $conn->prepare("UPDATE lavori SET stato=?, updated_at=NOW() WHERE id=? LIMIT 1");
      $st->bind_param('si', $target, $lavoroId); $st->execute(); $st->close();
      $statoNow = $target;
    }
  } elseif ($tot > 0 && $inc > 0) {
    $reopen = pickWorkingState($conn);
    if (in_array(mb_strtolower($statoNow,'UTF-8'), ['completato','chiuso'], true) &&
        mb_strtolower($statoNow,'UTF-8') !== mb_strtolower($reopen,'UTF-8')){
      $st = $conn->prepare("UPDATE lavori SET stato=?, updated_at=NOW() WHERE id=? LIMIT 1");
      $st->bind_param('si', $reopen, $lavoroId); $st->execute(); $st->close();
      $statoNow = $reopen;
    }
  }
  return $statoNow ?: 'aperto';
}

/* ------------ Input (accetta JSON o form) ------------ */
try{
  $raw = file_get_contents('php://input');
  $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
  if ($raw && stripos($ct,'application/json')===0){
    $j = json_decode($raw, true);
    if (is_array($j)) $_POST = array_merge($_POST, $j);
  }

  if (!isset($_POST['id'])) json_out(['ok'=>false,'err'=>'ID attività mancante'],400);
  $attId = (int)$_POST['id'];
  $done  = false;
  if (isset($_POST['done'])){
    $v = $_POST['done'];
    $done = ($v===1 || $v==='1' || $v===true || $v==='true' || $v==='on');
  }

  // Colonna cliente dinamica
  $clienteCol = null;
  if (tableExists($conn,'clienti')) {
    if (tableHasColumn($conn,'clienti','nome')) $clienteCol='nome';
    elseif (tableHasColumn($conn,'clienti','rgs')) $clienteCol='rgs';
  }

  // Carica attività + contesto (incluso evento_id e stato lavoro)
  $sql = "
    SELECT a.id, a.lavoro_id, a.utente_id, a.titolo, a.categoria_id,
           a.evento_id, a.completata, a.completato_il,
           l.titolo AS lavoro_titolo, l.cliente_id, l.stato AS lavoro_stato
           ".($clienteCol? ", TRIM(c.$clienteCol) AS cliente_nome" : ", '' AS cliente_nome")."
    FROM lavori_attivita a
    JOIN lavori l ON l.id = a.lavoro_id
    ".($clienteCol? "LEFT JOIN clienti c ON c.id = l.cliente_id" : "")."
    WHERE a.id = ?
    LIMIT 1
  ";
  $q = $conn->prepare($sql);
  $q->bind_param('i',$attId);
  $q->execute();
  $att = $q->get_result()->fetch_assoc();
  $q->close();
  if (!$att) json_out(['ok'=>false,'err'=>'Attività non trovata'],404);

  // Nome categoria (se esiste) — allineato a categorie_lavoro
  $catName = '';
  if (!empty($att['categoria_id']) && tableExists($conn,'categorie_lavoro')){
    $r=$conn->query("SELECT TRIM(nome) AS n FROM categorie_lavoro WHERE id=".(int)$att['categoria_id']." LIMIT 1");
    $catName = $r ? (string)($r->fetch_assoc()['n'] ?? '') : '';
  }

  $utente_corrente_id = isset($_SESSION['utente']['id']) ? (int)$_SESSION['utente']['id'] : null;

  $conn->begin_transaction();

  if ($done){
    // ---- Marca completata
    $tsNow = date('Y-m-d H:i:s');

    if (tableHasColumn($conn,'lavori_attivita','completato_da')){
      if ($utente_corrente_id === null){
        $u=$conn->prepare("UPDATE lavori_attivita SET completata=1, completato_il=?, completato_da=NULL WHERE id=? LIMIT 1");
        $u->bind_param('si',$tsNow,$attId);
      } else {
        $u=$conn->prepare("UPDATE lavori_attivita SET completata=1, completato_il=?, completato_da=? WHERE id=? LIMIT 1");
        $u->bind_param('sii',$tsNow,$utente_corrente_id,$attId);
      }
    } elseif (tableHasColumn($conn,'lavori_attivita','completato_il')){
      $u=$conn->prepare("UPDATE lavori_attivita SET completata=1, completato_il=? WHERE id=? LIMIT 1");
      $u->bind_param('si',$tsNow,$attId);
    } else {
      $u=$conn->prepare("UPDATE lavori_attivita SET completata=1 WHERE id=? LIMIT 1");
      $u->bind_param('i',$attId);
    }
    $u->execute(); $u->close();

    // ---- Aggiorna l'evento collegato (NO nuove righe)
    if (tableExists($conn,'flusso_lavoro')){
      $evento_id = (int)($att['evento_id'] ?? 0);
      if ($evento_id > 0){
        $cliente   = trim((string)($att['cliente_nome'] ?? ''));
        $attTitle  = trim((string)($att['titolo'] ?? ''));
        if ($attTitle==='') $attTitle = ($catName!=='' ? $catName : 'Attività');
        $baseName  = $att['lavoro_titolo'].' — '.$attTitle.($cliente ? ' — '.$cliente : '');
        $desc      = 'Lavoro #'.$att['lavoro_id'].' / Attività #'.$att['id'].($cliente ? ' — Cliente: '.$cliente : '');
        $noteAdd   = 'Segnata come completata'.($utente_corrente_id ? ' da #'.$utente_corrente_id : '').' il '.$tsNow;

        $tipo   = 'completato';
        $colore = lastColorForType($conn,$tipo) ?: defaultColorFor($tipo);
        $ass    = !empty($att['utente_id']) ? (string)$att['utente_id'] : null;

        $up = $conn->prepare("
          UPDATE flusso_lavoro
             SET nome = CASE WHEN nome LIKE CONCAT('%', ' — Completata') THEN nome ELSE CONCAT(?, ' — Completata') END,
                 descrizione = ?,
                 data_evento = COALESCE(data_evento, ?),
                 assegnato_a = ?,
                 note = TRIM(CONCAT(COALESCE(note,''), CASE WHEN note IS NULL OR note='' THEN '' ELSE '\n' END, ?)),
                 tipo = ?,
                 colore = ?
           WHERE id = ? LIMIT 1
        ");
        $up->bind_param('sssssssi', $baseName, $desc, $tsNow, $ass, $noteAdd, $tipo, $colore, $evento_id);
        $up->execute(); $up->close();
      }
    }

    // >>> AUTOCOMPLETA/RIAPRE STATO LAVORO (dentro la stessa transazione)
    $statoJob = autoupdate_lavoro_stato($conn, (int)$att['lavoro_id']);

    // --- BEGIN: tag ex-assegnatari quando il lavoro diventa "closing"
    $closingStates = ['completato','chiuso','annullato'];
    $prev   = mb_strtolower((string)($att['lavoro_stato'] ?? ''), 'UTF-8');
    $stNow  = mb_strtolower((string)$statoJob, 'UTF-8');
    $becameClosing = in_array($stNow, $closingStates, true) && !in_array($prev, $closingStates, true);

    if ($becameClosing) {
      // prendi tutte le attività con un utente assegnato
      $rs = $conn->prepare("
        SELECT la.id, la.utente_id,
               COALESCE(NULLIF(TRIM(u.nome),''), NULLIF(TRIM(u.email),''), CONCAT('#', u.id)) AS ulabel,
               la.descrizione
          FROM lavori_attivita la
          LEFT JOIN utenti u ON u.id = la.utente_id
         WHERE la.lavoro_id = ? AND la.utente_id IS NOT NULL
      ");
      $lid = (int)$att['lavoro_id'];
      $rs->bind_param('i', $lid);
      $rs->execute();
      $cur = $rs->get_result();

      while ($row = $cur->fetch_assoc()){
        $rid   = (int)$row['id'];
        $label = trim((string)($row['ulabel'] ?? ''));
        if ($label==='') $label = '#'.(int)$row['utente_id'];
        $desc  = (string)($row['descrizione'] ?? '');

        // se manca il tag, aggiungilo (regex tollerante: ex_utente / ex-utente / ex utente)
        if (!preg_match('/\[(?:ex[_\s-]?utente)\s*:\s*.+?\]/iu', $desc)) {
          $newDesc = rtrim($desc).($desc!=='' ? "\n" : '').'[ex_utente: '.$label.']';
          $up = $conn->prepare("UPDATE lavori_attivita SET descrizione=? WHERE id=? LIMIT 1");
          $up->bind_param('si', $newDesc, $rid);
          $up->execute();
          $up->close();
        }
      }
      $rs->close();

      // svuota gli assegnatari (come fai nel percorso da form)
      $conn->query("UPDATE lavori_attivita SET utente_id = NULL WHERE lavoro_id = ".(int)$att['lavoro_id']);
      $conn->query("UPDATE lavori SET assegnato_a = NULL WHERE id = ".(int)$att['lavoro_id']." LIMIT 1");

      // pulisci anche gli eventi del lavoro
      if (tableExists($conn,'flusso_lavoro')) {
        $conn->query("UPDATE flusso_lavoro SET assegnato_a = NULL WHERE descrizione LIKE CONCAT('Lavoro #', ".(int)$att['lavoro_id'].", '%')");
      }
    }
    // --- END

    $conn->commit();
    json_out([
      'ok'=>true,
      'completata'=>1,
      'stato_lavoro'=>$statoJob,
      'completato_il'=>$tsNow,
      'completato_il_readable'=>date('d/m/Y H:i', strtotime($tsNow))
    ]);

  } else {
    // ---- Riapri
    if (tableHasColumn($conn,'lavori_attivita','completato_da')){
      $u=$conn->prepare("UPDATE lavori_attivita SET completata=0, completato_il=NULL, completato_da=NULL WHERE id=? LIMIT 1");
      $u->bind_param('i',$attId);
    } elseif (tableHasColumn($conn,'lavori_attivita','completato_il')){
      $u=$conn->prepare("UPDATE lavori_attivita SET completata=0, completato_il=NULL WHERE id=? LIMIT 1");
      $u->bind_param('i',$attId);
    } else {
      $u=$conn->prepare("UPDATE lavori_attivita SET completata=0 WHERE id=? LIMIT 1");
      $u->bind_param('i',$attId);
    }
    $u->execute(); $u->close();

    // Riallinea evento
    if (tableExists($conn,'flusso_lavoro')){
      $evento_id = (int)($att['evento_id'] ?? 0);
      if ($evento_id > 0){
        $tsNow = date('Y-m-d H:i:s');
        $cliente   = trim((string)($att['cliente_nome'] ?? ''));
        $attTitle  = trim((string)($att['titolo'] ?? ''));
        if ($attTitle==='') $attTitle = ($catName!=='' ? $catName : 'Attività');
        $nomeNew   = $att['lavoro_titolo'].' — '.$attTitle.($cliente ? ' — '.$cliente : '');
        $noteAdd   = 'Attività riaperta'.($utente_corrente_id ? ' da #'.$utente_corrente_id : '').' il '.$tsNow;

        $tipoEv = 'lavorazione';
        $col    = lastColorForType($conn,$tipoEv) ?: defaultColorFor($tipoEv);
        $ass    = !empty($att['utente_id']) ? (string)$att['utente_id'] : null;

        $up = $conn->prepare("
          UPDATE flusso_lavoro
             SET nome = CASE 
                         WHEN nome LIKE CONCAT('%', ' — Completata') 
                         THEN SUBSTRING(nome, 1, CHAR_LENGTH(nome) - CHAR_LENGTH(' — Completata')) 
                         ELSE ? 
                        END,
                 assegnato_a = ?,
                 note = TRIM(CONCAT(COALESCE(note,''), CASE WHEN note IS NULL OR note='' THEN '' ELSE '\n' END, ?)),
                 tipo = ?,
                 colore = ?
           WHERE id = ? LIMIT 1
        ");
        $up->bind_param('sssssi', $nomeNew, $ass, $noteAdd, $tipoEv, $col, $evento_id);
        $up->execute(); $up->close();
      }
    }

    // >>> AUTOCOMPLETA/RIAPRE STATO LAVORO
    $statoJob = autoupdate_lavoro_stato($conn, (int)$att['lavoro_id']);

    // --- BEGIN: tag ex-assegnatari quando il lavoro diventa "closing"
    $closingStates = ['completato','chiuso','annullato'];
    $prev   = mb_strtolower((string)($att['lavoro_stato'] ?? ''), 'UTF-8');
    $stNow  = mb_strtolower((string)$statoJob, 'UTF-8');
    $becameClosing = in_array($stNow, $closingStates, true) && !in_array($prev, $closingStates, true);

    if ($becameClosing) {
      $rs = $conn->prepare("
        SELECT la.id, la.utente_id,
               COALESCE(NULLIF(TRIM(u.nome),''), NULLIF(TRIM(u.email),''), CONCAT('#', u.id)) AS ulabel,
               la.descrizione
          FROM lavori_attivita la
          LEFT JOIN utenti u ON u.id = la.utente_id
         WHERE la.lavoro_id = ? AND la.utente_id IS NOT NULL
      ");
      $lid = (int)$att['lavoro_id'];
      $rs->bind_param('i', $lid);
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

      $conn->query("UPDATE lavori_attivita SET utente_id = NULL WHERE lavoro_id = ".(int)$att['lavoro_id']);
      $conn->query("UPDATE lavori SET assegnato_a = NULL WHERE id = ".(int)$att['lavoro_id']." LIMIT 1");

      if (tableExists($conn,'flusso_lavoro')) {
        $conn->query("UPDATE flusso_lavoro SET assegnato_a = NULL WHERE descrizione LIKE CONCAT('Lavoro #', ".(int)$att['lavoro_id'].", '%')");
      }
    }
    // --- END

    $conn->commit();
    json_out(['ok'=>true,'completata'=>0,'stato_lavoro'=>$statoJob]);
  }

} catch (Throwable $ex){
  if ($conn) { try { $conn->rollback(); } catch(Throwable $e){} }
  json_out(['ok'=>false,'err'=>$ex->getMessage()], 400);
}
