# Trix TI – Proposta de estrutura e textos do novo site (trix.ebaem.com.br)

Proposta elaborada em 24/09/2026 com base no [inventário do site atual](01-inventario-site-atual.md). Nada aqui foi publicado no WordPress; é uma proposta para revisão.

Marcações **[confirmar]** indicam afirmações, números ou dados que precisam de validação da Trix antes de ir ao ar.

---

## 1. Posicionamento proposto

O site atual se apresenta como uma empresa de TI "para os diversos segmentos do mercado". Os clientes (39 logos, 35 deles Unimeds e operadoras), os produtos mais fortes (SAW, Prontow, Conectividade) e a parceria Oracle contam outra história: a Trix é uma **empresa de tecnologia especializada em saúde suplementar**, que conecta operadoras, prestadores e pacientes, e que usa essa base para oferecer também biometria, automação e fábrica de software.

O novo site assume esse foco:

- **Promessa central**: "Tecnologia que conecta operadoras, prestadores e pacientes."
- **Três públicos, três portas de entrada**: Operadoras de saúde (SAW, Conectividade, Saúde Populacional, Oracle) · Clínicas, consultórios e laboratórios (Prontow) · Empresas em geral (Aspect Face, Xield, Fábrica de Software, Consultoria).
- **Provas**: mais de 35 operadoras atendidas [confirmar número atual], presença nacional, Oracle PartnerNetwork, LGPD com DPO nomeado, prêmio Unimed de Comunicação.
- **Ação**: em todas as páginas, um único CTA principal ("Fale com um especialista" ou "Solicite uma demonstração") levando a um formulário curto ou ao WhatsApp.

Tom de voz: direto, confiante, sem jargão desnecessário. Frases curtas. Falar de resultado para o cliente antes de falar de tecnologia. Sempre "você"/"sua operadora"/"sua clínica".

## 2. Estrutura de páginas (sitemap do WordPress)

```
/                              Home
/empresa/                      Sobre a Trix
/solucoes/                     Hub de soluções (visão geral, por público)
  /solucoes/operadoras/        Página de público: operadoras de saúde
  /solucoes/clinicas/          Página de público: clínicas, consultórios e laboratórios
  /solucoes/empresas/          Página de público: empresas e instituições
/produtos/
  /produtos/prontow/           Prontow – gestão de clínicas com IA
  /produtos/saw/               SAW – Sistema de Atendimento Web (absorve "Portal Operadora")
  /produtos/gedai/             GEDAI – GED e digitalização
  /produtos/integrador/        Integrador
  /produtos/aspect-face/       Aspect Face – biometria facial
  /produtos/xield/             Xield – controle de acesso e automação
/servicos/
  /servicos/conectividade/     Conectividade em saúde suplementar
  /servicos/fabrica-de-software/
  /servicos/consultoria/       Consultoria (inclui Escritório de Processos e Soluções e Automação)
  /servicos/oracle/            Parceiro Oracle
/clientes/                     Clientes e casos
/blog/                         Notícias e conteúdo (opcional, ver 5.2)
/contato/                      Contato
/privacidade/                  Portal de Proteção de Dados (LGPD)
  /privacidade/politica/       Política de Privacidade
  /privacidade/ouvidoria/      Ouvidoria e Denúncias
  /privacidade/codigo-de-conduta/  Código de Conduta (PDF)
```

**Menu principal** (6 itens): Soluções ▾ (por público) · Produtos ▾ · Serviços ▾ · Clientes · Empresa · Contato. Botão destacado à direita: **Fale com um especialista**.

**Rodapé**: logo, frase de posicionamento, endereços de Brasília e São Paulo, telefones, e-mail, WhatsApp, LinkedIn/Instagram/Facebook [confirmar perfis], links de LGPD, selo Oracle PartnerNetwork.

**O que sai ou muda em relação ao site atual**

| Página atual | Destino no novo site | Motivo |
|---|---|---|
| Agenda Médica | Removida (ou, se ainda vendida, vira seção dentro de Prontow) [confirmar] | Serviço de call center de 2010s, concorre com o agendamento por WhatsApp do Prontow |
| Soluções e Automação | Absorvida por Consultoria | Cinco ferramentas genéricas (loja virtual, monitoramento) sem relação com o foco |
| Escritório de Processos | Absorvida por Consultoria (bloco "Processos e governança") | Página com dois parágrafos |
| Portal Operadora | Redireciona para SAW | Texto duplicado |
| Intranet Trix | Removida; prêmio Unimed vira prova social na página Empresa [confirmar se ainda é comercializada] | Produto sem menu e bloqueado no robots |
| Webinário 2022 | Removida; se houver eventos futuros, entram no Blog | Evento encerrado |
| Pop-up do webinário | Removido | Desatualizado |

**Redirecionamentos 301** a configurar no WordPress (plugin Redirection ou regra no servidor) quando o domínio for trocado:

```
/index.php            -> /
/fabrica.php          -> /servicos/fabrica-de-software/
/agenda.php           -> /produtos/prontow/
/solucoes.php         -> /servicos/consultoria/
/consultoria.php      -> /servicos/consultoria/
/conectividade.php    -> /servicos/conectividade/
/escritorio.php       -> /servicos/consultoria/
/oracle.php           -> /servicos/oracle/
/prontow.php          -> /produtos/prontow/
/saw.php              -> /produtos/saw/
/portal.php           -> /produtos/saw/
/gedai.php            -> /produtos/gedai/
/integrador.php       -> /produtos/integrador/
/biometriafacial.php  -> /produtos/aspect-face/
/xield.php            -> /produtos/xield/
/intranet.php         -> /empresa/
/clientes.php         -> /clientes/
/lgpd/                -> /privacidade/
/lgpd/politicas.html  -> /privacidade/politica/
/webinario/           -> /blog/
```

## 3. Textos propostos por página

Os textos abaixo já estão no formato de blocos (título, subtítulo, corpo, CTA) para colar no editor do WordPress.

### 3.1 Home

**Hero**
- Título: **Tecnologia que conecta operadoras, prestadores e pacientes.**
- Subtítulo: Há mais de 15 anos [confirmar ano de fundação], a Trix desenvolve os sistemas que fazem a saúde suplementar funcionar: atendimento, autorização, faturamento TISS, prontuário, biometria e integração. Tudo na nuvem, com suporte de quem conhece o setor.
- CTA primário: **Fale com um especialista** · CTA secundário: Conheça o Prontow

**Faixa de prova** (números em destaque) [confirmar todos]
- +35 operadoras de saúde atendidas em todo o Brasil
- Milhares de prestadores conectados via SAW
- Oracle PartnerNetwork
- Adequada à LGPD, com DPO nomeado

**Para quem** (3 cards)
- **Operadoras de saúde** – Autorize, audite e fature com o SAW, integre sua rede com a Conectividade e acompanhe a saúde da sua população de beneficiários. → Ver soluções para operadoras
- **Clínicas, consultórios e laboratórios** – O Prontow reúne agenda, prontuário, financeiro e TISS num só lugar, com agendamento por WhatsApp e apoio de IA. → Conhecer o Prontow
- **Empresas e instituições** – Biometria facial antifraude, controle de acesso inteligente e uma fábrica de software para o que só a sua operação precisa. → Ver soluções para empresas

**Produtos** (grade com logo, uma linha e link)
- **Prontow** – Gestão de clínicas na nuvem, com IA.
- **SAW** – Atendimento, autorização e auditoria para operadoras.
- **Aspect Face** – API de reconhecimento facial com antifraude.
- **Xield** – Controle de acesso e automação por reconhecimento facial.
- **GEDAI** – Gestão eletrônica de documentos e digitalização.
- **Integrador** – Integração em tempo real entre sistemas.

**Por que a Trix** (3 colunas)
- **Conhecemos o setor por dentro.** Elegibilidade, intercâmbio, TISS, auditoria: nossa equipe fala a língua da operadora e do prestador.
- **Entregamos ponta a ponta.** Produto, implantação, treinamento e suporte, com processos próprios (PROTRIX e MTRIX) e parceria Oracle.
- **Dados protegidos de verdade.** Comitês de compliance, proteção de dados e segurança, trilhas de auditoria e programa de treinamento LGPD.

**Clientes** – Faixa com logos (Central Nacional Unimed, Postal Saúde, Economus, Unimed Curitiba, Unimed São Paulo…) e link "Veja quem confia na Trix".

**Depoimento** – Espaço para 1 a 2 depoimentos de operadoras [coletar].

**CTA final**
- Título: **Vamos conversar sobre a sua operação?**
- Texto: Conte o que você precisa resolver. Um especialista da Trix retorna em até um dia útil [confirmar prazo].
- Botão: Fale com um especialista · Link: ou chame no WhatsApp

### 3.2 Empresa (Sobre a Trix)

- Título: **Uma empresa de tecnologia feita para a saúde.**
- Abertura: A Trix Tecnologia Inteligente nasceu em Brasília [confirmar] para resolver um problema concreto: fazer operadoras de planos de saúde, prestadores e beneficiários trocarem informação com segurança e rapidez. Desse desafio nasceram o SAW, a Conectividade em Saúde Suplementar e, mais recentemente, o Prontow. Hoje atendemos operadoras, cooperativas Unimed, autogestões e prestadores em todo o país, com escritórios em Brasília e São Paulo.
- **O que nos move**
  - Missão: Criar soluções que agregam valor ao negócio dos nossos clientes, com escalabilidade, resiliência e resolutividade.
  - Visão: Ser reconhecida pela satisfação dos clientes e pela qualidade do que entregamos, construindo relações duradouras.
  - Valores: Ética. Compromisso. Competência.
- **Como trabalhamos**
  - PROTRIX: nossa metodologia de desenvolvimento e manutenção de sistemas, com desenvolvimento iterativo, gestão de demandas e gerência de projetos.
  - MTRIX: nossa metodologia de gestão de processos, aplicada aos nossos produtos e oferecida como consultoria.
  - Governança: comitês de Compliance, Proteção de Dados e Segurança e Qualidade, com DPO nomeado e programa de treinamento na Trix Academy.
- **Reconhecimentos**: membro do Oracle PartnerNetwork; vencedora por dois anos consecutivos do Prêmio Unimed de Comunicação (troféu Alberto Urquiza Wanderley) com a Intranet Trix [confirmar anos].
- **Liderança**: Marcos Soares, Diretor Executivo [confirmar demais nomes e fotos].
- CTA: Conheça nossas soluções · Trabalhe conosco [se houver vagas]

### 3.3 Soluções por público

**/solucoes/operadoras/ – Para operadoras de saúde**
- Título: **Atendimento, autorização, auditoria e faturamento numa única plataforma.**
- Texto: Sua rede credenciada, seus atendentes, auditores e beneficiários trabalhando sobre os mesmos dados, em tempo real, integrados ao seu sistema de gestão. É assim que o SAW e a Conectividade da Trix operam em mais de 35 operadoras [confirmar].
- Blocos: SAW (autorização, auditoria técnica e inteligente, intercâmbio PTU, TISS, faturamento eletrônico) · Conectividade (implantação de prestadores, elegibilidade com cartão e biometria, treinamento, suporte com call-back) · Gestão de Saúde Populacional (estratificação de risco, busca ativa, linhas de cuidado) · Oracle (infraestrutura, banco de dados e compliance para ambientes críticos) · Aspect Face (biometria para elegibilidade e combate a fraudes).
- CTA: Agende uma apresentação para sua operadora.

**/solucoes/clinicas/ – Para clínicas, consultórios e laboratórios**
- Título: **Do agendamento à cobrança, sem retrabalho.**
- Texto: O Prontow cuida da agenda, do prontuário, do financeiro e do faturamento TISS da sua clínica, na nuvem. Um assistente de IA atende seus pacientes no WhatsApp 24 horas por dia e a recepção ganha tempo para quem está na sala de espera.
- Blocos: os módulos do Prontow (ver 3.4) · App do paciente · Assinatura digital e prescrição Memed · Segurança e LGPD.
- CTA: Solicite uma demonstração do Prontow.

**/solucoes/empresas/ – Para empresas e instituições**
- Título: **Identidade, acesso e software sob medida.**
- Texto: Reconhecimento facial com antifraude para cadastro e autenticação, controle de acesso e automação de ambientes por biometria, gestão de documentos e uma fábrica de software para o que só a sua operação precisa.
- Blocos: Aspect Face · Xield · GEDAI · Fábrica de Software · Consultoria.
- CTA: Fale com um especialista.

### 3.4 Produtos

**Prontow** (aproveita a página atual, que já é boa; ajustes de estrutura)
- Título: **Prontow. A gestão da sua clínica, com inteligência artificial.**
- Subtítulo: Agenda, prontuário eletrônico, financeiro, faturamento TISS e atendimento por WhatsApp com IA. Tudo em um só lugar, 100% na nuvem.
- CTA: Solicite uma demonstração · WhatsApp (61) 3246-1800
- Seções (manter o conteúdo atual, cada uma com um benefício no título):
  1. **Menos faltas, menos telefone**: Agendamento por WhatsApp com IA e confirmação automática.
  2. **Agenda que a recepção domina em minutos**: calendário por profissional, filtros, status em tempo real.
  3. **O paciente no centro do atendimento**: filas em tempo real, prontuário configurável por especialidade, prescrições, laudos, autorizações.
  4. **IA que apoia o médico, não o substitui**: sugestão diagnóstica e preenchimento assistido; a decisão é sempre do profissional.
  5. **Recursos clínicos completos**: painel de senhas, resultados online, prescrição Memed, assinatura ICP-Brasil, templates.
  6. **Financeiro sem planilha**: contas a pagar e receber, fluxo de caixa, NFS-e/NF-e.
  7. **TISS do jeito que a ANS pede**: guias, elegibilidade em lote, faturamento em lotes, versões de XML.
  8. **Gestão de várias unidades, com controle**: multiunidade, perfis, estoque, relatórios, trilha de auditoria.
  9. **Para operadoras: saúde populacional**: estratificação de risco, busca ativa, análise epidemiológica, linhas de cuidado.
  10. **App do paciente** (mostrar badges só quando publicado).
- Bloco final: perguntas frequentes (implantação, migração de dados, planos, suporte) [redigir com a equipe].

**SAW – Sistema de Atendimento Web**
- Título: **SAW. Atendimento, autorização e auditoria para operadoras, em uma única interface web.**
- Subtítulo: Atendentes, analistas de contas, prestadores, empresas contratantes e beneficiários trabalhando sobre os dados da sua operadora, em tempo real.
- Corpo (reescrito a partir do atual):
  - **Elegibilidade em segundos.** O prestador informa o código do beneficiário e o SAW consulta a base da operadora na hora: só há atendimento se houver cobertura.
  - **Auditoria médica onde o auditor estiver.** Histórico de utilização, restrições contratuais, laudos e conversas disponíveis em tempo de auditoria. A Auditoria Inteligente uniformiza decisões e antecipa a resposta ao solicitante.
  - **Intercâmbio nacional sem infraestrutura extra.** Certificado no PTU 3.5, o SAW envia e responde solicitações de intercâmbio para todo o Brasil e aplica regras de autorização automática mesmo com operadoras não certificadas.
  - **TISS completo.** Guias no padrão visual do TISS, validação na entrada, WebService XML para prestadores com sistema próprio e módulo de faturamento eletrônico para quem não tem.
  - **Integração com o seu sistema de gestão.** Por meio do Integrador Trix, o SAW conversa com qualquer banco de dados, com logs de todas as transações.
  - **Novidades**: Auditoria de Contas Hospitalares, autorização e absenteísmo por SMS, Guia Modelo [confirmar lista atual].
- Ficha técnica: Java, arquitetura MVC; licenciamento por direito de acesso ou aquisição.
- CTA: Agende uma apresentação do SAW.

**GEDAI**
- Título: **GEDAI. Seus documentos localizados em segundos, físicos ou digitais.**
- Corpo: Solução de GED/ECM com bureau de digitalização, OCR, workflow, versionamento, controle de correspondências e endereçamento físico de arquivos. Integra com ERP, RH e outros sistemas.
- Benefícios: produtividade, segurança e rastreabilidade, redução de espaço físico, base de conhecimento.
- CTA: Fale com um especialista.

**Integrador**
- Título: **Integrador. Sistemas diferentes, dados sincronizados em tempo real.**
- Corpo: Conecta bases de dados heterogêneas independentemente do fornecedor. É o mesmo componente que integra o SAW ao sistema de gestão de dezenas de operadoras.
- Ficha: Java, PostgreSQL, MVC.
- CTA: Fale com um especialista.

**Aspect Face**
- Título: **Aspect Face. Reconhecimento facial com antifraude de verdade.**
- Subtítulo: API de identificação 1:1, 1:N e N:N, com prova de vida e uma equipe de analistas que confirma as fraudes apontadas pela IA.
- Blocos: Antifraude (IA + analistas, base de fraudes confirmadas) · Identificação · Prova de vida · Integração por Swagger · Gerenciador web e dashboards · Sem limite de idade.
- Casos de uso: elegibilidade de beneficiários, onboarding digital, controle de acesso.
- CTA: Teste a API (aspectface.com) · Fale com um especialista.

**Xield**
- Título: **Xield. Segurança e automação do seu espaço pelo rosto.**
- Corpo: manter a estrutura atual (plataforma web, controle de acesso, mapa de presença, automação, integrações RTSP/Matter/Zigbee/MQTT/Home Assistant, casos de uso), sem emojis nos títulos.
- CTA: Solicite uma demonstração.

### 3.5 Serviços

**Conectividade em Saúde Suplementar**
- Título: **Sua rede credenciada conectada, treinada e atendida.**
- Texto: A Conectividade da Trix cuida de tudo o que acontece entre a operadora e o prestador: implantação do SAW na rede, elegibilidade com cartão e biometria, faturamento eletrônico, treinamento e reciclagem em e-learning e suporte remoto de segunda a sexta, das 7h30 às 19h30, com call-back gratuito.
- Prova: mais de 35 operadoras [confirmar].
- CTA: Fale com um especialista.

**Fábrica de Software**
- Título: **Software sob medida, com método.**
- Texto: Com a metodologia PROTRIX, a Trix assume o projeto inteiro ou apenas a etapa que você precisa: requisitos, análise e design, codificação, testes ou documentação de sistemas legados.
- Tecnologias: atualizar a lista (Java, PHP, mobile, PostgreSQL, Oracle, SQL Server, nuvem Oracle) [confirmar stack atual e remover itens obsoletos].
- CTA: Conte seu projeto.

**Consultoria** (inclui Escritório de Processos e Soluções e Automação)
- Título: **Processos, segurança e métricas para a sua TI.**
- Blocos: Processos e governança (MTRIX, BPM, BPMN, monitoramento assistido) · Segurança da informação · Análise de Pontos de Função (IFPUG) · Outsourcing de infraestrutura com SLAs · Ferramentas de gestão de demandas, projetos e qualidade · Soluções open source.
- CTA: Fale com um especialista.

**Parceiro Oracle**
- Título: **Oracle para ambientes críticos de saúde.**
- Texto: Como membro do Oracle PartnerNetwork, a Trix implanta e opera OCI, administra e migra bancos de dados Oracle, integra sistemas e configura segurança e compliance para os requisitos da saúde suplementar.
- Blocos: os 6 atuais.
- CTA: Fale com um especialista.

### 3.6 Clientes

- Título: **Quem confia na Trix.**
- Texto: Operadoras, cooperativas Unimed, autogestões e prestadores em todo o Brasil usam nossos sistemas todos os dias.
- Grade de logos (39 atuais, padronizados) agrupada por tipo: Operadoras e autogestões · Federações · Unimeds.
- Depoimentos e casos (a coletar) [confirmar].
- CTA: Quer ser o próximo? Fale com um especialista.

### 3.7 Contato

- Título: **Fale com a Trix.**
- Formulário curto: nome, e-mail, telefone/WhatsApp, empresa, assunto (Operadora · Clínica ou consultório · Empresa · Suporte ao prestador · Financeiro · Outro), mensagem, aceite de privacidade.
- Blocos: Brasília (matriz) · São Paulo · WhatsApp · E-mail · Suporte ao prestador (horário e call-back) · Ouvidoria 0800 941 1190.
- Mapa incorporado das duas unidades.

### 3.8 Privacidade (LGPD)

Manter o conteúdo do portal atual, reorganizado em uma página-mãe com âncoras (Compromisso · Como tratamos seus dados · Comitês · Treinamento · Canais) e subpáginas para Política, Ouvidoria e Código de Conduta. Corrigir o CEP para 70.610-440 [confirmar].

## 4. Textos comuns

- **Meta title padrão**: `%título% | Trix Tecnologia Inteligente`
- **Meta description da home**: "Sistemas para operadoras de saúde, clínicas e prestadores: SAW, Prontow, conectividade TISS, biometria facial e fábrica de software. Fale com um especialista."
- **Slogan curto para rodapé**: "Tecnologia que conecta operadoras, prestadores e pacientes."
- **Barra de cookies**: "Usamos cookies para melhorar sua experiência e medir o uso do site. Você pode aceitar ou recusar." Botões: Aceitar · Recusar · Política de Privacidade.

## 5. Recomendações de implementação no WordPress

### 5.1 Base
- Tema leve com editor de blocos (por exemplo Blocksy, Kadence ou GeneratePress) e um tema filho para ajustes; evitar page builders pesados.
- Tipos de conteúdo personalizados: **Produto** e **Serviço** (com campos: resumo, público, CTA, logo) e **Cliente** (logo, tipo). Facilita as grades da home e das páginas de público.
- Plugins: SEO (Rank Math ou Yoast) · Redirection (301 da seção 2) · formulário (WPForms ou Fluent Forms) com reCAPTCHA v3 · cookie/LGPD (Complianz ou CookieYes) · cache (LiteSpeed ou WP Rocket) · otimização de imagens (WebP).
- Analytics: migrar para Google Analytics 4 e remover o script do AdSense.
- Segurança: 2FA para administradores, limite de tentativas de login, backups automáticos.

### 5.2 Blog (opcional)
Um blog com 1 a 2 artigos por mês sobre TISS, intercâmbio, LGPD em saúde, IA em clínicas e Oracle em saúde alimenta o SEO nos termos que os clientes já buscam. As palavras-chave da página do Prontow (software para clínicas, prontuário eletrônico, agendamento por WhatsApp, guia TISS) são um bom ponto de partida.

### 5.3 Imagens
- Pedir à Trix os logos vetoriais (Trix, Prontow, SAW, GEDAI, Integrador, Aspect Face, Xield) e os logos dos clientes em boa resolução.
- Produzir novas fotos ou ilustrações para o hero e para as páginas de público; as atuais são pequenas demais.
- Telas reais do Prontow, SAW e Xield (com dados fictícios) valem mais do que ilustrações genéricas.
- Todas as imagens com `alt` descritivo.

### 5.4 Ordem sugerida de trabalho
1. Instalar tema, plugins e criar os tipos de conteúdo.
2. Publicar as páginas na ordem: Home → Prontow → SAW → Conectividade → Soluções por público → demais produtos e serviços → Empresa → Clientes → Contato → Privacidade.
3. Configurar formulários, WhatsApp e cookies.
4. Revisar SEO (títulos, descriptions, sitemap) e acessibilidade.
5. Testar os redirecionamentos e trocar o domínio.

## 6. Pendências para a Trix confirmar

1. Número atual de operadoras e de prestadores atendidos (para a faixa de prova).
2. Ano de fundação e história resumida da empresa.
3. Se Agenda Médica, Intranet Trix e Portal Operadora ainda são vendidos.
4. Lista atual de tecnologias da Fábrica de Software.
5. Qual WhatsApp é o oficial: (61) 99232-4516 ou (61) 3246-1800.
6. CEP correto da matriz (70.610-440 ou 76.610-440) e se o endereço de São Paulo continua ativo.
7. Perfis oficiais no LinkedIn e Instagram.
8. Nomes, cargos e fotos da liderança que podem aparecer no site.
9. Dois ou três clientes dispostos a dar depoimento.
10. Status do app do paciente nas lojas.
11. Prazo de retorno que a equipe comercial consegue cumprir (para prometer no CTA).
