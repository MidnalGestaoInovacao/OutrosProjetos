#!/usr/bin/env python3
"""Gera o template da página inicial (Moodle / Edwiser RemUI) a partir do protótipo "vert-academy".

Uso: python3 build_home.py <pasta-do-prototipo> <pasta-de-saida>

Saída:
  home.css        -> CSS escopado em .va-home (vai no Custom CSS do tema)
  bloco-9.html    -> Hero + Números        (região full-width-top)
  bloco-10.html   -> Trilhas de Aprendizagem (região full-bottom)
  bloco-11.html   -> Formações Recomendadas + CTA final
  bloco-12.html   -> Rodapé institucional

Regras de conversão:
  * todas as classes do protótipo recebem o prefixo "va-" (evita conflito com Bootstrap/Moodle);
  * todo seletor é escopado em ".va-home"; os tokens (:root) passam a valer em ".va-home";
  * caminhos "assets/..." viram URLs da pasta pública do Moodle (ASSET_BASE);
  * âncoras do protótipo viram links reais do Moodle (LINKS).
"""
import re, sys, os, html as H
from urllib.parse import quote

SRC, OUT = sys.argv[1], sys.argv[2]
ASSET_BASE = 'https://ead.vert.com.br/pluginfile.php/72/mod_folder/content/0/'
SITE = 'https://ead.vert.com.br'

LINKS = {
    # hero / cta
    ('heroBtnExplorar', '#cursos'): SITE + '/my/courses.php',
    ('heroBtnJornada', '#trilhas'): SITE + '/my/',
    ('btnVerTodosCursos', '#cursos'): SITE + '/course/index.php',
    ('btnFinalExplorar', '#cursos'): SITE + '/my/courses.php',
}
ANCHORS = {
    '#trilha-cloud': SITE + '/course/index.php',
    '#trilha-analytics': SITE + '/course/index.php',
    '#trilha-dados': SITE + '/course/index.php',
    '#trilha-governanca': SITE + '/course/index.php',
    '#trilhas': '#va-trilhas',
    '#stats': '#va-stats',
    '#cursos': SITE + '/course/index.php',
}

src_html = open(os.path.join(SRC, 'index.html'), encoding='utf-8').read()

def section(id_):
    m = re.search(r'(<(section|footer)\b[^>]*\bid="' + id_ + r'"[^>]*>.*?</\2>)', src_html, re.S)
    if not m:
        raise SystemExit('seção não encontrada: ' + id_)
    return m.group(1)

def prefix_classes(fragment):
    def repl(m):
        cls = ' '.join('va-' + c for c in m.group(1).split())
        return 'class="' + cls + '"'
    return re.sub(r'class="([^"]*)"', repl, fragment)

def fix_links(fragment):
    # botões com id conhecido
    def by_id(m):
        tag = m.group(0)
        idm = re.search(r'\bid="([^"]+)"', tag)
        hrefm = re.search(r'href="([^"]*)"', tag)
        if idm and hrefm and (idm.group(1), hrefm.group(1)) in LINKS:
            return tag.replace('href="%s"' % hrefm.group(1), 'href="%s"' % LINKS[(idm.group(1), hrefm.group(1))])
        return tag
    fragment = re.sub(r'<a\b[^>]*>', by_id, fragment)
    # cursos em destaque -> busca no catálogo pelo título do card
    def course(m):
        title = H.unescape(m.group(2))
        return m.group(1) + SITE + '/course/search.php?search=' + quote(title)
    fragment = re.sub(r'(<a href=")#curso-[a-z-]+(" class="course-circle-btn" aria-label="Acessar ([^"]+)")',
                      lambda m: m.group(0), fragment)
    fragment = re.sub(r'href="#curso-[a-z-]+" class="va-course-circle-btn"\s+aria-label="Acessar ([^"]+)"',
                      lambda m: 'href="%s/course/search.php?search=%s" class="va-course-circle-btn" aria-label="Acessar %s"'
                                % (SITE, quote(H.unescape(m.group(1))), m.group(1)), fragment)
    for a, b in ANCHORS.items():
        fragment = fragment.replace('href="%s"' % a, 'href="%s"' % b)
    return fragment

def fix_assets(fragment):
    return re.sub(r'(src|href)="assets/([^"]+)"', lambda m: '%s="%s%s"' % (m.group(1), ASSET_BASE, m.group(2)), fragment)

def ids(fragment):
    for i in ('hero', 'stats', 'trilhas', 'cursos', 'cta', 'footer'):
        fragment = fragment.replace('id="%s"' % i, 'id="va-%s"' % i)
    return fragment

def convert(fragment):
    return ids(fix_assets(fix_links(prefix_classes(fragment))))

FONT_LINK = ('<link rel="preconnect" href="https://fonts.googleapis.com">'
             '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
             '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Syne:wght@400;500;600;700;800&display=swap">')

def wrap(inner, extra=''):
    return '<div class="va-home%s">\n%s\n</div>' % (extra, inner)

hero = convert(section('hero'))
stats = convert(section('stats'))
tracks = convert(section('trilhas'))
courses = convert(section('cursos'))
cta = convert(section('cta'))
footer = convert(section('footer'))

# Rodapé: links reais (políticas públicas deste site e redes sociais oficiais da Vert)
footer = footer.replace(
    '<li><a href="#va-stats" class="va-footer-nav-link">Suporte ao Colaborador</a></li>',
    '<li><a href="%s/admin/tool/policy/view.php?policyid=1" class="va-footer-nav-link">Política de Privacidade</a></li>\n'
    '              <li><a href="%s/admin/tool/policy/view.php?policyid=3" class="va-footer-nav-link">Compliance &amp; LGPD</a></li>\n'
    '              <li><a href="%s/admin/tool/policy/viewall.php" class="va-footer-nav-link">Todas as políticas</a></li>' % (SITE, SITE, SITE))
footer = footer.replace('href="https://linkedin.com"', 'href="https://www.linkedin.com/company/vertanalytics/"')
footer = footer.replace('href="https://youtube.com"', 'href="https://www.youtube.com/channel/UCKaLCLFtJ8BFtM89x5E1lZA"')
footer = footer.replace('href="https://instagram.com"', 'href="https://www.instagram.com/vertanalytics"')
# "Diferenciais e Recursos" do rodapé aponta para os números da própria página
blocks = {
    'bloco-9.html': wrap(FONT_LINK + '\n' + hero + '\n' + stats),
    'bloco-10.html': wrap(tracks),
    'bloco-11.html': wrap(courses + '\n' + cta),
    'bloco-12.html': wrap(footer),
}
os.makedirs(OUT, exist_ok=True)
for name, content in blocks.items():
    leftovers = re.findall(r'href="#(?!va-)[^"]*"', content)
    if leftovers:
        raise SystemExit('âncora sem destino em %s: %s' % (name, leftovers))
    open(os.path.join(OUT, name), 'w', encoding='utf-8').write(content + '\n')

# ------------------------------------------------------------------ CSS
def scope_selector(sel):
    sel = sel.strip()
    if not sel:
        return sel
    sel = re.sub(r'(?<![0-9])\.(-?[_a-zA-Z][\w-]*)', r'.va-\1', sel)
    parts = []
    for p in sel.split(','):
        p = p.strip()
        if p == ':root':
            parts.append('.va-home')
        elif p in ('*', '*::before', '*::after'):
            parts.append('.va-home ' + p)
        else:
            parts.append('.va-home ' + p)
    return ',\n'.join(parts)

def transform_css(css):
    css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
    out, i, n = [], 0, len(css)
    depth_stack = []  # 'media' | 'keyframes' | 'rule'
    buf = ''
    while i < n:
        c = css[i]
        if c == '{':
            head = buf.strip(); buf = ''
            if head.startswith('@media') or head.startswith('@supports'):
                out.append(head + ' {'); depth_stack.append('media')
            elif head.startswith('@keyframes') or head.startswith('@-webkit-keyframes'):
                name = head.split()[-1]
                out.append('@keyframes va-' + name + ' {'); depth_stack.append('keyframes')
            elif head.startswith('@font-face') or head.startswith('@import'):
                out.append(head + ' {'); depth_stack.append('skip')
            elif depth_stack and depth_stack[-1] == 'keyframes':
                out.append(head + ' {'); depth_stack.append('rule')
            else:
                out.append(scope_selector(head) + ' {'); depth_stack.append('rule')
        elif c == '}':
            body = buf; buf = ''
            if body.strip():
                body = body.replace("url('../assets/", "url('" + ASSET_BASE)
                body = re.sub(r'animation:\s*([A-Za-z]+)', lambda m: 'animation: va-' + m.group(1), body)
                out.append(body.strip())
            out.append('}')
            if depth_stack: depth_stack.pop()
        elif c == ';' and not depth_stack:
            buf = ''  # @import ... ; no nível raiz -> descartado (fonte vai por <link>)
        else:
            buf += c
        i += 1
    return '\n'.join(out)

tokens = open(os.path.join(SRC, 'css/tokens.css'), encoding='utf-8').read()
layout = open(os.path.join(SRC, 'css/layout.css'), encoding='utf-8').read()
components = open(os.path.join(SRC, 'css/components.css'), encoding='utf-8').read()

base = """
.va-home { font-family: var(--font-body); font-weight: var(--font-weight-light); line-height: 1.6;
  color: var(--color-text-primary); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
.va-home *, .va-home *::before, .va-home *::after { box-sizing: border-box; }
.va-home h1, .va-home h2, .va-home h3, .va-home h4, .va-home h5, .va-home h6 {
  font-family: var(--font-heading); font-weight: var(--font-weight-demibold); line-height: 1.2;
  color: inherit; letter-spacing: -0.02em; margin: 0; }
.va-home p { margin: 0; font-size: 1rem; color: inherit; }
.va-home a { color: inherit; text-decoration: none; transition: color var(--transition-fast); }
.va-home a:hover, .va-home a:focus { text-decoration: none; }
.va-home ul { margin: 0; padding: 0; }
.va-home img, .va-home svg { display: block; max-width: 100%; height: auto; }
.va-home ::selection { background-color: var(--color-green); color: var(--color-navy); }
.va-home section, .va-home footer { scroll-margin-top: 90px; }
"""

moodle_glue = """
/* --- Integração com o Moodle / RemUI: blocos da página inicial sem moldura, de ponta a ponta --- */
body.pagelayout-frontpage .block:has(.va-home),
body.pagelayout-frontpage .block:has(.va-home) .block-body-wrapper,
body.pagelayout-frontpage .block:has(.va-home) .block-content-area,
body.pagelayout-frontpage .block:has(.va-home) .block-content {
  margin: 0 !important; padding: 0 !important; border: 0 !important; border-radius: 0 !important;
  background: transparent !important; box-shadow: none !important; max-width: none !important; width: 100% !important;
}
body.pagelayout-frontpage .block:has(.va-home) .block-header-wrapper { display: none !important; }
body.pagelayout-frontpage #region-fullwidthtop-blocks,
body.pagelayout-frontpage #block-region-full-width-top,
body.pagelayout-frontpage #block-region-full-bottom,
body.pagelayout-frontpage [data-blockregion="full-bottom"] { padding: 0 !important; margin: 0 !important; max-width: none !important; }
body.pagelayout-frontpage .block-region:has(.va-home) { gap: 0 !important; }
/* largura útil das seções: 90% da tela, como nas demais páginas principais */
.va-home { --container-max: 90%; }
@media (max-width: 991.98px) { .va-home { --container-max: 100%; } }
"""

css = ('/* ===== Página inicial — identidade Vert Academy (gerado por homepage/build_home.py) ===== */\n'
       + transform_css(tokens) + '\n' + base + '\n' + transform_css(layout) + '\n' + transform_css(components)
       + '\n' + moodle_glue)
open(os.path.join(OUT, 'home.css'), 'w', encoding='utf-8').write(css)
print('ok:', {k: len(v) for k, v in blocks.items()}, 'css', len(css))
