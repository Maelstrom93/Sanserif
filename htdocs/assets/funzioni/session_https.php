<?php
// htdocs/assets/funzioni/session_https.php

function ss_is_https_request(): bool
{
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
  if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') return true;
  return false;
}

function ss_force_https(): void
{
  if (ss_is_https_request()) return;

  $host = $_SERVER['HTTP_X_FORWARDED_HOST']
    ?? ($_SERVER['HTTP_HOST'] ?? '');

  $uri  = $_SERVER['REQUEST_URI'] ?? '/';

  header('Location: https://' . $host . $uri, true, 301);
  exit;
}

function ss_bootstrap_https_session(): void
{
  ss_force_https();

  // A questo punto siamo sicuramente in HTTPS -> secure=true è sempre safe
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
}
