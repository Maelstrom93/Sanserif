<?php
// file: assets/funzioni/funzioni_dashboard.php

require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/funzioni.php'; // usa tableExists, tableHasColumn, formattaData, currentUserAssigneeAliases, ecc.

/** safe-escape */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/**
 * Prossima scadenza assegnata a ME (match su alias nome/email/id) dagli eventi.
 * Ritorna array ['nome'=>..., 'data_evento'=>..., 'assegnato_a'=>...] o [].
 */
function nextDeadlineForMe(mysqli $conn): array {
  $aliases = currentUserAssigneeAliases();
  if (!$aliases) return [];
  $norm = array_map(function($a){ return mb_strtolower(trim((string)$a),'UTF-8'); }, $aliases);
  if (!$norm) return [];
  $place = implode(',', array_fill(0, count($norm), '?'));
  $types = str_repeat('s', count($norm));
  $sql = "
    SELECT nome, data_evento, assegnato_a
    FROM flusso_lavoro
    WHERE data_evento >= NOW()
      AND LOWER(TRIM(COALESCE(assegnato_a,''))) IN ($place)
    ORDER BY data_evento ASC
    LIMIT 1
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$norm);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc() ?: [];
  $stmt->close();
  return $row ?: [];
}

/**
 * Lavori per categoria nel mese corrente (conteggio)
 * Ritorna mappa ['Categoria' => count]
 */
function lavoriPerCategoriaMese(mysqli $conn): array {
  if (!tableExists($conn,'lavori')) return [];
  $start = (new DateTimeImmutable('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
  $end   = (new DateTimeImmutable('last day of this month 23:59:59'))->format('Y-m-d H:i:s');

  if (tableExists($conn,'lavori_categorie') && tableExists($conn,'categorie_lavoro')) {
    $sql = "
      SELECT cl.nome AS k, COUNT(*) AS c
      FROM lavori l
      JOIN lavori_categorie lc ON lc.lavoro_id = l.id
      JOIN categorie_lavoro cl ON cl.id = lc.categoria_id
      WHERE l.data_ricezione BETWEEN ? AND ?
      GROUP BY cl.id
      ORDER BY c DESC, cl.nome ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start, $end);
  } else {
    // Fallback legacy: colonna libera su lavori (se presente)
    $hasCol = tableHasColumn($conn, 'lavori', 'categoria');
    if (!$hasCol) return [];
    $sql = "
      SELECT COALESCE(NULLIF(TRIM(categoria),''),'—') AS k, COUNT(*) AS c
      FROM lavori
      WHERE data_ricezione BETWEEN ? AND ?
      GROUP BY k
      ORDER BY c DESC, k ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start, $end);
  }

  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while ($r = $res->fetch_assoc()) {
    $k = (string)($r['k'] ?? '');
    if ($k !== '') $out[$k] = (int)$r['c'];
  }
  $stmt->close();
  return $out;
}


/* ======================= Workload per utente (SOLO lavori_attivita) ======================= */

/** Carico lavori per utente (CONTA RIGHE: COUNT(*)) + scadenza più vicina */
function caricoLavoriPerUtente(mysqli $conn): array {
  if (!tableExists($conn,'lavori_attivita')) return [];

  $sql = "
    SELECT
      la.utente_id                                AS uid,
      MIN(COALESCE(NULLIF(TRIM(u.nome),''), NULLIF(TRIM(u.email),''), CONCAT('#',u.id))) AS label,
      COUNT(*)                                     AS tot,
      MIN(CASE WHEN la.scadenza IS NOT NULL AND la.scadenza <> '0000-00-00' AND la.scadenza >= CURDATE()
               THEN la.scadenza END)               AS next_future,
      MIN(CASE WHEN la.scadenza IS NOT NULL AND la.scadenza <> '0000-00-00'
               THEN la.scadenza END)               AS next_any
    FROM lavori_attivita la
    LEFT JOIN utenti u ON u.id = la.utente_id
    WHERE la.utente_id IS NOT NULL
    GROUP BY la.utente_id
    ORDER BY tot DESC, label ASC
  ";
  $res = $conn->query($sql);
  if (!$res) return [];

  $rows = [];
  while ($r = $res->fetch_assoc()) {
    $next = $r['next_future'] ?: ($r['next_any'] ?: null);
    $rows[] = [
      'uid'   => (int)$r['uid'],
      'utente'=> (string)($r['label'] ?: ('#'.(int)$r['uid'])),
      'tot'   => (int)$r['tot'],
      'next'  => $next ? formattaData((string)$next) : '—'
    ];
  }
  return $rows;
}

/**
 * Categoria più assegnata per utente (dalle righe attività).
 * Ritorna mappa: [uid => [['cat' => nome, 'c' => count]]]
 */
function topCategoriePerUtente(mysqli $conn, int $topN = 1): array {
  if (!tableExists($conn,'lavori_attivita')) return [];

  $joinCat = tableExists($conn,'categorie_lavoro')
    ? "LEFT JOIN categorie_lavoro cl ON cl.id = la.categoria_id"
    : "LEFT JOIN (SELECT NULL AS id, '—' AS nome) cl ON 1=1";

  $sql = "
    SELECT
      la.utente_id                           AS uid,
      COALESCE(NULLIF(TRIM(cl.nome),''),'—') AS cat,
      COUNT(*)                                AS c
    FROM lavori_attivita la
    $joinCat
    WHERE la.utente_id IS NOT NULL
    GROUP BY la.utente_id, cat
  ";
  $res = $conn->query($sql);
  if (!$res) return [];

  $map = [];
  while ($r = $res->fetch_assoc()) {
    $uid = (int)$r['uid'];
    if (!isset($map[$uid])) $map[$uid] = [];
    $map[$uid][] = ['cat'=>(string)$r['cat'], 'c'=>(int)$r['c']];
  }

  foreach ($map as $uid => &$arr) {
    usort($arr, function($a,$b){ return $b['c'] <=> $a['c'] ?: strcasecmp($a['cat'],$b['cat']); });
    $arr = array_slice($arr, 0, max(1,$topN));
  }
  unset($arr);

  return $map;
}


/** Wrapper: prepara tutti i dati per la dashboard in un solo posto */
function buildDashboardData(mysqli $conn, array $session): array {
  $vm   = buildDashboardViewModel($conn);

  // 7 giorni "intelligenti"
  $oggi = date('Y-m-d');
  $fine = date('Y-m-d', strtotime('+6 days')); // 7 giorni inclusi

  // Eventi nella finestra 7g da oggi
  $eventi = getEventiFlussoLavoro($conn, $oggi, $fine);

  // Se vuoto, sposta la finestra sul prossimo evento futuro e mostra 7 giorni da lì
  if (empty($eventi)) {
    $row = $conn->query("SELECT MIN(data_evento) AS prox FROM flusso_lavoro WHERE data_evento >= CURDATE()")
                ->fetch_assoc();
    $prox = $row['prox'] ?? null;
    if (!empty($prox)) {
      $oggi = (new DateTimeImmutable($prox))->format('Y-m-d');
      $fine = (new DateTimeImmutable($prox))->modify('+6 days')->format('Y-m-d');
      $eventi = getEventiFlussoLavoro($conn, $oggi, $fine);
    }
  }

  // JSON sicuro per l'attributo data-eventi
  $eventiJson = e(json_encode($eventi, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

  // Lavori per categoria (mese)
  $catMonthMap  = lavoriPerCategoriaMese($conn);
  $catMonthJson = e(json_encode($catMonthMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

  // Prossima scadenza "per me"
  $nextMine = nextDeadlineForMe($conn);
  $nextMineLabel = $nextMine
    ? (formattaData((string)$nextMine['data_evento']).' - '.(string)$nextMine['nome'])
    : '—';

  // KPI: lavori assegnati a me (righe in lavori_attivita)
  $lavoriAssegnatiAMe = 0;
  $myId = isset($session['utente']['id']) ? (int)$session['utente']['id'] : 0;
  if ($myId && tableExists($conn,'lavori_attivita')) {
    $sql = "SELECT COUNT(*) AS c FROM lavori_attivita la WHERE la.utente_id = ?";
    if ($st = $conn->prepare($sql)) {
      $st->bind_param('i', $myId);
      $st->execute();
      $row = $st->get_result()->fetch_assoc();
      $lavoriAssegnatiAMe = (int)($row['c'] ?? 0);
      $st->close();
    }
  }

  // Altri aggregati
  $ultime3        = array_slice($vm['ultime'] ?? [], 0, 3);
  $totLibri       = (int)($vm['stats']['totLibri'] ?? 0);
  $richiesteMese  = (int)($vm['stats']['richiesteMese'] ?? 0);
  $workloadLavori = caricoLavoriPerUtente($conn);
  $topCatByUser   = topCategoriePerUtente($conn, 1);

  return [
    'oggi'               => $oggi,
    'fine'               => $fine,
    'eventiJson'         => $eventiJson,
    'catMonthJson'       => $catMonthJson,
    'nextMineLabel'      => $nextMineLabel,
    'ultime3'            => $ultime3,
    'totLibri'           => $totLibri,
    'lavoriAssegnatiAMe' => $lavoriAssegnatiAMe,
    'richiesteMese'      => $richiesteMese,
    'workloadLavori'     => $workloadLavori,
    'topCatByUser'       => $topCatByUser,
    'vm'                 => $vm,
  ];
}

