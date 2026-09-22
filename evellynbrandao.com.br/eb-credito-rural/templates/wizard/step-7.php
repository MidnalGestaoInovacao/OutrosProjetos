<?php
/**
 * Etapa 7 — resumo, declarações e envio. Variáveis: $s, $errors, $wizard, $captcha.
 *
 * @package EBCR
 */

use EBCR\Domain\Consent;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_sum   = $wizard->summary( $s );
$ebcr_check = $wizard->validate_all( $s );
$ebcr_rules = \EBCR\Forms\SubmissionRules::can_submit( get_current_user_id(), (int) $s['id'] );
?>
<div data-ebcr-step="7">
	<?php echo ebcr_error_summary( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<section class="ebcr-summary">
		<h3><?php esc_html_e( 'Resumo', 'eb-credito-rural' ); ?></h3>
		<dl class="ebcr-dl">
			<dt><?php esc_html_e( 'Tomador', 'eb-credito-rural' ); ?></dt><dd><?php echo esc_html( $ebcr_sum['tomador'] ); ?> <span class="ebcr-muted"><?php echo esc_html( $ebcr_sum['documento'] ); ?></span></dd>
			<dt><?php esc_html_e( 'Imóveis', 'eb-credito-rural' ); ?></dt><dd><?php echo (int) $ebcr_sum['imoveis']; ?></dd>
			<dt><?php esc_html_e( 'Atividades', 'eb-credito-rural' ); ?></dt><dd><?php echo esc_html( $ebcr_sum['atividades'] ? $ebcr_sum['atividades'] : '—' ); ?></dd>
			<dt><?php esc_html_e( 'Valor solicitado', 'eb-credito-rural' ); ?></dt><dd><?php echo esc_html( Helpers::money( $ebcr_sum['valor'] ) ); ?> · <?php echo esc_html( $ebcr_sum['finalidade'] ); ?> · <?php echo $ebcr_sum['prazo'] ? esc_html( sprintf( /* translators: %d: meses */ __( '%d meses', 'eb-credito-rural' ), (int) $ebcr_sum['prazo'] ) ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Garantias', 'eb-credito-rural' ); ?></dt><dd><?php echo (int) $ebcr_sum['garantias']; ?></dd>
			<dt><?php esc_html_e( 'Documentos obrigatórios', 'eb-credito-rural' ); ?></dt><dd><?php echo (int) $ebcr_sum['documentos']['required_done']; ?> / <?php echo (int) $ebcr_sum['documentos']['required_total']; ?></dd>
		</dl>
		<?php if ( $ebcr_check ) : ?>
			<div class="ebcr-alert ebcr-alert--warning" role="alert">
				<strong><?php esc_html_e( 'Há pendências antes do envio:', 'eb-credito-rural' ); ?></strong>
				<ul>
				<?php foreach ( $ebcr_check as $ebcr_step => $ebcr_errs ) : ?>
					<li><a href="
					<?php
					echo esc_url(
						Helpers::portal_url(
							array(
								'ebcr_view' => 'formulario',
								'id'        => $s['public_id'],
								'etapa'     => $ebcr_step,
							)
						)
					);
					?>
									"><?php /* translators: %d: etapa */ printf( esc_html__( 'Etapa %d', 'eb-credito-rural' ), (int) $ebcr_step ); ?></a>: <?php echo esc_html( implode( ' ', array_slice( $ebcr_errs, 0, 3 ) ) ); ?><?php echo count( $ebcr_errs ) > 3 ? ' …' : ''; ?></li>
				<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php
		if ( ! $ebcr_rules['allowed'] ) :
			?>
			<div class="ebcr-alert ebcr-alert--warning" role="alert"><?php echo esc_html( $ebcr_rules['message'] ); ?></div><?php endif; ?>
	</section>
	<form class="ebcr-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-ebcr-submit>
		<input type="hidden" name="action" value="ebcr_wizard_submit"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
		<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo ebcr_honeypot_fields(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<fieldset class="ebcr-consents"><legend><?php esc_html_e( 'Declarações e consentimentos', 'eb-credito-rural' ); ?></legend>
		<?php foreach ( Consent::for_moment( 'submit' ) as $ebcr_key => $ebcr_p ) : ?>
			<?php
			$ebcr_url = Consent::url( $ebcr_key );
			echo ebcr_consent( 'consent_' . $ebcr_key, '<strong>' . esc_html( $ebcr_p['title'] ) . '</strong> — ' . esc_html( $ebcr_p['text'] ) . ( $ebcr_url ? ' <a href="' . esc_url( $ebcr_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ler o texto completo', 'eb-credito-rural' ) . '</a>' : '' ) . ' <span class="ebcr-muted">(v' . esc_html( $ebcr_p['version'] ) . ')</span>', $ebcr_p['required'], isset( $errors[ 'consent_' . $ebcr_key ] ) ? $errors[ 'consent_' . $ebcr_key ] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<?php endforeach; ?>
		</fieldset>
		<?php echo ebcr_captcha_field( $captcha ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="ebcr-actions ebcr-wizard-nav">
			<a class="ebcr-btn" href="
			<?php
			echo esc_url(
				Helpers::portal_url(
					array(
						'ebcr_view' => 'formulario',
						'id'        => $s['public_id'],
						'etapa'     => 6,
					)
				)
			);
			?>
			"><?php esc_html_e( 'Voltar', 'eb-credito-rural' ); ?></a>
			<button type="submit" class="ebcr-btn ebcr-btn--primary"<?php echo ( $ebcr_check || ! $ebcr_rules['allowed'] ) ? ' disabled' : ''; ?>><?php esc_html_e( 'Enviar solicitação', 'eb-credito-rural' ); ?></button>
		</div>
	</form>
</div>
