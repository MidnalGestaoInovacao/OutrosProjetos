# EB Crédito Rural — plugin WordPress

Captação de solicitações de crédito rural (criado para o site de Évellyn Brandão e usado também no site da BS Agro Capital): cadastro do produtor com confirmação de e-mail, formulário em sete etapas com validação no servidor, documentos armazenados fora do alcance público com download autenticado, notificações por e-mail, área do cliente com a identidade visual de cada site, botão "Área do Cliente" e painel da equipe com fluxo de status, pedidos de documento, revisão, mensagens, checklist de conferência, auditoria e configurações explicadas campo a campo.

Esta é a **Fase 1 (MVP seguro)** do `SPEC.md`. Slug `eb-credito-rural`, prefixo `ebcr_`, namespace `EBCR\`.

## Novidades da 1.3.0

- **Bens e garantias configuráveis** (Configurações → *Bens e garantias*): imóveis rurais (etapa 2) e garantias (etapa 5) podem ser **obrigatórios** (padrão, igual às versões anteriores), **opcionais** (o cliente pode declarar "não tenho garantia/imóvel a informar") ou **desativados** (a etapa some, as etapas seguintes são renumeradas e os documentos ligados a ela não são pedidos). Finalidades marcadas continuam exigindo garantia no modo opcional. Validação no navegador e no servidor; filtro `ebcr_guarantees_required`.
- **Identidade visual da área do cliente** (Configurações → *Identidade visual*): nome e logotipo da marca, cores, arredondamentos, fontes (com folhas de estilo externas), "Herdar do tema" e predefinições **Évellyn Brandão** (visual original), **BS Agro Capital** e **Neutro**; verificação de contraste WCAG AA. Vale para portal, login/cadastro, formulário, simulador, assinatura eletrônica, painel da equipe no site, verificação em duas etapas, botão "Área do Cliente", e-mails e tela de login do WordPress (quando a paleta não é a original).
- **Botão "Área do Cliente"**: shortcode `[ebcr_client_area_button]`, bloco `ebcr/client-area-button`, botão fixo no topo, item em menu clássico ou no bloco Navegação, atributo `data-ebcr-client-area` em qualquer link, `window.EBCR_CLIENT_AREA` e endereço amigável `/area-do-cliente/` (com `?ebcr_client_area=1` para links permanentes simples).
- **Páginas legais por slug candidato**: sem página escolhida, cada política procura a primeira página publicada entre slugs padrão (privacidade: `aviso-de-privacidade`, `politica-de-privacidade` e, por fim, a página de privacidade do WordPress); novas páginas complementares (portal do titular, cookies, comercialização, canal de integridade) aparecem na área Privacidade do cliente. Título de cada política editável.
- **Registro de consentimento de cookies** (prova do consentimento, LGPD art. 8º, § 2º): rota pública `POST ebcr/v1/cookie-consent` para o banner de cookies do site, tela *Crédito Rural → Consentimentos de cookies* com filtros e CSV, retenção configurável e integração com o exportador/apagador de dados pessoais. Nova tabela `{prefix}ebcr_cookie_consents` (`EBCR_DB_VERSION` 1.3.0, criada automaticamente na atualização).
- Padrões neutros para novos sites: nome da operação = "Crédito Rural — {nome do site}" e encarregado (DPO) vazio (a atualização preserva os valores anteriores de quem já usava o plugin).
- Correções: salvar a aba *Documentos e uploads* (matriz de documentos) causava erro fatal no PHP 8; aviso do PHP 8.3+ ao revalidar solicitação com a etapa financeira vazia; `font` inválido nos botões.

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
| `[ebcr_portal]` | Login/cadastro para visitantes; painel completo para clientes; painel de operações da equipe (visão geral, solicitações, CRM, relatórios) para analistas e gestores. Se nenhuma página estiver configurada, o plugin usa a primeira página publicada que contém o shortcode |
| `[ebcr_login]` / `[ebcr_register]` | Apenas login / apenas cadastro |
| `[ebcr_form]` | Formulário (redireciona ao login se necessário) |
| `[ebcr_cta titulo="" texto="" botao="" url=""]` | Chamada para ação |
| `[ebcr_simulador taxa="" sistema="price|sac" valor="" prazo="" carencia="" titulo="" botao="" url="" tema="claro|escuro"]` | Simulador de crédito ilustrativo (Price/SAC, carência, tabela de parcelas) com botão para a área do produtor. Padrões em Configurações → Integrações |
| `[ebcr_client_area_button label="Área do Cliente" label_logged="Minha área" style="primary|outline|ghost|link" icon="yes|no" class=""]` | Botão que abre a área do cliente; para quem está conectado mostra "Primeiro nome · Minha área". Também disponível como bloco *Botão Área do Cliente* |

## Bens e garantias (etapas 2 e 5)

Configurações → **Bens e garantias** (chaves entre parênteses; padrões preservam o comportamento até a 1.2.2):

| Configuração | Valores | Padrão |
| --- | --- | --- |
| Garantias (`guarantees_mode`) | `required` \| `optional` \| `disabled` | `required` |
| Finalidades que sempre exigem garantia (`guarantees_required_modalities`) | lista de chaves de Formulário → Finalidades | vazia |
| Imóveis rurais / bens (`assets_mode`) | `required` \| `optional` \| `disabled` | `required` |

- **Obrigatório**: como antes — ao menos uma garantia/um imóvel.
- **Opcional**: a etapa pergunta "Você tem garantia (imóvel) a oferecer?". "Não" dispensa a lista e descarta linhas enviadas; "Sim" exige ao menos uma. O laudo de avaliação e os documentos por imóvel só são pedidos quando há garantia real/imóvel. Se a finalidade escolhida na etapa 4 estiver em *Finalidades que sempre exigem garantia* (ex.: crie `garantia_imovel|Crédito com garantia de imóvel` em Formulário → Finalidades e marque-a), a garantia volta a ser obrigatória.
- **Desativado**: a etapa não aparece (o cliente vê as etapas renumeradas), não pode ser enviada nem por requisição forjada, e os documentos ligados a ela não entram na matriz. Com imóveis desativados, garantias reais são descritas no texto (sem vínculo com imóvel).
- A regra vale no navegador (pergunta obrigatória e mínimo de linhas antes de enviar) e no servidor (`Steps`/`Wizard`/`SubmissionRules`), na revalidação do envio final, no resumo da etapa 7, nas telas da equipe (portal e wp-admin), no dossiê em PDF e na ability `get-submission` (campos `requirements`, `guarantees`, `properties`).
- Condição nova na matriz de documentos: **Qualquer garantia oferecida** (`guarantee`).
- Filtros para desenvolvedores: `ebcr_guarantees_required` (`bool $required, array $submission_context`) e `ebcr_assets_required` (mesma assinatura). O contexto traz `submission_id`, `public_id`, `user_id`, `purpose`, `amount`, `term_months`, `person_type`, `properties_count` e `mode`. Se o filtro exigir garantia com o modo desativado, a etapa reaparece.

## Identidade visual

Configurações → **Identidade visual**: escolha uma predefinição e clique em **Aplicar** (preenche os campos; nada muda até **Salvar**).

| Chave | O que controla | Padrão (visual original) |
| --- | --- | --- |
| `brand_preset` | Predefinição de referência (`evellyn`, `bsagro`, `neutro`, `custom`) | `evellyn` |
| `brand_name` | Nome no topo da área do cliente, telas de acesso, e-mails, 2FA e dossiê | vazio = nome do site |
| `brand_logo_id` | Logotipo (biblioteca de mídia) | vazio = logotipo do tema → ícone do site |
| `brand_portal_header` | Logotipo + nome no topo do portal, do painel da equipe e da tela de acesso | ligado |
| `brand_inherit_theme` | Usa paleta/fontes do tema de blocos (`wp_get_global_settings()`/`wp_get_global_styles()`), com os campos manuais como reserva | desligado |
| `brand_primary`, `brand_primary_hover`, `brand_primary_contrast` | Botões principais, etapa atual, chamada para ação | `#d4af37`, `#b8860b`, `#111111` |
| `brand_accent`, `brand_accent_strong` | Destaques e links | `#d4af37`, `#b8860b` |
| `brand_bg`, `brand_surface`, `brand_text`, `brand_muted`, `brand_border` | Fundo, cartões, texto, texto secundário, bordas (aceita `rgba()`) | vazio (fundo do tema), `#ffffff`, `#111827`, `#4b5563`, `#e5e7eb` |
| `brand_radius`, `brand_btn_radius` | Arredondamento de cartões e botões (px; 999 = pílula) | 12, 999 |
| `brand_button_style` | `gradient` (degradê gerado da primária) ou `solid` | `gradient` |
| `brand_font_heading`, `brand_heading_weight`, `brand_font_body` | Pilhas de fontes e peso dos títulos | vazio = fontes do tema |
| `brand_font_urls` | Folhas de estilo das fontes (https, uma por linha), carregadas só nas páginas do plugin | vazio |

A predefinição **BS Agro Capital** aplica: primária `#0d3527` (hover `#124a35`, texto `#fbf9f4`), destaque `#d4af37` / `#b8860b`, fundo `#fbf9f4`, superfície `#ffffff`, texto `#171b24`, secundário `#4b5262`, borda `rgba(23,27,36,.15)`, cartões com 20 px, botões em pílula, títulos `"Raleway"` 600 e texto `"Roboto"` (fontsource via jsDelivr). Por MCP: `update-settings` com `{"settings":{"brand_preset":"bsagro","brand_name":"BS Agro Capital"}}` ou `run-tool` com `{"tool":"apply_brand_preset","preset":"bsagro"}`.

Como funciona: os arquivos CSS do front-end (`portal.css`, `simulator.css`, `esign.css`, `team.css`, `2fa.css`, `client-area.css`) só **leem** as variáveis `--ebcr-primary`, `--ebcr-primary-hover`, `--ebcr-primary-contrast`, `--ebcr-accent`, `--ebcr-accent-strong`, `--ebcr-bg`, `--ebcr-surface`, `--ebcr-text`, `--ebcr-muted`, `--ebcr-border`, `--ebcr-radius`, `--ebcr-btn-radius`, `--ebcr-font-heading`, `--ebcr-font-body`, `--ebcr-heading-weight` (e derivadas como `--ebcr-primary-bg`, `--ebcr-link`, `--ebcr-dark`), com os valores originais como reserva. O plugin imprime os valores configurados em um estilo inline (`ebcr-brand`, via `wp_add_inline_style`) limitado aos invólucros `.ebcr-portal`, `.ebcr-cta`, `.ebcr-sim`, `.ebcr-2fa-page`, `.ebcr-ca-btn`, `.ebcr-ca-bar` — o restante do tema não é afetado. Na paleta original nenhuma variável derivada é impressa, então o visual da 1.2.2 é mantido. Filtros: `ebcr_brand_tokens`, `ebcr_brand_css`, `ebcr_brand_name`, `ebcr_brand_logo_url`, `ebcr_brandbar_show_name`.

Acessibilidade: foco visível em todos os controles; a tela mostra a razão de contraste de texto/primária, texto/fundo, texto/cartões, secundário/cartões e links/cartões, avisa abaixo de 4,5:1 (também no retorno do `update-settings`) e, fora da paleta original, os links usam automaticamente a primeira cor com contraste suficiente (destaque forte → primária → texto).

## Botão "Área do Cliente"

- **Shortcode** `[ebcr_client_area_button]` — atributos `label` (padrão "Área do Cliente"), `label_logged` (padrão "Minha área"), `style` (`primary` | `outline` | `ghost` | `link`), `icon` (`yes` | `no`), `class`. Gera um `<a>` para a página do portal (configurada em Geral ou, se vazia, a que contém `[ebcr_portal]`) com `aria-label`; conectado, mostra "Primeiro nome · Minha área".
- **Bloco** `ebcr/client-area-button` (dinâmico, sem etapa de build): atributos `label`, `labelLogged`, `buttonStyle`, `showIcon` (+ classe e alinhamento).
- **Configurações → Botão Área do Cliente** (tudo desligado por padrão): botão fixo no topo (`client_area_bar`, `client_area_bar_position` = `top-right`|`top-left`, `client_area_bar_offset` em px, `client_area_bar_style`), inserido por `wp_body_open` (reserva no `wp_footer`) e oculto na própria página do portal; item no menu clássico (`client_area_menu_location`) e no primeiro bloco Navegação (`client_area_nav_block`) com `client_area_menu_style`; textos `client_area_label`/`client_area_label_logged`; ícone `client_area_icon`.
- **Qualquer link**: `<a href="#" data-ebcr-client-area>Área do Cliente</a>` recebe o endereço do portal e, para quem está conectado, o texto "Nome · Minha área" (personalizável com `data-label-logged`). O auxiliar também expõe `window.EBCR_CLIENT_AREA = { url, label, labelLogged, loggedIn, userFirstName }` (desligável pelo filtro `ebcr_client_area_helper_enabled`).
- **Endereço amigável**: `/area-do-cliente/` (slug em `client_area_slug`, ligado por `client_area_alias`) redireciona ao portal quando não existe página com esse slug; as regras são renovadas na ativação e quando o slug muda. Com **links permanentes simples** (`?page_id=`), use `/?ebcr_client_area=1` (o caminho `/area-do-cliente/` também é tratado se o servidor o encaminhar ao WordPress). Todos os links internos do portal são montados com `add_query_arg()` sobre `get_permalink()` e funcionam nos dois modos.
- Filtro `ebcr_client_area_url` (`string $url, array $context`).

## Consentimento de cookies (registro)

O banner de cookies do site (de qualquer fonte) registra cada decisão do visitante em `POST {rest_url}ebcr/v1/cookie-consent` — o endereço exato fica em `window.EBCR_CLIENT_AREA.consentEndpoint` (montado com `rest_url()`, funciona com links permanentes simples: `/?rest_route=/ebcr/v1/cookie-consent`). Rota pública, limitada a 20 registros por hora por IP (429 acima disso).

```json
{
  "consent_id": "3f1c2b8e-9d4a-4c2b-8f1e-0a1b2c3d4e5f",
  "categories": { "necessary": true, "functional": true, "analytics": false, "advertising": false },
  "action": "custom",
  "version": 3,
  "path": "/credito-rural/"
}
```

- `consent_id`: UUID v4 gerado e guardado pelo banner (identifica o navegador; o mesmo ID em registros sucessivos mostra o histórico).
- `categories`: objeto com booleanos para `necessary`, `functional`, `analytics`, `advertising` (outras chaves são ignoradas; `necessary` é sempre gravado como `true`).
- `action`: `accept_all` (grava todas as categorias como aceitas), `reject_all` e `withdraw` (só as necessárias) ou `custom` (como enviado).
- `version`: inteiro — versão do texto/configuração do banner.
- `path`: caminho da página (até 200 caracteres; query string e fragmento são descartados).
- Resposta: `{"ok": true}` (400 com `ebcr_consent_invalid` se o corpo for inválido; nada do que foi gravado é devolvido).
- Gravado: `consent_id`, categorias (JSON), ação, versão, data (UTC), **hash** HMAC-SHA256 do IP com `wp_salt('auth')` (nunca o IP), user agent (até 180 caracteres), caminho e `user_id` se a pessoa estiver conectada (envie o cabeçalho `X-WP-Nonce` com `window.EBCR_CLIENT_AREA.consentNonce`, presente só para usuários conectados).
- Consulta: **Crédito Rural → Consentimentos de cookies** (capacidade `ebcr_view_audit_log`), com filtros por ID, ação e período e **Exportar CSV** (`ebcr_export`).
- Retenção: `cookie_consent_retention_days` (Privacidade e compliance, padrão 730) — a rotina de retenção/diária apaga os registros mais antigos.
- LGPD: o exportador de dados pessoais do WordPress e o "Baixar meus dados" do portal incluem os registros do usuário (por `user_id`); o apagador desvincula o usuário (remove `user_id`, hash do IP e user agent), mantendo o registro anônimo como prova.

Exemplo mínimo no banner:

```js
fetch(window.EBCR_CLIENT_AREA.consentEndpoint, {
  method: 'POST', credentials: 'same-origin',
  headers: Object.assign({ 'Content-Type': 'application/json' }, window.EBCR_CLIENT_AREA.consentNonce ? { 'X-WP-Nonce': window.EBCR_CLIENT_AREA.consentNonce } : {}),
  body: JSON.stringify({ consent_id: id, categories: cats, action: 'custom', version: 1, path: location.pathname })
});
```

## Páginas legais

Em **Privacidade e compliance**, cada política tem título, página, versão e texto. Sem página escolhida (`policies[<chave>][page_id]` = 0), o plugin usa a primeira página **publicada** entre os slugs candidatos, sempre com `get_permalink()`:

| Política | Slugs procurados |
| --- | --- |
| `privacidade` | `aviso-de-privacidade`, `politica-de-privacidade`, depois a página de Configurações → Privacidade do WordPress |
| `termos` | `termos-de-uso` |
| `scr` | `autorizacao-consulta-scr` |
| `veracidade` | `declaracao-de-veracidade` |
| `socioambiental` | `politica-de-esg`, `politica-socioambiental` |
| `anticorrupcao` | `politica-de-compliance-anticorrupcao`, `politica-de-compliance` |
| `marketing` | `comunicacoes-de-marketing` |

Links complementares na área Privacidade do cliente (página escolhida ou slug): `legal_page_titular` (`portal-do-titular`), `legal_page_cookies` (`politica-de-cookies`), `legal_page_comercializacao` (`politica-de-comercializacao`), `legal_page_integridade` (`canal-de-integridade`). Filtro `ebcr_policy_page_slugs` (`array $slugs, string $key`).

## Configuração e operação por IA (Easy MCP AI / Abilities API)

O plugin registra 18 *abilities* na Abilities API do WordPress (6.9+), categoria `ebcr`, e as habilita sozinho na opção do plugin **Easy MCP AI** na ativação/atualização (sem remover outras). No agente elas aparecem como `wp_ability_ebcr_*`. Cada uma exige a mesma capacidade da tela equivalente e registra auditoria com origem `mcp`; dados sensíveis chegam mascarados e os arquivos nunca saem por esse caminho.

| Ferramenta | Função | Exige |
| --- | --- | --- |
| `wp_ability_ebcr_get_settings` | Lê configurações (todas, por aba ou por chave) com rótulo, ajuda e tipo; sem filtros inclui a identidade visual efetiva (tokens, variáveis CSS, contraste) e as URLs do botão "Área do Cliente" | `ebcr_manage_settings` |
| `wp_ability_ebcr_update_settings` | Altera configurações (mesma sanitização/limites/avisos da tela); `brand_preset` expande a predefinição | `ebcr_manage_settings` |
| `wp_ability_ebcr_reset_settings` | Restaura os padrões de uma aba | `ebcr_manage_settings` |
| `wp_ability_ebcr_get_status` | Estado do plugin: ambiente, pasta privada, criptografia, fila de e-mails, contagens, pendências de publicação, estado do MCP | `ebcr_manage_settings` |
| `wp_ability_ebcr_run_tool` | `test_protection`, `test_email`, `process_mail`, `retry_mail`, `run_daily`, `run_retention`, `enable_mcp`, `send_crm_reminders`, `apply_site_icon`, `apply_brand_preset` (+ `preset`), `flush_rewrite` | `ebcr_manage_settings` |
| `wp_ability_ebcr_list_team` | Lista analistas, gestores e administradores | `ebcr_change_final_status` |
| `wp_ability_ebcr_set_team_member` | Cria/atribui analista ou gestor por e-mail; `remover` tira da equipe | `promote_users` |
| `wp_ability_ebcr_list_submissions` | Lista solicitações com filtros (status, busca, responsável, paginação) | `ebcr_view_submissions` |
| `wp_ability_ebcr_get_submission` | Detalhe (dados mascarados, documentos, pendências, mensagens, histórico, exigências de imóveis/garantias) | `ebcr_view_submissions` |
| `wp_ability_ebcr_change_status` | Muda status respeitando transições e papéis (aprovar/reprovar só gestor) | `ebcr_edit_submissions` |
| `wp_ability_ebcr_assign_submission` | Atribui responsável (ID ou e-mail) | `ebcr_edit_submissions` |
| `wp_ability_ebcr_request_document` | Abre pendência documental e notifica o cliente | `ebcr_edit_submissions` |
| `wp_ability_ebcr_send_message` | Mensagem interna ou ao cliente | `ebcr_edit_submissions` |
| `wp_ability_ebcr_review_document` | Aceita/recusa documento enviado | `ebcr_edit_submissions` |
| `wp_ability_ebcr_get_report` | Totais e quebras por carteira, status, mês, analista e tempo por etapa, com filtros | `ebcr_view_dashboard` |
| `wp_ability_ebcr_list_contacts` | Fichas do CRM com estágio, responsável, próxima ação, solicitações abertas | `ebcr_crm` |
| `wp_ability_ebcr_update_contact` | Estágio, responsável, tags, origem, próxima ação, notas, telefones | `ebcr_crm` |
| `wp_ability_ebcr_add_activity` | Ligação, reunião, visita, e-mail, nota ou tarefa (com prazo e responsável) | `ebcr_crm` |

Exemplos: “Mostre as configurações da aba E-mails”, “Defina os e-mails administrativos como contato@dominio e ligue o aviso ao analista”, “Qual o estado do plugin e o que falta para publicar?”, “Cadastre maria@exemplo.com como analista”, “Atribua a EB-2026-000012 ao João e peça a matrícula atualizada com 30 dias de prazo”. Se as ferramentas não aparecerem no agente: Easy MCP AI → Abilities → marcar o grupo *EB Crédito Rural* (ou **Configurações → Ferramentas → Habilitar**) e reconectar o agente.

## Operação: painel, CRM, relatórios e dossiê

- **Painel** (Crédito Rural → Painel): cartões (novas, em análise, com pendência, aprovadas no mês, volumes, tempo médio por etapa), gráficos (funil por status, por mês, por UF, por atividade, por tipo de garantia) e listas (tarefas do CRM, pendências paradas, certidões vencendo). Analistas com "vê só as atribuídas" veem apenas as suas.
- **Relatórios** (Crédito Rural → Relatórios): filtros por período, carteira/fundo, status e analista; tabelas por carteira, status, mês, analista e tempo por etapa; exportação CSV (`ebcr_export`, auditada). Carteiras são configuradas em Geral → Fundos/carteiras e atribuídas na tela da solicitação.
- **CRM** (Crédito Rural → CRM): ficha por cliente (contato, origem, tags, estágio, responsável, próxima ação, notas, histórico de solicitações), atividades (ligação, reunião, visita, e-mail, nota) e tarefas com lembrete diário por e-mail ao responsável; lista com filtros, ação em massa e CSV; quadro Kanban com arrastar e soltar. Estágios e origens em Configurações → CRM.
- **Dossiê em PDF** (tela da solicitação → "Gerar dossiê em PDF para o comitê"): capa com indicadores, tomador, imóveis, produção, financeiro, garantias, documentos, conferência, histórico, notas internas e aceites. Gerado em PHP puro, sem serviços externos.
- **Painel da equipe no site**: analistas e gestores operam tudo dentro da página do portal (visão geral, solicitações com todas as ações, CRM, relatórios). Em Configurações → Geral → "Painel da equipe" = "Só no site", a equipe não entra no wp-admin (redirecionada ao portal, sem barra de administração); administradores nunca são bloqueados e as Configurações continuam no wp-admin.

## Segurança da equipe (2FA) e integrações

- **2FA** (Configurações → Segurança): opcional ou obrigatório para analistas, gestores e administradores. Cada um ativa no próprio perfil (Usuários → Perfil): aplicativo autenticador (QR/segredo) ou código por e-mail; 10 códigos de backup; "confiar neste dispositivo por 30 dias"; verificação em `/?ebcr_2fa=1` (front-end). Perdeu o celular e os códigos? Um administrador desativa em Usuários → editar; em último caso `define( 'EBCR_DISABLE_2FA', true );` no wp-config.php. XML-RPC é recusado para contas com 2FA.
- **Assinatura eletrônica** (Configurações → Integrações): o cliente assina a autorização de consulta ao SCR (e outros tipos configurados) na própria área: lê o texto, confirma o nome, recebe um código por e-mail e assina; o PDF assinado (nome, CPF, e-mail, IP, data/hora, hashes SHA-256) entra na lista de documentos e o admin vê o selo e as evidências. Assinatura eletrônica simples (Lei 14.063/2020).
- **CEP e CNPJ**: preenchimento automático no formulário via ViaCEP/BrasilAPI (servidor, com cache), sem chaves.
- **Turnstile**: Configurações → Segurança → provedor "Cloudflare Turnstile" com as chaves em Integrações; sem chaves, volta ao captcha matemático.
- **WhatsApp**: notificações pela Cloud API da Meta (token, Phone Number ID e template de utilidade aprovado); espelha os avisos de e-mail ao cliente e/ou à equipe; falhas ficam no log de auditoria.
- **Simulador**: `[ebcr_simulador]` na página de captação (ver Shortcodes).

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
src/Domain             Status (fluxo), Consent (políticas versionadas e páginas legais), CookieConsent (registro de consentimento de cookies), Protocol
src/Forms              Steps (7 etapas; 2 e 5 configuráveis), Validators (CPF, CNPJ, CAR, CEP…), DocumentMatrix, Wizard, SubmissionRules (regras de envio e de bens/garantias), SubmissionService
src/Security           Authorization, MathCaptcha, Honeypot, RateLimiter, LoginGuard, Nonces, Crypto (sodium), AuditLog, PrivacyIntegration, Retention
src/Files              FileGuard, Storage, UploadHandler, DownloadController, Exif, Antivirus
src/Mail               Events (templates), Mailer, Queue (Action Scheduler ou WP-Cron, 3 tentativas), Notifier
src/Frontend           Auth, Portal, FormRouter (formulários enviam para a própria página do portal), Fields, Simulator, ClientArea (botão/bloco/barra "Área do Cliente" e /area-do-cliente/), Team (painel da equipe no site)
src/Admin              Menu, Dashboard (gráficos), SubmissionsList, SubmissionView, Actions, Settings (15 abas), Branding (identidade do painel/login e da área do cliente), Help, AuditLogView, Crm/CrmList, Reports
src/Support            Options, Helpers, Color (cores CSS e contraste WCAG), View, Ip, Uuid
src/Reports            Metrics (funil, mês, UF, atividade, garantia, tempo por etapa, carteira, analista), Dossier (PDF do comitê)
src/Pdf                Writer (gerador de PDF em PHP puro)
src/Crm                Service (ficha, estágios, atividades, tarefas, lembretes, CSV)
src/Esign              assinatura eletrônica simples (código por e-mail, PDF com evidências)
src/Integrations       Lookup (CEP/CNPJ), WhatsApp (Cloud API)
src/Security           … + Turnstile (captcha), TwoFactor/Totp (2FA da equipe)
src/Abilities          abilities do WordPress expostas ao Easy MCP AI (18 ferramentas)
src/Rest               ebcr/v1 (etapas, uploads, envio, mensagens, status, atribuição, pedidos, revisão, lookup/cep, lookup/cnpj, crm/contacts/{id}/stage, esign/…, cookie-consent)
src/Cron               lembretes, certidões a vencer, retenção, limpezas, lembretes de tarefas do CRM
assets/vendor          Chart.js 4.4.4 (MIT) e qrcode-generator 1.4.4 (MIT), empacotados (sem CDN)
templates/             portal, wizard, admin e e-mails (sobrescrevíveis pelo tema em /eb-credito-rural/)
assets/                CSS/JS sem CDN
tests/                 PHPUnit (autorização, segurança, validadores, wizard, bens/garantias, identidade visual, botão Área do Cliente, consentimento de cookies, abilities, relatórios, CRM, dossiê, 2FA, assinatura, integrações, painel da equipe)
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
- [ ] Identidade visual revisada (predefinição/cores/logotipo) e sem avisos de contraste
- [ ] Modo de bens e garantias definido (obrigatório/opcional/desativado) e matriz de documentos conferida
- [ ] Botão "Área do Cliente" no cabeçalho (shortcode, bloco, menu, barra fixa ou `data-ebcr-client-area`) apontando para o portal
- [ ] Páginas legais publicadas (ou escolhidas em Privacidade e compliance) e links conferidos
- [ ] Banner de cookies registrando as decisões em `window.EBCR_CLIENT_AREA.consentEndpoint` (conferir em Consentimentos de cookies)
- [ ] Cron do sistema (se `DISABLE_WP_CRON`)
- [ ] Backup do banco e da pasta privada

## Versões

- **1.3.0** — Bens e garantias configuráveis (obrigatório/opcional/desativado, finalidades que exigem garantia, filtro `ebcr_guarantees_required`); identidade visual da área do cliente (predefinições Évellyn Brandão, BS Agro Capital e Neutro, herdar do tema, fontes, contraste WCAG) aplicada a portal, acesso, formulário, simulador, assinatura, painel da equipe, 2FA, e-mails e login; botão "Área do Cliente" (shortcode, bloco, barra fixa, menus, `data-ebcr-client-area`, `/area-do-cliente/`); páginas legais por slugs candidatos e links complementares; registro de consentimento de cookies (`POST ebcr/v1/cookie-consent`, tela com CSV, retenção, exportador/apagador; tabela nova, `EBCR_DB_VERSION` 1.3.0); nome da operação padrão passa a usar o nome do site e o encarregado (DPO) padrão fica vazio (instalações existentes mantêm os valores anteriores); correção do erro fatal ao salvar a matriz de documentos.
- **1.2.2** — Identidade visual do painel (Geral → logotipo e ícone da marca): tela de login com a marca, logotipo no topo do menu lateral e na barra do WordPress, ícone da marca no menu "Crédito Rural" e como ícone do site no wp-admin/login (ferramenta `apply_site_icon`).
- **1.2.1** — Páginas padrão para todas as políticas (Termos de Uso, Autorização SCR/Bacen, Declaração de veracidade, Comunicações de marketing) vinculadas por slug; todas as políticas aceitas também no cadastro (momento do aceite configurável por política em Privacidade e compliance); blocos das etapas do formulário com a mesma altura.
- **1.2.0** — Fases 2 e 3: painel com gráficos e tempo por etapa; relatórios por carteira/fundo com CSV; CRM completo (ficha, atividades, tarefas com lembretes, Kanban, CSV); dossiê em PDF (gerador próprio); 2FA da equipe (TOTP/e-mail, backup, dispositivo confiável); assinatura eletrônica simples; consultas de CEP/CNPJ; captcha Turnstile; notificações por WhatsApp; simulador de crédito; painel de operações da equipe no site (modo "só no site"); 4 abilities novas (relatório e CRM).
- **1.1.1** — Correções de bloqueio no fluxo do cliente: os formulários do portal passam a enviar para a própria página do portal (não mais para `wp-admin/admin-post.php`, que firewalls de hospedagem bloqueiam para visitantes/clientes) e os grupos de opções (rádios/caixas) fechavam o formulário antes da hora, impedindo "Salvar e continuar" no navegador.
- **1.1.0** — Abilities API / Easy MCP AI (14 ferramentas `wp_ability_ebcr_*`, auto-habilitadas no conector), limites de segurança aplicados na sanitização dos campos numéricos, e-mails padrão `contato@dominio`, detecção automática da página do portal, bloco de estado do MCP em Ferramentas e Ajuda.
- **1.0.0** — Fase 1 completa (segurança de arquivos, autorização, wizard de 7 etapas, matriz de documentos, fila de e-mails, CRM básico, LGPD, auditoria).

## Fora desta fase

Certificado digital ICP-Brasil na assinatura, integrações automáticas com SCR/Serasa/SICAR (as consultas continuam manuais; o plugin registra o resultado na conferência) e app nativo — ver `SPEC.md` §1 (fora de escopo).
