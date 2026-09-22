<?php
/**
 * Configurações em abas.
 * Variáveis: $tabs, $values, $fields, $guard, $storage, $crypto, $new_key, $has_encrypted, $php_limits, $events, $placeholders, $matrix, $conditions, $levels, $policies, $statuses, $mail_stats, $mail_failures, $env, $last_daily, $notice, $notice_type, $tab.
 *
 * @package EBCR
 */

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_post  = esc_url( admin_url( 'admin-post.php' ) );
$ebcr_field = static function ( $key, array $def, $value ) use ( $values ) {
	list( $type, $label, $help ) = $def;
	$id                          = 'ebcr-' . $key;
	echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
	switch ( $type ) {
		case 'checkbox':
			echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="1" ' . checked( (bool) $value, true, false ) . '> ' . esc_html__( 'Ativado', 'eb-credito-rural' ) . '</label>';
			break;
		case 'number':
			echo '<input type="number" step="any" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text">';
			break;
		case 'textarea':
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" rows="5" class="large-text code">' . esc_textarea( (string) $value ) . '</textarea>';
			break;
		case 'select':
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '">';
			foreach ( $def[3] as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '" ' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>';
			break;
		case 'page':
			wp_dropdown_pages(
				array(
					'name'              => esc_attr( $key ),
					'id'                => esc_attr( $id ),
					'selected'          => (int) $value,
					'show_option_none'  => esc_html__( '— Selecione —', 'eb-credito-rural' ),
					'option_none_value' => 0,
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- função do core.
			break;
		case 'steps':
			foreach ( \EBCR\Forms\Steps::all() as $n => $st ) {
				echo '<p><label>' . esc_html( $n . '. ' . $st['title'] ) . '<br><textarea name="' . esc_attr( $key ) . '[' . (int) $n . ']" rows="2" class="large-text">' . esc_textarea( isset( $value[ $n ] ) ? (string) $value[ $n ] : '' ) . '</textarea></label></p>';
			}
			break;
		default:
			echo '<input type="' . esc_attr( in_array( $type, array( 'email', 'url' ), true ) ? $type : 'text' ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text">';
	}
	echo '<p class="ebcr-help-field">' . esc_html( $help ) . '</p></td></tr>';
};
?>
<div class="wrap ebcr-admin">
	<h1><?php esc_html_e( 'Crédito Rural — Configurações', 'eb-credito-rural' ); ?></h1>
	<?php
	if ( $notice ) :
		?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
	<nav class="nav-tab-wrapper ebcr-tabs-nav">
		<?php
		foreach ( $tabs as $ebcr_k => $ebcr_t ) :
			?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $ebcr_k ) ); ?>" class="nav-tab" data-tab="<?php echo esc_attr( $ebcr_k ); ?>"><?php echo esc_html( $ebcr_t['label'] ); ?></a><?php endforeach; ?>
	</nav>
	<?php foreach ( $tabs as $ebcr_k => $ebcr_t ) : ?>
	<div class="ebcr-tabpanel" id="ebcr-tab-<?php echo esc_attr( $ebcr_k ); ?>" hidden>
		<p class="ebcr-settings-intro"><?php echo esc_html( $ebcr_t['intro'] ); ?></p>
		<?php if ( 'ferramentas' === $ebcr_k ) : ?>
			<h2><?php esc_html_e( 'Verificação do ambiente', 'eb-credito-rural' ); ?></h2>
			<table class="widefat striped" style="max-width:720px"><tbody>
			<?php
			foreach ( $env as $ebcr_e ) :
				?>
				<tr><td><?php echo esc_html( $ebcr_e[0] ); ?></td><td><?php echo esc_html( (string) $ebcr_e[1] ); ?></td><td><?php echo $ebcr_e[2] ? '<span class="ebcr-status-ok">✔</span>' : '<span class="ebcr-status-warn">⚠</span>'; ?></td></tr><?php endforeach; ?>
			</tbody></table>
			<h2><?php esc_html_e( 'Fila de e-mails', 'eb-credito-rural' ); ?></h2>
			<p><?php /* translators: 1: pendentes, 2: enviados, 3: falhas */ printf( esc_html__( 'Pendentes: %1$d · Enviados (30 dias): %2$d · Falhas: %3$d', 'eb-credito-rural' ), (int) $mail_stats['pending'], (int) $mail_stats['sent'], (int) $mail_stats['failed'] ); ?></p>
			<?php
			if ( $mail_failures ) :
				?>
				<table class="widefat striped" style="max-width:900px"><thead><tr><th><?php esc_html_e( 'Destinatário', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Assunto', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Erro', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
				<?php
				foreach ( $mail_failures as $ebcr_f ) :
					?>
				<tr><td><?php echo esc_html( $ebcr_f['recipient'] ); ?></td><td><?php echo esc_html( $ebcr_f['subject'] ); ?></td><td><?php echo esc_html( $ebcr_f['last_error'] ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
			<p>
			<?php
			foreach ( array(
				'process_mail'    => __( 'Processar fila agora', 'eb-credito-rural' ),
				'retry_mail'      => __( 'Reprocessar falhas', 'eb-credito-rural' ),
				'run_daily'       => __( 'Executar rotina diária', 'eb-credito-rural' ),
				'run_retention'   => __( 'Executar rotina de retenção', 'eb-credito-rural' ),
				'export_settings' => __( 'Exportar configurações (JSON)', 'eb-credito-rural' ),
			) as $ebcr_tool => $ebcr_label ) :
				?>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline-block;margin-right:6px"><input type="hidden" name="action" value="ebcr_tool"><input type="hidden" name="tool" value="<?php echo esc_attr( $ebcr_tool ); ?>"><?php echo ebcr_nonce_field( 'settings_tool' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="button" <?php echo 'run_retention' === $ebcr_tool ? 'data-confirm="' . esc_attr__( 'A rotina de retenção anonimiza solicitações finalizadas fora do prazo. Continuar?', 'eb-credito-rural' ) . '"' : ''; ?>><?php echo esc_html( $ebcr_label ); ?></button></form>
			<?php endforeach; ?>
			</p>
			<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" enctype="multipart/form-data"><input type="hidden" name="action" value="ebcr_tool"><input type="hidden" name="tool" value="import_settings"><?php echo ebcr_nonce_field( 'settings_tool' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><p><label><?php esc_html_e( 'Importar configurações (JSON exportado por este plugin):', 'eb-credito-rural' ); ?> <input type="file" name="settings_file" accept=".json,application/json" required></label> <button class="button"><?php esc_html_e( 'Importar', 'eb-credito-rural' ); ?></button></p></form>
			<?php
			if ( $last_daily ) :
				?>
				<p class="description"><?php /* translators: %s: data */ printf( esc_html__( 'Última rotina diária: %s', 'eb-credito-rural' ), esc_html( Helpers::date( $last_daily['at'] ?? '' ) ) ); ?></p><?php endif; ?>
			<p class="description"><?php esc_html_e( 'Exportadores/apagadores de dados pessoais: Ferramentas → Exportar/Apagar dados pessoais (integração nativa).', 'eb-credito-rural' ); ?></p>
		<?php else : ?>
		<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<input type="hidden" name="action" value="ebcr_save_settings"><input type="hidden" name="tab" value="<?php echo esc_attr( $ebcr_k ); ?>">
			<?php echo ebcr_nonce_field( 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( 'seguranca' === $ebcr_k ) : ?>
				<div class="ebcr-box">
					<h3><?php esc_html_e( 'Estado da pasta privada', 'eb-credito-rural' ); ?></h3>
					<p><?php /* translators: %s: caminho */ printf( esc_html__( 'Pasta em uso: %s', 'eb-credito-rural' ), '<code>' . esc_html( $storage['used'] ) . '</code>' ); ?> — <?php echo $storage['inside_public'] ? '<span class="ebcr-status-warn">' . esc_html__( 'dentro da raiz pública (protegida por .htaccess; teste abaixo)', 'eb-credito-rural' ) . '</span>' : '<span class="ebcr-status-ok">' . esc_html__( 'fora da raiz pública', 'eb-credito-rural' ) . '</span>'; ?></p>
					<?php $ebcr_last = $values['last_protection_test']; ?>
					<?php
					if ( is_array( $ebcr_last ) && ! empty( $ebcr_last['result'] ) ) :
						?>
						<p><?php /* translators: 1: resultado, 2: data */ printf( esc_html__( 'Último teste: %1$s (%2$s)', 'eb-credito-rural' ), '<strong class="' . ( 'exposed' === $ebcr_last['result'] ? 'ebcr-status-bad' : 'ebcr-status-ok' ) . '">' . esc_html( $ebcr_last['message'] ) . '</strong>', esc_html( Helpers::date( $ebcr_last['tested_at'] ) ) ); ?></p><?php endif; ?>
					<h3><?php esc_html_e( 'Criptografia', 'eb-credito-rural' ); ?></h3>
					<?php
					$ebcr_cs = array(
						'ok'       => array( 'ebcr-status-ok', __( 'Chave válida encontrada em wp-config.php.', 'eb-credito-rural' ) ),
						'none'     => array( 'ebcr-status-warn', __( 'Nenhuma chave definida.', 'eb-credito-rural' ) ),
						'invalid'  => array( 'ebcr-status-bad', __( 'Chave definida, mas inválida (precisa ter 32 bytes em base64:… ou 64 caracteres hexadecimais).', 'eb-credito-rural' ) ),
						'nosodium' => array( 'ebcr-status-bad', __( 'Extensão sodium ausente no PHP.', 'eb-credito-rural' ) ),
					);
					?>
					<p><span class="<?php echo esc_attr( $ebcr_cs[ $crypto ][0] ); ?>"><?php echo esc_html( $ebcr_cs[ $crypto ][1] ); ?></span>
					<?php
					if ( $has_encrypted ) :
						?>
						· <?php esc_html_e( 'Já existem dados criptografados: a criptografia não pode ser desligada.', 'eb-credito-rural' ); ?><?php endif; ?></p>
					<?php
					if ( 'ok' !== $crypto ) :
						?>
						<p><?php esc_html_e( 'Para ativar, adicione ao wp-config.php (antes de "/* That\'s all, stop editing! */") e guarde a chave em um cofre de senhas — perdê-la torna os dados irrecuperáveis:', 'eb-credito-rural' ); ?></p><pre class="ebcr-code">define( 'EBCR_ENCRYPTION_KEY', '<?php echo esc_html( $new_key ); ?>' );</pre><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( 'documentos' === $ebcr_k ) : ?>
				<p class="description"><?php /* translators: 1: upload, 2: post */ printf( esc_html__( 'Limites atuais do PHP: upload_max_filesize=%1$s, post_max_size=%2$s.', 'eb-credito-rural' ), esc_html( size_format( $php_limits['php_upload'] ) ), esc_html( size_format( $php_limits['php_post'] ) ) ); ?></p>
			<?php endif; ?>
			<table class="form-table" role="presentation"><tbody>
			<?php foreach ( $fields[ $ebcr_k ] as $ebcr_key => $ebcr_def ) : ?>
				<?php if ( in_array( $ebcr_def[0], array( 'matrix', 'templates', 'policies', 'statuses' ), true ) ) : ?>
					<tr><th scope="row"><?php echo esc_html( $ebcr_def[1] ); ?></th><td><p class="ebcr-help-field"><?php echo esc_html( $ebcr_def[2] ); ?></p>
					<?php if ( 'matrix' === $ebcr_def[0] ) : ?>
						<table class="widefat ebcr-matrix" data-matrix><thead><tr><th><?php esc_html_e( 'Chave', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Rótulo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Condição', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Obrigatoriedade', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Validade (dias)', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Ajuda ao cliente', 'eb-credito-rural' ); ?></th><th></th></tr></thead><tbody>
						<?php $ebcr_i = 0; foreach ( $matrix as $ebcr_row ) : ?>
							<tr>
								<td><input type="text" name="document_matrix[<?php echo (int) $ebcr_i; ?>][key]" value="<?php echo esc_attr( $ebcr_row['key'] ); ?>" pattern="[a-z0-9_]+"></td>
								<td><input type="text" name="document_matrix[<?php echo (int) $ebcr_i; ?>][label]" value="<?php echo esc_attr( $ebcr_row['label'] ); ?>"></td>
								<td><select name="document_matrix[<?php echo (int) $ebcr_i; ?>][condition]">
								<?php
								foreach ( $conditions as $ebcr_ck => $ebcr_cl ) :
									?>
									<option value="<?php echo esc_attr( $ebcr_ck ); ?>" <?php selected( $ebcr_row['condition'], $ebcr_ck ); ?>><?php echo esc_html( $ebcr_cl ); ?></option><?php endforeach; ?></select></td>
								<td><select name="document_matrix[<?php echo (int) $ebcr_i; ?>][required]">
								<?php
								foreach ( $levels as $ebcr_lk => $ebcr_ll ) :
									?>
									<option value="<?php echo esc_attr( $ebcr_lk ); ?>" <?php selected( $ebcr_row['required'], $ebcr_lk ); ?>><?php echo esc_html( $ebcr_ll ); ?></option><?php endforeach; ?></select></td>
								<td><input type="number" min="0" name="document_matrix[<?php echo (int) $ebcr_i; ?>][validity_days]" value="<?php echo esc_attr( (string) $ebcr_row['validity_days'] ); ?>" style="width:80px"></td>
								<td><input type="text" name="document_matrix[<?php echo (int) $ebcr_i; ?>][help]" value="<?php echo esc_attr( $ebcr_row['help'] ); ?>"></td>
								<td><button type="button" class="button-link-delete" data-matrix-remove><?php esc_html_e( 'remover', 'eb-credito-rural' ); ?></button></td>
							</tr>
							<?php
							++$ebcr_i;
endforeach;
						?>
						</tbody></table>
						<template><tr><td><input type="text" name="document_matrix[__i__][key]" pattern="[a-z0-9_]+"></td><td><input type="text" name="document_matrix[__i__][label]"></td><td><select name="document_matrix[__i__][condition]">
						<?php
						foreach ( $conditions as $ebcr_ck => $ebcr_cl ) :
							?>
							<option value="<?php echo esc_attr( $ebcr_ck ); ?>"><?php echo esc_html( $ebcr_cl ); ?></option><?php endforeach; ?></select></td><td><select name="document_matrix[__i__][required]">
							<?php
							foreach ( $levels as $ebcr_lk => $ebcr_ll ) :
								?>
							<option value="<?php echo esc_attr( $ebcr_lk ); ?>"><?php echo esc_html( $ebcr_ll ); ?></option><?php endforeach; ?></select></td><td><input type="number" min="0" name="document_matrix[__i__][validity_days]" value="0" style="width:80px"></td><td><input type="text" name="document_matrix[__i__][help]"></td><td><button type="button" class="button-link-delete" data-matrix-remove><?php esc_html_e( 'remover', 'eb-credito-rural' ); ?></button></td></tr></template>
						<p><button type="button" class="button" data-matrix-add><?php esc_html_e( '+ Adicionar documento', 'eb-credito-rural' ); ?></button></p>
					<?php elseif ( 'templates' === $ebcr_def[0] ) : ?>
						<p class="description"><?php esc_html_e( 'Placeholders:', 'eb-credito-rural' ); ?>
						<?php
						foreach ( $placeholders as $ebcr_ph => $ebcr_pl ) :
							?>
							<code title="<?php echo esc_attr( $ebcr_pl ); ?>"><?php echo esc_html( $ebcr_ph ); ?></code> <?php endforeach; ?></p>
						<?php
						foreach ( $events as $ebcr_ev => $ebcr_evd ) :
							$ebcr_tpl = $values['email_templates'][ $ebcr_ev ] ?? array();
							?>
							<details style="margin-bottom:8px"><summary><strong><?php echo esc_html( $ebcr_evd['label'] ); ?></strong></summary>
							<p><label><?php esc_html_e( 'Assunto', 'eb-credito-rural' ); ?><br><input type="text" class="large-text" name="email_templates[<?php echo esc_attr( $ebcr_ev ); ?>][subject]" value="<?php echo esc_attr( $ebcr_tpl['subject'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $ebcr_evd['subject'] ); ?>"></label></p>
							<p><label><?php esc_html_e( 'Corpo', 'eb-credito-rural' ); ?><br><textarea class="large-text" rows="6" name="email_templates[<?php echo esc_attr( $ebcr_ev ); ?>][body]" placeholder="<?php echo esc_attr( $ebcr_evd['body'] ); ?>"><?php echo esc_textarea( $ebcr_tpl['body'] ?? '' ); ?></textarea></label></p>
							<p class="description"><?php esc_html_e( 'Prévia do padrão:', 'eb-credito-rural' ); ?>
							<?php
							echo esc_html(
								mb_substr(
									\EBCR\Mail\Mailer::fill(
										$ebcr_evd['body'],
										array(
											'nome'      => 'Maria',
											'protocolo' => 'EB-2026-000001',
											'status'    => 'Enviada',
											'valor'     => 'R$ 500.000,00',
										),
										false
									),
									0,
									160
								)
							);
							?>
													…</p>
							</details>
						<?php endforeach; ?>
					<?php elseif ( 'policies' === $ebcr_def[0] ) : ?>
						<?php foreach ( $policies as $ebcr_pk => $ebcr_p ) : ?>
							<div class="ebcr-box">
								<p><strong><?php echo esc_html( $ebcr_p['title'] ); ?></strong> <span class="description">(<?php echo esc_html( $ebcr_pk ); ?> · <?php echo esc_html( 'register' === $ebcr_p['moment'] ? __( 'cadastro', 'eb-credito-rural' ) : ( 'submit' === $ebcr_p['moment'] ? __( 'envio', 'eb-credito-rural' ) : __( 'cadastro e envio', 'eb-credito-rural' ) ) ); ?><?php echo $ebcr_p['required'] ? ' · ' . esc_html__( 'obrigatória', 'eb-credito-rural' ) : ' · ' . esc_html__( 'opcional', 'eb-credito-rural' ); ?>)</span></p>
								<input type="hidden" name="policies[<?php echo esc_attr( $ebcr_pk ); ?>][title]" value="<?php echo esc_attr( $ebcr_p['title'] ); ?>">
								<p><label><?php esc_html_e( 'Página com o texto completo', 'eb-credito-rural' ); ?><br>
								<?php
								wp_dropdown_pages(
									array(
										'name'             => 'policies[' . esc_attr( $ebcr_pk ) . '][page_id]',
										'selected'         => (int) $ebcr_p['page_id'],
										'show_option_none' => esc_html__( '— Nenhuma —', 'eb-credito-rural' ),
										'option_none_value' => 0,
									)
								); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core. 
								?>
											</label> &nbsp; <label><?php esc_html_e( 'Versão', 'eb-credito-rural' ); ?> <input type="text" name="policies[<?php echo esc_attr( $ebcr_pk ); ?>][version]" value="<?php echo esc_attr( $ebcr_p['version'] ); ?>" style="width:80px"></label></p>
								<p><label><?php esc_html_e( 'Texto do aceite', 'eb-credito-rural' ); ?><br><textarea class="large-text" rows="3" name="policies[<?php echo esc_attr( $ebcr_pk ); ?>][text]"><?php echo esc_textarea( $ebcr_p['text'] ); ?></textarea></label></p>
							</div>
						<?php endforeach; ?>
					<?php elseif ( 'statuses' === $ebcr_def[0] ) : ?>
						<table class="widefat"><thead><tr><th><?php esc_html_e( 'Chave', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Nome', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Cor', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Visível ao cliente', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Texto padrão ao cliente', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Transições', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
						<?php foreach ( $statuses as $ebcr_sk => $ebcr_st ) : ?>
							<tr><td><code><?php echo esc_html( $ebcr_sk ); ?></code></td><td><input type="text" name="statuses[<?php echo esc_attr( $ebcr_sk ); ?>][label]" value="<?php echo esc_attr( $ebcr_st['label'] ); ?>"></td><td><input type="color" name="statuses[<?php echo esc_attr( $ebcr_sk ); ?>][color]" value="<?php echo esc_attr( $ebcr_st['color'] ); ?>"></td><td><input type="checkbox" name="statuses[<?php echo esc_attr( $ebcr_sk ); ?>][client_visible]" value="1" <?php checked( ! empty( $ebcr_st['client_visible'] ) ); ?>></td><td><textarea name="statuses[<?php echo esc_attr( $ebcr_sk ); ?>][client_text]" rows="2" style="width:100%"><?php echo esc_textarea( $ebcr_st['client_text'] ); ?></textarea></td><td class="description"><?php echo esc_html( implode( ', ', $ebcr_st['transitions'] ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					<?php endif; ?>
					</td></tr>
				<?php else : ?>
					<?php $ebcr_field( $ebcr_key, $ebcr_def, $values[ $ebcr_key ] ?? '' ); ?>
				<?php endif; ?>
			<?php endforeach; ?>
			</tbody></table>
			<p class="submit"><button class="button button-primary"><?php esc_html_e( 'Salvar', 'eb-credito-rural' ); ?></button></p>
		</form>
			<?php if ( 'seguranca' === $ebcr_k ) : ?>
			<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline-block"><input type="hidden" name="action" value="ebcr_tool"><input type="hidden" name="tool" value="test_protection"><?php echo ebcr_nonce_field( 'settings_tool' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="button button-secondary"><?php esc_html_e( 'Testar proteção da pasta agora', 'eb-credito-rural' ); ?></button></form>
		<?php endif; ?>
			<?php if ( 'emails' === $ebcr_k ) : ?>
			<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline-block"><input type="hidden" name="action" value="ebcr_tool"><input type="hidden" name="tool" value="test_email"><?php echo ebcr_nonce_field( 'settings_tool' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="email" name="to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required> <button class="button button-secondary"><?php esc_html_e( 'Enviar e-mail de teste', 'eb-credito-rural' ); ?></button></form>
		<?php endif; ?>
		<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline-block;margin-left:8px"><input type="hidden" name="action" value="ebcr_reset_settings"><input type="hidden" name="tab" value="<?php echo esc_attr( $ebcr_k ); ?>"><?php echo ebcr_nonce_field( 'settings_reset' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button class="button-link" data-confirm="<?php esc_attr_e( 'Restaurar os valores padrão desta aba?', 'eb-credito-rural' ); ?>"><?php esc_html_e( 'Restaurar padrão desta aba', 'eb-credito-rural' ); ?></button></form>
		<?php endif; ?>
	</div>
	<?php endforeach; ?>
</div>
