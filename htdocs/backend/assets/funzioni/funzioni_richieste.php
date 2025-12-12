<?php
/**
 * Funzioni helper per la pagina "Richieste".
 * Dipendenze:
 *  - $conn: istanza mysqli valida
 *  - contact_requests.php deve esportare cr_list($conn, $filters, $page, $perPage)
 */

// Safe escape
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Utenti assegnabili (prima da `utenti.nome`, fallback a DISTINCT assigned_to).
 */
function richieste_load_assignable_users(mysqli $conn): array {
  $users = [];
  try {
    if ($res = $conn->query("SELECT DISTINCT nome FROM utenti WHERE nome<>'' ORDER BY nome ASC")) {
      while ($row = $res->fetch_assoc()) $users[] = (string)$row['nome'];
      $res->close();
    }
  } catch (Throwable $e) { /* ignore */ }

  if (!$users) {
    try {
      $sql = "SELECT DISTINCT assigned_to AS nome
              FROM contact_requests
              WHERE assigned_to IS NOT NULL AND assigned_to<>'' 
              ORDER BY assigned_to ASC";
      if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) $users[] = (string)$row['nome'];
        $res->close();
      }
    } catch (Throwable $e) { /* ignore */ }
  }

  // unique + trim
  $users = array_values(array_unique(array_map('trim', $users)));
  return $users;
}

/**
 * Costruisce i filtri a partire da $_GET (o da un array analogo).
 * Ritorna:
 * - filtersDb: filtri che si possono passare a cr_list
 * - assignedTo: string
 * - hasMultiStatus: bool
 * - statuses: array<string> (se multi)
 */
function richieste_build_filters(array $src): array {
  $q           = trim((string)($src['q'] ?? ''));
  $statusParam = trim((string)($src['status'] ?? ''));
  $assignedTo  = trim((string)($src['assigned_to'] ?? ''));

  $filtersDb = [];
  if ($q !== '') $filtersDb['q'] = $q;

  $hasMultiStatus = (strpos($statusParam, ',') !== false);
  if (!$hasMultiStatus && $statusParam !== '') $filtersDb['status'] = $statusParam;

  $statuses = [];
  if ($hasMultiStatus) {
    $statuses = array_filter(array_map('trim', explode(',', $statusParam)));
    $statuses = array_map('strtolower', $statuses);
  }

  return [
    'filtersDb'      => $filtersDb,
    'assignedTo'     => $assignedTo,
    'hasMultiStatus' => $hasMultiStatus,
    'statuses'       => $statuses,
  ];
}

/**
 * Recupera l’elenco richieste con eventuale filtraggio in memoria (multi-status/assigned_to).
 * Ritorna: ['items'=>array, 'total'=>int, 'pages'=>int, 'page'=>int]
 */
function richieste_list(mysqli $conn, array $filtersDb, string $assignedTo, array $statuses, int $page = 1, int $perPage = 20): array {
  $useInMemory = ($assignedTo !== '') || !empty($statuses);

  if ($useInMemory) {
    // carica "tanto" e filtra lato PHP
    $raw   = cr_list($conn, $filtersDb, 1, 2000);  // limite di sicurezza
    $items = $raw['items'] ?? [];

    if (!empty($statuses)) {
      $items = array_values(array_filter($items, function($r) use ($statuses){
        $st = strtolower((string)($r['status'] ?? ''));
        return in_array($st, $statuses, true);
      }));
    }

    if ($assignedTo !== '') {
      $needle = mb_strtolower($assignedTo, 'UTF-8');
      $items = array_values(array_filter($items, function($r) use ($needle){
        $val = mb_strtolower((string)($r['assigned_to'] ?? ''), 'UTF-8');
        return $val !== '' && ( $val === $needle || mb_strpos($val, $needle) !== false );
      }));
    }

    $total  = count($items);
    $pages  = max(1, (int)ceil($total / $perPage));
    $offset = max(0, ($page - 1) * $perPage);
    $slice  = array_slice($items, $offset, $perPage);

    return ['items'=>$slice, 'total'=>$total, 'pages'=>$pages, 'page'=>$page];
  }

  // delega a cr_list per paginazione DB
  $list  = cr_list($conn, $filtersDb, $page, $perPage);
  $total = (int)($list['total'] ?? 0);
  $pages = max(1, (int)ceil($total / $perPage));

  return [
    'items' => $list['items'] ?? [],
    'total' => $total,
    'pages' => $pages,
    'page'  => $page,
  ];
}

/** Mapping stato -> etichetta italiana */
function richieste_it_status(string $s): string {
  return [
    'new'        => 'Nuova',
    'in_review'  => 'In revisione',
    'replied'    => 'Risposta inviata',
    'closed'     => 'Chiusa',
  ][$s] ?? $s;
}

/** Classe chip per stato */
function richieste_status_chip_class(string $s): string {
  $s = strtolower($s);
  if ($s === 'new')        return 'st-new';
  if ($s === 'in_review')  return 'st-inrev';
  if ($s === 'replied')    return 'st-replied';
  if ($s === 'closed')     return 'st-closed';
  return '';
}

/**
 * Costruisce una querystring preservando i filtri correnti, sovrascrivendo con $params.
 * Uso: href="?<?= richieste_qs(['page'=>2]) ?>"
 */
function richieste_qs(array $params): string {
  $base = [
    'q'           => $_GET['q'] ?? '',
    'status'      => $_GET['status'] ?? '',
    'assigned_to' => $_GET['assigned_to'] ?? '',
    'page'        => null,
  ];
  foreach ($params as $k=>$v) $base[$k] = $v;
  $clean = [];
  foreach ($base as $k=>$v) if ($v !== null) $clean[$k] = $v;
  return http_build_query($clean);
}
