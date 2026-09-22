<?php
/**
 * Perfil. Variáveis: $user, $flash, $flash_pw, $phone, $whatsapp.
 *
 * @package EBCR
 */


defined( 'ABSPATH' ) || exit;
$ebcr_v = $flash['values'];
$ebcr_e = $flash['errors'];
?>
<div class="ebcr-grid">
	<form class="ebcr-form ebcr-card ebcr-col-main" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h2><?php esc_html_e( 'Meus dados de contato', 'eb-credito-rural' ); ?></h2>
		<?php echo ebcr_error_summary( $ebcr_e ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="action" value="ebcr_profile">
		<?php echo ebcr_nonce_field( 'profile' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php
		echo ebcr_input(
			'nome',
			__( 'Nome completo', 'eb-credito-rural' ),
			isset( $ebcr_v['nome'] ) ? $ebcr_v['nome'] : $user->display_name,
			array(
				'required'  => true,
				'maxlength' => 120,
			),
			isset( $ebcr_e['nome'] ) ? $ebcr_e['nome'] : ''
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_input(
			'email',
			__( 'E-mail', 'eb-credito-rural' ),
			$user->user_email,
			array(
				'type'     => 'email',
				'disabled' => true,
			),
			'',
			__( 'Para trocar o e-mail, fale com a equipe.', 'eb-credito-rural' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_input(
			'telefone',
			__( 'Telefone', 'eb-credito-rural' ),
			isset( $ebcr_v['telefone'] ) ? $ebcr_v['telefone'] : $phone,
			array(
				'type'      => 'tel',
				'required'  => true,
				'data-mask' => 'phone',
			),
			isset( $ebcr_e['telefone'] ) ? $ebcr_e['telefone'] : ''
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_input(
			'whatsapp',
			__( 'WhatsApp', 'eb-credito-rural' ),
			isset( $ebcr_v['whatsapp'] ) ? $ebcr_v['whatsapp'] : $whatsapp,
			array(
				'type'      => 'tel',
				'data-mask' => 'phone',
			),
			isset( $ebcr_e['whatsapp'] ) ? $ebcr_e['whatsapp'] : ''
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<button type="submit" class="ebcr-btn ebcr-btn--primary"><?php esc_html_e( 'Salvar', 'eb-credito-rural' ); ?></button>
	</form>
	<form class="ebcr-form ebcr-card ebcr-col-side" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<h3><?php esc_html_e( 'Alterar senha', 'eb-credito-rural' ); ?></h3>
		<?php echo ebcr_error_summary( $flash_pw['errors'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="action" value="ebcr_password">
		<?php echo ebcr_nonce_field( 'password' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php
		echo ebcr_input(
			'senha_atual',
			__( 'Senha atual', 'eb-credito-rural' ),
			'',
			array(
				'type'         => 'password',
				'required'     => true,
				'autocomplete' => 'current-password',
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_input(
			'senha',
			__( 'Nova senha', 'eb-credito-rural' ),
			'',
			array(
				'type'          => 'password',
				'required'      => true,
				'autocomplete'  => 'new-password',
				'data-strength' => '1',
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<div class="ebcr-strength" aria-live="polite" data-strength-meter hidden><span class="ebcr-strength-bar"><i></i></span><span class="ebcr-strength-label"></span></div>
		<?php
		echo ebcr_input(
			'senha2',
			__( 'Confirme a nova senha', 'eb-credito-rural' ),
			'',
			array(
				'type'         => 'password',
				'required'     => true,
				'autocomplete' => 'new-password',
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<button type="submit" class="ebcr-btn"><?php esc_html_e( 'Alterar senha', 'eb-credito-rural' ); ?></button>
	</form>
</div>
