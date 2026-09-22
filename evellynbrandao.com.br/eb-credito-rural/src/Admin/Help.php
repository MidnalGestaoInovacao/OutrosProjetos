<?php
/**
 * Página de ajuda.
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Files\FileGuard;
use EBCR\Roles\Capabilities;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Primeiros passos, papéis, Nginx, FAQ e checklist de publicação.
 */
final class Help {

	/**
	 * Renderiza.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_VIEW ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		View::show(
			'admin/help',
			array(
				'guard' => new FileGuard(),
				'caps'  => Capabilities::map(),
			)
		);
	}
}
