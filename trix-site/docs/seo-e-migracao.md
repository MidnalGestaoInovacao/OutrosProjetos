# SEO e migração de domínio (trix.ebaem.com.br → trixti.com.br)

## O que já está implementado
- Títulos e meta descriptions únicos por página, a partir do JSON `#trix-seo` de cada página, + Open Graph/Twitter Cards (imagem padrão quando a página não tem uma), `theme-color`, `geo.*`. **Sem o mu-plugin** essas tags existem só depois do JavaScript (o Google renderiza, mas prévias de WhatsApp/LinkedIn/Facebook e parte dos buscadores não); **com o mu-plugin** saem no HTML do servidor.
- Canonical: o WordPress emite nas páginas; na home (servida pelo template) o canonical vem do mu-plugin no servidor e do JavaScript no navegador. Sitemap nativo em `/wp-sitemap.xml`; `robots.txt` virtual do WordPress.
- Dados estruturados (JSON-LD) gerados em tempo de execução com `location.origin`: Organization/LocalBusiness/ProfessionalService (CNPJ, endereço, geo, telefones, redes), WebSite, WebPage/AboutPage/ContactPage/ItemPage, BreadcrumbList, Service (produtos, como software como serviço, e serviços) e FAQPage gerado a partir do FAQ visível da página. Produtos não usam SoftwareApplication porque o Google exige preço ou avaliações reais nesse tipo. Com o mu-plugin ativo, trilha e FAQ saem só no servidor (sem duplicar). **Nenhuma URL absoluta do domínio é gravada no conteúdo**, então a migração de domínio não quebra SEO nem links.
- Links internos relativos (`/produtos/saw/`), slugs semânticos em português, hierarquia de páginas (`/servicos/…`, `/produtos/…`, `/conformidade/…`), H1 único, headings hierárquicos, alt em imagens, lazy loading, fontes com `display=swap`.
- Idioma `pt_BR`, título do site e tagline definidos.

## Antes de liberar para indexação (staging)
- Instalar `wordpress/mu-plugins/trix-site-helpers.php` (tags de SEO no servidor).
- Se trix.ebaem.com.br for só homologação, ligar **"Desencorajar os mecanismos de busca"** até o go-live no domínio definitivo; se já for o endereço público, manter indexável.
- A home é servida pelo template `home` (sem página `/home/` duplicada); **não** definir página inicial estática.
- O post de exemplo "Hello world!" foi para a lixeira (remove os sitemaps de posts e de categoria vazios). O mu-plugin também remove o sitemap de autores, redireciona `/author/…` e `?author=N` antes do WordPress e tira o autor do oEmbed (evita expor o login do administrador).

## Ao migrar para trixti.com.br
1. **Trocar o endereço do site** em Configurações → Geral (WordPress e URL do site) ou via `wp search-replace` (as páginas usam links relativos; só a biblioteca de mídia tem URLs absolutas do domínio antigo, que o search-replace corrige).
2. Publicar os **redirecionamentos 301** abaixo no servidor antigo/novo (Apache `.htaccess` ou plugin *Redirection*).
3. Reenviar o sitemap no Google Search Console (`https://www.trixti.com.br/wp-sitemap.xml`) e usar a ferramenta "Alteração de endereço".
4. Manter a home no template `home` (não definir página inicial estática).
5. Definir o ícone do site (Aparência → Personalizar → Identidade do site) com o logotipo quadrado `trix-logo-512.png` (512×512, já na biblioteca de mídia): o WordPress passa a emitir `<link rel="icon">` no servidor e a responder `/favicon.ico`. O mesmo arquivo é o `logo` da Organization no JSON-LD (mínimo do Google: 112×112).
6. Opcional: instalar Yoast SEO ou Rank Math para gerenciar metas pelo painel; os títulos/descrições atuais servem de base.

## Mapa de redirecionamentos (URLs antigas → novas)
| Antiga | Nova |
|---|---|
| `/index.php` | `/` (o WordPress já redireciona; não precisa de regra) |
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
| `/lgpd/index.html#ouvidoria` | cai em `/conformidade/lgpd/` (o fragmento `#…` não chega ao servidor; a página tem link para o Canal de Compliance) |
| `/lgpd/TRIX - Código de Conduta da Empresa.pdf` | `/conformidade/codigo-de-conduta/` |

### Exemplo `.htaccess`
Coloque as regras **antes** do bloco `# BEGIN WordPress`. Não crie regra para `/index.php`: o WordPress já redireciona para `/`, e uma regra `^index\.php$` casaria também com a reescrita interna do WordPress, colocando o site em loop.
```
RewriteEngine On
# host antigo e variações -> endereço definitivo (necessário para a ferramenta "Alteração de endereço")
RewriteCond %{HTTP_HOST} ^trix\.ebaem\.com\.br$ [NC,OR]
RewriteCond %{HTTP_HOST} ^trixti\.com\.br$ [NC]
RewriteRule ^(.*)$ https://www.trixti.com.br/$1 [R=301,L]
RewriteRule ^lgpd/TRIX.*Conduta.*\.pdf$ /conformidade/codigo-de-conduta/ [R=301,L,NC]
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
