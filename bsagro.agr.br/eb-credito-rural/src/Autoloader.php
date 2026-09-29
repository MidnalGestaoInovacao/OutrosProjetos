<?php
/**
 * Autoloader PSR-4 simples (sem Composer).
 *
 * @package EBCR
 */

namespace EBCR;

defined( 'ABSPATH' ) || exit;

/**
 * Mapeia EBCR\Sub\Classe => src/Sub/Classe.php.
 */
final class Autoloader {

	/**
	 * Registra o autoloader.
	 *
	 * @param string $base_dir Diretório src/ com barra final.
	 * @return void
	 */
	public static function register( $base_dir ) {
		spl_autoload_register(
			static function ( $class_name ) use ( $base_dir ) {
				$prefix = 'EBCR\\';
				if ( 0 !== strpos( $class_name, $prefix ) ) {
					return;
				}
				$relative = substr( $class_name, strlen( $prefix ) );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
