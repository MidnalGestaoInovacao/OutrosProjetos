#!/usr/bin/env python3
"""Envia as imagens originais (trixti.com.br) para a biblioteca de mídia do WordPress, otimizadas, via Easy MCP AI.
Uso: python3 src/media/upload_media.py <dir_imagens> <media_map.json> [upload_todo.json]
As imagens ausentes no diretório são baixadas de https://trixti.com.br/ (mesmo caminho do manifesto)."""
import json, os, sys, time, base64, io, urllib.request
HERE = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, HERE); sys.path.insert(0, os.path.dirname(HERE))
from wpmcp import call
from media_manifest import MEDIA
from PIL import Image
IMG_DIR = sys.argv[1]; MAP = sys.argv[2]; TODO = sys.argv[3] if len(sys.argv) > 3 else None
state = json.load(open(MAP)) if os.path.exists(MAP) else {}
todo = set(json.load(open(TODO))) if TODO and os.path.exists(TODO) else set(MEDIA)
PRIORITY = ["images/39.png", "images/01.png", "images/02.jpg", "favicon.ico", "images/30.png", "images/29.png", "images/prontow/prontow_logo.png", "images/logo_oracle.png", "images/logo_aspect_face.png"]
order = [k for k in PRIORITY + [k for k in MEDIA if k not in PRIORITY] if k in todo]
UA = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/124"
def local_path(key):
    rel = key.split("images/", 1)[1] if key.startswith("images/") else key.replace("lgpd/img/", "lgpd/").replace("webinario/assets/img/", "")
    return os.path.join(IMG_DIR, rel)
def fetch(key):
    p = local_path(key)
    if os.path.exists(p): return open(p, "rb").read()
    req = urllib.request.Request("https://trixti.com.br/" + key, headers={"User-Agent": UA})
    data = urllib.request.urlopen(req, timeout=60).read(); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, "wb").write(data); return data
def optimized(key):
    raw = fetch(key)
    try: im = Image.open(io.BytesIO(raw)); im.load()
    except Exception: return raw, key.rsplit(".", 1)[1]
    w, h = im.size
    if w > 1600: im = im.resize((1600, int(h * 1600 / w)), Image.LANCZOS)
    buf = io.BytesIO()
    if im.mode in ("RGBA", "LA", "P") and not key.endswith((".jpg", ".jpeg")):
        im.convert("RGBA").save(buf, "PNG", optimize=True); ext = "png"
    else:
        im.convert("RGB").save(buf, "JPEG", quality=82, optimize=True, progressive=True); ext = "jpg"
    out = buf.getvalue()
    return (out, ext) if len(out) < len(raw) else (raw, key.rsplit(".", 1)[1])
for key in order:
    if key in state: continue
    fname, alt = MEDIA[key]
    try: data, ext = optimized(key)
    except Exception as e: print("SKIP", key, e, flush=True); continue
    fname = fname.rsplit(".", 1)[0] + "." + ext
    try:
        r = call("wp_upload_media", {"filename": fname, "content_base64": base64.b64encode(data).decode(), "title": fname.rsplit(".", 1)[0], "alt_text": alt}, tries=6)
        state[key] = {"id": r["id"], "url": r["source_url"], "mime": r.get("mime_type")}; print("ok", key, len(data), "->", r["id"], flush=True)
    except Exception as e: print("FAIL", key, len(data), str(e)[:160], flush=True)
    json.dump(state, open(MAP, "w"), indent=1); time.sleep(3)
print("done", len(state), "of", len(MEDIA))
