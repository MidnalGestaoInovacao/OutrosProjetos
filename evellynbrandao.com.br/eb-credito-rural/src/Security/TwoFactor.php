<?php
/**
 * Verificação em duas etapas da equipe (TOTP por aplicativo ou código por e-mail), com códigos de backup e dispositivo confiável.
 * Entregue pelo módulo "2FA".
 *
 * @package EBCR
 */

namespace EBCR\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks de login, perfil e bloqueio do painel até a verificação.
 */
final class TwoFactor {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {}
}
