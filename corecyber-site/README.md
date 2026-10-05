# Site CoreCyber — corecyber.com.br

Site institucional e comercial do **CoreCyber**, a plataforma brasileira de cibersegurança da **EBAEM** (fabricante, licenciadora e primeira cliente). Publicado no WordPress do domínio pelo MCP do plugin *Easy MCP AI*, a partir das fontes deste diretório. Os fatos do produto usados no site estão em `docs/PRODUTO.md` (base: repositório `EbaemCybercore`, plataforma 3.0.197).

## O que tem

| Área | Páginas |
|---|---|
| Produto | Início, Plataforma (módulos, agente, conformidade, roadmap transparente), Tour de telas (9 protótipos interativos), Planos e preços (configurador por faixa de ativos e simulador), Comparativo de mercado, Diagnóstico gratuito |
| Relacionamento | Clientes (identidade oculta até o hover) e programa de indicação, Área do Cliente (acesso à plataforma, suporte, indicação de clientes), Sobre (EBAEM), Contato (demonstração e parcerias) |
| Conteúdos | 20 matérias (produto, diferenciais, conformidade, guias práticos e bastidores) no formato das matérias da EBAEM, com capa-infográfico em PT/EN/ES, filtro por categoria, sumário, compartilhamento e “continue lendo” |
| Confiança | Central de Confiança, Política de Privacidade (com Aviso de Privacidade), Política de Cookies, LGPD e Portal do Titular, Compliance e Canal de Integridade, Política de Comercialização, Termos de Uso, ESG, Acessibilidade, Divulgação Responsável de Vulnerabilidades, 404 |

Recursos em todas as páginas: aviso de privacidade/cookies com preferências por categoria (estilo CookieYes, com Consent Mode), **VLibras**, painel de acessibilidade (texto, contraste, fonte legível, régua, leitura em voz alta, pausar animações, atalhos Alt+1…4), **português, inglês e espanhol**, botão de contato no canto inferior direito (WhatsApp, e-mail, telefone, mapa, redes, assistente), mascote **Cy** (sentinela que reage à rolagem) e assistente com base de conhecimento (pronto para **FreeLLMAPI**).

Interações:
- **Camadas animadas** — as 5 camadas de defesa se separam com a rolagem (vista explodida).
- **Identidade oculta até o hover** — holofote sobre a EBAEM e cartões de clientes mascarados (EBAEM, ASSBAN, MOORE, MIDNAL) que se abrem a partir do ponteiro.
- **Personagem que reage ao scroll** — o Cy corre, olha para cima/baixo, sua quando a rolagem é rápida, comenta seções, dorme quando você para.
- **Papel amassado com rastreamento de mãos** (MediaPipe, câmera opcional e local) — o relatório de pentest vira laudo com QR; e **papel com paralaxe real** (câmera 3D).
- **3D integrado** (three.js) — o “C” octogonal do logo com anéis e um escudo que intercepta ameaças; globo com ataques sendo bloqueados no Brasil.
- **Teste real de e-mail/DNS** — SPF, DMARC, DNSSEC, CAA, MTA-STS e TLS-RPT consultados direto do navegador em DNS público (DoH).
- Protótipos com demonstrações: varredura, filtro por severidade, ciclo de correção, correção remota, aprovação da Declaração de Aplicabilidade, descomissionamento, caso de incidente, conferência de QR, alertas no celular.

Tudo é carregado sob demanda e respeita “reduzir movimento”.

## Estrutura

```
site.config.json        contatos, links, preços, modo de links do WordPress (fonte única — preços são PROPOSTAS a validar)
src/pages/*.html        conteúdo das páginas em PT (cabeçalho <!--meta {...}--> em cada arquivo)
src/posts/*.html        matérias (AAAA-MM-DD-slug.html; guia de redação em docs/MATERIAS.md)
src/img/posts/          capas das matérias (<slug>.<pt|en|es>.webp), geradas por tools/covers.py
src/partials/           cabeçalho, rodapé e ícones SVG
src/css/                design system (tons claros e sóbrios com as cores do logo: #002141, #005783, #0090AD, #19C3D6)
src/js/cc.early.js      preferências de acessibilidade e Consent Mode (topo da página)
src/js/cc.ui.js         textos da interface em PT/EN/ES + base de conhecimento do assistente Cy
src/js/cc.core.js       idiomas, acessibilidade, cookies, contato, mascote, assistente, preços, simulador, DNS, formulários, demos
src/js/cc.three.js      cenas 3D (módulo ES carregado sob demanda)
src/i18n/{en,es}.json   traduções (chave = hash do texto em PT)
tools/build.py          build: prévia estática em dist/ e pacotes WordPress em build/wp/
tools/i18n.py           divide, junta e valida traduções
tools/llm_i18n.py       traduz textos novos com o FreeLLMAPI (opcional)
tools/covers.py         gera as capas-infográfico das matérias (Playwright + Pillow; só refaz o que mudou)
tools/deploy_wp.py      publica no WordPress pelo MCP
freellmapi/             kit para ligar o assistente Cy (e as traduções) ao FreeLLMAPI
docs/COMPONENTES.md     guia de componentes para editar páginas
docs/PRODUTO.md         fatos do produto (o que está disponível, em evolução e no roadmap)
docs/MATERIAS.md        formato e regras das matérias
docs/GLOSSARIO_I18N.md  regras e glossário de tradução
```

## Fluxo de trabalho

```bash
pip install beautifulsoup4 pillow           # dependências do build
python3 tools/build.py --check              # valida as páginas
python3 tools/build.py                      # gera dist/ (prévia) e build/wp/
cd dist && python3 -m http.server 8765      # abre http://localhost:8765

# traduções: textos novos/alterados aparecem em build/i18n/missing.{en,es}.json
python3 tools/i18n.py split 1               # cria build/i18n/lote-1.json para traduzir
#   salve as traduções em src/i18n/parts/{en,es}.N.json (ou use tools/llm_i18n.py) e então:
python3 tools/i18n.py merge && python3 tools/i18n.py check

# publicar (a chave MCP nunca entra no repositório)
export WPMCP_KEY=wpmcp_...                  # ou WPMCP_KEY_FILE=/caminho/arquivo
python3 tools/deploy_wp.py                  # tudo
python3 tools/deploy_wp.py --only pages     # só páginas (também: media, css, chrome, templates, posts, settings)

# matérias: escreva src/posts/AAAA-MM-DD-slug.html (docs/MATERIAS.md), traduza e gere as capas
python3 tools/covers.py                     # capas PT (e EN/ES quando os textos da capa estiverem traduzidos)
python3 tools/deploy_wp.py --only posts     # envia capas novas e cria/atualiza os posts
```

### Como funciona no WordPress

- **Links**: o WordPress de corecyber.com.br usa links permanentes simples (`?page_id=`). Com `wp.permalinks = "plain"` no `site.config.json`, o build troca `/slug/` por `/?pagename=slug` (mesmo padrão do ebaem.com.br). Se ativar “Nome do post” em *Configurações → Links permanentes*, mude para `"pretty"` e publique de novo.
- **CSS**: vai para *Aparência → Personalizar → CSS adicional*.
- **Cabeçalho e rodapé**: partes de modelo `header` e `footer` do tema Twenty Twenty-Five (bloco HTML). O JavaScript vai embutido em gzip + base64 para não ser alterado pelos filtros de conteúdo do WordPress.
- **Página inicial**: modelo `home` exibindo o bloco reutilizável “CoreCyber · Início”. Página 404: modelo `404` com o bloco “CoreCyber · 404”.
- **Matérias**: viram posts do WordPress (categoria, tags, resumo, data às 8h e imagem de destaque = capa PT), exibidos pelo modelo `single` simplificado. Os links `/conteudos/slug/` viram `/?name=slug` com links simples. A página `/conteudos/` e a seção da página inicial são geradas pelo build.
- **Páginas**: criadas/atualizadas pelo slug, com o modelo `page` simplificado (largura total).
- Tudo é reversível: o Editor do site permite “Restaurar” modelos, e o conteúdo de exemplo foi para a lixeira.

### Editar textos e preços

- Preços, contatos e links: `site.config.json` → `python3 tools/deploy_wp.py`.
- Textos: edite `src/pages/*.html` seguindo `docs/COMPONENTES.md`. Texto alterado gera nova chave de tradução: enquanto não houver tradução, o site mostra o português nas versões EN/ES.
- Evite emoticons, colchetes e `<` solto nos textos (o WordPress converte). O `--check` avisa.
- **Honestidade de produto**: recursos do roadmap (SIEM, EDR completo, NDR, assistente de IA na plataforma, iOS, macOS) aparecem sempre com o selo “Roadmap”. Atualize `docs/PRODUTO.md` e os selos quando forem lançados.

## Pendências recomendadas

- Validar com a EBAEM os **preços**, a definição de **ativo**, o **SLA de 99,5 %**, as **metas de suporte** e as demais condições comerciais propostas (Política de Comercialização), além dos prazos do Canal de Integridade e da Divulgação Responsável.
- Confirmar o status de **ASSBAN, MOORE e MIDNAL** (hoje exibidos como “Em implantação”) e a autorização para exibir os nomes; enviar logotipos oficiais, se autorizados.
- Criar as caixas de e-mail do domínio **@corecyber.com.br** usadas no site (contato, suporte, lgpd, ouvidoria e seguranca) — ou trocar em `site.config.json`.
- Confirmar a exibição do nome do Encarregado (DPO) na Política de Privacidade.
- Ativar links permanentes “Nome do post” no WordPress (opcional, melhora SEO) e instalar um plugin de SEO (Yoast ou Rank Math) para meta descrição, Open Graph (`og.jpg` já está na mídia) e ícone do site.
- Opcional: publicar o FreeLLMAPI (`freellmapi/README.md`) e preencher `ai.endpoint`.
