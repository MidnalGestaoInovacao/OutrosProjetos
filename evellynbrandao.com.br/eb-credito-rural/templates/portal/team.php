<?php
/**
 * Atalho para membros da equipe. Variável: $user.
 *
 * @package EBCR
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ebcr-card">
	<h2><?php /* translators: %s: nome */ printf( esc_html__( 'Olá, %s', 'eb-credito-rural' ), esc_html( $user->display_name ) ); ?></h2>
	<p><?php esc_html_e( 'Sua conta faz parte da equipe de análise. As solicitações dos clientes ficam no painel administrativo.', 'eb-credito-rural' ); ?></p>
	<p><a class="ebcr-btn ebcr-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-submissions' ) ); ?>"><?php esc_html_e( 'Abrir painel de solicitações', 'eb-credito-rural' ); ?></a> <a class="ebcr-btn" href="<?php echo esc_url( wp_logout_url( \EBCR\Support\Helpers::portal_url() ) ); ?>"><?php esc_html_e( 'Sair', 'eb-credito-rural' ); ?></a></p>
</div>
