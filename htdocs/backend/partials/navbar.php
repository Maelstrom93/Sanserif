      <div class="actions-menu-wrap">
        <button class="chip actions-trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="menuAzioni">
          <i class="fas fa-bolt"></i> Azioni
          <i class="fa-solid fa-chevron-down" style="margin-left:4px; font-size:.85em"></i>
        </button>

        <nav id="menuAzioni" class="actions-dropdown" role="menu" aria-label="Azioni rapide">
          <a role="menuitem" class="item" href="/backend/lavori/index_lavori.php"><i class="fas fa-briefcase"></i> Lavori</a>
          <a role="menuitem" class="item" href="/backend/libri/index_libri.php"><i class="fas fa-book"></i> Portfolio</a>
          <a role="menuitem" class="item" href="/backend/articoli/index_articoli.php"><i class="fas fa-feather"></i> Articoli</a>
          <a role="menuitem" class="item" href="/backend/clienti/index_clienti.php"><i class="fas fa-user"></i> Clienti</a>
          <a role="menuitem" class="item" href="/backend/preventivi/index_preventivi.php"><i class="fas fa-file-invoice"></i> Preventivi</a>
          <a role="menuitem" class="item" href="/backend/email/index.php"><i class="fas fa-envelope"></i> Richieste</a>
          <a role="menuitem" class="item" href="/backend/calendario/calendario.php"><i class="fas fa-calendar-week"></i> Calendario</a>
          <?php if (isAdmin()): ?>
            <div class="sep" role="separator"></div>
            <a role="menuitem" class="item" href="/backend/admin/index_admin.php"><i class="fas fa-cog"></i> Admin</a>
          <?php endif; ?>
        </nav>
      </div>
