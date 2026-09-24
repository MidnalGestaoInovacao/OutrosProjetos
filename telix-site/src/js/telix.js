/*! TÉLIX site runtime — megamenu, cookies, acessibilidade, VLibras, formulários, SEO, 3D. */
(function () {
  'use strict';
  var d = document, html = d.documentElement;
  var CFG = window.TX_CONFIG || {};
  html.classList.add('tx-js');
  var mqReduce = window.matchMedia ? matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  function reduced() { return mqReduce.matches || html.classList.contains('tx-a11y-noanim'); }
  function $(s, c) { return (c || d).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); }
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) { return null; } }
  function onReady(fn) { if (d.readyState !== 'loading') fn(); else d.addEventListener('DOMContentLoaded', fn); }
  function rand(n) { var a = new Uint32Array(1); (window.crypto || window.msCrypto).getRandomValues(a); return a[0] % n; }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* Base do site (funciona após migração de domínio): deriva do link da REST API emitido pelo WP */
  function siteHome() {
    var l = $('link[rel="https://api.w.org/"]');
    if (l && l.href) return l.href.replace(/\/wp-json\/?$/, '').replace(/\/?\?rest_route=.*$/, '').replace(/\/$/, '');
    return location.origin;
  }
  var HOME = siteHome();
  function abs(u) { if (!u) return u; if (/^https?:\/\//i.test(u)) return u; return HOME + (u.charAt(0) === '/' ? '' : '/') + u; }

  /* ------------------------------------------------------------------
     HEADER / MEGAMENU
  ------------------------------------------------------------------ */
  function initHeader() {
    var header = $('.tx-header');
    if (!header) return;
    var wrap = header.closest('.wp-block-template-part') || header;
    wrap.classList.add('tx-header-wrap');
    var onScroll = function () {
      header.classList.toggle('is-stuck', window.scrollY > 40);
      var h = d.documentElement.scrollHeight - innerHeight;
      var p = $('.tx-progress'); if (p) p.style.setProperty('--p', h > 0 ? Math.min(1, scrollY / h) : 0);
      var t = $('.tx-totop'); if (t) t.classList.toggle('is-on', scrollY > 700);
    };
    addEventListener('scroll', onScroll, { passive: true }); onScroll();

    var items = $$('.tx-nav__item.has-mega', header);
    var timer;
    function close(item, focusBtn) {
      item.classList.remove('is-open');
      var b = $('.tx-nav__link', item); b.setAttribute('aria-expanded', 'false');
      if (focusBtn) b.focus();
    }
    function closeAll(except) { items.forEach(function (i) { if (i !== except) close(i); }); }
    function open(item) { closeAll(item); item.classList.add('is-open'); $('.tx-nav__link', item).setAttribute('aria-expanded', 'true'); }
    var fine = window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches;
    items.forEach(function (item) {
      var btn = $('.tx-nav__link', item);
      btn.addEventListener('click', function (e) { e.preventDefault(); item.classList.contains('is-open') ? close(item) : open(item); });
      if (fine) {
        item.addEventListener('mouseenter', function () { clearTimeout(timer); timer = setTimeout(function () { open(item); }, 90); });
        item.addEventListener('mouseleave', function () { clearTimeout(timer); timer = setTimeout(function () { close(item); }, 200); });
      }
      item.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { close(item, true); }
        if (e.key === 'ArrowDown' && e.target === btn) { e.preventDefault(); open(item); var f = $('.tx-mega a', item); if (f) f.focus(); }
      });
      item.addEventListener('focusout', function (e) { if (!item.contains(e.relatedTarget)) close(item); });
    });
    d.addEventListener('click', function (e) { if (!e.target.closest('.tx-nav__item')) closeAll(); });

    // Página atual
    var path = location.pathname.replace(/\/+$/, '/') || '/';
    $$('.tx-nav a, .tx-drawer a').forEach(function (a) {
      try {
        var u = new URL(a.getAttribute('href'), location.href);
        if (u.hash) return;
        var p = u.pathname.replace(/\/+$/, '/') || '/';
        if (p === path) {
          a.setAttribute('aria-current', 'page');
          var item = a.closest('.tx-nav__item'); if (item) { var l = $('.tx-nav__link', item); if (l && l !== a) l.classList.add('is-current'); }
        }
      } catch (e) {}
    });

    // Drawer mobile
    var drawer = $('.tx-drawer'), burger = $('.tx-burger');
    if (drawer && burger) {
      var lastFocus;
      var openD = function () { lastFocus = d.activeElement; drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false'); burger.setAttribute('aria-expanded', 'true'); d.body.classList.add('tx-lock'); setTimeout(function () { var c = $('.tx-drawer__close', drawer); if (c) c.focus(); }, 60); };
      var closeD = function () { drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); burger.setAttribute('aria-expanded', 'false'); d.body.classList.remove('tx-lock'); if (lastFocus) lastFocus.focus(); };
      burger.addEventListener('click', openD);
      $$('[data-tx-drawer-close]', drawer).forEach(function (b) { b.addEventListener('click', closeD); });
      drawer.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeD();
        if (e.key === 'Tab') trapTab(e, $('.tx-drawer__panel', drawer));
      });
      $$('a', drawer).forEach(function (a) { a.addEventListener('click', function () { if ((a.getAttribute('href') || '').indexOf('#') > -1) closeD(); }); });
    }
  }
  function trapTab(e, box) {
    var f = $$('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"]),summary', box).filter(function (el) { return el.offsetParent !== null; });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  /* ------------------------------------------------------------------
     Interações de conteúdo: reveal, contadores, tilt, flip, anel 3D, tabs, TOC
  ------------------------------------------------------------------ */
  function initReveal() {
    var els = $$('[data-reveal]');
    if (!('IntersectionObserver' in window) || reduced()) { els.forEach(function (e) { e.classList.add('is-in'); }); return; }
    var io = new IntersectionObserver(function (en) {
      en.forEach(function (x) { if (x.isIntersecting) { var el = x.target; var dl = parseInt(el.getAttribute('data-delay') || '0', 10); setTimeout(function () { el.classList.add('is-in'); }, dl); io.unobserve(el); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    els.forEach(function (e) { io.observe(e); });
  }
  function initCounters() {
    var els = $$('[data-count]');
    if (!els.length) return;
    var run = function (el) {
      var end = parseFloat(el.getAttribute('data-count')), dec = (el.getAttribute('data-count').split('.')[1] || '').length;
      var pre = el.getAttribute('data-prefix') || '', suf = el.getAttribute('data-suffix') || '';
      if (reduced()) { el.textContent = pre + end.toLocaleString('pt-BR', { minimumFractionDigits: dec }) + suf; return; }
      var t0 = performance.now(), dur = 1600;
      (function step(t) { var k = Math.min(1, (t - t0) / dur); k = 1 - Math.pow(1 - k, 3); el.textContent = pre + (end * k).toLocaleString('pt-BR', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suf; if (k < 1) requestAnimationFrame(step); })(t0);
    };
    if (!('IntersectionObserver' in window)) { els.forEach(run); return; }
    var io = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) { run(x.target); io.unobserve(x.target); } }); }, { threshold: 0.4 });
    els.forEach(function (e) { io.observe(e); });
  }
  function initTilt() {
    if (!(window.matchMedia && matchMedia('(hover: hover) and (pointer: fine)').matches)) return;
    $$('.tx-tilt').forEach(function (el) {
      var max = parseFloat(el.getAttribute('data-tilt') || '8');
      el.addEventListener('pointermove', function (e) {
        if (reduced()) return;
        var r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
        el.style.setProperty('--ry', ((x - .5) * max * 2).toFixed(2) + 'deg');
        el.style.setProperty('--rx', ((.5 - y) * max * 2).toFixed(2) + 'deg');
        el.style.setProperty('--gx', (x * 100).toFixed(1) + '%'); el.style.setProperty('--gy', (y * 100).toFixed(1) + '%');
      });
      el.addEventListener('pointerleave', function () { el.style.setProperty('--rx', '0deg'); el.style.setProperty('--ry', '0deg'); });
    });
  }
  function initFlip() {
    $$('.tx-flip').forEach(function (f) {
      var b = $('.tx-flip__toggle', f);
      if (b) b.addEventListener('click', function () { var on = f.classList.toggle('is-flipped'); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    });
  }
  function initRing() {
    $$('.tx-ring').forEach(function (ring) {
      var stage = $('.tx-ring__stage', ring), n = $$('.tx-ring__item', ring).length || 8, ang = 0, step = 360 / n, auto;
      ring.style.setProperty('--n', n);
      var set = function () { stage.style.setProperty('--ang', ang + 'deg'); };
      var go = function (dir) { ang -= dir * step; set(); };
      var ctrl = ring.nextElementSibling && ring.nextElementSibling.classList.contains('tx-ring__ctrl') ? ring.nextElementSibling : null;
      if (ctrl) { $$('button', ctrl).forEach(function (b) { b.addEventListener('click', function () { stop(); go(parseInt(b.getAttribute('data-dir'), 10)); }); }); }
      var sx = null;
      ring.addEventListener('pointerdown', function (e) { sx = e.clientX; stop(); });
      addEventListener('pointerup', function (e) { if (sx === null) return; var dx = e.clientX - sx; if (Math.abs(dx) > 30) go(dx < 0 ? 1 : -1); sx = null; });
      ring.setAttribute('tabindex', '0');
      ring.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') { stop(); go(1); } if (e.key === 'ArrowLeft') { stop(); go(-1); } });
      function start() { if (reduced()) return; stop(); auto = setInterval(function () { go(1); }, 3200); }
      function stop() { clearInterval(auto); }
      ring.addEventListener('mouseenter', stop); ring.addEventListener('mouseleave', start);
      start();
    });
  }
  function initTabs() {
    $$('.tx-tabs').forEach(function (w) {
      var tabs = $$('[role=tab]', w);
      tabs.forEach(function (t, i) {
        t.addEventListener('click', function () { sel(i); });
        t.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') { sel((i + 1) % tabs.length); tabs[(i + 1) % tabs.length].focus(); } if (e.key === 'ArrowLeft') { var j = (i - 1 + tabs.length) % tabs.length; sel(j); tabs[j].focus(); } });
      });
      function sel(i) { tabs.forEach(function (t, j) { t.setAttribute('aria-selected', i === j ? 'true' : 'false'); t.tabIndex = i === j ? 0 : -1; var p = d.getElementById(t.getAttribute('aria-controls')); if (p) p.hidden = i !== j; }); }
    });
  }
  function slug(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60); }
  function initToc() {
    $$('.tx-toc[data-auto]').forEach(function (toc) {
      var prose = $(toc.getAttribute('data-auto')) || $('.tx-prose');
      if (!prose) return;
      var hs = $$('h2', prose); if (!hs.length) return;
      var ol = d.createElement('ol');
      hs.forEach(function (h) { if (!h.id) h.id = slug(h.textContent); var li = d.createElement('li'); li.innerHTML = '<a href="#' + h.id + '">' + esc(h.textContent) + '</a>'; ol.appendChild(li); });
      toc.appendChild(ol);
      if ('IntersectionObserver' in window) {
        var links = $$('a', ol);
        var io = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) { links.forEach(function (l) { l.classList.toggle('is-active', l.getAttribute('href') === '#' + x.target.id); }); } }); }, { rootMargin: '-20% 0px -70% 0px' });
        hs.forEach(function (h) { io.observe(h); });
      }
    });
  }

  /* ------------------------------------------------------------------
     CONTATO FLUTUANTE + TOPO
  ------------------------------------------------------------------ */
  function initFab() {
    var fab = $('.tx-fab'); if (!fab) return;
    var btn = $('.tx-fab__toggle', fab);
    var set = function (on) { fab.classList.toggle('is-open', on); html.classList.toggle('tx-fab-open', on); btn.setAttribute('aria-expanded', on ? 'true' : 'false'); $$('.tx-fab__list a', fab).forEach(function (a) { a.tabIndex = on ? 0 : -1; }); };
    set(false);
    btn.addEventListener('click', function () { set(!fab.classList.contains('is-open')); });
    fab.addEventListener('keydown', function (e) { if (e.key === 'Escape') { set(false); btn.focus(); } });
    d.addEventListener('click', function (e) { if (!fab.contains(e.target)) set(false); });
    var top = $('.tx-totop');
    if (top) top.addEventListener('click', function () { scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' }); var s = $('#conteudo') || d.body; });
  }

  /* ------------------------------------------------------------------
     CONSENTIMENTO DE COOKIES (LGPD)
  ------------------------------------------------------------------ */
  var Consent = (function () {
    var KEY = 'tx_consent', VERSION = 2, cbs = [];
    var CATS = ['necessary', 'functional', 'analytics', 'marketing'];
    function read() {
      var m = d.cookie.match(/(?:^|; )tx_consent=([^;]+)/), v = m ? decodeURIComponent(m[1]) : ls(KEY);
      try { var o = JSON.parse(v); if (o && o.v === VERSION) return o; } catch (e) {}
      return null;
    }
    function write(cats) {
      var prev = read();
      var o = { v: VERSION, id: (prev && prev.id) || ('tx-' + Date.now().toString(36) + '-' + rand(1e9).toString(36)), ts: new Date().toISOString(), cats: cats };
      var s = JSON.stringify(o);
      d.cookie = 'tx_consent=' + encodeURIComponent(s) + ';path=/;max-age=' + (180 * 86400) + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
      ls(KEY, s);
      apply(o); cbs.forEach(function (cb) { try { cb(o); } catch (e) {} });
      try { d.dispatchEvent(new CustomEvent('tx:consent', { detail: o })); } catch (e) {}
      return o;
    }
    function has(cat) { if (cat === 'necessary') return true; var o = read(); return !!(o && o.cats && o.cats[cat]); }
    function apply(o) {
      // libera scripts bloqueados: <script type="text/plain" data-tx-consent="analytics">
      $$('script[type="text/plain"][data-tx-consent]').forEach(function (s) {
        if (!has(s.getAttribute('data-tx-consent'))) return;
        var n = d.createElement('script');
        for (var i = 0; i < s.attributes.length; i++) { var a = s.attributes[i]; if (a.name !== 'type') n.setAttribute(a.name, a.value); }
        n.text = s.text; s.parentNode.replaceChild(n, s);
      });
      $$('.tx-map[data-src]').forEach(function (m) { if (has(m.getAttribute('data-tx-consent') || 'functional')) loadMap(m); });
    }
    function init() {
      var banner = $('.tx-cookie'), modal = $('#tx-cookie-modal'), reopen = $('.tx-cookie-reopen');
      var cur = read();
      var show = function () { if (banner) { banner.classList.add('is-on'); banner.removeAttribute('hidden'); } if (reopen) reopen.classList.remove('is-on'); };
      var hide = function () { if (banner) banner.classList.remove('is-on'); if (reopen) reopen.classList.add('is-on'); };
      var all = function (v) { var c = {}; CATS.forEach(function (k) { c[k] = k === 'necessary' ? true : v; }); return c; };
      var fill = function () { var o = read(); $$('[data-tx-cat]', modal).forEach(function (i) { var k = i.getAttribute('data-tx-cat'); i.checked = k === 'necessary' ? true : !!(o && o.cats[k]); }); };
      var lastFocus;
      var openModal = function () { if (!modal) return; lastFocus = d.activeElement; fill(); modal.classList.add('is-open'); modal.setAttribute('aria-hidden', 'false'); setTimeout(function () { var x = $('.tx-modal__x', modal); if (x) x.focus(); }, 30); };
      var closeModal = function () { if (!modal) return; modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); if (lastFocus && lastFocus.focus) lastFocus.focus(); };
      if (!cur) setTimeout(show, 700); else { apply(cur); hide(); }
      d.addEventListener('click', function (e) {
        var t = e.target.closest('[data-tx-cookie]'); if (!t) return;
        var act = t.getAttribute('data-tx-cookie');
        if (act === 'accept') { write(all(true)); hide(); closeModal(); }
        else if (act === 'reject') { write(all(false)); hide(); closeModal(); }
        else if (act === 'customize' || act === 'open') { e.preventDefault(); openModal(); }
        else if (act === 'save') { var c = all(false); $$('[data-tx-cat]', modal).forEach(function (i) { c[i.getAttribute('data-tx-cat')] = i.getAttribute('data-tx-cat') === 'necessary' ? true : i.checked; }); write(c); hide(); closeModal(); }
        else if (act === 'close') { closeModal(); }
      });
      if (modal) modal.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); if (e.key === 'Tab') trapTab(e, $('.tx-modal__box', modal)); });
    }
    return { init: init, read: read, has: has, set: write, on: function (cb) { cbs.push(cb); }, open: function () { var b = $('[data-tx-cookie="open"]'); if (b) b.click(); } };
  })();
  window.TelixConsent = Consent;

  function loadMap(m) {
    if (m.getAttribute('data-loaded')) return;
    var f = d.createElement('iframe');
    f.src = m.getAttribute('data-src'); f.loading = 'lazy'; f.referrerPolicy = 'no-referrer-when-downgrade';
    f.title = m.getAttribute('data-title') || 'Mapa de localização'; f.setAttribute('allowfullscreen', '');
    m.appendChild(f); m.setAttribute('data-loaded', '1');
    var g = $('.tx-map__gate', m); if (g) g.remove();
  }
  function initMaps() {
    $$('.tx-map[data-src]').forEach(function (m) {
      var b = $('[data-tx-map-load]', m);
      if (b) b.addEventListener('click', function () { loadMap(m); });
    });
  }

  /* ------------------------------------------------------------------
     ACESSIBILIDADE
  ------------------------------------------------------------------ */
  function initA11y() {
    var KEY = 'tx_a11y';
    var st = {}; try { st = JSON.parse(ls(KEY) || '{}') || {}; } catch (e) { st = {}; }
    var panel = $('.tx-a11y'), btn = $('.tx-a11y-btn');
    var TOGGLES = ['contrast', 'gray', 'links', 'readable', 'spacing', 'headings', 'cursor', 'noanim', 'guide'];
    function fontReadable() { if (st.readable && !$('#tx-font-readable')) { var l = d.createElement('link'); l.id = 'tx-font-readable'; l.rel = 'stylesheet'; l.href = 'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap'; d.head.appendChild(l); } }
    function apply() {
      TOGGLES.forEach(function (k) { html.classList.toggle('tx-a11y-' + k, !!st[k]); });
      html.style.setProperty('--tx-font-scale', st.scale || 1);
      $$('[data-a11y]', panel).forEach(function (b) { b.setAttribute('aria-pressed', st[b.getAttribute('data-a11y')] ? 'true' : 'false'); });
      var s = $('[data-a11y-scale-val]', panel); if (s) s.textContent = Math.round((st.scale || 1) * 100) + '%';
      fontReadable();
      ls(KEY, JSON.stringify(st));
    }
    apply();
    if (!panel || !btn) return;
    var setOpen = function (on) { panel.classList.toggle('is-open', on); btn.setAttribute('aria-expanded', on ? 'true' : 'false'); panel.setAttribute('aria-hidden', on ? 'false' : 'true'); if (on) setTimeout(function () { var f = $('button', panel); if (f) f.focus(); }, 40); };
    btn.addEventListener('click', function () { setOpen(!panel.classList.contains('is-open')); });
    panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); btn.focus(); } if (e.key === 'Tab') trapTab(e, panel); });
    d.addEventListener('click', function (e) { if (panel.classList.contains('is-open') && !panel.contains(e.target) && !btn.contains(e.target)) setOpen(false); });
    panel.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      if (b.hasAttribute('data-a11y')) { var k = b.getAttribute('data-a11y'); st[k] = !st[k]; apply(); }
      if (b.hasAttribute('data-a11y-scale')) { var v = parseFloat(b.getAttribute('data-a11y-scale')); st.scale = Math.max(.9, Math.min(1.6, Math.round(((st.scale || 1) + v) * 10) / 10)); apply(); }
      if (b.hasAttribute('data-a11y-reset')) { st = {}; apply(); }
      if (b.hasAttribute('data-a11y-libras')) { setOpen(false); openLibras(); }
      if (b.hasAttribute('data-a11y-close')) { setOpen(false); btn.focus(); }
    });
    var guide = $('.tx-guide');
    if (guide) addEventListener('pointermove', function (e) { if (st.guide) guide.style.top = (e.clientY - 7) + 'px'; }, { passive: true });
    // atalho Alt+Shift+A
    d.addEventListener('keydown', function (e) { if (e.altKey && e.shiftKey && (e.key === 'A' || e.key === 'a')) { e.preventDefault(); setOpen(true); } });
  }
  function openLibras() {
    if (window.VLibrasWidget && window.VLibrasWidget.open) { window.VLibrasWidget.open(); return; }
    loadVLibras(function () { setTimeout(function () { if (window.VLibrasWidget && window.VLibrasWidget.open) window.VLibrasWidget.open(); }, 400); });
  }
  var vlLoading = false;
  function loadVLibras(cb) {
    if (window.VLibras && window.VLibras.Widget && window.__txVL) { cb && cb(); return; }
    if (vlLoading) return; vlLoading = true;
    var s = d.createElement('script'); s.src = 'https://vlibras.gov.br/app/vlibras-plugin.js'; s.async = true;
    s.onload = function () { try { new window.VLibras.Widget({ rootPath: 'https://vlibras.gov.br/app', position: 'R' }); window.__txVL = 1; } catch (e) {} cb && cb(); };
    d.body.appendChild(s);
  }

  /* ------------------------------------------------------------------
     FORMULÁRIOS (LGPD, Compliance, Contato, Carreiras) + CAPTCHA MATEMÁTICO
     Envio nativo do WordPress (wp-comments-post.php) para a página do canal:
     os relatos ficam em "Comentários > Pendentes" no painel, com e-mail de moderação.
  ------------------------------------------------------------------ */
  var NUM = ['zero', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze'];
  function captcha(box) {
    var q = $('.tx-captcha__q', box), input = $('input', box), a, b, op, ans;
    function gen() {
      op = rand(3) === 0 ? '−' : '+';
      a = 2 + rand(9); b = 1 + rand(8);
      if (op === '−' && b > a) { var t = a; a = b; b = t; }
      ans = op === '+' ? a + b : a - b;
      q.textContent = a + ' ' + op + ' ' + b + ' = ?';
      q.setAttribute('aria-label', 'Quanto é ' + NUM[a] + (op === '+' ? ' mais ' : ' menos ') + NUM[b] + '?');
      input.value = '';
    }
    gen();
    var r = $('button', box); if (r) r.addEventListener('click', function () { gen(); input.focus(); });
    return { ok: function () { return parseInt(input.value, 10) === ans; }, reset: gen, input: input };
  }
  function protocol(prefix) {
    var now = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', c = '';
    for (var i = 0; i < 6; i++) c += chars.charAt(rand(chars.length));
    return (prefix || 'TLX') + '-' + now.getFullYear() + p(now.getMonth() + 1) + p(now.getDate()) + '-' + c;
  }
  function fieldLabel(el, form) {
    var id = el.id, l = id ? form.querySelector('label[for="' + id + '"]') : null;
    if (el.getAttribute('data-label')) return el.getAttribute('data-label');
    if (l) return l.textContent.replace(/\*/g, '').trim();
    var fs = el.closest('fieldset'); if (fs) { var lg = $('legend', fs); if (lg) return lg.textContent.replace(/\*/g, '').trim(); }
    return el.name;
  }
  function setInvalid(el, msg) {
    var f = el.closest('.tx-field'); if (!f) return;
    f.classList.add('is-invalid'); el.setAttribute('aria-invalid', 'true');
    var e = $('.tx-err', f); if (!e) { e = d.createElement('div'); e.className = 'tx-err'; e.id = (el.id || el.name) + '-err'; f.appendChild(e); }
    e.textContent = msg; el.setAttribute('aria-describedby', e.id);
  }
  function clearInvalid(el) { var f = el.closest('.tx-field'); if (f) f.classList.remove('is-invalid'); el.removeAttribute('aria-invalid'); }
  function pageId() {
    var m = (d.body.className || '').match(/(?:^|\s)(?:page-id|postid)-(\d+)/); return m ? m[1] : null;
  }
  function initForms() {
    $$('form.tx-form[data-channel]').forEach(function (form) {
      var cap = $('.tx-captcha', form) ? captcha($('.tx-captcha', form)) : null;
      var t0 = Date.now();
      var status = $('.tx-form__status', form);
      var anon = $('[data-tx-anon]', form);
      if (anon) {
        var toggleAnon = function () {
          $$('[data-tx-ident]', form).forEach(function (w) {
            w.hidden = anon.checked;
            $$('input,select,textarea', w).forEach(function (i) { i.disabled = anon.checked; if (i.hasAttribute('data-req')) i.required = !anon.checked; });
          });
        };
        $$('[data-tx-ident] [required]', form).forEach(function (i) { i.setAttribute('data-req', '1'); });
        anon.addEventListener('change', toggleAnon); toggleAnon();
      }
      $$('input,select,textarea', form).forEach(function (el) { el.addEventListener('input', function () { clearInvalid(el); }); el.addEventListener('change', function () { clearInvalid(el); }); });
      // campos condicionais: data-show-if="name=value"
      $$('[data-show-if]', form).forEach(function (w) {
        var parts = w.getAttribute('data-show-if').split('='), src = form.elements[parts[0]];
        var upd = function () { var v = form.elements[parts[0]]; var val = v && (v.value !== undefined ? v.value : ''); if (v && v.length && !v.value) { $$('[name="' + parts[0] + '"]', form).forEach(function (r) { if (r.checked) val = r.value; }); } var on = parts[1].split('|').indexOf(val) > -1; w.hidden = !on; $$('input,select,textarea', w).forEach(function (i) { i.disabled = !on; }); };
        $$('[name="' + parts[0] + '"]', form).forEach(function (s) { s.addEventListener('change', upd); }); upd();
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (status) { status.innerHTML = ''; }
        var bad = null;
        $$('input,select,textarea', form).forEach(function (el) {
          if (el.disabled || el.closest('.tx-hp') || el.type === 'hidden') return;
          clearInvalid(el);
          if (!el.checkValidity()) {
            var msg = el.validity.valueMissing ? (el.type === 'checkbox' ? 'Marque esta opção para continuar.' : 'Campo obrigatório.') : el.validity.typeMismatch ? (el.type === 'email' ? 'Informe um e-mail válido.' : 'Formato inválido.') : el.validity.patternMismatch ? (el.getAttribute('data-pattern-msg') || 'Formato inválido.') : el.validity.tooShort ? 'Texto muito curto (mínimo ' + el.minLength + ' caracteres).' : 'Verifique este campo.';
            setInvalid(el, msg); if (!bad) bad = el;
          }
        });
        // grupos de rádio/checkbox obrigatórios
        $$('fieldset[data-required]', form).forEach(function (fs) { if (fs.disabled || fs.closest('[hidden]')) return; if (!$$('input:checked', fs).length) { var i = $('input', fs); var f = fs.closest('.tx-field'); if (f) { f.classList.add('is-invalid'); var er = $('.tx-err', f); if (er) er.textContent = 'Selecione ao menos uma opção.'; } if (!bad) bad = i; } });
        if (cap && !cap.ok()) { setInvalid(cap.input, 'Resposta incorreta. Resolva a conta para confirmar que você não é um robô.'); cap.reset(); if (!bad) bad = cap.input; }
        if (bad) { bad.focus(); if (status) status.innerHTML = '<div class="tx-alert tx-alert--err" role="alert"><svg class="tx-ic"><use href="#i-alert"/></svg><div>Revise os campos destacados antes de enviar.</div></div>'; return; }
        var hp = $('.tx-hp input', form);
        if ((hp && hp.value) || Date.now() - t0 < 3500) { fakeOk(form); return; } // provável robô

        var channel = form.getAttribute('data-channel'), prefix = form.getAttribute('data-prefix') || 'TLX';
        var proto = protocol(prefix);
        var lines = ['[' + (form.getAttribute('data-title') || channel).toUpperCase() + ']', 'Protocolo: ' + proto, 'Data/hora: ' + new Date().toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' }) + ' (Brasília)', 'Origem: ' + location.href.split('#')[0], '=============================='];
        var name = '', email = '';
        var seen = {};
        $$('input,select,textarea', form).forEach(function (el) {
          if (el.disabled || el.closest('.tx-hp') || el.closest('.tx-captcha') || el.type === 'hidden' || el.type === 'submit' || !el.name) return;
          if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) return;
          var lab = fieldLabel(el, form), val = el.type === 'checkbox' && el.value === 'on' ? 'Sim' : el.value;
          if (!String(val).trim()) return;
          if (el.name === 'nome') name = val; if (el.name === 'email') email = val;
          if (seen[lab] !== undefined) { lines[seen[lab]] += '; ' + val; return; }
          seen[lab] = lines.length;
          lines.push(lab + ': ' + String(val).replace(/\r/g, ''));
        });
        if (anon && anon.checked) lines.splice(5, 0, 'Identificação: ANÔNIMO (o relator optou por não se identificar)');
        var body = lines.join('\n');
        var fd = new URLSearchParams();
        fd.append('comment_post_ID', form.getAttribute('data-inbox') || pageId() || '');
        fd.append('author', (anon && anon.checked) ? 'Anônimo — ' + proto : ((name || 'Visitante') + ' — ' + proto).slice(0, 240));
        // e-mail técnico único por protocolo: garante que todo relato fique PENDENTE (nunca público)
        fd.append('email', proto.toLowerCase() + '@protocolo.telixcom.com.br');
        fd.append('url', '');
        fd.append('comment', body);
        fd.append('comment_parent', '0');
        var btn = $('button[type=submit]', form); if (btn) { btn.disabled = true; btn.setAttribute('data-txt', btn.innerHTML); btn.innerHTML = 'Enviando…'; }
        form.setAttribute('aria-busy', 'true');
        var done = function () { form.removeAttribute('aria-busy'); if (btn) { btn.disabled = false; btn.innerHTML = btn.getAttribute('data-txt'); } };
        var attempt = function (n) {
          return fetch(HOME + '/wp-comments-post.php', { method: 'POST', body: fd.toString(), headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, credentials: 'same-origin', redirect: 'manual' })
            .then(function (r) {
              // 302 (opaqueredirect) = gravado; 409 = já gravado (reenvio idêntico)
              if (r.type === 'opaqueredirect' || r.status === 302 || r.status === 409 || (r.ok && r.redirected)) return { ok: true };
              if (r.status === 429 && n < 3) { if (status) status.innerHTML = '<div class="tx-alert tx-alert--info" role="status"><div>Aguarde alguns segundos, estamos concluindo o envio…</div></div>'; return new Promise(function (res) { setTimeout(res, 16000); }).then(function () { return attempt(n + 1); }); }
              return r.text().then(function (t) { var m = (t.match(/<div class="wp-die-message">([\s\S]*?)<\/div>/) || t.match(/<p>([\s\S]*?)<\/p>/) || [])[1] || ''; return { ok: false, msg: m.replace(/<[^>]+>/g, '').trim(), status: r.status }; });
            })
            .catch(function () { if (n < 4) return new Promise(function (res) { setTimeout(res, 2000 * (n + 1)); }).then(function () { return attempt(n + 1); }); return { ok: false, msg: '' }; });
        };
        attempt(0).then(function (x) {
          if (x.ok) success(form, proto, email, channel);
          else fail(form, proto, body, x.status === 406 || x.status === 403 ? 'O envio foi bloqueado por uma regra de segurança do servidor.' : x.msg);
        }).then(done, done);
      });
    });
  }
  function success(form, proto, email, channel) {
    var tpl = form.getAttribute('data-success') || 'Recebemos sua mensagem. Guarde o número de protocolo para acompanhamento.';
    var box = d.createElement('div');
    box.className = 'tx-form__success'; box.setAttribute('role', 'status'); box.setAttribute('tabindex', '-1');
    box.innerHTML = '<div class="tx-icon"><svg class="tx-ic"><use href="#i-check-circle"/></svg></div><h3>Envio realizado com sucesso</h3><p>' + esc(tpl) + '</p><div class="tx-protocol" aria-label="Número de protocolo">' + esc(proto) + '</div><p class="tx-small tx-muted">Recomendamos anotar ou copiar este número agora. ' + (channel === 'compliance' ? 'Por segurança, ele não é enviado por e-mail.' : '') + '</p><div class="tx-btns" style="justify-content:center"><button type="button" class="tx-btn tx-btn--dark tx-btn--sm" data-copy="' + esc(proto) + '"><svg class="tx-ic"><use href="#i-copy"/></svg>Copiar protocolo</button><button type="button" class="tx-btn tx-btn--ghost tx-btn--sm" onclick="window.print()"><svg class="tx-ic"><use href="#i-printer"/></svg>Imprimir comprovante</button></div>';
    form.innerHTML = ''; form.appendChild(box); box.focus();
    var c = $('[data-copy]', box); if (c) c.addEventListener('click', function () { try { navigator.clipboard.writeText(proto); c.innerHTML = '<svg class="tx-ic"><use href="#i-check"/></svg>Copiado!'; } catch (e) {} });
    try { d.dispatchEvent(new CustomEvent('tx:form-sent', { detail: { channel: channel } })); } catch (e) {}
  }
  function fakeOk(form) { success(form, protocol(form.getAttribute('data-prefix') || 'TLX'), '', form.getAttribute('data-channel')); }
  function fail(form, proto, body, msg) {
    var status = $('.tx-form__status', form);
    var to = form.getAttribute('data-fallback-email') || CFG.email || '';
    var mail = 'mailto:' + to + '?subject=' + encodeURIComponent('[' + proto + '] ' + (form.getAttribute('data-title') || 'Contato pelo site')) + '&body=' + encodeURIComponent(body.slice(0, 1800));
    if (status) status.innerHTML = '<div class="tx-alert tx-alert--err" role="alert"><svg class="tx-ic"><use href="#i-alert"/></svg><div><strong>Não foi possível concluir o envio agora.</strong> ' + (msg ? esc(msg) + ' ' : '') + 'Tente novamente em instantes' + (to ? ' ou <a href="' + mail + '">envie por e-mail</a> (o texto já vai preenchido com o protocolo ' + esc(proto) + ')' : '') + '.</div></div>';
  }

  /* ------------------------------------------------------------------
     SEO dinâmico: metatags, Open Graph, Twitter, JSON-LD (Organization,
     LocalBusiness, WebSite, WebPage, BreadcrumbList, FAQPage + específicos)
     URLs absolutas geradas a partir do domínio atual → sobrevive a migrações.
  ------------------------------------------------------------------ */
  function meta(attr, key, val) {
    if (!val) return;
    var m = d.head.querySelector('meta[' + attr + '="' + key + '"]');
    if (!m) { m = d.createElement('meta'); m.setAttribute(attr, key); d.head.appendChild(m); }
    m.setAttribute('content', val);
  }
  function ld(obj) { var s = d.createElement('script'); s.type = 'application/ld+json'; s.text = JSON.stringify(obj); d.head.appendChild(s); }
  function deepAbs(o) {
    if (typeof o === 'string') return o.replace(/\{home\}/g, HOME);
    if (Array.isArray(o)) return o.map(deepAbs);
    if (o && typeof o === 'object') { var r = {}; for (var k in o) r[k] = deepAbs(o[k]); return r; }
    return o;
  }
  function initSEO() {
    var el = $('#tx-seo'), p = {};
    try { p = el ? JSON.parse(el.textContent) : {}; } catch (e) { p = {}; }
    var canonEl = d.head.querySelector('link[rel="canonical"]');
    if (!canonEl) { canonEl = d.createElement('link'); canonEl.rel = 'canonical'; d.head.appendChild(canonEl); canonEl.href = location.origin + location.pathname; }
    if (p.canonical) canonEl.href = abs(p.canonical);
    var url = canonEl.href;
    var org = CFG.org || {};
    var title = p.title || d.title;
    if (p.title) d.title = p.title;
    var desc = p.description || CFG.description || '';
    var img = abs(p.image || CFG.ogImage);
    meta('name', 'description', desc);
    if (p.keywords) meta('name', 'keywords', p.keywords);
    meta('name', 'robots', p.noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
    meta('name', 'theme-color', '#56534b');
    meta('name', 'author', org.name || 'Télix Comunicação e Relacionamento');
    meta('name', 'geo.region', CFG.geoRegion); meta('name', 'geo.placename', CFG.geoPlace);
    if (CFG.geo) { meta('name', 'geo.position', CFG.geo.lat + ';' + CFG.geo.lng); meta('name', 'ICBM', CFG.geo.lat + ', ' + CFG.geo.lng); }
    meta('property', 'og:locale', 'pt_BR'); meta('property', 'og:site_name', org.name || 'Télix');
    meta('property', 'og:type', p.ogType || 'website'); meta('property', 'og:title', title); meta('property', 'og:description', desc);
    meta('property', 'og:url', url); meta('property', 'og:image', img); meta('property', 'og:image:alt', p.imageAlt || title);
    meta('name', 'twitter:card', 'summary_large_image'); meta('name', 'twitter:title', title); meta('name', 'twitter:description', desc); meta('name', 'twitter:image', img);
    if (CFG.favicon && !d.head.querySelector('link[rel~="icon"]')) {
      ['icon', 'apple-touch-icon'].forEach(function (r) { var l = d.createElement('link'); l.rel = r; l.href = abs(CFG.favicon); d.head.appendChild(l); });
    }
    // JSON-LD
    var orgId = HOME + '/#organization', siteId = HOME + '/#website';
    var graph = [];
    var O = deepAbs(org.schema || {});
    O['@type'] = O['@type'] || ['Organization', 'LocalBusiness'];
    O['@id'] = orgId; O.url = HOME + '/';
    if (CFG.logo) { O.logo = { '@type': 'ImageObject', url: abs(CFG.logo) }; O.image = abs(CFG.ogImage || CFG.logo); }
    graph.push(O);
    graph.push({ '@type': 'WebSite', '@id': siteId, url: HOME + '/', name: org.name || 'Télix', inLanguage: 'pt-BR', publisher: { '@id': orgId }, potentialAction: { '@type': 'SearchAction', target: HOME + '/?s={search_term_string}', 'query-input': 'required name=search_term_string' } });
    var wp = { '@type': p.pageType || 'WebPage', '@id': url + '#webpage', url: url, name: title, description: desc, inLanguage: 'pt-BR', isPartOf: { '@id': siteId }, about: { '@id': orgId } };
    if (img) wp.primaryImageOfPage = { '@type': 'ImageObject', url: img };
    graph.push(wp);
    if (p.breadcrumb && p.breadcrumb.length) {
      graph.push({ '@type': 'BreadcrumbList', '@id': url + '#breadcrumb', itemListElement: p.breadcrumb.map(function (b, i) { return { '@type': 'ListItem', position: i + 1, name: b[0], item: abs(b[1]) }; }) });
      wp.breadcrumb = { '@id': url + '#breadcrumb' };
    }
    var faqs = $$('.tx-faq[data-schema] details');
    if (faqs.length) graph.push({ '@type': 'FAQPage', '@id': url + '#faq', mainEntity: faqs.map(function (f) { return { '@type': 'Question', name: $('summary', f).textContent.trim(), acceptedAnswer: { '@type': 'Answer', text: ($('div', f) || f).textContent.trim().replace(/\s+/g, ' ') } }; }) });
    (p.schema || []).forEach(function (s) { var x = deepAbs(s); if (x.provider === 'org') x.provider = { '@id': orgId }; if (x.publisher === 'org') x.publisher = { '@id': orgId }; graph.push(x); });
    ld({ '@context': 'https://schema.org', '@graph': graph });
  }

  /* ------------------------------------------------------------------
     3D (Three.js carregado sob demanda, apenas onde houver [data-tx-3d])
  ------------------------------------------------------------------ */
  var THREE_URL = 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js';
  function webgl() { try { var c = d.createElement('canvas'); return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl'))); } catch (e) { return false; } }
  function init3D() {
    var els = $$('[data-tx-3d]');
    if (!els.length || !webgl()) return;
    var start = function (el) {
      if (el.__tx3d) return; el.__tx3d = 1;
      (window.__txThree || (window.__txThree = import(THREE_URL))).then(function (T) {
        var kind = el.getAttribute('data-tx-3d');
        var fn = SCENES[kind]; if (!fn) return;
        try { fn(T, el); el.classList.add('is-ready'); } catch (err) { if (window.console) console.warn('3D', kind, err); }
      }).catch(function (e) { if (window.console) console.warn('three load', e); });
    };
    if (!('IntersectionObserver' in window)) { els.forEach(start); return; }
    var io = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) { start(x.target); io.unobserve(x.target); } }); }, { rootMargin: '300px' });
    els.forEach(function (e) { io.observe(e); });
  }
  function stage(T, el, opts) {
    opts = opts || {};
    var r = new T.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    r.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    r.outputColorSpace = T.SRGBColorSpace;
    el.appendChild(r.domElement);
    r.domElement.setAttribute('aria-hidden', 'true');
    var scene = new T.Scene();
    var cam = new T.PerspectiveCamera(opts.fov || 42, 1, 0.1, 100);
    cam.position.set(0, 0, opts.z || 6.2);
    var size = function () { var w = el.clientWidth || 300, h = el.clientHeight || 300; r.setSize(w, h, false); cam.aspect = w / h; cam.updateProjectionMatrix(); };
    size();
    if ('ResizeObserver' in window) new ResizeObserver(size).observe(el); else addEventListener('resize', size);
    // interação: ponteiro + arrastar com inércia
    var st = { mx: 0, my: 0, dragX: 0, dragY: 0, vx: 0, vy: 0, down: false, lx: 0, ly: 0, visible: true, hover: false };
    el.addEventListener('pointermove', function (e) {
      var b = el.getBoundingClientRect(); st.mx = ((e.clientX - b.left) / b.width) * 2 - 1; st.my = ((e.clientY - b.top) / b.height) * 2 - 1; st.px = e.clientX - b.left; st.py = e.clientY - b.top; st.hover = true;
      if (st.down) { var dx = e.clientX - st.lx, dy = e.clientY - st.ly; st.vx = dx * 0.006; st.vy = dy * 0.004; st.dragX += st.vx; st.dragY += st.vy; st.lx = e.clientX; st.ly = e.clientY; }
    });
    el.addEventListener('pointerleave', function () { st.mx = 0; st.my = 0; st.hover = false; });
    el.addEventListener('pointerdown', function (e) { st.down = true; st.lx = e.clientX; st.ly = e.clientY; if (e.pointerType === 'mouse') el.setPointerCapture(e.pointerId); });
    el.addEventListener('pointerup', function () { st.down = false; });
    el.addEventListener('pointercancel', function () { st.down = false; });
    if ('IntersectionObserver' in window) new IntersectionObserver(function (en) { st.visible = en[0].isIntersecting; }, { threshold: 0 }).observe(el);
    var clock = new T.Clock(), tick = null;
    function loop() {
      requestAnimationFrame(loop);
      if (!st.visible || d.hidden) { clock.getDelta(); return; }
      var dt = Math.min(clock.getDelta(), 0.05);
      if (!st.down) { st.vx *= 0.94; st.vy *= 0.94; st.dragX += st.vx; st.dragY += st.vy; }
      st.dragY = Math.max(-0.9, Math.min(0.9, st.dragY));
      if (tick) tick(dt, clock.elapsedTime, st);
      r.render(scene, cam);
    }
    return { T: T, r: r, scene: scene, cam: cam, st: st, run: function (fn) { tick = fn; loop(); } };
  }
  function dotTexture(T, inner, outer) {
    var c = d.createElement('canvas'); c.width = c.height = 64; var g = c.getContext('2d');
    var gr = g.createRadialGradient(32, 32, 0, 32, 32, 32); gr.addColorStop(0, inner || 'rgba(255,245,215,1)'); gr.addColorStop(0.35, outer || 'rgba(233,185,71,.9)'); gr.addColorStop(1, 'rgba(233,185,71,0)');
    g.fillStyle = gr; g.fillRect(0, 0, 64, 64); var t = new T.CanvasTexture(c); t.colorSpace = T.SRGBColorSpace; return t;
  }
  function labelsLayer(el, names) {
    var layer = d.createElement('div'); layer.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:2'; layer.setAttribute('aria-hidden', 'true');
    var nodes = names.map(function (n) { var s = d.createElement('span'); s.textContent = n; s.style.cssText = 'position:absolute;left:0;top:0;transform:translate(-50%,-50%);padding:6px 11px;border-radius:999px;background:rgba(36,34,30,.78);border:1px solid rgba(233,185,71,.55);color:#fff;font:600 12px/1.1 Montserrat,Roboto,sans-serif;white-space:nowrap;backdrop-filter:blur(4px);transition:opacity .2s;box-shadow:0 6px 16px -8px rgba(0,0,0,.6)'; layer.appendChild(s); return s; });
    el.appendChild(layer); return nodes;
  }
  var SCENES = {
    /* Rede de relacionamento: esfera de nós conectados, pulsos de comunicação e canais em órbita */
    network: function (T, el) {
      var S = stage(T, el, { z: 7.3 }), scene = S.scene, st = S.st, red = reduced();
      var light = el.getAttribute('data-variant') === 'light';
      var root = new T.Group(); scene.add(root);
      var N = light ? 110 : 190, R = 1.75, pts = [], pos = new Float32Array(N * 3);
      for (var i = 0; i < N; i++) {
        var y = 1 - (i / (N - 1)) * 2, rad = Math.sqrt(1 - y * y), th = Math.PI * (3 - Math.sqrt(5)) * i;
        var j = 1 + (Math.sin(i * 12.9898) * 43758.5453 % 1) * 0.06;
        var v = new T.Vector3(Math.cos(th) * rad * R * j, y * R * j, Math.sin(th) * rad * R * j); pts.push(v); pos.set([v.x, v.y, v.z], i * 3);
      }
      var gp = new T.BufferGeometry(); gp.setAttribute('position', new T.BufferAttribute(pos, 3));
      var dot = dotTexture(T);
      root.add(new T.Points(gp, new T.PointsMaterial({ size: 0.11, map: dot, transparent: true, depthWrite: false, blending: T.AdditiveBlending })));
      var edges = [], seg = [];
      for (i = 0; i < N; i++) {
        var ds = []; for (var k = 0; k < N; k++) if (k !== i) ds.push([pts[i].distanceToSquared(pts[k]), k]);
        ds.sort(function (a, b) { return a[0] - b[0]; });
        for (var m = 0; m < 3; m++) { var o = ds[m][1]; if (i < o) { edges.push([i, o]); seg.push(pts[i].x, pts[i].y, pts[i].z, pts[o].x, pts[o].y, pts[o].z); } }
      }
      var gl = new T.BufferGeometry(); gl.setAttribute('position', new T.Float32BufferAttribute(seg, 3));
      root.add(new T.LineSegments(gl, new T.LineBasicMaterial({ color: 0xf3d78d, transparent: true, opacity: 0.22 })));
      var core = new T.Mesh(new T.IcosahedronGeometry(0.95, 1), new T.MeshBasicMaterial({ color: 0xe9b947, wireframe: true, transparent: true, opacity: 0.18 }));
      root.add(core);
      var glow = new T.Sprite(new T.SpriteMaterial({ map: dotTexture(T, 'rgba(255,235,180,.55)', 'rgba(233,185,71,.18)'), transparent: true, depthWrite: false, blending: T.AdditiveBlending }));
      glow.scale.set(4.2, 4.2, 1); scene.add(glow);
      // anéis e satélites (canais)
      var names = (el.getAttribute('data-labels') || 'Telefone,WhatsApp,Chatbot,E-mail,Protocolo,Pesquisa').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      var rings = [], sats = [];
      [[2.35, 0.35, 0.2], [2.7, -0.5, 0.9]].forEach(function (cfg, ri) {
        var g = new T.Group(); g.rotation.set(cfg[1], 0, cfg[2]);
        g.add(new T.Mesh(new T.TorusGeometry(cfg[0], 0.006, 6, 220), new T.MeshBasicMaterial({ color: 0xe9b947, transparent: true, opacity: 0.45 })));
        root.add(g); rings.push(g);
      });
      var tagEls = labelsLayer(el, names);
      names.forEach(function (n, idx) {
        var ring = rings[idx % rings.length], rr = idx % 2 ? 2.7 : 2.35;
        var sp = new T.Sprite(new T.SpriteMaterial({ map: dot, transparent: true, depthWrite: false, blending: T.AdditiveBlending })); sp.scale.set(0.34, 0.34, 1);
        ring.add(sp); sats.push({ s: sp, r: rr, a: (idx / names.length) * Math.PI * 2, sp: 0.18 + (idx % 3) * 0.05 });
      });
      // pulsos percorrendo as conexões
      var P = light ? 14 : 28, pulses = [], pg = new T.BufferGeometry(), pp = new Float32Array(P * 3);
      pg.setAttribute('position', new T.BufferAttribute(pp, 3));
      root.add(new T.Points(pg, new T.PointsMaterial({ size: 0.2, map: dotTexture(T, 'rgba(255,255,255,1)', 'rgba(255,215,120,.95)'), transparent: true, depthWrite: false, blending: T.AdditiveBlending })));
      for (i = 0; i < P; i++) pulses.push({ e: edges[(i * 37) % edges.length], t: (i / P) });
      var tmp = new T.Vector3(), proj = new T.Vector3();
      S.run(function (dt, t, st) {
        var spin = red ? 0 : 0.12;
        var targetY = t * spin + st.dragX + st.mx * 0.35, targetX = st.dragY + st.my * 0.25;
        root.rotation.y += (targetY - root.rotation.y) * 0.06; root.rotation.x += (targetX - root.rotation.x) * 0.06;
        core.rotation.y -= dt * 0.25; core.rotation.x += dt * 0.1;
        rings[0].rotation.z += dt * 0.05; if (rings[1]) rings[1].rotation.z -= dt * 0.04;
        for (var i = 0; i < P; i++) {
          var p = pulses[i]; p.t += dt * (red ? 0 : 0.55); if (p.t > 1) { p.t = 0; p.e = edges[Math.floor(Math.random() * edges.length)]; }
          tmp.copy(pts[p.e[0]]).lerp(pts[p.e[1]], p.t); pp[i * 3] = tmp.x; pp[i * 3 + 1] = tmp.y; pp[i * 3 + 2] = tmp.z;
        }
        pg.attributes.position.needsUpdate = true;
        var w = el.clientWidth, h = el.clientHeight;
        sats.forEach(function (s, i) {
          s.a += dt * (red ? 0 : s.sp); s.s.position.set(Math.cos(s.a) * s.r, Math.sin(s.a) * s.r, 0);
          s.s.getWorldPosition(proj); var depth = proj.z; proj.project(S.cam);
          var tg = tagEls[i]; tg.style.transform = 'translate(' + ((proj.x + 1) / 2 * w) + 'px,' + ((1 - proj.y) / 2 * h) + 'px) translate(-50%,-160%)';
          tg.style.opacity = depth > -0.6 ? '1' : '0.25';
        });
        var sc = 4.2 + Math.sin(t * 1.4) * 0.15; glow.scale.set(sc, sc, 1);
      });
    },
    /* Escudo de conformidade: escudo metálico dourado com check, partículas de dados protegidos */
    shield: function (T, el) {
      var S = stage(T, el, { z: 6 }), scene = S.scene, red = reduced();
      scene.add(new T.AmbientLight(0xffffff, 0.55));
      var dl = new T.DirectionalLight(0xffffff, 1.6); dl.position.set(3, 4, 5); scene.add(dl);
      var pl = new T.PointLight(0xe9b947, 30, 20); pl.position.set(-3, -1, 3); scene.add(pl);
      var sh = new T.Shape();
      sh.moveTo(0, 1.35); sh.bezierCurveTo(0.55, 1.12, 0.95, 1.05, 1.2, 1.0); sh.lineTo(1.2, 0.05);
      sh.bezierCurveTo(1.2, -0.7, 0.62, -1.18, 0, -1.45); sh.bezierCurveTo(-0.62, -1.18, -1.2, -0.7, -1.2, 0.05);
      sh.lineTo(-1.2, 1.0); sh.bezierCurveTo(-0.95, 1.05, -0.55, 1.12, 0, 1.35);
      var geo = new T.ExtrudeGeometry(sh, { depth: 0.32, bevelEnabled: true, bevelThickness: 0.1, bevelSize: 0.09, bevelSegments: 6, curveSegments: 36 }); geo.center();
      var g = new T.Group(); scene.add(g);
      g.add(new T.Mesh(geo, new T.MeshStandardMaterial({ color: 0xe9b947, metalness: 0.55, roughness: 0.28, emissive: 0x3a2a05, emissiveIntensity: 0.4 })));
      var inner = new T.Shape(); inner.moveTo(0, 1.0); inner.bezierCurveTo(0.45, 0.83, 0.75, 0.78, 0.92, 0.75); inner.lineTo(0.92, 0.05); inner.bezierCurveTo(0.92, -0.52, 0.47, -0.9, 0, -1.12); inner.bezierCurveTo(-0.47, -0.9, -0.92, -0.52, -0.92, 0.05); inner.lineTo(-0.92, 0.75); inner.bezierCurveTo(-0.75, 0.78, -0.45, 0.83, 0, 1.0);
      var ig = new T.ExtrudeGeometry(inner, { depth: 0.1, bevelEnabled: false, curveSegments: 36 }); ig.center();
      var im = new T.Mesh(ig, new T.MeshStandardMaterial({ color: 0x3a3833, metalness: 0.3, roughness: 0.6 })); im.position.z = 0.24; g.add(im);
      var ck = new T.CatmullRomCurve3([new T.Vector3(-0.45, 0.02, 0), new T.Vector3(-0.12, -0.3, 0), new T.Vector3(0.5, 0.42, 0)], false, 'catmullrom', 0);
      var cm = new T.Mesh(new T.TubeGeometry(ck, 40, 0.085, 12, false), new T.MeshStandardMaterial({ color: 0xf3d78d, metalness: 0.4, roughness: 0.25, emissive: 0x6b4d0a, emissiveIntensity: 0.6 })); cm.position.z = 0.36; g.add(cm);
      var N = 260, pos = new Float32Array(N * 3), seeds = [];
      for (var i = 0; i < N; i++) { seeds.push([Math.random() * Math.PI * 2, 1.9 + Math.random() * 1.1, (Math.random() - 0.5) * 2.4, 0.1 + Math.random() * 0.3]); }
      var pg = new T.BufferGeometry(); pg.setAttribute('position', new T.BufferAttribute(pos, 3));
      scene.add(new T.Points(pg, new T.PointsMaterial({ size: 0.07, map: dotTexture(T), transparent: true, depthWrite: false, blending: T.AdditiveBlending })));
      S.run(function (dt, t, st) {
        var ty = (red ? 0 : Math.sin(t * 0.5) * 0.35) + st.mx * 0.5 + st.dragX, tx = st.my * 0.3 + st.dragY;
        g.rotation.y += (ty - g.rotation.y) * 0.06; g.rotation.x += (tx - g.rotation.x) * 0.06; g.position.y = red ? 0 : Math.sin(t * 1.2) * 0.06;
        for (var i = 0; i < N; i++) { var s = seeds[i]; s[0] += dt * s[3] * (red ? 0 : 1); pos[i * 3] = Math.cos(s[0]) * s[1]; pos[i * 3 + 1] = s[2] + Math.sin(t * 0.6 + i) * 0.05; pos[i * 3 + 2] = Math.sin(s[0]) * s[1] * 0.6; }
        pg.attributes.position.needsUpdate = true;
      });
    },
    /* Regulação e remoção: rede de hospitais com rotas e unidades em deslocamento */
    route: function (T, el) {
      var S = stage(T, el, { z: 7, fov: 40 }), scene = S.scene, red = reduced();
      S.cam.position.set(0, 5.4, 8.8); S.cam.lookAt(0, 0.2, 0);
      scene.fog = new T.Fog(0x2d2b26, 8, 15);
      scene.add(new T.AmbientLight(0xffffff, 0.7)); var dl = new T.DirectionalLight(0xffffff, 1.2); dl.position.set(2, 5, 3); scene.add(dl);
      var world = new T.Group(); scene.add(world);
      var grid = new T.GridHelper(10, 20, 0xe9b947, 0x6b675e); grid.material.transparent = true; grid.material.opacity = 0.22; world.add(grid);
      var nodes = [[-3, -1.2], [-1.2, 1.6], [0.6, -0.4], [2.6, 1.2], [3.1, -1.8], [-2.4, 2.2], [1.2, 2.6]];
      var tex = dotTexture(T);
      nodes.forEach(function (n, i) {
        var h = i === 2 ? 1.1 : 0.45 + (i % 3) * 0.2;
        var b = new T.Mesh(new T.BoxGeometry(0.42, h, 0.42), new T.MeshStandardMaterial({ color: i === 2 ? 0xe9b947 : 0x56534b, metalness: 0.2, roughness: 0.5 }));
        b.position.set(n[0], h / 2, n[1]); world.add(b);
        var cross = new T.Sprite(new T.SpriteMaterial({ map: tex, transparent: true, depthWrite: false, blending: T.AdditiveBlending })); cross.scale.set(0.5, 0.5, 1); cross.position.set(n[0], h + 0.25, n[1]); world.add(cross);
      });
      var hub = new T.Vector3(nodes[2][0], 0.05, nodes[2][1]), curves = [];
      nodes.forEach(function (n, i) {
        if (i === 2) return;
        var a = new T.Vector3(n[0], 0.05, n[1]), mid = a.clone().add(hub).multiplyScalar(0.5); mid.y = 1.1 + (i % 2) * 0.4;
        var c = new T.QuadraticBezierCurve3(a, mid, hub); curves.push(c);
        world.add(new T.Mesh(new T.TubeGeometry(c, 60, 0.018, 6, false), new T.MeshBasicMaterial({ color: 0xe9b947, transparent: true, opacity: 0.55 })));
      });
      var units = curves.map(function (c, i) { var m = new T.Sprite(new T.SpriteMaterial({ map: dotTexture(T, 'rgba(255,255,255,1)', 'rgba(255,120,80,.95)'), transparent: true, depthWrite: false, blending: T.AdditiveBlending })); m.scale.set(0.34, 0.34, 1); world.add(m); return { m: m, c: c, t: i / curves.length, dir: i % 2 ? 1 : -1 }; });
      var ringM = new T.Mesh(new T.RingGeometry(0.4, 0.46, 48), new T.MeshBasicMaterial({ color: 0xe9b947, transparent: true, opacity: 0.8, side: T.DoubleSide })); ringM.rotation.x = -Math.PI / 2; ringM.position.copy(hub); world.add(ringM);
      S.run(function (dt, t, st) {
        var ty = (red ? 0 : t * 0.08) + st.mx * 0.4 + st.dragX; world.rotation.y += (ty - world.rotation.y) * 0.05;
        units.forEach(function (u) { u.t += dt * (red ? 0 : 0.22); if (u.t > 1) u.t = 0; var k = u.dir > 0 ? u.t : 1 - u.t; u.m.position.copy(u.c.getPoint(k)); });
        var s = 1 + ((t * 0.8) % 1) * 2.2; ringM.scale.set(s, s, s); ringM.material.opacity = 0.8 * (1 - ((t * 0.8) % 1));
      });
    },
    /* Mapa 3D do Brasil: estados atendidos em destaque, pinos dos clientes, tooltip interativo */
    brmap: function (T, el) {
      var geoEl = d.getElementById('tx-br-geo'); if (!geoEl) return;
      var GEO = JSON.parse(geoEl.textContent), data = {};
      try { data = JSON.parse(el.getAttribute('data-served') || '{}'); } catch (e) {}
      var S = stage(T, el, { fov: 36 }), scene = S.scene, red = reduced();
      var fitCam = function () { var a = (el.clientWidth || 1) / (el.clientHeight || 1), f = a < 1.15 ? Math.min(1.9, 1.15 / a) : 1; S.cam.position.set(0, -5.6 * f, 9.2 * f); S.cam.lookAt(0, -0.3, 0); }; fitCam(); addEventListener('resize', fitCam);
      scene.add(new T.AmbientLight(0xffffff, 0.75)); var dl = new T.DirectionalLight(0xffffff, 1.3); dl.position.set(-3, -4, 8); scene.add(dl);
      var world = new T.Group(); scene.add(world);
      var cx = -52, cy = -14.5, k = 0.19;
      function P(lng, lat) { return [(lng - cx) * k * Math.cos(-14 * Math.PI / 180), (lat - cy) * k]; }
      var meshes = [];
      Object.keys(GEO).forEach(function (uf) {
        var served = !!data[uf], grp = new T.Group(); grp.userData = { uf: uf, name: GEO[uf].n, served: served, base: 0 };
        GEO[uf].p.forEach(function (ring) {
          var sh = new T.Shape(); ring.forEach(function (pt, i) { var q = P(pt[0], pt[1]); if (i) sh.lineTo(q[0], q[1]); else sh.moveTo(q[0], q[1]); });
          var geo = new T.ExtrudeGeometry(sh, { depth: served ? 0.32 : 0.12, bevelEnabled: false });
          var m = new T.Mesh(geo, new T.MeshStandardMaterial({ color: served ? 0xe9b947 : 0x6b675e, metalness: served ? 0.35 : 0.1, roughness: served ? 0.35 : 0.8, emissive: served ? 0x3a2a05 : 0x000000, emissiveIntensity: 0.35 }));
          m.userData.grp = grp; grp.add(m); meshes.push(m);
          var edges = new T.LineSegments(new T.EdgesGeometry(geo, 30), new T.LineBasicMaterial({ color: served ? 0xfff1c8 : 0x9a958a, transparent: true, opacity: served ? 0.6 : 0.35 })); grp.add(edges);
        });
        world.add(grp);
      });
      // pinos de clientes
      var pins = [], pinTex = dotTexture(T, 'rgba(255,255,255,1)', 'rgba(255,215,120,.95)');
      Object.keys(data).forEach(function (uf) {
        (data[uf].pins || []).forEach(function (pn) {
          var q = P(pn[1], pn[0]);
          var stick = new T.Mesh(new T.CylinderGeometry(0.012, 0.012, 0.5, 6), new T.MeshBasicMaterial({ color: 0xffffff })); stick.rotation.x = Math.PI / 2; stick.position.set(q[0], q[1], 0.57); world.add(stick);
          var sp = new T.Sprite(new T.SpriteMaterial({ map: pinTex, transparent: true, depthWrite: false, blending: T.AdditiveBlending })); sp.scale.set(0.3, 0.3, 1); sp.position.set(q[0], q[1], 0.85); world.add(sp);
          var ring = new T.Mesh(new T.RingGeometry(0.06, 0.08, 32), new T.MeshBasicMaterial({ color: 0xfff1c8, transparent: true, side: T.DoubleSide })); ring.position.set(q[0], q[1], 0.34); world.add(ring);
          pins.push(ring);
        });
      });
      var tip = d.createElement('div'); tip.className = 'tx-tip'; tip.setAttribute('role', 'status'); el.appendChild(tip);
      var ray = new T.Raycaster(), mv = new T.Vector2(), hovered = null;
      S.run(function (dt, t, st) {
        var ty = st.mx * 0.25 + st.dragX * 0.8 + (red ? 0 : Math.sin(t * 0.3) * 0.08), tx = st.my * 0.12 + st.dragY * 0.5;
        world.rotation.z += (ty - world.rotation.z) * 0.06; world.rotation.x += (tx - world.rotation.x) * 0.06;
        pins.forEach(function (r, i) { var ph = ((t * 0.7 + i * 0.13) % 1); var s = 1 + ph * 3; r.scale.set(s, s, s); r.material.opacity = 1 - ph; });
        if (st.hover) {
          mv.set(st.mx, -st.my); ray.setFromCamera(mv, S.cam);
          var hit = ray.intersectObjects(meshes, false)[0], g = hit ? hit.object.userData.grp : null;
          if (g !== hovered) { hovered = g; }
          if (g) {
            var info = data[g.userData.uf];
            tip.innerHTML = '<b>' + esc(g.userData.name) + ' (' + g.userData.uf + ')</b>' + (info ? '<br>' + esc((info.clients || []).join(' • ')) : '<br><span style="opacity:.75">Atendimento sob demanda</span>');
            tip.style.left = st.px + 'px'; tip.style.top = st.py + 'px'; tip.classList.add('is-on');
          } else tip.classList.remove('is-on');
        } else tip.classList.remove('is-on');
        world.children.forEach(function (c) { if (c.userData && c.userData.uf) { var target = c === hovered ? 0.18 : 0; c.position.z += (target - c.position.z) * 0.15; } });
      });
    }
  };

  /* ------------------------------------------------------------------
     Diversos
  ------------------------------------------------------------------ */
  function initFilter() {
    $$('[data-tx-filter]').forEach(function (inp) {
      var box = $(inp.getAttribute('data-tx-filter')); if (!box) return;
      var items = $$('[data-tx-filter-item]', box), out = $(inp.getAttribute('data-tx-filter-count') || '#none');
      var norm = function (t) { return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); };
      inp.addEventListener('input', function () {
        var q = norm(inp.value.trim()), n = 0;
        items.forEach(function (it) { var on = !q || norm(it.textContent).indexOf(q) > -1; it.hidden = !on; if (on) n++; });
        if (out) out.textContent = n + (n === 1 ? ' termo' : ' termos');
      });
    });
  }
  function initTables() {
    $$('.tx table').forEach(function (t) {
      var heads = $$('thead th', t).map(function (th) { return th.textContent.trim(); });
      if (!heads.length) return;
      t.classList.add('tx-rtable');
      $$('tbody tr', t).forEach(function (tr) { $$('td,th', tr).forEach(function (td, i) { if (heads[i]) td.setAttribute('data-label', heads[i]); }); });
    });
  }
  function initMisc() {
    $$('[data-year]').forEach(function (e) { e.textContent = new Date().getFullYear(); });
    // links externos seguros
    $$('.tx a[target="_blank"]').forEach(function (a) { var r = (a.getAttribute('rel') || '').split(' '); if (r.indexOf('noopener') < 0) r.push('noopener'); a.setAttribute('rel', r.join(' ').trim()); });
    // copiar texto
    $$('[data-copy-text]').forEach(function (b) { b.addEventListener('click', function () { try { navigator.clipboard.writeText(b.getAttribute('data-copy-text')); var o = b.innerHTML; b.textContent = 'Copiado!'; setTimeout(function () { b.innerHTML = o; }, 1600); } catch (e) {} }); });
  }

  onReady(function () {
    var run = function (f) { try { f(); } catch (e) { if (window.console) console.warn(e); } };
    [initHeader, initReveal, initCounters, initTilt, initFlip, initRing, initTabs, initToc, initFilter, initTables, initFab, initMaps, function () { Consent.init(); }, initA11y, initForms, initSEO, init3D, initMisc].forEach(run);
    // VLibras: carrega após o load para não competir com o conteúdo
    var vl = function () { setTimeout(function () { loadVLibras(); }, 1200); };
    if (d.readyState === 'complete') vl(); else addEventListener('load', vl);
  });
})();
