# CoreCyber — fatos do produto para o site

Fonte: repositório `MidnalGestaoInovacao/EbaemCybercore` (plataforma 3.0.197, agente 1.12.0, app 1.7.1, plugin WordPress 1.6.0 — set./out. 2026). **Use só o que está aqui.** O que é "Em evolução" ou "Roadmap" deve aparecer com esse rótulo — nunca como pronto.

- Nome comercial no site: **CoreCyber** (logo "CoreCyber — Um produto EBAEM"). O portal hoje se identifica como "EBAEM CyberCore" em `https://cybercore.ebaem.api.br/`.
- Slogan do produto: **"Segurança que se vê. Controle que se prova."**
- Fabricante, licenciadora e primeira cliente: **EBAEM** (Brasília/DF). Clientes informados pela EBAEM: **ASSBAN, MOORE, MIDNAL** (sem depoimentos ou números autorizados — não inventar).
- Proposta: em um só painel e no seu idioma, a visão completa e priorizada do risco da empresa — do subdomínio esquecido ao notebook desatualizado — com a recomendação do que fazer primeiro.

## Disponível hoje

**Superfície de ataque (EASM) — "FingerPrint"**
- Varredura externa **não invasiva** de sites, APIs, documentações e subdomínios: portas, SSL/TLS, cabeçalhos de segurança, exposições conhecidas (templates atualizados semanalmente), WAF, acessibilidade WCAG, SPF/DMARC e security.txt; descoberta de subdomínios por certificados públicos.
- Comparação entre execuções: achados **novos, resolvidos e persistentes**.
- **Monitoramento contínuo a cada 10 minutos**: alerta de achado crítico/alto novo ou subdomínio novo.
- Laudo em PDF/DOCX; agendamentos com envio por e-mail; inventário externo de domínios e subdomínios com histórico.

**Disponibilidade e status**
- Monitores HTTP/S e TCP, uptime 24h/7d/30d, latência média e p95, meta de SLA, vencimento de certificado TLS e de domínio, janelas de manutenção.
- **Páginas de status públicas** por empresa (com logo e PDF); importação de alvos por CSV/XLSX; chamados GLPI abertos e fechados automaticamente.

**Vulnerabilidades priorizadas por risco**
- Fila priorizada com score 0–100 que combina severidade (CVSS), **probabilidade de exploração (EPSS)** e **catálogo de vulnerabilidades exploradas ativamente (CISA KEV)** — item KEV nunca fica abaixo de 90. Recalculado todo dia.
- Indicadores: abertas, críticas/altas, KEV, com CVE, SLA vencido, riscos aceitos. **SLA por severidade: 7, 15, 30, 60 e 90 dias.** Aceite de risco com justificativa.
- Ficha com **regularização passo a passo (22 temas)**, como validar a correção e referências (NVD, KEV, EPSS, CWE, OWASP). Exportação PDF/Excel; vira caso de incidente com um clique.

**Segurança de aplicações (AppSec)**
- Análise de código: SAST + dependências (SCA) + segredos expostos; **SBOM CycloneDX 1.5**.
- Segurança de APIs (DAST com especificação OpenAPI); teste autenticado (white box); correlação código × aplicação em execução.
- Resultados consolidados com exportação PDF, Excel, CSV, JSON e **SARIF**.

**Inteligência de ameaças (CTI)**
- Indicadores de ameaça coletados **diariamente** de 4 fontes públicas reconhecidas (servidores de comando e controle, URLs maliciosas, certificados SSL maliciosos e o catálogo KEV). Templates de exposição atualizados semanalmente.

**Monitoração de ativos por agente** (Windows, Linux, Docker, WordPress, Android; iOS em preparação; macOS não suportado)
- Windows: instalador com serviço do Windows e "guardião" que religa o serviço; implantação em massa por GPO, Intune ou SCCM; ícone na bandeja com status da máquina.
- Coleta: CPU/memória/disco, criptografia de disco (BitLocker/LUKS) com guarda de chaves, antivírus e ameaças do Microsoft Defender, atualizações pendentes, inventário de software e hardware com histórico, usuário logado, 17 verificações de conformidade, localização **só com consentimento**.
- **15 correções remotas** e ações (aplicar atualizações, janela de atualização forçada, política de USB, bloqueio de tela, reiniciar serviço, varredura rápida de antivírus, desinstalação) — **todas desligadas de fábrica e recusáveis na própria máquina**.
- Autoatualização verificada (SHA-256, autoteste e retorno automático à versão anterior), número de série por ativo, detecção de agente adulterado, licença por token (1 a 60 meses ou vitalícia).
- Transparência: o agente **não lê conteúdo de arquivos, não captura tela nem teclas e não registra navegação**.

**Conformidade ISO e LGPD**
- **193 controles** de ISO/IEC 27001:2022 (Anexo A completo), 27017, 27018, 27701, **ISO 22301** e **LGPD**, cada um com situação (conforme, parcial, não conforme, sem dados, apoiado).
- **Declaração de Aplicabilidade** por norma, aprovada por versão, em PDF com QR.
- LGPD: encarregado cadastrado, portal "Exercer meus direitos" com protocolo e prazo de 15 dias, avisos com "li e estou ciente", pseudonimização, registro de quem acessou dados pessoais, **ROPA** (inventário de tratamento) em PDF/CSV.
- **Descomissionamento** em etapas (devolução → criptografia → desinstalação → sanitização → expurgo) com **certificado em PDF com QR**.

**Auditorias especializadas**
- **Auditoria SAP** e **auditoria RACF (mainframe)**: dossiê de evidências, segregação de funções, conciliações cruzadas e laudo autenticado por QR.

**Detecção, casos e automação**
- Detecções correlacionadas (XDR na plataforma) por 5 regras: vulnerabilidade KEV, risco muito alto, disponibilidade, superfície e indicador de ameaça.
- **Casos de incidente** com ciclo aberto → investigando → contido → resolvido → fechado, linha do tempo e chamado GLPI; "relatar incidente" pelo agente ou app.
- Automação (SOAR) por regras gatilho → abre caso e notifica, a cada 10 minutos, com botão "Testar".

**Relatórios e alertas**
- **32 tipos de relatório** PDF/DOCX com protocolo e **QR de autenticidade** (página pública de verificação); capa com 8 paletas; cópia automática no Google Drive.
- Alertas: sino de notificações, e-mail (modelos editáveis por empresa), **Telegram, Slack, webhook** e **GLPI** (API v1 e nova API v2 com OAuth2).

**Plataforma**
- SaaS **multiempresa** com empresas administradoras de grupo (base para MSSP); teste automático de isolamento entre empresas com **3.951 verificações e 0 falhas**.
- **8 idiomas** na plataforma: português, inglês, espanhol, italiano, francês, chinês, japonês e russo.
- Segurança: perfis e permissões por função, 2º fator por e-mail, login com Google e Microsoft, **certificado digital ICP-Brasil (e-CPF/e-CNPJ)**, reautenticação para ações sensíveis, sessão encerrada por inatividade (30 min), logs de auditoria (14 tipos, retenção padrão 180 dias), segredos cifrados, proteção contra SSRF, termos com evidência (IP, navegador e hash SHA-256 do texto aceito).
- Escala testada: **50 empresas × 10.000 ativos**.

## Em evolução (parcial)
- Índice de risco por ativo/empresa e benchmark entre empresas (hoje: score por achado e visão executiva).
- Inventário externo com IP/ASN, tecnologia e nota SSL na mesma tabela.
- Playbooks SOAR com ações de contenção e aprovação humana em etapas (hoje: regras → caso + notificação).
- Resumo diário/semanal de alertas, Microsoft Teams e WhatsApp.

## Roadmap (ainda não disponível)
- **SIEM** com coleta e retenção de logs (núcleo de detecção em código aberto).
- **EDR completo** (telemetria de processos, integridade de arquivos, regras YARA, isolamento de máquina) e **NDR** (análise de tráfego de rede).
- Mapeamento MITRE ATT&CK, CTI ampliada (plataformas de inteligência colaborativa).
- **Assistente de IA** (perguntas em linguagem natural sobre o risco) e IA preditiva. Hoje há apenas personalização opcional do laudo por IA.
- App iOS e publicação na Google Play.

## Planos (proposta comercial — valores em `site.config.json`)
Cobrança por **faixa de ativos monitorados** (domínio, subdomínio relevante, IP público, API, endpoint ou servidor = 1 ativo), em reais, sem variação cambial. Anual: paga 10 meses, leva 12.
1. **Essencial — Superfície & Disponibilidade**: EASM contínuo, disponibilidade e status pages, SSL/domínio, alertas, relatórios com QR.
2. **Profissional — Vulnerabilidades & Ameaças** (mais escolhido): + fila priorizada CVSS/EPSS/KEV com SLA, CTI diária, AppSec (código, dependências, segredos, APIs), relatórios executivos.
3. **Avançado — Ativos, Conformidade & Resposta**: + agentes, 193 controles ISO/LGPD, Declaração de Aplicabilidade, descomissionamento certificado, detecções correlacionadas, casos, automação, GLPI.
4. **MSSP / Operação gerenciada**: multiempresa com marca própria e SLA; operação pela equipe EBAEM — sob consulta.
- Complementos: Auditoria SAP/RACF (por projeto), retenção estendida, add-on de IA (lista de espera).
- Instância dedicada (nuvem privada) disponível sob consulta.
- **Diagnóstico gratuito de exposição externa** (não invasivo, mediante comprovação de titularidade do domínio) e **prova de conceito de 30 dias**.
