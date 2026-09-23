# EB Crédito Rural — plugin WordPress

Captação de solicitações de crédito rural para o site de Évellyn Brandão: cadastro do produtor com confirmação de e-mail, formulário em sete etapas com validação no servidor, documentos armazenados fora do alcance público com download autenticado, notificações por e-mail, área do cliente e painel da equipe com fluxo de status, pedidos de documento, revisão, mensagens, checklist de conferência, auditoria e configurações explicadas campo a campo.

Esta é a **Fase 1 (MVP seguro)** do `SPEC.md`. Slug `eb-credito-rural`, prefixo `ebcr_`, namespace `EBCR\`.

## Requisitos

- PHP 8.1+ (testado em 8.3/8.4) com `fileinfo`; `sodium` para criptografia em repouso; GD ou Imagick para remover EXIF.
- WordPress 6.4+ (testado em 7.1).
- HTTPS no site (o portal trata dados pessoais e documentos).
- Um plugin SMTP (WP Mail SMTP, FluentSMTP ou Post SMTP) é fortemente recomendado.

## Instalação

1. Copie a pasta `eb-credito-rural` para `wp-content/plugins/` (ou envie o ZIP em Plugins → Adicionar novo) e ative.
   A ativação cria as tabelas (`{prefix}ebcr_*`), os papéis `ebcr_cliente`, `ebcr_analista` e `ebcr_gestor`, a pasta privada de documentos e as tarefas agendadas.
2. Crie uma página (ex.: *Área do produtor*) com o shortcode `[ebcr_portal]` e selecione-a em **Crédito Rural → Configurações → Geral**.
3. Em **Configurações → E-mails**, defina remetente e destinatários administrativos e use **Enviar e-mail de teste**.
4. Em **Configurações → Segurança**, informe uma pasta **fora da raiz pública** (ex.: `/home/usuario/ebcr-private`, criada e gravável pelo PHP) e clique em **Testar proteção da pasta agora**. Se a hospedagem não permitir pasta externa, o plugin usa `wp-content/uploads/ebcr-private/` protegida por `.htaccess`; no Nginx aplique o bloco `location` mostrado em **Ajuda** e repita o teste até obter "Protegido".
5. (Recomendado) Criptografia em repouso: adicione ao `wp-config.php` a linha gerada na aba Segurança —
   `define( 'EBCR_ENCRYPTION_KEY', 'base64:…' );` — guarde a chave em um cofre (perdê-la torna os dados irrecuperáveis) e ligue "Criptografar arquivos" e/ou "Criptografar campos sensíveis".
6. Crie os usuários da equipe (Usuários → Adicionar) com o papel *Analista de crédito* ou *Gestor de crédito*.
7. Revise com o jurídico as políticas e versões em **Privacidade e compliance** e, com a gestora do fundo, a matriz de documentos e os limites de valor/prazo.
8. Coloque `[ebcr_cta]` na página de captação.
9. (Opcional) Com o plugin **Easy MCP AI** ativo, as ferramentas do plugin ficam disponíveis ao agente de IA automaticamente (veja *Configuração por IA*). Se não aparecerem, use **Configurações → Ferramentas → Habilitar as abilities do plugin no Easy MCP AI**.

Se `DISABLE_WP_CRON` estiver definido, configure um cron do sistema chamando `wp-cron.php` a cada 5 minutos (fila de e-mails) — a rotina diária roda às 6h.

## Shortcodes

| Shortcode | Função |
| --- | --- |
| `[ebcr_portal]` | Login/cadastro para visitantes; painel completo para clientes; atalho ao admin para a equipe. Se nenhuma página estiver configurada, o plugin usa a primeira página publicada que contém o shortcode |
| `[ebcr_login]` / `[ebcr_register]` | Apenas login / apenas cadastro |
| `[ebcr_form]` | Formulário (redireciona ao login se necessário) |
| `[ebcr_cta titulo="" texto="" botao="" url=""]` | Chamada para ação |

## Configuração e operação por IA (Easy MCP AI / Abilities API)

O plugin registra 14 *abilities* na Abilities API do WordPress (6.9+), categoria `ebcr`, e as habilita sozinho na opção do plugin **Easy MCP AI** na ativação/atualização (sem remover outras). No agente elas aparecem como `wp_ability_ebcr_*`. Cada uma exige a mesma capacidade da tela equivalente e registra auditoria com origem `mcp`; dados sensíveis chegam mascarados e os arquivos nunca saem por esse caminho.

| Ferramenta | Função | Exige |
| --- | --- | --- |
| `wp_ability_ebcr_get_settings` | Lê configurações (todas, por aba ou por chave) com rótulo, ajuda e tipo | `ebcr_manage_settings` |
| `wp_ability_ebcr_update_settings` | Altera configurações (mesma sanitização/limites/avisos da tela) | `ebcr_manage_settings` |
| `wp_ability_ebcr_reset_settings` | Restaura os padrões de uma aba | `ebcr_manage_settings` |
| `wp_ability_ebcr_get_status` | Estado do plugin: ambiente, pasta privada, criptografia, fila de e-mails, contagens, pendências de publicação, estado do MCP | `ebcr_manage_settings` |
| `wp_ability_ebcr_run_tool` | `test_protection`, `test_email`, `process_mail`, `retry_mail`, `run_daily`, `run_retention`, `enable_mcp` | `ebcr_manage_settings` |
| `wp_ability_ebcr_list_team` | Lista analistas, gestores e administradores | `ebcr_change_final_status` |
| `wp_ability_ebcr_set_team_member` | Cria/atribui analista ou gestor por e-mail; `remover` tira da equipe | `promote_users` |
| `wp_ability_ebcr_list_submissions` | Lista solicitações com filtros (status, busca, responsável, paginação) | `ebcr_view_submissions` |
| `wp_ability_ebcr_get_submission` | Detalhe (dados mascarados, documentos, pendências, mensagens, histórico) | `ebcr_view_submissions` |
| `wp_ability_ebcr_change_status` | Muda status respeitando transições e papéis (aprovar/reprovar só gestor) | `ebcr_edit_submissions` |
| `wp_ability_ebcr_assign_submission` | Atribui responsável (ID ou e-mail) | `ebcr_edit_submissions` |
| `wp_ability_ebcr_request_document` | Abre pendência documental e notifica o cliente | `ebcr_edit_submissions` |
| `wp_ability_ebcr_send_message` | Mensagem interna ou ao cliente | `ebcr_edit_submissions` |
| `wp_ability_ebcr_review_document` | Aceita/recusa documento enviado | `ebcr_edit_submissions` |

Exemplos: “Mostre as configurações da aba E-mails”, “Defina os e-mails administrativos como contato@dominio e ligue o aviso ao analista”, “Qual o estado do plugin e o que falta para publicar?”, “Cadastre maria@exemplo.com como analista”, “Atribua a EB-2026-000012 ao João e peça a matrícula atualizada com 30 dias de prazo”. Se as ferramentas não aparecerem no agente: Easy MCP AI → Abilities → marcar o grupo *EB Crédito Rural* (ou **Configurações → Ferramentas → Habilitar**) e reconectar o agente.

## Como a segurança de arquivos e a autorização funcionam

- Documentos são gravados em subpasta aleatória por usuário, com nome aleatório e extensão `.dat`; o nome original fica só no banco. A pasta recebe `.htaccess` (`Require all denied`), `index.php` e `web.config`; um arquivo sentinela permite testar por HTTP se o servidor expõe a pasta (alerta vermelho no admin quando exposta).
- Download **somente** por `?ebcr_download={uuid}&ebcr_nonce=…`: exige login, nonce válido, e **dono do documento** ou capacidade `ebcr_download_documents` (respeitando "analista vê só as atribuídas"); registra no log de auditoria; envia com `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff` e `Cache-Control: private, no-store`. Visualização inline (PDF/imagem) só para a equipe, com CSP `sandbox`.
- Upload: extensão em lista permitida (svg/html/php/js/zip nunca), MIME real via `finfo` + `wp_check_filetype_and_ext`, assinatura `%PDF`, tamanho, quantidade por solicitação, cota por usuário, SHA-256 (duplicados), remoção de EXIF, antivírus opcional (ClamAV).
- Toda ação passa por `EBCR\Security\Authorization` (capacidade + propriedade), inclusive REST (`permission_callback` real) e `admin-post` (nonce + capacidade). Clientes não entram no `wp-admin`.
- Anti-abuso: captcha matemático de uso único em transient (10 min), honeypot, tempo mínimo de preenchimento assinado (HMAC), limite de tentativas de login por IP e usuário com bloqueio progressivo, limites em cadastro/upload/captcha/recuperação de senha, sessão da equipe com expiração configurável.
- Auditoria: login/logout/falhas/bloqueios, cadastro, confirmação, criação/envio, status, upload, download/visualização, revisão, pedidos, exportações, LGPD, configurações; tela com filtros e CSV; retenção configurável.

## Estrutura

```
eb-credito-rural.php   bootstrap, constantes, autoloader
uninstall.php          respeita "manter dados ao desinstalar"
src/Install            Schema (dbDelta), Migrator, Activator, Deactivator
src/Roles              Capabilities (papéis e capacidades)
src/Database           repositórios por tabela ($wpdb->prepare em todas as consultas)
src/Domain             Status (fluxo), Consent (políticas versionadas), Protocol
src/Forms              Steps (7 etapas), Validators (CPF, CNPJ, CAR, CEP…), DocumentMatrix, Wizard, SubmissionRules, SubmissionService
src/Security           Authorization, MathCaptcha, Honeypot, RateLimiter, LoginGuard, Nonces, Crypto (sodium), AuditLog, PrivacyIntegration, Retention
src/Files              FileGuard, Storage, UploadHandler, DownloadController, Exif, Antivirus
src/Mail               Events (templates), Mailer, Queue (Action Scheduler ou WP-Cron, 3 tentativas), Notifier
src/Frontend           Auth, Portal, Shortcodes/assets, Fields
src/Admin              Menu, Dashboard, SubmissionsList, SubmissionView, Actions, Settings (10 abas), Help, AuditLogView
src/Rest               ebcr/v1 (etapas, uploads, envio, mensagens, status, atribuição, pedidos, revisão)
src/Cron               lembretes, certidões a vencer, retenção, limpezas
templates/             portal, wizard, admin e e-mails (sobrescrevíveis pelo tema em /eb-credito-rural/)
assets/                CSS/JS sem CDN
tests/                 PHPUnit (autorização, segurança, validadores, wizard)
```

## Desenvolvimento e testes

```bash
# PHPCS (WordPress-Extra + Security)
composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
phpcs            # usa phpcs.xml.dist

# Testes: precisa de um WordPress instalado com o plugin em wp-content/plugins/eb-credito-rural
WP_ROOT=/caminho/do/wordpress phpunit
```

Os testes de autorização cobrem os critérios de aceite do SPEC: cliente A não vê, baixa, edita nem lista dados de B (por UUID, por REST e pelo controlador de download); visitante recebe 401/403; analista não aprova/reprova; submissão fora do intervalo é rejeitada no servidor mesmo com requisição forjada; captcha reutilizado/expirado é rejeitado; `.php` renomeado para `.pdf` é rejeitado pela checagem de MIME; arquivo acima do limite e usuário acima da cota são rejeitados.

## Checklist de publicação

- [ ] Site em HTTPS
- [ ] Página do portal criada e selecionada
- [ ] Teste de proteção da pasta = "Protegido" ou pasta fora da raiz pública
- [ ] Chave de criptografia definida e guardada (se ligada)
- [ ] SMTP configurado e e-mail de teste recebido
- [ ] Políticas revisadas pelo jurídico, versões definidas
- [ ] Matriz de documentos e limites revisados pela gestora do fundo
- [ ] Usuários da equipe com papéis corretos
- [ ] Cron do sistema (se `DISABLE_WP_CRON`)
- [ ] Backup do banco e da pasta privada

## Versões

- **1.1.0** — Abilities API / Easy MCP AI (14 ferramentas `wp_ability_ebcr_*`, auto-habilitadas no conector), limites de segurança aplicados na sanitização dos campos numéricos, e-mails padrão `contato@dominio`, detecção automática da página do portal, bloco de estado do MCP em Ferramentas e Ajuda.
- **1.0.0** — Fase 1 completa (segurança de arquivos, autorização, wizard de 7 etapas, matriz de documentos, fila de e-mails, CRM básico, LGPD, auditoria).

## Fora desta fase

Dashboard com gráficos, CRM completo (tarefas/Kanban), dossiê em PDF, 2FA da equipe, importação automática de CEP/CNPJ, assinatura eletrônica e integrações externas (SCR, Serasa, SICAR) — ver `SPEC.md` §14.
