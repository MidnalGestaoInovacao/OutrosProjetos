# SPEC — Plugin WordPress "EB Crédito Rural"

> Documento de especificação para implementação no Claude Code.
> Coloque este arquivo na raiz do plugin (`wp-content/plugins/eb-credito-rural/SPEC.md`) e peça ao Claude Code para lê-lo antes de começar.
> Slug: `eb-credito-rural` · Prefixo de código: `ebcr_` · Namespace PHP: `EBCR\`

---

## 1. Contexto e objetivo

O site de Evellyn Brandão capta produtores rurais interessados em crédito de um fundo. Hoje a coleta de dados e documentos é manual. O plugin deve:

1. Permitir que o produtor se cadastre com e-mail, envie seus dados e documentos por um formulário em etapas, com validação.
2. Armazenar tudo em banco de dados, com os arquivos protegidos (apenas o próprio cliente e a equipe autorizada podem acessá-los).
3. Notificar administração e cliente por e-mail a cada envio e mudança de status.
4. Oferecer ao cliente uma área logada para acompanhar suas solicitações, responder pendências e fazer novas submissões (respeitando intervalo mínimo).
5. Oferecer à equipe um painel com dashboard, CRM, atualização de status e feedback, e uma área de configuração bem explicada.

### Metas mensuráveis
- Reduzir o tempo entre o primeiro contato e o dossiê completo para comitê.
- Pelo menos 80% das submissões chegarem à análise sem pendência documental de itens obrigatórios.
- Zero acesso indevido a documentos (validado por testes automatizados de autorização).

### Fora de escopo (v1)
- Decisão automática de crédito ou score automatizado (a decisão continua humana).
- Assinatura eletrônica de contratos (planejado para P2).
- Integrações automáticas com SCR/Bacen, Serasa, SICAR ou IBAMA (consultas continuam manuais; o plugin registra o resultado).
- Pagamentos, cobrança ou gestão da carteira após a concessão.

---

## 2. Requisitos técnicos gerais

- PHP ≥ 8.1, WordPress ≥ 6.4, MySQL ≥ 5.7 / MariaDB ≥ 10.4.
- Orientação a objetos, autoload PSR-4 (Composer opcional, com fallback de autoloader próprio para não exigir Composer no servidor).
- Seguir WordPress Coding Standards (PHPCS com `WordPress-Extra` e `WordPress-Security`).
- Totalmente traduzível (text domain `eb-credito-rural`, idioma padrão pt_BR).
- Sem dependência de page builders. Front-end em HTML/CSS/JS puro (sem jQuery obrigatório), responsivo e acessível (WCAG 2.1 AA: rótulos, foco visível, mensagens de erro associadas aos campos, contraste).
- Nenhum CDN externo: todos os assets empacotados no plugin.

### Estrutura de pastas sugerida
```
eb-credito-rural/
├── eb-credito-rural.php          # bootstrap, cabeçalho do plugin, constantes
├── uninstall.php                 # respeita configuração "manter dados ao desinstalar"
├── SPEC.md
├── src/
│   ├── Plugin.php                # orquestra hooks
│   ├── Install/ (Activator, Deactivator, Migrations/)
│   ├── Roles/Capabilities.php
│   ├── Database/ (Repositórios por tabela)
│   ├── Domain/ (Submission, Document, Status, Consent, Contact…)
│   ├── Forms/ (Wizard, Steps/, Validators/, Fields/)
│   ├── Security/ (Captcha, RateLimiter, Nonces, FileGuard, Crypto, AuditLog)
│   ├── Files/ (UploadHandler, Storage, DownloadController)
│   ├── Mail/ (Mailer, Templates/, Queue)
│   ├── Frontend/ (Shortcodes, Portal, Auth)
│   ├── Admin/ (Menu, Dashboard, SubmissionsList, SubmissionView, Crm, Settings/, Help, AuditLogView)
│   ├── Rest/ (endpoints internos com permission_callback)
│   └── Cron/ (lembretes, expiração de certidões, limpeza, retenção)
├── templates/ (sobrescrevíveis pelo tema em /eb-credito-rural/)
├── assets/ (css, js, img)
├── languages/
└── tests/ (PHPUnit + testes de autorização)
```

---

## 3. Papéis e permissões

| Papel | Descrição | Capacidades principais |
|---|---|---|
| `ebcr_cliente` | Produtor rural cadastrado | Ver e editar apenas as próprias submissões (em rascunho ou com pendência), enviar documentos, ler feedback visível ao cliente |
| `ebcr_analista` | Analista de crédito | `ebcr_view_submissions`, `ebcr_edit_submissions`, `ebcr_download_documents`, `ebcr_manage_crm` |
| `ebcr_gestor` | Gestor / comitê | Tudo do analista + `ebcr_change_final_status` (aprovar/reprovar), `ebcr_view_dashboard`, `ebcr_export`, `ebcr_manage_channels` (mensagens dos canais, 1.3.0; pode ser dada também ao encarregado/DPO) |
| `administrator` | Admin do WP | Tudo acima + `ebcr_manage_settings`, `ebcr_view_audit_log` |

Regras:
- Todas as verificações usam `current_user_can()` com capacidades próprias; nunca checar papel pelo nome.
- Clientes não acessam `/wp-admin` (redirecionar para o portal) e não veem a admin bar.
- Opção de atribuir cada submissão a um analista responsável; analista pode ser configurado para ver só as atribuídas a ele.

---

## 4. Modelo de dados (tabelas próprias via `dbDelta`)

Versão do schema salva em `ebcr_db_version`; migrações incrementais em `Install/Migrations/`.

| Tabela | Finalidade | Campos principais |
|---|---|---|
| `{prefix}ebcr_submissions` | Solicitação | `id`, `public_id` (UUID v4, usado em URLs), `user_id`, `status`, `assigned_to`, `person_type` (PF/PJ), `requested_amount`, `purpose`, `term_months`, `current_step`, `submitted_at`, `created_at`, `updated_at`, `deleted_at` |
| `{prefix}ebcr_submission_data` | Dados do formulário por seção | `submission_id`, `section`, `data` (JSON validado), `updated_at` |
| `{prefix}ebcr_properties` | Imóveis (1:N) | `submission_id`, nome, município/UF, matrícula, cartório, área total/útil (ha), CAR, CCIR, NIRF/CIB, SIGEF, própria/arrendada, fim do arrendamento |
| `{prefix}ebcr_guarantees` | Garantias oferecidas (1:N) | `submission_id`, tipo, descrição, `property_id` opcional, valor declarado, valor avaliado, LTV calculado, status da formalização |
| `{prefix}ebcr_documents` | Metadados de arquivos | `id`, `public_id`, `submission_id`, `user_id`, `doc_type`, `original_name`, `stored_name`, `mime`, `size`, `sha256`, `encrypted` (bool), `review_status` (pendente/aceito/recusado), `review_note`, `expires_at` (validade de certidões), `uploaded_at` |
| `{prefix}ebcr_status_history` | Linha do tempo | `submission_id`, `from_status`, `to_status`, `changed_by`, `comment_internal`, `comment_client`, `created_at` |
| `{prefix}ebcr_messages` | Feedback/mensagens | `submission_id`, `author_id`, `visibility` (interno/cliente), `body`, `read_at`, `created_at` |
| `{prefix}ebcr_crm_contacts` | Ficha CRM do cliente | `user_id`, telefone, WhatsApp, origem do lead, tags, responsável, estágio do funil, próxima ação e data |
| `{prefix}ebcr_crm_activities` | Interações e tarefas | `contact_id`, tipo (ligação, e-mail, reunião, visita, tarefa), descrição, `due_at`, `done_at`, `created_by` |
| `{prefix}ebcr_consents` | Aceites | `user_id`, `submission_id`, `policy_key`, `policy_version`, `policy_hash`, `accepted_at`, `ip`, `user_agent` |
| `{prefix}ebcr_audit_log` | Trilha de auditoria | `actor_id`, `action`, `object_type`, `object_id`, `ip`, `meta` (JSON), `created_at` |
| `{prefix}ebcr_checks` | Checklist de conferência do analista | `submission_id`, `check_key`, resultado (ok/alerta/reprovado/n.a.), observação, `checked_by`, `checked_at` |

Regras:
- Nunca expor IDs sequenciais no front-end; usar `public_id`.
- Todas as consultas com `$wpdb->prepare()`.
- Exclusão lógica (`deleted_at`) para submissões; exclusão física apenas via rotina de retenção/LGPD.
- Campos sensíveis (CPF/CNPJ, dados bancários, renda) opcionalmente criptografados em repouso (ver §8.6).

---

## 5. Formulário em etapas (wizard)

Salvamento automático de rascunho a cada etapa. O cliente pode sair e voltar. Validação no cliente (JS, para UX) **e** no servidor (fonte da verdade). Campos condicionais conforme respostas.

### Etapa 1 — Identificação do tomador
- Tipo: PF ou PJ.
- PF: nome, CPF, RG/órgão, data de nascimento, estado civil, regime de bens; se casado/união estável → dados do cônjuge (nome, CPF), pois a outorga é necessária para garantias reais.
- PJ: razão social, CNPJ, representantes legais (N) com CPF.
- Inscrição estadual de produtor rural, CAF (opcional, Pronaf), endereço com CEP, telefone/WhatsApp.
- Declaração de PEP (sim/não + detalhes).

### Etapa 2 — Imóveis rurais (repetível)
Campos da tabela `ebcr_properties`. Se arrendado → contrato e data de término (alertar se terminar antes do prazo do empréstimo).
Desde a 1.3.0 a exigência é configurável (`assets_mode`: obrigatório — padrão —, opcional com a pergunta "possui imóvel rural a informar?" ou desativado, quando a etapa some e os documentos por imóvel não são pedidos). Filtro `ebcr_assets_required`.

### Etapa 3 — Atividade produtiva
- Atividade (grãos, café, cana, fruticultura, pecuária de corte/leite, outras), área plantada por cultura, produtividade das últimas 3 a 5 safras.
- Plano da safra e orçamento de custeio.
- Comercialização: compradores principais, contratos de venda futura/barter.
- Pecuária: tamanho do rebanho por categoria.
- Seguro rural (sim/não, tipo), irrigação, maquinário principal.

### Etapa 4 — Situação financeira
- Receita bruta anual (últimos 3 anos), dívidas existentes (repetível: credor, saldo, parcela, vencimento final, garantia dada).
- Valor solicitado, finalidade (custeio, investimento, comercialização, capital de giro), prazo desejado, época preferida de pagamento.
- Calcular no servidor, para exibição ao analista (não ao cliente): comprometimento estimado e relação dívida/receita.

### Etapa 5 — Garantias oferecidas (repetível)
Tipos: alienação fiduciária de imóvel, hipoteca, penhor agrícola, penhor pecuário, máquinas/equipamentos, CPR, cessão de recebíveis, Patrimônio Rural em Afetação/CIR, aval/fiança. Cada tipo exibe os campos e documentos próprios (ver §6).
Desde a 1.3.0 a exigência é configurável (`guarantees_mode`): **obrigatória** (padrão), **opcional** (o cliente pode declarar "sem garantia a oferecer"; finalidades listadas em `guarantees_required_modalities` continuam exigindo garantia) ou **desativada** (etapa e documentos de garantia ocultos; as etapas exibidas são renumeradas). Validação no navegador e no servidor (inclusive na revalidação do envio). Filtro `ebcr_guarantees_required` (`bool $required, array $submission_context`). Sem imóveis informados, a garantia real não exige vínculo com imóvel.

### Etapa 6 — Documentos
Lista dinâmica gerada pela matriz de §6, marcando obrigatórios e opcionais conforme as respostas anteriores. Barra de progresso de documentos.

### Etapa 7 — Declarações, consentimentos e envio
- Aceites obrigatórios (checkboxes separados, nunca pré-marcados), cada um com link para o texto completo e versão:
  1. Política de Privacidade e tratamento de dados (LGPD).
  2. Termos de uso da plataforma.
  3. Autorização de consulta ao SCR/Bacen e birôs de crédito.
  4. Declaração de veracidade das informações e documentos.
  5. Declaração socioambiental (ausência de trabalho análogo ao escravo, embargos e desmatamento ilegal, conforme conhecimento do declarante).
  6. Declaração anticorrupção e de conformidade (política de compliance da empresa/fundo).
- Opcional: consentimento para comunicações de marketing (separado e não obrigatório).
- Resumo revisável de tudo antes de enviar.
- Captcha matemático (§8.2).
- Ao enviar: status → `enviada`, dados ficam somente leitura para o cliente (exceto quando a equipe abrir pendência).

### Validadores obrigatórios (servidor)
- CPF e CNPJ com dígitos verificadores; rejeitar sequências repetidas.
- E-mail (`is_email`), telefone BR (10–11 dígitos), CEP (8 dígitos).
- UF da lista oficial; datas válidas e coerentes (nascimento ≥ 18 anos; arrendamento no futuro).
- Valores monetários e áreas: numéricos, positivos, com limites configuráveis.
- CAR: formato `UF-XXXXXXX-XXXX.XXXX.XXXX.XXXX.XXXX.XXXX.XXXX.XXXX` (validar padrão, sem consulta externa).
- Soma das áreas por cultura ≤ área útil declarada (aviso, não bloqueio).
- Mensagens de erro claras, em português, próximas ao campo e resumidas no topo.

---

## 6. Matriz de documentos

Configurável no admin (adicionar/remover tipos, obrigatoriedade, condição, validade em dias). Valores iniciais:

| Documento | Condição | Obrigatório | Validade padrão |
|---|---|---|---|
| Documento de identidade (tomador e cônjuge) | PF | Sim | — |
| Contrato social e atos de representação | PJ | Sim | — |
| Comprovante de residência | Sempre | Sim | 90 dias |
| Certidão de casamento/união estável | Casado/união | Sim | — |
| Certidões negativas (Federal/PGFN, CNDT, FGTS, protestos, distribuidores) | Sempre | Sim | 30 dias (configurável por certidão) |
| Matrícula atualizada | Por imóvel | Sim | 30 dias |
| CCIR, ITR (5 anos) e CND do ITR | Por imóvel | Sim | CCIR/CND conforme vigência |
| Recibo do CAR | Por imóvel | Sim | — |
| Certificação SIGEF / georreferenciamento | Por imóvel | Condicional | — |
| Contrato de arrendamento/parceria | Imóvel arrendado | Sim | — |
| IRPF com LCDPR (ou balanço/DRE se PJ) | Sempre | Sim | — |
| Notas fiscais de produtor / contratos de venda | Sempre | Recomendado | — |
| Declaração de rebanho e GTAs | Pecuária | Sim | 90 dias |
| Apólice de seguro rural | Se possuir | Não | — |
| Licenças ambientais / outorga de água | Se aplicável | Condicional | — |
| Laudo de avaliação com ART | Garantia real | Recomendado (pode ser solicitado depois) | 180 dias |

Condições disponíveis na matriz: sempre, PF, PJ, casado/união, por imóvel, por imóvel arrendado, pecuária, seguro, licenças, garantia real, **qualquer garantia oferecida** (1.3.0) e "apenas sob solicitação". Com imóveis/garantias desativados ou não oferecidos, os documentos dessas condições não são pedidos.
| Autorização de consulta SCR assinada | Sempre | Sim | — |

Regras:
- A equipe pode **solicitar documento adicional** a qualquer momento (gera pendência, e-mail e item na área do cliente).
- Cada documento tem revisão individual (aceito/recusado com motivo). Recusado → cliente é notificado e pode reenviar só aquele item.
- Cron diário alerta a equipe sobre certidões que vencerão antes da data prevista do comitê.

---

## 7. Área do cliente e shortcodes

| Shortcode | Função |
|---|---|
| `[ebcr_portal]` | Página única: exibe login/cadastro para visitantes; para clientes logados, o painel completo; para equipe logada, um atalho para o painel admin |
| `[ebcr_login]` | Apenas o formulário de login (com links de cadastro e senha esquecida) |
| `[ebcr_register]` | Apenas cadastro |
| `[ebcr_form]` | Apenas o wizard (redireciona para login se não autenticado) |
| `[ebcr_cta]` | Bloco de chamada para ação configurável (título, texto, botão) para usar na página de captação |
| `[ebcr_client_area_button]` | (1.3.0) Botão "Área do Cliente" (`label`, `label_logged`, `style` = primary/outline/ghost/link, `icon`, `class`); também como bloco `ebcr/client-area-button`, botão fixo no topo, item de menu/bloco Navegação e atributo `data-ebcr-client-area`. Endereço amigável `/area-do-cliente/` (ou `?ebcr_client_area=1` com links permanentes simples) |

A área do cliente segue a identidade visual configurada (§11.4, aba Identidade visual) por meio de variáveis CSS `--ebcr-*` limitadas aos invólucros do plugin; todos os links internos usam `add_query_arg()` sobre `get_permalink()` e funcionam com links permanentes simples (`?page_id=`) ou amigáveis.

### Cadastro e acesso
- Cadastro com nome, e-mail, telefone e senha forte (medidor de força + mínimo configurável), captcha e aceite dos termos.
- Confirmação de e-mail obrigatória (link com token de uso único, expira em 24 h) antes de enviar qualquer submissão.
- Recuperação de senha pelo fluxo nativo do WordPress, com templates personalizados.
- Limite de tentativas de login por IP e por usuário (§8.4).
- Opcional: login sem senha por link mágico (P1).

### Painel do cliente
- Lista de submissões com status, data e próxima ação esperada.
- Linha do tempo do status (apenas comentários marcados como visíveis ao cliente).
- Pendências em destaque ("A equipe solicitou: certidão X") com upload direto.
- Mensagens com a equipe (thread por submissão).
- Documentos enviados, com status de revisão e download (só os próprios).
- Botão "Nova solicitação", desabilitado com contagem regressiva quando o intervalo mínimo não foi cumprido.
- Perfil: dados de contato, alteração de senha.
- Privacidade (LGPD): baixar cópia dos próprios dados (JSON/PDF), solicitar correção, solicitar exclusão (vira tarefa para a equipe, pois pode haver obrigação legal de retenção).

### Regras de nova submissão
- Intervalo mínimo entre submissões enviadas: configurável em dias (padrão 30).
- Opção: bloquear nova submissão enquanto houver outra em andamento (padrão: sim).
- Validação sempre no servidor, não apenas escondendo o botão.
- Opção de "duplicar dados da submissão anterior" para facilitar (documentos com validade vencida não são copiados).

---

## 8. Segurança (requisitos obrigatórios)

### 8.1 Princípios gerais
- Nonce em todo formulário e ação (`wp_nonce_field`/`check_admin_referer`/`wp_verify_nonce`).
- `current_user_can()` em toda ação; checagem de **propriedade** (o `user_id` da submissão/documento é o usuário atual) em toda ação do cliente, prevenindo IDOR.
- Sanitização na entrada (`sanitize_text_field`, `absint`, validadores próprios) e escape na saída (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`).
- Endpoints REST com `permission_callback` real (nunca `__return_true`).
- Sem `eval`, sem `extract`, sem inclusão dinâmica de arquivos a partir de input.
- Mensagens de erro genéricas para autenticação ("e-mail ou senha inválidos").

### 8.2 Anti-spam: captcha matemático + camadas extras
- Operação gerada no servidor (soma/subtração/multiplicação simples, números 1–20), exibida como texto e também com `aria-label` acessível.
- Resposta armazenada em transient atrelado a um token aleatório (não em campo oculto nem no JS), **uso único**, expira em 10 min.
- Camadas adicionais: campo honeypot oculto; tempo mínimo de preenchimento (ex.: 3 s para login/cadastro); limite de requisições por IP.
- Arquitetura com interface `CaptchaProvider` para permitir no futuro trocar por Cloudflare Turnstile ou hCaptcha pelas configurações.

### 8.3 Arquivos: armazenamento e acesso
- **Armazenamento fora da pasta pública** sempre que possível: caminho configurável (ex.: `/home/usuario/ebcr-private/`). Na ativação, testar se o caminho existe e é gravável.
- Fallback: `wp-content/uploads/ebcr-private/` com `.htaccess` (`Require all denied`), `index.php` vazio em cada subpasta, **e** teste automático no painel que tenta baixar um arquivo sentinela por URL direta e mostra alerta vermelho se conseguir (servidores Nginx ignoram `.htaccess`; exibir o bloco `location` necessário na tela de ajuda).
- Subpastas por usuário com nome aleatório (não o ID).
- Nome armazenado = hash aleatório sem extensão executável; nome original guardado só no banco.
- Download **somente** via controlador PHP autenticado: `?ebcr_download={public_id}&_wpnonce=…`, que verifica: logado → dono do documento **ou** capacidade `ebcr_download_documents` (e, se configurado, analista atribuído) → registra no audit log → envia com `Content-Type` correto, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`.
- Visualização inline de PDF/imagem no admin via mesmo controlador (`inline`).

### 8.4 Validação de upload
- Extensões permitidas configuráveis (padrão: pdf, jpg, jpeg, png). Nunca permitir svg, html, php, js, zip por padrão.
- Verificar MIME real com `finfo` e com `wp_check_filetype_and_ext`, e rejeitar se divergir.
- Limites configuráveis: tamanho máximo por arquivo (padrão 10 MB), quantidade máxima por submissão e **cota total por usuário** (padrão 200 MB); mostrar uso da cota no painel do cliente.
- Checar também `upload_max_filesize` e `post_max_size` do PHP e exibir no admin se forem menores que o configurado.
- Calcular SHA-256 (integridade e detecção de duplicados).
- Hook opcional para antivírus (ClamAV via `clamdscan`), habilitável nas configurações se disponível no servidor.
- Remover metadados EXIF de imagens (opcional, padrão ligado) para não vazar geolocalização desnecessária.

### 8.5 Autenticação e sessão
- Rate limit de login (ex.: 5 tentativas / 15 min por IP+usuário) com bloqueio progressivo; registrar no audit log.
- 2FA por e-mail ou TOTP para papéis da equipe (P1; recomendar plugin dedicado se não implementado).
- Forçar HTTPS nas páginas do portal (alerta no admin se o site não estiver em HTTPS).
- Expiração de sessão configurável para a equipe.

### 8.6 Criptografia em repouso (opcional, recomendada)
- Usar `sodium_crypto_secretbox` com chave definida em `wp-config.php` (`define('EBCR_ENCRYPTION_KEY', '…')`), **nunca** no banco.
- Aplicável a arquivos e a campos sensíveis (CPF/CNPJ, dados de renda e dívidas).
- Tela de configuração explica como gerar a chave e alerta que **perdê-la torna os dados irrecuperáveis**; não permitir desligar a criptografia com dados já criptografados sem rotina de migração.

### 8.7 Auditoria
Registrar: login/logout/falhas, criação/envio de submissão, upload, download/visualização de documento (quem, quando, IP), mudança de status, alteração de configurações, exportações, solicitações LGPD. Tela de consulta com filtros e retenção configurável.

### 8.8 E-mail e documentos
**Não anexar documentos aos e-mails por padrão.** E-mail não é canal seguro, anexos acumulam em caixas de terceiros (problema de LGPD) e muitos provedores recusam mensagens grandes. Enviar resumo + link para a área logada. Deixar uma opção "anexar documentos ao e-mail do administrador" desligada, com aviso explicando o risco, caso o cliente do projeto insista.

---

## 9. Notificações por e-mail

- Envio via `wp_mail`. Desde a 1.3.1, SMTP autenticado no próprio plugin (§9a); plugin SMTP de terceiros continua opcional.
- Fila assíncrona (Action Scheduler se disponível, senão WP-Cron) com retentativas e log de falhas.
- Templates editáveis no admin (assunto + corpo HTML simples), com placeholders: `{nome}`, `{protocolo}`, `{status}`, `{comentario}`, `{link_portal}`, `{pendencias}`, `{data}`, `{site}`.
- Destinatários administrativos: lista de e-mails configurável, com opção de notificar também o analista atribuído.

| Evento | Cliente | Administração |
|---|---|---|
| Cadastro (confirmação de e-mail) | ✓ | — |
| Submissão enviada (com protocolo) | ✓ | ✓ |
| Mudança de status | ✓ (se comentário visível ou status público) | ✓ (opcional) |
| Pendência/documento solicitado | ✓ | — |
| Documento recusado | ✓ | — |
| Nova mensagem | ✓ / equipe | ✓ |
| Cliente respondeu pendência | — | ✓ |
| Lembrete de pendência sem resposta (X dias) | ✓ | ✓ |
| Certidões a vencer | — | ✓ |
| Cliente complementou bens/garantias (`complement_added`, 1.3.1) | — | ✓ (+ analista atribuído) |
| Lembrete para completar bens/garantias (`complement_reminder`, 1.3.1, uma vez) | ✓ | — |

Protocolo legível: `EB-2026-000123` (sequencial apenas para exibição; URLs continuam com UUID).

### 9a. Envio autenticado por SMTP (1.3.1)

- Configurações (aba E-mails, seção "Envio (SMTP)"): `smtp_enabled` (false), `smtp_host` (''), `smtp_port` (465), `smtp_secure` (`ssl` | `tls` | `none`; ssl), `smtp_auth` (true), `smtp_username` (''), `smtp_password` (somente escrita), `smtp_from_email` / `smtp_from_name` ('' = `from_email` / `from_name`), `smtp_apply_all` (true), `smtp_verify_peer` (true), `smtp_timeout` (15).
- Senha: constante `EBCR_SMTP_PASSWORD` no wp-config.php (prioridade; recomendada). Senão, opção cifrada com libsodium — `EBCR_ENCRYPTION_KEY` quando definida; sem ela, chave derivada de `wp_salt('secure_auth')` (protege só contra vazamento do banco; a tela avisa). Nunca exibida, exportada, auditada, devolvida por abilities ou incluída em transcrições.
- `phpmailer_init` (quando ligado e — se `smtp_apply_all` for falso — só para mensagens do plugin): isSMTP, Host, Port, SMTPSecure/SMTPAutoTLS, SMTPAuth, Username, Password, Timeout, SMTPOptions (verify_peer), From/FromName e sempre `Sender` (Return-Path) = remetente; com `smtp_apply_all`, filtros `wp_mail_from`/`wp_mail_from_name`. Com SMTP desligado, as mensagens do plugin recebem `Sender` = `from_email`.
- `wp_mail_failed`: últimas 20 falhas (data, mensagem sem credenciais, transporte, domínios dos destinatários) em `get-status` → `mail_failures.recent`; a fila mantém retentativas e `last_error` por item (`mail_failures.queue`).
- `run-tool test_smtp` (`to`) e botão na tela: `{sent, error, transport, host, port, secure, from, sender, to, transcript}` (via `run-tool`, desde a 1.3.2, `error` sai da resposta e o erro, quando há, vem em `smtp_error`: o Easy MCP AI trata qualquer chave `error` como falha da ferramenta) com a conversa SMTP (SMTPDebug 2) mascarada e o corpo omitido, até ~4000 caracteres; `get-status` → `smtp` com `password_source` (constant | option | none).

---

## 10. Fluxo de status

Editável no admin (nome, cor, visível ao cliente, texto padrão ao cliente, transições permitidas). Padrão:

```
rascunho → enviada → pre_analise ─┬→ pendencia_documental → (volta) pre_analise
                                  ├→ analise_credito → comite ─┬→ aprovada → formalizacao → concluida
                                  │                            └→ reprovada
                                  └→ reprovada
Qualquer estado (exceto concluida) → cancelada (pelo cliente se ainda não em análise; pela equipe sempre)
```

- `aprovada`/`reprovada` exigem `ebcr_change_final_status` e comentário interno obrigatório.
- Toda transição grava histórico e dispara as notificações configuradas.
- Reprovação: texto ao cliente padronizado e respeitoso (sem expor critérios internos), editável.

---

## 11. Painel administrativo

Menu "Crédito Rural" com:

### 11.1 Dashboard
- Cartões: novas (7 dias), em análise, com pendência, aprovadas no mês, volume solicitado e aprovado (R$), tempo médio por etapa.
- Gráficos (Chart.js empacotado): funil por status, submissões por mês, por UF/município, por atividade, por tipo de garantia.
- Listas: "Minhas tarefas de hoje" (CRM), "Pendências paradas há mais de X dias", "Certidões vencendo".

### 11.2 Submissões
- `WP_List_Table` com busca (nome, CPF/CNPJ, protocolo), filtros (status, analista, UF, atividade, período, faixa de valor), ordenação, ações em massa (atribuir analista, exportar CSV).
- Tela de detalhe com abas: Resumo · Tomador · Imóveis · Produção · Financeiro · Garantias · Documentos · Conferência · Histórico · Mensagens.
- **Aba Conferência** (checklist do analista, tabela `ebcr_checks`), com itens pré-configurados:
  - Área: matrícula × CAR × SIGEF × imagem de satélite.
  - Produtividade declarada × média municipal (Conab/IBGE), com campo para o valor de referência.
  - Faturamento declarado × IRPF/LCDPR × notas.
  - Dívidas declaradas × SCR × ônus na matrícula.
  - Consultas socioambientais (embargos IBAMA/ICMBio, cadastro de empregadores do MTE, sobreposição com TI/UC/quilombolas, desmatamento).
  - Certidões dentro da validade; outorga do cônjuge; PEP/sanções.
  - LTV por garantia (valor avaliado × valor solicitado × haircut configurado por tipo).
  - Cada item: ok / alerta / reprovado / n.a. + observação + quem conferiu.
- Botão "Gerar dossiê em PDF para comitê" (P1): resumo, checklist, pareceres, lista de documentos.
- Painel lateral: alterar status (com comentário interno e comentário ao cliente separados), atribuir analista, solicitar documento, enviar mensagem.

### 11.3 CRM
- Ficha por cliente: dados de contato, origem do lead, tags, estágio, responsável, histórico de todas as submissões.
- Atividades: registrar ligação, reunião, visita técnica, e-mail; tarefas com data e responsável; lembrete por e-mail ao responsável.
- Visão em lista e em quadro Kanban por estágio (P1).
- Exportação CSV (com permissão `ebcr_export`, registrada no audit log).

### 11.4 Configurações (abas, com texto de ajuda em cada campo)
Cada campo deve ter descrição curta abaixo explicando **o que faz, valor recomendado e impacto**. Topo de cada aba com um parágrafo introdutório. Botão "Restaurar padrão" por aba.

1. **Geral** — nome da operação, e-mails administrativos, página do portal (seleção de página), prefixo do protocolo, logotipo para e-mails.
1a. **Identidade visual** (1.3.0) — nome e logotipo da marca, cores (primária, texto sobre a primária, destaque, fundo, superfície, texto, texto secundário, bordas), arredondamentos, fontes e folhas de estilo de fontes, "herdar do tema", predefinições (Évellyn Brandão, BS Agro Capital, Neutro) e verificação de contraste WCAG AA.
1b. **Botão Área do Cliente** (1.3.0) — textos, ícone, botão fixo no topo (posição/distância/estilo), item em menu clássico ou bloco Navegação, endereço amigável.
2. **Formulário** — ativar/desativar etapas e campos opcionais, limites de valor e prazo, textos de ajuda por etapa, lista de atividades e finalidades.
2a. **Bens e garantias** (1.3.0) — exigência de imóveis (etapa 2) e garantias (etapa 5): obrigatório, opcional ou desativado; finalidades que sempre exigem garantia. 1.3.1: textos de orientação do modo opcional (`assets_optional_notice`, `guarantees_optional_notice`), status em que o cliente pode completar depois do envio (`complement_statuses`) e lembrete (`complement_reminder_days`). Ver §12c.
3. **Documentos e uploads** — extensões, tamanho por arquivo, cota por usuário, máximo por submissão, matriz de documentos (§6), validade padrão por tipo, remoção de EXIF, antivírus.
4. **Regras de submissão** — intervalo mínimo em dias, bloquear se houver outra em andamento, prazo para cliente responder pendências antes de lembrete/cancelamento automático.
5. **Segurança** — caminho de armazenamento privado com **botão "Testar proteção"**, criptografia (status da chave), rate limit de login, captcha (provedor e dificuldade), honeypot, tempo mínimo, expiração de sessão da equipe, analista vê só as atribuídas.
6. **E-mails** — remetente, templates por evento com pré-visualização, botão "Enviar e-mail de teste", opção de anexos (desligada, com aviso) e, desde a 1.3.1, seção "Envio (SMTP)" com "Testar SMTP (com diagnóstico)" (§9a).
7. **Privacidade e compliance** — editor/seleção de página para cada política (sem página escolhida, procura por slugs candidatos, ex.: privacidade → `aviso-de-privacidade`, `politica-de-privacidade`, página de privacidade do WordPress), título e **versão** de cada texto (alterar a versão exige novo aceite no próximo acesso), prazo de retenção de dados e documentos após encerramento, contato do encarregado (DPO), texto das declarações e páginas complementares (portal do titular, cookies, comercialização, canal de integridade).
7a. **Canais de atendimento** (1.3.0) — e-mail de destino de cada canal (`channel_email_contato`, `channel_email_dpo`, `channel_email_ouvidoria`; vazio = e-mail do administrador), recibo ao remetente (`channel_send_receipt`) e retenção das mensagens (`channel_message_retention_days`, padrão 1825). Ver §12b.
8. **Status** — editor do fluxo (§10).
9. **Ferramentas** — verificação de ambiente (versão do PHP, limites de upload, HTTPS, extensões `sodium`/`fileinfo`, WP-Cron ativo, SMTP), exportar/importar configurações (sem segredos), reprocessar fila de e-mails, rodar rotina de retenção.
10. **Desinstalação** — "Manter dados ao desinstalar" (padrão: sim), com aviso claro.

### 11.5 Ajuda
Página interna com: primeiros passos (criar página com `[ebcr_portal]`, configurar SMTP, testar proteção de arquivos, definir chave de criptografia), explicação de cada papel, bloco de configuração Nginx, perguntas frequentes e checklist de publicação.

### 11.6 Log de auditoria
Tela com filtros (usuário, ação, período, objeto) e exportação.

---

## 12. LGPD e retenção

- Registrar base legal de cada tratamento na política (procedimentos preliminares a contrato, cumprimento de obrigação legal/regulatória, proteção do crédito, consentimento para marketing).
- Minimização: só pedir o que a matriz exige; campos opcionais claramente marcados.
- Retenção configurável: por exemplo, submissões reprovadas ou canceladas anonimizadas após X meses; aprovadas mantidas pelo prazo legal definido pelo jurídico.
- Rotina de anonimização: remove documentos, substitui dados pessoais por hash, mantém estatísticas agregadas.
- Integração com os exportadores e apagadores de dados pessoais nativos do WordPress (`wp_privacy_personal_data_exporters` / `erasers`).
- Consentimentos versionados e com prova (hash do texto, data, IP, user agent).

---

## 13. Qualidade e testes

- PHPUnit com `wp-env` ou `WP_UnitTestCase`.
- **Testes de autorização obrigatórios** (critério de aceite para release):
  - [ ] Cliente A não consegue ver, baixar, editar ou listar submissão/documento do cliente B (por UUID direto, por REST e por admin-ajax).
  - [ ] Visitante não autenticado recebe 403/redirect em todo endpoint de download.
  - [ ] URL direta do arquivo armazenado retorna 403/404.
  - [ ] Analista sem permissão de status final não consegue aprovar/reprovar.
  - [ ] Submissão antes do intervalo mínimo é rejeitada no servidor mesmo com requisição forjada.
  - [ ] Captcha reutilizado ou expirado é rejeitado.
  - [ ] Upload de `.php` renomeado para `.pdf` é rejeitado pela checagem de MIME.
  - [ ] Arquivo acima do limite e usuário acima da cota são rejeitados.
- Testes unitários dos validadores (CPF, CNPJ, CAR, CEP).
- PHPCS sem erros de segurança.

---

## 14. Fases de entrega

**Fase 1 — MVP seguro (P0)**
Bootstrap, instalação/migrações, papéis, cadastro com confirmação de e-mail, login, captcha + honeypot + rate limit, wizard completo com validação, uploads protegidos com download autenticado, aceites versionados, envio com e-mails (cliente e admin), painel do cliente (lista, status, pendências, reenvio), admin com lista de submissões, detalhe, alteração de status, solicitação de documentos, configurações essenciais com ajuda, audit log básico, testes de autorização.

**Fase 2 — Operação (P1)**
Dashboard com gráficos, CRM completo com tarefas e Kanban, checklist de conferência, dossiê PDF, alertas de validade de certidões, lembretes automáticos, criptografia em repouso, exportações, ferramentas de diagnóstico, 2FA da equipe.

**Fase 3 — Evolução (P2)**
Consulta automática de CEP/CNPJ (APIs públicas), simulador de crédito na página de captação, assinatura eletrônica, integração com WhatsApp para notificações, captcha Turnstile, relatórios por fundo/carteira.

---

## 15. Perguntas em aberto

| Pergunta | Quem responde | Bloqueia? |
|---|---|---|
| Servidor é Apache ou Nginx? Há acesso a pasta fora do `public_html`? | Hospedagem | Sim (armazenamento) |
| Quais políticas (privacidade, compliance, termos) já existem e quem aprova os textos? | Jurídico | Sim (etapa 7) |
| Prazos de retenção para reprovadas e aprovadas | Jurídico/DPO | Não (padrões provisórios) |
| Valores mínimo/máximo de crédito, prazos e finalidades aceitas pelo fundo | Gestora do fundo | Não |
| Haircuts e LTV máximos por tipo de garantia | Gestora / comitê | Não (Fase 2) |
| Quantos analistas e quem aprova (gestor único ou comitê)? | Operação | Não |
| Já existe plugin SMTP e de segurança no site? | Responsável pelo site | Não |

---

## 16. Instruções para o Claude Code

1. Leia este SPEC inteiro e o código atual do tema/site antes de criar arquivos.
2. Liste as perguntas da §15 que conseguir responder inspecionando o ambiente (servidor web, versão do PHP, plugins ativos) e pergunte o restante.
3. Implemente **apenas a Fase 1**, em commits pequenos por módulo, rodando PHPCS e os testes a cada módulo.
4. Priorize nesta ordem: segurança de arquivos e autorização → modelo de dados → wizard e validação → e-mails → portal do cliente → admin.
5. Ao terminar a Fase 1, gere um `README.md` com instalação, configuração inicial e o checklist de publicação.

---

## 12a. Consentimento de cookies (1.3.0)

- Rota pública `POST ebcr/v1/cookie-consent` (sem autenticação, 20 registros/hora por IP) recebendo `consent_id` (UUID v4), `categories` (booleanos para `necessary`, `functional`, `analytics`, `advertising`), `action` (`accept_all` | `reject_all` | `custom` | `withdraw`), `version` (inteiro) e `path` (só o caminho, até 200 caracteres). Resposta `{ok:true}`; nunca devolve dados gravados.
- Tabela `ebcr_cookie_consents`: consent_id, categorias (JSON), ação, versão, created_at (UTC), `ip_hash` = HMAC-SHA256(IP, `wp_salt('auth')`) — nunca o IP em claro —, user agent (180), caminho, user_id quando conectado.
- Tela somente leitura "Consentimentos de cookies" com filtros e CSV; retenção `cookie_consent_retention_days` (padrão 730) na rotina de retenção; exportador/apagador de dados pessoais por user_id (o apagador desvincula e mantém o registro anônimo).
- O endereço é exposto ao front-end em `window.EBCR_CLIENT_AREA.consentEndpoint` (via `rest_url()`).

## 12b. Canais de atendimento: contato, titular (DPO) e integridade (1.3.0, banco 1.3.1)

- Rota pública `POST ebcr/v1/channel-message` (`permission_callback` aberto; 10 envios/hora por IP; honeypot `_honey` vazio; com usuário conectado, `X-WP-Nonce` vincula o registro ao usuário). JSON ou formulário.
- Corpo: `channel` (`contato` | `dpo` | `ouvidoria`); `protocol` opcional no formato `^(CT|LGPD|OUV)-\d{8}-[A-Z0-9]{4}$` com o prefixo do canal — ausente, inválido, de outro canal ou já usado → gerado no servidor (`PREFIXO-AAAAMMDD-XXXX`); `anonymous` (só `ouvidoria`); `page` (só o caminho, até 200); `fields` (objeto, até 20 chaves, lista branca `nome, email, telefone, empresa, assunto, mensagem, relacao, direito, descricao, tipo, quando, onde, envolvidos, relato, evidencias, consentimento, declaracao_titular, boa_fe`; texto sanitizado até 5000 caracteres; `email` validado).
- Obrigatórios: contato `nome, email, assunto, mensagem, consentimento`; dpo `nome, email, relacao, direito, descricao, declaracao_titular, consentimento`; ouvidoria `tipo, relato, boa_fe`. Relato anônimo descarta `nome`, `email`, `telefone` e não grava hash de IP, user agent nem usuário.
- Respostas: `{ok:true, protocol}` (sempre o protocolo gravado) ou `{ok:false, message[, fields]}` com 400/429 (mensagem genérica em português); 500 só se o banco recusar a gravação.
- Tabela `ebcr_channel_messages`: channel, protocol (único), anonymous, fields (JSON), email (minúsculo, vazio se anônimo), page, ip_hash (HMAC-SHA256 com `wp_salt('auth')`), user_agent (180), user_id, status (`novo` | `em_andamento` | `concluido`), created_at/updated_at (UTC).
- E-mails pela fila: para o destinatário do canal (vazio = administrador), assunto `[<Canal>] <protocolo> — <nome do site>`, campos escapados, Reply-To do remetente (se e-mail válido e não anônimo), nunca o hash do IP; recibo com o protocolo ao remetente se `channel_send_receipt` (padrão ligado).
- Tela "Mensagens dos canais" (capacidade `ebcr_manage_channels`, gestores e administradores): filtros por canal/status/período, detalhe, troca de status e CSV via admin-post com nonce (também `ebcr_export`; células neutralizadas contra fórmulas; sem hash de IP/user agent). Auditoria: `channel_message_viewed`, `channel_status_changed`, `export`.
- Retenção `channel_message_retention_days` (padrão 1825) na rotina de retenção; exportador/apagador de dados pessoais por e-mail (anônimas nunca entram; o apagador remove nome, e-mail, telefone, empresa, hash do IP, user agent e usuário, mantendo o registro do atendimento).
- Endereço exposto em `window.EBCR_CLIENT_AREA.channelEndpoint` e em `get-status` (`modules.channels`).
- `disable_wp_emoji` (Geral, padrão ligado) remove do site público o script/estilos de emoji do WordPress.

---

## 12c. Bens e garantias opcionais: orientação e complemento depois do envio (1.3.1, banco 1.3.2)

- Modo opcional: quadro `role="note"` (cores da marca) nas etapas 2 e 5 e no resumo da etapa 7 quando nada foi informado, com texto configurável: não é obrigatório, mas é pertinente preencher o mais completo possível; dá para completar depois pela Área do Cliente.
- Área do Cliente → solicitação: quadro "Bens e garantias" (itens, datas de inclusão/alteração depois do envio, "Completar agora" / "Adicionar ou editar") e formulário de complemento (templates das etapas 2 e 5 em modo `complement`, ação `ebcr_complement`, nonce próprio) com as mesmas regras de `Steps::validate()`; permitido ao dono, nas partes em modo opcional e nos status de `complement_statuses` (padrão: enviada, pré-análise, pendência documental, análise de crédito, comitê, formalização). Itens existentes mantêm ID, data de inclusão e campos da equipe; não podem ser removidos pelo cliente; envio sem alteração é recusado; vínculo de garantia real só com imóveis da própria solicitação.
- Ao salvar: `submissions.complemented_at`; histórico sem mudança de status; auditoria `complement_added`; e-mail `complement_added` (administração + analista atribuído); pedidos de documento (`origin = complemento`, `ref_key` do imóvel) para os slots novos de nível obrigatório ou recomendado ainda não atendidos — o upload do pedido herda o `ref_key` e satisfaz o slot; esses pedidos não levam ao cancelamento automático.
- Telas da equipe (wp-admin e painel no site): itens marcados "Incluído/Alterado pelo cliente em … depois do envio" e aviso do último complemento; pedidos automáticos identificados.
- Lembretes: aviso no painel do cliente e um e-mail (`complement_reminder`) `complement_reminder_days` dias depois do envio (0 desliga; janela de 30 dias), marcado em `complement_reminded_at`.
- Rascunho: aviso "Você tem uma solicitação em preenchimento" com a etapa atual e "Continuar preenchimento".
- `storage_path`: criada ao salvar (0750, index.php, .htaccess, web.config) se a pasta mãe for gravável; aviso para caminho dentro do WordPress; `get-status` → `storage.created`.
- Esquema 1.3.2: `submissions.complemented_at`, `submissions.complement_reminded_at`, `properties.updated_at`, `guarantees.updated_at`, `document_requests.ref_key`, `document_requests.origin`.

---

## 17. Histórico de versões

| Versão | Mudanças |
|---|---|
| 1.3.1 | Bens e garantias opcionais com orientação configurável e complemento depois do envio (§12c: histórico, auditoria, e-mail à equipe, pedidos automáticos de documentos, lembrete único, aviso no painel); "Continuar preenchimento" para rascunhos; criação/proteção da pasta privada ao salvar; SMTP autenticado com Return-Path, registro de falhas e `run-tool test_smtp` (§9a). Banco: `EBCR_DB_VERSION` 1.3.2. |
| 1.3.2 | `run-tool test_smtp` sem a chave `error` vazia (o Easy MCP AI a exibia como "Tool execution failed."); erro do SMTP em `smtp_error`. Sem mudança de banco. |
| 1.3.0 | Bens e garantias configuráveis (`guarantees_mode`, `guarantees_required_modalities`, `assets_mode`; filtros `ebcr_guarantees_required` e `ebcr_assets_required`; condição de matriz "qualquer garantia oferecida"); identidade visual da área do cliente (aba Identidade visual, predefinições Évellyn Brandão/BS Agro Capital/Neutro, herdar do tema, fontes, contraste WCAG, variáveis `--ebcr-*` com os valores originais como reserva, e-mails e login com a marca); botão "Área do Cliente" (shortcode, bloco, barra fixa, menus, `data-ebcr-client-area`, `window.EBCR_CLIENT_AREA`, filtro `ebcr_client_area_url`, `/area-do-cliente/`); páginas legais por slugs candidatos e links complementares; registro de consentimento de cookies (§12a); nome da operação padrão = nome do site e DPO padrão vazio (instalações existentes preservam os anteriores por migração de configurações); correção do erro fatal ao salvar a matriz de documentos. Canais de atendimento (§12b): `POST ebcr/v1/channel-message`, tela *Mensagens dos canais*, e-mails por canal com recibo, retenção e exportador/apagador; capacidade `ebcr_manage_channels`; `disable_wp_emoji`; o log de auditoria grava IDs textuais (o `object_id` era convertido para número pelo `$wpdb->field_types` do WordPress). Banco: novas tabelas `ebcr_cookie_consents` e `ebcr_channel_messages` (`EBCR_DB_VERSION` 1.3.1; a versão do plugin continua 1.3.0). |
| 1.2.2 | Identidade visual do painel e do login (logotipo, ícone da marca, ícone do site). |
| 1.2.1 | Páginas padrão para as políticas, momento do aceite configurável, etapas com a mesma altura. |
| 1.2.0 | Fases 2 e 3: gráficos, relatórios por carteira, CRM completo, dossiê em PDF, 2FA, assinatura eletrônica, CEP/CNPJ, Turnstile, WhatsApp, simulador, painel da equipe no site. |
| 1.1.x | Abilities API / Easy MCP AI; formulários do portal enviados à própria página do portal. |
| 1.0.0 | Fase 1 (MVP seguro). |
