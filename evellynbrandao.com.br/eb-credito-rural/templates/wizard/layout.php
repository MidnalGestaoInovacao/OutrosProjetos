<?php
/**
 * Moldura do formulário em etapas. Variáveis: $s, $step, $steps, $content, $help, $wizard.
 *
 * @package EBCR
 */

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_msg = isset( $_GET['ebcr_msg'] ) ? sanitize_key( wp_unslash( $_GET['ebcr_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
?>
<div class="ebcr-wizard" data-ebcr-wizard data-step="<?php echo esc_attr( (string) $step ); ?>">
	<ol class="ebcr-steps" aria-label="<?php esc_attr_e( 'Etapas', 'eb-credito-rural' ); ?>">
	<?php foreach ( $steps as $ebcr_n => $ebcr_st ) : ?>
		<?php $ebcr_reachable = $ebcr_n <= ( isset( $max_step ) ? (int) $max_step : (int) $s['current_step'] ); ?>
		<li class="<?php echo $ebcr_n === $step ? 'is-current' : ( $ebcr_n < $step ? 'is-done' : '' ); ?>"<?php echo $ebcr_n === $step ? ' aria-current="step"' : ''; ?>>
			<?php if ( $ebcr_reachable && $ebcr_n !== $step ) : ?>
				<a href="
				<?php
				echo esc_url(
					Helpers::portal_url(
						array(
							'ebcr_view' => 'formulario',
							'id'        => $s['public_id'],
							'etapa'     => $ebcr_n,
						)
					)
				);
				?>
							"><span class="ebcr-step-n"><?php echo (int) $ebcr_n; ?></span> <span class="ebcr-step-t"><?php echo esc_html( $ebcr_st['title'] ); ?></span></a>
			<?php else : ?>
				<span><span class="ebcr-step-n"><?php echo (int) $ebcr_n; ?></span> <span class="ebcr-step-t"><?php echo esc_html( $ebcr_st['title'] ); ?></span></span>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
	</ol>
	<div class="ebcr-card">
		<h2><?php /* translators: 1: número, 2: título */ printf( esc_html__( 'Etapa %1$d — %2$s', 'eb-credito-rural' ), (int) $step, esc_html( $steps[ $step ]['title'] ) ); ?></h2>
		<?php
		if ( ! empty( $help[ $step ] ) ) :
			?>
			<p class="ebcr-help-intro"><?php echo esc_html( $help[ $step ] ); ?></p><?php endif; ?>
		<noscript><p class="ebcr-alert ebcr-alert--info"><?php esc_html_e( 'Seu navegador está sem JavaScript: o formulário funciona, mas sem salvamento automático nem validação instantânea.', 'eb-credito-rural' ); ?></p></noscript>
		<div class="ebcr-autosave" aria-live="polite" data-autosave-status></div>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template já escapado. ?>
	</div>
</div>
