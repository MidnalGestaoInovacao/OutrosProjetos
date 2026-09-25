#!/usr/bin/env python3
"""Gera, a partir de src/, os artefatos publicáveis no WordPress (dist/):
 - blocks/*.html   -> blocos reutilizáveis (estilos, cabeçalho, rodapé, widgets)
 - templates/*.html-> templates do tema de blocos (page, home, index, 404, ...)
 - pages/*.json    -> páginas (título, slug, pai, conteúdo em bloco HTML, SEO)
 - global-styles.json -> theme.json (paleta/tipografia)
Uso: python3 src/build.py [--media media_map.json]
"""
import json, os, re, sys, html, hashlib, base64
HERE = os.path.dirname(os.path.abspath(__file__)); ROOT = os.path.dirname(HERE); DIST = os.path.join(ROOT, "dist")
sys.path.insert(0, HERE)
from icons import ICONS
from nav import NAV, SITE
from content import ALL_PAGES

MEDIA_MAP = {}
def load_media(path):
    global MEDIA_MAP
    if path and os.path.exists(path): MEDIA_MAP = json.load(open(path))
IMG_DIR = os.environ.get("TRIX_IMG_DIR", "/tmp/claude-0/-home-user-OutrosProjetos/c6c5b86e-91b8-57fd-9cce-9e053bdb5efc/scratchpad/trix/images")
MISSING_MEDIA = set()
def media(key):
    """URL da mídia na biblioteca do WordPress, sem domínio (sobrevive à migração). Sem mapeamento: usa a URL do site antigo e registra aviso."""
    m = MEDIA_MAP.get(key)
    if m:
        u = m["url"]; return re.sub(r"^https?://[^/]+", "", u) if "/wp-content/" in u else u
    MISSING_MEDIA.add(key); return "https://trixti.com.br/" + key
def data_uri(key):
    """Logo embutida como data URI (sem requisição extra; independente de domínio). Cai para a URL da mídia se o arquivo não existir."""
    path = os.path.join(IMG_DIR, key.split("images/", 1)[1]) if key.startswith("images/") else None
    if path and os.path.exists(path):
        return "data:image/png;base64," + base64.b64encode(open(path, "rb").read()).decode("ascii")
    return media(key)
def render(s):
    s = re.sub(r"\{\{media:([^}]+)\}\}", lambda m: media(m.group(1)), s)
    s = re.sub(r"\{\{icon:([a-z0-9_-]+)\}\}", lambda m: ICONS.get(m.group(1), ICONS["dot"]), s)
    return s
def wp_html(inner):
    return "<!-- wp:html -->\n" + inner.strip() + "\n<!-- /wp:html -->\n"

# ---------------------------------------------------------------- header
def mega_item(it):
    return ('<a class="trix-mega__item" href="%s"><i>%s</i><span><b>%s</b><small>%s</small></span></a>'
            % (it["url"], ICONS.get(it.get("icon","dot"), ICONS["dot"]), html.escape(it["label"]), html.escape(it.get("desc",""))))
def header_html():
    out = ['<a class="trix-skip" href="#conteudo">Ir para o conteúdo</a>',
           '<header class="trix-header" role="banner"><div class="trix-container trix-header__in">',
           '<a class="trix-brand" href="/" aria-label="%s — página inicial"><img src="%s" alt="%s" width="347" height="130" decoding="async" fetchpriority="high"></a>' % (SITE["name"], data_uri("images/01.png"), SITE["name"]),
           '<nav aria-label="Navegação principal"><ul class="trix-nav">']
    for i, top in enumerate(NAV):
        if top.get("children"):
            mid = "mega-%d" % i
            out.append('<li><button type="button" class="trix-nav__link" aria-expanded="false" aria-controls="%s">%s %s</button>' % (mid, html.escape(top["label"]), ICONS["chevron"]))
            cols = "trix-mega__cols" + (" trix-mega__cols--1" if len(top["children"]) <= 4 else "")
            out.append('<div class="trix-mega%s" id="%s"><div class="%s"><p class="trix-mega__title">%s</p>%s</div>' % (" trix-mega--sm" if len(top["children"]) <= 5 else "", mid, cols, html.escape(top.get("title", top["label"])), "".join(mega_item(c) for c in top["children"])))
            f = top.get("feature")
            if f: out.append('<div class="trix-mega__feat"><b>%s</b><p>%s</p><a href="%s">%s →</a></div>' % (f["title"], f["text"], f["url"], f["cta"]))
            out.append('</div></li>')
        else:
            out.append('<li><a class="trix-nav__link%s" href="%s">%s</a></li>' % (" trix-nav__link--cta" if top.get("cta") else "", top["url"], html.escape(top["label"])))
    out.append('</ul></nav><button type="button" class="trix-a11y-hbtn" aria-label="Abrir recursos de acessibilidade" aria-expanded="false" aria-controls="trix-a11y">%s</button>' % ICONS["a11y"])
    out.append('<button type="button" class="trix-burger" aria-label="Abrir menu" aria-expanded="false" aria-controls="trix-drawer"><span></span><span></span><span></span></button></div></header>')
    out.append('<div class="trix-drawer__backdrop"></div><nav class="trix-drawer" id="trix-drawer" aria-label="Menu">')
    for top in NAV:
        if top.get("children"):
            out.append('<details><summary>%s</summary><div>%s</div></details>' % (html.escape(top["label"]), "".join('<a href="%s">%s %s</a>' % (c["url"], ICONS.get(c.get("icon","dot"), ICONS["dot"]), html.escape(c["label"])) for c in top["children"])))
        elif top.get("cta"):
            out.append('<a class="trix-btn trix-btn--primary" href="%s">%s</a>' % (top["url"], html.escape(top["label"])))
        else:
            out.append('<a href="%s">%s</a>' % (top["url"], html.escape(top["label"])))
    out.append('</nav>')
    return render("\n".join(out))

def config_script():
    cfg = {"siteName": SITE["name"], "defaultDescription": SITE["description"], "logo": media("images/trix-logo-512.png"), "defaultImage": media("images/02.jpg"),
           "favicon": SITE.get("favicon"), "phone": SITE["phone_e164"], "email": SITE["email"], "geo": SITE["geo"], "mapsUrl": SITE["maps_url"],
           "social": [SITE["social"][k] for k in ("linkedin", "instagram", "facebook")], "ga4": SITE.get("ga4", ""), "anonEmail": "anonimo@example.com"}
    return '<script>window.TRIX_CONFIG=%s;</script>' % json.dumps(cfg, ensure_ascii=False)

def styles_html():
    css = open(os.path.join(HERE, "assets", "trix.css"), encoding="utf-8").read()
    return ('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,600;0,700;0,800;1,300;1,400&display=swap">'
            '<style id="trix-css">\n' + css + '\n</style>\n' + config_script())

# ---------------------------------------------------------------- footer
def footer_html():
    s = SITE
    cols = [("Institucional", [("/empresa/", "A Trix"), ("/empresa/#missao", "Missão, visão e valores"), ("/clientes/", "Clientes"), ("/servicos/oracle-partner/", "Oracle Partner"), ("/empresa/eventos/", "Eventos e Trix Academy"), ("/contato/", "Contato")]),
            ("Soluções", [("/servicos/", "Todos os serviços"), ("/servicos/fabrica-de-software/", "Fábrica de software"), ("/servicos/conectividade-em-saude/", "Conectividade em saúde"), ("/produtos/prontow/", "Prontow"), ("/produtos/saw/", "SAW"), ("/produtos/xield/", "Xield"), ("/produtos/aspect-face/", "Aspect Face")]),
            ("Conformidade", [("/conformidade/", "Central de Conformidade"), ("/conformidade/lgpd/", "Portal de Proteção de Dados"), ("/conformidade/politica-de-privacidade/", "Política de Privacidade"), ("/conformidade/codigo-de-conduta/", "Código de Conduta Ética"), ("/canal-de-compliance/", "Ouvidoria e denúncias"), ("/canal-lgpd/", "Direitos do titular (LGPD)"), ("#", "Preferências de cookies", "open")])]
    out = ['<footer class="trix-footer" role="contentinfo"><div class="trix-container"><div class="trix-footer__grid">',
           '<div class="trix-footer__brand"><img src="%s" alt="Trix Tecnologia Inteligente" width="347" height="130" loading="lazy">' % data_uri("images/01.png"),
           '<p>Desde 2009 criando software sob medida, conectividade em saúde suplementar e soluções com inteligência artificial para empresas de todo o Brasil.</p>',
           '<p><strong>Trix Tecnologia Inteligente Ltda</strong><br>CNPJ 11.010.095/0001-40<br>%s<br>%s</p>' % (s["address_line1"], re.sub(r'(CEP \d{5}-\d{3}|Brasília – DF|Zona Industrial \(Guará\))', r'<span class="trix-nowrap">\1</span>', s["address_line2"])),
           '<p><a href="tel:%s">%s</a> · <a href="mailto:%s">%s</a></p>' % (s["phone_e164"], s["phone"], s["email"], s["email"]),
           '<div class="trix-footer__social">']
    for key, icon, label in (("linkedin", "linkedin", "LinkedIn"), ("instagram", "instagram", "Instagram"), ("facebook", "facebook", "Facebook"), ("whatsapp", "whatsapp", "WhatsApp")):
        out.append('<a href="%s" target="_blank" rel="noopener" aria-label="%s">%s</a>' % (s["social"][key], label, ICONS[icon]))
    out.append('</div></div>')
    for title, links in cols:
        out.append('<div><h4>%s</h4><ul>' % title)
        for l in links:
            attr = ' data-cookie="open"' if len(l) > 2 else ''
            out.append('<li><a href="%s"%s>%s</a></li>' % (l[0], attr, l[1]))
        out.append('</ul></div>')
    out.append('</div><div class="trix-footer__bottom"><span>© <span id="trix-year">2026</span> Trix Tecnologia Inteligente. Todos os direitos reservados.</span>'
               '<nav class="trix-footer__legal" aria-label="Links legais"><a href="/conformidade/politica-de-privacidade/">Privacidade</a><a href="/conformidade/cookies/">Cookies</a><a href="/conformidade/codigo-de-conduta/">Código de Conduta</a><a href="/conformidade/seguranca-da-informacao/">Segurança</a><a href="/conformidade/inteligencia-artificial/">IA responsável</a><a href="/conformidade/esg/">ESG</a></nav></div></div></footer>')
    return render("\n".join(out))

# ---------------------------------------------------------------- widgets
def widgets_html():
    s = SITE
    js = open(os.path.join(HERE, "assets", "trix.js"), encoding="utf-8").read()
    out = [
     # contatos flutuantes
     '<div class="trix-fab"><button type="button" class="trix-fab__main" aria-label="Abrir canais de contato" aria-expanded="false">%s</button><ul class="trix-fab__list">' % ICONS["chat"] +
     '<li><a href="%s" target="_blank" rel="noopener"><span>WhatsApp</span><span class="ic ic--wa">%s</span></a></li>' % (s["social"]["whatsapp"], ICONS["whatsapp"]) +
     '<li><a href="mailto:%s"><span>E-mail</span><span class="ic ic--mail">%s</span></a></li>' % (s["email"], ICONS["mail"]) +
     '<li><a href="tel:%s"><span>%s</span><span class="ic ic--tel">%s</span></a></li>' % (s["phone_e164"], s["phone"], ICONS["phone"]) +
     '<li><a href="%s" target="_blank" rel="noopener"><span>Instagram</span><span class="ic ic--ig">%s</span></a></li>' % (s["social"]["instagram"], ICONS["instagram"]) +
     '<li><a href="%s" target="_blank" rel="noopener"><span>LinkedIn</span><span class="ic ic--in">%s</span></a></li>' % (s["social"]["linkedin"], ICONS["linkedin"]) +
     '<li><a href="%s" target="_blank" rel="noopener"><span>Facebook</span><span class="ic ic--fb">%s</span></a></li>' % (s["social"]["facebook"], ICONS["facebook"]) +
     '</ul></div>',
     '<button type="button" class="trix-top" aria-label="Voltar ao topo">%s</button>' % ICONS["arrow-up"],
     # acessibilidade
     '<button type="button" class="trix-a11y-btn" aria-label="Abrir recursos de acessibilidade" aria-expanded="false" aria-controls="trix-a11y">%s</button>' % ICONS["a11y"],
     '<div class="trix-a11y" id="trix-a11y" role="dialog" aria-label="Recursos de acessibilidade"><h3>Acessibilidade <button type="button" data-a11y="close" aria-label="Fechar">×</button></h3><div class="trix-a11y__grid">'
     '<button type="button" data-a11y="bigger">%s Aumentar texto <span data-a11y-size>A</span></button>' % ICONS["text-plus"] +
     '<button type="button" data-a11y="smaller">%s Diminuir texto</button>' % ICONS["text-minus"] +
     '<button type="button" data-a11y="contrast" aria-pressed="false">%s Alto contraste</button>' % ICONS["contrast"] +
     '<button type="button" data-a11y="gray" aria-pressed="false">%s Escala de cinza</button>' % ICONS["gray"] +
     '<button type="button" data-a11y="font" aria-pressed="false">%s Fonte legível</button>' % ICONS["font"] +
     '<button type="button" data-a11y="space" aria-pressed="false">%s Espaçamento</button>' % ICONS["space"] +
     '<button type="button" data-a11y="links" aria-pressed="false">%s Destacar links</button>' % ICONS["link"] +
     '<button type="button" data-a11y="cursor" aria-pressed="false">%s Cursor grande</button>' % ICONS["cursor"] +
     '<button type="button" data-a11y="guide" aria-pressed="false">%s Guia de leitura</button>' % ICONS["guide"] +
     '<button type="button" data-a11y="motion" aria-pressed="false">%s Pausar animações</button>' % ICONS["pause"] +
     '<button type="button" data-a11y="libras">%s Libras (VLibras)</button>' % ICONS["hands"] +
     '<button type="button" data-a11y="reset">%s Redefinir</button>' % ICONS["reset"] +
     '</div></div>',
     '<button type="button" class="trix-cookiebtn" aria-label="Preferências de cookies">%s</button>' % ICONS["cookie"],
     # cookie banner
     '<div class="trix-cookie" role="region" aria-label="Aviso de cookies"><div><h3>🍪 Trix e os cookies</h3><p>Utilizamos cookies para oferecer melhor experiência, melhorar o desempenho, analisar como você interage com o site e personalizar conteúdo. <span class="trix-cookie__opt">Você pode aceitar todos, rejeitar os não essenciais ou personalizar suas preferências.</span></p><p class="trix-cookie__more">Saiba mais: <a href="/conformidade/politica-de-privacidade/">Política de Privacidade</a> · <a href="/conformidade/cookies/">Política de Cookies</a></p></div>'
     '<div class="trix-cookie__btns"><button type="button" class="trix-btn trix-btn--primary" data-cookie="accept">Aceitar todos</button><button type="button" class="trix-btn trix-btn--ghost" data-cookie="reject">Rejeitar não essenciais</button><button type="button" class="trix-btn trix-btn--dark" data-cookie="custom">Personalizar</button></div></div>',
     '<div class="trix-modal" id="trix-cookie-modal" role="dialog" aria-modal="true" aria-label="Preferências de cookies"><div class="trix-modal__box"><h3>Preferências de cookies <button type="button" data-cookie="close" aria-label="Fechar">×</button></h3><p class="trix-muted" style="font-size:.9rem">Escolha quais categorias de cookies deseja permitir. Cookies estritamente necessários não podem ser desativados, pois garantem o funcionamento do site.</p>'
     '<div class="trix-cookie-cat"><div><b id="cc-necessary">Estritamente necessários</b><p>Segurança, preferências de consentimento e de acessibilidade, fontes (Google Fonts) e biblioteca 3D (cdnjs) — sem cookies de rastreamento. Sempre ativos.</p></div><label class="trix-switch"><input type="checkbox" checked disabled name="necessary" aria-labelledby="cc-necessary"><span></span></label></div>'
     '<div class="trix-cookie-cat"><div><b id="cc-functional">Funcionais</b><p>Recursos como mapa interativo (Google Maps) e tradução em Libras (VLibras, gov.br), carregados de terceiros.</p></div><label class="trix-switch"><input type="checkbox" name="functional" aria-labelledby="cc-functional"><span></span></label></div>'
     '<div class="trix-cookie-cat"><div><b id="cc-analytics">Analíticos</b><p>Estatísticas anônimas de navegação (Google Analytics 4) para melhorar o site.</p></div><label class="trix-switch"><input type="checkbox" name="analytics" aria-labelledby="cc-analytics"><span></span></label></div>'
     '<div class="trix-cookie-cat"><div><b id="cc-marketing">Marketing</b><p>Medição de campanhas e conteúdo relevante em outras plataformas.</p></div><label class="trix-switch"><input type="checkbox" name="marketing" aria-labelledby="cc-marketing"><span></span></label></div>'
     '<div class="trix-actions"><button type="button" class="trix-btn trix-btn--primary" data-cookie="save">Salvar preferências</button><button type="button" class="trix-btn trix-btn--ghost" data-cookie="accept">Aceitar todos</button></div></div></div>',
     # O WordPress converte '&' em '&#038;' ao renderizar o template (mesmo dentro de <script>), o que quebraria o JS.
     # Por isso o script é embutido em base64 e decodificado em tempo de execução (UTF-8).
     '<script id="trix-js">(function(){var b="' + base64.b64encode(js.encode("utf-8")).decode("ascii") + '";var t=atob(b),u=new Uint8Array(t.length);for(var i=0;i<t.length;i++)u[i]=t.charCodeAt(i);var s=document.createElement("script");s.id="trix-js-src";s.textContent=new TextDecoder("utf-8").decode(u);document.head.appendChild(s);})();</script>']
    return render("\n".join(out))

# ---------------------------------------------------------------- templates
def template(main_inner, kind="page"):
    refs = '{{BLOCK_REFS}}'
    return ("<!-- wp:block {\"ref\":{{REF_ESTILOS}}} /-->\n<!-- wp:block {\"ref\":{{REF_CABECALHO}}} /-->\n"
            "<!-- wp:group {\"tagName\":\"main\",\"className\":\"trix-main\",\"layout\":{\"type\":\"default\"}} -->\n<main class=\"wp-block-group trix-main\" id=\"conteudo\">\n" + main_inner +
            "\n</main>\n<!-- /wp:group -->\n<!-- wp:block {\"ref\":{{REF_RODAPE}}} /-->\n<!-- wp:block {\"ref\":{{REF_WIDGETS}}} /-->\n")
TEMPLATES = {
  "page": template('<!-- wp:post-content {"layout":{"type":"default"}} /-->'),
  "page-no-title": template('<!-- wp:post-content {"layout":{"type":"default"}} /-->'),
  # home: exibe a página "home" (menor menu_order) via Query Loop — funciona mesmo sem definir página inicial estática
  "home": template('<!-- wp:query {"queryId":1,"query":{"perPage":1,"pages":0,"offset":0,"postType":"page","order":"asc","orderBy":"menu_order","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"trix-home-query"} -->\n<div class="wp-block-query trix-home-query"><!-- wp:post-template {"layout":{"type":"default"}} -->\n<!-- wp:post-content {"layout":{"type":"default"}} /-->\n<!-- /wp:post-template --></div>\n<!-- /wp:query -->'),
  "index": template('<!-- wp:group {"layout":{"type":"constrained"},"className":"trix-section"} --><div class="wp-block-group trix-section"><!-- wp:query {"queryId":2,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"isLink":true} /--><!-- wp:post-excerpt /--><!-- /wp:post-template --><!-- wp:query-pagination --><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--><!-- /wp:query-pagination --><!-- wp:query-no-results --><!-- wp:paragraph --><p>Nenhum conteúdo encontrado.</p><!-- /wp:paragraph --><!-- /wp:query-no-results --></div><!-- /wp:query --></div><!-- /wp:group -->'),
  "single": template('<!-- wp:group {"layout":{"type":"constrained"},"className":"trix-section"} --><div class="wp-block-group trix-section"><!-- wp:post-title {"level":1} /--><!-- wp:post-content /--></div><!-- /wp:group -->'),
  "404": template(wp_html('<section class="trix-hero"><div class="trix-container trix-hero__grid"><div class="trix-hero__head"><span class="trix-kicker">Erro 404</span><h1>Página <strong>não encontrada</strong></h1></div><div class="trix-hero__body"><p class="lead">O endereço pode ter mudado ou o link está incorreto. Use o menu ou os atalhos abaixo para continuar navegando.</p><div class="trix-actions"><a class="trix-btn trix-btn--primary" href="/">Ir para a página inicial</a><a class="trix-btn trix-btn--ghost" href="/produtos/">Ver produtos</a><a class="trix-btn trix-btn--ghost" href="/contato/">Falar com a Trix</a></div></div><div class="trix-hero__visual"><div class="trix-hero__fallback" aria-hidden="true"><i>{{icon:search}}</i></div><div class="trix-hero__stage" data-trix-3d="cubes" aria-hidden="true"></div><span class="trix-hero__hint" aria-hidden="true">arraste para girar</span></div></div></section>')),
}
TEMPLATES["archive"] = TEMPLATES["index"]; TEMPLATES["search"] = TEMPLATES["index"]

GLOBAL_STYLES = {
  "settings": {"color": {"palette": [
      {"slug": "trix-amarelo", "name": "Amarelo Trix", "color": "#F8CD4B"}, {"slug": "trix-grafite", "name": "Grafite Trix", "color": "#424346"},
      {"slug": "trix-grafite-escuro", "name": "Grafite escuro", "color": "#2B2C2F"}, {"slug": "trix-off-white", "name": "Off-white", "color": "#FAF9F7"},
      {"slug": "trix-areia", "name": "Areia", "color": "#E4E0D6"}, {"slug": "trix-dourado", "name": "Dourado", "color": "#A08E5D"}, {"slug": "trix-branco", "name": "Branco", "color": "#FFFFFF"}]},
    "typography": {"fontFamilies": [{"slug": "open-sans", "name": "Open Sans", "fontFamily": "'Open Sans', system-ui, sans-serif"}]},
    "layout": {"contentSize": "1240px", "wideSize": "1400px"}},
  "styles": {"color": {"background": "#FAF9F7", "text": "#424346"}, "typography": {"fontFamily": "var:preset|font-family|open-sans", "fontSize": "1.0625rem", "lineHeight": "1.65"}, "spacing": {"blockGap": "0"},
             "elements": {"link": {"color": {"text": "#424346"}}, "heading": {"typography": {"fontFamily": "var:preset|font-family|open-sans"}}},
             "css": ".wp-site-blocks{padding:0}"}
}

# ---------------------------------------------------------------- pages
def breadcrumb(page, by_slug):
    chain = []; p = page
    while p:
        chain.append({"name": p.get("short", re.sub("<[^>]+>", "", p["h1"])), "url": p["url"]}); p = by_slug.get(p.get("parent"))
    chain.append({"name": "Home", "url": "/"}); return list(reversed(chain))
# Cena 3D (e ícone de reserva) do banner de cada página: sempre no lado oposto ao texto
SCENES = {
 "home": ("network", "network"), "empresa": ("cubes", "building"), "eventos": ("orbit", "graduation"), "clientes": ("globe", "globe"),
 "servicos": ("cubes", "grid"), "fabrica-de-software": ("layers", "code"), "conectividade-em-saude": ("pulse", "activity"), "consultoria": ("orbit", "briefcase"),
 "escritorio-de-processos": ("gears", "flow"), "solucoes-e-automacao": ("gears", "gear"), "agenda-medica": ("pulse", "calendar"), "oracle-partner": ("cloud", "cloud"),
 "produtos": ("cubes", "layers"), "prontow": ("pulse", "stethoscope"), "saw": ("network", "monitor"), "portal-operadora": ("globe", "globe"),
 "aspect-face": ("face", "face"), "xield": ("face", "lock"), "gedai": ("docs", "file"), "integrador": ("network", "plug"), "intranet": ("orbit", "intranet"),
 "conformidade": ("shield", "shield"), "lgpd": ("shield", "lock"), "politica-de-privacidade": ("shield", "eye"), "compliance": ("shield", "scale"),
 "codigo-de-conduta": ("docs", "book"), "seguranca-da-informacao": ("shield", "cpu"), "cookies": ("orbit", "cookie"), "glossario-lgpd": ("docs", "book"),
 "esg": ("globe", "leaf"), "inteligencia-artificial": ("neural", "brain"), "contato": ("network", "chat"), "canal-lgpd": ("shield", "lock"), "canal-de-compliance": ("shield", "megaphone"),
}
def img_dims(key):
    try:
        from PIL import Image
        path = os.path.join(IMG_DIR, key.split("images/", 1)[1]) if key.startswith("images/") else None
        if path and os.path.exists(path):
            with Image.open(path) as im: return im.size
    except Exception: pass
    return None
def img_contrast(key, bg=(0x27, 0x28, 0x2B)):
    """Contraste (WCAG) entre a cor média dos pixels opacos da imagem e o fundo escuro do banner."""
    try:
        from PIL import Image
        path = os.path.join(IMG_DIR, key.split("images/", 1)[1]) if key.startswith("images/") else None
        if not path or not os.path.exists(path): return None
        with Image.open(path) as im:
            im = im.convert("RGBA"); im.thumbnail((200, 200))
            px = [q for q in im.getdata() if q[3] > 200]
        if not px: return None
        def lum(c):
            f = lambda v: (v / 255) / 12.92 if v / 255 <= 0.03928 else (((v / 255) + 0.055) / 1.055) ** 2.4
            return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2])
        a = lum(tuple(sum(q[i] for q in px) / len(px) for i in range(3))); b = lum(bg)
        return (max(a, b) + 0.05) / (min(a, b) + 0.05)
    except Exception:
        return None
def hero(page, crumbs):
    kind = page.get("hero", "dark")
    cls = "trix-hero" + (" trix-hero--light" if kind == "light" else "") + (" trix-hero--tall" if page.get("hero_short") is False else "")
    bc = '<nav class="trix-breadcrumb" aria-label="Você está em">' + ' <span aria-hidden="true">›</span> '.join(('<a href="%s">%s</a>' % (c["url"], html.escape(c["name"]))) if i < len(crumbs) - 1 else '<span aria-current="page">%s</span>' % html.escape(c["name"]) for i, c in enumerate(crumbs)) + '</nav>' if len(crumbs) > 1 else ''
    meta = ('<div class="trix-hero__meta">' + "".join("<span>%s</span>" % m for m in page["meta"]) + "</div>") if page.get("meta") else ""
    acts = ('<div class="trix-actions">' + "".join('<a class="trix-btn %s" href="%s">%s</a>' % (a[2] if len(a) > 2 else "trix-btn--primary", a[1], a[0]) for a in page["actions"]) + "</div>") if page.get("actions") else ""
    # título e texto em blocos separados: no celular o visual 3D entra entre eles (grid-template-areas)
    inner = ('<div class="trix-hero__head">%s%s<h1>%s</h1></div><div class="trix-hero__body"><p class="lead">%s</p>%s%s</div>'
             % (bc, ('<span class="trix-kicker">%s</span>' % page["kicker"]) if page.get("kicker") else "", page["h1"], page["lead"], acts, meta))
    scene, icon = SCENES.get(page["slug"], ("network", "dot"))
    scene = page.get("three") or scene
    floatimg = ""
    if page.get("hero_img"):
        m = re.match(r"\{\{media:([^}]+)\}\}", page["hero_img"]); key = m.group(1) if m else None
        dims = img_dims(key) if key else None
        # logotipos pequenos ou escuros demais para o fundo do banner ganham um cartão branco
        cr = img_contrast(key) if key else None
        card = page.get("hero_img_card", bool(dims and dims[0] <= 420 and dims[1] <= 200) or (cr is not None and cr < 3))
        wh = (' width="%d" height="%d"' % dims) if dims else ""
        floatimg = '<div class="trix-hero__float%s"><img src="%s" alt="%s"%s loading="eager" decoding="async"></div>' % (" trix-hero__float--card" if card else "", page["hero_img"], html.escape(page.get("hero_img_alt", "")), wh)
    visual = ('<div class="trix-hero__visual">'
              '<div class="trix-hero__fallback" aria-hidden="true"><i>{{icon:%s}}</i></div>'
              '<div class="trix-hero__stage" data-trix-3d="%s" aria-hidden="true"></div>'
              '%s<span class="trix-hero__hint" aria-hidden="true">arraste para girar</span></div>') % (icon, scene, floatimg)
    return '<section class="%s"><div class="trix-container trix-hero__grid">%s%s</div></section>' % (cls, inner, visual)
def build_pages():
    by_slug = {p["slug"]: p for p in ALL_PAGES}
    for p in ALL_PAGES:
        parent = by_slug.get(p.get("parent"))
        p["url"] = ((parent["url"].rstrip("/") if parent else "") + "/" + p["slug"] + "/") if p["slug"] != "home" else "/"
    out = []
    for p in ALL_PAGES:
        crumbs = breadcrumb(p, by_slug) if p["slug"] != "home" else [{"name": "Home", "url": "/"}]
        seo = {"title": p["title"], "description": p["description"], "type": p.get("type", "page"), "breadcrumb": crumbs[1:] and crumbs or [], "image": media(p.get("image") or "images/02.jpg")}
        if p.get("canonical"): seo["canonical"] = p["canonical"]
        for k in ("faq", "productName", "category", "serviceName", "serviceType"):
            if p.get(k): seo[k] = p[k]
        if p.get("type") == "service" and not p.get("serviceName"):
            seo["serviceName"] = re.sub(r"\s*\|\s*Trix TI\s*$", "", p.get("short") or p["title"])
        extra = ""
        if p.get("faq") and 'class="trix-acc' not in p["body"]:
            from content.helpers import sec, head, faq as faq_html
            extra = sec(head("Perguntas frequentes", "Dúvidas <strong>comuns</strong>", "", True) + faq_html([(q["q"], q["a"]) for q in p["faq"]]), "trix-section--sand")
        body = render((hero(p, crumbs) if not p.get("no_hero") else "") + p["body"] + extra)
        # âncora estável para a primeira seção de conteúdo (usada por botões como "Ver módulos")
        if not p.get("no_hero"):
            m = re.compile(r'<section class="([^"]*)"(?![^>]*\bid=)').search(body, body.find("</section>"))
            if m: body = body[:m.start()] + '<section class="%s" id="visao-geral"' % m.group(1) + body[m.end():]
        after = body.split("</h1>", 1)[1] if "</h1>" in body else ""
        first = re.search(r"<h([23])[\s>]", after)
        if first and first.group(1) == "3":
            label = p.get("sr_h2") or {"product": "Recursos e benefícios: %s", "service": "O que oferecemos em %s", "contact": "Formulário e canais: %s"}.get(p.get("type"), "Conteúdo: %s") % p.get("short", re.sub("<[^>]+>", "", p["h1"]))
            i = body.index("</section>", body.index("</h1>")) + len("</section>")
            j = body.index('<div class="trix-container">', i) + len('<div class="trix-container">')
            body = body[:j] + '<h2 class="trix-sr">%s</h2>' % html.escape(label) + body[j:]
        # FAQPage do JSON-LD sempre igual ao FAQ visível da página (diretriz do Google)
        vis = re.findall(r'<details><summary>(.*?)</summary><div>(.*?)</div></details>', body, re.S)
        if vis:
            plain = lambda t: re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", t))).strip()
            seo["faq"] = [{"q": plain(q), "a": plain(a)} for q, a in vis]
        else:
            seo.pop("faq", None)
        seo["build"] = hashlib.sha1((body + json.dumps(seo, sort_keys=True, ensure_ascii=False)).encode("utf-8")).hexdigest()[:12]
        content = wp_html('<script type="application/json" id="trix-seo">%s</script>\n%s' % (json.dumps(seo, ensure_ascii=False), body))
        out.append({"slug": p["slug"], "parent": p.get("parent"), "url": p["url"], "title": p["title"], "build": seo["build"], "wp_title": p.get("wp_title") or p.get("short") or re.sub("<[^>]+>", "", p["h1"]),
                    "content": content, "excerpt": p["description"], "menu_order": p.get("order", 10), "comments": p.get("comments", False), "template": p.get("template", "")})
    return out

def main():
    media_path = None
    if "--media" in sys.argv: media_path = sys.argv[sys.argv.index("--media") + 1]
    load_media(media_path)
    for sub in ("blocks", "templates", "pages"): os.makedirs(os.path.join(DIST, sub), exist_ok=True)
    blocks = {"estilos": styles_html(), "cabecalho": header_html(), "rodape": footer_html(), "widgets": widgets_html()}
    for k, v in blocks.items(): open(os.path.join(DIST, "blocks", k + ".html"), "w", encoding="utf-8").write(wp_html(render(v)))
    pages = build_pages()
    # A home vive no template "home" (página inicial do blog): evita a URL duplicada /home/ e dispensa configurar página estática.
    home = next((p for p in pages if p["slug"] == "home"), None)
    if home:
        TEMPLATES["home"] = template(home["content"]); home["in_template"] = True
    for k, v in TEMPLATES.items(): open(os.path.join(DIST, "templates", k + ".html"), "w", encoding="utf-8").write(render(v))
    json.dump(GLOBAL_STYLES, open(os.path.join(DIST, "global-styles.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    for p in pages: json.dump(p, open(os.path.join(DIST, "pages", p["slug"] + ".json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    json.dump([{k: p[k] for k in ("slug", "parent", "url", "title", "menu_order")} for p in pages], open(os.path.join(DIST, "pages-index.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    if MISSING_MEDIA: print("AVISO: mídias sem mapeamento (usando URL do site antigo):", sorted(MISSING_MEDIA))
    # sanidade: toda <img> precisa de src de imagem (data:, /, http) e nenhum aria-label pode conter data URI
    bad = []
    for name, html_ in list(blocks.items()) + [(p["slug"], p["content"]) for p in pages]:
        for src in re.findall(r'<img[^>]*\ssrc="([^"]*)"', html_):
            if not re.match(r"^(data:image/|/|https?://)", src): bad.append((name, src[:60]))
        if re.search(r'aria-label="data:', html_): bad.append((name, "aria-label com data URI"))
    if bad: sys.exit("ERRO de marcação de imagem: %s" % bad[:10])
    sizes = {k: len(v) for k, v in blocks.items()}
    print("blocks:", sizes); print("pages:", len(pages), "max page bytes:", max(len(p["content"]) for p in pages))
if __name__ == "__main__": main()
