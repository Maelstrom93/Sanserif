<?php
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    header("Location: login.php?error=empty");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM utenti WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 1) {
    $utente = $res->fetch_assoc();
    if (password_verify($password, $utente['password'])) {
        $_SESSION['utente'] = [
            'id' => $utente['id'],
            'username' => $utente['username'],
            'nome' => $utente['nome'],
            'ruolo' => $utente['ruolo']
        ];
        header("Location: ../index.php");
            registraAttivita($conn, 'Login eseguito');

        exit;
    } else {
        header("Location: login.php?error=password");
        exit;
    }
} else {
    header("Location: login.php?error=notfound");
    exit;
}
?>
