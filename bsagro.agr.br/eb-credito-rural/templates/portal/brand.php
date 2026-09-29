<?php
/**
 * Marca da operação (logotipo + nome) no topo da área do cliente, telas de acesso e painel da equipe.
 * Variáveis: $modifier (classe extra opcional). Desligável em Configurações → Identidade visual.
 *
 * @package EBCR
 */

use EBCR\Admin\Branding;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;
if ( ! Options::bool( 'brand_portal_header' ) ) {
	return;
}
$ebcr_logo = Branding::front_logo_url();
$ebcr_name = Branding::brand_name();
/**
 * Exibe o nome da marca ao lado do logotipo (quando há logotipo).
 *
 * @param bool $show Exibir.
 */
$ebcr_show_name = '' === $ebcr_logo || (bool) apply_filters( 'ebcr_brandbar_show_name', true );
?>
<div class="ebcr-brandbar<?php echo isset( $modifier ) && $modifier ? ' ' . esc_attr( $modifier ) : ''; ?>">
	<?php if ( '' !== $ebcr_logo ) : ?>
		<img src="<?php echo esc_url( $ebcr_logo ); ?>" alt="<?php echo $ebcr_show_name ? '' : esc_attr( $ebcr_name ); ?>" decoding="async">
	<?php endif; ?>
	<?php if ( $ebcr_show_name ) : ?>
		<span class="ebcr-brandbar-name"><?php echo esc_html( $ebcr_name ); ?></span>
	<?php endif; ?>
</div>
