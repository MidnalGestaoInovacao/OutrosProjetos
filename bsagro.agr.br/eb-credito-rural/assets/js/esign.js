/* EB Crédito Rural — assinatura eletrônica: contagem regressiva do código, reenvio e assinatura via REST (o formulário continua funcionando sem JS). */
(function () {
  'use strict';
  var cfg = window.EBCR || { rest: '', nonce: '', i18n: {} };
  var i18n = cfg.i18n || {};
  var root = document.querySelector('[data-ebcr-esign]'); if (!root) { return; }
  var id = root.getAttribute('data-id'), doc = root.getAttribute('data-doc');

  function post(path, body) {
    return fetch(cfg.rest + path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); });
  }
  function setStatus(el, text, cls) { if (!el) { return; } el.className = 'ebcr-upload-status' + (cls ? ' ' + cls : ''); el.textContent = text || ''; }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  var timer = null;
  function countdown(seconds) {
    var el = root.querySelector('[data-esign-countdown]'); if (!el) { return; }
    if (timer) { clearInterval(timer); }
    var left = seconds;
    function tick() {
      if (left <= 0) { el.textContent = '00:00'; clearInterval(timer); var btn = root.querySelector('[data-esign-submit]'); if (btn) { btn.disabled = true; } var st = root.querySelector('[data-esign-sign] [data-esign-status]'); setStatus(st, 'Código expirado. Peça um novo código.', 'is-error'); return; }
      el.textContent = pad(Math.floor(left / 60)) + ':' + pad(left % 60); left--;
    }
    tick(); timer = setInterval(tick, 1000);
  }
  var cd = root.querySelector('[data-esign-countdown]');
  if (cd) { countdown(parseInt(cd.getAttribute('data-esign-countdown'), 10) || 0); }

  // Só dígitos no código.
  var code = root.querySelector('input[name="codigo"]');
  if (code) { code.addEventListener('input', function () { code.value = code.value.replace(/\D+/g, '').slice(0, 6); }); code.focus(); }

  // Reenvio de código sem recarregar (o pedido inicial usa o formulário normal para o servidor montar a tela do passo 2).
  root.querySelectorAll('[data-esign-request]').forEach(function (form) {
    if (root.getAttribute('data-state') !== 'code' || !cfg.rest) { return; }
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = form.querySelector('button[type=submit]'), st = form.querySelector('[data-esign-status]');
      btn.disabled = true; setStatus(st, i18n.sending || 'Enviando…');
      post('esign/' + id + '/' + doc + '/code').then(function (r) {
        if (r.ok && r.json && r.json.ok) {
          setStatus(st, r.json.message || 'Código reenviado.', 'is-ok'); countdown(r.json.expires_in || 0);
          var submit = root.querySelector('[data-esign-submit]'); if (submit) { submit.disabled = false; }
          setTimeout(function () { btn.disabled = false; }, 30000);
        } else { setStatus(st, (r.json && r.json.message) ? r.json.message : (i18n.error || 'Erro.'), 'is-error'); btn.disabled = false; }
      }).catch(function () { setStatus(st, i18n.error || 'Erro.', 'is-error'); btn.disabled = false; });
    });
  });

  // Assinatura via REST com erros por campo; sucesso redireciona para a tela de conclusão.
  var sign = root.querySelector('[data-esign-sign]');
  if (sign && cfg.rest && window.EBCR_setFieldError) {
    sign.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = sign.querySelector('[data-esign-submit]'), st = sign.querySelector('[data-esign-status]');
      sign.querySelectorAll('.ebcr-error').forEach(function (e) { e.remove(); }); sign.querySelectorAll('.has-error').forEach(function (e) { e.classList.remove('has-error'); });
      var body = { codigo: (sign.querySelector('input[name="codigo"]') || {}).value || '', nome_confirmacao: (sign.querySelector('input[name="nome_confirmacao"]') || {}).value || '', aceite: (sign.querySelector('input[name="aceite"]') || {}).checked ? '1' : '' };
      btn.disabled = true; setStatus(st, i18n.sending || 'Enviando…');
      post('esign/' + id + '/' + doc + '/sign', body).then(function (r) {
        if (r.ok && r.json && r.json.ok) { setStatus(st, '✔ ' + (r.json.message || ''), 'is-ok'); window.location.href = r.json.redirect || root.getAttribute('data-redirect'); return; }
        btn.disabled = false;
        var errors = (r.json && r.json.data && r.json.data.errors) ? r.json.data.errors : {};
        var general = errors._ || (r.json && r.json.message) || (i18n.error || 'Erro.'); var shown = 0;
        Object.keys(errors).forEach(function (k) { var el = sign.querySelector('[name="' + k + '"]'); if (el && k !== '_') { window.EBCR_setFieldError(el, errors[k]); shown++; } });
        setStatus(st, shown && !errors._ ? '' : general, 'is-error');
        var first = sign.querySelector('.has-error input'); if (first) { first.focus(); }
      }).catch(function () { btn.disabled = false; setStatus(st, i18n.error || 'Erro.', 'is-error'); });
    });
  }
})();
