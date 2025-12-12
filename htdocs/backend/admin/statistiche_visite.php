<?php
// backend/admin/statistiche_visite.php
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';

requireLogin();
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

// Dati visite (mesi + datasets)
$datiVisite = getVisiteMensiliPerPagina($conn) ?: ['mesi'=>[], 'datasets'=>[]];

// Riepilogo per chip
$chips = [];
foreach ($datiVisite['datasets'] as $ds) {
  $chips[] = [
    'label' => (string)($ds['label'] ?? '—'),
    'tot'   => array_sum(array_map('intval', $ds['data'] ?? [])),
  ];
}

// Preparo comodi alias per header
$nomeUtente = e($_SESSION['utente']['nome'] ?? 'Utente');
$ruoloUtente = e($_SESSION['utente']['ruolo'] ?? 'user');
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Statistiche Visite</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="../assets/css/style1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    :root{
      --primary:#004c60;
      --primary-50:#e6f5f8;
      --primary-150:#cfe8ef;
      --border:#e5e7eb; --panel:#ffffff; --muted:#64748b; --text:#0f172a;
      --shadow:0 10px 30px rgba(2,6,23,.08); --radius:16px; --maxw:1240px;
    }
    main{ max-width:var(--maxw); margin:0 auto; }

    .panel{ background:var(--panel); border:1px solid var(--border);
      border-radius:var(--radius); box-shadow:var(--shadow); padding:16px; }

    .legend-chips{ display:flex; flex-wrap:wrap; gap:8px; margin:6px 0 2px; }
    .legend-chip{
      display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:12px;
      background:#fff; border:1px solid var(--border); box-shadow:0 6px 18px rgba(2,6,23,.06);
      font-size:12px;
    }
    .dot{ width:10px; height:10px; border-radius:50%; display:inline-block; border:1px solid rgba(0,0,0,.06); }
    .controls{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:10px 0 12px; }
    .chip.btn{ cursor:pointer; }
    .chip.brand{ background:var(--primary-50); border-color:var(--primary-150); color:var(--primary); }
    .muted{ color:var(--muted) }
    .canvas-wrap{ background:#fff; border:1px solid var(--border); border-radius:14px; padding:10px; box-shadow:0 10px 24px rgba(2,6,23,.06); }
    .empty{ padding:20px; text-align:center; color:var(--muted) }
      .canvas-wrap{
    background:#fff;
    border:1px solid var(--border);
    border-radius:14px;
    padding:10px;
    box-shadow:0 10px 24px rgba(2,6,23,.06);

    /* NEW: altezze più generose */
    height: 460px;              /* desktop */
  }
  #visiteChart{ width:100%; height:100%; }  /* il canvas riempie il contenitore */

  /* un po’ più basso su schermi piccoli */
  @media (max-width: 960px){
    .canvas-wrap{ height: 380px; }
  }
  @media (max-width: 720px){
    .canvas-wrap{ height: 320px; }
  }
  </style>
</head>
<body>
<main>
  <!-- ===== Topbar coerente con dashboard ===== -->
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

  <!-- ===== Pannello Statistiche ===== -->
  <section class="panel" style="margin-top:16px;">
    <h2 style="margin:0 0 6px; display:flex; align-items:center; gap:10px; color:var(--primary);">
      <i class="fa-solid fa-chart-column"></i> Statistiche visite mensili per pagina
    </h2>
    <div class="muted" style="margin-bottom:6px;">
      Intervallo:
      <?= $datiVisite['mesi'] ? e(reset($datiVisite['mesi'])) : '—' ?> → <?= $datiVisite['mesi'] ? e(end($datiVisite['mesi'])) : '—' ?>
    </div>

    <div class="controls">
      <button id="btnToggleMode" class="chip btn brand" type="button">
        <i class="fa-solid fa-layer-group"></i> Impila
      </button>
      <button id="btnDownloadCsv" class="chip btn" type="button">
        <i class="fa-solid fa-download"></i> Esporta CSV
      </button>
    </div>

    <?php if ($chips): ?>
      <div class="legend-chips" id="legendChips">
        <?php foreach ($chips as $i=>$c): ?>
          <span class="legend-chip">
            <span class="dot" data-chip-dot="<?= (int)$i ?>"></span>
            <strong><?= e($c['label']) ?></strong>
            <span class="muted">· <?= (int)$c['tot'] ?> visite</span>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="canvas-wrap" style="margin-top:10px;">
      <?php if (!empty($datiVisite['mesi']) && !empty($datiVisite['datasets'])): ?>
        <canvas id="visiteChart" aria-label="Grafico visite per mese" role="img"></canvas>
      <?php else: ?>
        <div class="empty"><i class="fa-regular fa-face-meh-blank"></i> Nessun dato disponibile.</div>
      <?php endif; ?>
    </div>
  </section>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</main>

<?php
  $labels   = $datiVisite['mesi'];
  $datasets = $datiVisite['datasets'];
?>
<script>
(function(){
  const labels   = <?= json_encode(array_values($labels)) ?>;
  const datasets = <?= json_encode(array_values($datasets)) ?>;
  if (!labels.length || !datasets.length) return;

  const basePalette = [
    '#004c60','#0ea5e9','#10b981','#8b5cf6','#f59e0b',
    '#ef4444','#14b8a6','#3b82f6','#22c55e','#e11d48',
    '#475569','#a855f7','#06b6d4','#84cc16','#f97316'
  ];

  // Colora i dot dei chip
  document.querySelectorAll('[data-chip-dot]').forEach(el=>{
    const i = +el.getAttribute('data-chip-dot');
    el.style.background = basePalette[i % basePalette.length];
  });

  const cjDatasets = datasets.map((ds, i) => {
    const c = basePalette[i % basePalette.length];
    return {
      label: ds.label || ('Serie ' + (i+1)),
      data: (ds.data || []).map(v => +v || 0),
      backgroundColor: c + 'cc',
      borderColor: c,
      borderWidth: 1,
      borderRadius: 6,
      maxBarThickness: 46
    };
  });

  let stacked = false;
  const ctx = document.getElementById('visiteChart').getContext('2d');
  const chart = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets: cjDatasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      aspectRatio: 2.2,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: true, labels: { usePointStyle: true } },
        tooltip: { callbacks: {
          title: (items)=> items?.[0]?.label ?? '',
          label: (ctx)=> ` ${ctx.dataset.label}: ${ctx.formattedValue}`
        }}
      },
      scales: {
        x: { stacked, ticks: { autoSkip: true, maxRotation: 0 } },
        y: { stacked, beginAtZero: true }
      }
    }
  });

  const btnToggle = document.getElementById('btnToggleMode');
  const setBtnLabel = ()=> btnToggle.innerHTML =
    (stacked
      ? '<i class="fa-solid fa-grip-lines-vertical"></i> Affianca'
      : '<i class="fa-solid fa-layer-group"></i> Impila');
  setBtnLabel();

  btnToggle.addEventListener('click', ()=>{
    stacked = !stacked;
    chart.options.scales.x.stacked = stacked;
    chart.options.scales.y.stacked = stacked;
    setBtnLabel();
    chart.update();
  });

  document.getElementById('btnDownloadCsv').addEventListener('click', ()=>{
    const head = ['Mese', ...chart.data.datasets.map(d=>d.label)];
    const rows = labels.map((lab, rIdx)=>{
      const vals = chart.data.datasets.map(d => (d.data?.[rIdx] ?? 0));
      return [lab, ...vals];
    });
    const csv = [head, ...rows].map(r => r.map(v=>{
      const s = (v ?? '').toString();
      return /[",;\n]/.test(s) ? `"${s.replace(/"/g,'""')}"` : s;
    }).join(';')).join('\n');

    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'visite_mensili.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
  });
})();
</script>
<script src="../assets/javascript/main.js"></script>
</body>
</html>
