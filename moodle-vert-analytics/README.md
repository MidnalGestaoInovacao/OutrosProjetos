# Moodle Academy Vert Analytics – personalizações (setembro/2026)

Site: https://ead.vert.com.br — Moodle 5.2.3+ com tema **Edwiser RemUI 5.1.1** e Edwiser Page Builder.

Este diretório guarda, sob controle de versão, o que foi aplicado diretamente no site
(via *Administração do site*), para que possa ser reaplicado ou ajustado no futuro.

| Arquivo | Onde é usado no Moodle |
|---|---|
| `customcss.css` | *Administração do site → Aparência → Temas → Edwiser RemUI → Custom CSS* (`theme_remui \| customcss`). O conteúdo do arquivo é o valor completo do campo (inclui as regras que já existiam antes). |
| `tours/*.json` | *Administração do site → Aparência → Passeios do usuário → Importar tour*. Um arquivo por tour. |
| `tours_spec.json` | Fonte (legível) usada para gerar os arquivos de importação. |
| `additionalhtmlfooter.html` | *Administração do site → Aparência → HTML adicional → Antes do fechamento do BODY* (`additionalhtmlfooter`). Script do botão "?" que reinicia o tour da página e correção do texto "Mark all read". |

## 1. Cor verde nos elementos que eram azuis (tela inicial)

Verde da marca: `#00DE8F` (o mesmo do botão "Explorar Treinamentos"); texto sobre o verde em `#101B3B`.

* Botão/seletor **"Cursos e Formações"** (`.catselector-menu`): fundo verde, texto azul-marinho, hover em `#00C67F`.
* Seletor **"Modo de edição"** (switch da barra superior, visível só para quem pode editar): trilho verde
  (45 % de opacidade quando desligado, sólido quando ligado), borda e foco em verde.

O azul restante do tema (`#0051f9`) é a cor primária definida no *Customizer* do RemUI
(paleta "pallet-1") e não foi alterado — só os elementos pedidos.

## 2. Ícones do menu do usuário

O RemUI aplica `color: #fff` a todo `.edw-icon` dentro da navbar, o que deixava os ícones do menu do
avatar (Perfil, Notas, Calendário, Arquivos privados, Relatórios, Preferências, Sair) brancos sobre
fundo branco. Regra adicionada: `.navbar #user-action-menu .edw-icon { color: #00de8f }`.

## 3. Idioma forçado: Português (Brasil)

*Administração do site → Idioma → Configurações de idioma*:

| Configuração | Valor |
|---|---|
| Idioma padrão (`lang`) | `pt_br` |
| Detecção automática de idioma (`autolang`) | desligada |
| Detecção de idioma na criação de usuários (`autolangusercreation`) | desligada |
| Exibir menu de idiomas (`langmenu`) | desligado |
| Idiomas no menu (`langlist`) | `pt_br` |

Além disso, a preferência de idioma de **todos os 8 usuários existentes** foi definida para `pt_br`
(*Preferências → Idioma preferido*), pois o Moodle respeita a preferência individual do usuário.
Usuários novos herdam o idioma padrão do site.

## 4. Largura das páginas principais (90 %)

O tema limita o conteúdo das páginas "limitedwidth" (Painel, Meus cursos, Perfil, página do curso em
formato Tópicos etc.) a **830 px**, e ainda envolve o conteúdo em um `.container` de no máximo 1320 px.
As regras adicionadas fazem `#page.drawers .main-inner` (e cabeçalho, abas e rodapé fixo
correspondentes) ocuparem `max-width: 90%` do espaço horizontal em telas ≥ 992 px, e 100 % em telas
menores.

## 5. Tours guiados (Passeios do usuário)

Cinco tours em pt-BR, com o logo da Vert Analytics no topo de cada passo (sobre fundo azul-marinho,
já que o logo é branco/verde) e botão "Próximo" em verde:

| Tour | Passos | Página (pathmatch) |
|---|---|---|
| Vert Academy – Página inicial | 12 | `FRONTPAGE` (somente a página inicial) |
| Vert Academy – Painel | 8 | `/my/%` + filtro CSS `body.pagelayout-mydashboard` (não dispara em Meus cursos) |
| Vert Academy – Meus cursos | 7 | `/my/courses.php%` |
| Vert Academy – Página do curso | 9 | `/course/view.php%` (formatos Tiles e Tópicos; passos sem alvo são pulados) |
| Vert Academy – Notas | 5 | `/grade/report/overview/index.php%` |

Observações:

* O Moodle compara o *pathmatch* com a URL de forma exata; o `%` é o curinga necessário quando a URL
  tem `index.php` ou parâmetros.
* Os tours ficam no topo da ordem (antes dos tours padrão do Moodle, que só valem para o tema Boost
  ou para professores/administradores) e valem para todos os papéis.
* O tour aparece automaticamente na primeira visita à página e pode ser reexecutado pelo botão
  **"?"** ao lado do título da página (o rodapé padrão, onde o Moodle mostra o link "Reiniciar tour",
  está oculto pelo CSS do site). O botão de saída em cada passo é "Encerrar tour". Para editar textos
  ou alvos: *Administração do site → Aparência → Passeios do usuário*.
* Passos sem alvo (boas-vindas e encerramento) são fixados no centro da tela (CSS), pois o Moodle os
  posicionava no topo do documento e eles ficavam fora da área visível após rolar a página.

## Como reaplicar / reverter

* CSS: cole o conteúdo de `customcss.css` no campo *Custom CSS* do tema (ou remova o bloco
  "Vert Analytics – ajustes" para reverter) e salve; o cache do tema é limpo automaticamente.
* Tours: importe os arquivos de `tours/` (ou desative/exclua os tours na lista de Passeios do usuário).
* Idioma: reverter as cinco configurações da tabela acima.

## Rodada 2 (mesmo dia)

### Textos em inglês
Após a mudança de idioma, sessões já abertas continuam com o idioma carregado no login: basta
**sair e entrar novamente** para ver tudo em português. Verificação feita em todas as páginas
principais renderizadas em pt_br (inclusive conteúdo carregado por AJAX): nenhum texto em inglês
visível. Ajustes feitos para os poucos textos fixos do tema Edwiser:

* Rótulos dos cartões de curso do tema: `theme_remui | showlessontextinput` = "Aulas" e
  `theme_remui | showenrolledtextinput` = "Inscritos" (antes "Lessons"/"Enrolled").
* "Mark all read" (popover de notificações) é texto fixo no template do tema; o script do rodapé o
  substitui pelo título já traduzido ("Marcar tudo como lido").

### Cinco cursos por linha
A grade de cartões de curso (`.edw-course-card-grid`, usada em Meus cursos) passa a ter 5 colunas em
telas ≥ 1200 px (4 entre 992 e 1199 px, 3 entre 768 e 991 px; abaixo disso o padrão do tema).

### Ícone antes dos títulos + botão "?"
* Os títulos das páginas principais (`#page-header h1.header-heading`) recebem um ícone Font Awesome
  em verde antes do texto (Painel, Meus cursos, Perfil, Calendário, Arquivos privados, Preferências,
  Relatórios, Cursos/categorias, Notificações, Emblemas, Notas) e ficam alinhados à esquerda com o
  conteúdo. A página do curso mantém o banner (sem ícone).
* O script do rodapé insere o botão "?" após o título (e após "Academy Vert Analytics" na página
  inicial) sempre que a página tem um tour; o clique usa a ação nativa do Moodle
  (`tool_usertours/resetpagetour`), que reinicia o tour imediatamente.

### Formato dos cursos
Curso "EXEMPLO" (id 3) alterado para **Formatos de curso Edwiser → Layout de lista**
(`format=remuiformat`, `remuicourseformat=1`); o curso "Integração Corporativa" (id 2) já estava
nesse formato. O tour "Página do curso" foi reescrito para esse layout (cabeçalho com progresso e
botão Continuar, índice, abas, seção de apresentação, fórum de avisos, lista de seções).

### Login com Google e Microsoft
* *Administração do site → Servidor → Serviços OAuth 2*: serviços **Google** (id 1) e **Microsoft**
  (id 2) criados a partir dos modelos do Moodle, exibidos "Na página de login e em serviços internos".
* *Administração do site → Plugins → Autenticação*: **OAuth 2** habilitado.
* CSS: os botões ficam empilhados abaixo de "Acessar", com o ícone de cada provedor; "Google" em
  vermelho Google (`#DB4437`) e "Microsoft" no cinza-escuro padrão da Microsoft (`#2F2F2F`).
* **Pendência:** o *Client ID* e o *Client secret* de cada serviço estão com o valor
  `PENDENTE-...`. Até que as credenciais oficiais sejam informadas (editar o serviço na página de
  Serviços OAuth 2), o clique nos botões leva a um erro do provedor (cliente inválido). No console
  do Google/Microsoft, a URL de redirecionamento a cadastrar é
  `https://ead.vert.com.br/admin/oauth2callback.php`.

### Observações finais
* Durante os testes automatizados, os tours foram executados com a conta `vertanalyticsadmin`; por
  isso, nessa conta eles não abrem sozinhos nas páginas já visitadas — basta clicar no botão "?".
  Para os demais usuários os tours abrem automaticamente na primeira visita a cada página.
* Ferramentas usadas para gerar/importar os tours e validar seletores ficaram fora do repositório
  (scripts de sessão); os artefatos versionados aqui são suficientes para reaplicar tudo pela
  interface de administração.

## Rodada 3 (mesmo dia)

### Estatísticas do curso em uma linha
O cartão de estatísticas da página do curso (`#wdm_course-stats`: Alunos matriculados / Alunos que
concluíram / Em progresso / Ainda não iniciado) usava, nas páginas "limitedwidth", a variante móvel do
tema (2 colunas). O CSS passa a exibir os 4 blocos em uma única linha, com larguras iguais
(`grid-template-columns: repeat(4, 1fr)`), ocupando 100 % do cartão; abaixo de 992 px volta a 2
colunas e abaixo de 576 px a 1 coluna. (Esse cartão só aparece para professores/administradores.)

### Largura da Administração do site
As páginas de administração (`pagelayout-admin`) e outras sem a classe `limitedwidth` mantinham o
`.container` do tema em 1320 px. Regra adicionada para esse `.container` usar `max-width: 90%`, como
as demais páginas (conteúdo com 1728 px em tela de 1920 px).

### Balão do tour mais compacto
Cabeçalho, corpo e rodapé do balão com menos preenchimento e logo um pouco menor, para o balão caber
acima ou abaixo do elemento destacado em telas de ~900 px de altura; textos dos passos "Abas de
navegação do curso", "Linha do tempo" e "Calendário do mês" encurtados e os dois últimos ancorados no
cabeçalho do bloco.

### Verificação "Router not configured" (avaliação)
* O que é: desde o Moodle 4.5 existe um roteador (`r.php`). Sem configuração do servidor web, o
  Moodle funciona normalmente, mas usa URLs mais longas (`/r.php/api/...`); a verificação fica
  em "Verificar" (aviso, não erro). Teste feito: `/r.php/api/rest/v2/openapi.json` responde 200 e
  `/api/rest/v2/openapi.json` responde 404 do Apache — ou seja, não há reescrita configurada.
* Não é possível configurar pela interface do Moodle: exige editar arquivos no servidor
  (Apache 2.4 / Ubuntu, conforme o cabeçalho HTTP do site). Passos, com acesso SSH/FTP ao servidor:
  1. No diretório raiz do Moodle (onde está `config.php`), criar/editar `.htaccess` com:
     ```
     <IfModule mod_rewrite.c>
         RewriteEngine On
         RewriteBase /
         RewriteCond %{REQUEST_FILENAME} !-f
         RewriteCond %{REQUEST_FILENAME} !-d
         RewriteRule ^(.*)$ r.php/$1 [L]
     </IfModule>
     ```
     O VirtualHost do Apache precisa permitir `.htaccess` (`AllowOverride All` no `<Directory>` do
     Moodle) e o módulo `mod_rewrite` precisa estar ativo (`sudo a2enmod rewrite && sudo systemctl
     reload apache2`). Alternativamente, colocar as mesmas regras dentro do `<Directory>` do
     VirtualHost.
  2. Em `config.php`, antes de `require_once(__DIR__ . '/lib/setup.php');`, adicionar:
     `$CFG->routerconfigured = true;`
  3. Testar `https://ead.vert.com.br/api/rest/v2/openapi.json` (deve responder JSON) e reabrir
     *Administração do site → Notificações* (ou *Relatórios → Verificações do sistema*): o item deve
     passar para OK.
  Se `.htaccess` for ignorado (404 persistindo), o ajuste precisa ser feito no VirtualHost pelo
  administrador do servidor.
