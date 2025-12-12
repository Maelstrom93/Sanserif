<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/db/contact_requests.php';

admin_require_login();

$q           = trim($_GET['q'] ?? '');
$statusParam = trim((string)($_GET['status'] ?? ''));
$assignedTo  = trim((string)($_GET['assigned_to'] ?? ''));
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 20;

$filters = [];
if ($q !== '')               $filters['q'] = $q;
if ($assignedTo !== '')      $filters['assigned_to'] = $assignedTo;

if ($statusParam !== '') {
  if (strpos($statusParam, ',') !== false) {
    $filters['status_in'] = explode(',', $statusParam);
  } else {
    $filters['status'] = $statusParam;
  }
}

$list  = cr_list($conn, $filters, $page, $perPage);
$total = $list['total'];
$pages = max(1, (int)ceil($total / $perPage));
$csrf  = $_SESSION['admin_csrf'] ?? '';
?>
<!doctype html>
<html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width">
<title>Richieste — Admin</title>
<style>
body{font-family:system-ui,Arial,sans-serif;background:#f5f7f9;padding:20px}
.topbar{display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
input,select{padding:.5rem;border:1px solid #cdd6df;border-radius:8px}
a.btn{display:inline-block;padding:.5rem .8rem;background:#004c60;color:#fff;text-decoration:none;border-radius:8px}
table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border:1px solid #e5e9ef;border-radius:10px;overflow:hidden}
th,td{padding:.6rem .7rem;border-bottom:1px solid #eef2f6;font-size:.95rem}
th{background:#f0f5f8;text-align:left}
tr:last-child td{border-bottom:0}
.badge{display:inline-block;padding:.15rem .45rem;border-radius:999px;font-size:.78rem}
.badge.new{background:#e8f0ff;color:#1a4}
.badge.in_review{background:#fff3cd;color:#a66}
.badge.replied{background:#e7f6ee;color:#205b36}
.badge.closed{background:#eee;color:#666}
.pager{margin-top:10px;display:flex;gap:6px;flex-wrap:wrap}
.pager a{padding:.35rem .6rem;border:1px solid #cdd6df;border-radius:8px;text-decoration:none;color:#333;background:#fff}
.pager .cur{background:#004c60;color:#fff;border-color:#004c60}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
</style>
</head><body>

<div class="header">
  <h1>Richieste ricevute</h1>
  <a class="btn" href="/backend/admin/logout.php">Logout</a>
</div>

<form class="topbar" method="get">
  <input type="text" name="q" placeholder="Cerca (email, nome, azienda, testo…)" value="<?= htmlspecialchars($q,ENT_QUOTES) ?>">
  <select name="status">
    <option value="">Tutte</option>
    <?php foreach (['new','in_review','replied','closed'] as $s): ?>
      <option value="<?= $s ?>" <?= $statusParam===$s?'selected':''?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="assigned_to" placeholder="Assegnata a…" value="<?= htmlspecialchars($assignedTo,ENT_QUOTES) ?>">
  <button class="btn" type="submit">Filtra</button>
</form>

<table>
  <thead>
    <tr>
      <th>Data</th>
      <th>Da</th>
      <th>Tipo</th>
      <th>Oggetto</th>
      <th>Stato</th>
      <th>Azioni</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($list['items'] as $r): ?>
      <?php
        $who = $r['email'];
        if ($r['tipo']==='privati') {
          $full = trim(($r['nome']??'') . ' ' . ($r['cognome']??''));
          if ($full !== '') $who = $full . " <{$r['email']}>";
        } else {
          $rg = $r['rgs'] ?? '';
          if ($rg !== '') $who = $rg . " <{$r['email']}>";
        }
        $subject = ($r['tipo']==='privati' ? 'Privato' : 'Azienda') . ' — richiesta di preventivo';
      ?>
      <tr>
        <td><?= htmlspecialchars($r['created_at'],ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($who,ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($r['tipo'],ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars(mb_strimwidth($subject,0,60,'…'),ENT_QUOTES) ?></td>
        <td><span class="badge <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        <td><a class="btn" href="/backend/admin/request.php?id=<?= (int)$r['id'] ?>">Apri</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list['items']): ?>
      <tr><td colspan="6">Nessun risultato.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<div class="pager">
  <?php for($p=1;$p<=$pages;$p++): ?>
    <?php
      $qs = http_build_query(['q'=>$q,'status'=>$statusParam,'assigned_to'=>$assignedTo,'page'=>$p]);
      $cls = $p===$page ? 'cur' : '';
    ?>
    <a class="<?= $cls ?>" href="?<?= $qs ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>

</body></html>
