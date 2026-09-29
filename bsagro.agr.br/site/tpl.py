# -*- coding: utf-8 -*-
"""Templates do tema em bloco (Twenty Twenty-Five) para bsagro.agr.br.
Cada função recebe os IDs dos blocos sincronizados de cabeçalho e rodapé.
Placeholders {{U:slug}}, {{HOME}}, {{IMG:chave}} e {{EMAIL:canal}} são resolvidos no deploy."""

def html(s):
    return "<!-- wp:html -->\n" + s.strip("\n") + "\n<!-- /wp:html -->\n"

def block_ref(bid):
    return '<!-- wp:block {"ref":%d} /-->\n' % bid

def main_open(page_key=""):
    attr = ' data-bs-page="%s"' % page_key if page_key else ""
    return ('<!-- wp:group {"tagName":"main","anchor":"conteudo-principal","className":"bs-main","layout":{"type":"default"}} -->\n'
            '<main class="wp-block-group bs-main" id="conteudo-principal"%s>\n' % attr)

def main_close():
    return "</main>\n<!-- /wp:group -->\n"

def group(cls, inner, tag="div"):
    return ('<!-- wp:group {"tagName":"%s","className":"%s","layout":{"type":"default"}} -->\n<%s class="wp-block-group %s">\n%s</%s>\n<!-- /wp:group -->\n'
            % (tag, cls, tag, cls, inner, tag))

# ---------- Query Loop (grade de matérias) ----------
def posts_grid(query_id, per_page=3, inherit=False, pagination=False, cols=3, exclude_current=False):
    cls = "bs-posts bs-posts--%d" % cols
    q = ('{"queryId":%d,"query":{"perPage":%d,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":%s},"className":"%s"}'
         % (query_id, per_page, "true" if inherit else "false", cls))
    out = '<!-- wp:query %s -->\n<div class="wp-block-query %s">\n' % (q, cls)
    out += '<!-- wp:post-template {"layout":{"type":"default"}} -->\n'
    out += '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9","sizeSlug":"medium_large"} /-->\n'
    out += '<!-- wp:group {"className":"bs-postbody","layout":{"type":"default"}} -->\n<div class="wp-block-group bs-postbody">\n'
    out += '<!-- wp:post-terms {"term":"category"} /-->\n'
    out += '<!-- wp:post-title {"isLink":true,"level":3} /-->\n'
    out += '<!-- wp:post-excerpt {"moreText":"Ler matéria →","excerptLength":26} /-->\n'
    out += '<!-- wp:post-date {"format":"d/m/Y"} /-->\n'
    out += "</div>\n<!-- /wp:group -->\n"
    out += "<!-- /wp:post-template -->\n"
    if pagination:
        out += ('<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->\n'
                '<!-- wp:query-pagination-previous {"label":"Anteriores"} /-->\n'
                "<!-- wp:query-pagination-numbers /-->\n"
                '<!-- wp:query-pagination-next {"label":"Próximas"} /-->\n'
                "<!-- /wp:query-pagination -->\n")
    out += ('<!-- wp:query-no-results -->\n<!-- wp:paragraph -->\n<p>Nenhuma matéria encontrada por aqui. '
            'Veja as <a href="{{U:materias}}">matérias publicadas</a>.</p>\n<!-- /wp:paragraph -->\n<!-- /wp:query-no-results -->\n')
    out += "</div>\n<!-- /wp:query -->\n"
    return out

# ---------- Banner das páginas internas ----------
def banner(crumbs_html, title_block, extra_html="", image_key="vista-lavoura-fileiras-agronegocio", featured=False, cls="bs-banner", aside_html=""):
    out = ('<!-- wp:group {"tagName":"section","className":"%s","layout":{"type":"default"}} -->\n'
           '<section class="wp-block-group %s">\n' % (cls, cls))
    if featured:
        out += '<!-- wp:post-featured-image {"className":"bs-banner__img"} /-->\n'
    else:
        out += html('<div class="bs-banner__img"><img src="{{IMG:%s}}" alt="" aria-hidden="true" decoding="async" fetchpriority="high"></div>' % image_key)
    out += '<!-- wp:group {"className":"bs-banner__in","layout":{"type":"default"}} -->\n<div class="wp-block-group bs-banner__in">\n'
    if aside_html:
        out += '<!-- wp:group {"className":"bs-banner__txt","layout":{"type":"default"}} -->\n<div class="wp-block-group bs-banner__txt">\n'
    out += crumbs_html
    out += title_block
    out += extra_html
    if aside_html:
        out += "</div>\n<!-- /wp:group -->\n" + aside_html
    out += "</div>\n<!-- /wp:group -->\n</section>\n<!-- /wp:group -->\n"
    return out

CRUMB_PAGE = html('<nav class="bs-crumbs" aria-label="Trilha de navegação"><a href="{{HOME}}">Início</a><span class="sep" aria-hidden="true">/</span><span data-bs-crumb aria-current="page">Página</span></nav>')

ASIDE_TOC = """<div class="bs-card"><h2>Neste conteúdo</h2><ul class="bs-toc" data-bs-toc></ul></div>"""

ASIDE_CLIENTE = """<div class="bs-card bs-card--dark">
  <h2>Área do Cliente</h2>
  <p>Solicite a análise on-line, envie documentos com segurança e acompanhe cada etapa da sua operação.</p>
  <a class="bs-btn bs-btn--gold" style="margin-top:1rem;width:100%" href="{{U:area-do-cliente}}" data-ebcr-client-area data-keep-label><svg width="18" height="18" aria-hidden="true"><use href="#i-lock"/></svg>Acessar a Área do Cliente</a>
</div>"""

ASIDE_CANAIS = """<div class="bs-card">
  <h2>Canais oficiais</h2>
  <ul class="bs-contact-list">
    <li><svg aria-hidden="true"><use href="#i-mail"/></svg><div><small>Contato central</small><a href="mailto:{{EMAIL:contato}}">{{EMAIL:contato}}</a></div></li>
    <li><svg aria-hidden="true"><use href="#i-key"/></svg><div><small>Privacidade e LGPD · DPO {{DPO}}</small><a href="mailto:{{EMAIL:dpo}}">{{EMAIL:dpo}}</a></div></li>
    <li><svg aria-hidden="true"><use href="#i-shield"/></svg><div><small>Ouvidoria e compliance</small><a href="mailto:{{EMAIL:ouvidoria}}">{{EMAIL:ouvidoria}}</a></div></li>
    <li><svg aria-hidden="true"><use href="#i-whats"/></svg><div><small>WhatsApp</small><a href="#" data-bs-wa target="_blank" rel="noopener noreferrer">(62) 99689-2488</a></div></li>
  </ul>
</div>"""

ASIDE_ANALISE = """<div class="bs-card">
  <h2>Próximo passo</h2>
  <p>Transforme este conteúdo em um plano para a sua operação: faça o pré-diagnóstico gratuito ou fale com um especialista.</p>
  <a class="bs-btn bs-btn--primary" style="margin-top:1rem;width:100%" href="{{HOME}}#pre-diagnostico">Fazer o pré-diagnóstico</a>
  <a class="bs-btn bs-btn--ghost" style="margin-top:.5rem;width:100%" href="#" data-bs-wa target="_blank" rel="noopener noreferrer">Falar pelo WhatsApp</a>
</div>"""

def aside(parts):
    inner = html('<div class="bs-aside__sticky">' + "\n".join(parts) + "</div>")
    return group("bs-aside", inner, "aside")

# ---------- Templates ----------
def tpl_page(hid, fid):
    """Página de conteúdo (políticas, textos): banner + corpo + coluna lateral."""
    out = block_ref(hid) + main_open()
    out += banner(CRUMB_PAGE, '<!-- wp:post-title {"level":1} /-->\n<!-- wp:post-excerpt {"className":"bs-lead"} /-->\n')
    body = group("bs-prose", '<!-- wp:post-content {"layout":{"type":"default"}} /-->\n', "article")
    out += group("bs-layout", body + aside([ASIDE_TOC, ASIDE_CLIENTE, ASIDE_CANAIS]))
    out += main_close() + block_ref(fid)
    return out

def tpl_page_landing(hid, fid):
    """Página 'landing' (slug page-no-title): banner + conteúdo em largura total (as seções controlam a largura)."""
    out = block_ref(hid) + main_open()
    out += banner(CRUMB_PAGE, '<!-- wp:post-title {"level":1} /-->\n<!-- wp:post-excerpt {"className":"bs-lead"} /-->\n')
    out += group("bs-layout bs-layout--full", '<!-- wp:post-content {"layout":{"type":"default"}} /-->\n')
    out += main_close() + block_ref(fid)
    return out

def tpl_single(hid, fid):
    out = block_ref(hid) + main_open("materias")
    crumbs = (html('<nav class="bs-crumbs" aria-label="Trilha de navegação"><a href="{{HOME}}">Início</a><span class="sep" aria-hidden="true">/</span><a href="{{U:materias}}">Matérias</a><span class="sep" aria-hidden="true">/</span>')
              + '<!-- wp:post-terms {"term":"category"} /-->\n' + html("</nav>"))
    meta = (html('<div class="bs-meta"><span><svg aria-hidden="true"><use href="#i-clock"/></svg>')
            + '<!-- wp:post-date {"format":"d/m/Y"} /-->\n'
            + html('</span><span><svg aria-hidden="true"><use href="#i-user"/></svg>Equipe BS Agro Capital</span><span><svg aria-hidden="true"><use href="#i-book"/></svg><span data-bs-readtime>Leitura de ~6 min</span></span></div>'))
    out += banner(crumbs, '<!-- wp:post-title {"level":1} /-->\n', meta, featured=True, cls="bs-banner bs-banner--post",
                  aside_html='<!-- wp:post-featured-image {"className":"bs-banner__cover","aspectRatio":"16/9"} /-->\n')
    body = '<!-- wp:post-content {"layout":{"type":"default"}} /-->\n'
    body += html('<div class="bs-tags">') + '<!-- wp:post-terms {"term":"post_tag","prefix":"Temas: "} /-->\n' + html("</div>")
    body += html('<div class="bs-post-cta"><div><b>Quer aplicar isso na sua operação?</b><p>Conte com a BS Agro Capital do diagnóstico à execução. Comece pela Área do Cliente ou fale com um especialista.</p></div>'
                 '<a class="bs-btn bs-btn--gold" href="{{U:area-do-cliente}}" data-ebcr-client-area data-keep-label>Solicitar análise</a></div>')
    out += group("bs-layout", group("bs-prose", body, "article") + aside([ASIDE_TOC, ASIDE_ANALISE, ASIDE_CLIENTE]))
    out += html('<section class="bs-sec bg-mesh-light"><div class="bs-wrap"><p class="bs-eyebrow">Continue lendo</p><h2 class="bs-h2 mt-3 mb-8">Mais matérias para o seu agro</h2>')
    out += posts_grid(query_id=7, per_page=3)
    out += html('<div class="mt-10"><a class="cta-btn cta-btn--dark" href="{{U:materias}}"><span class="cta-btn__text">Ver todas as matérias</span></a></div></div></section>')
    out += main_close() + block_ref(fid)
    return out

def tpl_listing(hid, fid, kind="archive"):
    out = block_ref(hid) + main_open("materias")
    crumbs = html('<nav class="bs-crumbs" aria-label="Trilha de navegação"><a href="{{HOME}}">Início</a><span class="sep" aria-hidden="true">/</span><a href="{{U:materias}}">Matérias</a></nav>')
    if kind == "search":
        title = '<!-- wp:query-title {"type":"search","level":1} /-->\n'
    elif kind == "archive":
        title = '<!-- wp:query-title {"type":"archive","level":1,"showPrefix":false} /-->\n<!-- wp:term-description /-->\n'
    else:
        title = html('<h1>Matérias</h1><p class="bs-lead">Crédito, garantias e gestão financeira no agronegócio, explicados com clareza.</p>')
    out += banner(crumbs, title)
    out += html('<section class="bs-sec"><div class="bs-wrap">')
    out += posts_grid(query_id=3, per_page=9, inherit=True, pagination=True)
    out += html("</div></section>")
    out += main_close() + block_ref(fid)
    return out

def tpl_404(hid, fid):
    out = block_ref(hid) + main_open()
    out += html("""<section class="bs-banner" style="min-height:70vh"><div class="bs-banner__img"><img src="{{IMG:estrada-propriedade-rural-recuperacao-judicial}}" alt="" aria-hidden="true"></div>
<div class="bs-banner__in">
  <nav class="bs-crumbs" aria-label="Trilha de navegação"><a href="{{HOME}}">Início</a><span class="sep" aria-hidden="true">/</span><span>Página não encontrada</span></nav>
  <h1>Esta página não existe — mas o seu próximo passo, sim.</h1>
  <p class="bs-lead">O endereço pode ter mudado ou o link estar incorreto. Escolha um caminho abaixo.</p>
  <div class="mt-8 flex flex-wrap gap-3"><a class="cta-btn" href="{{HOME}}"><span class="cta-btn__text">Voltar ao início</span></a><a class="cta-btn cta-btn--outline" href="{{U:materias}}"><span class="cta-btn__text">Ler as matérias</span></a><a class="cta-btn cta-btn--outline" href="{{U:contato}}"><span class="cta-btn__text">Canal de contato</span></a></div>
</div></section>
<section class="bs-sec"><div class="bs-wrap"><p class="bs-eyebrow">Enquanto isso</p><h2 class="bs-h2 mt-3 mb-8">Matérias recentes</h2>""")
    out += posts_grid(query_id=5, per_page=3)
    out += html("</div></section>")
    out += main_close() + block_ref(fid)
    return out

def tpl_home(hid, fid, parts):
    """parts: (antes_da_grade, depois_da_grade) — a grade de matérias entra no meio."""
    before, after = parts
    out = block_ref(hid)
    out += ('<!-- wp:group {"tagName":"main","anchor":"conteudo-principal","className":"bs-main flex-1","layout":{"type":"default"}} -->\n'
            '<main class="wp-block-group bs-main flex-1" id="conteudo-principal" data-bs-page="inicio">\n')
    out += html(before)
    out += posts_grid(query_id=1, per_page=3)
    out += html(after)
    out += main_close() + block_ref(fid)
    return out
