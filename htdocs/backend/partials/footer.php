<?php
  $nome    = htmlspecialchars($_SESSION['utente']['nome']  ?? 'Utente', ENT_QUOTES);
  $ruolo   = htmlspecialchars($_SESSION['utente']['ruolo'] ?? 'user',   ENT_QUOTES);
  $oggi    = (new DateTimeImmutable())->format('d/m/Y H:i');
  $ver     = 'v1.0.0';          // opzionale
  $brand   = 'Maelstrom';       // il tuo brand
  $primary = '#004c60';         // COLORE PRIMARIO
?>
<footer style="margin-top:24px;background:#fff;border-top:1px solid #e5e7eb;
               font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Arial;color:#0f172a;">
  <!-- barra accento -->
  <div style="height:4px;background:<?= $primary ?>;"></div>

  <!-- contenuto -->
  <div style="max-width:1240px;margin:0 auto;padding:12px 16px;
              display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <!-- utente / stato -->
    <div style="display:flex;align-items:center;gap:10px;flex:1 1 auto;min-width:260px;">
      <span style="font-size:13px;color:#334155;white-space:nowrap;">
        <strong><?= $nome ?></strong> (<?= $ruolo ?>)
      </span>
      <span aria-hidden="true" style="opacity:.35;">•</span>
      <span style="font-size:13px;color:#334155;white-space:nowrap;">
        Ultimo accesso: <?= $oggi ?>
      </span>
      <?php if (!empty($ver)): ?>
        <span aria-hidden="true" style="opacity:.35;">•</span>
        <span style="font-size:12px;padding:4px 8px;border-radius:999px;
                     background:#f8fafc;border:1px solid #e2e8f0;color:#0f172a;white-space:nowrap;">
          <?= $ver ?>
        </span>
      <?php endif; ?>
    </div>

    <!-- copyright -->
    <div style="display:flex;align-items:center;gap:8px;min-width:260px;justify-content:flex-end;">
      <div style="display:flex;align-items:center;gap:6px;white-space:nowrap;">
        <span style="font-size:13px;color:#334155;">&copy; <?= date('Y') ?></span>
        <span style="font-weight:700;font-size:13px;color:<?= $primary ?>;"><?= $brand ?></span>
        <span style="font-size:13px;color:#334155;">— Tutti i diritti riservati</span>
      </div>

      <div style="height:16px;width:1px;background:#e5e7eb;margin:0 8px;"></div>

      <!-- Torna su (pinned all’estrema destra) -->
      <button type="button"
              onclick="window.scrollTo({top:0,behavior:'smooth'})"
              title="Torna su"
              style="margin-left:auto;border:1px solid rgba(2,6,23,.08);background:#fff;color:#0f172a;
                     padding:6px 10px;border-radius:10px;font-size:12px;cursor:pointer;
                     display:inline-flex;align-items:center;gap:8px;
                     box-shadow:0 1px 0 rgba(0,0,0,.04);">
        <!-- freccia su -->
        <span style="display:inline-block;width:0;height:0;
                     border-left:6px solid transparent;border-right:6px solid transparent;
                     border-bottom:9px solid <?= $primary ?>;margin-top:2px;"></span>
        <span>Torna su</span>
      </button>
    </div>
  </div>
</footer>
