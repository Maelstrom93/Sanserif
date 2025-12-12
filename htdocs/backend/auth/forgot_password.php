<?php
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';

// rate limiting base per IP (facoltativo, semplice)
$rateLimited = false;
if (!isset($_SESSION['fp_last']) || time() - $_SESSION['fp_last'] > 15) {
    $_SESSION['fp_last'] = time();
} else {
    $rateLimited = true; // max una richiesta ogni 15s
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rateLimited) {
    $login = trim($_POST['login'] ?? '');
    if ($login !== '') {
        // Cerca per email o username
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("SELECT id, email, username FROM utenti WHERE email = ? LIMIT 1");
        } else {
            $stmt = $conn->prepare("SELECT id, email, username FROM utenti WHERE username = ? LIMIT 1");
        }
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $res = $stmt->get_result();

        // Messaggio neutro a prescindere
        $msg = "Se l'account esiste, riceverai un'email con un codice di verifica.";

        if ($res->num_rows === 1) {
            $u = $res->fetch_assoc();
            $user_id = (int)$u['id'];
            $email   = $u['email'];

            // Genera codice (6 cifre) + hasha per salvare
            $code = random_int(100000, 999999);
            $code_hash = password_hash((string)$code, PASSWORD_DEFAULT);

            // Scadenza 15 minuti
            $expires_at = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

            // Invalida eventuali precedenti (opzionale)
            $conn->query("UPDATE password_resets SET used = 1 WHERE user_id = {$user_id} AND used = 0");

            // Inserisci record
            $ins = $conn->prepare("INSERT INTO password_resets (user_id, code_hash, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("iss", $user_id, $code_hash, $expires_at);
            $ins->execute();

            // Invia email (usa mail() base; se vuoi SMTP/PHPMailer possiamo aggiungerlo)
            $subject = "Il tuo codice di verifica";
            $body    = "Ciao,\n\nIl tuo codice per il recupero password è: {$code}\nScade tra 15 minuti.\n\nSe non hai richiesto tu il recupero, ignora questa email.";
            $headers = "From: noreply@sansserifse.altervista.org\r\nReply-To: noreply@sansserifse.altervista.org\r\nX-Mailer: PHP/" . phpversion();

            // Silenziosamente: non mostriamo se fallisce
            @mail($email, $subject, $body, $headers);
        }
    } else {
        $msg = "Inserisci email o username.";
    }

    // Per UX: memorizza pseudo-info in session per precompilare verify
    $_SESSION['pending_reset_notice'] = true;
    header("Location: verify_code.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Recupero password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:'Segoe UI',sans-serif;background:#f5f8fa;display:flex;align-items:center;justify-content:center;height:100vh}
    .box{background:#fff;max-width:420px;width:100%;padding:24px;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,.1)}
    h2{margin:0 0 16px;color:#004c60;text-align:center}
    input{width:100%;padding:12px;border:1px solid #ccc;border-radius:6px;margin:8px 0}
    button{width:100%;padding:12px;background:#004c60;color:#fff;border:none;border-radius:6px;cursor:pointer}
    button:hover{background:#00748a}
    .msg{margin:10px 0;color:#444;text-align:center}
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
    <h2>Recupero password</h2>
    <?php if ($rateLimited): ?>
      <div class="msg">Stai procedendo troppo in fretta. Riprova tra qualche secondo.</div>
    <?php endif; ?>
    <?php if ($msg): ?>
      <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="text" name="login" placeholder="Email o Username" required autofocus>
      <button type="submit">Invia codice</button>
    </form>
    <div class="footer"><a href="login.php">Torna al login</a></div>
  </div>
</body>
</html>
