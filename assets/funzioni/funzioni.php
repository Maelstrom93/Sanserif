<?php

function log_visita($pagina) {
    $cookie_key = 'visitata_' . md5($pagina);

    if (!isset($_COOKIE[$cookie_key])) {

        // Carica la connessione al database
   require $_SERVER['DOCUMENT_ROOT'] . '/backend/assets/funzioni/db/db.php';
      
        if ($conn->connect_error) return;

        $stmt = $conn->prepare("INSERT INTO visite_pagine (pagina) VALUES (?)");
        $stmt->bind_param("s", $pagina);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        setcookie($cookie_key, '1', time() + 3600, "/");
    }
}

?>
