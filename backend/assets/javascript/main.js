/* main.js — gestione dropdown/nav riutilizzabile
   Attiva su qualunque trigger che abbia: aria-controls="ID_DEL_MENU"
   Opzionale: data-menu-close-on-select (chiude al click su una voce)
*/

(function () {
  function qsAll(root, sel) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function getMenuItems(menu) {
    return qsAll(menu, '[role="menuitem"], a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
  }

  function initDropdown(trigger) {
    var menuId = trigger.getAttribute('aria-controls');
    if (!menuId) return;
    var menu = document.getElementById(menuId);
    if (!menu) return;

    // Stato
    function isOpen() { return menu.classList.contains('open'); }
    function open()   { menu.classList.add('open'); trigger.setAttribute('aria-expanded','true'); }
    function close()  { menu.classList.remove('open'); trigger.setAttribute('aria-expanded','false'); }
    function toggle() { isOpen() ? close() : open(); }

    // Click esterno
    function onDocClick(e) {
      if (!isOpen()) return;
      if (!menu.contains(e.target) && !trigger.contains(e.target)) close();
    }

    // Tastiera
    function onTriggerKey(e) {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault(); open();
        var items = getMenuItems(menu);
        if (items.length) items[0].focus();
      } else if (e.key === 'Escape') {
        close(); trigger.focus();
      }
    }

    function onMenuKey(e) {
      if (!isOpen()) return;
      var items = getMenuItems(menu);
      if (!items.length) return;

      var idx = items.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        items[(idx + 1 + items.length) % items.length].focus();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        items[(idx - 1 + items.length) % items.length].focus();
      } else if (e.key === 'Home') {
        e.preventDefault(); items[0].focus();
      } else if (e.key === 'End') {
        e.preventDefault(); items[items.length - 1].focus();
      } else if (e.key === 'Escape') {
        e.preventDefault(); close(); trigger.focus();
      }
    }

    // Chiudi al click su una voce, se richiesto
    function onItemClick(e) {
      if (trigger.hasAttribute('data-menu-close-on-select')) close();
    }

    // Bind
    trigger.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
    trigger.addEventListener('keydown', onTriggerKey);
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', function (e) {
      if (menu.contains(document.activeElement)) onMenuKey(e);
    });
    getMenuItems(menu).forEach(function (el) { el.addEventListener('click', onItemClick); });

    // Accessibilità: assicura ARIA di base
    if (!menu.hasAttribute('role')) menu.setAttribute('role', 'menu');
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Inizializza:
    // - qualunque elemento con data-menu-trigger
    // - fallback per markup esistente: .actions-trigger con aria-controls
    var triggers = qsAll(document, '[data-menu-trigger], .actions-trigger[aria-controls]');
    triggers.forEach(initDropdown);
  });
})();
