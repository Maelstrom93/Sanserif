<?php
// file: backend/admin/categorie.php
session_start();

require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
require_once __DIR__ . '/../api/exceptions.php';
const ERR_UPDATE = 'Aggiornamento non riuscito.';
const ERR_DELETE = 'Eliminazione non riuscita.';
const ERR_CREATE = 'Creazione non riuscita.';

requireLogin();
if (!isAdmin()) {
  http_response_code(403);
  die('Accesso negato');
}

/* ---------------------------
   Helpers UI (flash) + escape
----------------------------*/
if (!function_exists('setFlash')) {
  function setFlash($msg){ $_SESSION['_flash_msg'] = (string)$msg; }
}
if (!function_exists('getFlash')) {
  function getFlash(){
    $m = $_SESSION['_flash_msg'] ?? '';
    unset($_SESSION['_flash_msg']);
    return $m;
  }
}
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* ---------------------------
   Funzioni DB: categorie
----------------------------*/

function getCategorieLibri(mysqli $conn): array {
  $out = [];
  $rs = $conn->query("SELECT id, nome FROM categorie_libri ORDER BY nome ASC");
  if ($rs) {
    while ($r = $rs->fetch_assoc()) {
      $out[] = $r;
    }
  }
  return $out;
}

function getCategorieLavoro(mysqli $conn): array {
  $out = [];
  $rs = $conn->query("SELECT id, nome, created_at FROM categorie_lavoro ORDER BY nome ASC");
  if ($rs) {
    while ($r = $rs->fetch_assoc()) {
      $out[] = $r;
    }
  }
  return $out;
}

function nomeEsiste(mysqli $conn, string $tabella, string $nome, int $excludeId = 0): bool {
  $sql = "SELECT id FROM {$tabella} WHERE nome = ? AND id <> ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param("si", $nome, $excludeId);
  $stmt->execute();
  return (bool) $stmt->get_result()->fetch_assoc();
}

function getNomeById(mysqli $conn, string $tabella, int $id): ?string {
  $sql = "SELECT nome FROM {$tabella} WHERE id = ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return null;
  }
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  return $res['nome'] ?? null;
}

/* ---- conteggi d’uso per bloccare eliminazioni ---- */
function countUsoCategoriaArticoli(mysqli $conn, int $id): int {
  $nome = getNomeById($conn, 'categorie_articoli', $id);
  if ($nome === null) {
    return 0;
  }
  $stmt = $conn->prepare("SELECT COUNT(*) c FROM articoli WHERE categoria = ?");
  if (!$stmt) {
    return 0;
  }
  $stmt->bind_param("s", $nome);
  $stmt->execute();
  return (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
}

function countUsoCategoriaLibri(mysqli $conn, int $id): int {
  $tot = 0;
  $sqls = [
    "SELECT COUNT(*) c FROM libri_categorie WHERE categoria_id = ?",
    "SELECT COUNT(*) c FROM lavori_categorie WHERE categoria_id = ?",
    "SELECT COUNT(*) c FROM lavori_attivita WHERE categoria_id = ?",
  ];

  foreach ($sqls as $sql) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      continue;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $tot += (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  }

  return $tot;
}

function countUsoCategoriaLavoro(mysqli $conn, int $id): int {
  $tot = 0;
  $sqls = [
    "SELECT COUNT(*) c FROM lavori_categorie WHERE categoria_id = ?",
    "SELECT COUNT(*) c FROM lavori_attivita WHERE categoria_id = ?",
  ];

  foreach ($sqls as $sql) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      continue;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $tot += (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  }

  return $tot;
}

/* ---------------------------
   AJAX: Crea/Aggiorna/Elimina
----------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
  header('Content-Type: application/json; charset=utf-8');

  try {
    $entity = $_POST['entity'] ?? '';
    if (!in_array($entity, ['articoli','libri','lavoro'], true)) {
      throw new BadRequestException('Tipo non valido.');
    }

    $tabella = [
      'articoli' => 'categorie_articoli',
      'libri'    => 'categorie_libri',
      'lavoro'   => 'categorie_lavoro',
    ][$entity];

    $action = $_POST['action'] ?? 'save';

    // DELETE
    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        throw new ValidationException('ID mancante.');
      }

      if ($entity === 'articoli' && countUsoCategoriaArticoli($conn, $id) > 0) {
        throw new OperationFailedException('Impossibile eliminare: categoria usata da almeno un articolo.');
      }
      if ($entity === 'libri' && countUsoCategoriaLibri($conn, $id) > 0) {
        throw new OperationFailedException('Impossibile eliminare: categoria in uso (libri o lavori).');
      }
      if ($entity === 'lavoro' && countUsoCategoriaLavoro($conn, $id) > 0) {
        throw new OperationFailedException('Impossibile eliminare: categoria in uso su lavori/attività.');
      }

      $stmt = $conn->prepare("DELETE FROM {$tabella} WHERE id = ?");
      if (!$stmt) {
        throw new OperationFailedException(ERR_DELETE);
      }
      $stmt->bind_param("i", $id);

      if (!$stmt->execute()) {
        throw new OperationFailedException(ERR_DELETE);
      }

      echo json_encode(['success'=>true, 'msg'=>'Categoria eliminata']);
      exit;
    }

    // SAVE (create/update)
    $id   = (int)($_POST['id'] ?? 0);
    $nome = trim((string)($_POST['nome'] ?? ''));

    if ($nome === '') {
      throw new OperationFailedException('Il nome è obbligatorio.');
    }
    if (mb_strlen($nome) > 191) {
      throw new OperationFailedException('Nome troppo lungo.');
    }

    if (nomeEsiste($conn, $tabella, $nome, $id)) {
      throw new ConflictException('Esiste già una categoria con questo nome.');
    }

    // UPDATE
    if ($id > 0) {
      if ($entity === 'articoli') {
        $old = getNomeById($conn, $tabella, $id);
        if ($old === null) {
          throw new NotFoundException('Categoria non trovata.');
        }

        $stmt = $conn->prepare("UPDATE {$tabella} SET nome = ? WHERE id = ?");
        if (!$stmt) {
          throw new OperationFailedException(ERR_UPDATE);
        }
        $stmt->bind_param("si", $nome, $id);

        if (!$stmt->execute()) {
          throw new OperationFailedException(ERR_UPDATE);
        }

        if ($old !== $nome) {
          $stmt2 = $conn->prepare("UPDATE articoli SET categoria = ? WHERE categoria = ?");
          if ($stmt2) {
            $stmt2->bind_param("ss", $nome, $old);
            $stmt2->execute();
          }
        }
      } else {
        $stmt = $conn->prepare("UPDATE {$tabella} SET nome = ? WHERE id = ?");
        if (!$stmt) {
          throw new OperationFailedException(ERR_UPDATE);
        }
        $stmt->bind_param("si", $nome, $id);

        if (!$stmt->execute()) {
          throw new OperationFailedException(ERR_UPDATE);
        }
      }

      echo json_encode(['success'=>true, 'msg'=>'Categoria aggiornata']);
      exit;
    }

    // CREATE
    $stmt = $conn->prepare("INSERT INTO {$tabella} (nome) VALUES (?)");
    if (!$stmt) {
      throw new OperationFailedException(ERR_CREATE);
    }
    $stmt->bind_param("s", $nome);

    if (!$stmt->execute()) {
      throw new OperationFailedException(ERR_CREATE);
    }

    echo json_encode(['success'=>true, 'msg'=>'Categoria creata']);
    exit;

  } catch (ValidationException|BadRequestException $e) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
    exit;
  } catch (NotFoundException $e) {
    http_response_code(404);
    echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
    exit;
  } catch (ConflictException $e) {
    http_response_code(409);
    echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
    exit;
  } catch (OperationFailedException $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'msg'=>'Errore inatteso']);
    exit;
  }
}




/* ---------------------------
   Dati pagina
----------------------------*/
$msg = function_exists('getFlash') ? getFlash() : '';
$nomeUt = e($_SESSION['utente']['nome'] ?? 'Utente');
$ruolo  = strtoupper($_SESSION['utente']['ruolo'] ?? 'ADMIN');

$catArticoli = getCategorieArticoli($conn);
$catLibri    = getCategorieLibri($conn);
$catLavoro   = getCategorieLavoro($conn);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Categorie — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="../assets/css/style1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .tabs{ display:flex; gap:8px; flex-wrap:wrap; }
    .tab-btn{ padding:8px 12px; border:1px solid var(--border); border-radius:999px; background:#fff; cursor:pointer; }
    .tab-btn.active{ background:#0f172a; color:#fff; border-color:#0f172a; }
    .tab-panel{ display:none; margin-top:12px; }
    .tab-panel.active{ display:block; }
    .form-grid{ display:grid; grid-template-columns: 1fr 160px; gap:10px; }
    @media (max-width:720px){ .form-grid{ grid-template-columns:1fr; } }
    .table.compact td, .table.compact th{ padding:10px 8px; font-size:13px; }
    .btn{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border:1px solid var(--border);
          border-radius:999px; background:#fff; color:#0f172a; cursor:pointer; text-decoration:none; }
    .btn:hover{ filter:brightness(.98) }
    .btn.danger{ background:#fff5f5; border-color:#fecaca; color:#7f1d1d; }
    .toast{ position:fixed; right:16px; bottom:16px; background:#0f172a; color:#fff; padding:10px 12px; border-radius:10px;
            box-shadow:0 10px 24px rgba(0,0,0,.18); z-index:1500; display:none; }
    .toast.show{ display:block; }
    input[type="text"]{ border:1px solid var(--border) !important; border-radius:10px; padding:10px 12px; background:#fff; }
    .invalid{ border-color:#dc2626 !important; box-shadow:0 0 0 3px rgba(220,38,38,.12); }
    .muted small{ font-size:12px; color:var(--muted); }
  </style>
</head>
<body>
<main>
  <!-- Topbar -->
    <header class="topbar">
    <div class="user-badge">
      <i class="fas fa-shield-halved icon-user"></i>
      <div>
        <div class="muted">Area Amministrativa</div>
        <div style="font-weight:800;letter-spacing:.2px;">Log attività</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>
    <div class="right">
      <a class="chip" href="../index.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <a class="chip" href="index_admin.php"><i class="fa-solid fa-toolbox"></i> Admin</a>
      <a class="chip" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </header>

  <!-- Hero -->
  <section class="panel" style="margin-top:16px;">
    <h2 style="margin:0 0 8px;"><i class="fa-solid fa-tags"></i> Gestione Categorie</h2>
    <div class="muted">Visualizza, crea, rinomina o elimina le categorie di <strong>Articoli</strong>, <strong>Libri</strong> e <strong>Lavori</strong>.</div>
    <?php if (!empty($msg)): ?>
      <div class="panel s-ok" style="margin-top:10px;"><?= e($msg) ?></div>
    <?php endif; ?>
  </section>

  <!-- Tabs -->
  <section class="panel" style="margin-top:12px;">
    <div class="tabs" role="tablist" aria-label="Seleziona tipologia">
      <button class="tab-btn active" data-tab="articoli" role="tab" aria-selected="true"><i class="fa-regular fa-newspaper"></i> Articoli</button>
      <button class="tab-btn" data-tab="libri" role="tab" aria-selected="false"><i class="fa-solid fa-book"></i> Libri</button>
      <button class="tab-btn" data-tab="lavoro" role="tab" aria-selected="false"><i class="fa-solid fa-briefcase"></i> Lavori</button>
    </div>

    <!-- Panel: Articoli -->
    <div id="panel-articoli" class="tab-panel active">
      <h3 style="margin:14px 0 8px;">Categorie Articoli</h3>
      <form class="form-grid cat-form" data-entity="articoli" novalidate>
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="entity" value="articoli">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="">
        <div>
     <label for="nome-articoli">Nome categoria</label>
<input id="nome-articoli" type="text" name="nome" placeholder="Es. Innovazione Editoriale">
          <div class="muted"><small>Rinominando, il nuovo nome verrà propagato sugli articoli collegati.</small></div>
        </div>
        <div style="display:flex; gap:8px; align-items:end;">
          <button type="button" class="btn btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</button>
          <button type="submit" class="btn s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
        </div>
      </form>

      <div class="table-responsive" style="margin-top:12px;">
        <table class="table compact">
          <thead><tr><th style="width:80px;">ID</th><th>Nome</th><th style="width:260px;">Azioni</th></tr></thead>
          <tbody>
          <?php if (!empty($catArticoli)): foreach ($catArticoli as $c): ?>
            <tr>
              <td><?= (int)$c['id'] ?></td>
              <td><?= e($c['nome']) ?></td>
              <td>
                <button class="btn btn-edit" data-entity="articoli" data-id="<?= (int)$c['id'] ?>" data-nome="<?= e($c['nome']) ?>">
                  <i class="fa-regular fa-pen-to-square"></i> Rinomina
                </button>
                <button class="btn danger btn-del" data-entity="articoli" data-id="<?= (int)$c['id'] ?>">
                  <i class="fa-regular fa-trash-can"></i> Elimina
                </button>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3" class="muted">Nessuna categoria.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Panel: Libri -->
    <div id="panel-libri" class="tab-panel">
      <h3 style="margin:14px 0 8px;">Categorie Libri</h3>
      <form class="form-grid cat-form" data-entity="libri" novalidate>
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="entity" value="libri">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="">
        <div>
       <label for="nome-libri">Nome categoria</label>
<input id="nome-libri" type="text" name="nome" placeholder="Es. Editing">

          <div class="muted"><small>Usate in <code>libri_categorie</code> e in alcune viste dei lavori.</small></div>
        </div>
        <div style="display:flex; gap:8px; align-items:end;">
          <button type="button" class="btn btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</button>
          <button type="submit" class="btn s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
        </div>
      </form>

      <div class="table-responsive" style="margin-top:12px;">
        <table class="table compact">
          <thead><tr><th style="width:80px;">ID</th><th>Nome</th><th style="width:260px;">Azioni</th></tr></thead>
          <tbody>
          <?php if (!empty($catLibri)): foreach ($catLibri as $c): ?>
            <tr>
              <td><?= (int)$c['id'] ?></td>
              <td><?= e($c['nome']) ?></td>
              <td>
                <button class="btn btn-edit" data-entity="libri" data-id="<?= (int)$c['id'] ?>" data-nome="<?= e($c['nome']) ?>">
                  <i class="fa-regular fa-pen-to-square"></i> Rinomina
                </button>
                <button class="btn danger btn-del" data-entity="libri" data-id="<?= (int)$c['id'] ?>">
                  <i class="fa-regular fa-trash-can"></i> Elimina
                </button>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3" class="muted">Nessuna categoria.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Panel: Lavori -->
    <div id="panel-lavoro" class="tab-panel">
      <h3 style="margin:14px 0 8px;">Categorie Lavori</h3>
      <form class="form-grid cat-form" data-entity="lavoro" novalidate>
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="entity" value="lavoro">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="">
        <div>
         <label for="nome-lavoro">Nome categoria</label>
<input id="nome-lavoro" type="text" name="nome" placeholder="Es. Revisione, Impaginazione…">

          <div class="muted"><small>Tabella dedicata (<code>categorie_lavoro</code>). L’eliminazione è bloccata se in uso.</small></div>
        </div>
        <div style="display:flex; gap:8px; align-items:end;">
          <button type="button" class="btn btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</button>
          <button type="submit" class="btn s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
        </div>
      </form>

      <div class="table-responsive" style="margin-top:12px;">
        <table class="table compact">
          <thead><tr><th style="width:80px;">ID</th><th>Nome</th><th style="width:260px;">Azioni</th></tr></thead>
          <tbody>
          <?php if (!empty($catLavoro)): foreach ($catLavoro as $c): ?>
            <tr>
              <td><?= (int)$c['id'] ?></td>
              <td><?= e($c['nome']) ?></td>
              <td>
                <button class="btn btn-edit" data-entity="lavoro" data-id="<?= (int)$c['id'] ?>" data-nome="<?= e($c['nome']) ?>">
                  <i class="fa-regular fa-pen-to-square"></i> Rinomina
                </button>
                <button class="btn danger btn-del" data-entity="lavoro" data-id="<?= (int)$c['id'] ?>">
                  <i class="fa-regular fa-trash-can"></i> Elimina
                </button>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3" class="muted">Nessuna categoria.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>
<?php include_once __DIR__ . '/../partials/footer.php'; ?>

<output id="toast" class="toast" aria-live="polite"></output>


<script>
  const toast = document.getElementById('toast');
  function showToast(msg, ok=true){
    toast.textContent = msg || (ok ? 'Operazione riuscita' : 'Errore');
    toast.style.background = ok ? '#065f46' : '#7f1d1d';
    toast.classList.add('show');
    setTimeout(()=> toast.classList.remove('show'), 2400);
  }

  // Tabs
  document.querySelectorAll('.tab-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.querySelectorAll('.tab-btn').forEach(b=> b.classList.remove('active'));
      btn.classList.add('active');
      const k = btn.dataset.tab;
      document.querySelectorAll('.tab-panel').forEach(p=> p.classList.remove('active'));
      document.getElementById('panel-'+k).classList.add('active');
    });
  });

  // Reset form helpers
  function resetForm(form){
    form.querySelector('input[name="id"]').value = '';
    const nome = form.querySelector('input[name="nome"]');
    nome.value = '';
    nome.classList.remove('invalid');
    nome.focus();
  }

  // Validazione minima client
  function validateForm(form){
    const nome = form.querySelector('input[name="nome"]');
    nome.classList.remove('invalid');
    if (!nome.value.trim()){
      nome.classList.add('invalid');
      return false;
    }
    return true;
  }

  // Submit AJAX per ogni form
  document.querySelectorAll('.cat-form').forEach(form=>{
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      if (!validateForm(form)) { showToast('Inserisci il nome della categoria.', false); return; }
      const fd = new FormData(form);
      try{
        const res = await fetch(location.href, { method:'POST', body: fd, credentials:'same-origin' });
        const out = await res.json().catch(()=>({success:false, msg:'Risposta non valida'}));
        if(!res.ok || !out.success) throw new Error(out.msg || 'Salvataggio non riuscito');
        showToast(out.msg || 'Salvato', true);
        setTimeout(()=> location.reload(), 700);
      }catch(err){
        console.error(err);
        showToast(err.message || 'Errore', false);
      }
    });

    form.querySelector('.btn-reset').addEventListener('click', ()=> resetForm(form));
  });

  // Click su "Rinomina"
  document.querySelectorAll('.btn-edit').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const entity = btn.dataset.entity;
      const id     = btn.dataset.id;
      const nome   = btn.dataset.nome;
      const form   = document.querySelector(`.cat-form[data-entity="${entity}"]`);
      form.querySelector('input[name="id"]').value   = id;
      form.querySelector('input[name="nome"]').value = nome;
      // attiva il tab corretto
      document.querySelector(`.tab-btn[data-tab="${entity}"]`).click();
      window.scrollTo({ top: form.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
      form.querySelector('input[name="nome"]').focus();
    });
  });

  // Delete
  document.querySelectorAll('.btn-del').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      if (!confirm('Confermi eliminazione categoria?')) return;
      const entity = btn.dataset.entity;
      const id     = btn.dataset.id;
      const fd = new FormData();
      fd.append('ajax','1');
      fd.append('entity', entity);
      fd.append('action', 'delete');
      fd.append('id', id);
      try{
        const res = await fetch(location.href, { method:'POST', body: fd, credentials:'same-origin' });
        const out = await res.json().catch(()=>({success:false, msg:'Risposta non valida'}));
        if(!res.ok || !out.success) throw new Error(out.msg || 'Eliminazione non riuscita');
        showToast(out.msg || 'Eliminata', true);
        setTimeout(()=> location.reload(), 700);
      }catch(err){
        console.error(err);
        showToast(err.message || 'Errore', false);
      }
    });
  });
</script>
</body>
</html>



