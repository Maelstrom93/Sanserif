<?php
require_once 'assets/funzioni/funzioni.php';
log_visita('Blog');


$csp_nonce = base64_encode(random_bytes(16));

$secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme  = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ($secure ? 'https' : 'http');
$host    = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseurl = $scheme . '://' . $host . '/';
$canonical = $baseurl . 'blog';
$og_image  = $baseurl . 'assets/img/og-blog.jpg';


header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdnjs.cloudflare.com; style-src 'self' 'nonce-{$csp_nonce}' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=()");
header("Cross-Origin-Resource-Policy: same-origin");
header("Cross-Origin-Opener-Policy: same-origin");
?>
<!doctype html>
<html lang="it" data-api-base="/api/">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="content-language" content="it" />

  <title>Blog — Sans Serif</title>
  <meta name="description" content="Articoli, novità e approfondimenti editoriali di Sans Serif." />
  <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1" />

  <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="it-IT" />

  <meta property="og:type" content="website" />
  <meta property="og:locale" content="it_IT" />
  <meta property="og:site_name" content="Sans Serif – Spazio Editoriale" />
  <meta property="og:title" content="Blog — Sans Serif" />
  <meta property="og:description" content="Articoli e approfondimenti di Sans Serif: editoria, libri, riviste, scrittura." />
  <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />

  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Blog — Sans Serif" />
  <meta name="twitter:description" content="Approfondimenti e novità dal mondo editoriale." />
  <meta name="twitter:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />

  <?php include 'assets/partials/favicon.php'; ?>

  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link rel="stylesheet" href="assets/css/style1.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />

  <script nonce="<?= $csp_nonce ?>" type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"CollectionPage",
    "name":"Blog — Sans Serif",
    "description":"Articoli e approfondimenti editoriali di Sans Serif.",
    "url":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>",
    "primaryImageOfPage":"<?= htmlspecialchars($og_image, ENT_QUOTES) ?>",
    "publisher":{
      "@type":"Organization",
      "name":"Sans Serif – Spazio Editoriale",
      "logo":"<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>assets/img/logo.webp",
      "sameAs":["https://www.instagram.com/sansserif_spazioeditoriale/"]
    }
  }
  </script>
  <script nonce="<?= $csp_nonce ?>" type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"BreadcrumbList",
    "itemListElement":[
      {
        "@type":"ListItem",
        "position":1,
        "name":"Home",
        "item":"<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>"
      },
      {
        "@type":"ListItem",
        "position":2,
        "name":"Blog",
        "item":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>"
      }
    ]
  }
  </script>
</head>

<body data-page="blog">
  <?php include 'assets/partials/navbar.php'; ?>

  <div class="menu-logo" aria-hidden="true">
    <img src="assets/img/logo.webp" alt="Sans Serif – Spazio Editoriale" width="460" height="180" />
  </div>

  <main>
    <div class="hero hero--inner_portfolio" aria-hidden="true"></div>

    <section class="intro">
      <div class="container">
        <h1>Blog</h1>
        <p class="testo">Articoli, novità e approfondimenti dal nostro spazio editoriale.</p>
      </div>
    </section>

    <section class="toolbar" role="region" aria-label="Filtri blog">
      <div class="t-row">
        <div class="filter-slot" style="position:relative">
          <button type="button" class="fbtn" id="btn-anni" aria-haspopup="true" aria-expanded="false">
            <i class="fa-regular fa-calendar"></i> Anno <span class="count" aria-hidden="true">0</span>
          </button>
          <div class="popover" id="pop-anni" role="dialog" aria-label="Filtra per anno">
            <div class="p-body" id="list-anni"></div>
            <div class="p-actions">
              <button class="link-reset" data-reset="anni">Azzera</button>
              <button class="btn btn--primary" id="close-anni">Chiudi</button>
            </div>
          </div>
        </div>

        <div class="filter-slot" style="position:relative">
          <button type="button" class="fbtn" id="btn-categorie" aria-haspopup="true" aria-expanded="false">
            <i class="fa-regular fa-folder-open"></i> Categoria <span class="count" aria-hidden="true">0</span>
          </button>
          <div class="popover" id="pop-categorie" role="dialog" aria-label="Filtra per categoria">
            <div class="p-body" id="list-categorie"></div>
            <div class="p-actions">
              <button class="link-reset" data-reset="categorie">Azzera</button>
              <button class="btn btn--primary" id="close-categorie">Chiudi</button>
            </div>
          </div>
        </div>

        <div class="search">
          <input type="search" id="q" placeholder="Cerca titolo, autore, testo…" aria-label="Cerca nel blog">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </div>
      </div>

      <div class="active-chips" id="activeChips" aria-live="polite"></div>
    </section>

    <div class="results-bar">
      <div id="resultsCount" aria-live="polite"></div>
      <button class="reset-all" id="resetAll" type="button">Azzera tutti i filtri</button>
    </div>

    <section class="portfolio-wrapper">
      <div class="grid" id="blogGrid"></div>
    </section>

    <div class="modal" id="modal">
      <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <button class="close" type="button" aria-label="Chiudi">×</button>
        <div id="modal-body"></div>
        <div class="modal-actions">
          <button type="button" class="btn btn--primary js-close-modal" aria-label="Chiudi">Chiudi</button>
        </div>
      </div>
    </div>
  </main>

  <?php include 'assets/partials/footer.php'; ?>

  <script src="assets/javascript/main.js" defer></script>
  <script src="assets/javascript/blog.js" defer></script>
</body>
</html>
