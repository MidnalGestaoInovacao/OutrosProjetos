<?php
/**
 * Honeypot e tempo mínimo de preenchimento (assinado com HMAC).
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Campos anti-bot invisíveis.
 */
final class Honeypot {

	/**
	 * Campos ocultos: honeypot + carimbo de tempo assinado.
	 *
	 * @return string
	 */
	public static function fields() {
		$ts   = (string) time();
		$sig  = self::sign( $ts );
		$html = sprintf( '<input type="hidden" name="ebcr_ts" value="%s">', esc_attr( $ts . '.' . $sig ) );
		if ( Options::bool( 'honeypot' ) ) {
			$html .= '<div class="ebcr-hp" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden"><label for="ebcr_website">' . esc_html__( 'Não preencha este campo', 'eb-credito-rural' ) . '</label><input type="text" id="ebcr_website" name="ebcr_website" tabindex="-1" autocomplete="off" value=""></div>';
		}
		return $html;
	}

	/**
	 * Valida honeypot e tempo mínimo.
	 *
	 * @param array $input Dados enviados.
	 * @return true|string true ou código do erro (honeypot|too_fast|bad_ts).
	 */
	public static function check( array $input ) {
		if ( Options::bool( 'honeypot' ) && ! empty( $input['ebcr_website'] ) ) {
			return 'honeypot';
		}
		$min = max( 0, Options::int( 'min_fill_seconds' ) );
		$raw = isset( $input['ebcr_ts'] ) ? (string) $input['ebcr_ts'] : '';
		if ( ! preg_match( '/^(\d{9,11})\.([a-f0-9]{16})$/', $raw, $m ) ) {
			return 'bad_ts';
		}
		if ( ! hash_equals( self::sign( $m[1] ), $m[2] ) ) {
			return 'bad_ts';
		}
		$elapsed = time() - (int) $m[1];
		if ( $elapsed < $min ) {
			return 'too_fast';
		}
		if ( $elapsed > DAY_IN_SECONDS ) {
			return 'bad_ts';
		}
		return true;
	}

	/**
	 * Assinatura curta.
	 *
	 * @param string $ts Timestamp.
	 * @return string
	 */
	private static function sign( $ts ) {
		return substr( hash_hmac( 'sha256', 'ebcr_ts|' . $ts, wp_salt( 'nonce' ) ), 0, 16 );
	}
}
