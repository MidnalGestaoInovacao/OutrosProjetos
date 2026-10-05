#!/usr/bin/env python3
"""Capas das matérias (infográficos 1200×675) em PT, EN e ES.

Gera src/img/posts/<slug>.<idioma>.webp a partir do cabeçalho de cada matéria (campo "cover")
e das traduções em src/i18n/{en,es}.json. Só refaz as capas cujo conteúdo mudou
(src/img/posts/covers.json guarda o hash de cada uma).

Uso:
  python3 tools/covers.py            # gera o que falta ou mudou
  python3 tools/covers.py --force    # refaz todas
  python3 tools/covers.py --only slug1,slug2

Precisa de Node.js com Playwright (Chromium) e Pillow.
Capas EN/ES só saem quando os textos da capa já estão traduzidos; até lá o site usa a capa PT.
"""
import hashlib
import html
import json
import os
import re
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import build  # noqa: E402  (reaproveita leitura das matérias, chaves de tradução e categorias)
from PIL import Image  # noqa: E402

ROOT = build.ROOT
OUT = ROOT / "src" / "img" / "posts"
WORK = ROOT / "build" / "covers"
FORCE = "--force" in sys.argv
ONLY = set()
if "--only" in sys.argv:
    ONLY = set(sys.argv[sys.argv.index("--only") + 1].split(","))

TR = {l: build.load_tr(l) for l in ("en", "es")}
FIXED = {
    "pt": {"slogan": "Segurança que se vê. Controle que se prova.", "by": "Um produto EBAEM",
           "months": build.MONTHS},
    "en": {"slogan": "Security you can see. Control you can prove.", "by": "An EBAEM product",
           "months": ("January", "February", "March", "April", "May", "June", "July", "August",
                      "September", "October", "November", "December")},
    "es": {"slogan": "Seguridad que se ve. Control que se prueba.", "by": "Un producto de EBAEM",
           "months": ("enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto",
                      "septiembre", "octubre", "noviembre", "diciembre")},
}
CAT_FALLBACK = {
    "en": {"Produto": "Product", "Diferenciais": "Differentiators", "Conformidade": "Compliance",
           "Guias práticos": "Practical guides", "Bastidores": "Behind the scenes"},
    "es": {"Produto": "Producto", "Diferenciais": "Diferenciales", "Conformidade": "Cumplimiento",
           "Guias práticos": "Guías prácticas", "Bastidores": "Detrás de escena"},
}


def tr(text, lang):
    """Texto traduzido (ou None se ainda não houver tradução)."""
    if lang == "pt" or not text:
        return text
    if not any(c.isalpha() for c in text):
        return text
    k = build.key_for(build.norm(html.escape(text, quote=False)))
    v = TR[lang].get(k)
    return html.unescape(v) if v else None


def fmt_date(iso, lang):
    y, m, d = (int(x) for x in iso.split("-"))
    mn = FIXED[lang]["months"][m - 1]
    return f"{mn} {d}, {y}" if lang == "en" else f"{d} de {mn} de {y}"


def texts(p, lang):
    c = p.get("cover", {})
    label = build.CATS[p["category"]][0]
    out = {"title": tr(p["title"], lang), "cat": tr(label, lang) or CAT_FALLBACK.get(lang, {}).get(label),
           "date": fmt_date(p["date"], lang), "icon": p.get("icon", "shield"),
           "slogan": FIXED[lang]["slogan"], "by": FIXED[lang]["by"]}
    for k in build.COVER_KEYS:
        out[k] = tr(c.get(k, ""), lang)
    if any(v is None for v in out.values()):
        return None
    return out


def esc(s):
    """Escapa e impede quebra de linha em palavras com hífen (ex.: e-mail)."""
    out = html.escape(s or "", quote=True)
    return re.sub(r"(\w+(?:-\w+)+)", r'<span style="white-space:nowrap">\1</span>', out)


def page(t, lang):
    icons = build.ICONS
    font = (ROOT / "src" / "fonts" / "Manrope-VariableFont_wght.woff2").as_uri()
    logo = (ROOT / "src" / "img" / "logo-h.webp").as_uri()
    # rede discreta (posições fixas por capa)
    seed = int(hashlib.sha1((t["title"] or "").encode()).hexdigest()[:8], 16)
    pts = []
    for i in range(16):
        seed = (seed * 1103515245 + 12345) & 0x7FFFFFFF
        x = 560 + seed % 640
        seed = (seed * 1103515245 + 12345) & 0x7FFFFFFF
        y = 20 + seed % 640
        pts.append((x, y))
    lines = "".join(f'<line x1="{a[0]}" y1="{a[1]}" x2="{b[0]}" y2="{b[1]}"/>'
                    for i, a in enumerate(pts) for b in pts[i + 1:i + 3] if abs(a[0] - b[0]) + abs(a[1] - b[1]) < 420)
    dots = "".join(f'<circle cx="{x}" cy="{y}" r="3"/>' for x, y in pts)
    return f"""<!doctype html><html lang="{lang}"><head><meta charset="utf-8"><style>
@font-face{{font-family:Manrope;src:url("{font}") format("woff2");font-weight:200 800}}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{width:1200px;height:675px;overflow:hidden}}
body{{font-family:Manrope,system-ui,sans-serif;color:#002141;position:relative;
background:radial-gradient(620px 420px at 100% 0%,rgba(25,195,214,.22),transparent 65%),
radial-gradient(520px 380px at 0% 100%,rgba(0,87,131,.12),transparent 70%),linear-gradient(160deg,#f7fafc 0%,#eef4f8 100%)}}
.grid{{position:absolute;inset:0;background-image:linear-gradient(rgba(0,33,65,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(0,33,65,.045) 1px,transparent 1px);background-size:40px 40px;
-webkit-mask:radial-gradient(900px 600px at 70% 40%,#000 30%,transparent 80%)}}
.net{{position:absolute;inset:0}} .net line{{stroke:#0090ad;stroke-opacity:.16;stroke-width:1.2}} .net circle{{fill:#0090ad;fill-opacity:.28}}
.bigico{{position:absolute;right:-60px;bottom:-90px;width:520px;height:520px;color:#0090ad;opacity:.07;transform:rotate(-12deg)}}
.bigico svg{{width:100%;height:100%;stroke-width:1.1}}
.top{{position:absolute;left:56px;right:56px;top:44px;display:flex;align-items:center;justify-content:space-between}}
.pill{{display:inline-flex;align-items:center;gap:10px;background:#002141;color:#fff;border-radius:999px;padding:10px 20px 10px 14px;font-weight:800;font-size:17px;letter-spacing:.06em;text-transform:uppercase}}
.pill svg{{width:20px;height:20px;color:#19c3d6}}
.date{{margin-left:16px;font-weight:700;font-size:17px;color:#33506e}}
.logo{{height:50px}}
.left{{position:absolute;left:56px;top:128px;width:640px;height:330px;display:flex;flex-direction:column}}
.kicker{{font-size:18px;font-weight:800;letter-spacing:.2em;text-transform:uppercase;color:#00708a;margin-bottom:16px}}
.title{{font-size:48px;line-height:1.1;font-weight:800;letter-spacing:-.02em;color:#002141}}
.right{{position:absolute;left:744px;top:128px;width:400px}}
.card{{background:rgba(255,255,255,.88);border:1px solid #d6e2ec;border-radius:22px;box-shadow:0 22px 50px -26px rgba(0,33,65,.35)}}
.stat{{padding:26px 28px 28px;height:236px;display:flex;flex-direction:column;justify-content:center}}
.stat b{{font-size:88px;line-height:1;font-weight:800;letter-spacing:-.03em;background:linear-gradient(120deg,#005783,#0090ad 55%,#19c3d6);-webkit-background-clip:text;color:transparent;white-space:nowrap}}
.stat span{{margin-top:14px;font-size:21px;line-height:1.3;font-weight:700;color:#24405e}}
.mini{{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}}
.mini .card{{padding:16px 18px;height:116px}}
.mini b{{display:block;font-size:32px;line-height:1;font-weight:800;color:#002141;white-space:nowrap}}
.mini span{{display:block;margin-top:8px;font-size:15px;line-height:1.3;font-weight:650;color:#3d5874}}
.q{{position:absolute;left:56px;top:496px;width:640px;height:92px;display:flex;align-items:center;gap:18px;padding:0 24px 0 20px;border-left:6px solid #19c3d6}}
.q i{{flex:none;width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#002141,#005783);color:#19c3d6;font-style:normal;font-weight:800;font-size:26px}}
.q p{{font-size:21px;line-height:1.3;font-weight:700;color:#002141}}
.foot{{position:absolute;left:56px;right:56px;bottom:30px;padding-top:16px;border-top:1px solid #d3dfe9;display:flex;justify-content:space-between;font-size:17px;font-weight:700;color:#4a6580}}
.foot b{{color:#00708a}}
</style></head><body>
<div style="display:none">{icons}</div>
<div class="grid"></div>
<svg class="net" viewBox="0 0 1200 675">{lines}{dots}</svg>
<div class="bigico"><svg class="ico" fill="none" stroke="currentColor"><use href="#i-{html.escape(t['icon'])}"/></svg></div>
<div class="top"><div><span class="pill"><svg fill="none" stroke="currentColor" stroke-width="2"><use href="#i-{html.escape(t['icon'])}"/></svg>{esc(t['cat'])}</span><span class="date">{esc(t['date'])}</span></div><img class="logo" src="{logo}" alt=""></div>
<div class="left"><div class="kicker">{esc(t['kicker'])}</div><div class="title" id="title">{esc(t['title'])}</div></div>
<div class="right">
  <div class="card stat"><b id="stat">{esc(t['stat'])}</b><span>{esc(t['stat_label'])}</span></div>
  <div class="mini"><div class="card"><b>{esc(t['s2'])}</b><span>{esc(t['s2_label'])}</span></div><div class="card"><b>{esc(t['s3'])}</b><span>{esc(t['s3_label'])}</span></div></div>
</div>
<div class="card q"><i>?</i><p id="q">{esc(t['question'])}</p></div>
<div class="foot"><span><b>corecyber.com.br</b> · {esc(t['slogan'])}</span><span>{esc(t['by'])}</span></div>
<script>
function fit(el, max, min){{var fs=parseFloat(getComputedStyle(el).fontSize);while((el.scrollHeight>max||el.scrollWidth>el.clientWidth+1)&&fs>min){{fs-=1;el.style.fontSize=fs+'px'}}}}
document.fonts.ready.then(function(){{
  var t=document.getElementById('title'); fit(t, 330-60, 28);
  var s=document.getElementById('stat'); fit(s, 100, 40);
  document.querySelectorAll('.mini b').forEach(function(b){{fit(b,40,18)}});
  document.querySelectorAll('.mini span').forEach(function(b){{fit(b,58,11)}});
  document.querySelectorAll('.stat span').forEach(function(b){{fit(b,82,14)}});
  fit(document.getElementById('q'), 84, 14);
  document.body.setAttribute('data-ready','1');
}});
</script></body></html>"""


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    WORK.mkdir(parents=True, exist_ok=True)
    man_p = OUT / "covers.json"
    man = json.loads(man_p.read_text()) if man_p.exists() else {}
    jobs, pending = [], []
    for p in build.load_posts():
        if ONLY and p["slug"] not in ONLY:
            continue
        for lang in ("pt", "en", "es"):
            t = texts(p, lang)
            name = f"{p['slug']}.{lang}"
            if t is None:
                pending.append(name)
                continue
            doc = page(t, lang)
            h = hashlib.sha1(doc.encode()).hexdigest()
            if not FORCE and man.get(name) == h and (OUT / f"{name}.webp").exists():
                continue
            hp = WORK / f"{name}.html"
            hp.write_text(doc, encoding="utf-8")
            jobs.append({"html": hp.as_uri(), "png": str(WORK / f"{name}.png"), "name": name, "hash": h})
    if jobs:
        print(f"[capas] gerando {len(jobs)} imagem(ns)…")
        subprocess.run(["node", str(ROOT / "tools" / "covers-shot.js")], input=json.dumps(jobs), text=True, check=True)
        for j in jobs:
            im = Image.open(j["png"]).convert("RGB")
            im.save(OUT / f"{j['name']}.webp", "WEBP", quality=84, method=6)
            man[j["name"]] = j["hash"]
            print("  ok", j["name"])
        man_p.write_text(json.dumps(dict(sorted(man.items())), indent=1) + "\n")
    else:
        print("[capas] nada a gerar")
    if pending:
        print(f"[capas] aguardando tradução da capa: {len(pending)} ({', '.join(pending[:6])}{'…' if len(pending) > 6 else ''})")


if __name__ == "__main__":
    main()
