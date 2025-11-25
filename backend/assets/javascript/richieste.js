// assets/javascript/richieste.js
// Tutta la logica JS per la pagina Richieste (modale + salvataggio).

(function(){
  'use strict';

  // ==== Boot: recupero dati dal DOM (no inline JS necessario) ====
  // Aggiungi sulla <body>:
  //   data-csrf="<?= e($csrf) ?>"
  //   data-assignable-users='<?= e(json_encode($assignableUsers, JSON_UNESCAPED_UNICODE)) ?>'
  const bootBody = document.body || document.documentElement;
  const csrf = bootBody?.dataset?.csrf || '';
  let assignableUsers = [];
  try {
    assignableUsers = JSON.parse(bootBody?.dataset?.assignableUsers || '[]') || [];
  } catch (_) { assignableUsers = []; }

  // ==== Utils ====
  function esc(s){ return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }
  function fmtDt(s){ return s ? String(s).replace('T',' ').replace('.000Z','') : '—'; }
  function whoStr(item){
    const email = (item.email||'').trim();
    if(item.tipo==='privati'){
      const full = [item.nome||'', item.cognome||''].join(' ').trim();
      return full ? `${full} &lt;${esc(email)}&gt;` : esc(email);
    } else {
      const label = (item.rgs||item.settore||'').trim();
      return label ? `${esc(label)} &lt;${esc(email)}&gt;` : esc(email);
    }
  }
  function renderAssigneeSelect(current){
    const opts = ['<option value="">— Nessuno —</option>'];
    (assignableUsers||[]).forEach(n=>{
      const sel = (String(n)===String(current)) ? ' selected' : '';
      opts.push(`<option value="${esc(n)}"${sel}>${esc(n)}</option>`);
    });
    return `<select name="assigned_to" class="chip" style="border-radius:10px;">${opts.join('')}</select>`;
  }

  function renderModalBody(item){
    const isClosed = (item.status==='closed');
    return `
      <div class="panel compact" style="margin:12px;">
        <div class="two-col">
          <div>
            <div class="muted">Tipo</div>
            <div><b>${item.tipo==='privati'?'Privati':'Aziende'}</b></div>
          </div>
          <div>
            <div class="muted">Da</div>
            <div>${whoStr(item)}</div>
          </div>
        </div>

        ${item.tipo==='privati' ? `
          <div class="two-col" style="margin-top:10px;">
            <div><div class="muted">Nome</div><div>${esc(item.nome)}</div></div>
            <div><div class="muted">Cognome</div><div>${esc(item.cognome)}</div></div>
          </div>
        ` : `
          <div class="two-col" style="margin-top:10px;">
            <div><div class="muted">Rag. Sociale</div><div>${esc(item.rgs)}</div></div>
            <div><div class="muted">Settore</div><div>${esc(item.settore)}</div></div>
          </div>
        `}

        <div style="margin-top:12px;">
          <div class="muted">Messaggio</div>
          <div class="panel" style="white-space:pre-wrap;border-radius:10px;padding:10px;background:#fff;">${esc(item.msg)}</div>
        </div>

        <div class="muted" style="margin-top:10px;">
          Inviata: ${fmtDt(item.created_at)}${item.updated_at?` — Ultima modifica: ${fmtDt(item.updated_at)}`:''}<br>
          Stato invio mail: <b>${esc(item.mail_status||'')}</b>${item.mail_error?` — errore: ${esc(item.mail_error)}`:''}
        </div>
      </div>

      <div class="panel compact" style="margin:12px;">
        <form id="updateForm">
          <input type="hidden" name="csrf" value="${esc(csrf)}">
          <input type="hidden" name="id" value="${Number(item.id)||0}">

          <div class="two-col">
            <label>
              <div class="muted">Stato</div>
              <select name="status" id="statusSel" required class="chip" style="border-radius:10px;">
                <option value="new" ${item.status==='new'?'selected':''}>Nuova</option>
                <option value="in_review" ${item.status==='in_review'?'selected':''}>In revisione</option>
                <option value="replied" ${item.status==='replied'?'selected':''}>Risposta inviata</option>
                <option value="closed" ${item.status==='closed'?'selected':''}>Chiusa</option>
              </select>
            </label>
            <label>
              <div class="muted">Assegnata a</div>
              ${renderAssigneeSelect(item.assigned_to||'')}
            </label>
          </div>

          <label class="full" id="closureWrap" style="display:${isClosed?'block':'none'}; margin-top:10px;">
            <div class="muted">Motivo chiusura <span style="color:#b91c1c">*</span></div>
            <textarea name="closure_reason" id="closureReason" rows="4" class="panel" style="width:100%;border-radius:10px;" ${isClosed?'required':''}>${esc(item.closure_reason||'')}</textarea>
            <div class="hint">Obbligatorio se lo stato è “Chiusa”.</div>
          </label>

          <label class="full" style="display:block; margin-top:10px;">
            <div class="muted">Note interne</div>
            <textarea name="internal_note" rows="5" class="panel" style="width:100%;border-radius:10px;">${esc(item.internal_note||'')}</textarea>
          </label>

          <div class="actions" style="margin-top:10px;">
            <button type="button" class="chip" data-modal-close>Chiudi</button>
            <button type="submit" class="chip s-ok"><i class="fa-solid fa-floppy-disk"></i> Salva</button>
          </div>
        </form>
      </div>
    `;
  }

  function openModal(item){
    const modal = document.getElementById('modal');
    const body  = document.getElementById('modalBody');
    if (!modal || !body) return;
    body.innerHTML = renderModalBody(item);
    modal.classList.add('open');
    document.body.classList.add('modal-open');

    const statusSel   = body.querySelector('#statusSel');
    const closureWrap = body.querySelector('#closureWrap');
    const closureArea = body.querySelector('#closureReason');
    function syncClosure(){
      const closed = statusSel.value === 'closed';
      closureWrap.style.display = closed ? 'block' : 'none';
      if (closed) { closureArea.setAttribute('required','required'); }
      else { closureArea.removeAttribute('required'); closureArea.value = ''; }
    }
    statusSel?.addEventListener('change', syncClosure);

    body.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));

    body.querySelector('#updateForm')?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      syncClosure();
      if (statusSel.value==='closed' && (!closureArea.value || !closureArea.value.trim())) {
        alert('Inserisci il motivo della chiusura.');
        closureArea.focus();
        return;
      }
      const fd = new FormData(e.target);
      try{
        const res = await fetch('/backend/email/update.php', { method:'POST', body: fd, credentials:'same-origin' });
        const txt = await res.text();
        if (!res.ok) throw new Error(txt||('HTTP '+res.status));
        location.reload();
      }catch(err){
        alert('Salvataggio non riuscito.');
        console.error(err);
      }
    });
  }

  function closeModal(){
    const modal = document.getElementById('modal');
    modal?.classList.remove('open');
    document.body.classList.remove('modal-open');
  }

  // Bind
  document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('.open-modal').forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        try{
          const raw = btn.getAttribute('data-item') || '{}';
          const item = JSON.parse(raw);
          openModal(item);
        }catch(e){ console.error('Bad data-item JSON', e); }
      });
    });
    document.querySelectorAll('[data-modal-close]').forEach(el=> el.addEventListener('click', closeModal));
    document.getElementById('modal')?.addEventListener('click', (e)=>{ if (e.target && e.target.id === 'modal') closeModal(); });
  });

  window.addEventListener('pagehide', closeModal);
})();

/*=========VIEW RICHIESTE==========*/
  // ========== DETTAGLIO (view.php): toggle motivo chiusura e guardia submit ==========
  const detailForm = document.getElementById('detailForm');
  if (detailForm) {
    const statusSel = document.getElementById('statusSel');
    const wrap = document.getElementById('closureWrap');
    const area = document.getElementById('closureReason');

    function sync() {
      const closed = statusSel.value === 'closed';
      wrap.style.display = closed ? 'block' : 'none';
      if (closed) { area.setAttribute('required','required'); }
      else { area.removeAttribute('required'); area.value = ''; }
    }
    statusSel?.addEventListener('change', sync);
    detailForm.addEventListener('submit', (e) => {
      sync();
      if (statusSel.value === 'closed' && (!area.value || !area.value.trim())) {
        e.preventDefault();
        alert('Inserisci il motivo della chiusura.');
        area.focus();
      }
    });
  }

