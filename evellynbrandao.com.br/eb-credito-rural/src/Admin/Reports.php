<?php
/**
 * Relatórios por fundo/carteira, período e status, com exportação CSV. Entregue pelo módulo "Painel e relatórios".
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Roles\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Tela Crédito Rural → Relatórios.
 */
final class Reports {

	/**
	 * Hooks (exportação etc.).
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Renderiza a tela.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_DASHBOARD ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		echo '<div class="wrap ebcr-admin"><h1>' . esc_html__( 'Relatórios', 'eb-credito-rural' ) . '</h1><p>' . esc_html__( 'Em construção.', 'eb-credito-rural' ) . '</p></div>';
	}
}
