// assets/javascript/clienti.js
// Logica comune Clienti: modale modifica (index) + piccole utilità form (nuovo).

(function () {
  'use strict';

  /* ===========================
   *   SEZIONE: LISTA CLIENTI
   * =========================== */
  const modal = document.getElementById('modale-cliente');
  const sheet = modal ? modal.querySelector('.sheet') : null;
  const formEdit  = document.getElementById('formModificaCliente');
  let lastOpener = null;

  function trapFocus(e){
    if (!modal || !modal.classList.contains('open')) return;
    const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    const list = Array.from(focusables).filter(el => !el.hasAttribute('disabled'));
    if (!list.length) return;
    const first = list[0], last = list[list.length - 1];
    if (e.key === 'Tab'){
      if (e.shiftKey && document.activeElement === first){ e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last){ e.preventDefault(); first.focus(); }
    }
  }

  function apri(openerBtn){
    if (!modal) return;
    lastOpener = openerBtn || null;
    modal.classList.add('open'); modal.classList.remove('closing');
    document.body.classList.add('modal-open');
    modal.setAttribute('aria-hidden','false');
    setTimeout(()=>{ formEdit?.querySelector('input, select, textarea, button')?.focus(); }, 10);
  }

  function chiudi(){
    if (!modal || !sheet) return;
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

  // backdrop close / close buttons / esc+tab
  modal?.addEventListener('click', (e)=>{ if (e.target === modal) chiudi(); });
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-modal-close]');
    if (btn && modal && modal.classList.contains('open')) { e.preventDefault(); chiudi(); }
  });
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape' && modal && modal.classList.contains('open')) chiudi();
    if (e.key === 'Tab'    && modal && modal.classList.contains('open')) trapFocus(e);
  });

  // Open-edit buttons -> fetch cliente (solo in index)
  document.querySelectorAll('.open-edit').forEach(btn=>{
    btn.addEventListener('click', async (e)=>{
      e.preventDefault();
      if (!formEdit) return;
      const id = btn.getAttribute('data-id');
      if(!id) return;
      try{
        const res = await fetch(`../api/get_contenuto.php?tipo=cliente&id=${encodeURIComponent(id)}`, { credentials:'same-origin' });
        if(!res.ok) throw new Error('HTTP '+res.status);
        const data = await res.json();

        formEdit.id.value             = data.id || id;
        formEdit.nome.value           = data.nome || '';
        formEdit.referente_1.value    = data.referente_1 || '';
        formEdit.referente_2.value    = data.referente_2 || '';
        formEdit.telefono.value       = data.telefono || '';
        formEdit.email.value          = data.email || '';
        formEdit.partita_iva.value    = data.partita_iva || '';
        formEdit.codice_univoco.value = data.codice_univoco || '';
        formEdit.indirizzo.value      = data.indirizzo || '';
        formEdit.cap.value            = data.cap || '';
        formEdit.citta.value          = data.citta || data['città'] || '';

        apri(btn);
      }catch(err){
        console.error(err);
        alert('Impossibile aprire il cliente.');
      }
    });
  });

  // Submit modifica -> API (solo in index)
  formEdit?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(formEdit);
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

  /* ===============================
   *   SEZIONE: NUOVO CLIENTE
   * =============================== */
  const formNew = document.getElementById('formNuovoCliente');
  if (formNew) {
    // evita doppi submit
    formNew.addEventListener('submit', (e)=>{
      const btn = formNew.querySelector('button[type="submit"]');
      btn?.setAttribute('disabled','disabled');
      btn?.classList.add('is-loading');
      // lascia procedere il POST tradizionale (server farà redirect)
    });

    // piccola validazione lato client per email
  const email = formNew.querySelector('input[name="email"]');
email?.addEventListener('blur', () => {
  email.value = email.value.trim();
  if (!email.checkValidity()) {
    email.setCustomValidity('Inserisci un indirizzo email valido');
  } else {
    email.setCustomValidity('');
  }
});


  window.addEventListener('pagehide', ()=>{
    if (modal?.classList.contains('open')) modal.classList.remove('open','closing');
  });

})();

