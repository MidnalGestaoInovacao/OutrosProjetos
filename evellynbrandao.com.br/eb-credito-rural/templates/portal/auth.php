<?php
/**
 * Login e cadastro. Variáveis: $tab, $login, $register, $policies, $captcha, $captcha_login, $min_pw, $redirect.
 *
 * @package EBCR
 */

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_login_tab = 'login' === $tab;
?>
<div class="ebcr-auth">
	<div class="ebcr-tabs" role="tablist">
		<a role="tab" aria-selected="<?php echo $ebcr_login_tab ? 'true' : 'false'; ?>" class="ebcr-tab<?php echo $ebcr_login_tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( Helpers::portal_url() ); ?>"><?php esc_html_e( 'Entrar', 'eb-credito-rural' ); ?></a>
		<a role="tab" aria-selected="<?php echo $ebcr_login_tab ? 'false' : 'true'; ?>" class="ebcr-tab<?php echo $ebcr_login_tab ? '' : ' is-active'; ?>" href="<?php echo esc_url( Helpers::portal_url( array( 'ebcr_view' => 'cadastro' ) ) ); ?>"><?php esc_html_e( 'Criar conta', 'eb-credito-rural' ); ?></a>
	</div>

	<?php if ( $ebcr_login_tab ) : ?>
	<form class="ebcr-form ebcr-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
		<h2><?php esc_html_e( 'Acesse sua área', 'eb-credito-rural' ); ?></h2>
		<?php echo ebcr_error_summary( $login['errors'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado no helper. ?>
		<input type="hidden" name="action" value="ebcr_login">
		<?php echo ebcr_nonce_field( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field. ?>
		<?php echo ebcr_honeypot_fields(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado no helper. ?>
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
		<?php
		echo ebcr_input(
			'email',
			__( 'E-mail', 'eb-credito-rural' ),
			isset( $login['values']['email'] ) ? $login['values']['email'] : '',
			array(
				'type'         => 'email',
				'required'     => true,
				'autocomplete' => 'username',
				'inputmode'    => 'email',
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_input(
			'senha',
			__( 'Senha', 'eb-credito-rural' ),
			'',
			array(
				'type'         => 'password',
				'required'     => true,
				'autocomplete' => 'current-password',
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php if ( $captcha_login ) : ?>
			<?php echo ebcr_captcha_field( $captcha ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado no provedor. ?>
		<?php endif; ?>
		<label class="ebcr-check" for="ebcr-lembrar"><input type="checkbox" id="ebcr-lembrar" name="lembrar" value="1"> <?php esc_html_e( 'Manter conectado neste dispositivo', 'eb-credito-rural' ); ?></label>
		<div class="ebcr-actions">
			<button type="submit" class="ebcr-btn ebcr-btn--primary"><?php esc_html_e( 'Entrar', 'eb-credito-rural' ); ?></button>
			<a class="ebcr-link" href="<?php echo esc_url( wp_lostpassword_url( Helpers::portal_url( array( 'ebcr_msg' => 'reset_sent' ) ) ) ); ?>"><?php esc_html_e( 'Esqueci minha senha', 'eb-credito-rural' ); ?></a>
		</div>
	</form>
	<?php else : ?>
	<form class="ebcr-form ebcr-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-ebcr-register>
		<h2><?php esc_html_e( 'Crie sua conta', 'eb-credito-rural' ); ?></h2>
		<p class="ebcr-muted"><?php esc_html_e( 'Após o cadastro, enviaremos um link para confirmar seu e-mail. A confirmação é obrigatória antes de enviar uma solicitação.', 'eb-credito-rural' ); ?></p>
		<?php echo ebcr_error_summary( $register['errors'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="action" value="ebcr_register">
		<?php echo ebcr_nonce_field( 'register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo ebcr_honeypot_fields(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php
		$ebcr_v = $register['values'];
		$ebcr_e = $register['errors'];
		?>
		<?php
		echo ebcr_input(
			'nome',
			__( 'Nome completo', 'eb-credito-rural' ),
			isset( $ebcr_v['nome'] ) ? $ebcr_v['nome'] : '',
			array(
				'required'     => true,
				'autocomplete' => 'name',
				'maxlength'    => 120,
			),
			isset( $ebcr_e['nome'] ) ? $ebcr_e['nome'] : ''
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_input(
			'email',
			__( 'E-mail', 'eb-credito-rural' ),
			isset( $ebcr_v['email'] ) ? $ebcr_v['email'] : '',
			array(
				'type'         => 'email',
				'required'     => true,
				'autocomplete' => 'email',
				'inputmode'    => 'email',
			),
			isset( $ebcr_e['email'] ) ? $ebcr_e['email'] : ''
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_input(
			'telefone',
			__( 'Telefone / WhatsApp (com DDD)', 'eb-credito-rural' ),
			isset( $ebcr_v['telefone'] ) ? $ebcr_v['telefone'] : '',
			array(
				'type'         => 'tel',
				'required'     => true,
				'autocomplete' => 'tel',
				'inputmode'    => 'tel',
				'data-mask'    => 'phone',
			),
			isset( $ebcr_e['telefone'] ) ? $ebcr_e['telefone'] : ''
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_input(
			'senha',
			__( 'Senha', 'eb-credito-rural' ),
			'',
			array(
				'type'          => 'password',
				'required'      => true,
				'autocomplete'  => 'new-password',
				'minlength'     => $min_pw,
				'data-strength' => '1',
			),
			isset( $ebcr_e['senha'] ) ? $ebcr_e['senha'] : '',
			sprintf( /* translators: %d: mínimo */ __( 'Mínimo de %d caracteres, com letras maiúsculas, minúsculas, números ou símbolos (pelo menos três tipos).', 'eb-credito-rural' ), $min_pw )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<div class="ebcr-strength" aria-live="polite" data-strength-meter hidden><span class="ebcr-strength-bar"><i></i></span><span class="ebcr-strength-label"></span></div>
		<?php
		echo ebcr_input(
			'senha2',
			__( 'Confirme a senha', 'eb-credito-rural' ),
			'',
			array(
				'type'         => 'password',
				'required'     => true,
				'autocomplete' => 'new-password',
			),
			isset( $ebcr_e['senha2'] ) ? $ebcr_e['senha2'] : ''
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<fieldset class="ebcr-consents"><legend><?php esc_html_e( 'Aceites', 'eb-credito-rural' ); ?></legend>
		<?php foreach ( $policies as $ebcr_key => $ebcr_p ) : ?>
			<?php
			$ebcr_url   = \EBCR\Domain\Consent::url( $ebcr_key );
			$ebcr_label = esc_html( $ebcr_p['text'] ) . ( $ebcr_url ? ' <a href="' . esc_url( $ebcr_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ler o texto completo', 'eb-credito-rural' ) . '</a>' : '' ) . ' <span class="ebcr-muted">(v' . esc_html( $ebcr_p['version'] ) . ')</span>';
			echo ebcr_consent( 'consent_' . $ebcr_key, $ebcr_label, $ebcr_p['required'], isset( $ebcr_e[ 'consent_' . $ebcr_key ] ) ? $ebcr_e[ 'consent_' . $ebcr_key ] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<?php endforeach; ?>
		</fieldset>
		<?php echo ebcr_captcha_field( $captcha ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="ebcr-actions"><button type="submit" class="ebcr-btn ebcr-btn--primary"><?php esc_html_e( 'Criar conta', 'eb-credito-rural' ); ?></button></div>
	</form>
	<?php endif; ?>
</div>
