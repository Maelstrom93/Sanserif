// assets/javascript/main.js
(function () {
  const DEFAULTS = {
    breakpoint: 900,
    scrollOffset: 50,
    lockBody: true,
    toggleBodyMenuClass: true,
    hideLogoWhenOpen: true
  };

  const $ = (sel, root = document) => root.querySelector(sel);
  const $all = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  function initNavbar(opts = {}) {
    const o = Object.assign({}, DEFAULTS, (window.SS_NAV_OPTS || {}), opts);

    const nav      = $('#navbar');
    const btn      = $('#hamburger');
    const hambIcon = $('#hamb-icon');
    let   overlay  = $('#nav-overlay');
    const body     = document.body;

    if (!nav || !btn) return;

    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'nav-overlay';
      overlay.className = 'nav-overlay';
      overlay.hidden = true;
      const header = nav.closest('header') || nav;
      header.appendChild(overlay);
    }

    function onScroll() {
      if (window.scrollY > o.scrollOffset) nav.classList.add('scrolled');
      else nav.classList.remove('scrolled');
    }
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    let lastFocused = null;
    function toggleMenu(force) {
      const willOpen = (typeof force === 'boolean') ? force : !nav.classList.contains('open');

      nav.classList.toggle('open', willOpen);
      btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      overlay.hidden = !willOpen;

      if (o.lockBody) body.style.overflow = willOpen ? 'hidden' : '';
      if (o.toggleBodyMenuClass) body.classList.toggle('menu-open', willOpen);

      if (hambIcon) {
        if (willOpen) {
          if (hambIcon.classList.contains('fa-bars')) hambIcon.classList.replace('fa-bars', 'fa-xmark');
          else hambIcon.classList.add('fa-xmark');
        } else {
          if (hambIcon.classList.contains('fa-xmark')) hambIcon.classList.replace('fa-xmark', 'fa-bars');
          else hambIcon.classList.add('fa-bars');
        }
      }

      if (willOpen) {
        lastFocused = document.activeElement;
        btn.focus();
        document.addEventListener('keydown', onKeydown);
      } else {
        document.removeEventListener('keydown', onKeydown);
        if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
        else btn.focus();
      }
    }

    function onKeydown(e) {
      if (!nav.classList.contains('open')) return;
      if (e.key === 'Escape') { toggleMenu(false); return; }
      if (e.key !== 'Tab') return;

      const scope = nav;
      const focusable = $all('a[href], button, [tabindex]:not([tabindex="-1"])', scope)
        .filter(el => !el.hasAttribute('disabled') && el.offsetParent !== null);

      if (focusable.length === 0) return;

      const first = focusable[0];
      const last  = focusable[focusable.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault(); last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus();
      }
    }

    btn.addEventListener('click', () => toggleMenu());
    overlay.addEventListener('click', () => toggleMenu(false));
    window.addEventListener('resize', () => {
      if (window.innerWidth > o.breakpoint && nav.classList.contains('open')) toggleMenu(false);
    });

    document.addEventListener('click', (e) => {
      if (!nav.classList.contains('open')) return;
      const inside = e.target.closest('#nav-list') || e.target.closest('#hamburger');
      if (!inside) toggleMenu(false);
    });
  }

  window.SS = window.SS || {};
  window.SS.initNavbar = initNavbar;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initNavbar());
  } else {
    initNavbar();
  }
})();
