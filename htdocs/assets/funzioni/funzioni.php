function log_visita($pagina) {
    $cookie_key = 'visitata_' . md5($pagina);

    if (isset($_COOKIE[$cookie_key])) {
        return;
    }

    require_once $_SERVER['DOCUMENT_ROOT'] . '/backend/assets/funzioni/db/db.php';

    try {
        $conn = db();

        $stmt = $conn->prepare("INSERT INTO visite_pagine (pagina) VALUES (?)");
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $pagina);
        $stmt->execute();
        $stmt->close();

        // NON chiudere $conn: è condivisa
        setcookie($cookie_key, '1', time() + 3600, "/");
    } catch (Throwable $e) {
        // Non rompere il sito per un log visita
        error_log("log_visita error: " . $e->getMessage());
    }
}
