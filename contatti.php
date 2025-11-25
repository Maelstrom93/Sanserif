<?php
require_once 'assets/funzioni/funzioni.php';
log_visita('Contatti');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',
  'secure' => $secure,
  'httponly' => true,
  'samesite' => 'Lax'
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (empty($_SESSION['hp_name'])) {
  $_SESSION['hp_name'] = 'hp_' . bin2hex(random_bytes(8));
}
$hp_name = $_SESSION['hp_name'];

$scheme   = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ($secure ? 'https' : 'http');
$host     = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseurl  = $scheme . '://' . $host . '/';
$canonical = $baseurl . 'contatti';
$og_image  = $baseurl . 'assets/img/og-contact.jpg';

$csp_nonce = base64_encode(random_bytes(16));
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
  <title>Contatti – Servizi editoriali Sans Serif</title>
  <meta name="description" content="Contatta Sans Serif – Spazio Editoriale per richiedere un preventivo di editing, correzione bozze, impaginazione o altri servizi editoriali per narrativa, saggistica e riviste." />
  <meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1" />
  <meta name="author" content="Sans Serif – Spazio Editorialiale" />
  <meta name="copyright" content="Sans Serif – Spazio Editorialiale" />

  <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="it-IT" />
  <link rel="alternate" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" hreflang="x-default" />

  <meta property="og:type" content="website" />
  <meta property="og:locale" content="it_IT" />
  <meta property="og:site_name" content="Sans Serif – Spazio Editoriale" />
  <meta property="og:title" content="Contatti – Servizi editoriali Sans Serif" />
  <meta property="og:description" content="Richiedi un preventivo per servizi editoriali di editing, correzione bozze, impaginazione e riviste." />
  <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES) ?>" />

  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Contatti – Servizi editoriali Sans Serif" />
  <meta name="twitter:description" content="Contatta Sans Serif – Spazio Editoriale per servizi di editing, correzione bozze, impaginazione e riviste." />
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
  <link rel="preload" as="image" href="assets/img/hero_contact.webp">
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
    "@context": "https://schema.org",
    "@type": "ContactPage",
    "@id": "<?= htmlspecialchars($canonical, ENT_QUOTES) ?>#webpage",
    "name": "Contatti – Sans Serif – Spazio Editoriale",
    "description": "Pagina contatti di Sans Serif – Spazio Editoriale per richiedere informazioni e preventivi sui servizi editoriali.",
    "url": "<?= htmlspecialchars($canonical, ENT_QUOTES) ?>",
    "inLanguage": "it-IT",
    "isPartOf": {
      "@id": "<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#website"
    },
    "about": {
      "@id": "<?= htmlspecialchars($baseurl, ENT_QUOTES) ?>#organization"
    },
    "primaryImageOfPage": "<?= htmlspecialchars($og_image, ENT_QUOTES) ?>"
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
        "name":"Contatti",
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


  <main id="main">
    <div class="hero hero--inner_contact" aria-hidden="true"></div>

    <section class="contact-wrapper" aria-labelledby="tit-contatti">
      <div class="contact-card">
        <h1 id="tit-contatti" class="c-title">Richiedi un preventivo</h1>
        <p class="c-lead">Scegli se sei un <b>Privato</b> o un’<b>Azienda</b> e compila il modulo.</p>

        <div class="contact-tabs" role="tablist" aria-label="Seleziona tipologia">
          <button class="ctab is-active" role="tab" aria-controls="pane-privati" aria-selected="true" id="tab-privati">
            <i class="fa-solid fa-user" aria-hidden="true"></i><span>Privati</span>
          </button>
          <button class="ctab" role="tab" aria-controls="pane-aziende" aria-selected="false" id="tab-aziende">
            <i class="fa-solid fa-building" aria-hidden="true"></i><span>Aziende</span>
          </button>
        </div>

        <div id="notice" class="notice" role="status" aria-live="polite" hidden></div>

        <form id="pane-privati" class="contact-form is-visible" method="post" action="contatti_send.php" novalidate aria-labelledby="tab-privati" autocomplete="on">
          <div class="hp-wrapper" aria-hidden="true">
            <input class="hp-field" type="text" name="<?= htmlspecialchars($hp_name, ENT_QUOTES) ?>" tabindex="-1" autocomplete="off" />
          </div>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
          <div class="grid2">
            <label class="field"><span>Nome</span><input type="text" name="nome" required autocomplete="given-name" /></label>
            <label class="field"><span>Cognome</span><input type="text" name="cognome" required autocomplete="family-name" /></label>
          </div>
          <label class="field"><span>Email</span><input type="email" name="email" required inputmode="email" autocomplete="email" /></label>
          <label class="field"><span>Messaggio</span><textarea name="msg" required rows="5"></textarea></label>
          <div class="actions"><button type="submit" name="invio" value="1" class="btn btn--primary">Invia</button></div>
        </form>

        <form id="pane-aziende" class="contact-form" method="post" action="contatti_send.php" novalidate aria-labelledby="tab-aziende" autocomplete="on">
          <div class="hp-wrapper" aria-hidden="true">
            <input class="hp-field" type="text" name="<?= htmlspecialchars($hp_name, ENT_QUOTES) ?>" tabindex="-1" autocomplete="off" />
          </div>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
          <div class="grid2">
            <label class="field"><span>Ragione Sociale</span><input type="text" name="rgs" required autocomplete="organization" /></label>
            <label class="field"><span>Referente</span><input type="text" name="settore" required autocomplete="name" /></label>
          </div>
          <label class="field"><span>Email</span><input type="email" name="email" required inputmode="email" autocomplete="email" /></label>
          <label class="field"><span>Messaggio</span><textarea name="msg" required rows="5"></textarea></label>
          <div class="actions"><button type="submit" name="invioazienda" value="1" class="btn btn--primary">Invia</button></div>
        </form>
      </div>
    </section>
  </main>

  <?php include 'assets/partials/footer.php'; ?>
  <script src="assets/javascript/main.js" defer></script>
  <script src="assets/javascript/contatti.js" defer></script>
  <noscript><p style="padding:1rem;text-align:center">Per inviare il modulo non è necessario JavaScript, ma alcune funzioni di interfaccia potrebbero non essere disponibili.</p></noscript>
</body>
</html>
