<?php
require_once 'assets/funzioni/funzioni.php';
log_visita('Cosa Offriamo');

$csp_nonce = base64_encode(random_bytes(16));

$secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme  = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ($secure ? 'https' : 'http');
$host    = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseurl = $scheme . '://' . $host . '/';
$canonical = $baseurl . 'cosa_offriamo';
$og_image  = $baseurl . 'assets/img/og-services.jpg';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdnjs.cloudflare.com; style-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=()");
header("Cross-Origin-Resource-Policy: same-origin");
header("Cross-Origin-Opener-Policy: same-origin");
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta http-equiv="content-language" content="it" />

  <title>Servizi editoriali – Editing, correzione bozze, impaginazione | Sans Serif</title>
  <meta name="description" content="I servizi editoriali di Sans Serif – Spazio Editoriale: editing, correzione bozze, impaginazione e cura redazionale di libri e riviste per editori e autori, in tutta Italia e online." />
  <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1" />
  <meta name="author" content="Sans Serif – Spazio Editoriale" />
  <meta name="copyright" content="Sans Serif – Spazio Editoriale" />

  <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="it-IT" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="x-default" />

  <meta property="og:type" content="website" />
  <meta property="og:locale" content="it_IT" />
  <meta property="og:site_name" content="Sans Serif – Spazio Editoriale" />
  <meta property="og:title" content="Servizi editoriali – Sans Serif" />
  <meta property="og:description" content="Editing, correzione bozze, impaginazione e riviste per editori e autori." />
  <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />

  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Servizi editoriali – Sans Serif" />
  <meta name="twitter:description" content="I servizi editoriali di Sans Serif: editing, correzione bozze, impaginazione e riviste per editori e autori." />
  <meta name="twitter:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />

  <meta name="theme-color" content="#ffffff" />
  <meta name="format-detection" content="telephone=no" />

  <?php include 'assets/partials/favicon.php'; ?>
  <link rel="icon" href="/favicon.ico" sizes="any" />
  <link rel="icon" href="/favicon-96x96.png" type="image/png" />
  <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
<link rel="icon" type="image/png" href="/favicon.png">

<link rel="preload" as="font" href="assets/font/ITCAvantGardeStd-Bk.woff" type="font/woff" crossorigin>
<link rel="preload" as="font" href="assets/font/ITCAvantGardeStd-Bk.woff2" type="font/woff2" crossorigin>
  <link rel="preload" as="image" href="assets/img/hero_service.webp">
  <link rel="preload" as="image" href="assets/img/logo.webp">

  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />

  <link rel="stylesheet" href="assets/css/style1.css" />

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
    "name":"Servizi editoriali — Sans Serif",
    "description":"I servizi editoriali di Sans Serif: editing, correzione bozze, impaginazione e riviste per editori e autori.",
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
    "@type":"ItemList",
    "name":"Servizi editoriali Sans Serif",
    "itemListElement":[
      {
        "@type":"ListItem",
        "position":1,
        "item":{
          "@type":"Service",
          "name":"Editing",
          "description":"Editing strutturale e di linea per narrativa e saggistica.",
          "provider":{
            "@id":"<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#organization"
          }
        }
      },
      {
        "@type":"ListItem",
        "position":2,
        "item":{
          "@type":"Service",
          "name":"Correzione bozze",
          "description":"Revisione ortografica, grammaticale e stilistica di testi editi e inediti.",
          "provider":{
            "@id":"<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#organization"
          }
        }
      },
      {
        "@type":"ListItem",
        "position":3,
        "item":{
          "@type":"Service",
          "name":"Impaginazione",
          "description":"Impaginazione professionale di libri, riviste e materiali editoriali.",
          "provider":{
            "@id":"<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#organization"
          }
        }
      },
      {
        "@type":"ListItem",
        "position":4,
        "item":{
          "@type":"Service",
          "name":"Riviste e progetti editoriali",
          "description":"Coordinamento editoriale, sviluppo e gestione di riviste e collane.",
          "provider":{
            "@id":"<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#organization"
          }
        }
      }
    ]
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
        "name":"Servizi editoriali",
        "item":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>"
      }
    ]
  }
  </script>
</head>

<body>
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
    <div class="hero hero--inner_service" aria-hidden="true"></div>

    <section class="intro">
      <div class="container">
        <h1>I nostri servizi</h1>
        <p>Ogni libro ha esigenze diverse: scegli tra i nostri servizi editoriali quello più adatto al tuo progetto.</p>
      </div>
    </section>

    <section class="services-wrapper">
      <div id="servicesGrid"></div>
    </section>

    <div id="service-modal" class="modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-content" tabindex="-1">
    <div id="service-modal-body"></div>
    <div class="modal-actions">
    <button type="button"
        class="btn btn--primary js-close-modal"
        aria-label="Chiudi">Chiudi</button>
    </div>
  </div>
</div>
  </main>

  <?php include 'assets/partials/footer.php'; ?>

  <script src="assets/javascript/main.js" defer></script>
  <script src="assets/javascript/servizi.js" defer></script>
</body>
</html>
