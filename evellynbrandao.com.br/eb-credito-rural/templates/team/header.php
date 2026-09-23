<?php
/**
 * Cabeçalho do painel da equipe. Variáveis: $user, $screen, $nav, $show_admin, $admin_url, $logout_url.
 *
 * @package EBCR
 */

defined( 'ABSPATH' ) || exit;
?>
<header class="ebcr-topbar ebcr-tbar">
	<div class="ebcr-topbar-user">
		<?php /* translators: %s: nome */ printf( esc_html__( 'Olá, %s', 'eb-credito-rural' ), '<strong>' . esc_html( $user->display_name ) . '</strong>' ); ?>
		<span class="ebcr-tbar-sep" aria-hidden="true">·</span>
		<span class="ebcr-tbar-title"><?php esc_html_e( 'Painel da equipe', 'eb-credito-rural' ); ?></span>
	</div>
	<nav class="ebcr-nav ebcr-tnav" aria-label="<?php esc_attr_e( 'Painel da equipe', 'eb-credito-rural' ); ?>">
		<?php
		foreach ( $nav as $ebcr_k => $ebcr_item ) :
			$ebcr_active = $screen === $ebcr_k || ( 'solicitacoes' === $ebcr_k && 'solicitacao' === $screen );
			?>
			<a class="ebcr-nav-link<?php echo $ebcr_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $ebcr_item['url'] ); ?>"<?php echo $ebcr_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $ebcr_item['label'] ); ?></a>
		<?php endforeach; ?>
		<?php if ( $show_admin ) : ?>
			<a class="ebcr-nav-link ebcr-nav-link--muted" href="<?php echo esc_url( $admin_url ); ?>">wp-admin</a>
		<?php endif; ?>
		<a class="ebcr-nav-link" href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Sair', 'eb-credito-rural' ); ?></a>
	</nav>
</header>
