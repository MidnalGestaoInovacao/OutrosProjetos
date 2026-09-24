# SEO e migração de domínio (trix.ebaem.com.br → trixti.com.br)

## O que já está implementado
- Títulos e meta descriptions únicos por página (injetados no `<head>` pelo `trix.js` a partir do JSON `#trix-seo` de cada página) + Open Graph/Twitter Cards, `theme-color`, `geo.*`.
- Canonical do próprio WordPress; sitemap nativo em `/wp-sitemap.xml`; `robots.txt` virtual do WordPress.
- Dados estruturados (JSON-LD) gerados em tempo de execução com `location.origin`: Organization/LocalBusiness/ProfessionalService (CNPJ, endereço, geo, telefones, redes), WebSite, WebPage/AboutPage/ContactPage/ItemPage, BreadcrumbList, SoftwareApplication (produtos), Service (serviços) e FAQPage. **Nenhuma URL absoluta do domínio é gravada no conteúdo**, então a migração de domínio não quebra SEO nem links.
- Links internos relativos (`/produtos/saw/`), slugs semânticos em português, hierarquia de páginas (`/servicos/…`, `/produtos/…`, `/conformidade/…`), H1 único, headings hierárquicos, alt em imagens, lazy loading, fontes com `display=swap`.
- Idioma `pt_BR`, título do site e tagline definidos.

## Antes de liberar para indexação (staging)
- Instalar `wordpress/mu-plugins/trix-site-helpers.php` (tags de SEO no servidor).
- Manter **"Desencorajar os mecanismos de busca"** ligado em trix.ebaem.com.br até o go-live no domínio definitivo; desligar após a migração.
- Definir a **página inicial estática = Home** (obrigatório): remove a duplicidade `/` × `/home/` e gera canonical/redirecionamento nativos.

## Ao migrar para trixti.com.br
1. **Trocar o endereço do site** em Configurações → Geral (WordPress e URL do site) ou via `wp search-replace` (as páginas usam links relativos; só a biblioteca de mídia tem URLs absolutas do domínio antigo, que o search-replace corrige).
2. Publicar os **redirecionamentos 301** abaixo no servidor antigo/novo (Apache `.htaccess` ou plugin *Redirection*).
3. Reenviar o sitemap no Google Search Console (`https://www.trixti.com.br/wp-sitemap.xml`) e usar a ferramenta "Alteração de endereço".
4. Definir a página inicial estática (Configurações → Leitura → "Página estática" → *Home*). O template `home` já exibe a página *Home* automaticamente, mas a configuração explícita é a forma canônica.
5. Definir o ícone do site (Aparência → Personalizar → Identidade do site) com `favicon`/logo já na biblioteca de mídia.
6. Opcional: instalar Yoast SEO ou Rank Math para gerenciar metas pelo painel; os títulos/descrições atuais servem de base.

## Mapa de redirecionamentos (URLs antigas → novas)
| Antiga | Nova |
|---|---|
| `/index.php` | `/` |
| `/fabrica.php` | `/servicos/fabrica-de-software/` |
| `/agenda.php` | `/servicos/agenda-medica/` |
| `/solucoes.php` | `/servicos/solucoes-e-automacao/` |
| `/consultoria.php` | `/servicos/consultoria/` |
| `/conectividade.php` | `/servicos/conectividade-em-saude/` |
| `/escritorio.php` | `/servicos/escritorio-de-processos/` |
| `/oracle.php` | `/servicos/oracle-partner/` |
| `/prontow.php` | `/produtos/prontow/` |
| `/saw.php` | `/produtos/saw/` |
| `/portal.php` | `/produtos/portal-operadora/` |
| `/gedai.php` | `/produtos/gedai/` |
| `/integrador.php` | `/produtos/integrador/` |
| `/intranet.php` | `/produtos/intranet/` |
| `/biometriafacial.php` | `/produtos/aspect-face/` |
| `/xield.php` | `/produtos/xield/` |
| `/clientes.php` | `/clientes/` |
| `/webinario/` | `/empresa/eventos/` |
| `/lgpd/`, `/lgpd/index.html` | `/conformidade/lgpd/` |
| `/lgpd/politicas.html` | `/conformidade/politica-de-privacidade/` |
| `/lgpd/cookies.html` | `/conformidade/cookies/` |
| `/lgpd/glossario.html` | `/conformidade/glossario-lgpd/` |
| `/lgpd/index.html#ouvidoria` | `/canal-de-compliance/` |
| `/lgpd/TRIX - Código de Conduta da Empresa.pdf` | `/conformidade/codigo-de-conduta/` |

### Exemplo `.htaccess`
```
RewriteEngine On
RewriteRule ^index\.php$ / [R=301,L]
RewriteRule ^fabrica\.php$ /servicos/fabrica-de-software/ [R=301,L]
RewriteRule ^agenda\.php$ /servicos/agenda-medica/ [R=301,L]
RewriteRule ^solucoes\.php$ /servicos/solucoes-e-automacao/ [R=301,L]
RewriteRule ^consultoria\.php$ /servicos/consultoria/ [R=301,L]
RewriteRule ^conectividade\.php$ /servicos/conectividade-em-saude/ [R=301,L]
RewriteRule ^escritorio\.php$ /servicos/escritorio-de-processos/ [R=301,L]
RewriteRule ^oracle\.php$ /servicos/oracle-partner/ [R=301,L]
RewriteRule ^prontow\.php$ /produtos/prontow/ [R=301,L]
RewriteRule ^saw\.php$ /produtos/saw/ [R=301,L]
RewriteRule ^portal\.php$ /produtos/portal-operadora/ [R=301,L]
RewriteRule ^gedai\.php$ /produtos/gedai/ [R=301,L]
RewriteRule ^integrador\.php$ /produtos/integrador/ [R=301,L]
RewriteRule ^intranet\.php$ /produtos/intranet/ [R=301,L]
RewriteRule ^biometriafacial\.php$ /produtos/aspect-face/ [R=301,L]
RewriteRule ^xield\.php$ /produtos/xield/ [R=301,L]
RewriteRule ^clientes\.php$ /clientes/ [R=301,L]
RewriteRule ^webinario/?$ /empresa/eventos/ [R=301,L]
RewriteRule ^lgpd/?(index\.html)?$ /conformidade/lgpd/ [R=301,L]
RewriteRule ^lgpd/politicas\.html$ /conformidade/politica-de-privacidade/ [R=301,L]
RewriteRule ^lgpd/cookies\.html$ /conformidade/cookies/ [R=301,L]
RewriteRule ^lgpd/glossario\.html$ /conformidade/glossario-lgpd/ [R=301,L]
```
