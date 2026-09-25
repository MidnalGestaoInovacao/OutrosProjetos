from .helpers import *
def svc(slug, title, desc, kicker, h1, lead, body, meta=None, image=None, stype=None, faq_items=None, order=10):
    p = {"slug": slug, "parent": "servicos", "order": order, "title": title, "description": desc, "type": "service", "serviceType": stype or kicker, "hero": "dark", "short": kicker,
         "kicker": kicker, "h1": h1, "lead": lead, "actions": [("Falar com um consultor", "/contato/"), ("Ver todos os serviços", "/servicos/", "trix-btn--ghost")], "body": body}
    if meta: p["meta"] = meta
    if image: p["image"] = image
    if faq_items: p["faq"] = [{"q": q, "a": a} for q, a in faq_items]
    return p
HUB = {
 "slug": "servicos", "order": 2, "short": "Serviços",
 "title": "Serviços de TI: software, saúde e consultoria | Trix TI",
 "description": "Fábrica de software (PROTRIX), conectividade em saúde suplementar, consultoria, escritório de processos, automação, agenda médica e parceria Oracle.",
 "image": "images/22.png", "hero": "dark",
 "kicker": "Serviços", "h1": "Soluções sob medida <strong>para o seu negócio</strong>",
 "lead": "Com processos bem estabelecidos, a Trix TI detém a prática necessária para atuar em diferentes formatos de projeto e em qualquer momento do ciclo de vida, sem perder a qualidade. Escolha o serviço e conte com uma equipe altamente qualificada, com experiência em ambientes heterogêneos.",
 "actions": [("Falar com um consultor", "/contato/")],
 "body": "".join([
  sec(cards([
     {"icon": "code", "title": "Fábrica de software", "text": "Metodologia PROTRIX: projeto completo, especificação de requisitos, análise e design, codificação, testes e documentação de sistemas legados.", "url": "/servicos/fabrica-de-software/"},
     {"icon": "network", "title": "Conectividade em saúde suplementar", "text": "Elegibilidade com cartão e biometria, faturamento eletrônico, treinamento, implantação, suporte remoto e integração com a central de atendimento.", "url": "/servicos/conectividade-em-saude/"},
     {"icon": "briefcase", "title": "Consultoria", "text": "Outsourcing, segurança da informação, análise de pontos de função (APF), mapeamento de processos, software livre e engenharia de software.", "url": "/servicos/consultoria/"},
     {"icon": "flow", "title": "Escritório de processos", "text": "BPM, governança, gestão de processos, gestão de TI e gestão estratégica com a metodologia MTRIX e monitoramento assistido.", "url": "/servicos/escritorio-de-processos/"},
     {"icon": "gear", "title": "Soluções e automação", "text": "Gestão de demandas, gestão de projetos (PMBOK), gestão da qualidade, loja virtual e monitoramento de serviços de TI.", "url": "/servicos/solucoes-e-automacao/"},
     {"icon": "calendar", "title": "Agenda médica", "text": "Central de atendimento receptiva e ativa, avisos por SMS e e-mail, número 4005 nacional e relatórios mensais para profissionais e clínicas.", "url": "/servicos/agenda-medica/"},
     {"icon": "cloud", "title": "Oracle Partner", "text": "Cloud Infrastructure (OCI), banco de dados Oracle, integração, aplicações corporativas, analytics e segurança/compliance.", "url": "/servicos/oracle-partner/"},
     {"icon": "graduation", "title": "Treinamento e capacitação", "text": "Trix Academy: programas de integração, LGPD, uso dos sistemas e reciclagem contínua para clientes, parceiros e colaboradores.", "url": "/empresa/eventos/"},
   ], 4)),
  sec(head("Como trabalhamos", "Do <strong>diagnóstico</strong> à operação assistida", "", True)
   + steps([("1", "Entendimento", "Reunião de diagnóstico para mapear objetivos, processos, sistemas legados e restrições. Sem custo e sem compromisso."), ("2", "Proposta", "Formato de projeto que melhor se adequa à sua empresa: projeto completo, alocação de especialistas, licenciamento ou serviço contínuo."), ("3", "Execução", "Metodologia PROTRIX/MTRIX, entregas iterativas, gestão de demandas e comunicação transparente com indicadores (KPIs/SLAs)."), ("4", "Operação assistida", "Implantação remota ou presencial, treinamento, suporte de segunda a sexta das 07h30 às 19h30 e melhoria contínua.")]), "trix-section--sand"),
  contact_band(),
 ]),
}
FABRICA = svc("fabrica-de-software",
 "Fábrica de software com metodologia PROTRIX | Trix TI",
 "Software sob medida em Brasília: requisitos, design, codificação, testes e sistemas legados com Java, PHP, Oracle, PostgreSQL e métodos ágeis.",
 "Fábrica de software", "Software sob medida com a <strong>metodologia PROTRIX</strong>",
 "A Trix TI utiliza um processo de software que permite a organização e o gerenciamento dos produtos de forma ágil e organizada. A metodologia PROTRIX de desenvolvimento e manutenção de sistemas define, de forma detalhada, as responsabilidades, atividades e interações com outras áreas de conhecimento — refletindo as boas práticas de desenvolvimento iterativo, gestão de demandas e gerência de projetos.",
 "".join([
  sec('<div class="trix-split"><div class="trix-reveal"><span class="trix-kicker">Soluções customizadas</span><h2>Você escolhe o formato. <strong>Nós garantimos a qualidade.</strong></h2><p>Com processos bem estabelecidos, a Trix TI detém a prática necessária para atuar em diferentes formatos de projetos e em momentos distintos do ciclo de vida, sem perder a qualidade. A flexibilidade decorrente da maturidade da Trix permite que o cliente contrate o formato que melhor se adequa às características da sua empresa.</p>'
      + checks(["Projeto completo de um sistema de informação", "Especificação de requisitos", "Análise e design (projeto técnico)", "Codificação (programação)", "Testes", "Documentação de sistemas legados"]) + '</div>'
      + '<div class="trix-reveal">' + cards([{"icon": "check", "title": "Benefícios das soluções customizadas", "text": "Solução sob medida · Atendimento das necessidades específicas do cliente · Profissionais experientes e qualificados · Alta capacidade técnica em desenvolvimento de software · Foco na qualidade do produto."}], 1, tilt=False) + '</div></div>'),
  sec(head("Stack tecnológico", "Principais <strong>tecnologias</strong>", "Dominamos plataformas consolidadas e incorporamos novas tecnologias conforme a necessidade de cada projeto — incluindo nuvem Oracle e inteligência artificial.", True)
   + '<div class="trix-grid trix-grid--3">'
   + '<div class="trix-card trix-reveal"><h3>Plataformas e linguagens</h3><p>' + pills(["Java", "PHP", "iOS / Objective-C", "JavaScript", "Ajax", "XHTML / XML", "Tableless"]) + '</p></div>'
   + '<div class="trix-card trix-reveal"><h3>Bancos de dados</h3><p>' + pills(["Oracle", "PostgreSQL", "MySQL", "Microsoft SQL Server"]) + '</p></div>'
   + '<div class="trix-card trix-reveal"><h3>Metodologia e processos</h3><p>' + pills(["SOA", "BPM", "FDD", "RUP", "MPS.BR", "Métodos ágeis", "UML"]) + '</p></div>'
   + '<div class="trix-card trix-reveal"><h3>Frameworks</h3><p>' + pills(["JSF", "JBoss Seam", "RichFaces", "A4J", "Spring", "Hibernate", "Struts"]) + '</p></div>'
   + '<div class="trix-card trix-reveal"><h3>Servidores de aplicação</h3><p>' + pills(["JBoss", "Apache", "Tomcat", "WebSphere", "GlassFish", "SunOne"]) + '</p></div>'
   + '<div class="trix-card trix-reveal"><h3>Nuvem e IA</h3><p>' + pills(["Oracle Cloud (OCI)", "APIs REST / Swagger", "Reconhecimento facial", "LLMs e assistentes conversacionais"]) + '</p></div></div>', "trix-section--sand"),
  sec(head("Processo", "Como funciona a <strong>PROTRIX</strong>")
   + steps([("Etapa 1", "Gestão de demandas", "Registro, priorização e acompanhamento das demandas com o cliente, garantindo visibilidade e previsibilidade."), ("Etapa 2", "Requisitos e design", "Especificação funcional e técnica, prototipação e modelagem (UML/BPMN) validadas com os usuários."), ("Etapa 3", "Desenvolvimento iterativo", "Entregas incrementais com revisão de código, integração contínua e testes automatizados e manuais."), ("Etapa 4", "Homologação e implantação", "Testes de aceitação, documentação, treinamento e implantação assistida — remota ou presencial."), ("Etapa 5", "Sustentação", "Manutenção evolutiva e corretiva com SLAs, monitoramento e melhoria contínua.")])),
  sec(faq([("A Trix desenvolve aplicativos móveis?", "Sim. Desenvolvemos aplicações web e mobile (iOS e Android), como o app do paciente do Prontow e o app de beneficiários das operadoras."), ("Vocês trabalham com sistemas legados?", "Sim. Oferecemos documentação de sistemas legados, integração via Integrador Trix e modernização gradual, preservando a operação."), ("Como é feita a medição e o orçamento?", "Podemos trabalhar por escopo fechado, por alocação de equipe ou por pontos de função (APF/IFPUG), com estimativas transparentes.")]), "trix-section--sand"),
  contact_band(),
 ]), meta=["Metodologia PROTRIX", "Java, PHP, iOS, Oracle e PostgreSQL", "Projetos completos ou por etapa"], image="images/22.png", stype="Desenvolvimento de software sob medida",
 faq_items=[("A Trix desenvolve aplicativos móveis?", "Sim. Desenvolvemos aplicações web e mobile (iOS e Android)."), ("Vocês trabalham com sistemas legados?", "Sim. Documentação, integração e modernização gradual."), ("Como é feita a medição e o orçamento?", "Escopo fechado, alocação de equipe ou pontos de função (APF/IFPUG).")], order=1)
CONECT = svc("conectividade-em-saude",
 "Conectividade em saúde suplementar | Trix TI",
 "Elegibilidade com cartão e biometria, faturamento eletrônico TISS, implantação, treinamento e suporte remoto a prestadores. Mais de 35 operadoras atendidas.",
 "Conectividade em saúde suplementar", "Tecnologia, recursos e pessoas para <strong>conectar prestadores e operadoras</strong>",
 "O serviço de conectividade em saúde suplementar da Trix TI fornece tecnologias, recursos e mão de obra especializada nos processos que envolvem a troca de informação entre o prestador e a operadora de saúde. Atendimento, faturamento, credenciamento, equipe médica, ouvidoria, cadastro, jurídico, compras, tecnologia e gestão — de ambas as partes — são diretamente beneficiados.",
 "".join([
  sec(head("Objetivo", "Integração de <strong>ambientes, pessoas, ferramentas e processos</strong>", "O principal objetivo do serviço é promover o uso de tecnologia de ponta para a interação e integração de ambientes, pessoas × ferramentas × processos, consolidando uma base de conhecimento de melhores práticas aplicáveis a qualquer prestador ou operadora de saúde, independentemente do porte, segmento ou abrangência.")
   + stats([("35", "+", "operadoras utilizam nossos produtos e serviços"), ("12", "h", "de suporte por dia útil (07h30–19h30)"), ("PTU 3.5", "", "versão certificada para intercâmbio nacional")])),
  sec(head("Atividades", "Principais atividades do <strong>serviço de conectividade</strong>")
   + cards([
     {"icon": "face", "title": "Elegibilidade com cartão e biometria", "text": "Monitoramento e aplicação de políticas de utilização sobre o módulo de biometria e a leitura de cartões durante o registro do atendimento e a realização de procedimentos."},
     {"icon": "file", "title": "Faturamento eletrônico", "text": "Acompanhamento e garantia do envio/geração de cobranças dos serviços prestados pela rede credenciada, conforme calendário de faturamento e regras definidas pela operadora."},
     {"icon": "graduation", "title": "Treinamento e reciclagem", "text": "Treinamento técnico e operacional sobre as funcionalidades do sistema, legislações e melhores práticas no processo de atendimento dos prestadores, com avaliação periódica em ambiente e-learning."},
     {"icon": "rocket", "title": "Implantação", "text": "Para novos prestadores ou conforme dimensionamento da rede credenciada, consolidação do ambiente necessário ao atendimento do beneficiário — remotamente ou in loco, conforme pré-condições estabelecidas."},
     {"icon": "monitor", "title": "Suporte remoto", "text": "Suporte operacional sobre o uso do sistema, do ambiente de execução de biometria e suporte técnico ao ambiente do prestador, sobre os componentes necessários ao módulo de biometria."},
     {"icon": "phone", "title": "Central de atendimento integrada", "text": "Suporte por e-mail, telefone, sistema de chamados e ferramentas de comunicação via internet, de segunda a sexta-feira, das 07h30 às 19h30, com serviço de call-back sem ônus para o prestador."},
   ], 3), "trix-section--sand"),
  sec('<div class="trix-split"><div class="trix-reveal"><span class="trix-kicker">Call-back</span><h2>Ligação de retorno <strong>sem custo</strong> para o prestador</h2><p>Em caso de contato por telefone, os prestadores podem optar pelo serviço de call-back, que permite a comunicação com o suporte da Trix TI por telefone fixo sem ônus. A ligação é realizada a partir do acesso do prestador ao site da Trix, que informa o seu telefone e aguarda a ligação automática realizada pelo ambiente da Trix TI.</p><p>A implantação do SAW em novos prestadores credenciados em todo o território nacional é realizada conforme dimensionamento da operadora, remotamente ou no próprio ambiente do prestador.</p></div><div class="trix-feature__media trix-feature__media--plain trix-reveal"><img src="{{media:images/29.png}}" alt="Suporte e consultoria especializada" loading="lazy"></div></div>'),
  contact_band(),
 ]), meta=["+35 operadoras", "Elegibilidade, TISS e intercâmbio", "Suporte com call-back gratuito"], image="images/30.png", order=2,
 faq_items=[("O que é o serviço de conectividade em saúde suplementar?", "É o conjunto de tecnologias, recursos e equipe especializada que garante a troca de informação entre prestadores e operadoras: elegibilidade, autorização, faturamento TISS, treinamento e suporte."), ("Qual o horário do suporte aos prestadores?", "De segunda a sexta-feira, das 07h30 às 19h30, por e-mail, telefone, sistema de chamados e ferramentas de comunicação via internet, com call-back gratuito.")])
CONSULT = svc("consultoria",
 "Consultoria em TI: outsourcing, segurança e APF | Trix TI",
 "Outsourcing com KPIs/SLAs, segurança da informação, análise de pontos de função (IFPUG), modelagem de processos (BPMN), open source e engenharia de software.",
 "Consultoria", "Eficiência e resolutividade <strong>para o seu segmento</strong>",
 "A Trix TI possui uma equipe de profissionais altamente qualificados, com experiência em ambientes heterogêneos, para oferecer aos nossos clientes a eficiência e a resolutividade necessárias ao seu segmento de negócio.",
 "".join([
  sec(cards([
     {"icon": "users", "title": "Outsourcing", "text": "Gestão dos serviços de infraestrutura de TI com fornecimento de mão de obra especializada, alcançando índices e níveis de atendimento (KPIs/SLAs) e promovendo a excelência dos serviços prestados."},
     {"icon": "shield", "title": "Segurança da informação", "text": "A informação é o bem mais precioso de qualquer empresa. Implementamos segurança e backup em ambientes complexos, com soluções robustas e preparadas para os novos tipos de fraudes digitais que surgem diariamente."},
     {"icon": "chart", "title": "APF — Análise de Pontos de Função", "text": "Medição de projetos de software pelo método IFPUG: auditoria de projetos medidos com APF, viabilidade de contratação por pontos de função, estimativa de custo e recursos, treinamento e implantação de processo de medição."},
     {"icon": "flow", "title": "Mapeamento e modelagem de processos", "text": "Entendimento e melhoria contínua por meio da modelagem e redesenho de processos com EPC, UML, BPMN e SPEM, identificando falhas operacionais e estabelecendo métricas para os envolvidos."},
     {"icon": "code", "title": "Soluções open source", "text": "Tecnologias abertas presentes em todos os segmentos: potencial competitivo para alavancar a organização sem onerar o cliente nem comprometer outros recursos."},
     {"icon": "layers", "title": "Engenharia de software", "text": "Concepção e implementação de soluções customizadas, conforme as suas necessidades, com métodos ágeis e ferramentas que garantem aderência às expectativas."},
   ], 3)),
  sec('<div class="trix-split"><div class="trix-reveal"><span class="trix-kicker">Medição consistente</span><h2>“A APF deve ser uma medida consistente entre <strong>vários projetos e organizações</strong>.”</h2><p>A Análise de Pontos de Função estabelece uma medida de tamanho conforme a funcionalidade implementada e a visão do usuário, possibilitando mensurar o tamanho do pacote adquirido, o esforço e a produtividade alcançada. A Trix TI provê mão de obra especializada para execução de serviços e consultoria em auditoria de projetos, análise de viabilidade de contratação por pontos de função, estimativas e definição e implantação de processo de medição.</p></div><div class="trix-reveal">' + cards([{"icon": "award", "title": "Por que a consultoria Trix", "text": "Equipe multidisciplinar com vivência em operadoras de saúde, órgãos públicos e empresas privadas; metodologias reconhecidas (IFPUG, BPM, PMBOK); e foco em resultado mensurável."}], 1, tilt=False) + '</div></div>', "trix-section--sand"),
  contact_band(),
 ]), meta=["Outsourcing com KPIs/SLAs", "APF pelo método IFPUG", "BPMN, UML, EPC e SPEM"], image="images/29.png", order=3)
ESCRIT = svc("escritorio-de-processos",
 "Escritório de Processos: BPM e metodologia MTRIX | Trix TI",
 "BPM, governança, gestão de processos, de TI e estratégica com a metodologia MTRIX e monitoramento assistido durante e após a implantação.",
 "Escritório de processos", "Alinhamento com os <strong>objetivos do seu negócio</strong>",
 "A Trix TI conta com uma estrutura de Escritório de Processos que apoia o desenvolvimento e a evolução das soluções, aplicando a cada produto os benefícios propostos pelo BPM e pelas boas práticas de governança, gestão de processos, gestão de TI e gestão estratégica. O principal benefício é o alinhamento com os objetivos, expectativas e necessidades dos nossos clientes.",
 "".join([
  sec('<div class="trix-split"><div class="trix-reveal"><span class="trix-kicker">Metodologia de gestão</span><h2>MTRIX: do <strong>estratégico ao operacional</strong></h2><p>A Metodologia de Gestão do Escritório de Processos MTRIX, adotada pela Trix TI, também é implementada como fruto de consultoria nas empresas. Abrange aspectos do âmbito estratégico, tático, operacional e de controle, e é apoiada pelo serviço de monitoramento assistido durante e após a implantação.</p><p>Uma das premissas deste serviço é assegurar que as boas práticas de gestão de processos sejam implementadas no ambiente do cliente e entre os profissionais, garantindo a qualidade e o foco na melhoria contínua dos processos da empresa.</p></div><div class="trix-reveal">'
      + cards([{"icon": "target", "title": "Estratégico", "text": "Objetivos, indicadores e alinhamento da TI ao negócio."}, {"icon": "flow", "title": "Tático", "text": "Desenho, priorização e governança dos processos críticos."}, {"icon": "gear", "title": "Operacional", "text": "Execução, automação e treinamento das equipes."}, {"icon": "chart", "title": "Controle", "text": "Monitoramento assistido, métricas e melhoria contínua."}], 2, tilt=False) + '</div></div>'),
  sec(head("Entregas", "O que o Escritório de Processos <strong>entrega</strong>", "", True) + checks(["Mapa de processos (AS-IS) e redesenho (TO-BE) em BPMN", "Modelo de governança com papéis, responsabilidades e rituais", "Indicadores de desempenho e painéis de acompanhamento", "Automação de fluxos com as soluções Trix (gestão de demandas, projetos e qualidade)", "Capacitação das equipes e monitoramento assistido pós-implantação"]), "trix-section--sand"),
  contact_band(),
 ]), meta=["Metodologia MTRIX", "BPM e governança", "Monitoramento assistido"], order=4)
SOLUC = svc("solucoes-e-automacao",
 "Soluções e automação de processos | Trix TI",
 "Gestão de demandas, projetos (PMBOK), qualidade e testes, loja virtual e monitoramento de serviços de TI, com ferramentas de mercado customizadas.",
 "Soluções e automação", "Automatize processos com ferramentas <strong>reconhecidas pelo mercado</strong>",
 "A Trix TI fornece a automação de processos a partir da implantação e customização de ferramentas de apoio às atividades nas diversas áreas da organização. As ferramentas são reconhecidas e utilizadas por empresas de todos os segmentos e podem ser formatadas para a sua realidade.",
 "".join([
  sec(cards([
     {"icon": "bell", "title": "Gestão de demandas", "text": "Controle das demandas internas e externas de atendimento, de qualquer parte da organização. Envolve áreas, clientes e fornecedores em um meio de comunicação eficaz, reduzindo custos e estabelecendo níveis de garantia de serviço."},
     {"icon": "briefcase", "title": "Gestão de projetos", "text": "Para organizações que praticam a gestão de projetos (PMBOK): planejamento, execução e monitoramento em ambiente web, integrado a recursos de multimídia, comunicação organizacional e ferramentas específicas da metodologia."},
     {"icon": "check", "title": "Gestão da qualidade", "text": "Ferramentas de apoio ao desenvolvimento que se adaptam aos processos do cliente: gestão de projetos de testes, controle de erros e bugs, planejamento, execução e monitoramento de testes e gráficos de indicadores — integradas e sincronizadas."},
     {"icon": "cart", "title": "Loja virtual", "text": "Solução de e-commerce personalizada para alavancar vendas, com recursos para gestão financeira, controle de correspondências e mecanismos de pagamento online."},
     {"icon": "activity", "title": "Monitoramento de serviços em TI", "text": "Acompanhamento contínuo dos serviços disponíveis no ambiente de rede, identificando e alertando os responsáveis sobre limites de operação, falhas de comunicação, violações de políticas de segurança e indisponibilidade."},
     {"icon": "brain", "title": "Automação com IA", "text": "Assistentes conversacionais, classificação de documentos e apoio à decisão integrados aos seus fluxos — com governança definida pela nossa Política de IA responsável."},
   ], 3)),
  contact_band(),
 ]), meta=["Ferramentas de mercado customizadas", "Integração com sistemas legados", "Implantação e treinamento"], order=5)
AGENDA = svc("agenda-medica",
 "Agenda Médica: central de atendimento para clínicas | Trix TI",
 "Central receptiva e ativa para marcações e cancelamentos, avisos por SMS e e-mail, número 4005 nacional e relatórios mensais, com valor mensal fixo.",
 "Agenda médica", "Excelência na gestão do seu tempo. <strong>Perfeito para quem não tem!</strong>",
 "Com a Agenda Médica da Trix TI você tem controle online dos seus compromissos e uma Central de Atendimento para atender o seu paciente, realizar todas as ações necessárias para satisfazê-lo e promover o bom funcionamento do seu estabelecimento — com um pequeno investimento mensal de valor fixo.",
 "".join([
  sec('<div class="trix-split"><div class="trix-reveal"><span class="trix-kicker">Benefícios</span><h2>Menos custo, <strong>mais atendimento</strong></h2>' + checks(["Redução de custos de infraestrutura e telefonia", "Gravação de todas as ligações telefônicas", "Acesso ininterrupto pela internet: mobilidade e praticidade", "Mão de obra especializada, com conhecimento da área médica e das legislações", "Baixo investimento mensal com valor fixo", "Marcação e cancelamento de horários pela Central de Atendimento Trix TI", "Suporte ao paciente para esclarecimento de dúvidas e marcação de consultas"]) + '</div><div class="trix-reveal"><span class="trix-kicker">Características</span><h2>Tudo que sua agenda <strong>precisa</strong></h2>' + checks(["Call center receptivo e ativo", "Avisos de marcações e cancelamentos por SMS* e e-mail", "Número de telefone 4005 para atendimento em todo o Brasil", "Contact center para pacientes especiais e casos de urgência/emergência*", "Controle e relatórios de pacientes", "Encaixe, fila de espera e remarcações", "Interface web intuitiva, compatível com todos os navegadores", "Configurações de agendas particular, Unimed e outros convênios", "Relatórios mensais de estatísticas de atendimento e utilização de recursos"]) + '<p class="trix-muted" style="font-size:.85rem">* Conforme plano contratado.</p></div></div>'),
  sec(cta("Quer ir além da agenda?", "Conheça o Prontow: agenda, prontuário eletrônico, financeiro, TISS e agendamento por WhatsApp com IA, tudo na nuvem.", ("Conhecer o Prontow", "/produtos/prontow/"), ("Contratar a Agenda Médica", "/contato/?assunto=Agenda%20M%C3%A9dica")), "trix-section--sand"),
  contact_band(),
 ]), meta=["Central de atendimento própria", "Número 4005 nacional", "Valor mensal fixo"], order=6,
 faq_items=[("Como o paciente é avisado das marcações?", "Por e-mail e SMS (conforme plano), com confirmação e lembretes gerados pela central."), ("A agenda funciona com convênios?", "Sim. Suporta configurações de agendas de atendimento particular, Unimed e outros convênios.")])
ORACLE = svc("oracle-partner",
 "Oracle Partner: cloud, banco de dados e integração | Trix TI",
 "Membro do Oracle PartnerNetwork: OCI, banco de dados Oracle, integração de sistemas, aplicações corporativas, analytics, segurança e compliance.",
 "Oracle PartnerNetwork", "Soluções Oracle com a <strong>expertise da Trix</strong>",
 "A Trix Tecnologia Inteligente é membro do Oracle PartnerNetwork (OPN), fortalecendo sua presença no mercado com soluções baseadas em uma das plataformas mais avançadas e confiáveis do mundo. Com essa frente comercial, unimos nossa expertise em conectividade, saúde e transformação digital à solidez das soluções Oracle.",
 "".join([
  sec('<div class="trix-center trix-reveal" style="margin-bottom:32px"><img src="{{media:images/logo_oracle.png}}" alt="Oracle PartnerNetwork" style="max-height:80px" loading="lazy"></div>'
   + cards([
     {"icon": "cloud", "title": "Cloud Infrastructure (OCI)", "text": "Consultoria, implantação e gestão de ambientes em nuvem Oracle, com alta disponibilidade e segurança avançada."},
     {"icon": "database", "title": "Banco de dados Oracle", "text": "Administração, migração, tuning de performance, backup e recuperação de desastres para bancos de dados críticos."},
     {"icon": "plug", "title": "Integração de sistemas", "text": "Uso de tecnologias Oracle para conectar aplicações, sistemas legados e novos serviços digitais."},
     {"icon": "briefcase", "title": "Aplicações corporativas", "text": "Suporte e customização em soluções empresariais baseadas no ecossistema Oracle."},
     {"icon": "chart", "title": "Analytics e big data", "text": "Implementação de soluções para análise de dados em larga escala, inteligência de negócios e insights estratégicos."},
     {"icon": "shield", "title": "Segurança e compliance", "text": "Configuração de ferramentas Oracle para atender a requisitos regulatórios em saúde suplementar e outros setores."},
   ], 3)),
  sec(head("Nossa essência", "Conectividade, saúde e <strong>transformação digital</strong>", "Garantimos resultados consistentes e sustentáveis para operadoras, hospitais, clínicas e empresas de diferentes setores — combinando o conhecimento de negócio da Trix com a robustez da plataforma Oracle.", True), "trix-section--dark"),
  contact_band(),
 ]), meta=["Membro do Oracle PartnerNetwork", "OCI, Database e integração", "Saúde suplementar e outros setores"], image="images/logo_oracle.png", order=7)
for _p, _k, _a in ((FABRICA, "images/22.png", "Software sob medida"), (CONECT, "images/30.png", "Sistema de Atendimento Web"), (CONSULT, "images/29.png", "Consultoria especializada"), (ORACLE, "images/logo_oracle.png", "Oracle PartnerNetwork")):
    _p["hero_img"] = "{{media:%s}}" % _k; _p["hero_img_alt"] = _a
PAGES = [HUB, FABRICA, CONECT, CONSULT, ESCRIT, SOLUC, AGENDA, ORACLE]
