const Servizi = [
  {
    nome: "Correzione bozze",
    descrizione:
      "La correzione bozze è l’ultima fase di lavorazione del testo, prima del fatidico “visto si stampi”, in cui si controlla il rispetto delle norme grammaticali, ortografiche, redazionali e l’aspetto generale del testo impaginato, che sia un libro o una rivista. Per farlo ci vogliono precisione, metodo e occhi che non hanno mai visto quel testo. In Sans Serif, questo passaggio fondamentale lo effettua chi non ha lavorato al dattiloscritto nelle altre fasi, in modo da garantire uno sguardo più distaccato ma anche un punto di vista diverso che permetta di cogliere ancor più sfumature.",
    img: "assets/img/bozza.webp",
  },
  {
    nome: "Copertina",
    descrizione:
      "La copertina è il primo incontro tra un libro e i suoi lettori: deve incuriosire, comunicare, distinguersi. Non è solo un involucro, ma una vera e propria dichiarazione d’intenti, capace di trasmettere l’identità dell’autore e il contenuto del testo. In Sans Serif accompagniamo ogni progetto editoriale nella definizione della veste grafica più adatta, con uno sguardo attento al mercato e alle tendenze visive contemporanee, senza mai perdere di vista l’eleganza e la coerenza con il testo.",
    img: "assets/img/copertina.webp",
  },
  {
    nome: "Copywriting",
    descrizione:
      "Ogni testo ha bisogno di cura e tempo. Anche quelli che non finiranno racchiusi fra due copertine colorate. Materiale pubblicitario, siti web, riviste, ogni prodotto che dovrà essere letto da qualcuno ha bisogno di essere redatto e revisionato da chi lo fa di professione. Noi di Sans Serif offriamo un servizio di creazione di contenuti, mirati a catturare l’attenzione dell’utente-lettore e aumentare la visibilità del prodotto/brand.​",
    img: "assets/img/copywriting.webp",
  },
  {
    nome: "Editing",
    descrizione:
      "L’editing, macro o micro che sia, rappresenta il primo passo nel processo di lavorazione del testo: è un importante momento di confronto e collaborazione fra l’editor e l’autore, alla ricerca della forma perfetta per esprimere il potenziale del singolo testo in lavorazione. Anche per questo, poniamo un occhio particolarmente attento al linguaggio inclusivo e non discriminatorio, prerequisito fondamentale di un testo efficace nell’attuale mercato editoriale. ​",
    img: "assets/img/editing.webp",
  },
  {
    nome: "Impaginazione, grafica e ricerca iconografica",
    descrizione:
      "L’aspetto di un libro, grazie anche a una maggior consapevolezza dei lettori, è divenuto fondamentale quanto il suo contenuto. È per questo che è necessario che le parole siano accompagnate, esaltate, completate da un’impaginazione e/o un apparato iconografico adeguati e da una copertina all’altezza del contenuto che racchiude. Per farlo, utilizziamo software professionali e ci avvaliamo di collaborazioni artistiche esterne qualora le vostre richieste non dovessero collimare con le nostre sensibilità. Offriamo anche un servizio di impaginazione e ricerca iconografica per riviste, curandole dal menabò al file di stampa. ​",
    img: "assets/img/impaginazione.webp",
  },
  {
    nome: "Revisione di traduzione",
    descrizione:
      "La traduzione di un testo non basta perché questo sia subito fruibile da parte del lettore. Il testo in italiano va infatti rivisto da un redattore professionista che verifichi che il linguaggio sia sempre fluente in italiano e non si trascini involontari calchi dalla lingua del testo di provenienza, che la punteggiatura sia rispettosa delle regole grammaticali dell’italiano e non semplicemente trasportata da una lingua all’altra, che i riferimenti culturali siano comprensibili… insomma, che il testo che poi sarà pubblicato risulti gradevole al lettore come se fosse stato scritto in italiano. Per questo motivo offriamo un servizio di revisione di traduzione per testi tradotti da tutte le lingue, se non specialistici, e dall’inglese e dal francese nel caso in cui fosse fondamentale il confronto con il testo in lingua originale.",
    img: "assets/img/revisione.webp",
  },
  {
    nome: "Traduzione",
    descrizione:
      "Tradurre vuol dire costruire ponti, fra lingue, fra culture, fra società. Il lavoro di chi traduce non si esaurisce nella ricerca del termine filologicamente più adeguato per rendere nella propria lingua una parola straniera ma si dilata nel tentativo di comunicare una cultura altra tramite linguaggi, topoi, metafore e figure retoriche familiari a chi sta leggendo. Questo vale per il lavoro sui libri ma vale per i testi di qualunque natura.Noi di Sans Serif offriamo un servizio di traduzione dall’inglese e dal francese verso l’italiano per tutti i tipi di testi, da quelli commerciali e pubblicitari a quelli destinati al web o alle riviste.​",
    img: "assets/img/traduzione.webp",
  },
  {
    nome: "Scheda di lettura",
    descrizione:
      "Per ogni libro pubblicato ne vengono scritti un migliaio che non hanno la possibilità di essere letti nelle case editrici per via della mole di lavoro nascosta dietro la pubblicazione di ogni singolo volume. Per snellire la selezione dei dattiloscritti che arrivano in redazione, offriamo quindi agli editori una lettura professionale dei testi e la redazione di schede che li sintetizzino con informazioni su trama, personaggi, stile, punti di forza, debolezze e potenzialità dell’opera, anche in relazione al catalogo della singola CE.",
    img: "assets/img/scheda_libro.webp",
  },
];

let _lastFocused = null;

function getAllServices() {
  return [...Servizi].sort((a, b) => a.nome.localeCompare(b.nome, "it", { sensitivity: "base" }));
}

function renderServices() {
  const grid = document.getElementById("servicesGrid");
  if (!grid) return;
  const services = getAllServices();
  grid.innerHTML = "";
  services.forEach((srv, i) => {
    const el = document.createElement("div");
    el.className = "scard article-block";
    el.style.animationDelay = `${i * 0.08}s`;
    el.innerHTML = `
  <div class="image-wrapper">
    <img
      src="${srv.img}"
      alt="${srv.nome}"
      loading="lazy"
      decoding="async"
      width="800"
      height="400"
    >
    <div class="boverlay">Scopri</div>
  </div>
  <div class="sinfo">
    <h3 class="stitle">${srv.nome}</h3>
  </div>
`;
   el.tabIndex = 0;
    el.setAttribute("role", "button");
    el.addEventListener("click", () => openServiceModal(srv, el));
    el.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        openServiceModal(srv, el);
      }
    });
    grid.appendChild(el);
  });
}

function bindServiceModalEvents() {
  const modal = document.getElementById("service-modal");
  if (!modal) return;
  const content = modal.querySelector(".modal-content");
  if (content && !content.hasAttribute("tabindex")) content.tabIndex = -1;

  modal.addEventListener("click", (e) => {
    if (e.target === modal || e.target.closest(".close, .js-close-modal")) closeServiceModal();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal.classList.contains("is-open")) closeServiceModal();
    if (e.key === "Tab" && modal.classList.contains("is-open")) trapFocus(modal, e);
  });
}

function openServiceModal(servizio, triggerEl) {
  const modal = document.getElementById("service-modal");
  const body = document.getElementById("service-modal-body");
  const content = modal.querySelector(".modal-content");
  body.innerHTML = `
    <h2>${servizio.nome}</h2>
    <p>${servizio.descrizione}</p>
  `;
  _lastFocused = triggerEl || document.activeElement;
  if (content && !content.hasAttribute("tabindex")) content.tabIndex = -1;
  modal.classList.add("is-open");
  modal.setAttribute("aria-hidden", "false");
  document.body.style.overflow = "hidden";
  requestAnimationFrame(() => content?.focus());
}

function closeServiceModal() {
  const modal = document.getElementById("service-modal");
  modal.classList.remove("is-open");
  modal.setAttribute("aria-hidden", "true");
  const unlock = () => {
    document.body.style.overflow = "";
    modal.removeEventListener("transitionend", unlock);
  };
  modal.addEventListener("transitionend", unlock);
  if (_lastFocused && typeof _lastFocused.focus === "function") _lastFocused.focus();
}

function trapFocus(scope, e) {
  const focusable = Array.from(
    scope.querySelectorAll('a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])')
  ).filter((el) => !el.hasAttribute("disabled") && el.offsetParent !== null);
  if (!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (e.shiftKey && document.activeElement === first) {
    e.preventDefault();
    last.focus();
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault();
    first.focus();
  }
}

document.addEventListener("DOMContentLoaded", () => {
  renderServices();
  bindServiceModalEvents();
});

window.closeServiceModal = closeServiceModal;
