/* =====================================================================
   CoreCyber · núcleo do site
   Idiomas, acessibilidade, VLibras, cookies, contato, mascote Cy, assistente,
   preços, simulador, verificação de DNS, formulários, protótipos e efeitos.
   Comportamentos usam delegação de eventos: sobrevivem à troca de idioma.
   ===================================================================== */
(function () {
  'use strict';
  if (window.CC && window.CC.ready) return;
  var d = document, w = window, html = d.documentElement, body = d.body;
  html.classList.add('cc-js');

  /* ------------------------------------------------------------ utils */
  function qs(s, r) { return (r || d).querySelector(s); }
  function qsa(s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); }
  function on(el, ev, fn, opt) { if (el) el.addEventListener(ev, fn, opt || false); }
  function store(k, v) {
    try {
      if (v === undefined) return JSON.parse(localStorage.getItem(k));
      if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v));
    } catch (e) { return null; }
  }
  function b64dec(s) { return new TextDecoder('utf-8').decode(Uint8Array.from(atob(String(s).trim()), function (c) { return c.charCodeAt(0); })); }
  function readJSON(el) { try { return JSON.parse(b64dec(el.textContent)); } catch (e) { return null; } }
  /* blocos com data-z="1" vêm em gzip + base64 */
  function unzipText(el) {
    if (!el.hasAttribute('data-z')) return Promise.resolve(b64dec(el.textContent));
    var u = Uint8Array.from(atob(el.textContent.trim()), function (c) { return c.charCodeAt(0); });
    return new Response(new Blob([u]).stream().pipeThrough(new DecompressionStream('gzip'))).text();
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function icon(n, cls) { return '<svg class="ico' + (cls ? ' ' + cls : '') + '" aria-hidden="true" focusable="false"><use href="#i-' + n + '"></use></svg>'; }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function fold(s) { return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function uid() { return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); }); }
  var mqReduce = w.matchMedia ? w.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  function motionOff() { return mqReduce.matches || html.classList.contains('a11y-motion'); }
  var finePointer = w.matchMedia && w.matchMedia('(hover: hover) and (pointer: fine)').matches;

  var CFG = readJSON(qs('#cc-cfg')) || {};
  var CO = CFG.company || {};
  var CC = w.CC = { ready: true, cfg: CFG, lang: 'pt', t: t, icon: icon, toast: toast, motionOff: motionOff, on: on, num: function (v) { return num(v); } };

  /* ------------------------------------------------------------ textos */
  function t(key, vars) {
    var dict = (w.CC_UI && w.CC_UI[CC.lang]) || {};
    var s = dict[key];
    if (s == null) s = (w.CC_UI && w.CC_UI.pt[key]) || key;
    if (vars) Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(vars[k]); });
    return s;
  }
  function waUrl(text) { return 'https://wa.me/' + (CO.whatsapp || '') + '?text=' + encodeURIComponent(text || t('wa.default')); }
  function money(v, dec) {
    var loc = CC.lang === 'en' ? 'en-US' : (CC.lang === 'es' ? 'es-ES' : 'pt-BR');
    return new Intl.NumberFormat(loc, { style: 'currency', currency: 'BRL', minimumFractionDigits: dec ? 2 : 0, maximumFractionDigits: dec ? 2 : 0 }).format(v);
  }
  function num(v) { return new Intl.NumberFormat(CC.lang === 'en' ? 'en-US' : (CC.lang === 'es' ? 'es-ES' : 'pt-BR')).format(v); }
  /* WordPress com links simples (?page_id=): /slug/?a#x -> /?pagename=slug&a#x */
  var WPC = CFG.wp || {}, SLUGS = WPC.slugs || [];
  function fixHref(url) {
    if (!WPC.plain) return url;
    var m = /^\/([a-z0-9-]+)\/(\?[^#]*)?(#.*)?$/.exec(url);
    if (!m || SLUGS.indexOf(m[1]) < 0) return url;
    return '/?pagename=' + m[1] + (m[2] ? '&' + m[2].slice(1) : '') + (m[3] || '');
  }
  CC.fixHref = fixHref;
  /* converte [texto](url) em link seguro; resto é escapado */
  function richText(s) {
    var out = esc(s).replace(/\{wa\}/g, esc(waUrl()));
    return out.replace(/\[([^\]]+)\]\(((?:https?:\/\/|\/|mailto:)[^\s)]+)\)/g, function (m, txt, url) {
      var ext = /^https?:/.test(url);
      return '<a href="' + fixHref(url) + '"' + (ext ? ' target="_blank" rel="noopener"' : '') + '>' + txt + '</a>';
    });
  }
  /* rede de segurança para links internos inseridos depois (traduções antigas, widgets) */
  on(d, 'click', function (e) {
    if (!WPC.plain || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
    var a = e.target.closest && e.target.closest('a[href^="/"]'); if (!a) return;
    var h = a.getAttribute('href'), f = fixHref(h);
    if (f !== h) { e.preventDefault(); location.href = f; }
  });

  /* ------------------------------------------------------------ aviso rápido */
  var toastEl, toastTimer;
  function toast(msg, ms, actions) {
    if (!toastEl) {
      toastEl = d.createElement('div');
      toastEl.className = 'ccw cc-toast';
      toastEl.setAttribute('role', 'status');
      toastEl.setAttribute('aria-live', 'polite');
      body.appendChild(toastEl);
    }
    toastEl.innerHTML = '<span>' + msg + '</span>';
    toastEl.style.pointerEvents = actions ? 'auto' : 'none';
    if (actions) {
      var box = d.createElement('span');
      box.style.cssText = 'display:inline-flex;gap:8px;margin-left:12px';
      actions.forEach(function (a) {
        var b = d.createElement('button');
        b.type = 'button'; b.textContent = a.label;
        b.style.cssText = 'border:1px solid rgba(255,255,255,.35);background:' + (a.primary ? '#fff' : 'transparent') + ';color:' + (a.primary ? '#0b1a3f' : '#fff') + ';border-radius:8px;padding:6px 10px;font:700 .82rem inherit;cursor:pointer';
        on(b, 'click', function () { toastEl.classList.remove('is-on'); a.fn(); });
        box.appendChild(b);
      });
      toastEl.appendChild(box);
    }
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, ms || 3200);
  }

  /* ------------------------------------------------------------ idiomas */
  var DICT = { en: {}, es: {} }, META = null;
  var dictReady = Promise.all(qsa('script.cc-i18n').map(function (s) {
    return unzipText(s).then(function (txt) {
      var o = JSON.parse(txt);
      ['en', 'es'].forEach(function (l) { if (o[l]) Object.assign(DICT[l], o[l]); });
      if (o._meta) META = o._meta;
    }).catch(function () { /* bloco inválido */ });
  }));
  var ptTitle = d.title, metaDesc = qs('meta[name="description"]'), ptDesc = metaDesc ? metaDesc.content : '';

  function applyLang(l, silent) {
    if (['pt', 'en', 'es'].indexOf(l) < 0) l = 'pt';
    CC.lang = l;
    html.lang = l === 'pt' ? 'pt-BR' : l;
    qsa('[data-t]').forEach(function (el) {
      if (el.__pt === undefined) el.__pt = el.innerHTML;
      var v = l === 'pt' ? el.__pt : DICT[l][el.getAttribute('data-t')];
      el.innerHTML = v != null ? v : el.__pt;
    });
    qsa('[data-ta]').forEach(function (el) {
      el.getAttribute('data-ta').split(';').forEach(function (p) {
        var i = p.indexOf(':'), a = p.slice(0, i), k = p.slice(i + 1), sk = '__pt_' + a;
        if (el[sk] === undefined) el[sk] = el.getAttribute(a);
        var v = l === 'pt' ? el[sk] : DICT[l][k];
        el.setAttribute(a, v != null ? v : el[sk]);
      });
    });
    if (META) {
      var tt = l === 'pt' ? null : DICT[l][META.title];
      d.title = tt ? tt + ' – CoreCyber' : ptTitle;
      if (metaDesc) metaDesc.content = (l !== 'pt' && DICT[l][META.desc]) || ptDesc;
    }
    qsa('[data-lang]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-lang') === l ? 'true' : 'false'); });
    var waDefaults = ['pt', 'en', 'es'].map(function (x) { return w.CC_UI && w.CC_UI[x] ? w.CC_UI[x]['wa.default'] : ''; });
    qsa('a[href^="https://wa.me/"]:not([data-wa-keep])').forEach(function (a) {
      try { var tx = new URL(a.href).searchParams.get('text'); if (!tx || waDefaults.indexOf(tx) >= 0) a.href = waUrl(); } catch (e) { /* link inválido */ }
    });
    qsa('.cc-skip').forEach(function (a) { a.textContent = t('skip'); });
    /* preços fixos (formatados em PT no build) seguem o formato do idioma */
    qsa('.price .val[data-noi18n]:not([data-price])').forEach(function (v) {
      if (v.__ptnum === undefined) v.__ptnum = parseInt(String(v.textContent).replace(/\D/g, ''), 10);
      if (!isNaN(v.__ptnum)) v.textContent = num(v.__ptnum);
    });
    if (!silent) store('cc_lang', l);
    d.dispatchEvent(new CustomEvent('cc:lang', { detail: l }));
  }
  on(d, 'click', function (e) {
    var b = e.target.closest('[data-lang]');
    if (!b || b.tagName !== 'BUTTON') return;
    applyLang(b.getAttribute('data-lang'));
    toast(t('lang.changed'));
  });
  dictReady.then(function initLang() {
    var p = new URLSearchParams(location.search).get('lang');
    var saved = store('cc_lang');
    var l = p || saved || 'pt';
    if (l !== 'pt') applyLang(l, !p); else CC.lang = 'pt';
    if (!p && !saved && !/bot|crawl|spider|lighthouse|headless/i.test(navigator.userAgent)) {
      var nav = fold(navigator.language || '');
      var sug = nav.indexOf('es') === 0 ? 'es' : (nav.indexOf('en') === 0 ? 'en' : null);
      if (sug) setTimeout(function () {
        var prev = CC.lang; CC.lang = sug;
        var msg = t('lang.suggest', { lang: t('lang.name.' + sug) }), sw = t('lang.switch');
        CC.lang = prev;
        toast(esc(msg), 12000, [{ label: sw, primary: true, fn: function () { applyLang(sug); } }, { label: t('lang.keep'), fn: function () { store('cc_lang', 'pt'); } }]);
      }, 2500);
    }
  });

  /* ------------------------------------------------------------ cabeçalho */
  var nav = qs('#cc-nav'), progress = qs('.cc-progress');
  function onScrollHeader() {
    var y = w.scrollY || 0;
    if (nav) nav.classList.toggle('is-stuck', y > 12);
    if (progress) {
      var h = d.documentElement.scrollHeight - w.innerHeight;
      progress.style.setProperty('--sp', h > 0 ? clamp(y / h, 0, 1) : 0);
    }
  }
  on(w, 'scroll', onScrollHeader, { passive: true });
  onScrollHeader();
  /* link ativo */
  (function () {
    var path = location.pathname.replace(/\/+$/, '/') || '/';
    var pn = new URLSearchParams(location.search).get('pagename');
    qsa('.cc-menu > ul > li > a, .cc-drawer a').forEach(function (a) {
      var h = a.getAttribute('href') || '';
      var hp = (/[?&]pagename=([a-z0-9-]+)/.exec(h) || [])[1];
      if (hp ? hp === pn : (h === path || (h.length > 1 && path.indexOf(h) === 0))) a.setAttribute('aria-current', 'page');
    });
  })();
  /* megamenu */
  var megaTimer;
  function closeMegas(except) {
    qsa('.has-mega.is-open').forEach(function (li) {
      if (li === except) return;
      li.classList.remove('is-open');
      var b = qs('button', li); if (b) b.setAttribute('aria-expanded', 'false');
    });
  }
  function openMega(li) {
    closeMegas(li); li.classList.add('is-open');
    var b = qs('button', li); if (b) b.setAttribute('aria-expanded', 'true');
  }
  qsa('.has-mega').forEach(function (li) {
    var b = qs('button', li);
    on(b, 'click', function () { li.classList.contains('is-open') ? closeMegas() : openMega(li); });
    if (finePointer) {
      on(li, 'mouseenter', function () { clearTimeout(megaTimer); megaTimer = setTimeout(function () { openMega(li); }, 90); });
      on(li, 'mouseleave', function () { clearTimeout(megaTimer); megaTimer = setTimeout(function () { closeMegas(); }, 220); });
    }
    on(li, 'focusout', function (e) { if (!li.contains(e.relatedTarget)) li.classList.remove('is-open'), b.setAttribute('aria-expanded', 'false'); });
  });
  on(d, 'click', function (e) { if (!e.target.closest('.has-mega')) closeMegas(); });
  /* gaveta mobile */
  var burger = qs('.cc-burger'), drawer = qs('#cc-drawer');
  function setDrawer(open) {
    if (!burger || !drawer) return;
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    drawer.hidden = !open;
    body.style.overflow = open ? 'hidden' : '';
    if (open) { var f = qs('a', drawer); if (f) f.focus(); }
  }
  on(burger, 'click', function () { setDrawer(burger.getAttribute('aria-expanded') !== 'true'); });
  on(drawer, 'click', function (e) { if (e.target.closest('a')) setDrawer(false); });

  /* ------------------------------------------------------------ teclado */
  on(d, 'keydown', function (e) {
    if (e.key === 'Tab') html.classList.add('cc-kbd');
    if (e.key === 'Escape') {
      closeMegas(); setDrawer(false); fabToggle(false); a11yPanel(false); chatOpen(false); closeModal();
    }
    if (e.altKey && !e.ctrlKey && !e.metaKey) {
      /* e.code funciona também no Mac, onde Option+1 gera outro caractere */
      var k = (e.code || '').replace(/^(Digit|Numpad)/, '') || e.key;
      var map = { '1': '#cc-content', '2': '#cc-menu', '3': '#cc-footer' };
      if (map[k]) {
        var el = qs(map[k]);
        if (el) { e.preventDefault(); el.setAttribute('tabindex', '-1'); el.focus(); el.scrollIntoView({ behavior: motionOff() ? 'auto' : 'smooth', block: 'start' }); }
      }
      if (k === '4') { e.preventDefault(); a11yPanel(true); }
    }
  });
  on(d, 'mousedown', function () { html.classList.remove('cc-kbd'); });

  /* ------------------------------------------------------------ acessibilidade */
  var A11Y = store('cc_a11y') || {};
  var A11Y_KEYS = ['contrast', 'gray', 'links', 'font', 'spacing', 'guide', 'cursor', 'motion', 'noimg'];
  var fontLoaded = false;
  function a11yApply() {
    html.style.setProperty('--a11y-scale', A11Y.scale || 1);
    A11Y_KEYS.forEach(function (k) { html.classList.toggle('a11y-' + k, !!A11Y[k]); });
    if (A11Y.font && !fontLoaded) {
      fontLoaded = true;
      var l = d.createElement('link'); l.rel = 'stylesheet';
      l.href = 'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap';
      d.head.appendChild(l);
    }
    qsa('.cc-panel [data-opt]').forEach(function (b) { b.setAttribute('aria-pressed', A11Y[b.getAttribute('data-opt')] ? 'true' : 'false'); });
    var out = qs('.cc-panel output'); if (out) out.textContent = Math.round((A11Y.scale || 1) * 100) + '%';
    store('cc_a11y', A11Y);
    d.dispatchEvent(new CustomEvent('cc:a11y'));
  }
  function a11yScale(dir) {
    var s = Math.round(((A11Y.scale || 1) + dir * 0.1) * 10) / 10;
    A11Y.scale = clamp(s, 0.9, 1.6); a11yApply();
  }
  on(d, 'click', function (e) {
    var q = e.target.closest('[data-a11y]');
    if (!q) return;
    var a = q.getAttribute('data-a11y');
    if (a === 'font-up') a11yScale(1);
    else if (a === 'font-down') a11yScale(-1);
    else if (a === 'contrast') { A11Y.contrast = !A11Y.contrast; a11yApply(); }
  });
  on(d, 'click', function (e) { if (e.target.closest('[data-a11y-open]')) a11yPanel(true); });
  var panel, scrim, reading = false;
  function buildPanel() {
    if (panel) return panel;
    scrim = d.createElement('div'); scrim.className = 'cc-scrim'; body.appendChild(scrim);
    on(scrim, 'click', function () { a11yPanel(false); chatOpen(false); });
    panel = d.createElement('div');
    panel.className = 'ccw cc-panel'; panel.id = 'cc-a11y-panel';
    panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); panel.setAttribute('aria-labelledby', 'cc-a11y-h');
    body.appendChild(panel);
    renderPanel();
    on(panel, 'click', function (e) {
      var o = e.target.closest('[data-opt]');
      if (o) { var k = o.getAttribute('data-opt'); A11Y[k] = !A11Y[k]; a11yApply(); return; }
      var act = e.target.closest('[data-act]');
      if (!act) return;
      var a = act.getAttribute('data-act');
      if (a === 'close') a11yPanel(false);
      if (a === 'smaller') a11yScale(-1);
      if (a === 'bigger') a11yScale(1);
      if (a === 'reset') { A11Y = {}; a11yApply(); stopReading(); }
      if (a === 'read') toggleReading(act);
      if (a === 'libras') openLibras();
    });
    return panel;
  }
  function renderPanel() {
    if (!panel) return;
    function opt(k, ic) { return '<button type="button" class="opt" data-opt="' + k + '" aria-pressed="' + (A11Y[k] ? 'true' : 'false') + '">' + icon(ic) + '<span>' + t('a11y.' + k) + '</span></button>'; }
    panel.innerHTML =
      '<div class="pn-head"><h2 id="cc-a11y-h">' + icon('access') + t('a11y.title') + '</h2><button type="button" class="pn-x" data-act="close" aria-label="' + esc(t('a11y.close')) + '">' + icon('x') + '</button></div>' +
      '<div class="pn-body">' +
      '<div class="pn-sec"><h3>' + t('a11y.text') + '</h3><div class="scale" role="group" aria-label="' + esc(t('a11y.size')) + '"><button type="button" data-act="smaller" aria-label="' + esc(t('a11y.smaller')) + '">A−</button><output aria-live="polite">' + Math.round((A11Y.scale || 1) * 100) + '%</output><button type="button" data-act="bigger" aria-label="' + esc(t('a11y.bigger')) + '">A+</button></div>' +
      '<div class="pn-grid" style="margin-top:10px">' + opt('font', 'type') + opt('spacing', 'sliders') + '</div></div>' +
      '<div class="pn-sec"><h3>' + t('a11y.colors') + '</h3><div class="pn-grid">' + opt('contrast', 'contrast') + opt('gray', 'eye') + opt('links', 'link') + opt('noimg', 'eye-off') + '</div></div>' +
      '<div class="pn-sec"><h3>' + t('a11y.reading') + '</h3><div class="pn-grid">' + opt('guide', 'minus') + opt('cursor', 'mouse') +
      '<button type="button" class="opt" data-act="read" aria-pressed="' + (reading ? 'true' : 'false') + '">' + icon('volume') + '<span>' + t(reading ? 'a11y.stop' : 'a11y.read') + '</span></button>' +
      '<button type="button" class="opt" data-act="libras">' + icon('hand') + '<span>' + t('a11y.libras') + '</span></button></div></div>' +
      '<div class="pn-sec"><h3>' + t('a11y.motion.h') + '</h3><div class="pn-grid">' + opt('motion', 'pause') + '</div></div>' +
      '<div class="pn-sec"><h3>' + t('a11y.keys') + '</h3><div class="kbd-list"><span><kbd>Alt</kbd> + <kbd>1</kbd> ' + t('a11y.k1') + '</span><span><kbd>Alt</kbd> + <kbd>2</kbd> ' + t('a11y.k2') + '</span><span><kbd>Alt</kbd> + <kbd>3</kbd> ' + t('a11y.k3') + '</span><span><kbd>Alt</kbd> + <kbd>4</kbd> ' + t('a11y.k4') + '</span></div></div>' +
      '</div>' +
      '<div class="pn-foot"><button type="button" data-act="reset">' + t('a11y.reset') + '</button><button type="button" data-cookie-open>' + t('dock.cookie') + '</button><a href="' + fixHref('/acessibilidade/') + '">' + t('a11y.statement') + '</a></div>';
  }
  function a11yPanel(open) {
    if (open) buildPanel();
    if (!panel) return;
    panel.classList.toggle('is-open', !!open);
    scrim.classList.toggle('is-on', !!open);
    var dockBtn = qs('.cc-dock [data-dock="a11y"]'); if (dockBtn) dockBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) setTimeout(function () { var f = qs('.pn-x', panel); if (f) f.focus(); }, 60);
  }
  /* leitura em voz alta */
  function stopReading() { if (w.speechSynthesis) w.speechSynthesis.cancel(); reading = false; renderPanel(); }
  function toggleReading() {
    if (!w.speechSynthesis) { toast(t('a11y.noread')); return; }
    if (reading) { stopReading(); return; }
    var main = qs('#cc-content') || body;
    var sel = String(w.getSelection ? w.getSelection() : '').trim();
    var text = sel || main.innerText.replace(/\s+\n/g, '\n').slice(0, 12000);
    var parts = text.match(/[^.!?\n]{1,220}[.!?\n]?/g) || [text];
    var langCode = CC.lang === 'pt' ? 'pt-BR' : (CC.lang === 'en' ? 'en-US' : 'es-ES');
    var voice = (w.speechSynthesis.getVoices() || []).filter(function (v) { return v.lang && v.lang.indexOf(langCode.slice(0, 2)) === 0; })[0];
    reading = true; renderPanel();
    parts.forEach(function (p, i) {
      var u = new SpeechSynthesisUtterance(p.trim());
      u.lang = langCode; if (voice) u.voice = voice; u.rate = 1;
      if (i === parts.length - 1) u.onend = function () { reading = false; renderPanel(); };
      w.speechSynthesis.speak(u);
    });
  }
  /* régua de leitura */
  var guide = d.createElement('div'); guide.className = 'cc-guide'; guide.setAttribute('aria-hidden', 'true'); body.appendChild(guide);
  on(d, 'pointermove', function (e) { if (html.classList.contains('a11y-guide')) guide.style.top = (e.clientY - 22) + 'px'; }, { passive: true });

  /* ------------------------------------------------------------ VLibras */
  function loadVLibras() {
    if (w.VLibras || qs('script[data-vlibras]')) return;
    var s = d.createElement('script');
    s.src = 'https://vlibras.gov.br/app/vlibras-plugin.js';
    s.async = true; s.setAttribute('data-vlibras', '');
    s.onload = function () { try { new w.VLibras.Widget('https://vlibras.gov.br/app'); } catch (e) { /* widget indisponível */ } };
    body.appendChild(s);
  }
  function openLibras() {
    loadVLibras();
    var tries = 0;
    (function go() {
      if (w.VLibrasWidget && typeof w.VLibrasWidget.open === 'function') { w.VLibrasWidget.open(); return; }
      if (tries++ < 40) setTimeout(go, 150);
    })();
  }
  if ('requestIdleCallback' in w) w.requestIdleCallback(loadVLibras, { timeout: 3500 }); else setTimeout(loadVLibras, 2500);

  /* ------------------------------------------------------------ doca lateral */
  var dock = d.createElement('div');
  dock.className = 'ccw cc-dock';
  dock.setAttribute('role', 'group');
  function renderDock() {
    dock.setAttribute('aria-label', t('dock.a11y'));
    dock.innerHTML =
      '<button type="button" data-dock="a11y" aria-expanded="false" aria-controls="cc-a11y-panel" aria-label="' + esc(t('dock.a11y')) + '">' + icon('access') + '<span class="tip">' + t('dock.a11y') + '</span></button>' +
      '<button type="button" data-dock="libras" aria-label="' + esc(t('dock.libras')) + '">' + icon('hand') + '<span class="tip">' + t('dock.libras') + '</span></button>' +
      '<button type="button" data-dock="cookie" aria-label="' + esc(t('dock.cookie')) + '">' + icon('cookie') + '<span class="tip">' + t('dock.cookie') + '</span></button>';
  }
  renderDock(); body.appendChild(dock);
  on(dock, 'click', function (e) {
    var b = e.target.closest('[data-dock]'); if (!b) return;
    var k = b.getAttribute('data-dock');
    if (k === 'a11y') a11yPanel(!(panel && panel.classList.contains('is-open')));
    if (k === 'libras') openLibras();
    if (k === 'cookie') openPrefs();
  });

  /* ------------------------------------------------------------ cookies (CMP) */
  var CK_VER = 1;
  var consent = store('cc_consent');
  if (consent && consent.v !== CK_VER) consent = null;
  var cookieBox, modal, lastFocus;
  var CK_ITEMS = {
    necessary: [['cc_consent', 'ck.i.consent', 'ck.12m'], ['cc_lang', 'ck.i.lang', 'ck.persist'], ['cc_a11y', 'ck.i.a11y', 'ck.persist'], ['cc_mascot', 'ck.i.mascot', 'ck.persist'], ['VLibras (vlibras.gov.br)', 'ck.i.vlibras', 'ck.third']],
    functional: [['Google Maps', 'ck.i.maps', 'ck.third'], ['FreeLLMAPI (CoreCyber)', 'ck.i.ai', 'ck.session']],
    analytics: [], marketing: []
  };
  function consentAllowed(cat) { return cat === 'necessary' || !!(consent && consent.cats && consent.cats[cat]); }
  CC.consentAllowed = consentAllowed;
  function saveConsent(cats) {
    consent = { v: CK_VER, id: (consent && consent.id) || uid(), ts: new Date().toISOString(), cats: Object.assign({ necessary: true }, cats) };
    store('cc_consent', consent);
    try {
      d.cookie = 'cc_consent=' + encodeURIComponent(JSON.stringify({ v: CK_VER, id: consent.id, f: +!!cats.functional, a: +!!cats.analytics, m: +!!cats.marketing })) +
        '; max-age=31536000; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    } catch (e) { /* cookies bloqueados */ }
    if (w.gtag) w.gtag('consent', 'update', { analytics_storage: cats.analytics ? 'granted' : 'denied', ad_storage: cats.marketing ? 'granted' : 'denied', ad_user_data: cats.marketing ? 'granted' : 'denied', ad_personalization: cats.marketing ? 'granted' : 'denied' });
    unblockScripts();
    hideBanner(); closeModal();
    toast(t('ck.saved'));
    d.dispatchEvent(new CustomEvent('cc:consent', { detail: consent }));
  }
  function unblockScripts() {
    qsa('script[type="text/plain"][data-consent]').forEach(function (s) {
      if (!consentAllowed(s.getAttribute('data-consent'))) return;
      var n = d.createElement('script');
      Array.prototype.forEach.call(s.attributes, function (a) { if (a.name !== 'type' && a.name !== 'data-consent') n.setAttribute(a.name, a.value); });
      n.text = s.text; s.parentNode.replaceChild(n, s);
    });
    qsa('[data-map]').forEach(function (m) { if (consentAllowed('functional')) loadMap(m); });
  }
  function renderBanner() {
    cookieBox.innerHTML = '<h2 id="cc-ck-h">' + icon('cookie') + t('ck.title') + '</h2>' +
      '<p id="cc-ck-d">' + esc(t('ck.text')) + ' <a href="' + fixHref('/politica-de-cookies/') + '">' + t('ck.policy') + '</a>.</p>' +
      '<div class="ck-actions"><button type="button" class="ckbtn" data-ck="reject">' + t('ck.reject') + '</button>' +
      '<button type="button" class="ckbtn ckbtn--solid" data-ck="accept">' + t('ck.accept') + '</button>' +
      '<button type="button" class="ckbtn ckbtn--link wide" data-ck="custom">' + t('ck.custom') + '</button></div>';
  }
  function showBanner() {
    if (!cookieBox) {
      cookieBox = d.createElement('section');
      cookieBox.className = 'ccw cc-cookie';
      cookieBox.setAttribute('role', 'region');
      cookieBox.setAttribute('aria-labelledby', 'cc-ck-h');
      cookieBox.setAttribute('aria-describedby', 'cc-ck-d');
      body.appendChild(cookieBox);
      on(cookieBox, 'click', function (e) {
        var b = e.target.closest('[data-ck]'); if (!b) return;
        var a = b.getAttribute('data-ck');
        if (a === 'accept') saveConsent({ functional: true, analytics: true, marketing: true });
        if (a === 'reject') saveConsent({ functional: false, analytics: false, marketing: false });
        if (a === 'custom') openPrefs();
      });
    }
    renderBanner();
    requestAnimationFrame(function () { cookieBox.classList.add('is-on'); });
  }
  function hideBanner() { if (cookieBox) cookieBox.classList.remove('is-on'); mascotShow(); }
  function catBlock(cat) {
    var items = CK_ITEMS[cat];
    var on_ = cat === 'necessary' ? true : (consent ? !!consent.cats[cat] : false);
    var ctl = cat === 'necessary' ? '<span class="always">' + t('ck.always') + '</span>' :
      '<span class="tgl"><input type="checkbox" id="ck-' + cat + '" data-cat="' + cat + '"' + (on_ ? ' checked' : '') + ' aria-describedby="ck-d-' + cat + '"><span></span></span>';
    var tbl = items.length ? '<table><thead><tr><th>' + t('ck.name') + '</th><th>' + t('ck.purpose') + '</th><th>' + t('ck.duration') + '</th></tr></thead><tbody>' +
      items.map(function (i) { return '<tr><td>' + esc(i[0]) + '</td><td>' + t(i[1]) + '</td><td>' + t(i[2]) + '</td></tr>'; }).join('') + '</tbody></table>' : '<p>' + t('ck.none') + '</p>';
    return '<div class="cat"><div class="cat-h"><b><label for="ck-' + cat + '">' + t('ck.c.' + cat) + '</label></b>' + ctl + '</div><p id="ck-d-' + cat + '">' + t('ck.d.' + cat) + '</p><details><summary>' + t('ck.details') + '</summary>' + tbl + '</details></div>';
  }
  function openPrefs() {
    lastFocus = d.activeElement;
    if (!modal) {
      modal = d.createElement('div');
      modal.className = 'ccw cc-modal'; modal.hidden = true;
      body.appendChild(modal);
      on(modal, 'click', function (e) {
        if (e.target === modal || e.target.closest('[data-md="close"]')) { closeModal(); return; }
        var b = e.target.closest('[data-ck]'); if (!b) return;
        var a = b.getAttribute('data-ck');
        if (a === 'accept') saveConsent({ functional: true, analytics: true, marketing: true });
        if (a === 'reject') saveConsent({ functional: false, analytics: false, marketing: false });
        if (a === 'save') {
          var c = {}; qsa('[data-cat]', modal).forEach(function (i) { c[i.getAttribute('data-cat')] = i.checked; });
          saveConsent(c);
        }
      });
      on(modal, 'keydown', function (e) {
        if (e.key !== 'Tab') return;
        var f = qsa('button, input, a, summary', modal).filter(function (x) { return x.offsetParent !== null; });
        if (!f.length) return;
        if (e.shiftKey && d.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && d.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      });
    }
    modal.innerHTML = '<div class="md-box" role="dialog" aria-modal="true" aria-labelledby="cc-md-h">' +
      '<div class="md-head"><h2 id="cc-md-h">' + t('ck.prefs') + '</h2><button type="button" class="md-x" data-md="close" aria-label="' + esc(t('ck.close')) + '">' + icon('x') + '</button></div>' +
      '<div class="md-body"><p>' + esc(t('ck.intro')) + ' <a href="' + fixHref('/politica-de-cookies/') + '">' + t('ck.policy') + '</a>.</p>' +
      ['necessary', 'functional', 'analytics', 'marketing'].map(catBlock).join('') +
      (consent ? '<p class="meta">' + t('ck.id') + ': <code>' + esc(consent.id) + '</code> · ' + t('ck.date') + ': ' + esc(new Date(consent.ts).toLocaleString(html.lang)) + '</p>' : '') +
      '</div><div class="md-foot"><button type="button" class="ckbtn" data-ck="reject">' + t('ck.reject') + '</button><button type="button" class="ckbtn" data-ck="save">' + t('ck.save') + '</button><button type="button" class="ckbtn ckbtn--solid" data-ck="accept">' + t('ck.accept') + '</button></div></div>';
    modal.hidden = false;
    setTimeout(function () { var x = qs('.md-x', modal); if (x) x.focus(); }, 30);
  }
  function closeModal() { if (modal && !modal.hidden) { modal.hidden = true; if (lastFocus && lastFocus.focus) lastFocus.focus(); } }
  CC.openPrefs = openPrefs;
  on(d, 'click', function (e) { if (e.target.closest('[data-cookie-open]')) { e.preventDefault(); a11yPanel(false); openPrefs(); } });
  if (!consent) setTimeout(showBanner, 700); else unblockScripts();

  /* ------------------------------------------------------------ mapa (com consentimento) */
  function loadMap(m) {
    if (m.__loaded) return; m.__loaded = true;
    var f = d.createElement('iframe');
    f.src = m.getAttribute('data-src'); f.loading = 'lazy'; f.referrerPolicy = 'no-referrer-when-downgrade';
    f.title = m.getAttribute('data-title') || 'Mapa';
    f.style.cssText = 'border:0;width:100%;height:100%;min-height:320px;border-radius:16px;display:block';
    m.innerHTML = ''; m.appendChild(f);
  }
  on(d, 'click', function (e) { var b = e.target.closest('[data-map-load]'); if (b) { var m = b.closest('[data-map]'); if (m) loadMap(m); } });

  /* ------------------------------------------------------------ FAB de contato */
  var fab = d.createElement('div');
  fab.className = 'ccw cc-fab';
  var FAB_ITEMS = [
    ['wa', 'whatsapp', function () { return waUrl(); }, true, 'b--wa'],
    ['ai', 'bot', null, false, 'b--ai'],
    ['mail', 'mail', function () { return 'mailto:' + CO.email_sales; }, false, 'b--mail'],
    ['tel', 'phone', function () { return 'tel:' + CO.phone_e164; }, false, 'b--tel'],
    ['ctt', 'message', function () { return fixHref('/contato/'); }, false, 'b--ctt'],
    ['map', 'pin', function () { return CO.maps_url; }, true, 'b--map'],
    ['ig', 'instagram', function () { return CO.instagram; }, true, 'b--ig'],
    ['li', 'linkedin', function () { return CO.linkedin; }, true, 'b--li'],
    ['fb', 'facebook', function () { return CO.facebook; }, true, 'b--fb']
  ];
  function renderFab() {
    var open = fab.classList.contains('is-open');
    var items = FAB_ITEMS.map(function (it, i) {
      var inner = '<span class="t">' + t('fab.' + it[0]) + '</span><span class="b ' + it[4] + '">' + icon(it[1], it[0] === 'wa' ? 'ico--fill' : '') + '</span>';
      if (!it[2]) return '<button type="button" class="fab-it" style="--i:' + (FAB_ITEMS.length - i) + ';border:0;background:none;padding:0;cursor:pointer" data-fab-ai>' + inner + '</button>';
      var ext = it[3] ? ' target="_blank" rel="noopener"' : '';
      return '<a class="fab-it" style="--i:' + (FAB_ITEMS.length - i) + '" href="' + esc(it[2]()) + '"' + ext + '>' + inner + '</a>';
    }).join('');
    fab.innerHTML = '<div class="fab-items" id="cc-fab-items"' + (open ? '' : ' hidden') + '>' + items + '</div>' +
      '<button type="button" class="fab-toggle" aria-expanded="' + open + '" aria-controls="cc-fab-items" aria-label="' + esc(t(open ? 'fab.close' : 'fab.open')) + '">' +
      icon('whatsapp', 'ico--fill i-open') + icon('x', 'i-close') + '<span class="fab-label" aria-hidden="true">' + t('fab.label') + '</span></button>';
  }
  function fabToggle(open) {
    if (open === fab.classList.contains('is-open')) return;
    fab.classList.toggle('is-open', !!open);
    renderFab();
    if (open) { var f = qs('.fab-it', fab); if (f) f.focus(); }
  }
  renderFab(); body.appendChild(fab);
  setTimeout(function () { fab.classList.add('label-off'); }, 7000);
  on(fab, 'click', function (e) {
    e.stopPropagation(); /* o redesenho remove o alvo do DOM: não deixar o "clique fora" fechar */
    if (e.target.closest('.fab-toggle')) { fabToggle(!fab.classList.contains('is-open')); return; }
    if (e.target.closest('[data-fab-ai]')) { fabToggle(false); chatOpen(true); }
  });
  on(d, 'click', function (e) { if (!e.target.closest('.cc-fab') && fab.classList.contains('is-open')) fabToggle(false); });
  var totop = d.createElement('button');
  totop.type = 'button'; totop.className = 'ccw cc-totop';
  totop.innerHTML = icon('arrow-up');
  totop.setAttribute('aria-label', t('totop'));
  body.appendChild(totop);
  on(totop, 'click', function () { w.scrollTo({ top: 0, behavior: motionOff() ? 'auto' : 'smooth' }); });
  on(w, 'scroll', function () { totop.classList.toggle('is-on', w.scrollY > 900); }, { passive: true });

  /* ------------------------------------------------------------ mascote Cy (sentinela do CoreCyber) */
  var MASCOT_SVG =
    '<svg viewBox="0 0 100 120" aria-hidden="true" focusable="false"><defs>' +
    '<linearGradient id="cyBody" x1="1" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#19c3d6"/><stop offset=".45" stop-color="#0a6a96"/><stop offset="1" stop-color="#002141"/></linearGradient>' +
    '<radialGradient id="cyOrb" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#b8f6fb"/><stop offset=".5" stop-color="#19c3d6"/><stop offset="1" stop-color="#005783"/></radialGradient>' +
    '<linearGradient id="cyVisor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0b2f52"/><stop offset="1" stop-color="#04192f"/></linearGradient></defs>' +
    '<ellipse class="m-shadow" cx="50" cy="114" rx="24" ry="4.5" fill="rgba(0,33,65,.16)"/>' +
    '<g class="m-bob"><g class="m-rig">' +
    '<g class="m-leg m-leg-l"><rect x="35" y="94" width="9" height="14" rx="4.5" fill="#002141"/><ellipse cx="38.5" cy="109.5" rx="7" ry="3.6" fill="#002141"/></g>' +
    '<g class="m-leg m-leg-r"><rect x="56" y="94" width="9" height="14" rx="4.5" fill="#002141"/><ellipse cx="61.5" cy="109.5" rx="7" ry="3.6" fill="#002141"/></g>' +
    '<g class="m-arm m-arm-l"><path d="M20 64 C14 68 11 74 10 80" stroke="#002141" stroke-width="6" stroke-linecap="round" fill="none"/><circle cx="10" cy="81" r="4.8" fill="#19c3d6"/></g>' +
    '<g class="m-arm m-arm-r"><path d="M80 64 C86 68 89 74 90 80" stroke="#002141" stroke-width="6" stroke-linecap="round" fill="none"/><circle cx="90" cy="81" r="4.8" fill="#19c3d6"/></g>' +
    '<path d="M50 27 V15" stroke="#002141" stroke-width="3" stroke-linecap="round"/>' +
    '<path class="m-sig" d="M41 8 A12 12 0 0 1 59 8" stroke="#19c3d6" stroke-width="1.8" fill="none" stroke-linecap="round"/>' +
    '<circle class="m-orb" cx="50" cy="12" r="5.6" fill="url(#cyOrb)"/>' +
    '<path d="M31 26 H69 L82 39 V85 L69 98 H31 L18 85 V39 Z" fill="url(#cyBody)"/>' +
    '<path d="M31 26 H53 L18 61 V39 Z" fill="rgba(255,255,255,.10)"/>' +
    '<rect x="25" y="43" width="50" height="31" rx="13" fill="url(#cyVisor)" stroke="rgba(25,195,214,.6)" stroke-width="1.5"/>' +
    '<g class="m-eyes"><circle cx="39" cy="58" r="8" fill="#0b3a5e"/><circle cx="61" cy="58" r="8" fill="#0b3a5e"/>' +
    '<circle class="m-ring" cx="39" cy="58" r="6.6" fill="none" stroke="#19c3d6" stroke-width="1.6" stroke-dasharray="31 10"/><circle class="m-ring" cx="61" cy="58" r="6.6" fill="none" stroke="#19c3d6" stroke-width="1.6" stroke-dasharray="31 10"/>' +
    '<g class="m-pupils"><circle cx="39" cy="58" r="3.5" fill="#8ff2f9"/><circle cx="61" cy="58" r="3.5" fill="#8ff2f9"/><circle cx="40.2" cy="56.8" r="1.1" fill="#fff"/><circle cx="62.2" cy="56.8" r="1.1" fill="#fff"/></g>' +
    '<rect class="m-lid" x="30" y="49" width="18" height="18" rx="8" fill="#062240"/><rect class="m-lid" x="52" y="49" width="18" height="18" rx="8" fill="#062240"/></g>' +
    '<path class="m-mouth" d="M44 68 Q50 72 56 68" stroke="#19c3d6" stroke-width="2.4" stroke-linecap="round" fill="none"/>' +
    '<ellipse class="m-mouth-o" cx="50" cy="69.5" rx="3" ry="3.2" fill="#19c3d6"/>' +
    '<path d="M50 78.5 l7 2.6 v4.4 c0 4.2-3 7-7 8-4-1-7-3.8-7-8 v-4.4z" fill="rgba(255,255,255,.14)" stroke="#9ff3fa" stroke-width="1.2"/>' +
    '<path d="M46.8 85.6l2.2 2.2 4.2-4.4" stroke="#9ff3fa" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>' +
    '<path class="m-sweat" d="M89 33 q4 6 0 9 q-4 -3 0 -9z" fill="#7fd0ff"/>' +
    '<g class="m-zzz" fill="#50637b" font-family="Arial" font-weight="700"><text x="78" y="24" font-size="9">z</text><text x="85" y="15" font-size="7">z</text></g>' +
    '<g class="m-stars" fill="#19c3d6"><path d="M8 40l2 4 4 2-4 2-2 4-2-4-4-2 4-2z"/><path d="M92 44l1.5 3 3 1.5-3 1.5-1.5 3-1.5-3-3-1.5 3-1.5z"/></g>' +
    '</g></g></svg>';
  var mascot = null, mBubble, mMin, mState = '', mStateTimer, mBubbleTimer, mSaid = {}, mIdleTimer, mHidden = !!store('cc_mascot');
  function buildMascot() {
    if (mascot) return;
    mascot = d.createElement('div');
    mascot.className = 'ccw cc-mascot is-hidden';
    mascot.innerHTML = MASCOT_SVG + '<div class="m-bubble" role="status" aria-live="polite"></div>' +
      '<button type="button" class="m-hit" aria-label="' + esc(t('m.ask')) + '"></button>' +
      '<button type="button" class="m-close" aria-label="' + esc(t('m.hide')) + '">' + icon('x') + '</button>';
    body.appendChild(mascot);
    mBubble = qs('.m-bubble', mascot);
    mMin = d.createElement('button');
    mMin.type = 'button'; mMin.className = 'ccw cc-mascot-min'; mMin.hidden = true;
    mMin.setAttribute('aria-label', t('m.show'));
    mMin.innerHTML = MASCOT_SVG.replace('viewBox="0 0 100 120"', 'viewBox="8 2 92 100"');
    body.appendChild(mMin);
    on(qs('.m-hit', mascot), 'click', function () { mWake(); chatOpen(true); });
    on(qs('.m-close', mascot), 'click', function () { mHidden = true; store('cc_mascot', 1); mascot.classList.add('is-hidden'); mMin.hidden = false; say(''); });
    on(mMin, 'click', function () { mHidden = false; store('cc_mascot', null); mMin.hidden = true; mascot.classList.remove('is-hidden'); mSet('st-wave', 2200); });
  }
  function mascotShow() {
    if (!consent) return;
    buildMascot();
    if (mHidden) { mMin.hidden = false; return; }
    setTimeout(function () {
      mascot.classList.remove('is-hidden');
      if (!sessionStorage.getItem('cc_hello')) {
        try { sessionStorage.setItem('cc_hello', '1'); } catch (e) { /* ok */ }
        mSet('st-wave', 2400); say(t('m.hello'), 5200);
      }
    }, 900);
  }
  function mSet(st, ms) {
    if (!mascot) return;
    mascot.classList.remove('st-down', 'st-up', 'st-run', 'st-fast', 'st-wave', 'st-joy', 'st-sleep', 'st-think');
    st.split(' ').forEach(function (c) { if (c) mascot.classList.add(c); });
    mState = st;
    clearTimeout(mStateTimer);
    if (ms) mStateTimer = setTimeout(function () { mSet(''); }, ms);
  }
  function say(text, ms) {
    if (!mBubble || mHidden) return;
    clearTimeout(mBubbleTimer);
    if (!text) { mBubble.classList.remove('is-on'); return; }
    mBubble.innerHTML = richText(text);
    mBubble.classList.add('is-on');
    mBubbleTimer = setTimeout(function () { mBubble.classList.remove('is-on'); }, ms || 4600);
  }
  function mWake() { if (mState.indexOf('st-sleep') >= 0) { mSet('st-joy', 1600); say(''); } resetIdle(); }
  function resetIdle() {
    clearTimeout(mIdleTimer);
    mIdleTimer = setTimeout(function () { if (mascot && !mHidden) { mSet('st-sleep'); say(t('m.sleep'), 5000); } }, 26000);
  }
  var lastY = w.scrollY, lastT = performance.now(), scrollStop, wasDeep = false, saidBottom = false;
  on(w, 'scroll', function () {
    if (!mascot || mHidden) return;
    var now = performance.now(), y = w.scrollY, dy = y - lastY, dt = Math.max(16, now - lastT);
    var v = Math.abs(dy) / dt * 1000;
    lastY = y; lastT = now;
    if (mState.indexOf('st-joy') < 0 && mState.indexOf('st-wave') < 0) {
      if (dy > 0) mSet(v > 2600 ? 'st-down st-run st-fast' : 'st-down st-run');
      else if (dy < 0) mSet(v > 2600 ? 'st-up st-run st-fast' : 'st-up st-run');
      if (v > 4200 && !mSaid.fast) { mSaid.fast = 1; say(t('m.fast'), 3000); }
    }
    clearTimeout(scrollStop);
    scrollStop = setTimeout(function () { if (mState.indexOf('st-run') >= 0) mSet(''); }, 180);
    if (y > 1600) wasDeep = true;
    if (y < 60 && wasDeep && !mSaid.top) { mSaid.top = 1; mSet('st-wave', 2000); say(t('m.top'), 3800); }
    var bottom = d.documentElement.scrollHeight - w.innerHeight - y;
    if (bottom < 220 && !saidBottom) { saidBottom = true; mSet('st-wave', 2600); say(t('m.bottom'), 5200); }
    resetIdle();
  }, { passive: true });
  on(d, 'pointermove', function (e) {
    if (!mascot || mHidden || !finePointer) return;
    var r = mascot.getBoundingClientRect();
    var cx = r.left + r.width / 2, cy = r.top + r.height * 0.55;
    var a = Math.atan2(e.clientY - cy, e.clientX - cx), dist = Math.min(1, Math.hypot(e.clientX - cx, e.clientY - cy) / 300);
    var p = qs('.m-pupils', mascot);
    if (p) p.setAttribute('transform', 'translate(' + (Math.cos(a) * 3 * dist).toFixed(2) + ' ' + (Math.sin(a) * 3 * dist).toFixed(2) + ')');
  }, { passive: true });
  on(d, 'keydown', resetIdle); on(d, 'pointerdown', function () { mWake(); });
  if ('IntersectionObserver' in w) {
    var sayIO = new IntersectionObserver(function (ents) {
      ents.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target, k = el.getAttribute('data-say');
        if (el.hasAttribute('data-celebrate') && !el.__joy) { el.__joy = 1; mSet('st-joy', 1700); }
        if (k && !el.__said) { el.__said = 1; say(k, 5000); }
      });
    }, { threshold: 0.45 });
    qsa('[data-say],[data-celebrate]').forEach(function (el) { sayIO.observe(el); });
  }
  if (consent) mascotShow();
  resetIdle();

  /* ------------------------------------------------------------ assistente (chat) */
  var chat, chatLog, chatHist = [], chatBusy = false;
  function aiEnabled() { return !!(CFG.ai && CFG.ai.endpoint); }
  function buildChat() {
    if (chat) return;
    chat = d.createElement('section');
    chat.className = 'ccw cc-chat';
    chat.setAttribute('role', 'dialog');
    chat.setAttribute('aria-labelledby', 'cc-ch-h');
    body.appendChild(chat);
    renderChat();
    on(chat, 'click', function (e) {
      if (e.target.closest('[data-ch="close"]')) chatOpen(false);
      var s = e.target.closest('[data-sugg]');
      if (s) ask(s.textContent);
    });
    on(chat, 'submit', function (e) {
      e.preventDefault();
      var inp = qs('input', chat), v = inp.value.trim();
      if (v) { inp.value = ''; ask(v); }
    });
  }
  function renderChat() {
    var ava = MASCOT_SVG.replace('viewBox="0 0 100 120"', 'viewBox="8 2 92 100"');
    chat.innerHTML = '<div class="ch-head"><span class="ch-ava">' + ava + '</span><div><b id="cc-ch-h">' + t('ch.title') + '</b><small><span class="dot"></span>' + t(aiEnabled() ? 'ch.ai' : 'ch.local') + '</small></div>' +
      '<button type="button" class="x" data-ch="close" aria-label="' + esc(t('ch.close')) + '">' + icon('x') + '</button></div>' +
      '<div class="ch-log" aria-live="polite"></div>' +
      '<div class="ch-sugg">' + [1, 2, 3, 4, 5].map(function (i) { return '<button type="button" data-sugg>' + t('ch.s' + i) + '</button>'; }).join('') + '</div>' +
      '<form><label class="sr-only" for="cc-ch-in">' + t('ch.ph') + '</label><input id="cc-ch-in" type="text" autocomplete="off" maxlength="600" placeholder="' + esc(t('ch.ph')) + '">' +
      '<button type="submit" aria-label="' + esc(t('ch.send')) + '">' + icon('send') + '</button></form>' +
      '<p class="ch-note">' + t('ch.note') + '</p>';
    chatLog = qs('.ch-log', chat);
    if (!chatHist.length) addMsg('bot', t('ch.greet'));
    else chatHist.forEach(function (m) { addMsg(m.role === 'user' ? 'me' : 'bot', m.content, true); });
  }
  function addMsg(who, text, replay) {
    var m = d.createElement('div');
    m.className = 'msg msg--' + who;
    m.innerHTML = who === 'bot' ? richText(text) : esc(text);
    chatLog.appendChild(m);
    chatLog.scrollTop = chatLog.scrollHeight;
    if (!replay && who !== 'typing') chatHist.push({ role: who === 'me' ? 'user' : 'assistant', content: text });
    return m;
  }
  function chatOpen(open) {
    if (open) buildChat();
    if (!chat) return;
    chat.classList.toggle('is-open', !!open);
    if (open) { setTimeout(function () { var i = qs('input', chat); if (i) i.focus(); }, 80); if (mascot) mSet('st-think', 1500); }
  }
  CC.chatOpen = chatOpen;
  on(d, 'click', function (e) { if (e.target.closest('[data-chat-open]')) { e.preventDefault(); chatOpen(true); } });
  function localAnswer(q) {
    var f = ' ' + fold(q).replace(/[^a-z0-9\s-]/g, ' ') + ' ';
    var best = null, score = 0;
    (w.CC_KB || []).forEach(function (e) {
      var s = 0;
      e.k.forEach(function (k) { if (f.indexOf(' ' + k) >= 0) s += k.length > 5 ? 2 : 1; });
      if (s > score) { score = s; best = e; }
    });
    return best ? (best[CC.lang] || best.pt) : t('ch.fallback');
  }
  function systemPrompt() {
    var kb = (w.CC_KB || []).map(function (e) { return '- ' + e.pt; }).join('\n');
    return 'Você é o Cy, assistente virtual do site do CoreCyber — plataforma brasileira de cibersegurança da EBAEM que unifica superfície de ataque (EASM), disponibilidade, vulnerabilidades priorizadas (CVSS, EPSS e KEV), inteligência de ameaças, monitoração de ativos por agentes, conformidade ISO 27001/LGPD, casos de incidente e relatórios com QR de autenticidade. ' +
      'Responda no idioma do usuário (' + CC.lang + '), em no máximo 5 frases, de forma cordial e objetiva. Use apenas as informações abaixo; se não souber, diga que não sabe e ofereça o contato humano (WhatsApp ' + (CO.phone_display || '') + ', ' + (CO.email_sales || '') + '). ' +
      'Seja honesto sobre o roadmap: SIEM, EDR completo, NDR e assistente de IA na plataforma ainda não estão disponíveis. Não invente preços, prazos, clientes ou certificações. Nunca ajude a atacar sistemas de terceiros: varreduras só com autorização do titular. Links podem ser escritos como [texto](/caminho/). Nunca peça dados pessoais sensíveis nem senhas.\n\nBase de conhecimento:\n' + kb;
  }
  function ask(q) {
    if (chatBusy) return;
    addMsg('me', q);
    var typing = d.createElement('div');
    typing.className = 'msg msg--bot'; typing.innerHTML = '<span class="typing"><i></i><i></i><i></i></span>';
    chatLog.appendChild(typing); chatLog.scrollTop = chatLog.scrollHeight;
    chatBusy = true;
    function done(text, note) {
      typing.remove(); chatBusy = false;
      addMsg('bot', text);
      if (note) addMsg('bot', note);
    }
    if (aiEnabled() && consentAllowed('functional')) {
      var msgs = [{ role: 'system', content: systemPrompt() }].concat(chatHist.slice(-8));
      var ctrl = 'AbortController' in w ? new AbortController() : null;
      var to = setTimeout(function () { if (ctrl) ctrl.abort(); }, 20000);
      fetch(CFG.ai.endpoint, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, signal: ctrl ? ctrl.signal : undefined,
        body: JSON.stringify({ model: CFG.ai.model || 'auto', messages: msgs, max_tokens: CFG.ai.max_tokens || 450, temperature: 0.3 })
      }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function (j) {
          clearTimeout(to);
          var c = j && j.choices && j.choices[0] && j.choices[0].message && j.choices[0].message.content;
          if (!c) throw new Error('vazio');
          done(String(c).trim().slice(0, 2400));
        }).catch(function () { clearTimeout(to); done(localAnswer(q), t('ch.err')); });
    } else {
      setTimeout(function () {
        done(localAnswer(q), aiEnabled() && !consentAllowed('functional') && chatHist.length < 4 ? t('ch.consent') : null);
      }, 450 + Math.random() * 400);
    }
  }

  /* ------------------------------------------------------------ entrada animada */
  if ('IntersectionObserver' in w) {
    qsa('[data-stagger]').forEach(function (p) {
      qsa('[data-reveal]', p).forEach(function (c, i) { c.style.setProperty('--d', (i * 0.08).toFixed(2) + 's'); });
    });
    var rio = new IntersectionObserver(function (ents) {
      ents.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); rio.unobserve(en.target); } });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    qsa('[data-reveal]').forEach(function (el) { rio.observe(el); });
    /* rede de segurança: em aparelhos lentos o observador pode pular quadros */
    var revTimer;
    on(w, 'scroll', function () {
      clearTimeout(revTimer);
      revTimer = setTimeout(function () {
        qsa('[data-reveal]:not(.is-in)').forEach(function (el) {
          if (el.getBoundingClientRect().top < w.innerHeight * 0.95) { el.classList.add('is-in'); rio.unobserve(el); }
        });
      }, 160);
    }, { passive: true });
    var cio = new IntersectionObserver(function (ents) {
      ents.forEach(function (en) {
        if (!en.isIntersecting) return;
        cio.unobserve(en.target);
        var el = en.target, end = parseFloat(el.getAttribute('data-count')), suf = el.getAttribute('data-suffix') || '', pre = el.getAttribute('data-prefix') || '';
        if (motionOff()) { el.textContent = pre + num(end) + suf; return; }
        var t0 = performance.now();
        (function step(now) {
          var p = clamp((now - t0) / 1400, 0, 1), e = 1 - Math.pow(1 - p, 3);
          el.textContent = pre + num(Math.round(end * e)) + suf;
          if (p < 1) requestAnimationFrame(step);
        })(t0);
      });
    }, { threshold: 0.6 });
    qsa('[data-count]').forEach(function (el) { cio.observe(el); });
  } else {
    qsa('[data-reveal]').forEach(function (el) { el.classList.add('is-in'); });
  }

  /* ------------------------------------------------------------ inclinação 3D e parallax */
  if (finePointer) {
    on(d, 'pointermove', function (e) {
      var c = e.target.closest && e.target.closest('[data-tilt]');
      if (!c || motionOff()) return;
      var r = c.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
      var max = parseFloat(c.getAttribute('data-tilt')) || 6;
      c.style.transform = 'perspective(900px) rotateX(' + ((0.5 - y) * max).toFixed(2) + 'deg) rotateY(' + ((x - 0.5) * max).toFixed(2) + 'deg)';
      c.style.setProperty('--mx', (x * 100).toFixed(1) + '%'); c.style.setProperty('--my', (y * 100).toFixed(1) + '%');
    }, { passive: true });
    on(d, 'pointerout', function (e) {
      var c = e.target.closest && e.target.closest('[data-tilt]');
      if (c && !c.contains(e.relatedTarget)) c.style.transform = '';
    });
  }
  qsa('[data-parallax-group]').forEach(function (g) {
    var items = qsa('[data-depth]', g), tx = 0, ty = 0, cx = 0, cy = 0, raf;
    function loop() {
      cx += (tx - cx) * 0.08; cy += (ty - cy) * 0.08;
      items.forEach(function (it) { var dd = parseFloat(it.getAttribute('data-depth')); it.style.transform = 'translate3d(' + (cx * dd * 26).toFixed(2) + 'px,' + (cy * dd * 26).toFixed(2) + 'px,0)'; });
      if (Math.abs(tx - cx) > 0.001 || Math.abs(ty - cy) > 0.001) raf = requestAnimationFrame(loop); else raf = 0;
    }
    on(w, 'pointermove', function (e) {
      if (motionOff()) return;
      tx = (e.clientX / w.innerWidth - 0.5) * 2; ty = (e.clientY / w.innerHeight - 0.5) * 2;
      if (!raf) raf = requestAnimationFrame(loop);
    }, { passive: true });
  });

  /* ------------------------------------------------------------ camadas animadas */
  qsa('[data-layers]').forEach(function (box) {
    var stack = qs('.lstack', box), plates = qsa('.lplate', box), items = qsa('.layers-list li', box);
    var n = Math.max(plates.length, items.length);
    function upd() {
      var r = box.getBoundingClientRect(), total = r.height - w.innerHeight;
      var p = total > 0 ? clamp(-r.top / total, 0, 1) : 0.7;
      if (w.innerWidth <= 900 || motionOff()) p = 0.7;
      var ease = p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2;
      if (stack) stack.style.setProperty('--lp', (0.12 + ease * 0.88).toFixed(3));
      var idx = clamp(Math.floor(p * n), 0, n - 1);
      items.forEach(function (li, i) { li.classList.toggle('is-active', i === idx); });
      plates.forEach(function (pl, i) { pl.classList.toggle('is-active', i === idx); pl.classList.toggle('is-dim', i > idx); });
    }
    on(w, 'scroll', upd, { passive: true }); on(w, 'resize', upd); upd();
  });

  /* ------------------------------------------------------------ holofote (identidade oculta) */
  qsa('.spotlight').forEach(function (sp) {
    var idle = 0, raf = 0, active = false;
    function set(x, y) { sp.style.setProperty('--sx', x + 'px'); sp.style.setProperty('--sy', y + 'px'); }
    function wander(ts) {
      if (active || sp.classList.contains('is-open')) { raf = 0; return; }
      var r = sp.getBoundingClientRect();
      var k = ts / 1000;
      set(r.width * (0.5 + 0.3 * Math.sin(k * 0.7)), r.height * (0.45 + 0.25 * Math.sin(k * 1.1 + 1)));
      raf = motionOff() ? 0 : requestAnimationFrame(wander);
    }
    on(sp, 'pointermove', function (e) { active = true; var r = sp.getBoundingClientRect(); set(e.clientX - r.left, e.clientY - r.top); clearTimeout(idle); idle = setTimeout(function () { active = false; if (!raf) raf = requestAnimationFrame(wander); }, 2500); });
    on(sp, 'pointerleave', function () { active = false; if (!raf) raf = requestAnimationFrame(wander); });
    on(sp, 'click', function (e) {
      if (e.target.closest('[data-spot-toggle]') || !finePointer) {
        sp.classList.toggle('is-open');
        var b = qs('[data-spot-toggle]', sp); if (b) b.setAttribute('aria-expanded', sp.classList.contains('is-open') ? 'true' : 'false');
      }
    });
    if ('IntersectionObserver' in w) new IntersectionObserver(function (en) { if (en[0].isIntersecting && !raf) raf = requestAnimationFrame(wander); }).observe(sp);
  });
  /* cartões de clientes: a máscara se abre a partir do ponteiro; no toque, alterna */
  on(d, 'pointermove', function (e) {
    var c = e.target.closest && e.target.closest('[data-spotmask]'); if (!c) return;
    var r = c.getBoundingClientRect();
    c.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
    c.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
  }, { passive: true });
  on(d, 'click', function (e) {
    var c = e.target.closest('[data-spotmask], .redact');
    if (c && !finePointer) c.classList.toggle('is-revealed');
  });
  on(d, 'click', function (e) {
    var c = e.target.closest('.reveal-id');
    if (c && !finePointer) c.classList.toggle('is-revealed');
  });

  /* ------------------------------------------------------------ abas */
  on(d, 'click', function (e) {
    var tab = e.target.closest('[role="tab"]'); if (!tab) return;
    selectTab(tab);
  });
  on(d, 'keydown', function (e) {
    var tab = e.target.closest && e.target.closest('[role="tab"]'); if (!tab) return;
    var tabs = qsa('[role="tab"]', tab.closest('[role="tablist"]')), i = tabs.indexOf(tab);
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); selectTab(tabs[(i + 1) % tabs.length], true); }
    if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); selectTab(tabs[(i - 1 + tabs.length) % tabs.length], true); }
  });
  function selectTab(tab, focus) {
    var list = tab.closest('[role="tablist"]');
    qsa('[role="tab"]', list).forEach(function (x) {
      var sel = x === tab;
      x.setAttribute('aria-selected', sel ? 'true' : 'false'); x.tabIndex = sel ? 0 : -1;
      var p = d.getElementById(x.getAttribute('aria-controls')); if (p) p.hidden = !sel;
    });
    if (focus) tab.focus();
    if (tab.id && history.replaceState) history.replaceState(null, '', '#' + tab.id.replace(/^tab-/, ''));
  }
  (function () {
    var h = location.hash.slice(1); if (!h) return;
    var tab = d.getElementById('tab-' + h); if (tab) { selectTab(tab); setTimeout(function () { tab.scrollIntoView({ block: 'center' }); }, 200); }
  })();

  /* ------------------------------------------------------------ pontos interativos */
  on(d, 'click', function (e) {
    var h = e.target.closest('.hotspot');
    qsa('.hotspot.is-on').forEach(function (x) { if (x !== h) x.classList.remove('is-on'); });
    if (h) h.classList.toggle('is-on');
  });

  /* ------------------------------------------------------------ protótipos: tema claro/escuro e demonstrações */
  on(d, 'click', function (e) {
    var b = e.target.closest('[data-theme-set]'); if (!b) return;
    var g = b.closest('.theme-pick'), app = qs(g.getAttribute('data-target'));
    qsa('[data-theme-set]', g).forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
    if (app) app.setAttribute('data-theme', b.getAttribute('data-theme-set'));
  });
  function hhmm() { var n = new Date(); return ('0' + n.getHours()).slice(-2) + ':' + ('0' + n.getMinutes()).slice(-2); }
  function seq(n) { return String(Math.floor(Math.random() * 9000) + 1000); }
  var PILL = { ok: 'm-pill--ok', warn: 'm-pill--warn', crit: 'm-pill--crit', info: 'm-pill--info', p: 'm-pill--p', '': '' };
  function setPill(el, cls, txt) { el.className = 'm-pill ' + (PILL[cls] || ''); el.textContent = txt; }
  function later(i, fn) { return setTimeout(fn, motionOff() ? 0 : i); }
  /* filtro por severidade */
  on(d, 'click', function (e) {
    var b = e.target.closest('[data-sev-filter]'); if (!b) return;
    var proto = b.closest('.proto') || d, v = b.getAttribute('data-sev-filter');
    qsa('[data-sev-filter]', proto).forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
    qsa('[data-sev]', proto).forEach(function (r) { r.hidden = !(v === 'all' || r.getAttribute('data-sev') === v); });
  });
  on(d, 'click', function (e) {
    var b = e.target.closest('[data-demo]'); if (!b) return;
    var kind = b.getAttribute('data-demo'), proto = b.closest('.proto') || d;
    /* varredura: revela linhas em sequência e atualiza a barra de progresso */
    if (kind === 'scan') {
      if (b.__busy) return; b.__busy = 1;
      var rows = qsa('[data-scan-row]', proto), bar = qs('[data-scan-prog]', proto), st = qs('[data-scan-status]', proto);
      rows.forEach(function (r) { r.classList.remove('is-in'); });
      if (st) setPill(st, 'info', t('demo.scan.run'));
      rows.forEach(function (r, i) {
        later(420 * (i + 1), function () {
          r.classList.add('is-in');
          if (bar) bar.style.setProperty('--w', Math.round((i + 1) / rows.length * 100) + '%');
          if (i === rows.length - 1) {
            b.__busy = 0;
            var nn = rows.filter(function (x) { return x.getAttribute('data-scan-row') === 'new'; }).length;
            var rs = rows.filter(function (x) { return x.getAttribute('data-scan-row') === 'fixed'; }).length;
            if (st) setPill(st, 'ok', t('demo.scan.done', { h: hhmm() }));
            toast(esc(t('demo.scan.toast', { n: nn, r: rs })));
          }
        });
      });
    }
    /* ciclo de estados (vulnerabilidade, correção remota, declaração de aplicabilidade, caso) */
    if (kind === 'cycle') {
      var box = b.closest('[data-cycle]') || proto, pill = qs('[data-cycle-pill]', box);
      var states = (box.getAttribute('data-states') || '').split('|'), classes = (box.getAttribute('data-classes') || '').split('|');
      if (!pill || !states.length) return;
      var i = ((+pill.getAttribute('data-i') || 0) + 1) % states.length;
      pill.setAttribute('data-i', i); setPill(pill, classes[i] || '', t(states[i]));
      var dec = box.getAttribute('data-dec');
      if (dec && i === states.length - 1) { qsa(dec, proto).forEach(function (k) { var v = parseInt(k.textContent, 10); if (!isNaN(v) && v > 0) k.textContent = v - 1; }); }
      var msg = box.getAttribute('data-toast'); if (msg) toast(esc(t(msg, { s: t(states[i]), n: seq() })));
    }
    /* etapas em sequência (linha do tempo de caso, descomissionamento, automação) */
    if (kind === 'steps') {
      if (b.__busy) return; b.__busy = 1;
      var wrap = qs(b.getAttribute('data-target') || '.m-steps', proto), spans = wrap ? qsa('span', wrap) : [];
      spans.forEach(function (s_) { s_.classList.remove('done', 'on'); });
      spans.forEach(function (s_, k) {
        later(520 * k, function () { if (k > 0) { spans[k - 1].classList.remove('on'); spans[k - 1].classList.add('done'); } s_.classList.add('on'); });
      });
      later(520 * spans.length, function () {
        if (spans.length) { spans[spans.length - 1].classList.remove('on'); spans[spans.length - 1].classList.add('done'); }
        b.__busy = 0;
        var m2 = b.getAttribute('data-toast'); if (m2) toast(esc(t(m2, { n: seq(), h: hhmm() })));
      });
    }
    /* mensagem simples */
    if (kind === 'toast') toast(esc(t(b.getAttribute('data-msg') || 'demo.ok', { n: seq(), h: hhmm() })));
    /* abrir/fechar painel do protótipo */
    if (kind === 'open') { var m = qs(b.getAttribute('data-target'), proto); if (m) m.hidden = false; }
    if (kind === 'close') { var m3 = b.closest('.m-modal'); if (m3) m3.hidden = true; }
  });

  /* ------------------------------------------------------------ preços (faixas de ativos) */
  var PR = CFG.pricing || {};
  function planById(id) { return (PR.plans || []).filter(function (p) { return p.id === id; })[0]; }
  qsa('[data-pricing]').forEach(function (box) {
    var st = { bill: 'y', tier: PR.default_tier != null ? PR.default_tier : 2, host: 'cloud' };
    var range = qs('[data-assets]', box);
    function render() {
      var tiers = PR.tiers || [25, 50, 100, 250, 500], n = tiers[st.tier];
      var out = qs('[data-assets-out]', box); if (out) out.textContent = t('pr.upto', { n: num(n) });
      if (range) { range.value = st.tier; range.style.setProperty('--p', (st.tier / (tiers.length - 1) * 100) + '%'); range.setAttribute('aria-valuetext', t('pr.upto', { n: num(n) })); }
      qsa('[data-bill]', box).forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-bill') === st.bill ? 'true' : 'false'); });
      qsa('[data-host]', box).forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-host') === st.host ? 'true' : 'false'); });
      (PR.plans || []).forEach(function (pl) {
        var card = qs('[data-plan="' + pl.id + '"]', box); if (!card) return;
        var m = pl.monthly[st.tier] * (st.host === 'dedicated' ? (PR.dedicated_factor || 1.35) : 1);
        m = Math.round(m / 10) * 10;
        var mp = PR.annual_months_paid || 10;
        var shown = st.bill === 'y' ? Math.round(m * mp / 12) : m;
        var val = qs('[data-price]', card), note = qs('[data-note]', card), per = qs('[data-perasset]', card), host = qs('[data-hostnote]', card);
        if (val) { val.textContent = num(shown); val.setAttribute('aria-label', money(shown)); }
        if (note) note.innerHTML = st.bill === 'y' ? t('pr.yearly', { v: '<b>' + money(m * mp) + '</b>', s: money(m * (12 - mp)) }) : t('pr.monthly');
        if (per) per.textContent = t('pr.perasset', { v: money(shown / n, true) });
        if (host) host.textContent = t(st.host === 'dedicated' ? 'pr.dedicated' : 'pr.cloud');
        var cta = qs('[data-plan-cta]', card);
        if (cta) cta.href = waUrl(t('pr.msg', { p: card.getAttribute('data-plan-name') || pl.id, u: t('pr.upto', { n: num(n) }), b: t(st.bill === 'y' ? 'pr.b.y' : 'pr.b.m'), h: t(st.host === 'dedicated' ? 'pr.h.ded' : 'pr.h.cloud') }));
      });
      var big = qs('[data-big]', box); if (big) big.hidden = st.tier < tiers.length - 1;
    }
    on(box, 'click', function (e) {
      var b = e.target.closest('[data-bill]'); if (b) { st.bill = b.getAttribute('data-bill'); render(); }
      var h = e.target.closest('[data-host]'); if (h) { st.host = h.getAttribute('data-host'); render(); }
    });
    on(range, 'input', function () { st.tier = +range.value; render(); });
    on(d, 'cc:lang', render);
    render();
  });

  /* ------------------------------------------------------------ simulador: ferramentas separadas x CoreCyber */
  qsa('[data-sim]').forEach(function (box) {
    var ra = qs('[data-sim-assets]', box), rf = qs('[data-sim-factor]', box);
    var MK = {}; (PR.market || []).forEach(function (m) { MK[m.id] = m; });
    function planFor(sel, n) {
      var id = (sel.siem || sel.edr || sel.soar || sel.grc) ? 'avancado' : ((sel.vm || sel.cti) ? 'profissional' : 'essencial');
      var tiers = PR.tiers || [25, 50, 100, 250, 500], i = 0;
      while (i < tiers.length - 1 && tiers[i] < n) i++;
      var pl = planById(id) || (PR.plans || [])[0];
      var monthly = pl ? pl.monthly[i] : 0;
      var over = n > tiers[tiers.length - 1];
      if (over) monthly = Math.round(monthly * n / tiers[tiers.length - 1]);
      return { id: id, monthly: monthly, over: over };
    }
    function render() {
      var n = +ra.value, f = (rf ? +rf.value : 100) / 100, sel = {}, perAsset = 0;
      qsa('[data-sim-tool]', box).forEach(function (c) {
        var id = c.getAttribute('data-sim-tool');
        sel[id] = c.checked;
        if (c.checked && MK[id]) perAsset += MK[id].per_asset;
        var lb = c.closest('label'); if (lb) lb.classList.toggle('is-on', c.checked);
      });
      ra.style.setProperty('--p', ((n - ra.min) / (ra.max - ra.min) * 100) + '%');
      if (rf) rf.style.setProperty('--p', ((rf.value - rf.min) / (rf.max - rf.min) * 100) + '%');
      var them = n * perAsset * f * 12, pf = planFor(sel, n), us = pf.monthly * (PR.annual_months_paid || 10), save = Math.max(0, them - us);
      qs('[data-sim-assets-out]', box).textContent = t('sim.assets', { n: num(n) });
      if (rf) qs('[data-sim-factor-out]', box).textContent = t('sim.factor', { p: rf.value });
      qs('[data-sim-them]', box).textContent = money(them);
      qs('[data-sim-us]', box).textContent = money(us);
      qs('[data-sim-plan]', box).textContent = t('sim.plan', { p: t('plan.' + pf.id) });
      var mx = Math.max(them, us, 1);
      qs('[data-sim-them-bar]', box).style.setProperty('--w', (them / mx * 100) + '%');
      qs('[data-sim-us-bar]', box).style.setProperty('--w', Math.max(2, us / mx * 100) + '%');
      qs('[data-sim-save]', box).innerHTML = money(save) + '<small>' + t('sim.save') + '</small>';
      var pct = them > 0 ? Math.round(save / them * 100) : 0;
      qs('[data-sim-pct]', box).textContent = !perAsset ? t('sim.pick') : (save > 0 ? t('sim.pct', { p: pct }) : t('sim.none'));
      var notes = [];
      if (pf.over) notes.push(t('sim.more'));
      if (sel.siem || sel.edr) notes.push(t('sim.roadmap'));
      var more = qs('[data-sim-more]', box); if (more) { more.hidden = !notes.length; more.textContent = notes.join(' '); }
    }
    on(ra, 'input', render); on(rf, 'input', render);
    on(box, 'change', function (e) { if (e.target.closest('[data-sim-tool]')) render(); });
    on(d, 'cc:lang', render);
    render();
  });

  /* ------------------------------------------------------------ verificação rápida de DNS e e-mail (DoH, iniciada pelo visitante) */
  function doh(name, type) {
    var g = 'https://dns.google/resolve?name=' + encodeURIComponent(name) + '&type=' + type;
    var c = 'https://cloudflare-dns.com/dns-query?name=' + encodeURIComponent(name) + '&type=' + type;
    function get(url, hdr) {
      var ctrl = 'AbortController' in w ? new AbortController() : null;
      var to = setTimeout(function () { if (ctrl) ctrl.abort(); }, 8000);
      return fetch(url, { headers: hdr || {}, signal: ctrl ? ctrl.signal : undefined, referrerPolicy: 'no-referrer', credentials: 'omit' })
        .then(function (r) { clearTimeout(to); if (!r.ok) throw new Error(r.status); return r.json(); });
    }
    return get(g).catch(function () { return get(c, { accept: 'application/dns-json' }); });
  }
  function txts(j) { return ((j && j.Answer) || []).filter(function (a) { return a.type === 16; }).map(function (a) { return String(a.data).replace(/^"|"$/g, '').replace(/"\s*"/g, ''); }); }
  function cleanDomain(v) { return String(v || '').trim().toLowerCase().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/[\/?#].*$/, '').replace(/\.$/, ''); }
  on(d, 'submit', function (e) {
    var f = e.target.closest('[data-dnscheck]'); if (!f) return;
    e.preventDefault();
    var dom = cleanDomain((qs('input', f) || {}).value);
    var out = qs('[data-dns-out]', f.parentNode) || qs('[data-dns-out]');
    if (!/^[a-z0-9-]+(\.[a-z0-9-]+)+$/.test(dom)) { toast(t('dns.invalid')); return; }
    if (!out) return;
    var btn = qs('button[type="submit"]', f); if (btn) btn.disabled = true;
    out.hidden = false;
    out.innerHTML = '<p class="dns-wait"><span class="typing"><i></i><i></i><i></i></span> ' + esc(t('dns.wait', { d: dom })) + '</p>';
    Promise.all([
      doh(dom, 'TXT'), doh('_dmarc.' + dom, 'TXT'), doh(dom, 'MX'), doh(dom, 'CAA'), doh('_mta-sts.' + dom, 'TXT'), doh('_smtp._tls.' + dom, 'TXT'), doh(dom, 'A')
    ].map(function (pr) { return pr.catch(function () { return null; }); })).then(function (r) {
      var res = [];
      if (!r[6] && !r[0]) { out.innerHTML = '<p class="dns-wait">' + esc(t('dns.fail')) + '</p>'; return; }
      if (r[6] && r[6].Status === 3) { out.innerHTML = '<p class="dns-wait">' + esc(t('dns.nx', { d: dom })) + '</p>'; return; }
      var spf = txts(r[0]).filter(function (x) { return /^v=spf1/i.test(x); });
      if (!spf.length) res.push(['bad', 'SPF', t('dns.spf.none')]);
      else if (spf.length > 1) res.push(['warn', 'SPF', t('dns.spf.multi')]);
      else if (/[+?]all\b/i.test(spf[0])) res.push(['warn', 'SPF', t('dns.spf.soft', { v: spf[0].slice(0, 120) })]);
      else res.push(['ok', 'SPF', t('dns.spf.ok', { v: spf[0].slice(0, 120) })]);
      var dm = txts(r[1]).filter(function (x) { return /^v=DMARC1/i.test(x); })[0];
      var pol = dm ? ((dm.match(/;\s*p=([a-z]+)/i) || [])[1] || '').toLowerCase() : '';
      if (!dm) res.push(['bad', 'DMARC', t('dns.dmarc.none')]);
      else if (pol === 'none' || !pol) res.push(['warn', 'DMARC', t('dns.dmarc.none.p')]);
      else res.push(['ok', 'DMARC', t('dns.dmarc.ok', { p: pol })]);
      var dnssec = !!((r[6] && r[6].AD) || (r[0] && r[0].AD));
      res.push(dnssec ? ['ok', 'DNSSEC', t('dns.sec.ok')] : ['warn', 'DNSSEC', t('dns.sec.no')]);
      var caa = ((r[3] && r[3].Answer) || []).filter(function (a) { return a.type === 257; });
      res.push(caa.length ? ['ok', 'CAA', t('dns.caa.ok')] : ['warn', 'CAA', t('dns.caa.no')]);
      var mx = ((r[2] && r[2].Answer) || []).filter(function (a) { return a.type === 15; });
      res.push(mx.length ? ['ok', 'MX', t('dns.mx.ok', { n: mx.length })] : ['info', 'MX', t('dns.mx.no')]);
      var sts = txts(r[4]).some(function (x) { return /^v=STSv1/i.test(x); });
      res.push(sts ? ['ok', 'MTA-STS', t('dns.sts.ok')] : ['info', 'MTA-STS', t('dns.sts.no')]);
      var rpt = txts(r[5]).some(function (x) { return /^v=TLSRPTv1/i.test(x); });
      res.push(rpt ? ['ok', 'TLS-RPT', t('dns.rpt.ok')] : ['info', 'TLS-RPT', t('dns.rpt.no')]);
      var pts = { ok: 2, info: 1, warn: 1, bad: 0 }, total = res.length * 2, got = res.reduce(function (a, x) { return a + pts[x[0]]; }, 0);
      var pctv = Math.round(got / total * 100), grade = pctv >= 90 ? 'A' : pctv >= 75 ? 'B' : pctv >= 60 ? 'C' : pctv >= 40 ? 'D' : 'E';
      var ic = { ok: 'check', warn: 'alert', bad: 'x', info: 'info' };
      out.innerHTML = '<div class="dns-head"><div class="dns-grade g-' + grade + '"><b>' + grade + '</b><span>' + pctv + '%</span></div><div><h3>' + esc(t('dns.title', { d: dom })) + '</h3><p>' + esc(t('dns.sub')) + '</p></div></div>' +
        '<ul class="dns-list">' + res.map(function (x) { return '<li class="is-' + x[0] + '">' + icon(ic[x[0]]) + '<b>' + x[1] + '</b><span>' + esc(x[2]) + '</span></li>'; }).join('') + '</ul>' +
        '<div class="dns-cta"><p>' + esc(t('dns.more')) + '</p><a class="btn btn--primary btn--sm" href="' + esc(fixHref('/diagnostico-gratuito/?dominio=' + encodeURIComponent(dom) + '#pedido')) + '">' + esc(t('dns.cta')) + ' ' + icon('arrow', 'arrow') + '</a></div>' +
        '<p class="tiny">' + esc(t('dns.note')) + '</p>';
      if (mascot && !mHidden) { mSet(grade <= 'B' ? 'st-joy' : 'st-think', 1600); say(t(grade <= 'B' ? 'm.dns.good' : 'm.dns.bad'), 5200); }
    }).finally(function () { if (btn) btn.disabled = false; });
  });
  /* preenche o domínio vindo da verificação rápida */
  (function () {
    var v = new URLSearchParams(location.search).get('dominio'); if (!v) return;
    qsa('input[data-prefill="dominio"]').forEach(function (i) { i.value = cleanDomain(v); });
  })();

  /* ------------------------------------------------------------ formulários (WhatsApp / e-mail) */
  function proto(prefix) { return prefix + '-' + new Date().getFullYear() + '-' + String(Math.floor(Math.random() * 900000) + 100000); }
  on(d, 'submit', function (e) {
    var f = e.target.closest('[data-form]'); if (!f) return;
    e.preventDefault();
    var ok = true, first = null;
    qsa('input, select, textarea', f).forEach(function (el) {
      el.removeAttribute('aria-invalid');
      var err = el.parentNode.querySelector('.field-err'); if (err) err.remove();
      var bad = '';
      if (el.type === 'checkbox' && el.required && !el.checked) bad = t('f.consent');
      else if (el.required && !String(el.value).trim()) bad = t('f.required');
      else if (el.type === 'email' && el.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value)) bad = t('f.email');
      if (bad) {
        ok = false; first = first || el;
        el.setAttribute('aria-invalid', 'true');
        var m = d.createElement('span'); m.className = 'help field-err'; m.style.color = 'var(--c-red)'; m.textContent = bad;
        m.id = (el.id || el.name) + '-err'; el.setAttribute('aria-describedby', m.id);
        (el.type === 'checkbox' ? el.closest('label') || el.parentNode : el.parentNode).appendChild(m);
      }
    });
    if (!ok) { first.focus(); return; }
    var kind = f.getAttribute('data-form');
    var pfx = { contact: 'CTT', titular: 'LGPD', integrity: 'INT', referral: 'IND', diagnostic: 'DGN', poc: 'POC', partner: 'PAR', disclosure: 'VDP', demo: 'DEM' }[kind] || 'CCY';
    var p = proto(pfx), title = t('f.t.' + kind);
    var lines = [title + ' — ' + t('f.proto') + ' ' + p, ''];
    qsa('input, select, textarea', f).forEach(function (el) {
      if (el.type === 'checkbox' || (el.type === 'radio' && !el.checked) || el.name === 'via' || !el.value || el.type === 'hidden') return;
      var label, val;
      if (el.type === 'radio') {
        var fs = el.closest('fieldset'), lg = fs ? qs('legend', fs) : null;
        label = lg ? lg.textContent.replace(/\*/g, '').trim() : el.name;
        val = (el.closest('label') || {}).textContent || el.value;
      } else {
        var lb = el.id ? qs('label[for="' + el.id + '"]', f) : null;
        label = lb ? lb.textContent.replace(/\*/g, '').trim() : (el.getAttribute('aria-label') || el.name);
        val = el.tagName === 'SELECT' ? el.options[el.selectedIndex].text : el.value;
      }
      lines.push(label + ': ' + String(val).trim());
    });
    var via = qs('input[name="via"]:checked', f);
    var mailTo = f.getAttribute('data-mail') || CO.email_sales;
    var msg = qs('.form-msg', f);
    if (via && via.value === 'whatsapp') {
      w.open(waUrl(lines.join('\n')), '_blank', 'noopener');
      if (msg) { msg.className = 'form-msg form-msg--ok is-on'; msg.textContent = t('f.ok.wa', { p: p }) + ' ' + t('f.ok.hint', { m: mailTo }); }
    } else {
      location.href = 'mailto:' + mailTo + '?subject=' + encodeURIComponent(t('f.subject', { t: title, p: p })) + '&body=' + encodeURIComponent(lines.join('\n'));
      if (msg) { msg.className = 'form-msg form-msg--ok is-on'; msg.textContent = t('f.ok.mail', { p: p }) + ' ' + t('f.ok.hint', { m: mailTo }); }
    }
    if (msg) msg.setAttribute('role', 'status');
  });

  /* ------------------------------------------------------------ copiar */
  on(d, 'click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return;
    var v = b.getAttribute('data-copy');
    if (navigator.clipboard) navigator.clipboard.writeText(v).then(function () { toast(t('toast.copied')); });
  });

  /* ------------------------------------------------------------ sumário com destaque (políticas) */
  var tocLinks = qsa('.toc a[href^="#"]');
  if (tocLinks.length && 'IntersectionObserver' in w) {
    var map = {};
    tocLinks.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
    var tio = new IntersectionObserver(function (ents) {
      ents.forEach(function (en) {
        if (en.isIntersecting && map[en.target.id]) {
          tocLinks.forEach(function (a) { a.classList.remove('is-active'); });
          map[en.target.id].classList.add('is-active');
        }
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    Object.keys(map).forEach(function (id) { var s = d.getElementById(id); if (s) tio.observe(s); });
  }

  /* ------------------------------------------------------------ diversos */
  qsa('[data-year]').forEach(function (el) { el.textContent = new Date().getFullYear(); });
  (function favicon() {
    var ic = CFG.img && CFG.img.icon;
    if (!ic || qs('link[rel="icon"][data-cc]')) return;
    if (!qs('link[rel="icon"]')) {
      var l = d.createElement('link'); l.rel = 'icon'; l.href = ic; l.setAttribute('data-cc', ''); d.head.appendChild(l);
    }
    if (!qs('link[rel="apple-touch-icon"]') && CFG.img.icon180) {
      var a = d.createElement('link'); a.rel = 'apple-touch-icon'; a.href = CFG.img.icon180; d.head.appendChild(a);
    }
    if (!qs('meta[name="theme-color"]')) { var m = d.createElement('meta'); m.name = 'theme-color'; m.content = '#002141'; d.head.appendChild(m); }
    if (!metaDesc && META && qs('[data-page-desc]')) {
      metaDesc = d.createElement('meta'); metaDesc.name = 'description'; metaDesc.content = qs('[data-page-desc]').getAttribute('data-page-desc'); ptDesc = metaDesc.content; d.head.appendChild(metaDesc);
    }
  })();

  /* re-renderiza widgets ao trocar idioma */
  on(d, 'cc:lang', function () {
    renderDock(); renderFab(); renderPanel();
    if (cookieBox && cookieBox.classList.contains('is-on')) renderBanner();
    if (modal && !modal.hidden) openPrefs();
    if (chat) renderChat();
    if (mascot) { qs('.m-hit', mascot).setAttribute('aria-label', t('m.ask')); qs('.m-close', mascot).setAttribute('aria-label', t('m.hide')); mMin.setAttribute('aria-label', t('m.show')); }
    totop.setAttribute('aria-label', t('totop'));
  });


  /* ------------------------------------------------------------ módulo 3D (sob demanda) */
  (function load3D() {
    var src = qs('#cc-mod-3d'), targets = qsa('[data-3d]');
    if (!src || !targets.length) return;
    var started = false;
    function start() {
      if (started) return; started = true;
      try {
        unzipText(src).then(function (code) {
          var url = URL.createObjectURL(new Blob([code], { type: 'text/javascript' }));
          return import(url);
        }).then(function (m) { if (m && m.init) m.init(CC); }).catch(function (err) { if (w.console) console.warn('[CoreCyber] 3D indisponível:', err); });
      } catch (err) { /* navegador sem suporte */ }
    }
    if ('IntersectionObserver' in w) {
      var io = new IntersectionObserver(function (en) { if (en.some(function (x) { return x.isIntersecting; })) { io.disconnect(); start(); } }, { rootMargin: '300px' });
      targets.forEach(function (t_) { io.observe(t_); });
    } else start();
  })();
})();
