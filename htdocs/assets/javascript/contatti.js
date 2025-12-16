(function () {
  // Svuota sempre eventuali honeypot (contro autofill post-caricamento)
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.hp-field').forEach(i => {
      try {
        i.value = '';
        i.setAttribute('autocomplete', 'off');
      } catch (e) {}
    });
  });

  const tabPriv  = document.getElementById('tab-privati');
  const tabAzi   = document.getElementById('tab-aziende');
  const formPriv = document.getElementById('pane-privati');
  const formAzi  = document.getElementById('pane-aziende');
  if (!tabPriv || !tabAzi || !formPriv || !formAzi) return;

  function setTab(which) {
    const isPriv = which === 'privati';
    tabPriv.classList.toggle('is-active', isPriv);
    tabAzi.classList.toggle('is-active', !isPriv);
    tabPriv.setAttribute('aria-selected', isPriv ? 'true' : 'false');
    tabAzi.setAttribute('aria-selected', !isPriv ? 'true' : 'false');
    formPriv.classList.toggle('is-visible', isPriv);
    formAzi.classList.toggle('is-visible', !isPriv);
  }

  tabPriv.addEventListener('click', () => setTab('privati'));
  tabAzi.addEventListener('click', () => setTab('aziende'));
  tabPriv.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setTab('privati'); }});
  tabAzi.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setTab('aziende'); }});
  setTab('privati');

  const notice = document.getElementById('notice');
  if (notice) {
    const qs = new URLSearchParams(location.search);
    if (qs.has('ok')) {
      notice.hidden = false;
      notice.classList.add('notice--ok', 'appear');
      notice.textContent = 'Messaggio inviato correttamente. Ti ricontatteremo al più presto.';
      setTimeout(() => { history.replaceState(null, '', location.pathname); }, 3500);
    } else if (qs.has('err')) {
      const map = {
        validation: 'Controlla i dati inseriti (email valida e messaggio non vuoto).',
        cfg: 'Configurazione mancante o non valida.',
        tpl: 'Template email non trovato o non leggibile.',
        mail: 'Errore durante l’invio. Riprova più tardi.',
        send: 'Errore durante l’invio. Riprova più tardi.'
      };
      notice.hidden = false;
      notice.classList.add('notice--err', 'appear');
      notice.textContent = map[qs.get('err')] || 'Errore imprevisto.';
      setTimeout(() => { history.replaceState(null, '', location.pathname); }, 5000);
    }
  }
function basicValidate(form) {
  const email = form.querySelector('input[type="email"]');
  const msg   = form.querySelector('textarea[name="msg"]');

  if (email) {
    email.value = email.value.trim();
    if (!email.checkValidity()) {
      alert('Inserisci un indirizzo email valido.');
      email.focus();
      return false;
    }
  }

  if (msg) {
    const v = msg.value.trim();
    if (v.length < 3) {
      alert('Il messaggio è troppo corto.');
      msg.focus();
      return false;
    }
    msg.value = v; // opzionale: salva la versione "trimmed"
  }

  return true;
}


  formPriv.addEventListener('submit', (e) => { if (!basicValidate(formPriv)) e.preventDefault(); });
  formAzi.addEventListener('submit',  (e) => { if (!basicValidate(formAzi))  e.preventDefault(); });
})();

