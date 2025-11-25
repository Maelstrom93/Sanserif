<?php
declare(strict_types=1);

/**
 * contatti_send.php
 * - CSRF + honeypot
 * - invio email
 * - salvataggio su contact_requests
 * - redirect con ok/err
 */

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'domain'   => '',
  'secure'   => $secure,
  'httponly' => true,
  'samesite' => 'Lax'
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  header('Location: /contatti?err=send', true, 303);
  exit;
}

/* === INCLUDES === */
require_once __DIR__ . '/backend/assets/funzioni/db/db.php';                 // $conn (mysqli)
require_once __DIR__ . '/assets/funzioni/mail_utils.php';                   // send_contact_email
require_once __DIR__ . '/backend/assets/funzioni/db/contact_requests.php';  // save_contact_request

/* === CSRF === */
$posted_token = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted_token)) {
  header('Location: /contatti?err=send', true, 303);
  exit;
}

/* === Honeypot === */
$hp_name = $_SESSION['hp_name'] ?? null;            // generato in contatti.php
$hp_val  = $hp_name ? (string)($_POST[$hp_name] ?? '') : '';
unset($_SESSION['hp_name']); // riduce i tentativi di replay

/* === Tipo e payload === */
$tipo = (isset($_POST['invioazienda']) || isset($_POST['rgs'])) ? 'aziende' : 'privati';

$post = [
  'email'   => trim((string)($_POST['email'] ?? '')),
  'msg'     => trim((string)($_POST['msg'] ?? '')),
  'website' => $hp_val, // honeypot (mail_utils storico lo guarda su 'website')
];

if ($tipo === 'privati') {
  $post['nome']    = trim((string)($_POST['nome'] ?? ''));
  $post['cognome'] = trim((string)($_POST['cognome'] ?? ''));
} else {
  $post['rgs']     = trim((string)($_POST['rgs'] ?? ''));
  $post['settore'] = trim((string)($_POST['settore'] ?? ''));
}

$post['_meta'] = [
  'ip'         => $_SERVER['REMOTE_ADDR']     ?? '',
  'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
  'time_utc'   => gmdate('c'),
];

/* === Invio email === */
$err = null;
$mailOk = send_contact_email($tipo, $post, $err);

/* === Mappatura stati === */
$pipelineStatus = 'new';                              // stato gestionale (pipeline)
$mail_status    = $mailOk ? 'sent' : 'failed';        // stato email -> colonne mail_status/mail_error
$mail_error     = $mailOk ? null   : ($err ?? 'send');

/* === Salvataggio DB === */
if (!($conn instanceof mysqli)) {
  error_log('contatti_send: connessione DB non disponibile.');
} else {
  // Errori MySQL espliciti (utile durante setup; commenta in produzione se vuoi)
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

  $saved = save_contact_request(
    $conn,
    $tipo,
    $post,
    $pipelineStatus,
    $mail_status,
    $mail_error
  );

  if (!$saved) {
    error_log('contatti_send: save_contact_request ha restituito FALSE');
  }
}

/* === Redirect finale === */
if ($mailOk) {
  header('Location: /contatti?ok=1', true, 303);
} else {
  $code = in_array($err, ['validation','cfg','tpl','mail','send'], true) ? $err : 'send';
  header('Location: /contatti?err=' . $code, true, 303);
}
exit;
