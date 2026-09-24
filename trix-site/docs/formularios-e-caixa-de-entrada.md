# Formulários (Contato, Canal LGPD, Canal de Compliance) — como funcionam

## Fluxo
1. O visitante preenche o formulário na página (`/contato/`, `/canal-lgpd/` ou `/canal-de-compliance/`).
2. Proteções antes do envio: campos obrigatórios (HTML5), **captcha matemático** (soma ou multiplicação, com botão "gerar outra pergunta"), **honeypot** invisível (`website_url`), tempo mínimo de preenchimento (2,5 s) e **consentimento LGPD** obrigatório.
3. O script gera um **número de protocolo** (`TRIX-AAAAMMDD-XXXX`), monta o texto da mensagem com todos os campos e envia via `fetch()` para o endpoint nativo do WordPress `wp-comments-post.php`, associando a mensagem à própria página (a página tem comentários abertos, mas o template nunca exibe comentários).
4. O WordPress grava a mensagem como **comentário pendente de moderação** e envia e-mail ao administrador do site (Configurações → Discussão → "Enviar-me um e-mail quando um comentário aguarda moderação").
5. O visitante vê a confirmação com o protocolo. Se o envio falhar, recebe um link `mailto:` já preenchido para enviar por e-mail (falecom@ / dpo@ / ouvidoria@trixti.com.br).

## Onde ler as mensagens
Painel → **Comentários** → aba **Pendentes**. Cada mensagem começa com `[CONTATO]`, `[LGPD]` ou `[COMPLIANCE]` e o protocolo. Relatos anônimos aparecem como autor "Relato anônimo".

## Configurações obrigatórias (painel)
- Configurações → Discussão: marcar **"O comentário deve ser aprovado manualmente"** e desmarcar **"O autor do comentário deve ter um comentário aprovado anteriormente"**. Assim nenhuma mensagem é publicada automaticamente.
- **Nunca aprovar** um comentário-mensagem (aprovar tornaria o texto acessível pela API pública de comentários). Use "Responder por e-mail" fora do painel e depois envie para a lixeira ou mantenha como pendente/spam.
- Trocar o e-mail do administrador para o e-mail que deve receber as notificações (ex.: falecom@trixti.com.br) e, se desejar, criar usuários "Editor" para o DPO e o Compliance Officer.
- Plugin de spam: Akismet vem instalado (inativo). Pode ser ativado com chave, mas o captcha já bloqueia envios automatizados simples.

## Evolução recomendada
Instalar um plugin de formulários (Fluent Forms, WPForms ou Forminator) com envio por e-mail e armazenamento próprio, e apontar `formEndpoint` em `TRIX_CONFIG` (bloco "Trix • Estilos") — ou substituir os formulários pelos shortcodes do plugin. Também é possível integrar o Canal de Compliance a uma plataforma de denúncias especializada.
