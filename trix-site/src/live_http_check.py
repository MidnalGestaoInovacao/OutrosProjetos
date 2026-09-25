#!/usr/bin/env python3
"""Checagem HTTP rápida do site publicado (sem navegador).

Para cada página do build: status 200, versão publicada igual à do build local
("build" no JSON #trix-seo), logo do cabeçalho embutida, banner com título,
visual 3D com a cena esperada e canonical do servidor. Também confere o 404,
robots.txt e o sitemap. Repete a requisição quando o túnel de rede derruba a conexão.

Uso: python3 src/live_http_check.py [BASE]
"""
import sys, os, re, json, glob, subprocess, time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = (sys.argv[1] if len(sys.argv) > 1 else "https://trix.ebaem.com.br").rstrip("/")

def get(url, tries=6):
    for i in range(tries):
        p = subprocess.run(["curl", "-sS", "--max-time", "45", "-H", "Cache-Control: no-cache", "-w", "\n%{http_code}", url + ("&" if "?" in url else "?") + "nc=%d" % time.time_ns()],
                           capture_output=True, text=True)
        if p.returncode == 0:
            body, _, code = p.stdout.rpartition("\n")
            if code.isdigit() and int(code) < 500:
                return int(code), body
        time.sleep(min(2 ** i, 16))
    return None, ""

pages = []
for f in sorted(glob.glob(os.path.join(ROOT, "dist", "pages", "*.json"))):
    d = json.load(open(f))
    m = re.search(r'data-trix-3d="([a-z]+)"', d["content"])
    cm = re.search(r'"canonical": "([^"]+)"', d["content"])
    pages.append((d["url"], d["slug"], d["build"], m.group(1) if m else None, cm.group(1) if cm else None))

problems, rows = [], []
for url, slug, build, scene, canon in pages:
    code, html = get(BASE + url)
    issues = []
    if code != 200: issues.append("status %s" % code)
    else:
        live = re.search(r'"build": "([0-9a-f]+)"', html)
        if not live: issues.append("sem JSON de SEO")
        elif live.group(1) != build: issues.append("versão antiga (%s ≠ %s)" % (live.group(1), build))
        if not re.search(r'class="trix-brand"[^>]*><img src="data:image/png', html): issues.append("logo")
        if 'class="trix-hero__head"' not in html: issues.append("banner sem título no novo formato")
        if scene and 'data-trix-3d="%s"' % scene not in html: issues.append("cena 3D %s ausente" % scene)
        if html.count("<h1") != 1: issues.append("h1=%d" % html.count("<h1"))
        can = re.search(r'<link rel="canonical" href="([^"]+)"', html)
        if not can and url == "/":
            print("   nota: o WordPress não imprime canonical na home servida pelo template; o JS aplica e o mu-plugin passa a imprimir no servidor", flush=True)
        elif not can or can.group(1).rstrip("/") not in ((BASE + url).rstrip("/"), (BASE + (canon or url)).rstrip("/")): issues.append("canonical %s" % (can.group(1) if can else None))
        if 'class="trix-a11y-hbtn"' not in html: issues.append("botão de acessibilidade do cabeçalho")
    rows.append((url, code, scene, issues))
    print("%-48s %s %-8s %s" % (url, code, scene, "OK" if not issues else issues), flush=True)
    if issues: problems.append((url, issues))

code, html = get(BASE + "/nao-existe-404/")
ok404 = code == 404 and 'data-trix-3d="cubes"' in html
print("%-48s %s %s" % ("/nao-existe-404/", code, "OK" if ok404 else "PROBLEMA"))
if not ok404: problems.append(("/nao-existe-404/", ["404"]))
code, robots = get(BASE + "/robots.txt")
okr = code == 200 and "Sitemap:" in robots
print("%-48s %s %s" % ("/robots.txt", code, "OK" if okr else "PROBLEMA"))
if not okr: problems.append(("/robots.txt", ["robots"]))
code, sm = get(BASE + "/wp-sitemap-posts-page-1.xml")
locs = set(re.findall(r"<loc>([^<]+)</loc>", sm))
missing = [u for u, s_, b_, sc_, canon in pages if (BASE + u) not in locs and not canon]
print("%-48s %s %d URLs, faltando: %s" % ("/wp-sitemap-posts-page-1.xml", code, len(locs), missing or "nenhuma"))
if missing: problems.append(("sitemap", missing))
print("\nPÁGINAS: %d | COM PROBLEMA: %d" % (len(pages), len(problems)))
for u, i in problems: print(" -", u, i)
