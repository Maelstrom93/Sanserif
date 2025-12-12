<?php
// file: lavori/salva_lavoro.php
session_start();
require_once '../assets/funzioni/funzioni.php';
require_once __DIR__ . '/../assets/funzioni/authz.php';
require_once '../assets/funzioni/db/db.php';
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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = db();
$conn->set_charset('utf8mb4');

function defaultColorFor($tipo){
  $k = mb_strtolower(trim((string)$tipo), 'UTF-8');
  if ($k === 'articolo')  return '#0EA5E9';
  if ($k === 'revisione') return '#F59E0B';
  if ($k === 'incontro')  return '#10B981';
  if ($k === 'scadenza')  return '#EF4444';
  if ($k === 'lavorazione') return '#2563EB';
  return '#64748B';
}
function lastColorForType(mysqli $conn, $tipo){
  if (!tableExists($conn,'flusso_lavoro')) return null;
  $stmt = $conn->prepare("SELECT colore FROM flusso_lavoro WHERE tipo=? AND TRIM(COALESCE(colore,''))<>'' ORDER BY id DESC LIMIT 1");
  $stmt->bind_param('s', $tipo);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $c = trim((string)($row['colore'] ?? ''));
  return $c !== '' ? $c : null;
}
function clean_date($s){
  $s = trim((string)$s);
  if ($s === '') return null;
  $ts = strtotime($s);
  return $ts ? date('Y-m-d', $ts) : null;
}
function clean_float($v){
  if ($v === '' || $v === null) return null;
  if (is_string($v)) $v = str_replace(',', '.', $v);
  return is_numeric($v) ? (float)$v : null;
}
function getCategoriaNome(mysqli $conn, $cid){
  if ($cid === null) return '';
  if (!tableExists($conn,'categorie_lavoro')) return '';
  $r = $conn->query("SELECT TRIM(nome) AS n FROM categorie_lavoro WHERE id=".(int)$cid." LIMIT 1");
  return $r ? (string)($r->fetch_assoc()['n'] ?? '') : '';
}

// ===== Input macro =====
$titolo         = trim(isset($_POST['titolo']) ? $_POST['titolo'] : '');
$stato          = isset($_POST['stato']) ? $_POST['stato'] : 'aperto';
$cliente_sel    = isset($_POST['cliente_sel']) ? $_POST['cliente_sel'] : '';
$data_ricezione = clean_date(isset($_POST['data_ricezione']) ? $_POST['data_ricezione'] : '');
$scadenza       = clean_date(isset($_POST['scadenza']) ? $_POST['scadenza'] : '');
$provenienza    = trim(isset($_POST['provenienza']) ? $_POST['provenienza'] : '');
$prezzo_override= clean_float(isset($_POST['prezzo']) ? $_POST['prezzo'] : null);
$descrizione    = trim(isset($_POST['descrizione']) ? $_POST['descrizione'] : '');
$cartelle_json  = (string)(isset($_POST['cartelle_json']) ? $_POST['cartelle_json'] : '[]');
$assegnato_a    = (isset($_POST['assegnato_a']) && ctype_digit((string)$_POST['assegnato_a'])) ? (int)$_POST['assegnato_a'] : null;

$nuove_cat      = trim(isset($_POST['nuove_categorie']) ? $_POST['nuove_categorie'] : '');
$cat_sel        = isset($_POST['categorie']) ? $_POST['categorie'] : [];

// evento macro
$macro_tipo_sel = isset($_POST['macro_evento_tipo_sel']) ? $_POST['macro_evento_tipo_sel'] : 'scadenza';
$macro_tipo_new = trim(isset($_POST['macro_evento_tipo_new']) ? $_POST['macro_evento_tipo_new'] : '');
$macro_color    = trim(isset($_POST['macro_evento_color']) ? $_POST['macro_evento_color'] : '');

// ===== RIGHE =====
$righe_utente_id       = (array)(isset($_POST['righe_utente_id']) ? $_POST['righe_utente_id'] : []);
$righe_categoria_id    = (array)(isset($_POST['righe_categoria_id']) ? $_POST['righe_categoria_id'] : []);
$righe_categoria_new   = (array)(isset($_POST['righe_categoria_new']) ? $_POST['righe_categoria_new'] : []);
$righe_titolo          = (array)(isset($_POST['righe_titolo']) ? $_POST['righe_titolo'] : []);
$righe_scadenza        = (array)(isset($_POST['righe_scadenza']) ? $_POST['righe_scadenza'] : []);
$righe_prezzo          = (array)(isset($_POST['righe_prezzo']) ? $_POST['righe_prezzo'] : []);
$righe_descrizione     = (array)(isset($_POST['righe_descrizione']) ? $_POST['righe_descrizione'] : []);
$righe_et_sel          = (array)(isset($_POST['righe_evento_tipo_sel']) ? $_POST['righe_evento_tipo_sel'] : []);
$righe_et_new          = (array)(isset($_POST['righe_evento_tipo_new']) ? $_POST['righe_evento_tipo_new'] : []);
$righe_color           = (array)(isset($_POST['righe_evento_color']) ? $_POST['righe_evento_color'] : []);

if ($titolo === '' || !$data_ricezione) die('Titolo e data di ricezione sono obbligatori.');

// Cliente id + nome
$cliente_id = null;
$cliente_nome = '';
if ($cliente_sel !== '' && ctype_digit($cliente_sel)) {
  $cliente_id = (int)$cliente_sel;
  if (tableExists($conn,'clienti')) {
    $col = tableHasColumn($conn,'clienti','nome') ? 'nome' : (tableHasColumn($conn,'clienti','rgs') ? 'rgs' : null);
    if ($col) {
      $r=$conn->query("SELECT TRIM($col) AS n FROM clienti WHERE id=$cliente_id LIMIT 1");
      $cliente_nome = $r? (string)($r->fetch_assoc()['n'] ?? '') : '';
    }
  }
  if ($cliente_nome==='') $cliente_nome = 'Cliente #'.$cliente_id;
}

// normalizza cartelle_json
$tmpDecoded = json_decode($cartelle_json, true);
if (!is_array($tmpDecoded)) $tmpDecoded = [];
$cartelleArr = [];
foreach ($tmpDecoded as $v) {
  $vv = trim((string)$v);
  if ($vv !== '') $cartelleArr[] = $vv;
}
$cartelle_json = json_encode(array_values($cartelleArr), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// somma righe
$somma_righe = 0.0;
foreach ($righe_prezzo as $p) { $val = clean_float($p); if ($val !== null) $somma_righe += $val; }
$totale = $prezzo_override !== null ? $prezzo_override : ($somma_righe ?: null);

$conn->begin_transaction();
try {
  // 1) lavoro (+ assegnatario macro)
  $ins = $conn->prepare("
    INSERT INTO lavori (titolo, cliente_id, assegnato_a, data_ricezione, scadenza, prezzo, provenienza, cartelle_json, descrizione, stato, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?, NOW(), NOW())
  ");
  $ins->bind_param('siissdssss',
    $titolo, $cliente_id, $assegnato_a, $data_ricezione, $scadenza, $totale, $provenienza, $cartelle_json, $descrizione, $stato
  );
  $ins->execute();
  $lavoro_id = (int)$ins->insert_id;
  $ins->close();

  // 2) categorie macro (+ nuove)
  $cat_ids = [];
  foreach ((array)$cat_sel as $raw){ $raw=trim((string)$raw); if ($raw!=='' && ctype_digit($raw)) $cat_ids[]=(int)$raw; }
if ($nuove_cat !== '') { $cat_ids = risolviCategorieLavoro($conn, $nuove_cat, $cat_ids ?: []); }
  $cat_ids = array_values(array_unique(array_map('intval', $cat_ids)));

  // 3) righe + eventi per-riga
  $insR = $conn->prepare("
    INSERT INTO lavori_attivita (lavoro_id, utente_id, categoria_id, titolo, descrizione, scadenza, prezzo)
    VALUES (?,?,?,?,?,?,?)
  ");
  $created_rows = 0;
  $n = max(count($righe_utente_id), count($righe_categoria_id), count($righe_prezzo), count($righe_scadenza), count($righe_titolo), count($righe_descrizione), count($righe_et_sel), count($righe_color), count($righe_categoria_new));
  for ($i=0; $i<$n; $i++){
    $uid = isset($righe_utente_id[$i]) && ctype_digit((string)$righe_utente_id[$i]) ? (int)$righe_utente_id[$i] : null;

    // Categoria: esistente o nuova al volo
    $cid = null;
    $rawCat = isset($righe_categoria_id[$i]) ? (string)$righe_categoria_id[$i] : '';
    if ($rawCat !== '' && $rawCat !== '__new__' && ctype_digit($rawCat)) {
      $cid = (int)$rawCat;
   } elseif ($rawCat === '__new__') {
  $newCatName = trim((string)($righe_categoria_new[$i] ?? ''));
  if ($newCatName !== '') {
    $newIds = risolviCategorieLavoro($conn, $newCatName, []);
    if (!empty($newIds)) $cid = (int)$newIds[0];
  }
}

    $tit = trim((string)($righe_titolo[$i] ?? ''));
    $des = trim((string)($righe_descrizione[$i] ?? ''));
    $sca = clean_date($righe_scadenza[$i] ?? null);
    $prz = clean_float($righe_prezzo[$i] ?? null);

    if ($uid===null && $cid===null && $prz===null && !$tit && !$des && !$sca) continue;

    $insR->bind_param('iiisssd', $lavoro_id, $uid, $cid, $tit, $des, $sca, $prz);
    $insR->execute();
    $attivita_id = (int)$conn->insert_id;
    $created_rows++;
    // evento singola riga
    if ($sca && tableExists($conn,'flusso_lavoro')) {
      $tipoSel = (string)($righe_et_sel[$i] ?? 'lavorazione');
      $tipo = $tipoSel === '__new__'
        ? (trim((string)($righe_et_new[$i] ?? 'lavorazione')) ?: 'lavorazione')
        : ($tipoSel ?: 'lavorazione');

      $colore = trim((string)($righe_color[$i] ?? ''));
      if ($colore === '') $colore = (lastColorForType($conn,$tipo) ?: defaultColorFor($tipo));

      $titAtt  = $tit !== '' ? $tit : 'Attività';
      $catName = getCategoriaNome($conn, $cid);
      // Titolo evento: Lavoro — Categoria (se c'è) — Cliente
      $nome    = $titolo . ' — ' . ($catName !== '' ? $catName : $titAtt) . ' — ' . $cliente_nome;
      $note    = $des !== '' ? $des : 'Non ci sono note';
      $descEv  = 'Lavoro #'.$lavoro_id.' / Attività #'.$attivita_id.' — Cliente: '.$cliente_nome;
      $ass     = $uid !== null ? (string)$uid : '';

      $e = $conn->prepare("INSERT INTO flusso_lavoro (nome, creato_il, descrizione, data_evento, assegnato_a, note, tipo, colore) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
      $e->bind_param('sssssss', $nome, $descEv, $sca, $ass, $note, $tipo, $colore);
      $e->execute();
      $evento_id = (int)$conn->insert_id;
      $e->close();

      $up = $conn->prepare("UPDATE lavori_attivita SET evento_id = ? WHERE id=? LIMIT 1");
      $up->bind_param('ii', $evento_id, $attivita_id);
      $up->execute(); $up->close();
    }

    if ($cid !== null) $cat_ids[] = (int)$cid;
  }
  $insR->close();
// === AUTO: crea attività per ogni categoria selezionata (scenario mono-utente)
// Solo se: c'è un assegnatario macro, NON sono state inserite righe manuali,
// e ci sono categorie selezionate/nuove già risolte in $cat_ids.
if ($assegnato_a !== null && $created_rows === 0 && !empty($cat_ids)) {

  // Prepara insert attività
  $insAuto = $conn->prepare("
    INSERT INTO lavori_attivita (lavoro_id, utente_id, categoria_id, titolo, descrizione, scadenza, prezzo)
    VALUES (?,?,?,?,?,?,?)
  ");

  foreach ($cat_ids as $cid) {
    $catName = getCategoriaNome($conn, $cid);
    $tit     = $catName !== '' ? $catName : 'Attività';
    $des     = '';                 // nessuna descrizione aggiuntiva
    $sca     = $scadenza;          // usa scadenza macro
    $prz     = null;               // prezzo vuoto (override comanda)

    // insert attività
    $insAuto->bind_param('iiisssd', $lavoro_id, $assegnato_a, $cid, $tit, $des, $sca, $prz);
    $insAuto->execute();
    $attivita_id = (int)$conn->insert_id;

    // crea evento collegato se ho una scadenza e la tabella esiste
    if ($sca && tableExists($conn,'flusso_lavoro')) {
      $tipo   = 'lavorazione';
      $colore = (lastColorForType($conn,$tipo) ?: defaultColorFor($tipo));

      // Titolo evento: Lavoro — Categoria — Cliente (come nelle righe)
      $nome    = $titolo . ' — ' . $tit . ' — ' . $cliente_nome;
      $note    = 'Non ci sono note';
      $descEv  = 'Lavoro #'.$lavoro_id.' / Attività #'.$attivita_id.' — Cliente: '.$cliente_nome;
      $ass     = (string)$assegnato_a;

      $e = $conn->prepare("INSERT INTO flusso_lavoro (nome, creato_il, descrizione, data_evento, assegnato_a, note, tipo, colore)
                           VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
      $e->bind_param('sssssss', $nome, $descEv, $sca, $ass, $note, $tipo, $colore);
      $e->execute();
      $evento_id = (int)$conn->insert_id;
      $e->close();

      // collega evento all'attività
      $up = $conn->prepare("UPDATE lavori_attivita SET evento_id = ? WHERE id=? LIMIT 1");
      $up->bind_param('ii', $evento_id, $attivita_id);
      $up->execute();
      $up->close();
    }
  }
  $insAuto->close();
}

  // 4) pivot categorie lavoro (complessive)
  $cat_ids = array_values(array_unique(array_map('intval', $cat_ids)));
  if ($cat_ids && tableExists($conn,'lavori_categorie')) {
    $p = $conn->prepare("INSERT IGNORE INTO lavori_categorie (lavoro_id, categoria_id) VALUES (?,?)");
    foreach ($cat_ids as $cid){ $p->bind_param('ii', $lavoro_id, $cid); $p->execute(); }
    $p->close();
  }

  // 5) evento macro (se scadenza impostata) — usa assegnatario macro se presente
  if ($scadenza && tableExists($conn,'flusso_lavoro')) {
    $tipo = $macro_tipo_sel === '__new__' ? ($macro_tipo_new ?: 'scadenza') : $macro_tipo_sel;
    $colore = $macro_color !== '' ? $macro_color : (lastColorForType($conn,$tipo) ?: defaultColorFor($tipo));
    $nome = $titolo . ' — Scadenza — ' . $cliente_nome;
    $note = $descrizione !== '' ? $descrizione : 'Non ci sono note';
    $descEv = 'Lavoro #'.$lavoro_id.' — Scadenza macro — Cliente: '.$cliente_nome;
    $ass = ($assegnato_a !== null) ? (string)$assegnato_a : '';

    $e = $conn->prepare("INSERT INTO flusso_lavoro (nome, creato_il, descrizione, data_evento, assegnato_a, note, tipo, colore) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)");
    $e->bind_param('sssssss', $nome, $descEv, $scadenza, $ass, $note, $tipo, $colore);
    $e->execute(); $e->close();
  }

  // 6) riallineo prezzo se non override
  if ($prezzo_override === null) {
    $tot = $somma_righe ?: null;
    $upd = $conn->prepare("UPDATE lavori SET prezzo = ? WHERE id = ? LIMIT 1");
    $upd->bind_param('si', $tot, $lavoro_id);
    $upd->execute(); $upd->close();
  }

  $conn->commit();
  header('Location: ../calendario/calendario.php?msg=lavoro_ok');
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  die('Errore salvataggio: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

/**
 * Crea (se mancante) e restituisce gli id in categorie_lavoro a partire da:
 * - stringa CSV "Traduzione, Editing"
 * - o array di nomi
 * $accumulator permette di aggiungere a una lista di id già raccolti
 */
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

  // Inserisci se non esiste (UNIQUE su nome) e recupera id
  $ins = $conn->prepare("INSERT IGNORE INTO categorie_lavoro (nome) VALUES (?)");
  $sel = $conn->prepare("SELECT id FROM categorie_lavoro WHERE nome = ? LIMIT 1");

  foreach ($nomi as $nome) {
    $ins->bind_param('s', $nome);
    $ins->execute();

    $sel->bind_param('s', $nome);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if ($row && isset($row['id'])) $accumulator[] = (int)$row['id'];
  }
  $ins->close(); $sel->close();

  return array_values(array_unique($accumulator));
}
