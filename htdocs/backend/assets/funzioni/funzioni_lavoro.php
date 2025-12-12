<?php
// funzioni_lavoro.php
// Helpers e logica estratta da index_lavori.php
// Dipendenze esterne già esistenti: assets/funzioni/db/db.php, assets/funzioni/funzioni.php, assets/funzioni/csrf.php
// ATTENZIONE: non ridefinire requireLogin(); viene chiamato in index_lavori.php

/* ==== Helpers generali ==== */

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money_it($v){
  if ($v === null || $v === '') return '';
  return number_format((float)$v, 2, ',', '.').' €';
}

/**
 * Costruisce URL preservando i GET correnti, con override/rimozioni dai $extra (null = rimuovi)
 */
function buildUrl(array $extra = []): string {
  $qs = $_GET;
  foreach ($extra as $k => $v) {
    if ($v === null) unset($qs[$k]); else $qs[$k] = $v;
  }
  $q = http_build_query($qs);
  return 'index_lavori.php' . ($q ? ('?'.$q) : '');
}

/* ==== Helper esecuzione query ==== */
function runQuery(mysqli $conn, string $sql, string $types, array $bind) {
  if (!empty($bind)) {
    $st = $conn->prepare($sql);
    if ($st === false) return false;
    // Per evitare "ArgumentCountError" quando non ci sono bind, controlliamo:
    if ($types !== '' && count($bind) > 0) {
      $st->bind_param($types, ...$bind);
    }
    $st->execute();
    $res = $st->get_result();
    $st->close();
    return $res;
  }
  return $conn->query($sql);
}

/* Conta scalare semplice */
function scalarCount(mysqli $conn, string $sql, string $types, array $bind): int {
  $res = runQuery($conn, $sql, $types, $bind);
  if (!$res) return 0;
  $row = $res->fetch_row();
  return (int)($row[0] ?? 0);
}

/* ==== Costruzione select lists (clienti / utenti / categorie) ==== */
function getSelectLists(mysqli $conn): array {
  $clienti = [];
  if (tableExists($conn,'clienti')) {
    $colNome = tableHasColumn($conn,'clienti','nome') ? 'nome' : (tableHasColumn($conn,'clienti','rgs') ? 'rgs' : null);
    if ($colNome) {
      $resC = $conn->query("SELECT id, TRIM($colNome) AS nome FROM clienti ORDER BY $colNome ASC");
      if ($resC) {
        while($c = $resC->fetch_assoc()) {
          $clienti[] = [
            'id'   => (int)$c['id'],
            'nome' => $c['nome'] ?: ('Cliente #'.$c['id']),
            // per compat compat: label = nome
            'label'=> $c['nome'] ?: ('Cliente #'.$c['id']),
          ];
        }
      }
    }
  }

  $utenti = [];
  if (tableExists($conn,'utenti')) {
    $resU = $conn->query("SELECT id, TRIM(COALESCE(nome,'')) AS nome, TRIM(COALESCE(email,'')) AS email FROM utenti ORDER BY nome, email");
    if ($resU) {
      while ($u = $resU->fetch_assoc()) {
        $label = $u['nome'] !== '' ? $u['nome'] : ($u['email'] !== '' ? $u['email'] : ('#'.$u['id']));
        $utenti[] = ['id'=>(int)$u['id'], 'label'=>$label];
      }
    }
  }

  // Funzione già esistente nel progetto
$categorie = [];
if (tableExists($conn,'categorie_lavoro')) {
  $rs = $conn->query("SELECT id, nome FROM categorie_lavoro ORDER BY nome ASC");
  if ($rs) while($r=$rs->fetch_assoc()) $categorie[] = ['id'=>(int)$r['id'],'nome'=>$r['nome']];
}

  return [$clienti, $utenti, $categorie];
}

/* ==== Parsing filtri GET ==== */
function parseFiltri(array $session, array $get): array {
  $myId = isset($session['utente']['id']) ? (int)$session['utente']['id'] : 0;

  $qTitolo = isset($get['q']) ? trim((string)$get['q']) : '';

  $fUtente = null;
  if (isset($get['utente_id']) && $get['utente_id'] !== '' && is_numeric($get['utente_id'])) {
    $fUtente = (int)$get['utente_id'];
  }

  $fCliente = null;
  if (isset($get['cliente_id']) && $get['cliente_id'] !== '' && is_numeric($get['cliente_id'])) {
    $fCliente = (int)$get['cliente_id'];
  }

  $fCat = null;
  if (isset($get['categoria_id']) && $get['categoria_id'] !== '' && is_numeric($get['categoria_id'])) {
    $fCat = (int)$get['categoria_id'];
  }

  $fDa = isset($get['scad_da']) ? trim((string)$get['scad_da']) : '';
  $fA  = isset($get['scad_a'])  ? trim((string)$get['scad_a']) : '';

  $assignedToMe = ($myId > 0) && isset($get['assigned_to_me']) && $get['assigned_to_me'] == '1';

  // CHIP: stato=chiusi abilita filtro su completati/chiusi
  $onlyClosed = (isset($get['stato']) && $get['stato'] === 'chiusi');

  // se sto guardando i “Completati”, NON applico i filtri per assegnatari
  $applyAssigneeFilter = !$onlyClosed && ($assignedToMe || $fUtente !== null);

  // Ordinamento + paginazione
  $sort = isset($get['sort']) ? (string)$get['sort'] : 'ricezione_desc';
  $sortMap = [
    'ricezione_desc' => 'l.id DESC',
    'scadenza_asc'   => 'l.scadenza IS NULL, l.scadenza ASC, l.id DESC',
    'prezzo_desc'    => 'l.prezzo IS NULL, l.prezzo DESC, l.id DESC',
    'titolo_asc'     => 'l.titolo ASC, l.id DESC',
  ];
  $orderSql = $sortMap[$sort] ?? $sortMap['ricezione_desc'];

  $page = max(1, (int)($get['page'] ?? 1));
  $perPage = min(30, max(6, (int)($get['per_page'] ?? 18)));
  $offset  = ($page-1) * $perPage;

  return compact(
    'myId','qTitolo','fUtente','fCliente','fCat','fDa','fA',
    'assignedToMe','onlyClosed','applyAssigneeFilter',
    'sort','orderSql','page','perPage','offset'
  );
}

/* ==== Costruzione WHERE dinamico e liste ==== */
function buildWhereAndLists(mysqli $conn, array $filtri): array {
  extract($filtri);

  $where = [];
  $bind  = [];
  $types = '';
/* === RBAC SCOPE: restringi i risultati in base ai permessi ===
   - lavori.view_all            → nessun filtro extra (LV1/LV2/Admin)
   - lavori.view_assigned_only  → solo i lavori assegnati a me (macro o nelle attività) (LV3)
   - nessuno dei due            → blocca tutto (safety net)
*/
if (function_exists('currentUserCan')) {
  $uid = $myId ?? ($_SESSION['utente']['id'] ?? null);

  if (currentUserCan('lavori.view_all')) {
    // nessun filtro extra
  } elseif (currentUserCan('lavori.view_assigned_only')) {
    if (tableExists($conn,'lavori_attivita')) {
      $where[]  = "(l.assegnato_a = ? OR EXISTS(
                      SELECT 1 FROM lavori_attivita la
                       WHERE la.lavoro_id = l.id
                         AND la.utente_id = ?
                   ))";
      $bind[]   = (int)$uid; $types .= 'i';
      $bind[]   = (int)$uid; $types .= 'i';
    } else {
      $where[]  = "l.assegnato_a = ?";
      $bind[]   = (int)$uid; $types .= 'i';
    }
  } else {
    // nessun permesso di view: blocca tutto
    $where[] = "0=1";
  }
} else {
  // se per qualche motivo currentUserCan non è caricato, meglio chiudere
  $where[] = "0=1";
}

  if ($qTitolo !== '') { $where[] = "l.titolo LIKE ?"; $bind[] = '%'.$qTitolo.'%'; $types .= 's'; }
  if ($fCliente !== null) { $where[] = "l.cliente_id = ?"; $bind[] = $fCliente; $types .= 'i'; }

  if ($fCat !== null) {
    if (tableExists($conn,'lavori_categorie') && tableExists($conn,'lavori_attivita')) {
      $where[] = "(
        EXISTS(SELECT 1 FROM lavori_categorie lc WHERE lc.lavoro_id = l.id AND lc.categoria_id = ?)
        OR
        EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id = l.id AND la.categoria_id = ?)
      )";
      $bind[] = $fCat; $types .= 'i';
      $bind[] = $fCat; $types .= 'i';
    } elseif (tableExists($conn,'lavori_categorie')) {
      $where[] = "EXISTS(SELECT 1 FROM lavori_categorie lc WHERE lc.lavoro_id = l.id AND lc.categoria_id = ?)";
      $bind[] = $fCat; $types .= 'i';
    } elseif (tableExists($conn,'lavori_attivita')) {
      $where[] = "EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id = l.id AND la.categoria_id = ?)";
      $bind[] = $fCat; $types .= 'i';
    }
  }

  if ($applyAssigneeFilter) {
    $uid = $assignedToMe ? $myId : $fUtente;
    if ($uid !== null) {
      if (tableExists($conn,'lavori_attivita')) {
        $where[] = "(l.assegnato_a = ? OR EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id = l.id AND la.utente_id = ?))";
        $bind[] = $uid; $types .= 'i';
        $bind[] = $uid; $types .= 'i';
      } else {
        $where[] = "l.assegnato_a = ?"; $bind[] = $uid; $types .= 'i';
      }
    }
  }

  if ($fDa !== '' || $fA !== '') {
    if (tableExists($conn,'lavori_attivita')) {
      if ($fDa !== '') { $where[] = "((l.scadenza >= ?) OR EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id = l.id AND la.scadenza >= ?))"; $bind[]=$fDa; $types.='s'; $bind[]=$fDa; $types.='s'; }
      if ($fA  !== '') { $where[] = "((l.scadenza <= ?) OR EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id = l.id AND la.scadenza <= ?))"; $bind[]=$fA;  $types.='s'; $bind[]=$fA;  $types.='s'; }
    } else {
      if ($fDa !== '') { $where[] = "l.scadenza >= ?"; $bind[]=$fDa; $types.='s'; }
      if ($fA  !== '') { $where[] = "l.scadenza <= ?"; $bind[]=$fA;  $types.='s'; }
    }
  }

  // WHERE base (senza stato) per conteggi
  $WHERE_SQL_BASE = $where ? ('WHERE '.implode(' AND ', $where)) : '';

  // Applica eventuale filtro stato per le liste (solo chiusi/completati)
  $whereLists = $where;
  $bindLists  = $bind;
  $typesLists = $types;
  if ($onlyClosed) { $whereLists[] = "(l.stato IN ('completato','chiuso'))"; }
  $WHERE_SQL = $whereLists ? ('WHERE '.implode(' AND ', $whereLists)) : '';

  /* ==== Aggregati per card ==== */
  $joinCliente = tableExists($conn,'clienti') ? "
    , (SELECT TRIM(COALESCE(nome, ".(tableHasColumn($conn,'clienti','rgs')?'rgs, ':'')." CONCAT('Cliente #', c.id)))
         FROM clienti c WHERE c.id = l.cliente_id LIMIT 1) AS cliente_nome
  " : "";

// SOLO le categorie correnti, lette dalle lavorazioni
$joinCategorie = (tableExists($conn,'lavori_attivita') && tableExists($conn,'categorie_lavoro')) ? "
  , (
      SELECT GROUP_CONCAT(DISTINCT clv.nome ORDER BY clv.nome SEPARATOR ', ')
        FROM lavori_attivita la
        JOIN categorie_lavoro clv ON clv.id = la.categoria_id
       WHERE la.lavoro_id = l.id
         AND la.categoria_id IS NOT NULL
    ) AS categorie
" : "";



  // === AGGIUNTO: conteggio attività completate (att_done) ===
  $aggAttivita = tableExists($conn,'lavori_attivita') ? "
    , (SELECT COUNT(*) FROM lavori_attivita la WHERE la.lavoro_id = l.id) AS att_count
    , (SELECT COUNT(DISTINCT la.utente_id) FROM lavori_attivita la WHERE la.lavoro_id = l.id AND la.utente_id IS NOT NULL) AS assignees
    , (SELECT CAST(SUM(la.prezzo) AS DECIMAL(10,2)) FROM lavori_attivita la WHERE la.lavoro_id = l.id) AS sum_righe
    , (SELECT COUNT(*) FROM lavori_attivita la WHERE la.lavoro_id = l.id AND (la.completata = 1 OR la.completata = '1')) AS att_done
  " : ", 0 AS att_count, 0 AS assignees, NULL AS sum_righe, 0 AS att_done";

  return compact(
    'WHERE_SQL_BASE','WHERE_SQL','bind','types','bindLists','typesLists',
    'joinCliente','joinCategorie','aggAttivita'
  );
}

/* ==== Query principali + conteggi ==== */
function getListeLavori(mysqli $conn, array $filtri): array {
  extract($filtri);

  $pieces = buildWhereAndLists($conn, $filtri);
  extract($pieces);

  $sqlArchivio = "
    SELECT
      l.id, l.titolo, l.cliente_id, l.assegnato_a, l.data_ricezione, l.scadenza,
      l.prezzo, l.provenienza, l.descrizione, l.stato, l.cartelle_json, l.priorita, l.checklist_json
      $joinCliente
      $joinCategorie
      $aggAttivita
    FROM lavori l
    $WHERE_SQL
    ORDER BY $orderSql
    LIMIT $perPage OFFSET $offset
  ";
  $tutti = runQuery($conn, $sqlArchivio, $typesLists, $bindLists);

  $sqlUltimi = "
    SELECT
      l.id, l.titolo, l.cliente_id, l.assegnato_a, l.data_ricezione, l.scadenza,
      l.prezzo, l.provenienza, l.descrizione, l.stato, l.priorita, l.checklist_json
      $joinCliente
      $joinCategorie
      $aggAttivita
    FROM lavori l
    $WHERE_SQL
    ORDER BY $orderSql
    LIMIT 3
  ";
  $ultimi = runQuery($conn, $sqlUltimi, $typesLists, $bindLists);

  $errSql = (!$ultimi || !$tutti) ? $conn->error : '';

  // Conteggi coerenti coi filtri attivi (escluso 'stato')
  $sqlCountBase   = "SELECT COUNT(*) FROM lavori l $WHERE_SQL_BASE";
  $sqlCountClosed = $sqlCountBase . ( $WHERE_SQL_BASE ? " AND " : " WHERE " ) . "(l.stato IN ('completato','chiuso'))";
  $sqlCountOpen   = $sqlCountBase . ( $WHERE_SQL_BASE ? " AND " : " WHERE " ) . "(l.stato NOT IN ('completato','chiuso'))";

  $cntAll    = scalarCount($conn, $sqlCountBase,   $types, $bind);
  $cntClosed = scalarCount($conn, $sqlCountClosed, $types, $bind);
  $cntOpen   = scalarCount($conn, $sqlCountOpen,   $types, $bind);

  // cntMine: open assegnati a me (macro o attività)
  $cntMine = 0;
  if ($myId) {
    $extra = "(l.stato NOT IN ('completato','chiuso')) AND (" .
             "l.assegnato_a = ? " .
             (tableExists($conn,'lavori_attivita') ? " OR EXISTS(SELECT 1 FROM lavori_attivita la WHERE la.lavoro_id = l.id AND la.utente_id = ?)" : "") .
             ")";
    $sqlCntMine = "SELECT COUNT(*) FROM lavori l ".($WHERE_SQL_BASE ? $WHERE_SQL_BASE.' AND ' : 'WHERE ').$extra;
    $typesMine = $types . 'i' . (tableExists($conn,'lavori_attivita') ? 'i' : '');
    $bindMine  = $bind; $bindMine[] = $myId; if (tableExists($conn,'lavori_attivita')) $bindMine[] = $myId;
    $cntMine   = scalarCount($conn, $sqlCntMine, $typesMine, $bindMine);
  }

  return compact(
    'tutti','ultimi','errSql',
    'cntAll','cntClosed','cntOpen','cntMine'
  );
}

/* ==== Helpers presentazione ==== */
function prio_label($v){
  if ($v === null || $v === '') return 'media';
  if (is_numeric($v)) {
    $n = (int)$v;
    if ($n <= 1) return 'bassa';
    if ($n >= 3) return 'alta';
    return 'media';
  }
  $s = strtolower(trim((string)$v));
  if ($s==='bassa' || $s==='low')  return 'bassa';
  if ($s==='alta'  || $s==='high') return 'alta';
  return 'media';
}

function done_class_by_pct($pct){
  $p = (int)$pct;
  if ($p <= 0)   return 'p0';
  if ($p < 25)   return 'p1';
  if ($p < 50)   return 'p25';
  if ($p < 75)   return 'p50';
  if ($p < 100)  return 'p75';
  return 'p100';
}

/* ==== JS globals per il template ==== */
function buildJsGlobals(array $utenti, array $categorie): array {
  $U = [];
  foreach ($utenti as $u) {
    $U[] = [
      'id'    => isset($u['id']) ? (int)$u['id'] : 0,
      'label' => isset($u['label']) ? (string)$u['label'] : ''
    ];
  }
  $C = [];
  foreach ($categorie as $c) {
    $C[] = [
      'id'   => isset($c['id']) ? (int)$c['id'] : 0,
      'nome' => isset($c['nome']) ? (string)$c['nome'] : ''
    ];
  }
  return [$U, $C];
}

/* ==== (opzionale) mappa ordinamenti centralizzata ==== */
function lavori_sort_map(): array {
  return [
    'ricezione_desc' => 'l.id DESC',
    'scadenza_asc'   => 'l.scadenza IS NULL, l.scadenza ASC, l.id DESC',
    'prezzo_desc'    => 'l.prezzo IS NULL, l.prezzo DESC, l.id DESC',
    'titolo_asc'     => 'l.titolo ASC, l.id DESC',
  ];
}

/*++++++++++++++++++++++++++++ nuovo lavoro*/
/* === Colori di default per tipologia evento === */
function lavori_default_color(string $tipo): string {
  $k = mb_strtolower(trim($tipo), 'UTF-8');
  return [
    'articolo'    => '#0EA5E9',
    'revisione'   => '#F59E0B',
    'incontro'    => '#10B981',
    'scadenza'    => '#EF4444',
    'lavorazione' => '#2563EB',
  ][$k] ?? '#64748B';
}

/**
 * Tipi evento disponibili e legenda (ultimo colore usato per tipo, con fallback ai default).
 * @return array [array $tipi, array $legend (tipo => colore)]
 */
function lavori_event_types_and_legend(mysqli $conn): array {
  $tipi = ['lavorazione','scadenza','altro'];
  $legend = [];

  if (tableExists($conn,'flusso_lavoro')) {
    // arricchisci lista tipi
    if ($res = $conn->query("SELECT DISTINCT TRIM(COALESCE(tipo,'')) AS tipo FROM flusso_lavoro WHERE TRIM(COALESCE(tipo,''))<>'' ORDER BY tipo")){
      while($r = $res->fetch_assoc()){
        $t = (string)$r['tipo'];
        if ($t !== '' && !in_array($t, $tipi, true)) $tipi[] = $t;
      }
      $res->close();
    }
    // colori ultimi usati
    $sql = "
      SELECT t.tipo, fl.colore
      FROM (SELECT tipo, MAX(id) AS maxid FROM flusso_lavoro WHERE TRIM(COALESCE(tipo,''))<>'' GROUP BY tipo) t
      JOIN flusso_lavoro fl ON fl.id = t.maxid
    ";
    if ($res = $conn->query($sql)){
      while($r = $res->fetch_assoc()){
        $tipo = (string)$r['tipo'];
        $col  = (string)($r['colore'] ?? '');
        $legend[$tipo] = $col !== '' ? $col : lavori_default_color($tipo);
      }
      $res->close();
    }
  }

  // assicura un colore anche per i default base
  foreach ($tipi as $t) {
    if (!isset($legend[$t])) $legend[$t] = lavori_default_color($t);
  }

  return [$tipi, $legend];
}

