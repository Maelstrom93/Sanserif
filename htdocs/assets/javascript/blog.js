// assets/javascript/blog.js
(() => {
  // ===== util =====
  const API_BASE = document.documentElement.dataset.apiBase || "api/";
  const filters = { anni: new Set(), categorie: new Set(), q: "" };
  let ALL = [];
  let VIRT = []; // vista corrente filtrata

  const $  = (sel, scope = document) => scope.querySelector(sel);
  const $$ = (sel, scope = document) => Array.from(scope.querySelectorAll(sel));
  const api  = (n) => `${API_BASE}${n}`;
  const norm = (s) => String(s ?? "").trim();


  document.addEventListener("DOMContentLoaded", init);

  async function getJSON(url, cache = "default") {
    const r = await fetch(url, { cache });
    if (!r.ok) throw new Error(r.status);
    return r.json();
  }

  async function init() {
    bindPopovers();
    bindSearch();
    const resetAllBtn = $("#resetAll");
    if (resetAllBtn) resetAllBtn.addEventListener("click", resetAll);

    try {
      const [articoli, anni, categorie] = await Promise.all([
        getJSON(api("articoli.php"), "no-store"),
        getJSON(api("anni.php"), "no-store"),
        getJSON(api("categories.php"), "no-store"),
      ]);

      ALL = (Array.isArray(articoli) ? articoli : []).map(a => ({
        ...a,
        // campi attesi dall'API:
        // title, date (YYYY-MM o YYYY-MM-DD), category, excerpt, img, link
        _t: norm(a.title).toLowerCase(),
        _c: norm(a.category).toLowerCase(),
        _x: norm(a.excerpt).toLowerCase(),
        year: norm(a.date).slice(0, 4),
      }));

      buildChecklist(
        "#list-anni",
        (Array.isArray(anni) ? anni.map(String) : [])
          .sort((a, b) => a.localeCompare(b, "it", { numeric: true })),
        "anni"
      );

      buildChecklist(
        "#list-categorie",
        (Array.isArray(categorie) ? categorie : []),
        "categorie"
      );

      applyAndRender();
    } catch (e) {
      console.error(e);
      const grid = $("#blogGrid");
      if (grid) grid.innerHTML = '<div style="grid-column:1/-1;text-align:center">Errore nel caricamento.</div>';
    }
  }

  /* ====== UI: popover/filtri ====== */
  function bindPopovers() {
    const pairs = [
      ["btn-anni", "pop-anni"],
      ["btn-categorie", "pop-categorie"],
    ];
    pairs.forEach(([btnId, popId]) => {
      const btn = $("#" + btnId), pop = $("#" + popId);
      const closer = $("#close-" + btnId.split("btn-")[1]);
      if (!btn || !pop) return;

      btn.addEventListener("click", () => {
        const open = pop.classList.toggle("open");
        btn.setAttribute("aria-expanded", open ? "true" : "false");
        positionPopover(btn, pop);
        if (open) trapWithin(pop);
      });

      (closer || pop).addEventListener("click", (e) => {
        if (e.target === closer) closePopover(btn, pop);
      });

      document.addEventListener("click", (e) => {
        if (!pop.contains(e.target) && !btn.contains(e.target)) closePopover(btn, pop);
      });
    });

     const resetFilterKey = (k) => {
      if (!k || !filters[k]) return;
      filters[k].clear();

      // uncheck (senza callback annidate)
      for (const c of $$("#list-" + k + " input[type=checkbox]")) c.checked = false;

      updateCounts();
      updateChips();
      applyAndRender();
    };

    $$(".link-reset").forEach(el => {
      el.addEventListener("click", () => resetFilterKey(el.dataset.reset));
    });


      globalThis.addEventListener("resize", () => {
      $$(".popover.open").forEach(pop => {
        const btn = document.querySelector(`[aria-controls="${pop.id}"]`) || pop.previousElementSibling;
        if (btn) positionPopover(btn, pop);
      });
    });
  }

  function closePopover(btn, pop) {
    if (pop) pop.classList.remove("open");
    if (btn) btn.setAttribute("aria-expanded", "false");
  }

  function positionPopover(btn, pop) {
    const r = btn.getBoundingClientRect();
    pop.style.left = r.left + "px";
       pop.style.top  = (r.bottom + globalThis.scrollY) + "px";
  }

  function trapWithin(scope) {
    const f = Array.from(scope.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )).filter(el => !el.disabled && el.offsetParent !== null);
  if (!f.length) {
  return;
}

const first = f[0];
const last = f[f.length - 1];
first.focus();

    function onKey(e) {
      if (e.key === "Escape") {
        scope.classList.remove("open");
        document.removeEventListener("keydown", onKey);
      }
      if (e.key !== "Tab") return;
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault(); last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus();
      }
    }
    document.addEventListener("keydown", onKey);
  }

  function bindSearch() {
    const input = $("#q");
    const debounced = debounce(() => {
      filters.q = input.value.trim().toLowerCase();
      applyAndRender();
    }, 160);
    if (input) input.addEventListener("input", debounced);
  }

  function debounce(fn, ms) {
    let t;
    return (...a) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...a), ms);
    };
  }

  function buildChecklist(containerSel, items, key) {
    const box = $(containerSel);
    if (!box) return;
    const frag = document.createDocumentFragment();

    // cerca locale dentro il popover
    const searchWrap = document.createElement("div");
    searchWrap.style.padding = ".25rem .25rem .4rem";
    searchWrap.innerHTML =
      `<input type="search" placeholder="Filtra ${key}…" ` +
      `style="width:100%;padding:.5rem .6rem;border:1px solid rgba(0,76,96,.25);border-radius:8px">`;
    const localSearch = searchWrap.firstElementChild;
    frag.appendChild(searchWrap);

    const list = document.createElement("div");
    (items || []).filter(Boolean).forEach(val => {
      const row = document.createElement("label");
      row.className = "p-item";
      row.innerHTML =
        `<input type="checkbox" value="${escapeHtml(val)}" aria-label="${escapeHtml(val)}" ` +
        `style="margin-top:.25rem"> <span>${escapeHtml(val)}</span>`;
      const cb = row.querySelector("input");
      cb.addEventListener("change", () => {
        if (cb.checked) filters[key].add(val);
        else filters[key].delete(val);
        updateCounts();
        updateChips();
        applyAndRender();
      });
      list.appendChild(row);
    });
    frag.appendChild(list);
    box.replaceChildren(frag);

    localSearch.addEventListener("input", () => {
      const q = localSearch.value.toLowerCase();
      $$(".p-item", box).forEach(el => {
        const txt = el.textContent.toLowerCase();
        el.style.display = txt.includes(q) ? "" : "none";
      });
    });
  }

  function escapeHtml(s) {
    return String(s)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  /* ====== FILTRAGGIO + RENDER ====== */
  function applyAndRender() {
    const grid = $("#blogGrid");
    if (grid) grid.classList.add("is-updating");

    const byFacet = ALL.filter(a => {
      const okAnno = filters.anni.size ? filters.anni.has(a.year) : true;
      const okCat  = filters.categorie.size ? filters.categorie.has(a.category || "") : true;
      return okAnno && okCat;
    });

    const q = filters.q;
    VIRT = q
      ? byFacet.filter(a => a._t.includes(q) || a._c.includes(q) || a._x.includes(q))
      : byFacet;

    updateResultsBar();
    updateChips();
    renderGrid();

    if (grid) setTimeout(() => { grid.classList.remove("is-updating"); }, 140);
  }

  function revealCards(grid) {
    for (const el of $$(".pcard:not(.is-visible)", grid)) el.classList.add("is-visible");
  }

  function renderGrid() {

    const grid = $("#blogGrid");
    if (!grid) return;
    grid.innerHTML = "";

    if (!VIRT.length) {
      grid.innerHTML =
        '<div style="grid-column:1/-1;text-align:center;font-size:1.05rem">Nessun risultato</div>';
      return;
    }

    const frag = document.createDocumentFragment();
    let i = 0;
    const BATCH = 24;

     const renderBatch = () => {
      for (let c = 0; c < BATCH && i < VIRT.length; c++, i++) {
        const a = VIRT[i];
        const card = document.createElement("div");
        card.className = "pcard"; // stessa card del portfolio
        card.style.setProperty('--i', i); // per animazioni/stagger
        card.innerHTML = `
          <img src="${a.img}" alt="${escapeHtml(a.title || 'Immagine articolo')}" loading="lazy" decoding="async">
          <div class="overlayp">Leggi</div>
          <div class="info">
            <h3>${escapeHtml(a.title || 'Senza titolo')}</h3>
            <div class="meta">${escapeHtml(formatDate(a.date))}${a.category ? " · " + escapeHtml(a.category) : ""}</div>
          </div>
        `;
        card.tabIndex = 0;
        card.setAttribute("role", "button");
        card.addEventListener("click", () => mostraDettagli(a, card));
        card.addEventListener("keydown", (e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            mostraDettagli(a, card);
          }
        });
        frag.appendChild(card);
      }

      grid.appendChild(frag);

      requestAnimationFrame(() => revealCards(grid));
      if (i < VIRT.length) requestAnimationFrame(renderBatch);
    };

    renderBatch();

  }

  function formatDate(dateStr) {
    const s = String(dateStr || "");
    const y = s.slice(0, 4), m = s.slice(5, 7);
    const months = [
      "Gennaio","Febbraio","Marzo","Aprile","Maggio","Giugno",
      "Luglio","Agosto","Settembre","Ottobre","Novembre","Dicembre"
    ];
    if (y && m && Number(m) >= 1 && Number(m) <= 12) return `${months[Number(m) - 1]} ${y}`;
    if (y) return y;
    return "";
  }

  function updateCounts() {
    const setTxt = (sel, n) => { const el = $(sel); if (el) el.textContent = String(n); };
    setTxt("#btn-anni .count",      filters.anni.size);
    setTxt("#btn-categorie .count", filters.categorie.size);
  }

  function updateResultsBar() {
    const n = VIRT.length;
    const label = (n === 1) ? "risultato" : "risultati";
    const el = $("#resultsCount");
    if (el) el.textContent = `${n} ${label}`;
  }

  function resetAll() {
    filters.anni.clear();
    filters.categorie.clear();
    filters.q = "";
    const q = $("#q"); if (q) q.value = "";
    $$(".p-item input[type=checkbox]").forEach(cb => cb.checked = false);
    updateCounts();
    updateChips();
    applyAndRender();
  }

  function updateChips() {
    const wrap = $("#activeChips");
    if (!wrap) return;
    wrap.innerHTML = "";

    const addChip = (label, group, value) => {
      const chip = document.createElement("span");
      chip.className = "chip";
      chip.innerHTML =
        `${escapeHtml(label)} <button aria-label="Rimuovi filtro"><i class="fa-solid fa-xmark"></i></button>`;
      chip.querySelector("button").addEventListener("click", () => {
        filters[group].delete(value);
        const cb = document.querySelector(
          `#list-${group} input[type=checkbox][value="${CSS.escape(value)}"]`
        );
        if (cb) cb.checked = false;
        updateCounts();
        updateChips();
        applyAndRender();
      });
      wrap.appendChild(chip);
    };

    filters.anni.forEach(v => addChip(v, "anni", v));
    filters.categorie.forEach(v => addChip(v, "categorie", v));

    if (filters.q) {
      const chip = document.createElement("span");
      chip.className = "chip";
      chip.innerHTML =
        `Cerca: ${escapeHtml(filters.q)} ` +
        `<button aria-label="Pulisci ricerca"><i class="fa-solid fa-xmark"></i></button>`;
      chip.querySelector("button").addEventListener("click", () => {
        const q = $("#q"); if (q) q.value = "";
        filters.q = "";
        applyAndRender();
        chip.remove();
      });
      wrap.appendChild(chip);
    }
  }

  // ===== Modale =====
  function mostraDettagli(article, triggerEl) {
    const modal = $("#modal");
    const body = $("#modal-body");
    if (!modal || !body) return;
    globalThis._lastFocusedEl = triggerEl || document.activeElement;
    body.innerHTML = `
      <h2 id="modal-title">${escapeHtml(article.title || "-")}</h2>
      <p class="meta">${escapeHtml(formatDate(article.date))}${article.category ? " · " + escapeHtml(article.category) : ""}</p>
      ${article.img
        ? `<img src="${article.img}" alt="${escapeHtml(article.title || '')}" ` +
          `style="width:auto; max-height:250px; object-fit:cover; margin:0 0 1rem 0;" ` +
          `loading="lazy" decoding="async" />`
        : ""
      }
      <p>${escapeHtml(article.excerpt || "")}</p>
      ${article.link
        ? `<p><a class="book-link" href="${article.link}" target="_blank" rel="noopener">` +
          `Apri articolo <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>` +
          `<span class="sr-only">(apre in nuova scheda)</span></a></p>`
        : ""
      }
    `;
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
  }

  function chiudiModal() {
    const modal = $("#modal");
    if (!modal) return;
    modal.classList.remove("show");
    document.body.style.overflow = "";
    if (globalThis._lastFocusedEl && typeof globalThis._lastFocusedEl.focus === "function") {
      globalThis._lastFocusedEl.focus();
    }

  }

  // Bind chiusura modale (compat CSP)
  (function bindModalClose() {
    const modal = document.getElementById("modal");
    if (!modal) return;

    const xBtn = modal.querySelector(".close");
    if (xBtn) xBtn.addEventListener("click", chiudiModal);

    modal.querySelectorAll(".js-close-modal").forEach(btn => {
      btn.addEventListener("click", chiudiModal);
    });

    modal.addEventListener("click", (e) => {
      if (e.target === modal) chiudiModal();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("show")) {
        chiudiModal();
      }
    });
  })();

  // Esponi se serve altrove
   globalThis.chiudiModal = chiudiModal;
})();
