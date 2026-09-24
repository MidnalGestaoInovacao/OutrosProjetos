# Site Télix Comunicação e Relacionamento (WordPress)

Novo site institucional da **Télix** (Telix Comunicações Ltda., CNPJ 16.881.685/0001-62), publicado em
`https://telix.ebaem.com.br` (WordPress 7.1 + tema de blocos **Twenty Twenty-Five**), construído a partir do
conteúdo do site antigo `www.telixcom.com.br` — ampliado, revisado e com base em dados públicos verificados.

## Arquitetura

| Camada | Onde fica no WordPress | Fonte neste repositório |
|---|---|---|
| Design system (CSS) | Aparência → Editor → Estilos → CSS adicional (Global Styles) | `src/styles/global.css` |
| Cabeçalho + megamenu + sprite de ícones | Bloco sincronizado **"Télix — Cabeçalho e megamenu"** | gerado por `tools/build.py` (`NAV`, `header_html`) |
| Rodapé + cookies + acessibilidade + VLibras + contatos + JS | Bloco sincronizado **"Télix — Rodapé, cookies, acessibilidade e contatos"** | `tools/build.py` (`footer_html`) + `src/js/telix.js` |
| Página inicial | Bloco sincronizado **"Télix — Página inicial"** (usado pelo modelo *Página inicial do blog* e pela página *Início*) | `src/pages/inicio.html` |
| Páginas | Páginas do WordPress (um bloco "HTML personalizado" cada) | `src/pages/*.html` |
| Modelos | Modelos do tema (page, single, archive, index, home, search, 404) | `tools/publish.py` (`step_templates`) + `src/templates/404.html` |
| Dados da empresa, clientes, tokens | — | `config/site.json` |
| IDs publicados | — | `config/state.json` |

O JavaScript do rodapé é embutido em Base64 com um carregador sem os caracteres `& < >`, porque o WordPress
converte `&&` em `&#038;&#038;` dentro de scripts inline dos modelos (isso quebrava o JS).

## Fluxo de trabalho

```bash
python3 tools/images.py          # otimiza as imagens do site antigo (WebP) -> dist/media
python3 tools/validate.py src/pages/*.html   # valida tokens, ícones, links, H1, alt, SEO e frases proibidas
python3 tools/build.py && python3 tools/preview.py   # gera dist/preview/<página>.html para conferência local
node tools/shots.js file://$PWD/dist/preview/inicio.html /tmp/home 1440 900   # capturas por viewport
WPMCP_URL=https://<dominio>/wp-json/easy-mcp-ai/v1/mcp/<chave> python3 tools/publish.py all   # publica
```

Etapas do publicador: `media styles parts pages settings templates menu cleanup` (é idempotente: atualiza
em vez de duplicar e remove duplicatas `slug-2`). **Nunca grave a chave do MCP em arquivos do repositório.**

## Formulários (Canal LGPD, Canal de Denúncia, Contato, Trabalhe Conosco)

- Cada envio gera um **protocolo** (ex.: `LGPD-20260924-7K3Q9A`, `ETICA-…`, `CONTATO-…`, `RH-…`) exibido ao usuário.
- O conteúdo é gravado no próprio WordPress como **comentário pendente** da página do canal — veja em
  **Painel → Comentários → Pendentes** (filtre pela página). Nunca clique em "Aprovar": aprovar tornaria o texto
  público. Cada envio usa um e-mail técnico único (`<protocolo>@protocolo.telixcom.com.br`) justamente para
  garantir que nada seja aprovado automaticamente. O e-mail real do remetente está no corpo do relato.
- O WordPress envia aviso de moderação ao e-mail do administrador (Configurações → Geral/Discussão). Recomenda-se
  trocar o e-mail do administrador para uma caixa do Compliance/DPO.
- Anti-spam: captcha matemático simples, campo-armadilha (honeypot), tempo mínimo de preenchimento e a proteção
  nativa de flood do WordPress. Relatos anônimos não enviam nome/e-mail/telefone.
- Após o tratamento, exclua os relatos conforme a tabela de retenção da Política de Privacidade.
- Anexos (currículos, evidências) não são aceitos pelo formulário: o texto orienta o envio por e-mail com o protocolo.

## Cookies (consentimento LGPD)

Banner próprio nas cores da marca ("Télix Cookies"), com Aceitar / Rejeitar / Personalizar, categorias
(necessários, funcionais, analíticos, marketing), registro por 180 dias (cookie `tx_consent`) e ícone para
reabrir. Mapas e vídeos só carregam com consentimento funcional ou clique. Para adicionar Google Analytics no
futuro sem violar o consentimento:

```html
<script type="text/plain" data-tx-consent="analytics" src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>
```

API em `window.TelixConsent` (`has('analytics')`, `open()`, `on(callback)`), evento `tx:consent`.

## Acessibilidade

Botão de acessibilidade (Alt+Shift+A): tamanho de texto, alto contraste, escala de cinza, destacar links,
fonte legível, espaçamento, destacar títulos, cursor grande, guia de leitura, pausar animações e atalho para
Libras. Widget oficial **VLibras** (gov.br). Link "Pular para o conteúdo", foco visível, ARIA nos menus/modais,
respeito a `prefers-reduced-motion`.

## SEO

- Títulos e descrições por página, Open Graph/Twitter, `robots`, geolocalização, canonical e JSON-LD
  (`Organization`+`LocalBusiness`, `WebSite`, `WebPage`, `BreadcrumbList`, `FAQPage`, `Service`, `DefinedTermSet`)
  gerados em tempo de execução com o domínio atual — por isso **sobrevivem à troca de domínio**.
- Links internos e caminhos de mídia são relativos à raiz (`/solucoes/…`, `/wp-content/uploads/…`).
- Sitemap nativo: `/wp-sitemap.xml`; `robots.txt` nativo aponta para ele; indexação liberada.
- Limitação: sem plugin de SEO, o WordPress não permite gravar meta tags no `<head>` do HTML servido; o Google
  lê as metas injetadas por JavaScript, mas pré-visualizações de WhatsApp/LinkedIn/Facebook leem apenas o HTML
  bruto. Recomendação: instalar Yoast SEO ou Rank Math e importar `docs/SEO.md`.

## Migração para outro domínio (ex.: telixcom.com.br)

1. Migrar com uma ferramenta de migração do WordPress (ex.: All-in-One WP Migration, Duplicator ou WP-CLI
   `search-replace`), que atualiza `siteurl`/`home` e URLs absolutas.
2. Conteúdo, menus, canonical, JSON-LD e mídia usam caminhos relativos/descoberta automática do domínio.
3. Criar redirecionamentos 301 das URLs antigas (`/trabalhe.php`, `/lgpd/…`, âncoras `#contato` etc.) —
   ver `docs/REDIRECIONAMENTOS.md`.
4. Reenviar o sitemap no Google Search Console (verificação por DNS) e no Bing Webmaster Tools.

Documentos complementares: `docs/AUTHORING.md` (guia de páginas), `docs/PENDENCIAS.md` (itens a confirmar com a
Télix), `docs/REDIRECIONAMENTOS.md`, `docs/SEO.md`.
