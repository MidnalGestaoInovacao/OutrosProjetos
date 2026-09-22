<?php
/**
 * Etapa 6 — documentos. Variáveis: $s, $errors, $wizard.
 *
 * @package EBCR
 */

use EBCR\Files\DownloadController;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;
$ebcr_slots    = $wizard->document_slots( $s );
$ebcr_accept   = '.' . implode( ',.', Options::allowed_extensions() );
$ebcr_labels   = array(
	'pendente' => __( 'Em conferência', 'eb-credito-rural' ),
	'aceito'   => __( 'Aceito', 'eb-credito-rural' ),
	'recusado' => __( 'Recusado', 'eb-credito-rural' ),
);
$ebcr_doc_item = static function ( array $d ) use ( $s, $ebcr_labels ) {
	$html = '<li class="ebcr-doc"><a href="' . esc_url( DownloadController::url( $d ) ) . '">' . esc_html( $d['original_name'] ) . '</a> <span class="ebcr-muted ebcr-small">(' . esc_html( Helpers::size( $d['size'] ) ) . ')</span> <span class="ebcr-review ebcr-review--' . esc_attr( $d['review_status'] ) . '">' . esc_html( isset( $ebcr_labels[ $d['review_status'] ] ) ? $ebcr_labels[ $d['review_status'] ] : $d['review_status'] ) . '</span>';
	if ( 'recusado' === $d['review_status'] && $d['review_note'] ) {
		$html .= '<br><span class="ebcr-error ebcr-small">' . esc_html( $d['review_note'] ) . '</span>';
	}
	if ( 'aceito' !== $d['review_status'] ) {
		$html .= ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ebcr-inline-form" data-ebcr-delete="' . esc_attr( $d['public_id'] ) . '"><input type="hidden" name="action" value="ebcr_delete_document"><input type="hidden" name="doc" value="' . esc_attr( $d['public_id'] ) . '">' . ebcr_nonce_field( 'wizard' ) . '<button type="submit" class="ebcr-btn ebcr-btn--ghost ebcr-btn--small">' . esc_html__( 'Remover', 'eb-credito-rural' ) . '</button></form>';
	}
	return $html . '</li>';
};
?>
<div data-ebcr-step="6">
	<?php
	if ( ! empty( $errors['upload'] ) ) :
		?>
		<div class="ebcr-alert ebcr-alert--error" role="alert"><?php echo esc_html( $errors['upload'] ); ?></div><?php endif; ?>
	<div class="ebcr-docs-progress">
		<div class="ebcr-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) max( 1, $ebcr_slots['required_total'] ) ); ?>" aria-valuenow="<?php echo esc_attr( (string) $ebcr_slots['required_done'] ); ?>" data-doc-progress><span style="width:<?php echo esc_attr( (string) round( 100 * $ebcr_slots['required_done'] / max( 1, $ebcr_slots['required_total'] ) ) ); ?>%"></span></div>
		<p class="ebcr-muted ebcr-small" data-doc-progress-text><?php /* translators: 1: feitos, 2: total */ printf( esc_html__( '%1$d de %2$d documentos obrigatórios enviados', 'eb-credito-rural' ), (int) $ebcr_slots['required_done'], (int) $ebcr_slots['required_total'] ); ?></p>
		<p class="ebcr-muted ebcr-small"><?php /* translators: 1: extensões, 2: tamanho */ printf( esc_html__( 'Formatos aceitos: %1$s. Tamanho máximo por arquivo: %2$d MB. Fotos legíveis de documentos são aceitas.', 'eb-credito-rural' ), esc_html( strtoupper( implode( ', ', Options::allowed_extensions() ) ) ), (int) Options::int( 'max_file_size_mb' ) ); ?></p>
	</div>
	<ul class="ebcr-slots">
	<?php foreach ( $ebcr_slots['slots'] as $ebcr_slot ) : ?>
		<li class="ebcr-slot<?php echo $ebcr_slot['satisfied'] ? ' is-done' : ( $ebcr_slot['required'] ? ' is-required' : '' ); ?>" data-slot="<?php echo esc_attr( $ebcr_slot['type'] . '|' . $ebcr_slot['ref_key'] ); ?>" data-required="<?php echo $ebcr_slot['required'] ? '1' : '0'; ?>">
			<div class="ebcr-slot-head">
				<strong><?php echo esc_html( $ebcr_slot['label'] ); ?></strong>
				<?php
				if ( $ebcr_slot['ref_label'] ) :
					?>
					<span class="ebcr-pill"><?php echo esc_html( $ebcr_slot['ref_label'] ); ?></span><?php endif; ?>
				<span class="ebcr-pill <?php echo $ebcr_slot['required'] ? 'ebcr-pill--warn' : ''; ?>"><?php echo esc_html( $ebcr_slot['required'] ? __( 'Obrigatório', 'eb-credito-rural' ) : ( 'recomendado' === $ebcr_slot['level'] ? __( 'Recomendado', 'eb-credito-rural' ) : __( 'Opcional', 'eb-credito-rural' ) ) ); ?></span>
				<?php
				if ( $ebcr_slot['validity_days'] ) :
					?>
					<span class="ebcr-muted ebcr-small"><?php /* translators: %d: dias */ printf( esc_html__( 'validade: %d dias', 'eb-credito-rural' ), (int) $ebcr_slot['validity_days'] ); ?></span><?php endif; ?>
			</div>
			<?php
			if ( $ebcr_slot['help'] ) :
				?>
				<p class="ebcr-muted ebcr-small"><?php echo esc_html( $ebcr_slot['help'] ); ?></p><?php endif; ?>
			<ul class="ebcr-doclist" data-slot-docs>
				<?php
				foreach ( $ebcr_slot['documents'] as $ebcr_d ) :
					?>
					<?php echo $ebcr_doc_item( $ebcr_d ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endforeach; ?>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ebcr-upload" data-ebcr-upload data-doc-type="<?php echo esc_attr( $ebcr_slot['type'] ); ?>" data-ref-key="<?php echo esc_attr( $ebcr_slot['ref_key'] ); ?>">
				<input type="hidden" name="action" value="ebcr_wizard_upload"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="doc_type" value="<?php echo esc_attr( $ebcr_slot['type'] ); ?>"><input type="hidden" name="ref_key" value="<?php echo esc_attr( $ebcr_slot['ref_key'] ); ?>">
				<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<label class="ebcr-sr" for="<?php echo esc_attr( 'ebcr-file-' . sanitize_key( $ebcr_slot['type'] . '-' . $ebcr_slot['ref_key'] ) ); ?>"><?php esc_html_e( 'Arquivo', 'eb-credito-rural' ); ?></label>
				<input type="file" id="<?php echo esc_attr( 'ebcr-file-' . sanitize_key( $ebcr_slot['type'] . '-' . $ebcr_slot['ref_key'] ) ); ?>" name="arquivo" accept="<?php echo esc_attr( $ebcr_accept ); ?>" required>
				<button type="submit" class="ebcr-btn ebcr-btn--small ebcr-btn--primary"><?php esc_html_e( 'Enviar', 'eb-credito-rural' ); ?></button>
				<span class="ebcr-upload-status" aria-live="polite"></span>
			</form>
		</li>
	<?php endforeach; ?>
	</ul>
	<?php if ( $ebcr_slots['others'] ) : ?>
		<h4><?php esc_html_e( 'Outros documentos enviados', 'eb-credito-rural' ); ?></h4>
		<ul class="ebcr-doclist">
		<?php
		foreach ( $ebcr_slots['others'] as $ebcr_d ) :
			?>
			<?php echo $ebcr_doc_item( $ebcr_d ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endforeach; ?></ul>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ebcr-upload ebcr-upload--other" data-ebcr-upload data-doc-type="outro" data-ref-key="">
		<h4><?php esc_html_e( 'Enviar outro documento (opcional)', 'eb-credito-rural' ); ?></h4>
		<input type="hidden" name="action" value="ebcr_wizard_upload"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="doc_type" value="outro"><input type="hidden" name="ref_key" value="">
		<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<label class="ebcr-sr" for="ebcr-file-outro"><?php esc_html_e( 'Arquivo', 'eb-credito-rural' ); ?></label>
		<input type="file" id="ebcr-file-outro" name="arquivo" accept="<?php echo esc_attr( $ebcr_accept ); ?>" required>
		<button type="submit" class="ebcr-btn ebcr-btn--small"><?php esc_html_e( 'Enviar', 'eb-credito-rural' ); ?></button>
		<span class="ebcr-upload-status" aria-live="polite"></span>
	</form>
	<div class="ebcr-actions ebcr-wizard-nav">
		<a class="ebcr-btn" href="
		<?php
		echo esc_url(
			Helpers::portal_url(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $s['public_id'],
					'etapa'     => 5,
				)
			)
		);
		?>
		"><?php esc_html_e( 'Voltar', 'eb-credito-rural' ); ?></a>
		<a class="ebcr-btn ebcr-btn--primary" href="
		<?php
		echo esc_url(
			Helpers::portal_url(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $s['public_id'],
					'etapa'     => 7,
				)
			)
		);
		?>
		"><?php esc_html_e( 'Continuar para a revisão', 'eb-credito-rural' ); ?></a>
	</div>
</div>
