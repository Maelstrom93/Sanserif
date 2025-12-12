<?php
session_start();
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

// dati clienti
$tutti      = clienti_tutti($conn);
$conteggio  = count($tutti);

// helper locale per escape (se non ereditato altrove)
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Gestione Clienti</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
</head>

<body>
<div id="preview" class="preview-modal"></div>

<main>
  <!-- Topbar -->
  <header class="topbar" style="margin-bottom:12px;">
    <div class="user-badge">
      <i class="fas fa-address-book icon-user"></i>
      <div>
        <div class="muted">Gestione</div>
        <div style="font-weight:800; letter-spacing:.2px;">Clienti</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>

    <div class="right">
      <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <a href="nuovo_cliente.php" class="chip s-ok" style="text-decoration:none"><i class="fas fa-plus"></i> Nuovo Cliente</a>

     <?php include '../partials/navbar.php' ?>
    </div>
  </header>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminato'): ?>
    <section class="panel s-ok" style="margin-bottom:12px;">✅ Cliente eliminato con successo.</section>
  <?php endif; ?>

  <!-- Elenco -->
  <section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <h2 style="margin:0; color:#004c60;"><i class="fa-solid fa-users"></i> Lista Clienti</h2>
      <div class="muted hide-sm"><?= e($conteggio) ?> risultati</div>
    </div>

    <div class="clients-row" id="clientsList" style="margin-top:12px;">
      <?php if (!empty($tutti)): ?>
        <?php foreach ($tutti as $c): ?>
          <?php
            $id    = (int)$c['id'];
            $nome  = (string)$c['nome'];
            $ref1  = (string)$c['referente_1'];
            $ref2  = (string)$c['referente_2'];
            $tel   = (string)$c['telefono'];
            $mail  = (string)$c['email'];
            $piva  = (string)$c['partita_iva'];
            $cu    = (string)$c['codice_univoco'];
            $addr  = (string)$c['indirizzo'];
            $cap   = (string)$c['cap'];
            $citta = (string)$c['citta'];
          ?>
          <article class="client-card" data-id="<?= $id ?>">
            <h3 class="client-title"><i class="fa-regular fa-building"></i> <?= e($nome) ?></h3>

            <div class="client-meta">
              <div class="client-row"><i class="fa-solid fa-user-tie"></i>
                <div><b>Referenti:</b> <?= e($ref1) ?><?= $ref2 ? ' &middot; '.e($ref2) : '' ?></div>
              </div>
              <div class="client-row"><i class="fa-solid fa-phone"></i>
                <div><b>Contatti:</b>
                  <?= e($tel) ?>
                  <?php if ($mail): ?>
                    &middot; <a href="mailto:<?= e($mail) ?>" style="color:#0ea5e9;text-decoration:none"><?= e($mail) ?></a>
                  <?php endif; ?>
                </div>
              </div>
              <div class="client-row"><i class="fa-solid fa-file-invoice"></i>
                <div><b>Dati societari:</b> <?= e($piva) ?><?= $cu ? ' &middot; '.e($cu) : '' ?></div>
              </div>
              <div class="client-row"><i class="fa-solid fa-location-dot"></i>
                <div><b>Indirizzo:</b> <?= e($addr) ?><?= ($cap||$citta) ? ', '.e($cap).' '.e($citta) : '' ?></div>
              </div>
            </div>

            <div class="client-actions">
              <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-pen-to-square"></i> Modifica</a>

              <form method="POST" action="../elimina.php"
                    onsubmit="return confirm('Eliminare questo Cliente?')"
                    style="display:inline;">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="tipo" value="cliente">
                <button type="submit" class="chip" style="background:#fff5f5;border-color:#fecaca;color:#7f1d1d;">
                  <i class="fas fa-trash-alt"></i> Elimina
                </button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="muted">Nessun cliente presente.</div>
      <?php endif; ?>
    </div>
  </section>
      </main>

<!-- ===== MODALE MODIFICA CLIENTE ===== -->
<div id="modale-cliente" class="modal" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalClienteTitolo">
    <div class="m-header">
      <h2 id="modalClienteTitolo" class="m-title">
        <i class="fa-regular fa-pen-to-square"></i> Modifica cliente
      </h2>
      <button type="button" class="chip" data-modal-close>
        <i class="fa-solid fa-xmark"></i> Chiudi
      </button>
    </div>

    <div class="m-body">
      <form id="formModificaCliente">
        <input type="hidden" name="tipo" value="cliente">
        <input type="hidden" name="id">

        <label>Nome / Ragione sociale</label>
        <input type="text" name="nome" required>

        <div style="display:grid; gap:10px; grid-template-columns: repeat(2,minmax(0,1fr));">
          <div>
            <label>Referente 1</label>
            <input type="text" name="referente_1">
          </div>
          <div>
            <label>Referente 2</label>
            <input type="text" name="referente_2">
          </div>
        </div>

        <div style="display:grid; gap:10px; grid-template-columns: repeat(2,minmax(0,1fr));">
          <div>
            <label>Telefono</label>
            <input type="text" name="telefono">
          </div>
          <div>
            <label>Email</label>
            <input type="email" name="email">
          </div>
        </div>

        <div style="display:grid; gap:10px; grid-template-columns: repeat(2,minmax(0,1fr));">
          <div>
            <label>Partita IVA</label>
            <input type="text" name="partita_iva">
          </div>
          <div>
            <label>Codice Univoco / PEC</label>
            <input type="text" name="codice_univoco">
          </div>
        </div>

        <label>Indirizzo</label>
        <input type="text" name="indirizzo">

        <div style="display:grid; gap:10px; grid-template-columns: 120px 1fr;">
          <div>
            <label>CAP</label>
            <input type="text" name="cap">
          </div>
          <div>
            <label>Città</label>
            <input type="text" name="citta">
          </div>
        </div>

        <div class="actions">
          <button type="button" class="chip" data-modal-close>Annulla</button>
          <button type="submit" class="chip s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva modifiche</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- JS -->
<script src="../assets/javascript/main.js"></script>
<script src="../assets/javascript/clienti.js"></script>
</body>
<?php include '../partials/footer.php' ?>
</html>
