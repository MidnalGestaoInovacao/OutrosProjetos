<?php
/**
 * CRM: ficha por cliente, atividades e tarefas, lista e quadro Kanban por estágio, exportação CSV. Entregue pelo módulo "CRM".
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Roles\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Tela Crédito Rural → CRM.
 */
final class Crm {

	/**
	 * Hooks (ações admin-post, lembretes, REST do Kanban).
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Renderiza a tela (lista, quadro ou ficha conforme a query).
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_CRM ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		echo '<div class="wrap ebcr-admin"><h1>' . esc_html__( 'CRM', 'eb-credito-rural' ) . '</h1><p>' . esc_html__( 'Em construção.', 'eb-credito-rural' ) . '</p></div>';
	}
}
