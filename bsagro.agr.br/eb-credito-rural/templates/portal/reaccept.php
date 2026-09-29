<?php
/**
 * Novo aceite obrigatório. Variável: $policies.
 *
 * @package EBCR
 */


defined( 'ABSPATH' ) || exit;
?>
<form class="ebcr-form ebcr-card" method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>">
	<h2><?php esc_html_e( 'Políticas atualizadas', 'eb-credito-rural' ); ?></h2>
	<p><?php esc_html_e( 'Atualizamos documentos que você precisa aceitar para continuar usando a plataforma.', 'eb-credito-rural' ); ?></p>
	<input type="hidden" name="action" value="ebcr_reaccept">
	<?php echo ebcr_nonce_field( 'reaccept' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php foreach ( $policies as $ebcr_key => $ebcr_p ) : ?>
		<?php
		$ebcr_url = \EBCR\Domain\Consent::url( $ebcr_key );
		echo ebcr_consent( 'consent_' . $ebcr_key, esc_html( $ebcr_p['text'] ) . ( $ebcr_url ? ' <a href="' . esc_url( $ebcr_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ler o texto completo', 'eb-credito-rural' ) . '</a>' : '' ) . ' <span class="ebcr-muted">(v' . esc_html( $ebcr_p['version'] ) . ')</span>', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	<?php endforeach; ?>
	<div class="ebcr-actions"><button type="submit" class="ebcr-btn ebcr-btn--primary"><?php esc_html_e( 'Aceitar e continuar', 'eb-credito-rural' ); ?></button> <a class="ebcr-link" href="<?php echo esc_url( wp_logout_url( \EBCR\Support\Helpers::portal_url() ) ); ?>"><?php esc_html_e( 'Sair', 'eb-credito-rural' ); ?></a></div>
</form>
