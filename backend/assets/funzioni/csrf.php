<?php
// assets/funzioni/csrf.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_check_from_post(): void {
  $tok = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
  if (!$tok || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$tok)) {
    http_response_code(419);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'error'=>'CSRF token non valido']);
    exit;
  }
}

function csrf_check_from_json(): void {
  $hdr = $_SERVER['HTTP_X_CSRF'] ?? '';
  $ok = $hdr && hash_equals($_SESSION['csrf_token'] ?? '', (string)$hdr);
  if (!$ok) {
    http_response_code(419);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'error'=>'CSRF token non valido']);
    exit;
  }
}
