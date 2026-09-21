/* ÉVELLYN BRANDÃO · interações do site (megamenu, acessibilidade, idiomas, cookies, canais, formulários) */
(function () {
  var d = document, w = window, C = w.EB_CONFIG || {};
  var reduce = w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches;
  d.documentElement.classList.add('eb-anim');

  /* SEO/ícones: move para o <head> as metatags, ícones e dados estruturados emitidos no corpo (tema em bloco não permite PHP no head) */
  try {
    var seen = {};
    Array.prototype.slice.call(d.body.querySelectorAll('meta[name], meta[property], link[rel~="icon"], link[rel="apple-touch-icon"], link[rel="shortcut icon"], script[type="application/ld+json"]')).forEach(function (el) {
      var key = el.tagName + ':' + (el.getAttribute('name') || el.getAttribute('property') || el.getAttribute('rel') || 'ld');
      if (el.tagName === 'META' && seen[key]) { el.parentNode.removeChild(el); return; }
      seen[key] = true; d.head.appendChild(el);
    });
    var t = d.querySelector('meta[property="og:title"]');
    if (t && t.content && d.title.indexOf(' – ') === -1) d.title = t.content;
  } catch (e) { }
  function $(s, r) { return (r || d).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); }
  function store(k, v) { try { if (v === undefined) { var x = localStorage.getItem(k); return x ? JSON.parse(x) : null; } localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }

  /* WhatsApp: links com data-eb-wa usam o número configurado */
  var waNum = String(C.whatsapp || '').replace(/\D/g, '');
  var waUrl = 'https://wa.me/' + waNum + '?text=' + encodeURIComponent(C.whatsappMsg || 'Olá!');
  $$('[data-eb-wa]').forEach(function (a) { a.href = waUrl; });

  /* Cabeçalho fixo compacto ao rolar */
  var header = $('#eb-header');
  function onScroll() { if (header) header.classList.toggle('is-scrolled', w.scrollY > 30); }
  onScroll(); w.addEventListener('scroll', onScroll, { passive: true });

  /* Menu mobile */
  var burger = $('#eb-burger');
  if (burger) burger.addEventListener('click', function () {
    var open = d.body.classList.toggle('eb-nav-open');
    burger.setAttribute('aria-expanded', open);
    burger.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    burger.innerHTML = '<svg><use href="#' + (open ? 'i-close' : 'i-menu') + '"/></svg>';
  });

  /* Megamenu (clique/teclado; hover é CSS no desktop) */
  var mmItems = $$('.eb-nav > li.eb-has-mm');
  function closeAll(except) {
    mmItems.forEach(function (li) {
      if (li !== except) { li.classList.remove('is-open'); var b = li.querySelector(':scope > button'); if (b) b.setAttribute('aria-expanded', 'false'); }
    });
  }
  mmItems.forEach(function (li) {
    var b = li.querySelector(':scope > button');
    if (!b) return;
    b.addEventListener('click', function (e) {
      e.preventDefault();
      var open = !li.classList.contains('is-open');
      closeAll(li); li.classList.toggle('is-open', open); b.setAttribute('aria-expanded', open);
    });
  });
  d.addEventListener('click', function (e) { if (!e.target.closest('.eb-nav')) closeAll(); });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(); d.body.classList.remove('eb-nav-open'); } });
  var path = location.pathname.replace(/\/+$/, '') || '/';
  $$('.eb-nav > li > a').forEach(function (a) { var p = (a.getAttribute('href') || '').replace(/\/+$/, '') || '/'; if (p === path) a.classList.add('is-active'); });

  /* Revelação ao rolar */
  var revs = $$('.eb-rev');
  if ('IntersectionObserver' in w && !reduce) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('eb-in'); io.unobserve(en.target); } }); }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    revs.forEach(function (el) { io.observe(el); });
  } else { revs.forEach(function (el) { el.classList.add('eb-in'); }); }
  /* Reforço: revela o que já está na tela ao rolar (garante exibição mesmo se o observer atrasar) */
  var revTick = false;
  function revealVisible() {
    revTick = false;
    revs.forEach(function (el) { if (!el.classList.contains('eb-in')) { var r = el.getBoundingClientRect(); if (w.innerHeight - 20 > r.top && r.bottom > 0) el.classList.add('eb-in'); } });
  }
  w.addEventListener('scroll', function () { if (!revTick) { revTick = true; (w.requestAnimationFrame || setTimeout)(revealVisible); } }, { passive: true });
  setTimeout(revealVisible, 900);

  /* Cards com inclinação 3D sutil (apenas com mouse) */
  if (!reduce && w.matchMedia && w.matchMedia('(hover:hover)').matches) {
    $$('.eb-card, .eb-stat, .eb-step').forEach(function (card) {
      card.addEventListener('mousemove', function (e) {
        var r = card.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
        card.style.transform = 'perspective(900px) rotateX(' + (-y * 6).toFixed(2) + 'deg) rotateY(' + (x * 8).toFixed(2) + 'deg) translateY(-4px)';
      });
      card.addEventListener('mouseleave', function () { card.style.transform = ''; });
    });
  }

  /* Acessibilidade: tamanho de fonte + alto contraste (persistidos) */
  var a11y = store('eb_a11y') || { fs: 1, contrast: false };
  function applyA11y() {
    d.documentElement.style.setProperty('--eb-fs', a11y.fs);
    d.documentElement.classList.toggle('eb-contrast', !!a11y.contrast);
    store('eb_a11y', a11y);
  }
  applyA11y();
  var a11yToggle = $('#eb-a11y-toggle'), a11yPanel = $('#eb-a11y-panel');
  if (a11yToggle && a11yPanel) {
    a11yToggle.addEventListener('click', function () { var open = a11yPanel.hasAttribute('hidden'); if (open) a11yPanel.removeAttribute('hidden'); else a11yPanel.setAttribute('hidden', ''); a11yToggle.setAttribute('aria-expanded', open); });
    $('#eb-a11y-plus').addEventListener('click', function () { a11y.fs = Math.min(1.5, +(a11y.fs + 0.1).toFixed(2)); applyA11y(); });
    $('#eb-a11y-minus').addEventListener('click', function () { a11y.fs = Math.max(0.8, +(a11y.fs - 0.1).toFixed(2)); applyA11y(); });
    $('#eb-a11y-contrast').addEventListener('click', function () { a11y.contrast = !a11y.contrast; applyA11y(); });
    $('#eb-a11y-reset').addEventListener('click', function () { a11y = { fs: 1, contrast: false }; applyA11y(); });
    d.addEventListener('click', function (e) { if (!e.target.closest('.eb-a11y')) { a11yPanel.setAttribute('hidden', ''); a11yToggle.setAttribute('aria-expanded', 'false'); } });
  }

  /* Idiomas (bandeiras PT/EN/ES) via Google Tradutor — cookie googtrans */
  function getCookie(n) { var m = d.cookie.match('(?:^|; )' + n + '=([^;]*)'); return m ? decodeURIComponent(m[1]) : ''; }
  function setLangCookie(l) {
    var v = l === 'pt' ? '' : '/pt/' + l, host = location.hostname.replace(/^www\./, '');
    var exp = l === 'pt' ? 'Thu, 01 Jan 1970 00:00:00 GMT' : new Date(Date.now() + 365 * 864e5).toUTCString();
    ['', 'domain=' + host + ';', 'domain=.' + host + ';'].forEach(function (dm) { d.cookie = 'googtrans=' + v + ';' + dm + 'path=/;expires=' + exp; });
  }
  var cur = (getCookie('googtrans').split('/')[2] || 'pt').slice(0, 2);
  if (!/^(pt|en|es)$/.test(cur)) cur = 'pt';
  $$('.eb-lang').forEach(function (b) { b.classList.toggle('is-on', b.dataset.lang === cur); });
  function loadGT() {
    if (w.__ebGT) return; w.__ebGT = true;
    w.ebGtInit = function () { try { new google.translate.TranslateElement({ pageLanguage: 'pt', includedLanguages: 'pt,en,es', autoDisplay: false }, 'eb-gt'); } catch (e) { } };
    var s = d.createElement('script'); s.src = 'https://translate.google.com/translate_a/element.js?cb=ebGtInit'; s.async = true; d.head.appendChild(s);
  }
  if (cur !== 'pt') loadGT();
  $$('.eb-lang').forEach(function (b) {
    b.addEventListener('click', function () {
      var l = b.dataset.lang; if (l === cur) return;
      setLangCookie(l); location.reload();
    });
  });

  /* Aviso de cookies */
  var ck = $('#eb-cookie'), consent = store('eb_cookie_consent');
  function showCookie(prefs) { if (!ck) return; ck.classList.add('is-on'); ck.classList.toggle('is-prefs', !!prefs); }
  function saveConsent(fn, an) { consent = { necessary: true, functional: !!fn, analytics: !!an, ts: Date.now() }; store('eb_cookie_consent', consent); if (ck) { ck.classList.remove('is-on', 'is-prefs'); } d.documentElement.classList.toggle('eb-no-func', !consent.functional); }
  if (!consent) setTimeout(function () { showCookie(false); }, 900); else d.documentElement.classList.toggle('eb-no-func', !consent.functional);
  $$('[data-eb-cookie]').forEach(function (b) {
    b.addEventListener('click', function () {
      var a = b.dataset.ebCookie;
      if (a === 'accept') saveConsent(true, true);
      else if (a === 'reject') saveConsent(false, false);
      else if (a === 'prefs') ck.classList.add('is-prefs');
      else if (a === 'save') saveConsent($('#eb-ck-func').checked, $('#eb-ck-ana').checked);
    });
  });
  $$('[data-eb-cookie-open]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); if (consent) { $('#eb-ck-func').checked = !!consent.functional; $('#eb-ck-ana').checked = !!consent.analytics; } showCookie(true); }); });

  /* Canais flutuantes */
  var fab = $('#eb-fabm');
  if (fab) { var ft = $('.eb-fabm-toggle', fab); ft.addEventListener('click', function () { var o = fab.classList.toggle('is-open'); ft.setAttribute('aria-expanded', o); }); d.addEventListener('click', function (e) { if (!e.target.closest('#eb-fabm')) fab.classList.remove('is-open'); }); }

  /* VLibras (widget oficial do Governo Federal) */
  function loadVLibras() {
    if (w.__ebVL) return; w.__ebVL = true;
    var s = d.createElement('script'); s.src = 'https://vlibras.gov.br/app/vlibras-plugin.js'; s.async = true;
    s.onload = function () { try { new w.VLibras.Widget('https://vlibras.gov.br/app'); } catch (e) { } };
    d.head.appendChild(s);
  }
  if (w.requestIdleCallback) requestIdleCallback(loadVLibras, { timeout: 4000 }); else setTimeout(loadVLibras, 2500);

  /* Formulários (envio por e-mail via FormSubmit, sem plugin) */
  $$('form.eb-form[data-endpoint]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = $('.eb-form-msg', f), btn = $('[type=submit]', f), hp = $('input[name=_honey]', f);
      if (hp && hp.value) return;
      var missing = $$('[required]', f).filter(function (i) { return i.type === 'checkbox' ? !i.checked : !String(i.value).trim(); });
      if (missing.length) { msg.className = 'eb-form-msg is-err'; msg.textContent = 'Preencha os campos obrigatórios para enviar.'; missing[0].focus(); return; }
      var data = {}; new FormData(f).forEach(function (v, k) { if (k !== '_honey') data[k] = v; });
      data._subject = f.dataset.subject || 'Mensagem pelo site evellynbrandao.com.br';
      data._template = 'table'; data._captcha = 'false';
      data._origem = location.href;
      if (btn) btn.disabled = true;
      msg.className = 'eb-form-msg is-ok'; msg.textContent = 'Enviando…';
      fetch('https://formsubmit.co/ajax/' + f.dataset.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data) })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j && (j.success === true || j.success === 'true')) { msg.className = 'eb-form-msg is-ok'; msg.textContent = f.dataset.ok || 'Mensagem enviada com sucesso! Retornaremos em breve.'; f.reset(); }
          else throw new Error((j && j.message) || 'erro');
        })
        .catch(function () {
          msg.className = 'eb-form-msg is-err';
          msg.innerHTML = (f.dataset.err || 'Não foi possível enviar agora.') + ' Você pode escrever para <a href="mailto:' + f.dataset.endpoint + '">' + f.dataset.endpoint + '</a> ou falar pelo <a href="' + waUrl + '" target="_blank" rel="noopener">WhatsApp</a>.';
        })
        .finally(function () { if (btn) btn.disabled = false; });
    });
  });

  /* Sumário automático (páginas longas) a partir dos H2 do conteúdo */
  var toc = $('.eb-toc[data-eb-toc]');
  if (toc) {
    var hs = $$('.eb-body h2');
    if (2 > hs.length) { var box = toc.closest('.eb-card'); if (box) box.style.display = 'none'; }
    hs.forEach(function (h, i) {
      if (!h.id) h.id = 'sec-' + (i + 1) + '-' + h.textContent.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 50);
      var li = d.createElement('li'), a = d.createElement('a'); a.href = '#' + h.id; a.textContent = h.textContent; li.appendChild(a); toc.appendChild(li);
    });
    if ('IntersectionObserver' in w && hs.length) {
      var tio = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { $$('a', toc).forEach(function (a) { a.classList.toggle('is-on', a.getAttribute('href') === '#' + en.target.id); }); } }); }, { rootMargin: '-20% 0px -70% 0px' });
      hs.forEach(function (h) { tio.observe(h); });
    }
  }

  /* Breadcrumb com o título da página e tempo de leitura estimado */
  var crumb = $('[data-eb-crumb]'), h1 = $('.eb-banner .wp-block-post-title, .eb-banner h1');
  if (crumb && h1) crumb.textContent = h1.textContent.trim();
  var rt = $('[data-eb-readtime]'), bodyEl = $('.eb-body');
  if (rt && bodyEl) { var words = (bodyEl.textContent || '').trim().split(/\s+/).length; rt.textContent = 'Leitura de ~' + Math.max(1, Math.round(words / 200)) + ' min'; }

  /* Ano no rodapé */
  $$('[data-eb-year]').forEach(function (el) { el.textContent = new Date().getFullYear(); });
})();
