#!/usr/bin/env python3
"""Gera docs/SEO.md e docs/seo-meta.csv (importável em Yoast/Rank Math via CSV) a partir dos META das páginas."""
import csv, os, sys
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, 'tools'))
import build  # noqa: E402

rows = []
for fn in sorted(os.listdir(os.path.join(ROOT, 'src', 'pages'))):
    if not fn.endswith('.html'):
        continue
    meta, _ = build.parse_page(os.path.join(ROOT, 'src', 'pages', fn))
    url = '/' if meta.get('front_page') else ('/' + (meta['parent'] + '/' if meta.get('parent') else '') + meta['slug'] + '/')
    s = meta.get('seo', {})
    rows.append((url, meta['title'], s.get('title', ''), s.get('description', ''), s.get('keywords', ''), s.get('pageType', 'WebPage'), ', '.join(x.get('@type', '') for x in s.get('schema', []))))
rows.sort(key=lambda r: r[0])
with open(os.path.join(ROOT, 'docs', 'SEO.md'), 'w', encoding='utf-8') as f:
    f.write('# SEO por página\n\nGerado por `tools/seo_report.py`. Títulos ≤ 60 caracteres e descrições entre 140 e 160 caracteres.\n'
            'Além destes campos, o runtime gera JSON-LD (Organization/LocalBusiness, WebSite, WebPage, BreadcrumbList, FAQPage) em todas as páginas.\n\n'
            '| URL | Título SEO | Descrição | Palavras-chave | Tipo | Schema extra |\n|---|---|---|---|---|---|\n')
    for url, t, st, d, k, pt, sc in rows:
        esc = lambda x: str(x).replace('|', '\\|')
        f.write(f'| `{url}` | {esc(st)} ({len(st)}) | {esc(d)} ({len(d)}) | {esc(k)} | {pt} | {sc} |\n')
with open(os.path.join(ROOT, 'docs', 'seo-meta.csv'), 'w', newline='', encoding='utf-8') as f:
    w = csv.writer(f)
    w.writerow(['url', 'titulo_pagina', 'seo_title', 'meta_description', 'focus_keywords'])
    for url, t, st, d, k, pt, sc in rows:
        w.writerow([url, t, st, d, k.split(',')[0].strip() if k else ''])
print(len(rows), 'páginas')
