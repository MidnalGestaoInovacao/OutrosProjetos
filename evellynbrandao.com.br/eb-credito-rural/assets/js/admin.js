/* EB Crédito Rural — admin: abas, confirmações, matriz de documentos, chave de criptografia. */
(function () {
  'use strict';
  document.querySelectorAll('.ebcr-tabs-nav').forEach(function (nav) {
    var links = nav.querySelectorAll('a[data-tab]'); var panels = document.querySelectorAll('.ebcr-tabpanel');
    function show(id, push) { links.forEach(function (l) { l.classList.toggle('nav-tab-active', l.getAttribute('data-tab') === id); }); panels.forEach(function (p) { p.hidden = p.id !== 'ebcr-tab-' + id; }); if (push && window.history.replaceState) { var u = new URL(window.location.href); u.searchParams.set('tab', id); window.history.replaceState(null, '', u.toString()); } }
    links.forEach(function (l) { l.addEventListener('click', function (e) { e.preventDefault(); show(l.getAttribute('data-tab'), true); }); });
    var cur = new URL(window.location.href).searchParams.get('tab'); var first = links[0] && links[0].getAttribute('data-tab');
    show(cur && nav.querySelector('a[data-tab="' + cur + '"]') ? cur : first, false);
  });
  document.querySelectorAll('[data-confirm]').forEach(function (el) { el.addEventListener('click', function (e) { if (!window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); } }); });
  var matrix = document.querySelector('[data-matrix]');
  if (matrix) {
    var tpl = matrix.querySelector('template'); var add = matrix.querySelector('[data-matrix-add]');
    add && add.addEventListener('click', function () { var i = matrix.querySelectorAll('tbody tr').length; var tr = document.createElement('tbody'); tr.innerHTML = tpl.innerHTML.replace(/__i__/g, i); matrix.querySelector('tbody').appendChild(tr.firstElementChild); });
    matrix.addEventListener('click', function (e) { if (e.target.matches('[data-matrix-remove]')) { e.target.closest('tr').remove(); } });
  }
  document.querySelectorAll('[data-status-select]').forEach(function (sel) {
    var internal = document.querySelector('[data-comment-internal]'); var note = document.querySelector('[data-requires-comment]');
    function upd() { var opt = sel.options[sel.selectedIndex]; var req = opt && opt.getAttribute('data-requires-comment') === '1'; if (internal) { internal.required = req; } if (note) { note.hidden = !req; } }
    sel.addEventListener('change', upd); upd();
  });
})();
