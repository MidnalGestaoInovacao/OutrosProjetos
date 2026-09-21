# -*- coding: utf-8 -*-
"""Templates do tema em bloco (Twenty Twenty-Five) para evellynbrandao.com.br.
Cada função recebe os IDs dos blocos sincronizados de cabeçalho e rodapé."""

HTML_OPEN = "<!-- wp:html -->\n"
HTML_CLOSE = "\n<!-- /wp:html -->\n"

def html(s):
    return HTML_OPEN + s.strip("\n") + HTML_CLOSE

def block_ref(bid):
    return '<!-- wp:block {"ref":%d} /-->\n' % bid

def main_open():
    return ('<!-- wp:group {"tagName":"main","className":"eb-main","layout":{"type":"default"}} -->\n'
            '<main class="wp-block-group eb-main" id="eb-main">\n')

def main_close():
    return '</main>\n<!-- /wp:group -->\n'

# ---------- Query Loop (grade de matérias) ----------
def posts_grid(query_id, per_page=3, inherit=False, pagination=False, cols=3, extra_class=""):
    cls = ("eb-posts eb-posts--%d %s" % (cols, extra_class)).strip()
    q = ('{"queryId":%d,"query":{"perPage":%d,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":%s},"className":"%s"}'
         % (query_id, per_page, "true" if inherit else "false", cls))
    out = '<!-- wp:query %s -->\n<div class="wp-block-query %s">\n' % (q, cls)
    out += '<!-- wp:post-template {"layout":{"type":"default"}} -->\n'
    out += '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->\n'
    out += '<!-- wp:group {"className":"eb-postbody","layout":{"type":"default"}} -->\n<div class="wp-block-group eb-postbody">\n'
    out += '<!-- wp:post-terms {"term":"category"} /-->\n'
    out += '<!-- wp:post-title {"isLink":true,"level":3} /-->\n'
    out += '<!-- wp:post-excerpt {"moreText":"Ler matéria →","excerptLength":24} /-->\n'
    out += '<!-- wp:post-date {"format":"d/m/Y"} /-->\n'
    out += '</div>\n<!-- /wp:group -->\n'
    out += '<!-- /wp:post-template -->\n'
    if pagination:
        out += ('<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->\n'
                '<!-- wp:query-pagination-previous {"label":"Anteriores"} /-->\n'
                '<!-- wp:query-pagination-numbers /-->\n'
                '<!-- wp:query-pagination-next {"label":"Próximas"} /-->\n'
                '<!-- /wp:query-pagination -->\n')
    out += ('<!-- wp:query-no-results -->\n<!-- wp:paragraph -->\n<p>Nenhuma matéria encontrada por aqui ainda. Explore as <a href="/materias/">matérias publicadas</a>.</p>\n<!-- /wp:paragraph -->\n<!-- /wp:query-no-results -->\n')
    out += '</div>\n<!-- /wp:query -->\n'
    return out

def posts_minilist(query_id, per_page=4):
    q = '{"queryId":%d,"query":{"perPage":%d,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"className":"eb-aside-posts"}' % (query_id, per_page)
    return ('<!-- wp:query %s -->\n<div class="wp-block-query eb-aside-posts">\n'
            '<!-- wp:post-template {"layout":{"type":"default"}} -->\n'
            '<!-- wp:post-title {"isLink":true,"level":5} /-->\n'
            '<!-- wp:post-date {"format":"d/m/Y"} /-->\n'
            '<!-- /wp:post-template -->\n</div>\n<!-- /wp:query -->\n') % q

# ---------- Peças de layout ----------
def banner_page(crumb_html, with_excerpt=True, variant="page", extra=""):
    out = ('<!-- wp:group {"tagName":"section","className":"eb-banner","layout":{"type":"default"}} -->\n'
           '<section class="wp-block-group eb-banner">\n')
    out += html('<div class="eb-banner-bg"></div><div class="eb-3d" data-eb-3d="%s"></div>' % variant)
    out += '<!-- wp:group {"className":"eb-wrap","layout":{"type":"default"}} -->\n<div class="wp-block-group eb-wrap">\n'
    out += html('<div class="eb-crumbs">%s</div>' % crumb_html)
    out += '<!-- wp:post-title {"level":1} /-->\n'
    if with_excerpt:
        out += '<!-- wp:post-excerpt {"className":"eb-lead"} /-->\n'
    out += extra
    out += '</div>\n<!-- /wp:group -->\n</section>\n<!-- /wp:group -->\n'
    return out

ASIDE_AUTHOR = """
<div class="eb-card">
  <div class="eb-author">
    <img src="{{IMG:portrait}}" alt="Évellyn Brandão" width="64" height="64" loading="lazy">
    <div><b class="notranslate">Évellyn Brandão</b><span>CEO e Fundadora da BS Agro Capital · 10 anos de mercado financeiro</span></div>
  </div>
  <p style="margin:16px 0 0;color:var(--eb-mut);font-size:.92rem;line-height:1.6">Conectando produtores rurais e empresas do agro a bancos, cooperativas, fundos e diferentes fontes de capital.</p>
  <div class="eb-row" style="margin-top:16px"><a class="eb-btn eb-btn--p eb-btn--sm" href="/contato/">Falar com Évellyn</a><a class="eb-btn eb-btn--g eb-btn--sm" href="/sobre/">Trajetória</a></div>
</div>
"""

ASIDE_TOC = """
<div class="eb-card">
  <h4>Neste conteúdo</h4>
  <ul class="eb-toc" data-eb-toc></ul>
</div>
"""

ASIDE_CONTACT = """
<div class="eb-card eb-card--gold">
  <h4>Canais oficiais</h4>
  <ul class="eb-contact-list">
    <li><span class="eb-ic"><svg><use href="#i-mail"/></svg></span><div><small>Contato</small><a href="mailto:contato@evellynbrandao.com.br">contato@evellynbrandao.com.br</a></div></li>
    <li><span class="eb-ic"><svg><use href="#i-key"/></svg></span><div><small>DPO · Sanclé Albuquerque</small><a href="mailto:dpo@evellynbrandao.com.br">dpo@evellynbrandao.com.br</a></div></li>
    <li><span class="eb-ic"><svg><use href="#i-shield"/></svg></span><div><small>Ouvidoria &amp; Compliance</small><a href="mailto:ouvidoria@evellynbrandao.com.br">ouvidoria@evellynbrandao.com.br</a></div></li>
    <li><span class="eb-ic"><svg><use href="#i-whats"/></svg></span><div><small>WhatsApp</small><a href="#" data-eb-wa target="_blank" rel="noopener">Conversar agora</a></div></li>
  </ul>
</div>
"""

def aside(parts, with_posts=True, query_id=9):
    out = '<!-- wp:group {"tagName":"aside","className":"eb-aside","layout":{"type":"default"}} -->\n<aside class="wp-block-group eb-aside">\n'
    for p in parts:
        out += html(p)
    if with_posts:
        out += html('<div class="eb-card eb-card--posts"><h4>Matérias recentes</h4>')
        out += posts_minilist(query_id)
        out += html('<a class="eb-btn eb-btn--g eb-btn--sm" style="margin-top:14px" href="/materias/">Todas as matérias</a></div>')
    out += '</aside>\n<!-- /wp:group -->\n'
    return out

def content_grid_open(full=False):
    return ('<!-- wp:group {"className":"eb-content%s","layout":{"type":"default"}} -->\n<div class="wp-block-group eb-content%s">\n'
            '<!-- wp:group {"className":"eb-wrap","layout":{"type":"default"}} -->\n<div class="wp-block-group eb-wrap">\n') % ((" eb-content--full", " eb-content--full") if full else ("", ""))

def content_grid_close():
    return '</div>\n<!-- /wp:group -->\n</div>\n<!-- /wp:group -->\n'

def body_open(tag="div"):
    return '<!-- wp:group {"tagName":"%s","className":"eb-body","layout":{"type":"default"}} -->\n<%s class="wp-block-group eb-body">\n' % (tag, tag)

def body_close(tag="div"):
    return '</%s>\n<!-- /wp:group -->\n' % tag

# ---------- Templates ----------
def tpl_page(hid, fid):
    """Página editorial: banner 3D + conteúdo em duas colunas (corpo + aside). As tags de SEO vêm no início do conteúdo de cada página."""
    out = block_ref(hid) + main_open()
    out += banner_page('<a href="/">Início</a><span>›</span><span data-eb-crumb>Página</span>')
    out += content_grid_open()
    out += body_open() + '<!-- wp:post-content {"layout":{"type":"default"}} /-->\n' + body_close()
    out += aside([ASIDE_TOC, ASIDE_AUTHOR, ASIDE_CONTACT])
    out += content_grid_close()
    out += main_close() + block_ref(fid)
    return out

def tpl_page_landing(hid, fid):
    """Página 'landing' (slug page-no-title): banner 3D + conteúdo em largura total (as seções controlam o eb-wrap)."""
    out = block_ref(hid) + main_open()
    out += banner_page('<a href="/">Início</a><span>›</span><span data-eb-crumb>Página</span>')
    out += ('<!-- wp:group {"className":"eb-landing","layout":{"type":"default"}} -->\n<div class="wp-block-group eb-landing">\n'
            '<!-- wp:post-content {"layout":{"type":"default"}} /-->\n</div>\n<!-- /wp:group -->\n')
    out += main_close() + block_ref(fid)
    return out

def tpl_single(hid, fid):
    out = block_ref(hid) + main_open()
    out += ('<!-- wp:group {"tagName":"section","className":"eb-banner eb-banner--post","layout":{"type":"default"}} -->\n'
            '<section class="wp-block-group eb-banner eb-banner--post">\n')
    out += '<!-- wp:group {"className":"eb-banner-bg","layout":{"type":"default"}} -->\n<div class="wp-block-group eb-banner-bg">\n<!-- wp:post-featured-image /-->\n</div>\n<!-- /wp:group -->\n'
    out += html('<div class="eb-3d" data-eb-3d="post"></div>')
    out += '<!-- wp:group {"className":"eb-wrap","layout":{"type":"default"}} -->\n<div class="wp-block-group eb-wrap">\n'
    out += html('<div class="eb-crumbs"><a href="/">Início</a><span>›</span><a href="/materias/">Matérias</a><span>›</span>')
    out += '<!-- wp:post-terms {"term":"category"} /-->\n'
    out += html('</div>')
    out += '<!-- wp:post-title {"level":1} /-->\n'
    out += html('<div class="eb-meta">')
    out += '<!-- wp:post-date {"format":"d/m/Y"} /-->\n'
    out += html('<span>·</span><span>Por <strong class="notranslate">Évellyn Brandão</strong></span><span>·</span><span data-eb-readtime>Leitura de ~5 min</span></div>')
    out += '</div>\n<!-- /wp:group -->\n</section>\n<!-- /wp:group -->\n'
    out += content_grid_open()
    out += body_open("article") + '<!-- wp:post-content {"layout":{"type":"default"}} /-->\n'
    out += html('<div class="eb-tags">')
    out += '<!-- wp:post-terms {"term":"post_tag","prefix":"Tags"} /-->\n'
    out += html('</div>\n<div class="eb-post-cta"><div><b>Como Évellyn ajuda</b><p>Fale com a BS Agro Capital e transforme este tema em plano de ação para a sua operação.</p></div><a class="eb-btn eb-btn--p" href="/contato/">Falar com Évellyn</a></div>')
    out += body_close("article")
    out += aside([ASIDE_AUTHOR, ASIDE_TOC, ASIDE_CONTACT])
    out += content_grid_close()
    out += html('<section class="eb-sec eb-sec--alt eb-sec--line"><div class="eb-wrap"><p class="eb-kicker">Continue lendo</p><h2 class="eb-h2">Mais matérias para o seu agro</h2>')
    out += posts_grid(query_id=7, per_page=3)
    out += html('<div class="eb-mt40"><a class="eb-btn eb-btn--g" href="/materias/">Ver todas as matérias</a></div></div></section>')
    out += main_close() + block_ref(fid)
    return out

def tpl_listing(hid, fid, kind="archive"):
    out = block_ref(hid) + main_open()
    out += ('<!-- wp:group {"tagName":"section","className":"eb-banner","layout":{"type":"default"}} -->\n<section class="wp-block-group eb-banner">\n')
    out += html('<div class="eb-banner-bg"></div><div class="eb-3d" data-eb-3d="page"></div>')
    out += '<!-- wp:group {"className":"eb-wrap","layout":{"type":"default"}} -->\n<div class="wp-block-group eb-wrap">\n'
    out += html('<div class="eb-crumbs"><a href="/">Início</a><span>›</span><a href="/materias/">Matérias</a></div>')
    if kind == "search":
        out += '<!-- wp:query-title {"type":"search","level":1} /-->\n'
        out += html('<div class="eb-search eb-mt24">')
        out += '<!-- wp:search {"label":"Pesquisar","showLabel":false,"placeholder":"O que você procura?","buttonText":"Buscar"} /-->\n'
        out += html('</div>')
    elif kind == "archive":
        out += '<!-- wp:query-title {"type":"archive","level":1,"showPrefix":false} /-->\n'
        out += '<!-- wp:term-description {"className":"eb-lead"} /-->\n'
    else:
        out += html('<h1 class="eb-h1">Matérias</h1><p class="eb-lead">Conteúdo relevante sobre crédito, capital e gestão no agronegócio.</p>')
    out += '</div>\n<!-- /wp:group -->\n</section>\n<!-- /wp:group -->\n'
    out += html('<section class="eb-sec"><div class="eb-wrap">')
    out += posts_grid(query_id=3, per_page=9, inherit=True, pagination=True)
    out += html('</div></section>')
    out += main_close() + block_ref(fid)
    return out

def tpl_404(hid, fid):
    out = block_ref(hid) + main_open()
    out += html("""
<section class="eb-banner" style="min-height:70vh"><div class="eb-banner-bg"></div><div class="eb-3d" data-eb-3d="page"></div>
<div class="eb-wrap" style="padding:120px 0 80px">
  <div class="eb-crumbs"><a href="/">Início</a><span>›</span><span>Erro 404</span></div>
  <h1 class="eb-h1">Esta página não existe <span class="eb-gtx">— mas o seu próximo passo, sim.</span></h1>
  <p class="eb-lead">O endereço pode ter mudado ou o link estar incorreto. Escolha um caminho abaixo ou fale diretamente com Évellyn.</p>
  <div class="eb-row eb-mt24"><a class="eb-btn eb-btn--p" href="/">Voltar ao início</a><a class="eb-btn eb-btn--g" href="/solucoes/">Conhecer as soluções</a><a class="eb-btn eb-btn--g" href="/materias/">Ler as matérias</a></div>
</div></section>
<section class="eb-sec eb-sec--alt"><div class="eb-wrap"><p class="eb-kicker">Enquanto isso</p><h2 class="eb-h2">Matérias recentes</h2>""")
    out += posts_grid(query_id=5, per_page=3)
    out += html('</div></section>')
    out += main_close() + block_ref(fid)
    return out

def tpl_home(hid, fid, home_html_parts):
    """home_html_parts: (antes_da_grade, depois_da_grade)"""
    before, after = home_html_parts
    out = block_ref(hid) + main_open()
    out += html(before)
    out += posts_grid(query_id=1, per_page=3)
    out += html(after)
    out += main_close() + block_ref(fid)
    return out
