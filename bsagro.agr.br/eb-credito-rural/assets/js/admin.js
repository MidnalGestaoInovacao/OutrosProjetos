/* EB Crédito Rural — admin: abas, confirmações, matriz de documentos, chave de criptografia, identidade visual
   (predefinições, seletor de cor, logotipo pela biblioteca de mídia e verificação de contraste WCAG ao vivo). */
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

  /* ---------------------------------------------------------------- identidade visual */
  function parseColor(v) {
    v = (v || '').trim().toLowerCase(); if (!v) { return null; }
    if (v === 'transparent') { return [0, 0, 0, 0]; }
    var m = v.match(/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/);
    if (m) { var h = m[1]; if (h.length <= 4) { h = h.replace(/(.)/g, '$1$1'); } return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16), h.length === 8 ? parseInt(h.substr(6, 2), 16) / 255 : 1]; }
    m = v.match(/^rgba?\(\s*([\d.]+%?)\s*[,\s]\s*([\d.]+%?)\s*[,\s]\s*([\d.]+%?)\s*(?:[,\/]\s*([\d.]+%?)\s*)?\)$/);
    if (m) { var c = [1, 2, 3].map(function (i) { var n = parseFloat(m[i]); if (/%$/.test(m[i])) { n *= 2.55; } return Math.max(0, Math.min(255, Math.round(n))); }); var a = 1; if (m[4]) { a = parseFloat(m[4]); if (/%$/.test(m[4])) { a /= 100; } } c.push(Math.max(0, Math.min(1, a))); return c; }
    return null;
  }
  function flatten(c, b) { b = b || [255, 255, 255, 1]; var a = c[3]; return [c[0] * a + b[0] * (1 - a), c[1] * a + b[1] * (1 - a), c[2] * a + b[2] * (1 - a), 1]; }
  function lum(c) { var l = c.slice(0, 3).map(function (x) { x /= 255; return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); }); return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2]; }
  function contrast(fg, bg) { var b = flatten(bg); var f = flatten(fg, b); var l1 = lum(f), l2 = lum(b); var hi = Math.max(l1, l2), lo = Math.min(l1, l2); return (hi + 0.05) / (lo + 0.05); }
  function toHex(c) { return '#' + c.slice(0, 3).map(function (x) { var h = Math.round(x).toString(16); return h.length < 2 ? '0' + h : h; }).join(''); }
  function val(id) { var el = document.getElementById('ebcr-' + id); return el ? el.value : ''; }
  function updateContrast() {
    var box = document.querySelector('[data-ebcr-contrast]'); if (!box) { return; }
    var bg = val('brand_bg') || val('brand_surface');
    var pairs = { primary: [val('brand_primary_contrast'), val('brand_primary')], text_bg: [val('brand_text'), bg], text_surface: [val('brand_text'), val('brand_surface')], muted_surface: [val('brand_muted'), val('brand_surface')], link_surface: [val('brand_accent_strong'), val('brand_surface')] };
    Object.keys(pairs).forEach(function (k) {
      var li = box.querySelector('[data-contrast-key="' + k + '"]'); if (!li) { return; }
      var f = parseColor(pairs[k][0]), b = parseColor(pairs[k][1]); var out = li.querySelector('[data-contrast-ratio]'); var st = li.querySelector('[data-contrast-status]'); var sample = li.querySelector('.ebcr-contrast-sample');
      if (!f || !b) { out.textContent = '—'; st.textContent = ''; return; }
      var r = contrast(f, b); out.textContent = r.toFixed(2).replace('.', ',') + ':1';
      var ok = r >= 4.5; st.className = ok ? 'ebcr-status-ok' : 'ebcr-status-warn'; st.textContent = ok ? '✔ adequado' : '⚠ abaixo de 4,5:1';
      if (sample) { sample.style.color = pairs[k][0]; sample.style.background = pairs[k][1]; }
    });
  }
  document.querySelectorAll('[data-ebcr-color]').forEach(function (input) {
    var picker = document.querySelector('[data-ebcr-color-picker="' + input.id + '"]');
    function syncPicker() { var c = parseColor(input.value); if (picker && c) { picker.value = toHex(flatten(c)); } }
    input.addEventListener('input', function () { syncPicker(); updateContrast(); });
    if (picker) { picker.addEventListener('input', function () { input.value = picker.value; updateContrast(); }); }
  });
  var presetSel = document.querySelector('[data-ebcr-presets]');
  if (presetSel) {
    var presets = {}; try { presets = JSON.parse(presetSel.getAttribute('data-ebcr-presets') || '{}'); } catch (e) { presets = {}; }
    var applyBtn = document.querySelector('[data-ebcr-preset-apply]'); var status = document.querySelector('[data-ebcr-preset-status]');
    applyBtn && applyBtn.addEventListener('click', function () {
      var p = presets[presetSel.value]; if (!p) { if (status) { status.textContent = 'Escolha uma predefinição.'; } return; }
      Object.keys(p).forEach(function (k) {
        var el = document.getElementById('ebcr-' + k); if (!el) { return; }
        if (el.type === 'checkbox') { el.checked = !!p[k]; } else { el.value = p[k] === null ? '' : String(p[k]); }
        el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      updateContrast(); if (status) { status.textContent = 'Campos preenchidos. Revise e clique em Salvar.'; }
    });
  }
  document.querySelectorAll('[data-ebcr-media]').forEach(function (box) {
    var input = box.querySelector('input[type=hidden]'); var img = box.querySelector('.ebcr-media-preview'); var choose = box.querySelector('[data-media-choose]'); var remove = box.querySelector('[data-media-remove]'); var frame = null;
    choose && choose.addEventListener('click', function () {
      if (!window.wp || !window.wp.media) { return; }
      if (!frame) {
        frame = window.wp.media({ title: choose.getAttribute('data-title'), button: { text: choose.getAttribute('data-button') }, library: { type: 'image' }, multiple: false });
        frame.on('select', function () { var att = frame.state().get('selection').first().toJSON(); input.value = att.id; var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url; img.src = url; img.hidden = false; if (remove) { remove.hidden = false; } });
      }
      frame.open();
    });
    remove && remove.addEventListener('click', function () { input.value = '0'; img.hidden = true; img.removeAttribute('src'); remove.hidden = true; });
  });
  updateContrast();
})();
