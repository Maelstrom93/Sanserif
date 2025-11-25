<?php
session_start();
require_once '../assets/funzioni/funzioni.php';           // contiene requireLogin(), formattaData(), ecc.
require_once __DIR__ . '/../assets/funzioni/authz.php';
require_once __DIR__ . '/../assets/funzioni/db/db.php';

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

require_once '../assets/funzioni/funzioni_preventivo.php';

/* Eliminazione (con transazione) */
$msg = '';
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  if (deletePreventivo($conn, $id)) {
    $msg = "Preventivo #$id eliminato correttamente.";
  } else {
    $msg = "Errore durante l'eliminazione del preventivo #$id.";
  }
}

/* Query elenco */
$res = getPreventivi($conn);
if ($res === false) die("Errore nella query SQL: " . $conn->error);
$tot = countResult($res);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Elenco Preventivi</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
</head>
<body>

<main>
  <!-- Topbar -->
  <header class="topbar" style="margin-bottom:12px;">
    <div class="user-badge">
      <i class="fas fa-file-invoice-dollar icon-user"></i>
      <div>
        <div class="muted">Preventivi</div>
        <div style="font-weight:800; letter-spacing:.2px;">Elenco</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>

    <div class="right">
      <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <a class="chip s-ok" href="nuovo_preventivo.php"><i class="fas fa-plus"></i> Nuovo Preventivo</a>
          <?php include '../partials/navbar.php' ?>
    </div>
  </header>

  <?php if (!empty($msg)): ?>
    <section class="panel s-ok" style="margin-bottom:12px;">✅ <?= e($msg) ?></section>
  <?php endif; ?>

  <section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <h2 style="margin:0; color:#004c60;"><i class="fa-solid fa-list"></i> Elenco Preventivi</h2>
      <div class="muted hide-sm"><?= e($tot) ?> risultati</div>
    </div>

    <div class="table-responsive" style="margin-top:12px;">
      <table class="table">
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th style="width:140px;">Data</th>
            <th>Cliente</th>
            <th style="width:160px;">Totale</th>
            <th style="width:380px;">Azioni</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($tot > 0): ?>
          <?php while ($p = $res->fetch_assoc()):
            $cliente = $p['cliente_nome'] ?: $p['cliente_nome_custom'] ?: 'N/D';
            $dataIt  = formatDataIt($p['data']);
            $totIt   = formatEuro($p['totale']);
          ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td><?= e($dataIt) ?></td>
              <td><?= e($cliente) ?></td>
              <td class="tot-cell"><?= e($totIt) ?></td>
              <td>
                <div class="actions-wrap">
                  <a class="chip" href="genera_preventivo.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf"></i> PDF
                  </a>
                  <button class="chip open-view-preventivo" type="button" data-id="<?= (int)$p['id'] ?>">
                    <i class="fa-regular fa-eye"></i> Dettagli
                  </button>
                  <button class="chip open-edit-preventivo" type="button" data-id="<?= (int)$p['id'] ?>">
                    <i class="fas fa-pen"></i> Modifica
                  </button>
                  <button class="chip open-duplica-preventivo" type="button" data-id="<?= (int)$p['id'] ?>">
                    <i class="fas fa-clone"></i> Duplica
                  </button>
                  <a class="chip danger" href="?delete=<?= (int)$p['id'] ?>" data-delete-id="<?= (int)$p['id'] ?>">
                    <i class="fas fa-trash"></i> Elimina
                  </a>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" class="muted">Nessun preventivo presente.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<!-- ===== MODALE VIEW PREVENTIVO ===== -->
<div id="modale-view-preventivo" class="modal pv-modal" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalViewPreventivoTitolo">
    <div class="m-header">
      <h2 id="modalViewPreventivoTitolo" class="m-title">
        <i class="fa-regular fa-eye"></i> Dettaglio Preventivo
      </h2>
      <div style="display:flex; gap:8px;">
        <a id="pv-pdf-link" class="chip" target="_blank"><i class="fa-regular fa-file-pdf"></i> Apri PDF</a>
        <button type="button" class="chip" data-view-close><i class="fa-solid fa-xmark"></i> Chiudi</button>
      </div>
    </div>
    <div class="m-body">
      <div id="pv-dettaglio" class="mini-card"></div>

      <div style="margin:10px 0 4px; font-weight:800; color:#004c60;">Voci</div>
      <div class="table-responsive">
        <table class="table-mini" id="pv-righe">
          <thead>
            <tr>
              <th>Descrizione</th>
              <th style="width:120px;">Q.tà</th>
              <th style="width:160px;">Prezzo</th>
              <th style="width:160px;">Totale</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div id="pv-totali" class="muted-sm" style="text-align:right; margin-top:8px;"></div>
    </div>
  </div>
</div>

<!-- ===== MODALE MODIFICA PREVENTIVO ===== -->
<div id="modale-edit-preventivo" class="modal pv-modal" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalEditPreventivoTitolo">
    <div class="m-header">
      <h2 id="modalEditPreventivoTitolo" class="m-title">
        <i class="fa-regular fa-pen-to-square"></i> Modifica Preventivo
      </h2>
      <button type="button" class="chip" data-modal-close><i class="fa-solid fa-xmark"></i> Chiudi</button>
    </div>
    <div class="m-body">
      <form id="formModificaPreventivo" action="../api/modifica_preventivo.php" method="post" autocomplete="off" novalidate>
        <input type="hidden" name="id" id="edit-id">

        <div class="grid2">
          <div>
            <label>Data Preventivo</label>
            <input type="date" name="data" id="edit-data">
          </div>
          <div>
            <label>Valido fino</label>
            <input type="date" name="valido_fino" id="edit-valido-fino">
          </div>
        </div>

        <label>Metodo di pagamento</label>
        <select name="pagamento" id="edit-pagamento">
          <option value="Bonifico Bancario">Bonifico Bancario</option>
          <option value="Carta di Credito">Carta di Credito</option>
          <option value="PayPal">PayPal</option>
          <option value="Altro">Altro</option>
        </select>

        <div style="margin-top:10px; font-weight:800; color:#004c60;">Voci</div>
        <div class="table-responsive" style="margin-top:6px;">
          <table class="quote" id="edit-voci-preventivo">
            <thead>
              <tr>
                <th>Descrizione</th>
                <th style="width:130px;">Quantità</th>
                <th style="width:160px;">Prezzo (€)</th>
                <th style="width:80px;">Rimuovi</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <button type="button" class="chip" id="btn-add-riga"><i class="fa-solid fa-plus"></i> Aggiungi Voce</button>

        <div class="grid2" style="margin-top:10px;">
          <div>
            <label>IVA (%)</label>
            <input type="number" name="iva" id="edit-iva" step="0.01" min="0" max="100">
          </div>
          <div>
            <label>Sconto (%)</label>
            <input type="number" name="sconto" id="edit-sconto" step="0.01" min="0" max="100">
          </div>
        </div>

        <label>Note</label>
        <textarea name="note" id="edit-note" rows="4"></textarea>

        <div class="form-actions-sticky">
          <button type="button" class="chip" data-modal-close>Annulla</button>
          <button type="submit" class="chip s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/javascript/preventivo.js"></script>
<script src="../assets/javascript/main.js"></script>
</body>
     <?php include '../partials/footer.php' ?>

</html>
