#!/usr/bin/env python3
"""Build do site Télix: gera cabeçalho (megamenu), rodapé+widgets, CSS global e páginas
(Gutenberg <!-- wp:html --> blocks) a partir de src/ e config/site.json -> dist/."""
import base64, json, os, re, html as H

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC, DIST = os.path.join(ROOT, 'src'), os.path.join(ROOT, 'dist')
CFG = json.load(open(os.path.join(ROOT, 'config', 'site.json'), encoding='utf-8'))


def I(name, cls='tx-ic'):
    return f'<svg class="{cls}" aria-hidden="true" focusable="false"><use href="#i-{name}"/></svg>'


def e(s):
    return H.escape(str(s), quote=True)


# ---------------------------------------------------------------------------
# Navegação (fonte única para megamenu, drawer, rodapé e mapa do site)
# ---------------------------------------------------------------------------
NAV = [
    {'label': 'Início', 'href': '/'},
    {'label': 'A Télix', 'id': 'empresa', 'cols': 2, 'groups': [
        {'title': 'Institucional', 'links': [
            ('building', 'Quem somos', '/a-telix/', 'História, propósito e o que nos move'),
            ('target', 'Missão, visão e valores', '/a-telix/#missao-visao-valores', 'Os compromissos que guiam cada atendimento'),
            ('award', 'Diferenciais', '/a-telix/#diferenciais', 'Por que operadoras confiam na Télix'),
            ('stethoscope', 'Especialistas em Saúde Suplementar', '/especialistas-em-saude-suplementar/', 'Experiência com operadoras e com a ANS'),
        ]},
        {'title': 'Conheça mais', 'links': [
            ('users', 'Clientes', '/clientes/', 'Cooperativas do Sistema Unimed em vários estados'),
            ('briefcase', 'Trabalhe conosco', '/trabalhe-conosco/', 'Faça parte de uma equipe que cuida de pessoas'),
            ('pin', 'Onde estamos', '/contato/#como-chegar', 'Endereço, mapa e como chegar'),
            ('help', 'Perguntas frequentes', '/perguntas-frequentes/', 'Respostas rápidas sobre serviços e contratação'),
        ]},
    ], 'feature': {'eyebrow': 'Mais de 10 anos', 'title': 'Relacionamento humano, tecnologia e resultado para a saúde suplementar.',
                   'text': 'Conheça a trajetória, a estrutura e a equipe que cuidam dos beneficiários dos nossos clientes.',
                   'cta': ('Conhecer a Télix', '/a-telix/')}},
    {'label': 'Soluções', 'id': 'solucoes', 'cols': 2, 'groups': [
        {'title': 'Centrais de atendimento', 'links': [
            ('headset', 'Central de Atendimento', '/solucoes/central-de-atendimento/', 'Call center ativo e receptivo especializado'),
            ('message-text', 'SAC para operadoras', '/solucoes/sac/', 'Serviço de Atendimento ao Consumidor/Cliente'),
            ('ticket', 'Sistema de Protocolo (RN 623)', '/solucoes/sistema-de-protocolo/', 'Registro, rastreio e tratativa de demandas'),
            ('ambulance', 'Regulação e Remoção 24h', '/solucoes/regulacao-e-remocao-hospitalar/', 'Vaga hospitalar e transporte, 24/7'),
        ]},
        {'title': 'Agenda, experiência e qualidade', 'links': [
            ('calendar', 'Agendamento (RN 566)', '/solucoes/agendamento/', 'Consultas, exames e procedimentos no prazo'),
            ('smile', 'Pesquisa de Satisfação', '/solucoes/pesquisa-de-satisfacao/', 'A voz do beneficiário em indicadores'),
            ('bot', 'WhatsApp e Chatbots', '/solucoes/atendimento-digital/', 'Atendimento digital humanizado e integrado'),
            ('chart', 'Qualidade e Monitoria', '/solucoes/qualidade-e-monitoria/', 'Apoio e análise de qualidade do atendimento'),
        ]},
    ], 'feature': {'eyebrow': 'Destaque 24/7', 'title': 'Regulação de vaga e remoção hospitalar a qualquer hora.',
                   'text': 'Central telefônica e WhatsApp com rastreabilidade de ponta a ponta e indicadores mensais.',
                   'cta': ('Ver todas as soluções', '/solucoes/')}},
    {'label': 'Clientes', 'href': '/clientes/'},
    {'label': 'Conformidade', 'id': 'conformidade', 'cols': 3, 'groups': [
        {'title': 'Governança', 'links': [
            ('shield', 'Visão geral', '/conformidade/', 'Nosso sistema integrado de conformidade'),
            ('target', 'Missão, visão e valores', '/conformidade/#identidade', 'Identidade organizacional'),
            ('layers', 'Política integrada', '/conformidade/#politica-integrada', 'Qualidade, privacidade, integridade e segurança'),
        ]},
        {'title': 'Políticas', 'links': [
            ('lock', 'Privacidade e LGPD', '/conformidade/politica-de-privacidade/', 'Como tratamos dados pessoais'),
            ('scale', 'Compliance e Integridade', '/conformidade/politica-de-compliance/', 'Ética, anticorrupção e conduta'),
            ('database', 'Segurança da Informação', '/conformidade/politica-de-seguranca-da-informacao/', 'Confidencialidade, integridade e disponibilidade'),
            ('leaf', 'ESG', '/conformidade/politica-esg/', 'Ambiental, social e governança'),
            ('cpu', 'Inteligência Artificial', '/conformidade/politica-de-inteligencia-artificial/', 'Uso ético e responsável de IA'),
            ('cookie', 'Cookies', '/conformidade/politica-de-cookies/', 'Tecnologias de navegação e preferências'),
        ]},
        {'title': 'Canais', 'links': [
            ('key', 'Canal LGPD', '/canal-lgpd/', 'Exercício de direitos do titular'),
            ('megaphone', 'Canal de Denúncia', '/canal-de-denuncia/', 'Relatos de conduta, anônimos ou não'),
            ('a11y', 'Acessibilidade', '/acessibilidade/', 'Recursos e compromisso de inclusão'),
        ]},
    ], 'feature': None},
    {'label': 'Contato', 'id': 'contato', 'cols': 2, 'small': True, 'groups': [
        {'title': 'Fale com a gente', 'links': [
            ('mail', 'Fale conosco', '/contato/', 'Dúvidas, sugestões, solicitações e propostas'),
            ('navigation', 'Como chegar', '/contato/#como-chegar', 'Mapa, rotas e referências'),
        ]},
        {'title': 'Atalhos', 'links': [
            ('whatsapp', 'WhatsApp', '{WA_LINK}', 'Converse agora com nossa equipe'),
            ('phone', 'Telefone', '{TEL_LINK}', '{PHONE}'),
        ]},
    ], 'feature': {'eyebrow': 'Atendimento', 'title': 'Vamos conversar sobre a experiência dos seus beneficiários?',
                   'text': 'Receba uma proposta sob medida para a sua operadora, hospital ou clínica.',
                   'cta': ('Solicitar proposta', '/contato/#proposta')}},
]


def fill(s):
    """Substitui {TOKENS} com dados do config."""
    c = CFG
    rep = {
        '{PHONE}': c['phone_display'], '{TEL_LINK}': 'tel:' + c['phone_tel'],
        '{WA_LINK}': c.get('whatsapp_link') or ('https://wa.me/' + c['whatsapp_number'] if c.get('whatsapp_number') else 'tel:' + c['phone_tel']),
        '{EMAIL}': c['email'], '{MAILTO}': 'mailto:' + c['email'],
    }
    for k, v in rep.items():
        s = s.replace(k, v)
    return s


def link_attrs(href):
    if href.startswith('http'):
        return f'href="{e(href)}" target="_blank" rel="noopener"'
    return f'href="{e(href)}"'


def mega_html(item):
    cols = []
    for g in item['groups']:
        links = ''.join(
            f'<a class="tx-mega__link" {link_attrs(fill(h))}><span class="tx-mi">{I(ic)}</span><span><strong>{e(t)}</strong><span>{e(fill(d))}</span></span></a>'
            for ic, t, h, d in g['links'])
        cols.append(f'<div class="tx-mega__group"><p class="tx-mega__title">{e(g["title"])}</p>{links}</div>')
    feat = ''
    f = item.get('feature')
    if f:
        feat = (f'<aside class="tx-mega__feature"><p class="tx-eyebrow">{e(f["eyebrow"])}</p><strong>{e(f["title"])}</strong>'
                f'<p>{e(f["text"])}</p><a class="tx-btn tx-btn--sm" href="{e(f["cta"][1])}">{e(f["cta"][0])} {I("arrow-right")}</a></aside>')
    inner_style = '' if f else ' style="grid-template-columns:1fr"'
    return (f'<div class="tx-mega{" tx-mega--sm" if item.get("small") else ""}" id="mega-{item["id"]}">'
            f'<div class="tx-mega__inner"{inner_style}><div class="tx-mega__cols" style="--cols:{item["cols"]}">{"".join(cols)}</div>{feat}</div></div>')


def header_html():
    c = CFG
    sprite = open(os.path.join(SRC, 'parts', 'sprite.svg'), encoding='utf-8').read()
    lis = []
    for it in NAV:
        if 'groups' in it:
            lis.append(f'<li class="tx-nav__item has-mega"><button type="button" class="tx-nav__link" aria-expanded="false" aria-controls="mega-{it["id"]}">{e(it["label"])} {I("chevron-down")}</button>{mega_html(it)}</li>')
        else:
            lis.append(f'<li class="tx-nav__item"><a class="tx-nav__link" href="{it["href"]}">{e(it["label"])}</a></li>')
    drawer = []
    for it in NAV:
        if 'groups' in it:
            links = ''.join(f'<a {link_attrs(fill(h))}>{I(ic)}{e(t)}</a>' for g in it['groups'] for ic, t, h, d in g['links'])
            drawer.append(f'<details><summary>{e(it["label"])} {I("chevron-down")}</summary><div>{links}</div></details>')
        else:
            drawer.append(f'<a href="{it["href"]}">{e(it["label"])} {I("chevron-right")}</a>')
    return f'''<a class="tx-skip" href="#conteudo">Pular para o conteúdo</a>
<div class="tx-progress" aria-hidden="true"></div>
{sprite}
<div class="tx tx-header">
  <div class="tx-topbar">
    <div class="tx-container">
      <div class="tx-topbar__l">
        <span>{I("heart-pulse")} Especialistas em relacionamento na Saúde Suplementar</span>
        <span class="tx-topbar__sep" aria-hidden="true"></span>
        <span>{I("clock")} Regulação e remoção 24h, 7 dias por semana</span>
      </div>
      <div class="tx-topbar__r">
        <a href="tel:{c["phone_tel"]}" aria-label="Telefone {e(c["phone_display"])}">{I("phone")} {e(c["phone_display"])}</a>
        <a class="tx-topbar__hide-sm" {link_attrs(fill("{WA_LINK}"))}>{I("whatsapp")} WhatsApp</a>
        <a class="tx-topbar__hide-sm tx-topbar__mail" href="mailto:{e(c["email"])}">{I("mail")} {e(c["email"])}</a>
        <span class="tx-topbar__sep tx-topbar__hide-sm" aria-hidden="true"></span>
        <a href="/canal-lgpd/">{I("key")} Canal LGPD</a>
        <a href="/canal-de-denuncia/">{I("megaphone")} Canal de Denúncia</a>
      </div>
    </div>
  </div>
  <div class="tx-navbar">
    <div class="tx-container">
      <a class="tx-brand" href="/" aria-label="Télix Comunicação e Relacionamento — página inicial">
        <svg viewBox="0 0 220 69.855" role="img" aria-labelledby="tx-logo-t"><title id="tx-logo-t">Télix Comunicação e Relacionamento</title><use href="#tx-logo"/></svg>
      </a>
      <nav class="tx-nav" aria-label="Menu principal"><ul>{"".join(lis)}</ul></nav>
      <a class="tx-btn tx-btn--sm tx-nav__cta" href="/contato/#proposta">Solicitar proposta {I("arrow-right")}</a>
      <button type="button" class="tx-burger" aria-expanded="false" aria-controls="tx-drawer" aria-label="Abrir menu">{I("menu")}</button>
    </div>
  </div>
</div>
<div class="tx tx-drawer" id="tx-drawer" aria-hidden="true">
  <div class="tx-drawer__bg" data-tx-drawer-close></div>
  <div class="tx-drawer__panel" role="dialog" aria-modal="true" aria-label="Menu">
    <div class="tx-drawer__head">
      <svg class="tx-logo" viewBox="0 0 220 69.855" aria-hidden="true"><use href="#tx-logo"/></svg>
      <button type="button" class="tx-drawer__close" data-tx-drawer-close aria-label="Fechar menu">{I("x")}</button>
    </div>
    <nav class="tx-drawer__body" aria-label="Menu móvel">{"".join(drawer)}</nav>
    <div class="tx-drawer__foot">
      <a class="tx-btn tx-btn--block" href="/contato/#proposta">Solicitar proposta {I("arrow-right")}</a>
      <a class="tx-btn tx-btn--wa tx-btn--block" {link_attrs(fill("{WA_LINK}"))}>{I("whatsapp")} Falar no WhatsApp</a>
      <a class="tx-btn tx-btn--ghost tx-btn--block" href="tel:{c["phone_tel"]}">{I("phone")} {e(c["phone_display"])}</a>
    </div>
  </div>
</div>'''


def footer_html():
    c = CFG
    sol = next(n for n in NAV if n.get('id') == 'solucoes')
    conf = next(n for n in NAV if n.get('id') == 'conformidade')
    sol_links = ''.join(f'<li><a href="{h}">{e(t)}</a></li>' for g in sol['groups'] for ic, t, h, d in g['links'])
    inst = [('Quem somos', '/a-telix/'), ('Missão, visão e valores', '/a-telix/#missao-visao-valores'), ('Especialistas em Saúde Suplementar', '/especialistas-em-saude-suplementar/'),
            ('Clientes', '/clientes/'), ('Trabalhe conosco', '/trabalhe-conosco/'), ('Perguntas frequentes', '/perguntas-frequentes/'), ('Contato', '/contato/')]
    inst_links = ''.join(f'<li><a href="{h}">{e(t)}</a></li>' for t, h in inst)
    conf_links = ''.join(f'<li><a href="{h}">{e(t)}</a></li>' for g in conf['groups'] for ic, t, h, d in g['links'] if t not in ('Missão, visão e valores',))
    social = ''.join(f'<a href="{e(u)}" target="_blank" rel="noopener" aria-label="{e(n)} da Télix (abre em nova aba)">{I(ic)}</a>'
                     for ic, n, u in [('instagram', 'Instagram', c['instagram']), ('linkedin', 'LinkedIn', c['linkedin']), ('facebook', 'Facebook', c['facebook'])] if u)
    addr = e(c['address_line1']) + '<br>' + e(c['address_line2'])
    wa = fill('{WA_LINK}')
    js = open(os.path.join(SRC, 'js', 'telix.js'), encoding='utf-8').read()
    tx_config = {
        'email': c['email'], 'description': c['default_description'],
        'logo': c.get('logo_url', ''), 'favicon': c.get('favicon_url', ''), 'ogImage': c.get('og_image_url', ''),
        'geo': c.get('geo'), 'geoRegion': c.get('geo_region', 'BR-GO'), 'geoPlace': c.get('geo_place', ''),
        'org': {'name': c['org_name'], 'schema': c['schema_org']},
    }
    cookie_rows = lambda rows: ''.join(f'<tr><td>{e(a)}</td><td>{e(b)}</td><td>{e(d)}</td></tr>' for a, b, d in rows)
    cats = [
        ('necessary', 'Estritamente necessários', 'Essenciais para o site funcionar e para lembrar suas escolhas de privacidade e acessibilidade. Não podem ser desativados.', True,
         [('tx_consent', 'Registra suas preferências de cookies', '180 dias'), ('tx_a11y (armazenamento local)', 'Guarda preferências de acessibilidade', 'Até ser apagado'), ('wordpress_test_cookie / wp-settings-*', 'Funcionamento do WordPress', 'Sessão / 1 ano')]),
        ('functional', 'Funcionais', 'Habilitam recursos de terceiros, como o mapa do Google Maps em “Como chegar”.', False,
         [('Google Maps (NID, AEC e outros)', 'Exibição do mapa interativo incorporado', 'Até 6 meses')]),
        ('analytics', 'Analíticos', 'Ajudam a entender, de forma agregada, como o site é usado para melhorarmos conteúdos. Hoje não utilizamos ferramentas analíticas; se forem adotadas, só serão ativadas com o seu consentimento.', False, []),
        ('marketing', 'Marketing', 'Permitiriam personalizar comunicações em outras plataformas. Atualmente não utilizamos cookies de marketing.', False, []),
    ]
    cat_html = ''
    for key, title, desc, locked, rows in cats:
        table = (f'<details><summary>Ver cookies desta categoria</summary><table><thead><tr><th>Nome</th><th>Finalidade</th><th>Duração</th></tr></thead><tbody>{cookie_rows(rows)}</tbody></table></details>'
                 if rows else '<details><summary>Ver cookies desta categoria</summary><p>Nenhum cookie desta categoria em uso no momento.</p></details>')
        cat_html += (f'<div class="tx-cat"><div class="tx-cat__row"><div><strong id="cat-{key}">{e(title)}</strong><small>{e(desc)}</small></div>'
                     f'<label class="tx-switch"><input type="checkbox" data-tx-cat="{key}" aria-labelledby="cat-{key}" {"checked disabled" if locked else ""}><span></span></label></div>{table}</div>')
    return f'''<div class="tx tx-footer">
  <div class="tx-footer__cta">
    <div class="tx-container">
      <div><strong>Seus beneficiários merecem um atendimento que resolve.</strong><span>Fale com um especialista e receba uma proposta sob medida.</span></div>
      <div class="tx-btns"><a class="tx-btn tx-btn--dark" href="/contato/#proposta">Solicitar proposta {I("arrow-right")}</a><a class="tx-btn tx-btn--ghost" {link_attrs(wa)}>{I("whatsapp")} WhatsApp</a></div>
    </div>
  </div>
  <div class="tx-container">
    <div class="tx-footer__main">
      <div class="tx-footer__brand">
        <svg viewBox="0 0 220 69.855" role="img" aria-label="Télix Comunicação e Relacionamento"><use href="#tx-logo"/></svg>
        <p>Comunicação e relacionamento com excelência para operadoras de planos de saúde, hospitais e clínicas. Atendimento humanizado, tecnologia e foco em resultado.</p>
        <div class="tx-social">{social}</div>
        <div class="tx-footer__seals"><span>{I("lock")} LGPD</span><span>{I("shield")} Compliance</span><span>{I("a11y")} Acessível</span></div>
      </div>
      <nav aria-labelledby="ft-sol"><h2 id="ft-sol">Soluções</h2><ul>{sol_links}<li><a href="/solucoes/">Todas as soluções</a></li></ul></nav>
      <nav aria-labelledby="ft-inst"><h2 id="ft-inst">A Télix</h2><ul>{inst_links}</ul></nav>
      <nav aria-labelledby="ft-conf"><h2 id="ft-conf">Conformidade</h2><ul>{conf_links}</ul></nav>
      <div class="tx-footer__contact"><h2>Atendimento</h2><ul>
        <li>{I("pin")}<span>{addr}<br><a href="/contato/#como-chegar">Como chegar</a></span></li>
        <li>{I("phone")}<a href="tel:{c["phone_tel"]}">{e(c["phone_display"])}</a></li>
        <li>{I("whatsapp")}<a {link_attrs(wa)}>{e(c.get("whatsapp_display") or "WhatsApp")}</a></li>
        <li>{I("mail")}<a href="mailto:{e(c["email"])}">{e(c["email"])}</a></li>
        <li>{I("clock")}<span>{e(c["hours"])}</span></li>
      </ul></div>
    </div>
  </div>
  <div class="tx-footer__bottom">
    <div class="tx-container">
      <p style="margin:0">© <span data-year>2026</span> {e(c["legal_name"])}{(" — CNPJ " + e(c["cnpj"])) if c.get("cnpj") else ""}. Todos os direitos reservados.</p>
      <div class="tx-footer__legal">
        <a href="/conformidade/politica-de-privacidade/">Privacidade</a>
        <a href="/conformidade/politica-de-cookies/">Cookies</a>
        <button type="button" data-tx-cookie="open">Preferências de cookies</button>
        <a href="/acessibilidade/">Acessibilidade</a>
        <a href="/mapa-do-site/">Mapa do site</a>
      </div>
    </div>
  </div>
</div>

<div class="tx tx-fab">
  <ul class="tx-fab__list" id="tx-fab-list" aria-label="Canais de contato">
    <li><a class="tx-fab__wa" {link_attrs(wa)}><span>WhatsApp</span><i>{I("whatsapp")}</i></a></li>
    <li><a class="tx-fab__ph" href="tel:{c["phone_tel"]}"><span>Ligar: {e(c["phone_display"])}</span><i>{I("phone")}</i></a></li>
    <li><a class="tx-fab__em" href="mailto:{e(c["email"])}"><span>E-mail</span><i>{I("mail")}</i></a></li>
    {f'<li><a class="tx-fab__ig" href="{e(c["instagram"])}" target="_blank" rel="noopener"><span>Instagram</span><i>{I("instagram")}</i></a></li>' if c.get("instagram") else ""}
    {f'<li><a class="tx-fab__in" href="{e(c["linkedin"])}" target="_blank" rel="noopener"><span>LinkedIn</span><i>{I("linkedin")}</i></a></li>' if c.get("linkedin") else ""}
    <li><a class="tx-fab__mp" href="/contato/#como-chegar"><span>Como chegar</span><i>{I("pin")}</i></a></li>
  </ul>
  <button type="button" class="tx-fab__toggle" aria-expanded="false" aria-controls="tx-fab-list" aria-label="Abrir canais de contato">{I("message", "tx-ic tx-ic--open")}{I("x", "tx-ic tx-ic--close")}</button>
</div>
<button type="button" class="tx tx-totop" aria-label="Voltar ao topo">{I("arrow-up")}</button>

<button type="button" class="tx tx-a11y-btn" aria-expanded="false" aria-controls="tx-a11y" aria-label="Abrir recursos de acessibilidade (Alt+Shift+A)" title="Acessibilidade">{I("a11y")}</button>
<div class="tx tx-a11y" id="tx-a11y" role="dialog" aria-label="Recursos de acessibilidade" aria-hidden="true">
  <div class="tx-a11y__head"><strong>{I("a11y")} Acessibilidade</strong><button type="button" data-a11y-close aria-label="Fechar">{I("x")}</button></div>
  <div class="tx-a11y__grid">
    <div class="tx-a11y__size"><span>Tamanho do texto <b data-a11y-scale-val>100%</b></span><span><button type="button" data-a11y-scale="-0.1" aria-label="Diminuir texto">A−</button> <button type="button" data-a11y-scale="0.1" aria-label="Aumentar texto">A+</button></span></div>
    <button type="button" class="tx-a11y__opt" data-a11y="contrast" aria-pressed="false">{I("contrast")}Alto contraste</button>
    <button type="button" class="tx-a11y__opt" data-a11y="gray" aria-pressed="false">{I("droplet")}Escala de cinza</button>
    <button type="button" class="tx-a11y__opt" data-a11y="links" aria-pressed="false">{I("link")}Destacar links</button>
    <button type="button" class="tx-a11y__opt" data-a11y="readable" aria-pressed="false">{I("type")}Fonte legível</button>
    <button type="button" class="tx-a11y__opt" data-a11y="spacing" aria-pressed="false">{I("lines")}Espaçamento</button>
    <button type="button" class="tx-a11y__opt" data-a11y="headings" aria-pressed="false">{I("heading")}Destacar títulos</button>
    <button type="button" class="tx-a11y__opt" data-a11y="cursor" aria-pressed="false">{I("pointer")}Cursor grande</button>
    <button type="button" class="tx-a11y__opt" data-a11y="guide" aria-pressed="false">{I("guide")}Guia de leitura</button>
    <button type="button" class="tx-a11y__opt" data-a11y="noanim" aria-pressed="false">{I("pause")}Pausar animações</button>
    <button type="button" class="tx-a11y__opt" data-a11y-libras>{I("hand")}Libras (VLibras)</button>
  </div>
  <div class="tx-a11y__foot"><a href="/acessibilidade/">Declaração de acessibilidade</a><button type="button" data-a11y-reset>{I("reset")} Restaurar</button></div>
</div>
<div class="tx-guide" aria-hidden="true"></div>

<div class="tx tx-cookie" role="region" aria-label="Aviso de cookies" hidden>
  <div class="tx-cookie__head"><span class="tx-icon">{I("cookie")}</span><strong>Sua privacidade importa</strong></div>
  <p>Usamos cookies necessários para o site funcionar e, com a sua permissão, cookies opcionais para recursos como mapas. Você pode aceitar, rejeitar ou personalizar a qualquer momento. Saiba mais na <a href="/conformidade/politica-de-cookies/">Política de Cookies</a> e na <a href="/conformidade/politica-de-privacidade/">Política de Privacidade</a>.</p>
  <div class="tx-cookie__btns">
    <button type="button" class="tx-btn" data-tx-cookie="accept">Aceitar todos</button>
    <button type="button" class="tx-btn tx-btn--ghost" data-tx-cookie="reject">Rejeitar opcionais</button>
    <button type="button" class="tx-btn tx-btn--link" data-tx-cookie="customize">Personalizar preferências</button>
  </div>
  <div class="tx-cookie__brand">Gestão de consentimento <b>Télix Cookies</b></div>
</div>
<button type="button" class="tx tx-cookie-reopen" data-tx-cookie="open" aria-label="Preferências de cookies" title="Preferências de cookies">{I("cookie")}</button>
<div class="tx tx-modal" id="tx-cookie-modal" aria-hidden="true">
  <div class="tx-modal__bg" data-tx-cookie="close"></div>
  <div class="tx-modal__box" role="dialog" aria-modal="true" aria-labelledby="tx-cookie-title">
    <div class="tx-modal__head"><h2 id="tx-cookie-title">Preferências de cookies</h2><button type="button" class="tx-modal__x" data-tx-cookie="close" aria-label="Fechar">{I("x")}</button></div>
    <div class="tx-modal__body">
      <p class="tx-small tx-muted">Você controla quais categorias opcionais podem ser usadas. Sua escolha fica registrada por até 180 dias e pode ser alterada a qualquer momento pelo ícone de cookie ou pelo rodapé. Base legal: consentimento (art. 7º, I, da LGPD) para categorias opcionais.</p>
      {cat_html}
    </div>
    <div class="tx-modal__foot">
      <button type="button" class="tx-btn tx-btn--ghost tx-btn--sm" data-tx-cookie="reject">Rejeitar opcionais</button>
      <button type="button" class="tx-btn tx-btn--dark tx-btn--sm" data-tx-cookie="save">Salvar minhas escolhas</button>
      <button type="button" class="tx-btn tx-btn--sm" data-tx-cookie="accept">Aceitar todos</button>
    </div>
  </div>
</div>
{js_loader(tx_config, js)}'''


def js_loader(tx_config, js):
    """Empacota config + runtime em Base64: o WordPress altera '&' dentro de scripts inline
    (vira '&#038;'), então o carregador não contém nenhum caractere '&', '<' ou '>'."""
    bundle = 'window.TX_CONFIG=' + json.dumps(tx_config, ensure_ascii=False) + ';\n' + js
    b64 = base64.b64encode(bundle.encode('utf-8')).decode('ascii')
    loader = ("(function(){var r=atob('" + b64 + "'),n=r.length,u=new Uint8Array(n),i;for(i=0;i!==n;i++){u[i]=r.charCodeAt(i)}"
              "var s=document.createElement('script');s.text=new TextDecoder('utf-8').decode(u);document.body.appendChild(s)})();")
    assert not re.search(r'[&<>]', loader)
    return '<script>' + loader + '</script>'


def fonts_html():
    return ('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Exo+2:wght@600;700;800&family=Montserrat:wght@600;700;800&family=Roboto:wght@400;500;700&display=swap">')


def wrap_block(inner):
    return '<!-- wp:html -->\n' + inner + '\n<!-- /wp:html -->'


def build_parts():
    os.makedirs(DIST, exist_ok=True)
    head = fonts_html() + '\n' + header_html()
    open(os.path.join(DIST, 'header.html'), 'w', encoding='utf-8').write(wrap_block(head))
    open(os.path.join(DIST, 'footer.html'), 'w', encoding='utf-8').write(wrap_block(footer_html()))
    css = open(os.path.join(SRC, 'styles', 'global.css'), encoding='utf-8').read()
    open(os.path.join(DIST, 'global.css'), 'w', encoding='utf-8').write(css)
    return head


# ---------------------------------------------------------------------------
# Páginas: src/pages/*.html com cabeçalho JSON em comentário <!--META {...} META-->
# ---------------------------------------------------------------------------
def parse_page(path):
    raw = open(path, encoding='utf-8').read()
    m = re.match(r'\s*<!--META\s*(\{.*?\})\s*META-->\s*', raw, re.S)
    if not m:
        raise ValueError('META ausente em ' + path)
    meta = json.loads(m.group(1))
    body = raw[m.end():]
    return meta, body


UF_NAMES = {'AC':'Acre','AL':'Alagoas','AM':'Amazonas','AP':'Amapá','BA':'Bahia','CE':'Ceará','DF':'Distrito Federal','ES':'Espírito Santo','GO':'Goiás','MA':'Maranhão','MG':'Minas Gerais','MS':'Mato Grosso do Sul','MT':'Mato Grosso','PA':'Pará','PB':'Paraíba','PE':'Pernambuco','PI':'Piauí','PR':'Paraná','RJ':'Rio de Janeiro','RN':'Rio Grande do Norte','RO':'Rondônia','RR':'Roraima','RS':'Rio Grande do Sul','SC':'Santa Catarina','SE':'Sergipe','SP':'São Paulo','TO':'Tocantins'}


def generated_tokens():
    c = CFG
    t = {}
    cl = c.get('clients', [])
    served = {}
    for x in cl:
        s = served.setdefault(x['uf'], {'clients': [], 'pins': []})
        s['clients'].append(x['name'])
        if x.get('lat') is not None:
            s['pins'].append([x['lat'], x['lng']])
    t['N_CLIENTES'] = str(len(cl))
    t['N_ESTADOS'] = str(len(served))
    t['SERVED_JSON'] = json.dumps(served, ensure_ascii=False).replace("'", '&#39;')
    ufs = sorted(served, key=lambda u: UF_NAMES.get(u, u))
    names = [UF_NAMES.get(u, u) for u in ufs]
    t['SERVED_LIST'] = (', '.join(names[:-1]) + ' e ' + names[-1]) if len(names) > 1 else ''.join(names)
    tok = c.get('tokens', {})
    def card(x, lazy=True):
        city = x.get('city', '')
        cap = e(x['name']) + (f' <br><small>{e(city)} – {x["uf"]}</small>' if city and 'confirmar' not in city else f' <br><small>{x["uf"]}</small>')
        return (f'<figure class="tx-logo-card"><img src="{e(tok.get(x["logo"], ""))}" alt="Logotipo {e(x["name"])}" width="190" height="90" loading="lazy">'
                f'<figcaption>{cap}</figcaption></figure>')
    t['LOGO_MARQUEE'] = '\n'.join(card(x) for x in cl + cl)
    t['LOGO_GRID'] = '\n'.join(card(x) for x in cl)
    t['BR_GEO'] = open(os.path.join(SRC, 'parts', 'br-geo.json'), encoding='utf-8').read()
    return t


def render_page(meta, body):
    seo = dict(meta.get('seo', {}))
    seo.setdefault('breadcrumb', meta.get('breadcrumb', []))
    body = fill(body)
    body = re.sub(r'\[\[i:([a-z0-9-]+)\]\]', lambda m: I(m.group(1)), body)
    toks = dict(CFG.get('tokens', {}))
    toks.update(generated_tokens())
    for k, v in toks.items():
        body = body.replace('{{' + k + '}}', str(v))
    known = set(open(os.path.join(ROOT, 'docs', 'icons.txt')).read().split())
    bad_icons = sorted(set(re.findall(r'#i-([a-z0-9-]+)', body)) - known)
    if bad_icons:
        raise ValueError(f'ícones inexistentes em {meta.get("slug")}: {bad_icons}')
    left = re.findall(r'\{\{[A-Z0-9_]+\}\}', body)
    if left:
        raise ValueError(f'tokens não resolvidos em {meta.get("slug")}: {sorted(set(left))}')
    seo_json = json.dumps(seo, ensure_ascii=False).replace('</', '<\\/')
    inner = f'<script type="application/json" id="tx-seo">{seo_json}</script>\n<div class="tx tx-page tx-page--{e(meta["slug"])}">\n{body.strip()}\n</div>'
    return wrap_block(inner)


def build_pages():
    out = {}
    pdir = os.path.join(SRC, 'pages')
    os.makedirs(os.path.join(DIST, 'pages'), exist_ok=True)
    for fn in sorted(os.listdir(pdir)):
        if not fn.endswith('.html'):
            continue
        meta, body = parse_page(os.path.join(pdir, fn))
        html_ = render_page(meta, body)
        open(os.path.join(DIST, 'pages', fn), 'w', encoding='utf-8').write(html_)
        out[fn] = meta
    json.dump(out, open(os.path.join(DIST, 'pages.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    return out


if __name__ == '__main__':
    build_parts()
    pages = build_pages() if os.path.isdir(os.path.join(SRC, 'pages')) else {}
    print('ok parts; pages:', len(pages))
