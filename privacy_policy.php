<?php
require_once 'assets/funzioni/funzioni.php';
log_visita('Privacy Policy');

$csp_nonce = base64_encode(random_bytes(16));

$secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme  = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ($secure ? 'https' : 'http');
$host    = $_SERVER['HTTP_HOST'] ?? 'example.com';
$baseurl = $scheme . '://' . $host . '/';
$canonical = $baseurl . 'privacy_policy';

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
<title>Privacy Policy — Sans Serif</title>
<link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>" />
<link rel="stylesheet" href="assets/css/style1.css" />

<style nonce="<?= $csp_nonce ?>">

body.page-privacy { background:#f7f8f9; }
.page-privacy .pp-wrap{
  max-width: 900px; margin: 0 auto;
  padding: calc(64px + 3rem) 1.5rem 3rem;
}
.page-privacy .pp-wrap h1{
  color: var(--primario);
  font-size: clamp(2rem, 3vw, 2.6rem);
  margin: 0 0 .5rem;
}
.page-privacy .pp-wrap h2{
  margin-top: 2.2rem;
  color: var(--primario);
  font-size: 1.6rem;
  scroll-margin-top: 90px; 
}
.page-privacy .pp-wrap h3{
  margin: 1.2rem 0 .35rem;
  color: var(--testo);
  font-size: 1.12rem;
}
.page-privacy .pp-wrap p{ color: var(--testo); line-height:1.7; margin:.6rem 0 1rem; }
.page-privacy .muted{ color: var(--muted); }

*/
.page-privacy .pp-toc ul{
  list-style:none; margin: 1rem 0 2rem; padding:0;
  display:grid; gap:.5rem;
}
.page-privacy .pp-toc li{
  position:relative; padding-left:1.25rem;
  opacity:0; transform: translateY(4px);
  animation: fadeList .35s ease forwards;
}
.page-privacy .pp-toc li::before{
  content:""; position:absolute; left:0; top:.55em;
  width:.55rem; height:.55rem; border-radius:999px;
  background: var(--primario);
  box-shadow: 0 0 0 3px rgba(0,76,96,.12);
  transition: transform .22s ease, box-shadow .22s ease;
}
.page-privacy .pp-toc a{
  color: var(--testo); text-decoration:none; font-weight:700;
  transition: color .22s ease, text-underline-offset .22s ease;
  text-decoration-thickness: 2px; text-underline-offset: 3px;
}
.page-privacy .pp-toc a:hover,
.page-privacy .pp-toc a:focus{
  color: var(--primario);
  text-decoration: underline;
  text-underline-offset: 5px;
}
.page-privacy .pp-toc li:hover::before{ transform:scale(1.1); box-shadow: 0 0 0 4px rgba(0,76,96,.18) }

.page-privacy .pp-wrap ul{ margin:.4rem 0 1rem 1.2rem; padding:0; }
.page-privacy .pp-wrap ul li{
  margin:.3rem 0; line-height:1.6;
  opacity:0; transform: translateY(4px);
  animation: fadeList .35s ease forwards;
}

.page-privacy .pp-wrap ul li:nth-child(1){animation-delay:.05s}
.page-privacy .pp-wrap ul li:nth-child(2){animation-delay:.10s}
.page-privacy .pp-wrap ul li:nth-child(3){animation-delay:.15s}
.page-privacy .pp-wrap ul li:nth-child(4){animation-delay:.20s}
.page-privacy .pp-wrap ul li:nth-child(5){animation-delay:.25s}
.page-privacy .pp-wrap ul li:nth-child(6){animation-delay:.30s}
.page-privacy .pp-wrap ul li a{
  transition: color .25s ease, text-decoration-color .25s ease;
}
.page-privacy .pp-wrap ul li a:hover{
  color: var(--primario); text-decoration-color: var(--primario);
}


.page-privacy .callout{
  background:#fff; border:1px solid rgba(0,76,96,.12);
  border-radius:12px; padding:1rem 1.1rem; margin:1rem 0 1.25rem;
  box-shadow:0 6px 16px rgba(0,0,0,.05);
}

.page-privacy .back-top{
  display:inline-flex; align-items:center; justify-content:center;
  gap:.45rem; background: var(--primario); color:#fff;
  width:auto; height:auto; padding:.55rem 1rem; border-radius:999px;
  font-size:.88rem; text-decoration:none; font-weight:800;
  text-transform:uppercase;
  transition: filter .25s ease, transform .15s ease, box-shadow .25s ease;
}
.page-privacy .back-top:hover{ filter:brightness(1.1); box-shadow:0 8px 18px rgba(0,0,0,.12) }
.page-privacy .back-top:active{ transform: scale(.96) }


.page-privacy .back-top--floating{
  position:fixed; right:18px; bottom:18px; z-index:999;
  width:46px; height:46px; padding:0; border-radius:50%;
  font-size:1.2rem; line-height:46px; text-align:center;
  box-shadow:0 8px 20px rgba(0,0,0,.14);
  transform: translateY(12px); opacity:0; pointer-events:none;
  transition: opacity .25s ease, transform .25s ease;
}
.page-privacy .back-top--floating.is-visible{ opacity:1; transform: translateY(0); pointer-events:auto; }

@keyframes fadeList{ from{opacity:0; transform:translateY(4px)} to{opacity:1; transform:none} }
</style>
</head>
<body class="page-privacy">

<?php include 'assets/partials/navbar.php'; ?>

<main class="pp-wrap">
  <span id="top" aria-hidden="true"></span>

  <h1>Privacy Policy</h1>
  <p class="muted">Informativa ai sensi degli artt. 13–14 del Regolamento (UE) 2016/679 (“GDPR”). Aggiornata il <strong>08/11/2025</strong>.</p>

  <nav class="pp-toc" aria-label="Indice della pagina">
    <ul>
      <li><a href="#p1">1. Titolare del trattamento</a></li>
      <li><a href="#p2">2. Dati trattati</a></li>
      <li><a href="#p3">3. Finalità e basi giuridiche</a></li>
      <li><a href="#p4">4. Modalità e tempi di conservazione</a></li>
      <li><a href="#p5">5. Destinatari e categorie di soggetti</a></li>
      <li><a href="#p6">6. Trasferimenti extra-UE</a></li>
      <li><a href="#p7">7. Diritti dell’interessato</a></li>
      <li><a href="#p8">8. Sicurezza dei dati</a></li>
      <li><a href="#p9">9. Minori</a></li>
      <li><a href="#p10">10. Cookie</a></li>
      <li><a href="#p11">11. Aggiornamenti</a></li>
    </ul>
  </nav>

  <h2 id="p1">1. Titolare del trattamento</h2>
  <p>
    <strong>Sans Serif — Spazio Editoriale</strong><br>
    Email: <a href="mailto:info@sansserifspazioeditoriale.itm">info@sansserifspazioeditoriale.it</a><br>
    Per qualsiasi richiesta in materia di privacy, puoi contattarci all’indirizzo e-mail sopra indicato.
  </p>

  <h2 id="p2">2. Dati trattati</h2>
  <div class="callout">
    <h3>Dati di navigazione</h3>
    <p>Dati tecnici generati dai sistemi informatici (es. indirizzi IP in forma temporanea, log tecnici, user-agent, timestamp) trattati per finalità di sicurezza e funzionamento.</p>

    <h3>Dati comunicati dall’utente</h3>
    <p>Dati inviati volontariamente tramite form o contatti (es. nome, e-mail, eventuale messaggio e/o allegati).</p>

    <h3>Cookie e strumenti analoghi</h3>
    <p>Utilizziamo esclusivamente cookie tecnici/necessari e, se presenti, analitici di prima parte minimizzati. Per i dettagli consulta la <a href="/cookie_policy">Cookie Policy</a>.</p>
  </div>

  <h2 id="p3">3. Finalità e basi giuridiche</h2>
  <ul>
    <li><strong>Funzionamento del sito</strong> (erogazione delle pagine, sicurezza, prevenzione abusi) — <em>base giuridica</em>: interesse legittimo del Titolare (art. 6.1.f GDPR).</li>
    <li><strong>Gestione delle richieste</strong> inviate dall’utente (contatti, informazioni, preventivi) — <em>base giuridica</em>: esecuzione di misure precontrattuali/contrattuali (art. 6.1.b GDPR).</li>
    <li><strong>Adempimenti legali</strong> (es. obblighi fiscali, riscontro ad Autorità) — <em>base giuridica</em>: obbligo legale (art. 6.1.c GDPR).</li>
  </ul>

  <h2 id="p4">4. Modalità e tempi di conservazione</h2>
  <p>I dati sono trattati con misure tecniche e organizzative adeguate, con logiche strettamente correlate alle finalità indicate. I tempi di conservazione, in via indicativa:</p>
  <ul>
    <li><strong>Log tecnici</strong>: fino a 7–30 giorni salvo necessità di ulteriore conservazione in caso di sicurezza o incidenti.</li>
    <li><strong>Dati di contatto</strong>: per il tempo necessario a rispondere e gestire la richiesta; eventualmente più a lungo in caso di rapporto contrattuale.</li>
    <li><strong>Documentazione amministrativa</strong>: secondo i termini di legge applicabili.</li>
  </ul>

  <h2 id="p5">5. Destinatari e categorie di soggetti</h2>
  <p>I dati possono essere trattati da soggetti autorizzati dal Titolare e/o da fornitori che svolgono servizi tecnici/professionali (es. hosting, manutenzione, assistenza), nominati ove necessario <em>Responsabili del trattamento</em> ai sensi dell’art. 28 GDPR. I dati non sono diffusi.</p>

  <h2 id="p6">6. Trasferimenti extra-UE</h2>
  <p>Di norma i dati sono trattati all’interno dello Spazio Economico Europeo (SEE). Qualora si rendessero necessari trasferimenti verso Paesi terzi, il Titolare garantirà la conformità al Capo V del GDPR (decisioni di adeguatezza, Clausole Contrattuali Standard, misure supplementari).</p>

  <h2 id="p7">7. Diritti dell’interessato</h2>
  <p>L’utente può esercitare, nei limiti e alle condizioni di legge, i diritti previsti dagli artt. 15–22 GDPR: accesso, rettifica, cancellazione, limitazione, portabilità, opposizione, e revoca del consenso (ove prestato). Le richieste vanno inviate a <a href="mailto:info@sansserifspazioeditoriale.it">info@sansserifspazioeditoriale.it</a>.</p>
  <p>È inoltre possibile proporre reclamo all’Autorità Garante per la Protezione dei Dati Personali (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">garanteprivacy.it</a>).</p>

  <h2 id="p8">8. Sicurezza dei dati</h2>
  <p>Adottiamo misure tecniche e organizzative adeguate a proteggere i dati personali (es. controlli di accesso, configurazioni di sicurezza del server, aggiornamenti software, log tecnici, politiche di backup).</p>

  <h2 id="p9">9. Minori</h2>
  <p>Il sito e i servizi sono destinati a utenti di età pari o superiore a 14 anni. Non raccogliamo consapevolmente dati di minori. Se ritieni che un minore ci abbia fornito dati personali, contattaci per la rimozione.</p>

  <h2 id="p10">10. Cookie</h2>
  <p>Per informazioni dettagliate sull’uso dei cookie (tipologie, gestione dal browser, tempi), consulta la nostra <a href="/cookie_policy">Cookie Policy</a>.</p>

  <h2 id="p11">11. Aggiornamenti</h2>
  <p>La presente informativa può essere aggiornata per adeguamenti normativi o tecnici. Le modifiche saranno pubblicate su questa pagina.</strong>.</p>

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
