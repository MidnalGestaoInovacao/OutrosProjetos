#!/usr/bin/env python3
"""Publica dist/ no WordPress via Easy MCP AI (idempotente; estado em deploy-state.json).
Etapas: blocos reutilizáveis -> global styles -> páginas (2 passes: criar, depois pais) -> templates -> limpeza.
Uso: python3 src/deploy.py [--only blocks|styles|pages|templates|cleanup] [--pages slug1,slug2]
"""
import json, os, sys, re, time
HERE = os.path.dirname(os.path.abspath(__file__)); ROOT = os.path.dirname(HERE); DIST = os.path.join(ROOT, "dist")
sys.path.insert(0, HERE)
from wpmcp import call, MCPError
STATE_F = os.path.join(ROOT, "deploy-state.json")
state = json.load(open(STATE_F)) if os.path.exists(STATE_F) else {"blocks": {}, "pages": {}, "templates": {}}
def save(): json.dump(state, open(STATE_F, "w"), indent=1, ensure_ascii=False)
def log(*a): print(time.strftime("%H:%M:%S"), *a, flush=True)
BLOCK_TITLES = {"estilos": "Trix • Estilos (CSS e configuração)", "cabecalho": "Trix • Cabeçalho (megamenu)", "rodape": "Trix • Rodapé", "widgets": "Trix • Widgets (cookies, acessibilidade, contatos, scripts)"}

def deploy_blocks():
    existing = {}
    try:
        r = call("wp_list_blocks", {"per_page": 100})
        for b in r.get("blocks", r.get("items", [])): existing[b.get("title", "")] = b["id"]
    except Exception as e: log("list blocks:", e)
    for key, title in BLOCK_TITLES.items():
        content = open(os.path.join(DIST, "blocks", key + ".html"), encoding="utf-8").read()
        bid = state["blocks"].get(key) or existing.get(title)
        if bid:
            call("wp_update_block", {"block_id": bid, "content": content, "title": title}); log("block updated", key, bid)
        else:
            r = call("wp_create_block", {"title": title, "content": content, "status": "publish", "slug": "trix-" + key}); bid = r["id"]; log("block created", key, bid)
        state["blocks"][key] = bid; save()

def deploy_styles():
    gs = json.load(open(os.path.join(DIST, "global-styles.json"), encoding="utf-8"))
    r = call("wp_update_global_styles", gs); log("global styles updated", r.get("id"))

def existing_pages():
    out = {}; page = 1
    while True:
        r = call("wp_list_pages", {"status": "any", "per_page": 100, "page": page})
        for p in r.get("pages", []): out[p["slug"]] = p
        if page >= r.get("total_pages", 1): break
        page += 1
    return out

def deploy_pages(only=None):
    pages = [json.load(open(os.path.join(DIST, "pages", f), encoding="utf-8")) for f in sorted(os.listdir(os.path.join(DIST, "pages")))]
    by_slug = {p["slug"]: p for p in pages}
    ex = existing_pages()
    # pass 1: garantir existência (pais antes dos filhos)
    order = sorted(pages, key=lambda p: (0 if not p["parent"] else 1, p["menu_order"]))
    for p in order:
        if only and p["slug"] not in only: continue
        args = {"title": p["wp_title"], "content": p["content"], "status": "publish", "slug": p["slug"], "excerpt": p["excerpt"], "menu_order": p["menu_order"],
                "comment_status": "open" if p["comments"] else "closed", "ping_status": "closed"}
        if p["parent"]:
            pid = state["pages"].get(p["parent"]) or (ex.get(p["parent"]) or {}).get("id")
            if pid: args["parent"] = pid
        pid = state["pages"].get(p["slug"]) or (ex.get(p["slug"]) or {}).get("id")
        for attempt in range(4):
            try:
                if pid:
                    args["page_id"] = pid; r = call("wp_update_page", args, tries=3); log("page updated", p["slug"], pid, r.get("link"))
                else:
                    r = call("wp_create_page", args, tries=2); pid = r["id"]; log("page created", p["slug"], pid, r.get("link"))
                break
            except MCPError as e:
                log("ERROR", p["slug"], str(e)[:200]); time.sleep(15)
                if not pid:  # a criação pode ter ocorrido no servidor apesar da falha do túnel: rechecar por slug
                    try:
                        ex = existing_pages(); pid = (ex.get(p["slug"]) or {}).get("id")
                        if pid: log("found after failure", p["slug"], pid)
                    except MCPError as e2: log("recheck failed", str(e2)[:100])
        if pid: state["pages"][p["slug"]] = pid; save()
        time.sleep(4)

def deploy_templates():
    refs = {"REF_" + k.upper(): v for k, v in state["blocks"].items()}
    for f in sorted(os.listdir(os.path.join(DIST, "templates"))):
        slug = f[:-5]; content = open(os.path.join(DIST, "templates", f), encoding="utf-8").read()
        for k, v in refs.items(): content = content.replace("{{%s}}" % k, str(v))
        tid = "twentytwentyfive//" + slug
        try:
            r = call("wp_update_template", {"template_id": tid, "content": content}); log("template updated", tid); state["templates"][slug] = True; save()
        except MCPError as e: log("template ERROR", tid, str(e)[:200])

def cleanup():
    """Remove páginas de teste, padrão do WordPress e duplicatas/órfãs (não geradas por dist/ ou fora do estado)."""
    ex = existing_pages()
    wanted = {f[:-5] for f in os.listdir(os.path.join(DIST, "pages"))}
    keep_ids = set(state["pages"].values())
    for slug, p in ex.items():
        dup = (slug not in wanted) or (p["id"] not in keep_ids)
        if slug.startswith("teste-") or slug in ("sample-page", "privacy-policy") or dup:
            try: call("wp_delete_page", {"page_id": p["id"], "force": True}); log("deleted", slug, p["id"])
            except MCPError as e: log("delete ERROR", slug, str(e)[:120])
    # blocos duplicados (mesmo título, id diferente do estado)
    try:
        r = call("wp_list_blocks", {"per_page": 100}); keep = set(state["blocks"].values())
        for b in r.get("blocks", r.get("items", [])):
            if b.get("title", "") in BLOCK_TITLES.values() and b["id"] not in keep:
                call("wp_delete_block", {"block_id": b["id"], "force": True}); log("deleted duplicate block", b["id"], b.get("title"))
    except MCPError as e: log("block cleanup ERROR", str(e)[:120])

if __name__ == "__main__":
    only = sys.argv[sys.argv.index("--only") + 1] if "--only" in sys.argv else "all"
    pages_only = sys.argv[sys.argv.index("--pages") + 1].split(",") if "--pages" in sys.argv else None
    if only in ("all", "blocks"): deploy_blocks()
    if only in ("all", "styles"): deploy_styles()
    if only in ("all", "pages"): deploy_pages(pages_only)
    if only in ("all", "templates"): deploy_templates()
    if only in ("cleanup",): cleanup()
    log("done")
