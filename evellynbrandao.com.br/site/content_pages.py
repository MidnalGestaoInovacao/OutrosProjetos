# -*- coding: utf-8 -*-
"""Conteúdo das páginas institucionais (HTML). Placeholders: {{IMG:nome}}, {{FAQ}}, {{POSTS_GRID}}, {{INTRO:chave}}."""

def li(icon, strong, text):
    return ('<li style="display:flex;gap:12px;align-items:flex-start"><svg style="width:20px;height:20px;color:var(--eb-gold);flex:none;margin-top:3px"><use href="#i-%s"/></svg><span><strong style="color:var(--eb-txt)">%s</strong> %s</span></li>' % (icon, strong, text))

CHECKLIST_STYLE = 'style="list-style:none;padding:0;margin:22px 0;display:grid;gap:12px;color:var(--eb-mut)"'

# ------------------------------------------------------------------ SOBRE
SOBRE = {
  "slug": "sobre", "title": "Sobre Évellyn Brandão", "template": "page-no-title",
  "excerpt": "Executiva com 10 anos de mercado financeiro, 8 deles no Itaú Unibanco. CEO e Fundadora da BS Agro Capital. Crédito não é sorte: crédito é estrutura.",
  "html": """
<section class="eb-sec">
  <div class="eb-wrap">
    <div class="eb-grid eb-grid--2" style="gap:56px;align-items:center">
      <div class="eb-rev"><img src="{{IMG:campo}}" alt="Évellyn Brandão em lavoura de milho ao entardecer" width="1120" height="1400" style="border-radius:var(--eb-r-lg);border:1px solid var(--eb-line2);box-shadow:var(--eb-shadow),0 0 60px rgba(212,175,55,.12)"></div>
      <div class="eb-rev eb-rev--d1">
        <p class="eb-kicker">Quem é Évellyn</p>
        <h2 class="eb-h2">Uma executiva que aprendeu crédito onde ele é decidido</h2>
        <p class="eb-lead">Natural de Curvelo, no interior de Minas Gerais, Évellyn Brandão construiu uma trajetória de dez anos no mercado financeiro — oito deles no Itaú Unibanco, o maior banco da América Latina — atuando nos segmentos empresarial e agronegócio.</p>
        <p class="eb-lead">Ao longo da carreira, liderou carteiras estratégicas, relacionamentos comerciais e equipes, com resultados consistentes em performance, crescimento de negócios e experiência do cliente. Em 2026, transformou essa vivência em empresa: a BS Agro Capital, onde atua na originação e estruturação de operações de crédito, conectando produtores rurais e empresas do agro a bancos, cooperativas, fundos de investimento e diferentes fontes de capital.</p>
        <div class="eb-chips eb-mt24">
          <span class="eb-chip"><svg><use href="#i-people"/></svg>Gestão de equipes</span>
          <span class="eb-chip"><svg><use href="#i-handshake"/></svg>Negociação de contratos</span>
          <span class="eb-chip"><svg><use href="#i-coin"/></svg>Análise de crédito</span>
          <span class="eb-chip"><svg><use href="#i-shield"/></svg>Gestão de risco financeiro</span>
          <span class="eb-chip"><svg><use href="#i-star"/></svg>Experiência do cliente</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="eb-sec eb-sec--alt" id="trajetoria">
  <div class="eb-wrap">
    <div class="eb-grid eb-grid--2" style="gap:56px;align-items:start">
      <div class="eb-rev">
        <p class="eb-kicker">Trajetória</p>
        <h2 class="eb-h2">Degrau a degrau, sempre perto do cliente</h2>
        <p class="eb-lead">Cada função foi uma escola: atendimento, agência, carteira PJ, liderança de time, consultoria para clientes corporativos e gerência de negócios Agro. O fio condutor nunca mudou — centralidade no cliente e obsessão por resultado bem construído.</p>
        <blockquote class="eb-quote eb-mt40"><p>Ser obstinado por encantar o cliente. Em cada transformação, o compromisso se reafirma: centralidade no cliente.</p><cite>Évellyn Brandão</cite></blockquote>
      </div>
      <div class="eb-tl eb-rev eb-rev--d1">
        <div class="eb-tl-it"><time>fev 2026 — hoje · Anápolis/GO</time><b>CEO e Fundadora · BS Agro Capital</b><p>Originação e estruturação de operações de crédito para produtores rurais e empresas do agro, conectando o campo a bancos, cooperativas, fundos e outras fontes de capital.</p></div>
        <div class="eb-tl-it"><time>ago 2025 — fev 2026 · Goiânia e região</time><b>Gerente de Negócios Agro · Itaú Unibanco</b><p>Crédito, relacionamento e soluções financeiras para o agronegócio goiano.</p></div>
        <div class="eb-tl-it"><time>mar 2024 — set 2025 · Belo Horizonte/MG</time><b>Gerente de Negócios PJ · Itaú Unibanco</b><p>Consultoria estratégica para clientes corporativos, gestão de carteira, análise de crédito e gestão de risco financeiro.</p></div>
        <div class="eb-tl-it"><time>fev 2023 — fev 2024 · Sabará e Ribeirão das Neves/MG</time><b>Gerente de Atendimento · Itaú Unibanco</b><p>Liderança e desenvolvimento de um time de 16 pessoas, com foco na excelência do atendimento e na satisfação do cliente.</p></div>
        <div class="eb-tl-it"><time>abr 2021 — fev 2023 · Sete Lagoas/MG</time><b>Gerente de Relacionamento PJ · Itaú Unibanco</b><p>Gestão estratégica de carteira e consultoria ao cliente: atendimento consultivo e soluções financeiras que geram valor.</p></div>
        <div class="eb-tl-it"><time>mar 2019 — abr 2021 · Sete Lagoas/MG</time><b>Agente de Negócios · Itaú Unibanco</b><p>Rede de agências: atendimento de excelência e soluções bancárias alinhadas aos objetivos de cada cliente.</p></div>
        <div class="eb-tl-it"><time>mar 2018 — fev 2019 · Sete Lagoas/MG</time><b>Estagiária em Atendimento e Relacionamento · Itaú Unibanco</b><p>O começo de tudo: uma experiência de atendimento diferenciada e apoio às necessidades financeiras dos clientes.</p></div>
        <div class="eb-tl-it"><time>2015 — 2018 · Sete Lagoas/MG</time><b>Primeiros passos</b><p>Promotora de Vendas na Proativa Contact Center e estagiária de operações na Sete Lagoas Transportes: escuta, disciplina e foco em resultado desde cedo.</p></div>
      </div>
    </div>
  </div>
</section>

<section class="eb-sec" id="formacao">
  <div class="eb-wrap">
    <div class="eb-center"><p class="eb-kicker">Formação &amp; certificações</p><h2 class="eb-h2">Técnica atualizada a serviço do campo</h2></div>
    <div class="eb-grid eb-grid--4 eb-mt40">
      <div class="eb-card eb-rev"><span class="eb-ic"><svg><use href="#i-star"/></svg></span><h3 class="eb-h3">MBA · PUCRS</h3><p>Vendas | Negociação e Resultados em Alta Performance (2022–2024). PNL, persuasão, gestão comercial, relacionamento e fidelização de clientes, prospecção e conversão.</p></div>
      <div class="eb-card eb-rev eb-rev--d1"><span class="eb-ic"><svg><use href="#i-doc"/></svg></span><h3 class="eb-h3">Administração</h3><p>Graduação em Administração de Empresas pela Faculdade Ciências da Vida (2016–2021): a base de gestão que sustenta cada decisão.</p></div>
      <div class="eb-card eb-rev eb-rev--d2"><span class="eb-ic"><svg><use href="#i-wheat"/></svg></span><h3 class="eb-h3">Crédito Rural</h3><p>Certificação pela Academia de Negócios Agro (2026): linhas, garantias, Plano Safra e a mecânica do crédito que move o campo.</p></div>
      <div class="eb-card eb-rev eb-rev--d3"><span class="eb-ic"><svg><use href="#i-chart"/></svg></span><h3 class="eb-h3">Análise de Crédito Agro</h3><p>Certificação pela Capital Escola de Finanças: leitura de balanço, fluxo de caixa da safra e risco de crédito no agronegócio.</p></div>
    </div>
  </div>
</section>

<section class="eb-sec eb-sec--panel" id="reconhecimentos">
  <div class="eb-wrap">
    <div class="eb-grid eb-grid--2" style="gap:56px;align-items:center">
      <div class="eb-rev">
        <p class="eb-kicker">Reconhecimentos</p>
        <h2 class="eb-h2">Resultados que o mercado enxergou</h2>
        <p class="eb-lead">Reconhecimentos não se pedem — se constroem, cliente a cliente. Alguns marcos recentes da trajetória de Évellyn:</p>
        <ul """ + CHECKLIST_STYLE + """>
          """ + li("star", "Destaque em Satisfação de Clientes — 1º trimestre de 2025 (Itaú Empresas).", "Reconhecimento por experiência do cliente e alta performance.") + """
          """ + li("globe", "FEBRABAN TECH 2025.", "Participação no maior evento de tecnologia e inovação do sistema financeiro brasileiro.") + """
          """ + li("people", "Liderança de equipe.", "Gestão e desenvolvimento de um time de 16 pessoas com foco em excelência no atendimento.") + """
          """ + li("handshake", "Recomendações profissionais.", "Competências reconhecidas por colegas e clientes em negociação de contratos e gestão de atendimento.") + """
        </ul>
      </div>
      <div class="eb-rev eb-rev--d1"><img src="{{IMG:visual-sobre}}" alt="Composição dourada com a frase Crédito não é sorte, crédito é estrutura" width="1600" height="1000" loading="lazy" style="border-radius:var(--eb-r-lg);border:1px solid var(--eb-line2);box-shadow:var(--eb-shadow)"></div>
    </div>
  </div>
</section>

<section class="eb-sec" id="valores">
  <div class="eb-wrap">
    <div class="eb-center"><p class="eb-kicker">Valores</p><h2 class="eb-h2">O que guia cada decisão</h2></div>
    <div class="eb-grid eb-grid--3 eb-mt40">
      <div class="eb-card eb-rev"><span class="eb-ic"><svg><use href="#i-target"/></svg></span><h3 class="eb-h3">Estrutura antes da pressa</h3><p>Operação bem desenhada é operação aprovada. Preparamos cada detalhe antes de sentar à mesa.</p></div>
      <div class="eb-card eb-rev eb-rev--d1"><span class="eb-ic"><svg><use href="#i-shield"/></svg></span><h3 class="eb-h3">Integridade inegociável</h3><p>Transparência com clientes e instituições, compliance e proteção de dados como parte do método — não como discurso.</p></div>
      <div class="eb-card eb-rev eb-rev--d2"><span class="eb-ic"><svg><use href="#i-leaf"/></svg></span><h3 class="eb-h3">Cuidado com pessoas</h3><p>Voluntária no Projeto Empatia, dedicado ao cuidado com idosos. Porque prosperidade de verdade inclui quem está ao redor.</p></div>
    </div>
    <div class="eb-center eb-mt64"><a class="eb-btn eb-btn--p" href="/contato/">Conversar com Évellyn</a> &nbsp; <a class="eb-btn eb-btn--g" href="https://www.linkedin.com/in/evellynbrandao/" target="_blank" rel="noopener"><svg><use href="#i-linkedin"/></svg> Seguir no LinkedIn</a></div>
  </div>
</section>
"""}

# ------------------------------------------------------------------ SOLUÇÕES (visão geral)
SOLUCOES = {
  "slug": "solucoes", "title": "Soluções", "template": "page-no-title",
  "excerpt": "Crédito rural, estruturação de operações, fontes de capital, consultoria financeira, renegociação e desenvolvimento de equipes: capital com estratégia, do diagnóstico à liberação.",
  "html": """
<section class="eb-sec">
  <div class="eb-wrap">
    <div class="eb-center">
      <p class="eb-kicker">Soluções</p>
      <h2 class="eb-h2">O que a sua operação precisa,<br>na ordem em que precisa</h2>
      <p class="eb-lead">Cada solução nasce da mesma pergunta: o que o comitê de crédito precisa ver para dizer sim — e como a sua fazenda ou empresa chega lá com as melhores condições?</p>
    </div>
    <div class="eb-grid eb-grid--3 eb-mt40">
      <a class="eb-card eb-card--link eb-rev" href="/solucoes/credito-rural/"><span class="eb-ic eb-ic--lg"><svg><use href="#i-wheat"/></svg></span><h3 class="eb-h3">Crédito Rural &amp; Plano Safra</h3><p>Custeio, investimento e comercialização. Pronamp, Moderfrota, linhas com recursos livres e direcionados — a linha certa para cada momento.</p><span class="eb-more">Ver solução <svg><use href="#i-arrow"/></svg></span></a>
      <a class="eb-card eb-card--link eb-rev eb-rev--d1" href="/solucoes/estruturacao-de-operacoes/"><span class="eb-ic eb-ic--lg"><svg><use href="#i-doc"/></svg></span><h3 class="eb-h3">Estruturação de Operações</h3><p>CPR física e financeira, Barter, Fiagro, CDCA/CRA e operações sob medida com garantias e fluxo bem desenhados.</p><span class="eb-more">Ver solução <svg><use href="#i-arrow"/></svg></span></a>
      <a class="eb-card eb-card--link eb-rev eb-rev--d2" href="/solucoes/fontes-de-capital/"><span class="eb-ic eb-ic--lg"><svg><use href="#i-bank"/></svg></span><h3 class="eb-h3">Fontes de Capital</h3><p>Bancos, cooperativas de crédito, fundos de investimento, fintechs e investidores: acesso a quem tem apetite pelo seu projeto.</p><span class="eb-more">Ver solução <svg><use href="#i-arrow"/></svg></span></a>
      <a class="eb-card eb-card--link eb-rev" href="/solucoes/consultoria-financeira-estrategica/"><span class="eb-ic eb-ic--lg"><svg><use href="#i-chart"/></svg></span><h3 class="eb-h3">Consultoria Financeira Estratégica</h3><p>Diagnóstico, fluxo de caixa da safra, custo de capital e plano de captação para crescer com previsibilidade.</p><span class="eb-more">Ver solução <svg><use href="#i-arrow"/></svg></span></a>
      <a class="eb-card eb-card--link eb-rev eb-rev--d1" href="/solucoes/renegociacao-e-reestruturacao/"><span class="eb-ic eb-ic--lg"><svg><use href="#i-scale"/></svg></span><h3 class="eb-h3">Renegociação &amp; Reestruturação</h3><p>Reorganize passivos, alongue prazos e recupere o fôlego da operação com um plano que o credor entende.</p><span class="eb-more">Ver solução <svg><use href="#i-arrow"/></svg></span></a>
      <a class="eb-card eb-card--link eb-rev eb-rev--d2" href="/solucoes/treinamento-de-equipes-comerciais/"><span class="eb-ic eb-ic--lg"><svg><use href="#i-people"/></svg></span><h3 class="eb-h3">Treinamento de Equipes Comerciais</h3><p>Vendas, crédito e relacionamento em alta performance, com o método que Évellyn aplicou liderando times em banco.</p><span class="eb-more">Ver solução <svg><use href="#i-arrow"/></svg></span></a>
      <a class="eb-card eb-card--link eb-rev" href="/solucoes/mentoria-e-palestras/"><span class="eb-ic eb-ic--lg"><svg><use href="#i-star"/></svg></span><h3 class="eb-h3">Mentoria &amp; Palestras</h3><p>Negociação, liderança e experiência do cliente para eventos, cooperativas, revendas e empresas do agro.</p><span class="eb-more">Ver solução <svg><use href="#i-arrow"/></svg></span></a>
      <a class="eb-card eb-card--link eb-card--gold eb-rev eb-rev--d1" href="/contato/"><span class="eb-ic eb-ic--lg"><svg><use href="#i-clock"/></svg></span><h3 class="eb-h3">Diagnóstico gratuito de 45 minutos</h3><p>Comece por aqui: uma conversa objetiva para mapear riscos, oportunidades e as fontes de capital mais aderentes ao seu negócio.</p><span class="eb-more">Agendar agora <svg><use href="#i-arrow"/></svg></span></a>
    </div>
  </div>
</section>

<section class="eb-sec eb-sec--alt" id="para-quem">
  <div class="eb-wrap">
    <div class="eb-grid eb-grid--2" style="gap:56px;align-items:center">
      <div class="eb-rev">
        <p class="eb-kicker">Para quem</p>
        <h2 class="eb-h2">Do produtor familiar à agroindústria</h2>
        <p class="eb-lead">O agro não é um único cliente — e cada perfil pede uma estrutura diferente de capital.</p>
        <ul """ + CHECKLIST_STYLE + """>
          """ + li("wheat", "Produtores rurais", "que precisam de custeio, investimento em máquinas, irrigação, armazenagem ou expansão de área.") + """
          """ + li("bank", "Cooperativas e revendas de insumos", "que financiam a safra dos cooperados e clientes e buscam funding e instrumentos como CPR e Barter.") + """
          """ + li("chart", "Agroindústrias e empresas do agro", "com necessidade de capital de giro, comercialização e estruturas via mercado de capitais.") + """
          """ + li("handshake", "Investidores e instituições financeiras", "que buscam originação qualificada e operações bem estruturadas no agronegócio.") + """
        </ul>
      </div>
      <div class="eb-steps eb-rev eb-rev--d1" style="grid-template-columns:1fr 1fr">
        <div class="eb-step"><b>Diagnóstico</b><p>Entendemos operação, fluxo de caixa, garantias e objetivo.</p></div>
        <div class="eb-step"><b>Estruturação</b><p>Instrumento, prazo, garantias e documentação certos.</p></div>
        <div class="eb-step"><b>Originação</b><p>Apresentação às fontes mais aderentes e negociação.</p></div>
        <div class="eb-step"><b>Acompanhamento</b><p>Da aprovação à liberação, com relacionamento contínuo.</p></div>
      </div>
    </div>
  </div>
</section>

<section class="eb-sec" id="faq">
  <div class="eb-wrap">
    <div class="eb-center"><p class="eb-kicker">Dúvidas frequentes</p><h2 class="eb-h2">Respostas diretas antes da primeira conversa</h2></div>
    <div class="eb-faq eb-mt40" style="max-width:1000px;margin-left:auto;margin-right:auto">
{{FAQ}}
    </div>
    <div class="eb-center eb-mt64"><a class="eb-btn eb-btn--p" href="/contato/">Agendar diagnóstico gratuito</a></div>
  </div>
</section>
"""}

# ------------------------------------------------------------------ Páginas de solução (editoriais, filhas de /solucoes/)
def solution(slug, title, excerpt, icon, intro, o_que, para_quem, como, resultados, extra_note=""):
    html = "<p><strong>%s</strong></p>\n" % intro
    html += "<h2>O que fazemos</h2>\n<ul>\n" + "".join("<li>%s</li>\n" % x for x in o_que) + "</ul>\n"
    html += "<h2>Para quem é</h2>\n<ul>\n" + "".join("<li>%s</li>\n" % x for x in para_quem) + "</ul>\n"
    html += "<h2>Como funciona</h2>\n<ol>\n" + "".join("<li>%s</li>\n" % x for x in como) + "</ol>\n"
    html += "<h2>O que você pode esperar</h2>\n<ul>\n" + "".join("<li>%s</li>\n" % x for x in resultados) + "</ul>\n"
    if extra_note:
        html += '<div class="eb-box eb-box--tip"><h3>💡 Bom saber</h3><p>%s</p></div>\n' % extra_note
    html += ('<div class="eb-box eb-box--brand"><h3>😉 E a Évellyn com isso?</h3><p>Évellyn conhece o crédito pelos dois lados da mesa: oito anos dentro do Itaú Unibanco, hoje à frente da BS Agro Capital. '
             'Isso significa operações apresentadas do jeito que o comitê precisa ver — e um relacionamento que não termina na liberação. <a href="/contato/">Agende um diagnóstico gratuito</a> e comece com estrutura.</p></div>\n')
    html += '<div class="eb-box eb-box--note"><h3>✍️ Nota</h3><p>Nenhuma operação de crédito tem aprovação garantida: a decisão é sempre da instituição financeira. O nosso compromisso é com a qualidade da estrutura, da documentação e da negociação.</p></div>\n'
    return {"slug": slug, "title": title, "template": "page", "parent": "solucoes", "excerpt": excerpt, "icon": icon, "html": html}

SOLUTIONS = [
 solution("credito-rural", "Crédito Rural & Plano Safra",
  "Custeio, investimento e comercialização com a linha certa para cada momento da fazenda — Plano Safra, Pronamp, Moderfrota e recursos livres.", "wheat",
  "O Plano Safra abre a porta. Chegar preparado é o que faz você atravessá-la com as melhores condições.",
  ["Mapeamento das linhas de crédito rural aderentes à sua atividade: custeio agrícola e pecuário, investimento (máquinas, irrigação, armazenagem, correção de solo) e comercialização.",
   "Enquadramento em programas do Plano Safra — Pronamp, Moderfrota, Moderagro, RenovAgro/ABC+ e linhas de recursos livres — sempre conforme as regras vigentes do Manual de Crédito Rural (MCR).",
   "Organização da documentação e do projeto técnico que a instituição precisa avaliar.",
   "Negociação de prazos, carência e garantias compatíveis com o ciclo da produção."],
  ["Produtores rurais pessoa física e jurídica, de pequenos a grandes.", "Produtores em expansão de área, renovação de frota ou investimento em tecnologia.", "Cooperados e clientes de revendas que precisam de custeio para a próxima safra."],
  ["Diagnóstico: entendemos ciclo produtivo, receitas, custos e garantias disponíveis.", "Enquadramento: identificamos as linhas e programas com melhor aderência.", "Estruturação: montamos o dossiê (documentos, projeto, fluxo de caixa) do jeito que o comitê precisa ver.", "Originação: apresentamos a operação às instituições com maior apetite e negociamos as condições.", "Acompanhamento: seguimos com você até a liberação — e preparamos a próxima safra."],
  ["Clareza sobre quais linhas fazem sentido para o seu momento.", "Dossiê completo, sem idas e vindas que atrasam a decisão.", "Condições negociadas com base em estrutura, não em sorte.", "Relacionamento contínuo com as instituições para as safras seguintes."],
  "Os limites, taxas e prazos das linhas do Plano Safra são definidos a cada ano-safra pelo Governo Federal e pelo Conselho Monetário Nacional. Por isso, o enquadramento correto e o timing da solicitação fazem diferença real no custo do dinheiro."),
 solution("estruturacao-de-operacoes", "Estruturação de Operações",
  "CPR física e financeira, Barter, Fiagro, CDCA/CRA e operações sob medida — instrumentos do mercado de capitais a serviço do campo.", "doc",
  "Quando o crédito bancário tradicional não basta, o mercado de capitais oferece caminhos. Estruturar bem é o que transforma um instrumento jurídico em capital na conta.",
  ["Estruturação de Cédulas de Produto Rural (CPR) físicas e financeiras, com garantias e fluxo de pagamento compatíveis com a safra.",
   "Operações de Barter (troca de insumos por produção) desenhadas para proteger a margem do produtor.",
   "Conexão com Fiagros, fundos de crédito e securitizadoras para operações via CDCA, CRA e outros títulos do agro.",
   "Modelagem de garantias: penhor de safra, alienação fiduciária, hipoteca, cessão de recebíveis e seguros."],
  ["Produtores e grupos que precisam de volumes ou prazos além do crédito rural tradicional.", "Revendas, cooperativas e tradings que financiam sua base de clientes.", "Agroindústrias que buscam capital de giro estruturado e melhor custo."],
  ["Diagnóstico da necessidade: volume, prazo, moeda, sazonalidade e garantias.", "Escolha do instrumento: CPR, Barter, CDCA, CRA ou combinação.", "Modelagem financeira e jurídica com parceiros especializados.", "Apresentação a investidores, fundos e instituições com apetite pela operação.", "Acompanhamento da emissão, liquidação e monitoramento até o vencimento."],
  ["Estruturas que refletem a realidade do seu ciclo produtivo.", "Acesso a capital além do balcão bancário.", "Menos surpresas: riscos e garantias claros para todas as partes.", "Um histórico de operações que abre portas para as próximas."],
  "A CPR foi criada pela Lei 8.929/1994 e ganhou força com a Lei do Agro (13.986/2020), que ampliou seu uso e permitiu registro eletrônico — o que aumentou a segurança para investidores e reduziu o custo para o produtor."),
 solution("fontes-de-capital", "Fontes de Capital",
  "Bancos, cooperativas de crédito, fundos de investimento, fintechs e investidores conectados ao seu projeto — a fonte certa muda o custo, o prazo e a chance de aprovação.", "bank",
  "Não existe a melhor fonte de capital. Existe a mais aderente ao seu momento — e saber qual é exige conhecer o apetite de cada uma.",
  ["Mapeamento das instituições com apetite pelo seu perfil: bancos públicos e privados, cooperativas de crédito, fundos (Fiagro e fundos de crédito), fintechs e investidores.",
   "Relacionamento institucional ativo: apresentação qualificada do seu projeto a quem decide.",
   "Comparação transparente de propostas — custo total, prazo, garantias e covenants.",
   "Estratégia de diversificação para reduzir a dependência de uma única fonte."],
  ["Produtores que hoje dependem de um único banco.", "Empresas do agro em expansão que precisam ampliar limites.", "Cooperativas e revendas em busca de funding para financiar sua base."],
  ["Diagnóstico do seu histórico de crédito e das fontes já utilizadas.", "Mapeamento do apetite de mercado para o seu perfil e região.", "Preparação do material de apresentação (memorando, fluxo de caixa, garantias).", "Rodada de apresentação e negociação com as instituições selecionadas.", "Escolha assistida da melhor proposta e acompanhamento até a liberação."],
  ["Mais alternativas na mesa — e poder de negociação.", "Condições comparadas com clareza, sem letras miúdas.", "Relacionamentos que continuam disponíveis para as próximas operações.", "Menos concentração de risco na sua estrutura de capital."],
  "As cooperativas de crédito são reguladas pelo Banco Central e, em muitas regiões do agro, oferecem proximidade e condições competitivas. Já os fundos e investidores costumam olhar para operações estruturadas, com garantias e fluxo bem definidos."),
 solution("consultoria-financeira-estrategica", "Consultoria Financeira Estratégica",
  "Diagnóstico, fluxo de caixa da safra, custo de capital e plano de captação: decisões financeiras com visão de longo prazo para a fazenda e para a empresa do agro.", "chart",
  "Crédito bom começa muito antes do pedido. Começa em uma operação que sabe o quanto custa, o quanto rende e quando o caixa aperta.",
  ["Diagnóstico financeiro da operação: receitas, custos, margens, endividamento e sazonalidade.",
   "Construção do fluxo de caixa da safra — o mapa que o banco quer ver antes de dizer sim.",
   "Análise do custo de capital e da estrutura de dívida atual.",
   "Plano de captação: quanto, quando, com qual instrumento e com qual fonte."],
  ["Produtores que querem crescer com previsibilidade.", "Empresas do agro que precisam profissionalizar a gestão financeira.", "Famílias empresárias em processo de sucessão ou expansão."],
  ["Imersão na operação e nos números (com o apoio do seu contador ou consultor técnico).", "Diagnóstico com prioridades claras: o que resolver primeiro.", "Modelagem do fluxo de caixa e dos cenários (safra boa, média e ruim).", "Plano de captação e de estrutura de capital para os próximos ciclos.", "Acompanhamento periódico dos indicadores e ajustes de rota."],
  ["Números organizados, que sustentam qualquer negociação.", "Decisões de investimento com base em cenários, não em intuição.", "Redução do custo médio do capital ao longo do tempo.", "Uma operação mais atraente para bancos, fundos e investidores."],
  "Instituições financeiras avaliam a capacidade de pagamento com base em fluxo de caixa projetado e histórico. Uma projeção bem construída, com premissas claras, costuma valer mais do que qualquer argumento verbal."),
 solution("renegociacao-e-reestruturacao", "Renegociação & Reestruturação",
  "Reorganize passivos, alongue prazos e recupere o fôlego da operação com um plano que o credor entende — sentar à mesa com estrutura.", "scale",
  "Uma safra ruim não define o seu negócio. A forma como você renegocia, sim.",
  ["Diagnóstico do endividamento: credores, vencimentos, taxas, garantias e cláusulas.",
   "Construção do plano de reestruturação: alongamento, carência, consolidação de dívidas e novas garantias.",
   "Preparação da proposta e do fluxo de caixa que demonstram capacidade de pagamento.",
   "Condução das conversas com bancos, cooperativas e fornecedores, com transparência e método."],
  ["Produtores com vencimentos concentrados ou descasados do ciclo da safra.", "Empresas do agro que precisam consolidar dívidas e reduzir o custo do passivo.", "Operações afetadas por quebra de safra, preço ou clima."],
  ["Levantamento completo do passivo e das garantias.", "Modelagem de cenários e definição da proposta viável.", "Preparação do dossiê: fluxo de caixa, plano de recuperação e comprovações.", "Negociação estruturada com cada credor, na ordem certa.", "Formalização e acompanhamento do novo cronograma."],
  ["Um plano realista, que cabe no fluxo de caixa da operação.", "Diálogo profissional com os credores, sem improviso.", "Preservação do relacionamento bancário para o futuro.", "Fôlego para voltar a investir na atividade."],
  "Renegociações bem-sucedidas costumam começar cedo — antes do vencimento e antes de qualquer inadimplência. Quanto mais cedo a conversa, mais alternativas ficam disponíveis."),
 solution("treinamento-de-equipes-comerciais", "Treinamento de Equipes Comerciais",
  "Vendas, crédito e relacionamento em alta performance para cooperativas, revendas, agroindústrias e instituições financeiras, com o método que Évellyn aplicou liderando times em banco.", "people",
  "Time comercial de alta performance não nasce pronto. É desenvolvido, treinado e liderado com método.",
  ["Programas de desenvolvimento para equipes de vendas, crédito e relacionamento no agro.",
   "Treinamentos práticos: prospecção, atendimento consultivo, negociação, gestão de carteira e fidelização.",
   "Formação de lideranças comerciais: gestão de equipes, gestão de conflitos e cultura de resultado.",
   "Rotinas de gestão: metas, indicadores, rituais de acompanhamento e feedback."],
  ["Cooperativas e revendas que querem elevar o nível do time comercial.", "Instituições financeiras e correspondentes com foco no agro.", "Agroindústrias e empresas de insumos com equipes de campo."],
  ["Diagnóstico da equipe e dos processos comerciais atuais.", "Desenho do programa sob medida (workshops, treinamentos, mentorias).", "Execução presencial ou on-line, com casos reais do agro.", "Implantação das rotinas de gestão e indicadores.", "Acompanhamento dos resultados e ajustes."],
  ["Equipes mais consultivas, que vendem valor e não preço.", "Líderes preparados para desenvolver pessoas e resolver conflitos.", "Rotinas claras que sustentam performance ao longo do tempo.", "Clientes mais satisfeitos — e mais fiéis."],
  "Évellyn liderou um time de 16 pessoas em agência, foi reconhecida como destaque em satisfação de clientes e cursou MBA em Vendas, Negociação e Resultados em Alta Performance na PUCRS. É essa combinação de prática e técnica que chega ao seu time."),
 solution("mentoria-e-palestras", "Mentoria & Palestras",
  "Negociação, liderança, crédito e experiência do cliente para eventos, cooperativas, revendas e empresas do agro — conteúdo com vivência real de mercado.", "star",
  "Uma boa palestra inspira. Uma boa mentoria transforma. Évellyn entrega as duas coisas com a credibilidade de quem viveu o que ensina.",
  ["Palestras para eventos do agro, cooperativas, associações, feiras e instituições financeiras.",
   "Mentorias individuais e em grupo para produtores, empreendedores do agro e lideranças comerciais.",
   "Temas: crédito e estrutura de capital no agro, negociação e persuasão ética, liderança de equipes de alta performance, experiência do cliente e mulheres no agro.",
   "Formatos presenciais e on-line, adaptados ao público e ao objetivo do evento."],
  ["Organizadores de eventos, feiras e dias de campo.", "Cooperativas, sindicatos rurais e associações de produtores.", "Empresas que querem desenvolver lideranças e times comerciais."],
  ["Briefing: público, objetivo e mensagem-chave.", "Personalização do conteúdo com casos e dados relevantes para o setor.", "Entrega da palestra ou do ciclo de mentoria.", "Materiais de apoio e próximos passos para os participantes."],
  ["Conteúdo prático, com exemplos reais do crédito e do agro.", "Público engajado e com ferramentas para aplicar no dia seguinte.", "Uma voz feminina de referência em finanças para o agronegócio.", "Conexões que continuam depois do evento."],
  "Solicite a disponibilidade de agenda e temas pelo formulário de contato ou pelo WhatsApp. Cada proposta é elaborada de forma transparente, de acordo com formato, duração e localidade."),
]

# ------------------------------------------------------------------ BS AGRO CAPITAL
BS = {
  "slug": "bs-agro-capital", "title": "BS Agro Capital", "template": "page-no-title",
  "excerpt": "Soluções financeiras para o agronegócio. Estratégia, capital e parcerias para um campo de maiores resultados — fundada por Évellyn Brandão em Anápolis/GO.",
  "html": """
<section class="eb-sec">
  <div class="eb-wrap">
    <div class="eb-grid eb-grid--2" style="gap:56px;align-items:center">
      <div class="eb-rev">
        <p class="eb-kicker">A empresa</p>
        <h2 class="eb-h2">Nascemos para colocar estrutura<br>entre o campo e o capital</h2>
        <p class="eb-lead">A BS Agro Capital é uma empresa de soluções financeiras para o agronegócio, fundada em 2026 por Évellyn Brandão, em Anápolis/GO. Atuamos na originação e estruturação de operações de crédito, conectando produtores rurais e empresas do agro a bancos, cooperativas, fundos de investimento e diferentes fontes de capital.</p>
        <p class="eb-lead">Nossa atuação combina visão estratégica, crédito, relacionamento institucional e desenvolvimento de negócios, com foco na construção de parcerias de longo prazo e geração de valor.</p>
        <div class="eb-row eb-mt24"><a class="eb-btn eb-btn--p" href="/contato/">Falar com a BS Agro Capital</a><a class="eb-btn eb-btn--g" href="/solucoes/">Ver soluções</a></div>
      </div>
      <div class="eb-rev eb-rev--d1"><img src="{{IMG:visual-bs}}" alt="BS Agro Capital — estratégia, capital e parcerias" width="1600" height="1000" style="border-radius:var(--eb-r-lg);border:1px solid var(--eb-line2);box-shadow:var(--eb-shadow)"></div>
    </div>
  </div>
</section>

<section class="eb-sec eb-sec--tight eb-sec--alt">
  <div class="eb-wrap">
    <div class="eb-stats">
      <div class="eb-stat eb-rev"><b>Originação</b><span>Identificamos a necessidade e o instrumento certo para cada operação</span></div>
      <div class="eb-stat eb-rev eb-rev--d1"><b>Estruturação</b><span>Garantias, prazos e documentação do jeito que o comitê precisa ver</span></div>
      <div class="eb-stat eb-rev eb-rev--d2"><b>Conexão</b><span>Bancos, cooperativas, fundos e investidores com apetite pelo seu projeto</span></div>
      <div class="eb-stat eb-rev eb-rev--d3"><b>Parceria</b><span>Relacionamento contínuo: a próxima operação começa na entrega desta</span></div>
    </div>
  </div>
</section>

<section class="eb-sec" id="como-trabalhamos">
  <div class="eb-wrap">
    <div class="eb-center"><p class="eb-kicker">Como trabalhamos</p><h2 class="eb-h2">Método com etapas, prazos e responsáveis</h2><p class="eb-lead">Da primeira conversa à liberação do recurso, você sabe exatamente em que ponto a operação está — e o que vem a seguir.</p></div>
    <div class="eb-steps eb-mt40">
      <div class="eb-step eb-rev"><span class="eb-ic"><svg><use href="#i-target"/></svg></span><b>Diagnóstico</b><p>Conversa de 45 minutos para entender operação, fluxo de caixa da safra, garantias e objetivo do capital.</p></div>
      <div class="eb-step eb-rev eb-rev--d1"><span class="eb-ic"><svg><use href="#i-doc"/></svg></span><b>Estruturação</b><p>Definição do instrumento (crédito rural, CPR, Barter, Fiagro…), prazo, garantias e montagem do dossiê.</p></div>
      <div class="eb-step eb-rev eb-rev--d2"><span class="eb-ic"><svg><use href="#i-handshake"/></svg></span><b>Originação &amp; negociação</b><p>Apresentação qualificada às fontes de capital mais aderentes e negociação transparente das condições.</p></div>
      <div class="eb-step eb-rev eb-rev--d3"><span class="eb-ic"><svg><use href="#i-chart"/></svg></span><b>Acompanhamento</b><p>Formalização, liberação e monitoramento. Relacionamento contínuo para as próximas safras.</p></div>
    </div>
  </div>
</section>

<section class="eb-sec eb-sec--panel" id="para-quem">
  <div class="eb-wrap">
    <div class="eb-center"><p class="eb-kicker">Para quem</p><h2 class="eb-h2">Cada elo da cadeia, uma estrutura de capital</h2></div>
    <div class="eb-grid eb-grid--4 eb-mt40">
      <div class="eb-card eb-rev"><span class="eb-ic"><svg><use href="#i-wheat"/></svg></span><h3 class="eb-h3">Produtores rurais</h3><p>Custeio, investimento, comercialização e renegociação — do pequeno produtor ao grande grupo.</p></div>
      <div class="eb-card eb-rev eb-rev--d1"><span class="eb-ic"><svg><use href="#i-bank"/></svg></span><h3 class="eb-h3">Cooperativas</h3><p>Funding e instrumentos para financiar a safra dos cooperados com segurança e escala.</p></div>
      <div class="eb-card eb-rev eb-rev--d2"><span class="eb-ic"><svg><use href="#i-tractor"/></svg></span><h3 class="eb-h3">Agroindústrias e revendas</h3><p>Capital de giro, Barter, CPR e operações estruturadas para crescer com a base de clientes.</p></div>
      <div class="eb-card eb-rev eb-rev--d3"><span class="eb-ic"><svg><use href="#i-handshake"/></svg></span><h3 class="eb-h3">Investidores e instituições</h3><p>Originação qualificada e operações bem estruturadas com produtores e empresas do agro.</p></div>
    </div>
  </div>
</section>

<section class="eb-sec" id="produtos">
  <div class="eb-wrap">
    <div class="eb-grid eb-grid--2" style="gap:56px;align-items:start">
      <div class="eb-rev">
        <p class="eb-kicker">Produtos e serviços</p>
        <h2 class="eb-h2">Instrumentos certos para cada necessidade</h2>
        <ul """ + CHECKLIST_STYLE + """>
          """ + li("wheat", "Crédito rural:", "custeio, investimento e comercialização, com enquadramento no Plano Safra e em linhas de recursos livres.") + """
          """ + li("doc", "CPR física e financeira:", "estruturação com garantias e fluxo compatíveis com a safra.") + """
          """ + li("coin", "Barter:", "troca de insumos por produção, desenhada para proteger a margem.") + """
          """ + li("bank", "Fiagro, CDCA e CRA:", "acesso ao mercado de capitais via fundos e securitizadoras parceiras.") + """
          """ + li("scale", "Renegociação e reestruturação:", "planos que o credor entende e que cabem no seu caixa.") + """
          """ + li("chart", "Consultoria financeira estratégica:", "fluxo de caixa da safra, custo de capital e plano de captação.") + """
        </ul>
        <a class="eb-btn eb-btn--g" href="/solucoes/">Detalhes de cada solução</a>
      </div>
      <div class="eb-rev eb-rev--d1">
        <p class="eb-kicker">Compromissos</p>
        <h2 class="eb-h2">Integridade, transparência e ESG</h2>
        <p class="eb-lead">Intermediar capital exige confiança. Por isso, a BS Agro Capital opera com políticas públicas de Compliance e Anticorrupção, ESG e Privacidade — e com canais oficiais para titulares de dados e relatos de integridade.</p>
        <div class="eb-grid eb-grid--2 eb-mt24">
          <a class="eb-card eb-card--link" href="/politica-de-compliance-anticorrupcao/"><span class="eb-ic"><svg><use href="#i-shield"/></svg></span><h3 class="eb-h3">Compliance &amp; Anticorrupção</h3><p>Tolerância zero, KYC e prevenção à lavagem de dinheiro.</p></a>
          <a class="eb-card eb-card--link" href="/politica-de-esg/"><span class="eb-ic"><svg><use href="#i-leaf"/></svg></span><h3 class="eb-h3">Política de ESG</h3><p>Crédito responsável, inclusão e governança.</p></a>
          <a class="eb-card eb-card--link" href="/politica-de-privacidade/"><span class="eb-ic"><svg><use href="#i-key"/></svg></span><h3 class="eb-h3">Privacidade &amp; LGPD</h3><p>DPO Sanclé Albuquerque · dpo@evellynbrandao.com.br</p></a>
          <a class="eb-card eb-card--link" href="/canal-de-integridade/"><span class="eb-ic"><svg><use href="#i-chat"/></svg></span><h3 class="eb-h3">Canal de Integridade</h3><p>Relatos com sigilo e sem retaliação.</p></a>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="eb-sec eb-sec--alt">
  <div class="eb-wrap">
    <blockquote class="eb-quote eb-rev"><p>Estratégia, capital e parcerias para um campo de maiores resultados.</p><cite>BS Agro Capital · Anápolis/GO</cite></blockquote>
    <div class="eb-center eb-mt40"><a class="eb-btn eb-btn--p" href="/contato/">Agendar diagnóstico gratuito</a></div>
  </div>
</section>
"""}

# ------------------------------------------------------------------ CONTATO
CONTATO = {
  "slug": "contato", "title": "Contato", "template": "page-no-title",
  "excerpt": "Fale com Évellyn Brandão e com a BS Agro Capital: WhatsApp, e-mail, LinkedIn ou formulário. Diagnóstico gratuito de 45 minutos.",
  "html": """
<section class="eb-sec">
  <div class="eb-wrap">
    <div class="eb-grid eb-grid--2" style="gap:56px;align-items:start">
      <div class="eb-rev">
        <p class="eb-kicker">Vamos conversar</p>
        <h2 class="eb-h2">Conte o momento da sua operação.<br>Nós cuidamos da estrutura.</h2>
        <p class="eb-lead">Escolha o canal que preferir. Respondemos em até um dia útil, de segunda a sexta, das 8h às 18h (horário de Brasília).</p>
        <ul class="eb-contact-list eb-mt40">
          <li><span class="eb-ic"><svg><use href="#i-whats"/></svg></span><div><small>WhatsApp</small><a href="#" data-eb-wa target="_blank" rel="noopener">Conversar agora pelo WhatsApp</a></div></li>
          <li><span class="eb-ic"><svg><use href="#i-mail"/></svg></span><div><small>Comercial, projetos e parcerias</small><a href="mailto:contato@evellynbrandao.com.br">contato@evellynbrandao.com.br</a></div></li>
          <li><span class="eb-ic"><svg><use href="#i-linkedin"/></svg></span><div><small>LinkedIn</small><a href="https://www.linkedin.com/in/evellynbrandao/" target="_blank" rel="noopener">linkedin.com/in/evellynbrandao</a></div></li>
          <li><span class="eb-ic"><svg><use href="#i-key"/></svg></span><div><small>Privacidade e LGPD · DPO Sanclé Albuquerque</small><a href="mailto:dpo@evellynbrandao.com.br">dpo@evellynbrandao.com.br</a> · <a href="/portal-do-titular/">Portal do Titular</a></div></li>
          <li><span class="eb-ic"><svg><use href="#i-shield"/></svg></span><div><small>Ouvidoria, compliance e denúncias</small><a href="mailto:ouvidoria@evellynbrandao.com.br">ouvidoria@evellynbrandao.com.br</a> · <a href="/canal-de-integridade/">Canal de Integridade</a></div></li>
          <li><span class="eb-ic"><svg><use href="#i-map"/></svg></span><div><small>Onde estamos</small><span>BS Agro Capital · Anápolis/GO · Goiânia/GO — atendimento em todo o Brasil</span></div></li>
        </ul>
      </div>
      <div class="eb-form-card eb-rev eb-rev--d1">
        <h3 class="eb-h3">Envie sua mensagem</h3>
        <p>Quanto mais contexto, mais objetiva será a nossa resposta.</p>
        <form class="eb-form" data-endpoint="contato@evellynbrandao.com.br" data-subject="Contato — evellynbrandao.com.br" data-ok="Mensagem enviada! Évellyn ou a equipe da BS Agro Capital retornará em até um dia útil." novalidate>
          <div class="eb-form-row">
            <div><label for="c-nome">Nome</label><input id="c-nome" name="nome" type="text" required placeholder="Seu nome completo"></div>
            <div><label for="c-empresa">Empresa / propriedade</label><input id="c-empresa" name="empresa" type="text" placeholder="Nome da empresa ou fazenda"></div>
          </div>
          <div class="eb-form-row">
            <div><label for="c-email">E-mail</label><input id="c-email" name="email" type="email" required placeholder="voce@empresa.com.br"></div>
            <div><label for="c-tel">WhatsApp</label><input id="c-tel" name="whatsapp" type="tel" placeholder="(62) 9 9999-9999"></div>
          </div>
          <div class="eb-form-row">
            <div><label for="c-perfil">Você é</label><select id="c-perfil" name="perfil"><option>Produtor(a) rural</option><option>Empresa do agro / agroindústria</option><option>Cooperativa</option><option>Revenda de insumos</option><option>Investidor / instituição financeira</option><option>Organizador de evento</option><option>Outro</option></select></div>
            <div><label for="c-assunto">Assunto</label><select id="c-assunto" name="assunto"><option>Diagnóstico gratuito</option><option>Crédito rural / Plano Safra</option><option>Estruturação (CPR, Barter, Fiagro)</option><option>Fontes de capital</option><option>Renegociação de dívidas</option><option>Treinamento de equipes</option><option>Mentoria ou palestra</option><option>Parcerias</option><option>Outro</option></select></div>
          </div>
          <div><label for="c-msg">Mensagem</label><textarea id="c-msg" name="mensagem" required placeholder="Conte o momento da sua operação e o que você precisa…"></textarea></div>
          <label class="eb-check"><input type="checkbox" name="consentimento" value="sim" required> <span>Li e concordo com a <a href="/politica-de-privacidade/">Política de Privacidade</a>. Meus dados serão usados apenas para responder a esta solicitação.</span></label>
          <div class="eb-hp" aria-hidden="true"><input type="text" name="_honey" tabindex="-1" autocomplete="off"></div>
          <div class="eb-form-msg" role="status" aria-live="polite"></div>
          <button class="eb-btn eb-btn--p" type="submit">Enviar mensagem</button>
        </form>
      </div>
    </div>
  </div>
</section>
"""}

# ------------------------------------------------------------------ MATÉRIAS (listagem)
MATERIAS = {
  "slug": "materias", "title": "Matérias", "template": "page-no-title",
  "excerpt": "Crédito rural, estruturação, gestão financeira, liderança e tendências do agro — conteúdo relevante, explicado com clareza para quem decide.",
  "html_before": """
<section class="eb-sec">
  <div class="eb-wrap">
    <div class="eb-chips eb-rev" style="margin-bottom:34px">
      <a class="eb-chip" href="/materias/">Todas</a>
      <a class="eb-chip" href="/category/credito-rural/">Crédito Rural</a>
      <a class="eb-chip" href="/category/estruturacao-capital/">Estruturação &amp; Capital</a>
      <a class="eb-chip" href="/category/gestao-financeira-agro/">Gestão Financeira no Agro</a>
      <a class="eb-chip" href="/category/lideranca-alta-performance/">Liderança &amp; Alta Performance</a>
      <a class="eb-chip" href="/category/mercado-tendencias/">Mercado &amp; Tendências</a>
      <a class="eb-chip" href="/category/esg-conformidade/">ESG &amp; Conformidade</a>
    </div>
""",
  "html_after": """
  </div>
</section>
<section class="eb-sec eb-sec--alt eb-sec--tight">
  <div class="eb-wrap">
    <div class="eb-post-cta" style="margin:0"><div><b>Quer levar estes temas para a sua operação?</b><p>Agende um diagnóstico gratuito de 45 minutos com Évellyn Brandão.</p></div><a class="eb-btn eb-btn--p" href="/contato/">Agendar diagnóstico</a></div>
  </div>
</section>
"""}

# ------------------------------------------------------------------ PORTAL DO TITULAR (LGPD) — editorial + formulário
PORTAL = {
  "slug": "portal-do-titular", "title": "Portal do Titular (LGPD)", "template": "page",
  "excerpt": "Exerça seus direitos previstos na LGPD. Sua requisição é enviada diretamente ao Encarregado de Proteção de Dados (DPO), Sr. Sanclé Albuquerque.",
  "html": """{{INTRO:portal-do-titular-intro}}
<div class="eb-form-card">
  <h3 class="eb-h3">Requisição do titular de dados</h3>
  <p>Preencha os campos abaixo. A requisição será enviada ao DPO (dpo@evellynbrandao.com.br) e você receberá a confirmação no e-mail informado.</p>
  <form class="eb-form" data-endpoint="dpo@evellynbrandao.com.br" data-subject="Requisição LGPD — Portal do Titular" data-ok="Requisição registrada! O DPO responderá no e-mail informado dentro dos prazos previstos na LGPD." novalidate>
    <div class="eb-form-row">
      <div><label for="l-nome">Nome completo</label><input id="l-nome" name="nome" type="text" required placeholder="Seu nome"></div>
      <div><label for="l-email">E-mail para resposta</label><input id="l-email" name="email" type="email" required placeholder="voce@exemplo.com"></div>
    </div>
    <div class="eb-form-row">
      <div><label for="l-tel">Telefone (opcional)</label><input id="l-tel" name="telefone" type="tel" placeholder="(00) 0 0000-0000"></div>
      <div><label for="l-rel">Sua relação conosco</label><select id="l-rel" name="relacao"><option>Cliente / potencial cliente</option><option>Parceiro ou fornecedor</option><option>Visitante do site</option><option>Colaborador</option><option>Outro</option></select></div>
    </div>
    <div><label for="l-tipo">Tipo de requisição</label><select id="l-tipo" name="tipo_requisicao"><option>Confirmação da existência de tratamento</option><option>Acesso aos dados</option><option>Correção de dados incompletos, inexatos ou desatualizados</option><option>Anonimização, bloqueio ou eliminação</option><option>Portabilidade</option><option>Informação sobre compartilhamento</option><option>Revogação do consentimento</option><option>Oposição ao tratamento</option><option>Outra</option></select></div>
    <div><label for="l-desc">Descrição da requisição</label><textarea id="l-desc" name="descricao" required placeholder="Descreva sua solicitação com o máximo de detalhes possível…"></textarea></div>
    <label class="eb-check"><input type="checkbox" name="declaracao" value="sim" required> <span>Declaro que sou o titular dos dados (ou seu representante legal) e que as informações prestadas são verdadeiras. Estou ciente de que poderá ser solicitada a confirmação de identidade.</span></label>
    <div class="eb-hp" aria-hidden="true"><input type="text" name="_honey" tabindex="-1" autocomplete="off"></div>
    <div class="eb-form-msg" role="status" aria-live="polite"></div>
    <button class="eb-btn eb-btn--p" type="submit">Enviar requisição ao DPO</button>
  </form>
</div>
"""}

# ------------------------------------------------------------------ CANAL DE INTEGRIDADE — editorial + formulário
INTEGRIDADE = {
  "slug": "canal-de-integridade", "title": "Canal de Integridade", "template": "page",
  "excerpt": "Relate, com sigilo garantido e sem retaliação, condutas que violem nossa Política de Compliance e Anticorrupção. Seu relato vai direto ao Compliance Officer.",
  "html": """{{INTRO:canal-de-integridade-intro}}
<div class="eb-form-card">
  <h3 class="eb-h3">Registrar relato</h3>
  <p>Você pode se identificar ou relatar de forma anônima. O relato é enviado ao canal de compliance (ouvidoria@evellynbrandao.com.br).</p>
  <form class="eb-form" data-endpoint="ouvidoria@evellynbrandao.com.br" data-subject="Relato — Canal de Integridade" data-ok="Relato registrado com sigilo. Se você informou um e-mail, receberá atualizações por ele." novalidate>
    <label class="eb-check"><input type="checkbox" name="anonimo" value="sim" id="i-anon"> <span><strong>Quero relatar de forma anônima</strong> (não preencha nome e e-mail).</span></label>
    <div class="eb-form-row">
      <div><label for="i-nome">Nome (opcional)</label><input id="i-nome" name="nome" type="text" placeholder="Seu nome"></div>
      <div><label for="i-email">E-mail para acompanhamento (opcional)</label><input id="i-email" name="email" type="email" placeholder="voce@exemplo.com"></div>
    </div>
    <div class="eb-form-row">
      <div><label for="i-rel">Sua relação conosco</label><select id="i-rel" name="relacao"><option>Cliente</option><option>Parceiro / fornecedor</option><option>Colaborador</option><option>Instituição financeira</option><option>Terceiro / público em geral</option></select></div>
      <div><label for="i-tipo">Tipo de relato</label><select id="i-tipo" name="tipo_relato"><option>Corrupção, suborno ou propina</option><option>Fraude ou falsificação</option><option>Conflito de interesses</option><option>Lavagem de dinheiro</option><option>Assédio ou discriminação</option><option>Violação de dados pessoais (LGPD)</option><option>Descumprimento de política interna</option><option>Outro</option></select></div>
    </div>
    <div><label for="i-desc">Descrição do fato</label><textarea id="i-desc" name="descricao" required placeholder="O que aconteceu, quando, onde e quem esteve envolvido? Quanto mais detalhes, melhor a apuração."></textarea></div>
    <div><label for="i-evid">Evidências (descreva; documentos podem ser solicitados posteriormente)</label><textarea id="i-evid" name="evidencias" style="min-height:90px" placeholder="Ex.: mensagens, documentos, testemunhas…"></textarea></div>
    <label class="eb-check"><input type="checkbox" name="ciencia" value="sim" required> <span>Estou ciente de que o relato será tratado com sigilo, apurado de forma independente e que não haverá retaliação a relatos de boa-fé.</span></label>
    <div class="eb-hp" aria-hidden="true"><input type="text" name="_honey" tabindex="-1" autocomplete="off"></div>
    <div class="eb-form-msg" role="status" aria-live="polite"></div>
    <button class="eb-btn eb-btn--p" type="submit">Enviar relato com sigilo</button>
  </form>
</div>
"""}

LANDING_PAGES = [SOBRE, SOLUCOES, BS, CONTATO]
