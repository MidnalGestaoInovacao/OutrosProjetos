# -*- coding: utf-8 -*-
"""Conteúdo das páginas de bsagro.agr.br (além da página inicial e das políticas em content/policies.json).
Placeholders: {{U:slug}} (link de página), {{HOME}}, {{IMG:chave}}, {{EMAIL:canal}}, {{POLICY:chave}} (texto de content/policies.json),
{{PORTAL}} (shortcode da Área do Cliente ou aviso, conforme o plugin esteja ativo)."""

CONSENT = ('<label class="bs-check"><input type="checkbox" name="consentimento" value="Sim" required>'
           '<span>Li o <a href="{{U:aviso-de-privacidade}}" target="_blank" rel="noopener">Aviso de Privacidade</a> e entendo que meus dados serão usados para responder a esta mensagem.</span></label>')
HONEY = '<div class="bs-hp" aria-hidden="true"><label>Não preencha<input type="text" name="_honey" tabindex="-1" autocomplete="off"></label></div>'

# ------------------------------------------------------------------ CANAL DE CONTATO
CONTATO = {
    "slug": "contato", "title": "Canal de contato", "template": "page-no-title",
    "seo_title": "Canal de contato — BS Agro Capital | Crédito estruturado para o agro",
    "excerpt": "Fale com a BS Agro Capital: contato central, WhatsApp, Área do Cliente e canais de privacidade e integridade, todos em um só lugar.",
    "html": """
<section class="bs-sec"><div class="bs-wrap">
  <div class="bs-grid-3">
    <div class="bs-channel revelar"><svg aria-hidden="true"><use href="#i-mail"/></svg><h2 class="bs-channel__t font-display text-xl font-semibold text-graphite-900">Contato central</h2><p>Solicitações comerciais, dúvidas sobre soluções, parcerias e imprensa.</p><a class="bs-channel__main" href="mailto:{{EMAIL:contato}}">{{EMAIL:contato}}</a></div>
    <div class="bs-channel revelar"><svg aria-hidden="true"><use href="#i-whats"/></svg><h2 class="font-display text-xl font-semibold text-graphite-900">WhatsApp e telefone</h2><p>Converse direto com um especialista sobre a sua operação.</p><a class="bs-channel__main" href="#" data-bs-wa target="_blank" rel="noopener noreferrer">(62) 99689-2488</a></div>
    <div class="bs-channel revelar"><svg aria-hidden="true"><use href="#i-lock"/></svg><h2 class="font-display text-xl font-semibold text-graphite-900">Área do Cliente</h2><p>Solicite a análise on-line, envie documentos e acompanhe o andamento com segurança.</p><a class="bs-channel__main" href="{{U:area-do-cliente}}" data-ebcr-client-area data-keep-label>Acessar a Área do Cliente →</a></div>
    <div class="bs-channel revelar"><svg aria-hidden="true"><use href="#i-key"/></svg><h2 class="font-display text-xl font-semibold text-graphite-900">Privacidade e LGPD</h2><p>Exerça seus direitos de titular junto ao Encarregado pelo Tratamento de Dados Pessoais.</p><a class="bs-channel__main" href="{{U:portal-do-titular}}">Portal do Titular →</a><a class="text-sm text-olive-700 underline" href="mailto:{{EMAIL:dpo}}">{{EMAIL:dpo}}</a></div>
    <div class="bs-channel revelar"><svg aria-hidden="true"><use href="#i-shield"/></svg><h2 class="font-display text-xl font-semibold text-graphite-900">Ouvidoria e integridade</h2><p>Reclamações, denúncias de conduta, fraude ou corrupção, com opção de anonimato.</p><a class="bs-channel__main" href="{{U:canal-de-integridade}}">Canal de Integridade →</a><a class="text-sm text-olive-700 underline" href="mailto:{{EMAIL:ouvidoria}}">{{EMAIL:ouvidoria}}</a></div>
    <div class="bs-channel revelar"><svg aria-hidden="true"><use href="#i-map"/></svg><h2 class="font-display text-xl font-semibold text-graphite-900">Onde estamos</h2><p>Av. 136, 960 – Setor Marista<br>Goiânia – GO, 74180-140</p><a class="bs-channel__main" href="https://www.google.com/maps/search/?api=1&amp;query=Av.+136%2C+960+-+Setor+Marista%2C+Goi%C3%A2nia+-+GO%2C+74180-140" target="_blank" rel="noopener noreferrer">Ver no mapa →</a></div>
  </div>
</div></section>

<section class="bs-sec bg-mesh-light" id="fale-conosco"><div class="bs-wrap bs-grid-2 lg:items-start">
  <div class="revelar max-w-xl">
    <p class="bs-eyebrow">Fale conosco</p>
    <h2 class="bs-h2 mt-3">Escreva para o nosso time</h2>
    <p class="bs-lead mt-4">Conte um pouco sobre a sua necessidade. A mensagem chega ao contato central da BS Agro Capital e você recebe um número de protocolo para acompanhar.</p>
    <div class="bs-box bs-box--warn"><h3>Atenção a golpes</h3><p>A BS Agro Capital nunca pede depósito, PIX ou pagamento para "liberar" crédito, nem solicita senhas por mensagem. Na dúvida, confirme por estes canais oficiais ou escreva para <a href="mailto:{{EMAIL:ouvidoria}}">{{EMAIL:ouvidoria}}</a>.</p></div>
    <p class="mt-4 text-sm text-graphite-600">Prefere enviar documentos ou acompanhar uma solicitação? Use a <a class="text-olive-700 underline" href="{{U:area-do-cliente}}" data-ebcr-client-area data-keep-label>Área do Cliente</a>, onde os arquivos ficam protegidos.</p>
  </div>
  <div class="bs-form-card revelar">
    <form class="bs-form" data-bs-canal="contato" data-bs-prefixo="CT" data-bs-assunto="Contato pelo site bsagro.agr.br" data-bs-ok="Mensagem enviada! Nosso time retornará pelo e-mail ou telefone informado." novalidate>
      <div class="bs-row">
        <div><label class="form-label" for="ct-nome">Nome completo <span aria-hidden="true">*</span></label><input class="form-input" id="ct-nome" name="nome" type="text" autocomplete="name" required></div>
        <div><label class="form-label" for="ct-email">E-mail <span aria-hidden="true">*</span></label><input class="form-input" id="ct-email" name="email" type="email" autocomplete="email" required></div>
      </div>
      <div class="bs-row">
        <div><label class="form-label" for="ct-tel">WhatsApp ou telefone</label><input class="form-input" id="ct-tel" name="telefone" type="tel" autocomplete="tel" inputmode="tel"></div>
        <div><label class="form-label" for="ct-emp">Empresa ou propriedade</label><input class="form-input" id="ct-emp" name="empresa" type="text" autocomplete="organization"></div>
      </div>
      <div><label class="form-label" for="ct-assunto">Assunto <span aria-hidden="true">*</span></label>
        <select class="form-input" id="ct-assunto" name="assunto" required><option value="">Selecione</option><option>Quero uma análise de crédito</option><option>Dúvida sobre uma solicitação em andamento</option><option>Parcerias com a BS Agro Capital</option><option>Imprensa</option><option>Outros assuntos</option></select></div>
      <div><label class="form-label" for="ct-msg">Mensagem <span aria-hidden="true">*</span></label><textarea class="form-input" id="ct-msg" name="mensagem" required placeholder="Como podemos ajudar?"></textarea></div>
      """ + HONEY + CONSENT + """
      <div class="bs-form-msg" role="status" aria-live="polite"></div>
      <button class="cta-btn cta-btn--block cta-btn--icon" type="submit"><span class="cta-btn__text">Enviar mensagem</span><span class="cta-btn__hover" aria-hidden="true"><span>Enviar mensagem</span><svg viewBox="0 0 24 24" focusable="false"><use href="#i-arrow"/></svg></span></button>
    </form>
  </div>
</div></section>
""",
}

# ------------------------------------------------------------------ CENTRAL DE CONFORMIDADE
DOCS = [
    ("aviso-de-privacidade", "i-key", "Aviso de Privacidade (LGPD)", "Quais dados tratamos, para quê, com quem compartilhamos e como exercer seus direitos."),
    ("termos-de-uso", "i-doc", "Termos de Uso", "Regras de uso do site e da Área do Cliente, responsabilidades e assinatura eletrônica."),
    ("politica-de-cookies", "i-cookie", "Política de Cookies", "Armazenamento no navegador, recursos de terceiros e como mudar suas preferências."),
    ("politica-de-compliance-anticorrupcao", "i-shield", "Compliance e Anticorrupção", "Condutas proibidas, prevenção à lavagem de dinheiro, diligência de parceiros e canal de denúncias."),
    ("politica-de-esg", "i-leaf", "Política de ESG", "Critérios socioambientais e de governança nas operações que estruturamos."),
    ("politica-de-comercializacao", "i-handshake", "Política de Comercialização", "Como oferecemos nossos serviços: transparência, adequação e proteção contra fraudes."),
    ("acessibilidade", "i-access", "Declaração de Acessibilidade", "Recursos de acessibilidade, Libras, tradução e como relatar barreiras."),
    ("autorizacao-consulta-scr", "i-doc", "Autorização de Consulta ao SCR", "Texto da autorização para consulta ao Sistema de Informações de Créditos do Banco Central."),
    ("declaracao-de-veracidade", "i-check", "Declaração de Veracidade", "Compromisso com a exatidão das informações e documentos enviados."),
    ("comunicacoes-de-marketing", "i-mail", "Comunicações e Marketing", "Como consentir e como deixar de receber nossas comunicações."),
]

def _doc_cards():
    return "\n".join('<a class="bs-doc-card revelar" href="{{U:%s}}"><svg aria-hidden="true"><use href="#%s"/></svg><b>%s</b><span>%s</span><i>Ler documento →</i></a>' % d for d in DOCS)

CONFORMIDADE = {
    "slug": "conformidade", "title": "Central de conformidade", "template": "page-no-title",
    "seo_title": "Conformidade, LGPD e integridade — BS Agro Capital",
    "excerpt": "Privacidade, integridade, ESG e transparência comercial: as políticas da BS Agro Capital e os canais para exercer direitos e relatar condutas.",
    "html": """
<section class="bs-sec"><div class="bs-wrap">
  <div class="bs-grid-2 lg:items-start">
    <div class="bs-prose revelar">{{POLICY:conformidade-intro}}</div>
    <div class="grid gap-4 revelar">
      <div class="bs-card bs-card--dark"><h2>Encarregado pelo Tratamento de Dados (LGPD)</h2><p>Solicitações de titulares, dúvidas sobre privacidade e comunicação com a ANPD.</p>
        <ul class="bs-contact-list mt-4"><li><svg aria-hidden="true"><use href="#i-key"/></svg><div><small>E-mail do Encarregado</small><a href="mailto:{{EMAIL:dpo}}">{{EMAIL:dpo}}</a></div></li></ul>
        <a class="bs-btn bs-btn--gold mt-5 w-full" href="{{U:portal-do-titular}}">Abrir o Portal do Titular</a></div>
      <div class="bs-card"><h2>Ouvidoria, Compliance e Anticorrupção</h2><p>Denúncias com sigilo e opção de anonimato, reclamações e dúvidas sobre conduta.</p>
        <ul class="bs-contact-list mt-4"><li><svg aria-hidden="true"><use href="#i-shield"/></svg><div><small>E-mail da Ouvidoria</small><a href="mailto:{{EMAIL:ouvidoria}}">{{EMAIL:ouvidoria}}</a></div></li></ul>
        <a class="bs-btn bs-btn--primary mt-5 w-full" href="{{U:canal-de-integridade}}">Abrir o Canal de Integridade</a></div>
    </div>
  </div>
</div></section>
<section class="bs-sec bg-mesh-light"><div class="bs-wrap">
  <p class="bs-eyebrow">Documentos</p>
  <h2 class="bs-h2 mt-3 mb-8">Políticas e termos</h2>
  <div class="bs-grid-3">
""" + _doc_cards() + """
  </div>
</div></section>
""",
}

# ------------------------------------------------------------------ PORTAL DO TITULAR (LGPD)
PORTAL_TITULAR = {
    "slug": "portal-do-titular", "title": "Portal do Titular (LGPD)", "template": "page-no-title",
    "seo_title": "Portal do Titular — Privacidade e LGPD | BS Agro Capital",
    "excerpt": "Exerça seus direitos previstos na Lei Geral de Proteção de Dados junto ao Encarregado da BS Agro Capital.",
    "html": """
<section class="bs-sec"><div class="bs-wrap bs-grid-2 lg:items-start">
  <div class="bs-prose revelar">{{POLICY:portal-do-titular-intro}}</div>
  <div class="bs-form-card revelar">
    <p class="bs-eyebrow">Requisição do titular</p>
    <h2 class="bs-h2 mt-2 mb-6" style="font-size:1.6rem">Envie sua solicitação ao Encarregado</h2>
    <form class="bs-form" data-bs-canal="dpo" data-bs-prefixo="LGPD" data-bs-assunto="Requisição de titular (LGPD) — bsagro.agr.br" data-bs-ok="Solicitação registrada. O Encarregado responderá pelo e-mail informado, nos prazos da LGPD." novalidate>
      <div class="bs-row">
        <div><label class="form-label" for="lg-nome">Nome completo <span aria-hidden="true">*</span></label><input class="form-input" id="lg-nome" name="nome" type="text" autocomplete="name" required></div>
        <div><label class="form-label" for="lg-email">E-mail para resposta <span aria-hidden="true">*</span></label><input class="form-input" id="lg-email" name="email" type="email" autocomplete="email" required></div>
      </div>
      <div class="bs-row">
        <div><label class="form-label" for="lg-tel">Telefone</label><input class="form-input" id="lg-tel" name="telefone" type="tel" autocomplete="tel"></div>
        <div><label class="form-label" for="lg-rel">Sua relação com a BS Agro <span aria-hidden="true">*</span></label>
          <select class="form-input" id="lg-rel" name="relacao" required><option value="">Selecione</option><option>Cliente</option><option>Pessoa que pediu informações ou análise</option><option>Parceiro ou fornecedor</option><option>Candidato ou colaborador</option><option>Outra</option></select></div>
      </div>
      <div><label class="form-label" for="lg-dir">O que você deseja? <span aria-hidden="true">*</span></label>
        <select class="form-input" id="lg-dir" name="direito" required><option value="">Selecione o direito</option>
          <option>Confirmar se a BS Agro trata meus dados</option><option>Acessar meus dados</option><option>Corrigir dados incompletos ou desatualizados</option>
          <option>Anonimizar, bloquear ou eliminar dados desnecessários</option><option>Portabilidade dos dados</option><option>Eliminar dados tratados com consentimento</option>
          <option>Saber com quem meus dados foram compartilhados</option><option>Revogar consentimento</option><option>Opor-me a um tratamento</option>
          <option>Revisão de decisão automatizada</option><option>Outra solicitação ou dúvida</option></select></div>
      <div><label class="form-label" for="lg-desc">Descreva a solicitação <span aria-hidden="true">*</span></label><textarea class="form-input" id="lg-desc" name="descricao" required placeholder="Informe o que precisa e, se possível, o contexto (ex.: data de contato, número de protocolo)."></textarea></div>
      """ + HONEY + """
      <label class="bs-check"><input type="checkbox" name="declaracao_titular" value="Sim" required><span>Declaro ser o titular dos dados ou seu representante legal e estou ciente de que poderá ser solicitada confirmação de identidade antes do atendimento.</span></label>
      """ + CONSENT + """
      <div class="bs-form-msg" role="status" aria-live="polite"></div>
      <button class="cta-btn cta-btn--block" type="submit"><span class="cta-btn__text">Enviar ao Encarregado</span></button>
      <p class="text-xs text-graphite-500">Não envie documentos por este formulário. Se for necessário confirmar a identidade, o Encarregado indicará um meio seguro. Você também pode escrever para <a class="underline" href="mailto:{{EMAIL:dpo}}">{{EMAIL:dpo}}</a>.</p>
    </form>
  </div>
</div></section>
""",
}

# ------------------------------------------------------------------ CANAL DE INTEGRIDADE (OUVIDORIA)
INTEGRIDADE = {
    "slug": "canal-de-integridade", "title": "Canal de Integridade", "template": "page-no-title",
    "seo_title": "Canal de Integridade — Ouvidoria, Compliance e Anticorrupção | BS Agro Capital",
    "excerpt": "Relate condutas irregulares, suspeitas de fraude ou corrupção e registre reclamações, com sigilo e opção de anonimato.",
    "html": """
<section class="bs-sec"><div class="bs-wrap bs-grid-2 lg:items-start">
  <div class="bs-prose revelar">{{POLICY:canal-de-integridade-intro}}</div>
  <div class="bs-form-card revelar">
    <p class="bs-eyebrow">Ouvidoria e Compliance</p>
    <h2 class="bs-h2 mt-2 mb-6" style="font-size:1.6rem">Registre seu relato</h2>
    <form class="bs-form" data-bs-canal="ouvidoria" data-bs-prefixo="OUV" data-bs-assunto="Canal de Integridade — relato pelo site" data-bs-ok="Relato registrado com sigilo. Se você se identificou, a Ouvidoria poderá retornar pelo contato informado." novalidate>
      <label class="bs-toggle"><input type="checkbox" name="anonimo" value="Sim" data-bs-anonimo><span><b>Quero fazer um relato anônimo</b><br><small>Seus dados de identificação não serão pedidos nem enviados. Guarde o protocolo exibido ao final.</small></span></label>
      <fieldset data-bs-identificacao>
        <legend class="form-legend">Identificação (opcional)</legend>
        <div class="bs-row mt-3">
          <div><label class="form-label" for="ig-nome">Nome</label><input class="form-input" id="ig-nome" name="nome" type="text" autocomplete="name"></div>
          <div><label class="form-label" for="ig-email">E-mail</label><input class="form-input" id="ig-email" name="email" type="email" autocomplete="email"></div>
        </div>
      </fieldset>
      <div><label class="form-label" for="ig-tipo">Tipo de relato <span aria-hidden="true">*</span></label>
        <select class="form-input" id="ig-tipo" name="tipo" required><option value="">Selecione</option>
          <option>Suspeita de corrupção, suborno ou vantagem indevida</option><option>Fraude em documentos ou garantias</option><option>Tentativa de golpe usando o nome da BS Agro Capital</option>
          <option>Conflito de interesses</option><option>Assédio, discriminação ou desrespeito</option><option>Descumprimento de política interna</option>
          <option>Reclamação sobre atendimento</option><option>Sugestão ou elogio</option><option>Outro</option></select></div>
      <div class="bs-row">
        <div><label class="form-label" for="ig-quando">Quando aconteceu?</label><input class="form-input" id="ig-quando" name="quando" type="text" placeholder="Data ou período aproximado"></div>
        <div><label class="form-label" for="ig-onde">Onde / em qual contexto?</label><input class="form-input" id="ig-onde" name="onde" type="text" placeholder="Ex.: atendimento, operação, evento"></div>
      </div>
      <div><label class="form-label" for="ig-env">Pessoas ou empresas envolvidas</label><input class="form-input" id="ig-env" name="envolvidos" type="text"></div>
      <div><label class="form-label" for="ig-desc">Descreva o que aconteceu <span aria-hidden="true">*</span></label><textarea class="form-input" id="ig-desc" name="relato" required placeholder="O que, quem, quando, onde e como. Quanto mais detalhes, melhor a apuração."></textarea></div>
      <div><label class="form-label" for="ig-prov">Há evidências? Onde podem ser encontradas?</label><input class="form-input" id="ig-prov" name="evidencias" type="text" placeholder="Ex.: e-mails, mensagens, documentos, testemunhas"></div>
      """ + HONEY + """
      <label class="bs-check"><input type="checkbox" name="boa_fe" value="Sim" required><span>Declaro que este relato é feito de boa-fé e que as informações são verdadeiras, até onde sei.</span></label>
      <div class="bs-form-msg" role="status" aria-live="polite"></div>
      <button class="cta-btn cta-btn--block" type="submit"><span class="cta-btn__text">Enviar relato</span></button>
      <p class="text-xs text-graphite-500">Garantimos sigilo e vedamos qualquer retaliação a quem relata de boa-fé. Também é possível escrever para <a class="underline" href="mailto:{{EMAIL:ouvidoria}}">{{EMAIL:ouvidoria}}</a>.</p>
    </form>
  </div>
</div></section>
""",
}

# ------------------------------------------------------------------ ÁREA DO CLIENTE
AREA_CLIENTE = {
    "slug": "area-do-cliente", "title": "Área do Cliente", "template": "page-no-title",
    "seo_title": "Área do Cliente — solicite e acompanhe seu crédito | BS Agro Capital",
    "excerpt": "Acesse ou crie seu cadastro para solicitar a análise de crédito, enviar documentos com segurança e acompanhar cada etapa da operação.",
    "keywords": ["área do cliente", "solicitar crédito rural", "análise de crédito agro", "BS Agro Capital"],
    "html": """
<section class="bs-sec" id="portal"><div class="bs-wrap">
  <div class="bs-portal-shell">
{{PORTAL}}
  </div>
  <div class="bs-grid-3 mt-10">
    <div class="bs-channel"><svg aria-hidden="true"><use href="#i-doc"/></svg><h2 class="font-display text-lg font-semibold text-graphite-900">1. Cadastro e solicitação</h2><p>Crie seu acesso, confirme o e-mail e preencha a solicitação em etapas, com rascunho salvo.</p></div>
    <div class="bs-channel"><svg aria-hidden="true"><use href="#i-upload"/></svg><h2 class="font-display text-lg font-semibold text-graphite-900">2. Documentos protegidos</h2><p>Envie os documentos pedidos para o seu caso. Os arquivos ficam em área privada, com acesso restrito.</p></div>
    <div class="bs-channel"><svg aria-hidden="true"><use href="#i-clock"/></svg><h2 class="font-display text-lg font-semibold text-graphite-900">3. Acompanhamento</h2><p>Veja o andamento, responda pendências e assine eletronicamente as autorizações necessárias.</p></div>
  </div>
  <p class="mt-8 text-center text-sm text-graphite-600">Nunca pedimos sua senha por e-mail ou WhatsApp, nem depósito para liberar crédito. Dúvidas? <a class="text-olive-700 underline" href="{{U:contato}}">Fale com o nosso time</a>.</p>
</div></section>
""",
}

PORTAL_FALLBACK = """<div class="bs-card" style="padding:2rem;text-align:center">
  <svg width="44" height="44" aria-hidden="true" style="color:var(--color-gold-700);margin:0 auto 1rem"><use href="#i-lock"/></svg>
  <h2 class="font-display text-2xl font-semibold text-graphite-900" style="text-transform:none;letter-spacing:-.01em;color:var(--color-graphite-900);font-size:1.5rem">A Área do Cliente está sendo ativada</h2>
  <p class="mx-auto mt-3 max-w-xl text-graphite-600">Em breve você poderá criar seu cadastro, enviar a solicitação e acompanhar tudo por aqui. Enquanto isso, fale com um especialista ou deixe seus dados no formulário da página inicial.</p>
  <div class="mt-6 flex flex-wrap justify-center gap-3"><a class="cta-btn cta-btn--whatsapp" href="#" data-bs-wa target="_blank" rel="noopener noreferrer"><span class="cta-btn__text">Falar pelo WhatsApp</span></a><a class="cta-btn cta-btn--dark" href="{{HOME}}#solicitar-analise"><span class="cta-btn__text">Solicitar análise</span></a></div>
</div>"""

# ------------------------------------------------------------------ MATÉRIAS (listagem)
MATERIAS = {
    "slug": "materias", "title": "Matérias", "template": "page-no-title",
    "seo_title": "Matérias sobre crédito e capital no agronegócio — BS Agro Capital",
    "excerpt": "Crédito estruturado, garantias, CPR, recuperação judicial e gestão financeira no agro, explicados de forma direta pela equipe da BS Agro Capital.",
    "html_before": '<section class="bs-sec"><div class="bs-wrap">',
    "html_after": """</div></section>
<section class="bs-sec bg-mesh-light"><div class="bs-wrap bs-grid-2 lg:items-center">
  <div><p class="bs-eyebrow">Da leitura à prática</p><h2 class="bs-h2 mt-3">Quer aplicar isso na sua operação?</h2><p class="bs-lead mt-4">Faça o pré-diagnóstico gratuito ou solicite a análise completa na Área do Cliente.</p></div>
  <div class="flex flex-wrap gap-3 lg:justify-end"><a class="cta-btn cta-btn--icon" href="{{HOME}}#pre-diagnostico"><span class="cta-btn__text">Fazer o pré-diagnóstico</span><span class="cta-btn__hover" aria-hidden="true"><span>Fazer o pré-diagnóstico</span><svg viewBox="0 0 24 24" focusable="false"><use href="#i-arrow"/></svg></span></a><a class="cta-btn cta-btn--dark" href="{{U:area-do-cliente}}" data-ebcr-client-area data-keep-label><span class="cta-btn__text">Área do Cliente</span></a></div>
</div></section>""",
}

# ------------------------------------------------------------------ OBRIGADO
OBRIGADO = {
    "slug": "obrigado", "title": "Solicitação recebida", "template": "page-no-title", "robots": "noindex,follow",
    "seo_title": "Solicitação recebida — BS Agro Capital",
    "excerpt": "Recebemos os dados da sua operação. Um especialista da BS Agro Capital entrará em contato em breve.",
    "html": """
<section class="bs-sec"><div class="bs-wrap bs-grid-2 lg:items-center">
  <div class="revelar">
    <p class="bs-eyebrow">Próximos passos</p>
    <h2 class="bs-h2 mt-3">Obrigado pela confiança.</h2>
    <p class="bs-lead mt-4">Nosso time vai analisar as informações e retornar pelo WhatsApp ou e-mail informados. Para agilizar, você já pode adiantar o cadastro na Área do Cliente e separar os documentos básicos da operação.</p>
    <div class="mt-8 flex flex-wrap gap-3"><a class="cta-btn cta-btn--icon" href="{{U:area-do-cliente}}" data-ebcr-client-area data-keep-label><span class="cta-btn__text">Adiantar na Área do Cliente</span><span class="cta-btn__hover" aria-hidden="true"><span>Adiantar na Área do Cliente</span><svg viewBox="0 0 24 24" focusable="false"><use href="#i-arrow"/></svg></span></a><a class="cta-btn cta-btn--dark" href="#" data-bs-wa target="_blank" rel="noopener noreferrer"><span class="cta-btn__text">Falar pelo WhatsApp</span></a></div>
  </div>
  <div class="bs-card revelar"><h2>Enquanto isso, leia</h2><p>Entenda como é feito o diagnóstico financeiro e o que as fontes de capital avaliam em uma operação.</p><a class="bs-btn bs-btn--primary mt-5" href="{{U:materias}}">Ver as matérias</a></div>
</div></section>
""",
}

LANDING_PAGES = [CONTATO, CONFORMIDADE, PORTAL_TITULAR, INTEGRIDADE, AREA_CLIENTE, OBRIGADO]

POLICY_PAGES = ["aviso-de-privacidade", "termos-de-uso", "politica-de-cookies", "politica-de-compliance-anticorrupcao",
                "politica-de-esg", "politica-de-comercializacao", "acessibilidade", "autorizacao-consulta-scr",
                "declaracao-de-veracidade", "comunicacoes-de-marketing"]
