<?php
/**
 * Tela de verificação em duas etapas (página própria, sem tema).
 * Variáveis: $site, $logo_url, $user, $method, $backup, $email_masked, $message, $locked, $backup_left, $redirect_to, $form_url, $switch_url, $logout_url.
 *
 * @package EBCR
 */

defined( 'ABSPATH' ) || exit;
$ebcr_lang = get_bloginfo( 'language' );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $ebcr_lang ? $ebcr_lang : 'pt-BR' ); ?>">
<head>
<meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( __( 'Verificação em duas etapas', 'eb-credito-rural' ) . ' — ' . $site ); ?></title>
<?php wp_print_styles( 'ebcr-2fa' ); ?>
</head>
<body class="ebcr-2fa-page">
<main class="ebcr-2fa-wrap">
	<div class="ebcr-2fa-card">
		<header class="ebcr-2fa-brand">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site ); ?>" class="ebcr-2fa-logo">
			<?php else : ?>
				<span class="ebcr-2fa-site"><?php echo esc_html( $site ); ?></span>
			<?php endif; ?>
		</header>
		<h1 class="ebcr-2fa-title"><?php esc_html_e( 'Verificação em duas etapas', 'eb-credito-rural' ); ?></h1>
		<p class="ebcr-2fa-lead">
			<?php
			if ( $backup ) {
				esc_html_e( 'Digite um dos seus códigos de backup. Cada código vale uma única vez.', 'eb-credito-rural' );
			} elseif ( 'email' === $method ) {
				/* translators: %s: e-mail mascarado */
				printf( esc_html__( 'Enviamos um código de 6 dígitos para %s. Ele vale por 10 minutos.', 'eb-credito-rural' ), '<strong>' . esc_html( $email_masked ) . '</strong>' );
			} else {
				esc_html_e( 'Abra o aplicativo autenticador no seu celular e digite o código de 6 dígitos.', 'eb-credito-rural' );
			}
			?>
		</p>
		<?php if ( $message ) : ?>
			<div class="ebcr-2fa-msg ebcr-2fa-msg--<?php echo esc_attr( $message['type'] ); ?>" role="<?php echo 'error' === $message['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $message['text'] ); ?></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="ebcr-2fa-form" autocomplete="off">
			<input type="hidden" name="action" value="ebcr_2fa_verify">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( rawurlencode( $redirect_to ) ); ?>">
			<?php if ( $backup ) : ?>
				<input type="hidden" name="backup" value="1">
			<?php endif; ?>
			<?php echo ebcr_nonce_field( '2fa_verify' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- campo de nonce gerado pelo WordPress. ?>
			<label for="ebcr-2fa-code" class="ebcr-2fa-label"><?php echo $backup ? esc_html__( 'Código de backup', 'eb-credito-rural' ) : esc_html__( 'Código de verificação', 'eb-credito-rural' ); ?></label>
			<?php if ( $backup ) : ?>
				<input type="text" id="ebcr-2fa-code" name="code" class="ebcr-2fa-input" required autofocus autocomplete="off" spellcheck="false" maxlength="12" placeholder="xxxx-xxxx" aria-describedby="ebcr-2fa-help" <?php disabled( $locked ); ?>>
				<p id="ebcr-2fa-help" class="ebcr-2fa-help">
					<?php
					/* translators: %d: quantidade */
					printf( esc_html( _n( 'Você ainda tem %d código de backup.', 'Você ainda tem %d códigos de backup.', (int) $backup_left, 'eb-credito-rural' ) ), (int) $backup_left );
					?>
				</p>
			<?php else : ?>
				<input type="text" id="ebcr-2fa-code" name="code" class="ebcr-2fa-input ebcr-2fa-input--code" required autofocus inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="6" placeholder="000000" aria-describedby="ebcr-2fa-help" <?php disabled( $locked ); ?>>
				<p id="ebcr-2fa-help" class="ebcr-2fa-help"><?php echo 'email' === $method ? esc_html__( 'Somente números. Use o código mais recente que recebeu; os anteriores deixam de valer.', 'eb-credito-rural' ) : esc_html__( 'Somente números. O código muda a cada 30 segundos no aplicativo.', 'eb-credito-rural' ); ?></p>
			<?php endif; ?>
			<label class="ebcr-2fa-check"><input type="checkbox" name="trust" value="1"> <?php esc_html_e( 'Confiar neste dispositivo por 30 dias (não pedir o código de novo neste navegador)', 'eb-credito-rural' ); ?></label>
			<button type="submit" class="ebcr-2fa-btn" <?php disabled( $locked ); ?>><?php esc_html_e( 'Confirmar e entrar', 'eb-credito-rural' ); ?></button>
		</form>
		<?php if ( 'email' === $method && ! $backup ) : ?>
			<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="ebcr-2fa-inline">
				<input type="hidden" name="action" value="ebcr_2fa_resend">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( rawurlencode( $redirect_to ) ); ?>">
				<?php echo ebcr_nonce_field( '2fa_resend' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- campo de nonce gerado pelo WordPress. ?>
				<button type="submit" class="ebcr-2fa-link-btn"><?php esc_html_e( 'Não recebeu? Reenviar código por e-mail', 'eb-credito-rural' ); ?></button>
			</form>
		<?php endif; ?>
		<nav class="ebcr-2fa-links" aria-label="<?php esc_attr_e( 'Outras opções', 'eb-credito-rural' ); ?>">
			<a href="<?php echo esc_url( $switch_url ); ?>"><?php echo $backup ? esc_html__( 'Voltar para o código normal', 'eb-credito-rural' ) : esc_html__( 'Usar um código de backup', 'eb-credito-rural' ); ?></a>
			<a href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Sair', 'eb-credito-rural' ); ?></a>
		</nav>
		<p class="ebcr-2fa-foot">
			<?php
			/* translators: %s: nome de exibição */
			printf( esc_html__( 'Conectado como %s.', 'eb-credito-rural' ), '<strong>' . esc_html( $user ? $user->display_name : '' ) . '</strong>' );
			?>
			<?php esc_html_e( 'Perdeu o acesso ao aplicativo e aos códigos? Peça a um administrador para desativar a verificação da sua conta.', 'eb-credito-rural' ); ?>
		</p>
	</div>
</main>
</body>
</html>
