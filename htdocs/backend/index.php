<?php
session_start();

require_once __DIR__ . '/assets/funzioni/db/db.php';
require_once __DIR__ . '/assets/funzioni/funzioni.php';
require_once __DIR__ . '/assets/funzioni/funzioni_dashboard.php';
require_once __DIR__ . '/assets/funzioni/authz.php';

requireLogin();

$uid    = currentUserId();
$roles  = userRoles($conn, $uid);
$isAdmin= currentUserIsAdminFallback() || in_array('admin', $roles, true);

$canWorkload = $isAdmin || currentUserCan('dashboard.view_workload');
$canReqKPI   = $isAdmin || currentUserCan('dashboard.view_last_requests_kpi');
$canCatMonth = $isAdmin || currentUserCan('dashboard.view_cat_month');
$canCalAll   = $isAdmin || currentUserCan('dashboard.view_calendar_all');
$canBooksTop = $isAdmin || currentUserCan('dashboard.view_books_topcats');

$data = buildDashboardData($conn, $_SESSION);
extract($data);

if (!$canCalAll) {
  $rawJson = html_entity_decode($eventiJson ?? '[]', ENT_QUOTES, 'UTF-8');
  $events  = json_decode($rawJson, true);
  if (!is_array($events)) $events = [];

  if ($events) {
    $aliases = currentUserAssigneeAliases();
    $aliases = array_values(array_unique(array_filter(array_map(function($a){
      return mb_strtolower(trim((string)$a), 'UTF-8');
    }, (array)$aliases))));
    $uidStr = (string)$uid;
    if (!in_array($uidStr, $aliases, true)) $aliases[] = $uidStr;

    $idKeys  = ['utente_id','uid','owner_id','assigned_user_id'];
    $strKeys = ['assegnato','assegnato_a','assigned_to','utente','owner','responsabile','titolo','title'];

    $events = array_values(array_filter($events, function($ev) use ($aliases, $uidStr, $idKeys, $strKeys){
      foreach ($idKeys as $k){
        if (!isset($ev[$k]) || $ev[$k]==='' || $ev[$k]===null) continue;
        $parts = array_map('trim', explode(',', (string)$ev[$k]));
        if (in_array($uidStr, $parts, true)) return true;
      }
      foreach ($strKeys as $k){
        if (!isset($ev[$k]) || $ev[$k]==='' || $ev[$k]===null) continue;
        $val = mb_strtolower(trim((string)$ev[$k]), 'UTF-8');
        if ($val==='') continue;
        foreach ($aliases as $a){
          if ($a!=='' && mb_strpos($val, $a, 0, 'UTF-8') !== false) return true;
        }
      }
      return false;
    }));
  }

  $eventiJson = e(json_encode($events, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

$needsCalendarMargin = (!$canCatMonth && !$canReqKPI);
$calExtraClass = $needsCalendarMargin ? ' mt-40' : '';

?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="assets/css/style1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<main>
  <header class="topbar">
    <div class="user-badge">
      <i class="fas fa-user-circle icon-user" aria-hidden="true"></i>
      <div>
        <div class="muted">Bentornata,</div>
        <div class="fw-800 ls-02"><?= e($_SESSION['utente']['nome'] ?? 'Utente') ?></div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>
    <div class="right">
      <?php include 'partials/navbar.php' ?>
      <a class="chip" href="auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <section class="cards-row mt-16">
    <a class="card kpi kpi--inline link" href="libri/index_libri.php">
      <div class="kpi-ico"><i class="fa-solid fa-book-open"></i></div>
      <div class="kpi-body">
        <div class="kpi-title">Libri in portfolio</div>
        <div class="kpi-value"><?= (int)$totLibri ?></div>
      </div>
    </a>

    <a class="card kpi kpi--inline link" href="lavori/index_lavori.php?assigned_to_me=1">
      <div class="kpi-ico"><i class="fa-solid fa-user-check"></i></div>
      <div class="kpi-body">
        <div class="kpi-title">Lavori assegnati a me</div>
        <div class="kpi-value"><?= (int)$lavoriAssegnatiAMe ?></div>
      </div>
    </a>

    <?php if ($canReqKPI): ?>
    <a class="card kpi kpi--inline link" href="email/index.php">
      <div class="kpi-ico"><i class="fa-solid fa-inbox"></i></div>
      <div class="kpi-body">
        <div class="kpi-title">Richieste (mese)</div>
        <div class="kpi-value"><?= (int)$richiesteMese ?></div>
      </div>
    </a>
    <?php endif; ?>

    <div class="card kpi">
      <div class="kpi-ico"><i class="fa-regular fa-calendar"></i></div>
      <div class="kpi-body">
        <div class="kpi-title">Prossima scadenza (per me)</div>
        <div class="kpi-value kpi-next"><?= e($nextMineLabel) ?></div>
      </div>
    </div>
  </section>

  <?php if ($canCatMonth): ?>
  <section class="panel span-12 mt-6">
    <h2><i class="fa-solid fa-layer-group"></i> Lavori per categoria (mese corrente)</h2>
    <div class="chart-wrap hbar">
      <canvas id="chartLavoriCat" class="resp" data-series='<?= $catMonthJson ?>' data-aspect="0.35"></canvas>
    </div>
    <div class="muted-sm">Basato su <code>data_ricezione</code> del mese corrente.</div>
  </section>
  <?php endif; ?>

  <section class="panel span-12 cal-panel<?= $calExtraClass ?>">
    <div class="flex-between gap-10 wrap">
      <h2 class="m-0"><i class="fa-regular fa-calendar"></i> Calendario (prossimi 7 giorni)</h2>
      <div class="muted">
        <?= e((new DateTimeImmutable($oggi))->format('d M')) ?> –
        <?= e((new DateTimeImmutable($fine))->format('d M Y')) ?>
      </div>
    </div>
    <div id="calendar" data-eventi='<?= $eventiJson ?>'></div>
  </section>

  <?php if ($canWorkload): ?>
  <section class="panel span-12">
    <div class="flex-between mb-8 gap-12 wrap">
      <h2 class="m-0"><i class="fa-solid fa-people-carry-box"></i> Carico di lavoro per utente</h2>
      <a class="chip" href="lavori/index_lavori.php"><i class="fas fa-arrow-right"></i> Vai ai lavori</a>
    </div>

    <div class="table-responsive">
      <table class="table compact">
        <thead>
          <tr>
            <th>Utente</th>
            <th class="text-right">Lavori assegnati</th>
            <th>Categoria più assegnata</th>
            <th>Scadenza più vicina</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $PALETTE = ['#004c60','#e67e22','#8e44ad','#2ecc71','#d35400','#16a085','#c0392b','#2980b9','#7f8c8d','#f1c40f','#27ae60','#9b59b6'];
          $colorFor = function($label) use ($PALETTE){
            $h=0; for($i=0,$n=strlen($label);$i<$n;$i++){$h=(($h*31)+ord($label[$i])) & 0xffffffff;}
            return $PALETTE[$h % count($PALETTE)];
          };
        ?>
        <?php if (!empty($workloadLavori)): foreach ($workloadLavori as $r):
              $uidRow  = (int)($r['uid'] ?? 0);
              $cats    = isset($topCatByUser[$uidRow]) ? $topCatByUser[$uidRow] : [];
        ?>
          <tr>
            <td><?= e($r['utente']) ?></td>
            <td class="text-right fw-700"><?= (int)$r['tot'] ?></td>
            <td>
              <?php if ($cats):
                    $first = $cats[0];
                    $nm = trim((string)$first['cat']); if($nm==='') $nm='—';
                    $cnt = (int)$first['c'];
                    $col = $colorFor($nm);
              ?>
                <div class="cat-chips">
                  <span class="cat-chip">
                    <span class="cat-dot" style="--dot: <?= e($col) ?>"></span>
                    <?= e($nm) ?> <span class="muted ml-2">(<?= $cnt ?>)</span>
                  </span>
                </div>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= e($r['next']) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4" class="muted">Nessun lavoro assegnato.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <?php $spanRight = $canBooksTop ? 'span-6' : 'span-12'; ?>
  <section class="equal-row">
    <?php if ($canBooksTop): ?>
      <div class="panel span-6">
        <h2><i class="fa-solid fa-tags"></i> Libri – top categorie (%)</h2>
        <div class="panel-body grid-gap-14">
          <?php
            $top = $vm['stats']['topCategorieLibri'] ?? [];
            $totTop = 0; foreach ($top as $r) { $totTop += (int)$r['tot']; }
            $palette = ['#004c60','#e67e22','#8e44ad','#2ecc71','#d35400','#16a085','#c0392b','#2980b9','#7f8c8d','#f1c40f','#27ae60','#9b59b6'];
          ?>
          <?php if ($top): foreach($top as $i=>$row):
            $n=(int)$row['tot']; $pct=$totTop? round($n*100/$totTop,1) : 0;
            $col=$palette[$i % count($palette)];
          ?>
            <div>
              <div class="flex-between gap-12 ai-center">
                <strong><?= e($row['nome']) ?></strong>
                <span class="muted"><?= $n ?> (<?= $pct ?>%)</span>
              </div>
              <div class="bar"><span class="bar-fill" style="--w: <?= $pct ?>%; --col: <?= $col ?>;"></span></div>
            </div>
          <?php endforeach; else: ?>
            <div class="muted">Nessun dato.</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="panel <?= $spanRight ?>">
      <h2><i class="fa-solid fa-book"></i> Ultimi 3 libri inseriti</h2>
      <div class="panel-body">
        <div class="books-row">
          <?php foreach(($vm['stats']['ultimiLibri'] ?? []) as $b): ?>
            <div class="book-card book-mini">
              <div class="cover cover-mini">
                <?php if(!empty($b['immagine'])): ?>
                  <img src="<?= e($b['immagine']) ?>" alt="">
                <?php else: ?>
                  <i class="fa-solid fa-book cover-fallback"></i>
                <?php endif; ?>
              </div>
              <div class="book-mini-body">
                <div class="book-mini-title"><?= e($b['titolo']) ?></div>
                <div class="muted">
                  <?php
                    $d = !empty($b['data_pubblicazione']) ? new DateTimeImmutable($b['data_pubblicazione']) : null;
                    echo $d ? e($d->format('d/m/Y')) : '—';
                  ?>
                </div>
              </div>
            </div>
          <?php endforeach; if (empty($vm['stats']['ultimiLibri'])): ?>
            <div class="muted">—</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <?php if ($canReqKPI): ?>
  <section class="panel span-12 last-requests-panel section-ultime">
    <div class="flex-between gap-10 wrap mb-6">
      <h2 class="m-0">Ultime richieste</h2>
      <a class="chip" href="admin/index_admin.php?section=richieste"><i class="fas fa-arrow-right"></i> Tutte</a>
    </div>

    <div class="table-responsive">
      <table class="table compact">
        <thead>
          <tr>
            <th>Richiedente</th>
            <th>Email</th>
            <th>Data</th>
            <th>Status</th>
            <th>Assegnato</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($ultime3)): foreach ($ultime3 as $r):
              $rid=(int)$r['id'];
              $vw = $vm['viewersMap'][$rid] ?? ['count'=>0,'users'=>[]];
              $vwCount = (int)($vw['count'] ?? 0);
              $vwUsers = is_array($vw['users'] ?? null) ? $vw['users'] : [];
        ?>
          <tr>
            <td>
              <div class="fw-600"><?= e(trim(($r['nome'] ?? '').' '.($r['cognome'] ?? '')) ?: ($r['rgs'] ?? '—')) ?></div>
              <div class="muted">
                <?php if (!empty($r['rgs'])): ?><?= e($r['rgs']) ?> · <?php endif; ?>
                <i class="fa-regular fa-eye"></i> <?= $vwCount ?>
                <?php if (!empty($vwUsers)): ?>(<?= e(implode(', ', $vwUsers)) ?>)<?php endif; ?>
              </div>
            </td>
            <td><?= e($r['email'] ?? '—') ?></td>
            <td><?= e((new DateTimeImmutable($r['created_at']))->format('d/m/Y H:i')) ?></td>
            <td>
              <?php $st = strtolower($r['status'] ?? ''); ?>
              <span class="chip <?= classStatusChip($st) ?>"><?= e(labelStatusIT($st)) ?></span>
            </td>
            <td><?= e(assigneeLabel($conn, $r['assigned_to'] ?? '')) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="5" class="muted">Nessuna richiesta recente.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php include 'partials/footer.php' ?>

<script defer src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.18/index.global.min.js"></script>
<script defer src="assets/javascript/dashboard.js"></script>
</body>
</html>
