#!/usr/bin/env python3
"""Verificação visual e funcional com Playwright: screenshots desktop/mobile, erros de console, presença dos widgets."""
import sys, json, os
from playwright.sync_api import sync_playwright
BASE = sys.argv[1] if len(sys.argv) > 1 else "https://trix.ebaem.com.br"
OUT = sys.argv[2] if len(sys.argv) > 2 else "screens"; os.makedirs(OUT, exist_ok=True)
PAGES = ["/", "/empresa/", "/servicos/", "/produtos/prontow/", "/produtos/saw/", "/clientes/", "/conformidade/", "/conformidade/politica-de-privacidade/", "/contato/", "/canal-lgpd/", "/canal-de-compliance/", "/nao-existe-404/"]
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
report = {}
with sync_playwright() as p:
    b = p.chromium.launch(executable_path=os.environ.get('CHROME_PATH', '/opt/pw-browsers/chromium-1194/chrome-linux/chrome'))
    for vp, name in (({"width": 1440, "height": 900}, "desktop"), ({"width": 390, "height": 844}, "mobile")):
        ctx = b.new_context(viewport=vp, user_agent=UA, locale="pt-BR", ignore_https_errors=True)
        for path in PAGES:
            page = ctx.new_page(); errors = []
            page.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
            page.on("pageerror", lambda e: errors.append(str(e)))
            try:
                r = None
                for attempt in range(4):
                    try:
                        r = page.goto(BASE + path + ("?v=%d" % attempt if attempt else ""), wait_until="domcontentloaded", timeout=60000)
                        if r and r.status < 500: break
                    except Exception as e:
                        print("  retry", path, str(e)[:60], flush=True)
                    page.wait_for_timeout(8000)
                page.wait_for_timeout(2500)
                h = page.evaluate("() => document.body.scrollHeight")
                for y in range(0, h, 700): page.evaluate("y => window.scrollTo(0, y)", y); page.wait_for_timeout(80)
                page.evaluate("() => window.scrollTo(0,0)"); page.wait_for_timeout(500)
                try: page.evaluate("() => { var c = document.querySelector('.trix-cookie'); if (c) c.classList.remove('is-visible'); }")
                except Exception: pass
                info = page.evaluate("""() => ({title: document.title, desc: (document.querySelector('meta[name=description]')||{}).content, h1: (document.querySelector('h1')||{}).innerText, ld: document.querySelectorAll('script[type="application/ld+json"]').length, header: !!document.querySelector('.trix-header'), footer: !!document.querySelector('.trix-footer'), cookie: !!document.querySelector('.trix-cookie'), a11y: !!document.querySelector('.trix-a11y-btn'), fab: !!document.querySelector('.trix-fab'), canvas: document.querySelectorAll('canvas').length, forms: document.querySelectorAll('form.trix-form').length, vlibras: !!document.querySelector('[vw]'), bodyClass: document.body.className.slice(0,80)})""")
                info["status"] = r.status if r else None; info["errors"] = errors[:5]
                fn = os.path.join(OUT, name + "-" + (path.strip("/").replace("/", "_") or "home") + ".png")
                page.screenshot(path=fn, full_page=(name == "desktop"))
                report[name + " " + path] = info; print(name, path, r.status if r else None, info["title"], "| errors:", len(errors), flush=True)
            except Exception as e:
                report[name + " " + path] = {"exception": str(e)[:200]}; print("EXC", name, path, str(e)[:120], flush=True)
            page.close()
        ctx.close()
    b.close()
json.dump(report, open(os.path.join(OUT, "report.json"), "w"), indent=1, ensure_ascii=False)
