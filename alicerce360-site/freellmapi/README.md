# FreeLLMAPI para o assistente do site Alicerce360

O site já traz o assistente **Ali** (botão do mascote e item “Pergunte ao Ali” no menu de contato). Sem configuração, ele responde por uma **base de conhecimento local** (`src/js/a360.ui.js`, lista `A3_KB`), sem enviar nada para fora. Este kit liga o assistente ao [FreeLLMAPI](https://github.com/tashfeenahmed/freellmapi) — um servidor de código aberto (MIT) que junta as faixas gratuitas de vários provedores de IA em um único endpoint compatível com a API da OpenAI.

## Como fica a arquitetura

```
Navegador (site)  ──POST /v1/chat/completions──▶  Nginx (ia.alicerce360.com.br)
                                                    • CORS só para o site
                                                    • 12 req/min por visitante
                                                    • injeta a chave no servidor
                                                    ▼
                                         FreeLLMAPI (127.0.0.1:3001, Docker)
                                                    ▼
                                         provedores de IA (faixas gratuitas)
```

A **chave unificada do FreeLLMAPI nunca vai para o navegador** nem para o repositório: fica em `/etc/nginx/freellmapi-key.conf` no servidor.

## Passo a passo (VPS com Docker e Nginx)

1. **Subir o FreeLLMAPI**
   ```bash
   mkdir -p /opt/freellmapi && cd /opt/freellmapi
   cp /caminho/do/repo/alicerce360-site/freellmapi/{docker-compose.yml,.env.example} .
   cp .env.example .env && sed -i "s/troque-por-64-caracteres-hexadecimais/$(openssl rand -hex 32)/" .env
   docker compose up -d
   ```
2. **Criar a conta e cadastrar provedores**: abra o painel por túnel SSH (`ssh -L 3001:127.0.0.1:3001 usuario@vps` e acesse `http://localhost:3001`). Na página **Keys**, cadastre as chaves gratuitas dos provedores que a EBAEM usar e copie a **chave unificada** (`freellmapi-…`).
3. **Publicar o proxy**: siga o cabeçalho de `nginx-ia.alicerce360.conf` (arquivo da chave com permissão 600, `certbot --nginx -d ia.alicerce360.com.br`). Crie antes o DNS `ia.alicerce360.com.br` apontando para a VPS.
4. **Ligar no site**: em `site.config.json`, preencha
   ```json
   "ai": { "endpoint": "https://ia.alicerce360.com.br/v1/chat/completions", "model": "auto", "max_tokens": 450 }
   ```
   e publique só o rodapé (onde vai a configuração): `python3 tools/deploy_wp.py --only chrome`.
5. **Testar**: abra o site, aceite a categoria “Funcionais” nas preferências de cookies e pergunte algo ao Ali. O cabeçalho do chat passa a mostrar “IA conectada (FreeLLMAPI)”. Se a IA falhar ou demorar mais de 20 s, o Ali responde pela base local automaticamente.

## Privacidade e uso responsável

- O assistente só envia perguntas à IA se a pessoa tiver permitido a categoria **Funcionais** no aviso de cookies; caso contrário, usa a base local. Isso está descrito na Política de Cookies e na de Privacidade.
- O Nginx não grava log de acesso (`access_log off`) — perguntas e IPs não ficam armazenados no proxy.
- O texto do sistema orienta a IA a usar só a base de conhecimento, não inventar preços e oferecer atendimento humano.
- **Atenção**: o próprio projeto FreeLLMAPI avisa que faixas gratuitas não têm garantia de disponibilidade nem modelos de ponta, e que cumprir os termos de uso de cada provedor é responsabilidade de quem configura. Use provedores cujos termos permitam esse uso e revise a lista de suboperadores na Central de Confiança se adicionar novos provedores.

## Ajustar respostas

- **Base local**: edite `A3_KB` em `src/js/a360.ui.js` (palavras-chave + respostas em PT/EN/ES) e publique com `--only chrome`.
- **Comportamento da IA**: o texto do sistema está em `systemPrompt()` em `src/js/a360.core.js` e já inclui a base local como contexto.
