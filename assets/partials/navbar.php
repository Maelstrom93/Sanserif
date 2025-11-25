<?php
$menu = [
  '/'             => 'HOME',
  'chi_siamo'     => 'CHI SIAMO',
  'cosa_offriamo' => 'COSA OFFRIAMO',
  'portfolio'     => 'PORTFOLIO',
  'contatti'      => 'CONTATTI',

  /*'blog'          => 'BLOG',*/
];

$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$reqPath = rtrim($reqPath, '/') === '' ? '/' : rtrim($reqPath, '/');

function href_for(string $key): string {
  return $key === '/' ? '/' : '/'.$key;
}
function is_active(string $href, string $reqPath): bool {
  if ($href === '/') return $reqPath === '/';
  if (strpos($reqPath, $href) !== 0) return false;
  return strlen($reqPath) === strlen($href) || $reqPath[strlen($href)] === '/';
}
?>
<header class="navbar" id="navbar">
  <div class="nav-inner">
    <a href="/" class="nav-logo" aria-label="Homepage" rel="home">
      <img src="assets/img/logow.webp" width="132" height="36" alt="Sans Serif – Spazio Editoriale" decoding="async" loading="eager" />
    </a>

    <ul class="nav-list" id="nav-list" aria-label="Menu principale">
      <?php foreach ($menu as $key => $label): 
        $href   = href_for($key);
        $active = is_active($href, $reqPath);
      ?>
        <li>
          <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"
             class="<?= $active ? 'active' : '' ?>"
             <?= $active ? 'aria-current="page"' : '' ?>>
            <?= htmlspecialchars($label, ENT_QUOTES) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <button class="hamburger" id="hamburger" aria-label="Apri menu" aria-controls="nav-list" aria-expanded="false">
      <i class="fa-solid fa-bars" id="hamb-icon"></i>
    </button>
  </div>
</header>
