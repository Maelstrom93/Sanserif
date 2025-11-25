<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['utente'])) {
    header("Location: /backend/auth/login.php");
    exit;
}
