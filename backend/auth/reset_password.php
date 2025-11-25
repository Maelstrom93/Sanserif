<?php
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';   

if (empty($_SESSION['password_reset_user']) || empty($_SESSION['reset_csrf'])) {
    header("Location: forgot_password.php");
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password2'] ?? '';

    if (!hash_equals($_SESSION['reset_csrf'], $csrf)) {
        $msg = "Sessione non valida. Riprova.";
    } elseif (strlen($p1) < 8) {
        $msg = "La password deve avere almeno 8 caratteri.";
    } elseif ($p1 !== $p2) {
        $msg = "Le password non coincidono.";
    } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $uid = (int)$_SESSION['password_reset_user'];
        $upd = $conn->prepare("UPDATE utenti SET password = ? WHERE id = ?");
        $upd->bind_param("si", $hash, $uid);
        $upd->execute();

        // cleanup sessione
        unset($_SESSION['password_reset_user'], $_SESSION['reset_csrf']);

        // opzionale: auto-login
        // header("Location: login.php?reset=ok");
        // exit;

        $msg = "Password aggiornata con successo. Ora puoi effettuare il login.";
        registraAttivita($conn, 'Password modificata');
        }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Imposta nuova password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:'Segoe UI',sans-serif;background:#f5f8fa;display:flex;align-items:center;justify-content:center;height:100vh}
    .box{background:#fff;max-width:420px;width:100%;padding:24px;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,.1)}
    h2{margin:0 0 16px;color:#004c60;text-align:center}
    input{width:100%;padding:12px;border:1px solid #ccc;border-radius:6px;margin:8px 0}
    button{width:100%;padding:12px;background:#004c60;color:#fff;border:none;border-radius:6px;cursor:pointer}
    button:hover{background:#00748a}
    .msg{margin:10px 0;color:#007000;text-align:center}
    .err{color:#d10000}
    .footer{text-align:center;margin-top:10px}
    a{color:#00748a;text-decoration:none}
    input {
  width: 100%;
  padding: 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
  margin: 8px 0;
  box-sizing: border-box; /* ✅ evita lo sbordo */
}

  </style>
</head>
<body>
  <div class="box">
    <h2>Nuova password</h2>
    <?php if ($msg): ?>
      <div class="msg <?= strpos($msg,'successo')===false ? 'err':'' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php else: ?>
      <div class="footer" style="margin-bottom:10px;color:#444;">
        Scegli una nuova password (min 8 caratteri).
      </div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['reset_csrf']) ?>">
      <input type="password" name="password" placeholder="Nuova password" required autofocus>
      <input type="password" name="password2" placeholder="Ripeti password" required>
      <button type="submit">Imposta password</button>
    </form>
    <div class="footer"><a href="login.php">Torna al login</a></div>
  </div>
</body>
</html>
