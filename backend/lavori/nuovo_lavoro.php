<?php
// file: lavori/nuovo_lavoro.php
session_start();
require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/funzioni_lavoro.php';
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

// Liste select (clienti/utenti/categorie)
list($clienti, $utenti, $categorie) = getSelectLists($conn);

// Tipi evento + legenda colori (da flusso_lavoro, con fallback ai default)
list($tipi_evento, $legend) = lavori_event_types_and_legend($conn);

// oggi di default per la data ricezione
$oggi = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Nuovo Lavoro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
</head>
<body>

<main>
  <header class="topbar" style="margin-bottom:12px;">
    <div class="user-badge">
      <i class="fas fa-briefcase icon-user"></i>
      <div>
        <div class="muted">Gestione</div>
        <div style="font-weight:800; letter-spacing:.2px;">Lavori</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>
    <div class="right">
    <a href="../index.php" class="chip"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
    <a href="../lavori/index_lavori.php" class="chip"><i class="fa-solid fa-folder-open"></i> Lista Lavori</a>
          <?php 
include '../partials/navbar.php'?>
      
    </div>
  </header>

  <section class="panel">
    <form method="POST" action="salva_lavoro.php" id="jobForm" class="fnew" autocomplete="off">
      <!-- Colonna sinistra -->
      <div class="stack">
        <label>Nome lavoro</label>
        <input type="text" name="titolo" required>

        <label>Cliente</label>
        <select name="cliente_sel" id="cliente_sel">
          <option value="">— Seleziona —</option>
          <?php foreach($clienti as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['nome'] ?? $c['label'] ?? ('Cliente #'.$c['id'])) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="note">
          Cliente non presente? Inseriscilo prima qui:
          <a href="../clienti/nuovo_cliente.php">Nuovo cliente</a>
        </div>

        <label>Provenienza</label>
        <input type="text" name="provenienza" placeholder="Es. Sito, Email, Passaparola…">

        <label>Descrizione (macro-lavoro)</label>
        <textarea name="descrizione" rows="6" placeholder="Note operative, scope generale, ecc."></textarea>

        <label>Categorie lavoro (se mono-utente) CMD/⌘ e click per selezionarle</label>
        <div class="cat-wrap">
          <div class="cat-chips" id="catChips"></div>
          <select name="categorie[]" id="categorie" multiple size="8" aria-label="Categorie lavoro">
            <?php foreach($categorie as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>"><?= e($cat['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <label><i class="fa-solid fa-plus"></i> Nuove categorie (separate da virgola)</label>
        <input type="text" name="nuove_categorie" id="nuove_categorie" placeholder="Es. Traduzione, Editing, Impaginazione">

        <label>Cartelle da lavorare</label>
        <div class="tag-wrap" id="tags" style="margin-top:-15px !important; "></div>
        <input type="text" id="tagInput" placeholder="Scrivi il numero di cartelle totali se previste" aria-label="Nuova cartella">
        <input type="hidden" name="cartelle_json" id="cartelle_json">
      </div>

      <!-- Colonna destra -->
      <div class="stack">
        <label>Ricevuto il</label>
        <input type="date" name="data_ricezione" required value="<?= e($oggi) ?>">

        <label>Scadenza (macro) — se prevista</label>
        <input type="date" name="scadenza">
        <div class="note">Se impostata, verrà creato un evento in calendario.</div>

        <label>Assegnato a (se mono-utente)</label>
        <select name="assegnato_a">
          <option value="">— Nessuno —</option>
          <?php foreach($utenti as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= e($u['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="note">Se impostato, verrà usato anche come assegnatario dell'evento macro.</div>

        <label>Totale lavoro (EUR)</label>
        <input type="number" name="prezzo" step="0.01" min="0" placeholder="0.00">
        <div class="note">Se vuoto, useremo la somma delle righe di lavorazioni. Se compilato, <strong>override</strong>.</div>

        <div class="panel" style="padding:12px;">
          <h3 class="h3-inline">
            <i class="fa-regular fa-calendar"></i> Evento calendario (macro)
          </h3>

          <label>Tipologia evento (macro)</label>
          <select name="macro_evento_tipo_sel" id="evento_tipo_sel">
            <?php foreach($tipi_evento as $t): ?>
              <option value="<?= e($t) ?>"><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
            <option value="__new__">➕ Nuova tipologia…</option>
          </select>

          <div id="evento_new_wrap" style="display:none; margin-top:8px;">
            <label>Nuova tipologia</label>
            <input type="text" name="macro_evento_tipo_new" id="evento_tipo_new" placeholder="Es. scadenza">
            <label style="margin-top:6px;">Colore evento (macro)</label>
            <input type="color" name="macro_evento_color" id="evento_color_new" value="#EF4444">
          </div>

          <?php if(!empty($legend)): ?>
            <div class="legend" style="margin-top:8px;">
              <?php foreach($legend as $tipo => $col): ?>
                <span class="chip"><span class="legend-color" style="background:<?= e($col) ?>"></span><?= e(ucfirst($tipo)) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- RIGHE ATTIVITÀ -->
      <div class="col-span-2" style="margin-top:15px">
        <h3 class="h3-inline">
        <i class="fa-solid fa-list"></i> Lavorazioni da svolgere (se multiutente)
        </h3>
        <div class="righe-wrap" id="righe">
          <!-- Riga template iniziale -->
          <div class="riga">
            <div class="field">
              <label>Assegnato a</label>
              <select name="righe_utente_id[]">
                <option value="">— Nessuno —</option>
                <?php foreach($utenti as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Categoria</label>
              <select name="righe_categoria_id[]">
                <option value="">— Seleziona —</option>
                <?php foreach($categorie as $cat): ?>
                  <option value="<?= (int)$cat['id'] ?>"><?= e($cat['nome']) ?></option>
                <?php endforeach; ?>
                <option value="__new__">➕ Nuova categoria…</option>
              </select>
              <input type="text" name="righe_categoria_new[]" placeholder="Nuova categoria" style="display:none;">
            </div>
            <div class="field">
              <label>Titolo</label>
              <input type="text" name="righe_titolo[]" placeholder="Es. Editing capitolo 1">
            </div>
            <div class="field">
              <label>Scadenza</label>
              <input type="date" name="righe_scadenza[]">
            </div>
            <div class="field">
              <label>Prezzo €</label>
              <input type="number" name="righe_prezzo[]" step="0.01" min="0" placeholder="0.00">
            </div>
            <div class="field">
              <label>Tipo evento</label>
              <select name="righe_evento_tipo_sel[]">
                <?php foreach($tipi_evento as $t): ?>
                  <option value="<?= e($t) ?>" <?= $t==='lavorazione'?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
                <option value="__new__">➕ Nuova tipologia…</option>
              </select>
              <input type="text" name="righe_evento_tipo_new[]" placeholder="Nuova tipologia" style="display:none;">
            </div>
            <div class="field">
              <label>Colore</label>
              <input type="color" name="righe_evento_color[]" value="<?= e(lavori_default_color('lavorazione')) ?>">
            </div>
            <div class="field wide">
              <label>Descrizione / Note attività</label>
              <input type="text" name="righe_descrizione[]" placeholder="Note specifiche. Se vuote, in calendario: “Non ci sono note”.">
            </div>
            <div class="del">
              <button type="button" class="chip js-del" title="Rimuovi riga"><i class="fa-regular fa-trash-can"></i></button>
            </div>
          </div>
        </div>

        <button type="button" id="addRow" class="chip" style="margin-top:10px;"><i class="fa-solid fa-plus"></i> Aggiungi riga</button>
      </div>

      <div class="col-span-2"></div>

      <div class="col-span-2 actions" style="margin-top:10px;">
        <button type="submit" class="action s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva lavoro</button>
        <a href="../index.php" class="action"><i class="fa-solid fa-xmark"></i> Annulla</a>
      </div>
    </form>
  </section>
</main>

<script>
  window.__TIPI_EVENTO = <?= json_encode(array_values($tipi_evento), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="../assets/javascript/lavori.js"></script>
<script src="../assets/javascript/main.js"></script>
</body>
  <?php include '../partials/footer.php' ?>
</html>
