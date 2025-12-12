<?php
header('Content-Type: application/json');
require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';
requireLogin();
if ($_SESSION['utente']['ruolo']!=='admin') {
  echo json_encode(['success'=>false]); exit;
}

$nome = trim($_POST['nome'] ?? '');
if ($nome === '') {
  echo json_encode(['success'=>false]); exit;
}

// Verifica esistenza
$stmt = $conn->prepare("SELECT id FROM categorie_articoli WHERE nome = ?");
$stmt->bind_param("s", $nome);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows>0) {
  $stmt->bind_result($id);
  $stmt->fetch();
  echo json_encode(['success'=>true,'id'=>$id,'nome'=>$nome]);
  exit;
}
$stmt->close();

// Inserimento
$stmt = $conn->prepare("INSERT INTO categorie_articoli (nome) VALUES (?)");
$stmt->bind_param("s",$nome);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

echo json_encode(['success'=>true,'id'=>$newId,'nome'=>$nome]);
