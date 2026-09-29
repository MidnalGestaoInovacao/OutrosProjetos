/* EB Crédito Rural — portal (máscaras, força de senha, contagem regressiva, upload via REST nas pendências). Sem dependências. */
(function () {
  'use strict';
  var cfg = window.EBCR || { rest: '', nonce: '', i18n: {} };
  var i18n = cfg.i18n || {};

  function digits(v) { return (v || '').replace(/\D+/g, ''); }
  function maskCpf(v) { v = digits(v).slice(0, 11); return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2'); }
  function maskCnpj(v) { v = digits(v).slice(0, 14); return v.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2'); }
  function maskCep(v) { v = digits(v).slice(0, 8); return v.replace(/^(\d{5})(\d)/, '$1-$2'); }
  function maskPhone(v) { v = digits(v).slice(0, 11); if (v.length > 10) { return v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3'); } return v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, function (m, a, b, c) { return '(' + a + ') ' + b + (c ? '-' + c : ''); }); }
  function maskMoney(v) { var n = digits(v); if (!n) { return ''; } n = n.replace(/^0+(?=\d)/, ''); while (n.length < 3) { n = '0' + n; } var int = n.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); return int + ',' + n.slice(-2); }
  function maskDecimal(v) { v = (v || '').replace(/[^\d,\.]/g, ''); return v; }

  function applyMask(el) {
    var t = el.getAttribute('data-mask');
    var map = { cpf: maskCpf, cnpj: maskCnpj, cep: maskCep, phone: maskPhone, money: maskMoney, decimal: maskDecimal };
    if (!map[t]) { return; }
    var fn = map[t];
    el.addEventListener('input', function () { var s = el.selectionStart; el.value = fn(el.value); if (t !== 'money') { try { el.setSelectionRange(s, s); } catch (e) {} } });
    if (el.value) { el.value = fn(el.value); }
  }

  function validCpf(cpf) {
    var d = digits(cpf); if (d.length !== 11 || /^(\d)\1{10}$/.test(d)) { return false; }
    for (var t = 9; t < 11; t++) { var sum = 0; for (var i = 0; i < t; i++) { sum += parseInt(d[i], 10) * (t + 1 - i); } var dig = ((10 * sum) % 11) % 10; if (parseInt(d[t], 10) !== dig) { return false; } }
    return true;
  }
  function validCnpj(cnpj) {
    var d = digits(cnpj); if (d.length !== 14 || /^(\d)\1{13}$/.test(d)) { return false; }
    var w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], w2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    var calc = function (w) { var s = 0; for (var i = 0; i < w.length; i++) { s += parseInt(d[i], 10) * w[i]; } var r = s % 11; return r < 2 ? 0 : 11 - r; };
    return parseInt(d[12], 10) === calc(w1) && parseInt(d[13], 10) === calc(w2);
  }
  function validCar(v) { return /^[A-Z]{2}-\d{7}-([A-F0-9]{4}\.){7}[A-F0-9]{4}$/.test((v || '').toUpperCase().trim()); }

  function setFieldError(el, msg) {
    var wrap = el.closest('.ebcr-field'); if (!wrap) { return; }
    var err = wrap.querySelector('.ebcr-error');
    if (msg) {
      wrap.classList.add('has-error'); el.setAttribute('aria-invalid', 'true');
      if (!err) { err = document.createElement('span'); err.className = 'ebcr-error'; err.setAttribute('role', 'alert'); err.id = el.id + '-error'; wrap.appendChild(err); }
      err.textContent = msg;
    } else if (err && err.getAttribute('data-server') !== '1') {
      wrap.classList.remove('has-error'); el.removeAttribute('aria-invalid'); err.remove();
    }
  }
  window.EBCR_setFieldError = setFieldError;

  function bindValidate(el) {
    var kind = el.getAttribute('data-validate');
    el.addEventListener('blur', function () {
      var v = el.value.trim(); if (!v) { setFieldError(el, ''); return; }
      var ok = kind === 'cpf' ? validCpf(v) : kind === 'cnpj' ? validCnpj(v) : kind === 'car' ? validCar(v) : true;
      setFieldError(el, ok ? '' : (i18n.invalid || 'Valor inválido.'));
    });
  }

  function strength(pw) {
    var score = 0; if (pw.length >= 10) { score++; } if (pw.length >= 14) { score++; }
    var classes = [/[a-z]/, /[A-Z]/, /\d/, /[^a-zA-Z\d]/].filter(function (r) { return r.test(pw); }).length;
    if (classes >= 3) { score++; } if (classes === 4) { score++; }
    return pw.length < 8 ? 1 : score <= 1 ? 1 : score <= 3 ? 2 : 3;
  }
  function bindStrength(el) {
    var meter = el.closest('form') && el.closest('form').querySelector('[data-strength-meter]'); if (!meter) { return; }
    var bar = meter.querySelector('i'), label = meter.querySelector('.ebcr-strength-label');
    el.addEventListener('input', function () {
      if (!el.value) { meter.hidden = true; return; }
      var s = strength(el.value); meter.hidden = false; meter.setAttribute('data-level', s);
      bar.style.width = (s * 33) + '%'; label.textContent = s === 1 ? (i18n.weak || 'Fraca') : s === 2 ? (i18n.medium || 'Média') : (i18n.strong || 'Forte');
    });
  }

  function countdown(el) {
    var left = parseInt(el.getAttribute('data-countdown'), 10) || 0;
    function tick() {
      if (left <= 0) { el.textContent = ''; window.location.reload(); return; }
      var d = Math.floor(left / 86400), h = Math.floor((left % 86400) / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
      el.textContent = (d ? d + 'd ' : '') + ('0' + h).slice(-2) + ':' + ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2);
      left--; setTimeout(tick, 1000);
    }
    tick();
  }

  // Upload via REST (progresso) — usado no portal (pendências) e no wizard (etapa 6).
  function bindUpload(form) {
    if (!cfg.rest || !window.FormData || !window.XMLHttpRequest) { return; }
    var idInput = form.querySelector('input[name="id"]'); if (!idInput) { return; }
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var file = form.querySelector('input[type=file]'); var status = form.querySelector('.ebcr-upload-status'); var btn = form.querySelector('button[type=submit]');
      if (!file || !file.files || !file.files.length) { return; }
      var max = (window.EBCR_WIZARD && window.EBCR_WIZARD.max_mb) ? window.EBCR_WIZARD.max_mb : 10;
      if (file.files[0].size > max * 1024 * 1024) { status.className = 'ebcr-upload-status is-error'; status.textContent = 'Arquivo maior que ' + max + ' MB.'; return; }
      var fd = new FormData(); fd.append('file', file.files[0]); fd.append('doc_type', form.getAttribute('data-doc-type') || ''); fd.append('ref_key', form.getAttribute('data-ref-key') || ''); fd.append('request_id', form.getAttribute('data-request-id') || '0');
      var xhr = new XMLHttpRequest(); xhr.open('POST', cfg.rest + 'submissions/' + idInput.value + '/documents'); xhr.setRequestHeader('X-WP-Nonce', cfg.nonce);
      btn.disabled = true; status.className = 'ebcr-upload-status'; status.textContent = (i18n.sending || 'Enviando…') + ' 0%';
      xhr.upload.onprogress = function (e) { if (e.lengthComputable) { status.textContent = (i18n.sending || 'Enviando…') + ' ' + Math.round(100 * e.loaded / e.total) + '%'; } };
      xhr.onload = function () {
        btn.disabled = false;
        var res = null; try { res = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status >= 200 && xhr.status < 300 && res && res.id) {
          status.className = 'ebcr-upload-status is-ok'; status.textContent = '✔ ' + res.name;
          file.value = '';
          document.dispatchEvent(new CustomEvent('ebcr:uploaded', { detail: { form: form, doc: res } }));
        } else {
          status.className = 'ebcr-upload-status is-error'; status.textContent = (res && res.message) ? res.message : (i18n.error || 'Erro.');
        }
      };
      xhr.onerror = function () { btn.disabled = false; status.className = 'ebcr-upload-status is-error'; status.textContent = i18n.error || 'Erro.'; };
      xhr.send(fd);
    });
  }

  function init(root) {
    root = root || document;
    root.querySelectorAll('[data-mask]').forEach(applyMask);
    root.querySelectorAll('[data-validate]').forEach(bindValidate);
    root.querySelectorAll('[data-strength]').forEach(bindStrength);
    root.querySelectorAll('[data-countdown]').forEach(countdown);
    root.querySelectorAll('[data-ebcr-upload]').forEach(bindUpload);
    root.querySelectorAll('.ebcr-error').forEach(function (e) { e.setAttribute('data-server', '1'); });
    var summary = root.querySelector('#ebcr-error-summary'); if (summary) { summary.focus(); }
  }
  window.EBCR_init = init;
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { init(document); }); } else { init(document); }
})();
