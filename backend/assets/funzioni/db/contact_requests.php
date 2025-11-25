<?php
// backend/assets/funzioni/db/contact_requests.php
declare(strict_types=1);

/**
 * Salva una richiesta di contatto nel database.
 *
 * Tabella attesa (colonne): 
 * id, tipo, nome, cognome, rgs, settore, email, msg, ip, user_agent,
 * created_at, mail_status, mail_error, status, internal_note, closure_reason, assigned_to, updated_at
 */
function save_contact_request(
  mysqli $conn,
  string $tipo,
  array $post,
  string $status = 'new',          // stato pipeline (es. new)
  string $mail_status = 'sent',    // sent | failed
  ?string $mail_error = null
): bool {
    $nome    = ($tipo === 'privati') ? trim((string)($post['nome']    ?? '')) : '';
    $cognome = ($tipo === 'privati') ? trim((string)($post['cognome'] ?? '')) : '';
    $rgs     = ($tipo === 'aziende') ? trim((string)($post['rgs']     ?? '')) : '';
    $settore = ($tipo === 'aziende') ? trim((string)($post['settore'] ?? '')) : '';
    $email   = trim((string)($post['email'] ?? ''));
    $msg     = trim((string)($post['msg']   ?? ''));

    $ip         = trim((string)($post['_meta']['ip']         ?? ''));
    $user_agent = trim((string)($post['_meta']['user_agent'] ?? ''));

    $sql = "
      INSERT INTO `contact_requests`
        (`tipo`,`nome`,`cognome`,`rgs`,`settore`,`email`,`msg`,
         `ip`,`user_agent`,
         `created_at`,`updated_at`,
         `mail_status`,`mail_error`,
         `status`,`internal_note`,`closure_reason`,`assigned_to`)
      VALUES
        (?,?,?,?,?,?,?,
         ?,?,
         NOW(), NULL,
         ?,?,
         ?, NULL, NULL, NULL)
    ";

    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('save_contact_request: prepare failed: ' . $conn->error);
            return false;
        }
        $stmt->bind_param(
            'ssssssssssss',
            $tipo, $nome, $cognome, $rgs, $settore, $email, $msg,
            $ip, $user_agent,
            $mail_status, $mail_error,
            $status
        );
        $ok = $stmt->execute();
        if (!$ok) {
            error_log('save_contact_request: execute failed: ' . $stmt->error);
        }
        $stmt->close();
        return (bool)$ok;
    } catch (mysqli_sql_exception $e) {
        error_log('save_contact_request mysqli error: ' . $e->getMessage());
        return false;
    }
}

/* --- (opzionali) utility per lista/lettura/aggiornamento admin --- */

function cr_list(mysqli $conn, array $filters = [], int $page = 1, int $perPage = 20): array {
    $where = [];
    $bind  = [];
    $types = '';

    // Ricerca testuale
    if (!empty($filters['q'])) {
        $q = '%'.$filters['q'].'%';
        $where[] = "(email LIKE ? OR nome LIKE ? OR cognome LIKE ? OR rgs LIKE ? OR msg LIKE ?)";
        for ($i=0; $i<5; $i++) { $bind[] = $q; $types .= 's'; }
    }

    // status singolo (retro-compat)
    if (!empty($filters['status'])) {
        $where[] = "status = ?";
        $bind[]  = $filters['status'];
        $types  .= 's';
    }

    // status multiplo (NUOVO): status_in = ['new','in_review', ...] oppure "new,in_review"
    if (!empty($filters['status_in'])) {
        $allowed = ['new','in_review','replied','closed'];
        $arr = is_array($filters['status_in']) ? $filters['status_in'] : explode(',', (string)$filters['status_in']);
        $arr = array_values(array_intersect(array_map('trim', $arr), $allowed));
        if ($arr) {
            $place = implode(',', array_fill(0, count($arr), '?'));
            $where[] = "status IN ($place)";
            foreach ($arr as $st) { $bind[] = $st; $types .= 's'; }
        }
    }

    // assigned_to (match esatto) — NUOVO
    if (array_key_exists('assigned_to', $filters) && $filters['assigned_to'] !== '' && $filters['assigned_to'] !== null) {
        $where[] = "assigned_to = ?";
        $bind[]  = (string)$filters['assigned_to'];
        $types  .= 's';
    }

    $wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

    // totale
    $sqlTotal = "SELECT COUNT(*) AS tot FROM contact_requests $wsql";
    $stmtT = $conn->prepare($sqlTotal);
    if ($types) { $stmtT->bind_param($types, ...$bind); }
    $stmtT->execute();
    $total = (int)($stmtT->get_result()->fetch_assoc()['tot'] ?? 0);
    $stmtT->close();

    // lista paginata
    $offset = max(0, ($page-1)*$perPage);
    $sql = "SELECT id, tipo, nome, cognome, rgs, settore, email, msg, ip, user_agent,
                   created_at, mail_status, mail_error, status, internal_note,
                   closure_reason, assigned_to, updated_at
            FROM contact_requests
            $wsql
            ORDER BY created_at DESC
            LIMIT ?, ?";
    $stmt = $conn->prepare($sql);

    if ($types) {
        $types2 = $types . 'ii';
        $bind2  = $bind;
        $bind2[] = $offset; $bind2[] = $perPage;
        $stmt->bind_param($types2, ...$bind2);
    } else {
        $stmt->bind_param('ii', $offset, $perPage);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $items = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['items'=>$items, 'total'=>$total];
}

function cr_get(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT * FROM contact_requests WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ?: null;
}

function cr_update_admin(
    mysqli $conn,
    int $id,
    string $status,
    ?string $assigned_to = null,
    ?string $internal_note = null
): bool {
    $stmt = $conn->prepare("
        UPDATE contact_requests
        SET status = ?, assigned_to = ?, internal_note = ?, updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('sssi', $status, $assigned_to, $internal_note, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function cr_update_admin_full(
    mysqli $conn,
    int $id,
    string $status,
    ?string $assigned_to = null,
    ?string $internal_note = null,
    ?string $closure_reason = null
): bool {
    $stmt = $conn->prepare("
        UPDATE contact_requests
        SET status = ?, assigned_to = ?, internal_note = ?, closure_reason = ?, updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ssssi', $status, $assigned_to, $internal_note, $closure_reason, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}
