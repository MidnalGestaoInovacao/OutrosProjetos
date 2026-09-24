# Trix TI – Inventário do site atual (trixti.com.br)

Levantamento feito em 24/09/2026 a partir das páginas públicas de https://trixti.com.br. Serve de base para a migração para o WordPress em trix.ebaem.com.br. Nada foi publicado no WordPress; este documento é apenas o registro do que existe hoje.

O documento irmão [02-proposta-novo-site.md](02-proposta-novo-site.md) traz a estrutura proposta e os textos novos.

---

## 1. Visão geral técnica

| Item | Situação atual |
|---|---|
| Plataforma | Páginas PHP estáticas (`index.php`, `fabrica.php` etc.), sem CMS |
| Front-end | Bootstrap 3.3.6, jQuery 1.11.3, LayerSlider, Owl Carousel, Swiper 11, jQuery Modal, Font Awesome 4.5 |
| Fontes | Open Sans (Light, Regular, Bold) servidas localmente |
| Analytics | Google Analytics Universal (`UA-163474009-1`, descontinuado pelo Google) e script do Google AdSense |
| Formulários | Formulário de contato com Google reCAPTCHA v2 (site key no HTML) |
| SEO | `sitemap.xml` com 16 URLs e `robots.txt` (bloqueia `/includes/`, `/intranet.php`, `/OLD_index.php`). Só a página do Prontow tem `meta description`, `keywords` e Open Graph; as demais não têm nenhuma meta tag |
| Pop-up na home | Modal com banner do "1º Webinário de Conectividade" (evento de março de 2022), ainda ativo |
| Cookies | Barra simples "Trix e os cookies" com link para a Política de Privacidade |
| Subsites | `/lgpd/` (Portal de Proteção de Dados, HTML próprio) e `/webinario/` (landing page de 2022) |
| Redes sociais | Facebook (link real: pt-br.facebook.com/trixti); Twitter e LinkedIn apontam para `#` (vazios) |
| WhatsApp | Botão flutuante para (61) 99232-4516; na página do Prontow o link é (61) 3246-1800 |

## 2. Mapa de páginas

Todas as páginas compartilham o mesmo cabeçalho (menu), rodapé (contato, endereços, formulário, links LGPD) e o bloco "produtos – nossos produtos facilitam a vida de milhares de pessoas brasil afora".

| # | URL atual | Título da aba | Menu | Conteúdo principal |
|---|---|---|---|---|
| 1 | `/` (`index.php`) | Trix Tecnologia Inteligente - Home | home | Slider com 3 chamadas, Empresa (carrossel com visão, valores, missão), Serviços (5 cards), Produtos (carrossel de logos), destaque "Portal Unimed", Contato |
| 2 | `/fabrica.php` | … - Fábrica de Software | serviços | Metodologia PROTRIX, soluções customizadas, benefícios, serviços, tecnologias |
| 3 | `/agenda.php` | … - Agenda Médica | serviços | Serviço de central de atendimento para agenda médica |
| 4 | `/solucoes.php` | … - Soluções e Automação | serviços | Gestão de demandas, projetos, qualidade, loja virtual, monitoramento de TI |
| 5 | `/consultoria.php` | … - Consultoria | serviços | Outsourcing, segurança da informação, APF, mapeamento de processos, open source, engenharia de software |
| 6 | `/conectividade.php` | … - Conectividade | serviços | Conectividade em saúde suplementar: elegibilidade, faturamento, treinamento, implantação, suporte |
| 7 | `/escritorio.php` | … - Escritorio de Processos | serviços | Escritório de processos e metodologia MTRIX |
| 8 | `/oracle.php` | … - Oracle Partner | serviços | Membro do Oracle PartnerNetwork; 6 frentes de soluções Oracle |
| 9 | `/prontow.php` | Prontow — Software com IA para Clínicas… | produtos | Página mais completa e recente do site; módulos do Prontow |
| 10 | `/saw.php` | … - SAW | produtos | Sistema de Atendimento Web para operadoras |
| 11 | `/gedai.php` | … - GEDAI | produtos | GED/ECM com bureau de digitalização |
| 12 | `/integrador.php` | … - Integrador | produtos | Integração de sistemas (texto de 2 parágrafos) |
| 13 | `/biometriafacial.php` | … - Biometria Facial | produtos ("aspect face") | API Aspect Face de reconhecimento facial |
| 14 | `/xield.php` | … - XIELD | produtos | Controle de acesso e automação com reconhecimento facial |
| 15 | `/portal.php` | … - Portal Operadora | fora do menu (comentado); linkado na home | Cópia literal do texto do SAW |
| 16 | `/intranet.php` | … - Intranet | fora do menu; linkado no carrossel de produtos; bloqueado no robots.txt | Intranet Trix, prêmio Unimed de Comunicação |
| 17 | `/clientes.php` | … - Clientes | clientes | Texto curto + grade com 39 logos de clientes |
| 18 | `/lgpd/` | TRIX - Portal de Proteção de Dados | rodapé | Compromisso LGPD, tratamento de dados, comitês, treinamento (Trix Academy), ouvidoria, formulário |
| 19 | `/lgpd/politicas.html` | Política de Privacidade | rodapé | Política de privacidade (54 KB de texto) |
| 20 | `/lgpd/TRIX - Código de Conduta da Empresa.pdf` | – | rodapé | PDF do Código de Conduta Ética |
| 21 | `/webinario/` | 1º WEBINÁRIO DE CONECTIVIDADE TRIX-TI | pop-up da home | Landing page do evento de 09 a 17/03/2022 com formulário de inscrição |

"Empresa" e "Contato" no menu não são páginas: abrem seções da home. `/empresa`, `/servicos`, `/produtos`, `/clientes`, `/contato` sem `.php` retornam 404.

## 3. Textos atuais (resumo por página)

### 3.1 Home

**Slider (3 slides)**
1. "software sob medida para seu negócio" – "A TRIX TI utiliza um processo de software que permite a organização e o gerenciamento dos produtos de forma ágil e organizada." → botão *saiba mais* (fabrica.php)
2. "conte com consultoria especializada" – "A TRIX TI possui uma equipe de profissionais altamente qualificados, para oferecer aos nossos clientes eficiência e resolutividade" → consultoria.php
3. "sistema de atendimento web" – "é uma ferramenta que integra todos os envolvidos no processo de atendimento a beneficiários de plano de saúde." → saw.php

**Empresa (carrossel)**
- Empresa: "Empresa dinâmica, focada na criação de produtos e serviços para os diversos segmentos do mercado. De uma maneira diferenciada provemos soluções adaptáveis e escaláveis, de acordo com as tendências da atual indústria tecnológica. Como entendemos que tecnologia é estratégia para qualquer organização, agregamos conceitos, metodologias e boas práticas para a melhoria continua de processos organizacionais."
- Visão: "Ser reconhecida pela satisfação dos nossos clientes e pela qualidade dos produtos e serviços oferecidos, estabelecendo alianças que formam um relacionamento continuo e próspero em todos os sentidos."
- Valores: Ética, Compromisso, Competência.
- Missão: "Criar soluções inovadoras que agregam valores aos negócios dos nossos clientes, promovendo a escalabilidade, resiliência e resolutividade esperada."

**Serviços (cards)**: Fábrica de Software ("Conheça a metodologia PROTRIX de desevolvimento" [sic]); Produtos e Soluções; Escritório de Processos ("BPM, Governança, Gestão de Processos, Gestão de TI e Gestão Estratégica"); Consultoria; Escritório Soluções e Automação (card sem descrição).

**Produtos**: "nossos produtos facilitam a vida de milhares de pessoas brasil afora. Nós transformamos a nossa paixão por tecnologia em produtos que agregam valor e entregam resultados." Carrossel de logos: SAW, GEDAI, Integrador, Intranet, Biometria. Destaque: "o portal unimed é uma solução criada para atender às necessidades das cooperativas unimed" → portal.php.

**Contato**: ver seção 6.

### 3.2 Serviços

**Fábrica de Software** – Metodologia PROTRIX "define, de forma detalhada, as responsabilidades, atividades e as interações com outras áreas de conhecimento", com desenvolvimento iterativo, gestão de demandas e gerência de projetos. Citação atribuída a Marcos Soares, Diretor Executivo. Formatos de projeto: projeto completo, especificação de requisitos, análise e design, codificação, testes, documentação de sistemas legados. Benefícios: solução sob medida, atendimento a necessidades específicas, profissionais experientes, alta capacidade técnica, foco em qualidade. Tecnologias listadas: Java, PHP, iOS; MySQL, PostgreSQL, Oracle, SQL Server; Ajax, JavaScript, XHTML, XML, Tableless; SOA, BPM, FDD, RUP, MPS.BR; JSF, JBoss Seam, RichFaces, A4J, Spring, Hibernate, Struts, Objective-C; UML; JBoss, Apache, Tomcat, WebSphere, GlassFish, SunOne.

**Agenda Médica** – "Excelência na gestão do seu tempo. Perfeito para quem não tem!" Central de atendimento terceirizada que marca e cancela consultas, avisa por e-mail e SMS, grava ligações, telefone 4005, relatórios mensais, suporte a agendas particular/Unimed/convênios, "baixo investimento mensal com valor fixo".

**Soluções e Automação** – Implantação e customização de ferramentas: Gestão de Demandas, Gestão de Projetos (PMBOK), Gestão da Qualidade (testes e bugs), Loja Virtual (e-commerce), Monitoramento de Serviços em TI.

**Consultoria** – Outsourcing (KPIs/SLAs), Segurança da Informação (backup, fraudes digitais), APF – Análise de Ponto de Função (IFPUG; auditoria, viabilidade, estimativas, treinamento, processo de medição), Mapeamento e Modelagem de Processos (EPC, UML, BPMN, SPEM), Soluções Open Source, Engenharia de Software (métodos ágeis).

**Conectividade** – "serviço de conectividade em saúde suplementar" ligando Prestador e Operadora. Atividades: Elegibilidade de beneficiários com cartão e biometria; Faturamento eletrônico; Treinamento e reciclagem (com e-learning); Implantação; Suporte remoto; Integração com a Central de Atendimento (e-mail, telefone, chamados, "Skype, MSN, Gtalk"; seg–sex 07:30–19:30; serviço "Call-back" gratuito). Frase de prova: "Mais de 35 OPERADORAS utilizam os nossos produtos e serviços!". Bloco final sobre implantação do SAW "conforme dimensionamento realizado pela Racers".

**Escritório de Processos** – Estrutura interna que aplica BPM, governança, gestão de processos, de TI e estratégica aos produtos. Metodologia de Gestão MTRIX oferecida como consultoria com "monitoramento assistido durante e após a implantação".

**Oracle Partner** – Membro do Oracle PartnerNetwork (OPN). Frentes: Cloud Infrastructure (OCI); Banco de Dados Oracle (administração, migração, tuning, backup e DR); Integração de Sistemas; Aplicações Corporativas; Analytics e Big Data; Segurança e Compliance. Fecho: "une sua expertise em conectividade, saúde e transformação digital à solidez das soluções Oracle… para operadoras, hospitais, clínicas e empresas de diferentes setores."

### 3.3 Produtos

**Prontow** – "sistema de gestão para clínicas e consultórios da Trix TI. Em um só lugar e na nuvem, ele reúne agenda, prontuário eletrônico, financeiro, faturamento TISS, atendimento por WhatsApp com IA e apoio ao diagnóstico por inteligência artificial". Módulos: Agenda; Agendamento por WhatsApp com IA (marca, remarca, cancela 24h; cadastra paciente novo; entende pedidos de exame e informa preparo; link de calendário; recebe fotos/PDF; multiclínica; confirmação de agenda com um toque); Atendimento e Prontuário Eletrônico (filas em tempo real; prontuário configurável por especialidade; prescrições, atestados, laudos, anexos; autorizações e pagamentos); Sugestão Diagnóstica por IA (diagnóstico diferencial, "Sugerir com IA", decisão sempre do médico); Mais recursos clínicos (painel de senhas com vídeos, exames/laudos com resultado online, prescrição digital Memed, assinatura digital ICP-Brasil A1/A3 BirdID/Valid com QR Code, atestados, templates); Financeiro (contas a pagar/receber, centros de custo, fluxo de caixa, NFS-e/NF-e); TISS e Faturamento (guias Consulta e SP/SADT, autorizações, elegibilidade em lote, lotes, versões XML ANS); Gestão e Administração (multiunidade, perfis, estoque, relatórios, trilha de auditoria LGPD, cadastros, 100% nuvem); Gestão de Saúde Populacional para operadoras (estratificação de risco, busca ativa, análise epidemiológica, linhas de cuidado, risco por contrato, indicadores de atenção primária); App do Paciente iOS/Android ("disponível em breve nas lojas"). CTA: "Solicite uma demonstração" – WhatsApp (61) 3246-1800.

**SAW – Sistema de Atendimento Web** – Integra atendentes, analistas de contas, cooperados/prestadores, empresas contratantes e beneficiários numa única interface web. Novidades citadas: Auditoria de Contas Hospitalares (Auditoria Técnica), Autorização e absenteísmo por SMS, Auditoria Inteligente, Guia Modelo. Seções: Auditoria médica avançada; Intercâmbio online e offline (certificado PTU 3.5, autorização automática por regras, ranking de intercâmbio); Arquitetura (Java, MVC; comercialização por direito de acesso ou aquisição); Integração personalizada com a base da operadora (via Integrador próprio, logs auditáveis); Elegibilidade e segurança em tempo real; Implementação completa do TISS (padrão de comunicação, conteúdo e estrutura, segurança, WebService XML); Faturamento eletrônico SAW.

**GEDAI – Gerenciador Eletrônico de Documentos e Arquivo Inteligente** – GED/ECM com Bureau de Digitalização (linha de produção) e sistema GEDAI. Conceitos: document management, document imaging, WCM, workflow, OCR/ICR, form processing, indexação, serialização, versionamento. Funcionamento por hierarquia e permissões; tramitação física registrada; ciclo de vida completo. Arquitetura Java/MVC. Bureau com endereçamento físico de caixas/depósitos ("depósito A, estante A-1, prateleira A1-15"). Características: perfis, integração com ERP/RH/GPS, lotes, atributos dinâmicos, pesquisa "estilo Google", controle de correspondências. Benefícios: produtividade, segurança, base de conhecimento, versionamento, mobilidade, redução de espaço físico.

**Integrador** – "permite a integração de sistemas e bases de dados heterogêneas realizando a comunicação em tempo real". Java, Postgres, MVC/Struts. Página muito curta.

**Aspect Face (Biometria Facial)** – API de reconhecimento facial "ÚNICA com tecnologia e serviço antifraude". Identificador 1:1, 1:N, N:N; prova de vida (liveness); integração via Swagger; gerenciador web de dados e fraudes; dashboards; baixo custo; sem limite de idade. Antifraude com IA + analistas humanos ("base de fraudes técnicas 99% confirmadas"). Site próprio: www.aspectface.com.

**Xield** – "Segurança e Automação Inteligente com Reconhecimento Facial". Plataforma 100% web, nuvem (SaaS) ou on-premises, multiunidade. Controle de acesso por biometria facial (portas sem toque, regras por perfil e horário). Mapa de presença em tempo real. Automação de ambientes (luzes, climatização, rotinas por perfil). Integrações: RTSP, Matter, Zigbee, Home Assistant, MQTT, câmeras, fechaduras, sensores, campainhas. Recursos: controle por cômodo, cadastro facial remoto por link, auditoria de eventos, dashboards. Casos de uso: hospitais/clínicas, residências/condomínios, empresas, educação.

**Portal Operadora** – Página com o mesmo texto do SAW (duplicado), imagem `img_port_opera.png`.

**Intranet Trix** – Ferramenta web de comunicação e administração interna; perfis por setor (RH, jornalismo, compras, almoxarifado, controladoria, financeiro, cadastro, diretorias); chat interno; tecnologia aberta; integração com legados. Benefícios: canal com beneficiários e prestadores, adequação à lei. Prêmio "Alberto Urquiza Wanderley" no 6º Prêmio Unimed de Comunicação (duas edições consecutivas; a de 2009 citada). Lista de funcionalidades por setor (com itens repetidos).

### 3.4 Clientes

Texto: "Para você que quer ser cliente saiba que: Desenvolver software é a nossa maior força! … Na área de saúde e conectividade em saúde, atendemos pequenas, médias e grandes Operadoras, Unimeds, RH's e autogestões em saúde, em todo Brasil. Também temos soluções em software especializadas para Prestadores de serviços médicos em saúde: profissionais médicos, consultórios/clínicas, CTM, laboratórios etc."

Logos (39, pelo atributo `alt`): Central Nacional Unimed, Economus, Intermed, Postal Saúde, FAMA Federação das Unimeds da Amazônia, Federação Minas, Unimed Anápolis, Unimed Aquidauana, Unimed Arapiraca, Unimed Ariquemes, Unimed Boa Vista, Unimed Cáceres, Unimed Campina Grande, Unimed Caruaru, Unimed Centro Oeste e Tocantins, Unimed Cerrado, Unimed Corumbá (grafado "Curumbá"), Unimed Curitiba, Unimed Dourados, Unimed Imperatriz, Unimed Macapá, Unimed Manaus, Unimed Oeste do Pará, Unimed Palmeira dos Índios, Unimed Picos, Unimed Regional de Floriano, Unimed Rio Branco, Unimed Rio Verde, Unimed Rondônia, Unimed Rondonópolis, Unimed São João Nepomuceno, Unimed São Paulo, Unimed Serra de Minas, Unimed Sul do Pará, Unimed Teresina, Unimed Três Lagoas, Unimed Ubá, Unimed Vale do Jauru, Unimed Vilhena.

### 3.5 Portal de Proteção de Dados (/lgpd/)

- Declaração: "Já estamos adequados com a LGPD – Lei Geral de Proteção de Dados."
- Blocos "Como tratamos seus dados" para Usuários, Colaboradores, Fornecedores e Parceiros.
- Três comitês com composição nominal: Compliance (Compliance Officer: Sanclé Landim Albuquerque; membros: Marcos Soares de Sousa, Nádia Regina Siqueira Alves, Herbert Oliveira); Proteção de Dados (DPO: Sanclé Landim Albuquerque; membros: Marcos Soares de Sousa, Daniel Rodrigo da Silva, Herbert Oliveira); Segurança e Qualidade (Security Officer: Sanclé Landim Albuquerque; membros: Marcos Soares de Sousa, Daniel Rodrigo da Silva, Nádia Regina).
- Canais: (61) 3403-5353; ouvidoria@trixti.com.br; dpo@trixti.com.br; seguranca@trixti.com.br; formulário web.
- Treinamento: plataforma Trix Academy (http://academy.trixti.com.br/).
- Ouvidoria: 0800 941 1190; formulário com categorias (sugestões, reclamações, elogios, solicitação de informação, de documentos, denúncias, segurança, ouvidoria, proteção de dados).
- Links: Política de Privacidade, Glossário, Código de Conduta (PDF).
- Rodapé com CEP "76.610-440" (diverge do "70.610-440" do site principal).

### 3.6 Webinário (/webinario/)

Landing page do "1º Webinário de Conectividade em Saúde Suplementar" (09 a 17/03/2022): temas Censo hospitalar, Nova TISS, Novo App Beneficiários, Racionalização Rol Unimed / Intercâmbio, Autorização e Elegibilidade; palestrantes Juliana (Gerente Operacional), Arlindo (Arquiteto de Software), Michel Popolin (Arquiteto de Software e Mobile), Clécio Pessoa (Coordenador de Suporte). Formulário de inscrição; contato webinario@trixti.com.br. Evento encerrado.

## 4. Serviços e produtos – lista consolidada

**Serviços (7):** Fábrica de Software (PROTRIX) · Agenda Médica · Soluções e Automação · Consultoria · Conectividade em Saúde Suplementar · Escritório de Processos (MTRIX) · Oracle Partner.

**Produtos (8, sendo 6 no menu):** Prontow · SAW · GEDAI · Integrador · Aspect Face · Xield · Portal Operadora (fora do menu, texto duplicado) · Intranet Trix (fora do menu).

## 5. Contatos e dados institucionais

| Dado | Valor no site |
|---|---|
| Razão/nome | Trix Tecnologia Inteligente ("Trix TI") |
| E-mail geral | falecom@trixti.com.br |
| E-mails setoriais | ouvidoria@trixti.com.br · dpo@trixti.com.br · seguranca@trixti.com.br · webinario@trixti.com.br |
| Matriz Brasília | Edifício Capital Financial Center, SIG Quadra 4, Lote 75, Bloco A, Sala 15, CEP 70.610-440, Brasília-DF · (61) 3403-5353 |
| Filial São Paulo | Alameda Santos, 1165, Cerqueira César, São Paulo-SP, CEP 01419-002 · (11) 3014-3220 |
| WhatsApp | (61) 99232-4516 (botão flutuante) · (61) 3246-1800 (Prontow) |
| Ouvidoria 0800 | 0800 941 1190 |
| Agenda Médica | Número 4005 (citado sem o restante) |
| Redes | Facebook pt-br.facebook.com/trixti · Twitter e LinkedIn sem link |
| Sites relacionados | aspectface.com · academy.trixti.com.br |
| Assuntos do formulário | Dúvida, Sugestão, Problema, Financeiro, Fábrica de Software, Agenda Médica, Treinamento e Capacitação, Soluções e Automação, Consultoria, Escritório de Processos, Suporte ao Prestador, Outro |

## 6. Inventário de imagens

Caminhos relativos a https://trixti.com.br/. Tamanhos obtidos por HEAD.

| Arquivo | Uso | Observação |
|---|---|---|
| `images/01.png` (9 KB) | Logo Trix no cabeçalho | Reaproveitar; pedir versão vetorial (SVG) |
| `images/39.png` (10 KB) | Logo Trix no rodapé | idem |
| `images/02.png`, `03.png`, `04.png` | Ícones Facebook, Twitter, LinkedIn | Substituir por ícones do tema |
| `images/06.png`, `07.png` | Setas do slider ("road") | Descartar |
| `images/12.png`, `13.png`, `14.png`, `15.png`, `16.png` | Ícones dos cards de serviços (produtos, fábrica, consultoria, processos) | Redesenhar em SVG |
| `images/22.png`, `29.png`, `30.png` (37–44 KB) | Imagens do slider da home | Baixa resolução para hero atual; substituir |
| `images/24.png`, `27.png` | Recortes de layout ("cut in layout") | Descartar |
| `images/08.png` SAW · `09.png` GEDAI · `10.png` Integrador · `11.png` Intranet · `logo_biometria.png` | Logos de produtos no carrossel | Reaproveitar (pedir vetores) |
| `images/prontow/prontow_logo.png`, `agenda.png`, `filas.png`, `fluxo-caixa.png`, `tiss-painel.png`, `appstore.png`, `gplay.png` | Página do Prontow (logo, telas, badges de loja) | Reaproveitar telas; badges só quando o app estiver publicado |
| `images/logo_aspect_face.png`, `logo_swagger.png`, `img_face.jpg`, `img_antifraude.jpg`, `img_prova_vida.jpg`, `img_swagger.jpg`, `img_gerenciador_web.jpg`, `img_dashboard.jpg`, `img_identificador.jpg` | Página Aspect Face | Reaproveitar |
| `images/xieldimg/xield_01.png` … `xield_08.png` | Página Xield | Reaproveitar |
| `images/img_port_opera.png` | Portal Operadora | Avaliar |
| `images/31.png` SAW · `32.png` GEDAI · `33.png` Integrador · `37.png` Intranet · `28.png` Fábrica · `logo_oracle.png` | Ilustrações de topo das páginas internas | Avaliar caso a caso |
| `images/desenvolvimento.jpg` (110 KB) | Página Clientes | Foto genérica; substituir |
| `images/economus.jpg`, `40.png`, `41.png`, `unimed/40.png` … `unimed/71.png`, `unimed/unimed-curitiba.jpg`, `unimed-sao-joao.jpg`, `unimed-serra-minas.jpg`, `unimed-uba.jpg` | 39 logos de clientes | Reaproveitar; padronizar tamanho e fundo |
| `images/20.png`, `21.png` | Fundos via CSS | Descartar |
| `images/banner-webinario-trixti.png` | Pop-up do webinário 2022 | Descartar |
| `lgpd/img/logo.png`, `comite-01..03.png`, `treinamento.png` | Portal LGPD | Reaproveitar ícones se couber |
| `webinario/assets/img/*.png` | Fotos dos palestrantes e patrocinadores | Descartar |
| `favicon.ico` | Favicon | Gerar novo conjunto (favicon + ícones de app) |

Não há imagens com `alt` descritivo além dos logos de clientes; a maioria tem `alt` vazio ou técnico ("road", "cut in layout").

## 7. Problemas encontrados no site atual

1. **Conteúdo desatualizado em destaque**: pop-up do webinário de março de 2022 ainda abre na home; ferramentas citadas no suporte (MSN, Gtalk) não existem mais; stack de tecnologias da Fábrica cita JBoss Seam, SunOne, Objective-C.
2. **Duplicação**: `portal.php` é cópia integral de `saw.php`; a seção "Elegibilidade" de Conectividade repete parágrafo da Fábrica; Intranet lista funcionalidades repetidas.
3. **Páginas órfãs**: Intranet e Portal Operadora estão fora do menu; Portal Operadora aparece só num destaque da home.
4. **SEO**: nenhuma meta description fora do Prontow; títulos genéricos; URLs `.php`; H1 múltiplos por página; Google Analytics Universal desativado; script AdSense sem propósito.
5. **Inconsistências de dados**: CEP 70.610-440 (site) vs 76.610-440 (LGPD); dois números de WhatsApp; Twitter e LinkedIn sem link; "Racers" citado sem contexto; número 4005 incompleto.
6. **Erros de texto**: "desevolvimento", "OUTSOURSING", "INDENTIFICADOR", "AespecFace", "Curumbá", "Opnião", "plateleira", "melhoria continua", "bug's", "KPI's".
7. **Posicionamento difuso**: a home fala de "diversos segmentos do mercado", mas clientes, conectividade, SAW, Prontow e Oracle mostram que o núcleo é saúde suplementar. Agenda Médica, Loja Virtual e Intranet diluem a mensagem.
8. **Prova social fraca**: 39 logos sem depoimentos, números ou casos; a frase "mais de 35 operadoras" fica escondida na página de Conectividade.
9. **Acessibilidade e mobile**: Bootstrap 3, imagens sem `alt`, slider com texto em imagem, formulário sem rótulos acessíveis.
10. **Chamadas para ação**: botões "saiba mais"/"contate um consultor" genéricos; só o Prontow tem CTA claro (demonstração via WhatsApp).
