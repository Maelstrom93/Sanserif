// Auto-refresh ogni 300s
setInterval(()=> location.reload(), 300000);

// === Tooltip helpers (una sola tooltip per tutta la pagina) ===
let __chartTipEl = null;
function getTip(){
  if (!__chartTipEl){
    __chartTipEl = document.createElement('div');
    __chartTipEl.className = 'chart-tip';
    document.body.appendChild(__chartTipEl);
  }
  return __chartTipEl;
}
function safeJsonParse(text, fallback) {
  try { return JSON.parse(text); }
  catch (e) {
    console.warn('JSON parse error:', e);
    return fallback;
  }
}
function sortKeys(obj) {
  return Object.keys(obj || {}).sort((a, b) => a.localeCompare(b, 'it', { numeric: true }));
}

function mapDayKeys(raw) {
  const mapped = {};
sortKeys(raw).forEach(k => { mapped[k.slice(8,10)] = Number.parseInt(raw[k] || 0, 10); });
  return mapped;
}


function showTip(html, x, y){
  const tip = getTip();
  tip.innerHTML = html;
  tip.style.left = Math.round(x) + 'px';
  tip.style.top  = Math.round(y) + 'px';
  tip.classList.add('show');
}
function hideTip(){
  const tip = getTip();
  tip.classList.remove('show');
}
function bindCanvasInteractions(canvas){
  if (canvas.__tipBound) return;
  canvas.__tipBound = true;

   const getRelXY = (clientX, clientY) => {
    const rect = canvas.getBoundingClientRect();
    return { x: clientX - rect.left, y: clientY - rect.top };
  };

  const hitTestBar = (x, y, hits) => {
    for (const h of hits) {
      if (x >= h.x && x <= h.x + h.w && y >= h.y && y <= h.y + h.h) return h;
    }
    return null;
  };

  const nearestBar = (x, hits) => {
    if (!hits.length) return null;
    let best = hits[0];
    let minDx = Infinity;
    for (const h of hits) {
      const cx = h.x + h.w / 2;
      const dx = Math.abs(x - cx);
      if (dx < minDx) { minDx = dx; best = h; }
    }
    return best;
  };

  const nearestPoint = (x, y, hits, maxDist = 24) => {
    let best = null;
    let minD2 = Infinity;
    for (const h of hits) {
      const dx = x - h.x, dy = y - h.y;
      const d2 = dx*dx + dy*dy;
      if (d2 < minD2) { minD2 = d2; best = h; }
    }
    return (best && Math.sqrt(minD2) <= maxDist) ? best : null;
  };

  const pickHit = (clientX, clientY) => {
    const { x, y } = getRelXY(clientX, clientY);
    const hits = canvas.__hits || [];

    let best = null;
    if (canvas.__chartType === 'bar') {
      best = hitTestBar(x, y, hits) || nearestBar(x, hits);
    } else {
      best = nearestPoint(x, y, hits);
    }
    return best ? { hit: best, clientX, clientY } : null;
  };


  let tapTimer = null;

  canvas.addEventListener('mousemove', (e) => {
    const picked = pickHit(e.clientX, e.clientY);
    if (picked){
      const {hit} = picked;
      const html = hit.series
        ? `<b>${hit.series}</b><br>${hit.label}: <b>${hit.value}</b>`
        : `${hit.label}: <b>${hit.value}</b>`;
      showTip(html, e.clientX, e.clientY);
    } else {
      hideTip();
    }
  }, {passive:true});

  canvas.addEventListener('mouseleave', () => hideTip(), {passive:true});

  canvas.addEventListener('touchstart', (e) => {
    const t = e.changedTouches[0];
    const picked = pickHit(t.clientX, t.clientY);
    if (picked){
      const {hit, clientX, clientY} = picked;
      const html = hit.series
        ? `<b>${hit.series}</b><br>${hit.label}: <b>${hit.value}</b>`
        : `${hit.label}: <b>${hit.value}</b>`;
      showTip(html, clientX, clientY);
      clearTimeout(tapTimer);
      tapTimer = setTimeout(hideTip, 1200);
    }
  }, {passive:true});
}

// Canvas responsive
function autosizeCanvas(canvas){
 const ratio = Number.parseFloat(canvas.dataset.aspect || '0.4');
  const dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
  const cssW = canvas.parentElement.clientWidth || 600;
  const cssH = Math.max(160, Math.round(cssW * ratio));
  canvas.style.width = cssW + 'px';
  canvas.style.height = cssH + 'px';
  if (canvas.width !== Math.round(cssW * dpr) || canvas.height !== Math.round(cssH * dpr)){
    canvas.width  = Math.round(cssW * dpr);
    canvas.height = Math.round(cssH * dpr);
    canvas.getContext('2d').setTransform(dpr, 0, 0, dpr, 0, 0);
  }
  return { width: cssW, height: cssH };
}

// Grafici canvas
function drawBars(canvas, seriesMap, opts={}){
  autosizeCanvas(canvas);
  const ctx = canvas.getContext('2d');
  const W = canvas.clientWidth, H = canvas.clientHeight;

  ctx.clearRect(0,0,W,H);

  const labels = Object.keys(seriesMap || {});
  const data   = labels.map(k => Number(seriesMap[k] || 0));
  const m = {t:20,r:20,b:40,l:40}, w = W-m.l-m.r, h = H-m.t-m.b;
  const maxV = Math.max(5, ...data, 0);
  const step  = data.length ? Math.max(1, Math.floor(w / Math.max(1,data.length)) - 6) : 20;
  const barW  = Math.min(28, step);

  // assi
  ctx.strokeStyle = '#e5e7eb';
  ctx.beginPath(); ctx.moveTo(m.l, H-m.b); ctx.lineTo(W-m.r, H-m.b); ctx.moveTo(m.l, m.t); ctx.lineTo(m.l, H-m.b); ctx.stroke();

  // griglia + ticks Y
  ctx.fillStyle = '#6b7280'; ctx.font = '12px system-ui';
  const ticks = 4;
  for (let i=0;i<=ticks;i++){
    const yVal=Math.round(maxV*i/ticks), y=H-m.b-(yVal/maxV)*h;
    ctx.strokeStyle='#f3f4f6'; ctx.beginPath(); ctx.moveTo(m.l, y); ctx.lineTo(W-m.r, y); ctx.stroke();
    ctx.fillText(String(yVal), 8, y+4);
  }

  const grad = ctx.createLinearGradient(0, m.t, 0, H-m.b);
  grad.addColorStop(0, '#0ea5e9'); grad.addColorStop(1, '#10b981'); ctx.fillStyle = grad;

  // hit areas
  const hits = [];

  labels.forEach((lab,i)=> {
    const v=data[i]||0, x=m.l+i*(barW+8), bh=(v/maxV)*h, y=H-m.b-bh;
    ctx.fillRect(x,y,barW,bh);

    if (i % Math.max(1, Math.floor(labels.length/10)) === 0) {
      const tick = (opts.shortLabel ? (lab.slice(5)) : lab);
      ctx.fillStyle='#6b7280'; ctx.fillText(tick, x, H-m.b+16); ctx.fillStyle=grad;
    }

    hits.push({ type:'bar', x, y, w:barW, h:bh, label: lab, value: v });
  });

  if (opts.totalLabel) {
    ctx.fillStyle='#374151'; ctx.font='bold 13px system-ui';
    const tot = data.reduce((a,b)=>a+b,0) || 0;
    ctx.fillText((opts.totalLabel+': '+tot), Math.max(8, W-180), m.t+4);
  }

  canvas.__hits = hits;
  canvas.__chartType = 'bar';
  bindCanvasInteractions(canvas);
}

function drawMultiLine(canvas, labels, series){
  autosizeCanvas(canvas);
  const ctx = canvas.getContext('2d');
  const W = canvas.clientWidth, H = canvas.clientHeight;
  ctx.clearRect(0,0,W,H);
  const m = {t:28,r:20,b:40,l:40}, w = W-m.l-m.r, h = H-m.t-m.b;

  const allVals = (series||[]).flatMap(s => s.data || []);
  const maxV = Math.max(3, ...allVals, 0);

  // assi
  ctx.strokeStyle='#e5e7eb';
  ctx.beginPath(); ctx.moveTo(m.l, H-m.b); ctx.lineTo(W-m.r, H-m.b); ctx.moveTo(m.l, m.t); ctx.lineTo(m.l, H-m.b); ctx.stroke();

  // griglia + ticks Y
  ctx.fillStyle='#6b7280'; ctx.font = '12px system-ui';
  const ticks=4;
  for(let i=0;i<=ticks;i++){
    const yVal=Math.round(maxV*i/ticks), y=H-m.b-(yVal/maxV)*h;
    ctx.strokeStyle='#f3f4f6'; ctx.beginPath(); ctx.moveTo(m.l, y); ctx.lineTo(W-m.r, y); ctx.stroke();
    ctx.fillText(String(yVal), 8, y+4);
  }

  const step = labels.length>1 ? w/(labels.length-1) : w;
  labels.forEach((lab,i)=>{
    if (i % Math.max(1, Math.floor(labels.length/10)) === 0) {
      ctx.fillStyle='#6b7280';
      ctx.fillText(lab.slice(5), m.l + i*step - 6, H-m.b+16);
    }
  });

  const colors = ['#0ea5e9','#10b981','#f43f5e','#a78bfa','#fb923c'];
  const hits = [];

  (series||[]).forEach((s,si)=>{
    const color = colors[si % colors.length];
    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.beginPath();
    (s.data||[]).forEach((v,i)=>{
      const x = m.l + i*step;
      const y = H-m.b - ((v||0)/maxV)*h;
      if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    });
    ctx.stroke();

    (s.data||[]).forEach((v,i)=>{
      const x = m.l + i*step;
      const y = H-m.b - ((v||0)/maxV)*h;
      ctx.fillStyle = color; ctx.beginPath(); ctx.arc(x, y, 2.5, 0, Math.PI*2); ctx.fill();
      hits.push({ type:'pt', x, y, r:8, label: labels[i] || String(i+1), value: Number(v||0), series: s.label || '' });
    });

    const xLeg = m.l + si*200; const yLeg = m.t - 10;
    ctx.fillStyle = color;
    ctx.fillRect(xLeg, yLeg, 14, 4);
    ctx.fillStyle = '#111827';
    ctx.fillText(String(s.label||''), xLeg+20, yLeg+6);
  });

  canvas.__hits = hits;
  canvas.__chartType = 'line';
  bindCanvasInteractions(canvas);
}

function renderDesktopCharts(){
  // Libri 12M (bar)
  const cL = document.getElementById('chartLibri12M');
  if (cL) {
 const series = safeJsonParse(cL.dataset.series || '{}', {});


    drawBars(cL, series, { totalLabel:'Totale 12 mesi', shortLabel:true });
  }

  // Storico top categorie (multi-line)
  const cT = document.getElementById('chartTopCat');
  if (cT) {
const labels = safeJsonParse(cT.dataset.labels || '[]', []);
const series = safeJsonParse(cT.dataset.series || '[]', []);


    if (labels.length && series.length) drawMultiLine(cT, labels, series);
  }

  // Richieste per giorno (mese) - bar
  const cR = document.getElementById('chartRichiesteMese');
  if (cR) {
    const raw = safeJsonParse(cR.dataset.series || '{}', {});
drawBars(cR, mapDayKeys(raw), { totalLabel:'Totale mese' });

  }
}

function renderChartIfNeeded(canvasId, type){
  const el = document.getElementById(canvasId);
  if (!el || el.dataset.rendered === '1') return;

  switch (type) {
    case 'richieste_sm': {
      const series = safeJsonParse(el.dataset.series || '{}', {});
      drawBars(el, mapDayKeys(series), { totalLabel:'Totale' });
      break;
    }
    case 'libri12m_sm': {
      const series = safeJsonParse(el.dataset.series || '{}', {});
      drawBars(el, series, { totalLabel:'Totale 12 mesi', shortLabel:true });
      break;
    }
    case 'topcat_sm': {
      const labels = safeJsonParse(el.dataset.labels || '[]', []);
      const series = safeJsonParse(el.dataset.series || '[]', []);
      if (labels.length && series.length) drawMultiLine(el, labels, series);
      break;
    }
    default:
      break;
  }

  el.dataset.rendered = '1';
}


document.addEventListener('DOMContentLoaded', () => {
  // Calendario (se presente)
  const calendarEl = document.getElementById('calendar');
  const ctrlsEl = document.getElementById('calMobileCtrls');

  if (calendarEl && window.FullCalendar) {
const eventi = safeJsonParse(calendarEl.dataset.eventi || '[]', []);


    const isSmall = () => window.matchMedia('(max-width: 720px)').matches;

    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: isSmall() ? 'dayGridFive' : 'dayGridWeek',
      views: {
        dayGridThree: { type: 'dayGrid', duration: { days: 3 } },
        dayGridFive : { type: 'dayGrid', duration: { days: 5 } },
        dayGridWeek : { type: 'dayGrid', duration: { weeks: 1 } }
      },
      height: 'auto',
      expandRows: true,
      locale: 'it',
      firstDay: 1,
      dayMaxEventRows: isSmall() ? false : 2,
      events: eventi,
      headerToolbar: isSmall()
        ? { left: '', center: 'title', right: '' }
        : { left: 'prev,next today', center: 'title', right: '' },
      buttonText: { today: 'oggi' },
      listDaySideFormat: { weekday: 'short', day: '2-digit', month: '2-digit' },
      moreLinkContent: args => ({ html: `<span style="background:#e11d48;color:#fff;padding:2px 6px;border-radius:12px;font-size:.75rem;font-weight:bold">+${args.num}</span>` }),
      windowResizeDelay: 80,
      eventContent: info => {
        const wrap = document.createElement('div');
        wrap.innerHTML = `<strong>${info.event.title}</strong>${info.event.extendedProps.assegnato ? `<div><small>${info.event.extendedProps.assegnato}</small></div>` : ''}`;
        return { domNodes: [wrap] };
      }
    });

    calendar.render();

    const applyCalendarResponsive = () => {
      if (isSmall()) {
        calendar.setOption('headerToolbar', { left:'', center:'title', right:'' });
        if (!['dayGridFive','dayGridThree','listWeek'].includes(calendar.view.type)) {
          calendar.changeView('dayGridFive');
        }
        if (ctrlsEl) ctrlsEl.style.display = 'flex';
      } else {
        calendar.setOption('headerToolbar', { left:'prev,next today', center:'title', right:'' });
        if (calendar.view.type !== 'dayGridWeek') calendar.changeView('dayGridWeek');
        if (ctrlsEl) ctrlsEl.style.display = 'none';
      }
    };

    applyCalendarResponsive();
    window.addEventListener('resize', () => {
      setTimeout(applyCalendarResponsive, 120);
    }, {passive:true});

    if (ctrlsEl) {
      const buttons = ctrlsEl.querySelectorAll('[data-view]');
      const setActive = (v) => buttons.forEach(b => b.classList.toggle('active', b.dataset.view === v));
      buttons.forEach(btn => {
        btn.addEventListener('click', () => {
          const view = btn.dataset.view;
          calendar.changeView(view);
          setActive(view);
        });
      });
    }
  }

  // Grafici desktop
  renderDesktopCharts();

  // Accordion mobile
  document.querySelectorAll('.accordion').forEach(acc => {
    const head = acc.querySelector('.acc-head');
    head.addEventListener('click', () => {
      acc.classList.toggle('acc-open');
      if (acc.classList.contains('acc-open')) {
        if (acc.dataset.acc === 'acc-richieste')        renderChartIfNeeded('chartRichiesteMese_sm', 'richieste_sm');
        if (acc.dataset.acc === 'acc-libri12m')         renderChartIfNeeded('chartLibri12M_sm',     'libri12m_sm');
        if (acc.dataset.acc === 'acc-topcat-storico')   renderChartIfNeeded('chartTopCat_sm',       'topcat_sm');
      }
    });
  });

  // Ridisegno grafici su resize
  let _resizeTo=null;
  window.addEventListener('resize', () => {
    clearTimeout(_resizeTo);
    _resizeTo = setTimeout(() => {
      renderDesktopCharts();
      if (document.querySelector('[data-acc="acc-richieste"].acc-open'))      renderChartIfNeeded('chartRichiesteMese_sm','richieste_sm');
      if (document.querySelector('[data-acc="acc-libri12m"].acc-open'))        renderChartIfNeeded('chartLibri12M_sm','libri12m_sm');
      if (document.querySelector('[data-acc="acc-topcat-storico"].acc-open'))  renderChartIfNeeded('chartTopCat_sm','topcat_sm');
    }, 120);
  }, {passive:true});
   if (calendarEl) {
    const eventi = safeJsonParse(calendarEl.dataset.eventi || '[]', []);
    console.log('Calendar: eventi caricati =', Array.isArray(eventi) ? eventi.length : 'N/A');
  }
});



/* ====== Orizzontal bars per “Lavori per categoria” ====== */
(function(){
  const canvas = document.getElementById('chartLavoriCat');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const series = safeJsonParse(canvas.dataset.series || '{}', {});

  const labels = Object.keys(series);
  const values = labels.map(k => Number(series[k] || 0));

  const PALETTE = [
    '#004c60', '#e67e22', '#8e44ad', '#2ecc71', '#d35400', '#16a085',
    '#c0392b', '#2980b9', '#7f8c8d', '#f1c40f', '#27ae60', '#9b59b6'
  ];

  function colorFor(label){
    let h = 0;
    for (let i = 0; i < label.length; i++) { h = (h*31 + label.charCodeAt(i)) >>> 0; }
    return PALETTE[h % PALETTE.length];
  }


  function autosize(){
    const dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
    const cssW = canvas.parentElement.clientWidth || 600;
    const rows = Math.max(3, labels.length);
    const cssH = Math.max(180, 26 * rows + 64);
    canvas.style.width  = cssW + 'px';
    canvas.style.height = cssH + 'px';
    canvas.width  = Math.round(cssW * dpr);
    canvas.height = Math.round(cssH * dpr);
    ctx.setTransform(dpr,0,0,dpr,0,0);
    return { W: cssW, H: cssH };
  }


  function draw(){
    const WH = autosize();
    const W = WH.W, H = WH.H;
    ctx.clearRect(0,0,W,H);
    const m = {t:16, r:16, b:32, l: Math.min(260, Math.max(120, Math.ceil(W*0.28)))};
    const w = W - m.l - m.r, h = H - m.t - m.b;
    const n = labels.length || 1;
    const row = h / n;

    let maxV = 1;
    for (let i = 0; i < values.length; i++) if (values[i] > maxV) maxV = values[i];


    ctx.strokeStyle = '#e5e7eb';
    ctx.beginPath();
    ctx.moveTo(m.l, m.t); ctx.lineTo(m.l, H-m.b);
    ctx.moveTo(m.l, H-m.b); ctx.lineTo(W-m.r, H-m.b);
    ctx.stroke();

    ctx.fillStyle = '#6b7280'; ctx.font='12px system-ui';
       const ticks = 4;
    for (let i = 0; i <= ticks; i++){
      const xVal = Math.round(maxV * i / ticks);
      const x = m.l + (xVal / maxV) * w;

      ctx.strokeStyle = '#f3f4f6';
      ctx.beginPath(); ctx.moveTo(x, m.t); ctx.lineTo(x, H-m.b); ctx.stroke();
      ctx.fillStyle='#6b7280'; ctx.fillText(String(xVal), x-4, H-m.b+16);
    }

       for (let i = 0; i < labels.length; i++){
      const lab = labels[i];
      const y = m.t + i * row + row * 0.15;
      const barH = row * 0.7;
      const v  = values[i] || 0;
      const bw = (v / maxV) * w;

      const col = colorFor(lab);

      ctx.fillStyle = col;
      ctx.fillRect(m.l, y, bw, barH);

      ctx.fillStyle='#0f172a';
      ctx.textBaseline='middle';
      ctx.font='13px system-ui';
      const tx = 10, ty = y + barH/2;
      const maxWidth = m.l - 14;
      let txt = lab;

      while (ctx.measureText(txt).width > maxWidth && txt.length > 3) { txt = txt.slice(0, -2); }
      if (txt !== lab) txt = txt + '…';
      ctx.fillText(txt, tx, ty);

      ctx.fillStyle='#374151';
      ctx.font='bold 12px system-ui';
      ctx.fillText(String(v), m.l + bw + 6, ty+1);
    }
    buildLegend();
  }

   function buildLegend(){
    const host = canvas.parentElement;
    const old = host.querySelector('.chart-legend');
    if (old) old.remove();
    const legend = document.createElement('div');
    legend.className = 'chart-legend';
    legend.style.display = 'flex';
    legend.style.flexWrap = 'wrap';
    legend.style.gap = '8px 14px';
    legend.style.marginTop = '8px';
    const maxLegend = Math.min(labels.length, 12);

    for (let i = 0; i < maxLegend; i++){
      const chip = document.createElement('span');
      chip.style.display = 'inline-flex';
      chip.style.alignItems = 'center';
      chip.style.gap = '6px';
      chip.style.fontSize = '12px';
      const dot = document.createElement('span');
      dot.style.width = '10px';
      dot.style.height = '10px';
      dot.style.borderRadius = '50%';
      dot.style.background = colorFor(labels[i]);
      chip.appendChild(dot);
      chip.appendChild(document.createTextNode(labels[i]));
      legend.appendChild(chip);
    }
    host.appendChild(legend);
  }

  draw();
  window.addEventListener('resize', () => { draw(); }, { passive:true });

})();



/* ====== Dropdown “Azioni” ====== */
(function(){
  const btn  = document.querySelector('.actions-trigger');
  const menu = document.getElementById('menuAzioni');
  if(!btn || !menu) return;


  function openMenu(){ menu.classList.add('open'); btn.setAttribute('aria-expanded','true'); }
  function closeMenu(){ menu.classList.remove('open'); btn.setAttribute('aria-expanded','false'); }
  function toggleMenu(){ if (menu.classList.contains('open')) closeMenu(); else openMenu(); }

  btn.addEventListener('click', function(e){ e.stopPropagation(); toggleMenu(); });
  btn.addEventListener('keydown', function(e){
    if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' '){ e.preventDefault(); openMenu(); }
  });
  document.addEventListener('click', function(e){
    if (!menu.classList.contains('open')) return;
    if (!menu.contains(e.target) && !btn.contains(e.target)) closeMenu();
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && menu.classList.contains('open')) { closeMenu(); btn.focus(); }
  });
})();

