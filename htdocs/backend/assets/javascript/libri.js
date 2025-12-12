/* =========================================================================
 *  LIBRI (Portfolio) — JS
 *  - Sinossi: Mostra + / – con transizione
 *  - Archivio: normalizzazione + “Mostra altri”
 *  - Modale Modifica: open/close + fetch/popola + submit
 *  - Categorie: chips dinamiche + nuova categoria (UI)
 *  - Copertina: anteprima + rimozione
 *  - Casa editrice: select con “Altro…”
 * ========================================================================= */
const LIBRI = window.__LIBRI || {};
const CAN_EDIT = !!LIBRI.canEdit;
const CAN_DELETE = !!LIBRI.canDelete; // se serve
const delBtn = document.getElementById('btnDeleteLibro'); // se lo aggiungi in HTML
if (delBtn) delBtn.style.display = (CAN_DELETE ? '' : 'none');

/* ===== Sinossi: Mostra + / – con transizione ===== */
function initSinossiToggles(root = document) {
  root.querySelectorAll('[data-toggle-sinossi]').forEach((btn) => {
    const card = btn.closest('.book-card');
    if (!card) return;
    const sn = card.querySelector('.sinossi');
    if (!sn) { btn.remove(); return; }

    const fullText = sn.getAttribute('data-full') || sn.textContent || '';
    if (fullText.trim().length < 180) { btn.remove(); return; }

    const cs = getComputedStyle(sn);
    const lineH = parseFloat(cs.lineHeight) || 18;
    const collapsedH = lineH * 3;

    sn.style.maxHeight = collapsedH + 'px';
    btn.textContent = 'Mostra +';

    btn.addEventListener('click', () => {
      const isOpen = sn.classList.contains('is-expanded');
      if (isOpen) {
        sn.style.maxHeight = sn.scrollHeight + 'px';
        requestAnimationFrame(() => {
          sn.classList.remove('is-expanded');
          sn.style.maxHeight = collapsedH + 'px';
        });
        btn.textContent = 'Mostra +';
      } else {
        sn.classList.add('is-expanded');
        sn.style.maxHeight = sn.scrollHeight + 'px';
        btn.textContent = 'Mostra –';
      }
    });

    sn.addEventListener('transitionend', (ev) => {
      if (ev.propertyName !== 'max-height') return;
      if (sn.classList.contains('is-expanded')) sn.style.maxHeight = 'none';
    });

    const ro = new ResizeObserver(() => {
      if (sn.classList.contains('is-expanded')) return;
      sn.style.maxHeight = collapsedH + 'px';
    });
    ro.observe(sn);
  });
}
initSinossiToggles();

/* ===== Archivio: Normalizzazione iniziale + Mostra altri (+3) ===== */
(function () {
  const container = document.getElementById('allArticles');
  const btn = document.getElementById('altro');
  if (!container || !btn) return;
  const take = 3;

  (function normalizeArchivio() {
    const cards = Array.from(container.querySelectorAll('[data-archivio]'));
    cards.forEach((el, i) => {
      if (i >= 3) {
        if (!el.hasAttribute('data-hidden')) el.setAttribute('data-hidden', '1');
        el.hidden = false;
        el.style.removeProperty?.('display');
      } else {
        el.removeAttribute('data-hidden');
        el.hidden = false;
        el.style.removeProperty?.('display');
      }
    });
  })();

  const hiddenList = () =>
    Array.from(container.querySelectorAll('[data-archivio][data-hidden="1"]'));

  function reveal(el) {
    el.removeAttribute('data-hidden');
    el.hidden = false;
    el.style.removeProperty('display');
    el.animate(
      [{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }],
      { duration: 160, easing: 'ease-out' }
    );
  }

  function updateLabel() {
    const rem = hiddenList().length;
    if (rem <= 0) {
      btn.closest('div')?.remove();
      return;
    }
    const next = Math.min(rem, take);
    btn.innerHTML = `<i class="fa-solid fa-angles-down"></i> Mostra altri (${next}/${rem})`;
  }

  btn.addEventListener('click', () => {
    const list = hiddenList();
    if (!list.length) { updateLabel(); return; }
    list.slice(0, take).forEach(reveal);
    updateLabel();
  });

  updateLabel();
})();

/* ===== Modal: open/close + focus trap ===== */
const modal       = document.getElementById('modale-libro');
const sheet       = modal?.querySelector('.sheet');
const form        = document.getElementById('formModificaLibro');
const imgEl       = document.getElementById('imgEsistenteLibro');
const selCat      = document.getElementById('categoriaLibro');
const catChips    = document.getElementById('catChips');
const coverInput  = document.getElementById('coverInput');
const btnRemove   = document.getElementById('btnRemoveCover');
const newCatInput = document.getElementById('newCategory');
const newCatHidden= document.getElementById('newCategoryHidden');
const btnAddCat   = document.getElementById('btnAddCat');

/* ===== Casa editrice: riferimenti (SELECT + "Altro…") ===== */
const casaEl   = document.getElementById('casaEditriceLibro');     // <select name="casa_editrice">
const ceWrap   = document.getElementById('casaEditriceCustomWrap'); // wrapper input custom
const ceCustom = document.getElementById('casaEditriceCustom');     // input text per "Altro…"

let lastOpener = null;

function trapFocus(e) {
  if (!modal.classList.contains('open')) return;
  const focusables = modal.querySelectorAll(
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  );
  const list = Array.from(focusables).filter((el) => !el.hasAttribute('disabled'));
  if (!list.length) return;
  const first = list[0], last = list[list.length - 1];
  if (e.key === 'Tab') {
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }
}
function apri(openerBtn) {
  lastOpener = openerBtn || null;
  modal.classList.add('open'); modal.classList.remove('closing');
  document.body.classList.add('modal-open');
  modal.setAttribute('aria-hidden', 'false');
  setTimeout(() => { form?.querySelector('input, select, textarea, button')?.focus(); }, 10);
}
function chiudi() {
  modal.classList.add('closing');
  modal.addEventListener('transitionend', function onEnd(ev) {
    if (ev.target !== sheet) return;
    modal.removeEventListener('transitionend', onEnd);
    modal.classList.remove('open', 'closing');
    document.body.classList.remove('modal-open');
    modal.setAttribute('aria-hidden', 'true');
    if (lastOpener) { try { lastOpener.focus(); } catch (_) { } }
  }, { once: true });
}
modal?.addEventListener('click', (e) => { if (e.target === modal) chiudi(); });
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-modal-close]');
  if (btn && modal.classList.contains('open')) { e.preventDefault(); chiudi(); }
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && modal.classList.contains('open')) chiudi();
  if (e.key === 'Tab'    && modal.classList.contains('open')) trapFocus(e);
});

/* ===== Utils immagini ===== */
function normImg(p) {
  if (!p) return '';
  p = String(p).trim();
  if (/^https?:\/\//i.test(p) || p.startsWith('//')) return p;
  if (p.startsWith('/backend/uploads/')) return p;
  if (p.startsWith('backend/uploads/')) return '/' + p;
  if (p.startsWith('uploads/')) return '/backend/' + p;
  if (!p.includes('/')) return '/backend/uploads/' + p;
  if (p[0] !== '/') return '/' + p;
  return p;
}
function toApiImgValue(src) {
  if (!src) return '';
  try { if (/^https?:\/\//i.test(src)) src = new URL(src).pathname; } catch (_) { }
  src = src.replace(/^\/+/, '');
  if (src.startsWith('backend/')) src = src.substring('backend/'.length);
  return src;
}

/* ===== Categorie: chips dinamiche ===== */
function syncCategoryChips() {
  if (!selCat) return;
  const opts = Array.from(selCat.options);
  const sel  = opts.filter((o) => o.selected);
  catChips.innerHTML = '';
  sel.forEach((o) => {
    const chip = document.createElement('span');
    chip.className = 'cat-chip';
    chip.innerHTML = `<i class="fa-solid fa-tag"></i> ${o.text} <span class="x" title="Rimuovi">&times;</span>`;
    chip.querySelector('.x').addEventListener('click', () => {
      o.selected = false;
      selCat.dispatchEvent(new Event('change', { bubbles: true }));
    });
    catChips.appendChild(chip);
  });
}
selCat?.addEventListener('change', syncCategoryChips);

/* ===== UI: nuova categoria in lista (solo UI; invio con new_category) ===== */
btnAddCat?.addEventListener('click', () => {
  const name = (newCatInput?.value || '').trim();
  if (!name) return;
  const dup = Array.from(selCat.options).some((o) => o.text.trim().toLowerCase() === name.toLowerCase());
  if (dup) {
    Array.from(selCat.options).forEach((o) => {
      if (o.text.trim().toLowerCase() === name.toLowerCase()) o.selected = true;
    });
    selCat.dispatchEvent(new Event('change', { bubbles: true }));
    newCatHidden.value = '';
    newCatInput.value = '';
    return;
  }
  const opt = document.createElement('option');
  opt.value = '__new__:' + name;
  opt.textContent = name + ' (nuova)';
  opt.selected = true;
  selCat.appendChild(opt);
  selCat.dispatchEvent(new Event('change', { bubbles: true }));
  newCatHidden.value = name;
  newCatInput.value = '';
});

/* ===== Casa editrice: toggle campo "Altro…" (un solo listener globale) ===== */
casaEl?.addEventListener('change', () => {
  if (!casaEl || !ceWrap || !ceCustom) return;
  if (casaEl.value === '__custom__') {
    ceWrap.style.display = '';
    ceCustom.focus();
  } else {
    ceWrap.style.display = 'none';
    ceCustom.value = '';
  }
});

/* ===== Bind apertura modale + fetch dati ===== */
document.querySelectorAll('.open-edit').forEach((btn) => {
  btn.addEventListener('click', async (e) => {
    e.preventDefault();
    const id = btn.getAttribute('data-id');
    if (!id) return;

    try {
      const res = await fetch(`../api/get_contenuto.php?tipo=libro&id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();

      // campi base
      form.id.value      = data.id || '';
      form.titolo.value  = data.titolo || '';
      document.getElementById('excerptLibro').value = (data.descrizione || data.contenuto || '');
      form.date.value    = (data.data_pubblicazione || '').slice(0, 10);
      form.link.value    = data.link || '';
      newCatHidden.value = '';
      newCatInput.value  = '';

      // CASA EDITRICE: popola select + preselect corrente + "Altro…"
      const current = (data.casa_editrice || '').trim();
      if (casaEl) {
        const names = Array.isArray(data.editori) ? data.editori : [];
        const all = [current, ...names].filter(Boolean);
        const seen = new Set();
        const uniq = all.filter((n) => {
          const k = n.toLowerCase();
          if (seen.has(k)) return false;
          seen.add(k); return true;
        });

        casaEl.innerHTML = '';
        // opzioni “normali”
        uniq.forEach((n) => {
          const opt = new Option(n, n, false, n.toLowerCase() === current.toLowerCase());
          casaEl.appendChild(opt);
        });
        // separatore visivo (disabilitato)
        if (uniq.length) {
          const sep = new Option('────────────', '', false, false);
          sep.disabled = true;
          casaEl.appendChild(sep);
        }
        // opzione Altro…
        casaEl.appendChild(new Option('Altro…', '__custom__', false, false));

        // originale per safety in submit
        casaEl.dataset.orig = current;

        // stato iniziale campo custom
        if (ceWrap && ceCustom) {
          ceWrap.style.display = 'none';
          ceCustom.value = '';
        }
      }

      // Copertina
      const src = normImg(data.copertina || '');
      if (src) {
        imgEl.src = src; imgEl.style.display = 'block';
        form.existing_img.value = toApiImgValue(src);
      } else {
        imgEl.style.display = 'none'; form.existing_img.value = '';
      }
      if (coverInput) coverInput.value = '';

      // Categorie (lista + selezionate)
      selCat.innerHTML = '';
      if (Array.isArray(data.categorie)) {
        data.categorie.forEach((c) => {
          const opt = document.createElement('option');
          opt.value = String(c.id);
          opt.textContent = c.nome;
          selCat.appendChild(opt);
        });
      }
      const selected = new Set(Array.isArray(data.categorie_ids) ? data.categorie_ids.map(String) : []);
      Array.from(selCat.options).forEach((opt) => opt.selected = selected.has(opt.value));
      syncCategoryChips();
// --- Modalità sola lettura se l'utente non ha portfolio.edit ---
const saveBtn   = form?.querySelector('button[type="submit"]');
const actions   = form?.querySelector('.actions');

function setReadOnly(ro) {
  form.querySelectorAll('input, select, textarea, button').forEach(el => {
    // consenti solo bottoni con data-modal-close per chiudere
    if (el.hasAttribute('data-modal-close')) return;
    if (el.tagName === 'BUTTON') { el.disabled = ro; return; }
    el.disabled = ro;
    if (ro && el.tagName === 'TEXTAREA') el.readOnly = true;
  });
  if (saveBtn)   saveBtn.style.display   = ro ? 'none' : '';
  if (btnRemove) btnRemove.style.display = ro ? 'none' : '';
  if (coverInput) coverInput.disabled    = ro;

  // badge nel titolo
  const mt = document.getElementById('modalLibroTitolo');
  if (mt) {
    const base = `<i class="fa-regular fa-pen-to-square"></i> Modifica libro`;
    mt.innerHTML = ro ? `${base} <span class="chip sm" style="margin-left:8px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;">Sola lettura</span>` : base;
  }
}

setReadOnly(!CAN_EDIT);

// Difesa extra: blocca il submit se read-only
if (!CAN_EDIT) {
  form.addEventListener('submit', function(ev){ ev.preventDefault(); }, { once:true });
}

      apri(btn);
    } catch (err) {
      console.error(err);
      alert('Impossibile aprire il libro.');
    }
  });
});

/* ===== Copertina: anteprima + rimozione ===== */
coverInput?.addEventListener('change', (e) => {
  const f = e.target.files && e.target.files[0];
  if (!f) return;
  const url = URL.createObjectURL(f);
  imgEl.src = url; imgEl.style.display = 'block';
});
btnRemove?.addEventListener('click', () => {
  if (coverInput) coverInput.value = '';
  imgEl.src = ''; imgEl.style.display = 'none';
  if (form.existing_img) form.existing_img.value = '';
});

/* ===== Submit -> API modifiche ===== */
form?.addEventListener('submit', async (e) => {
  e.preventDefault();

  // CASA EDITRICE: se Altro… o vuoto, gestisci fallback su originale
  if (casaEl) {
    if (casaEl.value === '__custom__') {
      const v = (ceCustom?.value || '').trim();
      casaEl.value = v || (casaEl.dataset.orig || '');
    } else if (casaEl.value.trim() === '' && casaEl.dataset.orig) {
      casaEl.value = casaEl.dataset.orig;
    }
  }

  const fd = new FormData(form);
  try {
    const res = await fetch('../api/modifica_contenuto.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const out = await res.json().catch(() => ({ success: false }));
    if (!res.ok || !out.success) throw new Error('Save failed');
    location.reload();
  } catch (err) {
    console.error(err);
    alert('Salvataggio non riuscito.');
  }
});

/* ===== Chips dinamiche categorie (inizializzazione iniziale) ===== */
document.addEventListener('DOMContentLoaded', () => {
  // inizializza rappresentazione chips in base alla select (se già presente)
  syncCategoryChips();
});

// ===== Eliminazione libro (delegata) =====
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-del-libro');
  if (!btn) return;

  // Rispetta i permessi lato client
  if (!CAN_DELETE) return;

  const id = btn.getAttribute('data-id');
  const titolo = btn.getAttribute('data-title') || 'questo libro';
  if (!id) return;

  const ok = confirm(`Sei sicuro di voler eliminare "${titolo}"? L'operazione è irreversibile.`);
  if (!ok) return;

  btn.disabled = true;

  try {
    const fd = new FormData();
    fd.append('tipo', 'libro');
    fd.append('id', id);

    const res = await fetch('../api/elimina_contenuto.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });
    const out = await res.json().catch(() => ({}));

    if (!res.ok || !out.success) {
      throw new Error(out.error || 'Eliminazione non riuscita');
    }

    // Rimuovi la card dalla UI con una piccola animazione
    const card = btn.closest('.book-card');
    if (card) {
      card.animate(
        [{opacity:1, transform:'none'}, {opacity:0, transform:'translateY(6px)'}],
        {duration:160, easing:'ease-out'}
      ).onfinish = () => card.remove();
    }

    // (Opzionale) mostra messaggio "eliminato" ricaricando la pagina
    // location.href = 'index_libro.php?msg=eliminato';
  } catch (err) {
    alert(err.message || 'Errore durante l’eliminazione.');
    btn.disabled = false;
  }
});
