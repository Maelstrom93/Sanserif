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


$clienti = [];
$res = $conn->query("SELECT id, nome FROM clienti ORDER BY nome ASC");
while ($row = $res->fetch_assoc()) $clienti[] = $row;

$oggi = date('Y-m-d');
$validita_default = date('Y-m-d', strtotime('+30 days'));

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Nuovo Preventivo</title>
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
        <div style="font-weight:800; letter-spacing:.2px;">Nuovo Preventivo</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>
    <div class="right">
      <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <a class="chip" href="index_preventivi.php"><i class="fa-solid fa-list"></i> Elenco</a>
          <?php include '../partials/navbar.php' ?>
    </div>
  </header>

  <!-- Form preventivo -->
  <section class="panel">
    <h2 style="margin:0 0 10px; color:#004c60;"><i class="fa-regular fa-clipboard"></i> Crea un nuovo preventivo</h2>

    <form method="POST" action="genera_preventivo.php" target="_blank" autocomplete="off" novalidate>
      <!-- Cliente -->
      <label>Seleziona Cliente dal Database</label>
      <select name="cliente_id" id="cliente_id">
        <option value="">— Seleziona cliente —</option>
        <?php foreach ($clienti as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['nome']) ?></option>
        <?php endforeach; ?>
      </select>

      <div id="referente-temp-wrapper" style="display:none;">
        <label for="referente_custom">Referente per questo preventivo</label>
        <input type="text" name="referente_custom" id="referente_custom" placeholder="Es. Mario Bianchi">
      </div>

      <button type="button" id="toggle-nuovo-cliente-btn" class="chip" onclick="toggleNuovoCliente()">
        <i class="fa-solid fa-user-plus"></i> Inserisci nuovo cliente
      </button>

      <div id="nuovo-cliente">
        <h3 style="margin:0 0 8px;"><i class="fa-regular fa-building"></i> Dati Nuovo Cliente</h3>
        <div class="form-grid">
          <div>
            <label>Ragione sociale / Nome completo</label>
            <input type="text" name="cliente_alt_nome" placeholder="Es. ACME S.p.A. / Mario Rossi">
          </div>
          <div>
            <label>Referente 1</label>
            <input type="text" name="cliente_alt_referente1" placeholder="Es. Mario Rossi">
          </div>
          <div>
            <label>Referente 2 (facoltativo)</label>
            <input type="text" name="cliente_alt_referente2" placeholder="Es. Laura Bianchi">
          </div>
          <div>
            <label>Telefono</label>
            <input type="text" name="cliente_alt_telefono" placeholder="Es. 0123 456789">
          </div>
          <div>
            <label>Email</label>
            <input type="text" name="cliente_alt_email" placeholder="esempio@mail.com">
          </div>
          <div>
            <label>Partita IVA</label>
            <input type="text" name="cliente_alt_partita_iva" placeholder="Es. IT12345678901">
          </div>
          <div>
            <label>Codice Univoco / PEC</label>
            <input type="text" name="cliente_alt_codice_univoco" placeholder="PEC o codice destinatario">
          </div>
          <div>
            <label>Indirizzo completo</label>
            <input type="text" name="cliente_alt_indirizzo" placeholder="Via/Piazza, civico, città">
          </div>
          <div>
            <label>CAP</label>
            <input type="text" name="cliente_alt_cap" placeholder="Es. 10100">
          </div>
          <div>
            <label>Città</label>
            <input type="text" name="cliente_alt_citta" placeholder="Es. Torino">
          </div>
        </div>
      </div>

      <!-- Meta preventivo -->
      <div class="form-grid">
        <div>
          <label>Data Preventivo</label>
          <input type="date" name="data" value="<?= e($oggi) ?>" required>
        </div>
        <div>
          <label>Valido fino al</label>
          <input type="date" name="valido_fino" value="<?= e($validita_default) ?>" required>
        </div>
      </div>

      <label>Metodo di pagamento</label>
      <select name="pagamento">
        <option value="Bonifico Bancario">Bonifico Bancario</option>
        <option value="Carta di Credito">Carta di Credito</option>
        <option value="PayPal">PayPal</option>
        <option value="Altro">Altro</option>
      </select>

      <!-- Voci preventivo -->
      <div class="table-responsive" style="margin-top:10px;">
        <table class="quote" id="voci-preventivo">
          <thead>
            <tr>
              <th style="width:55%;">Descrizione</th>
              <th style="width:15%;">Quantità</th>
              <th style="width:20%;">Prezzo unitario (€)</th>
              <th style="width:10%;">Rimuovi</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><input type="text" name="descrizione[]" required></td>
              <td><input type="number" name="quantita[]" step="1" min="1" required></td>
              <td><input type="number" name="prezzo[]" step="0.01" min="0" required></td>
              <td><button type="button" class="inline-btn" onclick="rimuoviRiga(this)"><i class="fa-regular fa-trash-can"></i></button></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="form-grid" style="margin-top:10px;">
        <div>
          <label>IVA (%)</label>
          <input type="number" name="iva" value="22" min="0" max="100" required>
        </div>
        <div>
          <label>Sconto (%)</label>
          <input type="number" name="sconto" value="0" min="0" max="100">
        </div>
      </div>

      <label>Note finali</label>
      <textarea name="note" rows="4" placeholder="Eventuali condizioni o messaggi..."></textarea>

      <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; margin-top:12px;">
        <button type="button" class="chip" onclick="aggiungiRiga()"><i class="fa-solid fa-plus"></i> Aggiungi Voce</button>
        <button type="submit" class="chip s-ok"><i class="fas fa-file-pdf"></i> Genera PDF</button>
      </div>
    </form>
  </section>
</main>

<script src="../assets/javascript/preventivo.js"></script>
<script src="../assets/javascript/main.js"></script>
</body>
     <?php include '../partials/footer.php' ?>

</html>
