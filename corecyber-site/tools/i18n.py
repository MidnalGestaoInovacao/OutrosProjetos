#!/usr/bin/env python3
"""Ferramentas de tradução do site CoreCyber.

  python3 tools/i18n.py split N        divide build/i18n/missing.en.json em N lotes (build/i18n/lote-K.json)
  python3 tools/i18n.py merge          junta src/i18n/parts/{en,es}.*.json em src/i18n/{en,es}.json
  python3 tools/i18n.py check [arq]    confere estrutura HTML das traduções contra o texto-fonte
"""
import json, re, sys
from pathlib import Path
from html.parser import HTMLParser

ROOT = Path(__file__).resolve().parent.parent
SRCJ = ROOT / "build" / "i18n" / "source.pt.json"


class Sig(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True); self.sig = []
    def handle_starttag(self, tag, attrs):
        keep = {k: v for k, v in attrs if k in ("href", "class", "id", "data-noi18n", "target", "rel", "style", "lang")}
        self.sig.append(("<", tag, tuple(sorted(keep.items()))))
    def handle_endtag(self, tag):
        self.sig.append((">", tag))


def sig(s):
    p = Sig(); p.feed(s); p.close(); return p.sig


def check(paths):
    src = json.loads(SRCJ.read_text(encoding="utf-8"))
    bad = 0
    for p in paths:
        tr = json.loads(Path(p).read_text(encoding="utf-8"))
        for k, v in tr.items():
            if k not in src:
                continue
            if not isinstance(v, str) or not v.strip():
                print(f"{p}: {k} vazio"); bad += 1; continue
            if sig(src[k]) != sig(v):
                print(f"{p}: {k} estrutura HTML diferente\n   PT: {src[k][:160]}\n   TR: {v[:160]}"); bad += 1
            for tok in re.findall(r"\{[a-z]+\}", src[k]):
                if tok not in v:
                    print(f"{p}: {k} perdeu marcador {tok}"); bad += 1
    print(f"verificação: {bad} problema(s)")
    return bad == 0


def main():
    cmd = sys.argv[1] if len(sys.argv) > 1 else ""
    if cmd == "split":
        n = int(sys.argv[2])
        miss = json.loads((ROOT / "build" / "i18n" / "missing.en.json").read_text(encoding="utf-8"))
        miss_es = json.loads((ROOT / "build" / "i18n" / "missing.es.json").read_text(encoding="utf-8"))
        keys = sorted(set(miss) | set(miss_es))
        usage = json.loads((ROOT / "build" / "i18n" / "usage.json").read_text(encoding="utf-8"))
        src = json.loads(SRCJ.read_text(encoding="utf-8"))
        # agrupa por página para dar contexto ao tradutor
        keys.sort(key=lambda k: (usage.get(k, ["~"])[0], k))
        size = -(-len(keys) // n)
        for i in range(n):
            part = {k: src[k] for k in keys[i * size:(i + 1) * size]}
            (ROOT / "build" / "i18n" / f"lote-{i + 1}.json").write_text(json.dumps(part, ensure_ascii=False, indent=1), encoding="utf-8")
            print(f"lote-{i + 1}.json: {len(part)} textos, {sum(len(v) for v in part.values())} caracteres")
    elif cmd == "merge":
        for lang in ("en", "es"):
            out = ROOT / "src" / "i18n" / f"{lang}.json"
            data = json.loads(out.read_text(encoding="utf-8")) if out.exists() else {}
            for p in sorted((ROOT / "src" / "i18n" / "parts").glob(f"{lang}.*.json")):
                data.update(json.loads(p.read_text(encoding="utf-8")))
            out.write_text(json.dumps(dict(sorted(data.items())), ensure_ascii=False, indent=1), encoding="utf-8")
            print(f"{lang}.json: {len(data)} textos")
    elif cmd == "check":
        files = sys.argv[2:] or [str(p) for p in sorted((ROOT / "src" / "i18n").glob("*.json"))] + [str(p) for p in sorted((ROOT / "src" / "i18n" / "parts").glob("*.json"))]
        sys.exit(0 if check(files) else 1)
    else:
        print(__doc__)


if __name__ == "__main__":
    main()
