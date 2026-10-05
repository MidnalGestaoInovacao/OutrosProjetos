# Guia de componentes do site Alicerce360

Referência rápida para escrever ou editar páginas em `src/pages/*.html`. Todas as classes vivem sob `.a3` (o build envolve cada página em `<div class="a3 a3-page">`).

## Estrutura de um arquivo de página

```html
<!--meta {"slug": "politica-de-cookies", "title": "Política de Cookies", "description": "Uma frase de até 160 caracteres.", "order": 62} -->
<section class="page-hero"> … </section>
<section class="sec"> … </section>
```

- `slug` vira a URL (`/politica-de-cookies/`). A página inicial usa `""`.
- `modules: ["3d"]` só em páginas com cenas 3D (`data-3d`).
- `order` define a ordem no WordPress (menor = antes).

## Regras de texto (importantes para o WordPress)

- **Não use** emoticons (`:)`, `;)`, `8)`), colchetes `[ ]` no texto nem `<` solto — o WordPress converte/remove.
- Escreva em pt-BR. O build gera automaticamente as chaves de tradução (EN/ES) de cada texto.
- Use `data-noi18n` em trechos que **não** devem ser traduzidos (nomes próprios em mockups, códigos, números).
- Números dinâmicos atualizados por JavaScript levam `data-dyn`.

## Marcadores do build

| Marcador | Resultado |
|---|---|
| `{{i:nome}}` | ícone em traço (`<svg class="ico">`). Nomes: check, x, arrow, arrow-up, chev, clock, globe, shield, lock, key, users, user, file, file-check, clipboard, bell, megaphone, message, mail, phone, whatsapp, pin, building, grad, award, badge, chart, layers, layout, server, database, cloud, refresh, eye, eye-off, access, hand, sparkles, rocket, zap, leaf, heart, scale, search, calendar, kanban, receipt, briefcase, cpu, contrast, type, cookie, sliders, play, pause, volume, camera, mouse, link, external, download, copy, plus, minus, info, alert, help, star, instagram, linkedin, facebook, bot, send, home, target, grid, finger, history, qr, ticket, phone2, monitor, flow, wallet, handshake, gauge, package, gift, login, pen, down, coins, filter, pulse, drive, bug, fire, cube, quote, map, book, wordpress |
| `{{i:nome:classe}}` | ícone com classe extra (ex.: `{{i:arrow:arrow}}` anima no hover do botão) |
| `{{if:whatsapp}}` | ícone preenchido |
| `{{c:company.email_dpo}}` | valor do `site.config.json` (company.name, legal_name, cnpj, address, hours, phone_display, phone_e164, email_sales, email_support, email_dpo, email_ombudsman, maps_url, instagram, linkedin, facebook, site; links.portal_titular, links.canal_integridade, links.minha_conta, links.chamados, links.ead; site.policy_date) |
| `{{wa}}` / `{{wa:Texto}}` | link do WhatsApp com mensagem |
| `{{img:logo-h}}` | URL de imagem (logo-h, logo-h-neg, logo-v, icon-512, icon-180, icon-64) |
| `{{pa:plano:faixa}}` | preço mensal equivalente no plano anual (planos: essencial, profissional, enterprise; faixas 0–4) |
| `{{hprice:id}}` | preço mensal de hospedagem (site, negocios, pro, vps) |

## Blocos de página

```html
<!-- cabeçalho de página interna: o build acrescenta sozinho o banner 3D ao lado do texto
     (cena escolhida pelo slug em EMBLEM, em tools/build.py; sem WebGL aparece o anel do logo com o ícone) -->
<section class="page-hero">
  <div class="wrap">
    <nav class="crumbs" aria-label="Você está aqui"><a href="/">Início</a><span aria-hidden="true">›</span><a href="/confianca/">Confiança</a><span aria-hidden="true">›</span><span aria-current="page">Título</span></nav>
    <span class="eyebrow">Rótulo curto</span>
    <h1>Título da página</h1>
    <p class="lead">Subtítulo acolhedor e objetivo.</p>
    <div class="doc-meta"><span>Documento <b>POL-A360-XXX-001</b></span><span>Versão <b>1.0</b></span><span>Vigência <b>{{c:site.policy_date}}</b></span><span>Aprovação <b>Direção Executiva EBAEM</b></span></div>
  </div>
</section>

<!-- seção comum -->
<section class="sec">            <!-- variações: sec--alt (cinza), sec--white, sec--navy (escura), sec--tight, sec--line (borda superior) -->
  <div class="wrap">             <!-- wrap--narrow (860px), wrap--wide -->
    <div class="sec-head"><span class="eyebrow">Rótulo</span><h2>Título</h2><p class="lead">Texto</p></div>
    …
  </div>
</section>
```

### Documento (políticas e termos)

```html
<section class="sec">
  <div class="wrap doc-layout">
    <aside class="toc" aria-label="Sumário"><b>Sumário</b><ol><li><a href="#s1">Quem somos</a></li><li><a href="#s2">…</a></li></ol></aside>
    <article class="doc">
      <div class="tldr"><b>{{i:sparkles}} Resumo em 30 segundos</b><ul class="checks checks--sm"><li>…</li></ul></div>
      <section id="s1"><h2><span class="n">1.</span> Quem somos</h2><p>…</p></section>
      <section id="s2"><h2><span class="n">2.</span> …</h2>
        <div class="table-wrap"><table class="tbl"><thead><tr><th scope="col">…</th></tr></thead><tbody><tr><td>…</td></tr></tbody></table></div>
        <div class="law"><span>LGPD, art. 18</span></div>
      </section>
      <div class="sign">Fecho / contato / histórico de versões.</div>
    </article>
  </div>
</section>
```

### Componentes

- Grades: `.grid.g-2`, `.g-3`, `.g-4`, `.g-auto`; duas colunas: `.split` (`.split--rev`).
- Cartão: `.card` (+ `card--hover`, `card--soft`, `card--flat`, `card--grad`, `card--navy`); ícone em caixa: `<span class="ibox">{{i:shield}}</span>` (+ `ibox--blue|purple|orange|navy|grad|sm`).
- Listas com check: `<ul class="checks">` (`checks--sm`, `checks--x` para “não”).
- Selos: `.badge` (+ `badge--live` disponível, `badge--soon` em breve, `badge--plan`, `badge--teal`, `badge--navy`).
- Botões: `.btn.btn--primary|accent|ghost|light|outline-light|wa|link` + `btn--sm|lg|block`.
- Aviso: `<div class="callout">{{i:info}}<p>…</p></div>` (`callout--teal`, `callout--warn`).
- Passos numerados: `<div class="steps"><div class="step"><h3>…</h3><p>…</p></div></div>`.
- Linha do tempo: `<div class="timeline"><div class="tl-item is-done"><h4>…</h4><p>…</p></div></div>`.
- FAQ: `<div class="faq"><details><summary>Pergunta</summary><div class="faq-a"><p>Resposta</p></div></details></div>`.
- Números: `<div class="stat"><b data-count="35">35</b><span>módulos</span></div>`; destaque: `.bigstat`.
- Direitos (LGPD): `<div class="rights"><div class="right"><span class="ibox">…</span><b>Acesso</b><p>…</p><small>Art. 18, II</small></div></div>`.
- Central de confiança: `<div class="trust-grid"><a class="trust-item" href="…"><span class="ibox">…</span><b>…</b><p>…</p><span class="meta">Atualizado em … {{i:arrow}}</span></a></div>`; canal: `.channel`; fluxo: `<div class="flow"><span>Recebido</span><i></i><span>Triagem</span></div>`; ODS: `<div class="ods"><div style="background:#C5192D"><b>4</b>Educação de qualidade</div></div>`.
- Faixa de chamada: `<div class="cta-band"><div><h2>…</h2><p>…</p></div><div class="row"><a class="btn btn--light">…</a></div></div>`.
- Animação de entrada: `data-reveal` (`left|right|zoom`); em grupo: `data-stagger` no pai.
- Mascote: `data-say="Dica curta"` numa seção faz o Ali comentar quando ela aparece.

### Formulários (abrem WhatsApp ou e-mail com a mensagem pronta e protocolo)

```html
<form class="form card" data-form="titular" data-mail="{{c:company.email_dpo}}" novalidate>
  <div class="two">
    <div class="field"><label for="t-nome">Nome completo <span class="req">*</span></label><input class="input" id="t-nome" name="nome" required autocomplete="name"></div>
    <div class="field"><label for="t-mail">E-mail <span class="req">*</span></label><input class="input" id="t-mail" name="email" type="email" required autocomplete="email"></div>
  </div>
  <div class="field"><label for="t-dir">Direito</label><select class="select" id="t-dir" name="direito"><option>Acesso aos dados</option></select></div>
  <div class="field"><label for="t-msg">Descreva</label><textarea class="textarea" id="t-msg" name="mensagem" required></textarea></div>
  <fieldset class="field"><legend class="lbl">Enviar por</legend><div class="radio-cards"><label><input type="radio" name="via" value="email" checked> E-mail</label><label><input type="radio" name="via" value="whatsapp"> WhatsApp</label></div></fieldset>
  <label class="check"><input type="checkbox" name="ok" required> Li a <a href="/politica-de-privacidade/">Política de Privacidade</a>.</label>
  <button class="btn btn--primary" type="submit">Enviar solicitação</button>
  <div class="form-msg" aria-live="polite"></div>
</form>
```

`data-form` aceita: `contact`, `titular`, `integrity`, `referral`, `trial`, `partner` (define o prefixo do protocolo).

Outros atalhos: `data-cookie-open` (abre preferências de cookies), `data-chat-open` (abre o assistente), `data-a11y-open` (painel de acessibilidade), `data-copy="texto"` (copia).

## Matérias (posts do WordPress)

Cada matéria é um arquivo em `src/posts/NN-slug.html`. O build gera o post, a capa, o sumário lateral, os botões de compartilhar, as matérias relacionadas e a chamada final — o arquivo traz só o conteúdo.

```html
<!--meta {"slug": "politicas-com-aceite-li-e-estou-ciente",
          "title": "Políticas com aceite: como transformar o “li e estou ciente” em prova",
          "description": "Resumo de 150 a 200 caracteres: aparece na listagem, no Google e ao compartilhar.",
          "date": "2026-10-05T09:20:00",
          "category": "Compliance e Integridade",
          "icon": "file-check",
          "related": ["canal-de-denuncias-anonimato-real", "trilha-de-auditoria-hash"]} -->
<p class="lead">Abertura de 2 a 3 frases que prende o leitor.</p>
<h2>Primeiro subtítulo</h2>
<p>Texto…</p>
<div class="art-box"><b>{{i:sparkles}} Na prática</b><p>Exemplo concreto.</p></div>
<figure class="art-fig">… um mini protótipo com classes .m-card/.m-row/.m-pill ou uma tabela .tbl …<figcaption>Legenda curta.</figcaption></figure>
<h2>Como o Alicerce360 ajuda</h2>
<ul class="checks">…</ul>
```

- Somente `<h2>` e `<h3>` como subtítulos (os `<h2>` viram o sumário automaticamente). Não use `<h1>` (o título vem do post).
- Categorias usadas: Gestão e Conformidade · Compliance e Integridade · LGPD e Privacidade · Segurança da Informação · Pessoas e Treinamentos · Comunicação Interna · Negócios e Licitações · Hospedagem e Infraestrutura.
- Componentes úteis: `.lead`, `.callout`, `.art-box`, `.art-fig` (com `<figcaption>`), `.checks`, `.steps`, `.table-wrap > table.tbl`, `.quote`, `.flow`, `.bigstat`, `.grid.g-3` com `.card`. Mini protótipos: as classes `.m-card`, `.m-row`, `.m-ic`, `.m-pill`, `.m-prog`, `.m-hash` (ver `src/pages/20-telas.html`).
- Mesmas regras de texto das páginas: sem emoticons, sem colchetes `[ ]`, sem `<` solto; contatos sempre por `{{c:...}}`.
- Capa (1200×630, uma por idioma): traz a categoria, o título (vem do meta e das traduções em `src/i18n`), a **chamada** e um **infográfico**, descritos em `src/covers.json`. Tipos de infográfico: `compare` (resposta frágil × com evidência), `seal` (aceite com campos e percentual), `flow` (etapas com status e selos), `countdown` (prazo em dias), `chain` (registros encadeados), `course` (trilha e certificado com QR), `timeline` (datas + passos), `bars` (comparativo de custos) e `checklist` (itens verificados). Gere com `node tools/covers.mjs slug` e depois `python3 tools/covers_webp.py`; o script reduz a fonte do título e avisa se algum texto não couber. Traduza o título da matéria antes de gerar, senão as versões EN/ES saem com o título em português.
- O site troca a capa conforme o idioma escolhido (listagem e página da matéria). A imagem destacada do WordPress é a versão em português.
- Na listagem, a matéria mais recente aparece em destaque (cartão largo) quando não há filtro ou busca ativos. Os marcadores `{{postcards}}`, `{{postcards:3}}` e `{{postchips}}` geram os cartões e os filtros em qualquer página.
