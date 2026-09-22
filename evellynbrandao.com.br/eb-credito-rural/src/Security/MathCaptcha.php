<?php
/**
 * Captcha matemático gerado no servidor, resposta em transient de uso único.
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Soma/subtração/multiplicação com números de 1 a 20; expira em 10 minutos.
 */
final class MathCaptcha implements CaptchaProvider {

	const TTL = 600;

	/**
	 * Gera o desafio e armazena a resposta em transient atrelado ao token.
	 *
	 * @return array
	 */
	public function issue() {
		$hard = 'dificil' === Options::get( 'captcha_difficulty', 'normal' );
		$a    = wp_rand( 1, $hard ? 20 : 10 );
		$b    = wp_rand( 1, $hard ? 20 : 10 );
		$ops  = $hard ? array( '+', '-', 'x' ) : array( '+', '-' );
		$op   = $ops[ wp_rand( 0, count( $ops ) - 1 ) ];
		if ( '-' === $op && $b > $a ) {
			list( $a, $b ) = array( $b, $a );
		}
		if ( 'x' === $op ) {
			$a = wp_rand( 2, 9 );
			$b = wp_rand( 2, 9 );
		}
		$answer = '+' === $op ? $a + $b : ( '-' === $op ? $a - $b : $a * $b );
		$token  = Helpers::random_hex( 16 );
		set_transient( 'ebcr_captcha_' . $token, (string) $answer, self::TTL );
		$words    = array(
			'+' => __( 'mais', 'eb-credito-rural' ),
			'-' => __( 'menos', 'eb-credito-rural' ),
			'x' => __( 'vezes', 'eb-credito-rural' ),
		);
		$question = sprintf( '%d %s %d', $a, $op, $b );
		/* translators: 1: primeiro número, 2: operação por extenso, 3: segundo número */
		$aria = sprintf( __( 'Quanto é %1$d %2$s %3$d? Responda com um número.', 'eb-credito-rural' ), $a, $words[ $op ], $b );
		return array(
			'token'    => $token,
			'question' => $question,
			'aria'     => $aria,
		);
	}

	/**
	 * Verifica e invalida o token (uso único).
	 *
	 * @param string $token  Token.
	 * @param string $answer Resposta.
	 * @return bool
	 */
	public function verify( $token, $answer ) {
		$token = preg_replace( '/[^a-f0-9]/', '', (string) $token );
		if ( 32 !== strlen( $token ) ) {
			return false;
		}
		$key      = 'ebcr_captcha_' . $token;
		$expected = get_transient( $key );
		delete_transient( $key ); // uso único, mesmo em caso de erro.
		if ( false === $expected ) {
			return false;
		}
		$answer = trim( str_replace( ' ', '', (string) $answer ) );
		return '' !== $answer && is_numeric( $answer ) && (int) $answer === (int) $expected;
	}

	/**
	 * Campo HTML.
	 *
	 * @return string
	 */
	public function field() {
		$c  = $this->issue();
		$id = 'ebcr-captcha-' . substr( $c['token'], 0, 8 );
		return sprintf(
			'<div class="ebcr-field ebcr-captcha"><label for="%1$s">%2$s <strong class="ebcr-captcha-q" aria-hidden="true">%3$s = ?</strong></label>'
			. '<input type="text" inputmode="numeric" autocomplete="off" id="%1$s" name="ebcr_captcha_answer" required aria-label="%4$s" aria-describedby="%1$s-help" class="ebcr-input ebcr-input--short">'
			. '<span id="%1$s-help" class="ebcr-help">%5$s</span><input type="hidden" name="ebcr_captcha_token" value="%6$s"></div>',
			esc_attr( $id ),
			esc_html__( 'Verificação de segurança:', 'eb-credito-rural' ),
			esc_html( $c['question'] ),
			esc_attr( $c['aria'] ),
			esc_html__( 'Resolva a conta para provar que você não é um robô.', 'eb-credito-rural' ),
			esc_attr( $c['token'] )
		);
	}

	/**
	 * Provedor configurado.
	 *
	 * @return CaptchaProvider
	 */
	public static function provider() {
		$provider = new self();
		/**
		 * Permite substituir o provedor de captcha (ex.: Turnstile).
		 *
		 * @param CaptchaProvider $provider Provedor.
		 * @param string          $key      Chave configurada.
		 */
		$custom = apply_filters( 'ebcr_captcha_provider', $provider, Options::get( 'captcha_provider', 'math' ) );
		return $custom instanceof CaptchaProvider ? $custom : $provider;
	}

	/**
	 * Verifica a resposta enviada em um request ($_POST ou array).
	 *
	 * @param array $input Dados enviados.
	 * @return bool
	 */
	public static function verify_request( array $input ) {
		$token  = isset( $input['ebcr_captcha_token'] ) ? sanitize_text_field( (string) $input['ebcr_captcha_token'] ) : '';
		$answer = isset( $input['ebcr_captcha_answer'] ) ? sanitize_text_field( (string) $input['ebcr_captcha_answer'] ) : '';
		return self::provider()->verify( $token, $answer );
	}
}
