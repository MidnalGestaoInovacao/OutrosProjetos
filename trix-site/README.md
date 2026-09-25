# Novo site Trix Tecnologia Inteligente (WordPress em trix.ebaem.com.br)

Reconstrução completa do site trixti.com.br em WordPress (tema de blocos Twenty Twenty-Five), publicada via **Easy MCP AI**, com identidade visual original (amarelo `#F8CD4B`, grafite `#424346`, Open Sans, títulos em caixa alta, botões quadrados), conteúdo ampliado e argumentado, elementos 3D interativos, formulários com captcha matemático, central de conformidade, cookies, acessibilidade, Libras, contatos flutuantes, megamenu e SEO completo.

## Estrutura
```
trix-site/
├── src/
│   ├── build.py              # gera dist/ (blocos, templates, páginas, theme.json)
│   ├── deploy.py             # publica dist/ no WordPress via MCP (idempotente)
│   ├── wpmcp.py              # cliente Easy MCP AI resiliente (retries/backoff)
│   ├── nav.py                # dados institucionais + estrutura do megamenu
│   ├── icons.py              # ícones SVG inline
│   ├── assets/trix.css       # design system (identidade visual)
│   ├── assets/trix.js        # megamenu, SEO/JSON-LD, cookies, acessibilidade, VLibras, formulários, 3D (Three.js)
│   ├── content/*.py          # conteúdo de todas as páginas (34)
│   ├── content/original/     # textos integrais originais (política, glossário, cookies)
│   ├── preview.py            # gera preview/*.html (páginas completas para testes locais)
│   ├── device_matrix.py      # testes em 12 dispositivos emulados (capturas + métricas)
│   ├── shoot_heroes.py       # capturas do banner de todas as páginas (desktop e celular)
│   ├── live_check.py         # verificação renderizada de todas as páginas (site ou prévias)
│   ├── live_http_check.py    # verificação HTTP rápida do site publicado (versão, logo, 3D, canonical, sitemap)
│   └── tools_extract_original.py
├── dist/                     # artefatos gerados (o que foi publicado)
├── docs/
│   ├── inventario-conteudo-original.md   # varredura completa do site antigo
│   ├── codigo-de-conduta-original.txt    # PDF do Código de Conduta convertido
│   ├── dados-empresa.md                  # dados públicos (CNPJ, endereços, redes)
│   └── seo-e-migracao.md                 # SEO implementado e mapa de redirecionamentos 301
├── media_map.json            # imagens originais -> IDs/URLs na biblioteca de mídia
└── deploy-state.json         # IDs dos blocos/páginas criados no WordPress
```

## Como funciona no WordPress
- **4 blocos reutilizáveis (padrões sincronizados)**: `Trix • Estilos`, `Trix • Cabeçalho (megamenu)`, `Trix • Rodapé`, `Trix • Widgets`. Editáveis em *Aparência → Editor → Padrões*.
- **Templates do tema** (`page`, `page-no-title`, `home`, `index`, `archive`, `search`, `single`, `404`) foram substituídos por: Estilos + Cabeçalho + `<main>` (conteúdo da página) + Rodapé + Widgets. O template `home` contém o conteúdo completo da página inicial (editável em *Aparência → Editor → Modelos → Página inicial do blog* ou pelo build).
- **Páginas**: cada página é um bloco HTML com um JSON `#trix-seo` (título, descrição, breadcrumb, FAQ, tipo) usado pelo script para gerar `<meta>`, Open Graph e JSON-LD em tempo de execução.
- **Estilos globais (theme.json)**: paleta Trix, Open Sans e largura de conteúdo.

## Recursos implementados
1. **Identidade visual** original preservada e modernizada (variáveis CSS em `trix.css`).
2. **3D interativo** (Three.js via cdnjs, com CDN de reserva jsDelivr): todo banner tem, no lado oposto ao texto, uma cena temática — rede, globo, cubos, escudo, pulso cardíaco, rosto (biometria), documentos, engrenagens, camadas, órbita, nuvem e rede neural (mapa em `SCENES`, `src/build.py`) — com arrastar para girar (mouse e toque), inércia, parallax, enquadramento automático, pausa fora da tela, qualidade reduzida no celular e ícone estático de reserva sem WebGL; imagens do produto flutuam sobre a cena quando existem. O banner é dividido em título, visual e texto: no desktop o 3D fica na coluna oposta ao texto; no celular em pé ele aparece logo abaixo do título, ainda na primeira tela; no celular deitado volta para a coluna da direita. Respeita `prefers-reduced-motion` e o botão "Pausar animações".
3. **Conteúdo ampliado**: 34 páginas (empresa, eventos, clientes, 8 serviços, 8 produtos, 10 páginas de conformidade, contato e 2 canais), com argumentação orientada a benefícios, provas sociais reais (2009, +35 operadoras, prêmio Unimed, OPN, LGPD), FAQs e CTAs.
4. **Formulários com captcha matemático** + honeypot + tempo mínimo: Contato (com consentimento), Canal LGPD e Canal de Compliance (aviso de privacidade informativo; opção de relato sem identificação — nome, e-mail e telefone não são enviados). Envio via endpoint nativo de comentários do WordPress (`wp-comments-post.php`) — as mensagens ficam em **Comentários → Pendentes**; com o mu-plugin instalado, nunca são publicadas, não guardam IP e são notificadas à caixa do canal. Fallback por e-mail se o envio falhar.
5. **Central de Conformidade**: missão, visão, valores, política integrada e links para as políticas integrais de LGPD/Privacidade, Compliance + Código de Conduta (texto integral do PDF), Segurança da Informação, ESG e IA, cookies e glossário.
6. **Acessibilidade**: painel (tamanho de texto, alto contraste, escala de cinza, fonte legível, espaçamento, destacar links, cursor grande, guia de leitura, pausar animações, Libras), skip link, foco visível, ARIA no megamenu; botão de acessibilidade no cabeçalho em telas com menu hambúrguer (<1100px) e flutuante no desktop; **VLibras** (gov.br) carregado com consentimento funcional ou ao clicar em Libras; **cookies** estilo CookieYes com categorias, Google Consent Mode v2 e botão de reabertura; **contatos flutuantes** (WhatsApp, e-mail, telefone, Instagram, LinkedIn, Facebook), que no celular e no tablet aparecem depois da primeira rolagem para não cobrir o banner.
7. **Megamenu** com ícones, descrições e card em destaque em cada grupo; drawer acessível no mobile.
8. **SEO**: ver `docs/seo-e-migracao.md`.

## Passos manuais OBRIGATÓRIOS no painel/servidor (não são possíveis pela chave MCP)
1. **Instalar o mu-plugin** `wordpress/mu-plugins/trix-site-helpers.php` em `wp-content/mu-plugins/` (via FTP/cPanel; sem acesso a arquivos, o mesmo código pode ir num plugin de snippets, executando em todo o site). A chave MCP não tem ferramenta para instalar plugins ou gravar arquivos. Ele emite no servidor `<title>`, meta description, Open Graph, canonical e JSON-LD (essencial para prévias no WhatsApp/LinkedIn e para rastreadores sem JavaScript) e protege os formulários: mensagens dos canais nunca são aprovadas/publicadas, IP e user-agent não são gravados nos relatos, notificações vão para dpo@ / ouvidoria@ / falecom@ e as mensagens ficam fora da API pública e dos feeds.
2. **Indexação de trix.ebaem.com.br**: se este endereço for apenas homologação, marque *Configurações → Leitura → "Desencorajar os mecanismos de busca"* até a migração; se ele já for o endereço público do site, mantenha indexável e, na migração, use os redirecionamentos 301 e a ferramenta "Alteração de endereço" do Search Console.
3. **Não** definir página inicial estática: a home é servida pelo template **"Página inicial do blog"** (`home`), com o mesmo conteúdo gerado pelo build. A antiga página `/home/` foi para a lixeira para não haver conteúdo duplicado.

## Passos manuais recomendados no painel
- *Configurações → Discussão*: marcar **"O comentário deve ser aprovado manualmente"** e desmarcar **"O autor deve ter um comentário aprovado anteriormente"**, para que nenhuma mensagem de formulário seja publicada automaticamente. Nunca aprovar os comentários-mensagens (apenas ler/responder e depois marcar como lido/lixeira). Trocar o e-mail do administrador para `falecom@trixti.com.br`.
- *Aparência → Personalizar → Identidade do site*: ícone do site com `trix-logo-512.png` (512×512, já na biblioteca de mídia, id 350). Assim o WordPress emite `<link rel="icon">` e `/favicon.ico` no servidor; o mesmo arquivo é o logo da Organization no JSON-LD.
- Opcional: instalar um plugin de formulários (Fluent Forms/WPForms) e apontar `formEndpoint` em `TRIX_CONFIG`; instalar Yoast/Rank Math; configurar GA4 (`ga4` em `nav.py` → rebuild → deploy).

## Reprocessar
```bash
export WPMCP_KEY=wpmcp_...            # chave Easy MCP AI (não versionada)
python3 src/build.py --media media_map.json
python3 src/deploy.py                 # tudo, ou --only blocks|styles|pages|templates|cleanup
```

## Testes
```bash
python3 src/preview.py && (cd preview && python3 -m http.server 8765 &)   # prévias locais
python3 src/device_matrix.py                   # 12 dispositivos x 7 páginas -> screens/devices/
python3 src/live_check.py http://127.0.0.1:8765 screens/preview-check   # 34 páginas renderizadas (desktop e celular)
python3 src/live_http_check.py                 # site publicado: versão, logo, cena 3D, canonical, 404, robots e sitemap
```

## Observações sobre o ambiente de publicação
- O servidor de trix.ebaem.com.br responde requisições PHP lentamente (vários segundos) e o túnel de rede desta sessão encerra conexões após ~11 s sem resposta. Por isso `wpmcp.py` reenvia cada chamada com pausas crescentes e só repete quando uma sonda leve (`GET /?trix_health=1`) volta a responder. Envios grandes ou concorrentes derrubam a taxa de sucesso — publique sempre em sequência.
- `deploy.py` é idempotente: guarda IDs e um hash do conteúdo de cada página em `deploy-state.json` e só reenvia o que mudou (`--create-only` cria apenas as páginas que faltam). `--only cleanup` remove páginas de teste/duplicadas e blocos duplicados.
- As imagens são enviadas em base64 já otimizadas (`upload_media.py` no diretório de trabalho da sessão; manifesto em `media_manifest.py`); enquanto uma imagem não está na biblioteca, o build usa a URL original em trixti.com.br como reserva.

## Auditoria multiagente (setembro de 2026)
Uma auditoria automatizada em 8 dimensões (completude do conteúdo, texto e fatos, SEO, acessibilidade, bugs de front-end, LGPD/jurídico, segurança e build/deploy) gerou 130 achados brutos (`docs/auditoria-achados-brutos.json`). A etapa de verificação adversarial (3 verificadores por achado) foi interrompida após as primeiras dezenas de veredictos por tempo; todos os achados foram lidos e triados manualmente antes das correções abaixo. As correções aplicadas incluem: contraste de cores (WCAG AA), anéis de foco, widgets ocultos fora da ordem de tabulação, modal de cookies com Esc/prisão de foco, megamenu sem corte, `blockGap` do tema neutralizado, captcha acessível, anonimato efetivo nos relatos, prazos do art. 19 da LGPD, políticas novas marcadas como minuta (v1.0) para aprovação e revisão jurídica, adendo informativo à Política de Privacidade de 2020, fidelidade do Código de Conduta ao PDF (com nota editorial sobre a Lei 14.133/2021), bases legais separadas para dados sensíveis, títulos ≤60 e descrições ≤155 caracteres, FAQ visível sempre que houver FAQPage, URLs de mídia relativas ao domínio, remoção de `offers` falsos no JSON-LD, contador com decimais, VLibras somente com consentimento funcional ou por ação do usuário, e limpeza de deploy restrita a testes e duplicatas.

**Itens que exigem decisão/validação da Trix (não resolvidos por código):** aprovação formal e revisão jurídica das políticas novas (Segurança da Informação, ESG, IA e Política Integrada) e da Política de Privacidade de 2020; confirmação de datas históricas e da grafia "Sousa/Souza"; SLAs de atendimento; e adoção de um plugin de formulários com validação anti-spam no servidor.
