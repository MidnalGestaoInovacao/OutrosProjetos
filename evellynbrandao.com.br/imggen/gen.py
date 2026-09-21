# -*- coding: utf-8 -*-
"""Gera os HTMLs das imagens (logo, favicon, og, visuais e banners das matérias) e renderiza com Playwright."""
import json, os, sys, subprocess, re, html as H

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "out")
os.makedirs(OUT, exist_ok=True)
SITE = os.path.join(os.path.dirname(HERE), "site")
header = open(os.path.join(SITE, "header.html"), encoding="utf-8").read()
# extrai o sprite SVG (símbolos) do header para reutilizar nas imagens
i = header.find("<svg xmlns"); j = header.find("</svg>", i) + 6
SPRITE = header[i:j]

FONTS = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">'

BASE_CSS = """
*{box-sizing:border-box}html,body{margin:0;padding:0}
body{background:#050505;color:#f6f0e2;font-family:'Montserrat',sans-serif;overflow:hidden}
.gtx{background:linear-gradient(90deg,#8a6a1c,#d4af37 32%,#f7e7b0 50%,#d4af37 68%,#8a6a1c);-webkit-background-clip:text;background-clip:text;color:transparent}
.hexbg{position:absolute;inset:0;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='120' height='104' viewBox='0 0 120 104'><path d='M60 2l50 29v58l-50 29L10 89V31z' fill='none' stroke='%23d4af37' stroke-opacity='.10' stroke-width='1.2'/></svg>");background-size:120px 104px;opacity:.9}
.glow{position:absolute;border-radius:50%;filter:blur(90px);opacity:.55}
.logo{width:100%;height:100%}
.brand{font-family:'Cinzel',serif;letter-spacing:.24em;text-transform:uppercase;font-weight:600}
.small{font-family:'Montserrat',sans-serif;letter-spacing:.32em;text-transform:uppercase;font-weight:500;color:#948a6c}
.kicker{font-family:'Montserrat',sans-serif;letter-spacing:.3em;text-transform:uppercase;font-weight:600;color:#d4af37}
.title{font-family:'Playfair Display',serif;font-weight:600;line-height:1.1}
.line{height:1px;background:linear-gradient(90deg,#8a6a1c,#d4af37 40%,#f7e7b0 50%,#d4af37 60%,#8a6a1c)}
.wheat{position:absolute;bottom:-10px;right:-20px;opacity:.14}
"""

def page(body_html, extra_css="", w=1200, h=675, bg="#050505"):
    return ("<!doctype html><html><head><meta charset='utf-8'>%s<style>%s body{width:%dpx;height:%dpx;background:%s}%s</style></head><body>%s%s</body></html>"
            % (FONTS, BASE_CSS, w, h, bg, extra_css, SPRITE, body_html))

def logo_svg(size):
    return '<svg class="logo" style="width:%dpx;height:%dpx" viewBox="0 0 80 80"><use href="#eb-logo-sym"/></svg>' % (size, size)

def wheat_svg(size, color="#d4af37"):
    return ('<svg viewBox="0 0 24 24" style="width:%dpx;height:%dpx;color:%s" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><use href="#i-wheat"/></svg>' % (size, size, color))

def icon_svg(key, size, color="#d4af37"):
    m = re.search(r'<symbol id="i-%s"[^>]*>(.*?)</symbol>' % key, SPRITE, flags=re.S)
    inner = m.group(1) if m else ""
    return ('<svg viewBox="0 0 24 24" style="width:%dpx;height:%dpx;color:%s;filter:drop-shadow(0 0 22px rgba(212,175,55,.35))" fill="none" stroke="currentColor" stroke-width=".55" stroke-linecap="round" stroke-linejoin="round">%s</svg>' % (size, size, color, inner))

def rings_svg(size):
    return ("""<svg viewBox="0 0 400 400" style="width:%dpx;height:%dpx">
<defs><linearGradient id="rg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f7e7b0"/><stop offset=".5" stop-color="#d4af37"/><stop offset="1" stop-color="#6e5416"/></linearGradient>
<radialGradient id="sph" cx=".35" cy=".3" r=".75"><stop offset="0" stop-color="#fff1c2"/><stop offset=".25" stop-color="#e9c85a"/><stop offset=".6" stop-color="#a8842a"/><stop offset="1" stop-color="#2a1e05"/></radialGradient></defs>
<g fill="none" stroke="url(#rg)"><ellipse cx="200" cy="200" rx="185" ry="70" stroke-width="1.5" transform="rotate(-22 200 200)"/><ellipse cx="200" cy="200" rx="150" ry="150" stroke-width=".8" opacity=".6"/><ellipse cx="200" cy="200" rx="185" ry="60" stroke-width="1" transform="rotate(35 200 200)" opacity=".8"/><polygon points="200,60 321,130 321,270 200,340 79,270 79,130" stroke-width="1.2" opacity=".7"/></g>
<circle cx="200" cy="200" r="78" fill="url(#sph)"/>
<circle cx="200" cy="200" r="78" fill="none" stroke="#f7e7b0" stroke-opacity=".5" stroke-width="1"/>
</svg>""" % (size, size))

specs = []

def add(name, body, w, h, extra_css="", transparent=False, bg="#050505", ext="png"):
    p = os.path.join(OUT, name + ".html")
    open(p, "w", encoding="utf-8").write(page(body, extra_css, w, h, bg))
    specs.append({"html": p, "out": os.path.join(OUT, name + "." + ext), "w": w, "h": h, "transparent": transparent})

# ---- logo (transparente) e favicon ----
add("logo", '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">%s</div>' % logo_svg(1000), 1024, 1024, transparent=True, bg="transparent")
add("favicon", '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#050505;border-radius:22%%">%s</div>' % logo_svg(440), 512, 512)
add("logo-horizontal", ('<div style="position:absolute;inset:0;display:flex;align-items:center;gap:40px;padding:60px">%s<div><div class="brand gtx" style="font-size:78px;line-height:1">Évellyn Brandão</div>'
                        '<div class="small" style="font-size:26px;margin-top:22px">CEO · BS Agro Capital</div></div></div>') % logo_svg(300), 1600, 420, transparent=True, bg="transparent")

# ---- imagem de compartilhamento (Open Graph): foto em medalhão dourado + nome ----
AVATAR = os.path.join(os.path.dirname(HERE), "fotos", "out", "evellyn-avatar.jpg")
if os.path.exists(AVATAR):
    photo = ('<div style="position:relative;width:330px;height:330px;flex:none"><div style="position:absolute;inset:-10px;border-radius:50%%;background:linear-gradient(135deg,#f7e7b0,#d4af37 45%%,#6e5416);"></div>'
             '<div style="position:absolute;inset:-4px;border-radius:50%%;background:#050505"></div>'
             '<img src="file://%s" style="position:absolute;inset:0;width:100%%;height:100%%;border-radius:50%%;object-fit:cover">'
             '<div style="position:absolute;right:-6px;bottom:-6px;width:96px;height:96px;border-radius:50%%;background:#050505;display:flex;align-items:center;justify-content:center;border:1px solid rgba(212,175,55,.5)">%s</div></div>' % (AVATAR, logo_svg(70)))
else:
    photo = logo_svg(300)
add("og", ('<div class="hexbg"></div><div class="glow" style="width:700px;height:700px;background:#d4af37;left:-200px;top:-300px;opacity:.25"></div>'
           '<div style="position:absolute;inset:0;display:flex;align-items:center;gap:64px;padding:90px 100px">%s<div><div class="kicker" style="font-size:20px;margin-bottom:22px">CEO e Fundadora da BS Agro Capital</div>'
           '<div class="brand gtx" style="font-size:62px;line-height:1.05">Évellyn Brandão</div><div class="title" style="font-size:38px;margin-top:26px;color:#f6f0e2;font-style:italic">Crédito não é sorte. Crédito é estrutura.</div>'
           '<div class="small" style="font-size:18px;margin-top:34px">evellynbrandao.com.br</div></div></div>') % photo, 1200, 630)

# ---- visuais ----
add("visual-bs", ('<div class="hexbg"></div><div class="glow" style="width:900px;height:900px;background:#d4af37;right:-250px;top:-250px;opacity:.28"></div><div class="glow" style="width:600px;height:600px;background:#8a6a1c;left:-200px;bottom:-300px;opacity:.35"></div>'
                  '<div style="position:absolute;right:120px;top:80px">%s</div>'
                  '<div style="position:absolute;left:110px;bottom:110px;max-width:760px"><div class="kicker" style="font-size:22px;margin-bottom:22px">BS Agro Capital</div><div class="title" style="font-size:64px">Estratégia, capital e parcerias para um campo de <span class="gtx">maiores resultados</span></div><div class="line" style="width:220px;margin-top:34px"></div></div>'
                  '<div class="wheat">%s</div>') % (rings_svg(760), wheat_svg(700)), 1600, 1000, ext="jpg")
add("visual-sobre", ('<div class="hexbg"></div><div class="glow" style="width:800px;height:800px;background:#d4af37;left:50%%;top:50%%;transform:translate(-50%%,-50%%);opacity:.2"></div>'
                     '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">%s</div>'
                     '<div style="position:absolute;left:0;right:0;bottom:80px;text-align:center"><div class="title" style="font-size:54px;font-style:italic">“Crédito não é sorte. <span class="gtx">Crédito é estrutura.</span>”</div><div class="small" style="font-size:18px;margin-top:20px">Évellyn Brandão</div></div>') % rings_svg(640), 1600, 1000, ext="jpg")
add("portrait", ('<div class="hexbg"></div><div class="glow" style="width:700px;height:700px;background:#d4af37;left:50%%;top:40%%;transform:translate(-50%%,-50%%);opacity:.22"></div>'
                 '<div style="position:absolute;inset:70px;border:1px solid rgba(212,175,55,.5);border-radius:40px"></div><div style="position:absolute;inset:86px;border:1px solid rgba(212,175,55,.25);border-radius:32px"></div>'
                 '<div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:34px">%s<div class="brand gtx" style="font-size:54px;text-align:center;line-height:1.2">Évellyn<br>Brandão</div><div class="small" style="font-size:18px">CEO · BS Agro Capital</div></div>') % logo_svg(360), 1000, 1000)

# ---- banners das matérias ----
def banner(slug, title, kicker, icon):
    t = H.escape(title)
    body = ('<div class="hexbg"></div><div class="glow" style="width:760px;height:760px;background:#d4af37;right:-200px;top:-260px;opacity:.26"></div><div class="glow" style="width:500px;height:500px;background:#8a6a1c;left:-160px;bottom:-260px;opacity:.3"></div>'
            '<div style="position:absolute;right:70px;top:50%%;transform:translateY(-50%%);opacity:.95">%s</div>'
            '<div style="position:absolute;left:80px;top:72px;display:flex;align-items:center;gap:18px">%s<div><div class="brand gtx" style="font-size:22px;line-height:1">Évellyn Brandão</div><div class="small" style="font-size:11px;margin-top:6px">Matérias · evellynbrandao.com.br</div></div></div>'
            '<div style="position:absolute;left:80px;bottom:78px;max-width:720px"><div class="kicker" style="font-size:16px;margin-bottom:18px">%s</div><div class="title" style="font-size:%dpx;color:#f6f0e2">%s</div><div class="line" style="width:180px;margin-top:28px"></div></div>'
            % (icon_svg(icon, 380, "#d4af37"), logo_svg(64), H.escape(kicker), 50 if len(title) < 60 else 42, t))
    add("banner-" + slug, body, 1200, 675, ext="jpg")

if __name__ == "__main__":
    posts = []
    for f in ("posts_a.json", "posts_b.json"):
        p = os.path.join(os.path.dirname(HERE), "content", f)
        if os.path.exists(p):
            posts += json.load(open(p, encoding="utf-8"))
    for p in posts:
        banner(p["slug"], p["title"], p.get("kicker", "Matéria"), p.get("icon", "wheat"))
    only = sys.argv[1:]  # opcional: filtra por nome
    if only:
        specs[:] = [s for s in specs if any(o in s["out"] for o in only)]
    spec_path = os.path.join(OUT, "spec.json")
    json.dump(specs, open(spec_path, "w"), indent=1)
    print("rendering", len(specs), "images")
    subprocess.run(["node", os.path.join(HERE, "render.js"), spec_path], check=True)
