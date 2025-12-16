<?php
// htdocs/assets/funzioni/session_https.php

function ssIsHttpsRequest(): bool
{
  $isHttps = false;

  $https = $_SERVER['HTTPS'] ?? '';
  if (!empty($https) && $https !== 'off') {
    $isHttps = true;
  }

  $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
  if ($xfp === 'https') {
    $isHttps = true;
  }

  $xfs = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '';
  if ($xfs === 'on') {
    $isHttps = true;
  }

  return $isHttps;
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
