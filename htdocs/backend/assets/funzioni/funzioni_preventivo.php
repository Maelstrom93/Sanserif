<?php
/**
 * Funzioni per gestione Preventivi (mysqli).
 * Dipende da una connessione mysqli valida passata come $conn.
 */

/** Escape HTML */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Format data YYYY-MM-DD -> DD/MM/YYYY (o '-' se vuoto) */
function formatDataIt(?string $date){
  if (!$date) return '-';
  $ts = strtotime($date);
  return $ts ? date('d/m/Y', $ts) : '-';
}

/** Format importo in Euro con separatori italiani */
function formatEuro($num){
  $n = (float)$num;
  return number_format($n, 2, ',', '.') . ' €';
}

/**
 * Elenco preventivi (per tabella)
 * Ritorna mysqli_result oppure false
 */
function getPreventivi(mysqli $conn){
  $sql = "
    SELECT p.id,
           p.data,
           p.totale_con_iva AS totale,
           p.data_creazione,
           p.cliente_nome_custom,
           c.nome AS cliente_nome
    FROM preventivi p
    LEFT JOIN clienti c ON c.id = p.cliente_id
    ORDER BY p.data DESC, p.id DESC
  ";
  return $conn->query($sql);
}

/**
 * Elimina un preventivo e le sue righe in transazione
 * @return bool esito
 */
function deletePreventivo(mysqli $conn, int $id): bool{
  // Disabilita autocommit e avvia transazione
  $conn->begin_transaction();
  try{
    // Elimina righe figlie
    $stmt1 = $conn->prepare("DELETE FROM righe_preventivo WHERE preventivo_id = ?");
    if (!$stmt1) throw new Exception($conn->error);
    $stmt1->bind_param('i', $id);
    if (!$stmt1->execute()) throw new Exception($stmt1->error);

    // Elimina il preventivo
    $stmt2 = $conn->prepare("DELETE FROM preventivi WHERE id = ?");
    if (!$stmt2) throw new Exception($conn->error);
    $stmt2->bind_param('i', $id);
    if (!$stmt2->execute()) throw new Exception($stmt2->error);

    $conn->commit();
    return true;
  }catch(Throwable $t){
    $conn->rollback();
    return false;
  }
}

/** Conta risultati in un mysqli_result */
function countResult($res): int{
  return ($res instanceof mysqli_result) ? (int)$res->num_rows : 0;
}


/** Ritorna tutti i clienti in array associativo */
function getClienti(mysqli $conn): array {
  $out = [];
  $sql = "SELECT id, nome FROM clienti ORDER BY nome ASC";
  if ($res = $conn->query($sql)) {
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $res->free();
  }
  return $out;
}

/** Data di oggi nel formato Y-m-d */
function getDataOggi(): string {
  return date('Y-m-d');
}

/** Data di validità predefinita (oggi +30 giorni) */
function getValiditaDefault(int $giorni = 30): string {
  return date('Y-m-d', strtotime("+{$giorni} days"));
}

/*funzioni Genera_preventivo*/

/**
 * funzioni_preventivo.php
 * Solo logica: connessione DB, CRUD preventivi/clienti, calcoli e preparazione dati.
 * Nessun output/echo, nessuna sessione, nessun codice PDF qui dentro.
 */

/* ===========================
 * CONNESSIONE DB
 * =========================== */
function db_connect_preventivi(): mysqli {
    require_once __DIR__ . '/db/db.php';
    return db();
}


/* ===========================
 * CLIENTI
 * =========================== */
function get_cliente(mysqli $conn, int $cliente_id): ?array {
    $stmt = $conn->prepare("SELECT * FROM clienti WHERE id = ?");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $cliente = $res->fetch_assoc() ?: null;
    $stmt->close();
    return $cliente;
}

function inserisci_cliente_alt(mysqli $conn, array $post): int {
    $sql = "
        INSERT INTO clienti (nome, referente_1, referente_2, telefono, email, partita_iva, codice_univoco, indirizzo, cap, città)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql);
    $nome = trim($post['cliente_alt_nome'] ?? '');
    $stmt->bind_param(
        "ssssssssss",
        $nome,
        $post['cliente_alt_referente1'] ?? '',
        $post['cliente_alt_referente2'] ?? '',
        $post['cliente_alt_telefono'] ?? '',
        $post['cliente_alt_email'] ?? '',
        $post['cliente_alt_partita_iva'] ?? '',
        $post['cliente_alt_codice_univoco'] ?? '',
        $post['cliente_alt_indirizzo'] ?? '',
        $post['cliente_alt_cap'] ?? '',
        $post['cliente_alt_citta'] ?? ''
    );
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

/* ===========================
 * PREVENTIVI - LETTURA
 * =========================== */
function get_preventivo_completo(mysqli $conn, int $preventivo_id): array {
    // Preventivo
    $stmt = $conn->prepare("SELECT * FROM preventivi WHERE id = ?");
    $stmt->bind_param("i", $preventivo_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $preventivo = $res->fetch_assoc();
    $stmt->close();

    if (!$preventivo) {
        throw new InvalidArgumentException("Preventivo non trovato");
    }

    // Cliente (se collegato)
    $cliente = [];
    if (!empty($preventivo['cliente_id'])) {
        $cliente = get_cliente($conn, (int)$preventivo['cliente_id']) ?? [];
    }

    // Righe
    $righe = get_righe_preventivo($conn, $preventivo_id);

    // Totali/calcoli (ricalcolo per sicurezza, ma mantengo anche i campi salvati)
    $calc = calcola_totali_da_righe(
        $righe,
        (float)$preventivo['sconto'],
        (float)$preventivo['iva']
    );

    // Nome cliente “risolto”
    $cliente_nome = $cliente['nome'] ?? $preventivo['cliente_nome_custom'] ?? 'Cliente Sconosciuto';

    return [
        'preventivo'   => $preventivo,
        'cliente'      => $cliente,
        'righe'        => $righe,
        'cliente_nome' => $cliente_nome,
        'calcoli'      => $calc, // ['totale','scontoVal','ivaVal','totaleFinale']
    ];
}

/* ===========================
 * PREVENTIVI - CREAZIONE
 * =========================== */
function genera_numero_progressivo(mysqli $conn, int $anno): int {
    $stmt = $conn->prepare("SELECT MAX(numero) AS ultimo FROM preventivi WHERE anno = ?");
    $stmt->bind_param("i", $anno);
    $stmt->execute();
    $res = $stmt->get_result();
    $numero = (int)(($res->fetch_assoc()['ultimo'] ?? 0) + 1);
    $stmt->close();
    return $numero;
}

/**
 * Prepara i dati del nuovo preventivo a partire dal POST (senza inserire).
 * Ritorna:
 * [
 *   'cliente_id','cliente_alt','referente_custom','data','valido_fino','pagamento',
 *   'iva','sconto','note','righe' (array di righe pulite),
 *   'calcoli' => ['totale','scontoVal','ivaVal','totaleFinale']
 *   'cliente_nome'
 * ]
 */
function prepara_dati_nuovo_preventivo(mysqli $conn, array $post): array {
    $cliente_id        = (int)($post['cliente_id'] ?? 0);
    $cliente_alt       = trim($post['cliente_alt_nome'] ?? '');
    $referente_custom  = trim($post['referente_custom'] ?? '');
    $data              = $post['data'] ?? date('Y-m-d');
    $valido_fino       = $post['valido_fino'] ?? '';
    $pagamento         = $post['pagamento'] ?? '';
    $iva               = (float)($post['iva'] ?? 22);
    $sconto            = (float)($post['sconto'] ?? 0);
    $note              = trim($post['note'] ?? '');
    $descrizioni       = $post['descrizione'] ?? [];
    $quantita          = $post['quantita'] ?? [];
    $prezzi            = $post['prezzo'] ?? [];

    // Se non ho cliente_id ma ho un cliente_alt, lo inserisco
    if (!$cliente_id && $cliente_alt !== '') {
        $cliente_id = inserisci_cliente_alt($conn, $post);
    }

    // Recupero cliente (se esiste)
    $cliente = $cliente_id ? (get_cliente($conn, $cliente_id) ?? []) : [];
    $cliente_nome = $cliente['nome'] ?? $cliente_alt ?? 'Cliente Sconosciuto';

    // Pulizia righe
    $righe = [];
    foreach ($descrizioni as $i => $desc) {
        $desc   = trim((string)$desc);
        $qta    = (int)($quantita[$i] ?? 0);
        $prezzo = (float)($prezzi[$i] ?? 0);
        if ($desc === '' || $qta <= 0) continue;

        $subTotale = $qta * $prezzo;
        $righe[] = [
            'descrizione'     => $desc,
            'quantita'        => $qta,
            'prezzo_unitario' => $prezzo,
            'totale_riga'     => $subTotale
        ];
    }

    // Calcoli
    $calcoli = calcola_totali_da_righe($righe, $sconto, $iva);

    return [
        'cliente_id'       => $cliente_id,
        'cliente_alt'      => $cliente_alt,
        'cliente'          => $cliente,
        'cliente_nome'     => $cliente_nome,
        'referente_custom' => $referente_custom,
        'data'             => $data,
        'valido_fino'      => $valido_fino,
        'pagamento'        => $pagamento,
        'iva'              => $iva,
        'sconto'           => $sconto,
        'note'             => $note,
        'righe'            => $righe,
        'calcoli'          => $calcoli
    ];
}

/**
 * Inserisce il preventivo e le righe.
 * Ritorna l'array completo come get_preventivo_completo().
 */
function crea_preventivo(mysqli $conn, array $dati): array {
    // Numero progressivo per l'anno corrente (o per anno di $dati['data'] se preferisci)
    $anno   = (int)date('Y', strtotime($dati['data'] ?? date('Y-m-d')));
    $numero = genera_numero_progressivo($conn, $anno);

    // Inserisci preventivo
    $sql = "
        INSERT INTO preventivi (
            numero, anno, cliente_id, cliente_nome_custom, referente_custom, data, valido_fino,
            pagamento, iva, sconto, note, totale, totale_con_iva, data_creazione
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    $stmt = $conn->prepare($sql);
    $totale        = (float)$dati['calcoli']['totale'];
    $totaleFinale  = (float)$dati['calcoli']['totaleFinale'];
    $cliente_id    = (int)($dati['cliente_id'] ?? 0);
    $cliente_alt   = $dati['cliente_alt'] ?? '';

    $stmt->bind_param(
        "iiisssssddssd",
        $numero, $anno, $cliente_id, $cliente_alt, $dati['referente_custom'],
        $dati['data'], $dati['valido_fino'], $dati['pagamento'],
        $dati['iva'], $dati['sconto'], $dati['note'], $totale, $totaleFinale
    );
    $stmt->execute();
    $preventivo_id = $stmt->insert_id;
    $stmt->close();

    // Inserisci righe
    inserisci_righe_preventivo($conn, $preventivo_id, $dati['righe']);

    // Ritorna il pacchetto completo
    return get_preventivo_completo($conn, $preventivo_id);
}

/* ===========================
 * RIGHE PREVENTIVO
 * =========================== */
function get_righe_preventivo(mysqli $conn, int $preventivo_id): array {
    $righe = [];
    $stmt = $conn->prepare("
        SELECT descrizione, quantita, prezzo_unitario, totale_riga
        FROM righe_preventivo
        WHERE preventivo_id = ?
        ORDER BY id ASC
    ");
    $stmt->bind_param("i", $preventivo_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        // Normalizzo i tipi
        $righe[] = [
            'descrizione'     => (string)$r['descrizione'],
            'quantita'        => (int)$r['quantita'],
            'prezzo_unitario' => (float)$r['prezzo_unitario'],
            'totale_riga'     => (float)$r['totale_riga'],
        ];
    }
    $stmt->close();
    return $righe;
}

function inserisci_righe_preventivo(mysqli $conn, int $preventivo_id, array $righe): void {
    if (empty($righe)) return;
    $stmt = $conn->prepare("
        INSERT INTO righe_preventivo (preventivo_id, descrizione, quantita, prezzo_unitario, totale_riga)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($righe as $r) {
        $desc   = (string)$r['descrizione'];
        $qta    = (int)$r['quantita'];
        $prezzo = (float)$r['prezzo_unitario'];
        $tot    = (float)$r['totale_riga'];
        $stmt->bind_param("isidd", $preventivo_id, $desc, $qta, $prezzo, $tot);
        $stmt->execute();
    }
    $stmt->close();
}

/* ===========================
 * CALCOLI
 * =========================== */
function calcola_totali_da_righe(array $righe, float $sconto, float $iva): array {
    $totale = 0.0;
    foreach ($righe as $r) {
        $totale += (float)$r['totale_riga'];
    }
    $scontoVal      = $totale * max(0.0, $sconto) / 100.0;
    $totaleScontato = $totale - $scontoVal;
    $ivaVal         = $totaleScontato * max(0.0, $iva) / 100.0;
    $totaleFinale   = $totaleScontato + $ivaVal;

    return [
        'totale'       => $totale,
        'scontoVal'    => $scontoVal,
        'ivaVal'       => $ivaVal,
        'totaleFinale' => $totaleFinale,
    ];
}

/* ===========================
 * FACILITATORI PER LO SCRIPT DI STAMPA
 * =========================== */

/**
 * Carica i dati per stampa da ID (preventivo esistente).
 * Ritorna array con: numero, anno, data, valido_fino, pagamento, iva, sconto, note,
 * totale, totaleFinale, scontoVal, ivaVal, righe, cliente, cliente_nome, referente_custom
 */
function prepara_stampa_da_id(mysqli $conn, int $preventivo_id): array {
    $pkg = get_preventivo_completo($conn, $preventivo_id);

    $p = $pkg['preventivo'];
    $calc = $pkg['calcoli'];

    return [
        'numero'           => $p['numero'],
        'anno'             => $p['anno'],
        'data'             => $p['data'],
        'valido_fino'      => $p['valido_fino'],
        'pagamento'        => $p['pagamento'],
        'iva'              => (float)$p['iva'],
        'sconto'           => (float)$p['sconto'],
        'note'             => $p['note'],
        'totale'           => (float)$p['totale'],
        'totaleFinale'     => (float)$p['totale_con_iva'],
        'scontoVal'        => (float)$calc['scontoVal'],
        'ivaVal'           => (float)$calc['ivaVal'],
        'righe'            => $pkg['righe'],
        'cliente'          => $pkg['cliente'],
        'cliente_nome'     => $pkg['cliente_nome'],
        'referente_custom' => $p['referente_custom'] ?? ''
    ];
}

/**
 * Crea il preventivo da $_POST e ritorna i dati pronti per la stampa.
 */
function prepara_stampa_da_post(mysqli $conn, array $post): array {
    $dati = prepara_dati_nuovo_preventivo($conn, $post);
    $pkg  = crea_preventivo($conn, $dati);

    $p = $pkg['preventivo'];
    $calc = $pkg['calcoli'];

    return [
        'numero'           => $p['numero'],
        'anno'             => $p['anno'],
        'data'             => $p['data'],
        'valido_fino'      => $p['valido_fino'],
        'pagamento'        => $p['pagamento'],
        'iva'              => (float)$p['iva'],
        'sconto'           => (float)$p['sconto'],
        'note'             => $p['note'],
        'totale'           => (float)$p['totale'],
        'totaleFinale'     => (float)$p['totale_con_iva'],
        'scontoVal'        => (float)$calc['scontoVal'],
        'ivaVal'           => (float)$calc['ivaVal'],
        'righe'            => $pkg['righe'],
        'cliente'          => $pkg['cliente'],
        'cliente_nome'     => $pkg['cliente_nome'],
        'referente_custom' => $p['referente_custom'] ?? ''
    ];
}

