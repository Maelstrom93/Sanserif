<?php
require_once __DIR__ . '/_auth_guard.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/db/contact_requests.php';
require_once __DIR__ . '/../assets/funzioni/funzioni_richieste.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
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

$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage= 20;

// utenti assegnabili
$assignableUsers = richieste_load_assignable_users($conn);

// filtri da GET
$built       = richieste_build_filters($_GET);
$filtersDb   = $built['filtersDb'];
$assignedTo  = $built['assignedTo'];
$hasMulti    = $built['hasMultiStatus'];
$statuses    = $built['statuses'];

// elenco richieste (con eventuale filtro in memoria)
$list   = richieste_list($conn, $filtersDb, $assignedTo, $statuses, $page, $perPage);
$total  = (int)$list['total'];
$pages  = (int)$list['pages'];
$items  = $list['items'] ?? [];

// comodi per UI
$q           = trim((string)($_GET['q'] ?? ''));
$statusParam = trim((string)($_GET['status'] ?? ''));
$me          = trim((string)($_SESSION['utente']['nome'] ?? ''));
$csrf        = email_csrf_token();

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
  <title>Richieste ricevute</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
</head>
<body
  data-csrf="<?= e($csrf) ?>"
  data-assignable-users='<?= e(json_encode($assignableUsers, JSON_UNESCAPED_UNICODE)) ?>'>

<main>
  <header class="topbar" style="margin-bottom:12px;">
    <div class="user-badge">
      <i class="fas fa-envelope" style="font-size:26px;color:#0ea5e9;"></i>
      <div>
        <div class="muted">Gestione</div>
        <div style="font-weight:800; letter-spacing:.2px;">Richieste</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>

    <div class="right">
      <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <form class="search" method="get">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Cerca (email, nome, azienda, testo…)" value="<?= e($q) ?>" aria-label="Cerca">
        <button class="chip" type="submit">Filtra</button>
      </form>
      <div class="muted hide-sm"><i class="fas fa-database"></i> <?= number_format($total,0,',','.') ?> risultati</div>
           <?php include '../partials/navbar.php' ?>
    </div>
  </header>

  <section class="panel" style="margin-bottom:12px;">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
      <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <strong class="muted"><i class="fa-solid fa-filter"></i> Filtri rapidi</strong>
        <a class="chip" href="?<?= richieste_qs(['status'=>'','assigned_to'=>'']) ?>"><i class="fa-solid fa-asterisk"></i> Tutte</a>
        <a class="chip" href="?<?= richieste_qs(['status'=>'new,in_review','assigned_to'=>'']) ?>"><i class="fa-solid fa-reply"></i> Da rispondere</a>
        <?php if ($me !== ''): ?>
          <a class="chip" href="?<?= richieste_qs(['assigned_to'=>$me]) ?>"><i class="fa-solid fa-user-check"></i> Assegnate a me</a>
        <?php endif; ?>
        <a class="chip" href="?<?= richieste_qs(['status'=>'closed','assigned_to'=>'']) ?>"><i class="fa-solid fa-lock"></i> Chiuse</a>
      </div>

      <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="q" value="<?= e($q) ?>">
        <select class="chip" name="assigned_to" style="border-radius:999px;">
          <option value="">— Assegnata a —</option>
          <?php foreach ($assignableUsers as $u): ?>
            <option value="<?= e($u) ?>" <?= $assignedTo===$u ? 'selected' : '' ?>><?= e($u) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="chip" name="status" style="border-radius:999px;">
          <option value="">Tutte</option>
          <?php foreach (['new','in_review','replied','closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusParam===$s ? 'selected' : '' ?>><?= e(richieste_it_status($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="chip" type="submit"><i class="fa-solid fa-sliders"></i> Applica</button>
      </form>
    </div>

    <?php if ($q!=='' || $statusParam!=='' || $assignedTo!==''): ?>
      <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
        <?php if ($q!==''):          ?><span class="chip"><i class="fa-solid fa-magnifying-glass"></i> q: “<?= e($q) ?>”</span><?php endif; ?>
        <?php if ($statusParam!==''): ?><span class="chip"><i class="fa-solid fa-tag"></i> status: <?= e($statusParam) ?></span><?php endif; ?>
        <?php if ($assignedTo!==''):  ?><span class="chip"><i class="fa-solid fa-user"></i> assigned_to: <?= e($assignedTo) ?></span><?php endif; ?>
        <a class="chip" href="?"><i class="fa-solid fa-xmark"></i> Pulisci</a>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px;">
      <h2 style="margin:0;"><i class="fa-solid fa-inbox"></i> Richieste</h2>
      <div class="muted">Pagina <?= (int)$page ?> di <?= (int)$pages ?></div>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Da</th>
            <th>Tipo</th>
            <th>Estratto</th>
            <th>Status</th>
            <th style="text-align:center;">Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $r): ?>
            <?php
              // "Da" (who)
              $who = (string)($r['email'] ?? '');
              if (($r['tipo'] ?? '') === 'privati') {
                $full = trim((string)($r['nome'] ?? '').' '.(string)($r['cognome'] ?? ''));
                $who  = $full ? ($full.' <'.$r['email'].'>') : (string)$r['email'];
              } else {
                $rg   = trim((string)($r['rgs'] ?? ''));
                $who  = $rg ? ($rg.' <'.$r['email'].'>') : (string)$r['email'];
              }
              $snippet = mb_strimwidth(trim((string)($r['msg'] ?? '')), 0, 90, '…', 'UTF-8');
              $chipCls = richieste_status_chip_class((string)($r['status'] ?? ''));

              // payload per modale
              $payload = [
                'id'            => (int)($r['id'] ?? 0),
                'tipo'          => (string)($r['tipo'] ?? ''),
                'email'         => (string)($r['email'] ?? ''),
                'nome'          => (string)($r['nome'] ?? ''),
                'cognome'       => (string)($r['cognome'] ?? ''),
                'rgs'           => (string)($r['rgs'] ?? ''),
                'settore'       => (string)($r['settore'] ?? ''),
                'msg'           => (string)($r['msg'] ?? ''),
                'status'        => (string)($r['status'] ?? ''),
                'assigned_to'   => (string)($r['assigned_to'] ?? ''),
                'internal_note' => (string)($r['internal_note'] ?? ''),
                'closure_reason'=> (string)($r['closure_reason'] ?? ''),
                'created_at'    => (string)($r['created_at'] ?? ''),
                'updated_at'    => (string)($r['updated_at'] ?? ''),
                'mail_status'   => (string)($r['mail_status'] ?? ''),
                'mail_error'    => (string)($r['mail_error'] ?? ''),
              ];
            ?>
            <tr>
              <td><?= e((string)($r['created_at'] ?? '')) ?></td>
              <td>
                <div style="font-weight:600;"><?= e($who) ?></div>
                <?php if (!empty($r['assigned_to'])): ?>
                  <div class="muted"><i class="fa-regular fa-user"></i> <?= e((string)$r['assigned_to']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= (($r['tipo'] ?? '') === 'privati' ? 'Privati' : 'Aziende') ?></td>
              <td><?= e($snippet) ?></td>
              <td><span class="chip <?= e($chipCls) ?>"><?= e(richieste_it_status((string)($r['status'] ?? ''))) ?></span></td>
              <td style="text-align:center;">
                <a class="chip open-modal"
                   href="#"
                   data-item='<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>'>
                  <i class="fa-solid fa-up-right-from-square"></i> Apri
                </a>
                <a class="chip" href="/backend/email/view.php?id=<?= (int)($r['id'] ?? 0) ?>" style="margin-left:6px;">
                  <i class="fa-regular fa-file-lines"></i> Pagina
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <tr><td colspan="6" class="muted">Nessun risultato.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:center; margin-top:12px;">
        <?php for($p=1; $p<=$pages; $p++): ?>
          <a class="chip<?= ($p===$page ? ' s-ok' : '') ?>" href="?<?= richieste_qs(['page'=>$p]) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<!-- MODALE -->
<div class="modal" id="modal" aria-hidden="true">
  <div class="panel" role="dialog" aria-modal="true" aria-labelledby="mtitle">
    <header>
      <h2 id="mtitle" style="margin:0;font-size:18px"><i class="fa-regular fa-envelope-open"></i> Dettagli richiesta</h2>
      <button type="button" class="chip" data-modal-close><i class="fa-solid fa-xmark"></i> Chiudi</button>
    </header>
    <div class="content" id="modalBody"></div>
  </div>
</div>
<!-- JS: niente inline, tutto nel file dedicato -->
<script src="../assets/javascript/richieste.js"></script>
<script src="../assets/javascript/main.js"></script>
</body>
     <?php include '../partials/footer.php' ?>

</html>
