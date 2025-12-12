<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';
$conn = db();

if (!isset($_SESSION['utente'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Non autorizzato']);
  exit;
}

if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['error' => 'Connessione fallita']);
  exit;
}
function defaultColorFor($tipo){
  $k = mb_strtolower(trim((string)$tipo), 'UTF-8');
  if ($k === 'articolo')  return '#0EA5E9';
  if ($k === 'revisione') return '#F59E0B';
  if ($k === 'incontro')  return '#10B981';
  if ($k === 'scadenza')  return '#EF4444';
  return '#64748B';
}

$sql = "SELECT id, nome, data_evento, assegnato_a, tipo, colore, note
        FROM flusso_lavoro
        ORDER BY data_evento ASC";
$res = $conn->query($sql);

$eventi = [];
while ($r = $res->fetch_assoc()) {
  $tipo = trim((string)($r['tipo'] ?? '')) ?: 'altro';
  $col  = trim((string)($r['colore'] ?? '')) ?: defaultColorFor($tipo);
  $eventi[] = [
    'id'    => (int)$r['id'],
    'title' => $r['nome'],
    'start' => $r['data_evento'],
    'color' => $col,
    'assegnato'     => $r['assegnato_a'] ?? '',
    'assegnato_raw' => $r['assegnato_a'] ?? '',
    'tipo'          => $tipo,
    'note'          => $r['note'] ?? ''
  ];
}

echo json_encode($eventi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
