# -*- coding: utf-8 -*-
"""Logo-assinatura de Évellyn Brandão (assinatura-src.jpg, dourado sobre verde): remove o fundo por chave no canal R
(2x Lanczos para bordas suaves), descontamina as bordas com a cor dourada vizinha e gera em out/:
PNG transparente em alta resolução, versão 800 px (cabeçalho/rodapé), versões monocromáticas (branca/preta),
versão sobre preto (JPG) e uma prévia."""
import os
from PIL import Image, ImageFilter, ImageChops
HERE = os.path.dirname(os.path.abspath(__file__)); OUT = os.path.join(HERE, "out"); os.makedirs(OUT, exist_ok=True)
src = Image.open(os.path.join(HERE, "assinatura-src.jpg")).convert("RGB")
W, H = src.size; big = src.resize((W * 2, H * 2), Image.LANCZOS)
r, g, b = big.split()
LO, HI = 18, 205                          # fundo: R 0–12 · ouro: R 190–255
alpha = r.point(lambda v: 0 if v <= LO else (255 if v >= HI else int((v - LO) * 255 / (HI - LO))))
alpha = alpha.filter(ImageFilter.GaussianBlur(0.5))
# cor das bordas: dilata o dourado dos pixels opacos sobre a área semitransparente (evita franja verde)
gold = big
for _ in range(8): gold = gold.filter(ImageFilter.MaxFilter(3))
edge = alpha.point(lambda v: 0 if v >= 250 else 255)
rgb = Image.composite(gold, big, edge)
rgba = rgb.convert("RGBA"); rgba.putalpha(alpha)
bb = alpha.point(lambda v: 255 if v > 10 else 0).getbbox(); pad = int(H * 2 * 0.035)
crop = rgba.crop((max(0, bb[0] - pad), max(0, bb[1] - pad), min(W * 2, bb[2] + pad), min(H * 2, bb[3] + pad)))
print("bbox", bb, "crop", crop.size)
master = crop.resize((2400, int(crop.size[1] * 2400 / crop.size[0])), Image.LANCZOS); master.save(os.path.join(OUT, "evellyn-brandao-assinatura.png"), optimize=True)
small = crop.resize((800, int(crop.size[1] * 800 / crop.size[0])), Image.LANCZOS)
small.save(os.path.join(OUT, "evellyn-brandao-assinatura-800.png"), optimize=True)
a = crop.getchannel("A")
for name, col in (("branca", (255, 255, 255)), ("preta", (10, 8, 3))):
    mono = Image.new("RGBA", crop.size, col + (0,)); mono.putalpha(a); mono.save(os.path.join(OUT, "evellyn-brandao-assinatura-%s.png" % name), optimize=True)
black = Image.new("RGB", crop.size, (5, 5, 5)); black.paste(crop, (0, 0), crop)
black.save(os.path.join(OUT, "evellyn-brandao-assinatura-sobre-preto.jpg"), quality=92, optimize=True, progressive=True)
# prévia: sobre preto, sobre branco (versão preta), sobre dourado (versão branca) e tamanho de cabeçalho
pw = 1400; ph = int(crop.size[1] * pw / crop.size[0]); prev = Image.new("RGB", (pw, ph * 3 + 160), (40, 40, 40))
def put(img, y, bgc):
    tile = Image.new("RGB", (pw, ph), bgc); im = img.resize((pw, ph), Image.LANCZOS); tile.paste(im, (0, 0), im); prev.paste(tile, (0, y))
put(crop, 0, (5, 5, 5)); put(Image.open(os.path.join(OUT, "evellyn-brandao-assinatura-preta.png")), ph, (255, 255, 255))
put(Image.open(os.path.join(OUT, "evellyn-brandao-assinatura-branca.png")), ph * 2, (212, 175, 55))
hdr = Image.new("RGB", (pw, 160), (5, 5, 5)); h64 = small.resize((int(small.size[0] * 64 / small.size[1]), 64), Image.LANCZOS); hdr.paste(h64, (40, 48), h64)
h46 = small.resize((int(small.size[0] * 46 / small.size[1]), 46), Image.LANCZOS); hdr.paste(h46, (80 + h64.size[0], 57), h46); prev.paste(hdr, (0, ph * 3))
prev.save(os.path.join(OUT, "_preview.jpg"), quality=88)
for f in sorted(os.listdir(OUT)): print(f, os.path.getsize(os.path.join(OUT, f)))
