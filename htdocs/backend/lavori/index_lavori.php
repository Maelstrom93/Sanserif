<?php
// file: lavori/index_lavori.php
session_start();
require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/csrf.php';
require_once '../assets/funzioni/funzioni_lavoro.php';
require_once '../assets/funzioni/authz.php';

requireLogin();
if (!currentUserCan('lavori.view_all') && !currentUserCan('lavori.view_assigned_only')) {
  http_response_code(403);
  exit('403 Permesso negato');
}
$csrf = csrf_token();
// === CAPABILITIES & SCOPE =====================================================
$IS_ADMIN       = currentUserCan('users.manage');          // admin = livello avanzato
$CAN_VIEW_ALL   = currentUserCan('lavori.view_all');        // di fatto solo admin nel nuovo schema
$CAN_EDIT_SOFT  = currentUserCan('lavori.edit_soft');
$CAN_EDIT_HARD  = currentUserCan('lavori.edit_hard');
$CAN_DELETE     = currentUserCan('lavori.delete');
$CAN_CREATE     = currentUserCan('lavori.create');
$CAN_SEE_PRICE  = $IS_ADMIN;                                // prezzi visibili solo agli admin

$MY_ID = (int)($_SESSION['utente']['id'] ?? 0);

// Se non posso vedere tutto, forza "solo i miei"
if (!$CAN_VIEW_ALL) {
  // Spingi il filtro lato pagina (prima di calcolare le liste)
  $_GET['assigned_to_me'] = 1;
  $_GET['utente_id']      = $MY_ID ?: null; // opzionale, dipende da parseFiltri()
}

list($clienti, $utenti, $categorie) = getSelectLists($conn);
$F = parseFiltri($_SESSION, $_GET);
$D = getListeLavori($conn, $F);

extract($F); // myId, qTitolo, ...
extract($D); // tutti, ultimi, errSql, cntAll, ...

// Prepara dati JS lato PHP (richiede buildJsGlobals in funzioni_lavoro.php)
list($__UTENTI_JS, $__CATEGORIE_JS) = buildJsGlobals($utenti, $categorie);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Gestione Lavori</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
<style>
  /* Nasconde elementi marcati come prezzo quando l'utente non è admin */
/* Nasconde elementi marcati come prezzo quando l'utente non è admin */
.no-price .js-price { display: none !important; }

/* Nascondi campo prezzo nella modale "Modifica" per non-admin */
.no-price .row-prezzo,
.no-price label[for="edit-prezzo"],
.no-price #edit-prezzo { display: none !important; }

/* Base/Intermedio: modale "Modifica" ridotta alla sola tab Lavorazioni */
.readonly-jobform #tabbtn-base,
.readonly-jobform #tabbtn-altro,
.readonly-jobform #tab-base,
.readonly-jobform #tab-altro,
.readonly-jobform #formModificaLavoro .actions { display: none !important; }

/* Disabilita inputs non attinenti alle lavorazioni in caso di read-only */
.readonly-jobform #tab-base input,
.readonly-jobform #tab-base select,
.readonly-jobform #tab-base textarea,
/* Solo checklist (niente editor dettagli righe) per base/intermedio */
.readonly-jobform #attivita-list { display: none !important; }
/* per sicurezza disabilita anche il tasto Agg. lavorazione se comparisse */
.readonly-jobform #addAttivita { display: none !important; }

</style>

</head>
<body class="<?= ($CAN_SEE_PRICE ? '' : 'no-price') . ((!$CAN_EDIT_SOFT && !$CAN_EDIT_HARD) ? ' readonly-jobform' : '') ?>">

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
<a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
<?php if (currentUserCan('lavori.create')): ?>
  <a href="nuovo_lavoro.php" class="chip s-ok"><i class="fas fa-plus"></i> Nuovo Lavoro</a>
<?php endif; ?>
         <?php 
include '../partials/navbar.php'?>
        </div>
      

  </header>

  <?php if ($errSql): ?>
    <section class="panel s-err" style="margin-bottom:12px; border-color:#f8caca; background:#fde4e4; color:#7a1f1f;">
      <strong>Errore:</strong> <?= e($errSql) ?>
    </section>
  <?php endif; ?>

  <div id="liveRegion" aria-live="polite" class="sr-only"></div>
  <div id="toasts" aria-live="polite" aria-atomic="true"></div>

  <!-- === FILTRI === -->
  <section class="panel" style="margin-bottom:12px;">
    <form class="filters" method="get" action="index_lavori.php" id="formFiltri">
      <div>
        <label>Titolo</label>
        <input type="text" name="q" value="<?= e($qTitolo) ?>" placeholder="Cerca per titolo">
      </div>
      <div>
        <label>Utente</label>
        <select name="utente_id">
          <option value="">— Tutti —</option>
          <?php foreach ($utenti as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($fUtente!==null && $fUtente==(int)$u['id'])?'selected':'' ?>><?= e($u['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Cliente</label>
        <select name="cliente_id">
          <option value="">— Tutti —</option>
          <?php foreach ($clienti as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($fCliente!==null && $fCliente==(int)$c['id'])?'selected':'' ?>><?= e($c['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Lavorazione da svolgere</label>
        <select name="categoria_id">
          <option value="">— Tutte —</option>
          <?php foreach ($categorie as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= ($fCat!==null && $fCat==(int)$cat['id'])?'selected':'' ?>>
              <?= e($cat['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Scadenza da</label>
        <input type="date" name="scad_da" value="<?= e($fDa) ?>">
      </div>
      <div>
        <label>Scadenza a</label>
        <input type="date" name="scad_a" value="<?= e($fA) ?>">
      </div>
      <div>
        <label>Ordina per</label>
        <select name="sort">
          <option value="ricezione_desc" <?= $sort==='ricezione_desc'?'selected':'' ?>>Ricezione (recenti)</option>
          <option value="scadenza_asc"   <?= $sort==='scadenza_asc'?'selected':'' ?>>Scadenza (prossime)</option>
          <option value="prezzo_desc"    <?= $sort==='prezzo_desc'?'selected':'' ?>>Prezzo (alto→basso)</option>
          <option value="titolo_asc"     <?= $sort==='titolo_asc'?'selected':'' ?>>Titolo (A→Z)</option>
        </select>
      </div>

      <input type="hidden" name="page" value="<?= (int)$page ?>">
      <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">

      <div class="col-span-7" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
        <button class="chip" type="submit"><i class="fa-solid fa-filter"></i> Filtra</button>
        <a class="chip" href="index_lavori.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
        <?php if ($myId): ?>
          <a class="chip s-ok" href="<?= e(buildUrl(['assigned_to_me'=>1])) ?>"><i class="fa-solid fa-user-check"></i> Solo i miei</a>
        <?php endif; ?>
        <button class="chip" id="saveDefaultFilters" type="button"><i class="fa-regular fa-bookmark"></i> Salva come default</button>
      </div>
    </form>

    <!-- CHIP BAR rapida -->
    <div class="chipbar">
      <?php
        $urlAll     = buildUrl(['stato'=>null]);
        $urlClosed  = buildUrl(['stato'=>'chiusi']);
        $urlMine    = buildUrl(['assigned_to_me'=>1]);
        $isAll      = !$onlyClosed;
        $isClosed   =  $onlyClosed;
      ?>
      <a class="chip pill <?= $isAll ? 'active' : '' ?>" href="<?= e($urlAll) ?>">
        <i class="fa-solid fa-layer-group"></i> Tutti
        <span class="count"><?= (int)$cntAll ?></span>
      </a>
      <a class="chip pill <?= $isClosed ? 'active' : '' ?>" href="<?= e($urlClosed) ?>">
        <i class="fa-solid fa-circle-check"></i> Completati
        <span class="count"><?= (int)$cntClosed ?></span>
      </a>
      <a class="chip pill" href="<?= e($urlMine) ?>">
        <i class="fa-regular fa-user"></i> Solo i miei
        <span class="count"><?= (int)$cntMine ?></span>
      </a>
    </div>
  </section>

  <!-- Ultimi -->
  <section class="panel" style="margin-bottom:12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <?php $ultimiLabel = ($onlyClosed ? 'Primi 3 completati (filtrati)' : ($qTitolo||$fCliente||$fCat||$fDa||$fA||$assignedToMe ? 'Primi 3 risultati (filtrati)' : 'Gli ultimi 3 caricamenti')); ?>
      <h2 style="margin:0; color:#004c60;"><i class="fa-solid fa-star"></i> <?= e($ultimiLabel) ?></h2>
      <div class="muted hide-sm"><i class="fa-regular fa-clock"></i> <?= $onlyClosed?'Vista “Completati”':(($qTitolo||$fCliente||$fCat||$fDa||$fA||$assignedToMe)?'Vista filtrata':'Gli ultimi 3 inserimenti') ?></div>
    </div>

    <div class="jobs-row" style="margin-top:12px;">
      <?php if ($ultimi): while ($r = $ultimi->fetch_assoc()):
        $id      = (int)$r['id'];
        $titolo  = (string)($r['titolo'] ?? '');
        $cliente = (string)($r['cliente_nome'] ?? '');
        $stato   = (string)($r['stato'] ?? '');
        $cats    = (string)($r['categorie'] ?? '');
        $ricev   = (string)($r['data_ricezione'] ?? '');
        $scadRaw = (string)($r['scadenza'] ?? '');
        $scad    = $scadRaw ? formattaData($scadRaw) : '';
        $prz     = ($r['prezzo'] !== null && $r['prezzo'] !== '') ? (float)$r['prezzo'] : null;
        $sumRig  = ($r['sum_righe'] !== null && $r['sum_righe'] !== '') ? (float)$r['sum_righe'] : null;
        $attC    = (int)($r['att_count'] ?? 0);
        $assCnt  = (int)($r['assignees'] ?? 0);
        $totShow = $prz !== null ? $prz : $sumRig;

        $attDone = (int)($r['att_done'] ?? 0);
        $pct     = $attC > 0 ? (int)round(100 * $attDone / $attC) : 0;
        $cls     = done_class_by_pct($pct);
      ?>
        <article
          class="job-card"
          data-scadenza="<?= e($scadRaw) ?>"
          data-stato="<?= e(strtolower($stato ?: 'aperto')) ?>">
          <div class="head">
            <h4 class="title"><?= e($titolo) ?></h4>
            <div style="display:flex; gap:6px; align-items:center;">
<span class="prio <?= e(prio_label($r['priorita'] ?? null)) ?>">
  <span class="prio-bars" aria-hidden="true">
    <span></span><span></span><span></span>
  </span>
  <span class="prio-label">
    <?= e(prio_label($r['priorita'] ?? null)) ?>
  </span>
</span>
              <span class="badge state-<?= e($stato ?: 'aperto') ?>"><?= e($stato ?: 'aperto') ?></span>
            </div>
          </div>
          <div class="body">
            <div class="meta">
              <?php if ($cliente): ?><i class="fa-regular fa-user"></i> <?= e($cliente) ?> &nbsp;&middot;&nbsp;<?php endif; ?>
              <?php if ($cats): ?><i class="fa-solid fa-tags"></i> <?= e($cats) ?><?php endif; ?>
            </div>
            <div class="meta">
              <?php if ($ricev): ?>  <i class="fa-solid fa-inbox"></i> Ricevuto: <?= e(formattaData($ricev)) ?><?php endif; ?>
              <?php if ($scad): ?> &nbsp;&middot;&nbsp; <i class="fa-solid fa-calendar-days"></i> Scadenza: <?= e($scad) ?><?php endif; ?>
<?php if ($CAN_SEE_PRICE && $totShow !== null): ?>
  &nbsp;&middot;&nbsp; <i class="fa-solid fa-euro-sign"></i> <?= e(money_it($totShow)) ?>
<?php endif; ?>
            </div>
            <div class="meta">
              <i class="fa-solid fa-list-check"></i> <?= $attC ?> Da svolgere
              &nbsp;&middot;&nbsp; <i class="fa-regular fa-user"></i> <?= $assCnt ?> assegnatari
              &nbsp;&middot;&nbsp;
              <span class="done-wrap" title="Lavorazioni completate">
                <span class="pill-done <?= e($cls) ?>" aria-label="Lavorazioni completate">
                  <i class="fa-regular fa-square-check"></i> <?= $attDone ?>/<?= $attC ?>
                </span>
                <span class="donebar" role="progressbar" aria-label="Completamento lavorazioni" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$pct ?>">
                  <span class="fill <?= e($cls) ?>" style="width: <?= (int)$pct ?>%;"></span>
                </span>
              </span>
              <span class="sla chip sm" aria-label="SLA scadenza"></span>
            </div>
     <div class="actions">
  <a class="chip open-view" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-eye"></i> Dettagli</a>

 <?php if ($CAN_EDIT_SOFT || $CAN_EDIT_HARD || currentUserCan('attivita.complete_any') || currentUserCan('attivita.complete_own')): ?>
  <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-pen-to-square"></i> Modifica</a>
<?php endif; ?>


  <?php if ($CAN_DELETE): ?>
    <button class="chip danger js-del-job" type="button" data-id="<?= $id ?>" data-title="<?= e($titolo) ?>">
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
      <div class="right" style="display:flex; gap:8px; align-items:center;">
        <button class="chip" id="toggleView" type="button" aria-pressed="false" aria-label="Cambia vista"><i class="fa-solid fa-table-list"></i> Vista tabella</button>
        <div class="muted">Elenco completo<?= $onlyClosed ? ' — filtro: Completati (ignora assegnatari)' : ($assignedToMe ? ' — filtro: Solo i miei' : '') ?></div>
      </div>
    </div>

    <!-- VISTA CARDS -->
    <div class="jobs-row" id="allJobs" style="margin-top:12px;">
      <?php
        $rowsAll = [];
        if ($tutti) {
          while ($r = $tutti->fetch_assoc()):
            $rowsAll[] = $r;
            $id      = (int)$r['id'];
            $titolo  = (string)($r['titolo'] ?? '');
            $cliente = (string)($r['cliente_nome'] ?? '');
            $stato   = (string)($r['stato'] ?? '');
            $scadRaw = (string)($r['scadenza'] ?? '');
            $scad    = $scadRaw ? formattaData($scadRaw) : '';
            $prz     = ($r['prezzo'] !== null && $r['prezzo'] !== '') ? (float)$r['prezzo'] : null;
            $sumRig  = ($r['sum_righe'] !== null && $r['sum_righe'] !== '') ? (float)$r['sum_righe'] : null;
            $attC    = (int)($r['att_count'] ?? 0);
            $assCnt  = (int)($r['assignees'] ?? 0);
            $totShow = $prz !== null ? $prz : $sumRig;

            $ricev   = (string)($r['data_ricezione'] ?? '');
            $cats    = (string)($r['categorie'] ?? '');

            $attDone = (int)($r['att_done'] ?? 0);
            $pct     = $attC > 0 ? (int)round(100 * $attDone / $attC) : 0;
            $cls     = done_class_by_pct($pct);
      ?>
        <article
          class="job-card"
          data-scadenza="<?= e($scadRaw) ?>"
          data-stato="<?= e(strtolower($stato ?: 'aperto')) ?>">
          <div class="head">
            <h4 class="title"><?= e($titolo) ?></h4>
            <div style="display:flex; gap:6px; align-items:center;">
<span class="prio <?= e(prio_label($r['priorita'] ?? null)) ?>">
  <span class="prio-bars" aria-hidden="true">
    <span></span><span></span><span></span>
  </span>
  <span class="prio-label">
    <?= e(prio_label($r['priorita'] ?? null)) ?>
  </span>
</span>             
 <span class="badge state-<?= e($stato ?: 'aperto') ?>"><?= e($stato ?: 'aperto') ?></span>
            </div>
          </div>
          <div class="body">
            <div class="meta">
              <?php if ($cliente): ?><i class="fa-regular fa-user"></i> <?= e($cliente) ?> &nbsp;&middot;&nbsp;<?php endif; ?>
              <?php if ($cats): ?><i class="fa-solid fa-tags"></i> <?= e($cats) ?><?php endif; ?>
            </div>
            <div class="meta">
           <?php if ($ricev): ?> <i class="fa-solid fa-inbox"></i> Ricevuto: <?= e(formattaData($ricev)) ?><?php endif; ?>
<?php if ($scad): ?> &nbsp;&middot;&nbsp; <i class="fa-solid fa-calendar-days"></i> Scadenza: <?= e($scad) ?><?php endif; ?>
<?php if ($CAN_SEE_PRICE && $totShow !== null): ?>
  &nbsp;&middot;&nbsp; <i class="fa-solid fa-euro-sign"></i> <?= e(money_it($totShow)) ?>
<?php endif; ?>
 </div>
            <div class="meta">
              <i class="fa-solid fa-list-check"></i> <?= $attC ?> Lavorazioni
              &nbsp;&middot;&nbsp; <i class="fa-regular fa-user"></i> <?= $assCnt ?> assegnatari
              &nbsp;&middot;&nbsp;
              <span class="done-wrap" title="Lavorazioni completate">
                <span class="pill-done <?= e($cls) ?>">
                  <i class="fa-regular fa-square-check"></i> <?= $attDone ?>/<?= $attC ?>
                </span>
                <span class="donebar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$pct ?>">
                  <span class="fill <?= e($cls) ?>" style="width: <?= (int)$pct ?>%;"></span>
                </span>
              </span>
              <span class="sla chip sm" aria-label="SLA scadenza"></span>
            </div>
           <div class="actions">
  <a class="chip open-view" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-eye"></i> Dettagli</a>

<?php if ($CAN_EDIT_SOFT || $CAN_EDIT_HARD || currentUserCan('attivita.complete_any') || currentUserCan('attivita.complete_own')): ?>
  <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-pen-to-square"></i> Modifica</a>
<?php endif; ?>

  <?php if ($CAN_DELETE): ?>
    <button class="chip danger js-del-job" type="button" data-id="<?= $id ?>" data-title="<?= e($titolo) ?>">
      <i class="fa-regular fa-trash-can"></i> Elimina
    </button>
  <?php endif; ?>
</div>

          </div>
        </article>
      <?php
          endwhile;
        }
      ?>
    </div>

    <!-- VISTA TABELLA -->
    <div id="tableJobs" class="xfade" style="display:none; margin-top:12px; overflow:auto;">
      <table class="jobs-table">
        <thead>
          <tr>
            <th>Titolo</th>
            <th>Cliente</th>
            <th>Stato</th>
            <th>Scadenza</th>
            <th>Totale</th>
            <th>Lavorazioni</th>
            <th>Ass.</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($rowsAll)): ?>
            <?php foreach ($rowsAll as $r):
              $id      = (int)$r['id'];
              $titolo  = (string)($r['titolo'] ?? '');
              $cliente = (string)($r['cliente_nome'] ?? '');
              $stato   = (string)($r['stato'] ?? '');
              $scadRaw = (string)($r['scadenza'] ?? '');
              $scad    = $scadRaw ? formattaData($scadRaw) : '';
              $prz     = ($r['prezzo'] !== null && $r['prezzo'] !== '') ? (float)$r['prezzo'] : null;
              $sumRig  = ($r['sum_righe'] !== null && $r['sum_righe'] !== '') ? (float)$r['sum_righe'] : null;
              $attC    = (int)($r['att_count'] ?? 0);
              $assCnt  = (int)($r['assignees'] ?? 0);
              $totShow = $prz !== null ? $prz : $sumRig;

              $attDone = (int)($r['att_done'] ?? 0);
              $pct     = $attC > 0 ? (int)round(100 * $attDone / $attC) : 0;
              $cls     = done_class_by_pct($pct);
            ?>
              <tr>
                <td><strong><?= e($titolo) ?></strong></td>
                <td><?= e($cliente) ?></td>
                <td>
                  <span class="badge state-<?= e($stato ?: 'aperto') ?>"><?= e($stato ?: 'aperto') ?></span>
<span class="prio <?= e(prio_label($r['priorita'] ?? null)) ?>">
  <span class="prio-bars" aria-hidden="true">
    <span></span><span></span><span></span>
  </span>
  <span class="prio-label">
    <?= e(prio_label($r['priorita'] ?? null)) ?>
  </span>
</span>                 </td>
                <td><?= $scad ? e($scad) : '—' ?></td>
<td><?= ($CAN_SEE_PRICE && $totShow !== null) ? e(money_it($totShow)) : '—' ?></td>
                <td>
                  <?= $attC ?>
                  <div class="done-wrap" style="margin-top:4px;">
                    <span class="pill-done <?= e($cls) ?>">
                      <i class="fa-regular fa-square-check"></i> <?= $attDone ?>/<?= $attC ?>
                    </span>
                    <span class="donebar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$pct ?>">
                      <span class="fill <?= e($cls) ?>" style="width: <?= (int)$pct ?>%;"></span>
                    </span>
                  </div>
                </td>
                <td><?= $assCnt ?></td>
              <td>
  <a class="chip open-view" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-eye"></i> Dettagli</a>
  <?php if ($CAN_EDIT_SOFT || $CAN_EDIT_HARD): ?>
    <a class="chip open-edit" href="#" data-id="<?= $id ?>"><i class="fa-regular fa-pen-to-square"></i> Modifica</a>
  <?php endif; ?>
  <?php if ($CAN_DELETE): ?>
    <button class="chip danger js-del-job" type="button" data-id="<?= $id ?>" data-title="<?= e($titolo) ?>">
      <i class="fa-regular fa-trash-can"></i> Elimina
    </button>
  <?php endif; ?>
</td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<!-- ===== MODALE VIEW (senza tab checklist) ===== -->
<div id="modale-view" class="modal" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalViewTitolo">
    <div class="m-header">
      <h2 id="modalViewTitolo" class="m-title">
        <i class="fa-regular fa-eye"></i>
        Dettaglio lavoro
      </h2>
      <div style="display:flex; gap:8px; align-items:center;">
        <span id="mv-badge-stato" class="badge"></span>
        <button type="button" class="chip" data-view-close><i class="fa-solid fa-xmark"></i> Chiudi</button>
      </div>
    </div>
    <div class="m-body">
      <div id="v-meta" class="meta" style="margin-bottom:8px"></div>

      <div class="tabs" role="tablist" aria-label="Sezioni dettaglio">
        <button class="tab-btn" role="tab" aria-selected="true" aria-controls="tab-overview" id="tabbtn-overview">Panoramica</button>
        <!-- RINOMINATA: Attività -> Lavorazioni (ID invariato per compatibilità JS) -->
        <button class="tab-btn" role="tab" aria-selected="false" aria-controls="tab-attivita" id="tabbtn-attivita">Lavorazioni</button>
       <div id="v-categorie" class="meta" style="display:none"></div>
        <!-- <button class="tab-btn" role="tab" aria-selected="false" aria-controls="tab-categorie" id="tabbtn-categorie">Categorie</button> -->
        <button class="tab-btn" role="tab" aria-selected="false" aria-controls="tab-descr" id="tabbtn-descr">Descrizione</button>
      </div>

      <div id="tab-overview" class="tab-panel active" role="tabpanel" aria-labelledby="tabbtn-overview">
        <div class="mini-card" id="v-overview"></div>
      </div>

      <!-- Pannello Lavorazioni (ex lavorazioni) -->
      <div id="tab-attivita" class="tab-panel" role="tabpanel" aria-labelledby="tabbtn-attivita">
        <div class="timeline" id="v-attivita"></div>
      </div>

      <!-- RIMOSSO pannello categorie (è già presente per-lavorazione) -->
      <!--
      <div id="tab-categorie" class="tab-panel" role="tabpanel" aria-labelledby="tabbtn-categorie">
        <div id="v-categorie" class="meta" ></div>
      </div>
      -->

      <div id="tab-descr" class="tab-panel" role="tabpanel" aria-labelledby="tabbtn-descr">
        <div id="v-desc" class="meta"></div>
      </div>
    </div>
  </div>
</div>

<!-- ===== MODALE MODIFICA (tolta tab Checklist) ===== -->
<?php $canHard = currentUserCan('lavori.edit_hard'); ?>
<?php $canSoft = $canHard || currentUserCan('lavori.edit_soft'); ?>
<div id="modale-lavoro" class="modal" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalEditTitolo">
    <div class="m-header">
      <h2 id="modalEditTitolo" class="m-title">
        <i class="fa-regular fa-pen-to-square"></i>
        Modifica lavoro
      </h2>
      <button type="button" class="chip" data-modal-close><i class="fa-solid fa-xmark"></i> Chiudi</button>
    </div>

    <div class="m-body">
      <div class="tabs" role="tablist" aria-label="Sezioni modifica">
        <button class="tab-btn" role="tab" aria-selected="true"  aria-controls="tab-base"           id="tabbtn-base">Dettagli base</button>
        <button class="tab-btn" role="tab" aria-selected="false" aria-controls="tab-attivita-edit"  id="tabbtn-attivita-edit">Lavorazioni</button>
        <button class="tab-btn" role="tab" aria-selected="false" aria-controls="tab-altro"          id="tabbtn-altro">Altro</button>
      </div>

      <form id="formModificaLavoro" action="../api/modifica_lavoro.php" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="edit-id" value="">

        <!-- TAB: Dettagli base -->
        <div id="tab-base" class="tab-panel active" role="tabpanel" aria-labelledby="tabbtn-base">
          <div class="form-grid form-group">
            <div class="form-group">
              <label for="edit-titolo">Titolo</label>
              <input type="text" name="titolo" id="edit-titolo" required>
            </div>
            <div>
              <label for="edit-cliente">Cliente</label>
              <select name="cliente_id" id="edit-cliente" required>
                <option value="">— Seleziona cliente —</option>
                <?php foreach($clienti as $cl): ?>
                  <option value="<?= (int)$cl['id'] ?>"><?= e($cl['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="edit-assegnato_a">Assegnato a (macro)</label>
              <select name="assegnato_a" id="edit-assegnato_a">
                <option value="">— Nessuno —</option>
                <?php foreach($utenti as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-grid form-group">
            <div>
              <label for="edit-data_ricezione">Ricevuto il</label>
              <input type="date" name="data_ricezione" id="edit-data_ricezione">
            </div>
            <div>
              <label for="edit-scadenza">Scadenza</label>
<input type="date" name="scadenza" id="edit-scadenza" <?= $canSoft ? '' : 'disabled' ?>>
            </div>
          </div>

          <div class="form-grid form-group">
            <div>
              <label for="edit-stato">Stato</label>
             <input type="hidden" name="stato_explicit" id="stato_explicit" value="0" <?= $canSoft ? '' : 'disabled' ?>>
<select name="stato" id="edit-stato" onchange="document.getElementById('stato_explicit').value='1'">
                <option value="aperto">Aperto</option>
                <option value="in_lavorazione">In lavorazione</option>
                <option value="pausa">In pausa</option>
                <option value="completato">Completato</option>
                <option value="chiuso">Chiuso</option>
                <option value="annullato">Annullato</option>
              </select>
            </div>
          <div class="row-prezzo">
  <label for="edit-prezzo">Prezzo (EUR)</label>
  <input type="number" step="0.01" min="0" name="prezzo" id="edit-prezzo" <?= $CAN_SEE_PRICE ? '' : 'disabled' ?>>
</div>

            <div>
              <label for="edit-priorita">Priorità</label>
              <select name="priorita" id="edit-priorita">
                <option value="bassa">Bassa</option>
                <option value="media" selected>Media</option>
                <option value="alta">Alta</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="edit-provenienza">Provenienza</label>
            <input type="text" name="provenienza" id="edit-provenienza">
          </div>

          <div class="form-group">
            <label for="edit-descrizione">Descrizione</label>
            <textarea name="descrizione" id="edit-descrizione"></textarea>
          </div>
        </div>

        <!-- TAB: Attività -->
        <div id="tab-attivita-edit" class="tab-panel" role="tabpanel" aria-labelledby="tabbtn-attivita-edit">
          <div class="form-group" style="margin-bottom:12px;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
              <h3 style="margin:0; font-size:16px; color:#004c60; display:flex; align-items:center; gap:8px;">
                <i class="fa-regular fa-square-check"></i> Stato completamento (toggle rapido)
              </h3>
              <div class="chk-progress" id="attivita-progress"></div>
            </div>
            <div class="chk" id="attivita-complete-list" style="margin-top:8px;"></div>
          </div>

          <div id="attivita-list" class="timeline"></div>
<?php if ($CAN_EDIT_SOFT || $CAN_EDIT_HARD): ?>
  <button type="button" class="chip" id="addAttivita" style="margin-top:10px"><i class="fa-solid fa-plus"></i> Aggiungi Lavorazione</button>
<?php endif; ?>
          <!-- opzionale: bottone per generare righe_json subito -->
          <!-- <button type="button" class="chip" id="salvaAttivita"><i class="fa-regular fa-floppy-disk"></i> Prepara Lavorazione</button> -->
        </div>

        <!-- TAB: Altro -->
        <div id="tab-altro" class="tab-panel" role="tabpanel" aria-labelledby="tabbtn-altro">
          <div class="form-group">
            <label>Note interne</label>
            <textarea name="note_interne"></textarea>
          </div>
        </div>

        <div class="actions form-actions-sticky">
          <button type="button" class="chip" data-modal-close>Annulla</button>
          <button type="submit" class="chip s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva lavoro</button>
        </div>
      </form>
    </div>
  </div>
</div>

</div>
<script>
  window.__MY_ID               = <?= (int)($MY_ID) ?>;
  window.__CAN_COMPLETE_ANY    = <?= json_encode(currentUserCan('attivita.complete_any')) ?>;
  window.__CAN_COMPLETE_OWN    = <?= json_encode(currentUserCan('attivita.complete_own')) ?>;
  // Suggerimento per lavori.js:
  // - abilita il toggle "completa attività" sempre se __CAN_COMPLETE_ANY
  // - altrimenti abilitalo SOLO se (attivita.utente_id === __MY_ID && __CAN_COMPLETE_OWN)
   window.__CAN_SEE_PRICE      = <?= json_encode($CAN_SEE_PRICE) ?>;
  window.__READONLY_JOBFORM   = <?= json_encode(!$CAN_EDIT_SOFT && !$CAN_EDIT_HARD) ?>;
</script>
<script>
/**
 * Blocca/abilita il toggle di completamento attività per base/intermedio:
 * - se __CAN_COMPLETE_ANY: può togglare tutto
 * - altrimenti se __CAN_COMPLETE_OWN: può togglare solo le attività con assegnatario == __MY_ID
 * - altrimenti: niente toggle
 *
 * NB: presuppone che ogni riga attività abbia data-attribs:
 *   data-attivita-id, data-assegnatario-id
 * Se non ci sono, basta aggiungerli in lavori.js quando renderizza.
 */
(function(){
  function canToggle(el){
    if (window.__CAN_COMPLETE_ANY) return true;
    if (!window.__CAN_COMPLETE_OWN) return false;
    const row = el.closest('[data-attivita-id]');
    if (!row) return false;
    const uid = parseInt(row.getAttribute('data-assegnatario-id') || '0', 10);
    return uid === window.__MY_ID;
  }

  // Delegation per qualsiasi checkbox/switch di completamento attività
  document.addEventListener('change', function(ev){
    const t = ev.target;
if (!t.matches('.js-att-toggle, .js-att-complete, input[type="checkbox"].att-complete, .js-compl')) return;
    if (canToggle(t)) return; // ok
    // se NON può, ripristina stato e avvisa
    ev.preventDefault();
    t.checked = !t.checked;
    alert('Non puoi modificare questa lavorazione: non è assegnata a te.');
  }, true);

  // In più, appena renderizzate le attività, disabilita i toggle non permessi
  function disableForbiddenToggles(scope){
(scope || document).querySelectorAll('.js-att-toggle, .js-att-complete, input[type="checkbox"].att-complete, .js-compl').forEach(cb=>{
      cb.disabled = !canToggle(cb);
    });
  }

  // Quando apro “Dettagli”, dopo che lavori.js ha riempito la lista:
  document.addEventListener('click', function(ev){
    const a = ev.target.closest('.open-view');
    if (!a) return;
    setTimeout(function wait(){
      const list = document.getElementById('v-attivita');
      if (list && list.children.length){
        disableForbiddenToggles(list);
      } else {
        setTimeout(wait, 80);
      }
    }, 150);
  }, true);
})();
</script>

<!-- Variabili globali per main.js -->
<script>
  window.__UTENTI = <?= json_encode($__UTENTI_JS, JSON_UNESCAPED_UNICODE) ?>;
  window.__CATEGORIE = <?= json_encode($__CATEGORIE_JS, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
(function(){
  // Dopo che la modale "view" viene popolata da lavori.js,
  // marchiamo le porzioni di testo che rappresentano il prezzo
  // (assumendo che ci sia il simbolo € o 'EUR' vicino al numero).
  function markPrices(container){
    if (!container) return;
    // Semplice marking: wrappa pattern € 1.234,56 o "EUR 1234.56"
    const rx = /(€\s?\d{1,3}(\.\d{3})*(,\d{2})?|EUR\s?\d+(?:[.,]\d{2})?)/g;
    container.querySelectorAll('*').forEach(el => {
      if (!el.childNodes || !el.childNodes.length) return;
      el.childNodes.forEach(node=>{
        if (node.nodeType===3 && rx.test(node.textContent)){
          const span = document.createElement('span');
          span.className = 'js-price';
          span.textContent = node.textContent;
          el.replaceChild(span, node);
        }
      });
    });
  }

  // Hook di apertura modale “Dettagli”
  document.addEventListener('click', function(ev){
    const a = ev.target.closest('.open-view');
    if (!a) return;
    // Il rendering effettivo avviene asincrono in lavori.js;
    // usiamo un piccolo polling finché #v-overview è pieno.
    setTimeout(function wait(){
      const ov = document.getElementById('v-overview');
      const meta= document.getElementById('v-meta');
      if (ov && ov.innerHTML.trim()){
        markPrices(ov); markPrices(meta);
      } else {
        setTimeout(wait, 80);
      }
    }, 150);
  }, true);
})();
</script>

<!-- JS principale -->
<script src="../assets/javascript/lavori.js"></script>
<script src="../assets/javascript/main.js"></script>
</body>
  <?php include '../partials/footer.php' ?>
</html>
