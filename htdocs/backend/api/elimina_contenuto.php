<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
const ERR_INTERNAL = 'Errore interno';

// Proteggi l'endpoint (se disponibile)
if (function_exists('requireLogin')) { requireLogin(); }

try {
    $conn = db();
} catch (Throwable $e) {
    error_log("elimina_contenuto.php DB init error: " . $e->getMessage());
    http_response_code(500);
  echo json_encode(['success'=>false,'error'=>ERR_INTERNAL], JSON_UNESCAPED_UNICODE);
    exit;
}


$tipo  = $_POST['tipo'] ?? '';
$id    = (int)($_POST['id'] ?? 0);
$valid = ['articolo','libro','cliente'];

if (!in_array($tipo, $valid, true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Tipo o ID non valido'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===================== ARTICOLO ===================== */
if ($tipo === 'articolo') {

    // (facoltativo) recupera info utili per log o per eventuale delete cover file
    $titolo = null;
    $copertina = null;
    if ($rs = $conn->prepare("SELECT titolo, copertina FROM articoli WHERE id=? LIMIT 1")) {
        $rs->bind_param('i', $id);
        $rs->execute();
        $row = $rs->get_result()->fetch_assoc();
        $titolo = $row['titolo'] ?? null;
        $copertina = $row['copertina'] ?? null;
        $rs->close();
    }

    $stmt = $conn->prepare("DELETE FROM articoli WHERE id = ?");
if (!$stmt) {
    error_log("elimina_contenuto.php prepare DELETE articolo failed: ".$conn->error);
    http_response_code(500);
  echo json_encode(['success'=>false,'error'=>ERR_INTERNAL], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param("i", $id);

    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        // 🔹 LOG
        registraAttivita($conn, 'Eliminato articolo ID '.$id.($titolo ? ' - Titolo: '.$titolo : ''));

        // (facoltativo) elimina file copertina se vuoi:
        // if ($copertina) { @unlink(__DIR__ . '/../' . $copertina); }

        echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success'=>false,'error'=>'Eliminazione articolo non riuscita'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* ===================== LIBRO ===================== */
if ($tipo === 'libro') {

    // (facoltativo) recupera info per log / immagine
    $titolo = null;
    $immagine = null;
    if ($rs = $conn->prepare("SELECT titolo, immagine FROM libri WHERE id=? LIMIT 1")) {
        $rs->bind_param('i', $id);
        $rs->execute();
        $row = $rs->get_result()->fetch_assoc();
        $titolo = $row['titolo'] ?? null;
        $immagine = $row['immagine'] ?? null;
        $rs->close();
    }

    $conn->begin_transaction();
    try {
        // 1) elimina pivot categorie
        $p = $conn->prepare("DELETE FROM libri_categorie WHERE libro_id = ?");
        $p->bind_param("i", $id);
        $okPivot = $p->execute();
        $p->close();

        // 2) elimina libro
        $stmt = $conn->prepare("DELETE FROM libri WHERE id = ?");
        $stmt->bind_param("i", $id);
        $okLibro = $stmt->execute();
        $stmt->close();

        if ($okPivot && $okLibro) {
            $conn->commit();

            // 🔹 LOG
            registraAttivita($conn, 'Eliminato libro ID '.$id.($titolo ? ' - Titolo: '.$titolo : ''));

            // (facoltativo) elimina immagine file:
            // if ($immagine) { @unlink(__DIR__ . '/../' . $immagine); }

            echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
        } else {
            $conn->rollback();
            echo json_encode(['success'=>false,'error'=>'Eliminazione libro non riuscita'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(500);
      error_log("elimina_contenuto.php libro exception: ".$e->getMessage());
echo json_encode(['success'=>false,'error'=>ERR_INTERNAL], JSON_UNESCAPED_UNICODE);

    }
    exit;
}

/* ===================== CLIENTE ===================== */

// (facoltativo) info per log
$nomeCliente = null;
if ($rs = $conn->prepare("SELECT nome FROM clienti WHERE id=? LIMIT 1")) {
    $rs->bind_param('i', $id);
    $rs->execute();
    $row = $rs->get_result()->fetch_assoc();
    $nomeCliente = $row['nome'] ?? null;
    $rs->close();
}

$stmt = $conn->prepare("DELETE FROM clienti WHERE id = ?");
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    // 🔹 LOG
    registraAttivita($conn, 'Eliminato cliente ID '.$id.($nomeCliente ? ' - Nome: '.$nomeCliente : ''));
    echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success'=>false,'error'=>'Eliminazione cliente non riuscita'], JSON_UNESCAPED_UNICODE);
}


