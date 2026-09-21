# -*- coding: utf-8 -*-
"""Cinco conceitos alternativos de logo para Évellyn Brandão, cada um com elementos gráficos distintos:
1 Selo de Trigo (selo circular, ramos de trigo, nome em arco) · 2 Sol & Campo (sol nascente sobre sulcos) ·
3 Arco & Espiga (arco com colunas e espiga: estrutura) · 4 Assinatura (manuscrita com floreio) ·
5 Crescimento (barras ascendentes que viram espiga em moldura).
Saídas em out2/: SVG vetorial (textos em caminhos) e PNG transparente (dourado, preto e branco) + ZIP."""
import os, math, json, subprocess, zipfile, re
from fontTools.ttLib import TTFont
from make_logos import text_group, text_paths, GRAD, CINZEL, MONT

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "out2"); os.makedirs(OUT, exist_ok=True)
SCRIPT = TTFont(os.path.join(HERE, "script.woff2"))

def svg(vw, vh, body, defs=""):
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2f %.2f" width="%.2f" height="%.2f"><title>Évellyn Brandão</title><defs>%s</defs>%s</svg>' % (vw, vh, vw, vh, defs, body))

def palette(color):
    if color == "dourado": return dict(f1="url(#ebg)", f2="url(#ebg2)", fw="url(#ebgw)", ft="#948a6c", defs=GRAD)
    if color == "preta": return dict(f1="#0a0803", f2="#0a0803", fw="#0a0803", ft="#3a3222", defs="")
    return dict(f1="#ffffff", f2="#ffffff", fw="#ffffff", ft="#d9d2bf", defs="")

def text_on_arc(font, text, size, tracking_em, cx, cy, r, center_deg, fill, bottom=False):
    """Texto ao longo de um arco (glifos rotacionados). Topo: lê da esquerda para a direita no sentido horário."""
    cmap = font.getBestCmap(); gs = font.getGlyphSet(); upm = font["head"].unitsPerEm; s = size / upm
    items = []; total = 0.0
    for ch in text:
        if ch == " ": adv = size * 0.32 + tracking_em * size; items.append((None, adv, 0)); total += adv; continue
        g = cmap.get(ord(ch))
        if g is None: continue
        gw = gs[g].width * s; adv = gw + tracking_em * size; items.append((g, adv, gw)); total += adv
    total -= tracking_em * size
    span = total / r
    out = '<g fill="%s">' % fill
    x = 0.0
    for g, adv, gw in items:
        if g is not None:
            from fontTools.pens.svgPathPen import SVGPathPen
            pen = SVGPathPen(gs); gs[g].draw(pen)
            mid = x + gw / 2
            if not bottom:
                a = math.radians(center_deg) - span / 2 + mid / r; rot = math.degrees(a) + 90
            else:
                a = math.radians(center_deg) + span / 2 - mid / r; rot = math.degrees(a) - 90
            px, py = cx + r * math.cos(a), cy + r * math.sin(a)
            out += '<path transform="translate(%.3f %.3f) rotate(%.3f) translate(%.3f 0) scale(%.5f %.5f)" d="%s"/>' % (px, py, rot, -gw / 2, s, -s, pen.getCommands())
        x += adv
    return out + "</g>"

def leaf(x, y, length, width, angle_deg, fill):
    """Folha/grão em forma de lente, ponta em (x,y) apontando na direção angle."""
    return ('<path transform="translate(%.2f %.2f) rotate(%.2f)" d="M0 0 Q %.2f %.2f %.2f 0 Q %.2f %.2f 0 0 Z" fill="%s"/>'
            % (x, y, angle_deg, length / 2, -width / 2, length, length / 2, width / 2, fill))

def spike(cx, base_y, top_y, fill, scale=1.0):
    """Espiga vertical: caule + 3 pares de grãos."""
    body = '<path d="M%.2f %.2f V%.2f" stroke="%s" stroke-width="%.2f" fill="none" stroke-linecap="round"/>' % (cx, base_y, top_y, fill, 1.3 * scale)
    h = base_y - top_y
    for i, t in enumerate((0.25, 0.5, 0.75)):
        y = top_y + h * t; L = 7.5 * scale * (1 - 0.12 * i); W = 3.6 * scale * (1 - 0.1 * i)
        body += leaf(cx, y, L, W, -125, fill) + leaf(cx, y, L, W, -55, fill)
    body += leaf(cx, top_y + h * 0.06, 6 * scale, 3 * scale, -90, fill)
    return body

def wheat_branch(cx, cy, R, a0, a1, fill, mirror=False):
    """Ramo curvo (arco) com folhas alternadas, de a0 a a1 graus (coordenadas SVG)."""
    pts = []
    for i in range(0, 41):
        t = i / 40; a = math.radians(a0 + (a1 - a0) * t); pts.append((cx + R * math.cos(a), cy + R * math.sin(a)))
    d = "M%.2f %.2f " % pts[0] + " ".join("L%.2f %.2f" % p for p in pts[1:])
    body = '<path d="%s" stroke="%s" stroke-width="1.4" fill="none" stroke-linecap="round"/>' % (d, fill)
    for i in range(7):
        t = 0.12 + 0.13 * i; a = math.radians(a0 + (a1 - a0) * t); x, y = cx + R * math.cos(a), cy + R * math.sin(a)
        tang = math.degrees(a) + (90 if not mirror else -90)  # direção do caule
        side = 1 if i % 2 == 0 else -1
        L = 10.5 - 0.7 * i; W = 4.8 - 0.3 * i
        body += leaf(x, y, L, W, tang + side * 38 * (1 if not mirror else -1), fill)
    return body

# ------------------------------------------------------------------ conceitos (emblemas 100×100)
def emblem_selo(p, name=True):
    c = 50; body = ''
    body += '<circle cx="50" cy="50" r="48" fill="none" stroke="%s" stroke-width="1.6"/>' % p["f1"]
    body += '<circle cx="50" cy="50" r="35" fill="none" stroke="%s" stroke-width=".8" opacity=".8"/>' % p["f1"]
    if name:
        body += text_on_arc(CINZEL, "ÉVELLYN BRANDÃO", 8.2, 0.22, 50, 50, 39.5, -90, p["fw"])
        body += text_on_arc(MONT, "CEO • BS AGRO CAPITAL", 5.2, 0.28, 50, 50, 43.5, 90, p["ft"], bottom=True)
        body += '<circle cx="9.5" cy="50" r="1.2" fill="%s"/><circle cx="90.5" cy="50" r="1.2" fill="%s"/>' % (p["f1"], p["f1"])
    body += wheat_branch(50, 51, 27, 108, 205, p["f2"])
    body += wheat_branch(50, 51, 27, 72, -25, p["f2"], mirror=True)
    body += text_group(CINZEL, "EB", 22, 1.5 / 22, 50, 59, p["fw"], anchor="middle")[0]
    return body

def emblem_sol(p):
    body = '<path d="M27 54 A23 23 0 0 1 73 54" fill="none" stroke="%s" stroke-width="2.4" stroke-linecap="round"/>' % p["f1"]
    for k in range(7):
        a = math.radians(200 + k * 140 / 6); x1, y1 = 50 + 28 * math.cos(a), 54 + 28 * math.sin(a); x2, y2 = 50 + 35 * math.cos(a), 54 + 35 * math.sin(a)
        body += '<path d="M%.2f %.2f L%.2f %.2f" stroke="%s" stroke-width="1.8" stroke-linecap="round"/>' % (x1, y1, x2, y2, p["f2"])
    body += '<path d="M12 54 H88" stroke="%s" stroke-width="1.6" stroke-linecap="round"/>' % p["f1"]
    for i, (yy, w) in enumerate(((63, 2.2), (72, 1.7), (81, 1.2))):
        inset = 6 + i * 5
        body += '<path d="M%.1f %.1f Q50 %.1f %.1f %.1f" stroke="%s" stroke-width="%.1f" fill="none" stroke-linecap="round"/>' % (16 + inset, yy, yy - 7, 84 - inset, yy, p["f2"], w)
    body += spike(50, 54, 30, p["f2"], 0.9).replace('stroke-width="1.17"', 'stroke-width="1.2"') if False else ""
    return body

def emblem_arco(p):
    body = ''
    for x in (20, 68):
        body += '<rect x="%d" y="42" width="12" height="34" rx="1" fill="%s"/>' % (x, p["f2"])
        body += '<rect x="%d" y="38" width="18" height="4.5" rx="1" fill="%s"/><rect x="%d" y="76" width="18" height="4.5" rx="1" fill="%s"/>' % (x - 3, p["f1"], x - 3, p["f1"])
    body += '<path d="M20 44 A30 30 0 0 1 80 44" fill="none" stroke="%s" stroke-width="3.2" stroke-linecap="round"/>' % p["f1"]
    body += '<path d="M27 46 A23 23 0 0 1 73 46" fill="none" stroke="%s" stroke-width=".9" opacity=".8"/>' % p["f1"]
    body += spike(50, 78, 26, p["f2"], 1.7)
    body += '<path d="M10 84 H90" stroke="%s" stroke-width="1.8" stroke-linecap="round"/>' % p["f1"]
    return body

def emblem_crescimento(p):
    body = '<rect x="7" y="7" width="86" height="86" rx="20" fill="none" stroke="%s" stroke-width="2"/>' % p["f1"]
    body += '<rect x="12" y="12" width="76" height="76" rx="16" fill="none" stroke="%s" stroke-width=".7" opacity=".7"/>' % p["f1"]
    for i, (x, y, h) in enumerate(((22, 62, 18), (36, 50, 30), (50, 38, 42))):
        body += '<rect x="%d" y="%d" width="10" height="%d" rx="2.5" fill="%s" opacity="%.2f"/>' % (x, y, h, p["f2"], 0.7 + 0.15 * i)
    body += spike(72, 80, 22, p["f2"], 1.3)
    body += '<path d="M18 82 H82" stroke="%s" stroke-width="1.6" stroke-linecap="round"/>' % p["f1"]
    return body

def flourish(x0, y0, w, fill):
    return ('<path d="M%.1f %.1f C %.1f %.1f, %.1f %.1f, %.1f %.1f S %.1f %.1f, %.1f %.1f" fill="none" stroke="%s" stroke-width="1.1" stroke-linecap="round"/>'
            % (x0, y0, x0 + w * 0.2, y0 + 6, x0 + w * 0.45, y0 - 4, x0 + w * 0.65, y0 + 1, x0 + w * 0.9, y0 + 4, x0 + w, y0 - 1, fill)) + leaf(x0 + w, y0 - 1, 7, 3.4, -20, fill)

# ------------------------------------------------------------------ lockups
def lockup_horizontal(emblem_fn, p, name="ÉVELLYN BRANDÃO", tag=("CEO", "BS AGRO CAPITAL"), pad=6, em=100):
    _, ww = text_group(CINZEL, name, 24, 0.2, 0, 0, p["fw"])
    x0 = pad + em + 20
    body = '<g transform="translate(%d %d)">%s</g>' % (pad, pad, emblem_fn(p))
    body += text_group(CINZEL, name, 24, 0.2, x0, pad + 54, p["fw"])[0]
    ty = pad + 71
    _, tw1 = text_group(MONT, tag[0], 8, 0.32, 0, 0, p["ft"])
    body += text_group(MONT, tag[0], 8, 0.32, x0, ty, p["ft"])[0]
    body += '<circle cx="%.2f" cy="%.2f" r="1.2" fill="%s"/>' % (x0 + tw1 + 7, ty - 2.6, p["ft"])
    body += text_group(MONT, tag[1], 8, 0.32, x0 + tw1 + 14, ty, p["ft"])[0]
    return svg(x0 + ww + pad, em + pad * 2, body, p["defs"])

def emblem_only(emblem_fn, p, pad=6):
    return svg(100 + pad * 2, 100 + pad * 2, '<g transform="translate(%d %d)">%s</g>' % (pad, pad, emblem_fn(p)), p["defs"])

def assinatura(p, pad=8):
    paths, w, s = text_paths(SCRIPT, "Évellyn Brandão", 44, 0.0)
    body = text_group(SCRIPT, "Évellyn Brandão", 44, 0.0, pad + 4, pad + 40, p["fw"])[0]
    body += flourish(pad + 10, pad + 50, w - 6, p["f2"])
    _, tw1 = text_group(MONT, "CEO", 7, 0.36, 0, 0, p["ft"]); _, tw2 = text_group(MONT, "BS AGRO CAPITAL", 7, 0.36, 0, 0, p["ft"])
    total = tw1 + 13 + tw2; tx = pad + 4 + (w - total) / 2; ty = pad + 66
    body += text_group(MONT, "CEO", 7, 0.36, tx, ty, p["ft"])[0]
    body += '<circle cx="%.2f" cy="%.2f" r="1.1" fill="%s"/>' % (tx + tw1 + 6.5, ty - 2.3, p["ft"])
    body += text_group(MONT, "BS AGRO CAPITAL", 7, 0.36, tx + tw1 + 13, ty, p["ft"])[0]
    return svg(w + pad * 2 + 12, 78 + pad, body, p["defs"])

CONCEPTS = [
    ("opcao-1-selo-de-trigo", lambda p: emblem_only(lambda q: emblem_selo(q, True), p, 4), lambda p: lockup_horizontal(lambda q: emblem_selo(q, False), p)),
    ("opcao-2-sol-e-campo", lambda p: emblem_only(emblem_sol, p), lambda p: lockup_horizontal(emblem_sol, p)),
    ("opcao-3-arco-e-espiga", lambda p: emblem_only(emblem_arco, p), lambda p: lockup_horizontal(emblem_arco, p)),
    ("opcao-4-assinatura", None, lambda p: assinatura(p)),
    ("opcao-5-crescimento", lambda p: emblem_only(emblem_crescimento, p), lambda p: lockup_horizontal(emblem_crescimento, p)),
]

if __name__ == "__main__":
    specs = []
    def emit(name, s, px):
        open(os.path.join(OUT, name + ".svg"), "w", encoding="utf-8").write(s)
        vw, vh = [float(v) for v in re.search(r'viewBox="0 0 ([\d.]+) ([\d.]+)"', s).groups()]
        w = px; h = int(round(px * vh / vw))
        html = ("<!doctype html><html><head><meta charset='utf-8'><style>html,body{margin:0;background:transparent}svg{display:block;width:%dpx;height:%dpx}</style></head><body>%s</body></html>"
                % (w, h, s.replace('width="%.2f" height="%.2f"' % (vw, vh), "")))
        hp = os.path.join(OUT, name + ".html"); open(hp, "w", encoding="utf-8").write(html)
        specs.append({"html": hp, "out": os.path.join(OUT, name + ".png"), "w": w, "h": h, "transparent": True})
    for slug, emb, lock in CONCEPTS:
        for color in ("dourado", "preta", "branca"):
            p = palette(color)
            emit("%s-horizontal-%s" % (slug, color), lock(p), 4000)
            if emb: emit("%s-emblema-%s" % (slug, color), emb(p), 2400)
    json.dump(specs, open(os.path.join(OUT, "spec.json"), "w"), indent=1)
    subprocess.run(["node", os.path.join(os.path.dirname(HERE), "imggen", "render.js"), os.path.join(OUT, "spec.json")], check=True)
    with zipfile.ZipFile(os.path.join(OUT, "evellyn-brandao-logos-conceitos.zip"), "w", zipfile.ZIP_DEFLATED) as z:
        for f in sorted(os.listdir(OUT)):
            if f.endswith((".png", ".svg")) and not f.startswith("_"): z.write(os.path.join(OUT, f), "evellyn-brandao-logos-conceitos/" + f)
        z.writestr("evellyn-brandao-logos-conceitos/LEIA-ME.txt", "Cinco conceitos alternativos de logo — Évellyn Brandão\n\n"
                   "1 Selo de Trigo: selo circular com ramos de trigo, monograma EB e nome em arco\n2 Sol & Campo: sol nascente sobre o horizonte e sulcos do campo\n"
                   "3 Arco & Espiga: arco com colunas (estrutura, crédito) abrigando uma espiga (agro)\n4 Assinatura: nome manuscrito com floreio dourado e grão\n"
                   "5 Crescimento: barras ascendentes que culminam em uma espiga, em moldura\n\nCada conceito em dourado (principal), preto (fundos claros) e branco (fundos escuros); "
                   "SVG vetorial (textos em caminhos) e PNG transparente em alta resolução.\n")
    for f in sorted(os.listdir(OUT)):
        if f.endswith((".png", ".zip")): print(f, os.path.getsize(os.path.join(OUT, f)))
