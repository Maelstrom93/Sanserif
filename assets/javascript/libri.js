// assets/javascript/libri.js
let BATCH_SIZE = 10;
let renderedCount = 0;

(() => {
  // ===== util =====
  const API_BASE = document.documentElement.dataset.apiBase || "api/";
  let MODE = "evidenza";
  const filters = { anni: new Set(), editori: new Set(), lavori: new Set(), q: "" };
  let ALL = [];
  let VIRT = []; // cache filtrata corrente (per render veloce)

  const $  = (sel, scope=document) => scope.querySelector(sel);
  const $$ = (sel, scope=document) => Array.from(scope.querySelectorAll(sel));
  const api  = (n) => `${API_BASE}${n}`;
  const norm = (s) => String(s ?? "").trim();

  document.addEventListener("DOMContentLoaded", init);

  async function getJSON(url, cache="default"){
    const r = await fetch(url, { cache });
    if (!r.ok) throw new Error(r.status);
    return r.json();
  }

  async function init(){
    bindSeg();
    bindPopovers();
    bindSearch();
    const resetAllBtn = $("#resetAll");
    if (resetAllBtn) resetAllBtn.addEventListener("click", resetAll);

    try{
      // carico tutto in parallelo: libri, anni, categorie (lavori), editori
      const [libri, anni, lavori, editori] = await Promise.all([
        getJSON(api("libri.php"), "no-store"),
        getJSON(api("libri_anni.php"), "no-store"),
        getJSON(api("libri_categorie.php"), "no-store"),
        safeCaseEditrici()
      ]);

   ALL = (Array.isArray(libri) ? libri : []).map(l => ({
  ...l,
  _t: norm(l.titolo).toLowerCase(),
  _e: norm(l.casa_editrice).toLowerCase(),
  _s: norm(l.sinossi).toLowerCase(),
  anno: norm(l.data_pubblicazione).slice(0,4),
  categorie: Array.isArray(l.categorie) ? l.categorie : [],
  cover_json: l.cover_json || null // <— NEW: passiamo giù le varianti
}));


      buildChecklist(
        "#list-anni",
        (Array.isArray(anni) ? anni : [])
          .map(String)
          .sort((a,b)=>a.localeCompare(b,"it",{numeric:true})),
        "anni"
      );
      buildChecklist("#list-editori", (Array.isArray(editori)?editori:[]), "editori");
      buildChecklist("#list-lavori", (Array.isArray(lavori)?lavori:[]), "lavori");

      applyAndRender();
    }catch(e){
      console.error(e);
      const grid = $("#projectGrid");
      if (grid) grid.innerHTML =
        '<div style="grid-column:1/-1;text-align:center">Errore nel caricamento.</div>';
    }
  }

  async function safeCaseEditrici(){
    try{
      const data = await getJSON(api("libri_case_editrici.php"), "force-cache");
      return Array.isArray(data) ? data : [];
    }catch(e){
      console.warn("libri_case_editrici non disponibile, uso elenco vuoto", e);
      return [];
    }
  }

  // normalizza path in /backend/uploads/… ecc.
  const normImgPath = (p) => {
    if (!p) return "";
    p = String(p).trim();
    if (/^https?:\/\//i.test(p) || p.startsWith("//")) return p;
    if (p.startsWith("/backend/uploads/")) return p;
    if (p.startsWith("backend/uploads/")) return "/" + p;
    if (p.startsWith("uploads/")) return "/backend/" + p;
    if (!p.includes("/")) return "/backend/uploads/" + p;
    if (p[0] !== "/") return "/" + p;
    return p;
  };

  // Costruisce attributi immagine a partire da cover_json (se presente)
  function buildCoverAttrs(l) {
    // Fallback legacy (solo l.img)
    const legacy = {
      src: normImgPath(l.img),
      srcsetWebp: "",
      srcsetJpg: "",
      sizes: "(min-width: 1200px) 240px, (min-width: 768px) 33vw, 50vw",
      width: 480,
      height: 700,
      blur: ""
    };

    const cj = l.cover_json; // aspettato dall’API (vedi patch api più sotto)
    if (!cj || typeof cj !== "object") return legacy;

    const w480  = cj.webp?.w480  || cj.jpeg?.w480  || l.img;
    const w768  = cj.webp?.w768  || cj.jpeg?.w768  || w480;
    const w1200 = cj.webp?.w1200 || cj.jpeg?.w1200 || w768;

    const srcsetWebp = [
      cj.webp?.w480  ? `${normImgPath(cj.webp.w480)} 480w`   : "",
      cj.webp?.w768  ? `${normImgPath(cj.webp.w768)} 768w`   : "",
      cj.webp?.w1200 ? `${normImgPath(cj.webp.w1200)} 1200w` : ""
    ].filter(Boolean).join(", ");

    const srcsetJpg = [
      cj.jpeg?.w480  ? `${normImgPath(cj.jpeg.w480)} 480w`   : "",
      cj.jpeg?.w768  ? `${normImgPath(cj.jpeg.w768)} 768w`   : "",
      cj.jpeg?.w1200 ? `${normImgPath(cj.jpeg.w1200)} 1200w` : ""
    ].filter(Boolean).join(", ");

    // src “di partenza”: l’opzione più leggera disponibile
    const src = normImgPath(w480);
    const blur = cj.blur ? `data:image/jpeg;base64,${cj.blur}` : "";

    // Proviamo a inferire una ratio tipica (per prevenire CLS).
    // Se vuoi, salva width/height nel JSON. Qui uso un default sensato.
    return {
      src,
      srcsetWebp,
      srcsetJpg,
      sizes: "(min-width: 1200px) 240px, (min-width: 768px) 33vw, 50vw",
      width: 480,
      height: 700,
      blur
    };
  }


  /* ====== UI bindings ====== */
  function bindSeg(){
    $$("#seg-evid, #seg-all").forEach(r=>{
      r.addEventListener("change", ()=>{
        MODE = r.value;
        applyAndRender();
      });
    });
  }

  function bindPopovers(){
    const pairs = [
      ["btn-anni","pop-anni"],
      ["btn-editori","pop-editori"],
      ["btn-lavori","pop-lavori"],
    ];
    pairs.forEach(([btnId,popId])=>{
      const btn = $("#"+btnId), pop = $("#"+popId);
      const closer = $("#close-"+btnId.split("btn-")[1]);
      if (!btn || !pop) return;

      btn.addEventListener("click", ()=>{
        const open = pop.classList.toggle("open");
        btn.setAttribute("aria-expanded", open ? "true" : "false");
        positionPopover(btn,pop);
        if (open) trapWithin(pop);
      });
      (closer || pop).addEventListener("click", (e)=>{
        if (e.target === closer){ closePopover(btn,pop); }
      });
      document.addEventListener("click", (e)=>{
        if (!pop.contains(e.target) && !btn.contains(e.target)) closePopover(btn,pop);
      });
    });

    $$(".link-reset").forEach(el=>{
      el.addEventListener("click", ()=>{
        const k = el.dataset.reset;
        filters[k].clear();
        // uncheck
        $$("#list-"+k+" input[type=checkbox]").forEach(c=>c.checked=false);
        updateCounts();
        applyAndRender();
      });
    });

    window.addEventListener("resize", ()=>{
      $$(".popover.open").forEach(pop=>{
        const btn = pop.previousElementSibling;
        if (btn) positionPopover(btn, pop);
      });
    });
  }

  function closePopover(btn,pop){
    if (pop){ pop.classList.remove("open"); }
    if (btn){ btn.setAttribute("aria-expanded","false"); }
  }

  function positionPopover(btn,pop){
    const r = btn.getBoundingClientRect();
    pop.style.left = r.left + "px";
    pop.style.top  = (r.bottom + window.scrollY) + "px";
  }

  function trapWithin(scope){
    const f = Array.from(
      scope.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
    ).filter(el=>!el.disabled && el.offsetParent!==null);
    if(!f.length) return;
    const first=f[0], last=f[f.length-1];
    first.focus();
    function onKey(e){
      if(e.key==="Escape"){
        scope.classList.remove("open");
        document.removeEventListener("keydown",onKey);
      }
      if(e.key!=="Tab") return;
      if(e.shiftKey && document.activeElement===first){
        e.preventDefault(); last.focus();
      } else if(!e.shiftKey && document.activeElement===last){
        e.preventDefault(); first.focus();
      }
    }
    document.addEventListener("keydown",onKey);
  }

  function bindSearch(){
    const input = $("#q");
    const debounced = debounce(()=>{
      filters.q = input.value.trim().toLowerCase();
      applyAndRender();
    }, 160);
    if (input) input.addEventListener("input", debounced);
  }
  function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }

  function buildChecklist(containerSel, items, key){
    const box = $(containerSel);
    if(!box) return;
    const frag = document.createDocumentFragment();

    // campo cerca locale dentro al popover per liste lunghe
    const searchWrap = document.createElement("div");
    searchWrap.style.padding = ".25rem .25rem .4rem";
    searchWrap.innerHTML =
      `<input type="search" placeholder="Filtra ${key}…" ` +
      `style="width:100%;padding:.5rem .6rem;border:1px solid rgba(0,76,96,.25);border-radius:8px">`;
    const localSearch = searchWrap.firstElementChild;
    frag.appendChild(searchWrap);

    const list = document.createElement("div");
    items.filter(Boolean).forEach(val=>{
      const row = document.createElement("label");
      row.className = "p-item";
      row.innerHTML =
        `<input type="checkbox" value="${escapeHtml(val)}" aria-label="${escapeHtml(val)}" ` +
        `style="margin-top:.25rem"> <span>${escapeHtml(val)}</span>`;
      const cb = row.querySelector("input");
      cb.addEventListener("change", ()=>{
        if(cb.checked) filters[key].add(val); else filters[key].delete(val);
        updateCounts(); updateChips(); applyAndRender();
      });
      list.appendChild(row);
    });
    frag.appendChild(list);
    box.replaceChildren(frag);

    localSearch.addEventListener("input", ()=>{
      const q = localSearch.value.toLowerCase();
      $$(".p-item", box).forEach(el=>{
        const txt = el.textContent.toLowerCase();
        el.style.display = txt.includes(q) ? "" : "none";
      });
    });
  }

  function escapeHtml(s){
    return String(s)
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;");
  }

  /* ====== FILTRAGGIO + RENDER ====== */
  function applyAndRender(){
    const grid = $("#projectGrid");
    if (grid) grid.classList.add("is-updating");

    const byMode = (MODE==="evidenza")
      ? ALL.filter(l => (l.categorie||[])
          .map(c=>String(c).toLowerCase().trim())
          .includes("in evidenza"))
      : ALL;

    const byFacet = byMode.filter(l=>{
      const okAnno = filters.anni.size ? filters.anni.has(l.anno) : true;
      const okEd   = filters.editori.size ? filters.editori.has(l.casa_editrice||"") : true;
      const okLav  = filters.lavori.size ? (l.categorie||[]).some(c=>filters.lavori.has(c)) : true;
      return okAnno && okEd && okLav;
    });

    const q = filters.q;
    VIRT = q
      ? byFacet.filter(l => l._t.includes(q) || l._e.includes(q) || l._s.includes(q))
      : byFacet;

    updateResultsBar();
    renderGrid(true);

    if (grid) setTimeout(()=>{ grid.classList.remove("is-updating"); }, 140);
  }

  function renderGrid(initial = false){
  const grid = $("#projectGrid");
  if(!grid) return;
  if(initial) renderedCount = 0;

  // se non ci sono risultati
  if(!VIRT.length){
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;font-size:1.05rem">Nessun risultato</div>';
    $("#showMoreWrap")?.remove();
    return;
  }

  // se è un render iniziale
  if(initial) grid.innerHTML = "";

  const slice = VIRT.slice(renderedCount, renderedCount + BATCH_SIZE);
  const frag = document.createDocumentFragment();

  slice.forEach((l, i) => {
    const card = document.createElement("div");
    card.className = "pcard";
    card.style.setProperty("--i", renderedCount + i);
       const c = buildCoverAttrs(l);
    // blur inline come background + classe che togliamo onload
    const styleBlur = c.blur ? `style="background-image:url('${c.blur}'); background-size:cover; background-position:center;"` : "";

    card.innerHTML = `
      <picture class="pcard-media" ${styleBlur}>
        ${c.srcsetWebp ? `<source type="image/webp" srcset="${c.srcsetWebp}" sizes="${c.sizes}">` : ""}
        ${c.srcsetJpg  ? `<source type="image/jpeg" srcset="${c.srcsetJpg}" sizes="${c.sizes}">` : ""}
        <img
          src="${c.src}"
          alt="${escapeHtml(l.titolo || 'Copertina')}"
          loading="lazy"
          decoding="async"
          width="${c.width}"
          height="${c.height}"
          class="pcard-img"
        >
      </picture>
      <div class="overlayp">Dettagli</div>
    `;

    card.tabIndex = 0;
    card.setAttribute("role","button");
    card.addEventListener("click", ()=> mostraDettagli(l, card));
    card.addEventListener("keydown", e => {
      if(e.key==="Enter"||e.key===" "){ e.preventDefault(); mostraDettagli(l, card); }
    });
    frag.appendChild(card);
  });

  grid.appendChild(frag);
 $$(".pcard .pcard-img", grid).forEach(img => {
    if (img.complete) img.classList.add("is-loaded");
    else img.addEventListener("load", () => img.classList.add("is-loaded"), { once: true });
  });
  requestAnimationFrame(()=>{
    $$(".pcard:not(.is-visible)", grid)
      .forEach(el => el.classList.add("is-visible"));
  });

  renderedCount += slice.length;

  // Mostra/nascondi il bottone
  manageShowMoreButton();
}

function manageShowMoreButton(){
  let wrap = $("#showMoreWrap");
  if (!wrap){
    wrap = document.createElement("div");
    wrap.id = "showMoreWrap";
    wrap.style.textAlign = "center";
    wrap.style.margin = "2rem 0";
    const btn = document.createElement("button");
    btn.className = "btn btn--primary";
    btn.textContent = "Mostra altri";
    btn.addEventListener("click", () => renderGrid(false));
    wrap.appendChild(btn);
    document.querySelector(".portfolio-wrapper")?.appendChild(wrap);
  }

  // se abbiamo mostrato tutto, rimuovilo
  if (renderedCount >= VIRT.length){
    wrap.remove();
  }
}


  function updateCounts(){
    const setTxt = (sel, n) => { const el = $(sel); if(el) el.textContent = String(n); };
    setTxt("#btn-anni .count",    filters.anni.size);
    setTxt("#btn-editori .count", filters.editori.size);
    setTxt("#btn-lavori .count",  filters.lavori.size);
  }

  function updateResultsBar(){
    const n = VIRT.length;
    const seg = (MODE==="evidenza") ? "In evidenza" : "Tutti";
    const label = (n === 1) ? "risultato" : "risultati";
    const el = $("#resultsCount");
    if (el) el.textContent = `${n} ${label} · Vista: ${seg}`;
  }

  function resetAll(){
    filters.anni.clear();
    filters.editori.clear();
    filters.lavori.clear();
    filters.q = "";
    const q = $("#q"); if (q) q.value = "";
    $$(".p-item input[type=checkbox]").forEach(cb=>cb.checked=false);
    updateCounts();
    updateChips();
    applyAndRender();
  }

  function updateChips(){
    const wrap = $("#activeChips");
    if(!wrap) return;
    wrap.innerHTML = "";

    const addChip = (label, group, value)=>{
      const chip = document.createElement("span");
      chip.className = "chip";
      chip.innerHTML =
        `${escapeHtml(label)} <button aria-label="Rimuovi filtro">` +
        `<i class="fa-solid fa-xmark"></i></button>`;
      chip.querySelector("button").addEventListener("click", ()=>{
        filters[group].delete(value);
        const cb = document.querySelector(
          `#list-${group} input[type=checkbox][value="${CSS.escape(value)}"]`
        );
        if(cb) cb.checked = false;
        updateCounts();
        updateChips();
        applyAndRender();
      });
      wrap.appendChild(chip);
    };

    filters.anni.forEach(v=> addChip(v,"anni",v));
    filters.editori.forEach(v=> addChip(v,"editori",v));
    filters.lavori.forEach(v=> addChip(v,"lavori",v));

    if(filters.q){
      const chip = document.createElement("span");
      chip.className = "chip";
      chip.innerHTML =
        `Cerca: ${escapeHtml(filters.q)} ` +
        `<button aria-label="Pulisci ricerca"><i class="fa-solid fa-xmark"></i></button>`;
      chip.querySelector("button").addEventListener("click", ()=>{
        const q = $("#q"); if (q) q.value = "";
        filters.q = "";
        applyAndRender();
        chip.remove();
      });
      wrap.appendChild(chip);
    }
  }

  // ===== Modale =====
  function mostraDettagli(libro, triggerEl){
    const modal = $("#modal");
    const body  = $("#modal-body");
    if(!modal || !body) return;
    window._lastFocusedEl = triggerEl || document.activeElement;
      const c = buildCoverAttrs(libro);
    body.innerHTML = `
  <h2 id="modal-title">${escapeHtml(libro.titolo || "-")}</h2>
  <picture>
    ${c.srcsetWebp ? `<source type="image/webp" srcset="${c.srcsetWebp}" sizes="(min-width: 768px) 400px, 80vw">` : ""}
    ${c.srcsetJpg  ? `<source type="image/jpeg" srcset="${c.srcsetJpg}" sizes="(min-width: 768px) 400px, 80vw">` : ""}
    <img
      src="${c.src}"
      alt="${escapeHtml(libro.titolo || '')}"
      loading="lazy"
      decoding="async"
      width="${c.width}"
      height="${c.height}"
      style="width:auto; max-height:250px; object-fit:cover; margin-bottom:1rem;"
      class="pcard-img"
    />
  </picture>
  <p><strong>Anno:</strong> ${escapeHtml(libro.anno || "-")}</p>
  <p><strong>Casa editrice:</strong> ${escapeHtml(libro.casa_editrice || "-")}</p>
  <p><strong>Servizi:</strong> ${
    (Array.isArray(libro.categorie) && libro.categorie.length)
      ? libro.categorie.map(escapeHtml).join(", ")
      : "-"
  }</p>
  <p><strong>Sinossi:</strong> ${escapeHtml(libro.sinossi || "-")}</p>
  ${ libro.link ? `<p><a class="book-link" href="${libro.link}" target="_blank" rel="noopener">
      Vai al libro <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
      <span class="sr-only">(apre in nuova scheda)</span>
    </a></p>` : "" }
`;


    modal.classList.add("show");
    document.body.style.overflow="hidden";
  }

  function chiudiModal(){
    const modal = $("#modal");
    if(!modal) return;
    modal.classList.remove("show");
    document.body.style.overflow="";
    if(window._lastFocusedEl && typeof window._lastFocusedEl.focus==="function"){
      window._lastFocusedEl.focus();
    }
  }

  // Bind chiusura modale (compat CSP)
  (function bindModalClose(){
    const modal = document.getElementById('modal');
    if(!modal) return;

    const xBtn = modal.querySelector('.close');
    if (xBtn) xBtn.addEventListener('click', chiudiModal);

    modal.querySelectorAll('.js-close-modal').forEach(btn=>{
      btn.addEventListener('click', chiudiModal);
    });

    modal.addEventListener('click', (e)=>{
      if (e.target === modal) chiudiModal();
    });

    document.addEventListener('keydown', (e)=>{
      if (e.key === 'Escape' && modal.classList.contains('show')) {
        chiudiModal();
      }
    });
  })();

  // Esponi solo se serve altrove
  window.chiudiModal = chiudiModal;
})();
