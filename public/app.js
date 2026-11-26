/*
 * Agora — optional comfort, about 1 KB. Every page works without this file:
 * forms, menus and confirmations all have a plain-HTML fallback.
 */
(function () {
  'use strict';
  var d = document;
  d.documentElement.classList.add('js');

  // Ask before deleting. (Without JavaScript, the server shows a confirmation page.)
  d.addEventListener('submit', function (e) {
    var form = e.target, question = form.getAttribute('data-confirm');
    if (!question) return;
    if (!window.confirm(question)) { e.preventDefault(); return; }
    var confirmed = form.querySelector('input[name="confirmed"]');
    if (confirmed) confirmed.value = '1';
  });

  // Filters and status menus apply as soon as they change (no "Filter" button).
  d.addEventListener('change', function (e) {
    var el = e.target, form = el.form;
    if (el.tagName === 'SELECT' && form && /\b(auto|move)\b/.test(form.className)) {
      if (form.requestSubmit) form.requestSubmit(); else form.submit();
    }
  });

  // Close the user menu when clicking elsewhere or pressing Escape.
  function closeMenus(keep) {
    d.querySelectorAll('details.usermenu[open]').forEach(function (m) { if (m !== keep) m.removeAttribute('open'); });
  }
  d.addEventListener('click', function (e) { closeMenus(e.target.closest ? e.target.closest('details.usermenu') : null); });
  d.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMenus(null);
    // "/" jumps to the search field.
    var tag = d.activeElement ? d.activeElement.tagName : '';
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(tag)) {
      var search = d.querySelector('.top .search input');
      if (search && search.offsetParent) { e.preventDefault(); search.focus(); }
    }
  });
})();
