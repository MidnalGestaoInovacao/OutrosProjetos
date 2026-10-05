# Matérias do CoreCyber — guia de redação

As matérias ficam em `src/posts/AAAA-MM-DD-slug.html` (uma por arquivo). O build gera a página da matéria (`/conteudos/slug/`), os cartões da página **Conteúdos** e da página inicial, e o pacote que vira **post** no WordPress, com a imagem de destaque. O formato segue o das matérias do site da EBAEM: abertura forte, seções curtas, “Você sabia?”, “E o CoreCyber com isso?”, referências, nota do autor e chamada final.

## Cabeçalho (meta)

```html
<!--meta {
  "slug": "seguranca-em-pedacos",
  "title": "Cinco ferramentas, cinco faturas, nenhuma visão: o custo escondido da segurança em pedaços",
  "description": "Uma frase (até 200 caracteres) que resume a matéria e dá vontade de ler. Vira o resumo do cartão e do WordPress.",
  "date": "2026-10-05",
  "category": "diferenciais",
  "tags": ["Consolidação", "Custo total", "Preço em reais"],
  "icon": "layers",
  "cover": {
    "kicker": "Consolidação · Custo total",
    "stat": "5 → 1",
    "stat_label": "ferramentas separadas trocadas por um painel",
    "s2": "R$",
    "s2_label": "preço em reais, sem variação cambial",
    "s3": "10 = 12",
    "s3_label": "no plano anual, paga 10 meses e leva 12",
    "question": "Quantas telas a sua equipe abre para responder a um único alerta?"
  },
  "alt": "Infográfico CoreCyber — cinco ferramentas trocadas por um painel único"
} -->
```

| Campo | Regra |
|---|---|
| `slug` | minúsculas, sem acento, com hífens; único |
| `title` | a chamada da matéria, até ~90 caracteres; inteligente, concreta, sem caça-clique |
| `description` | 1 frase, até 200 caracteres |
| `date` | `AAAA-MM-DD` (o mesmo do nome do arquivo); publicação às 8h |
| `category` | `produto`, `diferenciais`, `conformidade`, `guias` ou `bastidores` |
| `tags` | 2 a 4 rótulos curtos |
| `icon` | um ícone do sprite (lista em `docs/COMPONENTES.md`) — decora a capa |
| `cover` | textos da imagem de capa (infográfico 1200×675): `kicker` até 40 caracteres; `stat` até 7 caracteres (número ou símbolo forte); `stat_label` até 60; `s2`/`s3` até 6 caracteres; `s2_label`/`s3_label` até 42; `question` até 90 |
| `alt` | texto alternativo da capa, começando por “Infográfico CoreCyber — ” |

Os números da capa precisam ser fatos do produto (`docs/PRODUTO.md`) ou fatos públicos citados nas referências.

## Corpo

```html
<p class="art-lead">Abertura de 2–3 frases: uma cena reconhecível, uma pergunta ou um dado que prende.</p>

<h2>Seção 1</h2>
<p>…</p>

<div class="art-box art-box--curio"><b>Você sabia?</b><p>Um fato verificável e curioso, com a fonte nas referências.</p></div>

<h2>Seção 2</h2>
<p>…</p>
<ul class="checks"><li>Item de lista</li></ul>

<div class="art-box art-box--hook"><b>E o CoreCyber com isso?</b><p>A ponte entre o tema e o produto, sem exagero.</p></div>

<h2>Referências</h2>
<ul class="art-refs">
  <li><b>Fonte</b> · o que a fonte diz — <a href="https://…" target="_blank" rel="noopener">acessar</a></li>
</ul>

<div class="art-author"><b>Nota do autor</b><p>2–3 frases em primeira pessoa do plural, acolhedoras e honestas.</p><span>— Equipe CoreCyber · EBAEM</span></div>

<div class="art-cta"><p><strong>Como o CoreCyber ajuda:</strong> uma frase objetiva.</p><p><a class="btn btn--primary" href="/diagnostico-gratuito/">Quero meu diagnóstico gratuito</a></p></div>
```

Também valem: `<h3>`, `<ol class="art-steps">` (passo a passo numerado), `<ul class="checks">`, `<blockquote>`, `<div class="callout">{{i:info}}<p>…</p></div>` e tabelas (`<div class="table-wrap"><table class="tbl">…</table></div>`). Links internos usam o caminho da página (`/planos/`, `/plataforma/#vulnerabilidades`, `/conteudos/outro-slug/`).

## Tom e regras

- Português do Brasil, “você”, frases curtas, sem jargão sem explicação. Convincente pela clareza, não pelo exagero. Entre 700 e 1.000 palavras.
- **Só fatos do produto que estão em `docs/PRODUTO.md`.** O que está “Em evolução” ou no “Roadmap” aparece com essas palavras — nunca como pronto.
- Não invente estatísticas de mercado, números de clientes, depoimentos ou certificações. Fatos externos só com fonte oficial ou reconhecida nas referências (NIST, CISA, FIRST, ISO, ANPD, Planalto, CERT.br, OWASP, IETF/RFC…).
- Não cite concorrentes pelo nome: compare com categorias (“suítes globais”, “ferramentas abertas”, “planilhas”).
- Preços: não escreva valores; aponte para `/planos/` (os valores são propostas e mudam no `site.config.json`).
- Sem emojis, sem emoticons (`:)`), sem colchetes `[ ]` e sem `<` solto no texto.
