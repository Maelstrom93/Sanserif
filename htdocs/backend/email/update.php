<?php
require_once __DIR__ . '/_auth_guard.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/db/contact_requests.php';

// CSRF
if (empty($_POST['csrf']) || !email_csrf_check((string)$_POST['csrf'])) {
    http_response_code(400);
    echo "Token CSRF non valido";
    exit;
}

$id            = (int)($_POST['id'] ?? 0);
$status        = (string)($_POST['status'] ?? '');
$assigned_to   = trim((string)($_POST['assigned_to'] ?? ''));
$internal_note = trim((string)($_POST['internal_note'] ?? ''));
$closure_reason= trim((string)($_POST['closure_reason'] ?? ''));

$allowed = ['new','in_review','replied','closed'];
if (!$id || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo "Dati non validi";
    exit;
}

// Se "closed" il motivo è obbligatorio
if ($status === 'closed' && $closure_reason === '') {
    http_response_code(400);
    echo "Motivo chiusura obbligatorio";
    exit;
}

// Usa l'update "full" che aggiorna anche closure_reason
$ok = cr_update_admin_full(
    $conn,
    $id,
    $status,
    ($assigned_to !== '' ? $assigned_to : null),
    ($internal_note !== '' ? $internal_note : null),
    ($status === 'closed' ? $closure_reason : null)
);

if (!$ok) {
    http_response_code(500);
    echo "Errore salvataggio";
    exit;
}

// redirect alla view o alla lista: qui teniamo la view
header('Location: /backend/email/view.php?id=' . $id);
exit;
