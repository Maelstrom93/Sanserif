/* =========================================================================
 *  LAVORI — JS pulito e organizzato
 *  - Helpers
 *  - Filtri (submit automatico + transizione) + Stagger
 *  - Infinite scroll
 *  - SLA chip sulle card
 *  - Modale VIEW (titolo in header) + timeline read-only
 *  - Modale EDIT (toggle rapido completamento) + submit AJAX
 *  - Elimina lavoro (conferma)
 *  - Scorciatoie tastiera + persistenza filtri + tabs
 *  - Render/Serialize attività (edit)
 *  - Skeleton loader
 *  - Chip SLA aggiuntivi nella chipbar
 *  - Inline edit (scadenza) sulle card
 *  - Quick chip cambio stato
 * ========================================================================= */

/* ===================== Helpers ===================== */
const $$  = (sel, root=document) => Array.from(root.querySelectorAll(sel));
const $   = (sel, root=document) => root.querySelector(sel);

function money(v){
  if (v===null || v===undefined || v==='') return '—';
  const n = parseFloat(v); if (isNaN(n)) return '—';
  return n.toFixed(2).replace('.',',') + ' €';
}
function fmt(d){
  if (!d) return '—';
  try { return new Date(d).toLocaleDateString('it-IT'); }
  catch(_) { return d; }
}
function esc(s){
  return String(s||'').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[m]));
}
window.toast = window.toast || function(msg, type){
  const box = $('#toasts') || (()=>{ const n=document.createElement('div'); n.id='toasts'; document.body.appendChild(n); return n; })();
  const t = document.createElement('div'); t.className = 'toast ' + (type||'info'); t.textContent = msg;
  box.appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translateY(6px)'; }, 2200);
  setTimeout(()=>{ t.remove(); }, 2800);
  const lr = $('#liveRegion'); if (lr) lr.textContent = msg;
};

/* ========== Filtri: submit automatico + transizione + Stagger ========== */
(function(){
  const f = $('#formFiltri');
  function leaveThen(go){ $$('.jobs-row').forEach(r=>r.classList.add('leaving')); setTimeout(go, 180); }

  if (f){
    f.addEventListener('submit', e => { e.preventDefault(); leaveThen(()=>f.submit()); });
    $$('select, input[type="date"]', f).forEach(el=>{
      el.addEventListener('change', ev => { ev.preventDefault(); leaveThen(()=>f.submit()); });
    });
  }
  // chip bar / link interni
  document.addEventListener('click', e=>{
    const a = e.target.closest('a.chip, .chipbar a.chip');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || href.indexOf('index_lavori.php') === -1) return;
    e.preventDefault();
    leaveThen(()=>{ window.location.href = href; });
  });

  // Stagger
  function applyStagger(scope=document){
    $$('.jobs-row', scope).forEach(row=>{
      $$('.job-card', row).forEach((card, i)=> card.style.setProperty('--i', i));
    });
  }
  applyStagger();
  // esponi per ri-uso
  window.__applyStagger = applyStagger;
})();

/* ===================== Infinite scroll ===================== */
(function(){
  const container = $('#allJobs'); if (!container) return;

  let loading = false;
  let page    = parseInt($('input[name="page"]')?.value || '1', 10);
  const perPage= parseInt($('input[name="per_page"]')?.value || '18', 10);

  const sentinel = document.createElement('div');
  sentinel.id = 'sentinel'; sentinel.style.height='1px';
  container.after(sentinel);

  function buildQuery(nextPage){
    const url = new URL('../api/lista_lavori.php', window.location.href);
    const f = $('#formFiltri');
    if (f) new FormData(f).forEach((v,k)=>url.searchParams.set(k, v));
    url.searchParams.set('page',     String(nextPage));
    url.searchParams.set('per_page', String(perPage));
    return url;
  }

  const io = new IntersectionObserver(entries=>{
    entries.forEach(en=>{
      if (!en.isIntersecting || loading) return;
      loading = true;
      const next = page + 1;
      const url  = buildQuery(next);
      document.body.setAttribute('aria-busy','true');

      fetch(url.toString(), { credentials:'same-origin', headers:{'Accept':'text/html'} })
        .then(r=>r.text())
        .then(html=>{
          if (!html.trim()){ io.unobserve(sentinel); return; }
          const tmp = document.createElement('div'); tmp.innerHTML = html;
          tmp.querySelectorAll('.job-card').forEach(card=> container.appendChild(card));
          // repaint SLA + stagger
          setTimeout(()=>{
            typeof window.__paintSLA==='function' && window.__paintSLA(container);
            typeof window.__applyStagger==='function' && window.__applyStagger(container);
          }, 0);
          page = next;
        })
        .catch(()=>toast('Errore nel caricamento altri lavori','err'))
        .finally(()=>{ loading=false; document.body.removeAttribute('aria-busy'); });
    });
  },{ rootMargin:'800px 0px' });

  io.observe(sentinel);
})();

/* ===================== SLA chip sulle card ===================== */
(function(){
  function paint(container=document){
    $$('.job-card', container).forEach(card=>{
      const d = card.getAttribute('data-scadenza'); if (!d) return;
      const sla = $('.sla', card); if (!sla) return;
      try{
        const today = new Date(); today.setHours(0,0,0,0);
        const sca = new Date(d+'T00:00:00');
        const diff = Math.round((sca - today)/(1000*60*60*24));
        if (diff < 0){ sla.classList.add('late'); sla.textContent = 'Scaduto da '+Math.abs(diff)+'g'; }
        else if (diff <= 2){ sla.classList.add('soon'); sla.textContent = 'Scade tra '+diff+'g'; }
        else { sla.classList.add('ok'); sla.textContent = 'Tra '+diff+'g'; }
      }catch(_){}
    });
  }
  paint();
  window.__paintSLA = paint;
})();

/* ===================== Modale VIEW (dettaglio) ===================== */
(function(){
  const modal = $('#modale-view'); if (!modal) return;
  const sheet = $('.sheet', modal);
  const header= $('.m-header', modal);
  const meta  = $('#v-meta');
  const ovw   = $('#v-overview');
  const list  = $('#v-attivita');
  const cats  = $('#v-categorie');
  const desc  = $('#v-desc');
  const badge = $('#mv-badge-stato');
  const titleH= $('#modalViewTitolo');

  function lockScroll(on){ document.body.classList[on?'add':'remove']('modal-open'); }
  function headerShadow(){ if (!sheet||!header) return; header.classList[ sheet.scrollTop>4 ? 'add':'remove' ]('shadow'); }
  function open(){ lockScroll(true); sheet && (sheet.scrollTop=0); modal.classList.add('open'); modal.classList.remove('closing'); modal.setAttribute('aria-hidden','false'); headerShadow(); }
  function close(){
    modal.classList.add('closing');
    modal.addEventListener('transitionend', function onEnd(ev){
      if (ev.target !== sheet) return;
      modal.removeEventListener('transitionend', onEnd);
      modal.classList.remove('open','closing');
      modal.setAttribute('aria-hidden','true');
      lockScroll(false);
    }, {once:true});
  }

  modal.addEventListener('click', e=>{ if (e.target===modal) close(); });
  sheet && sheet.addEventListener('scroll', headerShadow, {passive:true});
  document.addEventListener('click', e=>{
    const b = e.target.closest('[data-view-close]');
    if (b && modal.classList.contains('open')){ e.preventDefault(); close(); }
  });

  // Focus trap + ESC
  (function trap(modalEl){
    if (!modalEl) return;
    const sh = $('.sheet', modalEl);
    function foci(){ return $$('a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])', sh); }
    modalEl.addEventListener('keydown', e=>{
      if (e.key==='Escape'){ const c=$('[data-view-close]', modalEl); c && c.click(); }
      if (e.key!=='Tab') return;
      const f = foci(); if (!f.length) return;
      const first=f[0], last=f[f.length-1];
      if (e.shiftKey && document.activeElement===first){ e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement===last){ e.preventDefault(); first.focus(); }
    });
  })(modal);

  // Tabs (view)
  (function(){
    const btns = $$('.tab-btn', modal);
    const panels = $$('.tab-panel', modal);
    function select(id){
      btns.forEach(b=> b.setAttribute('aria-selected', b.getAttribute('aria-controls')===id ? 'true':'false'));
      panels.forEach(p=> p.classList.toggle('active', p.id===id));
    }
    btns.forEach(b=> b.addEventListener('click', ()=>select(b.getAttribute('aria-controls'))));
  })();

  function slaBadge(dateStr){
    if (!dateStr) return '';
    try{
      const today = new Date(); today.setHours(0,0,0,0);
      const sca = new Date(dateStr+'T00:00:00');
      const diff = Math.round((sca - today)/(1000*60*60*24));
      if (diff < 0)  return `<span class="chip sm sla late">Scaduto da ${Math.abs(diff)}g</span>`;
      if (diff <= 2) return `<span class="chip sm sla soon">Scade tra ${diff}g</span>`;
      return `<span class="chip sm sla ok">Tra ${diff}g</span>`;
    }catch(_){ return ''; }
  }

  // Apri VIEW
  $$('.open-view').forEach(btn=>{
    btn.addEventListener('click', e=>{
      e.preventDefault();
      const id = btn.getAttribute('data-id'); if(!id) return;

      const apiUrl = new URL('../api/get_lavoro.php', window.location.href);
      apiUrl.searchParams.set('id', id);

      fetch(apiUrl.toString(), { method:'GET', credentials:'same-origin', headers:{ 'Accept':'application/json' }, cache:'no-store' })
        .then(res => { if(!res.ok) throw new Error('HTTP '+res.status); return res.json(); })
        .then(data=>{
          // Titolo in header
          if (titleH){
            titleH.innerHTML = `<i class="fa-regular fa-eye"></i> ${esc(data.titolo || 'Dettaglio lavoro')}`;
          }
          // Badge stato
          if (badge){
            const st = (data.stato||'aperto');
            badge.className = 'badge state-'+st;
            badge.textContent = st;
          }

          // Meta
          const clienteLbl = (data.cliente_label || data.cliente_nome || data.cliente || (data.cliente_id ? ('Cliente #'+data.cliente_id) : '—'));
        meta.innerHTML =
  `<div class="meta"><i class="fa-solid fa-inbox"></i> ${data.data_ricezione?fmt(data.data_ricezione):'—'} · `+
  `<i class="fa-regular fa-calendar-days"></i> ${data.scadenza?fmt(data.scadenza):'—'}`+
  `${(window.__CAN_SEE_PRICE && data.prezzo!=null) ? ` · <i class="fa-solid fa-euro-sign"></i> ${money(data.prezzo)}` : ''} `+
  `${slaBadge(data.scadenza)}</div>`;


          // Overview
          // Overview (con ex-assegnatari se lavoro chiuso)
(function buildOverview(){
  const statoClosing = ['completato','chiuso','annullato'].includes(String(data.stato||'').toLowerCase());

  // raccogli ex-assegnatari dalle attività (usa utente_ex oppure storico)
  function pickExAssignee(a){
  if (a && a.utente_ex) return a.utente_ex; // backend già lo calcola
  const h = Array.isArray(a?.assignees_history) ? a.assignees_history.filter(x => x && x.name) : [];
  if (!h.length) return null;

  // Preferisci l’ultimo assegnatario "chiuso" (ha un 'to' valorizzato)
  const closed = h.filter(x => x.to);
  if (closed.length) return closed[closed.length - 1].name;

  // Altrimenti, prendi il penultimo (chi era prima dell'attuale/ultimo)
  if (h.length >= 2) return h[h.length - 2].name || h[h.length - 1].name || null;

  // Fallback: l’ultimo noto
  return h[h.length - 1].name || null;
}


  const exUsersSet = new Set();
  (Array.isArray(data.attivita) ? data.attivita : []).forEach(a=>{
    const ex = pickExAssignee(a);
    if (ex) exUsersSet.add(ex);
  });
  const exUsers = Array.from(exUsersSet);

  // assegnatario macro corrente (se presente)
  const macroAss = (data.assegnato_a_label && String(data.assegnato_a_label).trim()!=='')
      ? String(data.assegnato_a_label).trim()
      : '';

  // riga "Assegnazione"
  let assegnazioneRow = '';
  if (macroAss || (statoClosing && exUsers.length)) {
    const exChips = (statoClosing && exUsers.length)
      ? exUsers.map(n=>`<span class="chip sm light">ex ${esc(n)}</span>`).join(' ')
      : '';
    const curr = macroAss ? `<span class="tl-meta">${esc(macroAss)}</span>` : '<span class="tl-meta">—</span>';
    assegnazioneRow =
      `<div class="tl-head" style="margin-top:6px">
         <div><strong>Assegnazione</strong></div>
         <div class="tl-meta" style="display:flex; gap:6px; flex-wrap:wrap;">
           ${curr} ${exChips}
         </div>
       </div>`;
  }

  // cliente / provenienza / cartelle
  ovw.innerHTML =
    `<div class="tl-head"><div><strong>Cliente</strong></div><div class="tl-meta">${esc(clienteLbl)}</div></div>`+
    assegnazioneRow +
    `${data.provenienza ? `<div class="tl-head" style="margin-top:6px"><div><strong>Provenienza</strong></div><div class="tl-meta">${esc(data.provenienza)}</div></div>` : ''}`+
    `${(data.cartelle && data.cartelle.length)
        ? `<div style="margin-top:6px"><strong>Cartelle:</strong> ${
            data.cartelle.map(esc).map(x=>`<span class="chip sm" style="margin-right:6px">${x}</span>`).join(' ')
          }</div>`
        : ''}`;
})();


          // Categorie + Descrizione
          cats.textContent = (data.categorie_nomi || '').trim() || '—';
          desc.textContent = data.descrizione || '—';

 // Timeline attività (read-only) — versione “ricca” come nel MODALE EDIT
list.innerHTML = '';
const arr = Array.isArray(data.attivita) ? data.attivita : [];
if (!arr.length){
  list.innerHTML = '<div class="meta">Nessuna attività</div>';
} else {

  // helper: “ex assegnatario” (come in EDIT)
  function pickExAssignee(a){
  if (a && a.utente_ex) return a.utente_ex; // backend già lo calcola
  const h = Array.isArray(a?.assignees_history) ? a.assignees_history.filter(x => x && x.name) : [];
  if (!h.length) return null;

  // Preferisci l’ultimo assegnatario "chiuso" (ha un 'to' valorizzato)
  const closed = h.filter(x => x.to);
  if (closed.length) return closed[closed.length - 1].name;

  // Altrimenti, prendi il penultimo (chi era prima dell'attuale/ultimo)
  if (h.length >= 2) return h[h.length - 2].name || h[h.length - 1].name || null;

  // Fallback: l’ultimo noto
  return h[h.length - 1].name || null;
}


  arr.forEach(a=>{
    const idAtt  = parseInt(a.id || a.attivita_id || 0, 10) || Math.floor(Math.random()*1e9);
    const titolo = (a.titolo || a.nome_attivita || 'Attività');
    const cat    = (a.categoria_nome || a.categoria || '');
    const scad   = (a.scadenza || a.data_scadenza || '');
    const comp   = !!(a.completata || a.done || false);
    const compIl = (a.closed_at || a.completato_il || '');

    const currUser = (a.utente || a.utente_nome || (a.utente_id ? ('#' + a.utente_id) : '—'));
    const exUser   = pickExAssignee(a);

    // wrapper stile “chk-item” (come in EDIT), ma read-only
    const row = document.createElement('div');
    row.className = 'chk-item' + (comp ? ' opacity-60' : '');

    row.innerHTML =
      '<input type="checkbox" disabled ' + (comp ? 'checked' : '') + ' aria-label="Completata">' +
      '<div style="flex:1; min-width:0;">' +
        '<div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' +
          esc(titolo) + (cat ? (' — ' + esc(cat)) : '') +
        '</div>' +
        '<div class="muted" style="font-size:12px;">' +
          '<i class="fa-regular fa-user"></i> ' + esc(currUser) +
          (exUser ? ' <span class="chip sm light">ex ' + esc(exUser) + '</span>' : '') +
          (cat ? ' · <i class="fa-solid fa-tag"></i> ' + esc(cat) : '') +
          ' · ' + (scad ? ('Scadenza: ' + esc(fmt(scad))) : 'Senza scadenza') +
          (compIl ? (' — Completata il ' + (function(v){try{return v?new Date(v).toLocaleString("it-IT"):'';}catch(_){return v||'';}})(compIl)) : '') +
        '</div>' +
        (a.descrizione ? ('<div class="meta" style="margin-top:4px">' + esc(a.descrizione) + '</div>') : '') +
      '</div>' +
      '<span class="chk-progress" id="compl-meta-' + idAtt + '"></span>';

    // meta grigio tra parentesi a destra (come in EDIT)
    const metaRight = row.querySelector('#compl-meta-' + idAtt);
    if (metaRight && compIl) {
      try { metaRight.textContent = '(' + new Date(compIl).toLocaleString('it-IT') + ')'; } catch (_) { /* noop */ }
    }

    list.appendChild(row);
  });
}

open();

        })
        .catch(()=>{ const t=$('#liveRegion'); if(t){t.textContent='Errore nel caricamento dettaglio';} });
    });
  });
})();

/* ===================== Modale EDIT (toggle rapido + submit AJAX) ===================== */
(function(){
  if (window.__READONLY_JOBFORM) {
  // forza selezione tab Lavorazioni
  document.getElementById('tabbtn-attivita-edit')?.click();
}

  const modalSel = '#modale-lavoro';
  const formSel  = '#formModificaLavoro';

  function lockScroll(on){ document.body.classList[on?'add':'remove']('modal-open'); }

  function openModal(){
    const modal = $(modalSel); if (!modal) return;
    const sheet = $('.sheet', modal); const header = $('.m-header', modal);
    function headerShadow(){ if (!sheet||!header) return; header.classList[ sheet.scrollTop>4 ? 'add':'remove' ]('shadow'); }
    lockScroll(true);
    if (sheet) { sheet.scrollTop = 0; sheet.removeEventListener('scroll', headerShadow); sheet.addEventListener('scroll', headerShadow, {passive:true}); }
    modal.classList.add('open'); modal.classList.remove('closing'); modal.setAttribute('aria-hidden','false'); headerShadow();
  }

  function closeModal(){
  const modal = document.querySelector('#modale-lavoro'); if (!modal) return;
  const sheet = modal.querySelector('.sheet');

  modal.classList.add('closing');

  const finish = ()=> {
    modal.classList.remove('open','closing');
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('modal-open');
  };

  let handled = false;
  const onEnd = (ev)=>{
    // chiudi al primo transitionend che arriva, da qualunque elemento
    if (handled) return;
    handled = true;
    modal.removeEventListener('transitionend', onEnd);
    sheet && sheet.removeEventListener('transitionend', onEnd);
    finish();
  };

  // ascolta sia il modal che la sheet, nel dubbio
  modal.addEventListener('transitionend', onEnd);
  sheet && sheet.addEventListener('transitionend', onEnd);

  // Fallback: se non c'è alcuna transizione, chiudi comunque dopo 200ms
  setTimeout(()=>{ if (!handled) finish(); }, 200);
}


  // Chiudi al click su sfondo / bottone chiudi
  (function wireCloseOnce(){
    const modal = $(modalSel); if (!modal) return;
    if (modal._wiredClose) return; modal._wiredClose = true;
    const sheet = $('.sheet', modal);
    modal.addEventListener('click', e=>{ if(e.target===modal) closeModal(); });
    sheet && sheet.addEventListener('scroll', ()=>{ const h=$('.m-header', modal); if(h) h.classList[sheet.scrollTop>4?'add':'remove']('shadow'); }, {passive:true});
 document.addEventListener('click', (e)=>{
  const b = e.target.closest('#modale-lavoro [data-modal-close]');
  if (!b) return;

  // 1) Chiudi SUBITO
  closeModal();

  // 2) Best-effort refresh se lo stato è “closing”, ma NON bloccare la chiusura
  (async ()=>{
    try{
      const jobId = $('#edit-id')?.value;
      const stSel = $('#edit-stato')?.value || '';
      const isClosing = ['completato','chiuso','annullato'].includes(String(stSel).toLowerCase());
      if (!(jobId && isClosing)) return;

      const url = new URL('../api/get_lavoro.php', location.href);
      url.searchParams.set('id', jobId);
      const fresh = await fetch(url, {credentials:'same-origin'}).then(r=>r.json());

      // aggiorna toggle rapido se esposto
      if (fresh && fresh.attivita && typeof window.renderQuickList === 'function') {
        window.renderQuickList(fresh.attivita);
      }

      // se la VIEW è aperta, ricaricala
      const mv = document.getElementById('modale-view');
      if (mv && mv.classList.contains('open')) {
        const btn = document.querySelector(`.open-view[data-id="${jobId}"]`);
        btn && btn.click();
      }
    }catch(_){ /* silenzioso */ }
  })();
});



// focus trap + ESC
    (function trap(){
      const sh = $('.sheet', modal);
      function foci(){ return $$('a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])', sh); }
      modal.addEventListener('keydown', e=>{
        if (e.key==='Escape'){ const cl=$('[data-modal-close]', modal); cl && cl.click(); }
        if (e.key!=='Tab') return;
        const f=foci(); if(!f.length) return;
        const first=f[0], last=f[f.length-1];
        if (e.shiftKey && document.activeElement===first){ e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement===last){ e.preventDefault(); first.focus(); }
      });
    })();
  })();

  function fmtDateForInput(d){ if(!d) return ''; try{ return new Date(d).toISOString().slice(0,10); } catch(_){ return ''; } }

  // helper: setter sicuro
  function setVal(sel, val){
    const el = $(sel);
    if (!el){ console.warn('Campo mancante nel DOM:', sel); return null; }
    el.value = (val===null || typeof val==='undefined') ? '' : val;
    return el;
  }

  // Delegato: funziona anche per card aggiunte dopo
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.open-edit'); if (!btn) return;
    e.preventDefault();

    const id = btn.getAttribute('data-id'); if(!id){ toast('ID lavoro mancante','err'); return; }

    const apiUrl = new URL('../api/get_lavoro.php', window.location.href);
    apiUrl.searchParams.set('id', id);

    fetch(apiUrl.toString(), { method:'GET', credentials:'same-origin', headers:{ 'Accept':'application/json' }, cache:'no-store' })
      .then(async res => {
        const raw = await res.text();
        if (!res.ok) throw new Error('HTTP '+res.status+' — '+raw.slice(0,120));
        let data; try { data = JSON.parse(raw); } catch (e) {
          console.error('get_lavoro NON JSON:', raw);
          throw new Error('Risposta non valida dal server');
        }
        return data;
      })
      .then(data => {
        // se il form non esiste, non andare oltre (evita TypeError)
        const form = $(formSel);
         const jobIdForToggles = data.id;
        if (!form){ toast('Form modifica non presente in pagina','err'); return; }

        // Campi base (tutti con setter sicuro)
        setVal('#edit-id',              data.id);
        setVal('#edit-titolo',          data.titolo || '');
        setVal('#edit-data_ricezione',  fmtDateForInput(data.data_ricezione));
        setVal('#edit-scadenza',        fmtDateForInput(data.scadenza));
        setVal('#edit-prezzo',          (data.prezzo===null||typeof data.prezzo==='undefined') ? '' : data.prezzo);
        setVal('#edit-provenienza',     data.provenienza || '');
        setVal('#edit-stato',           data.stato || 'aperto');
        setVal('#edit-descrizione',     data.descrizione || '');
        setVal('#edit-priorita',        data.priorita || 'media');
        setVal('#edit-cliente',         data.cliente_id || '');
        setVal('#edit-assegnato_a',     data.assegnato_a || '');

        // Attività editabili
        renderAttivitaEdit(Array.isArray(data.attivita) ? data.attivita : []);

        // Forza tab “Dettagli base”
(function ensureDefaultTab(){
  const wantAtt = !!window.__READONLY_JOBFORM;  // i read-only devono vedere le Lavorazioni
  const btn  = $(wantAtt ? '#tabbtn-attivita-edit' : '#tabbtn-base');
  const pane = $(wantAtt ? '#tab-attivita-edit'  : '#tab-base');
  // reset
  $$('#modale-lavoro .tab-btn').forEach(b => b.setAttribute('aria-selected','false'));
  $$('#modale-lavoro .tab-panel').forEach(p => p.classList.remove('active'));
  // set
  if (btn)  btn.setAttribute('aria-selected','true');
  if (pane) pane.classList.add('active');
  void (pane && pane.offsetHeight);
})();


        // Bottoni attività (bind leggera ad ogni apertura)
        const btnAdd = $('#addAttivita');
        if (btnAdd){ btnAdd.onclick = ()=>{ const list = $('#attivita-list'); if (list && typeof list._addRow==='function') list._addRow({}); }; }
        const btnSaveA = $('#salvaAttivita');
        if (btnSaveA){ btnSaveA.onclick = ()=>{ const arr = serializeAttivitaEdit(); const h = ensureRigheJsonHidden(); if (h) h.value = JSON.stringify(arr); toast('Attività pronte per il salvataggio','ok'); }; }

        // Submit AJAX (bind una sola volta)
      // Submit AJAX (bind una sola volta)
if (!form._ajaxBound){
  // helper robusto per JSON
  function safeParseJson(raw){
    // prova JSON diretto
    try { return JSON.parse(raw); } catch(_){}
    // se è HTML (redirect/login/error), abort
    if (/^\s*<!doctype html/i.test(raw) || /^\s*<html[\s>]/i.test(raw)) return null;
    // prova ad estrarre il primo blocco { ... }
    const i = raw.indexOf('{'), j = raw.lastIndexOf('}');
    if (i !== -1 && j !== -1 && j > i){
      const slice = raw.slice(i, j+1);
      try { return JSON.parse(slice); } catch(_){}
    }
    return null;
  }

  form.addEventListener('submit', function(ev){
    ev.preventDefault();

    // serializza righe attività nel campo hidden
    const h = ensureRigheJsonHidden(); 
    if (h){ h.value = JSON.stringify(serializeAttivitaEdit()); }

    const fd = new FormData(form);

    fetch(form.action, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
    .then(async r => {
      const raw = await r.text();

      // content-type controllato
      const ct = (r.headers.get('content-type') || '').toLowerCase();
      let out = null;
      if (ct.includes('application/json')) {
        try { out = JSON.parse(raw); } catch(_){ out = null; }
      }
      if (!out) out = safeParseJson(raw);

      // DEBUG: stampa in console cosa ha risposto il server
      if (!out) {
        console.error('[modifica_lavoro] Risposta non JSON. HTTP', r.status, r.statusText, '\nRAW:\n', raw);
      }

      // gestione esito
      if (out && out.success === true) {
        toast('Lavoro salvato','ok');
        const closeBtn = document.querySelector('#modale-lavoro [data-modal-close]');
        if (closeBtn) closeBtn.click();
        setTimeout(()=>location.reload(), 400);
        return;
      }

      // se 401/403/302 o HTML → fallback al submit nativo
      if (!out || r.status === 401 || r.status === 403 || r.status === 302 || /^\s*</.test(raw)) {
        // Fallback sicuro: esegue il submit tradizionale (pagina si ricarica)
        console.warn('[modifica_lavoro] Fallback a submit nativo. Status:', r.status);
        form._ajaxBound = false;  // evita doppio binding al prossimo open
        form.submit();
        return;
      }

      // Errore JSON “valido”
      toast((out && out.error) ? out.error : 'Salvataggio non riuscito','err');
    })
    .catch(err => {
      console.error('[modifica_lavoro] Network/JS error:', err);
      // fallback anche su eccezioni rete/JS
      form._ajaxBound = false;
      form.submit();
    });
  });

  form._ajaxBound = true;
}


        /* ================= PATCH: Toggle rapido con “ex assegnatario” ================= */
/* ================= PATCH: Toggle rapido con “ex assegnatario” (REFRESH auto se closing) ================= */
(function buildQuickToggle(){
  const complHost = $('#attivita-complete-list');
  const complProg = $('#attivita-progress');
  if (!complHost || !complProg) return;

  const closingStates = ['completato','chiuso','annullato'];
  const jobId = jobIdForToggles || (data && data.id);

  // --- helpers ---
  function isClosingState(st){ return closingStates.includes(String(st||'').toLowerCase()); }

  function pickExAssignee(a){
    // 1) preferisci ciò che il backend ha già calcolato (da [ex_utente: ...])
    if (a && a.utente_ex) return a.utente_ex;

    // 2) fallback: dallo storico se esiste
    const h = Array.isArray(a?.assignees_history) ? a.assignees_history.filter(x => x && x.name) : [];
    if (!h.length) return null;

    // Preferisci l’ultimo intervallo “chiuso”
    const closed = h.filter(x => x.to);
    if (closed.length) return closed[closed.length - 1].name;

    // Altrimenti il penultimo (chi era prima dell’ultimo)
    if (h.length >= 2) return h[h.length - 2].name || h[h.length - 1].name || null;

    // Fallback finale
    return h[h.length - 1].name || null;
  }

  function uiSyncState(stato){
    // 1) badge sulla card
    const cardBtn = document.querySelector(`.job-card .open-edit[data-id="${jobId}"]`)
                  || document.querySelector(`.job-card .open-view[data-id="${jobId}"]`);
    const card = cardBtn ? cardBtn.closest('.job-card') : null;
    if (card) {
      const badge = card.querySelector('.badge');
      if (badge) {
        badge.textContent = stato;
        badge.className = 'badge state-' + stato;
        card.setAttribute('data-stato', stato);
      }
    }
    // 2) badge nella VIEW (se aperta)
    const mv = document.getElementById('modale-view');
    if (mv && mv.classList.contains('open')) {
      const b = document.getElementById('mv-badge-stato');
      if (b) {
        b.textContent = stato;
        b.className = 'badge state-' + stato;
      }
    }
    // 3) select stato in EDIT (senza marcare esplicito)
    const mod = document.getElementById('modale-lavoro');
    if (mod && mod.classList.contains('open')) {
      const sel = document.getElementById('edit-stato');
      const exp = document.getElementById('stato_explicit');
      if (sel) sel.value = stato;
      if (exp) exp.value = '0';
    }
  }

  function refetchAndRerender(){
    const apiUrl = new URL('../api/get_lavoro.php', window.location.href);
    apiUrl.searchParams.set('id', String(jobId));
    return fetch(apiUrl.toString(), {
      method:'GET', credentials:'same-origin',
      headers:{ 'Accept':'application/json' }, cache:'no-store'
    })
    .then(r => r.json())
    .then(fresh => {
      renderQuickList(fresh.attivita || []);
      // se la VIEW è aperta, ri-apri i dettagli aggiornati
      const mv = document.getElementById('modale-view');
      if (mv && mv.classList.contains('open')) {
        const btn = document.querySelector(`.open-view[data-id="${jobId}"]`);
        if (btn) btn.click();
      }
      return fresh;
    })
    .catch(()=>null);
  }

 function wireToggle(checkboxEl, row, idAtt, metaEl){
  // se è disabilitato, non bindare nulla
  if (checkboxEl.disabled) return;

  checkboxEl.addEventListener('change', function () {
    const checked = this.checked ? 1 : 0;

    fetch('toggle_attivita.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ id: idAtt, done: checked })
    })
    .then(res => res.json())
    .then(out => {
      if (!out.ok) {
        checkboxEl.checked = !checked;
        toast('Errore: ' + (out.err || 'imprevisto'), 'err');
        return;
      }

      // aggiorna UI riga
      if (checked) {
        row.classList.add('opacity-60');
        if (metaEl) {
          const ts = out.completato_il_readable || (out.completato_il ? new Date(out.completato_il).toLocaleString('it-IT') : '');
          metaEl.textContent = ts ? '(' + ts + ')' : '';
        }
      } else {
        row.classList.remove('opacity-60');
        if (metaEl) metaEl.textContent = '';
      }

      // progress
      const all = $$('.js-compl', $('#attivita-complete-list'));
      const done = all.filter(x => x.checked).length;
      $('#attivita-progress').textContent = all.length ? ('Completate: ' + done + '/' + all.length) : 'Nessuna attività';

      if (out.stato_lavoro) uiSyncState(out.stato_lavoro);
      toast(checked ? 'Attività completata' : 'Attività riaperta', checked ? 'ok' : 'info');
    })
    .catch(() => {
      checkboxEl.checked = !checked;
      toast('Errore di rete', 'err');
    });
  });
}


  function renderQuickList(list){
    complHost.innerHTML = '';
    let doneCount = 0;

    (list || []).forEach(ac => {
      const idAtt = parseInt(ac.id || ac.attivita_id || 0, 10);
      if (!idAtt) return;

      const comp   = !!(ac.completata || ac.done || false);
      const compIl = (ac.closed_at || ac.completato_il || '');
      const titolo = (ac.titolo || ac.nome_attivita || 'Attività');
      const cat    = (ac.categoria_nome || ac.categoria || '');
      const scad   = (ac.scadenza || ac.data_scadenza || '');
  const currUser = (ac.utente || ac.utente_nome || (ac.utente_id ? ('#' + ac.utente_id) : '—'));
const exUser   = pickExAssignee(ac);
const uidOwn   = parseInt(ac.utente_id || 0, 10);

// permessi specifici per *questa* attività
const canAny   = !!window.__CAN_COMPLETE_ANY;
const canOwn   = !!window.__CAN_COMPLETE_OWN && (uidOwn === window.__MY_ID);
const canToggleThis = canAny || canOwn;


      if (comp) doneCount++;

      const row = document.createElement('div');
      row.setAttribute('data-attivita-id', String(idAtt));
row.setAttribute('data-assegnatario-id', String(uidOwn || 0));

      row.className = 'chk-item' + (comp ? ' opacity-60' : '');
      row.innerHTML =
        '<input type="checkbox" class="js-compl" data-id="' + idAtt + '" ' + (comp ? 'checked' : '') + '>' +
        '<div style="flex:1; min-width:0;">' +
          '<div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' +
            esc(titolo) + (cat ? (' — ' + esc(cat)) : '') +
          '</div>' +
          '<div class="muted" style="font-size:12px;">' +
            '<i class="fa-regular fa-user"></i> ' + esc(currUser) +
            (exUser ? ' <span class="chip sm light">ex ' + esc(exUser) + '</span>' : '') +
            (cat ? ' · <i class="fa-solid fa-tag"></i> ' + esc(cat) : '') +
            ' · ' + (scad ? ('Scadenza: ' + esc(scad)) : 'Senza scadenza') +
            (compIl ? (' — Completata il ' + (function(v){try{return v?new Date(v).toLocaleString("it-IT"):'';}catch(_){return v||'';}})(compIl)) : '') +
          '</div>' +
        '</div>' +
        '<span class="chk-progress" id="compl-meta-' + idAtt + '"></span>';

      const meta = row.querySelector('#compl-meta-' + idAtt);
      if (meta && compIl) {
        try { meta.textContent = '(' + new Date(compIl).toLocaleString('it-IT') + ')'; } catch(_) {}
      }

      const cb = row.querySelector('.js-compl');
cb.disabled = !canToggleThis;
if (!canToggleThis) cb.title = 'Non puoi completare lavorazioni non assegnate a te';

      wireToggle(cb, row, idAtt, meta);

      complHost.appendChild(row);
    });

    complProg.textContent = list.length ? ('Completate: ' + doneCount + '/' + list.length) : 'Nessuna attività';
  }

  // --- primo render con i dati attuali
  const initialActs = Array.isArray(data.attivita) ? data.attivita : (Array.isArray(data.righe) ? data.righe : []);
  renderQuickList(initialActs);

  // --- se è già closing (o tutte complete) ⇒ refetch immediato per leggere gli ex dal backend
  const badgeStato = (document.getElementById('edit-stato')?.value) || (data && data.stato) || '';
  const allDoneLocally = initialActs.length && initialActs.every(a => !!(a.completata || a.done));
  if (isClosingState(badgeStato) || allDoneLocally) {
    refetchAndRerender(); // best-effort, non blocca
  }
})();


        /* ================= /PATCH ================= */

        openModal();
      })
      .catch(err => {
        console.error(err);
        const t=$('#liveRegion'); if(t){ t.textContent='Errore apertura lavoro'; }
        toast('Impossibile aprire il lavoro', 'err');
      });
  });
})();



/* ===================== Elimina lavoro (conferma) ===================== */
(function(){
  function wire(scope=document){
    $$('.js-del-job', scope).forEach(btn=>{
      if (btn._wired) return; btn._wired = true;
      btn.addEventListener('click', function(){
        const id    = this.getAttribute('data-id');
        const title = this.getAttribute('data-title') || 'Questo lavoro';
        if (!id) return;
        if (!confirm(`Eliminare definitivamente "${title}"?\nVerranno rimosse anche le attività collegate.`)) return;

        fetch('../api/elimina_lavoro.php', {
          method:'POST',
          headers: { 'Content-Type':'application/json', 'X-CSRF': ($('input[name="csrf"]')?.value || '') },
          credentials:'same-origin',
          body: JSON.stringify({ id })
        })
        .then(r=>Promise.all([r.clone().text(), r]))
        .then(([raw, res])=>{
          if (!res.ok){ toast(`Eliminazione non riuscita (HTTP ${res.status}).`,'err'); return; }
          let out={}; try{ out=JSON.parse(raw); }catch(_){}
          if (!out.success){ toast('Eliminazione non riuscita.','err'); return; }
          const card = btn.closest('.job-card');
          if (card){ card.style.animation='fadeDownCard .18s ease both'; setTimeout(()=>{ card.remove(); toast('Lavoro eliminato','ok'); },180); }
          else { toast('Lavoro eliminato','ok'); location.reload(); }
        })
        .catch(()=> toast('Errore di rete: impossibile eliminare il lavoro.','err'));
      });
    });
  }
  wire();
  const list = $('#allJobs');
  if (list){
    const ob = new MutationObserver(m=>m.forEach(x=>{
      if (x.addedNodes && x.addedNodes.length) wire(document);
    }));
    ob.observe(list, {childList:true});
  }
})();

/* ===================== Scorciatoie + Persistenza filtri + Tabs ===================== */
(function shortcuts(){
  const form = $('#formFiltri');
  const q    = form ? form.querySelector('input[name="q"]') : null;
  document.addEventListener('keydown', e=>{
    if (e.key==='/' && !e.metaKey && !e.ctrlKey && !e.altKey && !e.shiftKey){
      if (q){ e.preventDefault(); q.focus(); q.select(); }
    }
  });
  if (form){
    form.addEventListener('keydown', e=>{
      const tag = (e.target.tagName||'').toLowerCase();
      if (e.key==='Enter' && (tag==='input' || tag==='select')){ e.preventDefault(); form.requestSubmit(); }
    });
  }
})();

(function persistFilters(){
  const KEY='lavori.filters.v1';
  const form = $('#formFiltri'); if (!form) return;

  function serialize(){
    const fd = new FormData(form); const o={};
    fd.forEach((v,k)=>{ if (o[k]!==undefined){ if(!Array.isArray(o[k])) o[k]=[o[k]]; o[k].push(v); } else { o[k]=v; } });
    return o;
  }
  function apply(o){
    if (!o) return;
    Array.from(form.elements).forEach(el=>{
      if (!el.name || !(el.name in o)) return;
      if (el.tagName==='SELECT' || el.type==='select-multiple'){ el.value = o[el.name]; }
      else if (['date','text','hidden'].includes(el.type) || el.tagName==='INPUT'){ el.value = o[el.name]; }
    });
  }
  try{ const saved = JSON.parse(localStorage.getItem(KEY)||'null'); if(saved) apply(saved); }catch(_){}
  form.addEventListener('change', ()=>{ try{ localStorage.setItem(KEY, JSON.stringify(serialize())); }catch(_){ } });

  const btn = $('#saveDefaultFilters');
  btn && btn.addEventListener('click', ()=>{
    try{ localStorage.setItem(KEY, JSON.stringify(serialize())); toast('Filtri salvati come default','ok'); }
    catch(_){ toast('Impossibile salvare i filtri','err'); }
  });
})();

(function tabsFallback(){
  const btns = $$('.tab-btn');
  const panels = $$('.tab-panel');
  function select(id){
    btns.forEach(b=> b.setAttribute('aria-selected', b.getAttribute('aria-controls')===id ? 'true':'false'));
    panels.forEach(p=> p.classList.toggle('active', p.id===id));
  }
  btns.forEach(b=> b.addEventListener('click', ()=>select(b.getAttribute('aria-controls'))));
})();

/* ===================== Attività (render/serialize in EDIT) ===================== */
function renderAttivitaEdit(arr){
  const list = $('#attivita-list'); if (!list) return;

  function optionList(items, valueKey, labelKey, sel){
    return items.map(x=>{
      const val = x[valueKey]; const lab = x[labelKey];
      const selAttr = (sel!==null && sel!==undefined && String(val)===String(sel)) ? ' selected' : '';
      return `<option value="${esc(val)}"${selAttr}>${esc(lab)}</option>`;
    }).join('');
  }

  list.innerHTML = '';
  (arr||[]).forEach(addRow);

  function addRow(a){
    a = a || {};
    const div = document.createElement('div');
    div.className = 'r-edit';
    div.innerHTML = `
      <input type="hidden" class="r-id" value="${a.id!=null?esc(a.id):''}">
      <div>
        <label style="display:block;font-size:12px;margin-bottom:4px;">Titolo</label>
        <input type="text" class="r-titolo" value="${esc(a.titolo||'')}">
      </div>
      <div>
        <label style="display:block;font-size:12px;margin-bottom:4px;">Utente</label>
        <select class="r-utente">
          <option value="">— Nessuno —</option>
          ${ optionList((window.__UTENTI||[]),'id','label', a.utente_id) }
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;margin-bottom:4px;">Categoria</label>
        <select class="r-categoria">
          <option value="">— Nessuna —</option>
          ${ optionList((window.__CATEGORIE||[]),'id','nome', a.categoria_id) }
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;margin-bottom:4px;">Scadenza</label>
        <input type="date" class="r-scadenza" value="${a.scadenza?esc(a.scadenza):''}">
      </div>
      ${ window.__CAN_SEE_PRICE ? `
  <div>
    <label style="display:block;font-size:12px;margin-bottom:4px;">Prezzo</label>
    <input type="number" step="0.01" class="r-prezzo" value="${(a.prezzo!=null && a.prezzo!=='')?esc(a.prezzo):''}">
  </div>` : `` }

      <div>
        <label style="display:block;font-size:12px;margin-bottom:4px;">Descrizione</label>
        <textarea class="r-descr" placeholder="Note...">${esc(a.descrizione||'')}</textarea>
      </div>
      <div class="del">
        <button type="button" class="chip danger r-del"><i class="fa-regular fa-trash-can"></i></button>
      </div>
    `;
    $('.r-del', div).addEventListener('click', function(){
      const rid = $('.r-id', div).value;
      if (rid) { div.setAttribute('data-delete','1'); div.style.opacity=.6; }
      div.remove();
    });
    list.appendChild(div);
  }
  list._addRow = addRow;
}
function serializeAttivitaEdit(){
  const list = $('#attivita-list'); if (!list) return [];
  const rows = $$('.r-edit', list);
  return rows.map(div=>{
    const rid = $('.r-id', div).value.trim();
    const uid = $('.r-utente', div).value.trim();
    const cid = $('.r-categoria', div).value.trim();
    const tit = $('.r-titolo', div).value.trim();
    const dsc = $('.r-descr', div).value.trim();
    const sca = $('.r-scadenza', div).value.trim();
const prezzoEl = $('.r-prezzo', div);
const prz = prezzoEl ? prezzoEl.value.trim() : '';
    const obj = {
      id:           rid!=='' ? parseInt(rid,10) : undefined,
      utente_id:    uid!=='' ? parseInt(uid,10) : null,
      categoria_id: cid!=='' ? parseInt(cid,10) : null,
      titolo:       tit,
      descrizione:  dsc,
      scadenza:     sca!=='' ? sca : null,
prezzo: (prezzoEl && prz!=='') ? parseFloat(prz.replace(',','.')) : null
    };
    if (div.getAttribute('data-delete')==='1') obj._delete = true;
    return obj;
  });
}
function ensureRigheJsonHidden(){
  const form = $('#formModificaLavoro'); if (!form) return null;
  let hn = $('input[name="righe_json"]', form);
  if (!hn){ hn = document.createElement('input'); hn.type='hidden'; hn.name='righe_json'; hn.id='righe_json'; form.appendChild(hn); }
  return hn;
}

/* ===================== Skeleton loader ===================== */
(function(){
  const list = $('#allJobs'); if (!list) return;
  const holder = document.createElement('div'); holder.id='skeleton-holder';

  function makeCard(){
    const a = document.createElement('article'); a.className='job-card skel';
    a.innerHTML = `
      <div class="head">
        <h4 class="title"><span class="line w60"></span></h4>
        <span class="badge"><span class="line w40"></span></span>
      </div>
      <div class="body">
        <div class="meta"><span class="line w80"></span></div>
        <div class="meta"><span class="line w60"></span></div>
        <div class="meta"><span class="line w100"></span></div>
        <div class="actions"><span class="chip"> </span><span class="chip"> </span><span class="chip"> </span></div>
      </div>`;
    return a;
  }
  function show(n){ holder.innerHTML=''; for (let i=0;i<n;i++) holder.appendChild(makeCard()); if (!holder.parentNode) list.parentNode.insertBefore(holder, list.nextSibling); }
  function hide(){ if (holder.parentNode) holder.parentNode.removeChild(holder); }

  let lastBusy = null;
  const ob = new MutationObserver(function(){
    const busy = document.body.getAttribute('aria-busy') === 'true';
    if (busy === lastBusy) return;
    lastBusy = busy;
    if (busy) show(6); else hide();
  });
  ob.observe(document.body, {attributes:true, attributeFilter:['aria-busy']});
})();

/* ===================== Chip SLA aggiuntivi nella chipbar ===================== */
(function(){
  const BAR = document.querySelector('.chipbar'); if (!BAR) return;
  const KEY = 'lavori.sla.filter'; // 'late' | 'soon' | ''
  let active = sessionStorage.getItem(KEY) || '';

  function mk(label, val, icon){
    const a = document.createElement('a');
    a.href='#'; a.className='chip pill'; a.setAttribute('data-sla', val);
    a.innerHTML = `<i class="${icon}"></i> ${label} <span class="count"></span>`;
    return a;
  }
  const chipLate = mk('Scaduti','late','fa-regular fa-hourglass-end');
  const chipSoon = mk('In scadenza (≤2g)','soon','fa-regular fa-bell');
  BAR.appendChild(chipLate); BAR.appendChild(chipSoon);

  function dayDiff(dateStr){
    try{ const t=new Date(); t.setHours(0,0,0,0); const d=new Date(dateStr+'T00:00:00'); return Math.round((d - t)/(1000*60*60*24)); }
    catch(_){ return null; }
  }
  function applyFilter(val){
    active = val || '';
    sessionStorage.setItem(KEY, active);
    let cnt = 0;
    $$('.job-card[data-scadenza]').forEach(c=>{
      const sca = c.getAttribute('data-scadenza');
      const df  = sca ? dayDiff(sca) : null;
      let show = true;
      if (active==='late') show = (df!==null && df<0);
      else if (active==='soon') show = (df!==null && df>=0 && df<=2);
      c.style.display = show ? '' : 'none';
      if (show) cnt++;
    });
    chipLate.classList.toggle('active', active==='late');
    chipSoon.classList.toggle('active', active==='soon');
    const tgt = (active==='late' ? chipLate : chipSoon);
    const other= (active==='late' ? chipSoon : chipLate);
    if (active){ $('.count', tgt).textContent = String(cnt); $('.count', other).textContent=''; }
    else { $('.count', chipLate).textContent=''; $('.count', chipSoon).textContent=''; }
  }
  function toggle(val){ applyFilter(active===val ? '' : val); }
  chipLate.addEventListener('click', e=>{ e.preventDefault(); toggle('late'); });
  chipSoon.addEventListener('click', e=>{ e.preventDefault(); toggle('soon'); });
  if (active) applyFilter(active);

  const list = $('#allJobs');
  if (list){
    const ob = new MutationObserver(function(){
      if (!active) return;
      clearTimeout(ob._t); ob._t = setTimeout(()=>applyFilter(active), 50);
    });
    ob.observe(list, {childList:true});
  }
})();

/* ===================== Inline edit (Scadenza) sulle card ===================== */
(function(){
  function insertButtons(card){
    if (card._inlineEditWired) return; card._inlineEditWired = true;
    const actions = $('.actions', card) || card;
    const bar = document.createElement('div'); bar.className='chipbar inlineedit'; bar.style.marginTop='6px';

    const bSca = document.createElement('button'); bSca.type='button'; bSca.className='chip light'; bSca.textContent='Modifica scadenza';
    bar.appendChild(bSca);

    actions.appendChild(bar);

    function jobId(){ return card.getAttribute('data-id') || ($('.open-edit', card)?.getAttribute('data-id')) || ($('.open-view', card)?.getAttribute('data-id')); }

    bSca.addEventListener('click', function(){
      const id = jobId(); if(!id) return;
      const cur = card.getAttribute('data-scadenza') || '';
      const v = prompt('Nuova scadenza (YYYY-MM-DD):', cur || '');
      if (v===null) return;

      const fd = new FormData();
      fd.append('id', id); fd.append('scadenza', v.trim());
      fd.append('csrf', $('input[name="csrf"]')?.value || '');

      fetch('../api/patch_lavoro.php', { method:'POST', body:fd, credentials:'same-origin' })
        .then(r=>r.json().catch(()=>({success:false,error:'Risposta non valida'})))
        .then(out=>{
          if (!out.success){ alert(out.error||'Salvataggio fallito'); return; }
          const newSca = out.updated?.scadenza || null;
          if (newSca!==null){
            card.setAttribute('data-scadenza', newSca);
            if (typeof window.__paintSLA==='function') window.__paintSLA(card);
            // best effort update testo
            $$('.meta', card).forEach(m=>{
              if (/Scadenza:\s*/i.test(m.textContent||'')){
                m.textContent = (m.textContent||'').replace(/(Scadenza:\s*)([0-9/]+)/i, '$1'+newSca);
              }
            });
          }
        })
        .catch(()=>alert('Errore di rete'));
    });
  }
  $$('.job-card').forEach(insertButtons);
  const list = $('#allJobs');
  if (list){
    const ob = new MutationObserver(m=>m.forEach(x=>{
      x.addedNodes && x.addedNodes.forEach(n=>{
        if (n.nodeType===1 && n.classList.contains('job-card')) insertButtons(n);
      });
    }));
    ob.observe(list,{childList:true});
  }
})();

/* ===================== Quick chip cambio stato ===================== */
(function(){
  const cycle = ['aperto','in_lavorazione','pausa','completato'];
  const nextState = cur => { const i = cycle.indexOf((cur||'').trim().toLowerCase()); return cycle[(i>=0 ? (i+1) : 0) % cycle.length]; };

  function ensure(card){
    if (card._quickStateWired) return; card._quickStateWired = true;
    const actions = $('.actions', card) || card;
    const bar = document.createElement('div'); bar.className='chipbar quickstate'; bar.style.marginTop='8px';

    const btn = document.createElement('button'); btn.type='button'; btn.className='chip light js-state-quick'; btn.textContent='Cambia stato';
    bar.appendChild(btn); actions.appendChild(bar);

    btn.addEventListener('click', function(){
      const id = card.getAttribute('data-id') || ($('.open-edit', card)?.getAttribute('data-id') || $('.open-view', card)?.getAttribute('data-id'));
      if (!id) return;
      const badge  = $('.badge', card);
      const cur    = (badge?.textContent || card.getAttribute('data-stato') || 'aperto').trim().toLowerCase();
      const next   = nextState(cur);
      const closing= ['completato','chiuso','annullato'].includes(next);
      if (closing && !confirm(`Impostare lo stato su "${next}"?\nLe assegnazioni verranno svuotate.`)) return;

      const fd = new FormData();
      fd.append('id', id); fd.append('stato', next); fd.append('csrf', $('input[name="csrf"]')?.value || '');
      fetch('../api/update_stato.php', { method:'POST', body:fd, credentials:'same-origin' })
        .then(r=>r.json().catch(()=>({success:false,error:'Risposta non valida'})))
        .then(out=>{
          if (!out.success){ alert(out.error||'Aggiornamento stato fallito'); return; }
          const st = out.stato || next;
          if (badge){ badge.className='badge state-'+st; badge.textContent = st; }
          card.setAttribute('data-stato', st);
        })
        .catch(()=>alert('Errore di rete'));
    });
  }

  $$('.job-card').forEach(ensure);
  const list = $('#allJobs');
  if (list){
    const ob = new MutationObserver(m=>m.forEach(x=>{
      x.addedNodes && x.addedNodes.forEach(n=>{
        if (n.nodeType===1 && n.classList.contains('job-card')) ensure(n);
      });
    }));
    ob.observe(list,{childList:true});
  }
})();

/*++++++++++++++++++++++*/
/* ===================== Toggle vista: Cards <-> Tabella ===================== */
(function(){
  const btn   = document.getElementById('toggleView');
  const cards = document.getElementById('allJobs');
  const table = document.getElementById('tableJobs');
  if (!btn || !cards || !table) return;

  const KEY = 'lavori.view.mode'; // 'cards' | 'table'

  function setIconAndText(isTable){
    const icon = isTable ? 'fa-solid fa-grid-2' : 'fa-solid fa-table-list';
    btn.innerHTML = `<i class="${icon}"></i> ${isTable ? 'Vista cards' : 'Vista tabella'}`;
    btn.setAttribute('aria-pressed', String(isTable));
  }

  function apply(mode){
    const isTable = (mode === 'table');
    cards.style.display = isTable ? 'none' : '';
    table.style.display = isTable ? '' : 'none';
    setIconAndText(isTable);

    // ricalcola SLA/stagger quando torni alle cards
    if (!isTable) {
      setTimeout(()=>{
        typeof window.__paintSLA==='function' && window.__paintSLA(cards);
        typeof window.__applyStagger==='function' && window.__applyStagger(cards);
      }, 0);
    }
  }

  // stato iniziale (persistenza)
  let mode = (localStorage.getItem(KEY) || 'cards');
  apply(mode);

  btn.addEventListener('click', (e)=>{
    e.preventDefault();
    mode = (mode === 'table') ? 'cards' : 'table';
    localStorage.setItem(KEY, mode);
    apply(mode);
  });
})();

/* NUOVO_LAVORO*/
/* =========================================================================
 *  NUOVO LAVORO — JS
 *  - Chips categorie live
 *  - Cartelle/tag con hidden JSON
 *  - Toggle nuova tipologia evento (macro)
 *  - Righe attività: add/remove + toggle campi “nuova categoria/tipologia”
 * ========================================================================= */

(function categorieChips(){
  var sel = document.getElementById('categorie');
  var chips = document.getElementById('catChips');
  if (!sel || !chips) return;

  function sync(){
    var selected = Array.from(sel.options).filter(o => o.selected);
    chips.innerHTML = '';
    selected.forEach(function(opt){
      var span = document.createElement('span');
      span.className = 'cat-chip';
      span.innerHTML = '<i class="fa-solid fa-tag"></i> '+opt.text+' <span class="x" title="Rimuovi" aria-label="Rimuovi categoria">&times;</span>';
      span.querySelector('.x').addEventListener('click', function(){
        opt.selected = false;
        var ev = new Event('change', {bubbles:true});
        sel.dispatchEvent(ev);
      });
      chips.appendChild(span);
    });
  }
  sel.addEventListener('change', sync);
  sync();
})();

(function cartelleTags(){
  var tags = document.getElementById('tags');
  var input = document.getElementById('tagInput');
  var hidden = document.getElementById('cartelle_json');
  if (!tags || !input || !hidden) return;

  var arr = [];
  function render(){
    tags.innerHTML = '';
    arr.forEach(function(t, i){
      var s = document.createElement('span'); s.className='tag';
      s.innerHTML = t+' <button type="button" class="tag-x" aria-label="Rimuovi">&times;</button>';
      s.querySelector('.tag-x').addEventListener('click', function(){ arr.splice(i,1); render(); });
      tags.appendChild(s);
    });
    hidden.value = JSON.stringify(arr);
  }
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter'){
      e.preventDefault();
      var v = (input.value||'').trim();
      if (v && !arr.includes(v)) { arr.push(v); render(); }
      input.value='';
    }
  });
  render();
})();

(function macroEventoToggle(){
  var evSel = document.getElementById('evento_tipo_sel');
  var evWrap = document.getElementById('evento_new_wrap');
  if (!evSel || !evWrap) return;
  function sync(){ evWrap.style.display = (evSel.value==='__new__') ? 'block' : 'none'; }
  evSel.addEventListener('change', sync);
  sync();
})();

(function righeAttivita(){
  var wrap = document.getElementById('righe');
  var add  = document.getElementById('addRow');
  if (!wrap || !add) return;

  function bindRow(row){
    var del = row.querySelector('.js-del');
    if (del) del.addEventListener('click', function(){
      if (wrap.children.length > 1) row.remove();
    });

    var tipoSel = row.querySelector('select[name="righe_evento_tipo_sel[]"]');
    var tipoNew = row.querySelector('input[name="righe_evento_tipo_new[]"]');
    if (tipoSel && tipoNew){
      tipoSel.addEventListener('change', function(){
        tipoNew.style.display = (tipoSel.value==='__new__') ? 'block' : 'none';
      });
    }

    var catSel = row.querySelector('select[name="righe_categoria_id[]"]');
    var catNew = row.querySelector('input[name="righe_categoria_new[]"]');
    if (catSel && catNew){
      catSel.addEventListener('change', function(){
        catNew.style.display = (catSel.value==='__new__') ? 'block' : 'none';
      });
    }
  }

  // bind iniziale
  Array.from(wrap.children).forEach(bindRow);

  // aggiungi
  add.addEventListener('click', function(){
    var last  = wrap.lastElementChild;
    var clone = last.cloneNode(true);

    // pulisci valori (mantieni solo il color picker)
    Array.from(clone.querySelectorAll('input, select, textarea')).forEach(function(el){
      if (el.name && el.name.indexOf('righe_evento_color[]') !== -1) return;
      if (el.tagName === 'SELECT') el.selectedIndex = 0;
      else el.value = '';
    });

    // nascondi eventuali input “nuovi”
    var tipoNew = clone.querySelector('input[name="righe_evento_tipo_new[]"]');
    if (tipoNew) tipoNew.style.display = 'none';
    var catNew = clone.querySelector('input[name="righe_categoria_new[]"]');
    if (catNew) catNew.style.display = 'none';

    wrap.appendChild(clone);
    bindRow(clone);
  });
})();
