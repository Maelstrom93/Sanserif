<?php
require_once __DIR__ . '/_auth_guard.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
require_once __DIR__ . '/../assets/funzioni/db/contact_requests.php';
require_once __DIR__ . '/../assets/funzioni/funzioni_richieste.php';
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
// =====================================================================

$id  = (int)($_GET['id'] ?? 0);
$rec = $id ? cr_get($conn, $id) : null;
if (!$rec) { http_response_code(404); echo "Richiesta non trovata"; exit; }

$userId = isset($_SESSION['utente']['id']) ? (int)$_SESSION['utente']['id'] : null;
trackRequestView($conn, $id, $userId);
markRequestInReviewIfNew($conn, $id);

$csrf = email_csrf_token();

// utenti assegnabili (stessa logica della index)
$assignableUsers = richieste_load_assignable_users($conn);

// helper escape locale (se non già definito)
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Richiesta #<?= (int)$rec['id'] ?></title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
</head>
<body>

<main>
  <header class="topbar" style="margin-bottom:12px;">
    <div class="user-badge">
      <i class="fas fa-envelope-open" style="font-size:26px;color:#0ea5e9;"></i>
      <div>
        <div class="muted">Dettaglio</div>
        <div style="font-weight:800; letter-spacing:.2px;">Richiesta #<?= (int)$rec['id'] ?></div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>
    <div class="right">
      <a class="chip" href="/backend/email/index.php"><i class="fas fa-arrow-left"></i> Lista</a>
        <?php include '../partials/navbar.php' ?>
    </div>
  </header>

  <!-- DATI PRINCIPALI -->
  <section class="panel compact" style="margin-bottom:12px;">
    <div class="two-col">
      <div>
        <div class="muted">Tipo</div>
        <div><b><?= ($rec['tipo']==='privati' ? 'Privati' : 'Aziende') ?></b></div>
      </div>
      <div>
        <div class="muted">Email</div>
        <div><?= e((string)$rec['email']) ?></div>
      </div>
    </div>

    <?php if (($rec['tipo'] ?? '') === 'privati'): ?>
      <div class="two-col" style="margin-top:10px;">
        <div><div class="muted">Nome</div><div><?= e((string)($rec['nome'] ?? '')) ?></div></div>
        <div><div class="muted">Cognome</div><div><?= e((string)($rec['cognome'] ?? '')) ?></div></div>
      </div>
    <?php else: ?>
      <div class="two-col" style="margin-top:10px;">
        <div><div class="muted">Rag. Sociale</div><div><?= e((string)($rec['rgs'] ?? '')) ?></div></div>
        <div><div class="muted">Settore</div><div><?= e((string)($rec['settore'] ?? '')) ?></div></div>
      </div>
    <?php endif; ?>

    <div style="margin-top:12px;">
      <div class="muted">Messaggio</div>
      <div class="panel" style="white-space:pre-wrap;border-radius:10px;padding:10px;background:#fff;">
        <?= e((string)($rec['msg'] ?? '')) ?>
      </div>
    </div>

    <div class="muted" style="margin-top:10px;">
      Inviata: <?= e((string)$rec['created_at']) ?>
      <?php if (!empty($rec['updated_at'])): ?> — Ultima modifica: <?= e((string)$rec['updated_at']) ?><?php endif; ?><br>
      Stato invio mail: <b><?= e((string)($rec['mail_status'] ?? '')) ?></b>
      <?php if (!empty($rec['mail_error'])): ?> — errore: <?= e((string)$rec['mail_error']) ?><?php endif; ?>
    </div>
  </section>

  <!-- FORM DI AGGIORNAMENTO -->
  <section class="panel compact">
    <form method="post" action="/backend/email/update.php" id="detailForm">
      <input type="hidden" name="id" value="<?= (int)$rec['id'] ?>">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

      <div class="two-col">
        <label>
          <div class="muted">Stato</div>
          <select name="status" id="statusSel" required class="chip" style="border-radius:10px;">
            <?php foreach (['new','in_review','replied','closed'] as $s): ?>
              <option value="<?= $s ?>" <?= (($rec['status'] ?? '')===$s ? 'selected' : '') ?>>
                <?= e(richieste_it_status($s)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          <div class="muted">Assegnata a</div>
          <select name="assigned_to" class="chip" style="border-radius:10px;">
            <option value="">— Nessuno —</option>
            <?php foreach ($assignableUsers as $u): ?>
              <option value="<?= e($u) ?>" <?= (($rec['assigned_to'] ?? '')===$u ? 'selected' : '') ?>><?= e($u) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <?php $isClosed = (($rec['status'] ?? '') === 'closed'); ?>
      <label class="full" id="closureWrap" style="display:<?= $isClosed ? 'block' : 'none' ?>; margin-top:10px;">
        <div class="muted">Motivo chiusura <span style="color:#b91c1c">*</span></div>
        <textarea name="closure_reason" id="closureReason" rows="4" class="panel" style="width:100%;border-radius:10px;" <?= $isClosed ? 'required' : '' ?>><?= e((string)($rec['closure_reason'] ?? '')) ?></textarea>
        <div class="hint">Obbligatorio se lo stato è “Chiusa”.</div>
      </label>

      <label class="full" style="display:block; margin-top:10px;">
        <div class="muted">Note interne</div>
        <textarea name="internal_note" rows="5" class="panel" style="width:100%;border-radius:10px;"><?= e((string)($rec['internal_note'] ?? '')) ?></textarea>
      </label>

      <div class="actions" style="justify-content:flex-end; gap:8px; margin-top:10px;">
        <a class="chip" href="/backend/email/index.php"><i class="fa-regular fa-circle-left"></i> Indietro</a>
        <button class="chip s-ok" type="submit"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
      </div>
    </form>
  </section>
</main>

<!-- JS condiviso (gestisce anche il form di dettaglio) -->
<script src="../assets/javascript/richieste.js"></script>
<script src="../assets/javascript/main.js"></script>
</body>
  <?php include '../partials/footer.php' ?>
</html>
