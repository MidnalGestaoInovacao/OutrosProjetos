/* =====================================================================
   TRIX Tecnologia Inteligente — scripts do site
   Módulos: header/megamenu, SEO (head + JSON-LD), cookies (consentimento),
   acessibilidade, VLibras, contatos flutuantes, formulários com captcha,
   3D (Three.js), tilt, reveal, mapa sob demanda.
   ===================================================================== */
(function () {
  'use strict';
  var CFG = window.TRIX_CONFIG || {};
  var d = document, w = window;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var on = function (el, ev, fn, o) { if (el) el.addEventListener(ev, fn, o || false); };
  var reduced = w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function calm() { return reduced || d.documentElement.classList.contains('a11y-motion'); }
  d.documentElement.classList.add('trix-js');
  d.body.classList.add('trix');

  /* ---------------- SEO: <head> dinâmico + dados estruturados ---------------- */
  function seo() {
    var node = $('#trix-seo'); var data = {};
    try { data = node ? JSON.parse(node.textContent) : {}; } catch (e) { data = {}; }
    var head = d.head, origin = location.origin, url = origin + (data.canonical || location.pathname);
    var site = CFG.siteName || 'Trix Tecnologia Inteligente';
    function meta(attr, key, val) {
      if (!val || serverSeo) return; var m = head.querySelector('meta[' + attr + '="' + key + '"]');
      if (!m) { m = d.createElement('meta'); m.setAttribute(attr, key); head.appendChild(m); }
      m.setAttribute('content', val);
    }
    var serverSeo = !!w.TRIX_SERVER_SEO; /* mu-plugin já emitiu title/meta/canonical no servidor */
    var title = data.title || d.title;
    if (data.title && !serverSeo) d.title = data.title;
    var desc = data.description || CFG.defaultDescription || '';
    var abs = function (u) { return u && u.charAt(0) === '/' ? origin + u : u; };
    var img = abs(data.image || CFG.defaultImage || ''), logo = abs(CFG.logo);
    meta('name', 'description', desc);
    if (data.robots) meta('name', 'robots', data.robots);
    meta('property', 'og:site_name', site);
    meta('property', 'og:type', data.type === 'product' ? 'product' : 'website');
    meta('property', 'og:title', title);
    meta('property', 'og:description', desc);
    meta('property', 'og:url', url);
    meta('property', 'og:locale', 'pt_BR');
    if (img) meta('property', 'og:image', img);
    meta('name', 'twitter:card', img ? 'summary_large_image' : 'summary');
    meta('name', 'twitter:title', title);
    meta('name', 'twitter:description', desc);
    if (img) meta('name', 'twitter:image', img);
    meta('name', 'theme-color', '#F8CD4B');
    meta('name', 'geo.region', 'BR-DF'); meta('name', 'geo.placename', 'Brasília');
    if (CFG.geo) meta('name', 'geo.position', CFG.geo.lat + ';' + CFG.geo.lng);
    var is404 = d.body.classList.contains('error404');
    if (is404) meta('name', 'robots', 'noindex, follow');
    if (!serverSeo && !is404) { var can = head.querySelector('link[rel="canonical"]'); if (!can) { can = d.createElement('link'); can.rel = 'canonical'; head.appendChild(can); } can.href = url; }
    if (CFG.favicon && !head.querySelector('link[rel="icon"]')) { var f = d.createElement('link'); f.rel = 'icon'; f.href = CFG.favicon; head.appendChild(f); var a = d.createElement('link'); a.rel = 'apple-touch-icon'; a.href = CFG.favicon; head.appendChild(a); }
    /* JSON-LD gerado em tempo de execução: sobrevive à migração de domínio */
    var org = {
      '@type': ['Organization', 'LocalBusiness', 'ProfessionalService'], '@id': origin + '/#organization', name: site, legalName: 'Trix Tecnologia Inteligente Ltda', alternateName: 'Trix TI',
      url: origin + '/', logo: logo, image: img || logo, foundingDate: '2009-07-10', taxID: '11.010.095/0001-40', vatID: '11.010.095/0001-40',
      telephone: CFG.phone, email: CFG.email, priceRange: '$$',
      address: { '@type': 'PostalAddress', streetAddress: 'SIG Quadra 4, Lote 75, Bloco A, Sala 15 – Edifício Capital Financial Center', addressLocality: 'Brasília', addressRegion: 'DF', postalCode: '70610-440', addressCountry: 'BR' },
      geo: CFG.geo ? { '@type': 'GeoCoordinates', latitude: CFG.geo.lat, longitude: CFG.geo.lng } : undefined,
      hasMap: CFG.mapsUrl, sameAs: CFG.social || [],
      contactPoint: [
        { '@type': 'ContactPoint', telephone: CFG.phone, contactType: 'customer service', areaServed: 'BR', availableLanguage: 'Portuguese' },
        { '@type': 'ContactPoint', telephone: '+55-800-941-1190', contactType: 'ombudsman', areaServed: 'BR' },
        { '@type': 'ContactPoint', email: 'dpo@trixti.com.br', contactType: 'data protection officer', areaServed: 'BR' }
      ],
      areaServed: 'BR', knowsAbout: ['Fábrica de software', 'Conectividade em saúde suplementar', 'TISS', 'LGPD', 'Reconhecimento facial', 'Oracle Cloud', 'Prontuário eletrônico']
    };
    var web = { '@type': 'WebSite', '@id': origin + '/#website', url: origin + '/', name: site, inLanguage: 'pt-BR', publisher: { '@id': origin + '/#organization' } };
    var page = { '@type': data.type === 'product' ? 'ItemPage' : (data.type === 'contact' ? 'ContactPage' : (data.type === 'about' ? 'AboutPage' : 'WebPage')), '@id': url + '#webpage', url: url, name: title, description: desc, inLanguage: 'pt-BR', isPartOf: { '@id': origin + '/#website' }, about: { '@id': origin + '/#organization' }, dateModified: data.modified || undefined };
    var graph = [org, web, page];
    if (data.breadcrumb && data.breadcrumb.length) {
      graph.push({ '@type': 'BreadcrumbList', itemListElement: data.breadcrumb.map(function (b, i) { return { '@type': 'ListItem', position: i + 1, name: b.name, item: origin + b.url }; }) });
    }
    if (data.type === 'product') {
      graph.push({ '@type': 'SoftwareApplication', name: data.productName || title, applicationCategory: data.category || 'BusinessApplication', operatingSystem: data.os || 'Web', description: desc, image: img || undefined, url: url, provider: { '@id': origin + '/#organization' } });
    }
    if (data.type === 'service') {
      graph.push({ '@type': 'Service', name: data.serviceName || title, serviceType: data.serviceType || title, description: desc, url: url, areaServed: 'BR', provider: { '@id': origin + '/#organization' } });
    }
    if (data.faq && data.faq.length) {
      graph.push({ '@type': 'FAQPage', mainEntity: data.faq.map(function (q) { return { '@type': 'Question', name: q.q, acceptedAnswer: { '@type': 'Answer', text: q.a } }; }) });
    }
    var ld = d.createElement('script'); ld.type = 'application/ld+json'; ld.textContent = JSON.stringify({ '@context': 'https://schema.org', '@graph': graph }); head.appendChild(ld);
  }

  /* ---------------- Header / megamenu ---------------- */
  function header() {
    var hd = $('.trix-header'); if (!hd) return;
    var onScroll = function () { hd.classList.toggle('is-scrolled', w.scrollY > 24); d.documentElement.classList.toggle('trix-scrolled', w.scrollY > Math.min(280, w.innerHeight * 0.35)); var t = $('.trix-top'); if (t) t.classList.toggle('is-visible', w.scrollY > 600); };
    on(w, 'scroll', onScroll, { passive: true }); onScroll();
    var items = $$('.trix-nav > li');
    function closeAll(except) { items.forEach(function (li) { if (li !== except) { li.classList.remove('is-open'); var b = $('.trix-nav__link[aria-expanded]', li); if (b) b.setAttribute('aria-expanded', 'false'); } }); }
    items.forEach(function (li) {
      var btn = $('button.trix-nav__link', li), mega = $('.trix-mega', li); if (!btn || !mega) return;
      var open = function () { closeAll(li); li.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true'); };
      var close = function () { li.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); };
      var viaHover = false, timer;
      on(btn, 'click', function (e) { e.preventDefault(); if (viaHover && li.classList.contains('is-open')) { viaHover = false; return; } viaHover = false; li.classList.contains('is-open') ? close() : open(); });
      on(li, 'pointerenter', function (e) { if (e.pointerType !== 'mouse') return; clearTimeout(timer); if (!li.classList.contains('is-open')) { open(); viaHover = true; } });
      on(li, 'pointerleave', function (e) { if (e.pointerType !== 'mouse') return; timer = setTimeout(function () { close(); viaHover = false; }, 180); });
      on(li, 'focusout', function (e) { if (!li.contains(e.relatedTarget)) close(); });
    });
    on(d, 'keydown', function (e) { if (e.key === 'Escape') { var openLi = $('.trix-nav > li.is-open'); closeAll(); if (openLi) { var ob = $('button.trix-nav__link', openLi); if (ob) ob.focus(); } if (drawer && drawer.classList.contains('is-open')) { drawerClose(); burger.focus(); } } });
    ['click', 'pointerdown'].forEach(function (t) { on(d, t, function (e) { if (!e.target.closest('.trix-nav')) closeAll(); }); });
    /* marca item atual */
    var path = location.pathname.replace(/\/+$/, '') || '/';
    $$('.trix-nav a, .trix-drawer a').forEach(function (a) { var p = (a.getAttribute('href') || '').replace(/\/+$/, '') || '/'; if (p === path) { a.setAttribute('aria-current', 'page'); var li = a.closest('.trix-nav > li'); if (li) li.classList.add('is-current'); } });
    /* drawer mobile */
    var burger = $('.trix-burger'), drawer = $('.trix-drawer'), back = $('.trix-drawer__backdrop');
    function drawerClose() { if (!drawer) return; drawer.classList.remove('is-open'); back.classList.remove('is-open'); burger.setAttribute('aria-expanded', 'false'); d.body.style.overflow = ''; d.documentElement.style.overflow = ''; }
    on(burger, 'click', function () { var o = drawer.classList.toggle('is-open'); back.classList.toggle('is-open', o); burger.setAttribute('aria-expanded', o ? 'true' : 'false'); d.body.style.overflow = o ? 'hidden' : ''; d.documentElement.style.overflow = o ? 'hidden' : ''; });
    on(back, 'click', drawerClose);
    /* âncoras com offset do header fixo */
    on(d, 'click', function (e) { var a = e.target.closest('a[href^="#"]'); if (!a || a.getAttribute('href') === '#') return; var t = $(a.getAttribute('href')); if (!t) return; e.preventDefault(); var y = t.getBoundingClientRect().top + w.scrollY - 90; w.scrollTo({ top: y, behavior: calm() ? 'auto' : 'smooth' }); t.setAttribute('tabindex', '-1'); t.focus({ preventScroll: true }); });
    on($('.trix-top'), 'click', function () { w.scrollTo({ top: 0, behavior: calm() ? 'auto' : 'smooth' }); });
  }

  /* ---------------- Consentimento de cookies (estilo CookieYes) ---------------- */
  var CONSENT_KEY = 'trix_cookie_consent';
  function readConsent() { try { var m = d.cookie.match(new RegExp('(?:^|; )' + CONSENT_KEY + '=([^;]*)')); if (m) return JSON.parse(decodeURIComponent(m[1])); var l = localStorage.getItem(CONSENT_KEY); return l ? JSON.parse(l) : null; } catch (e) { return null; } }
  function writeConsent(c) { c.ts = new Date().toISOString(); c.v = 1; var v = encodeURIComponent(JSON.stringify(c)); d.cookie = CONSENT_KEY + '=' + v + '; path=/; max-age=' + (60 * 60 * 24 * 180) + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); try { localStorage.setItem(CONSENT_KEY, JSON.stringify(c)); } catch (e) { } applyConsent(c); }
  function applyConsent(c) {
    w.dataLayer = w.dataLayer || []; function gtag() { w.dataLayer.push(arguments); } w.gtag = w.gtag || gtag;
    var g = function (b) { return b ? 'granted' : 'denied'; };
    w.gtag('consent', 'update', { analytics_storage: g(c.analytics), ad_storage: g(c.marketing), ad_user_data: g(c.marketing), ad_personalization: g(c.marketing), functionality_storage: g(c.functional), personalization_storage: g(c.functional), security_storage: 'granted' });
    if (c.analytics && CFG.ga4 && !w.__trixGA) { w.__trixGA = true; var s = d.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + CFG.ga4; d.head.appendChild(s); w.gtag('js', new Date()); w.gtag('config', CFG.ga4, { anonymize_ip: true }); }
    d.dispatchEvent(new CustomEvent('trix:consent', { detail: c }));
    var b = $('.trix-cookiebtn'); if (b) b.classList.add('is-visible');
  }
  function cookies() {
    w.dataLayer = w.dataLayer || []; w.gtag = w.gtag || function () { w.dataLayer.push(arguments); };
    w.gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', functionality_storage: 'denied', personalization_storage: 'denied', security_storage: 'granted', wait_for_update: 500 });
    var banner = $('.trix-cookie'), modal = $('#trix-cookie-modal'); if (!banner) return;
    var saved = readConsent();
    if (saved) applyConsent(saved); else setTimeout(function () { banner.classList.add('is-visible'); d.documentElement.classList.add('trix-cookie-open'); }, 900);
    function hide() { banner.classList.remove('is-visible'); d.documentElement.classList.remove('trix-cookie-open'); closeModal(); }
    function all(v) { return { necessary: true, functional: v, analytics: v, marketing: v }; }
    on($('[data-cookie="accept"]', banner), 'click', function () { writeConsent(all(true)); hide(); });
    on($('[data-cookie="reject"]', banner), 'click', function () { writeConsent(all(false)); hide(); });
    var opener = null;
    function closeModal() { if (!modal) return; modal.classList.remove('is-open'); d.documentElement.style.overflow = ''; if (opener && opener.focus) opener.focus(); }
    function openModal() { if (!modal) return; opener = d.activeElement; d.documentElement.style.overflow = 'hidden'; var c = readConsent() || all(false); ['functional', 'analytics', 'marketing'].forEach(function (k) { var i = $('input[name="' + k + '"]', modal); if (i) i.checked = !!c[k]; }); modal.classList.add('is-open'); var f = $('input:not([disabled])', modal); if (f) f.focus(); }
    on(d, 'keydown', function (e) { if (!modal || !modal.classList.contains('is-open')) return; if (e.key === 'Escape') { closeModal(); return; } if (e.key === 'Tab') { var f = $$('button, input:not([disabled]), a[href]', modal).filter(function (x) { return x.offsetParent !== null; }); if (!f.length) return; var first = f[0], last = f[f.length - 1]; if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); } } });
    on($('[data-cookie="custom"]', banner), 'click', openModal);
    on($('.trix-cookiebtn'), 'click', openModal);
    $$('[data-cookie="open"]').forEach(function (b) { on(b, 'click', function (e) { e.preventDefault(); openModal(); }); });
    if (modal) {
      on($('[data-cookie="save"]', modal), 'click', function () { var c = { necessary: true }; ['functional', 'analytics', 'marketing'].forEach(function (k) { var i = $('input[name="' + k + '"]', modal); c[k] = !!(i && i.checked); }); writeConsent(c); hide(); });
      on($('[data-cookie="accept"]', modal), 'click', function () { writeConsent(all(true)); hide(); });
      on($('[data-cookie="close"]', modal), 'click', closeModal);
      on(modal, 'click', function (e) { if (e.target === modal) closeModal(); });
    }
  }

  /* ---------------- Acessibilidade ---------------- */
  function a11y() {
    var btns = $$('.trix-a11y-btn, .trix-a11y-hbtn'), panel = $('.trix-a11y'); if (!btns.length || !panel) return;
    var btn = btns[0];
    function expand(o) { btns.forEach(function (b) { b.setAttribute('aria-expanded', o ? 'true' : 'false'); }); }
    function closePanel() { panel.classList.remove('is-open'); expand(false); btn.focus(); }
    var KEY = 'trix_a11y', root = d.documentElement, state = {};
    try { state = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { state = {}; }
    var guide = d.createElement('div'); guide.className = 'trix-readguide'; d.body.appendChild(guide);
    function moveGuide(e) { if (root.classList.contains('a11y-guide')) guide.style.top = (e.clientY - 7) + 'px'; }
    on(d, 'pointermove', function (e) { if (e.pointerType === 'mouse') moveGuide(e); }); on(d, 'pointerdown', moveGuide);
    function apply() {
      ['contrast', 'gray', 'font', 'space', 'links', 'cursor', 'motion', 'guide'].forEach(function (k) { root.classList.toggle('a11y-' + k, !!state[k]); var b = $('[data-a11y="' + k + '"]', panel); if (b) b.setAttribute('aria-pressed', state[k] ? 'true' : 'false'); });
      var size = Math.max(-2, Math.min(4, state.size || 0)); root.style.fontSize = size ? (100 + size * 12.5) + '%' : ''; var lbl = $('[data-a11y-size]', panel); if (lbl) lbl.textContent = size ? (size > 0 ? '+' : '') + size : 'A';
      try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { }
    }
    apply();
    btns.forEach(function (b) { on(b, 'click', function () { btn = b; var o = panel.classList.toggle('is-open'); expand(o); if (o) { var f = $('button', panel); if (f) f.focus(); } }); });
    on($('[data-a11y="close"]', panel), 'click', closePanel);
    $$('[data-a11y]', panel).forEach(function (b) {
      var k = b.getAttribute('data-a11y');
      on(b, 'click', function () {
        if (k === 'close') return;
        if (k === 'bigger') state.size = (state.size || 0) + 1; else if (k === 'smaller') state.size = (state.size || 0) - 1;
        else if (k === 'reset') state = {}; else if (k === 'libras') { vlibras(true); return; }
        else state[k] = !state[k];
        apply();
      });
    });
    on(d, 'keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open')) closePanel(); });
    ['click', 'pointerdown'].forEach(function (t) { on(d, t, function (e) { if (panel.classList.contains('is-open') && !panel.contains(e.target) && !e.target.closest('.trix-a11y-btn, .trix-a11y-hbtn')) { panel.classList.remove('is-open'); expand(false); } }); });
  }

  /* ---------------- VLibras (tradução para Libras) ---------------- */
  var vlLoaded = false;
  function vlibras(openNow) {
    if (vlLoaded) { var b = $('[vw-access-button]'); if (openNow && b) b.click(); return; }
    vlLoaded = true;
    if (!$('[vw]')) { var box = d.createElement('div'); box.setAttribute('vw', ''); box.className = 'enabled'; box.innerHTML = '<div vw-access-button class="active"></div><div vw-plugin-wrapper><div class="vw-plugin-top-wrapper"></div></div>'; d.body.appendChild(box); }
    var s = d.createElement('script'); s.src = 'https://vlibras.gov.br/app/vlibras-plugin.js'; s.async = true;
    s.onload = function () { try { new w.VLibras.Widget('https://vlibras.gov.br/app'); if (openNow) setTimeout(function () { var b = $('[vw-access-button]'); if (b) b.click(); }, 800); } catch (e) { } };
    d.body.appendChild(s);
  }

  /* ---------------- Contatos flutuantes ---------------- */
  function fab() {
    var f = $('.trix-fab'); if (!f) return; var b = $('.trix-fab__main', f);
    on(b, 'click', function () { var o = f.classList.toggle('is-open'); b.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    ['click', 'pointerdown'].forEach(function (t) { on(d, t, function (e) { if (!f.contains(e.target)) { f.classList.remove('is-open'); b.setAttribute('aria-expanded', 'false'); } }); });
  }

  /* ---------------- Formulários (captcha matemático + honeypot + envio) ---------------- */
  function forms() {
    $$('form.trix-form').forEach(function (form) {
      var cap = $('.trix-captcha', form), q = $('.trix-captcha__q', form), inp = $('input[name="trix_captcha"]', form), answer = 0;
      function newCaptcha() { var a = Math.floor(Math.random() * 9) + 1, b = Math.floor(Math.random() * 9) + 1, plus = Math.random() < .5; answer = plus ? a + b : a * b; if (q) q.innerHTML = 'Quanto é <span>' + a + '</span> <span aria-label="' + (plus ? 'mais' : 'vezes') + '">' + (plus ? '+' : '×') + '</span> <span>' + b + '</span> ?'; if (inp) inp.value = ''; }
      newCaptcha(); on($('button', cap), 'click', function (e) { e.preventDefault(); newCaptcha(); });
      var started = Date.now();
      on(form, 'submit', function (e) {
        e.preventDefault();
        var msg = $('.trix-form__msg', form), btn = $('button[type="submit"]', form);
        function show(ok, html) { msg.className = 'trix-form__msg ' + (ok ? 'is-ok' : 'is-err'); msg.textContent = ''; requestAnimationFrame(function () { msg.innerHTML = html; msg.scrollIntoView({ block: 'nearest', behavior: calm() ? 'auto' : 'smooth' }); }); }
        if (!form.checkValidity()) { form.reportValidity(); return; }
        var hp = $('input[name="website_url"]', form); if ((hp && hp.value) || Date.now() - started < 2500) { show(false, 'Não foi possível validar o envio. Tente novamente.'); return; }
        if (parseInt(inp.value, 10) !== answer) { show(false, 'A resposta da verificação anti-spam está incorreta. Tente novamente.'); newCaptcha(); inp.focus(); return; }
        var consent = $('input[name="consent"]', form); if (consent && !consent.checked) { show(false, 'É necessário concordar com a Política de Privacidade para enviar.'); return; }
        var kind = form.getAttribute('data-form') || 'contato', lines = [], name = '', email = '';
        var anon = !!$('input[name="identificacao"][value="anonima"]:checked', form);
        var proto = 'TRIX-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + Math.random().toString(36).slice(2, 6).toUpperCase();
        lines.push('[' + kind.toUpperCase() + '] Protocolo ' + proto);
        $$('[name]', form).forEach(function (el) {
          var n = el.name; if (['trix_captcha', 'website_url', 'consent'].indexOf(n) >= 0) return; if (anon && ['nome', 'email', 'telefone'].indexOf(n) >= 0) return; if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) return;
          var label = (form.querySelector('label[for="' + el.id + '"]') || {}).textContent || n; label = label.replace('*', '').trim();
          var v = el.value.trim(); if (!v) return; if (n === 'nome') name = v; if (n === 'email') email = v; lines.push(label + ': ' + v);
        });
        lines.push('Página: ' + location.pathname); lines.push('Enviado em: ' + new Date().toLocaleString('pt-BR'));
        var mailto = 'mailto:' + (form.getAttribute('data-mail') || CFG.email) + '?subject=' + encodeURIComponent('[' + kind + '] ' + proto) + '&body=' + encodeURIComponent(lines.join('\n'));
        var body = new URLSearchParams();
        body.set('comment', lines.join('\n')); body.set('author', anon ? 'Relato anônimo' : (name || 'Visitante')); body.set('email', (!anon && email) ? email : (CFG.anonEmail || 'anonimo@example.com')); body.set('url', '');
        var pid = (d.body.className.match(/page-id-(\d+)/) || [])[1] || form.getAttribute('data-post'); if (!pid) { show(false, 'Não foi possível identificar o destino do formulário. <a href="' + mailto + '">Envie por e-mail</a>.'); return; } body.set('comment_post_ID', pid); body.set('comment_parent', '0');
        btn.disabled = true; btn.dataset.label = btn.textContent; btn.textContent = 'Enviando…';
        fetch((CFG.formEndpoint || '/wp-comments-post.php'), { method: 'POST', body: body, credentials: 'omit', redirect: 'follow', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' } })
          .then(function (r) { if (!r.ok || (!CFG.formEndpoint && !r.redirected)) throw new Error('HTTP ' + r.status); return r.text(); })
          .then(function () { show(true, '<strong>Mensagem enviada com sucesso!</strong> Seu protocolo é <strong>' + proto + '</strong>. Guarde este número para acompanhamento. ' + (kind === 'contato' ? 'Nossa equipe retornará o mais breve possível, em dias úteis.' : 'Sua manifestação será analisada com confidencialidade pelo comitê responsável.')); form.reset(); newCaptcha(); })
          .catch(function () { show(false, 'Não conseguimos registrar sua mensagem automaticamente. <a href="' + mailto + '">Clique aqui para enviar por e-mail</a> com o protocolo ' + proto + ' ou tente novamente em instantes.'); })
          .then(function () { btn.disabled = false; btn.textContent = btn.dataset.label; });
      });
    });
    /* preenche assunto via ?assunto= */
    var p = new URLSearchParams(location.search); var pre = p.get('assunto') || p.get('tipo');
    if (pre) { var sel = $('select[name="assunto"], select[name="tipo"]'); if (sel) { $$('option', sel).forEach(function (o) { if (o.value.toLowerCase() === pre.toLowerCase()) sel.value = o.value; }); } }
  }

  /* ---------------- Mapa sob demanda (respeita consentimento) ---------------- */
  function maps() {
    $$('.trix-map[data-map]').forEach(function (m) {
      function load() { if (m.dataset.loaded) return; m.dataset.loaded = '1'; var fr = d.createElement('i' + 'frame'); fr.src = m.getAttribute('data-map'); fr.loading = 'lazy'; fr.title = m.getAttribute('data-title') || 'Mapa'; fr.setAttribute('allowfullscreen', ''); fr.referrerPolicy = 'no-referrer-when-downgrade'; m.appendChild(fr); var ph = $('.trix-map__ph', m); if (ph) ph.style.display = 'none'; }
      on($('[data-map-load]', m), 'click', load);
      var c = readConsent(); if (c && c.functional) load();
      on(d, 'trix:consent', function (e) { if (e.detail.functional) load(); });
    });
  }

  /* ---------------- Efeitos: reveal, tilt ---------------- */
  function effects() {
    if ('IntersectionObserver' in w && !reduced) {
      var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } }); }, { threshold: .12 });
      $$('.trix-reveal').forEach(function (el) { io.observe(el); });
      /* fallback: garante conteúdo visível mesmo sem rolagem (impressão, leitores, renderizadores) */
      setTimeout(function () { d.documentElement.classList.add('trix-revealed'); }, 1800);
      on(w, 'beforeprint', function () { d.documentElement.classList.add('trix-revealed'); });
    } else $$('.trix-reveal').forEach(function (el) { el.classList.add('is-in'); });
    if (!reduced && w.matchMedia('(hover:hover)').matches) {
      $$('.trix-tilt').forEach(function (card) {
        if (!$('.trix-tilt__glare', card)) { var g = d.createElement('span'); g.className = 'trix-tilt__glare'; card.appendChild(g); }
        on(card, 'mousemove', function (e) { var r = card.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height; card.style.transform = 'perspective(900px) rotateX(' + ((.5 - y) * 10) + 'deg) rotateY(' + ((x - .5) * 12) + 'deg) translateY(-6px)'; card.style.setProperty('--gx', (x * 100) + '%'); card.style.setProperty('--gy', (y * 100) + '%'); });
        on(card, 'mouseleave', function () { card.style.transform = ''; });
      });
    }
    /* contadores */
    $$('[data-count]').forEach(function (el) {
      var raw = el.getAttribute('data-count'), target = parseFloat(raw), dec = (raw.split('.')[1] || '').length, suffix = el.getAttribute('data-suffix') || '', dur = 1400, fmt = function (v) { return v.toFixed(dec).replace('.', ',') + suffix; }, run = function () { if (reduced) { el.textContent = fmt(target); return; } var t0 = null; function step(t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / dur); el.textContent = fmt(target * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(step); } requestAnimationFrame(step); };
      if ('IntersectionObserver' in w) { var o = new IntersectionObserver(function (es) { if (es[0].isIntersecting) { run(); o.disconnect(); } }); o.observe(el); } else run();
    });
  }

  /* ---------------- 3D (Three.js): cenas temáticas na coluna visual do banner ---------------- */
  function webgl() { try { var c = d.createElement('canvas'); return !!(w.WebGLRenderingContext && (c.getContext('webgl') || c.getContext('experimental-webgl'))); } catch (e) { return false; } }
  function three() {
    var hosts = $$('[data-trix-3d]'); if (!hosts.length || !webgl()) return;
    var urls = [CFG.threeUrl || 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js', 'https://cdn.jsdelivr.net/npm/three@0.128.0/build/three.min.js'];
    function go() { hosts.forEach(function (h) { try { scene(h); } catch (e) { if (w.console) console.warn('trix 3d', e); } }); }
    if (w.THREE) return go();
    (function load(i) { if (i >= urls.length) return; var s = d.createElement('script'); s.src = urls[i]; s.async = true; s.crossOrigin = 'anonymous'; s.onload = function () { if (w.THREE) go(); }; s.onerror = function () { load(i + 1); }; d.head.appendChild(s); })(0);
  }
  var YEL = 0xF8CD4B, GOLD = 0xA08E5D, GRAY = 0x424346, DARK = 0x2B2C2F, WHITE = 0xFFFFFF;
  var SCENES = {
    network: function (T, g, q) {
      var N = Math.round(170 * q), R = 8.5, pos = new Float32Array(N * 3), vel = [];
      for (var i = 0; i < N; i++) { var v = new T.Vector3().randomDirection ? new T.Vector3().randomDirection() : new T.Vector3(Math.random() - .5, Math.random() - .5, Math.random() - .5).normalize(); v.multiplyScalar(R * Math.cbrt(Math.random())); pos.set([v.x, v.y, v.z], i * 3); vel.push(new T.Vector3((Math.random() - .5) * .02, (Math.random() - .5) * .02, (Math.random() - .5) * .02)); }
      var geo = new T.BufferGeometry(); geo.setAttribute('position', new T.BufferAttribute(pos, 3));
      g.add(new T.Points(geo, new T.PointsMaterial({ color: YEL, size: .28, transparent: true, opacity: 1 })));
      var maxL = N * 5, lp = new Float32Array(maxL * 6), lg = new T.BufferGeometry(); lg.setAttribute('position', new T.BufferAttribute(lp, 3));
      g.add(new T.LineSegments(lg, new T.LineBasicMaterial({ color: WHITE, transparent: true, opacity: .32 })));
      var core = new T.Mesh(new T.SphereGeometry(1.9, 40, 30), new T.MeshStandardMaterial({ color: YEL, emissive: YEL, emissiveIntensity: .35, metalness: .3, roughness: .3 })); g.add(core);
      var coreRing = new T.Mesh(new T.TorusGeometry(2.7, .05, 8, 90), new T.MeshBasicMaterial({ color: WHITE, transparent: true, opacity: .45 })); g.add(coreRing);
      var shell = new T.Mesh(new T.IcosahedronGeometry(4.2, 1), new T.MeshBasicMaterial({ color: YEL, wireframe: true, transparent: true, opacity: .28 })); g.add(shell);
      return function (dt) {
        var p = geo.attributes.position.array, k = 0, i, j;
        for (i = 0; i < N; i++) { p[i * 3] += vel[i].x; p[i * 3 + 1] += vel[i].y; p[i * 3 + 2] += vel[i].z; var l = Math.hypot(p[i * 3], p[i * 3 + 1], p[i * 3 + 2]); if (l > R) { vel[i].multiplyScalar(-1); } }
        for (i = 0; i < N && k < maxL; i++) for (j = i + 1; j < N && k < maxL; j++) { var dx = p[i * 3] - p[j * 3], dy = p[i * 3 + 1] - p[j * 3 + 1], dz = p[i * 3 + 2] - p[j * 3 + 2]; if (dx * dx + dy * dy + dz * dz < 6.5) { lp.set([p[i * 3], p[i * 3 + 1], p[i * 3 + 2], p[j * 3], p[j * 3 + 1], p[j * 3 + 2]], k * 6); k++; } }
        lg.setDrawRange(0, k * 2); lg.attributes.position.needsUpdate = true; geo.attributes.position.needsUpdate = true;
        coreRing.rotation.x += dt * .5; coreRing.rotation.y += dt * .3; shell.rotation.y += dt * .1; shell.rotation.z -= dt * .06;
      };
    },
    globe: function (T, g, q) {
      var R = 5.6, N = Math.round(900 * q), pos = new Float32Array(N * 3);
      for (var i = 0; i < N; i++) { var y = 1 - (i / (N - 1)) * 2, r = Math.sqrt(1 - y * y), th = i * 2.399963; pos.set([Math.cos(th) * r * R, y * R, Math.sin(th) * r * R], i * 3); }
      var geo = new T.BufferGeometry(); geo.setAttribute('position', new T.BufferAttribute(pos, 3));
      g.add(new T.Points(geo, new T.PointsMaterial({ color: YEL, size: .13, transparent: true, opacity: 1 })));
      g.add(new T.Mesh(new T.SphereGeometry(R * .985, 32, 24), new T.MeshStandardMaterial({ color: 0x3A3B3F, emissive: 0x1A1A1C, metalness: .3, roughness: .7, transparent: true, opacity: .9 })));
      g.add(new T.Mesh(new T.SphereGeometry(R * 1.002, 24, 16), new T.MeshBasicMaterial({ color: WHITE, wireframe: true, transparent: true, opacity: .07 })));
      var arcs = new T.Group(); g.add(arcs);
      function pt() { var u = Math.random() * 2 - 1, t = Math.random() * Math.PI * 2, r = Math.sqrt(1 - u * u); return new T.Vector3(Math.cos(t) * r * R, u * R, Math.sin(t) * r * R); }
      for (var a = 0; a < Math.round(14 * q + 4); a++) { var p1 = pt(), p2 = pt(), mid = p1.clone().add(p2).multiplyScalar(.5).normalize().multiplyScalar(R * 1.45); var c = new T.QuadraticBezierCurve3(p1, mid, p2); arcs.add(new T.Line(new T.BufferGeometry().setFromPoints(c.getPoints(40)), new T.LineBasicMaterial({ color: a % 3 ? WHITE : YEL, transparent: true, opacity: a % 3 ? .25 : .7 }))); }
      var ring = new T.Mesh(new T.TorusGeometry(R * 1.35, .03, 8, 120), new T.MeshBasicMaterial({ color: YEL, transparent: true, opacity: .5 })); ring.rotation.x = Math.PI / 2.4; g.add(ring);
      var sat = new T.Mesh(new T.SphereGeometry(.22, 16, 12), new T.MeshBasicMaterial({ color: YEL })); g.add(sat); var t0 = 0;
      g.rotation.z = .35;
      return function (dt) { t0 += dt; g.children[0].rotation.y += dt * .08; g.children[1].rotation.y += dt * .08; g.children[2].rotation.y += dt * .08; arcs.rotation.y += dt * .08; var a = t0 * .6; sat.position.set(Math.cos(a) * R * 1.35, Math.sin(a) * R * 1.35 * Math.cos(Math.PI / 2.4), Math.sin(a) * R * 1.35 * Math.sin(Math.PI / 2.4) * -1); };
    },
    cubes: function (T, g) {
      var cubes = [], geo = new T.BoxGeometry(1.7, 1.7, 1.7), eg = new T.EdgesGeometry(geo);
      for (var x = -1; x <= 1; x++) for (var y = -1; y <= 1; y++) for (var z = -1; z <= 1; z++) {
        var hl = Math.random() < .28, m = new T.Mesh(geo, new T.MeshStandardMaterial({ color: hl ? YEL : GRAY, metalness: .45, roughness: .35 }));
        m.add(new T.LineSegments(eg, new T.LineBasicMaterial({ color: hl ? DARK : YEL, transparent: true, opacity: .55 })));
        m.userData.base = new T.Vector3(x, y, z).multiplyScalar(2.05); m.userData.ph = Math.random() * 6.28; m.position.copy(m.userData.base); g.add(m); cubes.push(m);
      }
      g.rotation.set(.5, .6, 0); var t = 0;
      return function (dt) { t += dt; var e = (Math.sin(t * .9) + 1) * .5; cubes.forEach(function (m) { m.position.copy(m.userData.base).multiplyScalar(1 + e * .22 + Math.sin(t * 1.4 + m.userData.ph) * .02); m.rotation.x += dt * .15; }); g.rotation.y += dt * .25; };
    },
    shield: function (T, g, q) {
      var s = new T.Shape(); s.moveTo(0, 4.2); s.bezierCurveTo(1.8, 3.3, 3.2, 3.4, 3.8, 3.2); s.bezierCurveTo(3.8, .2, 3, -2.8, 0, -4.4); s.bezierCurveTo(-3, -2.8, -3.8, .2, -3.8, 3.2); s.bezierCurveTo(-3.2, 3.4, -1.8, 3.3, 0, 4.2);
      var body = new T.Mesh(new T.ExtrudeGeometry(s, { depth: .9, bevelEnabled: true, bevelThickness: .25, bevelSize: .22, bevelSegments: 3, curveSegments: 24 }), new T.MeshStandardMaterial({ color: GRAY, metalness: .7, roughness: .28 }));
      body.geometry.center(); g.add(body);
      var edge = new T.LineSegments(new T.EdgesGeometry(body.geometry, 30), new T.LineBasicMaterial({ color: YEL, transparent: true, opacity: .9 })); g.add(edge);
      var ck = new T.Shape(); ck.moveTo(-1.6, .1); ck.lineTo(-.5, -1); ck.lineTo(1.8, 1.4); ck.lineTo(1.3, 1.9); ck.lineTo(-.5, .05); ck.lineTo(-1.1, .65); ck.lineTo(-1.6, .1);
      var check = new T.Mesh(new T.ExtrudeGeometry(ck, { depth: .35, bevelEnabled: true, bevelThickness: .08, bevelSize: .06, bevelSegments: 2 }), new T.MeshStandardMaterial({ color: YEL, emissive: YEL, emissiveIntensity: .35, metalness: .3, roughness: .4 }));
      check.position.z = .75; check.position.y = .15; g.add(check);
      var N = Math.round(260 * q), pos = new Float32Array(N * 3);
      for (var i = 0; i < N; i++) { var a = Math.random() * 6.283, r = 5.8 + Math.random() * 1.6; pos.set([Math.cos(a) * r, (Math.random() - .5) * 1.4, Math.sin(a) * r], i * 3); }
      var ring = new T.Points(new T.BufferGeometry().setAttribute('position', new T.BufferAttribute(pos, 3)), new T.PointsMaterial({ color: YEL, size: .09, transparent: true, opacity: .8 })); ring.rotation.x = .35; g.add(ring);
      var halo = new T.Mesh(new T.TorusGeometry(6.2, .025, 8, 140), new T.MeshBasicMaterial({ color: WHITE, transparent: true, opacity: .25 })); halo.rotation.x = Math.PI / 2 + .35; g.add(halo);
      var t = 0;
      return function (dt) { t += dt; body.rotation.y = edge.rotation.y = check.rotation.y = Math.sin(t * .6) * .45; ring.rotation.y += dt * .25; check.material.emissiveIntensity = .3 + Math.sin(t * 2.2) * .15; };
    },
    pulse: function (T, g, q) {
      var heart = new T.Mesh(new T.SphereGeometry(2.2, 48, 36), new T.MeshStandardMaterial({ color: YEL, emissive: YEL, emissiveIntensity: .3, metalness: .25, roughness: .35 })); g.add(heart);
      var wire = new T.Mesh(new T.IcosahedronGeometry(3.3, 1), new T.MeshBasicMaterial({ color: YEL, wireframe: true, transparent: true, opacity: .3 })); g.add(wire);
      var M = 220, lp = new Float32Array(M * 3), line = new T.Line(new T.BufferGeometry().setAttribute('position', new T.BufferAttribute(lp, 3)), new T.LineBasicMaterial({ color: WHITE })); line.position.z = 3.8; g.add(line);
      function ecg(x) { var u = ((x % 1) + 1) % 1; if (u < .40) return 0; if (u < .44) return (u - .40) * 12; if (u < .47) return .48 - (u - .44) * 50; if (u < .50) return -1.02 + (u - .47) * 60; if (u < .54) return .78 - (u - .50) * 19.5; if (u < .64) return Math.sin((u - .54) * 31.4) * .25; return 0; }
      var orb = new T.Group(); g.add(orb);
      for (var i = 0; i < Math.round(40 * q + 10); i++) { var dot = new T.Mesh(new T.SphereGeometry(.07 + Math.random() * .08, 8, 6), new T.MeshBasicMaterial({ color: i % 4 ? WHITE : YEL, transparent: true, opacity: .8 })); var a = Math.random() * 6.28, r = 4.6 + Math.random() * 2; dot.position.set(Math.cos(a) * r, (Math.random() - .5) * 5, Math.sin(a) * r); orb.add(dot); }
      var t = 0;
      return function (dt) { t += dt; for (var i = 0; i < M; i++) { var x = i / M; lp.set([-7 + x * 14, ecg(x * 1.2 - t * .45) * 2.6, 0], i * 3); } line.geometry.attributes.position.needsUpdate = true; var b = 1 + Math.max(0, Math.sin(t * 5.6)) * .08; heart.scale.set(b, b, b); heart.rotation.y += dt * .3; wire.rotation.y -= dt * .15; orb.rotation.y += dt * .2; };
    },
    face: function (T, g, q) {
      var N = Math.round(2600 * q), pos = new Float32Array(N * 3), col = new Float32Array(N * 3), c1 = new T.Color(YEL), c2 = new T.Color(0xD9D9DC);
      for (var i = 0; i < N; i++) { var y = 1 - (i / (N - 1)) * 2, r = Math.sqrt(1 - y * y), th = i * 2.399963, x = Math.cos(th) * r, z = Math.sin(th) * r; var px = x * 3.1, py = y * 4.1, pz = z * 3.3; if (z > .55 && Math.abs(x) < .18 && y > -.35 && y < .25) pz += .8 * (1 - Math.abs(x) / .18); if (z > .5 && Math.abs(Math.abs(x) - .38) < .12 && y > .12 && y < .3) pz -= .25; pos.set([px, py, pz], i * 3); col.set([c2.r, c2.g, c2.b], i * 3); }
      var geo = new T.BufferGeometry(); geo.setAttribute('position', new T.BufferAttribute(pos, 3)); geo.setAttribute('color', new T.BufferAttribute(col, 3));
      var head = new T.Points(geo, new T.PointsMaterial({ size: .13, vertexColors: true })); g.add(head);
      var scan = new T.Mesh(new T.PlaneGeometry(9, .06), new T.MeshBasicMaterial({ color: YEL, transparent: true, opacity: .85, side: T.DoubleSide })); g.add(scan);
      var frame = new T.Group(); g.add(frame);
      [[-1, 1], [1, 1], [-1, -1], [1, -1]].forEach(function (s) { var pts = [new T.Vector3(s[0] * 4.6, s[1] * 3.6, 3.4), new T.Vector3(s[0] * 4.6, s[1] * 5.2, 3.4), new T.Vector3(s[0] * 3, s[1] * 5.2, 3.4)]; frame.add(new T.Line(new T.BufferGeometry().setFromPoints(pts), new T.LineBasicMaterial({ color: YEL }))); });
      var t = 0;
      return function (dt) { t += dt; var sy = Math.sin(t * 1.1) * 4.1; scan.position.set(0, sy, 0); var p = geo.attributes.position.array, c = geo.attributes.color.array; for (var i = 0; i < N; i++) { var dd = Math.abs(p[i * 3 + 1] - sy), k = Math.max(0, 1 - dd / .7); c[i * 3] = c2.r + (c1.r - c2.r) * k; c[i * 3 + 1] = c2.g + (c1.g - c2.g) * k; c[i * 3 + 2] = c2.b + (c1.b - c2.b) * k; } geo.attributes.color.needsUpdate = true; head.rotation.y = Math.sin(t * .4) * .6; };
    },
    docs: function (T, g) {
      var docs = [], geo = new T.BoxGeometry(4.2, 5.6, .08);
      for (var i = 0; i < 6; i++) { var m = new T.Mesh(geo, new T.MeshStandardMaterial({ color: i === 5 ? WHITE : (i % 2 ? 0xE4E0D6 : 0xF1EEE6), metalness: .05, roughness: .7 })); m.add(new T.LineSegments(new T.EdgesGeometry(geo), new T.LineBasicMaterial({ color: i === 5 ? YEL : GOLD, transparent: true, opacity: .8 })));
        for (var l = 0; l < 7; l++) { var bar = new T.Mesh(new T.BoxGeometry(l === 0 ? 2.2 : 3.2 - Math.random() * 1.2, .16, .02), new T.MeshBasicMaterial({ color: l === 0 ? YEL : 0x8A8B90 })); bar.position.set(l === 0 ? -.8 : -0.3, 2.1 - l * .6, .06); m.add(bar); }
        m.userData.i = i; g.add(m); docs.push(m); }
      var t = 0;
      return function (dt) { t += dt; var spread = (Math.sin(t * .8) + 1) * .5; docs.forEach(function (m) { var k = m.userData.i - 2.5; m.position.set(k * .35 * spread, k * .1, k * (.25 + spread * .35)); m.rotation.set(-.15, .4 + k * .12 * spread, k * .05 * spread); }); g.rotation.y = Math.sin(t * .35) * .3; };
    },
    gears: function (T, g) {
      function gear(r, teeth, color) { var s = new T.Shape(), n = teeth * 2; for (var i = 0; i <= n; i++) { var a = i / n * Math.PI * 2, rr = i % 2 ? r * .82 : r; var a1 = a - Math.PI / n * .45, a2 = a + Math.PI / n * .45; if (i === 0) s.moveTo(Math.cos(a1) * rr, Math.sin(a1) * rr); else s.lineTo(Math.cos(a1) * rr, Math.sin(a1) * rr); s.lineTo(Math.cos(a2) * rr, Math.sin(a2) * rr); } var h = new T.Path(); h.absarc(0, 0, r * .3, 0, Math.PI * 2, true); s.holes.push(h);
        var m = new T.Mesh(new T.ExtrudeGeometry(s, { depth: .7, bevelEnabled: true, bevelThickness: .1, bevelSize: .08, bevelSegments: 2 }), new T.MeshStandardMaterial({ color: color, metalness: .65, roughness: .3 })); m.geometry.center(); return m; }
      var a = gear(3, 14, GRAY), b = gear(1.9, 9, YEL), c = gear(1.4, 7, 0x6B6C71);
      a.position.set(-1.4, .6, 0); b.position.set(2.75, -.95, .2); c.position.set(-.3, -3.4, -.2); g.add(a); g.add(b); g.add(c);
      g.rotation.set(-.35, .45, 0);
      return function (dt) { a.rotation.z += dt * .35; b.rotation.z -= dt * .35 * 14 / 9; c.rotation.z -= dt * .35 * 14 / 7; };
    },
    layers: function (T, g, q) {
      var planes = [];
      for (var i = 0; i < 5; i++) { var m = new T.Mesh(new T.BoxGeometry(6.4, .12, 6.4), new T.MeshStandardMaterial({ color: i === 2 ? YEL : GRAY, metalness: .4, roughness: .45, transparent: true, opacity: i === 2 ? .92 : .8 })); m.add(new T.LineSegments(new T.EdgesGeometry(m.geometry), new T.LineBasicMaterial({ color: i === 2 ? DARK : YEL, transparent: true, opacity: .6 }))); m.userData.i = i; g.add(m); planes.push(m);
        for (var k = 0; k < Math.round(10 * q + 4); k++) { var b = new T.Mesh(new T.BoxGeometry(.5, .35 + Math.random() * .6, .5), new T.MeshStandardMaterial({ color: (k + i) % 3 ? 0xE4E0D6 : YEL, metalness: .2, roughness: .6 })); b.position.set((Math.random() - .5) * 5.2, .3, (Math.random() - .5) * 5.2); m.add(b); } }
      var t = 0; g.rotation.set(.55, .6, 0);
      return function (dt) { t += dt; var gap = 1.3 + (Math.sin(t * .9) + 1) * .45; planes.forEach(function (m) { m.position.y = (m.userData.i - 2) * gap; }); g.rotation.y += dt * .2; };
    },
    orbit: function (T, g) {
      var core = new T.Mesh(new T.SphereGeometry(2.3, 40, 30), new T.MeshStandardMaterial({ color: YEL, emissive: YEL, emissiveIntensity: .3, metalness: .35, roughness: .35 })); g.add(core);
      var halo = new T.Mesh(new T.SphereGeometry(3, 24, 16), new T.MeshBasicMaterial({ color: YEL, wireframe: true, transparent: true, opacity: .15 })); g.add(halo);
      var rings = [];
      [[5, .2, 0], [4, 1.2, .8], [6.2, -.9, 1.6]].forEach(function (cfg, i) { var grp = new T.Group(); grp.rotation.set(cfg[1] + Math.PI / 2, cfg[2], 0); var torus = new T.Mesh(new T.TorusGeometry(cfg[0], .06, 8, 160), new T.MeshBasicMaterial({ color: i === 1 ? YEL : WHITE, transparent: true, opacity: i === 1 ? .8 : .5 })); grp.add(torus); var sat = new T.Mesh(new T.SphereGeometry(.6 - i * .08, 24, 16), new T.MeshStandardMaterial({ color: i === 1 ? YEL : 0xE4E0D6, metalness: .4, roughness: .4 })); sat.userData.r = cfg[0]; sat.userData.s = .8 - i * .18; grp.add(sat); g.add(grp); rings.push(sat); });
      var t = 0;
      return function (dt) { t += dt; rings.forEach(function (s, i) { var a = t * s.userData.s + i * 2; s.position.set(Math.cos(a) * s.userData.r, Math.sin(a) * s.userData.r, 0); }); core.rotation.y += dt * .3; };
    },
    cloud: function (T, g, q) {
      var mat = new T.MeshStandardMaterial({ color: 0xE9E7E1, metalness: .1, roughness: .55 }), cl = new T.Group();
      [[0, 0, 0, 2.3], [-2.2, -.5, .2, 1.7], [2.2, -.4, 0, 1.8], [-1, 1.2, -.2, 1.6], [1.2, 1, .3, 1.5], [3.6, -.9, -.3, 1.1], [-3.6, -1, -.2, 1.1]].forEach(function (b) { var m = new T.Mesh(new T.SphereGeometry(b[3], 32, 24), mat); m.position.set(b[0], b[1], b[2]); cl.add(m); });
      cl.position.y = 1.4; g.add(cl);
      var N = Math.round(120 * q + 30), pos = new Float32Array(N * 3), sp = []; for (var i = 0; i < N; i++) { pos.set([(Math.random() - .5) * 8, -6 + Math.random() * 6, (Math.random() - .5) * 3], i * 3); sp.push(.6 + Math.random() * 1.4); }
      var pts = new T.Points(new T.BufferGeometry().setAttribute('position', new T.BufferAttribute(pos, 3)), new T.PointsMaterial({ color: YEL, size: .14, transparent: true, opacity: .9 })); g.add(pts);
      var base = new T.Mesh(new T.CylinderGeometry(3.6, 3.6, .3, 48), new T.MeshStandardMaterial({ color: GRAY, metalness: .6, roughness: .35 })); base.position.y = -6.2; g.add(base);
      var t = 0;
      return function (dt) { t += dt; var p = pts.geometry.attributes.position.array; for (var i = 0; i < N; i++) { p[i * 3 + 1] += dt * sp[i]; if (p[i * 3 + 1] > 1) p[i * 3 + 1] = -6; } pts.geometry.attributes.position.needsUpdate = true; cl.position.y = 1.4 + Math.sin(t) * .15; g.rotation.y = Math.sin(t * .3) * .5; };
    },
    neural: function (T, g, q) {
      var layers = [4, 7, 7, 3], nodes = [], mat = new T.MeshStandardMaterial({ color: GRAY, emissive: YEL, emissiveIntensity: .15, metalness: .5, roughness: .35 }), matO = new T.MeshStandardMaterial({ color: YEL, emissive: YEL, emissiveIntensity: .4 });
      layers.forEach(function (n, li) { var arr = []; for (var i = 0; i < n; i++) { var m = new T.Mesh(new T.SphereGeometry(.34, 18, 14), li === layers.length - 1 ? matO : mat); m.position.set((li - 1.5) * 3.1, (i - (n - 1) / 2) * 1.35, 0); g.add(m); arr.push(m); } nodes.push(arr); });
      var edges = [];
      for (var li = 0; li < layers.length - 1; li++) nodes[li].forEach(function (a) { nodes[li + 1].forEach(function (b) { var ln = new T.Line(new T.BufferGeometry().setFromPoints([a.position, b.position]), new T.LineBasicMaterial({ color: WHITE, transparent: true, opacity: .12 })); g.add(ln); edges.push([a.position, b.position]); }); });
      var P = Math.round(26 * q + 8), sparks = [];
      for (var s = 0; s < P; s++) { var sp = new T.Mesh(new T.SphereGeometry(.1, 8, 6), new T.MeshBasicMaterial({ color: YEL })); sp.userData.e = edges[Math.floor(Math.random() * edges.length)]; sp.userData.t = Math.random(); sp.userData.v = .4 + Math.random() * .6; g.add(sp); sparks.push(sp); }
      var t = 0;
      return function (dt) { t += dt; sparks.forEach(function (sp) { sp.userData.t += dt * sp.userData.v; if (sp.userData.t > 1) { sp.userData.t = 0; sp.userData.e = edges[Math.floor(Math.random() * edges.length)]; } sp.position.lerpVectors(sp.userData.e[0], sp.userData.e[1], sp.userData.t); }); g.rotation.y = Math.sin(t * .4) * .5; mat.emissiveIntensity = .12 + Math.sin(t * 2) * .06; };
    }
  };
  function scene(host) {
    var T = w.THREE; if (!T) return;
    var kind = host.getAttribute('data-trix-3d') || 'network', build = SCENES[kind] || SCENES.network;
    var small = w.innerWidth < 768, q = small ? .55 : 1;
    var renderer = new T.WebGLRenderer({ antialias: !small, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(w.devicePixelRatio || 1, small ? 1.5 : 2)); host.appendChild(renderer.domElement);
    var sc = new T.Scene(), cam = new T.PerspectiveCamera(40, 1, .1, 200); cam.position.set(0, 0, 24);
    sc.add(new T.AmbientLight(0xffffff, .55)); var key = new T.DirectionalLight(0xffffff, .9); key.position.set(6, 10, 12); sc.add(key);
    var warm = new T.PointLight(YEL, 1.1, 60); warm.position.set(-8, -4, 10); sc.add(warm);
    var root = new T.Group(), g = new T.Group(); root.add(g); sc.add(root);
    var update = build(T, g, q);
    /* enquadramento automático: cabe a cena na coluna qualquer que seja a proporção */
    var box = new T.Box3().setFromObject(g), size = box.getSize(new T.Vector3()), radius = Math.max(size.x, size.y, size.z) * .62 || 8;
    /* extensões por eixo: altura (com folga para a inclinação do arraste) e largura (com folga para o giro em Y) */
    var hy = Math.max(size.y, size.z * .6) / 2 || 4, hx = Math.sqrt(size.x * size.x + size.z * size.z) / 2 * .9 || 4, hz = size.z / 2;
    function fit() {
      var r = host.getBoundingClientRect(), wd = Math.max(1, r.width), ht = Math.max(1, r.height); renderer.setSize(wd, ht, false); cam.aspect = wd / ht;
      var fov = cam.fov * Math.PI / 180, tv = Math.tan(fov / 2), th = tv * cam.aspect;
      var dSphere = radius / Math.sin(fov / 2); if (cam.aspect < 1) dSphere /= cam.aspect; dSphere *= 1.05;   /* cabe em qualquer rotação */
      var dAxis = Math.max(hy / tv, hx / th) * 1.2 + hz * .35;                                                 /* aproveita caixas largas (celular) */
      cam.position.z = Math.min(dSphere, dAxis); cam.updateProjectionMatrix();
    }
    fit(); on(w, 'resize', fit);
    if ('ResizeObserver' in w) new ResizeObserver(fit).observe(host);
    /* interação: arrastar para girar (mouse e toque), inércia e parallax */
    var rx = 0, ry = 0, vx = 0, vy = 0, drag = false, lx = 0, ly = 0, mx = 0, my = 0;
    on(host, 'pointerdown', function (e) { drag = true; lx = e.clientX; ly = e.clientY; if (host.setPointerCapture) host.setPointerCapture(e.pointerId); });
    on(host, 'pointermove', function (e) { if (!drag) return; var dx = e.clientX - lx, dy = e.clientY - ly; lx = e.clientX; ly = e.clientY; vy = dx * .006; vx = dy * .004; ry += vy; rx = Math.max(-.9, Math.min(.9, rx + vx)); });
    on(host, 'pointerup', function () { drag = false; }); on(host, 'pointercancel', function () { drag = false; });
    on(w, 'mousemove', function (e) { mx = (e.clientX / w.innerWidth - .5); my = (e.clientY / w.innerHeight - .5); }, { passive: true });
    var visible = true; if ('IntersectionObserver' in w) new IntersectionObserver(function (es) { visible = es[0].isIntersecting; }).observe(host);
    var vis = host.closest('.trix-hero__visual'); if (vis) vis.classList.add('is-3d');
    var last = performance.now();
    function still() { return reduced || d.documentElement.classList.contains('a11y-motion'); }
    function loop(now) {
      requestAnimationFrame(loop); if (!visible || d.hidden) { last = now; return; }
      var dt = Math.min(.05, (now - last) / 1000); last = now;
      if (!still()) { update(dt); if (!drag) { vy *= .95; vx *= .9; ry += vy; rx *= .98; } }
      root.rotation.y = ry + mx * .5; root.rotation.x = rx + my * .3;
      renderer.render(sc, cam);
    }
    if (still()) update(0);
    requestAnimationFrame(loop);
  }

  /* ---------------- init ---------------- */
  function init() {
    seo(); header(); cookies(); a11y(); fab(); forms(); maps(); effects();
    three();
    var c0 = readConsent(); if (c0 && c0.functional) vlibras(false);
    on(d, 'trix:consent', function (e) { if (e.detail.functional) vlibras(false); });
  }
  if (d.readyState === 'loading') on(d, 'DOMContentLoaded', init); else init();
})();
