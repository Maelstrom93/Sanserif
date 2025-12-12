// file: assets/javascript/calendario.js

(function(){
  // ==== Dati dal server (iniettati in pagina prima di questo script) ====
  const CAL = (window.__CAL_DATA || {});
  const eventi       = CAL.eventi || [];
  const tipoColorMap = CAL.tipoColorMap || {};
  const CAN_CREATE   = !!CAL.canCreate;
  const IS_ADMIN     = !!CAL.isAdmin;

  // ===== Fallback colore client =====
  function defaultColorForJS(tipo){
    const k = String(tipo||'').trim().toLowerCase();
    if (k === 'articolo')  return '#0EA5E9';
    if (k === 'revisione') return '#F59E0B';
    if (k === 'incontro')  return '#10B981';
    if (k === 'scadenza')  return '#EF4444';
    return '#64748B';
  }
  function suggestedColor(tipo){
    return tipoColorMap[tipo] || defaultColorForJS(tipo);
  }

  // ===== Modal controller =====
  const modal      = document.getElementById('eventModal');
  const sheet      = modal?.querySelector('.sheet');
  const form       = document.getElementById('eventForm');
  const deleteBtn  = document.getElementById('deleteBtn');
  const btnNuovo   = document.getElementById('btnNuovo');

  const fldAssHidden = document.getElementById('assegnato_a');
  const assSel       = document.getElementById('assegnato_sel');
  const assWrap      = document.getElementById('assegnato_custom_wrap');
  const assCustom    = document.getElementById('assegnato_custom');

  const tipoSel      = document.getElementById('tipo_evento');
  const newTypeWrap  = document.getElementById('newTypeWrap');
  const tipoNew      = document.getElementById('tipo_new');
  const tipoNewColor = document.getElementById('tipo_new_color');

  const colorInput    = document.getElementById('colore');
  const colorOverride = document.getElementById('color_override');


  // ==== DEBUG ====
  const debugBtn = document.getElementById('debugToggle');
  let debugEnabled = (localStorage.getItem('calDebug') === '1');

  function renderDebugBadge(){
    if (!debugBtn) return;
    debugBtn.classList.toggle('debug-active', !!debugEnabled);
    debugBtn.innerHTML = (debugEnabled ? '������ Debug ON' : '������ Debug');
  }

  if (debugBtn){
    renderDebugBadge();
    debugBtn.addEventListener('click', ()=>{
      debugEnabled = !debugEnabled;
      localStorage.setItem('calDebug', debugEnabled ? '1':'0');
      renderDebugBadge();
    });
  }

  // ==============================
  // Modal open/close + focus trap
  // ==============================
  let lastOpener = null;

  function trapFocus(e){
    if (!modal.classList.contains('open')) return;
    const focusables = modal.querySelectorAll('button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])');
    const list = Array.from(focusables).filter(el => !el.disabled);
    if (!list.length) return;

    const first = list[0];
    const last  = list[list.length - 1];

    if (e.key === 'Tab') {
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault(); last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus();
      }
    }
  }

  function apri(openerBtn){
    lastOpener = openerBtn || null;
    modal.classList.add('open');
    modal.classList.remove('closing');
    document.body.classList.add('modal-open');
    modal.setAttribute('aria-hidden','false');
    setTimeout(()=>{ form?.querySelector('input, select, textarea, button')?.focus(); }, 20);
  }

  function chiudi(){
    modal.classList.add('closing');
    const fallback = setTimeout(()=>{
      modal.classList.remove('open','closing');
      document.body.classList.remove('modal-open');
      modal.setAttribute('aria-hidden','true');
      if (lastOpener){ try{ lastOpener.focus(); }catch(_){} }
    }, 340);

    modal.addEventListener('transitionend', function onEnd(ev){
      if (ev.target !== sheet) return;
      modal.removeEventListener('transitionend', onEnd);
      clearTimeout(fallback);

      modal.classList.remove('open','closing');
      document.body.classList.remove('modal-open');
      modal.setAttribute('aria-hidden','true');

      if (lastOpener){ try{ lastOpener.focus(); }catch(_){} }
    }, { once:true });
  }

  modal?.addEventListener('click', e=>{
    if (e.target === modal) chiudi();
  });
  document.addEventListener('click', e=>{
    if (e.target.closest('[data-modal-close]') && modal.classList.contains('open')){
      e.preventDefault();
      chiudi();
    }
  });
  document.addEventListener('keydown', e=>{
    if (e.key === 'Escape' && modal.classList.contains('open')) chiudi();
    if (e.key === 'Tab'    && modal.classList.contains('open')) trapFocus(e);
  });

  // =========================
  // Assegnatario dinamico
  // =========================
  function syncAssHidden(){
    if (!assSel) return;
    if (assSel.value === '__custom__') {
      assWrap.style.display = 'block';
      fldAssHidden.value = assCustom.value.trim();
    } else {
      assWrap.style.display = 'none';
      fldAssHidden.value = assSel.value;
    }
  }
  assSel?.addEventListener('change', syncAssHidden);
  assCustom?.addEventListener('input', syncAssHidden);

  function toggleNewTypeBox(){
    const show = tipoSel.value === '__new__';
    newTypeWrap.style.display = show ? 'block' : 'none';
    if (show) tipoNew.focus();
  }
  tipoSel?.addEventListener('change', toggleNewTypeBox);

  colorInput?.addEventListener('input', ()=>{
    if (colorOverride) colorOverride.value = '1';
  });

  tipoSel?.addEventListener('change', ()=>{
    if (colorOverride.value !== '1'){
      const t = tipoSel.value === '__new__' ? (tipoNew.value || 'altro') : tipoSel.value;
      colorInput.value = suggestedColor(t);
    }
  });

  tipoNew?.addEventListener('input', ()=>{
    if (tipoSel.value === '__new__' && colorOverride.value !== '1') {
      colorInput.value = tipoNewColor.value || suggestedColor('altro');
    }
  });

  tipoNewColor?.addEventListener('input', ()=>{
    if (tipoSel.value === '__new__' && colorOverride.value !== '1') {
      colorInput.value = tipoNewColor.value;
    }
  });

  // ======================
  // Calendario FullCalendar
  // ======================
  document.addEventListener('DOMContentLoaded', function(){
    const calendarEl  = document.getElementById('calendar');
    const tipoFiltro  = document.getElementById('tipoFiltro');
    const searchInput = document.getElementById('searchInput');
    const viewSelect  = document.getElementById('viewSelect');

    let currentEvents = [...eventi];

    let calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: 'it',
      editable: IS_ADMIN,
      eventStartEditable: IS_ADMIN,
      eventDurationEditable: IS_ADMIN,
      selectable: CAN_CREATE,
      events: currentEvents,

      dayMaxEventRows: 1,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: '',
      },

      moreLinkContent: args => ({
        html: `<span style="background:#c62828;color:#fff;padding:2px 6px;border-radius:12px;font-size:.75rem;font-weight:700;">${args.num} eventi</span>`
      }),

      eventClick: info => {
        openModalFromEvent(info.event, info.dateStr, info.el);
      },

      dateClick: CAN_CREATE ? (info) => {
        openModalFromEvent(null, info.dateStr, btnNuovo);
      } : null,

      eventContent: function(info){
        const wrapper = document.createElement('div');
        wrapper.classList.add('fc-event-custom-content');

        const titolo = `<strong>${info.event.title}</strong>`;
        const assegnato = `<div><small>${info.event.extendedProps.assegnato || ''}</small></div>`;
        const badgeDesc = info.event.extendedProps.descrizione
          ? `<span style="display:inline-block;margin-top:4px;font-size:.7rem;padding:1px 6px;border-radius:8px;background:#E0E7FF;border:1px solid #C7D2FE;">descrizione</span>`
          : '';
        const badgeNote = info.event.extendedProps.note
          ? `<span style="display:inline-block;margin-top:4px;margin-left:6px;font-size:.7rem;padding:1px 6px;border-radius:8px;background:#FFF7ED;border:1px solid #FED7AA;">note</span>`
          : '';

        wrapper.innerHTML = `${titolo}${assegnato}${badgeDesc}${badgeNote}`;
        return { domNodes: [wrapper] };
      }
    });

    calendar.render();

    // Filtri
    function applyFilters(){
      const tipo  = (tipoFiltro?.value || '').trim();
      const query = (searchInput?.value || '').toLowerCase();

      currentEvents = eventi.filter(ev=>{
        const matchTipo  = !tipo || ev.tipo === tipo;
        const txt1       = (ev.title || '').toLowerCase();
        const txt2       = (ev.assegnato || '').toLowerCase();
        const matchTesto = !query || txt1.includes(query) || txt2.includes(query);
        return matchTipo && matchTesto;
      });

      calendar.removeAllEventSources();
      calendar.addEventSource(currentEvents);
    }
    tipoFiltro?.addEventListener('change', applyFilters);
    searchInput?.addEventListener('input', applyFilters);

    // Nuovo evento (solo admin)
    btnNuovo?.addEventListener('click', ()=>{
      openModalFromEvent(null, '', btnNuovo);
    });

    // ===========================================
    // FUNZIONE COMPLETA PER APRIRE LA MODALE
    // ===========================================
    window.openModalFromEvent = function(evento, data='', openerBtn=null){
      // blocco creazione a non-admin
      if (!evento && !CAN_CREATE) return;

      form.reset();
      if (colorOverride) colorOverride.value = '0';

      // reset assegnatario
      assWrap.style.display = 'none';
      assSel.value = '';
      assCustom.value = '';
      fldAssHidden.value = '';

      // data
      const iso      = evento?.startStr || data || '';
      const onlyDate = iso ? String(iso).slice(0,10) : '';

      document.getElementById('eventId').value     = evento?.id || '';
      document.getElementById('nome').value        = evento?.title || '';
      document.getElementById('data_evento').value = onlyDate;
      document.getElementById('note').value        = evento?.extendedProps?.note || '';
      document.getElementById('descrizione').value = evento?.extendedProps?.descrizione || '';

      // tipo evento
      const tip = evento?.extendedProps?.tipo || 'altro';
      if (![...tipoSel.options].some(o => o.value === tip)) {
        const opt = document.createElement('option');
        opt.value = tip;
        opt.textContent = tip.charAt(0).toUpperCase() + tip.slice(1);
        tipoSel.insertBefore(opt, tipoSel.querySelector('option[value="__new__"]'));
      }
      tipoSel.value = tip;

      // colore
      colorInput.value = evento
        ? (evento.backgroundColor || suggestedColor(tip))
        : suggestedColor(tip);

      // assegnatario: id o testo libero
      const raw = (evento?.extendedProps?.assegnato_raw || '').toString().trim();
      if (raw === '') {
        assSel.value = '';
      } else if ([...assSel.options].some(o => o.value === raw)) {
        assSel.value = raw;
      } else {
        assSel.value = '__custom__';
        assWrap.style.display = 'block';
        assCustom.value = raw;
      }
      syncAssHidden();

      // === sola lettura per non-admin su eventi esistenti ===
      const READ_ONLY = !!(evento && !IS_ADMIN);
      const saveBtn   = form?.querySelector('button[type="submit"]');

      function setReadOnly(ro){
        form.querySelectorAll('input, select, textarea, button').forEach(el=>{
          if (el.hasAttribute('data-modal-close')) return; // lascia il pulsante chiudi
          if (el.tagName === 'BUTTON') { el.disabled = true; return; }
          el.disabled = ro;
          if (ro && el.tagName === 'TEXTAREA') el.readOnly = true;
        });

        if (saveBtn)   saveBtn.style.display   = ro ? 'none' : '';
        if (deleteBtn) deleteBtn.style.display = ro ? 'none' : (evento ? 'inline-flex' : 'none');

        const mt   = document.getElementById('modalTitle');
        const base = `<i class="fa-regular fa-pen-to-square"></i> ${evento ? evento.title : 'Nuovo Evento'}`;
        mt.innerHTML = ro
          ? `${base} <span class="chip sm" style="margin-left:8px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;">Sola lettura</span>`
          : base;
      }

      setReadOnly(READ_ONLY);

      apri(openerBtn);
      toggleNewTypeBox();

      if (READ_ONLY){
        form.addEventListener('submit', e=> e.preventDefault(), { once:true });
      }
    };

    // ============================
    // SALVATAGGIO
    // ============================
    form?.addEventListener('submit', function(e){
      e.preventDefault();
      syncAssHidden();

      const fd = new FormData(form);
      const d  = (fd.get('data_evento') || '').toString().slice(0,10);
      fd.set('data_evento', d);

      if (debugEnabled){
        const obj = {};
        for (const [k,v] of fd.entries()) obj[k]=v;
        alert("Payload verso salva_evento.php:\n\n" + JSON.stringify(obj,null,2));
      }

      fetch('salva_evento.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(res => res.json())
      .then(out=>{
        if (!out.success) throw new Error(out.error || 'Errore salvataggio');
        location.reload();
      })
      .catch(err=>{
        alert("Errore salvataggio: " + err.message);
      });
    });

    // Eliminazione
    deleteBtn?.addEventListener('click', ()=>{
      const id = document.getElementById('eventId').value;
      if (!id) return;

      if (confirm("Eliminare evento?")){
        fetch('elimina_evento.php?id='+encodeURIComponent(id), { credentials:'same-origin' })
        .then(res => res.json())
        .then(out=>{
          if (!out.success) throw new Error();
          location.reload();
        })
        .catch(()=>{
          alert("Eliminazione non riuscita.");
        });
      }
    });

  }); // fine DOMContentLoaded

})();
