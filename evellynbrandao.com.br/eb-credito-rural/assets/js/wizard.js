/* EB Crédito Rural — formulário em etapas: campos condicionais, grupos repetíveis, autosave, envio via REST. */
(function () {
  'use strict';
  var cfg = window.EBCR || { rest: '', nonce: '', i18n: {} };
  var W = window.EBCR_WIZARD || {};
  var i18n = cfg.i18n || {};
  var root = document.querySelector('[data-ebcr-wizard]'); if (!root) { return; }
  var statusEl = root.querySelector('[data-autosave-status]');

  function fieldValues(form, name) {
    var els = form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]'); var out = [];
    els.forEach(function (el) { if ((el.type === 'radio' || el.type === 'checkbox')) { if (el.checked) { out.push(el.value); } } else { out.push(el.value); } });
    return out;
  }
  function applyConditions(form) {
    form.querySelectorAll('[data-show-if]').forEach(function (box) {
      var rule = box.getAttribute('data-show-if'); var eq = rule.indexOf('='); var name = rule.slice(0, eq); var wanted = rule.slice(eq + 1).split(',');
      var vals = fieldValues(form, name); var show = vals.some(function (v) { return wanted.indexOf(v) !== -1; });
      box.hidden = !show;
      box.querySelectorAll('input,select,textarea').forEach(function (el) { if (show) { el.removeAttribute('data-was-required') && el.setAttribute('required', ''); if (el.getAttribute('data-was-required') === '1') { el.setAttribute('required', ''); el.removeAttribute('data-was-required'); } } else if (el.required) { el.setAttribute('data-was-required', '1'); el.removeAttribute('required'); } });
    });
  }
  function bindRepeat(block) {
    var tpl = block.querySelector('[data-repeat-template]'); var addBtn = block.querySelector('[data-add-row]');
    if (addBtn && tpl) {
      addBtn.type = 'button'; addBtn.removeAttribute('name');
      addBtn.addEventListener('click', function () {
        var i = block.querySelectorAll('.ebcr-repeat-row').length; var html = tpl.innerHTML.replace(/__i__/g, i);
        var div = document.createElement('div'); div.innerHTML = html; var row = div.firstElementChild;
        tpl.parentNode.insertBefore(row, tpl); window.EBCR_init && window.EBCR_init(row); bindRemove(row); applyConditions(block.closest('form')); var first = row.querySelector('input,select,textarea'); if (first) { first.focus(); }
      });
    }
    block.querySelectorAll('.ebcr-repeat-row').forEach(bindRemove);
  }
  function bindRemove(row) {
    var btn = row.querySelector('[data-remove-row]'); if (!btn) { return; }
    btn.addEventListener('click', function () { var block = row.closest('[data-repeat]'); row.remove(); if (block && !block.querySelector('.ebcr-repeat-row')) { var add = block.querySelector('[data-add-row]'); if (add) { add.click(); } } sumAreas(); });
  }
  function serialize(form) {
    var data = {}; var fd = new FormData(form);
    fd.forEach(function (v, k) {
      if (k === 'ebcr_nonce' || k === '_wp_http_referer' || k === 'action') { return; }
      var m = k.match(/^([^\[]+)((\[[^\]]*\])+)$/);
      if (!m) { if (k.slice(-2) === '[]') { var kk = k.slice(0, -2); (data[kk] = data[kk] || []).push(v); } else { data[k] = v; } return; }
      var parts = [m[1]].concat(m[2].slice(1, -1).split(']['));
      var cur = data; parts.forEach(function (p, idx) { if (idx === parts.length - 1) { if (p === '') { (cur = cur) && (Array.isArray(cur) ? cur.push(v) : (cur[p] = v)); } else { cur[p] = v; } } else { cur[p] = cur[p] || (/^\d+$/.test(parts[idx + 1]) ? [] : {}); cur = cur[p]; } });
    });
    return data;
  }
  function post(path, body) {
    return fetch(cfg.rest + path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce }, body: JSON.stringify(body) }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j }; }); });
  }
  function showErrors(form, errors) {
    form.querySelectorAll('.ebcr-error').forEach(function (e) { e.remove(); }); form.querySelectorAll('.has-error').forEach(function (e) { e.classList.remove('has-error'); });
    var old = form.querySelector('#ebcr-error-summary'); if (old) { old.remove(); }
    var keys = Object.keys(errors || {}); if (!keys.length) { return; }
    var box = document.createElement('div'); box.className = 'ebcr-alert ebcr-alert--error'; box.id = 'ebcr-error-summary'; box.setAttribute('role', 'alert'); box.tabIndex = -1;
    var ul = document.createElement('ul'); keys.forEach(function (k) { var li = document.createElement('li'); li.textContent = errors[k]; ul.appendChild(li); var name = k.replace(/\.(\d+)\./g, '[$1][').replace(/\.([^.\[]+)$/, '[$1]'); if (/\[/.test(name)) { name = name.replace(/\[([^\]]+)$/, '[$1]'); } var el = form.querySelector('[name="' + name + '"]') || form.querySelector('[name="' + k + '"]') || form.querySelector('[name="' + k + '[]"]'); if (el && el.closest('.ebcr-field')) { window.EBCR_setFieldError(el, errors[k]); } });
    box.innerHTML = '<strong>Corrija os itens abaixo:</strong>'; box.appendChild(ul); form.insertBefore(box, form.firstChild); box.focus();
  }
  function sumAreas() {
    var form = root.querySelector('[data-usable-area]'); if (!form) { return; }
    var usable = parseFloat(form.getAttribute('data-usable-area')) || 0; var sum = 0;
    form.querySelectorAll('[data-sum="area"]').forEach(function (el) { var v = parseFloat((el.value || '').replace(/\./g, '').replace(',', '.')); if (!isNaN(v)) { sum += v; } });
    var warn = form.querySelector('[data-area-warning]'); if (warn) { warn.hidden = !(usable > 0 && sum > usable + 0.01); }
  }

  root.querySelectorAll('form[data-ebcr-step]').forEach(function (form) {
    var step = parseInt(form.getAttribute('data-ebcr-step'), 10);
    form.addEventListener('change', function () { applyConditions(form); sumAreas(); }); form.addEventListener('input', sumAreas);
    applyConditions(form); sumAreas();
    form.querySelectorAll('[data-repeat]').forEach(bindRepeat);
    var dirty = false; form.addEventListener('input', function () { dirty = true; });
    if (cfg.rest && W.id) {
      setInterval(function () { if (!dirty) { return; } dirty = false; if (statusEl) { statusEl.textContent = i18n.saving || 'Salvando…'; } post('submissions/' + W.id + '/step/' + step, Object.assign(serialize(form), { draft: 1 })).then(function () { if (statusEl) { statusEl.textContent = i18n.saved || 'Rascunho salvo.'; } }).catch(function () { if (statusEl) { statusEl.textContent = ''; } }); }, (W.autosave || 40) * 1000);
      form.addEventListener('submit', function (ev) {
        var submitter = ev.submitter; if (submitter && (submitter.name === 'ebcr_back' || submitter.name === 'ebcr_save' || submitter.name === 'ebcr_add')) { return; } // fluxo sem JS para essas ações
        ev.preventDefault(); var btn = form.querySelector('[data-next]'); if (btn) { btn.disabled = true; }
        if (statusEl) { statusEl.textContent = i18n.saving || 'Salvando…'; }
        post('submissions/' + W.id + '/step/' + step, serialize(form)).then(function (r) {
          if (btn) { btn.disabled = false; }
          if (r.ok && r.json && r.json.ok) { dirty = false; window.location.href = W.next_url; return; }
          if (statusEl) { statusEl.textContent = ''; }
          showErrors(form, (r.json && r.json.errors) ? r.json.errors : { _: (r.json && r.json.message) || i18n.error });
        }).catch(function () { if (btn) { btn.disabled = false; } form.submit(); });
      });
    }
  });

  // Etapa 6: atualizar lista/progresso após upload via REST.
  document.addEventListener('ebcr:uploaded', function (ev) {
    var form = ev.detail.form; var doc = ev.detail.doc; var slot = form.closest('.ebcr-slot');
    var list = slot ? slot.querySelector('[data-slot-docs]') : null;
    if (list) { var li = document.createElement('li'); li.className = 'ebcr-doc'; var a = document.createElement('a'); a.href = doc.download_url; a.textContent = doc.name; li.appendChild(a); li.appendChild(document.createTextNode(' ')); var s = document.createElement('span'); s.className = 'ebcr-review ebcr-review--pendente'; s.textContent = 'Em conferência'; li.appendChild(s); list.appendChild(li); slot.classList.add('is-done'); slot.classList.remove('is-required'); }
    var slots = root.querySelectorAll('.ebcr-slot[data-required="1"]'); var done = 0; slots.forEach(function (sl) { if (sl.classList.contains('is-done')) { done++; } });
    var bar = root.querySelector('[data-doc-progress] span'); var txt = root.querySelector('[data-doc-progress-text]');
    if (bar && slots.length) { bar.style.width = Math.round(100 * done / slots.length) + '%'; }
    if (txt && slots.length) { txt.textContent = done + ' de ' + slots.length + ' documentos obrigatórios enviados'; }
    if (form.classList.contains('ebcr-pending')) { form.classList.add('is-done'); }
  });

  // Remoção de documento via REST.
  root.querySelectorAll('[data-ebcr-delete]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!cfg.rest) { return; } ev.preventDefault();
      if (!window.confirm(i18n.confirm_delete || 'Remover?')) { return; }
      fetch(cfg.rest + 'documents/' + f.getAttribute('data-ebcr-delete'), { method: 'DELETE', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } }).then(function (r) { if (r.ok) { var li = f.closest('li'); if (li) { li.remove(); } } else { f.submit(); } }).catch(function () { f.submit(); });
    });
  });

  // Etapa 7: envio via REST com tratamento de erros.
  var submitForm = root.querySelector('form[data-ebcr-submit]');
  if (submitForm && cfg.rest && W.id) {
    submitForm.addEventListener('submit', function (ev) {
      ev.preventDefault(); var btn = submitForm.querySelector('button[type=submit]'); btn.disabled = true;
      post('submissions/' + W.id + '/submit', serialize(submitForm)).then(function (r) {
        if (r.ok && r.json && r.json.redirect) { window.location.href = r.json.redirect; return; }
        btn.disabled = false;
        var errors = {}; if (r.json && r.json.data && r.json.data.steps) { Object.keys(r.json.data.steps).forEach(function (st) { errors['step_' + st] = 'Etapa ' + st + ': ' + Object.values(r.json.data.steps[st]).join(' '); }); }
        errors._ = (r.json && r.json.message) || i18n.error; showErrors(submitForm, errors);
        // renova o captcha (uso único)
        fetch(cfg.rest + 'captcha', { method: 'POST', headers: { 'X-WP-Nonce': cfg.nonce } }).then(function (x) { return x.json(); }).then(function (c) { var q = submitForm.querySelector('.ebcr-captcha-q'); var t = submitForm.querySelector('[name=ebcr_captcha_token]'); var a = submitForm.querySelector('[name=ebcr_captcha_answer]'); if (q && t && a) { q.textContent = c.question + ' = ?'; t.value = c.token; a.value = ''; a.setAttribute('aria-label', c.aria); } });
      }).catch(function () { submitForm.submit(); });
    });
  }
})();
