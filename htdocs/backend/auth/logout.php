<?php
session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';  
require_once __DIR__ . '/../assets/funzioni/funzioni.php';

// Logga PRIMA di svuotare la sessione, così abbiamo ancora l'utente_id
registraAttivita($conn, 'Logout eseguito');

// Elimina tutte le variabili di sessione
$_SESSION = [];

// Distrugge la sessione
session_destroy();

// Cancella il cookie di sessione, se presente
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Redirect alla pagina di login
header('Location: login.php');
exit;
