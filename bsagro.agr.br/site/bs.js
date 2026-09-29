/* BS Agro Capital — interações do site (WordPress)
   Reúne o script original (Lenis, âncoras, menu, header, scroll spy, revelação,
   contadores, carrossel, formulário em duas etapas) e os recursos novos:
   acessibilidade (contraste/fonte/atalhos), Libras (VLibras), idiomas (Google
   Tradutor), aviso de cookies, pré-diagnóstico, formulários dos canais
   (contato, titular LGPD, integridade), sumário e tempo de leitura. */
(function () {
  "use strict";
  var d = document, w = window, C = w.BS_CONFIG || {};
  var semMovimento = w.matchMedia("(prefers-reduced-motion: reduce)").matches;
  function $(s, r) { return (r || d).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); }
  function store(k, v) {
    try {
      if (v === undefined) { var x = localStorage.getItem(k); return x ? JSON.parse(x) : null; }
      localStorage.setItem(k, JSON.stringify(v));
    } catch (e) { return null; }
  }

  /* ---- SEO: metatags/ícones/JSON-LD emitidos no corpo vão para o <head> ---- */
  try {
    $$('meta[name], meta[property], link[rel~="icon"], link[rel="apple-touch-icon"], link[rel="canonical"], script[type="application/ld+json"]', d.body).forEach(function (el) {
      if (el.tagName !== "SCRIPT") {
        var attr = el.hasAttribute("name") ? "name" : el.hasAttribute("property") ? "property" : "rel";
        if (d.head.querySelector(el.tagName.toLowerCase() + "[" + attr + '="' + el.getAttribute(attr) + '"]')) { el.parentNode.removeChild(el); return; }
      }
      d.head.appendChild(el);
    });
    var tituloSeo = $('meta[name="bs-title"]');
    if (tituloSeo && tituloSeo.content) d.title = tituloSeo.content;
  } catch (e) { /* segue sem mover as tags */ }

  /* ---- WhatsApp: links com data-bs-wa usam o número configurado ----------- */
  var waNum = String(C.whatsapp || "5562996892488").replace(/\D/g, "");
  function waLink(msg) { return "https://wa.me/" + waNum + "?text=" + encodeURIComponent(msg || C.whatsappMsg || "Olá!"); }
  $$("[data-bs-wa]").forEach(function (a) { a.href = waLink(a.getAttribute("data-bs-wa") || ""); });

  /* ---- Área do Cliente: href/rótulo do plugin, quando disponível ---------- */
  var CA = w.EBCR_CLIENT_AREA || null;
  $$("a[data-ebcr-client-area]").forEach(function (a) {
    if (CA && CA.url) a.href = CA.url;
    else if (C.clientArea) a.href = C.clientArea;
    if (CA && CA.loggedIn && !a.hasAttribute("data-keep-label")) {
      var t = a.querySelector(".bs-client-btn__txt") || (a.children.length ? null : a);
      if (t) t.textContent = CA.labelLogged || "Minha área";
    }
  });

  /* ---- Lenis: rolagem suave ---------------------------------------------- */
  var lenis = null;
  var suavizacao = function (t) { return Math.min(1, 1.001 - Math.pow(2, -10 * t)); };
  if (!semMovimento && typeof w.Lenis !== "undefined") {
    lenis = new w.Lenis({ autoRaf: false, duration: 1.4, easing: suavizacao, smoothWheel: true, wheelMultiplier: 1 });
    (function animar(t) { lenis.raf(t); requestAnimationFrame(animar); })(0);
  }

  var header = d.getElementById("site-header");
  function irPara(alvo) {
    var el = d.querySelector(alvo);
    if (!el) return;
    var alturaHeader = header ? header.getBoundingClientRect().bottom : 0;
    var topo = el.getBoundingClientRect().top + w.scrollY - Math.max(alturaHeader, 0) - 3;
    if (lenis) lenis.scrollTo(topo, { duration: 1.1, easing: suavizacao });
    else w.scrollTo({ top: topo, behavior: semMovimento ? "auto" : "smooth" });
    if (el.hasAttribute("tabindex") || /^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) el.focus({ preventScroll: true });
  }
  var mesmaPagina = function (link) {
    try { var u = new URL(link.href, location.href); return u.origin === location.origin && u.pathname === location.pathname && u.search === location.search && u.hash; }
    catch (e) { return false; }
  };
  d.addEventListener("click", function (ev) {
    var link = ev.target.closest && ev.target.closest("a[href*='#']");
    if (!link || link.hasAttribute("data-ck")) return;
    var hash = mesmaPagina(link);
    if (!hash || hash === "#" || !d.querySelector(hash)) return;
    ev.preventDefault();
    irPara(hash);
    history.replaceState(null, "", hash);
  });
  if (location.hash && location.hash.length > 1) {
    w.addEventListener("load", function () { try { if (d.querySelector(location.hash)) setTimeout(function () { irPara(location.hash); }, 60); } catch (e) {} });
  }

  /* ---- Menu mobile ------------------------------------------------------- */
  var toggle = d.getElementById("menu-toggle"), menu = d.getElementById("mobile-menu");
  var iconMenu = d.getElementById("icon-menu"), iconClose = d.getElementById("icon-close");
  function fecharMenu() {
    if (!menu) return;
    menu.classList.add("hidden");
    if (iconMenu) iconMenu.classList.remove("hidden");
    if (iconClose) iconClose.classList.add("hidden");
    if (toggle) { toggle.setAttribute("aria-expanded", "false"); var s = toggle.querySelector(".sr-only"); if (s) s.textContent = "Abrir menu de navegação"; }
  }
  if (toggle && menu) {
    toggle.addEventListener("click", function () {
      var aberto = !menu.classList.contains("hidden");
      menu.classList.toggle("hidden", aberto);
      if (iconMenu) iconMenu.classList.toggle("hidden", !aberto);
      if (iconClose) iconClose.classList.toggle("hidden", aberto);
      toggle.setAttribute("aria-expanded", String(!aberto));
      var s = toggle.querySelector(".sr-only"); if (s) s.textContent = aberto ? "Abrir menu de navegação" : "Fechar menu de navegação";
    });
    menu.addEventListener("click", function (ev) { if (ev.target.closest("a")) fecharMenu(); });
    d.addEventListener("keydown", function (ev) { if (ev.key === "Escape") fecharMenu(); });
  }

  /* ---- Megamenus (desktop): clique/teclado; hover com pequena intenção ------- */
  var mmBtns = $$("[data-mm]"), mmBack = $(".bs-mm-backdrop"), mmTimer = null, mmAtual = null, mmDesde = 0;
  var hoverOK = w.matchMedia("(hover: hover) and (pointer: fine)").matches;
  function mmPainel(b) { return d.getElementById(b.getAttribute("data-mm")); }
  function mmFechar(foco) {
    clearTimeout(mmTimer);
    if (!mmAtual) return;
    var p = mmPainel(mmAtual); if (p) p.hidden = true;
    mmAtual.setAttribute("aria-expanded", "false");
    if (foco) mmAtual.focus();
    mmAtual = null; if (mmBack) mmBack.hidden = true;
  }
  function mmAbrir(b) {
    if (mmAtual === b) return;
    mmFechar(false);
    var p = mmPainel(b); if (!p) return;
    p.hidden = false; b.setAttribute("aria-expanded", "true");
    mmAtual = b; mmDesde = Date.now(); if (mmBack) mmBack.hidden = false;
  }
  function focaveis(el) { return $$("a[href], button:not([disabled])", el).filter(function (x) { return x.offsetParent !== null; }); }
  mmBtns.forEach(function (b, i) {
    b.addEventListener("click", function () { if (mmAtual === b && Date.now() - mmDesde > 450) mmFechar(false); else mmAbrir(b); });
    if (hoverOK) {
      b.addEventListener("mouseenter", function () { clearTimeout(mmTimer); mmTimer = setTimeout(function () { mmAbrir(b); }, mmAtual ? 0 : 90); });
      b.addEventListener("mouseleave", function () { clearTimeout(mmTimer); mmTimer = setTimeout(function () { mmFechar(false); }, 280); });
    }
    b.addEventListener("keydown", function (e) {
      if (e.key === "ArrowDown" || (e.key === "Tab" && !e.shiftKey && mmAtual === b)) {
        e.preventDefault(); mmAbrir(b); var f = focaveis(mmPainel(b))[0]; if (f) f.focus();
      }
    });
  });
  $$(".bs-mm").forEach(function (p) {
    if (hoverOK) {
      p.addEventListener("mouseenter", function () { clearTimeout(mmTimer); });
      p.addEventListener("mouseleave", function () { clearTimeout(mmTimer); mmTimer = setTimeout(function () { mmFechar(false); }, 280); });
    }
    p.addEventListener("click", function (e) { if (e.target.closest("a")) mmFechar(false); });
    p.addEventListener("keydown", function (e) {
      if (e.key !== "Tab" || !mmAtual) return;
      var fs = focaveis(p), btn = mmAtual;
      if (e.shiftKey && d.activeElement === fs[0]) { e.preventDefault(); btn.focus(); }
      else if (!e.shiftKey && d.activeElement === fs[fs.length - 1]) {
        e.preventDefault(); var itens = $$("#bs-nav .nav-link"), prox = itens[itens.indexOf(btn) + 1];
        mmFechar(false); (prox || btn).focus();
      }
    });
  });
  if (mmBack) mmBack.addEventListener("click", function () { mmFechar(false); });
  d.addEventListener("keydown", function (e) { if (e.key === "Escape" && mmAtual) mmFechar(true); });
  d.addEventListener("click", function (e) { if (mmAtual && !e.target.closest("[data-mm], .bs-mm")) mmFechar(false); });
  w.addEventListener("resize", function () { if (w.innerWidth < 1280) mmFechar(false); });

  /* ---- Header -------------------------------------------------------------
     Página inicial: como no site original, aparece só enquanto o hero está na
     tela. Demais páginas: sempre visível; depois do banner, a logo ganha um
     fundo próprio (header--solido) para continuar legível sobre o conteúdo
     claro. A barra de acessibilidade rola junto com a página; o header sobe
     para o topo assim que ela sai da tela. */
  var util = d.getElementById("bs-util");
  if (header) {
    var hero = $("[data-hero]"), banner = $(".bs-banner");
    function atualizarHeader() {
      var menuAberto = menu && !menu.classList.contains("hidden");
      var temFoco = header.contains(d.activeElement);
      var utilH = util ? util.offsetHeight : 0;
      header.classList.toggle("header--sem-util", w.scrollY > utilH);
      if (hero) {
        var noHero = hero.getBoundingClientRect().bottom > 0;
        var esconder = !(menuAberto || temFoco || noHero || mmAtual);
        header.classList.toggle("header--escondido", esconder);
      } else {
        var ref = banner || $("main");
        var passou = ref ? ref.getBoundingClientRect().bottom < 90 : w.scrollY > 200;
        header.classList.toggle("header--solido", passou);
      }
    }
    w.addEventListener("scroll", atualizarHeader, { passive: true });
    w.addEventListener("resize", atualizarHeader);
    header.addEventListener("focusin", atualizarHeader);
    header.addEventListener("focusout", atualizarHeader);
    atualizarHeader();
  }

  /* ---- Menu: seção em leitura (home) e página atual ----------------------- */
  var navLinks = $$(".nav-link[data-nav-anchor]");
  if ($("[data-hero]") && "IntersectionObserver" in w) {
    var secoes = navLinks.map(function (l) { return d.querySelector(l.dataset.navAnchor); }).filter(Boolean);
    var visiveis = {};
    var spy = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (e) { if (e.isIntersecting) visiveis["#" + e.target.id] = true; else delete visiveis["#" + e.target.id]; });
      var atual = null;
      for (var i = 0; i < navLinks.length; i++) { if (visiveis[navLinks[i].dataset.navAnchor]) { atual = navLinks[i].dataset.navAnchor; break; } }
      navLinks.forEach(function (l) { var on = !!atual && l.dataset.navAnchor === atual; if (l.tagName === "BUTTON") l.classList.toggle("is-current", on); else if (on) l.setAttribute("aria-current", "true"); else l.removeAttribute("aria-current"); });
    }, { rootMargin: "-20% 0px -60% 0px", threshold: 0 });
    secoes.forEach(function (s) { spy.observe(s); });
  }
  var pagEl = $("[data-bs-page]"), pagAtual = pagEl ? pagEl.getAttribute("data-bs-page") : "";
  if (pagAtual) $$('.nav-link[data-nav-page="' + pagAtual + '"]').forEach(function (l) { if (l.tagName === "BUTTON") l.classList.add("is-current"); else l.setAttribute("aria-current", "page"); });

  /* ---- Revelação ao rolar -------------------------------------------------- */
  var revelaveis = $$(".revelar");
  if ("IntersectionObserver" in w) {
    var obsRevelar = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (e) { if (!e.isIntersecting) return; e.target.classList.add("visivel"); obsRevelar.unobserve(e.target); });
    }, { rootMargin: "0px 0px -10% 0px", threshold: 0.05 });
    revelaveis.forEach(function (el) { obsRevelar.observe(el); });
    setTimeout(function () { revelaveis.forEach(function (el) { var r = el.getBoundingClientRect(); if (r.top < w.innerHeight && r.bottom > 0) el.classList.add("visivel"); }); }, 1200);
  } else {
    revelaveis.forEach(function (el) { el.classList.add("visivel"); });
  }

  /* ---- Contagem animada dos indicadores ------------------------------------ */
  function animarNumero(el) {
    var bruto = el.textContent.trim();
    var casado = bruto.match(/^([^\d]*)([\d.,]+)(.*)$/);
    if (!casado || semMovimento) return;
    var prefixo = casado[1], sufixo = casado[3];
    var limpo = casado[2].replace(/\./g, "").replace(",", ".");
    var alvo = parseFloat(limpo);
    if (isNaN(alvo)) return;
    var decimais = (limpo.split(".")[1] || "").length;
    var pareceAno = decimais === 0 && alvo >= 1000 && alvo <= 9999 && limpo.indexOf(".") === -1;
    var inicio = null, duracao = 1600;
    function passo(ts) {
      if (inicio === null) inicio = ts;
      var p = Math.min((ts - inicio) / duracao, 1);
      var atual = (alvo * (1 - Math.pow(1 - p, 3))).toFixed(decimais);
      var txt = pareceAno ? Math.round(atual).toString() : Number(atual).toLocaleString("pt-BR", { minimumFractionDigits: decimais, maximumFractionDigits: decimais });
      el.textContent = prefixo + txt + sufixo;
      if (p < 1) requestAnimationFrame(passo);
    }
    requestAnimationFrame(passo);
  }
  var contadores = $$("[data-contador]");
  if (contadores.length && "IntersectionObserver" in w) {
    var obsContador = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (e) { if (!e.isIntersecting) return; animarNumero(e.target); obsContador.unobserve(e.target); });
    }, { threshold: 0.5 });
    contadores.forEach(function (el) { obsContador.observe(el); });
  }

  /* ---- Carrossel de diferenciais ------------------------------------------- */
  var carrossel = $("[data-carousel]");
  if (carrossel) {
    var trilho = $("[data-carousel-track]", carrossel), progresso = $("[data-carousel-progress]", carrossel);
    var btnAnt = $("[data-carousel-prev]", carrossel), btnProx = $("[data-carousel-next]", carrossel);
    var atualizarCarrossel = function () {
      var maximo = trilho.scrollWidth - trilho.clientWidth;
      btnAnt.disabled = maximo <= 0 || trilho.scrollLeft <= 1;
      btnProx.disabled = maximo <= 0 || trilho.scrollLeft >= maximo - 1;
      progresso.style.width = maximo <= 0 ? "100%" : Math.max(0, Math.min(1, trilho.scrollLeft / maximo)) * 100 + "%";
    };
    var irExtremo = function (pos) { trilho.scrollTo({ left: pos, behavior: semMovimento ? "auto" : "smooth" }); };
    btnAnt.addEventListener("click", function () { irExtremo(0); });
    btnProx.addEventListener("click", function () { irExtremo(trilho.scrollWidth - trilho.clientWidth); });
    trilho.addEventListener("scroll", atualizarCarrossel, { passive: true });
    w.addEventListener("resize", atualizarCarrossel);
    atualizarCarrossel();
  }

  /* ---- Ano ----------------------------------------------------------------- */
  $$("[data-bs-ano]").forEach(function (el) { el.textContent = new Date().getFullYear(); });

  /* ---- Acessibilidade: contraste e tamanho da fonte (persistidos) ---------- */
  var a11y = store("bs_a11y") || { fs: 1, contrast: false };
  function aplicarA11y(salvar) {
    d.documentElement.style.setProperty("--bs-fs", a11y.fs);
    d.documentElement.classList.toggle("bs-contrast", !!a11y.contrast);
    $$('[data-bs-a11y="contrast"]').forEach(function (b) { b.setAttribute("aria-pressed", a11y.contrast ? "true" : "false"); });
    if (salvar && (!w.BSConsent || w.BSConsent.allowed("functional"))) store("bs_a11y", a11y);
  }
  aplicarA11y(false);
  var anunciar = (function () {
    var live = d.createElement("div");
    live.className = "sr-only"; live.setAttribute("aria-live", "polite"); d.body.appendChild(live);
    return function (t) { live.textContent = ""; setTimeout(function () { live.textContent = t; }, 50); };
  })();
  $$("[data-bs-a11y]").forEach(function (b) {
    b.addEventListener("click", function () {
      var acao = b.getAttribute("data-bs-a11y");
      if (acao === "plus") a11y.fs = Math.min(1.5, +(a11y.fs + 0.1).toFixed(2));
      else if (acao === "minus") a11y.fs = Math.max(0.8, +(a11y.fs - 0.1).toFixed(2));
      else if (acao === "reset") a11y.fs = 1;
      else if (acao === "contrast") a11y.contrast = !a11y.contrast;
      aplicarA11y(true);
      anunciar(acao === "contrast" ? (a11y.contrast ? "Alto contraste ativado" : "Alto contraste desativado") : "Tamanho do texto: " + Math.round(a11y.fs * 100) + "%");
    });
  });

  /* ---- Consentimento de cookies ---------------------------------------------
     Banner + central de preferências + botão para rever. A escolha fica em
     localStorage (bs_cookie_consent) com ID, versão e data, vale 12 meses e é
     registrada no plugin da Área do Cliente quando ele está ativo (prova do
     consentimento). Scripts opcionais podem ser marcados com type="text/plain"
     e data-bs-category="analytics" (ou functional/advertising): só são
     executados depois do consentimento da categoria. */
  var CK_VERSAO = 1, CK_VALIDADE = 365 * 864e5;
  var CK_CATS = [
    { k: "necessary", t: "Necessários", fixo: true, d: "Essenciais para o site funcionar, manter a sessão da Área do Cliente com segurança e lembrar a sua escolha sobre cookies. Não podem ser desativados.",
      itens: [
        ["bs_cookie_consent", "12 meses", "Guarda a sua escolha neste aviso (armazenamento local).", "BS Agro Capital"],
        ["wordpress_logged_in_*, wordpress_sec_*", "Sessão ou até 14 dias", "Mantêm e protegem o login de clientes e da equipe na Área do Cliente.", "BS Agro Capital (WordPress)"],
        ["ebcr_fid", "1 dia", "Identificador aleatório para reexibir mensagens das telas de login e cadastro.", "BS Agro Capital (Área do Cliente)"],
        ["ebcr_2fa_trust", "Até 30 dias", "Lembra um dispositivo confiável na verificação em duas etapas da equipe.", "BS Agro Capital (Área do Cliente)"]
      ] },
    { k: "functional", t: "Funcionais", d: "Recursos opcionais que melhoram a experiência: preferências de acessibilidade, tradução automática e o widget de Libras. Sem eles, o site continua funcionando.",
      itens: [
        ["bs_a11y", "Até ser apagado", "Lembra tamanho do texto e alto contraste (armazenamento local).", "BS Agro Capital"],
        ["googtrans", "Até voltar ao português", "Guarda o idioma escolhido para a tradução automática (EN/ES).", "Google (Google Tradutor)"],
        ["VLibras", "Conforme o serviço", "Widget oficial de tradução para Libras; carregado a partir de vlibras.gov.br.", "Governo Federal (VLibras)"],
        ["wp-settings-*", "Até 1 ano", "Preferências de interface de usuários conectados.", "BS Agro Capital (WordPress)"]
      ] },
    { k: "analytics", t: "Analíticos", d: "Medem de forma agregada como o site é usado, para melhorar conteúdo e navegação.", itens: [] },
    { k: "advertising", t: "Publicidade", d: "Personalizam anúncios e medem campanhas. A BS Agro Capital não usa publicidade de terceiros neste site.", itens: [] }
  ];
  var ckBanner = d.getElementById("bs-ck"), ckModal = d.getElementById("bs-ckm"), ckRevisit = d.getElementById("bs-ck-revisit");
  var ckBox = ckModal ? $(".bs-ckm__box", ckModal) : null, ckFocoAntes = null;
  var gpc = !!(navigator.globalPrivacyControl);
  function ckLer() {
    var c = store("bs_cookie_consent");
    if (!c || c.v !== CK_VERSAO || !c.ts || Date.now() - c.ts > CK_VALIDADE) return null;
    return c;
  }
  var consent = ckLer();
  function ckUuid() {
    if (w.crypto && crypto.randomUUID) return crypto.randomUUID();
    return "10000000-1000-4000-8000-100000000000".replace(/[018]/g, function (c) { return (c ^ (Math.random() * 16) >> (c / 4)).toString(16); });
  }
  function permitido(cat) { return cat === "necessary" || !!(consent && consent.categories && consent.categories[cat]); }
  function ckAplicar() {
    d.documentElement.classList.toggle("bs-no-func", !permitido("functional"));
    /* Google Consent Mode v2 (pronto para uma futura medição de audiência) */
    if (typeof w.gtag === "function") w.gtag("consent", "update", {
      analytics_storage: permitido("analytics") ? "granted" : "denied",
      ad_storage: permitido("advertising") ? "granted" : "denied", ad_user_data: permitido("advertising") ? "granted" : "denied", ad_personalization: permitido("advertising") ? "granted" : "denied",
      functionality_storage: permitido("functional") ? "granted" : "denied", personalization_storage: permitido("functional") ? "granted" : "denied"
    });
    $$('script[type="text/plain"][data-bs-category]').forEach(function (s) {
      if (!permitido(s.getAttribute("data-bs-category")) || s.hasAttribute("data-bs-ativo")) return;
      var n = d.createElement("script");
      Array.prototype.forEach.call(s.attributes, function (a) { if (a.name !== "type" && a.name !== "data-bs-category") n.setAttribute(a.name, a.value); });
      if (!s.src) n.text = s.text;
      s.setAttribute("data-bs-ativo", "1"); s.parentNode.insertBefore(n, s.nextSibling);
    });
    if (permitido("functional")) carregarVLibras();
    if (ckRevisit) ckRevisit.hidden = !consent;
  }
  function ckRegistrar(acao) {
    var CAx = w.EBCR_CLIENT_AREA;
    if (!CAx || !CAx.consentEndpoint || !consent) return;
    try {
      fetch(CAx.consentEndpoint, { method: "POST", headers: { "Content-Type": "application/json" }, keepalive: true, credentials: "same-origin",
        body: JSON.stringify({ consent_id: consent.id, categories: consent.categories, action: acao, version: CK_VERSAO, path: location.pathname }) }).catch(function () {});
    } catch (e) { /* o registro é complementar; a escolha já está salva no navegador */ }
  }
  function ckSalvar(cats, acao) {
    var ant = consent;
    consent = { v: CK_VERSAO, id: (ant && ant.id) || ckUuid(), ts: Date.now(), action: acao, gpc: gpc,
      categories: { necessary: true, functional: !!cats.functional, analytics: !!cats.analytics, advertising: !!cats.advertising } };
    store("bs_cookie_consent", consent);
    if (!consent.categories.functional) { try { localStorage.removeItem("bs_a11y"); } catch (e) {} }
    if (ckBanner) ckBanner.hidden = true;
    ckFechar();
    ckAplicar(); ckRegistrar(acao);
    try { d.dispatchEvent(new CustomEvent("bs:consent", { detail: consent })); } catch (e) {}
    anunciar("Preferências de cookies salvas.");
  }
  function ckTodas(v) { return { functional: v, analytics: v, advertising: v }; }
  function ckMontar() {
    var alvo = $("[data-ck-cats]", ckModal); if (!alvo || alvo.childElementCount) return;
    alvo.innerHTML = CK_CATS.map(function (c) {
      var id = "bs-ckc-" + c.k;
      var tab = c.itens.length ? '<div class="bs-ckm__tbl" role="region" aria-label="Itens da categoria ' + c.t + '" tabindex="0"><table><thead><tr><th scope="col">Item</th><th scope="col">Duração</th><th scope="col">Finalidade</th><th scope="col">Fornecedor</th></tr></thead><tbody>' +
        c.itens.map(function (i) { return '<tr><td data-l="Item"><code>' + i[0] + '</code></td><td data-l="Duração">' + i[1] + '</td><td data-l="Finalidade">' + i[2] + '</td><td data-l="Fornecedor">' + i[3] + "</td></tr>"; }).join("") + "</tbody></table></div>"
        : '<p class="bs-ckm__none">Nenhum item desta categoria está em uso no momento. Se for adotado, ele aparecerá aqui e pediremos a sua permissão.</p>';
      var ctrl = c.fixo ? '<span class="bs-ckm__always">Sempre ativos</span>'
        : '<button type="button" class="bs-sw" role="switch" aria-checked="false" data-ck-cat="' + c.k + '" aria-label="' + c.t + '"><span class="bs-sw__k"></span></button>';
      return '<div class="bs-ckm__cat"><div class="bs-ckm__row"><button type="button" class="bs-ckm__acc" aria-expanded="false" aria-controls="' + id + '"><svg aria-hidden="true"><use href="#i-chev"/></svg><span><b>' + c.t + '</b></span></button>' + ctrl +
        '</div><div class="bs-ckm__detail" id="' + id + '" hidden><p>' + c.d + "</p>" + tab + "</div></div>";
    }).join("");
    $$(".bs-ckm__acc", alvo).forEach(function (b) {
      b.addEventListener("click", function () { var p = d.getElementById(b.getAttribute("aria-controls")), ab = b.getAttribute("aria-expanded") !== "true"; b.setAttribute("aria-expanded", ab); p.hidden = !ab; });
    });
    $$(".bs-sw", alvo).forEach(function (s) { s.addEventListener("click", function () { s.setAttribute("aria-checked", s.getAttribute("aria-checked") === "true" ? "false" : "true"); }); });
  }
  function ckPreencher() {
    $$("[data-ck-cat]", ckModal).forEach(function (s) {
      var k = s.getAttribute("data-ck-cat"), on = consent ? !!consent.categories[k] : (k === "functional" && !gpc);
      s.setAttribute("aria-checked", on ? "true" : "false");
    });
    var meta = $("[data-ck-meta]", ckModal);
    if (meta) {
      meta.hidden = !consent;
      if (consent) meta.innerHTML = "ID do seu consentimento: <code>" + consent.id + "</code><br>Registrado em " + new Date(consent.ts).toLocaleString("pt-BR") + (gpc ? " · Sinal Global Privacy Control respeitado" : "");
    }
  }
  function ckAbrir() {
    if (!ckModal) return;
    ckMontar(); ckPreencher();
    ckFocoAntes = d.activeElement;
    ckModal.hidden = false; d.documentElement.classList.add("bs-ck-lock");
    setTimeout(function () { ckBox.focus(); }, 30);
  }
  function ckFechar() {
    if (!ckModal || ckModal.hidden) return;
    ckModal.hidden = true; d.documentElement.classList.remove("bs-ck-lock");
    if (ckFocoAntes && ckFocoAntes.focus) ckFocoAntes.focus();
  }
  if (ckModal) ckModal.addEventListener("keydown", function (e) {
    if (e.key === "Escape") { e.preventDefault(); ckFechar(); return; }
    if (e.key !== "Tab") return;
    var fs = $$('button:not([hidden]), a[href], [tabindex="0"]', ckBox).filter(function (x) { return x.offsetParent !== null; });
    if (!fs.length) return;
    if (e.shiftKey && (d.activeElement === fs[0] || d.activeElement === ckBox)) { e.preventDefault(); fs[fs.length - 1].focus(); }
    else if (!e.shiftKey && d.activeElement === fs[fs.length - 1]) { e.preventDefault(); fs[0].focus(); }
  });
  d.addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest("[data-ck]"); if (!b) return;
    var a = b.getAttribute("data-ck");
    if (a === "open") { e.preventDefault(); ckAbrir(); }
    else if (a === "close") ckFechar();
    else if (a === "accept") ckSalvar(ckTodas(true), "accept_all");
    else if (a === "reject") ckSalvar(ckTodas(false), "reject_all");
    else if (a === "save") {
      var cats = {}; $$("[data-ck-cat]", ckModal).forEach(function (s) { cats[s.getAttribute("data-ck-cat")] = s.getAttribute("aria-checked") === "true"; });
      ckSalvar(cats, "custom");
    } else if (a === "more") {
      var intro = $("[data-ck-intro]", ckModal), ab = !intro.classList.contains("is-open");
      intro.classList.toggle("is-open", ab); b.setAttribute("aria-expanded", ab); b.textContent = ab ? "Mostrar menos" : "Mostrar mais";
    }
  });
  w.BSConsent = { get: function () { return consent; }, allowed: permitido, open: ckAbrir, acceptAll: function () { ckSalvar(ckTodas(true), "accept_all"); }, rejectAll: function () { ckSalvar(ckTodas(false), "reject_all"); } };
  if (!consent && ckBanner) setTimeout(function () { ckBanner.hidden = false; }, 700);

  /* ---- Libras (VLibras, widget oficial do Governo Federal) -----------------
     Carregado com o consentimento "funcionais" ou quando a pessoa pede Libras
     no botão da barra de acessibilidade (pedido explícito do recurso). */
  function carregarVLibras(abrir) {
    if (w.__bsVL) { if (abrir) abrirVLibras(); return; }
    if (!abrir && !permitido("functional")) return;
    w.__bsVL = true;
    var s = d.createElement("script");
    s.src = "https://vlibras.gov.br/app/vlibras-plugin.js"; s.async = true;
    s.onload = function () { try { new w.VLibras.Widget("https://vlibras.gov.br/app"); if (abrir) setTimeout(abrirVLibras, 1200); } catch (e) {} };
    d.head.appendChild(s);
  }
  function abrirVLibras() {
    d.documentElement.classList.remove("bs-no-func");
    var b = $("[vw-access-button]"); if (b) b.click();
  }
  $$("[data-bs-libras]").forEach(function (b) { b.addEventListener("click", function () { carregarVLibras(true); }); });
  ckAplicar();

  /* ---- Idiomas (PT/EN/ES) via Google Tradutor — cookie googtrans ------------ */
  function lerCookie(n) { var m = d.cookie.match("(?:^|; )" + n + "=([^;]*)"); return m ? decodeURIComponent(m[1]) : ""; }
  function gravarIdioma(l) {
    var v = l === "pt" ? "" : "/pt/" + l, host = location.hostname.replace(/^www\./, "");
    var exp = l === "pt" ? "Thu, 01 Jan 1970 00:00:00 GMT" : new Date(Date.now() + 365 * 864e5).toUTCString();
    ["", "domain=" + host + ";", "domain=." + host + ";"].forEach(function (dm) { d.cookie = "googtrans=" + v + ";" + dm + "path=/;expires=" + exp + ";SameSite=Lax"; });
  }
  var idioma = (lerCookie("googtrans").split("/")[2] || "pt").slice(0, 2);
  if (!/^(pt|en|es)$/.test(idioma)) idioma = "pt";
  $$(".bs-lang").forEach(function (b) { var on = b.dataset.lang === idioma; b.classList.toggle("is-on", on); b.setAttribute("aria-pressed", on ? "true" : "false"); });
  if (idioma !== "pt") {
    d.documentElement.setAttribute("lang", idioma === "en" ? "en" : "es");
    w.bsGtInit = function () { try { new w.google.translate.TranslateElement({ pageLanguage: "pt", includedLanguages: "pt,en,es", autoDisplay: false }, "bs-gt"); } catch (e) {} };
    var gt = d.createElement("script"); gt.src = "https://translate.google.com/translate_a/element.js?cb=bsGtInit"; gt.async = true; d.head.appendChild(gt);
  }
  $$(".bs-lang").forEach(function (b) {
    b.addEventListener("click", function () { var l = b.dataset.lang; if (l === idioma) return; gravarIdioma(l); location.reload(); });
  });

  /* ---- Pré-diagnóstico -------------------------------------------------------- */
  var quiz = $("[data-bs-quiz]");
  if (quiz) {
    var LINHAS = {
      custeio: { t: "Custeio Rural", d: "Financia insumos, sementes, defensivos e mão de obra do ciclo em andamento, cobrindo o intervalo entre o plantio e a colheita.", m: "Custeio Rural" },
      investimento: { t: "Crédito para Investimentos", d: "Para máquinas, equipamentos, benfeitorias, irrigação e armazenagem, com prazos de médio e longo prazo.", m: "Crédito para Investimentos" },
      comercializacao: { t: "Comercialização", d: "Fôlego financeiro entre a colheita e a venda, para armazenar e negociar em melhores condições.", m: "Comercialização" },
      cpr: { t: "CPR (Cédula de Produto Rural)", d: "Antecipação de recursos lastreada na produção futura, junto a compradores ou instituições financeiras.", m: "CPR (Cédula de Produto Rural)" },
      fundos: { t: "Crédito Estruturado via Fundos de Investimento", d: "Mais volume, prazo e flexibilidade com capital privado, compatível com a sua capacidade de pagamento.", m: "Crédito Estruturado via Fundos de Investimento" },
      imovel: { t: "Crédito com garantia de imóvel", d: "Seu patrimônio rural ou urbano como garantia real, com condições definidas junto às fontes de capital.", m: "Crédito com garantia de imóvel (rural ou urbano)" },
      rj: { t: "Estruturação para Recuperação Judicial", d: "Diagnóstico financeiro e patrimonial para reorganizar passivos e abrir caminho para um novo ciclo.", m: "Recuperação Judicial" }
    };
    var ROT = {
      bsq_perfil: { produtor: "Produtor rural", empresa: "Empresa do agronegócio", rj: "Em Recuperação Judicial" },
      bsq_necessidade: { custeio: "Financiar a safra", investimento: "Investir na estrutura", comercializacao: "Vender no melhor momento", cpr: "Antecipar recursos com a produção", dividas: "Reorganizar dívidas", volume: "Volume e prazo maiores" },
      bsq_garantia: { rural: "Imóvel rural", urbano: "Imóvel urbano", producao: "Produção futura / recebíveis", avaliar: "Ainda não sei" }
    };
    var passos = $$(".bs-quiz__step", quiz), resultado = $("[data-bs-quiz-result]", quiz);
    var contador = $("[data-bs-quiz-step]", quiz), barra = $("[data-bs-quiz-bar]", quiz);
    var voltar = $("[data-bs-quiz-back]", quiz), refazer = $("[data-bs-quiz-restart]", quiz);
    var atual = 0;
    function valor(n) { var r = quiz.querySelector('input[name="' + n + '"]:checked'); return r ? r.value : ""; }
    function mostrar(i) {
      atual = i;
      passos.forEach(function (p, k) { p.hidden = k !== i; p.classList.toggle("is-on", k === i); });
      resultado.hidden = i < passos.length;
      contador.textContent = Math.min(i + 1, 3);
      barra.style.width = (Math.min(i + 1, 3) / 3) * 100 + "%";
      voltar.hidden = i === 0;
      refazer.hidden = i < passos.length;
      $(".bs-quiz__count", quiz).hidden = i >= passos.length;
    }
    function recomendar() {
      var perfil = valor("bsq_perfil"), nec = valor("bsq_necessidade"), gar = valor("bsq_garantia");
      var lista = [];
      function add(k, motivo) { if (!lista.some(function (x) { return x.k === k; })) lista.push({ k: k, motivo: motivo }); }
      if (perfil === "rj") add("rj", "Indicado para o seu perfil");
      var mapa = { custeio: "custeio", investimento: "investimento", comercializacao: "comercializacao", cpr: "cpr", volume: "fundos" };
      if (mapa[nec]) add(mapa[nec], "Atende à sua necessidade principal");
      if (nec === "dividas") { if (gar === "rural" || gar === "urbano") add("imovel", "Reorganiza dívidas com garantia real"); add("fundos", "Alonga prazos com capital privado"); }
      if (gar === "rural" || gar === "urbano") add("imovel", "Aproveita a garantia que você indicou");
      if (gar === "producao") add("cpr", "Usa a produção futura como lastro");
      if (lista.length < 2) add("fundos", "Alternativa para ampliar volume e prazo");
      return lista.slice(0, 3);
    }
    function concluir() {
      var lista = recomendar();
      $("[data-bs-quiz-lines]", quiz).innerHTML = lista.map(function (x) {
        var l = LINHAS[x.k];
        return "<li><b>" + l.t + "</b><span>" + l.d + "</span><em>" + x.motivo + "</em></li>";
      }).join("");
      var resumo = "Perfil: " + (ROT.bsq_perfil[valor("bsq_perfil")] || "-") + "; Necessidade: " + (ROT.bsq_necessidade[valor("bsq_necessidade")] || "-") + "; Garantia: " + (ROT.bsq_garantia[valor("bsq_garantia")] || "-");
      var wa = $("[data-bs-quiz-wa]", quiz);
      if (wa) wa.href = waLink("Olá! Fiz o pré-diagnóstico no site da BS Agro Capital. " + resumo + ". Indicações: " + lista.map(function (x) { return LINHAS[x.k].t; }).join(", ") + ". Gostaria de conversar com um especialista.");
      quiz.setAttribute("data-bs-resumo", resumo);
      quiz.setAttribute("data-bs-top", LINHAS[lista[0].k].m);
      mostrar(passos.length);
      resultado.focus({ preventScroll: true });
    }
    quiz.addEventListener("change", function (e) {
      if (e.target.type !== "radio") return;
      setTimeout(function () { if (atual < passos.length - 1) { mostrar(atual + 1); var r = passos[atual].querySelector("input"); if (r) r.focus({ preventScroll: true }); } else concluir(); }, semMovimento ? 0 : 220);
    });
    voltar.addEventListener("click", function () { if (atual > 0) mostrar(Math.min(atual, passos.length) - 1); });
    refazer.addEventListener("click", function () { $$("input", quiz).forEach(function (i) { i.checked = false; }); mostrar(0); });
    var levarForm = $("[data-bs-quiz-form]", quiz);
    if (levarForm) levarForm.addEventListener("click", function () {
      var sel = d.getElementById("modalidade"), top = quiz.getAttribute("data-bs-top");
      if (sel && top) { Array.prototype.forEach.call(sel.options, function (o) { if (o.value === top) sel.value = top; }); }
      var g = valor("bsq_garantia"), pg = d.getElementById("possui_garantia"), tg = d.getElementById("tipo_garantia");
      if (pg && (g === "rural" || g === "urbano")) pg.value = "Sim";
      if (tg && g === "rural") tg.value = "Imóvel rural";
      if (tg && g === "urbano") tg.value = "Imóvel urbano";
    });
    mostrar(0);
  }

  /* ---- Formulário "Solicitar análise" (duas etapas, webhook) ------------------ */
  var mensagensErro = { valueMissing: "Este campo é obrigatório.", typeMismatch: "Informe um valor válido.", patternMismatch: "Formato inválido. Verifique o número informado." };
  function ligarFormulario(form) {
    var status = $("[data-form-status]", form);
    function validarCampo(campo) {
      var erro = campo.closest("div") ? campo.closest("div").querySelector(".field-error") : null;
      if (!erro) { var lab = campo.closest("label"); erro = lab && lab.parentElement ? lab.parentElement.querySelector(".field-error") : null; }
      if (!erro) return;
      if (campo.validity.valid) { erro.textContent = ""; campo.removeAttribute("aria-invalid"); return; }
      var chave = Object.keys(mensagensErro).filter(function (k) { return campo.validity[k]; })[0];
      erro.textContent = chave ? mensagensErro[chave] : "Verifique este campo.";
      campo.setAttribute("aria-invalid", "true");
    }
    var campos = $$("input, textarea, select", form);
    campos.forEach(function (c) { c.addEventListener("blur", function () { validarCampo(c); }); c.addEventListener("change", function () { validarCampo(c); }); });
    function formatarTelefone(v) { var dg = v.replace(/\D/g, "").slice(0, 11); if (dg.length <= 2) return dg ? "(" + dg : ""; return "(" + dg.slice(0, 2) + ") " + dg.slice(2); }
    var tel = form.querySelector('input[type="tel"]');
    if (tel) tel.addEventListener("input", function () {
      var antes = tel.value.slice(0, tel.selectionStart || 0).replace(/\D/g, "").length;
      tel.value = formatarTelefone(tel.value);
      var pos = 0, vistos = 0;
      while (pos < tel.value.length && vistos < antes) { if (/\d/.test(tel.value.charAt(pos))) vistos++; pos++; }
      while (pos < tel.value.length && !/\d/.test(tel.value.charAt(pos))) pos++;
      try { tel.setSelectionRange(pos, pos); } catch (e) {}
    });
    var mostrarPasso = null, passo1 = null, passo2 = null;
    if (form.hasAttribute("data-form-steps")) {
      passo1 = $('[data-form-step="1"]', form); passo2 = $('[data-form-step="2"]', form);
      mostrarPasso = function (n) {
        var alvo = n === 1 ? passo1 : passo2, outro = n === 1 ? passo2 : passo1;
        outro.hidden = true; outro.classList.remove("form-step--visivel"); alvo.hidden = false;
        if (semMovimento) alvo.classList.add("form-step--visivel");
        else { alvo.classList.remove("form-step--visivel"); requestAnimationFrame(function () { requestAnimationFrame(function () { alvo.classList.add("form-step--visivel"); }); }); }
        var primeiro = alvo.querySelector("input, select, textarea"); if (primeiro) primeiro.focus();
      };
      var av = $("[data-form-next]", form), vt = $("[data-form-back]", form);
      if (av) av.addEventListener("click", function () {
        $$("input, select, textarea", passo1).forEach(validarCampo);
        var inv = passo1.querySelector("input:invalid, textarea:invalid, select:invalid");
        if (inv) { inv.focus(); return; }
        mostrarPasso(2);
      });
      if (vt) vt.addEventListener("click", function () { mostrarPasso(1); });
    }
    function mostrarStatus(t, tipo) { if (!status) return; status.textContent = t; status.className = "form-status form-status--" + tipo; status.hidden = false; }
    var pag = $("[data-bs-pagina]", form); if (pag) pag.value = location.href;
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var hp = form.querySelector('input[name="_gotcha"]'); if (hp && hp.value) return;
      var paraValidar = mostrarPasso ? $$("input, select, textarea", passo2) : campos, primeiroInv = null;
      paraValidar.forEach(function (c) { validarCampo(c); if (!primeiroInv && !c.validity.valid) primeiroInv = c; });
      if (primeiroInv) { primeiroInv.focus(); return; }
      mostrarStatus("Enviando solicitação...", "info");
      var enviar = form.querySelector('button[type="submit"]'); if (enviar) enviar.disabled = true;
      var redirecionando = false;
      var dados = new FormData(form);
      if (quiz && quiz.getAttribute("data-bs-resumo")) dados.append("pre_diagnostico", quiz.getAttribute("data-bs-resumo"));
      fetch(form.action, { method: "POST", body: dados, headers: { Accept: "application/json" } })
        .then(function (r) {
          if (!r.ok) throw new Error("falha");
          form.reset(); if (mostrarPasso) mostrarPasso(1);
          redirecionando = true;
          mostrarStatus("Solicitação enviada. Redirecionando...", "ok");
          w.location.assign(C.obrigado || "/");
        })
        .catch(function () { mostrarStatus("Não foi possível enviar sua solicitação agora. Tente novamente ou fale conosco pelo WhatsApp.", "erro"); })
        .then(function () { if (enviar && !redirecionando) enviar.disabled = false; });
    });
  }
  $$("form[data-form-endpoint]").forEach(ligarFormulario);

  /* ---- Formulários dos canais (e-mail via FormSubmit) ------------------------ */
  function protocolo(prefixo) {
    var dt = new Date(), p = function (n) { return (n < 10 ? "0" : "") + n; };
    var rnd = Math.random().toString(36).slice(2, 6).toUpperCase();
    return prefixo + "-" + dt.getFullYear() + p(dt.getMonth() + 1) + p(dt.getDate()) + "-" + rnd;
  }
  $$("form.bs-form[data-bs-canal]").forEach(function (f) {
    var anon = f.querySelector("[data-bs-anonimo]");
    if (anon) {
      var aplicarAnon = function () {
        $$("[data-bs-identificacao]", f).forEach(function (bloco) {
          bloco.hidden = anon.checked;
          $$("input, select, textarea", bloco).forEach(function (i) { if (i.hasAttribute("data-req")) i.required = !anon.checked; if (anon.checked) i.value = ""; });
        });
      };
      $$("[data-bs-identificacao] [required]", f).forEach(function (i) { i.setAttribute("data-req", "1"); });
      anon.addEventListener("change", aplicarAnon); aplicarAnon();
    }
    f.addEventListener("submit", function (e) {
      e.preventDefault();
      var msg = $(".bs-form-msg", f), btn = $('[type="submit"]', f), hp = $('input[name="_honey"]', f);
      if (hp && hp.value) return;
      var faltando = $$("[required]", f).filter(function (i) { return !i.closest("[hidden]") && (i.type === "checkbox" ? !i.checked : !String(i.value).trim() || !i.validity.valid); });
      if (faltando.length) {
        msg.className = "bs-form-msg is-err"; msg.textContent = "Revise os campos obrigatórios destacados para continuar.";
        faltando.forEach(function (i) { i.setAttribute("aria-invalid", "true"); });
        faltando[0].focus(); return;
      }
      $$("[aria-invalid]", f).forEach(function (i) { i.removeAttribute("aria-invalid"); });
      var canal = f.getAttribute("data-bs-canal"), destino = (C.emails || {})[canal] || (C.emails || {}).contato;
      var prot = protocolo(f.getAttribute("data-bs-prefixo") || "BS");
      var dados = {};
      new FormData(f).forEach(function (v, k) { if (k !== "_honey") dados[k] = v; });
      dados.protocolo = prot;
      dados._subject = (f.getAttribute("data-bs-assunto") || "Mensagem pelo site bsagro.agr.br") + " — " + prot;
      dados._template = "table"; dados._captcha = "false"; dados.pagina = location.href;
      if (dados.email) dados._replyto = dados.email;
      if (btn) btn.disabled = true;
      msg.className = "bs-form-msg is-ok"; msg.textContent = "Enviando…";
      fetch((C.formEndpoint || "https://formsubmit.co/ajax/") + destino, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify(dados) })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!(j && (j.success === true || j.success === "true"))) throw new Error((j && j.message) || "erro");
          msg.className = "bs-form-msg is-ok";
          msg.innerHTML = (f.getAttribute("data-bs-ok") || "Mensagem enviada! Retornaremos em breve.") + '<br>Guarde o seu protocolo: <span class="bs-protocol">' + prot + "</span>";
          f.reset(); if (anon) anon.dispatchEvent(new Event("change"));
          msg.setAttribute("tabindex", "-1"); msg.focus();
        })
        .catch(function () {
          msg.className = "bs-form-msg is-err";
          msg.innerHTML = "Não foi possível enviar agora. Escreva para <a href=\"mailto:" + destino + "\">" + destino + "</a> ou fale pelo <a href=\"" + waLink() + "\" target=\"_blank\" rel=\"noopener\">WhatsApp</a>.";
        })
        .then(function () { if (btn) btn.disabled = false; });
    });
  });

  /* ---- Sumário automático e tempo de leitura --------------------------------- */
  var toc = $("[data-bs-toc]");
  if (toc) {
    var hs = $$(".bs-prose h2");
    if (hs.length < 2) { var box = toc.closest(".bs-card"); if (box) box.hidden = true; }
    hs.forEach(function (h, i) {
      if (!h.id) h.id = "secao-" + (i + 1) + "-" + h.textContent.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "").slice(0, 48);
      var li = d.createElement("li"), a = d.createElement("a"); a.href = "#" + h.id; a.textContent = h.textContent; li.appendChild(a); toc.appendChild(li);
    });
    if ("IntersectionObserver" in w && hs.length) {
      var tio = new IntersectionObserver(function (es) {
        es.forEach(function (en) { if (en.isIntersecting) $$("a", toc).forEach(function (a) { a.classList.toggle("is-on", a.getAttribute("href") === "#" + en.target.id); }); });
      }, { rootMargin: "-20% 0px -70% 0px" });
      hs.forEach(function (h) { tio.observe(h); });
    }
  }
  var rt = $("[data-bs-readtime]"), corpo = $(".bs-prose");
  if (rt && corpo) { var palavras = (corpo.textContent || "").trim().split(/\s+/).length; rt.textContent = "Leitura de ~" + Math.max(1, Math.round(palavras / 200)) + " min"; }
  var trilha = $("[data-bs-crumb]"), h1 = $(".bs-banner h1, .bs-banner .wp-block-post-title");
  if (trilha && h1) trilha.textContent = h1.textContent.trim();
})();
