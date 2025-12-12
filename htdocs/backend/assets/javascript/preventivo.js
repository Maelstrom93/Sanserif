// JS elenco preventivi
document.addEventListener('DOMContentLoaded', function () {
  // Conferma eliminazione (progressive enhancement)
  document.body.addEventListener('click', function (ev) {
    const a = ev.target.closest('a[data-delete-id]');
    if (!a) return;

    const id = a.getAttribute('data-delete-id');
    const conferma = confirm(`Eliminare il preventivo #${id}?`);
    if (!conferma) {
      ev.preventDefault();
      ev.stopPropagation();
    } else {
      // lascia che il link prosegua (GET ?delete=ID)
    }
  });

  // (facoltativo) evidenzia riga al passaggio mouse sulle azioni
  const table = document.querySelector('table.table');
  if (table) {
    table.addEventListener('mouseover', function (e) {
      const row = e.target.closest('tr');
      if (row) row.classList.add('hover');
    });
    table.addEventListener('mouseout', function (e) {
      const row = e.target.closest('tr');
      if (row) row.classList.remove('hover');
    });
  }
});

/* NUOVO_PREVENTIVO */
function aggiungiRiga() {
  const tbody = document.querySelector('#voci-preventivo tbody');
  const tr = document.createElement('tr');
  tr.style.opacity = '0';
  tr.innerHTML = `
    <td><input type="text" name="descrizione[]" required></td>
    <td><input type="number" name="quantita[]" step="1" min="1" required></td>
    <td><input type="number" name="prezzo[]" step="0.01" min="0" required></td>
    <td><button type="button" class="inline-btn" onclick="rimuoviRiga(this)"><i class="fa-regular fa-trash-can"></i></button></td>
  `;
  tbody.appendChild(tr);
  requestAnimationFrame(()=>{ tr.style.transition='opacity .22s ease'; tr.style.opacity='1'; });
}

function rimuoviRiga(btn){
  const row = btn.closest('tr');
  if (!row) return;
  row.style.transition='opacity .18s ease';
  row.style.opacity='0';
  row.addEventListener('transitionend', ()=> row.remove(), {once:true});
}

function toggleNuovoCliente(){
  const div = document.getElementById('nuovo-cliente');
  const btn = document.getElementById('toggle-nuovo-cliente-btn');
  const open = div.classList.toggle('aperto');
  btn.innerHTML = open
    ? '<i class="fa-solid fa-user-minus"></i> Nascondi nuovo cliente'
    : '<i class="fa-solid fa-user-plus"></i> Inserisci nuovo cliente';
}

// Mostra campo referente temporaneo quando è selezionato un cliente esistente
document.addEventListener('DOMContentLoaded', function() {
  const clienteSel = document.getElementById('cliente_id');
  const wrap = document.getElementById('referente-temp-wrapper');
  if (clienteSel && wrap) {
    clienteSel.addEventListener('change', function(){
      wrap.style.display = this.value ? 'block' : 'none';
    });
  }
});

/* =========================
   MODALI PREVENTIVI (view/edit/duplica)
========================= */
(function(){
  'use strict';

  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));

  // Helpers modale
  function openModal(id){
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.add('is-open','show');
    m.setAttribute('aria-hidden','false');
  }
  function closeModal(id){
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('is-open','show');
    m.setAttribute('aria-hidden','true');
  }

  // Chiudi modali (bottoni)
  document.addEventListener('click', (e)=>{
    const btnClose = e.target.closest('[data-modal-close]');
    if (btnClose) closeModal('modale-edit-preventivo');
    const btnViewClose = e.target.closest('[data-view-close]');
    if (btnViewClose) closeModal('modale-view-preventivo');
  });

  // Open VIEW
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.open-view-preventivo');
    if (!btn) return;
    const id = btn.dataset.id;
    fetch(`../api/preventivo_dettaglio.php?id=${id}`)
      .then(r=>r.json())
      .then(d=>{
        if(!d.ok){ alert(d.error||'Errore'); return; }
        renderView(d);
        const pdf = document.getElementById('pv-pdf-link');
        if (pdf) pdf.href = `genera_preventivo.php?id=${id}`;
        openModal('modale-view-preventivo');
      })
      .catch(()=>alert('Errore di rete'));
  });

  // Open EDIT
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.open-edit-preventivo');
    if (!btn) return;
    const id = btn.dataset.id;
    fetch(`../api/preventivo_dettaglio.php?id=${id}`)
      .then(r=>r.json())
      .then(d=>{
        if(!d.ok){ alert(d.error||'Errore'); return; }
        fillEditForm(d);
        openModal('modale-edit-preventivo');
      })
      .catch(()=>alert('Errore di rete'));
  });

  // DUPLICA
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.open-duplica-preventivo');
    if (!btn) return;
    const id = btn.dataset.id;
    if (!confirm('Duplicare il preventivo #' + id + '?')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('../api/duplica_preventivo.php',{ method:'POST', body:fd })
      .then(r=>r.json())
      .then(d=>{
        if(!d.ok){ alert(d.error||'Errore'); return; }
        // Dopo duplicazione: ricarico l’elenco
        location.reload();
      })
      .catch(()=>alert('Errore di rete'));
  });

  // RENDER VIEW
  function money(n){ return (Number(n)||0).toLocaleString('it-IT',{minimumFractionDigits:2, maximumFractionDigits:2}) + ' €'; }
  function dateit(d){ if(!d) return ''; const dt=new Date(d); if(isNaN(dt)) return d; return d.split('-').reverse().join('/'); }

  function renderView(d){
    const p = d.preventivo || {};
    const c = d.cliente || {};
    const r = d.righe || [];

    const det = $('#pv-dettaglio');
    det.innerHTML = `
      <div><strong>Cliente:</strong> ${escapeHtml(c.nome || p.cliente_nome_custom || '—')}</div>
      ${p.referente_custom ? `<div><strong>Referente:</strong> ${escapeHtml(p.referente_custom)}</div>` : (c.referente_1 ? `<div><strong>Referente:</strong> ${escapeHtml(c.referente_1)}</div>`:'')}
      <div class="muted-sm">
        Data: ${escapeHtml(dateit(p.data||''))} &nbsp;•&nbsp;
        N°: ${escapeHtml(String(p.numero||''))}/${escapeHtml(String(p.anno||''))} &nbsp;•&nbsp;
        Valido fino: ${escapeHtml(dateit(p.valido_fino||''))}
      </div>
      <div class="muted-sm">Pagamento: ${escapeHtml(p.pagamento||'')}</div>
    `;

    const tb = $('#pv-righe tbody');
    tb.innerHTML = '';
    r.forEach(x=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(x.descrizione||'')}</td>
        <td style="text-align:center">${escapeHtml(String(x.quantita||0))}</td>
        <td style="text-align:right">${money(x.prezzo_unitario||0)}</td>
        <td style="text-align:right">${money(x.totale_riga||0)}</td>
      `;
      tb.appendChild(tr);
    });

    const totali = $('#pv-totali');
    const sconto = Number(p.sconto||0);
    const iva = Number(p.iva||0);
    const tot = Number(p.totale||0);
    const sVal = tot * (sconto/100);
    const base = tot - sVal;
    const ivaVal = base * (iva/100);
    const finale = base + ivaVal;
    totali.innerHTML = `
      <div>Totale parziale: <strong>${money(tot)}</strong></div>
      ${sconto>0 ? `<div>Sconto (${sconto}%): <strong>- ${money(sVal)}</strong></div>`:''}
      <div>IVA (${iva}%): <strong>${money(ivaVal)}</strong></div>
      <div style="font-size:1.05rem; margin-top:4px;"><strong>Totale finale: ${money(finale)}</strong></div>
    `;
  }

  function escapeHtml(s){
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  // FILL EDIT FORM
  function fillEditForm(d){
    const p = d.preventivo || {};
    const r = d.righe || [];

    $('#edit-id').value = p.id || '';
    $('#edit-data').value = p.data || '';
    $('#edit-valido-fino').value = p.valido_fino || '';
    $('#edit-pagamento').value = p.pagamento || 'Bonifico Bancario';
    $('#edit-iva').value = (p.iva != null ? p.iva : 22);
    $('#edit-sconto').value = (p.sconto != null ? p.sconto : 0);
    $('#edit-note').value = p.note || '';

    const tb = $('#edit-voci-preventivo tbody');
    tb.innerHTML = '';
    if (r.length === 0) {
      tb.appendChild(buildEditRow());
    } else {
      r.forEach(x=> tb.appendChild(buildEditRow(x.descrizione, x.quantita, x.prezzo_unitario)));
    }
  }

  function buildEditRow(desc='', qta=1, prezzo=0){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" name="descrizione[]" value="${escapeHtml(desc)}" required></td>
      <td><input type="number" name="quantita[]" value="${escapeHtml(String(qta))}" step="1" min="1" required></td>
      <td><input type="number" name="prezzo[]" value="${escapeHtml(String(prezzo))}" step="0.01" min="0" required></td>
      <td><button type="button" class="inline-btn js-del-row"><i class="fa-regular fa-trash-can"></i></button></td>
    `;
    return tr;
  }

  // Add row (edit)
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('#btn-add-riga');
    if (!btn) return;
    const tb = document.querySelector('#edit-voci-preventivo tbody');
    if (!tb) return;
    tb.appendChild(buildEditRow());
  });

  // Delete row (edit)
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.js-del-row');
    if (!btn) return;
    const tr = btn.closest('tr');
    if (!tr) return;
    tr.remove();
  });

  // Submit Modifica
  const form = document.getElementById('formModificaPreventivo');
  if (form) {
    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      const fd = new FormData(form);
      fetch(form.action, { method:'POST', body: fd })
        .then(r=>r.json())
        .then(d=>{
          if(!d.ok){ alert(d.error||'Errore salvataggio'); return; }
          // chiudo modale e aggiorno elenco
          closeModal('modale-edit-preventivo');
          location.reload();
        })
        .catch(()=>alert('Errore di rete'));
    });
  }

  // Conferma eliminazione (già gestito anche altrove)
  document.addEventListener('click', function(ev){
    var a = ev.target.closest && ev.target.closest('a[data-delete-id]');
    if(!a) return;
    var id = a.getAttribute('data-delete-id') || '';
    if(!confirm('Eliminare il preventivo #' + id + '?')){
      ev.preventDefault();
      ev.stopPropagation();
    }
  });

})();
