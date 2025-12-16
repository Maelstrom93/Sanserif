<?php
// htdocs/assets/funzioni/session_https.php

function ssIsHttpsRequest(): bool
{
  $https = $_SERVER['HTTPS'] ?? '';
  if (!empty($https) && $https !== 'off') {
    return true;
  }

  $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
  if ($xfp === 'https') {
    return true;
  }

  $xfs = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '';
  if ($xfs === 'on') {
    return true;
  }

  return false;
}

function ssForceHttps(): void
{
  if (ssIsHttpsRequest()) {
    return;
  }

  $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
  $uri  = $_SERVER['REQUEST_URI'] ?? '/';

  header('Location: https://' . $host . $uri, true, 301);
  exit;
}

function ssBootstrapHttpsSession(): void
{
  ssForceHttps();

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
