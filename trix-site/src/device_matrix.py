#!/usr/bin/env python3
"""Testa páginas em uma matriz de dispositivos (emulação Chromium): layout, menu, toque, legibilidade, cookies, formulários e 3D.
Uso: python3 src/device_matrix.py [BASE_URL] [OUT_DIR]"""
import os, sys, json
from playwright.sync_api import sync_playwright
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8765"
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "screens", "devices"); os.makedirs(OUT, exist_ok=True)
SUFFIX = ".html" if "127.0.0.1" in BASE else "/"
IOS = "Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1"
IPAD = "Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1"
AND = "Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36"
DESK = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
DEVICES = [
 ("iphone-se", 375, 667, 2, True, IOS), ("galaxy-s8", 360, 740, 3, True, AND), ("pixel-7", 412, 915, 2.625, True, AND), ("iphone-14-pro-max", 430, 932, 3, True, IOS),
 ("iphone-landscape", 844, 390, 3, True, IOS), ("ipad-mini", 768, 1024, 2, True, IPAD), ("ipad-mini-landscape", 1024, 768, 2, True, IPAD), ("ipad-pro-12", 1024, 1366, 2, True, IPAD),
 ("laptop-1280", 1280, 800, 1, False, DESK), ("laptop-1366", 1366, 768, 1, False, DESK), ("desktop-1920", 1920, 1080, 1, False, DESK), ("desktop-2560", 2560, 1440, 1, False, DESK),
]
PAGES = ["home", "prontow", "contato", "conformidade", "politica-de-privacidade", "clientes", "canal-de-compliance"]
if os.environ.get("PAGES"): PAGES = os.environ["PAGES"].split(",")
if os.environ.get("DEVICES"): DEVICES = [d for d in DEVICES if d[0] in os.environ["DEVICES"].split(",")]
METRICS = r"""() => {
  const vw = document.documentElement.clientWidth, vh = innerHeight, hdr = document.querySelector('.trix-header'), hb = hdr ? hdr.getBoundingClientRect() : {bottom:0};
  const vis = el => { const r = el.getBoundingClientRect(), s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && s.opacity !== '0'; };
  const small = []; document.querySelectorAll('main a, main button, main input, main select, main summary, header a, header button, footer a').forEach(el => {
    if (!vis(el)) return; const r = el.getBoundingClientRect(); const inline = el.tagName === 'A' && getComputedStyle(el).display === 'inline' && el.closest('p,li,td,small,span');
    const min = inline ? 20 : 32; if (r.height < min || (!inline && r.width < 32)) small.push((el.innerText || el.getAttribute('aria-label') || el.name || el.tagName).trim().slice(0, 30) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height)); });
  let tiny = 0; const walker = document.createTreeWalker(document.querySelector('main') || document.body, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) { const n = walker.currentNode; if (!n.textContent.trim()) continue; const el = n.parentElement; if (!el || !vis(el)) continue; if (parseFloat(getComputedStyle(el).fontSize) < 12) tiny++; }
  const inputs = Array.from(document.querySelectorAll('form input:not([type=checkbox]):not([type=radio]):not([type=hidden]), form select, form textarea')).filter(vis).map(e => parseFloat(getComputedStyle(e).fontSize));
  const h1 = document.querySelector('h1'), h1r = h1 ? h1.getBoundingClientRect() : null;
  const visual = document.querySelector('.trix-hero__visual'), vr = visual ? visual.getBoundingClientRect() : null;
  const cookie = document.querySelector('.trix-cookie'), cr = cookie && cookie.classList.contains('is-visible') ? cookie.getBoundingClientRect() : null;
  const wide = Array.from(document.querySelectorAll('main *')).filter(e => { const r = e.getBoundingClientRect(); return r.right > vw + 2 && vis(e) && !e.closest('.trix-table-wrap,.trix-prose table,.trix-hero__visual'); }).slice(0, 5).map(e => e.tagName + '.' + (e.className || '').toString().slice(0, 30));
  const logo = document.querySelector('.trix-brand img');
  return { overflowX: document.documentElement.scrollWidth > vw + 1, wideEls: wide, smallTargets: small.slice(0, 12), smallTargetsCount: small.length, tinyText: tiny,
    minInputFont: inputs.length ? Math.min(...inputs) : null, h1BelowHeader: h1r ? h1r.top >= hb.bottom - 1 : null, h1Size: h1 ? parseFloat(getComputedStyle(h1).fontSize) : null,
    visual: vr ? [Math.round(vr.left), Math.round(vr.top), Math.round(vr.width), Math.round(vr.height)] : null, visualInside: vr ? (vr.left >= -1 && vr.right <= vw + 1) : null,
    is3d: !!(visual && visual.classList.contains('is-3d')), cookieHeightRatio: cr ? +(cr.height / vh).toFixed(2) : null, logoOk: !!(logo && logo.complete && logo.naturalWidth > 0),
    burgerVisible: !!(document.querySelector('.trix-burger') && vis(document.querySelector('.trix-burger'))), navVisible: !!(document.querySelector('.trix-nav') && vis(document.querySelector('.trix-nav'))), docHeight: document.documentElement.scrollHeight };
}"""
results = {}
with sync_playwright() as p:
    b = p.chromium.launch(executable_path=os.environ.get("CHROME_PATH", "/opt/pw-browsers/chromium-1194/chrome-linux/chrome"), args=["--use-gl=swiftshader", "--enable-webgl", "--ignore-gpu-blocklist"])
    for name, w, h, dpr, mobile, ua in DEVICES:
        ctx = b.new_context(viewport={"width": w, "height": h}, device_scale_factor=dpr, is_mobile=mobile, has_touch=mobile, user_agent=ua, locale="pt-BR", ignore_https_errors=True)
        page = ctx.new_page(); errs = []
        page.on("pageerror", lambda e: errs.append(str(e)[:160]))
        for slug in PAGES:
            errs.clear(); key = "%s %s" % (name, slug)
            try:
                for attempt in range(3):
                    r = page.goto(BASE + "/" + ("" if slug == "home" and SUFFIX == "/" else slug + SUFFIX), wait_until="load", timeout=60000)
                    if r and r.status < 500: break
                    page.wait_for_timeout(5000)
                try: page.wait_for_selector(".trix-hero__visual.is-3d canvas", timeout=8000)
                except Exception: pass
                page.wait_for_timeout(1200)
                m = page.evaluate(METRICS); m["status"] = r.status if r else None
                if slug == "home": page.screenshot(path=os.path.join(OUT, "%s--cookie.png" % name))
                # a primeira tela é capturada já sem o aviso de cookies (como o visitante vê depois de escolher)
                page.evaluate("() => { const c=document.querySelector('.trix-cookie'); if (c) c.classList.remove('is-visible'); document.documentElement.classList.remove('trix-cookie-open'); }"); page.wait_for_timeout(600)
                page.screenshot(path=os.path.join(OUT, "%s--%s--fold.png" % (name, slug)))
                # botões flutuantes visíveis sobre título, texto ou botões do banner na primeira tela
                m["floatOverlap"] = page.evaluate("""() => { const vis = e => { const s = getComputedStyle(e); return s.display !== 'none' && s.visibility !== 'hidden'; };
                  const fl = [...document.querySelectorAll('.trix-a11y-btn,.trix-fab__main,.trix-top,.trix-cookiebtn')].filter(vis).map(e => e.getBoundingClientRect()).filter(r => r.width);
                  const tg = [...document.querySelectorAll('.trix-hero .trix-btn,.trix-hero h1,.trix-hero .lead')].map(e => e.getBoundingClientRect());
                  const hit = (a, b) => a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom && a.top < innerHeight;
                  let n = 0; fl.forEach(a => tg.forEach(b => { if (hit(a, b)) n++; })); return n; }""")
                # menu: drawer (< 1100px) ou megamenu (>= 1100px)
                if m["burgerVisible"]:
                    page.click(".trix-burger"); page.wait_for_timeout(450)
                    page.click(".trix-drawer details:nth-of-type(3) summary", timeout=5000); page.wait_for_timeout(300)
                    m["drawer"] = page.evaluate("() => { const d=document.querySelector('.trix-drawer'), r=d.getBoundingClientRect(); return {open: d.classList.contains('is-open'), inView: r.left >= -1 && r.right <= innerWidth + 1, links: d.querySelectorAll('a').length, scrollable: d.scrollHeight > d.clientHeight} }")
                    if slug == "home": page.screenshot(path=os.path.join(OUT, "%s--menu.png" % name))
                    page.click(".trix-burger"); page.wait_for_timeout(350)
                elif m["navVisible"]:
                    page.hover(".trix-nav > li:nth-child(4) > button"); page.wait_for_timeout(450)
                    m["mega"] = page.evaluate("() => { const e=document.querySelector('.trix-nav > li.is-open .trix-mega'); if(!e) return null; const r=e.getBoundingClientRect(); return {inView: r.left >= -1 && r.right <= innerWidth + 1 && r.bottom <= innerHeight + 1, box:[Math.round(r.left),Math.round(r.top),Math.round(r.width),Math.round(r.height)]} }")
                    if slug == "home": page.screenshot(path=os.path.join(OUT, "%s--menu.png" % name))
                    page.mouse.move(5, h - 5); page.wait_for_timeout(300)
                if slug in ("contato", "canal-de-compliance", "politica-de-privacidade", "home"):
                    page.screenshot(path=os.path.join(OUT, "%s--%s--full.png" % (name, slug)), full_page=True)
                m["errors"] = list(errs); results[key] = m
                print(key, "ok" if not (m["overflowX"] or errs) else "CHECK", flush=True)
            except Exception as e:
                results[key] = {"exception": str(e)[:200]}; print(key, "EXC", str(e)[:100], flush=True)
        ctx.close()
    b.close()
json.dump(results, open(os.path.join(OUT, "report.json"), "w"), indent=1, ensure_ascii=False)
