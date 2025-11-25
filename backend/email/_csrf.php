<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function email_csrf_token(): string {
    if (empty($_SESSION['email_csrf'])) {
        $_SESSION['email_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['email_csrf'];
}
function email_csrf_check(string $t): bool {
    return !empty($_SESSION['email_csrf']) && hash_equals($_SESSION['email_csrf'], $t);
}
