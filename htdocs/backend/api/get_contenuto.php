<?php
require_once '../assets/funzioni/authz.php';
require_once '../assets/funzioni/funzioni.php';



// get_contenuto.php
header('Content-Type: application/json; charset=utf-8');

require_once '../assets/funzioni/db/db.php';

$tipo = $_GET['tipo'] ?? 'articolo';
$id   = intval($_GET['id'] ?? 0);

$valid = ['articolo','libro','cliente'];
if (!in_array($tipo, $valid, true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error'=>'Parametro tipo o id non valido'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($tipo === 'articolo') {
    $stmt = $conn->prepare("
      SELECT
        id,
        titolo,
        descrizione,
        contenuto,
        categoria    AS categoria_id,
        copertina,
        cover_json,
        data_pubblicazione
      FROM articoli
      WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error'=>'Elemento non trovato'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $item = $res->fetch_assoc();

    // tutte le categorie articoli
    $catRes = $conn->query("SELECT id, nome FROM categorie_articoli ORDER BY nome");
    $item['categorie'] = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

    // opzionale: normalizza path copertina in output legacy (se vuoi)
    // $item['copertina'] = norm_img_php($item['copertina'] ?? '');

    echo json_encode($item, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($tipo === 'libro') {
    $stmt = $conn->prepare("
      SELECT
        id,
        titolo,
        sinossi    AS descrizione,
        sinossi    AS contenuto,
        immagine   AS copertina,
        cover_json,
        link       AS link,
        data_pubblicazione,
        casa_editrice
      FROM libri
      WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error'=>'Elemento non trovato'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $item = $res->fetch_assoc();

    // categorie già assegnate
    $item['categorie_ids'] = [];
    $pivotStmt = $conn->prepare("SELECT categoria_id FROM libri_categorie WHERE libro_id = ?");
    $pivotStmt->bind_param("i", $id);
    $pivotStmt->execute();
    $pivotRes = $pivotStmt->get_result();
    while ($r = $pivotRes->fetch_assoc()) $item['categorie_ids'][] = (int)$r['categoria_id'];

    // tutte le categorie disponibili (libri)
    $allCatRes = $conn->query("SELECT id, nome FROM categorie_libri ORDER BY nome");
    $item['categorie'] = $allCatRes ? $allCatRes->fetch_all(MYSQLI_ASSOC) : [];

    // datalist case editrici (distinte)
    $eds = [];
    $resEd = $conn->query("SELECT DISTINCT TRIM(casa_editrice) AS nome FROM libri WHERE TRIM(COALESCE(casa_editrice,'')) <> '' ORDER BY nome");
    if ($resEd) { while ($r = $resEd->fetch_assoc()) $eds[] = (string)$r['nome']; }
    $item['editori'] = $eds;

    // opzionale: normalizza path copertina legacy
    // $item['copertina'] = norm_img_php($item['copertina'] ?? '');

    echo json_encode($item, JSON_UNESCAPED_UNICODE);
    exit;
}


/* === CLIENTE === */
$stmt = $conn->prepare("
  SELECT
    id,
    nome,
    referente_1,
    referente_2,
    telefono,
    email,
    partita_iva,
    codice_univoco,
    indirizzo,
    cap,
    `città` AS citta
  FROM clienti
  WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error'=>'Elemento non trovato'], JSON_UNESCAPED_UNICODE);
    exit;
}
$item = $res->fetch_assoc();

// Per compatibilità con frontend che può leggere entrambe le chiavi
$item['città'] = $item['citta'];

echo json_encode($item, JSON_UNESCAPED_UNICODE);
