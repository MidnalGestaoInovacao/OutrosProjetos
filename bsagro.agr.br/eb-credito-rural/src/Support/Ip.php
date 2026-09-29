<?php
/**
 * Endereço IP do visitante.
 *
 * @package EBCR
 */

namespace EBCR\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Obtém o IP de forma conservadora (REMOTE_ADDR), com cabeçalho de proxy opcional configurado pelo administrador.
 */
final class Ip {

	/**
	 * IP atual.
	 *
	 * @return string
	 */
	public static function get() {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$header = strtoupper( str_replace( '-', '_', (string) Options::get( 'trusted_proxy_header', '' ) ) );
		if ( $header ) {
			$key = 'HTTP_' . preg_replace( '/[^A-Z0-9_]/', '', $header );
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$candidates = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				$first      = trim( $candidates[0] );
				if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
					$ip = $first;
				}
			}
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * User agent (truncado).
	 *
	 * @return string
	 */
	public static function user_agent() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return mb_substr( $ua, 0, 250 );
	}
}
