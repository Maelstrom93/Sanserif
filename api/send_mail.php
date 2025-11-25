<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// /api/send_mail.php
header('Content-Type: application/json; charset=utf-8');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false, 'error'=>'Metodo non consentito']); exit;
}

// Honeypot: se pieno è bot
if (!empty($_POST['website'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'Richiesta non valida']); exit;
}

// Sanifica input
$tipo    = isset($_POST['tipo']) && $_POST['tipo'] === 'azienda' ? 'azienda' : 'privato';
$nome    = trim(filter_input(INPUT_POST, 'nome',    FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$cognome = trim(filter_input(INPUT_POST, 'cognome', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$rgs     = trim(filter_input(INPUT_POST, 'rgs',     FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$settore = trim(filter_input(INPUT_POST, 'settore', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$email   = trim(filter_input(INPUT_POST, 'email',   FILTER_SANITIZE_EMAIL));
$msg     = trim(filter_input(INPUT_POST, 'msg',     FILTER_SANITIZE_FULL_SPECIAL_CHARS));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['ok'=>false, 'error'=>'Email non valida']); exit;
}

// Controlli base
if ($tipo === 'privato' && (!$nome || !$cognome || !$msg)) {
  http_response_code(422); echo json_encode(['ok'=>false, 'error'=>'Compila tutti i campi richiesti']); exit;
}
if ($tipo === 'azienda' && (!$rgs || !$settore || !$msg)) {
  http_response_code(422); echo json_encode(['ok'=>false, 'error'=>'Compila tutti i campi richiesti']); exit;
}

// Componi mail
$to = 'vassallocalogero93@gmail.com'; // <-- se puoi, mettilo in config
if ($tipo === 'privato') {
  $subject = 'NUOVA RICHIESTA DI PREVENTIVO DA PRIVATO';
  $body = "
    <html><body>
      <h3>Nuova richiesta (Privato)</h3>
      <p><strong>Nome:</strong> {$nome}</p>
      <p><strong>Cognome:</strong> {$cognome}</p>
      <p><strong>Email cliente:</strong> {$email}</p>
      <p><strong>Richiesta:</strong><br>" . nl2br($msg) . "</p>
    </body></html>";
} else {
  $subject = 'NUOVA RICHIESTA DI PREVENTIVO DA AZIENDA';
  $body = "
    <html><body>
      <h3>Nuova richiesta (Azienda)</h3>
      <p><strong>Ragione Sociale:</strong> {$rgs}</p>
      <p><strong>Referente/Settore:</strong> {$settore}</p>
      <p><strong>Email cliente:</strong> {$email}</p>
      <p><strong>Richiesta:</strong><br>" . nl2br($msg) . "</p>
    </body></html>";
}

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/html; charset=UTF-8';
// From deve essere del tuo dominio (evita bounce). Reply-To = utente.
$headers[] = 'From: Sans Serif <noreply@sansserif.example>'; // cambia dominio
$headers[] = 'Reply-To: '.$email;
$headers[] = 'X-Mailer: PHP/'.phpversion();

// Invio
$ok = @mail($to, $subject, $body, implode("\r\n", $headers));

if ($ok) {
  echo json_encode(['ok'=>true]);
} else {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Invio non riuscito']);
}
