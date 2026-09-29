# -*- coding: utf-8 -*-
"""Publica o site bsagro.agr.br no WordPress via Easy MCP AI.
Etapas: settings → media → skeleton (garante páginas/posts e lê os permalinks) → css → blocks → templates → pages → posts → cleanup.
Uso: python3 site/deploy.py [settings|media|skeleton|css|blocks|templates|pages|posts|cleanup|forms|all]
Estado persistido em site/state.json (IDs de mídia, blocos, páginas, posts e links).

Links internos: são gerados a partir do permalink real de cada página (hoje o site usa links simples ?page_id=).
Depois de ativar os links permanentes "Nome do post" no WordPress, rode de novo `python3 site/deploy.py all`
para que todos os links passem ao formato /slug/."""
import os, sys, json, re, base64, html as H
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
sys.path.insert(0, ROOT); sys.path.insert(0, HERE)
import mcp
import tpl
import seo
import content_pages as CP

SITE = seo.SITE
# Canais oficiais. O pedido original citava "@bragro.agr.br", domínio que não existe (sem DNS);
# o domínio do site e do e-mail (MX) é bsagro.agr.br. Para trocar, edite aqui e rode `deploy.py all`.
EMAILS = {"contato": "contato@bsagro.agr.br", "dpo": "dpo@bsagro.agr.br", "ouvidoria": "ouvidoria@bsagro.agr.br"}
PLUGIN = "eb-credito-rural/eb-credito-rural.php"
CATEGORIES = ["Crédito Estruturado", "Garantias & Patrimônio", "Recuperação Judicial", "Instrumentos do Agro", "Gestão Financeira"]

STATE_PATH = os.path.join(HERE, "state.json")
state = json.load(open(STATE_PATH, encoding="utf-8")) if os.path.exists(STATE_PATH) else {}
for k in ("media", "blocks", "pages", "posts", "links", "post_links", "tags", "cats"):
    state.setdefault(k, {})

def save():
    tmp = STATE_PATH + ".tmp"
    json.dump(state, open(tmp, "w", encoding="utf-8"), indent=1, ensure_ascii=False, sort_keys=True)
    os.replace(tmp, STATE_PATH)

def read(p): return open(os.path.join(HERE, p), encoding="utf-8").read()
CONTENT = os.path.join(ROOT, "content")
def jload(name, default):
    p = os.path.join(CONTENT, name)
    return json.load(open(p, encoding="utf-8")) if os.path.exists(p) else default
POLICIES = jload("policies.json", {})
POSTS = jload("posts.json", [])

def call(tool, args):
    r = mcp.call(tool, args)
    if isinstance(r, dict) and "error" in r:
        raise RuntimeError("%s -> %s" % (tool, json.dumps(r["error"], ensure_ascii=False)[:800]))
    return r

# ---------------------------------------------------------------- resolução de placeholders
def rel(url):
    return url[len(SITE):] if url and url.startswith(SITE) else (url or "/")

def U(slug):
    return rel(state["links"].get(slug)) if slug in state["links"] else "/?pagename=" + slug

def POSTU(slug):
    return rel(state["post_links"].get(slug)) if slug in state["post_links"] else "/?name=" + slug

def IMG(key):
    m = state["media"].get(key)
    return m["url"] if m else ""

def LINK(slug):
    return {"home-solucoes": "/#solucoes", "home-como-funciona": "/#como-funciona", "home": "/"}.get(slug) or U(slug)

def resolve(s):
    s = s.replace("{{HOME}}", "/")
    s = re.sub(r"\{\{U:([a-z0-9-]+)\}\}", lambda m: U(m.group(1)), s)
    s = re.sub(r"\{\{LINK:([a-z0-9-]+)\}\}", lambda m: LINK(m.group(1)), s)
    s = re.sub(r"\{\{POST:([a-z0-9-]+)\}\}", lambda m: POSTU(m.group(1)), s)
    s = re.sub(r"\{\{IMG:([A-Za-z0-9_-]+)\}\}", lambda m: IMG(m.group(1)), s)
    s = re.sub(r"\{\{EMAIL:([a-z]+)\}\}", lambda m: EMAILS[m.group(1)], s)
    s = re.sub(r"\{\{POLICY:([a-z0-9-]+)\}\}", lambda m: resolve(POLICIES.get(m.group(1), {}).get("content_html", "")), s)
    s = s.replace("{{HEADER_CLASS}}", "")
    left = re.findall(r"\{\{[A-Z]+:?[^}]*\}\}", s)
    if left and "{{PORTAL}}" not in left and "{{SPLIT}}" not in left:
        print("  AVISO: placeholders não resolvidos:", sorted(set(left))[:8])
    return s

def html_block(s): return "<!-- wp:html -->\n%s\n<!-- /wp:html -->\n" % s.strip("\n")

# ---------------------------------------------------------------- HTML simples → blocos Gutenberg
def to_blocks(html_src):
    """Converte h2/h3/h4/p/ul/ol/blockquote/table em blocos nativos; o resto (div.bs-box etc.) vira bloco HTML."""
    s = html_src.strip(); out = []; pos = 0
    token = re.compile(r"<(h2|h3|h4|p|ul|ol|blockquote|table|div|section|figure|hr)(\s[^>]*)?>", re.I)
    while pos < len(s):
        m = token.search(s, pos)
        if not m:
            rest = s[pos:].strip()
            if rest: out.append(("html", rest))
            break
        if m.start() > pos:
            gap = s[pos:m.start()].strip()
            if gap: out.append(("html", gap))
        tag = m.group(1).lower()
        if tag == "hr":
            out.append(("hr", "")); pos = m.end(); continue
        depth, i = 1, m.end()
        open_re = re.compile(r"<%s(\s[^>]*)?>" % tag, re.I); close_re = re.compile(r"</%s\s*>" % tag, re.I)
        while depth and i < len(s):
            mo, mc = open_re.search(s, i), close_re.search(s, i)
            if not mc: i = len(s); break
            if mo and mo.start() < mc.start(): depth += 1; i = mo.end()
            else: depth -= 1; i = mc.end()
        full = s[m.start():i]
        inner = re.sub(r"</%s\s*>\s*$" % tag, "", s[m.end():i], flags=re.I).strip()
        attrs = m.group(2) or ""
        if tag in ("h2", "h3", "h4"):
            lvl = int(tag[1]); idm = re.search(r'id="([^"]+)"', attrs); ida = ' id="%s"' % idm.group(1) if idm else ""
            out.append(("block", '<!-- wp:heading {"level":%d} -->\n<h%d class="wp-block-heading"%s>%s</h%d>\n<!-- /wp:heading -->' % (lvl, lvl, ida, inner, lvl)))
        elif tag == "p":
            if re.sub(r"<[^>]+>|&nbsp;|\s", "", inner):
                out.append(("block", "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->" % inner))
        elif tag in ("ul", "ol"):
            items = re.findall(r"<li(?:\s[^>]*)?>(.*?)</li>", inner, flags=re.S | re.I)
            lis = "".join("<!-- wp:list-item -->\n<li>%s</li>\n<!-- /wp:list-item -->\n" % it.strip() for it in items)
            attr = '{"ordered":true} ' if tag == "ol" else ""
            out.append(("block", '<!-- wp:list %s-->\n<%s class="wp-block-list">\n%s</%s>\n<!-- /wp:list -->' % (attr, tag, lis, tag)))
        elif tag == "blockquote":
            ps = re.findall(r"<p(?:\s[^>]*)?>(.*?)</p>", inner, flags=re.S | re.I) or [re.sub(r"<[^>]+>", "", inner)]
            body = "".join("<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n" % p.strip() for p in ps)
            out.append(("block", '<!-- wp:quote -->\n<blockquote class="wp-block-quote">\n%s</blockquote>\n<!-- /wp:quote -->' % body))
        elif tag == "table":
            out.append(("block", '<!-- wp:table -->\n<figure class="wp-block-table">%s</figure>\n<!-- /wp:table -->' % full))
        else:
            out.append(("html", full))
        pos = i
    blocks = []
    for kind, val in out:
        if kind == "block": blocks.append(val)
        elif kind == "hr": blocks.append('<!-- wp:separator -->\n<hr class="wp-block-separator has-alpha-channel-opacity"/>\n<!-- /wp:separator -->')
        else: blocks.append("<!-- wp:html -->\n%s\n<!-- /wp:html -->" % val)
    return "\n\n".join(blocks)

# ---------------------------------------------------------------- SETTINGS
def step_settings():
    print("== CONFIGURAÇÕES ==")
    call("wp_update_site_settings", {"title": "BS Agro Capital", "description": "Crédito estruturado para quem produz",
                                     "timezone": "America/Sao_Paulo", "date_format": "d/m/Y", "time_format": "H:i", "posts_per_page": 9})
    print("  título, descrição, fuso, formatos de data e posts por página atualizados")

# ---------------------------------------------------------------- MEDIA
ALIASES = {"bs-agro-logo-white": "logo-white", "whatsapp": "whatsapp-icon"}

def media_files():
    files = []
    for base, _, names in os.walk(os.path.join(ROOT, "assets")):
        for n in sorted(names):
            if n.lower().endswith((".png", ".jpg", ".jpeg", ".webp")):
                files.append(os.path.join(base, n))
    out_dir = os.path.join(ROOT, "imggen", "out")
    if os.path.isdir(out_dir):
        files += [os.path.join(out_dir, n) for n in sorted(os.listdir(out_dir)) if n.endswith(".jpg")]
    return files

ALT = {
    "logo-white": "BS Agro Capital", "whatsapp-icon": "", "favicon-32": "BS Agro Capital", "favicon-512": "BS Agro Capital", "apple-touch-icon": "BS Agro Capital",
    "og-bs-agro-capital": "BS Agro Capital — capital para fortalecer quem produz", "og-conformidade": "BS Agro Capital — conformidade, LGPD e integridade",
    "og-area-do-cliente": "BS Agro Capital — Área do Cliente",
}

def step_media():
    print("== MÍDIA ==")
    posts_by_cover = {"capa-" + p["slug"]: p for p in POSTS}
    for path in media_files():
        name = os.path.basename(path); stem = name.rsplit(".", 1)[0]
        key = ALIASES.get(stem, stem); size = os.path.getsize(path)
        cur = state["media"].get(key)
        if cur and cur.get("size") == size and cur.get("file") == name:
            continue
        alt = ALT.get(key)
        if alt is None:
            alt = ("Capa da matéria: " + posts_by_cover[key]["title"]) if key in posts_by_cover else stem.replace("-", " ").capitalize()
        title = posts_by_cover[key]["title"] if key in posts_by_cover else "BS Agro Capital — " + stem.replace("-", " ")
        r = call("wp_upload_media", {"filename": name, "content_base64": base64.b64encode(open(path, "rb").read()).decode(), "title": title, "alt_text": alt})
        mid = r.get("id"); url = r.get("source_url") or r.get("url")
        if cur and cur.get("id") and cur.get("id") != mid:
            try: call("wp_delete_media", {"media_id": cur["id"], "force": True})
            except RuntimeError as e: print("  (não removi a versão antiga)", e)
        state["media"][key] = {"id": mid, "url": url, "file": name, "size": size}
        save(); print("  enviada:", key, mid)

# ---------------------------------------------------------------- SKELETON: garante páginas/posts e lê links
def all_pages():
    return [pg["slug"] for pg in CP.LANDING_PAGES] + [CP.MATERIAS["slug"]] + CP.POLICY_PAGES

PAGE_TITLES = {pg["slug"]: pg["title"] for pg in CP.LANDING_PAGES}
PAGE_TITLES[CP.MATERIAS["slug"]] = CP.MATERIAS["title"]

def refresh_links():
    for status in ("publish", "draft"):
        r = call("wp_list_pages", {"status": status, "per_page": 100})
        for p in r.get("pages", []):
            if p["slug"] in state["pages"] or p["slug"] in all_pages():
                state["pages"][p["slug"]] = p["id"]; state["links"][p["slug"]] = p["link"]
        r = call("wp_list_posts", {"status": status, "per_page": 100})
        for p in r.get("posts", []):
            if p.get("slug") in state["posts"] or p.get("slug") in [x["slug"] for x in POSTS]:
                state["posts"][p["slug"]] = p["id"]; state["post_links"][p["slug"]] = p["link"]
    save()

def step_skeleton():
    print("== ESQUELETO (páginas e matérias) ==")
    refresh_links()
    for slug in all_pages():
        if slug in state["pages"]:
            continue
        title = PAGE_TITLES.get(slug) or POLICIES.get(slug, {}).get("title") or slug.replace("-", " ").capitalize()
        r = call("wp_create_page", {"title": title, "slug": slug, "status": "publish", "content": "<!-- wp:paragraph --><p>Em publicação.</p><!-- /wp:paragraph -->", "comment_status": "closed"})
        state["pages"][slug] = r["id"]; state["links"][slug] = r.get("link"); save(); print("  página criada:", slug, r["id"], r.get("link"))
    for p in POSTS:
        if p["slug"] in state["posts"]:
            continue
        r = call("wp_create_post", {"title": p["title"], "slug": p["slug"], "status": "draft", "content": "", "comment_status": "closed"})
        state["posts"][p["slug"]] = r["id"]; state["post_links"][p["slug"]] = r.get("link"); save(); print("  matéria criada (rascunho):", p["slug"], r["id"])
    refresh_links()
    mode = "pretty" if any("?page_id=" not in (l or "") for l in state["links"].values()) else "plain"
    state["permalinks"] = mode; save(); print("  links permanentes:", mode)

# ---------------------------------------------------------------- CSS
def step_css():
    print("== CSS ==")
    css_path = os.path.join(HERE, "build", "bs.min.css")
    if not os.path.exists(css_path):
        raise SystemExit("Compile o CSS antes: bash site/build_css.sh")
    css = "/* BS Agro Capital — gerado por site/build_css.sh; não edite aqui */\n" + open(css_path, encoding="utf-8").read()
    r = call("wp_update_custom_css", {"css": css, "mode": "replace"})
    print("  CSS adicional atualizado:", r.get("length"), "bytes")

# ---------------------------------------------------------------- BLOCOS SINCRONIZADOS
def build_header_block():
    head = seo.head_links(IMG("favicon-512"), IMG("favicon-32"), IMG("apple-touch-icon"))
    head += seo.global_jsonld(EMAILS, IMG("favicon-512"), IMG("og-bs-agro-capital"), SITE + "/?s={search_term_string}")
    fonts = ('<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>'
             '<link rel="preload" as="font" type="font/woff2" href="https://cdn.jsdelivr.net/npm/@fontsource/raleway@5/files/raleway-latin-600-normal.woff2" crossorigin>'
             '<link rel="preload" as="font" type="font/woff2" href="https://cdn.jsdelivr.net/npm/@fontsource/roboto@5/files/roboto-latin-400-normal.woff2" crossorigin>')
    return html_block(fonts + head + "\n" + resolve(read("header.html")))

def build_footer_block():
    js = read("bs.js")
    return html_block(resolve(read("footer.html")) + '\n<script src="https://cdn.jsdelivr.net/npm/lenis@1.3.26/dist/lenis.min.js"></script>\n<script>\n' + js + "\n</script>")

def upsert_block(key, title, content):
    bid = state["blocks"].get(key)
    if bid:
        call("wp_update_block", {"block_id": bid, "content": content, "title": title, "status": "publish"}); print("  bloco atualizado:", key, bid)
    else:
        r = call("wp_create_block", {"title": title, "content": content, "status": "publish"})
        bid = r["id"]; state["blocks"][key] = bid; save(); print("  bloco criado:", key, bid)
    return bid

def step_blocks():
    print("== BLOCOS (cabeçalho e rodapé) ==")
    upsert_block("header", "BS · Cabeçalho (acessibilidade, idiomas, menu, Área do Cliente)", build_header_block())
    upsert_block("footer", "BS · Rodapé (canais, conformidade, cookies, VLibras, scripts)", build_footer_block())

# ---------------------------------------------------------------- TEMPLATES
def home_faq():
    s = read("home.html")
    out = []
    for q, a in re.findall(r'<summary class="faq-item__question">\s*(.*?)\s*<svg.*?</summary>\s*<p class="faq-item__answer">(.*?)</p>', s, re.S):
        out.append((H.unescape(re.sub(r"<[^>]+>", "", q)).strip(), H.unescape(re.sub(r"<[^>]+>", "", resolve(a))).strip()))
    return out

def step_templates():
    print("== TEMPLATES ==")
    hid, fid = state["blocks"]["header"], state["blocks"]["footer"]
    before, after = resolve(read("home.html")).split("{{SPLIT}}")
    home_seo = seo.seo_block("BS Agro Capital | Crédito estruturado para produtores rurais e empresas do agro",
                             "Estruturação de crédito para produtores rurais e empresas do agronegócio: Plano Safra, CPR, fundos de investimento, garantia de imóvel e Recuperação Judicial, do diagnóstico à execução.",
                             SITE + "/", IMG("og-bs-agro-capital"), breadcrumbs=[("Início", SITE + "/")], faq=home_faq(),
                             keywords=["BS Agro Capital", "crédito rural", "crédito agro", "Plano Safra", "CPR", "crédito estruturado", "fundos de investimento", "garantia de imóvel", "recuperação judicial", "Goiânia"])
    list_seo = seo.seo_block("Matérias — BS Agro Capital", CP.MATERIAS["excerpt"], SITE + U("materias"), IMG("og-bs-agro-capital"))
    templates = {
        "home": home_seo + tpl.tpl_home(hid, fid, (before, after)),
        "index": list_seo + tpl.tpl_listing(hid, fid, "index"),
        "page": tpl.tpl_page(hid, fid),
        "page-no-title": tpl.tpl_page_landing(hid, fid),
        "single": tpl.tpl_single(hid, fid),
        "archive": list_seo + tpl.tpl_listing(hid, fid, "archive"),
        "search": tpl.tpl_listing(hid, fid, "search"),
        "404": tpl.tpl_404(hid, fid),
    }
    for slug, content in templates.items():
        call("wp_update_template", {"template_id": "twentytwentyfive//" + slug, "content": resolve(content)})
        print("  template atualizado:", slug, len(content), "bytes")

# ---------------------------------------------------------------- PÁGINAS
def plugin_active():
    try:
        r = call("wp_list_plugins", {"status": "active"})
        return any(p.get("plugin") == PLUGIN for p in r.get("plugins", []))
    except RuntimeError:
        return False

def update_page(slug, title, content, excerpt, template):
    args = {"page_id": state["pages"][slug], "title": title, "content": content, "status": "publish", "slug": slug,
            "excerpt": excerpt, "comment_status": "closed", "template": "" if template == "page" else template}
    call("wp_update_page", args); print("  página publicada:", slug, state["pages"][slug])

def crumbs(title, slug):
    return [("Início", SITE + "/"), (title, SITE + U(slug))]

OG_BY_SLUG = {"area-do-cliente": "og-area-do-cliente", "conformidade": "og-conformidade", "portal-do-titular": "og-conformidade",
              "canal-de-integridade": "og-conformidade"}

def step_pages():
    print("== PÁGINAS ==")
    only = set(x for x in os.environ.get("BS_PAGES", "").split(",") if x)
    active = plugin_active()
    print("  plugin EB Crédito Rural ativo:", active)
    for pg in CP.LANDING_PAGES:
        if only and pg["slug"] not in only: continue
        head = seo.seo_block(pg.get("seo_title") or pg["title"], pg["excerpt"], SITE + U(pg["slug"]), IMG(OG_BY_SLUG.get(pg["slug"], "og-bs-agro-capital")),
                             breadcrumbs=crumbs(pg["title"], pg["slug"]), keywords=pg.get("keywords"), robots=pg.get("robots"))
        parts = resolve(pg["html"]).split("{{PORTAL}}")
        body = html_block(parts[0])
        for extra in parts[1:]:
            body += ("<!-- wp:shortcode -->\n[ebcr_portal]\n<!-- /wp:shortcode -->\n" if active else html_block(resolve(CP.PORTAL_FALLBACK))) + html_block(extra)
        update_page(pg["slug"], pg["title"], head + body, pg["excerpt"], pg["template"])
    if not only or "materias" in only:
        mat = CP.MATERIAS
        head = seo.seo_block(mat["seo_title"], mat["excerpt"], SITE + U("materias"), IMG("og-bs-agro-capital"), breadcrumbs=crumbs("Matérias", "materias"))
        body = html_block(resolve(mat["html_before"])) + resolve(tpl.posts_grid(query_id=11, per_page=9, pagination=True)) + html_block(resolve(mat["html_after"]))
        update_page("materias", mat["title"], head + body, mat["excerpt"], mat["template"])
    for slug in CP.POLICY_PAGES:
        if only and slug not in only: continue
        pd = POLICIES.get(slug)
        if not pd:
            print("  (sem texto ainda)", slug); continue
        head = seo.seo_block(pd["title"] + " — BS Agro Capital", pd.get("excerpt", ""), SITE + U(slug), IMG("og-conformidade"),
                             breadcrumbs=[("Início", SITE + "/"), ("Conformidade", SITE + U("conformidade")), (pd["title"], SITE + U(slug))])
        meta = html_block('<div class="bs-doc-meta"><span><svg aria-hidden="true"><use href="#i-shield"/></svg>BS Agro Capital · CNPJ 66.308.795/0001-08</span>'
                          '<span><svg aria-hidden="true"><use href="#i-mail"/></svg>Dúvidas: <a href="mailto:%s">%s</a></span>'
                          '<span><svg aria-hidden="true"><use href="#i-doc"/></svg><a href="%s">Todos os documentos</a></span></div>'
                          % ((EMAILS["dpo"],) * 2 + (U("conformidade"),)))
        update_page(slug, pd["title"], head + meta + to_blocks(resolve(pd["content_html"])), pd.get("excerpt", ""), "page")

# ---------------------------------------------------------------- MATÉRIAS
def term_id(kind, name):
    store = state["cats" if kind == "category" else "tags"]
    key = name.strip().lower()
    if key in store: return store[key]
    lister, creator, field = ("wp_list_categories", "wp_create_category", "categories") if kind == "category" else ("wp_list_tags", "wp_create_tag", "tags")
    r = call(lister, {"search": name, "per_page": 50})
    tid = next((t["id"] for t in r.get(field, []) if H.unescape(t["name"]).strip().lower() == key), None)
    if not tid:
        tid = call(creator, {"name": name.strip()})["id"]
    store[key] = tid; save(); return tid

def step_posts():
    print("== MATÉRIAS ==")
    for p in POSTS:
        cat = term_id("category", p["category"])
        tags = [term_id("tag", t) for t in p.get("tags", [])][:6]
        cover = IMG("capa-" + p["slug"])
        url = SITE + POSTU(p["slug"])
        head = seo.seo_block(p.get("seo_title") or p["title"], p["excerpt"], url, cover or IMG("og-bs-agro-capital"), "article",
                             article={"date": p["date"] + "T09:30:00-03:00", "section": p["category"], "tags": p.get("tags", [])},
                             breadcrumbs=[("Início", SITE + "/"), ("Matérias", SITE + U("materias")), (p["title"], url)],
                             keywords=p.get("tags"), image_size=(1200, 675))
        args = {"post_id": state["posts"][p["slug"]], "title": p["title"], "content": head + to_blocks(resolve(p["content_html"])),
                "status": "publish", "slug": p["slug"], "excerpt": p["excerpt"], "categories": [cat], "tags": tags,
                "date": p["date"] + "T09:30:00", "comment_status": "closed"}
        mid = state["media"].get("capa-" + p["slug"], {}).get("id")
        if mid: args["featured_media"] = mid
        call("wp_update_post", args); print("  matéria publicada:", p["slug"], state["posts"][p["slug"]])
    refresh_links()

# ---------------------------------------------------------------- LIMPEZA do conteúdo de exemplo
def step_cleanup():
    print("== LIMPEZA ==")
    for p in call("wp_list_posts", {"status": "publish", "per_page": 100}).get("posts", []):
        if p.get("slug") in ("hello-world", "ola-mundo"):
            call("wp_delete_post", {"post_id": p["id"]}); print("  post de exemplo enviado à lixeira:", p["id"])
    for p in call("wp_list_pages", {"status": "publish", "per_page": 100}).get("pages", []):
        if p.get("slug") in ("sample-page", "pagina-exemplo"):
            call("wp_delete_page", {"page_id": p["id"]}); print("  página de exemplo enviada à lixeira:", p["id"])

# ---------------------------------------------------------------- FORMULÁRIOS: dispara a ativação do FormSubmit
def step_forms():
    """O FormSubmit exige um clique de ativação por endereço. Este passo envia uma mensagem de ativação para cada canal."""
    import subprocess
    print("== ATIVAÇÃO DOS FORMULÁRIOS (FormSubmit) ==")
    for canal, email in EMAILS.items():
        data = json.dumps({"_subject": "Ativação do formulário do site bsagro.agr.br (%s)" % canal, "mensagem": "Mensagem automática para ativar o envio de formulários do site bsagro.agr.br para este endereço. Clique em 'Activate Form' no e-mail do FormSubmit.", "_template": "table", "_captcha": "false"})
        p = subprocess.run(["curl", "-sS", "-m", "60", "-X", "POST", "https://formsubmit.co/ajax/" + email, "-H", "Content-Type: application/json",
                            "-H", "Accept: application/json", "-H", "Origin: https://bsagro.agr.br", "-H", "Referer: https://bsagro.agr.br/", "--data", data], capture_output=True, text=True)
        print("  ", email, "→", (p.stdout or p.stderr).strip()[:200])

STEPS = {"settings": step_settings, "media": step_media, "skeleton": step_skeleton, "css": step_css, "blocks": step_blocks,
         "templates": step_templates, "pages": step_pages, "posts": step_posts, "cleanup": step_cleanup, "forms": step_forms}
if __name__ == "__main__":
    what = sys.argv[1:] or ["all"]
    if what == ["all"]:
        what = ["settings", "media", "skeleton", "css", "blocks", "templates", "pages", "posts", "cleanup"]
    for w in what:
        STEPS[w]()
    save()
