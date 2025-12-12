<?php
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
require_once __DIR__ . '/../assets/funzioni/funzioni_articoli.php';
require_once __DIR__ . '/../assets/funzioni/authz.php';

requireLogin();

$IS_ADMIN = currentUserCan('users.manage');
if (!$IS_ADMIN) {
  $SEZIONE_ICON  = 'fa-envelope';
  $SEZIONE_LABEL = 'Richieste';
  $RICHIESTI     = ['users.manage'];

  if (!function_exists('e')) {
    function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
  }
  $PERM_LABELS = [
    'users.manage'   => 'Amministrazione',
    'portfolio.view' => 'Accesso al portfolio',
    'blog.view'      => 'Accesso agli articoli',
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
        <?php include __DIR__ . '/../partials/navbar.php'; ?>
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
  <?php include __DIR__ . '/../partials/footer.php'; ?>
  </body>
  </html>
  <?php
  exit;
}

if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

$title   = $title   ?? '';
$excerpt = $excerpt ?? '';
$content = $content ?? '';
$selCat  = $selCat  ?? '';
$newCat  = $newCat  ?? '';
$err     = $err     ?? '';
$flashOk = ($_GET['msg'] ?? '') === 'ok' ? 'Articolo creato con successo!' : '';

$categorie = getCategorieArticoli($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title   = trim($_POST['title'] ?? '');
  $excerpt = trim($_POST['excerpt'] ?? '');
  $content = $_POST['content'] ?? '';
  $selCat  = $_POST['category'] ?? '';
  $newCat  = trim($_POST['newCategory'] ?? '');
  $dataPub = date('Y-m-d H:i:s');

  if ($selCat !== '__new__') { $newCat = ''; }

  $catID = resolveCategoria($conn, $selCat, $newCat);
  $cover = uploadCover($_FILES['cover'] ?? [], '../uploads/articoli');

  $dati = [
    'title'        => $title,
    'excerpt'      => $excerpt,
    'content'      => $content,
    'categoria_id' => $catID,
    'copertina'    => $cover,
    'data_pub'     => $dataPub
  ];

  $idArticolo = saveArticle($conn, $dati);

  if ($idArticolo) {
    registraAttivita($conn, 'Creato nuovo articolo ID ' . $idArticolo . ' - Titolo: ' . $title);
    header('Location: index_articoli.php?msg=ok');
    exit;
  } else {
    $err = 'Errore salvataggio articolo.';
  }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Nuovo Articolo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="../assets/css/style1.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
</head>
<body>
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
      <a class="chip" href="index_articoli.php"><i class="fa-solid fa-box-archive"></i> Elenco Articoli</a>
      <?php include __DIR__ . '/../partials/navbar.php'; ?>
    </div>
  </header>

  <?php if (!empty($flashOk)): ?>
    <section class="panel s-ok"><?= e($flashOk) ?></section>
  <?php elseif (!empty($err)): ?>
    <section class="panel alert error"><?= e($err) ?></section>
  <?php endif; ?>

  <section class="panel">
    <form id="articleForm" method="POST" enctype="multipart/form-data" class="fnew">
      <div class="stack">
        <label for="title">Titolo</label>
        <input id="title" name="title" type="text" required value="<?= e($title) ?>">

        <label for="category">Categoria</label>
        <div class="cat-wrap">
          <div id="catChips" class="cat-chips single"></div>
          <select id="category" name="category" required>
            <option value="">— Seleziona —</option>
            <?php foreach ($categorie as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((string)$selCat === (string)$c['id']) ? 'selected' : '' ?>>
                <?= e($c['nome']) ?>
              </option>
            <?php endforeach; ?>
            <option value="__new__" <?= ($selCat === '__new__' ? 'selected' : '') ?>>➕ Nuova categoria…</option>
          </select>
          <div id="newCatBox" class="slidebox">
            <input type="text" id="newCategory" name="newCategory" placeholder="Nome nuova categoria" value="<?= e($newCat) ?>" disabled>
          </div>
        </div>

        <label for="excerpt">Breve descrizione</label>
        <textarea id="excerpt" name="excerpt" rows="5" required><?= e($excerpt) ?></textarea>

        <label>Contenuto completo</label>
        <div id="editor"></div>
        <input type="hidden" name="content" id="hiddenContent" value="<?= e($content) ?>">
      </div>

      <div class="stack">
        <div class="cover-group">
          <label for="cover">Copertina (opzionale)</label>
          <div class="cover-preview">
            <img id="imgPreview" alt="Anteprima copertina" class="hidden">
          </div>
          <div class="cover-tools">
            <input id="cover" name="cover" type="file" accept="image/*">
            <button type="button" class="btn-ghost" id="btnRemoveCover">
              <i class="fa-regular fa-trash-can"></i> Rimuovi copertina
            </button>
          </div>
        </div>

        <button type="submit"><i class="fas fa-paper-plane"></i> Salva Articolo</button>
      </div>

      <div class="col-span-2"></div>
    </form>
  </section>
</main>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="../assets/javascript/articoli.js"></script>
<script src="../assets/javascript/main.js"></script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
