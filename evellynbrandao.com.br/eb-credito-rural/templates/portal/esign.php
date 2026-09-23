<?php
/**
 * Assinatura eletrônica de um documento (cliente).
 * Variáveis: $s, $doc_type, $title, $back_url, $sign_url, $errors, $values, $notice, $blocker, $blocker_code, $step1_url,
 * $ttl_minutes, $max_attempts e, quando elegível: $state (intro|code|done), $text, $signer, $email_masked, $pending,
 * $expires_in, $signed_docs, $can_edit.
 *
 * @package EBCR
 */

use EBCR\Files\DownloadController;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_state = isset( $state ) ? $state : 'blocked';
$ebcr_err   = static function ( $key ) use ( $errors ) {
	return isset( $errors[ $key ] ) ? (string) $errors[ $key ] : '';
};
?>
<p><a class="ebcr-link" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Voltar aos documentos', 'eb-credito-rural' ); ?></a></p>
<?php if ( $notice ) : ?>
	<div class="ebcr-alert ebcr-alert--<?php echo esc_attr( $notice['type'] ); ?>" role="status"><?php echo esc_html( $notice['text'] ); ?></div>
<?php endif; ?>
<div class="ebcr-esign" data-ebcr-esign data-state="<?php echo esc_attr( $ebcr_state ); ?>" data-id="<?php echo esc_attr( $s['public_id'] ); ?>" data-doc="<?php echo esc_attr( $doc_type ); ?>" data-redirect="<?php echo esc_url( add_query_arg( 'esign_msg', 'signed', $sign_url ) ); ?>">
	<div class="ebcr-card ebcr-esign-head">
		<div>
			<h2><?php esc_html_e( 'Assinar eletronicamente', 'eb-credito-rural' ); ?></h2>
			<p class="ebcr-muted"><?php echo esc_html( $title ); ?> · <?php echo esc_html( $s['protocol'] ? $s['protocol'] : __( 'Rascunho', 'eb-credito-rural' ) ); ?></p>
		</div>
		<span class="ebcr-esign-lock" aria-hidden="true"></span>
	</div>

	<?php if ( $blocker ) : ?>
		<div class="ebcr-alert ebcr-alert--warning" role="alert"><?php echo esc_html( $blocker ); ?></div>
		<p class="ebcr-actions">
			<?php if ( 'missing_identification' === $blocker_code ) : ?>
				<a class="ebcr-btn ebcr-btn--primary" href="<?php echo esc_url( $step1_url ); ?>"><?php esc_html_e( 'Preencher a etapa 1', 'eb-credito-rural' ); ?></a>
			<?php endif; ?>
			<a class="ebcr-btn" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Voltar', 'eb-credito-rural' ); ?></a>
		</p>
	<?php elseif ( 'done' === $ebcr_state ) : ?>
		<section class="ebcr-card ebcr-esign-done">
			<h3><?php esc_html_e( 'Documento assinado', 'eb-credito-rural' ); ?></h3>
			<?php if ( $signed_docs ) : ?>
				<?php $ebcr_last = $signed_docs[ count( $signed_docs ) - 1 ]; ?>
				<p><?php esc_html_e( 'O PDF assinado, com o texto e as evidências (data/hora, IP, hashes), já está na sua lista de documentos e será conferido pela equipe.', 'eb-credito-rural' ); ?></p>
				<p><a class="ebcr-btn ebcr-btn--primary" href="<?php echo esc_url( DownloadController::url( $ebcr_last ) ); ?>"><?php esc_html_e( 'Baixar o PDF assinado', 'eb-credito-rural' ); ?></a> <a class="ebcr-btn" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Voltar aos documentos', 'eb-credito-rural' ); ?></a></p>
				<dl class="ebcr-esign-kv">
					<dt><?php esc_html_e( 'Arquivo', 'eb-credito-rural' ); ?></dt><dd><?php echo esc_html( $ebcr_last['original_name'] ); ?> <span class="ebcr-muted ebcr-small">(<?php echo esc_html( Helpers::size( $ebcr_last['size'] ) ); ?>)</span></dd>
					<dt><?php esc_html_e( 'Assinado em', 'eb-credito-rural' ); ?></dt><dd><?php echo esc_html( \EBCR\Esign\Service::sao_paulo_date( strtotime( $ebcr_last['signature']['signed_at'] . ' UTC' ) ) ); ?></dd>
					<dt><?php esc_html_e( 'Identificador da assinatura', 'eb-credito-rural' ); ?></dt><dd><code><?php echo esc_html( $ebcr_last['signature']['public_id'] ); ?></code></dd>
					<dt><?php esc_html_e( 'Hash SHA-256 do texto', 'eb-credito-rural' ); ?></dt><dd><code class="ebcr-esign-hash"><?php echo esc_html( $ebcr_last['signature']['text_hash'] ); ?></code></dd>
					<dt><?php esc_html_e( 'Hash SHA-256 das evidências', 'eb-credito-rural' ); ?></dt><dd><code class="ebcr-esign-hash"><?php echo esc_html( $ebcr_last['signature']['evidence_hash'] ); ?></code></dd>
				</dl>
			<?php else : ?>
				<p><?php esc_html_e( 'Nenhum documento assinado encontrado para este item.', 'eb-credito-rural' ); ?></p>
				<p><a class="ebcr-btn" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Voltar aos documentos', 'eb-credito-rural' ); ?></a></p>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<div class="ebcr-grid">
			<div class="ebcr-col-main">
				<section class="ebcr-card">
					<h3><?php esc_html_e( 'Texto do documento', 'eb-credito-rural' ); ?></h3>
					<div class="ebcr-esign-text" tabindex="0" aria-label="<?php esc_attr_e( 'Texto que será assinado', 'eb-credito-rural' ); ?>">
						<?php foreach ( preg_split( '/\n{2,}/', $text ) as $ebcr_para ) : ?>
							<p><?php echo nl2br( esc_html( trim( $ebcr_para ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html + nl2br. ?></p>
						<?php endforeach; ?>
					</div>
					<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Leia o texto completo. O PDF gerado conterá exatamente este texto, seus dados de assinante e as evidências da assinatura.', 'eb-credito-rural' ); ?></p>
				</section>

				<section class="ebcr-card" id="ebcr-esign-step">
					<?php if ( ! empty( $errors['_'] ) ) : ?>
						<div class="ebcr-alert ebcr-alert--error" role="alert"><?php echo esc_html( $errors['_'] ); ?></div>
					<?php endif; ?>
					<?php if ( 'intro' === $ebcr_state ) : ?>
						<h3><?php esc_html_e( '1. Receber o código por e-mail', 'eb-credito-rural' ); ?></h3>
						<p><?php /* translators: %s: e-mail mascarado */ printf( esc_html__( 'Enviaremos um código de 6 dígitos para %s. Ele vale por 10 minutos e confirma que é você quem está assinando.', 'eb-credito-rural' ), '<strong>' . esc_html( $email_masked ) . '</strong>' ); ?></p>
						<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-form" data-esign-request>
							<input type="hidden" name="action" value="ebcr_esign_request">
							<input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
							<input type="hidden" name="doc" value="<?php echo esc_attr( $doc_type ); ?>">
							<?php echo ebcr_nonce_field( 'esign' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo ebcr_honeypot_fields(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<button type="submit" class="ebcr-btn ebcr-btn--primary"><?php esc_html_e( 'Enviar código por e-mail', 'eb-credito-rural' ); ?></button>
							<span class="ebcr-upload-status" aria-live="polite" data-esign-status></span>
						</form>
					<?php else : ?>
						<h3><?php esc_html_e( '2. Confirmar e assinar', 'eb-credito-rural' ); ?></h3>
						<p><?php /* translators: %s: e-mail mascarado */ printf( esc_html__( 'Código enviado para %s.', 'eb-credito-rural' ), '<strong>' . esc_html( $email_masked ) . '</strong>' ); ?> <span class="ebcr-muted"><?php esc_html_e( 'Válido por', 'eb-credito-rural' ); ?> <span data-esign-countdown="<?php echo esc_attr( (string) $expires_in ); ?>"><?php echo esc_html( sprintf( '%02d:%02d', (int) floor( $expires_in / 60 ), $expires_in % 60 ) ); ?></span>.</span></p>
						<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-form ebcr-esign-form" data-esign-sign novalidate>
							<input type="hidden" name="action" value="ebcr_esign_sign">
							<input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
							<input type="hidden" name="doc" value="<?php echo esc_attr( $doc_type ); ?>">
							<?php echo ebcr_nonce_field( 'esign' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo ebcr_honeypot_fields(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo ebcr_error_summary( array_diff_key( $errors, array( '_' => 1 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php
							echo ebcr_input(
								'codigo',
								__( 'Código recebido por e-mail', 'eb-credito-rural' ),
								'',
								array(
									'inputmode'    => 'numeric',
									'autocomplete' => 'one-time-code',
									'maxlength'    => 6,
									'pattern'      => '[0-9]{6}',
									'required'     => true,
									'class'        => 'ebcr-esign-code',
									'placeholder'  => '000000',
								),
								$ebcr_err( 'codigo' ),
								sprintf( /* translators: %d: tentativas */ __( '6 dígitos; até %d tentativas por código.', 'eb-credito-rural' ), (int) $max_attempts )
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo ebcr_input(
								'nome_confirmacao',
								__( 'Seu nome completo, como na etapa 1', 'eb-credito-rural' ),
								isset( $values['nome_confirmacao'] ) ? $values['nome_confirmacao'] : '',
								array(
									'required'     => true,
									'autocomplete' => 'name',
									'maxlength'    => 190,
								),
								$ebcr_err( 'nome_confirmacao' ),
								__( 'Acentos e maiúsculas não importam.', 'eb-credito-rural' )
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo ebcr_consent(
								'aceite',
								sprintf(
									/* translators: 1: nome, 2: CPF formatado */
									__( 'Declaro que li e concordo com o texto acima e que os dados do assinante (%1$s, CPF %2$s) são meus e estão corretos.', 'eb-credito-rural' ),
									'<strong>' . esc_html( $signer['signer_name'] ) . '</strong>',
									'<strong>' . esc_html( Helpers::format_document( $signer['signer_document'] ) ) . '</strong>'
								),
								true,
								$ebcr_err( 'aceite' )
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
							<div class="ebcr-actions">
								<button type="submit" class="ebcr-btn ebcr-btn--primary" data-esign-submit><?php esc_html_e( 'Assinar', 'eb-credito-rural' ); ?></button>
								<span class="ebcr-upload-status" aria-live="polite" data-esign-status></span>
							</div>
						</form>
						<div class="ebcr-esign-secondary">
							<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-inline-form" data-esign-request>
								<input type="hidden" name="action" value="ebcr_esign_request">
								<input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
								<input type="hidden" name="doc" value="<?php echo esc_attr( $doc_type ); ?>">
								<?php echo ebcr_nonce_field( 'esign' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php echo ebcr_honeypot_fields(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<button type="submit" class="ebcr-btn ebcr-btn--ghost ebcr-btn--small"><?php esc_html_e( 'Não recebi: reenviar código', 'eb-credito-rural' ); ?></button>
								<span class="ebcr-upload-status ebcr-small" aria-live="polite" data-esign-status></span>
							</form>
							<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-inline-form">
								<input type="hidden" name="action" value="ebcr_esign_cancel">
								<input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
								<input type="hidden" name="doc" value="<?php echo esc_attr( $doc_type ); ?>">
								<?php echo ebcr_nonce_field( 'esign' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<button type="submit" class="ebcr-btn ebcr-btn--ghost ebcr-btn--small"><?php esc_html_e( 'Cancelar', 'eb-credito-rural' ); ?></button>
							</form>
						</div>
					<?php endif; ?>
				</section>
			</div>
			<aside class="ebcr-col-side">
				<div class="ebcr-card">
					<h3><?php esc_html_e( 'Assinante', 'eb-credito-rural' ); ?></h3>
					<dl class="ebcr-esign-kv">
						<dt><?php esc_html_e( 'Nome', 'eb-credito-rural' ); ?></dt><dd><?php echo esc_html( $signer['signer_name'] ); ?></dd>
						<dt><?php esc_html_e( 'CPF', 'eb-credito-rural' ); ?></dt><dd><?php echo esc_html( Helpers::format_document( $signer['signer_document'] ) ); ?></dd>
						<?php if ( 'PJ' === $signer['person_type'] ) : ?>
							<dt><?php esc_html_e( 'Representando', 'eb-credito-rural' ); ?></dt><dd><?php echo esc_html( $signer['company'] ); ?><br><span class="ebcr-muted ebcr-small">CNPJ <?php echo esc_html( Helpers::format_document( $signer['company_document'] ) ); ?></span></dd>
						<?php endif; ?>
						<dt><?php esc_html_e( 'E-mail', 'eb-credito-rural' ); ?></dt><dd><?php echo esc_html( $signer['email'] ); ?></dd>
					</dl>
					<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Dados errados?', 'eb-credito-rural' ); ?>
					<?php
					if ( $can_edit ) :
						?>
						<a class="ebcr-link" href="<?php echo esc_url( $step1_url ); ?>"><?php esc_html_e( 'Corrija na etapa 1', 'eb-credito-rural' ); ?></a>
						<?php
else :
	?>
						<?php esc_html_e( 'Fale com a equipe pelas mensagens da solicitação.', 'eb-credito-rural' ); ?><?php endif; ?></p>
				</div>
				<div class="ebcr-card">
					<h3><?php esc_html_e( 'Como funciona', 'eb-credito-rural' ); ?></h3>
					<ol class="ebcr-esign-steps">
						<li><?php esc_html_e( 'Leia o texto do documento.', 'eb-credito-rural' ); ?></li>
						<li><?php esc_html_e( 'Receba o código de uso único no seu e-mail.', 'eb-credito-rural' ); ?></li>
						<li><?php esc_html_e( 'Digite o código, seu nome completo e marque a declaração.', 'eb-credito-rural' ); ?></li>
						<li><?php esc_html_e( 'Geramos um PDF com o texto, seus dados e as evidências (data/hora, IP, navegador e hashes), que entra na lista de documentos.', 'eb-credito-rural' ); ?></li>
					</ol>
					<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Assinatura eletrônica simples nos termos da Lei 14.063/2020 e da MP 2.200-2/2001. Se preferir, envie o arquivo assinado à mão pela lista de documentos.', 'eb-credito-rural' ); ?></p>
				</div>
				<?php if ( $signed_docs ) : ?>
				<div class="ebcr-card ebcr-card--warn">
					<h3><?php esc_html_e( 'Já assinado', 'eb-credito-rural' ); ?></h3>
					<ul class="ebcr-doclist">
						<?php foreach ( $signed_docs as $ebcr_d ) : ?>
							<li><a href="<?php echo esc_url( DownloadController::url( $ebcr_d ) ); ?>"><?php echo esc_html( $ebcr_d['original_name'] ); ?></a><br><span class="ebcr-muted ebcr-small"><?php echo esc_html( \EBCR\Esign\Service::sao_paulo_date( strtotime( $ebcr_d['signature']['signed_at'] . ' UTC' ) ) ); ?></span></li>
						<?php endforeach; ?>
					</ul>
					<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Uma nova assinatura substitui o PDF anterior gerado aqui (a menos que ele já tenha sido aceito pela equipe). Arquivos enviados manualmente não são alterados.', 'eb-credito-rural' ); ?></p>
				</div>
				<?php endif; ?>
			</aside>
		</div>
	<?php endif; ?>
</div>
