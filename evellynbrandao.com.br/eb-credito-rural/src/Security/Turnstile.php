<?php
/**
 * Provedor de captcha Cloudflare Turnstile (filtro ebcr_captcha_provider). Entregue pelo módulo "Integrações".
 *
 * @package EBCR
 */

namespace EBCR\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Registra o provedor quando selecionado em Configurações → Segurança.
 */
final class Turnstile {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {}
}
