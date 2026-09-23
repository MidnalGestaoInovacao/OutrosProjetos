/* EB Crédito Rural — painel da equipe no portal: abas, confirmações, status com parecer obrigatório,
 * quadro Kanban (arrastar e soltar via REST, com fallback por seleção) e gráficos (Chart.js empacotado).
 * Sem dependências além de portal.js (máscaras) e, nas telas com gráficos, chart.umd.js. */
(function () {
  'use strict';
  var cfg = window.ebcrTeam || { rest: '', nonce: '', i18n: {}, locale: 'pt-BR' };
  var i18n = cfg.i18n || {};
  var root = document.querySelector('.ebcr-team');
  if (!root) { return; }

  /* ------------------------------------------------------------ abas (com ?tab= na URL) */
  root.querySelectorAll('.ebcr-ttabs').forEach(function (nav) {
    var links = nav.querySelectorAll('[data-tab]');
    var panels = root.querySelectorAll('.ebcr-tpanel[data-panel]');
    function show(id, push) {
      links.forEach(function (l) {
        var on = l.getAttribute('data-tab') === id;
        l.classList.toggle('is-active', on);
        l.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== id; });
      if (push && window.history.replaceState) {
        try { var u = new URL(window.location.href); u.searchParams.set('tab', id); window.history.replaceState(null, '', u.toString()); } catch (e) { /* URL inválida */ }
      }
    }
    links.forEach(function (l) {
      l.addEventListener('click', function (e) { e.preventDefault(); show(l.getAttribute('data-tab'), true); l.focus(); });
      l.addEventListener('keydown', function (e) {
        var list = Array.prototype.slice.call(links); var i = list.indexOf(l);
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
          e.preventDefault(); var n = list[(i + (e.key === 'ArrowRight' ? 1 : list.length - 1)) % list.length]; show(n.getAttribute('data-tab'), true); n.focus();
        }
      });
    });
  });

  /* ------------------------------------------------------------ confirmação e parecer obrigatório */
  root.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) { if (!window.confirm(el.getAttribute('data-confirm') || i18n.confirm || 'Confirmar?')) { e.preventDefault(); } });
  });
  root.querySelectorAll('[data-status-form]').forEach(function (form) {
    var sel = form.querySelector('[data-status-select]'); var internal = form.querySelector('[data-comment-internal]'); var note = form.querySelector('[data-requires-comment]');
    if (!sel) { return; }
    function upd() { var opt = sel.options[sel.selectedIndex]; var req = opt && opt.getAttribute('data-requires-comment') === '1'; if (internal) { internal.required = req; } if (note) { note.hidden = !req; } }
    sel.addEventListener('change', upd); upd();
  });

  /* ------------------------------------------------------------ Kanban */
  var board = root.querySelector('[data-kanban]');
  if (board && cfg.rest && window.fetch) {
    var toast = root.querySelector('[data-toast]'); var toastTimer = null;
    function notify(msg, isError) {
      if (!toast) { return; }
      toast.textContent = msg; toast.classList.toggle('is-error', !!isError); toast.hidden = false;
      window.clearTimeout(toastTimer); toastTimer = window.setTimeout(function () { toast.hidden = true; }, isError ? 5000 : 2500);
    }
    function updateCounts() {
      board.querySelectorAll('.ebcr-kcol').forEach(function (col) { var n = col.querySelectorAll('.ebcr-kcard').length; var b = col.querySelector('[data-count]'); if (b) { b.textContent = String(n); } });
    }
    function saveStage(card, stage) {
      card.classList.add('is-saving');
      return window.fetch(cfg.rest + 'crm/contacts/' + encodeURIComponent(card.getAttribute('data-contact')) + '/stage', {
        method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce }, body: JSON.stringify({ stage: stage })
      }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (json) { if (!res.ok || !json || !json.ok) { throw new Error(json && json.message ? json.message : (i18n.error || 'Erro')); } return json; });
      }).then(function (json) {
        card.classList.remove('is-saving'); var sel = card.querySelector('[data-move]'); if (sel) { sel.value = json.stage; }
        notify(i18n.moved || 'OK', false); return json;
      }, function (err) { card.classList.remove('is-saving'); notify(err && err.message ? err.message : (i18n.error || 'Erro'), true); throw err; });
    }
    function moveCard(card, zone) {
      var from = card.parentNode; var col = zone.closest('[data-stage]'); var stage = col ? col.getAttribute('data-stage') : '';
      if (!stage || from === zone) { return; }
      zone.insertBefore(card, zone.querySelector('.ebcr-kcard')); updateCounts();
      saveStage(card, stage).catch(function () { from.appendChild(card); updateCounts(); });
    }
    var dragged = null;
    board.querySelectorAll('.ebcr-kcard[draggable]').forEach(function (card) {
      card.addEventListener('dragstart', function (e) { dragged = card; card.classList.add('is-dragging'); if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', card.getAttribute('data-contact')); } catch (_) { /* IE */ } } });
      card.addEventListener('dragend', function () { card.classList.remove('is-dragging'); board.querySelectorAll('.is-over').forEach(function (z) { z.classList.remove('is-over'); }); dragged = null; });
      // Com JS o botão "Mover" some e a seleção aplica na hora (o formulário continua funcionando sem JS).
      var form = card.querySelector('[data-move-form]'); var sel = card.querySelector('[data-move]'); var btn = card.querySelector('[data-move-btn]');
      if (form && sel) {
        if (btn) { btn.hidden = true; }
        form.addEventListener('submit', function (e) { e.preventDefault(); });
        sel.addEventListener('change', function () { var target = board.querySelector('[data-stage="' + sel.value.replace(/"/g, '') + '"] [data-dropzone]'); if (target) { moveCard(card, target); } });
        sel.addEventListener('click', function (e) { e.stopPropagation(); });
        sel.addEventListener('mousedown', function () { card.setAttribute('draggable', 'false'); });
        sel.addEventListener('blur', function () { card.setAttribute('draggable', 'true'); });
      }
    });
    board.querySelectorAll('[data-dropzone]').forEach(function (zone) {
      zone.addEventListener('dragover', function (e) { if (!dragged) { return; } e.preventDefault(); if (e.dataTransfer) { e.dataTransfer.dropEffect = 'move'; } zone.classList.add('is-over'); });
      zone.addEventListener('dragleave', function (e) { if (!zone.contains(e.relatedTarget)) { zone.classList.remove('is-over'); } });
      zone.addEventListener('drop', function (e) { e.preventDefault(); zone.classList.remove('is-over'); if (!dragged) { return; } moveCard(dragged, zone); dragged.classList.remove('is-dragging'); dragged = null; });
    });
    updateCounts();
  }

  /* ------------------------------------------------------------ gráficos (Chart.js) */
  var data = window.ebcrTeamCharts; var Chart = window.Chart;
  if (!data || !Chart) { return; }
  var C = { series: '#8a6a1c', wash: 'rgba(212,175,55,0.18)', grid: '#e5e7eb', axis: '#c9c7bd', muted: '#6b7280', ink: '#111827', surface: '#ffffff' };
  var locale = cfg.locale || 'pt-BR';
  var fmtInt = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 });
  var fmtBRL = new Intl.NumberFormat(locale, { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
  function compact(v) { var a = Math.abs(v); if (a >= 1e9) { return (v / 1e9).toLocaleString(locale, { maximumFractionDigits: 1 }) + ' bi'; } if (a >= 1e6) { return (v / 1e6).toLocaleString(locale, { maximumFractionDigits: 1 }) + ' mi'; } if (a >= 1e3) { return (v / 1e3).toLocaleString(locale, { maximumFractionDigits: 0 }) + ' mil'; } return fmtInt.format(v); }
  var font = 'inherit';
  Chart.defaults.font.family = font; Chart.defaults.font.size = 12; Chart.defaults.color = C.muted;
  Chart.defaults.plugins.tooltip.backgroundColor = '#111827'; Chart.defaults.plugins.tooltip.padding = 8; Chart.defaults.plugins.tooltip.displayColors = false;
  function sum(arr) { return (arr || []).reduce(function (s, v) { return s + (Number(v) || 0); }, 0); }
  function empty(canvas) { var p = document.createElement('p'); p.className = 'ebcr-tchart-empty'; p.textContent = i18n.empty || ''; canvas.parentNode.replaceWith(p); }
  function endLabels(fmt, mode) {
    return { id: 'ebcrEndLabels', afterDatasetsDraw: function (chart) {
      var meta = chart.getDatasetMeta(0); if (!meta || meta.hidden || !meta.data.length) { return; }
      var ctx = chart.ctx; var values = chart.data.datasets[0].data; ctx.save(); ctx.fillStyle = C.ink; ctx.font = '600 11px ' + font;
      if (mode === 'bars') { ctx.textAlign = 'left'; ctx.textBaseline = 'middle'; meta.data.forEach(function (el, i) { if (values[i] === null || values[i] === undefined) { return; } ctx.fillText(fmt(values[i]), el.x + 6, el.y); }); }
      else { var last = meta.data.length - 1; for (; last >= 0 && (values[last] === null || values[last] === undefined); last--) { /* último com valor */ } if (last >= 0) { var el = meta.data[last]; ctx.textAlign = 'right'; ctx.textBaseline = 'bottom'; ctx.fillText(fmt(values[last]), el.x, el.y - 8); } }
      ctx.restore();
    } };
  }
  function hbar(id, serie, seriesLabel) {
    var canvas = document.getElementById(id); if (!canvas) { return; }
    if (!serie || !serie.labels || !serie.labels.length || sum(serie.values) === 0) { empty(canvas); return; }
    new Chart(canvas, { type: 'bar', data: { labels: serie.labels, datasets: [{ label: seriesLabel, data: serie.values, backgroundColor: C.series, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22, categoryPercentage: 0.72, barPercentage: 1 }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, layout: { padding: { right: 36 } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return seriesLabel + ': ' + fmtInt.format(ctx.parsed.x); } } } },
        scales: { x: { beginAtZero: true, grid: { color: C.grid, drawTicks: false }, border: { display: false }, ticks: { precision: 0, color: C.muted } }, y: { grid: { display: false }, border: { color: C.axis }, ticks: { color: C.ink, autoSkip: false } } } },
      plugins: [endLabels(function (v) { return fmtInt.format(v); }, 'bars')] });
  }
  function monthsCount() {
    var canvas = document.getElementById('ebcr-chart-months-count'); if (!canvas) { return; }
    var m = data.months; if (!m || !m.labels.length || sum(m.counts) === 0) { empty(canvas); return; }
    new Chart(canvas, { type: 'bar', data: { labels: m.labels, datasets: [{ label: i18n.submissions, data: m.counts, backgroundColor: C.series, borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 22, categoryPercentage: 0.7 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return i18n.submissions + ': ' + fmtInt.format(ctx.parsed.y); } } } },
        scales: { x: { grid: { display: false }, border: { color: C.axis }, ticks: { color: C.muted, maxRotation: 0, autoSkipPadding: 8 } }, y: { beginAtZero: true, grid: { color: C.grid, drawTicks: false }, border: { display: false }, ticks: { precision: 0, color: C.muted } } } } });
  }
  function monthsVolume() {
    var canvas = document.getElementById('ebcr-chart-months-volume'); if (!canvas) { return; }
    var m = data.months; if (!m || !m.labels.length || sum(m.volumes) === 0) { empty(canvas); return; }
    new Chart(canvas, { type: 'line', data: { labels: m.labels, datasets: [{ label: i18n.volume, data: m.volumes, borderColor: C.series, borderWidth: 2, borderJoinStyle: 'round', borderCapStyle: 'round', backgroundColor: C.wash, fill: true, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: C.series, pointBorderColor: C.surface, pointBorderWidth: 2, tension: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, layout: { padding: { top: 16 } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return i18n.volume + ': ' + fmtBRL.format(ctx.parsed.y); } } } },
        scales: { x: { grid: { display: false }, border: { color: C.axis }, ticks: { color: C.muted, maxRotation: 0, autoSkipPadding: 8 } }, y: { beginAtZero: true, grid: { color: C.grid, drawTicks: false }, border: { display: false }, ticks: { color: C.muted, maxTicksLimit: 5, callback: function (v) { return compact(v); } } } } },
      plugins: [endLabels(function (v) { return fmtBRL.format(v); }, 'line')] });
  }
  hbar('ebcr-chart-funnel', data.funnel, i18n.submissions);
  hbar('ebcr-chart-uf', data.uf, i18n.submissions);
  hbar('ebcr-chart-activity', data.activity, i18n.submissions);
  hbar('ebcr-chart-guarantee', data.guarantee, i18n.guarantees);
  monthsCount();
  monthsVolume();
})();
