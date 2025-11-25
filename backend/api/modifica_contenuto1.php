<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';
require_once '../assets/funzioni/authz.php';
requireLogin();
if (!(currentUserCan('users.manage') || currentUserCan('portfolio.edit'))) {
  http_response_code(403);
  exit(json_encode(['success'=>false,'error'=>'Forbidden']));
}

// (Opzionale ma consigliato): proteggi l'endpoint AJAX
if (function_exists('requireLogin')) { requireLogin(); }

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'DB connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}
$conn->set_charset('utf8mb4');

/* ========== Input base ========== */
$tipo  = $_POST['tipo'] ?? '';
$id    = (int)($_POST['id'] ?? 0);
$valid = ['articolo','libro','cliente'];
if (!in_array($tipo, $valid, true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Tipo o ID non valido'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ========== Upload copertina comune (articolo/libro) ========== */
$imgPath = $_POST['existing_img'] ?? '';
if (!empty($_FILES['cover']['name']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
    // controllo MIME basilare
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES['cover']['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (in_array($mime, $allowed, true)) {
        $ext       = pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION);
        $filename  = uniqid('cov_', true) . '.' . $ext;
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir,0755,true);
        $target    = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['cover']['tmp_name'], $target)) {
            $imgPath = 'uploads/' . $filename;
        }
    }
}

/* ========== ARTICOLO ========== */
if ($tipo === 'articolo') {
    $titolo    = $_POST['title']   ?? '';
    $descr     = $_POST['excerpt'] ?? '';
    $contenuto = $_POST['content'] ?? '';
    $categoria = (int)($_POST['category'] ?? 0);

    $stmt = $conn->prepare("
      UPDATE articoli
      SET titolo=?, descrizione=?, contenuto=?, categoria=?, copertina=?
      WHERE id=?
    ");
    $stmt->bind_param("sssisi", $titolo, $descr, $contenuto, $categoria, $imgPath, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        // 🔹 LOG
        registraAttivita($conn, 'Aggiornato articolo ID '.$id.' - Titolo: '.$titolo);
    }

    echo json_encode(['success'=>$ok], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ========== LIBRO ========== */
if ($tipo === 'libro') {
    $titolo       = $_POST['titolo']  ?? '';
    $sinossi      = $_POST['excerpt'] ?? '';
    $pubDate      = $_POST['date']    ?? null;
    $link         = $_POST['link']    ?? '';
    $casaEditrice = trim($_POST['casa_editrice'] ?? '');

    // categorie (array)
    $cats = $_POST['category'] ?? [];
    if (!is_array($cats)) $cats = [$cats];

    // nuove categorie (stringa comma-separated)
    $newCat = trim($_POST['new_category'] ?? '');
    if ($newCat !== '') {
        $parts = array_filter(array_map('trim', explode(',', $newCat)));
        foreach ($parts as $nome) {
            if ($nome === '') continue;
            $lower = mb_strtolower($nome, 'UTF-8');

            $chk = $conn->prepare("SELECT id FROM categorie_libri WHERE LOWER(nome)=? LIMIT 1");
            $chk->bind_param('s', $lower);
            $chk->execute();
            $rs = $chk->get_result();
            if ($rs && ($row = $rs->fetch_assoc())) {
                $cats[] = (int)$row['id'];
            } else {
                $ins = $conn->prepare("INSERT INTO categorie_libri (nome) VALUES (?)");
                $ins->bind_param('s', $nome);
                $ins->execute();
                if ($ins->insert_id) $cats[] = (int)$ins->insert_id;
                $ins->close();
            }
            $chk->close();
        }
    }
    $cats = array_values(array_unique(array_map('intval', $cats)));

    // Transazione: update + pivot
    $conn->begin_transaction();
    try {
        // 1) update libri
        $stmt = $conn->prepare("
          UPDATE libri
          SET titolo = ?,
              sinossi = ?,
              immagine = ?,
              data_pubblicazione = ?,
              casa_editrice = ?,
              link = ?
          WHERE id = ?
        ");
        $stmt->bind_param("ssssssi", $titolo, $sinossi, $imgPath, $pubDate, $casaEditrice, $link, $id);
        $ok1 = $stmt->execute();
        $stmt->close();

        // 2) pulizia pivot categorie (prepared)
        $del = $conn->prepare("DELETE FROM libri_categorie WHERE libro_id = ?");
        $del->bind_param('i', $id);
        $ok2 = $del->execute();
        $del->close();

        // 3) re-insert pivot
        $ok3 = true;
        if (!empty($cats)) {
            $stmt2 = $conn->prepare("INSERT INTO libri_categorie(libro_id, categoria_id) VALUES(?,?)");
            foreach ($cats as $cid) {
                $stmt2->bind_param("ii", $id, $cid);
                $ok3 = $ok3 && $stmt2->execute();
            }
            $stmt2->close();
        }

        if ($ok1 && $ok2 && $ok3) {
            $conn->commit();
            // 🔹 LOG
            registraAttivita($conn, 'Aggiornato libro ID '.$id.' - Titolo: '.$titolo.' - Categorie: '.implode(',', $cats));
            echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
        } else {
            $conn->rollback();
            echo json_encode(['success'=>false,'error'=>'Update libri non riuscito'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Eccezione: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* ========== CLIENTE ========== */
$nome          = $_POST['nome']           ?? '';
$referente1    = $_POST['referente_1']    ?? '';
$referente2    = $_POST['referente_2']    ?? '';
$telefono      = $_POST['telefono']       ?? '';
$email         = $_POST['email']          ?? '';
$partitaIva    = $_POST['partita_iva']    ?? '';
$codiceUnivoco = $_POST['codice_univoco'] ?? '';
$indirizzo     = $_POST['indirizzo']      ?? '';
$cap           = $_POST['cap']            ?? '';
$citta         = $_POST['citta']          ?? '';

$stmt = $conn->prepare("
  UPDATE clienti
  SET nome=?,
      referente_1=?,
      referente_2=?,
      telefono=?,
      email=?,
      partita_iva=?,
      codice_univoco=?,
      indirizzo=?,
      cap=?,
      `città`=?
  WHERE id=?
");
$stmt->bind_param(
  "ssssssssssi",
  $nome, $referente1, $referente2, $telefono, $email,
  $partitaIva, $codiceUnivoco, $indirizzo, $cap, $citta, $id
);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    // 🔹 LOG
    registraAttivita($conn, 'Aggiornato cliente ID '.$id.' - Nome: '.$nome);
}

echo json_encode(['success'=>$ok], JSON_UNESCAPED_UNICODE);
