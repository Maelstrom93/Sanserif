<?php
// file: backend/admin/gestione_utenti.php
session_start();

require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
require_once __DIR__ . '/../assets/funzioni/authz.php';

/* -------------------------------------------------
   RBAC fallback helpers (se non già definiti)
------------------------------------------------- */
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('setFlash')) {
  function setFlash($msg){ $_SESSION['_flash_msg'] = (string)$msg; }
}
if (!function_exists('getFlash')) {
  function getFlash(){ $m = $_SESSION['_flash_msg'] ?? ''; unset($_SESSION['_flash_msg']); return $m; }
}
if (!function_exists('emailEsiste')) {
  function emailEsiste($conn, $email, $excludeId = 0){
    $sql = "SELECT id FROM utenti WHERE email = ? AND id <> ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $email, $excludeId);
    $stmt->execute();
    $res = $stmt->get_result();
    return (bool)$res->fetch_assoc();
  }
}



requireLogin();
if (!currentUserCan('users.manage')) {
  http_response_code(403);
  die('Accesso negato');
}

/* -------------------------------------------------
   AJAX: crea/aggiorna utente + ruoli multipli
------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
  header('Content-Type: application/json; charset=utf-8');

  try{
    $id       = (int)($_POST['id'] ?? 0);
    $nome     = trim((string)($_POST['nome'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $ruoliSel = $_POST['ruoli'] ?? []; // array di ruolo_id
// -- INIZIO blocco: ruolo unico (admin > user_intermedio > user_base)
if (!is_array($ruoliSel)) $ruoliSel = [];
$ruoliSel = array_map('intval', $ruoliSel);
$ruoliSel = array_values(array_unique($ruoliSel));

if (empty($ruoliSel)) {
  throw new Exception('Seleziona un ruolo.');
}

// Recupero slug dei ruoli selezionati
$in  = implode(',', array_fill(0, count($ruoliSel), '?'));
$ty  = str_repeat('i', count($ruoliSel));
$sql = "SELECT id, slug FROM ruoli WHERE id IN ($in)";
$stmt = $conn->prepare($sql);
$stmt->bind_param($ty, ...$ruoliSel);
$stmt->execute();
$rs = $stmt->get_result();
$byId = [];
while ($r = $rs->fetch_assoc()) $byId[(int)$r['id']] = $r['slug'];

// Priorità: admin > user_intermedio > user_base
$keepSlug = null;
$priority = ['admin','user_intermedio','user_base'];
foreach ($priority as $p) {
  if (in_array($p, $byId, true)) { $keepSlug = $p; break; }
}
if (!$keepSlug) {
  throw new Exception('Ruolo non valido: scegli admin, utente_intermedio o utente_base.');
}

// Conservo SOLO l’ID del ruolo con priorità scelta
$keepId = array_search($keepSlug, $byId, true);
$ruoliSel = [$keepId]; // d’ora in poi è unico
// -- FINE blocco: ruolo unico

    if ($nome === '' || $username === '') throw new Exception('Nome e username sono obbligatori.');
    if ($email === '') throw new Exception('L’email è obbligatoria.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Formato email non valido.');
    if (emailEsiste($conn, $email, $id)) throw new Exception('Email già presente nel sistema.');
    if (!is_array($ruoliSel)) $ruoliSel = [];
    if (empty($ruoliSel)) throw new Exception('Seleziona almeno un ruolo.');

    // Se stai modificando TE STESSO: impedisci di toglierti l’ultimo ruolo con users.manage
    $editingSelf = ($id > 0) && ($id === (int)($_SESSION['utente']['id'] ?? 0));
    if ($editingSelf) {
      // Verifica che nei nuovi ruoli ci sia ancora un ruolo che garantisce users.manage
      // (controllo semplice: cerca tra i nuovi ruoli se almeno uno ha quel permesso)
      $in  = implode(',', array_fill(0, count($ruoliSel), '?'));
      $ty  = str_repeat('i', count($ruoliSel));
      $sql = "SELECT COUNT(*) AS c
              FROM ruolo_permessi rp
              JOIN permessi p ON p.id = rp.permesso_id
              WHERE rp.ruolo_id IN ($in) AND p.slug = 'users.manage'";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($ty, ...array_map('intval', $ruoliSel));
      $stmt->execute();
      $c = ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
      if ($c < 1) throw new Exception('Non puoi rimuovere da te stesso il permesso di gestione utenti.');
    }

    $conn->begin_transaction();

    // upsert utente
    if ($id > 0) {
      if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE utenti SET nome=?, username=?, email=?, password=? WHERE id=?");
        $stmt->bind_param("ssssi", $nome, $username, $email, $hash, $id);
      } else {
        $stmt = $conn->prepare("UPDATE utenti SET nome=?, username=?, email=? WHERE id=?");
        $stmt->bind_param("sssi", $nome, $username, $email, $id);
      }
      if (!$stmt->execute()) throw new Exception('Aggiornamento non riuscito.');
    } else {
      if ($password === '') throw new Exception('La password è obbligatoria per la creazione.');
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO utenti (nome, username, email, password) VALUES (?,?,?,?)");
      $stmt->bind_param("ssss", $nome, $username, $email, $hash);
      if (!$stmt->execute()) throw new Exception('Creazione non riuscita.');
      $id = (int)$conn->insert_id;
    }

    // reset ruoli utente
    $stmt = $conn->prepare("DELETE FROM utente_ruoli WHERE utente_id=?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) throw new Exception('Errore assegnazione ruoli (delete).');

    // inserisci ruoli scelti
    $stmtIns = $conn->prepare("INSERT INTO utente_ruoli (utente_id, ruolo_id) VALUES (?, ?)");
    foreach ($ruoliSel as $rid) {
      $rid = (int)$rid;
      $stmtIns->bind_param("ii", $id, $rid);
if (!$stmtIns->execute()) throw new Exception('Errore assegnazione ruoli (insert).');
    }
// --- dopo aver inserito i ruoli scelti ---

// Permessi granulari selezionati dal form (array di slug)
$permGrant = $_POST['perm_grant'] ?? [];
if (!is_array($permGrant)) $permGrant = [];

// Applica i grant solo se tra i ruoli c’è 'user_intermedio'
// ruolo unico => controllo diretto
$hasIntermedio = false;
$stmt = $conn->prepare("SELECT slug FROM ruoli WHERE id=?");
$stmt->bind_param("i", $ruoliSel[0]);
$stmt->execute();
$hasIntermedio = (($stmt->get_result()->fetch_assoc()['slug'] ?? '') === 'user_intermedio');


// Reset grant diretti dell’utente
if (tableExists($conn,'utente_permessi')) {
  $stmt = $conn->prepare("DELETE FROM utente_permessi WHERE utente_id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();

  if ($hasIntermedio && !empty($permGrant)) {
    // Traduco slugs -> ids
    $in = implode(',', array_fill(0, count($permGrant), '?'));
    $types = str_repeat('s', count($permGrant));
    $sql = "SELECT id, slug FROM permessi WHERE slug IN ($in)";
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$permGrant);
    $st->execute();
    $rs = $st->get_result();
    $map = [];
    while($row = $rs->fetch_assoc()) $map[$row['slug']] = (int)$row['id'];

    $ins = $conn->prepare("INSERT INTO utente_permessi (utente_id, permesso_id) VALUES (?,?)");
    foreach ($permGrant as $slug) {
      if (isset($map[$slug])) {
        $pid = $map[$slug];
        $ins->bind_param("ii", $id, $pid);
        $ins->execute();
      }
    }
  }
}

    // opzionale: aggiorna utenti.ruolo come label legacy
    $ruoloLabel = 'utente';
    $q = $conn->prepare("
      SELECT r.slug FROM ruoli r
      JOIN utente_ruoli ur ON ur.ruolo_id=r.id
      WHERE ur.utente_id=? ORDER BY r.id
    ");
    $q->bind_param("i", $id);
    $q->execute();
    $res = $q->get_result();
    $slugs = [];
    while($row = $res->fetch_assoc()) $slugs[] = $row['slug'];
    if (in_array('admin', $slugs, true)) {
      $ruoloLabel = 'admin';
    }
    $up = $conn->prepare("UPDATE utenti SET ruolo=? WHERE id=?");
    $up->bind_param("si", $ruoloLabel, $id);
    $up->execute();

    $conn->commit();

    // Se ho modificato me stesso, ricarico i permessi in sessione
    if ($editingSelf) unset($_SESSION['__perms']);

    echo json_encode(['success'=>true, 'msg'=> ($id>0 ? 'Utente aggiornato' : 'Utente creato') ]);
    exit;

  } catch (Throwable $e) {
  // rollback a prescindere
  try { $conn->rollback(); } catch (Throwable $_) {}

  http_response_code(400);
  echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
  exit;
}

}

/* -------------------------------------------------
   Elimina utente (+ pivot ruoli se serve)
------------------------------------------------- */
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  if ($id === (int)($_SESSION['utente']['id'] ?? 0)) {
    setFlash('Non puoi eliminare te stesso.');
  } else {
    // protezione: non eliminare un utente admin se è l’unico con users.manage
    $sqlCountAdmins = "
      SELECT COUNT(DISTINCT ur.utente_id) AS c
      FROM utente_ruoli ur
      JOIN ruolo_permessi rp ON rp.ruolo_id = ur.ruolo_id
      JOIN permessi p ON p.id = rp.permesso_id
      WHERE p.slug='users.manage'
    ";
    $cAdmins = 0; $rs = $conn->query($sqlCountAdmins);
    if ($rs) $cAdmins = (int)($rs->fetch_assoc()['c'] ?? 0);

    $sqlUserHas = "
      SELECT COUNT(*) AS c
      FROM utente_ruoli ur
      JOIN ruolo_permessi rp ON rp.ruolo_id = ur.ruolo_id
      JOIN permessi p ON p.id = rp.permesso_id
      WHERE ur.utente_id=? AND p.slug='users.manage'
    ";
    $stmt = $conn->prepare($sqlUserHas);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $uhas = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    if ($uhas > 0 && $cAdmins <= 1) {
      setFlash('Impossibile eliminare: sarebbe assente qualsiasi utente con permesso di gestione.');
      header('Location: /backend/admin/gestione_utenti.php'); exit;
    }

    $conn->begin_transaction();
    try{
      // se non hai ON DELETE CASCADE sulla pivot, pulisci manualmente
      $stmt = $conn->prepare("DELETE FROM utente_ruoli WHERE utente_id=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();

      $stmt = $conn->prepare("DELETE FROM utenti WHERE id=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();

      $conn->commit();
      setFlash('Utente eliminato.');
    }catch(Throwable $e){
      $conn->rollback();
      setFlash('Errore durante l’eliminazione.');
    }
  }
  header('Location: /backend/admin/gestione_utenti.php');
  exit;
}

/* -------------------------------------------------
   Dati pagina
------------------------------------------------- */
$msg = getFlash();

// ruoli per il form
$ruoliDisponibili = [];
$rsR = $conn->query("
  SELECT id, slug, nome
  FROM ruoli
  WHERE slug IN ('admin','user_intermedio','user_base')
  ORDER BY FIELD(slug,'admin','user_intermedio','user_base')
");
if ($rsR) { while($r = $rsR->fetch_assoc()) $ruoliDisponibili[] = $r; }

// utenti + ruoli multipli (slugs)
$utenti = [];
$sqlU = "
  SELECT u.id, u.nome, u.username, u.email, u.ruolo,
         COALESCE(GROUP_CONCAT(r.slug ORDER BY r.id SEPARATOR ','), '') AS ruoli_slugs
  FROM utenti u
  LEFT JOIN utente_ruoli ur ON ur.utente_id = u.id
  LEFT JOIN ruoli r        ON r.id = ur.ruolo_id
  GROUP BY u.id
  ORDER BY u.id DESC
";
$rs = $conn->query($sqlU);
if ($rs) { while($row = $rs->fetch_assoc()) $utenti[] = $row; }

$nome  = e($_SESSION['utente']['nome'] ?? 'Utente');
$ruolo = strtoupper($_SESSION['utente']['ruolo'] ?? 'ADMIN'); // label legacy
// dopo aver creato $utenti, aggiungi i permessi diretti a ciascuno
foreach ($utenti as &$u) {
  $u['perm_grant'] = [];
  if (tableExists($conn,'utente_permessi')) {
    $stmt = $conn->prepare("
      SELECT p.slug
      FROM utente_permessi up
      JOIN permessi p ON p.id = up.permesso_id
      WHERE up.utente_id = ?
    ");
    $stmt->bind_param("i", $u['id']);
    $stmt->execute();
    $rs = $stmt->get_result();
    while($r = $rs->fetch_assoc()) $u['perm_grant'][] = $r['slug'];
  }
}
unset($u);

?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Gestione Utenti</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="../assets/css/style1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .form-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    .form-grid .col-2{ grid-column: span 2; }
    @media (max-width: 820px){ .form-grid{ grid-template-columns: 1fr; } .form-grid .col-2{ grid-column: auto; } }

    .table.compact td, .table.compact th{ padding:10px 8px; font-size:13px }
    .btn{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border:1px solid var(--border);
          border-radius:999px; background:#fff; color:#0f172a; text-decoration:none; cursor:pointer; }
    .btn:hover{ filter:brightness(.98) }
    .btn.danger{ background:#fff5f5; border-color:#fecaca; color:#7f1d1d; }
    .toast{ position:fixed; right:16px; bottom:16px; background:#0f172a; color:#fff; padding:10px 12px; border-radius:10px; box-shadow:0 10px 24px rgba(0,0,0,.18); z-index:1500; display:none; }
    .toast.show{ display:block; }

    input{ border:1px solid var(--border) !important; }
    .input-group{ display:flex; align-items:stretch; width:100%;
      border:1px solid var(--border) !important; border-radius:10px; overflow:hidden; background:#fff; }
    .input-group input{ border:0; flex:1 1 auto; padding:10px 12px; outline:none; font-size:14px; }
    .input-group button{ border:0; background:#f8fafc; padding:0 10px; cursor:pointer; min-width:44px; border-left:1px solid var(--border); }
    .hint{ font-size:12px; color:var(--muted); margin-top:4px; }
    .invalid{ border-color:#dc2626 !important; box-shadow:0 0 0 2px rgba(220,38,38,.08); }

    /* focus */
    #utenteForm :is(input, select, .input-group):focus,
    #utenteForm :is(input, select, .input-group):focus-within{
      box-shadow:0 0 0 3px rgba(37,99,235,.12); border-color:#2563eb !important;
    }
  </style>
</head>
<body>
<main>
  <!-- Topbar -->
  <header class="topbar">
    <div class="user-badge">
      <i class="fas fa-user-shield icon-user" aria-hidden="true"></i>
      <div>
        <div class="muted">Bentornata,</div>
        <div style="font-weight:800; letter-spacing:.2px;"><?= $nome ?></div>
      </div>
      <span class="role"><?= $ruolo ?></span>
    </div>
    <div class="right">
      <?php include __DIR__ . '/../partials/navbar.php'; ?>
      <a class="chip" href="/backend/admin/index_admin.php"><i class="fas fa-arrow-left"></i> Area Admin</a>
      <a class="chip" href="/backend/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <!-- Hero -->
  <section class="panel" style="margin-top:16px;">
    <h2 style="margin:0 0 8px;"><i class="fa-solid fa-users-gear"></i> Gestione Utenti</h2>
    <div class="muted">Crea, modifica e assegna ruoli multipli agli utenti (RBAC).</div>
    <?php if (!empty($msg)): ?>
      <div class="panel s-ok" style="margin-top:10px;"><?= e($msg) ?></div>
    <?php endif; ?>
  </section>

  <!-- Form -->
  <section class="panel" style="margin-top:12px;">
    <h2 style="margin:0 0 8px;"><i class="fa-regular fa-square-plus"></i> Aggiungi o modifica utente</h2>
    <form id="utenteForm" class="form-grid" method="post" action="#" novalidate>
      <input type="hidden" name="ajax" value="1">
      <input type="hidden" name="id" id="id">
      <div>
        <label>Nome</label>
        <input type="text" name="nome" id="nome" required>
      </div>
      <div>
        <label>Username</label>
        <input type="text" name="username" id="username" required>
      </div>
      <div>
        <label>Email</label>
        <div class="input-group">
          <input type="email" name="email" id="email" required placeholder="nome@dominio.it">
          <button type="button" id="copyEmail" aria-label="Copia email">
            <i class="fa-regular fa-copy"></i>
          </button>
        </div>
        <div class="hint">Obbligatoria, univoca.</div>
      </div>
      <div>
        <label>Password <span class="muted">(lascia vuoto per non cambiare)</span></label>
        <div class="input-group" id="pwdGroup">
          <input type="password" name="password" id="password" autocomplete="new-password" placeholder="••••••••">
          <button type="button" id="togglePwd" aria-label="Mostra/Nascondi password"><i class="fa-regular fa-eye"></i></button>
        </div>
        <div class="hint">Minimo 8 caratteri consigliati.</div>
      </div>

      <div class="col-2">
        <label>Ruoli</label>
        <div id="ruoliBox" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
          <?php foreach ($ruoliDisponibili as $r): ?>
            <label class="chip" style="cursor:pointer;">
              <input type="checkbox" name="ruoli[]" value="<?= (int)$r['id'] ?>" data-slug="<?= e($r['slug']) ?>" style="margin-right:6px;">
              <?= e($r['nome']) ?> <span class="muted" style="margin-left:6px;">(<?= e($r['slug']) ?>)</span>
            </label>
          <?php endforeach; ?>
        </div>
<div class="hint">Seleziona un solo ruolo (Admin / Intermedio / Base).</div>
      </div>

      <div class="col-2" id="box-perm-granulari" style="margin-top:6px; display:none;">
  <label>Permessi specifici (solo per Utente intermedio)</label>
  <div style="display:flex; gap:16px; flex-wrap:wrap;">
    <fieldset style="border:1px solid var(--border); padding:8px 10px; border-radius:10px;">
      <legend style="padding:0 6px;">Blog</legend>
      <label class="chip"><input type="checkbox" name="perm_grant[]" value="blog.view"> Vedi</label>
      <label class="chip"><input type="checkbox" name="perm_grant[]" value="blog.edit"> Modifica</label>
    </fieldset>
    <fieldset style="border:1px solid var(--border); padding:8px 10px; border-radius:10px;">
      <legend style="padding:0 6px;">Portfolio</legend>
      <label class="chip"><input type="checkbox" name="perm_grant[]" value="portfolio.view"> Vedi</label>
      <label class="chip"><input type="checkbox" name="perm_grant[]" value="portfolio.edit"> Modifica</label>
    </fieldset>
  </div>
  <div class="hint">Se l’utente non è “intermedio”, queste spunte sono ignorate.</div>
</div>


      <div class="col-2" style="display:flex; gap:8px; justify-content:flex-end;">
        <button type="button" class="btn" id="btnReset"><i class="fa-solid fa-rotate-left"></i> Reset</button>
        <button type="submit" class="btn s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
      </div>
    </form>
  </section>

  <!-- Tabella utenti -->
  <section class="panel" style="margin-top:12px;">
    <h2 style="margin:0 0 8px;"><i class="fa-regular fa-id-card"></i> Utenti registrati</h2>
    <div class="table-responsive">
      <table class="table compact">
        <thead>
          <tr>
            <th style="width:80px;">ID</th>
            <th>Nome</th>
            <th>Username</th>
            <th>Email</th>
            <th>Ruoli</th>
            <th style="width:240px;">Azioni</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($utenti)): foreach ($utenti as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= e($u['nome']) ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['email'] ?? '—') ?></td>
            <td><?= e($u['ruoli_slugs'] ?: ($u['ruolo'] ?? '')) ?></td>
            <td>
              <?php if ((int)$u['id'] !== (int)($_SESSION['utente']['id'] ?? 0)): ?>
                <button class="btn btn-edit"
                        data-user='<?= e(json_encode($u, JSON_UNESCAPED_UNICODE)) ?>'>
                  <i class="fa-regular fa-pen-to-square"></i> Modifica
                </button>
                <a class="btn danger"
                   href="/backend/admin/gestione_utenti.php?delete=<?=
                     (int)$u['id'] ?>"
                   onclick="return confirm('Confermi eliminazione utente?');">
                  <i class="fa-regular fa-trash-can"></i> Elimina
                </a>
              <?php else: ?>
                <span class="muted">Tu</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6" class="muted">Nessun utente.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script>
  const form    = document.getElementById('utenteForm');
  const idEl    = document.getElementById('id');
  const nomeEl  = document.getElementById('nome');
  const usrEl   = document.getElementById('username');
  const emailEl = document.getElementById('email');
  const pwdEl   = document.getElementById('password');
  const ruoliBox= document.getElementById('ruoliBox');
  const toast   = document.getElementById('toast');

  const toggle = document.getElementById('togglePwd');
  toggle.addEventListener('click', ()=>{
    const isPwd = pwdEl.getAttribute('type') === 'password';
    pwdEl.setAttribute('type', isPwd ? 'text' : 'password');
    toggle.innerHTML = isPwd ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
    pwdEl.focus();
  });

  function showToast(msg, ok=true){
    toast.textContent = msg || (ok ? 'Operazione riuscita' : 'Errore');
    toast.style.background = ok ? '#065f46' : '#7f1d1d';
    toast.classList.add('show');
    setTimeout(()=> toast.classList.remove('show'), 2400);
  }

  function validateClient(){
    let ok = true;
    [nomeEl, usrEl, emailEl].forEach(el=> el.classList.remove('invalid'));
    if (!nomeEl.value.trim()){ nomeEl.classList.add('invalid'); ok=false; }
    if (!usrEl.value.trim()){ usrEl.classList.add('invalid'); ok=false; }
    const em = emailEl.value.trim();
    if (!em || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)){ emailEl.classList.add('invalid'); ok=false; }
    const anyRole = !!ruoliBox.querySelector('input[type="checkbox"]:checked');
    if (!anyRole) { showToast('Seleziona almeno un ruolo.', false); ok=false; }
    return ok;
  }

  // Reset
  document.getElementById('btnReset').addEventListener('click', ()=>{
    idEl.value = '';
    nomeEl.value = '';
    usrEl.value  = '';
    emailEl.value= '';
    pwdEl.value  = '';
    ruoliBox.querySelectorAll('input[type="checkbox"]').forEach(ch => ch.checked = false);
    [nomeEl, usrEl, emailEl].forEach(el=> el.classList.remove('invalid'));
    nomeEl.focus();
  });

  // Click su "Modifica"
  document.querySelectorAll('.btn-edit').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      try{
        const u = JSON.parse(btn.getAttribute('data-user'));
        idEl.value    = u.id || '';
        nomeEl.value  = u.nome || '';
        usrEl.value   = u.username || '';
        emailEl.value = u.email || '';
        pwdEl.value   = '';

      const slugs = (u.ruoli_slugs || '').split(',').filter(Boolean);
ruoliBox.querySelectorAll('input[type="checkbox"]').forEach(ch => {
  const slug = ch.getAttribute('data-slug');
  ch.checked = slugs.includes(slug);
});

updatePermBoxVisibility();

// Prefill permessi granulari
document.querySelectorAll('input[name="perm_grant[]"]').forEach(ch => ch.checked = false);
if (Array.isArray(u.perm_grant)) {
  u.perm_grant.forEach(s => {
    const ch = document.querySelector(`input[name="perm_grant[]"][value="${s}"]`);
    if (ch) ch.checked = true;
  });
}


        window.scrollTo({ top: form.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
        nomeEl.focus();
      }catch(_){}
    });
  });

  // Submit AJAX
  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    if (!validateClient()){
      showToast('Controlla i campi evidenziati.', false);
      return;
    }
    const fd = new FormData(form);
    try{
      const res = await fetch(location.href, { method:'POST', body: fd, credentials:'same-origin' });
      const out = await res.json().catch(()=>({success:false, msg:'Risposta non valida'}));
      if(!res.ok || !out.success) throw new Error(out.msg || 'Salvataggio non riuscito');
      showToast(out.msg || 'Salvato', true);
      setTimeout(()=> location.reload(), 700);
    }catch(err){
      console.error(err);
      showToast(err.message || 'Errore', false);
    }
  });

  // Copia email
  const copyBtn = document.getElementById('copyEmail');
  copyBtn.addEventListener('click', ()=>{
    const v = emailEl.value.trim();
    if (!v) { showToast('Nessuna email da copiare', false); return; }
    navigator.clipboard?.writeText(v).then(()=> showToast('Email copiata'))
      .catch(()=> showToast('Impossibile copiare', false));
  });

  // Validazione live email
  emailEl.addEventListener('input', ()=>{
    const em = emailEl.value.trim();
    const ok = !!em && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em);
    emailEl.closest('.input-group').classList.toggle('invalid', !ok);
  });

  const boxPerm = document.getElementById('box-perm-granulari');

function updatePermBoxVisibility(){
  const checks = ruoliBox.querySelectorAll('input[type="checkbox"]');
  let isIntermedio = false;
  checks.forEach(ch => {
    if (ch.checked && ch.getAttribute('data-slug') === 'user_intermedio') isIntermedio = true;
  });
  boxPerm.style.display = isIntermedio ? 'block' : 'none';
}

ruoliBox.querySelectorAll('input[type="checkbox"]').forEach(ch=>{
  ch.addEventListener('change', updatePermBoxVisibility);
});

updatePermBoxVisibility();
// Esclusività dei 3 ruoli (admin / user_intermedio / user_base)
const ROLE_KEYS = ['admin','user_intermedio','user_base'];
ruoliBox.querySelectorAll('input[type="checkbox"]').forEach(ch=>{
  ch.addEventListener('change', (e)=>{
    const slug = ch.getAttribute('data-slug');
    if (ch.checked && ROLE_KEYS.includes(slug)) {
      // deseleziona gli altri 2
      ruoliBox.querySelectorAll('input[type="checkbox"]').forEach(x=>{
        if (x !== ch && ROLE_KEYS.includes(x.getAttribute('data-slug'))) x.checked = false;
      });
      updatePermBoxVisibility();
    }
  });
});

</script>
</body>
</html>