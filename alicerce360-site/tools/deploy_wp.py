#!/usr/bin/env python3
"""Publica o site Alicerce360 no WordPress pelo MCP do plugin Easy MCP AI.

A chave MCP NUNCA fica no repositório. Informe-a por variável de ambiente:
  export WPMCP_KEY=wpmcp_...            (ou WPMCP_KEY_FILE=/caminho/arquivo-com-a-chave)
  export WP_URL=https://www.alicerce360.com.br   (padrão)

Uso:
  python3 tools/deploy_wp.py                 publica tudo (mídia, CSS, cabeçalho, rodapé, modelos, páginas)
  python3 tools/deploy_wp.py --only pages    só páginas  (também: media, css, chrome, templates, posts, settings)
  python3 tools/deploy_wp.py --dry-run       mostra o que faria, sem gravar

O que é alterado no WordPress (tudo reversível pelo Editor do site / lixeira):
  - Mídia: logos e ícones (tools/media.json guarda as URLs).
  - CSS adicional do tema (Aparência → Personalizar → CSS adicional).
  - Partes de modelo "header" e "footer" do tema ativo (HTML do cabeçalho/rodapé + scripts).
  - Modelos "page", "page-no-title", "home" (página inicial), "404" e "single" (matérias).
  - Matérias: categorias e posts (tools/posts.json guarda id e link de cada uma).
  - Blocos reutilizáveis "Alicerce360 · Início" e "Alicerce360 · 404".
  - Páginas por slug (cria ou atualiza), título e descrição do site.
  - Conteúdo de exemplo do WordPress vai para a lixeira ("Olá, mundo!" e "Página de exemplo").
"""
import base64
import hashlib
import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
WP_URL = os.environ.get("WP_URL", "https://www.alicerce360.com.br").rstrip("/")
DRY = "--dry-run" in sys.argv
ONLY = sys.argv[sys.argv.index("--only") + 1].split(",") if "--only" in sys.argv else None
MEDIA_FILE = ROOT / "tools" / "media.json"
IMAGES = {  # chave -> arquivo em src/img
    "logo-h": "logo-h.webp", "logo-h-neg": "logo-h-neg.webp", "logo-v": "logo-v.webp",
    "icon-512": "icon-512.webp", "icon-180": "icon-180.png", "icon-64": "icon-64.png",
}


def key():
    k = os.environ.get("WPMCP_KEY", "").strip()
    if not k and os.environ.get("WPMCP_KEY_FILE"):
        k = Path(os.environ["WPMCP_KEY_FILE"]).read_text().strip()
    if not k.startswith("wpmcp_"):
        raise SystemExit("Defina WPMCP_KEY (ou WPMCP_KEY_FILE) com a chave do Easy MCP AI.")
    return k


class MCP:
    def __init__(self):
        self.url = f"{WP_URL}/wp-json/easy-mcp-ai/v1/mcp/{key()}"
        self.sid = None
        self.n = 0
        self.rpc("initialize", {"protocolVersion": "2025-03-26", "capabilities": {},
                                "clientInfo": {"name": "alicerce360-deploy", "version": "1.0"}})
        self.rpc("notifications/initialized", notify=True)

    def rpc(self, method, params=None, notify=False):
        body = {"jsonrpc": "2.0", "method": method}
        if params is not None:
            body["params"] = params
        if not notify:
            self.n += 1
            body["id"] = self.n
        h = {"Content-Type": "application/json", "Accept": "application/json, text/event-stream"}
        if self.sid:
            h["Mcp-Session-Id"] = self.sid
        data = json.dumps(body).encode()
        for attempt in range(4):
            try:
                req = urllib.request.Request(self.url, data=data, headers=h, method="POST")
                with urllib.request.urlopen(req, timeout=240) as r:
                    self.sid = r.headers.get("Mcp-Session-Id") or self.sid
                    txt = r.read().decode()
                break
            except urllib.error.HTTPError as e:
                raise SystemExit(f"HTTP {e.code} em {method}: {e.read().decode()[:500]}")
            except (urllib.error.URLError, TimeoutError) as e:
                if attempt == 3:
                    raise
                time.sleep(2 ** (attempt + 1))
        if not txt.strip():
            return None
        if txt.startswith(("event:", "data:")):
            txt = next(l[5:] for l in txt.splitlines() if l.startswith("data:"))
        return json.loads(txt)

    def tool(self, name, args):
        r = self.rpc("tools/call", {"name": name, "arguments": args})
        res = (r or {}).get("result") or {}
        text = "".join(c.get("text", "") for c in res.get("content", []))
        if res.get("isError") or (r or {}).get("error"):
            raise SystemExit(f"Erro em {name}: {text or r.get('error')}")
        try:
            return json.loads(text)
        except json.JSONDecodeError:
            return text


def step(name):
    return ONLY is None or name in ONLY


def html_block(html):
    return "<!-- wp:html -->\n" + html + "\n<!-- /wp:html -->"


def log(*a):
    print("·", *a, flush=True)


def main():
    mcp = MCP()
    log("conectado a", WP_URL)
    theme = mcp.tool("wp_get_active_theme", {})["stylesheet"]
    log("tema ativo:", theme)

    # ------------------------------------------------ mídia
    media = json.loads(MEDIA_FILE.read_text()) if MEDIA_FILE.exists() else {}
    hashes = media.pop("_sha1", {})
    ids = media.pop("_ids", {})
    images = dict(IMAGES)
    for f in sorted((ROOT / "src" / "img" / "posts").glob("*.webp")):
        images[f"posts/{f.stem}"] = f"posts/{f.name}"
    if step("media"):
        for k, fn in images.items():
            p = ROOT / "src" / "img" / fn
            h = hashlib.sha1(p.read_bytes()).hexdigest()
            if media.get(k) and hashes.get(k) == h:
                continue
            log("enviando mídia", fn)
            if not DRY:
                r = mcp.tool("wp_upload_media", {"filename": f"alicerce360-{fn.replace('/', '-')}", "content_base64": base64.b64encode(p.read_bytes()).decode(),
                                                 "title": f"Alicerce360 {k}", "alt_text": "Alicerce360 — um produto EBAEM"})
                media[k] = r["source_url"]; hashes[k] = h; ids[k] = r["id"]
        if not DRY:
            MEDIA_FILE.write_text(json.dumps({**media, "_sha1": hashes, "_ids": ids}, indent=1) + "\n")

    # ------------------------------------------------ build com URLs do WordPress
    def build():
        log("gerando build (modo WordPress)")
        subprocess.run([sys.executable, str(ROOT / "tools" / "build.py"), "--wp"], check=True)
    build()
    wp = ROOT / "build" / "wp"
    manifest = json.loads((wp / "manifest.json").read_text())
    posts_man = json.loads((wp / "posts.json").read_text()) if (wp / "posts.json").exists() else []

    # ------------------------------------------------ matérias: categorias e posts (links reais antes das páginas)
    links_file = ROOT / "tools" / "posts.json"
    links = json.loads(links_file.read_text()) if links_file.exists() else {}
    cat_ids, wp_posts = {}, {}
    if step("posts") and posts_man:
        cats = mcp.tool("wp_list_categories", {"per_page": 100}).get("categories", [])
        cat_ids = {c["slug"]: c["id"] for c in cats}
        for pm in posts_man:
            if pm["cat_slug"] not in cat_ids:
                log("criando categoria", pm["category"])
                if not DRY:
                    cat_ids[pm["cat_slug"]] = mcp.tool("wp_create_category", {"name": pm["category"], "slug": pm["cat_slug"]})["id"]
        for st in ("publish", "draft", "future"):
            for p in mcp.tool("wp_list_posts", {"status": st, "per_page": 100}).get("posts", []):
                wp_posts[p["slug"]] = p
        created = False
        for pm in posts_man:
            if pm["slug"] in wp_posts:
                links[pm["slug"]] = {"id": wp_posts[pm["slug"]]["id"], "link": wp_posts[pm["slug"]]["link"]}
                continue
            log("criando matéria", pm["slug"])
            if not DRY:
                r = mcp.tool("wp_create_post", {"title": pm["title"], "slug": pm["slug"], "status": "publish", "content": "<!-- wp:paragraph --><p>…</p><!-- /wp:paragraph -->",
                                                "date": pm["date"], "categories": [cat_ids[pm["cat_slug"]]], "excerpt": pm["description"],
                                                "comment_status": "closed", "ping_status": "closed"})
                full = mcp.tool("wp_get_post", {"post_id": r["id"]})
                links[pm["slug"]] = {"id": r["id"], "link": full.get("link") or r.get("link")}
                wp_posts[pm["slug"]] = {"id": r["id"], "link": links[pm["slug"]]["link"], "slug": pm["slug"]}
                created = True
        if not DRY:
            links_file.write_text(json.dumps(links, ensure_ascii=False, indent=1) + "\n")
        if created:
            build()  # refaz com os links definitivos das matérias

    if step("css"):
        css = (wp / "site.css").read_text()
        log(f"CSS adicional ({len(css)//1024} KB)")
        if not DRY:
            mcp.tool("wp_update_custom_css", {"css": css, "mode": "replace"})

    if step("chrome"):
        for part in ("header", "footer"):
            html = (wp / f"{part}.html").read_text()
            log(f"parte de modelo {part} ({len(html)//1024} KB)")
            if not DRY:
                mcp.tool("wp_update_template", {"template_id": f"{theme}//{part}", "type": "template_part", "content": html_block(html)})

    # ------------------------------------------------ blocos reutilizáveis (início e 404)
    blocks = {}
    if step("templates") or step("pages"):
        existing = {b["title"]: b["id"] for b in mcp.tool("wp_list_blocks", {"per_page": 100, "status": "publish"}).get("blocks", [])}
        for slug, title in (("", "Alicerce360 · Início"), ("404", "Alicerce360 · 404")):
            pg = next(p for p in manifest if p["slug"] == slug)
            content = html_block((wp / "pages" / pg["file"]).read_text())
            if title in existing:
                blocks[slug] = existing[title]
                log("atualizando bloco", title)
                if not DRY:
                    mcp.tool("wp_update_block", {"block_id": existing[title], "content": content})
            else:
                log("criando bloco", title)
                if not DRY:
                    blocks[slug] = mcp.tool("wp_create_block", {"title": title, "content": content, "status": "publish"})["id"]

    if step("templates"):
        hdr = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
        ftr = '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->'
        def main_wrap(inner):
            return ('<!-- wp:group {"tagName":"main","className":"a3-main","layout":{"type":"default"}} -->\n'
                    '<main class="wp-block-group a3-main">' + inner + '</main>\n<!-- /wp:group -->')
        page_tpl = hdr + "\n" + main_wrap('<!-- wp:post-content {"layout":{"type":"default"}} /-->') + "\n" + ftr
        tpls = {"page": page_tpl, "page-no-title": page_tpl}
        if blocks.get(""):
            tpls["home"] = hdr + "\n" + main_wrap(f'<!-- wp:block {{"ref":{blocks[""]}}} /-->') + "\n" + ftr
        if blocks.get("404"):
            tpls["404"] = hdr + "\n" + main_wrap(f'<!-- wp:block {{"ref":{blocks["404"]}}} /-->') + "\n" + ftr
        crumbs = (wp / "post-crumbs.html").read_text() if (wp / "post-crumbs.html").exists() else ""
        tpls["single"] = (hdr + "\n" +
            '<!-- wp:group {"tagName":"main","className":"a3-main a3-single","layout":{"type":"default"}} -->\n<main class="wp-block-group a3-main a3-single">'
            '<!-- wp:group {"tagName":"section","className":"a3 a3-post-head","layout":{"type":"default"}} -->\n<section class="wp-block-group a3 a3-post-head">'
            '<!-- wp:group {"className":"wrap a3-post-grid","layout":{"type":"default"}} -->\n<div class="wp-block-group wrap a3-post-grid">'
            '<!-- wp:group {"className":"a3-post-head-text","layout":{"type":"default"}} -->\n<div class="wp-block-group a3-post-head-text">'
            + html_block(crumbs) +
            '<!-- wp:post-terms {"term":"category"} /-->'
            '<!-- wp:post-title {"level":1} /-->'
            '<!-- wp:group {"className":"a3-post-meta","layout":{"type":"default"}} -->\n<div class="wp-block-group a3-post-meta"><!-- wp:post-date /--></div>\n<!-- /wp:group -->'
            '</div>\n<!-- /wp:group -->'
            '<!-- wp:post-featured-image {"className":"a3-post-cover"} /-->'
            '</div>\n<!-- /wp:group --></section>\n<!-- /wp:group -->'
            '<!-- wp:post-content {"layout":{"type":"default"}} /-->'
            '</main>\n<!-- /wp:group -->\n' + ftr)
        for slug, content in tpls.items():
            log("modelo", slug)
            if not DRY:
                mcp.tool("wp_update_template", {"template_id": f"{theme}//{slug}", "type": "template", "content": content})

    # ------------------------------------------------ páginas
    if step("pages"):
        pages = mcp.tool("wp_list_pages", {"status": "any", "per_page": 100}).get("pages", [])
        by_slug = {p["slug"]: p for p in pages}
        for pg in sorted(manifest, key=lambda p: p.get("order", 50)):
            if pg["slug"] in ("", "404"):
                continue
            content = html_block((wp / "pages" / pg["file"]).read_text())
            args = {"title": pg["title"], "content": content, "status": "publish", "slug": pg["slug"],
                    "menu_order": pg.get("order", 50), "comment_status": "closed", "ping_status": "closed"}
            if pg["slug"] in by_slug:
                log(f"atualizando /{pg['slug']}/ ({len(content)//1024} KB)")
                if not DRY:
                    mcp.tool("wp_update_page", {"page_id": by_slug[pg["slug"]]["id"], **args})
            else:
                log(f"criando /{pg['slug']}/ ({len(content)//1024} KB)")
                if not DRY:
                    mcp.tool("wp_create_page", args)
        # conteúdo de exemplo do WordPress → lixeira (recuperável)
        if "pagina-exemplo" in by_slug and by_slug["pagina-exemplo"]["status"] != "trash":
            log("página de exemplo → lixeira")
            if not DRY:
                mcp.tool("wp_delete_page", {"page_id": by_slug["pagina-exemplo"]["id"], "force": False})
        posts = mcp.tool("wp_list_posts", {"status": "publish", "per_page": 20}).get("posts", [])
        for p in posts:
            if p.get("slug") in ("ola-mundo", "hello-world"):
                log("post de exemplo → lixeira")
                if not DRY:
                    mcp.tool("wp_delete_post", {"post_id": p["id"], "force": False})

    if step("posts") and posts_man:
        for pm in posts_man:
            pid = links.get(pm["slug"], {}).get("id")
            if not pid:
                continue
            content = html_block((wp / "posts" / f'{pm["slug"]}.html').read_text())
            args = {"post_id": pid, "title": pm["title"], "content": content, "status": "publish", "excerpt": pm["description"],
                    "date": pm["date"], "categories": [cat_ids.get(pm["cat_slug"])] if cat_ids.get(pm["cat_slug"]) else None}
            fid = ids.get(pm["cover"])
            if fid:
                args["featured_media"] = fid
            args = {k: v for k, v in args.items() if v is not None}
            log(f"atualizando matéria {pm['slug']} ({len(content)//1024} KB)")
            if not DRY:
                mcp.tool("wp_update_post", args)

    if step("settings"):
        log("título e descrição do site")
        if not DRY:
            cfg = json.loads((ROOT / "site.config.json").read_text())
            mcp.tool("wp_update_site_settings", {"title": cfg["site"]["name"], "description": cfg["site"]["tagline"], "use_smilies": False})
    log("pronto:", WP_URL)


if __name__ == "__main__":
    main()
