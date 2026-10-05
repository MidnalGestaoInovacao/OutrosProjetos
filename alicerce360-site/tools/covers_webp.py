#!/usr/bin/env python3
"""Converte as capas geradas por tools/covers.mjs (PNG) para WebP e remove os PNG."""
import pathlib
from PIL import Image

OUT = pathlib.Path(__file__).resolve().parent.parent / "src" / "img" / "posts"
for png in sorted(OUT.glob("*.png")):
    webp = png.with_suffix(".webp")
    Image.open(png).convert("RGB").save(webp, "WEBP", quality=82, method=6)
    print(f"{webp.name}: {webp.stat().st_size // 1024} KB")
    png.unlink()
