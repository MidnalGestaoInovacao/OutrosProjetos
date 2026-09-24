#!/usr/bin/env python3
"""Lista as imagens do manifesto realmente usadas pelo site e ainda não enviadas (upload_todo.json)."""
import re, glob, json, sys, os
HERE = os.path.dirname(os.path.abspath(__file__)); ROOT = os.path.dirname(HERE)
scratch = sys.argv[1]; sys.path.insert(0, scratch)
from media_manifest import MEDIA
used = set()
for f in glob.glob(os.path.join(HERE, '**', '*.py'), recursive=True):
    t = open(f, encoding="utf-8").read()
    used |= set(re.findall(r'\{\{media:([^}%]+)\}\}', t)); used |= set(re.findall(r'"image":\s*"(images/[^"]+)"', t))
    used |= set(re.findall(r'image="(images/[^"]+)"', t)); used |= set(re.findall(r'media\("([^"]+)"\)', t))
used |= {k for k in MEDIA if k.startswith("images/unimed/")} | {"images/39.png", "images/01.png", "images/02.jpg", "favicon.ico", "images/economus.jpg", "images/41.png"}
have = json.load(open(os.path.join(scratch, "media_map.json"))) if os.path.exists(os.path.join(scratch, "media_map.json")) else {}
missing = sorted(k for k in used if k in MEDIA and k not in have)
json.dump(missing, open(os.path.join(scratch, "upload_todo.json"), "w"), indent=1)
print("used:", len(used), "uploaded:", len([k for k in used if k in have]), "to upload:", len(missing))
