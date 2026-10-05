#!/usr/bin/env python3
"""Build do site CoreCyber.

Gera, a partir de src/:
  dist/            prévia estática completa (mesmo HTML que vai para o WordPress)
  build/wp/        pacotes para publicação no WordPress (cabeçalho, rodapé, CSS, páginas)
  build/i18n/      textos-fonte em PT e relatório de traduções faltantes (EN/ES)

Uso:
  python3 tools/build.py            # build completo
  python3 tools/build.py --wp       # usa as URLs de mídia do WordPress (tools/media.json)

Marcadores aceitos nos HTML de src/:
  {{icons}}            sprite de ícones SVG
  {{i:nome}}           ícone (traço)          {{i:nome:classe}} com classe extra
  {{if:nome}}          ícone preenchido (ex.: whatsapp)
  {{img:chave}}        URL de imagem (src/img/<chave>.webp|png)
  {{c:caminho}}        valor do site.config.json (ex.: company.email_dpo)
  {{wa}}               link do WhatsApp com mensagem padrão
  {{wa:texto}}         link do WhatsApp com mensagem própria
  {{price:plano:tier}} preço mensal formatado (referência para quem não tem JS)
  {{pa:plano:tier}}    preço mensal equivalente no plano anual
  {{tier:N}}           tamanho da faixa N de ativos      {{managed}} preço inicial do gerenciado
"""
import base64
import gzip
import hashlib
import html
import json
import os
import re
import shutil
import sys
import urllib.parse
from pathlib import Path

from bs4 import BeautifulSoup, NavigableString, Tag, Comment

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "src"
DIST = ROOT / "dist"
BUILD = ROOT / "build"
CFG = json.loads((ROOT / "site.config.json").read_text(encoding="utf-8"))
WP_MODE = "--wp" in sys.argv
MEDIA = {}
if (ROOT / "tools" / "media.json").exists():
    MEDIA = json.loads((ROOT / "tools" / "media.json").read_text(encoding="utf-8"))

ICONS = (SRC / "partials" / "icons.svg").read_text(encoding="utf-8")

# ---------------------------------------------------------------- templating
def cfg_get(path):
    cur = CFG
    for part in path.split("."):
        cur = cur[part]
    return cur


def img_url(key):
    if WP_MODE:
        if key not in MEDIA:
            raise SystemExit(f"imagem '{key}' ausente em tools/media.json — rode o deploy de mídia primeiro")
        return MEDIA[key]
    for ext in ("webp", "png", "jpg", "svg"):
        if (SRC / "img" / f"{key}.{ext}").exists():
            return f"/assets/img/{key}.{ext}"
    raise SystemExit(f"imagem não encontrada: {key}")


def wa_url(text=None):
    text = text or "Olá! Vim pelo site do CoreCyber e quero saber mais."
    return f"https://wa.me/{CFG['company']['whatsapp']}?text=" + urllib.parse.quote(text)


def fmt_brl(v):
    s = f"{v:,.2f}" if v != int(v) else f"{int(v):,}"
    return s.replace(",", "X").replace(".", ",").replace("X", ".")


def icon(name, extra="", fill=False):
    cls = "ico" + (" ico--fill" if fill else "") + (f" {extra}" if extra else "")
    return f'<svg class="{cls}" aria-hidden="true" focusable="false"><use href="#i-{name}"></use></svg>'


TOKEN = re.compile(r"\{\{\s*([a-z]+)(?::([^}]*))?\s*\}\}")


def expand(text):
    def rep(m):
        kind, arg = m.group(1), m.group(2)
        if kind == "icons":
            return ICONS
        if kind == "i":
            parts = arg.split(":")
            return icon(parts[0], parts[1] if len(parts) > 1 else "")
        if kind == "if":
            return icon(arg, fill=True)
        if kind == "img":
            return img_url(arg)
        if kind == "c":
            return html.escape(str(cfg_get(arg)), quote=True)
        if kind == "wa":
            return html.escape(wa_url(arg), quote=True)
        if kind == "price":
            plan, tier = arg.split(":")
            p = next(x for x in CFG["pricing"]["plans"] if x["id"] == plan)
            return fmt_brl(p["monthly"][int(tier)])
        if kind == "pa":
            plan, tier = arg.split(":")
            p = next(x for x in CFG["pricing"]["plans"] if x["id"] == plan)
            return fmt_brl(round(p["monthly"][int(tier)] * CFG["pricing"]["annual_months_paid"] / 12))
        if kind == "tier":
            return fmt_brl(CFG["pricing"]["tiers"][int(arg)])
        if kind == "addon":
            return fmt_brl(CFG["pricing"]["ai_addon"])
        if kind == "managed":
            return fmt_brl(CFG["pricing"]["managed_from"])
        if kind == "year":
            return "2026"
        raise SystemExit(f"marcador desconhecido: {m.group(0)}")
    return TOKEN.sub(rep, text)


# ---------------------------------------------------------------- i18n
INLINE = {"a", "abbr", "b", "br", "code", "em", "i", "kbd", "mark", "small", "span", "strong",
          "sub", "sup", "time", "u", "s", "q", "cite", "wbr", "bdi", "data", "var", "del", "ins"}
SKIP = {"script", "style", "svg", "template", "noscript", "textarea", "canvas", "video",
        "iframe", "pre", "math", "object", "audio", "select"}
TATTRS = ("alt", "title", "aria-label", "placeholder", "data-say", "data-tip", "data-label")
SOURCE = {}          # chave -> texto PT
USAGE = {}           # chave -> conjunto de páginas


def norm(s):
    return re.sub(r"\s+", " ", s).strip()


def key_for(text):
    return "t" + hashlib.sha1(text.encode("utf-8")).hexdigest()[:9]


def register(text, page):
    text = norm(text)
    k = key_for(text)
    SOURCE[k] = text
    USAGE.setdefault(k, set()).add(page)
    return k


def has_dyn(el):
    return any(el.find(attrs={a: True}) is not None for a in ("data-dyn", "data-noi18n", "data-count", "data-price"))


def inline_only(el):
    # ícones (svg) e qualquer elemento de bloco quebram a unidade: o texto ao redor
    # vira unidade própria e o ícone fica fora da tradução
    for d in el.descendants:
        if isinstance(d, Tag) and d.name not in INLINE:
            return False
    return True


def text_of(el):
    t = ""
    for d in el.descendants:
        if isinstance(d, NavigableString) and not isinstance(d, Comment):
            if d.find_parent("svg") is None:
                t += str(d)
    return t.strip()


def inner_html(el):
    out = []
    for c in el.contents:
        if isinstance(c, Comment):
            continue
        if isinstance(c, Tag):
            out.append(c.decode(formatter="minimal"))
        else:
            out.append(html.escape(str(c), quote=False))
    return "".join(out)


def mark_attrs(el, page):
    if el.has_attr("data-noi18n"):
        return
    pairs = []
    for a in TATTRS:
        if el.has_attr(a):
            v = el[a]
            if isinstance(v, list):
                v = " ".join(v)
            if norm(v) and re.search(r"[A-Za-zÀ-ÿ]", v):
                pairs.append(f"{a}:{register(v, page)}")
    if pairs:
        el["data-ta"] = ";".join(pairs)


def walk(el, page):
    if not isinstance(el, Tag):
        return
    if el.name in SKIP or el.has_attr("data-noi18n") or el.get("translate") == "no":
        if el.has_attr("data-noi18n") or el.get("translate") == "no":
            return
        if el.name in ("textarea", "select", "iframe"):
            mark_attrs(el, page)
        if el.name == "select":
            for opt in el.find_all("option"):
                if text_of(opt):
                    opt["data-t"] = register(inner_html(opt), page)
        return
    mark_attrs(el, page)
    if text_of(el) and inline_only(el) and not has_dyn(el) and re.search(r"[A-Za-zÀ-ÿ]", text_of(el)):
        # atributos traduzíveis de descendentes inline seguem dentro do innerHTML traduzido
        el["data-t"] = register(inner_html(el), page)
        return
    for child in list(el.children):
        if isinstance(child, Comment):
            continue
        if isinstance(child, NavigableString):
            if norm(str(child)) and re.search(r"[A-Za-zÀ-ÿ]", str(child)):
                lead = re.match(r"^\s*", str(child)).group(0)
                trail = re.search(r"\s*$", str(child)).group(0)
                span = BeautifulSoup("", "html.parser").new_tag("span")
                span.string = norm(str(child))
                span["data-t"] = register(html.escape(span.string, quote=False), page)
                child.replace_with(span)
                if lead:
                    span.insert_before(NavigableString(" "))
                if trail:
                    span.insert_after(NavigableString(" "))
            continue
        walk(child, page)


def i18n_process(fragment, page):
    soup = BeautifulSoup(fragment, "html.parser")
    for top in list(soup.children):
        if isinstance(top, Tag):
            walk(top, page)
    return str(soup)


def load_tr(lang):
    p = SRC / "i18n" / f"{lang}.json"
    return json.loads(p.read_text(encoding="utf-8")) if p.exists() else {}


# ---------------------------------------------------------------- assets
def build_css():
    parts = []
    for f in sorted((SRC / "css").glob("*.css")):
        parts.append(f.read_text(encoding="utf-8"))
    css = "\n".join(parts)
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    css = re.sub(r"\s+", " ", css)
    css = re.sub(r"\s*([{};,>])\s*", r"\1", css)
    css = css.replace(";}", "}")
    return css.strip()


def b64(s):
    return base64.b64encode(s.encode("utf-8")).decode("ascii")


def b64gz(s):
    return base64.b64encode(gzip.compress(s.encode("utf-8"), 9, mtime=0)).decode("ascii")


def js_inline(code, sid, compress=False):
    """JS embutido em base64 (imune aos filtros de conteúdo do WordPress).
    compress=True: gzip + base64, descompactado no navegador (DecompressionStream)."""
    if not compress:
        return (f'<script id="{sid}">new Function(new TextDecoder("utf-8").decode(Uint8Array.from('
                f'atob("{b64(code)}"),function(c){{return c.charCodeAt(0)}})))();</script>')
    return (f'<script id="{sid}">(function(){{if(!window.DecompressionStream)return;'
            f'var u=Uint8Array.from(atob("{b64gz(code)}"),function(c){{return c.charCodeAt(0)}});'
            f'new Response(new Blob([u]).stream().pipeThrough(new DecompressionStream("gzip"))).text()'
            f'.then(function(s){{new Function(s)()}})}})();</script>')


def json_block(obj, cls, sid=None, compress=False):
    i = f' id="{sid}"' if sid else ""
    data = json.dumps(obj, ensure_ascii=False, separators=(",", ":"))
    if compress:
        return f'<script type="application/json" class="{cls}"{i} data-z="1">{b64gz(data)}</script>'
    return f'<script type="application/json" class="{cls}"{i}>{b64(data)}</script>'


def text_block(code, sid):
    return f'<script type="text/plain" id="{sid}" data-z="1">{b64gz(code)}</script>'


# ---------------------------------------------------------------- páginas
META_RE = re.compile(r"<!--meta\s*(\{.*?\})\s*-->", re.S)


def load_pages():
    pages = []
    for f in sorted((SRC / "pages").glob("*.html")):
        raw = f.read_text(encoding="utf-8")
        m = META_RE.search(raw)
        if not m:
            raise SystemExit(f"{f.name}: falta o comentário <!--meta {{...}} -->")
        meta = json.loads(m.group(1))
        body = META_RE.sub("", raw, count=1).strip()
        meta["file"] = f.name
        meta["body"] = body
        pages.append(meta)
    return pages


PLAIN = WP_MODE and CFG.get("wp", {}).get("permalinks") == "plain"
SLUGS = set()
HREF_RE = re.compile(r'href="/([a-z0-9-]+)/(\?[^"#]*)?(#[^"]*)?"')


def plain_links(text):
    """WordPress com links simples: /slug/?a=b#x -> /?pagename=slug&a=b#x (só para páginas do site)."""
    if not PLAIN:
        return text
    def rep(m):
        slug, q, frag = m.group(1), m.group(2) or "", m.group(3) or ""
        if slug not in SLUGS:
            return m.group(0)
        q = ("&" + q[1:]) if q else ""
        return f'href="/?pagename={slug}{q}{frag}"'
    return HREF_RE.sub(rep, text)


def public_cfg():
    c = CFG
    return {
        "site": c["site"], "company": c["company"], "links": c["links"],
        "wp": {"plain": PLAIN, "slugs": sorted(SLUGS)},
        "ai": {k: v for k, v in c["ai"].items() if not k.startswith("_")},
        "pricing": c["pricing"],
        "img": {"icon": img_url("icon-64"), "icon180": img_url("icon-180")},
    }


def main():
    tr = {"en": load_tr("en"), "es": load_tr("es")}
    pages = load_pages()
    SLUGS.update(p["slug"] for p in pages if p["slug"] and p["slug"] != "404")

    early = (SRC / "js" / "cc.early.js").read_text(encoding="utf-8")
    header = js_inline(early, "cc-early") + "\n" + i18n_process(
        expand((SRC / "partials" / "header.html").read_text(encoding="utf-8")), "_chrome")
    footer = i18n_process(expand((SRC / "partials" / "footer.html").read_text(encoding="utf-8")), "_chrome")

    built = []
    for p in pages:
        slug = p["slug"]
        body = i18n_process(expand(p["body"]), slug or "inicio")
        tkey = register(p["title"], slug or "inicio")
        dkey = register(p["description"], slug or "inicio")
        keys = sorted(k for k, pg in USAGE.items() if (slug or "inicio") in pg)
        payload = {"_meta": {"title": tkey, "desc": dkey}}
        for lang in ("en", "es"):
            payload[lang] = {k: plain_links(tr[lang][k]) for k in keys if k in tr[lang]}
        mods = ""
        if "3d" in p.get("modules", []):
            mods += text_block((SRC / "js" / "cc.three.js").read_text(encoding="utf-8"), "cc-mod-3d")
        content = (f'<div class="cc cc-page" id="cc-content" data-page="{slug or "inicio"}">\n{plain_links(body)}\n</div>\n'
                   + json_block(payload, "cc-i18n", compress=True) + mods)
        built.append({**p, "content": content})

    chrome_keys = sorted(k for k, pg in USAGE.items() if "_chrome" in pg)
    chrome_payload = {lang: {k: plain_links(tr[lang][k]) for k in chrome_keys if k in tr[lang]} for lang in ("en", "es")}
    header, footer = plain_links(header), plain_links(footer)
    core = ((SRC / "js" / "cc.ui.js").read_text(encoding="utf-8") + "\nwindow.CC_UI=CC_UI;window.CC_KB=CC_KB;\n"
            + (SRC / "js" / "cc.core.js").read_text(encoding="utf-8"))
    footer_full = (footer + "\n" + json_block(public_cfg(), "cc-cfg", "cc-cfg") + "\n"
                   + json_block(chrome_payload, "cc-i18n", compress=True) + "\n" + js_inline(core, "cc-core", compress=True))
    css = build_css()

    # ------------------------------------------------ relatórios de tradução
    (BUILD / "i18n").mkdir(parents=True, exist_ok=True)
    (BUILD / "i18n" / "source.pt.json").write_text(json.dumps(
        {k: SOURCE[k] for k in sorted(SOURCE)}, ensure_ascii=False, indent=1), encoding="utf-8")
    (BUILD / "i18n" / "usage.json").write_text(json.dumps(
        {k: sorted(v) for k, v in sorted(USAGE.items())}, ensure_ascii=False, indent=1), encoding="utf-8")
    for lang in ("en", "es"):
        miss = {k: SOURCE[k] for k in sorted(SOURCE) if k not in tr[lang]}
        (BUILD / "i18n" / f"missing.{lang}.json").write_text(json.dumps(miss, ensure_ascii=False, indent=1), encoding="utf-8")
        print(f"[i18n] {lang}: {len(SOURCE) - len(miss)}/{len(SOURCE)} traduzidos ({len(miss)} faltando)")

    # ------------------------------------------------ pacotes WordPress
    wp = BUILD / "wp"
    if wp.exists():
        shutil.rmtree(wp)
    (wp / "pages").mkdir(parents=True)
    (wp / "header.html").write_text(header, encoding="utf-8")
    (wp / "footer.html").write_text(footer_full, encoding="utf-8")
    (wp / "site.css").write_text(css, encoding="utf-8")
    manifest = []
    for p in built:
        name = (p["slug"] or "inicio") + ".html"
        (wp / "pages" / name).write_text(p["content"], encoding="utf-8")
        manifest.append({"slug": p["slug"], "title": p["title"], "description": p["description"],
                         "file": name, "order": p.get("order", 50)})
    (wp / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=1), encoding="utf-8")

    # ------------------------------------------------ prévia estática
    if DIST.exists():
        shutil.rmtree(DIST)
    (DIST / "assets" / "css").mkdir(parents=True)
    shutil.copytree(SRC / "img", DIST / "assets" / "img")
    fonts = SRC / "fonts"
    if fonts.exists():
        shutil.copytree(fonts, DIST / "assets" / "fonts")
    (DIST / "assets" / "css" / "corecyber.css").write_text(css, encoding="utf-8")
    font_face = ('<style>@font-face{font-family:"Manrope";src:url("/assets/fonts/Manrope-VariableFont_wght.woff2") '
                 'format("woff2");font-weight:200 800;font-display:swap}</style>') if fonts.exists() else ""
    for p in built:
        out = DIST / (p["slug"] or "") / "index.html" if p["slug"] else DIST / "index.html"
        out.parent.mkdir(parents=True, exist_ok=True)
        doc = f"""<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{html.escape(p['title'])} – CoreCyber</title>
<meta name="description" content="{html.escape(p['description'], quote=True)}">
<meta property="og:title" content="{html.escape(p['title'], quote=True)} – CoreCyber">
<meta property="og:description" content="{html.escape(p['description'], quote=True)}">
<meta property="og:image" content="/assets/img/og.jpg">
<meta name="theme-color" content="#002141">
<link rel="icon" href="/assets/img/icon-64.png">
{font_face}
<link rel="stylesheet" href="/assets/css/corecyber.css">
</head>
<body class="wp-site">
<div class="wp-site-blocks">
<header class="wp-block-template-part">{header}</header>
<main class="wp-block-group cc-main" id="wp--skip-link--target">
<div class="entry-content wp-block-post-content">
{p['content']}
</div>
</main>
<footer class="wp-block-template-part">{footer_full}</footer>
</div>
</body>
</html>"""
        out.write_text(doc, encoding="utf-8")
    nf = next((p for p in built if p["slug"] == "404"), None)
    if nf:
        shutil.copy(DIST / "404" / "index.html", DIST / "404.html")
    print(f"[build] {len(built)} páginas · CSS {len(css)//1024} KB · núcleo JS {len(core)//1024} KB · modo {'WP' if WP_MODE else 'prévia'}")


def check(files):
    """Valida páginas sem gravar nada: marcadores, HTML e padrões que o WordPress altera."""
    ok = True
    for f in files:
        path = Path(f)
        raw = path.read_text(encoding="utf-8")
        m = META_RE.search(raw)
        if not m:
            print(f"ERRO {path.name}: falta <!--meta {{...}} -->"); ok = False; continue
        try:
            meta = json.loads(m.group(1))
        except Exception as e:
            print(f"ERRO {path.name}: meta inválido: {e}"); ok = False; continue
        for k in ("slug", "title", "description"):
            if k not in meta:
                print(f"ERRO {path.name}: meta sem '{k}'"); ok = False
        body = META_RE.sub("", raw, count=1)
        try:
            body = expand(body)
        except SystemExit as e:
            print(f"ERRO {path.name}: {e}"); ok = False; continue
        soup = BeautifulSoup(body, "html.parser")
        text = soup.get_text(" ")
        # o WordPress só converte emoticons isolados por espaços (convert_smilies)
        for mm in re.finditer(r"(?:^|\s)(:\)|;\)|8\)|8-\)|:\(|:D|:P|:o|:x|:\?)(?=\s|$)", text):
            i = mm.start()
            print(f"AVISO {path.name}: emoticon no texto: …{text[max(0, i-40):i+20]!r}…"); ok = False
        for bad in ("[", "]"):
            if bad in text:
                i = text.index(bad)
                print(f"AVISO {path.name}: colchete no texto: …{text[max(0, i-40):i+20]!r}…"); ok = False
        ids = [t.get("id") for t in soup.find_all(id=True)]
        dup = {i for i in ids if ids.count(i) > 1}
        if dup:
            print(f"ERRO {path.name}: ids duplicados {sorted(dup)}"); ok = False
        for a in soup.select('.toc a[href^="#"]'):
            if not soup.find(id=a["href"][1:]):
                print(f"ERRO {path.name}: sumário aponta para #{a['href'][1:]} inexistente"); ok = False
        n0 = len(SOURCE)
        i18n_process(body, meta.get("slug") or "inicio")
        print(f"{'OK ' if ok else '-- '}{path.name}: {len(SOURCE) - n0} textos traduzíveis, {len(body)//1024} KB")
    return ok


if __name__ == "__main__":
    if "--check" in sys.argv:
        files = [a for a in sys.argv[1:] if not a.startswith("--")] or sorted(str(p) for p in (SRC / "pages").glob("*.html"))
        sys.exit(0 if check(files) else 1)
    main()
