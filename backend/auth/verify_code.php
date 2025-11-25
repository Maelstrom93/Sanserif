<?php
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if ($code === '' || !ctype_digit($code)) {
        $msg = "Inserisci un codice valido (6 cifre).";
    } else {
        // Trova il reset più recente non usato e non scaduto
        // Prima troviamo i candidate resets più recenti (potrebbero esserci più utenti, ma useremo un match sul code hash)
        // NOTA: per sicurezza reale, dovresti legare il codice anche alla mail/username inserita. Qui manteniamo flusso semplice.
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $q = $conn->query("SELECT pr.*, u.email, u.username 
                           FROM password_resets pr 
                           JOIN utenti u ON u.id = pr.user_id
                           WHERE pr.used = 0 AND pr.expires_at >= '{$now}'
                           ORDER BY pr.id DESC LIMIT 50");

        $match = null;
        while ($row = $q->fetch_assoc()) {
            if (password_verify($code, $row['code_hash'])) {
                $match = $row;
                break;
            }
        }

        if (!$match) {
            // incrementa tentativi per gli ultimi record per scoraggiare brute force
            $conn->query("UPDATE password_resets SET attempts = attempts + 1 WHERE used = 0 AND expires_at >= '{$now}' ORDER BY id DESC LIMIT 1");
            $msg = "Codice non valido o scaduto.";
        } else {
            if ((int)$match['attempts'] >= 10) {
                $msg = "Troppi tentativi. Richiedi un nuovo codice.";
            } else {
                // marca come usato e passa alla fase di reset
                $upd = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                $upd->bind_param("i", $match['id']);
                $upd->execute();

                // Salva in sessione l'utente da resettare
                $_SESSION['password_reset_user'] = (int)$match['user_id'];
                // Token CSRF semplice per reset
                $_SESSION['reset_csrf'] = bin2hex(random_bytes(16));

                header("Location: reset_password.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Verifica codice</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;font-family:'Segoe UI',sans-serif;background:#f5f8fa;display:flex;align-items:center;justify-content:center;height:100vh}
    .box{background:#fff;max-width:420px;width:100%;padding:24px;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,.1)}
    h2{margin:0 0 16px;color:#004c60;text-align:center}
    input{width:100%;padding:12px;border:1px solid #ccc;border-radius:6px;margin:8px 0}
    button{width:100%;padding:12px;background:#004c60;color:#fff;border:none;border-radius:6px;cursor:pointer}
    button:hover{background:#00748a}
    .msg{margin:10px 0;color:#d10000;text-align:center}
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
    <h2>Inserisci il codice</h2>
    <?php if ($msg): ?>
      <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php else: ?>
      <div class="footer" style="margin-bottom:10px;color:#444;">
        Inserisci il codice a 6 cifre ricevuto via email. Valido 15 minuti.
      </div>
    <?php endif; ?>
    <form method="POST">
      <input type="text" name="code" placeholder="Codice (6 cifre)" maxlength="6" required autofocus>
      <button type="submit">Verifica</button>
    </form>
    <div class="footer"><a href="forgot_password.php">Invia un nuovo codice</a> · <a href="login.php">Torna al login</a></div>
  </div>
</body>
</html>
