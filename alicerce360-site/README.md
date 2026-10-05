# Site Alicerce360 — www.alicerce360.com.br

Site institucional e comercial do **Alicerce360**, a intranet para WordPress da **EBAEM** (fabricante, licenciadora e primeira cliente). Publicado no WordPress do domínio pelo MCP do plugin *Easy MCP AI*, a partir das fontes deste diretório.

## O que tem

| Área | Páginas |
|---|---|
| Produto | Início, A intranet (35 módulos, roadmap, segurança), Tour de telas (8 protótipos interativos), Planos e preços (simulador e comparativo), Hospedagem (site, e-mail, backup, antivírus, SSL) |
| Relacionamento | Clientes e programa de indicação, Área do Cliente, Sobre (EBAEM), Contato |
| Confiança | Central de Confiança, Política de Privacidade (com Aviso de Privacidade), Política de Cookies, LGPD e Portal do Titular, Compliance e Canal de Integridade, Política de Comercialização, Termos de Uso, ESG, Acessibilidade, 404 |
| Matérias | Listagem `/materias/` (busca, filtro por categoria, destaque) e 10 matérias publicadas como posts do WordPress, com capa, sumário, compartilhamento e relacionadas |

Recursos em todas as páginas: aviso de privacidade/cookies com preferências por categoria (estilo CookieYes, com Consent Mode), **VLibras**, painel de acessibilidade (texto, contraste, fonte legível, régua, leitura em voz alta, pausar animações, atalhos Alt+1…4), **português, inglês e espanhol**, botão de contato no canto inferior direito (WhatsApp, e-mail, telefone, mapa, redes, assistente), mascote **Ali** que reage à rolagem e assistente com base de conhecimento (pronto para **FreeLLMAPI**).

Interações: camadas animadas (vista explodida do produto), identidade oculta até o hover (holofote e cartões de clientes), personagem que reage ao scroll, papel amassado controlado por **rastreamento de mãos** (MediaPipe, câmera opcional e local), papel com **paralaxe real** (câmera 3D), cena 3D do logo (three.js) na página inicial e um **banner 3D temático** no cabeçalho de cada página interna (casa, camadas, tela, moedas, servidores, escudo, cadeado, balança, livro…) — tudo carregado sob demanda e respeitando “reduzir movimento”.

Menu: **Intranet**, **Planos** (megamenu com Planos da intranet, Simulador, Hospedagem, E-mail, Clientes e Indique e ganhe), **Confiança**, **Matérias**, Sobre e Contato. Todas as páginas, o cabeçalho e o rodapé usam 90% da largura da tela.

## Estrutura

```
site.config.json        contatos, links, preços (fonte única — preços são PROPOSTAS a validar)
src/pages/*.html        conteúdo das páginas em PT (cabeçalho <!--meta {...}--> em cada arquivo)
src/posts/*.html        matérias (posts do WordPress), uma por arquivo
src/covers.json         chamada e infográfico da capa de cada matéria, em PT/EN/ES
src/img/posts/*.webp    capas das matérias: <slug>.webp (PT), <slug>.en.webp e <slug>.es.webp
src/partials/           cabeçalho, rodapé e ícones SVG
src/css/                design system (tons claros e sóbrios com as cores do logo)
src/js/a360.early.js    preferências de acessibilidade e Consent Mode (topo da página)
src/js/a360.ui.js       textos da interface em PT/EN/ES + base de conhecimento do assistente
src/js/a360.core.js     idiomas, acessibilidade, cookies, contato, mascote, assistente, preços, formulários
src/js/a360.three.js    cenas 3D (módulo ES carregado sob demanda)
src/i18n/{en,es}.json   traduções (chave = hash do texto em PT)
tools/build.py          build: prévia estática em dist/ e pacotes WordPress em build/wp/
tools/i18n.py           divide, junta e valida traduções
tools/deploy_wp.py      publica no WordPress pelo MCP (páginas, matérias, categorias, capas e modelos)
tools/covers.mjs        gera as capas (categoria, título, chamada e infográfico) nos 3 idiomas → tools/covers_webp.py converte para WebP
freellmapi/             kit para ligar o assistente ao FreeLLMAPI
docs/COMPONENTES.md     guia de componentes para editar páginas
```

## Fluxo de trabalho

```bash
pip install beautifulsoup4 pillow           # dependências do build
python3 tools/build.py --check              # valida as páginas
python3 tools/build.py                      # gera dist/ (prévia) e build/wp/
cd dist && python3 -m http.server 8765      # abre http://localhost:8765

# traduções: textos novos/alterados aparecem em build/i18n/missing.{en,es}.json
python3 tools/i18n.py split 1               # cria build/i18n/lote-1.json para traduzir
#   salve as traduções em src/i18n/parts/{en,es}.N.json e então:
python3 tools/i18n.py merge && python3 tools/i18n.py check

# publicar (a chave MCP nunca entra no repositório)
export WPMCP_KEY=wpmcp_...                  # ou WPMCP_KEY_FILE=/caminho/arquivo
python3 tools/deploy_wp.py                  # tudo
python3 tools/deploy_wp.py --only pages     # só páginas (também: media, posts, css, chrome, templates, settings)

# nova matéria: crie src/posts/NN-slug.html, traduza (i18n acima), descreva a capa em src/covers.json e gere-a
npm i playwright && node tools/covers.mjs slug-da-materia && python3 tools/covers_webp.py
```

### Como funciona no WordPress

- **CSS**: vai para *Aparência → Personalizar → CSS adicional*.
- **Cabeçalho e rodapé**: partes de modelo `header` e `footer` do tema Twenty Twenty-Five (bloco HTML). O JavaScript vai embutido em gzip + base64 para não ser alterado pelos filtros de conteúdo do WordPress.
- **Página inicial**: modelo `home` exibindo o bloco reutilizável “Alicerce360 · Início”. Página 404: modelo `404` com o bloco “Alicerce360 · 404”.
- **Páginas**: criadas/atualizadas pelo slug, com o modelo `page` simplificado (largura total).
- **Matérias**: posts do WordPress criados/atualizados pelo slug, com categoria, resumo e imagem destacada; os links definitivos ficam em `tools/posts.json`. O modelo `single` mostra categoria, título, data e capa (blocos do WordPress) e o conteúdo da matéria. A listagem `/materias/` também acrescenta sozinha os posts escritos direto no painel do WordPress (via REST).
- Tudo é reversível: o Editor do site permite “Restaurar” modelos, e o conteúdo de exemplo foi para a lixeira.

### Editar textos e preços

- Preços, contatos e links: `site.config.json` → `python3 tools/deploy_wp.py`.
- Textos: edite `src/pages/*.html` seguindo `docs/COMPONENTES.md`. Texto alterado gera nova chave de tradução: enquanto não houver tradução, o site mostra o português nas versões EN/ES.
- Evite emoticons, colchetes e `<` solto nos textos (o WordPress converte). O `--check` avisa.

## Pendências recomendadas

- Validar com a EBAEM os **preços** e as **condições comerciais** propostas (Política de Comercialização, SLA, cancelamento) e os prazos referenciais do Canal de Integridade.
- Confirmar a exibição do nome do Encarregado (DPO) na Política de Privacidade.
- Instalar um plugin de SEO (Yoast ou Rank Math) para meta descrição e Open Graph no `<head>`.
- Opcional: publicar o FreeLLMAPI (`freellmapi/README.md`) e preencher `ai.endpoint`.
