# bsagro.agr.br — site institucional da BS Agro Capital

Migração do site `bsagrocapital.vercel.app` para o WordPress em **https://bsagro.agr.br**, publicada inteiramente
pelo plugin **Easy MCP AI** (sem instalar outros plugins para o site em si). Mantém a estrutura, os textos e as
imagens do site original e acrescenta acessibilidade, Libras, três idiomas, canais de contato, central de
conformidade (LGPD, compliance, ESG, comercialização), Área do Cliente e matérias.

## O que mudou em relação ao site original

| Área | Original (Vercel) | No WordPress |
| --- | --- | --- |
| Estrutura da home | Hero, Soluções, Como funciona, Sobre, Na prática, FAQ, formulário | **Mantida**, com as mesmas imagens e textos |
| Novidades na home | — | Pré-diagnóstico interativo (3 perguntas → linhas indicadas, WhatsApp e formulário já preenchidos), faixa da Área do Cliente com linha do tempo ilustrativa, grade das últimas matérias, 3 novas perguntas no FAQ |
| CSS | Tailwind Play CDN (compilado no navegador) | Tailwind 3.4 **pré-compilado** só com as classes usadas + CSS original + camada WordPress (`site/build/bs.min.css`, ~70 KB) no *CSS adicional* |
| Fontes | Arquivos locais | Mesmos arquivos (Raleway/Roboto, fontsource) via jsDelivr — a biblioteca de mídia do WordPress não aceita `.woff2` |
| Cabeçalho | Pílula de vidro, só visível no hero | Igual na home; nas páginas internas fica sempre visível. **Megamenus** (Soluções, Como funciona, A BS Agro, Matérias, Conformidade) com acordeão no celular. Botão **Área do Cliente** no topo (ícone no celular) |
| Acessibilidade | Link "pular para o conteúdo" | Barra de acessibilidade com atalhos eMAG (Alt+1/2/3), alto contraste, A−/A/A+, **Libras (VLibras)**, declaração de acessibilidade |
| Idiomas | Só PT | Bandeiras **PT/EN/ES** (Google Tradutor, carregado só depois da escolha do idioma) |
| Contato | E-mail e WhatsApp no rodapé | Página **Canal de contato** (formulário com protocolo), Portal do Titular (LGPD), Canal de Integridade (com anonimato) |
| Conformidade | Política de privacidade curta | Central de conformidade + 10 documentos + gerenciador de consentimento de cookies (ver abaixo) |
| Matérias | — | Aba **Matérias** com 5 matérias com capa, categorias, tags, sumário automático, tempo de leitura e SEO |

## Canais oficiais (e-mails)

| Canal | E-mail |
| --- | --- |
| Contato central e principal | `contato@bsagro.agr.br` |
| LGPD / Encarregado (DPO) | `dpo@bsagro.agr.br` |
| Ouvidoria, Compliance e Anticorrupção | `ouvidoria@bsagro.agr.br` |

O pedido original citava o domínio `@bragro.agr.br`, que **não existe** (sem registro DNS). O domínio do site, com MX
configurado, é `bsagro.agr.br`, e foi o usado. Para trocar, edite `EMAILS` em `site/deploy.py` e rode `python3 site/deploy.py all`.

## Estrutura desta pasta

| Caminho | Conteúdo |
| --- | --- |
| `mcp.py` | Cliente MCP (Streamable HTTP, via `curl`) do Easy MCP AI. Lê a chave em `WPMCP_KEY` ou em `.wpmcp_key` (não versionado). O servidor derruba parte das conexões TLS, por isso há várias tentativas automáticas. |
| `fonte-vercel/` | Cópia do site original (HTML, CSS, configuração do Tailwind) usada como referência. |
| `assets/` | Imagens do site original (hero, soluções, atuação, parceiros, sócios, logo, ícones). |
| `site/header.html` | `BS_CONFIG` (WhatsApp, e-mails, endpoints), sprite de ícones, barra de acessibilidade/idiomas, cabeçalho com o botão **Área do Cliente**. |
| `site/footer.html` | Rodapé (navegação, conformidade, contatos), selos, botão de WhatsApp, aviso de cookies, contêineres do VLibras e do Google Tradutor. |
| `site/home.html` + `site/sections/` | Página inicial (gerada a partir de `fonte-vercel/index.html`) e as seções novas. `{{SPLIT}}` marca onde entra a grade de matérias. |
| `site/content_pages.py` | Canal de contato, Central de conformidade, Portal do Titular, Canal de Integridade, Área do Cliente, Matérias, Obrigado. |
| `content/policies.json` | Aviso de Privacidade, Termos de Uso, Cookies, Compliance e Anticorrupção, ESG, Comercialização, Acessibilidade, Autorização SCR, Declaração de Veracidade, Comunicações de Marketing, textos do Portal do Titular, do Canal de Integridade e da central, FAQ. |
| `content/posts.json` | As 5 matérias (HTML + metadados + dados da capa). |
| `site/css/original.css` | CSS do site original (fontes apontando para o jsDelivr). |
| `site/css/wp.css` | Ajustes para o tema em bloco e componentes novos. |
| `site/build_css.sh` | Compila `site/build/bs.min.css` (Tailwind + original + WordPress). |
| `site/bs.js` | Script do site original + acessibilidade, idiomas, cookies, VLibras, pré-diagnóstico, formulários dos canais, sumário, tempo de leitura. |
| `site/tpl.py` | Templates do tema (`home`, `page`, `page-no-title`, `single`, `archive`, `index`, `search`, `404`). |
| `site/seo.py` | Meta description, Open Graph, Twitter Cards e JSON-LD (`FinancialService`, `WebSite`, `WebPage`, `Article`, `BreadcrumbList`, `FAQPage`). |
| `site/deploy.py` | Publicador idempotente (IDs em `site/state.json`). |
| `site/preview.py` | Pré-visualização local sem WordPress (para revisar layout com o navegador). |
| `imggen/covers.mjs` | Gera as capas das matérias (1200×675) e as imagens de compartilhamento (1200×630) com as fotos do site. |
| `eb-credito-rural/` | Plugin da Área do Cliente (versão 1.3.0), ver abaixo. `dist/` guarda o `.zip` para instalar. |

## Como publicar / atualizar

```bash
export WPMCP_KEY="wpmcp_..."                 # chave do Easy MCP AI (ou arquivo .wpmcp_key nesta pasta)
bash site/build_css.sh                       # recompila o CSS (Node.js)
node imggen/covers.mjs                       # (opcional) regenera capas; requer playwright-core
python3 site/deploy.py all                   # ou etapas: settings media skeleton css blocks templates pages posts cleanup
python3 site/deploy.py forms                 # dispara o e-mail de ativação do FormSubmit para os 3 canais
```

Cada etapa é idempotente. Para republicar só algumas páginas: `BS_PAGES=contato,conformidade python3 site/deploy.py pages`.

### Arquitetura no WordPress (tema Twenty Twenty-Five)

- **Cabeçalho e rodapé** são padrões sincronizados (`wp_block`) referenciados pelos templates; uma edição vale para todas as páginas.
- **Templates** do tema são sobrescritos no banco (Aparência → Editor → Modelos). `page` = políticas (banner + texto + coluna lateral com sumário);
  `page-no-title` = páginas com seções próprias (contato, conformidade, canais, Área do Cliente, matérias, obrigado).
- **Página inicial** é o template `home` (o WordPress continua em "Página inicial mostra: posts mais recentes").
- **CSS** fica em *Aparência → Personalizar → CSS adicional* (gerado por `build_css.sh`; não editar lá).
- **Formulários**: "Solicitar análise" (home) continua enviando ao webhook n8n da Widen Creative, como no site original, e redireciona para `/obrigado`;
  Canal de contato, Portal do Titular e Canal de Integridade enviam por e-mail via FormSubmit (`contato@`, `dpo@`, `ouvidoria@`), com número de protocolo.
- **Links**: gerados a partir do permalink real de cada página. Hoje o site usa links simples (`?page_id=`).

## Pendências para o administrador do WordPress

1. **Links permanentes**: em *Configurações → Links permanentes*, escolha **"Nome do post"** e salve. Depois rode `python3 site/deploy.py all`
   para os links internos passarem ao formato `/contato/`, `/materias/` etc. (a API do MCP não altera essa opção).
2. **Plugin da Área do Cliente**: o Easy MCP AI não instala plugins. Em *Plugins → Adicionar novo → Enviar plugin*, envie
   `dist/eb-credito-rural-1.3.0.zip` e ative. Depois rode `python3 site/deploy.py pages` para a página **Área do Cliente**
   trocar o aviso "sendo ativada" pelo portal (`[ebcr_portal]`). Configure em *Crédito Rural → Configurações*: identidade visual
   (preset "BS Agro Capital"), exigência de garantias, e as páginas legais (Aviso de Privacidade etc.).
3. **FormSubmit**: cada endereço precisa clicar uma vez em "Activate Form" no e-mail recebido do FormSubmit (`deploy.py forms` dispara esse e-mail).
4. **Encarregado (DPO)**: a LGPD pede a divulgação da identidade do Encarregado. Os textos citam o cargo e o e-mail `dpo@`; informe o nome para incluí-lo.
5. **Ícone do site**: em *Configurações → Geral → Ícone do site*, escolha a mídia "favicon-512" (os ícones já são declarados via HTML).
6. **LinkedIn**: o site original tinha o placeholder `[INSERIR LINK DO LINKEDIN]`; o ícone foi retirado até o endereço ser informado.
7. **Números da trajetória** ("+800 clientes"): o site original marcava para confirmar com o cliente antes de publicar.
8. **Políticas**: são minutas completas, mas devem ser revisadas pelo jurídico (prazos internos, limite de brindes, retenção de 24 meses de contatos que não viraram clientes etc.).
9. **Página "Privacy Policy"** (rascunho padrão do WordPress): em *Configurações → Privacidade*, selecione "Aviso de Privacidade" como página de política.

## Aviso de cookies (gerenciador de consentimento)

Inspirado no padrão de uso do CookieYes, mas com código próprio e a identidade do site (oliva, dourado, Raleway/Roboto):

- **Banner** na primeira visita com "Aceitar tudo", "Rejeitar tudo" (mesmo destaque, como orienta a ANPD) e "Personalizar".
- **Central de preferências** (diálogo acessível: foco preso, Esc fecha e devolve o foco) com as categorias Necessários (sempre ativos),
  Funcionais, Analíticos e Publicidade, interruptores `role="switch"`, lista de cookies por categoria (nome, duração, finalidade,
  fornecedor), "Mostrar mais", **ID do consentimento** e data do registro.
- **Botão flutuante** (canto inferior esquerdo) e o link "Preferências de cookies" do rodapé reabrem a central.
- A escolha vale **12 meses** (`bs_cookie_consent`, com versão); depois o banner volta. O sinal **Global Privacy Control** do navegador é respeitado.
- **Bloqueio por categoria**: VLibras e preferências de acessibilidade só com "Funcionais" (ou quando a pessoa clica em "Libras");
  Google Tradutor só quando um idioma é escolhido. Scripts futuros podem ser marcados com `type="text/plain" data-bs-category="analytics"`
  e só rodam após o consentimento.
- **Google Consent Mode v2** já declarado com tudo negado por padrão, atualizado conforme a escolha (pronto para um futuro GA4).
- **Prova do consentimento**: com o plugin EB Crédito Rural 1.3.0 ativo, cada escolha é registrada no servidor (ID, categorias, ação,
  data, IP em hash, navegador) e pode ser consultada/exportada no painel.
- API para integrações: `window.BSConsent.get() | allowed(cat) | open() | acceptAll() | rejectAll()` e o evento `bs:consent`.

## SEO

Sem plugin: cada página/matéria traz description, Open Graph, Twitter Cards e JSON-LD, movidos para o `<head>` pelo `bs.js`.
O WordPress fornece `<title>`, canonical, RSS, `robots.txt` e `wp-sitemap.xml`. Se um plugin de SEO (ex.: Yoast) for instalado,
as tags dele prevalecem e as duplicadas são descartadas pelo script.
