# Guia de autoria de páginas — site Télix (WordPress, tema de blocos)

Cada página é um arquivo `src/pages/<arquivo>.html` com:

1. Um cabeçalho `<!--META { ...json... } META-->` (JSON válido, sem comentários).
2. O corpo em HTML puro (vira um bloco `<!-- wp:html -->` no WordPress). **Não** inclua `<html>`, `<head>`, `<body>`, cabeçalho ou rodapé — eles já existem no tema.

`python3 tools/build.py` valida tokens e ícones; `python3 tools/preview.py` gera `dist/preview/<arquivo>.html` para conferência visual.

## META (obrigatório)

```json
{
 "title": "Título da página no WordPress (curto, com a palavra-chave principal)",
 "slug": "slug-da-pagina",
 "parent": "slug-do-pai ou vazio",
 "menu_order": 0,
 "comments": false,
 "breadcrumb": [["Início", "/"], ["Soluções", "/solucoes/"], ["Nome da página", "/solucoes/slug/"]],
 "seo": {
  "title": "Título SEO ≤ 60 caracteres | Télix",
  "description": "Meta description entre 140 e 158 caracteres, com benefício + palavra-chave + chamada.",
  "keywords": "palavra 1, palavra 2, ...",
  "image": "{{IMG_OG}}",
  "pageType": "WebPage | AboutPage | ContactPage | CollectionPage | FAQPage",
  "schema": [ { "@type": "Service", "name": "...", "serviceType": "...", "provider": "org", "areaServed": "BR", "description": "...", "url": "{home}/solucoes/slug/" } ]
 }
}
```

- `comments: true` **somente** nas páginas com formulário (canal-lgpd, canal-de-denuncia, contato, trabalhe-conosco).
- Em `schema`, use `"provider": "org"` / `"publisher": "org"` (o runtime troca pela entidade da empresa) e `{home}` para URLs absolutas (o runtime troca pelo domínio atual — o site sobrevive a migração de domínio).
- O runtime já gera Organization/LocalBusiness, WebSite, WebPage, BreadcrumbList e FAQPage (a partir de `.tx-faq[data-schema]`). Não duplique esses tipos em `schema`.

## Tokens disponíveis no corpo

- `{PHONE}` telefone formatado · `{TEL_LINK}` `tel:+55...` · `{WA_LINK}` link do WhatsApp · `{EMAIL}` e-mail geral · `{MAILTO}`
- `{{IMG_*}}` imagens (ver `config/site.json → tokens`): `IMG_CENTRAL`, `IMG_CENTRAL_A`, `IMG_REG_SUP`, `IMG_BG01`, `IMG_SARH1..5`, `IMG_SOL1..5`, `IMG_TRABALHE`, `IMG_TRABALHE_A`, `IMG_COMITE1..3`, `IMG_OG`, `IMG_LOGO_PNG`.
- `{{N_CLIENTES}}`, `{{N_ESTADOS}}`, `{{SERVED_LIST}}`, `{{SERVED_JSON}}`, `{{LOGO_GRID}}`, `{{LOGO_MARQUEE}}`, `{{BR_GEO}}` (gerados a partir de `config/site.json → clients`).
- Qualquer outra chave de `config/site.json → tokens` escrita como `{{CHAVE}}`.
- Ícones: `[[i:nome]]` (lista em `docs/icons.txt`). Ex.: `[[i:headset]]`.

## Links internos

Sempre **relativos à raiz** (`/solucoes/sac/`), nunca com domínio. Páginas existentes:

`/`, `/a-telix/`, `/especialistas-em-saude-suplementar/`, `/solucoes/`, `/solucoes/central-de-atendimento/`, `/solucoes/sac/`, `/solucoes/sistema-de-protocolo/`, `/solucoes/regulacao-e-remocao-hospitalar/`, `/solucoes/agendamento/`, `/solucoes/pesquisa-de-satisfacao/`, `/solucoes/atendimento-digital/`, `/solucoes/qualidade-e-monitoria/`, `/clientes/`, `/conformidade/`, `/conformidade/politica-de-privacidade/`, `/conformidade/politica-de-compliance/`, `/conformidade/politica-de-seguranca-da-informacao/`, `/conformidade/politica-esg/`, `/conformidade/politica-de-inteligencia-artificial/`, `/conformidade/politica-de-cookies/`, `/conformidade/glossario-lgpd/`, `/canal-lgpd/`, `/canal-de-denuncia/`, `/contato/` (âncoras `#proposta`, `#como-chegar`), `/trabalhe-conosco/`, `/acessibilidade/`, `/perguntas-frequentes/`, `/mapa-do-site/`.

## Componentes (classes do design system)

Tudo já está estilizado em `src/styles/global.css` — use as classes, **não** escreva `<style>` nem CSS inline além de pequenos ajustes de espaçamento.

| Componente | Marcação |
|---|---|
| Seção | `<section class="tx-section [tx-section--sand|--sand2|--dark|--gold|--tight]" aria-labelledby="id-do-h2">` + `<div class="tx-container">` (`tx-container--narrow` para texto longo) |
| Hero de página interna | `<section class="tx-pagehero"><div class="tx-container tx-pagehero__grid"><div>` breadcrumb + eyebrow + `<h1 class="tx-h1">` + `<p class="tx-lead">` + botões `</div><div class="tx-pagehero__3d"><div class="tx-3d" data-tx-3d="network|shield|route" data-variant="light" data-labels="A,B,C" role="img" aria-label="..."><div class="tx-3d-fallback"></div></div></div></div></section>` |
| Breadcrumb visível | `<nav aria-label="Trilha de navegação"><ol class="tx-breadcrumb"><li><a href="/">Início</a></li>...<li aria-current="page">Atual</li></ol></nav>` |
| Eyebrow / títulos | `<p class="tx-eyebrow">`, `<h2 class="tx-h2">` com destaque `<span class="tx-hl">`, `<p class="tx-lead">` |
| Grade | `tx-grid tx-grid--2|3|4|auto`, duas colunas texto/mídia `tx-split` (`tx-split--rev` inverte) |
| Card | `<article class="tx-card [tx-tilt] [tx-card--dark|--gold|--glass|--feature]">` + `<div class="tx-icon">[[i:nome]]</div>` + `<h3>` + `<p>`; link no final `<a class="tx-link" href="...">Saiba mais [[i:arrow-right]]</a>` |
| Botões | `tx-btn` (dourado), `tx-btn--dark`, `tx-btn--ghost`, `tx-btn--light` (sobre fundo escuro), `tx-btn--wa`, `tx-btn--sm`; agrupar em `<div class="tx-btns">` |
| Lista com check | `<ul class="tx-checks [tx-checks--2]">` |
| Chips | `<ul class="tx-chips"><li class="tx-chip">...` |
| Números | `<div class="tx-stats"><div class="tx-stat"><div class="tx-stat__num"><span data-count="24">24</span></div><div class="tx-stat__label">...</div></div>` (só números verdadeiros) |
| Etapas | `<ol class="tx-steps"><li><h3>..</h3><p>..</p></li></ol>` |
| Linha do tempo | `<ol class="tx-timeline"><li><strong>2012</strong>texto</li></ol>` |
| FAQ | `<div class="tx-faq" data-schema><details><summary>Pergunta?</summary><div><p>Resposta.</p></div></details></div>` |
| Abas | `.tx-tabs` com `role=tablist/tab/tabpanel`, `aria-selected`, `aria-controls`, painéis com `hidden` |
| Cartões que viram (3D) | `<div class="tx-flip"><div class="tx-flip__inner"><div class="tx-flip__face tx-flip__front">..</div><div class="tx-flip__face tx-flip__back">..</div></div><button class="tx-flip__toggle" aria-pressed="false" aria-label="Virar cartão: ..."></button></div>` |
| Anel 3D (valores) | `<div class="tx-ring" aria-label="..."><div class="tx-ring__stage"><div class="tx-ring__item" style="--i:0">[[i:nome]]<strong>Valor</strong><span>descrição</span></div>...</div></div><div class="tx-ring__ctrl"><button type="button" data-dir="-1" aria-label="Anterior">[[i:chevron-left]]</button><button type="button" data-dir="1" aria-label="Próximo">[[i:chevron-right]]</button></div>` |
| Mídia | `<figure class="tx-media"><img src="{{IMG_...}}" alt="descrição real" width=".." height=".." loading="lazy"><figcaption class="tx-media__tag">[[i:..]] legenda</figcaption></figure>` |
| CTA | `<div class="tx-cta [tx-cta--dark]"><div><h2>..</h2><p>..</p></div><div class="tx-btns">..</div></div>` |
| Citação | `<blockquote class="tx-quote">` |
| Documento/política | `<div class="tx-doc"><aside class="tx-toc" data-auto=".tx-prose"><strong>Nesta página</strong></aside><article class="tx-prose">` com `<h2>` numerados; `<ul class="tx-docmeta">` para versão/vigência/responsável |
| Lista de políticas | `<div class="tx-policy-list"><a class="tx-policy" href=".."><span class="tx-icon">[[i:lock]]</span><span><strong>Nome</strong><span>resumo</span></span></a></div>` |
| Mapa (com consentimento) | `<div class="tx-map" data-src="URL-embed" data-title="..." data-tx-consent="functional"><div class="tx-map__gate"><span class="tx-icon">[[i:map]]</span><strong>Mapa interativo</strong><p>O Google Maps utiliza cookies de terceiros. Carregue o mapa para visualizar a rota.</p><button type="button" class="tx-btn tx-btn--sm" data-tx-map-load>Carregar mapa</button></div></div>` (também serve para vídeos do YouTube com `youtube-nocookie.com`) |
| Alertas | `<div class="tx-alert tx-alert--info|ok|warn|err">[[i:info]]<div>texto</div></div>` |
| Animação de entrada | atributo `data-reveal` (`left`, `right`, `zoom`) e `data-delay="80"` |

## Formulários (canais)

```html
<form class="tx-form" data-channel="lgpd|compliance|contato|carreiras" data-prefix="LGPD|ETICA|CONTATO|RH" data-title="Canal LGPD" data-fallback-email="dpo@telixcom.com.br" data-success="Texto de sucesso" novalidate>
  <div class="tx-form__status" aria-live="polite"></div>
  <div class="tx-form__grid">
    <div class="tx-field"><label for="f-nome">Nome completo <span class="tx-req" aria-hidden="true">*</span></label><input id="f-nome" name="nome" type="text" autocomplete="name" required></div>
    <div class="tx-field"><label for="f-email">E-mail <span class="tx-req">*</span></label><input id="f-email" name="email" type="email" autocomplete="email" required></div>
    <div class="tx-field tx-form__full"><label for="f-msg">Mensagem</label><textarea id="f-msg" name="mensagem" required minlength="10"></textarea><div class="tx-help">Dica.</div></div>
    <div class="tx-field tx-form__full"><fieldset class="tx-fieldset" data-required><legend>Opções</legend><div class="tx-options"><label><input type="radio" name="x" value="A"> A</label></div></fieldset></div>
    <div class="tx-hp" aria-hidden="true"><label for="f-site">Não preencha</label><input id="f-site" name="website" type="text" tabindex="-1" autocomplete="off"></div>
    <div class="tx-field tx-form__full"><label for="f-cap">Verificação anti-spam: resolva a conta <span class="tx-req">*</span></label>
      <div class="tx-captcha"><span class="tx-captcha__q" aria-live="polite"></span><input id="f-cap" type="text" inputmode="numeric" pattern="[0-9]*" required autocomplete="off" aria-describedby="f-cap-h"><button type="button">[[i:refresh]] Nova conta</button></div>
      <div class="tx-help" id="f-cap-h">Digite o resultado da soma ou subtração.</div></div>
    <div class="tx-field tx-form__full"><label class="tx-consent"><input type="checkbox" name="consentimento" required> <span>Li e concordo com a <a href="/conformidade/politica-de-privacidade/">Política de Privacidade</a> ...</span></label></div>
  </div>
  <div class="tx-btns" style="margin-top:18px"><button type="submit" class="tx-btn">Enviar [[i:send]]</button></div>
</form>
```

- O `input` do captcha **não tem `name`** (não é enviado). O honeypot fica em `.tx-hp`.
- Campos de identificação que podem ser ocultados no modo anônimo ficam dentro de `<div data-tx-ident>...</div>`; o checkbox de anonimato tem `data-tx-anon`.
- Campos condicionais: wrapper com `data-show-if="nome_do_campo=valor1|valor2"`.
- O envio vai para o próprio WordPress (fica em **Comentários → Pendentes** da página; nunca é publicado) e o usuário recebe um **número de protocolo**. Não prometa e-mail automático de confirmação ao usuário.
- Upload de arquivos não é suportado: oriente o envio por e-mail (currículo, evidências).

## Regras de conteúdo

- Português do Brasil, tom confiante, humano, claro; frases curtas; foco no benefício do cliente (operadoras, hospitais, clínicas) e no beneficiário.
- **Nunca invente** números, certificações, prêmios, clientes, depoimentos, prazos contratuais ou SLAs. Use somente fatos de `research/master_brief.md`. Quando um número ajudaria, descreva o que medimos/entregamos ("relatórios mensais com TMA, nível de serviço, abandono…"), não um resultado.
- Todo texto relevante do site original precisa aparecer (melhorado) na página correspondente — consulte a checklist do brief.
- Gatilhos éticos: autoridade (normas ANS, especialização), prova social (clientes reais), redução de risco (conformidade, rastreabilidade), clareza (passo a passo), reciprocidade (conteúdo útil/FAQ), compromisso (CTA progressivo). Sem urgência falsa.
- Acessibilidade: um único `<h1>` por página; hierarquia de títulos sem saltos; `alt` descritivo em imagens; textos de link significativos; ícones decorativos já saem com `aria-hidden`.
- Cada página termina com um CTA coerente e links internos para 2–4 páginas relacionadas.
