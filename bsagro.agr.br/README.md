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
| `eb-credito-rural/` | Plugin da Área do Cliente (versão 1.3.2), ver abaixo. `dist/` guarda o `.zip` para instalar. |

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
- **Scripts**: o WordPress aplica `wptexturize` ao HTML dos templates e corrompe JavaScript embutido que tenha comparações com `<`
  (troca `&&` por `&#038;&#038;`). Por isso o `bs.js` é publicado como `<script src="data:text/javascript;base64,…">` (ver `inline_js` em `deploy.py`).
- **Limite do Easy MCP AI**: o plugin limita requisições por período; o `deploy.py` espera e tenta de novo automaticamente (30 s, 60 s, 120 s…).
- **Imagens**: `{{IMG:chave@tamanho}}` usa um recorte gerado pelo WordPress (ex.: a logo do cabeçalho usa `medium_large`, 768 px).

## Pendências para o administrador do WordPress

Concluídas: links permanentes em "Nome do post" (links internos já no formato `/slug/`), nome do Encarregado (DPO) Sr. Sanclé
Albuquerque publicado (`DPO_NOME` em `site/deploy.py`, placeholder `{{DPO}}` nos textos), LinkedIn provisório da fundadora
(`LINKEDIN`), "+800 clientes" mantido; plugin instalado e configurado (portal na página Área do Cliente, preset BS Agro,
bens e garantias opcionais com complemento depois do envio, DPO, e-mails dos canais, 2FA obrigatório para a equipe);
pasta privada dos documentos fora da raiz pública (`/backup/bsagroagr/ebcr-private`, teste de proteção "externa");
envio de e-mails por SMTP autenticado com contato@bsagro.agr.br (`mail.bsagro.agr.br:465` SSL, senha na constante
`EBCR_SMTP_PASSWORD` do `wp-config.php`; teste com `235 Authentication succeeded` e `250 OK`).

1. **Atualizar o plugin para a 1.3.2** (opcional): `dist/eb-credito-rural-1.3.2.zip` em *Plugins → Adicionar novo → Enviar
   plugin → Substituir a atual*. Só muda a resposta do teste de SMTP via MCP (a 1.3.1 exibe "Tool execution failed." mesmo
   com o envio aceito). Sem mudança de banco.
2. **Senha de teste do SMTP**: troque a senha da caixa contato@ no cPanel e atualize a linha `EBCR_SMTP_PASSWORD` do
   `wp-config.php` (ver abaixo). A senha não fica no repositório nem no banco.
3. **Criptografia em repouso** (recomendada): defina `EBCR_ENCRYPTION_KEY` no `wp-config.php` (ver abaixo) e ligue
   "Criptografar arquivos em repouso" e "Criptografar campos sensíveis" em *Crédito Rural → Configurações → Segurança*.
4. **Equipe**: cadastre ao menos um analista/gestor (*Usuários → Adicionar novo* com a função "Analista de crédito" ou "Gestor de crédito", ou `set-team-member` via MCP). O 2FA é obrigatório para a equipe.
5. **Ícone do site**: em *Configurações → Geral → Ícone do site*, escolha a mídia "favicon-512" (os ícones já são declarados via HTML).
6. **Políticas**: são minutas completas, mas devem ser revisadas pelo jurídico (prazos internos, limite de brindes, retenção de 24 meses de contatos que não viraram clientes etc.).
7. **Página "Privacy Policy"** (rascunho padrão do WordPress): em *Configurações → Privacidade*, selecione "Aviso de Privacidade" como página de política.
8. **Formulários sem o plugin**: se o plugin for desativado, Contato, Portal do Titular e Canal de Integridade voltam a usar o
   FormSubmit, que exige um clique em "Activate Form" no primeiro e-mail recebido em cada endereço (contato@, dpo@, ouvidoria@).

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
- **Prova do consentimento**: com o plugin EB Crédito Rural (1.3.0 ou superior) ativo, cada escolha é registrada no servidor (ID, categorias, ação,
  data, IP em hash, navegador) e pode ser consultada/exportada no painel.
- API para integrações: `window.BSConsent.get() | allowed(cat) | open() | acceptAll() | rejectAll()` e o evento `bs:consent`.

## Configuração no wp-config.php (senhas e chaves fora do banco)

Arquivo: `public_html/wp-config.php` (cPanel → Gerenciador de Arquivos). Faça uma cópia de segurança antes de editar.
Cole o bloco **acima** da linha `/* That's all, stop editing! Happy publishing. */` (em instalações em português:
`/* Isso é tudo, pode parar de editar! :) */`) e, em qualquer caso, antes de `require_once ABSPATH . 'wp-settings.php';`.

```php
/* EB Crédito Rural — envio de e-mails (SMTP da caixa contato@bsagro.agr.br). */
define( 'EBCR_SMTP_PASSWORD', 'SENHA-DA-CAIXA-CONTATO' );

/* EB Crédito Rural — criptografia dos documentos e dados sensíveis (opcional, recomendado).
   Gere com: php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
   Guarde a chave em local seguro: sem ela, arquivos criptografados não podem ser abertos. */
define( 'EBCR_ENCRYPTION_KEY', 'base64:COLE-A-CHAVE-GERADA' );
```

- Use **aspas simples**: a senha pode conter `@`, `#` e `$` sem problemas.
- Não acrescente `?>` no fim do arquivo.
- Com a constante definida, o plugin usa a senha do `wp-config.php` e ignora a que estiver salva nas configurações
  (a tela mostra "definida no wp-config"). Ao trocar a senha da caixa no cPanel, atualize só esta linha.

## SEO

Sem plugin: cada página/matéria traz description, Open Graph, Twitter Cards e JSON-LD, movidos para o `<head>` pelo `bs.js`.
O WordPress fornece `<title>`, canonical, RSS, `robots.txt` e `wp-sitemap.xml`. Se um plugin de SEO (ex.: Yoast) for instalado,
as tags dele prevalecem e as duplicadas são descartadas pelo script.
