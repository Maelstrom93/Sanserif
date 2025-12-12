<?php
declare(strict_types=1);

function esc_html(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function sanitize_header(string $s): string {
  return str_replace(["\r","\n"], '', $s);
}

function normalize_to(string $toRaw): ?string {
  $parts = preg_split('/[;,]/', $toRaw);
  $valid = [];
  foreach ($parts as $p) {
    $p = trim($p);
    if ($p === '') continue;
    if (preg_match('/^(.+?)\s*<\s*([^>]+)\s*>$/', $p, $m)) {
      $addr = filter_var($m[2], FILTER_VALIDATE_EMAIL);
      if ($addr) $valid[] = sprintf('%s <%s>', sanitize_header($m[1]), $addr);
    } else {
      $addr = filter_var($p, FILTER_VALIDATE_EMAIL);
      if ($addr) $valid[] = $addr;
    }
  }
  if (!$valid) return null;
  return implode(', ', array_unique($valid));
}

function load_contact_config(?string $path = null): ?array {
  // Con la tua struttura: assets/funzioni/mail_utils.php -> ../config/contatti.json
  $path = $path ?: __DIR__ . '/../config/contatti.json';
  if (!is_readable($path)) return null;
  $json = json_decode((string)file_get_contents($path), true);
  return is_array($json) ? $json : null;
}

function validate_contact_payload(string $tipo, array $post, ?array $cfg, ?string &$err = null): bool {
  if (!$cfg || !isset($cfg['types'][$tipo])) { $err = 'cfg'; return false; }

  $required = $cfg['types'][$tipo]['required'] ?? [];
  foreach ($required as $k) {
    if (!isset($post[$k]) || trim((string)$post[$k]) === '') { $err = 'validation'; return false; }
  }

  $email = trim((string)($post['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err = 'validation'; return false; }

  $msg = trim((string)($post['msg'] ?? ''));
  if (mb_strlen($msg) < 3) { $err = 'validation'; return false; }

  // ⚠️ Modifica chiave: NON blocchiamo più se l’honeypot è pieno.
  // Se vuoi loggare/sporcare il subject, fallo in send_contact_email().
  // if (isset($post['website']) && trim((string)$post['website']) !== '') { $err = 'send'; return false; }

  return true;
}

function template_abs_path(string $rel): ?string {
  if ($rel === '') return null;
  $assetsDir = realpath(__DIR__ . '/..');
  if (!$assetsDir || !is_dir($assetsDir)) return null;
  $rel = str_replace(['\\', '//'], '/', $rel);
  if (strpos($rel, 'assets/') === 0) $rel = substr($rel, 7);
  $abs = realpath($assetsDir . DIRECTORY_SEPARATOR . $rel);
  if (!$abs) return null;
  if (strpos($abs, $assetsDir . DIRECTORY_SEPARATOR) !== 0) return null;
  $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
  if (!in_array($ext, ['html','htm','txt'], true)) return null;
  if (!is_readable($abs) || filesize($abs) > 200 * 1024) return null;
  return $abs;
}

function render_template_file(string $templateRelPath, array $vars): ?string {
  $tplPath = template_abs_path($templateRelPath);
  if (!$tplPath) return null;
  $tpl = (string)file_get_contents($tplPath);
  $safe = [];
  foreach ($vars as $k => $v) {
    if (is_scalar($v)) $safe[$k] = esc_html((string)$v);
  }
  if (isset($vars['msg'])) $safe['msgHtml'] = nl2br(esc_html((string)$vars['msg']));
  return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function($m) use ($safe){
    $k = $m[1];
    return $safe[$k] ?? '';
  }, $tpl);
}

function build_email_body_html(string $tipo, array $post, array $cfg): ?string {
  $vars = $post;
  $vars['brandName'] = $cfg['brand'] ?? 'Sans Serif';
  $vars['subject']   = $cfg['subjects'][$tipo] ?? 'Richiesta contatto';
  $tplRel            = $cfg['types'][$tipo]['template'] ?? '';
  return render_template_file($tplRel, $vars);
}

function build_email_body_text(string $tipo, array $post, array $cfg): string {
  $brand = $cfg['brand'] ?? 'Sans Serif';
  $lines = [];
  $lines[] = $cfg['subjects'][$tipo] ?? 'Richiesta contatto';
  $lines[] = "Brand: {$brand}";
  $lines[] = str_repeat('-', 40);
  if ($tipo === 'privati') {
    $nome    = trim((string)($post['nome'] ?? ''));
    $cognome = trim((string)($post['cognome'] ?? ''));
    if ($nome || $cognome) $lines[] = "Richiedente: {$nome} {$cognome}";
  } else {
    $rgs = trim((string)($post['rgs'] ?? ''));
    $ref = trim((string)($post['settore'] ?? ''));
    if ($rgs) $lines[] = "Azienda: {$rgs}";
    if ($ref) $lines[] = "Referente: {$ref}";
  }
  $email = trim((string)($post['email'] ?? ''));
  if ($email) $lines[] = "Email: {$email}";
  $lines[] = str_repeat('-', 40);
  $msg = (string)($post['msg'] ?? '');
  $lines[] = "Messaggio:";
  $lines[] = trim($msg);
  $lines[] = str_repeat('-', 40);
  if (isset($post['_meta']) && is_array($post['_meta'])) {
    $ip   = $post['_meta']['ip']         ?? '-';
    $ua   = $post['_meta']['user_agent'] ?? '-';
    $when = $post['_meta']['time_utc']   ?? '-';
    $lines[] = "IP: {$ip}";
    $lines[] = "User-Agent: {$ua}";
    $lines[] = "Data (UTC): {$when}";
  }
  return implode("\n", $lines) . "\n";
}

function pick_from_and_envelope(?string $cfg_from): array {
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $fallback = 'noreply@' . $host;
  $from = trim((string)$cfg_from);
  if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) $from = $fallback;
  $fromDomain = strtolower((string)substr(strrchr($from, "@"), 1));
  $hostLower  = strtolower($host);
  $useEnvelope = false;
  if ($fromDomain !== '') {
    $useEnvelope = ($fromDomain === $hostLower) || str_ends_with($hostLower, '.' . $fromDomain);
  }
  return [$from, $useEnvelope ? ('-f ' . $from) : null];
}

function send_contact_email(string $tipo, array $post, ?string &$err = null): bool {
  $cfg = load_contact_config();
  if (!validate_contact_payload($tipo, $post, $cfg, $err)) return false;

  $toNormalized = normalize_to((string)($cfg['mail_to'] ?? ''));
  if (!$toNormalized) { $err = 'cfg'; return false; }

  $subject = (string)($cfg['subjects'][$tipo] ?? 'Richiesta contatto');
  $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';

  $bodyHtml = build_email_body_html($tipo, $post, $cfg);
  if ($bodyHtml === null) { $err = 'tpl'; return false; }

  $bodyText = build_email_body_text($tipo, $post, $cfg);

  [$from, $envelope] = pick_from_and_envelope($cfg['mail_from'] ?? '');
  $brand = (string)($cfg['brand'] ?? 'Sans Serif');

  $replyRaw = trim((string)($post['email'] ?? ''));
  $reply = filter_var($replyRaw, FILTER_VALIDATE_EMAIL) ? sanitize_header($replyRaw) : null;

  $boundary = 'bnd_' . bin2hex(random_bytes(12));
  $headers = [];
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'From: ' . $brand . ' <' . sanitize_header($from) . '>';
  if ($reply) $headers[] = 'Reply-To: <' . $reply . '>';
  $headers[] = 'X-Mailer: PHP/' . phpversion();
  $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

  $eol = "\r\n";
  $body  = '';
  $body .= '--' . $boundary . $eol;
  $body .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
  $body .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
  $body .= $bodyText . $eol;
  $body .= '--' . $boundary . $eol;
  $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
  $body .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
  $body .= $bodyHtml . $eol;
  $body .= '--' . $boundary . '--' . $eol;

  $ok = $envelope
    ? mail($toNormalized, $subjectEnc, $body, implode($eol, $headers), $envelope)
    : mail($toNormalized, $subjectEnc, $body, implode($eol, $headers));

  if (!$ok) {
    error_log("mail() failed to={$toNormalized} from={$from} env=" . ($envelope ?: 'none'));
    $err = 'mail';
  }
  return $ok;
}
