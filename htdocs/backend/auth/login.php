<?php
session_start();
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
require_once __DIR__ . '/../assets/funzioni/db/db.php';

$msg = '';

// Mostra messaggi di errore provenienti da check_login.php
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'password':
            $msg = "Password errata.";
            registraAttivita($conn, 'Tentativo accesso fallito');

            break;
        case 'notfound':
            $msg = "Utente non trovato.";
            break;
        case 'empty':
            $msg = "Inserisci username e password.";
                  registraAttivita($conn, 'Tentativo accesso fallito');
            break;
        default:
            $msg = "Si è verificato un errore.";
                  registraAttivita($conn, 'Tentativo accesso fallito');
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Back-end Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --main-color: #004c60;
      --accent-color: #00748a;
      --light-bg: #f5f8fa;
      --border: #ccc;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
      background: var(--light-bg);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-container {
      background: #fff;
      padding: 2rem 2.5rem;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 400px;
      animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    h2 {
      text-align: center;
      margin-bottom: 1.5rem;
      color: var(--main-color);
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 0.75rem;
      margin-bottom: 1rem;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 1rem;
      transition: 0.2s;
    }

    input:focus {
      border-color: var(--main-color);
      outline: none;
      box-shadow: 0 0 3px rgba(0,76,96,0.3);
    }

    button {
      width: 100%;
      padding: 0.75rem;
      background: var(--main-color);
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.2s;
    }

    button:hover {
      background: var(--accent-color);
    }

    .error-msg {
      color: #d10000;
      background: #ffeaea;
      padding: 0.7rem;
      text-align: center;
      margin-bottom: 1rem;
      border: 1px solid #d10000;
      border-radius: 6px;
    }

    .footer {
      margin-top: 1rem;
      text-align: center;
      font-size: 0.85rem;
      color: #888;
    }

    @media (max-width: 480px) {
      .login-container {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>

<div class="login-container">
  <h2>Accedi al Pannello</h2>

  <?php if ($msg): ?>
    <div class="error-msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="POST" action="check_login.php">
    <input type="text" name="username" placeholder="Username"
           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Entra</button>
    <div style="margin-top: .75rem; text-align:center;">
  <a href="forgot_password.php" style="font-size:.95rem; text-decoration:none; color:#00748a;">
    Password dimenticata?
  </a>
</div>

  </form>

  <div class="footer">
    &copy; <?= date('Y') ?> Powered by Maelstrom
  </div>
</div>

</body>
</html>
