// assets/javascript/team.js
(function () {
  const MEMBERS = [
    {
      nome: "Roberta Callea",
      descrizione:
        "Avete presente quell’amico fastidioso che vi corregge mentre parlate? O che vi fa notare che il quadro sulla vostra parete non è perfettamente centrato? Ecco, io sono quel tipo di persona. Da quando nel 2020 ho iniziato a lavorare nel mondo editoriale, queste caratteristiche “fastidiose” sono diventate un mio punto di forza! L’attenzione alla grammatica mi serve nella redazione dei testi e nella correzione bozze, l’occhio preciso è un radar per i problemi di grafica e impaginazione, molto utile anche per la cura delle riviste.",
      img: "assets/img/teamroberta.webp"
    },
    /*{
      nome: "Chiara Marino",
      descrizione:
        "Leggo tanti libri e ne parlo sempre troppo. Ne ho fatto un lavoro per avere una giustificazione.Avrei voluto essere, in ordine cronologico: una giornalista, una linguista, Dostoevskij. Quando ho capito che un nesso esisteva, ed erano le parole, ho trovato la mia strada. Da allora edito, correggo bozze e scrivo, e collaboro con alcune riviste letterarie.Ogni tanto organizzo gruppi di lettura; nel tempo che rimane, osservo gatti e faccio collage.",
      img: "assets/img/teamchiara.webp"
    },*/
    {
      nome: "Ornella Privitera",
      descrizione:
        "Generazione millennial, il mio percorso per arrivare qui è stato disseminato di strade contorte, a volte sbagliate. Finché un volantino lungo una viuzza senza sbocco mi ha portato a un master in editoria (e poi uno in traduzione) e a lavorare dal 2018 come redattrice editoriale. Credo molto nel rispetto: delle persone, delle idee, della fatica, dei testi, delle scadenze; nell’aggiornamento continuo, professionale e umano; e nella necessità di essere seri ma di non prendersi troppo sul serio.",
      img: "assets/img/teamornella.webp"
    },
    {
      nome: "Sofia Sercia",
      descrizione:
        "Le storie mi hanno sempre affascinata: ho iniziato presto a riempire quaderni di racconti inventati e oggi aiuto le parole a prendere forma concreta. Dopo una laurea in Lingue e letterature straniere e un master in editoria mi sono dedicata alla correzione di bozze, all’impaginazione, alla scrittura e alla recensione di opere letterarie online. Amo la creatività che nasce dal lavorare con le parole e credo che ogni testo abbia dentro una storia da far emergere.",
      img: "assets/img/teamsofia.webp"
    }
  ];

 function makeCard(m) {
  const card = document.createElement("article");
  card.className = "card";
  card.innerHTML = `
    <div class="card__media">
      <img
        src="${m.img}"
        alt="${m.nome}"
        loading="lazy"
        decoding="async"
        width="800"
        height="600"
      >
    </div>
    <div class="card__body">
      <h3 class="card__name">${m.nome}</h3>
      <p class="card__desc">${m.descrizione}</p>
    </div>
    <div class="card__actions">
      <button class="btn btn--ghost btn-more" type="button" aria-expanded="false">Leggi di più</button>
      <button class="btn btn--primary btn-close" type="button">Chiudi</button>
    </div>
  `;
  return card;
}


  function initTeamGrid() {
    const grid = document.getElementById("team-grid");
    if (!grid) return;

    MEMBERS.forEach((m) => grid.appendChild(makeCard(m)));

    grid.addEventListener("click", (e) => {
      const more = e.target.closest(".btn-more");
      const close = e.target.closest(".btn-close");
      if (!more && !close) return;

      const card = e.target.closest(".card");
      if (!card) return;

      if (more) {
        grid.querySelectorAll(".card.open").forEach((c) => {
          if (c !== card) {
            c.classList.remove("open");
            const bMore = c.querySelector(".btn-more");
            const bClose = c.querySelector(".btn-close");
            if (bMore) {
              bMore.style.display = "inline-block";
              bMore.setAttribute("aria-expanded", "false");
            }
            if (bClose) bClose.style.display = "none";
          }
        });

        card.classList.add("open");
        const btnMore = card.querySelector(".btn-more");
        const btnClose = card.querySelector(".btn-close");
        if (btnMore) {
          btnMore.style.display = "none";
          btnMore.setAttribute("aria-expanded", "true");
        }
        if (btnClose) btnClose.style.display = "inline-block";
      }

      if (close) {
        card.classList.remove("open");
        const btnMore = card.querySelector(".btn-more");
        const btnClose = card.querySelector(".btn-close");
        if (btnMore) {
          btnMore.style.display = "inline-block";
          btnMore.setAttribute("aria-expanded", "false");
        }
        if (btnClose) btnClose.style.display = "none";
        card.scrollIntoView({ behavior: "smooth", block: "nearest" });
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTeamGrid);
  } else {
    initTeamGrid();
  }
})();
