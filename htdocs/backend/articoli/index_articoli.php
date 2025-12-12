<?php
session_start();
require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/funzioni_articoli.php';
require_once '../assets/funzioni/authz.php';

requireLogin();
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

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
    $pieces = array_filter([trim($u['nome'] ?? ''), trim($u['cognome'] ?? '')]);
    if ($pieces) return implode(' ', $pieces);
    if (!empty($u['username'])) return (string)$u['username'];
    if (!empty($u['email']))    return (string)$u['email'];
    if (!empty($u['id']))       return '#'.(int)$u['id'];
    return 'utente';
  }
}

$IS_ADMIN      = currentUserCan('users.manage');
$CAN_BLOG_EDIT = $IS_ADMIN || currentUserCan('blog.edit');
$CAN_BLOG_VIEW = $IS_ADMIN || $CAN_BLOG_EDIT || currentUserCan('blog.view');

if (!$CAN_BLOG_VIEW) {
  $SEZIONE_ICON  = 'fa-newspaper';
  $SEZIONE_LABEL = 'Articoli';
  $RICHIESTI     = ['blog.view'];
  http_response_code(403);
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
  </head>
  <body>
  <main>
    <header class="topbar mb-12">
      <div class="user-badge">
        <i class="fas <?= e($SEZIONE_ICON) ?> icon-user"></i>
        <div>
          <div class="muted">Gestione</div>
          <div class="fw-800 ls-02"><?= e($SEZIONE_LABEL) ?></div>
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
        </div>
      </div>
    </section>
  </main>
  <?php include '../partials/footer.php' ?>
  </body>
  </html>
  <?php
  exit;
}

$ultimiArticoli = estraiArticoli($conn, 3);
$tuttiArticoli  = estraiArticoli($conn);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Gestione Articoli</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
</head>
<body data-blog-can-view="<?= $CAN_BLOG_VIEW ? '1' : '0' ?>" data-blog-can-edit="<?= $CAN_BLOG_EDIT ? '1' : '0' ?>">

<div id="preview" class="preview-modal"></div>

<main>
  <header class="topbar mb-12">
    <div class="user-badge">
      <i class="fas fa-newspaper icon-user"></i>
      <div>
        <div class="muted">Gestione</div>
        <div class="fw-800 ls-02">Articoli</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>

    <div class="right">
      <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <?php if ($CAN_BLOG_EDIT): ?>
        <a href="nuovo_articolo.php" class="chip s-ok"><i class="fas fa-plus"></i> Aggiungi Nuovo Articolo</a>
      <?php endif; ?>
      <?php include '../partials/navbar.php'?>
    </div>
  </header>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminato' && $CAN_BLOG_EDIT): ?>
    <section class="panel s-ok mb-12">✅ Articolo eliminato con successo.</section>
  <?php endif; ?>

  <section class="panel mb-12">
    <div class="flex-between wrap ai-center gap-10">
      <h2 class="m-0 text-primary"><i class="fa-solid fa-star"></i> Ultimi Articoli Inseriti</h2>
      <div class="muted hide-sm"><i class="fa-regular fa-clock"></i> Gli ultimi 3 caricamenti</div>
    </div>

    <div class="books-row mt-12">
      <?php if ($ultimiArticoli): while ($r = $ultimiArticoli->fetch_assoc()): ?>
        <?php
          $id      = (int)$r['id'];
          $titolo  = (string)($r['title'] ?? $r['titolo'] ?? '');
          $cover   = norm_img((string)($r['cover'] ?? $r['copertina'] ?? $r['immagine'] ?? ''));
          $dateRaw = (string)($r['date'] ?? $r['data_pubblicazione'] ?? $r['created_at'] ?? '');
          $date    = $dateRaw ? formattaData($dateRaw) : '';
          $cats    = (string)($r['category'] ?? $r['categorie'] ?? '');
          $excerpt = (string)($r['excerpt'] ?? '');
          $link    = (string)($r['link'] ?? '');
        ?>
        <article class="book-card">
          <div class="cover">
            <?php if ($cover): ?>
              <img src="<?= e($cover) ?>" alt="<?= e($titolo) ?>">
            <?php else: ?>
              <i class="fa-regular fa-image cover-empty-ico"></i>
            <?php endif; ?>
          </div>
          <div class="body">
            <h4><?= e($titolo) ?></h4>
            <div class="meta">
              <?php if ($date): ?><i class="fa-regular fa-calendar"></i> <?= e($date) ?> &nbsp;&middot;&nbsp;<?php endif; ?>
              <?php if ($cats): ?><i class="fa-solid fa-tags"></i> <?= e($cats) ?><?php endif; ?>
            </div>
            <?php if ($excerpt): ?>
              <div class="sinossi" data-full="<?= e($excerpt) ?>"><?= e($excerpt) ?></div>
              <button class="chip sm showmore" data-toggle-sinossi type="button">Mostra +</button>
            <?php endif; ?>
            <div class="actions">
              <?php if ($CAN_BLOG_EDIT): ?>
                <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-pen-to-square"></i> Modifica</a>
                <?php if ($link): ?><a class="chip" href="<?= e($link) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-link"></i> Link</a><?php endif; ?>
                <button type="button" class="chip danger del-article" data-id="<?= $id ?>" data-title="<?= e($titolo) ?>" title="Elimina articolo">
                  <i class="fa-regular fa-trash-can"></i> Elimina
                </button>
              <?php else: ?>
                <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-eye"></i> Apri</a>
                <?php if ($link): ?><a class="chip" href="<?= e($link) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-link"></i> Link</a><?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endwhile; endif; ?>
    </div>
  </section>

  <section class="panel">
    <div class="flex-between wrap ai-center gap-10">
      <h2 class="m-0 text-primary"><i class="fa-solid fa-box-archive"></i> Archivio</h2>
      <div class="muted">Elenco completo</div>
    </div>

    <div class="books-row mt-12" id="allArticles">
      <?php
        $counter = 0;
        if ($tuttiArticoli):
          while ($r = $tuttiArticoli->fetch_assoc()):
            $id      = (int)$r['id'];
            $titolo  = (string)($r['title'] ?? $r['titolo'] ?? '');
            $cover   = norm_img((string)($r['cover'] ?? $r['copertina'] ?? $r['immagine'] ?? ''));
            $dateRaw = (string)($r['date'] ?? $r['data_pubblicazione'] ?? $r['created_at'] ?? '');
            $date    = $dateRaw ? formattaData($dateRaw) : '';
            $cats    = (string)($r['category'] ?? $r['categorie'] ?? '');
            $excerpt = (string)($r['excerpt'] ?? '');
            $link    = (string)($r['link'] ?? '');
            $hiddenAttr = ($counter >= 3) ? ' data-hidden="1"' : '';
      ?>
        <article class="book-card" data-archivio<?= $hiddenAttr ?>>
          <div class="cover">
            <?php if ($cover): ?>
              <img src="<?= e($cover) ?>" alt="<?= e($titolo) ?>">
            <?php else: ?>
              <i class="fa-regular fa-image cover-empty-ico"></i>
            <?php endif; ?>
          </div>
          <div class="body">
            <h4><?= e($titolo) ?></h4>
            <div class="meta">
              <?php if ($date): ?><i class="fa-regular fa-calendar"></i> <?= e($date) ?> &nbsp;&middot;&nbsp;<?php endif; ?>
              <?php if ($cats): ?><i class="fa-solid fa-tags"></i> <?= e($cats) ?><?php endif; ?>
            </div>
            <?php if ($excerpt): ?>
              <div class="sinossi" data-full="<?= e($excerpt) ?>"><?= e($excerpt) ?></div>
              <button class="chip sm showmore" data-toggle-sinossi type="button">Mostra +</button>
            <?php endif; ?>
            <div class="actions">
              <?php if ($CAN_BLOG_EDIT): ?>
                <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-pen-to-square"></i> Modifica</a>
                <?php if ($link): ?><a class="chip" href="<?= e($link) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-link"></i> Link</a><?php endif; ?>
                <button type="button" class="chip danger del-article" data-id="<?= $id ?>" data-title="<?= e($titolo) ?>" title="Elimina articolo">
                  <i class="fa-regular fa-trash-can"></i> Elimina
                </button>
              <?php else: ?>
                <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-eye"></i> Apri</a>
                <?php if ($link): ?><a class="chip" href="<?= e($link) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-link"></i> Link</a><?php endif; ?>
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
      <div class="center mt-14">
        <button class="chip" id="altro"><i class="fa-solid fa-angles-down"></i> Mostra altri</button>
      </div>
    <?php endif; ?>
  </section>
</main>

<div id="modale-articolo" class="modal" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalArticoloTitolo">
    <div class="m-header">
      <h2 id="modalArticoloTitolo" class="m-title">
        <i class="fa-regular fa-pen-to-square"></i> Modifica articolo
      </h2>
      <button type="button" class="chip" data-modal-close>
        <i class="fa-solid fa-xmark"></i> Chiudi
      </button>
    </div>

    <div class="m-body">
      <form id="formModificaArticolo" enctype="multipart/form-data">
        <input type="hidden" name="id">
        <input type="hidden" name="tipo" value="articolo">
        <input type="hidden" name="existing_img">

        <label>Titolo</label>
        <input type="text" name="title" required>

        <label>Categoria</label>
        <div class="cat-wrap">
          <div id="catChips" class="cat-chips"></div>
          <select name="category" id="categoriaArticolo" size="6" required></select>
          <div class="new-cat">
            <input type="text" id="nuovaCategoria" placeholder="➕ Nuova categoria (opzionale)">
            <button type="button" class="btn-ghost" id="btnAddCatArt"><i class="fa-solid fa-plus"></i> Aggiungi alla lista</button>
          </div>
          <small class="muted">La nuova categoria verrà inviata come <code>new_category</code>.</small>
          <input type="hidden" name="new_category" id="newCategoryArtHidden" value="">
        </div>

        <label>Descrizione</label>
        <textarea name="excerpt" id="excerptArticolo" rows="5" required></textarea>

        <label>Contenuto</label>
        <div id="contenutoEditor" class="q-editor"></div>
        <input type="hidden" name="contenuto" id="contenutoHidden">

        <label>Data</label>
        <input type="date" name="date" id="dateArticolo">

        <label>Copertina attuale</label>
        <div class="cover-preview">
          <img id="imgEsistenteArticolo" src="" alt="Copertina" class="d-none">
        </div>
        <div class="cover-tools">
          <input type="file" name="cover" id="coverInputArt" accept="image/*">
          <button type="button" class="btn-ghost" id="btnRemoveCoverArt"><i class="fa-regular fa-trash-can"></i> Rimuovi copertina</button>
        </div>

        <label>Link (opzionale)</label>
        <input type="text" name="link" placeholder="https://…">

        <div class="actions">
          <button type="button" class="chip" data-modal-close>Annulla</button>
          <button type="submit" class="chip s-ok" id="btnSaveArt"><i class="fa-solid fa-floppy-disk"></i> Salva modifiche</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../partials/footer.php'?>

<script src="../assets/javascript/main.js"></script>
<script src="../assets/javascript/articoli.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
</body>
</html>
