<?php
declare(strict_types=1);
const MIME_AVIF = 'image/avif';
const MIME_WEBP = 'image/webp';
const MIME_JPEG = 'image/jpeg';
const MIME_PNG  = 'image/png';

/** Escape HTML sicuro e compatto */
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** JSON encode con flags sicuri per output in attributi HTML */
function j($value): string {
    return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Formattazione data (input ISO o qualsiasi string parsabile).
 * Tenta IntlDateFormatter in it_IT, fallback a d/m/Y.
 */
function formattaData(string $dataISO): string {
    $ts = strtotime($dataISO);
    if (!$ts) return $dataISO;

    if (class_exists('IntlDateFormatter')) {
        static $fmt = null;
        if ($fmt === null) {
            $fmt = new IntlDateFormatter(
                'it_IT',
                IntlDateFormatter::LONG,
                IntlDateFormatter::NONE,
                date_default_timezone_get(),
                IntlDateFormatter::GREGORIAN,
                "d MMMM yyyy"
            );
        }
        $formatted = $fmt->format($ts);
        if ($formatted !== false) return (string)$formatted;
    }
    return date('d/m/Y', $ts);
}

/**
 * @deprecated Usa formattaData(). Mantiene i mesi in italiano.
 * @param mixed $data
 */
function formattaDataItaliano($data): string {
    $timestamp = strtotime((string)$data);
    if (!$timestamp) return (string)$data;
    $mesi = [
        '01'=>'gennaio','02'=>'febbraio','03'=>'marzo','04'=>'aprile','05'=>'maggio','06'=>'giugno',
        '07'=>'luglio','08'=>'agosto','09'=>'settembre','10'=>'ottobre','11'=>'novembre','12'=>'dicembre'
    ];
    $m = date('m', $timestamp);
    return date('d', $timestamp) . ' ' . ($mesi[$m] ?? $m) . ' ' . date('Y', $timestamp);
}

/** ========================= Auth helpers ========================= */

function isAdmin(): bool {
    return isset($_SESSION['utente']) && (($_SESSION['utente']['ruolo'] ?? '') === 'admin');
}

function isLogged(): bool {
    return !empty($_SESSION['utente']);
}

function requireLogin(): void {
    if (!isLogged()) {
        header("Location: /backend/auth/login.php");
        exit;
    }
}

/**
 * =========================================================
 * Helpers DB generici
 * =========================================================
 */

function tableHasColumn(mysqli $conn, string $table, string $column): bool {
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM $table LIKE '$column'";
    $res = $conn->query($sql);
    return (bool)($res && $res->num_rows > 0);
}

function tableExists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return (bool)($res && $res->num_rows > 0);
}

/**
 * =========================================================
 * Calendario / Flusso lavoro
 * =========================================================
 */

function getEventiFlussoLavoro(mysqli $conn, string $start, string $end): array {
    $stmt = $conn->prepare("
        SELECT nome, data_evento, assegnato_a, colore
        FROM flusso_lavoro
        WHERE data_evento BETWEEN ? AND ?
    ");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = [
            'title'     => $r['nome'],
            'start'     => $r['data_evento'],
            'assegnato' => $r['assegnato_a'],
            'color'     => $r['colore'] ?: '#004c60'
        ];
    }
    $stmt->close();
    return $out;
}

function getProssimeScadenze(mysqli $conn, int $limit = 5): array {
    $stmt = $conn->prepare("
        SELECT nome, data_evento, assegnato_a
        FROM flusso_lavoro
        WHERE data_evento >= NOW()
        ORDER BY data_evento ASC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function getScadenzeOggi(mysqli $conn): int {
    $oggi = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) AS tot FROM flusso_lavoro WHERE DATE(data_evento) = ?");
    $stmt->bind_param('s', $oggi);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($res['tot'] ?? 0);
}

/**
 * =========================================================
 * Contact Requests
 * =========================================================
 */

function getContactRequestStats(mysqli $conn, array $openStatuses = ['new','in_review']): array {
    $start = (new DateTimeImmutable('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
    $end   = (new DateTimeImmutable('last day of this month 23:59:59'))->format('Y-m-d H:i:s');

    // richieste mese
    $stmt = $conn->prepare("SELECT COUNT(*) AS tot FROM contact_requests WHERE created_at BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $totMese = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);
    $stmt->close();

    // da evadere
    if (!empty($openStatuses)) {
        $place = implode(',', array_fill(0, count($openStatuses), '?'));
        $types = str_repeat('s', count($openStatuses));
        $sqlOpen = "SELECT COUNT(*) AS tot FROM contact_requests WHERE (status IS NULL OR status = '' OR status IN ($place))";
        $stmt2 = $conn->prepare($sqlOpen);
        $stmt2->bind_param($types, ...$openStatuses);
    } else {
        $stmt2 = $conn->prepare("SELECT COUNT(*) AS tot FROM contact_requests WHERE status IS NULL OR status = ''");
    }
    $stmt2->execute();
    $daEvadere = (int)($stmt2->get_result()->fetch_assoc()['tot'] ?? 0);
    $stmt2->close();

    return ['richiesteMese'=>$totMese, 'richiesteDaEvadere'=>$daEvadere];
}

/** Traccia una visualizzazione (se esiste contact_request_views) evitando duplicati 5' */
function trackRequestView(mysqli $conn, int $requestId, ?int $userId): void {
    if ($requestId <= 0) return;
    if (!tableExists($conn, 'contact_request_views')) return;

    if ($userId) {
        $stmt = $conn->prepare("
            SELECT 1 FROM contact_request_views
            WHERE request_id = ? AND user_id = ?
              AND viewed_at >= (NOW() - INTERVAL 5 MINUTE)
            LIMIT 1
        ");
        $stmt->bind_param('ii', $requestId, $userId);
        $stmt->execute();
        $has = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($has) return;
    }

    if ($userId) {
        $ins = $conn->prepare("INSERT INTO contact_request_views (request_id, user_id) VALUES (?, ?)");
        $ins->bind_param('ii', $requestId, $userId);
    } else {
        $ins = $conn->prepare("INSERT INTO contact_request_views (request_id) VALUES (?)");
        $ins->bind_param('i', $requestId);
    }
    $ins->execute();
    $ins->close();
}

/** Passa da new -> in_review al primo accesso */
function markRequestInReviewIfNew(mysqli $conn, int $requestId): void {
    if ($requestId <= 0) return;
    $stmt = $conn->prepare("
        UPDATE contact_requests
        SET status = 'in_review', updated_at = NOW()
        WHERE id = ? AND (status IS NULL OR status = '' OR status = 'new')
        LIMIT 1
    ");
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $stmt->close();
}

/** Ultimi viewer per richiesta (mappa: request_id => ['count'=>N,'users'=>[]]) */
function getRecentViewersForRequests(mysqli $conn, array $requestIds, int $limitNames=3): array {
    if (!$requestIds) return [];
    if (!tableExists($conn,'contact_request_views')) return [];

    $ids = implode(',', array_map('intval', $requestIds));
    $joinUtenti = tableExists($conn, 'utenti') ? "LEFT JOIN utenti u ON u.id = v.user_id" : "";
    $selectNome = tableExists($conn, 'utenti') ? "TRIM(u.nome)" : "CONCAT('User #', v.user_id)";
    $sql = "
        SELECT v.request_id, v.user_id, $selectNome AS nome
        FROM contact_request_views v
        $joinUtenti
        WHERE v.request_id IN ($ids)
        ORDER BY v.viewed_at DESC
    ";
    $res = $conn->query($sql);

    $map = [];
    while($r=$res->fetch_assoc()){
        $rid=(int)$r['request_id'];
        $name=trim($r['nome'] ?? '');
        if(!isset($map[$rid])) $map[$rid] = ['count'=>0, 'users'=>[]];
        $map[$rid]['count']++;
        if ($name && count($map[$rid]['users']) < $limitNames) $map[$rid]['users'][] = $name;
    }
    return $map;
}

function getContactAssignedOpenCount(mysqli $conn, int $userId, array $openStatuses = ['new','in_review']): int {
    if ($userId <= 0) return 0;
    $place = implode(',', array_fill(0, count($openStatuses), '?'));
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS tot
        FROM contact_requests
        WHERE assigned_to = ?
          AND (status IS NULL OR status = '' OR status IN ($place))
    ");
    $stmt->bind_param('i'.str_repeat('s', count($openStatuses)), $userId, ...$openStatuses);
    $stmt->execute();
    $tot = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);
    $stmt->close();
    return $tot;
}

function getUltimeRichieste(mysqli $conn, int $limit = 8): array {
    $stmt = $conn->prepare("
        SELECT id, tipo, nome, cognome, rgs, email, created_at, status, assigned_to
        FROM contact_requests
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function getContactRequestsByDayThisMonth(mysqli $conn): array {
    $start = (new DateTimeImmutable('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
    $end   = (new DateTimeImmutable('last day of this month 23:59:59'))->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("
        SELECT DATE(created_at) AS giorno, COUNT(*) AS tot
        FROM contact_requests
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY giorno ASC
    ");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();

    $out = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $out[$r['giorno']] = (int)$r['tot'];
    }
    $stmt->close();
    return $out;
}

function getContactCountsByStatus(mysqli $conn, array $statuses = ['new','in_review','replied','closed']): array {
    if (empty($statuses)) $statuses = ['new','in_review','replied','closed'];
    $place = implode(',', array_fill(0, count($statuses), '?'));
    $types = str_repeat('s', count($statuses));

    $stmt = $conn->prepare("
        SELECT status, COUNT(*) AS tot
        FROM contact_requests
        WHERE status IN ($place)
        GROUP BY status
    ");
    $stmt->bind_param($types, ...$statuses);
    $stmt->execute();

    $out = array_fill_keys($statuses, 0);
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $k = (string)($r['status'] ?? '');
        if ($k !== '') $out[$k] = (int)$r['tot'];
    }
    $stmt->close();

    $resNull = $conn->query("SELECT COUNT(*) AS tot FROM contact_requests WHERE status IS NULL OR status = ''");
    $out['_null'] = (int)($resNull->fetch_assoc()['tot'] ?? 0);

    return $out;
}

/** SLA: prima risposta (updated_at dopo new) */
function getSlaStats(mysqli $conn, int $slaHours = 48): array {
    if (!tableHasColumn($conn, 'contact_requests', 'updated_at')) {
        return ['avg_h'=>0,'p50_h'=>0,'over_sla_pct'=>0];
    }

    $sql = "
        SELECT TIMESTAMPDIFF(HOUR, created_at, updated_at) AS h
        FROM contact_requests
        WHERE status IN ('in_review','replied','closed')
          AND updated_at IS NOT NULL
          AND updated_at > created_at
    ";
    $res = $conn->query($sql);
    $vals = [];
    while ($r = $res->fetch_assoc()) $vals[] = (int)$r['h'];
    if (!$vals) return ['avg_h'=>0,'p50_h'=>0,'over_sla_pct'=>0];

    sort($vals);
    $n   = count($vals);
    $avg = array_sum($vals) / $n;
    $p50 = $vals[(int)floor(($n-1)*0.5)];
    $over = 0;
    foreach ($vals as $v) if ($v > $slaHours) $over++;
    $pct = $n ? ($over * 100 / $n) : 0;

    return ['avg_h'=>$avg, 'p50_h'=>$p50, 'over_sla_pct'=>$pct];
}

/** Tempo medio/mediano di CHIUSURA (created_at -> closed_at) */
function getRequestClosureStats(mysqli $conn): array {
    if (!tableHasColumn($conn, 'contact_requests', 'closed_at')) {
        $sql = "
            SELECT TIMESTAMPDIFF(HOUR, created_at, updated_at) AS h
            FROM contact_requests
            WHERE status='closed' AND updated_at IS NOT NULL AND updated_at > created_at
        ";
    } else {
        $sql = "
            SELECT TIMESTAMPDIFF(HOUR, created_at, closed_at) AS h
            FROM contact_requests
            WHERE status='closed' AND closed_at IS NOT NULL AND closed_at > created_at
        ";
    }

    $res = $conn->query($sql);
    $vals=[];
    while($r=$res->fetch_assoc()) $vals[]=(int)$r['h'];
    if (!$vals) return ['avg_h'=>0,'p50_h'=>0];

    sort($vals);
    $n=count($vals);
    $avg=array_sum($vals)/$n;
    $p50=$vals[(int)floor(($n-1)*0.5)];
    return ['avg_h'=>$avg,'p50_h'=>$p50];
}

function getRequestAgingBuckets(mysqli $conn): array {
    $sql = "
        SELECT CASE
            WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) <= 24 THEN '0_24'
            WHEN TIMESTAMPDIFF(DAY, created_at, NOW()) BETWEEN 1 AND 3 THEN '1_3'
            WHEN TIMESTAMPDIFF(DAY, created_at, NOW()) BETWEEN 4 AND 7 THEN '4_7'
            ELSE 'gt_7'
        END AS b, COUNT(*) AS tot
        FROM contact_requests
        WHERE status IS NULL OR status = '' OR status IN ('new','in_review')
        GROUP BY b
    ";
    $res = $conn->query($sql);
    $out = ['0_24'=>0,'1_3'=>0,'4_7'=>0,'gt_7'=>0];
    while ($r = $res->fetch_assoc()) $out[$r['b']] = (int)$r['tot'];
    return $out;
}

function getWorkloadByAssignee(mysqli $conn): array {
    // 1) Indicizzazione utenti per confronti veloci
    $users = [];
    $usersById    = [];
    $usersByEmail = [];
    $usersByName  = [];

    if (tableExists($conn, 'utenti')) {
        $resU = $conn->query("SELECT id, TRIM(nome) AS nome, TRIM(email) AS email FROM utenti");
        while ($u = $resU->fetch_assoc()) {
            $id = (int)$u['id'];
            $users[$id] = ['id'=>$id, 'nome'=>trim((string)$u['nome']), 'email'=>trim((string)$u['email'])];
            if ($users[$id]['email'] !== '') $usersByEmail[strtolower($users[$id]['email'])] = $id;
            if ($users[$id]['nome']  !== '') $usersByName[strtolower($users[$id]['nome'])]  = $id;
            $usersById[(string)$id] = $id;
        }
    }

    // 2) Prendo i conteggi grezzi per valore salvato in assigned_to
    $sql = "
        SELECT TRIM(COALESCE(assigned_to,'')) AS k, COUNT(*) AS open, MIN(created_at) AS oldest
        FROM contact_requests
        WHERE status IS NULL OR status = '' OR status IN ('new','in_review')
        GROUP BY TRIM(COALESCE(assigned_to,''))
    ";
    $res = $conn->query($sql);

    // 3) Accorpo per “utente canonico”
    $agg = []; // key => ['user_id'=>?, 'raw'=>?, 'open'=>, 'oldest'=>]
    while ($r = $res->fetch_assoc()) {
        $k      = (string)$r['k'];               // valore così com'è nel DB
        $open   = (int)$r['open'];
        $oldest = (string)$r['oldest'];

        // prova a risalire all'ID utente
        $uid = null;
        if ($k !== '') {
            if (isset($usersById[$k])) {
                $uid = $usersById[$k];
            } else {
                $lk = strtolower($k);
                if (isset($usersByEmail[$lk])) {
                    $uid = $usersByEmail[$lk];
                } elseif (isset($usersByName[$lk])) {
                    $uid = $usersByName[$lk];
                }
            }
        }

        // chiave di aggregazione
        $key = $uid !== null ? ('u:'.$uid) : ($k === '' ? 'none' : 'raw:'.$k);

        if (!isset($agg[$key])) {
            $agg[$key] = [
                'user_id' => $uid,         // null se non mappato
                'raw'     => $k,
                'open'    => 0,
                'oldest'  => $oldest ?: null,
            ];
        }
        $agg[$key]['open'] += $open;

        if ($oldest && (!$agg[$key]['oldest'] || strtotime($oldest) < strtotime((string)$agg[$key]['oldest']))) {
            $agg[$key]['oldest'] = $oldest;
        }
    }

    // 4) Trasformo in output per la tabella
    $out = [];
    foreach ($agg as $row) {
        $label = '—';
        if ($row['user_id'] !== null && isset($users[$row['user_id']])) {
            $label = $users[$row['user_id']]['nome'] ?: ($users[$row['user_id']]['email'] ?: ('#'.$row['user_id']));
        } elseif ($row['raw'] !== '') {
            $label = $row['raw']; // valore libero non mappato a un utente
        }

        $days = 0;
        if (!empty($row['oldest'])) {
            $days = (int)floor((time() - strtotime((string)$row['oldest'])) / 86400);
        }

        $out[] = ['user' => $label, 'open' => (int)$row['open'], 'oldest_days' => $days];
    }

    // ordino per carico desc, poi nome
    usort($out, function($a, $b) {
        if ($a['open'] === $b['open']) return strcasecmp($a['user'], $b['user']);
        return $b['open'] <=> $a['open'];
    });

    return $out;
}




function getRequestOrigins(mysqli $conn): array {
    $res = $conn->query("
        SELECT COALESCE(NULLIF(TRIM(tipo),''),'—') AS k, COUNT(*) AS c
        FROM contact_requests
        GROUP BY k
        ORDER BY c DESC
    ");
    $out = [];
    while ($r = $res->fetch_assoc()) $out[$r['k']] = (int)$r['c'];
    return $out;
}

function getRequestSectors(mysqli $conn): array {
    $res = $conn->query("
        SELECT COALESCE(NULLIF(TRIM(settore),''),'—') AS k, COUNT(*) AS c
        FROM contact_requests
        GROUP BY k
        ORDER BY c DESC
    ");
    $out = [];
    while ($r = $res->fetch_assoc()) $out[$r['k']] = (int)$r['c'];
    return $out;
}


/**
 * =========================================================
 * Preventivi (KPI / funnel / valore / conversione)
 * =========================================================
 */

function getPreventiviKpi(mysqli $conn): array {
    $mese = date('Y-m');
    $like = $conn->real_escape_string($mese) . '%';

    // creati nel mese
    $stmt = $conn->prepare("SELECT COUNT(*) AS tot FROM preventivi WHERE data LIKE ?");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $creati = (int)($stmt->get_result()->fetch_assoc()['tot'] ?? 0);
    $stmt->close();

    $hasStato   = tableHasColumn($conn, 'preventivi', 'stato');
    $hasImporto = tableHasColumn($conn, 'preventivi', 'importo');

    $accettati = 0;
    $somma     = 0.0;
    $funnel    = [];

    if ($hasStato) {
        $acc = $conn->prepare("SELECT COUNT(*) AS tot FROM preventivi WHERE data LIKE ? AND stato = 'accettato'");
        $acc->bind_param('s', $like);
        $acc->execute();
        $accettati = (int)($acc->get_result()->fetch_assoc()['tot'] ?? 0);
        $acc->close();

        $res = $conn->query("SELECT stato, COUNT(*) AS c FROM preventivi GROUP BY stato");
        while ($r = $res->fetch_assoc()) $funnel[$r['stato']] = (int)$r['c'];
    }

    if ($hasImporto) {
        $sum = $conn->prepare("SELECT SUM(importo) AS s FROM preventivi WHERE data LIKE ?");
        $sum->bind_param('s', $like);
        $sum->execute();
        $somma = (float)($sum->get_result()->fetch_assoc()['s'] ?? 0);
        $sum->close();
    }

    return [
        'creati_mese'     => $creati,
        'accettati_mese'  => $accettati,
        'somma_importi'   => $somma,
        'funnel'          => $funnel
    ];
}

/** Conversion rate preventivi (inviato -> accettato) */
function getPreventiviConversionStats(mysqli $conn): array {
    if (!tableHasColumn($conn,'preventivi','stato')) return ['sent'=>0,'accepted'=>0,'rate'=>0.0];
    $sent = (int)($conn->query("SELECT COUNT(*) AS c FROM preventivi WHERE stato='inviato'")->fetch_assoc()['c'] ?? 0);
    $acc  = (int)($conn->query("SELECT COUNT(*) AS c FROM preventivi WHERE stato='accettato'")->fetch_assoc()['c'] ?? 0);
    $rate = $sent ? ($acc * 100.0 / $sent) : 0.0;
    return ['sent'=>$sent,'accepted'=>$acc,'rate'=>$rate];
}

/**
 * =========================================================
 * Libri / Portfolio — CRUD util & analytics
 * =========================================================
 */

/**
 * @return mysqli_result
 */
function estraiLibri(mysqli $conn, int $limit = 0, int $offset = 0) {
    $sql = "
        SELECT
            l.id, l.titolo, l.sinossi, l.immagine, l.data_pubblicazione, l.link,
            GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ', ') AS categorie
        FROM libri l
        LEFT JOIN libri_categorie lc ON l.id = lc.libro_id
        LEFT JOIN categorie_libri c   ON lc.categoria_id = c.id
        GROUP BY l.id
        ORDER BY l.id DESC
    ";
    if ($limit > 0) $sql .= " LIMIT $offset, $limit";

    $res = $conn->query($sql);
    if ($res === false) die("Errore in estraiLibri(): " . $conn->error);
    return $res;
}

function recuperaCategorieLibri(mysqli $conn, array $exclude = ['in evidenza']): array {
    $place = implode(',', array_fill(0, count($exclude), '?'));
    $sql = "SELECT id, nome FROM categorie_libri WHERE LOWER(nome) NOT IN ($place) ORDER BY nome ASC";
    $stmt = $conn->prepare($sql);
    $lower = array_map('strtolower', $exclude);
    $stmt->bind_param(str_repeat('s', count($lower)), ...$lower);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function risolviCategorie(mysqli $conn, string $nuovaCategoria, array $scelte = []): array {
    $nuove = array_filter(array_map('trim', explode(',', $nuovaCategoria)));
    foreach ($nuove as $catNome) {
        $lower = strtolower($catNome);
        $check = $conn->prepare("SELECT id FROM categorie_libri WHERE LOWER(nome)=? LIMIT 1");
        $check->bind_param('s', $lower);
        $check->execute();
        $check->store_result();

        if ($check->num_rows) {
            $check->bind_result($idEs);
            $check->fetch();
            if (!in_array($idEs, $scelte, true)) $scelte[] = $idEs;
        } else {
            $ins = $conn->prepare("INSERT INTO categorie_libri (nome) VALUES (?)");
            $ins->bind_param('s', $catNome);
            $ins->execute();
            $scelte[] = $ins->insert_id;
            $ins->close();
        }
        $check->close();
    }
    return $scelte;
}

function garantisciEvidenza(mysqli $conn, array &$scelte): void {
    $stmt = $conn->prepare("SELECT id FROM categorie_libri WHERE LOWER(nome)='in evidenza' LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($idE);
    if (!$stmt->fetch()) {
        $stmt->close();
        $conn->query("INSERT INTO categorie_libri (nome) VALUES ('In evidenza')");
        $idE = $conn->insert_id;
    } else {
        $stmt->close();
    }
    if (!in_array((int)$idE, $scelte, true)) $scelte[] = (int)$idE;
}

function saveLibro(mysqli $conn, array $dati, array $scelte): bool {
    // JSON varianti copertina generato da uploadCover()
    $coverJsonArr = isset($GLOBALS['__COVER_LAST_JSON__']) ? $GLOBALS['__COVER_LAST_JSON__'] : null;
    $coverJsonStr = $coverJsonArr ? json_encode($coverJsonArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    // 7 placeholder + NOW(): titolo, sinossi, immagine, data_pubblicazione, casa_editrice, link, cover_json
    $sql = "
        INSERT INTO libri (titolo, sinossi, immagine, data_pubblicazione, casa_editrice, link, cover_json, aggiunto_il)
        VALUES (?,?,?,?,?,?,?, NOW())
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('saveLibro prepare() failed: '.$conn->errno.' '.$conn->error);
        return false;
    }

    // 7 tipi (tutti stringhe)
    $types = 'sssssss';
    $stmt->bind_param(
        $types,
        $dati['titolo'],
        $dati['sinossi'],
        $dati['immagine'],
        $dati['data_pubblicazione'],
        $dati['casa_editrice'],
        $dati['link'],
        $coverJsonStr
    );

    $ok = $stmt->execute();
    if (!$ok) {
        error_log("saveLibro: errore insert libri - " . $stmt->error);
        $stmt->close();
        return false;
    }
    $idLibro = (int)$stmt->insert_id;
    $stmt->close();

    // pivot categorie
    if (!empty($scelte)) {
        $pivot = $conn->prepare("INSERT INTO libri_categorie (libro_id, categoria_id) VALUES (?, ?)");
        if ($pivot) {
            foreach ($scelte as $catID) {
                $cid = (int)$catID;
                $pivot->bind_param('ii', $idLibro, $cid);
                $pivot->execute();
            }
            $pivot->close();
        }
    }
    return true;
}



function getTopCategorieLibri(mysqli $conn, int $limit = 5): array {
    $stmt = $conn->prepare("
        SELECT c.nome, COUNT(*) AS tot
        FROM libri_categorie lc
        JOIN categorie_libri c ON lc.categoria_id = c.id
        GROUP BY c.id
        ORDER BY COUNT(*) DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();
    return $rows;
}

function getUltimiLibri(mysqli $conn, int $limit = 3): array {
    $stmt = $conn->prepare("
        SELECT id, titolo, immagine, data_pubblicazione
        FROM libri
        ORDER BY id DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();
    return $rows;
}

/** Inserimenti libri per mese negli ultimi $months mesi => mappa 'YYYY-MM' => count */
function getLibriInserimentiUltimi12M(mysqli $conn, int $months = 12): array {
    $labels = [];
    $now = new DateTimeImmutable('first day of this month');
    for ($i = $months - 1; $i >= 0; $i--) {
        $labels[] = $now->sub(new DateInterval('P' . $i . 'M'))->format('Y-m');
    }

    $start = $now->sub(new DateInterval('P' . ($months - 1) . 'M'))->format('Y-m-01 00:00:00');
    $end   = $now->add(new DateInterval('P1M'))->format('Y-m-01 00:00:00'); // esclusivo

    $sql = "
        SELECT DATE_FORMAT(data_pubblicazione, '%Y-%m') AS ym, COUNT(*) AS c
        FROM libri
        WHERE data_pubblicazione >= ? AND data_pubblicazione < ?
        GROUP BY ym
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();

    $map = array_fill_keys($labels, 0);
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $ym = $r['ym'];
        if (isset($map[$ym])) $map[$ym] = (int)$r['c'];
    }
    $stmt->close();

    return $map;
}

/** Top case editrici (richiede colonna 'casa_editrice' in libri) */
function getCaseEditriciTop(mysqli $conn, int $limit = 8): array {
    if (!tableHasColumn($conn, 'libri', 'casa_editrice')) return [];
    $stmt = $conn->prepare("
        SELECT TRIM(casa_editrice) AS casa, COUNT(*) AS tot
        FROM libri
        WHERE TRIM(COALESCE(casa_editrice,'')) <> ''
        GROUP BY TRIM(casa_editrice)
        ORDER BY COUNT(*) DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();
    return $rows;
}

/** Quota libri "in evidenza" */
function getLibriEvidenzaPct(mysqli $conn): array {
    $tot = (int)($conn->query("SELECT COUNT(*) AS t FROM libri")->fetch_assoc()['t'] ?? 0);
    if ($tot === 0) return ['total'=>0,'evidenza'=>0,'pct'=>0.0];

    $qCat = $conn->query("SELECT id FROM categorie_libri WHERE LOWER(nome)='in evidenza' LIMIT 1");
    $row  = $qCat ? $qCat->fetch_assoc() : null;
    if (!$row) return ['total'=>$tot,'evidenza'=>0,'pct'=>0.0];

    $idE = (int)$row['id'];
    $q   = $conn->query("SELECT COUNT(*) AS c FROM libri_categorie WHERE categoria_id = $idE");
    $evid = (int)($q->fetch_assoc()['c'] ?? 0);
    $pct  = $tot ? ($evid * 100.0 / $tot) : 0.0;

    return ['total'=>$tot, 'evidenza'=>$evid, 'pct'=>$pct];
}

/**
 * Storico (ultimi $months) per le TOP $topN categorie
 * Ritorna ['labels'=>['YYYY-MM',...], 'series'=>[['label'=>'..','data'=>[...]], ...]]
 */
function getLibriStoricoPerTopCategorie(mysqli $conn, int $months = 12, int $topN = 3): array {
    // labels
    $labels = [];
    $now = new DateTimeImmutable('first day of this month');
    for ($i = $months - 1; $i >= 0; $i--) {
        $labels[] = $now->sub(new DateInterval('P' . $i . 'M'))->format('Y-m');
    }

    $start = $now->sub(new DateInterval('P' . ($months - 1) . 'M'))->format('Y-m-01 00:00:00');
    $end   = $now->add(new DateInterval('P1M'))->format('Y-m-01 00:00:00'); // esclusivo

    // top categorie nel periodo
    $sqlTop = "
        SELECT c.id, c.nome, COUNT(*) AS tot
        FROM libri l
        JOIN libri_categorie lc ON lc.libro_id = l.id
        JOIN categorie_libri c  ON c.id = lc.categoria_id
        WHERE l.data_pubblicazione >= ? AND l.data_pubblicazione < ?
        GROUP BY c.id
        ORDER BY tot DESC
        LIMIT ?
    ";
    $stmtTop = $conn->prepare($sqlTop);
    $stmtTop->bind_param('ssi', $start, $end, $topN);
    $stmtTop->execute();
    $top = $stmtTop->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtTop->close();

    if (!$top) return ['labels'=>$labels, 'series'=>[]];

    // serie per categoria
    $series = [];
    foreach ($top as $row) {
        $cid   = (int)$row['id'];
        $label = (string)$row['nome'];
        $sql = "
            SELECT DATE_FORMAT(l.data_pubblicazione, '%Y-%m') AS ym, COUNT(*) AS c
            FROM libri l
            JOIN libri_categorie lc ON lc.libro_id = l.id
            WHERE lc.categoria_id = ? AND l.data_pubblicazione >= ? AND l.data_pubblicazione < ?
            GROUP BY ym
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iss', $cid, $start, $end);
        $stmt->execute();

        $map = array_fill_keys($labels, 0);
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $ym = $r['ym'];
            if (isset($map[$ym])) $map[$ym] = (int)$r['c'];
        }
        $stmt->close();

        $series[] = ['label' => $label, 'data' => array_values($map)];
    }

    return ['labels'=>$labels, 'series'=>$series];
}

/**
 * =========================================================
 * Articoli / Upload
 * =========================================================
 */

/**
 * @return mysqli_result|false (annotazione, niente union type in firma)
 */
function estraiArticoli(mysqli $conn, int $limit = 0, int $offset = 0) {
    $sql = "
        SELECT a.id, a.titolo, a.descrizione, a.data_pubblicazione, a.copertina, c.nome AS categoria
        FROM articoli a
        LEFT JOIN categorie_articoli c ON a.categoria = c.id
        ORDER BY a.data_pubblicazione DESC
    ";
    if ($limit > 0)      $sql .= " LIMIT $offset, $limit";
    elseif ($offset > 0) $sql .= " LIMIT $offset, 999999";
    return $conn->query($sql);
}

function resolveCategoria(mysqli $conn, string $selectedId, string $newName): int {
    if ($selectedId === '__new__' && $newName !== '') {
        $stmt = $conn->prepare("INSERT INTO categorie_articoli (nome) VALUES (?)");
        $stmt->bind_param("s", $newName);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }
    return (int)$selectedId;
}


function uploadCover(array $file): string {
    // CONFIG
    $BASE_DIR   = __DIR__ . '/../../uploads/covers';
    $PUBLIC_BASE= '/backend/uploads/covers';
    $MAX_BYTES  = 10 * 1024 * 1024;
    $MAX_W      = 4000; $MAX_H = 4000;
    $SIZES      = [320, 480, 640, 800, 1000];

    // accetta anche key diverse dal form
    if (empty($file) || (empty($file['tmp_name']) && isset($_FILES['copertina']))) $file = $_FILES['copertina'];
    if (empty($file) || (empty($file['tmp_name']) && isset($_FILES['cover'])))     $file = $_FILES['cover'];
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return '';

    if (($file['size'] ?? 0) > $MAX_BYTES) { error_log("uploadCover: file troppo grande"); return ''; }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string)($finfo->file($file['tmp_name']) ?: '');
$allowed = [MIME_JPEG ,MIME_PNG, MIME_WEBP, MIME_AVIF];
    if (!in_array($mime, $allowed, true)) { error_log("uploadCover: mime non supportato: $mime"); return ''; }

    $subdir  = date('Y/m');
    $saveDir = rtrim($BASE_DIR,'/').'/'.$subdir;
    if (!is_dir($saveDir) && !mkdir($saveDir, 0775, true)) { error_log("uploadCover: mkdir fallita $saveDir"); return ''; }

    $preferAvif = (class_exists('Imagick') && in_array('AVIF', \Imagick::queryFormats('AVIF') ?: [], true));
    $fmtExt     = $preferAvif ? 'avif' : 'webp';
$fmtMime = $preferAvif ? MIME_AVIF : MIME_WEBP;

    $base = bin2hex(random_bytes(8));
    $variants = [];
    $fallbackUrl = '';

    if (class_exists('Imagick')) {
        try {
            $img = new Imagick($file['tmp_name']);
            if ($img->getNumberImages() > 1) { $img = $img->coalesceImages(); $img->setIteratorIndex(0); }
            $geom = $img->getImageGeometry(); $w = (int)($geom['width'] ?? 0); $h = (int)($geom['height'] ?? 0);
            if (!$w || !$h) throw new RuntimeException('bad geometry');

            if ($w > $MAX_W || $h > $MAX_H) $img->resizeImage($MAX_W, $MAX_H, Imagick::FILTER_LANCZOS, 1, true);
            $img->stripImage();

            foreach ($SIZES as $tw) {
                $clone = clone $img;
                $clone->resizeImage($tw, 0, Imagick::FILTER_LANCZOS, 1, true);
                if ($fmtExt === 'avif') {
                    $clone->setImageFormat('avif');
                    $clone->setOption('heic:speed','6');
                    $clone->setOption('avif:subsample-mode','off');
                    $clone->setImageCompressionQuality(45);
                } else {
                    $clone->setImageFormat('webp');
                    $clone->setOption('webp:method','6');
                    $clone->setOption('webp:near-lossless','1');
                    $clone->setImageCompressionQuality(78);
                }
                $rel = $subdir.'/'.$base.'-'.$tw.'.'.$fmtExt;
                $clone->writeImage($saveDir.'/'.$base.'-'.$tw.'.'.$fmtExt);
                $variants[] = ['w'=>$tw,'h'=>$clone->getImageHeight(),'url'=>$PUBLIC_BASE.'/'.$rel,'type'=>$fmtMime];
                $clone->clear(); $clone->destroy();
            }

            // full
            $full = clone $img;
            $fullW = min($w, 1400);
            if ($w > $fullW) $full->resizeImage($fullW, 0, Imagick::FILTER_LANCZOS, 1, true);
            if ($fmtExt === 'avif') { $full->setImageFormat('avif'); $full->setImageCompressionQuality(45); }
            else { $full->setImageFormat('webp'); $full->setOption('webp:near-lossless','1'); $full->setImageCompressionQuality(78); }
            $fullRel = $subdir.'/'.$base.'-full.'.$fmtExt;
            $full->writeImage($saveDir.'/'.$base.'-full.'.$fmtExt);
            $full->clear(); $full->destroy();
            $img->clear();  $img->destroy();

            $fb = null; foreach ($variants as $v) { if ($v['w'] >= 480) { $fb = $v; break; } }
            if (!$fb) $fb = $variants[0] ?? null;
            if ($fb) $fallbackUrl = $fb['url'];
$GLOBALS['__COVER_LAST_JSON__'] = [
    'ok' => true,
    'format' => $fmtExt,
    'full' => ['url' => $PUBLIC_BASE . '/' . $fullRel, 'type' => $fmtMime],
    'variants' => $variants
];



            return ltrim($fallbackUrl, '/');
        } catch (Throwable $e) {
            error_log('uploadCover imagick fail: '.$e->getMessage());
        }
    }

    // Fallback: copia il file così com’è (estensione decisa da MIME reale)
   switch ($mime) {
    case MIME_JPEG: $ext = 'jpg';  break;
    case MIME_PNG:  $ext = 'png';  break;
    case MIME_AVIF:    $ext = 'avif'; break;
    case MIME_WEBP:    $ext = 'webp'; break;
    default:
        error_log("uploadCover fallback: mime non valido $mime");
        return '';
}

    $raw  = $base . '-raw.' . $ext;
    $dest = $saveDir . '/' . $raw;

    // Hardening: la destinazione deve essere davvero dentro $saveDir
    if (!is_dir($saveDir)) {
    error_log("uploadCover fallback: saveDir missing");
    return '';
}

    $realSaveDir = realpath($saveDir);
    $realParent  = realpath(dirname($dest));
    if ($realSaveDir === false || $realParent === false || $realSaveDir !== $realParent) {
        error_log("uploadCover fallback: invalid destination");
        return '';
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        error_log("uploadCover fallback move failed");
        return '';
    }

    $url = $PUBLIC_BASE . '/' . $subdir . '/' . $raw;
$extToMime = ['jpg'=>MIME_JPEG,'png'=>MIME_PNG,'webp'=>MIME_WEBP,'avif'=>MIME_AVIF];

$GLOBALS['__COVER_LAST_JSON__'] = [
    'ok' => true,
    'format' => $ext,
'full' => ['url' => $url, 'type' => ($extToMime[$ext] ?? MIME_WEBP)],
    'variants' => []
];
return ltrim($url, '/');
}


function saveArticle(mysqli $conn, array $dati): bool {
    $stmt = $conn->prepare("
        INSERT INTO articoli (titolo, descrizione, contenuto, categoria, copertina, data_pubblicazione)
        VALUES (?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "sssiss",
        $dati['title'],
        $dati['excerpt'],
        $dati['content'],
        $dati['categoria_id'],
        $dati['copertina'],
        $dati['data_pub']
    );
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function getCategorieArticoli(mysqli $conn): array {
    $out = [];
    $res = $conn->query("SELECT id, nome FROM categorie_articoli ORDER BY nome");
    while ($r = $res->fetch_assoc()) $out[] = $r;
    return $out;
}

/**
 * =========================================================
 * Clienti
 * =========================================================
 */

/**
 * @return mysqli_result|false
 */
function estraiclienti(mysqli $conn, int $limit = 0, int $offset = 0) {
    $sql = "SELECT * FROM clienti ORDER BY nome DESC";
    if ($limit > 0) $sql .= " LIMIT $offset, $limit";
    return $conn->query($sql);
}

/**
 * =========================================================
 * Log & Statistiche base
 * =========================================================
 */


function getStatisticheMensili(mysqli $conn): array {
    $articoli   = array_fill(0, 12, 0);
    $preventivi = array_fill(0, 12, 0);

    $res1 = $conn->query("
        SELECT MONTH(data_pubblicazione) AS mese, COUNT(*) AS tot
        FROM articoli
        WHERE YEAR(data_pubblicazione) = YEAR(CURDATE())
        GROUP BY mese
    ");
    while ($r = $res1->fetch_assoc()) {
        $articoli[(int)$r['mese'] - 1] = (int)$r['tot'];
    }

    $res2 = $conn->query("
        SELECT MONTH(data) AS mese, COUNT(*) AS tot
        FROM preventivi
        WHERE YEAR(data) = YEAR(CURDATE())
        GROUP BY mese
    ");
    while ($r = $res2->fetch_assoc()) {
        $preventivi[(int)$r['mese'] - 1] = (int)$r['tot'];
    }

    return [
        'mesi'       => ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'],
        'articoli'   => $articoli,
        'preventivi' => $preventivi
    ];
}

function getVisiteMensiliPerPagina(mysqli $conn): array {
    $res = $conn->query("
        SELECT pagina, MONTH(data_visita) AS mese, COUNT(*) AS visite
        FROM visite_pagine
        WHERE YEAR(data_visita) = YEAR(CURDATE())
        GROUP BY pagina, mese
        ORDER BY mese, pagina
    ");

    $stat   = [];
    $pagine = [];
    while ($row = $res->fetch_assoc()) {
        $mese   = (int)$row['mese'] - 1;
        $pagina = $row['pagina'];
        $visite = (int)$row['visite'];
        if (!in_array($pagina, $pagine, true)) $pagine[] = $pagina;
        $stat[$pagina][$mese] = $visite;
    }

    $mesi    = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];
    $colori  = ['#ff6384','#36a2eb','#cc65fe','#ffce56','#4bc0c0','#9966ff'];
    $datasets = [];
    foreach ($pagine as $i => $pagina) {
        $data = array_fill(0, 12, 0);
        if (isset($stat[$pagina])) {
            foreach ($stat[$pagina] as $m => $v) $data[$m] = $v;
        }
        $datasets[] = ['label'=>$pagina,'data'=>$data,'backgroundColor'=>$colori[$i % count($colori)]];
    }

    return ['mesi'=>$mesi,'datasets'=>$datasets];
}

/**
 * =========================================================
 * Dashboard aggregate (unica)
 * =========================================================
 */
function getDashboardStats(mysqli $conn): array {
    $mese = date('Y-m');
    $oggi = date('Y-m-d');
    $fine = date('Y-m-d', strtotime('+6 days'));

    // Articoli
    $totArticoli = (int)($conn->query("SELECT COUNT(*) AS tot FROM articoli")->fetch_assoc()['tot'] ?? 0);
    $meseLike = $conn->real_escape_string($mese) . '%';

    $stmtArtMese = $conn->prepare("SELECT COUNT(*) AS tot FROM articoli WHERE data_pubblicazione LIKE ?");
    $stmtArtMese->bind_param('s', $meseLike);
    $stmtArtMese->execute();
    $articoliMese = (int)($stmtArtMese->get_result()->fetch_assoc()['tot'] ?? 0);
    $stmtArtMese->close();

    // Preventivi (compat)
    $stmtPrev = $conn->prepare("SELECT COUNT(*) AS tot FROM preventivi WHERE data LIKE ?");
    $stmtPrev->bind_param('s', $meseLike);
    $stmtPrev->execute();
    $preventiviMese = (int)($stmtPrev->get_result()->fetch_assoc()['tot'] ?? 0);
    $stmtPrev->close();

    // Scadenze (7 giorni)
    $stmtScad = $conn->prepare("SELECT COUNT(*) AS tot FROM flusso_lavoro WHERE data_evento BETWEEN ? AND ?");
    $stmtScad->bind_param('ss', $oggi, $fine);
    $stmtScad->execute();
    $scadenze = (int)($stmtScad->get_result()->fetch_assoc()['tot'] ?? 0);
    $stmtScad->close();

    // Richieste
    $req        = getContactRequestStats($conn, ['new','in_review']);
$assegnate = countRequestsAssignedToMe($conn); // tutte le assegnate, qualsiasi stato
    $ultime     = getUltimeRichieste($conn, 8);
    $ids        = array_map(function($r){ return (int)$r['id']; }, $ultime);
    $viewersByRequest = getRecentViewersForRequests($conn, $ids);
    $reqByDay         = getContactRequestsByDayThisMonth($conn);
    $quickCounts      = getContactCountsByStatus($conn, ['new','in_review','replied','closed']);
    $sla              = getSlaStats($conn, 48);
    $closure          = getRequestClosureStats($conn);
    $aging            = getRequestAgingBuckets($conn);
    $workload         = getWorkloadByAssignee($conn);
    $origini          = getRequestOrigins($conn);
    $settori          = getRequestSectors($conn);

    // Preventivi KPI + conversione
    $prevKpi  = getPreventiviKpi($conn);
    $prevConv = getPreventiviConversionStats($conn);

    // Libri: conteggi + analytics
    $totLibri  = (int)($conn->query("SELECT COUNT(*) AS tot FROM libri")->fetch_assoc()['tot'] ?? 0);
    $topCat    = getTopCategorieLibri($conn, 5);
    $ultimi    = getUltimiLibri($conn, 3);
    $libri12m  = getLibriInserimentiUltimi12M($conn, 12);
    $storico   = getLibriStoricoPerTopCategorie($conn, 12, 3);
    $editori   = getCaseEditriciTop($conn, 8);
    $evpct     = getLibriEvidenzaPct($conn);

    return [
        'totArticoli'            => $totArticoli,
        'articoliMese'           => $articoliMese,
        'preventiviMese'         => $preventiviMese,
        'scadenze'               => $scadenze,
        'eventi'                 => getEventiFlussoLavoro($conn, $oggi, $fine),
        'prossimeScadenze'       => getProssimeScadenze($conn, 5),

        'richiesteMese'          => (int)$req['richiesteMese'],
        'richiesteDaEvadere'     => (int)$req['richiesteDaEvadere'],
        'richiesteAssegnateAMe'  => (int)$assegnate,
        'ultimeRichieste'        => $ultime,
        'viewersByRequest'       => $viewersByRequest,
        'richiestePerGiorno'     => $reqByDay,
        'richiesteStatusCount'   => $quickCounts,
        'sla'                    => $sla,
        'closure'                => $closure,
        'agingBuckets'           => $aging,
        'workload'               => $workload,
        'preventiviKpi'          => $prevKpi,
        'preventiviConv'         => $prevConv,

        'totLibri'               => $totLibri,
        'topCategorieLibri'      => $topCat,
        'ultimiLibri'            => $ultimi,
        'libriInserimenti12M'    => $libri12m,
        'libriStoricoTopCat'     => $storico,
        'caseEditriciTop'        => $editori,
        'libriEvidenzaPct'       => $evpct,

        'origini'                => $origini,
        'settori'                => $settori,
    ];
}

/*
Schema promemoria:

libri
- id
- titolo
- sinossi
- immagine
- data_pubblicazione
- casa_editrice
- link
- categoria_id

categorie_libri
- id
- nome

libri_categorie
- categoria_id
- libro_id
*/


/**
 * Mappa status EN -> IT (per chip)
 */
function labelStatusIT($en) {
  $en = strtolower(trim((string)$en));
  $map = [
    'new'        => 'nuova',
    'in_review'  => 'in revisione',
    'replied'    => 'risposta inviata',
    'answered'   => 'risposta',
    'closed'     => 'chiusa'
  ];
  return $map[$en] ?? ($en ?: '—');
}

/**
 * Classe CSS per chip status
 */
function classStatusChip($en) {
  $en = strtolower((string)$en);
  if ($en === 'closed') return 's-ok';
  if ($en === 'in_review' || $en === 'new') return 's-warn';
  return '';
}

/**
 * Costruisce il "view model" da passare ai template/partials.
 * Centralizza tutta la logica/derivazioni in un punto unico.
 */
function buildDashboardViewModel($conn): array {
  // opzionale ma utile: verifica che sia una connessione valida
if (!($conn instanceof mysqli)) {
    throw new InvalidArgumentException('Connessione non valida: atteso mysqli.');
}


  $stats = getDashboardStats($conn);

  $nome  = h($_SESSION['utente']['nome'] ?? 'Utente');
  $ruolo = h($_SESSION['utente']['ruolo'] ?? 'user');

  // JSON/front-end
  $eventiJson   = h(j($stats['eventi'] ?? []));
  $reqByDayArr  = $stats['richiestePerGiorno'] ?? [];
  $reqByDayJson = h(j($reqByDayArr));
  $funnelJson   = h(j($stats['preventiviKpi']['funnel'] ?? []));
  $originArr    = $stats['origini'] ?? [];
  $originJson   = h(j($originArr));
  $sectorJson   = h(j($stats['settori'] ?? []));
  $libri12MArr  = $stats['libriInserimenti12M'] ?? [];
  $insLibriJson = h(j($libri12MArr));

  $storicoTopCat = $stats['libriStoricoTopCat'] ?? ['labels'=>[],'series'=>[]];
  $storicoLabels = h(j($storicoTopCat['labels'] ?? []));
  $storicoSeries = h(j($storicoTopCat['series'] ?? []));

  $quickCounts = $stats['richiesteStatusCount'] ?? [];
  $ultime      = $stats['ultimeRichieste'] ?? [];
  $viewersMap  = $stats['viewersByRequest'] ?? [];

  // Mini-sommari mobile
  $totRichiesteMese = 0; foreach ($reqByDayArr as $v) $totRichiesteMese += (int)$v;
  $topOrigine = '—'; if ($originArr) { arsort($originArr); $topOrigine = h((string)array_key_first($originArr)); }
  $totLibri12M = 0; foreach ($libri12MArr as $v) $totLibri12M += (int)$v;
  $topCasa = '—'; if (!empty($stats['caseEditriciTop'])) { $topCasa = h((string)$stats['caseEditriciTop'][0]['casa']); }
  $ultimiTitoli = [];
  if (!empty($stats['ultimiLibri'])) {
    foreach ($stats['ultimiLibri'] as $b) {
      $ultimiTitoli[] = mb_strimwidth((string)($b['titolo'] ?? ''), 0, 24, '…', 'UTF-8');
    }
  }

  return [
    'nome' => $nome,
    'ruolo' => $ruolo,
    'stats' => $stats,

    // JSON/arr
    'eventiJson' => $eventiJson,
    'reqByDayArr' => $reqByDayArr,
    'reqByDayJson'=> $reqByDayJson,
    'funnelJson'  => $funnelJson,
    'originArr'   => $originArr,
    'originJson'  => $originJson,
    'sectorJson'  => $sectorJson,
    'libri12MArr' => $libri12MArr,
    'insLibriJson'=> $insLibriJson,
    'storicoLabels'=> $storicoLabels,
    'storicoSeries'=> $storicoSeries,
    'quickCounts' => $quickCounts,
    'ultime'      => $ultime,
    'viewersMap'  => $viewersMap,

    // mobile mini
    'totRichiesteMese' => $totRichiesteMese,
    'topOrigine'       => $topOrigine,
    'totLibri12M'      => $totLibri12M,
    'topCasa'          => $topCasa,
    'ultimiTitoli'     => $ultimiTitoli,
  ];
}

/**
 * Restituisce gli alias dell'utente corrente da confrontare con contact_requests.assigned_to
 * (nome visualizzato, email e id come fallback/retrocompatibilità).
 */
function currentUserAssigneeAliases(): array {
    $nome  = trim((string)($_SESSION['utente']['nome']  ?? ''));
    $email = trim((string)($_SESSION['utente']['email'] ?? ''));
    $id    = (string)($_SESSION['utente']['id'] ?? '');

    $aliases = array_filter([$nome, $email, $id], function($v){
        return $v !== '' && $v !== '0' && $v !== null;
    });

    // normalizza e unisci univoci
    $aliases = array_values(array_unique($aliases));
    return $aliases;
}

/**
 * Conta le richieste assegnate all'utente corrente (match su qualsiasi alias).
 */
function countRequestsAssignedToMe(mysqli $conn, array $openStatuses = []): int {
    $aliases = currentUserAssigneeAliases();
    if (!$aliases) return 0;

    // normalizza gli alias come fa la query lato DB
    $aliases = array_map(function($a){
        return mb_strtolower(trim((string)$a));
    }, $aliases);

    $phAliases = implode(',', array_fill(0, count($aliases), '?'));

    $sql = "
        SELECT COUNT(*) AS c
        FROM contact_requests
        WHERE LOWER(TRIM(COALESCE(assigned_to,''))) IN ($phAliases)
    ";

    $types = str_repeat('s', count($aliases));
    $params = $aliases;

    // opzionale: limita a "aperte" (new/in_review) come vuoi tu
    if (!empty($openStatuses)) {
        $phSt = implode(',', array_fill(0, count($openStatuses), '?'));
        $sql .= " AND (status IS NULL OR status = '' OR status IN ($phSt))";
        $types .= str_repeat('s', count($openStatuses));
        $params = array_merge($params, $openStatuses);
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['c'] ?? 0);
}


function assigneeLabel(mysqli $conn, $val): string {
    $k = trim((string)$val);
    if ($k === '') return '—';

    if (tableExists($conn, 'utenti')) {
        if (ctype_digit($k)) {
            $uid = (int)$k;
            $u = $conn->query("SELECT TRIM(COALESCE(nome, '')) AS nome, TRIM(COALESCE(email,'')) AS email FROM utenti WHERE id = $uid LIMIT 1");
            if ($u && ($row = $u->fetch_assoc())) {
                return $row['nome'] !== '' ? $row['nome'] : ($row['email'] !== '' ? $row['email'] : ('#'.$uid));
            }
        } else {
            $lk = strtolower($k);
            // prova match email o nome
            $u = $conn->query("SELECT TRIM(COALESCE(nome,'')) AS nome FROM utenti WHERE LOWER(TRIM(email)) = '".$conn->real_escape_string($lk)."' OR LOWER(TRIM(nome)) = '".$conn->real_escape_string($lk)."' LIMIT 1");
            if ($u && ($row = $u->fetch_assoc()) && $row['nome'] !== '') {
                return $row['nome'];
            }
        }
    }
    return $k; // valore libero
}

/** Elenco case editrici (DISTINCT da libri) */
function recuperaCaseEditrici(mysqli $conn): array {
    $res = $conn->query("
        SELECT DISTINCT TRIM(casa_editrice) AS nome
        FROM libri
        WHERE TRIM(COALESCE(casa_editrice,'')) <> ''
        ORDER BY nome
    ");
    $out = [];
    if ($res) while ($r = $res->fetch_assoc()) $out[] = (string)$r['nome'];
    return $out;
}

/** Risolve la casa editrice preferendo il valore “nuovo” se presente */
function risolviCasaEditrice(?string $nuova, ?string $selezionata): string {
    $nuova = trim((string)$nuova);
    $sel   = trim((string)$selezionata);
    return $nuova !== '' ? $nuova : $sel;
}

// ========== LOG: scrivi ==========
function logEvento(mysqli $conn, string $azione, array $extra = []): void {
  $userId   = $_SESSION['utente']['id']   ?? null;
  $username = $_SESSION['utente']['nome'] ?? ($_SESSION['utente']['username'] ?? null);
  $ip       = $_SERVER['REMOTE_ADDR']     ?? null;
  $ua       = $_SERVER['HTTP_USER_AGENT'] ?? null;

  $username = $extra['username'] ?? $username;   // override opzionale (es: login fail)
  $userId   = $extra['user_id']  ?? $userId;

  $stmt = $conn->prepare("INSERT INTO log_attivita (user_id, username, azione, ip, user_agent) VALUES (?,?,?,?,?)");
  $stmt->bind_param(
    "issss",
    $userId,
    $username,
    $azione,
    $ip,
    $ua
  );
  $stmt->execute();
  $stmt->close();
}

// ========== LOG: leggi (con filtri/paginazione minimi) ==========
function getLogAttivita(mysqli $conn, int $limit = 200, int $offset = 0, array $filters = []): array {
  $limit  = max(1, (int)$limit);
  $offset = max(0, (int)$offset);

  $where  = [];
  $params = [];
  $types  = "";

  if (!empty($filters['q'])) {
    $q = "%".$filters['q']."%";
    $where[] = "(username LIKE ? OR azione LIKE ? OR ip LIKE ?)";
    $params[] = $q; $types .= "s";
    $params[] = $q; $types .= "s";
    $params[] = $q; $types .= "s";
  }
  if (!empty($filters['da'])) {
    $where[] = "created_at >= ?";
    $params[] = $filters['da']." 00:00:00"; $types .= "s";
  }
  if (!empty($filters['a'])) {
    $where[] = "created_at <= ?";
    $params[] = $filters['a']." 23:59:59"; $types .= "s";
  }

  $sql = "SELECT id, user_id, username, azione, ip, user_agent, created_at
          FROM log_attivita";
  if ($where) {
    $sql .= " WHERE ".implode(" AND ", $where);
  }
  // NIENTE segnaposto qui: AlterVista/MySQL può rifiutare LIMIT/OFFSET bindati
  $sql .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    error_log("getLogAttivita prepare() failed: ".$conn->errno." ".$conn->error." | SQL: ".$sql);
    return [];
  }

  if ($types !== "") {
    $stmt->bind_param($types, ...$params);
  }

  if (!$stmt->execute()) {
    error_log("getLogAttivita execute() failed: ".$stmt->errno." ".$stmt->error);
    $stmt->close();
    return [];
  }

  $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
  return $rows;
}

function registraAttivita(mysqli $conn, string $azione): void {
    $utente_id = $_SESSION['utente']['id'] ?? null;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO log_attivita (utente_id, azione, data, ip)
        VALUES (?, ?, NOW(), ?)
    ");
    $stmt->bind_param('iss', $utente_id, $azione, $ip);
    $stmt->execute();
    $stmt->close();
}
