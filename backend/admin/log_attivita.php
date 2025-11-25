<?php
session_start();
require_once '../assets/funzioni/db/db.php';
require_once '../assets/funzioni/funzioni.php';

requireLogin();
if (!isAdmin()) {
  http_response_code(403);
  exit("Accesso negato");
}

if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

// === Filtri base ===
$q   = trim($_GET['q']  ?? '');
$da  = trim($_GET['da'] ?? '');
$a   = trim($_GET['a']  ?? '');
$pg  = max(1, (int)($_GET['p'] ?? 1));
$per = 100;
$off = ($pg - 1) * $per;

// === Costruzione query dinamica ===
$where = [];
$bind  = [];
$types = '';

if ($q !== '') {
  // filtro testo libero su nome, username, azione o ip
  $where[] = "(u.nome LIKE ? OR u.username LIKE ? OR la.azione LIKE ? OR la.ip LIKE ?)";
  for ($i = 0; $i < 4; $i++) {
    $bind[] = "%$q%";
    $types .= 's';
  }
}

if ($da !== '') {
  $where[] = "la.data >= ?";
  $bind[]  = $da . " 00:00:00";
  $types  .= 's';
}
if ($a !== '') {
  $where[] = "la.data <= ?";
  $bind[]  = $a . " 23:59:59";
  $types  .= 's';
}

$sql = "
  SELECT 
    la.id,
    la.utente_id,
    la.azione,
    la.data,
    la.ip,
    u.nome AS nome_utente,
    u.username AS username
  FROM log_attivita la
  LEFT JOIN utenti u ON u.id = la.utente_id
  " . ($where ? "WHERE " . implode(" AND ", $where) : "") . "
  ORDER BY la.data DESC
  LIMIT ? OFFSET ?
";

$types .= 'ii';
$bind[] = $per;
$bind[] = $off;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$bind);
$stmt->execute();
$res = $stmt->get_result();
$log = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Log attività</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style1.css">
  <style>
    .panel{margin-top:14px}
    .filters{display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:10px}
    .filters .f{display:flex;flex-direction:column;gap:4px}
    .filters input{border:1px solid var(--border);border-radius:10px;padding:8px 10px}
    .filters button{border:1px solid var(--border);border-radius:10px;padding:8px 12px;background:#fff;cursor:pointer}
    .table-responsive{border:1px solid var(--border);border-radius:12px;background:#fff}
    .muted{color:var(--muted)}
    td small{color:#888;font-size:12px}
  </style>
</head>
<body>
<main>
  <header class="topbar">
    <div class="user-badge">
      <i class="fas fa-shield-halved icon-user"></i>
      <div>
        <div class="muted">Area Amministrativa</div>
        <div style="font-weight:800;letter-spacing:.2px;">Log attività</div>
      </div>
      <span class="role"><?= e($_SESSION['utente']['ruolo'] ?? 'user') ?></span>
    </div>
    <div class="right">
      <a class="chip" href="../index.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <a class="chip" href="index_admin.php"><i class="fa-solid fa-toolbox"></i> Admin</a>
      <a class="chip" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </header>

  <section class="panel">
    <h2 style="margin:0 0 8px"><i class="fa-regular fa-rectangle-list"></i> Ultime attività</h2>

    <form class="filters" method="get">
      <div class="f">
        <label for="q">Cerca</label>
        <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Nome, username, azione o IP…">
      </div>
      <div class="f">
        <label for="da">Da</label>
        <input type="date" id="da" name="da" value="<?= e($da) ?>">
      </div>
      <div class="f">
        <label for="a">A</label>
        <input type="date" id="a" name="a" value="<?= e($a) ?>">
      </div>
      <div class="f">
        <label>&nbsp;</label>
        <button type="submit" class="chip"><i class="fa-solid fa-magnifying-glass"></i> Filtra</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table compact">
        <thead>
          <tr>
            <th style="white-space:nowrap">Data</th>
            <th>Utente</th>
            <th>Azione</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($log): foreach ($log as $r): ?>
            <tr>
              <td><?= e((new DateTimeImmutable($r['data']))->format('d/m/Y H:i:s')) ?></td>
              <td>
                <?= e($r['nome_utente'] ?: $r['username'] ?: '—') ?>
                <small>(#<?= e($r['utente_id'] ?? '-') ?>)</small>
              </td>
              <td><?= e($r['azione']) ?></td>
              <td><?= e($r['ip'] ?? '-') ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="4" class="muted">Nessuna attività trovata.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (is_countable($log) && count($log) === $per): ?>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px">
        <a class="chip" href="?<?= http_build_query(['q'=>$q,'da'=>$da,'a'=>$a,'p'=>max(1,$pg-1)]) ?>"><i class="fa-solid fa-arrow-left"></i> Prec.</a>
        <a class="chip" href="?<?= http_build_query(['q'=>$q,'da'=>$da,'a'=>$a,'p'=>$pg+1]) ?>">Succ. <i class="fa-solid fa-arrow-right"></i></a>
      </div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
