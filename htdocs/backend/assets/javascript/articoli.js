// =====================================================================
// articoli.js  —  unico file JS per sezione Articoli
// - Pagina "nuovo_articolo.php": editor Quill + cover + categoria
// - Pagina "index_articoli.php": modale edit/view + permessi + azioni
// =====================================================================

(function(){
  // -------------------------------------------------------------------
  // Helpers generici
  // -------------------------------------------------------------------
  function $(sel, root=document){ return root.querySelector(sel); }
  function $all(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }

  function normImg(p){
    if(!p) return '';
    p = String(p).trim();
    if (/^https?:\/\//i.test(p) || p.startsWith('//')) return p;
    if (p.startsWith('/backend/uploads/')) return p;
    if (p.startsWith('backend/uploads/'))  return '/'+p;
    if (p.startsWith('uploads/'))          return '/backend/'+p;
    if (!p.includes('/'))                  return '/backend/uploads/'+p;
    if (p[0] !== '/')                      return '/'+p;
    return p;
  }
  function toApiImgValue(src){
    if(!src) return '';
    try{ if (/^https?:\/\//i.test(src)) src = new URL(src).pathname; }catch(_){}
    src = src.replace(/^\/+/, '');
    if (src.startsWith('backend/')) src = src.substring('backend/'.length);
    return src;
  }

  // -------------------------------------------------------------------
  // SINOSSI toggle (riutilizzabile)
  // -------------------------------------------------------------------
  function initSinossiToggles(root=document){
    $all('[data-toggle-sinossi]', root).forEach(btn=>{
      const card = btn.closest('.book-card'); if(!card) return;
      const sn = card.querySelector('.sinossi'); if(!sn){ btn.remove(); return; }

      const fullText = sn.getAttribute('data-full') || sn.textContent || '';
      if (fullText.trim().length < 180){ btn.remove(); return; }

      const cs = getComputedStyle(sn);
      const lineH = parseFloat(cs.lineHeight) || 18;
      const collapsedH = lineH * 3;

      sn.style.maxHeight = collapsedH + 'px';
      btn.textContent = 'Mostra +';

      btn.addEventListener('click', ()=>{
        const isOpen = sn.classList.contains('is-expanded');
        if (isOpen){
          sn.style.maxHeight = sn.scrollHeight + 'px';
          requestAnimationFrame(()=>{
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

      sn.addEventListener('transitionend', (ev)=>{
        if (ev.propertyName !== 'max-height') return;
        if (sn.classList.contains('is-expanded')) sn.style.maxHeight = 'none';
      });

      const ro = new ResizeObserver(()=>{
        if (sn.classList.contains('is-expanded')) return;
        sn.style.maxHeight = collapsedH + 'px';
      });
      ro.observe(sn);
    });
  }

  // -------------------------------------------------------------------
  // PAGINA: nuovo_articolo.php  (creazione articolo)
  // -------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function(){
    var editorEl = document.getElementById('editor');      // editor principale (nuovo_articolo.php)
    var hidden   = document.getElementById('hiddenContent');// hidden HTML
    var formCreate = document.getElementById('articleForm');

    // Editor Quill (solo se presente la pagina di creazione)
    if (editorEl && window.Quill) {
      var quill = new Quill(editorEl, {
        theme: 'snow',
        placeholder: 'Scrivi qui il contenuto dell’articolo…',
        modules: {
          toolbar: [
            [{ header: [1,2,3,false] }],
            ['bold','italic','underline','strike'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'align': [] }],
            ['link','blockquote','code-block','image','clean']
          ]
        }
      });
      if (hidden && hidden.value) { try { quill.root.innerHTML = hidden.value; } catch(e){} }
      if (formCreate) {
        formCreate.addEventListener('submit', function(){
          if (hidden) hidden.value = quill.root.innerHTML.trim();
        });
      }
    }

    // Categoria: chip + slidebox
    (function(){
      var sel   = document.getElementById('category');
      var chips = document.getElementById('catChips');
      var box   = document.getElementById('newCatBox');
      var newI  = document.getElementById('newCategory');
      if (!sel || !chips) return;

      function syncUI(){
        chips.innerHTML = '';
        var v   = sel.value;
        var opt = sel.options[sel.selectedIndex];

        if (v === '__new__') {
          if (box) box.classList.add('show');
          if (newI) { newI.disabled = false; newI.required = true; }
          var label = (newI && newI.value.trim()) || 'Nuova categoria';
          var span  = document.createElement('span');
          span.className = 'cat-chip';
          span.innerHTML = '<i class="fa-solid fa-tag"></i> ' + label;
          chips.appendChild(span);
        } else {
          if (box) box.classList.remove('show');
          if (newI) { newI.disabled = true; newI.required = false; newI.value = ''; }
          if (v !== '' && opt) {
            var chip = document.createElement('span');
            chip.className = 'cat-chip';
            chip.innerHTML = '<i class="fa-solid fa-tag"></i> ' + opt.text;
            chips.appendChild(chip);
          }
        }
      }
      sel.addEventListener('change', syncUI);
      if (newI) newI.addEventListener('input', syncUI);
      syncUI();
    })();

    // Cover preview (pagina nuovo_articolo.php)
    (function(){
      var input = document.getElementById('cover');
      var img   = document.getElementById('imgPreview');
      var btnR  = document.getElementById('btnRemoveCover');
      if (!input || !img || !btnR) return;

      input.addEventListener('change', function(e){
        var f = e.target.files && e.target.files[0];
        if (!f){ img.src=''; img.style.display='none'; return; }
        var url = URL.createObjectURL(f);
        img.src = url; img.style.display = 'block';
      });
      btnR.addEventListener('click', function(){
        input.value = '';
        img.src = ''; img.style.display='none';
      });
    })();

    // Inizializza toggle sinossi dove serve
    initSinossiToggles(document);
  });

  // -------------------------------------------------------------------
  // PAGINA: index_articoli.php  (archivio + modale)
  // -------------------------------------------------------------------
  (function(){
const AUTH = (window.__BLOG_AUTH || {}); // fallback legacy se esistesse ancora
const ds = (document.body && document.body.dataset) ? document.body.dataset : {};
const CAN_VIEW = (ds.blogCanView === '1') || (AUTH.canView === true);
const CAN_EDIT = (ds.blogCanEdit === '1') || (AUTH.canEdit === true);

    const modal = document.getElementById('modale-articolo');
    const form  = document.getElementById('formModificaArticolo');
    if (!modal || !form) return;   // questa sezione parte solo nell'index

    // Quill per la MODALE dell'index (indipendente dall'editor della pagina nuovo_articolo)
    let qIndex = null;
    function ensureQuillIndex(){
      if (qIndex || !window.Quill) return qIndex;
      const area = $('#contenutoEditor', modal);
      if (!area) return null;
      qIndex = new Quill(area, {
        theme: 'snow',
        placeholder: 'Scrivi il contenuto dell’articolo…',
        modules: {
          toolbar: [
            [{ header: [1,2,3,false] }],
            ['bold','italic','underline','strike'],
            [{'list':'ordered'},{'list':'bullet'}],
            [{'indent':'-1'},{'indent':'+1'}],
            ['link','blockquote','code-block'],
            [{ 'align': [] }],
            [{ 'color': [] }, { 'background': [] }],
            ['clean']
          ]
        }
      });
      return qIndex;
    }
    function setQuillContentIndex(data){
      const q = ensureQuillIndex();
      if (!q) return;
      const raw = (data && typeof data.contenuto === 'string') ? data.contenuto.trim() : '';
      if (!raw){ q.setContents([]); return; }
      try {
        const delta = JSON.parse(raw);
        if (delta && typeof delta === 'object' && delta.ops) { q.setContents(delta); return; }
      } catch(_){}
      q.clipboard.dangerouslyPasteHTML(raw);
    }

    // Selettori scoped modale
    const sheet       = $('.sheet', modal);
    const imgEl       = $('#imgEsistenteArticolo', modal);
    const selCat      = $('#categoriaArticolo', modal);
    const catChips    = $('#catChips', modal);
    const coverInput  = $('#coverInputArt', modal);
    const btnRemove   = $('#btnRemoveCoverArt', modal);
    const newCatInput = $('#nuovaCategoria', modal);
    const newCatHidden= $('#newCategoryArtHidden', modal);
    const excerptTA   = $('#excerptArticolo', modal);
    const saveBtn     = $('#btnSaveArt', modal);

    let lastOpener = null;

    // Focus trap + open/close
    function trapFocus(e){
      if (!modal.classList.contains('open')) return;
      const focusables = $all('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])', modal)
        .filter(el=>!el.hasAttribute('disabled'));
      if (!focusables.length) return;
      const first = focusables[0], last = focusables[focusables.length - 1];
      if (e.key === 'Tab'){
        if (e.shiftKey && document.activeElement === first){ e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last){ e.preventDefault(); first.focus(); }
      }
    }
    function apri(openerBtn){
      lastOpener = openerBtn || null;
      modal.classList.add('open'); modal.classList.remove('closing');
      document.body.classList.add('modal-open');
      modal.setAttribute('aria-hidden','false');
      setTimeout(()=>{ form.querySelector('input, select, textarea, button')?.focus(); }, 10);
    }
    function chiudi(){
      modal.classList.add('closing');
      modal.addEventListener('transitionend', function onEnd(ev){
        if (ev.target !== sheet) return;
        modal.removeEventListener('transitionend', onEnd);
        modal.classList.remove('open','closing');
        document.body.classList.remove('modal-open');
        modal.setAttribute('aria-hidden','true');
        if (lastOpener) { try{ lastOpener.focus(); }catch(_){ } }
      }, { once:true });
    }
    modal.addEventListener('click', (e)=>{ if (e.target === modal) chiudi(); });
    document.addEventListener('click', (e)=>{
      const btn = e.target.closest('[data-modal-close]');
      if (btn && modal.classList.contains('open')) { e.preventDefault(); chiudi(); }
    });
    document.addEventListener('keydown', (e)=>{
      if (e.key === 'Escape' && modal.classList.contains('open')) chiudi();
      if (e.key === 'Tab'    && modal.classList.contains('open')) trapFocus(e);
    });

    // Categoria: chip (singola) + “aggiungi nuova” solo UI
    function syncCategoryChips(){
      if (!selCat || !catChips) return;
      const opt = selCat.selectedOptions[0];
      catChips.innerHTML = '';
      if (!opt) return;
      const chip = document.createElement('span');
      chip.className = 'cat-chip';
      chip.innerHTML = `<i class="fa-solid fa-tag"></i> ${opt.text} <span class="x" title="Rimuovi">&times;</span>`;
      chip.querySelector('.x').addEventListener('click', ()=>{
        selCat.value = '';
        syncCategoryChips();
      });
      catChips.appendChild(chip);
    }
    const btnAddCat = $('#btnAddCatArt', modal);
    selCat && selCat.addEventListener('change', syncCategoryChips);
    btnAddCat && btnAddCat.addEventListener('click', ()=>{
      const name = (newCatInput?.value || '').trim();
      if (!name) return;
      const dup = Array.from(selCat.options).find(o => o.text.trim().toLowerCase() === name.toLowerCase());
      if (dup){
        selCat.value = dup.value;
        newCatHidden && (newCatHidden.value = '');
        newCatInput  && (newCatInput.value  = '');
        selCat.dispatchEvent(new Event('change',{bubbles:true}));
        return;
      }
      const opt = document.createElement('option');
      opt.value = '__new__:'+name;
      opt.textContent = name + ' (nuova)';
      opt.selected = true;
      selCat.appendChild(opt);
      newCatHidden && (newCatHidden.value = name);
      newCatInput  && (newCatInput.value  = '');
      selCat.dispatchEvent(new Event('change',{bubbles:true}));
    });

    // READ-ONLY per chi non può editare
    function applyReadOnly(ro){
      // Disabilita tutto eccetto chiudi
      $all('input, select, textarea, button', form).forEach(el=>{
        if (el.hasAttribute('data-modal-close')) return;
        if (el === saveBtn) { el.style.display = ro ? 'none' : ''; el.disabled = ro; return; }
        if (el.tagName === 'BUTTON') { el.disabled = ro; return; }
        el.disabled = ro;
        if (ro && el.tagName === 'TEXTAREA') el.readOnly = true;
      });
      // Strumenti cover
      const tools = $('.cover-tools', modal);
      if (tools) tools.style.display = ro ? 'none' : '';
      // Badge titolo
      const mt = document.getElementById('modalArticoloTitolo');
      if (mt){
        const base = `<i class="fa-regular fa-pen-to-square"></i> Modifica articolo`;
        mt.innerHTML = ro ? `${base} <span class="chip sm" style="margin-left:8px; background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3;">Sola lettura</span>` : base;
      }
      // Disabilita toolbar Quill se RO
      const q = ensureQuillIndex();
      if (q){
        const tb = modal.querySelector('.ql-toolbar');
        if (tb) tb.style.display = ro ? 'none' : '';
        q.enable(!ro);
      }
    }

    // Apertura modale (view/edit)
    $all('.open-edit').forEach(btn=>{
      btn.addEventListener('click', async (e)=>{
        e.preventDefault();
        if (!CAN_VIEW) return; // safety: se non può nemmeno vedere (comunque la pagina lo blocca server side)

        const id = btn.getAttribute('data-id'); if(!id) return;

        try{
          const res = await fetch(`../api/get_contenuto.php?tipo=articolo&id=${encodeURIComponent(id)}`, { credentials:'same-origin' });
          if(!res.ok) throw new Error('HTTP '+res.status);
          const data = await res.json();

          // Campi base
          form.elements['id'].value    = data.id || '';
          form.elements['title'].value = data.title || data.titolo || '';
          if (excerptTA) excerptTA.value = (data.excerpt || data.descrizione || data.contenuto || '');
          if (form.elements['date']) form.elements['date'].value = (data.date || data.data_pubblicazione || data.created_at || '').slice(0,10);
          if (form.elements['link']) form.elements['link'].value = data.link || '';

          // Categorie
          if (selCat){
            selCat.innerHTML = '';
            if (Array.isArray(data.categorie)) {
              data.categorie.forEach(c=>{
                const opt = document.createElement('option');
                opt.value = String(c.id);
                opt.textContent = c.nome;
                selCat.appendChild(opt);
              });
            }
            const currCat = (data.category_id || data.category || '').toString();
            let matched = false;
            Array.from(selCat.options).forEach(opt=>{
              if (opt.value === currCat || opt.text === currCat){ selCat.value = opt.value; matched = true; }
            });
            if (!matched) selCat.value = '';
            newCatHidden && (newCatHidden.value = '');
            newCatInput  && (newCatInput.value  = '');
            syncCategoryChips();
          }

          // Copertina
          const src = normImg(data.cover || data.copertina || data.immagine || '');
          if (imgEl){
            if (src){
              imgEl.src = src; imgEl.style.display = 'block';
              if (form.elements['existing_img']) form.elements['existing_img'].value = toApiImgValue(src);
            } else {
              imgEl.style.display = 'none';
              if (form.elements['existing_img']) form.elements['existing_img'].value = '';
            }
          }
          if (coverInput) coverInput.value = '';

          // Contenuto nell'editor Quill
          setQuillContentIndex(data);

          // RO/EDIT in base ai permessi
          applyReadOnly(!CAN_EDIT);

          apri(btn);
        }catch(err){
          console.error(err);
          alert('Impossibile aprire l’articolo.');
        }
      });
    });

    // Copertina: anteprima + rimozione nel modale
    if (coverInput && imgEl){
      coverInput.addEventListener('change', (e)=>{
        const f = e.target.files && e.target.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        imgEl.src = url; imgEl.style.display = 'block';
      });
    }
    if (btnRemove && imgEl){
      btnRemove.addEventListener('click', ()=>{
        if (coverInput) coverInput.value = '';
        imgEl.src = ''; imgEl.style.display = 'none';
        if (form.elements['existing_img']) form.elements['existing_img'].value = '';
      });
    }

    // Submit salva modifiche (bloccato se !CAN_EDIT)
    form.addEventListener('submit', async (e)=>{
      if (!CAN_EDIT) { e.preventDefault(); return; }
      e.preventDefault();

      // Se presente l'editor Quill della modale, metti il Delta nel hidden
      const q = ensureQuillIndex();
      const hiddenDelta = document.getElementById('contenutoHidden');
      if (q && hiddenDelta) hiddenDelta.value = JSON.stringify(q.getContents());

      const fd = new FormData(form);
      try{
        const res = await fetch('../api/modifica_contenuto.php', { method:'POST', body: fd, credentials:'same-origin' });
        const out = await res.json().catch(()=>({success:false}));
        if (!res.ok || !out.success) throw new Error('Save failed');
        location.reload();
      }catch(err){
        console.error(err);
        alert('Salvataggio non riuscito.');
      }
    });

    // Elimina (mostrato solo se CAN_EDIT)
    $all('.del-article').forEach(btn=>{
      if (!CAN_EDIT) { btn.remove(); return; }
      btn.addEventListener('click', async ()=>{
        const id = btn.getAttribute('data-id');
        const title = btn.getAttribute('data-title') || 'articolo';
        if (!id) return;
        if (!confirm('Eliminare "'+title+'"?')) return;
        try{
          const res = await fetch(`../api/elimina_contenuto.php?tipo=articolo&id=${encodeURIComponent(id)}`, { credentials:'same-origin' });
          const out = await res.json().catch(()=>({success:false}));
          if (!res.ok || !out.success) throw new Error('Delete failed');
          location.reload();
        }catch(err){
          console.error(err);
          alert('Eliminazione non riuscita.');
        }
      });
    });

    // “Mostra altri” (+3 alla volta)
    (function(){
      const container = document.getElementById('allArticles');
      const btn = document.getElementById('altro');
      if (!container || !btn) return;
      const take = 3;

      $all('[data-archivio]', container).forEach((el,i)=>{
        if (i >= 3) el.setAttribute('data-hidden','1'); else el.removeAttribute('data-hidden');
        el.hidden = false; el.style.removeProperty?.('display');
      });

      const hiddenList = () => $all('[data-archivio][data-hidden="1"]', container);
      function reveal(el){
        el.removeAttribute('data-hidden'); el.hidden = false; el.style.removeProperty('display');
        el.animate([{opacity:0, transform:'translateY(6px)'},{opacity:1, transform:'none'}], {duration:160, easing:'ease-out'});
      }
      function updateLabel(){
        const rem = hiddenList().length;
        if (rem <= 0) { btn.closest('div')?.remove(); return; }
        const next = Math.min(rem, take);
        btn.innerHTML = `<i class="fa-solid fa-angles-down"></i> Mostra altri (${next}/${rem})`;
      }
      btn.addEventListener('click', ()=>{
        hiddenList().slice(0, take).forEach(reveal);
        updateLabel();
      });
      updateLabel();
    })();

    // Toggle sinossi
    initSinossiToggles(document);
  })();
})();

/* ===== Quill: setup editor modale articoli ===== */
(function(){
  let quill;

 
  function setContent(data){
    const q = ensureQuill();
    if (!q) return;
    const raw = (data && typeof data.contenuto === 'string') ? data.contenuto.trim() : '';
    if (!raw){ q.setContents([]); return; }
    try {
      const delta = JSON.parse(raw);
      if (delta && typeof delta === 'object' && delta.ops) { q.setContents(delta); return; }
    } catch(e) {}
    q.clipboard.dangerouslyPasteHTML(raw);
  }

  function getDeltaAsJSON(){
    const q = ensureQuill();
    if (!q) return '[]';
    const delta = q.getContents();
    return JSON.stringify(delta);
  }

  document.getElementById('formModificaArticolo')?.addEventListener('submit', function(){
    const hidden = document.getElementById('contenutoHidden');
    if (hidden) hidden.value = getDeltaAsJSON();
  });

  window.ArticoliEditor = { ensureQuill, setContent, getDeltaAsJSON };
})();

