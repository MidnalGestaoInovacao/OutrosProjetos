# -*- coding: utf-8 -*-
"""Pré-visualização local (sem WordPress): monta cabeçalho + página + rodapé com o CSS compilado.
Uso: python3 site/preview.py <pasta_saida>  → gera index.html (home), contato.html, conformidade.html, politica.html, materia.html"""
import os, sys, re, json
HERE = os.path.dirname(os.path.abspath(__file__)); ROOT = os.path.dirname(HERE)
sys.path.insert(0, HERE); sys.path.insert(0, ROOT)
os.environ.setdefault("WPMCP_KEY", "preview")
import content_pages as CP, tpl
out = sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "build", "preview")
os.makedirs(out, exist_ok=True)
imgs = {}
for base, _, names in os.walk(os.path.join(ROOT, "assets")):
    for n in names: imgs[n.rsplit(".", 1)[0]] = "file://" + os.path.join(base, n)
for n in os.listdir(os.path.join(ROOT, "imggen", "out")): imgs[n.rsplit(".", 1)[0]] = "file://" + os.path.join(ROOT, "imggen", "out", n)
imgs["logo-white"] = imgs["bs-agro-logo-white"]; imgs["whatsapp-icon"] = imgs["whatsapp"]
EM = {"contato": "contato@bsagro.agr.br", "dpo": "dpo@bsagro.agr.br", "ouvidoria": "ouvidoria@bsagro.agr.br"}
pol = json.load(open(os.path.join(ROOT, "content", "policies.json"), encoding="utf-8")) if os.path.exists(os.path.join(ROOT, "content", "policies.json")) else {}
def R(s):
    s = s.replace("{{HOME}}", "index.html").replace("{{HEADER_CLASS}}", "")
    s = re.sub(r"\{\{U:([a-z0-9-]+)\}\}", lambda m: m.group(1) + ".html", s)
    s = re.sub(r"\{\{LINK:([a-z0-9-]+)\}\}", lambda m: m.group(1) + ".html", s)
    s = re.sub(r"\{\{POST:([a-z0-9-]+)\}\}", lambda m: "materia.html", s)
    s = re.sub(r"\{\{IMG:([A-Za-z0-9_-]+)\}\}", lambda m: imgs.get(m.group(1), ""), s)
    s = re.sub(r"\{\{EMAIL:([a-z]+)\}\}", lambda m: EM[m.group(1)], s)
    s = re.sub(r"\{\{POLICY:([a-z0-9-]+)\}\}", lambda m: R(pol.get(m.group(1), {}).get("content_html", "<p>(texto da política)</p>")), s)
    return s
rd = lambda p: open(os.path.join(HERE, p), encoding="utf-8").read()
css = open(os.path.join(HERE, "build", "bs.min.css"), encoding="utf-8").read()
def page(body, title="Prévia"):
    return ('<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>%s</title><style>%s</style></head>'
            '<body class="flex min-h-screen flex-col bg-paper font-sans text-graphite-900 antialiased"><div class="wp-site-blocks">%s%s%s</div>'
            '<script>%s</script></body></html>') % (title, css, R(rd("header.html")), body, R(rd("footer.html")), rd("bs.js"))
cards = "".join('<li class="wp-block-post"><figure class="wp-block-post-featured-image"><img src="%s" alt=""></figure><div class="wp-block-group bs-postbody"><div class="wp-block-post-terms"><a href="#">%s</a></div><h3 class="wp-block-post-title"><a href="materia.html">%s</a></h3><div class="wp-block-post-excerpt"><p class="wp-block-post-excerpt__excerpt">%s</p></div><div class="wp-block-post-date">%s</div></div></li>' % (imgs.get("capa-" + p["slug"], ""), p["category"], p["title"], p["excerpt"], p["date"]) for p in json.load(open(os.path.join(ROOT, "content", "posts.json"), encoding="utf-8"))[:3])
grid = '<div class="wp-block-query bs-posts bs-posts--3"><ul class="wp-block-post-template">%s</ul></div>' % cards
home = R(rd("home.html")).replace("{{SPLIT}}", grid)
open(os.path.join(out, "index.html"), "w").write(page('<main class="bs-main flex-1" id="conteudo-principal">' + home + "</main>", "Home"))
def banner(title, excerpt, img="vista-lavoura-fileiras-agronegocio"):
    return '<section class="bs-banner"><div class="bs-banner__img"><img src="%s" alt=""></div><div class="bs-banner__in"><nav class="bs-crumbs"><a href="index.html">Início</a><span class="sep">/</span><span>%s</span></nav><h1>%s</h1><p class="bs-lead">%s</p></div></section>' % (imgs[img], title, title, excerpt)
for pg in CP.LANDING_PAGES:
    body = R(pg["html"]).replace("{{PORTAL}}", R(CP.PORTAL_FALLBACK))
    open(os.path.join(out, pg["slug"] + ".html"), "w").write(page('<main class="bs-main">' + banner(pg["title"], pg["excerpt"]) + '<div class="bs-layout bs-layout--full">' + body + "</div></main>", pg["title"]))
if pol.get("aviso-de-privacidade"):
    pd = pol["aviso-de-privacidade"]
    aside = '<aside class="bs-aside"><div class="bs-aside__sticky">' + R(tpl.ASIDE_TOC + tpl.ASIDE_CLIENTE + tpl.ASIDE_CANAIS) + "</div></aside>"
    open(os.path.join(out, "aviso-de-privacidade.html"), "w").write(page('<main class="bs-main">' + banner(pd["title"], pd["excerpt"]) + '<div class="bs-layout"><article class="bs-prose">' + R(pd["content_html"]) + "</article>" + aside + "</div></main>", pd["title"]))
p = json.load(open(os.path.join(ROOT, "content", "posts.json"), encoding="utf-8"))[3]
aside = '<aside class="bs-aside"><div class="bs-aside__sticky">' + R(tpl.ASIDE_TOC + tpl.ASIDE_ANALISE + tpl.ASIDE_CLIENTE) + "</div></aside>"
mb = '<section class="bs-banner"><div class="bs-banner__img"><img src="%s" alt=""></div><div class="bs-banner__in"><nav class="bs-crumbs"><a href="index.html">Início</a><span class="sep">/</span><a href="materias.html">Matérias</a><span class="sep">/</span><a href="#">%s</a></nav><h1>%s</h1><div class="bs-meta"><span>%s</span><span>Equipe BS Agro Capital</span><span data-bs-readtime></span></div></div></section>' % (imgs["capa-" + p["slug"]], p["category"], p["title"], p["date"])
open(os.path.join(out, "materia.html"), "w").write(page('<main class="bs-main">' + mb + '<div class="bs-layout"><article class="bs-prose">' + R(p["content_html"]) + "</article>" + aside + "</div></main>", p["title"]))
print("prévia em", out)
