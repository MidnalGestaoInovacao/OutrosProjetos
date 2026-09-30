# EB Crédito Rural — plugin WordPress

Captação de solicitações de crédito rural (criado para o site de Évellyn Brandão e usado também no site da BS Agro Capital): cadastro do produtor com confirmação de e-mail, formulário em sete etapas com validação no servidor, documentos armazenados fora do alcance público com download autenticado, notificações por e-mail, área do cliente com a identidade visual de cada site, botão "Área do Cliente" e painel da equipe com fluxo de status, pedidos de documento, revisão, mensagens, checklist de conferência, auditoria e configurações explicadas campo a campo.

Esta é a **Fase 1 (MVP seguro)** do `SPEC.md`. Slug `eb-credito-rural`, prefixo `ebcr_`, namespace `EBCR\`.

## Novidades da 1.3.1

- **Bens e garantias opcionais, com complemento depois do envio.** No modo *Opcional* (Configurações → Bens e garantias), as etapas de imóveis (2) e garantias (5) e o resumo antes do envio (quando nada foi informado) mostram um quadro de orientação (`role="note"`, cores da identidade visual) dizendo que não é obrigatório, mas é pertinente preencher o mais completo possível — e que dá para completar depois. Textos configuráveis: `assets_optional_notice` e `guarantees_optional_notice`.
- **Área do Cliente → solicitação → quadro "Bens e garantias"**: lista os itens informados (com a data dos incluídos/alterados depois do envio) e os botões **Completar agora** (nenhum item) ou **Adicionar ou editar**. O formulário é o mesmo das etapas 2 e 5, com as mesmas regras e sanitização no servidor, enquanto o status estiver em `complement_statuses`. Cada complemento grava uma entrada no histórico ("Atualização da solicitação"), o log de auditoria "Cliente complementou bens/garantias", avisa os e-mails administrativos e o analista atribuído (evento `complement_added`) e abre pedidos para os documentos que os novos itens passam a exigir (matrícula, CCIR/ITR, CAR, contrato de arrendamento, laudo…), que o cliente envia logo em seguida na mesma tela. As telas da equipe (wp-admin e painel no site) marcam os itens incluídos/alterados e quando.
- **Lembretes**: aviso no painel do cliente ("Sua solicitação … está sem bens e garantias informados — completar agora") e um único e-mail (`complement_reminder`) `complement_reminder_days` dias depois do envio (padrão 3; 0 desliga).
- **Rascunho**: o painel do cliente mostra o aviso "Você tem uma solicitação em preenchimento" com a etapa em que parou e o botão **Continuar preenchimento**.
- **Pasta privada criada ao salvar**: se `storage_path` não existir e a pasta mãe for gravável, o plugin cria a pasta (0750) com `index.php`, `.htaccess` (negar tudo) e `web.config`; avisa se o caminho estiver dentro da instalação do WordPress. `get-status` → `storage.created`.
- **Envio autenticado por SMTP** (Configurações → E-mails → *Envio (SMTP)*): servidor, porta, SSL/TLS, usuário e senha da caixa do domínio (senha de preferência na constante `EBCR_SMTP_PASSWORD` do wp-config.php; se salva na tela, fica cifrada e nunca é exibida), remetente próprio, aplicação a todos os e-mails do site (some o `wordpress@…`), remetente do envelope (Return-Path) sempre igual ao remetente, registro das falhas do `wp_mail` e o botão/ferramenta **Testar SMTP** com a conversa SMTP (credenciais mascaradas).
- Banco `EBCR_DB_VERSION` 1.3.2 (colunas novas, criadas automaticamente na atualização): `complemented_at` e `complement_reminded_at` nas solicitações, `updated_at` em imóveis e garantias, `ref_key` e `origin` nos pedidos de documento.
- Garantias passam a manter o ID (e a data de inclusão e os campos da equipe: valor avaliado, LTV, formalização) quando o cliente edita; o vínculo da garantia real só aceita imóveis da própria solicitação.

## Novidades da 1.3.0

- **Bens e garantias configuráveis** (Configurações → *Bens e garantias*): imóveis rurais (etapa 2) e garantias (etapa 5) podem ser **obrigatórios** (padrão, igual às versões anteriores), **opcionais** (o cliente pode declarar "não tenho garantia/imóvel a informar") ou **desativados** (a etapa some, as etapas seguintes são renumeradas e os documentos ligados a ela não são pedidos). Finalidades marcadas continuam exigindo garantia no modo opcional. Validação no navegador e no servidor; filtro `ebcr_guarantees_required`.
- **Identidade visual da área do cliente** (Configurações → *Identidade visual*): nome e logotipo da marca, cores, arredondamentos, fontes (com folhas de estilo externas), "Herdar do tema" e predefinições **Évellyn Brandão** (visual original), **BS Agro Capital** e **Neutro**; verificação de contraste WCAG AA. Vale para portal, login/cadastro, formulário, simulador, assinatura eletrônica, painel da equipe no site, verificação em duas etapas, botão "Área do Cliente", e-mails e tela de login do WordPress (quando a paleta não é a original).
- **Botão "Área do Cliente"**: shortcode `[ebcr_client_area_button]`, bloco `ebcr/client-area-button`, botão fixo no topo, item em menu clássico ou no bloco Navegação, atributo `data-ebcr-client-area` em qualquer link, `window.EBCR_CLIENT_AREA` e endereço amigável `/area-do-cliente/` (com `?ebcr_client_area=1` para links permanentes simples).
- **Páginas legais por slug candidato**: sem página escolhida, cada política procura a primeira página publicada entre slugs padrão (privacidade: `aviso-de-privacidade`, `politica-de-privacidade` e, por fim, a página de privacidade do WordPress); novas páginas complementares (portal do titular, cookies, comercialização, canal de integridade) aparecem na área Privacidade do cliente. Título de cada política editável.
- **Registro de consentimento de cookies** (prova do consentimento, LGPD art. 8º, § 2º): rota pública `POST ebcr/v1/cookie-consent` para o banner de cookies do site, tela *Crédito Rural → Consentimentos de cookies* com filtros e CSV, retenção configurável e integração com o exportador/apagador de dados pessoais. Nova tabela `{prefix}ebcr_cookie_consents` (`EBCR_DB_VERSION` 1.3.0, criada automaticamente na atualização).
- **Canais de atendimento** (contato, Portal do Titular/LGPD e Canal de Integridade): rota pública `POST ebcr/v1/channel-message` para os formulários do site, com protocolo (`CT-`, `LGPD-`, `OUV-`), relato anônimo no Canal de Integridade, e-mail para a caixa de cada canal (Reply-To do remetente) e recibo com o protocolo; tela *Crédito Rural → Mensagens dos canais* com filtros, detalhe, status e CSV; retenção e exportador/apagador de dados pessoais. Nova tabela `{prefix}ebcr_channel_messages` (`EBCR_DB_VERSION` 1.3.1) e capacidade `ebcr_manage_channels` (gestores e administradores).
- **Sem o script de emojis do WordPress** no site público (Configurações → Geral → *Remover o script de emojis do WordPress*, ligado por padrão).
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

### Modo opcional: orientação e complemento (1.3.1)

| Configuração | Padrão |
| --- | --- |
| Orientação sobre imóveis/bens (`assets_optional_notice`) | "Informar imóveis rurais e outros bens não é obrigatório para enviar a solicitação, mas é muito pertinente: …" |
| Orientação sobre garantias (`guarantees_optional_notice`) | "Informar bens e garantias não é obrigatório para enviar a solicitação, mas é muito pertinente: quanto mais completas as informações, mais ágil a análise e melhores as alternativas de crédito que conseguimos estruturar. Se não tiver os dados agora, envie a solicitação e complete depois pela Área do Cliente." |
| Status em que o cliente pode completar (`complement_statuses`) | `enviada`, `pre_analise`, `pendencia_documental`, `analise_credito`, `comite`, `formalizacao` (todos os em andamento, exceto `aprovada`; rascunho continua pelo formulário) |
| Lembrete para completar (`complement_reminder_days`) | `3` (0 desliga; 0–60) |

- Onde aparece a orientação: etapa 2 e etapa 5 (modo opcional), resumo da etapa 7 quando nenhum imóvel/garantia foi informado (com botões "Informar … agora"), quadro "Bens e garantias" da solicitação e e-mail de lembrete.
- Complemento (`?ebcr_view=complementar&id=<uuid>&parte=imoveis|garantias`, ação `ebcr_complement` com nonce próprio): só o dono, só partes em modo opcional, só nos status configurados. Valida com `Steps::validate()` (mesmas regras e mensagens), exige ao menos um item, não permite remover itens já informados (a equipe pode tê-los avaliado; para remover, o cliente fala com a equipe), mantém IDs e datas e recusa envio sem alteração. Sem JavaScript funciona igual (o botão "+ Adicionar" recarrega com uma linha a mais, sem gravar).
- Ao salvar: `complemented_at` na solicitação; entrada no histórico sem mudar o status (título "Atualização da solicitação"; comentário interno com o resumo e os documentos pedidos); auditoria `complement_added`; e-mail `complement_added` para os e-mails administrativos + analista atribuído; pedidos de documento (origem `complemento`, `ref_key` do imóvel) para os slots novos de nível **Obrigatório** ou **Recomendado** que ainda não foram enviados nem pedidos. Esses pedidos não contam para o cancelamento automático por pendência.
- Lembrete (cron diário): uma vez por solicitação (`complement_reminded_at`), para solicitações enviadas há mais de N dias (e no máximo N + 30) sem imóveis e/ou garantias numa parte em modo opcional, enquanto o status estiver em `complement_statuses`.
- Abilities: `get-submission` traz `complement` (`complemented_at`, `complement_reminded_at`, `parts`, `status_allows`, `missing`) e `properties.items` / `guarantees.items` com `created_at`, `updated_at`, `added_after_submission` e `changed_after_submission`; `get-status` → `modules.complement`.
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

## Canais de atendimento (contato, titular e integridade)

Os formulários públicos do site (contato, Portal do Titular/DPO e Canal de Integridade/ouvidoria) enviam para `POST {rest_url}ebcr/v1/channel-message` — o endereço exato fica em `window.EBCR_CLIENT_AREA.channelEndpoint` (montado com `rest_url()`; com links permanentes simples: `/?rest_route=/ebcr/v1/channel-message`). Rota pública, limitada a **10 envios por hora por IP**. Aceita JSON ou formulário (`application/x-www-form-urlencoded`). Com usuário conectado, envie `X-WP-Nonce` (`window.EBCR_CLIENT_AREA.consentNonce`) para vincular a mensagem à conta.

```json
{
  "channel": "contato",
  "protocol": "CT-20260929-7K2Q",
  "anonymous": false,
  "page": "/contato/",
  "_honey": "",
  "fields": {
    "nome": "Maria Souza", "email": "maria@exemplo.com.br", "telefone": "(62) 99999-0000",
    "assunto": "Custeio de soja", "mensagem": "Gostaria de informações.", "consentimento": true
  }
}
```

- `channel`: `contato` | `dpo` | `ouvidoria`.
- `protocol`: opcional. Formato `^(CT|LGPD|OUV)-\d{8}-[A-Z0-9]{4}$` com o prefixo do canal (`CT` contato, `LGPD` titular, `OUV` integridade). Se ausente, inválido, de outro canal ou já usado, o servidor gera outro (`PREFIXO-AAAAMMDD-XXXX`, data no fuso do site). A resposta traz **sempre** o protocolo gravado — mostre esse ao visitante.
- `anonymous`: só vale para `ouvidoria`. Quando verdadeiro, `nome`, `email` e `telefone` são descartados e **não** se grava hash de IP, user agent nem usuário; também não há Reply-To nem recibo.
- `page`: caminho da página (sem domínio, query ou fragmento; até 200 caracteres).
- `_honey`: campo invisível (no topo ou dentro de `fields`); precisa chegar vazio.
- `fields`: objeto (não lista) com até 20 chaves; só entram `nome, email, telefone, empresa, assunto, mensagem, relacao, direito, descricao, tipo, quando, onde, envolvidos, relato, evidencias, consentimento, declaracao_titular, boa_fe` (outras são ignoradas). Valores em texto, sanitizados, até 5000 caracteres cada; `email` é validado quando enviado. Caixas (`consentimento`, `declaracao_titular`, `boa_fe`) aceitam `true`, `1`, `"on"`, `"sim"`.
- Obrigatórios: **contato** `nome, email, assunto, mensagem, consentimento`; **dpo** `nome, email, relacao, direito, descricao, declaracao_titular, consentimento`; **ouvidoria** `tipo, relato, boa_fe`.
- Respostas: `200 {"ok": true, "protocol": "CT-20260929-7K2Q"}`; `400 {"ok": false, "message": "…", "fields": ["email"]}` (mensagem genérica em português; `fields` lista os campos com problema, quando houver); `429 {"ok": false, "message": "…"}` acima do limite; `500 {"ok": false, "message": "…"}` só se o banco recusar a gravação. Nada do que foi gravado é devolvido.
- Gravado em `{prefix}ebcr_channel_messages`: canal, protocolo, anônimo, campos (JSON), e-mail (minúsculo, para o exportador/apagador), página, **hash** HMAC-SHA256 do IP com `wp_salt('auth')` (nunca o IP), user agent (180 caracteres), usuário, status (`novo`, `em_andamento`, `concluido`) e datas (UTC).
- E-mail à equipe (fila de e-mails): assunto `[<Canal>] <protocolo> — <nome do site>`, corpo com os campos (escapados) e o protocolo, **Reply-To** do remetente quando há e-mail válido e a mensagem não é anônima; nunca inclui o hash do IP. Destinatário: `channel_email_contato`, `channel_email_dpo`, `channel_email_ouvidoria` (vazio = e-mail do administrador do WordPress). Recibo curto com o protocolo ao remetente quando `channel_send_receipt` está ligado (padrão).
- Consulta: **Crédito Rural → Mensagens dos canais** (capacidade `ebcr_manage_channels`: gestores e administradores; pode ser dada ao encarregado/DPO), com filtros por canal, status e período, detalhe da mensagem, troca de status e **Exportar CSV** (também exige `ebcr_export`; células neutralizadas contra fórmulas; sem hash de IP nem user agent). Visualização, troca de status e exportação ficam no log de auditoria.
- Retenção: `channel_message_retention_days` (Canais de atendimento, padrão 1825 = 5 anos) — a rotina de retenção apaga as mensagens mais antigas.
- LGPD: o exportador de dados pessoais do WordPress inclui as mensagens identificadas enviadas com o e-mail (mesmo sem conta; anônimas nunca entram) e o "Baixar meus dados" do portal as inclui para o e-mail da conta; o apagador mantém o registro do atendimento, mas remove nome, e-mail, telefone, empresa, hash do IP, user agent e usuário.

Exemplo mínimo:

```js
fetch(window.EBCR_CLIENT_AREA.channelEndpoint, {
  method: 'POST', credentials: 'same-origin',
  headers: Object.assign({ 'Content-Type': 'application/json' }, window.EBCR_CLIENT_AREA.consentNonce ? { 'X-WP-Nonce': window.EBCR_CLIENT_AREA.consentNonce } : {}),
  body: JSON.stringify({ channel: 'ouvidoria', anonymous: true, page: location.pathname, _honey: form._honey.value, fields: { tipo: 'fraude', relato: texto, boa_fe: true } })
}).then(r => r.json()).then(r => { if (r.ok) mostrarProtocolo(r.protocol); else mostrarErro(r.message); });
```

## Emojis do WordPress

`disable_wp_emoji` (Configurações → Geral, ligado por padrão) remove do site público o script de detecção de emojis (`print_emoji_detection_script`), os estilos (`wp_enqueue_emoji_styles`/`print_emoji_styles`), a conversão de emojis em imagens nos feeds e o `dns-prefetch` para o CDN de emojis. O painel não é afetado. Desligue se o tema depender das imagens de emoji do WordPress.

## Envio de e-mails (SMTP)

Configurações → **E-mails** → *Envio (SMTP)*. Resolve o caso "a fila diz *enviado*, mas nada chega": o PHP `mail()` do servidor entrega sem autenticação (e o WordPress usa `wordpress@dominio`, uma caixa inexistente).

| Configuração | Padrão | Observação |
| --- | --- | --- |
| `smtp_enabled` | `false` | Liga o SMTP (exige `smtp_host`). |
| `smtp_host` | `''` | Ex.: `mail.bsagro.agr.br`. |
| `smtp_port` | `465` | 465 (SSL) ou 587 (TLS). |
| `smtp_secure` | `ssl` | `ssl` \| `tls` \| `none`. |
| `smtp_auth` | `true` | Usuário e senha. |
| `smtp_username` | `''` | Normalmente a própria caixa (ex.: `contato@bsagro.agr.br`). |
| `smtp_password` | `''` | Somente escrita. A constante `EBCR_SMTP_PASSWORD` no wp-config.php tem prioridade e é o recomendado. Salva na tela/MCP, fica cifrada (libsodium) com `EBCR_ENCRYPTION_KEY`; sem essa chave, com uma chave derivada de `wp_salt('secure_auth')` (protege contra vazamento só do banco, não contra quem lê o wp-config.php — a tela avisa). Nunca é exibida, exportada, registrada na auditoria nem devolvida pelas abilities. `smtp_password_clear=true` apaga. |
| `smtp_from_email` / `smtp_from_name` | `''` | Vazio = usa `from_email` / `from_name`. |
| `smtp_apply_all` | `true` | Todos os `wp_mail()` do site (e filtros `wp_mail_from`/`wp_mail_from_name`); desligado = só as mensagens do plugin. |
| `smtp_verify_peer` | `true` | Desligar aceita certificado autoassinado (aviso). |
| `smtp_timeout` | `15` | 5–120 segundos. |

- Hook `phpmailer_init`: `isSMTP`, `Host`, `Port`, `SMTPSecure` (e `SMTPAutoTLS` desligado em "nenhuma"), `SMTPAuth`, `Username`, `Password`, `Timeout`, `SMTPOptions` (verificação do certificado), `From`/`FromName` e **sempre** `Sender` (Return-Path) = remetente. Com o SMTP desligado, as mensagens do plugin ainda recebem `Sender` = `from_email` (alinhamento SPF no `mail()`).
- Falhas: `wp_mail_failed` é registrado (últimas 20: data, mensagem sem credenciais, transporte e só o **domínio** dos destinatários) — `get-status` → `mail_failures.recent` (e `mail_failures.queue`, os itens da fila que falharam; a fila já tenta 3 vezes e guarda o erro do PHPMailer em cada item).
- Teste: botão **Testar SMTP (com diagnóstico)** na aba E-mails ou `run-tool` `test_smtp` com `to`. Retorno: `{sent, error, transport (smtp|mail|none), host, port, secure, from, sender, to, transcript}` — `transcript` é a conversa SMTP (SMTPDebug 2) com as linhas de autenticação e a senha mascaradas, o corpo da mensagem omitido, até ~4000 caracteres. Funciona com o SMTP ligado ou desligado (informa o transporte usado).
- `get-status` → `smtp` = `{enabled, configured, host, port, secure, auth, username, password_source (constant|option|none), password_storage, password_readable, from_email, from_name, apply_all, verify_peer, timeout}`.
- Para a BS Agro: `smtp_enabled=true`, `smtp_host` = servidor de saída do cPanel, `smtp_port=465`, `smtp_secure=ssl`, `smtp_username=contato@bsagro.agr.br`, `define( 'EBCR_SMTP_PASSWORD', '…' );` no wp-config.php e `smtp_from_email=contato@bsagro.agr.br`; depois `run-tool test_smtp`.

## Pasta privada (storage_path)

Ao salvar `storage_path` (tela Segurança ou `update-settings`), se a pasta não existir e a pasta mãe existente mais próxima for gravável pelo PHP, o plugin a cria (`wp_mkdir_p`, permissão 0750) e grava `index.php`, `.htaccess` (negar tudo) e `web.config`. Caminhos relativos ou com `..` são recusados; se não der para criar, o aviso de sempre continua ("não existe ou não é gravável" — o plugin segue usando a pasta dentro de uploads); caminhos dentro da instalação do WordPress geram aviso. Ex. BS Agro: `/backup/bsagroagr/ebcr-private` (a pasta da conta fica acima de `public_html`). `get-status` → `storage.created` (`path`, `at`, `mode`).

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
| `wp_ability_ebcr_get_status` | Estado do plugin: ambiente, pasta privada, criptografia, fila de e-mails, contagens, pendências de publicação, estado do MCP; em `modules`, entre outros, `cookie_consent` e `channels` (endpoint, destinatários efetivos, recibo, retenção, total e novas) e `disable_wp_emoji` | `ebcr_manage_settings` |
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
src/Domain             Status (fluxo), Consent (políticas versionadas e páginas legais), CookieConsent (registro de consentimento de cookies), ChannelMessage (canais de contato, titular e integridade), Protocol
src/Forms              Steps (7 etapas; 2 e 5 configuráveis), Validators (CPF, CNPJ, CAR, CEP…), DocumentMatrix, Wizard, SubmissionRules (regras de envio e de bens/garantias), Complement (complemento de bens/garantias depois do envio e lembretes), SubmissionService
src/Security           Authorization, MathCaptcha, Honeypot, RateLimiter, LoginGuard, Nonces, Crypto (sodium), AuditLog, PrivacyIntegration, Retention
src/Files              FileGuard, Storage, UploadHandler, DownloadController, Exif, Antivirus
src/Mail               Events (templates), Mailer, Queue (Action Scheduler ou WP-Cron, 3 tentativas), Notifier, Smtp (envio autenticado, Return-Path, falhas, teste com transcrição)
src/Frontend           Auth, Portal, FormRouter (formulários enviam para a própria página do portal), Fields, Simulator, ClientArea (botão/bloco/barra "Área do Cliente" e /area-do-cliente/), Team (painel da equipe no site)
src/Admin              Menu, Dashboard (gráficos), SubmissionsList, SubmissionView, Actions, Settings (16 abas), ChannelMessagesView (mensagens dos canais), CookieConsentsView, Branding (identidade do painel/login e da área do cliente), Help, AuditLogView, Crm/CrmList, Reports
src/Support            Options, Helpers, Color (cores CSS e contraste WCAG), View, Ip, Uuid
src/Reports            Metrics (funil, mês, UF, atividade, garantia, tempo por etapa, carteira, analista), Dossier (PDF do comitê)
src/Pdf                Writer (gerador de PDF em PHP puro)
src/Crm                Service (ficha, estágios, atividades, tarefas, lembretes, CSV)
src/Esign              assinatura eletrônica simples (código por e-mail, PDF com evidências)
src/Integrations       Lookup (CEP/CNPJ), WhatsApp (Cloud API)
src/Security           … + Turnstile (captcha), TwoFactor/Totp (2FA da equipe)
src/Abilities          abilities do WordPress expostas ao Easy MCP AI (18 ferramentas)
src/Rest               ebcr/v1 (etapas, uploads, envio, mensagens, status, atribuição, pedidos, revisão, lookup/cep, lookup/cnpj, crm/contacts/{id}/stage, esign/…, cookie-consent, channel-message)
src/Cron               lembretes, certidões a vencer, retenção, limpezas, lembretes de tarefas do CRM
assets/vendor          Chart.js 4.4.4 (MIT) e qrcode-generator 1.4.4 (MIT), empacotados (sem CDN)
templates/             portal, wizard, admin e e-mails (sobrescrevíveis pelo tema em /eb-credito-rural/)
assets/                CSS/JS sem CDN
tests/                 PHPUnit (autorização, segurança, validadores, wizard, bens/garantias, identidade visual, botão Área do Cliente, consentimento de cookies, canais de atendimento, complemento de bens/garantias, SMTP, abilities, relatórios, CRM, dossiê, 2FA, assinatura, integrações, painel da equipe)
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
- [ ] SMTP configurado (E-mails → Envio (SMTP), senha em `EBCR_SMTP_PASSWORD`) e "Testar SMTP" aceito; e-mail de teste recebido na caixa de entrada
- [ ] Políticas revisadas pelo jurídico, versões definidas
- [ ] Matriz de documentos e limites revisados pela gestora do fundo
- [ ] Usuários da equipe com papéis corretos
- [ ] Identidade visual revisada (predefinição/cores/logotipo) e sem avisos de contraste
- [ ] Modo de bens e garantias definido (obrigatório/opcional/desativado) e matriz de documentos conferida
- [ ] No modo opcional: textos de orientação, status em que o cliente pode completar e dias do lembrete revisados (Bens e garantias)
- [ ] Botão "Área do Cliente" no cabeçalho (shortcode, bloco, menu, barra fixa ou `data-ebcr-client-area`) apontando para o portal
- [ ] Páginas legais publicadas (ou escolhidas em Privacidade e compliance) e links conferidos
- [ ] Banner de cookies registrando as decisões em `window.EBCR_CLIENT_AREA.consentEndpoint` (conferir em Consentimentos de cookies)
- [ ] Formulários de contato, titular e integridade enviando para `window.EBCR_CLIENT_AREA.channelEndpoint`; e-mails de cada canal definidos em Canais de atendimento; mensagem de teste recebida e visível em Mensagens dos canais
- [ ] Papel com acesso às mensagens dos canais (`ebcr_manage_channels`) revisado — em especial para o Canal de Integridade
- [ ] Cron do sistema (se `DISABLE_WP_CRON`)
- [ ] Backup do banco e da pasta privada

## Versões

- **1.3.1** — Bens e garantias opcionais com orientação configurável (`assets_optional_notice`, `guarantees_optional_notice`) nas etapas 2, 5 e 7; complemento de imóveis/garantias depois do envio pela Área do Cliente (`complement_statuses`), com histórico, auditoria, e-mail `complement_added` à equipe e pedidos automáticos dos documentos exigidos pelos novos itens; aviso no painel e lembrete único por e-mail (`complement_reminder_days`); chamada "Continuar preenchimento" para rascunhos; criação e proteção da pasta privada ao salvar `storage_path`; envio autenticado por SMTP (`smtp_*`, `EBCR_SMTP_PASSWORD`, Return-Path, falhas em `get-status`, `run-tool test_smtp`). Banco `EBCR_DB_VERSION` 1.3.2.
- **1.3.0** — Bens e garantias configuráveis (obrigatório/opcional/desativado, finalidades que exigem garantia, filtro `ebcr_guarantees_required`); identidade visual da área do cliente (predefinições Évellyn Brandão, BS Agro Capital e Neutro, herdar do tema, fontes, contraste WCAG) aplicada a portal, acesso, formulário, simulador, assinatura, painel da equipe, 2FA, e-mails e login; botão "Área do Cliente" (shortcode, bloco, barra fixa, menus, `data-ebcr-client-area`, `/area-do-cliente/`); páginas legais por slugs candidatos e links complementares; registro de consentimento de cookies (`POST ebcr/v1/cookie-consent`, tela com CSV, retenção, exportador/apagador; tabela nova, `EBCR_DB_VERSION` 1.3.0); canais de atendimento (`POST ebcr/v1/channel-message` com protocolo, relato anônimo, e-mail por canal com Reply-To e recibo, tela *Mensagens dos canais* com status e CSV, retenção, exportador/apagador por e-mail; tabela `ebcr_channel_messages`, `EBCR_DB_VERSION` 1.3.1, capacidade `ebcr_manage_channels`); script de emojis do WordPress removido do site público (`disable_wp_emoji`); log de auditoria passa a gravar IDs textuais (public_id/protocolo) corretamente; nome da operação padrão passa a usar o nome do site e o encarregado (DPO) padrão fica vazio (instalações existentes mantêm os valores anteriores); correção do erro fatal ao salvar a matriz de documentos.
- **1.2.2** — Identidade visual do painel (Geral → logotipo e ícone da marca): tela de login com a marca, logotipo no topo do menu lateral e na barra do WordPress, ícone da marca no menu "Crédito Rural" e como ícone do site no wp-admin/login (ferramenta `apply_site_icon`).
- **1.2.1** — Páginas padrão para todas as políticas (Termos de Uso, Autorização SCR/Bacen, Declaração de veracidade, Comunicações de marketing) vinculadas por slug; todas as políticas aceitas também no cadastro (momento do aceite configurável por política em Privacidade e compliance); blocos das etapas do formulário com a mesma altura.
- **1.2.0** — Fases 2 e 3: painel com gráficos e tempo por etapa; relatórios por carteira/fundo com CSV; CRM completo (ficha, atividades, tarefas com lembretes, Kanban, CSV); dossiê em PDF (gerador próprio); 2FA da equipe (TOTP/e-mail, backup, dispositivo confiável); assinatura eletrônica simples; consultas de CEP/CNPJ; captcha Turnstile; notificações por WhatsApp; simulador de crédito; painel de operações da equipe no site (modo "só no site"); 4 abilities novas (relatório e CRM).
- **1.1.1** — Correções de bloqueio no fluxo do cliente: os formulários do portal passam a enviar para a própria página do portal (não mais para `wp-admin/admin-post.php`, que firewalls de hospedagem bloqueiam para visitantes/clientes) e os grupos de opções (rádios/caixas) fechavam o formulário antes da hora, impedindo "Salvar e continuar" no navegador.
- **1.1.0** — Abilities API / Easy MCP AI (14 ferramentas `wp_ability_ebcr_*`, auto-habilitadas no conector), limites de segurança aplicados na sanitização dos campos numéricos, e-mails padrão `contato@dominio`, detecção automática da página do portal, bloco de estado do MCP em Ferramentas e Ajuda.
- **1.0.0** — Fase 1 completa (segurança de arquivos, autorização, wizard de 7 etapas, matriz de documentos, fila de e-mails, CRM básico, LGPD, auditoria).

## Fora desta fase

Certificado digital ICP-Brasil na assinatura, integrações automáticas com SCR/Serasa/SICAR (as consultas continuam manuais; o plugin registra o resultado na conferência) e app nativo — ver `SPEC.md` §1 (fora de escopo).
