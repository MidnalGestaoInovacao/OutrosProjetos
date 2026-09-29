<?php
/**
 * Identificadores públicos.
 *
 * @package EBCR
 */

namespace EBCR\Support;

defined( 'ABSPATH' ) || exit;

/**
 * UUID v4 e validação.
 */
final class Uuid {

	/**
	 * Novo UUID v4.
	 *
	 * @return string
	 */
	public static function v4() {
		return wp_generate_uuid4();
	}

	/**
	 * Valida formato.
	 *
	 * @param mixed $value Valor.
	 * @return bool
	 */
	public static function is_valid( $value ) {
		return is_string( $value ) && wp_is_uuid( $value, 4 );
	}

	/**
	 * Sanitiza (retorna '' se inválido).
	 *
	 * @param mixed $value Valor.
	 * @return string
	 */
	public static function sanitize( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return self::is_valid( $value ) ? $value : '';
	}
}
