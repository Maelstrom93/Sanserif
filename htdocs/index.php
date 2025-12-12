<?php
require_once 'assets/funzioni/funzioni.php';
log_visita('Homepage');

$csp_nonce = base64_encode(random_bytes(16));

$secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme  = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ($secure ? 'https' : 'http');
$host    = $_SERVER['HTTP_HOST'] ?? 'example.com';
$base_url = $scheme . '://' . $host . '/';

$baseurl = $base_url;

$base_url = 'https://sansserifspazioeditoriale.it/';
$canonical = $base_url;
$og_image  = $base_url . 'assets/img/og-cover.jpg';

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

  <title>Servizi editoriali per libri e riviste – Sans Serif: editing, correzione bozze, impaginazione</title>

  <meta name="description" content="Sans Serif è uno spazio editoriale specializzato in servizi editoriali per libri e riviste: editing, correzione bozze, impaginazione e cura redazionale per case editrici e autori indipendenti, in tutta Italia e online." />
  <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1" />

  <meta name="author" content="Sans Serif – Spazio Editoriale" />
  <meta name="copyright" content="Sans Serif – Spazio Editoriale" />

  <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="it-IT" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="x-default" />

  <meta property="og:type" content="website" />
  <meta property="og:locale" content="it_IT" />
  <meta property="og:site_name" content="Sans Serif – Spazio Editoriale" />
  <meta property="og:title" content="Servizi editoriali Sans Serif: editing, correzione bozze, impaginazione" />
  <meta property="og:description" content="Curiamo il testo con precisione artigiana: editing, correzione bozze, impaginazione e riviste." />
  <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />

  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Servizi editoriali Sans Serif: editing, correzione bozze, impaginazione" />
  <meta name="twitter:description" content="Sans Serif è uno spazio editoriale: redazione, editing, correzione bozze, impaginazione e riviste." />
  <meta name="twitter:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />

  <meta name="theme-color" content="#ffffff" />
  <meta name="format-detection" content="telephone=no" />



  <link rel="preload" as="font" href="assets/font/ITCAvantGardeStd-Bk.woff" type="font/woff" crossorigin>
  <link rel="preload" as="font" href="assets/font/ITCAvantGardeStd-Bk.woff2" type="font/woff2" crossorigin>
  <link rel="preload"
        as="image"
        href="assets/img/logo.webp"
        imagesrcset="assets/img/logo.webp 460w"
        imagesizes="(max-width: 600px) 70vw, 460px">


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

  <link rel="icon" href="/favicon.ico" sizes="any" />
  <link rel="icon" href="/favicon-96x96.png" type="image/png" />
  <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
<link rel="icon" type="image/png" href="/favicon.png">

  <script nonce="<?= $csp_nonce ?>" type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "@id": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>#organization",
    "name": "Sans Serif – Spazio Editoriale",
    "url": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>",
    "logo": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>assets/img/logo.webp",
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
    "@id": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>#website",
    "url": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>",
    "name": "Sans Serif – Spazio Editoriale",
    "inLanguage": "it-IT",
    "publisher": {
      "@id": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>#organization"
    }
  }
  </script>

  <script nonce="<?= $csp_nonce ?>" type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "@id": "<?= htmlspecialchars($canonical, ENT_QUOTES) ?>#webpage",
    "name": "Servizi editoriali per libri e riviste – Sans Serif",
    "url": "<?= htmlspecialchars($canonical, ENT_QUOTES) ?>",
    "description": "Sans Serif è uno spazio editoriale specializzato in editing, correzione bozze, impaginazione e cura redazionale di libri e riviste per editori e autori.",
    "inLanguage": "it-IT",
    "isPartOf": {
      "@id": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>#website"
    },
    "primaryImageOfPage": "<?= htmlspecialchars($og_image, ENT_QUOTES) ?>"
  }
  </script>

  <script nonce="<?= $csp_nonce ?>" type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "ProfessionalService",
    "@id": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>#servizi-editoriali",
    "name": "Servizi editoriali Sans Serif",
    "description": "Servizi editoriali di editing, correzione bozze, impaginazione e cura redazionale per narrativa, saggistica e riviste, offerti online in tutta Italia.",
    "url": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>",
    "areaServed": "IT",
    "inLanguage": "it-IT",
    "provider": {
      "@id": "<?= htmlspecialchars($base_url, ENT_QUOTES) ?>#organization"
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
        "item":"<?= htmlspecialchars($canonical, ENT_QUOTES) ?>"
      }
    ]
  }
  </script>

</head>

<body class="page-home">

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


  <div class="nav-overlay" id="nav-overlay" hidden></div>

  <main>
    <div class="hero hero--inner_index" aria-hidden="true"></div>

   <section class="section-logo" id="menu-logo">
  <div class="wrap">
    <img
      src="assets/img/logo.webp"
      alt="Logo Sans Serif – Spazio Editoriale"
      width="460"
      height="180"
      loading="eager"
      decoding="async"
      fetchpriority="high"
    />
    <p class="cit">
          Pensiamo che gli autori debbano fare solo gli autori.<br>
          Del resto ci occupiamo noi.<br>
          Professionalità ed esperienza al vostro servizio.
        </p>
      </div>
    </section>

    <section class="about prima">
      <div class="contenuto">
        <div class="testo">

         <h1>Dalla prima alla quarta (di copertina)</h1>

<p>
  Sans serif è uno spazio editoriale: spazio di idee e confronto, dai contorni fluidi come fluido è il mondo editoriale.
  Qui lavoriamo, con passione, con dedizione, con fatica, per realizzare a pieno le potenzialità di ogni testo.
</p>

<p>
  Siamo persone con competenze ed esperienze diverse ma con tanti punti in comune: amiamo il nostro lavoro,
  seguiamo i progetti con cura e serietà, siamo un po’ nerd, siamo nomadi digitali, la curiosità ci guida e ci apre alle novità
  ma di sottecchi ogni tanto i nostri nasi si tuffano fra le pagine dei libri di carta per sentirne il profumo.
</p>

<p>
  Crediamo che i libri debbano diffondere conoscenza ma anche divertire, che debbano assistere nella vita quotidiana
  ma anche aprire a vite alternative quando non impossibili; per questo curiamo dalla saggistica alla narrativa,
  passando dalle riviste, seguendo ogni fase di lavorazione dalla prima alla quarta, dal dattiloscritto al file di stampa.
  Ci piace parlare in modo chiaro, diretto, senza fronzoli. Sans Serif, appunto.
</p>

<p>
  Scopri <a href="/chi_siamo">chi siamo</a>,
  i nostri <a href="/cosa_offriamo"><strong>servizi editoriali</strong></a>
  e <a href="/contatti">come contattarci</a>.
</p>


        </div>
      </div>
    </section>

  </main>

  <?php include 'assets/partials/footer.php'; ?>

  <script src="assets/javascript/main.js" defer></script>
</body>
</html>
