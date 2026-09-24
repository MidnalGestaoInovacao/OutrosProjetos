#!/usr/bin/env python3
"""Publica o site Télix no WordPress via Easy MCP AI (JSON-RPC).

Uso: WPMCP_URL=https://dominio/wp-json/easy-mcp-ai/v1/mcp/<chave> python3 tools/publish.py [etapas...]
Etapas: media styles parts templates pages settings menu cleanup all
O estado (IDs de mídia, blocos e páginas) fica em config/state.json — reexecutar atualiza em vez de duplicar.
"""
import base64, json, os, re, sys, time, threading
from concurrent.futures import ThreadPoolExecutor
import requests

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, 'tools'))
import build  # noqa: E402

URL = os.environ.get('WPMCP_URL')
if not URL:
    sys.exit('Defina WPMCP_URL (endpoint MCP com a chave).')
STATE_P = os.path.join(ROOT, 'config', 'state.json')
STATE = json.load(open(STATE_P, encoding='utf-8')) if os.path.exists(STATE_P) else {'media': {}, 'blocks': {}, 'pages': {}}
THEME = 'twentytwentyfive'


_slock = threading.Lock()


def save_state():
    with _slock:
        json.dump(STATE, open(STATE_P, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)


_tls = threading.local()
_lock = threading.Lock()
_id = [int(time.time()) % 100000]
WORKERS = int(os.environ.get('TX_WORKERS', '6'))


def _sess():
    if not getattr(_tls, 's', None):
        _tls.s = requests.Session()
    return _tls.s


def _new_session():
    try:
        _tls.s.close()
    except Exception:
        pass
    _tls.s = requests.Session()


def call(name, args, retries=60):
    """Chama uma ferramenta MCP; repete em falhas de rede (o egress é instável)."""
    with _lock:
        _id[0] += 1
        rid = _id[0]
    body = json.dumps({'jsonrpc': '2.0', 'id': rid, 'method': 'tools/call', 'params': {'name': name, 'arguments': args}})
    last = None
    for i in range(retries):
        try:
            r = _sess().post(URL, data=body, timeout=(20, 240), headers={'Content-Type': 'application/json', 'Accept': 'application/json, text/event-stream', 'User-Agent': 'Mozilla/5.0 telix-publisher/1.0'})
            if r.status_code in (502, 503, 504, 520, 522, 524, 429):
                last = f'http {r.status_code}'
                time.sleep(3 + i)
                continue
            t = r.text.strip()
            if t.startswith('event:') or t.startswith('data:'):
                t = [ln[5:].strip() for ln in t.splitlines() if ln.startswith('data:')][-1]
            j = json.loads(t)
            if 'error' in j:
                raise RuntimeError(f'{name}: {j["error"]}')
            res = j['result']
            txt = '\n'.join(c.get('text', '') for c in res.get('content', []) if c.get('type') == 'text')
            if res.get('isError'):
                raise RuntimeError(f'{name}: {txt[:600]}')
            try:
                return json.loads(txt)
            except Exception:
                return txt
        except RuntimeError:
            raise
        except Exception as e:  # rede
            last = repr(e)[:200]
            _new_session()
            time.sleep(min(1 + i, 6))
    raise RuntimeError(f'{name}: falhou após {retries} tentativas: {last}')


def rel(url):
    """URL de mídia -> caminho relativo à raiz (sobrevive a troca de domínio)."""
    return re.sub(r'^https?://[^/]+', '', url)


# ---------------------------------------------------------------------------
def step_media():
    mdir = os.path.join(ROOT, 'dist', 'media')
    manifest = json.load(open(os.path.join(mdir, 'manifest.json'), encoding='utf-8'))
    brand = {
        'IMG_OG': ('dist/brand/telix-og.jpg', 'telix-compartilhamento.jpg', 'Télix Comunicação e Relacionamento — central de atendimento especializada em saúde suplementar'),
        'IMG_ICON': ('dist/brand/telix-icone.png', 'telix-icone.png', 'Ícone Télix'),
    }
    cl_alt = {c['logo']: f'Logotipo {c["name"]}' for c in build.CFG.get('clients', [])}
    jobs = [(tok, os.path.join(mdir, v['file']), v['file'], v['alt'] or cl_alt.get(tok, 'Logotipo de cliente')) for tok, v in manifest.items()]
    jobs += [(tok, os.path.join(ROOT, p), fn, alt) for tok, (p, fn, alt) in brand.items()]
    def up(job):
        tok, path, fn, alt = job
        b64 = base64.b64encode(open(path, 'rb').read()).decode()
        r = call('wp_upload_media', {'filename': fn, 'content_base64': b64, 'title': alt[:90], 'alt_text': alt})
        with _slock:
            STATE['media'][tok] = {'id': r['id'], 'url': r['source_url'], 'path': rel(r['source_url'])}
        save_state()
        print('mídia', tok, r['id'], rel(r['source_url']), flush=True)
    have = {}
    for m in list_all('wp_list_media', 'media'):
        base = re.sub(r'(-\d+)?(-scaled)?\.[a-z0-9]+$', '', os.path.basename(m.get('source_url', '')))
        have.setdefault(base, m)
    for tok, path, fn, alt in jobs:
        base = re.sub(r'\.[a-z0-9]+$', '', fn)
        if tok not in STATE['media'] and base in have:
            m = have[base]
            STATE['media'][tok] = {'id': m['id'], 'url': m['source_url'], 'path': rel(m['source_url'])}
    save_state()
    todo = [j for j in jobs if j[0] not in STATE['media']]
    with ThreadPoolExecutor(WORKERS) as ex:
        list(ex.map(up, todo))
    apply_media_tokens()


def apply_media_tokens():
    """Grava no config/site.json os caminhos das mídias no WordPress."""
    p = os.path.join(ROOT, 'config', 'site.json')
    cfg = json.load(open(p, encoding='utf-8'))
    for tok, m in STATE['media'].items():
        cfg['tokens'][tok] = m['path']
    if 'IMG_ICON' in STATE['media']:
        cfg['favicon_url'] = STATE['media']['IMG_ICON']['path']
    if 'IMG_LOGO_PNG' in STATE['media']:
        cfg['logo_url'] = STATE['media']['IMG_LOGO_PNG']['path']
    if 'IMG_OG' in STATE['media']:
        cfg['og_image_url'] = STATE['media']['IMG_OG']['path']
    json.dump(cfg, open(p, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    build.CFG.clear(); build.CFG.update(cfg)


def step_styles():
    css = open(os.path.join(ROOT, 'dist', 'global.css'), encoding='utf-8').read()
    palette = [
        {'slug': 'tx-grafite', 'name': 'Grafite Télix', 'color': '#56534b'},
        {'slug': 'tx-grafite-escuro', 'name': 'Grafite escuro', 'color': '#2a2824'},
        {'slug': 'tx-dourado', 'name': 'Dourado Télix', 'color': '#e9b947'},
        {'slug': 'tx-dourado-escuro', 'name': 'Dourado escuro', 'color': '#8a6512'},
        {'slug': 'tx-areia', 'name': 'Areia', 'color': '#f8f5ee'},
        {'slug': 'tx-branco', 'name': 'Branco', 'color': '#ffffff'},
    ]
    r = call('wp_update_global_styles', {'styles': {'css': css, 'color': {'background': '#ffffff', 'text': '#24221e'}},
                                         'settings': {'color': {'palette': {'custom': palette}}}})
    print('estilos globais ok', r.get('id') if isinstance(r, dict) else r)


def step_parts():
    build.build_parts()
    for key, title in (('header', 'Télix — Cabeçalho e megamenu'), ('footer', 'Télix — Rodapé, cookies, acessibilidade e contatos')):
        content = open(os.path.join(ROOT, 'dist', f'{key}.html'), encoding='utf-8').read()
        bid = STATE['blocks'].get(key)
        if not bid:
            for b in list_all('wp_list_blocks', 'blocks', {'status': 'publish'}):
                if b.get('title') == title:
                    bid = STATE['blocks'][key] = b['id']
        if bid:
            call('wp_update_block', {'block_id': bid, 'content': content, 'title': title, 'status': 'publish'})
        else:
            r = call('wp_create_block', {'title': title, 'content': content, 'status': 'publish'})
            STATE['blocks'][key] = r['id']
            save_state()
        print('bloco', key, STATE['blocks'][key])


def shell(main_inner):
    h, f = STATE['blocks']['header'], STATE['blocks']['footer']
    return (f'<!-- wp:group {{"tagName":"header","className":"tx-site-header","layout":{{"type":"default"}}}} -->\n'
            f'<header class="wp-block-group tx-site-header"><!-- wp:block {{"ref":{h}}} /--></header>\n<!-- /wp:group -->\n\n'
            f'{main_inner}\n\n'
            f'<!-- wp:group {{"tagName":"footer","className":"tx-site-footer","layout":{{"type":"default"}}}} -->\n'
            f'<footer class="wp-block-group tx-site-footer"><!-- wp:block {{"ref":{f}}} /--></footer>\n<!-- /wp:group -->')


def main_group(inner, cls='tx-main'):
    return (f'<!-- wp:group {{"tagName":"main","anchor":"conteudo","className":"{cls}","layout":{{"type":"default"}}}} -->\n'
            f'<main id="conteudo" class="wp-block-group {cls}">{inner}</main>\n<!-- /wp:group -->')


def html_block(s):
    return '<!-- wp:html -->\n' + s + '\n<!-- /wp:html -->'


def step_templates():
    post_content = '<!-- wp:post-content {"layout":{"type":"default"}} /-->'
    tpl = {}
    tpl['page'] = shell(main_group(post_content))
    tpl['page-no-title'] = tpl['page']
    # post individual (blog/notícias): título + conteúdo em container
    tpl['single'] = shell(main_group(html_block('<div class="tx"><section class="tx-pagehero"><div class="tx-container"><p class="tx-eyebrow">Conteúdo Télix</p></div></section></div>') +
        '<!-- wp:group {"className":"tx-legacy","layout":{"type":"constrained","contentSize":"820px"}} --><div class="wp-block-group tx-legacy"><!-- wp:post-title {"level":1} /--><!-- wp:post-date /--><!-- wp:post-content {"layout":{"type":"constrained"}} /--></div><!-- /wp:group -->'))
    listing = ('<!-- wp:group {"className":"tx-legacy","layout":{"type":"constrained","contentSize":"980px"}} --><div class="wp-block-group tx-legacy">'
               '<!-- wp:query-title {"type":"archive"} /--><!-- wp:query {"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true}} --><div class="wp-block-query">'
               '<!-- wp:post-template --><!-- wp:post-title {"isLink":true} /--><!-- wp:post-excerpt /--><!-- /wp:post-template -->'
               '<!-- wp:query-pagination --><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--><!-- /wp:query-pagination -->'
               '<!-- wp:query-no-results --><!-- wp:paragraph --><p>Nenhum conteúdo encontrado.</p><!-- /wp:paragraph --><!-- /wp:query-no-results --></div><!-- /wp:query --></div><!-- /wp:group -->')
    tpl['archive'] = shell(main_group(listing))
    tpl['index'] = tpl['archive']
    if STATE['blocks'].get('home'):
        # página inicial: o modelo "home" exibe o bloco sincronizado da home (WordPress em modo "últimos posts")
        tpl['home'] = shell(main_group('<!-- wp:block {"ref":%d} /-->' % STATE['blocks']['home']))
    else:
        tpl['home'] = tpl['archive']
    search = ('<div class="tx"><section class="tx-pagehero"><div class="tx-container"><nav aria-label="Trilha de navegação"><ol class="tx-breadcrumb"><li><a href="/">Início</a></li><li aria-current="page">Busca</li></ol></nav>'
              '<p class="tx-eyebrow">Busca no site</p><h1 class="tx-h1">Resultados da pesquisa</h1></div></section></div>')
    tpl['search'] = shell(main_group(html_block(search) +
        '<!-- wp:group {"className":"tx-legacy","layout":{"type":"constrained","contentSize":"980px"}} --><div class="wp-block-group tx-legacy"><!-- wp:search {"label":"Pesquisar","buttonText":"Pesquisar"} /-->'
        '<!-- wp:query {"query":{"perPage":10,"pages":0,"offset":0,"postType":"page","order":"desc","orderBy":"relevance","inherit":true}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"isLink":true,"level":2} /--><!-- wp:post-excerpt {"excerptLength":30} /--><!-- /wp:post-template -->'
        '<!-- wp:query-no-results --><!-- wp:paragraph --><p>Nada encontrado. Tente outras palavras ou navegue pelo <a href="/mapa-do-site/">mapa do site</a>.</p><!-- /wp:paragraph --><!-- /wp:query-no-results --></div><!-- /wp:query --></div><!-- /wp:group -->'))
    notfound = open(os.path.join(ROOT, 'src', 'templates', '404.html'), encoding='utf-8').read()
    tpl['404'] = shell(main_group(html_block(build.fill(re.sub(r'\[\[i:([a-z0-9-]+)\]\]', lambda m: build.I(m.group(1)), notfound)))))
    for slug, content in tpl.items():
        call('wp_update_template', {'template_id': f'{THEME}//{slug}', 'content': content})
        print('modelo', slug, 'ok')


def list_all(tool, key, extra=None):
    out, page = [], 1
    while True:
        args = {'per_page': 100, 'page': page}
        args.update(extra or {})
        r = call(tool, args)
        out += r.get(key, [])
        if page >= int(r.get('total_pages') or 1):
            return out
        page += 1


def adopt_existing_pages(pages):
    """Reaproveita páginas já criadas (ex.: resposta perdida na rede) e remove duplicatas slug-2, slug-3..."""
    existing = list_all('wp_list_pages', 'pages', {'status': 'any'})
    ours = {m['slug'] for m in pages.values()}
    by_slug = {}
    for p in existing:
        by_slug.setdefault(p['slug'], []).append(p['id'])
    for slug in ours:
        if slug not in STATE['pages'] and by_slug.get(slug):
            STATE['pages'][slug] = min(by_slug[slug])
    for p in existing:
        m = re.match(r'^(.+)-(\d+)$', p['slug'])
        if m and m.group(1) in ours and p['id'] != STATE['pages'].get(p['slug']):
            try:
                call('wp_delete_page', {'page_id': p['id'], 'force': True}); print('duplicata removida', p['slug'], p['id'])
            except Exception as e:
                print('falha ao remover duplicata', p['id'], e)
    save_state()


def step_pages(only=None):
    pages = build.build_pages()
    adopt_existing_pages(pages)
    # ordena por profundidade (pais primeiro)
    items = sorted(pages.items(), key=lambda kv: (1 if kv[1].get('parent') else 0, kv[1].get('menu_order', 0)))
    level0 = [kv for kv in items if not kv[1].get('parent') and (not only or kv[1]['slug'] in only)]
    level1 = [kv for kv in items if kv[1].get('parent') and (not only or kv[1]['slug'] in only)]
    with ThreadPoolExecutor(WORKERS) as ex:
        list(ex.map(lambda kv: _push_page(*kv), level0))
    with ThreadPoolExecutor(WORKERS) as ex:
        list(ex.map(lambda kv: _push_page(*kv), level1))
    adopt_existing_pages(pages)


def _push_page(fn, meta):
    if True:
        slug = meta['slug']
        content = open(os.path.join(ROOT, 'dist', 'pages', fn), encoding='utf-8').read()
        if meta.get('front_page'):
            # conteúdo da home como bloco sincronizado: usado pelo modelo "home" e pela página Início
            bid = STATE['blocks'].get('home')
            if bid:
                call('wp_update_block', {'block_id': bid, 'content': content, 'title': 'Télix — Página inicial', 'status': 'publish'})
            else:
                r0 = call('wp_create_block', {'title': 'Télix — Página inicial', 'content': content, 'status': 'publish'})
                with _slock:
                    STATE['blocks']['home'] = r0['id']
                save_state()
            content = '<!-- wp:block {"ref":%d} /-->' % STATE['blocks']['home']
        parent = STATE['pages'].get(meta.get('parent') or '', 0) if meta.get('parent') else 0
        args = {'title': meta['title'], 'content': content, 'status': 'publish', 'slug': slug, 'parent': parent,
                'menu_order': meta.get('menu_order', 0), 'comment_status': 'open' if meta.get('comments') else 'closed', 'ping_status': 'closed',
                'excerpt': meta.get('seo', {}).get('description', '')}
        if meta.get('seo', {}).get('image_id_token') and meta['seo']['image_id_token'] in STATE['media']:
            args['featured_media'] = STATE['media'][meta['seo']['image_id_token']]['id']
        pid = STATE['pages'].get(slug) or meta.get('wp_id')
        if pid:
            args['page_id'] = pid
            r = call('wp_update_page', args)
        else:
            r = call('wp_create_page', args)
            pid = r['id']
        with _slock:
            STATE['pages'][slug] = pid
        save_state()
        print('página', slug, pid, r.get('link', '') if isinstance(r, dict) else '', flush=True)


def step_settings():
    c = build.CFG
    call('wp_update_site_settings', {'title': c['org_name'], 'description': c.get('tagline', 'Central de atendimento especializada em saúde suplementar'),
                                     'timezone': 'America/Sao_Paulo', 'date_format': 'd/m/Y', 'time_format': 'H:i'})
    front = STATE['pages'].get('inicio')
    ok = False
    if front:
        try:
            call('wp_update_site_settings', {'show_on_front': 'page', 'page_on_front': front})
            s = call('wp_get_site_settings', {})
            ok = s.get('show_on_front') == 'page' and int(s.get('page_on_front') or 0) == front
        except Exception as e:
            print('front page:', e)
    STATE['front_page_setting_ok'] = ok
    save_state()
    print('configurações ok; página inicial estática via ajuste:', ok)


def step_menu():
    """Menu nativo do WordPress (para editores, acessibilidade e navegação alternativa)."""
    if STATE.get('menu_id'):
        print('menu já existe', STATE['menu_id'])
        return
    r = call('wp_create_menu', {'name': 'Menu principal Télix', 'slug': 'menu-principal-telix'})
    mid = r['id']
    STATE['menu_id'] = mid
    pos = 1
    for it in build.NAV:
        if 'groups' in it:
            top = call('wp_create_menu_item', {'menu_id': mid, 'title': it['label'], 'url': it['groups'][0]['links'][0][2] if it['id'] != 'solucoes' else '/solucoes/', 'object_type': 'custom', 'position': pos})
            pos += 1
            for g in it['groups']:
                for ic, t, h, d in g['links']:
                    call('wp_create_menu_item', {'menu_id': mid, 'title': t, 'url': build.fill(h), 'object_type': 'custom', 'parent': top['id'], 'position': pos})
                    pos += 1
        else:
            call('wp_create_menu_item', {'menu_id': mid, 'title': it['label'], 'url': it['href'], 'object_type': 'custom', 'position': pos})
            pos += 1
    save_state()
    print('menu criado', mid)


def step_cleanup():
    for pid in STATE.get('cleanup_pages', []):
        try:
            call('wp_delete_page', {'page_id': pid, 'force': True}); print('removida página', pid)
        except Exception as e:
            print('cleanup', pid, e)
    for pid in STATE.get('cleanup_posts', []):
        try:
            call('wp_delete_post', {'post_id': pid, 'force': True}); print('removido post', pid)
        except Exception as e:
            print('cleanup', pid, e)


if __name__ == '__main__':
    steps = sys.argv[1:] or ['all']
    if 'all' in steps:
        steps = ['media', 'styles', 'parts', 'pages', 'settings', 'templates', 'menu']
    only = None
    if steps and steps[0] == 'pages' and len(steps) > 1:
        only = set(steps[1:]); steps = ['pages']
    for s in steps:
        print('==>', s)
        if s == 'pages':
            step_pages(only)
        else:
            globals()['step_' + s]()
