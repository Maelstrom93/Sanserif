<?php
require_once 'assets/funzioni/funzioni.php';
log_visita('Chi Siamo');

$csp_nonce = base64_encode(random_bytes(16));

$secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme  = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ($secure ? 'https' : 'http');
$host    = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseurl = $scheme . '://' . $host . '/';
$canonical = $baseurl . 'chi_siamo';
$og_image  = $baseurl . 'assets/img/og-team.jpg';

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
  <title>Chi siamo – Team editoriale Sans Serif</title>
  <meta name="description" content="Conosci il team editoriale di Sans Serif – Spazio Editoriale: professioniste dell’editoria specializzate in editing, correzione bozze, impaginazione e riviste, in tutta Italia e online." />
  <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1" />
  <meta name="author" content="Sans Serif – Spazio Editoriale" />
  <meta name="copyright" content="Sans Serif – Spazio Editoriale" />
  <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="it-IT" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="x-default" />
  <meta property="og:type" content="website" />
  <meta property="og:locale" content="it_IT" />
  <meta property="og:site_name" content="Sans Serif – Spazio Editoriale" />
  <meta property="og:title" content="Chi siamo – Team editoriale Sans Serif" />
  <meta property="og:description" content="Conosci il team di Sans Serif: professioniste dell’editoria con esperienza in editing, correzione bozze, impaginazione e riviste." />
  <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Chi siamo – Team editoriale Sans Serif" />
  <meta name="twitter:description" content="Conosci il team di Sans Serif: professioniste dell’editoria con esperienza in editing, correzione bozze, impaginazione e riviste." />
  <meta name="twitter:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />
  <meta name="theme-color" content="#ffffff" />
  <meta name="format-detection" content="telephone=no" />
   <link rel="icon" href="/favicon.ico" sizes="any" />
  <link rel="icon" href="/favicon-96x96.png" type="image/png" />
  <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
<link rel="icon" type="image/png" href="/favicon1.png">
    <link rel="preload" as="font" href="assets/font/ITCAvantGardeStd-Bk.woff" type="font/woff" crossorigin>
  <link rel="preload" as="font" href="assets/font/ITCAvantGardeStd-Bk.woff2" type="font/woff2" crossorigin>
  <link rel="preload" as="image" href="assets/img/hero_team.webp">
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
    "@type":"AboutPage",
    "@id":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>#webpage",
    "name":"Chi siamo – Sans Serif – Spazio Editoriale",
    "description":"Conosci il team editoriale di Sans Serif: professioniste dell’editoria con esperienza in editing, correzione bozze, impaginazione e riviste.",
    "url":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>",
    "inLanguage":"it-IT",
    "isPartOf": {
      "@id":"<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#website"
    },
    "about": {
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
        "name":"Chi siamo",
        "item":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>"
      }
    ]
  }
  </script>
</head>


<body class="page-team">

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


  <div class="hero hero--inner_team" aria-hidden="true"></div>

  <main>
    <section class="dark">
      <div class="about prima">
        <div class="contenuto">
          <div class="testo">
         <p>
              Siamo persone provenienti da città, percorsi e studi diversi, accomunate da una stessa passione: lavorare con e sui libri. Per uno strano caso di serendipità, siamo finite a seguire lo stesso master di editoria e a trovare la guida dello stesso mentore. Dopo anni di gavetta individuale e una fase di rodaggio come gruppo, avendo colto i messaggi nemmeno troppo sottili che il destino stava inviando, abbiamo deciso di fare una scommessa e lanciarci in una nuova avventura collettiva che esaltasse i punti di forza di ciascuna identità e, allo stesso tempo, ne compensasse i punti deboli. Siamo sicure che sarà una formidabile avventura e che ogni nuova pagina porterà un valore aggiunto.<br><br>

           Scopri anche i nostri <a href="/cosa_offriamo">servizi editoriali</a> o
            <a href="/contatti">contattaci</a> per un preventivo.
          </p>
          </div>
        </div>
      </div>
    </section>

    <div class="site-content">
    
      <section class="intro">
        <div class="container">
          <h1>Il nostro team</h1>
           
        </div>
      </section>

      <section class="team">
        <div class="container">
          <div class="team-grid" id="team-grid"></div>
        </div>
      </section>
    </div>
  </main>

  <?php include 'assets/partials/footer.php'; ?>

  <script src="assets/javascript/main.js" defer></script>
  <script src="assets/javascript/team.js" defer></script>
</body>
</html>
