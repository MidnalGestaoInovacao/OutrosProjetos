<?php
/**
 * Ajuda. Variáveis: $guard, $caps, $abilities, $mcp.
 *
 * @package EBCR
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ebcr-admin">
	<h1><?php esc_html_e( 'Ajuda — EB Crédito Rural', 'eb-credito-rural' ); ?></h1>
	<div class="ebcr-box">
		<h2><?php esc_html_e( 'Primeiros passos', 'eb-credito-rural' ); ?></h2>
		<ol>
			<li>
			<?php
			echo wp_kses(
				__( 'Crie uma página (ex.: <em>Área do produtor</em>) com o shortcode <code>[ebcr_portal]</code> e selecione-a em <strong>Configurações → Geral</strong>.', 'eb-credito-rural' ),
				array(
					'em'     => array(),
					'code'   => array(),
					'strong' => array(),
				)
			);
			?>
			</li>
			<li>
			<?php
			echo wp_kses(
				__( 'Instale um plugin SMTP (WP Mail SMTP, FluentSMTP ou Post SMTP), configure o remetente em <strong>Configurações → E-mails</strong> e use o botão <em>Enviar e-mail de teste</em>.', 'eb-credito-rural' ),
				array(
					'em'     => array(),
					'strong' => array(),
				)
			);
			?>
			</li>
			<li>
			<?php
			echo wp_kses(
				__( 'Em <strong>Configurações → Segurança</strong>, informe uma pasta fora da raiz pública (se a hospedagem permitir) e clique em <em>Testar proteção</em>. O resultado deve ser "Protegido" ou "fora da raiz pública".', 'eb-credito-rural' ),
				array(
					'em'     => array(),
					'strong' => array(),
				)
			);
			?>
			</li>
			<li><?php echo wp_kses( __( 'Para criptografia em repouso, adicione ao <code>wp-config.php</code> a linha mostrada na aba Segurança (<code>define( \'EBCR_ENCRYPTION_KEY\', \'base64:…\' );</code>), guarde a chave em local seguro e só então ligue as opções de criptografia.', 'eb-credito-rural' ), array( 'code' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( 'Crie usuários da equipe com os papéis <strong>Analista de crédito</strong> ou <strong>Gestor de crédito</strong> (Usuários → Adicionar).', 'eb-credito-rural' ), array( 'strong' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( 'Revise as políticas e versões em <strong>Configurações → Privacidade e compliance</strong> com o jurídico, e a matriz de documentos em <strong>Documentos e uploads</strong>.', 'eb-credito-rural' ), array( 'strong' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( 'Use <code>[ebcr_cta]</code> na página de captação para levar o produtor ao cadastro.', 'eb-credito-rural' ), array( 'code' => array() ) ); ?></li>
		</ol>
	</div>
	<div class="ebcr-box">
		<h2><?php esc_html_e( 'Papéis e permissões', 'eb-credito-rural' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Papel', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Capacidades', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $caps as $ebcr_role => $ebcr_list ) :
			?>
			<tr><td><code><?php echo esc_html( $ebcr_role ); ?></code></td><td><?php echo esc_html( implode( ', ', $ebcr_list ) ); ?></td></tr><?php endforeach; ?>
		</tbody></table>
		<p class="description"><?php esc_html_e( 'Clientes não acessam o painel do WordPress (são redirecionados ao portal) e não veem a barra de administração. Todas as verificações usam capacidades, nunca o nome do papel.', 'eb-credito-rural' ); ?></p>
	</div>
	<div class="ebcr-box" id="nginx">
		<h2><?php esc_html_e( 'Bloqueio da pasta de documentos no Nginx', 'eb-credito-rural' ); ?></h2>
		<p><?php esc_html_e( 'O Nginx ignora .htaccess. Se o teste de proteção indicar "exposto", peça à hospedagem para adicionar ao bloco server do site:', 'eb-credito-rural' ); ?></p>
		<pre class="ebcr-code"><?php echo esc_html( $guard->nginx_snippet() ); ?></pre>
		<p><?php esc_html_e( 'Alternativa preferível: configurar um caminho fora da raiz pública em Configurações → Segurança (ex.: /home/usuario/ebcr-private), o que elimina qualquer URL direta.', 'eb-credito-rural' ); ?></p>
		<p><?php /* translators: %s: caminho */ printf( esc_html__( 'Pasta em uso atualmente: %s', 'eb-credito-rural' ), '<code>' . esc_html( $guard->base_dir() ) . '</code>' ); ?></p>
	</div>
	<div class="ebcr-box" id="mcp">
		<h2><?php esc_html_e( 'Configuração por IA (Easy MCP AI)', 'eb-credito-rural' ); ?></h2>
		<p><?php esc_html_e( 'Tudo o que se faz nas telas de Configurações e na lista de solicitações também pode ser feito por um agente de IA conectado ao site pelo plugin Easy MCP AI. O plugin EB Crédito Rural registra as abilities abaixo na Abilities API do WordPress e as habilita automaticamente no Easy MCP AI ao ser ativado ou atualizado. Cada ability exige a mesma capacidade da tela equivalente (a chave MCP herda as permissões do usuário que a criou) e tudo fica registrado no log de auditoria com origem "mcp".', 'eb-credito-rural' ); ?></p>
		<p><strong><?php esc_html_e( 'Estado:', 'eb-credito-rural' ); ?></strong>
			<?php echo $mcp['abilities_api'] ? esc_html__( 'Abilities API disponível', 'eb-credito-rural' ) : esc_html__( 'Abilities API indisponível (WordPress 6.9+)', 'eb-credito-rural' ); ?> ·
			<?php echo $mcp['easy_mcp_ai_active'] ? esc_html__( 'Easy MCP AI detectado', 'eb-credito-rural' ) : esc_html__( 'Easy MCP AI não detectado', 'eb-credito-rural' ); ?> ·
			<?php /* translators: 1: habilitadas, 2: total */ printf( esc_html__( '%1$d de %2$d abilities habilitadas no conector', 'eb-credito-rural' ), count( $mcp['enabled'] ), count( $mcp['enabled'] ) + count( $mcp['missing'] ) ); ?>
			<?php if ( $mcp['missing'] && current_user_can( \EBCR\Roles\Capabilities::CAP_SETTINGS ) ) : ?>
				— <a href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-settings&tab=ferramentas#mcp' ) ); ?>"><?php esc_html_e( 'habilitar em Configurações → Ferramentas', 'eb-credito-rural' ); ?></a>
			<?php endif; ?>
		</p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Ferramenta (nome no Easy MCP AI)', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'O que faz', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Exige', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
		<?php
		foreach ( $abilities as $ebcr_slug => $ebcr_def ) :
			?>
			<tr><td><code><?php echo esc_html( 'wp_ability_ebcr_' . str_replace( '-', '_', $ebcr_slug ) ); ?></code><br><small><?php echo esc_html( $ebcr_def['label'] ); ?></small></td><td><?php echo esc_html( $ebcr_def['description'] ); ?></td><td><code><?php echo esc_html( $ebcr_def['cap'] ); ?></code></td></tr><?php endforeach; ?>
		</tbody></table>
		<h3><?php esc_html_e( 'Exemplos de pedidos ao agente', 'eb-credito-rural' ); ?></h3>
		<ul>
			<li>“<?php esc_html_e( 'Mostre as configurações da aba E-mails do EB Crédito Rural.', 'eb-credito-rural' ); ?>”</li>
			<li>“<?php esc_html_e( 'Defina os e-mails administrativos como contato@meudominio.com.br e ligue o aviso ao analista responsável.', 'eb-credito-rural' ); ?>”</li>
			<li>“<?php esc_html_e( 'Qual é o estado do plugin? Há algo pendente para a publicação?', 'eb-credito-rural' ); ?>”</li>
			<li>“<?php esc_html_e( 'Rode o teste de proteção da pasta de documentos e me diga o resultado.', 'eb-credito-rural' ); ?>”</li>
			<li>“<?php esc_html_e( 'Cadastre maria@exemplo.com como analista de crédito.', 'eb-credito-rural' ); ?>”</li>
			<li>“<?php esc_html_e( 'Liste as solicitações em pré-análise e atribua a EB-2026-000012 ao analista João.', 'eb-credito-rural' ); ?>”</li>
			<li>“<?php esc_html_e( 'Na solicitação EB-2026-000012, peça a matrícula atualizada do imóvel com prazo de 30 dias.', 'eb-credito-rural' ); ?>”</li>
		</ul>
		<p class="description"><?php esc_html_e( 'Os dados sensíveis (CPF/CNPJ, documentos) chegam mascarados ao agente e os arquivos nunca são expostos por esse caminho. Se as ferramentas não aparecerem no agente, abra o Easy MCP AI → Abilities, marque as do grupo "EB Crédito Rural" e reconecte o agente.', 'eb-credito-rural' ); ?></p>
	</div>
	<div class="ebcr-box" id="operacao">
		<h2><?php esc_html_e( 'Operação: painel, CRM, relatórios, dossiê e painel da equipe no site', 'eb-credito-rural' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Painel: cartões, gráficos (funil, mês, UF, atividade, garantia) e listas de pendências; analistas com "vê só as atribuídas" veem apenas as suas.', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'Relatórios: filtros por período, carteira/fundo, status e analista, com exportação CSV. Configure as carteiras em Geral → Fundos/carteiras e vincule cada solicitação na tela de detalhe.', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'CRM: ficha por cliente, atividades e tarefas (lembrete diário por e-mail ao responsável), lista com filtros e quadro Kanban. Estágios e origens em Configurações → CRM.', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'Dossiê: na solicitação, "Gerar dossiê em PDF para o comitê" reúne dados, indicadores, garantias, documentos, conferência, histórico e aceites.', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'Painel da equipe no site: analistas e gestores podem operar tudo dentro da página do portal. Em Geral → "Painel da equipe" = "Só no site", eles não entram no wp-admin (administradores nunca são bloqueados).', 'eb-credito-rural' ); ?></li>
		</ul>
	</div>
	<div class="ebcr-box" id="2fa">
		<h2><?php esc_html_e( 'Verificação em duas etapas (2FA) da equipe', 'eb-credito-rural' ); ?></h2>
		<p><?php esc_html_e( 'Em Configurações → Segurança escolha Opcional ou Obrigatório. Cada analista/gestor ativa em Usuários → Perfil: aplicativo autenticador (QR ou segredo) ou código por e-mail; guarde os 10 códigos de backup. No login, a verificação acontece em /?ebcr_2fa=1 (front-end) e é possível confiar no dispositivo por 30 dias.', 'eb-credito-rural' ); ?></p>
		<p><?php esc_html_e( 'Recuperação: se alguém perder o celular e os códigos de backup, um administrador desativa o 2FA dele em Usuários → editar usuário. Em último caso, adicione define( \'EBCR_DISABLE_2FA\', true ); ao wp-config.php temporariamente.', 'eb-credito-rural' ); ?></p>
	</div>
	<div class="ebcr-box" id="integracoes">
		<h2><?php esc_html_e( 'Integrações e assinatura eletrônica', 'eb-credito-rural' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'CEP/CNPJ: ligados por padrão (ViaCEP/BrasilAPI, sem chave). Se a hospedagem bloquear saída HTTP, os campos continuam manuais.', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'Turnstile: crie o widget no painel da Cloudflare, cole site key e secret key em Integrações e escolha o provedor em Segurança. Sem chaves, o plugin volta ao captcha matemático.', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'WhatsApp: app Business na Meta com o produto WhatsApp, número aprovado (Phone Number ID), token permanente de usuário de sistema e template de utilidade aprovado com uma variável {{1}}. Falhas de envio ficam no log de auditoria.', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'Assinatura eletrônica: o cliente assina a autorização de consulta ao SCR na própria área (texto, nome, código por e-mail). O PDF assinado, com IP, data/hora e hashes, entra nos documentos com o selo "Assinado eletronicamente". Revise o texto em Integrações com o jurídico.', 'eb-credito-rural' ); ?></li>
			<li><?php esc_html_e( 'Simulador: coloque [ebcr_simulador] na página de captação; taxa, limites e sistema em Integrações.', 'eb-credito-rural' ); ?></li>
		</ul>
	</div>
	<div class="ebcr-box">
		<h2><?php esc_html_e( 'Perguntas frequentes', 'eb-credito-rural' ); ?></h2>
		<p><strong><?php esc_html_e( 'O cliente diz que não recebeu o e-mail de confirmação.', 'eb-credito-rural' ); ?></strong><br><?php esc_html_e( 'Verifique Ferramentas → fila de e-mails (falhas), o plugin SMTP e o SPF/DKIM do domínio. O cliente pode reenviar o link pela própria área.', 'eb-credito-rural' ); ?></p>
		<p><strong><?php esc_html_e( 'Como pedir um documento que não está na matriz?', 'eb-credito-rural' ); ?></strong><br><?php esc_html_e( 'Na solicitação, use "Solicitar documento" com o tipo "Outro documento" e um rótulo claro. O cliente recebe e-mail e vê a pendência em destaque.', 'eb-credito-rural' ); ?></p>
		<p><strong><?php esc_html_e( 'Um analista precisa aprovar/reprovar.', 'eb-credito-rural' ); ?></strong><br><?php esc_html_e( 'Só o papel Gestor (ou administrador) tem a capacidade ebcr_change_final_status. Altere o papel do usuário.', 'eb-credito-rural' ); ?></p>
		<p><strong><?php esc_html_e( 'Um cliente pediu exclusão dos dados.', 'eb-credito-rural' ); ?></strong><br><?php esc_html_e( 'A solicitação vira tarefa no CRM e e-mail ao DPO. Solicitações em andamento têm retenção obrigatória; as finalizadas podem ser anonimizadas em Ferramentas → WordPress → Apagar dados pessoais.', 'eb-credito-rural' ); ?></p>
		<p><strong><?php esc_html_e( 'Os uploads falham com "excede o limite do servidor".', 'eb-credito-rural' ); ?></strong><br><?php esc_html_e( 'Aumente upload_max_filesize e post_max_size no PHP (ou peça à hospedagem) ou reduza o tamanho máximo em Configurações → Documentos.', 'eb-credito-rural' ); ?></p>
	</div>
	<div class="ebcr-box">
		<h2><?php esc_html_e( 'Checklist de publicação', 'eb-credito-rural' ); ?></h2>
		<ul>
			<li>☐ <?php esc_html_e( 'Site em HTTPS com certificado válido', 'eb-credito-rural' ); ?></li>
			<li>☐ <?php esc_html_e( 'Página do portal criada e selecionada', 'eb-credito-rural' ); ?></li>
			<li>☐ <?php esc_html_e( 'Teste de proteção da pasta de documentos = protegido', 'eb-credito-rural' ); ?></li>
			<li>☐ <?php esc_html_e( 'Chave de criptografia definida e guardada em cofre (se criptografia ligada)', 'eb-credito-rural' ); ?></li>
			<li>☐ <?php esc_html_e( 'SMTP configurado e e-mail de teste recebido', 'eb-credito-rural' ); ?></li>
			<li>☐ <?php esc_html_e( 'Políticas revisadas pelo jurídico, com versão definida', 'eb-credito-rural' ); ?></li>
			<li>☐ <?php esc_html_e( 'Matriz de documentos e limites de valor/prazo revisados pela gestora do fundo', 'eb-credito-rural' ); ?></li>
			<li>☐ <?php esc_html_e( 'Usuários da equipe criados com os papéis corretos', 'eb-credito-rural' ); ?></li>
			<li>☐ <?php esc_html_e( 'Cron do sistema (se DISABLE_WP_CRON) chamando wp-cron.php a cada 5 minutos', 'eb-credito-rural' ); ?></li>
			<li>☐ <?php esc_html_e( 'Backup do banco e da pasta privada configurado', 'eb-credito-rural' ); ?></li>
		</ul>
	</div>
</div>
