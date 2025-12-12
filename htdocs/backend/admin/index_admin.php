<?php
// file: backend/admin/index_admin.php
session_start();

require_once __DIR__ . '/../assets/funzioni/db/db.php';
require_once __DIR__ . '/../assets/funzioni/funzioni.php';

requireLogin();
if (!isAdmin()) {
  header('Location: /backend/index.php');
  exit;
}
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
$utente = $_SESSION['utente'] ?? [];
$nome   = $utente['nome']  ?? 'Utente';
$ruolo  = strtoupper($utente['ruolo'] ?? 'ADMIN');
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Area Amministrativa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="stylesheet" href="../assets/css/style1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* Piccole rifiniture per i tile admin (senza toccare lo stile globale) */
    .admin-tiles{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; }
    @media (max-width:1080px){ .admin-tiles{ grid-template-columns: repeat(2, minmax(0,1fr)); } }
    @media (max-width:720px){  .admin-tiles{ grid-template-columns: 1fr; } }

    .admin-tile{
      display:flex; gap:12px; align-items:flex-start; text-decoration:none;
      background:linear-gradient(180deg,#ffffff,#f8fafc);
      border:1px solid var(--primary-150); border-radius:14px; padding:14px;
      color:var(--primary); box-shadow: var(--shadow);
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease, background .12s ease;
      min-height:84px;
    }
    .admin-tile:hover{ transform:translateY(-2px); background:#fff; border-color:var(--primary-600); box-shadow:0 10px 24px rgba(2,6,23,.08); }
    .admin-tile .ico{
      width:44px; height:44px; border-radius:12px; display:grid; place-items:center; flex:0 0 44px;
      background:var(--primary-50); color:var(--primary-700); border:1px solid var(--primary-150); font-size:18px;
    }
    .admin-tile .txt{ display:flex; flex-direction:column; gap:4px; min-width:0; }
    .admin-tile .txt .title{ font-weight:900; letter-spacing:.2px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .admin-tile .txt .desc{ font-size:13px; color:var(--muted); line-height:1.25; }

    .section-head{ display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:8px; }
    .section-head h2{ margin:0; font-size:18px; }
  </style>
</head>
<body>
<main>
  <!-- Topbar -->
  <header class="topbar">
    <div class="user-badge">
      <i class="fas fa-user-shield icon-user" aria-hidden="true"></i>
      <div>
        <div class="muted">Bentornata,</div>
        <div style="font-weight:800; letter-spacing:.2px;"><?= e($nome) ?></div>
      </div>
      <span class="role"><?= e($ruolo) ?></span>
    </div>
    <div class="right">
      <?php include __DIR__ . '/../partials/navbar.php'; ?>
      <a class="chip" href="/backend/index.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <a class="chip" href="/backend/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <!-- Hero / introduzione -->
  <section class="panel" style="margin-top:16px;">
    <div class="section-head">
      <h2><i class="fa-solid fa-screwdriver-wrench"></i> Area Amministrativa</h2>
      <span class="chip s-ok"><i class="fa-solid fa-key"></i> Accesso Admin</span>
    </div>
    <div class="muted">Strumenti e impostazioni avanzate della piattaforma.</div>
  </section>

  <!-- Gestione contenuti -->
  <section class="panel" style="margin-top:12px;">
    <div class="section-head">
      <h2><i class="fa-solid fa-folder-gear"></i> Gestione contenuti</h2>
    </div>
    <div class="admin-tiles">
      <a class="admin-tile" href="/backend/admin/gestione_utenti.php">
        <span class="ico"><i class="fa-solid fa-users-gear"></i></span>
        <div class="txt">
          <div class="title">Gestione utenti</div>
          <div class="desc">Crea, modifica e assegna ruoli.</div>
        </div>
      </a>

      <a class="admin-tile" href="/backend/admin/categorie.php">
        <span class="ico"><i class="fa-solid fa-tags"></i></span>
        <div class="txt">
          <div class="title">Categorie</div>
          <div class="desc">Categorie condivise per libri, lavori, articoli.</div>
        </div>
      </a>

      <a class="admin-tile" href="/backend/email/index.php">
        <span class="ico"><i class="fa-solid fa-envelope-open-text"></i></span>
        <div class="txt">
          <div class="title">Gestione richieste</div>
          <div class="desc">Consulta e rispondi alle richieste in arrivo.</div>
        </div>
      </a>

      <a class="admin-tile" href="/backend/lavori/index_lavori.php">
        <span class="ico"><i class="fa-solid fa-briefcase"></i></span>
        <div class="txt">
          <div class="title">Gestione lavori</div>
          <div class="desc">Pianifica, assegna e monitora attività.</div>
        </div>
      </a>
    </div>
  </section>

  <!-- Sistema -->
  <section class="panel" style="margin-top:12px;">
    <div class="section-head">
      <h2><i class="fa-solid fa-shield-heart"></i> Sistema</h2>
    </div>
    <div class="admin-tiles">
      <a class="admin-tile" href="/backend/admin/log_attivita.php">
        <span class="ico"><i class="fa-solid fa-clipboard-list"></i></span>
        <div class="txt">
          <div class="title">Log attività</div>
          <div class="desc">Storico eventi e audit azioni utenti.</div>
        </div>
      </a>

      <a class="admin-tile" href="/backend/admin/backup.php">
        <span class="ico"><i class="fa-solid fa-database"></i></span>
        <div class="txt">
          <div class="title">Backup database</div>
          <div class="desc">Esporta/ristora backup di sicurezza.</div>
        </div>
      </a>

      <a class="admin-tile" href="/backend/admin/statistiche_visite.php">
        <span class="ico"><i class="fa-solid fa-chart-line"></i></span>
        <div class="txt">
          <div class="title">Statistiche visite</div>
          <div class="desc">Traffico e pagine più consultate.</div>
        </div>
      </a>

      <a class="admin-tile" href="/backend/admin/statistiche.php">
        <span class="ico"><i class="fa-solid fa-square-poll-vertical"></i></span>
        <div class="txt">
          <div class="title">Statistiche generali</div>
          <div class="desc">Andamento generale.</div>
        </div>
      </a>
    </div>
  </section>

  <!-- Note -->
  <section class="panel" style="margin-top:12px;">
    <h2 style="margin:0 0 8px;"><i class="fa-regular fa-circle-question"></i> Note & manutenzione</h2>
    <ul class="muted" style="margin:0; padding-left:18px; line-height:1.5;">
      <li>Esegui un <strong>backup</strong> prima di aggiornamenti importanti.</li>
      <li>Controlla periodicamente il <strong>log attività</strong> per anomalie.</li>
      <li>Le <strong>categorie</strong> sono condivise tra libri, lavori e articoli.</li>
    </ul>
  </section>
</main>
<script src="../assets/javascript/main.js"></script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
