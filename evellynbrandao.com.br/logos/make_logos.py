# -*- coding: utf-8 -*-
"""Gera o kit de logos de Évellyn Brandão em vetor (SVG com textos convertidos em caminhos) e PNG transparente.
Variantes: monograma dourado, horizontal dourado, vertical dourado, horizontal preta, horizontal branca (+ SVGs e ZIP).
Requer: fontTools + brotli (venv), fontes font2.woff2 (Cinzel SemiBold) e font3.woff2 (Montserrat Medium) nesta pasta,
Playwright/Chromium para os PNGs (imggen/render.js)."""
import os, json, subprocess, zipfile
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "out"); os.makedirs(OUT, exist_ok=True)
CINZEL = TTFont(os.path.join(HERE, "font2.woff2"))
MONT = TTFont(os.path.join(HERE, "font3.woff2"))

def text_paths(font, text, size, tracking_em=0.0):
    """Converte texto em caminhos SVG (unidades do desenho). Retorna (lista de (d, x_offset), largura_total)."""
    cmap = font.getBestCmap(); gs = font.getGlyphSet(); upm = font["head"].unitsPerEm
    s = size / upm; x = 0.0; out = []
    for ch in text:
        if ch == " ":
            x += size * 0.32 + tracking_em * size; continue
        gname = cmap.get(ord(ch))
        if gname is None: continue
        pen = SVGPathPen(gs); gs[gname].draw(pen)
        out.append((pen.getCommands(), x))
        x += gs[gname].width * s + tracking_em * size
    x -= tracking_em * size  # sem espaçamento após o último glifo
    return out, x, s

def text_group(font, text, size, tracking_em, x0, baseline, fill, anchor="start"):
    paths, width, s = text_paths(font, text, size, tracking_em)
    if anchor == "middle": x0 -= width / 2
    elif anchor == "end": x0 -= width
    g = '<g fill="%s">' % fill
    for d, xo in paths:
        g += '<path transform="translate(%.3f %.3f) scale(%.5f %.5f)" d="%s"/>' % (x0 + xo, baseline, s, -s, d)
    return g + "</g>", width

GRAD = ('<linearGradient id="ebg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f7e7b0"/><stop offset=".5" stop-color="#d4af37"/><stop offset="1" stop-color="#8a6a1c"/></linearGradient>'
        '<linearGradient id="ebg2" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#8a6a1c"/><stop offset=".55" stop-color="#d4af37"/><stop offset="1" stop-color="#f7e7b0"/></linearGradient>'
        '<linearGradient id="ebgw" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#8a6a1c"/><stop offset=".32" stop-color="#d4af37"/><stop offset=".5" stop-color="#f7e7b0"/><stop offset=".68" stop-color="#d4af37"/><stop offset="1" stop-color="#8a6a1c"/></linearGradient>')

def monogram(fill1, fill2, x=0, y=0):
    """Monograma 80×80 (hexágono duplo, espiga e 'EB' em Cinzel convertido em caminhos)."""
    eb, w = text_group(CINZEL, "EB", 27, 1.5 / 27, 40, 61, fill1, anchor="middle")
    return ('<g transform="translate(%s %s)">'
            '<polygon points="40,3 72,21.5 72,58.5 40,77 8,58.5 8,21.5" fill="none" stroke="%s" stroke-width="1.8"/>'
            '<polygon points="40,9.5 66,24.8 66,55.2 40,70.5 14,55.2 14,24.8" fill="none" stroke="%s" stroke-width=".7" opacity=".7"/>'
            '<g fill="%s"><path d="M40 31.5 V13" stroke="%s" stroke-width="1.3" fill="none" stroke-linecap="round"/>'
            '<path d="M40 30 C34.8 29.6 32.4 26.2 32.6 22.4 C36.6 23 39.6 25.8 40 30Z"/><path d="M40 30 C45.2 29.6 47.6 26.2 47.4 22.4 C43.4 23 40.4 25.8 40 30Z"/>'
            '<path d="M40 24.5 C35.6 24.1 33.6 21.2 33.8 17.9 C37.2 18.4 39.7 20.8 40 24.5Z"/><path d="M40 24.5 C44.4 24.1 46.4 21.2 46.2 17.9 C42.8 18.4 40.3 20.8 40 24.5Z"/>'
            '<path d="M40 19 C36.6 18.7 35.2 16.4 35.4 13.9 C38 14.3 39.8 16.2 40 19Z"/><path d="M40 19 C43.4 18.7 44.8 16.4 44.6 13.9 C42 14.3 40.2 16.2 40 19Z"/></g>'
            '%s<path d="M22 67 H58" stroke="%s" stroke-width=".9" opacity=".8"/></g>') % (x, y, fill1, fill1, fill2, fill2, eb, fill1)

def dot(x, y, r, fill):
    return '<circle cx="%.2f" cy="%.2f" r="%.2f" fill="%s"/>' % (x, y, r, fill)

def svg(view_w, view_h, body, defs=""):
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2f %.2f" width="%.2f" height="%.2f">'
            '<title>Évellyn Brandão — CEO · BS Agro Capital</title><defs>%s</defs>%s</svg>') % (view_w, view_h, view_w, view_h, defs, body)

def build(variant, color):
    """variant: monograma | horizontal | vertical ; color: dourado | preta | branca"""
    if color == "dourado": f1, f2, fw, ft, defs = "url(#ebg)", "url(#ebg2)", "url(#ebgw)", "#948a6c", GRAD
    elif color == "preta": f1 = f2 = fw = "#0a0803"; ft = "#3a3222"; defs = ""
    else: f1 = f2 = fw = "#ffffff"; ft = "#d9d2bf"; defs = ""
    pad = 6
    if variant == "monograma":
        return svg(80 + pad * 2, 80 + pad * 2, monogram(f1, f2, pad, pad), defs)
    if variant == "horizontal":
        word, ww = text_group(CINZEL, "ÉVELLYN BRANDÃO", 22, 0.2, 0, 0, fw)      # medir
        tag_left, tw1 = text_group(MONT, "CEO", 7.4, 0.3, 0, 0, ft)
        tag_right, tw2 = text_group(MONT, "BS AGRO CAPITAL", 7.4, 0.3, 0, 0, ft)
        x0 = pad + 80 + 18
        body = monogram(f1, f2, pad, pad)
        body += text_group(CINZEL, "ÉVELLYN BRANDÃO", 22, 0.2, x0, pad + 44, fw)[0]
        ty = pad + 60
        body += text_group(MONT, "CEO", 7.4, 0.3, x0, ty, ft)[0]
        body += dot(x0 + tw1 + 6.5, ty - 2.4, 1.1, ft)
        body += text_group(MONT, "BS AGRO CAPITAL", 7.4, 0.3, x0 + tw1 + 13, ty, ft)[0]
        return svg(x0 + ww + pad, 80 + pad * 2, body, defs)
    if variant == "vertical":
        word, ww = text_group(CINZEL, "ÉVELLYN BRANDÃO", 20, 0.2, 0, 0, fw)
        W = max(ww, 80) + pad * 2 + 20; cx = W / 2
        body = monogram(f1, f2, cx - 40, pad)
        body += text_group(CINZEL, "ÉVELLYN BRANDÃO", 20, 0.2, cx, pad + 80 + 26, fw, anchor="middle")[0]
        _, tw1 = text_group(MONT, "CEO", 6.8, 0.3, 0, 0, ft); _, tw2 = text_group(MONT, "BS AGRO CAPITAL", 6.8, 0.3, 0, 0, ft)
        total = tw1 + 12 + tw2; tx = cx - total / 2; ty = pad + 80 + 41
        body += text_group(MONT, "CEO", 6.8, 0.3, tx, ty, ft)[0]
        body += dot(tx + tw1 + 6, ty - 2.2, 1.0, ft)
        body += text_group(MONT, "BS AGRO CAPITAL", 6.8, 0.3, tx + tw1 + 12, ty, ft)[0]
        return svg(W, pad + 80 + 41 + pad + 4, body, defs)

VARIANTS = [("monograma", "dourado", 2400), ("horizontal", "dourado", 4000), ("vertical", "dourado", 2400),
            ("horizontal", "preta", 4000), ("horizontal", "branca", 4000), ("monograma", "preta", 2400), ("monograma", "branca", 2400), ("vertical", "preta", 2400), ("vertical", "branca", 2400)]

if __name__ == "__main__":
    import re
    specs = []
    for variant, color, px in VARIANTS:
        s = build(variant, color)
        name = "evellyn-brandao-logo-%s-%s" % (variant, color)
        open(os.path.join(OUT, name + ".svg"), "w", encoding="utf-8").write(s)
        vw, vh = [float(v) for v in re.search(r'viewBox="0 0 ([\d.]+) ([\d.]+)"', s).groups()]
        w = px; h = int(round(px * vh / vw))
        html = ("<!doctype html><html><head><meta charset='utf-8'><style>html,body{margin:0;background:transparent}svg{display:block;width:%dpx;height:%dpx}</style></head><body>%s</body></html>"
                % (w, h, s.replace('width="%.2f" height="%.2f"' % (vw, vh), "")))
        hp = os.path.join(OUT, name + ".html"); open(hp, "w", encoding="utf-8").write(html)
        specs.append({"html": hp, "out": os.path.join(OUT, name + ".png"), "w": w, "h": h, "transparent": True})
    # vetor principal (horizontal dourado) com nome amigável
    open(os.path.join(OUT, "evellyn-brandao-logo-vetor.svg"), "w", encoding="utf-8").write(build("horizontal", "dourado"))
    json.dump(specs, open(os.path.join(OUT, "spec.json"), "w"), indent=1)
    subprocess.run(["node", os.path.join(os.path.dirname(HERE), "imggen", "render.js"), os.path.join(OUT, "spec.json")], check=True)
    # pacote
    with zipfile.ZipFile(os.path.join(OUT, "evellyn-brandao-logos.zip"), "w", zipfile.ZIP_DEFLATED) as z:
        for f in sorted(os.listdir(OUT)):
            if f.endswith((".png", ".svg")) and not f.startswith("_"): z.write(os.path.join(OUT, f), "evellyn-brandao-logos/" + f)
        z.writestr("evellyn-brandao-logos/LEIA-ME.txt", "Kit de logos de Évellyn Brandão\n\n"
                   "- *-dourado.*: versão principal (dourado metálico) para fundos escuros\n- *-preta.*: monocromática para fundos claros e impressão em uma cor\n"
                   "- *-branca.*: monocromática para fundos escuros e fotos\n- *.svg: vetor (textos convertidos em caminhos; escala sem perda)\n- *.png: transparente em alta resolução (2400–4000 px)\n\n"
                   "Área de respiro recomendada: metade da altura do monograma em todos os lados.\nTipografias de origem: Cinzel (nome e monograma) e Montserrat (assinatura).\n")
    for f in sorted(os.listdir(OUT)): print(f, os.path.getsize(os.path.join(OUT, f)))
