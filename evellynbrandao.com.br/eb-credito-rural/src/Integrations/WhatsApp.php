<?php
/**
 * Notificações por WhatsApp (Meta Cloud API) espelhando os eventos de e-mail. Entregue pelo módulo "Integrações".
 *
 * @package EBCR
 */

namespace EBCR\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Ouve ebcr_notification_sent e envia mensagens quando habilitado.
 */
final class WhatsApp {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {}
}
