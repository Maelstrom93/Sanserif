<?php
require_once 'assets/funzioni/funzioni.php';
log_visita('Portfolio');

$csp_nonce = base64_encode(random_bytes(16));

$secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme  = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ($secure ? 'https' : 'http');
$host    = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseurl = $scheme . '://' . $host . '/';
$canonical = $baseurl . 'portfolio';
$og_image  = $baseurl . 'assets/img/og-portfolio.jpg';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests;");
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

  <title>Portfolio – Progetti editoriali | Sans Serif</title>
  <meta name="description" content="Portfolio dei progetti editoriali curati da Sans Serif – Spazio Editoriale: editing, correzione bozze, impaginazione e riviste per editori e autori." />
  <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1" />
  <meta name="author" content="Sans Serif – Spazio Editoriale" />
  <meta name="copyright" content="Sans Serif – Spazio Editoriale" />

  <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="it-IT" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="x-default" />

  <meta property="og:type" content="website" />
  <meta property="og:locale" content="it_IT" />
  <meta property="og:site_name" content="Sans Serif – Spazio Editoriale" />
  <meta property="og:title" content="Portfolio — Sans Serif" />
  <meta property="og:description" content="Progetti editoriali seguiti da Sans Serif: editing, bozze, impaginazione e riviste." />
  <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />

  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Portfolio — Sans Serif" />
  <meta name="twitter:description" content="Progetti editoriali seguiti da Sans Serif: editing, bozze, impaginazione e riviste." />
  <meta name="twitter:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />

  <meta name="theme-color" content="#ffffff" />
  <meta name="format-detection" content="telephone=no" />

  <?php include 'assets/partials/favicon.php'; ?>
  <link rel="icon" href="/favicon.ico" sizes="any" />
  <link rel="icon" href="/favicon-96x96.png" type="image/png" />
  <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
<link rel="icon" type="image/png" href="/favicon.png">

    <link rel="preload" as="image" href="assets/img/hero_portfolio.webp">
  <link rel="preload" as="image" href="assets/img/logo.webp">
  <link rel="preload" as="font" href="assets/font/ITCAvantGardeStd-Bk.woff2" type="font/woff2" crossorigin>
  <link rel="preload" as="font" href="assets/font/ITCAvantGardeStd-Bk.woff" type="font/woff" crossorigin>

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
    "@context": "https://schema.org",
    "@type": "Organization",
    "@id": "<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#organization",
    "name": "Sans Serif – Spazio Editoriale",
    "url": "<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>",
    "logo": "<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>assets/img/logo.webp",
    "sameAs": [
      "https://www.instagram.com/sansserif_spazioeditoriale/"
    ],
    "contactPoint": [
      {
        "@type": "ContactPoint",
        "contactType": "customer support",
        "email": "info@sansserifspazioeditoriale.it",
        "areaServed": "IT",
        "availableLanguage": ["it"]
      }
    ]
  }
  </script>

  <script nonce="<?= $csp_nonce ?>" type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "@id": "<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#website",
    "url": "<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>",
    "name": "Sans Serif – Spazio Editoriale",
    "inLanguage": "it-IT",
    "publisher": {
      "@id": "<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#organization"
    }
  }
  </script>

  <script nonce="<?= $csp_nonce ?>" type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"CollectionPage",
    "@id":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>#webpage",
    "name":"Portfolio — Sans Serif",
    "description":"Portfolio dei progetti editoriali curati da Sans Serif.",
    "url":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>",
    "inLanguage":"it-IT",
    "isPartOf":{
      "@id":"<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#website"
    },
    "about":{
      "@id":"<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#organization"
    },
    "primaryImageOfPage":"<?= htmlspecialchars($og_image, ENT_QUOTES) ?>"
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
        "name":"Portfolio",
        "item":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>"
      }
    ]
  }
  </script>
</head>

<body data-page="portfolio">
  <?php include 'assets/partials/navbar.php'; ?>

<div class="menu-logo" aria-hidden="true">
  <img
    src="assets/img/logo.webp"
    alt="Sans Serif – Spazio Editoriale"
    width="460"
    height="180"
    loading="lazy"
    decoding="async"
  />
</div>


  <main>
    <div class="hero hero--inner_portfolio" aria-hidden="true"></div>

   <section class="intro"> 
      <div class="container" >
        <h1>Portfolio</h1>
          <p class="testo" >Una panoramica dei libri e dei progetti editoriali a cui abbiamo lavorato</p>
          </div>
    </section>

   <section class="toolbar" role="region" aria-label="Filtri portfolio">
      <div class="t-row">
        <fieldset class="segmented" aria-label="Vista">
          <input type="radio" id="seg-evid" name="segment" value="evidenza" checked>
          <label for="seg-evid">In evidenza</label>
          <input type="radio" id="seg-all" name="segment" value="tutti">
          <label for="seg-all">Tutti</label>
        </fieldset>

        <div class="filter-slot">
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
          <button type="button" class="fbtn" id="btn-editori" aria-haspopup="true" aria-expanded="false">
            <i class="fa-regular fa-building"></i> Editore <span class="count" aria-hidden="true">0</span>
          </button>
          <div class="popover" id="pop-editori" role="dialog" aria-label="Filtra per casa editrice">
            <div class="p-body" id="list-editori"></div>
            <div class="p-actions">
              <button class="link-reset" data-reset="editori">Azzera</button>
              <button class="btn btn--primary" id="close-editori">Chiudi</button>
            </div>
          </div>
        </div>

        <div class="filter-slot" style="position:relative">
          <button type="button" class="fbtn" id="btn-lavori" aria-haspopup="true" aria-expanded="false">
            <i class="fa-regular fa-folder-open"></i> Servizi <span class="count" aria-hidden="true">0</span>
          </button>
          <div class="popover" id="pop-lavori" role="dialog" aria-label="Filtra per lavorazioni">
            <div class="p-body" id="list-lavori"></div>
            <div class="p-actions">
              <button class="link-reset" data-reset="lavori">Azzera</button>
              <button class="btn btn--primary" id="close-lavori">Chiudi</button>
            </div>
          </div>
        </div>

        <div class="search">
          <input type="search" id="q" placeholder="Cerca titolo, editore, parole della sinossi…" aria-label="Cerca nel portfolio">
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
      <div class="grid" id="projectGrid"></div>
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
<button id="scrollTopBtn" class="scroll-top-btn" type="button" aria-label="Torna su">
  <i class="fa-solid fa-arrow-up"></i>
</button>

  </main>

  <?php include 'assets/partials/footer.php'; ?>

  <script src="assets/javascript/main.js" defer></script>
  <script src="assets/javascript/libri.js" defer></script> 
</body>
</html>
