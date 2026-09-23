<?php
/**
 * Detalhe da solicitação (cliente).
 * Variáveis: $s, $history, $requests, $documents, $messages, $slots, $can_edit, $can_upload, $can_cancel, $can_message, $flash, $just_sent.
 *
 * @package EBCR
 */

use EBCR\Domain\Status;
use EBCR\Files\DownloadController;
use EBCR\Forms\DocumentMatrix;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_open = array_filter(
	$requests,
	static function ( $r ) {
		return empty( $r['fulfilled_at'] );
	}
);
?>
<?php if ( $just_sent ) : ?>
	<div class="ebcr-alert ebcr-alert--success" role="status"><strong><?php esc_html_e( 'Solicitação enviada!', 'eb-credito-rural' ); ?></strong> <?php /* translators: %s: protocolo */ printf( esc_html__( 'Seu protocolo é %s. Enviamos um e-mail de confirmação.', 'eb-credito-rural' ), '<strong>' . esc_html( $s['protocol'] ) . '</strong>' ); ?></div>
<?php endif; ?>
<?php if ( ! empty( $flash['errors'] ) ) : ?>
	<div class="ebcr-alert ebcr-alert--error" role="alert"><?php echo esc_html( implode( ' ', $flash['errors'] ) ); ?></div>
<?php endif; ?>
<p><a class="ebcr-link" href="<?php echo esc_url( Helpers::portal_url() ); ?>">&larr; <?php esc_html_e( 'Minhas solicitações', 'eb-credito-rural' ); ?></a></p>
<div class="ebcr-card ebcr-sub-head">
	<div>
		<h2><?php echo esc_html( $s['protocol'] ? $s['protocol'] : __( 'Rascunho', 'eb-credito-rural' ) ); ?></h2>
		<p class="ebcr-muted"><?php echo esc_html( Helpers::money( $s['requested_amount'] ) ); ?> · <?php echo esc_html( Helpers::date( $s['submitted_at'] ? $s['submitted_at'] : $s['created_at'] ) ); ?></p>
	</div>
	<div class="ebcr-sub-status"><?php echo ebcr_status_badge( $s['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><p><?php echo esc_html( Status::client_text( $s['status'] ) ); ?></p></div>
</div>
<?php if ( $can_edit ) : ?>
	<p><a class="ebcr-btn ebcr-btn--primary" href="
	<?php
	echo esc_url(
		Helpers::portal_url(
			array(
				'ebcr_view' => 'formulario',
				'id'        => $s['public_id'],
			)
		)
	);
	?>
													"><?php esc_html_e( 'Editar / continuar o preenchimento', 'eb-credito-rural' ); ?></a></p>
<?php endif; ?>

<?php if ( $ebcr_open && $can_upload ) : ?>
<section class="ebcr-card ebcr-card--warn">
	<h3><?php esc_html_e( 'Pendências solicitadas pela equipe', 'eb-credito-rural' ); ?></h3>
	<?php foreach ( $ebcr_open as $ebcr_r ) : ?>
		<form class="ebcr-pending" method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" enctype="multipart/form-data" data-ebcr-upload data-doc-type="<?php echo esc_attr( $ebcr_r['doc_type'] ); ?>" data-request-id="<?php echo esc_attr( (string) $ebcr_r['id'] ); ?>">
			<div><strong><?php echo esc_html( $ebcr_r['label'] ); ?></strong>
			<?php
			if ( $ebcr_r['note'] ) :
				?>
				<br><span class="ebcr-muted"><?php echo esc_html( $ebcr_r['note'] ); ?></span><?php endif; ?><br><span class="ebcr-muted ebcr-small"><?php /* translators: %s: data */ printf( esc_html__( 'Solicitado em %s', 'eb-credito-rural' ), esc_html( Helpers::date( $ebcr_r['requested_at'] ) ) ); ?></span></div>
			<div class="ebcr-upload-controls">
				<input type="hidden" name="action" value="ebcr_request_upload">
				<input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<input type="hidden" name="doc_type" value="<?php echo esc_attr( $ebcr_r['doc_type'] ); ?>">
				<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) $ebcr_r['id'] ); ?>">
				<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<label class="ebcr-sr" for="ebcr-req-<?php echo esc_attr( (string) $ebcr_r['id'] ); ?>"><?php esc_html_e( 'Arquivo', 'eb-credito-rural' ); ?></label>
				<input type="file" id="ebcr-req-<?php echo esc_attr( (string) $ebcr_r['id'] ); ?>" name="arquivo" required accept=".pdf,.jpg,.jpeg,.png">
				<button type="submit" class="ebcr-btn ebcr-btn--small ebcr-btn--primary"><?php esc_html_e( 'Enviar', 'eb-credito-rural' ); ?></button>
				<span class="ebcr-upload-status" aria-live="polite"></span>
			</div>
		</form>
	<?php endforeach; ?>
</section>
<?php endif; ?>

<div class="ebcr-grid">
	<div class="ebcr-col-main">
		<section class="ebcr-card">
			<h3><?php esc_html_e( 'Linha do tempo', 'eb-credito-rural' ); ?></h3>
			<ol class="ebcr-timeline">
			<?php foreach ( $history as $ebcr_h ) : ?>
				<li><span class="ebcr-timeline-dot" style="--ebcr-badge:<?php echo esc_attr( Status::color( $ebcr_h['to_status'] ) ); ?>"></span><div><strong><?php echo esc_html( Status::label( $ebcr_h['to_status'] ) ); ?></strong> <span class="ebcr-muted ebcr-small"><?php echo esc_html( Helpers::date( $ebcr_h['created_at'] ) ); ?></span>
				<?php
				if ( $ebcr_h['comment_client'] ) :
					?>
					<p><?php echo esc_html( $ebcr_h['comment_client'] ); ?></p><?php endif; ?></div></li>
			<?php endforeach; ?>
			<?php
			if ( ! $history ) :
				?>
				<li class="ebcr-muted"><?php esc_html_e( 'Ainda não enviada.', 'eb-credito-rural' ); ?></li><?php endif; ?>
			</ol>
		</section>

		<section class="ebcr-card">
			<h3><?php esc_html_e( 'Documentos enviados', 'eb-credito-rural' ); ?></h3>
			<?php
			if ( ! $documents ) :
				?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhum documento enviado.', 'eb-credito-rural' ); ?></p><?php endif; ?>
			<ul class="ebcr-doclist">
			<?php foreach ( $documents as $ebcr_d ) : ?>
				<li>
					<div><strong><?php echo esc_html( DocumentMatrix::label( $ebcr_d['doc_type'] ) ); ?></strong><br><a href="<?php echo esc_url( DownloadController::url( $ebcr_d ) ); ?>"><?php echo esc_html( $ebcr_d['original_name'] ); ?></a> <span class="ebcr-muted ebcr-small">(<?php echo esc_html( Helpers::size( $ebcr_d['size'] ) ); ?>)</span>
					<?php
					if ( $ebcr_d['expires_at'] ) :
						?>
						<span class="ebcr-muted ebcr-small"><?php /* translators: %s: data */ printf( esc_html__( 'válido até %s', 'eb-credito-rural' ), esc_html( wp_date( get_option( 'date_format' ), strtotime( $ebcr_d['expires_at'] ) ) ) ); ?></span><?php endif; ?></div>
					<div class="ebcr-doc-review ebcr-review--<?php echo esc_attr( $ebcr_d['review_status'] ); ?>">
						<?php
						$ebcr_labels = array(
							'pendente' => __( 'Em conferência', 'eb-credito-rural' ),
							'aceito'   => __( 'Aceito', 'eb-credito-rural' ),
							'recusado' => __( 'Recusado', 'eb-credito-rural' ),
						);
						echo esc_html( isset( $ebcr_labels[ $ebcr_d['review_status'] ] ) ? $ebcr_labels[ $ebcr_d['review_status'] ] : $ebcr_d['review_status'] );
						?>
						<?php
						if ( 'recusado' === $ebcr_d['review_status'] && $ebcr_d['review_note'] ) :
							?>
							<br><span class="ebcr-small"><?php echo esc_html( $ebcr_d['review_note'] ); ?></span><?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
			</ul>
		</section>

		<section class="ebcr-card" id="ebcr-mensagens">
			<h3><?php esc_html_e( 'Mensagens com a equipe', 'eb-credito-rural' ); ?></h3>
			<div class="ebcr-thread">
			<?php foreach ( $messages as $ebcr_m ) : ?>
				<div class="ebcr-msg<?php echo (int) $ebcr_m['author_id'] === (int) $s['user_id'] ? ' ebcr-msg--me' : ''; ?>">
					<div class="ebcr-msg-meta"><strong><?php echo esc_html( (int) $ebcr_m['author_id'] === (int) $s['user_id'] ? __( 'Você', 'eb-credito-rural' ) : __( 'Equipe', 'eb-credito-rural' ) ); ?></strong> · <?php echo esc_html( Helpers::date( $ebcr_m['created_at'] ) ); ?></div>
					<p><?php echo nl2br( esc_html( $ebcr_m['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html + nl2br. ?></p>
				</div>
			<?php endforeach; ?>
			<?php
			if ( ! $messages ) :
				?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma mensagem ainda.', 'eb-credito-rural' ); ?></p><?php endif; ?>
			</div>
			<?php if ( $can_message ) : ?>
			<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-form">
				<input type="hidden" name="action" value="ebcr_client_message">
				<input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo ebcr_textarea(
					'mensagem',
					__( 'Escreva para a equipe', 'eb-credito-rural' ),
					'',
					array(
						'required'  => true,
						'rows'      => 3,
						'maxlength' => 5000,
					),
					isset( $flash['errors']['mensagem'] ) ? $flash['errors']['mensagem'] : ''
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<button type="submit" class="ebcr-btn"><?php esc_html_e( 'Enviar mensagem', 'eb-credito-rural' ); ?></button>
			</form>
			<?php endif; ?>
		</section>
	</div>
	<aside class="ebcr-col-side">
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Checklist de documentos', 'eb-credito-rural' ); ?></h3>
			<?php if ( $slots['required_total'] ) : ?>
				<div class="ebcr-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $slots['required_total'] ); ?>" aria-valuenow="<?php echo esc_attr( (string) $slots['required_done'] ); ?>"><span style="width:<?php echo esc_attr( (string) round( 100 * $slots['required_done'] / max( 1, $slots['required_total'] ) ) ); ?>%"></span></div>
				<p class="ebcr-muted ebcr-small"><?php /* translators: 1: feitos, 2: total */ printf( esc_html__( '%1$d de %2$d obrigatórios', 'eb-credito-rural' ), (int) $slots['required_done'], (int) $slots['required_total'] ); ?></p>
			<?php endif; ?>
			<ul class="ebcr-checklist">
			<?php foreach ( $slots['slots'] as $ebcr_slot ) : ?>
				<li class="<?php echo $ebcr_slot['satisfied'] ? 'is-done' : ( $ebcr_slot['required'] ? 'is-missing' : '' ); ?>"><?php echo esc_html( $ebcr_slot['label'] . ( $ebcr_slot['ref_label'] ? ' — ' . $ebcr_slot['ref_label'] : '' ) ); ?>
				<?php
				if ( ! $ebcr_slot['required'] ) :
					?>
					<span class="ebcr-muted ebcr-small"><?php esc_html_e( '(opcional)', 'eb-credito-rural' ); ?></span><?php endif; ?></li>
			<?php endforeach; ?>
			</ul>
		</div>
		<?php if ( $can_cancel && Status::DRAFT !== $s['status'] ) : ?>
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Cancelar solicitação', 'eb-credito-rural' ); ?></h3>
			<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Tem certeza? Esta ação não pode ser desfeita.', 'eb-credito-rural' ) ); ?>');">
				<input type="hidden" name="action" value="ebcr_cancel_submission">
				<input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'cancel' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo ebcr_textarea(
					'motivo',
					__( 'Motivo (opcional)', 'eb-credito-rural' ),
					'',
					array(
						'rows'      => 2,
						'maxlength' => 500,
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<button type="submit" class="ebcr-btn ebcr-btn--danger ebcr-btn--block"><?php esc_html_e( 'Cancelar solicitação', 'eb-credito-rural' ); ?></button>
			</form>
		</div>
		<?php endif; ?>
	</aside>
</div>
