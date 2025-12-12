<?php
session_start();
require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni_libri.php';
require_once '../assets/funzioni/funzioni.php'; // se qui c'è requireLogin(), tienilo; altrimenti rimuovi questa riga
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

// Dati per i select
$categorie    = recuperaCategorieLibri($conn);
$caseEditrici = recuperaCaseEditrici($conn);

// Sticky defaults
$titolo    = $titolo    ?? '';
$sinossi   = $sinossi   ?? '';
$dataPub   = $dataPub   ?? '';
$nuovaCat  = $nuovaCat  ?? '';
$scelte    = $scelte    ?? [];
$inEvidenza= $inEvidenza?? false;
$link      = $link      ?? '';
$casaSel   = $casaSel   ?? '';
$casaNew   = $casaNew   ?? '';
$err       = $err       ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // recupero dati form
  $titolo      = trim($_POST['titolo'] ?? '');
  $sinossi     = trim($_POST['sinossi'] ?? '');
  $dataPub     = $_POST['data_pubblicazione'] ?? '';
  $nuovaCat    = trim($_POST['nuova_categoria'] ?? '');
  $scelte      = $_POST['categorie'] ?? [];
  $inEvidenza  = isset($_POST['in_evidenza']);
  $link        = trim($_POST['link'] ?? '');

  // casa editrice (nuova ha priorità)
  $casaSel      = trim($_POST['casa_editrice'] ?? '');
  $casaNew      = trim($_POST['nuova_casa_editrice'] ?? '');
  $casaEditrice = risolviCasaEditrice($casaNew, $casaSel);

  // upload copertina
  $copertinaPath = uploadCover($_FILES['copertina'] ?? []);

  // nuove categorie → risolvi/crea e unisci alle scelte
  if ($nuovaCat !== '') {
    $scelte = risolviCategorie($conn, $nuovaCat, $scelte);
  }

  // evidenza (unicità per categoria)
  if ($inEvidenza) {
    garantisciEvidenza($conn, $scelte);
  }

  // salva
  $dati = [
    'titolo'             => $titolo,
    'sinossi'            => $sinossi,
    'immagine'           => $copertinaPath,
    'data_pubblicazione' => $dataPub,
    'casa_editrice'      => $casaEditrice,
    'link'               => $link
  ];

  if (saveLibro($conn, $dati, $scelte)) {
    header('Location: index_libri.php?msg=ok');
    exit;
  } else {
    $err = 'Errore inserimento libro.';
  }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Nuovo Libro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="../assets/css/style1.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<main>
  <!-- Topbar -->
  <header class="topbar" style="margin-bottom:12px;">
    <div class="user-badge">
      <i class="fas fa-book icon-user"></i>
      <div>
        <div class="muted">Gestione</div>
        <div style="font-weight:800; letter-spacing:.2px;">Libri</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>
    <div class="right">
      <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <a class="chip" href="index_libri.php"><i class="fa-solid fa-box-archive"></i> Elenco Libri</a>
     <?php include '../partials/navbar.php' ?>
    </div>
  </header>

  <?php if (!empty($err)): ?>
    <section class="panel alert error"><?= e($err) ?></section>
  <?php endif; ?>

  <section class="panel">
    <form method="POST" enctype="multipart/form-data" class="fnew" id="bookForm">
      <!-- Colonna sinistra -->
      <div class="stack">
        <label for="titolo">Titolo</label>
        <input type="text" name="titolo" id="titolo" required value="<?= e($titolo) ?>">

        <label for="sinossi">Sinossi</label>
        <textarea name="sinossi" id="sinossi" rows="8" required><?= e($sinossi) ?></textarea>

        <label for="categoriaLibro">Lavorazioni svolte</label>
        <div class="cat-wrap">
          <div id="catChips" class="cat-chips"></div>
          <select name="categorie[]" id="categoriaLibro" multiple size="6">
            <?php foreach ($categorie as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>" <?= in_array($cat['id'], $scelte ?? [], true) ? 'selected' : '' ?>>
                <?= e($cat['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <label for="nuova_categoria">➕ Nuova Lavorazione (opzionale)</label>
        <input type="text" name="nuova_categoria" id="nuova_categoria" placeholder="Es. Traduzione, Editing" value="<?= e($nuovaCat) ?>">
      </div>

      <!-- Colonna destra -->
      <div class="stack">
        <div class="cover-group">
          <label for="copertina">Copertina</label>
          <div class="cover-preview">
            <img id="imgPreview" alt="Anteprima copertina" style="display:none">
          </div>
          <div class="cover-tools">
            <input type="file" name="copertina" id="copertina" accept="image/*">
            <button type="button" class="btn-ghost" id="btnRemoveCover">
              <i class="fa-regular fa-trash-can"></i> Rimuovi copertina
            </button>
          </div>
        </div>

        <label for="casa_editrice">Casa editrice</label>
        <select name="casa_editrice" id="casa_editrice">
          <option value="">— Seleziona —</option>
          <?php foreach ($caseEditrici as $ed): ?>
            <option value="<?= e($ed) ?>" <?= ($casaSel === $ed && $casaNew==='') ? 'selected' : '' ?>>
              <?= e($ed) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="nuova_casa_editrice">➕ Nuova casa editrice (opzionale)</label>
        <input type="text" name="nuova_casa_editrice" id="nuova_casa_editrice" placeholder="Es. Einaudi, Mondadori…" value="<?= e($casaNew) ?>">

        <label for="data_pubblicazione">Data pubblicazione</label>
        <input type="date" name="data_pubblicazione" id="data_pubblicazione" required value="<?= e($dataPub) ?>">

        <label for="link">Link</label>
        <input type="text" name="link" id="link" value="<?= e($link) ?>">

        <div class="evidenza-box">
          <label for="in_evidenza">
            <input type="checkbox" name="in_evidenza" id="in_evidenza" <?= !empty($inEvidenza) ? 'checked' : '' ?>>
            <span class="checkbox-icon"><i class="fa-solid fa-check"></i></span>
            Metti in evidenza
          </label>
        </div>

        <button type="submit"><i class="fas fa-book"></i> Aggiungi Libro</button>
      </div>

      <div class="col-span-2"></div>
    </form>
  </section>
</main>

<script src="../assets/javascript/libri.js"></script>
<script src="../assets/javascript/main.js"></script>
</body>
  <?php include '../partials/footer.php' ?>
</html>
