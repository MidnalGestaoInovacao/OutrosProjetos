# evellynbrandao.com.br — site institucional de Évellyn Brandão

Site WordPress (tema em bloco Twenty Twenty-Five) publicado integralmente por meio do plugin **Easy MCP AI**
(Model Context Protocol), sem instalação de plugins adicionais. Identidade visual dourado/preto, largura útil de 90%,
megamenu, banners 3D interativos (Three.js), bandeiras PT/EN/ES, acessibilidade, Libras (VLibras), aviso de cookies,
formulários por e-mail e 15 matérias sobre crédito e capital no agronegócio.

## Estrutura desta pasta

| Caminho | Conteúdo |
| --- | --- |
| `mcp.py` | Cliente MCP (Streamable HTTP) usado para falar com o WordPress. Lê a chave em `WPMCP_KEY` ou em `.wpmcp_key` (não versionado). |
| `site/eb.css` | Design system (prefixo `eb-`): cores, tipografia, cabeçalho, megamenu, hero/banners, cards, matérias, formulários, rodapé, acessibilidade, cookies, canais flutuantes. |
| `site/header.html` | Configuração global (`EB_CONFIG`: WhatsApp, LinkedIn, e-mails), sprite de ícones e monograma em SVG, logo-assinatura (PNG transparente), topbar (Portal do Titular, Canal de Integridade, LinkedIn, bandeiras), cabeçalho com megamenu e painel de acessibilidade. |
| `site/footer.html` | Rodapé (marca, Soluções, Institucional, Atendimento com formulário), selos, canais flutuantes (WhatsApp, e-mail, LinkedIn, contato), aviso de cookies, contêineres do Google Tradutor e do VLibras. |
| `site/eb.js` | Interações: megamenu, menu mobile, revelação ao rolar, inclinação 3D dos cards, acessibilidade (A+/A−/contraste, persistido), idiomas (cookie `googtrans` + Google Tradutor), cookies, canais flutuantes, VLibras, envio dos formulários (FormSubmit), sumário automático, breadcrumb e tempo de leitura. |
| `site/eb3d.js` | Cenas 3D dos banners (Three.js r128 via cdnjs): variantes `hero`, `page` e `post`, partículas douradas, paralaxe com mouse/giroscópio e rolagem; respeita `prefers-reduced-motion`. |
| `site/tpl.py` | Geração dos templates do tema (`home`, `page`, `page-no-title`, `single`, `archive`, `search`, `index`, `404`). |
| `site/home.html` | Seções da página inicial. |
| `site/content_pages.py` | Conteúdo das páginas: Sobre, Soluções (+7 subpáginas), BS Agro Capital, Contato, Matérias, Portal do Titular (LGPD) e Canal de Integridade. |
| `site/deploy.py` | Publicação: mídia → blocos sincronizados (cabeçalho/rodapé) → templates → páginas → matérias. Estado em `site/state.json`. |
| `content/posts_a.json`, `content/posts_b.json` | As 15 matérias (HTML + metadados). |
| `content/policies.json` | Política de Privacidade, Política de Cookies, Política de Compliance e Anticorrupção, Política de ESG, Declaração de Acessibilidade, textos do Portal do Titular e do Canal de Integridade e FAQ. |
| `imggen/gen.py`, `imggen/render.js` | Geração das imagens (monograma, favicon, Open Graph, visuais e banners das matérias) com Chromium/Playwright. |
| `logos/assinatura/process.py` | Logo oficial (assinatura dourada "Évellyn Brandão · Crédito Rural"): remove o fundo verde do original e gera as versões transparentes usadas no site. |

## Como publicar / atualizar

```bash
export WPMCP_KEY="wpmcp_..."          # chave do Easy MCP AI (Configurações → Easy MCP AI no WordPress)
cd imggen && python3 gen.py && cd ..  # (opcional) regenera imagens
python3 site/deploy.py all            # ou: media | blocks | templates | pages | posts
```

Cada etapa é idempotente: IDs criados ficam em `site/state.json` e são atualizados nas execuções seguintes.

## Arquitetura no WordPress

- **Cabeçalho e rodapé** são *padrões sincronizados* (`wp_block`) referenciados pelos templates com `<!-- wp:block {"ref":ID} /-->`.
  Assim, uma única edição vale para todas as páginas. (O Easy MCP AI não expõe a criação de *template parts*.)
- **Templates** do tema são sobrescritos no banco (Aparência → Editor → Modelos). `page` = layout editorial (banner 3D + conteúdo + coluna lateral);
  `page-no-title` = layout de landing (banner 3D + seções em largura total).
- **Página inicial** é o template `home` (o site mantém "Página inicial mostra: posts mais recentes").
- **Matérias**: categorias `Crédito Rural`, `Estruturação & Capital`, `Gestão Financeira no Agro`, `Liderança & Alta Performance`,
  `Mercado & Tendências`, `ESG & Conformidade`; cada matéria tem imagem destacada (banner 1200×675), tags, resumo e caixas
  "💡 Você sabia?", "😉 E a Évellyn com isso?", "📚 Referências" e "✍️ Nota da autora".

## Pendências conhecidas (ajustes rápidos)

- **Número do WhatsApp**: `EB_CONFIG.whatsapp` em `site/header.html` = `5562999232488` (+55 62 9 9923-2488). Para trocar, edite e execute `python3 site/deploy.py blocks`.
- **E-mail do DPO**: o pedido original citava `dpo@ouvidoria@evellynbrandao.com.br` (formato inválido). O site usa `dpo@evellynbrandao.com.br`; se o correto for outro, ajuste em `site/header.html`, `site/footer.html`, `site/content_pages.py`, `site/tpl.py` e `content/policies.json` e republique.
- **Ativação do FormSubmit**: cada endereço de destino precisa clicar uma vez em "Activate Form" no e-mail enviado pelo FormSubmit (já disparado para contato@ e ouvidoria@; para dpo@ o disparo será feito no primeiro envio pelo site).
- **Fotos profissionais**: tratadas por `fotos/process.py` (Pillow) a partir de `fotos/foto1-3.jpg` → `fotos/out/` (retrato quadrado na página inicial, foto vertical na página Sobre, avatar nos cartões da autora e na imagem de compartilhamento `og.png`). Para trocar as fotos, substitua os arquivos de origem, rode o script, `cd imggen && python3 gen.py og` e depois `python3 site/deploy.py media blocks templates pages`.
- **Ícone do site (favicon)**: injetado via `<link rel="icon">` no cabeçalho; para definir também em Aparência → Personalizar → Identidade do site, use `imggen/out/favicon.png`.

## Pontos de configuração

- **WhatsApp**: `site/header.html` → `EB_CONFIG.whatsapp` (somente dígitos, com DDI 55). Todos os botões `data-eb-wa` usam esse número.
- **E-mails**: contato@ (geral), dpo@ (LGPD, DPO Sr. Sanclé Albuquerque), ouvidoria@ (compliance/denúncias).
- **Formulários**: enviados por `https://formsubmit.co/ajax/<e-mail>` (sem plugin). No **primeiro envio** de cada endereço, o FormSubmit
  manda um e-mail de ativação para a caixa de destino — é preciso clicar em "Activate" uma única vez por endereço.
  Para trocar por um plugin de formulários (WPForms, Contact Form 7), substitua os `<form class="eb-form">` nas páginas.
- **Idiomas**: bandeiras PT/EN/ES na topbar gravam o cookie `googtrans` e carregam o Google Tradutor (tradução automática client-side).
- **Libras**: widget oficial VLibras (vlibras.gov.br). **Acessibilidade**: painel A+/A−/alto contraste + declaração em `/acessibilidade/`.
- **Cookies**: aviso próprio (Aceitar tudo / Rejeitar / Personalizar), preferência em `localStorage` (`eb_cookie_consent`).
- **3D**: `site/eb3d.js` — cada banner tem `<div class="eb-3d" data-eb-3d="hero|page|post">`.

## SEO (sem plugin)

- `site/seo.py` gera, para cada página e matéria, `meta description`, Open Graph (`og:title`, `og:description`, `og:url`, `og:image` com a imagem própria da matéria), Twitter Cards e dados estruturados JSON-LD (`WebPage`/`Article` + `BreadcrumbList`); o cabeçalho traz o JSON-LD global (`Person` Évellyn Brandão, `Organization` BS Agro Capital e `WebSite` com SearchAction) e as tags de ícone (`icon`, `apple-touch-icon`, `shortcut icon`, `theme-color`).
- Como o tema em bloco não permite PHP no `<head>`, as tags são emitidas no início do conteúdo e movidas para o `<head>` pelo `eb.js` no carregamento (o Google renderiza JavaScript; redes sociais leem as tags do HTML estático).
- O WordPress fornece nativamente `<title>`, canonical, RSS, `robots.txt` e o sitemap em `/wp-sitemap.xml` (o firewall da hospedagem devolve 406 para user-agents genéricos, mas responde 200 para navegadores e buscadores).
- Recomendado após a publicação: cadastrar o domínio no Google Search Console e enviar `https://evellynbrandao.com.br/wp-sitemap.xml`.

## Ícone do navegador (favicon)

O ícone (`imggen/out/favicon.png`, enviado à biblioteca de mídia) é declarado no HTML e movido para o `<head>` via script. Para que o WordPress também o sirva em `/favicon.ico` e nos ícones de dispositivos móveis, defina-o uma vez em **Configurações → Geral → Ícone do site** (ou Aparência → Personalizar → Identidade do site) escolhendo a mídia "Ícone do site" — a API MCP não expõe essa opção.

## Logo-assinatura de Évellyn Brandão (logo oficial do site)

A logo horizontal com o nome (assinatura manuscrita dourada + "CRÉDITO RURAL") vem do arquivo `logos/assinatura/assinatura-src.jpg` (dourado sobre verde).
`logos/assinatura/process.py` remove o fundo (chave no canal vermelho com reamostragem 2x, bordas descontaminadas com o dourado vizinho) e gera em `logos/assinatura/out/`:
`evellyn-brandao-assinatura.png` (transparente, 2400 px), `-800.png` (cabeçalho e rodapé), `-branca.png` e `-preta.png` (monocromáticas) e `-sobre-preto.jpg`.
Onde aparece: cabeçalho (`.eb-brand`, 64 px; 52 px ao rolar e no celular; 44 px abaixo de 480 px), rodapé (76 px), imagem de compartilhamento `og.png`
e canto de marca dos 15 banners das matérias (`imggen/gen.py`). O monograma EB continua como ícone do site (favicon) e nos ícones auxiliares.
Para trocar a logo: substitua `assinatura-src.jpg`, rode `python3 logos/assinatura/process.py`, `cd imggen && python3 gen.py og.png banner-` e `python3 site/deploy.py media blocks templates pages posts`.

## Logo da BS Agro Capital

`bslogo/process.py` extrai a logo do PDF em alta resolução (`bslogo/bs.pdf`) e gera as versões em `bslogo/out/` (mockup JPG, PNG transparente, PNG 600 px e versão sobre preto).
Ela aparece na página `/bs-agro-capital/` (cartão de marca e assinatura da citação), na seção BS Agro Capital da página inicial, no destaque do megamenu, no rodapé,
como imagem de compartilhamento da página da empresa e como `logo` da `Organization` nos dados estruturados.

## Kit de logos de Évellyn Brandão

`logos/make_logos.py` gera o kit vetorial (SVG com os textos convertidos em caminhos a partir das fontes Cinzel e Montserrat, `logos/font*.woff2`)
e os PNGs transparentes em alta resolução (2400–4000 px): monograma, horizontal e vertical em dourado, e horizontal em preto e branco; mais o ZIP completo.
Os arquivos estão em `logos/out/` e também na biblioteca de mídia do WordPress (títulos "Logo Évellyn Brandão — …").

## Conceitos alternativos de logo (5 opções)

`logos/make_logos2.py` gera cinco conceitos com elementos distintos, em dourado, preto e branco (SVG vetorial + PNG transparente), em `logos/out2/`:
1 Selo de Trigo (selo circular, ramos de trigo, EB e nome em arco) · 2 Sol & Campo (sol nascente sobre sulcos) · 3 Arco & Espiga (arco com colunas abrigando uma espiga) ·
4 Assinatura (nome manuscrito em Great Vibes com floreio) · 5 Crescimento (barras ascendentes que culminam em espiga, em moldura). Fontes: `logos/script.woff2` (Great Vibes, OFL).
