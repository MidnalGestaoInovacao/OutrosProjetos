<?php
/**
 * Botões do formulário de complemento de bens e garantias (área do cliente). Variáveis: $s, $step.
 *
 * @package EBCR
 */

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_back = Helpers::portal_url(
	array(
		'ebcr_view' => 'solicitacao',
		'id'        => $s['public_id'],
	)
) . '#ebcr-bens';
?>
<div class="ebcr-actions ebcr-wizard-nav">
	<a class="ebcr-btn" href="<?php echo esc_url( $ebcr_back ); ?>"><?php esc_html_e( 'Cancelar', 'eb-credito-rural' ); ?></a>
	<button type="submit" class="ebcr-btn ebcr-btn--primary" data-next><?php esc_html_e( 'Salvar complemento', 'eb-credito-rural' ); ?></button>
</div>
