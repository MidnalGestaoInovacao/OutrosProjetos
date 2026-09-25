#!/usr/bin/env python3
"""Captura o banner (primeira dobra) de todas as prévias e monta folhas de contato para revisão."""
import os, sys, json, glob
from playwright.sync_api import sync_playwright
from PIL import Image
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8765"
OUT = os.path.join(ROOT, "screens", "heroes"); os.makedirs(OUT, exist_ok=True)
slugs = sorted(os.path.basename(f)[:-5] for f in glob.glob(os.path.join(ROOT, "preview", "*.html")))
CHROME = os.environ.get("CHROME_PATH", "/opt/pw-browsers/chromium-1194/chrome-linux/chrome")
report = {}
with sync_playwright() as p:
    b = p.chromium.launch(executable_path=CHROME, args=["--use-gl=swiftshader", "--enable-webgl", "--ignore-gpu-blocklist"])
    for vp, name in (({"width": 1440, "height": 900}, "desktop"), ({"width": 390, "height": 844}, "mobile")):
        ctx = b.new_context(viewport=vp, locale="pt-BR", device_scale_factor=1, ignore_https_errors=True, is_mobile=(name == "mobile"), has_touch=(name == "mobile"))
        ctx.add_init_script("try{document.cookie='trix_cookie_consent='+encodeURIComponent(JSON.stringify({necessary:true,functional:false,analytics:false,marketing:false,v:1}))+'; path=/'}catch(e){}")
        page = ctx.new_page(); errs = []
        page.on("pageerror", lambda e: errs.append(str(e)[:160]))
        for s in slugs:
            errs.clear()
            page.goto("%s/%s.html" % (BASE, s), wait_until="load", timeout=60000)
            try: page.wait_for_selector(".trix-hero__visual.is-3d canvas", timeout=10000)
            except Exception: pass
            page.wait_for_timeout(1800)
            info = page.evaluate("""() => { const v=document.querySelector('.trix-hero__visual'), c=v&&v.querySelector('canvas'), logo=document.querySelector('.trix-brand img'), r=v?v.getBoundingClientRect():null, t=document.querySelector(".trix-hero__head").getBoundingClientRect();
              return {is3d: !!(v&&v.classList.contains('is-3d')), canvas: !!c, scene: v&&v.querySelector('[data-trix-3d]').getAttribute('data-trix-3d'), logoOk: !!(logo&&logo.complete&&logo.naturalWidth>0), visual: r?[Math.round(r.left),Math.round(r.top),Math.round(r.width),Math.round(r.height)]:null, text: [Math.round(t.left),Math.round(t.top),Math.round(t.width),Math.round(t.height)], overflowX: document.documentElement.scrollWidth>document.documentElement.clientWidth+1} }""")
            info["errors"] = list(errs); report["%s %s" % (name, s)] = info
            page.screenshot(path=os.path.join(OUT, "%s-%s.png" % (name, s)), full_page=False)
        ctx.close()
    b.close()
json.dump(report, open(os.path.join(OUT, "report.json"), "w"), indent=1)
# folhas de contato
for name, cols, scale in (("desktop", 4, .28), ("mobile", 6, .42)):
    files = [os.path.join(OUT, "%s-%s.png" % (name, s)) for s in slugs]
    ims = [Image.open(f) for f in files]; w, h = int(ims[0].width * scale), int(ims[0].height * scale)
    for part in range(0, len(ims), cols * 3):
        chunk = ims[part:part + cols * 3]; rows = (len(chunk) + cols - 1) // cols
        sheet = Image.new("RGB", (cols * w, rows * (h + 18)), "white")
        from PIL import ImageDraw
        dr = ImageDraw.Draw(sheet)
        for i, im in enumerate(chunk):
            x, y = (i % cols) * w, (i // cols) * (h + 18); sheet.paste(im.resize((w, h)), (x, y + 18)); dr.text((x + 4, y + 3), slugs[part + i], fill="black")
        sheet.save(os.path.join(OUT, "sheet-%s-%d.png" % (name, part // (cols * 3) + 1)))
bad = {k: v for k, v in report.items() if not v["is3d"] or not v["logoOk"] or v["overflowX"] or v["errors"]}
print("pages:", len(slugs), "| problems:", len(bad)); [print(" ", k, v) for k, v in list(bad.items())[:20]]
