<?php
// file: api/get_lavoro.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';

function getClienteNome(mysqli $conn, ?int $cliente_id): string {
  if ($cliente_id===null) return 'Cliente';
  if (!tableExists($conn,'clienti')) return 'Cliente #'.$cliente_id;
  $col = tableHasColumn($conn,'clienti','nome') ? 'nome' : (tableHasColumn($conn,'clienti','rgs') ? 'rgs' : null);
  if (!$col) return 'Cliente #'.$cliente_id;
  $q=$conn->query("SELECT TRIM($col) AS n FROM clienti WHERE id = ".(int)$cliente_id." LIMIT 1");
  $nome=$q?(string)($q->fetch_assoc()['n']??''):''; return $nome!==''?$nome:('Cliente #'.$cliente_id);
}

function getUserLabel(mysqli $conn, ?int $uid): ?string {
  if ($uid === null) return null;
  if (!tableExists($conn,'utenti')) return '#'.$uid;
  if ($st = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(nome),''), NULLIF(TRIM(email),''), CONCAT('#', id)) AS lbl FROM utenti WHERE id=? LIMIT 1")){
    $st->bind_param('i', $uid);
    $st->execute();
    $lbl = $st->get_result()->fetch_assoc()['lbl'] ?? null;
    $st->close();
    return $lbl ?: '#'.$uid;
  }
  return '#'.$uid;
}

/** Normalizza la priorità ritornando sempre 'bassa' | 'media' | 'alta' */
function normalizePriorita($raw): string {
  if ($raw === null || $raw === '') return 'media';
  if (is_numeric($raw)) {
    $n = (int)$raw;
    if ($n <= 1) return 'bassa';
    if ($n === 2) return 'media';
    if ($n >= 3) return 'alta';
  }
  $s = mb_strtolower(trim((string)$raw), 'UTF-8');
  if ($s === 'bassa' || $s === 'low')  return 'bassa';
  if ($s === 'alta'  || $s === 'high') return 'alta';
  return 'media';
}

if (!isLogged()) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Non autenticato']); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID non valido']); exit; }

try {
  // Lavoro base
  $selectCols = "
    id, titolo, cliente_id, assegnato_a, data_ricezione, scadenza, prezzo,
    provenienza, cartelle_json, descrizione, stato
  ";
  if (tableHasColumn($conn,'lavori','priorita'))       $selectCols .= ", priorita";
  if (tableHasColumn($conn,'lavori','checklist_json')) $selectCols .= ", checklist_json";

  $stmt = $conn->prepare("SELECT $selectCols FROM lavori WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;

  $stmt->close();
  if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Lavoro non trovato']); exit; }

  // categorie pivot
  $assegnato_a_label = getUserLabel($conn, isset($row['assegnato_a']) && $row['assegnato_a']!==null ? (int)$row['assegnato_a'] : null);
  $categorie_ids = []; $categorie_nomi = '';
  if (tableExists($conn,'lavori_categorie')) {
    $rs = $conn->prepare("SELECT categoria_id FROM lavori_categorie WHERE lavoro_id = ?");
    $rs->bind_param('i', $id); $rs->execute(); $rres = $rs->get_result();
    while ($r = $rres->fetch_assoc()) $categorie_ids[] = (int)$r['categoria_id'];
    $rs->close();

  if (tableExists($conn,'lavori_categorie') && tableExists($conn,'categorie_lavoro')) {
  $rs2 = $conn->prepare("
    SELECT GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ', ') AS nomi
      FROM lavori_categorie lc
      JOIN categorie_lavoro c ON c.id = lc.categoria_id
     WHERE lc.lavoro_id = ?
  ");
  $rs2->bind_param('i', $id);
  $rs2->execute();
  $categorie_nomi = (string)($rs2->get_result()->fetch_assoc()['nomi'] ?? '');
  $rs2->close();
}
  }

  // cartelle_json -> array
  $cartelle = [];
  if (!empty($row['cartelle_json'])) {
    $tmp = json_decode((string)$row['cartelle_json'], true);
    if (is_array($tmp)) $cartelle = array_values(array_filter(array_map('strval', $tmp)));
  }

  // === Attività: aggiunta sicura di completata/completato_il/completato_da ===
  $extraCols = '';
  $hasCompl   = tableHasColumn($conn,'lavori_attivita','completata');
  $hasComplIl = tableHasColumn($conn,'lavori_attivita','completato_il');
  $hasComplDa = tableHasColumn($conn,'lavori_attivita','completato_da');
  $extraCols .= $hasCompl   ? ", la.completata AS completata"       : ", 0 AS completata";
  $extraCols .= $hasComplIl ? ", la.completato_il AS completato_il" : ", NULL AS completato_il";
  $extraCols .= $hasComplDa ? ", la.completato_da AS completato_da" : ", NULL AS completato_da";

  $attivita = [];
  if (tableExists($conn,'lavori_attivita')) {
    $sqlA = "
      SELECT la.id, la.titolo, la.descrizione, la.scadenza, la.prezzo, la.utente_id, la.categoria_id, la.evento_id
           , u.nome AS utente_nome, u.email AS utente_email
           , cl.nome AS categoria_nome
           , ev.tipo AS evento_tipo, ev.colore AS evento_colore
           $extraCols
      FROM lavori_attivita la
      LEFT JOIN utenti u ON u.id = la.utente_id
LEFT JOIN categorie_lavoro cl ON cl.id = la.categoria_id
      LEFT JOIN flusso_lavoro ev ON ev.id = la.evento_id
      WHERE la.lavoro_id = ?
      ORDER BY la.id ASC
    ";
    $stA = $conn->prepare($sqlA);
    $stA->bind_param('i', $id);
    $stA->execute();
    $resA = $stA->get_result();
    while($a = $resA->fetch_assoc()){
      $labelUtente = trim((string)($a['utente_nome'] ?? ''));
      if ($labelUtente === '') $labelUtente = trim((string)($a['utente_email'] ?? ''));
      if ($labelUtente === '' && $a['utente_id']) $labelUtente = '#'.$a['utente_id'];

    $descrRaw = (string)($a['descrizione'] ?? '');
$utente_ex = null;
if ($descrRaw !== '') {
  if (preg_match('/\[(?:ex[_\s-]?utente)\s*:\s*(.+?)\]/iu', $descrRaw, $m)) {
    $utente_ex = trim($m[1]);
  }
  $descrRaw = trim(preg_replace('/\s*\[(?:ex[_\s-]?utente)\s*:\s*.+?\]\s*/iu', '', $descrRaw));
}


      // === STORICO ASSEGNATARI (se c'è una tabella storico) ===
      $assignees_history = [];
      if (tableExists($conn, 'attivita_utenti_storico')) {
        $sqlH = "
          SELECT s.user_id, COALESCE(u.nome, u.email, CONCAT('#', s.user_id)) AS name,
                 s.assigned_from, s.assigned_to
          FROM attivita_utenti_storico s
          LEFT JOIN utenti u ON u.id = s.user_id
          WHERE s.attivita_id = ?
          ORDER BY s.assigned_from ASC
        ";
        if ($stH = $conn->prepare($sqlH)) {
          $aid = (int)$a['id'];
          $stH->bind_param('i', $aid);
          $stH->execute();
          $rH = $stH->get_result();
          while ($h = $rH->fetch_assoc()) {
            $assignees_history[] = [
              'id'   => (int)$h['user_id'],
              'name' => (string)($h['name'] ?? ''),
              'from' => $h['assigned_from'],
              'to'   => $h['assigned_to'],
            ];
          }
          $stH->close();
        }
      }
      // fallback: se non c'è la tabella storico ma nella descrizione c'è [ex_utente: ...]
      if (empty($assignees_history) && !empty($utente_ex)) {
        $assignees_history[] = ['id' => null, 'name' => $utente_ex, 'from' => null, 'to' => null];
      }

      // === CHI HA CHIUSO L'ATTIVITÀ ===
      $closed_by = null;
      $closed_at = !empty($a['completato_il']) ? (string)$a['completato_il'] : null;

      // 1) se hai la colonna completato_da
      if (!empty($a['completato_da'])) {
        $uid = (int)$a['completato_da'];
        if (tableExists($conn,'utenti')) {
          if ($qU = $conn->prepare("SELECT COALESCE(nome, email, CONCAT('#', id)) AS n FROM utenti WHERE id=? LIMIT 1")) {
            $qU->bind_param('i', $uid);
            $qU->execute();
            $n = $qU->get_result()->fetch_assoc()['n'] ?? null;
            $qU->close();
            $closed_by = ['id'=>$uid, 'name'=>(string)($n ?: ('#'.$uid))];
          } else {
            $closed_by = ['id'=>$uid, 'name'=>'#'.$uid];
          }
        } else {
          $closed_by = ['id'=>$uid, 'name'=>'#'.$uid];
        }
      } else {
        // 2) oppure dal log azioni
        if (tableExists($conn,'attivita_log')) {
          $aid = (int)$a['id'];
          $sqlL = "
            SELECT l.user_id, COALESCE(u.nome, u.email, CONCAT('#', l.user_id)) AS name, l.created_at
            FROM attivita_log l
            LEFT JOIN utenti u ON u.id = l.user_id
            WHERE l.attivita_id=? AND l.action='complete'
            ORDER BY l.created_at DESC
            LIMIT 1
          ";
          if ($stL = $conn->prepare($sqlL)) {
            $stL->bind_param('i', $aid);
            $stL->execute();
            if ($rowL = $stL->get_result()->fetch_assoc()) {
              $closed_by = ['id'=>(int)$rowL['user_id'], 'name'=>(string)($rowL['name'] ?? '')];
              if (!$closed_at && !empty($rowL['created_at'])) $closed_at = (string)$rowL['created_at'];
            }
            $stL->close();
          }
        }
      }

      $attivita[] = [
        'id'               => (int)$a['id'],
        'titolo'           => (string)($a['titolo'] ?? ''),
        'descrizione'      => $descrRaw,
        'scadenza'         => $a['scadenza'] ? (string)$a['scadenza'] : null,
        'prezzo'           => $a['prezzo'] !== null ? (float)$a['prezzo'] : null,
        'utente'           => $labelUtente ?: '—',
        'utente_id'        => $a['utente_id'] !== null ? (int)$a['utente_id'] : null,
        'utente_ex'        => $utente_ex,
        'assignees_history'=> $assignees_history,
        'categoria'        => (string)($a['categoria_nome'] ?? '—'),
        'categoria_id'     => $a['categoria_id'] !== null ? (int)$a['categoria_id'] : null,
        'evento_id'        => $a['evento_id'] !== null ? (int)$a['evento_id'] : null,
        'evento_tipo'      => (string)($a['evento_tipo'] ?? 'lavorazione'),
        'evento_colore'    => (string)($a['evento_colore'] ?? ''),
        'completata'       => !empty($a['completata']) ? 1 : 0,
        'completato_il'    => !empty($a['completato_il']) ? (string)$a['completato_il'] : null,
        'completato_da'    => isset($a['completato_da']) ? ( ($a['completato_da']!==null)? (int)$a['completato_da'] : null) : null,
        'closed_by'        => $closed_by,
        'closed_at'        => $closed_at,
      ];
    }
    $stA->close();
  }

  // Macro-evento (se esiste)
  $macro_tipo = null; $macro_colore = null;
  if (tableExists($conn,'flusso_lavoro')) {
    $selMacro = $conn->prepare("
      SELECT tipo, colore
        FROM flusso_lavoro
       WHERE descrizione LIKE CONCAT('Lavoro #', ?, ' — Scadenza macro — Cliente:%')
       ORDER BY id DESC LIMIT 1
    ");
    $selMacro->bind_param('i', $id);
    $selMacro->execute();
    if ($rM = $selMacro->get_result()->fetch_assoc()){
      $macro_tipo = (string)($rM['tipo'] ?? 'scadenza');
      $macro_colore = (string)($rM['colore'] ?? '');
    }
    $selMacro->close();
  }

  $cliente_label = getClienteNome($conn, isset($row['cliente_id']) && $row['cliente_id']!==null ? (int)$row['cliente_id'] : null);

  // checklist decodificata
  $checklist = [];
  if (!empty($row['checklist_json'])) {
    $tmp = json_decode((string)$row['checklist_json'], true);
    if (is_array($tmp)) $checklist = $tmp;
  }

  // priorità normalizzata
  $prioritaNorm = 'media';
  if (array_key_exists('priorita', $row)) {
    $prioritaNorm = normalizePriorita($row['priorita']);
  }

  $out = [
    'success'             => true,
    'id'                  => (int)$row['id'],
    'titolo'              => (string)$row['titolo'],
    'cliente_id'          => $row['cliente_id'] !== null ? (int)$row['cliente_id'] : null,
    'cliente_label'       => $cliente_label,
    'assegnato_a'         => $row['assegnato_a'] !== null ? (int)$row['assegnato_a'] : null,
    'data_ricezione'      => $row['data_ricezione'] ? (string)$row['data_ricezione'] : null,
    'scadenza'            => $row['scadenza'] ? (string)$row['scadenza'] : null,
    'prezzo'              => $row['prezzo'] !== null ? (float)$row['prezzo'] : null,
    'provenienza'         => (string)($row['provenienza'] ?? ''),
    'descrizione'         => (string)($row['descrizione'] ?? ''),
    'stato'               => (string)($row['stato'] ?? 'aperto'),
    'priorita'            => $prioritaNorm,
    'categorie_ids'       => $categorie_ids,
    'categorie_nomi'      => $categorie_nomi,
    'cartelle'            => $cartelle,
    'checklist_json'      => isset($row['checklist_json']) ? (string)$row['checklist_json'] : '[]',
    'checklist'           => $checklist,
    'attivita'            => $attivita,
    'macro_evento_tipo'   => $macro_tipo ?: 'scadenza',
    'macro_evento_colore' => $macro_colore ?: '',
    'assegnato_a_label'   => $assegnato_a_label // NEW
  ];

  echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Errore server','debug'=>$e->getMessage()]);
}
