from .helpers import *
from .clientes_data import CLIENTS, logo_wall
EMPRESA = {
 "slug": "empresa", "order": 1, "short": "A Trix",
 "title": "A Trix: tecnologia inteligente em Brasília desde 2009",
 "description": "Conheça a Trix TI: desde 2009 em software sob medida, conectividade em saúde suplementar, consultoria e IA, com governança e LGPD.",
 "image": "images/02.jpg", "type": "about", "hero": "dark",
 "kicker": "Sobre a Trix", "h1": "Uma empresa dinâmica, <strong>focada em resultado</strong>",
 "lead": "Desde 10 de julho de 2009, a Trix Tecnologia Inteligente cria produtos e serviços para os diversos segmentos do mercado. De uma maneira diferenciada, provemos soluções adaptáveis e escaláveis, de acordo com as tendências da atual indústria tecnológica.",
 "actions": [("Nossos produtos", "/produtos/"), ("Fale conosco", "/contato/", "trix-btn--ghost")],
 "meta": ["Matriz em Brasília, escritório em São Paulo", "Sociedade Empresária Ltda. · CNPJ 11.010.095/0001-40", "Oracle PartnerNetwork"],
 "body": "".join([
  sec('<div class="trix-split"><div class="trix-reveal"><span class="trix-kicker">Quem somos</span><h2>Tecnologia como <strong>estratégia</strong> para qualquer organização</h2><p>Como entendemos que tecnologia é estratégia para qualquer organização, agregamos conceitos, metodologias e boas práticas para a melhoria contínua de processos organizacionais. Nossa atuação nasce da engenharia de software e se estende à conectividade em saúde suplementar, à consultoria e a produtos com inteligência artificial.</p><p>Hoje, mais de 35 operadoras de saúde utilizam nossos produtos e serviços, e milhares de pessoas Brasil afora passam todos os dias por sistemas desenvolvidos pela Trix: da autorização de um atendimento à digitalização de um documento, do reconhecimento facial de um beneficiário ao agendamento de uma consulta pelo WhatsApp.</p>'
      + '<figure class="trix-quote"><p>“A TRIX TI utiliza um processo de software que permite a organização e o gerenciamento dos produtos de forma ágil e organizada. Na metodologia PROTRIX estão refletidas as boas práticas de desenvolvimento iterativo, gestão de demandas e gerência de projetos.”</p><footer><div class="trix-author"><img src="{{media:images/28.png}}" alt="Marcos Soares" loading="lazy"><span>Marcos Soares · Diretor Executivo</span></div></footer></figure></div>'
      + '<div class="trix-reveal"><div class="trix-feature__media"><img src="{{media:images/02.jpg}}" alt="Equipe Trix Tecnologia Inteligente" loading="lazy"></div><div class="trix-center" style="margin-top:22px"><img src="{{media:images/39.png}}" alt="Trix Tecnologia Inteligente" width="191" height="72" loading="lazy"></div></div></div>'),
  sec(head("Propósito", "Missão, <strong>visão</strong> e valores", "", True)
   + cards([
     {"icon": "rocket", "title": "Missão", "text": "Criar soluções inovadoras que agregam valor aos negócios dos nossos clientes, promovendo a escalabilidade, resiliência e resolutividade esperadas."},
     {"icon": "eye", "title": "Visão", "text": "Ser reconhecida pela satisfação dos nossos clientes e pela qualidade dos produtos e serviços oferecidos, estabelecendo alianças que formam um relacionamento contínuo e próspero em todos os sentidos."},
     {"icon": "heart", "title": "Valores", "text": "<strong>Ética</strong> · <strong>Compromisso</strong> · <strong>Competência</strong>. Princípios de conduta: honestidade, responsabilidade, respeito, organização e valorização."},
   ], 3), "trix-section--sand", "missao"),
  sec(head("Linha do tempo", "Uma história construída <strong>com os clientes</strong>")
   + steps([
     ("2009", "Fundação em Brasília", "A Trix Tecnologia Inteligente nasce em 10 de julho de 2009 com foco em desenvolvimento de software e serviços de TI. No mesmo ano, a Intranet Trix vence o Prêmio Unimed de Comunicação na categoria até 30 mil beneficiários."),
     ("2010", "Bicampeã do Prêmio Unimed de Comunicação", "Pelo segundo ano consecutivo, a Intranet Trix recebe o troféu “Alberto Urquiza Wanderley” (6º Prêmio Unimed de Comunicação), agora na categoria de cooperativas com 40 a 100 mil beneficiários, durante a 40ª Convenção Nacional Unimed, em Goiânia."),
     ("Década de 2010", "Conectividade em saúde suplementar", "SAW, Portal Operadora, Integrador e GEDAI consolidam a Trix como parceira de operadoras, Unimeds, RHs e autogestões em saúde em todo o país, com certificação PTU para intercâmbio e faturamento eletrônico TISS."),
     ("2020", "Política de Privacidade e programa de proteção de dados", "Publicação da Política de Privacidade (28/09/2020). O Portal de Proteção de Dados apresenta o Encarregado (DPO), os comitês de Compliance, Proteção de Dados e Segurança e Qualidade e a plataforma Trix Academy."),
     ("2022", "1º Webinário de Conectividade em Saúde Suplementar", "Em março de 2022: nova TISS, censo hospitalar, novo app de beneficiários, autorização e elegibilidade e racionalização do Rol Unimed/Intercâmbio."),
     ("2024", "Nova estrutura societária", "Em janeiro de 2024, ingresso da Trix Participações e Investimentos S.A. no quadro societário da empresa."),
     ("Hoje", "Inteligência artificial e novos produtos", "Prontow com agendamento por WhatsApp e sugestão diagnóstica por IA, Aspect Face com serviço antifraude, Xield para controle de acesso e automação, e participação no Oracle PartnerNetwork."),
   ])),
  sec(head("Diferenciais", "Por que empresas escolhem a <strong>Trix</strong>", "", True)
   + cards([
     {"icon": "code", "title": "Metodologia própria (PROTRIX e MTRIX)", "text": "Processo de software maduro, com responsabilidades, atividades e interações definidas, e escritório de processos que aplica BPM e governança aos produtos."},
     {"icon": "stethoscope", "title": "Especialistas em saúde suplementar", "text": "Domínio profundo de TISS, PTU, auditoria médica, intercâmbio, elegibilidade e faturamento. Falamos a língua das operadoras e dos prestadores."},
     {"icon": "brain", "title": "IA aplicada a problemas reais", "text": "Antifraude facial, sugestão diagnóstica e agendamento conversacional por WhatsApp — sempre com o humano no controle."},
     {"icon": "shield", "title": "Governança e conformidade", "text": "Comitês ativos, DPO nomeado, código de conduta, políticas públicas e canais de denúncia. Segurança e privacidade por padrão em tudo que entregamos."},
     {"icon": "cloud", "title": "Ecossistema Oracle", "text": "Membro do Oracle PartnerNetwork (OPN), unindo expertise em conectividade e saúde à solidez das soluções Oracle em nuvem, banco de dados e integração."},
     {"icon": "clock", "title": "Atendimento ágil", "text": "Suporte de segunda a sexta das 07h30 às 19h30, call-back gratuito, central de atendimento e treinamento contínuo. Atendimento ágil também é uma marca da Trix."},
   ], 3), "trix-section--sand"),
  sec(head("Governança", "Comitês que <strong>sustentam</strong> nossa integridade")
   + cards([
     {"icon": "scale", "title": "Comitê de Compliance", "text": "Compliance Officer: Sanclé Landim Albuquerque. Patrocinadores: Marcos Soares de Sousa, Nádia Regina Siqueira Alves e Herbert Oliveira. Canal: ouvidoria@trixti.com.br.", "url": "/conformidade/compliance/", "cta": "Programa de compliance"},
     {"icon": "lock", "title": "Comitê de Proteção de Dados", "text": "Encarregado (DPO): Sanclé Landim Albuquerque. Patrocinadores: Marcos Soares de Sousa, Daniel Rodrigo da Silva e Herbert Oliveira. Canal: dpo@trixti.com.br.", "url": "/conformidade/lgpd/", "cta": "Portal de proteção de dados"},
     {"icon": "cpu", "title": "Comitê de Segurança e Qualidade", "text": "Security Officer: Sanclé Landim Albuquerque. Patrocinadores: Marcos Soares de Sousa, Daniel Rodrigo da Silva e Nádia Regina. Canal: seguranca@trixti.com.br.", "url": "/conformidade/seguranca-da-informacao/", "cta": "Política de segurança"},
   ], 3, dark=True)),
  sec('<div class="trix-split"><div class="trix-reveal"><span class="trix-kicker">Dados institucionais</span><h2>Transparência <strong>em primeiro lugar</strong></h2>'
      + table(["Item", "Informação"], [["Razão social", "Trix Tecnologia Inteligente Ltda"], ["Nome fantasia", "Trix TI"], ["CNPJ", "11.010.095/0001-40"], ["Fundação", "10 de julho de 2009"], ["Natureza jurídica", "Sociedade Empresária Limitada"], ["Atividade principal", "Desenvolvimento e licenciamento de programas de computador (CNAE 62.03-1-00)"], ["Atividades secundárias", "Suporte técnico e manutenção em TI (62.09-1-00) · Atividades de apoio à gestão de saúde (86.60-7-00)"], ["Administração", "Marcos Soares de Sousa (administrador; grafia no cadastro da Receita Federal: Souza) · Trix Participações e Investimentos S.A. (sócia desde 31/01/2024)"], ["Matriz", "Edifício Capital Financial Center, SIG Quadra 4, Lote 75, Bloco A, Sala 15 — Brasília/DF, CEP 70610-440"], ["Escritório", "Alameda Santos, 1165 — Cerqueira César, São Paulo/SP, CEP 01419-002"]])
      + '</div><div class="trix-reveal"><div class="trix-card trix-card--yellow"><div class="trix-card__icon">{{icon:graduation}}</div><h3>Trix Academy</h3><p>Plataforma de treinamentos para clientes, parceiros e fornecedores: integração, LGPD, código de conduta, políticas e uso dos sistemas. Parceiros, fornecedores e clientes devem estar preparados para a LGPD.</p><a class="trix-card__link" href="http://academy.trixti.com.br/" target="_blank" rel="noopener">Acessar a Trix Academy</a></div><div style="height:18px"></div><div class="trix-card trix-card--dark"><div class="trix-card__icon">{{icon:map}}</div><h3>Como chegar</h3><p>Estamos no Setor de Indústrias Gráficas (SIG), a poucos minutos do centro de Brasília. Veja o mapa, rotas e horários na página de contato.</p><a class="trix-card__link" href="/contato/#como-chegar">Ver mapa e rotas</a></div></div></div>', "trix-section--sand"),
  contact_band(),
 ]),
}
EVENTOS = {
 "slug": "eventos", "parent": "empresa", "order": 5, "short": "Eventos e Academy",
 "title": "Eventos e Trix Academy | Trix TI",
 "description": "Webinário de Conectividade em Saúde Suplementar (nova TISS, censo hospitalar, app de beneficiários) e a plataforma de treinamentos Trix Academy.",
 "image": "images/02.jpg", "hero": "dark",
 "kicker": "Conhecimento compartilhado", "h1": "Eventos e <strong>Trix Academy</strong>",
 "lead": "Acreditamos que tecnologia só gera valor quando as pessoas sabem usá-la. Por isso promovemos webinários, treinamentos e programas de capacitação para operadoras, prestadores, parceiros e colaboradores.",
 "actions": [("Acessar a Trix Academy", "http://academy.trixti.com.br/"), ("Quero participar do próximo evento", "/contato/?assunto=Treinamento%20e%20Capacita%C3%A7%C3%A3o", "trix-btn--ghost")],
 "body": "".join([
  sec(head("Registro", "1º Webinário de Conectividade em <strong>Saúde Suplementar</strong>", "Evento online realizado em março de 2022, destinado a operadoras de saúde — clientes Trix ou não — para apresentar as principais novidades da plataforma de conectividade da Trix TI.")
   + table(["Palestrante", "Função", "Tema", "Data"], [
      ["Juliana", "Gerente Operacional", "Censo hospitalar", "15/03/2022 · 14h às 14h30"],
      ["Arlindo", "Arquiteto de Software", "Nova TISS e nova Auditoria Técnica", "15/03/2022 · 15h às 16h"],
      ["Michel Popolin", "Arquiteto de Software e Mobile", "Novo App de Beneficiários", "17/03/2022 · 14h às 14h30"],
      ["Clécio Pessoa", "Coordenador de Suporte", "Autorização e elegibilidade · Racionalização Rol Unimed / Intercâmbio", "17/03/2022 · 15h às 16h"]])
   + '<p class="trix-muted" style="margin-top:14px">Contato da organização: <a href="mailto:webinario@trixti.com.br">webinario@trixti.com.br</a>. Prometemos não utilizar suas informações de contato para enviar qualquer tipo de SPAM. A Trix TI respeita sua privacidade.</p>'),
  sec(head("Capacitação", "Trix <strong>Academy</strong>", "Parceiros, fornecedores e clientes devem estar preparados para a LGPD. Acesse nossa plataforma de treinamentos e participe dos programas.", True)
   + cards([
     {"icon": "book", "title": "Integração e LGPD", "text": "Programa obrigatório para colaboradores e parceiros: Código de Conduta Ética, Políticas de Privacidade, Segurança e Proteção de Dados."},
     {"icon": "monitor", "title": "Uso dos sistemas", "text": "Treinamento técnico e operacional sobre SAW, Prontow, Portal Operadora e demais soluções, com avaliação periódica em ambiente e-learning."},
     {"icon": "graduation", "title": "Reciclagem contínua", "text": "Atualizações sobre legislação (TISS, ANS, LGPD) e melhores práticas do processo de atendimento dos prestadores."},
   ], 3), "trix-section--sand"),
  contact_band(),
 ]),
}
UNIMED = ["70.png","40.png","41.png","42.png","43.png","45.png","46.png","47.png","49.png","unimed-curitiba.jpg","50.png","51.png","52.png","53.png","55.png","56.png","57.png","58.png","59.png","61.png","62.png","unimed-sao-joao.jpg","unimed-serra-minas.jpg","65.png","66.png","67.png","unimed-uba.jpg","69.png","71.png"]
CLIENTES = {
 "slug": "clientes", "order": 4, "short": "Clientes",
 "title": "Clientes: operadoras, Unimeds e clínicas | Trix TI",
 "description": "Mais de 35 operadoras de saúde, Unimeds, RHs e autogestões confiam na Trix TI, que também atende médicos, clínicas e laboratórios em todo o Brasil.",
 "image": "images/desenvolvimento.jpg", "hero": "dark",
 "kicker": "Clientes", "h1": "Desenvolver software é a <strong>nossa maior força</strong>",
 "lead": "Para você que quer ser cliente, saiba: entre em contato e nos conheça melhor. Descubra o nível de satisfação dos nossos clientes e por que operadoras de saúde de todo o Brasil escolheram a Trix como parceira tecnológica.",
 "actions": [("Quero ser cliente", "/contato/"), ("Ver produtos", "/produtos/", "trix-btn--ghost")],
 "meta": ["+35 operadoras de saúde", "Unimeds, RHs e autogestões", "Clínicas, consultórios e laboratórios"],
 "body": "".join([
  sec(head("Segmentos", "Quem <strong>atendemos</strong>", "Na área de saúde e conectividade em saúde, atendemos pequenas, médias e grandes operadoras, Unimeds, RHs e autogestões em saúde em todo o Brasil. Também temos soluções em software especializadas para prestadores de serviços médicos.")
   + cards([
     {"icon": "globe", "title": "Operadoras e cooperativas", "text": "Unimeds, medicinas de grupo, autogestões e RHs com planos próprios: SAW, Portal Operadora, Integrador, conectividade e auditoria."},
     {"icon": "stethoscope", "title": "Profissionais médicos e clínicas", "text": "Consultórios, clínicas e CTMs: Prontow (agenda, prontuário, financeiro, TISS, WhatsApp com IA) e serviço de Agenda Médica."},
     {"icon": "activity", "title": "Laboratórios e diagnóstico", "text": "Pedidos, laudos com ciclo de status, resultados online e faturamento no padrão ANS."},
     {"icon": "building", "title": "Hospitais, empresas e condomínios", "text": "Xield para controle de acesso e automação com biometria facial; GEDAI para gestão documental; intranet corporativa."},
   ], 4)),
  sec(head("Portfólio de clientes", "Operadoras que <strong>confiam</strong> na Trix", "", True)
   + logo_wall(CLIENTS)
   + '<p class="trix-center trix-muted" style="margin-top:22px;font-size:.92rem">' + " · ".join(n for _, n in CLIENTS) + '</p>', "trix-section--sand"),
  sec(head("Resultados", "O que nossos clientes <strong>ganham</strong>")
   + cards([
     {"icon": "trending", "title": "Escalabilidade do atendimento", "text": "Uma única interface comum a atendentes, analistas de contas, cooperados/prestadores, empresas contratantes e beneficiários — via internet, sem infraestrutura adicional."},
     {"icon": "shield", "title": "Segurança e conformidade", "text": "Esquemas redundantes, SSL, controle de transações, autenticação, backups e proteções administrativas, técnicas e físicas. Logs de tratamento de dados e adequação à LGPD."},
     {"icon": "clock", "title": "Menos tempo, menos custo", "text": "Auditoria inteligente que antecipa a análise ao solicitante, intercâmbio sem links dedicados e faturamento eletrônico para toda a rede credenciada."},
   ], 3)),
  contact_band(),
 ]),
}
PAGES = [EMPRESA, EVENTOS, CLIENTES]
