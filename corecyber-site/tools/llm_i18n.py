#!/usr/bin/env python3
"""Traduz textos faltantes do site CoreCyber (PT → EN/ES) usando o FreeLLMAPI.

O FreeLLMAPI expõe um endpoint compatível com a API da OpenAI. A chave unificada
NUNCA fica no repositório: informe por variável de ambiente.

  export FREELLM_URL=http://127.0.0.1:3001/v1/chat/completions   (túnel SSH para a VPS)
  export FREELLM_KEY=freellmapi-...          (ou FREELLM_KEY_FILE=/caminho/arquivo)
  python3 tools/build.py                     # gera build/i18n/missing.{en,es}.json
  python3 tools/llm_i18n.py [--lang en,es] [--batch 25] [--model auto]
  python3 tools/i18n.py merge && python3 tools/i18n.py check

Saída: src/i18n/parts/{lang}.llm.json (acumulativo; rode de novo para continuar).
"""
import json
import os
import sys
import time
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
from i18n import sig  # noqa: E402  (mesma verificação de estrutura HTML do i18n.py)

ARG = dict(zip(sys.argv[1::2], sys.argv[2::2]))
LANGS = ARG.get("--lang", "en,es").split(",")
BATCH = int(ARG.get("--batch", "25"))
MODEL = ARG.get("--model", "auto")
URL = os.environ.get("FREELLM_URL", "http://127.0.0.1:3001/v1/chat/completions")
NAMES = {"en": "English (US)", "es": "Spanish (neutral Latin American)"}
GLOSSARY = (ROOT / "docs" / "GLOSSARIO_I18N.md").read_text(encoding="utf-8") if (ROOT / "docs" / "GLOSSARIO_I18N.md").exists() else ""


def key():
    k = os.environ.get("FREELLM_KEY", "").strip()
    if not k and os.environ.get("FREELLM_KEY_FILE"):
        k = Path(os.environ["FREELLM_KEY_FILE"]).read_text().strip()
    if not k:
        raise SystemExit("Defina FREELLM_KEY (ou FREELLM_KEY_FILE) com a chave unificada do FreeLLMAPI.")
    return k


def ask(batch, lang, k):
    system = ("You translate website strings for CoreCyber, a Brazilian cybersecurity SaaS by EBAEM, from Brazilian Portuguese to "
              f"{NAMES[lang]}. Return ONLY a JSON object with the same keys. Keep every HTML tag and attribute exactly, "
              "keep placeholders like {n}, keep brand names, codes, URLs and e-mails. Natural, persuasive, concise.\n\n" + GLOSSARY)
    body = {"model": MODEL, "temperature": 0.2, "messages": [
        {"role": "system", "content": system},
        {"role": "user", "content": json.dumps(batch, ensure_ascii=False)}]}
    req = urllib.request.Request(URL, data=json.dumps(body).encode(), method="POST",
                                 headers={"Content-Type": "application/json", "Authorization": f"Bearer {k}"})
    with urllib.request.urlopen(req, timeout=180) as r:
        txt = json.loads(r.read().decode())["choices"][0]["message"]["content"].strip()
    if txt.startswith("```"):
        txt = txt.strip("`").split("\n", 1)[1].rsplit("```", 1)[0]
    return json.loads(txt)


def main():
    k = key()
    for lang in LANGS:
        miss = json.loads((ROOT / "build" / "i18n" / f"missing.{lang}.json").read_text(encoding="utf-8"))
        out_p = ROOT / "src" / "i18n" / "parts" / f"{lang}.llm.json"
        out = json.loads(out_p.read_text(encoding="utf-8")) if out_p.exists() else {}
        todo = [x for x in miss if x not in out]
        print(f"[{lang}] {len(todo)} textos a traduzir")
        for i in range(0, len(todo), BATCH):
            chunk = {x: miss[x] for x in todo[i:i + BATCH]}
            for attempt in range(3):
                try:
                    res = ask(chunk, lang, k); break
                except Exception as e:  # rede, limite do provedor ou JSON inválido
                    print("  tentativa", attempt + 1, "falhou:", str(e)[:120]); time.sleep(4 * (attempt + 1)); res = {}
            ok = 0
            for x, v in res.items():
                if x in chunk and isinstance(v, str) and v.strip() and sig(v) == sig(chunk[x]):
                    out[x] = v; ok += 1
            out_p.write_text(json.dumps(dict(sorted(out.items())), ensure_ascii=False, indent=1), encoding="utf-8")
            print(f"  lote {i // BATCH + 1}: {ok}/{len(chunk)} aceitos")
    print("Pronto. Agora: python3 tools/i18n.py merge && python3 tools/i18n.py check")


if __name__ == "__main__":
    main()
