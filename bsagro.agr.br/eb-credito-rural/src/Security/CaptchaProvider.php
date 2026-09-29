<?php
/**
 * Interface de provedores de captcha (permite trocar por Turnstile/hCaptcha no futuro).
 *
 * @package EBCR
 */

namespace EBCR\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Contrato do captcha.
 */
interface CaptchaProvider {

	/**
	 * Gera um desafio.
	 *
	 * @return array{token:string,question:string,aria:string}
	 */
	public function issue();

	/**
	 * Verifica (uso único).
	 *
	 * @param string $token  Token do desafio.
	 * @param string $answer Resposta do usuário.
	 * @return bool
	 */
	public function verify( $token, $answer );

	/**
	 * HTML do campo (rótulo, pergunta acessível, input e token).
	 *
	 * @return string
	 */
	public function field();
}
