# Moodle Academy Vert Analytics – personalizações (setembro/2026)

Site: https://ead.vert.com.br — Moodle 5.2.3+ com tema **Edwiser RemUI 5.1.1** e Edwiser Page Builder.

Este diretório guarda, sob controle de versão, o que foi aplicado diretamente no site
(via *Administração do site*), para que possa ser reaplicado ou ajustado no futuro.

| Arquivo | Onde é usado no Moodle |
|---|---|
| `customcss.css` | *Administração do site → Aparência → Temas → Edwiser RemUI → Custom CSS* (`theme_remui \| customcss`). O conteúdo do arquivo é o valor completo do campo (inclui as regras que já existiam antes). |
| `tours/*.json` | *Administração do site → Aparência → Passeios do usuário → Importar tour*. Um arquivo por tour. |
| `tours_spec.json` | Fonte (legível) usada para gerar os arquivos de importação. |

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

| Tour | Página (pathmatch) |
|---|---|
| Vert Academy – Página inicial | `FRONTPAGE` (somente a página inicial) |
| Vert Academy – Painel | `/my/` + filtro CSS `body.pagelayout-mydashboard` |
| Vert Academy – Meus cursos | `/my/courses.php` |
| Vert Academy – Página do curso | `/course/view.php` |
| Vert Academy – Notas | `/grade/report/overview/index.php` |

O tour aparece automaticamente na primeira visita à página e pode ser reexecutado pelo link
"Reiniciar tour do usuário nesta página", no rodapé. Para editar textos ou alvos:
*Administração do site → Aparência → Passeios do usuário*.

## Como reaplicar / reverter

* CSS: cole o conteúdo de `customcss.css` no campo *Custom CSS* do tema (ou remova o bloco
  "Vert Analytics – ajustes" para reverter) e salve; o cache do tema é limpo automaticamente.
* Tours: importe os arquivos de `tours/` (ou desative/exclua os tours na lista de Passeios do usuário).
* Idioma: reverter as cinco configurações da tabela acima.
