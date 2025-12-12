<?php
if(!isset($_SESSION)) session_start();
require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/funzioni_cliente.php';
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

/* Connessione (se non già fornita dal require) */
$conn = db();
  if ($conn->connect_error) die('Errore connessione: '.$conn->connect_error);

$conn->set_charset('utf8mb4');

/* Sticky values + messaggi */
$sticky = [
  'nome'=>'','referente_1'=>'','referente_2'=>'','telefono'=>'','email'=>'',
  'partita_iva'=>'','codice_univoco'=>'','indirizzo'=>'','cap'=>'','citta'=>''
];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // raccogli input grezzo
  $in = [
    'nome'           => trim($_POST['nome'] ?? ''),
    'referente_1'    => trim($_POST['referente_1'] ?? ''),
    'referente_2'    => trim($_POST['referente_2'] ?? ''),
    'telefono'       => trim($_POST['telefono'] ?? ''),
    'email'          => trim($_POST['email'] ?? ''),
    'partita_iva'    => trim($_POST['partita_iva'] ?? ''),
    'codice_univoco' => trim($_POST['codice_univoco'] ?? ''),
    'indirizzo'      => trim($_POST['indirizzo'] ?? ''),
    'cap'            => trim($_POST['cap'] ?? ''),
    'citta'          => trim($_POST['citta'] ?? ''),
  ];

  // prova inserimento
  [$ok, $newId, $msg] = clienti_inserisci($conn, $in);
  if ($ok) {
    header('Location: index_clienti.php?msg=ok');
    exit;
  }
  // errore -> ripresenta form con sticky values
  $err = $msg;
  $sticky = array_merge($sticky, $in);
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Nuovo Cliente</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
</head>
<body class="clients-new">

<main>
  <!-- Topbar coerente -->
  <header class="topbar" style="margin-bottom:12px;">
    <div class="user-badge">
      <i class="fas fa-user-plus icon-user"></i>
      <div>
        <div class="muted">Clienti</div>
        <div style="font-weight:800; letter-spacing:.2px;">Nuovo Cliente</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>

    <div class="right">
      <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <a class="chip" href="index_clienti.php"><i class="fa-solid fa-users"></i> Elenco Clienti</a>
         <?php include '../partials/navbar.php' ?>
    </div>
  </header>

  <?php if (!empty($err)): ?>
    <section class="panel alert error"><?= e($err) ?></section>
  <?php endif; ?>

  <section class="panel">
    <form method="POST" class="fnew" id="formNuovoCliente" autocomplete="off" novalidate>
      <!-- Colonna sinistra -->
      <div class="stack">
        <label for="nome">Nome / Ragione Sociale</label>
        <input type="text" name="nome" id="nome" required value="<?= e($sticky['nome']) ?>">

        <div style="display:grid; gap:10px; grid-template-columns: 1fr 1fr;">
          <div>
            <label for="referente_1">Referente 1</label>
            <input type="text" name="referente_1" id="referente_1" value="<?= e($sticky['referente_1']) ?>">
          </div>
          <div>
            <label for="referente_2">Referente 2</label>
            <input type="text" name="referente_2" id="referente_2" value="<?= e($sticky['referente_2']) ?>">
          </div>
        </div>

        <div style="display:grid; gap:10px; grid-template-columns: 1fr 1fr;">
          <div>
            <label for="telefono">Telefono</label>
            <input type="text" name="telefono" id="telefono" value="<?= e($sticky['telefono']) ?>">
          </div>
          <div>
            <label for="email">Email</label>
            <input type="email" name="email" id="email" value="<?= e($sticky['email']) ?>">
          </div>
        </div>

        <div style="display:grid; gap:10px; grid-template-columns: 1fr 1fr;">
          <div>
            <label for="partita_iva">Partita IVA</label>
            <input type="text" name="partita_iva" id="partita_iva" value="<?= e($sticky['partita_iva']) ?>">
          </div>
          <div>
            <label for="codice_univoco">Codice Univoco / PEC</label>
            <input type="text" name="codice_univoco" id="codice_univoco" value="<?= e($sticky['codice_univoco']) ?>">
          </div>
        </div>
      </div>

      <!-- Colonna destra -->
      <div class="stack">
        <label for="indirizzo">Indirizzo</label>
        <input type="text" name="indirizzo" id="indirizzo" value="<?= e($sticky['indirizzo']) ?>">

        <div style="display:grid; gap:10px; grid-template-columns: 120px 1fr;">
          <div>
            <label for="cap">CAP</label>
            <input type="text" name="cap" id="cap" value="<?= e($sticky['cap']) ?>">
          </div>
          <div>
            <label for="citta">Città</label>
            <input type="text" name="citta" id="citta" value="<?= e($sticky['citta']) ?>">
          </div>
        </div>

        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Salva Cliente</button>
      </div>

      <div class="col-span-2"></div>
    </form>
  </section>
</main>

<!-- JS comune -->
<script src="../assets/javascript/main.js"></script>
<script src="../assets/javascript/clienti.js"></script>
</body>
     <?php include '../partials/footer.php' ?>

</html>
