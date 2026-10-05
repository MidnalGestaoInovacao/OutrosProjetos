#!/usr/bin/env python3
"""Publica o site CoreCyber no WordPress pelo MCP do plugin Easy MCP AI.

A chave MCP NUNCA fica no repositório. Informe-a por variável de ambiente:
  export WPMCP_KEY=wpmcp_...            (ou WPMCP_KEY_FILE=/caminho/arquivo-com-a-chave)
  export WP_URL=https://corecyber.com.br   (padrão)

Uso:
  python3 tools/deploy_wp.py                 publica tudo (mídia, CSS, cabeçalho, rodapé, modelos, páginas)
  python3 tools/deploy_wp.py --only pages    só páginas  (também: media, css, chrome, templates, posts, settings)
  python3 tools/deploy_wp.py --dry-run       mostra o que faria, sem gravar

O que é alterado no WordPress (tudo reversível pelo Editor do site / lixeira):
  - Mídia: logos, ícones e capas das matérias (tools/media.json guarda URLs e IDs).
  - CSS adicional do tema (Aparência → Personalizar → CSS adicional).
  - Partes de modelo "header" e "footer" do tema ativo (HTML do cabeçalho/rodapé + scripts).
  - Modelos "page", "page-no-title", "single" (matérias), "home" (página inicial) e "404".
  - Matérias como posts (por slug), com categoria, tags e imagem de destaque (capas em src/img/posts/).
  - Blocos reutilizáveis "CoreCyber · Início" e "CoreCyber · 404".
  - Páginas por slug (cria ou atualiza), título e descrição do site.
  - Conteúdo de exemplo do WordPress vai para a lixeira ("Olá, mundo!" e "Página de exemplo").
"""
import base64
import re
import http.client
import ssl
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
WP_URL = os.environ.get("WP_URL", "https://corecyber.com.br").rstrip("/")
DRY = "--dry-run" in sys.argv
ONLY = sys.argv[sys.argv.index("--only") + 1].split(",") if "--only" in sys.argv else None
MEDIA_FILE = ROOT / "tools" / "media.json"
IMAGES = {  # chave -> arquivo em src/img
    "logo-h": "logo-h.webp", "logo-h-neg": "logo-h-neg.webp", "logo-v": "logo-v.webp",
    "icon-512": "icon-512.webp", "icon-180": "icon-180.png", "icon-64": "icon-64.png",
    "icon-32": "icon-32.png", "icon-neg-256": "icon-neg-256.webp", "og": "og.jpg",
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
        # ?rest_route= funciona mesmo sem links permanentes (o /wp-json/ deste servidor responde 404)
        self.url = f"{WP_URL}/?rest_route=/easy-mcp-ai/v1/mcp/{key()}"
        self.sid = None
        self.n = 0
        self.rpc("initialize", {"protocolVersion": "2025-03-26", "capabilities": {},
                                "clientInfo": {"name": "corecyber-deploy", "version": "1.0"}})
        self.rpc("notifications/initialized", notify=True)

    def rpc(self, method, params=None, notify=False):
        body = {"jsonrpc": "2.0", "method": method}
        if params is not None:
            body["params"] = params
        if not notify:
            self.n += 1
            body["id"] = self.n
        # o firewall da hospedagem derruba conexões com o User-Agent padrão do Python
        h = {"Content-Type": "application/json", "Accept": "application/json, text/event-stream",
             "User-Agent": "Mozilla/5.0 (compatible; corecyber-deploy/1.0)"}
        if self.sid:
            h["Mcp-Session-Id"] = self.sid
        data = json.dumps(body).encode()
        for attempt in range(6):
            try:
                req = urllib.request.Request(self.url, data=data, headers=h, method="POST")
                with urllib.request.urlopen(req, timeout=240) as r:
                    self.sid = r.headers.get("Mcp-Session-Id") or self.sid
                    txt = r.read().decode()
                break
            except urllib.error.HTTPError as e:
                raise SystemExit(f"HTTP {e.code} em {method}: {e.read().decode()[:500]}")
            except (urllib.error.URLError, TimeoutError, http.client.HTTPException, ssl.SSLError, ConnectionError) as e:
                if attempt == 5:
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
    posts_meta = {}
    for f in sorted((ROOT / "src" / "posts").glob("*.html")):
        m = re.search(r"<!--meta\s*(\{.*?\})\s*-->", f.read_text(encoding="utf-8"), re.S)
        if m:
            pm = json.loads(m.group(1)); posts_meta[pm["slug"]] = pm
    images = dict(IMAGES)
    for f in sorted((ROOT / "src" / "img" / "posts").glob("*.webp")):
        images["posts/" + f.stem] = "posts/" + f.name
    def save_media():
        if not DRY:
            MEDIA_FILE.write_text(json.dumps({**media, "_sha1": hashes, "_ids": ids}, indent=1) + "\n")
    if step("media") or step("posts"):
        for k, fn in images.items():
            if not step("media") and not k.startswith("posts/"):
                continue
            p = ROOT / "src" / "img" / fn
            h = hashlib.sha1(p.read_bytes()).hexdigest()
            if media.get(k) and hashes.get(k) == h:
                continue
            alt = "CoreCyber — um produto EBAEM"
            if k.startswith("posts/"):
                slug, lang = k[6:].rsplit(".", 1)
                alt = posts_meta.get(slug, {}).get("alt") or alt
                if lang != "pt":
                    alt += f" ({lang.upper()})"
            log("enviando mídia", fn)
            if not DRY:
                r = mcp.tool("wp_upload_media", {"filename": "corecyber-" + fn.replace("/", "-"), "content_base64": base64.b64encode(p.read_bytes()).decode(),
                                                 "title": f"CoreCyber {k}", "alt_text": alt})
                media[k] = r["source_url"]; hashes[k] = h
                if r.get("id"):
                    ids[k] = r["id"]
                save_media()
        save_media()

    # ------------------------------------------------ build com URLs do WordPress
    log("gerando build (modo WordPress)")
    subprocess.run([sys.executable, str(ROOT / "tools" / "build.py"), "--wp"], check=True)
    wp = ROOT / "build" / "wp"
    manifest = json.loads((wp / "manifest.json").read_text())

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
        for slug, title in (("", "CoreCyber · Início"), ("404", "CoreCyber · 404")):
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
            return ('<!-- wp:group {"tagName":"main","className":"cc-main","layout":{"type":"default"}} -->\n'
                    '<main class="wp-block-group cc-main">' + inner + '</main>\n<!-- /wp:group -->')
        page_tpl = hdr + "\n" + main_wrap('<!-- wp:post-content {"layout":{"type":"default"}} /-->') + "\n" + ftr
        tpls = {"page": page_tpl, "page-no-title": page_tpl, "single": page_tpl}
        if blocks.get(""):
            tpls["home"] = hdr + "\n" + main_wrap(f'<!-- wp:block {{"ref":{blocks[""]}}} /-->') + "\n" + ftr
        if blocks.get("404"):
            tpls["404"] = hdr + "\n" + main_wrap(f'<!-- wp:block {{"ref":{blocks["404"]}}} /-->') + "\n" + ftr
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
        for ex in ("pagina-exemplo", "sample-page"):
            if ex in by_slug and by_slug[ex]["status"] != "trash":
                log(f"página de exemplo /{ex}/ → lixeira")
                if not DRY:
                    mcp.tool("wp_delete_page", {"page_id": by_slug[ex]["id"], "force": False})
        posts = mcp.tool("wp_list_posts", {"status": "publish", "per_page": 20}).get("posts", [])
        for p in posts:
            if p.get("slug") in ("ola-mundo", "hello-world"):
                log("post de exemplo → lixeira")
                if not DRY:
                    mcp.tool("wp_delete_post", {"post_id": p["id"], "force": False})

    # ------------------------------------------------ matérias (posts)
    if step("posts"):
        pman = json.loads((wp / "posts.json").read_text())
        cats = {c["slug"]: c["id"] for c in mcp.tool("wp_list_categories", {"per_page": 100, "hide_empty": False}).get("categories", [])}
        tags = {t["name"].lower(): t["id"] for t in mcp.tool("wp_list_tags", {"per_page": 100, "hide_empty": False}).get("tags", [])}
        existing = {p["slug"]: p for p in mcp.tool("wp_list_posts", {"status": "any", "per_page": 100}).get("posts", [])}
        for pm in sorted(pman, key=lambda x: x["date"]):
            if pm["category"] not in cats:
                log("criando categoria", pm["category_name"])
                if not DRY:
                    cats[pm["category"]] = mcp.tool("wp_create_category", {"name": pm["category_name"], "slug": pm["category"]})["id"]
            tag_ids = []
            for t in pm["tags"]:
                if t.lower() not in tags:
                    log("criando tag", t)
                    if not DRY:
                        tags[t.lower()] = mcp.tool("wp_create_tag", {"name": t})["id"]
                if t.lower() in tags:
                    tag_ids.append(tags[t.lower()])
            content = html_block((wp / pm["file"]).read_text())
            args = {"title": pm["title"], "content": content, "excerpt": pm["excerpt"], "status": "publish",
                    "date": pm["date"] + "T08:00:00", "slug": pm["slug"], "categories": [cats.get(pm["category"], 1)],
                    "tags": tag_ids, "comment_status": "closed", "ping_status": "closed"}
            if ids.get(pm["cover"]):
                args["featured_media"] = ids[pm["cover"]]
            if pm["slug"] in existing:
                log(f"atualizando matéria {pm['slug']} ({len(content)//1024} KB)")
                if not DRY:
                    mcp.tool("wp_update_post", {"post_id": existing[pm["slug"]]["id"], **args})
            else:
                log(f"criando matéria {pm['slug']} ({len(content)//1024} KB)")
                if not DRY:
                    mcp.tool("wp_create_post", args)

    if step("settings"):
        log("título e descrição do site")
        if not DRY:
            cfg = json.loads((ROOT / "site.config.json").read_text())
            mcp.tool("wp_update_site_settings", {"title": cfg["site"]["name"], "description": cfg["site"]["tagline"],
                                                 "timezone": "America/Sao_Paulo", "date_format": "d/m/Y", "time_format": "H:i"})
    log("pronto:", WP_URL)


if __name__ == "__main__":
    main()
