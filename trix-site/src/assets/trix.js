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
  d.documentElement.classList.add('trix-js');
  d.body.classList.add('trix');

  /* ---------------- SEO: <head> dinâmico + dados estruturados ---------------- */
  function seo() {
    var node = $('#trix-seo'); var data = {};
    try { data = node ? JSON.parse(node.textContent) : {}; } catch (e) { data = {}; }
    var head = d.head, origin = location.origin, url = origin + (data.canonical || location.pathname);
    var site = CFG.siteName || 'Trix Tecnologia Inteligente';
    function meta(attr, key, val) {
      if (!val) return; var m = head.querySelector('meta[' + attr + '="' + key + '"]');
      if (!m) { m = d.createElement('meta'); m.setAttribute(attr, key); head.appendChild(m); }
      m.setAttribute('content', val);
    }
    var title = data.title || d.title;
    if (data.title) d.title = data.title;
    var desc = data.description || CFG.defaultDescription || '';
    var img = data.image || CFG.defaultImage || '';
    meta('name', 'description', desc);
    meta('name', 'robots', data.robots || 'index, follow, max-image-preview:large');
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
    var can = head.querySelector('link[rel="canonical"]'); if (!can) { can = d.createElement('link'); can.rel = 'canonical'; head.appendChild(can); } can.href = url;
    if (CFG.favicon && !head.querySelector('link[rel="icon"]')) { var f = d.createElement('link'); f.rel = 'icon'; f.href = CFG.favicon; head.appendChild(f); var a = d.createElement('link'); a.rel = 'apple-touch-icon'; a.href = CFG.favicon; head.appendChild(a); }
    /* JSON-LD gerado em tempo de execução: sobrevive à migração de domínio */
    var org = {
      '@type': ['Organization', 'LocalBusiness', 'ProfessionalService'], '@id': origin + '/#organization', name: site, legalName: 'Trix Tecnologia Inteligente Ltda', alternateName: 'Trix TI',
      url: origin + '/', logo: CFG.logo, image: img || CFG.logo, foundingDate: '2009-07-10', taxID: '11.010.095/0001-40', vatID: '11.010.095/0001-40',
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
      graph.push({ '@type': 'SoftwareApplication', name: data.productName || title, applicationCategory: data.category || 'BusinessApplication', operatingSystem: data.os || 'Web', description: desc, image: img || undefined, url: url, provider: { '@id': origin + '/#organization' }, offers: { '@type': 'Offer', price: '0', priceCurrency: 'BRL', availability: 'https://schema.org/InStock', description: 'Demonstração e proposta sob consulta' } });
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
    var onScroll = function () { hd.classList.toggle('is-scrolled', w.scrollY > 24); var t = $('.trix-top'); if (t) t.classList.toggle('is-visible', w.scrollY > 600); };
    on(w, 'scroll', onScroll, { passive: true }); onScroll();
    var items = $$('.trix-nav > li');
    function closeAll(except) { items.forEach(function (li) { if (li !== except) { li.classList.remove('is-open'); var b = $('.trix-nav__link[aria-expanded]', li); if (b) b.setAttribute('aria-expanded', 'false'); } }); }
    items.forEach(function (li) {
      var btn = $('button.trix-nav__link', li), mega = $('.trix-mega', li); if (!btn || !mega) return;
      var open = function () { closeAll(li); li.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true'); };
      var close = function () { li.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); };
      on(btn, 'click', function (e) { e.preventDefault(); li.classList.contains('is-open') ? close() : open(); });
      var timer; on(li, 'mouseenter', function () { clearTimeout(timer); if (w.matchMedia('(hover:hover)').matches) open(); });
      on(li, 'mouseleave', function () { timer = setTimeout(close, 180); });
      on(li, 'focusout', function (e) { if (!li.contains(e.relatedTarget)) close(); });
    });
    on(d, 'keydown', function (e) { if (e.key === 'Escape') { closeAll(); drawerClose(); } });
    on(d, 'click', function (e) { if (!e.target.closest('.trix-nav')) closeAll(); });
    /* marca item atual */
    var path = location.pathname.replace(/\/+$/, '') || '/';
    $$('.trix-nav a, .trix-drawer a').forEach(function (a) { var p = (a.getAttribute('href') || '').replace(/\/+$/, '') || '/'; if (p === path) { a.setAttribute('aria-current', 'page'); var li = a.closest('.trix-nav > li'); if (li) li.classList.add('is-current'); } });
    /* drawer mobile */
    var burger = $('.trix-burger'), drawer = $('.trix-drawer'), back = $('.trix-drawer__backdrop');
    function drawerClose() { if (!drawer) return; drawer.classList.remove('is-open'); back.classList.remove('is-open'); burger.setAttribute('aria-expanded', 'false'); d.body.style.overflow = ''; }
    on(burger, 'click', function () { var o = drawer.classList.toggle('is-open'); back.classList.toggle('is-open', o); burger.setAttribute('aria-expanded', o ? 'true' : 'false'); d.body.style.overflow = o ? 'hidden' : ''; });
    on(back, 'click', drawerClose);
    /* âncoras com offset do header fixo */
    on(d, 'click', function (e) { var a = e.target.closest('a[href^="#"]'); if (!a || a.getAttribute('href') === '#') return; var t = $(a.getAttribute('href')); if (!t) return; e.preventDefault(); var y = t.getBoundingClientRect().top + w.scrollY - 90; w.scrollTo({ top: y, behavior: reduced ? 'auto' : 'smooth' }); t.setAttribute('tabindex', '-1'); t.focus({ preventScroll: true }); });
    on($('.trix-top'), 'click', function () { w.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' }); });
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
    if (saved) applyConsent(saved); else setTimeout(function () { banner.classList.add('is-visible'); }, 900);
    function hide() { banner.classList.remove('is-visible'); if (modal) modal.classList.remove('is-open'); }
    function all(v) { return { necessary: true, functional: v, analytics: v, marketing: v }; }
    on($('[data-cookie="accept"]', banner), 'click', function () { writeConsent(all(true)); hide(); });
    on($('[data-cookie="reject"]', banner), 'click', function () { writeConsent(all(false)); hide(); });
    function openModal() { if (!modal) return; var c = readConsent() || all(false); ['functional', 'analytics', 'marketing'].forEach(function (k) { var i = $('input[name="' + k + '"]', modal); if (i) i.checked = !!c[k]; }); modal.classList.add('is-open'); var f = $('input:not([disabled])', modal); if (f) f.focus(); }
    on($('[data-cookie="custom"]', banner), 'click', openModal);
    on($('.trix-cookiebtn'), 'click', openModal);
    $$('[data-cookie="open"]').forEach(function (b) { on(b, 'click', function (e) { e.preventDefault(); openModal(); }); });
    if (modal) {
      on($('[data-cookie="save"]', modal), 'click', function () { var c = { necessary: true }; ['functional', 'analytics', 'marketing'].forEach(function (k) { var i = $('input[name="' + k + '"]', modal); c[k] = !!(i && i.checked); }); writeConsent(c); hide(); });
      on($('[data-cookie="accept"]', modal), 'click', function () { writeConsent(all(true)); hide(); });
      on($('[data-cookie="close"]', modal), 'click', function () { modal.classList.remove('is-open'); });
      on(modal, 'click', function (e) { if (e.target === modal) modal.classList.remove('is-open'); });
    }
  }

  /* ---------------- Acessibilidade ---------------- */
  function a11y() {
    var btn = $('.trix-a11y-btn'), panel = $('.trix-a11y'); if (!btn || !panel) return;
    var KEY = 'trix_a11y', root = d.documentElement, state = {};
    try { state = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { state = {}; }
    var guide = d.createElement('div'); guide.className = 'trix-readguide'; d.body.appendChild(guide);
    on(d, 'mousemove', function (e) { if (root.classList.contains('a11y-guide')) guide.style.top = (e.clientY - 7) + 'px'; });
    function apply() {
      ['contrast', 'gray', 'font', 'space', 'links', 'cursor', 'motion', 'guide'].forEach(function (k) { root.classList.toggle('a11y-' + k, !!state[k]); var b = $('[data-a11y="' + k + '"]', panel); if (b) b.setAttribute('aria-pressed', state[k] ? 'true' : 'false'); });
      var size = Math.max(-2, Math.min(4, state.size || 0)); root.style.fontSize = size ? (100 + size * 12.5) + '%' : ''; var lbl = $('[data-a11y-size]', panel); if (lbl) lbl.textContent = size ? (size > 0 ? '+' : '') + size : 'A';
      try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { }
    }
    apply();
    on(btn, 'click', function () { var o = panel.classList.toggle('is-open'); btn.setAttribute('aria-expanded', o ? 'true' : 'false'); if (o) { var f = $('button', panel); if (f) f.focus(); } });
    on($('[data-a11y="close"]', panel), 'click', function () { panel.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); btn.focus(); });
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
    on(d, 'keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('is-open')) { panel.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); } });
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
    on(d, 'click', function (e) { if (!f.contains(e.target)) { f.classList.remove('is-open'); b.setAttribute('aria-expanded', 'false'); } });
  }

  /* ---------------- Formulários (captcha matemático + honeypot + envio) ---------------- */
  function forms() {
    $$('form.trix-form').forEach(function (form) {
      var cap = $('.trix-captcha', form), q = $('.trix-captcha__q', form), inp = $('input[name="trix_captcha"]', form), answer = 0;
      function newCaptcha() { var a = Math.floor(Math.random() * 9) + 1, b = Math.floor(Math.random() * 9) + 1, op = Math.random() < .5 ? '+' : '×'; answer = op === '+' ? a + b : a * b; if (q) q.innerHTML = 'Quanto é <span>' + a + '</span> ' + op + ' <span>' + b + '</span> ?'; if (inp) inp.value = ''; }
      newCaptcha(); on($('button', cap), 'click', function (e) { e.preventDefault(); newCaptcha(); });
      var started = Date.now();
      on(form, 'submit', function (e) {
        e.preventDefault();
        var msg = $('.trix-form__msg', form), btn = $('button[type="submit"]', form);
        function show(ok, html) { msg.className = 'trix-form__msg ' + (ok ? 'is-ok' : 'is-err'); msg.innerHTML = html; msg.scrollIntoView({ block: 'nearest', behavior: reduced ? 'auto' : 'smooth' }); }
        if (!form.checkValidity()) { form.reportValidity(); return; }
        var hp = $('input[name="website_url"]', form); if ((hp && hp.value) || Date.now() - started < 2500) { show(false, 'Não foi possível validar o envio. Tente novamente.'); return; }
        if (parseInt(inp.value, 10) !== answer) { show(false, 'A resposta da verificação anti-spam está incorreta. Tente novamente.'); newCaptcha(); inp.focus(); return; }
        var consent = $('input[name="consent"]', form); if (consent && !consent.checked) { show(false, 'É necessário concordar com a Política de Privacidade para enviar.'); return; }
        var kind = form.getAttribute('data-form') || 'contato', fd = new FormData(form), lines = [], name = '', email = '';
        var proto = 'TRIX-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + Math.random().toString(36).slice(2, 6).toUpperCase();
        lines.push('[' + kind.toUpperCase() + '] Protocolo ' + proto);
        $$('[name]', form).forEach(function (el) {
          var n = el.name; if (['trix_captcha', 'website_url', 'consent'].indexOf(n) >= 0) return; if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) return;
          var label = (form.querySelector('label[for="' + el.id + '"]') || {}).textContent || n; label = label.replace('*', '').trim();
          var v = el.value.trim(); if (!v) return; if (n === 'nome') name = v; if (n === 'email') email = v; lines.push(label + ': ' + v);
        });
        lines.push('Página: ' + location.href); lines.push('Enviado em: ' + new Date().toLocaleString('pt-BR'));
        var anon = $('input[name="identificacao"][value="anonima"]:checked', form);
        var body = new URLSearchParams();
        body.set('comment', lines.join('\n')); body.set('author', anon ? 'Relato anônimo' : (name || 'Visitante')); body.set('email', (!anon && email) ? email : (CFG.anonEmail || 'anonimo@example.com')); body.set('url', '');
        var pid = (d.body.className.match(/page-id-(\d+)/) || [])[1] || form.getAttribute('data-post'); body.set('comment_post_ID', pid || ''); body.set('comment_parent', '0');
        btn.disabled = true; btn.dataset.label = btn.textContent; btn.textContent = 'Enviando…';
        var mailto = 'mailto:' + (form.getAttribute('data-mail') || CFG.email) + '?subject=' + encodeURIComponent('[' + kind + '] ' + proto) + '&body=' + encodeURIComponent(lines.join('\n'));
        fetch((CFG.formEndpoint || '/wp-comments-post.php'), { method: 'POST', body: body, credentials: 'same-origin', redirect: 'follow', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' } })
          .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
          .then(function () { show(true, '<strong>Mensagem enviada com sucesso!</strong> Seu protocolo é <strong>' + proto + '</strong>. Guarde este número para acompanhamento. ' + (kind === 'contato' ? 'Nossa equipe responderá em até 1 dia útil.' : 'Sua manifestação será analisada com confidencialidade pelo comitê responsável.')); form.reset(); newCaptcha(); })
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
      var target = parseFloat(el.getAttribute('data-count')), suffix = el.getAttribute('data-suffix') || '', dur = 1400, run = function () { if (reduced) { el.textContent = target + suffix; return; } var t0 = null; function step(t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / dur), v = Math.round(target * (1 - Math.pow(1 - p, 3))); el.textContent = v + suffix; if (p < 1) requestAnimationFrame(step); } requestAnimationFrame(step); };
      if ('IntersectionObserver' in w) { var o = new IntersectionObserver(function (es) { if (es[0].isIntersecting) { run(); o.disconnect(); } }); o.observe(el); } else run();
    });
  }

  /* ---------------- 3D (Three.js) ---------------- */
  function webgl() { try { var c = d.createElement('canvas'); return !!(w.WebGLRenderingContext && (c.getContext('webgl') || c.getContext('experimental-webgl'))); } catch (e) { return false; } }
  function three() {
    var hosts = $$('[data-trix-3d]'); if (!hosts.length || !webgl()) return;
    var s = d.createElement('script'); s.src = CFG.threeUrl || 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js'; s.async = true;
    s.onload = function () { hosts.forEach(scene); }; d.head.appendChild(s);
  }
  function scene(host) {
    var T = w.THREE; if (!T) return; var kind = host.getAttribute('data-trix-3d') || 'network';
    var renderer = new T.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' }); renderer.setPixelRatio(Math.min(w.devicePixelRatio || 1, 1.75)); host.appendChild(renderer.domElement);
    var sc = new T.Scene(), cam = new T.PerspectiveCamera(55, 1, .1, 200); cam.position.set(0, 0, 26);
    var YEL = 0xF8CD4B, GRAY = 0x8A8B90, group = new T.Group(); sc.add(group);
    sc.add(new T.AmbientLight(0xffffff, .6)); var pl = new T.PointLight(YEL, 1.2, 120); pl.position.set(10, 12, 20); sc.add(pl);
    var mats = [];
    if (kind === 'network' || kind === 'globe') {
      var N = kind === 'globe' ? 300 : 240, pos = new Float32Array(N * 3), vel = [], radius = kind === 'globe' ? 9 : 14;
      for (var i = 0; i < N; i++) { var v; if (kind === 'globe') { var u = Math.random(), t = Math.random(), th = 2 * Math.PI * u, ph = Math.acos(2 * t - 1); v = new T.Vector3(radius * Math.sin(ph) * Math.cos(th), radius * Math.sin(ph) * Math.sin(th), radius * Math.cos(ph)); } else v = new T.Vector3((Math.random() - .5) * 2 * radius * 1.6, (Math.random() - .5) * 2 * radius * .9, (Math.random() - .5) * 2 * radius * .6); pos[i * 3] = v.x; pos[i * 3 + 1] = v.y; pos[i * 3 + 2] = v.z; vel.push(new T.Vector3((Math.random() - .5) * .02, (Math.random() - .5) * .02, (Math.random() - .5) * .02)); }
      var geo = new T.BufferGeometry(); geo.setAttribute('position', new T.BufferAttribute(pos, 3));
      var pts = new T.Points(geo, new T.PointsMaterial({ color: YEL, size: kind === 'globe' ? .16 : .22, transparent: true, opacity: .95 })); group.add(pts);
      var lineGeo = new T.BufferGeometry(), maxL = N * 6, lpos = new Float32Array(maxL * 3 * 2); lineGeo.setAttribute('position', new T.BufferAttribute(lpos, 3)); var lines = new T.LineSegments(lineGeo, new T.LineBasicMaterial({ color: 0xffffff, transparent: true, opacity: .16 })); group.add(lines);
      var ico = new T.Mesh(new T.IcosahedronGeometry(kind === 'globe' ? 9.2 : 6, 1), new T.MeshBasicMaterial({ color: YEL, wireframe: true, transparent: true, opacity: kind === 'globe' ? .10 : .35 })); group.add(ico);
      if (kind !== 'globe') { var core = new T.Mesh(new T.IcosahedronGeometry(3.2, 2), new T.MeshStandardMaterial({ color: 0x424346, emissive: YEL, emissiveIntensity: .25, metalness: .6, roughness: .35, flatShading: true })); group.add(core); }
      var thresh = kind === 'globe' ? 2.6 : 4.2;
      mats.push(function (dt) {
        var p = geo.attributes.position.array, k = 0;
        for (var i = 0; i < N; i++) { if (kind !== 'globe') { p[i * 3] += vel[i].x; p[i * 3 + 1] += vel[i].y; p[i * 3 + 2] += vel[i].z; if (Math.abs(p[i * 3]) > radius * 1.6) vel[i].x *= -1; if (Math.abs(p[i * 3 + 1]) > radius * .9) vel[i].y *= -1; if (Math.abs(p[i * 3 + 2]) > radius * .6) vel[i].z *= -1; } }
        for (var a = 0; a < N && k < maxL; a++) { var ax = p[a * 3], ay = p[a * 3 + 1], az = p[a * 3 + 2]; for (var b = a + 1; b < N && k < maxL; b += (kind === 'globe' ? 1 : 1)) { var dx = ax - p[b * 3], dy = ay - p[b * 3 + 1], dz = az - p[b * 3 + 2]; if (dx * dx + dy * dy + dz * dz < thresh * thresh) { lpos[k * 6] = ax; lpos[k * 6 + 1] = ay; lpos[k * 6 + 2] = az; lpos[k * 6 + 3] = p[b * 3]; lpos[k * 6 + 4] = p[b * 3 + 1]; lpos[k * 6 + 5] = p[b * 3 + 2]; k++; } } }
        lineGeo.setDrawRange(0, k * 2); lineGeo.attributes.position.needsUpdate = true; if (kind !== 'globe') geo.attributes.position.needsUpdate = true;
        group.rotation.y += dt * (kind === 'globe' ? .12 : .05); ico.rotation.x += dt * .08; ico.rotation.z -= dt * .05; if (core) { core.rotation.y -= dt * .3; core.rotation.x += dt * .12; }
      });
    } else { /* cubes */
      var cubes = [], cg = new T.BoxGeometry(2.4, 2.4, 2.4), eg = new T.EdgesGeometry(cg);
      for (var c = 0; c < 26; c++) { var m = new T.Mesh(cg, new T.MeshStandardMaterial({ color: c % 3 === 0 ? YEL : 0x424346, metalness: .5, roughness: .4, transparent: true, opacity: .92 })); m.position.set((Math.random() - .5) * 36, (Math.random() - .5) * 18, (Math.random() - .5) * 14); m.rotation.set(Math.random() * 3, Math.random() * 3, 0); m.userData.s = .2 + Math.random() * .5; var e = new T.LineSegments(eg, new T.LineBasicMaterial({ color: c % 3 === 0 ? 0x424346 : YEL, transparent: true, opacity: .6 })); m.add(e); group.add(m); cubes.push(m); }
      mats.push(function (dt, t) { cubes.forEach(function (m, i) { m.rotation.x += dt * m.userData.s; m.rotation.y += dt * m.userData.s * .7; m.position.y += Math.sin(t * .6 + i) * .004; }); group.rotation.y += dt * .04; });
    }
    var mx = 0, my = 0; on(w, 'mousemove', function (e) { mx = (e.clientX / w.innerWidth - .5); my = (e.clientY / w.innerHeight - .5); }, { passive: true });
    function resize() { var r = host.getBoundingClientRect(); renderer.setSize(r.width, r.height, false); cam.aspect = r.width / Math.max(1, r.height); cam.updateProjectionMatrix(); } resize(); on(w, 'resize', resize);
    var visible = true; if ('IntersectionObserver' in w) new IntersectionObserver(function (es) { visible = es[0].isIntersecting; }).observe(host);
    var last = performance.now(), t = 0;
    function loop(now) { requestAnimationFrame(loop); if (!visible || d.hidden) return; var dt = Math.min(.05, (now - last) / 1000); last = now; t += dt; if (!reduced && !d.documentElement.classList.contains('a11y-motion')) { mats.forEach(function (f) { f(dt, t); }); cam.position.x += (mx * 6 - cam.position.x) * .04; cam.position.y += (-my * 4 - cam.position.y) * .04; cam.lookAt(0, 0, 0); } renderer.render(sc, cam); }
    requestAnimationFrame(loop);
  }

  /* ---------------- init ---------------- */
  function init() {
    seo(); header(); cookies(); a11y(); fab(); forms(); maps(); effects();
    three();
    var idle = w.requestIdleCallback || function (f) { setTimeout(f, 1200); }; idle(function () { vlibras(false); });
  }
  if (d.readyState === 'loading') on(d, 'DOMContentLoaded', init); else init();
})();
