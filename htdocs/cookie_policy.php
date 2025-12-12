<?php
require_once 'assets/funzioni/funzioni.php';
log_visita('Cookie Policy');

$csp_nonce = base64_encode(random_bytes(16));

$secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme  = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ($secure ? 'https' : 'http');
$host    = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseurl = $scheme . '://' . $host . '/';
$canonical = $baseurl . 'cookie_policy';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://cdnjs.cloudflare.com; style-src 'self' 'nonce-{$csp_nonce}' https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests;");
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
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Cookie Policy — Sans Serif</title>
<link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />

<link rel="stylesheet" href="assets/css/style1.css" />

<style nonce="<?= $csp_nonce ?>">

body.page-cookie { background:#f7f8f9; }
.page-cookie .cookie-wrap{
  max-width: 900px; margin: 0 auto;
  padding: calc(64px + 3rem) 1.5rem 3rem;
}
.page-cookie .cookie-wrap h1{
  color: var(--primario);
  font-size: clamp(2rem, 3vw, 2.6rem);
  margin: 0 0 .5rem;
}
.page-cookie .cookie-wrap h2{
  margin-top: 2.2rem;
  color: var(--primario);
  font-size: 1.6rem;
  scroll-margin-top: 90px; 
}
.page-cookie .cookie-wrap h3{
  margin: 1.4rem 0 .3rem;
  color: var(--testo);
  font-size: 1.1rem;
}
.page-cookie .cookie-wrap p{ color: var(--testo); line-height:1.7; margin:.6rem 0 1rem; }

.page-cookie .cookie-toc ul{
  list-style:none; margin: 1rem 0 2rem; padding:0;
  display:grid; gap:.5rem;
}
.page-cookie .cookie-toc li{
  position:relative; padding-left:1.25rem;
  opacity:0; transform: translateY(4px);
  animation: fadeList .35s ease forwards;
}
.page-cookie .cookie-toc li::before{
  content:""; position:absolute; left:0; top:.55em;
  width:.55rem; height:.55rem; border-radius:999px;
  background: var(--primario);
  box-shadow: 0 0 0 3px rgba(0,76,96,.12);
  transition: transform .22s ease, box-shadow .22s ease;
}
.page-cookie .cookie-toc a{
  color: var(--testo); text-decoration:none; font-weight:700;
  transition: color .22s ease, text-underline-offset .22s ease;
  text-decoration-thickness: 2px; text-underline-offset: 3px;
}
.page-cookie .cookie-toc a:hover,
.page-cookie .cookie-toc a:focus{
  color: var(--primario);
  text-decoration: underline;
  text-underline-offset: 5px;
}
.page-cookie .cookie-toc li:hover::before{ transform:scale(1.1); box-shadow: 0 0 0 4px rgba(0,76,96,.18) }

.page-cookie .cookie-wrap ul{ margin:.4rem 0 1rem 1.2rem; padding:0; }
.page-cookie .cookie-wrap ul li{
  margin:.3rem 0; line-height:1.6;
  opacity:0; transform: translateY(4px);
  animation: fadeList .35s ease forwards;
}
.page-cookie .cookie-wrap ul li:nth-child(1){animation-delay:.05s}
.page-cookie .cookie-wrap ul li:nth-child(2){animation-delay:.10s}
.page-cookie .cookie-wrap ul li:nth-child(3){animation-delay:.15s}
.page-cookie .cookie-wrap ul li:nth-child(4){animation-delay:.20s}
.page-cookie .cookie-wrap ul li:nth-child(5){animation-delay:.25s}
.page-cookie .cookie-wrap ul li:nth-child(6){animation-delay:.30s}
.page-cookie .cookie-wrap ul li a{
  transition: color .25s ease, text-decoration-color .25s ease;
}
.page-cookie .cookie-wrap ul li a:hover{
  color: var(--primario); text-decoration-color: var(--primario);
}

.page-cookie .browser-list{
  list-style:none; margin:.75rem 0 1.5rem; padding:0;
  display:grid; gap:.55rem;
}
.page-cookie .browser-list li{
  background:#fff; border:1px solid rgba(0,76,96,.12);
  border-radius:10px; padding:.6rem .8rem;
  display:flex; align-items:center; gap:.6rem;
  transition: transform .16s ease, box-shadow .2s ease, border-color .2s ease;
}
.page-cookie .browser-list li::before{
  content:""; width:18px; height:18px; flex:0 0 18px; border-radius:4px;
  background: var(--primario);
  mask: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23fff' d='M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg>") center/contain no-repeat;
}
.page-cookie .browser-list a{
  color: var(--primario); text-decoration:none; font-weight:700;
  transition: color .2s ease;
}
.page-cookie .browser-list li:hover{
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(0,0,0,.08);
  border-color: rgba(0,76,96,.22);
}
.page-cookie .browser-list li:hover a{ color:#003847 }

.page-cookie .back-top{
  display:inline-flex; align-items:center; justify-content:center;
  gap:.45rem; background: var(--primario); color:#fff;
  width:auto; height:auto; padding:.55rem 1rem; border-radius:999px;
  font-size:.88rem; text-decoration:none; font-weight:800;
  text-transform:uppercase;
  transition: filter .25s ease, transform .15s ease, box-shadow .25s ease;
}
.page-cookie .back-top:hover{ filter:brightness(1.1); box-shadow:0 8px 18px rgba(0,0,0,.12) }
.page-cookie .back-top:active{ transform: scale(.96) }

.page-cookie .back-top--floating{
  position:fixed; right:18px; bottom:18px; z-index:999;
  width:46px; height:46px; padding:0; border-radius:50%;
  font-size:1.2rem; line-height:46px; text-align:center;
  box-shadow:0 8px 20px rgba(0,0,0,.14);
  transform: translateY(12px); opacity:0; pointer-events:none;
  transition: opacity .25s ease, transform .25s ease;
}
.page-cookie .back-top--floating.is-visible{ opacity:1; transform: translateY(0); pointer-events:auto; }

@keyframes fadeList{ from{opacity:0; transform:translateY(4px)} to{opacity:1; transform:none} }
</style>
</head>
<body class="page-cookie">

<?php include 'assets/partials/navbar.php'; ?>

<main class="cookie-wrap">
  <span id="top" aria-hidden="true"></span>

  <h1>Cookie Policy</h1>
  <p>Informativa sull’uso dei cookie su questo sito. Aggiornata il <strong>08/11/2025</strong>.</p>

  <nav class="cookie-toc" aria-label="Indice della pagina">
    <ul>
      <li><a href="#c1">1. Cosa sono i cookie</a></li>
      <li><a href="#c2">2. Tipologie di cookie</a></li>
      <li><a href="#c3">3. Cookie utilizzati da questo sito</a></li>
      <li><a href="#c4">4. Come gestirli dal browser</a></li>
      <li><a href="#c5">5. Titolare del trattamento</a></li>
      <li><a href="#c6">6. Aggiornamenti</a></li>
    </ul>
  </nav>

  <h2 id="c1">1. Cosa sono i cookie</h2>
  <p>I cookie sono piccoli file di testo inviati al dispositivo dell’utente per memorizzare informazioni utili. A ogni visita successiva tali informazioni vengono ritrasmesse al sito che li ha impostati, consentendo il corretto funzionamento delle pagine e, in alcuni casi, la produzione di statistiche aggregate.</p>

  <h2 id="c2">2. Tipologie di cookie</h2>
  <h3>Cookie tecnici</h3>
  <p>Necessari al funzionamento del sito (navigazione, sicurezza, preferenze). Non richiedono consenso.</p>

  <h3>Cookie analitici</h3>
  <p>Servono per generare statistiche aggregate e anonime. Se di prima parte e minimizzati, sono equiparati ai cookie tecnici.</p>

  <h3>Cookie di profilazione</h3>
  <p><strong>Non utilizzati</strong> su questo sito.</p>

  <h2 id="c3">3. Cookie utilizzati da questo sito</h2>
  <p>Questo sito utilizza esclusivamente cookie tecnici di prima parte, necessari al corretto funzionamento delle pagine — ad esempio, cookie che prevengono conteggi duplicati nelle statistiche interne.</p>

  <h2 id="c4">4. Come gestirli dal browser</h2>
  <p>È possibile disabilitare i cookie dalle impostazioni del proprio browser:</p>
  <ul class="browser-list">
    <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Guida Chrome</a></li>
    <li><a href="https://support.mozilla.org/it/kb/attivare-e-disattivare-i-cookie" target="_blank" rel="noopener">Guida Firefox</a></li>
    <li><a href="https://support.apple.com/it-it/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Guida Safari</a></li>
    <li><a href="https://support.microsoft.com/it-it/microsoft-edge" target="_blank" rel="noopener">Guida Edge</a></li>
    <li><a href="https://help.opera.com/it/latest/web-preferences/" target="_blank" rel="noopener">Guida Opera</a></li>
  </ul>

  <h2 id="c5">5. Titolare del trattamento</h2>
  <p><strong>Sans Serif — Spazio Editoriale</strong><br>
  Email: <a href="mailto:info@sansserifspazioeditoriale.it">info@sansserifspazioeditoriale.it</a><br>
  Per ulteriori informazioni consulta anche la <a href="/privacy_policy">Privacy Policy</a>.
  </p>

  <h2 id="c6">6. Aggiornamenti</h2>
  <p>La presente Cookie Policy può essere aggiornata per adeguamenti normativi o tecnici. Le modifiche saranno pubblicate su questa pagina.</p>

  <a href="#top" class="back-top">↑ Torna su</a>
</main>

<a href="#top" class="back-top back-top--floating" id="backTop" aria-label="Torna su">↑</a>

<?php include 'assets/partials/footer.php'; ?>

<script src="assets/javascript/main.js" defer></script>
<script nonce="<?= $csp_nonce ?>">
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener("click", function(e){
    const target = document.querySelector(this.getAttribute("href"));
    if(target){
      e.preventDefault();
      const y = target.getBoundingClientRect().top + window.pageYOffset - 70;
      window.scrollTo({ top: y, behavior: "smooth" });
    }
  });
});

const backTop = document.getElementById('backTop');
function toggleBackTop(){
  if(!backTop) return;
  const scrolled = window.pageYOffset || document.documentElement.scrollTop;
  backTop.classList.toggle('is-visible', scrolled > 400);
}
toggleBackTop();
window.addEventListener('scroll', toggleBackTop, { passive:true });
</script>
</body>
</html>
