# -*- coding: utf-8 -*-
"""Extrai a logo da BS Agro Capital do PDF (bs.pdf) e gera: JPG otimizado do mockup original, PNG transparente
(fundo verde removido por chave no canal R, com descontaminação das bordas), versão pequena (600 px) e versão sobre preto."""
import os
from pypdf import PdfReader
from PIL import Image, ImageFilter
HERE = os.path.dirname(os.path.abspath(__file__)); OUT = os.path.join(HERE, "out"); os.makedirs(OUT, exist_ok=True)
page = PdfReader(os.path.join(HERE, "bs.pdf")).pages[0]
open(os.path.join(HERE, "bs-logo-src.png"), "wb").write(page.images[0].data)
src = Image.open(os.path.join(HERE, "bs-logo-src.png")).convert("RGB"); w, h = src.size
src.save(os.path.join(OUT, "bs-agro-capital-logo.jpg"), quality=92, optimize=True, progressive=True)
r, g, b = src.split()
alpha = r.point(lambda v: 0 if v <= 38 else (255 if v >= 95 else int((v - 38) * 255 / 57))).filter(ImageFilter.GaussianBlur(0.6))
rgba = src.copy(); rgba.putalpha(alpha)
bb = alpha.point(lambda v: 255 if v > 40 else 0).getbbox(); pad = 40
crop = rgba.crop((max(0, bb[0]-pad), max(0, bb[1]-pad), min(w, bb[2]+pad), min(h, bb[3]+pad))); px = crop.load()
for y in range(crop.size[1]):
    for x in range(crop.size[0]):
        R, G, B, A = px[x, y]
        if 0 < A < 255:
            t = A / 255.0; px[x, y] = (max(0, min(255, int(R / t))), max(0, min(255, int((G - (1 - t) * 30) / t))), max(0, min(255, int((B - (1 - t) * 15) / t))), A)
crop.save(os.path.join(OUT, "bs-agro-capital-logo-transparente.png"), optimize=True)
crop.resize((600, int(crop.size[1] * 600 / crop.size[0])), Image.LANCZOS).save(os.path.join(OUT, "bs-agro-capital-logo-transparente-600.png"), optimize=True)
black = Image.new("RGB", crop.size, (5, 5, 5)); black.paste(crop, (0, 0), crop); black.save(os.path.join(OUT, "bs-agro-capital-logo-preto.jpg"), quality=92, optimize=True, progressive=True)
print("ok", crop.size)
