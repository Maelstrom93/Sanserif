<?php
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';
$conn = db();

if(!isset($_SESSION['utente'])){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
  exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($conn->connect_error) {
  echo json_encode(['success' => false, 'error' => 'Connessione fallita']);
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo json_encode(['success' => false, 'error' => 'ID mancante']);
  exit;
}

$stmt = $conn->prepare("DELETE FROM flusso_lavoro WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
  echo json_encode(['success' => true]);
} else {
  echo json_encode(['success' => false, 'error' => $stmt->error]);
}
