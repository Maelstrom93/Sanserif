<?php
// backend/admin/statistiche_generali.php
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';

requireLogin();
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!isAdmin()) { header('Location: ../index.php'); exit; }

/* --------------------- Utils --------------------- */
function qall(mysqli $c, $sql, $types = '', $params = array()){
  $st = $c->prepare($sql);
  if (!$st) return array();
  if ($types && $params) { $st->bind_param($types, ...$params); }
  if (!$st->execute()) return array();
  $res = $st->get_result();
  return $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
}
function qone(mysqli $c, $sql, $types = '', $params = array()){
  $a = qall($c,$sql,$types,$params);
  return $a ? $a[0] : null;
}
function columnsOf(mysqli $c, $table){ return qall($c, "SHOW COLUMNS FROM {$table}"); }

function monthKeysLast12(){
  $keys = array();
  $dt = new DateTimeImmutable('first day of this month');
  for ($i=11;$i>=0;$i--){
    $m = $dt->sub(new DateInterval("P{$i}M"));
    $keys[] = $m->format('Y-m');
  }
  return $keys;
}
function labelsITFromKeys($keys){
  $mesi = array('', 'Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic');
  $out = array();
  foreach ($keys as $ym){
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $ym.'-01');
    $out[] = $d ? $mesi[(int)$d->format('n')] : $ym;
  }
  return $out;
}
function seriesFromCounts($keys, $rows, $colKey, $colVal){
  $map = array();
  foreach ($rows as $r) { $map[(string)$r[$colKey]] = (float)$r[$colVal]; }
  $vals = array();
  foreach ($keys as $k){ $vals[] = isset($map[$k]) ? (float)$map[$k] : 0; }
  return $vals;
}

/* --------------------- Intervallo base --------------------- */
$last12  = monthKeysLast12();
$labels  = labelsITFromKeys($last12);
$labelsJson = json_encode($labels, JSON_UNESCAPED_UNICODE);

/* ============================================================
   LIBRI
   ============================================================ */
$libriMonth = qall($conn, "
  SELECT DATE_FORMAT(aggiunto_il, '%Y-%m') AS ym, COUNT(*) AS n
  FROM libri
  WHERE aggiunto_il IS NOT NULL
  GROUP BY ym ORDER BY ym
");

$libriPerMese = seriesFromCounts($last12, $libriMonth, 'ym', 'n');

$libCat = qall($conn, "
  SELECT cl.nome AS categoria, COUNT(*) AS n
  FROM (
    SELECT lc.categoria_id FROM libri_categorie lc
    UNION ALL
    SELECT l.categoria_id FROM libri l WHERE l.categoria_id IS NOT NULL
  ) x
  JOIN categorie_libri cl ON cl.id = x.categoria_id
  GROUP BY cl.nome ORDER BY n DESC LIMIT 10
");
$libriTopCatLabels = array_column($libCat,'categoria');
$libriTopCatValues = array_map('intval', array_column($libCat,'n'));

$libEd = qall($conn, "
  SELECT TRIM(casa_editrice) AS editore, COUNT(*) AS n
  FROM libri
  WHERE TRIM(COALESCE(casa_editrice,'')) <> ''
  GROUP BY editore ORDER BY n DESC LIMIT 12
");
$libriTopEdLabels = array_column($libEd,'editore');
$libriTopEdValues = array_map('intval', array_column($libEd,'n'));

/* ============================================================
   ARTICOLI
   ============================================================ */
$artMonth = qall($conn, "
  SELECT DATE_FORMAT(data_pubblicazione,'%Y-%m') AS ym, COUNT(*) AS n
  FROM articoli
  WHERE data_pubblicazione IS NOT NULL
  GROUP BY ym ORDER BY ym
");
$artPerMese = seriesFromCounts($last12, $artMonth, 'ym', 'n');

$artCat = qall($conn, "
  SELECT
    COALESCE(ca.nome, NULLIF(TRIM(a.categoria), '')) AS categoria,
    COUNT(*) AS n
  FROM articoli a
  LEFT JOIN categorie_articoli ca
    ON (a.categoria REGEXP '^[0-9]+$' AND ca.id = CAST(a.categoria AS UNSIGNED))
  WHERE TRIM(COALESCE(a.categoria,'')) <> ''
  GROUP BY COALESCE(ca.nome, NULLIF(TRIM(a.categoria), ''))
  ORDER BY n DESC
  LIMIT 10
");


$artTopCatLabels = array_map('strval', array_column($artCat,'categoria'));
$artTopCatValues = array_map('intval', array_column($artCat,'n'));

/* Mix categorie nel tempo (top 6) */
$artCatTotals = qall($conn, "
  SELECT COALESCE(ca.nome, NULLIF(TRIM(a.categoria), '')) AS categoria, COUNT(*) n
  FROM articoli a
  LEFT JOIN categorie_articoli ca
    ON (a.categoria REGEXP '^[0-9]+$' AND ca.id = CAST(a.categoria AS UNSIGNED))
  WHERE TRIM(COALESCE(a.categoria,'')) <> ''
  GROUP BY COALESCE(ca.nome, NULLIF(TRIM(a.categoria), ''))
  ORDER BY n DESC
  LIMIT 6
");


$topArtCats = array_map('strval', array_column($artCatTotals,'categoria'));
$artMixRows = qall($conn, "
  SELECT DATE_FORMAT(a.data_pubblicazione,'%Y-%m') ym,
         COALESCE(ca.nome, NULLIF(TRIM(a.categoria), '')) categoria,
         COUNT(*) n
  FROM articoli a
  LEFT JOIN categorie_articoli ca
    ON (a.categoria REGEXP '^[0-9]+$' AND ca.id = CAST(a.categoria AS UNSIGNED))
  WHERE a.data_pubblicazione IS NOT NULL
  GROUP BY ym, COALESCE(ca.nome, NULLIF(TRIM(a.categoria), ''))
  ORDER BY ym
");


$artMixBuf = array();
foreach ($artMixRows as $r){ $artMixBuf[$r['ym'].'|'.(string)$r['categoria']] = (int)$r['n']; }
$artMixSeries = array();
foreach ($topArtCats as $nm){
  $serie = array();
  foreach ($last12 as $ym){ $serie[] = isset($artMixBuf[$ym.'|'.$nm]) ? (int)$artMixBuf[$ym.'|'.$nm] : 0; }
  $artMixSeries[$nm] = $serie;
}

/* ============================================================
   LAVORI
   ============================================================ */
$row = qone($conn, "SELECT COUNT(*) AS n FROM lavori WHERE LOWER(COALESCE(priorita,'')) = 'alta'");
$lavHighPrioNum = (int)($row ? $row['n'] : 0);

/* In scadenza entro 30 giorni + fallback assegnatario da attività */
$lavDueSoon = qall($conn, "
  SELECT
    l.id, l.titolo, l.scadenza,
    COALESCE(u.nome,
      (SELECT u2.nome
         FROM lavori_attivita la
         LEFT JOIN utenti u2 ON u2.id = la.utente_id
        WHERE la.lavoro_id = l.id
        ORDER BY COALESCE(la.updated_at, la.created_at) DESC
        LIMIT 1
      ),
      '—'
    ) AS utente
  FROM lavori l
  LEFT JOIN utenti u ON u.id = l.assegnato_a
  WHERE l.scadenza BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND LOWER(COALESCE(l.stato,'')) NOT IN ('chiuso','annullato')
  ORDER BY l.scadenza ASC
  LIMIT 20
");

/* Stati per mese */
$lavByStatus = qall($conn, "
  SELECT DATE_FORMAT(l.data_ricezione,'%Y-%m') AS ym,
         CASE
           WHEN LOWER(COALESCE(l.stato,'')) IN ('aperto','aperti','open','nuovo','nuova') THEN 'Aperti'
           WHEN LOWER(COALESCE(l.stato,'')) IN ('in_lavorazione','in lavorazione','progress','working') THEN 'In lavorazione'
           WHEN LOWER(COALESCE(l.stato,'')) IN ('pausa','on hold') THEN 'Pausa'
           WHEN LOWER(COALESCE(l.stato,'')) IN ('completato','completati','done','concluso') THEN 'Completati'
           WHEN LOWER(COALESCE(l.stato,'')) IN ('chiuso','chiusa','closed') THEN 'Chiusi'
           WHEN LOWER(COALESCE(l.stato,'')) IN ('annullato','cancellato') THEN 'Annullati'
           ELSE 'Altro'
         END AS stato_norm,
         COUNT(*) AS n
  FROM lavori l
  WHERE l.data_ricezione IS NOT NULL
  GROUP BY ym, stato_norm
  ORDER BY ym
");
$STATUS_ORDER = array('Aperti','In lavorazione','Pausa','Completati','Chiusi','Annullati','Altro');
$statusSeries = array(); foreach ($STATUS_ORDER as $s){ $statusSeries[$s] = array_fill(0, count($last12), 0); }
$buffer = array(); foreach ($lavByStatus as $r){ $buffer[$r['ym'].'|'.$r['stato_norm']] = (int)$r['n']; }
for ($i=0;$i<count($last12);$i++){
  $ym = $last12[$i];
  foreach ($STATUS_ORDER as $s){
    $statusSeries[$s][$i] = isset($buffer[$ym.'|'.$s]) ? (int)$buffer[$ym.'|'.$s] : 0;
  }
}

/* Lavori per priorità */
$lavByPrio = qall($conn, "
  SELECT CASE LOWER(COALESCE(priorita,'')) 
           WHEN 'alta' THEN 'Alta'
           WHEN 'media' THEN 'Media'
           WHEN 'bassa' THEN 'Bassa'
           ELSE 'Non impostata' END AS p, COUNT(*) n
  FROM lavori GROUP BY p
  ORDER BY FIELD(p,'Alta','Media','Bassa','Non impostata')
");

/* Lead time medio (giorni) */
$leadRows = qall($conn, "
  SELECT DATE_FORMAT(data_ricezione,'%Y-%m') ym,
         AVG(GREATEST(DATEDIFF(COALESCE(scadenza, data_ricezione), data_ricezione),0)) lead_days
  FROM lavori GROUP BY ym ORDER BY ym
");
$leadSeries = seriesFromCounts($last12, $leadRows, 'ym', 'lead_days');

/* Creati / Chiusi / Scaduti per mese */
$lavCreati = qall($conn, "
  SELECT DATE_FORMAT(data_ricezione,'%Y-%m') ym, COUNT(*) n
  FROM lavori GROUP BY ym ORDER BY ym
");
$lavChiusi = qall($conn, "
  SELECT DATE_FORMAT(updated_at,'%Y-%m') ym, COUNT(*) n
  FROM lavori
  WHERE LOWER(stato) IN ('chiuso','completato','concluso')
  GROUP BY ym ORDER BY ym
");
$lavScadutiPerMese = qall($conn, "
  SELECT DATE_FORMAT(scadenza,'%Y-%m') ym, COUNT(*) n
  FROM lavori
  WHERE scadenza IS NOT NULL
    AND scadenza < CURDATE()
    AND LOWER(COALESCE(stato,'')) NOT IN ('chiuso','annullato','completato','concluso')
  GROUP BY ym ORDER BY ym
");
$lavCreatiSeries = seriesFromCounts($last12,$lavCreati,'ym','n');
$lavChiusiSeries = seriesFromCounts($last12,$lavChiusi,'ym','n');
$lavScadutiSeries = seriesFromCounts($last12,$lavScadutiPerMese,'ym','n');

/* KPI scaduti / in settimana */
$kpiOver = qone($conn, "
  SELECT
    SUM(scadenza < CURDATE() AND LOWER(COALESCE(stato,'')) NOT IN ('chiuso','annullato','completato','concluso')) AS scaduti,
    SUM(scadenza BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND LOWER(COALESCE(stato,'')) NOT IN ('chiuso','annullato')) AS entro7
  FROM lavori
");

/* Workload per utente su 12 mesi (fix con fallback attività + top8) */
$wRows = array();
if (tableExists($conn,'lavori_attivita')) {
  $wRows = qall($conn, "
    SELECT
      DATE_FORMAT(COALESCE(la.scadenza, la.created_at),'%Y-%m') AS ym,
      COALESCE(NULLIF(TRIM(u.nome),''), NULLIF(TRIM(u.email),''), CONCAT('#',u.id)) AS utente,
      COUNT(*) AS n
    FROM lavori_attivita la
    LEFT JOIN utenti u ON u.id = la.utente_id
    WHERE la.utente_id IS NOT NULL
    GROUP BY ym, utente
    ORDER BY ym
  ");
}
/* Top 8 utenti per volume totale */
$totPerUser = array();
foreach ($wRows as $r){
  $u = (string)$r['utente'];
  $totPerUser[$u] = ($totPerUser[$u] ?? 0) + (int)$r['n'];
}
arsort($totPerUser);
$users = array_slice(array_keys($totPerUser), 0, 8);
$wBuf = array(); foreach ($wRows as $r){ $wBuf[$r['ym'].'|'.$r['utente']] = (int)$r['n']; }
$workloadSeries = array();
foreach ($users as $u){
  $serie = array();
  foreach ($last12 as $ym){ $serie[] = isset($wBuf[$ym.'|'.$u]) ? (int)$wBuf[$ym.'|'.$u] : 0; }
  $workloadSeries[$u] = $serie;
}

/* Attività: throughput e tempi medi */
$attComp = qall($conn, "
  SELECT DATE_FORMAT(completato_il,'%Y-%m') ym, COUNT(*) completate
  FROM lavori_attivita WHERE completata=1 AND completato_il IS NOT NULL
  GROUP BY ym ORDER BY ym
");
$attCompSeries = seriesFromCounts($last12,$attComp,'ym','completate');
$attAvgDaysAll = qone($conn, "
  SELECT AVG(TIMESTAMPDIFF(DAY, created_at, IFNULL(completato_il,NOW()))) avg_days
  FROM lavori_attivita WHERE completata=1
");
$attAvgPerMonth = qall($conn, "
  SELECT DATE_FORMAT(IFNULL(completato_il, created_at),'%Y-%m') ym,
         AVG(TIMESTAMPDIFF(DAY, created_at, IFNULL(completato_il,NOW()))) avg_days
  FROM lavori_attivita
  WHERE completata=1
  GROUP BY ym ORDER BY ym
");
$attAvgSeries = seriesFromCounts($last12,$attAvgPerMonth,'ym','avg_days');

/* Provenienza lavori */
$lavProv = qall($conn, "
  SELECT TRIM(COALESCE(provenienza,'—')) src, COUNT(*) n
  FROM lavori GROUP BY src ORDER BY n DESC
");
$lavProvLabels = array_column($lavProv,'src');
$lavProvValues = array_map('intval', array_column($lavProv,'n'));

/* Categorie lavori per mese (top 5) */
$top5Names = array(); 
$catSeries = array();

if (tableExists($conn,'lavori_categorie') && tableExists($conn,'categorie_lavoro')) {
  // Top 5 categorie per volume complessivo
  $top5 = qall($conn, "
    SELECT c.nome, COUNT(*) AS n
    FROM lavori l
    JOIN lavori_categorie lc ON lc.lavoro_id = l.id
    JOIN categorie_lavoro c  ON c.id = lc.categoria_id
    GROUP BY c.id, c.nome
    ORDER BY n DESC
    LIMIT 5
  ");
  $top5Names = array_column($top5,'nome');

  if (!empty($top5Names)) {
    // Serie mensili per le top5
    $rows = qall($conn, "
      SELECT DATE_FORMAT(l.data_ricezione,'%Y-%m') AS ym, c.nome, COUNT(*) AS n
      FROM lavori l
      JOIN lavori_categorie lc ON lc.lavoro_id = l.id
      JOIN categorie_lavoro c  ON c.id = lc.categoria_id
      GROUP BY ym, c.id, c.nome
      ORDER BY ym
    ");
    $buf = array();
    foreach ($rows as $r) { $buf[$r['ym'].'|'.$r['nome']] = (int)$r['n']; }
    foreach ($last12 as $ym) {
      foreach ($top5Names as $nm) {
        if (!isset($catSeries[$nm])) $catSeries[$nm] = array();
        $catSeries[$nm][] = isset($buf[$ym.'|'.$nm]) ? (int)$buf[$ym.'|'.$nm] : 0;
      }
    }
  }
}
/* ============================================================
   PREVENTIVI
   ============================================================ */
$prevMonth = qall($conn, "
  SELECT DATE_FORMAT(data,'%Y-%m') AS ym,
         COUNT(*) AS n,
         SUM(COALESCE(totale_con_iva, totale, importo, 0)) AS tot
  FROM preventivi
  WHERE data IS NOT NULL
  GROUP BY ym ORDER BY ym
");
$prevNPerMese   = seriesFromCounts($last12, $prevMonth, 'ym', 'n');
$prevTotPerMese = seriesFromCounts($last12, $prevMonth, 'ym', 'tot');

/* Conversione preventivi (inviati vs accettati) */
$prevConv = qall($conn, "
  SELECT DATE_FORMAT(data,'%Y-%m') ym,
         SUM(stato='inviato') inviati,
         SUM(stato='accettato') accettati
  FROM preventivi GROUP BY ym ORDER BY ym
");
$prevInviati = seriesFromCounts($last12,$prevConv,'ym','inviati');
$prevAccettati = seriesFromCounts($last12,$prevConv,'ym','accettati');
$prevRate = array();
for ($i=0;$i<count($last12);$i++){
  $prevRate[] = ($prevInviati[$i] > 0) ? round(($prevAccettati[$i]/$prevInviati[$i])*100,1) : 0;
}

/* Valore medio preventivo */
$prevAvg = qall($conn, "
  SELECT DATE_FORMAT(data,'%Y-%m') ym,
         AVG(COALESCE(totale_con_iva,totale,importo,0)) avg_val
  FROM preventivi GROUP BY ym ORDER BY ym
");
$prevAvgSeries = seriesFromCounts($last12,$prevAvg,'ym','avg_val');

/* Stato preventivi (torta) */
$prevByStatus = qall($conn, "
  SELECT CASE COALESCE(stato,'bozza')
           WHEN 'bozza' THEN 'Bozza'
           WHEN 'inviato' THEN 'Inviato'
           WHEN 'accettato' THEN 'Accettato'
           WHEN 'rifiutato' THEN 'Rifiutato'
         END AS s, COUNT(*) n
  FROM preventivi GROUP BY s
  ORDER BY FIELD(s,'Accettato','Inviato','Bozza','Rifiutato')
");

/* Top 10 clienti per valore */
$prevTopCli = qall($conn, "
  SELECT COALESCE(c.nome, p.cliente_nome_custom, '—') cliente,
         SUM(COALESCE(p.totale_con_iva,p.totale,p.importo,0)) tot
  FROM preventivi p LEFT JOIN clienti c ON c.id = p.cliente_id
  GROUP BY cliente ORDER BY tot DESC LIMIT 10
");
$topCliLabels = array_column($prevTopCli,'cliente');
$topCliValues = array_map('floatval', array_column($prevTopCli,'tot'));

/* ============================================================
   RICHIESTE (autodetect)
   ============================================================ */
$reqTable = null;
foreach (array('contact_requests','richieste','email','emails','contatti') as $cand) {
  if (tableExists($conn,$cand)) { $reqTable = $cand; break; }
}
$req = array(
  'labels' => $labels, 'per_mese' => array_fill(0,12,0),
  'aperte' => 0, 'chiuse' => 0, 'da_chiudere' => 0,
  'by_status' => array(), 'sla_avg_min' => null
);
if ($reqTable) {
  $reqRaw = qall($conn, "
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS n
    FROM {$reqTable}
    WHERE created_at IS NOT NULL
    GROUP BY ym ORDER BY ym
  ");
  $req['per_mese'] = seriesFromCounts($last12, $reqRaw, 'ym', 'n');

  $cols = columnsOf($conn, $reqTable);
  $colnames = array(); foreach ($cols as $r){ $colnames[] = strtolower($r['Field']); }
  $statusCol = in_array('status',$colnames,true) ? 'status' : (in_array('stato',$colnames,true) ? 'stato' : null);

  if ($statusCol) {
    $chiuse = qone($conn, "SELECT COUNT(*) n FROM {$reqTable} WHERE LOWER({$statusCol}) IN ('chiusa','chiuso','closed')");
    $tutte  = qone($conn, "SELECT COUNT(*) n FROM {$reqTable}");
    $req['chiuse'] = (int)($chiuse ? $chiuse['n'] : 0);
    $tot = (int)($tutte ? $tutte['n'] : 0);
    $req['da_chiudere'] = max(0, $tot - $req['chiuse']);

    $req['by_status'] = qall($conn, "
      SELECT {$statusCol} AS s, COUNT(*) AS n
      FROM {$reqTable}
      GROUP BY {$statusCol}
      ORDER BY n DESC
    ");
  }

  /* Tempo alla prima presa in carico (minuti) con contact_request_views */
  if (tableExists($conn,'contact_request_views') && $reqTable === 'contact_requests') {
    $sla = qone($conn, "
      SELECT AVG(TIMESTAMPDIFF(MINUTE, cr.created_at, v.first_view)) avg_min
      FROM contact_requests cr
      JOIN (SELECT request_id, MIN(viewed_at) first_view FROM contact_request_views GROUP BY request_id) v
        ON v.request_id = cr.id
    ");
    $req['sla_avg_min'] = $sla && $sla['avg_min'] !== null ? (float)$sla['avg_min'] : null;

    $slaPerMeseRows = qall($conn, "
      SELECT DATE_FORMAT(cr.created_at,'%Y-%m') ym,
             AVG(TIMESTAMPDIFF(MINUTE, cr.created_at, v.first_view)) avg_min
      FROM contact_requests cr
      JOIN (SELECT request_id, MIN(viewed_at) first_view FROM contact_request_views GROUP BY request_id) v
        ON v.request_id = cr.id
      GROUP BY ym ORDER BY ym
    ");
    $req['sla_series'] = seriesFromCounts($last12,$slaPerMeseRows,'ym','avg_min');
  }
}

/* ============================================================
   DATA QUALITY CHECKS
   ============================================================ */
$qc = array(
  'scad_before_recv' => (int)(qone($conn,"SELECT COUNT(*) c FROM lavori WHERE scadenza IS NOT NULL AND scadenza < data_ricezione")['c'] ?? 0),
  'prev_tot_incoh'   => (int)(qone($conn,"SELECT COUNT(*) c FROM preventivi WHERE COALESCE(totale_con_iva,0) < COALESCE(totale,0)")['c'] ?? 0),
  'art_cat_orfani'   => (int)(qone($conn,"
    SELECT COUNT(*) c
    FROM articoli a
    WHERE a.categoria REGEXP '^[0-9]+$'
      AND NOT EXISTS (SELECT 1 FROM categorie_articoli ca WHERE ca.id = CAST(a.categoria AS UNSIGNED))
  ")['c'] ?? 0),
);

/* =================== UI =================== */
function e2($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Statistiche generali</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="../assets/css/style1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- SafeChart wrapper: deve stare PRIMA di qualsiasi new Chart(...) -->
  <script>
 // ==== PATCH: Safe Chart wrapper (skippa i grafici senza canvas) + registro istanze ====
const __Chart = Chart;
window.__charts = []; // registro globale affidabile

function SafeChart(ctx, cfg){
  if (!ctx || (typeof ctx.getContext !== 'function' && typeof ctx.canvas === 'undefined')) {
    console.warn('Chart skipped: missing/invalid canvas for', cfg?.options?.plugins?.title?.text || cfg?.type || '(chart)');
    return null;
  }
  if (typeof ctx.getContext !== 'function' && ctx.canvas) ctx = ctx.canvas;

  const ch = new __Chart(ctx, cfg);
  window.__charts.push(ch);
  return ch;
}


Object.getOwnPropertyNames(__Chart).forEach(k => { try { SafeChart[k] = __Chart[k]; } catch(_) {} });
Object.setPrototypeOf(SafeChart, __Chart);
window.Chart = SafeChart;

function forEachChart(fn){
  (window.__charts || []).forEach(ch => ch && fn(ch));
}

  </script>

  <style>
    :root{ --primary:#004c60; --primary-50:#e6f5f8; --primary-150:#cfe8ef; --border:#e5e7eb; }
    .wrap{ max-width:1240px; margin:0 auto; }
    .kpi-row{ display:grid; gap:12px; grid-template-columns: repeat(4, minmax(0,1fr)); }
    @media (max-width: 960px){ .kpi-row{ grid-template-columns: repeat(2, minmax(0,1fr)); } }
    @media (max-width: 640px){ .kpi-row{ grid-template-columns: 1fr; } }
    .kpi{ display:flex; align-items:center; gap:12px; padding:14px 16px;
          border:1px solid var(--primary-150); border-radius:14px; background:#fff;
          box-shadow:0 10px 24px rgba(2,6,23,.06); }
    .kpi .ico{ width:40px; height:40px; border-radius:10px; display:grid; place-items:center;
               background:var(--primary-50); color:var(--primary); border:1px solid var(--primary-150); }
    .kpi .txt{ font-size:13px; color:#5f6b7a; font-weight:700 }
    .kpi .val{ margin-left:auto; font-size:22px; font-weight:900; color:#0f172a }

    .panel h2{ margin:0 0 10px; display:flex; align-items:center; gap:8px;}
    .panel h2 .btn{ margin-left:auto; font-size:12px; padding:6px 10px; border:1px solid var(--border); border-radius:8px; cursor:pointer; background:#fff; }
    .two-col{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    .three-col{ display:grid; grid-template-columns: repeat(3,1fr); gap:12px; }
    @media (max-width: 960px){ .two-col, .three-col{ grid-template-columns: 1fr; } }

    .canvas-wrap{ background:#fff; border:1px solid var(--border); padding:10px;
                  box-shadow:0 10px 24px rgba(2,6,23,.06); height:460px; }
    .canvas-wrap.tall{ height:520px; } .canvas-wrap.short{ height:360px; }
    .canvas-wrap canvas{ width:100%; height:100%; }

    .table-responsive{ overflow:auto; border:1px solid var(--border); background:#fff; }
    .due-table{ width:100%; border-collapse:separate; border-spacing:0; }
    .due-table th, .due-table td{ padding:8px 10px; border-top:1px solid var(--border); white-space:nowrap; }
    .due-table th{ background:#f8fafc; position:sticky; top:0; }
    .chips{ display:flex; flex-wrap:wrap; gap:8px; }
    .chip.stat{ background:#eef6ff; border:1px solid #dbeafe; color:#1e293b; }
    .kpi .valwrap{ margin-left:auto; display:flex; flex-direction:column; align-items:flex-end; }
    .kpi .delta{ font-size:12px; font-weight:700; margin-top:2px; }
    .kpi .delta.up{ color:#16a34a; }      /* verde */
    .kpi .delta.down{ color:#dc2626; }    /* rosso */
    .toolbar{ display:flex; gap:8px; margin:10px 0 0; flex-wrap:wrap; }
    .toolbar .btn{ font-size:12px; padding:6px 10px; border:1px solid var(--border);
                   border-radius:8px; background:#fff; cursor:pointer; }
    .toolbar .btn.active{ background:var(--primary-50); border-color:var(--primary-150); }
    @media (max-width: 640px){
  .canvas-wrap{ height:320px; }
  .canvas-wrap.short{ height:280px; }
}

  </style>
</head>
<body>
<main class="wrap">
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
<!-- Toolbar range globale -->
<div class="toolbar" style="margin:12px 0;">
  <button class="btn active" data-range="12">Ultimi 12 mesi</button>
  <button class="btn" data-range="6">Ultimi 6 mesi</button>
  <button class="btn" data-range="3">Ultimi 3 mesi</button>
  <button class="btn" data-range="ytd">YTD</button>
</div>

  <!-- KPIs -->
  <section class="kpi-row" style="margin-top:16px;">
    <div class="kpi"><div class="ico"><i class="fa-solid fa-book"></i></div><div class="txt">Libri (12 mesi)</div><div class="val"><?= array_sum($libriPerMese) ?></div></div>
    <div class="kpi"><div class="ico"><i class="fa-solid fa-feather"></i></div><div class="txt">Articoli (12 mesi)</div><div class="val"><?= array_sum($artPerMese) ?></div></div>
    <div class="kpi"><div class="ico"><i class="fa-solid fa-briefcase"></i></div><div class="txt">Lavori (12 mesi)</div><div class="val"><?= array_sum(seriesFromCounts($last12, $lavByStatus, 'ym', 'n')) ?></div></div>
    <div class="kpi"><div class="ico"><i class="fa-solid fa-file-invoice"></i></div><div class="txt">Preventivi (12 mesi)</div><div class="val"><?= array_sum($prevNPerMese) ?></div></div>
  </section>

  <!-- Libri -->
  <section class="panel span-12" style="margin-top:14px;">
    <h2><i class="fa-solid fa-book-open"></i> Libri portfolio
      <button class="btn" onclick="exportCSV('chartLibriMese','libri_per_mese.csv')"><i class="fa-solid fa-download"></i> CSV</button>
    </h2>
    <div class="two-col">
      <div class="canvas-wrap"><canvas id="chartLibriMese"></canvas></div>
      <div class="canvas-wrap short"><canvas id="chartLibriCat"></canvas></div>
    </div>
    <div class="canvas-wrap short" style="margin-top:12px;"><canvas id="chartLibriEditori"></canvas></div>
  </section>

  <!-- Articoli -->
  <section class="panel span-12">
    <h2><i class="fa-solid fa-feather"></i> Articoli</h2>
    <div class="two-col">
      <div class="canvas-wrap"><canvas id="chartArticoliMese"></canvas></div>
      <div class="canvas-wrap short"><canvas id="chartArticoliCat"></canvas></div>
    </div>
    <div class="canvas-wrap" style="margin-top:12px;"><canvas id="chartArticoliMix"></canvas></div>
  </section>



  <!-- Lavori -->
  <section class="panel span-12">
    <h2><i class="fa-solid fa-briefcase"></i> Lavori</h2>
    <div class="three-col">
      <div class="canvas-wrap"><canvas id="chartLavoriStati"></canvas></div>
      <div class="canvas-wrap short"><canvas id="chartLavoriPrio"></canvas></div>
      <div class="canvas-wrap"><canvas id="chartLeadTime"></canvas></div>
    </div>
    <div class="two-col" style="margin-top:12px;">
      <div class="canvas-wrap"><canvas id="chartCreatiChiusiScaduti"></canvas></div>
      <div class="canvas-wrap"><canvas id="chartWorkloadUtente"></canvas></div>
    </div>
    <div class="canvas-wrap" style="margin-top:12px;"><canvas id="chartLavoriCatMonth"></canvas></div>
    <div class="two-col" style="margin-top:12px;">
      <div class="canvas-wrap short"><canvas id="chartAttThroughput"></canvas></div>
      <div class="canvas-wrap short"><canvas id="chartAttAvgDays"></canvas></div>
    </div>
    <div class="canvas-wrap short" style="margin-top:12px;"><canvas id="chartLavoriProvenienza"></canvas></div>
  </section>

<?php if (!empty($lavDueSoon)): ?>
<section class="panel span-12">
  <h2><i class="fa-regular fa-clock"></i> Lavori in scadenza (entro 30 giorni)</h2>
  <div class="table-responsive">
    <table class="due-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Titolo</th>
          <th>Scadenza</th>
          <th>Assegnato a</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($lavDueSoon as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= e2($r['titolo']) ?></td>
          <td><?= e2(date('d/m/Y', strtotime($r['scadenza']))) ?></td>
          <td><?= e2($r['utente']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

  <!-- Preventivi -->
  <section class="panel span-12">
    <h2><i class="fa-solid fa-file-invoice"></i> Preventivi</h2>
    <div class="two-col">
      <div class="canvas-wrap"><canvas id="chartPrevCount"></canvas></div>
      <div class="canvas-wrap"><canvas id="chartPrevTot"></canvas></div>
    </div>
    <div class="two-col" style="margin-top:12px;">
      <div class="canvas-wrap"><canvas id="chartPrevConv"></canvas></div>
      <div class="canvas-wrap"><canvas id="chartPrevRate"></canvas></div>
    </div>
    <div class="two-col" style="margin-top:12px;">
      <div class="canvas-wrap"><canvas id="chartPrevAvg"></canvas></div>
      <div class="canvas-wrap short"><canvas id="chartPrevStatus"></canvas></div>
    </div>
    <div class="canvas-wrap short" style="margin-top:12px;"><canvas id="chartPrevTopClienti"></canvas></div>
  </section>

  <?php if ($reqTable): ?>
  <section class="panel span-12">
    <h2><i class="fa-solid fa-inbox"></i> Richieste (tabella: <?= e2($reqTable) ?>)</h2>
    <div class="two-col">
      <div class="canvas-wrap short"><canvas id="chartReqMese"></canvas></div>
      <div class="kpi" style="height:100%;">
        <div class="ico"><i class="fa-solid fa-list-check"></i></div>
        <div class="txt">Stato</div>
        <div class="val" style="display:flex; flex-direction:column; gap:6px;">
          <span class="chip stat">Tot. 12 mesi: <b><?= array_sum($req['per_mese']) ?></b></span>
          <span class="chip stat">Chiuse: <b><?= (int)$req['chiuse'] ?></b></span>
          <span class="chip stat">Da chiudere: <b><?= (int)$req['da_chiudere'] ?></b></span>
          <?php if ($req['sla_avg_min'] !== null): ?>
            <span class="chip stat">Tempo medio prima presa in carico: <b><?= number_format($req['sla_avg_min'],1,',','.') ?> min</b></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php if (!empty($req['by_status'])): ?>
    <div class="canvas-wrap short" style="margin-top:12px;"><canvas id="chartReqStatus"></canvas></div>
    <?php endif; ?>
    <?php if (!empty($req['sla_series'])): ?>
    <div class="canvas-wrap short" style="margin-top:12px;"><canvas id="chartReqSLA"></canvas></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- Data quality -->
  <section class="panel span-12">
    <h2><i class="fa-solid fa-wrench"></i> Data Quality</h2>
    <div class="chips" style="margin:6px 0 10px;">
      <span class="chip stat">Lavori: scadenza &lt; ricezione: <b><?= (int)$qc['scad_before_recv'] ?></b></span>
      <span class="chip stat">Preventivi: totale_con_iva &lt; totale: <b><?= (int)$qc['prev_tot_incoh'] ?></b></span>
      <span class="chip stat">Articoli: categorie senza match: <b><?= (int)$qc['art_cat_orfani'] ?></b></span>
    </div>
  </section>

</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<script>
// ===== Global Chart "soft" look (solo arrotondato in alto / a destra) =====
Chart.defaults.elements.bar.borderRadius = 0;
Chart.defaults.elements.bar.borderSkipped = 'bottom';
Chart.defaults.datasets.bar.maxBarThickness = 36;
Chart.defaults.plugins.legend.labels.boxWidth = 14;
Chart.defaults.plugins.legend.labels.boxHeight = 14;
Chart.defaults.font.size = 12;

// ===== Helpers: palette RGBA compatibile =====
const BASE_PASTELS = [
  [99,102,241],[16,185,129],[59,130,246],[234,179,8],
  [251,113,133],[139,92,246],[20,184,166],[245,158,11],
  [236,72,153],[34,197,94],[2,132,199],[168,85,247],
];
function rgba(rgb, a){ return `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${a})`; }
function palette(n, a = 0.65){
  const out = [];
  for (let i=0;i<n;i++) out.push(rgba(BASE_PASTELS[i % BASE_PASTELS.length], a));
  return out;
}
function softHorizontalBarStyle(){
  return {
    borderRadius: { topLeft: 0, bottomLeft: 0, topRight: 8, bottomRight: 8 },
    borderSkipped: 'left'
  };
}

const LABELS = <?= $labelsJson ?>;

// ===== Libri =====
(() => {
  const c = palette(1)[0];
  new Chart(document.getElementById('chartLibriMese'), {
    type:'bar',
    data:{ labels: LABELS, datasets:[{ label:'Libri inseriti', data: <?= json_encode($libriPerMese) ?>, backgroundColor: c }]},
    options:{
      responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
      plugins:{ title:{ display:true, text:'Libri inseriti per mese (ultimi 12)' } }
    }
  });
})();
new Chart(document.getElementById('chartLibriCat'), {
  type:'doughnut',
  data:{ labels: <?= json_encode($libriTopCatLabels,JSON_UNESCAPED_UNICODE) ?>,
         datasets:[{ data: <?= json_encode($libriTopCatValues) ?>, backgroundColor: palette(<?= max(1,count($libriTopCatLabels)) ?>, 0.8), borderWidth:0 }]},
  options:{
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Top categorie libri' } }
  }
});
(function(){
  const labels = <?= json_encode($libriTopEdLabels,JSON_UNESCAPED_UNICODE) ?>;
  const values = <?= json_encode($libriTopEdValues) ?>;
  const colors = palette(labels.length, 0.65);
  new Chart(document.getElementById('chartLibriEditori'), {
    type:'bar',
    data:{ labels, datasets:[{
      label:'Titoli', data: values, backgroundColor: colors, ...softHorizontalBarStyle()
    }]},
    options:{
      responsive:true, maintainAspectRatio:false, indexAxis:'y',
      scales:{ x:{ beginAtZero:true } },
      plugins:{ title:{ display:true, text:'Libri per casa editrice (Top)' }, legend:{ display:false } }
    }
  });
})();

// ===== Articoli =====
(() => {
  const c = palette(1)[0];
  new Chart(document.getElementById('chartArticoliMese'), {
    type:'bar',
    data:{ labels: LABELS, datasets:[{ label:'Articoli pubblicati', data: <?= json_encode($artPerMese) ?>, backgroundColor: c }]},
    options:{
      responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
      plugins:{ title:{ display:true, text:'Articoli pubblicati per mese (ultimi 12)' } }
    }
  });
})();
new Chart(document.getElementById('chartArticoliCat'), {
  type:'pie',
  data:{ labels: <?= json_encode($artTopCatLabels,JSON_UNESCAPED_UNICODE) ?>,
         datasets:[{ data: <?= json_encode($artTopCatValues) ?>, backgroundColor: palette(<?= max(1,count($artTopCatLabels)) ?>, 0.8), borderWidth:0 }]},
  options:{
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Articoli per categoria (nomi reali)' } }
  }
});
// Mix categorie nel tempo — BAR impilate
(function(){
  const series = <?= json_encode($artMixSeries,JSON_UNESCAPED_UNICODE) ?>;
  const labels = LABELS;
  const keys = Object.keys(series);
  const colors = palette(keys.length, 0.65);
  new Chart(document.getElementById('chartArticoliMix'), {
    type:'bar',
    data:{ labels,
      datasets: keys.map((k,i)=>({label:k, data:series[k], stack:'artMix', backgroundColor: colors[i]}))
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      scales:{ y:{ beginAtZero:true, stacked:true }, x:{ stacked:true } },
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Mix categorie articoli (ultimi 12 mesi, impilato)' } }
    }
  });
})();

// ===== Lavori =====
const LAV_ST_SERIES = <?= json_encode($statusSeries) ?>;
(function(){
  const keys = Object.keys(LAV_ST_SERIES);
  const colors = palette(keys.length, 0.65);
  new Chart(document.getElementById('chartLavoriStati'), {
    type:'bar',
    data:{
      labels: LABELS,
      datasets: keys.map((k,i)=>({ label:k, data:LAV_ST_SERIES[k], stack:'st', backgroundColor: colors[i] }))
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      scales:{ x:{ stacked:true }, y:{ stacked:true, beginAtZero:true } },
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Lavori per stato (per mese, ultimi 12)' } }
    }
  });
})();
(function(){
  const rows = <?= json_encode($lavByPrio,JSON_UNESCAPED_UNICODE) ?>;
  const labels = rows.map(r => r.p);
  const data = rows.map(r => parseInt(r.n,10));
  new Chart(document.getElementById('chartLavoriPrio'), {
    type:'doughnut',
    data:{ labels, datasets:[{ data, backgroundColor: palette(labels.length, 0.8), borderWidth:0 }]},
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Distribuzione lavori per priorità' } } }
  });
})();
new Chart(document.getElementById('chartLeadTime'), {
  type:'line',
  data:{ labels: LABELS, datasets:[{ label:'Giorni medi', data: <?= json_encode($leadSeries) ?>, tension:.3 }]},
  options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
    plugins:{ title:{ display:true, text:'Lead time medio (ricezione → scadenza)' } } }
});
(function(){
  const creati = <?= json_encode($lavCreatiSeries) ?>;
  const chiusi = <?= json_encode($lavChiusiSeries) ?>;
  const scaduti = <?= json_encode($lavScadutiSeries) ?>;
  const colors = palette(3, 0.65);
  new Chart(document.getElementById('chartCreatiChiusiScaduti'), {
    type:'bar',
    data:{ labels: LABELS, datasets:[
      { label:'Creati', data: creati, backgroundColor: colors[0] },
      { label:'Chiusi', data: chiusi, backgroundColor: colors[1] },
      { label:'Scaduti', data: scaduti, backgroundColor: colors[2] }
    ]},
    options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Lavori: creati / chiusi / scaduti' } } }
  });
})();
(function(){
  const S = <?= json_encode($workloadSeries,JSON_UNESCAPED_UNICODE) ?>;
  const keys = Object.keys(S);
  const colors = palette(keys.length, 0.65);
  new Chart(document.getElementById('chartWorkloadUtente'), {
    type:'bar',
    data:{ labels: LABELS, datasets: keys.map((k,i)=>({label:k, data:S[k], stack:'w', backgroundColor: colors[i]})) },
    options:{ responsive:true, maintainAspectRatio:false,
      scales:{ x:{ stacked:true }, y:{ stacked:true, beginAtZero:true } },
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Workload per utente (top 8, ultimi 12 mesi)' } } }
  });
})();
<?php if (!empty($top5Names)): ?>
(function(){
  const CAT_S = <?= json_encode($catSeries) ?>;
  const colors = palette(Object.keys(CAT_S).length, 0.65);
  new Chart(document.getElementById('chartLavoriCatMonth'), {
    type:'bar',
    data:{ labels: LABELS, datasets: Object.keys(CAT_S).map((k,i)=>({ label:k, data:CAT_S[k], stack:'cat', backgroundColor:colors[i] })) },
    options:{
      responsive:true, maintainAspectRatio:false,
      scales:{ x:{ stacked:true }, y:{ stacked:true, beginAtZero:true } },
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Lavori: top 5 categorie per mese' } }
    }
  });
})();
<?php endif; ?>
new Chart(document.getElementById('chartAttThroughput'), {
  type:'bar',
  data:{ labels: LABELS, datasets:[{ label:'Attività completate', data: <?= json_encode($attCompSeries) ?>, backgroundColor: palette(1, 0.65)[0] }]},
  options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
    plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Throughput attività (completate/mese)' } } }
});
new Chart(document.getElementById('chartAttAvgDays'), {
  type:'line',
  data:{ labels: LABELS, datasets:[{ label:'Giorni medi', data: <?= json_encode($attAvgSeries) ?>, tension:.25 }]},
  options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
    plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Tempo medio completamento attività' } } }
});
(function(){
  const labels = <?= json_encode($lavProvLabels,JSON_UNESCAPED_UNICODE) ?>;
  const data = <?= json_encode($lavProvValues) ?>;
  new Chart(document.getElementById('chartLavoriProvenienza'), {
    type:'doughnut',
    data:{ labels, datasets:[{ data, backgroundColor: palette(labels.length, 0.8), borderWidth:0 }]},
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Provenienza lavori' } } }
  });
})();

// ===== Preventivi =====
(() => {
  const c = palette(1, 0.65)[0];
  new Chart(document.getElementById('chartPrevCount'), {
    type:'bar',
    data:{ labels: LABELS, datasets:[{ label:'Preventivi inviati', data: <?= json_encode($prevNPerMese) ?>, backgroundColor: c }]},
    options:{
      responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
      plugins:{ title:{ display:true, text:'Preventivi inviati (ultimi 12 mesi)' } }
    }
  });
})();
new Chart(document.getElementById('chartPrevTot'), {
  type:'line',
  data:{ labels: LABELS, datasets:[{ label:'Totale € (mese)', data: <?= json_encode($prevTotPerMese) ?>, tension:.3 }]},
  options:{
    responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
    plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Valore complessivo preventivi €/mese' } }
  }
});
(function(){
  const inviati = <?= json_encode($prevInviati) ?>;
  const accettati = <?= json_encode($prevAccettati) ?>;
  new Chart(document.getElementById('chartPrevConv'), {
    type:'line',
    data:{ labels: LABELS, datasets:[
      { label:'Inviati', data: inviati, tension:.2 },
      { label:'Accettati', data: accettati, tension:.2 }
    ]},
    options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Preventivi: Inviati vs Accettati' } } }
  });
})();
new Chart(document.getElementById('chartPrevRate'), {
  type:'bar',
  data:{ labels: LABELS, datasets:[{ label:'Conversione %', data: <?= json_encode($prevRate) ?>, backgroundColor: palette(1, 0.65)[0] }]},
  options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, ticks:{ callback:(v)=>v+'%' } } },
    plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Tasso di conversione preventivi' } } }
});
new Chart(document.getElementById('chartPrevAvg'), {
  type:'line',
  data:{ labels: LABELS, datasets:[{ label:'€ medio', data: <?= json_encode($prevAvgSeries) ?>, tension:.25 }]},
  options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
    plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Valore medio preventivo' } } }
});
(function(){
  const rows = <?= json_encode($prevByStatus,JSON_UNESCAPED_UNICODE) ?>;
  const labels = rows.map(r => r.s);
  const data = rows.map(r => parseInt(r.n,10));
  new Chart(document.getElementById('chartPrevStatus'), {
    type:'pie',
    data:{ labels, datasets:[{ data, backgroundColor: palette(labels.length, 0.8), borderWidth:0 }]},
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Preventivi per stato' } } }
  });
})();
(function(){
  const labels = <?= json_encode($topCliLabels,JSON_UNESCAPED_UNICODE) ?>;
  const data = <?= json_encode($topCliValues) ?>;
  new Chart(document.getElementById('chartPrevTopClienti'), {
    type:'bar',
    data:{ labels, datasets:[{
      label:'Valore €', data, backgroundColor: palette(1, 0.65)[0], ...softHorizontalBarStyle()
    }]},
    options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y', scales:{ x:{ beginAtZero:true } },
      plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Top 10 clienti per valore preventivi' } } }
  });
})();

// ===== Richieste =====
<?php if ($reqTable): ?>
(() => {
  const c = palette(1, 0.65)[0];
  new Chart(document.getElementById('chartReqMese'), {
    type:'bar',
    data:{ labels: LABELS, datasets:[{ label:'Richieste ricevute', data: <?= json_encode($req['per_mese']) ?>, backgroundColor: c }]},
    options:{
      responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
      plugins:{ title:{ display:true, text:'Richieste ricevute per mese (ultimi 12)' } }
    }
  });
})();
<?php if (!empty($req['by_status'])): ?>
(function(){
  const rows = <?= json_encode($req['by_status'],JSON_UNESCAPED_UNICODE) ?>;
  const labels = rows.map(r => (r.s || 'n/d'));
  const data = rows.map(r => parseInt(r.n,10));
  new Chart(document.getElementById('chartReqStatus'), {
    type:'bar',
    data:{ labels, datasets:[{
      label:'Richieste', data, backgroundColor: palette(labels.length, 0.65), ...softHorizontalBarStyle()
    }]},
    options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y', scales:{ x:{ beginAtZero:true } },
      plugins:{ legend:{ display:false }, title:{ display:true, text:'Funnel richieste per stato' } } }
  });
})();
<?php endif; ?>
<?php if (!empty($req['sla_series'])): ?>
new Chart(document.getElementById('chartReqSLA'), {
  type:'line',
  data:{ labels: LABELS, datasets:[{ label:'Minuti medi', data: <?= json_encode($req['sla_series']) ?>, tension:.25 }]},
  options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } },
    plugins:{ legend:{ position:'bottom' }, title:{ display:true, text:'Tempo medio alla prima presa in carico' } } }
});
<?php endif; ?>
<?php endif; ?>

// ====== RANGE GLOBALE & KPI DELTAS ======
function getCh(id){
  const el = document.getElementById(id);
  return el ? Chart.getChart(el) : null;
}

// Costruisco l'istantanea "full" una volta sola, dopo che i grafici sono stati creati
const _FULL = {
  labels: [...(<?= $labelsJson ?>)],
  libri: (getCh('chartLibriMese')?.data.datasets[0].data || []).slice(),
  articoli: (getCh('chartArticoliMese')?.data.datasets[0].data || []).slice(),
  lavoriStacks: (() => {
    const ch = getCh('chartLavoriStati');
    if(!ch) return [];
    const len = ch.data.labels.length;
    const out = new Array(len).fill(0);
    ch.data.datasets.forEach(ds => ds.data.forEach((v,i)=> out[i] += (v||0)));
    return out;
  })(),
  preventivi: (getCh('chartPrevCount')?.data.datasets[0].data || []).slice(),
};

// Util: calcolo variazione %
function fmtDelta(now, prev){
  if (prev === null || prev === undefined || prev <= 0) return {txt:'—', cls:''};
  const p = ((now - prev) / prev) * 100;
  const sign = p > 0 ? 'up' : (p < 0 ? 'down' : '');
  const txt = (p>0?'+':'') + p.toFixed(1) + '%';
  return {txt, cls:sign};
}

// Safe setter per evitare errori se l'elemento non esiste
function setTextIfExists(id, txt){
  const el = document.getElementById(id);
  if (el) el.textContent = txt;
}
function setDeltaIfExists(id, now, prev){
  const el = document.getElementById(id);
  if (!el) return;
  const d = fmtDelta(now, prev);
  el.textContent = d.txt;
  el.className = 'delta ' + d.cls;
}

function applyRange(range){
  let n;
  if(range === 'ytd'){
    const now = new Date();
    n = now.getMonth() + 1; // 1..12
    if (n > _FULL.labels.length) n = _FULL.labels.length;
  } else {
    n = parseInt(range,10) || 12;
  }
  function tail(arr){ return arr.slice(Math.max(0, arr.length - n)); }

  // reset/applica slice su tutti i grafici
  forEachChart(ch => {
    if(!ch?.data?.labels) return;

    // salva copia full la prima volta
    if(!ch._fullCopy){
      ch._fullCopy = {
        labels: ch.data.labels.slice(),
        datasets: ch.data.datasets.map(ds=>({...ds, data: Array.isArray(ds.data)? ds.data.slice(): ds.data}))
      };
    }else{
      // ripristina full
      ch.data.labels = ch._fullCopy.labels.slice();
      ch.data.datasets.forEach((ds,i)=> {
        const full = ch._fullCopy.datasets[i].data;
        ds.data = Array.isArray(full) ? full.slice() : full;
      });
    }

    // poi “tail” sugli ultimi n
    const L = ch.data.labels.length;
    ch.data.labels = ch.data.labels.slice(Math.max(0, L - n));
    (ch.data.datasets||[]).forEach(ds => {
      if (Array.isArray(ds.data)) {
        const len = ds.data.length;
        ds.data = ds.data.slice(Math.max(0, len - n));
      }
    });
    ch.update('none');
  });

  // KPI (solo se esistono in pagina)
  const nowLibri = tail(_FULL.libri).reduce((a,b)=>a+(b||0),0);
  const nowArt   = tail(_FULL.articoli).reduce((a,b)=>a+(b||0),0);
  const nowLav   = tail(_FULL.lavoriStacks).reduce((a,b)=>a+(b||0),0);
  const nowPrev  = tail(_FULL.preventivi).reduce((a,b)=>a+(b||0),0);

  function prevSum(arr){
    if(arr.length < (2*n)) return null;
    return arr.slice(arr.length - 2*n, arr.length - n).reduce((a,b)=>a+(b||0),0);
  }
  const pLibri = prevSum(_FULL.libri);
  const pArt   = prevSum(_FULL.articoli);
  const pLav   = prevSum(_FULL.lavoriStacks);
  const pPrev  = prevSum(_FULL.preventivi);

  setTextIfExists('kpiLibri', nowLibri);
  setTextIfExists('kpiArticoli', nowArt);
  setTextIfExists('kpiLavori', nowLav);
  setTextIfExists('kpiPrev', nowPrev);

  setDeltaIfExists('deltaLibri', nowLibri, pLibri);
  setDeltaIfExists('deltaArticoli', nowArt, pArt);
  setDeltaIfExists('deltaLavori', nowLav, pLav);
  setDeltaIfExists('deltaPrev', nowPrev, pPrev);
}

// Listener toolbar
document.querySelectorAll('.toolbar .btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('.toolbar .btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    applyRange(btn.dataset.range);
    // (opzionale) persistenza preferenza
    try { localStorage.setItem('rangePref', btn.dataset.range); } catch(_){}
  });
});

// Init: ripristina preferenza o 12
(function(){
  let def = '12';
  try { def = localStorage.getItem('rangePref') || '12'; } catch(_){}
  // sync stato pulsanti
  const found = document.querySelector(`.toolbar .btn[data-range="${def}"]`);
  if (found){
    document.querySelectorAll('.toolbar .btn').forEach(b=>b.classList.remove('active'));
    found.classList.add('active');
  }
  applyRange(def);
})();

// ===== Export CSV helper =====
function exportCSV(canvasId, filename){
  const ch = Chart.getChart(document.getElementById(canvasId));
  if (!ch) return;
  const labels = ch.data.labels || [];
  const ds = ch.data.datasets || [];
  let csv = 'Label,' + ds.map(d => `"${(d.label||'Serie')}"`).join(',') + '\n';
  for (let i=0;i<labels.length;i++){
    csv += `"${labels[i]}",` + ds.map(d => (Array.isArray(d.data) ? d.data[i] : '')).join(',') + '\n';
  }
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename || 'data.csv';
  a.click();
}
</script>
<script>
function exportPNG(canvasId, filename){
  const el = document.getElementById(canvasId);
  const ch = el ? Chart.getChart(el) : null;
  if (!ch) return;
  const url = ch.toBase64Image();
  const a = document.createElement('a');
  a.href = url;
  a.download = filename || (canvasId + '.png');
  a.click();
}
</script>
<script>
// Intl formatters
const fmtInt = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 0 });
const fmtPct = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 1 });
const fmtEUR = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

// Helper per capire se dataset è monetario (id naive per nome grafico/label)
function isEuroDataset(ds, chart){
  const t = (ds.label || chart?.options?.plugins?.title?.text || '').toLowerCase();
  return t.includes('€') || t.includes('euro') || t.includes('valore') || t.includes('totale');
}

// Imposta callback globali per assi e tooltip
Chart.defaults.plugins.tooltip.callbacks = Chart.defaults.plugins.tooltip.callbacks || {};
Chart.defaults.plugins.tooltip.callbacks.label = function(ctx){
  const raw = ctx.raw ?? 0;
  const val = (isEuroDataset(ctx.dataset, ctx.chart)) ? fmtEUR.format(raw) : fmtInt.format(raw);
  return `${ctx.dataset.label || ''}: ${val}`;
};

// Tick Y di default con migliaia (se non euro)
const oldScaleY = Chart.defaults.scales?.y || {};
Chart.defaults.scales = Chart.defaults.scales || {};
Chart.defaults.scales.y = {
  ...oldScaleY,
  ticks: {
    ...(oldScaleY.ticks || {}),
    callback: function(v){ return fmtInt.format(v); }
  }
};
</script>
<script>
Chart.register({
  id: 'noDataLabel',
  afterDraw(chart, args, options){
    const ds = chart.data?.datasets || [];
    if (!ds.length) return;
    let total = 0, hasNum = false;
    ds.forEach(s => (Array.isArray(s.data) ? s.data : []).forEach(v => { if (Number.isFinite(v)) { hasNum = true; total += v; } }));
    if (!hasNum || total === 0){
      const {ctx, chartArea} = chart;
      const txt = options?.text || 'Nessun dato';
      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = '600 16px system-ui, -apple-system, Segoe UI, Roboto';
      ctx.fillStyle = '#94a3b8';
      ctx.fillText(txt, (chartArea.left + chartArea.right)/2, (chartArea.top + chartArea.bottom)/2);
      ctx.restore();
    }
  }
});
</script>

</body>
</html>
