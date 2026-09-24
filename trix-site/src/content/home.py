from .helpers import *
PAGES = [{
 "slug": "home", "order": -1000, "wp_title": "Home",
 "title": "Trix Tecnologia Inteligente | Software sob medida, conectividade em saúde e IA",
 "description": "Desde 2009, a Trix TI cria software sob medida, conecta operadoras e prestadores de saúde (TISS) e entrega produtos com inteligência artificial como o Prontow, o SAW e o Aspect Face. Brasília e São Paulo, atendimento em todo o Brasil.",
 "image": "images/02.jpg", "type": "about", "hero": "dark", "hero_short": False, "three": "network",
 "kicker": "Tecnologia inteligente desde 2009",
 "h1": "Software sob medida <strong>para o seu negócio</strong>",
 "lead": "A Trix TI transforma paixão por tecnologia em produtos e serviços que entregam resultado: fábrica de software com metodologia própria, conectividade em saúde suplementar, consultoria especializada e soluções com inteligência artificial usadas por milhares de pessoas Brasil afora.",
 "actions": [("Conhecer os produtos", "/produtos/"), ("Falar com um consultor", "/contato/", "trix-btn--ghost")],
 "meta": ["17 anos de mercado", "+35 operadoras de saúde atendidas", "Adequada à LGPD", "Membro Oracle PartnerNetwork"],
 "faq": [
   {"q": "O que a Trix Tecnologia Inteligente faz?", "a": "A Trix TI é uma empresa de tecnologia de Brasília, fundada em 2009, que desenvolve software sob medida (fábrica de software), presta serviços de conectividade em saúde suplementar, consultoria e escritório de processos, e mantém produtos próprios como Prontow, SAW, Portal Operadora, GEDAI, Integrador, Intranet, Aspect Face e Xield."},
   {"q": "A Trix atende empresas fora de Brasília?", "a": "Sim. A Trix possui matriz em Brasília (DF) e escritório em São Paulo (SP) e atende clientes em todo o Brasil, com implantação remota ou presencial e suporte de segunda a sexta-feira."},
   {"q": "Como solicitar uma demonstração ou orçamento?", "a": "Basta usar o formulário de contato, o WhatsApp ou o telefone (61) 3403-5353. Um consultor entra em contato para entender o cenário e propor a solução mais adequada, sem compromisso."}
 ],
 "body": "".join([
  sec(head("Por que a Trix", "Tecnologia é <strong>estratégia</strong>. Nós tratamos assim.", "Somos uma empresa dinâmica, focada na criação de produtos e serviços para diversos segmentos do mercado. Provemos soluções adaptáveis e escaláveis, alinhadas às tendências da indústria tecnológica, e agregamos conceitos, metodologias e boas práticas para a melhoria contínua dos processos das organizações.")
   + stats([("17", "", "anos de história"), ("35", "+", "operadoras de saúde"), ("8", "", "produtos próprios"), ("2", "", "escritórios: Brasília e São Paulo")])
   + '<div style="height:32px"></div>'
   + cards([
     {"icon": "code", "title": "Fábrica de software", "text": "Metodologia PROTRIX de desenvolvimento e manutenção: requisitos, análise, codificação e testes com gestão de demandas e projetos.", "url": "/servicos/fabrica-de-software/"},
     {"icon": "network", "title": "Conectividade em saúde", "text": "Elegibilidade com cartão e biometria, faturamento eletrônico TISS, implantação, treinamento e suporte remoto para prestadores e operadoras.", "url": "/servicos/conectividade-em-saude/"},
     {"icon": "briefcase", "title": "Consultoria", "text": "Outsourcing de TI, segurança da informação, análise de pontos de função, modelagem de processos, software livre e engenharia de software.", "url": "/servicos/consultoria/"},
     {"icon": "flow", "title": "Escritório de processos", "text": "BPM, governança, gestão de processos, gestão de TI e gestão estratégica com a metodologia MTRIX e monitoramento assistido.", "url": "/servicos/escritorio-de-processos/"},
     {"icon": "gear", "title": "Soluções e automação", "text": "Gestão de demandas, projetos, qualidade, loja virtual e monitoramento de serviços de TI formatados para a sua organização.", "url": "/servicos/solucoes-e-automacao/"},
     {"icon": "cloud", "title": "Oracle Partner", "text": "Membro do Oracle PartnerNetwork: cloud (OCI), banco de dados, integração, analytics, segurança e compliance no ecossistema Oracle.", "url": "/servicos/oracle-partner/"},
   ], 3), "trix-section--sand", "servicos"),
  sec(head("Produtos", "Nossos produtos facilitam a vida de <strong>milhares de pessoas</strong> Brasil afora", "Transformamos a nossa paixão por tecnologia em produtos que agregam valor e entregam resultados. Conheça as soluções que já estão em operação em operadoras, clínicas, hospitais e empresas de todos os portes.")
   + cards([
     {"icon": "stethoscope", "title": "Prontow", "badge": "IA + WhatsApp", "text": "Gestão para clínicas, consultórios e laboratórios: agenda, prontuário eletrônico, financeiro, TISS, telemedicina, agendamento por WhatsApp com IA e sugestão diagnóstica.", "url": "/produtos/prontow/", "cta": "Conhecer o Prontow"},
     {"icon": "monitor", "title": "SAW — Sistema de Atendimento Web", "text": "Integra todos os envolvidos no atendimento a beneficiários de planos de saúde: auditoria médica, intercâmbio, TISS, elegibilidade e faturamento eletrônico.", "url": "/produtos/saw/"},
     {"icon": "face", "title": "Aspect Face", "badge": "Antifraude", "text": "API de reconhecimento facial com prova de vida, identificação 1:1, 1:N e N:N, integração via Swagger e serviço antifraude com IA.", "url": "/produtos/aspect-face/"},
     {"icon": "lock", "title": "Xield", "badge": "Novo", "text": "Segurança e automação inteligente com reconhecimento facial: controle de acesso, mapa de presença e integração com IoT (RTSP, Matter, Zigbee, MQTT).", "url": "/produtos/xield/"},
     {"icon": "file", "title": "GEDAI", "text": "Gerenciador Eletrônico de Documentos e Arquivo Inteligente: GED/ECM, workflow, OCR, bureau de digitalização e controle de correspondências.", "url": "/produtos/gedai/"},
     {"icon": "plug", "title": "Integrador", "text": "Integração de sistemas e bases de dados heterogêneas com comunicação em tempo real, independente do provedor da solução.", "url": "/produtos/integrador/"},
   ], 3)
   + '<div class="trix-actions" style="justify-content:center"><a class="trix-btn trix-btn--dark" href="/produtos/">Ver todos os produtos</a></div>', "", "produtos"),
  sec('<div class="trix-split">'
      + '<div class="trix-reveal"><span class="trix-kicker">Saúde suplementar</span><h2>Conectando <strong>operadoras e prestadores</strong> com segurança</h2><p class="lead">Nossos sistemas operam no coração do atendimento a beneficiários: elegibilidade com biometria, autorização, auditoria, intercâmbio e faturamento no padrão TISS da ANS.</p>'
      + checks(["SAW certificado no PTU 3.5 para intercâmbio nacional", "Faturamento eletrônico para prestadores com ou sem sistema próprio", "Suporte de segunda a sexta, das 07h30 às 19h30, com call-back gratuito", "Central de atendimento, treinamento e reciclagem contínua"])
      + '<div class="trix-actions"><a class="trix-btn trix-btn--primary" href="/servicos/conectividade-em-saude/">Conectividade em saúde</a><a class="trix-btn trix-btn--ghost" href="/produtos/saw/">Conhecer o SAW</a></div></div>'
      + '<div class="trix-feature__media trix-feature__media--plain trix-reveal"><img src="{{media:images/30.png}}" alt="Sistema de Atendimento Web (SAW)" loading="lazy"></div></div>', "trix-section--dark"),
  sec(head("Empresa", "Missão, <strong>visão</strong> e valores", "", True)
   + cards([
     {"icon": "rocket", "title": "Missão", "text": "Criar soluções inovadoras que agregam valor aos negócios dos nossos clientes, promovendo a escalabilidade, resiliência e resolutividade esperadas."},
     {"icon": "eye", "title": "Visão", "text": "Ser reconhecida pela satisfação dos nossos clientes e pela qualidade dos produtos e serviços oferecidos, estabelecendo alianças que formam um relacionamento contínuo e próspero em todos os sentidos."},
     {"icon": "heart", "title": "Valores", "text": "<strong>Ética</strong>, <strong>compromisso</strong> e <strong>competência</strong> — reforçados pelos princípios de conduta: honestidade, responsabilidade, respeito, organização e valorização."},
   ], 3)
   + '<div class="trix-actions" style="justify-content:center"><a class="trix-btn trix-btn--ghost" href="/empresa/">Conheça a Trix</a><a class="trix-btn trix-btn--ghost" href="/conformidade/">Central de Conformidade</a></div>', "trix-section--sand", "empresa"),
  sec(head("Confiança", "Quem <strong>confia</strong> na Trix", "Na área de saúde e conectividade em saúde, atendemos pequenas, médias e grandes operadoras, Unimeds, RHs e autogestões em saúde em todo o Brasil — além de prestadores de serviços médicos, consultórios, clínicas e laboratórios.", True)
   + '<div class="trix-logos trix-reveal">' + "".join('<figure><img src="{{media:images/unimed/%s}}" alt="Cliente Trix" loading="lazy"></figure>' % n for n in ["70.png", "40.png", "41.png", "42.png", "43.png", "45.png", "46.png", "47.png", "49.png", "50.png", "51.png", "52.png"]) + '<figure><img src="{{media:images/economus.jpg}}" alt="Economus" loading="lazy"></figure></div>'
   + '<div class="trix-actions" style="justify-content:center"><a class="trix-btn trix-btn--dark" href="/clientes/">Ver todos os clientes</a></div>'),
  sec('<div class="trix-split"><div class="trix-reveal"><span class="trix-kicker">Governança</span><h2>Compliance, LGPD e segurança <strong>não são discurso</strong></h2><p class="lead">Comitês de Compliance, Proteção de Dados e Segurança e Qualidade atuam de forma permanente, com DPO nomeado, código de conduta, políticas publicadas, treinamentos na Trix Academy e canais de denúncia confidenciais.</p>'
      + checks(["Portal de Proteção de Dados e Política de Privacidade públicas", "Canal LGPD para titulares exercerem seus direitos", "Ouvidoria 0800 941 1190 e canal de denúncias anônimo", "Políticas de Segurança da Informação, ESG e IA responsável"])
      + '<div class="trix-actions"><a class="trix-btn trix-btn--primary" href="/conformidade/">Central de Conformidade</a></div></div>'
      + '<div class="trix-grid trix-grid--2"><div class="trix-card trix-card--yellow trix-reveal"><div class="trix-card__icon">{{icon:lock}}</div><h3>Canal LGPD</h3><p>Acesso, correção, eliminação, portabilidade e revogação de consentimento.</p><a class="trix-card__link" href="/canal-lgpd/">Exercer direitos</a></div><div class="trix-card trix-card--dark trix-reveal"><div class="trix-card__icon">{{icon:megaphone}}</div><h3>Canal de Compliance</h3><p>Denúncias, dúvidas, sugestões e elogios com opção de anonimato.</p><a class="trix-card__link" href="/canal-de-compliance/">Fazer um relato</a></div></div></div>', "trix-section--sand"),
  sec(head("Perguntas frequentes", "Dúvidas <strong>comuns</strong>", "", True) + faq([
     ("O que a Trix Tecnologia Inteligente faz?", "A Trix TI é uma empresa de tecnologia de Brasília, fundada em 2009, que desenvolve software sob medida (fábrica de software), presta serviços de conectividade em saúde suplementar, consultoria e escritório de processos, e mantém produtos próprios como Prontow, SAW, Portal Operadora, GEDAI, Integrador, Intranet, Aspect Face e Xield."),
     ("A Trix atende empresas fora de Brasília?", "Sim. Temos matriz em Brasília (DF) e escritório em São Paulo (SP) e atendemos clientes em todo o Brasil, com implantação remota ou presencial e suporte de segunda a sexta-feira."),
     ("Como solicitar uma demonstração ou orçamento?", "Use o formulário de contato, o WhatsApp ou o telefone (61) 3403-5353. Um consultor entra em contato para entender o cenário e propor a solução mais adequada, sem compromisso."),
   ])),
  contact_band(),
 ]),
}]
