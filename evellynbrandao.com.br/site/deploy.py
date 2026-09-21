# -*- coding: utf-8 -*-
"""Publica o site evellynbrandao.com.br no WordPress via Easy MCP AI.
Etapas: media → blocos (header/footer) → templates → páginas → matérias.
Uso: python3 deploy.py [media|blocks|templates|pages|posts|all]
Estado persistido em state.json (IDs de mídia, blocos, páginas e posts)."""
import os, sys, json, re, base64, html as H
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
sys.path.insert(0, ROOT); sys.path.insert(0, HERE)
import mcp
import tpl
import seo
import content_pages as CP

STATE_PATH = os.path.join(HERE, "state.json")
state = json.load(open(STATE_PATH)) if os.path.exists(STATE_PATH) else {"media": {}, "blocks": {}, "pages": {}, "posts": {}, "tags": {}}
def save():
    """Grava o estado mesclando com o que já está em disco (permite etapas em processos paralelos)."""
    disk = {}
    if os.path.exists(STATE_PATH):
        try: disk = json.load(open(STATE_PATH, encoding="utf-8"))
        except Exception: disk = {}
    for k, v in state.items():
        if isinstance(v, dict):
            merged = dict(disk.get(k, {})); merged.update(v); disk[k] = merged
        else:
            disk[k] = v
    tmp = STATE_PATH + ".tmp"
    json.dump(disk, open(tmp, "w", encoding="utf-8"), indent=1, ensure_ascii=False)
    os.replace(tmp, STATE_PATH)
    for k, v in disk.items():
        if isinstance(v, dict): state.setdefault(k, {}).update(v)

def read(p): return open(p, encoding="utf-8").read()
CSS = read(os.path.join(HERE, "eb.css"))
HEADER = read(os.path.join(HERE, "header.html"))
FOOTER = read(os.path.join(HERE, "footer.html"))
JS = read(os.path.join(HERE, "eb.js"))
JS3D = read(os.path.join(HERE, "eb3d.js"))
HOME = read(os.path.join(HERE, "home.html"))
IMG_DIR = os.path.join(ROOT, "imggen", "out")
CONTENT = os.path.join(ROOT, "content")

FONTS = ('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
         '<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">')

CATS = {"Crédito Rural": 3, "Estruturação & Capital": 4, "Gestão Financeira no Agro": 5, "Liderança & Alta Performance": 6, "Mercado & Tendências": 7, "ESG & Conformidade": 8}

def call(tool, args):
    r = mcp.call(tool, args)
    if isinstance(r, dict) and "error" in r:
        raise RuntimeError("%s -> %s" % (tool, json.dumps(r["error"], ensure_ascii=False)[:800]))
    return r

def img(name):
    m = state["media"].get(name)
    return m["url"] if m else ""

def fill_imgs(s):
    return re.sub(r"\{\{IMG:([a-z0-9-]+)\}\}", lambda m: img(m.group(1)), s)

# ---------------------------------------------------------------- HTML → blocos Gutenberg
def esc_attr(s): return s
def to_blocks(html_src):
    """Converte HTML simples (h2/h3/p/ul/ol/blockquote/table/div.eb-box/outros) em blocos do editor."""
    s = html_src.strip()
    out = []
    pos = 0
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
        # encontra o fechamento correspondente (considera aninhamento do mesmo tag)
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
            if not re.sub(r"<[^>]+>|&nbsp;|\s", "", inner): pos = i; continue
            out.append(("block", '<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->' % inner))
        elif tag in ("ul", "ol"):
            items = re.findall(r"<li(?:\s[^>]*)?>(.*?)</li>", inner, flags=re.S | re.I)
            lis = "".join('<!-- wp:list-item -->\n<li>%s</li>\n<!-- /wp:list-item -->\n' % it.strip() for it in items)
            ordered = ',"ordered":true' if tag == "ol" else ""
            out.append(("block", '<!-- wp:list {%s} -->\n<%s class="wp-block-list">\n%s</%s>\n<!-- /wp:list -->' % (ordered.lstrip(","), tag, lis, tag)))
        elif tag == "blockquote":
            ps = re.findall(r"<p(?:\s[^>]*)?>(.*?)</p>", inner, flags=re.S | re.I) or [re.sub(r"<[^>]+>", "", inner)]
            body = "".join('<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n' % p.strip() for p in ps)
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
        else: blocks.append('<!-- wp:html -->\n%s\n<!-- /wp:html -->' % val)
    return "\n\n".join(blocks)

def html_block(s): return '<!-- wp:html -->\n%s\n<!-- /wp:html -->\n' % s.strip("\n")

# ---------------------------------------------------------------- MEDIA
def upload(name, filename, title, alt):
    path = os.path.join(IMG_DIR, filename)
    if name in state["media"] and state["media"][name].get("file") == filename and state["media"][name].get("size") == os.path.getsize(path):
        print("  media já enviada:", name); return state["media"][name]
    b64 = base64.b64encode(open(path, "rb").read()).decode()
    r = call("wp_upload_media", {"filename": filename, "content_base64": b64, "title": title, "alt_text": alt})
    mid = r.get("id"); url = r.get("source_url") or r.get("url")
    if not url and mid:
        g = call("wp_get_media", {"media_id": mid}); url = g.get("source_url")
    state["media"][name] = {"id": mid, "url": url, "file": filename, "size": os.path.getsize(path)}
    save(); print("  media enviada:", name, mid, url); return state["media"][name]

def step_media():
    print("== MEDIA ==")
    upload("logo", "logo.png", "Logo Évellyn Brandão", "Logotipo de Évellyn Brandão — monograma EB com espiga dourada")
    upload("favicon", "favicon.png", "Ícone do site", "Ícone Évellyn Brandão")
    upload("logo-horizontal", "logo-horizontal.png", "Logo horizontal Évellyn Brandão", "Logotipo horizontal Évellyn Brandão")
    upload("og", "og.png", "Évellyn Brandão — compartilhamento", "Évellyn Brandão — CEO e Fundadora da BS Agro Capital")
    upload("visual-bs", "visual-bs.jpg", "BS Agro Capital — visual", "Composição dourada representando o campo, capital e estrutura")
    upload("visual-sobre", "visual-sobre.jpg", "Crédito é estrutura — visual", "Composição dourada com a frase Crédito não é sorte, crédito é estrutura")
    upload("portrait", "portrait.png", "Évellyn Brandão — retrato", "Évellyn Brandão")
    FOTOS = os.path.join(ROOT, "fotos", "out")
    BSLOGO = os.path.join(ROOT, "bslogo", "out")
    LOGOS = os.path.join(ROOT, "logos", "out")
    extra = [(FOTOS, "retrato", "evellyn-retrato.jpg", "Évellyn Brandão — retrato no campo", "Évellyn Brandão, CEO e Fundadora da BS Agro Capital, em lavoura de milho"),
             (FOTOS, "avatar", "evellyn-avatar.jpg", "Évellyn Brandão — avatar", "Évellyn Brandão"),
             (FOTOS, "campo", "evellyn-campo.jpg", "Évellyn Brandão no campo", "Évellyn Brandão em lavoura de milho ao entardecer"),
             (FOTOS, "campo-wide", "evellyn-campo-wide.jpg", "Évellyn Brandão — campo (panorâmica)", "Évellyn Brandão em lavoura de milho, vista panorâmica"),
             (BSLOGO, "bs-logo", "bs-agro-capital-logo.jpg", "BS Agro Capital — logotipo", "Logotipo da BS Agro Capital: monograma BS dourado sobre verde"),
             (BSLOGO, "bs-logo-t", "bs-agro-capital-logo-transparente.png", "BS Agro Capital — logotipo (fundo transparente)", "Logotipo da BS Agro Capital em dourado"),
             (BSLOGO, "bs-logo-t600", "bs-agro-capital-logo-transparente-600.png", "BS Agro Capital — logotipo pequeno", "Logotipo da BS Agro Capital em dourado"),
             (BSLOGO, "bs-logo-preto", "bs-agro-capital-logo-preto.jpg", "BS Agro Capital — logotipo sobre preto", "Logotipo da BS Agro Capital em dourado sobre preto")]
    for name in ("evellyn-brandao-logo-monograma-dourado.png", "evellyn-brandao-logo-horizontal-dourado.png", "evellyn-brandao-logo-vertical-dourado.png",
                 "evellyn-brandao-logo-horizontal-preta.png", "evellyn-brandao-logo-horizontal-branca.png", "evellyn-brandao-logos.zip"):
        extra.append((LOGOS, "dl-" + name.rsplit(".", 1)[0], name, "Logo Évellyn Brandão — " + name.rsplit(".", 1)[0].replace("evellyn-brandao-logo-", "").replace("-", " "), "Logotipo de Évellyn Brandão"))
    for d, key, fn, title, alt in extra:
        if os.path.exists(os.path.join(d, fn)):
            _orig = globals()["IMG_DIR"]; globals()["IMG_DIR"] = d
            try: upload(key, fn, title, alt)
            finally: globals()["IMG_DIR"] = _orig
    for p in load_posts():
        fn = "banner-%s.jpg" % p["slug"]
        if os.path.exists(os.path.join(IMG_DIR, fn)):
            upload("banner-" + p["slug"], fn, p["title"], "Banner da matéria: " + p["title"])

# ---------------------------------------------------------------- BLOCOS (cabeçalho/rodapé sincronizados)
def build_header_block():
    head = seo.head_links(img("favicon"), img("logo")) + seo.global_jsonld(img("logo"), img("og"), img("bs-logo-t") or img("bs-logo"))
    return html_block(FONTS + head + "\n<style>\n" + CSS + "\n</style>\n" + fill_imgs(HEADER))

def page_seo(slug, title, excerpt, path, image_key="og", breadcrumbs=None):
    return seo.seo_block(title, excerpt, path, img(image_key) or img("og"), "website", breadcrumbs=breadcrumbs)

def post_seo(p):
    image = img("banner-" + p["slug"]) or img("og")
    art = {"date": p["date"] + "T09:30:00-03:00", "section": p["category"], "tags": p.get("tags", [])}
    crumbs = [("Início", "/"), ("Matérias", "/materias/"), (p["title"], "/" + p["slug"] + "/")]
    return seo.seo_block(p["title"], p["excerpt"], "/" + p["slug"] + "/", image, "article", article=art, breadcrumbs=crumbs, keywords=p.get("tags"))

def build_footer_block():
    return html_block(fill_imgs(FOOTER) + "\n<script>\n" + JS + "\n</script>\n<script>\n" + JS3D + "\n</script>")

def upsert_block(key, title, content):
    bid = state["blocks"].get(key)
    if bid:
        call("wp_update_block", {"block_id": bid, "content": content, "title": title, "status": "publish"})
        print("  bloco atualizado:", key, bid)
    else:
        r = call("wp_create_block", {"title": title, "content": content, "status": "publish"})
        bid = r["id"]; state["blocks"][key] = bid; save(); print("  bloco criado:", key, bid)
    return bid

def step_blocks():
    print("== BLOCOS ==")
    upsert_block("header", "EB · Cabeçalho (topbar, megamenu, acessibilidade)", build_header_block())
    upsert_block("footer", "EB · Rodapé (canais, cookies, scripts)", build_footer_block())

# ---------------------------------------------------------------- TEMPLATES
def step_templates():
    print("== TEMPLATES ==")
    hid, fid = state["blocks"]["header"], state["blocks"]["footer"]
    before, after = fill_imgs(HOME).split("{{SPLIT}}")
    home_seo = seo.seo_block("Évellyn Brandão — CEO e Fundadora da BS Agro Capital | Crédito e estrutura para o agronegócio",
                             "Executiva com 10 anos de mercado financeiro (8 no Itaú Unibanco), Évellyn Brandão conecta produtores rurais e empresas do agro a bancos, cooperativas, fundos e outras fontes de capital. Crédito não é sorte: crédito é estrutura.",
                             "/", img("og"), "website", breadcrumbs=[("Início", "/")],
                             keywords=["Évellyn Brandão", "BS Agro Capital", "crédito rural", "Plano Safra", "CPR", "Barter", "Fiagro", "agronegócio", "Goiânia", "Anápolis"])
    list_seo = seo.seo_block("Matérias sobre crédito, capital e gestão no agronegócio — Évellyn Brandão",
                             "Crédito rural, estruturação de operações, gestão financeira, liderança e tendências do agro, explicados com clareza por Évellyn Brandão, CEO da BS Agro Capital.",
                             "/materias/", img("og"), "website")
    templates = {
        "home": home_seo + tpl.tpl_home(hid, fid, (before, after)),
        "index": list_seo + tpl.tpl_listing(hid, fid, "index"),
        "page": fill_imgs(tpl.tpl_page(hid, fid)),
        "page-no-title": fill_imgs(tpl.tpl_page_landing(hid, fid)),
        "single": fill_imgs(tpl.tpl_single(hid, fid)),
        "archive": list_seo + tpl.tpl_listing(hid, fid, "archive"),
        "search": tpl.tpl_listing(hid, fid, "search"),
        "404": tpl.tpl_404(hid, fid),
    }
    for slug, content in templates.items():
        call("wp_update_template", {"template_id": "twentytwentyfive//" + slug, "content": content})
        print("  template atualizado:", slug, len(content), "bytes")

# ---------------------------------------------------------------- PÁGINAS
def find_page(slug):
    if slug in state["pages"]: return state["pages"][slug]
    r = call("wp_list_pages", {"status": "any", "per_page": 100, "search": slug})
    for p in r.get("pages", []):
        if p.get("slug") == slug: state["pages"][slug] = p["id"]; save(); return p["id"]
    return None

def upsert_page(slug, title, content, excerpt="", template="", parent=0):
    args = {"title": title, "content": content, "status": "publish", "slug": slug, "excerpt": excerpt, "comment_status": "closed"}
    if template and template != "page": args["template"] = template
    if parent: args["parent"] = parent
    pid = find_page(slug)
    if pid:
        args["page_id"] = pid; call("wp_update_page", args); print("  página atualizada:", slug, pid)
    else:
        r = call("wp_create_page", args); pid = r["id"]; state["pages"][slug] = pid; save(); print("  página criada:", slug, pid, r.get("link"))
    return pid

def policies():
    p = os.path.join(CONTENT, "policies.json")
    return json.load(open(p, encoding="utf-8")) if os.path.exists(p) else {}

def faq_html(pol):
    items = pol.get("faq", [])
    return "\n".join('<details><summary>%s</summary><p>%s</p></details>' % (H.escape(q["q"]), q["a"]) for q in items)

def step_pages():
    print("== PÁGINAS ==")
    pol = policies()
    only = set(x for x in os.environ.get("EB_PAGES", "").split(",") if x)  # ex.: EB_PAGES=sobre,contato → atualiza só estas
    _upsert = globals()["upsert_page"]
    def upsert_page(slug, *a, **k):
        if only and slug not in only: return state["pages"].get(slug)
        return _upsert(slug, *a, **k)
    page_images = {"sobre": "visual-sobre", "bs-agro-capital": "bs-logo"}
    def crumbs_for(slug, title, parent_title=None, parent_slug=None):
        c = [("Início", "/")]
        if parent_slug: c.append((parent_title, "/%s/" % parent_slug)); c.append((title, "/%s/%s/" % (parent_slug, slug)))
        else: c.append((title, "/%s/" % slug))
        return c
    # landing pages
    for pg in CP.LANDING_PAGES:
        htm = fill_imgs(pg["html"]).replace("{{FAQ}}", faq_html(pol))
        head = page_seo(pg["slug"], pg["title"] if pg["slug"] != "sobre" else "Sobre Évellyn Brandão — trajetória, formação e reconhecimentos", pg["excerpt"], "/%s/" % pg["slug"], page_images.get(pg["slug"], "og"), crumbs_for(pg["slug"], pg["title"]))
        upsert_page(pg["slug"], pg["title"], head + html_block(htm), pg["excerpt"], pg["template"])
    # matérias (listagem)
    mat = CP.MATERIAS
    content = page_seo(mat["slug"], "Matérias — crédito, capital e gestão no agro", mat["excerpt"], "/materias/", "og", crumbs_for("materias", "Matérias"))
    content += html_block(mat["html_before"]) + tpl.posts_grid(query_id=11, per_page=9, inherit=False, pagination=True) + html_block(mat["html_after"])
    upsert_page(mat["slug"], mat["title"], content, mat["excerpt"], mat["template"])
    # soluções (filhas)
    parent = state["pages"]["solucoes"]
    for s in CP.SOLUTIONS:
        head = page_seo(s["slug"], s["title"] + " — Évellyn Brandão · BS Agro Capital", s["excerpt"], "/solucoes/%s/" % s["slug"], "og", crumbs_for(s["slug"], s["title"], "Soluções", "solucoes"))
        upsert_page(s["slug"], s["title"], head + to_blocks(s["html"]), s["excerpt"], s["template"], parent)
    # portal do titular / canal de integridade
    for pg in (CP.PORTAL, CP.INTEGRIDADE):
        htm = pg["html"]
        m = re.search(r"\{\{INTRO:([a-z-]+)\}\}", htm)
        intro = pol.get(m.group(1), {}).get("content_html", "") if m else ""
        htm = htm.replace(m.group(0), "") if m else htm
        head = page_seo(pg["slug"], pg["title"], pg["excerpt"], "/%s/" % pg["slug"], "og", crumbs_for(pg["slug"], pg["title"]))
        upsert_page(pg["slug"], pg["title"], head + to_blocks(intro) + "\n\n" + html_block(htm), pg["excerpt"], pg["template"])
    # políticas
    for slug in ("politica-de-privacidade", "politica-de-cookies", "politica-de-compliance-anticorrupcao", "politica-de-esg", "acessibilidade"):
        pd = pol.get(slug)
        if not pd: print("  (sem conteúdo ainda)", slug); continue
        head = page_seo(slug, pd["title"] + " — Évellyn Brandão · BS Agro Capital", pd.get("excerpt", ""), "/%s/" % slug, "og", crumbs_for(slug, pd["title"]))
        upsert_page(slug, pd["title"], head + to_blocks(pd["content_html"]), pd.get("excerpt", ""), "page")

# ---------------------------------------------------------------- MATÉRIAS (posts)
def load_posts():
    posts = []
    for f in ("posts_a.json", "posts_b.json"):
        p = os.path.join(CONTENT, f)
        if os.path.exists(p): posts += json.load(open(p, encoding="utf-8"))
    return posts

def _lookup_tag(name):
    key = name.strip().lower()
    r = call("wp_list_tags", {"search": name, "per_page": 50})
    for t in r.get("tags", []):
        if H.unescape(t["name"]).strip().lower() == key: return t["id"]
    return None

def tag_id(name):
    key = name.strip().lower()
    save()  # sincroniza com o disco (outros processos podem ter criado a tag)
    if key in state["tags"]: return state["tags"][key]
    tid = _lookup_tag(name)
    if not tid:
        try:
            r = call("wp_create_tag", {"name": name.strip()}); tid = r["id"]
        except RuntimeError as e:
            tid = _lookup_tag(name)  # criada em paralelo por outro processo
            if not tid: raise
    state["tags"][key] = tid; save(); return tid

def find_post(slug):
    save()  # sincroniza com o disco
    if slug in state["posts"]: return state["posts"][slug]
    r = call("wp_list_posts", {"status": "any", "per_page": 100, "search": slug})
    for p in r.get("posts", []):
        if p.get("slug") == slug: state["posts"][slug] = p["id"]; save(); return p["id"]
    return None

def step_posts():
    print("== MATÉRIAS ==")
    posts = load_posts()
    only = os.environ.get("EB_POSTS", "").strip()  # ex.: "3-7" (índices 1-based) ou "slug1,slug2"
    if only:
        if re.fullmatch(r"\d+-\d+", only):
            a, b = [int(x) for x in only.split("-")]; posts = posts[a - 1:b]
        else:
            wanted = set(only.split(",")); posts = [p for p in posts if p["slug"] in wanted]
    for p in posts:
        cat = CATS.get(p["category"], 1)
        tags = [tag_id(t) for t in p.get("tags", [])][:6]
        media = state["media"].get("banner-" + p["slug"], {}).get("id")
        args = {"title": p["title"], "content": post_seo(p) + to_blocks(p["content_html"]), "status": "publish", "slug": p["slug"],
                "excerpt": p["excerpt"], "categories": [cat], "tags": tags, "date": p["date"] + "T09:30:00", "comment_status": "closed"}
        if media: args["featured_media"] = media
        pid = find_post(p["slug"])
        if pid:
            args["post_id"] = pid; call("wp_update_post", args); print("  matéria atualizada:", p["slug"], pid)
        else:
            r = call("wp_create_post", args); pid = r["id"]; state["posts"][p["slug"]] = pid; save(); print("  matéria criada:", p["slug"], pid, r.get("link"))

STEPS = {"media": step_media, "blocks": step_blocks, "templates": step_templates, "pages": step_pages, "posts": step_posts}
if __name__ == "__main__":
    what = sys.argv[1:] or ["all"]
    if what == ["all"]: what = ["media", "blocks", "templates", "pages", "posts"]
    for w in what:
        STEPS[w]()
    save()
