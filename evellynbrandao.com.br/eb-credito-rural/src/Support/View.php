<?php
/**
 * Carregador de templates (sobrescrevíveis pelo tema em /eb-credito-rural/).
 *
 * @package EBCR
 */

namespace EBCR\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Renderização de templates PHP.
 */
final class View {

	/**
	 * Localiza um template: tema filho/pai (eb-credito-rural/{nome}.php) ou o do plugin.
	 *
	 * @param string $name Caminho relativo sem extensão (ex.: portal/dashboard).
	 * @return string
	 */
	public static function locate( $name ) {
		$name  = ltrim( str_replace( array( '..', '\\' ), '', $name ), '/' );
		$theme = locate_template( array( 'eb-credito-rural/' . $name . '.php' ) );
		if ( $theme ) {
			return $theme;
		}
		return EBCR_DIR . 'templates/' . $name . '.php';
	}

	/**
	 * Renderiza e retorna a saída.
	 *
	 * @param string $name Template.
	 * @param array  $data Variáveis disponíveis no template.
	 * @return string
	 */
	public static function render( $name, array $data = array() ) {
		$file = self::locate( $name );
		if ( ! file_exists( $file ) ) {
			return '';
		}
		ob_start();
		( static function ( $__file, $__data ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- variáveis nomeadas para o template; dados vêm do próprio plugin, não de input.
			foreach ( $__data as $__k => $__v ) {
				${$__k} = $__v;
			}
			unset( $__k, $__v );
			include $__file;
		} )( $file, $data );
		return (string) ob_get_clean();
	}

	/**
	 * Imprime o template.
	 *
	 * @param string $name Template.
	 * @param array  $data Dados.
	 * @return void
	 */
	public static function show( $name, array $data = array() ) {
		echo self::render( $name, $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- os templates escapam cada valor na saída.
	}
}
