<?php
/**
 * Cabeçalho do portal (logado). Variáveis: $user, $view, $verified.
 *
 * @package EBCR
 */

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_nav = array(
	'painel'      => __( 'Minhas solicitações', 'eb-credito-rural' ),
	'perfil'      => __( 'Meu perfil', 'eb-credito-rural' ),
	'privacidade' => __( 'Privacidade', 'eb-credito-rural' ),
);
?>
<header class="ebcr-topbar">
	<div class="ebcr-topbar-user">
		<?php /* translators: %s: nome */ printf( esc_html__( 'Olá, %s', 'eb-credito-rural' ), '<strong>' . esc_html( $user->display_name ) . '</strong>' ); ?>
	</div>
	<nav class="ebcr-nav" aria-label="<?php esc_attr_e( 'Área do cliente', 'eb-credito-rural' ); ?>">
		<?php foreach ( $ebcr_nav as $ebcr_k => $ebcr_label ) : ?>
			<a class="ebcr-nav-link<?php echo $view === $ebcr_k || ( 'painel' === $ebcr_k && in_array( $view, array( 'solicitacao', 'formulario' ), true ) ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( Helpers::portal_url( array( 'ebcr_view' => $ebcr_k ) ) ); ?>"<?php echo $view === $ebcr_k ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $ebcr_label ); ?></a>
		<?php endforeach; ?>
		<a class="ebcr-nav-link" href="<?php echo esc_url( wp_logout_url( Helpers::portal_url( array( 'ebcr_msg' => 'logged_out' ) ) ) ); ?>"><?php esc_html_e( 'Sair', 'eb-credito-rural' ); ?></a>
	</nav>
</header>
<?php if ( ! $verified ) : ?>
<div class="ebcr-alert ebcr-alert--warning" role="status">
	<p><?php esc_html_e( 'Seu e-mail ainda não foi confirmado. Verifique sua caixa de entrada (e o spam). Sem a confirmação não é possível enviar solicitações.', 'eb-credito-rural' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ebcr-inline-form">
		<input type="hidden" name="action" value="ebcr_resend_confirm">
		<?php echo ebcr_nonce_field( 'resend' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<button type="submit" class="ebcr-btn ebcr-btn--small"><?php esc_html_e( 'Reenviar link de confirmação', 'eb-credito-rural' ); ?></button>
	</form>
</div>
<?php endif; ?>
