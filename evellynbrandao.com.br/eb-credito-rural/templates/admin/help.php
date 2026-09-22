<?php
/**
 * Ajuda. Variáveis: $guard, $caps.
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
