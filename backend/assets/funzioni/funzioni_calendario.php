<?php
// file: assets/funzioni/funzioni_calendario.php

// NB: presuppone che chi include abbia già incluso:
// require_once __DIR__ . '/db/db.php';        // $conn
// require_once __DIR__ . '/funzioni.php';     // e(), requireLogin(), tableExists(), assigneeLabel(), ecc.

/**
 * Colore di default per un tipo evento (server-side).
 */
function cal_default_color_for(string $tipo): string {
  $k = mb_strtolower(trim($tipo), 'UTF-8');
  if ($k === 'articolo')  return '#0EA5E9';
  if ($k === 'revisione') return '#F59E0B';
  if ($k === 'incontro')  return '#10B981';
  if ($k === 'scadenza')  return '#EF4444';
  return '#64748B'; // altro
}

/**
 * Elenco utenti per la select “Assegnato a” (se la tabella esiste).
 * Ritorna array di: ['id'=>int, 'label'=>string]
 */
function cal_fetch_utenti_select(mysqli $conn): array {
  $utenti = [];
  if (function_exists('tableExists') && tableExists($conn, 'utenti')) {
    $sql = "SELECT id, TRIM(COALESCE(nome,'')) AS nome, TRIM(COALESCE(email,'')) AS email
            FROM utenti
            ORDER BY nome, email";
    if ($rs = $conn->query($sql)) {
      while ($u = $rs->fetch_assoc()) {
        $label = $u['nome'] !== '' ? $u['nome'] : ($u['email'] !== '' ? $u['email'] : ('#'.$u['id']));
        $utenti[] = ['id'=>(int)$u['id'], 'label'=>$label];
      }
      $rs->free();
    }
  }
  return $utenti;
}

/**
 * Tipi evento distinti per select filtro + form.
 * Ritorna mappa tipo=>tipo (es. ['articolo'=>'articolo', ...]).
 */
function cal_fetch_tipi_evento(mysqli $conn): array {
  $tipi = [];
  $sql = "SELECT DISTINCT TRIM(COALESCE(tipo,'')) AS tipo
          FROM flusso_lavoro
          WHERE TRIM(COALESCE(tipo,'')) <> ''
          ORDER BY tipo";
  if ($rs = $conn->query($sql)) {
    while ($r = $rs->fetch_assoc()) {
      $val = (string)$r['tipo'];
      if ($val !== '') $tipi[$val] = $val;
    }
    $rs->free();
  }
  if (empty($tipi)) $tipi = ['altro' => 'altro'];
  return $tipi;
}

/**
 * Eventi per FullCalendar + legenda tipo=>colore.
 * Ritorna array: [$eventi, $legend]
 */
function cal_fetch_eventi_e_legend(mysqli $conn, ?int $assignedUserId = null): array {
  $eventi = [];
  $legend = [];

  // base query
  $sql = "SELECT id, nome, data_evento, assegnato_a, tipo, colore, note, descrizione
          FROM flusso_lavoro";
  $where  = [];
  $params = [];
  $types  = '';

  /*
   * Se $assignedUserId è valorizzato, limitiamo agli eventi “assegnati a me”.
   * Gestiamo sia il caso INT (assegnato_a = 12) sia quello testuale (assegnato_a = "12").
   * NB: se in passato hai salvato nomi liberi, questi NON combaceranno con l’ID dell’utente,
   * ed è desiderato: i base/intermedio vedono solo eventi dove l’assegnazione è il loro ID.
   */
  if (!empty($assignedUserId)) {
    $where[]  = "(assegnato_a = ? OR TRIM(COALESCE(assegnato_a,'')) = ?)";
    $params[] = (int)$assignedUserId;   $types .= 'i';
    $params[] = (string)$assignedUserId; $types .= 's';
  }

  if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
  }
  $sql .= " ORDER BY data_evento ASC, id ASC";

  // esecuzione (prepared)
  if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rs = $stmt->get_result();
  } else {
    // fallback (non dovrebbe servire): solo senza where
    $rs = $conn->query($sql);
  }

  if ($rs) {
    while ($r = $rs->fetch_assoc()) {
      $tipo = trim((string)($r['tipo'] ?? '')) ?: 'altro';
      $col  = trim((string)($r['colore'] ?? '')) ?: cal_default_color_for($tipo);

      $rawAss   = $r['assegnato_a'] ?? '';
      $assLabel = function_exists('assigneeLabel') ? assigneeLabel($conn, $rawAss) : (string)$rawAss;

      $eventi[] = [
        'id'    => (int)$r['id'],
        'title' => (string)$r['nome'],
        'start' => (string)$r['data_evento'],
        'color' => $col,
        // FullCalendar extendedProps:
        'assegnato'     => $assLabel,
        'assegnato_raw' => $rawAss,
        'tipo'          => $tipo,
        'note'          => (string)($r['note'] ?? ''),
        'descrizione'   => (string)($r['descrizione'] ?? ''),
      ];
      $legend[$tipo] = $col; // ultimo vince
    }
    $rs->free();
  }

  if (empty($legend)) $legend = ['altro' => cal_default_color_for('altro')];

  return [$eventi, $legend];
}


/**
 * ViewModel unico per la pagina calendario.
 * Ritorna:
 *  - utenti          (select Assegnato a)
 *  - tipi_evento     (select Tipi)
 *  - eventi_json     (array per FC)
 *  - legend          (mappa tipo=>colore)
 */
function buildCalendarioViewModel(mysqli $conn, array $opts = []): array {
  $assignedUserId = isset($opts['assigned_user_id']) && $opts['assigned_user_id']
    ? (int)$opts['assigned_user_id']
    : null;

  $utenti      = cal_fetch_utenti_select($conn);
  $tipi_evento = cal_fetch_tipi_evento($conn);
  [$eventi, $legend] = cal_fetch_eventi_e_legend($conn, $assignedUserId);

  return [
    'utenti'      => $utenti,
    'tipi_evento' => $tipi_evento,
    'eventi_json' => $eventi,
    'legend'      => $legend,
  ];
}

