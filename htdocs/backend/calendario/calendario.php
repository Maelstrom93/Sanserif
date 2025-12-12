<?php
// file: backend/calendario/calendario.php
session_start();

require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
require_once __DIR__ . '/../assets/funzioni/funzioni_calendario.php';
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

requireLogin();

// ViewModel
requireLogin();

require_once __DIR__ . '/../assets/funzioni/funzioni_calendario.php';
require_once __DIR__ . '/../assets/funzioni/authz.php'; // <— aggiunto
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

requireLogin();

// === CAPABILITIES / SCOPE ===
$MY_ID = (int)($_SESSION['utente']['id'] ?? 0);
// admin (users.manage) o permesso specifico calendar.view_all vedono tutto
$CAN_VIEW_ALL_CAL = currentUserCan('users.manage') || currentUserCan('calendar.view_all');

// ViewModel (se non posso vedere tutto, filtro per me)
$vm = buildCalendarioViewModel($conn, [
  'assigned_user_id' => $CAN_VIEW_ALL_CAL ? null : $MY_ID
]);

$utenti      = $vm['utenti'];
$tipi_evento = $vm['tipi_evento'];
$eventi_json = $vm['eventi_json'];
$legend      = $vm['legend'];
$SCOPE_ASSIGNED_ONLY = !$CAN_VIEW_ALL_CAL;

// helper e() è in funzioni.php
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Calendario Editoriale</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

  <!-- stile base condiviso (contiene anche la sezione Calendario) -->
  <link rel="stylesheet" href="../assets/css/style1.css">
</head>
<body>

<main>
  <header class="topbar" style="margin-bottom:12px;">
    <div class="user-badge">
      <i class="fas fa-calendar icon-user"></i>
      <div>
        <div class="muted">Strumenti</div>
        <div style="font-weight:800; letter-spacing:.2px;">Calendario Editoriale</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>
    
    <div class="right">
        <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
<?php
  require_once __DIR__ . '/../assets/funzioni/authz.php';
  $CAN_CREATE_EVENT = currentUserCan('users.manage') || currentUserCan('calendar.create');
?>
<?php if ($CAN_CREATE_EVENT): ?>
  <button type="button" class="chip s-ok" id="btnNuovo"><i class="fa-solid fa-plus"></i> Nuovo evento</button>
<?php endif; ?>
         <?php include '../partials/navbar.php' ?>
      </div>
    </div>
  </header>

  <!-- Filtri -->
  <section class="panel compact" style="margin-bottom:12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <h2 style="margin:0; color:#004c60;"><i class="fa-solid fa-filter"></i> Filtri &amp; Vista</h2>
    </div>

    <div class="filters" style="margin-top:10px;">
      <div class="search" style="flex:1 1 320px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Cerca per titolo o assegnatario…">
      </div>

      <select id="tipoFiltro" class="chip" style="padding:6px 10px;">
        <option value="">Tutti i tipi</option>
        <?php foreach ($tipi_evento as $t): ?>
          <option value="<?= e($t) ?>"><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>

      <select id="viewSelect" class="chip" style="padding:6px 10px;">
        <option value="dayGridMonth">Vista mensile</option>
        <option value="timeGridWeek">Vista settimanale</option>
        <option value="listWeek">Elenco settimana</option>
      </select>
      <button type="button" class="chip" id="debugToggle" title="Mostra i dati inviati prima del salvataggio">Debug</button>
    </div>

    <div class="legend" style="margin-top:10px;">
      <?php foreach ($legend as $tipo => $colore): ?>
        <span class="chip"><span class="legend-color" style="background: <?= e($colore) ?>;"></span> <?= ucfirst($tipo) ?></span>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Calendario -->
  <section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <h2 style="margin:0; color:#004c60;"><i class="fa-regular fa-calendar"></i> Calendario</h2>
      <div class="muted">Pianifica, assegna e visualizza gli impegni</div>
      <?= $SCOPE_ASSIGNED_ONLY ? ' — <strong>Vista: solo i miei eventi</strong>' : '' ?>
    </div>
    <div id="calendar" style="margin-top:12px;"></div>
  </section>
</main>

<!-- ===== MODALE ===== -->
<div id="eventModal" class="modal" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="m-header">
      <h2 id="modalTitle" class="m-title"><i class="fa-regular fa-pen-to-square"></i> Nuovo Evento</h2>
      <button type="button" class="chip" data-modal-close><i class="fa-solid fa-xmark"></i> Chiudi</button>
    </div>
    <div class="m-body">
      <form id="eventForm">
        <input type="hidden" name="id" id="eventId">

        <label for="nome">Nome evento</label>
        <input type="text" name="nome" id="nome" placeholder="Nome evento" required>

        <label for="data_evento">Data</label>
        <input type="date" name="data_evento" id="data_evento" required>

        <!-- ===== ASSEGNATO A (utenti + testo libero) ===== -->
        <input type="hidden" name="assegnato_a" id="assegnato_a">
        <label for="assegnato_sel">Assegnato a</label>
        <select id="assegnato_sel">
          <option value="">— Nessuno —</option>
          <?php foreach ($utenti as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= e($u['label']) ?></option>
          <?php endforeach; ?>
          <option value="__custom__">— Altro (testo libero) —</option>
        </select>
        <div id="assegnato_custom_wrap" style="display:none; margin-top:6px;">
          <input type="text" id="assegnato_custom" placeholder="Nome, email o ID…">
          <small class="muted">Se compilato, verrà salvato come valore libero.</small>
        </div>
        <!-- =============================================== -->

        <label for="tipo_evento">Tipo</label>
        <select name="tipo" id="tipo_evento">
          <?php foreach ($tipi_evento as $t): ?>
            <option value="<?= e($t) ?>"><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
          <option value="__new__">— Nuova tipologia —</option>
        </select>

        <div id="newTypeWrap" style="margin-top:8px; display:none;">
          <label for="tipo_new">Nuova tipologia</label>
          <input type="text" name="tipo_new" id="tipo_new" placeholder="Es. scadenza">

          <label for="tipo_new_color" style="margin-top:6px;">Colore tipologia</label>
          <input type="color" name="tipo_new_color" id="tipo_new_color" value="#EF4444">
          <small class="muted">Se compili questi campi, verrà usata la tipologia inserita con il colore indicato.</small>
        </div>

        <!-- override colore -->
        <input type="hidden" name="color_override" id="color_override" value="0">

        <label for="colore">Colore evento (opzionale)</label>
        <input type="color" name="colore" id="colore" value="#004c60">

        <label for="descrizione">Descrizione</label>
        <textarea name="descrizione" id="descrizione" rows="3" placeholder="Descrizione sintetica…"></textarea>

        <label for="note">Note interne</label>
        <textarea name="note" id="note" rows="3" placeholder="Note interne…"></textarea>

        <div class="actions">
          <button type="button" class="chip" data-modal-close>Annulla</button>
          <button type="submit" class="chip s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
          <button type="button" class="chip" id="deleteBtn" style="background:#fee2e2; border-color:#fecaca; color:#991b1b; display:none;">
            <i class="fa-regular fa-trash-can"></i> Elimina
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

  <?php include '../partials/footer.php' ?>

<!-- ===== Dati per JS (visibili al client) ===== -->
<?php
  // già incluso in patch precedente
  // require_once __DIR__ . '/../assets/funzioni/authz.php';
  $IS_ADMIN = currentUserCan('users.manage'); // o una tua capability tipo calendar.edit_any
?>
<script>
  window.__CAL_DATA = {
    eventi: <?= json_encode($eventi_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    tipoColorMap: <?= json_encode($legend, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    canCreate: <?= json_encode($CAN_CREATE_EVENT ?? false) ?>,
    isAdmin: <?= json_encode($IS_ADMIN) ?>
  };
</script>


<!-- JS condiviso (dropdown/menu ecc.) -->
<script src="../assets/javascript/main.js"></script>
<!-- JS della pagina Calendario -->
<script src="../assets/javascript/calendario.js"></script>
</body>
</html>
