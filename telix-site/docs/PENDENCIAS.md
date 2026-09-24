# Itens a confirmar com a Télix (antes de apontar o domínio definitivo)

Todos os dados publicados vêm do site antigo ou de fontes públicas verificadas (Receita Federal via APIs abertas,
Brasil Center Shopping, ANS/DOU, perfis sociais). Os itens abaixo não puderam ser confirmados e estão marcados no
`config/site.json` para ajuste rápido (um único arquivo, depois `python3 tools/publish.py parts pages`).

| # | Item | Situação atual no site | O que confirmar |
|---|---|---|---|
| 1 | **Número do WhatsApp** | Links usam `wa.me/556137711100` (o fixo 61 3771-1100) | Nenhum número de WhatsApp é publicado em lugar algum. Informar o número correto (ou confirmar que o fixo usa WhatsApp Business). |
| 2 | **Lista de clientes** | 15 clientes em 7 estados (logos do site antigo, incluindo a seção oculta e a página órfã `servicos.html`) | Confirmar contratos vigentes e autorização de uso das marcas. Clientes de 2015 (Unimed Centro-Oeste, PlanSaúde, Unimed Rio Verde) não foram publicados. |
| 3 | **Ouvidoria 0800 941 1190** | Publicado no Canal de Denúncia/Conformidade/Contato (vinha do portal LGPD antigo) | Titularidade não verificada. Confirmar se é da Télix e se está ativo. |
| 4 | **Telefone da matriz (61) 3403-5353** | Exibido como telefone da matriz (Brasília) | É compartilhado com empresas do mesmo grupo. Confirmar se deve aparecer. |
| 5 | **Encarregado (DPO)** | Sanclé Landim Albuquerque — dpo@telixcom.com.br (política de 2020) | Confirmar se continua o mesmo. |
| 6 | **Endereço: "1º piso"/Loja 13** | "Brasil Center Shopping – 1º piso" (sem nº da loja) | Confirmar piso e se o número da loja deve aparecer. |
| 7 | **CNPJ exibido** | Matriz 16.881.685/0001-62 no rodapé; filial 0002-43 na página de contato | Confirmar preferência. |
| 8 | **LinkedIn** | Página de empresa `/company/télix-comunicação-e-relacionamento` (o site antigo apontava para um perfil pessoal) | A página de empresa está com setor incorreto ("eletricidade, gás…") — corrigir no LinkedIn. |
| 9 | **Horário administrativo** | Não publicado (só o 24/7 da regulação/remoção, que consta no site antigo) | Informar se houver expediente/atendimento presencial. |
| 10 | **Médicos reguladores** | O texto fala em "apoio operacional à regulação de vagas" | Se houver médicos reguladores com responsável técnico no CRM, dá para usar "regulação médica". |
| 11 | **Pesquisa de satisfação IDSS** | Descrita como execução de campo conforme desenho da operadora | Confirmar se a Télix atende os requisitos técnicos da ANS (estatístico, auditoria). |
| 12 | **Fotos** | Reaproveitadas do site antigo (aparentam banco de imagens) | Confirmar licença de uso; idealmente substituir por fotos reais da equipe/unidade. |
| 13 | **Nome do banner de cookies** | "Télix Cookies" (visual semelhante ao CookieYes, nas cores da marca) | Se preferir outro nome (ex.: "Cookyes"), é um texto no `tools/build.py`. |
| 14 | **Grupo empresarial** | Não mencionado | Informar se a relação com o grupo (SAW/Trix) pode ser citada. |
| 15 | **E-mails** | falecom@, ouvidoria@, dpo@ (telixcom.com.br). O domínio recebe e-mail (MX próprio) | Confirmar caixas ativas; considerar rh@ e compliance@. O DMARC aponta relatórios para `infra@itrixti.com.br` (provável erro de digitação). |

## Ajustes recomendados no painel do WordPress (1 minuto cada)

1. **Aparência → Editor → Identidade do site → Ícone do site**: enviar `telix-icone.png` (já está na Biblioteca de Mídia) — o ícone já é injetado por script, mas o WordPress precisa dele para o `<head>` e para o `/favicon.ico`.
2. **Configurações → Leitura → "Sua página inicial exibe": Uma página estática → Início** (a ferramenta MCP não permite alterar; o site já funciona com o modelo da página inicial, então é opcional).
3. **Configurações → Geral → E-mail de administração**: usar a caixa do Compliance/DPO (recebe os avisos de novos relatos).
4. **Usuários → Perfil**: definir um "Apelido/Nome de exibição" diferente do login (o slug do autor aparece no sitemap de usuários).
5. **Plugins**: instalar um plugin de SEO (Yoast ou Rank Math) para meta tags no HTML servido e desativar o sitemap de usuários.
