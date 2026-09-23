<?php
/**
 * Privacidade (LGPD). Variáveis: $user, $policies, $dpo_name, $dpo_email, $flash.
 *
 * @package EBCR
 */


defined( 'ABSPATH' ) || exit;
?>
<div class="ebcr-grid">
	<section class="ebcr-card ebcr-col-main">
		<h2><?php esc_html_e( 'Privacidade e seus direitos', 'eb-credito-rural' ); ?></h2>
		<p><?php esc_html_e( 'Você pode baixar uma cópia dos dados que temos sobre você, pedir correção ou solicitar a exclusão. Solicitações de exclusão viram uma tarefa para a equipe, porque parte dos dados pode ter retenção obrigatória por lei.', 'eb-credito-rural' ); ?></p>
		<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-inline-form">
			<input type="hidden" name="action" value="ebcr_lgpd_export">
			<?php echo ebcr_nonce_field( 'lgpd' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<button type="submit" class="ebcr-btn"><?php esc_html_e( 'Baixar meus dados (JSON)', 'eb-credito-rural' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-form">
			<h3><?php esc_html_e( 'Solicitar correção ou exclusão', 'eb-credito-rural' ); ?></h3>
			<?php echo ebcr_error_summary( $flash['errors'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="action" value="ebcr_lgpd_request">
			<?php echo ebcr_nonce_field( 'lgpd' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			echo ebcr_select(
				'tipo',
				__( 'Tipo de solicitação', 'eb-credito-rural' ),
				array(
					'correcao' => __( 'Correção de dados', 'eb-credito-rural' ),
					'exclusao' => __( 'Exclusão de dados', 'eb-credito-rural' ),
					'outro'    => __( 'Outro direito do titular', 'eb-credito-rural' ),
				),
				'',
				array( 'required' => true )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_textarea(
				'detalhes',
				__( 'Descreva o que deseja', 'eb-credito-rural' ),
				'',
				array(
					'required'  => true,
					'rows'      => 4,
					'maxlength' => 3000,
				),
				isset( $flash['errors']['detalhes'] ) ? $flash['errors']['detalhes'] : ''
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<button type="submit" class="ebcr-btn ebcr-btn--primary"><?php esc_html_e( 'Enviar solicitação', 'eb-credito-rural' ); ?></button>
		</form>
	</section>
	<aside class="ebcr-col-side">
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Encarregado (DPO)', 'eb-credito-rural' ); ?></h3>
			<p><?php echo esc_html( $dpo_name ); ?><br><a href="mailto:<?php echo esc_attr( $dpo_email ); ?>"><?php echo esc_html( $dpo_email ); ?></a></p>
			<h3><?php esc_html_e( 'Políticas', 'eb-credito-rural' ); ?></h3>
			<ul>
			<?php foreach ( $policies as $ebcr_key => $ebcr_p ) : ?>
				<?php $ebcr_url = \EBCR\Domain\Consent::url( $ebcr_key ); ?>
				<li>
				<?php
				if ( $ebcr_url ) :
					?>
					<a href="<?php echo esc_url( $ebcr_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $ebcr_p['title'] ); ?></a>
					<?php
else :
	?>
					<?php echo esc_html( $ebcr_p['title'] ); ?><?php endif; ?> <span class="ebcr-muted ebcr-small">v<?php echo esc_html( $ebcr_p['version'] ); ?></span></li>
			<?php endforeach; ?>
			</ul>
		</div>
	</aside>
</div>
