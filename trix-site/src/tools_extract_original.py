#!/usr/bin/env python3
"""Extrai o miolo das páginas originais do portal LGPD (politicas, glossario, cookies) para HTML limpo,
preservando integralmente o texto. Entrada: diretório com os arquivos baixados de trixti.com.br/lgpd/."""
import re, sys, os, html
src = sys.argv[1]; out = os.path.join(os.path.dirname(os.path.abspath(__file__)), "content", "original")
def clean(t):
    t = re.sub(r'<!--.*?-->', '', t, flags=re.S)
    t = re.sub(r'<(script|style)[^>]*>.*?</\1>', '', t, flags=re.S | re.I)
    t = re.sub(r'<img[^>]*>', '', t)
    # remove wrappers de bootstrap/collapse mantendo conteúdo
    t = re.sub(r'<(div|span|section|button|i)\b[^>]*>', lambda m: '' if m.group(1) in ('span', 'i', 'button') else '<%s>' % m.group(1), t)
    t = re.sub(r'</(span|i|button)>', '', t)
    t = re.sub(r'<(h[1-6]|p|ul|ol|li|table|thead|tbody|tr|td|th|a|strong|b|em)\b[^>]*?(href="[^"]*")?[^>]*>', lambda m: '<%s%s>' % (m.group(1), (' ' + m.group(2)) if m.group(2) else ''), t)
    t = re.sub(r'<br\s*/?>', '<br>', t)
    t = re.sub(r'<h1[^>]*>.*?</h1>', '', t, flags=re.S)
    t = re.sub(r'</?div>', '', t)
    t = re.sub(r'<div>\s*</div>', '', t)
    for _ in range(6): t = re.sub(r'<div>\s*</div>', '', t); t = re.sub(r'<p>\s*</p>', '', t)
    t = re.sub(r'\s*\n\s*', '\n', t); t = re.sub(r'[ \t]+', ' ', t)
    t = t.replace('<h2>', '<h2>').replace('./li>', '')
    t = re.sub(r'href="(https?://www\.trixti\.com\.br)?/?lgpd/politicas\.html"', 'href="/conformidade/politica-de-privacidade/"', t)
    t = re.sub(r'href="(https?://www\.trixti\.com\.br)?/?lgpd/cookies\.html"', 'href="/conformidade/cookies/"', t)
    t = re.sub(r'href="(https?://www\.trixti\.com\.br)?/?lgpd/glossario\.html"', 'href="/conformidade/glossario-lgpd/"', t)
    return t.strip()
for name, fn in (("politica", "_lgpd_politicas.html"), ("glossario", "lgpd_glossario.html"), ("cookies", "lgpd_cookies.html")):
    t = open(os.path.join(src, fn), encoding="utf-8", errors="ignore").read()
    m = re.search(r'<section id="comeco"[^>]*>(.*?)<footer', t, re.S)
    body = clean(m.group(1))
    # remove blocos de "solicitar informação/documentos" (CTA do rodapé do portal antigo) se presentes
    body = re.sub(r'<h2>\s*</h2>', '', body)
    open(os.path.join(out, name + ".html"), "w", encoding="utf-8").write(body)
    print(name, len(body), "chars")
