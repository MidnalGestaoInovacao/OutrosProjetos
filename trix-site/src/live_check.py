#!/usr/bin/env python3
"""Verificação ao vivo de todas as páginas publicadas (desktop e celular).

Confere, já renderizado no navegador: status HTTP, logo carregada, cena 3D (ou
fallback) no banner, imagem flutuante, título/descrição/canonical/robots/JSON-LD,
um único H1, ausência de rolagem horizontal e erros de console.

Uso: python3 src/live_check.py [BASE] [SAIDA]
     python3 src/live_check.py http://127.0.0.1:8765 screens/preview-check   (prévias locais: /<slug>.html)
"""
import sys, json, os, glob
from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = (sys.argv[1] if len(sys.argv) > 1 else "https://trix.ebaem.com.br").rstrip("/")
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "screens", "live")
os.makedirs(OUT, exist_ok=True)
CHROME = os.environ.get("CHROME_PATH", "/opt/pw-browsers/chromium-1194/chrome-linux/chrome")

PREVIEW = BASE.startswith("http://127.0.0.1") or BASE.startswith("http://localhost")
paths, slug_of = ["/"], {"/": "home"}
for f in sorted(glob.glob(os.path.join(ROOT, "dist", "pages", "*.json"))):
    d = json.load(open(f))
    if d.get("slug") != "home" and d.get("url"):
        paths.append(d["url"]); slug_of[d["url"]] = d["slug"]
if not PREVIEW:
    paths.append("/nao-existe-404/")
def url_of(path, attempt=0):
    if PREVIEW:
        return "%s/%s.html" % (BASE, slug_of[path])
    return BASE + path + ("?v=%d" % attempt if attempt else "")

PROBE = """() => {
  const q = s => document.querySelector(s);
  const logo = q('.trix-brand img');
  const vis = q('.trix-hero__visual');
  const fl = q('.trix-hero__float img, img.trix-hero__float');
  const meta = n => (q('meta[name="'+n+'"]')||{}).content || '';
  const can = q('link[rel=canonical]');
  const ld = [...document.querySelectorAll('script[type="application/ld+json"]')];
  let ldOk = true; ld.forEach(s => { try { JSON.parse(s.textContent); } catch (e) { ldOk = false; } });
  const rect = vis ? vis.getBoundingClientRect() : null;
  return {
    title: document.title, desc: meta('description'), robots: meta('robots'),
    canonical: can ? can.href : '', ogImage: (q('meta[property="og:image"]')||{}).content || '',
    ld: ld.length, ldOk,
    h1: document.querySelectorAll('h1').length,
    logo: logo ? {w: logo.naturalWidth, shown: logo.getBoundingClientRect().width} : null,
    visual: !!vis, is3d: vis ? vis.classList.contains('is-3d') : false,
    canvas: vis ? !!vis.querySelector('canvas') : false,
    visualBox: rect ? [Math.round(rect.x), Math.round(rect.y), Math.round(rect.width), Math.round(rect.height)] : null,
    float: fl ? {src: fl.getAttribute('src').slice(0, 80), w: fl.naturalWidth} : null,
    overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  };
}"""

report, problems = {}, []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path=CHROME, args=["--use-gl=swiftshader", "--enable-webgl", "--ignore-gpu-blocklist"])
    for name, vp, mobile in (("desktop", {"width": 1440, "height": 900}, False), ("mobile", {"width": 390, "height": 844}, True)):
        ctx = b.new_context(viewport=vp, is_mobile=mobile, has_touch=mobile, device_scale_factor=2 if mobile else 1,
                            locale="pt-BR", ignore_https_errors=True)
        def check(path):
            page = ctx.new_page(); errors = []
            page.on("console", lambda m: errors.append(m.text[:160]) if m.type == "error" else None)
            page.on("pageerror", lambda e: errors.append(str(e)[:160]))
            status = None
            for attempt in range(5):
                try:
                    r = page.goto(url_of(path, attempt), wait_until="load", timeout=60000)
                    status = r.status if r else None
                    if status and status < 500:
                        break
                except Exception as e:
                    print("  retry", name, path, str(e)[:70], flush=True)
                page.wait_for_timeout(3000 * (attempt + 1))
            try:
                page.wait_for_function("() => { const v = document.querySelector('.trix-hero__visual'); return !v || v.classList.contains('is-3d'); }", timeout=20000)
            except Exception:
                pass
            page.wait_for_timeout(800)
            try:
                info = page.evaluate(PROBE)
            except Exception as e:
                info = {"exception": str(e)[:160]}
            info["status"] = status
            # falhas de rede do túnel (502/ERR_TOO_MANY_RETRIES/certificado) e de terceiros não são erro do site
            noise = ("Failed to load resource", "ERR_CERT", "vlibras")
            info["netErrors"] = len([e for e in errors if any(n.lower() in e.lower() for n in noise)])
            info["errors"] = [e for e in errors if not any(n.lower() in e.lower() for n in noise)][:5]
            is404 = path.startswith("/nao-existe")
            issues = []
            if status != (404 if is404 else 200): issues.append("status %s" % status)
            if not info.get("logo") or not info["logo"]["w"]: issues.append("logo")
            if not info.get("visual"): issues.append("sem visual no banner")
            elif not info.get("canvas"): issues.append("sem canvas 3D")
            if info.get("float") and not info["float"]["w"] and not PREVIEW: issues.append("imagem flutuante não carregou")
            if info.get("h1") != 1: issues.append("h1=%s" % info.get("h1"))
            if info.get("overflowX", 0) > 1: issues.append("rolagem horizontal %spx" % info["overflowX"])
            if not is404:
                if not (20 <= len(info.get("title", "")) <= 65): issues.append("título %d" % len(info.get("title", "")))
                if not (70 <= len(info.get("desc", "")) <= 160): issues.append("descrição %d" % len(info.get("desc", "")))
                if not info.get("canonical"): issues.append("sem canonical")
                if not info.get("ld") or not info.get("ldOk"): issues.append("JSON-LD")
            elif "noindex" not in info.get("robots", ""):
                issues.append("404 sem noindex")
            if info["errors"]: issues.append("console: %s" % info["errors"][0])
            if path in ("/", "/canal-lgpd/", "/produtos/prontow/", "/contato/") and not issues:
                page.screenshot(path=os.path.join(OUT, "%s-%s.png" % (name, path.strip("/").replace("/", "_") or "home")))
            page.close()
            return info, issues

        for path in paths:
            key = "%s %s" % (name, path)
            for tries in range(4):          # o túnel até o servidor derruba conexões; repete a página inteira
                info, issues = check(path)
                if not issues:
                    break
                print("  recheck", key, issues[:3], flush=True)
                ctx.clear_cookies()
            info["tries"] = tries + 1
            report[key] = info
            if issues:
                problems.append((key, issues))
            print(name, path, info.get("status"), "OK" if not issues else issues, "(tentativas: %d, falhas de rede: %d)" % (tries + 1, info.get("netErrors", 0)), flush=True)
        ctx.close()
    b.close()

json.dump({"report": report, "problems": problems}, open(os.path.join(OUT, "report.json"), "w"), indent=1, ensure_ascii=False)
print("\nPÁGINAS:", len(paths), "| VERIFICAÇÕES:", len(report), "| COM PROBLEMA:", len(problems))
for k, v in problems:
    print(" -", k, v)
