# -*- coding: utf-8 -*-
"""Tratamento das fotos profissionais (Pillow): níveis, contraste, cor, calor, nitidez e vinheta sutil
combinando com a identidade preto/dourado. Entradas: fotos/foto1.jpg, foto2.jpg, foto3.jpg (960×1280).
Saídas em fotos/out/: evellyn-retrato.jpg (1000×1000), evellyn-avatar.jpg (500×500),
evellyn-campo.jpg (1120×1400), evellyn-campo-wide.jpg (1600×900)."""
import math, os
from PIL import Image, ImageOps, ImageEnhance, ImageFilter, ImageDraw

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "out"); os.makedirs(OUT, exist_ok=True)

def enhance(im, warmth=1.03):
    im = ImageOps.autocontrast(im.convert("RGB"), cutoff=0.4)
    im = ImageEnhance.Contrast(im).enhance(1.06)
    im = ImageEnhance.Color(im).enhance(1.10)
    im = ImageEnhance.Brightness(im).enhance(1.02)
    r, g, b = im.split()
    r = r.point(lambda v: min(255, int(v * warmth))); b = b.point(lambda v: min(255, int(v * (2 - warmth))))
    return Image.merge("RGB", (r, g, b))

def sharpen(im, pct=110, radius=1.6):
    return im.filter(ImageFilter.UnsharpMask(radius=radius, percent=pct, threshold=3))

def vignette(im, strength=0.28, color=(10, 8, 3)):
    w, h = im.size
    mask = Image.new("L", (w, h), 0); d = ImageDraw.Draw(mask)
    cx, cy = w / 2, h / 2; maxr = math.hypot(cx, cy); steps = 60
    for i in range(steps, 0, -1):
        r = maxr * i / steps
        v = int(255 * strength * max(0, (i / steps - 0.55)) / 0.45)
        d.ellipse([cx - r, cy - r * 1.05, cx + r, cy + r * 1.05], fill=v)
    mask = mask.filter(ImageFilter.GaussianBlur(w / 12))
    return Image.composite(Image.new("RGB", (w, h), color), im, mask)

def crop_resize(im, box, size):
    return im.crop(box).resize(size, Image.LANCZOS)

def save(im, name, q=90):
    im.save(os.path.join(OUT, name), quality=q, optimize=True, progressive=True); print("ok", name, im.size)

if __name__ == "__main__":
    f1, f2, f3 = [Image.open(os.path.join(HERE, "foto%d.jpg" % i)) for i in (1, 2, 3)]
    p2 = enhance(f2)
    save(vignette(sharpen(crop_resize(p2, (200, 405, 760, 965), (1000, 1000)), 120, 1.8), 0.30), "evellyn-retrato.jpg")
    av = ImageOps.autocontrast(f2.convert("RGB"), cutoff=0.4); av = ImageEnhance.Contrast(av).enhance(1.05); av = ImageEnhance.Color(av).enhance(1.06)
    save(sharpen(crop_resize(av, (285, 380, 675, 770), (500, 500)), 95, 1.2), "evellyn-avatar.jpg")
    save(vignette(sharpen(crop_resize(enhance(f3), (0, 120, 960, 1320), (1120, 1400)), 105, 1.5), 0.26), "evellyn-campo.jpg", 88)
    save(vignette(sharpen(crop_resize(enhance(f1), (0, 380, 960, 920), (1600, 900)), 105, 1.5), 0.30), "evellyn-campo-wide.jpg", 88)
