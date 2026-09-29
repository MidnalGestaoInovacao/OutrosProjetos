/* EB Crédito Rural — painel: gráficos com Chart.js (empacotado em assets/vendor/chartjs). Dados em window.ebcrDashboardData. */
(function () {
  'use strict';
  var data = window.ebcrDashboardData;
  var Chart = window.Chart;
  if (!data || !Chart) { return; }

  // Uma única cor de série (identidade vem do rótulo); grade e eixos recessivos.
  var C = { series: '#2a78d6', wash: 'rgba(42,120,214,0.12)', grid: '#e1e0d9', axis: '#c3c2b7', muted: '#898781', ink: '#1d2327', surface: '#ffffff' };
  var locale = data.locale || 'pt-BR';
  var fmtInt = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 });
  var fmtBRL = new Intl.NumberFormat(locale, { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
  function compact(v) {
    var a = Math.abs(v);
    if (a >= 1e9) { return (v / 1e9).toLocaleString(locale, { maximumFractionDigits: 1 }) + ' bi'; }
    if (a >= 1e6) { return (v / 1e6).toLocaleString(locale, { maximumFractionDigits: 1 }) + ' mi'; }
    if (a >= 1e3) { return (v / 1e3).toLocaleString(locale, { maximumFractionDigits: 0 }) + ' mil'; }
    return fmtInt.format(v);
  }
  var font = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
  Chart.defaults.font.family = font;
  Chart.defaults.font.size = 12;
  Chart.defaults.color = C.muted;
  Chart.defaults.plugins.tooltip.backgroundColor = '#1d2327';
  Chart.defaults.plugins.tooltip.padding = 8;
  Chart.defaults.plugins.tooltip.displayColors = false;

  function sum(arr) { return (arr || []).reduce(function (s, v) { return s + (Number(v) || 0); }, 0); }
  function empty(canvas) {
    var p = document.createElement('p');
    p.className = 'ebcr-chart-empty';
    p.textContent = data.i18n.empty;
    canvas.parentNode.replaceWith(p);
  }

  // Rótulo direto na ponta de cada barra (barras horizontais) ou no fim da linha.
  function endLabels(fmt, mode) {
    return {
      id: 'ebcrEndLabels',
      afterDatasetsDraw: function (chart) {
        var meta = chart.getDatasetMeta(0);
        if (!meta || meta.hidden || !meta.data.length) { return; }
        var ctx = chart.ctx;
        var values = chart.data.datasets[0].data;
        ctx.save();
        ctx.fillStyle = C.ink;
        ctx.font = '600 11px ' + font;
        if (mode === 'bars') {
          ctx.textAlign = 'left';
          ctx.textBaseline = 'middle';
          meta.data.forEach(function (el, i) {
            if (values[i] === null || values[i] === undefined) { return; }
            ctx.fillText(fmt(values[i]), el.x + 6, el.y);
          });
        } else {
          var last = meta.data.length - 1;
          for (; last >= 0 && (values[last] === null || values[last] === undefined); last--) { /* último ponto com valor */ }
          if (last >= 0) {
            var el = meta.data[last];
            ctx.textAlign = 'right';
            ctx.textBaseline = 'bottom';
            ctx.fillText(fmt(values[last]), el.x, el.y - 8);
          }
        }
        ctx.restore();
      }
    };
  }

  function hbar(id, serie, seriesLabel) {
    var canvas = document.getElementById(id);
    if (!canvas) { return; }
    if (!serie || !serie.labels.length || sum(serie.values) === 0) { empty(canvas); return; }
    new Chart(canvas, {
      type: 'bar',
      data: { labels: serie.labels, datasets: [{ label: seriesLabel, data: serie.values, backgroundColor: C.series, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22, categoryPercentage: 0.72, barPercentage: 1 }] },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { right: 36 } },
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return seriesLabel + ': ' + fmtInt.format(ctx.parsed.x); } } } },
        scales: {
          x: { beginAtZero: true, grid: { color: C.grid, drawTicks: false }, border: { display: false }, ticks: { precision: 0, color: C.muted } },
          y: { grid: { display: false }, border: { color: C.axis }, ticks: { color: C.ink, autoSkip: false } }
        }
      },
      plugins: [endLabels(function (v) { return fmtInt.format(v); }, 'bars')]
    });
  }

  function monthsCount() {
    var canvas = document.getElementById('ebcr-chart-months-count');
    if (!canvas) { return; }
    var m = data.months;
    if (!m || !m.labels.length || sum(m.counts) === 0) { empty(canvas); return; }
    new Chart(canvas, {
      type: 'bar',
      data: { labels: m.labels, datasets: [{ label: data.i18n.submissions, data: m.counts, backgroundColor: C.series, borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 22, categoryPercentage: 0.7 }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return data.i18n.submissions + ': ' + fmtInt.format(ctx.parsed.y); } } } },
        scales: {
          x: { grid: { display: false }, border: { color: C.axis }, ticks: { color: C.muted, maxRotation: 0, autoSkipPadding: 8 } },
          y: { beginAtZero: true, grid: { color: C.grid, drawTicks: false }, border: { display: false }, ticks: { precision: 0, color: C.muted } }
        }
      }
    });
  }

  function monthsVolume() {
    var canvas = document.getElementById('ebcr-chart-months-volume');
    if (!canvas) { return; }
    var m = data.months;
    if (!m || !m.labels.length || sum(m.volumes) === 0) { empty(canvas); return; }
    new Chart(canvas, {
      type: 'line',
      data: { labels: m.labels, datasets: [{ label: data.i18n.volume, data: m.volumes, borderColor: C.series, borderWidth: 2, borderJoinStyle: 'round', borderCapStyle: 'round', backgroundColor: C.wash, fill: true, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: C.series, pointBorderColor: C.surface, pointBorderWidth: 2, tension: 0 }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 16 } },
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (ctx) { return data.i18n.volume + ': ' + fmtBRL.format(ctx.parsed.y); } } } },
        scales: {
          x: { grid: { display: false }, border: { color: C.axis }, ticks: { color: C.muted, maxRotation: 0, autoSkipPadding: 8 } },
          y: { beginAtZero: true, grid: { color: C.grid, drawTicks: false }, border: { display: false }, ticks: { color: C.muted, maxTicksLimit: 5, callback: function (v) { return compact(v); } } }
        }
      },
      plugins: [endLabels(function (v) { return fmtBRL.format(v); }, 'line')]
    });
  }

  hbar('ebcr-chart-funnel', data.funnel, data.i18n.submissions);
  hbar('ebcr-chart-uf', data.uf, data.i18n.submissions);
  hbar('ebcr-chart-activity', data.activity, data.i18n.submissions);
  hbar('ebcr-chart-guarantee', data.guarantee, data.i18n.guarantees);
  monthsCount();
  monthsVolume();
})();
