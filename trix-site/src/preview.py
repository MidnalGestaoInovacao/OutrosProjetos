#!/usr/bin/env python3
"""Monta uma prévia estática (HTML) de uma página a partir de dist/, para testes locais sem WordPress."""
import json, os, re, sys
HERE = os.path.dirname(os.path.abspath(__file__)); ROOT = os.path.dirname(HERE); DIST = os.path.join(ROOT, "dist")
def strip_block(t): return re.sub(r'<!-- /?wp:html -->', '', t)
def preview(slug, page_id=100):
    p = json.load(open(os.path.join(DIST, "pages", slug + ".json"), encoding="utf-8"))
    blocks = {k: strip_block(open(os.path.join(DIST, "blocks", k + ".html"), encoding="utf-8").read()) for k in ("estilos", "cabecalho", "rodape", "widgets")}
    html = ('<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>%s – Trix Tecnologia Inteligente</title>%s</head>'
            '<body class="page page-id-%d"><div class="wp-site-blocks">%s<main class="wp-block-group trix-main" id="conteudo"><div class="wp-block-post-content">%s</div></main>%s%s</div></body></html>'
            % (p["wp_title"], blocks["estilos"], page_id, blocks["cabecalho"], strip_block(p["content"]), blocks["rodape"], blocks["widgets"]))
    return html
if __name__ == "__main__":
    out = os.path.join(ROOT, "preview"); os.makedirs(out, exist_ok=True)
    slugs = sys.argv[1:] or [f[:-5] for f in os.listdir(os.path.join(DIST, "pages"))]
    for s in slugs:
        open(os.path.join(out, s + ".html"), "w", encoding="utf-8").write(preview(s)); print("preview/%s.html" % s)
