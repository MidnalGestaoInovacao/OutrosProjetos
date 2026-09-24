#!/usr/bin/env python3
"""Valida uma ou mais páginas de src/pages: python3 tools/validate.py src/pages/a-telix.html [...]
Sai com código 1 se houver ERROS. Avisos não bloqueiam."""
import json, os, re, sys
from html.parser import HTMLParser

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, 'tools'))
import build  # noqa: E402

PAGES = {'/', '/a-telix/', '/especialistas-em-saude-suplementar/', '/solucoes/', '/solucoes/central-de-atendimento/', '/solucoes/sac/',
         '/solucoes/sistema-de-protocolo/', '/solucoes/regulacao-e-remocao-hospitalar/', '/solucoes/agendamento/', '/solucoes/pesquisa-de-satisfacao/',
         '/solucoes/atendimento-digital/', '/solucoes/qualidade-e-monitoria/', '/clientes/', '/conformidade/', '/conformidade/politica-de-privacidade/',
         '/conformidade/politica-de-compliance/', '/conformidade/politica-de-seguranca-da-informacao/', '/conformidade/politica-esg/',
         '/conformidade/politica-de-inteligencia-artificial/', '/conformidade/politica-de-cookies/', '/conformidade/glossario-lgpd/', '/canal-lgpd/',
         '/canal-de-denuncia/', '/contato/', '/trabalhe-conosco/', '/acessibilidade/', '/perguntas-frequentes/', '/mapa-do-site/', '/inicio/'}
FORBIDDEN = [
    (r'garant\w+ (a )?conformidade (total|plena)', 'Não prometa conformidade total: a obrigação é da operadora; use "apoiamos".'),
    (r'vaga zero', '"Vaga zero" é prerrogativa do médico regulador — não usar.'),
    (r'regula[cç][aã]o m[eé]dica', 'Evite "regulação médica" (a Télix faz apoio operacional à regulação).'),
    (r'Autoridade Nacional de Prote[cç][aã]o de Dados', 'Desde a Lei 15.352/2026 é "Agência Nacional de Proteção de Dados".'),
    (r'RN\s*(n[ºo°.]*\s*)?259', 'RN 259/2011 foi revogada (use RN 566/2022).'),
    (r'RN\s*(n[ºo°.]*\s*)?395', 'RN 395/2016 foi revogada (use RN 623/2024).'),
    (r'certificad[ao]s? (pela |na )?ISO|ISO[^.]{0,40}certificad', 'Não afirme certificação ISO (não verificada).'),
    (r'Marco Legal da (IA|Intelig)', 'O PL 2338/2023 ainda não é lei.'),
    (r'signat[aá]ri[ao] do Pacto Global', 'Não verificado.'),
    (r'parceir[ao] oficial (da )?Unimed', 'Não verificado.'),
    (r'lorem ipsum', 'Texto de preenchimento.'),
]


class Checker(HTMLParser):
    VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr', 'path', 'use', 'circle', 'rect', 'polygon', 'line', 'ellipse', 'polyline'}

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.stack, self.errors, self.headings, self.imgs, self.links, self.ids = [], [], [], [], [], []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if a.get('id'):
            self.ids.append(a['id'])
        if tag in ('h1', 'h2', 'h3', 'h4', 'h5', 'h6'):
            self.headings.append(int(tag[1]))
        if tag == 'img':
            self.imgs.append(a)
        if tag == 'a' and a.get('href'):
            self.links.append(a['href'])
        if tag not in self.VOID:
            self.stack.append((tag, self.getpos()))

    def handle_startendtag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'img':
            self.imgs.append(a)
        if a.get('id'):
            self.ids.append(a['id'])

    def handle_endtag(self, tag):
        if tag in self.VOID:
            return
        for i in range(len(self.stack) - 1, -1, -1):
            if self.stack[i][0] == tag:
                for t, pos in self.stack[i + 1:]:
                    if t not in ('p', 'li', 'option', 'dt', 'dd', 'td', 'th', 'tr'):
                        self.errors.append(f'tag <{t}> aberta na linha {pos[0]} não foi fechada antes de </{tag}>')
                del self.stack[i:]
                return
        self.errors.append(f'</{tag}> sem abertura (linha {self.getpos()[0]})')


def validate(path):
    errs, warns = [], []
    try:
        meta, body = build.parse_page(path)
    except Exception as e:
        return [f'META inválido: {e}'], []
    for k in ('title', 'slug', 'breadcrumb', 'seo'):
        if k not in meta:
            errs.append(f'META sem "{k}"')
    seo = meta.get('seo', {})
    t, d = seo.get('title', ''), seo.get('description', '')
    if not (25 <= len(t) <= 65):
        warns.append(f'seo.title com {len(t)} caracteres (ideal 30–60)')
    if not (110 <= len(d) <= 160):
        warns.append(f'seo.description com {len(d)} caracteres (ideal 140–160)')
    try:
        rendered = build.render_page(meta, body)
    except Exception as e:
        return errs + [f'build: {e}'], warns
    html = re.sub(r'<!-- /?wp:html -->', '', rendered)
    c = Checker()
    c.feed(re.sub(r'<script\b[^>]*>.*?</script>', '', html, flags=re.S))
    for t2, pos in c.stack:
        if t2 not in ('p', 'li', 'option', 'dt', 'dd', 'td', 'th', 'tr'):
            errs.append(f'tag <{t2}> (linha {pos[0]}) não fechada')
    errs += c.errors[:15]
    if c.headings.count(1) != 1:
        errs.append(f'deve haver exatamente 1 <h1> (há {c.headings.count(1)})')
    prev = 0
    for h in c.headings:
        if prev and h > prev + 1:
            warns.append(f'salto de título h{prev} → h{h}')
        prev = h
    for im in c.imgs:
        if 'alt' not in im:
            errs.append(f'<img> sem alt: {im.get("src", "")[:60]}')
    dup = {i for i in c.ids if c.ids.count(i) > 1}
    if dup:
        errs.append(f'ids duplicados: {sorted(dup)}')
    for href in c.links:
        if href.startswith(('http', 'mailto:', 'tel:', '#', 'javascript')):
            continue
        base = href.split('#')[0].split('?')[0]
        if base and base not in PAGES:
            errs.append(f'link interno desconhecido: {href}')
    text = re.sub(r'<[^>]+>', ' ', html)
    for pat, msg in FORBIDDEN:
        m = re.search(pat, text, re.I)
        if m:
            errs.append(f'expressão proibida "{m.group(0)}": {msg}')
    if '<style' in body:
        warns.append('evite <style> na página (use classes do design system)')
    if meta.get('comments') and 'tx-form' not in body:
        warns.append('comments=true sem formulário')
    return errs, warns


if __name__ == '__main__':
    bad = 0
    for p in sys.argv[1:]:
        e, w = validate(p)
        print(f'== {os.path.basename(p)}: {len(e)} erro(s), {len(w)} aviso(s)')
        for x in e:
            print('  ERRO:', x)
        for x in w:
            print('  aviso:', x)
        bad += bool(e)
    sys.exit(1 if bad else 0)
