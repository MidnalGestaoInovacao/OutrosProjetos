/* EB Crédito Rural — simulador de crédito [ebcr_simulador]. Sem dependências. Mesmas convenções de EBCR\Frontend\Simulator::schedule(). */
(function () {
  'use strict';
  var brl = null;
  try { brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2, maximumFractionDigits: 2 }); } catch (e) { brl = null; }
  function money(v) {
    v = Math.round((Number(v) || 0) * 100) / 100;
    if (brl) { return brl.format(v).replace(/ /g, ' '); }
    var s = Math.abs(v).toFixed(2).split('.'); return (v < 0 ? '-' : '') + 'R$ ' + s[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + s[1];
  }
  function percent(v) { return (Math.round(v * 100) / 100).toFixed(2).replace('.', ',') + '%'; }
  function digits(v) { return (v || '').replace(/\D+/g, ''); }
  /* Aceita "300000", "300.000", "300.000,50" (pt-BR): sem vírgula = reais inteiros. */
  function parseMoney(v) { v = String(v || '').trim(); if (!v) { return 0; } if (v.indexOf(',') !== -1) { v = v.replace(/\./g, '').replace(',', '.'); } else { v = digits(v); } var n = parseFloat(v); return isNaN(n) ? 0 : n; }
  function fmtInput(v) { v = Math.round((Number(v) || 0) * 100) / 100; var s = v.toFixed(2).split('.'); return s[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + s[1]; }
  function clamp(v, min, max) { return Math.min(max, Math.max(min, v)); }
  function monthlyRate(annual) { return Math.pow(1 + annual / 100, 1 / 12) - 1; }

  /* Cronograma: carência = meses iniciais sem pagamento, juros capitalizados; prazo inclui a carência. */
  function schedule(amount, months, annual, system, grace) {
    amount = Math.max(0, Number(amount) || 0); months = Math.max(1, parseInt(months, 10) || 1);
    grace = Math.max(0, Math.min(parseInt(grace, 10) || 0, months - 1)); system = system === 'sac' ? 'sac' : 'price';
    var i = monthlyRate(Number(annual) || 0), n = months - grace, rows = [], bal = amount, paid = 0, k, interest;
    for (k = 1; k <= grace; k++) { interest = bal * i; bal += interest; rows.push({ n: k, grace: true, interest: interest, amort: 0, payment: 0, balance: bal }); }
    var pv = bal, amort = pv / n, pmt = i > 0 ? pv * i / (1 - Math.pow(1 + i, -n)) : pv / n, payment, ak;
    for (k = 1; k <= n; k++) {
      interest = bal * i;
      if (system === 'sac') { payment = amort + interest; ak = amort; } else { payment = pmt; ak = pmt - interest; }
      if (k === n) { ak = bal; payment = ak + interest; }
      bal -= ak; paid += payment;
      rows.push({ n: grace + k, grace: false, interest: interest, amort: ak, payment: payment, balance: Math.max(0, bal) });
    }
    return { rows: rows, first: rows[grace].payment, last: rows[rows.length - 1].payment, totalInterest: paid - amount, totalPaid: paid, monthly: i, cet: (Math.pow(1 + i, 12) - 1) * 100, grace: grace, months: months };
  }
  window.EBCR_simulate = schedule;

  function init(root) {
    var cfg = {};
    try { cfg = JSON.parse(root.getAttribute('data-config') || '{}'); } catch (e) { cfg = {}; }
    var q = function (sel) { return root.querySelector('[data-sim="' + sel + '"]'); };
    var amountEl = q('amount'), rangeEl = q('amount-range'), monthsEl = q('months'), graceEl = q('grace'), toggle = q('toggle'), toggleLabel = q('toggle-label'), rowsEl = q('rows'), countEl = q('count');
    var out = { first: q('first'), last: q('last'), interest: q('interest'), total: q('total'), cet: q('cet') };
    var min = Number(cfg.min) || 0, max = Number(cfg.max) || 1e9, maxTerm = parseInt(cfg.maxTerm, 10) || 600, preview = parseInt(cfg.preview, 10) || 12, rate = Number(cfg.rate) || 0;
    var state = { amount: clamp(Number(cfg.amount) || min, min, max), months: clamp(parseInt(cfg.term, 10) || 12, 1, maxTerm), grace: parseInt(cfg.grace, 10) || 0, system: cfg.system === 'sac' ? 'sac' : 'price', expanded: false };
    var labels = { mes: 'Mês', parcela: 'Parcela', juros: 'Juros', amort: 'Amortização', saldo: 'Saldo devedor' };
    var head = root.querySelectorAll('.ebcr-sim__table thead th'); if (head.length === 5) { labels = { mes: head[0].textContent, parcela: head[1].textContent, juros: head[2].textContent, amort: head[3].textContent, saldo: head[4].textContent }; }
    var graceLabel = (function () { var em = root.querySelector('tbody td em'); return em ? em.textContent : 'carência'; })();

    function cell(label, text) { var td = document.createElement('td'); td.setAttribute('data-label', label); td.textContent = text; return td; }
    function render() {
      var r = schedule(state.amount, state.months, rate, state.system, state.grace);
      if (out.first) { out.first.textContent = money(r.first); }
      if (out.last) { out.last.textContent = money(r.last); }
      if (out.interest) { out.interest.textContent = money(r.totalInterest); }
      if (out.total) { out.total.textContent = money(r.totalPaid); }
      if (out.cet) { out.cet.textContent = percent(r.cet) + ' a.a.'; }
      if (rowsEl) {
        var frag = document.createDocumentFragment();
        r.rows.forEach(function (row, idx) {
          var tr = document.createElement('tr');
          if (idx >= preview && !state.expanded) { tr.hidden = true; tr.className = 'is-extra'; }
          if (row.grace) { tr.setAttribute('data-grace', '1'); }
          var tdN = cell(labels.mes, String(row.n)); if (row.grace) { var em = document.createElement('em'); em.textContent = graceLabel; tdN.appendChild(document.createTextNode(' ')); tdN.appendChild(em); }
          tr.appendChild(tdN); tr.appendChild(cell(labels.parcela, money(row.payment))); tr.appendChild(cell(labels.juros, money(row.interest))); tr.appendChild(cell(labels.amort, money(row.amort))); tr.appendChild(cell(labels.saldo, money(row.balance)));
          frag.appendChild(tr);
        });
        rowsEl.innerHTML = ''; rowsEl.appendChild(frag);
      }
      if (countEl) { countEl.textContent = String(r.rows.length); }
      if (toggle) { toggle.hidden = r.rows.length <= preview; toggle.setAttribute('aria-expanded', state.expanded ? 'true' : 'false'); if (toggleLabel) { toggleLabel.textContent = state.expanded ? toggle.getAttribute('data-label-less') : toggle.getAttribute('data-label-more'); } }
      if (graceEl) { graceEl.max = String(Math.max(0, Math.min(60, state.months - 1))); }
    }
    function setAmount(v, fromRange) {
      state.amount = clamp(Math.round(v), min, max);
      if (rangeEl && !fromRange) { rangeEl.value = String(state.amount); }
      if (amountEl && fromRange) { amountEl.value = fmtInput(state.amount); }
      render();
    }
    if (amountEl) {
      amountEl.addEventListener('input', function () { var v = parseMoney(amountEl.value); if (v >= min && v <= max) { setAmount(v, false); } });
      amountEl.addEventListener('change', function () { var v = clamp(parseMoney(amountEl.value), min, max); amountEl.value = fmtInput(v); setAmount(v, false); });
      amountEl.addEventListener('focus', function () { try { amountEl.select(); } catch (e) {} });
    }
    if (rangeEl) {
      var span = max - min, step = Math.max(1, Math.pow(10, Math.floor(Math.log(Math.max(1, span / 300)) / Math.LN10)));
      rangeEl.step = String(step); rangeEl.min = String(min); rangeEl.max = String(max); rangeEl.value = String(state.amount);
      rangeEl.addEventListener('input', function () { setAmount(Number(rangeEl.value), true); });
    }
    if (monthsEl) {
      var onMonths = function () { var v = parseInt(monthsEl.value, 10); if (!v || v < 1) { return; } state.months = clamp(v, 1, maxTerm); if (state.grace > state.months - 1) { state.grace = Math.max(0, state.months - 1); if (graceEl) { graceEl.value = String(state.grace); } } render(); };
      monthsEl.addEventListener('input', onMonths);
      monthsEl.addEventListener('change', function () { state.months = clamp(parseInt(monthsEl.value, 10) || 1, 1, maxTerm); monthsEl.value = String(state.months); onMonths(); });
    }
    if (graceEl) {
      var onGrace = function () { var v = parseInt(graceEl.value, 10); if (isNaN(v) || v < 0) { v = 0; } state.grace = Math.min(60, Math.min(v, Math.max(0, state.months - 1))); render(); };
      graceEl.addEventListener('input', onGrace);
      graceEl.addEventListener('change', function () { onGrace(); graceEl.value = String(state.grace); });
    }
    root.querySelectorAll('[data-sim="system"]').forEach(function (radio) { radio.addEventListener('change', function () { if (radio.checked) { state.system = radio.value === 'sac' ? 'sac' : 'price'; render(); } }); });
    if (toggle) { toggle.addEventListener('click', function () { state.expanded = !state.expanded; render(); if (!state.expanded) { toggle.focus(); } }); }
    render();
  }

  function boot() { document.querySelectorAll('[data-ebcr-simulator]').forEach(init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
