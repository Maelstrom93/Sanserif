<?php
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
require_once __DIR__ . '/../assets/funzioni/authz.php';
$conn = db();
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if (!currentUserCan('users.manage') && !currentUserCan('calendar.create')) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Permesso negato']);
  exit;
}


if ($conn->connect_error) {
  echo json_encode(['success' => false, 'error' => 'Connessione fallita: '.$conn->connect_error]);
  exit;
}
$conn->set_charset('utf8mb4');

/* ================= Helpers ================= */
function sanitizeHex($c) {
  $c = trim((string)$c);
  return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $c) ? strtoupper($c) : '';
}
function defaultColorFor($tipo){
  $k = mb_strtolower(trim((string)$tipo), 'UTF-8');
  if ($k === 'articolo')  return '#0EA5E9';
  if ($k === 'revisione') return '#F59E0B';
  if ($k === 'incontro')  return '#10B981';
  if ($k === 'scadenza')  return '#EF4444';
  return '#64748B'; // altro/grigio
}
/** Ultimo colore usato per un tipo (se presente almeno un evento con colore) */
function lastColorForType(mysqli $conn, string $tipo): ?string {
  $stmt = $conn->prepare("
    SELECT colore
    FROM flusso_lavoro
    WHERE tipo = ? AND TRIM(COALESCE(colore,'')) <> ''
    ORDER BY id DESC
    LIMIT 1
  ");
  $stmt->bind_param('s', $tipo);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$res) return null;
  $c = sanitizeHex($res['colore'] ?? '');
  return $c ?: null;
}
/** Info colonna `tipo`: tipo SQL + maxLen se VARCHAR + enumVals se ENUM */
function getTipoColumnInfo(mysqli $conn): array {
  $row = $conn->query("SHOW COLUMNS FROM flusso_lavoro LIKE 'tipo'")->fetch_assoc();
  $type = (string)($row['Type'] ?? '');
  $out = ['sql'=>$type, 'maxLen'=>null, 'enumVals'=>null];
  if (preg_match('/varchar\((\d+)\)/i', $type, $m)) {
    $out['maxLen'] = (int)$m[1];
  } elseif (stripos($type, "enum(") === 0) {
    // enum('a','b','c')
    $vals = [];
    $inside = substr($type, 5, -1); // dentro le parentesi
    // split su virgole non escapate
    $parts = preg_split("/,(?=(?:[^']*'[^']*')*[^']*$)/", $inside);
    foreach ($parts as $p) {
      $p = trim($p);
      if ($p === "") continue;
      $vals[] = stripslashes(trim($p, "'"));
    }
    $out['enumVals'] = $vals;
  }
  return $out;
}

/* ================= Input ================= */
$id            = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
$nome          = trim((string)($_POST['nome'] ?? ''));
$data          = substr((string)($_POST['data_evento'] ?? ''), 0, 10);
$assegnato     = trim((string)($_POST['assegnato_a'] ?? '')); // id utente o testo libero
$tipoSel       = trim((string)($_POST['tipo'] ?? ''));
$tipoNew       = trim((string)($_POST['tipo_new'] ?? ''));
$tipoNewCol    = sanitizeHex($_POST['tipo_new_color'] ?? '');
$coloreEv      = sanitizeHex($_POST['colore'] ?? '');
$colorOverride = (string)($_POST['color_override'] ?? '0');   // "1" se utente ha toccato il picker
$note          = trim((string)($_POST['note'] ?? ''));
$descrizione   = trim((string)($_POST['descrizione'] ?? ''));

/* ================= Validazioni base ================= */
if ($nome === '') {
  echo json_encode(['success' => false, 'error' => 'Nome evento mancante']);
  exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
  echo json_encode(['success' => false, 'error' => 'Data non valida (atteso YYYY-MM-DD)']);
  exit;
}

/* ================= Risoluzione TIPO ================= */
if ($tipoSel === '__new__' && $tipoNew !== '') {
  $tipoFinal = $tipoNew;
} else {
  $tipoFinal = ($tipoSel !== '' && $tipoSel !== '__new__') ? $tipoSel : 'altro';
}

/* === Adattati allo schema DB di `tipo` (VARCHAR vs ENUM) === */
$tipoInfo = getTipoColumnInfo($conn);
if ($tipoInfo['enumVals'] !== null) {
  // È un ENUM: deve essere uno dei valori consentiti
  if (!in_array($tipoFinal, $tipoInfo['enumVals'], true)) {
    echo json_encode([
      'success' => false,
      'error'   => "La tipologia \"{$tipoFinal}\" non è consentita perché la colonna `tipo` è un ENUM. Valori ammessi: "
                   . implode(', ', $tipoInfo['enumVals'])
                   . ".\nSuggerimento: converti `tipo` in VARCHAR(100):\nALTER TABLE flusso_lavoro MODIFY COLUMN tipo VARCHAR(100) NOT NULL DEFAULT 'altro';"
    ]);
    exit;
  }
} elseif ($tipoInfo['maxLen'] !== null) {
  // È un VARCHAR(n): tronca per evitare "Data truncated"
  if (mb_strlen($tipoFinal, 'UTF-8') > $tipoInfo['maxLen']) {
    $tipoFinal = mb_substr($tipoFinal, 0, $tipoInfo['maxLen'], 'UTF-8');
  }
}
// Altrimenti (TEXT o altro), lascio com'è

/* ================= Risoluzione COLORE =================
   Regole:
   - Nuova tipologia con colore dedicato => usa quello.
   - Se color_override == "1" => usa il colore scelto manualmente nel picker.
   - Altrimenti:
       INSERT: ultimo colore per quel tipo, altrimenti default.
       UPDATE: se il tipo non cambia, mantieni il colore esistente; se cambia, ultimo colore del nuovo tipo o default.
*/
try {
  if ($id !== '' && ctype_digit($id)) {
    // Leggo riga attuale
    $stmtCur = $conn->prepare("SELECT tipo, colore FROM flusso_lavoro WHERE id = ? LIMIT 1");
    $stmtCur->bind_param('i', $id);
    $stmtCur->execute();
    $cur = $stmtCur->get_result()->fetch_assoc();
    $stmtCur->close();

    $curTipo = (string)($cur['tipo'] ?? 'altro');
    $curCol  = sanitizeHex($cur['colore'] ?? '');

    if ($tipoSel === '__new__' && $tipoNew !== '' && $tipoNewCol) {
      $coloreFinal = $tipoNewCol;
    } elseif ($colorOverride === '1' && $coloreEv) {
      $coloreFinal = $coloreEv;
    } else {
      if (mb_strtolower($curTipo,'UTF-8') === mb_strtolower($tipoFinal,'UTF-8')) {
        $coloreFinal = $curCol ?: (lastColorForType($conn, $tipoFinal) ?: defaultColorFor($tipoFinal));
      } else {
        $coloreFinal = lastColorForType($conn, $tipoFinal) ?: defaultColorFor($tipoFinal);
      }
    }

    // UPDATE
    $stmt = $conn->prepare("
      UPDATE flusso_lavoro
      SET nome = ?, data_evento = ?, assegnato_a = ?, tipo = ?, colore = ?, note = ?, descrizione = ?
      WHERE id = ?
    ");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param("sssssssi", $nome, $data, $assegnato, $tipoFinal, $coloreFinal, $note, $descrizione, $id);

  } else {
    // INSERT
    if ($tipoSel === '__new__' && $tipoNew !== '' && $tipoNewCol) {
      $coloreFinal = $tipoNewCol;
    } elseif ($colorOverride === '1' && $coloreEv) {
      $coloreFinal = $coloreEv;
    } else {
      $coloreFinal = lastColorForType($conn, $tipoFinal) ?: defaultColorFor($tipoFinal);
    }

    $stmt = $conn->prepare("
      INSERT INTO flusso_lavoro (nome, creato_il, data_evento, assegnato_a, tipo, colore, note, descrizione)
      VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param("sssssss", $nome, $data, $assegnato, $tipoFinal, $coloreFinal, $note, $descrizione);
  }

  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error);
  }

  echo json_encode(['success' => true]);

} catch (Throwable $e) {
  // Errore dettagliato per debug
  echo json_encode([
    'success' => false,
    'error'   => $e->getMessage(),
    'debug'   => [
      'tipo_sql'   => $tipoInfo['sql'] ?? null,
      'tipo_final' => $tipoFinal,
      'override'   => $colorOverride,
      'colore_in'  => $coloreEv,
    ]
  ]);
}
