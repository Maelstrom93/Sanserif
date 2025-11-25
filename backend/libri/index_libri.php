<?php
// backend/libri/index_libri.php
session_start();
require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/funzioni_libri.php';
require_once '../assets/funzioni/authz.php'; // ⬅️ nuovo

requireLogin();

// ==== PERMESSI ====
$IS_ADMIN   = currentUserCan('users.manage');
$CAN_VIEW   = $IS_ADMIN || currentUserCan('portfolio.view');
$CAN_EDIT   = $IS_ADMIN || currentUserCan('portfolio.edit');   // modifica
$CAN_CREATE = $IS_ADMIN || currentUserCan('portfolio.create'); // facoltativo
$CAN_DELETE = $IS_ADMIN || currentUserCan('portfolio.delete'); // facoltativo

// Regola richiesta:
// - Utenti base: NO accesso
// - Utenti intermedi: possono entrare se hanno almeno portfolio.view
if (!$CAN_VIEW) {
  // === VARIABILI PERSONALIZZABILI PER LA PAGINA ===
$SEZIONE_ICON  = 'fa-book';
$SEZIONE_LABEL = 'Libri';
$RICHIESTI     = ['portfolio.view'];     // Articoli: ['blog.view'] - Libri: ['portfolio.view']
  // ================================================
  http_response_code(403);
  
  // === Friendly labels per permessi + display name utente =====================
$PERM_LABELS = [
  'portfolio.view'   => 'Accesso al portfolio',
  'portfolio.edit'   => 'Modifica portfolio',
  'portfolio.create' => 'Creazione elementi portfolio',
  'portfolio.delete' => 'Eliminazione elementi portfolio',
  'blog.view'        => 'Accesso agli articoli',
  'blog.edit'        => 'Modifica articoli',
  'users.manage'     => 'Gestione utenti',
];

if (!function_exists('permLabel')) {
  function permLabel(string $slug): string {
    global $PERM_LABELS;
    return $PERM_LABELS[$slug] ?? $slug;
  }
}

if (!function_exists('currentUserDisplay')) {
  function currentUserDisplay(): string {
    $u = $_SESSION['utente'] ?? [];
    // prova in ordine: nome + cognome, username, email, altrimenti #id
    $pieces = array_filter([trim($u['nome'] ?? ''), trim($u['cognome'] ?? '')]);
    if ($pieces) return implode(' ', $pieces);
    if (!empty($u['username'])) return (string)$u['username'];
    if (!empty($u['email']))    return (string)$u['email'];
    if (!empty($u['id']))       return '#'.(int)$u['id'];
    return 'utente';
  }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>403 • Accesso negato</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
  <style>
    .hero403{
      padding: 28px 22px;
      border-radius: 14px;
      background: linear-gradient(135deg,#f8fafc 0%,#eef7fb 100%);
      border: 1px solid #dbe8ef;
    }
    .hero403 h1{
      margin: 0 0 6px 0;
      font-size: 22px;
      color: #0f172a;
      display:flex; align-items:center; gap:10px;
    }
    .hero403 p{ margin: 6px 0 0 0; color:#334155 }
    .hero403 .tips{ margin-top:12px; display:flex; gap:8px; flex-wrap:wrap }
    .hero403 .chip{ font-weight:600 }
    .hero403 .need{ margin-top:10px; font-size:13px; color:#475569 }
    .hero403 .need code{ background:#fff; border:1px solid #e2e8f0; padding:2px 6px; border-radius:6px }
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
      <?php include '../partials/navbar.php' ?>
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
        <?php /* opzionale: mostra un pulsante di richiesta accesso se hai una pagina/policy dedicata */ ?>
        <!-- <a class="chip s-ok" href="/backend/supporto/richiesta-permessi.php"><i class="fa-solid fa-key"></i> Richiedi accesso</a> -->
      </div>
    </div>
  </section>
</main>
<?php include '../partials/footer.php' ?>
</body>
</html>
<?php exit; }
?>
<?php


// Dati
$ultimiLibri = estraiLibri($conn, 3); // ultimi 3
$tuttiLibri  = estraiLibri($conn);    // archivio completo
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Gestione Libri</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
<style>
 .chip.s-danger{ background:#dc2626!important; border:1px solid #b91c1c!important; color:#fff!important;cursor:pointer; }

  .chip.s-danger i{ color: inherit; }

  .chip.s-danger:hover,
  .chip.s-danger:focus-visible{
    background: #b91c1c;
    border-color: #991b1b;
    transform: translateY(-1px);
  }

  .chip.s-danger:active{
    transform: none;
  }

  .chip.s-danger[disabled]{
    opacity: .6;
    cursor: not-allowed;
  }

</style>
  
</head>
<body>

<div id="preview" class="preview-modal"></div>

<main>
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
<?php if ($CAN_CREATE || $CAN_EDIT): ?>
  <a href="nuovo_libro.php" class="chip s-ok" style="text-decoration:none">
    <i class="fas fa-plus"></i> Aggiungi Nuovo Libro
  </a>
<?php endif; ?>

      <?php include '../partials/navbar.php' ?>

    </div>
  </header>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminato'): ?>
    <section class="panel s-ok" style="margin-bottom:12px;">✅ Libro eliminato con successo.</section>
  <?php endif; ?>

  <!-- Ultimi -->
  <section class="panel" style="margin-bottom:12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <h2 style="margin:0; color:#004c60;"><i class="fa-solid fa-star"></i> Ultimi Libri Inseriti</h2>
      <div class="muted hide-sm"><i class="fa-regular fa-clock"></i> Gli ultimi 3 caricamenti</div>
    </div>

    <div class="books-row" style="margin-top:12px;">
      <?php if ($ultimiLibri): while ($r = $ultimiLibri->fetch_assoc()): ?>
        <?php
          $id      = (int)$r['id'];
          $titolo  = (string)($r['titolo'] ?? '');
          $cover   = norm_img((string)($r['immagine'] ?? ''));
          $dateRaw = (string)($r['data_pubblicazione'] ?? '');
          $date    = $dateRaw ? formattaData($dateRaw) : '';
          $cats    = (string)($r['categorie'] ?? '');
          $sinossi = (string)($r['sinossi'] ?? '');
          $link    = (string)($r['link'] ?? '');
        ?>
        <article class="book-card">
          <div class="cover">
            <?php if ($cover): ?>
              <img src="<?= e($cover) ?>" alt="<?= e($titolo) ?>">
            <?php else: ?>
              <i class="fa-regular fa-image" style="font-size:48px; color:#cbd5e1;"></i>
            <?php endif; ?>
          </div>
          <div class="body">
            <h4><?= e($titolo) ?></h4>
            <div class="meta">
              <?php if ($date): ?><i class="fa-regular fa-calendar"></i> <?= e($date) ?> &nbsp;&middot;&nbsp;<?php endif; ?>
              <?php if ($cats): ?><i class="fa-solid fa-tags"></i> <?= e($cats) ?><?php endif; ?>
            </div>
            <?php if ($sinossi): ?>
              <div class="sinossi" data-full="<?= e($sinossi) ?>"><?= e($sinossi) ?></div>
              <button class="chip sm showmore" data-toggle-sinossi type="button">Mostra +</button>
            <?php endif; ?>
            <div class="actions">
  <?php if ($CAN_EDIT): ?>
    <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-pen-to-square"></i> Modifica</a>
  <?php endif; ?>
  <?php if ($link): ?>
    <a class="chip" href="<?= e($link) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-link"></i> Link</a>
  <?php endif; ?>
  <?php if ($CAN_DELETE): ?>
  <button type="button"
          class="chip s-danger btn-del-libro"
        title="Elimina ⚠️"
          data-id="<?= $id ?>"
          data-title="<?= e($titolo) ?>" >
    <i class="fa-regular fa-trash-can"></i> Elimina
  </button>
<?php endif; ?>
</div>
          </div>
        </article>
      <?php endwhile; endif; ?>
    </div>
  </section>

  <!-- Archivio -->
  <section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <h2 style="margin:0; color:#004c60;"><i class="fa-solid fa-box-archive"></i> Archivio</h2>
      <div class="muted">Elenco completo</div>
    </div>

    <div class="books-row" id="allArticles" style="margin-top:12px;">
      <?php
        $counter = 0;
        if ($tuttiLibri):
          while ($r = $tuttiLibri->fetch_assoc()):
            $id      = (int)$r['id'];
            $titolo  = (string)($r['titolo'] ?? '');
            $cover   = norm_img((string)($r['immagine'] ?? ''));
            $dateRaw = (string)($r['data_pubblicazione'] ?? '');
            $date    = $dateRaw ? formattaData($dateRaw) : '';
            $cats    = (string)($r['categorie'] ?? '');
            $sinossi = (string)($r['sinossi'] ?? '');
            $link    = (string)($r['link'] ?? '');
            $hiddenAttr = ($counter >= 3) ? ' data-hidden="1"' : '';
      ?>
        <article class="book-card" data-archivio<?= $hiddenAttr ?>>
          <div class="cover">
            <?php if ($cover): ?>
              <img src="<?= e($cover) ?>" alt="<?= e($titolo) ?>">
            <?php else: ?>
              <i class="fa-regular fa-image" style="font-size:48px; color:#cbd5e1;"></i>
            <?php endif; ?>
          </div>
          <div class="body">
            <h4><?= e($titolo) ?></h4>
            <div class="meta">
              <?php if ($date): ?><i class="fa-regular fa-calendar"></i> <?= e($date) ?> &nbsp;&middot;&nbsp;<?php endif; ?>
              <?php if ($cats): ?><i class="fa-solid fa-tags"></i> <?= e($cats) ?><?php endif; ?>
            </div>
            <?php if ($sinossi): ?>
              <div class="sinossi" data-full="<?= e($sinossi) ?>"><?= e($sinossi) ?></div>
              <button class="chip sm showmore" data-toggle-sinossi type="button">Mostra +</button>
            <?php endif; ?>
           <div class="actions">
  <?php if ($CAN_EDIT): ?>
    <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-pen-to-square"></i> Modifica</a>
  <?php else: ?>
    <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-eye"></i> Apri</a>
  <?php endif; ?>
  <?php if ($link): ?>
    <a class="chip" href="<?= e($link) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-link"></i> Link</a>
  <?php endif; ?>
  <?php if ($CAN_DELETE): ?>
  <button type="button"
          class="chip s-danger btn-del-libro"
          title="Elimina ⚠️"
          data-id="<?= $id ?>"
          data-title="<?= e($titolo) ?>">
    <i class="fa-regular fa-trash-can"></i> Elimina
  </button>
<?php endif; ?>

  </div>

          </div>
        </article>
      <?php
            $counter++;
          endwhile;
        endif;
      ?>
    </div>

    <?php if ($counter > 3): ?>
      <div style="display:flex; justify-content:center; margin-top:14px;">
        <button class="chip" id="altro" style="z-index:289;"><i class="fa-solid fa-angles-down"></i> Mostra altri</button>
      </div>
    <?php endif; ?>
  </section>
</main>

<!-- ===== MODALE ===== -->
<div id="modale-libro" class="modal" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalLibroTitolo">
    <div class="m-header">
      <h2 id="modalLibroTitolo" class="m-title">
        <i class="fa-regular fa-pen-to-square"></i> Modifica libro
      </h2>
      <button type="button" class="chip" data-modal-close>
        <i class="fa-solid fa-xmark"></i> Chiudi
      </button>
    </div>

    <div class="m-body">
      <form id="formModificaLibro" enctype="multipart/form-data">
        <input type="hidden" name="id">
        <input type="hidden" name="tipo" value="libro">
        <input type="hidden" name="existing_img">

        <label>Titolo</label>
        <input type="text" name="titolo" required>

        <label>Categorie</label>
        <div class="cat-wrap">
          <div id="catChips" class="cat-chips"></div>
          <select name="category[]" id="categoriaLibro" multiple size="6" required></select>
          <div class="new-cat">
            <input type="text" id="newCategory" placeholder="➕ Nuova categoria (opzionale)">
            <button type="button" class="btn-ghost" id="btnAddCat"><i class="fa-solid fa-plus"></i> Aggiungi alla lista</button>
          </div>
          <small class="muted">La nuova categoria verrà inviata come <code>new_category</code> (serve supporto in API).</small>
        </div>

        <label>Sinossi</label>
        <textarea name="excerpt" id="excerptLibro" rows="5" required></textarea>

       <!-- [X] CASA EDITRICE (SELECT + ALTRO) -->
<label>Casa editrice</label>
<select name="casa_editrice" id="casaEditriceLibro" required></select>

<!-- appare solo se scegli "Altro…" -->
<div id="casaEditriceCustomWrap" style="display:none; margin-top:.4rem">
  <input type="text" id="casaEditriceCustom" placeholder="Inserisci nuova casa editrice">
</div>
<!-- [Y] FINE CASA EDITRICE -->



        <label>Data di pubblicazione</label>
        <input type="date" name="date" id="dateLibro" required>

        <label>Copertina attuale</label>
        <div class="cover-preview">
          <img id="imgEsistenteLibro" src="" alt="Copertina" style="display:none">
        </div>
        <div class="cover-tools">
          <input type="file" name="cover" id="coverInput" accept="image/*">
          <button type="button" class="btn-ghost" id="btnRemoveCover"><i class="fa-regular fa-trash-can"></i> Rimuovi copertina</button>
        </div>

        <label>Link</label>
        <input type="text" name="link" placeholder="https://…">

        <input type="hidden" name="new_category" id="newCategoryHidden" value="">

        <div class="actions">
          <button type="button" class="chip" data-modal-close>Annulla</button>
          <button type="submit" class="chip s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva modifiche</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
  window.__LIBRI = {
    canView:   <?= json_encode($CAN_VIEW) ?>,
    canEdit:   <?= json_encode($CAN_EDIT) ?>,
    canCreate: <?= json_encode($CAN_CREATE) ?>,
    canDelete: <?= json_encode($CAN_DELETE) ?>
  };
</script>

<!-- JS principale per Libri -->
<script src="../assets/javascript/libri.js"></script>
<script src="../assets/javascript/main.js"></script>
</body>
  <?php include '../partials/footer.php' ?>
</html>
