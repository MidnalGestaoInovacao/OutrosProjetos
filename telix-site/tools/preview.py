#!/usr/bin/env python3
"""Gera dist/preview/<pagina>.html simulando a renderização do WordPress (tema de blocos)."""
import os, re, json, sys
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
D = os.path.join(ROOT, 'dist')
def unwrap(s): return re.sub(r'<!-- /?wp:html -->', '', s)
header = unwrap(open(os.path.join(D, 'header.html'), encoding='utf-8').read())
footer = unwrap(open(os.path.join(D, 'footer.html'), encoding='utf-8').read())
css = open(os.path.join(D, 'global.css'), encoding='utf-8').read()
os.makedirs(os.path.join(D, 'preview'), exist_ok=True)
pages = json.load(open(os.path.join(D, 'pages.json'), encoding='utf-8'))
for fn, meta in pages.items():
    body = unwrap(open(os.path.join(D, 'pages', fn), encoding='utf-8').read())
    doc = f'''<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{meta.get("title","")} – Télix</title><link rel="https://api.w.org/" href="http://127.0.0.1:8765/wp-json/"><link rel="canonical" href="http://127.0.0.1:8765/{meta.get("slug","")}/">
<style>body{{margin:0}}{css}</style></head><body class="page page-id-999">
<div class="wp-site-blocks"><header class="wp-block-group tx-site-header">{header}</header>
<main class="wp-block-group tx-main" id="conteudo"><div class="entry-content wp-block-post-content is-layout-flow">{body}</div></main>
<footer class="wp-block-group tx-site-footer">{footer}</footer></div></body></html>'''
    doc = doc.replace('"/wp-content/', '"https://telix.ebaem.com.br/wp-content/')  # mídia publicada
    open(os.path.join(D, 'preview', fn), 'w', encoding='utf-8').write(doc)
print('preview ok', len(pages))
